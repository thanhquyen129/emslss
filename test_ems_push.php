<?php

$url = "http://tincod.com/api/ems_receive.php";

function randomOrder() {
    $postOffices = ["BC Tân Bình","BC Quận 1","BC Hà Đông","BC Hải Châu"];
    $addresses = [
        "1 Cộng Hòa, TP HCM",
        "123 Lê Lợi, Quận 1, TP HCM",
        "12 Nguyễn Trãi, Hà Nội",
        "88 Trần Phú, Đà Nẵng"
    ];
    $names = ["Nguyễn Văn A","Trần Văn B","Lê Thị C","Phạm Văn D","Hoàng Văn E"];
    $phones = ["0901111111","0902222222","0903333333","0904444444","0905555555"];
    $cargo = ["Documents","Contract Papers","Small Parcel","Electronics"];

    return [
        "ems_code" => "EE".rand(100000000,999999999)."VN",
        "post_office_name" => $postOffices[array_rand($postOffices)],
        "post_office_address" => $addresses[array_rand($addresses)],
        "holder_name" => $names[array_rand($names)],
        "holder_phone" => $phones[array_rand($phones)],
        "sender_name" => $names[array_rand($names)],
        "sender_phone" => $phones[array_rand($phones)],
        "sender_address" => $addresses[array_rand($addresses)],
        "receiver_name" => $names[array_rand($names)],
        "receiver_phone" => $phones[array_rand($phones)],
        "receiver_address" => $addresses[array_rand($addresses)],
        "weight" => rand(1,5).".".rand(0,9),
        "cargo_type" => $cargo[array_rand($cargo)],
        "service_type" => "door_to_door"
    ];
}

function pushOrder($url, $data) {
    $payload = json_encode($data);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: EMSLSS2026KEY'
        ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

$msg = '';

if(isset($_POST['bulk'])){
    $count = intval($_POST['bulk']);
    for($i=1;$i<=$count;$i++){
        pushOrder($url, randomOrder());
    }
    $msg = "✅ Đã push $count đơn";
}

if(isset($_POST['single'])){
    $data = [
        "ems_code"=>$_POST['ems_code'],
        "post_office_name"=>$_POST['post_office_name'],
        "post_office_address"=>$_POST['post_office_address'],
        "holder_name"=>$_POST['holder_name'],
        "holder_phone"=>$_POST['holder_phone'],
        "sender_name"=>$_POST['sender_name'],
        "sender_phone"=>$_POST['sender_phone'],
        "sender_address"=>$_POST['sender_address'],
        "receiver_name"=>$_POST['receiver_name'],
        "receiver_phone"=>$_POST['receiver_phone'],
        "receiver_address"=>$_POST['receiver_address'],
        "weight"=>$_POST['weight'],
        "cargo_type"=>$_POST['cargo_type'],
        "service_type"=>$_POST['service_type']
    ];

    $msg = pushOrder($url,$data);
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>EMS Push Console</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-4">

<div class="card shadow-lg border-0 rounded-4 p-4">

<h3 class="mb-4">📦 EMS Push Test Console</h3>

<?php if($msg){ ?>
<div class="alert alert-success"><?= $msg ?></div>
<?php } ?>

<h5>Bulk Push</h5>
<form method="post" class="mb-4">
<button class="btn btn-primary" name="bulk" value="10">10 đơn</button>
<button class="btn btn-secondary" name="bulk" value="20">20 đơn</button>
<button class="btn btn-warning" name="bulk" value="30">30 đơn</button>
<button class="btn btn-danger" name="bulk" value="50">50 đơn</button>
<button class="btn btn-dark" name="bulk" value="100">100 đơn</button>
</form>

<hr>

<h5>Single Push</h5>

<form method="post">

<div class="row g-3">

<div class="col-md-6">
<label>Mã EMS</label>
<input name="ems_code" class="form-control" value="EE<?= rand(100000000,999999999) ?>VN">
</div>

<div class="col-md-6">
<label>Bưu cục</label>
<input name="post_office_name" class="form-control" value="BC Tân Bình">
</div>

<div class="col-md-12">
<label>Địa chỉ bưu cục</label>
<input name="post_office_address" class="form-control" value="1 Cộng Hòa, TP HCM">
</div>

<div class="col-md-6">
<label>Người giữ thư</label>
<input name="holder_name" class="form-control" value="Chị Thảo">
</div>

<div class="col-md-6">
<label>SĐT người giữ thư</label>
<input name="holder_phone" class="form-control" value="0901234567">
</div>

<div class="col-md-6">
<label>Người gửi</label>
<input name="sender_name" class="form-control" value="Nguyễn Văn A">
</div>

<div class="col-md-6">
<label>SĐT người gửi</label>
<input name="sender_phone" class="form-control" value="0901111111">
</div>

<div class="col-md-12">
<label>Địa chỉ người gửi</label>
<input name="sender_address" class="form-control" value="Hà Nội">
</div>

<div class="col-md-6">
<label>Người nhận</label>
<input name="receiver_name" class="form-control" value="Trần Văn B">
</div>

<div class="col-md-6">
<label>SĐT người nhận</label>
<input name="receiver_phone" class="form-control" value="0902222222">
</div>

<div class="col-md-12">
<label>Địa chỉ người nhận</label>
<input name="receiver_address" class="form-control" value="TP HCM">
</div>

<div class="col-md-4">
<label>Trọng lượng</label>
<input name="weight" class="form-control" value="1.2">
</div>

<div class="col-md-4">
<label>Loại hàng</label>
<input name="cargo_type" class="form-control" value="Documents">
</div>

<div class="col-md-4">
<label>Dịch vụ</label>
<select name="service_type" class="form-select">
<option value="door_to_door">door_to_door</option>
<option value="door_to_hub">door_to_hub</option>
<option value="hub_to_door">hub_to_door</option>
</select>
</div>

</div>

<button class="btn btn-success mt-4" name="single">Push 1 đơn</button>

</form>

</div>
</div>
</body>
</html>
