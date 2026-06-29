<?php

/**
 * Helper hiển thị hàng hóa & từ chối nhận đơn.
 */

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
        $parts[] = (float) $weight . ' kg';
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
