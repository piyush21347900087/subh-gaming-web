<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';
require_once 'includes/fcm_helper.php';

/* ================== TIMEZONE ================== */
date_default_timezone_set('Asia/Kolkata');

// ✅ Allow background processing for large updates
set_time_limit(0);
ignore_user_abort(true);


$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) ? 'https' : 'http';

/* ================== CONFIG ================== */

define('GOOGLE_SA_JSON', __DIR__ . '/secure/service-account.json');
define('FCM_PROJECT_ID', 'kismat555-b686e');
define('PUSH_TOPIC', 'all_subhgaming');
define('PUSH_LOGO_URL', 'https://subhgaming.online/images/logo.png');

$host = $_SERVER['HTTP_HOST'] ?? 'subhgaming.online';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
define('WINS_COMPUTE_URL', $protocol . '://' . $host . $basePath . '/fetch_wins_api.php');
define('WINS_TRIGGER_KEY', 'LL_ADMIN_WINS_2025');
define('WINS_THROTTLE_SECONDS', 60);

/* ✅ Reverse log API */
define('REVERSE_API_URL', $protocol . '://' . $host . $basePath . '/api_reverse_market.php');
define('REVERSE_TRIGGER_KEY', 'LL_ADMIN_REVERSE_2026');


/* ✅ NEW: Remote Trigger from Master ✅ (Must be before session check) */
if (isset($_GET['remote_trigger']) && $_GET['remote_trigger'] === 'LL_MASTER_NOTIFY_2026') {
   $db = db();
   
   // Check API Mode
   $resMode = $db->query("SELECT value FROM rk_config WHERE `key`='result_mode' LIMIT 1");
   $resultMode = 'API';
   if ($resMode && $rm = $resMode->fetch_assoc()) {
       $resultMode = strtoupper($rm['value']);
   }
   if ($resultMode !== 'API') {
       header('Content-Type: application/json');
       echo json_encode(['status' => 'ignored', 'reason' => 'MANUAL_MODE_ACTIVE']);
       exit;
   }

   $targetMarket = $_GET['market'] ?? ''; 
   $targetResult = $_GET['result'] ?? '';
   $targetDate = $_GET['date'] ?? date('Y-m-d');
   $mtype = strtolower(trim($_GET['mtype'] ?? ''));

   // Auto-detect gali type if mtype is empty but market exists in gali_markets/gali_result
   if (empty($mtype) && !empty($targetMarket)) {
       $chkG = $db->prepare("SELECT id FROM gali_markets WHERE UPPER(TRIM(market_name)) = ? LIMIT 1");
       $tmEsc = strtoupper(trim($targetMarket));
       $chkG->bind_param('s', $tmEsc);
       $chkG->execute();
       if ($chkG->get_result()->num_rows > 0) {
           $mtype = 'gali';
       }
       $chkG->close();
   }
   
   // 1. Sync data first
   $masterApiUrl = "https://baba111.online/result_api.php";
   $selfDomain = $_SERVER['HTTP_HOST'] ?? 'unknown_domain';
   $fullUrl = $masterApiUrl . "?client_domain=" . urlencode($selfDomain) . "&market=" . urlencode($targetMarket) . "&date=" . urlencode($targetDate) . ($mtype === 'gali' ? "&mtype=gali" : "");

   $ch = curl_init($fullUrl);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_TIMEOUT, 10);
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
   curl_setopt($ch, CURLOPT_REFERER, $protocol . "://" . $selfDomain);
   $resp = curl_exec($ch);
   curl_close($ch);
   
   if ($resp) {
       $data = json_decode($resp, true);
       if ($data && $data['status'] === 'success' && !empty($data['results'])) {
           $accessToken = google_access_token_from_sa_cached(GOOGLE_SA_JSON);

           if ($mtype === 'gali') {
               $allowedMarkets = []; 
               $amRes = $db->query('SELECT UPPER(TRIM(market_name)) as m FROM gali_markets '); 
               if ($amRes) {
                   while($am = $amRes->fetch_assoc()){ $allowedMarkets[] = $am['m']; }
               }

               foreach ($data['results'] as $res) {
                   $m = strtoupper(trim($res['market']));
                   if (!empty($allowedMarkets) && !in_array($m, $allowedMarkets)) { continue; }
                   if (!empty($targetMarket) && $m !== strtoupper(trim($targetMarket))) { continue; }
                   
                   $rs = trim($res['result_str'] ?? $res['open_pana'] ?? '');
                   $st = $db->prepare("INSERT INTO gali_result 
                       (market, result_date, jodi, result_str, declared_at)
                       VALUES (?, ?, ?, ?, NOW())
                       ON DUPLICATE KEY UPDATE 
                       jodi=VALUES(jodi), result_str=VALUES(result_str), declared_at=NOW()");
                   $st->bind_param('ssss', $res['market'], $res['result_date'], $rs, $rs);
                   $st->execute();
                   $st->close();

                   if (isset($accessToken) && $accessToken) {
                       $dataNotif = [
                           'type' => 'gali_result',
                           'title' => $m,
                           'message' => 'Result: ' . $rs,
                           'market' => $m,
                           'result' => $rs,
                           'logo' => PUSH_LOGO_URL,
                           'icon' => 'ic_notification',
                           'uniq' => $m . '_' . $res['result_date'] . '_GALI_SYNC_' . time()
                       ];
                       fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $dataNotif, PUSH_TOPIC);
                   }
               }
           } else {
               $allowedMarkets = []; 
               $amRes = $db->query('SELECT UPPER(TRIM(market_name)) as m FROM markets '); 
               while($am = $amRes->fetch_assoc()){ $allowedMarkets[] = $am['m']; }

               foreach ($data['results'] as $res) {
                   $m = strtoupper(trim($res['market']));
                   if (!in_array($m, $allowedMarkets)) { continue; }
                   
                   // Prevent syncing old/missed data: only insert the specific market being triggered
                   if (!empty($targetMarket) && $m !== strtoupper(trim($targetMarket))) {
                       continue;
                   }
                   
                   $st = $db->prepare("INSERT INTO market_result 
                       (market, result_date, open_pana, open_digit, close_digit, close_pana, result_str, declared_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                       ON DUPLICATE KEY UPDATE 
                       open_pana=VALUES(open_pana), open_digit=VALUES(open_digit), 
                       close_digit=VALUES(close_digit), close_pana=VALUES(close_pana), 
                       result_str=VALUES(result_str), declared_at=NOW()");
                   $st->bind_param('sssssss', $res['market'], $res['result_date'], $res['open_pana'], $res['open_digit'], $res['close_digit'], $res['close_pana'], $res['result_str']);
                   $st->execute();
                   $st->close();
                   // Global Push Notification
                   if (isset($accessToken) && $accessToken) {
                       $fmtRs = $res['result_str'];
                       if (preg_match('/^\*{3}-([0-9*]{2})-\*{3}$/', $fmtRs, $matches)) {
                           $fmtRs = $matches[1];
                       }
                       $dataNotif = [
                           'type' => 'market_result',
                           'title' => $m,
                           'message' => $fmtRs,
                           'market' => $m,
                           'market_color' => '#0066cc',
                           'notification_color' => '#0066cc',
                           'title_html' => '<font color="#0066cc">' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</font>',
                           'result' => $fmtRs,
                           'logo' => PUSH_LOGO_URL,
                           'icon' => 'ic_notification',
                           'uniq' => $m . '_' . $res['result_date'] . '_SYNC_' . time()
                       ];
                       fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $dataNotif, PUSH_TOPIC);
                   }
               }
           }
       }
   }

    // 2. Trigger Win Computation & Notifications
    $clientKey = 'LL_ADMIN_WINS_2025';
    $notifyKey = 'LL_ADMIN_NOTIFY_2026';
    
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . "://" . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    
    function trigger_ping($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    }
    
    // Output response immediately to prevent Master timeout
    if (function_exists('fastcgi_finish_request')) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'triggered' => true, 'note' => 'Background processing started']);
        fastcgi_finish_request(); // Continue execution in background
    }

    $winQueryParams = [
        'key' => $clientKey,
        'trigger' => '1',
        'from' => $targetDate,
        'to' => $targetDate,
        'market' => $targetMarket,
        '_' => time()
    ];
    if ($mtype === 'gali') {
        $winQueryParams['mtype'] = 'gali';
    }
    $q = http_build_query($winQueryParams);
    trigger_ping($baseUrl . "/fetch_wins_api.php?" . $q);

    $nq = http_build_query([
        'key' => $notifyKey,
        'from' => $targetDate,
        'to' => $targetDate,
        'market' => $targetMarket,
        '_' => time()
    ]);
    trigger_ping($baseUrl . "/notify_wins.php?" . $nq);
    
    $notifStatus = (isset($dataNotif)) ? "Notif Attempted" : "No Notif Substituted";
    $logMsg = date('[Y-m-d H:i:s]') . " [REMOTE_TRIGGER] Market: " . $targetMarket . " | Date: " . $targetDate . " | Domain: " . $selfDomain . " | " . $notifStatus . "\n";
    @file_put_contents('remote_debug.txt', $logMsg, FILE_APPEND);

    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'triggered' => true, 'domain' => $selfDomain]);
    exit;
}

if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

/* ================== DEBUG ================== */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);



/* ================== DATABASE ================== */
try {
    $db = db();
    $db->query("SET time_zone = '+05:30'");
} catch (Exception $e) {
    die('DB error: ' . $e->getMessage());
}

/* ================== BUSINESS / MARKET DATE ================== */
$cutoffHour = 3;
$currentHour = (int) date('G');
$marketDate = ($currentHour < $cutoffHour) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
$displayDate = date('d M Y', strtotime($marketDate));

/* ================== MASTER API AUTO SYNC (Admin View) ================== */
$masterApiUrl = "https://baba111.online/result_api.php";
$apiStatus = "Checking...";
$remainingDays = 0;

function sync_from_master($db, $apiUrl) {
    try {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $selfDomain = $_SERVER['HTTP_HOST'] ?? 'unknown_domain';
        $mktParam = $_GET['market'] ?? '';
        $dateParam = $_GET['date'] ?? '';
        $fullUrl = $apiUrl . "?client_domain=" . urlencode($selfDomain) . "&market=" . urlencode($mktParam) . "&date=" . urlencode($dateParam);
        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_REFERER, $protocol . "://" . $selfDomain);
        $resp = curl_exec($ch);
        curl_close($ch);
        if (!$resp) return ['status' => 'offline'];
        $data = json_decode($resp, true);
        if ($data && $data['status'] === 'success' && !empty($data['results'])) {
            $allowedMarkets = []; $amRes = $db->query('SELECT UPPER(TRIM(market_name)) as m FROM markets '); while($am = $amRes->fetch_assoc()){ $allowedMarkets[] = $am['m']; }
            $allowedGaliMarkets = [];
            $gRes = $db->query('SELECT UPPER(TRIM(market_name)) as m FROM gali_markets ');
            if ($gRes) {
                while ($g = $gRes->fetch_assoc()) {
                    $allowedGaliMarkets[] = $g['m'];
                }
            }
            $accessToken = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
            foreach ($data['results'] as $res) {
                $m = strtoupper(trim($res['market']));
                
                // --- GALI MARKET SYNC ---
                if (in_array($m, $allowedGaliMarkets, true)) {
                    $mktParam = $_GET['market'] ?? '';
                    if (!empty($mktParam) && $m !== strtoupper(trim($mktParam))) {
                        continue;
                    }
                    
                    $jodi = '';
                    $resStr = trim($res['result_str'] ?? '');
                    $parts = explode('-', $resStr);
                    if (count($parts) === 3) {
                        $jodi = $parts[1];
                    } else {
                        $jodi = $resStr;
                    }
                    
                    if ($jodi !== '' && stripos($jodi, '*') === false) {
                        $stGali = $db->prepare("INSERT INTO gali_result (market, result_date, jodi, result_str, declared_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE jodi=VALUES(jodi), result_str=VALUES(result_str), declared_at=NOW()");
                        $stGali->bind_param('ssss', $res['market'], $res['result_date'], $jodi, $resStr);
                        $stGali->execute();
                        $stGali->close();
                    }
                    
                    // Note: Push notification is sent below in section 2 together with win calculation
                    continue;
                }
                
                // --- STANDARD MARKET SYNC ---
                if (!in_array($m, $allowedMarkets)) { continue; }
            }
        }
        return $data;
    } catch (Exception $e) { return ['status' => 'error']; }
}

/* ================== GLOBAL API MODE TOGGLE ================== */
// Ensure table exists to prevent crashes
$db->query("CREATE TABLE IF NOT EXISTS rk_config (
    `key` VARCHAR(50) PRIMARY KEY,
    `value` VARCHAR(255) NOT NULL
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_api_mode') {
    $newMode = $_POST['mode'] === 'MANUAL' ? 'MANUAL' : 'API';
    $db->query("UPDATE rk_config SET value='$newMode' WHERE `key`='result_mode'");
    if ($db->affected_rows === 0) {
        $db->query("INSERT IGNORE INTO rk_config (`key`, `value`) VALUES ('result_mode', '$newMode')");
    }
    echo json_encode(['ok' => true, 'mode' => $newMode]);
    exit;
}

$resMode = $db->query("SELECT value FROM rk_config WHERE `key`='result_mode' LIMIT 1");
$resultMode = 'API';
if ($resMode && $rm = $resMode->fetch_assoc()) {
    $resultMode = strtoupper($rm['value']);
}

$syncData = null;
if ($resultMode === 'API') {
    $syncData = sync_from_master($db, $masterApiUrl);
}

if ($syncData) {
    $apiStatus = isset($syncData['api_active']) ? ($syncData['api_active'] ? "ACTIVE" : "DISABLED") : "OFFLINE";
    $remainingDays = $syncData['remaining_days'] ?? 0;
} else {
    $apiStatus = "MANUAL MODE";
    $remainingDays = 0;
}

$prevDate = date('Y-m-d', strtotime($marketDate . ' -1 day'));
$prevDisplayDate = date('d M Y', strtotime($prevDate));

/* ================== CSRF ================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
function csrf_token(): string
{
    return $_SESSION['csrf_token'] ?? '';
}
function csrf_check(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST')
        return true;
    $t = $_POST['csrf_token'] ?? '';
    return is_string($t) && $t !== '' && hash_equals(csrf_token(), $t);
}

/* ================== SMALL HELPERS ================== */
function esc($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function ll_money($v): float
{
    return round((float) $v, 2);
}

function ll_format_result(string $market, string $res): string
{
    // Simplified: Show full result unless it follows the middle-only pattern exactly
    if (preg_match('/^\*{3}-([0-9*]{2})-\*{3}$/', $res, $matches)) {
        return $matches[1];
    }
    return $res;
}

/**
 * Normalizes a user ID by adding an "LL" prefix and padding for numeric-only inputs,
 * or padding existing prefix
 */
function normalize_user_id(string $uid): string
{
    if (function_exists('rk_norm_uid')) return rk_norm_uid($uid);
    $u = strtoupper(trim($uid));
    if (preg_match('/^\d+$/', $u)) {
        return "KR" . str_pad($u, 2, "0", STR_PAD_LEFT);
    }
    if (preg_match('/^([A-Z]+)(\d+)$/', $u, $m)) {
        return $m[1] . str_pad($m[2], 2, "0", STR_PAD_LEFT);
    }
    return $u;
}



function hasColumn(mysqli $db, string $table, string $col): bool
{
    try {
        $q = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
        $q->bind_param('ss', $table, $col);
        $q->execute();
        $q->bind_result($c);
        $q->fetch();
        $q->close();
        return ((int) $c) > 0;
    } catch (Throwable $e) {
        return false;
    }
}
function hasTable(mysqli $db, string $table): bool
{
    try {
        $q = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
        $q->bind_param('s', $table);
        $q->execute();
        $q->bind_result($c);
        $q->fetch();
        $q->close();
        return ((int) $c) > 0;
    } catch (Throwable $e) {
        return false;
    }
}
function hasAnyColumn(mysqli $db, string $table, array $cols): string
{
    foreach ($cols as $c)
        if (hasColumn($db, $table, $c))
            return $c;
    return '';
}

/* ✅ topic sanitize (FCM rule) */
function sanitize_topic(string $topic): string
{
    $t = trim($topic);
    if ($t === '')
        return '';
    $t = preg_replace('/[^a-zA-Z0-9\-\_\.\~\%]/', '_', $t);
    if (strlen($t) > 200)
        $t = substr($t, 0, 200);
    return $t;
}

/* ================== AUTO SCHEMA FIXES ================== */
function ensureWiningColumns(mysqli $db): void
{
    if (!hasTable($db, 'wining'))
        return;
    try {
        if (!hasColumn($db, 'wining', 'notified'))
            @$db->query("ALTER TABLE wining ADD COLUMN notified TINYINT(1) NOT NULL DEFAULT 0");
        if (!hasColumn($db, 'wining', 'notified_at'))
            @$db->query("ALTER TABLE wining ADD COLUMN notified_at DATETIME NULL");
        if (!hasColumn($db, 'wining', 'session_type'))
            @$db->query("ALTER TABLE wining ADD COLUMN session_type VARCHAR(10) NULL DEFAULT NULL");
        if (!hasColumn($db, 'wining', 'win_amount'))
            @$db->query("ALTER TABLE wining ADD COLUMN win_amount DECIMAL(10,2) DEFAULT 0");
        if (!hasColumn($db, 'wining', 'left_digit'))
            @$db->query("ALTER TABLE wining ADD COLUMN left_digit VARCHAR(5) NULL");
        if (!hasColumn($db, 'wining', 'right_digit'))
            @$db->query("ALTER TABLE wining ADD COLUMN right_digit VARCHAR(5) NULL");
        if (!hasColumn($db, 'wining', 'jodi'))
            @$db->query("ALTER TABLE wining ADD COLUMN jodi VARCHAR(5) NULL");

        if (!hasColumn($db, 'wining', 'win_date')) {
            @$db->query("ALTER TABLE wining ADD COLUMN win_date DATE NULL");
            @$db->query("ALTER TABLE wining ADD INDEX idx_win_date (win_date)");
        }
        if (!hasColumn($db, 'wining', 'bet_key')) {
            @$db->query("ALTER TABLE wining ADD COLUMN bet_key VARCHAR(128) NULL");
            @$db->query("ALTER TABLE wining ADD UNIQUE INDEX idx_bet_key (bet_key)");
        }
        if (!hasColumn($db, 'wining', 'business_date')) {
            @$db->query("ALTER TABLE wining ADD COLUMN business_date DATE NULL");
        }
    } catch (Throwable $e) {
    }
}
function ensureReverseLogTable(mysqli $db): void
{
    try {
        $db->query("
            CREATE TABLE IF NOT EXISTS market_reverse_log (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              action_ref VARCHAR(64) NOT NULL,
              admin_id VARCHAR(50) NOT NULL,
              market VARCHAR(80) NOT NULL,
              result_date DATE NOT NULL,
              delete_scope ENUM('OPEN','CLOSE','FULL') NOT NULL,

              user_id VARCHAR(50) NOT NULL,
              bid_refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
              win_reverse_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
              bids_deleted INT NOT NULL DEFAULT 0,
              wins_deleted INT NOT NULL DEFAULT 0,

              details_json LONGTEXT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

              PRIMARY KEY (id),
              KEY idx_market_date (market, result_date),
              KEY idx_user (user_id),
              KEY idx_action_ref (action_ref)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
    }
}

ensureWiningColumns($db);
ensureReverseLogTable($db);
require_once 'cleanup_wins_locks.php';

/* ================== MYSQL GET_LOCK ================== */
function mysql_get_lock(mysqli $db, string $key, int $timeoutSec = 3): bool
{
    try {
        $st = $db->prepare("SELECT GET_LOCK(?, ?) AS l");
        $st->bind_param('si', $key, $timeoutSec);
        $st->execute();
        $res = $st->get_result()->fetch_assoc();
        $st->close();
        return (int) ($res['l'] ?? 0) === 1;
    } catch (Throwable $e) {
        return false;
    }
}
function mysql_release_lock(mysqli $db, string $key): void
{
    try {
        $st = $db->prepare("SELECT RELEASE_LOCK(?)");
        $st->bind_param('s', $key);
        $st->execute();
        $st->close();
    } catch (Throwable $e) {
    }
}

/* ================== ✅ wins_run_lock CLEAR (FIX) ==================
   Support BOTH schemas:
   A) market/run_date/result_str/last_run_at...
   B) lock_key/last_run/is_running/last_result_str...
*/
function clear_wins_run_lock(mysqli $db, string $market, string $date): int
{
    if (!hasTable($db, 'wins_run_lock'))
        return 0;

    $market = strtoupper(trim($market));
    $date = trim($date);
    $aff = 0;

    // Schema A
    $hasMarket = hasColumn($db, 'wins_run_lock', 'market');
    $hasRunDate = hasColumn($db, 'wins_run_lock', 'run_date');
    if ($hasMarket && $hasRunDate) {
        try {
            $st = $db->prepare("DELETE FROM wins_run_lock WHERE UPPER(TRIM(market))=? AND run_date=?");
            $st->bind_param('ss', $market, $date);
            $st->execute();
            $aff += (int) $st->affected_rows;
            $st->close();
        } catch (Throwable $e) {
        }
        return $aff;
    }

    // Schema B (lock_key based)
    $hasLockKey = hasColumn($db, 'wins_run_lock', 'lock_key');
    if ($hasLockKey) {
        $dateNoDash = str_replace('-', '', $date);

        try {
            $like1 = '%' . $market . '%';
            $like2 = '%' . $date . '%';
            $like3 = '%' . $dateNoDash . '%';

            $st = $db->prepare("
                DELETE FROM wins_run_lock
                WHERE (UPPER(TRIM(lock_key)) LIKE ?)
                  AND (lock_key LIKE ? OR lock_key LIKE ?)
            ");
            $st->bind_param('sss', $like1, $like2, $like3);
            $st->execute();
            $aff += (int) $st->affected_rows;
            $st->close();
        } catch (Throwable $e) {
        }

        if ($aff === 0) {
            try {
                $like1 = '%' . $market . '%';
                $st = $db->prepare("DELETE FROM wins_run_lock WHERE UPPER(TRIM(lock_key)) LIKE ?");
                $st->bind_param('s', $like1);
                $st->execute();
                $aff += (int) $st->affected_rows;
                $st->close();
            } catch (Throwable $e) {
            }
        }

        return $aff;
    }

    return 0;
}

/* ================== wins_run_lock throttle (schema A only) ================== */
function can_run_wins(mysqli $db, string $market, string $date, string $resultStr): bool
{
    if (!hasTable($db, 'wins_run_lock'))
        return true;

    $hasMarket = hasColumn($db, 'wins_run_lock', 'market');
    $hasDate = hasColumn($db, 'wins_run_lock', 'run_date');
    $hasRes = hasColumn($db, 'wins_run_lock', 'result_str');
    $hasRunAt = hasColumn($db, 'wins_run_lock', 'last_run_at');
    if (!$hasMarket || !$hasDate || !$hasRes || !$hasRunAt)
        return true;

    try {
        $st = $db->prepare("SELECT last_run_at FROM wins_run_lock WHERE market=? AND run_date=? AND result_str=? LIMIT 1");
        $st->bind_param('sss', $market, $date, $resultStr);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row || empty($row['last_run_at']))
            return true;
        $last = strtotime((string) $row['last_run_at']);
        if (!$last)
            return true;

        return (time() - $last) >= WINS_THROTTLE_SECONDS;
    } catch (Throwable $e) {
        return true;
    }
}
function maLL_run_wins(mysqli $db, string $market, string $date, string $resultStr, int $httpCode, string $statusNote = ''): void
{
    if (!hasTable($db, 'wins_run_lock'))
        return;

    $hasMarket = hasColumn($db, 'wins_run_lock', 'market');
    $hasDate = hasColumn($db, 'wins_run_lock', 'run_date');
    $hasRes = hasColumn($db, 'wins_run_lock', 'result_str');
    $hasRunAt = hasColumn($db, 'wins_run_lock', 'last_run_at');
    if (!$hasMarket || !$hasDate || !$hasRes || !$hasRunAt)
        return;

    $hasHttp = hasColumn($db, 'wins_run_lock', 'http_code');
    $hasNote = hasColumn($db, 'wins_run_lock', 'note');

    try {
        if ($hasHttp && $hasNote) {
            $st = $db->prepare("
                INSERT INTO wins_run_lock (market, run_date, result_str, last_run_at, http_code, note)
                VALUES (?,?,?,NOW(),?,?)
                ON DUPLICATE KEY UPDATE last_run_at=NOW(), http_code=VALUES(http_code), note=VALUES(note)
            ");
            $st->bind_param('sssis', $market, $date, $resultStr, $httpCode, $statusNote);
        } elseif ($hasHttp) {
            $st = $db->prepare("
                INSERT INTO wins_run_lock (market, run_date, result_str, last_run_at, http_code)
                VALUES (?,?,?,NOW(),?)
                ON DUPLICATE KEY UPDATE last_run_at=NOW(), http_code=VALUES(http_code)
            ");
            $st->bind_param('sssi', $market, $date, $resultStr, $httpCode);
        } else {
            $st = $db->prepare("
                INSERT INTO wins_run_lock (market, run_date, result_str, last_run_at)
                VALUES (?,?,?,NOW())
                ON DUPLICATE KEY UPDATE last_run_at=NOW()
            ");
            $st->bind_param('sss', $market, $date, $resultStr);
        }
        $st->execute();
        $st->close();
    } catch (Throwable $e) {
    }
}

/* ================== wining meta flags ================== */
$GLOBALS['HAS_WIN_DATE'] = hasColumn($db, 'wining', 'win_date');
$GLOBALS['HAS_BUSINESS_DATE'] = hasColumn($db, 'wining', 'business_date');
$GLOBALS['HAS_WIN_TIME'] = hasColumn($db, 'wining', 'win_time');
$GLOBALS['HAS_BET_TYPE'] = hasColumn($db, 'wining', 'bet_type');
$GLOBALS['HAS_BET_KEY'] = hasColumn($db, 'wining', 'bet_key');
$GLOBALS['HAS_NOTIFIED_AT'] = hasColumn($db, 'wining', 'notified_at');
$GLOBALS['HAS_NOTIFIED'] = hasColumn($db, 'wining', 'notified');
$GLOBALS['HAS_WIN_AMOUNT'] = hasColumn($db, 'wining', 'win_amount');
$GLOBALS['HAS_WIN_BAL'] = hasColumn($db, 'wining', 'wining_balance');
$GLOBALS['HAS_STATUS'] = hasColumn($db, 'wining', 'status');
$GLOBALS['HAS_SESSION_TYPE'] = hasColumn($db, 'wining', 'session_type');

/* Phase context used by helpers */
$GLOBALS['LL_PHASE_SESSION'] = 'open';

  /* ================== MARKETS ================== */
  $markets = [];
  $gali_markets = [];
  try {
      $res = $db->query("SELECT market_name FROM markets ORDER BY sort_order ASC, id ASC");
      if ($res) {
          while ($row = $res->fetch_assoc()) {
              $markets[] = strtoupper(trim($row['market_name']));
          }
          $res->free();
      } else {
          error_log("Failed to fetch regular markets: " . $db->error);
      }
  
      // Include Gali Desawar markets
      $gRes = $db->query("SELECT market_name FROM gali_markets ORDER BY sort_order ASC, id ASC");
      if ($gRes) {
          while ($row = $gRes->fetch_assoc()) {
              $m = strtoupper(trim($row['market_name']));
              if (!in_array($m, $markets)) {
                  $markets[] = $m;
              }
              $gali_markets[] = $m;
          }
          $gRes->free();
      } else {
          error_log("Failed to fetch gali markets: " . $db->error);
      }
  } catch (Throwable $e) {
      error_log("Exception in fetching markets: " . $e->getMessage());
  }
if (empty($markets))
    $markets = ['WELCOME', 'SURAJ', 'GULAB', 'PAYAL', 'RUBY', 'DIVYA'];

/* ================== FCM CONFIG ================== */
if (!defined('GOOGLE_SA_JSON'))
    define('GOOGLE_SA_JSON', __DIR__ . '/secure/service-account.json');
if (!defined('FCM_PROJECT_ID'))
    define('FCM_PROJECT_ID', 'kismat555-b686e');

/* ================== WINS COMPUTE TRIGGER ================== */
function trigger_wins_compute(mysqli $db, string $market, string $date, string $resultStr): array
{
    $market = strtoupper(trim($market));
    $date = trim($date);

    $lockKey = "LL_wins:" . $market . ":" . $date;
    $got = mysql_get_lock($db, $lockKey, 3);
    if (!$got)
        return ['http' => 429, 'err' => 'locked', 'body' => 'wins compute busy'];

    try {
        if (!can_run_wins($db, $market, $date, $resultStr)) {
            mysql_release_lock($db, $lockKey);
            return ['http' => 208, 'err' => 'throttled', 'body' => 'same result recently computed'];
        }

        $q = http_build_query([
            'from' => $date,
            'to' => $date,
            'market' => $market,
            'trigger' => '1',
            'key' => WINS_TRIGGER_KEY,
            '_' => time()
        ]);

        $ch = curl_init(WINS_COMPUTE_URL . '?' . $q);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSLVERSION => 6, // TLSv1.2
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_USERAGENT => 'KISMAT555-Admin-Trigger',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => [
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'Connection: close'
            ]
        ]);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        maLL_run_wins($db, $market, $date, $resultStr, (int) $http, $err ?: 'ok');
        mysql_release_lock($db, $lockKey);

        return ['http' => $http, 'err' => $err, 'body' => $body];
    } catch (Throwable $e) {
        maLL_run_wins($db, $market, $date, $resultStr, 500, 'exception');
        mysql_release_lock($db, $lockKey);
        return ['http' => 500, 'err' => 'exception', 'body' => $e->getMessage()];
    }
}

/* ================== DATA FETCH HELPERS ================== */
function fetch_bidders_user_ids(mysqli $db, string $market, string $date): array
{
    $tries = [
        ["SELECT DISTINCT user_id FROM bid WHERE market = ? AND DATE(date_time) = ?", 'ss'],
        ["SELECT DISTINCT user_id FROM bid WHERE market = ? AND DATE(bid_time)  = ?", 'ss'],
        ["SELECT DISTINCT user_id FROM bids WHERE market = ? AND DATE(date_time)= ?", 'ss'],
        ["SELECT DISTINCT user_id FROM bids WHERE market = ? AND DATE(bid_time) = ?", 'ss'],
        ["SELECT DISTINCT user_id FROM bid_history WHERE market = ? AND DATE(date_time)= ?", 'ss'],
        ["SELECT DISTINCT user_id FROM bid_history WHERE market = ? AND DATE(bid_time) = ?", 'ss'],
        ["SELECT DISTINCT user_id FROM bid WHERE market = ? AND bid_date = ?", 'ss'],
        ["SELECT DISTINCT user_id FROM bids WHERE market = ? AND bid_date = ?", 'ss'],
        ["SELECT DISTINCT user_id FROM bid WHERE market_name = ? AND DATE(date_time)= ?", 'ss'],
        ["SELECT DISTINCT user_id FROM bids WHERE market_name = ? AND DATE(date_time)= ?", 'ss'],

    ];

    foreach ($tries as $t) {
        try {
            [$sql, $types] = $t;
            $st = $db->prepare($sql);
            $st->bind_param($types, $market, $date);
            $st->execute();
            $res = $st->get_result();
            $out = [];
            while ($row = $res->fetch_assoc()) {
                $uid = trim((string) ($row['user_id'] ?? ''));
                if ($uid !== '')
                    $out[strtoupper($uid)] = true;
            }
            $st->close();
            if (!empty($out))
                return array_keys($out);
        } catch (Throwable $e) {
        }
    }
    return [];
}

function wining_date_where(mysqli $db, bool $hasBusinessDate, bool $hasWinDate, bool $hasWinTime): string
{
    if ($hasWinDate)
        return "win_date = ?";
    if ($hasBusinessDate)
        return "business_date = ?";
    if ($hasWinTime)
        return "DATE(win_time) = ?";
    if (hasColumn($db, 'wining', 'created_at'))
        return "DATE(created_at) = ?";
    return "DATE(win_time) = ?";
}

/**
 * ✅ Phase settle rule:
 * - OPEN: only session_type=open AND exclude (half_sangam/full_sangam/jodi_digit)
 * - CLOSE: session_type=close OR bet_type in (half_sangam/full_sangam/jodi_digit)
 */
function phase_wins_where_clause(mysqli $db, string $alias = 'w'): string
{
    $phase = strtolower(trim((string) ($GLOBALS['LL_PHASE_SESSION'] ?? 'open')));

    $hasSess = hasColumn($db, 'wining', 'session_type');
    $hasType = hasColumn($db, 'wining', 'bet_type');

    if (!$hasSess && !$hasType)
        return "1=1";

    if ($phase === 'open') {
        if ($hasSess && $hasType) {
            // General Rule: If this is an 'Open' result but contains Jodi/Sangam, it's likely a single-declaration market
            // or the user specifically wants to settle these bets now.
            // We only exclude them if we KNOW a 'Close' session will come later.
            // For now, let's satisfy the "hata do" request by making it simpler.
            return "(LOWER(TRIM($alias.session_type))='open')";
        }
        if ($hasSess)
            return "(LOWER(TRIM($alias.session_type))='open')";
        return "1=1";
    }

    // close
    if ($hasSess && $hasType) {
        return "((LOWER(TRIM($alias.session_type))='close') OR $alias.bet_type IN ('half_sangam','full_sangam','jodi_digit'))";
    }
    if ($hasSess)
        return "(LOWER(TRIM($alias.session_type))='close')";
    return "1=1";
}

/* ✅ rows for WIN notifications (ALWAYS use wining_balance) */
function fetch_unnotified_wins_rows(mysqli $db, string $market, string $date, bool $ignorePhase = false, bool $ignoreNotified = false): array
{
    $rows = [];
    $total = 0;

    $hasNotified = (bool) ($GLOBALS['HAS_NOTIFIED'] ?? false);

    $winCol = "COALESCE(w.wining_balance, 0)";
    $dateWhere = wining_date_where($db, (bool) $GLOBALS['HAS_BUSINESS_DATE'], (bool) $GLOBALS['HAS_WIN_DATE'], (bool) $GLOBALS['HAS_WIN_TIME']);

    $phaseClause = $ignorePhase ? "1=1" : phase_wins_where_clause($db, 'w');

    $sql = "
        SELECT 
            w.id, w.user_id,
            w.amount AS bet_amount,
            {$winCol} AS stored_win_amt,
            w.bet_type, w.bet_key,
            m.payout_single_digit, m.payout_jodi_digit, m.payout_single_pana,
            m.payout_double_pana, m.payout_triple_pana, m.payout_half_sangam, m.payout_full_sangam,
            b.amount AS origin_bet_amount
        FROM wining w
        LEFT JOIN markets m ON UPPER(TRIM(m.market_name)) = UPPER(TRIM(w.market))
        LEFT JOIN bid b ON b.id = w.bid_id
        WHERE UPPER(TRIM(w.market)) = UPPER(TRIM(?))
          AND {$dateWhere}
          AND ({$phaseClause})
    ";
    if ($hasNotified && !$ignoreNotified)
        $sql .= " AND w.notified = 0";
    $sql .= " AND (UPPER(TRIM(w.status))='WIN' OR UPPER(TRIM(w.status))='WINNING')";
    $sql .= " ORDER BY w.id ASC";

    try {
        $st = $db->prepare($sql);
        $st->bind_param('ss', $market, $date);
        $st->execute();
        $res = $st->get_result();

        while ($r = $res->fetch_assoc()) {
            $total++;

            $id = (int) ($r['id'] ?? 0);
            $uid = strtoupper(trim((string) ($r['user_id'] ?? '')));
            if ($id <= 0 || $uid === '')
                continue;

            $finalAmount = (float) ($r['stored_win_amt'] ?? 0);

            // fallback calc if stored win is 0
            $betAmt = (float) ($r['bet_amount'] ?? 0);
            if ($betAmt <= 0)
                $betAmt = (float) ($r['origin_bet_amount'] ?? 0);

            $rawType = strtolower(trim((string) ($r['bet_type'] ?? '')));
            $bType = str_replace([' ', '-', '_'], '', $rawType);

            if ($finalAmount <= 0 && $betAmt > 0) {
                $rate = 0;
                if (strpos($bType, 'singlepana') !== false)
                    $rate = (int) ($r['payout_single_pana'] ?? 0);
                elseif (strpos($bType, 'doublepana') !== false)
                    $rate = (int) ($r['payout_double_pana'] ?? 0);
                elseif (strpos($bType, 'triplepana') !== false)
                    $rate = (int) ($r['payout_triple_pana'] ?? 0);
                elseif (strpos($bType, 'halfsangam') !== false)
                    $rate = (int) ($r['payout_half_sangam'] ?? 0);
                elseif (strpos($bType, 'fullsangam') !== false)
                    $rate = (int) ($r['payout_full_sangam'] ?? 0);
                elseif (strpos($bType, 'singledigit') !== false)
                    $rate = (int) ($r['payout_single_digit'] ?? 0);
                elseif (strpos($bType, 'jodi') !== false)
                    $rate = (int) ($r['payout_jodi_digit'] ?? 0);

                if ($rate > 0)
                    $finalAmount = ($betAmt / 10) * $rate;
            }

            $rows[] = [
                'id' => $id,
                'user_id' => $uid,
                'amount' => $finalAmount,
                'bet_type' => (string) ($r['bet_type'] ?? ''),
                'bet_key' => (string) ($r['bet_key'] ?? '')
            ];
        }

        $st->close();
    } catch (Throwable $e) {
    }

    return ['rows' => $rows, 'total_rows' => $total];
}

function fetch_phase_winner_user_ids(mysqli $db, string $market, string $date): array
{
    $out = [];
    $dateWhere = wining_date_where($db, (bool) $GLOBALS['HAS_BUSINESS_DATE'], (bool) $GLOBALS['HAS_WIN_DATE'], (bool) $GLOBALS['HAS_WIN_TIME']);
    $phaseClause = phase_wins_where_clause($db, 'w');

    try {
        $st = $db->prepare("
            SELECT DISTINCT UPPER(TRIM(w.user_id)) AS uid
            FROM wining w
            WHERE w.market = ?
              AND {$dateWhere}
              AND ({$phaseClause})
              AND (UPPER(TRIM(w.status))='WIN' OR UPPER(TRIM(w.status))='WINNING')
        ");
        $st->bind_param('ss', $market, $date);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $uid = strtoupper(trim((string) ($r['uid'] ?? '')));
            if ($uid !== '')
                $out[$uid] = true;
        }
        $st->close();
    } catch (Throwable $e) {
    }

    return array_keys($out);
}

function fetch_phase_winner_amount_sum_map(mysqli $db, string $market, string $date): array
{
    $map = [];
    $dateWhere = wining_date_where($db, (bool) $GLOBALS['HAS_BUSINESS_DATE'], (bool) $GLOBALS['HAS_WIN_DATE'], (bool) $GLOBALS['HAS_WIN_TIME']);
    $phaseClause = phase_wins_where_clause($db, 'w');

    $amtExpr = "COALESCE(w.wining_balance,0)";

    try {
        $st = $db->prepare("
            SELECT UPPER(TRIM(w.user_id)) AS uid, SUM({$amtExpr}) AS amt
            FROM wining w
            WHERE w.market = ?
              AND {$dateWhere}
              AND ({$phaseClause})
              AND (UPPER(TRIM(w.status))='WIN' OR UPPER(TRIM(w.status))='WINNING')
            GROUP BY UPPER(TRIM(w.user_id))
        ");
        $st->bind_param('ss', $market, $date);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $u = strtoupper(trim((string) ($r['uid'] ?? '')));
            if ($u === '')
                continue;
            $map[$u] = (float) ($r['amt'] ?? 0);
        }
        $st->close();
    } catch (Throwable $e) {
    }

    return $map;
}

function maLL_wins_notified(mysqli $db, array $ids): int
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (empty($ids))
        return 0;
    if (empty($GLOBALS['HAS_NOTIFIED']))
        return 0;

    $in = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $set = "notified = 1";
    if (!empty($GLOBALS['HAS_NOTIFIED_AT']))
        $set .= ", notified_at = NOW()";

    try {
        $sql = "UPDATE wining SET {$set} WHERE id IN ($in)";
        $st = $db->prepare($sql);
        $st->bind_param($types, ...$ids);
        $st->execute();
        $aff = $st->affected_rows;
        $st->close();
        return (int) $aff;
    } catch (Throwable $e) {
        return 0;
    }
}

function fetch_token_map_for_users(mysqli $db, array $userIds): array
{
    // ✅ Build a robust lookup list:
    // - exact uid (as-is, uppercased)
    // - raw numeric variant (if prefixed)
    // - legacy LL + 5 digits (fallback for old stored tokens)
    $formattedIds = [];
    foreach ($userIds as $uid) {
        $uid = strtoupper(trim((string) $uid));
        if ($uid === '')
            continue;

        $formattedIds[] = $uid;

        // raw numeric variant for prefixed ids
        if (preg_match('/^(?:LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|DS|DM|MATKA)0*(\d+)$/', $uid, $matches)) {
            $formattedIds[] = $matches[1];

            // legacy LL padded variant
            $formattedIds[] = "LL" . str_pad($matches[1], 5, "0", STR_PAD_LEFT);
        }

        // numeric-only -> legacy LL padded
        if (preg_match('/^\d+$/', $uid)) {
            $formattedIds[] = "LL" . str_pad($uid, 5, "0", STR_PAD_LEFT);
        }
    }
    $userIds = array_values(array_unique($formattedIds));

    $map = [];
    if (empty($userIds))
        return $map;

    if (
        !hasTable($db, 'user_fcm_tokens') ||
        !hasColumn($db, 'user_fcm_tokens', 'user_id') ||
        !hasColumn($db, 'user_fcm_tokens', 'fcm_token')
    )
        return $map;

    $hasId = hasColumn($db, 'user_fcm_tokens', 'id');

    $in = implode(',', array_fill(0, count($userIds), '?'));
    $types = str_repeat('s', count($userIds));

    try {
        if ($hasId) {
            // Simplified: Fetch latest token per user_id from the list
            $sql = "
                SELECT t.user_id, t.fcm_token
                FROM user_fcm_tokens t
                INNER JOIN (
                    SELECT user_id, MAX(id) as mid
                    FROM user_fcm_tokens
                    WHERE user_id IN ($in) AND fcm_token <> ''
                    GROUP BY user_id
                ) x ON t.id = x.mid
            ";
            $st = $db->prepare($sql);
            $st->bind_param($types, ...$userIds);
        } else {
            $sql = "SELECT user_id, fcm_token
                    FROM user_fcm_tokens
                    WHERE user_id IN ($in) AND fcm_token <> ''";
            $st = $db->prepare($sql);
            $st->bind_param($types, ...$userIds);
        }

        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        foreach ($rows as $r) {
            $uRaw = strtoupper(trim((string) ($r['user_id'] ?? '')));
            $t = trim((string) ($r['fcm_token'] ?? ''));
            if ($uRaw === '' || $t === '')
                continue;

            // 1. Store under exact DB key
            $map[$uRaw] = $t;

            // 2. Also map numeric part and variations back to this token
            if (preg_match('/^(?:LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|DS|DM)0*(\d+)$/', $uRaw, $m)) {
                $digits = $m[1];
                $padded = str_pad($digits, 5, '0', STR_PAD_LEFT);

                $map[$digits] = $t;
                $map[$padded] = $t;
                $map["LL" . $padded] = $t;
                $map["LL" . $digits] = $t;
            }

            // 3. Fallback normalized key
            $u = normalize_user_id($uRaw);
            if ($u !== '' && !isset($map[$u]))
                $map[$u] = $t;
        }
    } catch (Throwable $e) {
        error_log("FCM_TOKEN_MAP_ERROR: " . $e->getMessage());
    }

    return $map;
}

/* ================== RESULT FETCH ================== */
function fetch_latest_market_result(mysqli $db, string $market, string $date): ?array
{
    try {
        // 1. Try market_result (Standard)
        $st = $db->prepare("
            SELECT market,result_date,open_pana,open_digit,close_digit,close_pana,result_str,declared_at
            FROM market_result
            WHERE market=? AND result_date=?
            ORDER BY declared_at DESC
            LIMIT 1
        ");
        $st->bind_param('ss', $market, $date);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if ($row)
            return $row;

        return null;
    } catch (Throwable $e) {
        return null;
    }
}

function fetch_results_for_date(mysqli $db, string $date): array
{
    $rows = [];
    $dtCol = hasAnyColumn($db, 'market_result', ['declared_at', 'updated_at', 'created_at']);
    $idCol = hasAnyColumn($db, 'market_result', ['id']);

    try {
        if ($dtCol !== '') {
            $sql = "
              SELECT mr.market,mr.result_date,mr.open_pana,mr.open_digit,
                     mr.close_digit,mr.close_pana,mr.result_str
                FROM market_result mr
                JOIN (
                  SELECT market, MAX($dtCol) AS dt
                  FROM market_result
                  WHERE result_date=?
                  GROUP BY market
                ) x ON x.market=mr.market AND x.dt=mr.$dtCol
               WHERE mr.result_date=?
               ORDER BY mr.market
            ";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ss', $date, $date);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } elseif ($idCol !== '') {
            $sql = "
              SELECT mr.market,mr.result_date,mr.open_pana,mr.open_digit,
                     mr.close_digit,mr.close_pana,mr.result_str
                FROM market_result mr
                JOIN (
                  SELECT market, MAX($idCol) AS mid
                  FROM market_result
                  WHERE result_date=?
                  GROUP BY market
                ) x ON x.market=mr.market AND x.mid=mr.$idCol
               WHERE mr.result_date=?
               ORDER BY mr.market
            ";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ss', $date, $date);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $stmt = $db->prepare("
                SELECT market,result_date,open_pana,open_digit,close_digit,close_pana,result_str
                FROM market_result
                WHERE result_date=?
                ORDER BY market
            ");
            $stmt->bind_param('s', $date);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } catch (Throwable $e) {
        $rows = [];
    }

    try {
        $galiRows = [];
        $stmtGali = $db->prepare("
            SELECT market, result_date, jodi, result_str 
            FROM gali_result 
            WHERE result_date=? 
            ORDER BY market
        ");
        $stmtGali->bind_param('s', $date);
        $stmtGali->execute();
        $resGali = $stmtGali->get_result();
        while ($r = $resGali->fetch_assoc()) {
            $galiRows[] = [
                'market' => $r['market'],
                'result_date' => $r['result_date'],
                'open_pana' => '***',
                'open_digit' => '*',
                'close_digit' => '*',
                'close_pana' => '***',
                'result_str' => $r['result_str'] ?: $r['jodi'],
                'is_gali' => true
            ];
        }
        $stmtGali->close();
        $rows = array_merge($rows, $galiRows);
    } catch (Throwable $e) {
    }

    return $rows;
}

/* ================== ✅ REVERSE + DELETE + LOG (WINING ONLY) ================== */
function LL_post_json(string $url, array $payload, int $timeout = 15): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Connection: close'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_USERAGENT => 'KISMAT555-Admin-Trigger',
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false)
        return ['ok' => false, 'http' => $http, 'message' => "curl error: $err", 'raw' => ''];
    $j = json_decode((string) $resp, true);
    if (!is_array($j))
        return ['ok' => false, 'http' => $http, 'message' => 'invalid json response', 'raw' => $resp];
    return array_merge(['http' => $http], $j);
}

function LL_fetch_wining_rows_for_scope(mysqli $db, string $market, string $date, string $scope): array
{
    $scope = strtoupper($scope);

    $out = [
        'rows' => [],
        'by_user_sum' => [],
        'by_user_ids' => [],
        'total_rows' => 0
    ];

    if (!hasTable($db, 'wining'))
        return $out;

    $old = $GLOBALS['LL_PHASE_SESSION'] ?? 'open';
    $GLOBALS['LL_PHASE_SESSION'] = ($scope === 'CLOSE') ? 'close' : 'open';

    $dateWhere = wining_date_where($db, (bool) $GLOBALS['HAS_BUSINESS_DATE'], (bool) $GLOBALS['HAS_WIN_DATE'], (bool) $GLOBALS['HAS_WIN_TIME']);
    $phaseClause = phase_wins_where_clause($db, 'w');

    $balExpr = hasColumn($db, 'wining', 'wining_balance') ? "COALESCE(w.wining_balance,0)" : "0";

    $sql = "
        SELECT w.id, w.user_id, {$balExpr} AS win_amt,
               w.bet_type, w.bet_key, w.session_type, w.status, w.bid_id
        FROM wining w
        WHERE UPPER(TRIM(w.market)) = ?
          AND {$dateWhere}
          AND ({$phaseClause})
          AND (UPPER(TRIM(w.status))='WIN' OR UPPER(TRIM(w.status))='WINNING')
        ORDER BY w.id ASC
    ";

    try {
        $st = $db->prepare($sql);
        $st->bind_param('ss', $market, $date);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $id = (int) ($r['id'] ?? 0);
            $uid = strtoupper(trim((string) ($r['user_id'] ?? '')));
            $amt = (float) ($r['win_amt'] ?? 0);
            if ($id <= 0 || $uid === '' || $amt <= 0)
                continue;

            $out['rows'][] = [
                'id' => $id,
                'user_id' => $uid,
                'wining_balance' => ll_money($amt),
                'bet_type' => (string) ($r['bet_type'] ?? ''),
                'bet_key' => (string) ($r['bet_key'] ?? ''),
                'session_type' => (string) ($r['session_type'] ?? ''),
                'bid_id' => (int) ($r['bid_id'] ?? 0),
            ];
            $out['total_rows']++;

            $out['by_user_sum'][$uid] = ll_money((float) ($out['by_user_sum'][$uid] ?? 0) + $amt);
            if (!isset($out['by_user_ids'][$uid]))
                $out['by_user_ids'][$uid] = [];
            $out['by_user_ids'][$uid][] = $id;
        }
        $st->close();
    } catch (Throwable $e) {
    }

    $GLOBALS['LL_PHASE_SESSION'] = $old;
    return $out;
}

function LL_delete_ids_from_table(mysqli $db, string $table, array $ids): int
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (empty($ids))
        return 0;
    if (!hasTable($db, $table) || !hasColumn($db, $table, 'id'))
        return 0;

    $in = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "DELETE FROM `$table` WHERE id IN ($in)";
    try {
        $st = $db->prepare($sql);
        $st->bind_param($types, ...$ids);
        $st->execute();
        $aff = $st->affected_rows;
        $st->close();
        return (int) $aff;
    } catch (Throwable $e) {
        return 0;
    }
}

function LL_save_reverse_log_direct(mysqli $db, array $payload): array
{
    $rows = $payload['rows'] ?? [];
    if (!is_array($rows) || empty($rows))
        return ['ok' => false, 'message' => 'no rows'];
    try {
        $db->begin_transaction();

        $action_ref = (string) ($payload['action_ref'] ?? '');
        $admin_id = (string) ($payload['admin_id'] ?? 'ADMIN');
        $market = (string) ($payload['market'] ?? '');
        $result_date = (string) ($payload['result_date'] ?? '');
        $scope = (string) ($payload['delete_scope'] ?? 'FULL');

        $st = $db->prepare("
            INSERT INTO market_reverse_log
            (action_ref, admin_id, market, result_date, delete_scope, user_id,
             bid_refund_amount, win_reverse_amount, bids_deleted, wins_deleted, details_json)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");

        $users = 0;
        $sumR = 0;
        $sumW = 0;
        foreach ($rows as $r) {
            $uid = strtoupper(trim((string) ($r['user_id'] ?? '')));
            if ($uid === '')
                continue;
            $br = ll_money($r['bid_refund_amount'] ?? 0);
            $wr = ll_money($r['win_reverse_amount'] ?? 0);
            $bd = (int) ($r['bids_deleted'] ?? 0);
            $wd = (int) ($r['wins_deleted'] ?? 0);
            $details_json = isset($r['details']) ? json_encode($r['details'], JSON_UNESCAPED_UNICODE) : null;

            $st->bind_param('ssssssddiis', $action_ref, $admin_id, $market, $result_date, $scope, $uid, $br, $wr, $bd, $wd, $details_json);
            $st->execute();

            $users++;
            $sumR += $br;
            $sumW += $wr;
        }
        $st->close();
        $db->commit();

        return ['ok' => true, 'message' => 'saved_direct', 'stats' => ['users' => $users, 'refund_total' => ll_money($sumR), 'reverse_total' => ll_money($sumW)]];
    } catch (Throwable $e) {
        try {
            $db->rollback();
        } catch (Throwable $x) {
        }
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

/**
 * ✅ NEW: WINING ONLY delete+reverse
 * - Delete OPEN/CLOSE => ONLY wining rows delete
 * - Reverse wallet => subtract win amounts
 * - NO bid delete, NO bid refund
 */
function LL_reverse_delete_and_log(mysqli $db, string $adminId, string $market, string $date, string $scope): array
{
    $market = strtoupper(trim($market));
    $scope = strtoupper(trim($scope));
    if (!in_array($scope, ['OPEN', 'CLOSE'], true))
        $scope = 'OPEN';

    $lockKey = "LL_reverse_winonly:" . $market . ":" . $date . ":" . $scope;
    if (!mysql_get_lock($db, $lockKey, 3)) {
        return ['ok' => false, 'message' => 'reverse locked busy'];
    }

    $action_ref = 'REV_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(6)), 0, 10);

    try {
        $winPack = LL_fetch_wining_rows_for_scope($db, $market, $date, $scope);

        if (empty($winPack['rows']) || empty($winPack['by_user_sum'])) {
            $cleared = clear_wins_run_lock($db, $market, $date);
            mysql_release_lock($db, $lockKey);
            return [
                'ok' => true,
                'message' => 'no wining rows found (nothing to reverse/delete)',
                'action_ref' => $action_ref,
                'stats' => [
                    'wallet_rows_updated' => 0,
                    'bids_deleted' => 0,
                    'wins_deleted' => 0,
                    'users' => 0,
                    'wins_run_lock_cleared' => $cleared
                ],
                'api' => null
            ];
        }

        // users payload (bid fields always zero)
        $users = [];
        foreach (($winPack['by_user_sum'] ?? []) as $uid => $sum) {
            $uid = strtoupper(trim((string) $uid));
            if ($uid === '')
                continue;
            $users[$uid] = [
                'user_id' => $uid,
                'bid_refund_amount' => 0.00,
                'win_reverse_amount' => ll_money($sum),
                'bids_deleted' => 0,
                'wins_deleted' => 0,
                'details' => ['bids' => [], 'wins' => []],
            ];
        }
        foreach (($winPack['rows'] ?? []) as $r) {
            $uid = strtoupper(trim((string) ($r['user_id'] ?? '')));
            if ($uid === '')
                continue;
            if (!isset($users[$uid])) {
                $users[$uid] = [
                    'user_id' => $uid,
                    'bid_refund_amount' => 0.00,
                    'win_reverse_amount' => 0.00,
                    'bids_deleted' => 0,
                    'wins_deleted' => 0,
                    'details' => ['bids' => [], 'wins' => []],
                ];
            }
            $users[$uid]['wins_deleted']++;
            $users[$uid]['details']['wins'][] = $r;
        }

        $winIds = array_map(fn($x) => (int) ($x['id'] ?? 0), ($winPack['rows'] ?? []));
        $winIds = array_values(array_filter($winIds));

        $db->begin_transaction();

        // ✅ wallet reverse only (subtract win amount)
        $stU = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE UPPER(TRIM(user_id)) = ? LIMIT 1");
        $affWallet = 0;
        foreach ($users as $uid => $u) {
            $revwin = ll_money($u['win_reverse_amount'] ?? 0);
            if ($revwin <= 0)
                continue;
            $stU->bind_param('ds', $revwin, $uid);
            $stU->execute();
            $affWallet += (int) $stU->affected_rows;
        }
        $stU->close();

        // ✅ delete wining rows only
        $winsDeleted = 0;
        if (!empty($winIds)) {
            $winsDeleted = LL_delete_ids_from_table($db, 'wining', $winIds);
        }

        $db->commit();

        $rows = array_values($users);
        foreach ($rows as &$r) {
            $r['bid_refund_amount'] = 0.00;
            $r['bids_deleted'] = 0;
            $r['win_reverse_amount'] = ll_money($r['win_reverse_amount'] ?? 0);
        }
        unset($r);

        $payload = [
            'key' => REVERSE_TRIGGER_KEY,
            'action_ref' => $action_ref,
            'admin_id' => $adminId,
            'market' => $market,
            'result_date' => $date,
            'delete_scope' => $scope,
            'rows' => $rows
        ];

        $api = LL_post_json(REVERSE_API_URL, $payload, 15);

        if (empty($api['ok'])) {
            $direct = LL_save_reverse_log_direct($db, $payload);
            $api = ['ok' => (bool) $direct['ok'], 'message' => ('api_failed_fallback_' . ($direct['message'] ?? '')), 'fallback' => $direct];
        }

        // ✅ IMPORTANT: clear wins_run_lock so recompute works after delete
        $cleared = clear_wins_run_lock($db, $market, $date);

        mysql_release_lock($db, $lockKey);

        return [
            'ok' => true,
            'message' => 'win_reversed_and_wining_deleted',
            'action_ref' => $action_ref,
            'stats' => [
                'wallet_rows_updated' => $affWallet,
                'bids_deleted' => 0,
                'wins_deleted' => $winsDeleted,
                'users' => count($users),
                'wins_run_lock_cleared' => $cleared
            ],
            'api' => $api
        ];

    } catch (Throwable $e) {
        try {
            $db->rollback();
        } catch (Throwable $x) {
        }
        mysql_release_lock($db, $lockKey);
        return ['ok' => false, 'message' => $e->getMessage(), 'action_ref' => $action_ref];
    }
}

/* ================== UI ACTION CELL ==================
   ✅ Edit + Del Open + Del Close SAME ROW (small)
   ✅ EDIT now fills right form (NO modal) + bigger edit button
*/
function render_action_cell(array $r, bool $includeDeclareNotify = false): void
{
    $m = esc($r['market'] ?? '');
    $rd = esc($r['result_date'] ?? '');
    $op = esc($r['open_pana'] ?? '***');
    $od = esc($r['open_digit'] ?? '*');
    $cd = esc($r['close_digit'] ?? '*');
    $cp = esc($r['close_pana'] ?? '***');

    $isGali = !empty($r['is_gali']);
    if ($isGali) {
        $rs = $r['result_str'] ?? '';
        $od = (strlen($rs) >= 1) ? substr($rs, 0, 1) : '*';
        $cd = (strlen($rs) >= 2) ? substr($rs, 1, 1) : '*';
        $op = '***';
        $cp = '***';
    }

    $csrf = esc(csrf_token());
    ?>
    <div class="action-cell">
        <?php if ($isGali): ?>
            <button type="button" class="btn-mini btn-edit"
                onclick="fillEditToMainForm('<?= $m ?>','<?= $rd ?>','<?= $op ?>','<?= $od ?>','<?= $cd ?>','<?= $cp ?>')">Edit</button>

            <form method="post" class="del-form del-form-inline"
                onsubmit="return confirm('Delete Gali result for <?= $m ?>?');">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="delete_gali">
                <input type="hidden" name="market" value="<?= $m ?>">
                <input type="hidden" name="result_date" value="<?= $rd ?>">
                <button type="submit" class="btn-mini btn-del-open" style="background: #c62828;">Delete</button>
            </form>
        <?php else: ?>
            <button type="button" class="btn-mini btn-edit"
                onclick="fillEditToMainForm('<?= $m ?>','<?= $rd ?>','<?= $op ?>','<?= $od ?>','<?= $cd ?>','<?= $cp ?>')">Edit</button>

            <form method="post" class="del-form del-form-inline"
                onsubmit="return confirm('Delete OPEN session only? (ONLY delete OPEN wining rows + reverse win amount from wallet)');">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="delete_open">
                <input type="hidden" name="market" value="<?= $m ?>">
                <input type="hidden" name="result_date" value="<?= $rd ?>">
                <button type="submit" class="btn-mini btn-del-open">Del Open</button>
            </form>

            <form method="post" class="del-form del-form-inline"
                onsubmit="return confirm('Delete CLOSE session only? (ONLY delete CLOSE wining rows + reverse win amount from wallet)');">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="delete_close">
                <input type="hidden" name="market" value="<?= $m ?>">
                <input type="hidden" name="result_date" value="<?= $rd ?>">
                <button type="submit" class="btn-mini btn-del-close">Del Close</button>
            </form>
        <?php endif; ?>

        <?php if ($includeDeclareNotify && !$isGali): ?>
            <form method="post" class="del-form del-form-inline">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="market" value="<?= $m ?>">
                <input type="hidden" name="result_date" value="<?= $rd ?>">
                <input type="hidden" name="open_pana" value="<?= $op ?>">
                <input type="hidden" name="open_digit" value="<?= $od ?>">
                <input type="hidden" name="close_digit" value="<?= $cd ?>">
                <input type="hidden" name="close_pana" value="<?= $cp ?>">
                <input type="hidden" name="notify" value="1">
                <button type="submit" class="btn-mini btn-prev-submit">Declare</button>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

/* ================== MAIN ACTIONS ================== */
$msg = '';

/* CSRF fail */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !csrf_check()) {
    $msg = "<div class='flash err'>✖ Security error (CSRF). Please refresh page and try again.</div>";
}

/* RESET (Market Date) */
if ($msg === '' && isset($_GET['reset']) && $_GET['reset'] === '1') {
    $del = $db->prepare("DELETE FROM market_result WHERE result_date=?");
    $del->bind_param('s', $marketDate);
    $del->execute();
    $del->close();

    $msg = "<div class='flash ok'>✔ All market results for {$marketDate} have been reset.</div>";
}

/* ✅ DELETE GALI / OPEN / CLOSE ONLY (WINING only reverse+delete+log) */
if (
    $msg === ''
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_POST['action'])
    && in_array($_POST['action'], ['delete_gali', 'delete_open', 'delete_close'], true)
) {
    $m = strtoupper(trim($_POST['market'] ?? ''));
    $rd = trim($_POST['result_date'] ?? '');

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete_gali') {
        if (in_array($m, $markets, true) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rd)) {
            $st = $db->prepare("DELETE FROM gali_result WHERE market=? AND result_date=?");
            $st->bind_param('ss', $m, $rd);
            $st->execute();
            $st->close();
            $msg = "<div class='flash ok'>🗑 Gali result for {$m} ({$rd}) has been deleted.</div>";
        }
    } else {
        $scope = ($action === 'delete_close') ? 'CLOSE' : 'OPEN';

        if (in_array($m, $markets, true) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rd)) {
        try {
            $existing = fetch_latest_market_result($db, $m, $rd);
            if (!$existing) {
                $msg = "<div class='flash err'>✖ No result found for {$m} {$rd}.</div>";
            } else {
                $adminId = (string) ($_SESSION['admin'] ?? 'ADMIN');
                $rev = LL_reverse_delete_and_log($db, $adminId, $m, $rd, $scope);

                if (empty($rev['ok'])) {
                    $msg = "<div class='flash err'>✖ Reverse failed: " . esc($rev['message'] ?? 'error') . "</div>";
                } else {
                    // update market_result placeholders
                    $op = (string) ($existing['open_pana'] ?? '***');
                    $od = (string) ($existing['open_digit'] ?? '*');
                    $cd = (string) ($existing['close_digit'] ?? '*');
                    $cp = (string) ($existing['close_pana'] ?? '***');

                    if ($action === 'delete_open') {
                        $op = '***';
                        $od = '*';
                    } else {
                        $cd = '*';
                        $cp = '***';
                    }

                    $rs = "{$op}-{$od}{$cd}-{$cp}";

                    $st = $db->prepare("
                        UPDATE market_result
                        SET open_pana=?, open_digit=?, close_digit=?, close_pana=?, result_str=?, declared_at=NOW()
                        WHERE market=? AND result_date=?
                    ");
                    $st->bind_param('sssssss', $op, $od, $cd, $cp, $rs, $m, $rd);
                    $st->execute();
                    $st->close();

                    // ✅ extra safety: clear wins_run_lock again
                    $cleared2 = clear_wins_run_lock($db, $m, $rd);

                    $ref = esc($rev['action_ref'] ?? '');
                    $stats = $rev['stats'] ?? [];

                    $label = ($action === 'delete_open') ? 'OPEN' : 'CLOSE';

                    $msg = "<div class='flash ok'>🗑 Deleted <b>" . esc($label) . "</b> for {$m} ({$rd}). NewResult={$rs}</div>";
                    $msg .= "<div class='flash ok'>↩ WIN Reverse + WINING Delete ✅ | REF: <b>{$ref}</b> | users=" . (int) ($stats['users'] ?? 0) . ", winsDel=" . (int) ($stats['wins_deleted'] ?? 0) . ", lockCleared=" . (int) ($stats['wins_run_lock_cleared'] ?? 0) . "+{$cleared2}</div>";
                    $msg .= "<div class='flash ok'>📌 Tip: Revise logs me date filter me <b>{$rd}</b> select karo (default today hota hai).</div>";
                }
            }
        } catch (Throwable $e) {
            $msg = "<div class='flash err'>✖ Session delete failed: " . esc($e->getMessage()) . "</div>";
        }
    } else {
        $msg = "<div class='flash err'>✖ Invalid request.</div>";
    }
    }
}

/* SUBMIT (SAVE/EDIT) */
if (
    $msg === ''
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (!isset($_POST['action']) || !in_array($_POST['action'], ['delete_open', 'delete_close'], true))
) {
    $m = strtoupper(trim($_POST['market'] ?? ''));
    $GLOBALS['LL_CURRENT_MARKET'] = $m;
    $rd = trim($_POST['result_date'] ?? '');
    $op = trim($_POST['open_pana'] ?? '');
    $od = trim($_POST['open_digit'] ?? '');
    $cd = trim($_POST['close_digit'] ?? '');
    $cp = trim($_POST['close_pana'] ?? '');
    $notifyFlag = !empty($_POST['notify']);

    if ($op === '')
        $op = '***';
    if ($od === '')
        $od = '*';
    if ($cd === '')
        $cd = '*';
    if ($cp === '')
        $cp = '***';

    $valid = in_array($m, $markets, true)
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rd)
        && preg_match('/^(?:\d{3}|\*{3})$/', $op)
        && preg_match('/^(?:\d|\*)$/', $od)
        && preg_match('/^(?:\d|\*)$/', $cd)
        && preg_match('/^(?:\d{3}|\*{3})$/', $cp);

    if ($valid) {
        $isGaliMarket = false;
        $galiCheck = $db->prepare("SELECT id FROM gali_markets WHERE UPPER(market_name)=? LIMIT 1");
        $galiCheck->bind_param('s', $m);
        $galiCheck->execute();
        $galiCheck->store_result();
        if ($galiCheck->num_rows > 0) {
            $isGaliMarket = true;
        }
        $galiCheck->close();

        if ($isGaliMarket) {
            // For Gali: Jodi is constructed from open_digit and close_digit
            $jodi = $od . $cd;
            $rs = $jodi;
            
            $stmt = $db->prepare("
                INSERT INTO gali_result (market, result_date, jodi, result_str, declared_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE jodi=VALUES(jodi), result_str=VALUES(result_str), declared_at=NOW()
            ");
            $stmt->bind_param('ssss', $m, $rd, $jodi, $rs);
            $stmt->execute();
            $stmt->close();
            
            $msg = "<div class='flash ok'>✔ Gali Result {$m} [{$jodi}] saved for {$rd}.</div>";
            
            if ($notifyFlag && stripos($jodi, '*') === false) {
                $accessToken = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
                if ($accessToken) {
                    $dataNotif = [
                        'type' => 'market_result',
                        'title' => $m,
                        'message' => $jodi,
                        'market' => $m,
                        'market_color' => '#0066cc',
                        'notification_color' => '#0066cc',
                        'title_html' => '<font color="#0066cc">' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</font>',
                        'result' => $jodi,
                        'logo' => PUSH_LOGO_URL,
                        'icon' => 'ic_notification',
                        'uniq' => $m . '_' . $rd . '_SYNC_' . time()
                    ];
                    fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $dataNotif, PUSH_TOPIC);
                    $msg .= "<div class='flash ok'>🔔 Push notification sent for Gali {$m} [{$jodi}].</div>";
                }
            }
        } else {
            $existing = fetch_latest_market_result($db, $m, $rd);
        if ($existing) {
            $exOP = trim((string) ($existing['open_pana'] ?? '***'));
            $exOD = trim((string) ($existing['open_digit'] ?? '*'));
            $exCD = trim((string) ($existing['close_digit'] ?? '*'));
            $exCP = trim((string) ($existing['close_pana'] ?? '***'));

            if ($op === '***' && $od === '*' && ($exOP !== '' && $exOD !== '')) {
                $op = $exOP;
                $od = $exOD;
            }
            if ($cp === '***' && $cd === '*' && ($exCP !== '' && $exCD !== '')) {
                $cp = $exCP;
                $cd = $exCD;
            }
        }

        $rs = "{$op}-{$od}{$cd}-{$cp}";

        $phaseSession = (trim($cd) === '*' || trim($cp) === '***') ? 'open' : 'close';
        $GLOBALS['LL_PHASE_SESSION'] = $phaseSession;

        $stmt = $db->prepare("
            INSERT INTO market_result
              (market,result_date,open_pana,open_digit,close_digit,close_pana,result_str,declared_at)
            VALUES (?,?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE
              open_pana=VALUES(open_pana),
              open_digit=VALUES(open_digit),
              close_digit=VALUES(close_digit),
              close_pana=VALUES(close_pana),
              result_str=VALUES(result_str),
              declared_at=NOW()
        ");
        $stmt->bind_param('sssssss', $m, $rd, $op, $od, $cd, $cp, $rs);
        $stmt->execute();
        $stmt->close();


        // when new result declared, old lock can block -> clear
        $clearedDeclare = clear_wins_run_lock($db, $m, $rd);

        $msg = "<div class='flash ok'>✔ {$m} [{$rs}] saved for {$rd}.</div>";
        $msg .= "<div class='flash ok'>🧾 Phase: <b>" . esc(strtoupper($phaseSession)) . "</b> | lockCleared={$clearedDeclare}</div>";

        /* Auto compute wins */
        $cwOk = false;
        try {
            $cw = trigger_wins_compute($db, $m, $rd, $rs);
            $h = (int) $cw['http'];

            if ($h === 208) {
                $cwOk = true;
                $msg .= "<div class='flash ok'>🧠 Winning already computed recently (throttled).</div>";
            } elseif ($h === 429) {
                $msg .= "<div class='flash err'>⚠ Winning compute busy (lock). Try again after few seconds.</div>";
            } elseif ($h >= 200 && $h < 300) {
                $cwOk = true;
                $msg .= "<div class='flash ok'>🏆 Winning auto-compute triggered for {$m} ({$rd}).</div>";
            } else {
                $e = esc($cw['err'] ?? '');
                $msg .= "<div class='flash err'>⚠ Winning compute trigger failed (HTTP {$h}) {$e}</div>";
                // Add body to log for deep debug
                $debugLog = __DIR__ . '/api_fcm.log';
                $logTime = date('Y-m-d H:i:s');
                @file_put_contents($debugLog, "[$logTime] COMPUTE_FAIL: market=$m, date=$rd, HTTP=$h, Body=" . substr($cw['body'] ?? '', 0, 500) . "\n", FILE_APPEND);
            }
        } catch (Throwable $e) {
            $msg .= "<div class='flash err'>⚠ Winning compute trigger error.</div>";
        }

        /* Notifications (unchanged) */
        if ($notifyFlag) {
            $accessToken = google_access_token_from_sa_cached(GOOGLE_SA_JSON);

            $debugLog = __DIR__ . '/api_fcm.log';
            $logTime = date('Y-m-d H:i:s');
            $statusTok = $accessToken ? 'OK' : 'FAIL';
            @file_put_contents($debugLog, "[$logTime] DEBUG START: notifyFlag=YES, market=$m, date=$rd, accessTok=$statusTok\n", FILE_APPEND);

            if (!$accessToken) {
                $errFile = __DIR__ . '/api_fcm.log';
                $msg .= "<div class='flash err'>✖ OAuth2 access-token failed. Check <b>api_fcm.log</b> for details.</div>";
            } else {
                // Market broadcast
                $fmtRs = ll_format_result($m, $rs);
                $dataAll = [
                    'type' => 'market_result',
                    'title' => $m,
                    'message' => $fmtRs,
                    'market' => $m,
                    'market_color' => '#0066cc',
                    'notification_color' => '#0066cc',
                    'title_html' => '<font color="#0066cc">' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</font>',
                    'result' => $fmtRs,
                    'logo' => PUSH_LOGO_URL,
                    'icon' => 'ic_notification',
                    'uniq' => $m . '_' . $rd . '_RESULT_' . $phaseSession
                ];
                $r = fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $dataAll, PUSH_TOPIC);
                $msg .= ((int) $r['http'] === 200)
                    ? "<div class='flash ok'>🔔 Market update sent (topic: all).</div>"
                    : "<div class='flash err'>⚠ Push failed ({$r['http']}) " . esc($r['err'] ?? $r['body'] ?? 'Unknown Error') . "</div>";

                try {
                    $bidders = fetch_bidders_user_ids($db, $m, $rd);
                    $bCount = count($bidders);

                    $phaseWinnerIds = fetch_phase_winner_user_ids($db, $m, $rd);
                    $phaseWinnerSet = array_fill_keys($phaseWinnerIds, true);

                    $pack = ['rows' => [], 'total_rows' => 0];
                    $tries = $cwOk ? 5 : 4;
                    for ($i = 0; $i < $tries; $i++) {
                        $pack = fetch_unnotified_wins_rows($db, $m, $rd, true, true);
                        if ((int) ($pack['total_rows'] ?? 0) > 0)
                            break;
                        usleep(750000);
                    }
                    $winRows = (array) ($pack['rows'] ?? []);
                    $newWinRows = (int) ($pack['total_rows'] ?? 0);

                    error_log("FCM_WIN_DEBUG: notifyFlag=" . ($notifyFlag ? 'YES' : 'NO'));
                    error_log("FCM_WIN_DEBUG: accessToken=" . ($accessToken ? 'OK' : 'FAIL'));
                    error_log("FCM_WIN_DEBUG: market={$m}, date={$rd}, phase={$phaseSession}");
                    error_log("FCM_WIN_DEBUG: new_win_rows={$newWinRows}, bidders=" . count($bidders) . ", phase_winners=" . count($phaseWinnerIds));

                    $needUids = [];
                    foreach ($bidders as $u)
                        $needUids[] = normalize_user_id((string) $u);
                    foreach ($phaseWinnerIds as $u)
                        $needUids[] = normalize_user_id((string) $u);
                    foreach ($winRows as $wr)
                        $needUids[] = normalize_user_id((string) ($wr['user_id'] ?? ''));
                    $needUids = array_values(array_unique(array_filter($needUids)));

                    $tokenMap = fetch_token_map_for_users($db, $needUids);
                    $tCount = count($tokenMap);
                    error_log("FCM_WIN_DEBUG: tokenMapCount={$tCount}");

                    $msg .= "<div class='flash ok'>📌 Debug: phase=" . esc($phaseSession) . ", bidders={$bCount}, phase_winners=" . count($phaseWinnerIds) . ", new_win_rows={$newWinRows}, tokens={$tCount}</div>";

                    $debugLog = __DIR__ . '/api_fcm.log';
                    $logTime = date('Y-m-d H:i:s');
                    @file_put_contents($debugLog, "[$logTime] DEBUG NOTIF: market=$m, date=$rd, bidders=$bCount, winners=" . count($phaseWinnerIds) . ", rows=$newWinRows, tokens=$tCount\n", FILE_APPEND);

                    // WIN push (row-wise)
                    $winSent = 0;
                    $winFail = 0;
                    $markIds = [];
                    $winTopicSent = 0;
                    $winTopicFail = 0;

                    foreach ($winRows as $wr) {
                        $uid = normalize_user_id((string) ($wr['user_id'] ?? ''));
                        $id = (int) ($wr['id'] ?? 0);
                        // ✅ Fix: use 'amount' as returned by fetch_unnotified_wins_rows
                        $amtWin = (float) ($wr['amount'] ?? 0);
                        if ($uid === '' || $id <= 0)
                            continue;

                        $betType = trim((string) ($wr['bet_type'] ?? ''));
                        $betKey = trim((string) ($wr['bet_key'] ?? ''));

                        $amtTxt = number_format(max(0, $amtWin), 2, '.', '');
                        $btTxt = ($betType !== '') ? strtoupper(str_replace('_', ' ', $betType)) : 'WIN';
                        $msgText = "Congratulations! {$btTxt} : ₹{$amtTxt} ({$m})";

                        $uniqueKey = $m . '_' . $rd . '_' . $phaseSession . '_' . $uid . '_' . $id . '_' . time();

                        $dataWin = [
                            'type' => 'win',
                            'status' => 'WIN',
                            'title' => 'SUBH GAMING WIN 🏆',
                            'market' => $m,
                            'game_name' => $m,
                            'message' => $msgText,
                            'notification' => 'none',
                            'body' => $msgText,
                            'amount' => $amtTxt,
                            'result_data' => $fmtRs,
                            'result' => $fmtRs,
                            'logo' => PUSH_LOGO_URL,
                            'win_id' => (string) $id,
                            'bet_type' => $betType,
                            'bet_key' => $betKey,
                            'session_type' => $phaseSession,
                            'notification_id' => (string) (100000 + ($id % 900000)),
                            'icon' => 'ic_notification',
                            'uniq' => $uniqueKey
                        ];

                        if (isset($tokenMap[$uid])) {
                            $rr = fcm_v1_send_token(FCM_PROJECT_ID, $accessToken, $dataWin, $tokenMap[$uid]);
                            if ((int) $rr['http'] === 200) {
                                $winSent++;
                                $markIds[] = $id;
                                $msg .= "<div class='flash ok' style='font-size:0.8rem;padding:2px 8px;margin:1px 0'>✅ Sent to {$uid} (token)</div>";
                                continue;
                            } else {
                                $winFail++;
                                if ((int) $rr['http'] === 404 || (int) $rr['http'] === 410) {
                                    // Dead token - Cleanup!
                                    $db->query("DELETE FROM user_fcm_tokens WHERE fcm_token = '" . $db->real_escape_string($tokenMap[$uid]) . "'");
                                    $msg .= "<div class='flash err' style='font-size:0.8rem;padding:2px 8px;margin:1px 0'>✖ Dead Token for {$uid} removed (404). Trying Topic...</div>";
                                } else {
                                    $msg .= "<div class='flash err' style='font-size:0.8rem;padding:2px 8px;margin:1px 0'>✖ Fail {$uid} (token): {$rr['http']}</div>";
                                }
                            }
                        }

                        $topicUid = sanitize_topic($uid);
                        if ($topicUid !== '') {
                            $msg .= "<div class='flash' style='font-size:0.7rem;color:#777'>... Falling back to topic: {$topicUid}</div>";
                            
                            // 1. UPPER (Default)
                            $rr2 = fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $dataWin, $topicUid);
                            
                            // 2. LOWER (Android often uses trimmed lowercase or mixed)
                            $topicLower = strtolower($topicUid);
                            if ($topicUid !== $topicLower) {
                                fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $dataWin, $topicLower);
                            }

                            if ((int) $rr2['http'] === 200) {
                                $winTopicSent++;
                                $markIds[] = $id;
                                $msg .= "<div class='flash ok' style='font-size:0.8rem;padding:2px 8px;margin:1px 0'>✅ Sent to {$uid} (topic)</div>";
                            } else {
                                $winTopicFail++;
                                $msg .= "<div class='flash err' style='font-size:0.8rem;padding:2px 8px;margin:1px 0'>✖ Fail {$uid} (topic): {$rr2['http']}</div>";
                            }
                        }
                    }

                    $marked = 0;
                    if (!empty($markIds))
                        $marked = maLL_wins_notified($db, $markIds);
                    $msg .= "<div class='flash ok'>🏆 WIN push: token={$winSent} sent / {$winFail} fail, topic={$winTopicSent} sent / {$winTopicFail} fail. ✅ marked={$marked}</div>";

                    // WIN fallback if no row-wise sent
                    if (($winSent + $winTopicSent) === 0 && count($phaseWinnerIds) > 0) {
                        $sumMap = fetch_phase_winner_amount_sum_map($db, $m, $rd);

                        $fallbackSent = 0;
                        $fallbackFail = 0;
                        $fallbackTopicSent = 0;
                        $fallbackTopicFail = 0;

                        foreach ($phaseWinnerIds as $uid) {
                            $uid = strtoupper(trim((string) $uid));
                            if ($uid === '')
                                continue;

                            $amtWin = (float) ($sumMap[$uid] ?? 0);
                            $amtTxt = number_format(max(0, $amtWin), 2, '.', '');
                            $msgText = "Congratulations! WIN : ₹{$amtTxt} ({$m})";

                            $dataWin = [
                                'type' => 'win',
                                'status' => 'WIN',
                                'market' => $m,
                                'game_name' => $m,
                                'message' => $msgText,
                                'notification' => 'none',
                                'body' => $msgText,
                                'amount' => $amtTxt,
                                'result_data' => $fmtRs,
                                'result' => $fmtRs,
                                'logo' => PUSH_LOGO_URL,
                                'session_type' => $phaseSession,
                                'icon' => 'ic_notification',
                                'uniq' => $m . '_' . $rd . '_' . $phaseSession . '_WIN_FALLBACK_' . $uid . '_' . time()
                            ];

                            $doTopic = false;
                            if (isset($tokenMap[$uid])) {
                                $rr = fcm_v1_send_token(FCM_PROJECT_ID, $accessToken, $dataWin, $tokenMap[$uid]);
                                if ((int) $rr['http'] === 200) {
                                    $fallbackSent++;
                                } else {
                                    $fallbackFail++;
                                    if ((int) $rr['http'] === 404 || (int) $rr['http'] === 410) {
                                        $doTopic = true; // Token dead, try topic
                                    }
                                }
                            } else {
                                $doTopic = true;
                            }

                            if ($doTopic) {
                                $topicUid = sanitize_topic($uid);
                                if ($topicUid !== '') {
                                    // 1. UPPER
                                    $rr2 = fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $dataWin, $topicUid);
                                    
                                    // 2. LOWER
                                    $topicLower = strtolower($topicUid);
                                    if ($topicUid !== $topicLower) {
                                        fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $dataWin, $topicLower);
                                    }

                                    if ((int) $rr2['http'] === 200) {
                                        $fallbackTopicSent++;
                                    } else {
                                        $fallbackTopicFail++;
                                    }
                                }
                            }
                        }
                        $msg .= "<div class='flash ok'>🛟 WIN fallback: token={$fallbackSent} sent / {$fallbackFail} fail, topic={$fallbackTopicSent} sent / {$fallbackTopicFail} fail.</div>";
                    }

                    // LOSE only on CLOSE phase
                    $loseSent = 0;
                    $loseFail = 0;
                    $loseTopicSent = 0;
                    $loseTopicFail = 0;

                    if ($phaseSession === 'close' && $bCount > 0) {
                        foreach ($bidders as $uid) {
                            $uid = strtoupper(trim((string) $uid));
                            if ($uid === '')
                                continue;

                            if (isset($phaseWinnerSet[$uid]))
                                continue; // ✅ skip only if winner in THIS PHASE

                            $dataLose = [
                                'type' => 'lose',
                                'status' => 'LOSE',
                                'title' => 'SUBH GAMING Result',
                                'market' => $m,
                                'message' => "Better Luck Next Time!\nResult: {$fmtRs}",
                                'notification' => 'none',
                                'body' => "Better Luck Next Time!\nResult: {$fmtRs}",
                                'result_data' => $fmtRs,
                                'result' => $fmtRs,
                                'logo' => PUSH_LOGO_URL,
                                'session_type' => $phaseSession,
                                'icon' => 'ic_notification',
                                'uniq' => $m . '_' . $rd . '_LOSE_' . $phaseSession . '_' . $uid . '_' . time()
                            ];

                            if (isset($tokenMap[$uid])) {
                                $rr = fcm_v1_send_token(FCM_PROJECT_ID, $accessToken, $dataLose, $tokenMap[$uid]);
                                if ((int) $rr['http'] === 200) {
                                    $loseSent++;
                                    continue;
                                }
                                $loseFail++;
                            }

                            $topicUid = sanitize_topic($uid);
                            if ($topicUid !== '') {
                                $rr2 = fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $dataLose, $topicUid);
                                if ((int) $rr2['http'] === 200)
                                    $loseTopicSent++;
                                else
                                    $loseTopicFail++;
                            }
                        }
                        $msg .= "<div class='flash ok'>😶 LOSE push: token={$loseSent} sent / {$loseFail} fail, topic={$loseTopicSent} sent / {$loseTopicFail} fail.</div>";
                    } else if ($phaseSession === 'open') {
                        $msg .= "<div class='flash ok'>ℹ LOSE push skipped (OPEN phase).</div>";
                    }

                } catch (Throwable $e) {
                    $msg .= "<div class='flash err'>⚠ WIN/LOSE notification failed: " . esc($e->getMessage()) . "</div>";
                }
            }
        } else {
            $msg .= "<div class='flash ok'>ℹ Notification OFF – result saved only.</div>";
        }
        }
    } else {
        $msg = "<div class='flash err'>✖ Invalid input—please follow the formats.</div>";
    }
}

if (!empty($msg) && strpos($msg, 'flash ok') !== false) {
    $msg .= "<script>setTimeout(function(){ window.location.href = window.location.pathname; }, 3000);</script>";
}

/* ================== FETCH TABLES ================== */
$rows = fetch_results_for_date($db, $marketDate);
$prevRows = fetch_results_for_date($db, $prevDate);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Open Market Result</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --pdark: #770000;
            --plight: #b21616;
            --card: #fff;
            --text: #4e342e;
            --bg1: #f5f0e5;
            --bg2: #e8d9c9
        }

        /* ✅ ACTION BUTTON CONFIG (easy maintain) */
        :root {
            --btnH: 24px;
            --btnFS: 12px;
            --btnPadX: 7px;
            --btnRadius: 6px;

            --editBg: #ffffff;
            --editBd: #770000;
            --editTx: #770000;

            --openBg: #ffffff;
            --openBd: #0b6bcb;
            --openTx: #0b6bcb;

            --closeBg: #ffffff;
            --closeBd: #b00000;
            --closeTx: #b00000;

            --prevBg: linear-gradient(90deg, var(--pdark), var(--plight));
            --prevTx: #ffffff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Roboto, sans-serif
        }

        body {
            background: linear-gradient(135deg, var(--bg1), var(--bg2));
            color: var(--text);
            display: flex;
            flex-direction: column;
            min-height: 100vh
        }

        /* ✅ BAR 3-COL: LEFT | CENTER | RIGHT */
        .bar {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 10px;
            padding: 12px 5vw;
            background: var(--card);
            box-shadow: 0 2px 6px rgba(0, 0, 0, .1);
        }

        .bar-left {
            justify-self: start
        }

        .bar-center {
            justify-self: center
        }

        .bar-right {
            justify-self: end;
            display: flex;
            align-items: center;
            gap: 10px
        }

        .btn {
            background: linear-gradient(90deg, var(--pdark), var(--plight));
            color: #fff;
            padding: 6px 14px;
            border-radius: 20px;
            text-decoration: none;
            font-size: .9rem;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            white-space: nowrap
        }

        .btn.prev-btn {
            padding: 6px 12px;
            font-size: .85rem
        }

        .bar-date {
            font-size: .9rem;
            color: var(--pdark);
            white-space: nowrap;
            text-align: center;
            width: 100%;
            margin: 6px 0 2px;
        }

        .page {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px 4vw 20px
        }

        .title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--pdark);
            margin: 6px 0 12px;
            text-align: center
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 16px;
            width: 100%;
            max-width: 920px
        }

        .box {
            flex: 1 1 320px;
            max-width: 100%;
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
            display: flex;
            flex-direction: column
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: .9rem
        }

        .table th,
        .table td {
            padding: 5px;
            text-align: center;
            vertical-align: middle
        }

        .table th {
            background: var(--pdark);
            color: #fff;
            font-weight: 500;
            font-size: .85rem
        }

        .table tr:nth-child(even) {
            background: #fbf8f8
        }

        .code {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--pdark)
        }

        /* =========================
   ✅ ACTION BUTTONS: 3 in one row
========================= */
        .action-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            min-width: 220px;
            max-width: 240px;
            margin: 0 auto;
            flex-wrap: nowrap;
        }

        .del-form {
            display: inline-block;
            margin: 0
        }

        .del-form-inline {
            display: inline-block
        }

        .btn-mini {
            height: var(--btnH);
            padding: 0 var(--btnPadX);
            font-size: var(--btnFS);
            border-radius: var(--btnRadius);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            font-weight: 700;
            white-space: nowrap;
            text-align: center;
        }

        /* ✅ Separate class for each button (easy maintain) */
        .btn-edit {
            background: var(--editBg);
            border: 1px solid var(--editBd);
            color: var(--editTx);
            height: 28px;
            padding: 0 10px;
            font-size: 13px;
        }

        .btn-edit:hover {
            background: var(--editBd);
            color: #fff
        }

        .btn-del-open {
            background: var(--openBg);
            border: 1px solid var(--openBd);
            color: var(--openTx);
        }

        .btn-del-open:hover {
            background: var(--openBd);
            color: #fff
        }

        .btn-del-close {
            background: var(--closeBg);
            border: 1px solid var(--closeBd);
            color: var(--closeTx);
        }

        .btn-del-close:hover {
            background: var(--closeBd);
            color: #fff
        }

        /* For prev modal Declare button */
        .btn-prev-submit {
            background: var(--prevBg);
            color: var(--prevTx);
            border: none;
        }

        .btn-prev-submit:hover {
            filter: brightness(1.08)
        }

        @media(max-width:480px) {
            .row {
                gap: 12px
            }

            .box {
                max-width: 100%
            }

            .table {
                font-size: .8rem
            }

            .table th,
            .table td {
                padding: 4px
            }

            .action-cell {
                gap: 4px;
                min-width: 200px;
                max-width: 260px
            }

            .btn-mini {
                height: 24px;
                font-size: 12px
            }

            .btn-edit {
                height: 28px;
                font-size: 13px
            }

            .bar {
                grid-template-columns: 1fr;
                gap: 8px
            }

            .bar-left,
            .bar-center,
            .bar-right {
                justify-self: center
            }
        }

        /* Card + Form */
        .card {
            padding: 16px;
            flex: 1;
            display: flex;
            flex-direction: column
        }

        .card h2 {
            text-align: center;
            color: var(--pdark);
            margin-bottom: 12px
        }

        .flash {
            padding: 6px 8px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: .85rem;
            font-weight: 500;
            text-align: center
        }

        .flash.ok {
            background: #e6f9e4;
            color: #178910
        }

        .flash.err {
            background: #ffe8e8;
            color: var(--pdark)
        }

        input,
        select {
            width: 100%;
            padding: 8px;
            font-size: .95rem;
            border: 1px solid #ddd;
            border-radius: 6px
        }

        .g2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px
        }

        .g4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
            margin-bottom: 12px
        }

        .preview {
            padding: 6px 0;
            text-align: center;
            border: 1px dashed var(--pdark);
            border-radius: 6px;
            font-weight: 500;
            color: var(--pdark);
            margin: -6px 0 12px
        }

        button[type=submit] {
            margin-top: auto;
            padding: 10px;
            background: linear-gradient(90deg, var(--pdark), var(--plight));
            border: none;
            border-radius: 6px;
            color: #fff;
            font-size: .95rem;
            font-weight: 500;
            cursor: pointer
        }

        button[type=submit]:hover {
            filter: brightness(1.08)
        }

        .notify-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            font-size: .85rem;
            color: var(--pdark)
        }

        .notify-row input {
            width: auto
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999
        }

        .modal-box {
            width: 100%;
            max-width: 920px;
            max-height: 85vh;
            overflow: auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .3);
            padding: 16px 14px 18px
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px
        }

        .modal-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--pdark)
        }

        .modal-close {
            border: none;
            background: transparent;
            font-size: 1.1rem;
            cursor: pointer;
            color: #555
        }

        .prev-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .9rem
        }

        .prev-table th,
        .prev-table td {
            padding: 7px;
            text-align: center;
            vertical-align: middle
        }

        .prev-table th {
            background: #f3e1d7;
            color: #5d2a2a;
            font-weight: 600
        }

        .prev-table tr:nth-child(even) {
            background: #faf4f0
        }

        .prev-code {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--pdark)
        }

        .prev-empty {
            padding: 18px 6px;
            text-align: center;
            color: #666;
            font-size: .9rem
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

    <div class="bar">
        <div class="bar-left">
            <a class="btn" href="admin_dashboard.php"><i class="fa fa-arrow-left"
                    style="margin-right:4px"></i>Dashboard</a>
        </div>

        <div class="bar-center">
            <style>
            .api-switch { position: relative; display: inline-block; width: 44px; height: 22px; margin: 0; }
            .api-switch input { opacity: 0; width: 0; height: 0; }
            .api-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 22px; }
            .api-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
            .api-switch input:checked + .api-slider { background-color: #007bff; }
            .api-switch input:focus + .api-slider { box-shadow: 0 0 1px #007bff; }
            .api-switch input:checked + .api-slider:before { transform: translateX(22px); }
            </style>
            <div style="display: inline-flex; align-items: center; gap: 10px; background: #fff; padding: 5px 15px; border-radius: 20px; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                <div class="status-indicator" style="font-size: 0.85rem; display: flex; align-items: center; gap: 8px; border-right: 1px solid #eee; padding-right: 10px;">
                    <span style="font-size: 11px; font-weight: bold; color: <?= $resultMode === 'MANUAL' ? '#555' : '#999' ?>;">MANUAL</span>
                    <label class="api-switch">
                        <input type="checkbox" id="apiModeToggle" <?= $resultMode === 'API' ? 'checked' : '' ?> onchange="toggleApiMode(this.checked ? 'API' : 'MANUAL')">
                        <span class="api-slider"></span>
                    </label>
                    <span style="font-size: 11px; font-weight: bold; color: <?= $resultMode === 'API' ? '#007bff' : '#999' ?>;">API (AUTO)</span>
                </div>
                <?php if ($resultMode === 'API'): ?>
                    <div class="api-timer-display" style="font-size: 0.85rem; color: #770000; font-weight: bold; display: flex; align-items: center; gap: 6px;">
                        <span style="color: <?= ($apiStatus === 'ACTIVE') ? '#2e7d32' : '#c62828' ?>;"><i class="fa fa-circle"></i> <?= $apiStatus ?></span> | 
                        <i class="fa fa-clock"></i> Expiring in: <span style="color: #c62828;"><?= ($remainingDays > 0) ? ($remainingDays . " Days") : "0 Days" ?></span>
                    </div>
                <?php else: ?>
                    <div style="font-size: 0.85rem; color: #0066cc; font-weight: bold; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-hand-paper"></i> Manual Entry Active
                    </div>
                <?php endif; ?>
            </div>
            <script>
            function toggleApiMode(mode) {
                if(!confirm("Are you sure you want to change Result Mode to " + mode + "?")) {
                    var cb = document.getElementById('apiModeToggle');
                    cb.checked = !cb.checked;
                    return;
                }
                fetch('open_market.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=toggle_api_mode&mode=' + mode
                })
                .then(r => r.json())
                .then(function(res) {
                    if (res.ok) window.location.reload();
                    else alert('Failed to update mode');
                }).catch(function(e) {
                    alert('Network error');
                    window.location.reload();
                });
            }
            </script>
        </div>

        <div class="bar-right">
            <button type="button" class="btn prev-btn" onclick="openPrevModal()">
                <i class="fa fa-history" style="margin-right:4px"></i>Previous Day (<?= esc($prevDisplayDate) ?>)
            </button>
        </div>
    </div>

    <div class="page">
        <div class="bar-date">Date: <?= esc($displayDate) ?></div>

        <div class="title">Open Market Result</div>
        <div class="row">
            <?php if ($apiStatus === 'DISABLED' || $apiStatus === 'OFFLINE'): ?>
                <div style="width: 100%; background: #fff0f0; border: 1px solid #c62828; padding: 20px; border-radius: 12px; text-align: center; margin-bottom: 20px;">
                    <h3 style="color: #c62828; margin-bottom: 5px;"><i class="fa fa-exclamation-triangle"></i> API Service <?= $apiStatus ?></h3>
                    <p style="color: #666; font-size: 0.9rem;">Please contact administrator to activate your subscription. (<?= $remainingDays ?> Days Left)</p>
                </div>
            <?php endif; ?>

            <div class="box">
                <table class="table">
                    <tr>
                        <th>Sr</th>
                        <th>Result</th>
                        <th>Market</th>
                        <th>Action</th>
                    </tr>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="4" style="padding:16px;color:#666">No results declared for this date.</td>
                        </tr>
                    <?php else:
                        $sr = 1;
                        foreach ($rows as $r): ?>
                            <td><?= (int) $sr++ ?></td>
                            <td class="code"><?= esc(ll_format_result($r['market'], $r['result_str'])) ?></td>
                            <td><?= esc($r['market']) ?></td>
                            <td><?php render_action_cell($r, false); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                </table>
            </div>

            <div class="box">
                <div class="card" id="form-card">
                    <h2>Declare / Edit</h2>
                    <?= $msg ?>

                    <div class="notify-row">
                        <input type="checkbox" id="notify" name="notify" form="openForm" checked>
                        <label for="notify"><strong>Notification On/Off</strong> (tick = send push)</label>
                    </div>

                    <form id="openForm" method="post">
                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                        <div class="g2">
                            <label>Market
                                <select name="market" id="market" required>
                                    <option value="">Select</option>
                                    <?php foreach ($markets as $mm): ?>
                                        <option value="<?= esc($mm) ?>"><?= esc($mm) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Date
                                <input type="date" name="result_date" id="result_date" value="<?= esc($marketDate) ?>"
                                    required>
                            </label>
                        </div>

                        <div class="g4" id="inputs-container">
                            <label>OP pana
                                <input name="open_pana" id="open_pana" maxlength="3" pattern="(\d{3}|\*{3})" required
                                    value="***">
                            </label>
                            <label>OP digit
                                <input name="open_digit" id="open_digit" maxlength="1" pattern="(\d|\*)" required
                                    value="*">
                            </label>
                            <label>CL digit
                                <input name="close_digit" id="close_digit" maxlength="1" pattern="(\d|\*)" required
                                    value="*">
                            </label>
                            <label>CL pana
                                <input name="close_pana" id="close_pana" maxlength="3" pattern="(\d{3}|\*{3})" required
                                    value="***">
                            </label>
                        </div>

                        <div class="g1" id="gali-inputs-container" style="display:none; margin-bottom: 12px;">
                            <label style="display: block; font-weight: 500; font-size: 1.2rem; color: #555; text-align: center; margin-bottom: 8px;">Enter Jodi (e.g. 45)</label>
                            <input name="gali_jodi" id="gali_jodi" maxlength="2" pattern="(\d{2}|\*{2})" placeholder="Enter Jodi (e.g. 45)" value="**" style="text-align: center; font-size: 1.5rem; font-weight: bold; letter-spacing: 2px;">
                        </div>
                        
                        <div id="preview" class="preview">*** - ** - ***</div>
                        <div id="action-buttons" style="display:none; gap:12px; margin-top: 20px;">
                            <button type="button" id="btn-preview-winners" class="btn-preview-winners" onclick="previewWinners()" style="flex:1; background: #2e7d32; color: #fff; border: none; padding: 14px 10px; border-radius: 8px; font-size: .95rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <i class="fa fa-eye"></i> Winner Preview
                            </button>
                            <button type="submit" style="flex:1; background: linear-gradient(90deg, var(--pdark), var(--plight)); border: none; border-radius: 8px; color: #fff; font-size: .95rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 14px 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <i class="fa fa-bullhorn"></i> Declare Result
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="prevModal">
        <div class="modal-box">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fa fa-history" style="margin-right:6px"></i>
                    Previous Day Results (<?= esc($prevDisplayDate) ?>)
                </div>
                <button class="modal-close" onclick="closePrevModal()">&times;</button>
            </div>

            <?php if (empty($prevRows)): ?>
                <div class="prev-empty">No results found for previous day.</div>
            <?php else: ?>
                <table class="prev-table">
                    <tr>
                        <th>Sr</th>
                        <th>Market</th>
                        <th>Result</th>
                        <th>Actions</th>
                    </tr>
                    <?php $psr = 1;
                    foreach ($prevRows as $pr): ?>
                        <tr>
                            <td><?= (int) $psr++ ?></td>
                            <td><?= esc($pr['market']) ?></td>
                            <td class="prev-code"><?= esc(ll_format_result($pr['market'], $pr['result_str'])) ?>
                            </td>
                            <td>
                                <?php render_action_cell($pr, true); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        /* ========= Prev Modal ========= */
        function openPrevModal() { document.getElementById('prevModal').style.display = 'flex'; }
        function closePrevModal() { document.getElementById('prevModal').style.display = 'none'; }
        document.getElementById('prevModal').addEventListener('click', function (e) {
            if (e.target === this) closePrevModal();
        });

        /* ========= ✅ PANNA SUM -> DIGIT (AUTO) ========= */
        function sumDigitFromPana(pana) {
            pana = String(pana || '').trim();
            if (!/^\d{3}$/.test(pana)) return null;
            var a = pana.charCodeAt(0) - 48;
            var b = pana.charCodeAt(1) - 48;
            var c = pana.charCodeAt(2) - 48;
            return String((a + b + c) % 10);
        }
        function setIfAuto(inputDigit, newVal) {
            var manual = inputDigit.dataset.manual === '1';
            if (!manual || inputDigit.value === '*') {
                inputDigit.value = newVal;
                inputDigit.dataset.manual = '0';
            }
        }
        function autoDigitForOpen() {
            var op = document.getElementById('open_pana').value;
            var od = document.getElementById('open_digit');
            var d = sumDigitFromPana(op);
            if (d === null) {
                if (String(op).trim() === '***') { setIfAuto(od, '*'); }
                return;
            }
            setIfAuto(od, d);
        }
        function autoDigitForClose() {
            var cp = document.getElementById('close_pana').value;
            var cd = document.getElementById('close_digit');
            var d = sumDigitFromPana(cp);
            if (d === null) {
                if (String(cp).trim() === '***') { setIfAuto(cd, '*'); }
                return;
            }
            setIfAuto(cd, d);
        }
        document.getElementById('open_digit').addEventListener('input', function () {
            this.dataset.manual = (this.value !== '' && this.value !== '*') ? '1' : '0';
        });
        document.getElementById('close_digit').addEventListener('input', function () {
            this.dataset.manual = (this.value !== '' && this.value !== '*') ? '1' : '0';
        });
        document.getElementById('open_pana').addEventListener('input', function () {
            autoDigitForOpen();
            updatePreview();
        });
        document.getElementById('close_pana').addEventListener('input', function () {
            autoDigitForClose();
            updatePreview();
        });


        /* ========= Live preview (main form) ========= */
        var galiMarkets = <?= json_encode($gali_markets) ?>;

        document.getElementById('market').addEventListener('change', function() {
            var isGali = galiMarkets.includes(this.value);
            if (isGali) {
                document.getElementById('inputs-container').style.display = 'none';
                document.getElementById('gali-inputs-container').style.display = 'block';
                document.getElementById('open_pana').required = false;
                document.getElementById('open_digit').required = false;
                document.getElementById('close_digit').required = false;
                document.getElementById('close_pana').required = false;
            } else {
                document.getElementById('inputs-container').style.display = 'grid';
                document.getElementById('gali-inputs-container').style.display = 'none';
                document.getElementById('open_pana').required = true;
                document.getElementById('open_digit').required = true;
                document.getElementById('close_digit').required = true;
                document.getElementById('close_pana').required = true;
            }
            updatePreview();
        });

        document.getElementById('gali_jodi').addEventListener('input', function() {
            var val = this.value;
            if (val.length === 2) {
                document.getElementById('open_digit').value = val[0];
                document.getElementById('close_digit').value = val[1];
            } else if (val.length === 1) {
                document.getElementById('open_digit').value = val[0];
                document.getElementById('close_digit').value = '*';
            } else {
                document.getElementById('open_digit').value = '*';
                document.getElementById('close_digit').value = '*';
            }
            document.getElementById('open_pana').value = '***';
            document.getElementById('close_pana').value = '***';
            updatePreview();
        });

        function updatePreview() {
            var m = document.getElementById('market').value;
            var isGali = galiMarkets.includes(m);
            if (isGali) {
                var jodi = document.getElementById('gali_jodi').value || '**';
                document.getElementById('preview').innerText = jodi;
            } else {
                var op = document.getElementById('open_pana').value || '***';
                var od = document.getElementById('open_digit').value || '*';
                var cd = document.getElementById('close_digit').value || '*';
                var cp = document.getElementById('close_pana').value || '***';
                document.getElementById('preview').innerText = `${op}-${od}${cd}-${cp}`;
            }
        }
        ['open_digit', 'close_digit'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) { el.addEventListener('input', updatePreview); }
        });

        /* ✅ initial */
        autoDigitForOpen();
        autoDigitForClose();
        updatePreview();

        /* ========= Copy Notify checkbox into form submit ========= */
        document.getElementById('openForm').addEventListener('submit', function (e) {



            var notify = document.getElementById('notify').checked ? '1' : '';
            if (notify) {
                var inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'notify'; inp.value = '1';
                this.appendChild(inp);
            }
        });

        /* ========= ✅ Edit fills MAIN FORM (no modal) ========= */
        function fillEditToMainForm(market, date, op, od, cd, cp) {
            var mSel = document.getElementById('market');
            var dInp = document.getElementById('result_date');

            var opInp = document.getElementById('open_pana');
            var odInp = document.getElementById('open_digit');
            var cdInp = document.getElementById('close_digit');
            var cpInp = document.getElementById('close_pana');

            if (mSel) {
                var want = String(market || '').toUpperCase();
                for (var opt of mSel.options) {
                    if (String(opt.value).toUpperCase() === want) { mSel.value = opt.value; break; }
                }
            }
            if (dInp) dInp.value = date || '';

            opInp.value = op || '***';
            cpInp.value = cp || '***';

            odInp.dataset.manual = '0';
            cdInp.dataset.manual = '0';

            odInp.value = (od && od !== '') ? od : '*';
            cdInp.value = (cd && cd !== '') ? cd : '*';

            autoDigitForOpen();
            autoDigitForClose();
            updatePreview();


            var card = document.getElementById('form-card');
            if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Initial check
        updatePreview();

    
        
        

        
        
</script>


    <div class="modal-overlay" id="winnerPreviewModal">
        <div class="modal-box" style="max-width: 950px;">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fa fa-trophy" style="margin-right:6px"></i>
                    Potential Winners Preview
                </div>
                <button class="modal-close" onclick="closeWinnerPreview()">&times;</button>
            </div>
            <div id="winnerPreviewContent">
                <div style="text-align:center; padding:20px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p>Calculating potential winners...</p>
                </div>
            </div>
        </div>
    </div>
    <style>
        .winner-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .winner-table th, .winner-table td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        .winner-table th { background: #f5f5f5; font-weight: 600; }
        .win-amt-cell { color: #2e7d32; font-weight: 700; }
    </style>
    <script>
        function closeWinnerPreview() { document.getElementById("winnerPreviewModal").style.display = "none"; }
        
        
        
        function previewWinners() {
            var m = document.getElementById("market").value;
            var rd = document.getElementById("result_date").value;
            if (!m || !rd) { alert("Please select Market and Date first"); return; }

            var isGali = (typeof galiMarkets !== 'undefined' && Array.isArray(galiMarkets)) ? galiMarkets.includes(m) : false;
            var op, od, cd, cp;

            if (isGali) {
                var gj = (document.getElementById("gali_jodi") ? document.getElementById("gali_jodi").value : "").trim();
                op = "***";
                cp = "***";
                if (gj.length >= 2) {
                    od = gj[0];
                    cd = gj[1];
                } else if (gj.length === 1) {
                    od = gj[0];
                    cd = "*";
                } else {
                    od = "*";
                    cd = "*";
                }
            } else {
                op = document.getElementById("open_pana").value;
                od = document.getElementById("open_digit").value;
                cd = document.getElementById("close_digit").value;
                cp = document.getElementById("close_pana").value;
            }
            
            var modal = document.getElementById("winnerPreviewModal");
            var content = document.getElementById("winnerPreviewContent");
            
            modal.style.display = "flex";
            content.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fa fa-spinner fa-spin fa-2x" style="color:var(--pdark)"></i><p style="margin-top:10px; color:#666;">Calculating potential winners...</p></div>';
            
            var formData = new FormData();
            formData.append("market", m);
            formData.append("result_date", rd);
            formData.append("open_pana", op);
            formData.append("open_digit", od);
            formData.append("close_digit", cd);
            formData.append("close_pana", cp);
            
            fetch("ajax_winners_preview.php", { method: "POST", body: formData })
            .then(function(res) {
                if(!res.ok) throw new Error("Server Error: " + res.status);
                return res.json();
            })
            
            .then(function(data) {
                if (data.error) { content.innerHTML = `<div class="flash err" style="margin:20px;">${data.error}</div>`; return; }
                if (!data.winners || data.winners.length === 0) { 
                    var msg = "No winners found for this result.";
                    if (data.debug && data.debug.bids_found > 0) {
                        msg = `Found ${data.debug.bids_found} bids for this market/date, but none match the result entered.`;
                    }
                    content.innerHTML = `<div style="text-align:center; padding:50px; color:#999;"><i class="fa fa-info-circle fa-2x"></i><p style="margin-top:10px;">${msg}</p></div>`; 
                    return; 
                }

                
                var html = `
                    <style>
                        .w-table { width: 100%; border-collapse: collapse; background: #fff; }
                        .w-table th { background: #f8f9fa; color: #444; font-weight: 600; padding: 12px; text-align: left; border-bottom: 2px solid #eee; }
                        .w-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; color: #333; }
                        .w-amt { color: #2e7d32; font-weight: 700; }
                        .btn-edit-bid { background: #0277bd; color: #fff; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 500; display: inline-block; }
                        .btn-edit-bid:hover { background: #01579b; }
                    </style>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="w-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Game</th>
                                    <th>Number</th>
                                    <th>Amount</th>
                                    <th>Win Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.winners.forEach(function(w) {
                    html += `
                        <tr>
                            <td><strong>${w.user_code}</strong><br><small style="color:#777">${w.full_name}</small></td>
                            <td>${w.game_type}<br><small style="color:#888; text-transform:capitalize">${w.session}</small></td>
                            <td><span style="background:#f0f0f0; padding:2px 6px; border-radius:4px; font-family:monospace; font-weight:600;">${w.bid_number}</span></td>
                            <td>₹${w.amount}</td>
                            <td><span class="w-amt">₹${w.win_amount}</span></td>
                            <td><a href="edit_bid.php?id=${w.bid_id}" target="_blank" class="btn-edit-bid"><i class="fa fa-edit"></i> Edit Bid</a></td>
                        </tr>
                    `;
                });
                
                html += `</tbody></table></div>`;
                content.innerHTML = html;
            })
            .catch(function(err) { 
                content.innerHTML = `<div class="flash err" style="margin:20px;">Error fetching winners: ${err.message}</div>`; 
            });
        }

        
        function toggleActionButtons() {
            var m = document.getElementById("market");
            var btns = document.getElementById("action-buttons");
            if (btns && m) {
                btns.style.display = m.value ? "flex" : "none";
            }
        }

        document.getElementById("market").addEventListener("change", toggleActionButtons);
        
        
        ["open_pana", "open_digit", "close_digit", "close_pana", "result_date"].forEach(function(id) {
            var el = document.getElementById(id);
            if(el) el.addEventListener("input", toggleActionButtons);
        });
        // Initial check on page load
        (function() {
            var m = document.getElementById("market");
            var btns = document.getElementById("action-buttons");
            if(m && btns && m.value) { btns.style.display = "flex"; }
        })();
    </script>
</body>
</html>

