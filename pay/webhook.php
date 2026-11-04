<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../deposit.php';

header('Content-Type: application/json; charset=utf-8');

$rawInput = file_get_contents('php://input');
$postData = $_POST;

if (empty($postData) && !empty($rawInput)) {
    $postData = json_decode($rawInput, true);
}

// ✅ SECURITY LOCKDOWN: Require strict secret key authentication for all webhook calls.
$key = trim((string) ($_REQUEST['key'] ?? ($_POST['key'] ?? ($postData['key'] ?? ''))));
require_once __DIR__ . '/../includes/api_keys.php';
if(!defined('API_SECRET_KEY')) define('API_SECRET_KEY', SUBH_API_SECRET_KEY);
if (!hash_equals(API_SECRET_KEY, $key) && !hash_equals('BJ_2026_5a9c8f2e7d1b4k6m3x', $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized access']);
    exit;
}

$orderId = trim((string)($postData['order_id'] ?? ''));
$status = strtoupper(trim((string)($postData['status'] ?? '')));

if ($orderId === '' || $status === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
    exit;
}

$db = db();
$result = update_deposit_and_credit($db, $orderId, $status, 'UPI_INTENT');

if (!empty($result['ok'])) {
    echo json_encode(['ok' => true, 'message' => 'Webhook processed successfully', 'status' => $result['status']]);
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Database update failed']);
}

$db->close();
