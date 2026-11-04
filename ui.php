<?php
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
// ui.php - Premium User Dashboard UI

// Mock data for demonstration if not logged in
$user_mobile = "7021812900"; 
if (isset($_SESSION['user']['mobile_no'])) {
    $user_mobile = $_SESSION['user']['mobile_no'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK CIRCLE | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css">
    <style>
        :root {
            --primary-bg: #0a0118;
            --card-bg: #1c1427;
            --accent-yellow: #ffc107;
            --accent-purple: #8e44ad;
            --accent-green: #27ae60;
            --accent-red: #e74c3c;
            --text-main: #ffffff;
            --text-dim: #b0b0b0;
            --gradient-gold: linear-gradient(90deg, #f39c12, #f1c40f);
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
            --border-radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--primary-bg);
            color: var(--text-main);
            padding-bottom: 80px; /* Space for bottom menu */
            overflow-x: hidden;
        }

        /* Header */
        header {
            background-color: #000000;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between; /* Changed for left/center/right layout */
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.5);
            border-bottom: 1px solid var(--accent-yellow);
        }

        .header-left, .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .header-right {
            justify-content: flex-end;
        }

        .wallet-display {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid var(--accent-yellow);
            padding: 4px 10px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 13px;
            color: var(--accent-yellow);
        }

        .wallet-display i {
            font-size: 14px;
        }

        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 2;
        }

        .logo-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 2px solid var(--accent-yellow);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Welcome Bar */
        .welcome-bar {
            background: var(--gradient-gold);
            margin: 15px;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            color: #000;
            font-weight: 600;
            font-size: 16px;
            box-shadow: var(--shadow);
        }

        /* Sections */
        .section-container {
            padding: 0 15px;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #fff;
            padding-left: 5px;
        }

        /* Live Results Card */
        .live-result-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 15px;
            position: relative;
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--accent-purple);
        }

        .result-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 20px;
            background-color: #000; /* Dark Black Fill */
            border: 2px solid var(--accent-yellow);
            text-shadow: 2px 2px 2px rgba(0,0,0,0.8), 0 0 10px rgba(255,255,255,0.3);
            letter-spacing: 1px;
        }

        .res-faridabad { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 99%, #fecfef 100%); color: #000; }
        .res-gali { background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%); color: #000; }

        .result-info h3 {
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .result-info p {
            font-size: 12px;
            color: var(--text-dim);
            margin-top: 4px;
        }

        .tag-wait {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--accent-red);
            padding: 3px 10px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 600;
        }

        /* Live Games Cards */
        .live-game-card {
            background-color: var(--card-bg);
            border: 2px solid var(--accent-yellow);
            border-radius: var(--border-radius);
            padding: 12px 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .game-icon-circle {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background-color: #000; /* Dark Black Fill */
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 24px;
            box-shadow: inset 0 0 15px rgba(255,193,7,0.4);
            border: 2px solid var(--accent-yellow);
            text-shadow: 2px 2px 3px rgba(0,0,0,0.9), 0 0 12px rgba(255,255,255,0.4);
            letter-spacing: 1.5px;
        }

        .ic-dg { color: #f39c12; }
        .ic-gb { color: #3498db; }
        .ic-pune { color: #9b59b6; }

        .game-details h3 {
            font-size: 16px;
            margin-bottom: 2px;
        }

        .game-details p {
            font-size: 13px;
            color: var(--text-dim);
        }

        .btn-result-out {
            background-color: var(--accent-green);
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 6px;
        }

        /* Upcoming Games Grid */
        .upcoming-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .upcoming-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 15px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: var(--shadow);
        }

        .upcoming-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #000; /* Dark Black Fill */
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 22px;
            margin-bottom: 10px;
            border: 2px solid var(--accent-yellow);
            text-shadow: 2px 2px 2px rgba(0,0,0,0.8), 0 0 10px rgba(255,255,255,0.3);
        }

        .ic-gali { color: #3498db; }
        .ic-ranchi { color: #e91e63; }
        .ic-desawar { color: #8e44ad; }

        .upcoming-card h4 {
            font-size: 12px;
            margin-bottom: 5px;
            color: var(--text-dim);
        }

        .upcoming-card .time {
            font-size: 11px;
            margin-bottom: 10px;
        }

        .btn-upcoming {
            background-color: var(--accent-yellow);
            color: #000;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 4px;
        }

        /* Last Results List */
        .last-results-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .last-result-row {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 12px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .lr-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .lr-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #000; /* Dark Black Fill */
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 18px;
            border: 2px solid var(--accent-yellow);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8), 0 0 6px rgba(255,255,255,0.2);
        }

        .lr-info h3 {
            font-size: 15px;
            margin-bottom: 2px;
        }

        .lr-info p {
            font-size: 11px;
            color: var(--text-dim);
        }

        .lr-value-circle {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--gradient-gold);
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            box-shadow: 0 0 10px rgba(243,156,18,0.5);
        }

        .lr-value-empty {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--gradient-gold);
            opacity: 0.8;
        }

        /* Bottom Menu */
        nav.bottom-menu {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 75px;
            background: #ffffff;
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 2000;
            box-shadow: 0 -5px 25px rgba(0, 0, 0, 0.08);
            border-top: 1px solid rgba(0, 0, 0, 0.04);
        }

        .menu-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #666666;
            text-decoration: none;
            flex: 1;
            height: 100%;
            transition: all 0.3s ease;
        }

        .menu-item i {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #e6e9f0, #eef1f5, #bdc3c7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #000000;
            margin-bottom: 6px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .menu-item span {
            font-size: 11px;
            font-weight: 500;
            color: #000000;
        }

        .menu-item.active span {
            font-weight: 700;
        }

        .menu-item.active i {
            color: #000000;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        /* Whatsapp Floating Button */
        .whatsapp-btn {
            position: fixed;
            bottom: 85px;
            right: 20px;
            width: 60px;
            height: 60px;
            background-color: #25d366;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 32px;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
            z-index: 999;
            text-decoration: none;
            transition: transform 0.3s;
        }

        .whatsapp-btn:active {
            transform: scale(0.9);
        }

        /* Micro-animations */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .result-icon {
            animation: pulse 2s infinite ease-in-out;
        }

        .live-game-card:active, .upcoming-card:active, .last-result-row:active {
            transform: scale(0.98);
        }

        /* Market List Styles */
        .market-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .market-row {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 12px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,0.03);
        }

        .market-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .market-info h3 {
            font-size: 15px;
            margin-bottom: 2px;
        }

        .market-info p {
            font-size: 11px;
            color: var(--text-dim);
        }

        .market-status {
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .status-open { color: var(--accent-green); }
        .status-closed { color: var(--accent-red); }

        .btn-play {
            background: var(--gradient-gold);
            color: #000;
            border: none;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(243, 156, 18, 0.3);
            transition: all 0.3s;
        }

        .btn-play i { font-size: 10px; }

        .btn-play:active { transform: scale(0.9); }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--accent-purple);
            border-radius: 10px;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

    <!-- Header -->
    <header>
        <div class="header-left">
            <i class="fa-solid fa-bars" style="font-size: 22px; color: var(--accent-yellow); margin-right: 15px;"></i>
            <img src="images/shree_ji_matka_logo.png" alt="Logo" style="height: 30px; width: auto; object-fit: contain;">
        </div>

        <div class="header-right">
            <div class="wallet-display">
                <i class="fa-solid fa-wallet"></i>
                <span>₹0.00</span>
            </div>
            <i class="fa-solid fa-bell" style="font-size: 20px; color: var(--accent-yellow);"></i>
        </div>
    </header>

    <!-- Welcome Bar -->
    <div class="welcome-bar">
        Welcome: <?= htmlspecialchars($user_mobile) ?>
    </div>

    <!-- Live Results -->
    <div class="section-container">
        <h2 class="section-title">Live Results</h2>
        <div class="live-result-card">
            <div class="result-icon res-faridabad">FD</div>
            <div class="result-info">
                <h3>FARIDABAD</h3>
                <p>Betting Is closed. Result Will announce Soon</p>
            </div>
            <div class="tag-wait">Wait</div>
        </div>
    </div>

    <!-- Live Games -->
    <div class="section-container">
        <h2 class="section-title">Live Games</h2>
        
        <div class="live-game-card">
            <div class="game-icon-circle ic-dg">DG</div>
            <div class="game-details">
                <h3>DELHI GOLD</h3>
                <p>Close Time: 07:30 pm</p>
                <p>Result Time: 07:45 pm</p>
                <div class="btn-result-out">Result Out</div>
            </div>
        </div>

        <div class="live-game-card">
            <div class="game-icon-circle ic-gb">GB</div>
            <div class="game-details">
                <h3>GHAZIABAD</h3>
                <p>Close Time: 08:15 pm</p>
                <p>Result Time: 09:00 pm</p>
                <div class="btn-result-out">Result Out</div>
            </div>
        </div>

        <div class="live-game-card">
            <div class="game-icon-circle ic-pune">PN</div>
            <div class="game-details">
                <h3>PUNE</h3>
                <p>Close Time: 11:40 pm</p>
                <p>Result Time: 11:45 pm</p>
                <div class="btn-result-out">Result Out</div>
            </div>
        </div>
    </div>

    <!-- Upcoming Games -->
    <div class="section-container">
        <h2 class="section-title">Upcoming Games</h2>
        <div class="upcoming-grid">
            <div class="upcoming-card">
                <div class="upcoming-icon ic-gali">GL</div>
                <h4>GALI</h4>
                <div class="time">7:00 pm - 11:15 pm</div>
                <div class="btn-upcoming">UpComing</div>
            </div>
            <div class="upcoming-card">
                <div class="upcoming-icon ic-ranchi">RC</div>
                <h4>RANCHI</h4>
                <div class="time">10:00 pm - 02:50 am</div>
                <div class="btn-upcoming">UpComing</div>
            </div>
        </div>
        
        <div style="display: flex; justify-content: center; margin-top: 15px;">
            <div class="upcoming-card" style="width: 170px;">
                <div class="upcoming-icon ic-desawar">DS</div>
                <h4>DESAWAR</h4>
                <div class="time">11:00 pm - 02:30 am</div>
                <div class="btn-upcoming">UpComing</div>
            </div>
        </div>
    </div>

    <!-- Available Markets -->
    <div class="section-container">
        <h2 class="section-title">Available Markets</h2>
        <div class="market-list">
            
            <div class="market-row">
                <div class="market-left">
                    <div class="lr-icon" style="color:#f39c12">SL</div>
                    <div class="market-info">
                        <h3>STARLINE MARKET</h3>
                        <p>10:00 AM - 10:00 PM</p>
                        <div class="market-status status-open">
                            <i class="fa-solid fa-circle" style="font-size: 8px;"></i> MARKET OPEN
                        </div>
                    </div>
                </div>
                <a href="user_play.php" class="btn-play">
                    <i class="fa-solid fa-play"></i> PLAY NOW
                </a>
            </div>

            <div class="market-row">
                <div class="market-left">
                    <div class="lr-icon" style="color:#e91e63">KM</div>
                    <div class="market-info">
                        <h3>KALYAN MARKET</h3>
                        <p>04:00 PM - 06:00 PM</p>
                        <div class="market-status status-open">
                            <i class="fa-solid fa-circle" style="font-size: 8px;"></i> MARKET OPEN
                        </div>
                    </div>
                </div>
                <a href="user_play.php" class="btn-play">
                    <i class="fa-solid fa-play"></i> PLAY NOW
                </a>
            </div>

            <div class="market-row">
                <div class="market-left">
                    <div class="lr-icon" style="color:#3498db">SM</div>
                    <div class="market-info">
                        <h3>SRIDEVI MARKET</h3>
                        <p>11:00 AM - 12:00 PM</p>
                        <div class="market-status status-closed">
                            <i class="fa-solid fa-circle" style="font-size: 8px;"></i> MARKET CLOSED
                        </div>
                    </div>
                </div>
                <button class="btn-play" style="opacity: 0.6; filter: grayscale(1); cursor:not-allowed;" disabled>
                    <i class="fa-solid fa-lock"></i> PLAY NOW
                </button>
            </div>

        </div>
    </div>

    <!-- Last Results -->
    <div class="section-container">
        <h2 class="section-title">Last Results</h2>
        <div class="last-results-list">
            
            <div class="last-result-row">
                <div class="lr-left">
                    <div class="lr-icon" style="color:#2ecc71">IB</div>
                    <div class="lr-info">
                        <h3>INDIA BAZAR</h3>
                        <p>Result Time: 04:15 pm</p>
                    </div>
                </div>
                <div class="lr-value-circle">95</div>
            </div>

            <div class="last-result-row">
                <div class="lr-left">
                    <div class="lr-icon" style="color:#e67e22">MC</div>
                    <div class="lr-info">
                        <h3>MUMBAI CITY</h3>
                        <p>Result Time: 11:45 am</p>
                    </div>
                </div>
                <div class="lr-value-circle">59</div>
            </div>

            <div class="last-result-row">
                <div class="lr-left">
                    <div class="lr-icon" style="color:#f1c40f">DG</div>
                    <div class="lr-info">
                        <h3>DELHI GOLD</h3>
                        <p>Result Time: 07:45 pm</p>
                    </div>
                </div>
                <div class="lr-value-circle">86</div>
            </div>

            <div class="last-result-row">
                <div class="lr-left">
                    <div class="lr-icon" style="color:#e74c3c">FD</div>
                    <div class="lr-info">
                        <h3>FARIDABAD</h3>
                        <p>Result Time: 06:10 pm</p>
                    </div>
                </div>
                <div class="lr-value-empty"></div>
            </div>

        </div>
    </div>

    <!-- WhatsApp Floating Button -->
    <a href="https://wa.me/917021812900" class="whatsapp-btn" target="_blank">
        <i class="fa-brands fa-whatsapp"></i>
    </a>

    <!-- Bottom Menu -->
    <nav class="bottom-menu">
        <a href="user_result.php" class="menu-item"><i class="fi fi-sr-trophy"></i><span>My Bids</span></a>
        <a href="deposit.php" class="menu-item"><i class="fi fi-sr-wallet"></i><span>Funds</span></a>
        <a href="user_dashboard.php" class="menu-item active"><i class="fi fi-sr-home"></i><span>HOME</span></a>
        <a href="user_bank.php" class="menu-item"><i class="fi fi-sr-bank"></i><span>Bank</span></a>
        <a href="user_profile.php" class="menu-item"><i class="fi fi-sr-user"></i><span>Profile</span></a>
    </nav>

    <script>
        // Smooth hover/active states for touch devices
        document.querySelectorAll('.menu-item, .whatsapp-btn, .live-game-card').forEach(function(el) {
            el.addEventListener('touchstart', function() {
                this.style.opacity = '0.7';
            }, {passive: true});
            el.addEventListener('touchend', function() {
                this.style.opacity = '1';
            }, {passive: true});
        });

        // Current page highlight logic (already set as active for Home)
    </script>
</body>
</html>

