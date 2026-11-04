<?php
require_once 'includes/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set('Asia/Kolkata');
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}
$adminName = $_SESSION['admin'];

$dbError = '';
$markets = [];

/* ---------- Flash toast ---------- */
$flashToast = isset($_SESSION['flash_toast']) ? $_SESSION['flash_toast'] : null;
unset($_SESSION['flash_toast']);

/* ---------- Helpers ---------- */
function setFlash($type, $msg)
{
  $_SESSION['flash_toast'] = ['type' => $type, 'msg' => $msg];
}

/**
 * Build 3 common slug variants:
 *  1) star_line
 *  2) star-line
 *  3) starline
 */
function marketSlugVariants($name)
{
  $raw = strtolower(trim((string) $name));
  $raw = preg_replace('/\s+/', ' ', $raw);
  // keep only letters/digits/spaces/_/-
  $raw = preg_replace('/[^a-z0-9 _-]/', '', $raw);

  $underscore = preg_replace('/[\s-]+/', '_', $raw);
  $underscore = preg_replace('/_+/', '_', trim($underscore, '_'));

  $hyphen = preg_replace('/[\s_]+/', '-', $raw);
  $hyphen = preg_replace('/-+/', '-', trim($hyphen, '-'));

  $compact = preg_replace('/[\s_-]+/', '', $raw);

  $out = array_values(array_unique(array_filter([$underscore, $hyphen, $compact])));
  return $out;
}

function tryDeleteMarketFile($marketName)
{
  $slugs = marketSlugVariants($marketName);
  if (empty($slugs))
    return [false, 'Slug empty, file not resolved'];

  // Possible locations
  $baseDirs = [
    __DIR__,
    __DIR__ . '/markets',
    __DIR__ . '/market',
    __DIR__ . '/pages',
    __DIR__ . '/charts', // Added charts
  ];

  $candidates = [];
  foreach ($baseDirs as $dir) {
    foreach ($slugs as $slug) {
      $candidates[] = $dir . '/' . $slug . '.php';
      $candidates[] = $dir . '/' . $slug . '_full_chart.php'; // Support chart naming convention
      $candidates[] = $dir . '/' . $slug . '_chart.php';
    }
  }

  // Also try case-insensitive match
  foreach ($baseDirs as $dir) {
    $dirFiles = @scandir($dir);
    if (!is_array($dirFiles))
      continue;

    foreach ($dirFiles as $f) {
      $full = $dir . '/' . $f;
      if (!is_file($full))
        continue;

      $lf = strtolower($f);
      foreach ($slugs as $slug) {
        if (
          $lf === strtolower($slug . '.php') ||
          $lf === strtolower($slug . '_full_chart.php') ||
          $lf === strtolower($slug . '_chart.php')
        ) {
          $candidates[] = $full;
        }
      }
    }
  }

  $deletedAny = false;
  $deletedList = [];
  $lastError = '';

  foreach (array_unique($candidates) as $path) {
    if (!file_exists($path))
      continue;

    if (!is_writable($path)) {
      $lastError = "File not writable: " . basename($path);
      error_log("[Market Delete] Not writable: " . $path);
      continue;
    }

    if (@unlink($path)) {
      $deletedAny = true;
      $deletedList[] = basename($path);
    } else {
      $lastError = "unlink failed: " . basename($path);
      error_log("[Market Delete] unlink failed: " . $path);
    }
  }

  if ($deletedAny) {
    $msg = 'Market page/chart file(s) deleted: ' . implode(', ', array_unique($deletedList));
    return [true, $msg];
  }
  if ($lastError !== '')
    return [false, $lastError];

  return [false, 'Market page file not found (all variants tried)'];
}

/* ====== DB schema helpers (PHP 5.6 compatible) ====== */
function LL_has_table($db, $table)
{
  try {
    $tblCount = 0;
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->bind_param('s', $table);
    $st->execute();
    $st->bind_result($tblCount);
    $st->fetch();
    $st->close();
    return ((int) $tblCount) > 0;
  } catch (Exception $e) {
    return false;
  }
}

function LL_has_column($db, $table, $col)
{
  try {
    $colCount = 0;
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $st->bind_param('ss', $table, $col);
    $st->execute();
    $st->bind_result($colCount);
    $st->fetch();
    $st->close();
    return ((int) $colCount) > 0;
  } catch (Exception $e) {
    return false;
  }
}

function LL_cleanup_market_everywhere($db, $marketName)
{
  $mUpper = strtoupper(trim($marketName));
  $stats = [
    'market_result' => 0,
    'wining' => 0,
    'wins_run_lock' => 0,
    'bid' => 0,
    'bids' => 0,
    'bid_history' => 0,

    'market_order' => 0
  ];

  $targets = [
    'market_result' => ['market', 'market_name'],
    'wining' => ['market', 'market_name'],
    'wins_run_lock' => ['market'],
    'bid' => ['market', 'market_name'],
    'bids' => ['market', 'market_name'],
    'bid_history' => ['market', 'market_name'],

    'market_order' => ['market_name']
  ];

  foreach ($targets as $table => $cols) {
    if (!LL_has_table($db, $table))
      continue;

    foreach ($cols as $c) {
      if (LL_has_column($db, $table, $c)) {
        try {
          $sql = "DELETE FROM `$table` WHERE UPPER(TRIM(`$c`)) = ?";
          $st = $db->prepare($sql);
          $st->bind_param('s', $mUpper);
          $st->execute();
          $stats[$table] += (int) $st->affected_rows;
          $st->close();
        } catch (Exception $e) {
          // ignore
        }
      }
    }
  }

  return $stats;
}

/* ---------- DB connect ---------- */
$db = db();

if ($db->connect_error) {
  $dbError = 'DB connection failed: ' . $db->connect_error;
} else {
  $db->set_charset('utf8mb4');
  $db->query("SET time_zone = '+05:30'");

  /* ---------- DELETE market (and file) ---------- */
  if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $did = (int) $_GET['delete'];

    $stmt = $db->prepare("SELECT market_name FROM markets WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $did);
    $stmt->execute();
    $stmt->bind_result($mName);

    if ($stmt->fetch()) {
      $stmt->close();
      try {
        $db->begin_transaction();

        $stmt2 = $db->prepare("DELETE FROM markets WHERE id=? LIMIT 1");
        $stmt2->bind_param('i', $did);
        $stmt2->execute();
        $stmt2->close();

        $cleanup = LL_cleanup_market_everywhere($db, $mName);

        $db->commit();

        // file delete
        $delRes = tryDeleteMarketFile($mName);
        $okFile = $delRes[0];
        $fileMsg = $delRes[1];

        $cleanMsg = "Cleanup: market_result={$cleanup['market_result']}, wining={$cleanup['wining']}, bid={$cleanup['bid']}, bids={$cleanup['bids']}, bid_history={$cleanup['bid_history']}";

        if ($okFile) {
          setFlash('success', 'Market deleted ✅ | ' . $fileMsg . ' | ' . $cleanMsg);
        } else {
          setFlash('error', 'Market deleted ✅ + DB cleaned, but file not removed: ' . $fileMsg . ' | ' . $cleanMsg);
        }

      } catch (Exception $e) {
        try {
          $db->rollback();
        } catch (Exception $x) {
        }
        setFlash('error', 'Delete failed: ' . $e->getMessage());
      }

    } else {
      $stmt->close();
      setFlash('error', 'Market not found.');
    }

    header('Location: list_of_market.php');
    exit;
  }

  /* ---------- Publish / Hide toggle ---------- */
  if (isset($_GET['toggle']) && ctype_digit($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];

    $stmt = $db->prepare("SELECT is_published FROM markets WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($currPub);

    if ($stmt->fetch()) {
      $stmt->close();

      if ((int) $currPub === 1) {
        $newPublished = 0;
        $newStatus = 'inactive';
      } else {
        $newPublished = 1;
        $newStatus = 'active';
      }

      $stmt2 = $db->prepare("UPDATE markets SET is_published=?, status=? WHERE id=? LIMIT 1");
      $stmt2->bind_param('isi', $newPublished, $newStatus, $id);
      $stmt2->execute();
      $stmt2->close();

      setFlash('success', 'Market publish status updated ✅');
    } else {
      $stmt->close();
      setFlash('error', 'Market not found.');
    }

    header('Location: list_of_market.php');
    exit;
  }

  /* ---------- Fetch markets + Pagination ---------- */
  $totalMarkets = 0;
  $cRes = $db->query("SELECT COUNT(*) AS c FROM markets");
  if ($cRes) {
    $row = $cRes->fetch_assoc();
    $totalMarkets = (int) ($row['c'] ? $row['c'] : 0);
  }

  $limit = 10;
  $page = max(1, (int) (isset($_GET['page']) ? $_GET['page'] : 1));
  $totalPages = max(1, (int) ceil($totalMarkets / $limit));
  $page = min($page, $totalPages);
  $offset = ($page - 1) * $limit;

  $markets = [];
  $stmt = $db->prepare("SELECT * FROM markets ORDER BY sort_order ASC, id ASC LIMIT ? OFFSET ?");
  $stmt->bind_param('ii', $limit, $offset);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $markets[] = $row;
  }
  $stmt->close();
  $db->close();
}

$currentTime = date('H:i:s');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <title>Market List – LALA 111 Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
  <style>
    :root {
      --red: #c62828;
      --green: #2e7d32;
      --blue: #0277BD;
      --orange: #F57C00;
      --brown: #4e342e;
      --wallet: #009688;
      --shadow: rgba(0, 0, 0, 0.1);
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      margin: 0;
      padding: 0;
      font-family: 'Roboto', sans-serif;
      background: #f5f0e5;
      color: #333;
    }

    a {
      text-decoration: none;
      color: inherit;
    }

    .wrapper {
      max-width: 1200px;
      margin: 20px auto 30px;
      padding: 0 16px;
    }

    /* Top bar */
    .topbar {
      background: #fff;
      padding: 10px 14px;
      border-radius: 10px;
      box-shadow: 0 2px 8px var(--shadow);
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .top-left-actions,
    .top-right-actions {
      flex: 0 0 auto;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .top-center {
      flex: 1 1 auto;
      text-align: center;
      min-width: 200px;
    }

    .top-title {
      font-size: 18px;
      font-weight: 700;
      color: var(--brown);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    .top-title i {
      color: #fLLd04;
    }

    .top-sub {
      font-size: 12px;
      color: #666;
      margin-top: 2px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 20px;
      border: none;
      font-size: 13px;
      cursor: pointer;
      font-weight: 600;
      transition: 0.15s;
      white-space: nowrap;
    }

    .btn i {
      font-size: 14px;
    }

    .btn-secondary {
      background: #eeeeee;
      color: #333;
    }

    .btn-secondary:hover {
      background: #e0e0e0;
    }

    .btn-primary {
      background: #c62828;
      color: #fff;
    }

    .btn-primary:hover {
      background: #b71c1c;
    }

    /* Info bar */
    .info-bar {
      margin: 16px 0 10px;
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      font-size: 13px;
    }

    .info-bar span.label {
      color: #555;
    }

    .badge-count {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #fff;
      border-radius: 20px;
      padding: 4px 10px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
    }

    .badge-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--green);
    }

    /* Table card */
    .card {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 3px 10px var(--shadow);
      padding: 12px;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    .card-header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
      gap: 10px;
      flex-wrap: wrap;
    }

    .card-header-row .title {
      font-size: 14px;
      font-weight: 600;
      color: #4e342e;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      min-width: 1120px;
    }

    th,
    td {
      padding: 8px 8px;
      border-bottom: 1px solid #eee;
      text-align: center;
      white-space: nowrap;
      vertical-align: middle;
    }

    th {
      background: #f7f3ea;
      font-weight: 600;
      color: #444;
      position: sticky;
      top: 0;
      z-index: 1;
    }

    tr:nth-child(even) td {
      background: #faf7f1;
    }

    tr:hover td {
      background: #f1ece2;
    }

    tr.draggable-row {
      cursor: move;
    }

    tr.dragging {
      opacity: 0.5;
    }

    tr.row-closed-time td {
      background: #ffebee !important;
      border-left: 3px solid #c62828;
    }

    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
    }

    .badge-green {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .badge-red {
      background: #ffebee;
      color: #c62828;
    }

    .badge-grey {
      background: #eceff1;
      color: #37474f;
    }

    .badge-blue {
      background: #e3f2fd;
      color: #1565c0;
    }

    .payout-text {
      font-size: 11px;
      line-height: 1.4;
      color: #4e342e;
    }

    .payout-text strong {
      font-weight: 700;
    }

    td.actions-col {
      min-width: 260px;
    }

    .actions {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }

    .actions a.edit,
    .actions a.delete {
      font-size: 12px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 5px 10px;
      border-radius: 16px;
      border: 1px solid transparent;
      background: #fff;
      white-space: nowrap;
    }

    .actions a.edit {
      color: #1976d2;
      border-color: #LLdefb;
      background: #e3f2fd;
    }

    .actions a.delete {
      color: #c62828;
      border-color: #ffcdd2;
      background: #ffebee;
    }

    .actions i {
      font-size: 12px;
    }

    .note {
      margin-top: 10px;
      font-size: 12px;
      color: #666;
    }

    .error-box {
      margin-top: 16px;
      padding: 10px 12px;
      border-radius: 8px;
      background: #ffebee;
      color: #c62828;
      font-size: 13px;
    }

    /* Switch */
    .switch {
      position: relative;
      display: inline-block;
      width: 40px;
      height: 22px;
      flex: 0 0 auto;
    }

    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .slider {
      position: absolute;
      cursor: pointer;
      inset: 0;
      background-color: #ccc;
      transition: .2s;
      border-radius: 999px;
    }

    .slider:before {
      position: absolute;
      content: "";
      height: 18px;
      width: 18px;
      left: 2px;
      bottom: 2px;
      background-color: white;
      transition: .2s;
      border-radius: 50%;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    }

    input:checked+.slider {
      background-color: #2196F3;
    }

    input:checked+.slider:before {
      transform: translateX(18px);
    }

    /* Toast */
    .toast {
      position: fixed;
      bottom: 18px;
      right: 18px;
      background: #2e7d32;
      color: #fff;
      padding: 10px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
      box-shadow: 0 3px 18px rgba(0, 0, 0, .25);
      display: none;
      z-index: 9999;
      max-width: 92vw;
    }

    .toast.error {
      background: #c62828;
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

    @media (max-width:768px) {
      .topbar {
        flex-direction: column;
        align-items: stretch;
      }

      .top-center {
        order: 1;
      }

      .top-left-actions {
        order: 2;
        justify-content: flex-start;
      }

      .top-right-actions {
        order: 3;
        justify-content: flex-end;
      }

      .top-title {
        font-size: 16px;
      }

      table {
        font-size: 12px;
        min-width: 1060px;
      }

      td.actions-col {
        min-width: 280px;
      }
    }
  </style>
  <script>
    function toggleMarket(id, checkbox) {
      if (!confirm('Change publish status for this market?')) {
        checkbox.checked = !checkbox.checked;
        return;
      }
      window.location = 'list_of_market.php?toggle=' + id;
    }
    function deleteMarket(id, name) {
      if (confirm('Delete market "' + name + '"?\n\nDB row delete + page file delete (all variants if writable).')) {
        window.location = 'list_of_market.php?delete=' + id;
      }
    }
  </script>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
  <div class="wrapper">

    <!-- TOP BAR -->
    <div class="topbar">
      <div class="top-left-actions">
        <a href="admin_dashboard.php" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Dashboard
        </a>
      </div>
      <div class="top-center">
        <div class="top-title"><i class="fas fa-crown"></i><span>Market List</span></div>
        <div class="top-sub">Drag &amp; drop to reorder markets (affects user app order)</div>
      </div>
      <div class="top-right-actions">
        <a href="create_new_market.php" class="btn btn-primary">
          <i class="fas fa-plus"></i> Create New Market
        </a>
      </div>
    </div>

    <!-- INFO BAR -->
    <div class="info-bar">
      <span class="label">
        Welcome, <strong>
          <?= htmlspecialchars($adminName) ?>
        </strong>
        <?php if (!$dbError): ?>
          &nbsp;|&nbsp; Total Markets: <strong>
            <?= $totalMarkets ?>
          </strong>
        <?php endif; ?>
      </span>
      <?php if (!$dbError): ?>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <div class="badge-count">
            <span class="badge-dot"></span>
            <span>Published markets show in user app. Reorder via drag &amp; drop.</span>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- MAIN CARD -->
    <div class="card">
      <?php if ($dbError): ?>
        <div class="error-box">
          <strong>Database error:</strong><br>
          <?= htmlspecialchars($dbError) ?><br><br>
          Make sure the <code>markets</code> table exists.
        </div>
      <?php elseif (empty($markets)): ?>
        <div style="padding:10px 4px;font-size:13px;color:#555;text-align:center;">
          No markets found. Use <strong>"Create New Market"</strong> to add first market.
        </div>
      <?php else: ?>

        <div class="card-header-row">
          <div class="title">All Markets (drag rows to change position)</div>
          <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button type="button" class="btn btn-secondary" id="arrangeByTimeBtn">
              <i class="fas fa-clock"></i> Arrange According to Time
            </button>
            <button type="button" class="btn btn-primary" id="saveOrderBtn2">
              <i class="fas fa-save"></i> Update Market Order
            </button>
          </div>
        </div>

        <table>
          <thead>
            <tr>
              <th>Sr No</th>
              <th>Market Name</th>
              <th>Default Result</th>
              <th>Open</th>
              <th>Close</th>
              <th>Today</th>
              <th>Custom Text</th>
              <th>Payouts<br><small>SD/JD/SP/DP/TP/HS/FS</small></th>
              <th>Status</th>
              <th style="min-width:260px;">Actions</th>
            </tr>
          </thead>
          <tbody id="marketBody">
            <?php
            $sr = $offset + 1;
            foreach ($markets as $m):
              $open = $m['open_time'] ? date('h:i A', strtotime($m['open_time'])) : '';
              $close = $m['close_time'] ? date('h:i A', strtotime($m['close_time'])) : '';

              $p_sd = (int) (isset($m['payout_single_digit']) ? $m['payout_single_digit'] : 0);
              $p_jd = (int) (isset($m['payout_jodi_digit']) ? $m['payout_jodi_digit'] : 0);
              $p_sp = (int) (isset($m['payout_single_pana']) ? $m['payout_single_pana'] : 0);
              $p_dp = (int) (isset($m['payout_double_pana']) ? $m['payout_double_pana'] : 0);
              $p_tp = (int) (isset($m['payout_triple_pana']) ? $m['payout_triple_pana'] : 0);
              $p_hs = (int) (isset($m['payout_half_sangam']) ? $m['payout_half_sangam'] : 0);
              $p_fs = (int) (isset($m['payout_full_sangam']) ? $m['payout_full_sangam'] : 0);

              $rowClass = 'draggable-row';
              if (!empty($m['close_time'])) {
                if ($currentTime >= $m['close_time'] && $currentTime < '23:59:59') {
                  $rowClass .= ' row-closed-time';
                }
              }
              ?>
              <tr class="<?= $rowClass ?>" draggable="true" data-id="<?= (int) $m['id'] ?>">
                <td>
                  <?= $sr++; ?>
                </td>
                <td><strong>
                    <?= htmlspecialchars($m['market_name']) ?>
                  </strong></td>
                <td>
                  <?= htmlspecialchars($m['default_result']) ?>
                </td>
                <td>
                  <?= htmlspecialchars($open) ?>
                </td>
                <td>
                  <?= htmlspecialchars($close) ?>
                </td>
                <td>
                  <?= $m['today_closed'] ? '<span class="badge badge-red">Closed</span>' : '<span class="badge badge-green">Open</span>' ?>
                </td>
                <td>
                  <?= $m['custom_close_text'] ? '<span class="badge badge-blue">' . htmlspecialchars($m['custom_close_text']) . '</span>' : '<span style="color:#aaa;font-size:11px;">—</span>' ?>
                </td>
                <td>
                  <div class="payout-text">
                    <strong>SD:</strong>
                    <?= $p_sd ?>x<br>
                    <strong>JD:</strong>
                    <?= $p_jd ?>x<br>
                    <strong>SP:</strong>
                    <?= $p_sp ?>x<br>
                    <strong>DP:</strong>
                    <?= $p_dp ?>x<br>
                    <strong>TP:</strong>
                    <?= $p_tp ?>x<br>
                    <strong>HS:</strong>
                    <?= $p_hs ?>x<br>
                    <strong>FS:</strong>
                    <?= $p_fs ?>x
                  </div>
                </td>
                <td>
                  <?= $m['is_published'] ? '<span class="badge badge-green">Published</span>' : '<span class="badge badge-grey">Hidden</span>' ?>
                  <?php if (!empty($m['status'])): ?>
                    <br><small style="font-size:11px;color:#555;">(
                      <?= htmlspecialchars($m['status']) ?>)
                    </small>
                  <?php endif; ?>
                </td>
                <td class="actions-col">
                  <div class="actions">
                    <a class="edit" href="create_new_market.php?id=<?= (int) $m['id'] ?>"><i class="fas fa-pen"></i>
                      Edit</a>
                    <label class="switch" title="Publish / Hide">
                      <input type="checkbox" onchange="toggleMarket(<?= (int) $m['id'] ?>, this)" <?= $m['is_published'] ? 'checked' : '' ?>>
                      <span class="slider"></span>
                    </label>
                    <a href="javascript:void(0);" class="delete"
                      onclick="deleteMarket(<?= (int) $m['id'] ?>,'<?= htmlspecialchars($m['market_name'], ENT_QUOTES) ?>')"><i
                        class="fas fa-trash-alt"></i> Delete</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="note">Tip: Drag any row up/down to change order, then click <strong>Update Market Order</strong>.
        </div>
      <?php endif; ?>

      <?php if (isset($totalPages) && $totalPages >= 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>">&laquo; Previous</a>
          <?php else: ?>
            <span class="disabled">&laquo; Previous</span>
          <?php endif; ?>
          <span class="info">Page
            <?= (int) $page ?> of
            <?= (int) $totalPages ?>
          </span>
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>">Next &raquo;</a>
          <?php else: ?>
            <span class="disabled">Next &raquo;</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <div id="toast" class="toast"></div>

  <script>
    function showToast(text, isError) {
      var t = document.getElementById('toast');
      t.textContent = text;
      t.classList.toggle('error', !!isError);
      t.style.display = 'block';
      setTimeout(function () { t.style.display = 'none'; }, 2800);
    }
    <?php if ($flashToast && isset($flashToast['msg'])): ?>
      showToast(<?= json_encode($flashToast['msg']) ?>, <?= json_encode((isset($flashToast['type']) ? $flashToast['type'] : '') === 'error') ?>);
    <?php endif; ?>

      (function () {
        var tbody = document.getElementById('marketBody');
        if (!tbody) return;
        var dragEl = null;

        function onDragStart(e) {
          dragEl = e.currentTarget;
          dragEl.classList.add('dragging');
          e.dataTransfer.effectAllowed = 'move';
        }
        function onDragEnd() {
          if (dragEl) {
            dragEl.classList.remove('dragging');
            dragEl = null;
          }
        }
        function onDragOver(e) {
          e.preventDefault();
          var afterElement = getDragAfterElement(tbody, e.clientY);
          if (afterElement == null) {
            tbody.appendChild(dragEl);
          } else {
            tbody.insertBefore(dragEl, afterElement);
          }
        }
        function getDragAfterElement(container, y) {
          var draggableElements = [...container.querySelectorAll('.draggable-row:not(.dragging)')];
          return draggableElements.reduce(function(closest, child) {
            var box = child.getBoundingClientRect();
            var offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
              return { offset: offset, element: child };
            } else {
              return closest;
            }
          }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
        }

        function saveOrder() {
          var ids = [];
          tbody.querySelectorAll('.draggable-row').forEach(function(row) {
            ids.push(row.getAttribute('data-id'));
          });
          if (!ids.length) { showToast('No markets to reorder', true); return; }

          fetch('update_market_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'order=' + encodeURIComponent(ids.join(','))
          })
            .then(r => r.json())
            .then(function(j) {
              if (j.status === 'success') { showToast(j.message || 'Market order updated.'); }
              else { showToast(j.message || 'Failed to update order.', true); }
            })
            .catch(function() { showToast('Network error.', true); });
        }

        document.querySelectorAll('.draggable-row').forEach(function(row) {
          row.addEventListener('dragstart', onDragStart);
          row.addEventListener('dragend', onDragEnd);
        });
        tbody.addEventListener('dragover', onDragOver);

        var btn2 = document.getElementById('saveOrderBtn2');
        if (btn2) btn2.addEventListener('click', saveOrder);

        var arrangeBtn = document.getElementById('arrangeByTimeBtn');
        if (arrangeBtn) {
          arrangeBtn.addEventListener('click', function () {
            if (!confirm('Market names ko unke "Open Time" ke mutabiq order karun?')) return;
            fetch('update_market_order_by_time.php', { method: 'POST' })
              .then(r => r.json())
              .then(function(j) {
                if (j.status === 'success') {
                  showToast(j.message || 'Market order by time updated.');
                  setTimeout(function() { return location.reload(); }, 1500);
                } else {
                  showToast(j.message || 'Failed to update order.', true);
                }
              })
              .catch(function() { showToast('Network error.', true); });
          });
        }
      })();
  </script>
</body>

</html>
