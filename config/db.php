<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
$conn = new mysqli("localhost","wamvietn_tincode","p6]L@7iTS5","wamvietn_tincode");

if($conn->connect_error){
 die("Database connection failed");
}

$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+07:00'");
$conn->query("SET NAMES utf8mb4");
$conn->query("SET CHARACTER SET utf8mb4");
$conn->query("SET collation_connection = utf8mb4_unicode_ci");
?>