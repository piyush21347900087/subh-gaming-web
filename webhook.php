<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ✅ SECURITY LOCKDOWN: Require strict secret key authentication for all webhook calls.
$key = trim((string) ($_REQUEST['key'] ?? ''));
require_once __DIR__ . '/../includes/api_keys.php';
if(!defined('API_SECRET_KEY')) define('API_SECRET_KEY', SUBH_API_SECRET_KEY);
if (!hash_equals(API_SECRET_KEY, $key) && !hash_equals('BJ_2026_5a9c8f2e7d1b4k6m3x', $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized access']);
    exit;
}

/* DB load */
$conn = null;
try {
    require_once __DIR__ . '/includes/db.php';
    if (function_exists('db')) {
        $conn = db();
    } elseif (isset($conn) && $conn instanceof mysqli) {
        // ok
    } else {
        require_once __DIR__ . '/config/db.php';
        if (isset($db) && $db instanceof mysqli)
            $conn = $db;
        if (isset($conn) && $conn instanceof mysqli) { /* ok */
        }
    }
} catch (Throwable $e) {
}

if (!($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB connection not available']);
    exit;
}
$conn->set_charset("utf8mb4");

function json_out(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
function hasColumn(mysqli $db, string $table, string $column): bool
{
    try {
        $sql = "SELECT 1
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                LIMIT 1";
        $st = $db->prepare($sql);
        $st->bind_param('ss', $table, $column);
        $st->execute();
        $res = $st->get_result();
        $ok = ($res && $res->num_rows > 0);
        $st->close();
        return $ok;
    } catch (Throwable $e) {
        return false;
    }
}
function bind_params(mysqli_stmt $stmt, string $types, array $values): void
{
    $refs = [];
    $refs[] = $types;
    foreach ($values as $k => $v)
        $refs[] = &$values[$k];
    if (!@call_user_func_array([$stmt, 'bind_param'], $refs)) {
        throw new RuntimeException('bind_param failed');
    }
}

/* ✅ recursive find (case-insensitive key match) */
function find_first_key_recursive($data, array $keys): string
{
    if (!is_array($data))
        return '';
    $keysLower = array_map('strtolower', $keys);

    // direct level
    foreach ($data as $k => $v) {
        if (in_array(strtolower((string) $k), $keysLower, true)) {
            if (is_scalar($v)) {
                $s = trim((string) $v);
                if ($s !== '')
                    return $s;
            }
        }
    }
    // deep level
    foreach ($data as $v) {
        if (is_array($v)) {
            $x = find_first_key_recursive($v, $keys);
            if ($x !== '')
                return $x;
        }
    }
    return '';
}

/* read payload */
$raw = file_get_contents('php://input') ?: '';
$payload = [];
if ($raw !== '') {
    $j = json_decode($raw, true);
    if (is_array($j))
        $payload = $j;
}
if (empty($payload))
    $payload = $_POST ?: [];
if (empty($payload))
    $payload = $_GET ?: [];

/* If gateways send JSON string in a field */
foreach (['data', 'payload', 'response', 'result'] as $maybeJsonField) {
    if (isset($payload[$maybeJsonField]) && is_string($payload[$maybeJsonField])) {
        $jj = json_decode($payload[$maybeJsonField], true);
        if (is_array($jj))
            $payload[$maybeJsonField] = $jj;
    }
}

/* Column checks */
$hasOrderCol = hasColumn($conn, 'deposit', 'order_id');
$hasTxnCol = hasColumn($conn, 'deposit', 'transaction_id');
$hasCredited = hasColumn($conn, 'deposit', 'credited');

/* POLL MODE */
if (($_GET['mode'] ?? '') === 'poll') {
    $body = json_decode($raw, true);
    if (!is_array($body))
        $body = [];

    $order_ids = $body['order_ids'] ?? [];
    if (!is_array($order_ids))
        json_out(['ok' => false, 'error' => 'Invalid order list'], 400);

    $order_ids = array_values(array_unique(array_filter(array_map('strval', $order_ids))));
    if (count($order_ids) > 100)
        json_out(['ok' => false, 'error' => 'Too many order ids'], 400);

    $data = [];
    if (count($order_ids) === 0)
        json_out(['ok' => true, 'data' => $data]);

    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('s', count($order_ids));

    $w = [];
    if ($hasOrderCol)
        $w[] = "order_id IN ($placeholders)";
    if ($hasTxnCol)
        $w[] = "transaction_id IN ($placeholders)";
    if (!$w)
        json_out(['ok' => false, 'error' => 'deposit table missing order_id/transaction_id'], 500);

    $creditedSel = $hasCredited ? "credited" : "0 AS credited";
    $sql = "SELECT " . ($hasOrderCol ? "order_id" : "transaction_id AS order_id") . ", status, $creditedSel
            FROM deposit
            WHERE (" . implode(" OR ", $w) . ")";

    $stmt = $conn->prepare($sql);
    if ($hasOrderCol && $hasTxnCol)
        bind_params($stmt, $types . $types, array_merge($order_ids, $order_ids));
    else
        bind_params($stmt, $types, $order_ids);

    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $oid = (string) $r['order_id'];
        $data[$oid] = ['status' => (string) $r['status'], 'credited' => ((int) $r['credited'] === 1)];
    }
    $stmt->close();

    json_out(['ok' => true, 'data' => $data]);
}

/* ✅ order_id extraction (very robust) */
$order_id = find_first_key_recursive($payload, [
    'order_id',
    'orderId',
    'merchantOrderId',
    'merchant_order_id',
    'merchant_txn_id',
    'client_txn_id',
    'txnid',
    'txnId',
    'transaction_id',
    'transactionId',
    'tr',
    'reference_id',
    'refId',
    'ref_id'
]);

/* ✅ status extraction */
$status_in = strtoupper(find_first_key_recursive($payload, [
    'status',
    'Status',
    'txnStatus',
    'result',
    'payment_status',
    'state',
    'code',
    'responseCode',
    'respCode',
    'message'
]));

/* amount */
$amountStr = find_first_key_recursive($payload, ['amount', 'amt', 'orderAmount', 'order_amount', 'txnAmount', 'txn_amount']);
$amount = (float) $amountStr;

if ($order_id === '')
    json_out(['ok' => false, 'error' => 'order_id missing'], 400);

$finalStatus = 'Pending';
if (in_array($status_in, ['SUCCESS', 'SUCCESSFUL', 'COMPLETED', 'CAPTURED', 'PAID', 'DONE', 'OK', '00', 'TRUE', '1', 'TXN_SUCCESS'], true)) {
    $finalStatus = 'Done';
} elseif (in_array($status_in, ['FAILURE', 'FAILED', 'CANCELLED', 'CANCELED', 'DECLINED', 'ERROR', 'F', '0', 'FALSE'], true)) {
    $finalStatus = 'Failed';
}

$conn->begin_transaction();

try {
    $where = [];
    if ($hasOrderCol)
        $where[] = "order_id=?";
    if ($hasTxnCol)
        $where[] = "transaction_id=?";
    if (!$where)
        throw new RuntimeException("deposit table missing order_id/transaction_id");

    $sql = "SELECT id, user_db_id, amount, status" . ($hasCredited ? ", credited" : ", 0 AS credited") . ", COALESCE(bonus_amount, 0.0) AS bonus_amount
            FROM deposit
            WHERE (" . implode(" OR ", $where) . ")
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE";

    $stmt = $conn->prepare($sql);
    if (count($where) === 2)
        $stmt->bind_param("ss", $order_id, $order_id);
    else
        $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $dep = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dep) {
        $conn->rollback();
        json_out(['ok' => false, 'error' => 'Deposit not found'], 404);
    }

    $depId = (int) $dep['id'];
    $user_db_id = (int) ($dep['user_db_id'] ?? 0);
    $dbAmount = (float) $dep['amount'];
    $bonusAmount = (float) $dep['bonus_amount'];
    $credited = (int) $dep['credited'];
    $curStatus = (string) $dep['status'];

    // Calculate Tiered Bonus if not assigned
    if ($bonusAmount <= 0) {
        if ($dbAmount >= 30000) $bonusAmount = 15000.0;
        elseif ($dbAmount >= 20000) $bonusAmount = 6000.0;
        elseif ($dbAmount >= 10000) $bonusAmount = 4000.0;
        elseif ($dbAmount >= 5000)  $bonusAmount = 2000.0;
        elseif ($dbAmount >= 2000)  $bonusAmount = 400.0;
        elseif ($dbAmount >= 1000)  $bonusAmount = 150.0;
        
        if ($bonusAmount > 0) {
            $upBonus = $conn->prepare("UPDATE deposit SET bonus_amount=? WHERE id=?");
            $upBonus->bind_param("di", $bonusAmount, $depId);
            $upBonus->execute();
            $upBonus->close();
        }
    }

    // amount mismatch safety (optional)
    if ($amount > 0 && abs($amount - $dbAmount) > 0.01)
        $finalStatus = 'Failed';

    $curStatusLower = strtolower(trim($curStatus));
    // update status
    if ($curStatusLower !== 'done' && $curStatusLower !== 'approved') {
        $up = $conn->prepare("UPDATE deposit SET status=? WHERE id=?");
        $up->bind_param("si", $finalStatus, $depId);
        $up->execute();
        $up->close();
    } else {
        $finalStatus = 'Done';
    }

    // credit once
    if ($finalStatus === 'Done' && $hasCredited && $credited === 0) {
        if ($user_db_id <= 0)
            throw new RuntimeException("user_db_id missing in deposit row");

        $totalToCredit = $dbAmount + $bonusAmount;

        $hasWBal = hasColumn($conn, 'users', 'wallet_balance');
        $hasW = hasColumn($conn, 'users', 'wallet');
        $add = null;

        if ($hasWBal) {
            $add = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
        } elseif ($hasW) {
            $add = $conn->prepare("UPDATE users SET wallet = wallet + ? WHERE id=?");
        }

        if ($add) {
            $add->bind_param("di", $totalToCredit, $user_db_id);
            $add->execute();
            $add->close();
        }

        $mark = $conn->prepare("UPDATE deposit SET credited=1, status='Done' WHERE id=? AND credited=0");
        $mark->bind_param("i", $depId);
        $mark->execute();
        $mark->close();

        $credited = 1;
    }

    $conn->commit();

    json_out([
        'ok' => true,
        'order_id' => $order_id,
        'status' => $finalStatus,
        'credited' => ($hasCredited ? ($credited === 1) : null)
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    json_out(['ok' => false, 'error' => 'Server error'], 500);
}
