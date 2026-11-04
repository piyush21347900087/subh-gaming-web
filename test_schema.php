<?php require "includes/db.php"; $db=db(); $res=$db->query("SHOW CREATE TABLE chat_messages"); var_dump($res->fetch_assoc()); ?>
