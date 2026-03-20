<?php
session_start();
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Pickup tasks
$pickup = $conn->query("
    SELECT * FROM emslss_orders
    WHERE pickup_shipper_id = $user_id
    AND status = 'assigned_pickup'
    ORDER BY created_at DESC
");

// Delivery tasks
$delivery = $conn->query("
    SELECT * FROM emslss_orders
    WHERE delivery_shipper_id = $user_id
    AND status = 'assigned_delivery'
    ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Shipper Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">

<h3>📦 Pickup Tasks</h3>
<table class="table table-bordered">
<tr><th>EMS Code</th><th>Sender</th><th>Action</th></tr>
<?php while($row = $pickup->fetch_assoc()): ?>
<tr>
    <td><?= $row['ems_code'] ?></td>
    <td><?= $row['sender_name'] ?></td>
    <td>
        <a href="shipper_order_detail.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">View</a>
    </td>
</tr>
<?php endwhile; ?>
</table>

<h3 class="mt-5">🚚 Delivery Tasks</h3>
<table class="table table-bordered">
<tr><th>EMS Code</th><th>Receiver</th><th>Action</th></tr>
<?php while($row = $delivery->fetch_assoc()): ?>
<tr>
    <td><?= $row['ems_code'] ?></td>
    <td><?= $row['receiver_name'] ?></td>
    <td>
        <a href="shipper_order_detail.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-success">View</a>
    </td>
</tr>
<?php endwhile; ?>
</table>

</body>
</html>