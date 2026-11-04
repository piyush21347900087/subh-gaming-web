<?php
require 'includes/db.php';
$db = db();
$res = $db->query("SELECT count(*) as count FROM market_result");
if (!$res) {
    echo "Error: " . $db->error;
} else {
    $row = $res->fetch_assoc();
    echo "Count: " . $row['count'] . "\n";
}
?>
