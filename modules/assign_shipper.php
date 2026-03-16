<?php
include '../config/db.php';

if(!isset($_POST['id']) || !isset($_POST['shipper_id'])){
    die("Missing data");
}

$id = intval($_POST['id']);
$shipper = intval($_POST['shipper_id']);

if($id<=0 || $shipper<=0){
    die("Invalid data");
}

$stmt = $conn->prepare("
UPDATE emslss_orders
SET pickup_shipper_id=?,
status='assigned_pickup'
WHERE id=?
");

$stmt->bind_param("ii",$shipper,$id);

if($stmt->execute()){
    header("Location: dashboard.php");
    exit;
}else{
    die("DB Error");
}
?>