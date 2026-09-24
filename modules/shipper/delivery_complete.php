<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/upload.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? '';

if (!in_array($role, ['shipper', 'admin', 'operation'], true)) {
    die('Access denied');
}

$order_id = (int) ($_GET['id'] ?? 0);
if ($order_id <= 0) {
    die('Thiếu ID đơn');
}

if (in_array($role, ['admin', 'operation'], true)) {
    $stmt = $conn->prepare('SELECT * FROM emslss_orders WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $order_id);
} else {
    $stmt = $conn->prepare('SELECT * FROM emslss_orders WHERE id=? AND delivery_shipper_id=? LIMIT 1');
    $stmt->bind_param('ii', $order_id, $user_id);
}
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Không tìm thấy đơn");
}

if ($order['status'] == 'delivered') {
    die("Đơn đã hoàn tất, không thể submit lại.");
}

$failCountStmt = $conn->prepare("SELECT COUNT(*) c FROM emslss_tracking WHERE order_id=? AND status='failed'");
$failCountStmt->bind_param('i', $order_id);
$failCountStmt->execute();
$deliveryFailCount = (int)($failCountStmt->get_result()->fetch_assoc()['c'] ?? 0);

$existingImgStmt = $conn->prepare("SELECT COUNT(*) c FROM emslss_images WHERE order_id=? AND image_path LIKE '%/delivery/%'");
$existingImgStmt->bind_param('i', $order_id);
$existingImgStmt->execute();
$existingDeliveryImages = (int)($existingImgStmt->get_result()->fetch_assoc()['c'] ?? 0);

$sigMetaStmt = $conn->prepare("SELECT meta_value FROM emslss_order_meta WHERE order_id=? AND meta_key='customer_signature' ORDER BY id DESC LIMIT 1");
$sigMetaStmt->bind_param('i', $order_id);
$sigMetaStmt->execute();
$existingSigRow = $sigMetaStmt->get_result()->fetch_assoc();
$existingSignaturePath = $existingSigRow['meta_value'] ?? null;

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['success', 'fail'], true)) {
        $message = 'Thao tác không hợp lệ';
    }
    $recipient_name = trim($_POST['recipient_name'] ?? '');
    $fail_reason    = trim($_POST['fail_reason'] ?? '');
    $note           = trim($_POST['note'] ?? $_POST['fail_note_extra'] ?? '');
    $has_image      = !empty($_FILES['proof_images']['name'][0]) || !empty($_FILES['proof_gallery']['name'][0]);
    $has_signature  = !empty($_FILES['signature_image']['name']) || !empty($_FILES['signature_gallery']['name']);
    $signaturePath  = null;
    $proofFiles = ['proof_images', 'proof_gallery'];
    $sigFiles = ['signature_image', 'signature_gallery'];

    if ($message === '' && $action === 'success') {
        if ($recipient_name === '') {
            $message = 'Vui lòng nhập tên người nhận.';
        } elseif (!$has_image && $existingDeliveryImages === 0) {
            $message = 'Vui lòng chụp/chọn ít nhất 1 ảnh phát.';
        } elseif (!$has_signature && empty($existingSignaturePath)) {
            $message = 'Vui lòng chụp/chọn ảnh chữ ký người nhận.';
        } elseif ($note === '') {
            $message = 'Vui lòng nhập ghi chú phát hàng.';
        }
    }
    if ($message === '' && $action === 'fail' && $fail_reason === '') {
        $message = 'Vui lòng chọn lý do giao không thành công.';
    }

    $new_status = ($action == 'success') ? 'delivered' : 'failed';

    if ($message === '') {
        $savedFiles = [];
        try {
            $conn->begin_transaction();

            // lock chống submit 2 lần
            $check = $conn->prepare("SELECT status FROM emslss_orders WHERE id=? FOR UPDATE");
            $check->bind_param("i", $order_id);
            $check->execute();
            $current = $check->get_result()->fetch_assoc();

            if ($current['status'] == 'delivered') {
                throw new RuntimeException("Đơn đã được submit trước đó.");
            }

            $proofPaths = [];
            foreach ($proofFiles as $field) {
                if (!empty($_FILES[$field]['name'][0])) {
                    $proofPaths = array_merge($proofPaths, emslss_upload_save_multipart_field('delivery', $_FILES[$field]));
                }
            }
            if ($proofPaths !== []) {
                $savedFiles = array_merge($savedFiles, $proofPaths);
                emslss_upload_insert_images($conn, $order_id, $proofPaths, $user_id);
            }
            if ($action === 'success' && $proofPaths === [] && $existingDeliveryImages === 0) {
                throw new RuntimeException('Không lưu được ảnh phát. Vui lòng thử lại.');
            }

            foreach ($sigFiles as $field) {
                if (!empty($_FILES[$field]['name'])) {
                    $sigPaths = emslss_upload_save_multipart_field('signatures', $_FILES[$field]);
                    $signaturePath = $sigPaths[0] ?? null;
                    if ($signaturePath !== null) {
                        $savedFiles[] = $signaturePath;
                        break;
                    }
                }
            }
            if ($action === 'success' && $signaturePath === null && !empty($existingSignaturePath)) {
                $signaturePath = $existingSignaturePath;
            }
            if ($action === 'success' && $signaturePath === null) {
                throw new RuntimeException('Không lưu được ảnh chữ ký. Vui lòng thử lại.');
            }

            // update order
            $up = $conn->prepare("
                UPDATE emslss_orders
                SET status=?
                WHERE id=?
            ");
            $up->bind_param("si", $new_status, $order_id);
            $up->execute();

            // lưu thông tin có cấu trúc vào order_meta (dùng đúng key mà trang chi tiết render)
            $metaStmt = $conn->prepare("
                INSERT INTO emslss_order_meta(order_id, meta_key, meta_value)
                VALUES(?,?,?)
            ");
            $metaInsert = function (string $key, string $value) use ($metaStmt, $order_id) {
                if ($value === '') {
                    return;
                }
                $metaStmt->bind_param("iss", $order_id, $key, $value);
                $metaStmt->execute();
            };
            if ($action === 'success') {
                $metaInsert('delivery_recipient_name', $recipient_name);
                $metaInsert('delivery_note', $note);
                if ($signaturePath !== null) {
                    $metaInsert('customer_signature', $signaturePath);
                }
            } else {
                $metaInsert('fail_note', $fail_reason . ($note !== '' ? '. ' . $note : ''));
            }

            // tracking
            $track_note = ($action == 'success')
                ? 'Giao thành công cho: ' . $recipient_name . ($note !== '' ? '. Ghi chú: ' . $note : '')
                : 'Giao thất bại — Lý do: ' . $fail_reason . ($note !== '' ? '. Ghi chú: ' . $note : '');

            $tr = $conn->prepare("
                INSERT INTO emslss_tracking(order_id,status,note,created_by)
                VALUES(?,?,?,?)
            ");
            $tr->bind_param("issi", $order_id, $new_status, $track_note, $user_id);
            $tr->execute();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            emslss_upload_cleanup_paths($savedFiles);
            $message = $e->getMessage();
        }
    }

    if ($message === '') {
        require_once __DIR__ . '/../../api/callback_delivery.php';
        if ($action === 'success') {
            $callbackNote = 'Người nhận: ' . $recipient_name . ($note !== '' ? '. ' . $note : '');
            $callbackExtra = ['note' => $callbackNote];
            if ($signaturePath !== null) {
                $callbackExtra['signature_image'] = emslss_upload_absolute_url($signaturePath);
            }
            sendDeliveryCallback($order_id, 'delivered', $callbackExtra);
        } else {
            $callbackReason = $fail_reason . ($note !== '' ? '. ' . $note : '');
            sendDeliveryCallback($order_id, 'failed', ['reason' => $callbackReason]);
        }
        header('Location: shipper_dashboard.php');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Delivery Complete</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f5f7fa;}
.card-box{
    border-radius:14px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
}
</style>
</head>
<body>

<div class="container py-4">

<div class="card card-box p-4">

<h4><?= $order['status'] === 'failed' ? '🔄 Giao lại đơn hàng' : '✅ Hoàn tất giao hàng' ?></h4>
<div class="mb-2">
    Mã EMS: <strong><?= htmlspecialchars($order['ems_code']) ?></strong>
    <?php if ($deliveryFailCount > 0): ?>
    <span class="badge bg-warning text-dark ms-1">Đã thất bại <?= $deliveryFailCount ?> lần</span>
    <?php endif; ?>
</div>
<?php if ($message !== ''): ?>
<div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="completeForm">

<div class="mb-3">
    <label class="form-label">Kết quả giao hàng <span class="text-danger">*</span></label>
    <select id="outcome" class="form-select" required>
        <option value="">-- Chọn kết quả --</option>
        <option value="success">Giao thành công</option>
        <option value="fail">Giao thất bại</option>
    </select>
</div>

<div id="blockSuccess" class="d-none">
    <div class="mb-3">
        <label>Tên người nhận <span class="text-danger">*</span></label>
        <input type="text" name="recipient_name" id="recipient_name" class="form-control" disabled
            placeholder="Người trực tiếp nhận hàng" value="<?= htmlspecialchars($_POST['recipient_name'] ?? '') ?>">
    </div>
    <div class="mb-3">
        <label>Ảnh phát — chụp</label>
        <input type="file" name="proof_images[]" multiple class="form-control" accept="image/*" capture="environment" disabled>
    </div>
    <div class="mb-3">
        <label>Ảnh phát — chọn từ thư viện (nhiều ảnh)</label>
        <input type="file" name="proof_gallery[]" multiple class="form-control" accept="image/*" disabled>
        <?php if ($existingDeliveryImages > 0): ?>
        <div class="form-text text-success">Đã có <?= $existingDeliveryImages ?> ảnh phát từ lần trước.</div>
        <?php endif; ?>
    </div>
    <div class="mb-3">
        <label>Chữ ký — chụp</label>
        <input type="file" name="signature_image" id="signature_image" class="form-control" accept="image/*" capture="environment" disabled>
    </div>
    <div class="mb-3">
        <label>Chữ ký — chọn từ thư viện</label>
        <input type="file" name="signature_gallery" id="signature_gallery" class="form-control" accept="image/*" disabled>
        <?php if ($existingSignaturePath): ?>
        <div class="form-text text-success">Đã có chữ ký từ lần trước.</div>
        <?php endif; ?>
    </div>
    <div class="mb-3">
        <label>Ghi chú <span class="text-danger">*</span></label>
        <textarea name="note" id="note" class="form-control" disabled
            placeholder="Ghi chú phát hàng"><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
    </div>
</div>

<div id="blockFail" class="d-none">
    <div class="mb-3">
        <label>Lý do giao không thành công <span class="text-danger">*</span></label>
        <select name="fail_reason" id="fail_reason" class="form-select" disabled>
            <option value="">-- chọn --</option>
            <?php foreach (['Khách từ chối nhận','Khách hẹn phát hôm sau','Khách không có ở nhà','Không liên lạc được','Sai địa chỉ','Lý do khác'] as $r): ?>
            <option><?= htmlspecialchars($r) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label>Ghi chú thêm (tùy chọn)</label>
        <textarea name="fail_note_extra" id="fail_note_extra" class="form-control" disabled rows="2"></textarea>
    </div>
</div>

<input type="hidden" name="action" id="actionField" value="">
<button type="submit" id="btnSubmit" class="btn btn-primary w-100 mt-2" disabled>Xác nhận &amp; Submit</button>

</form>

<script>
const outcome = document.getElementById('outcome');
const blockSuccess = document.getElementById('blockSuccess');
const blockFail = document.getElementById('blockFail');
const btnSubmit = document.getElementById('btnSubmit');
const actionField = document.getElementById('actionField');
const existingImgs = <?= (int)$existingDeliveryImages ?>;
const existingSig = <?= $existingSignaturePath ? 'true' : 'false' ?>;

function setBlockEnabled(block, on) {
    block.querySelectorAll('input, textarea, select').forEach(el => {
        if (el.id === 'outcome') return;
        el.disabled = !on;
    });
}

outcome.addEventListener('change', function() {
    const v = this.value;
    blockSuccess.classList.toggle('d-none', v !== 'success');
    blockFail.classList.toggle('d-none', v !== 'fail');
    setBlockEnabled(blockSuccess, v === 'success');
    setBlockEnabled(blockFail, v === 'fail');
    btnSubmit.disabled = v === '';
    btnSubmit.className = 'btn w-100 mt-2 ' + (v === 'fail' ? 'btn-danger' : 'btn-success');
    btnSubmit.textContent = v === 'fail' ? 'Submit — Giao thất bại' : 'Submit — Giao thành công';
});

document.getElementById('completeForm').addEventListener('submit', function(e) {
    const v = outcome.value;
    if (!v) { e.preventDefault(); alert('Chọn kết quả giao hàng trước.'); return; }
    actionField.value = v;
    if (v === 'success') {
        const name = document.getElementById('recipient_name').value.trim();
        const note = document.getElementById('note').value.trim();
        const cam = document.querySelector('input[name="proof_images[]"]').files.length;
        const gal = document.querySelector('input[name="proof_gallery[]"]').files.length;
        const sigCam = document.getElementById('signature_image').files.length;
        const sigGal = document.getElementById('signature_gallery').files.length;
        if (!name) { e.preventDefault(); alert('Nhập tên người nhận.'); return; }
        if (cam + gal === 0 && existingImgs === 0) { e.preventDefault(); alert('Chụp/chọn ít nhất 1 ảnh phát.'); return; }
        if (sigCam + sigGal === 0 && !existingSig) { e.preventDefault(); alert('Chụp/chọn ảnh chữ ký.'); return; }
        if (!note) { e.preventDefault(); alert('Nhập ghi chú phát hàng.'); return; }
    } else {
        if (!document.getElementById('fail_reason').value) {
            e.preventDefault(); alert('Chọn lý do giao thất bại.');
        }
        const extra = document.getElementById('fail_note_extra').value.trim();
        if (extra) {
            let noteEl = document.getElementById('note');
            if (!noteEl) {
                noteEl = document.createElement('input');
                noteEl.type = 'hidden'; noteEl.name = 'note'; noteEl.id = 'note';
                this.appendChild(noteEl);
            }
            noteEl.value = extra;
        }
    }
});
</script>

</div>

</div>


</body>
</html>