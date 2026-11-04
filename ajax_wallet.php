<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$uid = (int) $_SESSION['user']['id'];

try {
    $db = db();
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'DB Error']);
    exit;
}

// Fetch user code, then compute balance from ledger rows (same as dashboard/wallet page).
$res = $db->query("SELECT user_id, name FROM users WHERE id = $uid LIMIT 1");
$user = $res ? $res->fetch_assoc() : null;

if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'User not found']);
    exit;
}

$code = $user['user_id'];

$safeCode = $db->real_escape_string($code);

$totalBalance = bio_total_balance($db, $uid, trim((string) $code));
$walletBalance = $totalBalance;
$winBalance = 0;

// Auto-fail pending deposits older than 5 minutes for this user
$db->query("UPDATE deposit SET status='Failed' WHERE status='Pending' AND user_id='{$safeCode}' AND date_time < (NOW() - INTERVAL 5 MINUTE)");

// Get latest deposit status
$resDep = $db->query("SELECT id, status FROM deposit WHERE user_id='{$safeCode}' ORDER BY id DESC LIMIT 1");
$dep = $resDep->fetch_assoc() ?: ['id' => 0, 'status' => ''];

// Get latest withdrawal status
$resWd = $db->query("SELECT id, status FROM withdrawal WHERE user_id='{$safeCode}' AND method != 'game_bid' ORDER BY id DESC LIMIT 1");
$wd = $resWd->fetch_assoc() ?: ['id' => 0, 'status' => ''];

echo json_encode([
    'ok' => true,
    'balances' => [
        'wallet' => round($walletBalance, 2),
        'winning' => round($winBalance, 2),
        'total' => round($totalBalance, 2),
    ],
    'deposit' => $dep,
    'withdrawal' => $wd,
    'ts' => time(),
]);

$db->close();

