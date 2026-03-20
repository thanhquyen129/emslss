<?php
require_once 'bootstrap.php';

safeExecute(function(){

    global $conn;

    $res = $conn->query("
        SELECT id,source,payload
        FROM emslss_api_logs
        WHERE response LIKE '%error%'
        ORDER BY id ASC
        LIMIT 20
    ");

    while($row = $res->fetch_assoc()){

        $url = '';

        if($row['source'] === 'CALLBACK_PICKUP'){
            $url = 'https://ems-api.example.com/pickup';
        }

        if($row['source'] === 'CALLBACK_DELIVERY'){
            $url = 'https://ems-api.example.com/delivery';
        }

        if(!$url){
            continue;
        }

        $ch = curl_init($url);

        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => $row['payload'],
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);

        if(curl_errno($ch)){
            $response = curl_error($ch);
        }

        curl_close($ch);

        apiLog(
            'CALLBACK_RETRY',
            $row['payload'],
            $response
        );
    }

    responseSuccess([
        'message' => 'Retry completed'
    ]);
});
?>