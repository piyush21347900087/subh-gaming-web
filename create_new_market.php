<?php
require_once 'includes/db.php';
require_once 'includes/lang.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (php_sapi_name() !== 'cli' && !isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

/* ---------- DB CONNECT ---------- */
try {
  $db = db();
  $db->query("SET time_zone = '+05:30'");
} catch (mysqli_sql_exception $e) {
  die('DB connection failed');
}

/* ---------- ensure HS/FS columns exist (safe) ---------- */
function ensure_hs_fs_columns(mysqli $db): void
{
  $needHS = true;
  $needFS = true;

  $r = $db->query("SHOW COLUMNS FROM markets");
  while ($c = $r->fetch_assoc()) {
    if (!isset($c['Field']))
      continue;
    if ($c['Field'] === 'payout_half_sangam')
      $needHS = false;
    if ($c['Field'] === 'payout_full_sangam')
      $needFS = false;
  }

  if ($needHS) {
    $db->query("ALTER TABLE markets ADD COLUMN payout_half_sangam INT NOT NULL DEFAULT 10000");
  }
  if ($needFS) {
    $db->query("ALTER TABLE markets ADD COLUMN payout_full_sangam INT NOT NULL DEFAULT 100000");
  }
}
ensure_hs_fs_columns($db);

/* ---------- ensure market_type column exists ---------- */
function ensure_market_type_column(mysqli $db): void
{
  $needType = true;
  $r = $db->query("SHOW COLUMNS FROM markets");
  while ($c = $r->fetch_assoc()) {
    if ($c['Field'] === 'market_type') {
      $needType = false;
      break;
    }
  }
  if ($needType) {
    $db->query("ALTER TABLE markets ADD COLUMN market_type VARCHAR(50) NOT NULL DEFAULT 'regular'");
  }
}
ensure_market_type_column($db);

/* ---------- ensure market_color column exists ---------- */
function ensure_market_color_column(mysqli $db): void
{
  $needCol = true;
  $r = $db->query("SHOW COLUMNS FROM markets");
  while ($c = $r->fetch_assoc()) {
    if ($c['Field'] === 'market_color') {
      $needCol = false;
      break;
    }
  }
  if ($needCol) {
    $db->query("ALTER TABLE markets ADD COLUMN market_color VARCHAR(50) NOT NULL DEFAULT '#000000'");
  }
}
ensure_market_color_column($db);

/* ---------- ensure min_bid column exists ---------- */
function ensure_min_bid_column(mysqli $db): void
{
  $needMinBid = true;
  $r = $db->query("SHOW COLUMNS FROM markets");
  while ($c = $r->fetch_assoc()) {
    if ($c['Field'] === 'min_bid') {
      $needMinBid = false;
      break;
    }
  }
  if ($needMinBid) {
    $db->query("ALTER TABLE markets ADD COLUMN min_bid INT NOT NULL DEFAULT 10");
  }
}
ensure_min_bid_column($db);

/* ---------- helper: slug variants ---------- */
function slug_variants(string $marketName): array
{
  $name = strtolower(trim($marketName));
  // Replace anything that is NOT a-z or 0-9 with an underscore
  $slug = preg_replace('/[^a-z0-9]+/', '_', $name);
  // Remove leading/trailing underscores
  $slug = trim($slug, '_');
  return ($slug === '') ? [] : [$slug];
}

/* ---------- helper: create/upgrade market page (multi-slug) ---------- */
function ensure_market_page(string $marketName): void
{
  $variants = slug_variants($marketName);
  if (!$variants)
    return;

  $marketEsc = str_replace("'", "\\'", $marketName);

  // ✅ marker to detect latest template (so old pages can be upgraded safely)
  $marker = 'LL_MARKET_PAGE_V39';

  $template = <<<'TPL'
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
$marketCode = '__MARKET_NAME__';

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
TPL;

  $code = str_replace('__MARKET_NAME__', $marketEsc, $template);

  foreach ($variants as $slug) {
    $filePath = __DIR__ . '/' . $slug . '.php';

    if (!file_exists($filePath)) {
      file_put_contents($filePath, $code);
      continue;
    }

    $existing = @file_get_contents($filePath);
    if ($existing !== false && strpos($existing, $marker) === false) {
      file_put_contents($filePath, $code);
    }
  }
}
/* ---------- INIT FORM DATA ---------- */
$id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $id > 0;
$error = '';
$success = '';
$showConfirmOverride = false;
$market_name = '';
$default_result = '';
$open_time = '';
$close_time = '';
$today_closed = 0;
$market_color = '#000000';

$saturday_closed = 1;
$sunday_closed = 1;
$custom_close_text = 'Saturday & Sunday Holidays';

$p_sd = 100;
$p_jd = 1000;
$p_sp = 1600;
$p_dp = 3200;
$p_tp = 7000;

$p_hs = 10000;
$p_fs = 100000;

$is_published = 1;
$status = 'active';

/* ---------- LOAD EXISTING (EDIT) ---------- */
if ($isEdit) {
  $stmt = $db->prepare("SELECT * FROM markets WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    $market_name = $row['market_name'];
    $default_result = $row['default_result'];
    $open_time = $row['open_time'];
    $close_time = $row['close_time'];
    $today_closed = (int) $row['today_closed'];
    $saturday_closed = (int) $row['saturday_closed'];
    $sunday_closed = (int) $row['sunday_closed'];
    $custom_close_text = $row['custom_close_text'];

    $p_sd = (int) $row['payout_single_digit'];
    $p_jd = (int) $row['payout_jodi_digit'];
    $p_sp = (int) $row['payout_single_pana'];
    $p_dp = (int) $row['payout_double_pana'];
    $p_tp = (int) $row['payout_triple_pana'];

    $p_hs = isset($row['payout_half_sangam']) ? (int) $row['payout_half_sangam'] : $p_hs;
    $p_fs = isset($row['payout_full_sangam']) ? (int) $row['payout_full_sangam'] : $p_fs;

    $is_published = (int) $row['is_published'];
    $status = $row['status'];
    $market_color = $row['market_color'] ?? '#000000';
  } else {
    $error = 'Market not found.';
  }
  $stmt->close();
} else {
  $default_result = '***-**-***';
}

/* ---------- HANDLE POST ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $market_name = strtoupper(trim($_POST['market_name'] ?? ''));
  $default_result = trim($_POST['default_result'] ?? '');
  if ($default_result === '')
    $default_result = '***-**-***';

  $open_time = trim($_POST['open_time'] ?? '');
  $close_time = trim($_POST['close_time'] ?? '');
  $today_closed = isset($_POST['today_closed']) ? 1 : 0;
  $saturday_closed = isset($_POST['saturday_closed']) ? 1 : 0;
  $sunday_closed = isset($_POST['sunday_closed']) ? 1 : 0;

  $custom_close_text = trim($_POST['custom_close_text'] ?? '');
  if ($custom_close_text === '')
    $custom_close_text = 'Saturday & Sunday Holidays';

  $p_sd = (int) ($_POST['p_sd'] ?? 0);
  $p_jd = (int) ($_POST['p_jd'] ?? 0);
  $p_sp = (int) ($_POST['p_sp'] ?? 0);
  $p_dp = (int) ($_POST['p_dp'] ?? 0);
  $p_tp = (int) ($_POST['p_tp'] ?? 0);

  $p_hs = (int) ($_POST['p_hs'] ?? 0);
  $p_fs = (int) ($_POST['p_fs'] ?? 0);

  $is_published = isset($_POST['is_published']) ? 1 : 0;
  $status = $is_published ? 'active' : 'inactive';
  $market_color = trim($_POST['market_color'] ?? '#000000');

  if ($market_name === '') {
    $error = 'Market name is required.';
  }

  if ($error === '') {
    try {
      $force_override = isset($_POST['force_override']) && $_POST['force_override'] === '1';

      if (!$isEdit && $force_override) {
        $st_find = $db->prepare("SELECT id FROM markets WHERE market_name=? LIMIT 1");
        $st_find->bind_param('s', $market_name);
        $st_find->execute();
        $st_find->bind_result($existing_id);
        if ($st_find->fetch()) {
          $id = $existing_id;
          $isEdit = true;
        }
        $st_find->close();
      }

      if ($isEdit) {
        $stmt = $db->prepare("
                    UPDATE markets
                       SET market_name=?,
                           default_result=?,
                           open_time=?,
                           close_time=?,
                           today_closed=?,
                           saturday_closed=?,
                           sunday_closed=?,
                           custom_close_text=?,
                           payout_single_digit=?,
                           payout_jodi_digit=?,
                           payout_single_pana=?,
                           payout_double_pana=?,
                           payout_triple_pana=?,
                           payout_half_sangam=?,
                           payout_full_sangam=?,
                           is_published=?,
                           status=?,
                           market_color=?
                     WHERE id=? LIMIT 1
                ");
        $stmt->bind_param(
          'ssssiiisiiiiiiiissi',
          $market_name,
          $default_result,
          $open_time,
          $close_time,
          $today_closed,
          $saturday_closed,
          $sunday_closed,
          $custom_close_text,
          $p_sd,
          $p_jd,
          $p_sp,
          $p_dp,
          $p_tp,
          $p_hs,
          $p_fs,
          $is_published,
          $status,
          $market_color,
          $id
        );
        $stmt->execute();
        $stmt->close();

        // ✅ update this market page and refresh every page listed in markets table
        ensure_market_page($market_name);
        ensure_chart_page($market_name);
        upgrade_all_market_pages($db);

        $success = 'Market updated successfully. All market pages refreshed.';
      } else {
        $row = $db->query("SELECT IFNULL(MAX(sort_order),0)+1 AS pos FROM markets")->fetch_assoc();
        $nextSort = (int) $row['pos'];

        $stmt = $db->prepare("
                    INSERT INTO markets
                      (market_name, default_result, open_time, close_time,
                       today_closed, saturday_closed, sunday_closed, custom_close_text,
                       payout_single_digit, payout_jodi_digit, payout_single_pana,
                       payout_double_pana, payout_triple_pana,
                       payout_half_sangam, payout_full_sangam,
                       is_published, status, sort_order, market_type, market_color)
                    VALUES (?,?,?,?,?,?,?,?,
                            ?,?,?,?,?,
                            ?,?,
                            ?,?,?, 'regular', ?)
                ");
        $stmt->bind_param(
          'ssssiiisiiiiiiiiisi',
          $market_name,
          $default_result,
          $open_time,
          $close_time,
          $today_closed,
          $saturday_closed,
          $sunday_closed,
          $custom_close_text,
          $p_sd,
          $p_jd,
          $p_sp,
          $p_dp,
          $p_tp,
          $p_hs,
          $p_fs,
          $is_published,
          $status,
          $nextSort,
          $market_color
        );
        $stmt->execute();
        $stmt->close();

        ensure_market_page($market_name);
        ensure_chart_page($market_name);
        upgrade_all_market_pages($db);

        header('Location: list_of_market.php');
        exit;
      }
    } catch (Throwable $e) {
      if (!$isEdit && (strpos($e->getMessage(), 'Duplicate entry') !== false || (method_exists($e, 'getCode') && $e->getCode() == 1062))) {
        $showConfirmOverride = true;
        $error = "Duplicate entry detected: Market '" . htmlspecialchars($market_name) . "' already exists.";
      } else {
        $error = $e->getMessage();
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <title><?= $isEdit ? 'Edit Market' : 'Create New Market' ?> – SUBH GAMING Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
  <style>
    :root {
      --red: #0066cc;
      --brown: #4e342e;
      --shadow: rgba(0, 0, 0, 0.1);
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      margin: 0;
      padding: 0;
      font-family: 'Roboto', sans-serif;
      background: #f5f0e5;
      color: #333;
    }

    a {
      text-decoration: none;
      color: inherit;
    }

    .wrapper {
      max-width: 900px;
      margin: 20px auto 30px;
      padding: 0 16px;
    }

    .topbar {
      background: #ffffff;
      padding: 10px 14px;
      border-radius: 10px;
      box-shadow: 0 2px 8px var(--shadow);
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .top-left-actions {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 20px;
      border: none;
      font-size: 13px;
      cursor: pointer;
      font-weight: 600;
      transition: 0.15s;
    }

    .btn-secondary {
      background: #eeeeee;
      color: #333;
    }

    .btn-secondary:hover {
      background: #e0e0e0;
    }

    .top-title {
      font-size: 18px;
      font-weight: 700;
      color: var(--brown);
    }

    .card {
      margin-top: 16px;
      background: #ffffff;
      border-radius: 10px;
      box-shadow: 0 3px 10px var(--shadow);
      padding: 16px;
    }

    .card h2 {
      margin-top: 0;
      margin-bottom: 12px;
      font-size: 16px;
      color: var(--brown);
    }

    .row {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
    }

    .field {
      flex: 1 1 220px;
    }

    .field label {
      display: block;
      font-size: 13px;
      margin-bottom: 4px;
      color: #555;
    }

    .field input[type="text"],
    .field input[type="time"],
    .field input[type="color"] {
      width: 100%;
      padding: 7px 8px;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 13px;
    }

    .field small {
      font-size: 11px;
      color: #777;
    }

    .inline-check {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      margin-top: 6px;
    }

    .grid-3 {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 10px;
    }

    .grid-3 .field {
      flex: 1 1 150px;
    }

    .alert {
      margin-top: 10px;
      padding: 8px 10px;
      border-radius: 8px;
      font-size: 13px;
    }

    .alert.error {
      background: #e3f2fd;
      color: #0066cc;
    }

    .alert.success {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .submit-row {
      margin-top: 14px;
      text-align: right;
    }

    .btn-primary {
      background: #0066cc;
      color: #fff;
      border-radius: 20px;
      border: none;
      padding: 8px 18px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
    }

    .btn-primary:hover {
      background: #004d99;
    }

    @media(max-width:600px) {
      .row {
        flex-direction: column;
      }
    }
  </style>
</head>

<body>
  <div class="wrapper">
    <div class="topbar">
      <div class="top-left-actions">
        <a href="list_of_market.php" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Market List
        </a>
      </div>
      <div class="top-title"><?= $isEdit ? 'Edit Market' : 'Create New Market' ?></div>
    </div>

    <div class="card">
      <h2>Market Details</h2>

      <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
      <?php elseif ($success): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <div class="row">
          <div class="field">
            <label>Market Name <span style="color:#0066cc">*</span></label>
            <input type="text" name="market_name" value="<?= htmlspecialchars($market_name) ?>"
              placeholder="KALYAN, DIVYA..." required>
            <small>Auto page banega: e.g. KALYAN → <code>kalyan.php</code></small>
          </div>

          <div class="field">
            <label>Default Result</label>
            <input type="text" name="default_result"
              value="<?= htmlspecialchars($default_result !== '' ? $default_result : '***-**-***') ?>"
              placeholder="***-**-***">
            <small>Format: <code>***-**-***</code> (e.g. 123-45-678)</small>
          </div>
        </div>

        <div class="row" style="margin-top:10px;">
          <div class="field"><label>Open Time</label><input type="time" name="open_time"
              value="<?= htmlspecialchars($open_time) ?>"></div>
          <div class="field"><label>Close Time</label><input type="time" name="close_time"
              value="<?= htmlspecialchars($close_time) ?>"></div>
        </div>



        <div class="row" style="margin-top:10px;">
          <div class="field">
            <label>Today</label>
            <div class="inline-check">
              <input type="checkbox" name="today_closed" id="today_closed" <?= $today_closed ? 'checked' : '' ?>>
              <label for="today_closed">Closed (tick to close today)</label>
            </div>
          </div>
          <div class="field">
            <label>Saturday</label>
            <div class="inline-check">
              <input type="checkbox" name="saturday_closed" id="saturday_closed" <?= $saturday_closed ? 'checked' : '' ?>>
              <label for="saturday_closed">Closed</label>
            </div>
          </div>
          <div class="field">
            <label>Sunday</label>
            <div class="inline-check">
              <input type="checkbox" name="sunday_closed" id="sunday_closed" <?= $sunday_closed ? 'checked' : '' ?>>
              <label for="sunday_closed">Closed</label>
            </div>
          </div>
        </div>

        <div class="row" style="margin-top:10px;">
          <div class="field">
            <label>Custom Close Text</label>
            <input type="text" name="custom_close_text" value="<?= htmlspecialchars($custom_close_text) ?>"
              placeholder="Saturday & Sunday Holidays">
          </div>
          <div class="field">
            <label>Published?</label>
            <div class="inline-check">
              <input type="checkbox" name="is_published" id="is_published" <?= $is_published ? 'checked' : '' ?>>
              <label for="is_published">Show in user app</label>
            </div>
          </div>
        </div>

        <h2 style="margin-top:16px;">Payouts (per ₹10)</h2>
        <div class="grid-3">
          <div class="field"><label>Single Digit (SD)</label><input type="text" name="p_sd"
              value="<?= htmlspecialchars($p_sd) ?>"></div>
          <div class="field"><label>Jodi Digit (JD)</label><input type="text" name="p_jd"
              value="<?= htmlspecialchars($p_jd) ?>"></div>
          <div class="field"><label>Single Pana (SP)</label><input type="text" name="p_sp"
              value="<?= htmlspecialchars($p_sp) ?>"></div>
          <div class="field"><label>Double Pana (DP)</label><input type="text" name="p_dp"
              value="<?= htmlspecialchars($p_dp) ?>"></div>
          <div class="field"><label>Triple Pana (TP)</label><input type="text" name="p_tp"
              value="<?= htmlspecialchars($p_tp) ?>"></div>
          <div class="field"><label>Half Sangam (HS)</label><input type="text" name="p_hs"
              value="<?= htmlspecialchars($p_hs) ?>"></div>
          <div class="field"><label>Full Sangam (FS)</label><input type="text" name="p_fs"
              value="<?= htmlspecialchars($p_fs) ?>"></div>
        </div>

        <div class="submit-row">
          <button type="submit" class="btn-primary"><?= $isEdit ? 'Update Market' : 'Create Market' ?></button>
        </div>
      </form>
    </div>
  </div>
  <?php if ($showConfirmOverride): ?>
    <script>
      if (confirm("Market '<?= addslashes($market_name) ?>' already exists. Do you want to OVERRIDE its settings with the new data?")) {
        const form = document.querySelector('form');
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'force_override';
        hidden.value = '1';
        form.appendChild(hidden);
        form.submit();
      }
    </script>
  <?php endif; ?>
</body>

</html>

