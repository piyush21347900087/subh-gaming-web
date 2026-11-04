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
$market = substr($market, 0, 20);
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
function compute_balances(mysqli $db, string $LLCode): array
{
    $tot = get_total_balance($db, $LLCode);
    return [$tot, 0.0];
}

/* ---------- Balance ---------- */
$db = db_conn();
$LLCode = fetch_LL_code($db, $uid);
if ($LLCode === '') {
  die('Invalid user');
}
[$walletBalance, $winBalance] = compute_balances($db, $LLCode);
// $db->close();
$totalBalance = $walletBalance + $winBalance;
$minBid = getMinBid($db);

/* ---------- Default session from timing ---------- */
$defaultSession = (!$openDisabled && !$pageDisabled) ? 'Open' : ((!$closeDisabled && !$pageDisabled) ? 'Close' : 'Open');

/* ---------- Market status text ---------- */
$marketClosed = ($openDisabled && $closeDisabled) || $pageDisabled;
$marketStatusText = $marketClosed ? 'Market Time Close' : 'Market Open';
$marketStatusColor = $marketClosed ? 'red' : '#0a8f08';

/* ---------- Double Pana List ---------- */
$pana_numbers = array_values(array_unique(array_map('strval', [
  '118',
  '226',
  '244',
  '299',
  '334',
  '488',
  '550',
  '668',
  '677',
  '100',
  '119',
  '155',
  '227',
  '335',
  '344',
  '399',
  '588',
  '669',
  '110',
  '200',
  '228',
  '255',
  '336',
  '499',
  '660',
  '688',
  '778',
  '166',
  '229',
  '300',
  '337',
  '355',
  '445',
  '599',
  '779',
  '788',
  '112',
  '220',
  '266',
  '338',
  '400',
  '446',
  '455',
  '699',
  '770',
  '113',
  '122',
  '177',
  '339',
  '366',
  '447',
  '500',
  '799',
  '889',
  '555',
  '600',
  '114',
  '277',
  '330',
  '448',
  '466',
  '556',
  '880',
  '899',
  '115',
  '133',
  '188',
  '223',
  '377',
  '449',
  '557',
  '566',
  '700',
  '116',
  '224',
  '233',
  '288',
  '440',
  '477',
  '558',
  '800',
  '990',
  '117',
  '144',
  '199',
  '225',
  '388',
  '559',
  '577',
  '667',
  '900'
])));
sort($pana_numbers);

/* ---------- Submit (server validation) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bids'])) {

  // ✅ AJAX mode return JSON
  $isAjax = (isset($_POST['ajax']) && $_POST['ajax'] == '1');
  if ($isAjax)
    header('Content-Type: application/json; charset=utf-8');

  if ($marketClosed) {
    if ($isAjax) {
      echo json_encode(['ok' => false, 'msg' => 'Bidding closed']);
      exit;
    }
    die('Bidding closed');
  }

  $bids = json_decode((string) $_POST['bids'], true);
  if (!is_array($bids) || !count($bids)) {
    if ($isAjax) {
      echo json_encode(['ok' => false, 'msg' => 'Invalid payload']);
      exit;
    }
    die('Invalid payload');
  }

  $validSet = array_flip($pana_numbers);
  $clean = [];
  $total = 0.0;
  $secureCtx = vardhan_secure_bid_context($db, $market);
  if (empty($secureCtx['ok'])) {
    if ($isAjax) {
      echo json_encode(['ok' => false, 'msg' => $secureCtx['message'] ?? 'Bidding closed']);
      exit;
    }
    die($secureCtx['message'] ?? 'Bidding closed');
  }

  foreach ($bids as $r) {
    $sess = (string) ($r['session'] ?? 'Open');
    $sessionCheck = vardhan_secure_session($secureCtx, $sess);
    if (empty($sessionCheck['ok'])) {
      if ($isAjax) {
        echo json_encode(['ok' => false, 'msg' => $sessionCheck['message'] ?? 'Bidding closed']);
        exit;
      }
      die($sessionCheck['message'] ?? 'Bidding closed');
    }
    $sess = $sessionCheck['session'];

    $num = preg_replace('/[^0-9]/', '', (string) ($r['number'] ?? ''));
    $amt = (int) ($r['amount'] ?? 0);

    if (strlen($num) !== 3)
      continue;
    if (!isset($validSet[$num]))
      continue;
    if ($amt < $minBid)
      continue;

    $clean[] = ['session' => $sess, 'number' => $num, 'amount' => $amt];
    $total += $amt;
  }

  if (!count($clean) || $total <= 0) {
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

    [$w2, $win2] = compute_balances($db2, $rk2);
    $available = $w2 + $win2;

    if ($total > $available + 0.0001)
      throw new Exception('Insufficient balance');

    // ✅ Insert bid row for each pana (double_pana column)
    $ins = $db2->prepare("
      INSERT INTO bid(
        user_id, market, session_type,
        single_digit, jodi_digit, single_pana, double_pana, triple_pana,
        amount, bid_time
      )
      VALUES(?, ?, ?, NULL, NULL, NULL, ?, NULL, ?, ?)
    ");

    foreach ($clean as $row) {
      $sess = $row['session'];            // Open/Close
      $pana = $row['number'];             // 3 digit
      $amt = (float) $row['amount'];

      // bind: user_id(s), market(s), session_type(s), double_pana(s), amount(d)
      $ins->bind_param('ssssds', $rk2, $market, $sess, $pana, $amt, $bidNow);
      $ins->execute();
    }
    $ins->close();

    // ✅ wallet deduct only after insert success
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
    header("Location: double_pana.php?market=" . urlencode($market));
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
  <title><?= htmlspecialchars($marketLabel) ?> - Double Pana Dashboard</title>
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

    /* Header Bar */
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

    /* Container */
    .container {
      max-width: 600px;
      margin: 0 auto;
      padding: 0 0 20px;
    }

    /* Date & Bet Banner */
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

    /* Select Game Type Card */
    .select-game-card {
      background: #ffffff;
      border: 1.5px solid #c62828;
      border-radius: 12px;
      padding: 10px 16px;
      margin: 0 14px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .select-game-title {
      font-weight: 800;
      font-size: 13px;
      color: #1a202c;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .radio-group {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .radio-label {
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: 800;
      font-size: 14px;
      color: #8b0000;
      cursor: pointer;
    }

    .radio-label input[type="radio"] {
      width: 18px;
      height: 18px;
      accent-color: #8b0000;
      cursor: pointer;
    }

    /* Input Form Card */
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

    /* Bids List Table */
    .bids-table-container {
      margin: 0 14px 20px;
      background: #ffffff;
      border-radius: 12px;
      overflow: hidden;
    }

    .bids-table-header {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr 40px;
      padding: 12px 16px;
      background: #ffffff;
      border-bottom: 1px solid #edf2f7;
      font-weight: 800;
      font-size: 14px;
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
      padding: 10px 16px;
      align-items: center;
      border-bottom: 1px solid #f7fafc;
      font-size: 14px;
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

    /* Bottom Fixed Submit Bar */
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

    /* Modal Overlay */
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
      <div style="font-size: 11px; font-weight: 700; color: #ffcdd2; text-transform: uppercase; margin-top: 2px; letter-spacing: 0.5px;"><?= __("Double Pana") ?></div>
    </div>
    <a href="deposit.php" class="wallet-btn">
      <i class="fa-solid fa-wallet"></i>
      <span><?= number_format($totalBalance, 0) ?></span>
    </a>
  </header>

  <div class="container">
    <!-- Date & Status Banner -->
    <div class="date-banner">
      <?= date('d M Y - l') ?> - <?= ($openDisabled && $closeDisabled) ? 'BET CLOSED' : 'BET OPEN' ?>
    </div>

    <!-- Select Game Type -->
    <div class="select-game-card">
      <div class="select-game-title">SELECT GAME TYPE:</div>
      <div class="radio-group">
        <?php if (!$openDisabled): ?>
          <label class="radio-label">
            <input type="radio" name="gameSession" value="Open" checked> OPEN
          </label>
        <?php endif; ?>
        <?php if (!$closeDisabled): ?>
          <label class="radio-label">
            <input type="radio" name="gameSession" value="Close" <?= $openDisabled ? 'checked' : '' ?>> CLOSE
          </label>
        <?php endif; ?>
        <?php if ($openDisabled && $closeDisabled): ?>
          <span style="color:#c62828; font-weight:800; font-size:13px;">CLOSED</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Input Form Card -->
    <div class="input-form-card">
      <div class="form-row">
        <div class="form-label">DOUBLE PANNA</div>
        <input type="tel" id="pannaInput" class="form-input" placeholder="Enter Panna" maxlength="3">
      </div>
      <div class="form-row">
        <div class="form-label">POINTS</div>
        <input type="tel" id="pointsInput" class="form-input" placeholder="Enter Points" maxlength="6">
      </div>
      <div class="form-row" style="margin-bottom: 0;">
        <div></div>
        <button type="button" id="addMoreBtn" class="add-more-btn">
          Add More +
        </button>
      </div>
    </div>

    <!-- Added Bids Table -->
    <div class="bids-table-container">
      <div class="bids-table-header">
        <div>Digit</div>
        <div>Points</div>
        <div>Game Type</div>
        <div></div>
      </div>
      <div class="bids-list" id="bidsList">
        <!-- Added rows will appear here -->
      </div>
    </div>
  </div>

  <!-- Bottom Submit Bar -->
  <div class="bottom-bar">
    <button type="button" id="submitBtn" class="submit-btn">Submit</button>
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
    var wallet = <?= json_encode((float) $totalBalance) ?>;
    var marketClosed = <?= json_encode($marketClosed) ?>;
    var minBid = <?= (int) $minBid ?>;
    var VALID_PANAS = new Set(<?= json_encode(array_values($pana_numbers)) ?>);

    var bids = [];
    var pannaInput = document.getElementById('pannaInput');
    var pointsInput = document.getElementById('pointsInput');
    var addMoreBtn = document.getElementById('addMoreBtn');
    var bidsList = document.getElementById('bidsList');
    var submitBtn = document.getElementById('submitBtn');
    var successSound = document.getElementById('successSound');

    function showMsg(msg) {
      document.getElementById('popupMsg').textContent = msg;
      document.getElementById('popup').style.display = 'flex';
    }

    pannaInput.addEventListener('input', function() {
      this.value = this.value.replace(/[^0-9]/g, '');
    });

    pointsInput.addEventListener('input', function() {
      this.value = this.value.replace(/[^0-9]/g, '');
    });

    function getSelectedSession() {
      var radios = document.getElementsByName('gameSession');
      for (var i = 0; i < radios.length; i++) {
        if (radios[i].checked) return radios[i].value;
      }
      return 'Open';
    }

    function renderBids() {
      bidsList.innerHTML = '';
      bids.forEach(function(item, idx) {
        var row = document.createElement('div');
        row.className = 'bid-row';
        row.innerHTML = '<div>' + item.number + '</div>' +
                        '<div>' + item.amount + '</div>' +
                        '<div>' + item.session + '</div>' +
                        '<div><button type="button" class="delete-btn" onclick="removeBid(' + idx + ')"><i class="fa-solid fa-trash"></i></button></div>';
        bidsList.appendChild(row);
      });

      if (bids.length > 0) {
        submitBtn.classList.add('active');
      } else {
        submitBtn.classList.remove('active');
      }
    }

    function removeBid(idx) {
      bids.splice(idx, 1);
      renderBids();
    }

    addMoreBtn.addEventListener('click', function() {
      if (marketClosed) return showMsg('Market is closed for the day.');

      var pannaVal = pannaInput.value.trim();
      var pointsVal = parseInt(pointsInput.value.trim(), 10) || 0;

      if (pannaVal === '' || pannaVal.length !== 3) {
        return showMsg('Please enter a valid 3-digit Double Pana.');
      }

      if (!VALID_PANAS.has(pannaVal)) {
        return showMsg('Invalid Double Pana number (' + pannaVal + '). Please check and enter a valid Double Pana.');
      }

      if (pointsVal < minBid) {
        return showMsg('Minimum bid amount is ₹' + minBid + '.');
      }

      var currentTotal = bids.reduce(function(acc, item) { return acc + item.amount; }, 0);
      if (currentTotal + pointsVal > wallet) {
        return showMsg('Insufficient wallet balance!');
      }

      var sess = getSelectedSession();

      bids.push({
        session: sess,
        number: pannaVal,
        amount: pointsVal
      });

      pannaInput.value = '';
      pointsInput.value = '';
      pannaInput.focus();

      renderBids();
    });

    submitBtn.addEventListener('click', async function() {
      if (marketClosed) return showMsg('Market is closed for the day.');
      if (bids.length === 0) return showMsg('Please add at least one bid to the list.');

      var totalAmt = bids.reduce(function(acc, item) { return acc + item.amount; }, 0);
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



