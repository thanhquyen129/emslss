<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current = basename($_SERVER['PHP_SELF'] ?? '');
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="dashboard.php">EMS Portal</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#emsNavbar"
                aria-controls="emsNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="emsNavbar">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link<?= $current === 'dashboard.php' ? ' active' : '' ?>" href="dashboard.php">Đơn hàng</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $current === 'tracking.php' ? ' active' : '' ?>" href="tracking.php">Tra cứu</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $current === 'api_logs.php' ? ' active' : '' ?>" href="api_logs.php">API Logs</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $current === 'callbacks.php' ? ' active' : '' ?>" href="callbacks.php">Callback</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/modules/admin/order_export.php">Kết xuất</a>
                </li>
            </ul>

            <span class="navbar-text text-white me-3">
                <?= htmlspecialchars($_SESSION['full_name'] ?? 'EMS') ?>
            </span>
            <a href="/modules/change_password.php" class="btn btn-sm btn-outline-light me-2">Đổi MK</a>
            <a href="/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </div>
</nav>
<div style="height:70px;"></div>
<script>
if (!window.bootstrap) {
    document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"><\/script>');
}
</script>
