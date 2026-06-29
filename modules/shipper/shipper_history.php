<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Shipper';

$pickupStmt = $conn->prepare('
    SELECT * FROM emslss_orders
    WHERE ' . shipper_pickup_done_sql() . '
    ORDER BY updated_at DESC
    LIMIT 100
');
$pickupStmt->bind_param('i', $user_id);
$pickupStmt->execute();
$pickup = $pickupStmt->get_result();

$deliveryStmt = $conn->prepare('
    SELECT * FROM emslss_orders
    WHERE ' . shipper_delivery_done_sql() . '
    ORDER BY updated_at DESC
    LIMIT 100
');
$deliveryStmt->bind_param('i', $user_id);
$deliveryStmt->execute();
$delivery = $deliveryStmt->get_result();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đơn đã xử lý</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#0f172a;color:#fff;font-family:system-ui}
.topbar{display:flex;justify-content:space-between;align-items:center;padding:10px}
.card{border-radius:16px;background:#1e293b;color:#fff}
.tabs{position:fixed;bottom:0;left:0;width:100%;background:#1e293b;display:flex;z-index:100}
.tabs a, .tabs button{flex:1;padding:12px;border:none;background:none;color:#aaa;text-align:center;text-decoration:none;font-size:13px}
.tabs .active{color:#38bdf8}
</style>
</head>
<body>

<div class="topbar">
    <div>✅ Đơn đã xử lý</div>
    <a href="shipper_dashboard.php" class="btn btn-sm btn-outline-light">← Chờ xử lý</a>
</div>

<div class="container mb-5 pb-5">

<div id="pickup">
<?php if ($pickup->num_rows === 0): ?>
    <p class="text-secondary text-center py-4">Chưa có đơn pickup đã xử lý</p>
<?php endif; ?>
<?php while ($row = $pickup->fetch_assoc()): ?>
<div class="card p-3 mb-2">
    <div class="d-flex justify-content-between">
        <b><?= shipper_order_code_html($row, 'shipper_order_detail.php?id=' . (int) $row['id']) ?></b>
        <span class="badge bg-secondary"><?= htmlspecialchars($row['status']) ?></span>
    </div>
    <small class="text-secondary"><?= htmlspecialchars($row['post_office_name']) ?></small>
    <div class="mt-2">
        <a href="shipper_order_detail.php?id=<?= (int) $row['id'] ?>" class="btn btn-outline-light btn-sm">Chi tiết</a>
    </div>
</div>
<?php endwhile; ?>
</div>

<div id="delivery" style="display:none">
<?php if ($delivery->num_rows === 0): ?>
    <p class="text-secondary text-center py-4">Chưa có đơn giao đã xử lý</p>
<?php endif; ?>
<?php while ($row = $delivery->fetch_assoc()): ?>
<div class="card p-3 mb-2">
    <div class="d-flex justify-content-between">
        <b><?= shipper_order_code_html($row, 'delivery_detail.php?id=' . (int) $row['id']) ?></b>
        <span class="badge bg-success"><?= htmlspecialchars($row['status']) ?></span>
    </div>
    <div class="mt-2">
        <a href="delivery_detail.php?id=<?= (int) $row['id'] ?>" class="btn btn-outline-light btn-sm">Chi tiết</a>
    </div>
</div>
<?php endwhile; ?>
</div>

</div>

<div class="tabs">
    <button type="button" class="active" onclick="showTab('pickup', this)">📦 Pickup</button>
    <button type="button" onclick="showTab('delivery', this)">🚚 Delivery</button>
    <a href="shipper_dashboard.php">📋 Chờ xử lý</a>
</div>

<script>
function showTab(id, el) {
    document.getElementById('pickup').style.display = id === 'pickup' ? 'block' : 'none';
    document.getElementById('delivery').style.display = id === 'delivery' ? 'block' : 'none';
    document.querySelectorAll('.tabs button').forEach(b => b.classList.remove('active'));
    if (el) el.classList.add('active');
}
</script>
</body>
</html>
