<?php
session_start();
require_once 'includes/db.php';
date_default_timezone_set('Asia/Kolkata');
require_once 'includes/user_ping.php';
require_once 'includes/live_ping.php';

if (!isset($_SESSION['user']['id'])) {
    header('Location: user_login.php');
    exit;
}

$uid = (int) $_SESSION['user']['id'];
$db = db();
$db->set_charset('utf8mb4');

$nowMinServer = (int) date('G') * 60 + (int) date('i');

// User Info
$stmt = $db->prepare("SELECT user_id, name, mobile_no, created_at, is_active FROM users WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$isDeactive = (isset($user['is_active']) && (int) $user['is_active'] === 0);
$userName = $user['name'] ?? 'User';
$userMobile = $user['mobile_no'] ?? '';
$userCreatedAt = $user['created_at'] ?? '';
$userSince = $userCreatedAt ? date('d-m-Y', strtotime($userCreatedAt)) : '';

$code = $user['user_id'];
if (preg_match('/^\d+$/', $code)) {
    $code = normalize_user_id($code);
} elseif (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $code, $m)) {
    $code = $m[1] . str_pad($m[2], 2, "0", STR_PAD_LEFT);
}

// Fetch WhatsApp settings for side menu
$whatsapp_no_1 = '';
try {
    $rsSettings = $db->query("SELECT whatsapp_no_1 FROM admin_payment_details WHERE id = 1 LIMIT 1");
    if ($rsSettings && $rowSettings = $rsSettings->fetch_assoc()) {
        $whatsapp_no_1 = $rowSettings['whatsapp_no_1'] ?? '';
    }
} catch (Throwable $e) {
}

// Balance Polling
$hasBonusCol = false;
$resB = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
if ($resB && $resB->num_rows > 0) {
    $hasBonusCol = true;
}

$uIdEsc = $db->real_escape_string($code);
$bonusCountsInBalance = $hasBonusCol && function_exists('is_deposit_bonus_enabled') && is_deposit_bonus_enabled($db);
$depositDone = (float) ($db->query("SELECT (IFNULL(SUM(amount),0) + " . ($bonusCountsInBalance ? "IFNULL(SUM(bonus_amount),0)" : "0") . ") FROM deposit WHERE status='Done' AND user_id='$uIdEsc'")->fetch_row()[0] ?? 0);
$withdrawMinus = (float) ($db->query("SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE user_id='$uIdEsc' AND LOWER(status) IN ('pending','approved','done')")->fetch_row()[0] ?? 0);
$walletBalance = max(0.0, $depositDone - $withdrawMinus);
$winBalance = (float) ($db->query("SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id='$uIdEsc'")->fetch_row()[0] ?? 0);
$totalBalance = $walletBalance + $winBalance;

// Fetch Starline Markets
$starlineMarkets = [];
$resM = $db->query("SELECT * FROM markets WHERE market_name LIKE '%STARLINE%' AND is_published = 1 ORDER BY open_time ASC");
while ($row = $resM->fetch_assoc()) {
    $starlineMarkets[] = $row;
}

// Fallback Dummy Data for UI (8AM to 11PM hourly)
if (empty($starlineMarkets)) {
    $times = ["08:00 AM", "09:00 AM", "10:00 AM", "11:00 AM", "12:00 PM", "01:00 PM", "02:00 PM", "03:00 PM", "04:00 PM", "05:00 PM", "06:00 PM", "07:00 PM", "08:00 PM", "09:00 PM", "10:00 PM", "11:00 PM"];
    foreach ($times as $t) {
        $starlineMarkets[] = [
            'id' => 0,
            'market_name' => 'STARLINE ' . $t,
            'open_time' => date('H:i:s', strtotime($t)),
            'close_time' => date('H:i:s', strtotime($t)),
            'status' => 'closed',
            'payout_single_digit' => 100,
            'payout_single_panna' => 1600,
            'payout_double_panna' => 3200,
            'payout_triple_panna' => 9000
        ];
    }
}

$db->close();
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Raj Starline – ANKRAJ 666</title>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <style>
        :root {
            --primary-blue: #3498db;
            --secondary-blue: #2980b9;
            --accent-blue: #00c6ff;
            --bg-gray: #f8f9fa;
            --card-bg: #ffffff;
            --text-main: #333333;
            --text-blue: #2980b9;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            --border-radius: 12px;
            --primary-bg: #f8f9fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background: linear-gradient(180deg, #881313 0%, #b71c1c 15%, #c62828 35%, #d32f2f 65%, #e53935 100%) !important;
            color: #ffffff;
            padding-bottom: 100px;
            overflow-x: hidden;
        }

        /* Header Style */
        .header {
            height: 70px;
            background-color: #8b0000 !important;
            background-image: 
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px),
                repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px) !important;
            display: flex;
            align-items: center;
            padding: 0 20px;
            gap: 15px;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            border-bottom: none !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .back-btn {
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .header span {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Content Container */
        .page-container {
            background: #fff;
            border-radius: 30px 30px 0 0;
            margin-top: 80px;
            min-height: 80vh;
            padding: 20px 0;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.03);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            padding: 0 15px;
            margin-bottom: 20px;
        }

        .btn-history {
            flex: 1;
            background: #fff;
            border: 1.5px solid #eee;
            padding: 12px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            color: #555;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .btn-history i {
            color: var(--primary-blue);
            font-size: 16px;
        }

        /* Rates Pill Style - Grid 2x2 */
        .rates-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 0 15px;
            margin-bottom: 25px;
        }

        .rate-pill {
            background: #fff;
            border: 1.5px solid #eee;
            padding: 12px 5px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 800;
            color: #333;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            white-space: nowrap;
        }

        .rate-pill span {
            color: var(--primary-blue);
        }

        /* Game List */
        .game-list {
            padding: 0 15px;
        }

        .game-card-h {
            background: #fff;
            border: 1px solid #eef2f6;
            border-radius: 18px;
            padding: 15px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s;
        }

        .game-card-h:active {
            transform: scale(0.98);
        }

        .card-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }

        .game-time {
            font-size: 20px;
            font-weight: 800;
            color: #000;
            line-height: 1;
        }

        .game-status {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .status-running {
            color: var(--primary-blue);
        }

        .status-closed {
            color: #e74c3c;
        }

        .card-middle {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .result-pill {
            background: #fff;
            border: 1px solid #eee;
            width: 80px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            color: #333;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .card-right {
            flex: 1;
            display: flex;
            justify-content: flex-end;
        }

        .play-btn-pill {
            background: #576574;
            color: #fff;
            padding: 8px 18px;
            border-radius: 30px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .play-btn-pill.active {
            background: var(--primary-blue);
        }

        .play-btn-pill i {
            font-size: 16px;
        }

        /* SIDE MENU */
        .side-menu-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .6);
            display: none;
            z-index: 4000;
            backdrop-filter: blur(2px);
        }

        .side-menu {
            position: fixed;
            top: 0;
            left: -250px;
            width: min(250px, 75vw);
            height: 100%;
            background: #ffffff;
            padding: 0;
            transition: left 0.3s;
            z-index: 4100;
            box-shadow: 10px 0 30px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        .side-menu.open {
            left: 0;
        }

        .side-menu-header {
            padding: 20px 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-blue);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .profile-text {
            flex: 1;
        }

        .profile-name {
            font-size: 15px;
            font-weight: 700;
            color: #333;
        }

        .profile-id {
            font-size: 11px;
            color: #888;
        }

        .side-close-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: #ccc;
        }

        .side-menu-body {
            padding: 10px;
            flex: 1;
            overflow-y: auto;
        }

        .side-menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            font-weight: 600;
            border-radius: 10px;
            transition: 0.2s;
        }

        .side-menu-item:hover {
            background: #f0f7ff;
            color: var(--primary-blue);
        }

        .side-menu-item i {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f7ff;
            color: var(--primary-blue);
            font-size: 16px;
        }

        .side-menu-footer {
            padding: 15px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }

        .logout-btn {
            flex: 1;
            height: 45px;
            background: #fff5f5;
            color: #e74c3c;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
        }

        .share-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary-blue);
            color: #fff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        /* Bottom Nav */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 70px;
            background: #fff;
            display: flex;
            justify-content: space-around;
            align-items: center;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 2000;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            color: #000;
            flex: 1;
        }

        .nav-item i {
            font-size: 20px;
        }

        .nav-item span {
            font-size: 12px;
            font-weight: 500;
        }

        .nav-home-center {
            width: 60px;
            height: 60px;
            background: var(--primary-blue);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-top: -35px;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
            border: 4px solid #fff;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

    <div class="header">
        <a href="user_dashboard.php" class="back-btn"><i class="fa-solid fa-chevron-left"
                style="font-size:18px;"></i></a>
        <span>Raj Starline</span>
    </div>

    <div class="page-container">

        <div class="action-buttons">
            <a href="user_starline_win_history.php" class="btn-history">
                <i class="fa-solid fa-clock-rotate-left"></i>
                RESULT HISTORY
            </a>
            <a href="user_starline_bid_history.php" class="btn-history">
                <i class="fa-solid fa-list-ul"></i>
                BID HISTORY
            </a>
        </div>

        <div class="rates-wrapper">
            <div class="rate-pill">SINGLE DIGIT <span>10-100</span></div>
            <div class="rate-pill">SINGLE PANNA <span>10-1600</span></div>
            <div class="rate-pill">DOUBLE PANNA <span>10-3200</span></div>
            <div class="rate-pill">TRIPLE PANNA <span>10-9000</span></div>
        </div>

        <div class="game-list">
            <?php
            $curTime = date('H:i:s');
            foreach ($starlineMarkets as $m):
                $closeT = date('H:i:s', strtotime($m['close_time']));
                $isClosed = ($curTime >= $closeT);
                $statusText = $isClosed ? 'CLOSED FOR TODAY' : 'RUNNING NOW';
                $statusClass = $isClosed ? 'status-closed' : 'status-running';
                $btnClass = $isClosed ? '' : 'active';
                
                // Result Logic: Jaisa jackpot.php me kiya hai
                $resVal = $isClosed ? (rand(100, 999) . '-' . rand(0, 9)) : '***-*';
                
                // If actual DB result exists, use it
                if($m['id'] > 0){
                    $mNameEsc = $db->real_escape_string($m['market_name']);
                    $qR = $db->query("SELECT result_num FROM results WHERE market_name='$mNameEsc' AND result_date=CURDATE() LIMIT 1");
                    if($qR && $rR = $qR->fetch_assoc()){
                        $resVal = $rR['result_num'];
                    }
                }
                ?>
                <div class="game-card-h">
                    <div class="card-left">
                        <div class="game-time"><?= date('h:i A', strtotime($m['open_time'])) ?></div>
                        <div class="game-status <?= $statusClass ?>">
                            <?= $statusText ?>
                        </div>
                    </div>

                    <div class="card-middle">
                        <div class="result-pill">
                            <?= $resVal ?>
                        </div>
                    </div>

                    <div class="card-right">
                        <a href="user_starline_game.php?id=<?= $m['id'] ?>" class="play-btn-pill <?= $btnClass ?>">
                            <i class="fa-solid fa-circle-play"></i>
                            PLAY
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- SIDE MENU -->
    <div class="side-menu-overlay" id="sideOverlay"></div>
    <div class="side-menu" id="sideMenu">
        <div class="side-menu-header">
            <div class="profile-avatar"><i class="fas fa-user"></i></div>
            <div class="profile-text">
                <div class="profile-name"><?= htmlspecialchars($userName) ?></div>
                <div class="profile-id"><?= htmlspecialchars($userMobile ?: $code) ?></div>
                <div class="profile-since" style="font-size: 10px; color: #888;">Since
                    <?= htmlspecialchars($userSince ?: 'N/A') ?></div>
            </div>
            <button class="side-close-btn" id="sideCloseBtn"><i class="fas fa-times"></i></button>
        </div>
        <div class="side-menu-body">
            <a href="user_dashboard.php" class="side-menu-item"><i class="fi fi-rr-home"></i>Home</a>
            <a href="price_chart.php" class="side-menu-item"><i class="fi fi-rr-stats"></i>Game Rates</a>
            <a href="deposit.php" class="side-menu-item"><i class="fi fi-rr-receipt"></i>My Wallet</a>
            <a href="user_wining.php" class="side-menu-item"><i class="fi fi-rr-trophy"></i>Winning History</a>
            <a href="user_bid_history.php" class="side-menu-item"><i class="fi fi-rr-list"></i>Bid History</a>
            <a href="user_panel_chart.php" class="side-menu-item"><i class="fi fi-rr-apps"></i>Panel Chart</a>
            <a href="starline.php" class="side-menu-item"><i class="fi fi-rr-play"></i>Raj Starline</a>
            <a href="jackpot.php" class="side-menu-item"><i class="fi fi-rr-star"></i>Raj Jackpot</a>
            <a href="user_profile.php" class="side-menu-item"><i class="fi fi-rr-user"></i>My Profile</a>
            <a href="user_bank.php" class="side-menu-item"><i class="fi fi-rr-bank"></i>Bank Details</a>
            <a href="how_to_play.php" class="side-menu-item"><i class="fi fi-rr-info"></i>How to Play</a>
            <a href="https://wa.me/<?= $whatsapp_no_1 ?>" class="side-menu-item"><i
                    class="fa-brands fa-whatsapp"></i>Customer Support</a>
        </div>
        <div class="side-menu-footer">
            <a href="user_logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i>Logout</a>
            <button class="share-btn" id="shareBtn"><i class="fa-solid fa-share-nodes"></i></button>
        </div>
    </div>

    <!-- Bottom Nav -->
    <nav class="bottom-nav">
        <a href="user_result.php" class="nav-item">
            <i class="fi fi-rr-trophy"></i>
            <span>My Bids</span>
        </a>
        <a href="deposit.php" class="nav-item">
            <i class="fi fi-rr-wallet"></i>
            <span>Funds</span>
        </a>
        <a href="user_dashboard.php" class="nav-item">
            <div class="nav-home-center">
                <i class="fi fi-rr-home"></i>
            </div>
        </a>
        <a href="user_bank.php" class="nav-item">
            <i class="fi fi-rr-bank"></i>
            <span>Bank</span>
        </a>
        <a href="user_profile.php" class="nav-item">
            <i class="fi fi-rr-user"></i>
            <span>Profile</span>
        </a>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sideMenu = document.getElementById('sideMenu');
            const sideOverlay = document.getElementById('sideOverlay');
            const sideClose = document.getElementById('sideCloseBtn');

            function openM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault(); sideMenu.classList.add('open'); sideOverlay.style.display = 'block'; document.body.style.overflow = 'hidden'; }
            function closeM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(true); } if(e && e.preventDefault) e.preventDefault(); sideMenu.classList.remove('open'); sideOverlay.style.display = 'none'; document.body.style.overflow = ''; }

            if (sideOverlay) sideOverlay.addEventListener('click', closeM);
            if (sideClose) sideClose.addEventListener('click', closeM);

            const shareBtn = document.getElementById('shareBtn');
            if (shareBtn) shareBtn.addEventListener('click', () => {
                const url = "https://ankraj666.online/download.php";
                const text = "ANKRAJ 666 - Best Matka App. Download now: " + url;
                if (navigator.share) { navigator.share({ text: text }); }
                else { window.location.href = 'whatsapp://send?text=' + encodeURIComponent(text); }
            });

            // Balance Polling (Standardized)
            async function pollWallet() {
                try {
                    const r = await fetch('deposit.php?ajax=status', { cache: 'no-store' });
                    const d = await r.json();
                    if (d && d.ok && d.balances) {
                        // Polling active
                    }
                } catch (e) { }
                setTimeout(pollWallet, 15000);
            }
            pollWallet();
        });
    </script>
</body>

</html>
