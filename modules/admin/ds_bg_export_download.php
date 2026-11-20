<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/ds_bg_export_helpers.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'ems'], true)) {
    http_response_code(403);
    die('Access denied');
}

@ini_set('memory_limit', '512M');
@set_time_limit(300);

$input = $_POST ?: $_GET;
$filters = emslss_ds_bg_build_filters($input);
$rows = emslss_ds_bg_fetch_rows($conn, $filters['sql'], $filters['types'], $filters['params']);
emslss_ds_bg_send_xlsx($rows, $filters['date_from'], $filters['date_to']);
