<?php
/**
 * Bảng kê hàng không — chức năng độc lập trên domain lsslogistics.vn
 * Không phụ thuộc auth / module EMS-LSS.
 */
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
    $flash = 'File vượt quá dung lượng máy chủ cho phép (tối đa 5 MB).';
    $flashType = 'err';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'import') {
    try {
        $importResult = bangke_import_file($conn, $_FILES['import_file'] ?? []);
        if ($importResult['last_date'] !== null) {
            $importMonth = substr($importResult['last_date'], 0, 7);
        }
        $flash = sprintf(
            'Import xong: thêm mới %d dòng, bỏ qua %d dòng trùng, %d dòng lỗi.',
            $importResult['inserted'],
            $importResult['skipped'],
            count($importResult['errors'])
        );
        $flashType = $importResult['inserted'] > 0 || !$importResult['errors'] ? 'ok' : 'err';
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
    // Sắp xếp các dòng theo STT tăng dần để in bill đúng thứ tự STT
    usort($rows, static function ($a, $b) {
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

// --- Delete single ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $delId = (int) ($_POST['id'] ?? 0);
    $delCount = 0;
    if ($delId > 0) {
        $delCount = bangke_delete_by_ids($conn, [$delId]);
    }
    $ym = trim((string) ($_GET['m'] ?? date('Y-m')));
    header('Location: bangke.php?m=' . urlencode($ym) . '&deleted=' . ($delCount > 0 ? '1' : '0'));
    exit;
}

// --- Delete selected ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_selected') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    $delCount = bangke_delete_by_ids($conn, $ids);
    $ym = trim((string) ($_GET['m'] ?? date('Y-m')));
    header('Location: bangke.php?m=' . urlencode($ym) . '&deleted=' . $delCount);
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
            $ym = substr($request_date, 0, 7);
            header('Location: bangke.php?m=' . urlencode($ym) . '&updated=1');
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

// Kiểm tra chế độ sửa bản ghi
$editId = (int) ($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    $editRow = bangke_fetch_by_id($conn, $editId);
    if (!$editRow) {
        $flash = 'Không tìm thấy bản ghi #' . $editId . ' để sửa.';
        $flashType = 'err';
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

$months = bangke_fetch_months($conn);
$currentMonth = date('Y-m');
$activeMonth = $importMonth ?? trim((string) ($_GET['m'] ?? ''));
if ($editRow && !isset($_GET['m'])) {
    $activeMonth = substr($editRow['request_date'], 0, 7);
}
if (!preg_match('/^\d{4}-\d{2}$/', $activeMonth)) {
    $activeMonth = $months[0] ?? $currentMonth;
}
if (!$months) {
    $months = [$currentMonth];
    $activeMonth = $currentMonth;
} elseif (!in_array($activeMonth, $months, true)) {
    $activeMonth = $months[0];
}

$list = bangke_fetch_by_month($conn, $activeMonth);
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
.field input {
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
.field input:focus {
  outline: none;
  border-color: #3d7ab5;
  background: #fff;
  box-shadow: 0 0 0 4px rgba(61,122,181,.15);
}
.form-actions {
  display: flex; justify-content: flex-end; gap: 10px;
  margin-top: 16px; padding-top: 4px;
}

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
  .form-grid { grid-template-columns: repeat(2, 1fr); }
  .field.span2 { grid-column: span 2; }
  .hero { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 560px) {
  .form-grid { grid-template-columns: 1fr; }
  .field.span2 { grid-column: auto; }
  .form-actions { justify-content: stretch; }
  .form-actions .btn { width: 100%; }
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
      <form method="post" autocomplete="off" action="bangke.php?m=<?= urlencode($activeMonth) ?>">
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
            <a href="bangke.php?m=<?= urlencode($activeMonth) ?>" class="btn btn-ghost">Hủy sửa</a>
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
          Dòng đầu là tiêu đề cột: STT, Ngày yêu cầu, Chặng, Số hiệu chuyến bay, Airway Bill, Số VIN, Số Serial, Số kiện, Khối lượng.
          Ngày ghi dạng dd/mm/yyyy (bỏ trống = hôm nay). STT bỏ trống sẽ tự động sinh tăng dần theo thứ tự dòng. Dòng trùng Airway Bill + VIN + Serial với dữ liệu đã có sẽ được bỏ qua.
        </p>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="panel-hd">
      <h2>Danh sách tháng <?= bangke_h(bangke_month_label($activeMonth)) ?></h2>
      <span><?= count($list) ?> dòng</span>
    </div>
    <div class="panel-bd">
      <form method="post" id="listForm" action="bangke.php?m=<?= urlencode($activeMonth) ?>">
        <div class="toolbar">
          <button type="button" class="btn btn-ghost" id="btnSelectAll">Chọn tất cả</button>
          <button type="submit" name="action" value="print" class="btn btn-navy" id="btnPrint">In bill</button>
          <button type="submit" name="action" value="print_ems" class="btn btn-navy" id="btnEms" style="background:#1a5fa8">In bill EMS</button>
          <button type="submit" name="action" value="export" class="btn btn-primary" id="btnExport">Xuất Excel</button>
          <button type="button" class="btn btn-danger" id="btnDeleteSelected">Xóa đã chọn</button>
          <div class="grow" id="selectedHint">Chưa chọn dòng nào</div>
          <div class="stats">
            <div class="stat">Kiện: <b><?= (int) $sumPkg ?></b></div>
            <div class="stat">KL: <b><?= bangke_h(rtrim(rtrim(number_format($sumW, 2, '.', ''), '0'), '.') ?: '0') ?></b> kg</div>
          </div>
        </div>

        <div class="table-wrap">
          <?php if (!$list): ?>
            <div class="empty">Chưa có dữ liệu trong tháng này. Hãy nhập form phía trên rồi bấm Lưu.</div>
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
                <tr class="<?= ($editId === (int) $r['id']) ? 'editing' : '' ?>">
                  <td class="check">
                    <input type="checkbox" class="row-check" name="ids[]" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="stt[<?= (int) $r['id'] ?>]" value="<?= (int) (!empty($r['stt']) ? $r['stt'] : ($i + 1)) ?>">
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
                       href="bangke.php?m=<?= urlencode($activeMonth) ?>&edit=<?= (int) $r['id'] ?>#nhap-lieu"
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

        <nav class="sheet-bar" aria-label="Tab theo tháng">
          <?php foreach ($months as $ym): ?>
            <a class="sheet-tab <?= $ym === $activeMonth ? 'active' : '' ?>"
               href="bangke.php?m=<?= urlencode($ym) ?>">
              <?= bangke_h(bangke_month_label($ym)) ?>
            </a>
          <?php endforeach; ?>
        </nav>
      </form>

      <form id="singleDeleteForm" method="post" action="bangke.php?m=<?= urlencode($activeMonth) ?>" style="display:none">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteTargetId" value="">
      </form>
    </div>
  </section>
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

  if (btnDeleteSelected) {
    btnDeleteSelected.addEventListener('click', function () {
      var n = selectedCount();
      if (n === 0) {
        alert('Vui lòng chọn ít nhất một dòng để xóa.');
        return;
      }
      if (confirm('Bạn có chắc chắn muốn xóa ' + n + ' dòng đã chọn không? Thao tác này không thể hoàn tác.')) {
        var form = document.getElementById('listForm');
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = 'delete_selected';
        form.appendChild(input);
        form.submit();
      }
    });
  }

  window.confirmDelete = function (id) {
    if (confirm('Bạn có chắc chắn muốn xóa bản ghi #' + id + ' không?')) {
      document.getElementById('deleteTargetId').value = id;
      document.getElementById('singleDeleteForm').submit();
    }
  };

  document.getElementById('listForm').addEventListener('submit', function (e) {
    if (selectedCount() === 0) {
      e.preventDefault();
      alert('Vui lòng chọn ít nhất một dòng.');
    }
  });

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
