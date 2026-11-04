<?php require "includes/db.php"; $res = db()->query("SELECT CURDATE()"); $r = $res->fetch_row(); echo $r[0]; ?>
