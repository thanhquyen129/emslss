<?php
	session_start();
	include '../../config/db.php';
	require_once __DIR__ . '/../../config/auth.php';
	require_once __DIR__ . '/dashboard_helpers.php';

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

	$pickupUsers = emslss_fetch_active_users_by_role($conn, 'shipper');
	$deliveryUsers = $pickupUsers;

	$listFilter = admin_orders_list_filter($_GET, ['active_only' => true]);
	$statusFilter = $listFilter['statusFilter'];
	$emsKeyword = $listFilter['emsKeyword'];

	// Phân trang: hiển thị tất cả đơn; nếu vượt ngưỡng thì chia trang
	$perPage = 50;
	$paginateThreshold = 100;

	$totalOrders = admin_orders_run_count($conn, $listFilter);

	$usePagination = $totalOrders > $paginateThreshold;
	$page = 1;
	$totalPages = 1;

	if ($usePagination) {
		$totalPages = (int)ceil($totalOrders / $perPage);
		$page = max(1, intval($_GET['page'] ?? 1));
		if ($page > $totalPages) {
			$page = $totalPages;
		}
		$offset = ($page - 1) * $perPage;
		$orders = admin_orders_fetch_page($conn, $listFilter, $perPage, $offset);
	} else {
		$orders = admin_orders_fetch_page($conn, $listFilter, max(1, $totalOrders), 0);
	}

	$orderMeta = admin_load_order_ack_meta($conn, array_column($orders, 'id'));
	$orderCargoMeta = emslss_order_meta_bulk($conn, array_column($orders, 'id'), ['cargo_description']);

	// URL phân trang giữ nguyên filter status
	function pageUrl($p, $statusFilter, $emsKeyword)
	{
		return admin_orders_page_url((int) $p, $statusFilter, $emsKeyword);
	}

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
	#ordersWrap.orders-view-list .order-col { flex: 0 0 100%; max-width: 100%; }
	#ordersWrap.orders-view-list .order-card-body { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start; }
	#ordersWrap.orders-view-list .order-card-body > * { flex: 1 1 220px; }
	#ordersWrap.orders-view-list .assign-block { flex: 1 1 180px; }
	#ordersWrap.orders-view-thumb .order-col { flex: 0 0 50%; max-width: 50%; }
	@media (min-width: 768px) {
		#ordersWrap.orders-view-thumb .order-col { flex: 0 0 33.333%; max-width: 33.333%; }
	}
	@media (min-width: 1200px) {
		#ordersWrap.orders-view-thumb .order-col { flex: 0 0 25%; max-width: 25%; }
	}
	#ordersWrap.orders-view-thumb .order-extra { display: none; }
	#ordersWrap.orders-view-thumb .assign-block { margin-top: 6px; }
	#ordersWrap.orders-view-thumb .assign-select { min-width: 0; font-size: 12px; }
	#ordersWrap.orders-view-thumb .ems-code { font-size: 14px; }
	.order-list-only { display: none; }
	.orders-mode-list .order-list-only { display: block; }
	.orders-mode-list #ordersWrap { display: none !important; }
	.trim-tip { border-bottom: 1px dotted #888; cursor: help; }
	.trim-tip-popup {
		position: fixed; z-index: 9999; max-width: 90vw; padding: 8px 12px;
		background: #212529; color: #fff; border-radius: 8px; font-size: 13px;
		box-shadow: 0 4px 16px rgba(0,0,0,.25); display: none;
	}
</style>
</head>
<body>
<?php include '../../templates/admin_topbar.php'; ?>
<div id="trimTipPopup" class="trim-tip-popup" role="tooltip"></div>
<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-4">
		 <div>
			<h3>📊 Admin Dashboard Realtime</h3>
			<small>Đơn chưa xử lý — Auto refresh <span id="countdown">30</span>s · <a href="admin_orders.php">Xem tất cả đơn</a></small>
		</div>
		<a href="../logout.php" class="btn btn-danger">Đăng xuất</a>
	</div>

	<div class="d-flex justify-content-between align-items-center mb-2">
		<button type="button" class="btn btn-sm btn-outline-primary" id="btnToggleKpi" aria-expanded="true">
			<span id="kpiToggleIcon">▼</span> Thống kê
		</button>
		<div class="btn-group btn-group-sm" role="group" aria-label="Chế độ hiển thị">
			<button type="button" class="btn btn-outline-secondary view-mode-btn active" data-view="tile">Tile</button>
			<button type="button" class="btn btn-outline-secondary view-mode-btn" data-view="list">List</button>
			<button type="button" class="btn btn-outline-secondary view-mode-btn" data-view="thumb">Thumb</button>
		</div>
	</div>

	<div class="collapse show" id="kpiCollapse">
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
		<a href="<?= htmlspecialchars(admin_orders_page_url(1, 'new_order', $emsKeyword)) ?>" class="text-decoration-none">
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
		<a href="<?= htmlspecialchars(admin_orders_page_url(1, 'assigned_pickup', $emsKeyword)) ?>" class="text-decoration-none">
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
		<a href="<?= htmlspecialchars(admin_orders_page_url(1, 'assigned_delivery', $emsKeyword)) ?>" class="text-decoration-none">
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
		<a href="<?= htmlspecialchars(admin_orders_page_url(1, 'delivered', $emsKeyword, 'admin_orders.php')) ?>" class="text-decoration-none">
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
		<a href="<?= htmlspecialchars(admin_orders_page_url(1, 'failed', $emsKeyword)) ?>" class="text-decoration-none">
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
		<a href="<?= htmlspecialchars(admin_orders_page_url(1, 'in_transit', $emsKeyword)) ?>" class="text-decoration-none">
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
	</div><!-- kpiCollapse -->
	<div class="mb-3 d-flex flex-wrap gap-2">
		<a href="admin_orders.php"
		   class="btn btn-sm btn-outline-secondary">
		   Tất cả đơn
		</a>
		<a href="order_export.php"
		   class="btn btn-sm btn-outline-success">
		   📥 Kết xuất CSV
		</a>
	</div>

	<?= admin_render_ems_search_form($statusFilter, $emsKeyword) ?>





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




	<div class="order-list-only mb-3">
		<table class="table table-bordered table-hover bg-white order-list-table table-sm">
			<thead class="table-light">
				<tr>
					<th>Mã EMS</th><th>TT</th><th>Bưu cục</th><th>Địa chỉ</th><th>Hàng hóa</th><th>Người giữ</th><th>Người nhận</th><th>Pickup</th><th>Delivery</th><th>Nhận tin</th><th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($orders as $row): ?>
				<tr>
					<td><a href="admin_order_detail.php?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['ems_code']) ?></a></td>
					<td><?= statusBadge($row['status']) ?></td>
					<td><?= admin_render_trim_span($row['post_office_name']) ?></td>
					<td><?= admin_render_trim_span($row['post_office_address']) ?></td>
					<td class="small"><?= emslss_order_cargo_html($row, $orderCargoMeta[(int)$row['id']] ?? []) ?></td>
					<td><?= admin_render_trim_span($row['holder_name'] . ' (' . $row['holder_phone'] . ')') ?></td>
					<td><?= admin_render_trim_span($row['receiver_name'] . ' — ' . $row['receiver_address']) ?></td>
					<td style="min-width:140px"><?= admin_render_pickup_select($row, $pickupUsers) ?></td>
					<td style="min-width:140px"><?= admin_render_delivery_select($row, $deliveryUsers) ?></td>
					<td class="small"><?= admin_render_ack_html((int)$row['id'], $orderMeta) ?></td>
					<td>
						<a class="btn btn-sm btn-outline-primary" href="admin_order_detail.php?id=<?= (int)$row['id'] ?>">Chi tiết</a>
						<?= admin_render_reject_button($row) ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="row g-3 orders-view-tile order-tile-only" id="ordersWrap">

		<?php foreach ($orders as $row): ?>

		<div class="col-md-6 col-lg-4 order-col">
			<div class="card shadow-sm order-card h-100">
				<div class="card-body order-card-body">

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

					<?= admin_render_ack_html((int)$row['id'], $orderMeta) ?>

					<div class="mb-2">
						<strong>🏤 <?= admin_render_trim_span($row['post_office_name']) ?></strong><br>
						<span class="small-line"><?= admin_render_trim_span($row['post_office_address']) ?></span>
					</div>

					<div class="mb-2 small-line">
						👤 <?= admin_render_trim_span($row['holder_name']) ?>
						| 📞 <?= htmlspecialchars($row['holder_phone']) ?>
					</div>

					<div class="mb-3 order-extra">
						📍 <?= admin_render_trim_span($row['sender_address']) ?><br>
						➜ <?= admin_render_trim_span($row['receiver_address']) ?>
					</div>

					<?= admin_render_cargo_line($row, $orderCargoMeta) ?>

					<div class="mb-2 assign-block">
						<label class="form-label small">Pickup</label>
						<?= admin_render_pickup_select($row, $pickupUsers) ?>
					</div>

					<div class="assign-block">
						<label class="form-label small">Delivery</label>
						<?= admin_render_delivery_select($row, $deliveryUsers) ?>
					</div>

					<?= admin_render_reject_button($row) ?>

				</div>
			</div>
		</div>

		<?php endforeach; ?>

	</div>

	<?php if ($usePagination): ?>
	<?php
		$rangeStart = ($page - 1) * $perPage + 1;
		$rangeEnd = min($page * $perPage, $totalOrders);
		$winStart = max(1, $page - 2);
		$winEnd = min($totalPages, $page + 2);
	?>
	<div class="d-flex flex-column align-items-center mt-4">
		<div class="text-muted small mb-2">
			Hiển thị <?= $rangeStart ?>–<?= $rangeEnd ?> trên tổng <?= $totalOrders ?> đơn
			(trang <?= $page ?>/<?= $totalPages ?>)
		</div>
		<nav aria-label="Phân trang đơn hàng">
			<ul class="pagination flex-wrap mb-0">
				<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
					<a class="page-link" href="<?= htmlspecialchars(pageUrl(1, $statusFilter, $emsKeyword)) ?>">«</a>
				</li>
				<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
					<a class="page-link" href="<?= htmlspecialchars(pageUrl(max(1, $page - 1), $statusFilter, $emsKeyword)) ?>">‹</a>
				</li>
				<?php if ($winStart > 1): ?>
					<li class="page-item disabled"><span class="page-link">…</span></li>
				<?php endif; ?>
				<?php for ($p = $winStart; $p <= $winEnd; $p++): ?>
					<li class="page-item <?= $p == $page ? 'active' : '' ?>">
						<a class="page-link" href="<?= htmlspecialchars(pageUrl($p, $statusFilter, $emsKeyword)) ?>"><?= $p ?></a>
					</li>
				<?php endfor; ?>
				<?php if ($winEnd < $totalPages): ?>
					<li class="page-item disabled"><span class="page-link">…</span></li>
				<?php endif; ?>
				<li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
					<a class="page-link" href="<?= htmlspecialchars(pageUrl(min($totalPages, $page + 1), $statusFilter, $emsKeyword)) ?>">›</a>
				</li>
				<li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
					<a class="page-link" href="<?= htmlspecialchars(pageUrl($totalPages, $statusFilter, $emsKeyword)) ?>">»</a>
				</li>
			</ul>
		</nav>
	</div>
	<?php else: ?>
	<div class="text-muted small text-center mt-4">
		Hiển thị tất cả <?= $totalOrders ?> đơn
	</div>
	<?php endif; ?>
</div>

<div class="modal fade" id="rejectOrderModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Từ chối nhận đơn</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body">
				<p class="mb-2">Mã EMS: <strong id="rejectEmsCode">-</strong></p>
				<p class="small text-muted">Dùng cho đơn vùng sâu vùng xa hoặc ngoài phạm vi giao siêu tốc. Đơn sẽ chuyển sang <code>cancelled</code> và callback về EMS.</p>
				<div class="mb-3">
					<label class="form-label">Lý do <span class="text-danger">*</span></label>
					<select class="form-select" id="rejectReason">
						<option value="">-- chọn lý do --</option>
						<?php foreach (emslss_order_reject_reasons() as $r): ?>
						<option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="mb-0">
					<label class="form-label">Chi tiết thêm (bắt buộc nếu chọn Lý do khác)</label>
					<textarea class="form-control" id="rejectReasonDetail" rows="2" placeholder="Mô tả ngắn..."></textarea>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
				<button type="button" class="btn btn-danger" id="btnConfirmReject">Xác nhận từ chối</button>
			</div>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
	$('.assign-user').change(function(){
		const $el = $(this);
		if ($el.prop('disabled')) return;
		const order_id = $el.data('order-id');
		const user_id = $el.val();
		const type = $el.data('type');
		$.post('assign_order_user.php', { order_id, user_id, type }, function(res){
			if (!res || !res.success) {
				alert((res && res.message) ? res.message : 'Gán shipper thất bại');
				location.reload();
			}
		}, 'json').fail(function(){ alert('Lỗi kết nối khi gán shipper'); });
	});

	let rejectOrderId = 0;
	const rejectModalEl = document.getElementById('rejectOrderModal');
	if (rejectModalEl) {
		const rejectModal = new bootstrap.Modal(rejectModalEl);
		document.querySelectorAll('.btn-reject-order').forEach(btn => {
			btn.addEventListener('click', () => {
				rejectOrderId = parseInt(btn.dataset.orderId, 10);
				document.getElementById('rejectEmsCode').textContent = btn.dataset.emsCode || '';
				document.getElementById('rejectReason').value = '';
				document.getElementById('rejectReasonDetail').value = '';
				rejectModal.show();
			});
		});
		document.getElementById('btnConfirmReject').addEventListener('click', () => {
			const reason = document.getElementById('rejectReason').value;
			const reason_detail = document.getElementById('rejectReasonDetail').value.trim();
			if (!reason) { alert('Vui lòng chọn lý do từ chối'); return; }
			if (reason === 'Lý do khác' && !reason_detail) { alert('Vui lòng nhập chi tiết lý do'); return; }
			if (!confirm('Xác nhận từ chối nhận đơn này?')) return;
			const fd = new FormData();
			fd.append('order_id', rejectOrderId);
			fd.append('reason', reason);
			fd.append('reason_detail', reason_detail);
			fetch('reject_order.php', { method: 'POST', body: fd })
				.then(r => r.json())
				.then(res => {
					alert(res.message || (res.success ? 'OK' : 'Lỗi'));
					if (res.success) location.reload();
				})
				.catch(() => alert('Lỗi kết nối'));
		});
	}

	const tipPopup = document.getElementById('trimTipPopup');
	function showTrimTip(el, x, y) {
		if (!tipPopup || !el.dataset.full) return;
		tipPopup.textContent = el.dataset.full;
		tipPopup.style.display = 'block';
		tipPopup.style.left = Math.min(x, window.innerWidth - tipPopup.offsetWidth - 8) + 'px';
		tipPopup.style.top = Math.min(y, window.innerHeight - tipPopup.offsetHeight - 8) + 'px';
	}
	function hideTrimTip() { if (tipPopup) tipPopup.style.display = 'none'; }
	document.querySelectorAll('.trim-tip').forEach(el => {
		el.addEventListener('mouseenter', e => showTrimTip(el, e.clientX + 12, e.clientY + 12));
		el.addEventListener('mousemove', e => showTrimTip(el, e.clientX + 12, e.clientY + 12));
		el.addEventListener('mouseleave', hideTrimTip);
		el.addEventListener('click', e => { e.preventDefault(); showTrimTip(el, e.clientX, e.clientY); });
		el.addEventListener('blur', hideTrimTip);
	});
	document.addEventListener('touchstart', e => {
		const t = e.target.closest('.trim-tip');
		if (t) { e.preventDefault(); showTrimTip(t, e.touches[0].clientX, e.touches[0].clientY + 20); }
		else if (!e.target.closest('#trimTipPopup')) hideTrimTip();
	}, { passive: false });

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

	const LS_VIEW = 'emslss_admin_order_view';
	const LS_KPI = 'emslss_admin_kpi_collapsed';
	const ordersWrap = document.getElementById('ordersWrap');
	const kpiCollapse = document.getElementById('kpiCollapse');
	const btnToggleKpi = document.getElementById('btnToggleKpi');
	const kpiToggleIcon = document.getElementById('kpiToggleIcon');

	function applyViewMode(mode) {
		document.body.classList.remove('orders-mode-tile', 'orders-mode-list', 'orders-mode-thumb');
		document.body.classList.add('orders-mode-' + mode);
		if (ordersWrap) {
			ordersWrap.classList.remove('orders-view-tile', 'orders-view-list', 'orders-view-thumb');
			ordersWrap.classList.add('orders-view-' + mode);
		}
		document.querySelectorAll('.view-mode-btn').forEach(b => {
			b.classList.toggle('active', b.dataset.view === mode);
		});
		localStorage.setItem(LS_VIEW, mode);
	}

	const savedView = localStorage.getItem(LS_VIEW) || 'tile';
	applyViewMode(savedView);

	document.querySelectorAll('.view-mode-btn').forEach(btn => {
		btn.addEventListener('click', () => applyViewMode(btn.dataset.view));
	});

	if (kpiCollapse && btnToggleKpi) {
		const kpiCollapsed = localStorage.getItem(LS_KPI) === '1';
		if (kpiCollapsed) {
			kpiCollapse.classList.remove('show');
			btnToggleKpi.setAttribute('aria-expanded', 'false');
			kpiToggleIcon.textContent = '▶';
		}
		btnToggleKpi.addEventListener('click', () => {
			const shown = kpiCollapse.classList.toggle('show');
			btnToggleKpi.setAttribute('aria-expanded', shown ? 'true' : 'false');
			kpiToggleIcon.textContent = shown ? '▼' : '▶';
			localStorage.setItem(LS_KPI, shown ? '0' : '1');
		});
	}
</script>

</body>
</html>

