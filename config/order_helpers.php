<?php

/**
 * Helper hiển thị hàng hóa & từ chối nhận đơn.
 */

function emslss_format_weight($weight): string
{
    if ($weight === null || $weight === '') {
        return '-';
    }
    $n = (float) $weight;
    if ($n <= 0) {
        return '-';
    }
    if (floor($n) == $n) {
        return (string) (int) $n . ' g';
    }
    return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.') . ' g';
}

function emslss_format_weight_html($weight): string
{
    return htmlspecialchars(emslss_format_weight($weight), ENT_QUOTES, 'UTF-8');
}

function emslss_order_reject_reasons(): array
{
    return [
        'Vùng sâu vùng xa — không giao siêu tốc được',
        'Ngoài phạm vi phục vụ LSS',
        'Địa chỉ không hỗ trợ door-to-door',
        'Khối lượng / loại hàng không phù hợp',
        'Lý do khác',
    ];
}

function emslss_admin_can_reject_order(string $status): bool
{
    return in_array($status, ['new_order', 'assigned_pickup'], true);
}

function emslss_order_cargo_description_from_data(array $data): string
{
    foreach (['cargo_description', 'goods_content', 'cargo_content', 'item_description', 'goods_description'] as $key) {
        $val = trim((string) ($data[$key] ?? ''));
        if ($val !== '') {
            return $val;
        }
    }
    return '';
}

function emslss_order_save_cargo_meta(mysqli $conn, int $orderId, array $data): void
{
    $desc = emslss_order_cargo_description_from_data($data);
    if ($desc === '') {
        return;
    }
    $key = 'cargo_description';
    $stmt = $conn->prepare('INSERT INTO emslss_order_meta(order_id, meta_key, meta_value) VALUES(?,?,?)');
    $stmt->bind_param('iss', $orderId, $key, $desc);
    $stmt->execute();
}

function emslss_order_meta_bulk(mysqli $conn, array $orderIds, ?array $keys = null): array
{
    $meta = [];
    if ($orderIds === []) {
        return $meta;
    }
    $ids = implode(',', array_map('intval', $orderIds));
    $sql = "SELECT order_id, meta_key, meta_value FROM emslss_order_meta WHERE order_id IN ($ids)";
    if ($keys !== null && $keys !== []) {
        $escaped = array_map([$conn, 'real_escape_string'], $keys);
        $sql .= " AND meta_key IN ('" . implode("','", $escaped) . "')";
    }
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $meta[(int) $row['order_id']][$row['meta_key']] = $row['meta_value'];
        }
    }
    return $meta;
}

function emslss_order_cargo_summary(array $order, array $meta = []): string
{
    $parts = [];
    $type = trim((string) ($order['cargo_type'] ?? ''));
    if ($type !== '') {
        $parts[] = $type;
    }
    $desc = emslss_meta_value($meta, 'cargo_description');
    if ($desc !== '') {
        $parts[] = $desc;
    }
    $weight = $order['weight'] ?? null;
    if ($weight !== null && $weight !== '' && (float) $weight > 0) {
        $parts[] = emslss_format_weight($weight);
    }
    return $parts !== [] ? implode(' · ', $parts) : '-';
}

function emslss_order_cargo_html(array $order, array $meta = []): string
{
    return htmlspecialchars(emslss_order_cargo_summary($order, $meta), ENT_QUOTES, 'UTF-8');
}

function emslss_meta_value(array $meta, string $key): string
{
    if (!isset($meta[$key])) {
        return '';
    }
    $val = $meta[$key];
    if (is_array($val)) {
        return trim((string) ($val[0] ?? ''));
    }
    return trim((string) $val);
}

function emslss_order_is_lss_rejected(array $meta): bool
{
    return emslss_meta_value($meta, 'lss_reject_reason') !== '';
}

/* --- Kết xuất CSV (báo cáo thanh toán / chốt công nợ) --- */

function emslss_export_allowed_date_fields(): array
{
    return [
        'created_at' => 'Ngày EMS push (tạo đơn)',
        'updated_at' => 'Ngày cập nhật cuối',
        'delivered_at' => 'Ngày giao thành công',
        'picked_up_at' => 'Ngày thu gom',
    ];
}

function emslss_export_allowed_statuses(): array
{
    return [
        'new_order', 'assigned_pickup', 'picked_up', 'in_transit',
        'assigned_delivery', 'delivered', 'failed', 'cancelled',
    ];
}

function emslss_export_status_labels(): array
{
    return [
        'new_order' => 'Mới',
        'assigned_pickup' => 'Đã gán pickup',
        'picked_up' => 'Đã thu gom',
        'in_transit' => 'Tại kho',
        'assigned_delivery' => 'Đã gán giao',
        'delivered' => 'Đã giao',
        'failed' => 'Thất bại',
        'cancelled' => 'Hủy / từ chối',
    ];
}

function emslss_export_billable_label(string $status, string $rejectReason = ''): string
{
    if ($status === 'delivered') {
        return 'Tính phí giao';
    }
    if ($status === 'failed') {
        return 'Không tính phí giao';
    }
    if ($status === 'cancelled' && $rejectReason !== '') {
        return 'Từ chối nhận — không tính';
    }
    if ($status === 'cancelled') {
        return 'Hủy — không tính';
    }
    if (in_array($status, ['picked_up', 'in_transit', 'assigned_delivery'], true)) {
        return 'Đang xử lý';
    }
    return 'Chưa vận hành';
}

/**
 * Định nghĩa cột kết xuất chi tiết: key => label, group, default (tick sẵn).
 */
function emslss_export_detail_column_defs(): array
{
    return [
        'id' => ['label' => 'Mã nội bộ', 'group' => 'Định danh', 'default' => false],
        'ems_code' => ['label' => 'Mã EMS', 'group' => 'Định danh', 'default' => true],
        'status' => ['label' => 'Trạng thái', 'group' => 'Định danh', 'default' => true],
        'status_code' => ['label' => 'Mã trạng thái (raw)', 'group' => 'Định danh', 'default' => false],
        'billable_group' => ['label' => 'Nhóm thanh toán', 'group' => 'Định danh', 'default' => true],
        'service_type' => ['label' => 'Dịch vụ', 'group' => 'Định danh', 'default' => true],
        'post_office_name' => ['label' => 'Bưu cục', 'group' => 'Bưu cục', 'default' => true],
        'post_office_address' => ['label' => 'Địa chỉ bưu cục', 'group' => 'Bưu cục', 'default' => false],
        'holder_name' => ['label' => 'Người giữ', 'group' => 'Bưu cục', 'default' => false],
        'holder_phone' => ['label' => 'SĐT người giữ', 'group' => 'Bưu cục', 'default' => false],
        'sender_name' => ['label' => 'Người gửi', 'group' => 'Người gửi', 'default' => false],
        'sender_phone' => ['label' => 'SĐT người gửi', 'group' => 'Người gửi', 'default' => false],
        'sender_address' => ['label' => 'Địa chỉ gửi', 'group' => 'Người gửi', 'default' => false],
        'receiver_name' => ['label' => 'Người nhận', 'group' => 'Người nhận', 'default' => true],
        'receiver_phone' => ['label' => 'SĐT người nhận', 'group' => 'Người nhận', 'default' => true],
        'receiver_address' => ['label' => 'Địa chỉ nhận', 'group' => 'Người nhận', 'default' => true],
        'cargo_type' => ['label' => 'Loại hàng', 'group' => 'Hàng hóa', 'default' => true],
        'cargo_description' => ['label' => 'Nội dung hàng', 'group' => 'Hàng hóa', 'default' => false],
        'weight' => ['label' => 'Khối lượng (g)', 'group' => 'Hàng hóa', 'default' => true],
        'pickup_shipper_name' => ['label' => 'Shipper pickup', 'group' => 'Shipper', 'default' => true],
        'delivery_shipper_name' => ['label' => 'Shipper delivery', 'group' => 'Shipper', 'default' => true],
        'created_at' => ['label' => 'Ngày EMS push', 'group' => 'Thời gian', 'default' => true],
        'picked_up_at' => ['label' => 'Ngày thu gom', 'group' => 'Thời gian', 'default' => false],
        'in_transit_at' => ['label' => 'Ngày nhập kho', 'group' => 'Thời gian', 'default' => false],
        'assigned_delivery_at' => ['label' => 'Ngày gán giao', 'group' => 'Thời gian', 'default' => false],
        'delivered_at' => ['label' => 'Ngày giao thành công', 'group' => 'Thời gian', 'default' => true],
        'failed_at' => ['label' => 'Ngày thất bại', 'group' => 'Thời gian', 'default' => false],
        'cancelled_at' => ['label' => 'Ngày hủy / từ chối', 'group' => 'Thời gian', 'default' => false],
        'updated_at' => ['label' => 'Cập nhật cuối', 'group' => 'Thời gian', 'default' => false],
        'delivery_recipient_name' => ['label' => 'Người nhận thực tế', 'group' => 'Ghi chú', 'default' => true],
        'delivery_note' => ['label' => 'Ghi chú giao', 'group' => 'Ghi chú', 'default' => false],
        'fail_note' => ['label' => 'Ghi chú lỗi', 'group' => 'Ghi chú', 'default' => false],
        'lss_reject_reason' => ['label' => 'Lý do từ chối LSS', 'group' => 'Ghi chú', 'default' => true],
        'pickup_image_count' => ['label' => 'Số ảnh pickup', 'group' => 'Chứng từ', 'default' => false],
        'delivery_image_count' => ['label' => 'Số ảnh giao', 'group' => 'Chứng từ', 'default' => false],
        'has_signature' => ['label' => 'Có chữ ký', 'group' => 'Chứng từ', 'default' => false],
    ];
}

function emslss_export_detail_column_groups(): array
{
    $groups = [];
    foreach (emslss_export_detail_column_defs() as $key => $def) {
        $groups[$def['group']][$key] = $def;
    }
    return $groups;
}

function emslss_export_default_column_keys(): array
{
    $keys = [];
    foreach (emslss_export_detail_column_defs() as $key => $def) {
        if (!empty($def['default'])) {
            $keys[] = $key;
        }
    }
    return $keys;
}

function emslss_export_parse_columns($requested): array
{
    $defs = emslss_export_detail_column_defs();
    if (!is_array($requested) || $requested === []) {
        return emslss_export_default_column_keys();
    }
    $picked = [];
    foreach ($requested as $key) {
        $key = (string) $key;
        if (isset($defs[$key])) {
            $picked[$key] = true;
        }
    }
    if ($picked === []) {
        return emslss_export_default_column_keys();
    }
    $ordered = [];
    foreach (array_keys($defs) as $key) {
        if (isset($picked[$key])) {
            $ordered[] = $key;
        }
    }
    return $ordered;
}

function emslss_export_column_headers(array $columnKeys): array
{
    $defs = emslss_export_detail_column_defs();
    $headers = [];
    foreach ($columnKeys as $key) {
        $headers[] = $defs[$key]['label'] ?? $key;
    }
    return $headers;
}

function emslss_export_row_cell(string $key, array $row, array $statusLabels): string
{
    $reject = trim((string) ($row['lss_reject_reason'] ?? ''));
    switch ($key) {
        case 'status':
            return $statusLabels[$row['status'] ?? ''] ?? (string) ($row['status'] ?? '');
        case 'status_code':
            return (string) ($row['status'] ?? '');
        case 'billable_group':
            return emslss_export_billable_label((string) ($row['status'] ?? ''), $reject);
        case 'has_signature':
            return ((int) ($row['has_signature'] ?? 0)) > 0 ? 'Có' : 'Không';
        case 'pickup_image_count':
        case 'delivery_image_count':
            return (string) (int) ($row[$key] ?? 0);
        case 'weight':
            return emslss_format_weight($row['weight'] ?? null);
        default:
            return (string) ($row[$key] ?? '');
    }
}

function emslss_export_row_cells(array $row, array $columnKeys, array $statusLabels): array
{
    $cells = [];
    foreach ($columnKeys as $key) {
        $cells[] = emslss_export_row_cell($key, $row, $statusLabels);
    }
    return $cells;
}

function emslss_csv_cell($value): string
{
    $s = (string) $value;
    $s = str_replace(["\r\n", "\r", "\n"], ' ', $s);
    if (str_contains($s, '"') || str_contains($s, ',') || str_contains($s, ';')) {
        return '"' . str_replace('"', '""', $s) . '"';
    }
    return $s;
}

function emslss_csv_row(array $cells): string
{
    return implode(',', array_map('emslss_csv_cell', $cells)) . "\r\n";
}

function emslss_export_send_csv(string $filename, callable $writer): void
{
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo "\xEF\xBB\xBF";
    $writer();
    exit;
}

function emslss_export_build_filters(array $input): array
{
    $dateField = $input['date_field'] ?? 'created_at';
    $allowedDate = emslss_export_allowed_date_fields();
    if (!isset($allowedDate[$dateField])) {
        $dateField = 'created_at';
    }

    $dateFrom = trim((string) ($input['date_from'] ?? ''));
    $dateTo = trim((string) ($input['date_to'] ?? ''));
    $statuses = $input['status'] ?? [];
    if (!is_array($statuses)) {
        $statuses = $statuses !== '' ? [$statuses] : [];
    }
    $serviceType = trim((string) ($input['service_type'] ?? ''));
    $keyword = trim((string) ($input['keyword'] ?? ''));
    $shipperId = (int) ($input['shipper_id'] ?? 0);
    $lssRejectedOnly = !empty($input['lss_rejected_only']);

    $where = ['1=1'];
    $types = '';
    $params = [];

    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        if (in_array($dateField, ['created_at', 'updated_at'], true)) {
            $where[] = "DATE(o.$dateField) >= ?";
            $types .= 's';
            $params[] = $dateFrom;
        } else {
            $col = $dateField === 'delivered_at' ? 'delivered_at' : 'picked_up_at';
            $where[] = "DATE(COALESCE(milestones.$col, o.updated_at)) >= ?";
            $types .= 's';
            $params[] = $dateFrom;
        }
    }

    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        if (in_array($dateField, ['created_at', 'updated_at'], true)) {
            $where[] = "DATE(o.$dateField) <= ?";
            $types .= 's';
            $params[] = $dateTo;
        } else {
            $col = $dateField === 'delivered_at' ? 'delivered_at' : 'picked_up_at';
            $where[] = "DATE(COALESCE(milestones.$col, o.updated_at)) <= ?";
            $types .= 's';
            $params[] = $dateTo;
        }
    }

    $validStatuses = emslss_export_allowed_statuses();
    $statuses = array_values(array_filter($statuses, static fn ($s) => in_array($s, $validStatuses, true)));
    if ($statuses !== []) {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $where[] = "o.status IN ($placeholders)";
        $types .= str_repeat('s', count($statuses));
        $params = array_merge($params, $statuses);
    }

    if ($serviceType !== '' && in_array($serviceType, ['door_to_door', 'door_to_hub', 'hub_to_door'], true)) {
        $where[] = 'o.service_type = ?';
        $types .= 's';
        $params[] = $serviceType;
    }

    if ($keyword !== '') {
        $where[] = '(o.ems_code LIKE ? OR o.post_office_name LIKE ? OR o.receiver_name LIKE ? OR o.sender_name LIKE ?)';
        $types .= 'ssss';
        $like = '%' . $keyword . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }

    if ($shipperId > 0) {
        $where[] = '(o.pickup_shipper_id = ? OR o.delivery_shipper_id = ?)';
        $types .= 'ii';
        $params[] = $shipperId;
        $params[] = $shipperId;
    }

    if ($lssRejectedOnly) {
        $where[] = "EXISTS (SELECT 1 FROM emslss_order_meta m WHERE m.order_id = o.id AND m.meta_key = 'lss_reject_reason')";
    }

    return [
        'sql' => implode(' AND ', $where),
        'types' => $types,
        'params' => $params,
        'date_field' => $dateField,
    ];
}

function emslss_export_milestones_sql(): string
{
    return "
        LEFT JOIN (
            SELECT order_id,
                MIN(CASE WHEN status = 'picked_up' THEN created_at END) AS picked_up_at,
                MIN(CASE WHEN status = 'in_transit' THEN created_at END) AS in_transit_at,
                MIN(CASE WHEN status = 'assigned_delivery' THEN created_at END) AS assigned_delivery_at,
                MIN(CASE WHEN status = 'delivered' THEN created_at END) AS delivered_at,
                MIN(CASE WHEN status = 'failed' THEN created_at END) AS failed_at,
                MIN(CASE WHEN status = 'cancelled' THEN created_at END) AS cancelled_at
            FROM emslss_tracking
            GROUP BY order_id
        ) milestones ON milestones.order_id = o.id
    ";
}

function emslss_export_detail_sql(string $whereSql): string
{
    $milestones = emslss_export_milestones_sql();
    return "
        SELECT
            o.id, o.ems_code, o.status, o.service_type,
            o.post_office_name, o.post_office_address,
            o.holder_name, o.holder_phone,
            o.sender_name, o.sender_phone, o.sender_address,
            o.receiver_name, o.receiver_phone, o.receiver_address,
            o.cargo_type, o.weight, o.created_at, o.updated_at,
            pu.full_name AS pickup_shipper_name,
            du.full_name AS delivery_shipper_name,
            milestones.picked_up_at, milestones.in_transit_at,
            milestones.assigned_delivery_at, milestones.delivered_at,
            milestones.failed_at, milestones.cancelled_at,
            (SELECT meta_value FROM emslss_order_meta WHERE order_id = o.id AND meta_key = 'cargo_description' ORDER BY id DESC LIMIT 1) AS cargo_description,
            (SELECT meta_value FROM emslss_order_meta WHERE order_id = o.id AND meta_key = 'lss_reject_reason' ORDER BY id DESC LIMIT 1) AS lss_reject_reason,
            (SELECT meta_value FROM emslss_order_meta WHERE order_id = o.id AND meta_key = 'delivery_recipient_name' ORDER BY id DESC LIMIT 1) AS delivery_recipient_name,
            (SELECT meta_value FROM emslss_order_meta WHERE order_id = o.id AND meta_key = 'delivery_note' ORDER BY id DESC LIMIT 1) AS delivery_note,
            (SELECT meta_value FROM emslss_order_meta WHERE order_id = o.id AND meta_key = 'fail_note' ORDER BY id DESC LIMIT 1) AS fail_note,
            (SELECT COUNT(*) FROM emslss_images i WHERE i.order_id = o.id AND (i.image_path LIKE '%/pickup/%' OR i.image_path LIKE '%pickup%')) AS pickup_image_count,
            (SELECT COUNT(*) FROM emslss_images i WHERE i.order_id = o.id AND (i.image_path LIKE '%/delivery/%' OR i.image_path LIKE '%delivery%')) AS delivery_image_count,
            (SELECT COUNT(*) FROM emslss_order_meta m WHERE m.order_id = o.id AND m.meta_key = 'customer_signature') AS has_signature
        FROM emslss_orders o
        LEFT JOIN emslss_users pu ON pu.id = o.pickup_shipper_id
        LEFT JOIN emslss_users du ON du.id = o.delivery_shipper_id
        $milestones
        WHERE $whereSql
        ORDER BY o.created_at DESC, o.id DESC
    ";
}

function emslss_export_count_filtered(mysqli $conn, string $whereSql, string $types, array $params): int
{
    $sql = 'SELECT COUNT(*) AS c FROM emslss_orders o ' . emslss_export_milestones_sql() . ' WHERE ' . $whereSql;
    $res = emslss_export_run_query($conn, $sql, $types, $params);
    if (!$res) {
        return 0;
    }
    return (int) ($res->fetch_assoc()['c'] ?? 0);
}

function emslss_export_preview_limit(): int
{
    return 30;
}

function emslss_export_summary_shipper_sql(string $whereSql): string
{
    $milestones = emslss_export_milestones_sql();
    return "
        SELECT
            COALESCE(du.full_name, pu.full_name, '(chưa gán)') AS shipper_name,
            SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) AS cnt_delivered,
            SUM(CASE WHEN o.status = 'failed' THEN 1 ELSE 0 END) AS cnt_failed,
            SUM(CASE WHEN o.status IN ('picked_up','in_transit','assigned_delivery') THEN 1 ELSE 0 END) AS cnt_in_progress,
            SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cnt_cancelled,
            COUNT(*) AS cnt_total
        FROM emslss_orders o
        LEFT JOIN emslss_users pu ON pu.id = o.pickup_shipper_id
        LEFT JOIN emslss_users du ON du.id = o.delivery_shipper_id
        $milestones
        WHERE $whereSql
        GROUP BY shipper_name
        ORDER BY cnt_delivered DESC, cnt_total DESC
    ";
}

function emslss_export_summary_status_sql(string $whereSql): string
{
    $milestones = emslss_export_milestones_sql();
    return "
        SELECT o.status, o.service_type,
            COUNT(*) AS cnt_orders,
            SUM(COALESCE(o.weight, 0)) AS sum_weight_g
        FROM emslss_orders o
        $milestones
        WHERE $whereSql
        GROUP BY o.status, o.service_type
        ORDER BY o.status, o.service_type
    ";
}

function emslss_export_run_query(mysqli $conn, string $sql, string $types, array $params): mysqli_result|false
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

function emslss_export_filename(string $prefix): string
{
    return $prefix . '_' . date('Y-m-d_His') . '.csv';
}
