<?php
require_once 'includes/db.php';
$res = $db->query("SHOW COLUMNS FROM deposit LIKE 'bonus_amount'");
if($res && $res->num_rows > 0) {
    echo "Column exists";
} else {
    echo "Column does NOT exist";
}
