<?php
	session_start();
	include '../../config/db.php';
	include '../../templates/admin_topbar.php';

	if (!isset($_SESSION['user_id'])) {
		header("Location: ../../login.php");
		exit;
	}

	$pickupUsers = [];
	$deliveryUsers = [];
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

	$userQuery = $conn->query("
		SELECT id, full_name
		FROM emslss_users
		WHERE role='shipper' AND is_active=1
		ORDER BY full_name
	");

	while ($u = $userQuery->fetch_assoc()) {
		$pickupUsers[] = $u;
		$deliveryUsers[] = $u;
	}

	$orderQuery = $conn->query("
		SELECT *
		FROM emslss_orders
		ORDER BY created_at DESC
		LIMIT 30
	");

	function statusBadge($status)
	{
		$map = [
			'new_order' => 'secondary',
			'assigned_pickup' => 'primary',
			'picked_up' => 'info',
			'in_transit' => 'warning',
			'assigned_delivery' => 'dark',
			'delivered' => 'success',
			'failed' => 'danger',
			'cancelled' => 'danger'
		];

		$color = $map[$status] ?? 'secondary';
		return "<span class='badge bg-$color'>$status</span>";
	}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard Realtime</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
	body{ background:#f5f7fb; }
	.card-box{ border:none; border-radius:16px; box-shadow:0 6px 18px rgba(0,0,0,0.06);}
	.order-card{ border-left:5px solid #0d6efd; transition:0.2s; }
	.order-card:hover{ transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,.08); }
	.small-line{ font-size:13px; color:#666; }
	.ems-code{ font-weight:700; text-decoration:none; }
	.assign-select{ min-width:180px; }
	.kpi-number{font-size:30px; font-weight:700; }
	.section-title{ font-weight:600; font-size:18px; }
	.table td{ vertical-align:middle; }
</style>

</head>
<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">
     <div>
        <h3>📊 Admin Dashboard Realtime</h3>
        <small>EMS-LSS vận hành realtime - Auto refresh 30s</small>
    </div>
    <a href="../logout.php" class="btn btn-danger">Đăng xuất</a>
</div>


<div class="row g-3 mb-4">

	<div class="col-md-3">
		<div class="card card-box p-3">
			<div><label class="kpi-number text-primary">📥 Đơn mới <?= $kpi['new_order'] ?></label></div>
		</div>
	</div>

	<div class="col-md-3">
		<div class="card card-box p-3">
			<div><label class="kpi-number text-warning">🚚 Pickup <?= $kpi['assigned_pickup'] ?></label></div>
		</div>
	</div>

	<div class="col-md-3">
		<div class="card card-box p-3">
			<div><label class="kpi-number text-info">📦 Delivery <?= $kpi['assigned_delivery'] ?></label></div>
		</div>
	</div>

	<div class="col-md-3">
		<div class="card card-box p-3">
			<div><label class="kpi-number text-success">✅ Delivered <?= $kpi['delivered'] ?></label></div>
		</div>
	</div>

	<div class="col-md-3">
		<div class="card card-box p-3">
			<div><label class="kpi-number text-danger">⚠️ Failed <?= $kpi['failed'] ?></label></div>
		</div>
	</div>

	<div class="col-md-3">
		<div class="card card-box p-3">
			<div><label class="kpi-number text-danger">📡 Callback Fail <?= $callback_fail ?></label></div>
		</div>
	</div>

	<div class="col-md-3">
		<div class="card card-box p-3">
			<div><label class="kpi-number text-dark">💀 Dead Queue <?= $callback_dead ?></label></div>
		</div>
	</div>

	<div class="col-md-3">
		<div class="card card-box p-3">
			<div><label class="kpi-number text-secondary">🚛 In Transit <?= $kpi['in_transit'] ?></label></div>
		</div>
	</div>

</div>




<div class="row g-3">

<?php while($row = $orderQuery->fetch_assoc()): ?>

<div class="col-md-6 col-lg-4">
    <div class="card shadow-sm order-card h-100">
        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center mb-2">
                <a class="ems-code text-primary"
                   href="admin_order_detail.php?id=<?= $row['id'] ?>">
                    <?= htmlspecialchars($row['ems_code']) ?>
                </a>

                <?= statusBadge($row['status']) ?>
            </div>

            <div class="small-line mb-2">
                🕒 <?= $row['created_at'] ?>
            </div>

            <div class="mb-2">
                <strong>🏤 <?= htmlspecialchars($row['post_office_name']) ?></strong><br>
                <span class="small-line">
                    <?= htmlspecialchars($row['post_office_address']) ?>
                </span>
            </div>

            <div class="mb-2">
                👤 <?= htmlspecialchars($row['holder_name']) ?>
                | 📞 <?= htmlspecialchars($row['holder_phone']) ?>
            </div>

            <div class="mb-3">
                📍 <?= htmlspecialchars($row['sender_address']) ?><br>
                ➜ <?= htmlspecialchars($row['receiver_address']) ?>
            </div>

            <div class="mb-2">
                <label class="form-label small">Pickup</label>
                <select class="form-select form-select-sm assign-select assign-user"
                        data-order-id="<?= $row['id'] ?>"
                        data-type="pickup">

                    <option value="">-- Chọn pickup --</option>

                    <?php foreach($pickupUsers as $u): ?>
                    <option value="<?= $u['id'] ?>"
                        <?= $row['pickup_shipper_id']==$u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['full_name']) ?>
                    </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div>
                <label class="form-label small">Delivery</label>
                <select class="form-select form-select-sm assign-select assign-user"
                        data-order-id="<?= $row['id'] ?>"
                        data-type="delivery">

                    <option value="">-- Chọn delivery --</option>

                    <?php foreach($deliveryUsers as $u): ?>
                    <option value="<?= $u['id'] ?>"
                        <?= $row['delivery_shipper_id']==$u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['full_name']) ?>
                    </option>
                    <?php endforeach; ?>

                </select>
            </div>

        </div>
    </div>
</div>

<?php endwhile; ?>

</div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
$('.assign-user').change(function(){

    let order_id = $(this).data('order-id');
    let user_id = $(this).val();
    let type = $(this).data('type');

    $.post('assign_order_user.php',{
        order_id:order_id,
        user_id:user_id,
        type:type
    },function(res){
        console.log(res);
    });

});

setTimeout(function(){
    location.reload();
},30000);
</script>

</body>
</html>