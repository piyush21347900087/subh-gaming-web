<?php require "includes/db.php"; $db=db(); $res=$db->query("SELECT * FROM users WHERE user_id='MK01'"); var_dump($res->fetch_assoc()); ?>
