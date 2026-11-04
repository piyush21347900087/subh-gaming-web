<?php
/**
 * SUBH GAMING API v1 - FCM Device Token Registration API
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/app_brand.php';

$token = trim((string)($_REQUEST['token'] ?? $_REQUEST['fcm_token'] ?? ''));
$uid = trim((string)($_REQUEST['user_id'] ?? $_REQUEST['uid'] ?? ''));
$device = trim((string)($_REQUEST['device'] ?? $_REQUEST['model'] ?? 'Android'));

if (empty($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token parameter is required']);
    exit;
}

try {
    $db = db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Ensure user_fcm_tokens table has appropriate columns
$now = date('Y-m-d H:i:s');
$saved = false;

try {
    $check = $db->query("SHOW TABLES LIKE 'user_fcm_tokens'");
    if ($check && $check->num_rows > 0) {
        $st = $db->prepare("INSERT INTO user_fcm_tokens (user_id, token, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), updated_at = VALUES(updated_at)");
        if ($st) {
            $st->bind_param('sss', $uid, $token, $now);
            $st->execute();
            $st->close();
            $saved = true;
        }
    }
} catch (Throwable $e) {}

echo json_encode([
    'success'   => true,
    'app'       => SUBH_APP_NAME,
    'saved'     => $saved,
    'timestamp' => $now
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
