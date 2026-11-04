<?php
session_start();
require_once 'includes/db.php';
require_once 'process_starline4_wins.php';
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
starline4_ensure_schema($db);
starline4_seed_default_markets($db);
starline4_auto_generate_results($db);

// User Info
$stmt = $db->prepare("SELECT user_id, name, mobile_no FROM users WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Date Filter Logic
$filterDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Fetch Historical Results with Filter
$results = [];
$sql = "SELECT market, result_date, result_str, declared_at 
        FROM market_result4 
        WHERE result_date = ?
        ORDER BY declared_at ASC"; 
$stmtR = $db->prepare($sql);
$stmtR->bind_param('s', $filterDate);
$stmtR->execute();
$resR = $stmtR->get_result();
while ($row = $resR->fetch_assoc()) {
    $results[] = $row;
}
$stmtR->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <title>Starline 4 History – ANKRAJ 666</title>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root { 
            --primary: #000000; 
            --accent: #e53935;
            --bg-gray: #000000; 
            --text-dark: #ffffff;
            --red-gradient: linear-gradient(135deg, #e53935 0%, #c0392b 100%);
            accent-color: #e53935;
            color-scheme: dark;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; -webkit-tap-highlight-color: transparent; }
        html, body { 
            background: #000000 !important; 
            padding-bottom: 30px;
            accent-color: #e53935;
            color: #ffffff;
            color-scheme: dark;
        }
        
        /* Header */
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
        .back-btn { width: 38px; height: 38px; background: #e53935; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; border: none; flex-shrink: 0; box-shadow: 0 2px 6px rgba(0, 0, 0, .2); font-size: 15px; transition: opacity 0.15s ease; }
        .back-btn:active { opacity: 0.85; }
        .header-title { flex: 1; font-weight: 800; text-transform: uppercase; font-size: 16px; text-align: center; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .header-title img { width: 18px; height: 18px; vertical-align: middle; margin-top: -2px; }
        .spacer { width: 38px; }

        .page-container { margin-top: calc(env(safe-area-inset-top, 0px) + 85px); padding: 0 15px; }

        /* Filter Section Modern UI */
        .filter-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 5px 0;
        }
        .filter-label {
            font-size: 14px;
            font-weight: 800;
            color: #ffffff;
            text-transform: uppercase;
        }
        .date-picker-pill {
            position: relative;
            background: var(--red-gradient);
            padding: 8px 18px;
            border-radius: 30px;
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(139, 0, 0, 0.35);
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 140px;
            justify-content: center;
            cursor: pointer;
        }
        .date-picker-pill i {
            font-size: 14px;
            color: #fff;
        }
        .date-picker-pill input[type="date"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            accent-color: #8b0000 !important;
            color-scheme: light !important;
        }

        /* Result List UI */
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .history-item {
            background: #fff;
            border-radius: 12px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid #edf2f7;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }
        .time-label {
            font-size: 16px;
            font-weight: 700;
            color: #333;
        }
        .result-digit {
            font-size: 18px;
            font-weight: 800;
            color: #8b0000;
        }

        .no-data { text-align: center; padding: 80px 20px; color: #999; }
        .no-data i { font-size: 50px; opacity: 0.1; margin-bottom: 15px; }
        
        <?php $displayDate = date('d-m-Y', strtotime($filterDate)); ?>
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

    <header class="header">
        <a href="starline4.php" class="back-btn"><i class="fa-solid fa-chevron-left"></i></a>
        <span class="header-title">
            <img src="icons/gold_coin.svg" alt="coin">
            STARLINE 4 RESULT
            <img src="icons/gold_coin.svg" alt="coin">
        </span>
        <div class="spacer"></div>
    </header>

    <div class="page-container">
        <div class="filter-wrapper">
            <span class="filter-label">SELECT DATE :</span>
            <div class="date-picker-pill">
                <i class="fa-regular fa-calendar-days"></i>
                <span><?= $displayDate ?></span>
                <input type="date" id="dateInput" value="<?= $filterDate ?>" max="<?= date('Y-m-d') ?>" onchange="updateHistoryDate()">
            </div>
        </div>

        <div class="history-list">
            <?php if (!empty($results)): ?>
                <?php foreach ($results as $r): ?>
                    <div class="history-item">
                        <div class="time-label">
                            <?= date('h:i A', strtotime($r['declared_at'])) ?>
                        </div>
                        <div class="result-digit">
                            <?= htmlspecialchars($r['result_str']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-calendar-times"></i>
                    <p>No records found for this date.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateHistoryDate() {
            const val = document.getElementById('dateInput').value;
            if(val) {
                window.location.href = `?date=${val}`;
            }
        }
    </script>

</body>
</html>
