<?php
/* --------------------------------------------------------------------------
   sp_motor.php – Single Pana Motor
   Enter 4+ digits (e.g. 1234) → generates all C(n,3) single pana combinations.
   Example: 1234 → 123, 124, 134, 234 (4 panas)
   --------------------------------------------------------------------------*/
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$market = strtoupper($_GET['market'] ?? '');
if ($market === '')
    $market = 'WELCOME';
$market = substr($market, 0, 20);
$marketLabel = ucfirst(strtolower($market));

require_once 'disable_enable.php';

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

$stmt = $db->prepare("SELECT user_id FROM users WHERE id=? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$stmt->bind_result($LLCode);
$stmt->fetch();
$stmt->close();
if (empty($LLCode))
    die('User code missing');

function compute_balances(mysqli $db, string $code): array
{
    $tot = get_total_balance($db, $code);
    return [$tot, 0.0];
}

[$walletBalance, $winBalance] = compute_balances($db, $LLCode);
$totalBalance = $walletBalance + $winBalance;
$minBid = getMinBid($db);

$marketClosed = ($openDisabled && $closeDisabled) || ($pageDisabled ?? false);

if (empty($_SESSION['bid_nonce_sp_motor'])) {
    $_SESSION['bid_nonce_sp_motor'] = bin2hex(random_bytes(16));
}
$nonce = $_SESSION['bid_nonce_sp_motor'];

/* Handle POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bids'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($marketClosed) {
        echo json_encode(['ok' => false, 'message' => 'Bidding closed']);
        exit;
    }

    $clientNonce = (string) ($_POST['nonce'] ?? '');
    if ($clientNonce === '' || !hash_equals($_SESSION['bid_nonce_sp_motor'] ?? '', $clientNonce)) {
        echo json_encode(['ok' => false, 'message' => 'Invalid or duplicate request']);
        exit;
    }

    $bids = json_decode($_POST['bids'], true);
    if (!is_array($bids) || empty($bids)) {
        echo json_encode(['ok' => false, 'message' => 'No bids']);
        exit;
    }

    try {
        $db = LL_db();
        $secureCtx = vardhan_secure_bid_context($db, $market);
        if (empty($secureCtx['ok'])) {
            throw new Exception($secureCtx['message'] ?? 'Bidding closed');
        }
        $bidNow = $secureCtx['now_sql'];
        $db->begin_transaction();
        [$w2, $wn2] = compute_balances($db, $LLCode);
        $available = $w2 + $wn2;
        $totalAmt = 0;
        foreach ($bids as $b) {
            $amt = (int) ($b['amount'] ?? 0);
            if ($amt < $minBid) continue;
            $totalAmt += $amt;
        }
        if ($totalAmt > $available + 0.0001)
            throw new Exception('Insufficient balance');

        $ins = $db->prepare("INSERT INTO bid(user_id, market, session_type, single_pana, amount, bid_time) VALUES(?,?,?,?,?,?)");
        foreach ($bids as $b) {
            $sessionCheck = vardhan_secure_session($secureCtx, (string) ($b['session'] ?? ''));
            if (empty($sessionCheck['ok'])) {
                throw new Exception($sessionCheck['message'] ?? 'Bidding closed');
            }
            $sess = $sessionCheck['session'];
            $num = (string) $b['number'];
            $amt = (int) $b['amount'];
            if ($amt < $minBid)
                continue;
            $ins->bind_param('ssssis', $LLCode, $market, $sess, $num, $amt, $bidNow);
            $ins->execute();
        }
        $ins->close();

        $wd = $db->prepare("INSERT INTO withdrawal(user_id, amount, status, method, date_time) VALUES(?,?,'Done','game_bid',?)");
        $dAmt = (float) $totalAmt;
        $wd->bind_param('sds', $LLCode, $dAmt, $bidNow);
        $wd->execute();
        $wd->close();

        $db->commit();
        $_SESSION['bid_nonce_sp_motor'] = bin2hex(random_bytes(16));
        echo json_encode(['ok' => true]);
        exit;
    } catch (Throwable $e) {
        if ($db)
            $db->rollback();
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
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
  <title><?= htmlspecialchars($marketLabel) ?> - SP Motor Dashboard</title>
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

    .container {
      max-width: 600px;
      margin: 0 auto;
      padding: 0 0 20px;
    }

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
      font-size: 14px;
      padding: 10px 16px;
      width: 100%;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(255, 59, 48, 0.35);
      transition: transform 0.15s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .add-more-btn:active {
      transform: scale(0.96);
    }

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
      <div style="font-size: 11px; font-weight: 700; color: #ffcdd2; text-transform: uppercase; margin-top: 2px; letter-spacing: 0.5px;"><?= __("SP Motor") ?></div>
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
        <div class="form-label">SP MOTOR</div>
        <input type="tel" id="motorInput" class="form-input" placeholder="ENTER MOTOR (min 4 digits)" maxlength="10">
      </div>
      <div class="form-row">
        <div class="form-label">POINTS</div>
        <input type="tel" id="ptsInput" class="form-input" placeholder="Enter Points" maxlength="6">
      </div>
      <div class="form-row" style="margin-bottom: 0;">
        <div></div>
        <button type="button" id="addBidBtn" class="add-more-btn">
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
      <div class="bids-list" id="bidList">
        <!-- Added rows will appear here -->
      </div>
    </div>
  </div>

  <!-- Bottom Submit Bar -->
  <div class="bottom-bar">
    <button type="button" id="submitBtn" class="submit-btn"><?= __("Submit") ?></button>
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
    var wallet = <?= json_encode((float) $totalBalance) ?>;
    var nonce = <?= json_encode($nonce) ?>;
    var isClosed = <?= json_encode($marketClosed) ?>;
    var minBid = <?= (int) $minBid ?>;

    var motorInput = document.getElementById('motorInput');
    var ptsInput = document.getElementById('ptsInput');
    var addBidBtn = document.getElementById('addBidBtn');
    var bidListEl = document.getElementById('bidList');
    var submitBtn = document.getElementById('submitBtn');
    var successSound = document.getElementById('successSound');

    var bidsList = [];

    function showMsg(msg) {
      document.getElementById('popupMsg').textContent = msg;
      document.getElementById('popup').style.display = 'flex';
    }

    if (motorInput) motorInput.addEventListener('input', function () { this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10); });
    if (ptsInput) ptsInput.addEventListener('input', function () { this.value = this.value.replace(/[^0-9]/g, ''); });

    function getSelectedSession() {
      var radios = document.getElementsByName('gameSession');
      for (var i = 0; i < radios.length; i++) {
        if (radios[i].checked) return radios[i].value;
      }
      return 'Open';
    }

    // Sort pana: non-zero digits ascending, zeros at end
    function sortPana(a, b, c) {
      var arr = [a, b, c];
      arr.sort(function(x, y) {
        if (x === 0) return 1;
        if (y === 0) return -1;
        return x - y;
      });
      return arr.join('');
    }

    // Generate all C(n,3) combinations from unique digits
    function getSPForMotor(motorStr) {
      var digits = [...new Set(motorStr.split('').map(Number))];
      if (digits.length < 3) return [];

      var panas = [];
      for (var i = 0; i < digits.length; i++) {
        for (var j = i + 1; j < digits.length; j++) {
          for (var k = j + 1; k < digits.length; k++) {
            var pana = sortPana(digits[i], digits[j], digits[k]);
            if (!panas.includes(pana)) {
              panas.push(pana);
            }
          }
        }
      }
      return panas;
    }

    function renderBids() {
      bidListEl.innerHTML = '';
      bidsList.forEach(function(item, idx) {
        var row = document.createElement('div');
        row.className = 'bid-row';
        row.innerHTML = '<div>' + item.pana + '</div>' +
                        '<div>' + item.points + '</div>' +
                        '<div>' + item.session + '</div>' +
                        '<div><button type="button" class="delete-btn" onclick="removeBid(' + idx + ')"><i class="fa-solid fa-trash"></i></button></div>';
        bidListEl.appendChild(row);
      });

      if (bidsList.length > 0) {
        submitBtn.classList.add('active');
      } else {
        submitBtn.classList.remove('active');
      }
    }

    function removeBid(idx) {
      bidsList.splice(idx, 1);
      renderBids();
    }

    addBidBtn.addEventListener('click', function () {
      if (isClosed) return showMsg('Market is closed for the day.');

      var motor = motorInput.value.trim();
      var points = parseInt(ptsInput.value, 10) || 0;
      var session = getSelectedSession();

      var uniqueDigits = [...new Set(motor.split('').filter(d => d >= '0' && d <= '9'))];
      if (motor.length < 4 || uniqueDigits.length < 4) {
        showMsg('Minimum 4 different digits required');
        motorInput.focus();
        return;
      }

      if (points < minBid) {
        showMsg('Minimum points ' + minBid + ' required');
        ptsInput.focus();
        return;
      }

      if (!session) return showMsg('Session not available');

      var relatedPanas = getSPForMotor(motor);
      if (relatedPanas.length === 0) return showMsg('No panas found for this motor');

      var currentTotal = bidsList.reduce((a, b) => a + b.points, 0);
      var potentialIncrease = relatedPanas.length * points;

      if (currentTotal + potentialIncrease > wallet) {
        return showMsg('Insufficient wallet balance!');
      }

      relatedPanas.forEach(function(pana) {
        var existing = bidsList.find(b => b.pana === pana && b.session === session);
        if (existing) {
          existing.points += points;
        } else {
          bidsList.push({ pana: pana, points: points, session: session });
        }
      });

      motorInput.value = '';
      ptsInput.value = '';
      motorInput.focus();

      renderBids();
    });

    submitBtn.addEventListener('click', async function() {
      if (isClosed) return showMsg('Market is closed for the day.');
      if (bidsList.length === 0) return showMsg('Please add at least one bid to the list.');
      var total = bidsList.reduce((a, b) => a + b.points, 0);
      if (total > wallet) return showMsg('Insufficient wallet balance!');

      submitBtn.disabled = true;
      var ogHtml = submitBtn.innerHTML;
      submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing...';

      try {
        var payload = bidsList.map(b => ({ number: b.pana, amount: b.points, session: b.session }));
        var fd = new FormData();
        fd.append('bids', JSON.stringify(payload));
        fd.append('nonce', nonce);
        var res = await fetch('', { method: 'POST', body: fd });
        var data = await res.json();
        if (data.ok) {
          if (successSound) {
            successSound.currentTime = 0;
            successSound.play().catch(function() { });
          }
          document.getElementById('successOverlay').style.display = 'flex';
          setTimeout(function() { return location.reload(); }, 1500);
        } else {
          showMsg(data.message || data.msg || 'Error occurred');
          submitBtn.disabled = false;
          submitBtn.innerHTML = ogHtml;
        }
      } catch (e) {
        showMsg('Network error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = ogHtml;
      }
    });
  </script>
</body>

</html>


