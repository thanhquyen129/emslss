<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/order_helpers.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'dispatcher', 'ems'], true)) {
    header('Location: ../login.php');
    exit;
}

$isEmsPortal = ($_SESSION['role'] ?? '') === 'ems';
$canPickColumns = in_array($_SESSION['role'] ?? '', ['admin', 'dispatcher'], true);
$exportColumnGroups = emslss_export_detail_column_groups();
$defaultColumnKeys = emslss_export_default_column_keys();

$shippers = emslss_fetch_active_users_by_role($conn, 'shipper');
$statusLabels = emslss_export_status_labels();
$dateFields = emslss_export_allowed_date_fields();

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$dateField = $_GET['date_field'] ?? 'delivered_at';
$previewCount = 0;

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $filters = emslss_export_build_filters([
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'date_field' => $dateField,
        'status' => $_GET['status'] ?? ['delivered'],
    ]);
    $countSql = 'SELECT COUNT(*) AS c FROM emslss_orders o ' . emslss_export_milestones_sql() . ' WHERE ' . $filters['sql'];
    $countStmt = $conn->prepare($countSql);
    if ($filters['types'] !== '') {
        $countStmt->bind_param($filters['types'], ...$filters['params']);
    }
    $countStmt->execute();
    $previewCount = (int) ($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
}

$deliveredMonth = (int) $conn->query("
    SELECT COUNT(*) c FROM emslss_orders WHERE status='delivered'
    AND DATE(updated_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
")->fetch_assoc()['c'];

$rejectedMonth = (int) $conn->query("
    SELECT COUNT(DISTINCT o.id) c FROM emslss_orders o
    INNER JOIN emslss_order_meta m ON m.order_id = o.id AND m.meta_key = 'lss_reject_reason'
    WHERE DATE(o.updated_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kết xuất đơn — Báo cáo thanh toán</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f5f7fb; }
.card { border: none; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,.06); }
.export-columns fieldset { border: 1px solid #dee2e6; border-radius: 10px; padding: 1rem 1.25rem; }
.export-columns legend { font-size: 0.95rem; font-weight: 600; padding: 0 0.5rem; width: auto; float: none; }
#previewPanel .table-preview { font-size: 12px; }
#previewPanel .table-preview th { white-space: nowrap; position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
#previewPanel .preview-scroll { max-height: 420px; overflow: auto; }
</style>
</head>
<body>
<?php if ($isEmsPortal): include __DIR__ . '/../../templates/ems_topbar.php'; else: include __DIR__ . '/../../templates/admin_topbar.php'; endif; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">📥 Kết xuất dữ liệu đơn</h4>
            <small class="text-muted">Phục vụ báo cáo thống kê thanh toán, chốt công nợ với EMS / shipper</small>
        </div>
        <a href="<?= $isEmsPortal ? '/modules/ems/dashboard.php' : 'admin_reports.php' ?>" class="btn btn-outline-secondary btn-sm">← Quay lại</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3 border-start border-success border-4">
                <div class="text-muted small">Đã giao tháng này</div>
                <div class="fs-3 fw-bold text-success"><?= $deliveredMonth ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 border-start border-danger border-4">
                <div class="text-muted small">Từ chối LSS tháng này</div>
                <div class="fs-3 fw-bold text-danger"><?= $rejectedMonth ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 border-start border-primary border-4">
                <div class="text-muted small">Khớp bộ lọc (mặc định)</div>
                <div class="fs-3 fw-bold text-primary"><?= $previewCount ?> đơn</div>
            </div>
        </div>
    </div>

    <div class="card p-4">
        <form method="POST" action="order_export_download.php" id="exportForm">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Lọc theo mốc thời gian</label>
                    <select name="date_field" class="form-select">
                        <?php foreach ($dateFields as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $dateField === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Từ ngày</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Đến ngày</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Dịch vụ</label>
                    <select name="service_type" class="form-select">
                        <option value="">Tất cả</option>
                        <option value="door_to_door">door_to_door</option>
                        <option value="door_to_hub">door_to_hub</option>
                        <option value="hub_to_door">hub_to_door</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Trạng thái (giữ Ctrl để chọn nhiều)</label>
                    <select name="status[]" class="form-select" multiple size="5">
                        <?php foreach ($statusLabels as $code => $label): ?>
                        <option value="<?= htmlspecialchars($code) ?>" <?= in_array($code, ['delivered'], true) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?> (<?= $code ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Gợi ý chốt công nợ: chọn <strong>delivered</strong> + lọc theo <strong>Ngày giao thành công</strong></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Shipper (pickup hoặc delivery)</label>
                    <select name="shipper_id" class="form-select">
                        <option value="0">Tất cả</option>
                        <?php foreach ($shippers as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tìm nhanh</label>
                    <input type="text" name="keyword" class="form-control" placeholder="Mã EMS, bưu cục, tên...">
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="lss_rejected_only" value="1" id="lssRejected">
                        <label class="form-check-label" for="lssRejected">Chỉ đơn bị LSS từ chối nhận</label>
                    </div>
                </div>

                <?php if ($canPickColumns): ?>
                <div class="col-12 export-columns">
                    <fieldset>
                        <legend>Chọn cột xuất — file chi tiết từng đơn</legend>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="colSelectDefault">Mặc định (quan trọng)</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="colSelectAll">Chọn tất cả</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="colClearAll">Bỏ chọn hết</button>
                        </div>
                        <?php foreach ($exportColumnGroups as $groupName => $cols): ?>
                        <div class="mb-3">
                            <div class="text-muted small fw-semibold mb-2"><?= htmlspecialchars($groupName) ?></div>
                            <div class="row g-2">
                                <?php foreach ($cols as $colKey => $colDef): ?>
                                <div class="col-6 col-md-4 col-lg-3">
                                    <div class="form-check">
                                        <input class="form-check-input export-col-cb" type="checkbox"
                                               name="columns[]" value="<?= htmlspecialchars($colKey) ?>"
                                               id="col_<?= htmlspecialchars($colKey) ?>"
                                               <?= !empty($colDef['default']) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="col_<?= htmlspecialchars($colKey) ?>">
                                            <?= htmlspecialchars($colDef['label']) ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <p class="small text-muted mb-0">Chỉ áp dụng khi bấm <strong>Chi tiết từng đơn (CSV)</strong>. Tổng hợp shipper/trạng thái giữ cột cố định.</p>
                    </fieldset>
                </div>
                <?php endif; ?>

                <div class="col-12">
                    <label class="form-label fw-semibold">Kết xuất / xem trước</label>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-info text-white" id="btnPreview">
                            👁 Xem trước
                        </button>
                        <button type="submit" name="export_type" value="detail" class="btn btn-primary">
                            📄 Chi tiết từng đơn (CSV)
                        </button>
                        <button type="submit" name="export_type" value="summary_shipper" class="btn btn-outline-primary">
                            👤 Tổng hợp theo shipper
                        </button>
                        <button type="submit" name="export_type" value="summary_status" class="btn btn-outline-primary">
                            📊 Tổng hợp trạng thái / dịch vụ
                        </button>
                    </div>
                    <p class="small text-muted mt-2 mb-0">
                        <strong>Xem trước</strong> hiển thị tối đa <?= emslss_export_preview_limit() ?> dòng đầu theo bộ lọc và cột đã chọn.
                        File CSV UTF-8 (mở được bằng Excel).
                    </p>
                </div>
            </div>
        </form>
    </div>

    <div class="card p-4 mt-3 d-none" id="previewPanel">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h6 class="mb-0">👁 Xem trước dữ liệu</h6>
            <span class="small text-muted" id="previewMeta"></span>
        </div>
        <div id="previewLoading" class="text-center py-4 text-muted d-none">Đang tải...</div>
        <div id="previewEmpty" class="text-center py-4 text-muted d-none">Không có đơn phù hợp bộ lọc.</div>
        <div class="preview-scroll d-none" id="previewTableWrap">
            <table class="table table-bordered table-sm table-hover table-preview mb-0" id="previewTable">
                <thead><tr id="previewHead"></tr></thead>
                <tbody id="previewBody"></tbody>
            </table>
        </div>
    </div>
</div>
<script>
(function () {
    const defaults = <?= json_encode($defaultColumnKeys, JSON_UNESCAPED_UNICODE) ?>;
    const allKeys = <?= json_encode(array_keys(emslss_export_detail_column_defs()), JSON_UNESCAPED_UNICODE) ?>;
    const canPick = <?= $canPickColumns ? 'true' : 'false' ?>;

    const setCols = (keys) => {
        document.querySelectorAll('.export-col-cb').forEach(cb => {
            cb.checked = keys.includes(cb.value);
        });
    };
    document.getElementById('colSelectDefault')?.addEventListener('click', () => setCols(defaults));
    document.getElementById('colSelectAll')?.addEventListener('click', () => setCols(allKeys));
    document.getElementById('colClearAll')?.addEventListener('click', () => setCols([]));

    document.getElementById('exportForm')?.addEventListener('submit', function (e) {
        const btn = e.submitter;
        if (btn && btn.name === 'export_type' && btn.value === 'detail') {
            const checked = canPick
                ? document.querySelectorAll('.export-col-cb:checked').length
                : defaults.length;
            if (checked === 0) {
                e.preventDefault();
                alert('Vui lòng chọn ít nhất một cột để xuất.');
            }
        }
    });

    document.getElementById('btnPreview')?.addEventListener('click', async () => {
        if (canPick && document.querySelectorAll('.export-col-cb:checked').length === 0) {
            alert('Vui lòng chọn ít nhất một cột để xem trước.');
            return;
        }
        const form = document.getElementById('exportForm');
        const panel = document.getElementById('previewPanel');
        const loading = document.getElementById('previewLoading');
        const empty = document.getElementById('previewEmpty');
        const wrap = document.getElementById('previewTableWrap');
        const meta = document.getElementById('previewMeta');
        const head = document.getElementById('previewHead');
        const body = document.getElementById('previewBody');

        panel.classList.remove('d-none');
        loading.classList.remove('d-none');
        empty.classList.add('d-none');
        wrap.classList.add('d-none');
        head.innerHTML = '';
        body.innerHTML = '';
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

        try {
            const res = await fetch('order_export_preview.php', {
                method: 'POST',
                body: new FormData(form),
            });
            const data = await res.json();
            loading.classList.add('d-none');
            if (!data.ok) {
                alert(data.error || 'Không xem trước được');
                return;
            }
            meta.textContent = 'Tổng ' + data.total + ' đơn — hiển thị ' + data.shown
                + (data.total > data.shown ? ' (tối đa ' + data.limit + ' dòng đầu)' : '');
            if (!data.rows || data.rows.length === 0) {
                empty.classList.remove('d-none');
                return;
            }
            head.innerHTML = data.headers.map(h => '<th>' + escapeHtml(h) + '</th>').join('');
            body.innerHTML = data.rows.map(row => {
                return '<tr>' + row.map(cell => '<td>' + escapeHtml(cell) + '</td>').join('') + '</tr>';
            }).join('');
            wrap.classList.remove('d-none');
        } catch (err) {
            loading.classList.add('d-none');
            alert('Lỗi kết nối khi xem trước.');
        }
    });

    function escapeHtml(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>
</body>
</html>
