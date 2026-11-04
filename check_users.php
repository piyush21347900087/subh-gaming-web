<?php require "includes/db.php"; $db=db(); $res=$db->query("SELECT user_id, name, mobile_no FROM users LIMIT 10"); while($row=$res->fetch_assoc()){ var_dump($row); } ?>
