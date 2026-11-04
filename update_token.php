<?php


header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ---------- Helpers ---------- */
function json_out($arr, $code = 200)
{
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalize_token($t)
{
    $t = (string) $t;
    $t = trim($t);
    if ($t === '' || $t === 'null' || $t === 'undefined')
        return '';
    $t = preg_replace('/\s+/', '', $t); // remove whitespace/newlines
    return $t;
}

function is_valid_token($t)
{
    $t = normalize_token($t);
    if ($t === '')
        return false;
    // FCM token generally long; 30 min safe lower bound
    if (strlen($t) < 30)
        return false;
    return true;
}

function stable_device_id()
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'ua';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'ip';
    return 'dev_' . substr(sha1($ua . '|' . $ip), 0, 16);
}

function clean_uid($uid)
{
    $uid = trim((string) $uid);
    if ($uid === '' || $uid === 'null' || $uid === 'undefined')
        return '';

    $uid = strtoupper($uid);

    // allow numeric only -> LL00001 (New Standard)
    if (preg_match('/^\d+$/', $uid)) {
        $n = (int) $uid;
        return "KR" . str_pad((string) $n, 2, "0", STR_PAD_LEFT);
    }

    // Support multiple prefixes and enforce KR prefix with stripped leading zeroes
    if (preg_match('/^(?:BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR|DS|DM)0*(\d+)$/', $uid, $m)) {
        $digits = $m[1];
        return "KR" . str_pad($digits, 2, "0", STR_PAD_LEFT);
    }

    return $uid; // return as-is if no match but not empty
}

/* ---------- DB connect ---------- */
try {
    if (file_exists(__DIR__ . '/includes/db.php')) {
        include __DIR__ . '/includes/db.php';
    }

    if (!isset($db) || !($db instanceof mysqli)) {
        $db = db();
    }

    $db->set_charset('utf8mb4');
    $db->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db->query("SET time_zone = '+05:30'");
} catch (Throwable $e) {
    json_out(["status" => "error", "message" => "DB connection failed"], 500);
}

/* ---------- Ensure table exists ---------- */
try {
    $db->query("CREATE TABLE IF NOT EXISTS user_fcm_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id VARCHAR(50) NOT NULL,
        fcm_token TEXT NOT NULL,
        device_id VARCHAR(190) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_id (user_id),
        KEY idx_device_id (device_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Throwable $e) {
}

/* add missing columns (safe) */
try {
    $db->query("ALTER TABLE user_fcm_tokens ADD COLUMN device_id VARCHAR(190) DEFAULT NULL");
} catch (Throwable $e) {
}
try {
    $db->query("ALTER TABLE user_fcm_tokens ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
} catch (Throwable $e) {
}
try {
    $db->query("ALTER TABLE user_fcm_tokens ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
} catch (Throwable $e) {
}

/* ✅ unique key for upsert */
try {
    $db->query("ALTER TABLE user_fcm_tokens ADD UNIQUE KEY uniq_user_device (user_id, device_id)");
} catch (Throwable $e) {
}

/* ---------- Input (POST + GET) ---------- */
$raw_uid =
    $_POST['user_id'] ?? $_GET['user_id'] ??
    $_POST['uid'] ?? $_GET['uid'] ??
    $_POST['userId'] ?? $_GET['userId'] ?? '';

$user_id = clean_uid($raw_uid);

$token =
    $_POST['fcm_token'] ?? $_POST['token'] ??
    $_GET['fcm_token'] ?? $_GET['token'] ?? '';

$token = normalize_token($token);

$deviceId = $_POST['device_id'] ?? ($_GET['device_id'] ?? '');
$deviceId = trim((string) $deviceId);

$androidId = $_POST['android_id'] ?? ($_GET['android_id'] ?? '');
$androidId = trim((string) $androidId);

if ($deviceId === '' || strtolower($deviceId) === 'android') {
    $deviceId = stable_device_id();
}
if ($androidId !== '') {
    $deviceId = $deviceId . '_a' . substr(sha1($androidId), 0, 8);
}

/* ---------- If opened directly in browser, show help ---------- */
if ($user_id === '' && $token === '') {
    json_out([
        "status" => "info",
        "message" => "Use: ?user_id=LL00001&token=FCM_TOKEN&device_id=DEVICE123",
        "example" => "https://subhgaming.online/update_token.php?user_id=LL00001&token=YOUR_FCM_TOKEN",
        "note" => "This API stores FCM token per user/device. Empty token is ignored."
    ], 200);
}

if ($user_id === '') {
    json_out(["status" => "error", "message" => "Invalid user_id"], 400);
}

if (!is_valid_token($token)) {
    // ✅ DO NOT overwrite old token
    json_out([
        "status" => "ignored",
        "message" => "Invalid token (ignored)",
        "user_id" => $user_id,
        "device_id" => $deviceId,
        "token_length" => strlen($token),
        "hint" => "Pass token param: ?user_id=LL00001&token=FCM_TOKEN"
    ], 200);
}

/* ---------- Save token (Upsert) ---------- */
$userIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$userIp = trim(explode(',', $userIp)[0]);

try {
    $sql = "INSERT INTO user_fcm_tokens (user_id, fcm_token, device_id, ip_address, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                fcm_token = VALUES(fcm_token),
                ip_address = VALUES(ip_address),
                updated_at = NOW()";

    $st = $db->prepare($sql);
    $st->bind_param("ssss", $user_id, $token, $deviceId, $userIp);
    $st->execute();
    $st->close();

    // cleanup duplicates of same token for same user (keep current device)
    $del = $db->prepare("DELETE FROM user_fcm_tokens WHERE user_id=? AND fcm_token=? AND device_id<>?");
    $del->bind_param("sss", $user_id, $token, $deviceId);
    $del->execute();
    $del->close();

    // remove junk rows for this user
    $safeUid = $db->real_escape_string($user_id);
    $db->query("DELETE FROM user_fcm_tokens
               WHERE user_id='{$safeUid}'
                 AND (fcm_token='' OR fcm_token='null' OR fcm_token='undefined')");

    // keep max 5 devices per user
    try {
        $db->query("
            DELETE t FROM user_fcm_tokens t
            JOIN (
                SELECT id FROM user_fcm_tokens
                WHERE user_id = '{$safeUid}'
                ORDER BY updated_at DESC, id DESC
                LIMIT 100 OFFSET 5
            ) old ON old.id = t.id
        ");
    } catch (Throwable $e) {
    }

    json_out([
        "status" => "success",
        "message" => "Token saved",
        "user_id" => $user_id,
        "device_id" => $deviceId
    ], 200);

} catch (Throwable $e) {
    json_out(["status" => "error", "message" => "Server error"], 500);
}





