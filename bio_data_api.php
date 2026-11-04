<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = db();
} catch (Exception $e) {
    echo json_encode(['ok'=>0,'msg'=>'DB Error']);
    exit;
}

/* ---------------------- INPUT ---------------------- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo json_encode(['ok'=>0]); exit; }

$range = $_GET['range'] ?? 'all';
$from  = $_GET['from']  ?? '';
$to    = $_GET['to']    ?? '';

$today = date('Y-m-d');
$fromDate = null;
$toDate   = null;

/* ---------------------- DATE FILTER ---------------------- */
if ($range === 'today') {
    $fromDate = $today;
    $toDate   = $today;
}
elseif ($range === '7') {
    $fromDate = date('Y-m-d', strtotime('-6 days'));
    $toDate   = $today;
}
elseif ($range === '15') {
    $fromDate = date('Y-m-d', strtotime('-14 days'));
    $toDate   = $today;
}
elseif ($range === '30') {
    $fromDate = date('Y-m-d', strtotime('-29 days'));
    $toDate   = $today;
}
elseif ($range === 'custom') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $fromDate = $from;
        $toDate   = $to;
    }
}

$depCond  = '';
$withCond = '';
$bidCond  = '';
$winCond  = '';

if ($fromDate && $toDate) {
    $depCond  = " AND DATE(date_time) BETWEEN '$fromDate' AND '$toDate'";
    $withCond = " AND DATE(date_time) BETWEEN '$fromDate' AND '$toDate'";
    $bidCond  = " AND DATE(bid_time)  BETWEEN '$fromDate' AND '$toDate'";
    $winCond  = " AND DATE(win_time)  BETWEEN '$fromDate' AND '$toDate'";
}

/* ---------------------- FETCH USER ---------------------- */
$u = $db->query("SELECT user_id, last_seen FROM users WHERE id=$id LIMIT 1")->fetch_assoc();
if (!$u) { echo json_encode(['ok'=>0]); exit; }

$user_id     = $u['user_id'];
$lastSeenRaw = $u['last_seen'];

/* ---------------------- LIVE STATUS (2 SEC RULE) ---------------------- */
function live_status($lastSeenRaw){
    $out = ['status'=>'Offline','class'=>'status-off','last'=>'-'];

    if (empty($lastSeenRaw) || $lastSeenRaw==='0000-00-00 00:00:00')
        return $out;

    $ts = strtotime($lastSeenRaw);
    if (!$ts) return $out;

    $diff = time() - $ts;

    if ($diff <= 2) {
        $out['status'] = 'LIVE';
        $out['class']  = 'status-on';
    } else {
        $out['status'] = 'Offline';
        $out['class']  = 'status-off';
    }

    $out['last'] = date('d-m-Y h:i A', $ts);
    return $out;
}

/* get latest live table ping */
$q = $db->prepare("SELECT last_seen FROM live WHERE user_id=? ORDER BY last_seen DESC LIMIT 1");
$q->bind_param('s', $user_id);
$q->execute();
$rr = $q->get_result();
if ($rr->num_rows) {
    $row = $rr->fetch_assoc();
    if (!empty($row['last_seen'])) $lastSeenRaw = $row['last_seen'];
}
$statusInfo = live_status($lastSeenRaw);

/* ---------------------- SUMS ---------------------- */

/* Total Deposit (Done only) */
$sumDep = 0;
$q = $db->prepare("
    SELECT IFNULL(SUM(amount),0) AS s
    FROM deposit
    WHERE user_id=? AND status='Done' $depCond
");
$q->bind_param('s',$user_id);
$q->execute();
$sumDep = (float)$q->get_result()->fetch_assoc()['s'];

/* Total Withdrawal (Done only) */
$sumWith = 0;
$q = $db->prepare("
    SELECT IFNULL(SUM(amount),0) AS s
    FROM withdrawal
    WHERE user_id=? AND status='Done' $withCond
");
$q->bind_param('s',$user_id);
$q->execute();
$sumWith = (float)$q->get_result()->fetch_assoc()['s'];

/* Total Wining */
$sumWin = 0;
$q = $db->prepare("
    SELECT IFNULL(SUM(wining_balance),0) AS s
    FROM wining
    WHERE user_id=? $winCond
");
$q->bind_param('s',$user_id);
$q->execute();
$sumWin = (float)$q->get_result()->fetch_assoc()['s'];

/* Total Bidding Amount */
$bidAmt = 0;
$q = $db->prepare("
    SELECT IFNULL(SUM(amount),0) AS s
    FROM bid
    WHERE user_id=? $bidCond
");
$q->bind_param('s',$user_id);
$q->execute();
$bidAmt = (float)$q->get_result()->fetch_assoc()['s'];

/* Wallet Balance = (Deposit - Withdrawal + Wining) - Bidding */
$walletBalance = ($sumDep - $sumWith + $sumWin) - $bidAmt;

/* P & L = (Withdrawal - Deposit) + Wining */
$pl = ($sumWith - $sumDep) + $sumWin;

/* ---------------------- OUTPUT ---------------------- */
echo json_encode([
    'ok'                     => 1,
    'status'                 => $statusInfo['status'],
    'status_class'           => $statusInfo['class'],
    'last_seen'              => $statusInfo['last'],
    'sum_deposit'            => $sumDep,
    'sum_withdrawal'         => $sumWith,
    'sum_wining'             => $sumWin,
    'bidding_amount'         => $bidAmt,
    'wallet_balance_after_bid'=> $walletBalance,
    'pl'                     => $pl
]);
