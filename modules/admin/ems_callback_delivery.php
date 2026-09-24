<?php
include '../../config/db.php';

function sendDeliveryCallback($order_id, $conn)
{
    // lấy dữ liệu đơn
    $stmt = $conn->prepare("
        SELECT *
        FROM emslss_orders
        WHERE id=?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        return [
            'success' => false,
            'message' => 'Order not found'
        ];
    }

    // payload gửi EMS
    $payload = [
        'ems_code' => $order['ems_code'],
        'status' => $order['status'],
        'receiver_name' => $order['receiver_name'],
        'receiver_phone' => $order['receiver_phone'],
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // checksum placeholder
    $secret = 'LSS_EMS_SECRET_KEY';
    $checksum = md5($order['ems_code'] . $order['status'] . $secret);

    $payload['checksum'] = $checksum;

    // EMS endpoint
    $ems_url = 'https://ems.example.com/api/update-status';

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

    // CURL gửi
    $ch = curl_init($ems_url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $response = 'CURL ERROR: ' . curl_error($ch);
    }

    curl_close($ch);

    // log API
    $source = 'delivery_callback';

    $log = $conn->prepare("
        INSERT INTO emslss_api_logs(source,payload,response)
        VALUES(?,?,?)
    ");
    $log->bind_param("sss", $source, $jsonPayload, $response);
    $log->execute();

    // tracking log nếu callback fail
    if ($http_code != 200) {

        $note = "EMS callback fail: HTTP ".$http_code;

        $tr = $conn->prepare("
            INSERT INTO emslss_tracking(order_id,status,note,created_by)
            VALUES(?,?,?,NULL)
        ");

        $status = 'callback_fail';

        $tr->bind_param("iss", $order_id, $status, $note);
        $tr->execute();
    }

    return [
        'success' => ($http_code == 200),
        'http_code' => $http_code,
        'response' => $response
    ];
}

// gọi trực tiếp nếu test (chỉ admin, có menu)
if (isset($_GET['order_id'])) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ../login.php');
        exit;
    }

    $order_id = intval($_GET['order_id']);
    $result = sendDeliveryCallback($order_id, $conn);
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Test EMS callback (legacy)</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../../templates/admin_topbar.php'; ?>
<div class="container py-4">
    <p class="text-muted small">Legacy test — ưu tiên dùng <code>api/callback_delivery.php</code> + Callback Monitor.</p>
    <pre class="bg-light p-3 rounded border"><?php echo htmlspecialchars(print_r($result, true)); ?></pre>
    <a href="callback_monitor.php" class="btn btn-secondary btn-sm">Callback Monitor</a>
</div>
</body>
</html>
    <?php
    exit;
}
?>