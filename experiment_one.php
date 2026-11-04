<?php
/**
 * experiment_one.php - Premium Market Selection Experiment Grid with SP/DP
 * Created for: SUBH GAMING
 */

require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
// Authentication Check
if (!isset($_SESSION['user']['id'])) {
    header('Location: user_login.php');
    exit;
}

date_default_timezone_set('Asia/Kolkata');

$db = db();
$currentMonth = date('F'); 
$currentMonthNum = date('n');
$currentYear = date('Y');
$daysInMonth = date('t'); 

// Fetch Markets
$markets = [];
try {
    $res = $db->query("SELECT market_name FROM markets WHERE is_published = 1 ORDER BY sort_order ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $markets[] = $row['market_name'];
        }
    }
} catch (Throwable $e) {
    $markets = ['KALYAN', 'MAIN BAZAR', 'MILAN DAY', 'MILAN NIGHT'];
}

// Handle form submission
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['m_data'])) {
    $mData = $_POST['m_data']; 
    try {
        // Clear previous data for this month/year to avoid duplicates
        $delStmt = $db->prepare("DELETE FROM experiment WHERE month_name = ? AND year_val = ?");
        $delStmt->bind_param('si', $currentMonth, $currentYear);
        $delStmt->execute();
        $delStmt->close();

        $stmt = $db->prepare("INSERT INTO experiment (market_name, day_val, month_name, year_val, is_checked, bet_type) VALUES (?, ?, ?, ?, 1, ?)");
        foreach ($mData as $mName => $days) {
            foreach ($days as $day => $type) {
                if ($type !== '') {
                    $stmt->bind_param('sisis', $mName, $day, $currentMonth, $currentYear, $type);
                    $stmt->execute();
                }
            }
        }
        $stmt->close();
        $message = "Grid selections (SP/DP) saved successfully!";
    } catch (Throwable $e) {
        $message = "Error saving: " . $e->getMessage();
    }
}

// Fetch Existing Selections for Pre-population
$savedData = [];
$resSaved = $db->query("SELECT market_name, day_val, bet_type FROM experiment WHERE month_name = '$currentMonth' AND year_val = '$currentYear'");
if ($resSaved) {
    while ($row = $resSaved->fetch_assoc()) {
        $savedData[$row['market_name']][$row['day_val']] = $row['bet_type'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Experiment Grid | SUBH GAMING</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-bg: #f8f9fa;
            --card-bg: #ffffff;
            --accent-main: #000000;
            --accent-gold: #c9a227;
            --sp-green: #2ecc71;
            --dp-blue: #3498db;
            --cl-red: #e74c3c;
            --text-main: #1a1a1a;
            --text-dim: #7f8a9b;
            --shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
            --border-radius: 20px;
        }

        * {
            margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--primary-bg);
            color: var(--text-main);
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .grid-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid #eee;
            overflow-x: auto;
            position: relative;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 1200px;
        }

        th, td {
            padding: 12px 6px;
            text-align: center;
            border-bottom: 1px solid #f1f1f1;
            background-clip: padding-box;
        }

        th:last-child, td:last-child {
            padding-right: 25px;
        }

        th {
            background: #fafafa;
            font-size: 11px;
            font-weight: 800;
            color: var(--text-dim);
            text-transform: uppercase;
            position: sticky;
            top: 0;
            z-index: 20;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-bottom: 2px solid #eee;
        }

        .market-col {
            text-align: left;
            padding-left: 20px;
            font-weight: 700;
            font-size: 11px;
            background: #fff;
            position: sticky;
            left: 0;
            z-index: 5;
            border-right: 2px solid #f1f1f1;
            min-width: 130px;
        }

        th.market-col {
            z-index: 25;
            background: #fafafa;
            top: 0;
            border-right: 2px solid #eee;
        }

        /* 3-State Toggle Box */
        .grid-cell {
            width: 22px;
            height: 22px;
            margin: 0 auto;
            border-radius: 4px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            font-weight: 800;
            color: #fff;
            transition: 0.2s;
            user-select: none;
        }

        .grid-cell.sp {
            background-color: var(--sp-green);
            border-color: var(--sp-green);
            box-shadow: 0 2px 8px rgba(46, 204, 113, 0.3);
        }

        .grid-cell.dp {
            background-color: var(--dp-blue);
            border-color: var(--dp-blue);
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }

        .grid-cell.cl {
            background-color: var(--cl-red);
            border-color: var(--cl-red);
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }

        .grid-cell:hover {
            filter: brightness(0.95);
        }

        .save-bar {
            padding: 20px;
            text-align: center;
            background: #fff;
            border-top: 1px solid #f1f1f1;
        }

        .save-btn {
            background: var(--accent-main);
            color: #fff;
            padding: 12px 50px;
            border-radius: 12px;
            border: none;
            font-weight: 800;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .msg {
            text-align: center; padding: 12px; margin-bottom: 15px;
            border-radius: 10px; font-weight: 600; font-size: 13px;
        }
        .msg.success { background: #e8f5e9; color: #2e7d32; }

        .is-today {
            background-color: #fdfaf0 !important;
            color: var(--accent-gold) !important;
        }

        .is-weekend {
            color: #e74c3c !important;
        }

        .legend {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 12px;
            font-weight: 700;
            flex-wrap: wrap;
        }
        .legend-item { display: flex; align-items: center; gap: 8px; }
        .box { width: 15px; height: 15px; border-radius: 3px; }
        .box.sp { background: var(--sp-green); }
        .box.dp { background: var(--dp-blue); }
        .box.cl { background: var(--cl-red); }

    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

<div class="container">
    <?php if ($message): ?>
        <div class="msg success"><?php echo $message; ?></div>
        <script>
            setTimeout(function() {
                window.location.href = 'experiment_one.php';
            }, 3000);
        </script>
    <?php endif; ?>

    <form class="grid-card" method="POST" id="gridForm">
        <table>
            <thead>
                    <tr>
                        <th class="market-col">Market List</th>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $isToday = ($d == (int)date('d'));
                            $ts = strtotime("$currentYear-$currentMonth-$d");
                            $dayOfWeek = date('N', $ts);
                            $isWeekend = ($dayOfWeek == 6 || $dayOfWeek == 7); // 6=Sat, 7=Sun
                        ?>
                            <th class="<?php echo $isToday ? 'is-today' : ''; ?> <?php echo $isWeekend ? 'is-weekend' : ''; ?>">
                                <?php echo str_pad($d, 2, '0', STR_PAD_LEFT); ?>
                            </th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($markets as $m): ?>
                        <tr>
                            <td class="market-col"><?php echo htmlspecialchars($m); ?></td>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                                $isToday = ($d == (int)date('d'));
                                $ts = strtotime("$currentYear-$currentMonth-$d");
                                $dayOfWeek = date('N', $ts);
                                $isWeekend = ($dayOfWeek == 6 || $dayOfWeek == 7);
                                $savedType = $savedData[$m][$d] ?? '';
                                $cellClass = $savedType === 'SP' ? 'sp' : ($savedType === 'DP' ? 'dp' : ($savedType === 'CL' ? 'cl' : ''));
                            ?>
                                <td class="<?php echo $isToday ? 'is-today' : ''; ?> <?php echo $isWeekend ? 'is-weekend' : ''; ?>">
                                    <div class="grid-cell <?php echo $cellClass; ?>" 
                                         onclick="cycleType(this, '<?php echo htmlspecialchars($m); ?>', <?php echo $d; ?>)">
                                         <?php echo $savedType; ?>
                                    </div>
                                    <input type="hidden" name="m_data[<?php echo htmlspecialchars($m); ?>][<?php echo $d; ?>]" value="<?php echo $savedType; ?>">
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <div class="save-bar">
            <button type="submit" class="save-btn">SAVE GRID DATA</button>
        </div>
    </form>
</div>

<script>
    function cycleType(el, market, day) {
        var input = el.nextElementSibling;
        var currentVal = input.value;
        
        if (currentVal === '') {
            input.value = 'SP';
            el.classList.add('sp');
            el.classList.remove('dp', 'cl');
            el.textContent = 'SP';
        } else if (currentVal === 'SP') {
            input.value = 'DP';
            el.classList.add('dp');
            el.classList.remove('sp', 'cl');
            el.textContent = 'DP';
        } else if (currentVal === 'DP') {
            input.value = 'CL';
            el.classList.add('cl');
            el.classList.remove('sp', 'dp');
            el.textContent = 'CL';
        } else {
            input.value = '';
            el.classList.remove('sp', 'dp', 'cl');
            el.textContent = '';
        }
    }
</script>

</body>
</html>
