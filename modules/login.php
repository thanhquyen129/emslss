<?php
session_start();
include '../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username']);
    $p = trim($_POST['password']);

    $stmt = $conn->prepare('SELECT * FROM emslss_users WHERE username=? AND is_active=1 LIMIT 1');
    $stmt->bind_param('s', $u);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    if ($user && emslss_verify_password($p, $user['password'])) {
        $roleCodes = emslss_user_role_codes($conn, (int) $user['id']);
        if ($roleCodes === []) {
            $fallback = emslss_resolve_user_role($user, $conn);
            if ($fallback !== '') {
                $roleCodes = [$fallback];
            }
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['user_roles'] = $roleCodes;

        if (count($roleCodes) === 1) {
            $_SESSION['role'] = $roleCodes[0];
            header('Location: ' . emslss_role_login_redirect($roleCodes[0]));
            exit;
        }
        if (count($roleCodes) > 1) {
            header('Location: choose_role.php');
            exit;
        }
        $error = 'Tài khoản chưa được gán role.';
    } else {
        $error = 'Sai tài khoản hoặc mật khẩu';
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>EMS-LSS Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5" style="max-width:400px;">

<h3>EMS-LSS Login</h3>

<?php if ($error !== ''): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

<div class="mb-3">
<input name="username" class="form-control" placeholder="Username" required>
</div>

<div class="mb-3">
<input name="password" type="password" class="form-control" placeholder="Password" required>
</div>

<button type="submit" class="btn btn-primary w-100">Login</button>

</form>

</div>

</body>
</html>
