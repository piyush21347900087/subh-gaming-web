<?php require "includes/db.php"; $db = db(); 
$res = $db->query("SELECT market_name, is_published, status FROM markets WHERE is_published = 0"); 
while($row = $res->fetch_assoc()) { echo "UNPUBLISHED REGULAR: " . $row["market_name"] . "\n"; }
$res = $db->query("SELECT market_name, is_published, status FROM gali_markets WHERE is_published = 0"); 
if($res) { while($row = $res->fetch_assoc()) { echo "UNPUBLISHED GALI: " . $row["market_name"] . "\n"; } }
?>
