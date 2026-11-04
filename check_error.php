<?php require "includes/db.php"; try { echo (int) db()->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetch_row()[0]; } catch(Exception $e) { echo $e->getMessage(); } ?>
