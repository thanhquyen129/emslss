<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$confirm = trim($_POST['confirm_username'] ?? '');

if ($id <= 0) {
    header('Location: admin_users.php');
    exit;
}

$stmt = $conn->prepare('SELECT id, username, full_name FROM emslss_users WHERE id=? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();

if (!$target) {
    header('Location: admin_users.php');
    exit;
}

$error = '';
$orderCount = 0;
$oc = $conn->prepare('SELECT COUNT(*) c FROM emslss_orders WHERE pickup_shipper_id=? OR delivery_shipper_id=?');
$oc->bind_param('ii', $id, $id);
$oc->execute();
$orderCount = (int) ($oc->get_result()->fetch_assoc()['c'] ?? 0);

if ((int) $_SESSION['user_id'] === $id) {
    $error = 'Không thể xóa chính tài khoản đang đăng nhập.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    if ($confirm !== $target['username']) {
        $error = 'Nhập đúng username để xác nhận xóa.';
    } elseif ($orderCount > 0) {
        $error = 'User đang được gán ' . $orderCount . ' đơn (pickup/delivery). Bỏ gán trước khi xóa.';
    } else {
        try {
            emslss_ensure_user_roles_table($conn);
            $conn->begin_transaction();
            $delRoles = $conn->prepare('DELETE FROM emslss_user_roles WHERE user_id=?');
            $delRoles->bind_param('i', $id);
            $delRoles->execute();
            $delUser = $conn->prepare('DELETE FROM emslss_users WHERE id=?');
            $delUser->bind_param('i', $id);
            $delUser->execute();
            $conn->commit();
            header('Location: admin_users.php?deleted=1');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $error = 'Xóa thất bại: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Xóa user</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../../templates/admin_topbar.php'; ?>
<div class="container mt-4" style="max-width:520px">
    <h4 class="text-danger">Xóa vĩnh viễn user</h4>
    <div class="alert alert-warning">
        Hành động không thể hoàn tác. User <strong><?= htmlspecialchars($target['username']) ?></strong>
        (<?= htmlspecialchars($target['full_name']) ?>) sẽ bị xóa khỏi hệ thống.
    </div>
    <?php if ($orderCount > 0): ?>
    <div class="alert alert-danger">User đang gán <?= $orderCount ?> đơn hàng — cần đổi shipper trước.</div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <label class="form-label">Gõ username <code><?= htmlspecialchars($target['username']) ?></code> để xác nhận</label>
        <input type="text" name="confirm_username" class="form-control mb-3" autocomplete="off" required
            <?= ($orderCount > 0 || (int)$_SESSION['user_id'] === $id) ? 'disabled' : '' ?>>
        <button type="submit" class="btn btn-danger"
            <?= ($orderCount > 0 || (int)$_SESSION['user_id'] === $id) ? 'disabled' : '' ?>>
            Xóa vĩnh viễn
        </button>
        <a href="admin_users.php" class="btn btn-secondary">Hủy</a>
    </form>
</div>
</body>
</html>
