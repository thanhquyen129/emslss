<?php
ob_start();
include '../config/auth.php';
include '../config/db.php';

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    die("Invalid order");
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("
SELECT * FROM emslss_orders
WHERE id=?
LIMIT 1
");

$stmt->bind_param("i",$id);
$stmt->execute();

$result = $stmt->get_result();

if($result->num_rows==0){
    die("Order not found");
}

$order = $result->fetch_assoc();

$message='';

if($_SERVER['REQUEST_METHOD']=='POST'){

    $barcode = trim($_POST['barcode']);

    if($barcode == $order['ems_code']){

        $stmt = $conn->prepare("
        UPDATE emslss_orders
        SET status='picked_up'
        WHERE id=?
        ");

        $stmt->bind_param("i",$id);
        $stmt->execute();

        $note='Pickup confirmed';

        $stmt2 = $conn->prepare("
        INSERT INTO emslss_tracking(order_id,status,note,created_by)
        VALUES(?,?,?,?)
        ");

        $status='picked_up';
        $uid=$_SESSION['user_id'];

        $stmt2->bind_param("issi",$id,$status,$note,$uid);
        $stmt2->execute();

        $message='Pickup confirmed successfully';

    } else {

        $message='Barcode not match EMS code';

    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Pickup Scan</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<meta name="viewport" content="width=device-width, initial-scale=1">

<script src="https://unpkg.com/html5-qrcode"></script>
</head>

<body>

<div class="container mt-3">

<h4>Pickup Scan</h4>

<div class="card p-3 mb-3">

<p><strong>EMS:</strong> <?= htmlspecialchars($order['ems_code']) ?></p>
<p><strong>Bưu cục:</strong> <?= htmlspecialchars($order['post_office']) ?></p>
<p><strong>Người giữ thư:</strong> <?= htmlspecialchars($order['holder_name']) ?></p>
<p><strong>Phone:</strong>
<a href="tel:<?= $order['holder_phone'] ?>">
<?= htmlspecialchars($order['holder_phone']) ?>
</a>
</p>

</div>

<?php if($message!=''){ ?>

<div class="alert alert-info">
<?= $message ?>
</div>

<?php } ?>

<div class="card p-3 mb-3">

<h5>Camera Scan</h5>

<div id="reader" style="width:100%;"></div>

</div>

<form method="post">

<div class="mb-3">

<label>Barcode / EMS Code</label>

<input id="barcode"
name="barcode"
class="form-control"
required>

</div>

<button class="btn btn-success w-100">
Confirm Pickup
</button>

</form>

<a href="pickup_dashboard.php" class="btn btn-secondary mt-3 w-100">
Back Dashboard
</a>

</div>

<script>

function onScanSuccess(decodedText){

    document.getElementById('barcode').value = decodedText;

}

new Html5Qrcode("reader").start(
    { facingMode:"environment" },
    {
        fps:10,
        qrbox:250
    },
    onScanSuccess
);

</script>

</body>
</html>