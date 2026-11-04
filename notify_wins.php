<?php
ignore_user_abort(true);
set_time_limit(0);
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

/* =======================
   JSON + no-cache
======================= */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/* =======================
   Debug (prod me 0 hi rakho)
======================= */
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* =======================
   Helpers
======================= */
function fail($msg, $code = 500, $extra = [])
{
  http_response_code($code);
  echo json_encode(['status' => 'error', 'message' => $msg] + $extra, JSON_UNESCAPED_SLASHES);
  exit;
}
function strim($v)
{
  return trim((string) ($v ?? ''));
}

/* ✅ normalize bet_type safely (fix HS + variants) */
function norm_bet_type($raw): string
{
  $s = strtolower(strim($raw));
  if ($s === '')
    return '';
  $s = str_replace(['-', ' '], '_', $s);
  $s = preg_replace('/_+/', '_', $s);

  if (in_array($s, ['hs', 'half', 'half_sangam', 'half_sangam_'], true))
    return 'half_sangam';
  if (in_array($s, ['fs', 'full', 'full_sangam', 'full_sangam_'], true))
    return 'full_sangam';

  $allowed = ['full_sangam', 'half_sangam', 'jodi_digit', 'single_digit', 'single_pana', 'double_pana', 'triple_pana'];
  return in_array($s, $allowed, true) ? $s : '';
}

require_once __DIR__ . '/includes/fcm_helper.php';

/* ✅ normalize user_id */
function norm_uid($uid): string
{
  return normalize_uid($uid);
}

/* =======================
   ADMIN AUTH (IMPORTANT)
   - is endpoint ko public mat chhodo
   - default: admin session required
   - optional: ?key=YOUR_KEY (if you want cron)
======================= */
define('NOTIFY_WINS_KEY', 'LL_ADMIN_NOTIFY_2026'); // change this
$providedKey = strim($_GET['key'] ?? '');

if (!isset($_SESSION['admin']) && ($providedKey === '' || $providedKey !== NOTIFY_WINS_KEY)) {
  fail('Unauthorized', 401);
}

/* =======================
   CONFIG
======================= */
$FIREBASE_PROJECT_ID = 'kismat555-b686e';
$SA_PATH = __DIR__ . '/secure/service-account.json';

/* =======================
   Sanity
======================= */
if (!function_exists('curl_init'))
  fail('cURL extension not enabled.');
if (!function_exists('openssl_sign'))
  fail('OpenSSL extension not enabled.');
if ($FIREBASE_PROJECT_ID === '')
  fail('Please set FIREBASE_PROJECT_ID');

/* =======================
   DB
======================= */
$db = @db();
if ($db->connect_errno)
  fail('DB connection failed', 500, ['db_error' => $db->connect_error]);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db->set_charset('utf8mb4');
$db->query("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
$db->query("SET collation_connection = 'utf8mb4_general_ci'");
$db->query("SET time_zone = '+05:30'");

/* =======================
   Schema ensure (safe)
======================= */
$chk = $db->query("SHOW COLUMNS FROM `wining` LIKE 'notified'");
if (!$chk)
  fail('Could not inspect wining table.', 500, ['sql_error' => $db->error]);
if ($chk->num_rows === 0) {
  if (!$db->query("ALTER TABLE `wining` ADD COLUMN `notified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `win_time`")) {
    fail('Failed adding wining.notified column', 500, ['sql_error' => $db->error]);
  }
}
$chk2 = $db->query("SHOW COLUMNS FROM `wining` LIKE 'notified_at'");
if ($chk2 && $chk2->num_rows === 0) {
  @$db->query("ALTER TABLE `wining` ADD COLUMN `notified_at` DATETIME NULL AFTER `notified`");
}

/* =======================
   Simple runtime lock (avoid double send)
   - works even without wins_run_lock
======================= */
$lockName = "ll_notify_wins_lock";
$gotLock = 0;
try {
  $rs = $db->query("SELECT GET_LOCK('{$lockName}', 1) AS l");
  $row = $rs ? $rs->fetch_assoc() : null;
  $gotLock = (int) ($row['l'] ?? 0);
} catch (Throwable $e) {
}
if ($gotLock !== 1) {
  fail('Busy: notify already running', 429);
}

/* =======================
   Filters
======================= */
$session = strtolower(strim($_GET['session'] ?? 'both'));
if (!in_array($session, ['open', 'close', 'both'], true))
  $session = 'both';

$market = strtoupper(strim($_GET['market'] ?? ''));

if ($market !== '') {
    $checkM = $db->prepare("SELECT id FROM markets WHERE UPPER(TRIM(market_name)) = ? AND is_published = 1");
    $checkM->bind_param("s", $market);
    $checkM->execute();
    if ($checkM->get_result()->num_rows === 0) {
        fail('Market not allowed or inactive for notifications.');
    }
}

$today = date('Y-m-d');
$from = strim($_GET['from'] ?? $today);
$to = strim($_GET['to'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
  $from = $today;
  $to = $today;
}
if ($from > $to) {
  $t = $from;
  $from = $to;
  $to = $t;
}

$dry = isset($_GET['dry']) ? 1 : 0;

/* =======================
   FCM helpers (token cache ✅)
======================= */
function fcm_v1_get_access_token($saPath)
{
  static $cache = ['token' => null, 'exp' => 0];

  if ($cache['token'] && time() < ($cache['exp'] - 60))
    return $cache['token'];

  if (!is_readable($saPath))
    fail('Service account JSON not readable', 500, ['path' => $saPath]);
  $sa = json_decode(file_get_contents($saPath), true);
  if (!$sa || empty($sa['client_email']) || empty($sa['private_key']) || empty($sa['token_uri'])) {
    fail('Invalid service account JSON structure', 500);
  }

  $iss = $sa['client_email'];
  $aud = $sa['token_uri'];
  $scope = 'https://www.googleapis.com/auth/firebase.messaging';
  $now = time();

  $header = ['alg' => 'RS256', 'typ' => 'JWT'];
  $payload = ['iss' => $iss, 'scope' => $scope, 'aud' => $aud, 'iat' => $now, 'exp' => $now + 3600];

  $b64 = function ($x) {
    return rtrim(strtr(base64_encode(json_encode($x)), '+/', '-_'), '=');
  };
  $unsigned = $b64($header) . '.' . $b64($payload);

  $signature = '';
  if (!openssl_sign($unsigned, $signature, $sa['private_key'], 'sha256WithRSAEncryption')) {
    fail('OpenSSL sign failed.');
  }
  $jwt = $unsigned . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

  $ch = curl_init($aud);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_POSTFIELDS => http_build_query([
      'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion' => $jwt
    ]),
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => false
  ]);
  $resp = curl_exec($ch);
  if ($resp === false)
    fail('OAuth HTTP error', 500, ['curl' => curl_error($ch)]);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code !== 200)
    fail('OAuth bad status', 500, ['status' => $code, 'resp' => $resp]);

  $data = json_decode($resp, true);
  if (empty($data['access_token']))
    fail('OAuth: token missing', 500, ['resp' => $resp]);

  $cache['token'] = $data['access_token'];
  $cache['exp'] = $now + (int) ($data['expires_in'] ?? 3600);

  return $cache['token'];
}

function fcm_v1_send_to_topic_data_only($projectId, $saPath, $topic, $data = [])
{
  $token = fcm_v1_get_access_token($saPath);
  $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

  $payload = [
    'message' => [
      'topic' => $topic,
      'android' => [
        'priority' => 'HIGH'
      ],
      'data' => array_map('strval', (array) $data)
    ]
  ];

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $token,
      'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => false
  ]);
  $resp = curl_exec($ch);
  if ($resp === false)
    fail('FCM HTTP error', 500, ['curl' => curl_error($ch)]);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code !== 200)
    fail('FCM bad status', 500, ['status' => $code, 'resp' => $resp]);
  return true;
}

/* =======================
   Build WHERE
======================= */
$where = [];
$params = [];
$types = "";

$where[] = "notified=0";
$where[] = "(status='Win' OR status='WIN')";

/* ✅ If in future you mark reversals as status='Reversed', they will auto-exclude */
if ($session !== 'both') {
  $where[] = "(LOWER(IFNULL(session_type,''))=?)";
  $types .= "s";
  $params[] = $session;
}

if ($market !== '') {
  $where[] = "UPPER(market)=?";
  $types .= "s";
  $params[] = $market;
}

/* detect win_date */
$hasWinDate = false;
$chk = $db->query("SHOW COLUMNS FROM `wining` LIKE 'win_date'");
if ($chk && $chk->num_rows > 0)
  $hasWinDate = true;

if ($hasWinDate) {
  $where[] = "win_date BETWEEN ? AND ?";
  $types .= "ss";
  $params[] = $from;
  $params[] = $to;
} else {
  $where[] = "DATE(win_time) BETWEEN ? AND ?";
  $types .= "ss";
  $params[] = $from;
  $params[] = $to;
}

/* =======================
   SELECT
======================= */
$sql = "
SELECT
  id, user_id, market,
  IFNULL(session_type,'') AS session_type,
  bet_type,
  single_digit, jodi_digit,
  single_pana, double_pana, triple_pana,
  open_pana_x, close_pana_x,
  open_digit, close_digit,
  amount, wining_balance, win_time
FROM wining
WHERE " . implode(" AND ", $where) . "
ORDER BY win_time ASC
LIMIT 200
";

$st = $db->prepare($sql);
if (!$st)
  fail('Prepare failed', 500, ['sql_error' => $db->error]);

if ($types !== '') {
  $refs = [];
  foreach ($params as $k => $v)
    $refs[$k] = &$params[$k];
  array_unshift($refs, $types);
  call_user_func_array([$st, 'bind_param'], $refs);
}

if (!$st->execute())
  fail('Select execute failed', 500, ['sql_error' => $db->error]);
$res = $st->get_result();

$rows = [];
while ($r = $res->fetch_assoc())
  $rows[] = $r;
$st->close();

/* =======================
   Send notifications
======================= */
$sent = 0;
$preview = [];
$errors = 0;
$error_list = [];

foreach ($rows as $w) {

  $betType = norm_bet_type($w['bet_type']);

  $sessLower = strtolower(strim($w['session_type']));
  if (!in_array($sessLower, ['open', 'close'], true)) {
    $sessLower = ($betType === 'full_sangam' || $betType === 'jodi_digit') ? 'close' : 'open';
  }
  $sessUpper = strtoupper($sessLower);

  $sd = strim($w['single_digit']);
  $jd = strim($w['jodi_digit']);
  $sp = strim($w['single_pana']);
  $dp = strim($w['double_pana']);
  $tp = strim($w['triple_pana']);
  $opx = strim($w['open_pana_x']);
  $cpx = strim($w['close_pana_x']);

  if ($betType === '') {
    if ($opx !== '' && $cpx !== '')
      $betType = 'full_sangam';
    else if ($sd !== '' && ($opx !== '' || $cpx !== ''))
      $betType = 'half_sangam';
    else if ($jd !== '')
      $betType = 'jodi_digit';
    else if ($sp !== '')
      $betType = 'single_pana';
    else if ($dp !== '')
      $betType = 'double_pana';
    else if ($tp !== '')
      $betType = 'triple_pana';
    else if ($sd !== '')
      $betType = 'single_digit';
    else
      continue;
  }

  $value = '';
  switch ($betType) {
    case 'full_sangam': {
      if ($opx === '' || $cpx === '')
        continue 2;
      $value = $opx . '-' . $cpx;
      break;
    }
    case 'half_sangam': {
      $p = ($opx !== '') ? $opx : $cpx;
      $p = strim($p);
      $sd = strim($sd);
      if ($sd === '' || $p === '')
        continue 2;
      $value = $sd . '-' . $p;
      break;
    }
    case 'jodi_digit':
      $value = $jd;
      if ($value === '')
        continue 2;
      break;
    case 'single_digit':
      $value = $sd;
      if ($value === '')
        continue 2;
      break;
    case 'single_pana':
      $value = $sp;
      if ($value === '')
        continue 2;
      break;
    case 'double_pana':
      $value = $dp;
      if ($value === '')
        continue 2;
      break;
    case 'triple_pana':
      $value = $tp;
      if ($value === '')
        continue 2;
      break;
    default:
      continue 2;
  }

  $mkt = (string) $w['market'];

  // ✅ WIN & BID amounts
  $winAmt = (float) ($w['wining_balance'] ?? 0); // ✅ always wining_balance
  $bidAmt = (float) ($w['amount'] ?? 0);

  $title = $mkt . ' • ' . $sessUpper . ' WIN';
  $body = strtoupper(str_replace('_', ' ', $betType)) . ' ' . $value . ' | WIN ₹' . number_format($winAmt, 2);

  // ✅ DATA keys (app reads these)
  $data = [
    // detection
    'type' => 'win',
    'status' => 'win',

    // DATA-only title/body
    'title' => $title,
    'message' => $body,
    'body' => $body,
    'icon' => 'ic_notification',
    'image' => 'https://subhgaming.online/images/logo.png',
    'picture' => 'https://subhgaming.online/images/logo.png',

    'market' => $mkt,
    'session' => $sessLower,
    'session_type' => $sessLower,
    'bet_type' => $betType,
    'value' => $value,

    // ✅ old app compatibility
    'amount' => (string) $winAmt, // display amount
    'bid_amount' => (string) $bidAmt,
    'win_amount' => (string) $winAmt,
    'wining_balance' => (string) $winAmt,

    'win_id' => (string) $w['id'],
    'time' => (string) $w['win_time'],

    // extra
    'open_pana_x' => $opx,
    'close_pana_x' => $cpx,
    'open_digit' => (string) ($w['open_digit'] ?? ''),
    'close_digit' => (string) ($w['close_digit'] ?? ''),
    'icon' => 'ic_notification',
  ];

  $normUser = norm_uid($w['user_id']);

  if ($dry) {
    $preview[] = ['topic' => $normUser, 'data' => $data];
    continue;
  }

  try {
    // ✅ send DATA-only to all user topic variants
    $userVars = get_all_uid_variants($w['user_id'] ?? $normUser);
    if (empty($userVars)) { $userVars = [$normUser]; }
    foreach ($userVars as $vTopic) {
      if ($vTopic !== '') {
        @fcm_v1_send_to_topic_data_only($FIREBASE_PROJECT_ID, $SA_PATH, $vTopic, $data);
      }
    }

    // ✅ mark notified
    $upd = $db->prepare("UPDATE wining SET notified=1, notified_at=NOW() WHERE id=?");
    $idInt = (int) $w['id'];
    $upd->bind_param('i', $idInt);
    $upd->execute();
    $upd->close();

    $sent++;
  } catch (Throwable $e) {
    $errors++;
    $error_list[] = [
      'id' => (int) $w['id'],
      'user_id' => (string) $w['user_id'],
      'err' => $e->getMessage()
    ];
  }
}

/* release lock */
try {
  $db->query("DO RELEASE_LOCK('{$lockName}')");
} catch (Throwable $e) {
}

$db->close();

/* =======================
   Output
======================= */
echo json_encode([
  'status' => 'ok',
  'sent' => $sent,
  'errors' => $errors,
  'error_list' => ($dry ? null : ($errors ? $error_list : [])),
  'mode' => $dry ? 'dry' : 'live',
  'session' => $session,
  'market' => $market,
  'from' => $from,
  'to' => $to,
  'preview' => $dry ? $preview : null,
  'note' => [
    '✅ TRUE DATA-ONLY: FCM payload me "notification" block bilkul nahi bheja.',
    '✅ amount=win_amount=wining_balance (Half Sangam bhi correct).',
    '✅ Admin auth added (session admin OR ?key=NOTIFY_WINS_KEY).',
    '✅ GET_LOCK added to avoid double-send while delete/revise operations.'
  ]
], JSON_UNESCAPED_SLASHES);





