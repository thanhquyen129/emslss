<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: index.php");
    exit;
}

if($_SESSION['role'] != 'operation' && $_SESSION['role'] != 'admin'){
    die("Access denied");
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

/* load delivery shipper */

$shipper_query = $conn->query("
SELECT id, full_name 
FROM emslss_users
WHERE role='shipper' AND is_active=1
ORDER BY full_name
");

$shippers = [];
while($s = $shipper_query->fetch_assoc()){
    $shippers[] = $s;
}

/* load orders waiting operation */

$new_orders = $conn->query("
SELECT * FROM emslss_orders
WHERE status='picked_up'
ORDER BY created_at DESC
");

/* assigned delivery */

$assigned_orders = $conn->query("
SELECT o.*, u.full_name as shipper_name
FROM emslss_orders o
LEFT JOIN emslss_users u ON o.delivery_shipper_id = u.id
WHERE o.status='assigned_delivery'
ORDER BY o.updated_at DESC
");

/* delivered */

$done_orders = $conn->query("
SELECT o.*, u.full_name as shipper_name
FROM emslss_orders o
LEFT JOIN emslss_users u ON o.delivery_shipper_id = u.id
WHERE o.status='delivered'
ORDER BY o.updated_at DESC
LIMIT 50
");

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<title>Operation Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
background:#f5f6fa;
}

.card{
border-radius:12px;
}

.badge-status{
font-size:12px;
}

</style>

</head>

<body>

<div class="container mt-4">

<div class="d-flex justify-content-between mb-4">

<h4>Operation Dashboard</h4>

<div>

Xin chào <b><?php echo $full_name ?></b>

<a href="logout.php" class="btn btn-sm btn-danger ms-3">Logout</a>

</div>

</div>


<ul class="nav nav-tabs">

<li class="nav-item">
<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#new">
Chờ xử lý
</button>
</li>

<li class="nav-item">
<button class="nav-link" data-bs-toggle="tab" data-bs-target="#assigned">
Đã assign delivery
</button>
</li>

<li class="nav-item">
<button class="nav-link" data-bs-toggle="tab" data-bs-target="#done">
Đã giao
</button>
</li>

</ul>


<div class="tab-content mt-3">

<!-- WAITING -->

<div class="tab-pane fade show active" id="new">

<div class="card">

<div class="card-body">

<table class="table table-bordered">

<thead>

<tr>

<th>EMS Code</th>
<th>Người nhận</th>
<th>Điện thoại</th>
<th>Địa chỉ</th>
<th>Assign shipper</th>

</tr>

</thead>

<tbody>

<?php while($o = $new_orders->fetch_assoc()){ ?>

<tr>

<td>
<b><?php echo $o['ems_code'] ?></b>
</td>

<td><?php echo $o['receiver_name'] ?></td>

<td><?php echo $o['receiver_phone'] ?></td>

<td><?php echo $o['receiver_address'] ?></td>

<td>

<select class="form-select shipper_select" data-id="<?php echo $o['id'] ?>">

<option value="">-- chọn shipper --</option>

<?php foreach($shippers as $s){ ?>

<option value="<?php echo $s['id'] ?>">
<?php echo $s['full_name'] ?>
</option>

<?php } ?>

</select>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>


<!-- ASSIGNED -->

<div class="tab-pane fade" id="assigned">

<div class="card">

<div class="card-body">

<table class="table table-bordered">

<thead>

<tr>

<th>EMS</th>
<th>Receiver</th>
<th>Shipper</th>
<th>Status</th>

</tr>

</thead>

<tbody>

<?php while($o = $assigned_orders->fetch_assoc()){ ?>

<tr>

<td><?php echo $o['ems_code'] ?></td>

<td><?php echo $o['receiver_name'] ?></td>

<td>
<span class="badge bg-primary">
<?php echo $o['shipper_name'] ?>
</span>
</td>

<td>
<span class="badge bg-warning">
Assigned Delivery
</span>
</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>


<!-- DONE -->

<div class="tab-pane fade" id="done">

<div class="card">

<div class="card-body">

<table class="table table-bordered">

<thead>

<tr>

<th>EMS</th>
<th>Receiver</th>
<th>Shipper</th>
<th>Status</th>

</tr>

</thead>

<tbody>

<?php while($o = $done_orders->fetch_assoc()){ ?>

<tr>

<td><?php echo $o['ems_code'] ?></td>

<td><?php echo $o['receiver_name'] ?></td>

<td><?php echo $o['shipper_name'] ?></td>

<td>

<span class="badge bg-success">
Delivered
</span>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>


</div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

document.querySelectorAll(".shipper_select").forEach(el=>{

el.addEventListener("change",function(){

let order_id = this.dataset.id
let shipper_id = this.value

if(!shipper_id) return

fetch("operation_assign_delivery.php",{

method:"POST",
headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:`order_id=${order_id}&shipper_id=${shipper_id}`

})
.then(res=>res.text())
.then(r=>{

alert("Assigned successfully")

location.reload()

})

})

})

</script>

</body>
</html>