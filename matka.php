<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';
date_default_timezone_set('Asia/Kolkata');

$uid = (int) ($_SESSION['user']['id'] ?? 0);
$totalBalance = 0;

if ($uid > 0) {
    try {
        $db = db();
        $db->query("SET time_zone = '+05:30'");
        
        $st = $db->prepare("SELECT user_id FROM users WHERE id=? LIMIT 1");
        $st->bind_param('i', $uid);
        $st->execute();
        $st->bind_result($LL);
        $st->fetch();
        $st->close();
        
        $LLCode = (string)$LL;
        if ($LLCode !== '') {
            $st = $db->prepare("
                SELECT
                    IFNULL((SELECT SUM(amount) + IFNULL(SUM(bonus_amount),0) FROM deposit WHERE status='Done' AND user_id=?),0)
                - IFNULL((SELECT SUM(amount) FROM withdrawal  WHERE LOWER(status) IN ('pending','approved','done') AND user_id=?),0) AS walletBalance,
                    IFNULL((SELECT SUM(wining_balance) FROM wining WHERE user_id=?),0) AS winBalance
            ");
            $st->bind_param('sss', $LLCode, $LLCode, $LLCode);
            $st->execute();
            $st->bind_result($walletBalance, $winBalance);
            $st->fetch();
            $st->close();
            $totalBalance = $walletBalance + $winBalance;
        }
        $db->close();
    } catch (Throwable $e) {
        $totalBalance = 0;
    }
}
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <title>Matka Pana Categorizer</title>
    <!-- Fonts and Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-light: #f8fafc;
            --surface-light: #ffffff;
            --surface-accent: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: #e2e8f0;
            
            /* Color Categories */
            --color-blue: #3b82f6;
            --color-green: #10b981;
            --color-yellow: #f59e0b;
            --color-red: #ef4444;
            --color-neutral: #94a3b8;
            
            --transition-speed: 0.15s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 30px;
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Header */
        .header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 50px;
            padding: 0 15px;
            background: #ffffff;
            border-bottom: 1px solid var(--border-light);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-right {
            display: flex;
            align-items: center;
        }

        .header-stats {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        @media (max-width: 991px) {
            .header-stats .indicator-label {
                display: none;
            }
            .header-bar .page-title h1 {
                font-size: 13px;
            }
            .header-stats {
                gap: 8px;
            }
        }

        .back-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #ffffff;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 14px;
            border: 1px solid var(--border-light);
            transition: all var(--transition-speed);
        }

        .back-btn:hover {
            background: var(--surface-accent);
            border-color: #cbd5e1;
        }

        .page-title {
            text-align: center;
        }

        .page-title h1 {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: 0.3px;
        }

        .page-title p {
            font-size: 8px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 1px;
        }

        .wallet-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 15px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #d97706;
            font-weight: 700;
            font-size: 12px;
        }

        /* Container */
        .main-container {
            max-width: 1420px;
            margin: 0 auto;
            padding: 10px;
        }





        .stat-indicator {
            display: flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            padding: 3px 6px;
            border-radius: 6px;
            transition: all 0.15s ease;
            user-select: none;
            border: 1px solid transparent;
        }

        .stat-indicator:hover, .stat-indicator.active-filter {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .stat-indicator.active-filter.blue-indicator { border-color: var(--color-blue); box-shadow: 0 0 5px rgba(59,130,246,0.15); }
        .stat-indicator.active-filter.green-indicator { border-color: var(--color-green); box-shadow: 0 0 5px rgba(16,185,129,0.15); }
        .stat-indicator.active-filter.yellow-indicator { border-color: var(--color-yellow); box-shadow: 0 0 5px rgba(245,158,11,0.15); }
        .stat-indicator.active-filter.red-indicator { border-color: var(--color-red); box-shadow: 0 0 5px rgba(239,68,68,0.15); }
        .stat-indicator.active-filter.black-indicator { border-color: var(--color-neutral); }

        .color-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot-blue { background-color: var(--color-blue); }
        .dot-green { background-color: var(--color-green); }
        .dot-yellow { background-color: var(--color-yellow); }
        .dot-red { background-color: var(--color-red); }
        .dot-neutral { background-color: var(--color-neutral); }

        .indicator-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-secondary);
        }

        .indicator-val {
            font-size: 12px;
            font-weight: 800;
            color: var(--text-primary);
        }

        .copy-icon {
            font-size: 10px;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.15s;
            padding: 2px;
            margin-left: 2px;
        }

        .copy-icon:hover {
            color: var(--text-primary);
        }





        /* Layout Grid sections */
        .section-header {
            margin: 10px 0 5px;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .section-header::after {
            content: '';
            flex-grow: 1;
            height: 1px;
            background: var(--border-light);
        }

        /* Grid scroll wrapper for responsive spreadsheet table look */
        .grid-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            background: #ffffff;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(20, 1fr);
            gap: 6px;
            padding: 12px;
            min-width: 960px; /* Ensures boxes retain structure even on mobile scroll */
        }

        /* Header columns row */
        .grid-cols-header {
            display: grid;
            grid-template-columns: repeat(20, 1fr);
            gap: 6px;
            padding: 8px 12px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-light);
            min-width: 960px;
        }

        .grid-col-label {
            font-size: 11px;
            font-weight: 800;
            color: #94a3b8;
            text-align: center;
            user-select: none;
        }

        /* Individual Number Box - light theme reference styling */
        .num-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.1s ease;
            user-select: none;
            position: relative;
        }

        .num-box span {
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
        }

        .num-box:hover {
            border-color: #cbd5e1;
            background-color: #f1f5f9;
        }

        /* Selected active Color States - matching reference image */
        .num-box.state-blue {
            background-color: var(--color-blue);
            border-color: var(--color-blue);
        }
        .num-box.state-blue span { color: #ffffff; }

        .num-box.state-green {
            background-color: var(--color-green);
            border-color: var(--color-green);
        }
        .num-box.state-green span { color: #ffffff; }

        .num-box.state-yellow {
            background-color: var(--color-yellow);
            border-color: var(--color-yellow);
        }
        .num-box.state-yellow span { color: #ffffff; }

        .num-box.state-red {
            background-color: var(--color-red);
            border-color: var(--color-red);
        }
        .num-box.state-red span { color: #ffffff; }

        /* Notification Popup Toast */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #1e293b;
            border-left: 4px solid var(--color-blue);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 8px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 2000;
            font-size: 13px;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast.toast-success {
            border-left-color: var(--color-green);
        }


    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

    <!-- Header bar -->
    <header class="header-bar">
        <div class="header-left">
            <a href="javascript:history.back()" class="back-btn"><i class="fas fa-chevron-left"></i></a>
            <div class="page-title">
                <h1>Matka Pana</h1>
            </div>
        </div>

        <div class="header-stats">
            <div class="stat-indicator blue-indicator" onclick="toggleFilterColor('blue')">
                <span class="color-dot dot-blue"></span>
                <span class="indicator-label">Blue:</span>
                <span class="indicator-val" id="count-blue">0</span>
                <i class="far fa-copy copy-icon" onclick="event.stopPropagation(); copyList('blue')" title="Copy Blue list"></i>
            </div>
            <div class="stat-indicator green-indicator" onclick="toggleFilterColor('green')">
                <span class="color-dot dot-green"></span>
                <span class="indicator-label">Green:</span>
                <span class="indicator-val" id="count-green">0</span>
                <i class="far fa-copy copy-icon" onclick="event.stopPropagation(); copyList('green')" title="Copy Green list"></i>
            </div>
            <div class="stat-indicator yellow-indicator" onclick="toggleFilterColor('yellow')">
                <span class="color-dot dot-yellow"></span>
                <span class="indicator-label">Yellow:</span>
                <span class="indicator-val" id="count-yellow">0</span>
                <i class="far fa-copy copy-icon" onclick="event.stopPropagation(); copyList('yellow')" title="Copy Yellow list"></i>
            </div>
            <div class="stat-indicator red-indicator" onclick="toggleFilterColor('red')">
                <span class="color-dot dot-red"></span>
                <span class="indicator-label">Red:</span>
                <span class="indicator-val" id="count-red">0</span>
                <i class="far fa-copy copy-icon" onclick="event.stopPropagation(); copyList('red')" title="Copy Red list"></i>
            </div>
            <div class="stat-indicator black-indicator" onclick="toggleFilterColor('neutral')">
                <span class="color-dot dot-neutral"></span>
                <span class="indicator-label">Remaining:</span>
                <span class="indicator-val" id="count-neutral">220</span>
                <i class="far fa-copy copy-icon" onclick="event.stopPropagation(); copyList('neutral')" title="Copy Remaining list"></i>
            </div>
        </div>

        <div class="header-right">
            <?php if ($uid > 0): ?>
            <div class="wallet-btn">
                <i class="fas fa-wallet"></i>
                <span>₹<?= number_format($totalBalance, 0) ?></span>
            </div>
            <?php else: ?>
            <a href="user_login.php" class="wallet-btn" style="border-color: var(--border-light); color: var(--text-secondary); text-decoration: none;">
                <i class="fas fa-sign-in-alt"></i>
                <span>Login</span>
            </a>
            <?php endif; ?>
        </div>
    </header>

    <div class="main-container">





        <!-- Single Pana Grid -->
        <div class="grid-section" id="section-single">
            <h3 class="section-header"><i class="fas fa-dice-one"></i> Single Pana (SP)</h3>
            <div class="grid-scroll-wrapper">
                <div class="grid-cols-header">
                    <div class="grid-col-label">01</div>
                    <div class="grid-col-label">02</div>
                    <div class="grid-col-label">03</div>
                    <div class="grid-col-label">04</div>
                    <div class="grid-col-label">05</div>
                    <div class="grid-col-label">06</div>
                    <div class="grid-col-label">07</div>
                    <div class="grid-col-label">08</div>
                    <div class="grid-col-label">09</div>
                    <div class="grid-col-label">10</div>
                    <div class="grid-col-label">11</div>
                    <div class="grid-col-label">12</div>
                    <div class="grid-col-label">13</div>
                    <div class="grid-col-label">14</div>
                    <div class="grid-col-label">15</div>
                    <div class="grid-col-label">16</div>
                    <div class="grid-col-label">17</div>
                    <div class="grid-col-label">18</div>
                    <div class="grid-col-label">19</div>
                    <div class="grid-col-label">20</div>
                </div>
                <div class="grid-container" id="grid-single"></div>
            </div>
        </div>

        <!-- Double Pana Grid -->
        <div class="grid-section" id="section-double">
            <h3 class="section-header"><i class="fas fa-dice-two"></i> Double Pana (DP)</h3>
            <div class="grid-scroll-wrapper">
                <div class="grid-cols-header">
                    <div class="grid-col-label">01</div>
                    <div class="grid-col-label">02</div>
                    <div class="grid-col-label">03</div>
                    <div class="grid-col-label">04</div>
                    <div class="grid-col-label">05</div>
                    <div class="grid-col-label">06</div>
                    <div class="grid-col-label">07</div>
                    <div class="grid-col-label">08</div>
                    <div class="grid-col-label">09</div>
                    <div class="grid-col-label">10</div>
                    <div class="grid-col-label">11</div>
                    <div class="grid-col-label">12</div>
                    <div class="grid-col-label">13</div>
                    <div class="grid-col-label">14</div>
                    <div class="grid-col-label">15</div>
                    <div class="grid-col-label">16</div>
                    <div class="grid-col-label">17</div>
                    <div class="grid-col-label">18</div>
                    <div class="grid-col-label">19</div>
                    <div class="grid-col-label">20</div>
                </div>
                <div class="grid-container" id="grid-double"></div>
            </div>
        </div>

        <!-- Triple Pana Grid -->
        <div class="grid-section" id="section-triple">
            <h3 class="section-header"><i class="fas fa-dice-three"></i> Triple Pana (TP)</h3>
            <div class="grid-scroll-wrapper">
                <div class="grid-cols-header">
                    <div class="grid-col-label">01</div>
                    <div class="grid-col-label">02</div>
                    <div class="grid-col-label">03</div>
                    <div class="grid-col-label">04</div>
                    <div class="grid-col-label">05</div>
                    <div class="grid-col-label">06</div>
                    <div class="grid-col-label">07</div>
                    <div class="grid-col-label">08</div>
                    <div class="grid-col-label">09</div>
                    <div class="grid-col-label">10</div>
                    <div class="grid-col-label">11</div>
                    <div class="grid-col-label">12</div>
                    <div class="grid-col-label">13</div>
                    <div class="grid-col-label">14</div>
                    <div class="grid-col-label">15</div>
                    <div class="grid-col-label">16</div>
                    <div class="grid-col-label">17</div>
                    <div class="grid-col-label">18</div>
                    <div class="grid-col-label">19</div>
                    <div class="grid-col-label">20</div>
                </div>
                <div class="grid-container" id="grid-triple"></div>
            </div>
        </div>


    </div>

    <!-- Notification Toast -->
    <div class="toast" id="toast">
        <i class="fas fa-info-circle"></i>
        <span id="toast-text">Success</span>
    </div>

    <!-- Javascript Logical Handling -->
    <script>
        // Lists extracted directly from application
        var SINGLE_PANAS = [
            '128', '137', '146', '236', '245', '290', '380', '470', '489', '560', 
            '579', '678', '129', '138', '147', '156', '237', '246', '345', '390', 
            '480', '570', '589', '679', '120', '139', '148', '157', '238', '247', 
            '256', '346', '490', '580', '670', '689', '130', '149', '158', '167', 
            '239', '248', '257', '347', '356', '590', '680', '789', '140', '159', 
            '168', '230', '249', '258', '267', '348', '357', '456', '690', '780', 
            '123', '150', '169', '178', '240', '259', '268', '349', '358', '367', 
            '457', '790', '124', '160', '278', '179', '250', '269', '340', '359', 
            '368', '458', '467', '890', '125', '134', '170', '189', '260', '279', 
            '350', '369', '468', '378', '459', '567', '126', '135', '180', '234', 
            '270', '289', '360', '379', '450', '469', '478', '568', '127', '136', 
            '145', '190', '235', '280', '370', '389', '460', '479', '569', '578'
        ];

        var DOUBLE_PANAS = [
            '118', '226', '244', '299', '334', '488', '550', '668', '677', '100', 
            '119', '155', '227', '335', '344', '399', '588', '669', '110', '200', 
            '228', '255', '336', '499', '660', '688', '778', '166', '229', '300', 
            '337', '355', '445', '599', '779', '788', '112', '220', '266', '338', 
            '400', '446', '455', '699', '770', '113', '122', '177', '339', '366', 
            '447', '500', '799', '889', '600', '114', '277', '330', '448', '466', 
            '556', '880', '899', '115', '133', '188', '223', '377', '449', '557', 
            '566', '700', '116', '224', '233', '288', '440', '477', '558', '800', 
            '990', '117', '144', '199', '225', '388', '559', '577', '667', '900'
        ];

        var TRIPLE_PANAS = [
            '000', '111', '222', '333', '444', '555', '666', '777', '888', '999'
        ];

        // Selections stored in localStorage under 'matka_pana_categories'
        // Structure: { "120": "blue", "111": "green" }
        var categories = {};
        
        // Filter variables
        var activeFilterColor = null; // Filter to show ONLY a specific color

        // Color Cycle order
        var colorCycle = ['neutral', 'blue', 'green', 'yellow', 'red'];

        // On document load
        document.addEventListener('DOMContentLoaded', function() {
            loadSelections();
            generateGrids();
            updateStatistics();
        });

        // Load categories from LocalStorage
        function loadSelections() {
            var stored = localStorage.getItem('matka_pana_categories');
            if (stored) {
                try {
                    categories = JSON.parse(stored);
                } catch(e) {
                    categories = {};
                }
            }
        }

        // Save categories to LocalStorage
        function saveSelections() {
            localStorage.setItem('matka_pana_categories', JSON.stringify(categories));
            updateStatistics();
        }

        // Generate grids dynamically
        function generateGrids() {
            var singleGrid = document.getElementById('grid-single');
            var doubleGrid = document.getElementById('grid-double');
            var tripleGrid = document.getElementById('grid-triple');

            // Render SP
            singleGrid.innerHTML = SINGLE_PANAS.map(num => createBoxHtml(num)).join('');
            // Render DP
            doubleGrid.innerHTML = DOUBLE_PANAS.map(num => createBoxHtml(num)).join('');
            // Render TP
            tripleGrid.innerHTML = TRIPLE_PANAS.map(num => createBoxHtml(num)).join('');
        }

        function createBoxHtml(num) {
            var state = categories[num] || 'neutral';
            var stateClass = state !== 'neutral' ? `state-${state}` : '';
            return `<div class="num-box ${stateClass}" id="box-${num}" data-num="${num}" onclick="handleBoxClick('${num}')">
                <span>${num}</span>
            </div>`;
        }

        // Box Click logic
        function handleBoxClick(num) {
            var box = document.getElementById(`box-${num}`);
            if (!box) return;

            var prevColor = categories[num] || 'neutral';

            // Sequential click cycle
            var idx = colorCycle.indexOf(prevColor);
            var nextIdx = (idx + 1) % colorCycle.length;
            var nextColor = colorCycle[nextIdx];

            // Update local object
            if (nextColor === 'neutral') {
                delete categories[num];
            } else {
                categories[num] = nextColor;
            }

            // Update box class styles
            box.className = 'num-box'; // reset
            if (nextColor !== 'neutral') {
                box.classList.add(`state-${nextColor}`);
            }

            saveSelections();
            applyActiveFilters(); // real-time visual filter updates
        }

        // Update counts and lists
        function updateStatistics() {
            var counts = { blue: 0, green: 0, yellow: 0, red: 0, neutral: 0 };
            var lists = { blue: [], green: [], yellow: [], red: [], neutral: [] };

            // Categorize all 220 numbers
            var allNumbers = [...SINGLE_PANAS, ...DOUBLE_PANAS, ...TRIPLE_PANAS];
            
            allNumbers.forEach(function(num) {
                var color = categories[num] || 'neutral';
                counts[color]++;
                lists[color].push(num);
            });

            // Update counter values
            document.getElementById('count-blue').textContent = counts.blue;
            document.getElementById('count-green').textContent = counts.green;
            document.getElementById('count-yellow').textContent = counts.yellow;
            document.getElementById('count-red').textContent = counts.red;
            document.getElementById('count-neutral').textContent = counts.neutral;

            // Sort arrays numerically for display
            var sorter = (a, b) => Number(a) - Number(b);
            lists.blue.sort(sorter);
            lists.green.sort(sorter);
            lists.yellow.sort(sorter);
            lists.red.sort(sorter);
            lists.neutral.sort(sorter);

        }

        // Copy a color category group to clipboard
        function copyList(color) {
            var counts = { blue: 0, green: 0, yellow: 0, red: 0, neutral: 0 };
            var lists = { blue: [], green: [], yellow: [], red: [], neutral: [] };
            
            var allNumbers = [...SINGLE_PANAS, ...DOUBLE_PANAS, ...TRIPLE_PANAS];
            allNumbers.forEach(function(num) {
                var c = categories[num] || 'neutral';
                lists[c].push(num);
            });

            var sorter = (a, b) => Number(a) - Number(b);
            lists[color].sort(sorter);

            if (lists[color].length === 0) {
                showToast(`No numbers found in ${color.toUpperCase()} category`, true);
                return;
            }

            var text = lists[color].join(', ');
            navigator.clipboard.writeText(text).then(function() {
                showToast(`Copied ${lists[color].length} numbers to clipboard!`);
            }).catch(function() {
                showToast('Copy failed, please try again.', true);
            });
        }

        // Toggle Grid Filtering by clicking cards
        function toggleFilterColor(color) {
            var cardMap = {
                'blue': '.blue-indicator',
                'green': '.green-indicator',
                'yellow': '.yellow-indicator',
                'red': '.red-indicator',
                'neutral': '.black-indicator'
            };

            // Remove active classes
            document.querySelectorAll('.stat-indicator').forEach(card => card.classList.remove('active-filter'));

            if (activeFilterColor === color) {
                activeFilterColor = null; // reset filter
                showToast('Color filter cleared');
            } else {
                activeFilterColor = color;
                document.querySelector(cardMap[color]).classList.add('active-filter');
                showToast(`Showing only ${color.toUpperCase()} numbers`);
            }

            applyActiveFilters();
        }





        // Comprehensive visual filter processor
        function applyActiveFilters() {
            var boxes = document.querySelectorAll('.num-box');

            boxes.forEach(function(box) {
                var num = box.getAttribute('data-num');
                var color = categories[num] || 'neutral';
                
                var matchesColorFilter = true;

                // Check Color Card Filter
                if (activeFilterColor !== null) {
                    matchesColorFilter = (color === activeFilterColor);
                }

                // Show/hide box based on evaluation
                if (matchesColorFilter) {
                    box.style.display = 'flex';
                } else {
                    box.style.display = 'none';
                }
            });

            // Adjust section headers visibility if there are no matching items
            ['single', 'double', 'triple'].forEach(function(secType) {
                var section = document.getElementById(`section-${secType}`);
                var visibleBoxes = section.querySelectorAll('.num-box[style="display: flex;"]').length;
                section.style.display = visibleBoxes > 0 ? 'block' : 'none';
            });
        }

        // Show toast notifications
        var toastTimeout;
        function showToast(message, isError = false) {
            var toast = document.getElementById('toast');
            var toastText = document.getElementById('toast-text');
            
            clearTimeout(toastTimeout);
            
            toastText.textContent = message;
            toast.className = 'toast show';
            if (isError) {
                toast.classList.add('toast-error');
            } else {
                toast.classList.add('toast-success');
            }
            
            toastTimeout = setTimeout(function() {
                toast.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>
