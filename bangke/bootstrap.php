<?php
/**
 * Bootstrap độc lập cho chức năng Bảng kê (không dùng auth/module LSS).
 */
require_once __DIR__ . '/../config/db.php';

function bangke_ensure_table(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS bangke_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        request_date DATE NOT NULL,
        route_leg VARCHAR(255) NOT NULL DEFAULT '',
        flight_no VARCHAR(100) NOT NULL DEFAULT '',
        airway_bill VARCHAR(120) NOT NULL DEFAULT '',
        vin_no VARCHAR(120) NOT NULL DEFAULT '',
        serial_no VARCHAR(120) NOT NULL DEFAULT '',
        package_count INT NOT NULL DEFAULT 0,
        weight DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_request_date (request_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
}

function bangke_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function bangke_month_label(string $ym): string
{
    $parts = explode('-', $ym);
    if (count($parts) !== 2) {
        return $ym;
    }
    return sprintf('%02d/%s', (int) $parts[1], $parts[0]);
}

function bangke_fetch_months(mysqli $conn): array
{
    $months = [];
    $res = $conn->query(
        "SELECT DATE_FORMAT(MIN(request_date), '%Y-%m') AS ym
         FROM bangke_items
         GROUP BY YEAR(request_date), MONTH(request_date)
         ORDER BY YEAR(request_date) DESC, MONTH(request_date) DESC"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $months[] = $row['ym'];
        }
    }
    return $months;
}

function bangke_month_range(string $ym): ?array
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
        return null;
    }
    $y = (int) $m[1];
    $mo = (int) $m[2];
    if ($mo < 1 || $mo > 12) {
        return null;
    }
    $from = sprintf('%04d-%02d-01', $y, $mo);
    $to = date('Y-m-d', strtotime($from . ' +1 month'));
    return [$from, $to];
}

/**
 * Tách chặng thành From / To (VD: "Hà Nội → Hồ Chí Minh", "HAN-SGN").
 * @return array{0:string,1:string}
 */
function bangke_parse_route(string $route): array
{
    $route = trim($route);
    if ($route === '') {
        return ['Hà Nội', 'Hồ Chí Minh'];
    }
    if (preg_match('/^(.+?)\s*(?:→|->|–|—|\-|\/|toi|đến|den)\s*(.+)$/iu', $route, $m)) {
        return [trim($m[1]), trim($m[2])];
    }
    return [$route, ''];
}

function bangke_fmt_weight($weight): string
{
    $w = rtrim(rtrim(number_format((float) $weight, 2, '.', ''), '0'), '.');
    return ($w === '' ? '0' : $w);
}

function bangke_fetch_by_month(mysqli $conn, string $ym): array
{
    $range = bangke_month_range($ym);
    if (!$range) {
        return [];
    }
    // Dùng khoảng ngày (DATE) để tránh lỗi mix collation utf8mb4_unicode_ci vs general_ci
    $stmt = $conn->prepare(
        "SELECT id, request_date, route_leg, flight_no, airway_bill,
                vin_no, serial_no, package_count, weight, created_at
         FROM bangke_items
         WHERE request_date >= ? AND request_date < ?
         ORDER BY request_date DESC, id DESC"
    );
    $stmt->bind_param('ss', $range[0], $range[1]);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function bangke_fetch_by_ids(mysqli $conn, array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids), static function ($v) {
        return $v > 0;
    }));
    if (!$ids) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare(
        "SELECT id, request_date, route_leg, flight_no, airway_bill,
                vin_no, serial_no, package_count, weight, created_at
         FROM bangke_items
         WHERE id IN ($placeholders)
         ORDER BY request_date ASC, id ASC"
    );
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

bangke_ensure_table($conn);
