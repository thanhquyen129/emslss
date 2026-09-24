<?php
/** @var array $imageRows each: id, image_path (url), created_at */
/** @var int $order_id */
/** @var bool $can_delete default true */
$can_delete = $can_delete ?? true;
?>
<div class="row g-2" id="imageGallery">
<?php if (empty($imageRows)): ?>
    <div class="col-12 text-muted">Chưa có ảnh</div>
<?php else: ?>
    <?php foreach ($imageRows as $img): ?>
    <div class="col-6 col-md-4 image-gallery-item" data-image-id="<?= (int) $img['id'] ?>">
        <div class="position-relative">
            <img src="<?= htmlspecialchars($img['image_path']) ?>"
                 class="img-fluid rounded border w-100 shipper-gallery-img"
                 style="height:140px;object-fit:cover;cursor:pointer"
                 data-full="<?= htmlspecialchars($img['image_path']) ?>"
                 alt="proof">
            <?php if ($can_delete): ?>
            <button type="button"
                    class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 btn-delete-image"
                    data-image-id="<?= (int) $img['id'] ?>"
                    title="Xóa ảnh">×</button>
            <?php endif; ?>
        </div>
        <small class="text-muted d-block mt-1"><?= htmlspecialchars($img['created_at'] ?? '') ?></small>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>
<script>
(function(){
    const orderId = <?= (int) $order_id ?>;
    document.querySelectorAll('.btn-delete-image').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            if (!confirm('Xóa ảnh này? File trên server cũng sẽ bị xóa.')) return;
            const imageId = btn.dataset.imageId;
            const fd = new FormData();
            fd.append('image_id', imageId);
            fd.append('order_id', orderId);
            const res = await fetch('delete_image.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                btn.closest('.image-gallery-item')?.remove();
            } else {
                alert(data.error || 'Không xóa được ảnh');
            }
        });
    });
    document.querySelectorAll('.shipper-gallery-img').forEach(img => {
        img.addEventListener('click', () => window.open(img.dataset.full, '_blank'));
    });
})();
</script>
