<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function notify_json($data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function notify_user_code(mysqli $db): string
{
    $uid = (int)($_SESSION['user']['id'] ?? 0);
    if ($uid <= 0) {
        return '';
    }

    $stmt = $db->prepare('SELECT user_id FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $code = strtoupper(trim((string)($row['user_id'] ?? '')));
    if ($code === '') {
        return '';
    }
    if (preg_match('/^\d+$/', $code)) {
        return 'KR' . str_pad($code, 2, '0', STR_PAD_LEFT);
    }
    if (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR|DS|DM)0*(\d+)$/', $code, $m)) {
        return $m[1] . str_pad($m[2], 2, '0', STR_PAD_LEFT);
    }
    return $code;
}

require_once 'includes/fcm_helper.php';

try {
    if (empty($_SESSION['user']['id'])) {
        notify_json(['bid' => [], 'win' => []]);
    }

    $db = db();
    $db->set_charset('utf8mb4');

    $userCode = notify_user_code($db);
    if ($userCode === '') {
        $db->close();
        notify_json(['bid' => [], 'win' => []]);
    }

    $userVariants = get_all_uid_variants($userCode);
    if (empty($userVariants)) { $userVariants = [$userCode]; }
    $placeholders = implode(',', array_fill(0, count($userVariants), '?'));

    $lastWinId = max(0, (int)($_GET['last_win_id'] ?? 0));
    $wins = [];

    $recentOnlySql = $lastWinId > 0 ? '' : 'AND win_time >= (NOW() - INTERVAL 10 MINUTE)';
    $stmt = $db->prepare("
        SELECT id, market, bet_type, session_type, amount, wining_balance
        FROM wining
        WHERE user_id IN ({$placeholders}) AND id > ? AND LOWER(status) = 'win' AND is_reversed = 0 {$recentOnlySql}
        ORDER BY id ASC
        LIMIT 10
    ");
    $types = str_repeat('s', count($userVariants)) . 'i';
    $params = array_merge($userVariants, [$lastWinId]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $wins[] = [
            'id' => (int)$row['id'],
            'market' => (string)$row['market'],
            'bet_type' => (string)$row['bet_type'],
            'session' => (string)$row['session_type'],
            'amount' => (float)$row['amount'],
            'wining_balance' => (float)$row['wining_balance'],
        ];
    }
    $stmt->close();
    $db->close();

    notify_json([
        'bid' => [],
        'win' => $wins,
    ]);
} catch (Throwable $e) {
    notify_json(['bid' => [], 'win' => []]);
}
