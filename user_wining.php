<?php
require_once 'includes/lang.php';
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/user_ping.php';
require_once 'includes/live_ping.php';

date_default_timezone_set('Asia/Kolkata');

if (empty($_SESSION['user']['id'])) {
  header('Location: user_login.php');
  exit;
}

$uid = (int) $_SESSION['user']['id'];
$db = db();
$isDeactive = (isset($_SESSION['user']['is_active']) && (int) $_SESSION['user']['is_active'] === 0);

/* ===================== LOGIC FOR USER CODE & INFO ===================== */
function fetch_one_val($db, $sql, $types, $params)
{
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $val = null;
  $stmt->bind_result($val);
  $out = $stmt->fetch() ? $val : null;
  $stmt->close();
  return $out;
}

$code = trim((string) ($_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? ''));
if ($code === '') {
  $code = fetch_one_val($db, 'SELECT user_id FROM users WHERE id = ?', 'i', [$uid]) ?: '';
}
if (preg_match('/^\d+$/', $code)) {
  $code = "KR" . str_pad($code, 2, "0", STR_PAD_LEFT);
} elseif (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $code, $m)) {
  $code = $m[1] . str_pad($m[2], 2, "0", STR_PAD_LEFT);
}

$userName = $_SESSION['user']['name'] ?? '';
if ($userName === '') {
  $userName = fetch_one_val($db, 'SELECT name FROM users WHERE id = ?', 'i', [$uid]) ?: $code;
}

$userCreatedAt = fetch_one_val($db, 'SELECT created_at FROM users WHERE id = ?', 'i', [$uid]) ?: '';
$userSince = $userCreatedAt ? date('d-m-Y', strtotime($userCreatedAt)) : '';
$userMobile = fetch_one_val($db, 'SELECT mobile_no FROM users WHERE id = ?', 'i', [$uid]) ?: '';

// Balance calculation
$hasBonusCol = false;
$resB = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
if ($resB && $resB->num_rows > 0) {
  $hasBonusCol = true;
}

$depositDone = (float) (fetch_one_val(
  $db,
  "SELECT (IFNULL(SUM(amount),0) + " . ($hasBonusCol ? "IFNULL(SUM(bonus_amount),0)" : "0") . ") FROM deposit WHERE status='Done' AND user_id=?",
  's',
  [$code]
) ?? 0);

$withdrawMinus = (float) (fetch_one_val(
  $db,
  "SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE user_id=? AND LOWER(status) IN ('pending','approved','done')",
  's',
  [$code]
) ?? 0);

$walletBalance = max(0.0, $depositDone - $withdrawMinus);
$winBalance = (float) (fetch_one_val($db, "SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id=?", 's', [$code]) ?? 0);
$totalBalance = (float)($depositDone - $withdrawMinus) + (float)$winBalance;
if ($totalBalance < 0) $totalBalance = 0;
$winBalance = (float)($totalBalance - $walletBalance);

function send_winnings($db, $code, $uid, $from, $to) {
  $fromStr = trim($from) . " 00:00:00";
  $toStr = trim($to) . " 23:59:59";
  $data = [];
  $uidStr = (string) $uid;
  $sql = "SELECT market, bet_type AS game_type, session_type, amount, wining_balance, win_time AS created_at, bet_key FROM wining WHERE user_id IN (?, ?) AND win_time BETWEEN ? AND ? AND LOWER(status) = 'win' AND IFNULL(is_reversed, 0) = 0 ORDER BY win_time DESC";
  if ($stmt = $db->prepare($sql)) {
    $stmt->bind_param('ssss', $code, $uidStr, $fromStr, $toStr);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
      $data[] = $r;
    }
    $stmt->close();
  }
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

$today = date('Y-m-d');
$lastweek = date('Y-m-d', strtotime('-6 days'));

if (isset($_GET['ajax'])) {
  send_winnings($db, $code, $uid, $_GET['from'] ?? $today, $_GET['to'] ?? $today);
}
$db->close();
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <title><?= __("Winning History") ?> | SUBH GAMING</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #f8f9fb;
            --card-bg: #ffffff;
            --primary-red: #8b0000;
            --accent-red: #ff5252;
            --btn-red: #e53935;
            --text-main: #333333;
            --text-dim: #666666;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            background-color: #000000 !important;
            background: #000000 !important;
            color: #ffffff;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── Header Standardized matching user_bid_history.php & withdrawal_history.php ── */
        header {
            background-color: #000000 !important;
            background-image: none !important;
            border-bottom: 1px solid #1a1a1a;
            padding-top: calc(env(safe-area-inset-top) + 12px) !important;
            padding-bottom: 12px !important;
            padding-left: 14px !important;
            padding-right: 14px !important;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #e53935;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            text-decoration: none;
            font-size: 15px;
            cursor: pointer;
            border: none;
            flex-shrink: 0;
            transition: opacity 0.15s ease;
        }

        .back-btn:active {
            opacity: 0.8;
        }

        .header-title-flash {
            color: #ffffff !important;
            font-size: 19px !important;
            font-weight: 800 !important;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
        }

        .header-title-flash img {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            margin-top: -2px;
        }

        /* ── Filter Bar matching user_bid_history.php 100% ── */
        .filter-bar {
            display: flex;
            gap: 8px;
            padding: 10px;
            background: #121212;
            align-items: center;
            border-bottom: 1px solid #1a1a1a;
            max-width: 550px;
            margin: 0 auto;
        }

        .filter-input-wrap {
            flex: 1;
            position: relative;
        }

        .filter-input-wrap i {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #e53935;
            font-size: 14px;
            pointer-events: none;
        }

        .filter-input-wrap input[type="date"] {
            width: 100%;
            padding: 8px 8px 8px 28px;
            border: 1px solid #e53935;
            border-radius: 6px;
            font-size: 12px;
            color: #ffffff;
            font-weight: 600;
            outline: none;
            background: #1a1a1a;
            font-family: 'Poppins', sans-serif;
        }

        .filter-apply-btn {
            background: #e53935;
            color: #ffffff;
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            flex-shrink: 0;
            transition: opacity 0.15s ease, transform 0.1s ease;
        }

        .filter-apply-btn:active {
            transform: scale(0.96);
            opacity: 0.9;
        }

        /* ── Main Container & Cards ── */
        .content {
            max-width: 550px;
            margin: 0 auto;
            padding: 14px;
            min-height: calc(100vh - 130px);
        }

        .summary-card { 
            background: #ffffff; 
            border-radius: 8px; 
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); 
            padding: 10px 14px;
            margin-bottom: 12px;
            border: 1px solid #eeeeee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .summary-total { font-size: 14px; font-weight: 700; color: #333333; }
        .summary-total strong { color: #2ecc71; font-size: 17px; margin-left: 6px; }

        .win-box {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            padding: 12px 14px;
            position: relative;
            border-left: 4px solid #2ecc71;
            margin-bottom: 10px;
            border-top: 1px solid #f0f0f0;
            border-right: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }

        .row-flex { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 4px; }
        .game-name { font-size: 14px; font-weight: 800; color: #e53935; text-transform: uppercase; margin: 0; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .win-val { font-size: 15px; font-weight: 700; color: #2ecc71; }
        .bid-amount { font-size: 12px; font-weight: 600; color: #666666; }
        .session-chip { font-size: 10px; font-weight: 700; color: #0066cc; background: #eaf4ff; border: 1px solid rgba(0,102,204,0.16); padding: 2px 8px; border-radius: 999px; text-transform: uppercase; white-space: nowrap; flex-shrink: 0; }
        .no-chip { font-size: 10px; font-weight: 800; color: #0066cc; background: #eef4ff; padding: 2px 8px; border-radius: 999px; border: 1px solid rgba(0,102,204,0.16); }
        .info-tag { font-size: 10px; font-weight: 700; color: #666666; background: #f0f2f5; padding: 2px 8px; border-radius: 99px; text-transform: uppercase; }

        .win-center-text {
            font-size: 14px;
            font-weight: 800;
            margin: 0;
            letter-spacing: 2px;
            animation: colorChange 2s infinite;
            flex: 1;
            text-align: center;
        }

        @keyframes colorChange {
            0% { color: #ff0000; text-shadow: 0 2px 8px rgba(255,0,0,0.4); }
            25% { color: #00cc00; text-shadow: 0 2px 8px rgba(0,204,0,0.4); }
            50% { color: #0066ff; text-shadow: 0 2px 8px rgba(0,102,255,0.4); }
            75% { color: #ff00ff; text-shadow: 0 2px 8px rgba(255,0,255,0.4); }
            100% { color: #ff0000; text-shadow: 0 2px 8px rgba(255,0,0,0.4); }
        }

        .footer-info { display: flex; justify-content: space-between; font-size: 11px; color: #888888; font-weight: 500; border-top: 1px solid #f5f5f5; padding-top: 6px; margin-top: 6px; }
        .footer-info span { display: flex; align-items: center; gap: 4px; color: #e53935; font-weight: 600; }

        /* Loader & Load More */
        #loader { text-align: center; margin: 30px 0; display: none; }
        .lds-dual-ring { display: inline-block; width: 30px; height: 30px; }
        .lds-dual-ring:after { content: " "; display: block; width: 24px; height: 24px; margin: 3px; border-radius: 50%; border: 3px solid #ff5252; border-color: #ff5252 transparent #ff5252 transparent; animation: lds-dual-ring 1.2s linear infinite; }
        @keyframes lds-dual-ring { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .load-more-container { display: flex; justify-content: center; align-items: center; margin-top: 18px; margin-bottom: 25px; }
        .load-more-btn { background: #ff5252; color: #ffffff; border: none; padding: 8px 22px; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 10px rgba(255, 82, 82, 0.25); transition: opacity 0.15s ease; display: none; }
        .load-more-btn:active { transform: scale(0.96); }

        /* ── Centered Empty State ── */
        .empty-state-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: calc(70vh - 120px);
            text-align: center;
            padding: 20px;
        }

        .illustration-container { margin-bottom: 20px; }
        .illustration-container img { filter: invert(32%) sepia(87%) saturate(2986%) hue-rotate(346deg) brightness(96%) contrast(92%); }
        .empty-state-text { font-size: 16px; font-weight: 500; color: #94a3b8; }
    </style>

    <script>
        var IST_TODAY = "<?= $today ?>";
    </script>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

    <!-- Header identical to user_bid_history.php -->
    <header>
        <div class="header-left">
            <a class="back-btn" href="javascript:history.back()" title="Back">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <div class="header-title-flash">
                <img src="icons/gold_coin.svg" alt="coin">
                <?= __("Winning History") ?>
                <img src="icons/gold_coin.svg" alt="coin">
            </div>
        </div>
    </header>

    <!-- Filter Bar matching user_bid_history.php 100% -->
    <div class="filter-bar">
        <div class="filter-input-wrap">
            <i class="far fa-calendar-alt"></i>
            <input type="date" id="filterFrom" value="<?= $today ?>">
        </div>
        <div class="filter-input-wrap">
            <i class="far fa-calendar-alt"></i>
            <input type="date" id="filterTo" value="<?= $today ?>">
        </div>
        <button id="applyFilterBtn" class="filter-apply-btn" onclick="filterStatement()"><?= __("Apply") ?></button>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="summary-card" id="summaryCard" style="display: none;">
            <div class="summary-total"><?= __("Total Winnings:") ?> <strong id="totalWin">₹0.00</strong></div>
        </div>

        <div id="loader"><span class="lds-dual-ring"></span></div>
        <div class="win-list"></div>

        <div class="load-more-container">
            <button id="loadMoreBtn" class="load-more-btn"><?= __("Load More") ?></button>
        </div>
    </div>

    <script>
        var list = document.querySelector('.win-list');
        var load = document.getElementById('loader');
        var loadMoreBtn = document.getElementById('loadMoreBtn');
        var filterFrom = document.getElementById('filterFrom');
        var filterTo = document.getElementById('filterTo');
        var totalWinEl = document.getElementById('totalWin');
        var summaryCard = document.getElementById('summaryCard');

        var data = [], limit = 20, per = 20;

        function filterStatement() {
            var fromVal = filterFrom ? filterFrom.value : IST_TODAY;
            var toVal = filterTo ? filterTo.value : IST_TODAY;
            fetchWins(fromVal, toVal);
        }

        function fetchWins(from, to) {
            load.style.display = 'block';
            list.innerHTML = '';
            if (summaryCard) summaryCard.style.display = 'none';

            fetch("user_wining.php?ajax=1&from=" + encodeURIComponent(from) + "&to=" + encodeURIComponent(to), { cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                data = Array.isArray(d) ? d : [];
                limit = 20;
                load.style.display = 'none';
                render();
            })
            .catch(function() {
                data = [];
                limit = 20;
                load.style.display = 'none';
                renderEmptyState();
            });
        }

        function renderEmptyState() {
            list.innerHTML = `
                <div class="empty-state-wrapper">
                    <div class="illustration-container">
                        <img src="icons/loading.svg" alt="Loading" style="width: 50px; height: 50px; margin-bottom: 10px;">
                    </div>
                    <div class="empty-state-text">No Results Found!</div>
                </div>
            `;
            if (summaryCard) summaryCard.style.display = 'none';
            if (totalWinEl) totalWinEl.textContent = '₹0.00';
            if (loadMoreBtn) loadMoreBtn.style.display = 'none';
        }

        function render(append) {
            if (!append) {
                list.innerHTML = '';
            }
            if (!data.length) {
                renderEmptyState();
                return;
            }

            if (summaryCard) summaryCard.style.display = 'flex';
            var sum = data.reduce(function(s, w) { return s + parseFloat(w.wining_balance || 0); }, 0);
            if (totalWinEl) totalWinEl.textContent = '₹' + sum.toLocaleString('en-IN', { minimumFractionDigits: 2 });

            var s = append ? list.children.length : 0;
            var e = Math.min(limit, data.length);

            var bidTypesDict = {
                'SINGLE DIGIT': '<?= __("Single Digit") ?>',
                'JODI DIGIT': '<?= __("Jodi Digit") ?>',
                'SINGLE PANA': '<?= __("Single Pana") ?>',
                'DOUBLE PANA': '<?= __("Double Pana") ?>',
                'TRIPLE PANA': '<?= __("Triple Pana") ?>',
                'HALF SANGAM': '<?= __("Half Sangam") ?>',
                'FULL SANGAM': '<?= __("Full Sangam") ?>',
                'LEFT DIGIT': '<?= __("Left Digit") ?>',
                'RIGHT DIGIT': '<?= __("Right Digit") ?>',
                'ANDAR HARUF': '<?= __("Andar Haruf") ?>',
                'BAHAR HARUF': '<?= __("Bahar Haruf") ?>',
                'CROSSING': '<?= __("Crossing") ?>',
                'WITH PALTI': '<?= __("With Palti") ?>',
                'WITHOUT PALTI': '<?= __("Without Palti") ?>'
            };

            var sessionLabels = {
                'OPEN': '<?= __("OPEN") ?>',
                'CLOSE': '<?= __("CLOSE") ?>'
            };

            for (var i = s; i < e; i++) {
                var w = data[i];
                var dt = new Date((w.created_at || '').replace(' ', 'T'));
                var dateStr = dt.toLocaleDateString('<?= $_SESSION["lang"] ?? "en" ?>-IN', { day: '2-digit', month: 'short', year: 'numeric' });
                var timeStr = dt.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });

                var bidTypeStr = String(w.game_type || 'Bet').replace(/_/g, ' ').toUpperCase();
                var bidType = bidTypesDict[bidTypeStr] || bidTypeStr;
                var session = String(w.session_type || '').toUpperCase();
                var bidAmount = parseFloat(w.amount || 0);
                var winAmount = parseFloat(w.wining_balance || 0);
                var betKey = w.bet_key || 'N/A';
                var parsedNum = betKey;
                if (betKey.includes('|')) {
                    var parts = betKey.split('|');
                    for (var p of parts) {
                        if (p.includes(':') && !p.startsWith('BID:') && !p.startsWith('MK') && !p.startsWith('GR') && !p.startsWith('TX')) {
                            parsedNum = p.split(':')[1] || p;
                            break;
                        }
                    }
                }

                var html = '<div class="win-box">' +
                    '<div class="row-flex">' +
                        '<h2 class="game-name">' + (w.market || '') + '</h2>' +
                        '<div style="display:flex; align-items:center; gap:6px;">' +
                            '<div class="session-chip"><?= __("Session:") ?> ' + (sessionLabels[session] || session) + '</div>' +
                            '<div class="no-chip">No: ' + parsedNum + '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="row-flex" style="margin-top:4px; margin-bottom:4px;">' +
                        '<div style="flex:1;">' +
                            '<div class="win-val"><?= __("Win") ?> ₹' + winAmount.toLocaleString('en-IN') + '</div>' +
                            '<div class="bid-amount"><?= __("Bid") ?> ₹' + bidAmount.toLocaleString('en-IN') + '</div>' +
                        '</div>' +
                        '<div class="win-center-text">WIN</div>' +
                        '<div class="win-side-tags" style="flex:1; display:flex; justify-content:flex-end;">' +
                            '<div class="info-tag">' + bidType + '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="footer-info">' +
                        '<span><i class="far fa-calendar-alt"></i> ' + dateStr + '</span>' +
                        '<span><i class="far fa-clock"></i> ' + timeStr + '</span>' +
                    '</div>' +
                '</div>';

                list.insertAdjacentHTML('beforeend', html);
            }

            if (loadMoreBtn) {
                if (limit < data.length) {
                    loadMoreBtn.style.display = 'inline-block';
                } else {
                    loadMoreBtn.style.display = 'none';
                }
            }
        }

        if (loadMoreBtn) {
            loadMoreBtn.onclick = function() {
                limit += per;
                render(true);
            };
        }

        // Initial fetch
        fetchWins(IST_TODAY, IST_TODAY);
    </script>
    <script src="assets/js/user_notice_tracker.js?v=4"></script>
</body>
</html>
