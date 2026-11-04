<?php
require_once 'includes/db.php';
// admin_stats.php – Admin wise Deposit / Withdraw / Bid / Wining / PN
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

$adminName = $_SESSION['admin'] ?? 'Admin';

/* ---- Debug ON (500 dekhne ke liye) ---- */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = db();
} catch (Exception $e) {
    die('DB error: ' . $e->getMessage());
}

/* ---------- DATE RANGE HANDLING ---------- */
$range    = $_GET['range'] ?? 'today';
$today    = $range === 'today' || $range === '';
$days     = in_array($range, ['7','15','30']) ? (int)$range : 0;
$from     = $_GET['from'] ?? '';
$to       = $_GET['to']   ?? '';
$isCustom = $range === 'custom'
         && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)
         && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);

$label = $today ? 'Today'
        : ($days ? "Last $days days"
        : ($range === 'all' ? 'All time'
        : "Custom ($from → $to)"));

// date_time based filter (sirf deposit / withdrawal jahan column pakka hai)
$dateFilter = '';
if ($today) {
    $dateFilter = " AND date_time >= CURDATE()";
} elseif ($days) {
    $dateFilter = " AND date_time >= CURDATE() - INTERVAL $days DAY";
} elseif ($isCustom) {
    $fromEsc = $db->real_escape_string($from);
    $toEsc   = $db->real_escape_string($to);
    $dateFilter = " AND date_time BETWEEN '{$fromEsc} 00:00:00' AND '{$toEsc} 23:59:59'";
} // all time => no filter

/*
   Per-admin summary:
   - Total Deposit  (deposit.admin_id)   → dateFilter apply
   - Total Withdraw (withdrawal.admin_id)→ dateFilter apply
   - Total Bid      (bid.admin_id)       → all-time (no date_time column assume)
   - Total Wining   (wining.admin_id)    → all-time (safe)
*/
$sql = "
SELECT 
    a.id,
    a.name,
    a.email,
    a.role,
    COALESCE(d.total_dep, 0)   AS total_dep,
    COALESCE(w.total_with, 0)  AS total_with,
    COALESCE(b.total_bid, 0)   AS total_bid,
    COALESCE(win.total_win, 0) AS total_win
FROM admin a
LEFT JOIN (
    SELECT admin_id, SUM(amount) AS total_dep
    FROM deposit
    WHERE status='Done' 
      AND admin_id IS NOT NULL
      $dateFilter
    GROUP BY admin_id
) d ON d.admin_id = a.id
LEFT JOIN (
    SELECT admin_id, SUM(amount) AS total_with
    FROM withdrawal
    WHERE status='Done' 
      AND admin_id IS NOT NULL
      $dateFilter
    GROUP BY admin_id
) w ON w.admin_id = a.id
LEFT JOIN (
    SELECT admin_id, SUM(amount) AS total_bid
    FROM bid
    WHERE admin_id IS NOT NULL
    GROUP BY admin_id
) b ON b.admin_id = a.id
LEFT JOIN (
    SELECT admin_id, SUM(wining_balance) AS total_win
    FROM wining
    WHERE admin_id IS NOT NULL
    GROUP BY admin_id
) win ON win.admin_id = a.id
ORDER BY a.id
";

$res = $db->query($sql);
$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
<meta charset="UTF-8">
<title>Admin Statistics – SUBH GAMING</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,sans-serif;}
  :root{--red:#c62828;--dark:#8B0000;--brown:#5c3a21;--blue:#0277BD;}

  body{
    background:#f5f0e5;
    padding:15px;
    display:flex;
    justify-content:center;
    min-height:100vh;
  }
  .wrapper{
    width:100%;
    max-width:1100px;
  }
  .top-bar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:10px;
    font-size:13px;
    flex-wrap:wrap;
    gap:6px;
  }
  .back-link{
    text-decoration:none;
    color:var(--dark);
    font-weight:600;
    display:inline-flex;
    align-items:center;
    gap:5px;
  }
  .back-link i{font-size:12px;}
  .logged{
    color:#7b5a3a;
    font-size:12px;
  }

  .title{
    text-align:center;
    font-size:22px;
    font-weight:700;
    color:var(--dark);
    letter-spacing:1px;
    margin-bottom:8px;
  }

  .range-group{
    display:flex;
    flex-wrap:wrap;
    gap:4px;
    margin-bottom:8px;
    justify-content:center;
  }
  .range-btn{
    padding:4px 8px;
    border-radius:6px;
    border:1px solid var(--blue);
    background:#fff;
    color:var(--blue);
    font-size:11px;
    text-decoration:none;
  }
  .range-btn.active,
  .range-btn:hover{
    background:var(--blue);
    color:#fff;
  }

  .custom-form{
    display:none;
    justify-content:center;
    gap:6px;
    margin-bottom:8px;
    flex-wrap:wrap;
  }
  .custom-form input{
    padding:4px 6px;
    border-radius:6px;
    border:1px solid #ccc;
    font-size:11px;
  }
  .custom-form button{
    padding:4px 10px;
    border-radius:6px;
    border:none;
    background:var(--blue);
    color:#fff;
    font-size:11px;
    cursor:pointer;
  }

  .label-text{
    text-align:center;
    font-size:12px;
    color:#555;
    margin-bottom:8px;
  }

  table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 3px 10px rgba(0,0,0,0.08);
    font-size:13px;
  }
  th,td{
    padding:8px 8px;
    border-bottom:1px solid #f0e0c4;
    text-align:right;
  }
  th{
    background:#fdebd0;
    color:var(--brown);
    font-weight:700;
  }
  th:nth-child(1),
  td:nth-child(1),
  th:nth-child(2),
  td:nth-child(2),
  th:nth-child(3),
  td:nth-child(3){
    text-align:left;
  }
  tr:last-child td{border-bottom:none;}

  .role-badge{
    display:inline-block;
    padding:2px 7px;
    border-radius:10px;
    font-size:10px;
    font-weight:600;
  }
  .master{background:#ffebee;color:#c62828;}
  .admin{background:#e3f2fd;color:#1565c0;}
  .pn-pos{color:#2e7d32;font-weight:700;}
  .pn-neg{color:#c62828;font-weight:700;}

  @media(max-width:720px){
    table{font-size:12px;}
    th,td{padding:6px 5px;}
  }
</style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
<div class="wrapper">

  <div class="top-bar">
    <a href="admin_dashboard.php" class="back-link">
      <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
    <span class="logged">Logged in as: <strong><?= htmlspecialchars($adminName) ?></strong></span>
  </div>

  <div class="title">Admin Statistics</div>

  <!-- RANGE BUTTONS -->
  <div class="range-group">
    <?php
require_once 'includes/db.php';
      $btn = function($txt, $val) use ($range, $from, $to) {
        printf(
          '<a class="range-btn %s" href="?range=%s%s">%s</a>',
          $val === $range ? 'active' : '',
          $val,
          $val === 'custom' ? "&from=$from&to=$to" : '',
          $txt
        );
      };
      $btn('Today','today');
      $btn('7 Days','7');
      $btn('15 Days','15');
      $btn('30 Days','30');
      $btn('All Time','all');
    ?>
    <a href="#" class="range-btn <?= $range==='custom'?'active':'' ?>" onclick="showCustom(event)">Custom…</a>
  </div>

  <!-- CUSTOM DATE FORM -->
  <form id="cForm" class="custom-form" method="get">
    <input type="hidden" name="range" value="custom">
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" required>
    <input type="date" name="to"   value="<?= htmlspecialchars($to) ?>" required>
    <button type="submit">Apply</button>
  </form>

  <div class="label-text">
    Showing data: <strong><?= htmlspecialchars($label) ?></strong>
  </div>

  <table>
    <tr>
      <th>#</th>
      <th>Admin Name</th>
      <th>Role</th>
      <th>Total Deposit</th>
      <th>Total Withdrawal</th>
      <th>Total Bid</th>
      <th>Total Wining</th>
      <th>PN (Net)</th>
    </tr>

    <?php
require_once 'includes/db.php'; if (empty($rows)): ?>
      <tr><td colspan="8" style="text-align:center;">No admin data found.</td></tr>
    <?php
require_once 'includes/db.php'; else: ?>
      <?php
require_once 'includes/db.php'; foreach ($rows as $r):
            $dep   = (float)$r['total_dep'];
            $with  = (float)$r['total_with'];
            $bid   = (float)$r['total_bid'];
            $win   = (float)$r['total_win'];
            // PN = Deposit - (Withdrawal + Wining)
            $pn    = $dep - ($with + $win);
            $pnCls = $pn >= 0 ? 'pn-pos' : 'pn-neg';
      ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td>
            <span class="role-badge <?= $r['role']==='master'?'master':'admin' ?>">
              <?= $r['role']==='master' ? 'Master' : 'Admin' ?>
            </span>
          </td>
          <td>₹<?= number_format($dep,2) ?></td>
          <td>₹<?= number_format($with,2) ?></td>
          <td>₹<?= number_format($bid,2) ?></td>
          <td>₹<?= number_format($win,2) ?></td>
          <td class="<?= $pnCls ?>">₹<?= number_format($pn,2) ?></td>
        </tr>
      <?php
require_once 'includes/db.php'; endforeach; ?>
    <?php
require_once 'includes/db.php'; endif; ?>
  </table>

</div>

<script>
function showCustom(e){
  e.preventDefault();
  document.getElementById('cForm').style.display = 'flex';
}
<?php
require_once 'includes/db.php'; if ($range === 'custom'): ?>
document.getElementById('cForm').style.display = 'flex';
<?php
require_once 'includes/db.php'; endif; ?>
</script>
</body>
</html>




