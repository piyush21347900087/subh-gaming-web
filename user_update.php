<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

/* ========= MASTER CHECK (robust) ========= */
if (!function_exists('is_master_admin')) {
function is_master_admin(): bool
{
    if (!isset($_SESSION['admin']))
        return false;

    $a = $_SESSION['admin'];

    // If stored as array
    if (is_array($a)) {
        $candidates = [];
        foreach (['role', 'admin_role', 'type', 'admin_type', 'level', 'user_type'] as $k) {
            if (isset($a[$k]))
                $candidates[] = (string) $a[$k];
        }
        foreach (['is_master', 'master', 'isMaster'] as $k) {
            if (isset($a[$k]) && (int) $a[$k] === 1)
                return true;
        }
        foreach ($candidates as $v) {
            $v = strtolower(trim($v));
            if ($v === 'master' || $v === 'master_admin' || $v === 'super' || $v === 'superadmin' || $v === 'super_admin') {
                return true;
            }
        }
    }

    // If stored as string
    if (is_string($a)) {
        $v = strtolower(trim($a));
        if ($v === 'master' || $v === 'master_admin' || $v === 'super' || $v === 'superadmin' || $v === 'super_admin') {
            return true;
        }
    }

    // Some projects store role separately
    foreach (['admin_role', 'role', 'user_type'] as $k) {
        if (isset($_SESSION[$k])) {
            $v = strtolower(trim((string) $_SESSION[$k]));
            if ($v === 'master' || $v === 'master_admin' || $v === 'super' || $v === 'superadmin' || $v === 'super_admin') {
                return true;
            }
        }
    }

    return false;
}
}
$isMaster = is_master_admin();

/* ========= CSRF ========= */
if (empty($_SESSION['csrf_user_update'])) {
    $_SESSION['csrf_user_update'] = bin2hex(random_bytes(24));
}
$csrfToken = $_SESSION['csrf_user_update'];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = db();
} catch (Exception $e) {
    die('DB error: ' . $e->getMessage());
}

// Check for bonus_amount column
$hasBonusCol = false;
try {
    $resB = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
    if ($resB && $resB->num_rows > 0) {
        $hasBonusCol = true;
    }
} catch (Throwable $e) {
}

$totalBonusSum = 0.0;
$totalBalancePendingSum = 0.0;


/* ================== DELETE USER (MASTER ONLY) ================== */
$flash = null;

if (!function_exists('db_name')) {
function db_name(mysqli $db): string
{
    $row = $db->query("SELECT DATABASE() AS d")->fetch_assoc();
    return (string) ($row['d'] ?? '');
}
}

if (!function_exists('table_exists')) {
function table_exists(mysqli $db, string $table): bool
{
    try {
        $t = $db->real_escape_string($table);
        $res = $db->query("SHOW TABLES LIKE '{$t}'");
        return ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        return false;
    }
}
}

if (!function_exists('column_exists')) {
function column_exists(mysqli $db, string $table, string $column): bool
{
    try {
        $st = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        if (!$st)
            return false;
        $st->bind_param('s', $column);
        $st->execute();
        $res = $st->get_result();
        $ok = $res && $res->num_rows > 0;
        $st->close();
        return $ok;
    } catch (Throwable $e) {
        return false;
    }
}
}

if (!function_exists('user_total_balance_sql')) {
function user_total_balance_sql(mysqli $db, bool $hasBonusCol): string
{
    $bonus = $hasBonusCol
        ? "(SELECT IFNULL(SUM(bonus_amount),0) FROM deposit WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND LOWER(status) IN ('pending','approved','done') AND (method IS NULL OR UPPER(method) <> 'REFUND'))"
        : "0";

    $mainWins = table_exists($db, 'wining')
        ? "(SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci))"
        : "0";

    $extraWins = [];
    foreach (['wining3', 'wining4'] as $table) {
        if (table_exists($db, $table)) {
            $safe = str_replace('`', '', $table);
            $extraWins[] = "(SELECT IFNULL(SUM(wining_balance),0) FROM `{$safe}` WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND LOWER(status)='win')";
        }
    }
    $extraWinSql = $extraWins ? implode(' + ', $extraWins) : "0";

    $bids = [];
    if (table_exists($db, 'bid')) {
        $refundFilter = table_exists($db, 'bid_refunds')
            ? "AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = b.id AND br.source_table IN ('bid','bids'))"
            : "";
        $bids[] = "(SELECT IFNULL(SUM(amount),0) FROM bid b WHERE (b.user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR b.user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) {$refundFilter})";
    }
    if (table_exists($db, 'gali_bids')) {
        $refundFilter = table_exists($db, 'bid_refunds')
            ? "AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = g.id AND br.source_table IN ('gali_bid','gali_bids'))"
            : "";
        $bids[] = "(SELECT IFNULL(SUM(amount),0) FROM gali_bids g WHERE (g.user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR g.user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) {$refundFilter})";
    }
    foreach (['bid3', 'bid4'] as $table) {
        if (table_exists($db, $table)) {
            $safe = str_replace('`', '', $table);
            $cancelFilter = column_exists($db, $table, 'is_cancelled') ? "AND COALESCE(b.is_cancelled,0)=0" : "";
            $bids[] = "(SELECT IFNULL(SUM(amount),0) FROM `{$safe}` b WHERE (b.user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR b.user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) {$cancelFilter})";
        }
    }
    $bidSql = $bids ? implode(' + ', $bids) : "0";

    return "GREATEST(0.00, (
        (SELECT IFNULL(SUM(amount),0) FROM deposit WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND LOWER(status) IN ('pending','approved','done') AND (method IS NULL OR UPPER(method) <> 'REFUND'))
        + {$bonus}
        + {$mainWins}
        + {$extraWinSql}
        - (SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND LOWER(status) IN ('pending','approved','done') AND (method IS NULL OR (method NOT LIKE '%bid%' AND LOWER(method) <> 'game_bid')))
        - ({$bidSql})
    ))";
}
}

if (!function_exists('delete_user_everywhere_by_user_id')) {
function delete_user_everywhere_by_user_id(mysqli $db, string $userId): array
{
    $deletedTables = [];
    $totalDeleted = 0;

    $schema = db_name($db);
    if ($schema === '') {
        return ['ok' => false, 'msg' => 'Database not selected.'];
    }

    $userId = trim($userId);
    if ($userId === '')
        return ['ok' => false, 'msg' => 'User ID missing.'];

    $userIdEsc = $db->real_escape_string($userId);

    $userDbId = null;
    if (table_exists($db, 'users')) {
        $row = $db->query("SELECT `id` FROM `users` WHERE `user_id` = '{$userIdEsc}' LIMIT 1")->fetch_assoc();
        if ($row && isset($row['id']))
            $userDbId = (int) $row['id'];
    }

    $tablesUserId = [];
    $st = $db->prepare("
        SELECT TABLE_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND COLUMN_NAME = 'user_id'
    ");
    $st->bind_param("s", $schema);
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        if (!empty($r['TABLE_NAME']))
            $tablesUserId[] = (string) $r['TABLE_NAME'];
    }
    $st->close();

    $tablesUserDbId = [];
    $st2 = $db->prepare("
        SELECT TABLE_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND COLUMN_NAME = 'user_db_id'
    ");
    $st2->bind_param("s", $schema);
    $st2->execute();
    $rs2 = $st2->get_result();
    while ($r = $rs2->fetch_assoc()) {
        if (!empty($r['TABLE_NAME']))
            $tablesUserDbId[] = (string) $r['TABLE_NAME'];
    }
    $st2->close();

    $tablesUserId = array_values(array_unique($tablesUserId));
    $tablesUserDbId = array_values(array_unique($tablesUserDbId));

    if (!$tablesUserId && !$tablesUserDbId) {
        if (table_exists($db, 'users')) {
            $db->query("DELETE FROM `users` WHERE `user_id` = '{$userIdEsc}'");
            $totalDeleted += (int) $db->affected_rows;
            $deletedTables[] = 'users';
        }
        return [
            'ok' => true,
            'msg' => "Deleted (no matching columns found except maybe users).",
            'tables' => $deletedTables,
            'rows' => $totalDeleted
        ];
    }

    $db->begin_transaction();
    try {
        $db->query("SET FOREIGN_KEY_CHECKS=0");
        foreach ($tablesUserId as $t) {
            if (strtolower($t) === 'users')
                continue;
            $tSafe = str_replace("`", "``", $t);
            $db->query("DELETE FROM `{$tSafe}` WHERE `user_id` = '{$userIdEsc}'");
            $aff = (int) $db->affected_rows;
            if ($aff > 0) {
                $deletedTables[] = $t . " (user_id)";
                $totalDeleted += $aff;
            }
        }

        if ($userDbId !== null) {
            foreach ($tablesUserDbId as $t) {
                if (strtolower($t) === 'users')
                    continue;
                $tSafe = str_replace("`", "``", $t);
                $db->query("DELETE FROM `{$tSafe}` WHERE `user_db_id` = " . (int) $userDbId);
                $aff = (int) $db->affected_rows;
                if ($aff > 0) {
                    $deletedTables[] = $t . " (user_db_id)";
                    $totalDeleted += $aff;
                }
            }
        }

        if (table_exists($db, 'users')) {
            $db->query("DELETE FROM `users` WHERE `user_id` = '{$userIdEsc}'");
            $affU = (int) $db->affected_rows;
            if ($affU > 0) {
                $deletedTables[] = "users";
                $totalDeleted += $affU;
            }
        }

        $db->query("SET FOREIGN_KEY_CHECKS=1");
        $db->commit();

        return [
            'ok' => true,
            'msg' => "User deleted from database (all matching tables).",
            'tables' => $deletedTables,
            'rows' => $totalDeleted
        ];
    } catch (Throwable $e) {
        try {
            $db->query("SET FOREIGN_KEY_CHECKS=1");
        } catch (Throwable $x) {
        }
        $db->rollback();
        return ['ok' => false, 'msg' => "Delete failed: " . $e->getMessage()];
    }
}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    if (!$isMaster) {
        $flash = "Only MASTER can delete users.";
    } else {
        $tok = (string) ($_POST['csrf'] ?? '');
        if (!hash_equals($csrfToken, $tok)) {
            $flash = "Security token mismatch.";
        } else {
            $userId = trim((string) ($_POST['user_id'] ?? ''));
            if ($userId === '') {
                $flash = "User ID missing.";
            } else {
                $res = delete_user_everywhere_by_user_id($db, $userId);
                $flash = $res['ok']
                    ? ("✅ " . $res['msg'] . " (Rows: " . (int) ($res['rows'] ?? 0) . ")")
                    : ("❌ " . $res['msg']);
            }
        }
    }

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => (strpos((string) $flash, "✅") === 0), 'message' => $flash]);
        $db->close();
        exit;
    }

    $q = $_GET['q'] ?? '';
    $status = $_GET['status'] ?? 'all';
    $sort = $_GET['sort'] ?? 'new';
    $page = $_GET['page'] ?? 1;
    header("Location: user_update.php?page=" . urlencode((string) $page) . "&status=" . urlencode((string) $status) . "&sort=" . urlencode((string) $sort) . "&q=" . urlencode((string) $q) . "&msg=" . urlencode((string) $flash));
    $db->close();
    exit;
}

/* ================== SEARCH + PAGINATION + STATUS FILTER ================== */

$limit = 10;
$search = trim($_GET['q'] ?? '');
$hasSearch = ($search !== '');
$status = $_GET['status'] ?? 'all';
$allowed = ['all', 'live', 'offline'];
if (!in_array($status, $allowed, true))
    $status = 'all';

$condStatus = '';
if ($status === 'live') {
    $condStatus = " AND last_seen >= NOW() - INTERVAL 3 MINUTE";
} elseif ($status === 'offline') {
    $condStatus = " AND (last_seen < NOW() - INTERVAL 3 MINUTE OR last_seen IS NULL)";
}

// Fast aggregated sums for header metrics
try {
    $balSql = user_total_balance_sql($db, $hasBonusCol);
    $sumBalRow = $db->query("SELECT IFNULL(SUM($balSql), 0) AS total_balance_sum FROM users u WHERE 1 {$condStatus}")->fetch_assoc();
    $totalBalancePendingSum = (float) ($sumBalRow['total_balance_sum'] ?? 0);

    if ($hasBonusCol) {
        $sumBonusRow = $db->query("SELECT IFNULL(SUM(bonus_amount), 0) AS total_bonus FROM deposit WHERE LOWER(status)='done' AND (method IS NULL OR UPPER(method) <> 'REFUND')")->fetch_assoc();
        $totalBonusSum = (float) ($sumBonusRow['total_bonus'] ?? 0);
    } else {
        $totalBonusSum = 0.0;
    }
} catch (Throwable $e) {
    $totalBalancePendingSum = 0.0;
    $totalBonusSum = 0.0;
}

if ($hasSearch) {
    $like = "%{$search}%";
    $sqlCount = "
        SELECT COUNT(*) AS c
        FROM users
        WHERE (user_id LIKE ? OR name LIKE ? OR mobile_no LIKE ?)
        $condStatus
    ";
    $stmtCount = $db->prepare($sqlCount);
    $stmtCount->bind_param('sss', $like, $like, $like);
    $stmtCount->execute();
    $stmtCount->bind_result($total);
    $stmtCount->fetch();
    $stmtCount->close();
} else {
    $sqlCountAll = "SELECT COUNT(*) AS c FROM users WHERE 1 $condStatus";
    $totalRow = $db->query($sqlCountAll)->fetch_assoc();
    $total = (int) ($totalRow['c'] ?? 0);
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$pages = max(1, (int) ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;

$sort = $_GET['sort'] ?? 'new';
$allowedSort = ['new', 'big'];
if (!in_array($sort, $allowedSort, true))
    $sort = 'new';

$orderSql = ($sort === 'big') ? "ORDER BY total_balance DESC, u.id DESC" : "ORDER BY u.id DESC";
$forceIndex = ""; // Cannot force index on dynamically computed balance

if ($hasSearch) {
    $like = "%{$search}%";
    $sqlList = "
        SELECT u.id, u.user_id, u.name, u.mobile_no, u.last_seen,
               (u.last_seen >= NOW() - INTERVAL 3 MINUTE) AS is_live,
               $balSql AS total_balance,
               0 AS total_bonus
        FROM users u $forceIndex
        WHERE (u.user_id LIKE ? OR u.name LIKE ? OR u.mobile_no LIKE ?)
        $condStatus
        $orderSql
        LIMIT ? OFFSET ?
    ";
    $stmtList = $db->prepare($sqlList);
    if (!$stmtList)
        die("Prepare failed: " . $db->error);
    $stmtList->bind_param('sssii', $like, $like, $like, $limit, $offset);
} else {
    $sqlList = "
        SELECT u.id, u.user_id, u.name, u.mobile_no, u.last_seen,
               (u.last_seen >= NOW() - INTERVAL 3 MINUTE) AS is_live,
               $balSql AS total_balance,
               0 AS total_bonus
        FROM users u $forceIndex
        WHERE 1
        $condStatus
        $orderSql
        LIMIT ? OFFSET ?
    ";
    $stmtList = $db->prepare($sqlList);
    if (!$stmtList)
        die("Prepare failed: " . $db->error);
    $stmtList->bind_param('ii', $limit, $offset);
}
$stmtList->execute();
$rows = $stmtList->get_result();
$stmtList->close();

if (!$flash && isset($_GET['msg']) && $_GET['msg'] !== '') {
    $flash = (string) $_GET['msg'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Users – Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --red: #e53935;
            --green: #2e7d32;
            --amber: #ffb300;
            --brown: #4e342e;
            --blue: #0277bd;
            --bg: #f3f4f7;
            --glass: rgba(255, 255, 255, .78);
            --radius: 12px;
            --shadow: 0 4px 18px rgba(0, 0, 0, .08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Poppins, Arial, sans-serif
        }

        body {
            background: var(--bg);
            min-height: 100vh;
            padding: 24px 18px
        }

        a {
            color: inherit;
            text-decoration: none
        }

        .toast {
            margin: 0 0 14px 0;
            padding: 10px 14px;
            border-radius: 12px;
            background: rgba(255, 255, 255, .92);
            border: 1px solid rgba(78, 52, 46, .18);
            box-shadow: var(--shadow);
            font-size: 13px;
        }

        .top-bar {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 12px 20px;
            border-radius: 16px;
            backdrop-filter: blur(14px);
            background: #ffffff;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .05);
        }

        .top-left {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .back-btn-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #4e342e;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 16px;
            transition: transform 0.15s ease, background 0.15s ease;
            box-shadow: 0 2px 6px rgba(78, 52, 46, 0.3);
            flex-shrink: 0;
        }

        .back-btn-circle:hover {
            transform: scale(1.05);
            background: #3e2723;
        }

        .metrics-pills {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .metric-pill {
            padding: 7px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: none;
            transition: all 0.2s ease;
        }

        .metric-pill:hover {
            transform: translateY(-1px);
        }

        .wallet-pill {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .bonus-pill {
            background: #fff3e0;
            color: #e65100;
            border: 1px solid #ffcc80;
        }

        .total-pill {
            background: #e1f5fe;
            color: #0277bd;
            border: 1px solid #81d4fa;
        }

        .top-right {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-left: auto;
        }

        .pill-group {
            display: inline-flex;
            align-items: center;
            background: #ffffff;
            border-radius: 999px;
            padding: 3px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            gap: 2px;
        }

        .pill-btn, .sort-btn {
            border: none;
            outline: none;
            background: transparent;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            color: #555555;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all .18s ease-out;
        }

        .pill-btn:hover, .sort-btn:hover {
            color: #222222;
            background: rgba(0,0,0,0.04);
        }

        .pill-btn.active, .sort-btn.active {
            background: #4e342e;
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 3px 8px rgba(78, 52, 46, .3);
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot-all, .dot-new {
            background: #ffffff;
        }

        .dot-live, .dot-big {
            background: #2e7d32;
        }

        .dot-off {
            background: #9e9e9e;
        }

        .pill-btn.active .dot-all,
        .sort-btn.active .dot-new {
            background: #ffffff;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, .3);
        }

        .top-bar .search {
            text-align: right;
        }

        .top-bar input {
            width: 220px;
            max-width: 100%;
            padding: 8px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 999px;
            font-size: 14px;
            background: #fafafa;
            outline: none;
            transition: border-color 0.2s ease, background 0.2s ease;
        }

        .top-bar input:focus {
            border-color: #4e342e;
            background: #ffffff;
        }

        .status-chip.active {
            background: linear-gradient(135deg, #5d4037, #8d6e63);
            color: #fff;
            box-shadow: 0 3px 10px rgba(93, 64, 55, .35);
        }

        .status-chip.active .dot {
            box-shadow: 0 0 0 3px rgba(255, 255, 255, .4);
        }

        .table-wrap {
            overflow-x: auto;
            margin-top: 10px;
        }

        table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse
        }

        th,
        td {
            padding: 10px 8px;
            text-align: center;
            font-size: 14px;
        }

        th {
            background: var(--brown);
            color: #fff;
            font-weight: 600;
        }

        tbody tr:nth-child(even) {
            background: #fafafa
        }

        .view-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid var(--blue);
            font-size: 13px;
            color: var(--blue);
            margin-right: 8px;
        }

        .view-link i {
            font-size: 14px
        }

        .delete-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid rgba(229, 57, 53, .75);
            background: rgba(229, 57, 53, .08);
            color: var(--red);
            font-size: 13px;
            cursor: pointer;
        }

        .delete-btn i {
            font-size: 14px
        }

        .delete-btn:hover {
            background: rgba(229, 57, 53, .14)
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            font-size: 14px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 16px;
            border-radius: 8px;
            background: #e0d7ce;
            color: #333;
            text-decoration: none;
            font-weight: 600;
            min-width: 100px;
            text-align: center;
        }

        .pagination .disabled {
            background: #e0e0e0;
            color: #9e9e9e;
            cursor: default;
            pointer-events: none;
        }

        .pagination .info {
            background: transparent;
            padding: 0;
            color: var(--brown);
            font-weight: 700;
            min-width: auto;
        }

        .live-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .2px;
        }

        .live-pill .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }

        .live-pill.live {
            background: rgba(46, 125, 50, .10);
            color: var(--green);
            border: 1px solid rgba(46, 125, 50, .45);
        }

        .live-pill.live .dot {
            background: var(--green);
            box-shadow: 0 0 0 4px rgba(46, 125, 50, .22);
        }

        .live-pill.off {
            background: rgba(158, 158, 158, .10);
            color: #757575;
            border: 1px solid rgba(158, 158, 158, .40);
        }

        .live-pill.off .dot {
            background: #9e9e9e;
        }

        #loadingBar {
            position: fixed;
            left: 0;
            top: 0;
            height: 3px;
            width: 0;
            background: var(--brown);
            z-index: 9999;
            transition: width .25s ease-out, opacity .25s ease-out;
            opacity: 0;
        }

        @media(max-width:650px) {
            body {
                padding: 18px 10px
            }

            .top-bar {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .top-bar h1 {
                position: static;
                transform: none;
                order: -1;
                margin-bottom: 4px;
            }

            .top-right {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 8px;
            }

            .search {
                flex: 1
            }

            .top-bar input {
                width: 100%;
            }

            table {
                min-width: 0
            }
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

    <div id="loadingBar"></div>

    <?php if ($flash): ?>
        <div class="toast"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="top-bar">
        <div class="top-left">
            <a href="admin_dashboard.php" class="back-btn-circle" title="Dashboard">
                <i class="fas fa-arrow-left"></i>
            </a>

            <div class="metrics-pills">
                <span class="metric-pill wallet-pill">
                    <i class="fas fa-wallet"></i> Total: ₹<?php echo number_format($totalBalancePendingSum, 2); ?>
                </span>
                <span class="metric-pill bonus-pill">
                    <i class="fas fa-gift"></i> Bonus: ₹<?php echo number_format($totalBonusSum, 2); ?>
                </span>
                <span class="metric-pill total-pill">
                    <i class="fas fa-coins"></i> Total: ₹<?php echo number_format($totalBalancePendingSum, 2); ?>
                </span>
            </div>
        </div>

        <div class="top-right">
            <!-- Group 1: Live / Offline Status -->
            <div class="pill-group" id="statusGroup">
                <button type="button" class="pill-btn <?php if ($status === 'all') echo 'active'; ?>" data-status="all">
                    <span class="dot dot-all"></span> All
                </button>
                <button type="button" class="pill-btn <?php if ($status === 'live') echo 'active'; ?>" data-status="live">
                    <span class="dot dot-live"></span> Live
                </button>
                <button type="button" class="pill-btn <?php if ($status === 'offline') echo 'active'; ?>" data-status="offline">
                    <span class="dot dot-off"></span> Offline
                </button>
            </div>

            <!-- Group 2: New / Big Sort -->
            <div class="pill-group" id="sortGroup">
                <button type="button" class="sort-btn <?php if ($sort === 'new') echo 'active'; ?>" data-sort="new">
                    <span class="dot dot-new"></span> New
                </button>
                <button type="button" class="sort-btn <?php if ($sort === 'big') echo 'active'; ?>" data-sort="big">
                    <span class="dot dot-big"></span> Big
                </button>
            </div>

            <div class="search">
                <form id="searchForm" method="get" action="">
                    <input id="searchBox" name="q" type="text" placeholder="Search by User ID, Name or Mobile…"
                        value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                </form>
            </div>
        </div>
    </div>

    <div class="table-wrap">
        <table id="userTable">
            <thead>
                <tr>
                    <th>Sr No</th>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th style="width: 50px; text-align: center;">Chat</th>
                    <th>Total Balance</th>
                    <th>Bonus Amount</th>
                    <th>Live Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows && $rows->num_rows): ?>
                    <?php
                    $sr = $offset + 1;
                    while ($u = $rows->fetch_assoc()):
                        $u['total_balance'] = bio_total_balance($db, (int) ($u['id'] ?? 0), trim((string) ($u['user_id'] ?? '')));
                        $uidRaw = (string) ($u['user_id'] ?? '');
                        $uidShow = htmlspecialchars($uidRaw, ENT_QUOTES, 'UTF-8');
                        $name = htmlspecialchars((string) ($u['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $mob = htmlspecialchars((string) ($u['mobile_no'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $isLive = (int) ($u['is_live'] ?? 0);
                        $liveCls = $isLive ? 'live' : 'off';
                        $liveTxt = $isLive ? 'Live' : 'Offline';
                        ?>
                        <tr>
                            <td><?php echo $sr++ ?></td>
                            <td><?php echo $uidShow ?></td>
                            <td><?php echo $name ?></td>
                            <td>
                                <?php if (!empty($u['mobile_no'])): ?>
                                    <a href="https://wa.me/91<?php echo preg_replace('/\D/', '', $u['mobile_no']) ?>"
                                        target="_blank" title="WhatsApp पर Chat करें"
                                        style="color:#25D366;font-weight:600;text-decoration:none;transition:color .15s;"
                                        onmouseover="this.style.color='#128C7E'" onmouseout="this.style.color='#25D366'">
                                        <?php echo $mob ?>
                                    </a>
                                    <a href="https://wa.me/91<?php echo preg_replace('/\D/', '', $u['mobile_no']) ?>"
                                        target="_blank" title="WhatsApp पर Chat करें"
                                        style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:#25D366;color:#fff;font-size:13px;margin-left:4px;vertical-align:middle;text-decoration:none;transition:transform .15s,box-shadow .15s;box-shadow:0 2px 6px rgba(37,211,102,.4);flex-shrink:0;"
                                        onmouseover="this.style.transform='scale(1.15)';this.style.boxShadow='0 4px 12px rgba(37,211,102,.6)'"
                                        onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 2px 6px rgba(37,211,102,.4)'">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                <?php else: ?>
                                    <?php echo $mob ?: '-' ?>
                                <?php endif; ?>
                            </td>
                            <td style="width: 50px; text-align: center; padding: 4px;">
                                <?php if (!empty($uidRaw)): ?>
                                    <a href="admin_live_chat.php?uid=<?php echo urlencode($uidRaw); ?>"
                                        title="Live Chat (<?php echo $uidShow; ?>)"
                                        style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg, #e53935, #b71c1c);color:#fff;font-size:13px;text-decoration:none;transition:transform .15s,box-shadow .15s;box-shadow:0 2px 6px rgba(229,57,53,.35);"
                                        onmouseover="this.style.transform='scale(1.15)';this.style.boxShadow='0 4px 12px rgba(229,57,53,.6)'"
                                        onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 2px 6px rgba(229,57,53,.35)'">
                                        <i class="fas fa-comment-dots"></i>
                                    </a>
                                <?php else: ?>
                                    <span style="color:#bbb;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight:700; color:#2e7d32; background:rgba(46,125,50,0.05);">
                                ₹<?php echo number_format((float) ($u['total_balance'] ?? 0), 2) ?></td>
                            <td style="font-weight:700; color:#ffb300; background:rgba(255,179,0,0.05);">
                                ₹<?php echo number_format((float) ($u['total_bonus'] ?? 0), 2) ?></td>
                            <td>
                                <span class="live-pill <?php echo $liveCls ?>">
                                    <span class="dot"></span>
                                    <?php echo $liveTxt ?>
                                </span>
                            </td>
                            <td>
                                <a class="view-link" href="bio_data.php?id=<?php echo (int) $u['id'] ?>">
                                    <i class="fas fa-eye"></i> View
                                </a>

                                <?php if ($isMaster): ?>
                                    <form class="deleteForm" method="post" action="user_update.php" style="display:inline">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="csrf"
                                            value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="user_id"
                                            value="<?php echo htmlspecialchars($uidRaw, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="delete-btn">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages >= 1): ?>
        <div class="pagination">
            <?php
            $queryExtra = '';
            if ($hasSearch)
                $queryExtra .= '&q=' . urlencode($search);
            if ($status !== 'all')
                $queryExtra .= '&status=' . urlencode($status);
            ?>

            <?php if ($page > 1): ?>
                <a href="?page=<?php echo ($page - 1) . $queryExtra ?>">&laquo; Previous</a>
            <?php else: ?>
                <span class="disabled">&laquo; Previous</span>
            <?php endif; ?>

            <span class="info">Page <?php echo (int) $page ?> of <?php echo (int) $pages ?></span>

            <?php if ($page < $pages): ?>
                <a href="?page=<?php echo ($page + 1) . $queryExtra ?>">Next &raquo;</a>
            <?php else: ?>
                <span class="disabled">Next &raquo;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script>
        const searchBox = document.getElementById('searchBox');
        const searchForm = document.getElementById('searchForm');
        const loadingBar = document.getElementById('loadingBar');
        const statusChips = document.querySelectorAll('.status-chip');

        let searchTimer = null;
        let lastQuery = searchBox.value.trim();
        let currentStatus = (document.querySelector('.pill-btn.active')?.dataset.status) || 'all';
        let currentSort = (document.querySelector('.sort-btn.active')?.dataset.sort) || 'new';

        function showLoading() {
            loadingBar.style.opacity = '1';
            loadingBar.style.width = '60%';
        }
        function hideLoading() {
            loadingBar.style.width = '100%';
            setTimeout(() => {
                loadingBar.style.opacity = '0';
                loadingBar.style.width = '0';
            }, 200);
        }

        function buildParams(page = 1) {
            const q = searchBox.value.trim();
            const params = new URLSearchParams();
            if (q !== '') params.set('q', q);
            params.set('page', page);
            if (currentStatus && currentStatus !== 'all') params.set('status', currentStatus);
            if (currentSort && currentSort !== 'new') params.set('sort', currentSort);
            return params;
        }

        function fetchUsers(page = 1) {
            const params = buildParams(page);
            const url = 'user_update.php?' + params.toString();

            showLoading();

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    const newTbody = doc.querySelector('#userTable tbody');
                    const curTbody = document.querySelector('#userTable tbody');
                    if (newTbody && curTbody) curTbody.innerHTML = newTbody.innerHTML;

                    const newPag = doc.querySelector('.pagination');
                    const curPag = document.querySelector('.pagination');
                    if (newPag) {
                        if (curPag) curPag.outerHTML = newPag.outerHTML;
                        else document.body.insertAdjacentElement('beforeend', newPag);
                    } else if (curPag) {
                        curPag.remove();
                    }

                    if (window.history && window.history.replaceState) {
                        const newUrl = window.location.pathname + '?' + params.toString();
                        window.history.replaceState({}, '', newUrl);
                    }

                    hideLoading();
                })
                .catch(err => {
                    console.error(err);
                    hideLoading();
                });
        }

        searchBox.addEventListener('input', function () {
            const q = searchBox.value.trim();
            if (q === lastQuery) return;
            lastQuery = q;

            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => fetchUsers(1), 350);
        });

        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            fetchUsers(1);
        });

        document.querySelectorAll('.pill-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentStatus = this.dataset.status || 'all';
                fetchUsers(1);
            });
        });

        document.querySelectorAll('.sort-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentSort = this.dataset.sort || 'new';
                fetchUsers(1);
            });
        });

        document.addEventListener('click', function (e) {
            const link = e.target.closest('.pagination a');
            if (!link) return;
            e.preventDefault();

            const url = new URL(link.href, window.location.href);
            const page = parseInt(url.searchParams.get('page') || '1', 10);
            fetchUsers(page);
        });

        document.addEventListener('submit', function (e) {
            const form = e.target.closest('.deleteForm');
            if (!form) return;

            e.preventDefault();
            if (!confirm("Delete this user from ALL tables (same user_id)? This cannot be undone.")) return;

            showLoading();

            fetch('user_update.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form)
            })
                .then(r => r.json())
                .then(data => {
                    alert(data?.message || 'Done');
                    const cur = new URL(window.location.href);
                    const p = parseInt(cur.searchParams.get('page') || '1', 10);
                    fetchUsers(p);
                })
                .catch(err => {
                    console.error(err);
                    alert('Delete failed.');
                    hideLoading();
                });
        });
    </script>

</body>

</html>
<?php
$db->close();
?>
