<?php
/**
 * Bảng kê hàng không — chức năng độc lập trên domain lsslogistics.vn
 * Không phụ thuộc auth / module EMS-LSS.
 */
require_once __DIR__ . '/bangke/bootstrap.php';
require_once __DIR__ . '/bangke/export.php';

$flash = '';
$flashType = 'ok';
$today = date('Y-m-d');

// --- Actions: print / export ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action === 'print' || $action === 'export') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    $rows = bangke_fetch_by_ids($conn, $ids);
    if (!$rows) {
        $flash = 'Vui lòng chọn ít nhất một dòng trong danh sách.';
        $flashType = 'err';
    } elseif ($action === 'print') {
        bangke_render_print($rows);
        exit;
    } else {
        bangke_export_excel($rows, 'bang_ke_lss');
        exit;
    }
}

// --- Save ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'save' || isset($_POST['save']))) {
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

    if ($route_leg === '' && $airway_bill === '' && $vin_no === '' && $serial_no === '') {
        $flash = 'Nhập ít nhất Chặng, Airway Bill, VIN hoặc Serial.';
        $flashType = 'err';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO bangke_items
             (request_date, route_leg, flight_no, airway_bill, vin_no, serial_no, package_count, weight)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'ssssssid',
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
            $flash = 'Đã lưu bản ghi thành công.';
            $flashType = 'ok';
            // Redirect tránh F5 submit lại
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
}

$months = bangke_fetch_months($conn);
$currentMonth = date('Y-m');
$activeMonth = trim((string) ($_GET['m'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}$/', $activeMonth)) {
    $activeMonth = $months[0] ?? $currentMonth;
}
if (!in_array($activeMonth, $months, true) && $months) {
    // Tháng mới chưa có data nhưng user vừa lưu → đã có trong $months
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
    'request_date' => $_POST['request_date'] ?? $today,
    'route_leg' => $_POST['route_leg'] ?? '',
    'flight_no' => $_POST['flight_no'] ?? '',
    'airway_bill' => $_POST['airway_bill'] ?? '',
    'vin_no' => $_POST['vin_no'] ?? '',
    'serial_no' => $_POST['serial_no'] ?? '',
    'package_count' => $_POST['package_count'] ?? '',
    'weight' => $_POST['weight'] ?? '',
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
    <div class="flash <?= $flashType === 'ok' ? 'ok' : 'err' ?>"><?= bangke_h($flash) ?></div>
  <?php endif; ?>

  <section class="panel">
    <div class="panel-hd">
      <h2>Nhập liệu</h2>
      <span>Ngày mặc định = hôm nay</span>
    </div>
    <div class="panel-bd">
      <form method="post" autocomplete="off">
        <input type="hidden" name="action" value="save">
        <div class="form-grid">
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
          <button type="reset" class="btn btn-ghost">Xóa form</button>
          <button type="submit" class="btn btn-primary">Lưu bản ghi</button>
        </div>
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
          <button type="submit" name="action" value="export" class="btn btn-primary" id="btnExport">Xuất Excel</button>
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
                </tr>
              </thead>
              <tbody>
              <?php foreach ($list as $i => $r): ?>
                <tr>
                  <td class="check">
                    <input type="checkbox" class="row-check" name="ids[]" value="<?= (int) $r['id'] ?>">
                  </td>
                  <td><?= $i + 1 ?></td>
                  <td><?= bangke_h(date('d/m/Y', strtotime($r['request_date']))) ?></td>
                  <td><?= bangke_h($r['route_leg']) ?></td>
                  <td class="mono"><?= bangke_h($r['flight_no']) ?></td>
                  <td class="mono"><?= bangke_h($r['airway_bill']) ?></td>
                  <td class="mono"><?= bangke_h($r['vin_no']) ?></td>
                  <td class="mono"><?= bangke_h($r['serial_no']) ?></td>
                  <td class="num"><?= (int) $r['package_count'] ?></td>
                  <td class="num"><?= bangke_h(rtrim(rtrim(number_format((float) $r['weight'], 2, '.', ''), '0'), '.') ?: '0') ?></td>
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
  var btnExport = document.getElementById('btnExport');

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
    if (btnExport) btnExport.disabled = disabled;
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

  document.getElementById('listForm').addEventListener('submit', function (e) {
    if (selectedCount() === 0) {
      e.preventDefault();
      alert('Vui lòng chọn ít nhất một dòng.');
    }
  });

  refresh();
})();
</script>
</body>
</html>
