<?php
/* ----------------------------------------------------------
   single_digit.php – Enter single-digit bids (Open / Close)
   ✅ FIX: Success only after DB insert confirmed (AJAX + transaction)
-----------------------------------------------------------*/
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* 0. Market from URL – support all DB markets */
$market = strtoupper($_GET['market'] ?? '');
if ($market === '')
  $market = 'WELCOME';
$marketLabel = ucfirst(strtolower($market));

/* Timing helper → $openDisabled, $closeDisabled, $pageDisabled … */
require_once 'disable_enable.php';
$defaultSession = !$openDisabled ? 'Open' : (!$closeDisabled ? 'Close' : 'Open');

/* 1. User & balance ------------------------------------------------------- */
require_once 'includes/user_ping.php';
require_once 'includes/db.php';
require_once 'includes/lang.php';
require_once 'includes/secure_bid_time.php';

function LL_db(): mysqli
{
  return db();
}

$uid = (int) ($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) {
  header('Location: user_login.php');
  exit;
}

$db = LL_db();

/* Royal-King user code */
$stmt = $db->prepare("SELECT user_id FROM users WHERE id=? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$stmt->bind_result($LLCode);
$stmt->fetch();
$stmt->close();

if (empty($LLCode)) {
  // $db->close();
  die('User code missing');
}

/* total balance */
$totalBalance = current_user_wallet($db ?? null);

/* Nonce (duplicate submit protection) */
if (empty($_SESSION['bid_nonce'])) {
  $_SESSION['bid_nonce'] = bin2hex(random_bytes(16));
}
$nonce = $_SESSION['bid_nonce'];
// $db->close();

$minBid = getMinBid($db);
/* 2. Handle bid submission (AJAX JSON) ----------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bids'])) {

  header('Content-Type: application/json; charset=utf-8');

  // page locked
  // nonce check
  $clientNonce = (string) ($_POST['nonce'] ?? '');
  if ($clientNonce === '' || !hash_equals($_SESSION['bid_nonce'] ?? '', $clientNonce)) {
    echo json_encode(['ok' => false, 'message' => 'Duplicate / invalid request']);
    exit;
  }

  $bids = json_decode($_POST['bids'], true);
  if (!is_array($bids) || empty($bids)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid payload']);
    exit;
  }

  $secureCtx = vardhan_secure_bid_context($db, $market);
  if (empty($secureCtx['ok'])) {
    echo json_encode(['ok' => false, 'message' => $secureCtx['message'] ?? 'Bidding closed']);
    exit;
  }

  // Recreate LLCode + balance quickly (safe)
  try {
    $db = LL_db();

    $stmt = $db->prepare("SELECT user_id FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->bind_result($LLCode2);
    $stmt->fetch();
    $stmt->close();

    if (empty($LLCode2)) {
      // $db->close();
      echo json_encode(['ok' => false, 'message' => 'User code missing']);
      exit;
    }

    $total = 0;
    foreach ($bids as $r) {
      $amt = (int) ($r['amount'] ?? 0);
      $num = (int) ($r['number'] ?? -1);
      $ses = (string) ($r['session'] ?? '');
      if ($num < 0 || $num > 9) {
        // $db->close();
        echo json_encode(['ok' => false, 'message' => 'Invalid digit']);
        exit;
      }
      if ($amt < $minBid) {
        // $db->close();
        echo json_encode(['ok' => false, 'message' => 'Minimum bid ₹' . $minBid]);
        exit;
      }
      if ($ses !== 'Open' && $ses !== 'Close') {
        // $db->close();
        echo json_encode(['ok' => false, 'message' => 'Invalid choice']);
        exit;
      }
      $sessionCheck = vardhan_secure_session($secureCtx, $ses);
      if (empty($sessionCheck['ok'])) {
        echo json_encode(['ok' => false, 'message' => $sessionCheck['message'] ?? 'Bidding closed']);
        exit;
      }
      $total += $amt;
    }

    // balance check (same formula)
    $available = get_total_balance($db, $LLCode2);
    if ($total > $available) {
      // $db->close();
      echo json_encode(['ok' => false, 'message' => 'Insufficient balance']);
      exit;
    }

    // ✅ TRANSACTION: bid insert + withdrawal together
    $db->begin_transaction();

    $ins = $db->prepare("
            INSERT INTO bid(
                user_id, market, session_type,
                single_digit, jodi_digit, single_pana, double_pana, triple_pana,
                amount, bid_time
            )
            VALUES(?,?,?,?,NULL,NULL,NULL,NULL,?,?)
        ");

    $inserted = 0;
    $bidNow = $secureCtx['now_sql'];
    foreach ($bids as $r) {
      $digit = (int) $r['number'];
      $amount = (int) $r['amount'];
      $sessionCheck = vardhan_secure_session($secureCtx, (string) ($r['session'] ?? ''));
      if (empty($sessionCheck['ok'])) {
        throw new Exception($sessionCheck['message'] ?? 'Bidding closed');
      }
      $sess = $sessionCheck['session'];

      // bind: user_id(s), market(s), session_type(s), single_digit(i), amount(i)
      $ins->bind_param('sssiis', $LLCode2, $market, $sess, $digit, $amount, $bidNow);
      $ins->execute();
      $inserted += $ins->affected_rows;
    }
    $ins->close();

    if ($inserted <= 0) {
      throw new Exception('Bid insert failed');
    }

    // wallet deduction entry
    $wd = $db->prepare("
            INSERT INTO withdrawal(user_id, amount, status, method, date_time)
            VALUES(?, ?, 'Done', 'game_bid', ?)
        ");
    $amtD = (float) $total;
    $wd->bind_param('sds', $LLCode2, $amtD, $bidNow);
    $wd->execute();
    $wd->close();

    $db->commit();

    // invalidate nonce so double click won't repeat
    $_SESSION['bid_nonce'] = bin2hex(random_bytes(16));
    // $db->close();
    echo json_encode(['ok' => true]);
    exit;

  } catch (Throwable $e) {
    if (isset($db) && $db instanceof mysqli) {
      try {
        $db->rollback();
      } catch (Throwable $x) {
      }
      try {
        // $db->close();
      } catch (Throwable $x) {
      }
    }
    // IMPORTANT: hide internal SQL details in production
    echo json_encode(['ok' => false, 'message' => 'DB insert failed. Please try again.']);
    exit;
  }
}
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
  <meta name="theme-color" content="#070b14">
  <title><?= htmlspecialchars($marketLabel) ?> - Single Digit Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.lordicon.com/lusqsztk.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
      -webkit-tap-highlight-color: transparent;
    }

    html {
      background-color: #000000 !important;
      background: #000000 !important;
    }

    body {
      background-color: #000000 !important;
      background: #000000 !important;
      color: #333333;
      min-height: 100vh;
      padding-bottom: 90px;
    }

    /* Header Bar */
    .header-bar {
      min-height: 65px;
      height: auto;
      background-color: #000000 !important;
      background-image: none !important;
      padding-top: calc(env(safe-area-inset-top, 0px) + 12px);
      padding-bottom: 12px;
      padding-left: 14px;
      padding-right: 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
      border-bottom: 1px solid #1a1a1a;
    }

    .back-btn {
      width: 38px;
      height: 38px;
      border-radius: 8px;
      background: #e53935;
      color: #ffffff !important;
      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      font-size: 16px;
      border: none;
      cursor: pointer;
      flex-shrink: 0;
      transition: transform 0.15s ease;
    }

    .back-btn:active {
      transform: scale(0.92);
    }

    .page-title {
      font-size: 15px;
      font-weight: 800;
      text-transform: uppercase;
      color: #ffffff;
      letter-spacing: 0.5px;
      flex: 1;
      text-align: center;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin: 0 8px;
    }

    .wallet-btn {
      display: flex;
      align-items: center;
      gap: 6px;
      background: #e53935;
      border-radius: 8px;
      padding: 6px 12px;
      font-weight: 800;
      font-size: 13px;
      color: #ffffff;
      text-decoration: none;
      flex-shrink: 0;
      border: none;
    }

    /* Container */
    .container {
      max-width: 600px;
      margin: 0 auto;
      padding: 0 0 20px;
    }

    /* Date & Bet Banner */
    .date-banner {
      background: #ffe4cb;
      padding: 10px 14px;
      text-align: center;
      font-weight: 800;
      font-size: 13px;
      color: #1e1e1e;
      margin-bottom: 12px;
      letter-spacing: 0.3px;
    }

    /* Select Game Type Card */
    .select-game-card {
      background: #ffffff;
      border: 1.5px solid #c62828;
      border-radius: 12px;
      padding: 10px 16px;
      margin: 0 14px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .select-game-title {
      font-weight: 800;
      font-size: 13px;
      color: #1a202c;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .radio-group {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .radio-label {
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: 800;
      font-size: 14px;
      color: #8b0000;
      cursor: pointer;
    }

    .radio-label input[type="radio"] {
      width: 18px;
      height: 18px;
      accent-color: #8b0000;
      cursor: pointer;
    }

    /* Input Form Card */
    .input-form-card {
      background: linear-gradient(180deg, #ff8a8a 0%, #ffb3b3 35%, #ffffff 100%);
      margin: 0 0 16px;
      padding: 18px 16px 14px;
      box-shadow: none;
    }

    .form-row {
      display: grid;
      grid-template-columns: 130px 1fr;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
    }

    .form-label {
      font-weight: 800;
      font-size: 14px;
      color: #1a202c;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .form-input {
      background: #f0f4f8;
      border: 1.5px solid transparent;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 14px;
      font-weight: 700;
      color: #2d3748;
      outline: none;
      width: 100%;
      transition: border-color 0.15s ease, background 0.15s ease;
    }

    .form-input:focus {
      background: #ffffff;
      border-color: #e53935;
    }

    .form-input::placeholder {
      color: #718096;
      font-weight: 500;
    }

    .add-more-btn {
      background: #ff3b30;
      color: #ffffff;
      border: none;
      border-radius: 10px;
      font-weight: 800;
      font-size: 15px;
      padding: 12px;
      width: 100%;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(255, 59, 48, 0.35);
      transition: transform 0.15s ease, background 0.15s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      margin-top: 6px;
    }

    .add-more-btn:active {
      transform: scale(0.97);
    }

    /* Bids List Table */
    .bids-table-container {
      margin: 0 14px 20px;
      background: #ffffff;
      border-radius: 12px;
      overflow: hidden;
    }

    .bids-table-header {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr 40px;
      padding: 12px 16px;
      background: #ffffff;
      border-bottom: 1px solid #edf2f7;
      font-weight: 800;
      font-size: 14px;
      color: #1a202c;
      text-align: center;
    }

    .bids-list {
      max-height: 250px;
      overflow-y: auto;
    }

    .bid-row {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr 40px;
      padding: 10px 16px;
      align-items: center;
      border-bottom: 1px solid #f7fafc;
      font-size: 14px;
      font-weight: 700;
      color: #2d3748;
      text-align: center;
    }

    .delete-btn {
      color: #e53935;
      background: transparent;
      border: none;
      font-size: 16px;
      cursor: pointer;
    }

    /* Bottom Fixed Submit Bar */
    .bottom-bar {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background-color: #000000 !important;
      padding: 8px 14px;
      border-top: 1px solid #1a1a1a;
      box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.5);
      z-index: 1000;
      display: flex;
      justify-content: flex-end;
      align-items: center;
    }

    .submit-btn {
      background: #ff8a80;
      color: #ffffff;
      border: none;
      border-radius: 8px;
      font-weight: 800;
      font-size: 14px;
      padding: 7px 32px;
      cursor: pointer;
      box-shadow: 0 3px 10px rgba(255, 138, 128, 0.35);
      transition: background 0.2s ease, transform 0.15s ease;
    }

    .submit-btn.active {
      background: #ff3b30;
      box-shadow: 0 4px 14px rgba(255, 59, 48, 0.4);
    }

    .submit-btn:disabled {
      opacity: 0.65;
      cursor: not-allowed;
    }

    /* Modal Overlay */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(3px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 5000;
    }

    .modal-box {
      background: #ffffff;
      width: 80%;
      max-width: 290px;
      border-radius: 14px;
      padding: 20px 18px 16px;
      text-align: center;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    }

    .modal-msg {
      font-size: 14px;
      font-weight: 700;
      color: #1e1e1e;
      margin-bottom: 14px;
      line-height: 1.4;
    }

    .modal-btn {
      background: #8b0000;
      color: #ffffff;
      border: none;
      padding: 6px 26px;
      border-radius: 6px;
      font-weight: 700;
      font-size: 14px;
      cursor: pointer;
      display: inline-block;
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
  <header class="header-bar">
    <a href="javascript:history.back()" class="back-btn" title="Back">
      <i class="fa-solid fa-chevron-left"></i>
    </a>
    <div style="text-align: center; flex: 1; margin: 0 8px; overflow: hidden;">
      <div style="font-size: 15px; font-weight: 800; text-transform: uppercase; color: #ffffff; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($marketLabel) ?></div>
      <div style="font-size: 11px; font-weight: 700; color: #ffcdd2; text-transform: uppercase; margin-top: 2px; letter-spacing: 0.5px;"><?= __("Single Digit") ?></div>
    </div>
    <a href="deposit.php" class="wallet-btn">
      <i class="fa-solid fa-wallet"></i>
      <span><?= number_format($totalBalance, 0) ?></span>
    </a>
  </header>

  <div class="container">
    <!-- Date & Status Banner -->
    <div class="date-banner">
      <?= date('d M Y - l') ?> - <?= ($openDisabled && $closeDisabled) ? 'BET CLOSED' : 'BET OPEN' ?>
    </div>

    <!-- Select Game Type -->
    <div class="select-game-card">
      <div class="select-game-title">SELECT GAME TYPE:</div>
      <div class="radio-group">
        <?php if (!$openDisabled): ?>
          <label class="radio-label">
            <input type="radio" name="gameSession" value="Open" checked> OPEN
          </label>
        <?php endif; ?>
        <?php if (!$closeDisabled): ?>
          <label class="radio-label">
            <input type="radio" name="gameSession" value="Close" <?= $openDisabled ? 'checked' : '' ?>> CLOSE
          </label>
        <?php endif; ?>
        <?php if ($openDisabled && $closeDisabled): ?>
          <span style="color:#c62828; font-weight:800; font-size:13px;">CLOSED</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Input Form Card -->
    <div class="input-form-card">
      <div class="form-row">
        <div class="form-label">SINGLE DIGIT</div>
        <input type="tel" id="digitInput" class="form-input" placeholder="Enter Single Digit" maxlength="1">
      </div>
      <div class="form-row">
        <div class="form-label">POINTS</div>
        <input type="tel" id="pointsInput" class="form-input" placeholder="Enter Points" maxlength="6">
      </div>
      <div class="form-row" style="margin-bottom: 0;">
        <div></div>
        <button type="button" id="addMoreBtn" class="add-more-btn">
          Add More +
        </button>
      </div>
    </div>

    <!-- Added Bids Table -->
    <div class="bids-table-container">
      <div class="bids-table-header">
        <div>Digit</div>
        <div>Points</div>
        <div>Game Type</div>
        <div></div>
      </div>
      <div class="bids-list" id="bidsList">
        <!-- Added rows will appear here -->
      </div>
    </div>
  </div>

  <!-- Bottom Submit Bar -->
  <div class="bottom-bar">
    <button type="button" id="submitBtn" class="submit-btn">Submit</button>
  </div>

  <!-- Alert Modal -->
  <div class="modal-overlay" id="popup">
    <div class="modal-box">
      <div class="modal-msg" id="popupMsg"></div>
      <button class="modal-btn" type="button" onclick="document.getElementById('popup').style.display='none'"><?= __("OK") ?></button>
    </div>
  </div>

  <!-- Success Modal -->
  <style>
    .success-tick-wrapper {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: linear-gradient(135deg, #2e7d32, #4caf50);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 10px 25px rgba(76, 175, 80, 0.4), 0 0 0 10px rgba(76, 175, 80, 0.15);
      animation: popSuccess 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }
    @keyframes popSuccess {
      0% { transform: scale(0); opacity: 0; }
      80% { transform: scale(1.1); }
      100% { transform: scale(1); opacity: 1; }
    }
    .checkmark-svg {
      width: 55px;
      height: 55px;
      stroke: #ffffff;
      stroke-width: 4;
      stroke-linecap: round;
      stroke-linejoin: round;
      fill: none;
      stroke-dasharray: 100;
      stroke-dashoffset: 100;
      animation: drawCheck 0.6s ease-in-out 0.2s forwards;
    }
    @keyframes drawCheck {
      to { stroke-dashoffset: 0; }
    }
  </style>
  <div class="modal-overlay" id="successOverlay" style="background: rgba(255,255,255,0.96); flex-direction: column; z-index: 9999;">
    <div class="success-tick-wrapper">
      <svg class="checkmark-svg" viewBox="0 0 52 52">
        <path d="M14 27 l10 10 l20 -20" />
      </svg>
    </div>
    <div style="font-size:22px; font-weight:800; color:#1b5e20; margin-top:20px; letter-spacing:0.5px;"><?= __("Bid Submitted!") ?></div>
    <div style="font-size:14px; color:#555; margin-top:6px; font-weight:500;"><?= __("Your bid has been placed successfully.") ?></div>
  </div>

  <audio id="successSound" src="sound/done.mp3" preload="auto"></audio>

  <script>
    var nonce = <?= json_encode($nonce) ?>;
    var wallet = <?= json_encode((float) $totalBalance) ?>;
    var isClosed = <?= json_encode($openDisabled && $closeDisabled) ?>;
    var minBid = <?= (int) $minBid ?>;

    var bids = [];
    var digitInput = document.getElementById('digitInput');
    var pointsInput = document.getElementById('pointsInput');
    var addMoreBtn = document.getElementById('addMoreBtn');
    var bidsList = document.getElementById('bidsList');
    var submitBtn = document.getElementById('submitBtn');
    var successSound = document.getElementById('successSound');

    function showMsg(msg) {
      document.getElementById('popupMsg').textContent = msg;
      document.getElementById('popup').style.display = 'flex';
    }

    digitInput.addEventListener('input', function() {
      this.value = this.value.replace(/[^0-9]/g, '');
    });

    pointsInput.addEventListener('input', function() {
      this.value = this.value.replace(/[^0-9]/g, '');
    });

    function getSelectedSession() {
      var radios = document.getElementsByName('gameSession');
      for (var i = 0; i < radios.length; i++) {
        if (radios[i].checked) return radios[i].value;
      }
      return '';
    }

    function renderBids() {
      bidsList.innerHTML = '';
      bids.forEach(function(item, idx) {
        var row = document.createElement('div');
        row.className = 'bid-row';
        row.innerHTML = '<div>' + item.number + '</div>' +
                        '<div>' + item.amount + '</div>' +
                        '<div>' + item.session + '</div>' +
                        '<div><button type="button" class="delete-btn" onclick="removeBid(' + idx + ')"><i class="fa-solid fa-trash"></i></button></div>';
        bidsList.appendChild(row);
      });

      if (bids.length > 0) {
        submitBtn.classList.add('active');
      } else {
        submitBtn.classList.remove('active');
      }
    }

    function removeBid(idx) {
      bids.splice(idx, 1);
      renderBids();
    }

    addMoreBtn.addEventListener('click', function() {
      if (isClosed) return showMsg('Market is closed for the day.');

      var sess = getSelectedSession();
      if (!sess) return showMsg('Please select Open or Close session.');

      var digitVal = digitInput.value.trim();
      var pointsVal = parseInt(pointsInput.value.trim(), 10) || 0;

      if (digitVal === '' || digitVal < 0 || digitVal > 9) {
        return showMsg('Please enter a valid single digit (0-9).');
      }

      if (pointsVal < minBid) {
        return showMsg('Minimum bid amount is ₹' + minBid + ' per digit.');
      }

      var currentTotal = bids.reduce(function(acc, item) { return acc + item.amount; }, 0);
      if (currentTotal + pointsVal > wallet) {
        return showMsg('Insufficient wallet balance!');
      }

      bids.push({
        session: sess,
        number: parseInt(digitVal, 10),
        amount: pointsVal
      });

      digitInput.value = '';
      pointsInput.value = '';
      digitInput.focus();

      renderBids();
    });

    submitBtn.addEventListener('click', async function() {
      if (isClosed) return showMsg('Market is closed for the day.');
      if (bids.length === 0) return showMsg('Please add at least one bid using Add More + button.');

      var totalAmt = bids.reduce(function(acc, item) { return acc + item.amount; }, 0);
      if (totalAmt > wallet) return showMsg('Insufficient wallet balance!');

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing...';

      try {
        var fd = new FormData();
        fd.append('bids', JSON.stringify(bids));
        fd.append('nonce', nonce);

        var res = await fetch(window.location.href, {
          method: 'POST',
          body: fd,
          headers: { 'X-Requested-With': 'fetch' }
        });

        var data = await res.json();

        if (data.ok) {
          if (successSound) {
            successSound.currentTime = 0;
            successSound.play().catch(function() { });
          }
          document.getElementById('successOverlay').style.display = 'flex';
          setTimeout(function() { return location.reload(); }, 1500);
        } else {
          showMsg(data.message || 'Submission failed.');
          submitBtn.disabled = false;
          submitBtn.innerHTML = 'Submit';
        }
      } catch (e) {
        showMsg('Connection error. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Submit';
      }
    });
  </script>
</body>
</html>
