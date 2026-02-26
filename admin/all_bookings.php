<?php
// admin/all_bookings.php - Trang hiển thị tất cả các đơn hàng cho Admin và Viewer
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php'; // Đã sửa đường dẫn thành '../includes/config.php'

// Kiểm tra quyền truy cập: Chỉ admin và viewer được phép
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'viewer')) {
    header("Location: ../../login.php"); // Chuyển hướng nếu không có quyền
    exit;
}

$pdo = null;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}

$orders = []; // Đổi tên biến từ bookings thành orders cho phù hợp với vmlbooking_orders
try {
    // Truy vấn TẤT CẢ các đơn hàng từ bảng vmlbooking_orders
    // và lấy thông tin công ty của người tạo đơn từ vmlbooking_users
    $stmt = $pdo->prepare("SELECT 
                                b.id, 
                                b.user_id, 
                                b.shipper_agency_name,
                                b.shipper_contact,
                                b.reference_code,
                                b.service_type,
                                b.gross_weight,
                                b.number_of_packages,
                                b.receiver_company,
                                b.receiver_contact,
                                b.status,
                                b.created_at,
                                u.company_name AS user_company_name 
                           FROM vmlbooking_orders b
                           JOIN vmlbooking_users u ON b.user_id = u.id
                           ORDER BY b.created_at DESC, b.id DESC"); // Đã sửa: sắp xếp theo 'created_at'
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC); // Đổi tên biến

} catch (PDOException $e) {
    echo "<p style='color: red;'>Lỗi khi tải danh sách đơn hàng: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tất Cả Đơn Hàng - Booking System Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 1400px; margin: auto; }
        h2 { color: #0056b3; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; } /* Giảm font-size cho bảng rộng */
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; color: #555; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .no-orders { text-align: center; color: #777; margin-top: 20px; }
        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            color: #fff;
            background-color: #007bff;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin-right: 5px;
        }
        .btn-info { background-color: #17a2b8; }
        .btn-primary { background-color: #007bff; }
        .btn-back { background-color: #6c757d; margin-top: 20px; display: inline-block;}
        /* actions-column có thể không cần nữa nếu không có nút sửa/xóa trên trang này */
    </style>
</head>
<body>
    <div class="container">
        <h2>Tất Cả Đơn Hàng</h2>

        <?php if (empty($orders)): ?>
            <p class="no-orders">Chưa có đơn hàng nào trong hệ thống.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Đơn hàng</th>
                        <th>Công ty gửi hàng (Agency)</th>
                        <th>Người liên hệ gửi</th>
                        <th>Công ty tạo đơn (User)</th>
                        <th>Mã tham chiếu</th>
                        <th>Loại dịch vụ</th>
                        <th>Số kiện</th>
                        <th>Tổng trọng lượng</th>
                        <th>Công ty nhận hàng</th>
                        <th>Người liên hệ nhận</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): // Đổi tên biến ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['id']); ?></td>
                        <td><?php echo htmlspecialchars($order['shipper_agency_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['shipper_contact']); ?></td>
                        <td><?php echo htmlspecialchars($order['user_company_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['reference_code']); ?></td>
                        <td><?php echo htmlspecialchars($order['service_type']); ?></td>
                        <td><?php echo htmlspecialchars($order['number_of_packages']); ?></td>
                        <td><?php echo htmlspecialchars($order['gross_weight']); ?></td>
                        <td><?php echo htmlspecialchars($order['receiver_company']); ?></td>
                        <td><?php echo htmlspecialchars($order['receiver_contact']); ?></td>
                        <td><?php echo htmlspecialchars($order['status']); ?></td>
                        <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <a href="../index.php" class="btn btn-back">Quay lại Trang chủ</a>
    </div>
</body>
</html>