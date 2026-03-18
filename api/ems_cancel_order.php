<?php
require_once 'bootstrap.php';

safeExecute(function(){

    global $conn;

    requireApiKey();
    requireMethod('POST');

    [$data,$payload] = getJsonInput();

    validateRequired($data,['ems_code']);

    beginTx();

    $ems_code = strtoupper(trim($data['ems_code']));

    $stmt = $conn->prepare("
        UPDATE emslss_orders
        SET status='cancelled'
        WHERE ems_code=?
    ");

    $stmt->bind_param("s",$ems_code);
    $stmt->execute();

    $order = checkDuplicateOrder($ems_code);

    if(!$order){
        rollbackTx();
        responseError('Order not found',404);
    }

    $track = $conn->prepare("
        INSERT INTO emslss_tracking(order_id,status,note)
        VALUES(?,?,?)
    ");

    $status='cancelled';
    $note='EMS cancelled order';

    $track->bind_param("iss",$order['id'],$status,$note);
    $track->execute();

    commitTx();

    apiLog('EMS_CANCEL',$payload,'OK');

    responseSuccess();
});
?>