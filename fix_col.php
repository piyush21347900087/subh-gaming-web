<?php
require_once 'includes/db.php';
$db = db();
$res = $db->query("SHOW COLUMNS FROM deposit LIKE 'bonus_amount'");
if($res && $res->num_rows > 0) {
    echo "Column bonus_amount exists in deposit table.\n";
} else {
    echo "Column bonus_amount does NOT exist in deposit table. Adding it...\n";
    $db->query("ALTER TABLE deposit ADD COLUMN bonus_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
    echo "Added bonus_amount column.\n";
}
