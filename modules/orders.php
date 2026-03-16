<?php
ob_start();
include '../config/auth.php';
include '../config/db.php';

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    die("Invalid order ID");
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM emslss_orders WHERE id=? LIMIT 1");
$stmt->bind_param("i",$id);
$stmt->execute();

$result = $stmt->get_result();

if($result->num_rows==0){
    die("Order not found");
}

$order = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Order Detail</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-4">

<h3>Order Detail</h3>

<div class="card p-3">

<p><strong>EMS:</strong> <?= htmlspecialchars($order['ems_code']) ?></p>
<p><strong>Pickup:</strong> <?= htmlspecialchars($order['pickup_name']) ?></p>
<p><strong>Phone:</strong> <?= htmlspecialchars($order['pickup_phone']) ?></p>
<p><strong>Address:</strong> <?= htmlspecialchars($order['pickup_address']) ?></p>
<p><strong>Status:</strong> <?= htmlspecialchars($order['status']) ?></p>

</div>

<form method="post" action="assign_shipper.php" class="mt-3">

<input type="hidden" name="id" value="<?= $id ?>">

<div class="mb-3">
<input name="shipper_id" class="form-control" placeholder="Shipper ID" required>
</div>

<button type="submit" class="btn btn-primary">Assign Shipper</button>

</form>

<a href="dashboard.php" class="btn btn-secondary mt-3">Back</a>

</div>

</body>
</html>