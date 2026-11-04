<?php require "includes/db.php"; $res = db()->query("SELECT user_id, created_at FROM users WHERE DATE(created_at) = CURDATE()"); while($r = $res->fetch_assoc()) echo json_encode($r)."\n"; ?>
