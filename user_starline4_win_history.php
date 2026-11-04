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

$uid = (int)$_SESSION['user']['id'];
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
    $searchId = '%' . preg_replace('/^[A-Z]+/', '', $code);
    $stmt = $db->prepare("SELECT market, bet_type AS game_type, session_type, single_digit, single_pana, double_pana, triple_pana, amount, wining_balance, win_time AS created_at FROM wining4 WHERE user_id LIKE ? AND win_time BETWEEN ? AND ? AND status='win' ORDER BY win_time DESC");
    $stmt->bind_param('sss', $searchId, $from, $to);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
  <title>Starline 4 Win History</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root {
      --primary: #000000;
      --accent: #e53935;
      --win: #16a34a;
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
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
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
      color: var(--win);
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

    .win-box {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
      padding: 12px 14px;
      border: 1px solid #eee;
      border-left: 4px solid var(--win);
      margin-bottom: 10px;
    }

    .row {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .win-box h2 {
      font-size: 13px;
      font-weight: 800;
      text-transform: uppercase;
      color: #333;
    }

    .chip {
      font-size: 9px;
      font-weight: 900;
      color: #8b0000;
      background: rgba(139, 0, 0, 0.08);
      padding: 2px 8px;
      border-radius: 99px;
      text-transform: uppercase;
    }

    .val {
      font-size: 18px;
      font-weight: 900;
      color: var(--win);
    }

    .sub {
      font-size: 11px;
      font-weight: 700;
      color: #7f8a9b;
    }

    .tag {
      font-size: 10px;
      font-weight: 800;
      color: #666;
      background: #f0f2f5;
      padding: 3px 8px;
      border-radius: 99px;
      text-transform: uppercase;
    }

    .footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
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
        Starline Wins
        <img src="icons/gold_coin.svg" alt="coin" style="width: 18px; height: 18px; vertical-align: middle; margin-top: -2px;">
      </div>
    </div>
    <a href="deposit.php" class="wallet">
      <i class="fa-solid fa-wallet"></i>
      <span><?= number_format($totalBalance, 0) ?></span>
    </a>
  </header>
  <div class="content">
    <div class="summary">
      <div class="summary-total">Total Winnings: <strong id="totalWin">&#8377;0</strong></div>
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
    const TODAY="<?= $today ?>", WEEK="<?= $lastweek ?>", list=document.getElementById('list'), loader=document.getElementById('loader'), totalWin=document.getElementById('totalWin'), pager=document.getElementById('pager'), pageInd=document.getElementById('pageInd'), btnToday=document.getElementById('btnToday'), btnWeek=document.getElementById('btnWeek'), prev=document.getElementById('prev'), next=document.getElementById('next');
    let data=[],page=0,per=5;
    function numberOf(w){return w.single_digit||w.single_pana||w.double_pana||w.triple_pana||'-'}
    function render(){list.innerHTML='';if(!data.length){list.innerHTML='<div style="text-align:center;padding:38px;color:#999">No records found.</div>';totalWin.innerHTML='&#8377;0';pager.style.display='none';return}let sum=data.reduce((s,w)=>s+(+w.wining_balance||0),0);totalWin.innerHTML='&#8377;'+Math.floor(sum).toLocaleString('en-IN');let s=page*per,e=Math.min(s+per,data.length);for(let i=s;i<e;i++){let w=data[i],dt=new Date((w.created_at||'').replace(' ','T'));let ds=isNaN(dt)?'':dt.toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'});let ts=isNaN(dt)?'':dt.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',hour12:true});list.insertAdjacentHTML('beforeend',`<div class="win-box"><div class="row"><h2>${w.market}</h2><div class="val">&#8377;${(+w.wining_balance||0).toLocaleString('en-IN')}</div></div><div class="row" style="margin-top:6px"><div style="display:flex;align-items:center;gap:12px"><div style="font-size:14px;font-weight:900;background:rgba(139,0,0,0.08);color:#8b0000;padding:2px 10px;border-radius:6px">No: ${numberOf(w)}</div><div class="sub">Bid: &#8377;${(+w.amount||0).toLocaleString('en-IN')}</div></div><div class="tag">${(w.game_type||'WIN').replace(/_/g,' ')}</div></div><div class="footer"><div><span><i class="far fa-calendar-alt"></i> ${ds}</span><span style="margin-left:10px"><i class="far fa-clock"></i> ${ts}</span></div><span class="chip">${w.session_type||'Open'}</span></div></div>`)}let pages=Math.ceil(data.length/per);pageInd.textContent=(page+1)+' / '+pages;prev.disabled=page===0;next.disabled=e>=data.length;pager.style.display=pages>1?'flex':'none'}
    function load(from,to){loader.style.display='block';list.innerHTML='';fetch(`user_starline4_win_history.php?ajax=1&from=${from}&to=${to}`).then(r=>r.json()).then(d=>{data=Array.isArray(d)?d:[];page=0;loader.style.display='none';render()}).catch(()=>loader.style.display='none')}
    btnToday.onclick=()=>{btnToday.classList.add('active');btnWeek.classList.remove('active');load(TODAY,TODAY)};btnWeek.onclick=()=>{btnWeek.classList.add('active');btnToday.classList.remove('active');load(WEEK,TODAY)};prev.onclick=()=>{if(page){page--;render()}};next.onclick=()=>{if((page+1)*per<data.length){page++;render()}};load(TODAY,TODAY);
  </script>
</body>
</html>
