<?php
require_once 'includes/db.php';
/**
 * revise_bid_win_balance.php
 * -------------------------------------------------
 * Admin page to view reversal/refund logs from `market_reverse_log`
 * (created by open_market.php + api_reverse_market.php)
 *
 * Features:
 * - Admin session required
 * - Filters: date range, market, scope, action_ref, user_id
 * - Summary cards (users/total refund/total win reverse/bids deleted/wins deleted)
 * - Grouped view by action_ref (one reversal operation)
 * - Expand modal to view per-user rows + details_json (bids/wins lists)
 *
 * NOTE:
 * - This file ONLY reads `market_reverse_log`.
 * - It does NOT change balances or delete anything.
 */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

/* ================== DEBUG ================== */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ✅ No-cache (admin pages) */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/* ================== TIMEZONE ================== */
date_default_timezone_set('Asia/Kolkata');

/* ================== CONFIG ================== */

/* ================== DB ================== */
try {
    $db = db();
    $db->query("SET time_zone = '+05:30'");
} catch (Throwable $e) {
    die('DB error: ' . $e->getMessage());
}

/* ================== HELPERS ================== */
function esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function LL_money($v): string { return number_format((float)$v, 2, '.', ''); }

function hasTable(mysqli $db, string $table): bool {
    try {
        $q = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
        $q->bind_param('s', $table);
        $q->execute();
        $q->bind_result($c);
        $q->fetch();
        $q->close();
        return ((int)$c) > 0;
    } catch (Throwable $e) { return false; }
}

function ensureReverseLogTable(mysqli $db): void {
    try {
        $db->query("
            CREATE TABLE IF NOT EXISTS market_reverse_log (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              action_ref VARCHAR(64) NOT NULL,
              admin_id VARCHAR(50) NOT NULL,
              market VARCHAR(80) NOT NULL,
              result_date DATE NOT NULL,
              delete_scope ENUM('OPEN','CLOSE','FULL') NOT NULL,

              user_id VARCHAR(50) NOT NULL,
              bid_refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
              win_reverse_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
              bids_deleted INT NOT NULL DEFAULT 0,
              wins_deleted INT NOT NULL DEFAULT 0,

              details_json LONGTEXT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

              PRIMARY KEY (id),
              KEY idx_market_date (market, result_date),
              KEY idx_user (user_id),
              KEY idx_action_ref (action_ref)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {}
}

ensureReverseLogTable($db);

/* ================== DEFAULT FILTERS ================== */
$today = date('Y-m-d');
$from  = $_GET['from'] ?? $today;
$to    = $_GET['to'] ?? $today;

$market    = strtoupper(trim($_GET['market'] ?? ''));
$scope     = strtoupper(trim($_GET['scope'] ?? ''));
$actionRef = trim($_GET['action_ref'] ?? '');
$userId    = strtoupper(trim($_GET['user_id'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = $today;
if (!in_array($scope, ['', 'OPEN', 'CLOSE', 'FULL'], true)) $scope = '';

/* ================== MARKETS LIST (for dropdown) ================== */
$markets = [];
try {
    if (hasTable($db, 'markets')) {
        $res = $db->query("SELECT market_name FROM markets ORDER BY sort_order, market_name");
        while ($r = $res->fetch_assoc()) $markets[] = strtoupper((string)$r['market_name']);
        if ($res) $res->free();
    }
} catch (Throwable $e) {}
$markets = array_values(array_unique(array_filter($markets)));
sort($markets);

/* ================== FETCH: grouped actions ================== */
$where = [];
$bind = [];
$types = '';

$where[] = "result_date BETWEEN ? AND ?";
$bind[] = $from; $types .= 's';
$bind[] = $to;   $types .= 's';

if ($market !== '') { $where[] = "UPPER(TRIM(market)) = ?"; $bind[] = $market; $types .= 's'; }
if ($scope !== '')  { $where[] = "delete_scope = ?"; $bind[] = $scope; $types .= 's'; }
if ($actionRef !== '') { $where[] = "action_ref LIKE ?"; $bind[] = '%' . $actionRef . '%'; $types .= 's'; }
if ($userId !== '') { $where[] = "UPPER(TRIM(user_id)) = ?"; $bind[] = $userId; $types .= 's'; }

$whereSql = implode(' AND ', $where);

$groups = [];
$summary = [
    'actions' => 0,
    'users' => 0,
    'refund_total' => 0.0,
    'reverse_total' => 0.0,
    'bids_deleted' => 0,
    'wins_deleted' => 0
];

try {
    $sql = "
        SELECT
          action_ref,
          MAX(created_at) AS created_at,
          MAX(admin_id)   AS admin_id,
          MAX(market)     AS market,
          MAX(result_date) AS result_date,
          MAX(delete_scope) AS delete_scope,

          COUNT(*) AS users_count,
          SUM(bid_refund_amount) AS refund_total,
          SUM(win_reverse_amount) AS reverse_total,
          SUM(bids_deleted) AS bids_deleted,
          SUM(wins_deleted) AS wins_deleted
        FROM market_reverse_log
        WHERE {$whereSql}
        GROUP BY action_ref
        ORDER BY created_at DESC
        LIMIT 300
    ";

    $st = $db->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$bind);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $groups[] = $r;

        $summary['actions']++;
        $summary['users'] += (int)($r['users_count'] ?? 0);
        $summary['refund_total'] += (float)($r['refund_total'] ?? 0);
        $summary['reverse_total'] += (float)($r['reverse_total'] ?? 0);
        $summary['bids_deleted'] += (int)($r['bids_deleted'] ?? 0);
        $summary['wins_deleted'] += (int)($r['wins_deleted'] ?? 0);
    }
    $st->close();
} catch (Throwable $e) {
    $groups = [];
}

/* ================== AJAX: fetch action details ================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $ref = trim($_GET['ref'] ?? '');
    if ($ref === '') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'message'=>'missing ref'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rows = [];
    try {
        $st = $db->prepare("
            SELECT id, user_id, bid_refund_amount, win_reverse_amount, bids_deleted, wins_deleted, details_json, created_at
            FROM market_reverse_log
            WHERE action_ref = ?
            ORDER BY id ASC
        ");
        $st->bind_param('s', $ref);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $dj = (string)($r['details_json'] ?? '');
            $details = null;
            if ($dj !== '') {
                $tmp = json_decode($dj, true);
                if (is_array($tmp)) $details = $tmp;
            }
            $rows[] = [
                'id' => (int)($r['id'] ?? 0),
                'user_id' => (string)($r['user_id'] ?? ''),
                'bid_refund_amount' => (float)($r['bid_refund_amount'] ?? 0),
                'win_reverse_amount' => (float)($r['win_reverse_amount'] ?? 0),
                'bids_deleted' => (int)($r['bids_deleted'] ?? 0),
                'wins_deleted' => (int)($r['wins_deleted'] ?? 0),
                'created_at' => (string)($r['created_at'] ?? ''),
                'details' => $details
            ];
        }
        $st->close();
    } catch (Throwable $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'ref'=>$ref,'rows'=>$rows], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reverse Logs</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{--pdark:#770000;--plight:#b21616;--card:#fff;--text:#4e342e;--bg1:#f5f0e5;--bg2:#e8d9c9}
*{box-sizing:border-box;margin:0;padding:0;font-family:Roboto,sans-serif}
body{background:linear-gradient(135deg,var(--bg1),var(--bg2));color:var(--text);min-height:100vh}
.bar{display:flex;align-items:center;justify-content:space-between;padding:12px 5vw;background:var(--card);box-shadow:0 2px 6px rgba(0,0,0,.1)}
.btn{background:linear-gradient(90deg,var(--pdark),var(--plight));color:#fff;padding:6px 14px;border-radius:20px;text-decoration:none;font-size:.9rem;border:none;cursor:pointer;display:inline-flex;align-items:center;white-space:nowrap}
.btn.ghost{background:#fff;color:var(--pdark);border:1px solid var(--pdark)}
.btn.ghost:hover{background:var(--pdark);color:#fff}
.page{padding:10px 4vw 22px;max-width:1100px;margin:0 auto}
.title{font-size:1.25rem;font-weight:700;color:var(--pdark);margin:12px 0 10px}
.card{background:var(--card);border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);padding:14px;margin-bottom:14px}

.filters{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;align-items:end}
.filters label{font-size:.82rem;color:#6b3b36}
input,select{width:100%;padding:9px;font-size:.92rem;border:1px solid #ddd;border-radius:8px;background:#fff}
.filters .span-2{grid-column:span 2}
.filters .span-3{grid-column:span 3}
.filters .span-6{grid-column:1/-1}

@media(max-width:900px){.filters{grid-template-columns:repeat(3,1fr)}}
@media(max-width:520px){.filters{grid-template-columns:repeat(2,1fr)}}

.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:10px}
@media(max-width:900px){.stats{grid-template-columns:repeat(3,1fr)}}
@media(max-width:520px){.stats{grid-template-columns:repeat(2,1fr)}}
.stat{border:1px solid #f0d7d7;border-radius:10px;padding:10px 10px;background:#fff}
.stat .k{font-size:.78rem;color:#7b4b45}
.stat .v{font-size:1.05rem;font-weight:700;color:var(--pdark);margin-top:2px}

.table{width:100%;border-collapse:collapse;font-size:.9rem}
.table th,.table td{padding:8px;text-align:center;vertical-align:middle}
.table th{background:var(--pdark);color:#fff;font-weight:500;font-size:.85rem}
.table tr:nth-child(even){background:#fbf8f8}
.code{font-family:'Courier New',monospace;font-weight:700;color:var(--pdark)}
.small{font-size:.82rem;color:#6a4a45}
.badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:.78rem;font-weight:700}
.badge.open{background:#e7f5ff;color:#0b61a4;border:1px solid #bcdfff}
.badge.close{background:#fff3e6;color:#9a4c00;border:1px solid #ffd6ad}
.badge.full{background:#eee;color:#000;border:1px solid #ddd}

.viewbtn{border:1px solid var(--pdark);background:#fff;color:var(--pdark);border-radius:8px;padding:6px 10px;cursor:pointer;font-weight:700}
.viewbtn:hover{background:var(--pdark);color:#fff}

.flash{padding:8px 10px;border-radius:8px;font-size:.9rem;font-weight:600;text-align:center}
.flash.ok{background:#e6f9e4;color:#178910}
.flash.err{background:#ffe8e8;color:var(--pdark)}

.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:999}
.modal-box{width:95%;max-width:980px;max-height:88vh;overflow:auto;background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.3);padding:14px}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.modal-title{font-size:1rem;font-weight:800;color:var(--pdark)}
.modal-close{border:none;background:transparent;font-size:1.2rem;cursor:pointer;color:#555}

.detail-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
@media(max-width:900px){.detail-grid{grid-template-columns:1fr}}
pre{white-space:pre-wrap;word-break:break-word;background:#faf4f0;border:1px solid #f0d7d7;padding:10px;border-radius:10px;font-size:.82rem;color:#3b2a26}
</style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

<div class="bar">
  <a class="btn" href="open_market.php"><i class="fa fa-arrow-left" style="margin-right:6px"></i>Back</a>
  <a class="btn ghost" href="admin_dashboard.php"><i class="fa fa-house" style="margin-right:6px"></i>Dashboard</a>
</div>

<div class="page">
  <div class="title"><i class="fa fa-rotate-left" style="margin-right:8px"></i>Reverse Logs (Refund + Win Reverse)</div>

  <div class="card">
    <form method="get" class="filters">
      <label>From
        <input type="date" name="from" value="<?= esc($from) ?>">
      </label>
      <label>To
        <input type="date" name="to" value="<?= esc($to) ?>">
      </label>

      <label class="span-2">Market
        <select name="market">
          <option value="">All</option>
          <?php
require_once 'includes/db.php'; foreach ($markets as $m): ?>
            <option value="<?= esc($m) ?>" <?= ($market === $m ? 'selected' : '') ?>><?= esc($m) ?></option>
          <?php
require_once 'includes/db.php'; endforeach; ?>
        </select>
      </label>

      <label>Scope
        <select name="scope">
          <option value="" <?= ($scope===''?'selected':'') ?>>All</option>
          <option value="OPEN" <?= ($scope==='OPEN'?'selected':'') ?>>OPEN</option>
          <option value="CLOSE" <?= ($scope==='CLOSE'?'selected':'') ?>>CLOSE</option>
          <option value="FULL" <?= ($scope==='FULL'?'selected':'') ?>>FULL</option>
        </select>
      </label>

      <label class="span-2">Action Ref
        <input type="text" name="action_ref" placeholder="REV_20260106..." value="<?= esc($actionRef) ?>">
      </label>

      <label class="span-2">User ID
        <input type="text" name="user_id" placeholder="LL001" value="<?= esc($userId) ?>">
      </label>

      <div class="span-6" style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end">
        <button class="btn" type="submit"><i class="fa fa-filter" style="margin-right:6px"></i>Apply</button>
        <a class="btn ghost" href="revise_bid_win_balance.php"><i class="fa fa-rotate" style="margin-right:6px"></i>Reset</a>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="stats">
      <div class="stat"><div class="k">Actions</div><div class="v"><?= (int)$summary['actions'] ?></div></div>
      <div class="stat"><div class="k">Users</div><div class="v"><?= (int)$summary['users'] ?></div></div>
      <div class="stat"><div class="k">Bid Refund Total</div><div class="v">₹<?= esc(LL_money($summary['refund_total'])) ?></div></div>
      <div class="stat"><div class="k">Win Reverse Total</div><div class="v">₹<?= esc(LL_money($summary['reverse_total'])) ?></div></div>
      <div class="stat"><div class="k">Deleted (Bids/Wins)</div><div class="v"><?= (int)$summary['bids_deleted'] ?>/<?= (int)$summary['wins_deleted'] ?></div></div>
    </div>
    <div class="small" style="margin-top:10px">
      Showing max 300 actions. Use filters for older logs.
    </div>
  </div>

  <div class="card">
    <?php
require_once 'includes/db.php'; if (empty($groups)): ?>
      <div class="flash err">No logs found for selected filters.</div>
    <?php
require_once 'includes/db.php'; else: ?>
      <table class="table">
        <tr>
          <th>Time</th>
          <th>Action Ref</th>
          <th>Market</th>
          <th>Date</th>
          <th>Scope</th>
          <th>Users</th>
          <th>Refund</th>
          <th>Win Reverse</th>
          <th>Del Bids/Wins</th>
          <th>View</th>
        </tr>

        <?php
require_once 'includes/db.php'; foreach ($groups as $g): ?>
          <?php
require_once 'includes/db.php';
            $sc = strtoupper((string)($g['delete_scope'] ?? 'FULL'));
            $badgeCls = ($sc === 'OPEN') ? 'open' : (($sc === 'CLOSE') ? 'close' : 'full');
          ?>
          <tr>
            <td class="small"><?= esc($g['created_at'] ?? '') ?></td>
            <td class="code"><?= esc($g['action_ref'] ?? '') ?></td>
            <td><?= esc($g['market'] ?? '') ?></td>
            <td><?= esc($g['result_date'] ?? '') ?></td>
            <td><span class="badge <?= esc($badgeCls) ?>"><?= esc($sc) ?></span></td>
            <td><?= (int)($g['users_count'] ?? 0) ?></td>
            <td>₹<?= esc(LL_money($g['refund_total'] ?? 0)) ?></td>
            <td>₹<?= esc(LL_money($g['reverse_total'] ?? 0)) ?></td>
            <td><?= (int)($g['bids_deleted'] ?? 0) ?>/<?= (int)($g['wins_deleted'] ?? 0) ?></td>
            <td>
              <button type="button" class="viewbtn" onclick="openDetails('<?= esc($g['action_ref'] ?? '') ?>')">
                <i class="fa fa-eye" style="margin-right:6px"></i>View
              </button>
            </td>
          </tr>
        <?php
require_once 'includes/db.php'; endforeach; ?>
      </table>
    <?php
require_once 'includes/db.php'; endif; ?>
  </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="detailModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title" id="modalTitle"><i class="fa fa-list" style="margin-right:8px"></i>Details</div>
      <button class="modal-close" onclick="closeDetails()">&times;</button>
    </div>

    <div id="modalBody">
      <div class="flash ok">Loading...</div>
    </div>
  </div>
</div>

<script>
function money2(v){
  try { return Number(v || 0).toFixed(2); } catch(e){ return "0.00"; }
}
function escHtml(s){
  s = String(s ?? '');
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function openDetails(ref){
  document.getElementById('detailModal').style.display = 'flex';
  document.getElementById('modalTitle').innerHTML = '<i class="fa fa-list" style="margin-right:8px"></i>Action Ref: <span class="code">' + escHtml(ref) + '</span>';

  var body = document.getElementById('modalBody');
  body.innerHTML = '<div class="flash ok">Loading...</div>';

  fetch('revise_bid_win_balance.php?ajax=1&ref=' + encodeURIComponent(ref))
    .then(r => r.json())
    .then(function(j) {
      if (!j || !j.ok) {
        body.innerHTML = '<div class="flash err">Failed: ' + escHtml((j && j.message) ? j.message : 'error') + '</div>';
        return;
      }

      var rows = Array.isArray(j.rows) ? j.rows : [];
      if (rows.length === 0) {
        body.innerHTML = '<div class="flash err">No rows found for this action_ref.</div>';
        return;
      }

      var sumRefund=0, sumRev=0, sumBD=0, sumWD=0;

      var html = '';
      html += '<div class="card" style="padding:12px;margin-bottom:10px">';
      html += '<div class="detail-grid">';
      html += '<div class="stat"><div class="k">Users</div><div class="v">' + rows.length + '</div></div>';
      html += '<div class="stat"><div class="k">Totals</div><div class="v" id="totalsLine">...</div></div>';
      html += '</div>';
      html += '<div class="small" style="margin-top:8px">Per-user reverse entries (wallet was updated in open_market.php; this page only shows logs).</div>';
      html += '</div>';

      html += '<table class="table">';
      html += '<tr><th>User</th><th>Bid Refund</th><th>Win Reverse</th><th>Del Bids</th><th>Del Wins</th><th>Details</th></tr>';

      rows.forEach(function(r, idx){
        sumRefund += Number(r.bid_refund_amount || 0);
        sumRev    += Number(r.win_reverse_amount || 0);
        sumBD     += Number(r.bids_deleted || 0);
        sumWD     += Number(r.wins_deleted || 0);

        var details = r.details || null;
        var detailsText = '';
        if (details) {
          try { detailsText = JSON.stringify(details, null, 2); } catch(e) { detailsText = ''; }
        }

        var btn = '';
        if (detailsText) {
          btn = '<button class="viewbtn" type="button" onclick="togglePre(' + idx + ')"><i class="fa fa-code" style="margin-right:6px"></i>Show</button>';
        } else {
          btn = '<span class="small">-</span>';
        }

        html += '<tr>';
        html += '<td class="code">' + escHtml(r.user_id || '') + '</td>';
        html += '<td>₹' + escHtml(money2(r.bid_refund_amount)) + '</td>';
        html += '<td>₹' + escHtml(money2(r.win_reverse_amount)) + '</td>';
        html += '<td>' + escHtml(String(r.bids_deleted || 0)) + '</td>';
        html += '<td>' + escHtml(String(r.wins_deleted || 0)) + '</td>';
        html += '<td>' + btn + '</td>';
        html += '</tr>';

        if (detailsText) {
          html += '<tr id="preRow' + idx + '" style="display:none">';
          html += '<td colspan="6" style="text-align:left;padding:10px">';
          html += '<pre>' + escHtml(detailsText) + '</pre>';
          html += '</td></tr>';
        }
      });

      html += '</table>';

      body.innerHTML = html;

      var totalsLine = '₹' + money2(sumRefund) + ' refund, ₹' + money2(sumRev) + ' reverse, ' + sumBD + '/' + sumWD + ' bids/wins deleted';
      var el = document.getElementById('totalsLine');
      if (el) el.textContent = totalsLine;
    })
    .catch(function(err) {
      body.innerHTML = '<div class="flash err">Network error.</div>';
    });
}

function togglePre(i){
  var row = document.getElementById('preRow' + i);
  if (!row) return;
  row.style.display = (row.style.display === 'none' || row.style.display === '') ? 'table-row' : 'none';
}

function closeDetails(){
  document.getElementById('detailModal').style.display = 'none';
}
</script>

</body>
</html>

