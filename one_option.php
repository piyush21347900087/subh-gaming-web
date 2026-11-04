<?php
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user']['id'])) {
    header('Location: user_login.php');
    exit;
}
$uid = (int) $_SESSION['user']['id'];

try {
    $db = db();
} catch (Exception $e) {
    die('Database connection failed.');
}

// Fetch user wallet balance
$resUser = $db->query("SELECT wallet_balance, user_id FROM users WHERE id = $uid LIMIT 1");
$userRow = $resUser->fetch_assoc();
$walletBalance = (float) ($userRow['wallet_balance'] ?? 0);
$code = $userRow['user_id'] ?? '';

// Winning balance sum
$winBalance = (float) ($db->query("SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id='$code'")->fetch_row()[0] ?? 0);
$totalBalance = $walletBalance + $winBalance;

// Date Handling
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Last 10 Business Days (Mon-Fri)
$dateFilters = [];
$dIter = new DateTime();
while (count($dateFilters) < 10) {
    if ((int)$dIter->format('N') < 6) {
        $dateFilters[] = $dIter->format('Y-m-d');
    }
    $dIter->modify('-1 day');
}

// Fetch results for selected date
$resArr = [];
$stmt = $db->prepare("SELECT market, result_str, open_pana, close_pana FROM market_result WHERE result_date = ?");
$stmt->bind_param('s', $selectedDate);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $resArr[strtoupper(trim($row['market']))] = [
        'str' => $row['result_str'],
        'open' => $row['open_pana'],
        'close' => $row['close_pana']
    ];
}
$stmt->close();

// Fetch Markets
$markets = [];
$res = $db->query("SELECT market_name, open_time, close_time FROM markets WHERE is_published = 1 ORDER BY sort_order ASC");
while ($row = $res->fetch_assoc()) {
    $markets[] = $row;
}

// Categorize Panas
$panasSP = ['120', '123', '124', '125', '126', '127', '128', '129', '130', '134', '135', '136', '137', '138', '139', '140', '145', '146', '147', '148', '149', '150', '156', '157', '158', '159', '160', '167', '168', '169', '170', '178', '179', '180', '189', '190'];
$panasDP = ['110', '112', '113', '114', '115', '116', '117', '118', '119', '122', '133', '144', '155', '166', '177', '188', '199'];
$panasWithOne = array_merge($panasSP, $panasDP);
sort($panasWithOne);

// Virtual Bidding Stats for SELECTED DATE
$totalInvested = 0;
$totalWon = 0;
$bidSP = 10;
$bidDP = 20;
$winSP = isset($_GET['win_sp']) ? (int)$_GET['win_sp'] : 1500;
$winDP = isset($_GET['win_dp']) ? (int)$_GET['win_dp'] : 6000;

foreach ($markets as $m) {
    $mName = strtoupper(trim($m['market_name']));
    // Invest on both sessions: (SP count * 10 * 2) + (DP count * 20 * 2)
    $investPerMarket = (count($panasSP) * $bidSP * 2) + (count($panasDP) * $bidDP * 2);
    $totalInvested += $investPerMarket;
    
    if (isset($resArr[$mName])) {
        $res = $resArr[$mName];
        $oP = $res['open'];
        $cP = $res['close'];
        foreach ($panasWithOne as $p) {
            if ($p === $oP || $p === $cP) {
                if (in_array($p, $panasSP)) $totalWon += $winSP;
                else $totalWon += $winDP;
            }
        }
    }
}
$netProfit = $totalWon - $totalInvested;
?>

<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>One Option - SUBH GAMING</title>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #f8f9fa;
            --card-bg: #ffffff;
            --accent-main: #000000;
            --accent-silver: linear-gradient(135deg, #e6e9f0, #eef1f5, #bdc3c7);
            --text-main: #333333;
            --text-dim: #666666;
            --win-green: #2ecc71;
            --lose-red: #e74c3c;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            --border-radius: 15px;
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
            padding-bottom: 20px;
        }

        header {
            background-color: #ffffff;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .back-btn {
            background: var(--accent-silver);
            color: #000;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        header h1 {
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .wallet-info {
            font-size: 14px;
            font-weight: 700;
            background: var(--accent-silver);
            padding: 5px 12px;
            border-radius: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .content {
            padding: 15px;
            max-width: 600px;
            margin: 0 auto;
        }

        .market-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 12px;
            margin-bottom: 15px;
            box-shadow: var(--shadow);
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        .market-header {
            flex: 0 0 100px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-top: 5px;
        }

        .market-name {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.2;
        }

        .market-result {
            font-size: 11px;
            font-weight: 700;
            color: var(--accent-main);
            background: #f0f0f0;
            padding: 4px 6px;
            border-radius: 6px;
            text-align: center;
            white-space: nowrap;
        }

        .pana-grid {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(45px, 1fr));
            gap: 6px;
        }

        .pana-box {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 6px 2px;
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .pana-box.win {
            background: var(--win-green);
            color: #fff;
            border-color: var(--win-green);
        }

        .pana-box.lose {
            background: #fdfdfd;
            color: #ccc;
        }

        .status-badge {
            display: block;
            font-size: 8px;
            margin-top: 3px;
            text-transform: uppercase;
        }

        .win-badge {
            color: #fff;
            font-weight: 800;
        }

        .lose-badge {
            color: #ddd;
        }

        .legend {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .dot.win { background: var(--win-green); }
        .dot.lose { background: #ddd; }

        .date-filter-container {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 5px 0 15px;
            margin-bottom: 5px;
            scrollbar-width: none;
        }

        .date-filter-container::-webkit-scrollbar { display: none; }

        .date-pill {
            flex: 0 0 auto;
            background: #fff;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-dim);
            text-decoration: none;
            box-shadow: var(--shadow);
            border: 1px solid transparent;
        }

        .date-pill.active {
            background: var(--accent-silver);
            color: #000;
            border-color: #ddd;
        }

        .settings-form {
            background: #fff;
            padding: 12px;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            box-shadow: var(--shadow);
            display: flex;
            gap: 10px;
            align-items: flex-end;
            justify-content: space-between;
        }

        .settings-form .field {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .settings-form label {
            font-size: 9px;
            font-weight: 700;
            color: var(--text-dim);
            text-transform: uppercase;
        }

        .settings-form input {
            width: 100%;
            padding: 8px;
            border: 1px solid #eee;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            outline: none;
        }

        .settings-form button {
            background: var(--accent-silver);
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }

        .stats-bar {
            display: flex;
            justify-content: space-around;
            background: #fff;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-label {
            font-size: 10px;
            font-weight: 600;
            color: var(--text-dim);
            text-transform: uppercase;
        }

        .stat-value {
            font-size: 15px;
            font-weight: 800;
        }

        .val-invest { color: var(--accent-main); }
        .val-win { color: var(--win-green); }
        .val-net { color: var(--accent-main); }
        .val-profit { color: var(--win-green); }
        .val-loss { color: var(--lose-red); }

        .bid-tag {
            font-size: 8px;
            display: block;
            opacity: 0.6;
            margin-top: 2px;
        }

    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
    <header>
        <a href="user_dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>One Option Analysis</h1>
    </header>

    <div class="content">
        <!-- Date Filters -->
        <div class="date-filter-container">
            <?php foreach ($dateFilters as $df): 
                $isActive = ($df === $selectedDate);
                $label = ($df === date('Y-m-d')) ? 'Today' : date('d M', strtotime($df));
            ?>
                <a href="?date=<?php echo $df; ?>&win_sp=<?php echo $winSP; ?>&win_dp=<?php echo $winDP; ?>" 
                   class="date-pill <?php echo $isActive ? 'active' : ''; ?>">
                    <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Settings Form -->
        <form class="settings-form" method="GET">
            <input type="hidden" name="date" value="<?php echo $selectedDate; ?>">
            <div class="field">
                <label>SP Win (10 का)</label>
                <input type="number" name="win_sp" value="<?php echo $winSP; ?>" placeholder="1500">
            </div>
            <div class="field">
                <label>DP Win (20 का)</label>
                <input type="number" name="win_dp" value="<?php echo $winDP; ?>" placeholder="6000">
            </div>
            <button type="submit">UPDATE</button>
        </form>

        <!-- Virtual Stats -->
        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-label">Invested</span>
                <span class="stat-value val-invest">₹<?php echo number_format($totalInvested); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Total Win</span>
                <span class="stat-value val-win">₹<?php echo number_format($totalWon); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Profit/Loss</span>
                <span class="stat-value val-net <?php echo $netProfit >= 0 ? 'val-profit' : 'val-loss'; ?>">
                    <?php echo ($netProfit >= 0 ? '+' : '') . number_format($netProfit); ?>
                </span>
            </div>
        </div>

        <div class="legend">
            <div class="legend-item"><div class="dot win"></div> WIN</div>
            <div class="legend-item"><div class="dot lose"></div> PENDING/LOSE</div>
        </div>

        <?php if (empty($resArr)): ?>
            <div style="text-align: center; padding: 40px 20px; background: #fff; border-radius: 15px; box-shadow: var(--shadow); margin-top: 20px;">
                <i class="fa-solid fa-database" style="font-size: 40px; color: #ddd; margin-bottom: 15px;"></i>
                <p style="font-weight: 600; color: #666;">Is date ke liye koi results nahi mile.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($markets as $m): 
            $mName = strtoupper(trim($m['market_name']));
            $result = $resArr[$mName] ?? null;
            $resultStr = $result['str'] ?? '***-**-***';
            $openPana = $result['open'] ?? '';
            $closePana = $result['close'] ?? '';
        ?>
            <div class="market-card">
                <div class="market-header">
                    <div class="market-name"><?php echo htmlspecialchars($m['market_name']); ?></div>
                    <div class="market-result"><?php echo $resultStr; ?></div>
                </div>
                <div class="pana-grid">
                    <?php foreach ($panasWithOne as $pana): 
                        $isWin = false;
                        $isSP = in_array($pana, $panasSP);
                        if ($result) {
                            if ($pana === $openPana || $pana === $closePana) {
                                $isWin = true;
                            }
                        }
                    ?>
                        <div class="pana-box <?php echo $isWin ? 'win' : 'lose'; ?>">
                            <div><?php echo $pana; ?></div>
                            <span class="bid-tag">₹<?php echo $isSP ? '10' : '20'; ?></span>
                        </div>
<?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        // Refresh every minute to update results if any
        setTimeout(function() {
            window.location.reload();
        }, 60000);
    </script>
</body>
<?php $db->close(); ?>
</html>
