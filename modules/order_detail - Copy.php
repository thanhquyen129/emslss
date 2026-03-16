<?php
include '../config/auth.php';
include '../config/db.php';

$id=$_GET['id'];

$order=$conn->query("
SELECT * FROM emslss_orders WHERE id=$id
")->fetch_assoc();
?>

<h3>Order Detail</h3>

EMS: <?= $order['ems_code'] ?><br>
Pickup: <?= $order['pickup_name'] ?><br>
Phone: <?= $order['pickup_phone'] ?><br>
Address: <?= $order['pickup_address'] ?><br>

<form method="post" action="assign_shipper.php">
<input type="hidden" name="id" value="<?= $id ?>">
<input name="shipper_id" placeholder="Shipper ID">
<button type="submit">Assign</button>
</form>