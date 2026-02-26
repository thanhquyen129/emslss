<?php
session_start(); // Đảm bảo session đã được bắt đầu

// Kiểm tra xem người dùng đã đăng nhập chưa và có vai trò 'admin' không
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    // Nếu không phải admin hoặc chưa đăng nhập, chuyển hướng về trang đăng nhập
    header("Location: ../../login.php");
    exit; // Dừng script ngay lập tức
}
// admin/users/delete.php - Xử lý xóa người dùng
// QUAN TRỌNG: Script này hiện chưa có hệ thống xác thực và phân quyền.

ini_set('display_errors', 1); // Bật hiển thị lỗi
ini_set('display_startup_errors', 1); // Bật hiển thị lỗi khởi tạo
error_reporting(E_ALL); // Báo cáo tất cả các loại lỗi

require_once '../../includes/config.php'; // Điều chỉnh đường dẫn nếu file config.php không đúng vị trí này

// Khởi tạo PDO
$pdo = null;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}

$user_id = null;
$username = '';
$message = '';

// Kiểm tra xem có ID người dùng được truyền qua URL không
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = $_GET['id'];

    // Lấy tên người dùng để hiển thị trong thông báo xác nhận
    try {
        $stmt = $pdo->prepare("SELECT username FROM vmlbooking_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $username = htmlspecialchars($user['username']);
        } else {
            $message = '<p style="color: red;">Không tìm thấy người dùng này.</p>';
            $user_id = null; // Đặt lại ID để không xử lý xóa
        }
    } catch (PDOException $e) {
        die("Lỗi truy vấn database: " . $e->getMessage());
    }
} else {
    // Nếu không có ID hoặc ID không hợp lệ, chuyển hướng về trang danh sách
    header('Location: index.php');
    exit();
}

// Xử lý khi người dùng xác nhận xóa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']) && $user_id !== null) {
    try {
        $stmt = $pdo->prepare("DELETE FROM vmlbooking_users WHERE id = ?");
        $stmt->execute([$user_id]);

        $message = '<p style="color: green;">Người dùng "' . $username . '" đã được xóa thành công!</p>';
        // Chuyển hướng về trang danh sách sau khi xóa
        header('Location: index.php?status=deleted&username=' . urlencode($username));
        exit();
    } catch (PDOException $e) {
        $message = '<p style="color: red;">Lỗi khi xóa người dùng: ' . $e->getMessage() . '</p>';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_delete'])) {
    // Nếu hủy xóa, chuyển hướng về trang danh sách
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xóa Người dùng - Booking System Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 500px; margin: auto; text-align: center; }
        h2 { color: #dc3545; }
        .message { margin-top: 15px; padding: 10px; border-radius: 5px; }
        .confirmation { margin-top: 20px; }
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            color: #fff;
            background-color: #007bff;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        .btn-danger { background-color: #dc3545; }
        .btn-secondary { background-color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Xóa Người dùng</h2>

        <?php echo $message; // Hiển thị thông báo lỗi hoặc thành công nếu có ?>

        <?php if ($user_id !== null): // Chỉ hiển thị form xác nhận nếu tìm thấy người dùng ?>
            <div class="confirmation">
                <p>Bạn có chắc chắn muốn xóa người dùng **<?php echo $username; ?>**?</p>
                <p style="color: red;">Hành động này không thể hoàn tác.</p>
                <form action="" method="POST">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <button type="submit" name="confirm_delete" class="btn btn-danger">Xác nhận xóa</button>
                    <button type="submit" name="cancel_delete" class="btn btn-secondary">Hủy</button>
                </form>
            </div>
        <?php else: ?>
            <p>Không có người dùng nào để xóa hoặc ID không hợp lệ.</p>
            <a href="index.php" class="btn btn-secondary">Quay lại danh sách</a>
        <?php endif; ?>
    </div>
</body>
</html>