<?php
session_start();
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) exit;

$id = intval($_GET['id']);
$order = $conn->query("SELECT * FROM emslss_orders WHERE id = $id")->fetch_assoc();

if (!$order) die("Order not found");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Scan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-3">

<h4>📦 Scan: <?= $order['ems_code'] ?></h4>

<div id="reader" style="width:100%"></div>

<form method="POST" action="shipper_complete.php">
    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
    <input type="hidden" name="scanned_code" id="scanned_code">
    <input type="hidden" name="lat" id="lat">
    <input type="hidden" name="lng" id="lng">

    <button class="btn btn-success mt-3 w-100">Confirm</button>
</form>

<script>
function onScanSuccess(decodedText) {
    document.getElementById('scanned_code').value = decodedText;
    alert("Scanned: " + decodedText);
}

let html5QrcodeScanner = new Html5QrcodeScanner(
    "reader", { fps: 10, qrbox: 250 }
);
html5QrcodeScanner.render(onScanSuccess);

// GPS
navigator.geolocation.getCurrentPosition(function(pos){
    document.getElementById('lat').value = pos.coords.latitude;
    document.getElementById('lng').value = pos.coords.longitude;
});
</script>

</body>
</html>