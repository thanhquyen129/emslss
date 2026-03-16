<?php
include '../config/db.php';

$filter = $_GET['filter'] ?? 'all';

$where = "";

if($filter=='new'){
    $where = "WHERE status='new_order'";
}

if($filter=='processing'){
    $where = "WHERE status IN ('assigned','delivering','picked_up')";
}

if($filter=='done'){
    $where = "WHERE status IN ('delivered','failed')";
}

$q = $conn->query("
SELECT * FROM emslss_orders
$where
ORDER BY id DESC
");

$shipperRes = $conn->query("
SELECT id,full_name FROM emslss_users
WHERE role='shipper'
");

$shipperList = [];

while($s=$shipperRes->fetch_assoc()){
    $shipperList[] = $s;
}

echo "<div class='table-responsive'>";

echo "<table class='table table-bordered table-hover align-middle'>";

echo "<thead class='table-dark sticky-top'>
<tr style='text-align: center;'>
<th>EMS Code</th>
<th>Ngày giờ</th>
<th>Tuyến</th>
<th>Điểm Pickup</th>
<th>Người giữ thư</th>
<th>Status</th>
<th>Assign nhanh</th>
<th>Chi tiết</th>
</tr>
</thead>";

while($r=$q->fetch_assoc()){

$statusClass='secondary';

if($r['status']=='new_order') $statusClass='danger';
if($r['status']=='assigned') $statusClass='warning';
if($r['status']=='delivering') $statusClass='info';
if($r['status']=='delivered') $statusClass='success';

echo "<tr>";

echo "<td>".$r['ems_code']."</td>";

echo "<td>".$r['created_at']."</td>";

echo "<td>".$r['route']."</td>";

echo "<td>".$r['post_office']."<br><small>".$r['pickup_address']."</small></td>";

echo "<td>".$r['pickup_name']."<br><small>".$r['pickup_phone']."</small></td>";

echo "<td><span class='badge bg-$statusClass'>".$r['status']."</span></td>";

echo "<td>
<form method='post' action='../api/assign_ajax.php' class='d-flex'>

<input type='hidden' name='id' value='".$r['id']."'>

<select name='shipper_id' class='form-select form-select-sm'>";

foreach($shipperList as $sp){

$selected = ($r['shipper_id']==$sp['id']) ? 'selected' : '';

echo "<option value='".$sp['id']."' $selected>".$sp['full_name']."</option>";
}

echo "</select>

<button class='btn btn-primary btn-sm ms-1'>Go</button>

</form>
</td>";

echo "<td>
<a href='order_detail.php?id=".$r['id']."' class='btn btn-secondary btn-sm'>Open</a>
</td>";

echo "</tr>";
}

echo "</table>";

echo "</div>";
?>