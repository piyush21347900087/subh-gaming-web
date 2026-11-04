<?php
/**
 * SUBH GAMING API v1 - Real-time User Balance API
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Api-Key');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/app_brand.php';
require_once __DIR__ . '/../../includes/api_keys.php';

$uid = (int)($_REQUEST['user_id'] ?? $_REQUEST['id'] ?? 0);
$code = trim((string)($_REQUEST['code'] ?? $_REQUEST['user_code'] ?? ''));
$key = trim((string)($_REQUEST['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? ''));

try {
    $db = db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

if ($uid <= 0 && empty($code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing user_id or code']);
    exit;
}

if ($uid > 0) {
    $stmt = $db->prepare("SELECT id, user_id, name, wallet_balance, mobile_no FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $uid);
} else {
    $stmt = $db->prepare("SELECT id, user_id, name, wallet_balance, mobile_no FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('s', $code);
}

$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$userId = (int)$user['id'];
$rawUserCode = $user['user_id'];

// Use central balance calculation if available
$totalBal = function_exists('bio_total_balance')
    ? bio_total_balance($db, $userId, $rawUserCode, true)
    : (float)$user['wallet_balance'];

echo json_encode([
    'success'        => true,
    'app'            => SUBH_APP_NAME,
    'id'             => $userId,
    'user_code'      => $rawUserCode,
    'name'           => $user['name'],
    'mobile_no'      => substr($user['mobile_no'] ?? '', 0, 2) . '******' . substr($user['mobile_no'] ?? '', -2),
    'wallet_balance' => (float)$totalBal,
    'timestamp'      => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
