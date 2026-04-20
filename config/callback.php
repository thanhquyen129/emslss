<?php
$_system = require __DIR__ . '/system.php';

return [
    'timeout' => (int)($_system['callback']['timeout'] ?? 10),
    'pickup_url' => $_system['callback']['pickup_url'] ?? '',
    'delivery_url' => $_system['callback']['delivery_url'] ?? ''
];
?>
