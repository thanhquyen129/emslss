<?php
require_once 'bootstrap.php';

function sendPickupCallback($order_id)
{
    global $conn;

    $order = $conn->query("
        SELECT ems_code
        FROM emslss_orders
        WHERE id=$order_id
    ")->fetch_assoc();

    $data = [
        'ems_code'=>$order['ems_code'],
        'status'=>'picked_up',
        'time'=>date('Y-m-d H:i:s')
    ];

    $payload = json_encode($data, JSON_UNESCAPED_UNICODE);

    $url = "https://ems-api.example.com/pickup";

    $ch = curl_init($url);

    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>[
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_TIMEOUT=>10
    ]);

    $response = curl_exec($ch);

    if(curl_errno($ch)){
        $response = curl_error($ch);
    }

    curl_close($ch);

    apiLog('CALLBACK_PICKUP',$payload,$response);
}
?>