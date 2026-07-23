<?php
$mysqli = new mysqli("localhost", "root", "", "backup_gudang");
if ($mysqli->connect_error) {
    die("CONNECT_FAIL: " . $mysqli->connect_error);
}
$result = $mysqli->query("SELECT COUNT(*) AS total FROM tbl_user");
$row = $result->fetch_assoc();
echo "CONNECTED\n";
echo "USER_COUNT=" . $row['total'] . "\n";
$mysqli->close();
