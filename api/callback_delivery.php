<?php
if (!defined('EMSLSS_SKIP_JSON_HEADER')) {
    define('EMSLSS_SKIP_JSON_HEADER', true);
}
require_once __DIR__ . '/bootstrap.php';
$callbackConfig = require __DIR__ . '/../config/callback.php';
require_once __DIR__ . '/../config/upload.php';

function emslss_order_meta_map(mysqli $conn, int $orderId): array
{
    $meta = [];
    $stmt = $conn->prepare('SELECT meta_key, meta_value FROM emslss_order_meta WHERE order_id = ?');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $meta[$row['meta_key']][] = $row['meta_value'];
    }
    return $meta;
}

/**
 * Gửi callback trạng thái phát về EMS.
 *
 * @param int    $order_id
 * @param string $status  'delivered' (mặc định) hoặc 'failed'.
 * @param array  $extra   Tùy chọn: note (delivered), reason (failed), signature_image.
 */
function sendDeliveryCallback($order_id, $status = 'delivered', array $extra = [])
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

    $eventTime = date('Y-m-d\TH:i:s');
    $meta = emslss_order_meta_map($conn, $order_id);
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
    $deliverySeg = '/' . emslss_upload_subdir_name('delivery') . '/';
    while ($img = $imgRes->fetch_assoc()) {
        $path = trim((string) ($img['image_path'] ?? ''));
        if ($path === '') {
            continue;
        }
        $isDelivery = str_contains($path, $deliverySeg)
            || str_contains($path, '/delivery/');
        if ($isDelivery) {
            $images[] = emslss_upload_absolute_url($path);
        }
    }

    $status = in_array($status, ['delivered', 'failed'], true) ? $status : 'delivered';

    $data = [
        'ems_code' => $order['ems_code'],
        'status' => $status,
        'time' => $eventTime,
    ];

    if ($status === 'delivered') {
        // EMS bắt buộc: images, signature_image, note
        $data['images'] = $images;

        $signatureImage = trim((string) ($extra['signature_image'] ?? ''));
        if ($signatureImage === '' && !empty($meta['customer_signature'][0])) {
            $signatureImage = emslss_upload_absolute_url((string) $meta['customer_signature'][0]);
        }
        if ($signatureImage !== '') {
            $data['signature_image'] = $signatureImage;
        }

        $note = trim((string) ($extra['note'] ?? ''));
        if ($note === '') {
            $recipient = trim((string) ($meta['delivery_recipient_name'][0] ?? ''));
            $deliveryNote = trim((string) ($meta['delivery_note'][0] ?? ''));
            if ($recipient !== '') {
                $note = 'Người nhận: ' . $recipient . ($deliveryNote !== '' ? '. ' . $deliveryNote : '');
            } elseif ($deliveryNote !== '') {
                $note = $deliveryNote;
            }
        }
        if ($note !== '') {
            $data['note'] = $note;
        }
    } else {
        // failed — EMS bắt buộc: reason
        if ($images !== []) {
            $data['images'] = $images;
        }
        $reason = trim((string) ($extra['reason'] ?? ''));
        if ($reason === '' && !empty($meta['fail_note'][0])) {
            $reason = trim((string) $meta['fail_note'][0]);
        }
        if ($reason !== '') {
            $data['reason'] = $reason;
        }
    }

    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $url = $callbackConfig['delivery_url'] ?? '';
    $timeout = (int)($callbackConfig['timeout'] ?? 10);
    $authHeaderName = trim((string)($callbackConfig['auth_header_name'] ?? 'Authorization'));
    $authHeaderValue = trim((string)($callbackConfig['auth_header_value'] ?? ''));
    $accessKey = trim((string)($callbackConfig['access_key'] ?? ''));
    $apiKey = trim((string)($callbackConfig['api_key'] ?? ''));
    $secretKey = trim((string)($callbackConfig['secret_key'] ?? ''));
    $payloadMode = trim((string)($callbackConfig['payload_mode'] ?? 'dto'));
    $useExecuteFormat = (int)($callbackConfig['use_execute_format'] ?? 0) === 1;
    $deliveryCode = trim((string)($callbackConfig['delivery_code'] ?? 'EMS_PARTNER_RETURN_STATUS'));
    if ($url === '') {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => 'Missing delivery callback URL config'
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
        $signatureSource = $deliveryCode . $payload . $secretKey;
        $bodyToSend = json_encode([
            'Code' => $deliveryCode,
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
    apiLog('CALLBACK_DELIVERY', $bodyToSend, $logResponse);

    $trackStatus = $success ? 'callback_success' : 'callback_fail';
    $trackNote = $success
        ? 'EMS delivery callback (' . $status . ') success'
        : 'EMS delivery callback (' . $status . ') fail: HTTP ' . $http_code;
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

function emslss_resend_order_callback(int $order_id): array
{
    global $conn;

    $stmt = $conn->prepare('SELECT status FROM emslss_orders WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (!$order) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => 'Order not found',
        ];
    }

    $orderStatus = (string) $order['status'];

    if ($orderStatus === 'picked_up' || $orderStatus === 'assigned_pickup') {
        return sendPickupCallback($order_id, 'picked_up');
    }
    if ($orderStatus === 'delivered') {
        return sendDeliveryCallback($order_id, 'delivered');
    }
    if ($orderStatus === 'failed') {
        $tr = $conn->prepare("
            SELECT note FROM emslss_tracking
            WHERE order_id=? AND status='failed'
            ORDER BY id DESC LIMIT 1
        ");
        $tr->bind_param('i', $order_id);
        $tr->execute();
        $failRow = $tr->get_result()->fetch_assoc();
        $failNote = (string) ($failRow['note'] ?? '');
        if (stripos($failNote, 'Thu gom') !== false) {
            return sendPickupCallback($order_id, 'failed');
        }
        return sendDeliveryCallback($order_id, 'failed');
    }

    if ($orderStatus === 'cancelled') {
        require_once __DIR__ . '/callback_cancel.php';
        return sendCancelCallback($order_id);
    }

    return [
        'success' => false,
        'http_code' => 0,
        'response' => 'Không thể resend callback với trạng thái đơn: ' . $orderStatus,
    ];
}