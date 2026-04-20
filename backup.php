<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');

if (php_sapi_name() !== 'cli') {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        die('Unauthorized');
    }
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('Access denied');
    }
}

require_once __DIR__ . '/config/db.php';

function sqlValue($conn, $value)
{
    if ($value === null) {
        return "NULL";
    }
    return "'" . $conn->real_escape_string((string)$value) . "'";
}

$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$dbNameRes = $conn->query("SELECT DATABASE() AS dbname");
$dbNameRow = $dbNameRes->fetch_assoc();
$dbName = $dbNameRow['dbname'] ?? 'database';
$timestamp = date("Y-m-d_H-i-s");
$backupFile = "backup_full_" . $dbName . "_" . $timestamp . ".sql";

$sqlScript = [];
$sqlScript[] = "-- EMS-LSS full backup";
$sqlScript[] = "-- Database: " . $dbName;
$sqlScript[] = "-- Generated at: " . date('Y-m-d H:i:s');
$sqlScript[] = "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";";
$sqlScript[] = "SET time_zone = \"+07:00\";";
$sqlScript[] = "SET FOREIGN_KEY_CHECKS = 0;";
$sqlScript[] = "";

foreach ($tables as $table) {
    $safeTable = str_replace('`', '``', $table);
    $sqlScript[] = "-- --------------------------------------------------------";
    $sqlScript[] = "-- Structure for table `" . $safeTable . "`";
    $sqlScript[] = "DROP TABLE IF EXISTS `" . $safeTable . "`;";

    $createRes = $conn->query("SHOW CREATE TABLE `" . $safeTable . "`");
    $createRow = $createRes->fetch_row();
    $sqlScript[] = $createRow[1] . ";";
    $sqlScript[] = "";

    $dataRes = $conn->query("SELECT * FROM `" . $safeTable . "`");
    if ($dataRes->num_rows > 0) {
        $columns = [];
        while ($field = $dataRes->fetch_field()) {
            $columns[] = "`" . str_replace('`', '``', $field->name) . "`";
        }
        $columnList = implode(",", $columns);

        while ($row = $dataRes->fetch_assoc()) {
            $values = [];
            foreach ($row as $value) {
                $values[] = sqlValue($conn, $value);
            }
            $sqlScript[] = "INSERT INTO `" . $safeTable . "` (" . $columnList . ") VALUES (" . implode(",", $values) . ");";
        }
        $sqlScript[] = "";
    }
}

$sqlScript[] = "SET FOREIGN_KEY_CHECKS = 1;";
$output = implode("\n", $sqlScript) . "\n";

$mode = $_GET['mode'] ?? 'download';
if ($mode === 'save') {
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0775, true);
    }
    $fullPath = $backupDir . '/' . $backupFile;
    file_put_contents($fullPath, $output);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'file' => 'backups/' . $backupFile
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename=' . $backupFile);
echo $output;
exit;
?>