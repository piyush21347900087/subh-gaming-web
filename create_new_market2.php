<?php
require_once 'includes/db.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
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

/* ---------- ensure market_type column exists ---------- */
/* ---------- ensure gali_markets table exists ---------- */
function ensure_gali_markets_table(mysqli $db): void
{
    $res = $db->query("SHOW TABLES LIKE 'gali_markets'");
    if ($res->num_rows === 0) {
        // Clone structure from markets + add specific defaults if needed
        // For simplicity, we create a similar structure
        $sql = "CREATE TABLE gali_markets (
      id INT AUTO_INCREMENT PRIMARY KEY,
      market_name VARCHAR(100) NOT NULL,
      default_result VARCHAR(50) DEFAULT '**',
      result_time TIME,
      close_time_start TIME,
      close_time_end TIME,
      today_closed TINYINT(1) DEFAULT 0,
      saturday_closed TINYINT(1) DEFAULT 0,
      sunday_closed TINYINT(1) DEFAULT 0,
      custom_close_text VARCHAR(255),
      payout_left_digit INT DEFAULT 100,
      payout_right_digit INT DEFAULT 100,
      payout_jodi_digit INT DEFAULT 1000,
      is_published TINYINT(1) DEFAULT 1,
      status VARCHAR(20) DEFAULT 'active',
      sort_order INT DEFAULT 0,
      is_next_day TINYINT(1) DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $db->query($sql);
    }
}
ensure_gali_markets_table($db);

/* Upgrade schema if needed */
try {
    $cols = [
        'payout_left_digit' => 'INT DEFAULT 100',
        'payout_right_digit' => 'INT DEFAULT 100',
        'payout_jodi_digit' => 'INT DEFAULT 1000',
        'sort_order' => 'INT DEFAULT 0',
        'is_next_day' => 'TINYINT(1) DEFAULT 0'
    ];
    foreach ($cols as $c => $def) {
        $res = $db->query("SHOW COLUMNS FROM gali_markets LIKE '$c'");
        if ($res->num_rows === 0) {
            $db->query("ALTER TABLE gali_markets ADD COLUMN `$c` $def");
        }
    }
} catch (Throwable $e) {}

/* ---------- helper: slug variants ---------- */
function slug_variants(string $marketName): array
{
    $name = mb_strtolower(trim($marketName), 'UTF-8');
    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '_', $name);
    $slug = trim($slug, '_');
    
    if ($slug === '') {
        $slug = 'market_' . hash('crc32b', $marketName);
    }
    return [$slug];
}

/* ---------- helper: create/upgrade GALI market page ---------- */
function ensure_gali_market_page(string $marketName): void
{
    $variants = slug_variants($marketName);
    if (!$variants)
        return;

    $marketEsc = str_replace("'", "\\'", $marketName);

    // ✅ marker to detect GALI template
    $marker = 'RK_GALI_MARKET_V2';

    $template = <<<'TPL'
<?php
require_once 'includes/db.php';
/* market page – Gali Desawar (Haruf/Jodi) */
/* RK_GALI_MARKET_V2 */
session_start();
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
    SELECT IFNULL((SELECT SUM(amount) FROM deposit    WHERE status='Done' AND user_id=?),0)
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
/* Gali Markets: Left Digit, Right Digit, Jodi Digit */
$tiles = [
  ['Left Digit'          , 'single-digit.svg'   , 'red'   , 'left_digit.php'   ],
  ['Right Digit'         , 'single-digit.svg'   , 'blue'  , 'right_digit.php'  ],
  ['Jodi Digit'          , 'jodi-digit.svg'     , 'teal'  , 'jodi.php'         ],
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
$market_name = '';
$default_result = '**';
$result_time = '00:00';
$close_time_start = '23:45';
$close_time_end = '00:00';
$today_closed = 0;
$saturday_closed = 0;
$sunday_closed = 0;
$custom_close_text = 'Saturday & Sunday Holidays';

$p_left = 100; // Left Digit
$p_right = 100; // Right Digit
$p_jd = 1000; // Jodi

$is_published = 0;
$status = 'inactive';
$is_next_day = 0;

/* ---------- LOAD EXISTING (EDIT) ---------- */
if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM gali_markets WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $market_name = $row['market_name'];
        $default_result = $row['default_result'];
        $result_time = $row['result_time'] ?? '00:00';
        $close_time_start = $row['close_time_start'] ?? '23:45';
        $close_time_end = $row['close_time_end'] ?? '00:00';
        $today_closed = (int) $row['today_closed'];
        $saturday_closed = (int) $row['saturday_closed'];
        $sunday_closed = (int) $row['sunday_closed'];
        $custom_close_text = $row['custom_close_text'];

        $p_left = isset($row['payout_left_digit']) ? (int) $row['payout_left_digit'] : 100;
        $p_right = isset($row['payout_right_digit']) ? (int) $row['payout_right_digit'] : 100;
        $p_jd = (int) $row['payout_jodi_digit'];

        // Ignore Pana/Sangam fields for Gali markets

        $is_published = (int) $row['is_published'];
        $status = $row['status'];
        $is_next_day = (int) ($row['is_next_day'] ?? 0);
    } else {
        $error = 'Market not found.';
    }
    $stmt->close();
} else {
    $default_result = '**';
}

/* ---------- HANDLE POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $market_name = strtoupper(trim($_POST['market_name'] ?? ''));
    $default_result = trim($_POST['default_result'] ?? '');
    if ($default_result === '')
        $default_result = '**';

    $result_time = trim($_POST['result_time'] ?? '');
    $close_time_start = trim($_POST['close_time_start'] ?? '');
    $close_time_end = trim($_POST['close_time_end'] ?? '');

    $today_closed = isset($_POST['today_closed']) ? 1 : 0;
    $saturday_closed = isset($_POST['saturday_closed']) ? 1 : 0;
    $sunday_closed = isset($_POST['sunday_closed']) ? 1 : 0;

    $custom_close_text = trim($_POST['custom_close_text'] ?? '');
    if ($custom_close_text === '')
        $custom_close_text = 'Saturday & Sunday Holidays';

    $p_left = (int) ($_POST['p_left'] ?? 0);
    $p_right = (int) ($_POST['p_right'] ?? 0);
    $p_jd = (int) ($_POST['p_jd'] ?? 0);

    // Set defaults for unused Gali columns
    $p_sp = 0;
    $p_dp = 0;
    $p_tp = 0;
    $p_hs = 0;
    $p_fs = 0;

    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $status = $is_published ? 'active' : 'inactive';
    $is_next_day = isset($_POST['is_next_day']) ? 1 : 0;

    if ($market_name === '') {
        $error = 'Market name is required.';
    }

    if ($error === '') {
        try {
            if ($isEdit) {
                $stmt = $db->prepare("
                    UPDATE gali_markets
                       SET market_name=?,
                           default_result=?,
                           result_time=?,
                           close_time_start=?,
                           close_time_end=?,
                           today_closed=?,
                           saturday_closed=?,
                           sunday_closed=?,
                           custom_close_text=?,
                           payout_left_digit=?,
                           payout_right_digit=?,
                           payout_jodi_digit=?,
                           is_published=?,
                           status=?,
                           is_next_day=?
                     WHERE id=? LIMIT 1
                ");
                $stmt->bind_param(
                    'sssssiiisiiiisii',
                    $market_name,
                    $default_result,
                    $result_time,
                    $close_time_start,
                    $close_time_end,
                    $today_closed,
                    $saturday_closed,
                    $sunday_closed,
                    $custom_close_text,
                    $p_left,
                    $p_right,
                    $p_jd,
                    $is_published,
                    $status,
                    $is_next_day,
                    $id
                );
                $stmt->execute();
                $stmt->close();

                ensure_gali_market_page($market_name);
                ensure_gali_chart_page($market_name);

                $success = 'Gali Market updated successfully.';
            } else {
                $row = $db->query("SELECT IFNULL(MAX(sort_order),0)+1 AS pos FROM gali_markets")->fetch_assoc();
                $nextSort = (int) $row['pos'];

                $stmt = $db->prepare("
                    INSERT INTO gali_markets
                      (market_name, default_result, result_time,
                       close_time_start, close_time_end,
                       today_closed, saturday_closed, sunday_closed, custom_close_text,
                       payout_left_digit, payout_right_digit, payout_jodi_digit,
                       is_published, status, sort_order, is_next_day)
                    VALUES (?,?,?,?,?,?,?,?,
                            ?,?,?,?,
                            ?,?, ?, ?)
                ");
                $stmt->bind_param(
                    'sssssiiisiiiisii',
                    $market_name,
                    $default_result,
                    $result_time,
                    $close_time_start,
                    $close_time_end,
                    $today_closed,
                    $saturday_closed,
                    $sunday_closed,
                    $custom_close_text,
                    $p_left,
                    $p_right,
                    $p_jd,
                    $is_published,
                    $status,
                    $nextSort,
                    $is_next_day
                );
                $stmt->execute();
                $stmt->close();

                ensure_gali_market_page($market_name);
                ensure_gali_chart_page($market_name);

                header('Location: list_of_market2.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>
        <?php echo  $isEdit ? 'Edit Gali Market' : 'Create Gali Market' ?> – DESAWAR BOSS Admin
    </title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c62828;
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
            background: #fff;
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
            background: #fff;
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

        .grid-checkboxes {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 15px;
            padding: 10px 0;
            border-top: 1px dashed #eee;
        }

        @media(max-width: 500px) {
            .grid-checkboxes {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .field label {
            display: block;
            font-size: 13px;
            margin-bottom: 4px;
            color: #555;
        }

        .field input[type="text"],
        .field input[type="time"] {
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
            background: #ffebee;
            color: #c62828;
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
            background: #c62828;
            color: #fff;
            border-radius: 20px;
            border: none;
            padding: 8px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: #b71c1c;
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
                <a href="list_of_market2.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Market List
                </a>
            </div>
            <div class="top-title">
                <?php echo  $isEdit ? 'Edit Gali Market' : 'Create Gali Market' ?>
            </div>
        </div>

        <div class="card">
            <h2>Gali Market Details</h2>

            <?php
            require_once 'includes/db.php';
            if ($error): ?>
                <div class="alert error">
                    <?php echo  htmlspecialchars($error) ?>
                </div>
                <?php
                require_once 'includes/db.php';
            elseif ($success): ?>
                <div class="alert success">
                    <?php echo  htmlspecialchars($success) ?>
                </div>
                <?php
                require_once 'includes/db.php';
            endif; ?>

            <form method="post" autocomplete="off">
                <div class="row">
                    <div class="field">
                        <label>Market Name <span style="color:#c62828">*</span></label>
                        <input type="text" name="market_name" value="<?php echo  htmlspecialchars($market_name) ?>"
                            placeholder="GALI, DESAWAR..." required>
                        <small>Generates page: e.g. GALI → <code>gali.php</code></small>
                    </div>

                    <div class="field">
                        <label>Default Result</label>
                        <input type="text" name="default_result"
                            value="<?php echo  htmlspecialchars($default_result !== '' ? $default_result : '**') ?>"
                            placeholder="**">
                        <small>Format: <code>**</code> (e.g. 56)</small>
                    </div>
                </div>

                <div class="row" style="margin-top:10px;">
                    <div class="field">
                        <label>Result Time</label>
                        <input type="time" name="result_time" value="<?php echo  htmlspecialchars($result_time) ?>">
                    </div>
                    <div class="field">
                        <label>Close Time (Start)</label>
                        <input type="time" name="close_time_start" value="<?php echo  htmlspecialchars($close_time_start) ?>">
                    </div>
                    <div class="field">
                        <label>Close Time (End)</label>
                        <input type="time" name="close_time_end" value="<?php echo  htmlspecialchars($close_time_end) ?>">
                    </div>
                </div>

                <div class="row" style="margin-top:10px;">
                    <div class="field">
                        <label>Custom Close Text</label>
                        <input type="text" name="custom_close_text" value="<?php echo  htmlspecialchars($custom_close_text) ?>"
                            placeholder="Saturday & Sunday Holidays">
                    </div>
                </div>

                <div class="grid-checkboxes" style="margin-top:10px;">
                    <div class="field">
                        <label>Today</label>
                        <div class="inline-check">
                            <input type="checkbox" name="today_closed" id="today_closed" <?php echo  $today_closed ? 'checked' : '' ?>>
                            <label for="today_closed">Closed</label>
                        </div>
                    </div>
                    <div class="field">
                        <label>Saturday</label>
                        <div class="inline-check">
                            <input type="checkbox" name="saturday_closed" id="saturday_closed" <?php echo  $saturday_closed ? 'checked' : '' ?>>
                            <label for="saturday_closed">Closed</label>
                        </div>
                    </div>
                    <div class="field">
                        <label>Sunday</label>
                        <div class="inline-check">
                            <input type="checkbox" name="sunday_closed" id="sunday_closed" <?php echo  $sunday_closed ? 'checked' : '' ?>>
                            <label for="sunday_closed">Closed</label>
                        </div>
                    </div>
                    <div class="field">
                        <label>Published?</label>
                        <div class="inline-check">
                            <input type="checkbox" name="is_published" id="is_published" <?php echo  $is_published ? 'checked' : '' ?>>
                            <label for="is_published">Show App</label>
                        </div>
                    </div>
                    <div class="field">
                        <label>Next Day?</label>
                        <div class="inline-check">
                            <input type="checkbox" name="is_next_day" id="is_next_day" <?php echo  $is_next_day ? 'checked' : '' ?>>
                            <label for="is_next_day">One Day Later</label>
                        </div>
                    </div>
                </div>

                <h2 style="margin-top:16px;">Payouts (per ₹10)</h2>
                <div class="grid-3">
                    <div class="field"><label>Left Digit (100)</label><input type="text" name="p_left"
                            value="<?php echo  htmlspecialchars($p_left) ?>"></div>
                    <div class="field"><label>Right Digit (100)</label><input type="text" name="p_right"
                            value="<?php echo  htmlspecialchars($p_right) ?>"></div>
                    <div class="field"><label>Jodi Digit (1000)</label><input type="text" name="p_jd"
                            value="<?php echo  htmlspecialchars($p_jd) ?>"></div>
                </div>

                <div class="submit-row">
                    <button type="submit" class="btn-primary">
                        <?php echo  $isEdit ? 'Update Gali Market' : 'Create Gali Market' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>

