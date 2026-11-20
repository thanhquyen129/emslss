<?php
session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

switch ($_SESSION['role']) {
    case 'admin':
    case 'dispatcher':
        header("Location: admin_dashboard_realtime.php");
        break;

    case 'operation':
        header("Location: /modules/operation/dashboard.php");
        break;

    case 'shipper':
        header("Location: pickup_dashboard.php");
        break;

    case 'ems':
        header("Location: /modules/ems/dashboard.php");
        break;

    default:
        header("Location: ../login.php");
        break;
}
exit;
