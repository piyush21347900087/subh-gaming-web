<?php
require 'includes/db.php';
$db = db();
$res = $db->query('SHOW TABLES');
while($r = $res->fetch_array()) {
    echo $r[0]."\n";
}
?>
