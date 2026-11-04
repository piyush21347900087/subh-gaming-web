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

// User Info
$stmt = $db->prepare("SELECT user_id, name, mobile_no FROM users WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Date Filter Logic
$filterDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Pagination
$limit = 50; // Increased limit for history
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Total count with filter
$countStmt = $db->prepare("SELECT COUNT(*) FROM market_result3 WHERE result_date = ?");
$countStmt->bind_param('s', $filterDate);
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_row()[0];
$countStmt->close();
$totalPages = ceil($totalRecords / $limit);

// Fetch Historical Results with Filter
$results = [];
$sql = "SELECT market, result_date, result_str, declared_at 
        FROM market_result3 
        WHERE result_date = ?
        ORDER BY declared_at ASC"; // ASC as per image usually shows early to late
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Jackpot History – ANKRAJ 666</title>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root { 
            --primary-blue: #3498db; 
            --secondary-blue: #2980b9;
            --bg-gray: #f8f9fa; 
            --text-dark: #2c3e50;
            --accent-orange: #f39c12;
            --royal-gradient: linear-gradient(135deg, #3498db 0%, #2193b0 100%);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: var(--bg-gray); padding-bottom: 30px; }
        
        /* Header */
        .header { height: 70px; background: #3498db; display: flex; align-items: center; padding: 0 16px; gap: 12px; position: fixed; top: 0; width: 100%; z-index: 1000; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; color: #fff; }
        .back-btn { width: 38px; height: 38px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; flex-shrink: 0; }
        .header-title { flex: 1; font-weight: 700; text-transform: uppercase; font-size: 16px; text-align: center; }
        .spacer { width: 38px; }

        .page-container { margin-top: 85px; padding: 0 15px; }

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
            color: #444;
            text-transform: uppercase;
        }
        .date-picker-pill {
            position: relative;
            background: var(--royal-gradient);
            padding: 8px 20px;
            border-radius: 30px;
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(33, 147, 176, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 140px;
            justify-content: center;
        }
        .date-picker-pill input {
            position: absolute;
            inset: 0;
            opacity: 0;
            width: 100%;
            cursor: pointer;
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
            color: var(--primary-blue);
        }

        .no-data { text-align: center; padding: 80px 20px; color: #999; }
        .no-data i { font-size: 50px; opacity: 0.1; margin-bottom: 15px; }
        
        /* Formatted date for pill display */
        <?php 
            $displayDate = date('d-m-Y', strtotime($filterDate));
        ?>
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

    <header class="header">
        <a href="jackpot3.php" class="back-btn"><i class="fa-solid fa-chevron-left"></i></a>
        <span class="header-title">RESULT HISTORY</span>
        <div class="spacer"></div>
    </header>

    <div class="page-container">
        <!-- Modern Date Filter Pill -->
        <div class="filter-wrapper">
            <span class="filter-label">SELECT DATE :</span>
            <div class="date-picker-pill">
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
