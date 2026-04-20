<?php
require_once 'bootstrap.php';

safeExecute(function(){

    global $conn;

    requireApiKey();
    requireMethod('GET');

    $ems_code = strtoupper(trim($_GET['ems_code'] ?? ''));

    if(!$ems_code){
        responseError('Missing ems_code');
    }

    $order = checkDuplicateOrder($ems_code);

    if(!$order){
        responseError('Order not found',404);
    }

    $timeline = [];

    $stmt = $conn->prepare("
        SELECT status, note, created_at
        FROM emslss_tracking
        WHERE order_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->bind_param("i", $order['id']);
    $stmt->execute();
    $res = $stmt->get_result();

    while($row = $res->fetch_assoc()){
        $timeline[] = [
            'status' => $row['status'],
            'note' => $row['note'],
            'created_at' => $row['created_at']
        ];
    }

    responseSuccess([
        'ems_code'=>$ems_code,
        'current_status'=>$order['status'],
        'timeline'=>$timeline
    ]);
});
?>