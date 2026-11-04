<?php require "includes/db.php"; $db = db(); $res = $db->query("SELECT market_name, is_published, status FROM markets"); while($row = $res->fetch_assoc()) echo json_encode($row) . "\n"; ?>
