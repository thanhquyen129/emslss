<?php
require_once 'bootstrap.php';
require_once 'callback_pickup.php';
require_once 'callback_delivery.php';

safeExecute(function(){

    global $conn;

    $maxRetry = 3;

    $res = $conn->query("
        SELECT
            o.id,
            o.status,
            SUM(CASE WHEN t.status='callback_retry' THEN 1 ELSE 0 END) AS retry_count,
            MAX(CASE WHEN t.status='callback_dead' THEN 1 ELSE 0 END) AS is_dead
        FROM emslss_orders o
        LEFT JOIN emslss_tracking t ON t.order_id = o.id
        GROUP BY o.id, o.status
        HAVING MAX(CASE WHEN t.status='callback_fail' THEN 1 ELSE 0 END) = 1
           AND is_dead = 0
        ORDER BY o.id ASC
        LIMIT 20
    ");

    $processed = 0;

    while($row = $res->fetch_assoc()){
        $order_id = (int)$row['id'];
        $retryCount = (int)$row['retry_count'];
        $isPickup = ($row['status'] === 'picked_up');

        if ($isPickup) {
            $result = sendPickupCallback($order_id);
        } else {
            $result = sendDeliveryCallback($order_id);
        }

        $status = 'callback_retry';
        $note = $result['success']
            ? 'Auto retry success'
            : 'Auto retry fail: HTTP ' . ($result['http_code'] ?? 0);
        $tr = $conn->prepare("
            INSERT INTO emslss_tracking(order_id,status,note,created_by)
            VALUES(?,?,?,NULL)
        ");
        $tr->bind_param("iss", $order_id, $status, $note);
        $tr->execute();

        if (!$result['success'] && ($retryCount + 1) >= $maxRetry) {
            $deadStatus = 'callback_dead';
            $deadNote = 'Auto moved to dead queue after ' . $maxRetry . ' retries';
            $dead = $conn->prepare("
                INSERT INTO emslss_tracking(order_id,status,note,created_by)
                VALUES(?,?,?,NULL)
            ");
            $dead->bind_param("iss", $order_id, $deadStatus, $deadNote);
            $dead->execute();
        }

        $processed++;
    }

    responseSuccess([
        'message' => 'Retry completed',
        'processed' => $processed
    ]);
});
?>