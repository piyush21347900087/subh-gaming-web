<?php
require_once 'includes/db.php';
$db = db();
$res = $db->query("DESCRIBE admin_payment_details");
$cols = [];
while($row = $res->fetch_assoc()) $cols[] = $row['Field'];
echo "Columns: " . implode(", ", $cols) . "\n";

$res = $db->query("SELECT id FROM admin_payment_details");
$ids = [];
while($row = $res->fetch_assoc()) $ids[] = $row['id'];
echo "IDs: " . implode(", ", $ids) . "\n";
