<?php require "includes/db.php"; $db = db(); 
$res = $db->query("SELECT id, user_id, name, wallet_balance FROM users WHERE user_id LIKE '%MK%' LIMIT 5");
while($row = $res->fetch_assoc()) { echo json_encode($row) . "\n"; }
?>
