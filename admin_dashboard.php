<?php
require_once 'includes/db.php';
// admin_dashboard.php (FIXED: exclude method='REFUND' from Total Deposit + ADMIN NULL order_id safe + bid_refunds date column + no unknown column errors)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

date_default_timezone_set('Asia/Kolkata');

/**
 * Session me jo bhi value rakhi hai (email / admin_id / name),
 * usko yahan use karenge aur admin table se correct row nikalenge.
 */
$adminIdentifier = $_SESSION['admin']; // email / admin_id / name
$adminName = $adminIdentifier;   // default display
$adminRole = 'admin';            // default
$isMaster = false;              // default

/* ---------- Banner text defaults ---------- */
$bannerTextDefault = 'Market New Price Update Ho Gaya Hai.';
$bannerText = $bannerTextDefault;
$bannerError = '';
$bannerSuccess = '';

/* ---------- 1. Date-range handling ---------- */
$range = $_GET['range'] ?? 'today';
$today = ($range === 'today' || $range === '');
$days = in_array($range, ['7', '15', '30'], true) ? (int) $range : 0;
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$isCustom = ($range === 'custom')
  && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)
  && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);

$label = $today ? 'Today'
  : ($days ? "Last $days days"
    : ($range === 'all' ? 'All time'
      : "Custom ($from → $to)"));

/* ---------- 2. Upload status holders ---------- */
$uploadError = '';
$uploadSuccess = '';

/* ---------- 3. DB CONNECT + ROLE + BANNER FETCH ---------- */
$db = db();

/* ✅ Helper: check column exists */
$hasColumn = function (mysqli $db, string $table, string $col): bool {
  try {
    $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
    $stmt->bind_param("ss", $table, $col);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    return ((int) $cnt > 0);
  } catch (Throwable $e) {
    return false;
  }
};

/* ✅ Build date filter for any table/column safely */
$buildDateFilter = function (string $col) use ($today, $days, $isCustom, $from, $to): string {
  if ($today) {
    return " AND {$col} >= CURDATE()";
  }
  if ($days) {
    return " AND {$col} >= CURDATE() - INTERVAL {$days} DAY";
  }
  if ($isCustom) {
    // from 00:00:00 to to 23:59:59
    $fromSql = $from . " 00:00:00";
    $toSql = $to . " 23:59:59";
    return " AND {$col} BETWEEN '{$fromSql}' AND '{$toSql}'";
  }
  return ""; // all time
};

// date filters for most tables
$dateFilter = $buildDateFilter('date_time');

/* ---- 3A. Role + Name from admin table (using email / admin_id / name) ---- */
try {
  $stmtInfo = $db->prepare("
        SELECT name, role
        FROM admin
        WHERE email = ? OR admin_id = ? OR name = ?
        LIMIT 1
    ");
  $stmtInfo->bind_param("sss", $adminIdentifier, $adminIdentifier, $adminIdentifier);
  $stmtInfo->execute();
  $resInfo = $stmtInfo->get_result();
  if ($row = $resInfo->fetch_assoc()) {
    $adminName = $row['name'];
    $adminRole = strtolower(trim($row['role'])); // 'master' / 'admin'
    $isMaster = ($adminRole === 'master');
  }
  $stmtInfo->close();
} catch (Throwable $e) {
  // defaults
}

/* ---- 3B. Banner text READ from DB (banner_text.banner) ---- */
try {
  $resB = $db->query("SELECT banner FROM banner_text ORDER BY id DESC LIMIT 1");
  if ($resB && $rowB = $resB->fetch_assoc()) {
    if (!empty($rowB['banner']))
      $bannerText = $rowB['banner'];
  }
} catch (Throwable $e) {
  // default
}

/* ---------- 4. Handle POST (Banner + Notice image) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  /* ---- 4A. Banner text submit (DB update) ---- */
  if (isset($_POST['banner_text_submit'])) {
    $newText = trim($_POST['banner_text'] ?? '');
    if ($newText === '') {
      $bannerError = 'Banner text khaali nahi ho sakta.';
    } else {
      if (mb_strlen($newText) > 100)
        $newText = mb_substr($newText, 0, 100);

      try {
        $hasRow = false;
        $lastId = 0;

        $stmt = $db->prepare("SELECT id FROM banner_text ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $stmt->bind_result($lastId);
        if ($stmt->fetch())
          $hasRow = true;
        $stmt->close();

        if ($hasRow) {
          $stmtU = $db->prepare("UPDATE banner_text SET banner = ? WHERE id = ?");
          $stmtU->bind_param('si', $newText, $lastId);
          $stmtU->execute();
          $stmtU->close();
        } else {
          $stmtI = $db->prepare("INSERT INTO banner_text (banner) VALUES (?)");
          $stmtI->bind_param('s', $newText);
          $stmtI->execute();
          $stmtI->close();
        }

        $bannerText = $newText;
        $bannerSuccess = 'Banner text successfully update ho gaya.';
      } catch (Throwable $e) {
        $bannerError = 'Database error: banner update nahi ho paya.';
      }
    }
  }

  /* ---- 4B. Notice image upload ---- */
  if (isset($_FILES['notice_img']) && ($_FILES['notice_img']['name'] ?? '') !== '') {
    $f = $_FILES['notice_img'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $uploadError = 'Upload error ' . $f['error'];
    } elseif (!isset($f['tmp_name']) || !is_file($f['tmp_name'])) {
      $uploadError = 'Invalid upload.';
    } elseif (strpos((string) mime_content_type($f['tmp_name']), 'image/') !== 0) {
      $uploadError = 'Only images allowed.';
    } else {
      $dir = __DIR__ . '/notice board/';
      if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        $uploadError = 'Cannot create directory.';
      } elseif (move_uploaded_file($f['tmp_name'], $dir . 'notice_board.jpg')) {
        $uploadSuccess = 'Notice updated.';
      } else {
        $uploadError = 'Move failed.';
      }
    }
  }
}

/* ---------- 4C. Handle Minimum Bid Update (Directly from Dashboard) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_min_bid'])) {
  if (!$isMaster) {
    die('Unauthorized: Only Master role can update Min Bid.');
  }
  $newMinBid = (int) ($_POST['min_bid'] ?? 10);
  try {
    $stmt = $db->prepare("UPDATE admin_payment_details SET min_bid = ? WHERE id = 1");
    $stmt->bind_param('i', $newMinBid);
    $stmt->execute();
    $stmt->close();
    header('Location: admin_dashboard.php?success=min_bid');
    exit;
  } catch (Throwable $e) {
    $bannerError = 'Min Bid update failed: ' . $e->getMessage();
  }
}

$currentMinBid = 10;
try {
  $resMB = $db->query("SELECT min_bid FROM admin_payment_details WHERE id = 1 LIMIT 1");
  if ($resMB && $rowMB = $resMB->fetch_assoc()) {
    $currentMinBid = (int) ($rowMB['min_bid'] ?? 10);
  }
} catch (Throwable $e) {
}

/* ---------- 5. KPI & download queries (FIXED) ---------- */

/**
 * ✅ FIX NOTES:
 * - deposit table has date_time (not created_at)
 * - bid_refunds table has created_at (NOT refunded_at)
 * - ADMIN deposits with NULL order_id were getting excluded due to NULL LIKE logic
 * - Total Deposit should EXCLUDE ONLY method='REFUND'
 */

// ✅ Pick correct date column for bid_refunds
$bidRefundDateCol = 'created_at';
if ($hasColumn($db, 'bid_refunds', 'refunded_at')) {
  $bidRefundDateCol = 'refunded_at';
} elseif ($hasColumn($db, 'bid_refunds', 'created_at')) {
  $bidRefundDateCol = 'created_at';
} elseif ($hasColumn($db, 'bid_refunds', 'bid_time')) {
  $bidRefundDateCol = 'bid_time';
} elseif ($hasColumn($db, 'bid_refunds', 'date_time')) {
  $bidRefundDateCol = 'date_time';
}
$bidRefundDateFilter = $buildDateFilter($bidRefundDateCol);

// ✅ Wining date column
$winDateFilter = $buildDateFilter('win_time');

/* ✅ Total Deposit: include ALL methods except REFUND (and Done only) */
$totDep = 0.0;
try {
  $sqlTotDep = "SELECT COALESCE(SUM(amount),0) FROM deposit
                   WHERE status='Done'
                     AND method <> 'REFUND'" . $dateFilter;
  $totDep = (float) $db->query($sqlTotDep)->fetch_row()[0];
} catch (Throwable $e) {
  $totDep = 0.0;
}

// Total Withdrawals (sirf Done + method NOT NULL/NOT empty)
$totWith = 0.0;
try {
  $sqlTotWith = "SELECT COALESCE(SUM(amount),0) FROM withdrawal
                   WHERE status='Done'
                     AND method IS NOT NULL
                     AND method <> ''
                     AND method <> 'game_bid'" . $dateFilter;
  $totWith = (float) $db->query($sqlTotWith)->fetch_row()[0];
} catch (Throwable $e) {
  $totWith = 0.0;
}

/* ✅ Bid Refund (bid_refunds table) — FIXED date column */
$bidRefund = 0.0;
try {
  $sqlBidRefund = "SELECT COALESCE(SUM(amount),0) FROM bid_refunds WHERE 1=1" . $bidRefundDateFilter;
  $bidRefund = (float) $db->query($sqlBidRefund)->fetch_row()[0];
} catch (Throwable $e) {
  $bidRefund = 0.0;
}

/* ✅ Bid Trade = Sum of amount from bid excluding refunded bids */
$bidTrade = 0.0;
try {
  $bidDateFilter = $buildDateFilter('b.bid_time');
  $sqlBidTrade = "SELECT COALESCE(SUM(b.amount),0) 
                  FROM bid b 
                  WHERE 1=1 " . $bidDateFilter . " 
                    AND NOT EXISTS (
                        SELECT 1 FROM bid_refunds br 
                        WHERE br.bid_id = b.id AND br.source_table = 'bid'
                    )";
  $bidTrade = (float) $db->query($sqlBidTrade)->fetch_row()[0];
} catch (Throwable $e) {
  $bidTrade = 0.0;
}

/* Wallet Balance (Accurate calculation from user_total_balance_sql) - ALWAYS ALL TIME */
$walletBal = 0.0;
$totBonus = 0.0;

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
        if (!$st) return false;
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

try {
  $hasBonusCol = $hasColumn($db, 'deposit', 'bonus_amount');
  $balSql = user_total_balance_sql($db, $hasBonusCol);
  
  $sqlWalletBal = "SELECT SUM($balSql) FROM users u WHERE 1";
  $walletBal = (float) $db->query($sqlWalletBal)->fetch_row()[0];

  /* Total Distributed Bonus */
  if ($hasBonusCol) {
      $sqlTotBonus = "SELECT COALESCE(SUM(bonus_amount), 0) FROM deposit WHERE LOWER(status) IN ('pending','approved','done') AND (method IS NULL OR UPPER(method) <> 'REFUND')";
      $totBonus = (float) $db->query($sqlTotBonus)->fetch_row()[0];
  }
} catch (Throwable $e) {
  $walletBal = 0.0;
  $totBonus = 0.0;
}

/* Total Wining (respects date filter for display) */
$totalWining = 0.0;
try {
  $sqlWin = "SELECT COALESCE(SUM(wining_balance),0) FROM wining WHERE status='Win'" . $winDateFilter;
  $totalWining = (float) $db->query($sqlWin)->fetch_row()[0];
} catch (Throwable $e) {
  $totalWining = 0.0;
}

/* Total Wining (All Time for Liability snapshot) */
$totalWiningAllTime = 0.0;
try {
  $totalWiningAllTime = (float) $db->query("SELECT COALESCE(SUM(wining_balance),0) FROM wining WHERE status='Win'")->fetch_row()[0];
} catch (Throwable $e) {
  $totalWiningAllTime = 0.0;
}

$totalCombined = $walletBal + $totalWiningAllTime;

/* Pending requests */
$depReq = 0;
$withReq = 0;
$cryptoKYC = 0;

try {
  $depReq = (int) $db->query("SELECT COUNT(*) FROM deposit WHERE status='Pending'" . $dateFilter)->fetch_row()[0];
} catch (Throwable $e) {
}
try {
  $withReq = (int) $db->query("SELECT COUNT(*) FROM withdrawal WHERE status='Pending'" . $dateFilter)->fetch_row()[0];
} catch (Throwable $e) {
}

/* ---- Crypto KYC Pending ---- */
try {
  $cryptoKYC = (int) $db->query("SELECT COUNT(*) FROM crypto_kyc WHERE status='Pending'" . $dateFilter)->fetch_row()[0];
} catch (Throwable $e) {
}

/* ---- Bank & UPI KYC Pending ---- */
$bankKYCPending = 0;
$upiKYCPending = 0;
try {
  $bankKYCPending = (int) $db->query("SELECT COUNT(*) FROM bank_kyc WHERE LOWER(status) = 'linked'")->fetch_row()[0];
} catch (Throwable $e) {
  $bankKYCPending = 0;
}
try {
  $upiKYCPending = (int) $db->query("SELECT COUNT(*) FROM upi_kyc WHERE LOWER(status) = 'linked'")->fetch_row()[0];
} catch (Throwable $e) {
  $upiKYCPending = 0;
}

/* ---- Check show_crypto from admin_payment_details (toggle) ---- */
$showCrypto = 0;
try {
  $rsAdm = $db->query("SELECT show_crypto FROM admin_payment_details WHERE id = 1 LIMIT 1");
  if ($rsAdm && $rowAdm = $rsAdm->fetch_assoc()) {
    $showCrypto = (int) $rowAdm['show_crypto'];
  }
} catch (Throwable $e) {
  $showCrypto = 0;
}

/* Users + Live users */
$totalUsers = 0;
$todayRegistrations = 0;
$liveUsers = 0;
$liveActive = 0;

try {
  $totalUsers = (int) $db->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
} catch (Throwable $e) {}
try {
  $todayRegistrations = 0;
  $filter = $buildDateFilter('created_at');
  $todayRegistrations = (int) $db->query("SELECT COUNT(*) FROM users WHERE 1=1 " . $filter)->fetch_row()[0];
} catch (Throwable $e) {
}
try {
  $liveUsers = (int) $db->query("SELECT COUNT(*) FROM live WHERE status='online'")->fetch_row()[0];
} catch (Throwable $e) {
}
try {
  $liveActive = (int) $db->query("
        SELECT COUNT(*) FROM live
        WHERE status='online'
          AND last_seen >= NOW() - INTERVAL 20 SECOND
    ")->fetch_row()[0];
} catch (Throwable $e) {
}

/* APK downloads */
$apkDownloads = 0;
try {
  $res = $db->query("SELECT SUM(download_count) AS total FROM downloads");
  $apkDownloads = $res ? (int) ($res->fetch_assoc()['total'] ?? 0) : 0;
} catch (Throwable $e) {
  $apkDownloads = 0;
}

/* ---- Sub Admin count (staff table) ---- */
$subAdminCount = 0;
try {
  $rSub = $db->query("SELECT COUNT(*) FROM staff WHERE role='subadmin'");
  if ($rSub)
    $subAdminCount = (int) $rSub->fetch_row()[0];
} catch (Throwable $e) {
  $subAdminCount = 0;
}

/* ✅ Markets list for win trigger (published only) */
$allMarkets = [];
try {
  $rm = $db->query("SELECT market_name FROM markets WHERE is_published=1 ORDER BY sort_order, market_name");
  while ($rm && ($rowm = $rm->fetch_assoc())) {
    $allMarkets[] = strtoupper(trim($rowm['market_name']));
  }
} catch (Throwable $e) {
  $allMarkets = [];
}
if (empty($allMarkets))
  $allMarkets = ['WELCOME'];

// $db->close(); // Moved to very end

/* ✅ For JS (today range by default) */
$jsFrom = date('Y-m-d');
$jsTo = date('Y-m-d');

/* ✅ Wins trigger config */
define('WINS_COMPUTE_URL', 'fetch_wins_api.php');  // same-origin
define('WINS_TRIGGER_KEY', 'LL_ADMIN_WINS_2025');  // optional (admin session bhi chalega)
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard – SUBH GAMING</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    a {
      text-decoration: none !important;
      color: inherit;
    }

    html,
    body {
      margin: 0;
      padding: 0;
      overflow-x: hidden;
      background: #f5f0e5;
      font-family: 'Roboto', sans-serif;
    }

    :root {
      --red: #c62828;
      --green: #2e7d32;
      --blue: #0277BD;
      --orange: #F57C00;
      --brown: #4e342e;
      --wallet: #009688;
      --shadow: rgba(0, 0, 0, 0.10);
      --cardShadow: 0 6px 18px rgba(0, 0, 0, 0.10);
      --radius: 14px;
    }

    .wrapper {
      width: 96%;
      max-width: 1400px;
      margin: 14px auto;
      padding: 0 10px;
      box-sizing: border-box;
    }

    @media (max-width: 600px) {
      .wrapper {
        width: 100%;
        padding: 0 8px;
        margin: 8px auto;
      }
    }

    .topbar {
      position: sticky;
      top: 6px;
      z-index: 50;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      background: #fff;
      padding: 10px 12px;
      border-radius: var(--radius);
      box-shadow: 0 2px 10px var(--shadow);
    }

    /* topbar row layout */
    .topbar-row1 {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      gap: 8px;
    }

    .topbar-row2 {
      display: flex;
      align-items: center;
      flex-wrap: nowrap;
      gap: 8px;
      width: 100%;
    }

    .topbar-row3 {
      display: flex;
      align-items: center;
      justify-content: space-between;
      width: 100%;
      gap: 8px;
    }

    /* Desktop: Row1=Title center, Row2=Date filters, Row3=APK+Logout */
    @media (min-width: 601px) {
      .topbar {
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
      }
      .topbar-row1 {
        width: auto;
        flex: 0 0 auto;
        justify-content: flex-start;
      }
      .topbar-row2 {
        flex: 1 1 auto;
        width: auto;
      }
      .topbar-row3 {
        width: auto;
        flex: 0 0 auto;
      }
    }

    @media (max-width: 600px) {
      .topbar {
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
        padding: 8px 10px;
        top: 4px;
      }

      /* Row 1: Title centered */
      .topbar-row1 {
        justify-content: center !important;
        width: 100%;
      }
      .topbar-row1 .title {
        justify-content: center;
        text-align: center;
        flex: 1;
      }

      /* Row 2: Date filters full width */
      .topbar-row2 {
        flex-direction: column;
        gap: 5px;
      }
      .range-group {
        width: 100% !important;
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        gap: 4px !important;
        flex: unset !important;
      }
      .range-btn {
        font-size: 10px !important;
        padding: 5px 7px !important;
        white-space: nowrap;
        flex-shrink: 0;
      }

      /* Row 3: APK left + Logout right */
      .topbar-row3 {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        gap: 8px;
      }
      .apk-count {
        font-size: 11px;
        padding: 4px 8px;
        flex-shrink: 0;
      }

      .custom-form {
        flex-wrap: nowrap !important;
        gap: 4px !important;
        width: 100%;
      }
      .custom-form input {
        flex: 1;
        min-width: 0;
        font-size: 11px !important;
        padding: 5px 6px !important;
      }
      .custom-form button {
        font-size: 11px !important;
        padding: 5px 8px !important;
        white-space: nowrap;
      }
    }

    .title {
      font-size: 18px;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--brown);
    }

    .title i {
      color: #ffb300;
    }

    .range-group {
      display: flex;
      flex-wrap: nowrap;
      gap: 4px;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      flex: 1;
    }

    .range-group::-webkit-scrollbar {
      height: 2px;
    }

    .range-btn {
      padding: 6px 10px;
      font-size: 12px;
      border: 1px solid rgba(2, 119, 189, .35);
      border-radius: 8px;
      background: #fff;
      color: #0277BD;
      transition: 0.18s;
      white-space: nowrap;
      flex-shrink: 0;
      box-shadow: 0 1px 0 rgba(0, 0, 0, 0.03);
    }

    .range-btn.active,
    .range-btn:hover {
      background: var(--blue);
      color: #fff;
      border-color: var(--blue);
    }

    .custom-form {
      display: none;
      margin-top: 6px;
      gap: 8px;
      align-items: center;
    }

    .custom-form input {
      padding: 6px 10px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 12px;
    }

    .custom-form button {
      padding: 6px 10px;
      background: var(--blue);
      color: #fff;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 700;
    }

    .logout {
      background: var(--red);
      color: #fff;
      padding: 7px 12px;
      border-radius: 8px;
      font-weight: 700;
      font-size: 12px;
      box-shadow: 0 3px 10px rgba(198, 40, 40, 0.18);
    }

    .apk-count {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      font-weight: 700;
      color: var(--blue);
      background: #e3f2fd;
      border: 1px solid #bbdefb;
      border-radius: 8px;
      padding: 6px 10px;
    }

    .badge-row {
      margin: 12px 0 8px;
    }

    .badge-actions {
      display: grid;
      grid-template-columns: repeat(9, 1fr);
      gap: 5px;
      width: 100%;
    }

    @media (max-width: 1200px) {
      .badge-actions {
        grid-template-columns: repeat(6, 1fr);
      }
    }

    @media (max-width: 768px) {
      .badge-actions {
        grid-template-columns: repeat(3, 1fr);
        gap: 5px;
      }
    }

    .btn-pill {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      padding: 5px 4px;
      border-radius: 8px;
      color: #fff;
      font-weight: 800;
      font-size: 11px;
      transition: 0.18s;
      width: 100%;
      min-height: 28px;
      box-sizing: border-box;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
      user-select: none;
      text-align: center;
      white-space: nowrap;
    }

    .btn-pill i {
      font-size: 11px;
      flex-shrink: 0;
    }

    .btn-pill:hover {
      opacity: 0.92;
      transform: translateY(-1px);
    }

    @media (prefers-reduced-motion: reduce) {
      .btn-pill:hover {
        transform: none;
      }

      .card:hover {
        transform: none;
      }
    }

    .chat-badge {
      background: var(--green);
    }

    .open-badge {
      background: var(--blue);
    }

    .stats-badge {
      background: var(--orange);
    }

    .update-badge {
      background: var(--brown);
    }

    .total-wallet-wining-badge {
      background: #7e57c2;
    }

    .total-wallet-wining-badge:hover {
      background: #512da8;
    }

    .all-bid-data-badge {
      background: #5d4037;
    }

    .all-bid-data-badge:hover {
      background: #3e2723;
    }

    .market-list-badge {
      background: #1565c0;
    }

    .market-list-badge:hover {
      background: #0d47a1;
    }

    .market-list2-badge {
      background: #0288d1;
    }

    .market-list2-badge:hover {
      background: #01579b;
    }

    .market-list4-badge {
      background: #8b0000;
    }

    .market-list4-badge:hover {
      background: #6a0000;
    }

    .starline-condn-badge {
      background: #673ab7;
    }

    .starline-condn-badge:hover {
      background: #512da8;
    }

    .bank-update-badge {
      background: #00897b;
    }

    .bank-update-badge:hover {
      background: #00695c;
    }

    .admin-notice-badge {
      background: #c2185b;
    }

    .admin-notice-badge:hover {
      background: #880e4f;
    }

    .create-admin-badge {
      background: #8e24aa;
    }

    .create-admin-badge:hover {
      background: #6a1b9a;
    }

    .banner-text-badge {
      background: #455a64;
    }

    .banner-text-badge:hover {
      background: #263238;
    }

    .data-analytics-badge {
      background: #1e88e5;
    }

    .data-analytics-badge:hover {
      background: #1565c0;
    }

    .set-limit-badge {
      background: #546e7a;
    }

    .set-limit-badge:hover {
      background: #37474f;
    }

    .bid-revise-badge {
      background: #d84315;
    }

    .bid-revise-badge:hover {
      background: #bf360c;
    }

    .btn-pill .cnt {
      width: 18px;
      height: 18px;
      padding: 0 !important;
      background: #ffffff;
      color: black;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      line-height: 18px;
      min-width: 18px;
      aspect-ratio: 1 / 1;
    }

    .live-count {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 800;
      font-size: 12px;
      white-space: nowrap;
      text-align: center;
    }

    .live-wrap {
      display: flex;
      align-items: center;
      gap: 10px;
      background: #fff3e0;
      padding: 8px 12px;
      border-radius: 8px;
      border: 1px solid #ffe0b2;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    }

    .dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      display: inline-block;
    }

    .dot.green {
      background: #43a047;
    }

    .dot.yellow {
      background: #ffb300;
    }

    .live-user-link {
      background: var(--green);
      color: #fff;
      border-radius: 8px;
      font-size: 12px;
      padding: 8px 12px;
      text-decoration: none;
      font-weight: 800;
      transition: 0.18s;
      box-shadow: 0 4px 14px rgba(46, 125, 50, 0.15);
    }

    .live-user-link:hover {
      background: #18691c;
    }

    .live-user-link.deactive {
      background: var(--red);
      box-shadow: 0 4px 14px rgba(198, 40, 40, 0.16);
    }

    .live-user-link.deactive:hover {
      background: #8e0000;
    }

    .notice {
      text-align: center;
      margin: 10px auto 6px;
      font-weight: 700;
      font-size: 12px;
      padding: 10px 12px;
      border-radius: 12px;
      max-width: 820px;
      background: #fff;
      box-shadow: 0 2px 10px var(--shadow);
    }

    .meta {
      display: flex;
      align-items: center;
      justify-content: center;
      flex-wrap: wrap;
      gap: 10px;
      text-align: center;
      color: #555;
      width: 92%;
      max-width: 1400px;
      margin: 10px auto 16px;
      padding: 0 20px;
      box-sizing: border-box;
      font-weight: 600;
    }

    @media (max-width: 600px) {
      .meta {
        width: 100%;
        padding: 0 10px;
      }
    }

    .role-pill {
      display: inline-block;
      margin-left: 8px;
      padding: 4px 12px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 900;
      color: #fff;
    }

    .card {
      background: #fff;
      border-radius: var(--radius);
      padding: 16px;
      text-align: center;
      box-shadow: 0 4px 14px var(--shadow);
      transition: 0.18s;
      border: 1px solid rgba(0, 0, 0, 0.03);
    }

    .card:hover {
      transform: translateY(-3px);
      box-shadow: var(--cardShadow);
    }

    .card i {
      font-size: 28px;
      margin-bottom: 8px;
      opacity: .95;
    }

    .card span {
      display: block;
      font-size: 21px;
      font-weight: 900;
      margin-bottom: 4px;
      color: var(--brown);
      letter-spacing: 0.2px;
    }

    .card small {
      font-size: 13px;
      color: #666;
      font-weight: 700;
    }

    .bg-green {
      border-top: 6px solid var(--green);
    }

    .bg-red {
      border-top: 6px solid var(--red);
    }

    .bg-blue {
      border-top: 6px solid var(--blue);
    }

    .bg-orange {
      border-top: 6px solid var(--orange);
    }

    .bg-wallet {
      border-top: 6px solid var(--wallet);
    }

    .bg-brown {
      border-top: 6px solid var(--brown);
    }

    .bg-purple {
      border-top: 6px solid #7e57c2;
    }

    .grid-5 {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 14px;
      margin-top: 10px;
      padding: 0 35px;
      /* Added side padding for KPI cards */
    }

    @media(max-width:1100px) {
      .grid-5 {
        grid-template-columns: repeat(4, 1fr);
      }
    }

    @media(max-width:900px) {
      .grid-5 {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    @media(max-width:600px) {
      .grid-5 {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media(max-width:420px) {
      .grid-5 {
        grid-template-columns: repeat(1, 1fr);
      }
    }

    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .55);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 200;
      padding: 18px;
    }

    .modal-box {
      background: #fff;
      padding: 18px 16px 14px;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
      width: 100%;
      max-width: 460px;
      animation: pop .22s ease;
      border: 1px solid rgba(0, 0, 0, 0.06);
    }

    .modal-box h3 {
      margin: 0 0 10px;
      font-size: 16px;
      font-weight: 900;
      color: #263238;
      text-align: center;
      letter-spacing: .3px;
    }

    .modal-box textarea {
      width: 100%;
      min-height: 86px;
      resize: vertical;
      padding: 10px 10px;
      font-size: 13px;
      border-radius: 12px;
      border: 1px solid #ddd;
      box-sizing: border-box;
      margin-bottom: 12px;
      outline: none;
    }

    .modal-box textarea:focus {
      border-color: #90caf9;
      box-shadow: 0 0 0 3px rgba(144, 202, 249, 0.35);
    }

    .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }

    .modal-actions button {
      border: none;
      border-radius: 12px;
      padding: 8px 14px;
      font-size: 13px;
      cursor: pointer;
      font-weight: 900;
    }

    .btn-cancel {
      background: #eceff1;
      color: #263238;
    }

    .btn-save {
      background: #2e7d32;
      color: #fff;
    }

    @keyframes pop {
      0% {
        transform: scale(.92);
        opacity: 0
      }

      100% {
        transform: scale(1);
        opacity: 1
      }
    }

    .pulse {
      display: inline-block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: rgba(0, 0, 0, 0.2);
      animation: pulse 1.2s infinite;
      vertical-align: middle;
    }

    @keyframes pulse {

      0%,
      100% {
        transform: scale(1);
        opacity: .45
      }

      50% {
        transform: scale(1.6);
        opacity: .9
      }
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
  <div class="wrapper">
    <div class="topbar">
      <!-- Row 1: Title centered (mobile: center) -->
      <div class="topbar-row1">
        <div class="title"><img src="images/logo.png" style="height:28px; border-radius:6px; vertical-align:middle; margin-right:6px;"><i class="fas fa-crown" style="color:#fbbf24;"></i> SUBH GAMING ADMIN</div>
      </div>

      <!-- Row 2: Date filters full width scrollable -->
      <div class="topbar-row2">
        <div class="range-group">
          <?php
          require_once 'includes/db.php';
          $btn = function ($txt, $val) use ($range, $from, $to) {
            printf(
              '<a class="range-btn %s" href="?range=%s%s">%s</a>',
              $val === $range ? 'active' : '',
              $val,
              $val === 'custom' ? "&from=$from&to=$to" : '',
              $txt
            );
          };
          $btn('Today', 'today');
          $btn('7 Days', '7');
          $btn('15 Days', '15');
          $btn('30 Days', '30');
          $btn('All Time', 'all');
          echo '<a class="range-btn ' . ($range === 'custom' ? 'active' : '') . '" href="#" onclick="showCustom(event)">Custom…</a>';
          ?>
        </div>

        <form class="custom-form" id="cForm" method="GET">
          <input type="hidden" name="range" value="custom">
          <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" required>
          <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" required>
          <button>Apply</button>
        </form>
      </div>

      <!-- Row 3: APK (left) + Logout (right) -->
      <div class="topbar-row3">
        <div class="apk-count" title="Total APK Downloads">
          <i class="fas fa-download"></i>
          APK: <strong id="apkDownloadsCount"><?= number_format($apkDownloads) ?></strong>
        </div>
        <a class="logout" href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>

    <div class="badge-row">
      <div class="badge-actions">
        <a class="btn-pill" href="clean_database.php" style="background:linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); color:#fff; box-shadow:0 0 10px rgba(239,68,68,0.5);" title="Database Wipe & Reset Utility"><i class="fas fa-database"></i> Reset DB</a>
        <a class="btn-pill chat-badge" href="admin_live_chat.php" title="Open Live Chat">
          <i class="fas fa-comments"></i>Chat <span id="chatCnt" class="cnt"><span class="pulse"></span></span>
        </a>
        <a class="btn-pill open-badge" href="open_market.php">
          <i class="fas fa-store"></i>Open Market
        </a>
        <a class="btn-pill stats-badge" href="admin_statistics.php">
          <i class="fas fa-chart-line"></i>Statistics
        </a>
        <a class="btn-pill update-badge" href="user_update.php">
          <i class="fas fa-users"></i>Users (<?= (int) $totalUsers ?>)
        </a>
        <a class="btn-pill live-user-link" href="live_account.php">
          <i class="fas fa-signal"></i>Live Account
        </a>
        <a class="btn-pill live-user-link deactive" href="admin_user_active.php">
          <i class="fas fa-user-slash"></i>Deactive Users
        </a>
        <?php if ($isMaster): ?>
          <a class="btn-pill create-admin-badge" href="create_admin.php">
            <i class="fas fa-user-shield"></i>Create Admin
          </a>
          <a class="btn-pill data-analytics-badge" href="admin_views.php">
            <i class="fas fa-chart-pie"></i>Data Analytics
          </a>
        <?php endif; ?>
        <a class="btn-pill total-wallet-wining-badge" href="total_wallet_wining.php">
          <i class="fas fa-rupee-sign"></i>Total Wining
        </a>
        <a class="btn-pill all-bid-data-badge" href="all_bid_data.php">
          <i class="fas fa-table"></i>All Bid Data</a>
        <a class="btn-pill bid-revise-badge" href="admin_bid_revise.php">
          <i class="fas fa-edit"></i>Bid Revise
        </a>
        <a class="btn-pill market-list-badge" href="list_of_market.php">
          <i class=" fas fa-list"></i>Market List
        </a>
        <a class="btn-pill market-list2-badge" href="list_of_market2.php">
          <i class=" fas fa-list"></i>Market List 2
        </a>
        <a class="btn-pill market-list4-badge" href="list_of_market4.php">
          <i class=" fas fa-list"></i>Market List 4
        </a>
        <a class="btn-pill starline-condn-badge" href="starline4_condition.php">
          <i class="fas fa-magic"></i>Starline Condn
        </a>
        <a class="btn-pill" href="payout.php" style="background:#4e342e; color:#fff;">
          <i class="fas fa-hand-holding-dollar"></i>Payout Update
        </a>
        <?php if ($isMaster): ?>
          <a class="btn-pill bank-update-badge" href="admin_bank_update.php">
            <i class="fas fa-building-columns"></i>Bank Update
          </a>
          <a class="btn-pill set-limit-badge" href="admin_bank_update.php">
            <i class="fas fa-sliders-h"></i>Set Limit
          </a>
          <a class="btn-pill banner-text-badge" href="admin_bank_update.php#whatsapp_settings"
            style="background:#25D366;">
            <i class="fab fa-whatsapp"></i>Whatsap Update
          </a>
        <?php endif; ?>
        <a class="btn-pill admin-notice-badge" href="admin_notice.php">
          <i class="fas fa-bullhorn"></i>Admin Notice
        </a>
        <a class="btn-pill" href="admin_bonus_rates.php" style="background:#0066cc; color:#fff;">
          <i class="fas fa-gift"></i>Bonus Rates
        </a>
        <a class="btn-pill" href="otp_records.php" style="background:#607d8b; color:#fff;">
          <i class="fas fa-key"></i>OTP RECORD
        </a>
        <a class="btn-pill" href="admin_howtoplay.php" style="background:#e67e22; color:#fff;">
          <i class="fas fa-circle-info"></i>How to Play
        </a>

        <a class="btn-pill" href="admin_price_chart.php" style="background:#795548; color:#fff;">
          <i class="fas fa-chart-bar"></i>Game Rate
        </a>

        <a href="#" class="btn-pill banner-text-badge" onclick="openBannerModal(event)"
          style="background:#455a64; color:#fff;">
          <i class="fas fa-scroll"></i>Banner text
        </a>
        <a class="btn-pill" href="admin_recent_winners.php" style="background:#ff005c; color:#fff;">
          <i class="fas fa-trophy"></i>Recent Winners
        </a>
      </div>
    </div><!-- /.badge-row -->
  </div>

  <?php
  if ($uploadError || $uploadSuccess || $bannerError || $bannerSuccess): ?>
    <div class="notice" style="color:<?= ($uploadError || $bannerError) ? '#E53935' : '#2e7d32' ?>;">
      <?= htmlspecialchars($uploadError ?: $uploadSuccess ?: $bannerError ?: $bannerSuccess) ?>
    </div>
  <?php endif; ?>

  <div class="meta">
    Showing data: <strong><?= $label ?></strong> — Welcome,
    <strong><?= htmlspecialchars($adminName) ?></strong>
    <span class="role-pill" style="background:<?= ($adminRole === 'master') ? '#2e7d32' : '#0277BD' ?>;">
      ROLE: <?= strtoupper($adminRole) ?>
    </span>
    <span class="live-count">
      <span class="live-wrap">
        <span style="display:flex; align-items:center; gap:6px;">
          <span class="dot green"></span>
          Live:
          <span id="liveActiveCount"><?= (int) $liveActive ?></span>
        </span>
        <span style="color:#c9bdb2; font-size:11px;">|</span>
        <span style="display:flex; align-items:center; gap:6px;">
          <span class="dot yellow"></span>
          Login:
          <span id="liveUsersCount"><?= (int) $liveUsers ?></span>
        </span>
      </span>
    </span>
  </div>

  <div class="grid-5">
    <div class="card bg-green">
      <i class="fas fa-arrow-circle-down"></i>
      <span id="totDepAmount">₹<?= number_format($totDep, 2) ?></span>
      <small>Total Deposit</small>
    </div>
    <div class="card bg-red">
      <i class="fas fa-arrow-circle-up"></i>
      <span id="totWithAmount">₹<?= number_format($totWith, 2) ?></span>
      <small>Total Withdrawals</small>
    </div>

    <?php $pnl = $totDep - $totWith; ?>
    <div class="card <?= $pnl >= 0 ? 'bg-green' : 'bg-red' ?>" style="<?= $pnl >= 0 ? 'border-top: 6px solid #2e7d32;' : 'border-top: 6px solid #c62828;' ?>">
      <i class="fas <?= $pnl >= 0 ? 'fa-chart-line' : 'fa-chart-line' ?>" style="color: <?= $pnl >= 0 ? '#2e7d32' : '#c62828' ?>;"></i>
      <span>₹<?= number_format($pnl, 2) ?></span>
      <small>Profit and Loss</small>
    </div>

    <div class="card bg-blue">
      <i class="fas fa-exchange-alt"></i>
      <span id="bidTradeAmount">₹<?= number_format($bidTrade, 2) ?></span>
      <small>Bid Trade</small>
    </div>

    <div class="card bg-purple">
      <i class="fas fa-rotate-left"></i>
      <span id="bidRefundAmount">₹<?= number_format($bidRefund, 2) ?></span>
      <small>Bid Refund</small>
    </div>

    <div class="card bg-orange">
      <i class="fas fa-trophy"></i>
      <span id="totalWiningAmount">₹<?= number_format($totalWining, 2) ?></span>
      <small>Total Wining (<?= $label ?>)</small>
    </div>

    <div class="card bg-wallet">
      <i class="fas fa-wallet"></i>
      <span>₹<?= number_format($walletBal, 2) ?></span>
      <small>Users Wallet Balance</small>
    </div>

    <div class="card bg-orange" style="border-top: 6px solid #ffb300;">
      <i class="fas fa-gift" style="color:#ffb300;"></i>
      <span>₹<?= number_format($totBonus, 2) ?></span>
      <small>Total Bonus</small>
    </div>

    <div class="card bg-purple" style="border-top: 6px solid #8e24aa;">
      <i class="fas fa-plus-circle" style="color:#8e24aa;"></i>
      <span>₹<?= number_format($walletBal + $totBonus, 2) ?></span>
      <small>Balance + Bonus</small>
    </div>

    <div class="card" style="border-top: 6px solid #00bcd4;">
      <i class="fas fa-user-plus" style="color:#00bcd4;"></i>
      <span><?= (int) $todayRegistrations ?></span>
      <small>Registrations</small>
    </div>

    <a class="card bg-blue" href="deposit_req.php">
      <i class="fas fa-money-check"></i>
      <span id="depReqCount"><?= (int) $depReq ?></span>
      <small>Pending Deposits</small>
    </a>

    <a class="card bg-orange" href="withdrawal_req.php">
      <i class="fas fa-hand-holding-usd"></i>
      <span id="withReqCount"><?= (int) $withReq ?></span>
      <small>Pending Withdrawals</small>
    </a>

    <a class="card" href="user_bank_kyc.php" style="border-top: 6px solid #5d4037; text-decoration: none;">
      <i class="fa-solid fa-building-columns" style="color:#5d4037;"></i>
      <span><?= (int) $bankKYCPending ?></span>
      <small>Bank KYC Pending</small>
    </a>

    <a class="card" href="user_upi_kyc.php" style="border-top: 6px solid #0071cc; text-decoration: none;">
      <i class="fa-solid fa-credit-card" style="color:#0071cc;"></i>
      <span><?= (int) $upiKYCPending ?></span>
      <small>UPI KYC Pending</small>
    </a>
    <?php
    require_once 'includes/db.php';
    if ($showCrypto): ?>
      <a class="card bg-purple" href="user_crypto_kyc.php">
        <i class="fas fa-coins"></i>
        <span id="cryptoKYCCount"><?= (int) $cryptoKYC ?></span>
        <small>Crypto KYC Pending</small>
      </a>
      <?php
      require_once 'includes/db.php';
    endif; ?>
  </div>
  </div><!-- /.wrapper -->

  <div id="bannerModal" class="modal-overlay" role="dialog" aria-modal="true">
    <div class="modal-box">
      <h3>Banner text</h3>
      <form method="post" autocomplete="off">
        <textarea name="banner_text" maxlength="200" required><?= htmlspecialchars($bannerText) ?></textarea>
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeBannerModal()">Cancel</button>
          <button type="submit" class="btn-save" name="banner_text_submit" value="1">Save</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function showCustom(e) {
      e.preventDefault();
      var f = document.getElementById('cForm');
      if (!f) return;
      f.style.display = (f.style.display === 'flex') ? 'none' : 'flex';
    }

    // --- Smart auto-reload (performance) ---
    var reloadTimer = null;
    var reloadRunning = false;

    function startAutoReload() {
      if (reloadTimer) return;
      reloadTimer = setInterval(function () {
        if (document.hidden) return;
        if (reloadRunning) return;
        reloadRunning = true;
        // Cache-busting reload
        var url = new URL(window.location.href);
        url.searchParams.set('t', Date.now());
        window.location.href = url.toString();
      }, 12000); // Increased interval slightly for stability
    }

    function stopAutoReload() {
      if (!reloadTimer) return;
      clearInterval(reloadTimer);
      reloadTimer = null;
    }

    document.addEventListener('visibilitychange', function() {
      if (document.hidden) {
        stopAutoReload();
      } else {
        startAutoReload();
        loadChatCnt();
        loadDashboardData();
      }
    });

    function openBannerModal(e) {
      if (e) e.preventDefault();
      var m = document.getElementById('bannerModal');
      if (m) {
        m.style.display = 'flex';
        stopAutoReload();
      }
    }
    function closeBannerModal() {
      var m = document.getElementById('bannerModal');
      if (m) {
        m.style.display = 'none';
        startAutoReload();
      }
    }

    var bannerModalEl = document.getElementById('bannerModal');
    if (bannerModalEl) {
      bannerModalEl.addEventListener('click', function (e) {
        if (e.target === this) { closeBannerModal(); }
      });
    }

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        var m = document.getElementById('bannerModal');
        if (m && m.style.display === 'flex') closeBannerModal();
      }
    });

    // ✅ PERMANENT FIX: unread_count must be a number; if HTML/login page => 0
    function isProbablyHtml(t) {
      if (!t) return false;
      var s = String(t).trim().toLowerCase();
      return s.startsWith('<!doctype') || s.startsWith('<html') || s.includes('<body') || s.includes('<head');
    }

    function toSafeInt(v) {
      var n = parseInt(String(v).trim(), 10);
      return Number.isFinite(n) && n >= 0 ? n : 0;
    }

    // safe fetch helper (prevents overlapping)
    var chatFetchInFlight = false;

    async function loadChatCnt() {
      if (chatFetchInFlight || document.hidden) return;
      chatFetchInFlight = true;

      var el = document.getElementById('chatCnt');
      if (!el) { chatFetchInFlight = false; return; }

      try {
        var url = 'admin_live_chat.php?action=unread_count&_=' + Date.now();
        var res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });

        var ct = (res.headers.get('content-type') || '').toLowerCase();
        var txt = await res.text();

        if (!res.ok) { el.textContent = '0'; return; }

        if (ct.includes('application/json')) {
          try {
            var j = JSON.parse(txt);
            var n = toSafeInt(j.unread ?? j.unread_count ?? j.count ?? 0);
            el.textContent = String(n);
            return;
          } catch (e) { el.textContent = '0'; return; }
        }

        if (isProbablyHtml(txt)) { el.textContent = '0'; return; }

        var cleaned = String(txt).trim();
        if (/^\d+$/.test(cleaned)) { el.textContent = String(toSafeInt(cleaned)); return; }

        el.textContent = '0';
      } catch (e) {
        el.textContent = '0';
      } finally {
        chatFetchInFlight = false;
      }
    }

    // live counts are already on a lightweight endpoint ✅
    var dashFetchInFlight = false;
    function loadDashboardData() {
      if (dashFetchInFlight || document.hidden) return;
      dashFetchInFlight = true;

      fetch('admin_dashboard_data.php?_=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' })
        .then(r => r.json())
        .then(function(d) {
          if (typeof d.liveUsers !== 'undefined') {
            var el = document.getElementById('liveUsersCount');
            if (el) el.textContent = d.liveUsers;
          }
          if (typeof d.liveActive !== 'undefined') {
            var el = document.getElementById('liveActiveCount');
            if (el) el.textContent = d.liveActive;
          }
        })
        .catch(function() { })
        .finally(function() { dashFetchInFlight = false; });
    }

    /* ✅ WINNING TRIGGER on refresh (THROTTLED + ALL MARKETS) */
    var WIN_FROM = "<?= htmlspecialchars($jsFrom) ?>";
    var WIN_TO = "<?= htmlspecialchars($jsTo) ?>";
    var WIN_KEY = "<?= htmlspecialchars(WINS_TRIGGER_KEY) ?>";
    var WIN_URL = "<?= htmlspecialchars(WINS_COMPUTE_URL) ?>";
    var WIN_MARKETS = <?= json_encode(array_values($allMarkets), JSON_UNESCAPED_SLASHES) ?>;

    function sleep(ms) { return new Promise(res => setTimeout(res, ms)); }

    async function triggerWinsComputeOnce() {
      try {
        if (document.hidden) return;

        var throttleKey = 'LL_last_win_trigger_' + WIN_FROM + '_' + WIN_TO;
        var now = Date.now();
        var last = parseInt(localStorage.getItem(throttleKey) || '0', 10);
        if (now - last < 60000) return;

        localStorage.setItem(throttleKey, String(now));

        for (var i = 0; i < WIN_MARKETS.length; i++) {
          var mkt = (WIN_MARKETS[i] || '').toString().trim();
          if (!mkt) continue;

          var qs = new URLSearchParams({
            trigger: '1',
            market: mkt,
            from: WIN_FROM,
            to: WIN_TO,
            key: WIN_KEY,
            _: String(Date.now())
          });

          fetch(WIN_URL + "?" + qs.toString(), { cache: 'no-store', credentials: 'same-origin' })
            .then(r => r.text())
            .then(t => console.log('✅ Win compute:', mkt, t))
            .catch(e => console.error('❌ Win compute error:', mkt, e));

          await sleep(220);
        }
      } catch (e) {
        console.error(e);
      }
    }

    function startLoops() {
      loadChatCnt();
      loadDashboardData();
      triggerWinsComputeOnce();

      setInterval(loadChatCnt, 20000);
      setInterval(loadDashboardData, 25000);
    }

    document.addEventListener('DOMContentLoaded', function() {
      startLoops();
      startAutoReload();
      setTimeout(function() { reloadRunning = false; }, 1000);
    });
  </script>
</body>
<?php if (isset($db))
  $db->close(); ?>

</html>











