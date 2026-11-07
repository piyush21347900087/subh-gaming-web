<?php
if (!ob_get_level()) { ob_start(); }
session_start();
require_once 'includes/db.php';
require_once 'includes/fcm_helper.php';
date_default_timezone_set('Asia/Kolkata');

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

/* ---------- No-cache ---------- */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/* ---------- Raw POST body parser (sendBeacon fix) ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST)) {
  $raw = file_get_contents('php://input');
  if ($raw) {
    $tmp = [];
    parse_str($raw, $tmp);
    if (is_array($tmp) && $tmp)
      $_POST = $tmp + $_POST;
  }
}

/* ---------- DB ---------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = db();// ✅ Important: force connection collation to unicode_ci (prevents mix in placeholders too)
  $db->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
  $db->query("SET time_zone = '+05:30'");
} catch (Throwable $e) {
  header('Content-Type: text/plain; charset=UTF-8');
  echo "DB error";
  exit;
}

/* ================== FCM CONFIG ================== */
if (!defined('GOOGLE_SA_JSON')) define('GOOGLE_SA_JSON', __DIR__ . '/secure/service-account.json');

if (!defined('DEBUG_LOG_FILE')) define('DEBUG_LOG_FILE', __DIR__ . '/debug_chat.txt');

function write_log($msg)
{
  $time = date("Y-m-d H:i:s");
  @file_put_contents(DEBUG_LOG_FILE, "[$time] $msg\n", FILE_APPEND);
}

/* ---------- Helpers & Normalization ---------- */
function norm_uid_php($uid) { $uid = trim((string)$uid); if ($uid === '') return ''; $uid = preg_replace('/\s+/', '', $uid); $uid = strtoupper($uid); return normalize_user_id($uid); } function get_all_uid_variants_admin($uid): array
{
  $uid = strtoupper(trim((string) $uid));
  if ($uid === '') return [];
  $variants = [$uid];
  $norm = normalize_user_id($uid);
  if ($norm !== '') $variants[] = $norm;

  if (preg_match('/^([A-Z]+)0*(\d+)$/', $uid, $m)) {
    $num = $m[2];
    $padded = str_pad($num, 2, "0", STR_PAD_LEFT);
    $prefixes = ['MK', 'DP', 'AR', 'LL', 'BB', 'RK', 'DB', 'DPB', 'KL', 'KLY', 'KB', 'KBT', 'DS', 'DM', 'KR'];
    foreach ($prefixes as $p) {
      $variants[] = $p . $padded;
      $variants[] = $p . $num;
    }
    $variants[] = $num;
  } elseif (ctype_digit($uid)) {
    $padded = str_pad($uid, 2, "0", STR_PAD_LEFT);
    $prefixes = ['MK', 'DP', 'AR', 'LL', 'BB', 'RK', 'DB', 'DPB', 'KL', 'KLY', 'KB', 'KBT', 'DS', 'DM', 'KR'];
    foreach ($prefixes as $p) {
      $variants[] = $p . $padded;
      $variants[] = $p . $uid;
    }
    $variants[] = $uid;
  }
  return array_values(array_unique(array_filter($variants)));
}

function sql_norm_expr(string $col): string
{
  return "TRIM(BOTH ' ' FROM UPPER($col)) COLLATE utf8mb4_unicode_ci";
}

function detect_user_mobile_col(mysqli $db): string
{
  static $cached = null;
  if ($cached !== null) return $cached;
  $candidates = ['mobile', 'phone', 'number', 'contact', 'mobile_no', 'phone_no'];
  $found = '';
  try {
    $res = $db->query("SHOW COLUMNS FROM users");
    $cols = [];
    while ($r = $res->fetch_assoc()) {
      $cols[] = strtolower((string) ($r['Field'] ?? ''));
    }
    foreach ($candidates as $c) {
      if (in_array(strtolower($c), $cols, true)) {
        $found = $c;
        break;
      }
    }
  } catch (Throwable $e) {
    $found = '';
  }
  $cached = $found;
  return $cached;
}

/* =========================================================
   ✅ Ensure chat tables exist + TRY to unify collations
   - Prevents "Illegal mix of collations" permanently
========================================================= */
function ensure_chat_tables(mysqli $db)
{
  try {
    $db->query("CREATE TABLE IF NOT EXISTS admin_chat_seen(
          user_id VARCHAR(50) PRIMARY KEY,
          last_seen BIGINT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  } catch (Throwable $e) {
  }

  try {
    $db->query("CREATE TABLE IF NOT EXISTS admin_status(
          id TINYINT PRIMARY KEY,
          status ENUM('online','offline') NOT NULL,
          last_seen DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $colChk = $db->query("SHOW COLUMNS FROM admin_status LIKE 'last_seen'");
    if ($colChk && $colChk->num_rows === 0) {
      $db->query("ALTER TABLE admin_status ADD COLUMN last_seen DATETIME DEFAULT NULL");
    }
    $db->query("INSERT IGNORE INTO admin_status(id,status,last_seen) VALUES(1,'offline',NULL)");
  } catch (Throwable $e) {
  }

  // run at most once per day (server load safe)
  $today = date('Y-m-d');
  
  $_SESSION['LL_chat_schema_day'] = $today;

  try {
    $db->query("CREATE TABLE IF NOT EXISTS chat_seen(
          user_id VARCHAR(50) PRIMARY KEY,
          last_seen INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  } catch (Throwable $e) {
  }

  try {
    $db->query("CREATE TABLE IF NOT EXISTS user_status(
          user_id VARCHAR(50) PRIMARY KEY,
          last_seen DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  } catch (Throwable $e) {
  }

  try {
    $db->query("CREATE TABLE IF NOT EXISTS chat_messages(
          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
          user_id VARCHAR(50) NOT NULL,
          sender ENUM('user','admin') NOT NULL,
          message TEXT NOT NULL,
          image VARCHAR(255) DEFAULT NULL,
          is_edited TINYINT(1) DEFAULT 0,
          ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          KEY idx_user_id (user_id),
          KEY idx_ts (ts)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  } catch (Throwable $e) {
  }

  try {
    $cols = $db->query("SHOW COLUMNS FROM chat_messages LIKE 'is_edited'");
    if ($cols && $cols->num_rows === 0) {
      $db->query("ALTER TABLE chat_messages ADD COLUMN is_edited TINYINT(1) NOT NULL DEFAULT 0");
    }
  } catch (Throwable $e) {
  }

  // ✅ Best-effort: convert old tables collation to unicode_ci (ignore failures)
  foreach (['chat_messages', 'chat_seen', 'admin_chat_seen', 'user_status', 'admin_status'] as $t) {
    try {
      $db->query("ALTER TABLE `$t` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (Throwable $e) {
      // ignore
    }
  }
}

ensure_chat_tables($db);

// Keep admin online when accessing admin chat
if (!empty($_SESSION['admin']) && ($action ?? '') !== 'ping') {
  try {
    $db->query("UPDATE admin_status SET status='online', last_seen=NOW() WHERE id=1");
  } catch (Throwable $e) {
  }
}

/* =========================================================
   ✅ FIX (PERMANENT):
   - Deleted users ka record/chat count/badge me na aaye
   - Deleted users ke liye unread "0" ho jaye (cleanup)
========================================================= */
function cleanup_deleted_users_chat(mysqli $db)
{
  // throttle: 1 time per 10 minutes per session (server load safe)
  $now = time();
  if (!empty($_SESSION['LL_chat_cleanup_ts']) && ($now - (int) $_SESSION['LL_chat_cleanup_ts']) < 600)
    return;
  $_SESSION['LL_chat_cleanup_ts'] = $now;

  $normU = sql_norm_expr("u.user_id");
  $normCS = sql_norm_expr("cs.user_id");
  $normACS = sql_norm_expr("acs.user_id");
  $normUS = sql_norm_expr("us.user_id");

  // 1) chat_seen cleanup
  try {
    $db->query("
            DELETE cs
            FROM chat_seen cs
            LEFT JOIN (
                SELECT DISTINCT $normU AS nu
                FROM users u
            ) uu ON uu.nu = $normCS
            WHERE uu.nu IS NULL
        ");
  } catch (Throwable $e) {
  }

  // 2) user_status cleanup (✅ correct normalization)
  try {
    $db->query("
            DELETE acs
            FROM admin_chat_seen acs
            LEFT JOIN (
                SELECT DISTINCT $normU AS nu
                FROM users u
            ) uu ON uu.nu = $normACS
            WHERE uu.nu IS NULL
        ");
  } catch (Throwable $e) {
  }

  // 3) user_status cleanup (✅ correct normalization)
  try {
    $db->query("
            DELETE us
            FROM user_status us
            LEFT JOIN (
                SELECT DISTINCT $normU AS nu
                FROM users u
            ) uu ON uu.nu = $normUS
            WHERE uu.nu IS NULL
        ");
  } catch (Throwable $e) {
  }
}

/* ================== FCM CONFIG ================== */
if (!defined('GOOGLE_SA_JSON')) define('GOOGLE_SA_JSON', __DIR__ . '/secure/service-account.json');


/* ---------- Action ---------- */
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

/* ✅ user ping endpoint (live green dot) */
if ($action === 'ping' && isset($_GET['uid'])) {
  header('Content-Type: application/json; charset=UTF-8');
  cleanup_deleted_users_chat($db);

  $uid = norm_uid_php($_GET['uid']);
  if ($uid !== '') {
    $stmt = $db->prepare("
            INSERT INTO user_status(user_id,last_seen) VALUES(?, NOW())
            ON DUPLICATE KEY UPDATE last_seen = NOW()
        ");
    $stmt->bind_param('s', $uid);
    $stmt->execute();
    $stmt->close();
  }
  echo json_encode(['ok' => 1]);
  exit;
}


/* ✅ unread_count plain (ONLY EXISTING USERS THREADS) */
if ($action === 'unread_count') {
  header('Content-Type: text/plain; charset=UTF-8');
  cleanup_deleted_users_chat($db);

  $normCM = sql_norm_expr("cm.user_id");
  $normU = sql_norm_expr("u.user_id");

  try {
    $q = $db->query("
            SELECT COUNT(*) AS c
            FROM (
              SELECT $normCM AS nu, MAX(cm.id) AS last_id
              FROM chat_messages cm
              WHERE cm.sender = 'user'
              GROUP BY nu
            ) m
            INNER JOIN (
              SELECT DISTINCT $normU AS nu
              FROM users u
            ) uu ON uu.nu = m.nu
            LEFT JOIN admin_chat_seen acs ON (acs.user_id COLLATE utf8mb4_unicode_ci) = m.nu
            WHERE m.nu <> ''
              AND m.last_id > IFNULL(acs.last_seen, 0)
        ");
    $r = $q ? $q->fetch_assoc() : null;
    echo (string) ((int) ($r['c'] ?? 0));
  } catch (Throwable $e) {
    echo '0';
  }
  exit;
}

/* ✅ set_status endpoint */
if ($action === 'set_status') {
  header('Content-Type: application/json; charset=UTF-8');
  $state = ($_POST['state'] ?? ($_GET['state'] ?? '')) === 'offline' ? 'offline' : 'online';
  try {
    if ($state === 'online') {
      $db->query("UPDATE admin_status SET status='online', last_seen=NOW() WHERE id=1");
    } else {
      $db->query("UPDATE admin_status SET status='offline', last_seen=NULL WHERE id=1");
    }
  } catch (Throwable $e) {
  }
  echo json_encode(['ok' => 1, 'status' => $state]);
  exit;
}

/* ✅ broadcast to users with total deposit = 0 and wallet balance = 0 */
if (($action === 'broadcast_zero_bal_dep' || $action === 'broadcast_under_10') && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=UTF-8');
  cleanup_deleted_users_chat($db);

  $msg = trim((string) ($_POST['message'] ?? ''));
  if ($msg === '') {
    echo json_encode(['ok' => false, 'error' => 'Message cannot be empty']);
    exit;
  }

  $targetUids = [];
  if (!empty($_POST['user_ids'])) {
    if (is_array($_POST['user_ids'])) {
      $targetUids = $_POST['user_ids'];
    } else {
      $targetUids = json_decode((string)$_POST['user_ids'], true) ?: [];
    }
  }

  // If no explicit list passed, fetch all eligible users from DB (deposit 0 and balance 0)
  if (empty($targetUids)) {
    try {
      $normU = sql_norm_expr("u.user_id");
      $q = $db->query("
        SELECT $normU AS user_norm
        FROM users u
        LEFT JOIN (
          SELECT user_id, IFNULL(SUM(amount), 0) AS total_dep
          FROM deposit
          WHERE status = 'Done' AND (method IS NULL OR UPPER(method) <> 'REFUND')
          GROUP BY user_id
        ) d ON (d.user_id COLLATE utf8mb4_unicode_ci) = $normU
        WHERE IFNULL(d.total_dep, 0) <= 0
          AND IFNULL(u.wallet_balance, 0) <= 0
      ");
      while ($r = $q->fetch_assoc()) {
        $uVal = trim((string)($r['user_norm'] ?? ''));
        if ($uVal !== '') $targetUids[] = $uVal;
      }
    } catch (Throwable $e) {
      echo json_encode(['ok' => false, 'error' => 'DB query error: ' . $e->getMessage()]);
      exit;
    }
  }

  $targetUids = array_values(array_unique(array_filter(array_map('norm_uid_php', $targetUids))));
  if (empty($targetUids)) {
    echo json_encode(['ok' => true, 'sent_count' => 0, 'message' => 'No eligible users found']);
    exit;
  }

  $sentCount = 0;
  $accessToken = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
  $appLink = 'subhgaming.online/live_chat.php';

  $stmtMsg = $db->prepare("INSERT INTO chat_messages(user_id, sender, message, image, is_edited, ts) VALUES(?, 'admin', ?, NULL, 0, NOW())");
  $stmtSeen = $db->prepare("
    INSERT INTO admin_chat_seen(user_id, last_seen) VALUES(?, ?)
    ON DUPLICATE KEY UPDATE last_seen = GREATEST(last_seen, VALUES(last_seen))
  ");

  foreach ($targetUids as $uNorm) {
    if ($stmtMsg) {
      $stmtMsg->bind_param('ss', $uNorm, $msg);
      if ($stmtMsg->execute()) {
        $newId = (int) $stmtMsg->insert_id;
        $sentCount++;

        if ($stmtSeen && $newId > 0) {
          $stmtSeen->bind_param('si', $uNorm, $newId);
          $stmtSeen->execute();
        }

        // Push notification (FCM + In-app)
        if ($accessToken) {
          $fcmToken = '';
          try {
            $fcmToken = function_exists('get_latest_fcm_token') ? get_latest_fcm_token($db, $uNorm) : '';
          } catch (Throwable $e) {}

          $dataPayload = [
            'type' => 'chat',
            'title' => 'SUBH GAMING | Support Message',
            'message' => (string) $msg,
            'link_url' => $appLink,
            'user_id' => $uNorm,
            'uniq' => 'CHAT:' . $uNorm . ':' . $newId,
            'icon' => 'logo'
          ];

          if ($fcmToken !== '') {
            @fcm_v1_send_token(FCM_PROJECT_ID, $accessToken, $dataPayload, $fcmToken);
          }
          $topics = function_exists('fcm_uid_variants') ? fcm_uid_variants($uNorm) : [$uNorm];
          foreach ($topics as $t) {
            @fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $dataPayload, $t);
          }
        }

        if (function_exists('pushUserNotification')) {
          @pushUserNotification($db, $uNorm, 'SUBH GAMING | Support Message', $msg, 'chat');
        }
      }
    }
  }

  if ($stmtMsg) $stmtMsg->close();
  if ($stmtSeen) $stmtSeen->close();

  echo json_encode([
    'ok' => true,
    'sent_count' => $sentCount,
    'total_selected' => count($targetUids)
  ]);
  exit;
}

/* ✅ user list for admin sidebar */
if ($action === 'users') {
  header('Content-Type: application/json; charset=UTF-8');
  cleanup_deleted_users_chat($db);

  // Admin presence heartbeat: keep admin online and last_seen updated while admin page is open/polling
  try {
    $db->query("UPDATE admin_status SET status='online', last_seen=NOW() WHERE id=1");
  } catch (Throwable $e) {
  }

  $term = trim((string) ($_GET['q'] ?? ''));
  $termU = norm_uid_php($term);
  $filter = trim((string) ($_GET['filter'] ?? ''));

  $mobileCol = detect_user_mobile_col($db);
  $mobileSelect = $mobileCol !== '' ? "u.`$mobileCol` AS mobile" : "'' AS mobile";
  $mobileWhere  = $mobileCol !== '' ? "OR (u.`$mobileCol` IS NOT NULL AND u.`$mobileCol` LIKE CONCAT('%', ?, '%'))" : "";

  $normU = sql_norm_expr("u.user_id");
  $normCM = sql_norm_expr("cm.user_id");
  $normACS = sql_norm_expr("acs.user_id");
  $normUS = sql_norm_expr("us.user_id");

  $depFilterJoin = "";
  $depFilterWhere = "";
  if ($filter === 'zero_bal_dep' || $filter === 'under_10') {
    $depFilterJoin = "
      LEFT JOIN (
        SELECT user_id, IFNULL(SUM(amount), 0) AS total_dep
        FROM deposit
        WHERE status = 'Done' AND (method IS NULL OR UPPER(method) <> 'REFUND')
        GROUP BY user_id
      ) dep ON (dep.user_id COLLATE utf8mb4_unicode_ci) = $normU
    ";
    $depFilterWhere = " AND (IFNULL(dep.total_dep, 0) <= 0 AND IFNULL(u.wallet_balance, 0) <= 0) ";
  }

  $sql = "
      SELECT
        $normU AS user_norm,
        u.name AS name,
        $mobileSelect,
        IFNULL(m.last_id, 0) AS last_id,
        IFNULL(m.last_user_id, 0) AS last_user_id,
        m.last_ts,
        IFNULL(acs.last_seen,0) AS seen,
        CASE
          WHEN us.last_seen IS NULL THEN 0
          WHEN TIMESTAMPDIFF(SECOND, us.last_seen, NOW()) < 60 THEN 1
          ELSE 0
        END AS live
      FROM users u
      LEFT JOIN (
        SELECT
          $normCM AS nu,
          MAX(cm.id) AS last_id,
          MAX(CASE WHEN cm.sender = 'user' THEN cm.id ELSE 0 END) AS last_user_id,
          MAX(cm.ts) AS last_ts
        FROM chat_messages cm
        GROUP BY nu
      ) m ON m.nu = $normU
      LEFT JOIN admin_chat_seen acs ON $normACS = $normU
      LEFT JOIN user_status us ON $normUS = $normU
      $depFilterJoin
      WHERE
        (
          ? = ''
          OR $normU LIKE CONCAT('%', ?, '%')
          OR (u.name IS NOT NULL AND u.name LIKE CONCAT('%', ?, '%'))
          $mobileWhere
        )
        $depFilterWhere
      ORDER BY
        (IFNULL(m.last_user_id,0) > IFNULL(acs.last_seen,0)) DESC,
        live DESC,
        IFNULL(m.last_ts, '1970-01-01 00:00:00') DESC,
        u.name ASC
      LIMIT 1000
    ";

  try {
    $stmt = $db->prepare($sql);
    if ($mobileCol !== '') {
      $stmt->bind_param('ssss', $termU, $termU, $term, $term);
    } else {
      $stmt->bind_param('sss', $termU, $termU, $term);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
  } catch (Throwable $e) {
    write_log("users list error: " . $e->getMessage());
    echo json_encode([]);
    exit;
  }

  $out = [];
  foreach ($rows as $r) {
    $uid = (string) ($r['user_norm'] ?? '');
    $ts = (string) ($r['last_ts'] ?? '');
    $name = (string) ($r['name'] ?? '');
    $mob = (string) ($r['mobile'] ?? '');

    $out[] = [
      'user_id' => $uid,
      'name' => $name,
      'mobile' => $mob,
      'unread' => ((int) $r['last_user_id'] > (int) $r['seen']),
      'live' => ((int) $r['live'] === 1),
      'time' => $ts ? date('H:i', strtotime($ts)) : ''
    ];
  }

  echo json_encode($out);
  exit;
}

/* ✅ user stats */
if ($action === 'user_stats' && isset($_GET['uid'])) {
  header('Content-Type: application/json; charset=UTF-8');
  cleanup_deleted_users_chat($db);
  $rawUid = trim((string) $_GET['uid']);
  $uidNorm = norm_uid_php($rawUid);
  
  $sumDep = 0.0;
  $sumWithAll = 0.0;
  $sumWin = 0.0;
  $biddingAmount = 0.0;
  
  $uids = get_all_uid_variants_admin($rawUid ?: $uidNorm);
  if (empty($uids)) $uids = [$rawUid];
  $inClause = implode(',', array_fill(0, count($uids), '?'));
  $types = str_repeat('s', count($uids));
  
  try {
      $q = $db->prepare("SELECT IFNULL(SUM(amount),0) FROM deposit WHERE user_id IN ($inClause) AND status = 'Done' AND (method IS NULL OR UPPER(method) <> 'REFUND')");
      if ($q) {
          $q->bind_param($types, ...$uids);
          $q->execute();
          $q->bind_result($val);
          if ($q->fetch()) $sumDep = (float)$val;
          $q->close();
      }
  } catch(Throwable $e){}

  try {
      $q = $db->prepare("SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE user_id IN ($inClause) AND status = 'Done' AND (method IS NULL OR method NOT LIKE '%bid%')");
      if ($q) {
          $q->bind_param($types, ...$uids);
          $q->execute();
          $q->bind_result($val);
          if ($q->fetch()) $sumWithAll = (float)$val;
          $q->close();
      }
  } catch(Throwable $e){}

  try {
      $q = $db->prepare("SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id IN ($inClause)");
      if ($q) {
          $q->bind_param($types, ...$uids);
          $q->execute();
          $q->bind_result($val);
          if ($q->fetch()) $sumWin = (float)$val;
          $q->close();
      }
  } catch(Throwable $e){}

  $bidTables = [];
  if ($db->query("SHOW TABLES LIKE 'bid'")->num_rows > 0) $bidTables[] = "bid";
  if ($db->query("SHOW TABLES LIKE 'gali_bids'")->num_rows > 0) $bidTables[] = "gali_bids";
  if ($db->query("SHOW TABLES LIKE 'bid3'")->num_rows > 0) $bidTables[] = "bid3";
  if ($db->query("SHOW TABLES LIKE 'bid4'")->num_rows > 0) $bidTables[] = "bid4";

  $hasBidRef = ($db->query("SHOW TABLES LIKE 'bid_refunds'")->num_rows > 0);

  foreach ($bidTables as $tbl) {
      try {
          $extra = "";
          if ($hasBidRef && in_array($tbl, ['bid', 'gali_bids'])) {
              $source = $tbl === 'bid' ? "('bid','bids')" : "('gali_bid','gali_bids')";
              $extra = " AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = b.id AND br.source_table IN $source) ";
          }
          if (in_array($tbl, ['bid3', 'bid4'])) {
              $extra = " AND COALESCE(b.is_cancelled, 0) = 0 ";
          }
          
          $q = $db->prepare("SELECT IFNULL(SUM(amount),0) FROM $tbl b WHERE user_id IN ($inClause) $extra");
          if ($q) {
              $q->bind_param($types, ...$uids);
              $q->execute();
              $q->bind_result($val);
              if ($q->fetch()) {
                  $biddingAmount += (float)$val;
              }
              $q->close();
          }
      } catch(Throwable $e){}
  }

  $pl = $sumWin - $biddingAmount;

  $accountAgeStr = '';
  try {
      $q = $db->prepare("SELECT created_at FROM users WHERE user_id IN ($inClause) LIMIT 1");
      if ($q) {
          $q->bind_param($types, ...$uids);
          $q->execute();
          $q->bind_result($createdAt);
          if ($q->fetch()) {
              if (!empty($createdAt) && $createdAt !== '0000-00-00 00:00:00') {
                  $regTs = strtotime($createdAt);
                  if ($regTs) {
                      $diffSecs = max(0, time() - $regTs);
                      $days = (int) floor($diffSecs / (60 * 60 * 24));
                      if ($days === 0) $accountAgeStr = 'Today';
                      elseif ($days === 1) $accountAgeStr = '1 day old';
                      else $accountAgeStr = $days . ' days old';
                  }
              }
          }
          $q->close();
      }
  } catch(Throwable $e){}

  $cityStr = '';
  try {
      $ipCol = 'ip';
      $chkCol = $db->query("SHOW COLUMNS FROM users LIKE 'ip'");
      if (!$chkCol || $chkCol->num_rows === 0) {
          $ipCol = 'ip_address';
      }
      $q = $db->prepare("SELECT `$ipCol` FROM users WHERE user_id IN ($inClause) LIMIT 1");
      if ($q) {
          $q->bind_param($types, ...$uids);
          $q->execute();
          $q->bind_result($ipAddr);
          if ($q->fetch() && !empty($ipAddr) && $ipAddr !== '-' && $ipAddr !== '127.0.0.1' && $ipAddr !== '::1') {
              if (isset($_SESSION['ip_city_cache'][$ipAddr])) {
                  $cityStr = $_SESSION['ip_city_cache'][$ipAddr];
              } else {
                  $url = "http://ip-api.com/json/" . urlencode($ipAddr) . "?fields=status,city,regionName";
                  if (function_exists('curl_init')) {
                      $ch = curl_init($url);
                      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                      curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                      $raw = curl_exec($ch);
                      curl_close($ch);
                      if ($raw) {
                          $json = json_decode($raw, true);
                          if (!empty($json['status']) && $json['status'] === 'success' && !empty($json['city'])) {
                              $cityStr = $json['city'] . (!empty($json['regionName']) ? ', ' . $json['regionName'] : '');
                              $_SESSION['ip_city_cache'][$ipAddr] = $cityStr;
                          }
                      }
                  }
              }
          }
          $q->close();
      }
  } catch (Throwable $e) {}

  echo json_encode([
      'city' => $cityStr,
      'age' => $accountAgeStr,
      'ok' => true,
      'sumDep' => $sumDep,
      'sumWithAll' => $sumWithAll,
      'pl' => $pl
  ]);
  exit;
}

/* ✅ fetch thread (normalize WHERE + seen = REAL latest id) */
if ($action === 'fetch' && isset($_GET['uid'])) {
  header('Content-Type: application/json; charset=UTF-8');
  cleanup_deleted_users_chat($db);

  $rawUid = trim((string) $_GET['uid']);
  $uidNorm = norm_uid_php($rawUid);
  if ($uidNorm === '' && $rawUid === '') {
    echo json_encode(['ok' => false, 'messages' => [], 'user_seen' => 0, 'user_online' => false]);
    exit;
  }

  $uids = get_all_uid_variants_admin($rawUid ?: $uidNorm);
  if (empty($uids)) $uids = [$rawUid];
  $inClause = implode(',', array_fill(0, count($uids), '?'));
  $types = str_repeat('s', count($uids));

  // Keep admin status online
  try {
    $db->query("UPDATE admin_status SET status='online', last_seen=NOW() WHERE id=1");
  } catch (Throwable $e) {
  }


  // ✅ only allow fetch if user exists in users table
  try {
    $chk = $db->prepare("SELECT 1 FROM users WHERE user_id IN ($inClause) LIMIT 1");
    $chk->bind_param($types, ...$uids);
    $chk->execute();
    $has = $chk->get_result()->fetch_row();
    $chk->close();
    if (!$has) {
      $normU = sql_norm_expr("user_id");
      $chk2 = $db->prepare("SELECT 1 FROM users WHERE $normU = ? LIMIT 1");
      $chk2->bind_param('s', $uidNorm);
      $chk2->execute();
      $has = $chk2->get_result()->fetch_row();
      $chk2->close();
      if (!$has) {
        echo json_encode(['ok' => false, 'messages' => [], 'user_seen' => 0, 'user_online' => false]);
        exit;
      }
    }
  } catch (Throwable $e) {
    echo json_encode(['ok' => false, 'messages' => [], 'user_seen' => 0, 'user_online' => false]);
    exit;
  }

  try {
    $stmt = $db->prepare("
            SELECT id, sender, message, image, is_edited,
                   CONCAT(DATE_FORMAT(ts,'%d %b %Y %I:%i %p'),' IST') dt
            FROM chat_messages
            WHERE user_id IN ($inClause)
            ORDER BY id ASC
            LIMIT 300
        ");
    $stmt->bind_param($types, ...$uids);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
  } catch (Throwable $e) {
    write_log("fetch error uid=$rawUid : " . $e->getMessage());
    echo json_encode(['ok' => false, 'messages' => [], 'user_seen' => 0, 'user_online' => false]);
    exit;
  }

  // ✅ mark seen to REAL latest message id
  try {
    $stmtMax = $db->prepare("SELECT MAX(id) AS mx FROM chat_messages WHERE user_id IN ($inClause)");
    $stmtMax->bind_param($types, ...$uids);
    $stmtMax->execute();
    $mxRow = $stmtMax->get_result()->fetch_assoc();
    $stmtMax->close();

    $maxId = (int) ($mxRow['mx'] ?? 0);
    if ($maxId > 0) {
      foreach ($uids as $u) {
        $stmtSeen = $db->prepare("
              INSERT INTO admin_chat_seen(user_id,last_seen) VALUES(?,?)
                  ON DUPLICATE KEY UPDATE last_seen = GREATEST(last_seen, VALUES(last_seen))
        ");
        $stmtSeen->bind_param('si', $u, $maxId);
        $stmtSeen->execute();
        $stmtSeen->close();
      }
    }
  } catch (Throwable $e) {
    write_log("seen update error: " . $e->getMessage());
  }

  // Get user seen ID
  $userSeen = 0;
  try {
    $sStmt = $db->prepare("SELECT IFNULL(MAX(last_seen),0) FROM chat_seen WHERE user_id IN ($inClause)");
    if ($sStmt) {
      $sStmt->bind_param($types, ...$uids);
      $sStmt->execute();
      $sStmt->bind_result($uSeenVal);
      if ($sStmt->fetch()) {
        $userSeen = (int) $uSeenVal;
      }
      $sStmt->close();
    }
  } catch (Throwable $e) {}

  // Check if user is live/online
  $userOnline = false;
  $liveStatusHtml = '<span style="display:inline-block; margin-left:12px; font-size:12px; background:#b0bec5; color:#fff; padding:2px 10px; border-radius:12px; font-weight:600; vertical-align:middle;">Offline</span>';
  try {
    $uStmt = $db->prepare("SELECT status, last_seen, (last_seen >= NOW() - INTERVAL 20 SECOND) AS recent FROM live WHERE user_id IN ($inClause) ORDER BY last_seen DESC LIMIT 1");
    if ($uStmt) {
      $uStmt->bind_param($types, ...$uids);
      $uStmt->execute();
      $resU = $uStmt->get_result();
      if ($rowU = $resU->fetch_assoc()) {
        if ($rowU['status'] === 'online') {
          $userOnline = true;
          if ($rowU['recent']) {
            $liveStatusHtml = '<span style="display:inline-block; margin-left:12px; font-size:12px; background:#00e676; color:#000; padding:2px 10px; border-radius:12px; font-weight:600; box-shadow:0 0 6px rgba(0,230,118,0.7); vertical-align:middle;"><i class="fas fa-circle" style="font-size:9px; margin-right:4px;"></i> Online (Active)</span>';
          } else {
            $liveStatusHtml = '<span style="display:inline-block; margin-left:12px; font-size:12px; background:#ffb300; color:#000; padding:2px 10px; border-radius:12px; font-weight:600; box-shadow:0 0 6px rgba(255,179,0,0.7); vertical-align:middle;"><i class="fas fa-clock" style="font-size:10px; margin-right:4px;"></i> Login but not active</span>';
          }
        }
      }
      $uStmt->close();
    }
  } catch (Throwable $e) {}

  echo json_encode([
    'ok' => true,
    'messages' => $rows,
    'user_seen' => $userSeen,
    'user_online' => $userOnline,
    'live_html' => $liveStatusHtml
  ]);
  exit;
}

/* ✅ edit message (Admin) */
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=UTF-8');
  $id = (int) ($_POST['id'] ?? 0);
  $msg = trim((string) ($_POST['message'] ?? ''));

  if ($id <= 0 || $msg === '') {
    echo json_encode(['ok' => false, 'error' => 'Message cannot be empty']);
    exit;
  }

  $stmt = $db->prepare("UPDATE chat_messages SET message = ?, is_edited = 1 WHERE id = ? AND sender = 'admin'");
  $stmt->bind_param('si', $msg, $id);
  $stmt->execute();
  $stmt->close();

  echo json_encode(['ok' => true, 'id' => $id, 'message' => $msg]);
  exit;
}

/* ✅ delete message (Admin) */
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=UTF-8');
  $id = (int) ($_POST['id'] ?? 0);

  if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid ID']);
    exit;
  }

  $stmt = $db->prepare("SELECT image FROM chat_messages WHERE id = ?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $imgToUnlink = '';
  if ($row = $res->fetch_assoc()) {
    $imgToUnlink = (string) ($row['image'] ?? '');
  }
  $stmt->close();

  if ($imgToUnlink !== '') {
    $uploadRoot = realpath(__DIR__ . '/uploads/chat');
    $imgClean = str_replace(['\\', "\0"], ['/', ''], $imgToUnlink);
    $fullImgPath = realpath(__DIR__ . '/' . ltrim($imgClean, '/'));
    if ($fullImgPath && $uploadRoot && strpos($fullImgPath, $uploadRoot . DIRECTORY_SEPARATOR) === 0 && is_file($fullImgPath)) {
      @unlink($fullImgPath);
    }
  }

  $delStmt = $db->prepare("DELETE FROM chat_messages WHERE id = ?");
  $delStmt->bind_param('i', $id);
  $delStmt->execute();
  $delStmt->close();

  echo json_encode(['ok' => true, 'id' => $id]);
  exit;
}

/* ✅ send admin message */
if ($action === 'send' && isset($_POST['uid'], $_POST['message'])) {
  header('Content-Type: text/plain; charset=UTF-8');
  cleanup_deleted_users_chat($db);

  $uidNorm = norm_uid_php($_POST['uid']);
  $msg = trim((string) $_POST['message']);
  $imagePath = null;

  if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
    $tmp = (string) ($_FILES['screenshot']['tmp_name'] ?? '');
    $name = (string) ($_FILES['screenshot']['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $imgCheck = @getimagesize($tmp);
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && $imgCheck !== false) {
      $uploadDir = 'uploads/chat/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
      }
      $filename = 'admin_chat_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
      $target = $uploadDir . $filename;
      if (move_uploaded_file($tmp, $target)) {
        $imagePath = $target;
      }
    }
  }

  if ($uidNorm === '' || ($msg === '' && !$imagePath)) {
    echo "OK";
    exit;
  }

  

  $stmt = $db->prepare("INSERT INTO chat_messages(user_id,sender,message,image,is_edited,ts) VALUES(?, 'admin', ?, ?, 0, NOW())");
  $stmt->bind_param('sss', $uidNorm, $msg, $imagePath);
  $stmt->execute();
  $newId = (int) $stmt->insert_id;
  $stmt->close();

  // mark seen to newId
  $stmtSeen = $db->prepare("
        INSERT INTO admin_chat_seen(user_id,last_seen) VALUES(?,?)
        ON DUPLICATE KEY UPDATE last_seen = GREATEST(last_seen, VALUES(last_seen))
    ");
  $stmtSeen->bind_param('si', $uidNorm, $newId);
  $stmtSeen->execute();
  $stmtSeen->close();

  // try token
  $fcmToken = '';
  $uidNorm = norm_uid_php($uidNorm);
  try {
    $fcmToken = function_exists('get_latest_fcm_token') ? get_latest_fcm_token($db, $uidNorm) : '';
  } catch (Throwable $e) {
    $fcmToken = '';
  }

  $accessToken = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
  if ($accessToken) {
    if ($msg === '' && $imagePath) $msg = '(Sent an image)';
    if ($msg === '') $msg = '(Empty Message)';
    $dataPayload = [
      'type' => 'chat',
      'title' => 'SUBH GAMING | Support Message',
      'message' => (string) $msg,
      'link_url' => 'subhgaming.online/live_chat.php',
      'user_id' => $uidNorm,
      'uniq' => 'CHAT:' . $uidNorm . ':' . $newId,
      'icon' => 'logo'
    ];

    $success = false;
    if ($fcmToken !== '') {
      $r = fcm_v1_send_token(FCM_PROJECT_ID, $accessToken, $dataPayload, $fcmToken);
      if ((int) ($r['http'] ?? 0) === 200) {
        $success = true;
      }
    }

    if (!$success && $uidNorm !== '') {
      fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $dataPayload, $uidNorm);
    }
  }

  echo "OK";
  exit;
}

/* ---------- DEFAULT HTML ---------- */
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">

<head>
  <title>Admin Chat</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --red: #e53935;
      --green: #4caf50;
      --bg: #f7f7f7;
      --txt: #333;
      --g1: #43a047;
      --g2: #2e7d32;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: -apple-system, BlinkMacSystemFont, 'Roboto';
    }

    html,
    body {
      height: 100%;
      background: var(--bg);
      display: flex;
      flex-direction: column;
    }

    .topBar { flex: 0 0 48px; display: flex; align-items: center; justify-content: space-between; padding: 0 14px; background: #fff; border-bottom: 1px solid #ddd; }

    .topBar a { color: var(--txt); text-decoration: none; font-weight: 600; font-size: 14px; }

    .topBar .stats { font-size: 12px; color: #666; display: flex; gap: 12px; align-items: center; }

    .main {
      flex: 1;
      display: flex;
      overflow: hidden;
      width: 100%
    }

    .sidebar {
      flex: 0 0 260px;
      border-right: 1px solid #ddd;
      background: #fff;
      display: flex;
      flex-direction: column;
    }

    .search {
      padding: 8px 10px;
      border-bottom: 1px solid #eee;
    }

    .search input {
      width: 100%;
      padding: 7px 9px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 13px;
    }

    /* Filters Bar */
    .filter-bar {
      display: flex;
      gap: 6px;
      padding: 8px 10px;
      background: #fafafa;
      border-bottom: 1px solid #eee;
    }

    .filter-pill {
      flex: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      padding: 6px 8px;
      border-radius: 6px;
      border: 1px solid #ddd;
      background: #fff;
      color: #555;
      font-size: 11px;
      font-weight: 600;
      cursor: pointer;
      transition: all .15s ease;
      white-space: nowrap;
    }

    .filter-pill:hover {
      background: #f0f0f0;
      color: #333;
    }

    .filter-pill.active {
      background: #ffebee;
      border-color: #ef5350;
      color: #c62828;
    }

    .filter-pill .badge {
      background: #e0e0e0;
      color: #333;
      padding: 1px 6px;
      border-radius: 10px;
      font-size: 10px;
      font-weight: 700;
    }

    .filter-pill.active .badge {
      background: #c62828;
      color: #fff;
    }

    .bulk-action-bar {
      padding: 8px 10px;
      background: #fff8e1;
      border-bottom: 1px solid #ffe082;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      animation: fadeIn .2s ease;
    }

    .bulk-action-bar .bulk-info {
      font-size: 11px;
      font-weight: 600;
      color: #f57f17;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .bulk-send-btn {
      background: linear-gradient(135deg, #e53935, #b71c1c);
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 6px 10px;
      font-size: 11px;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      box-shadow: 0 2px 4px rgba(229, 57, 53, .3);
      transition: all .15s ease;
      white-space: nowrap;
    }

    .bulk-send-btn:hover {
      background: linear-gradient(135deg, #d32f2f, #8e0000);
      transform: translateY(-1px);
      box-shadow: 0 3px 6px rgba(229, 57, 53, .4);
    }

    /* Bulk Progress Bar */
    .bulk-progress-box {
      margin-top: 15px;
      padding: 12px;
      background: #f8f9fa;
      border: 1px solid #e9ecef;
      border-radius: 8px;
    }

    .bulk-progress-info {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 13px;
      font-weight: 700;
      color: #333;
      margin-bottom: 8px;
    }

    .bulk-progress-info .pct {
      color: #b71c1c;
    }

    .bulk-progress-track {
      width: 100%;
      height: 12px;
      background: #e0e0e0;
      border-radius: 6px;
      overflow: hidden;
      position: relative;
    }

    .bulk-progress-fill {
      height: 100%;
      width: 0%;
      background: linear-gradient(90deg, #4caf50, #2e7d32);
      border-radius: 6px;
      transition: width 0.15s ease;
    }

    .bulk-status-detail {
      font-size: 11.5px;
      color: #666;
      margin-top: 6px;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .list {
      flex: 1;
      overflow-y: auto;
    }

    .row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 12px;
      border-bottom: 1px solid #f0f0f0;
      cursor: pointer;
      transition: background .15s;
    }

    .row:hover {
      background: #fafafa;
    }

    .row.active {
      background: #ffecea;
    }

    .nameWrap {
      display: flex;
      align-items: flex-start;
      gap: 6px;
    }

    .dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
    }

    .green { background: var(--green); }

    .grey {
      background: #ccc;
    }

    .info {
      display: flex;
      flex-direction: column;
      font-size: 12px;
    }

    .info .uid {
      font-size: 11px;
      color: #777;
    }

    .info .mob {
      font-size: 11px;
      color: #555;
      margin-top: 1px;
    }

    .info .time {
      font-size: 10px;
      color: #999;
      margin-top: 1px;
    }

    .unread {
      font-size: 11px;
      font-weight: 800;
      color: var(--red);
    }

    .hide {
      display: none;
    }

    .chatCol {
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .head { flex: 0 0 50px; background: #fff; border-bottom: 1px solid #ddd; display: flex; align-items: center; padding: 0 14px; font-weight: 600; font-size: 15px; justify-content: space-between; }

    .user-bio-link {
      color: #b71c1c;
      text-decoration: none;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 2px 8px;
      border-radius: 6px;
      background: #ffebee;
      border: 1px solid #ffcdd2;
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .user-bio-link:hover {
      background: #b71c1c;
      color: #fff;
      text-decoration: none;
      box-shadow: 0 2px 6px rgba(183, 28, 28, 0.3);
    }

    .head .quick {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      max-width: 60%;
    }

    .head .quick button {
      border: none;
      border-radius: 999px;
      padding: 3px 8px;
      font-size: 11px;
      cursor: pointer;
      background: #ffe0e0;
      color: #b71c1c;
    }

    .chat { flex: 1; padding: 14px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; background: #efeae2; }

    .wrap {
      max-width: 80%;
      display: flex;
      flex-direction: column;
    }

    .admin {
      align-self: flex-end;
      align-items: flex-end;
      text-align: right;
    }

    .user {
      align-self: flex-start;
      align-items: flex-start;
      text-align: left;
    }

    .msg-body-row {
      display: flex;
      align-items: center;
      gap: 6px;
      position: relative;
      max-width: 100%;
    }

    .admin .msg-body-row {
      flex-direction: row-reverse;
    }

    .user .msg-body-row {
      flex-direction: row;
    }

    .bub {
      padding: 10px 14px;
      border-radius: 14px;
      font-size: 14px;
      line-height: 1.45;
      word-wrap: break-word;
      word-break: break-word;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
      position: relative;
    }

    .admin .bub { background: #dcf8c6; color: #212121; border-bottom-right-radius: 3px; border: none; }

    .user .bub {
      background: #f5f5f5;
      border: 1px solid #e0e0e0;
      color: #212121;
      border-bottom-left-radius: 3px;
    }

    .meta {
      font-size: 10px;
      margin-top: 4px;
      color: #777;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .admin .meta {
      justify-content: flex-end;
    }

    .user .meta {
      justify-content: flex-start;
    }

    .edited-tag {
      font-size: 9px;
      color: #888;
      font-style: italic;
    }

    .tick-wrap {
      display: inline-flex;
      align-items: center;
      margin-left: 3px;
      vertical-align: middle;
    }

    .chat-tick {
      height: 12px;
      width: auto;
      max-height: 12px;
      object-fit: contain;
      vertical-align: middle;
      display: inline-block;
    }

    /* 3 dots action menu button */
    .msg-opt-btn {
      background: #f0f0f0;
      border: 1px solid #ddd;
      color: #555;
      border-radius: 50%;
      width: 26px;
      height: 26px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      cursor: pointer;
      opacity: 0;
      transition: opacity 0.2s, background 0.2s;
      flex-shrink: 0;
    }

    .wrap:hover .msg-opt-btn, .msg-opt-btn.active {
      opacity: 1;
    }

    @media (max-width: 768px) {
      .msg-opt-btn {
        opacity: 0.7;
      }
    }

    .msg-menu {
      position: absolute;
      top: 100%;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
      z-index: 100;
      display: none;
      flex-direction: column;
      min-width: 105px;
      overflow: hidden;
    }

    .admin .msg-menu {
      right: 0;
    }

    .user .msg-menu {
      left: 0;
    }

    .msg-menu.show {
      display: flex;
    }

    .msg-menu-item {
      padding: 8px 12px;
      background: transparent;
      border: none;
      color: #333;
      font-size: 12px;
      text-align: left;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      transition: background 0.15s;
      font-family: inherit;
      width: 100%;
    }

    .msg-menu-item:hover {
      background: #f5f5f5;
      color: #000;
    }

    .msg-menu-item.danger {
      color: #d32f2f;
    }

    .msg-menu-item.danger:hover {
      background: #ffebee;
    }

    /* Admin Edit Modal */
    .admin-modal-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 2000;
      padding: 15px;
    }

    .admin-modal-overlay.show {
      display: flex;
    }

    .admin-modal-box {
      background: #fff;
      border-radius: 12px;
      width: 100%;
      max-width: 440px;
      padding: 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    }

    .admin-modal-title {
      font-size: 16px;
      font-weight: 700;
      color: #b71c1c;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .admin-modal-textarea {
      width: 100%;
      min-height: 100px;
      border: 1px solid #ccc;
      border-radius: 8px;
      padding: 10px 12px;
      font-family: inherit;
      font-size: 14px;
      resize: vertical;
      outline: none;
      box-sizing: border-box;
    }

    .admin-modal-textarea:focus {
      border-color: #b71c1c;
      box-shadow: 0 0 0 2px rgba(183, 28, 28, 0.15);
    }

    .admin-modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 14px;
    }

    .admin-modal-btn {
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      border: none;
      font-family: inherit;
    }

    .admin-modal-btn.cancel {
      background: #f0f0f0;
      color: #555;
    }

    .admin-modal-btn.cancel:hover {
      background: #e0e0e0;
    }

    .admin-modal-btn.save {
      background: #b71c1c;
      color: #fff;
    }

    .admin-modal-btn.save:hover {
      background: #8e0000;
    }

    .inputRow {
      position: sticky;
      bottom: 0;
      background: #fff;
      padding: 10px 12px;
      border-top: 1px solid #ddd;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .inputRow input {
      flex: 1;
      padding: 10px 15px;
      border: 1px solid #ddd;
      border-radius: 22px;
      font-size: 14px;
      min-width: 0;
      outline: none;
    }

    .inputRow button {
      width: 40px;
      height: 40px;
      min-width: 40px;
      border: none;
      border-radius: 50%;
      background: var(--red);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      cursor: pointer;
      flex-shrink: 0;
    }

    .scrollBtn {
      position: fixed;
      right: 22px;
      width: 44px;
      height: 44px;
      border: none;
      border-radius: 50%;
      color: #fff;
      font-size: 18px;
      display: none;
      align-items: center;
      justify-content: center;
      box-shadow: 0 3px 8px rgba(0, 0, 0, .3);
      z-index: 99;
      cursor: pointer;
      transition: transform 0.2s ease, opacity 0.2s ease;
    }

    .scrollBtn.down {
      bottom: 112px;
      background: linear-gradient(135deg, var(--g1), var(--g2));
    }

    .scrollBtn.up {
      bottom: 166px;
      background: linear-gradient(135deg, #37474f, #212121);
    }

    .scrollBtn.show, .down.show {
      display: flex;
      animation: fadeIn .25s;
    }

    .down {
      position: fixed;
      bottom: 112px;
      right: 22px;
      width: 46px;
      height: 46px;
      border: none;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--g1), var(--g2));
      color: #fff;
      font-size: 20px;
      display: none;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 5px rgba(0, 0, 0, .25);
      z-index: 4;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(12px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media(min-width:900px) {
      .main {
        max-width: 900px;
        margin: 0 auto;
        border-left: 1px solid #ddd;
        border-right: 1px solid #ddd;
      }
    }

    @media (max-width: 768px) {
      .topBar {
        flex: 0 0 auto !important;
        height: auto !important;
        flex-wrap: wrap !important;
        padding: 8px 10px !important;
        gap: 8px !important;
      }
      .topBar > a { order: 1; font-size: 13px !important; }
      .topBar > #statsBar { order: 2; font-size: 11px !important; gap: 8px !important; }
      .topBar > #topBarCenter { 
        order: 3; 
        width: 100% !important; 
        flex: 0 0 100% !important; 
        flex-direction: column !important; 
        gap: 6px !important; 
        margin-top: 4px;
      }
      #topBarCenter span#chatTitle {
        font-size: 14px !important;
      }
      #topBarCenter > div#chatUserStats {
        margin-left: 0 !important; 
        border-left: none !important; 
        padding-left: 0 !important; 
        width: 100%;
        justify-content: center;
        border-top: 1px solid #eee;
        padding-top: 6px;
        flex-wrap: wrap;
        font-size: 11.5px !important;
        gap: 8px !important;
      }
      .chatCol .head {
        min-height: auto !important;
      }
      .sidebar {
        flex: 0 0 100%;
        width: 100%;
      }
      .chatCol {
        display: none;
      }
      .main.chat-open .sidebar {
        display: none;
      }
      .main.chat-open .chatCol {
        display: flex;
        flex: 1;
      }
      .wrap {
        max-width: 85%;
      }
      .head .quick {
        display: none !important;
      }
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
  <div class="topBar">
    <a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    
    <div id="topBarCenter" style="display:flex; flex:1; flex-wrap:wrap; justify-content:center; align-items:center; gap: 8px;">
      <span id="chatTitle" style="text-align: center; font-size: 16px; font-weight: bold;">Select a User...</span>
      <span id="chatLiveStatus"></span>
      <span id="chatAccountAge" style="display:none; font-size: 11px; font-weight: bold; background: #e3f2fd; color: #1565c0; padding: 2px 8px; border-radius: 12px;"></span>
      <span id="chatLocation" style="display:none; font-size: 11px; color: #e65100; background: #fff3e0; padding: 2px 8px; border-radius: 12px;"><i class="fas fa-map-marker-alt"></i> <span></span></span>
      <div id="chatUserStats" style="display:none; gap:12px; margin-left: 15px; font-size:13px; border-left:1px solid #ddd; padding-left:15px;">
        <div title="Total Deposit"><i class="fas fa-arrow-down" style="color:#2e7d32;"></i> Dep: <b id="statDep" style="color:#2e7d32;">₹0</b></div>
        <div title="Total Withdrawal"><i class="fas fa-arrow-up" style="color:#c62828;"></i> Wd: <b id="statWith" style="color:#c62828;">₹0</b></div>
        <div title="Profit / Loss"><i class="fas fa-chart-line"></i> P/L: <b id="statPL">₹0</b></div>
      </div>
    </div>
    <div id="statsBar" class="stats"><span>Online: 0</span><span>Unread: 0</span></div>
  </div>
  <div class="main">
    <div class="sidebar">
      <div class="search">
        <input id="search" placeholder="Search by User ID / Name / Mobile..." autocomplete="off">
      </div>
      <div class="filter-bar">
        <button type="button" class="filter-pill active" id="filterAllBtn" onclick="setFilter('')">
          <i class="fas fa-users"></i> All Users
        </button>
        <button type="button" class="filter-pill" id="filterZeroBtn" onclick="setFilter('zero_bal_dep')" title="Users whose Balance = 0 and Deposit = 0">
          <i class="fas fa-filter"></i> Bal 0 &amp; Dep 0
          <span class="badge" id="filterZeroBadge">0</span>
        </button>
      </div>
      <div class="bulk-action-bar" id="bulkActionBar" style="display:none;">
        <div class="bulk-info">
          <i class="fas fa-bullhorn"></i> <span id="bulkUserCountLabel">0 users selected</span>
        </div>
        <button type="button" class="bulk-send-btn" id="openBulkModalBtn" onclick="openBulkModal()">
          <i class="fas fa-paper-plane"></i> Send 'No Play'
        </button>
      </div>
      <div id="userList" class="list"></div>
    </div>
    <div class="chatCol">
      <div id="chatHead" class="head" style="flex-direction: column; justify-content: center; gap: 8px; position: relative; height: auto; min-height: 50px;">
        <div style="display:flex; align-items:center; width: 100%;"><button id="backToList" class="fas fa-arrow-left" style="display:none; border:none; background:none; font-size:18px; cursor:pointer; color:#b71c1c; padding: 4px;"></button></div>

        <div class="quick" id="quickReplies" style="display:none; justify-content: center; flex-wrap: wrap;">
          <button data-text="Welcome to SUBH GAMING!">Welcome</button>
          <button data-text="app me play, deposit ya withdrawal me koi bhi problem ho to live support me message karo 24/7">Problem</button>
          <button data-text="Hello sir aap ne app install kiya hai but play nhi huwa koi problem aa raha ho to live support me message karo aur whatsapp karo.">No Play</button>
          <button data-text="AAP KA DEPOSIT FAIL HO GAYA HAI QR SE DEPOSIT KAR SAKTE HO.">Deposit Issue</button>
          <button data-text="Please wait, support will reply.">Wait</button>
        </div>
      </div>
      <div id="chatBox" class="chat"></div>
      <button id="scrollUp" class="scrollBtn up" title="Scroll to Top"><i class="fas fa-arrow-up"></i></button>
      <button id="scrollDown" class="scrollBtn down" title="Scroll to Bottom"><i class="fas fa-arrow-down"></i></button>
      <form id="form" class="inputRow" style="display:none">
        <input type="file" id="screenshotInput" accept="image/png, image/jpeg, image/jpg, image/webp" style="display: none;">
        <button type="button" id="screenshotBtn" style="background: #666;"><i class="fas fa-camera"></i></button>
        <input id="msg" autocomplete="off" placeholder="Type a message...">
        <button type="submit"><i class="fas fa-paper-plane"></i></button>
      </form>
    </div>
  </div>

  <!-- Bulk Message Modal (Balance 0 & Deposit 0) -->
  <div id="bulkMessageModal" class="admin-modal-overlay">
    <div class="admin-modal-box">
      <div class="admin-modal-title">
        <i class="fas fa-bullhorn"></i> Send Message to Selected Users (<span id="bulkModalCount">0</span>)
      </div>
      <div style="font-size:12px; color:#666; margin-bottom:10px; line-height:1.4;">
        Yeh message sirf un users ko jayega jinka <b>Balance 0 aur Deposit 0</b> hai. Ek click me sabhi eligible users ko message chala jayega.
      </div>
      <textarea id="bulkMsgTextarea" class="admin-modal-textarea" maxlength="1000" placeholder="Type message to broadcast...">Hello sir aap ne app install kiya hai but play nhi huwa koi problem aa raha ho to live support me message karo aur whatsapp karo.</textarea>
      
      <!-- Live Sending Progress Box -->
      <div id="bulkProgressBox" class="bulk-progress-box" style="display:none;">
        <div class="bulk-progress-info">
          <span><i class="fas fa-spinner fa-spin" style="margin-right:5px; color:#e53935;"></i> Sending: <span id="bulkProgressRatio">0 / 0</span></span>
          <span class="pct" id="bulkProgressPct">0%</span>
        </div>
        <div class="bulk-progress-track">
          <div class="bulk-progress-fill" id="bulkProgressFill"></div>
        </div>
        <div class="bulk-status-detail" id="bulkStatusDetail">
          Sending messages in background...
        </div>
      </div>

      <div class="admin-modal-actions">
        <button type="button" class="admin-modal-btn cancel" id="bulkCancelBtn" onclick="closeBulkModal()">Cancel</button>
        <button type="button" class="admin-modal-btn save" id="bulkSendSubmitBtn" onclick="submitBulkMessage()">
          <i class="fas fa-paper-plane"></i> Send to All (<span id="bulkSubmitCount">0</span>)
        </button>
      </div>
    </div>
  </div>

  <!-- Admin Edit Message Modal -->
  <div id="adminEditModal" class="admin-modal-overlay">
    <div class="admin-modal-box">
      <div class="admin-modal-title">
        <i class="fas fa-edit"></i> Edit Message
      </div>
      <textarea id="adminEditMsgTextarea" class="admin-modal-textarea" maxlength="1000" placeholder="Edit message..."></textarea>
      <div class="admin-modal-actions">
        <button type="button" class="admin-modal-btn cancel" id="adminCancelEditBtn">Cancel</button>
        <button type="button" class="admin-modal-btn save" id="adminSaveEditBtn">Save Changes</button>
      </div>
    </div>
  </div>

  <script>
    const API = 'admin_live_chat.php';
    let uid = null;
    let inFetch = false;
    let userDisplayLimit = 25;
    const adminMsgMap = new Map();
    let currentUserSeen = 0;
    let currentUserOnline = false;
    let editingAdminMsgId = null;

    const adminEditModal = document.getElementById('adminEditModal');
    const adminEditMsgTextarea = document.getElementById('adminEditMsgTextarea');
    const adminCancelEditBtn = document.getElementById('adminCancelEditBtn');
    const adminSaveEditBtn = document.getElementById('adminSaveEditBtn');

    let currentFilter = '';
    let filteredUsersList = [];

    const bulkMessageModal = document.getElementById('bulkMessageModal');
    const bulkMsgTextarea = document.getElementById('bulkMsgTextarea');
    const bulkSendSubmitBtn = document.getElementById('bulkSendSubmitBtn');
    const bulkCancelBtn = document.getElementById('bulkCancelBtn');

    function setFilter(type) {
      currentFilter = type || '';
      userDisplayLimit = 25;
      
      const allBtn = document.getElementById('filterAllBtn');
      const zeroBtn = document.getElementById('filterZeroBtn');
      const bulkBar = document.getElementById('bulkActionBar');

      if (allBtn) allBtn.classList.toggle('active', currentFilter === '');
      if (zeroBtn) zeroBtn.classList.toggle('active', currentFilter === 'zero_bal_dep');
      if (bulkBar) bulkBar.style.display = (currentFilter === 'zero_bal_dep') ? 'flex' : 'none';

      loadUsers();
      if (currentFilter !== 'zero_bal_dep') {
        updateZeroCountBadge();
      }
    }

    function updateZeroCountBadge() {
      fetch(API + '?action=users&filter=zero_bal_dep&_=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' })
        .then(r => r.json())
        .then(list => {
          if (Array.isArray(list)) {
            const badge = document.getElementById('filterZeroBadge');
            if (badge) badge.textContent = list.length;
          }
        }).catch(() => {});
    }

    let isBulkSending = false;

    function openBulkModal() {
      const count = filteredUsersList.length;
      document.getElementById('bulkModalCount').textContent = count;
      document.getElementById('bulkSubmitCount').textContent = count;
      
      const progBox = document.getElementById('bulkProgressBox');
      if (progBox) progBox.style.display = 'none';
      const fill = document.getElementById('bulkProgressFill');
      if (fill) fill.style.width = '0%';
      const pct = document.getElementById('bulkProgressPct');
      if (pct) pct.textContent = '0%';
      const ratio = document.getElementById('bulkProgressRatio');
      if (ratio) ratio.textContent = '0 / ' + count;
      
      bulkMessageModal.classList.add('show');
      setTimeout(() => bulkMsgTextarea.focus(), 80);
    }

    function closeBulkModal() {
      if (isBulkSending) {
        if (!confirm('Message sending is in progress. Are you sure you want to close this window? Sending will continue for remaining batches.')) {
          return;
        }
      }
      bulkMessageModal.classList.remove('show');
    }

    async function submitBulkMessage() {
      const msg = bulkMsgTextarea.value.trim();
      if (!msg) {
        alert('Please enter a message to send!');
        return;
      }

      const totalCount = filteredUsersList.length;
      if (totalCount === 0) {
        alert('No users selected!');
        return;
      }

      if (!confirm(`Are you sure you want to send this message to ${totalCount} users with Balance = 0 and Deposit = 0?`)) {
        return;
      }

      isBulkSending = true;
      bulkSendSubmitBtn.disabled = true;
      bulkCancelBtn.disabled = true;
      bulkMsgTextarea.disabled = true;

      const progBox = document.getElementById('bulkProgressBox');
      const ratioEl = document.getElementById('bulkProgressRatio');
      const pctEl = document.getElementById('bulkProgressPct');
      const fillEl = document.getElementById('bulkProgressFill');
      const statusDetail = document.getElementById('bulkStatusDetail');

      if (progBox) progBox.style.display = 'block';
      if (fillEl) fillEl.style.width = '0%';
      if (pctEl) pctEl.textContent = '0%';
      if (ratioEl) ratioEl.textContent = `0 / ${totalCount}`;

      const uids = filteredUsersList.map(u => u.user_id);
      const BATCH_SIZE = 20; // 20 users per request for smooth real-time progress update
      let sentTotal = 0;

      for (let i = 0; i < uids.length; i += BATCH_SIZE) {
        const chunk = uids.slice(i, i + BATCH_SIZE);
        const chunkIndex = Math.min(i + chunk.length, uids.length);

        if (statusDetail) {
          statusDetail.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Sending batch ${Math.floor(i / BATCH_SIZE) + 1} of ${Math.ceil(uids.length / BATCH_SIZE)}...`;
        }

        try {
          const fd = new FormData();
          fd.append('action', 'broadcast_zero_bal_dep');
          fd.append('message', msg);
          fd.append('user_ids', JSON.stringify(chunk));

          const r = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' });
          const res = await r.json();

          if (res && res.ok) {
            sentTotal += (res.sent_count || chunk.length);
          } else {
            sentTotal += chunk.length;
          }
        } catch (err) {
          sentTotal += chunk.length;
        }

        const currentSent = Math.min(chunkIndex, totalCount);
        const percentage = Math.min(100, Math.round((currentSent / totalCount) * 100));

        if (ratioEl) ratioEl.textContent = `${currentSent} / ${totalCount}`;
        if (pctEl) pctEl.textContent = `${percentage}%`;
        if (fillEl) fillEl.style.width = `${percentage}%`;
        bulkSendSubmitBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Sending... ${currentSent} / ${totalCount} (${percentage}%)`;
      }

      isBulkSending = false;
      bulkSendSubmitBtn.disabled = false;
      bulkCancelBtn.disabled = false;
      bulkMsgTextarea.disabled = false;
      bulkSendSubmitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Completed!';

      if (statusDetail) {
        statusDetail.innerHTML = `<b style="color:#2e7d32;"><i class="fas fa-check-circle"></i> Successfully sent to ${totalCount} users (100%)!</b>`;
      }

      setTimeout(() => {
        alert(`Success! Message sent to ${totalCount} users (${totalCount}/${totalCount} - 100%).`);
        closeBulkModal();
        bulkSendSubmitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send to All (<span id="bulkSubmitCount">' + totalCount + '</span>)';
        loadUsers();
        if (uid) fetchMsgs(false);
      }, 700);
    }

    function cleanId(x) { if (!x) return ''; let id = String(x).toUpperCase().replace(/\s+/g, ''); let m = id.match(/^([A-Z]+)0*([0-9]+)$/); if (m) return m[1] + m[2].padStart(2, '0'); if (/^\d+$/.test(id)) return 'MK' + id.padStart(2, '0'); return id.replace(/[^A-Z0-9_-]/g, ''); }

    function row(u) {
      const displayId = cleanId(u.user_id);
      const name = (u.name && u.name.trim()) ? u.name : displayId;
      const time = u.time || '';
      const mob = (u.mobile && String(u.mobile).trim()) ? String(u.mobile).trim() : '';
      return `<div class="nameWrap">
            <span class="dot ${u.live ? 'green' : 'grey'}"></span>
            <div class="info">
              <span>${name}</span>
              <span class="uid">${displayId}</span>
              ${mob ? `<span class="mob">${mob}</span>` : ``}
              <span class="time">${time}</span>
            </div>
          </div>
          <span class="unread ${u.unread ? '' : 'hide'}">NEW</span>`;
    }

    function loadUsers() {
      const term = (document.getElementById('search').value || '').trim();
      let url = API + '?action=users&q=' + encodeURIComponent(term);
      if (currentFilter) {
        url += '&filter=' + encodeURIComponent(currentFilter);
      }
      url += '&_=' + Date.now();

      fetch(url, { cache: 'no-store', credentials: 'same-origin' })
        .then(r => r.json())
        .then(list => {
          const box = document.getElementById('userList');
          box.innerHTML = '';
          let onlineCount = 0, unreadCount = 0;

          list.forEach(u => { if (u.live) onlineCount++; if (u.unread) unreadCount++; });
          document.getElementById('statsBar').innerHTML =
            `<span>Online: ${onlineCount}</span><span>Unread: ${unreadCount}</span>`;

          if (currentFilter === 'zero_bal_dep') {
            filteredUsersList = list;
            const badge = document.getElementById('filterZeroBadge');
            if (badge) badge.textContent = list.length;
            const countLabel = document.getElementById('bulkUserCountLabel');
            if (countLabel) countLabel.textContent = `${list.length} user${list.length === 1 ? '' : 's'} (Bal 0 & Dep 0)`;
            const modalCount = document.getElementById('bulkModalCount');
            if (modalCount) modalCount.textContent = list.length;
            const submitCount = document.getElementById('bulkSubmitCount');
            if (submitCount) submitCount.textContent = list.length;
          }

          if (uid) {
            const targetIdx = list.findIndex(u => cleanId(u.user_id) === uid);
            if (targetIdx >= userDisplayLimit) {
              userDisplayLimit = targetIdx + 10;
            }
            const matched = list.find(u => cleanId(u.user_id) === uid);
            if (matched) {
              const chatTitleEl = document.getElementById('chatTitle') || document.getElementById('chatHead')?.querySelector('span');
              if (chatTitleEl) {
                const nameText = (matched.name && matched.name.trim()) ? `${matched.name} (${esc(uid)})` : esc(uid);
                chatTitleEl.innerHTML = `Chat: <a href="bio_data.php?id=${encodeURIComponent(uid)}" target="_blank" class="user-bio-link" title="Open Bio Data for ${esc(uid)}">${nameText} <i class="fas fa-external-link-alt" style="font-size:11px;"></i></a>`;
              }
            }
          }

          const visibleList = list.slice(0, userDisplayLimit);
          if (visibleList.length === 0) {
            const emptyDiv = document.createElement('div');
            emptyDiv.style.cssText = 'padding:20px 12px; text-align:center; color:#888; font-size:13px;';
            emptyDiv.innerHTML = currentFilter === 'zero_bal_dep' 
              ? '<i class="fas fa-info-circle"></i> No users found with Balance 0 & Deposit 0'
              : 'No users found';
            box.appendChild(emptyDiv);
          } else {
            visibleList.forEach(u => {
              const div = document.createElement('div');
              div.className = 'row' + (cleanId(u.user_id) === uid ? ' active' : '');
              div.onclick = () => openThread(u.user_id);
              div.innerHTML = row(u);
              box.appendChild(div);
            });
          }

          if (list.length > userDisplayLimit) {
            const loadMoreBtn = document.createElement('button');
            loadMoreBtn.textContent = 'Load More Users (' + (list.length - userDisplayLimit) + ' remaining)';
            loadMoreBtn.style.cssText = 'width:100%; padding:10px; background:#f0f0f0; border:none; border-top:1px solid #ddd; cursor:pointer; font-size:12px; font-weight:600; color:#555; text-align:center; margin-top:5px;';
            loadMoreBtn.onclick = () => {
              userDisplayLimit += 25;
              loadUsers();
            };
            box.appendChild(loadMoreBtn);
          }
        })
        .catch(() => { });
    }

    function esc(s) {
      return (s ?? '').toString().replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
      }[m]));
    }

    function getAdminTickData(m, userSeen, userOnline) {
      if (m.sender !== 'admin') return null;
      const mid = parseInt(m.id, 10) || 0;
      if (mid <= userSeen) {
        return { src: 'icons/green_tick.svg', alt: 'Seen', title: 'Seen' };
      } else if (userOnline) {
        return { src: 'icons/double_tick.svg', alt: 'Delivered', title: 'Delivered' };
      } else {
        return { src: 'icons/single_tick.svg', alt: 'Sent', title: 'Sent' };
      }
    }

    function closeAllAdminMenus() {
      document.querySelectorAll('.msg-menu.show').forEach(m => m.classList.remove('show'));
      document.querySelectorAll('.msg-opt-btn.active').forEach(b => b.classList.remove('active'));
    }
    document.addEventListener('click', closeAllAdminMenus);

    function openAdminEditModal(id, currentText) {
      editingAdminMsgId = id;
      adminEditMsgTextarea.value = currentText || '';
      adminEditModal.classList.add('show');
      setTimeout(() => adminEditMsgTextarea.focus(), 80);
    }

    function closeAdminEditModal() {
      editingAdminMsgId = null;
      adminEditModal.classList.remove('show');
    }

    adminCancelEditBtn.addEventListener('click', closeAdminEditModal);
    adminEditModal.addEventListener('click', (e) => {
      if (e.target === adminEditModal) closeAdminEditModal();
    });

    async function saveAdminEditedMessage() {
      if (!editingAdminMsgId) return;
      const newText = adminEditMsgTextarea.value.trim();
      if (!newText) {
        alert('Message cannot be empty!');
        return;
      }

      adminSaveEditBtn.disabled = true;
      try {
        const fd = new FormData();
        fd.append('action', 'edit');
        fd.append('id', editingAdminMsgId);
        fd.append('message', newText);

        const r = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await r.json();
        if (j.ok) {
          closeAdminEditModal();
          fetchMsgs(false);
        } else {
          alert(j.error || 'Failed to edit message');
        }
      } catch (e) {
        alert('Network error while editing message');
      } finally {
        adminSaveEditBtn.disabled = false;
      }
    }
    adminSaveEditBtn.addEventListener('click', saveAdminEditedMessage);

    async function deleteAdminMessage(id) {
      if (!confirm('Delete this message?')) return;

      try {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);

        const r = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await r.json();
        if (j.ok) {
          const mid = parseInt(id, 10);
          if (adminMsgMap.has(mid)) {
            const el = adminMsgMap.get(mid);
            el.style.transition = 'opacity 0.2s, transform 0.2s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-8px)';
            setTimeout(() => {
              el.remove();
              adminMsgMap.delete(mid);
            }, 200);
          }
        } else {
          alert(j.error || 'Failed to delete message');
        }
      } catch (e) {
        alert('Network error while deleting message');
      }
    }

    function createAdminMessageElement(m, userSeen, userOnline) {
      const mid = parseInt(m.id, 10) || 0;
      const wrap = document.createElement('div');
      wrap.className = 'wrap ' + (m.sender === 'admin' ? 'admin' : 'user');
      wrap.setAttribute('data-id', mid);

      const bodyRow = document.createElement('div');
      bodyRow.className = 'msg-body-row';

      const bub = document.createElement('div');
      bub.className = 'bub';

      let imgHtml = '';
      if (m.image) {
        imgHtml = `<img src="${m.image}" style="max-width:200px; display:block; border-radius:8px; margin-bottom:5px; cursor:pointer;" onclick="window.open('${m.image}','_blank')">`;
      }

      const msgTextDiv = document.createElement('div');
      msgTextDiv.className = 'msg-text';
      if (m.message && m.message.trim() !== '') {
        msgTextDiv.innerHTML = esc(m.message).replace(/\n/g, '<br>');
      } else {
        msgTextDiv.style.display = 'none';
      }

      bub.innerHTML = imgHtml;
      bub.appendChild(msgTextDiv);
      bodyRow.appendChild(bub);

      // Options menu (Edit / Delete)
      const optBtn = document.createElement('button');
      optBtn.className = 'msg-opt-btn';
      optBtn.type = 'button';
      optBtn.title = 'Message options';
      optBtn.innerHTML = '<i class="fas fa-ellipsis-v"></i>';

      const menu = document.createElement('div');
      menu.className = 'msg-menu';

      if (m.sender === 'admin' && m.message && m.message.trim() !== '') {
        const editBtn = document.createElement('button');
        editBtn.className = 'msg-menu-item';
        editBtn.type = 'button';
        editBtn.innerHTML = '<i class="fas fa-edit"></i> Edit';
        editBtn.onclick = (e) => {
          e.stopPropagation();
          closeAllAdminMenus();
          openAdminEditModal(mid, m.message);
        };
        menu.appendChild(editBtn);
      }

      const delBtn = document.createElement('button');
      delBtn.className = 'msg-menu-item danger';
      delBtn.type = 'button';
      delBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
      delBtn.onclick = (e) => {
        e.stopPropagation();
        closeAllAdminMenus();
        deleteAdminMessage(mid);
      };
      menu.appendChild(delBtn);

      optBtn.onclick = (e) => {
        e.stopPropagation();
        const isOpen = menu.classList.contains('show');
        closeAllAdminMenus();
        if (!isOpen) {
          menu.classList.add('show');
          optBtn.classList.add('active');
        }
      };

      bodyRow.appendChild(optBtn);
      bodyRow.appendChild(menu);
      wrap.appendChild(bodyRow);

      // Meta info (time, edited, tick)
      const meta = document.createElement('div');
      meta.className = 'meta';

      const timeSpan = document.createElement('span');
      timeSpan.className = 'time';
      timeSpan.innerText = m.dt || '';
      meta.appendChild(timeSpan);

      const editSpan = document.createElement('span');
      editSpan.className = 'edited-tag';
      editSpan.innerText = '(edited)';
      editSpan.style.display = (m.is_edited == 1) ? 'inline' : 'none';
      meta.appendChild(editSpan);

      if (m.sender === 'admin') {
        const tickWrap = document.createElement('span');
        tickWrap.className = 'tick-wrap';
        const tickData = getAdminTickData(m, userSeen, userOnline);
        const tickImg = document.createElement('img');
        tickImg.className = 'chat-tick';
        tickImg.src = tickData.src;
        tickImg.alt = tickData.alt;
        tickImg.title = tickData.title;
        tickWrap.appendChild(tickImg);
        meta.appendChild(tickWrap);
      }

      wrap.appendChild(meta);
      return wrap;
    }

    function updateAdminMessageElement(el, m, userSeen, userOnline) {
      const msgDiv = el.querySelector('.msg-text');
      if (msgDiv) {
        if (m.message && m.message.trim() !== '') {
          msgDiv.style.display = 'block';
          const newHtml = esc(m.message).replace(/\n/g, '<br>');
          if (msgDiv.innerHTML !== newHtml) {
            msgDiv.innerHTML = newHtml;
          }
        } else {
          msgDiv.style.display = 'none';
        }
      }

      const editSpan = el.querySelector('.edited-tag');
      if (editSpan) {
        editSpan.style.display = (m.is_edited == 1) ? 'inline' : 'none';
      }

      if (m.sender === 'admin') {
        let tickWrap = el.querySelector('.tick-wrap');
        const tickData = getAdminTickData(m, userSeen, userOnline);
        if (tickData) {
          if (!tickWrap) {
            const meta = el.querySelector('.meta');
            if (meta) {
              tickWrap = document.createElement('span');
              tickWrap.className = 'tick-wrap';
              const tickImg = document.createElement('img');
              tickImg.className = 'chat-tick';
              tickImg.src = tickData.src;
              tickImg.alt = tickData.alt;
              tickImg.title = tickData.title;
              tickWrap.appendChild(tickImg);
              meta.appendChild(tickWrap);
            }
          } else {
            const tickImg = tickWrap.querySelector('.chat-tick');
            if (tickImg && tickImg.getAttribute('src') !== tickData.src) {
              tickImg.src = tickData.src;
              tickImg.alt = tickData.alt;
              tickImg.title = tickData.title;
            }
          }
        }
      }
    }

    function fetchUserStats(userId) {
      if(!document.getElementById('chatUserStats')) return;
      document.getElementById('chatUserStats').style.display = 'none';
      fetch(API + '?action=user_stats&uid=' + encodeURIComponent(userId))
        .then(r => r.json())
        .then(d => {
          if (d && d.ok) {
            document.getElementById('statDep').textContent = '₹' + Number(d.sumDep).toLocaleString('en-IN', {minimumFractionDigits:0, maximumFractionDigits:2});
            document.getElementById('statWith').textContent = '₹' + Number(d.sumWithAll).toLocaleString('en-IN', {minimumFractionDigits:0, maximumFractionDigits:2});
            
            const plEl = document.getElementById('statPL');
            let pl = Number(d.pl);
            plEl.textContent = '₹' + Math.abs(pl).toLocaleString('en-IN', {minimumFractionDigits:0, maximumFractionDigits:2});
            if (pl > 0) {
              plEl.style.color = '#2e7d32'; // profit (green)
              plEl.textContent = '+' + plEl.textContent;
            } else if (pl < 0) {
              plEl.style.color = '#c62828'; // loss (red)
              plEl.textContent = '-' + plEl.textContent;
            } else {
              plEl.style.color = '#333';
            }
            document.getElementById('chatUserStats').style.display = 'flex';
            if (document.getElementById('chatAccountAge')) {
              if (d.age) {
                document.getElementById('chatAccountAge').textContent = d.age;
                document.getElementById('chatAccountAge').style.display = 'inline-block';
              } else {
                document.getElementById('chatAccountAge').style.display = 'none';
              }
            }
            if (document.getElementById('chatLocation')) {
              if (d.city) {
                document.getElementById('chatLocation').querySelector('span').textContent = d.city;
                document.getElementById('chatLocation').style.display = 'inline-block';
              } else {
                document.getElementById('chatLocation').style.display = 'none';
              }
            }
          }
        }).catch(()=>{});
    }

    function openThread(id) {
      uid = cleanId(id);
      adminMsgMap.clear();
      document.querySelector('.main').classList.add('chat-open');
      document.getElementById('backToList').style.display = (window.innerWidth <= 768) ? 'block' : 'none';
      const chatTitleEl = document.getElementById('chatTitle') || document.getElementById('chatHead').querySelector('span');
      chatTitleEl.innerHTML = `Chat: <a href="bio_data.php?id=${encodeURIComponent(uid)}" target="_blank" class="user-bio-link" title="Open Bio Data for ${esc(uid)}">${esc(uid)} <i class="fas fa-external-link-alt" style="font-size:11px;"></i></a>`;
      document.getElementById('form').style.display = 'flex';
      document.getElementById('quickReplies').style.display = 'flex';
      document.getElementById('chatBox').innerHTML = '';
      loadUsers();
      fetchMsgs(true);
      fetchUserStats(uid);
    }

    function closeThread() {
      uid = null;
      adminMsgMap.clear();
      document.querySelector('.main').classList.remove('chat-open');
      document.getElementById('backToList').style.display = 'none';
      const chatTitleEl = document.getElementById('chatTitle') || document.getElementById('chatHead').querySelector('span');
      chatTitleEl.textContent = 'Select a User...';
      if(document.getElementById('chatLiveStatus')) document.getElementById('chatLiveStatus').innerHTML = '';
      if(document.getElementById('chatUserStats')) document.getElementById('chatUserStats').style.display = 'none';
      if(document.getElementById('chatAccountAge')) document.getElementById('chatAccountAge').style.display = 'none';
      if(document.getElementById('chatLocation')) document.getElementById('chatLocation').style.display = 'none';
      document.getElementById('form').style.display = 'none';
      document.getElementById('quickReplies').style.display = 'none';
      document.getElementById('chatBox').innerHTML = '';
      loadUsers();
    }

    document.getElementById('backToList').addEventListener('click', closeThread);

    window.addEventListener('resize', () => {
      if (uid && window.innerWidth <= 768) {
        document.getElementById('backToList').style.display = 'block';
      } else {
        document.getElementById('backToList').style.display = 'none';
      }
    });

    function fetchMsgs(full = false) {
      if (!uid || inFetch) return;
      inFetch = true;

      fetch(API + '?action=fetch&uid=' + encodeURIComponent(uid) + '&_=' + Date.now(),
        { cache: 'no-store', credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
          if (!data || !data.ok) return;
          const messages = data.messages || [];
          currentUserSeen = parseInt(data.user_seen, 10) || 0;
          currentUserOnline = !!data.user_online;
          if (document.getElementById('chatLiveStatus')) {
            document.getElementById('chatLiveStatus').innerHTML = data.live_html || '';
          }

          const box = document.getElementById('chatBox');
          const near = box.scrollHeight - box.scrollTop - box.clientHeight < 40;

          const currentMsgIds = new Set(messages.map(m => parseInt(m.id, 10)));

          // Remove messages deleted on server
          for (let [id, el] of adminMsgMap.entries()) {
            if (!currentMsgIds.has(id)) {
              el.remove();
              adminMsgMap.delete(id);
            }
          }

          let addedNew = false;
          messages.forEach(m => {
            const mid = parseInt(m.id, 10) || 0;
            if (adminMsgMap.has(mid)) {
              const el = adminMsgMap.get(mid);
              updateAdminMessageElement(el, m, currentUserSeen, currentUserOnline);
            } else {
              const el = createAdminMessageElement(m, currentUserSeen, currentUserOnline);
              box.appendChild(el);
              adminMsgMap.set(mid, el);
              addedNew = true;
            }
          });

          if (full || (addedNew && near)) {
            box.scrollTop = box.scrollHeight;
          }
        })
        .then(() => loadUsers())
        .catch(() => { })
        .finally(() => { inFetch = false; });
    }

    document.getElementById('search').addEventListener('input', () => loadUsers());

    document.getElementById('form').addEventListener('submit', e => {
      e.preventDefault();
      const v = document.getElementById('msg').value.trim();
      const fileInput = document.getElementById('screenshotInput');
      if ((!v && !fileInput.files.length) || !uid) return;

      const fd = new FormData();
      fd.append('action', 'send');
      fd.append('uid', uid);
      fd.append('message', v);
      if (fileInput.files.length) {
        fd.append('screenshot', fileInput.files[0]);
      }

      fetch(API, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      }).then(() => {
        document.getElementById('msg').value = '';
        document.getElementById('msg').placeholder = "Type a message...";
        fileInput.value = '';
        document.getElementById('screenshotBtn').style.background = '#666';
        fetchMsgs(false);
      }).catch(() => { });
    });

    document.getElementById('screenshotBtn').addEventListener('click', () => {
      document.getElementById('screenshotInput').click();
    });
    document.getElementById('screenshotInput').addEventListener('change', function () {
      if (this.files.length) {
        document.getElementById('screenshotBtn').style.background = '#4caf50';
        document.getElementById('msg').placeholder = "Send attachment file...";
      } else {
        document.getElementById('screenshotBtn').style.background = '#666';
        document.getElementById('msg').placeholder = "Type a message...";
      }
    });

    document.getElementById('quickReplies').addEventListener('click', e => {
      const txt = e.target.getAttribute('data-text');
      if (txt) {
        document.getElementById('msg').value = txt;
        document.getElementById('msg').focus();
      }
    });

    const down = document.getElementById('scrollDown');
    const up = document.getElementById('scrollUp');
    const box = document.getElementById('chatBox');
    box.addEventListener('scroll', () => {
      const near = box.scrollHeight - box.scrollTop - box.clientHeight < 6;
      if (down) down.classList.toggle('show', !near);
      if (up) up.classList.toggle('show', box.scrollTop > 100);
    });
    if (down) down.addEventListener('click', () => box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' }));
    if (up) up.addEventListener('click', () => box.scrollTo({ top: 0, behavior: 'smooth' }));
    const urlParams = new URLSearchParams(window.location.search);
    const initialUid = urlParams.get('uid') || urlParams.get('user_id') || urlParams.get('user');
    if (initialUid) {
      openThread(initialUid);
    } else {
      loadUsers();
    }
    updateZeroCountBadge();
    setInterval(loadUsers, 5000);
    setInterval(updateZeroCountBadge, 15000);
    setInterval(() => fetchMsgs(false), 1200);
  </script>
</body>

</html>






















