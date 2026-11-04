<?php
require_once 'includes/db.php';
/* -------------------------------------------------
   admin_statistics.php – Matka Admin • Statistics
   ------------------------------------------------- */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
  header('Location: admin_login.php');
  exit;
}

/* ---------- DB CONNECT (with collation + error mode) ---------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = db();
} catch (Exception $e) {
  die('DB error: ' . $e->getMessage());
}

/* ---------- TIMEZONE (PHP + MySQL) ---------- */
date_default_timezone_set('Asia/Kolkata');
$db->query("SET time_zone = '+05:30'");

/* ---------- Markets from DB (for filter dropdown) ---------- */
$mainMarkets = [];
try {
  $res = $db->query("SELECT market_name FROM markets WHERE is_published = 1 ORDER BY sort_order, market_name");
  while ($row = $res->fetch_assoc()) {
    $mainMarkets[] = strtoupper(trim($row['market_name']));
  }
  $res->free();
} catch (Throwable $e) {
  // ignore
}
if (empty($mainMarkets)) {
  $mainMarkets = ['WELCOME', 'SURAJ', 'GULAB', 'PAYAL', 'RUBY', 'DIVYA']; // fallback
}

$galiMarkets = [];
try {
  $res = $db->query("SELECT market_name FROM gali_markets WHERE is_published = 1 ORDER BY sort_order, market_name");
  while ($row = $res->fetch_assoc()) {
    $galiMarkets[] = strtoupper(trim($row['market_name']));
  }
  $res->free();
} catch (Throwable $e) {
  // ignore
}

$marketOptions = [];
$marketOptions[] = ['val' => '', 'label' => 'All Markets'];
foreach ($mainMarkets as $m) {
  $marketOptions[] = ['val' => "{$m}_OPEN", 'label' => "{$m} open"];
  $marketOptions[] = ['val' => "{$m}_CLOSE", 'label' => "{$m} close"];
  $marketOptions[] = ['val' => "{$m}", 'label' => "{$m} (both)"];
}
foreach ($galiMarkets as $gm) {
  $marketOptions[] = ['val' => "{$gm}", 'label' => "{$gm} (Gali Desawar)"];
}
$markets = array_merge($mainMarkets, $galiMarkets);

/* ---------- 1 • filters ---------- */
$range = ($_GET['range'] ?? 'today') === 'custom' ? 'custom' : 'today';

$today = date('Y-m-d');
$from = $db->real_escape_string($_GET['from'] ?? $today);
$to = $db->real_escape_string($_GET['to'] ?? $today);

$ms = $db->real_escape_string($_GET['market'] ?? '');
if ($ms && strpos($ms, '_') !== false) {
  [$mkt, $sess] = explode('_', $ms, 2);
} else {
  $mkt = $ms !== '' ? $ms : null;
  $sess = null;
}

/* ---------- 2 • WHERE clause ---------- */
$where = "WHERE 1 ";
$where .= ($range === 'today')
  ? "AND DATE(bid_time)=CURDATE() "
  : "AND DATE(bid_time) BETWEEN '$from' AND '$to' ";

if ($mkt) {
  $where .= "AND market='" . $db->real_escape_string($mkt) . "' ";
}
if ($sess === 'OPEN' || $sess === 'CLOSE') {
  $where .= "AND session_type='" . $db->real_escape_string($sess) . "' ";
}

/* ---------- 3 • SINGLE & JODI digits ---------- */
$singleCnt = $singleAmt = array_fill(0, 10, 0);
$q = $db->query("SELECT single_digit d,COUNT(*) c,SUM(amount) a 
                 FROM bid $where AND single_digit IS NOT NULL 
                 GROUP BY d");
while ($r = $q->fetch_assoc()) {
  $i = (int) $r['d'];
  $singleCnt[$i] = (int) $r['c'];
  $singleAmt[$i] = (float) $r['a'];
}
$q->free();

$jodiCnt = $jodiAmt = array_fill(0, 100, 0);
/* Remove session filter for Jodi Digit */
$whereJodi = preg_replace("/AND session_type='(OPEN|CLOSE)' /i", '', $where);
$q = $db->query("SELECT jodi_digit d,COUNT(*) c,SUM(amount) a 
                 FROM bid $whereJodi AND jodi_digit IS NOT NULL 
                 GROUP BY d");
while ($r = $q->fetch_assoc()) {
  $i = (int) $r['d'];
  $jodiCnt[$i] = (int) $r['c'];
  $jodiAmt[$i] = (float) $r['a'];
}
$q->free();

/* ---------- 3.1 • GALI DESAWAR BIDS MERGE ---------- */
$galiWhere = "WHERE 1 ";
$galiWhere .= ($range === 'today')
  ? "AND (date = CURDATE() OR DATE(created_at) = CURDATE()) "
  : "AND (date BETWEEN '$from' AND '$to' OR DATE(created_at) BETWEEN '$from' AND '$to') ";

if ($mkt) {
  $galiWhere .= "AND UPPER(TRIM(market_name))='" . $db->real_escape_string(strtoupper(trim($mkt))) . "' ";
}

$qGali = $db->query("SELECT game_type, digit, amount FROM gali_bids $galiWhere");
if ($qGali) {
  while ($r = $qGali->fetch_assoc()) {
    $gt = strtolower(trim((string) ($r['game_type'] ?? '')));
    $dStr = trim((string) ($r['digit'] ?? ''));
    $amtVal = (float) ($r['amount'] ?? 0);

    if ($dStr === '')
      continue;

    if (stripos($gt, 'jodi') !== false || strlen($dStr) === 2) {
      $dInt = (int) $dStr;
      if ($dInt >= 0 && $dInt <= 99) {
        $jodiCnt[$dInt] = ($jodiCnt[$dInt] ?? 0) + 1;
        $jodiAmt[$dInt] = ($jodiAmt[$dInt] ?? 0) + $amtVal;
      }
    } else {
      $dInt = (int) $dStr;
      if ($dInt >= 0 && $dInt <= 9) {
        $singleCnt[$dInt] = ($singleCnt[$dInt] ?? 0) + 1;
        $singleAmt[$dInt] = ($singleAmt[$dInt] ?? 0) + $amtVal;
      }
    }
  }
  $qGali->free();
}

/* ---------- 4 • master-lists ---------- */
$singlePana = array_unique([
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
  '179',
  '250',
  '269',
  '340',
  '359',
  '368',
  '458',
  '467',
  '890',
  '278',
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
  '479',
  '569',
  '578',
  '389',
  '460'
]);
$doublePana = [
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
  '288',
  '299',
  '300',
  '330',
  '990',
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
  '700',
  '889',
  '799',
  '445',
  '446',
  '447',
  '448',
  '449',
  '455',
  '466',
  '477',
  '488',
  '499',
  '500',
  '550',
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
  '788'
];
$triplePana = ['000', '111', '222', '333', '444', '555', '666', '777', '888', '999'];

function blankStats($codes)
{
  $o = [];
  foreach ($codes as $c)
    $o[$c] = ['c' => 0, 'a' => 0];
  return $o;
}
$sP = blankStats($singlePana);
$dP = blankStats($doublePana);
$tP = blankStats($triplePana);

/* ---------- 5 • PANA stats ---------- */
foreach (['single_pana' => 'sP', 'double_pana' => 'dP', 'triple_pana' => 'tP'] as $col => $arr) {
  $q = $db->query("SELECT $col p,COUNT(*) c,SUM(amount) a
                     FROM bid $where AND $col IS NOT NULL 
                     GROUP BY p");
  while ($r = $q->fetch_assoc()) {
    if (isset(${$arr}[$r['p']])) {
      ${$arr}[$r['p']] = [
        'c' => (int) $r['c'],
        'a' => (float) $r['a']
      ];
    }
  }
  $q->free();
}

/* ---------- 6 • Sangam stats ---------- */
$halfSangam = [];
$fullSangam = [];

/* Sangam Bids (Top 100) */
$qS = $db->query("SELECT game_type, open_digit, close_digit, open_pana, close_pana, amount FROM bid $where AND game_type IN ('HALF_SANGAM', 'FULL_SANGAM')");
if ($qS) {
  while ($r = $qS->fetch_assoc()) {
    if ($r['game_type'] === 'HALF_SANGAM') {
      $d = ($r['open_digit'] !== null) ? $r['open_digit'] : $r['close_digit'];
      $p = ($r['open_pana'] !== null) ? $r['open_pana'] : $r['close_pana'];
      $val = $d . '-' . $p;
      if (!isset($halfSangam[$val]))
        $halfSangam[$val] = ['c' => 0, 'a' => 0];
      $halfSangam[$val]['c']++;
      $halfSangam[$val]['a'] += (float) $r['amount'];
    } else {
      $val = $r['open_pana'] . '-' . $r['close_pana'];
      if (!isset($fullSangam[$val]))
        $fullSangam[$val] = ['c' => 0, 'a' => 0];
      $fullSangam[$val]['c']++;
      $fullSangam[$val]['a'] += (float) $r['amount'];
    }
  }
  $qS->free();
}
// Sort and limit
uasort($halfSangam, function ($a, $b) {
  return $b['a'] <=> $a['a'];
});
uasort($fullSangam, function ($a, $b) {
  return $b['a'] <=> $a['a'];
});
$halfSangam = array_slice($halfSangam, 0, 100, true);
$fullSangam = array_slice($fullSangam, 0, 100, true);
$db->close();

/* ---------- 6 • totals for overview ---------- */
$totSingleCnt = array_sum($singleCnt);
$totSingleAmt = array_sum($singleAmt);
$totJodiCnt = array_sum($jodiCnt);
$totJodiAmt = array_sum($jodiAmt);


$totSPCnt = $totSPAmt = $totDPCnt = $totDPAmt = $totTPCnt = $totTPAmt = 0;
foreach ($sP as $v) {
  $totSPCnt += $v['c'];
  $totSPAmt += $v['a'];
}
foreach ($dP as $v) {
  $totDPCnt += $v['c'];
  $totDPAmt += $v['a'];
}
foreach ($tP as $v) {
  $totTPCnt += $v['c'];
  $totTPAmt += $v['a'];
}

/* Grand Total */
$grandTotalCnt = $totSingleCnt + $totJodiCnt + $totSPCnt + $totDPCnt + $totTPCnt;
$grandTotalAmt = $totSingleAmt + $totJodiAmt + $totSPAmt + $totDPAmt + $totTPAmt;

/* ---------- 7 • push arrays to JS ---------- */
?>
<script>
  var singleCnt = <?= json_encode($singleCnt) ?>;
  var singleAmt = <?= json_encode($singleAmt) ?>;
  var jodiCnt = <?= json_encode($jodiCnt) ?>;
  var jodiAmt = <?= json_encode($jodiAmt) ?>;
  var singlePana = <?= json_encode(array_values($singlePana), JSON_NUMERIC_CHECK) ?>;
  var sPData = <?= json_encode(array_values($sP)) ?>;
  var doublePana = <?= json_encode(array_values($doublePana), JSON_NUMERIC_CHECK) ?>;
  var dPData = <?= json_encode(array_values($dP)) ?>;
  var triplePana = <?= json_encode(array_values($triplePana)) ?>; // leading zeros safe
  var tPData = <?= json_encode(array_values($tP)) ?>;
  var defRange = <?= json_encode($range) ?>;
  var defFrom = <?= json_encode($from) ?>;
  var defTo = <?= json_encode($to) ?>;
  var defMarket = <?= json_encode($ms) ?>;
  var marketsPhp = <?= json_encode(array_values($markets)) ?>;

  var halfSangamData = <?= json_encode($halfSangam) ?>;
  var fullSangamData = <?= json_encode($fullSangam) ?>;
</script>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Matka Admin • Statistics</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
  <style>
    :root {
      --grad-start: #f5f0e5;
      --grad-end: #e8d9c9;
      --primary: #c62828;
      --primary-dark: #750000;
      --primary-light: #b21616;
      --card: #ffffff;
      --text: #4e342e;
      --muted: #757575;
      --shadow: 0 3px 12px rgba(0, 0, 0, .12);
      --radius: 10px
    }

    *,
    *:before,
    *:after {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    body {
      font-family: 'Roboto', sans-serif;
      background: linear-gradient(135deg, var(--grad-start), var(--grad-end));
      color: var(--text);
      min-height: 100vh;
      padding: 20px
    }

    /* top bar */
    .top-bar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 22px
    }

    .page-title {
      font-size: 1.6rem;
      font-weight: 700;
      flex: 1 1 200px;
      text-align: center;
      color: var(--primary-dark)
    }

    .btn-back {
      background: linear-gradient(90deg, var(--primary-dark), var(--primary-light));
      color: #fff;
      border: none;
      border-radius: 50px;
      padding: 9px 18px;
      text-decoration: none;
      font-size: .9rem;
      box-shadow: var(--shadow);
      transition: .25s
    }

    .btn-back:hover {
      filter: brightness(1.07);
      transform: translateY(-2px)
    }

    .clock {
      font-size: .95rem;
      color: var(--muted);
      font-variant-numeric: tabular-nums
    }

    /* filters */
    .filter-box {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 12px 14px;
      margin-bottom: 18px;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
      justify-content: center
    }

    .filter-box label {
      font-size: .9rem;
      display: flex;
      align-items: center;
      gap: 6px;
      color: var(--text)
    }

    .filter-box select,
    .filter-box input[type=date] {
      padding: 6px 8px;
      border: 1px solid #ccc;
      border-radius: 6px;
      background: #fff;
      font-size: .9rem
    }

    .filter-box input[type=checkbox] {
      transform: scale(1.1)
    }

    .btn-search {
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 7px 16px;
      font-size: .9rem;
      cursor: pointer;
      box-shadow: var(--shadow);
      transition: .25s
    }

    .btn-search:hover {
      filter: brightness(1.07);
      transform: translateY(-2px)
    }

    /* overview */
    .overview {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 10px;
      margin-bottom: 20px
    }

    .info-bar {
      flex: 1 1 220px;
      font-size: .9rem;
      color: var(--muted)
    }

    .ov-grid {
      display: flex;
      justify-content: center;
      gap: 16px;
      margin-top: 10px;
    }

    .ov-card {
      background: linear-gradient(135deg, var(--primary-light), var(--primary));
      border: none;
      color: #fff;
      border-radius: 8px;
      padding: 12px;
      text-align: center;
      box-shadow: var(--shadow);
    }

    .ov-card .lbl,
    .ov-card .amt {
      color: rgba(255, 255, 255, .85);
    }

    .ov-card .cnt {
      color: #fff;
    }

    .ov-card.grand {
      background: linear-gradient(135deg, #B8860B 0%, #FFD700 50%, #B8860B 100%);
      border: 2px solid #fff;
      box-shadow: 0 4px 18px rgba(184,134,11,0.45);
    }

    .ov-card.grand .lbl,
    .ov-card.grand .amt {
      color: rgba(0,0,0,0.75);
    }

    .ov-card.grand .cnt {
      color: #000;
      font-size: 1.3rem;
      font-weight: 800;
    }

    /* grids */
    .section {
      margin-bottom: 26px
    }

    .section h2 {
      font-size: 1.25rem;
      font-weight: 600;
      margin-bottom: 12px;
      color: var(--primary-dark)
    }

    .section-card {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 14px
    }

    .grid-flex {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
      gap: 12px
    }

    .grid-jodi {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(52px, 1fr));
      gap: 12px
    }

    .pana-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(68px, 1fr));
      gap: 10px
    }

    .grid-card,
    .pana-card {
      background: #fafafa;
      border: 2px solid var(--primary);
      border-radius: 8px;
      text-align: center;
      padding: 8px 6px;
      position: relative;
      transition: .15s
    }

    .grid-card:hover,
    .pana-card:hover {
      background: #fff;
      transform: translateY(-2px)
    }

    .num-lg {
      font-size: 1.2rem;
      font-weight: 600;
      color: var(--primary-dark)
    }

    .amt {
      display: block;
      font-size: .7rem;
      color: var(--muted);
      margin-top: 2px
    }

    .badge {
      position: absolute;
      top: -7px;
      right: -7px;
      background: var(--primary);
      color: #fff;
      font-size: .7rem;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      animation: badgePulse 1.8s infinite
    }

    @keyframes badgePulse {

      0%,
      100% {
        box-shadow: 0 0 4px rgba(198, 40, 40, .4)
      }

      50% {
        box-shadow: 0 0 10px rgba(198, 40, 40, .8)
      }
    }

    /* mobile tweaks */
    @media(max-width:600px) {
      .btn-back {
        padding: 8px 14px
      }

      .page-title {
        text-align: left;
        font-size: 1.35rem
      }

      .filter-box {
        justify-content: flex-start
      }

      .ov-grid {
        flex-direction: column;
      }
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>
  <!-- top bar -->
  <div class="top-bar">
    <a href="admin_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
    <h1 class="page-title">Statistics</h1>
    <div class="clock" id="liveClock"></div>
  </div>

  <!-- filters -->
  <form class="filter-box" method="get">
    <label>Date&nbsp;
      <select id="dateRange" name="range">
        <option value="today">Today</option>
        <option value="custom">Custom</option>
      </select>
    </label>
    <input type="date" id="fromDate" name="from">
    <input type="date" id="toDate" name="to">
    <label>Market&nbsp;
      <select id="marketFilter" name="market">
        <?php foreach ($marketOptions as $opt): ?>
          <option value="<?= htmlspecialchars($opt['val']) ?>" <?= ($ms === $opt['val']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($opt['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><input type="checkbox" id="onlyRequested" name="only" value="1" <?= isset($_GET['only']) ? 'checked' : '' ?>>
      Only requested</label>
    <button class="btn-search" type="submit"><i class="fas fa-search"></i> Search</button>
  </form>

  <!-- overview -->
  <div class="overview">
    <div class="info-bar">
      <?= $mkt ? "<strong>$mkt</strong> " . ($sess ? strtolower($sess) : '') . ' | ' : 'All markets | ' ?>
      <?= $range === 'today'
        ? date('d M Y', strtotime($from))
        : date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to)); ?>
    </div>
    <div class="ov-grid">
      <div class="ov-card"><span class="lbl">Single Digit</span>
        <span class="cnt"><?= $totSingleCnt ?></span>
        <span class="amt">₹<?= number_format($totSingleAmt, 0, '.', ',') ?></span>
      </div>
      <div class="ov-card"><span class="lbl">Jodi Digit</span>
        <span class="cnt"><?= $totJodiCnt ?></span>
        <span class="amt">₹<?= number_format($totJodiAmt, 0, '.', ',') ?></span>
      </div>
      <div class="ov-card"><span class="lbl">Single Pana</span>
        <span class="cnt"><?= $totSPCnt ?></span>
        <span class="amt">₹<?= number_format($totSPAmt, 0, '.', ',') ?></span>
      </div>
      <div class="ov-card"><span class="lbl">Double Pana</span>
        <span class="cnt"><?= $totDPCnt ?></span>
        <span class="amt">₹<?= number_format($totDPAmt, 0, '.', ',') ?></span>
      </div>
      <div class="ov-card"><span class="lbl">Triple Pana</span>
        <span class="cnt"><?= $totTPCnt ?></span>
        <span class="amt">₹<?= number_format($totTPAmt, 0, '.', ',') ?></span>
      </div>
      <div class="ov-card grand"><span class="lbl">🏆 Grand Total</span>
        <span class="cnt"><?= $grandTotalCnt ?></span>
        <span class="amt">₹<?= number_format($grandTotalAmt, 0, '.', ',') ?></span>
      </div>
    </div>
  </div>

  <!-- stats -->
  <div class="section">
    <h2>Single Digit</h2>
    <div class="section-card">
      <div id="singleList" class="grid-flex"></div>
    </div>
  </div>
  <div class="section">
    <h2>Jodi Digit</h2>
    <div class="section-card">
      <div id="jodiGrid" class="grid-jodi"></div>
    </div>
  </div>
  <div class="section">
    <h2>Single Pana</h2>
    <div class="section-card">
      <div id="panaS" class="pana-container"></div>
    </div>
  </div>
  <div class="section">
    <h2>Double Pana</h2>
    <div class="section-card">
      <div id="panaD" class="pana-container"></div>
    </div>
  </div>
  <div class="section">
    <h2>Triple Pana</h2>
    <div class="section-card">
      <div id="panaT" class="pana-container"></div>
    </div>
  </div>


  <div id="sangamSections">
    <div class="section">
      <h2>Half Sangam (Top 100)</h2>
      <div class="section-card">
        <div id="halfSangamList" class="pana-container"></div>
      </div>
    </div>
    <div class="section">
      <h2>Full Sangam (Top 100)</h2>
      <div class="section-card">
        <div id="fullSangamList" class="pana-container"></div>
      </div>
    </div>
  </div>

  <script>
    /* market dropdown */
    var mf = document.getElementById('marketFilter');

    /* date range widgets */
    var dateRange = document.getElementById('dateRange');
    var fromDate = document.getElementById('fromDate');
    var toDate = document.getElementById('toDate');

    dateRange.value = defRange;
    fromDate.value = defFrom;
    toDate.value = defTo;

    if (defRange !== 'custom') {
      fromDate.style.display = 'none';
      toDate.style.display = 'none';
    }
    dateRange.onchange = function() {
      var sh = dateRange.value === 'custom';
      fromDate.style.display = toDate.style.display = sh ? 'inline-block' : 'none';
    };

    /* live clock (12hr) */
    function tick() {
      document.getElementById('liveClock').textContent =
        new Date().toLocaleString('en-US', {
          day: '2-digit', month: 'short', year: 'numeric',
          hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
    }
    setInterval(tick, 1000); tick();

    /* helpers */
    var onlyBox = document.getElementById('onlyRequested');
    var badge = c => c ? `<span class="badge">${c}</span>` : '';
    var amt = v => `<span class="amt">₹${(+v || 0).toLocaleString('en-IN')}</span>`;
    var card = (n, c, a) => `<div class="grid-card"><span class="num-lg">${n}</span>${amt(a)}${badge(c)}</div>`;
    var pcard = (code, o) => `<div class="pana-card"><span class="num-lg">${code}</span>${amt(o.a)}${badge(o.c)}</div>`;

    function draw() {
      var only = onlyBox.checked;

      singleList.innerHTML = singleCnt
        .map((c, i) => card(i, c, singleAmt[i]))
        .join('');

      jodiGrid.innerHTML = jodiCnt
        .map((c, i) => only && c === 0 ? '' : card(String(i).padStart(2, '0'), c, jodiAmt[i]))
        .join('');

      panaS.innerHTML = singlePana
        .map((p, i) => only && sPData[i].c === 0 ? '' : pcard(p, sPData[i]))
        .join('');

      panaD.innerHTML = doublePana
        .map((p, i) => only && dPData[i].c === 0 ? '' : pcard(p, dPData[i]))
        .join('');

      panaT.innerHTML = triplePana
        .map((p, i) => only && tPData[i].c === 0 ? '' : pcard(p, tPData[i]))
        .join('');


      // SANGAM Rendering
      halfSangamList.innerHTML = Object.entries(halfSangamData).map(([val, o]) => pcard(val, o)).join('');
      fullSangamList.innerHTML = Object.entries(fullSangamData).map(([val, o]) => pcard(val, o)).join('');
    }
    draw();

    /* Search button form submit server-side (keep) – "Only requested" works client-side */
  </script>
</body>

</html>
