<?php
require_once 'includes/db.php';
// fetch_stats.php – returns JSON stats based on GET range / from / to
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['agent'])) {
    echo json_encode(['error'=>'unauthorized']);
    exit;
}
$ag = $_SESSION['agent'];

$db = db();
if ($db->connect_error) {
    echo json_encode(['error'=>'db_connection']);
    exit;
}

// Read and sanitize inputs
$range = $_GET['range'] ?? 'today';
$from  = $_GET['from']  ?? '';
$to    = $_GET['to']    ?? '';

// Build a WHERE clause fragment if needed
$where = '1';
if ($range==='7') {
    $where = "bid_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($range==='15') {
    $where = "bid_time >= DATE_SUB(NOW(), INTERVAL 15 DAY)";
} elseif ($range==='30') {
    $where = "bid_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($range==='custom' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) {
    $where = "DATE(bid_time) BETWEEN '".$db->real_escape_string($from)."' AND '".$db->real_escape_string($to)."'";
}

// Helpers
function tbl($d,$t){ return $d&&!$d->connect_error && $d->query("SHOW TABLES LIKE '".$d->real_escape_string($t)."'")->num_rows; }
function val($d,$q){ $r=$d->query($q); return $r?($r->fetch_row()[0]??0):0; }

// Compute stats
$usersReferred = tbl($db,'users')
    ? val($db,"SELECT COUNT(*) FROM users WHERE referral='".$db->real_escape_string($ag['referral'])."' AND $where")
    : 0;
$allDeposit = tbl($db,'deposits')
    ? val($db,"SELECT IFNULL(SUM(amount),0) FROM deposits WHERE $where")
    : 0;
$allWithdraw = tbl($db,'withdrawals')
    ? val($db,"SELECT IFNULL(SUM(amount),0) FROM withdrawals WHERE $where")
    : 0;
$myCommission = tbl($db,'agent_commission')
    ? val($db,"SELECT IFNULL(SUM(commission),0) FROM agent_commission WHERE agent_id='".$db->real_escape_string($ag['agent_id'])."' AND $where")
    : 0;

$db->close();

// Return JSON
echo json_encode([
    'usersReferred'=> $usersReferred,
    'allDeposit'   => $allDeposit,
    'allWithdraw'  => $allWithdraw,
    'myCommission' => $myCommission,
]);
