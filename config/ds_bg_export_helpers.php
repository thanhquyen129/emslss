<?php
/**
 * Kết xuất danh sách bưu gửi EMS qua LSS theo mẫu docs/DS BG EMS qua LSS.xlsx
 */

/** Nhãn kết quả phát lần đầu (theo mốc tracking delivered/failed sớm nhất). */
function emslss_ds_bg_first_result_label(?string $firstDeliveryStatus): string
{
    if ($firstDeliveryStatus === 'delivered') {
        return 'Phát thành công';
    }
    if ($firstDeliveryStatus === 'failed') {
        return 'Phát không thành công';
    }
    // Chưa có mốc phát thật → để trống (không bịa “phát không thành công”).
    return '';
}

/** Đơn đã vào giai đoạn phát (có/đang/đã phát). */
function emslss_ds_bg_is_delivery_phase(string $status): bool
{
    return in_array($status, ['assigned_delivery', 'failed', 'delivered'], true);
}

/** Nhãn trạng thái cuối theo status hiện tại của đơn. */
function emslss_ds_bg_final_status_label(string $status): string
{
    if ($status === 'delivered') {
        return 'Phát thành công';
    }
    if ($status === 'cancelled') {
        return 'Hủy';
    }
    if (emslss_ds_bg_is_delivery_phase($status)) {
        return 'Phát lần sau';
    }
    // LSS chỉ nhận dữ liệu / chưa phát hàng thật → Đơn mới.
    return 'Đơn mới';
}

function emslss_ds_bg_format_ts(?string $datetime, string $format): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : '';
}

/**
 * @return array{sql:string,types:string,params:array,date_from:string,date_to:string}
 */
function emslss_ds_bg_build_filters(array $input): array
{
    $dateFrom = trim((string) ($input['date_from'] ?? ''));
    $dateTo = trim((string) ($input['date_to'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = date('Y-m-01');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $dateTo = date('Y-m-d');
    }
    if ($dateFrom > $dateTo) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    return [
        'sql' => 'DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?',
        'types' => 'ss',
        'params' => [$dateFrom, $dateTo],
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ];
}

function emslss_ds_bg_list_sql(string $whereSql): string
{
    return "
        SELECT
            o.id,
            o.ems_code,
            o.status,
            o.created_at,
            o.updated_at,
            del.first_delivery_at,
            del.first_delivery_status,
            del.last_delivery_at,
            assign.first_assigned_at
        FROM emslss_orders o
        LEFT JOIN (
            SELECT
                order_id,
                MIN(created_at) AS first_delivery_at,
                SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY created_at ASC, id ASC), ',', 1) AS first_delivery_status,
                MAX(created_at) AS last_delivery_at
            FROM emslss_tracking
            WHERE status IN ('delivered', 'failed')
            GROUP BY order_id
        ) del ON del.order_id = o.id
        LEFT JOIN (
            SELECT order_id, MIN(created_at) AS first_assigned_at
            FROM emslss_tracking
            WHERE status = 'assigned_delivery'
            GROUP BY order_id
        ) assign ON assign.order_id = o.id
        WHERE $whereSql
        ORDER BY o.created_at ASC, o.id ASC
    ";
}

function emslss_ds_bg_count(mysqli $conn, string $whereSql, string $types, array $params): int
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM emslss_orders o WHERE $whereSql");
    if (!$stmt) {
        return 0;
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
}

/**
 * @return array<int,array{
 *   ems_code:string,
 *   received_date:string,
 *   received_time:string,
 *   first_delivery_date:string,
 *   first_delivery_time:string,
 *   result:string,
 *   final_status:string,
 *   last_delivery_at:string
 * }>
 */
function emslss_ds_bg_fetch_rows(mysqli $conn, string $whereSql, string $types, array $params): array
{
    $sql = emslss_ds_bg_list_sql($whereSql);
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $status = (string) ($row['status'] ?? '');
        $firstAt = (string) ($row['first_delivery_at'] ?? '');
        $firstStatus = is_string($row['first_delivery_status'] ?? null)
            ? (string) $row['first_delivery_status']
            : null;
        $lastAt = (string) ($row['last_delivery_at'] ?? '');
        $assignAt = (string) ($row['first_assigned_at'] ?? '');
        $updatedAt = (string) ($row['updated_at'] ?? '');

        $firstFill = '';
        $lastFill = '';
        $result = '';

        if ($firstAt !== '') {
            // Có mốc phát thật (delivered/failed).
            $firstFill = $firstAt;
            $lastFill = $lastAt !== '' ? $lastAt : $firstAt;
            $result = emslss_ds_bg_first_result_label($firstStatus);
        } elseif (emslss_ds_bg_is_delivery_phase($status)) {
            // Đã gán phát nhưng chưa có mốc delivered/failed.
            $firstFill = $assignAt !== '' ? $assignAt : $updatedAt;
            $lastFill = $firstFill;
            $result = 'Phát không thành công';
        }
        // new_order / pickup / transit / cancelled: không điền cột phát giả.

        $rows[] = [
            'ems_code' => (string) ($row['ems_code'] ?? ''),
            'received_date' => emslss_ds_bg_format_ts((string) ($row['created_at'] ?? ''), 'd/m/Y'),
            'received_time' => emslss_ds_bg_format_ts((string) ($row['created_at'] ?? ''), 'H:i:s'),
            'first_delivery_date' => emslss_ds_bg_format_ts($firstFill, 'd/m/Y'),
            'first_delivery_time' => emslss_ds_bg_format_ts($firstFill, 'H:i:s'),
            'result' => $result,
            'final_status' => emslss_ds_bg_final_status_label($status),
            'last_delivery_at' => emslss_ds_bg_format_ts($lastFill, 'd/m/Y H:i:s'),
        ];
    }
    return $rows;
}

function emslss_ds_bg_range_label(string $dateFrom, string $dateTo): string
{
    $fromTs = strtotime($dateFrom);
    $toTs = strtotime($dateTo);
    $fromShow = $fromTs ? date('d/m', $fromTs) : $dateFrom;
    $toShow = $toTs ? date('d/m', $toTs) : $dateTo;
    return 'Thời gian lấy dữ liệu: Từ ' . $fromShow . ' - ' . $toShow;
}

function emslss_ds_bg_xml_escape(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function emslss_ds_bg_col_letter(int $index1): string
{
    $s = '';
    while ($index1 > 0) {
        $index1--;
        $s = chr(65 + ($index1 % 26)) . $s;
        $index1 = intdiv($index1, 26);
    }
    return $s;
}

/**
 * Sinh sharedStrings.xml + sheet1.xml theo style mẫu (s=1 data, s=2 header/title).
 *
 * @param array<int,array<string,string>> $rows
 * @return array{shared:string,sheet:string}
 */
function emslss_ds_bg_build_sheet_parts(array $rows, string $title): array
{
    $headers = [
        'STT',
        'Mã bưu gửi',
        'Ngày LSS nhận dữ liệu',
        'Giờ LSS nhận dữ liệu',
        'Ngày phát lần đầu',
        'Giờ phát lần đầu',
        'Kết quả phát',
        'Trạng thái cuối',
        'Thời gian phát lần cuối (ngày giờ)',
    ];
    $textColCount = count($headers) - 1; // trừ STT (số)

    $strings = [];
    $stringIndex = static function (string $value) use (&$strings): int {
        if (!isset($strings[$value])) {
            $strings[$value] = count($strings);
        }
        return $strings[$value];
    };

    $titleIdx = $stringIndex($title);
    $headerIdx = [];
    foreach ($headers as $h) {
        $headerIdx[] = $stringIndex($h);
    }

    $dataRows = [];
    foreach ($rows as $i => $row) {
        $textCells = [
            $row['ems_code'],
            $row['received_date'],
            $row['received_time'],
            $row['first_delivery_date'],
            $row['first_delivery_time'],
            $row['result'],
            $row['final_status'],
            $row['last_delivery_at'],
        ];
        $refs = [];
        foreach ($textCells as $c) {
            $refs[] = $stringIndex($c);
        }
        $dataRows[] = [
            'stt' => $i + 1,
            'refs' => $refs,
        ];
    }

    $uniqueCount = count($strings);
    $totalCount = 1 + count($headers) + (count($rows) * $textColCount);
    $sst = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sst .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'
        . $totalCount . '" uniqueCount="' . $uniqueCount . '">';
    // Giữ thứ tự index theo lần xuất hiện (giá trị của $strings là index)
    $ordered = array_fill(0, $uniqueCount, '');
    foreach ($strings as $text => $idx) {
        $ordered[$idx] = $text;
    }
    foreach ($ordered as $text) {
        $sst .= '<si><t>' . emslss_ds_bg_xml_escape($text) . '</t></si>';
    }
    $sst .= '</sst>';

    $lastCol = emslss_ds_bg_col_letter(count($headers));
    $span = '1:' . count($headers);
    $lastRow = max(3, 3 + count($rows));
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
        . ' xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"'
        . ' mc:Ignorable="x14ac"'
        . ' xmlns:x14ac="http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac">';
    $sheet .= '<dimension ref="A1:' . $lastCol . $lastRow . '"/>';
    $sheet .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
    $sheet .= '<sheetFormatPr defaultRowHeight="15" x14ac:dyDescent="0.25"/>';
    $sheet .= '<cols>'
        . '<col min="1" max="1" width="10" customWidth="1"/>'
        . '<col min="2" max="' . count($headers) . '" width="28.7109375" customWidth="1"/>'
        . '</cols>';
    $sheet .= '<sheetData>';

    $sheet .= '<row r="1" spans="' . $span . '" ht="15.75" x14ac:dyDescent="0.25">'
        . '<c r="A1" s="2" t="s"><v>' . $titleIdx . '</v></c>'
        . '</row>';

    $sheet .= '<row r="3" spans="' . $span . '" ht="15.75" x14ac:dyDescent="0.25">';
    foreach ($headerIdx as $ci => $idx) {
        $col = emslss_ds_bg_col_letter($ci + 1);
        $sheet .= '<c r="' . $col . '3" s="2" t="s"><v>' . $idx . '</v></c>';
    }
    $sheet .= '</row>';

    foreach ($dataRows as $ri => $dataRow) {
        $r = $ri + 4;
        $sheet .= '<row r="' . $r . '" spans="' . $span . '" ht="15.75" x14ac:dyDescent="0.25">';
        $sheet .= '<c r="A' . $r . '" s="1"><v>' . (int) $dataRow['stt'] . '</v></c>';
        foreach ($dataRow['refs'] as $ci => $idx) {
            $col = emslss_ds_bg_col_letter($ci + 2);
            $sheet .= '<c r="' . $col . $r . '" s="1" t="s"><v>' . $idx . '</v></c>';
        }
        $sheet .= '</row>';
    }

    $sheet .= '</sheetData></worksheet>';

    return ['shared' => $sst, 'sheet' => $sheet];
}

/**
 * Xuất .xlsx bằng cách giữ styles/theme từ file mẫu, ghi lại sheet + sharedStrings.
 *
 * @param array<int,array<string,string>> $rows
 */
function emslss_ds_bg_send_xlsx(array $rows, string $dateFrom, string $dateTo): void
{
    $template = dirname(__DIR__) . '/docs/DS BG EMS qua LSS.xlsx';
    if (!is_readable($template) || !class_exists('ZipArchive')) {
        emslss_ds_bg_send_spreadsheetml($rows, $dateFrom, $dateTo);
        return;
    }

    $title = emslss_ds_bg_range_label($dateFrom, $dateTo);
    $parts = emslss_ds_bg_build_sheet_parts($rows, $title);

    $tmp = tempnam(sys_get_temp_dir(), 'dsbg');
    if ($tmp === false || !copy($template, $tmp)) {
        emslss_ds_bg_send_spreadsheetml($rows, $dateFrom, $dateTo);
        return;
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) {
        @unlink($tmp);
        emslss_ds_bg_send_spreadsheetml($rows, $dateFrom, $dateTo);
        return;
    }

    // Bảo đảm package OOXML khai báo sharedStrings (template sửa bằng Excel/lib có thể làm mất).
    $ctypes = $zip->getFromName('[Content_Types].xml');
    if (is_string($ctypes) && strpos($ctypes, 'sharedStrings.xml') === false) {
        $ctypes = str_replace(
            '</Types>',
            '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>',
            $ctypes
        );
        $zip->deleteName('[Content_Types].xml');
        $zip->addFromString('[Content_Types].xml', $ctypes);
    }
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if (is_string($rels) && strpos($rels, 'sharedStrings') === false) {
        $rels = str_replace(
            '</Relationships>',
            '<Relationship Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml" Id="rIdSharedStrings"/></Relationships>',
            $rels
        );
        $zip->deleteName('xl/_rels/workbook.xml.rels');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
    }

    $zip->deleteName('xl/sharedStrings.xml');
    $zip->deleteName('xl/worksheets/sheet1.xml');
    $zip->addFromString('xl/sharedStrings.xml', $parts['shared']);
    $zip->addFromString('xl/worksheets/sheet1.xml', $parts['sheet']);
    $zip->close();

    $filename = 'DS_BG_EMS_qua_LSS_' . date('Ymd_His') . '.xlsx';
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Content-Length: ' . (string) filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

/**
 * Fallback SpreadsheetML (.xls) khi không có ZipArchive / thiếu file mẫu.
 *
 * @param array<int,array<string,string>> $rows
 */
function emslss_ds_bg_send_spreadsheetml(array $rows, string $dateFrom, string $dateTo): void
{
    $filename = 'DS_BG_EMS_qua_LSS_' . date('Ymd_His') . '.xls';
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $title = emslss_ds_bg_range_label($dateFrom, $dateTo);
    $headers = [
        'STT', 'Mã bưu gửi', 'Ngày LSS nhận dữ liệu', 'Giờ LSS nhận dữ liệu',
        'Ngày phát lần đầu', 'Giờ phát lần đầu', 'Kết quả phát',
        'Trạng thái cuối', 'Thời gian phát lần cuối (ngày giờ)',
    ];

    echo "\xEF\xBB\xBF";
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    echo '<Styles>'
        . '<Style ss:ID="Title"><Font ss:FontName="Times New Roman" ss:Size="12" ss:Bold="1"/>'
        . '<Borders>'
        . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '</Borders></Style>'
        . '<Style ss:ID="Header"><Font ss:FontName="Times New Roman" ss:Size="12" ss:Bold="1"/>'
        . '<Borders>'
        . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '</Borders></Style>'
        . '<Style ss:ID="Cell"><Font ss:FontName="Times New Roman" ss:Size="12"/>'
        . '<Borders>'
        . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '</Borders></Style>'
        . '</Styles>' . "\n";
    echo '<Worksheet ss:Name="Sheet1"><Table>' . "\n";
    echo '<Column ss:Width="50"/><Column ss:Width="140"/>'
        . '<Column ss:Width="120"/><Column ss:Width="120"/>'
        . '<Column ss:Width="120"/><Column ss:Width="120"/><Column ss:Width="140"/>'
        . '<Column ss:Width="140"/><Column ss:Width="180"/>' . "\n";
    echo '<Row><Cell ss:StyleID="Title"><Data ss:Type="String">'
        . emslss_ds_bg_xml_escape($title) . '</Data></Cell></Row>' . "\n";
    echo '<Row></Row>' . "\n";
    echo '<Row>';
    foreach ($headers as $h) {
        echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . emslss_ds_bg_xml_escape($h) . '</Data></Cell>';
    }
    echo '</Row>' . "\n";
    foreach ($rows as $i => $row) {
        $cells = [
            $row['ems_code'],
            $row['received_date'],
            $row['received_time'],
            $row['first_delivery_date'],
            $row['first_delivery_time'],
            $row['result'],
            $row['final_status'],
            $row['last_delivery_at'],
        ];
        echo '<Row>';
        echo '<Cell ss:StyleID="Cell"><Data ss:Type="Number">' . ($i + 1) . '</Data></Cell>';
        foreach ($cells as $c) {
            echo '<Cell ss:StyleID="Cell"><Data ss:Type="String">' . emslss_ds_bg_xml_escape($c) . '</Data></Cell>';
        }
        echo '</Row>' . "\n";
    }
    echo '</Table></Worksheet></Workbook>';
    exit;
}
