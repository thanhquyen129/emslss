<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/order_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'dispatcher', 'ems'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

$input = $_POST ?: $_GET;
$filters = emslss_export_build_filters($input);
$whereSql = $filters['sql'];
$types = $filters['types'];
$params = $filters['params'];
$labels = emslss_export_status_labels();
$columnKeys = emslss_export_parse_columns($input['columns'] ?? null);

$total = emslss_export_count_filtered($conn, $whereSql, $types, $params);
$limit = emslss_export_preview_limit();
$sql = emslss_export_detail_sql($whereSql) . ' LIMIT ' . $limit;
$res = emslss_export_run_query($conn, $sql, $types, $params);

$rows = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = emslss_export_row_cells($row, $columnKeys, $labels);
    }
}

echo json_encode([
    'ok' => true,
    'total' => $total,
    'shown' => count($rows),
    'limit' => $limit,
    'headers' => emslss_export_column_headers($columnKeys),
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE);
