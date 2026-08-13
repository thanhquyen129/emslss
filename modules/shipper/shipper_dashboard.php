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

$kpiStmt = $conn->prepare('
    SELECT
        SUM(pickup_shipper_id = ? AND status = \'assigned_pickup\') AS pickup_pending,
        SUM(delivery_shipper_id = ? AND status IN (\'assigned_delivery\',\'in_transit\',\'failed\')) AS delivery_pending,
        SUM(pickup_shipper_id = ? AND status <> \'assigned_pickup\') AS pickup_done,
        SUM(delivery_shipper_id = ? AND status IN (\'delivered\',\'cancelled\')) AS delivery_done
    FROM emslss_orders
    WHERE pickup_shipper_id = ? OR delivery_shipper_id = ?
');
$kpiStmt->bind_param('iiiiii', $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$kpiStmt->execute();
$kpi = $kpiStmt->get_result()->fetch_assoc();

$pickupStmt = $conn->prepare('
    SELECT o.*,
        (SELECT meta_value FROM emslss_order_meta m
         WHERE m.order_id=o.id AND m.meta_key=\'shipper_ack_pickup\' LIMIT 1) AS ack_pickup
    FROM emslss_orders o
    WHERE ' . shipper_pickup_pending_sql() . '
    ORDER BY created_at DESC
');
$pickupStmt->bind_param('i', $user_id);
$pickupStmt->execute();
$pickup = $pickupStmt->get_result();

$deliveryStmt = $conn->prepare('
    SELECT o.*,
        (SELECT COUNT(*) FROM emslss_tracking t
         WHERE t.order_id=o.id AND t.status=\'failed\') AS delivery_fail_count,
        (SELECT meta_value FROM emslss_order_meta m
         WHERE m.order_id=o.id AND m.meta_key=\'shipper_ack_delivery\' LIMIT 1) AS ack_delivery
    FROM emslss_orders o
    WHERE ' . shipper_delivery_pending_sql() . '
    ORDER BY FIELD(o.status, \'failed\', \'assigned_delivery\', \'in_transit\'), o.created_at DESC
');
$deliveryStmt->bind_param('i', $user_id);
$deliveryStmt->execute();
$delivery = $deliveryStmt->get_result();

function shipper_status_label(string $status): string
{
    $map = [
        'assigned_pickup' => 'Chờ pickup',
        'assigned_delivery' => 'Chờ giao',
        'in_transit' => 'Đang giao',
        'failed' => 'Giao thất bại',
    ];
    return $map[$status] ?? $status;
}

function shipper_status_badge(string $status): string
{
    $cls = match ($status) {
        'failed' => 'bg-danger',
        'assigned_delivery' => 'bg-warning text-dark',
        'in_transit' => 'bg-info',
        default => 'bg-secondary',
    };
    return '<span class="badge ' . $cls . '">' . htmlspecialchars(shipper_status_label($status)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="UTF-8">
<title>Shipper — Đơn cần xử lý</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#0f172a;color:#fff;font-family:system-ui}
.topbar{display:flex;justify-content:space-between;align-items:center;padding:10px;flex-wrap:wrap;gap:8px}
.card{border-radius:16px;background:#1e293b;color:#fff}
.badge{font-size:11px}
.kpi{display:flex;gap:10px;overflow-x:auto;padding:0 8px 8px}
.kpi .item{min-width:90px;background:#1e293b;padding:10px;border-radius:12px;text-align:center}
.tabs{position:fixed;bottom:0;left:0;width:100%;background:#1e293b;display:flex;z-index:100}
.tabs a, .tabs button{flex:1;padding:12px;border:none;background:none;color:#aaa;text-align:center;text-decoration:none;font-size:13px}
.tabs .active{color:#38bdf8}
.btn-ack{font-size:12px}
.card-failed{border:1px solid #ef4444}
</style>
</head>
<body>

<div class="topbar">
    <div>👋 <?= htmlspecialchars($user_name) ?></div>
    <div class="d-flex gap-2">
        <a href="shipper_history.php" class="btn btn-sm btn-outline-info">Đã xử lý</a>
        <a href="../change_password.php" class="btn btn-sm btn-outline-light">Đổi MK</a>
        <a href="../../logout.php" class="btn btn-sm btn-danger">Logout</a>
    </div>
</div>

<div class="kpi">
    <div class="item">📦<br><?= (int) ($kpi['pickup_pending'] ?? 0) ?><br><small>Pickup chờ</small></div>
    <div class="item">🚚<br><?= (int) ($kpi['delivery_pending'] ?? 0) ?><br><small>Giao chờ</small></div>
    <a href="shipper_history.php" class="item text-decoration-none text-white">
        ✅<br><?= (int) (($kpi['pickup_done'] ?? 0) + ($kpi['delivery_done'] ?? 0)) ?><br><small>Đã xử lý</small>
    </a>
</div>

<div class="container mb-5 pb-5">

<div id="pickup">
<?php if ($pickup->num_rows === 0): ?>
    <p class="text-secondary text-center py-4">Không có đơn pickup chờ xử lý</p>
<?php endif; ?>
<?php while ($row = $pickup->fetch_assoc()): ?>
<div class="card p-3 mb-2" data-order-id="<?= (int)$row['id'] ?>">
    <div class="d-flex justify-content-between align-items-start">
        <b><?= shipper_order_code_html($row, 'shipper_order_detail.php?id=' . (int) $row['id']) ?></b>
        <?= shipper_status_badge($row['status']) ?>
    </div>
    <small><?= htmlspecialchars($row['post_office_name']) ?></small>
    <small class="text-secondary d-block"><?= htmlspecialchars($row['post_office_address']) ?></small>
    <div class="mt-2">
        👤 <?= htmlspecialchars($row['holder_name']) ?><br>
        📞 <a href="tel:<?= htmlspecialchars($row['holder_phone']) ?>" class="text-info"><?= htmlspecialchars($row['holder_phone']) ?></a>
    </div>
    <?php if (!empty($row['ack_pickup'])): ?>
    <small class="text-success d-block mt-1">✓ Đã nhận tin: <?= htmlspecialchars($row['ack_pickup']) ?></small>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-2 mt-2">
        <a href="shipper_order_detail.php?id=<?= (int) $row['id'] ?>" class="btn btn-light btn-sm">Chi tiết</a>
        <a href="shipper_scan.php?id=<?= (int) $row['id'] ?>" class="btn btn-success btn-sm">Pickup</a>
        <button type="button" class="btn btn-outline-info btn-sm btn-ack"
            data-order-id="<?= (int)$row['id'] ?>" data-type="pickup"
            <?= !empty($row['ack_pickup']) ? 'disabled' : '' ?>>Nhận thông tin</button>
    </div>
</div>
<?php endwhile; ?>
</div>

<div id="delivery" style="display:none">
<?php if ($delivery->num_rows === 0): ?>
    <p class="text-secondary text-center py-4">Không có đơn giao chờ xử lý</p>
<?php endif; ?>
<?php while ($row = $delivery->fetch_assoc()):
    $failCount = (int)($row['delivery_fail_count'] ?? 0);
    $isFailed = ($row['status'] === 'failed');
?>
<div class="card p-3 mb-2 <?= $isFailed ? 'card-failed' : '' ?>" data-order-id="<?= (int)$row['id'] ?>">
    <div class="d-flex justify-content-between align-items-start">
        <b><?= shipper_order_code_html($row, 'delivery_detail.php?id=' . (int) $row['id']) ?></b>
        <?= shipper_status_badge($row['status']) ?>
    </div>
    <?php if ($failCount > 0): ?>
    <div class="small text-warning mt-1">⚠️ Giao thất bại <?= $failCount ?> lần — có thể giao lại</div>
    <?php endif; ?>
    <div class="mt-2">
        👤 <?= htmlspecialchars($row['receiver_name']) ?><br>
        📞 <a href="tel:<?= htmlspecialchars($row['receiver_phone']) ?>" class="text-info"><?= htmlspecialchars($row['receiver_phone']) ?></a><br>
        📍 <small><?= htmlspecialchars($row['receiver_address']) ?></small>
    </div>
    <?php if (!empty($row['ack_delivery'])): ?>
    <small class="text-success d-block mt-1">✓ Đã nhận tin: <?= htmlspecialchars($row['ack_delivery']) ?></small>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-2 mt-2">
        <a href="delivery_detail.php?id=<?= (int) $row['id'] ?>" class="btn btn-light btn-sm">Chi tiết</a>
        <a href="delivery_complete.php?id=<?= (int) $row['id'] ?>" class="btn btn-<?= $isFailed ? 'warning' : 'success' ?> btn-sm">
            <?= $isFailed ? 'Giao lại' : 'Giao hàng' ?>
        </a>
        <button type="button" class="btn btn-outline-info btn-sm btn-ack"
            data-order-id="<?= (int)$row['id'] ?>" data-type="delivery"
            <?= !empty($row['ack_delivery']) ? 'disabled' : '' ?>>Nhận thông tin</button>
    </div>
</div>
<?php endwhile; ?>
</div>

</div>

<div class="tabs">
    <button type="button" class="active" onclick="showTab('pickup', this)">📦 Pickup</button>
    <button type="button" onclick="showTab('delivery', this)">🚚 Delivery</button>
    <a href="shipper_history.php">✅ Đã xử lý</a>
</div>

<script>
function showTab(id, el) {
    document.getElementById('pickup').style.display = id === 'pickup' ? 'block' : 'none';
    document.getElementById('delivery').style.display = id === 'delivery' ? 'block' : 'none';
    document.querySelectorAll('.tabs button').forEach(b => b.classList.remove('active'));
    if (el && el.tagName === 'BUTTON') el.classList.add('active');
}

document.querySelectorAll('.btn-ack').forEach(btn => {
    btn.addEventListener('click', function(){
        if (this.disabled) return;
        const orderId = this.dataset.orderId;
        const type = this.dataset.type;
        const fd = new FormData();
        fd.append('order_id', orderId);
        fd.append('type', type);
        fetch('acknowledge_order.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                alert(res.message || (res.success ? 'OK' : 'Lỗi'));
                if (res.success) location.reload();
            })
            .catch(() => alert('Lỗi kết nối'));
    });
});
</script>
</body>
</html>
