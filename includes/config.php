<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'danangit_vmlbooking');
define('DB_USER', 'danangit_vmlbooking');
define('DB_PASS', 'PCSCSPQfJte$j7HW');

try {
    // Sử dụng các hằng số đã define để kết nối PDO
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Kết nối thất bại: " . $e->getMessage());
}
?>