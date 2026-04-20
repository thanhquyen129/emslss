<?php
if(session_status() == PHP_SESSION_NONE){
    session_start();
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="admin_dashboard_realtime.php">
            EMS-LSS Admin
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto">

                <li class="nav-item">
                    <a class="nav-link" href="admin_dashboard_realtime.php">Dashboard</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Orders</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="admin_users.php">Users</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="admin_roles.php">Roles</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="admin_reports.php">Reports</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="callback_monitor.php">Callback</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="API_logs.php">API Logs</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/modules/operation/dashboard.php">Operation</a>
                </li>

            </ul>

            <span class="navbar-text text-white me-3">
                Xin chào <?= $_SESSION['full_name'] ?? 'Admin' ?>
            </span>

            <a href="/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </div>
</nav>

<div style="height:70px;"></div>