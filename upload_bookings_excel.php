<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php'; // Đảm bảo đường dẫn đúng đến file config.php
require_once 'includes/functions.php'; // Đảm bảo đường dẫn đúng đến file functions.php và có hàm sanitize_input
require_once 'vendor/autoload.php'; // Đảm bảo đường dẫn đúng đến autoload của PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['user_id'])) {
    $_SESSION['booking_message'] = "<p class='message error'>❌ Bạn cần đăng nhập để thực hiện thao tác này.</p>";
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'] ?? 'agency';
$current_user_company_name = $_SESSION['company_name'] ?? '';

// Kiểm tra quyền truy cập: chỉ Admin và Agency mới có thể upload
if (!in_array($current_user_role, ['admin', 'agency'])) {
    $_SESSION['booking_message'] = "<p class='message error'>❌ Bạn không có quyền tải lên đơn hàng.</p>";
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excel_file"])) {
    $upload_dir = 'uploads/temp/'; // Thư mục tạm để lưu file upload
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); // Tạo thư mục nếu chưa có
    }

    $file_name = $_FILES["excel_file"]["name"];
    $file_tmp_name = $_FILES["excel_file"]["tmp_name"];
    $file_error = $_FILES["excel_file"]["error"];
    $file_size = $_FILES["excel_file"]["size"];

    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_ext = ['xls', 'xlsx'];

    $upload_success_count = 0;
    $upload_error_count = 0;
    $error_details = [];

    if ($file_error !== UPLOAD_ERR_OK) {
        $_SESSION['booking_message'] = "<p class='message error'>Lỗi tải file lên: " . get_upload_error_message($file_error) . "</p>";
        header("Location: index.php");
        exit;
    }

    if (!in_array($file_ext, $allowed_ext)) {
        $_SESSION['booking_message'] = "<p class='message error'>❌ Chỉ cho phép file Excel (.xls hoặc .xlsx).</p>";
        header("Location: index.php");
        exit;
    }

    // Di chuyển file tạm vào thư mục tạm của chúng ta
    $temp_file_path = $upload_dir . uniqid('upload_') . '.' . $file_ext;
    if (!move_uploaded_file($file_tmp_name, $temp_file_path)) {
        $_SESSION['booking_message'] = "<p class='message error'>❌ Không thể di chuyển file đã tải lên.</p>";
        header("Location: index.php");
        exit;
    }

    try {
        if (!isset($pdo) || $pdo === null) {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        $spreadsheet = IOFactory::load($temp_file_path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        // Lấy tiêu đề cột từ hàng đầu tiên
        $header = [];
        $header_cells = $sheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE)[0];
        foreach ($header_cells as $col_index => $col_name) {
            $header[trim($col_name)] = $col_index;
        }

        error_log("DEBUG: Excel Headers (trimmed): " . print_r($header, true));

        // Định nghĩa ánh xạ các cột từ Excel sang tên cột database
        $column_map = [
            'Người LH (contact name) (Người gửi)' => 'shipper_contact',
            'Địa chỉ (Người gửi)' => 'shipper_address',
            'ĐT (tel) (Người gửi)' => 'shipper_phone',
            'Mã số thuế/CCCD/CMND (Người gửi)' => 'shipper_id_tax',
            'Email (Người gửi)' => 'shipper_email',
            'Quốc gia (Người gửi)' => 'shipper_country',
            'Cân nặng (gross weight) (kg)' => 'gross_weight',
            'Số lượng kiện (No. of packages)' => 'number_of_packages',
            'Kích thước (Dài x Rộng x Cao) (cm)' => 'dimensions_text',
            'Loại hình dịch vụ (Service Type)' => 'service_type',
            'Loại (type)' => 'shipment_type',
            'Kho hàng' => 'warehouse',
            'Nước đến (country)' => 'receiver_country',
            'Cty (company name) (Người nhận)' => 'receiver_company',
            'Người LH (contact name) (Người nhận)' => 'receiver_contact',
            'ĐT (tel) (Người nhận)' => 'receiver_phone',
            'Tax ID (Người nhận)' => 'receiver_tax_id',
            'Email (Người nhận)' => 'receiver_email',
            'Postal code (Người nhận)' => 'receiver_postal_code',
            'Thành phố (city) (Người nhận)' => 'receiver_city',
            'Tỉnh (State) (Người nhận)' => 'receiver_state',
            'Địa chỉ 1 (address 1) (Người nhận)' => 'receiver_address1',
            'Địa chỉ 2 (address 2) (Người nhận)' => 'receiver_address2',
            'Địa chỉ 3 (address 3) (Người nhận)' => 'receiver_address3',
            'Chi phí (Cost)' => 'cost', // Chỉ admin/accounting
            'Giá bán (Sales Price)' => 'sales_price', // Chỉ admin/accounting
            'Ghi chú (Note)' => 'note', // Chỉ admin/accounting
        ];

        // Chuẩn bị câu lệnh INSERT
        $insert_sql_columns = "user_id, shipper_agency_name, reference_code, booking_status, created_at, updated_at,
            shipper_contact, shipper_address, shipper_phone, shipper_id_tax, shipper_email, shipper_country,
            gross_weight, number_of_packages, dimensions_text, service_type, shipment_type, warehouse,
            receiver_country, receiver_company, receiver_contact, receiver_phone, receiver_tax_id, receiver_email, receiver_postal_code,
            receiver_city, receiver_state, receiver_address1, receiver_address2, receiver_address3";

        $insert_values_placeholder_base = "?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?";
        
        // Thêm cột cost, sales_price, note nếu người dùng có quyền admin/accounting
        if (in_array($current_user_role, ['admin', 'accounting'])) {
            $insert_sql_columns .= ", cost, sales_price, note";
            $insert_values_placeholder_base .= ", ?, ?, ?";
        }
        
        $insert_sql = "INSERT INTO vmlbooking_orders ($insert_sql_columns) VALUES ($insert_values_placeholder_base)";
        $stmt_insert = $pdo->prepare($insert_sql);

        // Lặp qua từng hàng dữ liệu (bắt đầu từ hàng thứ 2)
        for ($row = 2; $row <= $highestRow; $row++) {
            $row_data = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE)[0];
            $booking_data = [];
            $row_errors = [];

            // Ánh xạ dữ liệu từ hàng Excel vào mảng booking_data
            foreach ($column_map as $excel_col_name => $db_col_name) {
                $col_index = $header[$excel_col_name] ?? null;

                error_log("DEBUG: Row " . $row . ", Excel Column Name: '" . $excel_col_name . "'");
                error_log("DEBUG: Col Index found for '" . $excel_col_name . "': " . ($col_index !== null ? $col_index : 'NOT FOUND'));
                if ($col_index !== null && isset($row_data[$col_index])) {
                    error_log("DEBUG: Raw data from Excel for '" . $excel_col_name . "': '" . $row_data[$col_index] . "' (Length: " . strlen($row_data[$col_index]) . ")");
                } else {
                    error_log("DEBUG: Data for '" . $excel_col_name . "' is not set or col_index is null.");
                }

                if ($col_index !== null && isset($row_data[$col_index])) {
                    // Cập nhật: trim mạnh mẽ hơn với non-breaking space
                    $booking_data[$db_col_name] = trim($row_data[$col_index], " \t\n\r\0\x0B\xC2\xA0");
                } else {
                    $booking_data[$db_col_name] = null; 
                }
            }
            
            error_log("DEBUG: Processing row " . $row);
            
            if (isset($booking_data) && is_array($booking_data)) {
                error_log("DEBUG: Final booking_data for row " . $row . ": " . print_r($booking_data, true));
                
                // Debug chi tiết cho các trường gây lỗi
                error_log("DEBUG: shipper_contact value after aggressive trim: " . var_export($booking_data['shipper_contact'] ?? 'NULL', true) . " (Length: " . (isset($booking_data['shipper_contact']) ? strlen($booking_data['shipper_contact']) : 0) . ")");
                error_log("DEBUG: receiver_address1 value after aggressive trim: " . var_export($booking_data['receiver_address1'] ?? 'NULL', true) . " (Length: " . (isset($booking_data['receiver_address1']) ? strlen($booking_data['receiver_address1']) : 0) . ")");

            } else {
                error_log("DEBUG ERROR: \$booking_data is not set or not an array for row " . $row . ". Type: " . gettype($booking_data) . " Isset: " . (isset($booking_data) ? 'true' : 'false'));
            }

            // Xử lý các giá trị mặc định và xác thực
            $booking_data['shipper_country'] = $booking_data['shipper_country'] ?? 'Vietnam';
            $booking_data['shipment_type'] = $booking_data['shipment_type'] ?? 'PACK';
            $booking_data['number_of_packages'] = intval($booking_data['number_of_packages'] ?? 1);
            $booking_data['gross_weight'] = floatval($booking_data['gross_weight'] ?? 0);

            $booking_user_id = $current_user_id;
            $booking_shipper_agency_name = $current_user_company_name;

            // Xác thực dữ liệu bắt buộc
            // Cập nhật: Thêm điều kiện kiểm tra chuỗi rỗng nghiêm ngặt hơn
            if (empty($booking_data['shipper_contact']) || $booking_data['shipper_contact'] === '') { $row_errors[] = "Người LH (người gửi) không được trống."; }
            if (empty($booking_data['receiver_contact']) || $booking_data['receiver_contact'] === '') { $row_errors[] = "Người LH (người nhận) không được trống."; }
            if (empty($booking_data['receiver_phone']) || $booking_data['receiver_phone'] === '') { $row_errors[] = "ĐT (người nhận) không được trống."; }
            if ($booking_data['gross_weight'] <= 0) { $row_errors[] = "Cân nặng phải lớn hơn 0."; }
            if (empty($booking_data['service_type']) || $booking_data['service_type'] === '') { $row_errors[] = "Loại hình dịch vụ không được trống."; }
            if (empty($booking_data['receiver_country']) || $booking_data['receiver_country'] === '') { $row_errors[] = "Nước đến không được trống."; }
            if (empty($booking_data['receiver_address1']) || $booking_data['receiver_address1'] === '') { $row_errors[] = "Địa chỉ 1 (người nhận) không được trống."; }
            
            // Nếu có lỗi, bỏ qua dòng này và ghi lại lỗi
            if (!empty($row_errors)) {
                $upload_error_count++;
                $error_details[] = "Dòng " . $row . ": " . implode("; ", $row_errors);
                continue; // Chuyển sang dòng tiếp theo
            }

            // Tạo mã booking duy nhất
            $reference_code = generateUniqueReferenceCode($pdo, $booking_user_id);

            // Chuẩn bị các tham số cho câu lệnh SQL
            $params = [
                $booking_user_id,
                $booking_shipper_agency_name,
                $reference_code,
                'Pending', // Trạng thái mặc định khi tạo booking
                $booking_data['shipper_contact'],
                $booking_data['shipper_address'],
                $booking_data['shipper_phone'],
                $booking_data['shipper_id_tax'],
                $booking_data['shipper_email'],
                $booking_data['shipper_country'],
                $booking_data['gross_weight'],
                $booking_data['number_of_packages'],
                $booking_data['dimensions_text'],
                $booking_data['service_type'],
                $booking_data['shipment_type'],
                $booking_data['warehouse'],
                $booking_data['receiver_country'],
                $booking_data['receiver_company'],
                $booking_data['receiver_contact'],
                $booking_data['receiver_phone'],
                $booking_data['receiver_tax_id'],
                $booking_data['receiver_email'],
                $booking_data['receiver_postal_code'],
                $booking_data['receiver_city'],
                $booking_data['receiver_state'],
                $booking_data['receiver_address1'],
                $booking_data['receiver_address2'],
                $booking_data['receiver_address3']
            ];

            // Thêm cost, sales_price, note nếu có quyền
            if (in_array($current_user_role, ['admin', 'accounting'])) {
                $params[] = floatval($booking_data['cost'] ?? 0);
                $params[] = floatval($booking_data['sales_price'] ?? 0);
                $params[] = $booking_data['note'] ?? '';
            }

            // ========================= BẮT ĐẦU PHẦN DEBUG MỚI - FINAL SQL VÀ PARAM COUNT =========================
            error_log("DEBUG: Final SQL query: " . $insert_sql);
            error_log("DEBUG: Number of parameters for execute: " . count($params));
            error_log("DEBUG: Parameters array: " . print_r($params, true)); // In ra toàn bộ mảng params
            // ========================= KẾT THÚC PHẦN DEBUG MỚI =========================

            try {
                $stmt_insert->execute($params);
                $upload_success_count++;
            } catch (PDOException $e) {
                $upload_error_count++;
                $error_details[] = "Dòng " . $row . " (Mã: " . htmlspecialchars($reference_code) . "): Lỗi database - " . htmlspecialchars($e->getMessage());
            }
        }

        $summary_message = "Hoàn thành tải lên booking từ Excel:<br>";
        $summary_message .= "✅ Thành công: " . $upload_success_count . " booking.<br>";
        if ($upload_error_count > 0) {
            $summary_message .= "❌ Lỗi: " . $upload_error_count . " booking.<br>";
            $summary_message .= "Chi tiết lỗi:<br>" . implode("<br>", $error_details);
            $_SESSION['booking_message'] = "<p class='message warning'>" . $summary_message . "</p>";
        } else {
            $_SESSION['booking_message'] = "<p class='message success'>" . $summary_message . "</p>";
        }

    } catch (Exception $e) {
        $_SESSION['booking_message'] = "<p class='message error'>Lỗi xử lý file Excel: " . htmlspecialchars($e->getMessage()) . "</p>";
    } finally {
        // Xóa file tạm sau khi xử lý
        if (file_exists($temp_file_path)) {
            unlink($temp_file_path);
        }
    }
    header("Location: index.php");
    exit;
} else {
    $_SESSION['booking_message'] = "<p class='message error'>❌ Không có file được tải lên hoặc phương thức không hợp lệ.</p>";
    header("Location: index.php");
    exit;
}

// Hàm hỗ trợ lấy thông báo lỗi upload
function get_upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return "Kích thước file vượt quá giới hạn cho phép trong php.ini.";
        case UPLOAD_ERR_FORM_SIZE:
            return "Kích thước file vượt quá giới hạn FORM_SIZE.";
        case UPLOAD_ERR_PARTIAL:
            return "File chỉ được tải lên một phần.";
        case UPLOAD_ERR_NO_FILE:
            return "Không có file nào được chọn.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Thiếu thư mục tạm thời.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Không thể ghi file vào đĩa.";
        case UPLOAD_ERR_EXTENSION:
            return "Một PHP extension đã dừng quá trình tải file.";
        default:
            return "Lỗi không xác định.";
    }
}
?>