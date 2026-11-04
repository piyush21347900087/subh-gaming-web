<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';
require_once 'includes/lang.php';
require_once 'includes/secure_bid_time.php';
date_default_timezone_set('Asia/Kolkata');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* ---------- Market ---------- */
$market = strtoupper($_GET['market'] ?? '');
if ($market === '')
  $market = 'WELCOME';
$market = substr($market, 0, 20); // bid.market varchar(20) safe
$marketLabel = ucfirst(strtolower($market));

/* ---------- Timing helper ---------- */
require_once 'disable_enable.php';

/* ---------- User check ---------- */
require_once 'includes/user_ping.php';
$uid = (int) ($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) {
  header('Location: user_login.php');
  exit;
}

/* ---------- DB helpers ---------- */
function db_conn(): mysqli
{
  $db = db();
  $db->query("SET time_zone = '+05:30'");
  return $db;
}
function fetch_LL_code(mysqli $db, int $uid): string
{
  $st = $db->prepare("SELECT user_id FROM users WHERE id=? LIMIT 1");
  $st->bind_param('i', $uid);
  $st->execute();
  $st->bind_result($LL);
  $st->fetch();
  $st->close();
  return (string) $LL;
}
function compute_total_balance(mysqli $db, string $code): float
{
    return get_total_balance($db, $code);
}

/* ---------- Balance ---------- */
$db = db_conn();
$LLCode = fetch_LL_code($db, $uid);
if ($LLCode === '') {
  die('Invalid user');
}
$totalBalance = compute_total_balance($db, $LLCode);
$minBid = getMinBid($db);

/* ---------- Market status text ---------- */
$marketStatusText = $jodiDisabled ? 'Market Time Close' : 'Market Open';
$marketStatusColor = $jodiDisabled ? 'red' : '#0a8f08';

/* ---------- Valid Pana List ---------- */
$validPanas = [
  /* SP (120) */
  '128',
  '137',
  '146',
  '236',
  '245',
  '290',
  '380',
  '470',
  '489',
  '560',
  '579',
  '678',
  '129',
  '138',
  '147',
  '156',
  '237',
  '246',
  '345',
  '390',
  '480',
  '570',
  '589',
  '679',
  '120',
  '139',
  '148',
  '157',
  '238',
  '247',
  '256',
  '346',
  '490',
  '580',
  '670',
  '689',
  '130',
  '149',
  '158',
  '167',
  '239',
  '248',
  '257',
  '347',
  '356',
  '590',
  '680',
  '789',
  '140',
  '159',
  '168',
  '230',
  '249',
  '258',
  '267',
  '348',
  '357',
  '456',
  '690',
  '780',
  '123',
  '150',
  '169',
  '178',
  '240',
  '259',
  '268',
  '349',
  '358',
  '367',
  '457',
  '790',
  '124',
  '160',
  '278',
  '179',
  '250',
  '269',
  '340',
  '359',
  '368',
  '458',
  '467',
  '890',
  '125',
  '134',
  '170',
  '189',
  '260',
  '279',
  '350',
  '369',
  '468',
  '378',
  '459',
  '567',
  '126',
  '135',
  '180',
  '234',
  '270',
  '289',
  '360',
  '379',
  '450',
  '469',
  '478',
  '568',
  '127',
  '136',
  '145',
  '190',
  '235',
  '280',
  '370',
  '389',
  '460',
  '479',
  '569',
  '578',
  /* DP (90) */
  '100',
  '110',
  '112',
  '113',
  '114',
  '115',
  '116',
  '117',
  '118',
  '119',
  '122',
  '133',
  '144',
  '155',
  '166',
  '177',
  '188',
  '199',
  '200',
  '220',
  '900',
  '223',
  '224',
  '110',
  '225',
  '226',
  '227',
  '228',
  '229',
  '233',
  '244',
  '255',
  '266',
  '277',
  '120',
  '288',
  '299',
  '300',
  '330',
  '124',
  '990',
  '125',
  '800',
  '334',
  '335',
  '336',
  '337',
  '338',
  '339',
  '344',
  '355',
  '366',
  '377',
  '388',
  '399',
  '400',
  '440',
  '140',
  '700',
  '141',
  '889',
  '142',
  '799',
  '445',
  '446',
  '447',
  '448',
  '449',
  '455',
  '466',
  '150',
  '477',
  '151',
  '488',
  '152',
  '499',
  '500',
  '550',
  '155',
  '699',
  '770',
  '899',
  '880',
  '556',
  '557',
  '558',
  '559',
  '566',
  '577',
  '588',
  '599',
  '600',
  '660',
  '667',
  '668',
  '669',
  '677',
  '688',
  '778',
  '779',
  '788',
  /* TP (10) */
  '000',
  '111',
  '222',
  '333',
  '444',
  '555',
  '666',
  '777',
  '888',
  '999'
];
$validPanas = array_values(array_unique($validPanas));
sort($validPanas);

/* ---------- Submit (server validation) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bids'])) {

  // ✅ AJAX mode: return JSON
  $isAjax = (isset($_POST['ajax']) && $_POST['ajax'] == '1');
  if ($isAjax)
    header('Content-Type: application/json; charset=utf-8');
  $bids = json_decode((string) $_POST['bids'], true);
  if (!is_array($bids) || !count($bids)) {
    if ($isAjax) {
      echo json_encode(['ok' => false, 'msg' => 'Invalid payload']);
      exit;
    }
    die('Invalid payload');
  }

  $validSet = array_flip($validPanas);
  $safe = [];
  $total = 0;
  $secureCtx = vardhan_secure_bid_context($db, $market);
  if (empty($secureCtx['ok'])) {
    if ($isAjax) {
      echo json_encode(['ok' => false, 'msg' => $secureCtx['message'] ?? 'Bidding closed']);
      exit;
    }
    die($secureCtx['message'] ?? 'Bidding closed');
  }
  $sessionCheck = vardhan_secure_session($secureCtx, 'Open', true);
  if (empty($sessionCheck['ok'])) {
    if ($isAjax) {
      echo json_encode(['ok' => false, 'msg' => $sessionCheck['message'] ?? 'Bidding closed']);
      exit;
    }
    die($sessionCheck['message'] ?? 'Bidding closed');
  }

  foreach ($bids as $r) {
    $openP = preg_replace('/[^0-9]/', '', (string) ($r['open_pana'] ?? ''));
    $closeP = preg_replace('/[^0-9]/', '', (string) ($r['close_pana'] ?? ''));
    $amt = (int) ($r['amount'] ?? 0);

    if (strlen($openP) !== 3 || strlen($closeP) !== 3)
      continue;
    if (!isset($validSet[$openP]) || !isset($validSet[$closeP]))
      continue;
    if ($amt < $minBid)
      continue;

    $safe[] = ['open_pana' => $openP, 'close_pana' => $closeP, 'amount' => $amt];
    $total += $amt;
  }

  if (!count($safe)) {
    if ($isAjax) {
      echo json_encode(['ok' => false, 'msg' => 'No valid bids']);
      exit;
    }
    die('No valid bids');
  }

  $db2 = db_conn();

  try {
    $db2->begin_transaction();
    $bidNow = $secureCtx['now_sql'];

    $rk2 = fetch_LL_code($db2, $uid);
    if ($rk2 === '')
      throw new Exception('Invalid user');

    // Compute total balance again for validation
    $available = compute_total_balance($db2, $rk2);

    if ($total > $available + 0.0001)
      throw new Exception('Insufficient balance');

    // ✅ Correct insert as per bid.sql (use open_pana + close_pana)
    $sessionType = 'Open';
    $ins = $db2->prepare("
      INSERT INTO bid(
        user_id, market, game_type,
        open_pana, close_pana,
        session_type, amount, bid_time
      )
      VALUES(?, ?, 'FULL_SANGAM', ?, ?, ?, ?, ?)
    ");

    foreach ($safe as $r) {
      $op = (string) $r['open_pana'];
      $cp = (string) $r['close_pana'];
      $amt = (float) $r['amount'];

      // user_id(s), market(s), open_pana(s), close_pana(s), session_type(s), amount(d)
      $ins->bind_param('sssssds', $rk2, $market, $op, $cp, $sessionType, $amt, $bidNow);
      $ins->execute();
    }
    $ins->close();

    // ✅ Deduct wallet only if bid insert success
    $wd = $db2->prepare("
      INSERT INTO withdrawal (user_id, amount, status, method, date_time)
      VALUES (?, ?, 'Done', 'game_bid', ?)
    ");
    $wd->bind_param('sds', $rk2, $total, $bidNow);
    $wd->execute();
    $wd->close();

    $db2->commit();
    $db2->close();

    if ($isAjax) {
      echo json_encode(['ok' => true]);
      exit;
    }
    header("Location: full_sangam.php?market=" . urlencode($market));
    exit;

  } catch (Throwable $e) {
    try {
      $db2->rollback();
    } catch (Throwable $x) {
    }
    try {
      $db2->close();
    } catch (Throwable $x) {
    }

    if ($isAjax) {
      echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
      exit;
    }
    die('Bid failed: ' . $e->getMessage());
  }
}
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
  <meta name="theme-color" content="#070b14">
  <title><?= htmlspecialchars($marketLabel) ?> - Full Sangam Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.lordicon.com/lusqsztk.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
      -webkit-tap-highlight-color: transparent;
    }

    html {
      background-color: #000000 !important;
      background: #000000 !important;
    }

    body {
      background-color: #000000 !important;
      background: #000000 !important;
      color: #333333;
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
      max-width: 600px;
      margin: 0 auto;
      padding: 0 0 20px;
    }

    .date-banner {
      background: #ffe4cb;
      padding: 10px 14px;
      text-align: center;
      font-weight: 800;
      font-size: 13px;
      color: #1e1e1e;
      margin-bottom: 12px;
      letter-spacing: 0.3px;
    }

    .input-form-card {
      background: linear-gradient(180deg, #ff8a8a 0%, #ffb3b3 35%, #ffffff 100%);
      margin: 0 0 16px;
      padding: 18px 16px 14px;
      box-shadow: none;
    }

    .form-row {
      display: grid;
      grid-template-columns: 130px 1fr;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
      position: relative;
    }

    .form-label {
      font-weight: 800;
      font-size: 14px;
      color: #1a202c;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .form-input {
      background: #f0f4f8;
      border: 1.5px solid transparent;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 14px;
      font-weight: 700;
      color: #2d3748;
      outline: none;
      width: 100%;
      transition: border-color 0.15s ease, background 0.15s ease;
    }

    .form-input:focus {
      background: #ffffff;
      border-color: #e53935;
    }

    .form-input::placeholder {
      color: #718096;
      font-weight: 500;
    }

    .add-more-btn {
      background: #ff3b30;
      color: #ffffff;
      border: none;
      border-radius: 10px;
      font-weight: 800;
      font-size: 14px;
      padding: 10px 16px;
      width: 100%;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(255, 59, 48, 0.35);
      transition: transform 0.15s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .add-more-btn:active {
      transform: scale(0.96);
    }

    .suggest-list {
      position: absolute;
      top: 100%;
      left: 142px;
      right: 0;
      background: #ffffff;
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
      z-index: 500;
      max-height: 180px;
      overflow-y: auto;
      display: none;
      margin-top: 4px;
    }

    .suggest-item {
      padding: 10px 14px;
      font-size: 14px;
      font-weight: 700;
      color: #2d3748;
      border-bottom: 1px solid #edf2f7;
      cursor: pointer;
    }

    .suggest-item:hover, .suggest-item:active {
      background: #fff5f5;
      color: #e53935;
    }

    .bids-table-container {
      margin: 0 14px 20px;
      background: #ffffff;
      border-radius: 12px;
      overflow: hidden;
    }

    .bids-table-header {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr 40px;
      padding: 12px 14px;
      background: #ffffff;
      border-bottom: 1px solid #edf2f7;
      font-weight: 800;
      font-size: 13px;
      color: #1a202c;
      text-align: center;
    }

    .bids-list {
      max-height: 250px;
      overflow-y: auto;
    }

    .bid-row {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr 40px;
      padding: 10px 14px;
      align-items: center;
      border-bottom: 1px solid #f7fafc;
      font-size: 13px;
      font-weight: 700;
      color: #2d3748;
      text-align: center;
    }

    .delete-btn {
      color: #e53935;
      background: transparent;
      border: none;
      font-size: 16px;
      cursor: pointer;
    }

    .bottom-bar {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background-color: #000000 !important;
      padding: 8px 14px;
      border-top: 1px solid #1a1a1a;
      box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.5);
      z-index: 1000;
      display: flex;
      justify-content: flex-end;
      align-items: center;
    }

    .submit-btn {
      background: #ff8a80;
      color: #ffffff;
      border: none;
      border-radius: 8px;
      font-weight: 800;
      font-size: 14px;
      padding: 7px 32px;
      cursor: pointer;
      box-shadow: 0 3px 10px rgba(255, 138, 128, 0.35);
      transition: background 0.2s ease, transform 0.15s ease;
    }

    .submit-btn.active {
      background: #ff3b30;
      box-shadow: 0 4px 14px rgba(255, 59, 48, 0.4);
    }

    .submit-btn:disabled {
      opacity: 0.65;
      cursor: not-allowed;
    }

    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(3px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 5000;
    }

    .modal-box {
      background: #ffffff;
      width: 80%;
      max-width: 290px;
      border-radius: 14px;
      padding: 20px 18px 16px;
      text-align: center;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    }

    .modal-msg {
      font-size: 14px;
      font-weight: 700;
      color: #1e1e1e;
      margin-bottom: 14px;
      line-height: 1.4;
    }

    .modal-btn {
      background: #8b0000;
      color: #ffffff;
      border: none;
      padding: 6px 26px;
      border-radius: 6px;
      font-weight: 700;
      font-size: 14px;
      cursor: pointer;
      display: inline-block;
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
  <header class="header-bar">
    <a href="javascript:history.back()" class="back-btn" title="Back">
      <i class="fa-solid fa-chevron-left"></i>
    </a>
    <div style="text-align: center; flex: 1; margin: 0 8px; overflow: hidden;">
      <div style="font-size: 15px; font-weight: 800; text-transform: uppercase; color: #ffffff; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($marketLabel) ?></div>
      <div style="font-size: 11px; font-weight: 700; color: #ffcdd2; text-transform: uppercase; margin-top: 2px; letter-spacing: 0.5px;"><?= __("Full Sangam") ?></div>
    </div>
    <a href="deposit.php" class="wallet-btn">
      <i class="fa-solid fa-wallet"></i>
      <span><?= number_format($totalBalance, 0) ?></span>
    </a>
  </header>

  <div class="container">
    <!-- Date & Status Banner -->
    <div class="date-banner">
      <?= date('d M Y - l') ?> - <?= $jodiDisabled ? 'BET CLOSED' : 'BET OPEN' ?>
    </div>

    <!-- Input Form Card -->
    <div class="input-form-card">
      <div class="form-row">
        <div class="form-label"><?= __("OPEN PANA") ?></div>
        <input type="tel" id="openPana" class="form-input" placeholder="<?= __("Enter Open Pana") ?>" maxlength="3" autocomplete="off">
        <div id="openSuggest" class="suggest-list"></div>
      </div>

      <div class="form-row">
        <div class="form-label"><?= __("CLOSE PANA") ?></div>
        <input type="tel" id="closePana" class="form-input" placeholder="<?= __("Enter Close Pana") ?>" maxlength="3" autocomplete="off">
        <div id="closeSuggest" class="suggest-list"></div>
      </div>

      <div class="form-row">
        <div class="form-label"><?= __("POINTS") ?></div>
        <input type="tel" id="pointsInput" class="form-input" placeholder="<?= __("Enter Points") ?>" maxlength="6">
      </div>

      <div class="form-row" style="margin-bottom: 0;">
        <div></div>
        <button type="button" id="addBtn" class="add-more-btn">
          Add More +
        </button>
      </div>
    </div>

    <!-- Added Bids Table -->
    <div class="bids-table-container">
      <div class="bids-table-header">
        <div>Open Pana</div>
        <div>Close Pana</div>
        <div>Points</div>
        <div></div>
      </div>
      <div class="bids-list" id="bidList">
        <!-- Added rows will appear here -->
      </div>
    </div>
  </div>

  <!-- Bottom Submit Bar -->
  <div class="bottom-bar">
    <button type="button" id="submitBtn" class="submit-btn"><?= __("Submit") ?></button>
  </div>

  <!-- Alert Modal -->
  <div class="modal-overlay" id="popup">
    <div class="modal-box">
      <div class="modal-msg" id="popupMsg"></div>
      <button class="modal-btn" type="button" onclick="document.getElementById('popup').style.display='none'"><?= __("OK") ?></button>
    </div>
  </div>

  <!-- Success Modal -->
  <style>
    .success-tick-wrapper {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: linear-gradient(135deg, #2e7d32, #4caf50);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 10px 25px rgba(76, 175, 80, 0.4), 0 0 0 10px rgba(76, 175, 80, 0.15);
      animation: popSuccess 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }
    @keyframes popSuccess {
      0% { transform: scale(0); opacity: 0; }
      80% { transform: scale(1.1); }
      100% { transform: scale(1); opacity: 1; }
    }
    .checkmark-svg {
      width: 55px;
      height: 55px;
      stroke: #ffffff;
      stroke-width: 4;
      stroke-linecap: round;
      stroke-linejoin: round;
      fill: none;
      stroke-dasharray: 100;
      stroke-dashoffset: 100;
      animation: drawCheck 0.6s ease-in-out 0.2s forwards;
    }
    @keyframes drawCheck {
      to { stroke-dashoffset: 0; }
    }
  </style>
  <div class="modal-overlay" id="successOverlay" style="background: rgba(255,255,255,0.96); flex-direction: column; z-index: 9999;">
    <div class="success-tick-wrapper">
      <svg class="checkmark-svg" viewBox="0 0 52 52">
        <path d="M14 27 l10 10 l20 -20" />
      </svg>
    </div>
    <div style="font-size:22px; font-weight:800; color:#1b5e20; margin-top:20px; letter-spacing:0.5px;"><?= __("Bid Submitted!") ?></div>
    <div style="font-size:14px; color:#555; margin-top:6px; font-weight:500;"><?= __("Your bid has been placed successfully.") ?></div>
  </div>

  <audio id="successSound" src="sound/done.mp3" preload="auto"></audio>

  <script>
    var marketClosed = <?= json_encode((bool) $jodiDisabled) ?>;
    var VALID_PANAS = <?= json_encode($validPanas) ?>;
    var minBid = <?= (int) $minBid ?>;
    var wallet = <?= json_encode((float) $totalBalance) ?>;

    var openPanaEl = document.getElementById('openPana');
    var closePanaEl = document.getElementById('closePana');
    var pointsEl = document.getElementById('pointsInput');
    var addBtn = document.getElementById('addBtn');
    var bidList = document.getElementById('bidList');
    var submitBtn = document.getElementById('submitBtn');
    var openSuggest = document.getElementById('openSuggest');
    var closeSuggest = document.getElementById('closeSuggest');
    var successSound = document.getElementById('successSound');

    var bids = [];

    function showMsg(msg) {
      document.getElementById('popupMsg').textContent = msg;
      document.getElementById('popup').style.display = 'flex';
    }

    [openPanaEl, closePanaEl, pointsEl].forEach(function(el) {
      el.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '');
      });
    });

    /* Suggestion Handler */
    function attachSuggest(input, list) {
      input.addEventListener('input', function () {
        var val = this.value;
        if (!val) return list.style.display = 'none';
        var matches = VALID_PANAS.filter(p => p.startsWith(val)).slice(0, 10);
        if (matches.length) {
          list.innerHTML = matches.map(p => `<div class="suggest-item">${p}</div>`).join('');
          list.style.display = 'block';
        } else {
          list.style.display = 'none';
        }
      });
      list.addEventListener('click', function (e) {
        if (e.target.classList.contains('suggest-item')) {
          input.value = e.target.innerText;
          list.style.display = 'none';
        }
      });
    }
    attachSuggest(openPanaEl, openSuggest);
    attachSuggest(closePanaEl, closeSuggest);

    document.addEventListener('click', function (e) {
      if (!e.target.closest('#openPana') && !e.target.closest('#openSuggest') &&
          !e.target.closest('#closePana') && !e.target.closest('#closeSuggest')) {
        openSuggest.style.display = 'none';
        closeSuggest.style.display = 'none';
      }
    });

    // Add Bid Logic
    addBtn.addEventListener('click', function() {
      if (marketClosed) return showMsg('Market is closed for the day.');

      var op = openPanaEl.value;
      var cp = closePanaEl.value;
      var pts = parseInt(pointsEl.value, 10) || 0;

      if (op.length !== 3 || !VALID_PANAS.includes(op)) return showMsg('Please enter a valid 3-digit Open Pana.');
      if (cp.length !== 3 || !VALID_PANAS.includes(cp)) return showMsg('Please enter a valid 3-digit Close Pana.');
      if (pts < minBid) return showMsg("Minimum bid points is ₹" + minBid);

      var currentTotal = bids.reduce((a, b) => a + b.amount, 0);
      if (currentTotal + pts > wallet) return showMsg('Insufficient wallet balance!');

      bids.push({ open_pana: op, close_pana: cp, amount: pts });
      renderList();

      openPanaEl.value = '';
      closePanaEl.value = '';
      pointsEl.value = '';
      openPanaEl.focus();
    });

    function renderList() {
      bidList.innerHTML = bids.map((b, i) => `
        <div class="bid-row">
          <div>${b.open_pana}</div>
          <div>${b.close_pana}</div>
          <div>${b.amount}</div>
          <div><button type="button" class="delete-btn" onclick="removeBid(${i})"><i class="fa-solid fa-trash"></i></button></div>
        </div>
      `).join('');

      if (bids.length > 0) {
        submitBtn.classList.add('active');
      } else {
        submitBtn.classList.remove('active');
      }
    }

    window.removeBid = function (index) {
      bids.splice(index, 1);
      renderList();
    };

    // Submit Logic
    submitBtn.addEventListener('click', async function() {
      if (marketClosed) return showMsg('Market is closed for the day.');
      if (!bids.length) return showMsg('Please add at least one bid to the list.');

      var totalAmt = bids.reduce((a, b) => a + b.amount, 0);
      if (totalAmt > wallet) return showMsg('Insufficient wallet balance!');

      submitBtn.disabled = true;
      var ogHtml = submitBtn.innerHTML;
      submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing...';

      try {
        var fd = new FormData();
        fd.append('bids', JSON.stringify(bids));
        fd.append('ajax', '1');

        var res = await fetch('', { method: 'POST', body: fd });
        var data = await res.json();

        if (data.ok) {
          if (successSound) {
            successSound.currentTime = 0;
            successSound.play().catch(function() { });
          }
          document.getElementById('successOverlay').style.display = 'flex';
          setTimeout(function() { return location.reload(); }, 1500);
        } else {
          showMsg(data.msg || 'Failed');
          submitBtn.disabled = false;
          submitBtn.innerHTML = ogHtml;
        }
      } catch (e) {
        showMsg('Error submitting bid');
        submitBtn.disabled = false;
        submitBtn.innerHTML = ogHtml;
      }
    });
  </script>
</body>

</html>


