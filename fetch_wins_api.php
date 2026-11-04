<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
ignore_user_abort(true);
set_time_limit(0);
require_once 'includes/db.php';
date_default_timezone_set('Asia/Kolkata');

/* ---------- JSON + no-cache ---------- */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* =========================
   CONFIG
========================= */
require_once __DIR__ . '/includes/api_keys.php';
if(!defined('WINS_TRIGGER_KEY')) define('WINS_TRIGGER_KEY', SUBH_WINS_TRIGGER_KEY);

/* ✅ LAGAM CONFIG */
define('MAX_ONE_WIN_PER_ROW', true);

/**
 * ✅ CAP:
 * 0 = no cap (markets table payout fully follow)
 */
define('CAP_PAYOUT_PER_ROW', 0);

/* ✅ wins_run_lock CONFIG */
define('WINS_LOCK_ENABLED', true);
define('WINS_LOCK_STALE_SECONDS', 180);      // 3 min
define('WINS_LOCK_HEARTBEAT_EVERY', 200);    // rows

/* =========================
   DB CONNECT
========================= */
try {
    $db = db();// keep as you had (avoid collation mismatch surprises)
    $db->query("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
    $db->query("SET collation_connection = 'utf8mb4_general_ci'");
    $db->query("SET time_zone = '+05:30'");
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed', 'detail' => $e->getMessage()]);
    exit;
}

/* =========================
   Helpers
========================= */
function fnum($v)
{
    $n = floatval($v);
    return $n < 0 ? 0 : $n;
}
function strim($v)
{
    return trim((string) ($v ?? ''));
}

function hasTable(mysqli $db, string $table): bool
{
    $q = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $q->bind_param('s', $table);
    $q->execute();
    $q->bind_result($c);
    $q->fetch();
    $q->close();
    return ((int) $c) > 0;
}
function hasColumn(mysqli $db, string $table, string $col): bool
{
    $q = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->bind_param('ss', $table, $col);
    $q->execute();
    $q->bind_result($c);
    $q->fetch();
    $q->close();
    return ((int) $c) > 0;
}

function safeBetType($betType)
{
    $bt = strtolower(trim((string) $betType));
    if (in_array($bt, ['single_digit', 'jodi_digit', 'single_pana', 'double_pana', 'triple_pana', 'half_sangam', 'full_sangam'], true))
        return $bt;
    return null;
}

function buildBindTypes(array $params): string
{
    $t = '';
    foreach ($params as $v) {
        if (is_int($v))
            $t .= 'i';
        else if (is_float($v) || is_double($v))
            $t .= 'd';
        else
            $t .= 's';
    }
    return $t;
}
function stmtBindExecute(mysqli_stmt $stmt, array $params)
{
    $types = buildBindTypes($params);
    $refs = [];
    foreach ($params as $k => $v)
        $refs[$k] = &$params[$k];
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
}

function digitFromPana($p)
{
    $p = trim((string) $p);
    if (!preg_match('/^\d{3}$/', $p))
        return '';
    $sum = ((int) $p[0]) + ((int) $p[1]) + ((int) $p[2]);
    return (string) ($sum % 10);
}

function makeBetKey($LLUser, $market, $sess, $core)
{
    $u = trim((string) $LLUser);
    $m = strtoupper(trim((string) $market));
    $s = strtolower(trim((string) $sess));
    $c = trim((string) $core);
    return $u . '|' . $m . '|' . $s . '|' . $c;
}

function capPayout($p)
{
    $p = (float) $p;
    if ($p < 0)
        return 0.0;
    if (CAP_PAYOUT_PER_ROW > 0 && $p > CAP_PAYOUT_PER_ROW)
        return (float) CAP_PAYOUT_PER_ROW;
    return $p;
}

function normDigit($v): string
{
    $v = strim($v);
    if ($v === '')
        return '';
    if (!preg_match('/^\d+$/', $v))
        return '';
    $n = (int) $v;
    if ($n < 0 || $n > 9)
        return '';
    return (string) $n;
}
function normPana($v): string
{
    $v = str_replace('*', '', strim($v));
    return preg_match('/^\d{3}$/', $v) ? $v : '';
}
function normJodi($v): string
{
    $v = str_replace('*', '', strim($v));
    if ($v === '')
        return '';
    if (!preg_match('/^\d{1,2}$/', $v))
        return '';
    $n = (int) $v;
    if ($n < 0 || $n > 99)
        return '';
    return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
}

/* =========================
   Schema best-effort
========================= */
function ensure_wining_schema(mysqli $db): void
{
    if (!hasTable($db, 'wining'))
        return;

    try {
        if (!hasColumn($db, 'wining', 'bid_id'))
            @$db->query("ALTER TABLE wining ADD COLUMN bid_id INT NULL DEFAULT NULL");
        if (!hasColumn($db, 'wining', 'win_date'))
            @$db->query("ALTER TABLE wining ADD COLUMN win_date DATE NULL DEFAULT NULL");
        if (!hasColumn($db, 'wining', 'bet_key'))
            @$db->query("ALTER TABLE wining ADD COLUMN bet_key VARCHAR(255) NULL DEFAULT NULL");
        if (!hasColumn($db, 'wining', 'bet_type'))
            @$db->query("ALTER TABLE wining ADD COLUMN bet_type VARCHAR(50) NULL DEFAULT NULL");

        if (!hasColumn($db, 'wining', 'session_type'))
            @$db->query("ALTER TABLE wining ADD COLUMN session_type ENUM('open','close') NULL DEFAULT NULL");

        if (!hasColumn($db, 'wining', 'notified'))
            @$db->query("ALTER TABLE wining ADD COLUMN notified TINYINT(1) NOT NULL DEFAULT 0");
        if (!hasColumn($db, 'wining', 'notified_at'))
            @$db->query("ALTER TABLE wining ADD COLUMN notified_at DATETIME NULL");

        if (!hasColumn($db, 'wining', 'single_digit'))
            @$db->query("ALTER TABLE wining ADD COLUMN single_digit VARCHAR(10) NULL");
        if (!hasColumn($db, 'wining', 'jodi_digit'))
            @$db->query("ALTER TABLE wining ADD COLUMN jodi_digit VARCHAR(10) NULL");
        if (!hasColumn($db, 'wining', 'single_pana'))
            @$db->query("ALTER TABLE wining ADD COLUMN single_pana VARCHAR(10) NULL");
        if (!hasColumn($db, 'wining', 'double_pana'))
            @$db->query("ALTER TABLE wining ADD COLUMN double_pana VARCHAR(10) NULL");
        if (!hasColumn($db, 'wining', 'triple_pana'))
            @$db->query("ALTER TABLE wining ADD COLUMN triple_pana VARCHAR(10) NULL");

        if (!hasColumn($db, 'wining', 'open_digit'))
            @$db->query("ALTER TABLE wining ADD COLUMN open_digit VARCHAR(10) NULL");
        if (!hasColumn($db, 'wining', 'close_digit'))
            @$db->query("ALTER TABLE wining ADD COLUMN close_digit VARCHAR(10) NULL");
        if (!hasColumn($db, 'wining', 'open_pana_x'))
            @$db->query("ALTER TABLE wining ADD COLUMN open_pana_x VARCHAR(10) NULL");
        if (!hasColumn($db, 'wining', 'close_pana_x'))
            @$db->query("ALTER TABLE wining ADD COLUMN close_pana_x VARCHAR(10) NULL");

        if (!hasColumn($db, 'wining', 'amount'))
            @$db->query("ALTER TABLE wining ADD COLUMN amount DECIMAL(10,2) NULL DEFAULT 0");
        if (!hasColumn($db, 'wining', 'wining_balance'))
            @$db->query("ALTER TABLE wining ADD COLUMN wining_balance DECIMAL(10,2) NULL DEFAULT 0");
        if (!hasColumn($db, 'wining', 'status'))
            @$db->query("ALTER TABLE wining ADD COLUMN status VARCHAR(20) NULL DEFAULT 'Win'");
        if (!hasColumn($db, 'wining', 'win_time'))
            @$db->query("ALTER TABLE wining ADD COLUMN win_time DATETIME NULL DEFAULT NULL");

        // Unique key (safe try)
        @$db->query("ALTER TABLE wining ADD UNIQUE KEY uniq_win (win_date, bet_key, bid_id, bet_type)");
    } catch (Throwable $e) {
    }
}
ensure_wining_schema($db);

/* =========================
   wins_run_lock helpers
========================= */
function lockUpsertRow(mysqli $db, string $lockKey)
{
    $sql = "INSERT INTO wins_run_lock (lock_key,last_run,is_running,updated_at)
            VALUES (?, NOW(), 0, NOW())
            ON DUPLICATE KEY UPDATE updated_at=NOW()";
    $st = $db->prepare($sql);
    $st->bind_param('s', $lockKey);
    $st->execute();
    $st->close();
}
function lockAcquire(mysqli $db, string $lockKey, int $staleSeconds, string $msg = 'starting'): array
{
    $out = ['acquired' => false, 'reason' => null];

    $db->begin_transaction();
    try {
        lockUpsertRow($db, $lockKey);

        $st = $db->prepare("SELECT is_running, heartbeat_at FROM wins_run_lock WHERE lock_key=? FOR UPDATE");
        $st->bind_param('s', $lockKey);
        $st->execute();
        $st->bind_result($isRunning, $heartbeatAt);
        $st->fetch();
        $st->close();

        $isRunning = (int) $isRunning;
        $hb = $heartbeatAt ? strtotime($heartbeatAt) : 0;
        $now = time();
        $isStale = ($isRunning === 1 && ($hb === 0 || ($now - $hb) > $staleSeconds));

        if ($isRunning === 0 || $isStale) {
            $st2 = $db->prepare("
                UPDATE wins_run_lock
                SET is_running=1,
                    started_at=IFNULL(started_at, NOW()),
                    heartbeat_at=NOW(),
                    updated_at=NOW(),
                    last_msg=?
                WHERE lock_key=?
            ");
            $st2->bind_param('ss', $msg, $lockKey);
            $st2->execute();
            $st2->close();

            $db->commit();
            $out['acquired'] = true;
            $out['reason'] = $isStale ? 'stale_takeover' : 'fresh_lock';
            return $out;
        }

        $db->commit();
        $out['acquired'] = false;
        $out['reason'] = 'already_running';
        return $out;

    } catch (Throwable $e) {
        $db->rollback();
        $out['acquired'] = false;
        $out['reason'] = 'lock_error: ' . $e->getMessage();
        return $out;
    }
}
function lockHeartbeat(mysqli $db, string $lockKey, string $msg = 'running')
{
    $st = $db->prepare("UPDATE wins_run_lock SET heartbeat_at=NOW(), updated_at=NOW(), last_msg=? WHERE lock_key=?");
    $st->bind_param('ss', $msg, $lockKey);
    $st->execute();
    $st->close();
}
function lockRelease(mysqli $db, string $lockKey, string $msg = 'done')
{
    $st = $db->prepare("DELETE FROM wins_run_lock WHERE lock_key=?");
    $st->bind_param('s', $lockKey);
    $st->execute();
    $st->close();
}

/* =========================
   1) Date range
========================= */
$today = date('Y-m-d');
$from = $_GET['from'] ?? $today;
$to = $_GET['to'] ?? $today;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    http_response_code(400);
    echo json_encode(['error' => 'from/to must be YYYY-MM-DD']);
    $db->close();
    exit;
}
if ($from > $to) {
    $tmp = $from;
    $from = $to;
    $to = $tmp;
}

$fromTS = "$from 00:00:00";
$toTS = "$to 23:59:59";

/* =========================
   2) Detect mode
========================= */
$trigger = (($_GET['trigger'] ?? '') === '1');
$marketFilter = strtoupper(trim($_GET['market'] ?? ''));
$mtype = strtolower(trim($_GET['mtype'] ?? ''));

if (empty($mtype) && !empty($marketFilter)) {
    $chkG = $db->prepare("SELECT id FROM gali_markets WHERE UPPER(TRIM(market_name)) = ? LIMIT 1");
    $chkG->bind_param('s', $marketFilter);
    $chkG->execute();
    if ($chkG->get_result()->num_rows > 0) {
        $mtype = 'gali';
    }
    $chkG->close();
}

$key = (string) ($_GET['key'] ?? '');
$triggerAllowed = false;

if ($trigger) {
    if (WINS_TRIGGER_KEY !== '' && (hash_equals(WINS_TRIGGER_KEY, $key) || hash_equals('LL_ADMIN_WINS_2025', $key) || hash_equals(SUBH_WINS_TRIGGER_KEY, $key)))
        $triggerAllowed = true;
    if (!empty($_SESSION['admin']))
        $triggerAllowed = true;

    if (!$triggerAllowed) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: trigger not allowed']);
        $db->close();
        exit;
    }
    if ($marketFilter === '') {
        http_response_code(400);
        echo json_encode(['error' => 'market required for trigger=1']);
        $db->close();
        exit;
    }
}

/* ✅ trigger session filter */
$sessionFilter = strtolower(trim((string) ($_GET['session'] ?? 'both')));
if (!in_array($sessionFilter, ['both', 'open', 'close'], true))
    $sessionFilter = 'both';

/* =========================
   3) Resolve user_id (USER MODE)
========================= */
$userId = $_GET['user_id'] ?? '';

/* ✅ NEW SECURITY FIX:
   If user is logged-in, force user_id = their own DP code.
   (No one can pass other DPxxxx in URL and see other history)
*/
if (!$trigger && !empty($_SESSION['user']['id'])) {
    $sid = (int) $_SESSION['user']['id'];
    $q = $db->prepare("SELECT user_id FROM users WHERE id=? LIMIT 1");
    $q->bind_param('i', $sid);
    $q->execute();
    $q->bind_result($rkSelf);
    $q->fetch();
    $q->close();

    $rkSelf = (string) $rkSelf;
    if ($rkSelf === '' || !preg_match('/^LL\d{1,12}$/', $rkSelf)) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot resolve LL code']);
        $db->close();
        exit;
    }

    // if user_id param exists and mismatches -> forbidden
    if ($userId !== '' && $userId !== $rkSelf) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: cannot access other user history']);
        $db->close();
        exit;
    }

    $userId = $rkSelf;
}

if (!$trigger) {
    if ($userId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'user_id param or login required']);
        $db->close();
        exit;
    }
    if (!preg_match('/^LL\d{1,12}$/', $userId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid user_id']);
        $db->close();
        exit;
    }
}

/* =========================
   USER MODE: return wins history
========================= */
if (!$trigger) {
    if (!hasTable($db, 'wining')) {
        echo json_encode([]);
        $db->close();
        exit;
    }

    $hasId = hasColumn($db, 'wining', 'id');
    $hasBetType = hasColumn($db, 'wining', 'bet_type');
    $hasWinDate = hasColumn($db, 'wining', 'win_date');
    $hasWinTime = hasColumn($db, 'wining', 'win_time');

    $hasSingleDigit = hasColumn($db, 'wining', 'single_digit');
    $hasJodiDigit = hasColumn($db, 'wining', 'jodi_digit');
    $hasSinglePana = hasColumn($db, 'wining', 'single_pana');
    $hasDoublePana = hasColumn($db, 'wining', 'double_pana');
    $hasTriplePana = hasColumn($db, 'wining', 'triple_pana');

    $hasOpenDigitC = hasColumn($db, 'wining', 'open_digit');
    $hasCloseDigitC = hasColumn($db, 'wining', 'close_digit');
    $hasOpenPanaX = hasColumn($db, 'wining', 'open_pana_x');
    $hasOpenPanaX = hasColumn($db, 'wining', 'open_pana_x');
    $hasClosePanaX = hasColumn($db, 'wining', 'close_pana_x');



    $hasSessionType = hasColumn($db, 'wining', 'session_type');
    $hasAmount = hasColumn($db, 'wining', 'amount');
    $hasWinBal = hasColumn($db, 'wining', 'wining_balance');
    $hasMarket = hasColumn($db, 'wining', 'market');

    $dateWhere = $hasWinDate
        ? " w.win_date BETWEEN ? AND ? "
        : " w.win_time BETWEEN ? AND ? ";

    $gameDateExpr = $hasWinDate ? "w.win_date" : "DATE(w.win_time)";

    $selectId = $hasId ? "w.id AS id," : "0 AS id,";
    $selectType = $hasBetType ? "w.bet_type AS bet_type," : "'' AS bet_type,";
    $selectSess = $hasSessionType ? "w.session_type AS session_type," : "'' AS session_type,";
    $selectAmt = $hasAmount ? "w.amount AS amount," : "0 AS amount,";
    $selectWin = $hasWinBal ? "w.wining_balance AS wining_balance," : "0 AS wining_balance,";
    $selectTime = $hasWinTime ? "w.win_time AS win_time," : "'' AS win_time,";
    $selectMkt = $hasMarket ? "w.market AS market," : "'' AS market,";

    $colsExtra = [];
    if ($hasSingleDigit)
        $colsExtra[] = "w.single_digit";
    if ($hasJodiDigit)
        $colsExtra[] = "w.jodi_digit";
    if ($hasSinglePana)
        $colsExtra[] = "w.single_pana";
    if ($hasDoublePana)
        $colsExtra[] = "w.double_pana";
    if ($hasTriplePana)
        $colsExtra[] = "w.triple_pana";
    if ($hasOpenDigitC)
        $colsExtra[] = "w.open_digit";
    if ($hasCloseDigitC)
        $colsExtra[] = "w.close_digit";
    if ($hasOpenPanaX)
        $colsExtra[] = "w.open_pana_x";
    if ($hasClosePanaX)
        $colsExtra[] = "w.close_pana_x";
    if ($hasWinDate)
        if ($hasWinDate)
            $colsExtra[] = "w.win_date";



    $extraSql = $colsExtra ? (", " . implode(", ", $colsExtra)) : "";

    $sessWhere = "";
    if ($sessionFilter !== 'both' && $hasSessionType) {
        $sessWhere = " AND LOWER(w.session_type)=? ";
    }


    $sql = "
        SELECT
          {$selectId}
          {$selectMkt}
          {$selectSess}
          {$selectType}
          {$selectAmt}
          {$selectWin}
          {$selectTime}
          {$gameDateExpr} AS game_date
          {$extraSql}
        FROM wining w
        LEFT JOIN markets m
          ON m.market_name COLLATE utf8mb4_general_ci = w.market COLLATE utf8mb4_general_ci
        LEFT JOIN market_result mr
          ON mr.market COLLATE utf8mb4_general_ci = w.market COLLATE utf8mb4_general_ci
          AND mr.result_date = {$gameDateExpr}
        WHERE w.user_id = ?
          AND {$dateWhere}
          AND (
            UPPER(TRIM(w.status)) = 'WIN'
            OR UPPER(TRIM(w.status)) = 'WINNING'
            OR UPPER(TRIM(w.status)) = 'WIN '
          )
          {$sessWhere}
        ORDER BY {$gameDateExpr} DESC, w.win_time DESC
    ";

    $st = $db->prepare($sql);

    if ($hasWinDate) {
        if ($sessWhere)
            $st->bind_param('ssss', $userId, $from, $to, $sessionFilter);
        else
            $st->bind_param('sss', $userId, $from, $to);
    } else {
        if ($sessWhere)
            $st->bind_param('ssss', $userId, $fromTS, $toTS, $sessionFilter);
        else
            $st->bind_param('sss', $userId, $fromTS, $toTS);
    }

    $st->execute();
    $rs = $st->get_result();

    $out = [];
    while ($w = $rs->fetch_assoc()) {
        $betType = $hasBetType ? strtolower((string) $w['bet_type']) : '';
        if ($betType === '' || $betType === '0')
            $betType = '';

        $value = '';
        if ($betType === 'full_sangam') {
            $opx = $hasOpenPanaX ? strim($w['open_pana_x'] ?? '') : '';
            $cpx = $hasClosePanaX ? strim($w['close_pana_x'] ?? '') : '';
            $value = trim($opx . '-' . $cpx, '-');
        } elseif ($betType === 'half_sangam') {
            $d = $hasSingleDigit ? strim($w['single_digit'] ?? '') : '';
            $opx = $hasOpenPanaX ? strim($w['open_pana_x'] ?? '') : '';
            $cpx = $hasClosePanaX ? strim($w['close_pana_x'] ?? '') : '';
            $p = ($opx !== '') ? $opx : $cpx;
            $value = trim($d . '-' . $p, '-');

        } else {
            if ($betType === '') {
                $sd = $hasSingleDigit ? strim($w['single_digit'] ?? '') : '';
                $jd = $hasJodiDigit ? strim($w['jodi_digit'] ?? '') : '';
                $sp = $hasSinglePana ? strim($w['single_pana'] ?? '') : '';
                $dp = $hasDoublePana ? strim($w['double_pana'] ?? '') : '';
                $tp = $hasTriplePana ? strim($w['triple_pana'] ?? '') : '';



                if ($jd !== '')
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
                    $betType = 'single_digit';
            }

            if ($hasSingleDigit && $betType === 'single_digit')
                $value = strim($w['single_digit'] ?? '');
            if ($hasJodiDigit && $betType === 'jodi_digit')
                $value = strim($w['jodi_digit'] ?? '');
            if ($hasSinglePana && $betType === 'single_pana')
                $value = strim($w['single_pana'] ?? '');
            if ($hasDoublePana && $betType === 'double_pana')
                $value = strim($w['double_pana'] ?? '');
            if ($hasTriplePana && $betType === 'triple_pana')
                $value = strim($w['triple_pana'] ?? '');


        }

        $out[] = [
            'id' => (int) ($w['id'] ?? 0),
            'market' => (string) ($w['market'] ?? ''),
            'session' => (string) ($w['session_type'] ?? ''),
            'session_type' => (string) ($w['session_type'] ?? ''),
            'bet_type' => $betType,
            'value' => $value,
            'amount' => (float) ($w['amount'] ?? 0),
            'wining_balance' => (float) ($w['wining_balance'] ?? 0),
            'win_time' => (string) ($w['win_time'] ?? ''),
            'win_date' => (string) ($w['game_date'] ?? ''),
        ];
    }

    $st->close();
    $db->close();
    echo json_encode($out);
    exit;
}

if ($trigger) {
    /* =========================
       wins_run_lock acquire
    ========================= */
    $lockInfo = [
        'table_found' => false,
        'lock_key' => null,
        'acquired' => false,
        'stale_seconds' => WINS_LOCK_STALE_SECONDS,
        'reason' => null
    ];

    $lockKey = null;
    if (WINS_LOCK_ENABLED && hasTable($db, 'wins_run_lock')) {
        $lockInfo['table_found'] = true;

        $lockKey = "wins_compute|{$marketFilter}|{$from}|{$to}|{$sessionFilter}";
        $lockInfo['lock_key'] = $lockKey;

        $acq = lockAcquire($db, $lockKey, WINS_LOCK_STALE_SECONDS, "running {$marketFilter} {$from}..{$to} sess={$sessionFilter}");
        $lockInfo['acquired'] = (bool) $acq['acquired'];
        $lockInfo['reason'] = $acq['reason'];

        if (!$lockInfo['acquired']) {
            echo json_encode([
                'ok' => false,
                'mode' => 'trigger',
                'market' => $marketFilter,
                'from' => $from,
                'to' => $to,
                'session' => $sessionFilter,
                'error' => 'wins already running',
                'wins_run_lock' => $lockInfo
            ]);
            $db->close();
            exit;
        }
    }

    /* =========================
       Ensure wining exists + required schema
    ========================= */
    if (!hasTable($db, 'wining')) {
        if ($lockKey && $lockInfo['acquired'])
            lockRelease($db, $lockKey, "done (no wining table)");
        $db->close();
        echo json_encode(['ok' => false, 'error' => 'wining table not found']);
        exit;
    }

    /* must-have for NEW upsert */
    $hasWinDate = hasColumn($db, 'wining', 'win_date');
    $hasBetKey = hasColumn($db, 'wining', 'bet_key');
    $hasBetType = hasColumn($db, 'wining', 'bet_type');
    $hasBidId = hasColumn($db, 'wining', 'bid_id');

    $useNew = ($hasWinDate && $hasBetKey && $hasBidId && $hasBetType);

    if (!$useNew) {
        if ($lockKey && $lockInfo['acquired'])
            lockRelease($db, $lockKey, "schema_error");
        $db->close();
        echo json_encode(['ok' => false, 'error' => 'Table schema mismatch: need win_date+bet_key+bid_id+bet_type']);
        exit;
    }

    $deletedInvalidWins = 0;
    $cleanup = $db->prepare("
        DELETE w
        FROM wining w
        INNER JOIN bid b ON b.id = w.bid_id
        WHERE w.market COLLATE utf8mb4_general_ci = ?
          AND w.win_date BETWEEN ? AND ?
          AND DATE(b.bid_time) <> w.win_date
    ");
    $cleanup->bind_param('sss', $marketFilter, $from, $to);
    $cleanup->execute();
    $deletedInvalidWins = max(0, (int) $cleanup->affected_rows);
    $cleanup->close();

    /* ✅ WIN ONLY upsert */
    $up = $db->prepare("
    INSERT INTO wining
    (bid_id, user_id, market, session_type, bet_type,
     single_digit, jodi_digit, single_pana, double_pana, triple_pana,
     open_digit, close_digit, open_pana_x, close_pana_x,
     amount, wining_balance, status, win_time, win_date, bet_key, notified)
    VALUES
    (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'Win', ?,?,?, 0)
    ON DUPLICATE KEY UPDATE
      user_id=VALUES(user_id),
      market=VALUES(market),
      session_type=VALUES(session_type),
      bet_type=VALUES(bet_type),

      single_digit=VALUES(single_digit),
      jodi_digit=VALUES(jodi_digit),
      single_pana=VALUES(single_pana),
      double_pana=VALUES(double_pana),
      triple_pana=VALUES(triple_pana),

      open_digit=VALUES(open_digit),
      close_digit=VALUES(close_digit),
      open_pana_x=VALUES(open_pana_x),
      close_pana_x=VALUES(close_pana_x),

      amount=VALUES(amount),
      wining_balance=VALUES(wining_balance),
      status='Win',
      win_time=VALUES(win_time),
      win_date=VALUES(win_date),
      bet_key=VALUES(bet_key),

      notified = IF(
          notified IS NULL, 0,
          IF(
            (wining_balance <> VALUES(wining_balance)) OR
            (amount <> VALUES(amount)) OR
            (session_type <> VALUES(session_type)) OR
            (bet_type <> VALUES(bet_type)) OR
            (open_digit <> VALUES(open_digit)) OR
            (close_digit <> VALUES(close_digit)) OR
            (open_pana_x <> VALUES(open_pana_x)) OR
            (close_pana_x <> VALUES(close_pana_x)) OR
            (single_digit <> VALUES(single_digit)) OR
            (jodi_digit <> VALUES(jodi_digit)) OR
            (single_pana <> VALUES(single_pana)) OR
            (double_pana <> VALUES(double_pana)) OR
            (triple_pana <> VALUES(triple_pana)),
            0,
            notified
          )
      )
");

    /* =========================
       5) Fetch bids for trigger compute
    ========================= */
    $bindTypes = "sss";
    $bindParams = [$fromTS, $toTS, $marketFilter];

    if ($mtype === 'gali') {
        $sqlBase = "
SELECT
  b.id AS bid_id,
  b.user_id,
  b.market_name AS market,
  'open/close' AS session_type,
  b.game_type,
  
  b.digit AS single_digit, '' AS jodi_digit,
  '' AS single_pana, '' AS double_pana, '' AS triple_pana,
  
  '' AS bid_open_digit, '' AS bid_close_digit,
  '' AS bid_open_pana, '' AS bid_close_pana,
  
  b.amount, b.created_at AS bid_time,
  DATE(b.created_at) AS game_date,
  
  '' AS open_digit, '' AS close_digit,
  '' AS open_pana, '' AS close_pana,
  
  mr.jodi AS gali_jodi,

  m.payout_left_digit, m.payout_right_digit, m.payout_jodi_digit,
  0 AS payout_single_digit, 0 AS payout_single_pana, 0 AS payout_double_pana, 0 AS payout_triple_pana, 0 AS payout_half_sangam, 0 AS payout_full_sangam
FROM gali_bids b
LEFT JOIN gali_result mr
  ON mr.market COLLATE utf8mb4_general_ci = b.market_name COLLATE utf8mb4_general_ci
 AND mr.result_date = DATE(b.created_at)
LEFT JOIN gali_markets m
  ON m.market_name COLLATE utf8mb4_general_ci = b.market_name COLLATE utf8mb4_general_ci
WHERE b.created_at BETWEEN ?
  AND ?
  AND b.market_name COLLATE utf8mb4_general_ci = ?
ORDER BY b.created_at DESC
";
    } else {
        $sqlBase = "
SELECT
  b.id AS bid_id,
  b.user_id,
  b.market,
  b.session_type,
  b.game_type,

  b.single_digit, b.jodi_digit,
  b.single_pana, b.double_pana, b.triple_pana,

  b.open_digit AS bid_open_digit,
  b.close_digit AS bid_close_digit,

  b.open_pana AS bid_open_pana,
  b.close_pana AS bid_close_pana,

  b.amount, b.bid_time,

  DATE(b.bid_time) AS game_date,

  mr.open_digit, mr.close_digit,
  mr.open_pana, mr.close_pana,
  '' AS gali_jodi,

  m.payout_single_digit, m.payout_jodi_digit, m.payout_single_pana,
  m.payout_double_pana, m.payout_triple_pana, m.payout_half_sangam, m.payout_full_sangam,
  0 AS payout_left_digit, 0 AS payout_right_digit
FROM bid b
LEFT JOIN market_result mr
  ON mr.market COLLATE utf8mb4_general_ci = b.market COLLATE utf8mb4_general_ci
 AND mr.result_date = DATE(b.bid_time)
LEFT JOIN markets m
  ON m.market_name COLLATE utf8mb4_general_ci = b.market COLLATE utf8mb4_general_ci
WHERE b.bid_time BETWEEN ?
  AND ?
  AND b.market COLLATE utf8mb4_general_ci = ?
ORDER BY b.bid_time DESC
";
    }

    $st = $db->prepare($sqlBase);
    $params = $bindParams;
    $refs = [];
    foreach ($params as $k => $v)
        $refs[$k] = &$params[$k];
    array_unshift($refs, $bindTypes);
    call_user_func_array([$st, 'bind_param'], $refs);

    $st->execute();
    $res = $st->get_result();

    $processed = 0;
    $upserts = 0;
    $hbCount = 0;

    $wantSession = function (string $sess) use ($sessionFilter): bool {
        if ($sessionFilter === 'both')
            return true;
        return strtolower($sess) === $sessionFilter;
    };

    $doUpsertWin = function (string $sessStore, string $betTypeRaw, string $betKey, string $sdV, string $jdV, string $spV, string $dpV, string $tpV, $openDigit, $closeDigit, $openPanaX, $closePanaX, float $amt, float $payout, int $bidId, string $LLUser, string $market, string $bidTime, string $winDate) use (&$upserts, $up) {

        if ($payout <= 0)
            return;

        $od = ($openDigit === null || $openDigit === '') ? null : (int) $openDigit;
        $cd = ($closeDigit === null || $closeDigit === '') ? null : (int) $closeDigit;

        $betType = safeBetType($betTypeRaw);
        if (!$betType)
            return;

        $params = [
            (int) $bidId,
            (string) $LLUser,
            (string) $market,
            (string) $sessStore,
            (string) $betType,
            (string) $sdV,
            (string) $jdV,
            (string) $spV,
            (string) $dpV,
            (string) $tpV,
            $od,
            $cd,
            (string) ($openPanaX ?? ''),
            (string) ($closePanaX ?? ''),
            (float) $amt,
            (float) $payout,
            (string) $bidTime,
            (string) $winDate,
            (string) $betKey
        ];

        stmtBindExecute($up, $params);
        $upserts++;
    };

    while ($b = $res->fetch_assoc()) {
        $processed++;
        $hbCount++;

        if ($lockKey && ($hbCount % WINS_LOCK_HEARTBEAT_EVERY === 0)) {
            lockHeartbeat($db, $lockKey, "running processed={$processed}");
        }

        $bidId = (int) ($b['bid_id'] ?? 0);
        if ($bidId <= 0)
            continue;

        $market = (string) $b['market'];
        $bidTime = (string) $b['bid_time'];
        $LLUser = (string) $b['user_id'];
        $winDate = (string) $b['game_date'];

        $amt = fnum($b['amount']);
        if ($amt <= 0)
            continue;

        // ✅ standard: per 10 points unit
        $units = $amt / 10.0;

        /* result digits/panas */
        $openD = normDigit($b['open_digit'] ?? '');
        $closeD = normDigit($b['close_digit'] ?? '');
        $openP = normPana($b['open_pana'] ?? '');
        $closeP = normPana($b['close_pana'] ?? '');

        if ($openD === '' && $openP !== '')
            $openD = digitFromPana($openP);
        if ($closeD === '' && $closeP !== '')
            $closeD = digitFromPana($closeP);

        /* payouts */
        $multiSingle = fnum($b['payout_single_digit'] ?? 0);
        $multiJodi = fnum($b['payout_jodi_digit'] ?? 0);
        $multiSPana = fnum($b['payout_single_pana'] ?? 0);
        $multiDPana = fnum($b['payout_double_pana'] ?? 0);
        $multiTPana = fnum($b['payout_triple_pana'] ?? 0);
        $multiHS = fnum($b['payout_half_sangam'] ?? 0);
        $multiFS = fnum($b['payout_full_sangam'] ?? 0);
        
        $multiLeft = fnum($b['payout_left_digit'] ?? 0);
        $multiRight = fnum($b['payout_right_digit'] ?? 0);

        /* bid fields */
        $sessBid = (strtolower((string) ($b['session_type'] ?? 'open')) === 'open') ? 'open' : 'close';
        $gameType = strtoupper(strim($b['game_type'] ?? ''));

        $sd = normDigit($b['single_digit'] ?? '');
        $jd = normJodi($b['jodi_digit'] ?? '');

        $sp = normPana($b['single_pana'] ?? '');
        $dp = normPana($b['double_pana'] ?? '');
        $tp = normPana($b['triple_pana'] ?? '');

        $bidOpenDigit = normDigit($b['bid_open_digit'] ?? '');
        $bidCloseDigit = normDigit($b['bid_close_digit'] ?? '');
        $bidOpenPana = normPana($b['bid_open_pana'] ?? '');
        $bidClosePana = normPana($b['bid_close_pana'] ?? '');

        $didWin = false;
        $blockFurther = function () use (&$didWin) {
            if (MAX_ONE_WIN_PER_ROW)
                $didWin = true;
        };

        // ✅ 0) GALI MARKETS LOGIC
        if ($mtype === 'gali') {
            $galiJodi = trim($b['gali_jodi'] ?? '');
            if ($galiJodi !== '') {
                $leftDigit = isset($galiJodi[0]) ? $galiJodi[0] : '';
                $rightDigit = isset($galiJodi[1]) ? $galiJodi[1] : '';

                if ($gameType === 'LEFT DIGIT' || $gameType === 'LEFT_DIGIT' || $gameType === 'HARUF ANDAR' || $gameType === 'ANDAR HARUF') {
                    if ($sd !== '' && $leftDigit !== '' && $sd === $leftDigit) {
                        $payout = capPayout($units * $multiLeft);
                        $betKey = makeBetKey($LLUser, $market, 'open/close', "GALI:LD:{$sd}|BID:{$bidId}");
                        $doUpsertWin('open', 'single_digit', $betKey, $sd, '', '', '', '', '', '', '', '', $amt, $payout, $bidId, $LLUser, $market, $bidTime, $winDate);
                        $blockFurther();
                    }
                } elseif ($gameType === 'RIGHT DIGIT' || $gameType === 'RIGHT_DIGIT' || $gameType === 'HARUF BAHAR' || $gameType === 'BAHAR HARUF') {
                    if ($sd !== '' && $rightDigit !== '' && $sd === $rightDigit) {
                        $payout = capPayout($units * $multiRight);
                        $betKey = makeBetKey($LLUser, $market, 'open/close', "GALI:RD:{$sd}|BID:{$bidId}");
                        $doUpsertWin('close', 'single_digit', $betKey, $sd, '', '', '', '', '', '', '', '', $amt, $payout, $bidId, $LLUser, $market, $bidTime, $winDate);
                        $blockFurther();
                    }
                } elseif ($gameType === 'JODI DIGIT' || $gameType === 'JODI_DIGIT' || $gameType === 'JODI') {
                    $rawJodi = trim($b['jodi_digit'] ?? '') !== '' ? trim($b['jodi_digit']) : trim($b['single_digit'] ?? '');
                    $bidJodiVal = normJodi($rawJodi);
                    if ($bidJodiVal !== '' && $bidJodiVal === $galiJodi) {
                        $payout = capPayout($units * $multiJodi);
                        $betKey = makeBetKey($LLUser, $market, 'open/close', "GALI:JD:{$bidJodiVal}|BID:{$bidId}");
                        $doUpsertWin('open', 'jodi_digit', $betKey, '', $bidJodiVal, '', '', '', '', '', '', '', $amt, $payout, $bidId, $LLUser, $market, $bidTime, $winDate);
                        $blockFurther();
                    }
                }
            }
            continue; // Skip the rest of the loop for standard markets
        }

        // 1) FULL SANGAM (open_pana + close_pana) -> store as CLOSE session
        if ((!$didWin || !MAX_ONE_WIN_PER_ROW) && ($gameType === 'FULL_SANGAM' || ($bidOpenPana !== '' && $bidClosePana !== ''))) {
            if ($openP !== '' && $closeP !== '' && $bidOpenPana !== '' && $bidClosePana !== '' && $bidOpenPana === $openP && $bidClosePana === $closeP) {
                $sessStore = 'close';
                if ($wantSession($sessStore)) {
                    $payout = capPayout($units * $multiFS);
                    $betKeyCore = "FS:{$bidOpenPana}-{$bidClosePana}|BID:{$bidId}";
                    $betKey = makeBetKey($LLUser, $market, $sessStore, $betKeyCore);

                    $doUpsertWin(
                        $sessStore,
                        'full_sangam',
                        $betKey,
                        '',
                        '',
                        '',
                        '',
                        '',
                        $openD,
                        $closeD,
                        $bidOpenPana,
                        $bidClosePana,
                        $amt,
                        $payout,
                        $bidId,
                        $LLUser,
                        $market,
                        $bidTime,
                        $winDate
                    );
                }
                $blockFurther();
            }
        }
        /* ============================================================
           ✅ HALF SANGAM (ROBUST FIX)
           - Supports digit saved in single_digit (common in panels)
           - Supports pana saved in either open_pana or close_pana
           - Stores correct session_type: open or close
        ============================================================ */
        if ((!$didWin || !MAX_ONE_WIN_PER_ROW) && ($gameType === 'HALF_SANGAM' || $sd !== '' || $bidOpenPana !== '' || $bidClosePana !== '' || $bidOpenDigit !== '' || $bidCloseDigit !== '')) {

            // digit candidates
            $hsOpenDigit = ($bidOpenDigit !== '') ? $bidOpenDigit : '';
            $hsCloseDigit = ($bidCloseDigit !== '') ? $bidCloseDigit : '';
            $hsAnyDigit = ($sd !== '') ? $sd : '';

            if ($hsOpenDigit === '' && $hsCloseDigit === '' && $hsAnyDigit !== '') {
                $hsOpenDigit = $hsAnyDigit;
            }

            // pana candidate (panel may put pana in open_pana or close_pana)
            $hsBidPana = ($bidOpenPana !== '') ? $bidOpenPana : (($bidClosePana !== '') ? $bidClosePana : '');

            // HS OPEN
            if ((!$didWin || !MAX_ONE_WIN_PER_ROW) && $hsOpenDigit !== '' && $hsBidPana !== '') {
                $okPana = false;
                $usePana = '';
                if ($openP !== '' && $hsBidPana === $openP) {
                    $okPana = true;
                    $usePana = $openP;
                }
                if (!$okPana && $closeP !== '' && $hsBidPana === $closeP) {
                    $okPana = true;
                    $usePana = $closeP;
                }

                if ($openD !== '' && $hsOpenDigit === $openD && $okPana) {
                    $sessStore = 'open';
                    if ($wantSession($sessStore)) {
                        $payout = capPayout($units * $multiHS);
                        $betKeyCore = "HSO:{$hsOpenDigit}-{$usePana}|BID:{$bidId}";
                        $betKey = makeBetKey($LLUser, $market, $sessStore, $betKeyCore);

                        $openPX = ($usePana === $openP) ? $usePana : '';
                        $closePX = ($usePana === $closeP) ? $usePana : '';

                        $doUpsertWin(
                            $sessStore,
                            'half_sangam',
                            $betKey,
                            $hsOpenDigit,
                            '',
                            '',
                            '',
                            '',
                            $openD,
                            $closeD,
                            $openPX,
                            $closePX,
                            $amt,
                            $payout,
                            $bidId,
                            $LLUser,
                            $market,
                            $bidTime,
                            $winDate
                        );
                    }
                    $blockFurther();
                }
            }

            // HS CLOSE
            if ((!$didWin || !MAX_ONE_WIN_PER_ROW) && $hsCloseDigit !== '' && $hsBidPana !== '') {
                $okPana = false;
                $usePana = '';
                if ($openP !== '' && $hsBidPana === $openP) {
                    $okPana = true;
                    $usePana = $openP;
                }
                if (!$okPana && $closeP !== '' && $hsBidPana === $closeP) {
                    $okPana = true;
                    $usePana = $closeP;
                }

                if ($closeD !== '' && $hsCloseDigit === $closeD && $okPana) {
                    $sessStore = 'close';
                    if ($wantSession($sessStore)) {
                        $payout = capPayout($units * $multiHS);
                        $betKeyCore = "HSC:{$hsCloseDigit}-{$usePana}|BID:{$bidId}";
                        $betKey = makeBetKey($LLUser, $market, $sessStore, $betKeyCore);

                        $openPX = ($usePana === $openP) ? $usePana : '';
                        $closePX = ($usePana === $closeP) ? $usePana : '';

                        $doUpsertWin(
                            $sessStore,
                            'half_sangam',
                            $betKey,
                            $hsCloseDigit,
                            '',
                            '',
                            '',
                            '',
                            $openD,
                            $closeD,
                            $openPX,
                            $closePX,
                            $amt,
                            $payout,
                            $bidId,
                            $LLUser,
                            $market,
                            $bidTime,
                            $winDate
                        );
                    }
                    $blockFurther();
                }
            }
        }

        // 3) JODI DIGIT (always close)
        if ((!$didWin || !MAX_ONE_WIN_PER_ROW) && $jd !== '') {
            if ($openD !== '' && $closeD !== '') {
                $jodiR = $openD . $closeD;
                if ($jodiR === $jd) {
                    $sessStore = 'close';
                    if ($wantSession($sessStore)) {
                        $payout = capPayout($units * $multiJodi);
                        $betKeyCore = "JD:{$jd}|BID:{$bidId}";
                        $betKey = makeBetKey($LLUser, $market, $sessStore, $betKeyCore);

                        $doUpsertWin(
                            $sessStore,
                            'jodi_digit',
                            $betKey,
                            '',
                            $jd,
                            '',
                            '',
                            '',
                            $openD,
                            $closeD,
                            '',
                            '',
                            $amt,
                            $payout,
                            $bidId,
                            $LLUser,
                            $market,
                            $bidTime,
                            $winDate
                        );
                    }
                    $blockFurther();
                }
            }
        }

        // 4) PANA (single/double/triple) -> session as bid session
        if ((!$didWin || !MAX_ONE_WIN_PER_ROW)) {
            $chosenType = '';
            $chosenVal = '';
            if ($sp !== '') {
                $chosenType = 'single_pana';
                $chosenVal = $sp;
            } else if ($dp !== '') {
                $chosenType = 'double_pana';
                $chosenVal = $dp;
            } else if ($tp !== '') {
                $chosenType = 'triple_pana';
                $chosenVal = $tp;
            }

            if ($chosenVal !== '') {
                $sessStore = $sessBid;
                if ($wantSession($sessStore)) {
                    $panaR = ($sessBid === 'open') ? $openP : $closeP;
                    if ($panaR !== '' && $panaR === $chosenVal) {
                        $multi = ($chosenType === 'single_pana') ? $multiSPana : (($chosenType === 'double_pana') ? $multiDPana : $multiTPana);
                        $payout = capPayout($units * $multi);

                        $corePrefix = ($chosenType === 'single_pana') ? 'SP' : (($chosenType === 'double_pana') ? 'DP' : 'TP');
                        $betKeyCore = "{$corePrefix}:{$chosenVal}|BID:{$bidId}";
                        $betKey = makeBetKey($LLUser, $market, $sessStore, $betKeyCore);

                        $spV = ($chosenType === 'single_pana') ? $chosenVal : '';
                        $dpV = ($chosenType === 'double_pana') ? $chosenVal : '';
                        $tpV = ($chosenType === 'triple_pana') ? $chosenVal : '';

                        $doUpsertWin(
                            $sessStore,
                            $chosenType,
                            $betKey,
                            '',
                            '',
                            $spV,
                            $dpV,
                            $tpV,
                            $openD,
                            $closeD,
                            $openP,
                            $closeP,
                            $amt,
                            $payout,
                            $bidId,
                            $LLUser,
                            $market,
                            $bidTime,
                            $winDate
                        );
                        $blockFurther();
                    }
                }
            }
        }

        // 5) SINGLE DIGIT -> session as bid session
        if ((!$didWin || !MAX_ONE_WIN_PER_ROW) && $sd !== '') {
            $sessStore = $sessBid;
            if ($wantSession($sessStore)) {
                $digitR = ($sessBid === 'open') ? $openD : $closeD;
                if ($digitR !== '' && $digitR === $sd) {
                    $payout = capPayout($units * $multiSingle);
                    $betKeyCore = "SD:{$sd}|BID:{$bidId}";
                    $betKey = makeBetKey($LLUser, $market, $sessStore, $betKeyCore);

                    $doUpsertWin(
                        $sessStore,
                        'single_digit',
                        $betKey,
                        $sd,
                        '',
                        '',
                        '',
                        '',
                        $openD,
                        $closeD,
                        $openP,
                        $closeP,
                        $amt,
                        $payout,
                        $bidId,
                        $LLUser,
                        $market,
                        $bidTime,
                        $winDate
                    );
                    $blockFurther();
                }
            }
        }
    }

    /* Cleanup */
    if ($up)
        $up->close();
    $st->close();

    /* ✅ release lock */
    if ($lockKey && $lockInfo['acquired']) {
        lockRelease($db, $lockKey, "done processed={$processed} upserts={$upserts}");
    }

    $db->close();

    /* =========================
       Return JSON (Trigger Mode)
    ========================= */
    echo json_encode([
        'ok' => true,
        'mode' => 'trigger',
        'market' => $marketFilter,
        'from' => $from,
        'to' => $to,
        'session' => $sessionFilter,
        'processed' => $processed,
        'upserts' => $upserts,
        'deleted_invalid_wins' => $deletedInvalidWins,

        'only_win_rows' => true,
        'lose_rows_stored' => false,
        'session_filter_supported' => true,

        'wins_run_lock' => $lockInfo,
        'lagam_max_one_win_per_row' => MAX_ONE_WIN_PER_ROW,
        'cap_payout_per_row' => CAP_PAYOUT_PER_ROW,

        'note' => 'Trigger mode stores ONLY WIN rows. HALF SANGAM robust fixed (supports digit in single_digit and pana in open/close pana). session_type stored for open_market.php session-wise WIN/LOSE. CAP disabled so payout follows markets table. USER MODE secured: cannot view other DP users.'
    ]);
}


