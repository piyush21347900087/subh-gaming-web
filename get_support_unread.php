<?php
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (empty($_SESSION['user']['id']) && empty($_SESSION['user_id'])) {
    echo json_encode(['unread' => 0]);
    exit;
}

$db = db();
$uid = (int)($_SESSION['user']['id'] ?? 0);
$userCode = '';
if ($uid > 0) {
    $res = $db->query("SELECT user_id FROM users WHERE id = $uid LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $userCode = $row['user_id'] ?? '';
    }
}
if ($userCode === '' && !empty($_SESSION['user_id'])) {
    $userCode = (string)$_SESSION['user_id'];
}

function norm_chat_uid_helper(string $code): string {
    $code = strtoupper(trim($code));
    if ($code === '') return '';
    if (preg_match('/^\d+$/', $code)) {
        return "KR" . str_pad($code, 2, "0", STR_PAD_LEFT);
    }
    if (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR|DS|DM)([0-9]+)$/', $code, $m)) {
        return "KR" . str_pad($m[2], 2, "0", STR_PAD_LEFT);
    }
    return preg_replace('/[^A-Z0-9_-]/', '', $code);
}

$chatUid = norm_chat_uid_helper($userCode);
if ($chatUid === '' && $userCode === '') {
    echo json_encode(['unread' => 0]);
    exit;
}

try {
    $db->query("
        CREATE TABLE IF NOT EXISTS chat_seen_user(
          user_id VARCHAR(50) PRIMARY KEY,
          last_seen BIGINT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci
    ");

    $seen = 0;
    $s = $db->prepare("SELECT last_seen FROM chat_seen_user WHERE user_id=? OR user_id=? LIMIT 1");
    $s->bind_param('ss', $chatUid, $userCode);
    $s->execute();
    $s->bind_result($seenDb);
    if ($s->fetch()) {
        $seen = (int)$seenDb;
    }
    $s->close();

    $c = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE (user_id=? OR user_id=?) AND sender='admin' AND id > ?");
    $c->bind_param('ssi', $chatUid, $userCode, $seen);
    $c->execute();
    $c->bind_result($cnt);
    $c->fetch();
    $c->close();

    echo json_encode(['unread' => (int)$cnt]);
} catch (Throwable $e) {
    echo json_encode(['unread' => 0]);
}
