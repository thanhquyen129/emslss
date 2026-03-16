<?php
include '../config/db.php';

$order_id=$_POST['order_id'];

$order=$conn->query("
SELECT * FROM emslss_orders WHERE id=$order_id
")->fetch_assoc();

$payload=json_encode([
 "ems_code"=>$order['ems_code'],
 "status"=>$order['status']
]);

$conn->query("
INSERT INTO emslss_api_logs(source,payload,response)
VALUES('callback','$payload','pending')
");

echo $payload;
?>