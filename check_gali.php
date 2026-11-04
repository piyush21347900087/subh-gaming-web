<?php require "includes/db.php"; $db = db(); $db->query("SELECT market_name, is_published FROM gali_markets"); echo $db->error; ?>
