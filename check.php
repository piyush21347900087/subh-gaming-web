<?php
require 'includes/db.php';
$db = db();
$today = date('Y-m-d');
$res = $db->query("SELECT * FROM market_result WHERE result_date = '$today'");
if (!$res) {
    echo "Query Error: " . $db->error;
} else {
    echo "Results for $today:\n";
    while($r = $res->fetch_assoc()) {
        print_r($r);
    }
}
?>
