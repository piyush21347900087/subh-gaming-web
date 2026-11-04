<?php
require_once 'includes/db.php';
// upi_intent_callback.php
// Sirf SUCCESS UPI intent par deposit entry create karega

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Kolkata');

$logFile = __DIR__ . '/upi_intent_log.txt';

// ---- simple logger ----
function log_line($msg) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
}

// ---- read input (GET/POST both) ----
$input = $_REQUEST;

// Expecting from Android: user_id, amount, status, app_name, txn_id, ref_id (optional)
$userId   = isset($input['user_id'])   ? trim($input['user_id'])   : '';
$amount   = isset($input['amount'])    ? (float)$input['amount']   : 0;
$status   = isset($input['status'])    ? strtoupper(trim($input['status'])) : '';
$appName  = isset($input['app_name'])  ? trim($input['app_name'])  : '';
$txnId    = isset($input['txn_id'])    ? trim($input['txn_id'])    : '';
$refId    = isset($input['ref_id'])    ? trim($input['ref_id'])    : '';

log_line("HIT | user_id={$userId} | amount={$amount} | status={$status} | app={$appName} | txn_id={$txnId} | ref_id={$refId}");

// Basic validation
if ($userId === '' || $amount <= 0) {
    log_line("ERROR: Missing user_id or amount");
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>'Invalid params']);
    exit;
}

// ✅ Sirf SUCCESS par hi deposit create karna
if ($status !== 'SUCCESS') {
    log_line("NO-CREDIT: status is {$status}, deposit not created");
    echo json_encode(['ok'=>false,'msg'=>'Status not success, no credit']);
    exit;
}

// ---- DB CONNECT ----
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = db();
} catch (Exception $e) {
    log_line("DB ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'DB error']);
    exit;
}

// ---- DUPLICATE CHECK (same txn_id) ----
if ($txnId !== '') {
    $chk = $db->prepare("SELECT id FROM deposit WHERE utr_number = ? LIMIT 1");
    $chk->bind_param('s', $txnId);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $chk->close();
        log_line("DUPLICATE: txn_id already exists, skipping credit");
        echo json_encode(['ok'=>true,'msg'=>'Already credited']);
        $db->close();
        exit;
    }
    $chk->close();
}

// ---- Insert deposit row ----
// method: 'UPI Intent', status: 'Done'
try {
    $method = 'upi intent';
    $statusDb = 'Done';
    $note = "UPI Intent via {$appName}" . ($refId ? " (Ref: {$refId})" : '');

    $bonus = 0;
    $bSet = $db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1");
    if ($bSet && $rowSet = $bSet->fetch_assoc()) {
        if ((int)$rowSet['bonus_enabled'] === 1) {
            $stmtRate = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE min_amount <= ? AND is_active = 1 ORDER BY min_amount DESC LIMIT 1");
            if ($stmtRate) {
                $stmtRate->bind_param('d', $amount);
                $stmtRate->execute();
                $stmtRate->bind_result($bonusAmt);
                if ($stmtRate->fetch()) {
                    $bonus = (float)$bonusAmt;
                }
                $stmtRate->close();
            }
        }
    }

    $stmt = $db->prepare("
        INSERT INTO deposit (user_id, amount, method, status, date_time, utr_number, remark, bonus_amount)
        VALUES (?, ?, ?, ?, '" . date('Y-m-d H:i:s') . "', ?, ?, ?)
    ");
    $stmt->bind_param('sdssssd', $userId, $amount, $method, $statusDb, $txnId, $note, $bonus);
    $stmt->execute();
    $depId = $stmt->insert_id;
    $stmt->close();

    try {
        if (file_exists(__DIR__ . '/includes/fcm_helper.php')) {
            require_once __DIR__ . '/includes/fcm_helper.php';
            if (function_exists('send_admin_alert_notification')) {
                send_admin_alert_notification('admin_deposit', 'New Deposit Request', "Deposit request of ₹{$amount} by {$userId}", $userId, (float)$amount, 'UPI Intent');
            }
        }
    } catch (Throwable $e) {}

    log_line("CREDITED: deposit_id={$depId}, user_id={$userId}, amount={$amount}");
    echo json_encode(['ok'=>true,'msg'=>'Deposit credited','deposit_id'=>$depId]);
} catch (Exception $e) {
    log_line("INSERT ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'Insert error']);
}

$db->close();
