<?php
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Kolkata');

require_once 'includes/db.php';
require_once 'process_starline4_wins.php';
require_once 'includes/secure_bid_time.php';
require_once 'includes/user_ping.php';
if (file_exists('includes/lang.php')) {
    require_once 'includes/lang.php';
}
if (!function_exists('__')) {
    function __($str) { return $str; }
}

if (empty($_SESSION['user']['id'])) {
    header('Location: user_login.php');
    exit;
}

$gameType = $STARLINE4_GAME_TYPE ?? 'SINGLE_DIGIT';
$gameLabel = $STARLINE4_GAME_LABEL ?? 'Single Digit';
$gameColumn = $STARLINE4_GAME_COLUMN ?? 'single_digit';
$isBulk = !empty($STARLINE4_BULK);
$allowedNumbers = array_values(array_map('strval', $STARLINE4_NUMBERS ?? range(0, 9)));
$validColumns = ['single_digit', 'single_pana', 'double_pana', 'triple_pana'];
if (!in_array($gameColumn, $validColumns, true)) die('Invalid game setup');

$db = db();
$db->set_charset('utf8mb4');
starline4_ensure_schema($db);
starline4_seed_default_markets($db);

$market = strtoupper(trim($_GET['market'] ?? ''));
if ($market === '') {
    $firstM = $db->query("SELECT market_name FROM markets4 WHERE is_published=1 ORDER BY sort_order ASC, close_time ASC, id ASC LIMIT 1");
    if ($firstM && $firstRow = $firstM->fetch_assoc()) {
        $market = strtoupper(trim($firstRow['market_name']));
    } else {
        $market = 'TIME BAZAR';
    }
}
$marketLabel = ucwords(strtolower($market));

$uid = (int)$_SESSION['user']['id'];
$stmt = $db->prepare("SELECT user_id FROM users WHERE id=? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$userCode = normalize_user_id($userRow['user_id'] ?? '');
if ($userCode === '') die('User code missing');

$stmt = $db->prepare("SELECT * FROM markets4 WHERE UPPER(market_name)=UPPER(?) AND is_published=1 LIMIT 1");
$stmt->bind_param('s', $market);
$stmt->execute();
$marketRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$closeLabel = $marketRow ? date('h:i A', strtotime($marketRow['close_time'] ?? '00:00:00')) : '--';
$pageState = $marketRow ? starline4_market_state($db, $market, new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'))) : ['ok' => false, 'page_disabled' => true, 'message' => 'Invalid market'];
$pageClosed = empty($pageState['ok']) || !empty($pageState['page_disabled']);
$totalBalance = get_total_balance($db, $userCode);
$minBid = getMinBid($db);

$nonceKey = 'bid_nonce4_' . strtolower($gameType) . ($isBulk ? '_bulk' : '');
if (empty($_SESSION[$nonceKey])) $_SESSION[$nonceKey] = bin2hex(random_bytes(16));
$nonce = $_SESSION[$nonceKey];

function starline4_clean_number(string $value, string $gameType): string {
    $value = preg_replace('/\D+/', '', $value);
    if ($value === '') return '';
    if ($gameType === 'SINGLE_DIGIT') return substr($value, 0, 1);
    return str_pad(substr($value, 0, 3), 3, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bids'])) {
    header('Content-Type: application/json; charset=utf-8');
    $clientNonce = (string)($_POST['nonce'] ?? '');
    if ($clientNonce === '' || !hash_equals($_SESSION[$nonceKey] ?? '', $clientNonce)) {
        echo json_encode(['ok' => false, 'message' => 'Duplicate / invalid request']);
        exit;
    }

    $bids = json_decode((string)$_POST['bids'], true);
    if (!is_array($bids) || empty($bids)) {
        echo json_encode(['ok' => false, 'message' => 'Please add at least one bid']);
        exit;
    }

    $secureCtx = starline4_secure_bid_context($db, $market);
    if (empty($secureCtx['ok'])) {
        echo json_encode(['ok' => false, 'message' => $secureCtx['message'] ?? 'Bidding closed']);
        exit;
    }

    $allowedMap = array_flip($allowedNumbers);
    $payload = [];
    $total = 0.0;
    foreach ($bids as $row) {
        $num = starline4_clean_number((string)($row['number'] ?? ''), $gameType);
        $amount = (float)($row['amount'] ?? 0);
        if (!isset($allowedMap[$num])) {
            echo json_encode(['ok' => false, 'message' => 'Invalid number']);
            exit;
        }
        if ($amount < $minBid) {
            echo json_encode(['ok' => false, 'message' => 'Minimum bid Rs. ' . $minBid]);
            exit;
        }
        $payload[] = ['number' => $num, 'amount' => $amount];
        $total += $amount;
    }

    if ($total > get_total_balance($db, $userCode)) {
        echo json_encode(['ok' => false, 'message' => 'Insufficient balance']);
        exit;
    }

    try {
        $db->begin_transaction();
        $closeTime = $marketRow ? date('h:i A', strtotime($marketRow['close_time'] ?? '00:00:00')) : '';
        $bidNow = $secureCtx['now_sql'] ?? date('Y-m-d H:i:s');

        $sql = "INSERT INTO bid4 (user_id, market, close_time, game_type, `$gameColumn`, amount, bid_time) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $ins = $db->prepare($sql);
        if (!$ins) throw new Exception('Bid prepare failed: ' . $db->error);

        $numBind = '';
        $amtBind = 0.0;
        $ins->bind_param('sssssds', $userCode, $market, $closeTime, $gameType, $numBind, $amtBind, $bidNow);

        foreach ($payload as $bid) {
            $numBind = (string)$bid['number'];
            $amtBind = (float)$bid['amount'];
            if (!$ins->execute()) {
                throw new Exception('Bid insert error: ' . $ins->error);
            }
        }
        $ins->close();

        $wd = $db->prepare("INSERT INTO withdrawal (user_id, amount, status, method, date_time) VALUES (?, ?, 'Done', 'game_bid', ?)");
        if (!$wd) throw new Exception('Withdrawal prepare failed: ' . $db->error);
        $wd->bind_param('sds', $userCode, $total, $bidNow);
        if (!$wd->execute()) {
            throw new Exception('Wallet deduction failed: ' . $wd->error);
        }
        $wd->close();

        $db->commit();
        $_SESSION[$nonceKey] = bin2hex(random_bytes(16));
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignore) {}
        echo json_encode(['ok' => false, 'message' => $e->getMessage() ?: 'Bid insert failed. Please try again.']);
    }
    exit;
}

$subtitle = $gameLabel . ($isBulk ? ' Bulk' : '');
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <meta name="theme-color" content="#000000">
  <title><?= htmlspecialchars($subtitle) ?> - <?= htmlspecialchars($marketLabel) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { box-sizing:border-box; margin:0; padding:0; font-family:'Poppins', sans-serif; -webkit-tap-highlight-color: transparent; }
    body { background: #000000 !important;
      min-height: 100vh;
      padding-bottom: 96px;
      color: #ffffff;
    }
    .header-bar {
      min-height: 70px;
      height: auto;
      padding-top: calc(env(safe-area-inset-top, 0px) + 14px);
      padding-bottom: 12px;
      padding-left: 14px;
      padding-right: 14px;
      background-color: #000000 !important; background-image: none !important;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
      border-bottom: none !important;
    }
    .round-btn, .wallet {
      min-width: 38px;
      height: 38px;
      border-radius: 8px;
      background: #e53935;
      color: #ffffff !important;
      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      border: 0;
      font-size: 15px;
      box-shadow: none;
      cursor: pointer;
      transition: transform 0.15s ease;
    }
    .round-btn:active, .wallet:active { transform: scale(0.92); }
    .wallet { padding: 0 12px; gap: 6px; font-weight: 800; font-size: 13px; }
    .title { text-align: center; line-height: 1.1; color: #ffffff; }
    .title strong { display: block; font-size: 15px; text-transform: uppercase; letter-spacing: 0.5px; }
    .title span { font-size: 11px; color: #ffcdd2; text-transform: uppercase; font-weight: 700; }

    .info { max-width: 720px; margin: 14px auto 8px; padding: 0 14px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .box { background: #ffffff; color: #1e1e1e; border: 1px solid #eeeeee; border-radius: 12px; padding: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 800; box-shadow: 0 4px 12px rgba(0,0,0,.15); font-size: 13px; }
    .box i { color: #ff4d4d; }
    .closed { max-width: 720px; margin: 8px auto 0; padding: 10px 14px; border-radius: 10px; background: #2a0a0a; color: #ff4d4d; text-align: center; font-weight: 800; font-size: 13px; border: 1px solid #333333; }

    .grid { max-width: 720px; margin: 0 auto; padding: 12px 14px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .pana-grid { grid-template-columns: repeat(3, 1fr); }
    .card, .key { background: #ffffff; border: 1px solid #eeeeee; border-radius: 12px; min-height: 56px; display: flex; align-items: center; gap: 10px; padding: 10px; box-shadow: 0 6px 16px rgba(0,0,0,.12); transition: transform 0.15s ease; }
    .card:focus-within { transform: translateY(-2px); border-color: #ff4d4d; }
    .num { width: 38px; height: 38px; border-radius: 50%; background: #fdeeed; color: #8b0000; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 16px; flex: 0 0 auto; }
    .card input, .bulk-points input, .search input { border: 0; outline: 0; width: 100%; font-weight: 800; font-size: 15px; color: #1e1e1e; background: transparent; }
    .bulk-points, .search { max-width: 720px; margin: 12px auto 0; padding: 0 14px; }
    .bulk-points .inner, .search .inner { background: #ffffff; border: 1px solid #eeeeee; border-radius: 12px; padding: 14px; box-shadow: 0 6px 16px rgba(0,0,0,.12); }
    .key { min-height: 62px; justify-content: center; flex-direction: column; gap: 2px; cursor: pointer; }
    .key .num { background: transparent; width: auto; height: auto; color: #1e1e1e; font-size: 17px; }
    .badge { font-size: 11px; color: #2e7d32; font-weight: 900; min-height: 14px; }

    .bottom { position: fixed; left: 0; right: 0; bottom: 0; background: #ffffff; border-top: 1px solid #eeeeee; padding: 12px 16px; box-shadow: 0 -4px 18px rgba(0,0,0,.15); z-index: 20; border-top-left-radius: 16px; border-top-right-radius: 16px; }
    .totals { display: flex; justify-content: space-between; max-width: 720px; margin: 0 auto 8px; font-size: 13px; font-weight: 800; color: #212529; }
    .submit { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; max-width: 480px; margin: 0 auto; height: 48px; border: 0; border-radius: 999px; background: linear-gradient(135deg, #8b0000 0%, #c62828 50%, #e53935 100%); color: #ffffff; font-weight: 900; letter-spacing: 0.5px; box-shadow: 0 8px 20px rgba(139, 0, 0, 0.35); cursor: pointer; transition: transform 0.15s ease; }
    .submit:active { transform: scale(0.97); }
    .submit:disabled { opacity: 0.65; cursor: not-allowed; }

    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.6); backdrop-filter: blur(3px); display: none; align-items: center; justify-content: center; z-index: 5000; }
    .modal-box { background: #1a1a1a; border: 1px solid #333333; width: 80%; max-width: 290px; border-radius: 14px; padding: 20px 18px 16px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
    .modal-msg { font-size: 14px; font-weight: 700; color: #ffffff; margin-bottom: 14px; line-height: 1.4; }
    .modal-btn { background: #ff4d4d; color: #ffffff; border: 0; padding: 6px 26px; border-radius: 6px; font-weight: 700; font-size: 14px; cursor: pointer; display: inline-block; }
    #successOverlay { background: rgba(0,0,0,.95); flex-direction: column; }
    @media (min-width:680px){.grid{grid-template-columns:repeat(3,1fr)}.pana-grid{grid-template-columns:repeat(5,1fr)}}
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
  <?php $cleanMarket = str_replace(['STARLINE ', 'Starline '], '', $marketLabel); ?>
  <header class="header-bar">
    <a href="javascript:history.back()" class="round-btn"><i class="fas fa-chevron-left"></i></a>
    <div class="title" style="flex:1; overflow:hidden; margin:0 8px; text-align:center;">
      <strong style="display:block; font-size:15px; text-transform:uppercase; letter-spacing:0.5px; color:#ffffff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($cleanMarket !== 'Welcome' ? $cleanMarket : 'KISMAT STARLINE') ?></strong>
      <span style="display:block; font-size:11px; color:#aaaaaa; text-transform:uppercase; font-weight:700; margin-top:2px; letter-spacing:0.5px;"><?= htmlspecialchars($subtitle) ?></span>
    </div>
    <a href="deposit.php" class="wallet"><i class="fas fa-wallet"></i><span><?= number_format($totalBalance, 0) ?></span></a>
  </header>

  <div class="info">
    <div class="box"><i class="far fa-calendar-alt"></i><?= date('Y-m-d') ?></div>
    <div class="box"><i class="fas fa-clock"></i>CLOSING: <?= htmlspecialchars($closeLabel) ?></div>
  </div>
  <?php if ($pageClosed): ?>
    <div class="closed"><?= htmlspecialchars($pageState['message'] ?: 'Market Closed for Today') ?></div>
  <?php endif; ?>

  <?php if ($isBulk): ?>
    <div class="bulk-points"><div class="inner"><input type="tel" id="pointsInput" maxlength="6" placeholder="Points"></div></div>
    <?php if ($gameType !== 'SINGLE_DIGIT'): ?><div class="search"><div class="inner"><input type="tel" id="searchInput" placeholder="Search pana"></div></div><?php endif; ?>
    <div class="grid <?= $gameType === 'SINGLE_DIGIT' ? '' : 'pana-grid' ?>" id="bulkGrid">
      <?php foreach ($allowedNumbers as $n): ?>
        <button type="button" class="key" data-num="<?= htmlspecialchars($n) ?>"><span class="num"><?= htmlspecialchars($n) ?></span><span class="badge" id="badge-<?= htmlspecialchars($n) ?>"></span></button>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="grid <?= $gameType === 'SINGLE_DIGIT' ? '' : 'pana-grid' ?>">
      <?php foreach ($allowedNumbers as $n): ?>
        <div class="card"><span class="num"><?= htmlspecialchars($n) ?></span><input type="tel" class="points-input" data-num="<?= htmlspecialchars($n) ?>" maxlength="6" placeholder="Points"></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="bottom">
    <div class="totals"><span id="remain">Wallet: &#8377;<?= number_format($totalBalance, 0) ?></span><span id="totalBid">Total: &#8377;0</span></div>
    <button class="submit" id="submitBtn"><span>SUBMIT BID</span><i class="fas fa-paper-plane"></i></button>
  </div>

  <div class="modal-overlay" id="popup">
    <div class="modal-box">
      <div class="modal-msg" id="popupMsg"></div>
      <button class="modal-btn" type="button" onclick="document.getElementById('popup').style.display='none'"><?= __("OK") ?></button>
    </div>
  </div>

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
    const nonce = <?= json_encode($nonce) ?>;
    const wallet = <?= json_encode((float)$totalBalance) ?>;
    const minBid = <?= (int)$minBid ?>;
    const isClosed = <?= json_encode($pageClosed) ?>;
    const isBulk = <?= json_encode($isBulk) ?>;
    const submitBtn = document.getElementById('submitBtn');
    const totalBidEl = document.getElementById('totalBid');
    const remainEl = document.getElementById('remain');
    const popup = document.getElementById('popup');
    const successSound = document.getElementById('successSound');
    const bids = {};

    function showMsg(msg){document.getElementById('popupMsg').textContent=msg;popup.style.display='flex'}
    function fmt(n){return Math.floor(n).toLocaleString('en-IN')}
    function total(){return Object.values(bids).reduce((s,v)=>s+(+v||0),0)}
    function setTotals(n){totalBidEl.innerHTML='Total: &#8377;'+fmt(n);remainEl.innerHTML='Wallet: &#8377;'+fmt(Math.max(0,wallet-n))}

    if (isBulk) {
      const points = document.getElementById('pointsInput');
      if (points) points.addEventListener('input',()=>{points.value=points.value.replace(/[^0-9]/g,'')});
      document.querySelectorAll('.key').forEach(btn=>{
        btn.addEventListener('click',()=>{
          const amt=parseInt(points.value||'0',10);
          if(amt<minBid)return showMsg('Minimum bid Rs. '+minBid);
          if(total()+amt>wallet)return showMsg('Insufficient balance');
          const num=btn.dataset.num;
          bids[num]=(bids[num]||0)+amt;
          const badge=btn.querySelector('.badge');
          if(badge)badge.textContent='Rs. '+bids[num];
          setTotals(total());
        });
      });
      const search=document.getElementById('searchInput');
      if(search)search.addEventListener('input',()=>{const q=search.value.trim();document.querySelectorAll('.key').forEach(k=>{k.style.display=k.dataset.num.includes(q)?'flex':'none'})});
    } else {
      document.querySelectorAll('.points-input').forEach(inp=>{
        inp.addEventListener('input',()=>{
          inp.value=inp.value.replace(/[^0-9]/g,'');
          let sum=0;
          document.querySelectorAll('.points-input').forEach(i=>sum+=parseInt(i.value||'0',10));
          if(sum>wallet){inp.value='';showMsg('Insufficient balance');sum=0;document.querySelectorAll('.points-input').forEach(i=>sum+=parseInt(i.value||'0',10));}
          setTotals(sum);
        });
      });
    }

    submitBtn.addEventListener('click',async()=>{
      if(isClosed)return showMsg('Market is closed for today');
      let payload=[];
      if(isBulk){
        payload=Object.keys(bids).filter(k=>bids[k]>=minBid).map(k=>({number:k,amount:bids[k]}));
      }else{
        document.querySelectorAll('.points-input').forEach(inp=>{const amt=parseInt(inp.value||'0',10);if(amt>0)payload.push({number:inp.dataset.num,amount:amt})});
      }
      if(!payload.length)return showMsg('Please enter points');
      if(payload.some(b=>b.amount<minBid))return showMsg('Minimum bid Rs. '+minBid);
      const sum=payload.reduce((s,b)=>s+b.amount,0);
      if(sum>wallet)return showMsg('Insufficient balance');
      submitBtn.disabled=true;submitBtn.innerHTML='<i class="fas fa-circle-notch fa-spin"></i> Processing...';
      try{
        const fd=new FormData();fd.append('bids',JSON.stringify(payload));fd.append('nonce',nonce);
        const res=await fetch(window.location.href,{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}});
        const data=await res.json();
        if(data.ok){
          if(successSound){
            successSound.currentTime=0;
            successSound.play().catch(()=>{});
          }
          document.getElementById('successOverlay').style.display='flex';
          setTimeout(()=>location.reload(),1500);
        }
        else{showMsg(data.message||'Submission failed');submitBtn.disabled=false;submitBtn.innerHTML='<span>SUBMIT BID</span><i class="fas fa-paper-plane"></i>'}
      }catch(e){showMsg('Connection error. Please try again.');submitBtn.disabled=false;submitBtn.innerHTML='<span>SUBMIT BID</span><i class="fas fa-paper-plane"></i>'}
    });
  </script>
</body>
</html>





