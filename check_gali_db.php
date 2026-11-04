<?php
require_once 'includes/db.php';
$db = db();
echo "--- GALI MARKETS ---\n";
$res = $db->query('DESCRIBE gali_markets');
while($r = $res->fetch_assoc()) { print_r($r); }

echo "--- GALI RESULT ---\n";
$res = $db->query('DESCRIBE gali_result');
while($r = $res->fetch_assoc()) { print_r($r); }
