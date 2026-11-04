<?php
/* gali_game.php – Gali/Desawar Game Page
   Tabs: JODI | CROSSING | COPY PASTE
*/
session_start();
require_once 'includes/db.php';
date_default_timezone_set('Asia/Kolkata');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 0);

if (!isset($_SESSION['user']['id'])) {
    header('Location: user_login.php');
    exit;
}

if (!function_exists('__')) {
    function __($str) { return $str; }
}

$market = strtoupper(trim($_GET['market'] ?? 'GALI'));
$marketLabel = ucwords(strtolower($market));

/* DB helpers */
function gg_db(): mysqli {
    $db = db();
    $db->query("SET time_zone = '+05:30'");
    return $db;
}
function gg_code(mysqli $db, int $uid): string {
    $st = $db->prepare("SELECT user_id FROM users WHERE id=? LIMIT 1");
    $st->bind_param('i', $uid);
    $st->execute();
    $st->bind_result($c);
    $st->fetch();
    $st->close();
    return (string)$c;
}
function gg_bal(mysqli $db, string $code): float {
    return get_total_balance($db, $code);
}

$uid  = (int)$_SESSION['user']['id'];
$db   = gg_db();
$code = gg_code($db, $uid);
$bal  = gg_bal($db, $code);

/* Min bid */
$minBid = 10;
$rs = $db->query("SELECT setting_value FROM app_settings WHERE setting_key='min_bid' LIMIT 1");
if ($rs && $r = $rs->fetch_assoc()) $minBid = (int)$r['setting_value'];

/* Market info */
$stmt = $db->prepare("SELECT id, result_time, close_time_start, close_time_end, today_closed, saturday_closed, sunday_closed, is_next_day FROM gali_markets WHERE UPPER(market_name)=? AND status='active' LIMIT 1");
$stmt->bind_param('s', $market);
$stmt->execute();
$mkt = $stmt->get_result()->fetch_assoc();
$stmt->close();

$marketId   = $mkt['id'] ?? 0;
$closeStart = $mkt ? date('H:i:s', strtotime($mkt['close_time_start'])) : '23:59:00';
$closeEnd   = $mkt ? date('H:i:s', strtotime($mkt['close_time_end']))   : '23:59:00';
$resultTime = $mkt ? date('h:i A', strtotime($mkt['result_time']))        : '12:00 AM';

$now = date('H:i');
$isClosed = false;
if ($mkt) {
    $cs = date('H:i', strtotime($mkt['close_time_start']));
    $ce = date('H:i', strtotime($mkt['close_time_end']));
    if ($cs !== $ce) {
        if ($cs < $ce) { if ($now >= $cs && $now < $ce) $isClosed = true; }
        else           { if ($now >= $cs || $now < $ce)  $isClosed = true; }
    }
    if ($mkt['today_closed'])                       $isClosed = true;
    if (date('w')==6 && $mkt['saturday_closed'])    $isClosed = true;
    if (date('w')==0 && $mkt['sunday_closed'])      $isClosed = true;
}

/* Token */
if (empty($_SESSION['gg_token'])) $_SESSION['gg_token'] = bin2hex(random_bytes(16));
$token = $_SESSION['gg_token'];

/* ====== Handle POST bid submission ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bids'])) {
    header('Content-Type: application/json');
    if ($isClosed) { echo json_encode(['ok'=>false,'message'=>'Market Closed']); exit; }

    $postedToken = (string)($_POST['bid_token'] ?? '');
    if (!$postedToken || !hash_equals($_SESSION['gg_token'] ?? '', $postedToken)) {
        echo json_encode(['ok'=>false,'message'=>'Invalid Token']); exit;
    }

    $gameType = trim($_POST['game_type'] ?? 'Jodi Digit');
    $bids = json_decode((string)$_POST['bids'], true);
    if (!is_array($bids) || empty($bids)) { echo json_encode(['ok'=>false,'message'=>'No bids']); exit; }

    $clean = []; $total = 0.0;
    $defaultGameType = trim($_POST['game_type'] ?? 'Jodi Digit');
    foreach ($bids as $b) {
        $digit     = preg_replace('/\D/', '', (string)($b['number'] ?? ''));
        $amount    = (float)($b['amount'] ?? 0);
        $bidGT     = trim((string)($b['game_type'] ?? $defaultGameType));
        if ($digit === '' || $amount < $minBid) continue;
        $total += $amount;
        $clean[] = ['digit'=>$digit, 'amount'=>$amount, 'game_type'=>$bidGT ?: $defaultGameType];
    }
    if (empty($clean)) { echo json_encode(['ok'=>false,'message'=>'No valid bids']); exit; }

    $available = gg_bal($db, $code);
    if ($total > $available + 0.001) { echo json_encode(['ok'=>false,'message'=>'Insufficient balance']); exit; }

    try {
        $db->begin_transaction();
        $targetDate = date('Y-m-d');
        if (!empty($mkt['is_next_day'])) {
            if (date('H:i') >= '09:00') {
                $targetDate = date('Y-m-d', strtotime('+1 day'));
            }
        }

        $ins = $db->prepare("INSERT INTO gali_bids (user_id, market_id, market_name, game_type, digit, amount, date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($clean as $b) {
            $gt = $b['game_type'];
            $ins->bind_param('sisssds', $code, $marketId, $market, $gt, $b['digit'], $b['amount'], $targetDate);
            $ins->execute();
        }
        $ins->close();
        $wd = $db->prepare("INSERT INTO withdrawal (user_id, amount, status, method, date_time) VALUES (?, ?, 'Done', 'game_bid', NOW())");
        $wd->bind_param('sd', $code, $total);
        $wd->execute();
        $wd->close();
        $db->commit();
        $_SESSION['gg_token'] = bin2hex(random_bytes(16));
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        $db->rollback();
        echo json_encode(['ok'=>false,'message'=>'DB Error']);
    }
    exit;
}
$db->close();

/* Compute JS timer seconds */
$nowTs    = time();
$closeTs  = strtotime(date('Y-m-d') . ' ' . $closeStart);
if ($closeTs <= $nowTs) $closeTs += 86400;
$secsLeft = max(0, $closeTs - $nowTs);
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <title><?php echo  htmlspecialchars($marketLabel) ?> – DESAWAR BOSS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.lordicon.com/lusqsztk.js"></script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI', Tahoma, Verdana, sans-serif}
        body{background:#000000;min-height:100vh;padding-bottom:80px}

        /* Header */
        .g-header{
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
        }
        .g-header .back{
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #e53935;
            color: #ffffff !important;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 18px;
            border: none;
            cursor: pointer;
            flex-shrink: 0;
            transition: transform 0.15s ease;
        }
        .g-header .back:active{ transform: scale(0.92); }
        .g-header .market-title-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: #ffffff;
            font-size: 16px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex: 1;
            margin: 0 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .g-header .wallet-btn-head {
            background: #e53935;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            color: #ffffff;
            font-weight: 800;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            line-height: 1;
            text-decoration: none;
            flex-shrink: 0;
        }

        /* Info Bar */
        .g-bar { background: #1a1a1a; padding: 10px 14px; text-align: center; font-weight: 800; font-size: 13px; color: #ffffff; margin-bottom: 0; letter-spacing: 0.3px; border-bottom: 1px solid #333333; }

        .closed-bar { background: #2a0a0a; color: #ff4d4d;
            padding: 10px 14px;
            text-align: center;
            font-weight: 800;
            font-size: 13px;
        }

        /* Tabs */
        .g-tabs { display: flex; background: #1a1a1a; border-bottom: 1px solid #333333;
            margin-bottom: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
        }

        .g-tab { flex: 1; text-align: center; padding: 12px 6px; font-weight: 800; font-size: 13px; color: #aaaaaa;
            cursor: pointer;
            border: none;
            background: transparent;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            outline: none;
            -webkit-appearance: none;
        }

        .g-tab.active { color: #ff4d4d; border-bottom-color: #ff4d4d; background: rgba(255, 255, 255, 0.05);
        }

        /* Tab panels */
        .g-panel { display: none; padding: 10px; max-width: 600px; margin: 0 auto; }
        .g-panel.active { display: block; }

        /* ======= JODI GRID ======= */
        .jodi-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 14px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #ffcdd2;
        }

        .jodi-table td {
            border: 1px solid #ffebee;
            text-align: center;
            padding: 0;
            width: 10%;
        }

        .jodi-num {
            background: #c62828;
            color: #ffffff;
            font-size: 11.5px;
            font-weight: 800;
            padding: 4px 1px;
        }

        .jodi-input {
            background: #ffffff;
            padding: 2px 0;
        }

        .jodi-input input {
            width: 100%;
            border: none;
            outline: none;
            text-align: center;
            font-size: 13px;
            font-weight: 800;
            padding: 4px 0;
            background: transparent;
            color: #1e1e1e;
        }

        .jodi-input input:focus {
            background: #fff8f8;
            color: #c62828;
        }

        /* Haruf label */
        .haruf-label { color: #ff4d4d;
            font-weight: 800;
            font-size: 13.5px;
            margin: 14px 0 6px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* CROSSING / COPY PASTE Form */
        .input-card-custom { background: #ffffff; border-radius: 12px; padding: 16px; margin-bottom: 16px; border: 1px solid #eeeeee;
        }

        .g-field { margin-bottom: 12px; }
        .g-field label {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: #1a202c;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .g-field input {
            width: 100%;
            border: 1.5px solid transparent;
            background: #f0f4f8;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 10px;
            outline: none;
            color: #2d3748;
            transition: all 0.15s ease;
        }

        .g-field input:focus {
            background: #ffffff;
            border-color: #e53935;
            box-shadow: 0 0 0 3px rgba(229, 57, 53, 0.15);
        }

        /* Palti Radio */
        .palti-row { display: flex; gap: 10px; margin-bottom: 14px; }
        .palti-opt {
            flex: 1;
            border: 1.5px solid #c62828;
            border-radius: 10px;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 800;
            color: #8b0000;
            background: #ffffff;
            transition: all 0.15s ease;
        }
        .palti-opt input[type=radio] { accent-color: #8b0000; width: 18px; height: 18px; cursor: pointer; }

        /* Add Button */
        .g-add-btn {
            width: 100%;
            background: #ff3b30;
            color: #ffffff;
            border: none;
            padding: 11px;
            font-size: 14px;
            font-weight: 800;
            border-radius: 8px;
            cursor: pointer;
            margin-bottom: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 14px rgba(255, 59, 48, 0.35);
            transition: transform 0.15s ease;
        }
        .g-add-btn:active { transform: scale(0.97); }
        .g-add-btn:disabled { background: #ff8a80; opacity: 0.65; cursor: not-allowed; box-shadow: none; }

        /* Table */
        .g-table-wrap { background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #edf2f7; margin-top: 10px; }
        .g-table { width: 100%; border-collapse: collapse; }
        .g-table th {
            background: #f8fafc;
            color: #1a202c;
            font-size: 13px;
            font-weight: 800;
            padding: 10px 8px;
            border-bottom: 1px solid #edf2f7;
            text-transform: uppercase;
        }
        .g-table td {
            padding: 10px 8px;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            color: #2d3748;
            border-bottom: 1px solid #f7fafc;
        }
        .g-del-btn {
            background: transparent;
            color: #e53935;
            border: none;
            font-size: 16px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
        }
        body { background: #000000; min-height: 100vh; padding-bottom: 100px; }

        .g-del-btn:active { background: #ffebee; }

        /* Footer */
        .g-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #ffffff;
            border-top: 1px solid #edf2f7;
            box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.12);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            z-index: 99999;
        }
        .g-footer .amt {
            font-size: 18px;
            font-weight: 800;
            color: #1e1e1e;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .g-footer .amt span { font-size: 12px; color: #777; font-weight: 600; }
        .g-play-btn {
            background: #ff3b30;
            color: #ffffff;
            border: none;
            padding: 11px 36px;
            font-size: 15px;
            font-weight: 800;
            border-radius: 8px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 14px rgba(255, 59, 48, 0.4);
            transition: transform 0.15s ease;
        }
        .g-play-btn:active { transform: scale(0.96); }
        .g-play-btn:disabled { background: #ff8a80; opacity: 0.65; cursor: not-allowed; box-shadow: none; }

        /* Popup */
        .gg-overlay{
            position:fixed;inset:0;background:rgba(0,0,0,.5);
            display:none;align-items:center;justify-content:center;z-index:999;
        }
        .gg-box{background:#1a1a1a;border-radius:12px;width:90%;max-width:320px;overflow:hidden;box-shadow: 0 10px 25px rgba(0,0,0,0.15);}
        .gg-box-head{background:#000000; border-bottom:1px solid #333333;color:#fff;padding:14px 18px;font-weight:700;font-size:16px;display:flex;justify-content:space-between;align-items:center}
        .gg-box-body{padding:20px;text-align:center;font-size:14px;color:#fff}
        .gg-close{cursor:pointer;font-size:18px}

        /* Success overlay */
        .gg-success{
            position:fixed;inset:0;background:rgba(0,0,0,.6);
            display:none;align-items:center;justify-content:center;z-index:1000;
            flex-direction:column;gap:12px;
        }
        .gg-success-icon{font-size:70px;color:#4caf50}
        .gg-success-text{color:#fff;font-size:22px;font-weight:700}

        .closed-bar{
            background:#8b0000;color:#fff;text-align:center;
            padding:8px;font-weight:700;font-size:13px;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

<!-- Header -->
<div class="g-header">
    <a href="gali_desawar.php" class="back" title="Back"><i class="fas fa-arrow-left"></i></a>
    <div class="market-title-wrap">
        <img src="icons/gold_coin.svg" alt="coin" style="width: 18px; height: 18px; vertical-align: middle;">
        <?= htmlspecialchars($marketLabel) ?>
        <img src="icons/gold_coin.svg" alt="coin" style="width: 18px; height: 18px; vertical-align: middle;">
    </div>
    <a href="deposit.php" class="wallet-btn-head">
        <i class="fa-solid fa-wallet"></i>
        <span><?= number_format($bal, 0) ?></span>
    </a>
</div>

<!-- Info bar with countdown -->
<?php if ($isClosed): ?>
<div class="closed-bar">
    <div style="text-align:center;">
        <span>⚠️ Market Closed</span>
    </div>
</div>
<?php else: ?>
<div class="g-bar">
    <div style="text-align:center;">
        <span>मोटी जोडी लास्ट टाइम : <span id="barTimer">00:00:00</span></span>
    </div>
</div>
<?php endif; ?>

<!-- Tabs -->
<div class="g-tabs">
    <button type="button" class="g-tab active" onclick="switchTab('jodi',this)">JODI</button>
    <button type="button" class="g-tab" onclick="switchTab('crossing',this)">CROSSING</button>
    <button type="button" class="g-tab" onclick="switchTab('copypaste',this)">COPY PASTE</button>
</div>

<!-- =========== JODI TAB =========== -->
<div class="g-panel active" id="tab-jodi">
    <table class="jodi-table" id="jodiGrid">
        <tbody>
        <?php
        // 10 rows × 10 cols (01..00)
        for ($row = 0; $row < 10; $row++):
        ?>
        <tr>
            <?php for ($col = 1; $col <= 10; $col++):
                $n = ($row * 10 + $col) % 100;
                $label = str_pad($n, 2, '0', STR_PAD_LEFT);
            ?>
            <td><div class="jodi-num"><?php echo  $label ?></div></td>
            <?php endfor; ?>
        </tr>
        <tr>
            <?php for ($col = 1; $col <= 10; $col++):
                $n = ($row * 10 + $col) % 100;
                $label = str_pad($n, 2, '0', STR_PAD_LEFT);
            ?>
            <td class="jodi-input">
                <input type="number" min="0" placeholder=""
                    data-num="<?php echo  $label ?>"
                    id="j<?php echo  $label ?>"
                    <?php echo  $isClosed ? 'disabled' : '' ?>>
            </td>
            <?php endfor; ?>
        </tr>
        <?php endfor; ?>
        </tbody>
    </table>

    <!-- Andar Haruf -->
    <div class="haruf-label">Andar Haruf ( अंदर )</div>
    <table class="jodi-table" style="margin-bottom:14px">
        <tbody>
        <tr>
            <?php for ($d = 0; $d <= 9; $d++): ?>
            <td><div class="jodi-num"><?php echo  $d ?></div></td>
            <?php endfor; ?>
        </tr>
        <tr>
            <?php for ($d = 0; $d <= 9; $d++): ?>
            <td class="jodi-input">
                <input type="number" min="0" placeholder=""
                    data-num="<?php echo  $d ?>" data-type="andar" id="ah<?php echo  $d ?>"
                    <?php echo  $isClosed ? 'disabled' : '' ?>>
            </td>
            <?php endfor; ?>
        </tr>
        </tbody>
    </table>

    <!-- Bahar Haruf -->
    <div class="haruf-label">Bahar Haruf ( बाहर )</div>
    <table class="jodi-table" style="margin-bottom:60px">
        <tbody>
        <tr>
            <?php for ($d = 0; $d <= 9; $d++): ?>
            <td><div class="jodi-num"><?php echo  $d ?></div></td>
            <?php endfor; ?>
        </tr>
        <tr>
            <?php for ($d = 0; $d <= 9; $d++): ?>
            <td class="jodi-input">
                <input type="number" min="0" placeholder=""
                    data-num="<?php echo  $d ?>" data-type="bahar" id="bh<?php echo  $d ?>"
                    <?php echo  $isClosed ? 'disabled' : '' ?>>
            </td>
            <?php endfor; ?>
        </tr>
        </tbody>
    </table>
</div>

<!-- =========== CROSSING TAB =========== -->
<div class="g-panel" id="tab-crossing">
    <div class="input-card-custom">
        <div class="g-field">
            <label>ENTER DIGIT</label>
            <input type="tel" id="cr-digit" placeholder="e.g. 18">
        </div>
        <div class="g-field">
            <label>ENTER AMOUNT</label>
            <input type="tel" id="cr-amount" placeholder="Enter points">
        </div>
        <button type="button" class="g-add-btn" onclick="addCrossing()" <?php echo  $isClosed?'disabled':'' ?>>ADD</button>
    </div>

    <div class="g-table-wrap">
        <table class="g-table">
            <thead><tr><th>NUMBER</th><th>AMOUNT</th><th>ACTION</th></tr></thead>
            <tbody id="cr-tbody"></tbody>
        </table>
    </div>
</div>

<!-- =========== COPY PASTE TAB =========== -->
<div class="g-panel" id="tab-copypaste">
    <div class="input-card-custom">
        <div class="g-field">
            <label>ENTER DIGIT</label>
            <input type="tel" id="cp-digit" placeholder="e.g. 12">
        </div>
        <div class="g-field">
            <label>ENTER AMOUNT</label>
            <input type="tel" id="cp-amount" placeholder="Enter points">
        </div>
        <div class="palti-row">
            <div class="palti-opt" onclick="document.getElementById('paltiWith').checked=true; this.style.borderWidth='2px'; document.getElementById('paltiWithout').parentElement.style.borderWidth='1.5px';">
                <input type="radio" name="palti" id="paltiWith" value="with" checked onclick="event.stopPropagation()"> पलटी के साथ
            </div>
            <div class="palti-opt" onclick="document.getElementById('paltiWithout').checked=true; this.style.borderWidth='2px'; document.getElementById('paltiWith').parentElement.style.borderWidth='1.5px';">
                <input type="radio" name="palti" id="paltiWithout" value="without" onclick="event.stopPropagation()"> बिना पलटी के
            </div>
        </div>
        <button type="button" class="g-add-btn" onclick="addCopyPaste()" <?php echo  $isClosed?'disabled':'' ?>>ADD</button>
    </div>

    <div class="g-table-wrap">
        <table class="g-table">
            <thead><tr><th>NUMBER</th><th>AMOUNT</th><th>ACTION</th></tr></thead>
            <tbody id="cp-tbody"></tbody>
        </table>
    </div>
</div>

<!-- Footer -->
<div class="g-footer">
    <div class="amt"><span>₹</span><span id="footerAmt">0</span></div>
    <button type="button" class="g-play-btn" onclick="submitBids()" <?php echo  $isClosed?'disabled':'' ?>>PLAY</button>
</div>

<!-- Popup -->
<div class="gg-overlay" id="ggPopup">
    <div class="gg-box">
        <div class="gg-box-head">
            Alert
            <span class="gg-close" onclick="closePopup()">✕</span>
        </div>
        <div class="gg-box-body" id="ggMsg">-</div>
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
<!-- Success -->
<div class="gg-overlay" id="ggSuccess" style="background: rgba(0,0,0,0.96); flex-direction: column; z-index: 9999;">
    <div class="success-tick-wrapper">
      <svg class="checkmark-svg" viewBox="0 0 52 52">
        <path d="M14 27 l10 10 l20 -20" />
      </svg>
    </div>
    <div style="font-size:22px; font-weight:800; color:#1b5e20; margin-top:20px; letter-spacing:0.5px;">Bid Submitted!</div>
    <div style="font-size:14px; color:#555; margin-top:6px; font-weight:500;">Your bid has been placed successfully.</div>
</div>

<audio id="doneSound" src="sound/done.mp3" preload="auto"></audio>

<script>
const TOKEN    = <?php echo  json_encode($token) ?>;
const MIN_BID  = <?php echo  $minBid ?>;
const IS_CLOSED = <?php echo  $isClosed ? 'true' : 'false' ?>;
let wallet = <?php echo  $bal ?>;

/* ===== Tab switching ===== */
let activeTab = 'jodi';
function switchTab(name, el) {
    document.querySelectorAll('.g-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.g-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    el.classList.add('active');
    activeTab = name;
    updateFooter();
}

/* ===== Timer ===== */
let secsLeft = <?php echo  $secsLeft ?>;
function fmtTime(s) {
    let h = Math.floor(s/3600), m = Math.floor((s%3600)/60), ss = s%60;
    return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(ss).padStart(2,'0');
}
function tickTimer() {
    if(secsLeft > 0) secsLeft--;
    const main = document.getElementById('mainTimer');
    if(main) main.textContent = fmtTime(secsLeft);
    const bar = document.getElementById('barTimer');
    if(bar) bar.textContent = fmtTime(secsLeft);
}
tickTimer();
setInterval(tickTimer, 1000);

/* ===== Popup ===== */
function warn(msg) {
    document.getElementById('ggMsg').textContent = msg;
    document.getElementById('ggPopup').style.display = 'flex';
}
function closePopup() { document.getElementById('ggPopup').style.display = 'none'; }
document.getElementById('ggPopup').onclick = e => { if(e.target.id==='ggPopup') closePopup(); };

/* ===== Footer amount ===== */
function getTotal() {
    let t = 0;
    if(activeTab === 'jodi') {
        document.querySelectorAll('#jodiGrid input').forEach(i => t += (parseInt(i.value)||0));
        document.querySelectorAll('[data-type=andar]').forEach(i => t += (parseInt(i.value)||0));
        document.querySelectorAll('[data-type=bahar]').forEach(i => t += (parseInt(i.value)||0));
    } else if(activeTab === 'crossing') {
        document.querySelectorAll('#cr-tbody tr').forEach(tr => {
            t += parseFloat(tr.dataset.amt || 0);
        });
    } else {
        document.querySelectorAll('#cp-tbody tr').forEach(tr => {
            t += parseFloat(tr.dataset.amt || 0);
        });
    }
    return t;
}
function updateFooter() {
    document.getElementById('footerAmt').textContent = getTotal();
}

/* Jodi input listeners */
document.querySelectorAll('#jodiGrid input, [data-type=andar], [data-type=bahar]').forEach(inp => {
    inp.addEventListener('input', updateFooter);
});

/* ===== Crossing logic ===== */
function getCrossingCombos(digits) {
    // e.g. "18" → combos of {1,8}: 11,18,81,88
    let arr = digits.split('').filter(c => /\d/.test(c));
    let unique = [...new Set(arr)];
    let combos = [];
    for(let a of unique) for(let b of unique) combos.push(a + b);
    return [...new Set(combos)];
}
function addCrossing() {
    if(IS_CLOSED) return warn('Market Closed');
    let digit = document.getElementById('cr-digit').value.trim();
    let amt   = parseInt(document.getElementById('cr-amount').value) || 0;
    if(!digit) return warn('Enter digit');
    if(amt < MIN_BID) return warn('Minimum amount ₹' + MIN_BID);

    let combos = getCrossingCombos(digit);
    let tbody  = document.getElementById('cr-tbody');

    combos.forEach(c => {
        let tr = document.createElement('tr');
        tr.dataset.amt = amt;
        tr.innerHTML = `
            <td>${c}</td>
            <td>${amt}</td>
            <td><button class="g-del-btn" onclick="delRow(this,'cr-tbody')">Delete</button></td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('cr-digit').value = '';
    document.getElementById('cr-amount').value = '';
    updateFooter();
}

/* ===== Copy Paste logic ===== */
function addCopyPaste() {
    if(IS_CLOSED) return warn('Market Closed');
    let digit = document.getElementById('cp-digit').value.trim();
    let amt   = parseInt(document.getElementById('cp-amount').value) || 0;
    let palti = document.querySelector('input[name=palti]:checked').value;
    if(!digit) return warn('Enter digit');
    if(digit.length < 2) return warn('Enter 2-digit number');
    if(amt < MIN_BID) return warn('Minimum amount ₹' + MIN_BID);

    let tbody = document.getElementById('cp-tbody');
    let nums  = [digit.slice(-2).padStart(2,'0')];
    if(palti === 'with') {
        let rev = digit.slice(-2).split('').reverse().join('');
        if(rev !== nums[0]) nums.push(rev);
    }

    nums.forEach(n => {
        let tr = document.createElement('tr');
        tr.dataset.amt = amt;
        tr.innerHTML = `
            <td>${n}</td>
            <td>${amt}</td>
            <td><button class="g-del-btn" onclick="delRow(this,'cp-tbody')">Delete</button></td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('cp-digit').value = '';
    document.getElementById('cp-amount').value = '';
    updateFooter();
}

function delRow(btn, tbodyId) {
    btn.closest('tr').remove();
    updateFooter();
}

/* ===== Submit bids ===== */
let isSubmitting = false;
async function submitBids() {
    if(IS_CLOSED) return warn('Market Closed');
    if(isSubmitting) return;

    let bids = [], gameType = '';

    if(activeTab === 'jodi') {
        gameType = 'Jodi Digit';
        // Main jodi grid
        document.querySelectorAll('#jodiGrid input').forEach(inp => {
            let amt = parseInt(inp.value) || 0;
            if(amt > 0) bids.push({number: inp.dataset.num, amount: amt, game_type: 'Jodi Digit'});
        });
        // Andar Haruf
        document.querySelectorAll('[data-type=andar]').forEach(inp => {
            let amt = parseInt(inp.value) || 0;
            if(amt > 0) bids.push({number: inp.dataset.num, amount: amt, game_type: 'Andar Haruf'});
        });
        // Bahar Haruf
        document.querySelectorAll('[data-type=bahar]').forEach(inp => {
            let amt = parseInt(inp.value) || 0;
            if(amt > 0) bids.push({number: inp.dataset.num, amount: amt, game_type: 'Bahar Haruf'});
        });
    } else if(activeTab === 'crossing') {
        gameType = 'Crossing';
        document.querySelectorAll('#cr-tbody tr').forEach(tr => {
            bids.push({number: tr.cells[0].textContent, amount: parseFloat(tr.dataset.amt), game_type: 'Crossing'});
        });
    } else {
        let paltiVal = document.querySelector('input[name=palti]:checked').value;
        gameType = (paltiVal === 'with') ? 'With Palti' : 'Without Palti';
        document.querySelectorAll('#cp-tbody tr').forEach(tr => {
            bids.push({number: tr.cells[0].textContent, amount: parseFloat(tr.dataset.amt), game_type: gameType});
        });
    }

    if(!bids.length) return warn('Koi bid nahi add ki');

    let total = bids.reduce((s,b) => s + b.amount, 0);
    if(total > wallet) return warn('Insufficient balance');

    isSubmitting = true;
    try {
        let fd = new FormData();
        fd.append('bids', JSON.stringify(bids));
        fd.append('bid_token', TOKEN);
        fd.append('game_type', gameType);

        let res  = await fetch(window.location.href, {method:'POST', body:fd});
        let data = await res.json().catch(() => null);

        if(!data || data.ok !== true) {
            isSubmitting = false;
            return warn(data?.message || 'Submit failed');
        }

        let snd = document.getElementById('doneSound');
        if(snd) { snd.currentTime = 0; snd.play().catch(()=>{}); }
        document.getElementById('ggSuccess').style.display = 'flex';
        setTimeout(() => location.reload(), 1400);
    } catch(e) {
        isSubmitting = false;
        warn('Network error');
    }
}
</script>
</body>
</html>

