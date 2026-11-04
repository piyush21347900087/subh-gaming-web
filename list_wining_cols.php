<?php
require_once 'includes/db.php';
$db = db();
$res = $db->query('DESC wining');
echo "Columns in wining:\n";
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
