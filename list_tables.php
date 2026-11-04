<?php
require_once 'includes/db.php';
$db = db();
$res = $db->query("SHOW TABLES");
while ($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
