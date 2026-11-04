<?php
require_once 'includes/db.php';
require_once 'includes/lang.php';
/* market page – modern tiles grid for this market */
/* LL_MARKET_PAGE_V39 */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/user_ping.php';
require_once 'includes/live_ping.php';

if (empty($_SESSION['user']['id'])) {
  header('Location: user_login.php');
  exit;
}

/* -------- balance -------- */
$db = db();
if ($db->connect_error) die('DB error');
$db->set_charset('utf8mb4');
$db->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$db->query("SET time_zone = '+05:30'");

$uid = (int)$_SESSION['user']['id'];

// fetch user code
$stmt = $db->prepare("SELECT user_id FROM users WHERE id=?");
$stmt->bind_param('i',$uid);
$stmt->execute();
$stmt->bind_result($code);
$stmt->fetch();
$stmt->close();

if (empty($code)) {
  $db->close();
  die('Invalid user');
}

// wallet balance
$stmt = $db->prepare("
    SELECT IFNULL((SELECT SUM(amount) + IFNULL(SUM(bonus_amount),0) FROM deposit WHERE status='Done' AND user_id=?),0)
         - IFNULL((SELECT SUM(amount) FROM withdrawal WHERE LOWER(status) IN ('pending','approved','done') AND user_id=?),0)
    AS bal
");
$stmt->bind_param('ss',$code,$code);
$stmt->execute();
$stmt->bind_result($walletBalance);
$stmt->fetch();
$stmt->close();

// winning balance
$winStmt = $db->prepare("
    SELECT IFNULL(SUM(wining_balance),0)
      FROM wining
     WHERE user_id=?
");
$winStmt->bind_param('s',$code);
$winStmt->execute();
$winStmt->bind_result($winBalance);
$winStmt->fetch();
$winStmt->close();

$db->close();

$totalBalance = (float)$walletBalance + (float)$winBalance;

/* fixed market code for this page */
$marketCode = 'RAJDHANI NIGHT';

/* label , icon , accent-class , target file */
$tiles = [
  ['Single Digit'      , 'single-digit.svg' , 'sd-red'   , 'single_digit.php'     , '0 to 9'],
  ['Single Digit Bulk' , 'single-digit.svg' , 'orange'   , 'single_digit_bulk.php', 'Bulk entry'],
  ['Jodi Digit'        , 'jodi-digit.svg'   , 'purple'   , 'jodi_digit.php'       , '00 to 99'],
  ['Jodi Digit Bulk'   , 'jodi-digit.svg'   , 'teal'     , 'jodi_digit_bulk.php'  , 'Bulk entry'],
  ['Single Pana'       , 'single-pana.svg'  , 'sd-red'   , 'single_pana.php'      , 'Sum of 3 digits'],
  ['Single Pana Bulk'  , 'single-pana.svg'  , 'orange'   , 'single_pana_bulk.php' , 'Bulk entry'],
  ['Double Pana'       , 'double-pana.svg'  , 'purple'   , 'double_pana.php'      , 'Double digits'],
  ['Double Pana Bulk'  , 'double-pana.svg'  , 'teal'     , 'double_pana_bulk.php' , 'Bulk entry'],
  ['Triple Pana'       , 'triple-pana.svg'  , 'sd-red single-row', 'triple_pana.php'      , 'Triple digits'],
  ['SP Motor'          , 'sp-motor.svg'     , 'purple'   , 'sp_motor.php'         , 'SP Motor'],
  ['DP Motor'          , 'dp-motor.svg'     , 'teal'     , 'dp_motor.php'         , 'DP Motor'],
  ['Half Sangam'       , 'half-sangam.svg'  , 'sd-red'   , 'half_sangam.php'      , 'Half Sangam'],
  ['Full Sangam'       , 'full-sangam.svg'  , 'orange'   , 'full_sangam.php'      , 'Full Sangam'],
];
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <title><?= htmlspecialchars($marketCode) ?> – SUBH GAMING</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins', sans-serif; }
    body {
      background-color: #000000 !important;
      background: #000000 !important;
      color: #ffffff;
      min-height: 100vh;
      padding-bottom: 90px;
    }
    
    header {
      background-color: #000000 !important;
      background-image: none !important;
      padding-top: calc(env(safe-area-inset-top) + 10px);
      padding-bottom: 10px;
      padding-left: 14px;
      padding-right: 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      border-bottom: 1px solid #1a1a1a;
    }

    .header-title-flash {
      color: #ffffff;
      font-size: 16px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      flex: 1;
      text-align: center;
      margin: 0 8px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .header-back-btn {
      width: 38px;
      height: 38px;
      border-radius: 8px;
      background: #e53935;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff !important;
      box-shadow: none;
      cursor: pointer;
      border: none;
      font-size: 16px;
      text-decoration: none;
      flex-shrink: 0;
      transition: transform 0.15s ease;
    }
    .header-back-btn:active { transform: scale(0.92); }

    .header-right {
      display: flex;
      align-items: center;
      flex-shrink: 0;
    }

    .wallet-display {
      background: #e53935;
      border: none;
      padding: 5px 10px;
      border-radius: 8px;
      color: #ffffff;
      font-weight: 700;
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      line-height: 1;
      text-decoration: none;
    }
    .wallet-display i {
      font-size: 13px;
      display: inline-flex;
      align-items: center;
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
    .market-card.single-row {
      grid-column: 1 / -1;
      justify-self: center;
    }

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

    /* 4 Alternating Card Themes matching screenshot */
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
    .card-subtitle {
      display: none;
    }
    .card-arrow {
      display: none;
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

<header>
  <a href="user_dashboard.php" class="header-back-btn" title="Back">
    <i class="fa-solid fa-chevron-left"></i>
  </a>
  <div class="header-title-flash"><img src="icons/gold_coin.svg" alt="coin" style="width: 18px; height: 18px; vertical-align: middle; margin-top: -3px;"> <?= htmlspecialchars($marketCode) ?> <img src="icons/gold_coin.svg" alt="coin" style="width: 18px; height: 18px; vertical-align: middle; margin-top: -3px;"></div>
  <div class="header-right">
    <a href="deposit.php" class="wallet-display" style="text-decoration: none;">
      <i class="fa-solid fa-wallet"></i>
      <span id="walletVal"><?= (int)$totalBalance ?></span>
    </a>
  </div>
</header>

<div class="container">
  <div class="grid">
    <?php foreach($tiles as $t): 
      $label = $t[0];
      $file = $t[1];
      $clr = $t[2];
      $target = $t[3];
    ?>
      <a href="<?= htmlspecialchars($target) ?>?market=<?= urlencode($marketCode) ?>" class="market-card <?= htmlspecialchars($clr) ?>">
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

</body>
</html>