<?php require "includes/db.php"; $res = db()->query("SELECT user_id, created_at FROM users"); while($r = $res->fetch_assoc()) { echo $r["user_id"] . " => " . $r["created_at"] . "\n"; } ?>
