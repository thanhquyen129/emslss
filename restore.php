<?php
set_time_limit(0);
ini_set('memory_limit', '512M');

// ===== CONFIG =====
$dbHost = "localhost";
$dbUser = "wamvietn_lss";
$dbPass = "Xcs~CpH$5i{EwwSk";
$dbName = "wamvietn_lss";

$sqlFile = "backup.sql"; // file backup

// ===== CONNECT =====
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("❌ Connect failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// ===== CHECK FILE =====
if (!file_exists($sqlFile)) {
    die("❌ File SQL không tồn tại");
}

// ===== RESTORE =====
$templine = '';
$lines = file($sqlFile);

echo "🚀 Bắt đầu restore...<br>";

foreach ($lines as $line) {

    // Bỏ comment
    if (substr($line, 0, 2) == '--' || trim($line) == '') {
        continue;
    }

    $templine .= $line;

    // Nếu kết thúc câu lệnh SQL
    if (substr(trim($line), -1, 1) == ';') {
        if (!$conn->query($templine)) {
            echo "❌ Lỗi query: " . $conn->error . "<br>";
        }
        $templine = '';
    }
}

echo "✅ Restore hoàn tất! 🎉";

$conn->close();
?>