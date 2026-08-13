<?php
$_system = require __DIR__ . '/system.php';

return [
    'timeout' => (int)($_system['callback']['timeout'] ?? 10),
    'pickup_url' => $_system['callback']['pickup_url'] ?? '',
    'delivery_url' => $_system['callback']['delivery_url'] ?? '',
    'auth_header_name' => $_system['callback']['auth_header_name'] ?? 'X-API-KEY',
    'auth_header_value' => ($_system['callback']['auth_header_value'] ?? '') !== ''
        ? $_system['callback']['auth_header_value']
        : ($_system['callback']['api_key'] ?? ''),
    'api_key' => $_system['callback']['api_key'] ?? '',
    'access_key' => $_system['callback']['access_key'] ?? '',
    'secret_key' => $_system['callback']['secret_key'] ?? '',
    'payload_mode' => $_system['callback']['payload_mode'] ?? 'flat',
    'use_execute_format' => (int)($_system['callback']['use_execute_format'] ?? 0),
    'pickup_code' => $_system['callback']['pickup_code'] ?? 'EMS_PARTNER_RETURN_STATUS',
    'delivery_code' => $_system['callback']['delivery_code'] ?? 'EMS_PARTNER_RETURN_STATUS'
];
?>
