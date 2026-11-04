<?php
require_once 'includes/lang.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/security.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_SESSION['user']['id'])) {
  $LLUser = $_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? '';
  header('Location: user_dashboard.php?LL=' . urlencode($LLUser));
  exit;
}

/* Flash helper */
function flash($msg = null)
{
  if ($msg) {
    $_SESSION['flash'] = $msg;
    return;
  }
  if (isset($_SESSION['flash'])) {
    echo '<script>alert(' . json_encode($_SESSION['flash']) . ');</script>';
    unset($_SESSION['flash']);
  }
}

/* DB connection */
require_once 'includes/db.php';
try {
    $mysqli = db();
} catch (Throwable $e) {
    echo '<div style="font-family:sans-serif; text-align:center; padding:50px 20px; background:#111; color:#fff;"><h2>Server Maintenance</h2><p>Database connection is currently updating. Please refresh in a few seconds.</p></div>';
    exit;
}

date_default_timezone_set('Asia/Kolkata');

$errors = [];

/* Fetch dynamic WhatsApp for service footer */
$waNum = '917021812900';
$waNumLink = '917021812900';
try {
  $resWa = $mysqli->query("SELECT * FROM admin_payment_details WHERE id = 1 LIMIT 1");
  if ($resWa && $rowWa = $resWa->fetch_assoc()) {
    $dbWa = $rowWa['whatsapp_no_1'] ?? $rowWa['whatsapp_deposit_no'] ?? $rowWa['whatsapp_no_2'] ?? '';
    if (!empty($dbWa)) {
      $waNum = trim($dbWa);
      $waNumLink = preg_replace('/[^0-9]/', '', $waNum);
    }
  }
} catch (Throwable $e) {
  error_log('user_login whatsapp fetch error: ' . $e->getMessage());
}

function clear_remember_cookies()
{
  $domain = '.subhgaming.online';
  setcookie('remember_mobile', '', time() - 3600, '/', $domain, false, true);
  setcookie('remember_pass', '', time() - 3600, '/', $domain, false, true);
}

/* Handle <?= __("Remember Me") ?> login on load */
if (!isset($_SESSION['user']['id']) && isset($_COOKIE['remember_mobile']) && isset($_COOKIE['remember_pass'])) {
  $mob = trim($_COOKIE['remember_mobile']);
  $pwd = (string) $_COOKIE['remember_pass'];

  if (preg_match('/^\d{10}$/', $mob) && preg_match('/^\d{4}$/', $pwd)) {
    $q = $mysqli->prepare("SELECT * FROM users WHERE mobile_no=? LIMIT 1");
    if ($q) {
      $q->bind_param('s', $mob);
      $q->execute();
      $res = $q->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $q->close();
    } else {
      $row = null;
    }

    if ($row) {
      $dbpwd = (string) ($row['password'] ?? '');
      $isActive = (int) ($row['is_active'] ?? 1);

      if (trim($pwd) === trim($dbpwd)) {
        $LLUser = trim((string) ($row['user_id'] ?? ''));
        if (preg_match('/^\d+$/', $LLUser)) {
          $LLUser = "KR" . str_pad($LLUser, 2, "0", STR_PAD_LEFT);
        } elseif (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $LLUser, $m)) {
          $LLUser = "KR" . str_pad($m[2], 2, "0", STR_PAD_LEFT);
        }

        if ($LLUser === '') {
          $LLUser = 'KR' . str_pad((string) $row['id'], 2, '0', STR_PAD_LEFT);
        }

        $_SESSION['user'] = [
          'id' => (int) $row['id'],
          'name' => (string) $row['name'],
          'mobile' => $mob,
          'user_id' => $LLUser,
          'is_active' => ($isActive === 1 ? 1 : 0)
        ];
        $_SESSION['user_id'] = $LLUser;



        header('Location: user_dashboard.php?LL=' . urlencode($LLUser));
        exit;
      } else {
        clear_remember_cookies();
      }
    } else {
      clear_remember_cookies();
    }
  } else {
    clear_remember_cookies();
  }
}

/* LOGIN submit */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $mob = trim($_POST['mobile_no'] ?? '');
  $pwd = (string) ($_POST['password'] ?? '');
  $remember = isset($_POST['remember']);

  if (!preg_match('/^\d{10}$/', $mob) || !preg_match('/^\d{4}$/', $pwd)) {
    $errors[] = 'Password must be 4 digit numbers only.';
  } else {

    $q = $mysqli->prepare("SELECT * FROM users WHERE mobile_no=? LIMIT 1");
    if ($q) {
      $q->bind_param('s', $mob);
      $q->execute();
      $res = $q->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $q->close();
    } else {
      $row = null;
      $errors[] = 'Database error. Failed to prepare query.';
    }

    if (!$row) {
      $errors[] = 'Mobile number not registered.';
    } else {
      $dbpwd = (string) ($row['password'] ?? '');
      $isActive = (int) ($row['is_active'] ?? 1);

      if ($pwd !== $dbpwd) {
        $errors[] = 'Incorrect password.';
      } else {

        $LLUser = trim((string) ($row['user_id'] ?? ''));
        if (preg_match('/^\d+$/', $LLUser)) {
          $LLUser = "KR" . str_pad($LLUser, 2, "0", STR_PAD_LEFT);
        } elseif (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $LLUser, $m)) {
          $LLUser = "KR" . str_pad($m[2], 2, "0", STR_PAD_LEFT);
        }

        if ($LLUser === '') {
          $LLUser = 'KR' . str_pad((string) $row['id'], 2, '0', STR_PAD_LEFT);
        }

        $_SESSION['user'] = [
          'id' => (int) $row['id'],
          'name' => (string) $row['name'],
          'mobile' => $mob,
          'user_id' => $LLUser,
          'is_active' => ($isActive === 1 ? 1 : 0)
        ];
        $_SESSION['user_id'] = $LLUser;

        $domain = '.subhgaming.online';
        $exp = time() + 86400 * 30;

        if ($remember) {
          setcookie('remember_mobile', $mob, $exp, '/', $domain, false, true);
          setcookie('remember_pass', $pwd, $exp, '/', $domain, false, true);
        } else {
          clear_remember_cookies();
        }

        header('Location: user_dashboard.php?LL=' . urlencode($LLUser));
        exit;
      }
    }
  }
}

/* Prefill values */
$prefMob = $_COOKIE['remember_mobile'] ?? '';
$prefPwd = $_COOKIE['remember_pass'] ?? '';
$prefChk = true; // Always checked by default
$phpHasErrors = !empty($errors);
?>
<!DOCTYPE html>
<html lang="en">

<head>

  <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <meta name="theme-color" content="#000000">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= __("Login") ?> | SUBH GAMING</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
      -webkit-tap-highlight-color: transparent;
    }

    html, body {
      background-color: #000000;
      background-image: 
        repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px),
        repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px);
      color: #dddddd;
      min-height: 100vh;
    }

    body {
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    .app-container {
      width: 100%;
      max-width: 480px;
      min-height: 100vh;
      background-color: #000000;
      background-image: 
        repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px),
        repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px);
      display: flex;
      flex-direction: column;
    }

    /* Top Red Header */
    .top-header {
      padding: max(45px, calc(env(safe-area-inset-top) + 20px)) 24px 32px 24px;
      background-color: #000000;
      background-image: 
        repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px),
        repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px);
      display: flex;
      flex-direction: column;
      align-items: flex-start;
    }

    .back-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 44px;
      height: 44px;
      background: #222222;
      color: #ffffff;
      border-radius: 12px;
      text-decoration: none;
      font-size: 18px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
      transition: transform 0.2s;
      margin-bottom: 24px;
    }

    .back-btn:active {
      transform: scale(0.94);
    }

    .welcome-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-left: -24px;
      width: calc(100% + 24px);
    }

    .white-accent-bar {
      width: 5px;
      height: 30px;
      background: #ffffff;
      border-radius: 0 3px 3px 0;
      flex-shrink: 0;
    }

    .welcome-title {
      color: #ffffff;
      font-size: 22px;
      font-weight: 600;
      margin: 0;
      letter-spacing: -0.3px;
      line-height: 1.2;
    }

    /* White Main Card Container */
    .main-card { flex: 1; background: #121212; border-top: 1px solid #2a2a2a; border-radius: 32px 32px 0 0;
      padding: 32px 24px 40px 24px;
      display: flex;
      flex-direction: column;
      box-shadow: 0 -8px 25px rgba(0, 0, 0, 0.12);
    }

    .card-title {
      text-align: center;
      font-size: 20px;
      font-weight: 600; color: #ff0000;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .title-coin-icon {
      width: 24px;
      height: 24px;
      object-fit: contain;
    }

    .error-msg {
      background: #fee2e2;
      border-left: 4px solid #dc2626;
      color: #991b1b;
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .form-group {
      margin-bottom: 18px;
    }

    .form-label {
      display: block;
      font-size: 14px;
      font-weight: 500;
      color: #dddddd;
      margin-bottom: 6px;
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .form-input { width: 100%; height: 50px; background: #ffffff; border: 1px solid #e0e0e0; box-shadow: inset 0 2px 5px rgba(0,0,0,0.05);
      border-radius: 12px;
      padding: 0 16px;
      font-size: 16px;
      font-weight: 600; color: #000000;
      outline: none;
      transition: all 0.2s ease;
    }

    .form-input:focus { border-color: #ff4d4d; outline: none; box-shadow: 0 0 0 2px rgba(255, 77, 77, 0.2); background: #ffffff; }

    .form-input::placeholder {
      color: #888888;
      font-weight: 400;
    }

    .pw-input {
      padding-right: 50px;
    }

    .pw-toggle-btn {
      position: absolute;
      right: 14px;
      background: none;
      border: none;
      color: #888888;
      font-size: 17px;
      cursor: pointer;
      padding: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .forgot-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 4px;
      margin-bottom: 24px;
    }

    .remember-me {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      color: #aaaaaa;
      cursor: pointer;
    }

    .remember-me input {
      accent-color: #ff4d4d;
      width: 16px;
      height: 16px;
    }

    .forgot-link {
      font-size: 13px;
      color: #aaaaaa;
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s;
    }

    .forgot-link:hover {
      color: #ff4d4d;
    }

    .btn-login {
      width: 100%;
      height: 50px;
      background: #ff4d4d;
      color: #ffffff;
      border: none;
      border-radius: 14px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 6px 18px rgba(139, 0, 0, 0.35);
      transition: all 0.2s ease;
    }

    .btn-login:hover {
      background: #e60000;
      box-shadow: 0 8px 22px rgba(139, 0, 0, 0.45);
    }

    .btn-login:active {
      transform: scale(0.98);
    }

    .footer-links {
      margin-top: 24px;
      text-align: center;
    }

    .signup-text {
      font-size: 14px;
      color: #aaaaaa;
      margin-bottom: 12px;
    }

    .signup-link {
      color: #ff4d4d;
      font-weight: 700;
      text-decoration: none;
    }

    .help-text {
      font-size: 13px;
      color: #888888;
    }

    .help-link {
      color: #aaaaaa;
      text-decoration: none;
      font-weight: 600;
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

  <div class="app-container">
    <!-- Top Red Header -->
    <div class="top-header">
      <a href="javascript:history.back()" class="back-btn" aria-label="Go Back">
        <i class="fas fa-chevron-left"></i>
      </a>
      <div class="welcome-row">
        <div class="white-accent-bar"></div>
        <h1 class="welcome-title"><?= __("Welcome Back!") ?></h1>
      </div>
    </div>

    <!-- Main Card Content -->
    <div class="main-card">
      <div class="card-title">
        <img src="icons/gold_coin.svg" alt="Coin" class="title-coin-icon">
        <?= __("Login") ?>
        <img src="icons/gold_coin.svg" alt="Coin" class="title-coin-icon">
      </div>

      <?php if (!empty($errors)): ?>
        <div class="error-msg">
          <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errors[0]) ?>
        </div>
      <?php endif; ?>

      <form action="user_login.php" method="POST" autocomplete="off">
        <div class="form-group">
          <label class="form-label"><?= __("Mobile Number") ?></label>
          <div class="input-wrapper">
            <input type="tel" name="mobile_no" class="form-control form-input" placeholder="<?= __("10 digit mobile number") ?>" maxlength="10"
              required value="<?= htmlspecialchars($prefMob) ?>" inputmode="numeric" pattern="[0-9]*">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label"><?= __("Password") ?></label>
          <div class="input-wrapper">
            <input type="password" name="password" id="password" class="form-control form-input pw-input" placeholder="****" maxlength="4"
              required value="<?= htmlspecialchars($prefPwd) ?>" inputmode="numeric" pattern="[0-9]*">
            <button type="button" class="pw-toggle-btn" onclick="togglePwd()" aria-label="Toggle Password Visibility">
              <i class="fas fa-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <div class="forgot-container">
          <label class="remember-me">
            <input type="checkbox" name="remember" <?= $prefChk ? 'checked' : '' ?>>
            <span><?= __("Remember Me") ?></span>
          </label>
          <a href="user_forgot_password.php" class="forgot-link"><?= __("Forgot Password?") ?></a>
        </div>

        <button type="submit" class="btn-login"><?= __("Login") ?></button>
      </form>

      <div class="footer-links">
        <p class="signup-text">
          <?= __("Don't have an account?") ?> <a href="user_register.php" class="signup-link"><?= __("Sign Up") ?></a>
        </p>
        <p class="help-text">
          <a href="https://wa.me/<?= $waNumLink ?>" class="help-link"><?= __("Need help? Call us at") ?></a>
        </p>
      </div>
    </div>
  </div>

  <script>
    document.getElementById('password').oninput = function () {
      this.value = this.value.replace(/[^0-9]/g, '');
    };

    function togglePwd() {
      var pwd = document.getElementById('password');
      var icon = document.getElementById('eyeIcon');
      if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        pwd.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }
  </script>
  <?php flash(); ?>
</body>

</html>









