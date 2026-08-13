<?php
if (!defined('EMSLSS_SKIP_JSON_HEADER')) {
    define('EMSLSS_SKIP_JSON_HEADER', true);
}
require_once 'bootstrap.php';
$callbackConfig = require __DIR__ . '/../config/callback.php';
require_once __DIR__ . '/../config/upload.php';

/**
 * Gửi callback trạng thái thu gom về EMS.
 *
 * @param int    $order_id
 * @param string $status  'picked_up' (mặc định) hoặc 'failed' (thu gom không thành công).
 * @param array  $extra   Tùy chọn: reason (bắt buộc với failed theo tài liệu EMS).
 */
function sendPickupCallback($order_id, $status = 'picked_up', array $extra = [])
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

    $status = in_array($status, ['picked_up', 'failed'], true) ? $status : 'picked_up';

    $images = [];
    $imgStmt = $conn->prepare("
        SELECT image_path
        FROM emslss_images
        WHERE order_id = ?
        ORDER BY created_at ASC
        LIMIT 10
    ");
    $imgStmt->bind_param("i", $order_id);
    $imgStmt->execute();
    $imgRes = $imgStmt->get_result();
    $pickupSeg = '/' . emslss_upload_subdir_name('pickup') . '/';
    while ($img = $imgRes->fetch_assoc()) {
        $path = trim((string) ($img['image_path'] ?? ''));
        if ($path === '') {
            continue;
        }
        $isPickup = str_contains($path, $pickupSeg)
            || str_contains($path, '/pickup/')
            || str_contains($path, 'modules/shipper/uploads/');
        if ($isPickup) {
            $images[] = emslss_upload_absolute_url($path);
        }
    }

    $eventTime = date('Y-m-d\TH:i:s');
    $data = [
        'ems_code' => $order['ems_code'],
        'status' => $status,
        'time' => $eventTime
    ];

    if ($status === 'picked_up') {
        // EMS bắt buộc images khi picked_up
        $data['images'] = $images;
    } elseif ($status === 'failed') {
        $reason = trim((string) ($extra['reason'] ?? ''));
        if ($reason === '') {
            $metaStmt = $conn->prepare("
                SELECT meta_value FROM emslss_order_meta
                WHERE order_id = ? AND meta_key = 'fail_note'
                ORDER BY id DESC LIMIT 1
            ");
            $metaStmt->bind_param('i', $order_id);
            $metaStmt->execute();
            $metaRow = $metaStmt->get_result()->fetch_assoc();
            $reason = trim((string) ($metaRow['meta_value'] ?? ''));
        }
        if ($reason !== '') {
            $data['reason'] = $reason;
        }
        if ($images !== []) {
            $data['images'] = $images;
        }
    }

    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $url = $callbackConfig['pickup_url'] ?? '';
    $timeout = (int)($callbackConfig['timeout'] ?? 10);
    $authHeaderName = trim((string)($callbackConfig['auth_header_name'] ?? 'Authorization'));
    $authHeaderValue = trim((string)($callbackConfig['auth_header_value'] ?? ''));
    $accessKey = trim((string)($callbackConfig['access_key'] ?? ''));
    $apiKey = trim((string)($callbackConfig['api_key'] ?? ''));
    $secretKey = trim((string)($callbackConfig['secret_key'] ?? ''));
    $payloadMode = trim((string)($callbackConfig['payload_mode'] ?? 'dto'));
    $useExecuteFormat = (int)($callbackConfig['use_execute_format'] ?? 0) === 1;
    $pickupCode = trim((string)($callbackConfig['pickup_code'] ?? 'EMS_PARTNER_RETURN_STATUS'));
    if ($url === '') {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => 'Missing pickup callback URL config'
        ];
    }

    $bodyToSend = $payload;
    if ($payloadMode === 'dto_wrapper') {
        $bodyToSend = json_encode([
            'dto' => $data,
            'time' => $eventTime
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if ($useExecuteFormat) {
        $signatureSource = $pickupCode . $payload . $secretKey;
        $bodyToSend = json_encode([
            'Code' => $pickupCode,
            'Data' => $payload,
            'Signature' => hash('sha256', $signatureSource)
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $headers = ['Content-Type: application/json'];
    if ($authHeaderName !== '') {
        if ($authHeaderValue !== '') {
            $headers[] = $authHeaderName . ': ' . $authHeaderValue;
        } elseif (strcasecmp($authHeaderName, 'Authorization') === 0 && $accessKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $accessKey;
        } elseif ($accessKey !== '') {
            $headers[] = $authHeaderName . ': ' . $accessKey;
        }
    } elseif ($accessKey !== '') {
        $headers[] = 'Authorization: Bearer ' . $accessKey;
    }
    if ($apiKey !== '') {
        $headers[] = 'APIKey: ' . $apiKey;
    }

    $ch = curl_init($url);

    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_POSTFIELDS=>$bodyToSend,
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
    apiLog('CALLBACK_PICKUP', $bodyToSend, $logResponse);

    $trackStatus = $success ? 'callback_success' : 'callback_fail';
    $trackNote = $success
        ? 'EMS pickup callback (' . $status . ') success'
        : 'EMS pickup callback (' . $status . ') fail: HTTP ' . $http_code;
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