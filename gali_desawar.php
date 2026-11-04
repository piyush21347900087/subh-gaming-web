<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';
require_once 'includes/lang.php';
require_once 'includes/user_ping.php';
require_once 'includes/live_ping.php';
date_default_timezone_set('Asia/Kolkata');
$nowMinServer = (int) date('G') * 60 + (int) date('i');
$todayDow = (int) date('N'); // 1=Mon ... 7=Sun

/* ===================== SESSION CHECK ===================== */
if (!isset($_SESSION['user']['id'])) {
    header('Location: user_login.php');
    exit;
}
$uid = (int) $_SESSION['user']['id'];
$isDeactive = (isset($_SESSION['user']['is_active']) && (int) $_SESSION['user']['is_active'] === 0);

/* ===================== DB CONNECT ===================== */
try {
    $db = db();
    $db->query("SET time_zone = '+05:30'");
} catch (Throwable $e) {
    echo '<div style="font-family:sans-serif; text-align:center; padding:50px 20px; background:#111; color:#fff;"><h2>Server Maintenance</h2><p>Database is currently updating. Please refresh in a few seconds.</p></div>';
    exit;
}

/* ===================== USER INFO & BALANCE ===================== */
$resUser = $db->query("SELECT user_id, name, mobile_no, created_at, is_active FROM users WHERE id = $uid LIMIT 1");
$userRow = ($resUser && $resUser->num_rows > 0) ? $resUser->fetch_assoc() : [];
if (isset($userRow['is_active'])) {
    $isDeactive = ((int)$userRow['is_active'] === 0);
}

$code = $userRow['user_id'] ?? '';
if (preg_match('/^\d+$/', $code)) {
    $code = "KR" . str_pad($code, 2, "0", STR_PAD_LEFT);
} elseif (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $code, $m)) {
    $code = $m[1] . str_pad($m[2], 2, "0", STR_PAD_LEFT);
}
$LLCode = $code;
$_SESSION['user_id'] = $LLCode;

$userName = $userRow['name'] ?? 'User';
$userMobile = $userRow['mobile_no'] ?? '';
$userCreatedAt = $userRow['created_at'] ?? '';
$userSince = $userCreatedAt ? date('d-m-Y', strtotime($userCreatedAt)) : '';

// Admin WhatsApp
$waAdminNo = '';
try {
    $rsWA = $db->query("SELECT whatsapp_no_1, whatsapp_no_2 FROM admin_payment_details WHERE id = 1 LIMIT 1");
    if ($rsWA && $rowWA = $rsWA->fetch_assoc()) {
        $waAdminNo = !empty($rowWA['whatsapp_no_1']) ? $rowWA['whatsapp_no_1'] : ($rowWA['whatsapp_no_2'] ?? '');
    }
} catch (Throwable $e) {}
$waAdminDigits = preg_replace('/\D/', '', $waAdminNo);
if (strlen($waAdminDigits) === 10) { $waAdminDigits = '91' . $waAdminDigits; }
if (empty($waAdminDigits)) { $waAdminDigits = '917020691217'; }
$waSupportUrl = "https://wa.me/" . $waAdminDigits;

// Balance computation
$hasBonusCol = false;
$resB = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
if ($resB && $resB->num_rows > 0) {
    $hasBonusCol = true;
}

$depositDone = 0.0;
$st = $db->prepare("SELECT (IFNULL(SUM(amount),0) + " . ($hasBonusCol ? "IFNULL(SUM(bonus_amount),0)" : "0") . ") FROM deposit WHERE status='Done' AND user_id=?");
if ($st) {
    $st->bind_param('s', $code);
    $st->execute();
    $st->bind_result($depositDone);
    $st->fetch();
    $st->close();
}
$depositDone = (float)$depositDone;

$withdrawMinus = 0.0;
$st = $db->prepare("SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE user_id=? AND LOWER(status) IN ('pending','approved','done')");
if ($st) {
    $st->bind_param('s', $code);
    $st->execute();
    $st->bind_result($withdrawMinus);
    $st->fetch();
    $st->close();
}
$withdrawMinus = (float)$withdrawMinus;

$winBalance = 0.0;
$st = $db->prepare("SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id=?");
if ($st) {
    $st->bind_param('s', $code);
    $st->execute();
    $st->bind_result($winBalance);
    $st->fetch();
    $st->close();
}
$winBalance = (float)$winBalance;

$walletBalance = max(0.0, $depositDone - $withdrawMinus);
$totalBalance = (float)($depositDone - $withdrawMinus) + (float)$winBalance;
if ($totalBalance < 0) $totalBalance = 0;
$winBalance = (float)($totalBalance - $walletBalance);

/* ===================== GALI MARKETS FETCH ===================== */
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$res = $db->query("
    SELECT m.*, 
           (SELECT r.jodi FROM gali_result r WHERE r.market = m.market_name AND r.result_date = '$today' LIMIT 1) as live_result,
           (SELECT r.jodi FROM gali_result r WHERE r.market = m.market_name AND r.result_date = '$yesterday' LIMIT 1) as old_result
    FROM gali_markets m 
    WHERE m.status='active' AND m.is_published=1
    ORDER BY m.sort_order ASC
");
$markets = [];
while ($res && $row = $res->fetch_assoc()) {
    $markets[] = $row;
}

/* ===================== MONTHLY CHART DATA ===================== */
$chartYear  = isset($_GET['cy']) ? (int)$_GET['cy']  : (int)date('Y');
$chartMonth = isset($_GET['cm']) ? (int)$_GET['cm']  : (int)date('m');
if ($chartMonth < 1)  { $chartMonth = 12; $chartYear--; }
if ($chartMonth > 12) { $chartMonth = 1;  $chartYear++; }

$daysInMonth = (int)date('t', mktime(0, 0, 0, $chartMonth, 1, $chartYear));
$monthLabel  = date('F Y', mktime(0, 0, 0, $chartMonth, 1, $chartYear));

$chartMarkets = [];
foreach ($markets as $m) {
    $chartMarkets[] = $m['market_name'];
}

$monthStart = sprintf('%04d-%02d-01', $chartYear, $chartMonth);
$monthEnd   = sprintf('%04d-%02d-%02d', $chartYear, $chartMonth, $daysInMonth);
$chartData  = [];

$cRes = $db->query("
    SELECT result_date, market, jodi
    FROM gali_result
    WHERE result_date BETWEEN '$monthStart' AND '$monthEnd'
    ORDER BY result_date ASC
");
if ($cRes) {
    while ($crow = $cRes->fetch_assoc()) {
        $d = (int)date('j', strtotime($crow['result_date']));
        $chartData[$d][$crow['market']] = $crow['jodi'];
    }
}

/* ===================== GALI RATES ===================== */
$galiRates = [];
try {
    $ratesRes = $db->query("SELECT * FROM gali_rates ORDER BY id ASC");
    if ($ratesRes) {
        while ($row = $ratesRes->fetch_assoc()) {
            $name = '';
            $play = 10;
            $win  = 95;

            if (isset($row['rate_key'])) {
                $rk = strtolower(trim($row['rate_key']));
                if (strpos($rk, 'left') !== false) {
                    $name = 'HARUF ANDAR';
                } elseif (strpos($rk, 'right') !== false) {
                    $name = 'HARUF BAHAR';
                } elseif (strpos($rk, 'jodi') !== false) {
                    $name = 'JODI';
                } else {
                    $name = strtoupper(str_replace('_', ' ', $rk));
                }

                if (isset($row['rate_value'])) {
                    if (preg_match_all('/\d+/', $row['rate_value'], $m) && count($m[0]) >= 2) {
                        $play = (int)$m[0][0];
                        $win  = (int)$m[0][1];
                    } elseif (preg_match('/\d+/', $row['rate_value'], $m)) {
                        $win = (int)$m[0];
                    }
                }
            } else {
                if (isset($row['game_type']))       $name = $row['game_type'];
                elseif (isset($row['game_name']))   $name = $row['game_name'];
                elseif (isset($row['name']))        $name = $row['name'];
                elseif (isset($row['label']))       $name = $row['label'];
                elseif (isset($row['title']))       $name = $row['title'];

                if (isset($row['play_amount']))     $play = (int)$row['play_amount'];
                elseif (isset($row['from_amount'])) $play = (int)$row['from_amount'];
                elseif (isset($row['bet_amount']))  $play = (int)$row['bet_amount'];

                if (isset($row['win_amount']))      $win = (int)$row['win_amount'];
                elseif (isset($row['rate_value']))  $win = (int)$row['rate_value'];
                elseif (isset($row['payout']))      $win = (int)$row['payout'];
                elseif (isset($row['return']))      $win = (int)$row['return'];
            }

            if (empty($name) || $name === 'GAME') {
                continue;
            }

            if (strtoupper(trim($name)) === 'JANTRI') { $name = 'JODI'; }

            $galiRates[] = ['game_type' => $name, 'play_amount' => $play, 'win_amount' => $win];
        }
    }
} catch (Throwable $e) {}

if (empty($galiRates)) {
    $galiRates = [
        ['game_type' => 'HARUF ANDAR', 'play_amount' => 10, 'win_amount' => 95],
        ['game_type' => 'HARUF BAHAR', 'play_amount' => 10, 'win_amount' => 95],
        ['game_type' => 'JODI',        'play_amount' => 10, 'win_amount' => 950],
    ];
}
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Desawar | SUBH GAMING</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Poppins:wght@300;400;500;600;700;800;900&family=Roboto:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-brands/css/uicons-brands.css">
    <style>
        :root {
            --primary-bg: #000000;
            --card-bg: #ffffff;
            --accent-main: #000000;
            --accent-red: #e60000;
            --text-main: #ffffff;
            --text-dim: #a0a0a0;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
            --border-radius: 15px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        html {
            background-color: #000000;
            scroll-behavior: smooth;
        }

        body {
            background: #000000 !important;
            color: #ffffff;
            padding-bottom: 100px;
            overflow-x: clip;
        }

        /* ── Sticky Freeze Header Area (Header + Marquee + Action Bar) ── */
        .sticky-top-area {
            position: sticky;
            top: 0;
            z-index: 1000;
            background-color: #8b0000 !important;
            background-image: 
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px),
                repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        }

        /* ── Header Matching Dashboard ── */
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
            position: relative;
            z-index: 10;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            border-bottom: none !important;
        }

        .header-left,
        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title-flash {
            color: #ffffff;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
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

        .bell-icon-btn {
            background: transparent;
            color: #ffc107 !important;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
            text-decoration: none;
            border: none;
            font-size: 22px;
        }

        .back-btn, #menuToggle {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #e53935;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 15px !important;
            cursor: pointer;
            border: none;
            flex-shrink: 0;
        }

        /* ── Marquee ── */
        .marquee-wrap {
            background: #ffffff;
            padding: 8px;
            color: #000000;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            overflow: hidden;
            white-space: nowrap;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .marquee-content {
            display: inline-block;
            animation: marquee 15s linear infinite;
        }

        @keyframes marquee {
            0% { transform: translateX(100vw); }
            100% { transform: translateX(-100%); }
        }

        /* ── Content Container ── */
        .content {
            max-width: 480px;
            margin: 0 auto;
            padding: 10px 15px 15px 15px;
        }

        /* ── Action Buttons (Matching Dashboard) ── */
        .sticky-action-bar {
            max-width: 480px;
            margin: 0 auto;
            padding: 10px 15px 2px 15px;
            background: #000000 !important;
        }

        .action-btns-row {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
        }

        .action-btn-custom {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            padding: 2px 14px 2px 2px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 900;
            color: #ffffff;
            text-decoration: none;
            text-transform: uppercase;
            box-shadow: 0 3px 6px rgba(0,0,0,0.12);
            transition: transform 0.15s ease;
        }

        .action-btn-custom:active {
            transform: scale(0.97);
        }

        .btn-add-money-green {
            background: #00a65a;
        }

        .btn-withdraw-red {
            background: #ff0000;
        }

        .icon-circle-custom {
            width: 34px;
            height: 34px;
            background: #ffffff;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .btn-add-money-green .icon-circle-custom i { color: #00a65a; font-size: 14px; }
        .btn-withdraw-red .icon-circle-custom i { color: #ff0000; font-size: 14px; }

        .btn-play-pill-custom {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            padding: 2px 14px 2px 2px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 800;
            color: #111111;
            background: #ffffff;
            border: 1.5px solid #ff4d4d;
            text-decoration: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.15s ease;
        }

        .btn-play-pill-custom:active {
            transform: scale(0.97);
        }

        .icon-square-custom {
            width: 34px;
            height: 34px;
            background: #ff4d4d;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 18px;
            flex-shrink: 0;
        }

        .btn-text-center {
            flex: 1;
            text-align: center;
            margin-right: 32px;
            white-space: nowrap;
            font-family: 'Roboto', sans-serif;
            font-size: 18px;
        }

        @media (max-width: 480px) {
            .content {
                padding: 10px;
            }
            .btn-text-center {
                margin-right: 0px;
                font-size: 15px;
            }
            .action-btn-custom, .btn-play-pill-custom {
                gap: 5px;
            }
            .chart-img-icon {
                width: 30px;
                height: 30px;
            }
            .market-number-row {
                gap: 6px;
            }
            .gali-res-badge {
                padding: 3px 5px;
            }
            .gali-res-badge .val {
                font-size: 13.5px;
            }
            .status-text-running, .status-text-closed {
                font-size: 9.5px;
            }
            .gali-chart-hdr {
                padding: 8px;
            }
            .chart-hdr-title {
                font-size: 12px;
            }
            .gali-chart-table th {
                font-size: 8px;
                padding: 4px 1px;
                letter-spacing: 0;
            }
            .gali-chart-table th.th-date,
            .gali-chart-table td.td-date {
                width: 30px;
                padding: 4px 0;
            }
            .td-date .d-num {
                font-size: 10px;
            }
            .td-date .d-name {
                font-size: 7px;
            }
            .jodi-pill {
                font-size: 10px;
                padding: 1px 2px;
                min-width: 18px;
                border-radius: 3px;
            }
        }

        /* ── Market Cards ── */
        .market-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .market-card-custom {
            background: #ffffff;
            border-radius: 12px;
            display: flex;
            box-shadow: 0 3px 10px rgba(0,0,0,0.07);
            border: 1px solid #e0e0e0;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .market-card-custom:active {
            transform: scale(0.98);
        }

        .market-card-main-info {
            flex: 1;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        /* 1. Left Dedicated Chart Box (Matching Right Play Box) */
        .market-card-left {
            width: 65px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            flex-shrink: 0;
            align-self: stretch;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        .market-card-left:active { transform: scale(0.95); }

        .chart-img-icon {
            width: 28px;
            height: 28px;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.12));
        }

        .chart-col-label {
            font-size: 13px;
            font-weight: 600;
            color: #111111;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-family: 'Poppins', sans-serif;
            line-height: 1;
        }

        /* 2. Center Column: Title & 3D OLD/NEW Badges */
        .market-col-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 2px;
            flex: 1;
            min-width: 0;
        }

        .market-card-title {
            font-size: 15px;
            font-weight: 900;
            color: #7f0000;
            text-transform: uppercase;
            letter-spacing: -0.2px;
            margin: 0;
            font-family: 'Poppins', sans-serif;
            line-height: 1.2;
            text-align: center;
            width: 100%;
        }

        .gali-3d-row-sm {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 2px 0;
        }

        .gali-3d-col-sm {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .box-3d-sm {
            min-width: 42px;
            height: 30px;
            padding: 0 6px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 900;
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            margin-bottom: 2px;
        }

        .box-red-sm {
            background: linear-gradient(180deg, #ff4747 0%, #d32f2f 100%);
            box-shadow: 0 2.5px 0 #9a0007, 0 3px 6px rgba(211, 47, 47, 0.25);
        }

        .box-green-sm {
            background: linear-gradient(180deg, #2ecc71 0%, #27ae60 100%);
            box-shadow: 0 2.5px 0 #1e8449, 0 3px 6px rgba(39, 174, 96, 0.25);
        }

        .box-grey-sm {
            background: linear-gradient(180deg, #95a5a6 0%, #7f8c8d 100%);
            box-shadow: 0 2.5px 0 #5c6768;
        }

        .box-grey-light-sm {
            background: linear-gradient(180deg, #bdc3c7 0%, #95a5a6 100%);
            box-shadow: 0 2.5px 0 #7f8c8d;
        }

        .box-lbl-sm {
            font-size: 9.5px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-family: 'Poppins', sans-serif;
        }

        .lbl-red { color: #ff005c; }
        .lbl-green { color: #00b84c; }
        .lbl-grey { color: #888888; }

        .gali-3d-arrow-sm {
            font-size: 11px;
            color: #7f8c8d;
            font-weight: 900;
            margin-top: -10px;
        }

        /* 3. Right Column: Clock & Times */
        .market-col-right {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
        }

        .clock-icon {
            font-size: 16px;
            flex-shrink: 0;
            transform: translateX(-5px);
        }

        .clock-open { color: #00b894; }
        .clock-closed { color: #ff6b4a; }

        .market-card-times {
            display: flex;
            flex-direction: column;
            font-size: 9.5px;
            font-weight: 400;
            font-family: 'Poppins', sans-serif;
            color: #333333;
            line-height: 1.25;
            text-align: left;
            white-space: nowrap;
        }

        .market-card-times .time-val {
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            color: #222222;
            font-size: 9.5px;
        }

        /* 4. Play Section (Right 25%) */
        .market-card-right {
            width: 65px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            flex-shrink: 0;
            align-self: stretch;
        }

        .play-bg-open {
            background-color: #c8f7c5;
        }

        .play-bg-closed {
            background-color: #ffb8b8;
        }

        .play-triangle {
            font-size: 32px;
            color: #111111;
        }

        .play-label {
            font-size: 14px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            color: #111111;
            text-transform: uppercase;
        }

        /* ── Monthly Chart Section ── */
        .gali-chart-section {
            margin-top: 25px;
            margin-bottom: 25px;
            width: 100%;
        }

        .gali-chart-hdr {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #8b0000;
            background-image: 
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px),
                repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px);
            border-radius: 12px 12px 0 0;
            padding: 9px 10px;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .chart-nav-btn {
            color: #ffffff;
            text-decoration: none;
            font-size: 16px;
            padding: 3px 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .chart-nav-btn:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .chart-nav-btn.disabled {
            color: rgba(255, 255, 255, 0.3);
            pointer-events: none;
        }

        .chart-hdr-title {
            font-weight: 800;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            display: flex;
            align-items: center;
            gap: 6px;
            color: #ffffff;
        }

        .gali-chart-table-wrap {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            background: #ffffff;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            border: 1px solid #e0e0e0;
            border-top: none;
        }

        .gali-chart-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin: 0;
        }

        .gali-chart-table thead tr {
            background: #1a1a2e;
        }

        .gali-chart-table th {
            padding: 6px 2px;
            color: #ffd700;
            font-size: 9px;
            font-weight: 800;
            text-align: center;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            text-transform: uppercase;
            letter-spacing: 0.2px;
            white-space: normal;
            word-break: break-word;
            line-height: 1.15;
            vertical-align: middle;
        }

        .gali-chart-table th:last-child {
            border-right: none;
        }

        .gali-chart-table th.th-date {
            width: 36px;
            color: #ffd700;
        }

        .gali-chart-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
        }

        .gali-chart-table tbody tr:nth-child(even) {
            background: #fafafa;
        }

        .gali-chart-table tbody tr.row-today {
            background: #fff3f6;
            border-left: 3px solid #8b0000;
        }

        .gali-chart-table td.td-date {
            padding: 5px 1px;
            text-align: center;
            border-right: 1px solid #eeeeee;
            width: 36px;
            vertical-align: middle;
        }

        .td-date .d-num {
            font-size: 11px;
            font-weight: 800;
            color: #111111;
            line-height: 1;
        }

        .gali-chart-table tbody tr.row-today .td-date .d-num {
            color: #8b0000;
        }

        .td-date .d-name {
            font-size: 7.5px;
            color: #777777;
            font-weight: 600;
            line-height: 1;
            margin-top: 1px;
            text-transform: uppercase;
        }

        .gali-chart-table td.td-res {
            padding: 4px 1px;
            text-align: center;
            border-right: 1px solid #eeeeee;
            vertical-align: middle;
        }

        .gali-chart-table td.td-res:last-child {
            border-right: none;
        }

        .jodi-pill {
            display: inline-block;
            background: #8b0000;
            color: #ffffff;
            font-weight: 800;
            font-size: 11px;
            border-radius: 4px;
            padding: 2px 3px;
            letter-spacing: 0.3px;
            font-family: 'Montserrat', sans-serif;
            line-height: 1.1;
            min-width: 20px;
            text-align: center;
            box-sizing: border-box;
        }

        .dash-empty {
            color: #cccccc;
            font-size: 12px;
            line-height: 1;
        }

        .closed-text {
            color: #e53935;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            line-height: 1;
        }

        .chart-footer-note {
            text-align: center;
            margin: 8px 0 4px;
            font-size: 11px;
            color: #888888;
            font-weight: 500;
        }

        /* ── Scroll To Top Button ── */
        .back-to-top {
            position: fixed;
            bottom: 85px;
            right: 16px;
            width: 46px;
            height: 46px;
            background: linear-gradient(135deg, #e53935 0%, #8b0000 100%);
            color: #ffffff;
            border: 2px solid #ffffff;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            z-index: 999;
            box-shadow: 0 4px 14px rgba(139, 0, 0, 0.45);
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .back-to-top i {
            animation: bounceUp 1.2s infinite ease-in-out;
        }

        @keyframes bounceUp {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        /* ── Closed Market Popup ── */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.65);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 5000;
            backdrop-filter: blur(2px);
        }

        .popup-overlay.show {
            display: flex;
        }

        /* ── Bottom Menu (Matching Dashboard) ── */
        nav.bottom-menu {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 62px;
            background-color: #000000 !important;
            background-image: none !important;
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 2000;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.5);
            border-top: 1px solid #1a1a1a !important;
        }

        .menu-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            text-decoration: none;
            flex: 1;
            height: 100%;
            transition: all 0.2s ease;
        }

        .menu-item img.nav-icon {
            width: 24px;
            height: 24px;
            object-fit: contain;
            margin-bottom: 2px;
            filter: brightness(0) invert(1);
            transition: filter 0.2s ease;
        }

        .menu-item span {
            font-size: 11px;
            font-weight: 600;
            color: #ffffff;
            font-family: 'Roboto', sans-serif;
        }

        .menu-item.home-item {
            position: relative;
            top: -14px;
            flex: 1.2;
            animation: floatHome 2s ease-in-out infinite;
        }

        @keyframes floatHome {
            0%, 100% { transform: translateY(0px); }
            50%       { transform: translateY(-6px); }
        }

        .menu-item.home-item .home-circle {
            width: 52px;
            height: 52px;
            background: #ff2a2a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            border: none;
            margin-bottom: 0;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .menu-item.home-item:active .home-circle {
            transform: scale(0.95);
        }

        .menu-item.home-item img.nav-icon {
            width: 26px;
            height: 26px;
            margin-bottom: 0;
            filter: brightness(0) invert(1) !important;
        }

        .menu-item.home-item span {
            display: none;
        }

        /* ── Support Popup Modal ── */
        .support-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            width: 100vw; height: 100vh;
            background: rgba(0, 0, 0, 0.6);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .support-modal-overlay.show {
            display: flex !important;
            opacity: 1;
        }

        .support-modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 320px;
            border-radius: 14px;
            padding: 24px 20px 20px 20px;
            position: relative;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            text-align: center;
            transform: scale(0.85);
            transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-sizing: border-box;
        }

        .support-modal-overlay.show .support-modal-card {
            transform: scale(1);
        }

        .support-modal-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ff4747;
            border: none;
            color: #ffffff;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(255, 71, 71, 0.35);
            transition: transform 0.15s ease;
        }

        .support-modal-title {
            font-size: 20px;
            font-weight: 800;
            color: #1a202c;
            margin-bottom: 20px;
            font-family: 'Poppins', sans-serif;
            letter-spacing: -0.2px;
        }

        .support-modal-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .support-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px 10px 14px 10px;
            border-radius: 12px;
            text-decoration: none;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transition: transform 0.15s ease;
        }

        .support-btn:active {
            transform: scale(0.95);
        }

        .support-btn-whatsapp {
            background: #00b84c;
        }

        .support-btn-chat {
            background: #6b7c93;
        }

        .support-btn-icon {
            font-size: 32px;
            line-height: 1;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .support-btn-whatsapp .support-btn-icon i {
            color: #ffffff;
            font-size: 34px;
        }

        .support-btn span {
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.2;
            font-family: 'Poppins', sans-serif;
        }

        /* Red Blinking Dot Animation for Support */
        @keyframes supportRedDotBlink {
            0% {
                transform: scale(0.9);
                box-shadow: 0 0 0 0 rgba(255, 23, 68, 0.9);
                opacity: 1;
            }
            50% {
                transform: scale(1.25);
                box-shadow: 0 0 0 6px rgba(255, 23, 68, 0);
                opacity: 0.9;
            }
            100% {
                transform: scale(0.9);
                box-shadow: 0 0 0 0 rgba(255, 23, 68, 0);
                opacity: 1;
            }
        }

        .menu-item#liveSupportBtn {
            position: relative;
        }

        .support-dot-badge {
            position: absolute;
            top: 4px;
            right: calc(50% - 15px);
            width: 10px;
            height: 10px;
            background: #ff1744;
            border: 2px solid #ffffff;
            border-radius: 50%;
            display: none;
            z-index: 10;
            pointer-events: none;
            animation: supportRedDotBlink 1.2s infinite;
        }

        .has-support-unread .support-dot-badge,
        .menu-item#liveSupportBtn.has-support-unread .support-dot-badge {
            display: block !important;
        }

        .chat-modal-dot-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 12px;
            height: 12px;
            background: #ff1744;
            border: 2px solid #ffffff;
            border-radius: 50%;
            display: none;
            z-index: 10;
            pointer-events: none;
            animation: supportRedDotBlink 1.2s infinite;
        }

        .has-support-unread .chat-modal-dot-badge,
        .support-btn-chat.has-support-unread .chat-modal-dot-badge,
        .support-modal-overlay.has-support-unread .chat-modal-dot-badge {
            display: block !important;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body data-server-min="<?= $nowMinServer ?>" data-deactive="<?= $isDeactive ? '1' : '0' ?>" data-LL="<?= $LLCode ?>">

    <!-- Sticky Freeze Header Area (Header + Marquee + Action Buttons) -->
    <div class="sticky-top-area">
        <!-- Header -->
        <header>
            <div class="header-left">
                <a class="back-btn" href="user_dashboard.php" title="Back">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <div class="header-title-flash">
                    <img src="icons/gold_coin.svg" alt="coin" style="width: 18px; height: 18px; vertical-align: middle; margin-top: -3px;">
                    DESAWAR
                    <img src="icons/gold_coin.svg" alt="coin" style="width: 18px; height: 18px; vertical-align: middle; margin-top: -3px;">
                </div>
            </div>
            <div class="header-right">
                <a href="deposit.php" class="wallet-display">
                    <i class="fa-solid fa-wallet"></i>
                    <span id="walletVal"><?= (int)$totalBalance ?></span>
                </a>
                <a href="notice_board.php" class="bell-icon-btn" id="notifyLink" style="position: relative;">
                    <i class="fa-solid fa-bell"></i>
                    <div id="notifyDot" style="display: none; position: absolute; top: 0; right: 0; width: 8px; height: 8px; background: #ff1f50; border-radius: 50%;"></div>
                </a>
            </div>
        </header>

        <!-- Marquee -->
        <div class="marquee-wrap">
            <div class="marquee-content">
                <div style="display:flex; align-items:center; gap:16px;">
                    <?php
                    function getRateColor($gameType, $index) {
                        $gt = strtoupper(trim($gameType));
                        if (strpos($gt, 'ANDAR') !== false) return '#2ecc71';
                        if (strpos($gt, 'BAHAR') !== false) return '#3498db';
                        if (strpos($gt, 'JODI') !== false)  return '#e67e22';
                        if (strpos($gt, 'HARUF') !== false) return '#2ecc71';
                        $colors = ['#2ecc71', '#3498db', '#e67e22', '#e74c3c', '#9b59b6', '#1abc9c'];
                        return $colors[$index % count($colors)];
                    }
                    ?>
                    <?php for ($loop = 0; $loop < 3; $loop++): ?>
                        <?php foreach ($galiRates as $idx => $gr): 
                            $rateColor = getRateColor($gr['game_type'], $idx);
                        ?>
                            <span style="color:#000000; font-weight:900; text-transform:uppercase; font-size:13px; font-family:'Poppins',sans-serif;"><?= htmlspecialchars($gr['game_type']) ?></span> 
                            <span style="color:<?= $rateColor ?>; font-weight:900; font-size:13px; font-family:'Poppins',sans-serif;"><?= $gr['play_amount'] ?> X <?= $gr['win_amount'] ?></span>
                            <span style="color:#cccccc; font-weight:700;">|</span>
                        <?php endforeach; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Sticky Action Bar Buttons -->
        <div class="sticky-action-bar">
            <div class="action-btns-row">
                <a href="user_wining.php" class="btn-play-pill-custom">
                    <div class="icon-square-custom"><img src="icons/win_history.svg" alt="Win History" style="width: 20px; height: 20px; filter: brightness(0) invert(1);"></div>
                    <span class="btn-text-center">WIN HISTORY</span>
                </a>
                <a href="user_bid_history.php" class="btn-play-pill-custom">
                    <div class="icon-square-custom"><img src="icons/bid.svg" alt="Bid History" style="width: 20px; height: 20px; filter: brightness(0) invert(1);"></div>
                    <span class="btn-text-center">BID HISTORY</span>
                </a>
            </div>
        </div>
    </div>

    <div class="content">

        <!-- Market List -->
        <div class="market-list">
            <?php if (empty($markets)): ?>
                <div style="text-align:center; padding:40px 20px; color:#777; font-weight:600; background:#fff; border-radius:12px; border:1px solid #e0e0e0;">
                    No Gali Desawar markets currently available.
                </div>
            <?php else: ?>
                <?php foreach ($markets as $m):
                    $name = htmlspecialchars($m['market_name']);

                    // Determine Status
                    $day = date('w'); // 0=Sun, 6=Sat
                    $isHoliday = false;
                    $holidayLabel = '';

                    if ($m['today_closed']) {
                        $isHoliday = true;
                        $holidayLabel = $m['custom_close_text'] ?: 'Today Closed';
                    } elseif ($day == 6 && $m['saturday_closed']) {
                        $isHoliday = true;
                        $holidayLabel = 'Saturday Holiday';
                    } elseif ($day == 0 && $m['sunday_closed']) {
                        $isHoliday = true;
                        $holidayLabel = 'Sunday Holiday';
                    }

                    $isOpen = true;
                    $isTimeOut = false;

                    if ($isHoliday) {
                        $isOpen = false;
                    } else {
                        $now = date('H:i');
                        $closingStart = $m['close_time_start'] ? date('H:i', strtotime($m['close_time_start'])) : '23:59';
                        $closingEnd = $m['close_time_end'] ? date('H:i', strtotime($m['close_time_end'])) : '00:00';

                        if ($closingStart < $closingEnd) {
                            if ($now >= $closingStart && $now <= $closingEnd) {
                                $isTimeOut = true;
                            }
                        } else {
                            if ($now >= $closingStart || $now <= $closingEnd) {
                                $isTimeOut = true;
                            }
                        }

                        if ($isTimeOut) {
                            $isOpen = false;
                        }
                    }

                    $link = 'gali_game.php?market=' . urlencode($m['market_name']);
                ?>
                    <div class="market-card-custom <?= !$isOpen || $isDeactive ? 'closed-market' : 'open-market' ?> <?= $isHoliday ? 'holiday-market' : '' ?>"
                         data-holiday="<?= $isHoliday ? '1' : '0' ?>"
                         onclick="handleGaliMarketClick(this, '<?= $link ?>', <?= $isOpen && !$isDeactive ? 'true' : 'false' ?>)">
                        
                        <!-- 1. Left Section (Chart Box Area - Matching Play Box) -->
                        <a href="user_panel_chart.php?market=<?= urlencode($m['market_name']) ?>&type=jodi"
                           onclick="event.stopPropagation();"
                           class="market-card-left <?= $isOpen && !$isDeactive ? 'play-bg-open' : 'play-bg-closed' ?>" title="View Chart">
                            <img src="images/chart_icon.png" alt="Chart" class="chart-img-icon">
                            <span class="chart-col-label">Chart</span>
                        </a>

                        <!-- Main Info Area (Center Info & Right Times) -->
                        <div class="market-card-main-info">
                            <!-- 2. Center: Title & Compact 3D OLD/NEW Badges -->
                            <div class="market-col-center">
                                <h3 class="market-card-title"><?= $name ?></h3>

                                <div class="gali-3d-row-sm">
                                    <!-- OLD -->
                                    <div class="gali-3d-col-sm">
                                        <div class="box-3d-sm <?= $isOpen && !$isDeactive ? 'box-red-sm' : 'box-grey-sm' ?>">
                                            <?= htmlspecialchars($m['old_result'] ?: '**') ?>
                                        </div>
                                        <div class="box-lbl-sm <?= $isOpen && !$isDeactive ? 'lbl-red' : 'lbl-grey' ?>">OLD</div>
                                    </div>

                                    <!-- Arrow -->
                                    <div class="gali-3d-arrow-sm">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </div>

                                    <!-- NEW -->
                                    <div class="gali-3d-col-sm">
                                        <div class="box-3d-sm <?= $isOpen && !$isDeactive ? 'box-green-sm' : 'box-grey-light-sm' ?>">
                                            <?= htmlspecialchars($m['live_result'] ?: '**') ?>
                                        </div>
                                        <div class="box-lbl-sm <?= $isOpen && !$isDeactive ? 'lbl-green' : 'lbl-grey' ?>">NEW</div>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Right: Clock & Times -->
                            <div class="market-col-right">
                                <i class="fi fi-rr-alarm-clock clock-icon <?= $isOpen && !$isDeactive ? 'clock-open' : 'clock-closed' ?>"></i>
                                <div class="market-card-times">
                                    <div>Open Bids -</div>
                                    <div class="time-val"><?= date('h:i A', strtotime($m['result_time'])) ?></div>
                                    <div style="margin-top: 2px;">Close Bids -</div>
                                    <div class="time-val"><?= date('h:i A', strtotime($m['close_time_start'] ?? '00:00')) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Section (Play Button Area) -->
                        <div class="market-card-right <?= $isOpen && !$isDeactive ? 'play-bg-open' : 'play-bg-closed' ?>">
                            <i class="fa-solid fa-play play-triangle"></i>
                            <span class="play-label">Play</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ===== MONTHLY CHART SECTION ===== -->
        <div class="gali-chart-section">
            <!-- Month Selector Header -->
            <?php
                $prevMonth = $chartMonth - 1; $prevYear = $chartYear;
                if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
                $nextMonth = $chartMonth + 1; $nextYear = $chartYear;
                if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
                $isCurrentOrFuture = ($nextYear > (int)date('Y') || ($nextYear == (int)date('Y') && $nextMonth > (int)date('m')));
            ?>
            <div class="gali-chart-hdr">
                <a href="?cy=<?= $prevYear ?>&cm=<?= $prevMonth ?>" class="chart-nav-btn">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <span class="chart-hdr-title">
                    <i class="fas fa-calendar-alt" style="color:#ffd700;"></i> <?= $monthLabel ?>
                </span>
                <?php if (!$isCurrentOrFuture): ?>
                    <a href="?cy=<?= $nextYear ?>&cm=<?= $nextMonth ?>" class="chart-nav-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="chart-nav-btn disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>

            <!-- Chart Table Container -->
            <div class="gali-chart-table-wrap">
                <table class="gali-chart-table">
                    <thead>
                        <tr>
                            <th class="th-date">DATE</th>
                            <?php foreach ($chartMarkets as $mn): ?>
                                <th><?= htmlspecialchars($mn) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($day = 1; $day <= $daysInMonth; $day++):
                            $isToday = ($day == (int)date('j') && $chartMonth == (int)date('m') && $chartYear == (int)date('Y'));
                            $fullDate = sprintf('%04d-%02d-%02d', $chartYear, $chartMonth, $day);
                            $dayName = date('D', strtotime($fullDate));
                        ?>
                        <tr class="">
                            <td class="td-date">
                                <div class="d-num"><?= $day ?></div>
                                <div class="d-name"><?= $dayName ?></div>
                            </td>
                            <?php foreach ($chartMarkets as $mn): ?>
                                <?php $jodi = $chartData[$day][$mn] ?? ''; ?>
                                <td class="td-res">
                                    <?php if ($jodi !== ''): ?>
                                        <span class="jodi-pill"><?= htmlspecialchars($jodi) ?></span>
                                    <?php elseif ($day == $daysInMonth): ?>
                                        <span class="closed-text">CLOSED</span>
                                    <?php else: ?>
                                        <span class="dash-empty">–</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <div class="chart-footer-note">
                <?= $monthLabel ?> &bull; <?= $daysInMonth ?> Days
            </div>
        </div>
        <!-- ===== END MONTHLY CHART ===== -->
    </div>

    <!-- Closed Popup Modal (Matching Dashboard) -->
    <div id="closedPopup" class="popup-overlay" onclick="closeClosedPopup(event)">
        <div class="popup-box-slim" onclick="event.stopPropagation()" style="background: linear-gradient(135deg, #8b0000 0%, #5a0000 100%); border: 1.5px solid #d4af37; border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.5); max-width: 90%; margin: 0 auto; transform: scale(0.9); transition: transform 0.3s ease;" id="closedPopupInnerBox">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="background: rgba(255,215,0,0.2); border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fa-solid fa-lock" style="color: #ffd700; font-size: 14px;"></i>
                </div>
                <div style="color: #ffffff; font-weight: 700; font-size: 13px; text-transform: uppercase; line-height: 1.2; font-family: 'Poppins', sans-serif;" id="closedPopupMessage">
                    THIS GAME IS CURRENTLY CLOSED
                </div>
            </div>
        </div>
    </div>

    <!-- Scroll To Top Button -->
    <button class="back-to-top" id="scrollTopBtn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="Back to top">
        <i class="fa-solid fa-angles-up"></i>
    </button>

    <!-- Bottom Navigation (Matching Dashboard) -->
    <nav class="bottom-menu">
        <a href="user_result.php" class="menu-item">
            <img src="icons/bid_icon.svg" alt="My Bids" class="nav-icon">
            <span><?= __("My Bids") ?></span>
        </a>
        <a href="price_chart.php" class="menu-item">
            <img src="icons/coin_icon.svg" alt="Game Rates" class="nav-icon">
            <span><?= __("Game Rates") ?></span>
        </a>
        <a href="user_dashboard.php" class="menu-item home-item">
            <div class="home-circle">
                <img src="icons/home_icon.svg" alt="Home" class="nav-icon">
            </div>
            <span><?= __("HOME") ?></span>
        </a>
        <a href="user_profile.php" class="menu-item">
            <img src="icons/profile_icon.svg" alt="Account" class="nav-icon">
            <span><?= __("Account") ?></span>
        </a>
        <a href="javascript:void(0)" onclick="openSupportModal()" class="menu-item" id="liveSupportBtn" style="position: relative;">
            <img src="icons/chat_icon.svg" alt="Support" class="nav-icon">
            <div class="support-dot-badge"></div>
            <span><?= __("Support") ?></span>
        </a>
    </nav>

    <!-- SUPPORT MODAL -->
    <div class="support-modal-overlay" id="supportModalOverlay" onclick="closeSupportModal(event)">
        <div class="support-modal-card" onclick="event.stopPropagation()">
            <button type="button" class="support-modal-close" onclick="closeSupportModal(event)" title="Close">
                <i class="fas fa-times"></i>
            </button>
            <div class="support-modal-title">Support</div>
            <div class="support-modal-buttons">
                <a href="<?= htmlspecialchars($waSupportUrl) ?>" target="_blank" rel="noopener" class="support-btn support-btn-whatsapp">
                    <div class="support-btn-icon">
                        <i class="fa-brands fa-whatsapp"></i>
                    </div>
                    <span>WhatsApp</span>
                </a>
                <a href="live_chat.php" class="support-btn support-btn-chat" id="modalChatBtn" style="position: relative;">
                    <div class="support-btn-icon" style="position: relative;">
                        <img src="icons/live_chat.svg" alt="Chat with Us" style="width: 32px; height: 32px; object-fit: contain;">
                        <div class="chat-modal-dot-badge"></div>
                    </div>
                    <span>Chat with Us</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Support Modal Functions
        function openSupportModal() {
            var modal = document.getElementById('supportModalOverlay');
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        }
        function closeSupportModal(e) {
            if (e && e.stopPropagation) e.stopPropagation();
            var modal = document.getElementById('supportModalOverlay');
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
        window.openSupportModal = openSupportModal;
        window.closeSupportModal = closeSupportModal;

        function refreshSupportDot() {
            var liveSupportBtn = document.getElementById('liveSupportBtn');
            var modalOverlay = document.getElementById('supportModalOverlay');
            var modalChatBtn = document.getElementById('modalChatBtn') || document.querySelector('.support-btn-chat');
            try {
                fetch('get_support_unread.php?_=' + Date.now(), {
                    cache: 'no-store',
                    credentials: 'same-origin'
                }).then(function(res) {
                    return res.json();
                }).then(function(data) {
                    var unread = parseInt(data.unread, 10) || 0;
                    if (unread > 0) {
                        if (liveSupportBtn) liveSupportBtn.classList.add('has-support-unread');
                        if (modalOverlay) modalOverlay.classList.add('has-support-unread');
                        if (modalChatBtn) modalChatBtn.classList.add('has-support-unread');
                    } else {
                        if (liveSupportBtn) liveSupportBtn.classList.remove('has-support-unread');
                        if (modalOverlay) modalOverlay.classList.remove('has-support-unread');
                        if (modalChatBtn) modalChatBtn.classList.remove('has-support-unread');
                    }
                }).catch(function(e) {});
            } catch (e) {}
        }
        document.addEventListener('DOMContentLoaded', function() {
            refreshSupportDot();
            setInterval(refreshSupportDot, 3000);
        });

        // Market Click Handler
        function handleGaliMarketClick(card, url, isOpen) {
            if (document.body.getAttribute('data-deactive') === '1') {
                showClosedPopup(false, 'Your account is deactivated.');
                return;
            }
            var isHoliday = card.getAttribute('data-holiday') === '1';
            if (!isOpen || isHoliday) {
                showClosedPopup(isHoliday);
            } else {
                window.location.href = url;
            }
        }

        // Closed Popup Handler
        function showClosedPopup(isHoliday, customMsg) {
            if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
            if (window.AndroidNotify && typeof window.AndroidNotify.vibrate === 'function') {
                window.AndroidNotify.vibrate(100);
            }
            try {
                var snd = new Audio('sound/closed.wav');
                snd.volume = 0.25;
                snd.play().catch(function() {});
            } catch(e) {}

            var msg = document.getElementById('closedPopupMessage');
            if (msg) {
                if (customMsg) {
                    msg.innerText = customMsg;
                } else {
                    msg.innerText = isHoliday
                        ? 'THIS GAME IS CURRENTLY ON HOLIDAY'
                        : 'THIS GAME IS CURRENTLY CLOSED';
                }
            }

            var popup = document.getElementById('closedPopup');
            var box = document.getElementById('closedPopupInnerBox');
            if (popup) {
                popup.style.display = 'flex';
                setTimeout(function() {
                    popup.classList.add('show');
                    if (box) {
                        box.style.transform = 'scale(1)';
                        box.style.opacity = '1';
                    }
                }, 10);
            }

            if (window._closedTimer) clearTimeout(window._closedTimer);
            window._closedTimer = setTimeout(closeClosedPopup, 2000);
        }

        function closeClosedPopup(e) {
            var popup = document.getElementById('closedPopup');
            var box = document.getElementById('closedPopupInnerBox');
            if (popup) {
                popup.classList.remove('show');
                if (box) {
                    box.style.transform = 'scale(0.9)';
                    box.style.opacity = '0';
                }
                setTimeout(function() { popup.style.display = 'none'; }, 250);
            }
        }

        // Scroll to Top Listener
        var scrollBtn = document.getElementById('scrollTopBtn');
        window.addEventListener('scroll', function () {
            if (window.scrollY > 300) {
                if (scrollBtn) scrollBtn.style.display = 'flex';
            } else {
                if (scrollBtn) scrollBtn.style.display = 'none';
            }
        }, { passive: true });

        // Auto-refresh when new Gali result declared
        (function () {
            var knownId = null;
            function checkGaliResult() {
                fetch('api_gali_result_ts.php?_=' + Date.now())
                    .then(r => r.json())
                    .then(function(data) {
                        if (!data.ok) return;
                        if (knownId === null) {
                            knownId = data.latest_id;
                        } else if (data.latest_id > knownId) {
                            window.location.reload();
                        }
                    })
                    .catch(function() {});
            }
            checkGaliResult();
            setInterval(checkGaliResult, 10000);
        })();
    </script>
</body>
</html>


