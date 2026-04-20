<?php
return [
    'env' => [
        'display_errors' => (int)(getenv('EMSLSS_DISPLAY_ERRORS') ?: 1),
        'timezone' => getenv('EMSLSS_TZ') ?: 'Asia/Ho_Chi_Minh'
    ],
    'db' => [
        'host' => getenv('EMSLSS_DB_HOST') ?: 'localhost',
        'user' => getenv('EMSLSS_DB_USER') ?: 'wamvietn_tincode',
        'pass' => getenv('EMSLSS_DB_PASS') ?: 'p6]L@7iTS5',
        'name' => getenv('EMSLSS_DB_NAME') ?: 'wamvietn_tincode',
        'charset' => 'utf8mb4'
    ],
    'security' => [
        'api_key' => getenv('EMSLSS_API_KEY') ?: 'EMSLSS2026'
    ],
    'callback' => [
        'timeout' => (int)(getenv('EMSLSS_CALLBACK_TIMEOUT') ?: 10),
        'pickup_url' => getenv('EMSLSS_CALLBACK_PICKUP_URL') ?: 'https://ems-api.example.com/pickup',
        'delivery_url' => getenv('EMSLSS_CALLBACK_DELIVERY_URL') ?: 'https://ems-api.example.com/delivery'
    ]
];
?>
