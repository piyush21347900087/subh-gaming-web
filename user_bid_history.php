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

function send_bids($db, $code, $uid, $from, $to) {
  $fromStr = trim($from) . " 00:00:00";
  $toStr = trim($to) . " 23:59:59";
  $data = [];
  $uidStr = (string) $uid;

  $hasGali = false;
  $resG = $db->query("SHOW TABLES LIKE 'gali_bids'");
  if ($resG && $resG->num_rows > 0) {
      $hasGali = true;
  }

  if ($hasGali) {
      $sql = "(SELECT CONVERT(market USING utf8mb4) AS market, CONVERT(session_type USING utf8mb4) AS session_type, CONVERT(game_type USING utf8mb4) AS game_type, 'Done' AS status, CONVERT(single_digit USING utf8mb4) AS single_digit, CONVERT(open_digit USING utf8mb4) AS open_digit, CONVERT(close_digit USING utf8mb4) AS close_digit, CONVERT(jodi_digit USING utf8mb4) AS jodi_digit, CONVERT(single_pana USING utf8mb4) AS single_pana, CONVERT(double_pana USING utf8mb4) AS double_pana, CONVERT(triple_pana USING utf8mb4) AS triple_pana, CONVERT(open_pana USING utf8mb4) AS open_pana, CONVERT(close_pana USING utf8mb4) AS close_pana, amount, bid_time AS raw_date, CONVERT(DATE_FORMAT(bid_time, '%d %b %Y') USING utf8mb4) AS bid_date, CONVERT(DATE_FORMAT(bid_time, '%h:%i %p') USING utf8mb4) AS bid_clock FROM bid WHERE user_id IN (?, ?) AND bid_time BETWEEN ? AND ?)
              UNION ALL
              (SELECT CONVERT(market_name USING utf8mb4) AS market, 'O/C' AS session_type, CONVERT(game_type USING utf8mb4) AS game_type, 'Done' AS status, CONVERT(digit USING utf8mb4) AS single_digit, NULL AS open_digit, NULL AS close_digit, NULL AS jodi_digit, NULL AS single_pana, NULL AS double_pana, NULL AS triple_pana, NULL AS open_pana, NULL AS close_pana, amount, created_at AS raw_date, CONVERT(DATE_FORMAT(created_at, '%d %b %Y') USING utf8mb4) AS bid_date, CONVERT(DATE_FORMAT(created_at, '%h:%i %p') USING utf8mb4) AS bid_clock FROM gali_bids WHERE user_id IN (?, ?) AND created_at BETWEEN ? AND ?)
              ORDER BY raw_date DESC";
      if ($stmt = $db->prepare($sql)) {
          $stmt->bind_param('ssssssss', $code, $uidStr, $fromStr, $toStr, $code, $uidStr, $fromStr, $toStr);
          $stmt->execute();
          $res = $stmt->get_result();
          while ($r = $res->fetch_assoc()) {
              $data[] = $r;
          }
          $stmt->close();
      }
  } else {
      $sql = "SELECT market, session_type, game_type, 'Done' AS status, single_digit, open_digit, close_digit, jodi_digit, single_pana, double_pana, triple_pana, open_pana, close_pana, amount, bid_time AS raw_date, DATE_FORMAT(bid_time, '%d %b %Y') AS bid_date, DATE_FORMAT(bid_time, '%h:%i %p') AS bid_clock FROM bid WHERE user_id IN (?, ?) AND bid_time BETWEEN ? AND ? ORDER BY bid_time DESC";
      if ($stmt = $db->prepare($sql)) {
          $stmt->bind_param('ssss', $code, $uidStr, $fromStr, $toStr);
          $stmt->execute();
          $res = $stmt->get_result();
          while ($r = $res->fetch_assoc()) {
              $data[] = $r;
          }
          $stmt->close();
      }
  }

  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

$today = date('Y-m-d');
$lastweek = date('Y-m-d', strtotime('-6 days'));

if (isset($_GET['ajax'])) {
  send_bids($db, $code, $uid, $_GET['from'] ?? $today, $_GET['to'] ?? $today);
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
    <title><?= __("Bid History") ?> | SUBH GAMING</title>
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

        /* ── Header Standardized matching withdrawal_history.php 100% ── */
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

        /* ── Filter Bar matching withdrawal_history.php 100% ── */
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
        .summary-total strong { color: #e53935; font-size: 16px; margin-left: 6px; }

        .bid-box {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            padding: 12px 14px;
            position: relative;
            border-left: 4px solid #e53935;
            margin-bottom: 10px;
            border-top: 1px solid #f0f0f0;
            border-right: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }

        .row-flex { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 4px; }
        .game-name { font-size: 14px; font-weight: 800; color: #e53935; text-transform: uppercase; margin: 0; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .session-chip { font-size: 10px; font-weight: 700; color: #0066cc; background: #eaf4ff; border: 1px solid rgba(0,102,204,0.16); padding: 2px 8px; border-radius: 999px; text-transform: uppercase; white-space: nowrap; flex-shrink: 0; }
        .bid-type-line { font-size: 12px; font-weight: 600; color: #666666; }
        .bid-val { font-size: 15px; font-weight: 800; color: #111111; }
        .bid-amount { font-size: 15px; font-weight: 800; color: #ff5252; }
        .bid-time-footer { display: flex; justify-content: space-between; font-size: 11px; color: #888888; font-weight: 500; border-top: 1px solid #f5f5f5; padding-top: 6px; margin-top: 6px; }
        .bid-time-footer span { display: flex; align-items: center; gap: 4px; color: #e53935; font-weight: 600; }

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

    <!-- Header identical to withdrawal_history.php -->
    <header>
        <div class="header-left">
            <a class="back-btn" href="javascript:history.back()" title="Back">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <div class="header-title-flash">
                <img src="icons/gold_coin.svg" alt="coin">
                <?= __("Bid History") ?>
                <img src="icons/gold_coin.svg" alt="coin">
            </div>
        </div>
    </header>

    <!-- Filter Bar matching withdrawal_history.php 100% -->
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
            <div class="summary-total"><?= __("Total Bid:") ?> <strong id="totalBid">₹0.00</strong></div>
        </div>

        <div id="loader"><span class="lds-dual-ring"></span></div>
        <div class="box-wrapper"></div>

        <div class="load-more-container">
            <button id="loadMoreBtn" class="load-more-btn"><?= __("Load More") ?></button>
        </div>
    </div>

    <script>
        var wrap = document.querySelector('.box-wrapper');
        var load = document.getElementById('loader');
        var loadMoreBtn = document.getElementById('loadMoreBtn');
        var filterFrom = document.getElementById('filterFrom');
        var filterTo = document.getElementById('filterTo');
        var totalBidEl = document.getElementById('totalBid');
        var summaryCard = document.getElementById('summaryCard');

        var data = [], limit = 20, per = 20;

        function filterStatement() {
            var fromVal = filterFrom ? filterFrom.value : IST_TODAY;
            var toVal = filterTo ? filterTo.value : IST_TODAY;
            fetchRows(fromVal, toVal);
        }

        function fetchRows(from, to) {
            load.style.display = 'block';
            wrap.innerHTML = '';
            if (summaryCard) summaryCard.style.display = 'none';

            fetch("user_bid_history.php?ajax=1&from=" + encodeURIComponent(from) + "&to=" + encodeURIComponent(to), { cache: 'no-store' })
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
            wrap.innerHTML = `
                <div class="empty-state-wrapper">
                    <div class="illustration-container">
                        <img src="icons/loading.svg" alt="Loading" style="width: 50px; height: 50px; margin-bottom: 10px;">
                    </div>
                    <div class="empty-state-text">No Results Found!</div>
                </div>
            `;
            if (summaryCard) summaryCard.style.display = 'none';
            if (totalBidEl) totalBidEl.textContent = '₹0.00';
            if (loadMoreBtn) loadMoreBtn.style.display = 'none';
        }

        function analyzeBid(b) {
            var gt = (b.game_type || '').toUpperCase();
            var label = 'Bet', val = '-';
            if (gt === 'LEFT DIGIT') {
                label = 'Left Digit'; val = b.single_digit;
            } else if (gt === 'ANDAR HARUF') {
                label = 'Andar Haruf'; val = b.single_digit;
            } else if (gt === 'RIGHT DIGIT') {
                label = 'Right Digit'; val = b.single_digit;
            } else if (gt === 'BAHAR HARUF') {
                label = 'Bahar Haruf'; val = b.single_digit;
            } else if (gt === 'JODI DIGIT') {
                label = 'Jodi Digit'; val = b.single_digit;
            } else if (gt === 'CROSSING') {
                label = 'Crossing'; val = b.single_digit;
            } else if (gt === 'WITH PALTI') {
                label = 'With Palti'; val = b.single_digit;
            } else if (gt === 'WITHOUT PALTI') {
                label = 'Without Palti'; val = b.single_digit;
            } else if (b.single_digit !== null && b.single_digit !== '') {
                label = 'Single Digit'; val = b.single_digit;
            } else if (b.jodi_digit !== null && b.jodi_digit !== '') {
                label = 'Jodi Digit'; val = b.jodi_digit;
            } else if (b.single_pana !== null && b.single_pana !== '') {
                label = 'Single Pana'; val = b.single_pana;
            } else if (b.double_pana !== null && b.double_pana !== '') {
                label = 'Double Pana'; val = b.double_pana;
            } else if (b.triple_pana !== null && b.triple_pana !== '') {
                label = 'Triple Pana'; val = b.triple_pana;
            } else if (gt.includes('SANGAM')) {
                var leftVal = b.open_digit !== null && b.open_digit !== '' ? b.open_digit : b.open_pana;
                var rightVal = b.close_digit !== null && b.close_digit !== '' ? b.close_digit : b.close_pana;
                label = gt.replace('_', ' ');
                val = leftVal + '-' + rightVal;
            }
            var sess = (b.session_type || '').toUpperCase();
            return { label: label, val: val, sess: label === 'Jodi Digit' ? 'O/C' : sess, type: label.toUpperCase() };
        }

        function render(append) {
            if (!append) {
                wrap.innerHTML = '';
            }
            if (!data.length) {
                renderEmptyState();
                return;
            }

            if (summaryCard) summaryCard.style.display = 'flex';
            var totalSum = data.reduce(function(s, b) { return s + (+b.amount || 0); }, 0);
            if (totalBidEl) totalBidEl.textContent = '₹' + totalSum.toLocaleString('en-IN', { minimumFractionDigits: 2 });

            var s = append ? wrap.children.length : 0;
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
                'CLOSE': '<?= __("CLOSE") ?>',
                'O/C': 'O/C'
            };

            for (var i = s; i < e; i++) {
                var b = data[i];
                var info = analyzeBid(b);
                var dt = new Date((b.raw_date || '').replace(' ', 'T'));
                var dateStr = b.raw_date ? dt.toLocaleDateString('<?= $_SESSION["lang"] ?? "en" ?>-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : b.bid_date || '';
                var timeStr = b.raw_date ? dt.toLocaleTimeString('<?= $_SESSION["lang"] ?? "en" ?>-IN', { hour: '2-digit', minute: '2-digit', hour12: true }) : b.bid_clock || '';

                var html = '<div class="bid-box">' +
                    '<div class="row-flex">' +
                        '<h2 class="game-name">' + (b.market || '') + '</h2>' +
                        '<div class="session-chip"><?= __("Session:") ?> ' + (sessionLabels[info.sess] || info.sess) + '</div>' +
                    '</div>' +
                    '<div class="row-flex" style="margin-top:4px;">' +
                        '<div>' +
                            '<div class="bid-type-line"><?= __("Bid Type:") ?> ' + (bidTypesDict[info.type] || info.type) + '</div>' +
                            '<div class="bid-val">' + info.val + '</div>' +
                        '</div>' +
                        '<div class="bid-amount">₹' + (+b.amount || 0).toLocaleString('en-IN') + '</div>' +
                    '</div>' +
                    '<div class="bid-time-footer">' +
                        '<span><i class="far fa-calendar-alt"></i> ' + dateStr + '</span>' +
                        '<span><i class="far fa-clock"></i> ' + timeStr + '</span>' +
                    '</div>' +
                '</div>';

                wrap.insertAdjacentHTML('beforeend', html);
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
        fetchRows(IST_TODAY, IST_TODAY);
    </script>
    <script src="assets/js/user_notice_tracker.js?v=4"></script>
</body>
</html>
