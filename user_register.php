<?php
require_once 'includes/lang.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/sms_helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_OFF);

$mysqli = db();
if ($mysqli->connect_error) {
    die("DB Connection Error: " . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

// ── Check OTP System Status ──────────────────────────────────────────────────
function is_otp_system_on(mysqli $db): bool {
    return false;
}
$otpSystemOn = is_otp_system_on($mysqli);

try {
    $checkCol = $mysqli->query("SHOW COLUMNS FROM users LIKE 'language'");
    if ($checkCol && $checkCol->num_rows === 0) {
        $mysqli->query("ALTER TABLE users ADD COLUMN language VARCHAR(10) DEFAULT 'en'");
    }
    $checkRefCol = $mysqli->query("SHOW COLUMNS FROM users LIKE 'referral'");
    if ($checkRefCol && $checkRefCol->num_rows === 0) {
        $mysqli->query("ALTER TABLE users ADD COLUMN referral VARCHAR(50) DEFAULT NULL");
    }
} catch (Throwable $e) {
    error_log("Database auto-heal error: " . $e->getMessage());
}

$message = "";
$success = false;
$regMobile = "";
$regPass = "";
$LLUser = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = mb_substr(trim($_POST['name'] ?? ''), 0, 20);
    $mobile        = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
    $password      = preg_replace('/[^0-9]/', '', $_POST['password'] ?? '');
    $referral_code = mb_substr(trim($_POST['referral_code'] ?? $_POST['referral'] ?? ''), 0, 10);
    $otp_entered   = preg_replace('/[^0-9]/', '', $_POST['otp'] ?? '');

    // OTP Removed: Direct Registration
    if (!$name || !$mobile || !$password) {
        $message = __("All fields are required.");
    } elseif (!preg_match('/^\d{10}$/', $mobile)) {
        $message = __("Enter a valid 10-digit mobile number.");
    } elseif (!preg_match('/^\d{4}$/', $password)) {
        $message = __("Password must be 4 digit numbers only.");
    } else {
        $dup = $mysqli->prepare("SELECT id FROM users WHERE mobile_no=? LIMIT 1");
        $dup->bind_param('s', $mobile);
        $dup->execute();
        $dup->store_result();

        if ($dup->num_rows) {
            $message = __("Mobile number already registered.");
            $dup->close();
        } else {
            $dup->close();

            // Generate sequential user_id starting with MK01, MK02...
            $user_id = 'SG01';
            try {
                $chk = $mysqli->query("SELECT user_id FROM users WHERE user_id LIKE 'SG%' ORDER BY LENGTH(user_id) DESC, user_id DESC LIMIT 1");
                if ($chk && $row = $chk->fetch_assoc()) {
                    $last_id = $row['user_id'];
                    $num_part = (int) substr($last_id, 2);
                    if ($num_part > 0) {
                        $new_num = $num_part + 1;
                        $user_id = 'SG' . str_pad((string)$new_num, 2, '0', STR_PAD_LEFT);
                    }
                }
            } catch (Throwable $e) {}

            $userIp = trim(explode(',', $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
            $ins = $mysqli->prepare("INSERT INTO users (user_id,name,mobile_no,password,referral,is_active,language) VALUES (?,?,?,?,?,?,?)");
            $is_active = 1;
            $langCode = $_COOKIE['app_lang'] ?? 'en';
            $ins->bind_param('sssssis', $user_id, $name, $mobile, $password, $referral_code, $is_active, $langCode);

            if ($ins->execute()) {
                $new_id = (int) $ins->insert_id;
                $LLUser = $user_id;

                if (!empty($referral_code)) {
                    try {
                        $mysqli->query("CREATE TABLE IF NOT EXISTS `referrals` (`id` INT AUTO_INCREMENT, `referrer_id` VARCHAR(50) NOT NULL, `referee_id` VARCHAR(50) NOT NULL, `referee_name` VARCHAR(100), `referee_mobile` VARCHAR(20), `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(`id`), UNIQUE KEY `uniq_referee_id`(`referee_id`), KEY `idx_ref`(`referrer_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                        $insRef = $mysqli->prepare("INSERT IGNORE INTO referrals (referrer_id, referee_id, referee_name, referee_mobile) VALUES (?, ?, ?, ?)");
                        if ($insRef) {
                            $insRef->bind_param('ssss', $referral_code, $user_id, $name, $mobile);
                            $insRef->execute();
                            $insRef->close();
                        }
                    } catch (Throwable $e) {}
                }

                unset($_SESSION['reg_otp']);
                unset($_SESSION['reg_mobile']);
                $_SESSION['user'] = ['id' => $new_id, 'name' => $name, 'mobile' => $mobile, 'user_id' => $LLUser, 'is_active' => 1];
                $_SESSION['user_id'] = $LLUser;
                $success = true;
                $regMobile = $mobile;
                $regPass = $password;
                $welcome_msg = "Welcome to SUBH GAMING! Your User ID is $LLUser.";
                @send_sms($mobile, $welcome_msg);
            } else {
                $message = "Database error: " . $mysqli->error;
            }
            $ins->close();
        }
    }
}
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
  <title><?= __("Register") ?> | SUBH GAMING</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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

    .message { background: #2a1010;
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

    .label-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 6px;
    }

    .form-label {
      display: block;
      font-size: 14px;
      font-weight: 500;
      color: #dddddd;
      margin-bottom: 0;
    }

    .ref-status-badge {
      font-size: 13px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .ref-status-badge.valid {
      color: #16a34a;
    }

    .ref-status-badge.invalid {
      color: #dc2626;
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

    .btn-otp {
      position: absolute;
      right: 8px;
      background: #ff4d4d;
      color: white;
      border: none;
      padding: 8px 14px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .btn-otp:disabled {
      background: #cbd5e1 !important;
      color: #64748b !important;
      cursor: not-allowed;
    }

    .btn-register {
      width: 100%;
      height: 50px;
      background: #ff4d4d;
      color: #ffffff;
      border: none;
      border-radius: 14px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 6px 18px rgba(255, 77, 77, 0.35);
      transition: all 0.2s ease;
      margin-top: 10px;
    }

    .btn-register:hover {
      background: #e60000;
      box-shadow: 0 8px 22px rgba(255, 77, 77, 0.45);
    }

    .btn-register:active {
      transform: scale(0.98);
    }

    .footer-links {
      margin-top: 24px;
      text-align: center;
    }

    .signin-text {
      font-size: 14px;
      color: #aaaaaa;
    }

    .signin-link {
      color: #ff4d4d;
      font-weight: 700;
      text-decoration: none;
    }

    .otp-status {
      display: none;
      margin: 0 0 15px;
      padding: 10px 12px;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 700;
      text-align: left;
    }

    .otp-status.error {
      display: block;
      background: rgba(255, 77, 77, 0.08);
      color: #dc2626;
    }

    .otp-status.success {
      display: block;
      background: rgba(0, 0, 0, 0.05);
      color: #ff4d4d;
    }

    /* Modal Styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      backdrop-filter: blur(8px);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 1000;
      padding: 20px;
    }

    .modal-overlay.active {
      display: flex;
    }

    .otp-modal { background: #1a1a1a;
      width: 100%;
      max-width: 380px;
      border-radius: 24px;
      padding: 30px 24px;
      position: relative;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
      text-align: center;
    }

    .otp-modal .icon-circle {
      width: 60px;
      height: 60px;
      background: rgba(0, 0, 0, 0.05);
      color: #ff4d4d;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 16px;
      font-size: 24px;
    }

    .modal-close {
      position: absolute;
      top: 16px;
      right: 16px;
      font-size: 18px;
      color: #999;
      cursor: pointer;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f1f5f9;
    }

    .btn-step {
      background: #ff4d4d;
      color: #fff;
      border: none;
      padding: 10px 20px;
      border-radius: 10px;
      font-weight: 700;
      cursor: pointer;
    }

    .loader-bar { width: 100%; height: 4px; background: #eee; border-radius: 2px; overflow: hidden; margin-top: 20px; }
    .loader-progress { width: 0%; height: 100%; background: #ff4d4d; animation: progress 2.5s linear forwards; }
    @keyframes progress { from { width: 0%; } to { width: 100%; } }
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
        <h1 class="welcome-title"><?= __("Create New Account!") ?></h1>
      </div>
    </div>

    <!-- Main Card Content -->
    <div class="main-card">
      <div class="card-title">
        <img src="icons/gold_coin.svg" alt="Coin" class="title-coin-icon">
        <?= __("Register Here") ?>
        <img src="icons/gold_coin.svg" alt="Coin" class="title-coin-icon">
      </div>

      <?php if ($message && !$success): ?>
        <div class="message">
          <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off" id="regF">
        <!-- Full Name -->
        <div class="form-group">
          <label class="form-label"><?= __("Full Name") ?></label>
          <div class="input-wrapper">
            <input id="u_n" name="name" class="form-control form-input" maxlength="20" placeholder="<?= __("Enter Full Name") ?>" required
              value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
          </div>
        </div>

        <!-- Mobile Number -->
        <div class="form-group">
          <label class="form-label"><?= __("Mobile Number") ?></label>
          <div class="input-wrapper">
            <input id="m_o" name="phone" class="form-control form-input" maxlength="10" inputmode="numeric" pattern="[0-9]*"
              placeholder="<?= __("10 digit mobile number") ?>" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
        </div>

        <!-- 4-Digit Password -->
        <div class="form-group">
          <label class="form-label"><?= __("Password") ?></label>
          <div class="input-wrapper">
            <input id="p_f" name="password" type="password" class="form-control form-input pw-input" maxlength="4" inputmode="numeric" pattern="[0-9]*"
              placeholder="****" required>
            <button type="button" class="pw-toggle-btn" onclick="togglePass()" aria-label="Toggle Password Visibility">
              <i class="fas fa-eye toggle-pass"></i>
            </button>
          </div>
        </div>

        <!-- Referral Code (Below Password) -->
        <div class="form-group">
          <div class="label-row">
            <label class="form-label"><?= __("Referral Code (Optional)") ?></label>
            <span id="refStatus" class="ref-status-badge"></span>
          </div>
          <div class="input-wrapper">
            <input id="ref_c" name="referral_code" class="form-control form-input" maxlength="10" placeholder="<?= __("Enter Referral Code") ?>"
              value="<?= htmlspecialchars($_POST['referral_code'] ?? $_GET['ref'] ?? '') ?>">
          </div>
        </div>

        <button type="submit" class="btn-register"><?= __("REGISTER NOW") ?></button>
      </form>

      <div class="footer-links">
        <p class="signin-text">
          <?= __("Already have an account?") ?> <a href="user_login.php" class="signin-link"><?= __("Sign In") ?></a>
        </p>
      </div>
    </div>
  </div>

  <div id="noticeOverlay" class="modal-overlay">
    <div class="otp-modal">
      <i class="fas fa-times modal-close" onclick="closeNotice()"></i>
      <div class="icon-circle" id="noticeIconWrap"><i class="fas fa-circle-exclamation" id="noticeIcon"></i></div>
      <h2 id="noticeTitle"><?= __("Notice") ?></h2>
      <p id="noticeText"><?= __("Please try again.") ?></p>
      <div class="notice-actions" style="margin-top: 20px;">
        <button type="button" class="btn-step" onclick="closeNotice()">OK</button>
      </div>
    </div>
  </div>

  <!-- Success Modal -->
  <div id="successOverlay" class="modal-overlay">
    <div class="otp-modal">
      <div class="icon-circle"><i class="fas fa-check"></i></div>
      <h2>Success!</h2>
      <p>Your account has been created successfully. Redirecting you to the dashboard...</p>
      <div class="loader-bar">
        <div class="loader-progress"></div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    function setOtpStatus(message, type) {
      if (!message) return;
      showNotice('<?= __("Notice") ?>', message, type === 'success' ? 'success' : 'error', type === 'success' ? 2000 : 0);
    }

    function clearOtpStatus() {
      // No inline status div needed
    }

    function showNotice(title, message, type = 'error', autoCloseMs = 0) {
      var icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
      var color = type === 'success' ? '#8b0000' : '#dc2626';
      $('#noticeTitle').text(title);
      $('#noticeText').text(message);
      $('#noticeIcon').removeClass().addClass('fas ' + icon);
      $('#noticeIconWrap').css({ color: color, background: type === 'success' ? 'rgba(255, 77, 77, 0.1)' : 'rgba(220, 38, 38, 0.08)' });
      $('#noticeOverlay').addClass('active');

      if (autoCloseMs > 0) {
        setTimeout(function() {
          closeNotice();
        }, autoCloseMs);
      }
    }

    function closeNotice() {
      $('#noticeOverlay').removeClass('active');
    }

    $('#m_o').on('input', function () {
      this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
    });

    $('#otp').on('input', function () {
      this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
    });

    var OTP_SYSTEM_ON = <?= $otpSystemOn ? 'true' : 'false' ?>;

    function sendOTP() {
      var m = $('#m_o').val();
      if (!/^\d{10}$/.test(m)) { showNotice('<?= __("Invalid Number") ?>', '<?= __("Enter a valid 10-digit mobile number.") ?>'); return; }

      if (!OTP_SYSTEM_ON) {
        $.post('ajax_otp_handler.php', { action: 'send_registration_otp', mobile: m }, function() {}, 'json');
        return;
      }

      $('#btn-get-otp').prop('disabled', true).text('Sending...');
      clearOtpStatus();

      $.post('ajax_otp_handler.php', { action: 'send_registration_otp', mobile: m }, function (res) {
        if (res.status === 'success') {
          showNotice('<?= __("OTP Sent") ?>', '<?= __("OTP sent successfully. Please check your SMS.") ?>', 'success', 2000);
          $('#otpGroup').slideDown();
          startTimer(60);
        } else {
          showNotice('<?= __("OTP Not Sent") ?>', res.message || '<?= __("Please try again later.") ?>');
          $('#btn-get-otp').prop('disabled', false).text('GET OTP');
        }
      }, 'json').fail(function () {
        showNotice('<?= __("Connection Error") ?>', '<?= __("Unable to connect right now. Please try again.") ?>');
        $('#btn-get-otp').prop('disabled', false).text('GET OTP');
      });
    }

    function startTimer(duration) {
      var timer = duration, seconds;
      var btn = $('#btn-get-otp');
      btn.prop('disabled', true);
      var interval = setInterval(function () {
        seconds = parseInt(timer % 60, 10);
        btn.text("Wait " + seconds + "s");
        if (--timer < 0) {
          clearInterval(interval);
          btn.prop('disabled', false).text('RESEND OTP');
        }
      }, 1000);
    }

    function togglePass() {
      var p = $('#p_f');
      var i = $('.toggle-pass');
      if (p.attr('type') === 'password') {
        p.attr('type', 'text');
        i.removeClass('fa-eye').addClass('fa-eye-slash');
      } else {
        p.attr('type', 'password');
        i.removeClass('fa-eye-slash').addClass('fa-eye');
      }
    }

    $('#p_f').on('input', function () {
      this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
    });

    var refCheckCount = 0;
    var lastCheckedCode = '';

    function checkReferralCode() {
      var code = $('#ref_c').val().trim();
      if (!code) {
        lastCheckedCode = '';
        $('#refStatus').removeClass('valid invalid').html('');
        return;
      }

      if (refCheckCount >= 5 && code !== 'GR01') {
        $('#ref_c').val('GR01');
        code = 'GR01';
      }

      if (code === lastCheckedCode) return;
      lastCheckedCode = code;
      refCheckCount++;

      $.post('ajax_otp_handler.php', { action: 'check_referral', code: code }, function(res) {
        if (res && res.status === 'valid') {
          $('#refStatus').removeClass('invalid').addClass('valid').html('<i class="fas fa-check-circle"></i> ' + res.name);
        } else if (res && res.status === 'invalid') {
          $('#refStatus').removeClass('valid').addClass('invalid').html('<i class="fas fa-times-circle"></i> Invalid');
        } else {
          $('#refStatus').removeClass('valid invalid').html('');
        }
      }, 'json').fail(function() {
        $('#refStatus').removeClass('valid invalid').html('');
      });
    }

    var refTimer;
    $('#ref_c').on('input change blur keyup', function () {
      this.value = this.value.slice(0, 10);
      clearTimeout(refTimer);
      refTimer = setTimeout(checkReferralCode, 150);
    });

    $(document).ready(function() {
      if ($('#ref_c').val().trim() !== '') {
        checkReferralCode();
      }
    });
  </script>
  <?php if ($success): ?>
    <script>
      $('#successOverlay').addClass('active');
      setTimeout(function () {
        window.location.href = 'user_dashboard.php';
      }, 2500);
    </script>
  <?php endif; ?>
</body>
</html>







