<?php
/* --------------------------------------------------------------------------
   triple_pana_bulk.php – Bulk entry for triple-pana bids by digit
   Logic: Enter points once -> tap digit buttons (0-9) to add triple pana.
   Triple pana = all 3 digits same (000, 111, 222, ... 999) — 10 total.
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
if (empty($_SESSION['bid_nonce_bulk_tp'])) {
    $_SESSION['bid_nonce_bulk_tp'] = bin2hex(random_bytes(16));
}
$nonce = $_SESSION['bid_nonce_bulk_tp'];

/* 2. Handle POST (AJAX) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bids'])) {
    header('Content-Type: application/json; charset=utf-8');
    $clientNonce = (string) ($_POST['nonce'] ?? '');
    if ($clientNonce === '' || !hash_equals($_SESSION['bid_nonce_bulk_tp'] ?? '', $clientNonce)) {
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
                user_id, market, session_type, triple_pana, amount, bid_time
            ) VALUES(?, ?, ?, ?, ?, ?)
        ");

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

        /* Deduct Balance (via withdrawal entry) */
        $wd = $db->prepare("INSERT INTO withdrawal(user_id, amount, status, method, date_time) VALUES(?, ?, 'Done', 'game_bid', ?)");
        $dAmt = (float) $totalAmt;
        $wd->bind_param('sds', $LLCode, $dAmt, $bidNow);
        $wd->execute();
        $wd->close();

        $db->commit();
        $_SESSION['bid_nonce_bulk_tp'] = bin2hex(random_bytes(16));
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
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <title>
        <?= htmlspecialchars($marketLabel) ?> – Triple Pana Bulk
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, sans-serif;
        }

        body {
            background: #f4f6f8;
            min-height: 100vh;
            padding-bottom: 120px;
        }

        /* Header Styling */
        .header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 15px;
            background: #ff005c;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .back-btn,
        .wallet-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #ffffff;
            color: #000000;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 18px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            border: none;
            cursor: pointer;
        }

        .page-title {
            font-size: 16px;
            font-weight: 800;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Form Controls */
        .controls-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 20px 20px 10px;
            max-width: 600px;
            margin: 0 auto;
        }

        .control-box {
            background: #fff;
            border-radius: 12px;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
            font-size: 14px;
            font-weight: 600;
            color: #455a64;
            position: relative;
        }

        .control-box select {
            border: none;
            outline: none;
            background: transparent;
            width: 100%;
            cursor: pointer;
            font-weight: 600;
            color: #455a64;
            appearance: none;
        }

        .control-box i.chevron {
            font-size: 12px;
            color: #ff005c;
            pointer-events: none;
        }

        /* Points Input */
        .points-container {
            padding: 10px 20px 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        .points-input-card {
            background: #fff;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .points-input-card input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 16px;
            font-weight: 600;
        }

        /* Keypad Styling */
        .keypad-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            padding: 0 20px 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        .key-btn {
            background: #fff;
            border-radius: 10px;
            height: 60px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
            cursor: pointer;
            transition: transform 0.1s, background 0.2s;
            border: 1px solid #f0f0f0;
            outline: none;
            position: relative;
        }

        .key-digit {
            font-size: 18px;
            font-weight: 800;
            color: #455a64;
        }

        .key-amt {
            font-size: 9px;
            font-weight: 700;
            color: #2e7d32;
            margin-top: 1px;
            min-height: 11px;
        }

        .key-btn:active {
            transform: scale(0.92);
        }

        .key-btn.active {
            background: #333333;
            color: #fff;
            border-color: #333333;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        /* Bid List / Summary */
        .bid-summary {
            padding: 0 20px 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        .labels-row {
            display: grid;
            grid-template-columns: 100px 1fr 1fr 40px;
            padding: 0 5px 10px;
            font-size: 14px;
            font-weight: 700;
            color: #666;
        }

        .bid-item {
            background: #fff;
            border-radius: 12px;
            padding: 12px 15px;
            display: grid;
            grid-template-columns: 100px 1fr 1fr 40px;
            align-items: center;
            margin-bottom: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .bid-item .pana {
            font-weight: 800;
            font-size: 16px;
            color: #333;
        }

        .bid-item .points {
            font-weight: 700;
            color: #2e7d32;
            text-align: center;
        }

        .bid-item .type {
            font-weight: 600;
            color: #455a64;
            text-align: center;
            font-size: 14px;
        }

        .bid-item .remove {
            color: #fff;
            background: #333333;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            margin-left: auto;
        }

        /* Bottom Fixed Bar */
        .bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            padding: 15px 20px;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 200;
        }

        .total-info {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .total-info .rem {
            color: #ff005c;
        }

        .total-info .tot {
            color: #2e7d32;
        }

        .submit-btn {
            background: linear-gradient(135deg, #ff005c, #cc004a);
            color: #fff;
            border: none;
            border-radius: 12px;
            height: 50px;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 1px;
            cursor: pointer;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-btn:disabled {
            opacity: 0.6;
            filter: grayscale(0.5);
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

        #successOverlay {
            display: none;
            background: rgba(255, 255, 255, 0.95);
            flex-direction: column;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

    <header class="header-bar">
        <a href="javascript:history.back()" class="back-btn"><i class="fas fa-chevron-left"></i></a>
        <div style="text-align: center;">
            <div class="page-title" style="line-height: 1;"><?= htmlspecialchars($marketLabel) ?></div>
            <div
                style="font-size: 9px; font-weight: 300; color: #ffffff; text-transform: uppercase; margin-top: 2px; letter-spacing: 0.5px;">
                <?= __("Triple Pana Bulk") ?> - TPB</div>
        </div>
        <div class="wallet-btn" style="width:auto; padding:0 12px; gap:6px; border-radius:20px;">
            <i class="fas fa-wallet" style="font-size:16px"></i>
            <span style="font-size:14px; font-weight:700;"><?= number_format($totalBalance) ?></span>
        </div>
    </header>

    <div class="controls-row">
        <div class="control-box">
            <span>
                <?= date('Y-m-d') ?>
            </span>
            <i class="far fa-calendar-alt" style="color:#000000"></i>
        </div>
        <div class="control-box">
            <select id="sessionBox">
                <?php if (!$openDisabled): ?>
                    <option value="Open"><?= __("Open") ?></option>
                <?php endif; ?>
                <?php if (!$closeDisabled): ?>
                    <option value="Close" <?= $openDisabled ? 'selected' : '' ?>><?= __("Close") ?></option>
                <?php endif; ?>
                <?php if ($openDisabled && $closeDisabled): ?>
                    <option value="" disabled selected>Closed</option>
                <?php endif; ?>
            </select>
            <i class="fas fa-chevron-down chevron"></i>
        </div>
    </div>

    <div class="points-container">
        <div class="points-input-card">
            <input type="tel" id="ptsInput" placeholder="<?= __("Enter points") ?>" maxlength="5">
        </div>
    </div>

    <div class="keypad-grid">
        <?php for ($i = 1; $i <= 9; $i++): ?>
            <button class="key-btn" data-digit="<?= $i ?>">
                <span class="key-digit">
                    <?= $i ?>
                </span>
                <span class="key-amt" id="badge-<?= $i ?>"></span>
            </button>
        <?php endfor; ?>
        <button class="key-btn" data-digit="0">
            <span class="key-digit">0</span>
            <span class="key-amt" id="badge-0"></span>
        </button>
    </div>

    <div class="bid-summary" id="bidSummaryArea" style="display:none;">
        <div class="labels-row">
            <span><?= __("Pana") ?></span>
            <span style="text-align:center"><?= __("Points") ?></span>
            <span style="text-align:center"><?= __("Session") ?></span>
            <span></span>
        </div>
        <div id="bidList"></div>
    </div>

    <div class="bottom-bar">
        <div class="total-info">
            <span class="rem"><?= __("Wallet") ?>: ₹
                <?= number_format($totalBalance) ?>
            </span>
            <span class="tot"><?= __("Total Bid") ?>: ₹0</span>
        </div>
        <button class="submit-btn" id="submitBtn">
            <span>SUBMIT BID</span>
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>

    <div class="modal-overlay" id="popup">
        <div class="modal-box">
            <div class="modal-msg" id="popupMsg"></div>
            <button class="modal-btn" onclick="document.getElementById('popup').style.display='none'"><?= __("OK") ?></button>
        </div>
    </div>

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

    <script src="https://cdn.lordicon.com/lusqsztk.js"></script>
    <audio id="successSound" src="sound/done.mp3" preload="auto"></audio>
    <script>
        var wallet = <?= json_encode((float) $totalBalance) ?>;
        var nonce = <?= json_encode($nonce) ?>;
        var isClosed = <?= json_encode($openDisabled && $closeDisabled) ?>;
        var minBid = <?= (int) $minBid ?>;

        var ptsInput = document.getElementById('ptsInput');
        var digitKeys = document.querySelectorAll('.key-btn');
        var bidListEl = document.getElementById('bidList');
        var submitBtn = document.getElementById('submitBtn');
        var sessionBox = document.getElementById('sessionBox');

        var bidsList = [];

        // MAPPING: Triple Panas (10 Total)
        // Triple pana = all 3 digits same: 000, 111, 222, ... 999
        // Digit grouping: (3 * a) % 10
        // 000->0, 111->3, 222->6, 333->9, 444->2, 555->5, 666->8, 777->1, 888->4, 999->7
        var triplePanasByDigit = {};
        for (var d = 0; d <= 9; d++) triplePanasByDigit[d] = [];

        for (var a = 0; a <= 9; a++) {
            var sum = 3 * a;
            var resultDigit = sum % 10;
            var panaStr = '' + a + a + a;
            triplePanasByDigit[resultDigit].push(panaStr);
        }

        function showMsg(msg) {
            document.getElementById('popupMsg').textContent = msg;
            document.getElementById('popup').style.display = 'flex';
        }

        ptsInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        digitKeys.forEach(function(btn) {
            btn.addEventListener('click', function () {
                if (isClosed) return showMsg('Market is closed');

                var digit = this.dataset.digit;
                var points = parseInt(ptsInput.value) || 0;
                var session = sessionBox.value;

                if (points < minBid) {
                    showMsg('Minimum points ' + minBid + ' required');
                    ptsInput.focus();
                    return;
                }

                if (!session) return showMsg('Session not available');

                var relatedPanas = triplePanasByDigit[digit];

                if (relatedPanas.length === 0) {
                    return showMsg('No triple pana for this digit');
                }

                // Calculate future total before adding
                var currentTotal = bidsList.reduce((acc, b) => acc + b.points, 0);
                var potentialIncrease = 0;

                relatedPanas.forEach(function(pana) {
                    potentialIncrease += points;
                });

                if (currentTotal + potentialIncrease > wallet) {
                    return showMsg('Insufficient balance');
                }

                relatedPanas.forEach(function(pana) {
                    var existing = bidsList.find(b => b.pana === pana && b.session === session);
                    if (existing) {
                        existing.points += points;
                    } else {
                        bidsList.push({ pana, points, session });
                    }
                });

                updateUI();

                // Visual feedback
                this.classList.add('active');
                setTimeout(function() { return this.classList.remove('active'); }, 200);
            });
        });

        function updateUI() {
            var totalPts = 0;

            var digitPoints = {};
            for (var d = 0; d <= 9; d++) digitPoints[d] = 0;

            if (bidsList.length > 0) {
                document.getElementById('bidSummaryArea').style.display = 'block';
                bidListEl.innerHTML = bidsList.map(function(bid, index) {
                    totalPts += bid.points;

                    for (var d = 0; d <= 9; d++) {
                        if (triplePanasByDigit[d].includes(bid.pana)) {
                            digitPoints[d] += bid.points;
                            break;
                        }
                    }

                    return `
                    <div class="bid-item">
                        <span class="pana">${bid.pana}</span>
                        <span class="points">${bid.points} Points</span>
                        <span class="type">${bid.session}</span>
                        <div class="remove" onclick="removeBid(${index})"><i class="fas fa-times"></i></div>
                    </div>`;
                }).reverse().join('');
            } else {
                document.getElementById('bidSummaryArea').style.display = 'none';
            }

            // Update Badges on Keys
            for (var d = 0; d <= 9; d++) {
                var badge = document.getElementById('badge-' + d);
                if (digitPoints[d] > 0) badge.textContent = '₹' + digitPoints[d];
                else badge.textContent = '';
            }

            document.querySelector('.tot').textContent = `Total Bid: ₹${totalPts}`;
            document.querySelector('.rem').textContent = `<?= __("Wallet") ?>: ₹${(wallet - totalPts).toLocaleString()}`;

            if (totalPts > wallet) {
                document.querySelector('.tot').style.color = '#000000';
            } else {
                document.querySelector('.tot').style.color = '#2e7d32';
            }
        }

        window.removeBid = function (index) {
            bidsList.splice(index, 1);
            updateUI();
        };

        window.clearAll = function () {
            bidsList = [];
            updateUI();
        };

        submitBtn.addEventListener('click', async function() {
            if (bidsList.length === 0) return showMsg('No bids to submit');

            var total = bidsList.reduce((acc, b) => acc + b.points, 0);
            if (total > wallet) return showMsg('Insufficient balance');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing...';

            try {
                var payload = bidsList.map(b => ({
                    number: b.pana,
                    amount: b.points,
                    session: b.session
                }));

                var fd = new FormData();
                fd.append('bids', JSON.stringify(payload));
                fd.append('nonce', nonce);

                var res = await fetch('', { method: 'POST', body: fd });
                var data = await res.json();

                if (data.ok) {
                    var successSound = document.getElementById('successSound');
                    if (successSound) {
                        successSound.currentTime = 0;
                        successSound.play().catch(function() { });
                    }
                    document.getElementById('successOverlay').style.display = 'flex';
                    setTimeout(function() { return location.reload(); }, 1500);
                } else {
                    showMsg(data.message || 'Error occurred');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span>SUBMIT BID</span> <i class="fas fa-paper-plane"></i>';
                }
            } catch (e) {
                showMsg('Network error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>SUBMIT BID</span> <i class="fas fa-paper-plane"></i>';
            }
        });

    </script>
</body>

</html>


