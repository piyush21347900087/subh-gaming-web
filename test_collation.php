<?php require "includes/db.php"; $db = db(); 
$res = $db->query("SHOW FULL COLUMNS FROM users LIKE 'user_id'");
$row = $res->fetch_assoc(); echo "users.user_id: " . $row["Collation"] . "\n";
$res = $db->query("SHOW FULL COLUMNS FROM deposit LIKE 'user_id'");
$row = $res->fetch_assoc(); echo "deposit.user_id: " . $row["Collation"] . "\n";
?>
