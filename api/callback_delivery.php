<?php
if (!defined('EMSLSS_SKIP_JSON_HEADER')) {
    define('EMSLSS_SKIP_JSON_HEADER', true);
}
require_once 'bootstrap.php';
$callbackConfig = require __DIR__ . '/../config/callback.php';

function sendDeliveryCallback($order_id)
{
    global $conn;
    global $callbackConfig;
    $stmt = $conn->prepare("
        SELECT id, ems_code, status
        FROM emslss_orders
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (!$order) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => 'Order not found'
        ];
    }

    $data = [
        'ems_code' => $order['ems_code'],
        'status' => 'delivered',
        'time' => date('Y-m-d H:i:s')
    ];

    $payload = json_encode($data, JSON_UNESCAPED_UNICODE);

    $url = $callbackConfig['delivery_url'] ?? '';
    $timeout = (int)($callbackConfig['timeout'] ?? 10);
    if ($url === '') {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => 'Missing delivery callback URL config'
        ];
    }

    $ch = curl_init($url);

    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>[
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_TIMEOUT=>$timeout
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $response = 'CURL ERROR: ' . curl_error($ch);
        $http_code = 0;
    }

    curl_close($ch);

    $success = ($http_code >= 200 && $http_code < 300);
    $logResponse = json_encode([
        'success' => $success,
        'http_code' => $http_code,
        'body' => $response
    ], JSON_UNESCAPED_UNICODE);
    apiLog('CALLBACK_DELIVERY', $payload, $logResponse);

    $trackStatus = $success ? 'callback_success' : 'callback_fail';
    $trackNote = $success
        ? 'EMS delivery callback success'
        : 'EMS delivery callback fail: HTTP ' . $http_code;
    $tr = $conn->prepare("
        INSERT INTO emslss_tracking(order_id, status, note, created_by)
        VALUES(?,?,?,NULL)
    ");
    $tr->bind_param("iss", $order_id, $trackStatus, $trackNote);
    $tr->execute();

    return [
        'success' => $success,
        'http_code' => $http_code,
        'response' => $response
    ];
}
?>