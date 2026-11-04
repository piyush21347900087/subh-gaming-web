<?php
require_once 'includes/lang.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/sms_helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

mysqli_report(MYSQLI_REPORT_OFF);

$mysqli = db();
if ($mysqli->connect_error) {
    die("DB Connection Error: " . $mysqli->connect_error);
}

// ── Check OTP system ON/OFF ───────────────────────────────────────────────────
function is_otp_system_on(mysqli $db): bool {
    return false;
}
$otpSystemOn = is_otp_system_on($mysqli);

$message = "";
$recovered_pw = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $mobile      = trim($_POST['phone'] ?? '');
  $otp_entered = trim($_POST['otp'] ?? '');

  // Direct Password Recovery without OTP
  if (!$mobile) {
    $message = "Please enter your 10-digit mobile number.";
  } elseif (!preg_match('/^\d{10}$/', $mobile)) {
    $message = "Enter a valid 10-digit mobile number.";
  } else {
    $stmt = $mysqli->prepare("SELECT password FROM users WHERE mobile_no = ? LIMIT 1");
    $stmt->bind_param('s', $mobile);
    $stmt->execute();
    $stmt->store_result();

    if (!$stmt->num_rows) {
      $message = "Mobile number not registered.";
    } else {
      $stmt->bind_result($password);
      $stmt->fetch();
      $stmt->close();
      $recovered_pw = $password;
      $message = "SUCCESS_RECOVERED";
      unset($_SESSION['forgot_otp']);
      unset($_SESSION['forgot_mobile']);
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
  <title><?= __("Forgot Password") ?> | SUBH GAMING</title>
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

    .btn-submit {
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

    .btn-submit:hover {
      background: #e60000;
      box-shadow: 0 8px 22px rgba(255, 77, 77, 0.45);
    }

    .btn-submit:active {
      transform: scale(0.98);
    }

    .pass-box {
      background: #f8fafc;
      color: #0f172a;
      font-size: 24px;
      padding: 20px;
      border-radius: 14px;
      margin: 20px 0;
      font-weight: 700;
      text-align: center;
      letter-spacing: 2px;
      border: 2px dashed #8b0000;
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

    .nice-modal {
      background: #fff;
      width: 100%;
      max-width: 380px;
      border-radius: 24px;
      padding: 30px 24px;
      position: relative;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
      text-align: center;
    }

    .modal-icon {
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
        <h1 class="welcome-title"><?= __("Password Recovery") ?></h1>
      </div>
    </div>

    <!-- Main Card Content -->
    <div class="main-card">
      <div class="card-title">
        <img src="icons/gold_coin.svg" alt="Coin" class="title-coin-icon">
        <?= __("Forgot Password") ?>
        <img src="icons/gold_coin.svg" alt="Coin" class="title-coin-icon">
      </div>

      <?php if ($message === "SUCCESS_RECOVERED"): ?>
        <div style="text-align:center">
          <i class="fas fa-check-circle" style="color: #16a34a; font-size: 54px; margin-bottom: 16px;"></i>
          <h2 style="font-weight:700; color:#1e293b; font-size:22px;"><?= __("Password Found!") ?></h2>
          <div class="pass-box"><?= htmlspecialchars($recovered_pw) ?></div>
          <a href="user_login.php" class="btn-submit" style="display:flex; align-items:center; justify-content:center; text-decoration:none;"><?= __("LOGIN NOW") ?></a>
        </div>
      <?php else: ?>
        <?php if ($message): ?>
          <div class="message">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($message) ?>
          </div>
        <?php endif; ?>

        <form method="POST" id="forF" autocomplete="off">
          <!-- Mobile Number -->
          <div class="form-group">
            <label class="form-label"><?= __("Mobile Number") ?></label>
            <div class="input-wrapper">
              <input id="m_o" name="phone" class="form-control form-input" maxlength="10" inputmode="numeric" pattern="[0-9]*"
                placeholder="<?= __("10 digit mobile number") ?>" required>
            </div>
          </div>

          <button type="submit" class="btn-submit"><?= __("RECOVER PASSWORD") ?></button>

          <div class="footer-links">
            <p class="signin-text">
              <?= __("Remember password?") ?> <a href="user_login.php" class="signin-link"><?= __("Sign In") ?></a>
            </p>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div id="noticeOverlay" class="modal-overlay">
    <div class="nice-modal">
      <i class="fas fa-times modal-close" onclick="closeNotice()"></i>
      <div class="modal-icon" id="noticeIconWrap"><i class="fas fa-circle-exclamation" id="noticeIcon"></i></div>
      <h2 id="noticeTitle"><?= __("Notice") ?></h2>
      <p id="noticeText"><?= __("Please try again.") ?></p>
      <div style="margin-top: 20px;">
        <button type="button" class="btn-submit" onclick="closeNotice()" style="height: 42px; margin-top: 0; font-size: 14px;">OK</button>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    $('#m_o').on('input', function () {
      this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
    });

    $('#otp_o').on('input', function () {
      this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
    });

    function showNotice(title, message, type = 'error') {
      var icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
      var color = type === 'success' ? '#16a34a' : '#dc2626';
      $('#noticeTitle').text(title);
      $('#noticeText').text(message);
      $('#noticeIcon').removeClass().addClass('fas ' + icon);
      $('#noticeIconWrap').css({ color: color, background: type === 'success' ? 'rgba(22, 163, 74, 0.1)' : 'rgba(220, 38, 38, 0.08)' });
      $('#noticeOverlay').addClass('active');
    }

    function closeNotice() {
      $('#noticeOverlay').removeClass('active');
    }

    function sendOTP() {
      var m = $('#m_o').val();
      if (!/^\d{10}$/.test(m)) { showNotice('<?= __("Invalid Number") ?>', '<?= __("Enter a valid 10-digit mobile number.") ?>'); return; }

      $('#s_o').prop('disabled', true).text('Sending...');

      $.post('ajax_otp_handler.php', { action: 'send_forgot_otp', mobile: m }, function(res) {
        if (res.status === 'success') {
          showNotice('<?= __("OTP Sent") ?>', '<?= __("A 4-digit OTP has been sent to ") ?>' + m + '.', 'success');
          startTimer(60);
        } else {
          showNotice('<?= __("OTP Not Sent") ?>', res.message || '<?= __("Please try again later.") ?>');
          $('#s_o').prop('disabled', false).text('GET OTP');
        }
      }, 'json').fail(function() {
        showNotice('<?= __("Connection Error") ?>', '<?= __("Unable to connect right now. Please try again.") ?>');
        $('#s_o').prop('disabled', false).text('GET OTP');
      });
    }

    function startTimer(duration) {
      var timer = duration, seconds;
      var btn = $('#s_o');
      var interval = setInterval(function () {
        seconds = parseInt(timer % 60, 10);
        btn.text("Wait " + seconds + "s");
        if (--timer < 0) {
          clearInterval(interval);
          btn.prop('disabled', false).text('GET OTP');
        }
      }, 1000);
    }
  </script>
</body>
</html>



