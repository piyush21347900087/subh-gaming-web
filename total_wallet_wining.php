<?php
require_once 'includes/db.php';
// total_wallet_wining.php – Admin: Total Wining Balance with filters, search, pagination, referral, styled like all_bid_data.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

// Database connection
$db = db();

// Params
$range = $_GET['range'] ?? 'today';    // today|all|custom
$order = $_GET['order'] ?? '';         // desc|asc
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;

// Build WHERE for date
$whereWin = '';
$paramsWin = [];
$typesWin = '';
if ($range === 'today') {
  $whereWin = "AND w.win_time BETWEEN ? AND ?";
  $paramsWin = [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')];
  $typesWin = 'ss';
} elseif ($range === 'custom' && $from && $to) {
  $whereWin = "AND w.win_time BETWEEN ? AND ?";
  $paramsWin = ["$from 00:00:00", "$to 23:59:59"];
  $typesWin = 'ss';
}

// Fetch all users
$users = $db->query("SELECT id,name,user_id FROM users")
  ->fetch_all(MYSQLI_ASSOC);

// Compute wining per user
$data = [];
$totalSum = 0;
$sqlWin = "SELECT COALESCE(SUM(w.wining_balance),0) AS win,
                  MAX(w.win_time) AS last_win_time
           FROM wining w
           WHERE w.user_id = ? 
           AND w.status='Win'
           $whereWin";
foreach ($users as $u) {
  $stmt = $db->prepare($sqlWin);
  $uid = $u['user_id'];
  if (!empty($paramsWin)) {
    $p1 = $paramsWin[0];
    $p2 = $paramsWin[1];
    $stmt->bind_param('sss', $uid, $p1, $p2);
  } else {
    $stmt->bind_param('s', $uid);
  }
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  $win = $result['win'] ?? 0;
  $lastWinTime = $result['last_win_time'] ?? '';
  $stmt->close();

  if ($win > 0) {
    $data[] = [
      'id' => $u['id'],
      'name' => $u['name'],
      'user_id' => $u['user_id'],
      'last_win_time' => $lastWinTime,
      'total' => $win
    ];
    $totalSum += $win;
  }
}

// Server-side search
if ($search !== '') {
  $data = array_values(array_filter(
    $data,
    fn($r) =>
    stripos($r['name'], $search) !== false ||
    stripos($r['user_id'], $search) !== false
  ));
}

// Sorting
if ($order === 'asc') {
  usort($data, fn($a, $b) => $a['total'] <=> $b['total']);
} elseif ($order === 'desc') {
  usort($data, fn($a, $b) => $b['total'] <=> $a['total']);
}

// Pagination
$totalRows = count($data);
$totalPages = max(1, ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$rows = array_slice($data, $offset, $perPage);

// Today's page sum
$pageSum = 0;
if ($range === 'today') {
  foreach ($rows as $r)
    $pageSum += $r['total'];
}

$db->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Total Wining – Admin</title>
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
      --shadow: 0 4px 18px rgba(0, 0, 0, .08);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: Poppins, Arial, sans-serif;
    }

    body {
      background: var(--bg);
      min-height: 100vh;
      padding: 24px 18px;
      color: #212529;
    }

    a {
      text-decoration: none;
      color: inherit;
    }

    /* Filter/Search Bar */
    .filter-bar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
      padding: 12px 20px;
      border-radius: var(--radius);
      background: var(--glass);
      box-shadow: var(--shadow);
    }

    .filter-bar h1 {
      margin-right: 24px;
      font-size: 18px;
      font-weight: 600;
      color: var(--brown);
    }

    .top-btn {
      margin-right: auto;
      border: none;
      background: var(--brown);
      color: #fff;
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: .2s;
    }

    .top-btn:hover {
      opacity: .85;
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
      transition: .15s;
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
      font-size: 12px;
    }

    .search-form {
      margin-left: auto;
      display: inline-flex;
      gap: 6px;
      align-items: center;
    }

    .search-form input[type="text"] {
      padding: 6px 10px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 12px;
    }

    .search-form button {
      background: var(--brown);
      color: #fff;
      border: none;
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
    }

    /* Summary */
    .summary {
      max-width: 980px;
      margin: 8px auto;
      font-size: 14px;
      font-weight: 600;
      color: var(--brown);
    }

    /* Table */
    .table-wrap {
      overflow-x: auto;
    }

    table {
      width: 100%;
      min-width: 980px;
      border-collapse: collapse;
      font-size: 12px;
    }

    th,
    td {
      padding: 6px 4px;
      text-align: center;
      font-size: 12px;
    }

    th {
      background: var(--brown);
      color: #fff;
      font-weight: 600;
      position: sticky;
      top: 0;
    }

    tbody tr:nth-child(even) {
      background: #fafafa
    }

    tbody tr:hover {
      background: #fffbea;
      box-shadow: 0 0 0 9999px #fffbea inset;
    }

    .user {
      color: var(--blue);
      font-weight: 600;
    }

    .referral {
      color: var(--amber);
      font-weight: 600;
    }

    .amount {
      background: #d4edda;
      color: #155724;
      border-radius: 6px;
      padding: 2px 6px;
      font-weight: 600;
      font-size: 12px;
    }

    /* Pagination */
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

    /* Responsive */
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

      .search-form input[type="text"] {
        width: 100%;
        margin-left: 0
      }
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

  <div class="filter-bar">
    <a href="admin_dashboard.php" class="top-btn">
      <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
    <h1>Total Wining</h1>

    <!-- Range Filters -->
    <a href="?<?= http_build_query(['range' => 'today', 'order' => $order, 'from' => $from, 'to' => $to, 'search' => $search]) ?>"
      class="btn<?= $range === 'today' ? ' active' : '' ?>">Today</a>
    <a href="?<?= http_build_query(['range' => 'all', 'order' => $order, 'from' => $from, 'to' => $to, 'search' => $search]) ?>"
      class="btn<?= $range === 'all' ? ' active' : '' ?>">All Time</a>
    <button class="btn<?= $range === 'custom' ? ' active' : '' ?>"
      onclick="document.getElementById('customForm').style.display='inline-flex';">
      Customize
    </button>
    <form id="customForm" method="get"
      style="display:<?= $range === 'custom' ? 'inline-flex' : 'none' ?>;gap:8px;align-items:center">
      <input type="hidden" name="range" value="custom">
      <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
      <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
      <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" required>
      <span>to</span>
      <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" required>
      <button class="btn active" style="padding:5px 12px;">Apply</button>
    </form>

    <!-- Order Filters -->
    <a href="?<?= http_build_query(['range' => $range, 'order' => 'desc', 'from' => $from, 'to' => $to, 'search' => $search]) ?>"
      class="btn<?= $order === 'desc' ? ' active' : '' ?>">Big Capital</a>
    <a href="?<?= http_build_query(['range' => $range, 'order' => 'asc', 'from' => $from, 'to' => $to, 'search' => $search]) ?>"
      class="btn<?= $order === 'asc' ? ' active' : '' ?>">Small Capital</a>

    <!-- Search -->
    <form method="get" class="search-form">
      <input type="hidden" name="range" value="<?= htmlspecialchars($range) ?>">
      <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
      <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
      <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
      <input type="text" name="search" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
      <button type="submit" class="btn">Go</button>
    </form>
  </div>

  <div class="summary">
    Total Wining Balance: ₹ <?= number_format($totalSum, 2) ?>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Sr</th>
          <th>Name</th>
          <th>User ID</th>
          <th>Date & Time</th>
          <th>Total Wining (₹)</th>
        </tr>
      </thead>
      <tbody>
        <?php
        require_once 'includes/db.php';
        if ($range === 'today'): ?>
          <tr>
            <td colspan="5"
              style="text-align:left; padding:8px 12px; background:#fff3cd; color:#856404; font-weight:600;">
              <i class="fas fa-sun"></i> Today Page Wining: ₹<?= number_format($pageSum, 2) ?>
            </td>
          </tr>
          <?php
          require_once 'includes/db.php';
        endif; ?>

        <?php
        require_once 'includes/db.php';
        if ($rows):
          $sr = $offset + 1; ?>
          <?php
          require_once 'includes/db.php';
          foreach ($rows as $r): ?>
            <tr>
              <td><?= $sr++ ?></td>
              <td><?= htmlspecialchars($r['name']) ?></td>
              <td><?= htmlspecialchars($r['user_id']) ?></td>
              <td><?= $r['last_win_time'] ? date('d M Y, h:i A', strtotime($r['last_win_time'])) : '-' ?></td>
              <td><span class="amount"><?= number_format($r['total'], 2) ?></span></td>
            </tr>
            <?php
            require_once 'includes/db.php';
          endforeach; ?>
          <?php
          require_once 'includes/db.php';
        else: ?>
          <tr>
            <td colspan="5" style="font-weight:600;color:var(--red)">No data found</td>
          </tr>
          <?php
          require_once 'includes/db.php';
        endif; ?>
      </tbody>
    </table>
  </div>

  <?php
  require_once 'includes/db.php';
  // Always show pagination container if pages >= 1 (to maintain layout consistently)
  if ($totalPages >= 1): ?>
    <div class="pagination">
      <?php
      // Previous
      $pr = ['range' => $range, 'order' => $order, 'from' => $from, 'to' => $to, 'search' => $search, 'page' => $page - 1];
      if ($page > 1) {
        echo '<a href="?' . http_build_query($pr) . '">&laquo; Previous</a>';
      } else {
        echo '<span class="disabled">&laquo; Previous</span>';
      }

      // Info
      echo '<span class="info">Page ' . $page . ' of ' . $totalPages . '</span>';

      // Next
      $nx = ['range' => $range, 'order' => $order, 'from' => $from, 'to' => $to, 'search' => $search, 'page' => $page + 1];
      if ($page < $totalPages) {
        echo '<a href="?' . http_build_query($nx) . '">Next &raquo;</a>';
      } else {
        echo '<span class="disabled">Next &raquo;</span>';
      }
      ?>
    </div>
  <?php endif; ?>

</body>

</html>
