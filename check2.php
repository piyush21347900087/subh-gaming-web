<?php
require 'includes/db.php';
$db = db();
$res = $db->query("SELECT * FROM market_result ORDER BY declared_at DESC LIMIT 10");
while($r = $res->fetch_assoc()) {
    print_r($r);
}
?>
