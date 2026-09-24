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

$result = $conn->query('
    SELECT u.*
    FROM emslss_users u
    ORDER BY u.id DESC
');
$deleted = isset($_GET['deleted']);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Admin Users</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/../../templates/admin_topbar.php'; ?>

<div class="container mt-4">
    <h3>Quản lý Users</h3>

    <?php if ($deleted): ?>
    <div class="alert alert-success">Đã xóa user thành công.</div>
    <?php endif; ?>

    <a href="admin_user_edit.php" class="btn btn-primary mb-3">+ Thêm User</a>

    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Họ tên</th>
                <th>Role</th>
                <th>Phone</th>
                <th>Trạng thái</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= (int) $row['id'] ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><small><?= htmlspecialchars(emslss_user_roles_label($conn, (int) $row['id'], $row)) ?></small></td>
                <td><?= htmlspecialchars($row['phone']) ?></td>
                <td>
                    <?= $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Disabled</span>' ?>
                </td>
                <td class="text-nowrap">
                    <a href="admin_user_edit.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                    <a href="admin_user_disable.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-secondary">Disable</a>
                    <?php if ((int) $row['id'] !== (int) $_SESSION['user_id']): ?>
                    <a href="admin_user_delete.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-danger">Xóa</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>
