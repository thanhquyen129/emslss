<?php
include '../config/auth.php';
include '../config/db.php';
include '../templates/header.php';

$status = $_GET['status'] ?? '';
$limit = intval($_GET['limit'] ?? 20);

$where = '';

if($status=='new'){
    $where = "WHERE status='new_order'";
}
elseif($status=='processing'){
    $where = "WHERE status IN ('assigned_pickup','picked_up','assigned_delivery','out_for_delivery')";
}
elseif($status=='done'){
    $where = "WHERE status='delivered'";
}

$shipperData = [];
$shipperQuery = $conn->query("
SELECT id, full_name
FROM emslss_users
WHERE role='shipper'
ORDER BY full_name
");

while($s = $shipperQuery->fetch_assoc()){
    $shipperData[] = $s;
}

$shipperMap = [];
foreach($shipperData as $s){
    $shipperMap[$s['id']] = $s['full_name'];
}

$q = $conn->query("
SELECT * FROM emslss_orders
$where
ORDER BY id DESC
LIMIT $limit
");
?>

<div class="container mt-4">

<h3>Dispatcher Dashboard</h3>

<div class="mb-3">
<a href="https://tincod.com/modules/dashboard.php" class="btn btn-primary">Tất cả</a>
<a href="?status=new" class="btn btn-primary">Đơn mới</a>
<a href="?status=processing" class="btn btn-warning">Đang xử lý</a>
<a href="?status=done" class="btn btn-success">Đã xong</a>

<select onchange="location='?limit='+this.value" class="form-select w-auto d-inline-block ms-3">
<option value="20">20</option>
<option value="50">50</option>
<option value="100">100</option>
</select>
</div>

<table class="table table-bordered table-hover">

<tr>
<th>EMS</th>
<th>Ngày giờ</th>
<th>Tuyến</th>
<th>Bưu cục Pickup</th>
<th>Liên hệ</th>
<th>Status</th>
<th>Pickup Shipper</th>
<th>Delivery Shipper</th>
<th>Assign</th>
<th>Detail</th>
</tr>

<?php while($r=$q->fetch_assoc()){

$route='';

if(stripos($r['sender_address'],'Hà Nội')!==false && stripos($r['receiver_address'],'HCM')!==false){
    $route='HN-HCM';
}
elseif(stripos($r['sender_address'],'HCM')!==false && stripos($r['receiver_address'],'Hà Nội')!==false){
    $route='HCM-HN';
}

$rowColor='';

if($r['status']=='new_order'){
    $rowColor='table-primary';
}
elseif(in_array($r['status'],['assigned_pickup','picked_up','assigned_delivery','out_for_delivery'])){
    $rowColor='table-warning';
}
elseif($r['status']=='delivered'){
    $rowColor='table-success';
}
?>

<tr class="<?= $rowColor ?>">

<td><?= $r['ems_code'] ?></td>
<td><?= $r['created_at'] ?></td>
<td><?= $route ?></td>

<td>
<?= $r['post_office_name'] ?><br>
<?= $r['post_office_address'] ?>
</td>

<td><?= $r['holder_name'] ?> - <?= $r['holder_phone'] ?></td>

<td><?= $r['status'] ?></td>

<td><?= $shipperMap[$r['pickup_shipper_id']] ?? '' ?></td>

<td><?= $shipperMap[$r['delivery_shipper_id']] ?? '' ?></td>

<td>

<form method="post" action="assign_shipper.php" class="d-flex">

<input type="hidden" name="id" value="<?= $r['id'] ?>">

<select name="shipper_id" class="form-select form-select-sm me-1" required>

<option value="">Chọn shipper</option>

<?php foreach($shipperData as $s){ ?>

<option value="<?= $s['id'] ?>"
<?= ($r['pickup_shipper_id']==$s['id']) ? 'selected' : '' ?>>

<?= $s['full_name'] ?>

</option>

<?php } ?>

</select>

<button class="btn btn-sm btn-primary">Assign</button>

</form>

</td>

<td>
<a href="order_detail.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-dark">Detail</a>
</td>

</tr>

<?php } ?>

</table>

</div>