<?php
include '../config/db.php';

$q=$conn->query("
SELECT * FROM emslss_orders
WHERE callback_status='pending'
AND callback_retry<5
");

while($r=$q->fetch_assoc()){

 $payload=json_encode([
   "ems_code"=>$r['ems_code'],
   "status"=>$r['status']
 ]);

 $ch=curl_init("EMS_CALLBACK_URL");

 curl_setopt($ch,CURLOPT_POST,1);
 curl_setopt($ch,CURLOPT_POSTFIELDS,$payload);
 curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

 $res=curl_exec($ch);

 if($res){
   $conn->query("
   UPDATE emslss_orders
   SET callback_status='success'
   WHERE id=".$r['id']);
 }else{
   $conn->query("
   UPDATE emslss_orders
   SET callback_retry=callback_retry+1
   WHERE id=".$r['id']);
 }

}
?>