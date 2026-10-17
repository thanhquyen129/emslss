<?php
/**
 * Import bảng kê từ .xlsx / .csv / .xls (XML Spreadsheet do chính trang này xuất ra).
 * Đọc bằng PHP thuần, không cần Composer.
 */

const BANGKE_IMPORT_MAX_BYTES = 5242880;
const BANGKE_IMPORT_MAX_ROWS = 5000;
// Chặn file .xlsx nén tỉ lệ cao (zip bomb) làm tràn RAM shared hosting
const BANGKE_IMPORT_MAX_XML_BYTES = 52428800;

function bangke_import_normalize_header(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, [
        'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
        'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
        'đ' => 'd',
    ]);
    return preg_replace('/[^a-z0-9]/', '', $s);
}

function bangke_import_header_aliases(): array
{
    return [
        'stt' => ['stt', 'sothutu', 'no', 'num', 'order', 'thutu', 'sttno'],
        'request_date' => ['ngayyeucau', 'ngay', 'date', 'bookingdate', 'ngayyeucaubookingdate'],
        'route_leg' => ['chang', 'route', 'tuyen', 'changbay'],
        'flight_no' => ['sohieuchuyenbay', 'chuyenbay', 'flight', 'flightno', 'sohieu', 'sochuyenbay'],
        'airway_bill' => ['airwaybill', 'awb', 'sovandon', 'vandon', 'soawb'],
        'vin_no' => ['sovin', 'vin', 'mavin', 'vinno'],
        'serial_no' => ['soserial', 'serial', 'serialno', 'maserial'],
        'package_count' => ['sokien', 'kien', 'pieces', 'pcs', 'soluongkien'],
        'weight' => ['khoiluong', 'khoiluongkg', 'kl', 'klkg', 'weight', 'weightkg', 'trongluong', 'trongluongkg'],
    ];
}

/**
 * Tìm dòng tiêu đề trong 15 dòng đầu (file xuất có dòng tiêu đề/ngày phía trên).
 * @return array{0:int,1:array<string,int>}|null [chỉ số dòng header, map field => cột]
 */
function bangke_import_detect_header(array $rows): ?array
{
    $aliases = bangke_import_header_aliases();
    $limit = min(count($rows), 15);
    for ($i = 0; $i < $limit; $i++) {
        $map = [];
        foreach ($rows[$i] as $col => $cell) {
            $key = bangke_import_normalize_header((string) $cell);
            if ($key === '') {
                continue;
            }
            foreach ($aliases as $field => $names) {
                if (!isset($map[$field]) && in_array($key, $names, true)) {
                    $map[$field] = $col;
                    break;
                }
            }
        }
        $hasId = isset($map['airway_bill']) || isset($map['vin_no']) || isset($map['serial_no']);
        if ($hasId && count($map) >= 3) {
            return [$i, $map];
        }
    }
    return null;
}

function bangke_import_col_index(string $ref): int
{
    $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
    $n = 0;
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }
    return $n - 1;
}

/** @return array<int,array<int,string>> */
function bangke_import_read_xlsx(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Máy chủ chưa bật PHP extension zip, chưa đọc được .xlsx. Hãy dùng file .csv.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Không mở được file .xlsx (file hỏng hoặc không đúng định dạng).');
    }

    $readEntry = static function (string $name) use ($zip): ?string {
        $stat = $zip->statName($name);
        if ($stat === false) {
            return null;
        }
        if ($stat['size'] > BANGKE_IMPORT_MAX_XML_BYTES) {
            throw new RuntimeException('File Excel quá lớn sau khi giải nén.');
        }
        $data = $zip->getFromName($name);
        return $data === false ? null : $data;
    };

    $shared = [];
    $ssXml = $readEntry('xl/sharedStrings.xml');
    if ($ssXml !== null) {
        $ss = simplexml_load_string($ssXml, 'SimpleXMLElement', LIBXML_NONET);
        if ($ss !== false) {
            foreach ($ss->si as $si) {
                $shared[] = dom_import_simplexml($si)->textContent;
            }
        }
    }

    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wbXml = $readEntry('xl/workbook.xml');
    $relsXml = $readEntry('xl/_rels/workbook.xml.rels');
    if ($wbXml !== null && $relsXml !== null) {
        $wb = simplexml_load_string($wbXml, 'SimpleXMLElement', LIBXML_NONET);
        $rels = simplexml_load_string($relsXml, 'SimpleXMLElement', LIBXML_NONET);
        if ($wb !== false && $rels !== false && isset($wb->sheets->sheet[0])) {
            $rid = (string) $wb->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
            foreach ($rels->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = ltrim((string) $rel['Target'], '/');
                    $sheetPath = strpos($target, 'xl/') === 0 ? $target : 'xl/' . $target;
                    break;
                }
            }
        }
    }

    $sheetXml = $readEntry($sheetPath);
    $zip->close();
    if ($sheetXml === null) {
        throw new RuntimeException('Không tìm thấy sheet dữ liệu trong file .xlsx.');
    }
    $sheet = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NONET);
    if ($sheet === false || !isset($sheet->sheetData)) {
        throw new RuntimeException('Sheet đầu tiên trong file .xlsx không đọc được.');
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        $next = 0;
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            $col = $ref !== '' ? bangke_import_col_index($ref) : $next;
            $next = $col + 1;
            $type = (string) $c['t'];
            if ($type === 's') {
                $val = $shared[(int) $c->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $val = isset($c->is) ? dom_import_simplexml($c->is)->textContent : '';
            } else {
                $val = (string) $c->v;
            }
            $cells[$col] = $val;
        }
        $rows[] = $cells;
        if (count($rows) > BANGKE_IMPORT_MAX_ROWS + 20) {
            break;
        }
    }
    return $rows;
}

/** @return array<int,array<int,string>> */
function bangke_import_read_spreadsheetml(string $content): array
{
    $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET);
    if ($xml === false) {
        throw new RuntimeException('File .xls không đọc được. Hãy mở bằng Excel rồi lưu lại dạng .xlsx.');
    }
    $ns = 'urn:schemas-microsoft-com:office:spreadsheet';
    $xml->registerXPathNamespace('ss', $ns);
    $rowNodes = $xml->xpath('//ss:Worksheet[1]/ss:Table/ss:Row') ?: [];

    $rows = [];
    foreach ($rowNodes as $row) {
        $cells = [];
        $col = 0;
        foreach ($row->children($ns)->Cell as $cell) {
            $attr = $cell->attributes($ns);
            if (isset($attr['Index'])) {
                $col = (int) $attr['Index'] - 1;
            }
            $data = $cell->children($ns)->Data;
            $cells[$col] = isset($data[0]) ? dom_import_simplexml($data[0])->textContent : '';
            $col += 1 + (int) ($attr['MergeAcross'] ?? 0);
        }
        $rows[] = $cells;
        if (count($rows) > BANGKE_IMPORT_MAX_ROWS + 20) {
            break;
        }
    }
    return $rows;
}

/** @return array<int,array<int,string>> */
function bangke_import_read_csv(string $content): array
{
    if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
        $content = substr($content, 3);
    }
    if (!mb_check_encoding($content, 'UTF-8')) {
        throw new RuntimeException('File CSV không phải UTF-8. Trong Excel chọn Save As → "CSV UTF-8 (Comma delimited)".');
    }

    $firstLine = strtok($content, "\n");
    $delims = [',' => 0, ';' => 0, "\t" => 0];
    foreach ($delims as $d => $_) {
        $delims[$d] = substr_count((string) $firstLine, $d);
    }
    arsort($delims);
    $delim = (string) key($delims);

    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $content);
    rewind($fh);
    $rows = [];
    while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
        $rows[] = $r;
        if (count($rows) > BANGKE_IMPORT_MAX_ROWS + 20) {
            break;
        }
    }
    fclose($fh);
    return $rows;
}

/**
 * Chuẩn hóa ngày về Y-m-d. Chấp nhận số serial Excel, dd/mm/yyyy, yyyy-mm-dd, ISO datetime.
 * Trả '' nếu ô trống, null nếu không hợp lệ.
 */
function bangke_import_parse_date(string $v): ?string
{
    $v = trim($v);
    if ($v === '') {
        return '';
    }
    if (is_numeric($v)) {
        $serial = (int) floor((float) $v);
        if ($serial < 1 || $serial > 2958465) {
            return null;
        }
        $d = new DateTime('1899-12-30');
        $d->modify('+' . $serial . ' days');
        return $d->format('Y-m-d');
    }
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m)) {
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    } elseif (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2,4})$#', $v, $m)) {
        [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($y < 100) {
            $y += 2000;
        }
    } else {
        return null;
    }
    if (!checkdate($mo, $d, $y)) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

/** Số dạng "1.234,5" / "1,234.5" / "120,5" / "120.5" → float. Trả null nếu không phải số. */
function bangke_import_parse_number(string $v): ?float
{
    $v = str_replace([' ', "\xC2\xA0"], '', trim($v));
    if ($v === '') {
        return 0.0;
    }
    $lastComma = strrpos($v, ',');
    $lastDot = strrpos($v, '.');
    if ($lastComma !== false && $lastDot !== false) {
        $v = $lastComma > $lastDot
            ? str_replace(',', '.', str_replace('.', '', $v))
            : str_replace(',', '', $v);
    } elseif ($lastComma !== false) {
        $v = str_replace(',', '.', $v);
    }
    return is_numeric($v) ? (float) $v : null;
}

function bangke_import_dup_key(string $awb, string $vin, string $serial): string
{
    return mb_strtoupper($awb . "\x1F" . $vin . "\x1F" . $serial, 'UTF-8');
}

/**
 * @param array $file phần tử của $_FILES
 * @return array{inserted:int,skipped:int,errors:array<int,string>,last_date:?string}
 */
function bangke_import_file(mysqli $conn, array $file): array
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Vui lòng chọn file để import.');
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('File vượt quá dung lượng cho phép (tối đa 5 MB).');
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException('Upload file thất bại, vui lòng thử lại.');
    }
    if ((int) $file['size'] > BANGKE_IMPORT_MAX_BYTES) {
        throw new RuntimeException('File vượt quá dung lượng cho phép (tối đa 5 MB).');
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $path = $file['tmp_name'];
    if ($ext === 'xlsx') {
        $rows = bangke_import_read_xlsx($path);
    } elseif ($ext === 'csv' || $ext === 'txt') {
        $rows = bangke_import_read_csv((string) file_get_contents($path));
    } elseif ($ext === 'xls' || $ext === 'xml') {
        $content = (string) file_get_contents($path);
        if (strncmp($content, "\xD0\xCF\x11\xE0", 4) === 0) {
            throw new RuntimeException('File .xls định dạng cũ (Excel 97-2003) chưa hỗ trợ. Hãy mở bằng Excel rồi lưu lại dạng .xlsx.');
        }
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $content = substr($content, 3);
        }
        $rows = bangke_import_read_spreadsheetml($content);
    } else {
        throw new RuntimeException('Chỉ hỗ trợ file .xlsx, .csv hoặc .xls xuất từ trang này.');
    }

    $header = bangke_import_detect_header($rows);
    if ($header === null) {
        throw new RuntimeException('Không tìm thấy dòng tiêu đề. File cần có các cột như: Ngày yêu cầu, Chặng, Số hiệu chuyến bay, Airway Bill, Số VIN, Số Serial, Số kiện, Khối lượng. Hãy tải file mẫu để đối chiếu.');
    }
    [$headerIdx, $map] = $header;

    $errors = [];
    $valid = [];

    $total = count($rows);
    for ($i = $headerIdx + 1; $i < $total; $i++) {
        $excelRow = $i + 1;
        $cells = $rows[$i];
        $get = static function (string $field) use ($cells, $map): string {
            if (!isset($map[$field])) {
                return '';
            }
            return trim((string) ($cells[$map[$field]] ?? ''));
        };

        $joined = trim(implode('', array_map('strval', $cells)));
        if ($joined === '') {
            continue;
        }
        if (strpos(bangke_import_normalize_header($joined), 'tongcong') === 0) {
            continue;
        }
        if (count($valid) + count($errors) >= BANGKE_IMPORT_MAX_ROWS) {
            throw new RuntimeException('File có quá ' . BANGKE_IMPORT_MAX_ROWS . ' dòng. Hãy chia nhỏ file rồi import từng phần.');
        }

        $row = [
            'route_leg' => mb_substr($get('route_leg'), 0, 255, 'UTF-8'),
            'flight_no' => mb_substr($get('flight_no'), 0, 100, 'UTF-8'),
            'airway_bill' => mb_substr($get('airway_bill'), 0, 120, 'UTF-8'),
            'vin_no' => mb_substr($get('vin_no'), 0, 120, 'UTF-8'),
            'serial_no' => mb_substr($get('serial_no'), 0, 120, 'UTF-8'),
        ];

        if ($row['route_leg'] === '' && $row['airway_bill'] === '' && $row['vin_no'] === '' && $row['serial_no'] === '') {
            $errors[] = "Dòng $excelRow: thiếu cả Chặng, Airway Bill, VIN và Serial.";
            continue;
        }

        // 1. Kiểm tra Ngày yêu cầu: bắt buộc nhập, không được để trống
        $rawDateStr = $get('request_date');
        if ($rawDateStr === '') {
            $errors[] = "Dòng $excelRow: Cột Ngày yêu cầu đang để trống. Vui lòng nhập ngày (định dạng dd/mm/yyyy).";
            continue;
        }
        $date = bangke_import_parse_date($rawDateStr);
        if ($date === null || $date === '') {
            $errors[] = "Dòng $excelRow: Ngày yêu cầu \"$rawDateStr\" không đúng định dạng (dùng dd/mm/yyyy).";
            continue;
        }
        $row['request_date'] = $date;

        // 2. Kiểm tra STT: bắt buộc nhập, không được để trống và phải là số nguyên dương
        $rawSttStr = $get('stt');
        if ($rawSttStr === '') {
            $errors[] = "Dòng $excelRow: Cột STT đang để trống. Vui lòng nhập số thứ tự trong file upload.";
            continue;
        }
        $rawStt = bangke_import_parse_number($rawSttStr);
        if ($rawStt === null || $rawStt <= 0) {
            $errors[] = "Dòng $excelRow: Cột STT (\"$rawSttStr\") không hợp lệ (phải là số nguyên dương).";
            continue;
        }
        $row['stt'] = (int) round($rawStt);

        // 3. Số kiện và Khối lượng
        $pkg = bangke_import_parse_number($get('package_count'));
        $weight = bangke_import_parse_number($get('weight'));
        if ($pkg === null || $pkg < 0) {
            $errors[] = "Dòng $excelRow: số kiện \"" . $get('package_count') . '" không hợp lệ.';
            continue;
        }
        if ($weight === null || $weight < 0) {
            $errors[] = "Dòng $excelRow: khối lượng \"" . $get('weight') . '" không hợp lệ.';
            continue;
        }
        $row['package_count'] = (int) round($pkg);
        $row['weight'] = round($weight, 2);

        // Dữ liệu có thể trùng Airway Bill + VIN + Serial nên vẫn cho phép upload để in bill
        $valid[] = $row;
    }

    // Nếu có bất kỳ ô trống hoặc dòng lỗi nào, dừng import để người dùng điều chỉnh file upload
    if (!empty($errors)) {
        return [
            'inserted' => 0,
            'skipped' => 0,
            'errors' => $errors,
            'last_date' => null,
        ];
    }

    $inserted = 0;
    $lastDate = null;
    if ($valid) {
        $insStmt = $conn->prepare(
            "INSERT INTO bangke_items
             (stt, request_date, route_leg, flight_no, airway_bill, vin_no, serial_no, package_count, weight)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $conn->begin_transaction();
        try {
            foreach ($valid as $r) {
                $insStmt->bind_param(
                    'issssssid',
                    $r['stt'],
                    $r['request_date'],
                    $r['route_leg'],
                    $r['flight_no'],
                    $r['airway_bill'],
                    $r['vin_no'],
                    $r['serial_no'],
                    $r['package_count'],
                    $r['weight']
                );
                $insStmt->execute();
                $inserted++;
                if ($lastDate === null || $r['request_date'] > $lastDate) {
                    $lastDate = $r['request_date'];
                }
            }
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw new RuntimeException('Lỗi ghi dữ liệu, không có dòng nào được import. Vui lòng thử lại.');
        }
        $insStmt->close();
    }

    return [
        'inserted' => $inserted,
        'skipped' => 0,
        'errors' => [],
        'last_date' => $lastDate,
    ];
}

/** File mẫu dạng XML Spreadsheet (.xls) — Excel mở được, lưu lại vẫn import được. */
function bangke_import_send_template(): void
{
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="mau_import_bang_ke.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $headers = ['STT', 'Ngày yêu cầu', 'Chặng', 'Số hiệu chuyến bay', 'Airway Bill', 'Số VIN', 'Số Serial', 'Số kiện', 'Khối lượng (kg)'];
    $widths = [50, 90, 140, 110, 120, 150, 120, 60, 90];

    echo "\xEF\xBB\xBF";
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    echo ' <Styles><Style ss:ID="H"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0C2340" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>'
        . '<Style ss:ID="T"><NumberFormat ss:Format="@"/></Style></Styles>' . "\n";
    echo ' <Worksheet ss:Name="Import"><Table>' . "\n";
    foreach ($widths as $i => $w) {
        // Cột ngày + mã để dạng text, tránh Excel tự đổi VIN/Serial dài thành số khoa học
        $style = in_array($i, [1, 4, 5, 6], true) ? ' ss:StyleID="T"' : '';
        echo '  <Column ss:Width="' . $w . '"' . $style . '/>' . "\n";
    }
    echo '  <Row>';
    foreach ($headers as $h) {
        echo '<Cell ss:StyleID="H"><Data ss:Type="String">' . bangke_h($h) . '</Data></Cell>';
    }
    echo "</Row>\n";
    $sampleDate = date('d/m/Y');
    echo "  <Row>\n";
    echo '    <Cell><Data ss:Type="Number">1</Data></Cell>' . "\n";
    echo '    <Cell ss:StyleID="T"><Data ss:Type="String">' . $sampleDate . '</Data></Cell>' . "\n";
    echo '    <Cell><Data ss:Type="String">HAN - SGN</Data></Cell>' . "\n";
    echo '    <Cell ss:StyleID="T"><Data ss:Type="String">VN210</Data></Cell>' . "\n";
    echo '    <Cell ss:StyleID="T"><Data ss:Type="String">738-12345678</Data></Cell>' . "\n";
    echo '    <Cell ss:StyleID="T"><Data ss:Type="String">VF123456789</Data></Cell>' . "\n";
    echo '    <Cell ss:StyleID="T"><Data ss:Type="String">SN987654321</Data></Cell>' . "\n";
    echo '    <Cell><Data ss:Type="Number">1</Data></Cell>' . "\n";
    echo '    <Cell><Data ss:Type="Number">15.5</Data></Cell>' . "\n";
    echo "  </Row>\n";
    echo "  </Table></Worksheet>\n</Workbook>\n";
}
