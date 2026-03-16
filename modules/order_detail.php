<?php
ob_start();
include '../config/auth.php';
include '../config/db.php';

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    die("Invalid order");
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

$shippers = $conn->query("
SELECT id,full_name FROM emslss_users
WHERE role='shipper'
ORDER BY full_name
");

$history = $conn->query("
SELECT * FROM emslss_tracking
WHERE order_id=$id
ORDER BY id DESC
");

$images = $conn->query("
SELECT * FROM emslss_images
WHERE order_id=$id
ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Order Detail</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body>

<div class="container mt-3">

<div class="card p-3 mb-3">

<h4><?= htmlspecialchars($order['ems_code']) ?></h4>

<p><strong>Pickup:</strong> <?= htmlspecialchars($order['pickup_name']) ?></p>
<p><strong>Phone:</strong> <?= htmlspecialchars($order['pickup_phone']) ?></p>
<p><strong>Address:</strong> <?= htmlspecialchars($order['pickup_address']) ?></p>

<p><strong>Receiver:</strong> <?= htmlspecialchars($order['receiver_name']) ?></p>
<p><strong>Receiver Phone:</strong> <?= htmlspecialchars($order['receiver_phone']) ?></p>
<p><strong>Receiver Address:</strong> <?= htmlspecialchars($order['receiver_address']) ?></p>

<p><strong>Status:</strong> <?= htmlspecialchars($order['status']) ?></p>

</div>

<div class="card p-3 mb-3">

<h5>Assign Shipper</h5>

<form method="post" action="assign_shipper.php">

<input type="hidden" name="id" value="<?= $id ?>">

<select name="shipper_id" class="form-control mb-2" required>

<option value="">Choose shipper</option>

<?php while($s=$shippers->fetch_assoc()){ ?>

<option value="<?= $s['id'] ?>"
<?= ($order['shipper_id']==$s['id']) ? 'selected' : '' ?>>

<?= htmlspecialchars($s['full_name']) ?>

</option>

<?php } ?>

</select>

<button class="btn btn-primary">Assign</button>

</form>

</div>

<div class="card p-3 mb-3">

<h5>Update Status</h5>

<form method="post" action="tracking_update.php">

<input type="hidden" name="order_id" value="<?= $id ?>">

<select name="status" class="form-control mb-2">

<option value="assigned">Assigned</option>
<option value="picked_up">Picked Up</option>
<option value="delivering">Delivering</option>
<option value="delivered">Delivered</option>
<option value="failed">Failed</option>

</select>

<button class="btn btn-success">Update Status</button>

</form>

</div>

<div class="card p-3 mb-3">

<h5>Upload Images</h5>

<form method="post" action="upload_image.php" enctype="multipart/form-data">

<input type="hidden" name="order_id" value="<?= $id ?>">

<input type="file" name="image[]" multiple class="form-control mb-2">

<button class="btn btn-warning">Upload</button>

</form>

</div>

<div class="card p-3 mb-3">

<h5>Callback EMS</h5>

<form method="post" action="../api/callback_ems.php">

<input type="hidden" name="order_id" value="<?= $id ?>">

<button class="btn btn-danger">Send Callback</button>

</form>

</div>

<div class="card p-3 mb-3">

<h5>Tracking History</h5>

<table class="table table-bordered">

<tr>
<th>Status</th>
<th>Time</th>
</tr>

<?php while($h=$history->fetch_assoc()){ ?>

<tr>
<td><?= htmlspecialchars($h['status']) ?></td>
<td><?= $h['created_at'] ?></td>
</tr>

<?php } ?>

</table>

</div>

<div class="card p-3 mb-3">

<h5>Uploaded Images</h5>

<div class="row">

<?php while($img=$images->fetch_assoc()){ ?>

<div class="col-4 mb-2">

<img src="../assets/uploads/<?= $img['image_path'] ?>"
class="img-fluid rounded border">

</div>

<?php } ?>

</div>

</div>

<a href="dashboard.php" class="btn btn-secondary mb-5">Back Dashboard</a>

</div>

</body>
</html>