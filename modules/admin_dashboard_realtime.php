<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] != 'admin') {
    die("Access denied");
}

/* KPI tổng */

$kpi = [];

$statuses = [
    'new_order',
    'assigned_pickup',
    'picked_up',
    'assigned_delivery',
    'in_transit',
    'delivered',
    'failed'
];

foreach ($statuses as $st) {
    $q = $conn->query("
        SELECT COUNT(*) total
        FROM emslss_orders
        WHERE status='$st'
    ");
    $kpi[$st] = $q->fetch_assoc()['total'];
}

/* callback lỗi */

$callback_fail = $conn->query("
SELECT COUNT(*) total
FROM emslss_tracking
WHERE status='callback_fail'
")->fetch_assoc()['total'];

/* callback dead */

$callback_dead = $conn->query("
SELECT COUNT(*) total
FROM emslss_tracking
WHERE status='callback_dead'
")->fetch_assoc()['total'];

/* shipper đang active */

$shippers = $conn->query("
SELECT 
u.full_name,
COUNT(o.id) total
FROM emslss_users u
LEFT JOIN emslss_orders o 
ON (u.id=o.pickup_shipper_id OR u.id=o.delivery_shipper_id)
WHERE u.role='shipper'
GROUP BY u.id
ORDER BY total DESC
LIMIT 8
");

/* đơn mới nhất */

$latest = $conn->query("
SELECT *
FROM emslss_orders
ORDER BY created_at DESC
LIMIT 10
");

?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard Realtime</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f4f7fb;
}

.card-box{
    border:none;
    border-radius:16px;
    box-shadow:0 6px 18px rgba(0,0,0,0.06);
}

.kpi-number{
    font-size:30px;
    font-weight:700;
}

.section-title{
    font-weight:600;
    font-size:18px;
}

.table td{
    vertical-align:middle;
}
</style>
</head>
<body>

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3>📊 Admin Dashboard Realtime</h3>
        <small>EMS-LSS vận hành realtime</small>
    </div>
    <a href="logout.php" class="btn btn-danger">Đăng xuất</a>
</div>

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card card-box p-3">
<div>📥 Đơn mới</div>
<div class="kpi-number text-primary"><?= $kpi['new_order'] ?></div>
</div>
</div>

<div class="col-md-3">
<div class="card card-box p-3">
<div>🚚 Pickup</div>
<div class="kpi-number text-warning"><?= $kpi['assigned_pickup'] ?></div>
</div>
</div>

<div class="col-md-3">
<div class="card card-box p-3">
<div>📦 Delivery</div>
<div class="kpi-number text-info"><?= $kpi['assigned_delivery'] ?></div>
</div>
</div>

<div class="col-md-3">
<div class="card card-box p-3">
<div>✅ Delivered</div>
<div class="kpi-number text-success"><?= $kpi['delivered'] ?></div>
</div>
</div>

<div class="col-md-3">
<div class="card card-box p-3">
<div>⚠️ Failed</div>
<div class="kpi-number text-danger"><?= $kpi['failed'] ?></div>
</div>
</div>

<div class="col-md-3">
<div class="card card-box p-3">
<div>📡 Callback Fail</div>
<div class="kpi-number text-danger"><?= $callback_fail ?></div>
</div>
</div>

<div class="col-md-3">
<div class="card card-box p-3">
<div>💀 Dead Queue</div>
<div class="kpi-number text-dark"><?= $callback_dead ?></div>
</div>
</div>

<div class="col-md-3">
<div class="card card-box p-3">
<div>🚛 In Transit</div>
<div class="kpi-number text-secondary"><?= $kpi['in_transit'] ?></div>
</div>
</div>

</div>

<div class="row g-4">

<div class="col-md-8">

<div class="card card-box p-3">

<div class="section-title mb-3">📦 Đơn mới nhất</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>
<tr>
<th>Mã EMS</th>
<th>Người nhận</th>
<th>Trạng thái</th>
<th>Ngày tạo</th>
</tr>
</thead>

<tbody>

<?php while($r = $latest->fetch_assoc()): ?>

<tr>
<td><?= $r['ems_code'] ?></td>
<td><?= htmlspecialchars($r['receiver_name']) ?></td>
<td><?= $r['status'] ?></td>
<td><?= $r['created_at'] ?></td>
</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card card-box p-3 mb-4">

<div class="section-title mb-3">🚴 Shipper hoạt động</div>

<table class="table table-sm">

<?php while($s = $shippers->fetch_assoc()): ?>

<tr>
<td><?= htmlspecialchars($s['full_name']) ?></td>
<td>
<span class="badge bg-primary">
<?= $s['total'] ?>
</span>
</td>
</tr>

<?php endwhile; ?>

</table>

</div>

<div class="card card-box p-3">

<div class="section-title mb-3">📡 Callback Center</div>

<a href="callback_monitor.php" class="btn btn-outline-primary w-100 mb-2">
Mở Callback Monitor
</a>

<a href="dispatcher_dashboard.php" class="btn btn-outline-secondary w-100 mb-2">
Dispatcher
</a>

<a href="operation_dashboard.php" class="btn btn-outline-secondary w-100 mb-2">
Operation
</a>

<a href="delivery_dashboard.php" class="btn btn-outline-secondary w-100">
Delivery
</a>

</div>

</div>

</div>

</div>

</body>
</html>