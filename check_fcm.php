<?php require "includes/db.php"; $res = db()->query("SELECT COUNT(*) FROM user_fcm_tokens"); echo $res ? $res->fetch_row()[0] : "Table missing or error: " . db()->error; ?>
