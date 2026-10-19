<?php
/**
 * Bảng kê hàng không — chức năng độc lập trên domain lsslogistics.vn
 * Không phụ thuộc auth / module EMS-LSS.
 */
@ini_set('max_input_vars', '100000');
@ini_set('memory_limit', '512M');
@set_time_limit(600);

require_once __DIR__ . '/bangke/bootstrap.php';
require_once __DIR__ . '/bangke/export.php';
require_once __DIR__ . '/bangke/import.php';

$flash = '';
$flashType = 'ok';
$today = date('Y-m-d');
$importResult = null;
$importMonth = null;

// --- Actions: print / export ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'template') {
    bangke_import_send_template();
    exit;
}

// File vượt post_max_size thì PHP bỏ trống toàn bộ $_POST/$_FILES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $flash = 'File vượt quá dung lượng máy chủ cho phép (tối đa 50 MB).';
    $flashType = 'err';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'import') {
    try {
        $importResult = bangke_import_file($conn, $_FILES['import_file'] ?? []);
        if ($importResult['last_date'] !== null) {
            $importMonth = substr($importResult['last_date'], 0, 7);
        }
        if (!empty($importResult['errors'])) {
            $flash = sprintf(
                'File upload có %d dòng có ô trống hoặc không hợp lệ. Vui lòng điều chỉnh file theo thông báo bên dưới rồi import lại.',
                count($importResult['errors'])
            );
            $flashType = 'err';
        } else {
            $flash = sprintf(
                'Import thành công: đã thêm mới %d dòng vào danh sách.',
                $importResult['inserted']
            );
            $flashType = 'ok';
        }
    } catch (RuntimeException $e) {
        $flash = $e->getMessage();
        $flashType = 'err';
    }
}
if ($action === 'print' || $action === 'print_ems' || $action === 'export') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    if (!empty($_POST['ids_csv'])) {
        $csvIds = array_filter(array_map('trim', explode(',', (string) $_POST['ids_csv'])));
        $ids = array_merge($ids, $csvIds);
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $rows = bangke_fetch_by_ids($conn, $ids);
    $sttMap = $_POST['stt'] ?? [];
    foreach ($rows as &$row) {
        $rowId = (int) ($row['id'] ?? 0);
        if (isset($row['stt']) && (int) $row['stt'] > 0) {
            $row['_stt'] = (int) $row['stt'];
        } elseif (isset($sttMap[$rowId]) && (int) $sttMap[$rowId] > 0) {
            $row['_stt'] = (int) $sttMap[$rowId];
        } else {
            $row['_stt'] = 0;
        }
    }
    unset($row);
    // Sắp xếp các dòng theo tháng và STT tăng dần để in bill đúng thứ tự STT
    usort($rows, static function ($a, $b) {
        $ymA = substr((string) ($a['request_date'] ?? ''), 0, 7);
        $ymB = substr((string) ($b['request_date'] ?? ''), 0, 7);
        if ($ymA !== $ymB) {
            return $ymA <=> $ymB;
        }
        $sttA = (int) (!empty($a['_stt']) ? $a['_stt'] : (!empty($a['stt']) ? $a['stt'] : 999999));
        $sttB = (int) (!empty($b['_stt']) ? $b['_stt'] : (!empty($b['stt']) ? $b['stt'] : 999999));
        if ($sttA !== $sttB) {
            return $sttA <=> $sttB;
        }
        return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
    });
    if (!$rows) {
        $flash = 'Vui lòng chọn ít nhất một dòng trong danh sách.';
        $flashType = 'err';
    } elseif ($action === 'print') {
        bangke_render_print($rows);
        exit;
    } elseif ($action === 'print_ems') {
        bangke_render_ems_print($rows);
        exit;
    } else {
        bangke_export_excel($rows, 'bang_ke_lss');
        exit;
    }
}

// --- Đọc các tham số bộ lọc tìm kiếm ---
$filterDateFrom = trim((string) ($_GET['date_from'] ?? ''));
$filterDateTo = trim((string) ($_GET['date_to'] ?? ''));
$filterRoute = trim((string) ($_GET['route'] ?? ''));
$filterFlight = trim((string) ($_GET['flight'] ?? ''));
$filterAwb = trim((string) ($_GET['awb'] ?? ''));
$filterVinSerial = trim((string) ($_GET['vin_serial'] ?? ''));

$months = bangke_fetch_months($conn);
$currentMonth = date('Y-m');
$reqMonth = trim((string) ($_GET['m'] ?? ''));

// Kiểm tra chế độ sửa bản ghi sớm để xác định tháng nếu chưa có m
$editId = (int) ($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    $editRow = bangke_fetch_by_id($conn, $editId);
    if (!$editRow) {
        $flash = 'Không tìm thấy bản ghi #' . $editId . ' để sửa.';
        $flashType = 'err';
    }
}

if ($importMonth !== null) {
    $activeMonth = $importMonth;
} elseif ($reqMonth === 'all') {
    $activeMonth = 'all';
} elseif (preg_match('/^\d{4}-\d{2}$/', $reqMonth)) {
    $activeMonth = $reqMonth;
} elseif ($editRow && $reqMonth === '') {
    $activeMonth = substr($editRow['request_date'], 0, 7);
} else {
    $activeMonth = $months[0] ?? $currentMonth;
}

if (!$months) {
    $months = [$currentMonth];
}

$isFilterActive = (
    $activeMonth === 'all'
    || $filterDateFrom !== ''
    || $filterDateTo !== ''
    || $filterRoute !== ''
    || $filterFlight !== ''
    || $filterAwb !== ''
    || $filterVinSerial !== ''
);

// Mảng tham số query để bảo lưu bộ lọc qua các link, form và redirect
$filterParams = [];
if ($activeMonth !== '') {
    $filterParams['m'] = $activeMonth;
}
if ($filterDateFrom !== '') {
    $filterParams['date_from'] = $filterDateFrom;
}
if ($filterDateTo !== '') {
    $filterParams['date_to'] = $filterDateTo;
}
if ($filterRoute !== '') {
    $filterParams['route'] = $filterRoute;
}
if ($filterFlight !== '') {
    $filterParams['flight'] = $filterFlight;
}
if ($filterAwb !== '') {
    $filterParams['awb'] = $filterAwb;
}
if ($filterVinSerial !== '') {
    $filterParams['vin_serial'] = $filterVinSerial;
}

$pageQuery = http_build_query($filterParams);
$pageUrl = 'bangke.php' . ($pageQuery !== '' ? '?' . $pageQuery : '');

// --- Delete single ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $delId = (int) ($_POST['id'] ?? 0);
    $delCount = 0;
    if ($delId > 0) {
        $delCount = bangke_delete_by_ids($conn, [$delId]);
    }
    $redirParams = $filterParams;
    $redirParams['deleted'] = ($delCount > 0 ? '1' : '0');
    header('Location: bangke.php?' . http_build_query($redirParams));
    exit;
}

// --- Delete selected ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_selected') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    if (!empty($_POST['ids_csv'])) {
        $csvIds = array_filter(array_map('trim', explode(',', (string) $_POST['ids_csv'])));
        $ids = array_merge($ids, $csvIds);
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $delCount = bangke_delete_by_ids($conn, $ids);
    $redirParams = $filterParams;
    $redirParams['deleted'] = (string) $delCount;
    header('Location: bangke.php?' . http_build_query($redirParams));
    exit;
}

// --- Save / Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['save', 'update'], true)) {
    $isUpdate = ($action === 'update');
    $id = (int) ($_POST['id'] ?? 0);
    $stt = (int) ($_POST['stt'] ?? 0);
    $request_date = trim((string) ($_POST['request_date'] ?? ''));
    $route_leg = trim((string) ($_POST['route_leg'] ?? ''));
    $flight_no = trim((string) ($_POST['flight_no'] ?? ''));
    $airway_bill = trim((string) ($_POST['airway_bill'] ?? ''));
    $vin_no = trim((string) ($_POST['vin_no'] ?? ''));
    $serial_no = trim((string) ($_POST['serial_no'] ?? ''));
    $package_count = (int) ($_POST['package_count'] ?? 0);
    $weight = (float) str_replace(',', '.', (string) ($_POST['weight'] ?? '0'));

    if ($request_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $request_date)) {
        $request_date = $today;
    }

    if (!$isUpdate && $stt <= 0) {
        $ym = substr($request_date, 0, 7);
        $range = bangke_month_range($ym);
        if ($range) {
            $stmtMax = $conn->prepare("SELECT COALESCE(MAX(stt), 0) FROM bangke_items WHERE request_date >= ? AND request_date < ?");
            $stmtMax->bind_param('ss', $range[0], $range[1]);
            $stmtMax->execute();
            $stt = (int) ($stmtMax->get_result()->fetch_row()[0] ?? 0) + 1;
            $stmtMax->close();
        } else {
            $stt = 1;
        }
    }

    if ($isUpdate && $id <= 0) {
        $flash = 'Bản ghi cần sửa không hợp lệ.';
        $flashType = 'err';
    } elseif ($route_leg === '' && $airway_bill === '' && $vin_no === '' && $serial_no === '') {
        $flash = 'Nhập ít nhất Chặng, Airway Bill, VIN hoặc Serial.';
        $flashType = 'err';
    } elseif ($isUpdate) {
        $stmt = $conn->prepare(
            "UPDATE bangke_items
             SET stt = ?, request_date = ?, route_leg = ?, flight_no = ?, airway_bill = ?, vin_no = ?, serial_no = ?, package_count = ?, weight = ?
             WHERE id = ?"
        );
        $stmt->bind_param(
            'issssssidi',
            $stt,
            $request_date,
            $route_leg,
            $flight_no,
            $airway_bill,
            $vin_no,
            $serial_no,
            $package_count,
            $weight,
            $id
        );
        if ($stmt->execute()) {
            $stmt->close();
            $redirParams = $filterParams;
            $redirParams['updated'] = '1';
            header('Location: bangke.php?' . http_build_query($redirParams));
            exit;
        }
        $flash = 'Lỗi cập nhật dữ liệu. Vui lòng thử lại.';
        $flashType = 'err';
        $stmt->close();
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO bangke_items
             (stt, request_date, route_leg, flight_no, airway_bill, vin_no, serial_no, package_count, weight)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'issssssid',
            $stt,
            $request_date,
            $route_leg,
            $flight_no,
            $airway_bill,
            $vin_no,
            $serial_no,
            $package_count,
            $weight
        );
        if ($stmt->execute()) {
            $stmt->close();
            $ym = substr($request_date, 0, 7);
            header('Location: bangke.php?m=' . urlencode($ym) . '&saved=1');
            exit;
        }
        $flash = 'Lỗi lưu dữ liệu. Vui lòng thử lại.';
        $flashType = 'err';
        $stmt->close();
    }
}

if (isset($_GET['saved'])) {
    $flash = 'Đã lưu bản ghi thành công.';
    $flashType = 'ok';
} elseif (isset($_GET['updated'])) {
    $flash = 'Đã cập nhật bản ghi thành công.';
    $flashType = 'ok';
} elseif (isset($_GET['deleted'])) {
    $n = (int) $_GET['deleted'];
    if ($n > 0) {
        $flash = $n === 1 ? 'Đã xóa bản ghi thành công.' : sprintf('Đã xóa %d bản ghi thành công.', $n);
        $flashType = 'ok';
    } else {
        $flash = 'Không có bản ghi nào bị xóa.';
        $flashType = 'err';
    }
}

$list = bangke_fetch_items($conn, [
    'month' => $activeMonth,
    'date_from' => $filterDateFrom,
    'date_to' => $filterDateTo,
    'route' => $filterRoute,
    'flight' => $filterFlight,
    'awb' => $filterAwb,
    'vin_serial' => $filterVinSerial,
]);
$sumPkg = 0;
$sumW = 0.0;
foreach ($list as $r) {
    $sumPkg += (int) $r['package_count'];
    $sumW += (float) $r['weight'];
}

$formDefaults = [
    'stt' => $_POST['stt'] ?? ($editRow['stt'] ?? ''),
    'request_date' => $_POST['request_date'] ?? ($editRow['request_date'] ?? $today),
    'route_leg' => $_POST['route_leg'] ?? ($editRow['route_leg'] ?? ''),
    'flight_no' => $_POST['flight_no'] ?? ($editRow['flight_no'] ?? ''),
    'airway_bill' => $_POST['airway_bill'] ?? ($editRow['airway_bill'] ?? ''),
    'vin_no' => $_POST['vin_no'] ?? ($editRow['vin_no'] ?? ''),
    'serial_no' => $_POST['serial_no'] ?? ($editRow['serial_no'] ?? ''),
    'package_count' => $_POST['package_count'] ?? ($editRow['package_count'] ?? ''),
    'weight' => $_POST['weight'] ?? ($editRow['weight'] ?? ''),
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bảng kê — LSS Logistics</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
:root {
  --navy: #0c2340;
  --navy-2: #163a5f;
  --amber: #f0a202;
  --amber-deep: #d98900;
  --ink: #142033;
  --muted: #5d7086;
  --line: #d5e0ec;
  --surface: #ffffff;
  --bg0: #e8eef5;
  --bg1: #f4f7fb;
  --ok: #0f7a4c;
  --ok-bg: #e7f7ef;
  --err: #b42318;
  --err-bg: #fdecea;
  --shadow: 0 18px 40px rgba(12, 35, 64, .10);
  --radius: 16px;
}
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body {
  font-family: "Outfit", system-ui, sans-serif;
  color: var(--ink);
  background:
    radial-gradient(1200px 500px at 10% -10%, rgba(240,162,2,.18), transparent 55%),
    radial-gradient(900px 420px at 100% 0%, rgba(22,58,95,.16), transparent 50%),
    linear-gradient(180deg, var(--bg1), var(--bg0));
  min-height: 100vh;
}
.mono { font-family: "IBM Plex Mono", ui-monospace, monospace; }
.wrap { width: min(1180px, calc(100% - 28px)); margin: 0 auto; padding: 28px 0 48px; }

.hero {
  display: flex; align-items: flex-end; justify-content: space-between; gap: 16px;
  margin-bottom: 22px;
}
.hero h1 {
  margin: 0; font-size: clamp(1.6rem, 2.5vw, 2.2rem); font-weight: 800;
  letter-spacing: -.02em; color: var(--navy);
}
.hero p { margin: 6px 0 0; color: var(--muted); font-size: .98rem; }
.badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--navy); color: #fff;
  padding: 10px 14px; border-radius: 999px; font-size: .85rem; font-weight: 600;
  box-shadow: var(--shadow);
}
.badge i {
  width: 8px; height: 8px; border-radius: 50%; background: var(--amber);
  box-shadow: 0 0 0 4px rgba(240,162,2,.25);
}

.panel {
  background: var(--surface);
  border: 1px solid rgba(12,35,64,.06);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
}
.panel + .panel { margin-top: 20px; }
.panel-hd {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 16px 20px;
  background: linear-gradient(90deg, var(--navy), var(--navy-2));
  color: #fff;
}
.panel-hd h2 { margin: 0; font-size: 1.05rem; font-weight: 700; }
.panel-hd span { opacity: .78; font-size: .86rem; }
.panel-bd { padding: 20px; }

.flash {
  margin-bottom: 16px; padding: 12px 14px; border-radius: 12px;
  font-weight: 600; font-size: .95rem;
}
.flash.ok { background: var(--ok-bg); color: var(--ok); }
.flash.err { background: var(--err-bg); color: var(--err); }
.flash ul {
  margin: 8px 0 0; padding-left: 20px;
  font-weight: 500; font-size: .88rem; color: var(--err);
  max-height: 180px; overflow: auto;
}

.import-row {
  display: flex; flex-wrap: wrap; align-items: center; gap: 12px;
}
.file-pick {
  flex: 1; min-width: 260px;
  display: flex; align-items: center; gap: 10px;
  border: 1.5px dashed #9fb4cc; border-radius: 10px;
  padding: 8px 10px; background: #f7fafd; cursor: pointer;
  transition: border-color .15s, background .15s;
}
.file-pick:hover { border-color: #3d7ab5; background: #fff; }
.file-pick input[type="file"] { display: none; }
.file-pick .pick-btn {
  background: var(--navy); color: #fff; border-radius: 8px;
  padding: 7px 12px; font-weight: 700; font-size: .88rem; white-space: nowrap;
}
.file-pick .pick-name {
  color: var(--muted); font-size: .92rem;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.import-hint { margin: 10px 0 0; color: var(--muted); font-size: .86rem; }
.import-hint a { color: #1f5f99; font-weight: 600; }

.form-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}
.field { display: flex; flex-direction: column; gap: 6px; }
.field.span2 { grid-column: span 2; }
.field label {
  font-size: .78rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .04em; color: var(--muted);
}
.field input, .field select {
  width: 100%;
  border: 1.5px solid var(--line);
  border-radius: 10px;
  padding: 11px 12px;
  font: inherit;
  font-size: .98rem;
  color: var(--ink);
  background: #fbfcfe;
  transition: border-color .15s, box-shadow .15s, background .15s;
}
.field input:focus, .field select:focus {
  outline: none;
  border-color: #3d7ab5;
  background: #fff;
  box-shadow: 0 0 0 4px rgba(61,122,181,.15);
}
.form-actions {
  display: flex; justify-content: flex-end; gap: 10px;
  margin-top: 16px; padding-top: 4px;
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}
.filter-actions {
  display: flex; justify-content: flex-end; align-items: center; gap: 10px;
  margin-top: 16px; padding-top: 4px;
}
.filter-tags {
  display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
  margin-top: 16px; padding-top: 14px;
  border-top: 1px dashed var(--line);
  font-size: .88rem;
}
.filter-tag-label {
  color: var(--muted); font-weight: 700; text-transform: uppercase;
  font-size: .75rem; letter-spacing: .04em;
}
.filter-tag {
  display: inline-flex; align-items: center; gap: 4px;
  background: #eef3f9; border: 1px solid var(--line); border-radius: 8px;
  padding: 4px 10px; color: var(--navy); font-size: .84rem;
}
.filter-tag b { color: var(--ink); }
.filter-tag-clear {
  color: var(--err); text-decoration: none; font-weight: 700;
  font-size: .84rem; margin-left: 6px; padding: 4px 8px;
  border-radius: 6px; background: var(--err-bg);
  transition: background .12s;
}
.filter-tag-clear:hover { background: #fad7d4; }

.btn {
  appearance: none; border: 0; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  border-radius: 10px; padding: 11px 16px;
  font: inherit; font-weight: 700; font-size: .95rem;
  transition: transform .12s, background .15s, box-shadow .15s, opacity .15s;
}
.btn:hover { transform: translateY(-1px); }
.btn:active { transform: translateY(0); }
.btn:disabled { opacity: .45; cursor: not-allowed; transform: none; }
.btn-primary { background: var(--amber); color: var(--navy); box-shadow: 0 8px 18px rgba(240,162,2,.28); }
.btn-primary:hover { background: var(--amber-deep); }
.btn-navy { background: var(--navy); color: #fff; }
.btn-navy:hover { background: var(--navy-2); }
.btn-ghost {
  background: #eef3f9; color: var(--navy);
  border: 1px solid var(--line);
}
.btn-ghost:hover { background: #e4ecf6; }
.btn-danger { background: var(--err); color: #fff; }
.btn-danger:hover { background: #961d14; }

.btn-action {
  appearance: none; border: 1px solid transparent; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center;
  padding: 4px 8px; border-radius: 6px;
  font-family: inherit; font-size: .80rem; font-weight: 600;
  text-decoration: none; line-height: 1.25;
  transition: background .12s, border-color .12s, color .12s, transform .12s;
}
.btn-action:hover { transform: translateY(-1px); }
.btn-action:active { transform: translateY(0); }
.btn-action-edit {
  background: #eaf2fd; color: #1652a2; border-color: #c5dbf8; margin-right: 4px;
}
.btn-action-edit:hover { background: #d3e5fe; color: #0d3e80; border-color: #9ec5f6; }
.btn-action-del {
  background: #fdecea; color: var(--err); border-color: #f7c3be;
}
.btn-action-del:hover { background: #fadad7; color: #961d14; border-color: #f2a69f; }
tbody tr.editing { background: #e7f0fd !important; outline: 2px solid #2b7fff; }
td.action-col, th.action-col { text-align: center; white-space: nowrap; width: 125px; }

.toolbar {
  display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
  margin-bottom: 14px;
}
.toolbar .grow { flex: 1; color: var(--muted); font-size: .92rem; }
.stats {
  display: flex; gap: 10px; flex-wrap: wrap;
}
.stat {
  background: #f3f7fc; border: 1px solid var(--line);
  border-radius: 10px; padding: 8px 12px; font-size: .88rem;
}
.stat b { color: var(--navy); }

.table-wrap {
  border: 1px solid var(--line);
  border-radius: 12px 12px 0 0;
  overflow: auto;
  max-height: min(58vh, 620px);
}
table {
  width: 100%; border-collapse: collapse; min-width: 920px;
}
thead th {
  position: sticky; top: 0; z-index: 2;
  background: #102a46; color: #fff;
  font-size: .78rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .03em;
  padding: 12px 10px; text-align: left; white-space: nowrap;
}
tbody td {
  padding: 11px 10px;
  border-bottom: 1px solid #e8eef5;
  font-size: .94rem;
  vertical-align: middle;
}
tbody tr:nth-child(even) { background: #f8fafc; }
tbody tr:hover { background: #fff7e8; }
tbody tr.selected { background: #ffe8b8 !important; }
td.num, th.num { text-align: right; }
td.check, th.check { width: 42px; text-align: center; }
input[type="checkbox"] {
  width: 17px; height: 17px; accent-color: var(--navy); cursor: pointer;
}
.empty {
  padding: 36px 16px; text-align: center; color: var(--muted);
}

/* Sheet tabs kiểu Excel */
.sheet-bar {
  display: flex; align-items: stretch; gap: 0;
  background: #d9e3ef;
  border: 1px solid var(--line);
  border-top: 0;
  border-radius: 0 0 12px 12px;
  overflow-x: auto;
  padding: 0 6px;
  min-height: 38px;
}
.sheet-tab {
  position: relative;
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 16px 8px;
  margin: 4px 2px 0;
  text-decoration: none;
  color: var(--muted);
  font-weight: 600; font-size: .88rem;
  background: #c9d6e6;
  border-radius: 8px 8px 0 0;
  border: 1px solid transparent;
  border-bottom: 0;
  white-space: nowrap;
}
.sheet-tab:hover { background: #bfcede; color: var(--navy); }
.sheet-tab.active {
  background: #fff;
  color: var(--navy);
  border-color: var(--line);
  box-shadow: 0 -1px 0 #fff;
  z-index: 1;
}
.sheet-tab.active::after {
  content: "";
  position: absolute; left: 0; right: 0; bottom: -1px; height: 2px;
  background: var(--amber);
}

@media (max-width: 900px) {
  .form-grid, .filter-grid { grid-template-columns: repeat(2, 1fr); }
  .field.span2 { grid-column: span 2; }
  .hero { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 560px) {
  .form-grid, .filter-grid { grid-template-columns: 1fr; }
  .field.span2 { grid-column: auto; }
  .form-actions, .filter-actions { justify-content: stretch; }
  .form-actions .btn, .filter-actions .btn { width: 100%; }
}

/* Modal xác nhận xóa */
.modal-backdrop {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(12, 35, 64, 0.55);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 16px;
  animation: modalFadeIn 0.18s ease-out;
}
@keyframes modalFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
.modal-box {
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 24px 60px rgba(12, 35, 64, 0.25);
  width: 100%;
  max-width: 480px;
  padding: 24px;
  border: 1px solid var(--line);
  animation: modalZoomIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes modalZoomIn {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
.modal-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  margin-bottom: 14px;
}
.modal-icon-danger {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--err-bg);
  color: var(--err);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.modal-title {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--navy);
  line-height: 1.3;
}
.modal-subtitle {
  margin: 4px 0 0;
  font-size: 0.86rem;
  color: var(--muted);
}
.modal-body {
  font-size: 0.95rem;
  color: var(--ink);
  line-height: 1.5;
  background: #f8fafc;
  padding: 14px 16px;
  border-radius: 10px;
  border: 1px solid var(--line);
  margin-bottom: 20px;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
</head>
<body>
<div class="wrap">
  <header class="hero">
    <div>
      <h1>Bảng kê hàng không</h1>
      <p>Nhập liệu · In bill · Xuất Excel — LSS Logistics</p>
    </div>
    <div class="badge"><i></i> lsslogistics.vn</div>
  </header>

  <?php if ($flash !== ''): ?>
    <div class="flash <?= $flashType === 'ok' ? 'ok' : 'err' ?>">
      <?= bangke_h($flash) ?>
      <?php if ($importResult && $importResult['errors']): ?>
        <ul>
          <?php foreach (array_slice($importResult['errors'], 0, 50) as $e): ?>
            <li><?= bangke_h($e) ?></li>
          <?php endforeach; ?>
          <?php if (count($importResult['errors']) > 50): ?>
            <li>… và <?= count($importResult['errors']) - 50 ?> dòng lỗi khác.</li>
          <?php endif; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <section class="panel" id="nhap-lieu">
    <div class="panel-hd">
      <h2><?= $editRow ? ('Sửa bản ghi #' . (int) $editRow['id']) : 'Nhập liệu' ?></h2>
      <span><?= $editRow ? 'Chỉnh sửa thông tin rồi bấm Cập nhật' : 'Ngày mặc định = hôm nay' ?></span>
    </div>
    <div class="panel-bd">
      <form method="post" autocomplete="off" action="<?= bangke_h($pageUrl) ?>">
        <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'save' ?>">
        <?php if ($editRow): ?>
          <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
        <?php endif; ?>
        <div class="form-grid">
          <div class="field">
            <label for="stt">STT</label>
            <input class="mono" type="number" id="stt" name="stt" min="1" step="1" placeholder="Tự động"
                   value="<?= $formDefaults['stt'] ? (int) $formDefaults['stt'] : '' ?>">
          </div>
          <div class="field">
            <label for="request_date">Ngày yêu cầu</label>
            <input type="date" id="request_date" name="request_date" required
                   value="<?= bangke_h($formDefaults['request_date']) ?>">
          </div>
          <div class="field span2">
            <label for="route_leg">Chặng</label>
            <input type="text" id="route_leg" name="route_leg" placeholder="VD: HAN → SGN"
                   value="<?= bangke_h($formDefaults['route_leg']) ?>">
          </div>
          <div class="field">
            <label for="flight_no">Số hiệu chuyến bay</label>
            <input class="mono" type="text" id="flight_no" name="flight_no" placeholder="VD: VN210"
                   value="<?= bangke_h($formDefaults['flight_no']) ?>">
          </div>
          <div class="field">
            <label for="airway_bill">Airway Bill</label>
            <input class="mono" type="text" id="airway_bill" name="airway_bill" placeholder="AWB"
                   value="<?= bangke_h($formDefaults['airway_bill']) ?>">
          </div>
          <div class="field">
            <label for="vin_no">Số VIN</label>
            <input class="mono" type="text" id="vin_no" name="vin_no"
                   value="<?= bangke_h($formDefaults['vin_no']) ?>">
          </div>
          <div class="field">
            <label for="serial_no">Số Serial</label>
            <input class="mono" type="text" id="serial_no" name="serial_no"
                   value="<?= bangke_h($formDefaults['serial_no']) ?>">
          </div>
          <div class="field">
            <label for="package_count">Số kiện</label>
            <input type="number" id="package_count" name="package_count" min="0" step="1"
                   value="<?= bangke_h((string) $formDefaults['package_count']) ?>">
          </div>
          <div class="field">
            <label for="weight">Khối lượng (kg)</label>
            <input type="number" id="weight" name="weight" min="0" step="0.01"
                   value="<?= bangke_h((string) $formDefaults['weight']) ?>">
          </div>
        </div>
        <div class="form-actions">
          <?php if ($editRow): ?>
            <a href="<?= bangke_h($pageUrl) ?>" class="btn btn-ghost">Hủy sửa</a>
            <button type="submit" class="btn btn-primary">Cập nhật bản ghi</button>
          <?php else: ?>
            <button type="reset" class="btn btn-ghost">Xóa form</button>
            <button type="submit" class="btn btn-primary">Lưu bản ghi</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="panel-hd">
      <h2>Import từ Excel</h2>
      <span>.xlsx · .csv · .xls (xuất từ trang này)</span>
    </div>
    <div class="panel-bd">
      <form method="post" enctype="multipart/form-data" id="importForm">
        <input type="hidden" name="action" value="import">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= BANGKE_IMPORT_MAX_BYTES ?>">
        <div class="import-row">
          <label class="file-pick">
            <input type="file" name="import_file" id="importFile" accept=".xlsx,.csv,.xls" required>
            <span class="pick-btn">Chọn file</span>
            <span class="pick-name" id="importFileName">Chưa chọn file</span>
          </label>
          <a class="btn btn-ghost" href="bangke.php?action=template">Tải file mẫu</a>
          <button type="submit" class="btn btn-primary" id="btnImport">Import</button>
        </div>
        <p class="import-hint">
          Dòng đầu là tiêu đề cột: STT, Ngày yêu cầu, Chặng, Số hiệu chuyến bay, Airway Bill, Số VIN, Số Serial, Số kiện, Khối lượng.<br>
          <strong>Lưu ý:</strong> Cột <b>STT</b> và <b>Ngày yêu cầu</b> (định dạng dd/mm/yyyy) là bắt buộc, không được để trống (hệ thống sẽ hiện thông báo để điều chỉnh file upload nếu có ô trống). Hệ thống cho phép upload các dòng trùng Airway Bill, VIN, Serial để in bill.
        </p>
      </form>
    </div>
  </section>

  <section class="panel" id="bo-loc">
    <div class="panel-hd">
      <h2>Bộ lọc &amp; Tìm kiếm</h2>
      <span>Lọc theo ngày, chặng bay, chuyến bay, số bill, số VIN / Serial...</span>
    </div>
    <div class="panel-bd">
      <form method="get" action="bangke.php" id="filterForm">
        <div class="filter-grid">
          <div class="field">
            <label for="f_m">Tháng</label>
            <select id="f_m" name="m">
              <option value="all" <?= $activeMonth === 'all' ? 'selected' : '' ?>>Tất cả các tháng</option>
              <?php foreach ($months as $ym): ?>
                <option value="<?= bangke_h($ym) ?>" <?= $activeMonth === $ym ? 'selected' : '' ?>>
                  Tháng <?= bangke_h(bangke_month_label($ym)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="f_date_from">Từ ngày</label>
            <input type="date" id="f_date_from" name="date_from" value="<?= bangke_h($filterDateFrom) ?>">
          </div>
          <div class="field">
            <label for="f_date_to">Đến ngày</label>
            <input type="date" id="f_date_to" name="date_to" value="<?= bangke_h($filterDateTo) ?>">
          </div>
          <div class="field">
            <label for="f_route">Chặng bay</label>
            <input type="text" id="f_route" name="route" placeholder="VD: HAN, SGN, BMV..." value="<?= bangke_h($filterRoute) ?>">
          </div>
          <div class="field">
            <label for="f_flight">Số hiệu chuyến bay</label>
            <input class="mono" type="text" id="f_flight" name="flight" placeholder="VD: VN1603..." value="<?= bangke_h($filterFlight) ?>">
          </div>
          <div class="field">
            <label for="f_awb">Airway Bill (Số bill)</label>
            <input class="mono" type="text" id="f_awb" name="awb" placeholder="VD: 738-..." value="<?= bangke_h($filterAwb) ?>">
          </div>
          <div class="field span2">
            <label for="f_vin_serial">Số VIN / Serial</label>
            <input class="mono" type="text" id="f_vin_serial" name="vin_serial" placeholder="Nhập số VIN hoặc Serial..." value="<?= bangke_h($filterVinSerial) ?>">
          </div>
        </div>
        <div class="filter-actions">
          <?php if ($isFilterActive): ?>
            <a href="bangke.php?m=<?= urlencode($activeMonth === 'all' ? ($months[0] ?? date('Y-m')) : $activeMonth) ?>" class="btn btn-ghost" title="Xóa các điều kiện lọc và quay về tháng mặc định">
              ✕ Đặt lại
            </a>
          <?php endif; ?>
          <button type="submit" class="btn btn-navy">🔍 Tìm kiếm</button>
        </div>
      </form>

      <?php if ($isFilterActive): ?>
        <div class="filter-tags">
          <span class="filter-tag-label">Đang lọc:</span>
          <?php if ($activeMonth === 'all'): ?>
            <span class="filter-tag">Tháng: <b>Tất cả các tháng</b></span>
          <?php elseif ($activeMonth !== ''): ?>
            <span class="filter-tag">Tháng: <b><?= bangke_h(bangke_month_label($activeMonth)) ?></b></span>
          <?php endif; ?>
          <?php if ($filterDateFrom !== ''): ?>
            <span class="filter-tag">Từ ngày: <b><?= bangke_h(date('d/m/Y', strtotime($filterDateFrom))) ?></b></span>
          <?php endif; ?>
          <?php if ($filterDateTo !== ''): ?>
            <span class="filter-tag">Đến ngày: <b><?= bangke_h(date('d/m/Y', strtotime($filterDateTo))) ?></b></span>
          <?php endif; ?>
          <?php if ($filterRoute !== ''): ?>
            <span class="filter-tag">Chặng: <b><?= bangke_h($filterRoute) ?></b></span>
          <?php endif; ?>
          <?php if ($filterFlight !== ''): ?>
            <span class="filter-tag">Chuyến: <b><?= bangke_h($filterFlight) ?></b></span>
          <?php endif; ?>
          <?php if ($filterAwb !== ''): ?>
            <span class="filter-tag">Số bill: <b><?= bangke_h($filterAwb) ?></b></span>
          <?php endif; ?>
          <?php if ($filterVinSerial !== ''): ?>
            <span class="filter-tag">VIN/Serial: <b><?= bangke_h($filterVinSerial) ?></b></span>
          <?php endif; ?>
          <a href="bangke.php?m=<?= urlencode($activeMonth === 'all' ? ($months[0] ?? date('Y-m')) : $activeMonth) ?>" class="filter-tag-clear" title="Xóa toàn bộ bộ lọc">Xóa lọc</a>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="panel">
    <div class="panel-hd">
      <h2><?= $isFilterActive ? 'Kết quả tìm kiếm' : ('Danh sách tháng ' . bangke_h(bangke_month_label($activeMonth))) ?></h2>
      <span><?= count($list) ?> dòng</span>
    </div>
    <div class="panel-bd">
      <form method="post" id="listForm" action="<?= bangke_h($pageUrl) ?>">
        <input type="hidden" name="ids_csv" id="idsCsv" value="">
        <div class="toolbar">
          <button type="button" class="btn btn-ghost" id="btnSelectAll">Chọn tất cả</button>
          <button type="submit" name="action" value="print" class="btn btn-navy" id="btnPrint">In bill</button>
          <button type="submit" name="action" value="print_ems" class="btn btn-navy" id="btnEms" style="background:#1a5fa8">In bill EMS</button>
          <button type="submit" name="action" value="export" class="btn btn-primary" id="btnExport">Xuất Excel</button>
          <button type="button" class="btn btn-danger" id="btnDeleteSelected">Xóa đã chọn</button>
          <div class="grow" id="selectedHint">Chưa chọn dòng nào</div>
          <div class="stats">
            <div class="stat">Dòng: <b><?= count($list) ?></b></div>
            <div class="stat">Kiện: <b><?= (int) $sumPkg ?></b></div>
            <div class="stat">KL: <b><?= bangke_h(rtrim(rtrim(number_format($sumW, 2, '.', ''), '0'), '.') ?: '0') ?></b> kg</div>
          </div>
        </div>

        <div class="table-wrap">
          <?php if (!$list): ?>
            <div class="empty">
              <?= $isFilterActive ? 'Không tìm thấy kết quả nào phù hợp với bộ lọc tìm kiếm.' : 'Chưa có dữ liệu trong tháng này. Hãy nhập form phía trên rồi bấm Lưu.' ?>
            </div>
          <?php else: ?>
            <table>
              <thead>
                <tr>
                  <th class="check"><input type="checkbox" id="checkAll" title="Chọn tất cả"></th>
                  <th>STT</th>
                  <th>Ngày yêu cầu</th>
                  <th>Chặng</th>
                  <th>Chuyến bay</th>
                  <th>Airway Bill</th>
                  <th>Số VIN</th>
                  <th>Số Serial</th>
                  <th class="num">Số kiện</th>
                  <th class="num">KL (kg)</th>
                  <th class="action-col">Thao tác</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($list as $i => $r): ?>
                <?php
                $rowEditUrl = 'bangke.php?' . http_build_query(array_merge($filterParams, ['edit' => (int) $r['id']])) . '#nhap-lieu';
                ?>
                <tr class="<?= ($editId === (int) $r['id']) ? 'editing' : '' ?>">
                  <td class="check">
                    <input type="checkbox" class="row-check" name="ids[]" value="<?= (int) $r['id'] ?>">
                  </td>
                  <td class="mono"><strong><?= (int) (!empty($r['stt']) ? $r['stt'] : ($i + 1)) ?></strong></td>
                  <td><?= bangke_h(date('d/m/Y', strtotime($r['request_date']))) ?></td>
                  <td><?= bangke_h($r['route_leg']) ?></td>
                  <td class="mono"><?= bangke_h($r['flight_no']) ?></td>
                  <td class="mono"><?= bangke_h($r['airway_bill']) ?></td>
                  <td class="mono"><?= bangke_h($r['vin_no']) ?></td>
                  <td class="mono"><?= bangke_h($r['serial_no']) ?></td>
                  <td class="num"><?= (int) $r['package_count'] ?></td>
                  <td class="num"><?= bangke_h(rtrim(rtrim(number_format((float) $r['weight'], 2, '.', ''), '0'), '.') ?: '0') ?></td>
                  <td class="action-col">
                    <a class="btn-action btn-action-edit"
                       href="<?= bangke_h($rowEditUrl) ?>"
                       title="Sửa bản ghi #<?= (int) $r['id'] ?>">Sửa</a>
                    <button type="button" class="btn-action btn-action-del"
                            onclick="confirmDelete(<?= (int) $r['id'] ?>)"
                            title="Xóa bản ghi #<?= (int) $r['id'] ?>">Xóa</button>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <?php
        $tabParams = array_filter([
            'route' => $filterRoute,
            'flight' => $filterFlight,
            'awb' => $filterAwb,
            'vin_serial' => $filterVinSerial,
        ]);
        ?>
        <nav class="sheet-bar" aria-label="Tab theo tháng">
          <a class="sheet-tab <?= $activeMonth === 'all' ? 'active' : '' ?>"
             href="bangke.php?<?= bangke_h(http_build_query(array_merge(['m' => 'all'], $tabParams))) ?>">
            Tất cả các tháng
          </a>
          <?php foreach ($months as $ym): ?>
            <a class="sheet-tab <?= $ym === $activeMonth ? 'active' : '' ?>"
               href="bangke.php?<?= bangke_h(http_build_query(array_merge(['m' => $ym], $tabParams))) ?>">
              <?= bangke_h(bangke_month_label($ym)) ?>
            </a>
          <?php endforeach; ?>
        </nav>
      </form>

      <form id="singleDeleteForm" method="post" action="<?= bangke_h($pageUrl) ?>" style="display:none">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteTargetId" value="">
      </form>
    </div>
  </section>
</div>

<!-- Modal xác nhận xóa dữ liệu -->
<div id="confirmDeleteModal" class="modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-icon-danger">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
          <line x1="12" y1="9" x2="12" y2="13"></line>
          <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
      </div>
      <div>
        <h3 id="modalTitle" class="modal-title">Xác nhận xóa dữ liệu</h3>
        <p class="modal-subtitle">Thao tác này sẽ xóa vĩnh viễn dữ liệu khỏi hệ thống</p>
      </div>
    </div>
    <div class="modal-body" id="modalMessage">
      Bạn có chắc chắn muốn xóa các dòng đã chọn không? Hành động này không thể hoàn tác.
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" id="modalBtnCancel">Hủy bỏ</button>
      <button type="button" class="btn btn-danger" id="modalBtnConfirm">Đồng ý xóa</button>
    </div>
  </div>
</div>

<script>
(function () {
  var checkAll = document.getElementById('checkAll');
  var checks = Array.prototype.slice.call(document.querySelectorAll('.row-check'));
  var hint = document.getElementById('selectedHint');
  var btnSelectAll = document.getElementById('btnSelectAll');
  var btnPrint = document.getElementById('btnPrint');
  var btnEms = document.getElementById('btnEms');
  var btnExport = document.getElementById('btnExport');
  var btnDeleteSelected = document.getElementById('btnDeleteSelected');

  // Modal xác nhận xóa
  var confirmModal = document.getElementById('confirmDeleteModal');
  var modalMsg = document.getElementById('modalMessage');
  var modalBtnCancel = document.getElementById('modalBtnCancel');
  var modalBtnConfirm = document.getElementById('modalBtnConfirm');
  var pendingDeleteAction = null;

  function showConfirmModal(messageHtml, onConfirm) {
    if (!confirmModal) {
      if (confirm(messageHtml.replace(/<[^>]+>/g, ''))) {
        onConfirm();
      }
      return;
    }
    modalMsg.innerHTML = messageHtml;
    pendingDeleteAction = onConfirm;
    confirmModal.style.display = 'flex';
    if (modalBtnConfirm) modalBtnConfirm.focus();
  }

  function hideConfirmModal() {
    if (confirmModal) confirmModal.style.display = 'none';
    pendingDeleteAction = null;
  }

  if (modalBtnCancel) {
    modalBtnCancel.addEventListener('click', hideConfirmModal);
  }

  if (confirmModal) {
    confirmModal.addEventListener('click', function (e) {
      if (e.target === confirmModal) {
        hideConfirmModal();
      }
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && confirmModal && confirmModal.style.display === 'flex') {
      hideConfirmModal();
    }
  });

  if (modalBtnConfirm) {
    modalBtnConfirm.addEventListener('click', function () {
      if (typeof pendingDeleteAction === 'function') {
        var actionFn = pendingDeleteAction;
        hideConfirmModal();
        actionFn();
      }
    });
  }

  function selectedCount() {
    return checks.filter(function (c) { return c.checked; }).length;
  }

  function syncRowStyle(cb) {
    var tr = cb.closest('tr');
    if (tr) tr.classList.toggle('selected', cb.checked);
  }

  function refresh() {
    var n = selectedCount();
    hint.textContent = n ? ('Đã chọn ' + n + ' dòng') : 'Chưa chọn dòng nào';
    if (checkAll) {
      checkAll.checked = checks.length > 0 && n === checks.length;
      checkAll.indeterminate = n > 0 && n < checks.length;
    }
    var disabled = n === 0;
    if (btnPrint) btnPrint.disabled = disabled;
    if (btnEms) btnEms.disabled = disabled;
    if (btnExport) btnExport.disabled = disabled;
    if (btnDeleteSelected) btnDeleteSelected.disabled = disabled;
    checks.forEach(syncRowStyle);
  }

  if (checkAll) {
    checkAll.addEventListener('change', function () {
      checks.forEach(function (c) { c.checked = checkAll.checked; });
      refresh();
    });
  }

  if (btnSelectAll) {
    btnSelectAll.addEventListener('click', function () {
      var allOn = checks.length > 0 && selectedCount() === checks.length;
      checks.forEach(function (c) { c.checked = !allOn; });
      refresh();
    });
  }

  checks.forEach(function (c) {
    c.addEventListener('change', refresh);
  });

  if (btnPrint) {
    btnPrint.addEventListener('click', function () {
      document.getElementById('listForm').target = '_blank';
    });
  }

  if (btnEms) {
    btnEms.addEventListener('click', function () {
      document.getElementById('listForm').target = '_blank';
    });
  }

  if (btnExport) {
    btnExport.addEventListener('click', function () {
      document.getElementById('listForm').target = '_self';
    });
  }

  function prepareListFormSubmit() {
    var checkedVals = checks.filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
    var idsCsvInput = document.getElementById('idsCsv');
    if (idsCsvInput) {
      idsCsvInput.value = checkedVals.join(',');
    }
  }

  if (btnDeleteSelected) {
    btnDeleteSelected.addEventListener('click', function () {
      var n = selectedCount();
      if (n === 0) {
        alert('Vui lòng chọn ít nhất một dòng để xóa.');
        return;
      }
      var msg = 'Bạn có chắc chắn muốn xóa <strong>' + n + ' dòng đã chọn</strong> không?<br>' +
                '<span style="color:var(--err);font-weight:600;display:inline-block;margin-top:8px;">⚠️ Hành động này sẽ xóa vĩnh viễn dữ liệu và không thể hoàn tác.</span>';
      showConfirmModal(msg, function () {
        var form = document.getElementById('listForm');
        form.target = '_self';
        prepareListFormSubmit();
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = 'delete_selected';
        form.appendChild(input);
        form.submit();
      });
    });
  }

  window.confirmDelete = function (id) {
    var msg = 'Bạn có chắc chắn muốn xóa <strong>bản ghi #' + id + '</strong> không?<br>' +
              '<span style="color:var(--err);font-weight:600;display:inline-block;margin-top:8px;">⚠️ Dữ liệu sẽ bị xóa vĩnh viễn và không thể hoàn tác.</span>';
    showConfirmModal(msg, function () {
      document.getElementById('deleteTargetId').value = id;
      document.getElementById('singleDeleteForm').submit();
    });
  };

  var listFormEl = document.getElementById('listForm');
  if (listFormEl) {
    listFormEl.addEventListener('submit', function (e) {
      if (selectedCount() === 0) {
        e.preventDefault();
        alert('Vui lòng chọn ít nhất một dòng.');
        return;
      }
      prepareListFormSubmit();
    });
  }

  refresh();

  var importFile = document.getElementById('importFile');
  var importFileName = document.getElementById('importFileName');
  if (importFile) {
    importFile.addEventListener('change', function () {
      importFileName.textContent = importFile.files.length ? importFile.files[0].name : 'Chưa chọn file';
    });
  }
  var importForm = document.getElementById('importForm');
  if (importForm) {
    importForm.addEventListener('submit', function () {
      var btn = document.getElementById('btnImport');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Đang import…';
      }
    });
  }
})();
</script>
</body>
</html>
