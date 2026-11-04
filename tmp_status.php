<?php
require 'includes/db.php';
$db = db();
$res = $db->query('SELECT DISTINCT status FROM bank_kyc');
if ($res) {
    while($row = $res->fetch_row()) echo $row[0]."\n";
}
$res = $db->query('SELECT DISTINCT status FROM upi_kyc');
if ($res) {
    while($row = $res->fetch_row()) echo $row[0]."\n";
}
