<?php
ob_start();
include '../config/auth.php';
include '../config/db.php';

$user_id = $_SESSION['user_id'];

$q = $conn->query("SELECT * FROM emslss_orders WHERE pickup_shipper_id=$user_id AND status IN ('assigned_pickup') ORDER BY id DESC");

$today = $conn->query("SELECT count(*) c FROM emslss_orders WHERE pickup_shipper_id=$user_id")->fetch_assoc()['c'];

$done = $conn->query(" SELECT count(*) c FROM emslss_orders WHERE pickup_shipper_id=$user_id AND status='picked_up'")->fetch_assoc()['c'];

$pending = $today - $done;
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Pickup Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body>

<div class="container mt-3">

<h3>Pickup Dashboard</h3>

<div class="row mb-3">

<div class="col-4">
<div class="card p-2 text-center">
Assigned<br><strong><?= $today ?></strong>
</div>
</div>

<div class="col-4">
<div class="card p-2 text-center">
Done<br><strong><?= $done ?></strong>
</div>
</div>

<div class="col-4">
<div class="card p-2 text-center">
Pending<br><strong><?= $pending ?></strong>
</div>
</div>

</div>

<table class="table table-bordered">

<tr>
<th>Mã vận đơn EMS</th>
<th>Bưu cục</th>
<th>Địa chỉ</th>
<th>Người liên hệ</th>
<th>Thao tác</th>
</tr>

<?php while($r=$q->fetch_assoc()){ ?>

<tr>
	<td><?= $r['ems_code'] ?></td>
	<td><?= $r['post_office_name'] ?>
		<!--a href="tel:<?= $r['holder_phone'] ?>"><?= $r['holder_phone'] ?></a-->
	</td>
	<td><?= $r['post_office_address'] ?></td>
	<td><?= $r['holder_name'] ?>- <?= $r['holder_phone'] ?></td>
	<td>
		<a href="pickup_scan.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-sm">Scan</a>
	</td>
</tr>

<?php } ?>

</table>

</div>

</body>
</html>