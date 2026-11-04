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

/* Fetch Main Game Markets */
$mainMarkets = [];
try {
    $q = $db->query("SELECT market_name FROM markets ORDER BY sort_order ASC, id ASC");
    if ($q && $q->num_rows > 0) {
        while ($r = $q->fetch_assoc()) {
            $mainMarkets[] = $r['market_name'];
        }
    }
} catch (Throwable $e) {}

if (empty($mainMarkets)) {
    $mainMarkets = [
        "KARNATAKA DAY", "SRIDEVI", "TIME BAZAR", "MADHUR DAY", "MILAN DAY", 
        "RAJDHANI DAY", "SUPREME DAY", "KALYAN", "KARNATAKA NIGHT", "SRIDEVI NIGHT", 
        "MADHUR NIGHT", "MILAN NIGHT", "RAJDHANI NIGHT", "SUPREME NIGHT", "KALYAN NIGHT", "MAIN BAZAR"
    ];
}

/* Starline Times */
$starlineTimes = [
    "09:00 AM", "10:00 AM", "11:00 AM", "12:00 PM", "01:00 PM",
    "02:00 PM", "03:00 PM", "04:00 PM", "05:00 PM", "06:00 PM",
    "07:00 PM", "08:00 PM", "09:00 PM", "10:00 PM", "11:00 PM"
];

/* Jackpot Times */
$jackpotTimes = [
    "09:30 AM", "10:30 AM", "11:30 AM", "12:30 PM", "01:30 PM",
    "02:30 PM", "03:30 PM", "04:30 PM", "05:30 PM", "06:30 PM",
    "07:30 PM", "08:30 PM", "09:30 PM", "10:30 PM", "11:30 PM"
];

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <meta name="theme-color" content="#000000">
    <title><?= __("Notification Settings") ?> | SUBH GAMING</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --header-bg: #000000;
            --btn-red: #e53935;
            --accent-red: #e53935;
            --text-dark: #ffffff;
            --text-gray: #94a3b8;
            --card-bg: #ffffff;
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
            justify-content: flex-start;
            gap: 14px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
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

        /* ── Tabs Bar ── */
        .tabs-bar {
            display: flex;
            background: #121212;
            border-bottom: 1px solid #1a1a1a;
            position: sticky;
            top: 62px;
            z-index: 900;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
            max-width: 550px;
            margin: 0 auto;
        }

        .tab-btn {
            flex: 1;
            text-align: center;
            padding: 14px 6px;
            font-size: 14px;
            font-weight: 700;
            color: #94a3b8;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
            transition: color 0.2s ease;
        }

        .tab-btn.active {
            color: #e53935;
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background-color: #e53935;
            border-radius: 3px 3px 0 0;
        }

        /* ── Content Area ── */
        .content {
            max-width: 550px;
            margin: 0 auto;
            padding: 16px 14px 40px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* ── Cards Design (White Boxes as requested) ── */
        .card-item {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid #edf2f7;
        }

        .master-card {
            border: 1px solid #ffcdd2;
            box-shadow: 0 4px 15px rgba(229, 57, 53, 0.1);
            margin-bottom: 16px;
        }

        .card-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding-right: 12px;
        }

        .card-title {
            font-size: 15px;
            font-weight: 800;
            color: #e53935;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .master-card .card-title {
            font-size: 15.5px;
            color: #111111;
            text-transform: none;
        }

        .card-subtext {
            font-size: 12px;
            color: #666666;
            font-weight: 500;
        }

        /* ── iOS / Material Toggle Switch ── */
        .switch {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 26px;
            flex-shrink: 0;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e0;
            transition: .3s ease;
            border-radius: 26px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: #ffffff;
            transition: .3s ease;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.25);
        }

        input:checked + .slider {
            background-color: #ffcdd2;
        }

        input:checked + .slider:before {
            transform: translateX(22px);
            background-color: #e53935;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

    <!-- Header -->
    <header>
        <a class="back-btn" href="javascript:history.back()" title="Back">
            <i class="fa-solid fa-chevron-left"></i>
        </a>
        <div class="header-title-flash">
            <img src="icons/gold_coin.svg" alt="coin">
            <?= __("Notification Settings") ?>
            <img src="icons/gold_coin.svg" alt="coin">
        </div>
    </header>

    <!-- Top Tabs Bar -->
    <div class="tabs-bar">
        <button type="button" class="tab-btn active" id="tab-btn-main" onclick="switchTab('main')"><?= __("Main Games") ?></button>
        <button type="button" class="tab-btn" id="tab-btn-starline" onclick="switchTab('starline')"><?= __("Starline") ?></button>
        <button type="button" class="tab-btn" id="tab-btn-jackpot" onclick="switchTab('jackpot')"><?= __("Jackpot") ?></button>
    </div>

    <!-- Main Content -->
    <div class="content">

        <!-- TAB 1: Main Games -->
        <div class="tab-content active" id="tab-main">
            <!-- Master Toggle -->
            <div class="card-item master-card">
                <div class="card-info">
                    <div class="card-title"><?= __("Main Game Notification") ?></div>
                    <div class="card-subtext"><?= __("Enable/Disable all main game notifications") ?></div>
                </div>
                <label class="switch">
                    <input type="checkbox" id="master-main" checked onchange="toggleCategory('main', this.checked)">
                    <span class="slider"></span>
                </label>
            </div>

            <!-- Items -->
            <div id="list-main">
                <?php foreach ($mainMarkets as $idx => $m): ?>
                    <div class="card-item">
                        <div class="card-info">
                            <div class="card-title"><?= htmlspecialchars($m) ?></div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" class="item-main" data-id="<?= htmlspecialchars($m) ?>" checked onchange="updateItemState('main')">
                            <span class="slider"></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAB 2: Starline -->
        <div class="tab-content" id="tab-starline">
            <!-- Master Toggle -->
            <div class="card-item master-card">
                <div class="card-info">
                    <div class="card-title"><?= __("Starline Notification") ?></div>
                    <div class="card-subtext"><?= __("Enable/Disable all starline notifications") ?></div>
                </div>
                <label class="switch">
                    <input type="checkbox" id="master-starline" checked onchange="toggleCategory('starline', this.checked)">
                    <span class="slider"></span>
                </label>
            </div>

            <!-- Items -->
            <div id="list-starline">
                <?php foreach ($starlineTimes as $idx => $t): ?>
                    <div class="card-item">
                        <div class="card-info">
                            <div class="card-title"><?= htmlspecialchars($t) ?></div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" class="item-starline" data-id="<?= htmlspecialchars($t) ?>" checked onchange="updateItemState('starline')">
                            <span class="slider"></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAB 3: Jackpot -->
        <div class="tab-content" id="tab-jackpot">
            <!-- Master Toggle -->
            <div class="card-item master-card">
                <div class="card-info">
                    <div class="card-title"><?= __("Jackpot Notification") ?></div>
                    <div class="card-subtext"><?= __("Enable/Disable all jackpot notifications") ?></div>
                </div>
                <label class="switch">
                    <input type="checkbox" id="master-jackpot" checked onchange="toggleCategory('jackpot', this.checked)">
                    <span class="slider"></span>
                </label>
            </div>

            <!-- Items -->
            <div id="list-jackpot">
                <?php foreach ($jackpotTimes as $idx => $t): ?>
                    <div class="card-item">
                        <div class="card-info">
                            <div class="card-title"><?= htmlspecialchars($t) ?></div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" class="item-jackpot" data-id="<?= htmlspecialchars($t) ?>" checked onchange="updateItemState('jackpot')">
                            <span class="slider"></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <script>
        const STORAGE_KEY = 'mahadevking_notification_settings';

        function loadSettings() {
            let saved = {};
            try {
                saved = JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
            } catch (e) {}

            ['main', 'starline', 'jackpot'].forEach(cat => {
                const items = document.querySelectorAll('.item-' + cat);
                let allChecked = true;
                let anyChecked = false;

                items.forEach(inp => {
                    const id = inp.dataset.id;
                    if (saved[cat] && typeof saved[cat][id] !== 'undefined') {
                        inp.checked = !!saved[cat][id];
                    }
                    if (inp.checked) anyChecked = true;
                    else allChecked = false;
                });

                const master = document.getElementById('master-' + cat);
                if (master) {
                    master.checked = items.length > 0 ? allChecked : true;
                }
            });
        }

        function saveSettings() {
            const settings = {};
            ['main', 'starline', 'jackpot'].forEach(cat => {
                settings[cat] = {};
                document.querySelectorAll('.item-' + cat).forEach(inp => {
                    settings[cat][inp.dataset.id] = inp.checked;
                });
            });
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
            } catch (e) {}
        }

        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            const btn = document.getElementById('tab-btn-' + tabId);
            const content = document.getElementById('tab-' + tabId);
            if (btn) btn.classList.add('active');
            if (content) content.classList.add('active');
        }

        function toggleCategory(cat, isChecked) {
            document.querySelectorAll('.item-' + cat).forEach(inp => {
                inp.checked = isChecked;
            });
            saveSettings();
        }

        function updateItemState(cat) {
            const items = document.querySelectorAll('.item-' + cat);
            let allChecked = true;
            items.forEach(inp => {
                if (!inp.checked) allChecked = false;
            });
            const master = document.getElementById('master-' + cat);
            if (master) {
                master.checked = allChecked;
            }
            saveSettings();
        }

        // Initialize state on load
        loadSettings();
    </script>
    <script src="assets/js/user_notice_tracker.js?v=4"></script>
</body>
</html>
