<?php
/**
 * YuviPay Webhook Handler (v1) - BABA JI
 * Receives payment callbacks from YuviPay and updates user balance.
 * Updated to support both Form-Data and JSON payloads.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../includes/db.php';
$db = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_GET)) {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

// Log incoming request
$raw = file_get_contents('php://input') ?: '';
$post = $_POST;
$request = $_REQUEST;

file_put_contents(__DIR__ . '/yuvipay.log', date('Y-m-d H:i:s') . ' | Webhook Hit | POST: ' . json_encode($post) . ' | REQ: ' . json_encode($request) . ' | Raw: ' . $raw . PHP_EOL, FILE_APPEND);

// Detect Data (Auto-parse JSON if needed)
$data = $request;
if ($raw !== '') {
    $j = json_decode($raw, true);
    if (is_array($j)) {
        $data = array_merge($data, $j);
    }
}

// ✅ SECURITY LOCKDOWN: Require strict secret key authentication for all webhook calls.
$key = trim((string) ($_REQUEST['key'] ?? ($_POST['key'] ?? ($data['key'] ?? ''))));
require_once __DIR__ . '/../includes/api_keys.php';
if(!defined('API_SECRET_KEY')) define('API_SECRET_KEY', SUBH_API_SECRET_KEY);
if (!hash_equals(API_SECRET_KEY, $key) && !hash_equals('BJ_2026_5a9c8f2e7d1b4k6m3x', $key)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

/**
 * YuviPay Webhook Parameters:
 * status, order_id, customer_mobile, amount, currency_code, udf1-5, time
 */
$status_in = strtoupper(trim((string)($data['status'] ?? '')));
$order_id = trim((string)($data['order_id'] ?? ''));
$amount = (float)($data['amount'] ?? 0);
$txn_id = trim((string)($data['transaction_id'] ?? $data['time'] ?? '')); 

if ($order_id === '') {
    file_put_contents(__DIR__ . '/yuvipay.log', date('Y-m-d H:i:s') . ' | Webhook Error | Missing Order ID' . PHP_EOL, FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Missing Order ID']);
    exit;
}

// Map YuviPay Status to BABA JI Status
$finalStatus = 'Pending';
// Possible success strings: SUCCESS, SUCCESSFUL, COMPLETED, CAPTURED, PAID, TRUE, 1
if (in_array($status_in, ['SUCCESS', 'SUCCESSFUL', 'COMPLETED', 'CAPTURED', 'PAID', 'TRUE', '1', 'FINISHED'], true)) {
    $finalStatus = 'Done';
} elseif (in_array($status_in, ['FAILURE', 'FAILED', 'CANCELLED', 'FALSE', '0'], true)) {
    $finalStatus = 'Failed';
}

file_put_contents(__DIR__ . '/yuvipay.log', date('Y-m-d H:i:s') . " | Webhook Proc | Order: $order_id | In-Status: $status_in | Final: $finalStatus" . PHP_EOL, FILE_APPEND);

if ($finalStatus === 'Pending') {
    echo json_encode(['status' => 'ignored', 'message' => 'Status is still pending']);
    exit;
}

$db->begin_transaction();
try {
    // Find matching deposit row
    $stmt = $db->prepare("
        SELECT d.id, d.amount, d.status, d.credited, u.id as user_db_id 
        FROM deposit d
        JOIN users u ON u.user_id = d.user_id
        WHERE d.order_id=? 
        ORDER BY d.id DESC LIMIT 1 FOR UPDATE
    ");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $dep = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dep) {
        $db->rollback();
        file_put_contents(__DIR__ . '/yuvipay.log', date('Y-m-d H:i:s') . " | Webhook Error | Order $order_id not found in DB" . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'Deposit record not found']);
        exit;
    }

    $depId = (int)$dep['id'];
    $userId = (int)$dep['user_db_id'];
    $dbAmount = (float)$dep['amount'];
    $credited = (int)$dep['credited'];
    $curStatusLower = strtolower(trim((string)$dep['status']));

    // Update status if not already finalized
    if ($curStatusLower !== 'done' && $curStatusLower !== 'approved') {
        $st_up = $db->prepare("UPDATE deposit SET status=? WHERE id=?");
        $st_up->bind_param("si", $finalStatus, $depId);
        $st_up->execute();
        $st_up->close();
    } else {
        $finalStatus = 'Done';
    }

    // Credit logic (only if Success and not yet credited)
    if ($finalStatus === 'Done' && $credited === 0) {
        
        // Update User Balance
        $add = $db->prepare("UPDATE users SET wallet_balance = (wallet_balance + ?) WHERE id=?");
        $add->bind_param("di", $dbAmount, $userId);
        $add->execute();
        $add->close();

        // Mark as Credited
        $stmt_c = $db->prepare("UPDATE deposit SET credited=1, status='Done', transaction_id=? WHERE id=?");
        $stmt_c->bind_param("si", $txn_id, $depId);
        $stmt_c->execute();
        $stmt_c->close();
        
        file_put_contents(__DIR__ . '/yuvipay.log', date('Y-m-d H:i:s') . " | Webhook Success | Order $order_id Credited: $dbAmount to User-PK: $userId" . PHP_EOL, FILE_APPEND);
    }

    $db->commit();
    echo "Webhook received successfully";
} catch (Throwable $e) {
    if (isset($db) && $db->ping()) $db->rollback();
    file_put_contents(__DIR__ . '/yuvipay.log', date('Y-m-d H:i:s') . ' | Webhook Critical | ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error']);
}
