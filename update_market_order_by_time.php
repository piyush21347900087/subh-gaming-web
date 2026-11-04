<?php
require_once 'includes/db.php';
// update_market_order_by_time.php

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

try {
    $db = db();
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed']);
    exit;
}

try {
    // 1. Fetch all markets sorted by open_time
    $res = $db->query("SELECT id FROM markets ORDER BY open_time ASC");
    $markets = [];
    while ($row = $res->fetch_assoc()) {
        $markets[] = (int) $row['id'];
    }

    if (empty($markets)) {
        echo json_encode(['status' => 'error', 'message' => 'No markets found.']);
        exit;
    }

    // 2. Update sort_order based on the new array index
    $db->begin_transaction();
    $stmt = $db->prepare("UPDATE markets SET sort_order = ? WHERE id = ?");

    $pos = 1;
    foreach ($markets as $mid) {
        $stmt->bind_param('ii', $pos, $mid);
        $stmt->execute();
        $pos++;
    }

    $stmt->close();
    $db->commit();

    echo json_encode(['status' => 'success', 'message' => 'Markets arranged according to time successfully.']);
} catch (mysqli_sql_exception $e) {
    if (isset($db))
        $db->rollback();
    error_log('[update_market_order_by_time] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to arrange by time.']);
}

if (isset($db))
    $db->close();
