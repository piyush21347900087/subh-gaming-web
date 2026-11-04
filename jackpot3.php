<?php
session_start();
require_once 'includes/db.php';
date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');
require_once 'includes/user_ping.php';
require_once 'includes/live_ping.php';

if (!isset($_SESSION['user']['id'])) {
    header('Location: user_login.php');
    exit;
}

$uid = (int) $_SESSION['user']['id'];
$db = db();
$db->set_charset('utf8mb4');

// User Info
$stmt = $db->prepare("SELECT user_id, name, mobile_no, created_at, is_active FROM users WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$userName = $user['name'] ?? 'User';
$code = $user['user_id'];
if (preg_match('/^\d+$/', $code)) {
    $code = "AR" . str_pad($code, 2, "0", STR_PAD_LEFT);
} elseif (preg_match('/^(AR|BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $code, $m)) {
    $code = $m[1] . str_pad($m[2], 2, "0", STR_PAD_LEFT);
}

// Balance logic for Jackpot 3 (Synchronized with deposit.php header)
$hasBonusCol = false;
$resB = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
if ($resB && $resB->num_rows > 0) { $hasBonusCol = true; }
$bonusCountsInBalance = $hasBonusCol && function_exists('is_deposit_bonus_enabled') && is_deposit_bonus_enabled($db);

$st = $db->prepare("
    SELECT 
        (SELECT (IFNULL(SUM(amount),0) + " . ($bonusCountsInBalance ? "IFNULL(SUM(bonus_amount),0)" : "0") . ") FROM deposit WHERE status='Done' AND user_id=?)
      - (SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE user_id=? AND LOWER(status) IN ('pending','approved','done')) AS wallet,
        (SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id=?) + (SELECT IFNULL(SUM(wining_balance),0) FROM wining3 WHERE user_id=?) AS win
");
$st->bind_param('ssss', $code, $code, $code, $code);
$st->execute();
$st->bind_result($walletBal, $winBal);
$st->fetch();
$st->close();

$totalBalance = (float)$walletBal + (float)$winBal;

// Fetch Jackpot 3 Markets
$jackpotMarkets = [];
$resM = $db->query("SELECT * FROM markets3 WHERE is_published = 1 ORDER BY sort_order ASC, id ASC");
if ($resM) {
    while ($row = $resM->fetch_assoc()) {
        $jackpotMarkets[] = $row;
    }
}
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Raj Jackpot – ANKRAJ 666</title>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root { --primary-blue: #3498db; --bg-gray: #f8f9fa; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: var(--bg-gray); padding-bottom: 80px; }
        .header { height: 70px; background: #3498db; display: flex; align-items: center; padding: 0 16px; gap: 12px; position: fixed; top: 0; width: 100%; z-index: 1000; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; color: #fff; }
        .back-btn { width: 38px; height: 38px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; flex-shrink: 0; }
        .header-title { flex: 1; font-weight: 700; text-transform: uppercase; font-size: 16px; text-align: center; }
        .header-wallet { display: flex; align-items: center; gap: 5px; background: rgba(255,255,255,0.18); border-radius: 20px; padding: 5px 12px; font-weight: 700; font-size: 14px; color: #fff; text-decoration: none; flex-shrink: 0; }
        .action-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
        .btn-history { flex: 1; background: #fff; border: 1.5px solid #eee; padding: 12px; border-radius: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; color: #555; font-size: 13px; font-weight: 700; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .btn-history i { color: #3498db; font-size: 16px; }
        .notify-toggle { background: #fff; border: 1px solid #eef2f6; border-radius: 14px; padding: 11px 12px; margin: -8px 0 18px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .notify-title { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 900; color: #222; }
        .notify-title i { color: #3498db; }
        .notify-right { display: flex; align-items: center; }
        .notify-state { font-size: 11px; font-weight: 900; color: #16a34a; margin-right: 10px; }
        .notify-state.off { color: #ef4444; }
        .notify-switch { position: relative; width: 46px; height: 26px; flex: 0 0 auto; }
        .notify-switch input { opacity: 0; width: 0; height: 0; }
        .notify-slider { position: absolute; cursor: pointer; inset: 0; background: #d1d5db; border-radius: 999px; transition: 0.2s; }
        .notify-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.18); }
        .notify-switch input:checked + .notify-slider { background: #3498db; }
        .notify-switch input:checked + .notify-slider:before { transform: translateX(20px); }
        .rate-pill-container { text-align: center; margin-bottom: 25px; position: relative; }
        .rate-pill-container::after { content: ''; position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: #eee; z-index: 1; }
        .rate-pill { position: relative; z-index: 2; background: #fff; border: 1.5px solid #ddd; padding: 6px 25px; border-radius: 30px; display: inline-block; font-size: 15px; font-weight: 700; color: #333; }
        .rate-pill span { color: #3498db; margin-left: 5px; }
        .page-container { margin-top: 90px; padding: 0 10px; }
        .game-list { padding: 0; }
        .game-card-h { background: #fff; border: 1px solid #eef2f6; border-radius: 18px; padding: 15px 12px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); transition: transform 0.2s; }
        .game-card-h:active { transform: scale(0.98); }
        .card-left { display: flex; flex-direction: column; gap: 4px; flex: 1; }
        .game-time { font-size: 18px; font-weight: 800; color: #000; line-height: 1.2; }
        .game-status { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.3px; }
        .status-running { color: #3498db; }
        .status-closed { color: #e74c3c; }
        .card-middle { flex: 1; display: flex; align-items: center; justify-content: center; }
        .result-pill { background: #fff; border: 1px solid #eee; width: 80px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 800; color: #333; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); }
        .card-right { flex: 1; display: flex; justify-content: flex-end; }
        .play-btn-pill { background: #576574; color: #fff; padding: 8px 18px; border-radius: 30px; text-decoration: none; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .play-btn-pill.active { background: #3498db; }
        
        /* Popup Modal Styles */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 5000;
        }

        .popup-box {
            width: 88%;
            max-width: 310px;
            background: #ffffff;
            border-radius: 18px;
            padding: 18px 16px;
            text-align: center;
            transform: scale(0.8);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            color: #333;
            border: 3px solid #3498db;
            box-shadow: 0 15px 40px rgba(52, 152, 219, 0.3);
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
            font-size: 46px;
            margin-bottom: 10px;
            filter: drop-shadow(0 5px 15px rgba(52, 152, 219, 0.4));
        }

        .popup-icon i {
            display: inline-block;
            background: linear-gradient(135deg, #3498db 0%, #00c6ff 50%, #3498db 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            color: #3498db;
            animation: iconWobble 0.8s infinite alternate ease-in-out;
        }

        @keyframes iconWobble {
            0% { transform: scale(0.9) rotate(-8deg); }
            100% { transform: scale(1.1) rotate(8deg); }
        }

        .popup-box h2 {
            font-size: 19px;
            font-weight: 900;
            margin-bottom: 12px;
            color: #3498db;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .popup-box p {
            font-size: 12px;
            color: #666;
            margin-bottom: 12px;
            line-height: 1.4;
            font-weight: 500;
        }

        .popup-close-btn {
            background: linear-gradient(135deg, #3498db 0%, #00c6ff 50%, #3498db 100%);
            color: #fff;
            border: none;
            padding: 9px 36px;
            border-radius: 30px;
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: transform 0.2s;
        }

        .popup-close-btn:active {
            transform: scale(0.95);
        }

        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 95px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: #3498db;
            color: #ffffff;
            border: none;
            border-radius: 50%;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            cursor: pointer;
            z-index: 999;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            line-height: 1;
            transition: transform 0.2s;
        }
        .back-to-top:active { transform: scale(0.9); }
        .back-to-top span { font-size: 8px; font-weight: 900; margin-top: 3px; text-transform: uppercase; text-align: center; }
        .back-to-top i { animation: bounceUp 1.2s infinite ease-in-out; }
        @keyframes bounceUp {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <div class="header">
        <a href="user_dashboard.php" class="back-btn"><i class="fa-solid fa-chevron-left"></i></a>
        <span class="header-title">Raj Jackpot</span>
        <a href="jackpot3_history.php" style="text-decoration:none; color:#fff; font-size:12px; font-weight:800; background:rgba(255,255,255,0.2); padding:6px 12px; border-radius:20px; text-transform:uppercase;">RESULT</a>
    </div>
    <div class="page-container">
        <div class="action-buttons">
            <a href="user_jackpot3_win_history.php" class="btn-history">
                <i class="fas fa-trophy"></i> WIN HISTORY
            </a>
            <a href="user_jackpot_bid_history.php" class="btn-history">
                <i class="fas fa-list-ul"></i> BID HISTORY
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

        <div class="rate-pill-container">
            <div class="rate-pill">JODI <span>10-950</span></div>
        </div>

        <div class="game-list">
            <?php
            $curTime = date('H:i:s');
            foreach ($jackpotMarkets as $m):
                $openStr = date('h:i A', strtotime($m['open_time']));
                $closeStr = date('h:i A', strtotime($m['close_time']));
                $closeT = date('H:i:s', strtotime($m['close_time']));
                $isClosed = ($curTime >= $closeT);
                $statusText = $isClosed ? 'CLOSED FOR TODAY' : 'RUNNING NOW';
                $statusClass = $isClosed ? 'status-closed' : 'status-running';
                $btnClass = $isClosed ? '' : 'active';
                
                // Fetch actual result for today
                $resVal = '**';
                $stmtR = $db->prepare("SELECT result_str FROM market_result3 WHERE market = ? AND result_date = ? LIMIT 1");
                $stmtR->bind_param("ss", $m['market_name'], $today);
                $stmtR->execute();
                $resData = $stmtR->get_result()->fetch_assoc();
                $stmtR->close();
                if ($resData) {
                    $resVal = $resData['result_str'];
                }

                $gameUrl = "user_market_games3.php?id=" . $m['id'] . "&market=" . urlencode($m['market_name']);
            ?>
                <div class="game-card-h" 
                     data-name="<?= htmlspecialchars($m['market_name']) ?>" 
                     data-open-str="<?= $openStr ?>" 
                     data-close-str="<?= $closeStr ?>"
                     onclick="handleMarketClick(this, '<?= $gameUrl ?>', <?= $isClosed ? 'false' : 'true' ?>)">
                    <div class="card-left">
                        <span class="game-time"><?= $closeStr ?></span>
                        <span class="game-status <?= $statusClass ?>"><?= $statusText ?></span>
                    </div>
                    <div class="card-middle">
                        <div class="result-pill"><?= $resVal ?></div>
                    </div>
                    <div class="card-right">
                        <a href="<?= $btnClass ? $gameUrl : 'javascript:void(0)' ?>" class="play-btn-pill <?= $btnClass ? 'active' : '' ?>" onclick="handlePlayClick(event, this.parentElement.parentElement, '<?= $gameUrl ?>', <?= $isClosed ? 'false' : 'true' ?>)">
                            <i class="fa-solid fa-circle-play"></i> PLAY
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Auto-Declare Trigger (Hidden Ping) -->
            <img src="auto_declare_jackpot3.php" style="display:none; width:0; height:0;" alt="">
            
            <?php if (empty($jackpotMarkets)): ?>

                <div style="text-align:center; padding: 20px;">No markets available.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Closed Popup Modal -->
    <div id="closedPopup" class="popup-overlay" onclick="closeClosedPopup(event)">
        <div class="popup-box" onclick="event.stopPropagation()">
            <div class="popup-icon">
                <i class="fa-solid fa-store-slash"></i>
            </div>
            <h2 style="font-weight: 900; margin-bottom: 4px;">MARKET IS CLOSED</h2>
            <p style="font-size: 12px; color: #666; margin-bottom: 8px; font-weight: 500;">Bidding is currently unavailable for this market.</p>
            <div id="popupMarketTime"
                style="margin-top: 8px; max-height: 220px; overflow-y: auto; border-radius: 10px; border: 1px solid #eee;">
                <table style="width: 100%; border-collapse: collapse; font-size: 11px; text-align: left;">
                    <thead style="background: #f8f9fa; position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th style="padding: 7px; border-bottom: 1px solid #eee;">MARKET</th>
                            <th style="padding: 7px; border-bottom: 1px solid #eee;">OPEN</th>
                            <th style="padding: 7px; border-bottom: 1px solid #eee;">CLOSE</th>
                        </tr>
                    </thead>
                    <tbody id="timetableBody">
                        <!-- Populated via JS -->
                    </tbody>
                </table>
            </div>
            <button class="popup-close-btn" style="margin-top: 12px;" onclick="closeClosedPopup()">Close</button>
        </div>
    </div>

    <script>
        function handlePlayClick(event, cardEl, link, isOpen) {
            event.preventDefault();
            event.stopPropagation();
            if (isOpen) {
                window.location.href = link;
            } else {
                handleMarketClick(cardEl, link, false);
            }
        }

        function handleMarketClick(el, link, isOpen) {
            if (isOpen) {
                window.location.href = link;
            } else {
                if (navigator.vibrate) { navigator.vibrate([70, 40, 70]); }
                
                const tbody = document.getElementById('timetableBody');
                tbody.innerHTML = '';
                
                const mName = el.getAttribute('data-name');
                const mOpen = el.getAttribute('data-open-str');
                const mClose = el.getAttribute('data-close-str');

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="padding: 7px; font-weight: 700; color: #111;">${mName}</td>
                    <td style="padding: 7px; color: #2ecc71; font-weight: 600;">${mOpen}</td>
                    <td style="padding: 7px; color: #e74c3c; font-weight: 600;">${mClose}</td>
                `;
                tbody.appendChild(tr);

                const popup = document.getElementById('closedPopup');
                popup.style.display = 'flex';
                setTimeout(() => popup.classList.add('show'), 10);
            }
        }

        function closeClosedPopup(e) {
            const popup = document.getElementById('closedPopup');
            popup.classList.remove('show');
            setTimeout(() => popup.style.display = 'none', 300);
        }

        function resultNotifyKey(game) { return 'result_notify_' + game; }
        function readResultNotify(game) {
            try {
                if (window.AndroidNotify && AndroidNotify.isResultNotificationEnabled) {
                    return !!AndroidNotify.isResultNotificationEnabled(game);
                }
            } catch (e) {}
            try { return localStorage.getItem(resultNotifyKey(game)) !== '0'; } catch (e) { return true; }
        }
        function saveResultNotify(game, enabled) {
            try {
                if (window.AndroidNotify && AndroidNotify.setResultNotificationEnabled) {
                    AndroidNotify.setResultNotificationEnabled(game, enabled ? '1' : '0');
                }
            } catch (e) {}
            try { localStorage.setItem(resultNotifyKey(game), enabled ? '1' : '0'); } catch (e) {}
        }
        (function initResultNotifyToggle() {
            const game = 'jackpot3';
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

        // Live wallet balance refresh
        function refreshWallet() {
            fetch('get_latest_balance.php', { cache: 'no-store' })
                .then(r => r.json())
                .then(d => {
                    if (d && typeof d.total !== 'undefined') {
                        const el = document.getElementById('walletAmt');
                        if (el) el.textContent = Math.floor(d.total).toLocaleString('en-IN');
                    }
                }).catch(() => {});
        }
        refreshWallet();
        setInterval(refreshWallet, 30000);
        
        // Auto-declare ping
        function pingAutoDeclare() {
            fetch('auto_declare_jackpot3.php').catch(() => {});
        }
        setTimeout(pingAutoDeclare, 2000); // First run after 2s
        setInterval(pingAutoDeclare, 60000); // Every 60s

        // Scroll to Top
        (function() {
            const upBtn = document.createElement('button');
            upBtn.className = 'back-to-top';
            upBtn.innerHTML = '<i class="fa-solid fa-arrow-up"></i><span>SCROLL UP</span>';
            document.body.appendChild(upBtn);

            window.addEventListener('scroll', () => {
                if (window.scrollY > 300) upBtn.style.display = 'flex';
                else upBtn.style.display = 'none';
            }, { passive: true });

            upBtn.onclick = () => window.scrollTo({ top: 0, behavior: 'smooth' });
        })();
    </script>

    <?php $db->close(); ?>
</body>
</html>
