<?php
session_start();
require_once 'includes/db.php';
require_once 'process_starline4_wins.php';
date_default_timezone_set('Asia/Kolkata');
require_once 'includes/user_ping.php';
require_once 'includes/live_ping.php';

if (empty($_SESSION['user']['id'])) {
    header('Location: user_login.php');
    exit;
}

$uid = (int) $_SESSION['user']['id'];
$db = db();
$db->set_charset('utf8mb4');
starline4_ensure_schema($db);
starline4_seed_default_markets($db);
starline4_auto_generate_results($db);

$today = date('Y-m-d');
$todayDow = (int) date('N');
$curTime = date('H:i:s');

$stmt = $db->prepare("SELECT user_id, name, mobile_no, created_at, is_active FROM users WHERE id=? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$isDeactive = isset($user['is_active']) && (int) $user['is_active'] === 0;
$code = normalize_user_id($user['user_id'] ?? '');
$totalBalance = get_total_balance($db, $code);

$starlineMarkets = [];
$resM = $db->query("SELECT * FROM markets4 WHERE is_published=1 ORDER BY sort_order ASC, close_time ASC, id ASC");
if ($resM) {
    while ($row = $resM->fetch_assoc())
        $starlineMarkets[] = $row;
}
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <title>KISMAT STARLINE - SUBH GAMING</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <style>
        :root {
            --primary: #000000;
            --accent: #e53935;
            --bg: #000000;
            --shadow: 0 2px 10px rgba(0, 0, 0, .5)
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent
        }

        body {
            background: #000000 !important;
            color: #ffffff;
            overflow-x: hidden
        }

        .header {
            min-height: 70px;
            height: auto;
            background-color: #000000 !important;
            background-image: none !important;
            display: flex;
            align-items: center;
            padding-top: calc(env(safe-area-inset-top, 0px) + 16px);
            padding-bottom: 14px;
            padding-left: 16px;
            padding-right: 16px;
            gap: 12px;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            border-bottom: 1px solid #1a1a1a !important;
            color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .2);
        }

        .back-btn {
            width: 38px;
            height: 38px;
            background: #e53935;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-decoration: none;
            border: none;
            flex: 0 0 auto;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .2);
            font-size: 15px;
            transition: opacity 0.15s ease, transform 0.15s ease;
        }

        .back-btn:active {
            opacity: 0.85;
            transform: scale(0.95);
        }

        .header-result-btn {
            text-decoration: none;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            background: #e53935;
            padding: 7px 14px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .2);
            border: none;
            transition: opacity 0.15s ease, transform 0.15s ease;
        }

        .header-result-btn:active {
            opacity: 0.85;
            transform: scale(0.95);
        }

        .header-title {
            flex: 1;
            text-align: center;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .header-title img {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            margin-top: -2px;
        }

        .header-wallet {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #e53935;
            border-radius: 20px;
            padding: 6px 11px;
            font-weight: 800;
            font-size: 13px;
            color: #fff;
            text-decoration: none
        }

        .page-container {
            margin-top: calc(env(safe-area-inset-top, 0px) + 85px);
            padding: 0 12px;
            max-width: 550px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ── Action Buttons ── */
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        .btn-history {
            background: #ffffff;
            border: 1px solid rgba(139, 0, 0, 0.15);
            padding: 10px 12px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            text-decoration: none;
            color: #1a202c;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.3px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .btn-history:active {
            transform: scale(0.97);
        }

        .btn-icon-box {
            width: 34px;
            height: 34px;
            background: #e53935;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(229, 57, 53, 0.3);
        }

        .btn-icon-box img {
            width: 18px;
            height: 18px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        /* ── Result Notification Bar ── */
        .notify-toggle {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .notify-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 800;
            color: #1a202c;
            letter-spacing: 0.3px;
        }

        .notify-title i {
            color: #ffc107;
            font-size: 15px;
        }

        .notify-state {
            font-size: 11px;
            font-weight: 800;
            color: #16a34a;
            margin-right: 10px;
        }

        .notify-state.off {
            color: #ef4444;
        }

        .notify-right {
            display: flex;
            align-items: center;
        }

        .notify-switch {
            position: relative;
            width: 44px;
            height: 24px;
            flex: 0 0 auto;
        }

        .notify-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .notify-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #e2e8f0;
            border-radius: 999px;
            transition: .25s;
        }

        .notify-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: .25s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .2);
        }

        .notify-switch input:checked + .notify-slider {
            background: linear-gradient(135deg, #8b0000 0%, #e53935 100%);
        }

        .notify-switch input:checked + .notify-slider:before {
            transform: translateX(20px);
        }

        /* ── Rate Pills Grid ── */
        .rates-wrapper {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }

        .rate-pill {
            background: #ffffff;
            border: 1px solid rgba(139, 0, 0, 0.1);
            padding: 9px 10px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 10px;
            font-weight: 800;
            color: #4a5568;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
        }

        .rate-pill span {
            color: #8b0000;
            font-weight: 900;
            background: rgba(139, 0, 0, 0.08);
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 10.5px;
        }

        /* ── Game Market Cards ── */
        .game-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .game-card {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.07);
            border-radius: 14px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            position: relative;
            overflow: hidden;
        }

        .game-card:active {
            transform: scale(0.98);
        }

        .game-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #8b0000;
            border-radius: 4px 0 0 4px;
        }

        .card-left {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1.1;
        }

        .game-time {
            font-size: 17px;
            font-weight: 900;
            color: #1a202c;
            line-height: 1.2;
            letter-spacing: -0.2px;
        }

        .game-status {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .status-running {
            color: #16a34a;
        }

        .status-running::before {
            content: '';
            width: 6px;
            height: 6px;
            background: #16a34a;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 6px #16a34a;
        }

        .status-closed {
            color: #e53935;
        }

        .status-closed::before {
            content: '';
            width: 6px;
            height: 6px;
            background: #e53935;
            border-radius: 50%;
            display: inline-block;
        }

        .card-mid {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .result-pill {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            min-width: 84px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 900;
            color: #8b0000;
            letter-spacing: 1px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.04);
            font-family: 'Poppins', monospace;
        }

        .card-right {
            flex: 1;
            display: flex;
            justify-content: flex-end;
        }

        .play-btn {
            background: #cbd5e1;
            color: #64748b;
            padding: 8px 16px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.3px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .play-btn.active {
            background: linear-gradient(135deg, #8b0000 0%, #e53935 100%);
            color: #ffffff;
            box-shadow: 0 3px 10px rgba(229, 57, 53, 0.35);
        }

        .play-btn.active:active {
            transform: scale(0.94);
        }

        /* ── Popup Modal ── */
        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 5000;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .popup-box {
            width: 88%;
            max-width: 320px;
            background: #ffffff;
            border-radius: 18px;
            padding: 22px 18px;
            text-align: center;
            color: #1a202c;
            border: none;
            box-shadow: 0 15px 40px rgba(0, 0, 0, .3);
            transform: scale(.8);
            opacity: 0;
            transition: all .25s cubic-bezier(.34, 1.56, .64, 1);
            position: relative;
            overflow: hidden;
        }

        .popup-overlay.show {
            display: flex;
        }

        .popup-overlay.show .popup-box {
            transform: scale(1);
            opacity: 1;
        }

        .popup-icon {
            font-size: 44px;
            margin-bottom: 12px;
        }

        .popup-icon i {
            display: inline-block;
            background: linear-gradient(135deg, #8b0000 0%, #e60000 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            color: #8b0000;
            animation: iconWobble .8s infinite alternate ease-in-out;
        }

        @keyframes iconWobble {
            0% {
                transform: scale(.9) rotate(-8deg);
            }

            100% {
                transform: scale(1.1) rotate(8deg);
            }
        }

        .popup-box h2 {
            font-size: 18px;
            font-weight: 900;
            margin-bottom: 6px;
            color: #8b0000;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .popup-box p {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 14px;
            line-height: 1.4;
            font-weight: 500;
        }

        .popup-close {
            background: linear-gradient(135deg, #8b0000 0%, #e60000 100%);
            color: #fff;
            border: 0;
            padding: 10px 36px;
            border-radius: 12px;
            font-weight: 800;
            margin-top: 14px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(139, 0, 0, .35);
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        .popup-close:active {
            transform: scale(.95);
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body data-deactive="<?= $isDeactive ? '1' : '0' ?>">
    <div class="header">
        <a href="user_dashboard.php" class="back-btn"><i class="fa-solid fa-chevron-left"></i></a>
        <div class="header-title">
            <img src="icons/gold_coin.svg" alt="coin">
            KISMAT STARLINE
            <img src="icons/gold_coin.svg" alt="coin">
        </div>
        <a href="starline4_history.php" class="header-result-btn">RESULT</a>
    </div>

    <div class="page-container">
        <div class="action-buttons">
            <a href="user_starline4_win_history.php" class="btn-history">
                <div class="btn-icon-box"><img src="icons/win_history.svg" alt="Win History"></div>
                <span>WIN HISTORY</span>
            </a>
            <a href="user_starline4_bid_history.php" class="btn-history">
                <div class="btn-icon-box"><img src="icons/bid.svg" alt="Bid History"></div>
                <span>BID HISTORY</span>
            </a>
        </div>

        <div class="notify-toggle">
            <div class="notify-title"><i class="fas fa-bell"></i> RESULT NOTIFICATION</div>
            <div class="notify-right">
                <span id="resultNotifyState" class="notify-state">ON</span>
                <label class="notify-switch" aria-label="Result notification">
                    <input id="resultNotifyToggle" type="checkbox" checked>
                    <span class="notify-slider"></span>
                </label>
            </div>
        </div>

        <div class="rates-wrapper">
            <div class="rate-pill">SINGLE DIGIT <span>10-100</span></div>
            <div class="rate-pill">SINGLE PANA <span>10-1600</span></div>
            <div class="rate-pill">DOUBLE PANA <span>10-3200</span></div>
            <div class="rate-pill">TRIPLE PANA <span>10-9000</span></div>
        </div>

        <div class="game-list">
            <?php foreach ($starlineMarkets as $m):
                $closeT = date('H:i:s', strtotime($m['close_time'] ?? '00:00:00'));
                $isHoliday = !empty($m['today_closed']) || ($todayDow === 6 && !empty($m['saturday_closed'])) || ($todayDow === 7 && !empty($m['sunday_closed']));
                $isClosed = $isHoliday || ($curTime >= $closeT) || $isDeactive;
                $statusText = $isClosed ? 'CLOSED FOR TODAY' : 'RUNNING NOW';
                $statusClass = $isClosed ? 'status-closed' : 'status-running';
                $stmtR = $db->prepare("SELECT result_str FROM market_result4 WHERE market=? AND result_date=? LIMIT 1");
                $stmtR->bind_param('ss', $m['market_name'], $today);
                $stmtR->execute();
                $resData = $stmtR->get_result()->fetch_assoc();
                $stmtR->close();
                $resVal = $resData['result_str'] ?? '***-*';
                $gameUrl = "user_market_games4.php?id=" . (int) $m['id'] . "&market=" . urlencode($m['market_name']);
                ?>
                <div class="game-card" data-name="<?= htmlspecialchars($m['market_name']) ?>"
                    data-open-str="<?= date('h:i A', strtotime($m['open_time'] ?? '00:00:00')) ?>"
                    data-close-str="<?= date('h:i A', strtotime($m['close_time'])) ?>"
                    onclick="handleMarketClick(this,'<?= $gameUrl ?>',<?= $isClosed ? 'false' : 'true' ?>)">
                    <div class="card-left">
                        <span class="game-time"><?= date('h:i A', strtotime($m['close_time'])) ?></span>
                        <span class="game-status <?= $statusClass ?>"><?= $statusText ?></span>
                    </div>
                    <div class="card-mid">
                        <div class="result-pill"><?= htmlspecialchars($resVal) ?></div>
                    </div>
                    <div class="card-right">
                        <a href="<?= $isClosed ? 'javascript:void(0)' : $gameUrl ?>"
                            class="play-btn <?= $isClosed ? '' : 'active' ?>"
                            onclick="handlePlayClick(event,this.parentElement.parentElement,'<?= $gameUrl ?>',<?= $isClosed ? 'false' : 'true' ?>)"><i
                                class="fa-solid fa-circle-play"></i> PLAY</a>
                    </div>
                </div>
            <?php endforeach; ?>
            <img src="auto_declare_starline4.php" style="display:none;width:0;height:0" alt="">
            <?php if (empty($starlineMarkets)): ?>
                <div style="text-align:center;padding:20px">No markets available.</div><?php endif; ?>
        </div>
    </div>

    <div id="closedPopup" class="popup-overlay" onclick="closeClosedPopup(event)">
        <div class="popup-box" onclick="event.stopPropagation()">
            <div class="popup-icon">
                <i class="fa-solid fa-store-slash"></i>
            </div>
            <h2 style="font-weight:900;margin-bottom:4px;">MARKET IS CLOSED</h2>
            <p>Bidding is currently unavailable for this market.</p>
            <div id="popupMarketTime"
                style="margin-top:8px;max-height:220px;overflow-y:auto;border-radius:10px;border:1px solid #eee;">
                <table style="width:100%;border-collapse:collapse;font-size:11px;text-align:left;">
                    <thead style="background:#f8f9fa;position:sticky;top:0;z-index:1;">
                        <tr>
                            <th style="padding:7px;border-bottom:1px solid #eee;">MARKET</th>
                            <th style="padding:7px;border-bottom:1px solid #eee;">OPEN</th>
                            <th style="padding:7px;border-bottom:1px solid #eee;">CLOSE</th>
                        </tr>
                    </thead>
                    <tbody id="timetableBody"></tbody>
                </table>
            </div>
            <button class="popup-close" onclick="closeClosedPopup()">Close</button>
        </div>
    </div>


    <script>
        function handlePlayClick(event, card, link, isOpen) { event.preventDefault(); event.stopPropagation(); handleMarketClick(card, link, isOpen) }
        function handleMarketClick(el, link, isOpen) {
            if (isOpen) { window.location.href = link; return }
            triggerClosedVibration();

            const tbody = document.getElementById('timetableBody');
            tbody.innerHTML = '';

            const mName = el.getAttribute('data-name') || 'Market';
            const mOpen = el.getAttribute('data-open-str') || '--';
            const mClose = el.getAttribute('data-close-str') || '--';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="padding:7px;font-weight:700;color:#111;">${escapeHtml(mName)}</td>
                <td style="padding:7px;color:#2ecc71;font-weight:600;">${escapeHtml(mOpen)}</td>
                <td style="padding:7px;color:#e74c3c;font-weight:600;">${escapeHtml(mClose)}</td>
            `;
            tbody.appendChild(tr);

            const popup = document.getElementById('closedPopup');
            popup.style.display = 'flex';
            setTimeout(() => popup.classList.add('show'), 10);
        }
        function closeClosedPopup(e) {
            if (e && e.target.id !== 'closedPopup') return;
            const popup = document.getElementById('closedPopup');
            popup.classList.remove('show');
            setTimeout(() => popup.style.display = 'none', 300);
        }
        function triggerClosedVibration() {
            try { if (navigator.vibrate) navigator.vibrate([70, 40, 70]); } catch (e) { }
            try { if (window.AndroidNotify && AndroidNotify.vibrate) AndroidNotify.vibrate(140); } catch (e) { }
        }
        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, function (ch) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch];
            });
        }
        function resultNotifyKey(game) { return 'result_notify_' + game; }
        function readResultNotify(game) {
            try {
                if (window.AndroidNotify && AndroidNotify.isResultNotificationEnabled) {
                    return !!AndroidNotify.isResultNotificationEnabled(game);
                }
            } catch (e) { }
            try { return localStorage.getItem(resultNotifyKey(game)) !== '0'; } catch (e) { return true; }
        }
        function saveResultNotify(game, enabled) {
            try {
                if (window.AndroidNotify && AndroidNotify.setResultNotificationEnabled) {
                    AndroidNotify.setResultNotificationEnabled(game, enabled ? '1' : '0');
                }
            } catch (e) { }
            try { localStorage.setItem(resultNotifyKey(game), enabled ? '1' : '0'); } catch (e) { }
        }
        (function initResultNotifyToggle() {
            const game = 'starline4';
            const toggle = document.getElementById('resultNotifyToggle');
            const state = document.getElementById('resultNotifyState');
            if (!toggle || !state) return;
            function render(enabled) {
                toggle.checked = !!enabled;
                state.textContent = enabled ? 'ON' : 'OFF';
                state.classList.toggle('off', !enabled);
            }
            render(readResultNotify(game));
            toggle.addEventListener('change', function () {
                saveResultNotify(game, this.checked);
                render(this.checked);
            });
        })();
        function pingAutoDeclare() { fetch('auto_declare_starline4.php').catch(() => { }) }
        setTimeout(pingAutoDeclare, 1500);
        setInterval(pingAutoDeclare, 60000);
    </script>
    <?php $db->close(); ?>
</body>

</html>
