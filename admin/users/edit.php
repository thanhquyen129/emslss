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
$user_data = [];

$pdo = null;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}

$user_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = (int)$_GET['id'];

    try {
        $stmt = $pdo->prepare("SELECT id, username, company_name, role, email, phone, is_active FROM vmlbooking_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user_data) {
            $message = '<p style="color: red;">Không tìm thấy người dùng này.</p>';
            $user_id = null;
        }
    } catch (PDOException $e) {
        $message = '<p style="color: red;">Lỗi truy vấn thông tin người dùng: ' . $e->getMessage() . '</p>';
        $user_id = null;
    }
} else {
    $message = '<p style="color: red;">ID người dùng không hợp lệ.</p>';
    $user_id = null;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id !== null) {
    $new_username = trim($_POST['username']);
    $new_password = $_POST['password'];
    $new_company_name = trim($_POST['company_name']);
    $new_role = $_POST['role'];
    $new_email = trim($_POST['email']);
    // Chuyển chuỗi rỗng thành NULL để tránh lỗi UNIQUE constraint nếu email không được điền
    $new_email = empty($new_email) ? null : $new_email;
    $new_phone = trim($_POST['phone']);
    $new_is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($new_username) || empty($new_company_name) || empty($new_role)) {
        $message = '<p style="color: red;">Vui lòng điền đầy đủ các trường bắt buộc (Username, Tên Công ty, Vai trò).</p>';
    } elseif (strlen($new_username) > 10) {
        $message = '<p style="color: red;">Username không được dài quá 10 ký tự.</p>';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM vmlbooking_users WHERE username = ? AND id != ?");
            $stmt->execute([$new_username, $user_id]);
            if ($stmt->fetchColumn() > 0) {
                $message = '<p style="color: red;">Username đã tồn tại. Vui lòng chọn username khác.</p>';
            } else {
                $sql = "UPDATE vmlbooking_users SET username = ?, company_name = ?, role = ?, email = ?, phone = ?, is_active = ? WHERE id = ?";
                $params = [$new_username, $new_company_name, $new_role, $new_email, $new_phone, $new_is_active, $user_id];

                if (!empty($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE vmlbooking_users SET username = ?, password = ?, company_name = ?, role = ?, email = ?, phone = ?, is_active = ? WHERE id = ?";
                    array_splice($params, 1, 0, $hashed_password);
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $message = '<p style="color: green;">Thông tin người dùng "' . htmlspecialchars($new_username) . '" đã được cập nhật thành công!</p>';

                $stmt = $pdo->prepare("SELECT id, username, company_name, role, email, phone, is_active FROM vmlbooking_users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            }

        } catch (PDOException $e) {
            $message = '<p style="color: red;">Lỗi khi cập nhật người dùng: ' . $e->getMessage() . '</p>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa Người dùng - Booking System Admin</title>
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
        <h2>Chỉnh sửa Người dùng</h2>

        <?php echo $message; ?>

        <?php if ($user_data): ?>
            <form action="" method="POST">
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($user_data['id']) ?>">

                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" maxlength="10" value="<?= htmlspecialchars($user_data['username'] ?? '') ?>" required>
                    <small>Tối đa 10 ký tự.</small>
                </div>
                <div class="form-group">
                    <label for="password">Mật khẩu (Để trống nếu không thay đổi):</label>
                    <input type="password" id="password" name="password">
                </div>
                <div class="form-group">
                    <label for="company_name">Tên Công ty:</label>
                    <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($user_data['company_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="role">Vai trò:</label>
                    <select id="role" name="role" required>
                        <option value="agency" <?= ($user_data['role'] ?? '') == 'agency' ? 'selected' : '' ?>>Agency</option>
                        <option value="admin" <?= ($user_data['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="viewer" <?= ($user_data['role'] ?? '') == 'viewer' ? 'selected' : '' ?>>Viewer</option>
                        <option value="accounting" <?= ($user_data['role'] ?? '') == 'accounting' ? 'selected' : '' ?>>Accounting</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Điện thoại:</label>
                    <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user_data['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <input type="checkbox" id="is_active" name="is_active" value="1" <?= ($user_data['is_active'] ?? 0) ? 'checked' : '' ?>>
                    <label for="is_active" style="display: inline;">Hoạt động</label>
                </div>
                <button type="submit" class="btn btn-success">Cập nhật Người dùng</button>
                <a href="index.php" class="btn btn-back">Quay lại danh sách</a>
            </form>
        <?php else: ?>
            <a href="index.php" class="btn btn-back">Quay lại danh sách</a>
        <?php endif; ?>
    </div>
</body>
</html>