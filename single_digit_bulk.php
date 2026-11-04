<?php
/* --------------------------------------------------------------------------
   single_digit_bulk.php – Bulk entry for single-digit bids
   Logic: Enter points once -> tap digit buttons to accumulate bids.
   --------------------------------------------------------------------------*/
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* 0. Market from URL */
$market = strtoupper($_GET['market'] ?? '');
if ($market === '')
    $market = 'WELCOME';
$marketLabel = ucfirst(strtolower($market));

/* Timing helper */
require_once 'disable_enable.php';
$defaultSession = !$openDisabled ? 'Open' : (!$closeDisabled ? 'Close' : 'Open');

/* 1. User & balance */
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

/* Fetch User Code */
$stmt = $db->prepare("SELECT user_id FROM users WHERE id=? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$stmt->bind_result($LLCode);
$stmt->fetch();
$stmt->close();

if (empty($LLCode)) {
    die('User code missing');
}

/* Wallet and Win Balance */
function compute_balances(mysqli $db, string $code): array
{
    $tot = get_total_balance($db, $code);
    return [$tot, 0.0];
}

[$walletBalance, $winBalance] = compute_balances($db, $LLCode);
$totalBalance = $walletBalance + $winBalance;
$minBid = getMinBid($db);

/* Nonce for safety */
if (empty($_SESSION['bid_nonce_bulk'])) {
    $_SESSION['bid_nonce_bulk'] = bin2hex(random_bytes(16));
}
$nonce = $_SESSION['bid_nonce_bulk'];

/* 2. Handle POST (AJAX) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bids'])) {
    header('Content-Type: application/json; charset=utf-8');
    $clientNonce = (string) ($_POST['nonce'] ?? '');
    if ($clientNonce === '' || !hash_equals($_SESSION['bid_nonce_bulk'] ?? '', $clientNonce)) {
        echo json_encode(['ok' => false, 'message' => 'Invalid or duplicate request']);
        exit;
    }

    $bids = json_decode($_POST['bids'], true);
    if (!is_array($bids) || empty($bids)) {
        echo json_encode(['ok' => false, 'message' => 'No bids provided']);
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

        /* Re-verify balance */
        [$w2, $wn2] = compute_balances($db, $LLCode);
        $available = $w2 + $wn2;

        $totalAmt = 0;
        foreach ($bids as $b) {
            $amt = (int) ($b['amount'] ?? 0);
            if ($amt < $minBid) continue;
            $totalAmt += $amt;
        }

        if ($totalAmt > $available) {
            throw new Exception('Insufficient balance');
        }

        /* Insert Bids */
        $ins = $db->prepare("
            INSERT INTO bid(
                user_id, market, session_type, single_digit, amount, bid_time
            ) VALUES(?, ?, ?, ?, ?, ?)
        ");

        foreach ($bids as $b) {
            $sessionCheck = vardhan_secure_session($secureCtx, (string) ($b['session'] ?? ''));
            if (empty($sessionCheck['ok'])) {
                throw new Exception($sessionCheck['message'] ?? 'Bidding closed');
            }
            $sess = $sessionCheck['session'];
            $num = (int) $b['number'];
            $amt = (int) $b['amount'];
            if ($amt < $minBid)
                continue;

            $ins->bind_param('sssiis', $LLCode, $market, $sess, $num, $amt, $bidNow);
            $ins->execute();
        }
        $ins->close();

        /* Deduct Balance (via withdrawal entry) */
        $wd = $db->prepare("INSERT INTO withdrawal(user_id, amount, status, method, date_time) VALUES(?, ?, 'Done', 'game_bid', ?)");
        $dAmt = (float) $totalAmt;
        $wd->bind_param('sds', $LLCode, $dAmt, $bidNow);
        $wd->execute();
        $wd->close();

        $db->commit();
        $_SESSION['bid_nonce_bulk'] = bin2hex(random_bytes(16));
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
  <title><?= htmlspecialchars($marketLabel) ?> - Single Digit Bulk</title>
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

    html { background-color: #000000 !important; background: #000000 !important; }

    body {
      background-color: #000000 !important; background: #000000 !important;
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
      padding: 18px 16px 20px;
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

    /* Keypad Layout */
    .keypad-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-top: 10px;
    }

    .key-btn {
      background: #ffffff;
      border: 1.5px solid #ff5252;
      border-radius: 10px;
      height: 60px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
      cursor: pointer;
      user-select: none;
      transition: transform 0.1s ease, background 0.15s ease;
      border-style: solid;
    }

    .key-btn:active {
      transform: scale(0.93);
      background: #ffebee;
    }

    .key-digit {
      font-size: 20px;
      font-weight: 800;
      color: #1e1e1e;
      line-height: 1;
    }

    .key-amt {
      font-size: 11px;
      font-weight: 800;
      color: #e53935;
      margin-top: 2px;
      height: 14px;
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
      <div style="font-size: 11px; font-weight: 700; color: #ffcdd2; text-transform: uppercase; margin-top: 2px; letter-spacing: 0.5px;"><?= __("Single Digit Bulk") ?></div>
    </div>
    <a href="deposit.php" class="wallet-btn">
      <i class="fa-solid fa-wallet"></i>
      <span id="walletAmt"><?= number_format($totalBalance, 0) ?></span>
    </a>
  </header>

  <div class="container">
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

    <!-- Enter Points First -->
    <div class="input-form-card">
      <div class="form-row">
        <div class="form-label">POINTS</div>
        <input type="tel" id="pointsInput" class="form-input" placeholder="Enter Points" maxlength="6">
      </div>
      
      <!-- Keypad -->
      <div class="keypad-grid">
        <?php for($i=0; $i<=9; $i++): ?>
        <div class="key-btn" data-digit="<?= $i ?>">
          <div class="key-digit"><?= $i ?></div>
          <div class="key-amt" id="amt-<?= $i ?>"></div>
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Added Bids -->
    <div class="bids-table-container">
      <div class="bids-table-header">
        <div>Digit</div>
        <div>Points</div>
        <div>Type</div>
        <div></div>
      </div>
      <div class="bids-list" id="bidsList"></div>
    </div>
  </div>

  <div class="bottom-bar">
    <button type="button" id="submitBtn" class="submit-btn" style="background:#ff8a80; color:#fff; border:none; border-radius:8px; font-weight:800; font-size:14px; padding:7px 32px; box-shadow:0 3px 10px rgba(255,138,128,0.35);">Submit</button>
  </div>

  <!-- Popups -->
  <div class="modal-overlay" id="popup" style="position:fixed; inset:0; background:rgba(0,0,0,0.6); display:none; align-items:center; justify-content:center; z-index:5000;">
    <div class="modal-box" style="background:#fff; width:80%; max-width:290px; border-radius:14px; padding:20px 18px; text-align:center;">
      <div class="modal-msg" id="popupMsg" style="font-size:14px; font-weight:700; color:#1e1e1e; margin-bottom:14px;"></div>
      <button type="button" onclick="document.getElementById('popup').style.display='none'" style="background:#8b0000; color:#fff; border:none; padding:6px 26px; border-radius:6px; font-weight:700;">OK</button>
    </div>
  </div>

  <div class="modal-overlay" id="successOverlay" style="position:fixed; inset:0; background:rgba(255,255,255,0.96); display:none; flex-direction:column; align-items:center; justify-content:center; z-index:9999;">
    <div style="font-size:22px; font-weight:800; color:#1b5e20;">Bid Submitted!</div>
  </div>

  <audio id="successSound" src="sound/done.mp3" preload="auto"></audio>

  <script>
    var nonce = <?= json_encode($nonce) ?>;
    var wallet = <?= json_encode((float) $totalBalance) ?>;
    var isClosed = <?= json_encode($openDisabled && $closeDisabled) ?>;
    var minBid = <?= (int) $minBid ?>;

    var bids = [];
    var pointsInput = document.getElementById('pointsInput');
    var bidsList = document.getElementById('bidsList');
    var submitBtn = document.getElementById('submitBtn');
    
    function showMsg(msg) {
      document.getElementById('popupMsg').textContent = msg;
      document.getElementById('popup').style.display = 'flex';
    }

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
      
      // Update keypad UI
      for(var i=0; i<=9; i++) {
        var el = document.getElementById('amt-'+i);
        if(el) el.textContent = '';
      }
      
      bids.forEach(function(item, idx) {
        var row = document.createElement('div');
        row.className = 'bid-row';
        row.innerHTML = '<div>' + item.number + '</div>' +
                        '<div>' + item.amount + '</div>' +
                        '<div>' + item.session + '</div>' +
                        '<div><button type="button" class="delete-btn" onclick="removeBid(' + idx + ')"><i class="fa-solid fa-trash"></i></button></div>';
        bidsList.appendChild(row);
        
        var k = document.getElementById('amt-' + item.number);
        if(k) k.textContent = item.amount;
      });
      
      if (bids.length > 0) {
        submitBtn.style.background = '#ff3b30';
      } else {
        submitBtn.style.background = '#ff8a80';
      }
    }

    window.removeBid = function(idx) {
      bids.splice(idx, 1);
      renderBids();
    };

    var keys = document.querySelectorAll('.key-btn');
    keys.forEach(function(btn) {
      btn.addEventListener('click', function() {
        if (isClosed) return showMsg('Market is closed for the day.');
        var sess = getSelectedSession();
        if (!sess) return showMsg('Please select Open or Close session.');
        
        var pointsVal = parseInt(pointsInput.value.trim(), 10) || 0;
        if (pointsVal < minBid) return showMsg('Minimum bid amount is ₹' + minBid);
        
        var currentTotal = bids.reduce(function(a,b){return a+b.amount;},0);
        if (currentTotal + pointsVal > wallet) {
          return showMsg('Insufficient wallet balance!');
        }
        
        var digit = parseInt(this.getAttribute('data-digit'), 10);
        
        var existIdx = bids.findIndex(b => b.number === digit && b.session === sess);
        if(existIdx >= 0) bids.splice(existIdx, 1);
        
        bids.push({ session: sess, number: digit, amount: pointsVal });
        renderBids();
      });
    });

    submitBtn.addEventListener('click', async function() {
      if (isClosed) return showMsg('Market is closed for the day.');
      if (bids.length === 0) return showMsg('Please add at least one bid.');

      var totalAmt = bids.reduce(function(a,b){return a+b.amount;}, 0);
      if (totalAmt > wallet) return showMsg('Insufficient wallet balance!');

      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Processing...';

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
           document.getElementById('successOverlay').style.display = 'flex';
           setTimeout(() => location.reload(), 1500);
        } else {
           showMsg(data.message || 'Error occurred');
           submitBtn.disabled = false;
           submitBtn.innerHTML = 'Submit';
        }
      } catch(e) {
        showMsg('Network error.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Submit';
      }
    });
  </script>
</body>
</html>


