<?php require "includes/db.php"; $db = db(); $res = $db->query("SELECT sort_order FROM markets LIMIT 1"); if(!$res) { echo "ERROR: " . $db->error; } else { echo "sort_order exists!"; } ?>
