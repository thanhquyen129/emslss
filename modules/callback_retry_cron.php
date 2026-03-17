<?php
require_once '../config/db.php';
require_once 'ems_callback_delivery.php';

/*
Chạy cron:
*/ 

echo "=== CALLBACK RETRY START ===\n";

/*
Lấy các đơn callback fail gần nhất
retry tối đa 5 lần
*/

$sql = "
SELECT t.*, o.ems_code, o.status
FROM emslss_tracking t
JOIN emslss_orders o ON t.order_id = o.id
WHERE t.status='callback_fail'
ORDER BY t.created_at ASC
LIMIT 20
";

$res = $conn->query($sql);

while ($row = $res->fetch_assoc()) {

    $order_id = $row['order_id'];

    /*
    đếm retry cũ
    */

    $count_stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM emslss_tracking
        WHERE order_id=?
        AND status='callback_retry'
    ");
    $count_stmt->bind_param("i", $order_id);
    $count_stmt->execute();
    $retry_count = $count_stmt->get_result()->fetch_assoc()['total'];

    if ($retry_count >= 5) {

        echo "Order {$order_id} vượt quá retry\n";

        $note = "Callback retry exceeded 5 times";

        $tr = $conn->prepare("
            INSERT INTO emslss_tracking(order_id,status,note,created_by)
            VALUES(?,?,?,NULL)
        ");

        $status = 'callback_dead';

        $tr->bind_param("iss", $order_id, $status, $note);
        $tr->execute();

        continue;
    }

    /*
    retry callback
    */

    $result = sendDeliveryCallback($order_id, $conn);

    if ($result['success']) {

        echo "Retry success order {$order_id}\n";

        $note = "Callback retry success";

        $tr = $conn->prepare("
            INSERT INTO emslss_tracking(order_id,status,note,created_by)
            VALUES(?,?,?,NULL)
        ");

        $status = 'callback_success';

        $tr->bind_param("iss", $order_id, $status, $note);
        $tr->execute();

    } else {

        echo "Retry fail order {$order_id}\n";

        $note = "Retry fail HTTP ".$result['http_code'];

        $tr = $conn->prepare("
            INSERT INTO emslss_tracking(order_id,status,note,created_by)
            VALUES(?,?,?,NULL)
        ");

        $status = 'callback_retry';

        $tr->bind_param("iss", $order_id, $status, $note);
        $tr->execute();
    }
}

echo "=== CALLBACK RETRY END ===\n";
?>