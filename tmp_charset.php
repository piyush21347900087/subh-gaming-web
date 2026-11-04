<?php
require 'includes/db.php';
$db = db();
$res = $db->query("SHOW VARIABLES LIKE 'character_set%'");
while($row = $res->fetch_assoc()){
  echo $row['Variable_name'] . ' = ' . $row['Value'] . "\n";
}
