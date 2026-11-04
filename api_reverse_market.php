<?php
require_once 'includes/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

date_default_timezone_set('Asia/Kolkata');

/* ================== CONFIG ================== */

/* Must match open_market.php */
define('REVERSE_TRIGGER_KEY', 'LL_ADMIN_REVERSE_2026');

/* ================== HELPERS ================== */
function ll_json_exit(array $out, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}
function ll_money($v): float
{
    return round((float) $v, 2);
}
function ll_upper($s): string
{
    return strtoupper(trim((string) $s));
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

/* Same as open_market.php */
function ensureReverseLogTable(mysqli $db): void
{
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
}

/* ================== METHOD + BODY ================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ll_json_exit(['ok' => false, 'message' => 'POST required'], 405);
}

$raw = file_get_contents('php://input');
if (!$raw)
    ll_json_exit(['ok' => false, 'message' => 'empty body'], 400);

$payload = json_decode($raw, true);
if (!is_array($payload))
    ll_json_exit(['ok' => false, 'message' => 'invalid json'], 400);

/* ================== AUTH ================== */
$key = (string) ($payload['key'] ?? '');
if ($key !== REVERSE_TRIGGER_KEY) {
    ll_json_exit(['ok' => false, 'message' => 'unauthorized'], 401);
}

/* ================== VALIDATE INPUT ================== */
$action_ref = trim((string) ($payload['action_ref'] ?? ''));
$admin_id = trim((string) ($payload['admin_id'] ?? 'ADMIN'));
$market = ll_upper($payload['market'] ?? '');
$result_date = trim((string) ($payload['result_date'] ?? ''));
$scope = ll_upper($payload['delete_scope'] ?? 'FULL');
$rows = $payload['rows'] ?? [];

if ($action_ref === '' || strlen($action_ref) > 64)
    ll_json_exit(['ok' => false, 'message' => 'invalid action_ref'], 422);
if ($market === '' || strlen($market) > 80)
    ll_json_exit(['ok' => false, 'message' => 'invalid market'], 422);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $result_date))
    ll_json_exit(['ok' => false, 'message' => 'invalid result_date'], 422);
if (!in_array($scope, ['OPEN', 'CLOSE', 'FULL'], true))
    $scope = 'FULL';
if (!is_array($rows) || empty($rows))
    ll_json_exit(['ok' => false, 'message' => 'rows required'], 422);

/* ================== DB CONNECT ================== */
try {
    $db = db();
    $db->query("SET time_zone = '+05:30'");
} catch (Throwable $e) {
    ll_json_exit(['ok' => false, 'message' => 'db error', 'error' => $e->getMessage()], 500);
}

try {
    ensureReverseLogTable($db);

    // Basic required columns check (in case someone changed schema)
    $needCols = ['action_ref', 'admin_id', 'market', 'result_date', 'delete_scope', 'user_id', 'bid_refund_amount', 'win_reverse_amount', 'bids_deleted', 'wins_deleted', 'details_json'];
    foreach ($needCols as $c) {
        if (!hasColumn($db, 'market_reverse_log', $c)) {
            ll_json_exit(['ok' => false, 'message' => "market_reverse_log missing column: $c"], 500);
        }
    }

    $db->begin_transaction();

    $ins = $db->prepare("
        INSERT INTO market_reverse_log
        (action_ref, admin_id, market, result_date, delete_scope, user_id,
         bid_refund_amount, win_reverse_amount, bids_deleted, wins_deleted, details_json)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");

    $savedUsers = 0;
    $sumRefund = 0.0;
    $sumReverse = 0.0;

    foreach ($rows as $r) {
        if (!is_array($r))
            continue;

        $uid = ll_upper($r['user_id'] ?? '');
        if ($uid === '')
            continue;

        $bid_refund = ll_money($r['bid_refund_amount'] ?? 0);
        $win_rev = ll_money($r['win_reverse_amount'] ?? 0);
        $bids_del = (int) ($r['bids_deleted'] ?? 0);
        $wins_del = (int) ($r['wins_deleted'] ?? 0);

        // details: keep as json string, can be huge
        $details_json = null;
        if (array_key_exists('details', $r)) {
            $details_json = json_encode($r['details'], JSON_UNESCAPED_UNICODE);
            // Avoid super huge payload crash (optional cap)
            if (is_string($details_json) && strlen($details_json) > 2000000) {
                $details_json = json_encode(['note' => 'details too large; truncated'], JSON_UNESCAPED_UNICODE);
            }
        }

        $ins->bind_param(
            'ssssssddiis',
            $action_ref,
            $admin_id,
            $market,
            $result_date,
            $scope,
            $uid,
            $bid_refund,
            $win_rev,
            $bids_del,
            $wins_del,
            $details_json
        );
        $ins->execute();
        $savedUsers++;

        $sumRefund += $bid_refund;
        $sumReverse += $win_rev;
    }

    $ins->close();
    $db->commit();

    ll_json_exit([
        'ok' => true,
        'message' => 'saved',
        'action_ref' => $action_ref,
        'stats' => [
            'users' => $savedUsers,
            'refund_total' => ll_money($sumRefund),
            'reverse_total' => ll_money($sumReverse),
            'market' => $market,
            'result_date' => $result_date,
            'delete_scope' => $scope
        ]
    ], 200);

} catch (Throwable $e) {
    try {
        $db->rollback();
    } catch (Throwable $x) {
    }
    ll_json_exit(['ok' => false, 'message' => 'exception', 'error' => $e->getMessage()], 500);
}

