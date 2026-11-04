<?php
session_start();
require_once 'includes/db.php';
require_once 'process_starline4_wins.php';
require_once 'includes/user_ping.php';
date_default_timezone_set('Asia/Kolkata');

if (empty($_SESSION['user']['id'])) {
  header('Location: user_login.php');
  exit;
}

$uid = (int) $_SESSION['user']['id'];
$db = db();
starline4_ensure_schema($db);

$stmt = $db->prepare("SELECT user_id FROM users WHERE id=? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$code = normalize_user_id($user['user_id'] ?? '');
$totalBalance = get_total_balance($db, $code);
$today = date('Y-m-d');
$lastweek = date('Y-m-d', strtotime('-6 days'));

if (isset($_GET['ajax'])) {
  $from = trim($_GET['from'] ?? $today) . ' 00:00:00';
  $to = trim($_GET['to'] ?? $today) . ' 23:59:59';
  $data = [];
  $stmt = $db->prepare("SELECT market, close_time, 'Open' AS session_type, game_type, single_digit, single_pana, double_pana, triple_pana, amount, DATE_FORMAT(bid_time, '%d %b %Y') AS bid_date, DATE_FORMAT(bid_time, '%h:%i %p') AS bid_clock FROM bid4 WHERE user_id=? AND bid_time BETWEEN ? AND ? ORDER BY bid_time DESC");
  $stmt->bind_param('sss', $code, $from, $to);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc())
    $data[] = $row;
  $stmt->close();
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
  <meta name="theme-color" content="#000000">
  <title>Starline 4 Bid History</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root {
      --primary: #000000;
      --accent: #e53935;
      --bg: #000000;
      --shadow: 0 2px 10px rgba(0, 0, 0, .5);
      accent-color: #e53935;
      color-scheme: dark;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
      -webkit-tap-highlight-color: transparent;
    }

    body {
      background: #000000 !important;
      padding-bottom: 86px;
      color: #ffffff;
    }

    header {
      background-color: #000000 !important;
      background-image: none !important;
      padding-top: calc(env(safe-area-inset-top, 0px) + 14px);
      padding-bottom: 12px;
      padding-left: 14px;
      padding-right: 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0, 0, 0, .2);
      border-bottom: 1px solid #1a1a1a !important;
      color: #fff;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .back {
      width: 38px;
      height: 38px;
      background: #e53935;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      text-decoration: none;
      border: none;
      flex-shrink: 0;
      font-size: 15px;
    }

    .logo {
      color: #ffffff;
      font-size: 17px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .wallet {
      display: flex;
      align-items: center;
      gap: 6px;
      background: #e53935;
      border-radius: 20px;
      padding: 6px 12px;
      font-weight: 800;
      font-size: 13px;
      color: #fff;
      text-decoration: none;
      box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }

    .content {
      max-width: 500px;
      margin: 0 auto;
      padding: 0 14px 14px;
    }

    .summary {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 4px 14px rgba(0,0,0,0.05);
      overflow: hidden;
      margin-top: 14px;
      margin-bottom: 15px;
      border: 1px solid #eee;
      padding: 12px 14px;
    }

    .summary-total {
      padding: 5px 12px;
      text-align: center;
      font-weight: 800;
      color: #444;
    }

    .summary-total strong {
      color: var(--primary);
      font-size: 21px;
      margin-left: 6px;
    }

    .filters {
      display: flex;
      gap: 10px;
      padding: 8px 0 0 0;
    }

    .filter {
      flex: 1;
      height: 38px;
      border-radius: 999px;
      border: 1px solid #eee;
      background: #fff;
      font-weight: 800;
      color: #555;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .filter.active {
      background: linear-gradient(135deg, #8b0000 0%, #c0392b 100%);
      color: #fff;
      border-color: #8b0000;
      box-shadow: 0 4px 10px rgba(139, 0, 0, 0.25);
    }

    .bid-box {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
      padding: 12px 14px;
      border: 1px solid #eee;
      border-left: 4px solid var(--primary);
      margin-bottom: 10px;
    }

    .row {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .bid-box h2 {
      font-size: 13px;
      font-weight: 900;
      text-transform: uppercase;
      color: #333;
    }

    .chip {
      font-size: 9px;
      font-weight: 900;
      color: var(--primary);
      background: rgba(139, 0, 0, 0.08);
      padding: 2px 8px;
      border-radius: 99px;
      text-transform: uppercase;
    }

    .val {
      font-size: 17px;
      font-weight: 900;
      color: var(--primary);
      margin-top: 2px;
    }

    .type {
      font-size: 10px;
      font-weight: 800;
      color: #7f8a9b;
      text-transform: uppercase;
    }

    .amt {
      font-size: 17px;
      font-weight: 900;
      color: #222;
    }

    .footer {
      display: flex;
      justify-content: space-between;
      font-size: 10px;
      color: #a0a8b5;
      font-weight: 700;
      border-top: 1px solid #f5f5f5;
      padding-top: 8px;
      margin-top: 8px;
    }

    .pager {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 18px;
      margin: 18px 0;
    }

    .pager button {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: 1px solid #eee;
      background: #fff;
      color: #8b0000;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
  <header>
    <div class="header-left">
      <a href="starline4.php" class="back"><i class="fa-solid fa-chevron-left"></i></a>
      <div class="logo">
        <img src="icons/gold_coin.svg" alt="coin" style="width: 18px; height: 18px; vertical-align: middle; margin-top: -2px;">
        Starline Bids
        <img src="icons/gold_coin.svg" alt="coin" style="width: 18px; height: 18px; vertical-align: middle; margin-top: -2px;">
      </div>
    </div>
    <a href="deposit.php" class="wallet">
      <i class="fa-solid fa-wallet"></i>
      <span id="walletVal"><?= number_format($totalBalance, 0) ?></span>
    </a>
  </header>
  <div class="content">
    <div class="summary">
      <div class="summary-total">Total Bid: <strong id="totalBid">&#8377;0</strong></div>
      <div class="filters">
        <button id="btnToday" class="filter active">Today</button>
        <button id="btnWeek" class="filter">One Week</button>
      </div>
    </div>
    <div id="loader" style="text-align:center;padding:20px;display:none">
      <i class="fas fa-spinner fa-spin" style="font-size:24px;color:var(--primary)"></i>
    </div>
    <div id="list"></div>
    <div class="pager" id="pager" style="display:none">
      <button id="prev"><i class="fas fa-chevron-left"></i></button>
      <div id="pageInd">1 / 1</div>
      <button id="next"><i class="fas fa-chevron-right"></i></button>
    </div>
  </div>
  <script>
    const TODAY = "<?= $today ?>", WEEK = "<?= $lastweek ?>", list = document.getElementById('list'), loader = document.getElementById('loader'), totalBid = document.getElementById('totalBid'), pager = document.getElementById('pager'), pageInd = document.getElementById('pageInd'), btnToday = document.getElementById('btnToday'), btnWeek = document.getElementById('btnWeek'), prev = document.getElementById('prev'), next = document.getElementById('next');
    let data = [], page = 0, per = 4;
    function info(b) { let val = b.single_digit || b.single_pana || b.double_pana || b.triple_pana || '-'; return { label: (b.game_type || 'BID').replace(/_/g, ' '), val } }
    function render() { list.innerHTML = ''; if (!data.length) { list.innerHTML = '<div style="text-align:center;padding:38px;color:#999">No records found.</div>'; totalBid.innerHTML = '&#8377;0'; pager.style.display = 'none'; return } let sum = data.reduce((s, b) => s + (+b.amount || 0), 0); totalBid.innerHTML = '&#8377;' + Math.floor(sum).toLocaleString('en-IN'); let s = page * per, e = Math.min(s + per, data.length); for (let i = s; i < e; i++) { let b = data[i], x = info(b); list.insertAdjacentHTML('beforeend', `<div class="bid-box"><div class="row"><h2>${b.market}</h2><span class="chip">${b.session_type}</span></div><div class="row" style="margin-top:3px"><div><div class="type">${x.label}</div><div class="val">${x.val}</div></div><div class="amt">&#8377;${(+b.amount || 0).toLocaleString('en-IN')}</div></div><div class="footer"><span><i class="far fa-calendar-alt"></i> ${b.bid_date}</span><span><i class="far fa-clock"></i> ${b.bid_clock}</span></div></div>`) } let pages = Math.ceil(data.length / per); pageInd.textContent = (page + 1) + ' / ' + pages; prev.disabled = page === 0; next.disabled = e >= data.length; pager.style.display = pages > 1 ? 'flex' : 'none' }
    function load(from, to) { loader.style.display = 'block'; list.innerHTML = ''; fetch(`user_starline4_bid_history.php?ajax=1&from=${from}&to=${to}`).then(r => r.json()).then(d => { data = Array.isArray(d) ? d : []; page = 0; loader.style.display = 'none'; render() }).catch(() => loader.style.display = 'none') }
    btnToday.onclick = () => { btnToday.classList.add('active'); btnWeek.classList.remove('active'); load(TODAY, TODAY) }; btnWeek.onclick = () => { btnWeek.classList.add('active'); btnToday.classList.remove('active'); load(WEEK, TODAY) }; prev.onclick = () => { if (page) { page--; render() } }; next.onclick = () => { if ((page + 1) * per < data.length) { page++; render() } }; load(TODAY, TODAY);
  </script>
</body>

</html>