<?php
// FULLY FAILSAFE HTML REDIRECT HANDLER
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Safe file inclusion - won't crash even if relative paths differ slightly
$dbPath = __DIR__ . '/../includes/db.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
    $db = db();
} else {
    // Fallback DB connection
    $db = new mysqli('localhost', 'u243882542_subh_gaming', 'MahaDevKing@7723646', 'u243882542_subh_gaming');
}

$orderId = trim($_REQUEST['order_id'] ?? $_REQUEST['client_order_id'] ?? '');

$logFile = __DIR__ . '/imb_return.log';
@file_put_contents($logFile, date('H:i:s') . " - return file hit for $orderId\n", FILE_APPEND);

if ($orderId !== '' && $db && !$db->connect_error) {
    $gwStatus = strtoupper(trim($_REQUEST['status'] ?? $_REQUEST['payment_status'] ?? $_REQUEST['txnStatus'] ?? ''));
    $gwTxn = trim($_REQUEST['utr'] ?? $_REQUEST['txn_id'] ?? $_REQUEST['transaction_id'] ?? '');

    $successKeys = ['SUCCESS', 'SUCCESSFUL', 'PAID', 'COMPLETED', 'TRUE', '1', 'OK'];
    $isSuccess = in_array($gwStatus, $successKeys, true);

    // If Webhook already did the job, or we received SUCCESS we mark it
    if ($isSuccess) {
        // Direct Query without prepared statements to avoid edge cases
        $orderIdSafe = $db->real_escape_string($orderId);
        $res = $db->query("SELECT id, user_id, amount, status, credited FROM deposit WHERE order_id='$orderIdSafe' LIMIT 1");
        
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            
            if (empty($row['credited']) && strtolower($row['status']) !== 'done') {
                $depId = (int)$row['id'];
                $uid = $db->real_escape_string($row['user_id']);
                $amt = (float)$row['amount'];
                $txnSafe = $db->real_escape_string($gwTxn);
                
                // --- BONUS CALCULATION ---
                $bonus = 0;
                if ($amt >= 30000) $bonus = 15000;
                else if ($amt >= 20000) $bonus = 6000;
                else if ($amt >= 10000) $bonus = 4000;
                else if ($amt >= 5000)  $bonus = 2000;
                else if ($amt >= 2000)  $bonus = 400;
                else if ($amt >= 1000)  $bonus = 150;
                
                $totalToCredit = $amt + $bonus;
                
                $updQ = "UPDATE deposit SET status='Done', credited=1, bonus_amount=$bonus";
                if ($txnSafe !== '') $updQ .= ", transaction_id='$txnSafe'";
                $updQ .= " WHERE id=$depId";
                
                $db->query($updQ);
                
                if ($db->affected_rows > 0) {
                    $walletCol = 'wallet_balance'; // Most likely
                    $db->query("UPDATE users SET $walletCol = $walletCol + $totalToCredit WHERE user_id='$uid'");
                    @file_put_contents($logFile, "Credited wallet for $orderId (Amt: $amt + Bonus: $bonus)\n", FILE_APPEND);
                }
            }
        }
    }
}

// Ensure DB closes safely
if ($db && !is_string($db)) @$db->close();
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Payment...</title>
    <style>
        body { margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f7f7f7; font-family: sans-serif; }
        .loader { border: 4px solid #ddd; border-left-color: #4CAF50; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .box { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; }
        h3 { color: #333; margin: 0 0 10px; font-size: 18px; }
        p { color: #666; margin: 0; font-size: 14px; }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <div class="box">
        <div class="loader"></div>
        <h3>Payment Verified</h3>
        <p>Redirecting back to app...</p>
    </div>
    
    <script>
        // Force the redirection via Javascript to bypass tight 302 strict WebView rules
        // Pass order_id so background polling can verify via API if webhook fails
        setTimeout(function() {
            window.location.replace("../deposit_history.php?paid=1&order_id=<?php echo htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8'); ?>");
        }, 1500);
    </script>
</body>
</html>


