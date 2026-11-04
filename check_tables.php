<?php require "includes/db.php"; $db = db(); $res = $db->query("SHOW TABLES LIKE '%market%'"); while($row = $res->fetch_row()) echo $row[0] . "\n"; ?>
