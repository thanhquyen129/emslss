<?php
require_once __DIR__ . '/../../config/order_helpers.php';

function admin_trim_text(string $text, int $len = 50): string
{
    $text = (string) $text;
    if (mb_strlen($text) <= $len) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    $short = mb_substr($text, 0, $len);
    return htmlspecialchars($short, ENT_QUOTES, 'UTF-8') . '…';
}

function admin_trim_attr(string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function admin_can_assign_delivery(string $status, array $row = []): bool
{
    if (in_array($status, ['in_transit', 'assigned_delivery'], true)) {
        return true;
    }
    if ($status === 'failed' && (int) ($row['delivery_shipper_id'] ?? 0) > 0) {
        return true;
    }
    return false;
}

function admin_load_order_ack_meta(mysqli $conn, array $orderIds): array
{
    $meta = [];
    if ($orderIds === []) {
        return $meta;
    }
    $ids = implode(',', array_map('intval', $orderIds));
    $res = $conn->query("
        SELECT order_id, meta_key, meta_value
        FROM emslss_order_meta
        WHERE order_id IN ($ids)
          AND meta_key IN ('shipper_ack_pickup', 'shipper_ack_delivery')
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $meta[(int) $row['order_id']][$row['meta_key']] = $row['meta_value'];
        }
    }
    return $meta;
}

function admin_render_ack_html(int $orderId, array $orderMeta): string
{
    $html = '';
    if (!empty($orderMeta[$orderId]['shipper_ack_pickup'])) {
        $html .= '<div class="small text-success mb-1">✅ Shipper pickup: '
            . htmlspecialchars($orderMeta[$orderId]['shipper_ack_pickup']) . '</div>';
    }
    if (!empty($orderMeta[$orderId]['shipper_ack_delivery'])) {
        $html .= '<div class="small text-success mb-1">✅ Shipper delivery: '
            . htmlspecialchars($orderMeta[$orderId]['shipper_ack_delivery']) . '</div>';
    }
    return $html;
}

function admin_render_trim_span(string $text, int $len = 50): string
{
    $full = (string) $text;
    if ($full === '') {
        return '';
    }
    if (mb_strlen($full) <= $len) {
        return htmlspecialchars($full, ENT_QUOTES, 'UTF-8');
    }
    return '<span class="trim-tip" tabindex="0" data-full="' . admin_trim_attr($full) . '">'
        . admin_trim_text($full, $len) . '</span>';
}

function admin_render_pickup_select(array $row, array $pickupUsers): string
{
    $id = (int) $row['id'];
    $html = '<select class="form-select form-select-sm assign-select assign-user" data-order-id="' . $id . '" data-type="pickup">';
    $html .= '<option value="">-- Pickup --</option>';
    foreach ($pickupUsers as $u) {
        $sel = ((int) $row['pickup_shipper_id'] === (int) $u['id']) ? ' selected' : '';
        $html .= '<option value="' . (int) $u['id'] . '"' . $sel . '>'
            . htmlspecialchars($u['full_name']) . '</option>';
    }
    $html .= '</select>';
    return $html;
}

function admin_render_delivery_select(array $row, array $deliveryUsers): string
{
    $id = (int) $row['id'];
    $disabled = admin_can_assign_delivery((string) $row['status'], $row) ? '' : ' disabled';
    $title = $disabled !== '' ? ' title="Cần Operation nhập kho (in_transit) trước"' : '';
    $html = '<select class="form-select form-select-sm assign-select assign-user"'
        . ' data-order-id="' . $id . '" data-type="delivery"' . $disabled . $title . '>';
    $html .= '<option value="">-- Delivery --</option>';
    foreach ($deliveryUsers as $u) {
        $sel = ((int) $row['delivery_shipper_id'] === (int) $u['id']) ? ' selected' : '';
        $html .= '<option value="' . (int) $u['id'] . '"' . $sel . '>'
            . htmlspecialchars($u['full_name']) . '</option>';
    }
    $html .= '</select>';
    if ($disabled !== '') {
        $html .= '<div class="form-text text-warning" style="font-size:11px">Chờ nhập kho</div>';
    }
    return $html;
}

function admin_render_cargo_line(array $row, array $orderCargoMeta): string
{
    $meta = $orderCargoMeta[(int) $row['id']] ?? [];
    $text = emslss_order_cargo_summary($row, $meta);
    if ($text === '-') {
        return '';
    }
    return '<div class="small-line mb-2">📦 ' . admin_render_trim_span($text, 60) . '</div>';
}

function admin_render_reject_button(array $row): string
{
    if (!emslss_admin_can_reject_order((string) $row['status'])) {
        return '';
    }
    $id = (int) $row['id'];
    $code = htmlspecialchars($row['ems_code'], ENT_QUOTES, 'UTF-8');
    return '<button type="button" class="btn btn-sm btn-outline-danger btn-reject-order mt-2"'
        . ' data-order-id="' . $id . '" data-ems-code="' . $code . '">'
        . 'Từ chối nhận</button>';
}

function admin_orders_allowed_statuses(): array
{
    return [
        'new_order', 'assigned_pickup', 'picked_up', 'assigned_delivery',
        'in_transit', 'delivered', 'failed', 'cancelled',
    ];
}

function admin_orders_list_filter(array $get, array $options = []): array
{
    $allowedStatuses = $options['allowed_statuses'] ?? admin_orders_allowed_statuses();
    $activeOnly = !empty($options['active_only']);

    $statusFilter = trim((string) ($get['status'] ?? ''));
    $emsKeyword = trim((string) ($get['ems'] ?? ''));

    $where = [];
    $types = '';
    $params = [];

    if ($emsKeyword !== '') {
        $where[] = 'ems_code LIKE ?';
        $types .= 's';
        $params[] = '%' . $emsKeyword . '%';
    }

    if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
        $where[] = 'status = ?';
        $types .= 's';
        $params[] = $statusFilter;
    } elseif ($activeOnly && $emsKeyword === '') {
        $where[] = "status NOT IN ('delivered','cancelled')";
    }

    return [
        'whereSql' => $where === [] ? '1=1' : implode(' AND ', $where),
        'types' => $types,
        'params' => $params,
        'statusFilter' => $statusFilter,
        'emsKeyword' => $emsKeyword,
    ];
}

function admin_orders_page_url(int $page, string $statusFilter, string $emsKeyword, string $base = ''): string
{
    $params = [];
    if ($statusFilter !== '') {
        $params['status'] = $statusFilter;
    }
    if ($emsKeyword !== '') {
        $params['ems'] = $emsKeyword;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }
    $query = $params === [] ? '' : '?' . http_build_query($params);
    return $base . $query;
}

function admin_orders_run_count(mysqli $conn, array $filter): int
{
    $sql = 'SELECT COUNT(*) AS total FROM emslss_orders WHERE ' . $filter['whereSql'];
    $stmt = $conn->prepare($sql);
    if ($filter['types'] !== '') {
        $stmt->bind_param($filter['types'], ...$filter['params']);
    }
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
}

function admin_orders_fetch_page(mysqli $conn, array $filter, int $limit, int $offset): array
{
    $sql = 'SELECT * FROM emslss_orders WHERE ' . $filter['whereSql']
        . ' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?';
    $stmt = $conn->prepare($sql);
    $types = $filter['types'] . 'ii';
    $params = array_merge($filter['params'], [$limit, $offset]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $orders = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $orders[] = $row;
    }
    return $orders;
}

function admin_render_ems_search_form(string $statusFilter, string $emsKeyword, string $action = ''): string
{
    $statusEsc = htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8');
    $emsEsc = htmlspecialchars($emsKeyword, ENT_QUOTES, 'UTF-8');
    $clearUrl = admin_orders_page_url(1, $statusFilter, '', $action);
    $html = '<form method="get" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" class="d-flex flex-wrap gap-2 align-items-center mb-3">';
    if ($statusFilter !== '') {
        $html .= '<input type="hidden" name="status" value="' . $statusEsc . '">';
    }
    $html .= '<input type="text" name="ems" class="form-control form-control-sm" style="max-width:240px"'
        . ' placeholder="Tìm nhanh mã EMS" value="' . $emsEsc . '">'
        . '<button type="submit" class="btn btn-sm btn-primary">Tìm</button>';
    if ($emsKeyword !== '') {
        $html .= '<a href="' . htmlspecialchars($clearUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-sm btn-outline-secondary">Xóa tìm</a>';
    }
    $html .= '</form>';
    return $html;
}
