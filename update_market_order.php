<?php
require_once 'includes/db.php';
// update_market_order.php
// AJAX se aane wala market order save karega (sort_order update)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// DB CONNECT
try {
    $db = db();} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed']);
    exit;
}

// order string expect: "5,2,8,1..."
$orderStr = $_POST['order'] ?? '';

if ($orderStr === '') {
    echo json_encode(['status' => 'error', 'message' => 'No order data received']);
    exit;
}

// sanitize ids
$rawIds = explode(',', $orderStr);
$ids = [];
foreach ($rawIds as $val) {
    $id = (int)trim($val);
    if ($id > 0) {
        $ids[] = $id;
    }
}
$ids = array_values(array_unique($ids));

if (empty($ids)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid market list']);
    exit;
}

try {
    $db->begin_transaction();

    $stmt = $db->prepare("UPDATE markets SET sort_order = ? WHERE id = ?");

    $pos = 1;
    foreach ($ids as $mid) {
        $stmt->bind_param('ii', $pos, $mid);
        $stmt->execute();
        $pos++;
    }

    $stmt->close();
    $db->commit();

    echo json_encode(['status' => 'success', 'message' => 'Market order updated successfully.']);
} catch (mysqli_sql_exception $e) {
    $db->rollback();
    error_log('[update_market_order] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to update order.']);
}

$db->close();
