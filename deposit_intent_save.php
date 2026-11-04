<?php
require_once 'includes/db.php';
// deposit_intent_save.php
// UPI Intent / Gateway callback se aayi payment ko "deposit" table me save karega

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json; charset=utf-8');

// ===== DB CONNECT =====
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = db();
    if ($db->connect_error) {
        throw new Exception('DB Connection Error: ' . $db->connect_error);
    }
    $db->set_charset('utf8mb4');
    $db->query("SET time_zone = '+05:30'");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'DB_ERROR',
        'message' => $e->getMessage()
    ]);
    exit;
}

// ===== HELPER: clean input =====
function post_str($key, $default = '') {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}
function post_float($key, $default = 0.0) {
    return isset($_POST[$key]) ? (float)$_POST[$key] : $default;
}

// ===== INPUTS (flexible) =====
//
// Prefer: LL_user_id  (jaise: LL0001)
// Fallback: user_id   (same as above)
// Fallback numeric: uid (users.id se resolve)
$userCode = post_str('LL_user_id');
if ($userCode === '') {
    $userCode = post_str('user_id');
}

$uidNumeric = 0;
if ($userCode === '') {
    $uidNumeric = (int) post_float('uid', 0);
}

// Amount
$amount = post_float('amount', 0);

// UPI app name (optional)
$app = strtolower(post_str('app'));  // phonepe / gpay / paytm / bhim / other

// UTR / txn id (optional – logging ke liye)
$utr     = post_str('utr');
$txnId   = post_str('txn_id');
$gateway = post_str('gateway'); // optional: "intent", "imb", etc.

// ===== BASIC VALIDATION =====
if ($amount <= 0) {
    echo json_encode([
        'success' => false,
        'error'   => 'INVALID_AMOUNT',
        'message' => 'Amount must be greater than 0'
    ]);
    exit;
}

// User resolve: agar RK user code nahi mila ho, numeric uid se nikaalo
if ($userCode === '' && $uidNumeric > 0) {
    $stmt = $db->prepare("SELECT user_id FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $uidNumeric);
    $stmt->execute();
    $stmt->bind_result($resolvedCode);
    if ($stmt->fetch()) {
        $userCode = $resolvedCode;
    }
    $stmt->close();
}

// Final check: userCode zaroori hai
if ($userCode === '') {
    echo json_encode([
        'success' => false,
        'error'   => 'USER_MISSING',
        'message' => 'user_id / LL_user_id / uid not provided or not found'
    ]);
    exit;
}

// Optional: verify ke yeh user_id users table me exist karta hai
$exists = false;
$check = $db->prepare("SELECT 1 FROM users WHERE user_id = ? LIMIT 1");
$check->bind_param('s', $userCode);
$check->execute();
$check->bind_result($dummy);
if ($check->fetch()) {
    $exists = true;
}
$check->close();

if (!$exists) {
    echo json_encode([
        'success' => false,
        'error'   => 'INVALID_USER',
        'message' => 'User code not found in users table'
    ]);
    exit;
}

// ===== Method name decide karein =====
$method = 'UPI Intent';
if ($app === 'phonepe') {
    $method = 'UPI Intent - PhonePe';
} elseif ($app === 'gpay' || $app === 'googlepay' || $app === 'google pay') {
    $method = 'UPI Intent - Google Pay';
} elseif ($app === 'paytm') {
    $method = 'UPI Intent - Paytm';
} elseif ($app === 'bhim') {
    $method = 'UPI Intent - BHIM';
}

// Screenshot column me hum textual info store kar sakte hain (kyunki intent me screenshot nahi hota)
$extraInfo = [];
if ($utr !== '')     $extraInfo[] = 'UTR:' . $utr;
if ($txnId !== '')   $extraInfo[] = 'TXN:' . $txnId;
if ($gateway !== '') $extraInfo[] = 'GW:' . $gateway;
if ($app !== '')     $extraInfo[] = 'APP:' . $app;

$screenshotField = '';
if (!empty($extraInfo)) {
    $screenshotField = implode(' | ', $extraInfo);
} else {
    $screenshotField = 'upi_intent';
}

// Status: Pending (admin baad me Done karega)
$status   = 'Pending';
$dateTime = date('Y-m-d H:i:s'); // IST

// ===== INSERT DEPOSIT ROW =====
try {
    $ins = $db->prepare("
        INSERT INTO deposit
            (user_id, amount, screenshot, method, status, date_time, bonus_amount)
          VALUES (?,?,?,?,?,?,?)
    ");
    $ins->bind_param(
        'sdssss',
        $userCode,
        $amount,
        $screenshotField,
        $method,
        $status,
        $dateTime
    );
    $ins->execute();
    $newId = $ins->insert_id;
    $ins->close();

    try {
        if (file_exists(__DIR__ . '/includes/fcm_helper.php')) {
            require_once __DIR__ . '/includes/fcm_helper.php';
            if (function_exists('send_admin_alert_notification')) {
                send_admin_alert_notification('admin_deposit', 'New Deposit Request', "Deposit request of ₹{$amount} by {$userCode}", $userCode, (float)$amount, $method);
            }
        }
    } catch (Throwable $e) {}

    echo json_encode([
        'success' => true,
        'message' => 'Deposit record created (intent)',
        'deposit_id' => $newId,
        'user_id'    => $userCode,
        'amount'     => $amount,
        'method'     => $method,
        'status'     => $status,
        'date_time'  => $dateTime
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'INSERT_ERROR',
        'message' => $e->getMessage()
    ]);
    exit;
}
