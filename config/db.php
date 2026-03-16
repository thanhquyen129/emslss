<?php
$conn = new mysqli("localhost","wamvietn_tincode","p6]L@7iTS5","wamvietn_tincode");

if($conn->connect_error){
 die("Database connection failed");
}

$conn->set_charset("utf8mb4");
?>