<?php require "includes/db.php"; $res = db()->query("SELECT DATABASE()"); $r = $res->fetch_row(); echo "Current DB: " . $r[0] . "\n"; ?>
