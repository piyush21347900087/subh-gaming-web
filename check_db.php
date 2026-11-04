<?php require "includes/db.php"; $db = db(); echo "DB: " . $db->query("SELECT DATABASE()")->fetch_row()[0] . "\n"; ?>
