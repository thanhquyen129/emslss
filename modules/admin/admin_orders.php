<?php
session_start();
require '../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/dashboard_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$pickupUsers = emslss_fetch_active_users_by_role($conn, 'shipper');
$deliveryUsers = $pickupUsers;

$listFilter = admin_orders_list_filter($_GET);
$statusFilter = $listFilter['statusFilter'];
$emsKeyword = $listFilter['emsKeyword'];

$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalOrders = admin_orders_run_count($conn, $listFilter);
$totalPages = max(1, (int)ceil($totalOrders / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$orders = admin_orders_fetch_page($conn, $listFilter, $perPage, $offset);
$orderMeta = admin_load_order_ack_meta($conn, array_column($orders, 'id'));
$orderCargoMeta = emslss_order_meta_bulk($conn, array_column($orders, 'id'), ['cargo_description']);

function statusBadge($status)
{
    $map = [
        'new_order' => 'secondary', 'assigned_pickup' => 'primary', 'picked_up' => 'info',
        'in_transit' => 'warning', 'assigned_delivery' => 'dark', 'delivered' => 'success',
        'failed' => 'danger', 'cancelled' => 'danger',
    ];
    $color = $map[$status] ?? 'secondary';
    return "<span class='badge bg-$color'>$status</span>";
}

function pageUrlOrders($p, $statusFilter, $emsKeyword)
{
    return admin_orders_page_url((int) $p, $statusFilter, $emsKeyword);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tất cả đơn hàng</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f5f7fb; }
.trim-tip { border-bottom: 1px dotted #888; cursor: help; }
.trim-tip-popup {
    position: fixed; z-index: 9999; max-width: 90vw; padding: 8px 12px;
    background: #212529; color: #fff; border-radius: 8px; font-size: 13px;
    box-shadow: 0 4px 16px rgba(0,0,0,.25); display: none;
}
</style>
</head>
<body>
<?php include '../../templates/admin_topbar.php'; ?>
<div id="trimTipPopup" class="trim-tip-popup"></div>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Tất cả đơn hàng</h4>
            <small class="text-muted"><?= $totalOrders ?> đơn · trang <?= $page ?>/<?= $totalPages ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="order_export.php" class="btn btn-sm btn-success">📥 Kết xuất CSV</a>
            <a href="admin_dashboard_realtime.php" class="btn btn-sm btn-outline-primary">Dashboard realtime</a>
            <a href="admin_orders_cleanup.php" class="btn btn-sm btn-outline-danger">Xóa đơn test</a>
        </div>
    </div>

    <?= admin_render_ems_search_form($statusFilter, $emsKeyword) ?>

    <div class="mb-3">
        <a href="<?= htmlspecialchars(admin_orders_page_url(1, '', $emsKeyword)) ?>" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">Tất cả</a>
        <?php foreach (admin_orders_allowed_statuses() as $st): ?>
        <a href="<?= htmlspecialchars(admin_orders_page_url(1, $st, $emsKeyword)) ?>" class="btn btn-sm <?= $statusFilter === $st ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $st ?></a>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover bg-white table-sm">
            <thead class="table-light">
                <tr>
                    <th>Mã EMS</th><th>TT</th><th>Bưu cục</th><th>Hàng hóa</th><th>Địa chỉ</th><th>Người nhận</th>
                    <th>Pickup</th><th>Delivery</th><th>Nhận tin</th><th>Ngày tạo</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $row): ?>
                <tr>
                    <td><a href="admin_order_detail.php?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['ems_code']) ?></a></td>
                    <td><?= statusBadge($row['status']) ?></td>
                    <td><?= admin_render_trim_span($row['post_office_name']) ?></td>
                    <td class="small"><?= emslss_order_cargo_html($row, $orderCargoMeta[(int)$row['id']] ?? []) ?></td>
                    <td><?= admin_render_trim_span($row['post_office_address']) ?></td>
                    <td><?= admin_render_trim_span($row['receiver_name'] . ' — ' . $row['receiver_address']) ?></td>
                    <td style="min-width:130px"><?= admin_render_pickup_select($row, $pickupUsers) ?></td>
                    <td style="min-width:130px"><?= admin_render_delivery_select($row, $deliveryUsers) ?></td>
                    <td class="small"><?= admin_render_ack_html((int)$row['id'], $orderMeta) ?></td>
                    <td><small><?= htmlspecialchars($row['created_at']) ?></small></td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="admin_order_detail.php?id=<?= (int)$row['id'] ?>">Chi tiết</a>
                        <?= admin_render_reject_button($row) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination flex-wrap">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= htmlspecialchars(pageUrlOrders($p, $statusFilter, $emsKeyword)) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<div class="modal fade" id="rejectOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Từ chối nhận đơn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Mã EMS: <strong id="rejectEmsCode">-</strong></p>
                <div class="mb-3">
                    <label class="form-label">Lý do <span class="text-danger">*</span></label>
                    <select class="form-select" id="rejectReason">
                        <option value="">-- chọn --</option>
                        <?php foreach (emslss_order_reject_reasons() as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <textarea class="form-control" id="rejectReasonDetail" rows="2" placeholder="Chi tiết (bắt buộc nếu Lý do khác)"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger" id="btnConfirmReject">Xác nhận</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$('.assign-user').change(function(){
    const $el = $(this);
    if ($el.prop('disabled')) return;
    $.post('assign_order_user.php', {
        order_id: $el.data('order-id'), user_id: $el.val(), type: $el.data('type')
    }, function(res){
        if (!res || !res.success) { alert((res && res.message) || 'Gán thất bại'); location.reload(); }
    }, 'json');
});
let rejectOrderId = 0;
const rejectModalEl = document.getElementById('rejectOrderModal');
if (rejectModalEl) {
    const rejectModal = new bootstrap.Modal(rejectModalEl);
    document.querySelectorAll('.btn-reject-order').forEach(btn => {
        btn.addEventListener('click', () => {
            rejectOrderId = parseInt(btn.dataset.orderId, 10);
            document.getElementById('rejectEmsCode').textContent = btn.dataset.emsCode || '';
            document.getElementById('rejectReason').value = '';
            document.getElementById('rejectReasonDetail').value = '';
            rejectModal.show();
        });
    });
    document.getElementById('btnConfirmReject').addEventListener('click', () => {
        const reason = document.getElementById('rejectReason').value;
        const reason_detail = document.getElementById('rejectReasonDetail').value.trim();
        if (!reason) { alert('Chọn lý do'); return; }
        if (reason === 'Lý do khác' && !reason_detail) { alert('Nhập chi tiết'); return; }
        if (!confirm('Từ chối nhận đơn?')) return;
        const fd = new FormData();
        fd.append('order_id', rejectOrderId);
        fd.append('reason', reason);
        fd.append('reason_detail', reason_detail);
        fetch('reject_order.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => { alert(res.message || 'OK'); if (res.success) location.reload(); });
    });
}
const tipPopup = document.getElementById('trimTipPopup');
function showTrimTip(el, x, y) {
    if (!el.dataset.full) return;
    tipPopup.textContent = el.dataset.full;
    tipPopup.style.display = 'block';
    tipPopup.style.left = Math.min(x, innerWidth - 200) + 'px';
    tipPopup.style.top = Math.min(y, innerHeight - 80) + 'px';
}
function hideTrimTip() { tipPopup.style.display = 'none'; }
document.querySelectorAll('.trim-tip').forEach(el => {
    el.addEventListener('mouseenter', e => showTrimTip(el, e.clientX + 12, e.clientY + 12));
    el.addEventListener('mouseleave', hideTrimTip);
    el.addEventListener('click', e => { e.preventDefault(); showTrimTip(el, e.clientX, e.clientY); });
});
</script>
</body>
</html>
