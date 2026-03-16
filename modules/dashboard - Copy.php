<?php
include '../config/auth.php';
include '../config/db.php';

$result=$conn->query("
SELECT * FROM emslss_orders ORDER BY id DESC
");
?>

<h2>EMS LSS Dashboard</h2>

<a href="../logout.php">Logout</a>

<table border="1" cellpadding="10">
<tr>
<th>EMS Code</th>
<th>Pickup Contact</th>
<th>Phone</th>
<th>Pickup Address</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php while($r=$result->fetch_assoc()){ ?>
<tr>
<td><?= $r['ems_code'] ?></td>
<td><?= $r['pickup_name'] ?></td>
<td><?= $r['pickup_phone'] ?></td>
<td><?= $r['pickup_address'] ?></td>
<td><?= $r['status'] ?></td>
<td>
<a href="orders.php?id=<?= $r['id'] ?>">View</a>
</td>
</tr>
<?php } ?>
</table>