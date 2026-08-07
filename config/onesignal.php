<?php
/**
 * OneSignal helpers — push tới thiết bị đã login (external_id = user_id).
 * Cấu hình qua config/system.php → onesignal (env EMSLSS_ONESIGNAL_*).
 */

function emslss_onesignal_cfg(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $system = require __DIR__ . '/system.php';
    $cfg = $system['onesignal'] ?? [];
    $localFile = __DIR__ . '/onesignal.local.php';
    if (is_file($localFile)) {
        $local = require $localFile;
        if (is_array($local)) {
            $cfg = array_merge($cfg, $local);
        }
    }
    return $cfg;
}

function emslss_onesignal_enabled(): bool
{
    $c = emslss_onesignal_cfg();
    $appId = trim((string)($c['app_id'] ?? ''));
    $key = trim((string)($c['rest_api_key'] ?? ''));
    return $appId !== '' && $key !== '' && ($c['enabled'] ?? true);
}

function emslss_ensure_push_devices_table(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $conn->query("
        CREATE TABLE IF NOT EXISTS emslss_push_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            onesignal_subscription_id VARCHAR(64) DEFAULT NULL,
            platform VARCHAR(20) DEFAULT 'android',
            role_code VARCHAR(50) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_platform (user_id, platform),
            KEY idx_push_role (role_code),
            KEY idx_push_sub (onesignal_subscription_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $done = true;
}

/**
 * @return list<string> external user ids (stringified)
 */
function emslss_admin_external_ids(mysqli $conn): array
{
    $ids = [];

    $sql = "
        SELECT DISTINCT u.id
        FROM emslss_users u
        LEFT JOIN emslss_user_roles ur ON ur.user_id = u.id
        LEFT JOIN emslss_roles r ON r.id = ur.role_id
        WHERE (u.is_active IS NULL OR u.is_active = 1)
          AND (
            u.role = 'admin'
            OR r.role_code = 'admin'
          )
    ";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (string)((int)$row['id']);
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Gửi push OneSignal. Trả về ['ok'=>bool, 'response'=>mixed, 'error'=>?string]
 */
function emslss_onesignal_send(array $payload): array
{
    if (!emslss_onesignal_enabled()) {
        return ['ok' => false, 'response' => null, 'error' => 'onesignal_disabled'];
    }

    $c = emslss_onesignal_cfg();
    $payload['app_id'] = $c['app_id'];

    $ch = curl_init('https://api.onesignal.com/notifications');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Key ' . $c['rest_api_key'],
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => (int)($c['timeout'] ?? 8),
    ]);

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return ['ok' => false, 'response' => null, 'error' => $err ?: 'curl_error'];
    }

    $decoded = json_decode((string)$raw, true);
    $ok = $code >= 200 && $code < 300 && is_array($decoded) && empty($decoded['errors']);
    return [
        'ok' => $ok,
        'response' => $decoded ?? $raw,
        'error' => $ok ? null : ('http_' . $code),
    ];
}

/**
 * Thông báo đơn mới từ EMS tới role admin.
 */
function emslss_notify_admins_new_order(mysqli $conn, string $ems_code, int $order_id = 0): array
{
    if (!emslss_onesignal_enabled()) {
        return ['ok' => false, 'error' => 'onesignal_disabled'];
    }

    $c = emslss_onesignal_cfg();
    $externalIds = emslss_admin_external_ids($conn);

    $heading = 'Đơn mới từ EMS';
    $body = 'Mã đơn: ' . $ems_code;
    $openUrl = rtrim((string)($c['open_url'] ?? 'https://lsslogistics.vn'), '/')
        . '/modules/admin/admin_orders.php';

    $base = [
        'target_channel' => 'push',
        'headings' => ['en' => $heading, 'vi' => $heading],
        'contents' => ['en' => $body, 'vi' => $body],
        'data' => [
            'type' => 'new_order',
            'ems_code' => $ems_code,
            'order_id' => $order_id,
        ],
        'url' => $openUrl,
    ];

    $results = [];

    // Ưu tiên external_id (sau khi app gọi OneSignal.login(userId))
    if ($externalIds !== []) {
        $payload = $base;
        $payload['include_aliases'] = ['external_id' => $externalIds];
        $results['by_external_id'] = emslss_onesignal_send($payload);
        if (!empty($results['by_external_id']['ok'])) {
            return ['ok' => true, 'results' => $results];
        }
    }

    // Fallback: tag role=admin (tránh gửi trùng nếu external_id đã OK)
    $payloadTag = $base;
    $payloadTag['filters'] = [
        ['field' => 'tag', 'key' => 'role', 'relation' => '=', 'value' => 'admin'],
    ];
    $results['by_tag'] = emslss_onesignal_send($payloadTag);

    $ok = !empty($results['by_tag']['ok']);
    return ['ok' => $ok, 'results' => $results];
}
