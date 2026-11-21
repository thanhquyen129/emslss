<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

emslss_ensure_user_roles_table($conn);
emslss_ensure_roles_seed($conn);

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$user = null;
$error = '';
$selectedRoleIds = [];

if ($id) {
    $stmt = $conn->prepare('SELECT * FROM emslss_users WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user) {
        $selectedRoleIds = emslss_user_role_ids($conn, $id);
    }
}

$rolesList = emslss_fetch_all_roles($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role_ids = array_map('intval', $_POST['role_ids'] ?? []);
    $password_plain = trim($_POST['password'] ?? '');

    if ($username === '' || $full_name === '') {
        $error = 'Username và họ tên là bắt buộc.';
    } elseif ($role_ids === []) {
        $error = 'Chọn ít nhất một role.';
    } else {
        try {
            if ($id) {
                if ($password_plain !== '' && strlen($password_plain) < 6) {
                    throw new InvalidArgumentException('Mật khẩu phải có ít nhất 6 ký tự.');
                }
                if ($password_plain !== '') {
                    $password = emslss_hash_password($password_plain);
                    $stmt = $conn->prepare('UPDATE emslss_users SET username=?, full_name=?, phone=?, password=? WHERE id=?');
                    $stmt->bind_param('ssssi', $username, $full_name, $phone, $password, $id);
                } else {
                    $stmt = $conn->prepare('UPDATE emslss_users SET username=?, full_name=?, phone=? WHERE id=?');
                    $stmt->bind_param('sssi', $username, $full_name, $phone, $id);
                }
                $stmt->execute();
                emslss_save_user_roles($conn, (int) $id, $role_ids);
                header('Location: admin_users.php');
                exit;
            }

            if ($password_plain === '') {
                throw new InvalidArgumentException('Vui lòng nhập mật khẩu cho tài khoản mới.');
            }
            if (strlen($password_plain) < 6) {
                throw new InvalidArgumentException('Mật khẩu phải có ít nhất 6 ký tự.');
            }
            $password = emslss_hash_password($password_plain);
            $stmt = $conn->prepare('INSERT INTO emslss_users(username,password,full_name,phone,role_id,role,is_active) VALUES(?,?,?,?,0,\'\',1)');
            $stmt->bind_param('ssss', $username, $password, $full_name, $phone);
            $stmt->execute();
            $newId = (int) $conn->insert_id;
            emslss_save_user_roles($conn, $newId, $role_ids);
            header('Location: admin_users.php');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $selectedRoleIds = $role_ids;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title><?= $id ? 'Sửa User' : 'Thêm User' ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.role-picker {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    max-height: 280px;
    overflow-y: auto;
    background: #fff;
}
.role-picker .form-check {
    margin: 0;
    padding: 10px 12px 10px 36px;
    border-bottom: 1px solid #f0f0f0;
}
.role-picker .form-check:last-child { border-bottom: none; }
.role-picker .form-check:hover { background: #f8f9fa; }
</style>
</head>
<body>

<?php include __DIR__ . '/../../templates/admin_topbar.php'; ?>

<div class="container mt-4" style="max-width:560px">
<h3><?= $id ? 'Sửa User' : 'Thêm User' ?></h3>

<?php if ($error !== ''): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
    <input class="form-control mb-2" name="username" placeholder="Username"
        value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
    <input class="form-control mb-2" name="full_name" placeholder="Họ tên"
        value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
    <input class="form-control mb-2" name="phone" placeholder="Phone"
        value="<?= htmlspecialchars($user['phone'] ?? '') ?>">

    <?php if ($id): ?>
    <input class="form-control mb-2" type="password" name="password"
        placeholder="Mật khẩu mới (để trống nếu không đổi)" minlength="6" autocomplete="new-password">
    <?php else: ?>
    <input class="form-control mb-2" type="password" name="password" placeholder="Mật khẩu"
        required minlength="6" autocomplete="new-password">
    <?php endif; ?>

    <label class="form-label mt-2">Role <span class="text-danger">*</span> <small class="text-muted">(chọn một hoặc nhiều)</small></label>
    <?php if ($rolesList === []): ?>
    <div class="alert alert-warning">Chưa có role trong hệ thống. Liên hệ kỹ thuật chạy seed roles.</div>
    <?php else: ?>
    <div class="role-picker mb-3">
        <?php foreach ($rolesList as $r): ?>
        <div class="form-check">
            <input class="form-check-input role-chk" type="checkbox" name="role_ids[]"
                value="<?= (int) $r['id'] ?>" id="role_<?= (int) $r['id'] ?>"
                <?= in_array((int) $r['id'], $selectedRoleIds, true) ? 'checked' : '' ?>>
            <label class="form-check-label w-100" for="role_<?= (int) $r['id'] ?>">
                <strong><?= htmlspecialchars($r['role_name']) ?></strong>
                <small class="text-muted">(<?= htmlspecialchars($r['role_code']) ?>)</small>
                <?php if (!empty($r['description'])): ?>
                <br><small class="text-secondary"><?= htmlspecialchars($r['description']) ?></small>
                <?php endif; ?>
            </label>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-success">Lưu</button>
    <a href="admin_users.php" class="btn btn-secondary">Hủy</a>
    <?php if ($id && (int) $id !== (int) $_SESSION['user_id']): ?>
    <a href="admin_user_delete.php?id=<?= (int) $id ?>" class="btn btn-outline-danger float-end">Xóa user</a>
    <?php endif; ?>
</form>
</div>

<script>
document.querySelector('form')?.addEventListener('submit', function(e) {
    if (!document.querySelector('.role-chk:checked')) {
        e.preventDefault();
        alert('Chọn ít nhất một role.');
    }
});
</script>
</body>
</html>
