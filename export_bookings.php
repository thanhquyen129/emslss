<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php'; // Đảm bảo đường dẫn đúng đến file config.php
require_once 'includes/functions.php'; // Đảm bảo đường dẫn đúng đến file functions.php và có hàm sanitize_input

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['user_id'])) {
    // Chuyển hướng về trang đăng nhập nếu chưa đăng nhập
    $_SESSION['booking_message'] = "<p class='message error'>❌ Bạn cần đăng nhập để thực hiện thao tác này.</p>";
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'] ?? 'agency'; // Lấy vai trò của người dùng, mặc định là 'agency'

// Kiểm tra quyền truy cập: chỉ Admin, Accounting, Agency, Viewer mới có thể xuất dữ liệu
if (!in_array($current_user_role, ['admin', 'accounting', 'agency', 'viewer'])) {
    die("Bạn không có quyền truy cập chức năng này.");
}

try {
    if (!isset($pdo) || $pdo === null) {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    // --- Xây dựng câu truy vấn SQL dựa trên các bộ lọc từ GET request ---
    $sql = "SELECT o.*, u.company_name as user_company_name FROM vmlbooking_orders o JOIN vmlbooking_users u ON o.user_id = u.id WHERE 1=1";
    $params = [];

    // Lọc theo từ khóa tìm kiếm (reference_code, shipper_contact, receiver_contact, etc.)
    if (!empty($_GET['search_query'])) {
        $search_query = '%' . sanitize_input($_GET['search_query']) . '%';
        $sql .= " AND (o.reference_code LIKE ? OR o.shipper_contact LIKE ? OR o.receiver_contact LIKE ? OR o.receiver_company LIKE ?)";
        $params[] = $search_query;
        $params[] = $search_query;
        $params[] = $search_query;
        $params[] = $search_query;
    }

    // Lọc theo ngày tạo (created_at)
    if (!empty($_GET['start_date'])) {
        $start_date = sanitize_input($_GET['start_date']);
        $sql .= " AND DATE(o.created_at) >= ?";
        $params[] = $start_date;
    }
    if (!empty($_GET['end_date'])) {
        $end_date = sanitize_input($_GET['end_date']);
        $sql .= " AND DATE(o.created_at) <= ?";
        $params[] = $end_date;
    }

    // Lọc theo trạng thái booking
    if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
        $status = sanitize_input($_GET['status']);
        $sql .= " AND o.booking_status = ?";
        $params[] = $status;
    }

    // Lọc theo Agency (chỉ Admin và Accounting mới có thể lọc theo Agency)
    if (in_array($current_user_role, ['admin', 'accounting']) && !empty($_GET['agency_id']) && $_GET['agency_id'] !== 'all') {
        $agency_id = (int)sanitize_input($_GET['agency_id']);
        $sql .= " AND o.user_id = ?";
        $params[] = $agency_id;
    }

    // Lọc theo Quốc gia nhận
    if (!empty($_GET['receiver_country'])) {
        $receiver_country = sanitize_input($_GET['receiver_country']);
        $sql .= " AND o.receiver_country LIKE ?";
        $params[] = '%' . $receiver_country . '%';
    }

    // Lọc theo loại dịch vụ
    if (!empty($_GET['service_type'])) {
        $service_type = sanitize_input($_GET['service_type']);
        $sql .= " AND o.service_type LIKE ?";
        $params[] = '%' . $service_type . '%';
    }

    // --- ÁP DỤNG PHÂN QUYỀN TRUY VẤN DỮ LIỆU ---
    // Agency chỉ được xem booking của chính họ
    if ($current_user_role === 'agency') {
        $sql .= " AND o.user_id = ?";
        $params[] = $current_user_id;
    }

    $sql .= " ORDER BY o.created_at DESC"; // Sắp xếp theo ngày tạo mới nhất

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();

    // --- Chuẩn bị dữ liệu để xuất ra CSV ---

    // 1. Định nghĩa các cột tiêu đề
    $header_row = [
        'ID Booking',
        'Mã Booking',
        'Ngày tạo',
        'Tên Agency',
        'Người gửi',
        'SĐT Người gửi',
        'Email Người gửi',
        'Quốc gia gửi',
        'Người nhận',
        'Cty Người nhận',
        'SĐT Người nhận',
        'Email Người nhận',
        'Quốc gia nhận',
        'Địa chỉ nhận 1',
        'Địa chỉ nhận 2',
        'Địa chỉ nhận 3',
        'Thành phố nhận',
        'Tỉnh nhận',
        'Mã bưu chính nhận',
        'Cân nặng (kg)',
        'Số kiện',
        'Kích thước',
        'Loại dịch vụ',
        'Loại hàng',
        'Kho hàng',
        'Trạng thái Booking'
    ];

    // Thêm các cột tài chính nếu người dùng có quyền
    if (in_array($current_user_role, ['admin', 'accounting'])) {
        $header_row[] = 'Chi phí';
        $header_row[] = 'Giá bán';
        $header_row[] = 'Ghi chú';
    }

    // 2. Thiết lập HTTP Headers để trình duyệt tải về file CSV
    $filename = 'danh_sach_booking_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8'); // Đảm bảo UTF-8 để hỗ trợ tiếng Việt
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Mở một "file" ảo để ghi CSV
    $output = fopen('php://output', 'w');

    // Ghi BOM (Byte Order Mark) cho UTF-8 để Excel đọc đúng tiếng Việt
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Ghi dòng tiêu đề
    fputcsv($output, $header_row);

    // 3. Ghi dữ liệu booking
    foreach ($bookings as $booking) {
        $data_row = [
            $booking['id'],
            $booking['reference_code'],
            date('d-m-Y H:i:s', strtotime($booking['created_at'])),
            $booking['user_company_name'], // Tên Agency từ bảng users
            $booking['shipper_contact'],
            $booking['shipper_phone'],
            $booking['shipper_email'],
            $booking['shipper_country'],
            $booking['receiver_contact'],
            $booking['receiver_company'],
            $booking['receiver_phone'],
            $booking['receiver_email'],
            $booking['receiver_country'],
            $booking['receiver_address1'],
            $booking['receiver_address2'],
            $booking['receiver_address3'],
            $booking['receiver_city'],
            $booking['receiver_state'],
            $booking['receiver_postal_code'],
            $booking['gross_weight'],
            $booking['number_of_packages'],
            $booking['dimensions_text'],
            $booking['service_type'],
            $booking['shipment_type'],
            $booking['warehouse'],
            $booking['booking_status']
        ];

        // Thêm dữ liệu tài chính nếu người dùng có quyền
        if (in_array($current_user_role, ['admin', 'accounting'])) {
            $data_row[] = $booking['cost'];
            $data_row[] = $booking['sales_price'];
            $data_row[] = $booking['note'];
        }
        
        fputcsv($output, $data_row);
    }

    // Đóng file ảo
    fclose($output);

    exit; // Quan trọng: Dừng script sau khi xuất file

} catch (PDOException $e) {
    // Xóa bất kỳ output nào trước đó để tránh lỗi header
    ob_clean();
    error_log("Lỗi database khi xuất CSV: " . $e->getMessage());
    die("Lỗi hệ thống khi xuất dữ liệu: " . htmlspecialchars($e->getMessage()));
} catch (Exception $e) {
    ob_clean();
    error_log("Lỗi chung khi xuất CSV: " . $e->getMessage());
    die("Lỗi hệ thống khi xuất dữ liệu: " . htmlspecialchars($e->getMessage()));
}
?>