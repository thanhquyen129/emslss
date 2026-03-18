<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

$total = $conn->query("SELECT COUNT(*) c FROM emslss_orders")->fetch_assoc()['c'];
$delivered = $conn->query("SELECT COUNT(*) c FROM emslss_orders WHERE status='delivered'")->fetch_assoc()['c'];
$failed = $conn->query("SELECT COUNT(*) c FROM emslss_orders WHERE status='failed'")->fetch_assoc()['c'];
$new = $conn->query("SELECT COUNT(*) c FROM emslss_orders WHERE status='new_order'")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Reports</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__.'/../../templates/admin_topbar.php'; ?>

<div class="container mt-4">
<h3>Báo cáo hệ thống</h3>

<div class="row g-3">

<div class="col-md-3">
<div class="card bg-primary text-white">
<div class="card-body">
<h4><?= $total ?></h4>
Tổng đơn
</div>
</div>
</div>

<div class="col-md-3">
<div class="card bg-success text-white">
<div class="card-body">
<h4><?= $delivered ?></h4>
Delivered
</div>
</div>
</div>

<div class="col-md-3">
<div class="card bg-danger text-white">
<div class="card-body">
<h4><?= $failed ?></h4>
Failed
</div>
</div>
</div>

<div class="col-md-3">
<div class="card bg-warning text-dark">
<div class="card-body">
<h4><?= $new ?></h4>
New Order
</div>
</div>
</div>

</div>
</div>

</body>
</html>