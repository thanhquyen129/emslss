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
        'pickup_url' => getenv('EMSLSS_CALLBACK_PICKUP_URL') ?: 'https://emslss.ems.com.vn/api/lss/callback',
        'delivery_url' => getenv('EMSLSS_CALLBACK_DELIVERY_URL') ?: 'https://emslss.ems.com.vn/api/lss/callback',
        'auth_header_name' => getenv('EMSLSS_CALLBACK_AUTH_HEADER') ?: 'X-API-KEY',
        'auth_header_value' => getenv('EMSLSS_CALLBACK_AUTH_VALUE') ?: '',
        'api_key' => getenv('EMSLSS_CALLBACK_API_KEY') ?: 'EMSLSS2026',
        'access_key' => getenv('EMSLSS_CALLBACK_ACCESS_KEY') ?: 'd61ca2bcc3a1ed184b06fe8dee3622406065aa39eac9fcb698aa1078da794d91',
        'secret_key' => getenv('EMSLSS_CALLBACK_SECRET_KEY') ?: '6f7cf240caaf5ef6948319bfe098dfa7e790bc1421e8710083d219f59c402c5c',
        'payload_mode' => getenv('EMSLSS_CALLBACK_PAYLOAD_MODE') ?: 'flat',
        'use_execute_format' => (int)(getenv('EMSLSS_CALLBACK_USE_EXECUTE_FORMAT') ?: 0),
        'pickup_code' => getenv('EMSLSS_CALLBACK_PICKUP_CODE') ?: 'EMS_PARTNER_RETURN_STATUS',
        'delivery_code' => getenv('EMSLSS_CALLBACK_DELIVERY_CODE') ?: 'EMS_PARTNER_RETURN_STATUS'
    ],
    'upload' => [
        'root_path' => getenv('EMSLSS_UPLOAD_ROOT') ?: null,
        'web_path' => getenv('EMSLSS_UPLOAD_WEB') ?: '/uploads',
        'public_base_url' => getenv('EMSLSS_PUBLIC_URL') ?: '',
        'subdirs' => [
            'pickup' => 'pickup',
            'delivery' => 'delivery',
            'signatures' => 'signatures',
            'misc' => 'misc',
        ],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'max_bytes' => (int)(getenv('EMSLSS_UPLOAD_MAX_BYTES') ?: 10485760),
    ]
];
?>
