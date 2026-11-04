<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/lang.php';
require_once 'process_starline4_wins.php';
date_default_timezone_set('Asia/Kolkata');

if (empty($_SESSION['user']['id'])) {
    header('Location: user_login.php');
    exit;
}

$marketName = $_GET['market'] ?? 'Market';
$marketId = (int)($_GET['id'] ?? 0);
$uid = (int)$_SESSION['user']['id'];
$db = db();
starline4_ensure_schema($db);
starline4_seed_default_markets($db);

$stmt = $db->prepare("SELECT user_id, name FROM users WHERE id=? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$code = normalize_user_id($user['user_id'] ?? '');
$totalBalance = get_total_balance($db, $code);

// Remove "STARLINE" prefix for clean display
$displayTitle = str_replace('STARLINE ', '', $marketName);

/* label , icon , accent-class , target file */
$tiles = [
    ['Single Digit',      'single-digit.svg', 'sd-red',  'single_digit4.php'],
    ['Single Digit Bulk', 'single-digit.svg', 'orange',  'single_digit_bulk4.php'],
    ['Single Pana',       'single-pana.svg',  'purple',  'single_pana4.php'],
    ['Single Pana Bulk',  'single-pana.svg',  'teal',    'single_pana_bulk4.php'],
    ['Double Pana',       'double-pana.svg',  'sd-red',  'double_pana4.php'],
    ['Double Pana Bulk',  'double-pana.svg',  'orange',  'double_pana_bulk4.php'],
    ['Triple Pana',       'triple-pana.svg',  'purple',  'triple_pana4.php'],
    ['Triple Pana Bulk',  'triple-pana.svg',  'teal',    'triple_pana_bulk4.php'],
];
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins', sans-serif; -webkit-tap-highlight-color: transparent; }
        body {
            background: #000000 !important;
            color: #ffffff;
            min-height: 100vh;
            padding-bottom: 90px;
        }

        .header-bar {
            min-height: 65px;
            height: auto;
            background-color: #000000 !important;
            background-image: none !important;
            padding-top: calc(env(safe-area-inset-top, 0px) + 12px);
            padding-bottom: 12px;
            padding-left: 14px;
            padding-right: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid #1a1a1a;
        }

        .back-btn {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #e53935;
            color: #ffffff !important;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 16px;
            border: none;
            cursor: pointer;
            flex-shrink: 0;
            transition: transform 0.15s ease;
        }

        .back-btn:active {
            transform: scale(0.92);
        }

        .wallet-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #e53935;
            border-radius: 8px;
            padding: 6px 12px;
            font-weight: 800;
            font-size: 13px;
            color: #ffffff;
            text-decoration: none;
            flex-shrink: 0;
            border: none;
        }

        .container {
            max-width: 480px;
            margin: 0 auto;
            padding: 24px 16px 80px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 120px);
            gap: 26px 36px;
            justify-content: center;
        }

        .market-card {
            width: 120px;
            height: 140px;
            min-height: 140px;
            padding: 16px 6px 14px;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            border: none;
        }
        .market-card:active { transform: scale(0.95); }

        .market-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 48px;
            height: 4.5px;
            border-radius: 4px 4px 0 0;
            background: var(--accent-bar, #e53935);
        }

        /* Alternating Card Themes matching markets page */
        .market-card.theme-pink, .market-card.sd-red, .market-card.red { background: #fdeeed; --accent-bar: #e53935; }
        .market-card.theme-yellow, .market-card.orange, .market-card.yellow { background: #fdf1b8; --accent-bar: #fbc02d; }
        .market-card.theme-purple, .market-card.purple, .market-card.indigo { background: #d8d7f8; --accent-bar: #7c77eb; }
        .market-card.theme-peach, .market-card.teal, .market-card.green, .market-card.blue { background: #fddbb4; --accent-bar: #f57c00; }

        .icon-box {
            width: 50px;
            height: 50px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .icon-box img, .icon-box svg {
            width: 44px;
            height: 44px;
            object-fit: contain;
            filter: drop-shadow(0 3px 5px rgba(0,0,0,0.12));
        }
        
        .card-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .card-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e1e1e;
            line-height: 1.2;
            font-family: 'Poppins', sans-serif;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <header class="header-bar">
        <a href="starline4.php" class="back-btn" title="Back">
            <i class="fa-solid fa-chevron-left"></i>
        </a>
        <div style="text-align: center; flex: 1; margin: 0 8px; overflow: hidden;">
            <div style="font-weight: 900; font-size: 14px; text-transform: uppercase; color: #ffffff; letter-spacing: 0.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= htmlspecialchars($displayTitle) ?>
            </div>
            <div style="font-size: 11px; font-weight: 800; color: #ffeb3b; text-transform: uppercase; margin-top: 1px; letter-spacing: 1px;">
                SELECT GAME
            </div>
        </div>
        <a href="deposit.php" class="wallet-btn" id="walletBtn">
            <img src="images/wallet.png" alt="Wallet" style="width:16px; height:16px; filter: brightness(0) invert(1);">
            <span id="walletBal">₹<?= number_format($totalBalance, 0) ?></span>
        </a>
    </header>

    <div class="container">
        <div class="grid">
            <?php foreach($tiles as [$label, $file, $clr, $target]): ?>
                <a href="<?= htmlspecialchars($target) ?>?market=<?= urlencode($marketName) ?>&id=<?= $marketId ?>" class="market-card <?= htmlspecialchars($clr) ?>">
                    <div class="icon-box">
                        <img src="icons/<?= htmlspecialchars($file) ?>" alt="">
                    </div>
                    <div class="card-content">
                        <div class="card-title"><?= htmlspecialchars(__($label)) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function pingAutoDeclare(){fetch('auto_declare_starline4.php').catch(()=>{})}
        setTimeout(pingAutoDeclare, 1500);
        setInterval(pingAutoDeclare, 60000);
    </script>
</body>
</html>



