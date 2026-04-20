<?php
	session_start();
	include '../../config/db.php';
	include '../../templates/admin_topbar.php';

	if (!isset($_SESSION['user_id'])) {
		header("Location: ../login.php");
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

	/*
	// Callback fail
	*/
	$cf = $conn->query("
		SELECT COUNT(*) total
		FROM emslss_api_logs
		WHERE source = 'CALLBACK_FAIL'
	");
	$callback_fail = $cf->fetch_assoc()['total'] ?? 0;


	// Dead queue (retry fail nhiều lần)
	$cd = $conn->query("
		SELECT COUNT(*) total
		FROM emslss_api_logs
		WHERE source = 'CALLBACK_DEAD'
	");
	$callback_dead = $cd->fetch_assoc()['total'] ?? 0;

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

	$statusFilter = $_GET['status'] ?? '';

	$allowedStatuses = [
		'new_order',
		'assigned_pickup',
		'picked_up',
		'assigned_delivery',
		'in_transit',
		'delivered',
		'failed',
		'cancelled'
	];

	$where = '';

	if ($statusFilter != '' && in_array($statusFilter, $allowedStatuses)) {
		$safeStatus = $conn->real_escape_string($statusFilter);
		$where = "WHERE status='$safeStatus'";
	}

	$orderQuery = $conn->query("
		SELECT *
		FROM emslss_orders
		$where
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
			<small>EMS-LSS vận hành realtime - Auto refresh <span id="countdown">30</span>s</small>
		</div>
		<a href="../logout.php" class="btn btn-danger">Đăng xuất</a>
	</div>

	<!--Top card-->
	<?php
	function activeCard($key, $statusFilter)
	{
		return $key == $statusFilter ? 'active-kpi' : '';
	}
	?>

	<style>
		.kpi-card{
			border:none;
			border-radius:18px;
			overflow:hidden;
			transition:.25s;
			cursor:pointer;
			position:relative;
			min-height:110px;
			box-shadow:0 6px 18px rgba(0,0,0,.06);
		}

		.kpi-card:hover{
			transform:translateY(-4px);
			box-shadow:0 10px 24px rgba(0,0,0,.12);
		}

		.kpi-card .card-body{
			position:relative;
			z-index:2;
		}

		.kpi-icon{
			font-size:32px;
			line-height:1;
		}

		.kpi-title{
			font-size:15px;
			font-weight:600;
			opacity:.9;
		}

		.kpi-value{
			font-size:30px;
			font-weight:700;
		}

		.kpi-gradient-blue{
			background:linear-gradient(135deg,#0d6efd,#4ea3ff);
			color:#fff;
		}

		.kpi-gradient-orange{
			background:linear-gradient(135deg,#ff9800,#ffc107);
			color:#fff;
		}

		.kpi-gradient-cyan{
			background:linear-gradient(135deg,#00bcd4,#26c6da);
			color:#fff;
		}

		.kpi-gradient-green{
			background:linear-gradient(135deg,#28a745,#5fd37a);
			color:#fff;
		}

		.kpi-gradient-red{
			background:linear-gradient(135deg,#dc3545,#ff6b6b);
			color:#fff;
		}

		.kpi-gradient-dark{
			background:linear-gradient(135deg,#343a40,#6c757d);
			color:#fff;
		}

		.kpi-gradient-gray{
			background:linear-gradient(135deg,#6c757d,#adb5bd);
			color:#fff;
		}

		.active-kpi{
			outline:4px solid rgba(255,255,255,.75);
			transform:scale(1.02);
		}

		.realtime-dot{
			width:10px;
			height:10px;
			border-radius:50%;
			background:#fff;
			display:inline-block;
			animation:pulse 1.4s infinite;
			margin-left:8px;
		}

		@keyframes pulse{
			0%{
				transform:scale(1);
				opacity:1;
			}
			50%{
				transform:scale(1.6);
				opacity:.4;
			}
			100%{
				transform:scale(1);
				opacity:1;
			}
		}

		@media(max-width:768px){
			.kpi-value{
				font-size:24px;
			}

			.kpi-icon{
				font-size:26px;
			}
		}
	</style>


	<div class="row g-3 mb-4">
		<div class="col-md-3 col-6">
		<a href="?status=new_order" class="text-decoration-none">
		<div class="card kpi-card kpi-gradient-blue <?= activeCard('new_order',$statusFilter) ?>">
		<div class="card-body d-flex justify-content-between align-items-center">
		<div>
		<div class="kpi-title">Đơn mới <span class="realtime-dot"></span></div>
		<div class="kpi-value"><?= $kpi['new_order'] ?></div>
		</div>
		<div class="kpi-icon">📥</div>
		</div>
		</div>
		</a>
		</div>

		<div class="col-md-3 col-6">
		<a href="?status=assigned_pickup" class="text-decoration-none">
		<div class="card kpi-card kpi-gradient-orange <?= activeCard('assigned_pickup',$statusFilter) ?>">
		<div class="card-body d-flex justify-content-between align-items-center">
		<div>
		<div class="kpi-title">Pickup</div>
		<div class="kpi-value"><?= $kpi['assigned_pickup'] ?></div>
		</div>
		<div class="kpi-icon">🚚</div>
		</div>
		</div>
		</a>
		</div>

		<div class="col-md-3 col-6">
		<a href="?status=assigned_delivery" class="text-decoration-none">
		<div class="card kpi-card kpi-gradient-cyan <?= activeCard('assigned_delivery',$statusFilter) ?>">
		<div class="card-body d-flex justify-content-between align-items-center">
		<div>
		<div class="kpi-title">Delivery</div>
		<div class="kpi-value"><?= $kpi['assigned_delivery'] ?></div>
		</div>
		<div class="kpi-icon">📦</div>
		</div>
		</div>
		</a>
		</div>

		<div class="col-md-3 col-6">
		<a href="?status=delivered" class="text-decoration-none">
		<div class="card kpi-card kpi-gradient-green <?= activeCard('delivered',$statusFilter) ?>">
		<div class="card-body d-flex justify-content-between align-items-center">
		<div>
		<div class="kpi-title">Delivered</div>
		<div class="kpi-value"><?= $kpi['delivered'] ?></div>
		</div>
		<div class="kpi-icon">✅</div>
		</div>
		</div>
		</a>
		</div>

		<div class="col-md-3 col-6">
		<a href="?status=failed" class="text-decoration-none">
		<div class="card kpi-card kpi-gradient-red <?= activeCard('failed',$statusFilter) ?>">
		<div class="card-body d-flex justify-content-between align-items-center">
		<div>
		<div class="kpi-title">Failed</div>
		<div class="kpi-value"><?= $kpi['failed'] ?></div>
		</div>
		<div class="kpi-icon">⚠️</div>
		</div>
		</div>
		</a>
		</div>

		<div class="col-md-3 col-6">
		<a href="callback_monitor.php" class="text-decoration-none">
		<div class="card kpi-card kpi-gradient-red">
		<div class="card-body d-flex justify-content-between align-items-center">
		<div>
		<div class="kpi-title">Callback Fail</div>
		<div class="kpi-value"><?= $callback_fail ?></div>
		</div>
		<div class="kpi-icon">📡</div>
		</div>
		</div>
		</a>
		</div>

		<div class="col-md-3 col-6">
		<a href="callback_monitor.php?dead=1" class="text-decoration-none">
		<div class="card kpi-card kpi-gradient-dark">
		<div class="card-body d-flex justify-content-between align-items-center">
		<div>
		<div class="kpi-title">Dead Queue</div>
		<div class="kpi-value"><?= $callback_dead ?></div>
		</div>
		<div class="kpi-icon">💀</div>
		</div>
		</div>
		</a>
		</div>

		<div class="col-md-3 col-6">
		<a href="?status=in_transit" class="text-decoration-none">
		<div class="card kpi-card kpi-gradient-gray <?= activeCard('in_transit',$statusFilter) ?>">
		<div class="card-body d-flex justify-content-between align-items-center">
		<div>
		<div class="kpi-title">In Transit</div>
		<div class="kpi-value"><?= $kpi['in_transit'] ?></div>
		</div>
		<div class="kpi-icon">🚛</div>
		</div>
		</div>
		</a>

		</div>
	</div>
	<div class="mb-3">
		<a href="admin_dashboard_realtime.php"
		   class="btn btn-sm btn-outline-secondary">
		   Tất cả đơn
		</a>
	</div>





	<!--div class="row g-3 mb-4">
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
	</div-->




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

	//setTimeout(function(){location.reload();},30000);
</script>
<script>
	let timeLeft = 30;
	const countdownEl = document.getElementById("countdown");

	function startCountdown() {
		const timer = setInterval(() => {
			timeLeft--;
			countdownEl.innerText = timeLeft;

			if (timeLeft <= 0) {
				clearInterval(timer);
				location.reload();
			}
		}, 1000);
	}

	startCountdown();
</script>

</body>
</html>