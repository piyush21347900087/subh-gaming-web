<?php
/**
 * SUBH GAMING API v1 - Payment & Order Status Checker
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
require_once __DIR__ . '/../../includes/api_keys.php';

$orderId = trim((string)($_REQUEST['order_id'] ?? $_REQUEST['txnid'] ?? ''));

if ($orderId === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'app'     => SUBH_APP_NAME,
        'error'   => 'Order ID or Transaction ID parameter is required'
    ]);
    exit;
}

try {
    $db = db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$stmt = $db->prepare("SELECT id, user_id, amount, status, method, date_time FROM deposit WHERE order_id = ? OR transaction_id = ? ORDER BY id DESC LIMIT 1");
$row = null;
if ($stmt) {
    $stmt->bind_param('ss', $orderId, $orderId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
}

if (!$row) {
    echo json_encode([
        'success'   => true,
        'app'       => SUBH_APP_NAME,
        'status'    => 'NOT_FOUND',
        'message'   => 'No deposit found matching ID: ' . $orderId,
        'order_id'  => $orderId
    ]);
    exit;
}

$rawStatus = strtolower(trim((string)$row['status']));
$cleanStatus = 'PENDING';
if (in_array($rawStatus, ['done', 'success', 'completed', 'approved'], true)) {
    $cleanStatus = 'SUCCESS';
} elseif (in_array($rawStatus, ['failed', 'fail', 'cancelled', 'rejected'], true)) {
    $cleanStatus = 'FAILED';
}

echo json_encode([
    'success'    => true,
    'app'        => SUBH_APP_NAME,
    'status'     => $cleanStatus,
    'raw_status' => $row['status'],
    'order_id'   => $orderId,
    'amount'     => (float)$row['amount'],
    'method'     => $row['method'] ?? 'UPI',
    'date_time'  => $row['date_time']
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
