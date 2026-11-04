<?php require "includes/db.php"; $db = db(); 
$res = $db->query("SELECT amount, status, method FROM withdrawal WHERE user_id LIKE '%MK01%' AND LOWER(status) = 'pending'");
if($res) { while($row = $res->fetch_assoc()) { echo json_encode($row) . "\n"; } }
?>
