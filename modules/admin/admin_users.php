<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

$result = $conn->query("
    SELECT u.*, r.role_name
    FROM emslss_users u
    LEFT JOIN emslss_roles r ON u.role_id = r.id
    ORDER BY u.id DESC
");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Admin Users</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__.'/../../templates/admin_topbar.php'; ?>

<div class="container mt-4">
    <h3>Quản lý Users</h3>

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
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= $row['username'] ?></td>
                <td><?= $row['full_name'] ?></td>
                <td><?= $row['role_name'] ?? $row['role'] ?></td>
                <td><?= $row['phone'] ?></td>
                <td>
                    <?= $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Disabled</span>' ?>
                </td>
                <td>
                    <a href="admin_user_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                    <a href="admin_user_disable.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger">Disable</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>