<?php

set_time_limit(0);

$logs = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $pickupPoints = [
        ["Chị Thảo", "0903123456", "Bưu cục Tân Bình, 123 Cộng Hòa, TP.HCM"],
        ["Anh Minh", "0904555666", "Bưu cục Quận 1, 12 Hai Bà Trưng, TP.HCM"],
        ["Chị Lan", "0909777888", "Bưu cục Bình Thạnh, 45 Xô Viết Nghệ Tĩnh, TP.HCM"],
        ["Anh Phúc", "0902888999", "Bưu cục Phú Nhuận, 88 Nguyễn Văn Trỗi, TP.HCM"],
        ["Chị Hà", "0906111222", "Bưu cục Gò Vấp, 201 Quang Trung, TP.HCM"]
    ];

    $receivers = [
        ["ACB CN Quận 1", "442 Nguyễn Thị Minh Khai, Q1"],
        ["VCB CN Tân Bình", "12 Hoàng Văn Thụ, Tân Bình"],
        ["BIDV CN Bình Thạnh", "78 Điện Biên Phủ, Bình Thạnh"],
        ["Techcombank CN Phú Nhuận", "201 Phan Xích Long, Phú Nhuận"],
        ["MB Bank CN Gò Vấp", "99 Nguyễn Oanh, Gò Vấp"]
    ];

    function fakeEMSCode() {
        return "EE" . rand(100000000,999999999) . "VN";
    }

    function fakePhone() {
        return "09" . rand(10000000,99999999);
    }

    for ($i=1; $i<=50; $i++) {

        $pickup = $pickupPoints[array_rand($pickupPoints)];
        $receiver = $receivers[array_rand($receivers)];

        $payload = [
            "ems_code" => fakeEMSCode(),
            "service_type" => "hoa_toc",
            "pickup_name" => $pickup[0],
            "pickup_phone" => $pickup[1],
            "pickup_address" => $pickup[2],
            "receiver_name" => $receiver[0],
            "receiver_phone" => fakePhone(),
            "receiver_address" => $receiver[1],
            "note" => "Hồ sơ ngân hàng giao gấp SLA 2h"
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $url = "https://tincod.com/a1/sieutoc/api/ems_receive.php"; // sửa domain thật

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json)
        ]);

        $response = curl_exec($ch);

        curl_close($ch);

        $logs[] = [
            "ems_code" => $payload['ems_code'],
            "pickup" => $payload['pickup_name'],
            "receiver" => $payload['receiver_name'],
            "response" => $response
        ];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>EMS Bulk Push 50 Orders</title>
<style>
body{
 font-family:Arial;
 max-width:1000px;
 margin:auto;
 padding:20px;
}
button{
 padding:15px 25px;
 font-size:16px;
}
table{
 width:100%;
 border-collapse:collapse;
 margin-top:20px;
}
td,th{
 border:1px solid #ddd;
 padding:8px;
}
</style>
</head>
<body>

<h2>EMS Bulk Push 50 Orders</h2>

<form method="post">
<button type="submit">Push 50 đơn ngay</button>
</form>

<?php if($logs){ ?>

<table>
<tr>
<th>EMS Code</th>
<th>Pickup</th>
<th>Receiver</th>
<th>Response</th>
</tr>

<?php foreach($logs as $log){ ?>
<tr>
<td><?= $log['ems_code'] ?></td>
<td><?= $log['pickup'] ?></td>
<td><?= $log['receiver'] ?></td>
<td><?= htmlspecialchars($log['response']) ?></td>
</tr>
<?php } ?>

</table>

<?php } ?>

</body>
</html>