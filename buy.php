<?php
function get_bonus_amount(mysqli $db, float $amount): float {
        try {
            $enabled = (int)($db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1")->fetch_row()[0] ?? 1);
            if ($enabled !== 1) return 0.0;
            $stmt = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE is_active=1 AND min_amount <= ? ORDER BY min_amount DESC LIMIT 1");
            $stmt->bind_param('d', $amount);
            $stmt->execute();
            $stmt->bind_result($bonus);
            $out = $stmt->fetch() ? (float)$bonus : 0.0;
            $stmt->close();
            return $out;
        } catch (Throwable $e) { return 0.0; }
    }

require_once 'includes/lang.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';

/* ✅ No-cache (WebView friendly) */
header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (file_exists('includes/user_ping.php'))
  require_once 'includes/user_ping.php';
if (file_exists('includes/live_ping.php'))
  require_once 'includes/live_ping.php';

if (!isset($_SESSION['user']['id'])) {
  header('Location: user_login.php');
  exit;
}

date_default_timezone_set('Asia/Kolkata');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = db();
} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  echo '<h3>Database Connection Error</h3>';
  echo '<p>Error: ' . $e->getMessage() . '</p>';
  exit;
}

$uid = (int) $_SESSION['user']['id'];

function fetch_one(mysqli $db, string $sql, string $types = '', array $params = []): ?string
{
  $stmt = $db->prepare($sql);
  if ($types !== '')
    $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $stmt->bind_result($val);
  $out = $stmt->fetch() ? $val : null;
  $stmt->close();
  return $out;
}

/* User Code */
$userCode = fetch_one($db, 'SELECT user_id FROM users WHERE id = ?', 'i', [$uid]) ?: '';
if (preg_match('/^\d+$/', $userCode)) {
  $userCode = "KR" . str_pad($userCode, 2, "0", STR_PAD_LEFT);
} elseif (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $userCode, $m)) {
  $userCode = $m[1] . str_pad($m[2], 2, "0", STR_PAD_LEFT);
}

/* Fetch Admin Payment Details for Crypto */
$pay = [
  'crypto_name' => 'USDT',
  'crypto_network' => 'TRC20',
  'crypto_address' => '',
  'crypto_qr' => '',
  'inr_rate_per_unit' => 90.0,
  'min_deposit' => 300,
];

try {
  $resPay = $db->query("SELECT * FROM admin_payment_details WHERE id=1 LIMIT 1");
  if ($resPay && $rowPay = $resPay->fetch_assoc()) {
    $pay = array_merge($pay, $rowPay);
  } else {
    $resPayFallback = $db->query("SELECT * FROM admin_payment_details ORDER BY id DESC LIMIT 1");
    if ($resPayFallback && $rowPayFallback = $resPayFallback->fetch_assoc()) {
      $pay = array_merge($pay, $rowPayFallback);
    }
  }
} catch (Exception $e) {}

$cryptoName = trim($pay['crypto_name'] ?? 'USDT');
if ($cryptoName === '') $cryptoName = 'USDT';
$cryptoNetwork = trim($pay['crypto_network'] ?? 'TRC20');
if ($cryptoNetwork === '') $cryptoNetwork = 'TRC20';
$cryptoAddress = trim($pay['crypto_address'] ?? '');
$cryptoQr = trim($pay['crypto_qr'] ?? '');
$cryptoRate = (float) ($pay['inr_rate_per_unit'] ?? 90.0);
if ($cryptoRate <= 0) $cryptoRate = 90.0;
$MIN_DEPOSIT = (int) ($pay['min_deposit'] ?? 300);
if ($MIN_DEPOSIT < 1) $MIN_DEPOSIT = 300;

$flashMsg = '';

/* Handle Form Submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $amount = (float) ($_POST['amount'] ?? 0);
  if ($amount < $MIN_DEPOSIT) {
    $flashMsg = "Minimum deposit amount is ₹" . $MIN_DEPOSIT;
  } else {
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
      @mkdir($uploadDir, 0777, true);
    }
    $screenshotPath = '';
    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
      $ext = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
      $filename = 'dep_usdt_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
      if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $uploadDir . $filename)) {
        $screenshotPath = $uploadDir . $filename;
      }
    }

    $order_id = 'USDT' . date('YmdHis') . rand(100, 999);

    $stCol = $db->query("SHOW COLUMNS FROM deposit LIKE 'user_id'");
    $uCol = ($stCol && $stCol->num_rows > 0) ? 'user_id' : 'userid';

    $hasUsdtCol = false;
    $stUsdt = $db->query("SHOW COLUMNS FROM deposit LIKE 'screenshot_usdt'");
    if ($stUsdt && $stUsdt->num_rows > 0) {
      $hasUsdtCol = true;
    }

    if ($hasUsdtCol) {
      $sql = "INSERT INTO deposit ($uCol, amount, status, method, date_time, order_id, screenshot_usdt, bonus_amount) VALUES (?, ?, 'Pending', ?, '" . date('Y-m-d H:i:s') . "', ?, ?, ?)";
    } else {
      $sql = "INSERT INTO deposit ($uCol, amount, status, method, date_time, order_id, screenshot, bonus_amount) VALUES (?, ?, 'Pending', ?, '" . date('Y-m-d H:i:s') . "', ?, ?, ?)";
    }

    $st = $db->prepare($sql);
    if ($st) {
      $methodName = 'CRYPTO';
      $bAmt = get_bonus_amount($db, $amount);
        $st->bind_param('sdsssd', $userCode, $amount, $methodName, $order_id, $screenshotPath, $bAmt);
      $st->execute();
      $st->close();

      try {
        if (file_exists(__DIR__ . '/includes/fcm_helper.php')) {
          require_once __DIR__ . '/includes/fcm_helper.php';
          if (function_exists('send_admin_alert_notification')) {
            send_admin_alert_notification('admin_deposit', 'New Crypto Deposit Request', "Crypto Deposit request of ₹{$amount} by {$userCode}", $userCode, (float)$amount, 'CRYPTO');
          }
        }
      } catch (Throwable $e) {}

      header('Location: deposit_history.php?dep=ok');
      exit;
    } else {
      $flashMsg = "Failed to submit request. Please try again.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buy <?= htmlspecialchars($cryptoName) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
    body { background: #f5f6fa; color: #222; min-height: 100vh; padding-bottom: 40px; }

    .top-header {
      background: linear-gradient(135deg, #c62828 0%, #8e0000 100%);
      color: #fff;
      padding: 15px 18px;
      display: flex;
      align-items: center;
      box-shadow: 0 3px 10px rgba(0,0,0,0.15);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .back-btn {
      color: #fff;
      font-size: 18px;
      text-decoration: none;
      margin-right: 14px;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      background: rgba(255,255,255,0.18);
    }
    .page-title { font-size: 18px; font-weight: 700; flex: 1; }

    .content-area {
      max-width: 460px;
      margin: 18px auto;
      padding: 0 15px;
    }

    .form-card {
      background: #ffffff;
      border-radius: 16px;
      padding: 22px 18px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.06);
      border: 1px solid #e2e8f0;
    }

    /* 1. Current USDT Rate in INR */
    .rate-box {
      background: #fff8e1;
      border: 1px solid #ffe082;
      border-radius: 12px;
      padding: 14px;
      text-align: center;
      margin-bottom: 16px;
    }
    .rate-label { font-size: 12px; font-weight: 700; color: #795548; text-transform: uppercase; letter-spacing: 0.5px; }
    .rate-value { font-size: 22px; font-weight: 800; color: #d84315; margin-top: 3px; }

    /* 2. Network */
    .network-box {
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      padding: 10px 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }
    .network-label { font-size: 14px; font-weight: 600; color: #475569; }
    .network-value { font-size: 14px; font-weight: 700; color: #0284c7; background: #e0f2fe; padding: 3px 12px; border-radius: 6px; }

    /* 3. QR */
    .qr-box {
      text-align: center;
      margin-bottom: 18px;
    }
    .qr-img {
      max-width: 170px;
      height: auto;
      border: 1px solid #cbd5e1;
      border-radius: 12px;
      padding: 6px;
      background: #ffffff;
      box-shadow: 0 3px 10px rgba(0,0,0,0.08);
      display: inline-block;
    }

    /* 4. Address */
    .address-group {
      margin-bottom: 18px;
    }
    .form-label { font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block; }
    .address-box {
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      padding: 10px 12px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .address-text { font-size: 13px; color: #0f172a; font-weight: 600; flex: 1; word-break: break-all; font-family: monospace; }
    .copy-btn {
      background: #2563eb;
      color: #ffffff;
      border: none;
      padding: 8px 14px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      white-space: nowrap;
      transition: background 0.2s;
    }
    .copy-btn:active { opacity: 0.9; }

    /* 5. Amount */
    .form-group { margin-bottom: 18px; }
    .form-input {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 600;
      color: #0f172a;
      outline: none;
      background: #ffffff;
    }
    .form-input:focus { border-color: #2563eb; }
    .calc-hint { font-size: 12px; color: #0284c7; font-weight: 600; margin-top: 5px; display: block; }

    /* 6. Upload Screenshot */
    .file-input {
      width: 100%;
      padding: 10px;
      background: #f8fafc;
      border: 1px dashed #94a3b8;
      border-radius: 10px;
      font-size: 13px;
      color: #475569;
    }

    /* 7. Submit Request */
    .submit-btn {
      width: 100%;
      background: linear-gradient(135deg, #c62828 0%, #d32f2f 100%);
      color: #ffffff;
      border: none;
      padding: 14px;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(198,40,40,0.3);
      margin-top: 8px;
    }

    .toast {
      position: fixed;
      bottom: 25px;
      left: 50%;
      transform: translateX(-50%);
      background: #15803d;
      color: #ffffff;
      padding: 8px 20px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      display: none;
      z-index: 1000;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .error-msg {
      background: #fef2f2;
      border: 1px solid #fca5a5;
      color: #991b1b;
      padding: 10px 14px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 16px;
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

  <div class="top-header">
    <a href="deposit.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="page-title">Buy <?= htmlspecialchars($cryptoName) ?></div>
  </div>

  <div class="content-area">

    <?php
function get_bonus_amount(mysqli $db, float $amount): float {
        try {
            $enabled = (int)($db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1")->fetch_row()[0] ?? 1);
            if ($enabled !== 1) return 0.0;
            $stmt = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE is_active=1 AND min_amount <= ? ORDER BY min_amount DESC LIMIT 1");
            $stmt->bind_param('d', $amount);
            $stmt->execute();
            $stmt->bind_result($bonus);
            $out = $stmt->fetch() ? (float)$bonus : 0.0;
            $stmt->close();
            return $out;
        } catch (Throwable $e) { return 0.0; }
    }
 if (!empty($flashMsg)): ?>
      <div class="error-msg"><?= htmlspecialchars($flashMsg) ?></div>
    <?php
function get_bonus_amount(mysqli $db, float $amount): float {
        try {
            $enabled = (int)($db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1")->fetch_row()[0] ?? 1);
            if ($enabled !== 1) return 0.0;
            $stmt = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE is_active=1 AND min_amount <= ? ORDER BY min_amount DESC LIMIT 1");
            $stmt->bind_param('d', $amount);
            $stmt->execute();
            $stmt->bind_result($bonus);
            $out = $stmt->fetch() ? (float)$bonus : 0.0;
            $stmt->close();
            return $out;
        } catch (Throwable $e) { return 0.0; }
    }
 endif; ?>

    <div class="form-card">
      <form method="POST" action="buy.php" enctype="multipart/form-data">

        <!-- 1. Current USDT Rate in INR -->
        <div class="rate-box">
          <div class="rate-label">Current <?= htmlspecialchars($cryptoName) ?> Rate in INR</div>
          <div class="rate-value">1 <?= htmlspecialchars($cryptoName) ?> ≈ ₹<?= number_format($cryptoRate, 2) ?></div>
        </div>

        <!-- 2. Network -->
        <div class="network-box">
          <span class="network-label">Network:</span>
          <span class="network-value"><?= htmlspecialchars($cryptoNetwork) ?></span>
        </div>

        <!-- 3. QR -->
        <?php
function get_bonus_amount(mysqli $db, float $amount): float {
        try {
            $enabled = (int)($db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1")->fetch_row()[0] ?? 1);
            if ($enabled !== 1) return 0.0;
            $stmt = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE is_active=1 AND min_amount <= ? ORDER BY min_amount DESC LIMIT 1");
            $stmt->bind_param('d', $amount);
            $stmt->execute();
            $stmt->bind_result($bonus);
            $out = $stmt->fetch() ? (float)$bonus : 0.0;
            $stmt->close();
            return $out;
        } catch (Throwable $e) { return 0.0; }
    }
 if (!empty($cryptoQr)): ?>
          <div class="qr-box">
            <img src="<?= htmlspecialchars($cryptoQr) ?>" alt="Crypto QR Code" class="qr-img">
          </div>
        <?php
function get_bonus_amount(mysqli $db, float $amount): float {
        try {
            $enabled = (int)($db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1")->fetch_row()[0] ?? 1);
            if ($enabled !== 1) return 0.0;
            $stmt = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE is_active=1 AND min_amount <= ? ORDER BY min_amount DESC LIMIT 1");
            $stmt->bind_param('d', $amount);
            $stmt->execute();
            $stmt->bind_result($bonus);
            $out = $stmt->fetch() ? (float)$bonus : 0.0;
            $stmt->close();
            return $out;
        } catch (Throwable $e) { return 0.0; }
    }
 else: ?>
          <div class="qr-box">
            <div style="font-size: 12px; color: #64748b; font-weight: 600; padding: 20px; background: #f8fafc; border-radius: 10px; border: 1px dashed #cbd5e1;">
              <i class="fa-solid fa-qrcode" style="font-size: 32px; color: #94a3b8; margin-bottom: 6px;"></i><br>
              Scan QR Code to Pay
            </div>
          </div>
        <?php
function get_bonus_amount(mysqli $db, float $amount): float {
        try {
            $enabled = (int)($db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1")->fetch_row()[0] ?? 1);
            if ($enabled !== 1) return 0.0;
            $stmt = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE is_active=1 AND min_amount <= ? ORDER BY min_amount DESC LIMIT 1");
            $stmt->bind_param('d', $amount);
            $stmt->execute();
            $stmt->bind_result($bonus);
            $out = $stmt->fetch() ? (float)$bonus : 0.0;
            $stmt->close();
            return $out;
        } catch (Throwable $e) { return 0.0; }
    }
 endif; ?>

        <!-- 4. Address -->
        <div class="address-group">
          <label class="form-label">Wallet Address:</label>
          <div class="address-box">
            <span class="address-text" id="walletAddress"><?= htmlspecialchars($cryptoAddress !== '' ? $cryptoAddress : 'Not configured') ?></span>
            <?php
function get_bonus_amount(mysqli $db, float $amount): float {
        try {
            $enabled = (int)($db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1")->fetch_row()[0] ?? 1);
            if ($enabled !== 1) return 0.0;
            $stmt = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE is_active=1 AND min_amount <= ? ORDER BY min_amount DESC LIMIT 1");
            $stmt->bind_param('d', $amount);
            $stmt->execute();
            $stmt->bind_result($bonus);
            $out = $stmt->fetch() ? (float)$bonus : 0.0;
            $stmt->close();
            return $out;
        } catch (Throwable $e) { return 0.0; }
    }
 if (!empty($cryptoAddress)): ?>
              <button type="button" class="copy-btn" onclick="copyAddress()"><i class="fa-regular fa-copy"></i> Copy</button>
            <?php
function get_bonus_amount(mysqli $db, float $amount): float {
        try {
            $enabled = (int)($db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1")->fetch_row()[0] ?? 1);
            if ($enabled !== 1) return 0.0;
            $stmt = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE is_active=1 AND min_amount <= ? ORDER BY min_amount DESC LIMIT 1");
            $stmt->bind_param('d', $amount);
            $stmt->execute();
            $stmt->bind_result($bonus);
            $out = $stmt->fetch() ? (float)$bonus : 0.0;
            $stmt->close();
            return $out;
        } catch (Throwable $e) { return 0.0; }
    }
 endif; ?>
          </div>
        </div>

        <!-- 5. Amount -->
        <div class="form-group">
          <label class="form-label">Amount (₹ INR):</label>
          <input type="number" name="amount" id="inrAmount" class="form-input" min="<?= $MIN_DEPOSIT ?>" value="<?= $MIN_DEPOSIT ?>" required placeholder="Enter amount in ₹" oninput="calcTokens()">
          <span class="calc-hint" id="tokenHint">≈ 0.00 <?= htmlspecialchars($cryptoName) ?></span>
        </div>

        <!-- 6. Upload Screenshot -->
        <div class="form-group">
          <label class="form-label">Upload Screenshot:</label>
          <input type="file" name="screenshot" accept="image/*" class="file-input">
        </div>

        <!-- 7. Submit Request -->
        <button type="submit" class="submit-btn">Submit Request</button>

      </form>
    </div>

  </div>

  <div class="toast" id="toastMsg">Address Copied!</div>

  <script>
    var cryptoRate = <?= (float)$cryptoRate ?>;
    var cryptoName = <?= json_encode($cryptoName) ?>;

    function calcTokens() {
      var inr = parseFloat(document.getElementById('inrAmount').value || 0);
      var hint = document.getElementById('tokenHint');
      if (inr > 0 && cryptoRate > 0) {
        var tokens = (inr / cryptoRate).toFixed(4);
        hint.textContent = '≈ ' + tokens + ' ' + cryptoName;
      } else {
        hint.textContent = '≈ 0.00 ' + cryptoName;
      }
    }
    calcTokens();

    function copyAddress() {
      var addrText = document.getElementById('walletAddress').innerText.trim();
      if (!addrText || addrText === 'Not configured') return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(addrText).then(function() { showToast('Address Copied!'); });
      } else {
        var ta = document.createElement('textarea');
        ta.value = addrText;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showToast('Address Copied!');
      }
    }

    function showToast(msg) {
      var toast = document.getElementById('toastMsg');
      toast.textContent = msg;
      toast.style.display = 'block';
      setTimeout(function() { toast.style.display = 'none'; }, 2000);
    }
  </script>

</body>
</html>


