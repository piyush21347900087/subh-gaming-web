<?php
/* --------------------------------------------------------------------------
   double_pana_bulk.php – Bulk entry for double-pana bids by digit
   Logic: Enter points once -> tap digit buttons (0-9) to add all related double panas.
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
if (empty($_SESSION['bid_nonce_bulk_dp'])) {
    $_SESSION['bid_nonce_bulk_dp'] = bin2hex(random_bytes(16));
}
$nonce = $_SESSION['bid_nonce_bulk_dp'];

/* 2. Handle POST (AJAX) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bids'])) {
    header('Content-Type: application/json; charset=utf-8');
    $clientNonce = (string) ($_POST['nonce'] ?? '');
    if ($clientNonce === '' || !hash_equals($_SESSION['bid_nonce_bulk_dp'] ?? '', $clientNonce)) {
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
                user_id, market, session_type, double_pana, amount, bid_time
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
        $_SESSION['bid_nonce_bulk_dp'] = bin2hex(random_bytes(16));
        echo json_encode(['ok' => true]);
        exit;

    } catch (Throwable $e) {
        if ($db)
            $db->rollback();
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Helper: Get sum digit mod 10 for Pana (0 treated as 10)
function getPanaDigit(string $pana): int {
    $d1 = (int)$pana[0]; if ($d1 === 0) $d1 = 10;
    $d2 = (int)$pana[1]; if ($d2 === 0) $d2 = 10;
    $d3 = (int)$pana[2]; if ($d3 === 0) $d3 = 10;
    return ($d1 + $d2 + $d3) % 10;
}

// Generate all 90 valid Double Panas in Matka order
$validDoublePanas = [];
for ($a = 0; $a <= 9; $a++) {
    for ($b = 0; $b <= 9; $b++) {
        for ($c = 0; $c <= 9; $c++) {
            $vA = ($a === 0) ? 10 : $a;
            $vB = ($b === 0) ? 10 : $b;
            $vC = ($c === 0) ? 10 : $c;
            
            if ($vA <= $vB && $vB <= $vC) {
                // Must be double pana: exactly 2 digits equal
                if (($a === $b && $b !== $c) || ($b === $c && $a !== $b) || ($a === $c && $a !== $b)) {
                    $pStr = "{$a}{$b}{$c}";
                    if (!in_array($pStr, $validDoublePanas)) {
                        $validDoublePanas[] = $pStr;
                    }
                }
            }
        }
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
        <?= htmlspecialchars($marketLabel) ?> – Double Pana Bulk
    </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Filter Tabs Styling */
        .tabs-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3px;
            margin-bottom: 14px;
            width: 100%;
        }

        .tab-btn {
            padding: 5px 2px;
            background: #ffffff;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 800;
            color: #8b0000;
            border: 1.5px solid #ff5252;
            white-space: nowrap;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s ease;
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
        }

        .tab-btn.active {
            background: #c62828;
            color: #ffffff;
            border-color: #c62828;
        }

        /* Keypad Grid (4 columns for Pana) */
        .keypad-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 10px;
        }

        .key-btn {
            background: #ffffff;
            border: 1.5px solid #ff5252;
            border-radius: 10px;
            height: 56px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            user-select: none;
            transition: transform 0.1s ease, background 0.15s ease;
        }

        .key-btn:active {
            transform: scale(0.93);
            background: #ffebee;
        }

        .key-digit {
            font-size: 16px;
            font-weight: 800;
            color: #1e1e1e;
            line-height: 1;
        }

        .key-amt {
            font-size: 10px;
            font-weight: 800;
            color: #e53935;
            margin-top: 2px;
            height: 12px;
        }

        /* Bids List Table */
        .bids-table-container {
            margin: 0 14px 20px;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #edf2f7;
        }

        .bids-table-header {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 40px;
            padding: 12px 16px;
            background: #f8fafc;
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
        <a href="javascript:history.back()" class="back-btn" title="Back">
            <i class="fa-solid fa-chevron-left"></i>
        </a>
        <div style="text-align: center; flex: 1; margin: 0 8px; overflow: hidden;">
            <div style="font-size: 15px; font-weight: 800; text-transform: uppercase; color: #ffffff; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($marketLabel) ?></div>
            <div style="font-size: 11px; font-weight: 700; color: #ffcdd2; text-transform: uppercase; margin-top: 2px; letter-spacing: 0.5px;"><?= __("Double Pana Bulk") ?></div>
        </div>
        <a href="deposit.php" class="wallet-btn">
            <i class="fa-solid fa-wallet"></i>
            <span><?= number_format($totalBalance, 0) ?></span>
        </a>
    </header>

    <div class="container">
        <!-- Date & Bet Banner -->
        <div class="date-banner">
            <?= date('d M Y - l') ?> - <?= ($openDisabled && $closeDisabled) ? 'BET CLOSED' : 'BET OPEN' ?>
        </div>

        <!-- Select Game Type Card -->
        <div class="select-game-card">
            <div class="select-game-title"><?= __("SELECT GAME TYPE") ?>:</div>
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
                <div class="form-label"><?= __("POINTS") ?></div>
                <input type="tel" id="ptsInput" class="form-input" placeholder="<?= __("Enter points") ?>" maxlength="5">
            </div>

            <!-- Filter Tabs -->
            <div class="tabs-row">
                <button type="button" class="tab-btn active" data-digit="all">ALL</button>
                <?php 
                $digitTabs = [1, 2, 3, 4, 5, 6, 7, 8, 9, 0];
                foreach ($digitTabs as $dt): 
                ?>
                    <button type="button" class="tab-btn" data-digit="<?= $dt ?>">P<?= $dt ?></button>
                <?php endforeach; ?>
            </div>

            <!-- Grid of 90 Separate Double Pana Buttons -->
            <div class="keypad-grid" id="keypadGrid">
                <?php foreach ($validDoublePanas as $p): 
                    $digit = getPanaDigit($p);
                ?>
                    <button type="button" class="key-btn" data-num="<?= $p ?>" data-digit="<?= $digit ?>">
                        <span class="key-digit"><?= $p ?></span>
                        <span class="key-amt" id="badge-<?= $p ?>"></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Bids Summary Table -->
        <div class="bids-table-container" id="bidSummaryArea" style="display:none;">
            <div class="bids-table-header">
                <div><?= __("PANA") ?></div>
                <div><?= __("POINTS") ?></div>
                <div><?= __("TYPE") ?></div>
                <div></div>
            </div>
            <div class="bids-list" id="bidList"></div>
        </div>
    </div>

    <!-- Bottom Fixed Submit Bar -->
    <div class="bottom-bar">
        <button class="submit-btn" id="submitBtn">Submit</button>
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
        var keys = document.querySelectorAll('.key-btn');
        var bidSummaryArea = document.getElementById('bidSummaryArea');
        var bidListEl = document.getElementById('bidList');
        var submitBtn = document.getElementById('submitBtn');
        var tabBtns = document.querySelectorAll('.tab-btn');
        var keypadGrid = document.getElementById('keypadGrid');

        var bids = {}; // { "100": { amount: 10, session: "Open" } }

        function getSelectedSession() {
            var radios = document.getElementsByName('gameSession');
            for (var i = 0; i < radios.length; i++) {
                if (radios[i].checked) return radios[i].value;
            }
            return 'Open';
        }

        function showMsg(msg) {
            document.getElementById('popupMsg').textContent = msg;
            document.getElementById('popup').style.display = 'flex';
        }

        ptsInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Tab Filtering
        tabBtns.forEach(function(btn) {
            btn.addEventListener('click', function () {
                tabBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                var filterDigit = this.dataset.digit;
                var cards = keypadGrid.querySelectorAll('.key-btn');

                cards.forEach(function(card) {
                    if (filterDigit === 'all') {
                        card.style.display = 'flex';
                    } else {
                        if (card.dataset.digit === filterDigit) {
                            card.style.display = 'flex';
                        } else {
                            card.style.display = 'none';
                        }
                    }
                });
            });
        });

        keys.forEach(function(k) {
            k.addEventListener('click', function () {
                if (isClosed) return showMsg('Market is closed');

                var num = this.dataset.num;
                var amt = parseInt(ptsInput.value) || 0;
                var session = getSelectedSession();

                if (amt < minBid) {
                    showMsg('Please enter points (Min ₹' + minBid + ')');
                    ptsInput.focus();
                    return;
                }

                var currentTotal = calculateTotal();
                if (currentTotal + amt > wallet) {
                    return showMsg('Insufficient wallet balance!');
                }

                if (!bids[num]) {
                    bids[num] = { amount: 0, session: session };
                }
                bids[num].amount += amt;
                bids[num].session = session;

                updateUI();
            });
        });

        function calculateTotal() {
            return Object.values(bids).reduce((a, b) => a + b.amount, 0);
        }

        function updateUI() {
            var total = calculateTotal();

            // Clear all badges
            keys.forEach(function(k) {
                var num = k.dataset.num;
                var badge = document.getElementById('badge-' + num);
                if (badge) badge.textContent = '';
            });

            var activeNums = Object.keys(bids).filter(n => bids[n].amount > 0);

            if (activeNums.length > 0) {
                bidSummaryArea.style.display = 'block';
                submitBtn.classList.add('active');

                bidListEl.innerHTML = activeNums.map(function(num) {
                    var item = bids[num];
                    var badge = document.getElementById('badge-' + num);
                    if (badge) badge.textContent = '₹' + item.amount;

                    return `
                        <div class="bid-row">
                            <div>${num}</div>
                            <div>₹${item.amount}</div>
                            <div>${item.session}</div>
                            <div><button type="button" class="delete-btn" onclick="removeBid('${num}')"><i class="fa-solid fa-trash"></i></button></div>
                        </div>
                    `;
                }).reverse().join('');
            } else {
                bidSummaryArea.style.display = 'none';
                submitBtn.classList.remove('active');
            }
        }

        window.removeBid = function (num) {
            delete bids[num];
            updateUI();
        };

        window.clearAll = function () {
            bids = {};
            updateUI();
        };

        submitBtn.addEventListener('click', async function() {
            var activeNums = Object.keys(bids).filter(n => bids[n].amount > 0);
            if (activeNums.length === 0) return showMsg('No bids to submit');

            var total = calculateTotal();
            if (total > wallet) return showMsg('Insufficient wallet balance');

            submitBtn.disabled = true;
            var ogText = submitBtn.innerText;
            submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing...';

            try {
                var payload = activeNums.map(n => ({
                    number: n,
                    amount: bids[n].amount,
                    session: bids[n].session
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
                    submitBtn.innerText = ogText;
                }
            } catch (e) {
                showMsg('Network error');
                submitBtn.disabled = false;
                submitBtn.innerText = ogText;
            }
        });
    </script>
</body>

</html>



