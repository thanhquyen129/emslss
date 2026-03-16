<?php
include '../config/db.php';

$data=json_decode(file_get_contents("php://input"),true);

$stmt=$conn->prepare("
INSERT INTO emslss_orders
(
 ems_code,
 service_type,
 pickup_name,
 pickup_phone,
 pickup_address,
 receiver_name,
 receiver_phone,
 receiver_address,
 note
)
VALUES (?,?,?,?,?,?,?,?,?)
");

$stmt->bind_param(
"sssssssss",
$data['ems_code'],
$data['service_type'],
$data['pickup_name'],
$data['pickup_phone'],
$data['pickup_address'],
$data['receiver_name'],
$data['receiver_phone'],
$data['receiver_address'],
$data['note']
);

$stmt->execute();

echo json_encode([
 "success"=>true,
 "order_id"=>$conn->insert_id
]);
?>