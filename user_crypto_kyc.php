<?php
require_once 'includes/db.php';
// user_crypto_kyc.php – Admin Crypto (USDT) KYC requests (view + approve/reject + modal)
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

$db = db();
if ($db->connect_error) {
  die('DB error: ' . $db->connect_error);
}
$db->set_charset('utf8mb4');
$db->query("SET time_zone = '+05:30'");

/* ========== AJAX Approve / Reject ========== */
if (isset($_POST['ajax'], $_POST['id'], $_POST['newStatus'])) {
  require_once 'includes/fcm_helper.php';
  $id = (int) $_POST['id'];
  $st = ($_POST['newStatus'] === 'Approved') ? 'Approved' : 'Rejected';

  // Fetch user_id for notification
  $userId = '';
  $stmtUser = $db->prepare("SELECT user_id FROM crypto_kyc WHERE id = ?");
  $stmtUser->bind_param("i", $id);
  $stmtUser->execute();
  $stmtUser->bind_result($userId);
  $stmtUser->fetch();
  $stmtUser->close();

  $q = $db->prepare("UPDATE crypto_kyc SET status=? WHERE id=?");
  $q->bind_param('si', $st, $id);
  $ok = $q->execute();
  $q->close();

  if ($ok && !empty($userId)) {
      // Trigger user-side reload via FCM
      pushWalletFcm($db, $userId, 'reload', 0.0, $st, 'Crypto KYC ' . $st, 'Your Crypto KYC status has been updated to ' . $st);
  }

  echo json_encode(['ok' => $ok, 'status' => $st]);
  exit;
}

/* ========== Pagination ========== */
$limit = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$totalQ = $db->query("SELECT COUNT(*) FROM crypto_kyc");
$total = $totalQ ? (int) $totalQ->fetch_row()[0] : 0;
$pages = max(1, (int) ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;

$sql = "
    SELECT id, user_id, network, address, qr_image, status, date_time
    FROM crypto_kyc
    ORDER BY status='Pending' DESC, id DESC
    LIMIT $limit OFFSET $offset
";
$rows = $db->query($sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Crypto KYC – Admin</title>
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
      color: inherit;
      text-decoration: none
    }

    /* Top bar */
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
      font-size: 14px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .top-bar h1 {
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
      font-size: 20px;
      font-weight: 600;
      color: var(--brown);
      white-space: nowrap;
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

    /* Table */
    .table-wrap {
      overflow-x: auto
    }

    table {
      width: 100%;
      min-width: 780px;
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
      cursor: pointer;
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

    /* Status badge */
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

    /* Buttons */
    .btn {
      padding: 6px 10px;
      border: none;
      border-radius: 6px;
      font-size: 12px;
      color: #fff;
      cursor: pointer;
      box-shadow: var(--shadow);
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

    /* Pagination (Next / Prev only) */
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

    /* Modal */
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
      word-break: break-all;
    }

    .modal-fields img {
      display: block;
      margin: 12px auto 0 auto;
      max-width: 200px;
      border-radius: 10px;
      box-shadow: 0 2px 14px #0001;
    }

    /* Responsive */
    @media(max-width:650px) {
      body {
        padding: 18px 12px
      }

      .top-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 14px;
      }

      .top-bar h1 {
        position: static;
        transform: none;
        margin: 0 auto;
        text-align: center;
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
    <a href="admin_dashboard.php" class="top-btn">
      <i class="fas fa-arrow-left"></i> Dashboard
    </a>
    <h1>Crypto KYC Requests</h1>
    <div class="search">
      <input id="searchBox" type="text" placeholder="Search current page…">
    </div>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <table id="reqTable">
      <thead>
        <tr>
          <th data-type="num">Sr&nbsp;No.</th>
          <th data-type="text">User&nbsp;ID</th>
          <th data-type="text">Network</th>
          <th data-type="text">Address</th>
          <th data-type="text">QR&nbsp;Image</th>
          <th data-type="text">Status</th>
          <th data-type="text">Date/Time</th>
          <th data-type="text">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sr = $offset;
        if ($rows && $rows->num_rows):
          while ($r = $rows->fetch_assoc()):
            $c = strtolower($r['status']);
            $rowData = [
              'network' => $r['network'],
              'address' => $r['address'],
              'qr_image' => $r['qr_image']
            ];
            ?>
            <tr data-modal='<?= htmlspecialchars(json_encode($rowData), ENT_QUOTES, 'UTF-8') ?>'>
              <td><?= ++$sr ?></td>
              <td><?= htmlspecialchars($r['user_id']) ?></td>
              <td><?= htmlspecialchars($r['network']) ?></td>
              <td><?= htmlspecialchars($r['address']) ?></td>
              <td><?php if ($r['qr_image']): ?>
                  <a href="<?= htmlspecialchars($r['qr_image']) ?>" target="_blank"
                    style="color:var(--blue);text-decoration:underline;font-weight:600;">View Image</a>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
              <td><span class="badge <?= $c ?>"><?= ucfirst($c) ?></span></td>
              <td><?= date('d M Y H:i', strtotime($r['date_time'])) ?></td>
              <td>
                <button class="btn approve" data-id="<?= $r['id'] ?>" data-action="Approved" <?= $c != 'pending' ? 'disabled' : '' ?> title="Approve">
                  <i class="fas fa-check"></i>
                </button>
                <button class="btn reject" data-id="<?= $r['id'] ?>" data-action="Rejected" <?= $c != 'pending' ? 'disabled' : '' ?> title="Reject">
                  <i class="fas fa-times"></i>
                </button>
              </td>
            </tr>
            <?php
          endwhile;
        else: ?>
          <tr>
            <td colspan="8">No data found</td>
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

  <!-- MODAL for Crypto KYC -->
  <div id="kycModal">
    <div class="modal-box">
      <span class="close" onclick="modal(false)">&times;</span>
      <div class="modal-title">Crypto KYC Details</div>
      <div class="modal-fields">
        <label>Network</label>
        <div id="modalNetwork"></div>

        <label>Address</label>
        <div id="modalAddress"></div>

        <label>QR Image</label>
        <div id="modalQrImg"></div>
      </div>
    </div>
  </div>

  <script>
// Row click -> open modal (except when clicking buttons or links)
document.querySelectorAll('#reqTable tbody tr').forEach(function (tr) {
  tr.addEventListener('click', function (e) {
    if (e.target.classList.contains('btn') || e.target.closest('button') || e.target.tagName === 'A') return;
    var d = JSON.parse(this.dataset.modal || '{}');
    document.getElementById('modalNetwork').textContent = d.network ? d.network + ' Network' : '-';
    document.getElementById('modalAddress').textContent = d.address || '-';

    var imgDiv = document.getElementById('modalQrImg');
    if (d.qr_image) {
      imgDiv.innerHTML = "<img src=\"".concat(d.qr_image, "\" alt=\"Crypto QR\">");
    } else {
      imgDiv.innerHTML = '-';
    }
    modal(true);
  });
});

function modal(show) {
  document.getElementById('kycModal').classList.toggle('show', !!show);
}
document.getElementById('kycModal').addEventListener('click', function (e) {
  if (e.target.id === 'kycModal') modal(false);
});

// AJAX Approve/Reject
document.querySelectorAll('.approve,.reject').forEach(function (btn) {
  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    if (btn.disabled) return;

    fetch('user_crypto_kyc.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        ajax: 1,
        id: btn.dataset.id,
        newStatus: btn.dataset.action
      })
    }).
    then((r) => r.json()).
    then(function (j) {
      if (j.ok) {
        var row = btn.closest('tr');
        var badge = row.querySelector('.badge');
        row.classList.add('fade-update');
        badge.textContent = j.status;
        badge.className = 'badge ' + j.status.toLowerCase();
        row.querySelectorAll('button').forEach((b) => b.disabled = true);
      }
    }).
    catch(console.error);
  });
});

// Live search (current page only)
var allRows = Array.from(document.querySelectorAll('#reqTable tbody tr'));
document.getElementById('searchBox').addEventListener('input', function (e) {
  var q = e.target.value.toLowerCase();
  allRows.forEach(function (r) {
    r.style.display = r.innerText.toLowerCase().includes(q) ? '' : 'none';
  });
});

// Sortable columns
document.querySelectorAll('#reqTable th').forEach(function (th, idx) {
  th.addEventListener('click', function () {
    var asc = !th.classList.contains('sorted-asc');
    document.querySelectorAll('#reqTable th').forEach((h) => h.classList.remove('sorted-asc', 'sorted-desc'));
    th.classList.add(asc ? 'sorted-asc' : 'sorted-desc');

    var tb = th.closest('table').querySelector('tbody');
    var type = th.dataset.type;
    var rowsArr = Array.from(tb.querySelectorAll('tr'));

    rowsArr.sort(function (a, b) {
      var A = a.children[idx].innerText.trim();
      var B = b.children[idx].innerText.trim();
      if (type === 'num') {
        A = parseFloat(A.replace(/[^0-9.-]+/g, '')) || 0;
        B = parseFloat(B.replace(/[^0-9.-]+/g, '')) || 0;
      }
      if (A > B) return asc ? 1 : -1;
      if (A < B) return asc ? -1 : 1;
      return 0;
    });

    rowsArr.forEach((r) => tb.appendChild(r));
  });
});</script>
</body>

</html>
<?php
$db->close();
