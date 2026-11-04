<?php
require_once 'includes/db.php';
// subadmin_manage.php – Create / Manage Sub Admins
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
// Sirf Master Admin (admin session)
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

/* ==== DATABASE ==== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = db();
} catch (Exception $e) {
    die('DB error: ' . $e->getMessage());
}

$popup  = '';
$error  = '';

/* ====== AUTO USERNAME → admin01, admin02, admin03...  ====== */
$suggestedUsername = 'admin01';

try {
    $resSug = $db->query("SELECT COUNT(*) AS cnt FROM staff WHERE role='subadmin'");
    if ($rowSug = $resSug->fetch_assoc()) {

        $num = (int)$rowSug['cnt'] + 1;

        // For <100 → pad with zeros
        if ($num < 100) {
            $numFormatted = str_pad($num, 2, '0', STR_PAD_LEFT);
        } else {
            $numFormatted = (string)$num;
        }

        $suggestedUsername = "admin" . $numFormatted;
    }
} catch (Exception $e) {}

/* ====== CREATE SUB ADMIN ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {

    $name     = trim($_POST['name'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $mobile   = trim($_POST['mobile'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // VALIDATION
    if ($name === '')            $error = 'Full Name required.';
    elseif (!preg_match('/^[A-Za-z ]+$/', $name))
                                 $error = 'Full Name only letters.';
    elseif (strlen($name) > 20)  $error = 'Full Name max 20 chars.';
    elseif ($username === '')    $error = 'Username required.';
    elseif (!preg_match('/^[a-z0-9]{1,20}$/', $username))
                                 $error = 'Username only lowercase & numbers.';
    elseif (!preg_match('/^[0-9]{10}$/', $mobile))
                                 $error = 'Mobile must be 10 digits.';
    elseif ($password === '')    $error = 'Password required.';
    elseif (strlen($password) > 20)
                                 $error = 'Password max 20 chars.';

    if ($error === '') {
        $stmt = $db->prepare("SELECT id FROM staff WHERE username=?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'Username already exists.';
        } else {
            // plain password
            $stmt = $db->prepare("
                INSERT INTO staff (name, username, mobile, password, role, is_active)
                VALUES (?, ?, ?, ?, 'subadmin', 1)
            ");
            $stmt->bind_param('ssss', $name, $username, $mobile, $password);
            $stmt->execute();
            $popup = 'yes';
        }
    }
}

/* ====== TOGGLE ACTIVE ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        $st = $db->prepare("SELECT is_active FROM staff WHERE id=? AND role='subadmin'");
        $st->bind_param('i', $id);
        $st->execute();
        $st->bind_result($active);

        if ($st->fetch()) {
            $st->close();
            $new = $active ? 0 : 1;

            $up = $db->prepare("UPDATE staff SET is_active=? WHERE id=?");
            $up->bind_param('ii', $new, $id);
            $up->execute();
        }
    }
}

/* ====== FETCH LIST ====== */
$subadmins = [];
$rs = $db->query("SELECT * FROM staff WHERE role='subadmin' ORDER BY id DESC");
while ($r = $rs->fetch_assoc()) $subadmins[] = $r;
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
<meta charset="UTF-8">
<title>Sub Admin Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma;}
:root{--red:#c62828;--brown:#5c3a21;--dark:#8B0000;}
body{
  background:#faf2de;
  color:var(--brown);
  padding:15px;
  display:flex;
  justify-content:center;
  min-height:100vh;
}
.container{width:100%;max-width:900px;}

.top-bar{
  display:flex;justify-content:space-between;align-items:center;
  margin-bottom:8px;font-size:12px;
}
.back-link{
  text-decoration:none;color:var(--dark);
  font-weight:600;display:flex;align-items:center;gap:4px;
}
.back-link i{font-size:12px;}
.logged-as{color:#7b5a3a;font-size:11px;}

.page-title{
  text-align:center;font-size:22px;font-weight:700;
  margin:8px 0 15px;color:var(--dark);
}
.card{
  background:#fff;padding:18px;border-radius:12px;margin-bottom:16px;
  box-shadow:0 2px 6px rgba(0,0,0,0.1);
}
.card-title{
  font-size:15px;font-weight:700;margin-bottom:8px;color:var(--dark);
}

label{
  font-size:10px;font-weight:600;margin-bottom:3px;color:var(--brown);
}

.form-control{
  width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;font-size:14px;
}

.row{display:flex;flex-wrap:wrap;gap:12px;}
.col{flex:1;min-width:200px;}

.pw-wrap{position:relative;}
.pw-toggle{
  position:absolute;right:12px;top:65%;transform:translateY(-50%);
  background:none;border:none;font-size:16px;color:var(--dark);cursor:pointer;
}

.btn{
  padding:10px 20px;border:none;border-radius:8px;
  font-weight:700;cursor:pointer;
}
.btn-primary{
  background:linear-gradient(90deg,#750000,#b21616);
  color:#fff;
}

table{
  width:100%;border-collapse:collapse;margin-top:10px;font-size:12px;
}
th,td{
  border:1px solid #eed9be;padding:7px;
}
th{
  background:#f6e8cf;font-weight:700;
}

/* Popup */
#popup{
  position:fixed;top:50%;left:50%;
  transform:translate(-50%,-50%);
  background:#fff;padding:20px 26px;
  border-radius:10px;border:2px solid #a5d6a7;
  color:#2e7d32;font-size:14px;display:none;
}

.status-badge{
  display:inline-block;padding:3px 8px;border-radius:10px;
  font-size:10px;font-weight:600;
}
.status-active{background:#e8f5e9;color:#2e7d32;}
.status-deactive{background:#ffebee;color:#c62828;}

.switch{
  position:relative;display:inline-block;width:40px;height:20px;
}
.switch input{display:none;}
.slider{
  position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;
  background:#ccc;transition:.3s;border-radius:20px;
}
.slider:before{
  position:absolute;content:"";height:16px;width:16px;left:2px;bottom:2px;
  background:white;transition:.3s;border-radius:50%;
}
input:checked + .slider{background:#2e7d32;}
input:checked + .slider:before{transform:translateX(20px);}
.toggle-form{margin:0;display:flex;justify-content:center;}

.warn{
  display:none;font-size:10px;margin-top:2px;color:#c62828;
}
</style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

<div id="popup"><i class="fas fa-check-circle"></i> Sub Admin Created!</div>

<div class="container">

  <div class="top-bar">
    <a href="admin_dashboard.php" class="back-link">
      <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <?php
require_once 'includes/db.php'; if (!empty($_SESSION['admin'])): ?>
      <span class="logged-as">Logged in as: <strong><?= htmlspecialchars($_SESSION['admin']) ?></strong></span>
    <?php
require_once 'includes/db.php'; endif; ?>
  </div>

  <div class="page-title">Sub Admin Management</div>

  <div class="card">
    <div class="card-title">Create New Sub Admin</div>

    <?php
require_once 'includes/db.php'; if ($error): ?>
      <div style="color:#c62828;font-size:13px;margin-bottom:8px;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php
require_once 'includes/db.php'; endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="action" value="create">

      <div class="row">

        <div class="col">
          <label>Full Name (letters only)</label>
          <input class="form-control" name="name" maxlength="20"
                 pattern="[A-Za-z ]{1,20}" required>
        </div>

        <div class="col">
          <label>Username (admin01, admin02...)</label>
          <input class="form-control" name="username"
                 maxlength="20"
                 value="<?= htmlspecialchars($suggestedUsername) ?>"
                 pattern="[a-z0-9]{1,20}" required>
          <div id="userWarn" class="warn">Only lowercase & numbers allowed.</div>
        </div>

        <div class="col">
          <label>Mobile (10 digit)</label>
          <input class="form-control" name="mobile" maxlength="10"
                 pattern="[0-9]{10}" required>
          <div id="mobileWarn" class="warn">Digits only (10).</div>
        </div>

        <div class="col pw-wrap">
          <label>Password (max 20)</label>
          <input class="form-control" name="password"
                 maxlength="20" id="subPw" required>
          <button type="button" class="pw-toggle"
                  onclick="togglePwd('subPw',this)">
            <i class="fas fa-eye"></i>
          </button>
        </div>

      </div>

      <div style="margin-top:12px;text-align:right;">
        <button class="btn btn-primary">Create Sub Admin</button>
      </div>

    </form>
  </div>

  <div class="card">
    <div class="card-title">All Sub Admins</div>

    <table>
      <tr>
        <th>ID</th>
        <th>Full Name</th>
        <th>Username</th>
        <th>Password</th>
        <th>Mobile</th>
        <th>Status</th>
        <th>Toggle</th>
        <th>Created</th>
      </tr>

      <?php
require_once 'includes/db.php'; if (empty($subadmins)): ?>
        <tr><td colspan="8" style="text-align:center;">No Sub Admin found.</td></tr>

      <?php
require_once 'includes/db.php'; else: foreach($subadmins as $s): ?>
        <tr>
          <td><?= $s['id'] ?></td>

          <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>

          <td><?= htmlspecialchars($s['username']) ?></td>

          <td style="color:#b21616;font-weight:600;">
            <?= htmlspecialchars($s['password']) ?>
          </td>

          <td><?= htmlspecialchars($s['mobile']) ?></td>

          <td>
            <?php
require_once 'includes/db.php'; if ($s['is_active']): ?>
              <span class="status-active status-badge">Active</span>
            <?php
require_once 'includes/db.php'; else: ?>
              <span class="status-deactive status-badge">Deactive</span>
            <?php
require_once 'includes/db.php'; endif; ?>
          </td>

          <td style="text-align:center;">
            <form method="post" class="toggle-form">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <label class="switch">
                <input type="checkbox" <?= $s['is_active'] ? 'checked' : '' ?>
                       onchange="this.form.submit()">
                <span class="slider"></span>
              </label>
            </form>
          </td>

          <td><?= $s['created_at'] ?></td>
        </tr>
      <?php
require_once 'includes/db.php'; endforeach; endif; ?>
    </table>

  </div>
</div>

<script>
function togglePwd(id,btn){
  var i=document.getElementById(id);
  var ic=btn.querySelector('i');
  if(i.type==="password"){i.type="text";ic.classList.add("fa-eye-slash");}
  else{i.type="password";ic.classList.remove("fa-eye-slash");}
}

<?php
require_once 'includes/db.php'; if ($popup): ?>
document.getElementById("popup").style.display="block";
setTimeout(function() {document.getElementById("popup").style.display="none";},2000);
<?php
require_once 'includes/db.php'; endif; ?>

// Username validation
var uInp = document.querySelector('input[name="username"]');
var uWarn = document.getElementById('userWarn');
uInp.addEventListener('input', function () {
    var val = this.value.toLowerCase();
    var clean = val.replace(/[^a-z0-9]/g,'');
    if (val !== clean) uWarn.style.display='block';
    else uWarn.style.display='none';
    this.value = clean;
});

// Mobile validation
var mInp = document.querySelector('input[name="mobile"]');
var mWarn = document.getElementById('mobileWarn');
mInp.addEventListener('input', function () {
    var val = this.value;
    var clean = val.replace(/[^0-9]/g,'');
    if (val !== clean) mWarn.style.display='block';
    else mWarn.style.display='none';
    this.value = clean;
});
</script>

</body>
</html>
