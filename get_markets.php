<?php
require 'includes/db.php';
$db = db();
$res = $db->query('SELECT market_name FROM markets');
while ($row = $res->fetch_assoc()) {
    echo $row['market_name'] . "\n";
}
