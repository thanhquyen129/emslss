<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

emslss_ensure_roles_seed($conn);
$rolesList = emslss_fetch_all_roles($conn);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản lý Roles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__.'/../../templates/admin_topbar.php'; ?>

<div class="container mt-4">
<h3>Quản lý Roles</h3>

<table class="table table-bordered">
<tr class="table-dark">
<th>ID</th>
<th>Role Code</th>
<th>Role Name</th>
<th>Description</th>
</tr>

<?php foreach ($rolesList as $r): ?>
<tr>
<td><?= $r['id'] ?></td>
<td><?= htmlspecialchars($r['role_code']) ?></td>
<td><?= htmlspecialchars($r['role_name']) ?></td>
<td><?= htmlspecialchars($r['description']) ?></td>
</tr>
<?php endforeach; ?>

</table>
</div>

</body>
</html>