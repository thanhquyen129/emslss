<?php
require_once 'bootstrap.php';

safeExecute(function(){

    global $conn;

    requireApiKey();
    requireMethod('GET');

    $ems_code = $_GET['ems_code'] ?? '';

    if(!$ems_code){
        responseError('Missing ems_code');
    }

    $order = checkDuplicateOrder($ems_code);

    if(!$order){
        responseError('Order not found',404);
    }

    $tracking = [];

    $res = $conn->query("
        SELECT status,note,created_at
        FROM emslss_tracking
        WHERE order_id=".$order['id']."
        ORDER BY created_at ASC
    ");

    while($row = $res->fetch_assoc()){
        $tracking[] = $row;
    }

    responseSuccess([
        'order_id'=>$order['id'],
        'current_status'=>$order['status'],
        'timeline'=>$tracking
    ]);
});
?>