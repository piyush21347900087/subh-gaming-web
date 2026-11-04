<?php
require_once 'includes/db.php';
// create_admin.php – Show all admins + Create Admin (modal, MASTER only)
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
/* ---- Must be logged-in admin ---- */
if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

/* ---- Only MASTER role allowed ---- */
$sessionRole = strtolower($_SESSION['admin_role'] ?? '');
if ($sessionRole !== 'master') {
  die("<p style='color:red;font-family:Arial,sans-serif;margin:20px;'>
        ⛔ Sirf <strong>MASTER</strong> admin ko yeh page access hai.
    </p>");
}

$loginAdminName = $_SESSION['admin'] ?? 'Admin';
$loginAdminRole = $_SESSION['admin_role'] ?? 'master';

/* ---- PHP MySQL error reporting ---- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ===== DATABASE – SUBH GAMING ===== */
try {
  $db = db();
} catch (Exception $e) {
  die("<p style='color:red;font-family:Arial,sans-serif;margin:20px;'>
        DB Error: " . htmlspecialchars($e->getMessage()) . "
    </p>");
}

/* ===== Check admin table ===== */
$res = $db->query("SHOW TABLES LIKE 'admin'");
if (!$res || $res->num_rows === 0) {
  die("<p style='color:red;font-family:Arial,sans-serif;margin:20px;'>
        ❌ Table <strong>admin</strong> nahi mila. Pehle <strong>admin.sql</strong> import karo.
    </p>");
}

/* ===== AJAX Password Change Handler ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  header('Content-Type: application/json');
  $adminId = (int) ($_POST['admin_id'] ?? 0);
  $newPassword = trim($_POST['new_password'] ?? '');

  if ($adminId <= 0 || $newPassword === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
  }

  try {
    $stmt = $db->prepare("UPDATE admin SET password = ? WHERE id = ?");
    $stmt->bind_param('si', $newPassword, $adminId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
      echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
    } else {
      echo json_encode(['success' => false, 'message' => 'No changes made']);
    }
    $stmt->close();
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
  }
  exit;
}

/* ===== AJAX Delete Handler ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
  header('Content-Type: application/json');
  $adminId = (int) ($_POST['admin_id'] ?? 0);

  if ($adminId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
  }
  try {
    // Check if this is the last admin
    $countRes = $db->query("SELECT COUNT(*) as cnt FROM admin");
    $countRow = $countRes->fetch_assoc();
    if ($countRow['cnt'] <= 1) {
      echo json_encode(['success' => false, 'message' => 'Cannot delete! At least one admin must remain in the system.']);
      exit;
    }

    $stmt = $db->prepare("DELETE FROM admin WHERE id = ?");
    $stmt->bind_param('i', $adminId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
      echo json_encode(['success' => true, 'message' => 'Admin deleted successfully']);
    } else {
      echo json_encode(['success' => false, 'message' => 'Admin not found or already deleted']);
    }
    $stmt->close();
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
  }
  exit;
}

/* ===== Form submit handling (Create Admin) ===== */
$msgError = '';
$msgSuccess = '';

$old_name = '';
$old_email = '';
$old_pass = '';
$old_role = 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $role = $_POST['role'] ?? 'admin';

  // repopulate
  $old_name = $name;
  $old_email = $email;
  $old_pass = $password;
  $old_role = $role;

  if ($name === '' || $email === '' || $password === '') {
    $msgError = 'Please fill all fields.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $msgError = 'Invalid e-mail format.';
  } else {
    try {
      // Email duplicate check
      $stmt = $db->prepare("SELECT id FROM admin WHERE email = ? LIMIT 1");
      $stmt->bind_param('s', $email);
      $stmt->execute();
      $stmt->store_result();

      if ($stmt->num_rows > 0) {
        $msgError = 'This e-mail already exists.';
      } else {
        // Next admin_id (ADM0001 pattern)
        $nextNum = 1;
        $resLast = $db->query("SELECT admin_id FROM admin ORDER BY id DESC LIMIT 1");
        if ($resLast && $rowLast = $resLast->fetch_assoc()) {
          if (preg_match('/(\d+)/', $rowLast['admin_id'], $m)) {
            $nextNum = (int) $m[1] + 1;
          }
        }
        $admin_id = 'ADM' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        // Role sanitize
        $role = (strtolower($role) === 'master') ? 'master' : 'admin';

        // Insert new admin
        $ins = $db->prepare("
                    INSERT INTO admin (admin_id, name, email, password, role)
                    VALUES (?, ?, ?, ?, ?)
                ");
        $ins->bind_param('sssss', $admin_id, $name, $email, $password, $role);
        $ins->execute();

        $msgSuccess = "New admin created: {$admin_id} ({$email})";
        $old_name = $old_email = $old_pass = '';
        $old_role = 'admin';
      }
      $stmt->close();
    } catch (Exception $e) {
      $msgError = 'DB error: ' . $e->getMessage();
    }
  }
}

/* ===== Fetch all admins for table ===== */
$adminsData = [];
try {
  $resAdmins = $db->query("SELECT id, admin_id, name, email, password, role FROM admin ORDER BY id ASC");
  while ($row = $resAdmins->fetch_assoc()) {
    $adminsData[] = $row;
  }
} catch (Exception $e) {
  $msgError = 'DB error (list): ' . $e->getMessage();
}

$db->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <title>Create Admin – SUBH GAMING</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, sans-serif;
    }

    body {
      background: #e5f0f3;
      min-height: 100vh;
      padding: 16px 8px 24px;
      display: flex;
      justify-content: center;
    }

    .wrapper {
      width: 100%;
      max-width: 1200px;
    }

    /* ===== HEADER (bio_data style) ===== */
    .page-header {
      background: #ffffff;
      border-radius: 18px;
      padding: 12px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
      margin-bottom: 18px;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border-radius: 999px;
      border: none;
      background: #5d4037;
      color: #fff;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
    }

    .back-btn i {
      font-size: 13px;
    }

    .header-title {
      font-size: 20px;
      font-weight: 700;
      color: #4e342e;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 12px;
    }

    .role-pill {
      padding: 6px 14px;
      border-radius: 999px;
      font-weight: 600;
      color: #fff;
      background: #2e7d32;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .role-pill span.badge-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #c8e6c9;
      display: inline-block;
    }

    .admin-name {
      font-size: 13px;
      color: #555;
    }

    /* ===== CONTENT CARD ===== */
    .card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      padding: 14px 14px 18px;
    }

    .top-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 10px;
    }

    .subtitle {
      font-size: 13px;
      color: #777;
    }

    .btn-create {
      background: linear-gradient(90deg, #750000, #b21616);
      color: #fff;
      border: none;
      border-radius: 999px;
      padding: 8px 16px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .btn-create i {
      font-size: 13px;
    }

    .btn-create:hover {
      opacity: 0.92;
      transform: translateY(-1px);
    }

    .alert {
      border-radius: 8px;
      padding: 9px 11px;
      margin-bottom: 10px;
      font-size: 13px;
    }

    .alert.error {
      background: #ffebee;
      color: #c62828;
      border: 1px solid #ffcdd2;
    }

    .alert.success {
      background: #e8f5e9;
      color: #2e7d32;
      border: 1px solid #c8e6c9;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      margin-top: 6px;
    }

    th,
    td {
      border: 1px solid #e0e0e0;
      padding: 8px 6px;
      text-align: left;
      white-space: nowrap;
    }

    th {
      background: #fff3e0;
      font-weight: 600;
      color: #5d4037;
      position: sticky;
      top: 0;
      z-index: 1;
    }

    tbody tr:nth-child(even) {
      background: #fafafa;
    }

    .role-chip {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 600;
      color: #fff;
    }

    .role-master {
      background: #2e7d32;
    }

    .role-admin {
      background: #0277bd;
    }

    .table-wrap {
      max-height: 420px;
      overflow: auto;
      border-radius: 10px;
      border: 1px solid #e0e0e0;
      margin-top: 4px;
    }

    /* Modal */
    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.45);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 999;
    }

    .modal {
      width: 100%;
      max-width: 420px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
      padding: 16px 16px 18px;
      animation: pop 0.2s ease-out;
    }

    @keyframes pop {
      from {
        transform: scale(0.95);
        opacity: 0;
      }

      to {
        transform: scale(1);
        opacity: 1;
      }
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }

    .modal-title {
      font-size: 18px;
      font-weight: 700;
      color: #4e342e;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .modal-title i {
      color: #c62828;
    }

    .modal-close {
      border: none;
      background: none;
      font-size: 18px;
      cursor: pointer;
      color: #888;
    }

    .form-group {
      margin-bottom: 9px;
    }

    label {
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 3px;
      display: block;
      color: #5d4037;
    }

    input,
    select {
      width: 100%;
      padding: 9px 9px;
      border-radius: 8px;
      border: 1px solid #ccc;
      font-size: 13px;
      outline: none;
    }

    input:focus,
    select:focus {
      border-color: #c62828;
    }

    .btn-modal {
      width: 100%;
      padding: 11px;
      border: none;
      border-radius: 8px;
      background: linear-gradient(90deg, #750000, #b21616);
      color: #fff;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      margin-top: 5px;
    }

    .btn-modal:hover {
      opacity: 0.93;
      transform: translateY(-1px);
    }

    .btn-change-pass {
      background: linear-gradient(90deg, #0277bd, #01579b);
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 6px 10px;
      font-size: 11px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .btn-change-pass:hover {
      opacity: 0.9;
      transform: translateY(-1px);
    }

    .btn-change-pass i {
      font-size: 10px;
    }

    .btn-delete {
      background: linear-gradient(90deg, #c62828, #e53935);
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 6px 10px;
      font-size: 11px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      margin-left: 4px;
    }

    .btn-delete:hover {
      opacity: 0.9;
      transform: translateY(-1px);
    }

    .btn-delete i {
      font-size: 10px;
    }

    @media(max-width:720px) {
      .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }

      .header-right {
        align-self: flex-end;
      }
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
  <div class="wrapper">

    <!-- ===== NEW HEADER (bio_data style) ===== -->
    <div class="page-header">
      <div class="header-left">
        <a href="admin_dashboard.php" class="back-btn">
          <i class="fas fa-arrow-left"></i> Back
        </a>
        <div class="header-title">Admin Accounts</div>
      </div>
      <div class="header-right">
        <div class="role-pill">
          <span class="badge-dot"></span>
          <?= strtoupper(htmlspecialchars($loginAdminRole)) ?> ADMIN
        </div>
        <div class="admin-name">
          Logged in as: <strong><?= htmlspecialchars($loginAdminName) ?></strong>
        </div>
      </div>
    </div>

    <!-- ===== MAIN CARD ===== -->
    <div class="card">
      <div class="top-row">
        <div class="subtitle">
          Only <strong>MASTER</strong> can manage admins.
        </div>
        <button class="btn-create" type="button" onclick="openModal()">
          <i class="fas fa-plus-circle"></i> Create Admin
        </button>
      </div>

      <?php
      require_once 'includes/db.php';
      if ($msgError): ?>
        <div class="alert error"><?= htmlspecialchars($msgError) ?></div>
        <?php
        require_once 'includes/db.php';
      endif; ?>

      <?php
      require_once 'includes/db.php';
      if ($msgSuccess): ?>
        <div class="alert success"><?= htmlspecialchars($msgSuccess) ?></div>
        <?php
        require_once 'includes/db.php';
      endif; ?>

      <!-- Admin table -->
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:50px;">ID</th>
              <th style="width:90px;">Admin ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Password</th>
              <th style="width:80px;">Role</th>
              <th style="width:120px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            require_once 'includes/db.php';
            if (count($adminsData) === 0): ?>
              <tr>
                <td colspan="7" style="text-align:center;color:#999;">No admins found.</td>
              </tr>
              <?php
              require_once 'includes/db.php';
            else: ?>
              <?php
              require_once 'includes/db.php';
              foreach ($adminsData as $a): ?>
                <tr>
                  <td><?= (int) $a['id'] ?></td>
                  <td><?= htmlspecialchars($a['admin_id']) ?></td>
                  <td><?= htmlspecialchars($a['name']) ?></td>
                  <td><?= htmlspecialchars($a['email']) ?></td>
                  <td><?= htmlspecialchars($a['password']) ?></td>
                  <td>
                    <?php
                    require_once 'includes/db.php';
                    $r = strtolower($a['role']);
                    $class = ($r === 'master') ? 'role-master' : 'role-admin';
                    ?>
                    <span class="role-chip <?= $class ?>"><?= strtoupper($r) ?></span>
                  </td>
                  <td>
                    <button class="btn-change-pass"
                      onclick="changePassword(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name']) ?>')">
                      <i class="fas fa-key"></i> Change
                    </button>
                    <button class="btn-delete"
                      onclick="deleteAdmin(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name']) ?>')">
                      <i class="fas fa-trash"></i> Delete
                    </button>
                  </td>
                </tr>
                <?php
                require_once 'includes/db.php';
              endforeach; ?>
              <?php
              require_once 'includes/db.php';
            endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal-backdrop" id="adminModalBackdrop">
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title">
          <i class="fas fa-user-plus"></i>
          New Admin
        </div>
        <button class="modal-close" type="button" onclick="closeModal()">&times;</button>
      </div>

      <form method="post" autocomplete="off">
        <input type="hidden" name="create_admin" value="1">
        <div class="form-group">
          <label>Admin Name</label>
          <input type="text" name="name" maxlength="50" value="<?= htmlspecialchars($old_name) ?>" required>
        </div>

        <div class="form-group">
          <label>Email (Login ID)</label>
          <input type="email" name="email" maxlength="80" value="<?= htmlspecialchars($old_email) ?>" required>
        </div>

        <div class="form-group">
          <label>Password</label>
          <input type="text" name="password" maxlength="30" value="<?= htmlspecialchars($old_pass) ?>" required>
        </div>

        <div class="form-group">
          <label>Role</label>
          <select name="role">
            <option value="admin" <?= ($old_role === 'admin' ? 'selected' : '') ?>>Admin</option>
            <option value="master" <?= ($old_role === 'master' ? 'selected' : '') ?>>Master</option>
          </select>
        </div>

        <button class="btn-modal" type="submit">
          <i class="fas fa-save"></i> Save Admin
        </button>
      </form>
    </div>
  </div>

  <script>
    function openModal() {
      document.getElementById('adminModalBackdrop').style.display = 'flex';
    }
    function closeModal() {
      document.getElementById('adminModalBackdrop').style.display = 'none';
    }

    // Keep modal open after POST (error / success message dekhne ke liye)
    <?php
    require_once 'includes/db.php';
    if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
      document.addEventListener('DOMContentLoaded', function () {
        openModal();
      });
      <?php
      require_once 'includes/db.php';
    endif; ?>

    // Change Password Function
    function changePassword(adminId, adminName) {
      var newPass = prompt(`Change password for: ${adminName}\n\nEnter new password:`);
      if (!newPass || newPass.trim() === '') {
        alert('Password cannot be empty');
        return;
      }

      var formData = new FormData();
      formData.append('change_password', '1');
      formData.append('admin_id', adminId);
      formData.append('new_password', newPass.trim());

      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(function(data) {
          if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
          } else {
            alert('❌ ' + data.message);
          }
        })
        .catch(function(err) {
          alert('❌ Error: ' + err.message);
        });
    }

    // Delete Admin Function
    function deleteAdmin(adminId, adminName) {
      if (!confirm(`Are you sure you want to DELETE admin: ${adminName}?\n\nThis action cannot be undone.`)) {
        return;
      }

      var formData = new FormData();
      formData.append('delete_admin', '1');
      formData.append('admin_id', adminId);

      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(function(data) {
          if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
          } else {
            alert('❌ ' + data.message);
          }
        })
        .catch(function(err) {
          alert('❌ Error: ' + err.message);
        });
    }
  </script>

</body>

</html>




