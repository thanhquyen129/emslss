<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../../login.php");
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/config.php';

$message = '';
$pdo = null;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $company_name = trim($_POST['company_name']);
    $role = $_POST['role'];
    $email = trim($_POST['email']);
    // Chuyển chuỗi rỗng thành NULL để tránh lỗi UNIQUE constraint nếu email không được điền
    $email = empty($email) ? null : $email;
    $phone = trim($_POST['phone']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($username) || empty($password) || empty($company_name) || empty($role)) {
        $message = '<p style="color: red;">Vui lòng điền đầy đủ các trường bắt buộc (Username, Mật khẩu, Tên Công ty, Vai trò).</p>';
    } elseif (strlen($username) > 10) {
        $message = '<p style="color: red;">Username không được dài quá 10 ký tự.</p>';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM vmlbooking_users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $message = '<p style="color: red;">Username đã tồn tại. Vui lòng chọn username khác.</p>';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO vmlbooking_users (username, password, company_name, role, email, phone, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $company_name, $role, $email, $phone, $is_active]);

                $message = '<p style="color: green;">Người dùng "' . htmlspecialchars($username) . '" đã được thêm thành công!</p>';
            }

        } catch (PDOException $e) {
            $message = '<p style="color: red;">Lỗi khi thêm người dùng: ' . $e->getMessage() . '</p>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Người dùng Mới - Booking System Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 600px; margin: auto; }
        h2 { color: #0056b3; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group select {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group input[type="checkbox"] {
            margin-right: 5px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            color: #fff;
            background-color: #007bff;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-success { background-color: #28a745; }
        .btn-back { background-color: #6c757d; margin-left: 10px; }
        .message { margin-top: 15px; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Thêm Người dùng Mới</h2>

        <?php echo $message; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="username">Username: <span style="color: red;">*</span></label>
                <input type="text" id="username" name="username" maxlength="10" required>
                <small>Tối đa 10 ký tự.</small>
            </div>
            <div class="form-group">
                <label for="password">Mật khẩu: <span style="color: red;">*</span></label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="company_name">Tên Công ty: <span style="color: red;">*</span></label>
                <input type="text" id="company_name" name="company_name" required>
            </div>
            <div class="form-group">
                <label for="role">Vai trò: <span style="color: red;">*</span></label>
                <select id="role" name="role" required>
                    <option value="agency">Agency</option>
                    <option value="admin">Admin</option>
                    <option value="viewer">Viewer</option>
                    <option value="accounting">Accounting</option>
                </select>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email">
            </div>
            <div class="form-group">
                <label for="phone">Điện thoại:</label>
                <input type="tel" id="phone" name="phone">
            </div>
            <div class="form-group">
                <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                <label for="is_active" style="display: inline;">Hoạt động</label>
            </div>
            <button type="submit" class="btn btn-success">Thêm Người dùng</button>
            <a href="index.php" class="btn btn-back">Quay lại danh sách</a>
        </form>
    </div>
</body>
</html>