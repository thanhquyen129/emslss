<?php

$host = "localhost";
$user = "wamvietn_tincode";
$pass = "p6]L@7iTS5";
$dbname = "wamvietn_tincode";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$tables = [];
$result = $conn->query("SHOW TABLES");

while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$sqlScript = "";

foreach ($tables as $table) {

    $result = $conn->query("SHOW CREATE TABLE $table");
    $row = $result->fetch_row();

    $sqlScript .= "\n\n" . $row[1] . ";\n\n";

    $result = $conn->query("SELECT * FROM $table");

    while ($row = $result->fetch_assoc()) {
        $columns = array_keys($row);
        $values = array_values($row);

        $values = array_map(function($value) use ($conn) {
            return "'" . $conn->real_escape_string($value) . "'";
        }, $values);

        $sqlScript .= "INSERT INTO `$table` (`" . implode("`,`", $columns) . "`) VALUES (" . implode(",", $values) . ");\n";
    }
}

$backup_file = "backup_" . date("Y-m-d_H-i-s") . ".sql";

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename=' . $backup_file);

echo $sqlScript;

$conn->close();
?>