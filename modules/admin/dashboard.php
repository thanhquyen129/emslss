<?php ini_set('display_errors', '1');
session_start();
require '../../config/db.php';
include '../../templates/admin_topbar.php';
if(!isset($_SESSION['user_id']))
{
	 header("Location:../modules/login.php");
	 exit;
}
else
{
	switch($_SESSION['role'])
	{
		case 'admin':
		case 'dispatcher':
		header("Location: admin_dashboard_realtime.php");
		break;

		case 'operation':
		header("Location: operation_dashboard.php");
		break;

		case 'shipper':
		header("Location: pickup_dashboard.php");
		break;
	}
}
exit;
?>