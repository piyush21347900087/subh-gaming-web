<?php
require_once 'includes/db.php';
// user_upi_kyc.php – Admin UPI KYC requests (full view, approve/reject, modal for QR)
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

$db = db();
if ($db->connect_error)
  die('DB error: ' . $db->connect_error);

// AJAX Save Cropped Image
if (isset($_POST['action']) && $_POST['action'] === 'save_cropped_image' && isset($_POST['id'], $_POST['image'])) {
  header('Content-Type: application/json');
  try {
      $id = intval($_POST['id']);
      $base64Data = $_POST['image'];
      
      $stmtPath = $db->prepare("SELECT qr_image FROM upi_kyc WHERE id = ?");
      $stmtPath->bind_param("i", $id);
      $stmtPath->execute();
      $stmtPath->bind_result($qrImage);
      $stmtPath->fetch();
      $stmtPath->close();
      
      if (empty($qrImage)) {
          throw new Exception("Record or QR Image path not found.");
      }
      
      $filePath = __DIR__ . '/' . ltrim($qrImage, '/');
      
      // Backup original if not already backed up
      $backupPath = preg_replace('/(\.[a-zA-Z0-9]+)$/', '_org$1', $filePath);
      if (file_exists($filePath) && !file_exists($backupPath)) {
          copy($filePath, $backupPath);
      }
      
      if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
          $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
          $type = strtolower($type[1]);
          if (!in_array($type, ['jpg', 'jpeg', 'png', 'webp'], true)) {
              throw new Exception("Invalid image type.");
          }
          $base64Data = base64_decode($base64Data);
          if ($base64Data === false) {
              throw new Exception("Base64 decode failed.");
          }
      } else {
          throw new Exception("Invalid image data format.");
      }
      
      if (file_put_contents($filePath, $base64Data) === false) {
          throw new Exception("Failed to write file to disk. Check folder permissions.");
      }
      
      echo json_encode(['ok' => true, 'msg' => 'Image cropped successfully!']);
  } catch (Exception $e) {
      echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
}

// AJAX Restore Original Image
if (isset($_POST['action']) && $_POST['action'] === 'restore_original_image' && isset($_POST['id'])) {
  header('Content-Type: application/json');
  try {
      $id = intval($_POST['id']);
      
      $stmtPath = $db->prepare("SELECT qr_image FROM upi_kyc WHERE id = ?");
      $stmtPath->bind_param("i", $id);
      $stmtPath->execute();
      $stmtPath->bind_result($qrImage);
      $stmtPath->fetch();
      $stmtPath->close();
      
      if (empty($qrImage)) {
          throw new Exception("Record or QR Image path not found.");
      }
      
      $filePath = __DIR__ . '/' . ltrim($qrImage, '/');
      $backupPath = preg_replace('/(\.[a-zA-Z0-9]+)$/', '_org$1', $filePath);
      
      if (!file_exists($backupPath)) {
          throw new Exception("Original backup not found.");
      }
      
      if (!copy($backupPath, $filePath)) {
          throw new Exception("Failed to restore original image from backup.");
      }
      
      echo json_encode(['ok' => true, 'msg' => 'Original image restored successfully!']);
  } catch (Exception $e) {
      echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
}

// AJAX Approve/Reject
if (isset($_POST['ajax'], $_POST['id'], $_POST['newStatus'])) {
  require_once 'includes/fcm_helper.php';
  $id = intval($_POST['id']);
  $st = ($_POST['newStatus'] === 'Approved') ? 'Approved' : 'Rejected';
  
  // Fetch user_id for notification
  $userId = '';
  $stmtUser = $db->prepare("SELECT user_id FROM upi_kyc WHERE id = ?");
  $stmtUser->bind_param("i", $id);
  $stmtUser->execute();
  $stmtUser->bind_result($userId);
  $stmtUser->fetch();
  $stmtUser->close();

  $q = $db->prepare("UPDATE upi_kyc SET status=? WHERE id=?");
  $q->bind_param('si', $st, $id);
  $ok = $q->execute();
  $q->close();

  if ($ok && !empty($userId)) {
      // Trigger user-side reload via FCM
      pushWalletFcm($db, $userId, 'reload', 0.0, $st, 'UPI KYC ' . $st, 'Your UPI KYC status has been updated to ' . $st);
  }

  echo json_encode(['ok' => $ok, 'status' => $st]);
  exit;
}

// Pagination
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$total = $db->query("SELECT COUNT(*) FROM upi_kyc")->fetch_row()[0];
$pages = max(1, ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;
$rows = $db->query("
    SELECT id, user_id, upi_id, qr_image, status, date_time
    FROM upi_kyc
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
  <title>UPI KYC – Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Cropper.js -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
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
      min-width: 740px;
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

    .modal-fields img {
      display: block;
      margin: 12px auto 0 auto;
      max-width: 160px;
      border-radius: 10px;
      box-shadow: 0 2px 14px #0001;
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
    <h1>UPI KYC Requests</h1>
    <div class="search"><input id="searchBox" type="text" placeholder="Search current page…"></div>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <table id="reqTable">
      <thead>
        <tr>
          <th data-type="num">Sr&nbsp;No.</th>
          <th data-type="text">User&nbsp;ID</th>
          <th data-type="text">UPI&nbsp;ID</th>
          <th data-type="text">QR&nbsp;Image</th>
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
            $hasBackup = 0;
            if ($r['qr_image']) {
              $filePath = __DIR__ . '/' . ltrim($r['qr_image'], '/');
              $backupPath = preg_replace('/(\.[a-zA-Z0-9]+)$/', '_org$1', $filePath);
              if (file_exists($backupPath)) {
                $hasBackup = 1;
              }
            }
            $rowData = [
              'upi_id' => $r['upi_id'],
              'qr_image' => $r['qr_image'],
              'has_backup' => $hasBackup
            ];
            ?>
            <tr data-modal='<?= htmlspecialchars(json_encode($rowData)) ?>'>
              <td><?= ++$sr ?></td>
              <td><?= htmlspecialchars($r['user_id']) ?></td>
              <td><?= htmlspecialchars($r['upi_id']) ?></td>
              <td><?php if ($r['qr_image']): ?>
                  <a href="#" class="view-qr-link" data-id="<?= $r['id'] ?>" data-qr="<?= htmlspecialchars($r['qr_image']) ?>" data-backup="<?= $hasBackup ?>"
                    style="color:var(--blue);text-decoration:underline;font-weight:600;">View Image</a>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
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
            <td colspan="7">No data found</td>
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

  <!-- MODAL for UPI KYC -->
  <div id="kycModal">
    <div class="modal-box">
      <span class="close" onclick="modal(false)">&times;</span>
      <div class="modal-title">UPI KYC Details</div>
      <div class="modal-fields">
        <label>UPI ID</label>
        <div id="modalUpiId"></div>
        <label>QR Image</label>
        <div id="modalQrImg"></div>
      </div>
    </div>
  </div>

  <!-- Cropping Modal -->
  <div id="cropModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); align-items:center; justify-content:center; z-index:300;">
    <div style="background:#1c1427; color:#fff; padding:20px; border-radius:12px; max-width:90vw; width:450px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.5); border:1px solid rgba(255,255,255,0.1); position:relative;">
      <span style="position:absolute; top:8px; right:12px; font-size:26px; cursor:pointer; color:#ccc;" onclick="closeCropModal()">&times;</span>
      <div style="font-size:18px; font-weight:600; color:var(--amber); margin-bottom:14px; text-align:center;">Crop QR Image</div>
      <div style="max-height:50vh; overflow:hidden; margin-bottom:15px; background:#000; border-radius:8px; display:flex; align-items:center; justify-content:center;">
        <img id="cropImageSource" src="" style="max-width:100%; max-height:50vh; display:block;">
      </div>
      <!-- Controls -->
      <div style="display:flex; justify-content:center; gap:10px; margin-bottom:15px; flex-wrap:wrap;">
        <button type="button" class="btn" style="background:#4e342e; padding:8px 14px; border-radius:6px; font-weight:600;" onclick="cropper.rotate(-90)" title="Rotate Left"><i class="fas fa-undo"></i> Rotate Left</button>
        <button type="button" class="btn" style="background:#4e342e; padding:8px 14px; border-radius:6px; font-weight:600;" onclick="cropper.rotate(90)" title="Rotate Right"><i class="fas fa-redo"></i> Rotate Right</button>
        <button type="button" class="btn" style="background:#777; padding:8px 14px; border-radius:6px; font-weight:600;" onclick="cropper.reset()" title="Reset"><i class="fas fa-sync-alt"></i> Reset</button>
      </div>
      <!-- Restore Original Button -->
      <div style="margin-bottom:15px;">
        <button type="button" id="restoreOrgBtn" class="btn" style="background:#0277bd; padding:8px 14px; border-radius:6px; font-weight:600; width:100%; display:none;">
          <i class="fas fa-history"></i> Restore Original Image (Recover)
        </button>
      </div>
      <div style="display:flex; justify-content:space-between; gap:10px;">
        <button type="button" class="btn" style="background:#777; flex:1; padding:10px;" onclick="closeCropModal()">Cancel</button>
        <button type="button" id="saveCropBtn" class="btn approve" style="flex:1; padding:10px; font-weight:600;"><i class="fas fa-save"></i> Crop & Save</button>
      </div>
    </div>
  </div>

  <script>
// Row click: show modal
document.querySelectorAll('#reqTable tbody tr').forEach(function (tr) {
  tr.onclick = function (e) {
    if (e.target.classList.contains('btn') || e.target.tagName === 'A' || e.target.closest('.view-qr-link')) return; // Don't open modal on button or link click!
    var d = JSON.parse(this.dataset.modal);
    document.getElementById('modalUpiId').textContent = d.upi_id || '-';
    var imgDiv = document.getElementById('modalQrImg');
    imgDiv.innerHTML = d.qr_image ? "<img src=\"".concat(d.qr_image.split('?')[0], "?t=").concat(new Date().getTime(), "\" alt=\"QR Code\">") : '-';
    modal(true);
  };
});
function modal(s) {document.getElementById('kycModal').classList.toggle('show', !!s);}
document.getElementById('kycModal').onclick = function (e) {
  if (e.target.id === 'kycModal') modal(false);
};

// AJAX status approve/reject
document.querySelectorAll('.approve,.reject').forEach((btn) => btn.onclick = function (e) {
  e.stopPropagation();
  if (btn.disabled) return;
  fetch('user_upi_kyc.php', {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ ajax: 1, id: btn.dataset.id, newStatus: btn.dataset.action })
  }).
  then((r) => r.json()).then(function (j) {
    if (j.ok) {
      var row = btn.closest('tr');row.classList.add('fade-update');
      var badge = row.querySelector('.badge');badge.textContent = j.status;badge.className = 'badge ' + j.status.toLowerCase();
      row.querySelectorAll('button').forEach((b) => b.disabled = true);
    }
  });
});

// live search
var rows = [...document.querySelectorAll('#reqTable tbody tr')];
document.getElementById('searchBox').oninput = function (e) {
  var q = e.target.value.toLowerCase();rows.forEach((r) => r.style.display = r.innerText.toLowerCase().includes(q) ? '' : 'none');
};

// sortable columns
document.querySelectorAll('#reqTable th').forEach(function (th, i) {
  th.addEventListener('click', function () {
    var asc = !th.classList.contains('sorted-asc');
    document.querySelectorAll('#reqTable th').forEach((h) => h.classList.remove('sorted-asc', 'sorted-desc'));
    th.classList.add(asc ? 'sorted-asc' : 'sorted-desc');
    var tb = th.closest('table').querySelector('tbody');
    var type = th.dataset.type;
    var rowsArr = [...tb.querySelectorAll('tr')];
    rowsArr.sort(function (a, b) {
      var A = a.children[i].innerText.trim(),B = b.children[i].innerText.trim();
      if (type === 'num') {A = parseFloat(A.replace(/[^0-9.-]+/g, ''));B = parseFloat(B.replace(/[^0-9.-]+/g, ''));}
      return (A > B ? 1 : A < B ? -1 : 0) * (asc ? 1 : -1);
    });
    rowsArr.forEach((r) => tb.appendChild(r));
  });
});

// Cropper.js Controller
var cropper = null;
var activeCropId = null;

function openCropModal(id, imageUrl, hasBackup) {
  activeCropId = id;
  var cropModal = document.getElementById('cropModal');
  var cropImageSource = document.getElementById('cropImageSource');

  var restoreBtn = document.getElementById('restoreOrgBtn');
  if (hasBackup == 1 || hasBackup === '1') {
    restoreBtn.style.display = 'block';
  } else {
    restoreBtn.style.display = 'none';
  }

  // Prevent browser cache
  cropImageSource.src = imageUrl + '?t=' + new Date().getTime();
  cropModal.style.display = 'flex';

  if (cropper) {
    cropper.destroy();
  }

  setTimeout(function () {
    cropper = new Cropper(cropImageSource, {
      viewMode: 1,
      autoCropArea: 0.9,
      background: false,
      responsive: true,
      checkOrientation: false
    });
  }, 100);
}

function closeCropModal() {
  document.getElementById('cropModal').style.display = 'none';
  if (cropper) {
    cropper.destroy();
    cropper = null;
  }
  activeCropId = null;
}

document.addEventListener('click', function (e) {
  var link = e.target.closest('.view-qr-link');
  if (link) {
    e.preventDefault();
    e.stopPropagation();
    var id = link.dataset.id;
    var qrUrl = link.dataset.qr;
    var hasBackup = link.dataset.backup;
    openCropModal(id, qrUrl, hasBackup);
  }
});

document.getElementById('saveCropBtn').onclick = function () {
  if (!cropper || !activeCropId) return;

  var btn = this;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  var canvas = cropper.getCroppedCanvas({
    maxWidth: 1024,
    maxHeight: 1024
  });

  if (!canvas) {
    alert('Could not crop image.');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Crop & Save';
    return;
  }

  var croppedBase64 = canvas.toDataURL('image/png');

  fetch('user_upi_kyc.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action: 'save_cropped_image',
      id: activeCropId,
      image: croppedBase64
    })
  }).
  then((r) => r.json()).
  then(function (res) {
    if (res.ok) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Success!',
          text: 'QR Image cropped and saved successfully!',
          icon: 'success',
          confirmButtonColor: '#ffb300'
        });
      } else {
        alert('QR Image cropped and saved successfully!');
      }

      var rowLink = document.querySelector(".view-qr-link[data-id=\"".concat(activeCropId, "\"]"));
      if (rowLink) {
        var newUrl = rowLink.dataset.qr.split('?')[0] + '?t=' + new Date().getTime();
        rowLink.dataset.qr = newUrl;
        rowLink.dataset.backup = "1";

        // Sync with row click details modal data
        var tr = rowLink.closest('tr');
        if (tr) {
          var modalData = JSON.parse(tr.dataset.modal);
          modalData.qr_image = newUrl;
          modalData.has_backup = 1;
          tr.dataset.modal = JSON.stringify(modalData);
        }
      }

      closeCropModal();
    } else {
      alert('Error: ' + (res.error || 'Failed to save cropped image.'));
    }
  }).
  catch(function () {return alert('Network error while saving cropped image.');}).
  finally(function () {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Crop & Save';
  });
};

// Restore original QR Image handler
document.getElementById('restoreOrgBtn').onclick = function () {
  if (!activeCropId) return;

  var btn = this;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Restoring...';

  fetch('user_upi_kyc.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action: 'restore_original_image',
      id: activeCropId
    })
  }).
  then((r) => r.json()).
  then(function (res) {
    if (res.ok) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Restored!',
          text: 'Original image has been recovered successfully!',
          icon: 'success',
          confirmButtonColor: '#ffb300'
        });
      } else {
        alert('Original image has been recovered successfully!');
      }

      var rowLink = document.querySelector(".view-qr-link[data-id=\"".concat(activeCropId, "\"]"));
      if (rowLink) {
        rowLink.dataset.backup = "0";
        var restoredUrl = rowLink.dataset.qr.split('?')[0] + '?t=' + new Date().getTime();
        rowLink.dataset.qr = restoredUrl;

        // Sync with row click details modal data
        var tr = rowLink.closest('tr');
        if (tr) {
          var modalData = JSON.parse(tr.dataset.modal);
          modalData.qr_image = restoredUrl;
          modalData.has_backup = 0;
          tr.dataset.modal = JSON.stringify(modalData);
        }
      }

      closeCropModal();
    } else {
      alert('Error: ' + (res.error || 'Failed to restore original image.'));
    }
  }).
  catch(function () {return alert('Network error while restoring original image.');}).
  finally(function () {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-history"></i> Restore Original Image (Recover)';
  });
};</script>
</body>

</html>
<?php $db->close(); 
