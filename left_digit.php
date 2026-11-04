<?php
/* ----------------------------------------------------------
   left_digit.php – Gali Left Digit (0-9)
   UI Theme: Exact match to single_digit.php
-----------------------------------------------------------*/
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* 0. Market from URL */
$market = isset($_GET['market']) ? $_GET['market'] : '';
if ($market === '') {
    header("Location: gali_desawar.php");
    exit;
}
$marketLabel = strtoupper($market);

/* 1. User & balance */
require_once 'includes/db.php';
require_once 'includes/user_ping.php';

$uid = (int) ($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) {
    header('Location: user_login.php');
    exit;
}

$db = db();
$db->query("SET time_zone = '+05:30'");

/* 2. Fetch Market Details & Check Time */
$mkt = null;
if (hasTable($db, 'gali_markets')) {
    $stmt = $db->prepare("SELECT id, result_time, close_time_start, close_time_end, today_closed, saturday_closed, sunday_closed, custom_close_text FROM gali_markets WHERE UPPER(market_name)=? AND status='active' LIMIT 1");
    $stmt->bind_param('s', $marketLabel);
    $stmt->execute();
    $res = $stmt->get_result();
    $mkt = $res->fetch_assoc();
    $stmt->close();
}

if (!$mkt) {
    die("Invalid Market");
}

$marketId = $mkt['id'];
$resultTime = $mkt['result_time'];

$now = date('H:i');
$isClosed = false;
$msg = '';

$day = date('w');
if ($mkt['today_closed']) {
    $isClosed = true;
    $msg = $mkt['custom_close_text'] ?: 'Market Closed Today';
} elseif ($day == 6 && $mkt['saturday_closed']) {
    $isClosed = true;
    $msg = $mkt['custom_close_text'] ?: 'Saturday Holiday';
} elseif ($day == 0 && $mkt['sunday_closed']) {
    $isClosed = true;
    $msg = $mkt['custom_close_text'] ?: 'Sunday Holiday';
}

if (!$isClosed) {
    $closeStart = date('H:i', strtotime($mkt['close_time_start'] ?? '00:00'));
    $closeEnd = date('H:i', strtotime($mkt['close_time_end'] ?? '00:00'));

    $isOpen = true;
    if ($closeStart < $closeEnd) {
        if ($now >= $closeStart && $now < $closeEnd) {
            $isOpen = false;
        }
    } else {
        if ($now >= $closeStart || $now < $closeEnd) {
            $isOpen = false;
        }
    }

    if (!$isOpen) {
        $isClosed = true;
        $msg = 'Bidding Closed';
    }
}

/* User Code */
$stmt = $db->prepare("SELECT user_id FROM users WHERE id=? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$stmt->bind_result($LLCode);
$stmt->fetch();
$stmt->close();

if (empty($LLCode))
    die('User Code Missing');

/* Balance */
$totalBalance = current_user_wallet($db ?? null);

/* Nonce */
if (empty($_SESSION['gali_nonce'])) {
    $_SESSION['gali_nonce'] = bin2hex(random_bytes(16));
}
$nonce = $_SESSION['gali_nonce'];

/* 3. Handle POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bids'])) {
    header('Content-Type: application/json');

    if ($isClosed) {
        echo json_encode(['ok' => false, 'message' => 'Market Closed']);
        exit;
    }

    $clientNonce = $_POST['nonce'] ?? '';
    if (!hash_equals($_SESSION['gali_nonce'] ?? '', $clientNonce)) {
        echo json_encode(['ok' => false, 'message' => 'Duplicate request']);
        exit;
    }

    $bids = json_decode($_POST['bids'], true);
    if (!is_array($bids) || empty($bids)) {
        echo json_encode(['ok' => false, 'message' => 'Invalid data']);
        exit;
    }

    $total = 0;
    foreach ($bids as $r) {
        $amt = (int) ($r['amount'] ?? 0);
        $num = (int) ($r['number'] ?? -1);

        if ($num < 0 || $num > 9) {
            echo json_encode(['ok' => false, 'message' => 'Invalid digit']);
            exit;
        }
        if ($amt < 10) {
            echo json_encode(['ok' => false, 'message' => 'Min bid ₹10']);
            exit;
        }
        $total += $amt;
    }

    if ($total > $totalBalance) {
        echo json_encode(['ok' => false, 'message' => 'Insufficient balance']);
        exit;
    }

    try {
        $db->begin_transaction();

        if (!hasTable($db, 'gali_bids')) {
            throw new Exception("Bidding system initialization failed (gali_bids missing)");
        }
        $ins = $db->prepare("INSERT INTO gali_bids (user_id, market_id, market_name, game_type, digit, amount, date) VALUES (?, ?, ?, 'Left Digit', ?, ?, CURDATE())");

        $inserted = 0;
        foreach ($bids as $r) {
            $digit = (string) $r['number'];
            $amount = (float) $r['amount'];
            // user_id is integer in this table? The creating script used 's' for user_id bind but passed $uid or $LLCode?
            // create_new_market2.php used $uid (integer user id) effectively? 
            // In left_digit.php we used $uid. Let's stick to $uid for gali_bids user_id if that's the convention.
            // Wait, previous left_digit.php used $uid. 
            $ins->bind_param('sissd', $uid, $marketId, $marketLabel, $digit, $amount);
            $ins->execute();
            $inserted++;
        }
        $ins->close();

        // Warning: gali_markets logic used $uid for bids, but withdrawal table uses $LLCode usually.
        $wd = $db->prepare("INSERT INTO withdrawal (user_id, amount, status, method, date_time) VALUES (?, ?, 'Done', 'game_bid', NOW())");
        $wd->bind_param('sd', $LLCode, $total);
        $wd->execute();
        $wd->close();

        $db->commit();
        $_SESSION['gali_nonce'] = bin2hex(random_bytes(16));
        echo json_encode(['ok' => true]);
        exit;

    } catch (Throwable $e) {
        $db->rollback();
        echo json_encode(['ok' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
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
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=no">
    <title><?= htmlspecialchars($marketLabel) ?> – Left Digit</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.lordicon.com/lusqsztk.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: linear-gradient(135deg, #f5f0e5, #e8d9c9)
        }

        .container {
            max-width: 400px;
            margin: auto;
            padding: 10px
        }

        .header {
            background: #fff;
            border: 1px solid red;
            border-radius: 15px;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between
        }

        .header .back {
            color: #000;
            font-size: 22px;
            text-decoration: none
        }

        .header .info .market {
            font-size: 18px;
            font-weight: bold
        }

        .header .info .type {
            font-size: 14px;
            margin-top: 2px
        }

        .header .meta .balance {
            font-size: 16px;
            font-weight: bold
        }

        .header .meta .datetime {
            font-size: 12px;
            color: #444;
            margin-top: 2px
        }

        .digit-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .digit-box {
            display: flex;
            border-top: 2px solid #000;
            border-radius: 8px;
            background: #fff;
            overflow: hidden
        }

        .digit-box .num {
            width: 40px;
            background: #000;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold
        }

        .digit-box input {
            border: none;
            outline: none;
            padding: 8px;
            width: 100%;
            text-align: center;
            font-size: 18px;
            font-weight: bold
        }

        .add-btn,
        .confirm-btn {
            width: 100%;
            background: red;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 20px;
            margin-top: 15px;
            font-size: 18px;
            cursor: pointer
        }

        .table-container {
            background: #fff;
            padding: 10px;
            border-radius: 10px;
            margin-top: 15px;
            text-align: center
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px
        }

        .table-container th,
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            font-size: 14px
        }

        .table-container th {
            background: #000;
            color: #fff
        }

        .delete-btn {
            background: transparent;
            border: 1px solid red;
            color: red;
            padding: 4px 8px;
            border-radius: 5px;
            cursor: pointer
        }

        .summary-row {
            margin-top: 8px;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            padding: 0 5px;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, .5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000
        }

        .modal {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .3);
            max-width: 300px;
            width: 80%
        }

        .modal-header {
            background: red;
            color: #fff;
            padding: 10px;
            position: relative
        }

        .modal-body {
            padding: 20px;
            text-align: center
        }

        .modal-close {
            position: absolute;
            top: 8px;
            right: 12px;
            font-size: 18px;
            cursor: pointer;
            color: #fff
        }

        .success-text {
            font-size: 20px;
            font-weight: bold;
            color: #333
        }

        .market-closed {
            text-align: center;
            color: red;
            font-weight: bold;
            margin-top: 15px;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
    <div class="container">
        <div class="header">
            <a href="gali.php?market=<?= urlencode($marketLabel) ?>" class="back"><i class="fas fa-arrow-left"></i></a>
            <div class="info">
                <div class="market"><?= htmlspecialchars($marketLabel) ?></div>
                <div class="type">Left Digit</div>
            </div>
            <div class="meta">
                <div class="balance">₹ <?= number_format($totalBalance, 2) ?></div>
                <div class="datetime" id="datetime">--</div>
            </div>
        </div>

        <?php if ($isClosed): ?>
            <div class="market-closed"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="digit-grid">
            <?php for ($i = 0; $i < 10; $i++): ?>
                <div class="digit-box">
                    <div class="num"><?= $i ?></div>
                    <input type="number" min="0" placeholder="Amt" <?= $isClosed ? 'disabled' : '' ?> inputmode="numeric"
                        oninput="this.value=this.value.replace(/[^0-9]/g,'');">
                </div>
            <?php endfor; ?>
        </div>

        <button id="addBtn" class="add-btn" <?= $isClosed ? 'disabled' : '' ?>>ADD</button>

        <div class="table-container">
            <table id="entryTable">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            <button id="clearBtn" class="delete-btn">Delete All</button>

            <div class="summary-row">
                <span class="left">Remaining: ₹<span id="remaining"><?= (float) $totalBalance ?></span></span>
                <span class="right">Total: ₹<span id="totalBid">0</span></span>
            </div>
        </div>

        <button id="confirmBtn" class="confirm-btn" <?= $isClosed ? 'disabled' : '' ?>
            style="display:flex;align-items:center;justify-content:center;gap:8px;">
            <lord-icon src="https://cdn.lordicon.com/zmkotitn.json" trigger="loop" delay="1200"
                colors="primary:#ffffff,secondary:#ffffff" style="width:28px;height:28px;"></lord-icon>
            Confirm
        </button>
    </div>

    <div id="popupOverlay" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <span>Alert</span>
                <span id="popupClose" class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div id="popupMessage"></div>
            </div>
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
    <div id="successOverlay" class="modal-overlay">
        <div class="modal">
            <div class="modal-body">
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <div class="success-tick-wrapper">
                      <svg class="checkmark-svg" viewBox="0 0 52 52">
                        <path d="M14 27 l10 10 l20 -20" />
                      </svg>
                    </div>
                    <div class="success-text" style="margin-top:14px; font-weight:800; color:#1b5e20; font-size:20px;">Bid Submitted</div>
                </div>
            </div>
        </div>
    </div>

    <audio id="successSound" src="sound/done.mp3" preload="auto"></audio>

    <script>
        var pageDisabled = <?= $isClosed ? 'true' : 'false' ?>;
        var nonce = <?= json_encode($nonce) ?>;

        var wallet = <?= json_encode((float) $totalBalance) ?>;
        var total = 0;

        var addBtn = document.getElementById('addBtn');
        var clearBtn = document.getElementById('clearBtn');
        var confirmBtn = document.getElementById('confirmBtn');
        var tbody = document.querySelector('#entryTable tbody');
        var remain = document.getElementById('remaining');
        var totalBidEl = document.getElementById('totalBid');
        var pop = document.getElementById('popupOverlay');
        var popMsg = document.getElementById('popupMessage');
        var successOv = document.getElementById('successOverlay');
        var successSound = document.getElementById('successSound');

        function rem() {
            totalBidEl.textContent = total;
            remain.textContent = (wallet - total).toFixed(2);
        }
        function warn(m) {
            popMsg.textContent = m;
            pop.style.display = 'flex';
        }
        document.getElementById('popupClose').onclick = function() { return pop.style.display = 'none'; };
        pop.onclick = function(e) { if (e.target === pop) pop.style.display = 'none'; };

        addBtn.onclick = function() {
            if (pageDisabled) return warn('Market Closed');
            var anyAdded = false;

            for (var box of document.querySelectorAll('.digit-box')) {
                var num = box.querySelector('.num').textContent;
                var inp = box.querySelector('input');
                var amt = parseInt(inp.value) || 0;

                if (!amt) { inp.value = ''; continue; }
                if (amt < 10) return warn('Minimum bid ₹10');
                if (amt > (wallet - total)) return warn('Insufficient balance');

                total += amt;
                anyAdded = true;

                var tr = document.createElement('tr');
                tr.innerHTML = `<td>${num}</td><td>₹${amt}</td>`;
                tbody.appendChild(tr);

                inp.value = '';
            }

            if (anyAdded) rem();
        };

        clearBtn.onclick = function() {
            total = 0;
            tbody.innerHTML = '';
            rem();
        };

        function clock() {
            document.getElementById('datetime').textContent = new Date().toLocaleString(
                'en-GB',
                { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }
            );
        }
        clock(); setInterval(clock, 1000);
        rem();

        confirmBtn.onclick = async function() {
            if (pageDisabled) return warn('Market Closed');

            var rows = [...tbody.children].map(function(tr) {
                var n = tr.cells[0].textContent;
                var a = tr.cells[1].textContent;
                return { number: +n, amount: +(a.replace('₹', '')) };
            });

            if (!rows.length) return warn('No bids to submit');

            confirmBtn.disabled = true;

            try {
                var fd = new FormData();
                fd.append('bids', JSON.stringify(rows));
                fd.append('nonce', nonce);

                var res = await fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'fetch' }
                });

                var data = await res.json().catch(function() { return null; });
                if (!data || data.ok !== true) {
                    confirmBtn.disabled = false;
                    return warn((data && data.message) ? data.message : 'Submit failed');
                }

                successOv.style.display = 'flex';
                if (successSound) {
                    successSound.currentTime = 0;
                    successSound.play().catch(function() { });
                }

                setTimeout(function() {
                    location.reload();
                }, 1200);

            } catch (e) {
                confirmBtn.disabled = false;
                warn('Network/Server error');
            }
        };

        window.addEventListener('wheel', function(e) { if (e.ctrlKey) e.preventDefault(); }, { passive: false });
        document.addEventListener('keydown', function(e) { if (e.ctrlKey && ['+', '-', '=', '0'].includes(e.key)) e.preventDefault(); });
    </script>
</body>

</html>
