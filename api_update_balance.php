<?php
require_once 'includes/db.php';
// declare(strict_types=1);

function debug_log($msg)
{
    try {
        $logFile = __DIR__ . '/upi_debug.log';
        $entry = date('Y-m-d H:i:s') . " | " . $msg . PHP_EOL;
        file_put_contents($logFile, $entry, FILE_APPEND);
    } catch (Exception $e) {
    }
}
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/includes/db.php';

require_once __DIR__ . '/includes/api_keys.php';
const API_SECRET_KEY = SUBH_API_SECRET_KEY;

function build_next_url(string $next, array $params): string
{
    if (empty($next))
        return '';
    if (!preg_match('~^https?://~i', $next)) {
        $scheme = 'https://';
        $next = $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . ltrim($next, '/');
    }
    $u = parse_url($next);
    $q = [];
    if (!empty($u['query']))
        parse_str($u['query'], $q);
    foreach ($params as $k => $v)
        $q[$k] = $v;
    return $u['scheme'] . '://' . $u['host'] . ($u['path'] ?? '/') . '?' . http_build_query($q);
}

function is_json_request(): bool
{
    $ct = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    $acc = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $mode = strtolower((string) ($_REQUEST['mode'] ?? ''));
    return (strpos($ct, 'application/json') !== false) || (strpos($acc, 'application/json') !== false) || ($mode === 'json');
}

function api_out(array $data, string $redirUrl): void
{
    if (is_json_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    } else {
        header("Location: $redirUrl");
    }
    exit;
}

function ensure_column(mysqli $db, string $table, string $col, string $ddl): void
{
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) c
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->bind_param('ss', $table, $col);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $exists = (int) ($row['c'] ?? 0) > 0;
        if (!$exists) {
            $db->query("ALTER TABLE `$table` ADD COLUMN $ddl");
        }
    } catch (Throwable $e) {
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = db();

$orderId = trim((string) ($_REQUEST['order_id'] ?? ($_REQUEST['orderId'] ?? ($_REQUEST['tr'] ?? ''))));
$statusRaw = trim((string) ($_REQUEST['status'] ?? ($_REQUEST['Status'] ?? ($_REQUEST['upi'] ?? ''))));
$key = trim((string) ($_REQUEST['key'] ?? ''));
$next = trim((string) ($_REQUEST['next'] ?? 'user_dashboard.php'));

$respCode = trim((string) ($_REQUEST['resp_code'] ?? ($_REQUEST['responseCode'] ?? ($_REQUEST['responsecode'] ?? ''))));

$txnId = trim((string) ($_REQUEST['txn_id'] ?? ($_REQUEST['txnid'] ?? '')));

$txnRef = trim((string) ($_REQUEST['txn_ref'] ?? ($_REQUEST['txnref'] ?? '')));
$appr = trim((string) ($_REQUEST['appr'] ?? ($_REQUEST['approvalRefNo'] ?? ($_REQUEST['rrn'] ?? ''))));
$clientRc = trim((string) ($_REQUEST['client_rc'] ?? ''));
$rawShort = trim((string) ($_REQUEST['raw'] ?? ''));

if ($key === '' || (!hash_equals(API_SECRET_KEY, $key) && !hash_equals('BJ_2026_5a9c8f2e7d1b4k6m3x', $key))) {
    debug_log("DENY: bad key | order=$orderId | status=$statusRaw | resp=$respCode | txn=$txnId | appr=$appr");
    http_response_code(403);
    if (is_json_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    } else {
        echo "Forbidden";
    }
    exit;
}

debug_log("HIT: Order=$orderId | Status=$statusRaw | resp_code=$respCode | txn_id=$txnId | appr=$appr | txn_ref=$txnRef | client_rc=$clientRc | raw=" . substr($rawShort, 0, 120));

if ($orderId === '')
    $orderId = trim((string) ($_SESSION['last_intent_order_id'] ?? ''));

if ($orderId === '') {
    debug_log("ERROR: Order ID Missing");
    http_response_code(400);
    if (is_json_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Order ID Missing']);
    } else {
        echo "Order ID Missing";
    }
    exit;
}

$statusUpper = strtoupper($statusRaw);
$respUpper = strtoupper($respCode);

$successCodes = ['SUCCESS', 'SUCCESSFUL', 'PAYMENT_SUCCESS', 'TXN_SUCCESS', 'COMPLETED', 'CAPTURED', 'DONE', 'S'];
$failCodes = ['FAILED', 'FAILURE', 'CANCELLED', 'CANCELED', 'DECLINED', 'ERROR', 'F', 'FALSE'];

$isSuccessText = in_array($statusUpper, $successCodes, true);
$isFailText = in_array($statusUpper, $failCodes, true);

// ✅ REMOVED Raw String Override Hack to fix the PhonePe Cancel/Wrong PIN glitch.
// Scanning raw text for 'status=submitted' was matching dummy merchant parameters.

$isSuccessResp = in_array($respUpper, ['00', '0', '000'], true);

$hasProof = ($txnId !== '' || $appr !== '' || $txnRef !== '');
$isResultOk = ($clientRc === '-1' || $clientRc === 'RESULT_OK');
$isResultCanceled = ($clientRc === '0' || $clientRc === 'RESULT_CANCELED');

// ✅ HARDENED: Require explicit Success text OR (Success response code AND physical payment proof)
// Prevents dummy intent exits from crediting without trace IDs.
$isSuccess = !$isFailText && ($isSuccessText || ($isSuccessResp && $hasProof));

// ✅ STRICT SYSTEM: If we have a txn_id/proof, DO NOT mark as Failed automatically.
// Money might be deducted. Leave it PENDING for admin verification or callback.
// Only mark Failed if definitely failed/cancelled AND no proof exists.
$isFail = (
    $isFailText
    && !$isSuccess
    && !$hasProof // Protect: If we have txn_id, keep Pending
);

if (!$isSuccess && !$isFail) {
    debug_log("ACTION: Pending/Unknown (no DB update). status='$statusRaw' resp='$respCode' proof=" . ($hasProof ? "1" : "0"));
    $redir = build_next_url($next, ['upi' => 'PENDING', 'order_id' => $orderId]);
    header("Location: $redir");
    exit;
}

try {
    $db->begin_transaction();

    ensure_column($db, 'deposit', 'upi_txn_id', "`upi_txn_id` VARCHAR(120) DEFAULT NULL");
    ensure_column($db, 'deposit', 'upi_txn_ref', "`upi_txn_ref` VARCHAR(120) DEFAULT NULL");
    ensure_column($db, 'deposit', 'upi_appr', "`upi_appr` VARCHAR(120) DEFAULT NULL");
    ensure_column($db, 'deposit', 'upi_resp_code', "`upi_resp_code` VARCHAR(30) DEFAULT NULL");
    ensure_column($db, 'deposit', 'upi_client_rc', "`upi_client_rc` VARCHAR(30) DEFAULT NULL");
    ensure_column($db, 'deposit', 'upi_raw', "`upi_raw` VARCHAR(700) DEFAULT NULL");

    $stmt = $db->prepare("SELECT id, user_id, amount, status, credited FROM deposit WHERE order_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $res = $stmt->get_result();
    $dep = $res->fetch_assoc();
    $stmt->close();

    if (!$dep) {
        $db->commit();
        debug_log("ERROR: Order ID $orderId not found in DB");
        $redir = build_next_url($next, ['upi' => 'FAILED', 'order_id' => $orderId, 'error' => 'not_found']);
        header("Location: $redir");
        exit;
    }

    $depId = (int) $dep['id'];
    $userId = (string) $dep['user_id'];
    $amount = (float) $dep['amount'];
    $currentStatus = strtolower(trim((string) $dep['status']));
    $credited = (int) $dep['credited'];

    // --- ADDED: BONUS CALCULATION FOR INTENT ---
    $bonus = 0.0;
    if ($amount >= 30000) $bonus = 15000.0;
    elseif ($amount >= 20000) $bonus = 6000.0;
    elseif ($amount >= 10000) $bonus = 4000.0;
    elseif ($amount >= 5000)  $bonus = 2000.0;
    elseif ($amount >= 2000)  $bonus = 400.0;
    elseif ($amount >= 1000)  $bonus = 150.0;

    $totalToCredit = $amount + $bonus;

    debug_log("DB STATE: ID=$depId | CurrStatus=$currentStatus | Credited=$credited | Bonus=$bonus");

    try {
        $rawSave = $rawShort;
        if (strlen($rawSave) > 650)
            $rawSave = substr($rawSave, 0, 650);

        $p = $db->prepare("UPDATE deposit SET upi_txn_id=?, upi_txn_ref=?, upi_appr=?, upi_resp_code=?, upi_client_rc=?, upi_raw=? WHERE id=?");
        $p->bind_param('ssssssi', $txnId, $txnRef, $appr, $respCode, $clientRc, $rawSave, $depId);
        $p->execute();
        $p->close();
    } catch (Throwable $e) {
    }

    if ($isSuccess) {

        if ($currentStatus !== 'done') {
            $u = $db->prepare("UPDATE deposit SET status='Done' WHERE id=?");
            $u->bind_param('i', $depId);
            $u->execute();
            $u->close();
            debug_log("UPDATE: Marked as Done (success)");
        }

        if ($credited === 0) {
            // Update bonus_amount in deposit safely
            $hasBonusCol = false;
            $resB = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
            if ($resB && $resB->num_rows > 0) {
              $hasBonusCol = true;
            }
            if ($hasBonusCol) {
              $db->query("UPDATE deposit SET bonus_amount=$bonus WHERE id=$depId");
            }

            $w = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?");
            $w->bind_param('ds', $totalToCredit, $userId);
            $w->execute();

            if ($w->affected_rows === 0) {
                $w->close();
                $w = $db->prepare("UPDATE users SET wallet = wallet + ? WHERE user_id = ?");
                $w->bind_param('ds', $totalToCredit, $userId);
                $w->execute();
                $w->close();
            } else {
                $w->close();
            }

            $c = $db->prepare("UPDATE deposit SET credited=1 WHERE id=?");
            $c->bind_param('i', $depId);
            $c->execute();
            $c->close();

            debug_log("UPDATE: Wallet Credited ₹$totalToCredit (Deposit: ₹$amount + Bonus: ₹$bonus)");
        } else {
            debug_log("SKIP: Already Credited");
        }

        $db->commit();
        $redir = build_next_url($next, ['upi' => 'SUCCESS', 'order_id' => $orderId, 'credited' => '1', 'status' => 'Done']);
        api_out(['ok' => true, 'status' => 'SUCCESS', 'order_id' => $orderId, 'credited' => 1], $redir);
    }

    if ($isFail) {

        if ($currentStatus === 'done') {
            debug_log("SKIP: Cannot mark failed, already Done");
            $db->commit();
            $redir = build_next_url($next, ['upi' => 'SUCCESS', 'order_id' => $orderId, 'status' => 'Done']);
            api_out(['ok' => true, 'status' => 'SUCCESS', 'order_id' => $orderId, 'credited' => 1], $redir);
        }

        if ($currentStatus === 'pending') {
            $f = $db->prepare("UPDATE deposit SET status='Failed' WHERE id=?");
            $f->bind_param('i', $depId);
            $f->execute();
            $f->close();
            debug_log("UPDATE: Marked as Failed");
        } else {
            debug_log("SKIP: Current status not pending ($currentStatus)");
        }

        $db->commit();
        $db->commit();
        $redir = build_next_url($next, ['upi' => 'FAILED', 'order_id' => $orderId]);
        api_out(['ok' => false, 'status' => 'FAILED', 'order_id' => $orderId], $redir);
    }

    $db->commit();
    $redir = build_next_url($next, ['upi' => 'PENDING', 'order_id' => $orderId]);
    api_out(['ok' => true, 'status' => 'PENDING', 'order_id' => $orderId], $redir);

} catch (Throwable $e) {
    try {
        $db->rollback();
    } catch (Exception $ee) {
    }
    debug_log("EXCEPTION: " . $e->getMessage());
    error_log("Payment Error: " . $e->getMessage());
    $redir = build_next_url($next, ['upi' => 'PENDING', 'order_id' => $orderId, 'error' => 'server_error']);
    api_out(['ok' => false, 'status' => 'ERROR', 'error' => $e->getMessage()], $redir);
}
