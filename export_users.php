<?php
ob_start(); // buffer everything so headers never fail
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';

if (!isset($_SESSION['admin'])) {
    ob_end_clean();
    header('Location: admin_login.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF); // suppress strict mode during export

try {
    $db = db();
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    die('DB connection failed: ' . htmlspecialchars($e->getMessage()));
}

$format = strtolower(trim($_GET['format'] ?? 'excel'));
if (!in_array($format, ['excel', 'sql'], true)) {
    $format = 'excel';
}

// ─── Detect wallet_balance column ─────────────────────────────────────────────
$hasWallet = false;
try {
    $r = $db->query("SHOW COLUMNS FROM `users` LIKE 'wallet_balance'");
    if ($r && $r->num_rows > 0) $hasWallet = true;
} catch (Throwable $e) {}

// ─── Build balance expression ─────────────────────────────────────────────────
if ($hasWallet) {
    $balExpr = "IFNULL(u.wallet_balance, 0)";
} else {
    // Check for bonus_amount column in deposit
    $hasBonusCol = false;
    try {
        $rb = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
        if ($rb && $rb->num_rows > 0) $hasBonusCol = true;
    } catch (Throwable $e) {}

    $bonus = $hasBonusCol
        ? "(SELECT IFNULL(SUM(bonus_amount),0) FROM deposit WHERE user_id = u.user_id COLLATE utf8mb4_general_ci AND status='Done' AND method <> 'REFUND')"
        : "0";

    $balExpr = "ROUND((
        (SELECT IFNULL(SUM(amount),0) FROM deposit WHERE user_id = u.user_id COLLATE utf8mb4_general_ci AND status='Done' AND method <> 'REFUND')
        + {$bonus}
        - (SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE user_id = u.user_id COLLATE utf8mb4_general_ci AND status='Done')
    ), 2)";
}

// ─── Fetch ALL users ──────────────────────────────────────────────────────────
$sql = "SELECT u.user_id, u.name, u.mobile_no, ({$balExpr}) AS wallet_amount
        FROM users u
        ORDER BY u.id DESC";

$result = $db->query($sql);
if (!$result) {
    die("Query failed: " . $db->error);
}

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$db->close();

$now    = date('Y-m-d_H-i-s');
$total  = count($users);

// ══════════════════════════════════════════════════════════════════════════════
// EXCEL (CSV) EXPORT
// ══════════════════════════════════════════════════════════════════════════════
if ($format === 'excel') {
    ob_end_clean();
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="users_export_' . $now . '.xls"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // UTF-8 BOM for Excel
    echo "\xEF\xBB\xBF";

    // Title row
    echo "Users Export\t" . date('d-m-Y H:i:s') . "\t\t\n";
    echo "Total Records\t" . $total . "\t\t\n";
    echo "\n";

    // Header
    echo "Sr No\tUser ID\tUser Name\tMobile Number\tWallet Amount (₹)\n";

    $sr = 1;
    foreach ($users as $u) {
        $uid    = strip_tags($u['user_id'] ?? '');
        $name   = strip_tags($u['name'] ?? '');
        $mobile = strip_tags($u['mobile_no'] ?? '');
        $wallet = number_format((float)($u['wallet_amount'] ?? 0), 2, '.', '');

        echo implode("\t", [$sr++, $uid, $name, $mobile, $wallet]) . "\n";
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// SQL EXPORT
// ══════════════════════════════════════════════════════════════════════════════
if ($format === 'sql') {
    ob_end_clean();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="users_export_' . $now . '.sql"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $lines = [];
    $lines[] = "-- ============================================================";
    $lines[] = "-- Users Export";
    $lines[] = "-- Generated : " . date('Y-m-d H:i:s');
    $lines[] = "-- Total     : " . $total . " records";
    $lines[] = "-- ============================================================";
    $lines[] = "";
    $lines[] = "-- DROP TABLE IF EXISTS `users_export`;";
    $lines[] = "";
    $lines[] = "CREATE TABLE IF NOT EXISTS `users_export` (";
    $lines[] = "  `sr_no`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,";
    $lines[] = "  `user_id`       VARCHAR(100)     NOT NULL DEFAULT '',";
    $lines[] = "  `user_name`     VARCHAR(255)     NOT NULL DEFAULT '',";
    $lines[] = "  `mobile_no`     VARCHAR(20)      NOT NULL DEFAULT '',";
    $lines[] = "  `wallet_amount` DECIMAL(15,2)    NOT NULL DEFAULT '0.00',";
    $lines[] = "  PRIMARY KEY (`sr_no`)";
    $lines[] = ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $lines[] = "";
    $lines[] = "TRUNCATE TABLE `users_export`;";
    $lines[] = "";

    if ($total > 0) {
        $lines[] = "INSERT INTO `users_export` (`user_id`, `user_name`, `mobile_no`, `wallet_amount`) VALUES";
        $valueParts = [];
        foreach ($users as $u) {
            $uid    = addslashes($u['user_id'] ?? '');
            $name   = addslashes($u['name'] ?? '');
            $mobile = addslashes($u['mobile_no'] ?? '');
            $wallet = number_format((float)($u['wallet_amount'] ?? 0), 2, '.', '');
            $valueParts[] = "  ('{$uid}', '{$name}', '{$mobile}', {$wallet})";
        }
        $lines[] = implode(",\n", $valueParts) . ";";
    }

    $lines[] = "";
    $lines[] = "-- End of export";

    echo implode("\n", $lines);
    exit;
}
