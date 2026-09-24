<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($new === '' || $confirm === '') {
        $error = 'Vui lòng nhập mật khẩu mới và xác nhận.';
    } elseif (strlen($new) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    } elseif ($new !== $confirm) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } else {
        $stmt = $conn->prepare('SELECT password FROM emslss_users WHERE id=? AND is_active=1 LIMIT 1');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || !emslss_verify_password($current, $row['password'])) {
            $error = 'Mật khẩu hiện tại không đúng.';
        } else {
            $hash = emslss_hash_password($new);
            $upd = $conn->prepare('UPDATE emslss_users SET password=? WHERE id=?');
            $upd->bind_param('si', $hash, $user_id);
            $upd->execute();
            $success = 'Đã đổi mật khẩu thành công.';
        }
    }
}

$role = $_SESSION['role'] ?? '';
if ($role === 'shipper') {
    $back_url = 'shipper/shipper_dashboard.php';
} elseif ($role === 'operation') {
    $back_url = 'operation/dashboard.php';
} elseif ($role === 'ems') {
    $back_url = 'ems/dashboard.php';
} else {
    $back_url = 'admin/admin_dashboard_realtime.php';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đổi mật khẩu</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width:420px;">
    <h4 class="mb-4">Đổi mật khẩu</h4>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" class="card card-body shadow-sm">
        <div class="mb-3">
            <label class="form-label">Mật khẩu hiện tại</label>
            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
        </div>
        <div class="mb-3">
            <label class="form-label">Mật khẩu mới</label>
            <input type="password" name="new_password" class="form-control" required minlength="6" autocomplete="new-password">
        </div>
        <div class="mb-3">
            <label class="form-label">Xác nhận mật khẩu mới</label>
            <input type="password" name="confirm_password" class="form-control" required minlength="6" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary w-100">Lưu mật khẩu</button>
    </form>

    <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-link mt-3">← Quay lại</a>
</div>

</body>
</html>
