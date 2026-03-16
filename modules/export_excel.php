<?php
include '../config/db.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=orders.xls");

$q=$conn->query("SELECT * FROM emslss_orders");

echo "EMS\tPickup\tStatus\n";

while($r=$q->fetch_assoc()){
 echo $r['ems_code']."\t".$r['pickup_name']."\t".$r['status']."\n";
}
?>