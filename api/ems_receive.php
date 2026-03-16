<?php

include("../config/db.php");

header('Content-Type: application/json');

$headers = getallheaders();

if(($headers['x-api-key'] ?? '') !== 'EMSLSS2026KEY'){
    echo json_encode(["status"=>"error","message"=>"Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if(empty($data['ems_code'])){
    echo json_encode(["status"=>"error","message"=>"Missing ems_code"]);
    exit;
}

$stmt = $conn->prepare("
INSERT INTO emslss_orders(
 ems_code,
 post_office_name,
 post_office_address,
 holder_name,
 holder_phone,
 sender_name,
 sender_phone,
 sender_address,
 receiver_name,
 receiver_phone,
 receiver_address,
 weight,
 cargo_type,
 service_type
)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

$stmt->bind_param(
"sssssssssssdss",
$data['ems_code'],
$data['post_office_name'],
$data['post_office_address'],
$data['holder_name'],
$data['holder_phone'],
$data['sender_name'],
$data['sender_phone'],
$data['sender_address'],
$data['receiver_name'],
$data['receiver_phone'],
$data['receiver_address'],
$data['weight'],
$data['cargo_type'],
$data['service_type']
);

if($stmt->execute()){
    echo json_encode([
        "status"=>"success",
        "message"=>"Order received"
    ]);
}else{
    echo json_encode([
        "status"=>"error",
        "message"=>$stmt->error
    ]);
}
?>