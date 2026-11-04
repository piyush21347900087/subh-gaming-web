<?php require "includes/db.php"; $db = db(); echo $db->query("SHOW CREATE TABLE markets")->fetch_row()[1]; ?>
