<?php
require_once 'includes/db.php';
// all_bid_data.php – All bids list with filters, search, pagination, WIN status, Back to Dashboard, and Amount sum
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Kolkata');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $db = db();
  
  // ✅ Helper: check column exists
  $hasColumn = function (mysqli $db, string $table, string $col): bool {
    $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
    $stmt->bind_param("ss", $table, $col);
    $stmt->execute();
    $n = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $n > 0;
  };
  
  // Ensure bid_modified column exists in bid table (self-healing database schema)
  if (!$hasColumn($db, 'bid', 'bid_modified')) {
      @$db->query("ALTER TABLE bid ADD COLUMN bid_modified TIMESTAMP NULL DEFAULT NULL");
  }
  
  // ✅ Fetch Markets for Dropdowns
  $allMarkets = [];
  $mRes = $db->query("SELECT market_name FROM markets ORDER BY market_name ASC");
  while($mr = $mRes->fetch_assoc()) $allMarkets[] = $mr['market_name'];

  // ✅ Handle Bid Update via AJAX or Form POST
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bid') {
      $bidId = (int)($_POST['bid_id'] ?? 0);
      $sourceTable = $_POST['source_table'] ?? 'bid';
      $u_userId = $_POST['user_id'] ?? '';
      $u_market = $_POST['market'] ?? '';
      $u_gameType = $_POST['game_type'] ?? '';
      $u_sessType = $_POST['session_type'] ?? '';
      $u_amt = $_POST['amount'] ?? '';
      $u_time = $_POST['bid_time'] ?? '';
      $u_bidNum = $_POST['bid_number'] ?? '';

      if ($sourceTable === 'gali_bids') {
          $sql = "UPDATE gali_bids SET user_id=?, market_name=?, game_type=?, amount=?, created_at=?, digit=? WHERE id=?";
          $st = $db->prepare($sql);
          $st->bind_param("ssssssi", $u_userId, $u_market, $u_gameType, $u_amt, $u_time, $u_bidNum, $bidId);
          $st->execute();
          $st->close();
      } else {
          // Prepare Update based on Game Type
          $digitCol = '';
          $gtU = strtoupper(trim((string)$u_gameType));
          $gtU = str_replace(' ', '_', $gtU);
          if ($gtU === 'SINGLE_DIGIT') $digitCol = 'single_digit';
          elseif ($gtU === 'JODI_DIGIT' || $gtU === 'JODI') $digitCol = 'jodi_digit';
          elseif ($gtU === 'SINGLE_PANA') $digitCol = 'single_pana';
          elseif ($gtU === 'DOUBLE_PANA') $digitCol = 'double_pana';
          elseif ($gtU === 'TRIPLE_PANA') $digitCol = 'triple_pana';
          elseif ($gtU === 'HALF_SANGAM') $digitCol = 'half_sangam'; // or whichever it uses
          elseif ($gtU === 'FULL_SANGAM') $digitCol = 'full_sangam';

          $sql = "UPDATE bid SET user_id=?, market=?, game_type=?, session_type=?, amount=?, bid_time=?";
          $params = [$u_userId, $u_market, $u_gameType, $u_sessType, $u_amt, $u_time];
          $types = "ssssss";

          if ($digitCol) {
              $sql .= ", `$digitCol` = ?";
              $params[] = $u_bidNum;
              $types .= "s";
          }

          if ($hasColumn($db, 'bid', 'bid_modified')) {
              $sql .= ", `bid_modified` = CURRENT_TIMESTAMP";
          }
          
          $sql .= " WHERE id=?";
          $params[] = $bidId;
          $types .= "i";

          $st = $db->prepare($sql);
          $st->bind_param($types, ...$params);
          $st->execute();
          $st->close();
      }
      
      // Redirect to refresh
      $curUrl = $_SERVER['REQUEST_URI'];
      header("Location: $curUrl");
      exit;
  }

  // ✅ Detect optional HS/FS columns
  $b_has_open_digit = $hasColumn($db, 'bid', 'open_digit');
  $b_has_close_digit = $hasColumn($db, 'bid', 'close_digit');

  $b_has_open_pana_x = $hasColumn($db, 'bid', 'open_pana_x');
  $b_has_close_pana_x = $hasColumn($db, 'bid', 'close_pana_x');

  $b_has_open_pana = $hasColumn($db, 'bid', 'open_pana');
  $b_has_close_pana = $hasColumn($db, 'bid', 'close_pana');
  
  $b_has_bid_modified = $hasColumn($db, 'bid', 'bid_modified');

  $b_has_gali = false;
  $chkGali = $db->query("SHOW TABLES LIKE 'gali_bids'");
  if ($chkGali && $chkGali->num_rows > 0) {
      $b_has_gali = true;
  }

  $w_has_open_digit = $hasColumn($db, 'wining', 'open_digit');
  $w_has_close_digit = $hasColumn($db, 'wining', 'close_digit');

  $w_has_open_pana_x = $hasColumn($db, 'wining', 'open_pana_x');
  $w_has_close_pana_x = $hasColumn($db, 'wining', 'close_pana_x');

  $w_has_open_pana = $hasColumn($db, 'wining', 'open_pana');
  $w_has_close_pana = $hasColumn($db, 'wining', 'close_pana');

  $B_OPEN_PANA_COL = $b_has_open_pana_x ? 'open_pana_x' : ($b_has_open_pana ? 'open_pana' : '');
  $B_CLOSE_PANA_COL = $b_has_close_pana_x ? 'close_pana_x' : ($b_has_close_pana ? 'close_pana' : '');

  $W_OPEN_PANA_COL = $w_has_open_pana_x ? 'open_pana_x' : ($w_has_open_pana ? 'open_pana' : '');
  $W_CLOSE_PANA_COL = $w_has_close_pana_x ? 'close_pana_x' : ($w_has_close_pana ? 'close_pana' : '');

  $range = $_GET['range'] ?? 'today';   // today|all|custom
  $from = $_GET['from'] ?? '';
  $to = $_GET['to'] ?? '';
  $q = trim($_GET['q'] ?? '');
  $perPage = 10;
  $page = max(1, intval($_GET['page'] ?? 1));
  $offset = ($page - 1) * $perPage;

  // --- Build WHERE ---
  $conds = [];
  $params = [];
  $types = '';

  if ($range === 'today') {
    $conds[] = "b.bid_time BETWEEN ? AND ?";
    $params[] = date('Y-m-d 00:00:00');
    $params[] = date('Y-m-d 23:59:59');
    $types .= 'ss';
  } elseif ($range === 'custom' && $from && $to) {
    $conds[] = "b.bid_time BETWEEN ? AND ?";
    $params[] = "$from 00:00:00";
    $params[] = "$to 23:59:59";
    $types .= 'ss';
  }
  if ($q !== '') {
    $conds[] = "(b.user_id LIKE ? OR u.name LIKE ? OR b.market LIKE ? OR b.game_type LIKE ?)";
    $like = "%$q%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
  }
  $whereBase = $conds ? 'WHERE ' . implode(' AND ', $conds) : 'WHERE 1=1';

  // ✅ Exclude refunded bids (Main)
  $bidWhere = $whereBase . " AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = b.id AND br.source_table = 'bid')";

  $countParams = $params;
  $countTypes = $types;

  if ($b_has_gali) {
      $galiWhere = str_replace('b.', 'g.', $whereBase);
      $galiWhere = str_replace('g.market', 'g.market_name', $galiWhere);
      $galiWhere = str_replace('g.bid_time', 'g.created_at', $galiWhere);
      $galiWhere .= " AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = g.id AND br.source_table IN ('gali_bid','gali_bids'))";
      
      $countSumSql = "
        SELECT SUM(cnt) AS cnt, SUM(sumamt) AS sumamt FROM (
          SELECT COUNT(*) AS cnt, COALESCE(SUM(b.amount),0) AS sumamt
          FROM bid b
          LEFT JOIN users u ON u.user_id = b.user_id
          $bidWhere
          UNION ALL
          SELECT COUNT(*) AS cnt, COALESCE(SUM(g.amount),0) AS sumamt
          FROM gali_bids g
          LEFT JOIN users u ON u.user_id = g.user_id COLLATE utf8mb4_general_ci
          $galiWhere
        ) sub";
      $countParams = array_merge($params, $params);
      $countTypes = $types . $types;
  } else {
      $countSumSql = "SELECT COUNT(*) AS cnt, COALESCE(SUM(b.amount),0) AS sumamt
          FROM bid b
          LEFT JOIN users u ON u.user_id = b.user_id
          $bidWhere";
  }

  $stmtC = $db->prepare($countSumSql);
  if (!$stmtC) {
      die("Prepare failed on count: " . $db->error . " <br> SQL: " . htmlspecialchars($countSumSql));
  }
  if ($countTypes) {
      $refsC = [];
      $refsC[] = &$countTypes;
      foreach ($countParams as $k => $param) {
          $refsC[] = &$countParams[$k];
      }
      call_user_func_array([$stmtC, 'bind_param'], $refsC);
  }
  $stmtC->execute();
  $row = $stmtC->get_result()->fetch_assoc();
  $total = (int) ($row['cnt'] ?? 0);
  $totalSum = (float) ($row['sumamt'] ?? 0);
  $stmtC->close();

  $pages = max(1, (int) ceil($total / $perPage));
  $page = min($page, $pages);
  $offset = ($page - 1) * $perPage;

  // ✅ Entry range for pagination text
  $entryFrom = ($total === 0) ? 0 : ($offset + 1);
  $entryTo = ($total === 0) ? 0 : min($offset + $perPage, $total);

  // --- Dynamic SELECT fields ---
  $selOpenDigit = $b_has_open_digit ? "b.open_digit" : "''";
  $selCloseDigit = $b_has_close_digit ? "b.close_digit" : "''";

  $selOpenPana = $B_OPEN_PANA_COL ? "b.`$B_OPEN_PANA_COL`" : "''";
  $selClosePana = $B_CLOSE_PANA_COL ? "b.`$B_CLOSE_PANA_COL`" : "''";
  
  $selBidModified = $b_has_bid_modified ? "b.bid_modified" : "''";

  // --- Fetch page of bids (UNION or Single) ---
  // Main
  $sqlMain = "SELECT b.id, b.user_id, IFNULL(u.name,'') AS name,
                   b.market as market, 
                   b.session_type as session_type, 
                   b.game_type as game_type,
                   b.single_digit as single_digit, 
                   b.jodi_digit as jodi_digit,
                   b.single_pana as single_pana, 
                   b.double_pana as double_pana, 
                   b.triple_pana as triple_pana,
                   $selOpenDigit as open_digit,
                   $selCloseDigit as close_digit,
                   $selOpenPana as open_pana,
                   $selClosePana as close_pana,
                   '' as left_digit,
                   '' as right_digit,
                   b.amount, b.bid_time, $selBidModified as bid_modified,
                   'bid' as source_table
            FROM bid b
            LEFT JOIN users u ON u.user_id = b.user_id
            $bidWhere";

  $mainParams = $params;
  $mainTypes = $types;

  if ($b_has_gali) {
      $sqlMain .= "
            UNION ALL
            SELECT g.id, g.user_id COLLATE utf8mb4_general_ci AS user_id, IFNULL(u.name,'') AS name,
                   g.market_name COLLATE utf8mb4_general_ci as market, 
                   'O/C' as session_type, 
                   g.game_type COLLATE utf8mb4_general_ci as game_type,
                   g.digit COLLATE utf8mb4_general_ci as single_digit, 
                   '' as jodi_digit,
                   '' as single_pana, 
                   '' as double_pana, 
                   '' as triple_pana,
                   '' as open_digit,
                   '' as close_digit,
                   '' as open_pana,
                   '' as close_pana,
                   '' as left_digit,
                   '' as right_digit,
                   g.amount, g.created_at as bid_time, '' as bid_modified,
                   'gali_bids' as source_table
            FROM gali_bids g
            LEFT JOIN users u ON u.user_id = g.user_id COLLATE utf8mb4_general_ci
            $galiWhere";
      
      $mainParams = array_merge($params, $params);
      $mainTypes = $types . $types;
  }

  $sql = "$sqlMain ORDER BY bid_time DESC LIMIT ? OFFSET ?";
  $finalParams = $mainParams;
  $finalTypes = $mainTypes . 'ii';

  // Params: Main + Limit
  $finalParams[] = $perPage;
  $finalParams[] = $offset;

  $stmt = $db->prepare($sql);
  if (!$stmt) {
      die("Prepare failed on line 205: " . $db->error . " <br> SQL: " . $sql);
  }
  // Need to use bind_param with array unpacking
  // But define bindings references
  $refs = [];
  $refs[] = &$finalTypes;
  foreach ($finalParams as $k => $param) {
    $refs[] = &$finalParams[$k];
  }
  call_user_func_array([$stmt, 'bind_param'], $refs);

  $stmt->execute();
  $bids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // --- Fetch wins ---
  $wSelOpenDigit = $w_has_open_digit ? "open_digit" : "'' AS open_digit";
  $wSelCloseDigit = $w_has_close_digit ? "close_digit" : "'' AS close_digit";

  $wSelOpenPana = $W_OPEN_PANA_COL ? "`$W_OPEN_PANA_COL` AS open_pana" : "'' AS open_pana";
  $wSelClosePana = $W_CLOSE_PANA_COL ? "`$W_CLOSE_PANA_COL` AS close_pana" : "'' AS close_pana";

  $winKeys = [];
  $wQ = $db->query("
        SELECT user_id, market, session_type,
               single_digit, jodi_digit,
               single_pana, double_pana, triple_pana,
               $wSelOpenDigit,
               $wSelCloseDigit,
               $wSelOpenPana,
               $wSelClosePana
        FROM wining
    ");
  while ($w = $wQ->fetch_assoc()) {
    $winKeys[implode('|', [
      $w['user_id'],
      $w['market'],
      $w['session_type'],
      (string) $w['single_digit'],
      (string) $w['jodi_digit'],
      (string) $w['single_pana'],
      (string) $w['double_pana'],
      (string) $w['triple_pana'],
      (string) $w['open_digit'],
      (string) $w['close_digit'],
      (string) $w['open_pana'],
      (string) $w['close_pana'],
    ])] = true;
  }
  $wQ->free();
  $db->close();

} catch (mysqli_sql_exception $e) {
  echo "<h1>Database Error</h1><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
  exit;
}

function bid_key(array $b): string
{
  return implode('|', [
    $b['user_id'],
    $b['market'],
    $b['session_type'],
    (string) $b['single_digit'],
    (string) $b['jodi_digit'],
    (string) $b['single_pana'],
    (string) $b['double_pana'],
    (string) $b['triple_pana'],
    (string) ($b['open_digit'] ?? ''),
    (string) ($b['close_digit'] ?? ''),
    (string) ($b['open_pana'] ?? ''),
    (string) ($b['close_pana'] ?? ''),
  ]);
}
function show_val($v)
{
  return ($v !== "" && $v !== null) ? htmlspecialchars((string) $v) : '-';
}
function has_any($arr, $keys)
{
  foreach ($keys as $k) {
    if (isset($arr[$k]) && $arr[$k] !== "" && $arr[$k] !== null)
      return true;
  }
  return false;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>All Bids – Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
  <style>
    :root {
      --red: #e53935;
      --red-light: #ffe5e5;
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
      min-height: 100vh;
      padding: 24px 18px;
      color: #212529
    }

    a {
      color: inherit;
      text-decoration: none
    }

    .filter-bar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
      padding: 12px 20px;
      border-radius: var(--radius);
      backdrop-filter: blur(10px);
      background: var(--glass);
      box-shadow: var(--shadow)
    }

    .filter-bar h1 {
      margin-right: 24px;
      font-size: 18px;
      font-weight: 600;
      color: var(--brown)
    }

    .top-btn {
      border: none;
      background: var(--brown);
      color: #fff;
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      margin-right: auto
    }

    .btn {
      background: #fff;
      color: var(--red);
      border: 2px solid var(--red);
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: .15s
    }

    .btn:hover:not(.active) {
      background: var(--red-light);
    }

    .btn.active {
      background: var(--red);
      color: #fff;
      border: none;
    }

    #customForm input[type=date] {
      border: 1px solid #ccc;
      border-radius: 8px;
      padding: 5px 8px;
      font-size: 12px
    }

    #searchInput {
      margin-left: auto;
      width: 220px;
      max-width: 100%;
      padding: 6px 10px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 12px
    }

    .summary {
      max-width: 980px;
      margin: 8px auto;
      font-size: 14px;
      font-weight: 600;
      color: var(--brown)
    }

    .table-wrap {
      overflow-x: auto
    }

    table {
      width: 100%;
      min-width: 1180px;
      border-collapse: collapse;
      font-size: 12px
    }

    th,
    td {
      padding: 6px 4px;
      text-align: center;
      font-size: 12px
    }

    th {
      background: var(--brown);
      color: #fff;
      font-weight: 600;
      position: sticky;
      top: 0
    }

    tbody tr:nth-child(even) {
      background: #fafafa
    }

    tbody tr:hover {
      background: #fffbea;
      box-shadow: 0 0 0 9999px #fffbea inset
    }

    .user {
      color: var(--blue);
      font-weight: 600
    }

    .market {
      color: var(--brown);
      font-weight: 600
    }

    .bid-val {
      display: inline-block;
      padding: 2px 6px;
      border-radius: 6px;
      background: #e0d7ce;
      font-weight: 600;
      font-size: 12px
    }

    .amount {
      background: #d4edda;
      color: #155724;
      border-radius: 6px;
      padding: 2px 6px;
      font-weight: 600;
      font-size: 12px
    }

    .session-open {
      color: var(--green);
      font-weight: 600
    }

    .session-close {
      color: var(--red);
      font-weight: 600
    }

    .status-win {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 20px;
      background: linear-gradient(90deg, #ff1877 10%, #ffae0d 90%);
      color: #fff;
      font-weight: 600;
      font-size: 12px;
      animation: tada 1s
    }

    .tag-game {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 11px;
      color: #fff
    }

    .tag-normal {
      background: #607d8b
    }

    .tag-hs {
      background: #8e24aa
    }

    .tag-fs {
      background: #1565c0
    }

    /* ✅ Pagination (only Previous + text + Next) */
    .pagination {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 12px;
      margin: 20px 0;
      font-size: 14px
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
      opacity: 1;
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

    @media(max-width:650px) {
      body {
        padding: 18px 12px
      }

      .filter-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 14px
      }

      .filter-bar h1 {
        margin-right: 0
      }

      .summary {
        text-align: center
      }

      table {
        min-width: 0
      }

      th,
      td {
        padding: 4px 2px;
        font-size: 10px
      }

      #searchInput {
        width: 100%;
        margin-left: 0
      }
    }

    /* Modal Styles */
    #editModal {
      display: none;
      position: fixed;
      z-index: 10001;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
      align-items: center;
      justify-content: center;
    }
    .modal-card {
      background: #fff;
      border-radius: 12px;
      width: 100%;
      max-width: 450px;
      padding: 25px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      animation: zoomIn 0.3s;
    }
    .modal-card h2 { font-size: 18px; color: var(--brown); margin-bottom: 20px; text-align: center; }
    .m-group { margin-bottom: 15px; }
    .m-group label { display: block; font-size: 11px; font-weight: 600; color: #666; margin-bottom: 4px; text-transform: uppercase; }
    .m-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; box-sizing: border-box; }
    .m-btns { display: flex; gap: 10px; margin-top: 20px; }
    .m-btn-save { flex: 1; background: var(--brown); color: #fff; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; }
    .m-btn-back { flex: 1; background: #eee; color: #333; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; text-align: center; }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

  <div class="filter-bar">
    <a href="admin_dashboard.php" class="top-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <h1>All Bids</h1>

    <a href="?range=today" class="btn<?= $range === 'today' ? ' active' : ''; ?>">Today</a>
    <a href="?range=all" class="btn<?= $range === 'all' ? ' active' : ''; ?>">All&nbsp;Time</a>

    <button type="button" class="btn<?= $range === 'custom' ? ' active' : ''; ?>"
      onclick="document.getElementById('customForm').style.display='inline-flex';">
      Customize
    </button>

    <form id="customForm" method="get"
      style="display:<?= $range === 'custom' ? 'inline-flex' : 'none'; ?>;gap:8px;align-items:center">
      <input type="hidden" name="range" value="custom">
      <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" required>
      <span>to</span>
      <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" required>
      <button class="btn active" style="padding:5px 12px;">Apply</button>
    </form>

    <input type="text" id="searchInput" placeholder="Search…" value="<?= htmlspecialchars($q) ?>">
  </div>

  <div class="summary">
    Total Amount:&nbsp;₹&nbsp;<?= number_format($totalSum) ?>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>User</th>
          <th>Market</th>
          <th>Game Type</th>
          <th>Session</th>
          <th>Bid Number</th>
          <th>Amt&nbsp;(₹)</th>
          <th>Bid Time</th>
          <th>Bid Modified</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        require_once 'includes/db.php';
        if (!$bids): ?>
          <tr>
            <td colspan="8" style="font-weight:600;color:var(--red);text-align:center;">No bids found</td>
          </tr>
          <?php
          require_once 'includes/db.php';
        else:
          $sr = $offset + 1;
          foreach ($bids as $b):
            $gt = strtoupper(trim((string) ($b['game_type'] ?? '')));
            $sessRaw = strtolower((string) $b['session_type']);

            // Logic to determine Display Session and Bid Number
            $dispSession = ucfirst($sessRaw) ?: '—';
            $dispBid = '—';
            $gameLabel = $gt ?: '';
            $gameClass = 'tag-normal';

            // Helper to check if value exists (including '0')
            $hasVal = function ($v) {
              return $v !== "" && $v !== null && $v !== false && (string) $v !== '—';
            };

            // --- 1. SPECIAL GAMES (Sangam/Half Sangam) ---
            if ($gt === 'HALF_SANGAM') {
              $gameLabel = 'Half Sangam';
              $gameClass = 'tag-hs';
              if ($hasVal($b['open_digit']) && $hasVal($b['close_pana'])) {
                $dispSession = 'Open Digit - Close Pana';
                $dispBid = $b['open_digit'] . ' - ' . $b['close_pana'];
              } elseif ($hasVal($b['open_pana']) && $hasVal($b['close_digit'])) {
                $dispSession = 'Open Pana - Close Digit';
                $dispBid = $b['open_pana'] . ' - ' . $b['close_digit'];
              } else {
                $dispSession = 'Half Sangam';
                $dispBid = '—';
              }
            } elseif ($gt === 'FULL_SANGAM') {
              $gameLabel = 'Full Sangam';
              $gameClass = 'tag-fs';
              $dispSession = 'Open Pana - Close Pana';
              $dispBid = $b['open_pana'] . ' - ' . $b['close_pana'];
            }
            // --- 2. JODI ---
            elseif (strpos($gt, 'JODI') !== false || $hasVal($b['jodi_digit'])) {
              $gameLabel = 'Jodi Digit';
              $dispSession = 'Jodi';
              $dispBid = $b['jodi_digit'];
            }
            // --- 3. PANAS ---
            elseif (strpos($gt, 'SINGLE_PANA') !== false) {
              $gameLabel = 'Single Pana';
              $dispBid = $b['single_pana'];
            } elseif (strpos($gt, 'DOUBLE_PANA') !== false) {
              $gameLabel = 'Double Pana';
              $dispBid = $b['double_pana'];
            } elseif (strpos($gt, 'TRIPLE_PANA') !== false) {
              $gameLabel = 'Triple Pana';
              $dispBid = $b['triple_pana'];
            } elseif (strpos($gt, 'PANA') !== false) {
              $gameLabel = ucwords(strtolower(str_replace('_', ' ', $gt)));
              if ($hasVal($b['single_pana']))
                $dispBid = $b['single_pana'];
              elseif ($hasVal($b['double_pana']))
                $dispBid = $b['double_pana'];
              elseif ($hasVal($b['triple_pana']))
                $dispBid = $b['triple_pana'];
              elseif ($hasVal($b['open_pana']))
                $dispBid = $b['open_pana'];
            }
            // --- 4. SINGLES ---
            elseif (strpos($gt, 'SINGLE') !== false || $hasVal($b['single_digit'])) {
              $gameLabel = 'Single Digit';
              $dispBid = $b['single_digit'];
            }
            // --- Fallback inference ---
            else {
              if ($hasVal($b['single_digit'])) {
                $gameLabel = 'Single Digit';
                $dispBid = $b['single_digit'];
              } elseif ($hasVal($b['jodi_digit'])) {
                $gameLabel = 'Jodi Digit';
                $dispBid = $b['jodi_digit'];
                $dispSession = 'Jodi [O/C]';
              } elseif ($hasVal($b['single_pana'])) {
                $gameLabel = 'Single Pana';
                $dispBid = $b['single_pana'];
              } elseif ($hasVal($b['double_pana'])) {
                $gameLabel = 'Double Pana';
                $dispBid = $b['double_pana'];
              } elseif ($hasVal($b['triple_pana'])) {
                $gameLabel = 'Triple Pana';
                $dispBid = $b['triple_pana'];
              }

              if (empty($gameLabel))
                $gameLabel = 'NORMAL';
            }

            // formatting
            if ($dispBid === '—' || (string) $dispBid === '')
              $dispBid = '<span style="color:#ccc;">—</span>';
            else
              $dispBid = htmlspecialchars((string) $dispBid);
            ?>
            <tr>
              <td><?= $sr++ ?></td>
              <td class="user"><?= htmlspecialchars($b['name'] ?: $b['user_id']) ?></td>
              <td class="market"><?= htmlspecialchars($b['market']) ?></td>

              <td><span class="tag-game <?= $gameClass ?>"><?= htmlspecialchars($gameLabel) ?></span></td>
              <td class="session-<?= strtolower(str_replace(' ', '', strtok($dispSession, '/'))) ?>">
                <?= htmlspecialchars($dispSession) ?>
              </td>

              <td><span class="bid-val" style="font-size:14px; font-weight:700;"><?= $dispBid ?></span></td>

              <td><span class="amount"><?= number_format((float) $b['amount']) ?></span></td>
              <td><?= date('d M Y, h:i A', strtotime($b['bid_time'])) ?></td>
              <td><?= $b['bid_modified'] ? date('d M Y, h:i A', strtotime($b['bid_modified'])) : '<span style="color:#ccc;">Not Modified</span>' ?></td>
              <td>
                <button type="button" class="btn" onclick='openEditModal(<?= htmlspecialchars(json_encode([
                    "id" => $b["id"],
                    "source_table" => $b["source_table"] ?? "bid",
                    "user_id" => $b["user_id"],
                    "market" => $b["market"],
                    "game_type" => $b["game_type"] ?: $gameLabel,
                    "session_type" => $b["session_type"],
                    "amount" => $b["amount"],
                    "bid_time" => $b["bid_time"],
                    "bid_number" => strip_tags($dispBid)
                ]), ENT_QUOTES, 'UTF-8') ?>)' style="padding: 4px 10px; font-size: 11px;">
                  <i class="fas fa-edit"></i> Edit
                </button>
              </td>
            </tr>
            <?php
            require_once 'includes/db.php';
          endforeach;
        endif; ?>
      </tbody>
    </table>
  </div>

  <?php
  require_once 'includes/db.php';
  // Always show pagination container if pages >= 1
  if ($pages >= 1): ?>
    <div class="pagination">
      <?php
      // Prev link
      if ($page > 1) {
        $pr = ['range' => $range, 'page' => $page - 1];
        if ($range === 'custom') {
          $pr['from'] = $from;
          $pr['to'] = $to;
        }
        if ($q !== '')
          $pr['q'] = $q;
        echo '<a href="?' . http_build_query($pr) . '">&laquo; Previous</a>';
      } else {
        echo '<span class="disabled">&laquo; Previous</span>';
      }

      // ✅ Page Info (only Page X of Y)
      echo '<span class="info">Page ' . $page . ' of ' . $pages . '</span>';

      // Next link
      if ($page < $pages) {
        $nx = ['range' => $range, 'page' => $page + 1];
        if ($range === 'custom') {
          $nx['from'] = $from;
          $nx['to'] = $to;
        }
        if ($q !== '')
          $nx['q'] = $q;
        echo '<a href="?' . http_build_query($nx) . '">Next &raquo;</a>';
      } else {
        echo '<span class="disabled">Next &raquo;</span>';
      }
      ?>
    </div>
  <?php endif; ?>

  <div id="editModal">
    <div class="modal-card">
      <form method="POST">
        <h2>Edit Bid Details</h2>
        <input type="hidden" name="action" value="save_bid">
        <input type="hidden" name="bid_id" id="m_bid_id">
        <input type="hidden" name="source_table" id="m_source_table" value="bid">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="m-group">
                <label>User ID</label>
                <input type="text" name="user_id" id="m_userId">
            </div>
            <div class="m-group">
                <label>Market Name</label>
                <select name="market" id="m_market" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; background: #fff; appearance: auto;">
                    <?php foreach($allMarkets as $m): ?>
                    <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="m-group">
                <label>Game Type</label>
                <select name="game_type" id="m_gameType" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; background: #fff; appearance: auto;">
                    <option value="SINGLE_DIGIT">Single Digit</option>
                    <option value="JODI_DIGIT">Jodi Digit</option>
                    <option value="SINGLE_PANA">Single Pana</option>
                    <option value="DOUBLE_PANA">Double Pana</option>
                    <option value="TRIPLE_PANA">Triple Pana</option>
                    <option value="HALF_SANGAM">Half Sangam</option>
                    <option value="FULL_SANGAM">Full Sangam</option>
                </select>
            </div>
            <div class="m-group">
                <label>Session Type</label>
                <select name="session_type" id="m_sessType" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; background: #fff; appearance: auto;">
                    <option value="Open">Open</option>
                    <option value="Close">Close</option>
                </select>
            </div>

            <div class="m-group">
                <label>Bid Number</label>
                <input type="text" name="bid_number" id="m_bidNum">
            </div>
            <div class="m-group">
                <label>Amount</label>
                <input type="text" name="amount" id="m_amount">
            </div>

            <div class="m-group" style="grid-column: span 2;">
                <label>Bid Time</label>
                <input type="text" name="bid_time" id="m_bidTime">
            </div>
        </div>

        <div class="m-btns">
          <button type="submit" class="m-btn-save">Save Changes</button>
          <button type="button" class="m-btn-back" onclick="closeModal()">Back to Bids Overview</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openEditModal(data) {
      document.getElementById('m_bid_id').value = data.id;
      if (document.getElementById('m_source_table')) {
          document.getElementById('m_source_table').value = data.source_table || 'bid';
      }
      document.getElementById('m_userId').value = data.user_id;
      document.getElementById('m_market').value = data.market;
      let gt = (data.game_type || '').toUpperCase().trim().replace(/ /g, '_');
      document.getElementById('m_gameType').value = gt;
      document.getElementById('m_sessType').value = data.session_type;
      document.getElementById('m_amount').value = data.amount;
      document.getElementById('m_bidTime').value = data.bid_time;
      document.getElementById('m_bidNum').value = data.bid_number;
      
      document.getElementById('editModal').style.display = 'flex';
    }

    function closeModal() {
      document.getElementById('editModal').style.display = 'none';
    }

    // Close when clicking outside
    window.onclick = function(event) {
      if (event.target == document.getElementById('editModal')) closeModal();
    }

    document.getElementById('searchInput').addEventListener('keyup', function() {
      var t = document.getElementById('searchInput').value.toLowerCase();
      document.querySelectorAll('tbody tr').forEach(function(r) {
        r.style.display = r.innerText.toLowerCase().includes(t) ? '' : 'none';
      });
    });
  </script>
</body>

</html>
