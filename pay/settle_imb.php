<?php
/**
 * IMB Payment Force-Settle Tool
 * USE: visit /pay/settle_imb.php?order_id=IMB20260416154946566&secret=imb2026
 * This manually marks a payment as Done and credits wallet
 */
if (($_GET['secret'] ?? '') !== 'imb2026') die('Unauthorized');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../deposit.php';

$orderId = trim($_GET['order_id'] ?? '');
if (!$orderId) die('order_id required');

$db = db();

// Show current state
$res = $db->query("SELECT * FROM deposit WHERE order_id = '$orderId' LIMIT 1");
$row = $res->fetch_assoc();
echo "<pre>Current DB Record:\n" . print_r($row, true) . "</pre>";

// Check deposit table columns
$cols = [];
$colRes = $db->query("SHOW COLUMNS FROM deposit");
while ($c = $colRes->fetch_assoc()) $cols[] = $c['Field'];
echo "<pre>Deposit Columns: " . implode(', ', $cols) . "</pre>";

// Check users table columns  
$ucols = [];
$ucolRes = $db->query("SHOW COLUMNS FROM users");
while ($c = $ucolRes->fetch_assoc()) $ucols[] = $c['Field'];
echo "<pre>Users Columns: " . implode(', ', $ucols) . "</pre>";

// Force update
if (isset($_GET['force']) && $_GET['force'] === '1') {
    $result = update_deposit_and_credit($db, $orderId, 'SUCCESS', 'IMB', 'MANUAL_SETTLE');
    echo "<pre>Update Result:\n" . print_r($result, true) . "</pre>";
    
    // Show updated record
    $res2 = $db->query("SELECT * FROM deposit WHERE order_id = '$orderId' LIMIT 1");
    echo "<pre>Updated Record:\n" . print_r($res2->fetch_assoc(), true) . "</pre>";
} else {
    echo "<br><a href='?order_id=$orderId&secret=imb2026&force=1' style='background:#c62828;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;'>Force Settle This Payment</a>";
}
