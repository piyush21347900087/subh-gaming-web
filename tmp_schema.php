<?php
require 'includes/db.php';
$db = db();
$res = $db->query('SHOW CREATE TABLE users');
$row = $res->fetch_row();
echo $row[1];
