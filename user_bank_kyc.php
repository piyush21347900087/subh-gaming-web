<?php
require_once 'includes/db.php';
// user_bank_kyc.php – Show ALL KYC requests (not just pending), keep modal/details, allow actions only for Pending
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

$db = db();
if ($db->connect_error)
  die('DB error: ' . $db->connect_error);

// Auto-approve any pending/linked bank KYC requests immediately
try {
  $db->query("UPDATE bank_kyc SET status='Approved' WHERE LOWER(status) = 'linked' OR LOWER(status) = 'pending'");
} catch (Exception $e) {}

// AJAX: Approve/Reject
if (isset($_POST['ajax'], $_POST['id'], $_POST['newStatus'])) {
  require_once 'includes/fcm_helper.php';
  $id = intval($_POST['id']);
  $st = ($_POST['newStatus'] === 'Approved') ? 'Approved' : 'Rejected';
  
  // Fetch user_id for notification
  $userId = '';
  $stmtUser = $db->prepare("SELECT user_id FROM bank_kyc WHERE id = ?");
  $stmtUser->bind_param("i", $id);
  $stmtUser->execute();
  $stmtUser->bind_result($userId);
  $stmtUser->fetch();
  $stmtUser->close();

  $q = $db->prepare("UPDATE bank_kyc SET status=? WHERE id=?");
  $q->bind_param('si', $st, $id);
  $ok = $q->execute();
  $q->close();

  if ($ok && !empty($userId)) {
      // Trigger user-side reload via FCM
      pushWalletFcm($db, $userId, 'reload', 0.0, $st, 'KYC ' . $st, 'Your Bank KYC status has been updated to ' . $st);
  }

  echo json_encode(['ok' => $ok, 'status' => $st]);
  exit;
}

// Pagination
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$total = $db->query("SELECT COUNT(*) FROM bank_kyc")->fetch_row()[0];
$pages = max(1, ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;
$rows = $db->query("
    SELECT id, user_id, bank_name, account_name, account_number, ifsc, branch, status, date_time
    FROM bank_kyc
    ORDER BY status='Pending' DESC, id DESC
    LIMIT $limit OFFSET $offset
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Bank KYC – Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
      min-height: 100vh;
      padding: 24px 18px
    }

    a {
      color: inherit
    }

    .top-bar {
      position: relative;
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      padding: 12px 20px;
      border-radius: var(--radius);
      backdrop-filter: blur(10px);
      background: var(--glass);
      box-shadow: var(--shadow)
    }

    .top-btn {
      background: var(--brown);
      color: #fff;
      padding: 8px 16px;
      border-radius: 8px;
      text-decoration: none;
      font-size: 14px
    }

    .top-bar h1 {
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
      font-size: 20px;
      font-weight: 600;
      color: var(--brown)
    }

    .top-bar .search {
      flex: 1;
      text-align: right
    }

    .top-bar input {
      width: 320px;
      max-width: 100%;
      padding: 9px 14px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 15px
    }

    .table-wrap {
      overflow-x: auto
    }

    table {
      width: 100%;
      min-width: 820px;
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
      font-weight: 600;
      user-select: none;
      cursor: pointer
    }

    th.sorted-asc::after {
      content: " ▲"
    }

    th.sorted-desc::after {
      content: " ▼"
    }

    tbody tr:nth-child(even) {
      background: #fafafa
    }

    tbody tr:hover {
      background: #fffbea;
      box-shadow: 0 0 0 9999px #fffbea inset
    }

    .fade-update {
      animation: fadeBG 2s
    }

    @keyframes fadeBG {
      from {
        background: #e8f5e9
      }

      to {
        background: inherit
      }
    }

    .badge {
      display: inline-block;
      padding: 3px 9px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      color: #fff
    }

    .pending {
      background: var(--amber)
    }

    .approved {
      background: var(--green)
    }

    .rejected {
      background: var(--red)
    }

    .incomplete {
      background: #aaa
    }

    .btn {
      padding: 6px 10px;
      border: none;
      border-radius: 6px;
      font-size: 12px;
      color: #fff;
      cursor: pointer;
      box-shadow: var(--shadow)
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

    .pagination {
      text-align: center;
      margin-top: 18px;
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 12px
    }

    .pagination a,
    .pagination span.page-info {
      padding: 8px 16px;
      border-radius: 8px;
      background: #e0d7ce;
      color: #333;
      text-decoration: none;
      font-size: 14px;
      font-weight: 600
    }

    .pagination a:hover {
      background: #d4c8bc
    }

    .pagination a.disabled {
      opacity: 0.5;
      pointer-events: none;
      background: #eee
    }

    #kycModal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .62);
      align-items: center;
      justify-content: center;
      z-index: 200
    }

    #kycModal.show {
      display: flex
    }

    .modal-box {
      background: #fff;
      padding: 24px 26px 18px 26px;
      border-radius: var(--radius);
      max-width: 95vw;
      box-shadow: 0 6px 32px #0002;
      position: relative;
      animation: zoomIn .25s
    }

    @keyframes zoomIn {
      from {
        transform: scale(.85)
      }

      to {
        transform: scale(1)
      }
    }

    .close {
      position: absolute;
      top: 8px;
      right: 12px;
      font-size: 26px;
      color: #666;
      cursor: pointer
    }

    .modal-title {
      font-size: 1.15em;
      font-weight: 700;
      margin-bottom: 14px;
      color: var(--brown);
      text-align: center;
    }

    .modal-fields label {
      display: block;
      color: #777;
      font-weight: 600;
      margin-top: 9px;
      font-size: 13px;
    }

    .modal-fields div {
      margin-bottom: 7px;
      font-size: 15px;
      color: #222;
    }

    @media(max-width:650px) {
      body {
        padding: 18px 12px
      }

      .top-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 14px
      }

      .top-bar h1 {
        position: static;
        transform: none
      }

      .top-bar .search {
        width: 100%;
        text-align: left
      }

      table {
        min-width: 0;
        font-size: 13px
      }

      th,
      td {
        padding: 8px 6px
      }
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

  <!-- Top Bar -->
  <div class="top-bar">
    <a href="admin_dashboard.php" class="top-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
    <h1>Bank KYC Requests</h1>
    <div class="search"><input id="searchBox" type="text" placeholder="Search current page…"></div>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <table id="reqTable">
      <thead>
        <tr>
          <th data-type="num">Sr&nbsp;No.</th>
          <th data-type="text">User&nbsp;ID</th>
          <th data-type="text">Bank Name</th>
          <th data-type="text">Status</th>
          <th data-type="text">Date/Time</th>
          <th data-type="text">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php $sr = $offset;
        if ($rows && $rows->num_rows):
          while ($r = $rows->fetch_assoc()):
            $c = strtolower($r['status']);
            $rowData = [
              'bank_name' => $r['bank_name'],
              'account_name' => $r['account_name'],
              'account_number' => $r['account_number'],
              'ifsc' => $r['ifsc'],
              'branch' => $r['branch']
            ];
            ?>
            <tr data-modal='<?= htmlspecialchars(json_encode($rowData)) ?>'>
              <td><?= ++$sr ?></td>
              <td><?= htmlspecialchars($r['user_id']) ?></td>
              <td><?= htmlspecialchars($r['bank_name']) ?></td>
              <td><span class="badge <?= $c ?>"><?= ucfirst($c) ?></span></td>
              <td><?= date('d M Y H:i', strtotime($r['date_time'])) ?></td>
              <td>
                <button class="btn approve" data-id="<?= $r['id'] ?>" data-action="Approved" <?= $c != 'pending' ? 'disabled' : '' ?> title="Approve">
                  <i class="fas fa-check"></i></button>
                <button class="btn reject" data-id="<?= $r['id'] ?>" data-action="Rejected" <?= $c != 'pending' ? 'disabled' : '' ?> title="Reject">
                  <i class="fas fa-times"></i></button>
              </td>
            </tr>
          <?php endwhile; else: ?>
          <tr>
            <td colspan="6">No data found</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination (Next / Prev only) -->
  <?php if ($pages > 1): ?>
    <div class="pagination">
      <a href="?page=<?= max(1, $page - 1) ?>" class="<?= ($page <= 1) ? 'disabled' : '' ?>">&laquo; Previous</a>
      <span class="page-info">Page <?= $page ?> of <?= $pages ?></span>
      <a href="?page=<?= min($pages, $page + 1) ?>" class="<?= ($page >= $pages) ? 'disabled' : '' ?>">Next &raquo;</a>
    </div>
  <?php endif; ?>

  <!-- MODAL for Bank KYC -->
  <div id="kycModal">
    <div class="modal-box">
      <span class="close" onclick="modal(false)">&times;</span>
      <div class="modal-title">Bank KYC Details</div>
      <div class="modal-fields">
        <label>Bank Name</label>
        <div id="modalBankName"></div>
        <label>Account Name</label>
        <div id="modalAccName"></div>
        <label>Account Number</label>
        <div id="modalAccNum"></div>
        <label>IFSC</label>
        <div id="modalIfsc"></div>
        <label>Branch</label>
        <div id="modalBranch"></div>
      </div>
    </div>
  </div>

  <script>
    // Row click: show modal
    document.querySelectorAll('#reqTable tbody tr').forEach(function(tr) {
      tr.onclick = function (e) {
        if (e.target.classList.contains('btn')) return; // Don't open modal on button click!
        var d = JSON.parse(this.dataset.modal);
        document.getElementById('modalBankName').textContent = d.bank_name || '-';
        document.getElementById('modalAccName').textContent = d.account_name || '-';
        document.getElementById('modalAccNum').textContent = d.account_number || '-';
        document.getElementById('modalIfsc').textContent = d.ifsc || '-';
        document.getElementById('modalBranch').textContent = d.branch || '-';
        modal(true);
      };
    });
    function modal(s) { document.getElementById('kycModal').classList.toggle('show', !!s); }
    document.getElementById('kycModal').onclick = function (e) {
      if (e.target.id === 'kycModal') modal(false);
    };

    // AJAX status approve/reject
    document.querySelectorAll('.approve,.reject').forEach(btn => btn.onclick = function (e) {
      e.stopPropagation();
      if (btn.disabled) return;
      fetch('user_bank_kyc.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ ajax: 1, id: btn.dataset.id, newStatus: btn.dataset.action })
      })
        .then(r => r.json()).then(function(j) {
          if (j.ok) {
            var row = btn.closest('tr'); row.classList.add('fade-update');
            var badge = row.querySelector('.badge'); badge.textContent = j.status; badge.className = 'badge ' + j.status.toLowerCase();
            row.querySelectorAll('button').forEach(b => b.disabled = true);
          }
        });
    });

    // live search
    var rows = [...document.querySelectorAll('#reqTable tbody tr')];
    document.getElementById('searchBox').oninput = function(e) {
      var q = e.target.value.toLowerCase(); rows.forEach(r => r.style.display = r.innerText.toLowerCase().includes(q) ? '' : 'none');
    };

    // sortable columns
    document.querySelectorAll('#reqTable th').forEach(function(th, i) {
      th.addEventListener('click', function() {
        var asc = !th.classList.contains('sorted-asc');
        document.querySelectorAll('#reqTable th').forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
        th.classList.add(asc ? 'sorted-asc' : 'sorted-desc');
        var tb = th.closest('table').querySelector('tbody');
        var type = th.dataset.type;
        var rowsArr = [...tb.querySelectorAll('tr')];
        rowsArr.sort(function(a, b) {
          var A = a.children[i].innerText.trim(), B = b.children[i].innerText.trim();
          if (type === 'num') { A = parseFloat(A.replace(/[^0-9.-]+/g, '')); B = parseFloat(B.replace(/[^0-9.-]+/g, '')); }
          return (A > B ? 1 : A < B ? -1 : 0) * (asc ? 1 : -1);
        });
        rowsArr.forEach(r => tb.appendChild(r));
      });
    });
  </script>
</body>

</html>
<?php $db->close(); 
