<?php
/**
 * Bootstrap độc lập cho chức năng Bảng kê (không dùng auth/module LSS).
 */
require_once __DIR__ . '/../config/db.php';

function bangke_ensure_table(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS bangke_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        stt INT NOT NULL DEFAULT 0,
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

    // Tự động thêm cột stt nếu bảng đã tạo từ trước
    $chk = $conn->query("SHOW COLUMNS FROM bangke_items LIKE 'stt'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE bangke_items ADD COLUMN stt INT NOT NULL DEFAULT 0 AFTER id");
    }

    // Tự động gán STT cho các bản ghi cũ đang có stt = 0 (reset bắt đầu từ 1 ở mỗi tháng)
    $resZero = $conn->query("SELECT id, DATE_FORMAT(request_date, '%Y-%m') AS ym FROM bangke_items WHERE stt = 0 ORDER BY request_date ASC, id ASC");
    if ($resZero && $resZero->num_rows > 0) {
        $upStmt = $conn->prepare("UPDATE bangke_items SET stt = ? WHERE id = ?");
        $monthCounters = [];
        while ($rowZero = $resZero->fetch_assoc()) {
            $rowId = (int) $rowZero['id'];
            $ym = (string) $rowZero['ym'];
            if (!isset($monthCounters[$ym])) {
                $range = bangke_month_range($ym);
                $maxVal = 0;
                if ($range) {
                    $chkMax = $conn->prepare("SELECT COALESCE(MAX(stt), 0) FROM bangke_items WHERE request_date >= ? AND request_date < ?");
                    $chkMax->bind_param('ss', $range[0], $range[1]);
                    $chkMax->execute();
                    $maxVal = (int) ($chkMax->get_result()->fetch_row()[0] ?? 0);
                    $chkMax->close();
                }
                $monthCounters[$ym] = $maxVal;
            }
            $monthCounters[$ym]++;
            $newStt = $monthCounters[$ym];
            $upStmt->bind_param('ii', $newStt, $rowId);
            $upStmt->execute();
        }
        $upStmt->close();
    }
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

/**
 * Chuẩn hóa chuỗi (bỏ dấu tiếng Việt, ký tự đặc biệt) để so khớp nhận diện sân bay.
 */
function bangke_normalize_text(string $str): string
{
    $str = mb_strtolower(trim($str), 'UTF-8');
    $unicode = [
        'a' => 'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
        'd' => 'đ',
        'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
        'i' => 'í|ì|ỉ|ĩ|ị',
        'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
        'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
        'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
    ];
    foreach ($unicode as $nonAccent => $accent) {
        $str = preg_replace('/(' . $accent . ')/u', $nonAccent, $str);
    }
    return (string) preg_replace('/[^a-z0-9]/', '', $str);
}

/**
 * Danh mục sân bay tại Việt Nam và một số sân bay quốc tế thường dùng.
 * @return array<string,array{name:string,code:string,province:string,aliases:list<string>}>
 */
function bangke_airport_catalog(): array
{
    return [
        'HAN' => ['name' => 'Sân bay Nội Bài', 'code' => 'HAN', 'province' => 'Hà Nội', 'aliases' => ['han', 'hanoi', 'noibai', 'sanbaynoibai']],
        'SGN' => ['name' => 'Sân bay Tân Sơn Nhất', 'code' => 'SGN', 'province' => 'Tp. Hồ Chí Minh', 'aliases' => ['sgn', 'saigon', 'hochiminh', 'tphcm', 'tphochiminh', 'tansonnhat', 'sanbaytansonnhat']],
        'BMV' => ['name' => 'Sân bay Buôn Ma Thuột', 'code' => 'BMV', 'province' => 'Đắk Lắk', 'aliases' => ['bmv', 'bmt', 'buonmathuot', 'buonmethuot', 'daklak', 'daclac', 'sanbaybuonmathuot']],
        'DAD' => ['name' => 'Sân bay Đà Nẵng', 'code' => 'DAD', 'province' => 'Đà Nẵng', 'aliases' => ['dad', 'danang', 'sanbaydanang']],
        'CXR' => ['name' => 'Sân bay Cam Ranh', 'code' => 'CXR', 'province' => 'Khánh Hòa', 'aliases' => ['cxr', 'camranh', 'nhatrang', 'khanhhoa', 'sanbaycamranh']],
        'PQC' => ['name' => 'Sân bay Phú Quốc', 'code' => 'PQC', 'province' => 'Phú Quốc', 'aliases' => ['pqc', 'phuquoc', 'sanbayphuquoc']],
        'VCA' => ['name' => 'Sân bay Cần Thơ', 'code' => 'VCA', 'province' => 'Cần Thơ', 'aliases' => ['vca', 'cantho', 'sanbaycantho']],
        'HPH' => ['name' => 'Sân bay Cát Bi', 'code' => 'HPH', 'province' => 'Hải Phòng', 'aliases' => ['hph', 'catbi', 'haiphong', 'sanbaycatbi']],
        'VII' => ['name' => 'Sân bay Vinh', 'code' => 'VII', 'province' => 'Nghệ An', 'aliases' => ['vii', 'vinh', 'nghean', 'sanbayvinh']],
        'HUI' => ['name' => 'Sân bay Phú Bài', 'code' => 'HUI', 'province' => 'Thừa Thiên Huế', 'aliases' => ['hui', 'phubai', 'hue', 'thuathienhue', 'sanbayphubai']],
        'THD' => ['name' => 'Sân bay Thọ Xuân', 'code' => 'THD', 'province' => 'Thanh Hóa', 'aliases' => ['thd', 'thoxuan', 'thanhhoa', 'sanbaythoxuan']],
        'VDH' => ['name' => 'Sân bay Đồng Hới', 'code' => 'VDH', 'province' => 'Quảng Bình', 'aliases' => ['vdh', 'donghoi', 'quangbinh', 'sanbaydonghoi']],
        'UIH' => ['name' => 'Sân bay Phù Cát', 'code' => 'UIH', 'province' => 'Bình Định', 'aliases' => ['uih', 'phucat', 'quynhon', 'binhdinh', 'sanbayphucat']],
        'TBB' => ['name' => 'Sân bay Tuy Hòa', 'code' => 'TBB', 'province' => 'Phú Yên', 'aliases' => ['tbb', 'tuyhoa', 'phuyen', 'sanbaytuyhoa']],
        'PXU' => ['name' => 'Sân bay Pleiku', 'code' => 'PXU', 'province' => 'Gia Lai', 'aliases' => ['pxu', 'pleiku', 'playku', 'gialai', 'sanbaypleiku']],
        'DLI' => ['name' => 'Sân bay Liên Khương', 'code' => 'DLI', 'province' => 'Lâm Đồng', 'aliases' => ['dli', 'lienkhuong', 'dalat', 'lamdong', 'sanbaylienkhuong']],
        'VCS' => ['name' => 'Sân bay Côn Đảo', 'code' => 'VCS', 'province' => 'Côn Đảo', 'aliases' => ['vcs', 'condao', 'conson', 'sanbaycondao']],
        'VCL' => ['name' => 'Sân bay Chu Lai', 'code' => 'VCL', 'province' => 'Quảng Nam', 'aliases' => ['vcl', 'chulai', 'quangnam', 'tamky', 'sanbaychulai']],
        'DIN' => ['name' => 'Sân bay Điện Biên Phủ', 'code' => 'DIN', 'province' => 'Điện Biên', 'aliases' => ['din', 'dienbien', 'dienbienphu', 'sanbaydienbienphu']],
        'VKG' => ['name' => 'Sân bay Rạch Giá', 'code' => 'VKG', 'province' => 'Kiên Giang', 'aliases' => ['vkg', 'rachgia', 'kiengiang', 'sanbayrachgia']],
        'CAH' => ['name' => 'Sân bay Cà Mau', 'code' => 'CAH', 'province' => 'Cà Mau', 'aliases' => ['cah', 'camau', 'sanbaycamau']],
        'VDO' => ['name' => 'Sân bay Vân Đồn', 'code' => 'VDO', 'province' => 'Quảng Ninh', 'aliases' => ['vdo', 'vandon', 'quangninh', 'sanbayvandon']],
        'BKK' => ['name' => 'Sân bay Suvarnabhumi', 'code' => 'BKK', 'province' => 'Bangkok', 'aliases' => ['bkk', 'suvarnabhumi', 'bangkok']],
        'DMK' => ['name' => 'Sân bay Don Mueang', 'code' => 'DMK', 'province' => 'Bangkok', 'aliases' => ['dmk', 'donmueang']],
        'SIN' => ['name' => 'Sân bay Changi', 'code' => 'SIN', 'province' => 'Singapore', 'aliases' => ['sin', 'changi', 'singapore']],
        'ICN' => ['name' => 'Sân bay Incheon', 'code' => 'ICN', 'province' => 'Seoul', 'aliases' => ['icn', 'incheon', 'seoul']],
        'NRT' => ['name' => 'Sân bay Narita', 'code' => 'NRT', 'province' => 'Tokyo', 'aliases' => ['nrt', 'narita', 'tokyo']],
        'HND' => ['name' => 'Sân bay Haneda', 'code' => 'HND', 'province' => 'Tokyo', 'aliases' => ['hnd', 'haneda']],
        'KIX' => ['name' => 'Sân bay Kansai', 'code' => 'KIX', 'province' => 'Osaka', 'aliases' => ['kix', 'kansai', 'osaka']],
        'TPE' => ['name' => 'Sân bay Đào Viên', 'code' => 'TPE', 'province' => 'Đài Bắc', 'aliases' => ['tpe', 'taoyuan', 'daovien', 'taipei']],
        'HKG' => ['name' => 'Sân bay Hồng Kông', 'code' => 'HKG', 'province' => 'Hong Kong', 'aliases' => ['hkg', 'hongkong']],
        'CAN' => ['name' => 'Sân bay Bạch Vân', 'code' => 'CAN', 'province' => 'Quảng Châu', 'aliases' => ['can', 'baiyun', 'quangchau', 'guangzhou']],
        'PVG' => ['name' => 'Sân bay Phố Đông', 'code' => 'PVG', 'province' => 'Thượng Hải', 'aliases' => ['pvg', 'pudong', 'thuonghai', 'shanghai']],
    ];
}

/**
 * Tìm thông tin sân bay dựa trên mã IATA hoặc tên tỉnh/thành phố/sân bay.
 * @return array{name:string,code:string,province:string,aliases:list<string>}
 */
function bangke_airport_info(string $input, string $fallbackRole = 'sender'): array
{
    $catalog = bangke_airport_catalog();
    $raw = trim($input);
    if ($raw !== '') {
        $codeUpper = strtoupper($raw);
        if (isset($catalog[$codeUpper])) {
            return $catalog[$codeUpper];
        }
        $key = bangke_normalize_text($raw);
        foreach ($catalog as $item) {
            if (in_array($key, $item['aliases'], true)) {
                return $item;
            }
        }
        foreach ($catalog as $item) {
            foreach ($item['aliases'] as $al) {
                if (strlen($al) >= 4 && (strpos($key, $al) !== false || strpos($al, $key) !== false)) {
                    return $item;
                }
            }
        }
        if (preg_match('/^s[aâ]n\s*bay\s*(.+)$/iu', $raw, $m)) {
            return [
                'name' => 'Sân bay ' . trim($m[1]),
                'code' => $codeUpper,
                'province' => trim($m[1]),
                'aliases' => [],
            ];
        }
        if (preg_match('/^[A-Z0-9]{3}$/', $codeUpper)) {
            return [
                'name' => 'Sân bay ' . $codeUpper,
                'code' => $codeUpper,
                'province' => $codeUpper,
                'aliases' => [],
            ];
        }
        return [
            'name' => 'Sân bay ' . $raw,
            'code' => '',
            'province' => $raw,
            'aliases' => [],
        ];
    }

    return $fallbackRole === 'receiver'
        ? $catalog['SGN']
        : $catalog['HAN'];
}

/**
 * Ghép số hiệu chuyến bay với mã sân bay gửi - nhận.
 * VD: VN1603 (HAN-BMV)
 */
function bangke_format_flight_no(?string $flightNo, string $from, string $to): string
{
    $senderAirport   = bangke_airport_info($from, 'sender');
    $receiverAirport = bangke_airport_info($to, 'receiver');
    $senderCode   = $senderAirport['code'] !== '' ? $senderAirport['code'] : strtoupper(trim($from));
    $receiverCode = $receiverAirport['code'] !== '' ? $receiverAirport['code'] : strtoupper(trim($to));
    $routeCode    = ($senderCode !== '' && $receiverCode !== '') ? ($senderCode . '-' . $receiverCode) : '';

    $f = trim((string) $flightNo);
    if ($f !== '') {
        if ($routeCode !== '' && strpos($f, $routeCode) === false) {
            return $f . ' (' . $routeCode . ')';
        }
        return $f;
    }
    return $routeCode !== '' ? '(' . $routeCode . ')' : '—';
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
        "SELECT id, stt, request_date, route_leg, flight_no, airway_bill,
                vin_no, serial_no, package_count, weight, created_at
         FROM bangke_items
         WHERE request_date >= ? AND request_date < ?
         ORDER BY CASE WHEN stt > 0 THEN stt ELSE 999999 END ASC, request_date ASC, id ASC"
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
        "SELECT id, stt, request_date, route_leg, flight_no, airway_bill,
                vin_no, serial_no, package_count, weight, created_at
         FROM bangke_items
         WHERE id IN ($placeholders)
         ORDER BY CASE WHEN stt > 0 THEN stt ELSE 999999 END ASC, request_date ASC, id ASC"
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

function bangke_fetch_by_id(mysqli $conn, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $conn->prepare(
        "SELECT id, stt, request_date, route_leg, flight_no, airway_bill,
                vin_no, serial_no, package_count, weight, created_at
         FROM bangke_items
         WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function bangke_delete_by_ids(mysqli $conn, array $ids): int
{
    $ids = array_values(array_filter(array_map('intval', $ids), static function ($v) {
        return $v > 0;
    }));
    if (!$ids) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare("DELETE FROM bangke_items WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

bangke_ensure_table($conn);
