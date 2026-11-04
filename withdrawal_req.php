<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db.php';
/* withdrawal_req.php – Admin view for withdrawal requests + Notifications (UPDATED + Date Range like deposit_req.php) */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = db();
    if ($db->connect_errno) {
        throw new Exception($db->connect_error);
    }
    $db->set_charset('utf8mb4');
    $db->query("SET time_zone = '+05:30'");
} catch (Exception $e) {
    die('DB error: ' . $e->getMessage());
}
/* ================== FCM CONFIG & HELPERS ================== */
if (!defined('GOOGLE_SA_JSON')) {
    define('GOOGLE_SA_JSON', __DIR__ . '/secure/service-account.json');
}
if (!defined('FCM_PROJECT_ID')) {
    define('FCM_PROJECT_ID', 'kismat555-b686e');
}
// Safe include for FCM helper to prevent Fatal Error if file is missing on server
$fcmFile = __DIR__ . '/includes/fcm_helper.php';
if (file_exists($fcmFile)) {
    require_once $fcmFile;
} else {
    // Fallback stubs so page handles manual actions without crashing
    if (!function_exists('google_access_token_from_sa_cached')) {
        function google_access_token_from_sa_cached($p)
        {
            return '';
        }
    }
    if (!function_exists('fcm_v1_send_token')) {
        function fcm_v1_send_token($p, $t, $d, $tr)
        {
            return ['http' => 0, 'err' => 'missing_helper_file'];
        }
    }
    if (!function_exists('fcm_v1_send_topic')) {
        function fcm_v1_send_topic($p, $t, $d, $topic)
        {
            return ['http' => 0, 'err' => 'missing_helper_file'];
        }
    }
}
/* -------- helper: column exists ----------*/
function columnExists(mysqli $db, $table, $col)
{
    $table = $db->real_escape_string($table);
    $col = $db->real_escape_string($col);
    $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
    return ($res && $res->num_rows > 0);
}
/* -------- helpers ----------*/
function displayMethodNice($m)
{
    $mStr = trim((string) $m);
    $lower = strtolower($mStr);
    if ($mStr === '' || $mStr === '-' || $lower === 'admin')
        return 'Admin';
    $map = ['bank' => 'Bank', 'upi' => 'UPI QR', 'phonepe' => 'PhonePe', 'google pay' => 'Google Pay', 'gpay' => 'Google Pay', 'paytm' => 'Paytm', 'bhim' => 'BHIM', 'imb' => 'IMB'];
    return $map[$lower] ?? ucfirst($mStr);
}
function upiKeyForNumbers($m)
{
    $k = strtolower(trim((string) $m));
    if ($k === 'google pay' || $k === 'gpay')
        return 'gpay';
    return $k;
}
function isUpiApp($m)
{
    $k = strtolower(trim((string) $m));
    return in_array($k, ['phonepe', 'google pay', 'gpay', 'paytm', 'bhim'], true);
}
/* ---------- Fixed master admin_id ---------- */
$fixedAdminId = 'ADM0001';
$hasAdminIdCol = columnExists($db, 'withdrawal', 'admin_id');
/* ---------- Helper: Normalize User ID ---------- */
function norm_uid_admin($uid)
{
    $uid = trim((string) $uid);
    if ($uid === '')
        return '';
    $uid = strtoupper($uid);
    // Numeric only -> DM padding (New Standard)
    if (preg_match('/^\d+$/', $uid)) {
        return "DM" . str_pad($uid, 5, "0", STR_PAD_LEFT);
    }
    if (preg_match('/^(RK|DP|DB|DPB|KL|KLY|KB|KBT)([0-9]+)$/', $uid, $m)) {
        return $m[1] . str_pad($m[2], 5, "0", STR_PAD_LEFT);
    }
    return $uid;
}
/* ⚠️ Ensure we capture filters from either GET or POST (for AJAX) */
$statusFilter = strtolower(trim($_REQUEST['status'] ?? 'all'));
$search = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';
$dateRange = strtolower(trim($_REQUEST['range'] ?? 'today'));
$startDate = trim($_REQUEST['start'] ?? '');
$endDate = trim($_REQUEST['end'] ?? '');
/* ---- Status Filter ---- */
$allowed = ['all', 'pending', 'done', 'failed'];
if (!in_array($statusFilter, $allowed, true))
    $statusFilter = 'all';
$statusAppend = "";
if ($statusFilter === 'pending')
    $statusAppend = " AND status = 'Pending'";
elseif ($statusFilter === 'done')
    $statusAppend = " AND status = 'Done'";
elseif ($statusFilter === 'failed')
    $statusAppend = " AND status = 'Failed'";
/* ---- Search Filter (User ID or Amount) ---- */
$searchAppend = "";
if ($search !== '') {
    $escSearch = $db->real_escape_string($search);
    $searchAppend = " AND (user_id LIKE '%$escSearch%' OR amount LIKE '%$escSearch%')";
}
/* ========================= DATE RANGE FILTER ========================= */
$allowedRanges = ['today', '7', '15', '30', 'all', 'custom'];
if (!in_array($dateRange, $allowedRanges, true))
    $dateRange = 'today';
$dateWhere = "";
$rangeLabel = "All Time";
if ($dateRange === 'today') {
    $dateWhere = " AND date_time >= CURDATE() AND date_time < (CURDATE() + INTERVAL 1 DAY)";
    $rangeLabel = "Today";
} elseif ($dateRange === '7') {
    $dateWhere = " AND date_time >= (NOW() - INTERVAL 7 DAY)";
    $rangeLabel = "Last 7 Days";
} elseif ($dateRange === '15') {
    $dateWhere = " AND date_time >= (NOW() - INTERVAL 15 DAY)";
    $rangeLabel = "Last 15 Days";
} elseif ($dateRange === '30') {
    $dateWhere = " AND date_time >= (NOW() - INTERVAL 30 DAY)";
    $rangeLabel = "Last 30 Days";
} elseif ($dateRange === 'custom') {
    $okStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate);
    $okEnd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate);
    if ($okStart && $okEnd) {
        $s = $db->real_escape_string($startDate);
        $e = $db->real_escape_string($endDate);
        $dateWhere = " AND date_time >= '{$s} 00:00:00' AND date_time <= '{$e} 23:59:59'";
        $rangeLabel = "Custom: {$startDate} to {$endDate}";
    } elseif ($okStart && !$okEnd) {
        $s = $db->real_escape_string($startDate);
        $dateWhere = " AND date_time >= '{$s} 00:00:00'";
        $rangeLabel = "Custom: From {$startDate}";
    } elseif (!$okStart && $okEnd) {
        $e = $db->real_escape_string($endDate);
        $dateWhere = " AND date_time <= '{$e} 23:59:59'";
        $rangeLabel = "Custom: Upto {$endDate}";
    } else {
        $dateRange = 'today';
        $rangeLabel = "Today";
    }
}
// ⚠️ CRITICAL: $baseWhere is Search + Date (BUT NO STATUS)
$cleanedBase = "method != 'game_bid'";
$baseWhere = "$cleanedBase $searchAppend $dateWhere";
// $where is $baseWhere + STATUS (For table display)
$where = $baseWhere . $statusAppend;
/* -------- AJAX: Bulk Reject All Pending (Ultra-Stable Version) ----------*/
if (isset($_POST['action']) && $_POST['action'] == 'reject_all_pending') {
    header('Content-Type: application/json');
    try {
        $adminId = $fixedAdminId; 
        // 1. Precise Selection (Get counts and basic info first for logs/notifications)
        $findQ = "SELECT id, user_id, amount FROM withdrawal WHERE LOWER(status)='pending' AND ($baseWhere)";
        $findRes = $db->query($findQ);
        $toProcess = [];
        while($r = $findRes->fetch_assoc()) $toProcess[] = $r;
        if (empty($toProcess)) {
            echo json_encode(['ok' => true, 'count' => 0, 'msg' => 'No pending requests found matching current filters.']);
            exit;
        }
        // 2. Optimized Bulk Update
        $finalQuery = ($search === '' && $dateRange === 'all') 
            ? "LOWER(status)='pending' AND method != 'game_bid'" 
            : "LOWER(status)='pending' AND ($baseWhere)";
        $bulkSql = $hasAdminIdCol 
            ? "UPDATE withdrawal SET status='Failed', admin_id='$adminId' WHERE $finalQuery"
            : "UPDATE withdrawal SET status='Failed' WHERE $finalQuery";
        if (!$db->query($bulkSql)) {
            throw new Exception("Database Update Failed: " . $db->error);
        }
        $affected = $db->affected_rows;
        // 3. Selective Notifications (Process in background, don't let failure stop us)
        $accessToken = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
        if ($accessToken) {
            foreach ($toProcess as $item) {
                try {
                    $uId = $item['user_id'];
                    $amt = (float)$item['amount'];
                    $tk = get_latest_fcm_token($db, $uId);
                    if ($tk) {
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                        $wtUrl = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/deposit.php';
                        $payload = [
                            'type' => 'withdrawal_rejected',
                            'title' => 'Withdrawal Rejected',
                            'message' => "Your withdrawal request of ₹" . number_format($amt, 2) . " was rejected by Admin.",
                            'amount' => (string) $amt,
                            'link_url' => $wtUrl,
                            'icon' => 'ic_notification',
                        ];
                        fcm_v1_send_token(FCM_PROJECT_ID, $accessToken, $payload, $tk);
                    }
                } catch (Exception $e2) {}
            }
        }
        echo json_encode(['ok' => true, 'count' => $affected]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
/* -------- AJAX status update + NOTIFICATION ----------*/
if (isset($_POST['ajax'], $_POST['id'], $_POST['newStatus'])) {
    header('Content-Type: application/json');
    $id = (int) ($_POST['id']);
    $st = ($_POST['newStatus'] === 'Done') ? 'Done' : 'Failed';
    $adminId = $fixedAdminId;
    // Fetch request details BEFORE update
    $reqQ = $db->prepare("SELECT user_id, amount, status FROM withdrawal WHERE id=? LIMIT 1");
    $reqQ->bind_param('i', $id);
    $reqQ->execute();
    $reqRes = $reqQ->get_result();
    $reqRow = $reqRes ? $reqRes->fetch_assoc() : null;
    $reqQ->close();
    if (!$reqRow) {
        echo json_encode(['ok' => false, 'error' => 'Request not found']);
        exit;
    }
    $userId = (string) $reqRow['user_id'];
    $amt = (float) $reqRow['amount'];
    $oldSt = (string) $reqRow['status'];
    if (strtolower($oldSt) !== 'pending') {
        echo json_encode(['ok' => false, 'error' => 'Already processed']);
        exit;
    }
    // 1) Update Status
    if ($hasAdminIdCol) {
        $q = $db->prepare("UPDATE withdrawal SET status=?, admin_id=? WHERE id=?");
        $q->bind_param('ssi', $st, $adminId, $id);
    } else {
        $q = $db->prepare("UPDATE withdrawal SET status=? WHERE id=?");
        $q->bind_param('si', $st, $id);
    }
    $ok = $q->execute();
    $q->close();
    // 2) Send Notification
    if ($ok) {
        $fcmToken = get_latest_fcm_token($db, $userId);
        if ($fcmToken !== '') {
            $accessToken = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
            if ($accessToken) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $wtHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $wtBase = dirname($_SERVER['PHP_SELF']);
                $wtBase = ($wtBase === '.' || $wtBase === '/' || $wtBase === '\\') ? '' : rtrim($wtBase, '/\\');
                $wtUrl = $protocol . $wtHost . $wtBase . '/deposit.php';
                if ($st === 'Done') {
                    $payload = [
                        'type' => 'withdrawal_approved',
                        'title' => 'Withdrawal Approved',
                        'message' => "Your withdrawal request of ₹" . number_format($amt, 2) . " has been processed successfully.",
                        'amount' => (string) $amt,
                        'link_url' => $wtUrl,
                        'icon' => 'ic_notification',
                    ];
                } else {
                    $payload = [
                        'type' => 'withdrawal_rejected',
                        'title' => 'Withdrawal Rejected',
                        'message' => "Your withdrawal request of ₹" . number_format($amt, 2) . " was rejected.",
                        'amount' => (string) $amt,
                        'link_url' => $wtUrl,
                        'icon' => 'ic_notification',
                    ];
                }
                fcm_v1_send_token(FCM_PROJECT_ID, $accessToken, $payload, $fcmToken);
            }
        }
    }
    echo json_encode([
        'ok' => (bool) $ok,
        'status' => $st,
        'admin_id' => $adminId
    ]);
    exit;
}
$sumQ = $db->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status='Done' THEN 1 ELSE 0 END) AS done_count,
        SUM(CASE WHEN status='Failed' THEN 1 ELSE 0 END) AS failed_count,
        COALESCE(SUM(CASE WHEN status='Done' THEN amount ELSE 0 END),0) AS done_amount
    FROM withdrawal
    WHERE $baseWhere
");
$sumRow = $sumQ ? $sumQ->fetch_assoc() : null;
$hdrTotal = (int) ($sumRow['total'] ?? 0);
$hdrPend = (int) ($sumRow['pending_count'] ?? 0);
$hdrDone = (int) ($sumRow['done_count'] ?? 0);
$hdrFail = (int) ($sumRow['failed_count'] ?? 0);
$hdrDoneAmt = (float) ($sumRow['done_amount'] ?? 0);
/* -------- 3. PAGINATION LOGIC ----------*/
$limit = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
// Count Total
$cntRes = $db->query("SELECT COUNT(*) AS c FROM withdrawal WHERE $where");
if (!$cntRes)
    die('DB error: ' . $db->error);
$total = (int) $cntRes->fetch_assoc()['c'];
$pages = max(1, (int) ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;
/* --- admin_id column include --- */
$extraCols = '';
if ($hasAdminIdCol)
    $extraCols .= ", admin_id";
// Fetch Data
$res = $db->query("
    SELECT id, user_id, amount, method, status, date_time, (SELECT id FROM users WHERE users.user_id = withdrawal.user_id LIMIT 1) AS u_id
    $extraCols
    FROM withdrawal
    WHERE $where
    ORDER BY status='Pending' DESC, id DESC
    LIMIT $limit OFFSET $offset
");
if (!$res)
    die('DB error: ' . $db->error);
/* --- Pull rows into array --- */
$list = [];
$userIds = [];
while ($row = $res->fetch_assoc()) {
    $list[] = $row;
    $userIds[strtoupper($row['user_id'])] = true;
}
$res->free();
/* ------- Preload KYC + UPI numbers ------- */
$userKyc = [];
$upiNums = [];
if (!empty($userIds)) {
    $keys = array_keys($userIds);
    $quoted = array_map(function ($x) use ($db) {
        return "'" . $db->real_escape_string($x) . "'";
    }, $keys);
    $uidsQuoted = implode(',', $quoted);
    // BANK KYC latest (PHP sorted to be 100% case and space robust)
    $q = "SELECT * FROM bank_kyc WHERE user_id IN ($uidsQuoted) ORDER BY id DESC";
    if ($bks = $db->query($q)) {
        while ($bk = $bks->fetch_assoc()) {
            $uidKey = strtoupper(trim($bk['user_id']));
            if (!isset($userKyc[$uidKey]['bank'])) {
                $userKyc[$uidKey]['bank'] = $bk;
            }
        }
        $bks->free();
    }
    // UPI KYC latest (PHP sorted to be 100% case and space robust)
    $q = "SELECT * FROM upi_kyc WHERE user_id IN ($uidsQuoted) ORDER BY id DESC";
    if ($uks = $db->query($q)) {
        while ($uk = $uks->fetch_assoc()) {
            $uidKey = strtoupper(trim($uk['user_id']));
            if (!isset($userKyc[$uidKey]['upi'])) {
                $userKyc[$uidKey]['upi'] = $uk;
            }
        }
        $uks->free();
    }
    // UPI NUMBERS (PHP sorted to be 100% case and space robust)
    $q = "SELECT user_id, upi_type, upi_number FROM upi_numbers WHERE user_id IN ($uidsQuoted) ORDER BY id DESC";
    if ($uns = $db->query($q)) {
        while ($un = $uns->fetch_assoc()) {
            $uidKey = strtoupper(trim($un['user_id']));
            $type = strtolower(trim($un['upi_type']));
            if (!isset($upiNums[$uidKey][$type]))
                $upiNums[$uidKey][$type] = $un['upi_number'];
        }
        $uns->free();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Withdrawal Requests – Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 for premium popup alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --red: #e53935;
            --amber: #ffb300;
            --green: #2e7d32;
            --brown: #4e342e;
            --blue: #0277bd;
            --bg: #f3f4f7;
            --glass: rgba(255, 255, 255, .7);
            --radius: 12px;
            --shadow: 0 4px 18px rgba(0, 0, 0, .08)
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Poppins, Arial, sans-serif
        }
        body {
            background: var(--bg);
            padding: 24px 18px
        }
        a {
            color: inherit;
            text-decoration: none
        }
        .top-bar {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            padding: 12px 20px;
            border-radius: var(--radius);
            backdrop-filter: blur(10px);
            background: var(--glass);
            box-shadow: var(--shadow)
        }
        .top-btn {
            background: var(--brown);
            color: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px
        }
        .top-bar h1 {
            font-size: 20px;
            font-weight: 600;
            color: var(--brown);
            position: absolute;
            left: 50%;
            transform: translateX(-50%)
        }
        .top-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-left: auto;
            flex-wrap: wrap
        }
        .search-form {
            display: flex;
            gap: 8px;
        }
        .search-form input {
            padding: 9px 12px;
            width: 200px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 14px
        }
        .search-form button {
            padding: 9px 12px;
            background: var(--brown);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .status-filter,
        .range-filter,
        .date-input {
            padding: 7px 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 14px;
            background: #fff
        }
        .table-wrap {
            overflow-x: auto;
            margin-top: 10px
        }
        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden
        }
        th,
        td {
            padding: 10px 8px;
            text-align: center;
            font-size: 14px
        }
        th {
            background: var(--brown);
            color: #fff;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer
        }
        tbody tr {
            cursor: pointer;
        }
        tbody tr:nth-child(even) {
            background: #fafafa
        }
        tbody tr:hover {
            background: #fff9e6
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #fff;
            min-width: 70px;
            display: inline-block
        }
        .pending {
            background: var(--amber)
        }
        .done {
            background: var(--green)
        }
        .failed {
            background: var(--red)
        }
        .btn {
            padding: 6px 10px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            color: #fff;
            cursor: pointer
        }
        .approve {
            background: var(--green)
        }
        .reject, .reject-bulk {
            background: var(--red)
        }
        .btn:disabled {
            opacity: .45;
            cursor: not-allowed
        }
        .pagination {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
            align-items: center;
        }
        .pagination a,
        .pagination span {
            padding: 8px 16px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #ccc;
            font-size: 14px;
            color: #333;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .pagination a:hover {
            background: var(--brown);
            color: #fff;
            border-color: var(--brown);
        }
        .pagination .info {
            background: transparent;
            border: none;
            box-shadow: none;
        }
        .user-link {
            color: var(--blue);
            font-weight: 600;
            text-decoration: none;
        }
        .user-link:hover {
            text-decoration: underline;
        }
        #kycModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .62);
            align-items: center;
            justify-content: center;
            z-index: 200
        }
        #kycModal.show {
            display: flex
        }
        .modal-box {
            background: #1c1427;
            color: #fff;
            padding: 22px 26px 18px 26px;
            border-radius: var(--radius);
            max-width: 90vw;
            min-width: 320px;
            width: 400px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .close {
            position: absolute;
            top: 8px;
            right: 12px;
            font-size: 26px;
            color: #ccc;
            cursor: pointer
        }
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--amber);
            margin-bottom: 14px;
            text-align: center
        }
        .modal-fields>div {
            margin-bottom: 18px;
            font-size: 15px;
        }
        .modal-fields b {
            color: var(--blue);
        }
        .modal-fields img {
            max-width: 150px;
            margin: 12px 0;
            border-radius: 8px;
            box-shadow: 0 2px 10px #0002
        }
        /* ---- stats row (like deposit_req.php) ---- */
        .stats-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            background: var(--glass);
            backdrop-filter: blur(10px);
            padding: 10px 14px;
            margin-bottom: 14px;
            border-radius: var(--radius);
            box-shadow: var(--shadow)
        }
        .stat-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 1px solid #e6e6e6;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 13px;
            font-weight: 600;
            color: #333
        }
        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .dot.all {
            background: #777
        }
        .dot.pending {
            background: var(--amber)
        }
        .dot.done {
            background: var(--green)
        }
        .dot.failed {
            background: var(--red)
        }
        .dot.money {
            background: var(--blue)
        }
        .small-note {
            font-size: 12px;
            color: #555;
            font-weight: 600
        }
        @media(max-width:768px) {
            .top-bar {
                flex-direction: column;
                align-items: flex-start
            }
            .top-bar h1 {
                position: static;
                transform: none;
                margin-bottom: 10px
            }
            .top-controls {
                flex-direction: column;
                width: 100%
            }
            .search-form {
                width: 100%;
            }
            .search-form input {
                width: 100%
            }
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <div class="top-bar">
        <a href="admin_dashboard.php" class="top-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <h1>Withdrawal Requests</h1>
        <div class="top-controls">
            <!-- Date Range -->
            <select id="rangeFilter" class="range-filter">
                <option value="all" <?= $dateRange === 'all' ? 'selected' : '' ?>>All Time</option>
                <option value="today" <?= $dateRange === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="7" <?= $dateRange === '7' ? 'selected' : '' ?>>7 Days</option>
                <option value="15" <?= $dateRange === '15' ? 'selected' : '' ?>>15 Days</option>
                <option value="30" <?= $dateRange === '30' ? 'selected' : '' ?>>30 Days</option>
                <option value="custom" <?= $dateRange === 'custom' ? 'selected' : '' ?>>Custom</option>
            </select>
            <!-- Custom date inputs -->
            <input type="date" id="startDate" class="date-input" value="<?= htmlspecialchars($startDate) ?>"
                <?= $dateRange === 'custom' ? '' : 'style="display:none;"' ?>>
            <input type="date" id="endDate" class="date-input" value="<?= htmlspecialchars($endDate) ?>"
                <?= $dateRange === 'custom' ? '' : 'style="display:none;"' ?>>
            <button id="applyCustom" class="top-btn"
                style="padding:7px 12px;<?= $dateRange === 'custom' ? '' : 'display:none;' ?>">
                <i class="fas fa-filter"></i> Apply
            </button>
            <!-- Status -->
            <select id="statusFilter" class="status-filter">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="done" <?= $statusFilter === 'done' ? 'selected' : '' ?>>Done</option>
                <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
            <!-- Search -->
            <form method="GET" class="search-form">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <input type="hidden" name="range" value="<?= htmlspecialchars($dateRange) ?>">
                <input type="hidden" name="start" value="<?= htmlspecialchars($startDate) ?>">
                <input type="hidden" name="end" value="<?= htmlspecialchars($endDate) ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search User ID or Amount...">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
    <!-- ✅ SAME ROW HEADER NUMBERS (Counts + Done Amount only) -->
    <?php if ($hdrPend > 0): ?>
        <div class="stats-row">
            <button type="button" id="bulkRejectBtn" class="btn reject-bulk" style="margin-left:auto; padding: 8px 16px; font-weight:600; cursor:pointer; border:none; border-radius:8px; background:#e74c3c; color:white;">
                <i class="fas fa-trash-alt"></i> Reject All Pending (<?= $hdrPend ?>)
            </button>
        </div>
    <?php endif; ?>
    <div class="stats-row">
        <div class="stat-pill"><span class="dot all"></span> Total: <?= (int) $hdrTotal ?></div>
        <div class="stat-pill"><span class="dot pending"></span> Pending: <?= (int) $hdrPend ?></div>
        <div class="stat-pill"><span class="dot done"></span> Done: <?= (int) $hdrDone ?></div>
        <div class="stat-pill"><span class="dot failed"></span> Failed: <?= (int) $hdrFail ?></div>
        <div class="stat-pill"><span class="dot money"></span> Done Amount: ₹<?= number_format($hdrDoneAmt, 2) ?></div>
        <div class="small-note"><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($rangeLabel) ?></div>
    </div>
    <div class="table-wrap">
        <table id="reqTable">
            <thead>
                <tr>
                    <th>Sr No.</th>
                    <th>User ID</th>
                    <th>Amount (₹)</th>
                    <th>Details</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Action By</th>
                    <th>Date/Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sr = $offset;
                if (!empty($list)):
                    foreach ($list as $r):
                        $c = strtolower((string) $r['status']);
                        $userId = strtoupper((string) $r['user_id']);
                        $rawMethod = strtolower(trim((string) $r['method']));
                        $prettyMethod = displayMethodNice($rawMethod);
                        $bank = $userKyc[$userId]['bank'] ?? null;
                        $upi = $userKyc[$userId]['upi'] ?? null;
                        $adminIdShow = ($hasAdminIdCol && isset($r['admin_id']) && $r['admin_id'] !== '' && $r['admin_id'] !== '0') ? $r['admin_id'] : $fixedAdminId;
                        ob_start();
                        ?>
                        <div style="font-family: 'Poppins', sans-serif; padding: 10px; border-radius: 12px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); width: 100%;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <span style="font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #aaa; font-weight: 500;">Payment Method</span>
                                <span style="font-size: 15px; font-weight: 600; color: #ffb300; background: rgba(255,179,0,0.1); padding: 4px 12px; border-radius: 20px;"><?= htmlspecialchars($prettyMethod) ?></span>
                            </div>
                            <!-- Details based on Method -->
                            <?php if ($rawMethod === 'bank'): ?>
                                <?php if (!empty($bank) && isset($bank['status'])): ?>
                                    <div style="display: flex; flex-direction: column; gap: 10px;">
                                        <div style="display: flex; justify-content: space-between; font-size: 14px;"><span style="color: #bbb;">Bank Name:</span><span style="color: #fff; font-weight: 600;"><?= htmlspecialchars($bank['bank_name'] ?? '') ?></span></div>
                                        <div style="display: flex; justify-content: space-between; font-size: 14px;"><span style="color: #bbb;">Holder Name:</span><span style="color: #fff; font-weight: 600;"><?= htmlspecialchars($bank['account_name'] ?? '') ?></span></div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 14px;">
                                            <span style="color: #bbb;">Account No:</span>
                                            <span style="color: #fff; font-weight: 600; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                                                <?= htmlspecialchars($bank['account_number'] ?? '') ?>
                                                <i class="far fa-copy" onclick="copyKycVal(event, '<?= htmlspecialchars($bank['account_number'] ?? '') ?>')" style="cursor: pointer; color: #ffb300;" title="Copy"></i>
                                            </span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 14px;">
                                            <span style="color: #bbb;">IFSC Code:</span>
                                            <span style="color: #fff; font-weight: 600; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                                                <?= htmlspecialchars($bank['ifsc'] ?? '') ?>
                                                <i class="far fa-copy" onclick="copyKycVal(event, '<?= htmlspecialchars($bank['ifsc'] ?? '') ?>')" style="cursor: pointer; color: #ffb300;" title="Copy"></i>
                                            </span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; font-size: 14px;"><span style="color: #bbb;">Branch:</span><span style="color: #fff; font-weight: 600;"><?= htmlspecialchars($bank['branch'] ?? '') ?></span></div>
                                    </div>
                                <?php else: ?>
                                    <div style="color: #ff5252; text-align: center; font-weight: 600; padding: 10px 0;"><i class="fas fa-exclamation-circle"></i> No Bank KYC Found</div>
                                <?php endif; ?>
                            <?php elseif ($rawMethod === 'upi' || $rawMethod === 'upi_qr' || $rawMethod === 'upi_intent'): ?>
                                <?php if (!empty($upi) && isset($upi['status'])): ?>
                                    <div style="display: flex; flex-direction: column; gap: 10px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 14px;">
                                            <span style="color: #bbb;">UPI ID:</span>
                                            <span style="color: #fff; font-weight: 600; font-size: 15px; letter-spacing: 0.5px;"><?= htmlspecialchars($upi['upi_id'] ?? '') ?></span>
                                        </div>
                                    </div>
                                    <?php if (!empty($upi['qr_image'])): 
                                        $qrUrl = trim((string)$upi['qr_image']);
                                    ?>
                                        <div style="text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.06);">
                                            <div style="font-size: 12px; color: #aaa; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">UPI QR Code</div>
                                            <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code" style="max-width: 180px; border-radius: 10px; border: 4px solid #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.5);">
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div style="color: #ff5252; text-align: center; font-weight: 600; padding: 10px 0;"><i class="fas fa-exclamation-circle"></i> No UPI KYC Found</div>
                                <?php endif; ?>
                            <?php elseif (isUpiApp($rawMethod)): 
                                $key = upiKeyForNumbers($rawMethod);
                                $num = $upiNums[$userId][$key] ?? '';
                            ?>
                                <?php if ($num !== ''): ?>
                                    <div style="display: flex; flex-direction: column; gap: 10px;">
                                        <div style="display: flex; justify-content: space-between; font-size: 14px;">
                                            <span style="color: #bbb;">Mobile No:</span>
                                            <span style="color: #fff; font-weight: 600; font-size: 15px; letter-spacing: 0.5px;"><?= htmlspecialchars($num) ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="color: #ff5252; text-align: center; font-weight: 600; padding: 10px 0;"><i class="fas fa-exclamation-circle"></i> No Linked Mobile Number</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="color: #eee; text-align: center; padding: 10px 0;">Method: <?= htmlspecialchars($prettyMethod) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php
                        $kycBase64 = base64_encode(ob_get_clean());
                        ?>
                        <tr data-kyc64="<?= htmlspecialchars($kycBase64) ?>">
                            <td><?= ++$sr ?></td>
                            <td>
                                <a href="bio_data.php?id=<?= (int)$r['u_id'] ?>" class="user-link"
                                    title="Open Bio Data" target="_blank">
                                    <?= htmlspecialchars($userId) ?> <i class="fas fa-external-link-alt"
                                        style="font-size:10px;"></i>
                                </a>
                            </td>
                            <td><?= number_format((float) $r['amount'], 2) ?></td>
                            <td>
                                <span class="view-img" style="cursor:pointer; color:var(--blue); font-weight:600;">
                                    <i class="fas fa-info-circle"></i> Info
                                </span>
                            </td>
                            <td><?= htmlspecialchars($prettyMethod) ?></td>
                            <td><span class="badge <?= $c ?>"><?= ucfirst($c) ?></span></td>
                            <td class="action-by"><?= $c !== 'pending' ? htmlspecialchars($adminIdShow) : '-' ?></td>
                            <td><?= date('d M Y H:i', strtotime($r['date_time'])) ?></td>
                            <td>
                                <button type="button" class="btn approve" data-id="<?= (int) $r['id'] ?>" data-action="Done"
                                    <?= $c !== 'pending' ? 'disabled' : '' ?>><i class="fas fa-check"></i></button>
                                <button type="button" class="btn reject" data-id="<?= (int) $r['id'] ?>" data-action="Failed"
                                    <?= $c !== 'pending' ? 'disabled' : '' ?>><i class="fas fa-times"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="9">No data found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > 0): ?>
        <div class="pagination">
            <?php
            $qs = "&status=" . urlencode($statusFilter)
                . "&search=" . urlencode($search)
                . "&range=" . urlencode($dateRange)
                . "&start=" . urlencode($startDate)
                . "&end=" . urlencode($endDate);
            ?>
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= $qs ?>">&laquo; Prev</a>
            <?php else: ?>
                <span style="opacity:0.5; cursor:not-allowed;">&laquo; Prev</span>
            <?php endif; ?>
            <span class="info">Page <?= $page ?> of <?= $pages ?> (Total <?= $total ?>)</span>
            <?php if ($page < $pages): ?>
                <a href="?page=<?= $page + 1 ?><?= $qs ?>">Next &raquo;</a>
            <?php else: ?>
                <span style="opacity:0.5; cursor:not-allowed;">Next &raquo;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div id="kycModal">
        <div class="modal-box"><span class="close" onclick="modal(false)">&times;</span>
            <div class="modal-title">Payment Details</div>
            <div class="modal-fields" id="modalFields"></div>
        </div>
    </div>
    <script>
        var FIXED_ADMIN_ID = "<?= htmlspecialchars($fixedAdminId) ?>";
        function modal(s) { document.getElementById('kycModal').classList.toggle('show', !!s); }
        /* ----- Status Filter Change (preserve other params) ----- */
        document.getElementById("statusFilter").onchange = function () {
            var val = this.value;
            var url = new URL(window.location.href);
            url.searchParams.set("status", val);
            url.searchParams.set("page", 1);
            window.location.href = url.toString();
        };
        /* ----- Range Filter Change (like deposit_req.php) ----- */
        var rangeSel = document.getElementById("rangeFilter");
        var startInp = document.getElementById("startDate");
        var endInp = document.getElementById("endDate");
        var applyBtn = document.getElementById("applyCustom");
        rangeSel.onchange = function () {
            var val = this.value;
            var url = new URL(window.location.href);
            url.searchParams.set("range", val);
            url.searchParams.set("page", 1);
            if (val === "custom") {
                startInp.style.display = "";
                endInp.style.display = "";
                applyBtn.style.display = "";
                return; // wait for Apply
            } else {
                url.searchParams.delete("start");
                url.searchParams.delete("end");
                window.location.href = url.toString();
            }
        };
        if (applyBtn) {
            applyBtn.onclick = function () {
                var url = new URL(window.location.href);
                url.searchParams.set("range", "custom");
                url.searchParams.set("page", 1);
                url.searchParams.set("start", startInp.value || "");
                url.searchParams.set("end", endInp.value || "");
                url.searchParams.delete("search"); // clear search to avoid messy queries
                window.location.href = url.toString();
            };
        }
        function copyKycVal(e, val) {
            if (e) e.stopPropagation();
            navigator.clipboard.writeText(val).then(function() {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500,
                        icon: 'success',
                        title: 'Copied: ' + val,
                        background: '#1c1427',
                        color: '#fff'
                    });
                } else {
                    alert('Copied: ' + val);
                }
            }).catch(function() {
                var ta = document.createElement('textarea');
                ta.value = val;
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy');
                    alert('Copied: ' + val);
                } catch(err) {}
                document.body.removeChild(ta);
            });
        }
        /* ----- Row click -> KYC Popup ----- */
        document.querySelectorAll('#reqTable tbody tr').forEach(function(tr) {
            tr.addEventListener('click', function (e) {
                if (e.target.closest('.btn') || e.target.closest('a')) return;
                var kycHtml = '';
                try { 
                    kycHtml = atob(this.dataset.kyc64 || ''); 
                } catch (err) {
                    kycHtml = 'Failed to load KYC details.';
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Payment Details',
                        html: `<div style="text-align:left; color:#fff; font-size:14px;">${kycHtml}</div>`,
                        background: '#1c1427',
                        color: '#fff',
                        confirmButtonColor: '#ffb300',
                        confirmButtonText: 'Close',
                        width: '400px'
                    });
                } else {
                    // Fallback to local HTML modal
                    var modalFields = document.getElementById('modalFields');
                    if (modalFields) {
                        modalFields.innerHTML = kycHtml;
                        modal(true);
                    }
                }
            });
        });
        /* ----- Approve/Reject AJAX (Scoped to table rows only) ----- */
        document.querySelectorAll('#reqTable tbody .approve, #reqTable tbody .reject').forEach(function(btn) {
            btn.onclick = function() {
                if (btn.disabled || !confirm('Are you sure?')) return;
                fetch('withdrawal_req.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ ajax: 1, id: btn.dataset.id, newStatus: btn.dataset.action })
                })
                    .then(r => r.json())
                    .then(function(j) {
                        if (j.ok) {
                            var row = btn.closest('tr');
                            var badge = row.querySelector('.badge');
                            badge.textContent = j.status;
                            badge.className = 'badge ' + j.status.toLowerCase();
                            row.querySelectorAll('button').forEach(b => b.disabled = true);
                            var act = row.querySelector('.action-by');
                            if (act) act.textContent = j.status.toLowerCase() === 'pending' ? '-' : (j.admin_id || FIXED_ADMIN_ID);
                            // optional refresh counts
                            setTimeout(function() { window.location.reload(); }, 350);
                        } else {
                            alert(j.error || 'Failed');
                        }
                    })
                    .catch(function() { alert('Network error'); });
            };
        });
        // Bulk Reject Logic (Vanilla JS – no jQuery dependency)
        var bulkRejectBtn = document.getElementById('bulkRejectBtn');
        if (bulkRejectBtn) {
            bulkRejectBtn.addEventListener('click', function () {
                var countLabel = "<?= (int)$hdrPend ?>";
                if (!confirm("Kya aap sach mein saari (" + countLabel + ") PENDING requests reject karna chahte hain?")) return;
                bulkRejectBtn.disabled = true;
                bulkRejectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                var urlParams = new URLSearchParams(window.location.search);
                var postData = new URLSearchParams({ action: 'reject_all_pending' });
                urlParams.forEach((v, k) => postData.set(k, v));
                fetch('withdrawal_req.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: postData.toString()
                })
                .then(r => r.json())
                .then(function(res) {
                    if (res.ok) {
                        alert(res.count + " requests reject kar di gayi hain!");
                        location.reload();
                    } else {
                        alert("Error: " + (res.error || "Kuch gadbad ho gayi"));
                        bulkRejectBtn.disabled = false;
                        bulkRejectBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Reject All Pending';
                    }
                })
                .catch(function() {
                    alert("Server Connection Error!");
                    bulkRejectBtn.disabled = false;
                    bulkRejectBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Reject All Pending';
                });
            });
        }
    </script>
</body>
</html>
<?php $db->close(); 
