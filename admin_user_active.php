<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';
if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $db = db();
  $db->query("SET time_zone = '+05:30'");
} catch (Throwable $e) {
  die('DB error');
}

/* ===================== app_settings (single source) ===================== */
function ll_ensure_app_settings(mysqli $db): void
{
  try {
    $db->query("
      CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(100) NOT NULL,
        setting_value VARCHAR(255) NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (setting_key)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
  } catch (Throwable $e) {
  }

  // Keep only needed keys (safe)
  try {
    $db->query("DELETE FROM app_settings WHERE setting_key NOT IN ('global_user_deactive','new_user_default_deactive')");
  } catch (Throwable $e) {
  }

  // Ensure keys exist (no overwrite)
  try {
    $db->query("
      INSERT INTO app_settings (setting_key, setting_value)
      VALUES ('global_user_deactive','0'), ('new_user_default_deactive','0')
      ON DUPLICATE KEY UPDATE setting_value = setting_value
    ");
  } catch (Throwable $e) {
  }
}

function ll_setting_get(mysqli $db, string $key, string $default = '0'): string
{
  ll_ensure_app_settings($db);
  try {
    $st = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1");
    $st->bind_param('s', $key);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    return $row ? (string) $row['setting_value'] : $default;
  } catch (Throwable $e) {
    return $default;
  }
}
function ll_setting_set(mysqli $db, string $key, string $value): bool
{
  ll_ensure_app_settings($db);
  try {
    $st = $db->prepare("
      INSERT INTO app_settings (setting_key, setting_value)
      VALUES (?,?)
      ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)
    ");
    $st->bind_param('ss', $key, $value);
    $ok = $st->execute();
    $st->close();
    return (bool) $ok;
  } catch (Throwable $e) {
    return false;
  }
}

/* ===================== AJAX toggle user ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_toggle'])) {
  header('Content-Type: application/json; charset=utf-8');
  $uid = (int) ($_POST['uid'] ?? 0);
  $val = (int) ($_POST['new_status'] ?? 0);
  $val = ($val === 1) ? 1 : 0;

  try {
    $st = $db->prepare("UPDATE users SET is_active=? WHERE id=?");
    $st->bind_param('ii', $val, $uid);
    $st->execute();
    $st->close();
    echo json_encode(['ok' => true, 'status' => $val]);
  } catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
}

/* ===================== Actions ===================== */
$msg = '';
$msgType = 'info';

/* Save default status (UI: Active=1, Deactive=0) -> store new_user_default_deactive (Deactive=1, Active=0) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_default_status'])) {
  $ui = isset($_POST['new_default_status']) ? (int) $_POST['new_default_status'] : 1;
  $ui = ($ui === 0) ? 0 : 1;
  $storeDeactive = ($ui === 1) ? '0' : '1';

  $ok = ll_setting_set($db, 'new_user_default_deactive', $storeDeactive);
  $after = (int) ll_setting_get($db, 'new_user_default_deactive', '0');

  if ($ok) {
    $msg = 'Saved: ' . ($after === 1 ? 'DEACTIVE' : 'ACTIVE');
    $msgType = 'success';
  } else {
    $msg = 'Save failed!';
    $msgType = 'error';
  }
}

/* bulk */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !isset($_POST['ajax_toggle']) && !isset($_POST['save_default_status'])) {
  $act = (string) $_POST['bulk_action'];
  try {
    if ($act === 'all_active') {
      $db->query("UPDATE users SET is_active=1");
      $msg = 'All ACTIVE.';
      $msgType = 'success';
    }
    if ($act === 'all_deactive') {
      $db->query("UPDATE users SET is_active=0");
      $msg = 'All DEACTIVE.';
      $msgType = 'success';
    }
  } catch (Throwable $e) {
    $msg = 'Action failed: ' . $e->getMessage();
    $msgType = 'error';
  }
}

/* read default */
$deactiveFlag = (int) ll_setting_get($db, 'new_user_default_deactive', '0'); // 1=deactive default
$deactiveFlag = ($deactiveFlag === 1) ? 1 : 0;
$defaultNew = ($deactiveFlag === 1) ? 0 : 1; // UI value

/* ================== SEARCH + PAGINATION + FILTER ================== */
$limit = 10;
$search = trim($_GET['q'] ?? '');
$hasSearch = ($search !== '');
$status = $_GET['status'] ?? 'all';
$allowed = ['all', 'active', 'deactive'];
if (!in_array($status, $allowed, true))
  $status = 'all';

$condStatus = '';
if ($status === 'active')
  $condStatus = " AND IFNULL(is_active,1)=1";
if ($status === 'deactive')
  $condStatus = " AND IFNULL(is_active,1)=0";

/* total */
if ($hasSearch) {
  $like = "%{$search}%";
  $stmt = $db->prepare("SELECT COUNT(*) c FROM users WHERE (user_id LIKE ? OR name LIKE ? OR mobile_no LIKE ?) $condStatus");
  $stmt->bind_param('sss', $like, $like, $like);
  $stmt->execute();
  $stmt->bind_result($total);
  $stmt->fetch();
  $stmt->close();
} else {
  $r = $db->query("SELECT COUNT(*) c FROM users WHERE 1 $condStatus")->fetch_assoc();
  $total = (int) ($r['c'] ?? 0);
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$pages = max(1, (int) ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;

/* counts */
$activeCount = 0;
$inactiveCount = 0;
try {
  $r = $db->query("SELECT SUM(IFNULL(is_active,1)=1) active_cnt, SUM(IFNULL(is_active,1)=0) deactive_cnt FROM users");
  if ($r) {
    $row = $r->fetch_assoc();
    $activeCount = (int) $row['active_cnt'];
    $inactiveCount = (int) $row['deactive_cnt'];
  }
} catch (Throwable $e) {
}

/* list */
$users = [];
if ($hasSearch) {
  $like = "%{$search}%";
  $stmt = $db->prepare("
    SELECT id,user_id,name,mobile_no,IFNULL(is_active,1) is_active
    FROM users
    WHERE (user_id LIKE ? OR name LIKE ? OR mobile_no LIKE ?) $condStatus
    ORDER BY id DESC LIMIT ? OFFSET ?
  ");
  $stmt->bind_param('sssii', $like, $like, $like, $limit, $offset);
} else {
  $stmt = $db->prepare("
    SELECT id,user_id,name,mobile_no,IFNULL(is_active,1) is_active
    FROM users
    WHERE 1 $condStatus
    ORDER BY id DESC LIMIT ? OFFSET ?
  ");
  $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc())
  $users[] = $row;
$stmt->close();

/* pagination html */
$paginationHtml = '';
if ($pages >= 1) { // Show even if 1 page to maintain layout structure
  $qextra = '';
  if ($hasSearch)
    $qextra .= '&q=' . urlencode($search);
  if ($status !== 'all')
    $qextra .= '&status=' . urlencode($status);

  ob_start(); ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?page=<?= ($page - 1) . $qextra ?>" class="page-btn">&laquo; Previous</a>
    <?php else: ?>
      <span class="page-btn disabled">&laquo; Previous</span>
    <?php endif; ?>

    <span class="page-info">Page <?= $page ?> of <?= $pages ?></span>

    <?php if ($page < $pages): ?>
      <a href="?page=<?= ($page + 1) . $qextra ?>" class="page-btn">Next &raquo;</a>
    <?php else: ?>
      <span class="page-btn disabled">Next &raquo;</span>
    <?php endif; ?>
  </div>
  <?php $paginationHtml = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>User Active / Deactive – Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --red: #e53935;
      --green: #2e7d32;
      --brown: #4e342e;
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
      backdrop-filter: blur(14px);
      background: var(--glass);
      box-shadow: var(--shadow);
    }

    .top-btn {
      background: var(--brown);
      color: #fff;
      padding: 8px 16px;
      border-radius: 10px;
      font-size: 14px;
      display: inline-flex;
      align-items: center;
      gap: 6px
    }

    .top-btn i {
      font-size: 13px
    }

    .top-bar h1 {
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
      font-size: 20px;
      font-weight: 600;
      color: var(--brown);
    }

    .top-right {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-left: auto;
    }

    .top-bar input {
      width: 220px;
      max-width: 100%;
      padding: 9px 14px;
      border: 1px solid rgba(0, 0, 0, .10);
      border-radius: 999px;
      font-size: 14px;
      background: #faf7f3;
    }

    .status-chips {
      display: inline-flex;
      align-items: center;
      background: rgba(255, 255, 255, 0.96);
      border-radius: 999px;
      padding: 3px;
      box-shadow: 0 3px 10px rgba(0, 0, 0, .06);
      border: 1px solid rgba(78, 52, 46, .14);
    }

    .status-chip {
      border: none;
      outline: none;
      background: transparent;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 500;
      cursor: pointer;
      color: #6d4c41;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: all .18s ease-out;
    }

    .status-chip .dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      box-shadow: 0 0 0 2px rgba(0, 0, 0, .04);
    }

    .status-chip.all .dot {
      background: #fff;
      box-shadow: 0 0 0 2px rgba(0, 0, 0, .10);
    }

    .status-chip.active-status .dot {
      background: var(--green);
    }

    .status-chip.deactive-status .dot {
      background: #c62828;
    }

    .status-chip:hover {
      background: rgba(244, 234, 228, .9);
    }

    .status-chip.active {
      background: linear-gradient(135deg, #5d4037, #8d6e63);
      color: #fff;
      box-shadow: 0 3px 10px rgba(93, 64, 55, .35);
    }

    .status-chip.active .dot {
      box-shadow: 0 0 0 3px rgba(255, 255, 255, .4);
    }

    .msg-box {
      margin: 10px 0 12px;
      padding: 9px 10px;
      border-radius: 8px;
      font-size: 13px;
      border: 1px solid transparent;
    }

    .msg-box.info {
      background: #e3f2fd;
      border-color: #90caf9;
      color: #0d47a1;
    }

    .msg-box.error {
      background: #ffebee;
      border-color: #ef9a9a;
      color: #b71c1c;
    }

    .msg-box.success {
      background: #e8f5e9;
      border-color: #a5d6a7;
      color: #1b5e20;
    }

    .control-strip {
      display: grid;
      grid-template-columns: 1fr 1.4fr 1fr;
      gap: 12px;
      align-items: stretch;
      margin: 10px 0 12px;
    }

    .strip-block {
      background: #fff;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 12px 12px;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      min-height: 74px;
    }

    .strip-left {
      justify-content: flex-start
    }

    .strip-mid {
      justify-content: center
    }

    .strip-right {
      justify-content: flex-end
    }

    .pill {
      font-size: 12px;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid rgba(0, 0, 0, 0.12);
      background: #fff;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .pill span.dot {
      width: 8px;
      height: 8px;
      border-radius: 50%
    }

    .pill.active span.dot {
      background: #2e7d32;
    }

    .pill.deactive span.dot {
      background: #c62828;
    }

    .pill strong {
      font-weight: 700;
    }

    .setting-title {
      font-size: 13px;
      color: #5d4037;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
    }

    .setting-title b {
      color: #c62828
    }

    .radio-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border: 1px solid rgba(0, 0, 0, .12);
      border-radius: 999px;
      background: #faf7f3;
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      user-select: none;
    }

    .radio-pill input {
      accent-color: #c62828;
    }

    .save-btn {
      border: none;
      border-radius: 10px;
      padding: 8px 12px;
      background: #4e342e;
      color: #fff;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-bulk {
      border: none;
      border-radius: 10px;
      padding: 8px 12px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-all-active {
      background: #2e7d32;
      color: #fff;
    }

    .btn-all-deactive {
      background: #c62828;
      color: #fff;
    }

    .table-wrap {
      overflow-x: auto;
      margin-top: 10px;
      background: #fff;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }

    table {
      width: 100%;
      min-width: 750px;
      border-collapse: collapse
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
      font-weight: 600
    }

    tbody tr:nth-child(even) {
      background: #fafafa
    }

    tbody tr:hover {
      background: #fff3e0
    }

    .status-pill {
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 800;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .status-pill.active {
      background: rgba(46, 125, 50, 0.1);
      color: #1b5e20;
      border: 1px solid rgba(56, 142, 60, 0.6);
    }

    .status-pill.deactive {
      background: rgba(198, 40, 40, 0.08);
      color: #b71c1c;
      border: 1px solid rgba(211, 47, 47, 0.7);
    }

    .status-pill i {
      font-size: 10px
    }

    .switch {
      position: relative;
      display: inline-block;
      width: 50px;
      height: 26px
    }

    .switch input {
      opacity: 0;
      width: 0;
      height: 0
    }

    .slider {
      position: absolute;
      cursor: pointer;
      inset: 0;
      background: #bdbdbd;
      border-radius: 999px;
      transition: .25s;
      box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.3);
    }

    .slider:before {
      content: "";
      position: absolute;
      height: 20px;
      width: 20px;
      left: 3px;
      top: 3px;
      background: #fff;
      border-radius: 50%;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.35);
      transition: .25s;
    }

    input:checked+.slider {
      background: #1e88e5;
    }

    input:checked+.slider:before {
      transform: translateX(24px);
    }

    .pagination {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 12px;
      margin-top: 20px;
    }

    .pagination .page-btn {
      padding: 8px 16px;
      border-radius: 8px;
      background: var(--brown);
      color: #fff;
      font-size: 14px;
      font-weight: 600;
      min-width: 100px;
      text-align: center;
    }

    .pagination .page-btn.disabled {
      background: #e0e0e0;
      color: #9e9e9e;
      cursor: default;
      pointer-events: none;
    }

    .pagination .page-info {
      font-size: 14px;
      font-weight: 700;
      color: #5d4037;
    }

    @media(max-width:980px) {
      .control-strip {
        grid-template-columns: 1fr;
      }

      .strip-right,
      .strip-mid,
      .strip-left {
        justify-content: flex-start
      }
    }

    @media(max-width:650px) {
      body {
        padding: 18px 10px
      }

      .top-bar {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start
      }

      .top-bar h1 {
        position: static;
        transform: none;
        order: -1;
        margin-bottom: 4px
      }

      .top-right {
        width: 100%;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px
      }

      table {
        min-width: 0
      }
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

  <div class="top-bar">
    <a href="admin_dashboard.php" class="top-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
    <h1>User Active / Deactive</h1>

    <div class="top-right">
      <form id="filterForm" method="get" action="">
        <input type="hidden" name="status" id="statusInput" value="<?= htmlspecialchars($status) ?>">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <div class="status-chips">
            <button type="button" class="status-chip all <?= $status === 'all' ? 'active' : '' ?>"
              data-status="all"><span class="dot"></span> All</button>
            <button type="button" class="status-chip active-status <?= $status === 'active' ? 'active' : '' ?>"
              data-status="active"><span class="dot"></span> Active</button>
            <button type="button" class="status-chip deactive-status <?= $status === 'deactive' ? 'active' : '' ?>"
              data-status="deactive"><span class="dot"></span> Deactive</button>
          </div>
          <div class="search">
            <input id="searchBox" name="q" type="text" placeholder="Search by User ID, Name or Mobile…"
              value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
          </div>
        </div>
      </form>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="msg-box <?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="control-strip">
    <div class="strip-block strip-left">
      <div class="pill active"><span class="dot"></span><span><strong>Active:</strong> <?= (int) $activeCount ?></span>
      </div>
      <div class="pill deactive"><span class="dot"></span><span><strong>Deactive:</strong>
          <?= (int) $inactiveCount ?></span></div>
    </div>

    <form method="post" class="strip-block strip-mid">
      <div class="setting-title"><i class="fa-solid fa-user-plus"></i> New Account Default Status:
        <b><?= $defaultNew === 1 ? 'ACTIVE' : 'DEACTIVE' ?></b>
      </div>
      <label class="radio-pill"><input type="radio" name="new_default_status" value="1" <?= $defaultNew === 1 ? 'checked' : '' ?>> Active</label>
      <label class="radio-pill"><input type="radio" name="new_default_status" value="0" <?= $defaultNew === 0 ? 'checked' : '' ?>> Deactive</label>
      <button type="submit" name="save_default_status" value="1" class="save-btn"><i
          class="fa-solid fa-floppy-disk"></i> Save</button>
    </form>

    <form method="post" class="strip-block strip-right">
      <span style="font-size:13px;color:#5d4037;font-weight:700;">Actions:</span>
      <button type="submit" name="bulk_action" value="all_active" class="btn-bulk btn-all-active"><i
          class="fa-solid fa-toggle-on"></i> All Active</button>
      <button type="submit" name="bulk_action" value="all_deactive" class="btn-bulk btn-all-deactive"><i
          class="fa-solid fa-toggle-off"></i> All Deactive</button>
    </form>
  </div>

  <div class="table-wrap">
    <table id="userTable">
      <thead>
        <tr>
          <th>Sr No</th>
          <th>User ID</th>
          <th>Name</th>
          <th>Mobile</th>
          <th>Status</th>
          <th>Toggle</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr>
            <td colspan="6">No users found.</td>
          </tr>
        <?php else:
          foreach ($users as $i => $u):
            $isAct = ((int) $u['is_active'] === 1); ?>
            <tr data-id="<?= (int) $u['id'] ?>">
              <td><?= $offset + $i + 1 ?></td>
              <td><?= htmlspecialchars($u['user_id'] ?? '') ?></td>
              <td><?= htmlspecialchars($u['name'] ?? '') ?></td>
              <td><?= htmlspecialchars($u['mobile_no'] ?? '') ?></td>
              <td>
                <span class="status-pill <?= $isAct ? 'active' : 'deactive' ?>">
                  <i class="fa-solid <?= $isAct ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                  <?= $isAct ? 'ACTIVE' : 'DEACTIVE' ?>
                </span>
              </td>
              <td>
                <label class="switch">
                  <input type="checkbox" class="toggle-switch" <?= $isAct ? 'checked' : '' ?>>
                  <span class="slider round"></span>
                </label>
              </td>
            </tr>
          <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?= $paginationHtml ?>

  <script>
    var searchBox = document.getElementById('searchBox');
    var filterForm = document.getElementById('filterForm');
    var statusInput = document.getElementById('statusInput');
    var searchTimer = null, lastQuery = searchBox.value.trim();

    function attachToggleButtons() {
      document.querySelectorAll('.toggle-switch').forEach(function(sw) {
        if (sw.dataset.bound === '1') return;
        sw.dataset.bound = '1';
        sw.addEventListener('change', function () {
          var checkbox = this;
          var tr = checkbox.closest('tr');
          var id = tr.getAttribute('data-id');
          var pill = tr.querySelector('.status-pill');
          var newStatus = checkbox.checked ? 1 : 0;
          var prevChecked = !checkbox.checked;

          var fd = new FormData();
          fd.append('ajax_toggle', '1');
          fd.append('uid', id);
          fd.append('new_status', String(newStatus));

          checkbox.disabled = true;
          fetch('admin_user_active.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(function(j) {
              if (j.ok) {
                if (newStatus === 1) {
                  pill.classList.remove('deactive'); pill.classList.add('active');
                  pill.innerHTML = '<i class="fa-solid fa-circle-check"></i> ACTIVE';
                } else {
                  pill.classList.remove('active'); pill.classList.add('deactive');
                  pill.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> DEACTIVE';
                }
              } else {
                alert('Update failed: ' + (j.error || 'Unknown'));
                checkbox.checked = prevChecked;
              }
            })
            .catch(function(err) { alert('Network error: ' + err); checkbox.checked = prevChecked; })
            .finally(function() { checkbox.disabled = false; });
        });
      });
    }

    function fetchUsers(page = 1) {
      var q = searchBox.value.trim();
      var status = statusInput.value || 'all';
      var params = new URLSearchParams();
      if (q !== '') params.set('q', q);
      if (status && status !== 'all') params.set('status', status);
      params.set('page', page);

      fetch('admin_user_active.php?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.text())
        .then(function(html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var newTbody = doc.querySelector('#userTable tbody');
          var curTbody = document.querySelector('#userTable tbody');
          if (newTbody && curTbody) curTbody.innerHTML = newTbody.innerHTML;

          var newPag = doc.querySelector('.pagination');
          var curPag = document.querySelector('.pagination');
          if (newPag) {
            if (curPag) curPag.innerHTML = newPag.innerHTML;
            else document.body.appendChild(newPag);
          } else if (curPag) curPag.remove();

          if (history && history.replaceState) {
            history.replaceState({}, '', window.location.pathname + '?' + params.toString());
          }
          attachToggleButtons();
        })
        .catch(console.error);
    }

    document.querySelectorAll('.status-chip').forEach(function(chip) {
      chip.addEventListener('click', function () {
        document.querySelectorAll('.status-chip').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        statusInput.value = this.dataset.status || 'all';
        fetchUsers(1);
      });
    });

    searchBox.addEventListener('input', function () {
      var q = searchBox.value.trim();
      if (q === lastQuery) return;
      lastQuery = q;
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function() { return fetchUsers(1); }, 400);
    });

    filterForm.addEventListener('submit', function(e) { e.preventDefault(); fetchUsers(1); });

    document.addEventListener('click', function (e) {
      var link = e.target.closest('.pagination a');
      if (!link) return;
      e.preventDefault();
      var url = new URL(link.href, location.href);
      fetchUsers(parseInt(url.searchParams.get('page') || '1', 10));
    });

    document.addEventListener('DOMContentLoaded', attachToggleButtons);
  </script>
</body>

</html>
<?php $db->close(); 
