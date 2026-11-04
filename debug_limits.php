<?php
require_once 'includes/db.php';
$db = db();
$res = $db->query("SELECT min_deposit, min_withdraw, min_bid FROM admin_payment_details WHERE id=1");
if ($res && $row = $res->fetch_assoc()) {
    echo json_encode($row);
} else {
    echo "No row found for ID=1";
}
