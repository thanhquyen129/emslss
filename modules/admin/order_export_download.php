<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/order_helpers.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'dispatcher', 'ems'], true)) {
    http_response_code(403);
    die('Access denied');
}

$exportType = $_POST['export_type'] ?? $_GET['export_type'] ?? 'detail';
$allowedTypes = ['detail', 'summary_shipper', 'summary_status'];
if (!in_array($exportType, $allowedTypes, true)) {
    $exportType = 'detail';
}

$filters = emslss_export_build_filters($_POST ?: $_GET);
$whereSql = $filters['sql'];
$types = $filters['types'];
$params = $filters['params'];
$labels = emslss_export_status_labels();

if ($exportType === 'detail') {
    $columnKeys = emslss_export_parse_columns($_POST['columns'] ?? $_GET['columns'] ?? null);
    $sql = emslss_export_detail_sql($whereSql);
    emslss_export_send_csv(emslss_export_filename('don_chi_tiet'), function () use ($conn, $sql, $types, $params, $labels, $columnKeys) {
        $res = emslss_export_run_query($conn, $sql, $types, $params);
        echo emslss_csv_row(emslss_export_column_headers($columnKeys));
        if (!$res) {
            return;
        }
        while ($row = $res->fetch_assoc()) {
            echo emslss_csv_row(emslss_export_row_cells($row, $columnKeys, $labels));
        }
    });
}

if ($exportType === 'summary_shipper') {
    $sql = emslss_export_summary_shipper_sql($whereSql);
    emslss_export_send_csv(emslss_export_filename('tong_hop_shipper'), function () use ($conn, $sql, $types, $params) {
        $res = emslss_export_run_query($conn, $sql, $types, $params);
        echo emslss_csv_row([
            'Shipper', 'Đã giao', 'Thất bại', 'Đang xử lý', 'Hủy/từ chối', 'Tổng đơn',
        ]);
        if (!$res) {
            return;
        }
        while ($row = $res->fetch_assoc()) {
            echo emslss_csv_row([
                $row['shipper_name'],
                (int) $row['cnt_delivered'],
                (int) $row['cnt_failed'],
                (int) $row['cnt_in_progress'],
                (int) $row['cnt_cancelled'],
                (int) $row['cnt_total'],
            ]);
        }
    });
}

$sql = emslss_export_summary_status_sql($whereSql);
emslss_export_send_csv(emslss_export_filename('tong_hop_trang_thai'), function () use ($conn, $sql, $types, $params, $labels) {
    $res = emslss_export_run_query($conn, $sql, $types, $params);
    echo emslss_csv_row([
        'Trạng thái', 'Dịch vụ', 'Số đơn', 'Tổng khối lượng (g)',
    ]);
    if (!$res) {
        return;
    }
    while ($row = $res->fetch_assoc()) {
        echo emslss_csv_row([
            $labels[$row['status']] ?? $row['status'],
            $row['service_type'],
            (int) $row['cnt_orders'],
            $row['sum_weight_g'],
        ]);
    }
});
