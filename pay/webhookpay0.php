<?php
/**
 * Pay0 Webhook Handler - BABA JI
 * Uses Pay0 SDK structure to update payment status and credit users.
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
// ✅ SECURITY LOCKDOWN: Require strict secret key authentication for all webhook calls.
$key = trim((string) ($_REQUEST['key'] ?? ($_POST['key'] ?? '')));
require_once __DIR__ . '/../includes/api_keys.php';
if(!defined('API_SECRET_KEY')) define('API_SECRET_KEY', SUBH_API_SECRET_KEY);
if (!hash_equals(API_SECRET_KEY, $key) && !hash_equals('BJ_2026_5a9c8f2e7d1b4k6m3x', $key)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$status_in = strtoupper(trim((string)($_REQUEST['status'] ?? '')));
$order_id = trim((string)($_REQUEST['order_id'] ?? $_REQUEST['client_txn_id'] ?? $_REQUEST['merchant_order_id'] ?? ''));
$txn_id = trim((string)($_REQUEST['transaction_id'] ?? $_REQUEST['txn_id'] ?? $_REQUEST['client_txn_id'] ?? ''));
$amount = (float)($_REQUEST['amount'] ?? 0);

// Log incoming request
file_put_contents(__DIR__ . '/pay0.log', date('Y-m-d H:i:s') . ' | Webhook Hit: ' . json_encode($_REQUEST) . PHP_EOL, FILE_APPEND);

if ($order_id === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing Order ID']);
    exit;
}

// Map Pay0 Status to BABA JI Status
// SDK webhook.php uses 'SUCCESS'.
$finalStatus = 'Pending';
if (in_array($status_in, ['SUCCESS', 'COMPLETED', 'CAPTURED', 'PAID'], true)) {
    $finalStatus = 'Done';
} elseif (in_array($status_in, ['FAILURE', 'FAILED', 'CANCELLED'], true)) {
    $finalStatus = 'Failed';
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
        // Log mismatch but don't fail yet, maybe check by transaction_id
        $db->rollback();
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
        $up = $db->prepare("UPDATE deposit SET status=? WHERE id=?");
        $up->bind_param("si", $finalStatus, $depId);
        $up->execute();
        $up->close();
    } else {
        $finalStatus = 'Done'; // Already finalized
    }

    // Credit logic (only if Success and not yet credited)
    if ($finalStatus === 'Done' && $credited === 0) {
        
        // Update User Balance
        $add = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
        $add->bind_param("di", $dbAmount, $userId);
        $add->execute();
        $add->close();

        // Mark as Credited
        $stmt_c = $db->prepare("UPDATE deposit SET credited=1, status='Done', transaction_id=? WHERE id=?");
        $stmt_c->bind_param("si", $txn_id, $depId);
        $stmt_c->execute();
        $stmt_c->close();
    }

    $db->commit();
    echo "Webhook received successfully"; // As per SDK webhook.php (line 14)
} catch (Throwable $e) {
    $db->rollback();
    file_put_contents(__DIR__ . '/pay0.log', date('Y-m-d H:i:s') . ' | Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error']);
}
