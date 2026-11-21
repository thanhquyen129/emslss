<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$roleCodes = $_SESSION['user_roles'] ?? emslss_user_role_codes($conn, $userId);

if ($roleCodes === []) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (count($roleCodes) === 1) {
    $_SESSION['role'] = $roleCodes[0];
    header('Location: ' . emslss_role_login_redirect($roleCodes[0]));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $picked = trim($_POST['role'] ?? '');
    if (!in_array($picked, $roleCodes, true)) {
        $error = 'Role không hợp lệ.';
    } else {
        $_SESSION['role'] = $picked;
        header('Location: ' . emslss_role_login_redirect($picked));
        exit;
    }
}

emslss_ensure_roles_seed($conn);
$roleNames = [];
foreach (emslss_fetch_all_roles($conn) as $row) {
    $roleNames[$row['role_code']] = $row['role_name'] ?: $row['role_code'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chọn vai trò đăng nhập</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:420px">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h5 class="mb-1">Chọn vai trò</h5>
            <p class="text-muted small mb-3">Tài khoản có nhiều role. Chọn role để làm việc trong phiên này.</p>
            <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <?php foreach ($roleCodes as $code): ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="role" id="role_<?= htmlspecialchars($code) ?>"
                        value="<?= htmlspecialchars($code) ?>" required>
                    <label class="form-check-label" for="role_<?= htmlspecialchars($code) ?>">
                        <?= htmlspecialchars($roleNames[$code] ?? $code) ?>
                    </label>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary w-100 mt-3">Tiếp tục</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
