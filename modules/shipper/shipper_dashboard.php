<?php
session_start();
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Shipper';

// KPI
$kpi = $conn->query(" 
    SELECT 
        SUM(status='assigned_pickup') as pickup,
        SUM(status='assigned_delivery') as delivery,
        SUM(status IN ('picked_up','in_transit')) as processing,
        SUM(status='delivered') as done
    FROM emslss_orders
    WHERE pickup_shipper_id = $user_id OR delivery_shipper_id = $user_id
")->fetch_assoc();

$pickup = $conn->query(" 
    SELECT * FROM emslss_orders
    WHERE pickup_shipper_id = $user_id
    ORDER BY created_at DESC
");

$delivery = $conn->query(" 
    SELECT * FROM emslss_orders
    WHERE delivery_shipper_id = $user_id
    ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Shipper Mobile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#0f172a;color:#fff;font-family:system-ui}
.topbar{display:flex;justify-content:space-between;align-items:center;padding:10px}
.card{border-radius:16px;background:#1e293b;color:#fff}
.badge{font-size:11px}
.kpi{display:flex;gap:10px;overflow-x:auto}
.kpi .item{min-width:90px;background:#1e293b;padding:10px;border-radius:12px;text-align:center}
.tabs{position:fixed;bottom:0;width:100%;background:#1e293b;display:flex}
.tabs button{flex:1;padding:12px;border:none;background:none;color:#aaa}
.tabs button.active{color:#38bdf8}
</style>
</head>
<body>

<div class="topbar">
    <div>👋 <?= htmlspecialchars($user_name) ?></div>
    <a href="../../logout.php" class="btn btn-sm btn-danger">Logout</a>
</div>

<!-- KPI scroll -->
<div class="kpi px-2 mb-2">
    <div class="item">📦<br><?= $kpi['pickup'] ?? 0 ?><br><small>Pickup</small></div>
    <div class="item">🚚<br><?= $kpi['delivery'] ?? 0 ?><br><small>Delivery</small></div>
    <div class="item">⏳<br><?= $kpi['processing'] ?? 0 ?><br><small>Processing</small></div>
    <div class="item">✅<br><?= $kpi['done'] ?? 0 ?><br><small>Done</small></div>
</div>

<div class="container mb-5">

<!-- PICKUP -->
<div id="pickup">
<?php while($row = $pickup->fetch_assoc()): ?>
<div class="card p-3 mb-2">
    <div class="d-flex justify-content-between">
        <b>
            <a class="text-info" href="/modules/admin/admin_order_detail.php?id=<?= intval($row['id']) ?>">
                <?= htmlspecialchars($row['ems_code']) ?>
            </a>
        </b>
        <span class="badge bg-warning text-dark"><?= $row['status'] ?></span>
    </div>

    <small><?= htmlspecialchars($row['post_office_name']) ?></small>
    <small class="text-secondary"><?= htmlspecialchars($row['post_office_address']) ?></small>

    <div class="mt-2">
        👤 <?= htmlspecialchars($row['holder_name']) ?>
        <br>📞 <a href="tel:<?= htmlspecialchars($row['holder_phone']) ?>" class="text-info"><?= htmlspecialchars($row['holder_phone']) ?></a>
    </div>

    <div class="d-flex gap-2 mt-2">
        <a href="shipper_order_detail.php?id=<?= intval($row['id']) ?>" class="btn btn-light btn-sm w-50">Chi tiết</a>
        <?php if ($row['status'] === 'assigned_pickup'): ?>
            <a href="shipper_scan.php?id=<?= intval($row['id']) ?>" class="btn btn-success btn-sm w-50">Pickup</a>
        <?php else: ?>
            <button type="button" class="btn btn-secondary btn-sm w-50" disabled>Đã pickup</button>
        <?php endif; ?>
    </div>
</div>
<?php endwhile; ?>
</div>

<!-- DELIVERY -->
<div id="delivery" style="display:none">
<?php while($row = $delivery->fetch_assoc()): ?>
<div class="card p-3 mb-2">
    <div class="d-flex justify-content-between">
        <b>
            <a class="text-info" href="/modules/admin/admin_order_detail.php?id=<?= intval($row['id']) ?>">
                <?= htmlspecialchars($row['ems_code']) ?>
            </a>
        </b>
        <span class="badge bg-info"><?= $row['status'] ?></span>
    </div>

    <div class="mt-2">
        👤 <?= htmlspecialchars($row['receiver_name']) ?>
        <br>📞 <a href="tel:<?= htmlspecialchars($row['receiver_phone']) ?>" class="text-info"><?= htmlspecialchars($row['receiver_phone']) ?></a>
        <br>📍 <small><?= htmlspecialchars($row['receiver_address']) ?></small>
    </div>

    <div class="d-flex gap-2 mt-2">
        <a href="delivery_detail.php?id=<?= intval($row['id']) ?>" class="btn btn-light btn-sm w-50">Chi tiết</a>
        <?php if (in_array($row['status'], ['assigned_delivery', 'in_transit', 'failed'], true)): ?>
            <a href="delivery_complete.php?id=<?= intval($row['id']) ?>" class="btn btn-success btn-sm w-50">Giao hàng</a>
        <?php else: ?>
            <button type="button" class="btn btn-secondary btn-sm w-50" disabled>Đã hoàn tất</button>
        <?php endif; ?>
    </div>
</div>
<?php endwhile; ?>
</div>

</div>

<!-- Bottom tabs -->
<div class="tabs">
    <button class="active" onclick="showTab('pickup', this)">📦 Pickup</button>
    <button onclick="showTab('delivery', this)">🚚 Delivery</button>
</div>

<script>
function showTab(id, el){
    document.getElementById('pickup').style.display='none';
    document.getElementById('delivery').style.display='none';
    document.getElementById(id).style.display='block';

    document.querySelectorAll('.tabs button').forEach(b=>b.classList.remove('active'));
    el.classList.add('active');
}
</script>

</body>
</html>
