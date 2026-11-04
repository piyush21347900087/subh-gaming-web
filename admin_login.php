<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/app_brand.php';

if (isset($_SESSION['admin'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email === '' || $pass === '') {
        $msg = 'Enter both e-mail and password.';
    } else {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $db = db();
            $stmt = $db->prepare("SELECT id, name, password, role FROM admin WHERE email = ? OR admin_id = ?");
            $stmt->bind_param('ss', $email, $email);
            $stmt->execute();
            $stmt->bind_result($id, $name, $dbPass, $role);
            $found = $stmt->fetch();
            $stmt->close();

            if (!($found && trim($pass) === trim($dbPass))) {
                $msg = 'Invalid credentials.';
                $db->close();
            } else {
                $_SESSION['admin']       = $name;
                $_SESSION['admin_id']    = (int)$id;
                $_SESSION['admin_email'] = $email;
                $_SESSION['admin_role']  = $role;

                try {
                    $db->query("UPDATE admin_status SET status='online', last_seen=NOW() WHERE id=1");
                } catch (Throwable $e) {
                }

                $db->close();
                header('Location: admin_dashboard.php');
                exit;
            }
        } catch (Throwable $e) {
            $msg = 'DB error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars(SUBH_APP_NAME); ?> Admin Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --bg-1: #07111f;
      --bg-2: #0f172a;
      --card: rgba(18, 26, 42, 0.9);
      --card-strong: rgba(15, 23, 34, 0.96);
      --border: rgba(148, 163, 184, 0.2);
      --text: #ecf2ff;
      --muted: #93a7c9;
      --primary: #7c3aed;
      --secondary: #2dd4bf;
      --gold: #fbbf24;
      --danger: #ef4444;
      --shadow: 0 30px 70px rgba(2, 6, 23, 0.55);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
    body {
      min-height: 100vh;
      display: grid;
      place-items: center;
      background: radial-gradient(circle at top left, rgba(124, 58, 237, 0.25), transparent 28%),
                  radial-gradient(circle at bottom right, rgba(45, 212, 191, 0.18), transparent 30%),
                  linear-gradient(135deg, var(--bg-1), var(--bg-2));
      color: var(--text);
      padding: 22px;
    }
    .shell {
      width: min(440px, 100%);
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 28px;
      box-shadow: var(--shadow);
      overflow: hidden;
    }
    .header {
      padding: 28px 28px 18px;
      text-align: center;
      background: linear-gradient(180deg, rgba(124, 58, 237, 0.18), rgba(15, 23, 34, 0.1));
      border-bottom: 1px solid var(--border);
    }
    .brand-badge {
      width: 72px; height: 72px; margin: 0 auto 16px; border-radius: 22px;
      display: grid; place-items: center; font-weight: 800; font-size: 24px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white; box-shadow: 0 15px 30px rgba(124, 58, 237, 0.45);
    }
    .title { font-size: clamp(1.5rem, 2vw, 2rem); font-weight: 800; } 
    .subtitle { margin-top: 6px; color: var(--muted); font-size: 0.85rem; letter-spacing: 0.08em; text-transform: uppercase; }
    .body {
      padding: 24px 28px 28px;
    }
    .msg {
      list-style: none; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35);
      border-radius: 12px; color: #fecaca; padding: 10px 12px; font-size: 0.9rem; margin-bottom: 18px;
    }
    .form-group { margin-bottom: 18px; }
    .label {
      display: block; margin-bottom: 8px; color: var(--muted); font-size: 0.82rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
    }
    .input-wrap { position: relative; }
    .form-control {
      width: 100%; border: 1px solid var(--border); background: rgba(15, 23, 34, 0.8); border-radius: 14px;
      color: var(--text); padding: 13px 14px; font-size: 1rem; outline: none; transition: all 150ms ease; 
    }
    .form-control:focus { border-color: rgba(124, 58, 237, 0.8); box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.14); }
    .pw-toggle {
      position: absolute; right: 14px; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: var(--muted); cursor: pointer; font-size: 1rem;
    }
    .save-wrap { display: flex; align-items: center; gap: 10px; font-size: 0.92rem; color: var(--muted); margin-bottom: 18px; }
    .save-wrap input { accent-color: var(--primary); width: 16px; height: 16px; }
    .btn {
      width: 100%; border: 0; border-radius: 14px; padding: 14px 18px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase;
      color: white; cursor: pointer; background: linear-gradient(135deg, var(--primary), var(--secondary));
      box-shadow: 0 15px 30px rgba(124, 58, 237, 0.3); transition: transform 150ms ease; 
    }
    .btn:hover { transform: translateY(-1px); }
    @media (max-width: 500px) {
      .shell { border-radius: 22px; }
      .header, .body { padding-left: 18px; padding-right: 18px; }
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
  <div class="shell">
    <div class="header">
      <div class="brand-badge">SG</div>
      <div class="title"><?php echo htmlspecialchars(SUBH_APP_NAME); ?></div>
      <div class="subtitle">Admin Control Panel</div>
    </div>

    <div class="body">
      <?php if ($msg): ?>
        <ul class="msg"><li><?= htmlspecialchars($msg) ?></li></ul>
      <?php endif ?>

      <form method="POST" autocomplete="off">
        <div class="form-group">
          <label class="label" for="adminEmail">Email</label>
          <div class="input-wrap">
            <input class="form-control" type="email" name="email" id="adminEmail" maxlength="120" required>
          </div>
        </div>

        <div class="form-group">
          <label class="label" for="loginPass">Password</label>
          <div class="input-wrap">
            <input class="form-control" type="password" id="loginPass" name="password" maxlength="20" required>
            <button type="button" class="pw-toggle" onclick="togglePwd('loginPass', this)"><i class="fas fa-eye"></i></button>
          </div>
        </div>

        <div class="save-wrap">
          <input type="checkbox" id="saveAdminPass">
          <label for="saveAdminPass">Save password</label>
        </div>

        <button class="btn" type="submit">Login</button>
      </form>
    </div>
  </div>

  <script>
    function togglePwd(id, btn) {
      const input = document.getElementById(id);
      const icon = btn ? btn.querySelector('i') : null;
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      if (icon) {
        icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
      }
    }

    const adminEmail = document.getElementById('adminEmail');
    const adminPass  = document.getElementById('loginPass');
    const saveCheck  = document.getElementById('saveAdminPass');

    window.addEventListener('DOMContentLoaded', function() {
      const ad = localStorage.getItem('admin_save');
      if (ad && adminEmail && adminPass) {
        try {
          const obj = JSON.parse(atob(ad));
          if (obj.email) adminEmail.value = obj.email;
          if (obj.pass)  adminPass.value  = obj.pass;
          if (saveCheck) saveCheck.checked = true;
        } catch(e){}
      }
    });

    const form = document.getElementById('adminLoginForm');
    if (form) {
      form.addEventListener('submit', function() {
        if (saveCheck && saveCheck.checked && adminEmail && adminPass) {
          const obj = {
            email: adminEmail.value,
            pass:  adminPass.value
          };
          localStorage.setItem('admin_save', btoa(JSON.stringify(obj)));
        } else {
          localStorage.removeItem('admin_save');
        }
      });
    }
  </script>
</body>
</html>
