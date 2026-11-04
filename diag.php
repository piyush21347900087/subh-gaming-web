<?php
require_once 'includes/db.php';
$db = db();
$res = $db->query("SELECT MAX(result_date) as max_date FROM market_result");
$row = $res->fetch_assoc();
echo "MAX DATE: " . $row['max_date'] . PHP_EOL;

$res = $db->query("SELECT COUNT(*) as cnt FROM market_result WHERE result_date = '" . $row['max_date'] . "'");
$row2 = $res->fetch_assoc();
echo "RESULTS ON THAT DATE: " . $row2['cnt'] . PHP_EOL;

$res = $db->query("SELECT COUNT(*) as cnt FROM markets WHERE is_published = 1");
$row3 = $res->fetch_assoc();
echo "ACTIVE MARKETS: " . $row3['cnt'] . PHP_EOL;
