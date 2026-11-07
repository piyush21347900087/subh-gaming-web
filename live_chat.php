<?php
if (!ob_get_level()) { ob_start(); }
require_once 'includes/lang.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set('Asia/Kolkata');
header('Content-Type: text/html; charset=UTF-8');

/* No-cache (IMPORTANT) */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/* ---------- Raw POST body parser (sendBeacon fix) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $tmp = [];
        parse_str($raw, $tmp);
        if (is_array($tmp) && $tmp)
            $_POST = $tmp + $_POST;
    }
}

/* Optional project guards (if exist) */
if (file_exists(__DIR__ . '/includes/user_ping.php'))
    require_once __DIR__ . '/includes/user_ping.php';
if (file_exists(__DIR__ . '/includes/live_ping.php'))
    require_once __DIR__ . '/includes/live_ping.php';

/* ---- Login guard ---- */
$sessionVal = $_SESSION['user']['user_id'] ?? ($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? ($_SESSION['user']['email'] ?? null)));
if (!$sessionVal) {
    header('Location: login.php');
    exit;
}

/* ---- DB CONNECTION ---- */
try {
    $db = db();
    $db->query("SET time_zone = '+05:30'");
} catch (Throwable $e) {
    http_response_code(500);
    die("DB error: " . $e->getMessage());
}

/* ---- Helpers ---- */
function norm_uid($uid): string
{
    $uid = trim((string) $uid);
    if ($uid === '')
        return '';
    $uid = strtoupper($uid);
    // allow only A-Z 0-9 _ -
    $uid = preg_replace('/[^A-Z0-9_-]/', '', $uid);
    return $uid;
}

/**
 * IMPORTANT:
 * - Yahan koi bhi "LL + random" / "LL + internalId" generate nahi hoga.
 * - Sirf DB se existing users.user_id hi return hoga.
 * - Agar user resolve nahi hota -> logout/redirect (taaki fake id na ban jaaye)
 */
function resolve_logged_user_id(mysqli $db): string
{
    $candidates = [];

    // Best: session user array
    if (!empty($_SESSION['user']['user_id']))
        $candidates[] = (string) $_SESSION['user']['user_id'];
    if (!empty($_SESSION['user']['id']))
        $candidates[] = (string) $_SESSION['user']['id'];
    if (!empty($_SESSION['user_id']))
        $candidates[] = (string) $_SESSION['user_id'];
    if (!empty($_SESSION['user']['email']))
        $candidates[] = (string) $_SESSION['user']['email'];
    if (!empty($_SESSION['user']['mobile_no']))
        $candidates[] = (string) $_SESSION['user']['mobile_no'];

    // Also allow sessionVal direct (if called from outside)
    $candidates = array_values(array_unique(array_filter($candidates)));

    // 1) If any candidate is already a known user_id format -> verify exists in users
    foreach ($candidates as $v) {
        $v2 = norm_uid($v);
        if ($v2 !== '') {
            $st = $db->prepare("SELECT user_id FROM users WHERE user_id=? LIMIT 1");
            $st->bind_param("s", $v2);
            $st->execute();
            $st->bind_result($foundUid);
            $st->fetch();
            $st->close();
            if (!empty($foundUid))
                return norm_uid($foundUid);
        }
    }

    // 2) If any candidate is numeric -> treat as internal users.id and fetch user_id
    foreach ($candidates as $v) {
        if (ctype_digit($v)) {
            $id = (int) $v;
            $st = $db->prepare("SELECT user_id FROM users WHERE id=? LIMIT 1");
            $st->bind_param("i", $id);
            $st->execute();
            $st->bind_result($uid);
            $st->fetch();
            $st->close();
            if (!empty($uid))
                return norm_uid($uid);
        }
    }

    // 3) Try by email
    foreach ($candidates as $v) {
        if (strpos($v, '@') !== false) {
            $st = $db->prepare("SELECT user_id FROM users WHERE email=? LIMIT 1");
            $st->bind_param("s", $v);
            $st->execute();
            $st->bind_result($uid);
            $st->fetch();
            $st->close();
            if (!empty($uid))
                return norm_uid($uid);
        }
    }

    // 4) Try by mobile (10 digits typical)
    foreach ($candidates as $v) {
        $vv = preg_replace('/\D+/', '', $v);
        if ($vv !== '' && strlen($vv) >= 8) {
            $st = $db->prepare("SELECT user_id FROM users WHERE mobile_no=? LIMIT 1");
            $st->bind_param("s", $vv);
            $st->execute();
            $st->bind_result($uid);
            $st->fetch();
            $st->close();
            if (!empty($uid))
                return norm_uid($uid);
        }
    }

    // 5) Last attempt: maybe they stored user_id without RK normalization
    foreach ($candidates as $v) {
        $st = $db->prepare("SELECT user_id FROM users WHERE user_id=? LIMIT 1");
        $st->bind_param("s", $v);
        $st->execute();
        $st->bind_result($uid);
        $st->fetch();
        $st->close();
        if (!empty($uid))
            return norm_uid($uid);
    }

    return '';
}

function maLL_user_seen(mysqli $db, string $uid, int $seenId): void
{
    if ($seenId <= 0)
        return;
    $st = $db->prepare("
        INSERT INTO chat_seen_user(user_id,last_seen) VALUES(?,?)
        ON DUPLICATE KEY UPDATE last_seen = GREATEST(last_seen, VALUES(last_seen))
    ");
    $st->bind_param("si", $uid, $seenId);
    $st->execute();
    $st->close();
}

function chat_text_len(string $text): int
{
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function chat_text_substr(string $text, int $start, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($text, $start, $length, 'UTF-8') : substr($text, $start, $length);
}

/* ---- Tables ---- */
$db->query("CREATE TABLE IF NOT EXISTS admin_status(
  id TINYINT PRIMARY KEY,
  status ENUM('online','offline') NOT NULL
)");
$db->query("INSERT IGNORE INTO admin_status(id,status) VALUES(1,'offline')");

$db->query("CREATE TABLE IF NOT EXISTS user_status(
  user_id VARCHAR(50) PRIMARY KEY,
  last_seen DATETIME NOT NULL
)");

$db->query("
CREATE TABLE IF NOT EXISTS chat_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id VARCHAR(50) NOT NULL,
  sender ENUM('user','admin') NOT NULL DEFAULT 'user',
  message TEXT NOT NULL,
  image VARCHAR(255) DEFAULT NULL,
  ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_id (user_id),
  KEY idx_ts (ts),
  KEY idx_sender (sender)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci
");

try {
    $db->query("ALTER TABLE chat_messages ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER message");
} catch (Throwable $e) {
}
try {
    $db->query("ALTER TABLE chat_messages MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
} catch (Throwable $e) {
}
try {
    $db->query("ALTER TABLE chat_messages ADD PRIMARY KEY (id)");
} catch (Throwable $e) {
}
try {
    $db->query("ALTER TABLE chat_messages ADD KEY idx_user_id (user_id)");
} catch (Throwable $e) {
}
try {
    $db->query("ALTER TABLE chat_messages ADD KEY idx_ts (ts)");
} catch (Throwable $e) {
}
try {
    $db->query("ALTER TABLE chat_messages ADD KEY idx_sender (sender)");
} catch (Throwable $e) {
}

$db->query("
CREATE TABLE IF NOT EXISTS chat_seen_user(
  user_id VARCHAR(50) PRIMARY KEY,
  last_seen BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci
");

function get_today_uploads_count(mysqli $db, string $uid): int {
    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE user_id=? AND image IS NOT NULL AND ts >= CURDATE()");
        $countStmt->bind_param("s", $uid);
        $countStmt->execute();
        $countStmt->bind_result($cnt);
        $countStmt->fetch();
        $countStmt->close();
        return (int)$cnt;
    } catch (Throwable $e) {
        return 0;
    }
}

/* ---- Resolve correct LL user_id (ONLY from users table) ---- */
$uid = resolve_logged_user_id($db);
$uid = norm_uid($uid);

if ($uid === '') {
    // session garbage -> logout to prevent fake IDs like LL1768...
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

/* Compute daily upload stats for HTML rendering */
$todayUploads = get_today_uploads_count($db, $uid);
$remainingUploads = max(0, 5 - $todayUploads);

/* =========================
   AJAX ENDPOINTS
========================= */
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

/* unread_count (PLAIN NUMBER) — user_dashboard dot ke liye */
if ($action === 'unread_count') {
    header('Content-Type: text/plain; charset=UTF-8');
    try {
        $seen = 0;
        $s = $db->prepare("SELECT last_seen FROM chat_seen_user WHERE user_id=? LIMIT 1");
        $s->bind_param("s", $uid);
        $s->execute();
        $s->bind_result($seenDb);
        if ($s->fetch())
            $seen = (int) $seenDb;
        $s->close();

        $c = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE user_id=? AND sender='admin' AND id > ?");
        $c->bind_param("si", $uid, $seen);
        $c->execute();
        $c->bind_result($cnt);
        $c->fetch();
        $c->close();

        echo (int) $cnt;
    } catch (Throwable $e) {
        echo "0";
    }
    exit;
}

/* maLL_read — chat open / notification tap */
if ($action === 'maLL_read') {
    header('Content-Type: application/json; charset=UTF-8');
    try {
        $maxId = 0;
        $m = $db->prepare("SELECT IFNULL(MAX(id),0) FROM chat_messages WHERE user_id=?");
        $m->bind_param("s", $uid);
        $m->execute();
        $m->bind_result($mx);
        if ($m->fetch())
            $maxId = (int) $mx;
        $m->close();

        maLL_user_seen($db, $uid, $maxId);
        echo json_encode(['ok' => true, 'seen' => $maxId, 'uid' => $uid]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'uid' => $uid]);
    }
    exit;
}

/* Status */
if ($action === 'status') {
    header('Content-Type: application/json; charset=UTF-8');
    $row = $db->query("SELECT status FROM admin_status WHERE id=1")->fetch_assoc();
    echo json_encode(['ok' => true, 'online' => ($row && $row['status'] === 'online')]);
    exit;
}

/* Ping */
if ($action === 'ping') {
    $stmt = $db->prepare("INSERT INTO user_status(user_id,last_seen) VALUES(?, NOW())
                          ON DUPLICATE KEY UPDATE last_seen=NOW()");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $stmt->close();
    exit('ok');
}

/* Send */
if ($action === 'send') {
    header('Content-Type: application/json; charset=UTF-8');

    $msg = trim($_POST['message'] ?? '');
    $imagePath = null;

    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
        // Limit: 5 uploads per day
        $countStmt = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE user_id=? AND image IS NOT NULL AND ts >= CURDATE()");
        $countStmt->bind_param("s", $uid);
        $countStmt->execute();
        $countStmt->bind_result($todayUploads);
        $countStmt->fetch();
        $countStmt->close();

        if ($todayUploads >= 5) {
            echo json_encode(['ok' => false, 'error' => 'Aap ek din me maximum 5 images hi upload kar sakte hain.']);
            exit;
        }

        $uploadDir = 'uploads/chat/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
        $filename = 'chat_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $target)) {
            $imagePath = $target;
        }
    }

    if ($msg === '' && !$imagePath) {
        echo json_encode(['ok' => false, 'error' => 'Empty message']);
        exit;
    }
    if (chat_text_len($msg) > 1000)
        $msg = chat_text_substr($msg, 0, 1000);

    $stmt = $db->prepare("INSERT INTO chat_messages (user_id, sender, message, image, ts) VALUES (?, 'user', ?, ?, NOW())");
    $stmt->bind_param("sss", $uid, $msg, $imagePath);
    $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $stmt->close();

    // Trigger Admin Notification for Live Chat
    try {
        if (file_exists(__DIR__ . '/includes/fcm_helper.php')) {
            require_once __DIR__ . '/includes/fcm_helper.php';
            if (function_exists('send_admin_alert_notification')) {
                $chatMsg = $msg !== '' ? $msg : '📷 Sent a photo';
                send_admin_alert_notification('admin_chat', 'New Chat Message', $chatMsg, $uid);
            }
        }
    } catch (Throwable $e) {}

    $stmt2 = $db->prepare("INSERT INTO user_status(user_id,last_seen) VALUES(?, NOW())
                           ON DUPLICATE KEY UPDATE last_seen=NOW()");
    $stmt2->bind_param("s", $uid);
    $stmt2->execute();
    $stmt2->close();

    // user sent => mark seen to latest
    try {
        $maxId = 0;
        $m = $db->prepare("SELECT IFNULL(MAX(id),0) FROM chat_messages WHERE user_id=?");
        $m->bind_param("s", $uid);
        $m->execute();
        $m->bind_result($mx);
        if ($m->fetch())
            $maxId = (int) $mx;
        $m->close();
        maLL_user_seen($db, $uid, $maxId);
    } catch (Throwable $e) {
    }

    $newToday = get_today_uploads_count($db, $uid);
    echo json_encode(['ok' => true, 'id' => $newId, 'uid' => $uid, 'today_uploads' => $newToday, 'remaining_uploads' => max(0, 5 - $newToday), 'max_uploads' => 5]);
    exit;
}

/* Fetch */
if ($action === 'fetch') {
    header('Content-Type: application/json; charset=UTF-8');

    $afterId = (int) ($_GET['after_id'] ?? 0);

    if ($afterId <= 0) {
        $stmt = $db->prepare("
            SELECT id, sender, message, image,
                   DATE_FORMAT(ts, '%d %b %Y, %h:%i %p') AS t
            FROM chat_messages
            WHERE user_id = ?
            ORDER BY id DESC
            LIMIT 120
        ");
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc())
            $rows[] = $r;
        $stmt->close();
        $rows = array_reverse($rows);

        // mark read to MAX(id)
        try {
            $maxId = 0;
            $m = $db->prepare("SELECT IFNULL(MAX(id),0) FROM chat_messages WHERE user_id=?");
            $m->bind_param("s", $uid);
            $m->execute();
            $m->bind_result($mx);
            if ($m->fetch())
                $maxId = (int) $mx;
            $m->close();
            maLL_user_seen($db, $uid, $maxId); } catch(Throwable $e){} $admin_seen = 0; try { $aS = $db->prepare("SELECT last_seen FROM admin_chat_seen WHERE user_id=?"); $aS->bind_param("s",$uid); $aS->execute(); $aS->bind_result($aSeen); if($aS->fetch()) $admin_seen = (int)$aSeen; $aS->close(); } catch(Throwable $e){} $admin_online=false; try { $row=$db->query("SELECT status, last_seen FROM admin_status WHERE id=1")->fetch_assoc(); if ($row && $row["status"]==="online") { $lts = strtotime((string)$row["last_seen"]); if ($lts && (time() - $lts) < 60) $admin_online=true; } } catch(Throwable $e){} $newToday = get_today_uploads_count($db, $uid); echo json_encode(["ok" => true, "messages" => $rows, "uid" => $uid, "admin_seen" => $admin_seen, "admin_online" => $admin_online, "today_uploads" => $newToday, "remaining_uploads" => max(0, 5 - $newToday), "max_uploads" => 5]);
        exit;
    } else {
        $stmt = $db->prepare("
            SELECT id, sender, message, image,
                   DATE_FORMAT(ts, '%d %b %Y, %h:%i %p') AS t
            FROM chat_messages
            WHERE user_id = ? AND id > ?
            ORDER BY id ASC
            LIMIT 200
        ");
        $stmt->bind_param("si", $uid, $afterId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        $maxGot = 0;
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
            $id = (int) ($r['id'] ?? 0);
            if ($id > $maxGot)
                $maxGot = $id;
        }
        $stmt->close();

        maLL_user_seen($db, $uid, $maxGot); $admin_seen = 0; try { $aS = $db->prepare("SELECT last_seen FROM admin_chat_seen WHERE user_id=?"); $aS->bind_param("s",$uid); $aS->execute(); $aS->bind_result($aSeen); if($aS->fetch()) $admin_seen = (int)$aSeen; $aS->close(); } catch(Throwable $e){} $admin_online=false; try { $row=$db->query("SELECT status, last_seen FROM admin_status WHERE id=1")->fetch_assoc(); if ($row && $row["status"]==="online") { $lts = strtotime((string)$row["last_seen"]); if ($lts && (time() - $lts) < 60) $admin_online=true; } } catch(Throwable $e){} $newToday = get_today_uploads_count($db, $uid); echo json_encode(["ok" => true, "messages" => $rows, "uid" => $uid, "admin_seen" => $admin_seen, "admin_online" => $admin_online, "today_uploads" => $newToday, "remaining_uploads" => max(0, 5 - $newToday), "max_uploads" => 5]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>SUBH GAMING • Live Support</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-bg: #000000;
            --card-bg: #121212;
            --accent-main: #e53935;
            --accent-red: #e53935;
            --accent-gold: #ffcc00;
            --gradient-main: linear-gradient(135deg, #e53935, #8b0000);
            --text-main: #ffffff;
            --text-dim: #94a3b8;
            --line: rgba(255, 255, 255, 0.1);
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
            --radius: 16px;
            --kb: 0px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: #000000 !important;
            background: #000000 !important;
            color: #ffffff;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
        }

        .wrap {
            height: 100%;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            position: relative;
            background: #000000 !important;
        }

        /* ── Header — matches user_dashboard.php exactly ── */
        header {
            background-color: #000000 !important;
            background-image: none !important;
            padding-top: calc(env(safe-area-inset-top) + 10px);
            padding-bottom: 10px;
            padding-left: 14px;
            padding-right: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.4);
            border-bottom: 1px solid #1a1a1a;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-btn {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #e53935;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            text-decoration: none;
            font-size: 15px;
            cursor: pointer;
            border: none;
            flex-shrink: 0;
            transition: opacity 0.15s ease;
        }

        .back-btn:active {
            opacity: 0.8;
        }

        .header-title-flash {
            color: #ffffff;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .header-title-flash img {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            margin-top: -2px;
        }

        .statPill {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 215, 0, 0.35);
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #94a3b8;
        }

        .dot.on {
            background: #25D366;
            box-shadow: 0 0 8px rgba(37, 211, 102, 0.8);
            animation: pulse-gold 2s infinite;
        }

        @keyframes pulse-gold {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        .statTxt {
            font-size: 10px;
            font-weight: 700;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
            background: #000000 !important;
        }

        .msgs {
            flex: 1;
            overflow-y: auto;
            padding: 16px 14px calc(var(--compH, 80px) + var(--kb, 0px) + 20px);
            display: flex;
            flex-direction: column;
            gap: 12px;
            scroll-behavior: smooth;
        }

        .msgs::-webkit-scrollbar { width: 4px; }
        .msgs::-webkit-scrollbar-thumb { background: #e53935; border-radius: 10px; }

        .row {
            display: flex;
            flex-direction: column;
            max-width: 85%;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .row.user {
            align-self: flex-end;
            align-items: flex-end;
        }

        .row.admin {
            align-self: flex-start;
            align-items: flex-start;
        }

        .buLLle {
            padding: 10px 14px;
            border-radius: var(--radius);
            font-size: 14px;
            line-height: 1.5;
            position: relative;
            box-shadow: var(--shadow);
            word-wrap: break-word;
        }

        .row.user .buLLle {
            background: linear-gradient(135deg, #e53935, #8b0000);
            color: #ffffff;
            border-bottom-right-radius: 4px;
            box-shadow: 0 3px 10px rgba(229, 57, 53, 0.25);
        }

        .row.admin .buLLle {
            background: #1a1a1a;
            color: #ffffff;
            border-bottom-left-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .meta {
            font-size: 9px;
            margin-top: 5px;
            font-weight: 600;
            opacity: 0.85;
        }

        .row.user .meta {
            color: #ffffff;
            text-align: right;
        }

        .row.admin .meta {
            color: var(--text-dim);
            text-align: left;
        }

        .hint {
            text-align: center;
            font-size: 12px;
            color: var(--text-dim);
            margin: 20px 0;
            font-weight: 500;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .composer {
            position: absolute;
            bottom: var(--kb, 0px);
            left: 0;
            right: 0;
            background: #000000 !important;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-top: 1px solid #1a1a1a;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.5);
            z-index: 100;
        }

        .composer-wrap {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .attachment-preview {
            display: none;
            align-items: center;
            gap: 8px;
            max-width: 100%;
            padding: 6px 8px;
            border-radius: 14px;
            background: #1a1a1a;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .attachment-preview.show {
            display: flex;
        }

        .attachment-preview img {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            border: 1px solid #333333;
        }

        .attachment-info-col {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .upload-limit-text {
            font-size: 10px;
            font-weight: 600;
            color: #ff4d4d;
        }

        .upload-limit-text strong {
            color: #ff6b6b;
            font-weight: 700;
        }

        .attachment-name {
            flex: 1;
            min-width: 0;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-main);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .attachment-clear {
            border: none;
            background: transparent;
            color: #ef4444;
            font-size: 14px;
            padding: 4px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .input {
            width: 100%;
            background: #121212;
            border: 1.5px solid #2a2a2a;
            border-radius: 22px;
            padding: 10px 16px;
            font-size: 14px;
            color: #ffffff;
            outline: none;
            transition: border-color 0.2s ease, background 0.2s ease;
        }

        .input:focus {
            border-color: #e53935;
            background: #1a1a1a;
        }

        .btn {
            background: linear-gradient(135deg, #e53935, #8b0000);
            color: #ffffff;
            border: none;
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.25);
            flex-shrink: 0;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }

        .btn:active {
            transform: scale(0.92);
        }

        .btn:disabled {
            background: #cbd5e1;
            box-shadow: none;
            cursor: not-allowed;
        }

        .btn.sending {
            width: auto !important;
            min-width: 96px !important;
            border-radius: 20px !important;
            padding: 0 12px !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            opacity: 0.95 !important;
            cursor: wait !important;
            gap: 5px !important;
            background: linear-gradient(135deg, #800000, #990000) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.25) !important;
        }

        .btn-camera {
            background: linear-gradient(135deg, #475569, #334155) !important;
            box-shadow: 0 4px 10px rgba(71, 85, 105, 0.25) !important;
        }

        .scrollDown {
            position: absolute;
            bottom: calc(var(--compH, 80px) + var(--kb, 0px) + 20px);
            right: 18px;
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #800000, #990000);
            color: #ffffff;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.35);
            cursor: pointer;
            z-index: 10;
            transition: transform 0.15s ease;
        }

        .scrollDown:active {
            transform: scale(0.92);
        }

        .scrollDown.show { display: flex; }

        @media (max-width: 480px) {
            .wrap { border-radius: 0; }
        }

        @media (max-width: 380px) {
            .header-title-flash { font-size: 14px; }
            .back-btn { width: 32px; height: 32px; font-size: 14px; }
            .buLLle { font-size: 13px; padding: 8px 12px; }
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
    <div class="wrap">
            <header>
            <div class="header-left">
                <a class="back-btn" href="user_dashboard.php" title="Back">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <div class="header-title-flash">
                    <img src="icons/gold_coin.svg" alt="coin">
                    <?= __("Live Support") ?>
                    <img src="icons/gold_coin.svg" alt="coin">
                </div>
            </div>
            <div class="statPill">
                <div id="statDot" class="dot"></div>
                <div id="statTxt" class="statTxt"><?= __("Offline") ?></div>
            </div>
        </header>

        <div class="chat">
            <div id="msgs" class="msgs">
                <div class="hint"><?= __("Loading messages…") ?></div>
            </div>

            <div id="scrollDown" class="scrollDown">
                <i class="fa-solid fa-chevron-down"></i>
            </div>

            <div id="composer" class="composer">
                <input type="file" id="screenshotInput" accept="image/*" style="display: none;">
                <button id="screenshotBtn" type="button" class="btn btn-camera" title="Attach Screenshot">
                    <i class="fa-solid fa-camera"></i>
                </button>
                <div class="composer-wrap">
                    <div id="attachmentPreview" class="attachment-preview">
                        <img id="attachmentThumb" alt="">
                        <div class="attachment-info-col">
                            <span id="attachmentName" class="attachment-name"></span>
                            <span id="uploadLimitText" class="upload-limit-text">Daily Image Limit: <strong id="remUploadCount"><?= (int)$remainingUploads ?></strong>/5 remaining</span>
                        </div>
                        <button id="attachmentClear" type="button" class="attachment-clear" title="Remove attachment">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <input id="message" class="input" type="text" placeholder="<?= __('Type your message…') ?>" maxlength="1000">
                </div>
                <button id="sendBtn" type="button" class="btn">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var msgsEl = document.getElementById('msgs');
            var scrollBtn = document.getElementById('scrollDown');
            var input = document.getElementById('message');
            var sendBtn = document.getElementById('sendBtn');
            var screenshotBtn = document.getElementById('screenshotBtn');
            var screenshotInput = document.getElementById('screenshotInput');
            var attachmentPreview = document.getElementById('attachmentPreview');
            var attachmentThumb = document.getElementById('attachmentThumb');
            var attachmentName = document.getElementById('attachmentName');
            var attachmentClear = document.getElementById('attachmentClear');
            var composer = document.getElementById('composer');

            var statDot = document.getElementById('statDot');
            var statTxt = document.getElementById('statTxt');

            var notifySound = new Audio('sound/done.mp3');
            notifySound.preload = 'auto';

            var lastId = 0;
            var firstLoad = true;
            var isSending = false;
            var shouldScrollAfterSend = false;
            var maxUploadLimit = 5;
            var remainingUploads = <?= (int)$remainingUploads ?>;

            function triggerHaptic(pattern = 50) {
                try {
                    if (navigator.vibrate) {
                        navigator.vibrate(pattern);
                    }
                } catch (e) { }
            }

            function updateUploadLimitDisplay(rem) {
                if (rem !== undefined && rem !== null) {
                    remainingUploads = Math.max(0, parseInt(rem));
                }
                var remEl = document.getElementById('remUploadCount');
                if (remEl) remEl.textContent = remainingUploads;
                if (screenshotBtn) {
                    screenshotBtn.title = "Attach Screenshot (" + remainingUploads + "/5 remaining today)";
                }
            }
            var attachmentObjectUrl = '';

            var lastNotifiedAdminId = parseInt(localStorage.getItem('ll_chat_last_notified_admin_id') || '0', 10);

            screenshotBtn.addEventListener('click', function() {
                if (remainingUploads <= 0) {
                    triggerHaptic([80, 50, 80]);
                    alert("Aaj ki 5 image upload limit puri ho chuki hai. Aap kal dobara image bhej sakte hain.");
                    return;
                }
                screenshotInput.click();
            });
            screenshotInput.addEventListener('change', function() {
                if (attachmentObjectUrl) {
                    URL.revokeObjectURL(attachmentObjectUrl);
                    attachmentObjectUrl = '';
                }
                if (screenshotInput.files.length > 0) {
                    var file = screenshotInput.files[0];
                    attachmentObjectUrl = URL.createObjectURL(file);
                    attachmentThumb.src = attachmentObjectUrl;
                    attachmentName.textContent = file.name || 'Attachment ready';
                    attachmentPreview.classList.add('show');
                    screenshotBtn.style.background = '#25D366';
                    input.placeholder = "Send attachment file...";
                } else {
                    attachmentPreview.classList.remove('show');
                    attachmentThumb.removeAttribute('src');
                    attachmentName.textContent = '';
                    screenshotBtn.style.background = '#666';
                    input.placeholder = "<?= __('Type your message…') ?>";
                }
            });

            attachmentClear.addEventListener('click', function() {
                screenshotInput.value = '';
                if (attachmentObjectUrl) {
                    URL.revokeObjectURL(attachmentObjectUrl);
                    attachmentObjectUrl = '';
                }
                attachmentPreview.classList.remove('show');
                attachmentThumb.removeAttribute('src');
                attachmentName.textContent = '';
                screenshotBtn.style.background = '#666';
                input.placeholder = "<?= __('Type your message…') ?>";
            });

            function pageVisible() {
                return !document.hidden && document.visibilityState === 'visible';
            }

            function ensureNotifPerm() {
                if (!('Notification' in window)) return;
                if (Notification.permission === 'default') {
                    Notification.requestPermission().catch(function() { });
                }
            }

            function nearBottom() {
                var threshold = 120;
                return (msgsEl.scrollHeight - msgsEl.scrollTop - msgsEl.clientHeight) < threshold;
            }

            function updateScrollArrow() {
                if (nearBottom()) scrollBtn.classList.remove('show');
                else scrollBtn.classList.add('show');
            }

            function scrollToBottom(smooth = true) {
                msgsEl.scrollTo({ top: msgsEl.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
                updateScrollArrow();
            }

            scrollBtn.addEventListener('click', function() { return scrollToBottom(true); });

            msgsEl.addEventListener('scroll', updateScrollArrow);

            var renderedMsgIds = new Set();

            function escHtml(s) {
                return (s ?? '').toString().replace(/[&<>"']/g, m => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
                }[m]));
            }

            function renderMessage(m) {
                var mid = parseInt(m.id);
                if (mid > 0 && renderedMsgIds.has(mid)) return;
                if (mid > 0) renderedMsgIds.add(mid);

                var row = document.createElement('div');
                row.className = 'row ' + (m.sender === 'admin' ? 'admin' : 'user');
                row.setAttribute('data-msg-id', m.id);

                var buLLle = document.createElement('div');
                buLLle.className = 'buLLle';

                var msg = document.createElement('div');
                msg.innerHTML = escHtml(m.message).replace(/\n/g, '<br>');
                buLLle.appendChild(msg);

                if (m.image) {
                    var img = document.createElement('img');
                    img.src = m.image;
                    img.style.maxWidth = '100%';
                    img.style.borderRadius = '8px';
                    img.style.marginTop = '8px';
                    img.style.display = 'block';
                    img.style.cursor = 'pointer';
                    img.onclick = function() { return window.open(m.image, '_blank'); };
                    buLLle.appendChild(img);
                }

                var meta = document.createElement('div');
                meta.className = 'meta';
                
                var tSpan = document.createElement('span');
                tSpan.innerText = m.t;
                meta.appendChild(tSpan);
                
                if (m.sender === 'user') {
                    var tw = document.createElement('span');
                    tw.className = 'tick-wrap';
                    tw.style.marginLeft = '4px';
                    tw.style.display = 'inline-flex';
                    tw.style.alignItems = 'center';
                    tw.style.verticalAlign = 'middle';
                    
                    var tImg = document.createElement('img');
                    tImg.className = 'chat-tick';
                    tImg.src = 'icons/single_tick.svg';
                    tImg.style.height = '12px';
                    tImg.style.width = 'auto';
                    
                    tw.appendChild(tImg);
                    meta.appendChild(tw);
                }
                
                buLLle.appendChild(meta);

                row.appendChild(buLLle);
                msgsEl.appendChild(row);
            }

            async function sendMessage() {
                var val = input.value.trim();
                var file = screenshotInput.files[0];
                if ((!val && !file) || isSending) return;

                isSending = true;
                triggerHaptic(50);
                sendBtn.disabled = true;
                sendBtn.classList.add('sending');
                sendBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i><span>Sending...</span>';
                input.placeholder = "Sending...";

                var fd = new FormData();
                fd.append('action', 'send');
                fd.append('message', val);
                if (file) fd.append('screenshot', file);

                try {
                    var r = await fetch('live_chat.php', { method: 'POST', body: fd });
                    var raw = await r.text();
                    var j = null;
                    try {
                        j = JSON.parse(raw);
                    } catch (e) {
                        throw new Error(raw ? raw.replace(/<[^>]*>/g, ' ').trim().slice(0, 160) : 'Invalid server response');
                    }
                    if (!r.ok) {
                        throw new Error(j.error || 'Message send failed');
                    }
                    if (j.ok) {
                        input.value = '';
                        input.placeholder = "<?= __('Type your message…') ?>";
                        screenshotInput.value = '';
                        if (attachmentObjectUrl) {
                            URL.revokeObjectURL(attachmentObjectUrl);
                            attachmentObjectUrl = '';
                        }
                        attachmentPreview.classList.remove('show');
                        attachmentThumb.removeAttribute('src');
                        attachmentName.textContent = '';
                        screenshotBtn.style.background = '';
                        if (j.remaining_uploads !== undefined) { updateUploadLimitDisplay(j.remaining_uploads); }
                        shouldScrollAfterSend = true;
                        await fetchMessages();
                        scrollToBottom(true);
                    } else {
                        alert(j.error || 'Message send failed');
                    }
                } catch (e) {
                    alert(e.message || 'Connection error. Please try again.');
                } finally {
                    isSending = false;
                    sendBtn.disabled = false;
                    sendBtn.classList.remove('sending');
                    sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
                    input.placeholder = "<?= __('Type your message…') ?>";
                    input.focus();
                }
            }

            async function fetchMessages() {
                try {
                    var r = await fetch(`live_chat.php?action=fetch&after_id=${lastId}`);
                    var j = await r.json();
                    if (j.remaining_uploads !== undefined) { updateUploadLimitDisplay(j.remaining_uploads); }
                    
                    if (j.ok) {
                        // UPDATE TICKS REGARDLESS OF NEW MESSAGES
                        var userAdminSeen = j.admin_seen || 0;
                        var userAdminOnline = j.admin_online || false;
                        
                        document.querySelectorAll('.row.user').forEach(function(r) {
                            var msgId = parseInt(r.getAttribute('data-msg-id') || 0);
                            var tickImg = r.querySelector('.chat-tick');
                            if (tickImg) {
                                if (msgId <= userAdminSeen) {
                                    if (tickImg.src.indexOf('green_tick.svg') === -1) {
                                        tickImg.src = 'icons/green_tick.svg';
                                    }
                                } else if (userAdminOnline) {
                                    if (tickImg.src.indexOf('double_tick.svg') === -1) {
                                        tickImg.src = 'icons/double_tick.svg';
                                    }
                                } else {
                                    if (tickImg.src.indexOf('single_tick.svg') === -1) {
                                        tickImg.src = 'icons/single_tick.svg';
                                    }
                                }
                            }
                        });

                        if (j.messages && j.messages.length > 0) {
                            var wasAtBottom = nearBottom();
                            var hasAdminMsg = false;
                            
                            j.messages.forEach(function(m) {
                                renderMessage(m);
                                var mid = parseInt(m.id);
                                if (mid > lastId) lastId = mid;
                                if (m.sender === 'admin' && mid > lastNotifiedAdminId) {
                                    hasAdminMsg = true;
                                    lastNotifiedAdminId = mid;
                                }
                            });

                            if (hasAdminMsg && !pageVisible()) {
                                notifySound.play().catch(function() { });
                                localStorage.setItem('ll_chat_last_notified_admin_id', lastNotifiedAdminId);
                                if ('Notification' in window && Notification.permission === 'granted') {
                                    new Notification('New Message', { body: 'You have a new message from Support.' });
                                }
                            }

                            if (firstLoad) {
                                var hint = msgsEl.querySelector('.hint');
                                if (hint) hint.remove();
                            }
                            if (firstLoad || wasAtBottom || shouldScrollAfterSend) scrollToBottom(!firstLoad);
                            shouldScrollAfterSend = false;
                            firstLoad = false;
                        } else if (firstLoad) {
                            msgsEl.innerHTML = '<div class="hint">' + '<?= __("No messages yet. Start the conversation!") ?>' + '</div>';
                            firstLoad = false;
                        }
                    }
                } catch (e) {
                }
            }

            async function markRead() {
                try { fetch('live_chat.php?action=maLL_read'); } catch (e) { }
            }

            async function updStatus() {
                try {
                    var r = await fetch('live_chat.php?action=status');
                    var j = await r.json();
                    if (j.online) {
                        statDot.classList.add('on');
                        statTxt.innerText = '<?= __("Online") ?>';
                    } else {
                        statDot.classList.remove('on');
                        statTxt.innerText = '<?= __("Offline") ?>';
                    }
                } catch (e) { }
            }

            var sendFired = false;
            function handleSendTap(e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                if (sendFired) return;
                sendFired = true;
                setTimeout(function() { sendFired = false; }, 400);
                sendMessage();
            }

            sendBtn.addEventListener('touchend', handleSendTap, { passive: false });
            sendBtn.addEventListener('click', handleSendTap);
            sendBtn.style.touchAction = 'manipulation';

            if (typeof composer !== 'undefined' && composer) {
                composer.addEventListener('submit', function(e) {
                    e.preventDefault();
                    sendMessage();
                });
            }
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sendMessage();
                }
            });

            function ping() {
                try { navigator.sendBeacon('live_chat.php', new URLSearchParams({ action: 'ping' })); } catch (e) { }
            }

            function applyKeyboard() {
                var ch = composer ? composer.getBoundingClientRect().height : 66;
                document.documentElement.style.setProperty('--compH', Math.round(ch) + 'px');

                if (!window.visualViewport) {
                    document.documentElement.style.setProperty('--kb', '0px');
                    return;
                }
                var vv = window.visualViewport;
                var kb = Math.max(0, Math.round(window.innerHeight - vv.height - vv.offsetTop));
                document.documentElement.style.setProperty('--kb', kb + 'px');

                if (nearBottom()) setTimeout(function() { return scrollToBottom(false); }, 30);
            }

            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', applyKeyboard);
                window.visualViewport.addEventListener('scroll', applyKeyboard);
            }
            window.addEventListener('resize', applyKeyboard);
            input.addEventListener('focus', function() { return setTimeout(applyKeyboard, 60); });
            input.addEventListener('blur', function() { return document.documentElement.style.setProperty('--kb', '0px'); });

            document.addEventListener('visibilitychange', function() {
                if (!pageVisible()) ensureNotifPerm();
                if (pageVisible()) {
                    markRead();
                    if (nearBottom()) setTimeout(function() { return scrollToBottom(false); }, 80);
                }
            });

            var qp = new URLSearchParams(window.location.search);
            if (qp.get('from') === 'notif') {
                markRead();
            }

            ensureNotifPerm();
            updStatus();
            fetchMessages();
            markRead();
            ping();
            setTimeout(function() { applyKeyboard(); scrollToBottom(false); }, 220);

            setInterval(updStatus, 5000);
            setInterval(fetchMessages, 1500);
            setInterval(ping, 15000);

        })();
    </script>
</body>
</html>



