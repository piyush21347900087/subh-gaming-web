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

if (!function_exists('hasTable')) {
    function hasTable(mysqli $db, string $table): bool
    {
        try {
            $res = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
            return ($res && $res->num_rows > 0);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('fetch_one_val')) {
    function fetch_one_val($db, $sql, $types, $params)
    {
        try {
            $stmt = $db->prepare($sql);
            if (!$stmt) return null;
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $val = null;
            $stmt->bind_result($val);
            $out = $stmt->fetch() ? $val : null;
            $stmt->close();
            return $out;
        } catch (Throwable $e) {
            return null;
        }
    }
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

function send($db, $code, $uid, $from, $to)
{
    $fromStr = trim($from) . " 00:00:00";
    $toStr = trim($to) . " 23:59:59";
    $uidStr = (string) $uid;
    $codeStr = (string) $code;
    $out = [];

    try {
        if (hasTable($db, 'gali_bids')) {
            $hasDateCol = false;
            $resCol = $db->query("SHOW COLUMNS FROM `gali_bids` LIKE 'date'");
            if ($resCol && $resCol->num_rows > 0) {
                $hasDateCol = true;
            }

            if ($hasDateCol) {
                $sql = "SELECT market_name, game_type, digit, amount, created_at, status FROM gali_bids WHERE (user_id = ? OR user_id = ?) AND (date BETWEEN ? AND ? OR DATE(created_at) BETWEEN ? AND ?) ORDER BY id DESC";
                $q = $db->prepare($sql);
                if ($q) {
                    $q->bind_param('ssssss', $codeStr, $uidStr, $from, $to, $from, $to);
                    $q->execute();
                    $res = $q->get_result();
                    while ($r = $res->fetch_assoc()) {
                        $out[] = $r;
                    }
                    $q->close();
                }
            } else {
                $sql = "SELECT market_name, game_type, digit, amount, created_at, status FROM gali_bids WHERE (user_id = ? OR user_id = ?) AND created_at BETWEEN ? AND ? ORDER BY id DESC";
                $q = $db->prepare($sql);
                if ($q) {
                    $q->bind_param('ssss', $codeStr, $uidStr, $fromStr, $toStr);
                    $q->execute();
                    $res = $q->get_result();
                    while ($r = $res->fetch_assoc()) {
                        $out[] = $r;
                    }
                    $q->close();
                }
            }
        }
    } catch (Throwable $e) {
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$today = date('Y-m-d');
$lastweek = date('Y-m-d', strtotime('-6 days'));

if (isset($_GET['ajax'])) {
    $from = $_GET['from'] ?? $today;
    $to = $_GET['to'] ?? $today;
    send($db, $code, $uid, $from, $to);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no" />
    <meta name="theme-color" content="#000000">
    <title><?= __("Gali Bid History") ?> | SUBH GAMING</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <style>
        :root {
            --primary-bg: #000000;
            --card-bg: #ffffff;
            --btn-red: #e53935;
            --text-main: #ffffff;
            --text-dim: #94a3b8;
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

        /* ── Header Standardized ── */
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

        /* ── Filter Bar ── */
        .filter-bar {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 12px 10px;
            background: #121212;
            border-bottom: 1px solid #1a1a1a;
            max-width: 550px;
            margin: 0 auto;
        }

        .filter-btn {
            padding: 8px 20px;
            font-weight: 700;
            font-size: 13px;
            border-radius: 6px;
            border: 1px solid #e53935;
            background: #1a1a1a;
            color: #ffffff;
            cursor: pointer;
            transition: .2s ease-in-out;
        }

        .filter-btn.active {
            background: #e53935;
            color: #ffffff;
        }

        .content {
            max-width: 550px;
            margin: 0 auto;
            padding: 14px;
            min-height: calc(100vh - 130px);
        }

        .box-wrapper {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* Cards Design (White boxes) */
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
        .bid-type-line { font-size: 12px; font-weight: 600; color: #666666; }
        .bid-val { font-size: 15px; font-weight: 800; color: #111111; }
        .bid-amount { font-size: 15px; font-weight: 800; color: #e53935; }
        .bid-time-footer { display: flex; justify-content: space-between; font-size: 11px; color: #888888; font-weight: 500; border-top: 1px solid #f5f5f5; padding-top: 6px; margin-top: 6px; }
        .bid-time-footer span { display: flex; align-items: center; gap: 4px; color: #e53935; font-weight: 600; }

        .status-badge {
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 800;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid rgba(133,100,4,0.2);
        }

        .status-win {
            background: #d4edda;
            color: #155724;
            border: 1px solid rgba(21,87,36,0.2);
        }

        .status-loss {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid rgba(114,28,36,0.2);
        }

        /* Centered Empty State */
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

        #loader {
            display: none;
            text-align: center;
            font-weight: 700;
            margin: 20px 0;
        }

        .lds-dual-ring {
            display: inline-block;
            width: 30px;
            height: 30px;
        }

        .lds-dual-ring:after {
            content: " ";
            display: block;
            width: 24px;
            height: 24px;
            margin: 3px;
            border-radius: 50%;
            border: 3px solid #e53935;
            border-color: #e53935 transparent #e53935 transparent;
            animation: spin 1.2s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0); }
            100% { transform: rotate(360deg); }
        }

        .pager {
            display: flex;
            justify-content: center;
            gap: 14px;
            margin: 20px 0;
        }

        .pager button {
            background: #e53935;
            color: #ffffff;
            border: none;
            padding: 8px 18px;
            font-weight: 700;
            font-size: 13px;
            border-radius: 6px;
            min-width: 100px;
            cursor: pointer;
            transition: opacity 0.15s ease;
        }

        .pager button:disabled {
            opacity: .35;
            cursor: not-allowed;
        }
    </style>
    <script>
        var IST_TODAY = "<?php echo $today; ?>";
        var IST_LASTWEEK = "<?php echo $lastweek; ?>";
    </script>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
    <!-- Header -->
    <header>
        <div class="header-left">
            <a class="back-btn" href="javascript:history.back()" title="Back">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <div class="header-title-flash">
                <img src="icons/gold_coin.svg" alt="coin">
                <?= __("Gali Bid History") ?>
                <img src="icons/gold_coin.svg" alt="coin">
            </div>
        </div>
    </header>

    <div class="filter-bar">
        <button id="btnToday" class="filter-btn active"><?= __("Today") ?></button>
        <button id="btnWeek" class="filter-btn"><?= __("One Week") ?></button>
    </div>

    <div class="content">
        <div id="loader"><span class="lds-dual-ring"></span></div>
        <div class="box-wrapper"></div>

        <div class="pager">
            <button id="prev"><?= __("Previous") ?></button>
            <button id="next"><?= __("Next") ?></button>
        </div>
    </div>

    <script>
        var wrap = document.querySelector('.box-wrapper'),
            load = document.getElementById('loader'),
            prev = document.getElementById('prev'),
            next = document.getElementById('next'),
            btnToday = document.getElementById('btnToday'),
            btnWeek = document.getElementById('btnWeek');

        var data = [], page = 0, per = 10;

        function getToday() { return IST_TODAY; }
        function getLastWeek() { return IST_LASTWEEK; }

        function fetchRows(from, to) {
            load.style.display = 'block';
            wrap.innerHTML = '';

            fetch(`bid_history2.php?ajax=1&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`, {
                cache: 'no-store',
                credentials: 'same-origin'
            })
                .then(async function(r) {
                    var txt = await r.text();
                    try { return JSON.parse(txt); } catch (e) { return []; }
                })
                .then(function(d) { data = Array.isArray(d) ? d : []; page = 0; load.style.display = 'none'; render(); })
                .catch(function() { data = []; load.style.display = 'none'; render(); });
        }

        function formatTime(ts) {
            try {
                return new Date((ts || '').replace(' ', 'T')).toLocaleString('en-IN', {
                    day: '2-digit', month: 'short', year: 'numeric',
                    hour: '2-digit', minute: '2-digit', hour12: true
                });
            } catch (e) { return ts || ''; }
        }

        function render() {
            wrap.innerHTML = '';
            if (!data.length) {
                wrap.innerHTML = `
                    <div class="empty-state-wrapper">
                        <div class="illustration-container">
                            <img src="icons/loading.svg" alt="Loading" style="width: 50px; height: 50px; margin-bottom: 10px;">
                        </div>
                        <div class="empty-state-text">No Gali bids found.</div>
                    </div>
                `;
                prev.disabled = next.disabled = true;
                return;
            }

            var s = page * per, e = Math.min(s + per, data.length);
            for (var i = s; i < e; i++) {
                var b = data[i];

                var stClass = 'status-pending';
                var stText = b.status || 'Pending';
                if (stText.toLowerCase() === 'win') stClass = 'status-win';
                else if (stText.toLowerCase() === 'loss') stClass = 'status-loss';

                wrap.insertAdjacentHTML('beforeend', `
                    <div class="bid-box">
                        <div class="row-flex">
                            <h3 class="game-name">${(b.market_name || 'Gali').toUpperCase()}</h3>
                            <span class="status-badge ${stClass}">${stText}</span>
                        </div>
                        <div class="row-flex" style="margin-top: 6px;">
                            <span class="bid-type-line">${(b.game_type || '').toUpperCase()}</span>
                            <span class="bid-val">Digit: ${b.digit}</span>
                        </div>
                        <div class="bid-time-footer">
                            <span><i class="far fa-clock"></i> ${formatTime(b.created_at)}</span>
                            <span class="bid-amount">₹${(+b.amount || 0)}</span>
                        </div>
                    </div>
                `);
            }

            prev.disabled = page === 0;
            next.disabled = e === data.length;
        }

        btnToday.onclick = function() {
            btnToday.classList.add('active');
            btnWeek.classList.remove('active');
            fetchRows(getToday(), getToday());
        };

        btnWeek.onclick = function() {
            btnWeek.classList.add('active');
            btnToday.classList.remove('active');
            fetchRows(getLastWeek(), getToday());
        };

        prev.onclick = function() { if (page) { page--; render(); window.scrollTo({ top: 0, behavior: 'smooth' }); } };
        next.onclick = function() { if ((page + 1) * per < data.length) { page++; render(); window.scrollTo({ top: 0, behavior: 'smooth' }); } };

        document.addEventListener('DOMContentLoaded', function() { return btnToday.click(); });
    </script>
</body>

</html>
<?php $db->close(); ?>
