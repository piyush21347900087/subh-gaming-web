<?php
/**
 * SUBH GAMING API v1 - Secure Result Declaration & Wins Trigger Engine
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/app_brand.php';
require_once __DIR__ . '/../../includes/api_keys.php';

$key = trim((string)($_REQUEST['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? ''));
$market = trim((string)($_REQUEST['market'] ?? ''));
$resultStr = trim((string)($_REQUEST['result'] ?? $_REQUEST['result_str'] ?? ''));
$date = trim((string)($_REQUEST['date'] ?? date('Y-m-d')));

// Auth check: either valid sync key or logged-in admin session
$isAuthorized = false;
if (!empty($key) && ($key === SUBH_RESULT_SYNC_KEY || $key === SUBH_WINS_TRIGGER_KEY || $key === SUBH_API_SECRET_KEY)) {
    $isAuthorized = true;
} elseif (isset($_SESSION['admin'])) {
    $isAuthorized = true;
}

if (!$isAuthorized) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'app'     => SUBH_APP_NAME,
        'error'   => 'Unauthorized: Invalid API key or unauthenticated session'
    ]);
    exit;
}

if (empty($market)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Market parameter is required']);
    exit;
}

try {
    $db = db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$nowTs = date('Y-m-d H:i:s');
$updatedResult = false;

// If result string provided, update market_result
if (!empty($resultStr)) {
    // Check if result already exists for today
    $checkStmt = $db->prepare("SELECT id FROM market_result WHERE market = ? AND result_date = ? LIMIT 1");
    if ($checkStmt) {
        $checkStmt->bind_param('ss', $market, $date);
        $checkStmt->execute();
        $resCheck = $checkStmt->get_result();
        $existing = $resCheck->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            $updStmt = $db->prepare("UPDATE market_result SET result_str = ?, declared_at = ? WHERE id = ?");
            if ($updStmt) {
                $id = (int)$existing['id'];
                $updStmt->bind_param('ssi', $resultStr, $nowTs, $id);
                $updStmt->execute();
                $updStmt->close();
                $updatedResult = true;
            }
        } else {
            $insStmt = $db->prepare("INSERT INTO market_result (market, result_str, result_date, declared_at) VALUES (?, ?, ?, ?)");
            if ($insStmt) {
                $insStmt->bind_param('ssss', $market, $resultStr, $date, $nowTs);
                $insStmt->execute();
                $insStmt->close();
                $updatedResult = true;
            }
        }
    }
}

// Trigger wins computation
$winsTriggered = false;
$winsCount = 0;
try {
    $winsUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/fetch_wins_api.php?market=' . urlencode($market) . '&key=' . urlencode(SUBH_WINS_TRIGGER_KEY);
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $resp = @file_get_contents($winsUrl, false, $ctx);
    if ($resp !== false) {
        $winsTriggered = true;
    }
} catch (Throwable $e) {
}

echo json_encode([
    'success'         => true,
    'app'             => SUBH_APP_NAME,
    'market'          => $market,
    'result'          => $resultStr,
    'date'            => $date,
    'result_recorded' => $updatedResult,
    'wins_engine'     => $winsTriggered ? 'COMPUTED' : 'QUEUED',
    'timestamp'       => $nowTs
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
