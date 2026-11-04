<?php require "includes/db.php"; $db = db(); 
$res = $db->query("SELECT method, amount FROM withdrawal WHERE method LIKE '%bid%' LIMIT 5");
if($res) { while($row = $res->fetch_assoc()) { echo json_encode($row) . "\n"; } }
?>
