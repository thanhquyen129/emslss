<?php
session_start();
include '../../config/db.php';
include '../../templates/admin_topbar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$order_id = intval($_GET['id'] ?? 0);

if ($order_id <= 0) {
    die("Invalid order");
}

/*
|--------------------------------------------------------------------------
| ORDER DETAIL
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT o.*,
           pu.full_name AS pickup_shipper_name,
           du.full_name AS delivery_shipper_name
    FROM emslss_orders o
    LEFT JOIN emslss_users pu ON o.pickup_shipper_id = pu.id
    LEFT JOIN emslss_users du ON o.delivery_shipper_id = du.id
    WHERE o.id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Order not found");
}

/*
|--------------------------------------------------------------------------
| TRACKING TIMELINE
|--------------------------------------------------------------------------
*/
$tracking = $conn->query("
    SELECT t.*, u.full_name
    FROM emslss_tracking t
    LEFT JOIN emslss_users u ON t.created_by = u.id
    WHERE t.order_id = $order_id
    ORDER BY t.created_at ASC
");

/*
|--------------------------------------------------------------------------
| IMAGES
|--------------------------------------------------------------------------
*/
$images = $conn->query("
    SELECT *
    FROM emslss_images
    WHERE order_id = $order_id
    ORDER BY created_at DESC
");

/*
|--------------------------------------------------------------------------
| META
|--------------------------------------------------------------------------
*/
$meta = [];
$meta_q = $conn->query("
    SELECT meta_key, meta_value
    FROM emslss_order_meta
    WHERE order_id = $order_id
");

while ($m = $meta_q->fetch_assoc()) {
    $meta[$m['meta_key']][] = $m['meta_value'];
}

/*
|--------------------------------------------------------------------------
| CALLBACK LOGS
|--------------------------------------------------------------------------
*/
$callback_logs = $conn->query("
    SELECT *
    FROM emslss_api_logs
    WHERE payload LIKE '%{$order['ems_code']}%'
    ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Chi tiết đơn hàng</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{
    background:#f5f7fb;
}
.card{
    border:none;
    border-radius:14px;
    box-shadow:0 4px 14px rgba(0,0,0,0.08);
}
.timeline-item{
    border-left:3px solid #0d6efd;
    padding-left:15px;
    margin-bottom:15px;
}
.img-thumb{
    width:140px;
    height:140px;
    object-fit:cover;
    border-radius:10px;
    margin:5px;
}
pre{
    font-size:12px;
    white-space:pre-wrap;
}
</style>
</head>
<body>

<div class="container py-4">

<div class="d-flex justify-content-between mb-4">
    <h3>📦 Chi tiết đơn: <?= htmlspecialchars($order['ems_code']) ?></h3>
    <a href="dashboard.php" class="btn btn-secondary">← Quay lại</a>
</div>

<div class="row">

<div class="col-md-6">
<div class="card p-3 mb-4">
<h5>Thông tin đơn</h5>
<p><b>Người gửi:</b> <?= $order['sender_name'] ?></p>
<p><b>Người nhận:</b> <?= $order['receiver_name'] ?></p>
<p><b>Địa chỉ nhận:</b> <?= $order['receiver_address'] ?></p>
<p><b>Trạng thái:</b> <span class="badge bg-primary"><?= $order['status'] ?></span></p>
<p><b>Pickup shipper:</b> <?= $order['pickup_shipper_name'] ?: '-' ?></p>
<p><b>Delivery shipper:</b> <?= $order['delivery_shipper_name'] ?: '-' ?></p>
</div>
</div>

<div class="col-md-6">
<div class="card p-3 mb-4">
<h5>Meta lỗi / note</h5>

<p><b>Fail note:</b><br>
<?= $meta['fail_note'][0] ?? '-' ?>
</p>

<p><b>Delivery note:</b><br>
<?= $meta['delivery_note'][0] ?? '-' ?>
</p>

<p><b>Retry count:</b><br>
<?= count($meta['callback_retry'] ?? []) ?>
</p>
</div>
</div>

</div>

<div class="card p-3 mb-4">
<h5>📍 Timeline Tracking</h5>

<?php while($t = $tracking->fetch_assoc()): ?>
<div class="timeline-item">
    <b><?= $t['status'] ?></b><br>
    <?= $t['note'] ?><br>
    <small><?= $t['created_at'] ?> | <?= $t['full_name'] ?></small>
</div>
<?php endwhile; ?>

</div>

<div class="card p-3 mb-4">
<h5>🖼 Ảnh pickup / delivery</h5>

<?php while($img = $images->fetch_assoc()): ?>
    <img src="<?= $img['image_path'] ?>" class="img-thumb">
<?php endwhile; ?>

</div>

<div class="card p-3 mb-4">
<h5>✍️ Chữ ký khách hàng</h5>

<?php if(isset($meta['customer_signature'][0])): ?>
    <img src="<?= $meta['customer_signature'][0] ?>" class="img-fluid rounded">
<?php else: ?>
    <p>Chưa có chữ ký</p>
<?php endif; ?>

</div>

<div class="card p-3 mb-4">
<h5>📡 Callback Logs</h5>

<?php while($log = $callback_logs->fetch_assoc()): ?>
<div class="border rounded p-2 mb-3 bg-light">
    <b><?= $log['source'] ?></b><br>
    <small><?= $log['created_at'] ?></small>

    <pre><?= htmlspecialchars($log['payload']) ?></pre>
    <pre><?= htmlspecialchars($log['response']) ?></pre>
</div>
<?php endwhile; ?>

</div>

<div class="card p-3 mb-4">
<h5>🔁 Retry Logs</h5>

<?php
if(isset($meta['callback_retry'])){
    foreach($meta['callback_retry'] as $r){
        echo "<div class='border rounded p-2 mb-2 bg-warning-subtle'>{$r}</div>";
    }
}else{
    echo "<p>Không có retry</p>";
}
?>

</div>

</div>

</body>
</html>