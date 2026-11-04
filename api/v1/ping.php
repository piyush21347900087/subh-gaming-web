<?php
/**
 * SUBH GAMING API v1 - Real-time User & Admin Ping Heartbeat API
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/app_brand.php';

$uid = trim((string)($_REQUEST['user_id'] ?? $_REQUEST['uid'] ?? ''));
$isAdmin = isset($_REQUEST['admin']) && $_REQUEST['admin'] == '1';

try {
    $db = db();
    $now = date('Y-m-d H:i:s');

    if ($isAdmin) {
        $db->query("UPDATE admin_status SET status = 'online', last_seen = '{$now}' WHERE id = 1");
    } elseif (!empty($uid)) {
        $escUid = $db->real_escape_string($uid);
        $check = $db->query("SHOW TABLES LIKE 'user_status'");
        if ($check && $check->num_rows > 0) {
            $db->query("INSERT INTO user_status (user_id, status, last_seen) VALUES ('{$escUid}', 'online', '{$now}') ON DUPLICATE KEY UPDATE status = 'online', last_seen = '{$now}'");
        }
    }

    // Get count of online users in past 3 minutes
    $onlineCount = 0;
    $resOnline = $db->query("SELECT COUNT(*) FROM user_status WHERE last_seen >= NOW() - INTERVAL 3 MINUTE");
    if ($resOnline) {
        $onlineCount = (int)$resOnline->fetch_row()[0];
    }

    echo json_encode([
        'success'      => true,
        'app'          => SUBH_APP_NAME,
        'status'       => 'PONG',
        'online_users' => $onlineCount,
        'timestamp'    => $now
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success'   => true,
        'app'       => SUBH_APP_NAME,
        'status'    => 'PONG',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
