<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

$id = $_GET['id'] ?? null;
$user = null;
$error = '';

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM emslss_users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

$rolesList = [];
$rolesQuery = $conn->query("SELECT * FROM emslss_roles");
while ($r = $rolesQuery->fetch_assoc()) {
    $rolesList[] = $r;
}

function emslss_role_code_by_id(mysqli $conn, int $role_id): string
{
    $stmt = $conn->prepare("SELECT role_code FROM emslss_roles WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['role_code'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $role_id = (int) $_POST['role_id'];
    $role_code = emslss_role_code_by_id($conn, $role_id);
    $password_plain = trim($_POST['password'] ?? '');

    if ($id) {
        if ($password_plain !== '' && strlen($password_plain) < 6) {
            $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
        } else {
            if ($password_plain !== '') {
                $password = emslss_hash_password($password_plain);
                $stmt = $conn->prepare("UPDATE emslss_users SET username=?, full_name=?, phone=?, role_id=?, role=?, password=? WHERE id=?");
                $stmt->bind_param("sssissi", $username, $full_name, $phone, $role_id, $role_code, $password, $id);
            } else {
                $stmt = $conn->prepare("UPDATE emslss_users SET username=?, full_name=?, phone=?, role_id=?, role=? WHERE id=?");
                $stmt->bind_param("sssisi", $username, $full_name, $phone, $role_id, $role_code, $id);
            }
            $stmt->execute();
            header("Location: admin_users.php");
            exit;
        }
    } else {
        if ($password_plain === '') {
            $error = 'Vui lòng nhập mật khẩu cho tài khoản mới.';
        } elseif (strlen($password_plain) < 6) {
            $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
        } else {
            $password = emslss_hash_password($password_plain);
            $stmt = $conn->prepare("INSERT INTO emslss_users(username,password,full_name,phone,role_id,role,is_active) VALUES(?,?,?,?,?,?,1)");
            $stmt->bind_param("ssssis", $username, $password, $full_name, $phone, $role_id, $role_code);
            $stmt->execute();
            header("Location: admin_users.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Edit User</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__.'/../../templates/admin_topbar.php'; ?>

<div class="container mt-4">
<h3><?= $id ? 'Sửa User' : 'Thêm User' ?></h3>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
    <input class="form-control mb-2" name="username" placeholder="Username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
    <input class="form-control mb-2" name="full_name" placeholder="Họ tên" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
    <input class="form-control mb-2" name="phone" placeholder="Phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">

    <?php if ($id): ?>
    <input class="form-control mb-2" type="password" name="password" placeholder="Mật khẩu mới (để trống nếu không đổi)" minlength="6" autocomplete="new-password">
    <?php else: ?>
    <input class="form-control mb-2" type="password" name="password" placeholder="Mật khẩu" required minlength="6" autocomplete="new-password">
    <?php endif; ?>

    <select name="role_id" class="form-select mb-3">
        <?php foreach ($rolesList as $r): ?>
            <option value="<?= $r['id'] ?>" <?= (($user['role_id'] ?? '') == $r['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['role_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button class="btn btn-success">Lưu</button>
    <a href="admin_users.php" class="btn btn-secondary">Hủy</a>
</form>
</div>

</body>
</html>
