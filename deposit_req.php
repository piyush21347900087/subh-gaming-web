<?php
date_default_timezone_set('Asia/Kolkata');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

/* ---------- DB CONNECT ---------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = db();
} catch (Exception $e) {
    die('DB error: ' . $e->getMessage());
}

/* ================== 1. AUTO REJECT PENDING UPI INTENT / IMB (AFTER 5 MINUTES) ================== */
$db->query("UPDATE deposit 
            SET status='Failed' 
            WHERE status='Pending' 
            AND credited = 0
            AND (method='UPI_INTENT' OR method='UPI Intent' OR method LIKE '%Intent%' OR method='IMB' OR method LIKE '%IMB%' OR method LIKE '%Pay0%' OR method LIKE '%Payo%' OR method LIKE '%YUVI%')
            AND date_time < (NOW() - INTERVAL 5 MINUTE)");

/* ================== FCM CONFIG & HELPERS ================== */
define('GOOGLE_SA_JSON', __DIR__ . '/secure/service-account.json');
define('FCM_PROJECT_ID', 'dp-boss-c92be');

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

/* ---------- Helper Functions ---------- */
function columnExists(mysqli $db, $table, $col)
{
    $table = $db->real_escape_string($table);
    $col = $db->real_escape_string($col);
    $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
    return ($res && $res->num_rows > 0);
}

function displayMethodAdmin($m)
{
    $raw = trim((string) $m);
    $low = strtolower($raw);
    if ($low === 'admin' || $low === 'admin panel')
        return 'Admin';
    if ($low === '' || $raw === '-')
        return 'Bank';
    if (strpos($low, 'imb') !== false)
        return 'IMB';
    if (strpos($low, 'upi intent') !== false || $low === 'upi_intent' || $low === 'intent')
        return 'UPI Intent';
    if (strpos($low, 'usdt') !== false || strpos($low, 'crypto') !== false)
        return 'USDT';
    if ($low === 'ref_comm')
        return 'Referral Comm';
    if ($low === 'spin_bonus')
        return 'Spin Bonus';
    if ($low === 'welcome_bonus' || $low === 'welcome bonus' || $low === 'bonus' || strpos($low, 'welcome') !== false)
        return 'Welcome Bonus';

    $map = ['bank' => 'Bank', 'upi' => 'UPI QR', 'phonepe' => 'PhonePe', 'google pay' => 'Google Pay', 'paytm' => 'Paytm', 'imb' => 'IMB'];
    return $map[$low] ?? ($low === 'upi' ? 'UPI QR' : ucfirst($raw));
}

/* ---------- Current admin ADM code ---------- */
$currentAdminCode = '';
$currentAdminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($currentAdminId > 0) {
    try {
        $stAdm = $db->prepare("SELECT admin_id FROM admin WHERE id = ? LIMIT 1");
        $stAdm->bind_param('i', $currentAdminId);
        $stAdm->execute();
        $stAdm->bind_result($currentAdminCode);
        $stAdm->fetch();
        $stAdm->close();
    } catch (Exception $e) {
        $currentAdminCode = '';
    }
}
$fixedAdminId = 'ADM0001';

$hasAcceptedByCol = columnExists($db, 'deposit', 'accepted_by');
$hasAdminIdCol = columnExists($db, 'deposit', 'admin_id');
$hasScreenshotUsdt = columnExists($db, 'deposit', 'screenshot_usdt');

/* ---------- Helper: Normalize User ID (Same as in update_token.php) ---------- */
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

    // Support multiple prefixes and pad digits to 5
    if (preg_match('/^(RK|DP|DB|DPB|KL|KLY|KB|KBT)([0-9]+)$/', $uid, $m)) {
        return $m[1] . str_pad($m[2], 5, "0", STR_PAD_LEFT);
    }
    return $uid;
}

/* ---------- AJAX STATUS UPDATE (Manual Actions) ---------- */
if (isset($_POST['ajax'], $_POST['id'], $_POST['newStatus'])) {
    $id = (int) $_POST['id'];
    $st = ($_POST['newStatus'] === 'Done') ? 'Done' : 'Failed';

    $acceptedBy = $currentAdminCode !== '' ? $currentAdminCode : $fixedAdminId;
    $adminId = $fixedAdminId;

    if ($hasAcceptedByCol && $hasAdminIdCol) {
        $q = $db->prepare("UPDATE deposit SET status=?, accepted_by=?, admin_id=? WHERE id=?");
        $q->bind_param('sssi', $st, $acceptedBy, $adminId, $id);
    } elseif ($hasAcceptedByCol) {
        $q = $db->prepare("UPDATE deposit SET status=?, accepted_by=? WHERE id=?");
        $q->bind_param('ssi', $st, $acceptedBy, $id);
    } elseif ($hasAdminIdCol) {
        $q = $db->prepare("UPDATE deposit SET status=?, admin_id=? WHERE id=?");
        $q->bind_param('ssi', $st, $adminId, $id);
    } else {
        $q = $db->prepare("UPDATE deposit SET status=? WHERE id=?");
        $q->bind_param('si', $st, $id);
    }

    $ok = $q->execute();
    $q->close();

    // --- DYNAMIC BONUS CALCULATION LINKED TO admin_bonus_rates.php ---
    if ($ok && $st === 'Done') {
        $bonusSystemEnabled = 1;
        $resSet = $db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1");
        if ($resSet && $rowSet = $resSet->fetch_assoc()) {
            $bonusSystemEnabled = (int) $rowSet['bonus_enabled'];
        }

        if ($bonusSystemEnabled === 1) {
            $getAmt = $db->query("SELECT amount FROM deposit WHERE id=$id LIMIT 1");
            if ($getAmt && $rAmt = $getAmt->fetch_assoc()) {
                $amt = (float)$rAmt['amount'];
                
                // Fetch highest matching active bonus rate from the database
                $stmtRate = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE min_amount <= ? AND is_active = 1 ORDER BY min_amount DESC LIMIT 1");
                $stmtRate->bind_param('d', $amt);
                $stmtRate->execute();
                $stmtRate->bind_result($bonus);
                if ($stmtRate->fetch()) {
                    $bonus = (float)$bonus;
                } else {
                    $bonus = 0.0;
                }
                $stmtRate->close();
                
                if ($bonus > 0) {
                    $db->query("UPDATE deposit SET bonus_amount=$bonus WHERE id=$id");
                }
            }
        }

        // Trigger Referral Commission
        $reqQRef = $db->query("SELECT user_id, amount FROM deposit WHERE id = $id LIMIT 1");
        if ($reqQRef && $reqRowRef = $reqQRef->fetch_assoc()) {
            process_referral_commission($db, (string)$reqRowRef['user_id'], (float)$reqRowRef['amount'], (int)$id);
        }
    }

    // FCM Notification for Manual Action
    if ($ok) {
        $reqQ = $db->query("SELECT user_id, amount FROM deposit WHERE id = $id LIMIT 1");
        if ($reqQ && $reqRow = $reqQ->fetch_assoc()) {
            $userId = $reqRow['user_id'];
            $amt = $reqRow['amount'];

            $fcmToken = get_latest_fcm_token($db, $userId);

            if (!empty($fcmToken)) {
                $accessToken = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
                if ($accessToken) {
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                        $dtHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $dtBase = dirname($_SERVER['PHP_SELF']);
                        $dtBase = ($dtBase === '.' || $dtBase === '/' || $dtBase === '\\') ? '' : rtrim($dtBase, '/\\');
                        $dtUrl = $protocol . $dtHost . $dtBase . '/user_wallet.php';

                        if ($st === 'Done') {
                            $payload = [
                                'type' => 'deposit_approved',
                                'title' => 'Deposit Successful',
                                'message' => "Your deposit of ₹{$amt} has been approved successfully.",
                                'amount' => (string) $amt,
                                'link_url' => $dtUrl,
                                'icon' => 'ic_notification',
                                'image' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dp-boss.fun') . '/images/logo.png',
                                'picture' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dp-boss.fun') . '/images/logo.png'
                            ];
                        } else {
                            $payload = [
                                'type' => 'notice',
                                'title' => 'Deposit Rejected',
                                'message' => "Your deposit request of ₹{$amt} was rejected by admin.",
                                'amount' => (string) $amt,
                                'link_url' => $dtUrl,
                                'icon' => 'ic_notification',
                                'image' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dp-boss.fun') . '/images/logo.png',
                                'picture' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dp-boss.fun') . '/images/logo.png'
                            ];
                        }
                    fcm_v1_send_token(FCM_PROJECT_ID, $accessToken, $payload, $fcmToken);
                }
            }
        }
    }

    echo json_encode([
        'ok' => $ok,
        'status' => $st,
        'accepted_by' => $acceptedBy,
        'admin_id' => $adminId
    ]);
    exit;
}

/* ========================= 2. SEARCH, FILTER, DATE RANGE ========================= */
$where = "1";

/* ---- Status Filter ---- */
$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
$allowed = ['all', 'pending', 'done', 'failed'];
if (!in_array($statusFilter, $allowed, true))
    $statusFilter = 'all';

if ($statusFilter === 'pending')
    $where .= " AND status='Pending'";
elseif ($statusFilter === 'done')
    $where .= " AND status='Done'";
elseif ($statusFilter === 'failed')
    $where .= " AND status='Failed'";

/* ---- Method Filter ---- */
$methodFilter = trim($_GET['method'] ?? 'all');
$methodWhere = "";
if ($methodFilter !== '' && strtolower($methodFilter) !== 'all') {
    $lowM = strtolower($methodFilter);
    if ($lowM === 'upi_intent') {
        $methodWhere = " AND (method='UPI_INTENT' OR method='UPI Intent' OR method LIKE '%intent%')";
    } elseif ($lowM === 'imb') {
        $methodWhere = " AND (method='IMB' OR method LIKE '%imb%' OR method LIKE '%IMB%')";
    } elseif ($lowM === 'usdt' || $lowM === 'crypto') {
        $methodWhere = " AND (method='CRYPTO' OR method='USDT' OR method LIKE '%usdt%' OR method LIKE '%crypto%')";
    } elseif ($lowM === 'bank') {
        $methodWhere = " AND (method LIKE '%bank%' OR method LIKE '%imb%' OR method = '' OR method IS NULL OR method = '-')";
    } elseif ($lowM === 'phonepe') {
        $methodWhere = " AND method LIKE '%phonepe%'";
    } elseif ($lowM === 'google pay' || $lowM === 'gpay') {
        $methodWhere = " AND (method LIKE '%google pay%' OR method LIKE '%gpay%')";
    } elseif ($lowM === 'paytm') {
        $methodWhere = " AND method LIKE '%paytm%'";
    } elseif ($lowM === 'upi') {
        $methodWhere = " AND (LOWER(method) = 'upi' OR (LOWER(method) LIKE '%upi%' AND LOWER(method) NOT LIKE '%intent%' AND LOWER(method) NOT LIKE '%imb%'))";
    } elseif ($lowM === 'admin') {
        $methodWhere = " AND (method = 'Admin' OR method = 'Admin Panel' OR method = 'admin panel')";
    } elseif ($lowM === 'ref_comm') {
        $methodWhere = " AND (method = 'REF_COMM' OR method LIKE '%REF%')";
    } elseif ($lowM === 'spin_bonus') {
        $methodWhere = " AND (method = 'SPIN_BONUS' OR method LIKE '%SPIN%')";
    } elseif ($lowM === 'welcome_bonus' || $lowM === 'welcome bonus' || $lowM === 'bonus') {
        $methodWhere = " AND (method = 'WELCOME_BONUS' OR method = 'WELCOME BONUS' OR method = 'BONUS' OR method LIKE '%WELCOME%' OR (method LIKE '%BONUS%' AND method NOT LIKE '%SPIN%'))";
    } else {
        $escMethod = $db->real_escape_string($methodFilter);
        $methodWhere = " AND (method = '$escMethod' OR method LIKE '%$escMethod%')";
    }
}
$where .= $methodWhere;

/* ---- Search Filter ---- */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    $escSearch = $db->real_escape_string($search);
    $where .= " AND (user_id LIKE '%$escSearch%' OR amount LIKE '%$escSearch%')";
}

/* ---- Date Range Filter ---- */
$dateRange = strtolower(trim($_REQUEST['range'] ?? 'today')); // today,7,15,30,all,custom
$allowedRanges = ['today', '7', '15', '30', 'all', 'custom'];
if (!in_array($dateRange, $allowedRanges, true))
    $dateRange = 'today';

$startDate = trim($_GET['start'] ?? '');
$endDate = trim($_GET['end'] ?? '');

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
    // Expect YYYY-MM-DD
    $okStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate);
    $okEnd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate);

    if ($okStart && $okEnd) {
        $s = $db->real_escape_string($startDate);
        $e = $db->real_escape_string($endDate);
        // inclusive end date
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

$where .= $dateWhere;

/* ========================= 3. COUNTS (Header numbers + Done Amount only) ========================= */
// Note: counts should be based on current search + date filters, but NOT locked to status filter
// So we compute on: baseWhereWithoutStatus, then show numbers for each status in one row.
$baseWhere = "1";
if ($search !== '') {
    $escSearch = $db->real_escape_string($search);
    $baseWhere .= " AND (user_id LIKE '%$escSearch%' OR amount LIKE '%$escSearch%')";
}
$baseWhere .= $methodWhere;
$baseWhere .= $dateWhere;

$sumQ = $db->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status='Done' THEN 1 ELSE 0 END) AS done_count,
        SUM(CASE WHEN status='Failed' THEN 1 ELSE 0 END) AS failed_count,
        COALESCE(SUM(CASE WHEN status='Done' THEN amount ELSE 0 END),0) AS done_amount
    FROM deposit
    WHERE $baseWhere
");
$sumRow = $sumQ ? $sumQ->fetch_assoc() : null;

$hdrTotal = (int) ($sumRow['total'] ?? 0);
$hdrPend = (int) ($sumRow['pending_count'] ?? 0);
$hdrDone = (int) ($sumRow['done_count'] ?? 0);
$hdrFail = (int) ($sumRow['failed_count'] ?? 0);
$hdrDoneAmt = (float) ($sumRow['done_amount'] ?? 0);

/* ========================= 4. PAGINATION ========================= */
$limit = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));

$resTotal = $db->query("SELECT COUNT(*) AS c FROM deposit WHERE $where");
$total = (int) $resTotal->fetch_assoc()['c'];

$pages = max(1, ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;

/* Columns */
$extraCols = '';
if ($hasAcceptedByCol)
    $extraCols .= ', accepted_by';
if ($hasAdminIdCol)
    $extraCols .= ', admin_id';
if ($hasScreenshotUsdt)
    $extraCols .= ', screenshot_usdt';

/* Fetch Data */
$rows = $db->query("
    SELECT id, user_id, amount, screenshot, method, status, date_time, (SELECT id FROM users WHERE users.user_id = deposit.user_id LIMIT 1) AS u_id
    $extraCols
    FROM deposit
    WHERE $where
    ORDER BY status='Pending' DESC, id DESC
    LIMIT $limit OFFSET $offset
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <title>Deposit Requests – Admin</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #e53935;
            --amber: #ffb300;
            --green: #43a047;
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
            text-decoration: none;
            color: inherit
        }

        .top-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            background: var(--glass);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            margin-bottom: 14px;
            border-radius: var(--radius);
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
            gap: 10px;
            margin-left: auto;
            flex-wrap: wrap
        }

        .filters-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .custom-date-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .status-filter,
        .method-filter,
        .range-filter,
        .date-input {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 14px;
            background: #fff;
            outline: none;
        }

        .search-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .search-form input {
            padding: 9px 12px;
            width: 210px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 14px;
            outline: none;
        }

        .search-form button {
            padding: 9px 14px;
            background: var(--brown);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .table-wrap {
            overflow-x: auto;
            margin-top: 10px
        }

        table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden
        }

        th,
        td {
            padding: 10px;
            font-size: 14px;
            text-align: center
        }

        th {
            background: var(--brown);
            color: #fff;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer
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

        .reject {
            background: var(--red)
        }

        .btn:disabled {
            opacity: .45;
            cursor: not-allowed
        }

        .view-img {
            color: var(--blue);
            cursor: pointer;
            text-decoration: underline;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .view-img i {
            font-size: 14px
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
            body {
                padding: 12px 10px;
            }

            .top-bar {
                flex-direction: column;
                align-items: stretch;
                padding: 14px;
                gap: 12px;
            }

            .top-btn {
                align-self: flex-start;
            }

            .top-controls {
                display: flex;
                flex-direction: column;
                width: 100%;
                gap: 10px;
                margin-left: 0;
                align-items: stretch;
            }

            .filters-row {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
                width: 100%;
            }

            .filters-row select {
                width: 100%;
                min-width: 0;
                padding: 8px 6px;
                font-size: 13px;
            }

            .custom-date-row {
                display: grid;
                grid-template-columns: 1fr 1fr auto;
                gap: 8px;
                width: 100%;
            }

            .custom-date-row input {
                width: 100%;
                min-width: 0;
            }

            .search-form {
                display: flex;
                width: 100%;
                gap: 8px;
            }

            .search-form input {
                flex: 1;
                width: 100%;
                min-width: 0;
            }

            .search-form button {
                flex-shrink: 0;
                padding: 9px 16px;
            }

            .stats-row {
                gap: 8px;
                padding: 10px;
            }

            .stat-pill {
                font-size: 12px;
                padding: 5px 8px;
            }

            table {
                min-width: 980px;
            }
        }

        @media(max-width:480px) {
            .filters-row {
                grid-template-columns: 1fr;
            }

            .custom-date-row {
                grid-template-columns: 1fr;
            }

            .custom-date-row button {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

    <div class="top-bar">
        <a href="admin_dashboard.php" class="top-btn">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>

        <div class="top-controls">
            <div class="filters-row">
                <!-- Date Range -->
                <select id="rangeFilter" class="range-filter">
                    <option value="all" <?= $dateRange === 'all' ? 'selected' : '' ?>>All Time</option>
                    <option value="today" <?= $dateRange === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="7" <?= $dateRange === '7' ? 'selected' : '' ?>>7 Days</option>
                    <option value="15" <?= $dateRange === '15' ? 'selected' : '' ?>>15 Days</option>
                    <option value="30" <?= $dateRange === '30' ? 'selected' : '' ?>>30 Days</option>
                    <option value="custom" <?= $dateRange === 'custom' ? 'selected' : '' ?>>Custom</option>
                </select>

                <!-- Status -->
                <select id="statusFilter" class="status-filter">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="done" <?= $statusFilter === 'done' ? 'selected' : '' ?>>Done</option>
                    <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>

                <!-- Method -->
                <select id="methodFilter" class="method-filter">
                    <option value="all" <?= $methodFilter === 'all' ? 'selected' : '' ?>>All Methods</option>
                    <option value="imb" <?= $methodFilter === 'imb' ? 'selected' : '' ?>>IMB</option>
                    <option value="upi" <?= $methodFilter === 'upi' ? 'selected' : '' ?>>UPI QR</option>
                    <option value="upi_intent" <?= $methodFilter === 'upi_intent' ? 'selected' : '' ?>>UPI Intent</option>
                    <option value="bank" <?= $methodFilter === 'bank' ? 'selected' : '' ?>>Bank</option>
                    <option value="usdt" <?= $methodFilter === 'usdt' ? 'selected' : '' ?>>USDT</option>
                    <option value="admin" <?= $methodFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="ref_comm" <?= $methodFilter === 'ref_comm' ? 'selected' : '' ?>>Referral Comm</option>
                    <option value="spin_bonus" <?= $methodFilter === 'spin_bonus' ? 'selected' : '' ?>>Spin Bonus</option>
                    <option value="welcome_bonus" <?= ($methodFilter === 'welcome_bonus' || $methodFilter === 'welcome bonus' || $methodFilter === 'bonus') ? 'selected' : '' ?>>Welcome Bonus</option>
                </select>
            </div>

            <!-- Custom date inputs -->
            <div id="customDateRow" class="custom-date-row" <?= $dateRange === 'custom' ? '' : 'style="display:none;"' ?>>
                <input type="date" id="startDate" class="date-input" value="<?= htmlspecialchars($startDate) ?>">
                <input type="date" id="endDate" class="date-input" value="<?= htmlspecialchars($endDate) ?>">
                <button id="applyCustom" class="top-btn" type="button" style="padding:8px 14px;">
                    <i class="fas fa-filter"></i> Apply
                </button>
            </div>

            <!-- Search -->
            <form method="GET" class="search-form">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <input type="hidden" name="method" value="<?= htmlspecialchars($methodFilter) ?>">
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
                    <th>Screenshot</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Action By</th>
                    <th>Date/Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                require_once 'includes/db.php';
                $sr = $offset;
                if ($rows && $rows->num_rows > 0):
                    while ($r = $rows->fetch_assoc()):
                        $status = strtolower($r['status']);
                        $methodDisplay = displayMethodAdmin($r['method'] ?? '');

                        /* Screenshot Logic */
                        $scrRaw = trim((string) ($r['screenshot'] ?? ''));
                        if ($scrRaw === '' && $hasScreenshotUsdt && isset($r['screenshot_usdt'])) {
                            $scrRaw = trim((string) $r['screenshot_usdt']);
                        }
                        $hasImg = ($scrRaw !== '');
                        $imgUrl = '';
                        if ($hasImg) {
                            if (preg_match('~^https?://~i', $scrRaw)) {
                                $imgUrl = $scrRaw;
                            } elseif (strpos($scrRaw, 'uploads/') === 0) {
                                $imgUrl = '/' . ltrim($scrRaw, '/');
                            } else {
                                $imgUrl = '/uploads/deposit/' . $scrRaw;
                            }
                        }

                        /* Action By Logic */
                        $adminIdDb = '';
                        if ($hasAdminIdCol && isset($r['admin_id']) && $r['admin_id'] !== '') {
                            $adminIdDb = $r['admin_id'];
                        }
                        $actionByShow = $adminIdDb !== '' ? $adminIdDb : $fixedAdminId;
                        ?>
                        <tr>
                            <td><?= ++$sr ?></td>

                            <td>
                                <a href="bio_data.php?id=<?= (int)$r['u_id'] ?>" class="user-link"
                                    title="Open Bio Data">
                                    <?= htmlspecialchars($r['user_id']) ?> <i class="fas fa-external-link-alt"
                                        style="font-size:10px;"></i>
                                </a>
                            </td>

                            <td><?= number_format((float) $r['amount'], 2) ?></td>

                            <td>
                                <?php
                                require_once 'includes/db.php';
                                if ($hasImg): ?>
                                    <a href="<?= htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                                        class="view-img">
                                        <i class="fas fa-image"></i> View
                                    </a>
                                    <div style="font-size:11px;color:#777;margin-top:2px;word-break:break-all;">
                                        <?= htmlspecialchars($scrRaw, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <?php
                                    require_once 'includes/db.php';
                                else: ?>
                                    -
                                    <?php
                                    require_once 'includes/db.php';
                                endif; ?>
                            </td>

                            <td><?= htmlspecialchars($methodDisplay) ?></td>

                            <td>
                                <?php
                                $rawMeth = strtoupper(trim((string)($r['method'] ?? '')));
                                $isImbReq = ($rawMeth === 'IMB' || strpos($rawMeth, 'IMB') !== false || $methodDisplay === 'IMB');
                                if ($isImbReq && $status === 'pending'):
                                    $createdTs = strtotime($r['date_time']);
                                    $expiryTs = $createdTs + 300; // Fixed 5 minutes from DB creation time
                                    $nowTs = time();
                                    $remSecs = max(0, $expiryTs - $nowTs);
                                    $orderIdAttr = htmlspecialchars($r['order_id'] ?? $r['transaction_id'] ?? '', ENT_QUOTES, 'UTF-8');
                                    if ($remSecs > 0):
                                        $displaySecs = min(300, $remSecs);
                                        $mins = (int)floor($displaySecs / 60);
                                        $secs = $displaySecs % 60;
                                        $initTimeStr = sprintf('%02d:%02d', $mins, $secs);
                                ?>
                                        <span class="badge pending imb-timer-wrap"
                                              data-expiry-ts="<?= $expiryTs ?>"
                                              data-order-id="<?= $orderIdAttr ?>"
                                              data-id="<?= (int)$r['id'] ?>"
                                              title="5-Minute Payment Timeout">
                                            <i class="fas fa-clock" style="font-size:11px;"></i>
                                            <span class="imb-timer-txt"><?= $initTimeStr ?></span>
                                        </span>
                                <?php else: ?>
                                        <span class="badge failed">Failed</span>
                                <?php endif;
                                else: ?>
                                    <span class="badge <?= $status ?>"><?= ucfirst($status) ?></span>
                                <?php endif; ?>
                            </td>

                            <td class="action-by">
                                <?= $status !== 'pending'
                                    ? htmlspecialchars($actionByShow)
                                    : '-' ?>
                            </td>

                            <td><?= date("d-m-Y h:i A", strtotime($r['date_time'])) ?></td>

                            <td>
                                <button class="btn approve" data-id="<?= (int) $r['id'] ?>" data-action="Done" <?= $status != 'pending' ? 'disabled' : '' ?>>
                                    <i class="fas fa-check"></i>
                                </button>

                                <button class="btn reject" data-id="<?= (int) $r['id'] ?>" data-action="Failed" <?= $status != 'pending' ? 'disabled' : '' ?>>
                                    <i class="fas fa-times"></i>
                                </button>
                            </td>
                        </tr>
                        <?php
                        require_once 'includes/db.php';
                    endwhile;
                else: ?>
                    <tr>
                        <td colspan="9">No data found</td>
                    </tr>
                    <?php
                    require_once 'includes/db.php';
                endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    require_once 'includes/db.php';
    if ($total > 0): ?>
        <div class="pagination">
            <?php
            require_once 'includes/db.php';
            $qs = "&status=" . urlencode($statusFilter)
                . "&method=" . urlencode($methodFilter)
                . "&search=" . urlencode($search)
                . "&range=" . urlencode($dateRange)
                . "&start=" . urlencode($startDate)
                . "&end=" . urlencode($endDate);
            ?>

            <?php
            require_once 'includes/db.php';
            if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= $qs ?>">&laquo; Prev</a>
                <?php
                require_once 'includes/db.php';
            else: ?>
                <span style="opacity:0.5; cursor:not-allowed;">&laquo; Prev</span>
                <?php
                require_once 'includes/db.php';
            endif; ?>

            <span class="info">Page <?= $page ?> of <?= $pages ?> (Total <?= $total ?>)</span>

            <?php
            require_once 'includes/db.php';
            if ($page < $pages): ?>
                <a href="?page=<?= $page + 1 ?><?= $qs ?>">Next &raquo;</a>
                <?php
                require_once 'includes/db.php';
            else: ?>
                <span style="opacity:0.5; cursor:not-allowed;">Next &raquo;</span>
                <?php
                require_once 'includes/db.php';
            endif; ?>
        </div>
        <?php
        require_once 'includes/db.php';
    endif; ?>

    <script>
        var FIXED_ADMIN_ID = "<?= htmlspecialchars($fixedAdminId, ENT_QUOTES, 'UTF-8') ?>";

        // Status Filter Change
        document.getElementById("statusFilter").onchange = function () {
            var val = this.value;
            var url = new URL(window.location.href);
            url.searchParams.set("status", val);
            url.searchParams.set("page", 1);
            window.location.href = url.toString();
        };

        // Method Filter Change
        var methodFilterElem = document.getElementById("methodFilter");
        if (methodFilterElem) {
            methodFilterElem.onchange = function () {
                var val = this.value;
                var url = new URL(window.location.href);
                url.searchParams.set("method", val);
                url.searchParams.set("page", 1);
                window.location.href = url.toString();
            };
        }

        // Range Filter Change
        var rangeSel = document.getElementById("rangeFilter");
        var customRow = document.getElementById("customDateRow");
        var startInp = document.getElementById("startDate");
        var endInp = document.getElementById("endDate");
        var applyBtn = document.getElementById("applyCustom");

        rangeSel.onchange = function () {
            var val = this.value;
            var url = new URL(window.location.href);
            url.searchParams.set("range", val);
            url.searchParams.set("page", 1);

            if (val === "custom") {
                if (customRow) customRow.style.display = "";
                else {
                    startInp.style.display = "";
                    endInp.style.display = "";
                    applyBtn.style.display = "";
                }
                // don't redirect yet (wait for Apply)
                return;
            } else {
                if (customRow) customRow.style.display = "none";
                // reset custom dates
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
                window.location.href = url.toString();
            };
        }

        // Approve/Reject Action
        document.querySelectorAll(".approve,.reject").forEach(function(btn) {
            btn.onclick = function() {
                if (btn.disabled) return;
                if (!confirm("Are you sure?")) return;

                fetch("deposit_req.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({
                        ajax: 1,
                        id: btn.dataset.id,
                        newStatus: btn.dataset.action
                    })
                })
                    .then(r => r.json())
                    .then(function(j) {
                        if (j.ok) {
                            var row = btn.closest("tr");
                            var badge = row.querySelector(".badge");
                            var newStatus = j.status.toLowerCase();

                            badge.textContent = j.status;
                            badge.className = "badge " + newStatus;

                            var actCell = row.querySelector(".action-by");
                            if (actCell) {
                                actCell.textContent = (newStatus === 'pending')
                                    ? '-'
                                    : (j.admin_id || FIXED_ADMIN_ID);
                            }

                            row.querySelectorAll("button").forEach(b => b.disabled = true);

                            // optional: refresh counts automatically
                            setTimeout(function() { window.location.reload(); }, 350);
                        }
                    });
            };
        });

        var SERVER_NOW = <?= time() ?>;
        var CLIENT_START = Math.floor(Date.now() / 1000);

        // ✅ IMB 5-Minute Live Countdown Timer (Bound to fixed DB creation time)
        function tickImbTimers() {
            var clientNow = Math.floor(Date.now() / 1000);
            var currentServerTime = SERVER_NOW + (clientNow - CLIENT_START);

            document.querySelectorAll('.imb-timer-wrap').forEach(function(wrap) {
                if (wrap.dataset.done === "1") return;

                var expiryTs = parseInt(wrap.getAttribute('data-expiry-ts') || 0, 10);
                var remaining = Math.min(300, expiryTs - currentServerTime);

                if (remaining > 0) {
                    var mins = Math.floor(remaining / 60);
                    var secs = remaining % 60;
                    var timeStr = (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
                    var txtElem = wrap.querySelector('.imb-timer-txt');
                    if (txtElem) txtElem.textContent = timeStr;
                } else {
                    // 5 Minutes expired without payment completion -> Mark Failed
                    wrap.dataset.done = "1";
                    wrap.className = 'badge failed';
                    wrap.style = '';
                    wrap.innerHTML = 'Failed';

                    // Disable buttons in same row
                    var row = wrap.closest('tr');
                    if (row) {
                        row.querySelectorAll('.btn').forEach(function(b) { b.disabled = true; });
                        var actBy = row.querySelector('.action-by');
                        if (actBy) actBy.textContent = '-';
                    }
                }
            });
        }

        // Poll status for active IMB timers every 4 seconds
        function pollImbStatus() {
            document.querySelectorAll('.imb-timer-wrap').forEach(function(wrap) {
                if (wrap.dataset.done === "1") return;
                var orderId = wrap.getAttribute('data-order-id');
                if (!orderId) return;

                fetch('api_check_status.php?order_id=' + encodeURIComponent(orderId))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data && data.ok && data.status === 'SUCCESS') {
                            // Payment Done -> Show Normal Success Badge immediately
                            wrap.dataset.done = "1";
                            wrap.className = 'badge done';
                            wrap.style = '';
                            wrap.innerHTML = 'Done';

                            var row = wrap.closest('tr');
                            if (row) {
                                row.querySelectorAll('.btn').forEach(function(b) { b.disabled = true; });
                                var actBy = row.querySelector('.action-by');
                                if (actBy) actBy.textContent = FIXED_ADMIN_ID || 'System';
                            }
                        } else if (data && data.ok && data.status === 'FAILED') {
                            // Payment explicitly Failed -> Show Failed Badge immediately
                            wrap.dataset.done = "1";
                            wrap.className = 'badge failed';
                            wrap.style = '';
                            wrap.innerHTML = 'Failed';

                            var row = wrap.closest('tr');
                            if (row) {
                                row.querySelectorAll('.btn').forEach(function(b) { b.disabled = true; });
                            }
                        }
                    })
                    .catch(function(e) {});
            });
        }

        setInterval(tickImbTimers, 1000);
        setInterval(pollImbStatus, 4000);
        tickImbTimers();
    </script>

</body>

</html>
<?php
require_once 'includes/db.php';
$db->close(); 
