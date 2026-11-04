<?php
require_once 'includes/db.php';
$db = db();
$res = $db->query("SHOW COLUMNS FROM upi_numbers");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
