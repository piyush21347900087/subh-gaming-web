<?php
require_once __DIR__ . '/includes/db.php';
$db = db();

echo "Main Markets:\n";
$res = $db->query("SELECT market_name FROM markets");
while ($row = $res->fetch_assoc())
    echo "- " . $row['market_name'] . "\n";


?>

