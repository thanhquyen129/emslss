<?php
require_once 'bootstrap.php';

safeExecute(function() {

    global $conn;

    requireApiKey();
    requireMethod('POST');

    [$data, $payload] = getJsonInput();

    validateRequired($data, [
        'ems_code',
        'post_office_name',
        'post_office_address',
        'holder_name',
        'holder_phone',
        'sender_name',
        'sender_phone',
        'sender_address',
        'receiver_name',
        'receiver_phone',
        'receiver_address',
        'weight',
        'cargo_type',
        'service_type'
    ]);

    validateServiceType($data['service_type']);

    $ems_code = strtoupper(trim($data['ems_code']));

    $dup = checkDuplicateOrder($ems_code);

    if ($dup) {
        apiLog('EMS_PUSH_DUPLICATE', $payload, json_encode($dup));
        responseDuplicate($dup, $ems_code);
    }

    beginTx();

    $holder_phone = normalizePhone($data['holder_phone']);
    $sender_phone = normalizePhone($data['sender_phone']);
    $receiver_phone = normalizePhone($data['receiver_phone']);
    $weight = floatval($data['weight']);

    $stmt = $conn->prepare("
        INSERT INTO emslss_orders
        (
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
            service_type,
            status
        )
        VALUES
        (
            ?,?,?,?,?,?,?,?,?,?,?,?,?,?,
            'new_order'
        )
    ");

    $stmt->bind_param(
        "sssssssssssdsd",
        $ems_code,
        $data['post_office_name'],
        $data['post_office_address'],
        $data['holder_name'],
        $holder_phone,
        $data['sender_name'],
        $sender_phone,
        $data['sender_address'],
        $data['receiver_name'],
        $receiver_phone,
        $data['receiver_address'],
        $weight,
        $data['cargo_type'],
        $data['service_type']
    );

    $stmt->execute();

    $order_id = $stmt->insert_id;

    $track = $conn->prepare("
        INSERT INTO emslss_tracking(order_id,status,note)
        VALUES(?,?,?)
    ");

    $status = 'new_order';
    $note = 'EMS pushed order';

    $track->bind_param("iss", $order_id, $status, $note);
    $track->execute();

    commitTx();

    apiLog(
        'EMS_PUSH',
        $payload,
        json_encode(['ems_code'=>$ems_code])
    );

    responseSuccess([
        'ems_code'=>$ems_code
    ]);
});
?>