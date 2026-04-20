<?php
$_system = require __DIR__ . '/system.php';
$dbConfig = $_system['db'];
$envConfig = $_system['env'];

error_reporting(E_ALL);
$showErrors = !empty($envConfig['display_errors']) ? '1' : '0';
ini_set('display_errors', $showErrors);
ini_set('display_startup_errors', $showErrors);

date_default_timezone_set($envConfig['timezone']);
$conn = new mysqli(
    $dbConfig['host'],
    $dbConfig['user'],
    $dbConfig['pass'],
    $dbConfig['name']
);

if($conn->connect_error){
 die("Database connection failed");
}

$conn->set_charset($dbConfig['charset']);
$conn->query("SET time_zone = '+07:00'");
$conn->query("SET NAMES utf8mb4");
$conn->query("SET CHARACTER SET utf8mb4");
$conn->query("SET collation_connection = utf8mb4_unicode_ci");
?>