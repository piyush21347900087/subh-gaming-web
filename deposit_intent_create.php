<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';

/* ✅ No cache + JSON */
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once 'includes/user_ping.php';
require_once 'includes/live_ping.php';

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('Asia/Kolkata');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = db();
} catch (Exception $e) {
    http_response_code(500);
    error_log('[deposit_intent_create][DB] '.$e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'DB error']);
    exit;
}

/* ✅ Helpers */
function fetch_one(mysqli $db, string $sql, string $types = '', array $params = []): ?string {
    $st = $db->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $st->bind_result($v);
    $out = $st->fetch() ? $v : null;
    $st->close();
    return $out;
}
function hasColumn(mysqli $db, string $table, string $column): bool {
    try {
        $st = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $st->bind_param('s', $column);
        $st->execute();
        $res = $st->get_result();
        $ok = ($res && $res->num_rows > 0);
        $st->close();
        return $ok;
    } catch (Exception $e) { return false; }
}
function detectFirstCol(mysqli $db, string $table, array $cands, string $fallback): string {
    foreach ($cands as $c) {
        if (hasColumn($db, $table, $c)) return $c;
    }
    return $fallback;
}

/* ✅ Read inputs */
$uid    = (int)$_SESSION['user']['id'];
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$txn    = trim((string)($_POST['transaction_id'] ?? ''));

/* minimum amount from DB */
$MIN_DEPOSIT = 300;
try {
    $resL = $db->query("SELECT min_deposit FROM admin_payment_details ORDER BY id DESC LIMIT 1");
    if ($resL && $rL = $resL->fetch_assoc()) {
        $MIN_DEPOSIT = (float) $rL['min_deposit'];
    }
} catch (Exception $e) { }

if ($amount < $MIN_DEPOSIT) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>"Minimum deposit ₹{$MIN_DEPOSIT}"]);
    $db->close(); exit;
}
if ($txn === '' || !preg_match('/^[A-Za-z0-9\-\_]{6,64}$/', $txn)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Invalid transaction_id']);
    $db->close(); exit;
}

/* ✅ Get user's internal code (users.user_id) */
$userCode = fetch_one($db, "SELECT user_id FROM users WHERE id=?", "i", [$uid]) ?: '';
if ($userCode === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'User code not found']);
    $db->close(); exit;
}

/* ✅ Detect deposit table columns safely */
$DEP_USER_COL = detectFirstCol($db, 'deposit', ['user_id','userid','uid','userId','userID'], 'user_id');

$hasAmount   = hasColumn($db, 'deposit', 'amount');
$hasStatus   = hasColumn($db, 'deposit', 'status');
$hasMethod   = hasColumn($db, 'deposit', 'method');
$hasTxnCol   = hasColumn($db, 'deposit', 'transaction_id');
$timeCol     = detectFirstCol($db, 'deposit', ['date_time','created_at'], ''); // optional

if (!$hasAmount || !$hasStatus || !$hasMethod) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'deposit table missing required columns (amount/status/method)']);
    $db->close(); exit;
}
if (!hasColumn($db, 'deposit', $DEP_USER_COL)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>"deposit table missing user column: {$DEP_USER_COL}"]);
    $db->close(); exit;
}

/* ✅ Idempotent: if already exists (same txn) return ok */
try {
    if ($hasTxnCol) {
        $st = $db->prepare("SELECT id, status, amount FROM deposit WHERE transaction_id=? ORDER BY id DESC LIMIT 1");
        $st->bind_param('s', $txn);
        $st->execute();
        $res = $st->get_result();
        if ($res && $res->num_rows) {
            $row = $res->fetch_assoc();
            $st->close();
            echo json_encode([
                'ok' => true,
                'already' => true,
                'id' => (int)$row['id'],
                'status' => (string)$row['status'],
                'amount' => (float)$row['amount'],
                'txn' => $txn
            ], JSON_UNESCAPED_UNICODE);
            $db->close();
            exit;
        }
        $st->close();
    }
} catch (Exception $e) {
    // ignore and try insert
}

/* ✅ Insert Pending deposit (UPI Intent) */
try {
    $amount = round($amount, 2);

    $cols = [];
    $vals = [];
    $types = '';
    $params = [];

    // user col
    $cols[] = "`{$DEP_USER_COL}`";
    $vals[] = "?";
    $types .= "s";
    $params[] = $userCode;

    // amount
    $cols[] = "`amount`";
    $vals[] = "?";
    $types .= "d";
    $params[] = $amount;

    // status
    $cols[] = "`status`";
    $vals[] = "?";
    $types .= "s";
    $params[] = "Pending";

    // method
    $cols[] = "`method`";
    $vals[] = "?";
    $types .= "s";
    $params[] = "UPI Intent";

    // transaction_id (optional)
    if ($hasTxnCol) {
        $cols[] = "`transaction_id`";
        $vals[] = "?";
        $types .= "s";
        $params[] = $txn;
    }

    // time col (optional)
    if ($timeCol !== '') {
        $cols[] = "`{$timeCol}`";
        $vals[] = "?";
        $types .= "s";
        $params[] = date('Y-m-d H:i:s');
    }

    $sql = "INSERT INTO deposit (".implode(",", $cols).") VALUES (".implode(",", $vals).")";
    $st = $db->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $newId = (int)$st->insert_id;
    $st->close();

    echo json_encode([
        'ok' => true,
        'id' => $newId,
        'txn' => $txn,
        'amount' => $amount,
        'status' => 'Pending'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    error_log('[deposit_intent_create][INSERT] '.$e->getMessage());
    echo json_encode(['ok'=>false,'error'=>'Insert failed']);
}

$db->close();
