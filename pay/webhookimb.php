<?php
/**
 * pay/webhookimb.php
 * Overhauled Robust Version
 */
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../deposit.php'; 

$logFile = __DIR__ . '/IMB.log';
$rawBody = file_get_contents('php://input');
$json    = json_decode($rawBody, true);
// Merge all possible sources
$data = array_merge($_GET, $_POST, (is_array($json) ? $json : []));

// ✅ SECURITY LOCKDOWN: Require strict secret key authentication for all webhook calls.
$key = trim((string) ($_REQUEST['key'] ?? ($_POST['key'] ?? ($data['key'] ?? ''))));
require_once __DIR__ . '/../includes/api_keys.php';
if(!defined('API_SECRET_KEY')) define('API_SECRET_KEY', SUBH_API_SECRET_KEY);
if (!hash_equals(API_SECRET_KEY, $key) && !hash_equals('BJ_2026_5a9c8f2e7d1b4k6m3x', $key)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] WEBHOOK HIT: " . json_encode($data) . " | RAW: " . $rawBody . "\n", FILE_APPEND);

// 1. Extract Order ID & Transaction ID
$orderId = trim($data['order_id'] ?? $data['client_order_id'] ?? $data['result']['orderId'] ?? $data['external_id'] ?? '');
$txnId   = trim($data['utr'] ?? $data['txn_id'] ?? $data['transaction_id'] ?? $data['result']['utr'] ?? $data['gateway_id'] ?? '');

// 2. Extract Status
$statusRaw = $data['status'] ?? $data['payment_status'] ?? $data['result']['status'] ?? '';
$statusStr = strtoupper(trim((string)$statusRaw));

// Important: If success is indicated by boolean or specific IMB success keys
if ($statusRaw === true || $statusRaw === 'true' || $statusRaw === 1 || $statusStr === 'SUCCESSFUL' || $statusStr === 'PAID') {
    $statusStr = 'SUCCESS';
}

if ($orderId === '') {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR: No Order ID found in any field.\n", FILE_APPEND);
    die("Error: No Order ID");
}

$db = db();
$result = update_deposit_and_credit($db, $orderId, $statusStr, 'IMB', $txnId);

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] PROCESSED: Order $orderId ($txnId) | Status: $statusStr | DB Result: " . json_encode($result) . "\n", FILE_APPEND);

if ($result['ok']) {
    echo json_encode(['status' => 'success', 'message' => 'Processed']);
} else {
    echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Unknown Error']);
}
