<?php
require 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/db.php';
$db = db();
$q = $db->query("SELECT * FROM notices");
while ($r = $q->fetch_assoc()) {
    var_dump($r);
}
