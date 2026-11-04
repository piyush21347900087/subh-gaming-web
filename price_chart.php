<?php
require_once 'includes/lang.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';
require_once 'includes/user_ping.php';
require_once 'includes/live_ping.php';
date_default_timezone_set("Asia/Kolkata");
$nowMinServer = (int) date('G') * 60 + (int) date('i');
$todayDow = (int) date('N');

if (!isset($_SESSION['user']['id'])) {
  header('Location: user_login.php');
  exit;
}

$uid = (int) $_SESSION['user']['id'];
$db = db();
$isDeactive = (isset($_SESSION['user']['is_active']) && (int) $_SESSION['user']['is_active'] === 0);

/* ===================== LOGIC FOR HEADER & SIDEBAR ===================== */
function fetch_one_val($db, $sql, $types, $params)
{
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $val = null;
  $stmt->bind_result($val);
  $out = $stmt->fetch() ? $val : null;
  $stmt->close();
  return $out;
}

$code = fetch_one_val($db, 'SELECT user_id FROM users WHERE id = ?', 'i', [$uid]) ?: '';
if (preg_match('/^\d+$/', $code)) {
  $code = "KR" . str_pad($code, 2, "0", STR_PAD_LEFT);
} elseif (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $code, $m)) {
  $code = $m[1] . str_pad($m[2], 2, "0", STR_PAD_LEFT);
}
$LLCode = $code;

$userName = $_SESSION['user']['name'] ?? '';
if ($userName === '') {
  $userName = fetch_one_val($db, 'SELECT name FROM users WHERE id = ?', 'i', [$uid]) ?: $code;
}

$userCreatedAt = fetch_one_val($db, 'SELECT created_at FROM users WHERE id = ?', 'i', [$uid]) ?: '';
$userSince = $userCreatedAt ? date('d-m-Y', strtotime($userCreatedAt)) : '';
$userMobile = fetch_one_val($db, 'SELECT mobile_no FROM users WHERE id = ?', 'i', [$uid]) ?: '';

/* WhatsApp Support Number from Admin Settings */
$waAdminNo = '';
$rsWA = $db->query("SELECT whatsapp_no_1, whatsapp_no_2 FROM admin_payment_details WHERE id = 1 LIMIT 1");
if ($rsWA && $rowWA = $rsWA->fetch_assoc()) {
    $waAdminNo = !empty($rowWA['whatsapp_no_1']) ? $rowWA['whatsapp_no_1'] : ($rowWA['whatsapp_no_2'] ?? '');
}
$waAdminDigits = preg_replace('/\D/', '', $waAdminNo);
if (strlen($waAdminDigits) === 10) {
    $waAdminDigits = '91' . $waAdminDigits;
}
if (empty($waAdminDigits)) {
    $waAdminDigits = '917020691217';
}
$waSupportUrl = "https://wa.me/" . $waAdminDigits;

// Balance calculation
$hasBonusCol = false;
$resB = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
if ($resB && $resB->num_rows > 0) {
  $hasBonusCol = true;
}

$depositDone = (float) (fetch_one_val(
  $db,
  "SELECT (IFNULL(SUM(amount),0) + " . ($hasBonusCol ? "IFNULL(SUM(bonus_amount),0)" : "0") . ") FROM deposit WHERE status='Done' AND user_id=?",
  's',
  [$code]
) ?? 0);

$withdrawMinus = (float) (fetch_one_val(
  $db,
  "SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE user_id=? AND LOWER(status) IN ('pending','approved','done')",
  's',
  [$code]
) ?? 0);

$walletBalance = max(0.0, $depositDone - $withdrawMinus);
$winBalance = (float) (fetch_one_val($db, "SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id=?", 's', [$code]) ?? 0);
$totalBalance = (float)($depositDone - $withdrawMinus) + (float)$winBalance;
if ($totalBalance < 0) $totalBalance = 0;
$winBalance = (float)($totalBalance - $walletBalance);

// Fetch game rates from DB
$gameRates = [];
try {
  $resRates = $db->query("SELECT game_type, play_amount, win_amount FROM game_rates ORDER BY id ASC");
  if ($resRates) {
    while ($r = $resRates->fetch_assoc()) {
      $gameRates[] = $r;
    }
  }
} catch (Throwable $e) {
  // Fallback to empty
}

/* ===================== INITIAL WIN CHECK ===================== */
$initialWinData = null;
try {
    $checkCol = $db->query("SHOW COLUMNS FROM `wining` LIKE 'notified'");
    if ($checkCol && $checkCol->num_rows === 0) {
        $db->query("ALTER TABLE `wining` ADD COLUMN `notified` TINYINT(1) DEFAULT 0");
    }
    $uidStr = (string) $uid;
    $winRowsInit = [];
    $stmtWinInit = $db->prepare("SELECT id, market, wining_balance, win_time FROM wining WHERE user_id IN (?, ?) AND LOWER(status) = 'win' AND IFNULL(is_reversed, 0) = 0 AND (notified IS NULL OR notified = 0) ORDER BY id DESC LIMIT 10");
    if ($stmtWinInit) {
        $stmtWinInit->bind_param('ss', $code, $uidStr);
        $stmtWinInit->execute();
        $resWinInit = $stmtWinInit->get_result();
        while ($rw = $resWinInit->fetch_assoc()) { $winRowsInit[] = $rw; }
        $stmtWinInit->close();
    }
    if (count($winRowsInit) > 0) {
        $totalWinAmountInit = 0;
        $winIdsInit = [];
        $latestMarketInit = $winRowsInit[0]['market'] ?? '';
        foreach ($winRowsInit as $w) {
            $totalWinAmountInit += (float) $w['wining_balance'];
            $winIdsInit[] = (int) $w['id'];
        }
        $todayWinCountInit = 0;
        $stmtCntInit = $db->prepare("SELECT COUNT(*) FROM wining WHERE user_id IN (?, ?) AND LOWER(status) = 'win' AND IFNULL(is_reversed, 0) = 0 AND DATE(win_time) = CURDATE()");
        if ($stmtCntInit) {
            $stmtCntInit->bind_param('ss', $code, $uidStr);
            $stmtCntInit->execute();
            $stmtCntInit->bind_result($todayWinCountInit);
            $stmtCntInit->fetch();
            $stmtCntInit->close();
        }
        if (count($winIdsInit) > 0) {
            $idListInit = implode(',', array_map('intval', $winIdsInit));
            $db->query("UPDATE wining SET notified = 1 WHERE id IN ($idListInit)");
        }
        $initialWinData = ['win_amount' => $totalWinAmountInit, 'market' => $latestMarketInit, 'today_win_count' => max(1, (int) $todayWinCountInit)];
    }
} catch (Throwable $e) {}

$db->close();

$sideUserName = $userName;
$sideUserMobile = $userMobile ?: $code;
$sideUserSince = $userSince;
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
    <title>SUBH GAMING • Game Rates</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Poppins:wght@300;400;500;600;700;800&family=Roboto:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-brands/css/uicons-brands.css">
    <style>
        /* ===================== PREMIUM PINK WIN MODAL STYLES ===================== */
        .pink-win-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: radial-gradient(circle at center, rgba(40, 10, 30, 0.78) 0%, rgba(10, 2, 12, 0.88) 100%);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999999;
            opacity: 0;
            transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 20px;
        }

        .pink-win-modal-overlay.show {
            display: flex !important;
            opacity: 1;
        }

        .pink-win-modal-card {
            background: linear-gradient(165deg, #ffffff 0%, #fff2f7 45%, #ffdce8 100%);
            border: 2px solid rgba(255, 105, 180, 0.55);
            border-radius: 30px;
            width: 100%;
            max-width: 350px;
            padding: 28px 24px 24px;
            text-align: center;
            position: relative;
            box-shadow: 0 25px 65px rgba(216, 27, 96, 0.45), 0 0 35px rgba(255, 105, 180, 0.35), inset 0 2px 4px rgba(255, 255, 255, 0.95);
            transform: scale(0.75) translateY(30px);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-sizing: border-box;
            overflow: hidden;
        }

        .pink-win-modal-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 182, 193, 0.25) 0%, transparent 60%);
            pointer-events: none;
            animation: pinkRadialSpin 12s linear infinite;
        }

        @keyframes pinkRadialSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .pink-win-modal-overlay.show .pink-win-modal-card {
            transform: scale(1) translateY(0);
        }

        .pink-win-modal-close {
            position: absolute;
            top: 14px;
            right: 16px;
            background: rgba(255, 228, 233, 0.9);
            border: 1px solid rgba(255, 105, 180, 0.3);
            color: #e91e63;
            font-size: 18px;
            font-weight: 800;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s ease;
            z-index: 5;
            box-shadow: 0 3px 8px rgba(233, 30, 99, 0.15);
        }

        .pink-win-modal-close:hover {
            background: #ff4081;
            color: #ffffff;
            transform: scale(1.12) rotate(90deg);
            box-shadow: 0 4px 12px rgba(255, 64, 129, 0.4);
        }

        .pink-win-emojis {
            font-size: 24px;
            letter-spacing: 6px;
            margin-bottom: 12px;
            user-select: none;
            position: relative;
            z-index: 2;
            filter: drop-shadow(0 2px 4px rgba(255, 105, 180, 0.3));
            animation: pinkEmojiBounce 1.8s infinite ease-in-out;
        }

        @keyframes pinkEmojiBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        .pink-win-trophy-ring {
            width: 96px;
            height: 96px;
            margin: 0 auto 16px;
            background: radial-gradient(circle, #ffffff 20%, #ffe3ec 60%, #ffb3c6 100%);
            border: 3px solid #ff80ab;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 3px 8px rgba(255, 255, 255, 1), 0 10px 28px rgba(233, 30, 99, 0.35);
            position: relative;
            z-index: 2;
            animation: pinkTrophyGlow 2s infinite ease-in-out;
        }

        @keyframes pinkTrophyGlow {
            0%, 100% { transform: scale(1); box-shadow: inset 0 3px 8px rgba(255, 255, 255, 1), 0 10px 28px rgba(233, 30, 99, 0.35); }
            50% { transform: scale(1.06); box-shadow: inset 0 3px 8px rgba(255, 255, 255, 1), 0 16px 36px rgba(233, 30, 99, 0.5); }
        }

        .pink-win-trophy-emoji {
            font-size: 48px;
            line-height: 1;
            filter: drop-shadow(0 6px 10px rgba(0, 0, 0, 0.2));
            animation: winTrophyFloat 3s infinite ease-in-out;
        }

        @keyframes winTrophyFloat {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-6px) rotate(4deg); }
        }

        .pink-win-title {
            margin: 0 0 4px;
            font-size: 26px;
            font-weight: 900;
            background: linear-gradient(135deg, #e91e63 0%, #ff1744 50%, #c2185b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Poppins', 'Montserrat', sans-serif;
            letter-spacing: -0.4px;
            position: relative;
            z-index: 2;
        }

        .pink-win-subtitle {
            margin: 0 0 16px;
            font-size: 14px;
            font-weight: 700;
            color: #880e4f;
            font-family: 'Poppins', sans-serif;
            position: relative;
            z-index: 2;
            opacity: 0.9;
        }

        .pink-win-amount-badge {
            background: linear-gradient(135deg, #ff007f 0%, #ff2a7a 50%, #e91e63 100%);
            color: #ffffff;
            padding: 14px 20px;
            border-radius: 18px;
            font-size: 24px;
            font-weight: 900;
            margin: 14px 0 14px;
            box-shadow: 0 10px 25px rgba(255, 0, 127, 0.42), inset 0 2px 4px rgba(255, 255, 255, 0.4);
            letter-spacing: 0.5px;
            font-family: 'Poppins', sans-serif;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
            z-index: 2;
        }

        .pink-win-amount-badge::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 60px;
            height: 200%;
            background: rgba(255, 255, 255, 0.35);
            transform: skewX(-25deg);
            animation: badgeSheen 2.5s infinite;
        }

        @keyframes badgeSheen {
            0% { left: -60px; }
            30%, 100% { left: 120%; }
        }

        .pink-win-stats-box {
            background: rgba(233, 30, 99, 0.07);
            border: 1px dashed rgba(233, 30, 99, 0.35);
            border-radius: 14px;
            padding: 8px 14px;
            margin: 0 0 20px;
            display: inline-block;
            position: relative;
            z-index: 2;
        }

        .pink-win-stats {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: #880e4f;
            font-family: 'Poppins', sans-serif;
        }

        .pink-win-action-btn {
            width: 100%;
            border: none;
            background: linear-gradient(135deg, #ff1744 0%, #ff4081 50%, #d81b60 100%);
            color: #ffffff;
            padding: 15px 22px;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(216, 27, 96, 0.45), inset 0 2px 2px rgba(255, 255, 255, 0.3);
            transition: all 0.25s ease;
            font-family: 'Poppins', sans-serif;
            position: relative;
            z-index: 2;
            letter-spacing: 0.3px;
        }

        .pink-win-action-btn:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 12px 30px rgba(216, 27, 96, 0.55);
        }

        .pink-win-action-btn:active {
            transform: scale(0.97);
        }

        :root {
            --primary-bg: #000000;
            --card-bg: #121212;
            --accent-main: #e53935;
            --accent-red: #e53935;
            --text-main: #ffffff;
            --text-dim: #94a3b8;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            --border-radius: 15px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        html {
            background-color: #000000 !important;
        }

        body {
            background-color: #000000 !important;
            background: #000000 !important;
            color: var(--text-main);
            padding-bottom: 100px;
            overflow-x: clip;
        }

        header {
            background-color: #000000 !important;
            background-image: none !important;
            background-image: 
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px),
                repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px);
            /* Push content below Android status bar but keep pattern behind it */
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid #1a1a1a;
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
        }

        .wallet-display i {
            font-size: 13px;
            display: inline-flex;
            align-items: center;
        }

        .wallet-icon-btn {
            display: none;
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

        /* Header Toggle Btn */
        #menuToggle {
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
            font-size: 20px !important;
        }

        /* Marquee */
        .marquee-wrap {
            background: #121212;
            padding: 8px;
            color: #ffcc00;
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
            0% {
                transform: translateX(100vw);
            }

            100% {
                transform: translateX(-100%);
            }
        }

        /* Sections */
        .content {
            max-width: 480px;
            margin: 0 auto;
            padding: 15px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #ffffff;
            border-left: 4px solid #e53935;
            padding-left: 10px;
        }

        /* ── Rate Sections ── */
        .rate-section {
            margin-bottom: 25px;
        }

        .rate-header-wrapper {
            margin-bottom: 14px;
        }

        .rate-header {
            color: #ffffff;
            padding: 2px 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-align: center;
            font-size: 19px;
            font-weight: 600;
            text-transform: capitalize;
            letter-spacing: 0.2px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
            filter: drop-shadow(0 2px 5px rgba(0, 0, 0, 0.4));
            box-sizing: border-box;

            /* Bigger corner cut (radius 16) with stroke line placed further inside */
            border-style: solid;
            border-width: 17px;
            border-image-source: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='72' height='72' viewBox='0 0 72 72'%3E%3Cpath d='M16 0 L56 0 A16 16 0 0 0 72 16 L72 56 A16 16 0 0 0 56 72 L16 72 A16 16 0 0 0 0 56 L0 16 A16 16 0 0 0 16 0 Z' fill='%23800000'/%3E%3Cpath d='M20.8 5.5 L51.2 5.5 A21.5 21.5 0 0 0 66.5 20.8 L66.5 51.2 A21.5 21.5 0 0 0 51.2 66.5 L20.8 66.5 A21.5 21.5 0 0 0 5.5 51.2 L5.5 20.8 A21.5 21.5 0 0 0 20.8 5.5 Z' fill='none' stroke='%23d93b3b' stroke-width='1.3'/%3E%3C/svg%3E");
            border-image-slice: 25 fill;
            border-image-repeat: stretch;
            background: transparent;
        }

        .rate-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            background-color: #ffffff !important;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.15s ease;
        }
        .rate-row:active {
            transform: scale(0.98);
        }

        .rate-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .rate-left img {
            width: 22px;
            height: 22px;
            object-fit: contain;
        }

        .rate-name {
            font-size: 13px;
            font-weight: 700;
            color: #8b0000;
        }

        .rate-right {
            font-size: 13px;
            font-weight: 700;
            color: #111111;
        }

        /* ── Action Buttons ── */
        .action-btns-row { display:flex; gap:10px; margin-bottom:12px; }
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
            font-weight: 600;
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
            font-size: 22px;
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
            .btn-text-center {
                margin-right: 0px;
                font-size: 16px;
            }
            .action-btn-custom, .btn-play-pill-custom {
                gap: 5px;
            }
        }

        .sticky-action-bar {
            position: sticky;
            top: 65px;
            background: #000000 !important;
            border-bottom: 1px solid #1a1a1a;
            z-index: 90;
            padding-top: 4px;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        /* ── Market Cards (Screenshot exact design) ── */
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
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        /* Left Sub-Column: Title, Number, Status */
        .market-col-left {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 3px;
            flex: 1;
        }

        .market-card-title {
            font-size: 17px;
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
        .market-card-status {
            font-size: 11px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            text-align: center;
            width: 100%;
        }

        .market-number-row {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .market-number-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .info-btn-icon {
            color: #111111;
            font-size: 17px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            right: 100%;
            margin-right: 6px;
        }
        .info-circle-outline {
            border: 1px solid #111111;
            border-radius: 50%;
            width: 19px;
            height: 19px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-family: 'Times New Roman', serif;
            font-style: italic;
            font-weight: bold;
            color: #111111;
        }

        .market-card-number {
            font-size: 17px;
            font-weight: 900;
            color: #111111;
            letter-spacing: 0.5px;
            font-family: 'Montserrat', sans-serif;
        }

        .market-card-status {
            text-align: center;
        }

        .status-text-running {
            color: #00b894;
            font-weight: 600;
            font-size: 12px;
        }

        .status-text-closed {
            color: #e53935;
            font-weight: 600;
            font-size: 12px;
        }

        /* Right Sub-Column: Clock & Times */
        .market-col-right {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .clock-icon {
            font-size: 18px;
            flex-shrink: 0;
            transform: translateX(-5px);
        }

        .clock-open { color: #00b894; }
        .clock-closed { color: #ff6b4a; }

        .market-card-times {
            display: flex;
            flex-direction: column;
            font-size: 10px;
            font-weight: 400;
            font-family: 'Poppins', sans-serif;
            color: #333333;
            line-height: 1.2;
            text-align: left;
            white-space: nowrap;
        }

        .market-card-times .time-val {
            font-weight: 400;
            font-family: 'Poppins', sans-serif;
            color: #222222;
            font-size: 10px;
        }

        /* Play Section (Right side) */
        .market-card-right {
            width: 70px;
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

        .market-time-bar {
            background: #ff005c;
            color: #ffffff;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(0,0,0,0.03);
        }

        .market-row:active {
            transform: scale(0.98);
        }

        .market-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .market-icon {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: none !important;
            color: #000000 !important;
            border: none !important;
            box-shadow: none !important;
            text-shadow: none !important;
            font-size: 24px;
            position: relative;
            z-index: 1;
        }

        .nums-small {
            font-size: 9px;
            color: #333333;
            margin-top: 2px;
        }

        .market-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            pointer-events: none;
            text-align: center;
        }

        .market-info h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #000;
            text-transform: uppercase;
            letter-spacing: -0.5px;
            line-height: 26px;
            margin: 0;
            display: inline-block;
        }

        .market-nums {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: #ff005c;
            font-size: 18px;
            margin: 0;
            letter-spacing: 2px;
            white-space: nowrap;
        }

        .market-info p {
            font-size: 11px;
            color: #666666;
            margin: 0;
            font-weight: 500;
        }


        @keyframes jellyPulse {
            0% { transform: scale(1); }
            20% { transform: scale(1.1, 0.9); }
            40% { transform: scale(0.9, 1.1); }
            60% { transform: scale(1.05, 0.95); }
            80% { transform: scale(0.98, 1.02); }
            100% { transform: scale(1); }
        }

        @keyframes scalePulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .btn-play-pill {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 50%, #27ae60 100%) !important;
            color: #ffffff !important;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.1s ease;
            position: relative;
            border: none !important;
            box-shadow: 0 4px 0 #1e8449, 0 6px 12px rgba(39, 174, 96, 0.45);
            animation: waterBubble 3s ease-in-out infinite;
        }
        .btn-play-pill::after {
            content: ''; position: absolute; top: 2px; left: 10%; width: 80%; height: 30%; background: linear-gradient(180deg, rgba(255,255,255,0.4), transparent); border-radius: 50px 50px 0 0; pointer-events: none;
        }

        .btn-play-pill i {
            margin-left: 3px;
        }
        .btn-play-pill.locked i {
            margin-left: 0;
        }

        .btn-play-pill span { font-size: 13px; font-weight: 600; }

        .btn-play-pill:active {
            animation: none;
            transform: translateY(4px);
            box-shadow: 0 0px 0 #1e8449, 0 2px 5px rgba(39,174,96,0.3) !important;
        }

        .btn-play-pill.locked {
            background: linear-gradient(135deg, #e60000 0%, #ff4d4d 50%, #e60000 100%) !important;
            color: #ffffff !important;
            border: none !important;
            box-shadow: 0 4px 0 #990000, 0 6px 12px rgba(230, 0, 0, 0.45);
            animation: none;
        }

        .btn-play:active {
            transform: scale(0.9);
        }

        .btn-play.locked {
            background: #ff4d4d;
            border-color: #ffffff;
            box-shadow: 0 0 0 2px #ff4d4d;
            cursor: not-allowed;
            animation: scalePulse 3s infinite ease-in-out;
        }

        .market-row.closed-market {
            border-color: #ff4d4d;
        }

        .market-row.holiday-market {
            background: linear-gradient(135deg, #fff3f3 0%, #ffe0e0 100%);
            border-left-color: #e60000 !important;
            box-shadow: 0 6px 18px rgba(230, 0, 0, 0.15);
        }

        .market-row.holiday-market .market-info h3,
        .market-row.holiday-market .market-info p,
        .market-row.holiday-market .market-left span {
            color: #a40000 !important;
        }

        .market-row.holiday-market .market-icon {
            background: linear-gradient(135deg, #B8860B 0%, #FFD700 50%, #B8860B 100%) !important;
            color: #000000 !important;
        }

        .holiday-note {
            font-size: 10px;
            font-weight: 900;
            color: #e60000;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-top: 3px;
        }
        
        .market-row.closed-market .market-icon {
            border-color: #ff4d4d;
            color: #ff4d4d !important;
        }

        .market-row.closed-market .market-left span {
            color: #ff4d4d !important;
        }

        .market-row.closed-market .market-left div:first-child span {
            color: #000000 !important;
        }

        .market-status-text {
            font-size: 14px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .status-running {
            color: #2ecc71;
        }

        .status-closed {
            color: #ff0000;
        }

        .back-to-top {
            position: fixed;
            bottom: 95px;
            right: 20px;
            width: 45px;
            height: 45px;
            background: #000000;
            color: #ffffff;
            border: none;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            z-index: 999;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        .back-to-top i {
            animation: bounceUp 1.2s infinite ease-in-out;
        }

        @keyframes bounceUp {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        /* Closed Market Popup */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 5000;
        }

        .popup-box {
            width: 80%;
            max-width: 270px;
            background: #fff;
            border-radius: 24px;
            padding: 25px 20px;
            text-align: center;
            transform: scale(0.8);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            color: #333;
        }

        .popup-overlay.show {
            display: flex;
        }

        .popup-overlay.show .popup-box {
            transform: scale(1);
            opacity: 1;
        }

        .popup-icon {
            font-size: 60px;
            color: #ff1f50;
            margin-bottom: 15px;
        }

        .popup-icon i {
            display: inline-block;
            animation: iconWobble 0.8s infinite alternate ease-in-out;
        }

        @keyframes iconWobble {
            0% {
                transform: scale(0.9) rotate(-8deg);
            }

            100% {
                transform: scale(1.1) rotate(8deg);
            }
        }

        .popup-box h2 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1a1a1a;
        }

        .popup-box p {
            font-size: 13px;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.4;
            font-weight: 500;
        }

        .popup-close-btn {
            background: linear-gradient(to bottom, #f3555a 0%, #c11016 48%, #850005 52%, #e51d24 100%) !important;
            color: #fff !important;
            border: 1px solid #7c0408 !important;
            padding: 10px 40px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(255, 31, 80, 0.3), inset 0 2px 3px rgba(255, 255, 255, 0.4), inset 0 -2px 3px rgba(0, 0, 0, 0.5) !important;
            text-shadow: 0 -1px 1px rgba(0, 0, 0, 0.8) !important;
        }

        /* Bottom Menu (Matching Screenshot Design) */
        nav.bottom-menu {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 62px;
            background-color: #000000 !important;
            border-top: 1px solid #1a1a1a;
            background-image: none !important;


            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 2000;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.5);
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

        .menu-item i {
            font-size: 20px !important;
            color: #ffffff !important;
            margin-bottom: 2px;
            background: none !important;
            border: none !important;
            box-shadow: none !important;
            width: auto !important;
            height: auto !important;
            border-radius: 0 !important;
        }

        .menu-item span {
            font-size: 11px;
            font-weight: 600;
            color: #ffffff;
            font-family: 'Roboto', sans-serif;
        }

        .chip-100-icon {
            width: 20px;
            height: 20px;
            border: 2px dashed #ffffff;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            font-weight: 900;
            line-height: 1;
            color: #ffffff;
            margin-bottom: 2px;
        }

        /* Center Home button specific styles */
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

        /* PNG icon styles */
        .menu-item img.nav-icon {
            width: 24px;
            height: 24px;
            object-fit: contain;
            margin-bottom: 2px;
            filter: brightness(0) invert(1);
            transition: filter 0.2s ease;
        }

        .menu-item.active img.nav-icon {
            filter: brightness(0) saturate(100%) invert(48%) sepia(79%) saturate(2476%) hue-rotate(328deg) brightness(102%) contrast(101%);
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
            filter: brightness(0) invert(1) !important; /* Always white */
        }

        .menu-item.home-item span {
            display: none;
        }

        /* Active states */
        .menu-item.active img.nav-icon {
            filter: brightness(0) saturate(100%) invert(48%) sepia(79%) saturate(2476%) hue-rotate(328deg) brightness(102%) contrast(101%);
        }

        .menu-item.active span {
            color: #ffffff !important;
            font-weight: 700;
        }

        .menu-item.home-item.active .home-circle {
            background: #ff2a2a;
        }



        .menu-item.center-btn.active i {
            color: #ffffff;
        }

        .menu-item.center-btn i {
            font-size: 26px;
        }

        .menu-item.center-btn span {
            display: none;
        }

        .menu-item.center-btn.active::before {
            display: none;
        }


        /* Contact Section */
        .wa-contact-row {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .wa-contact-item {
            flex: 1;
            background: var(--gradient-gold);
            padding: 12px;
            border-radius: 12px;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            box-shadow: var(--shadow), inset 0 6px 12px rgba(0, 0, 0, 0.15), inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .wa-contact-item i {
            color: #000;
            font-size: 20px;
        }

        /* SIDE MENU Standardized (V6 Black & Gold) */
        .side-menu-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.6);
            display: none;
            z-index: 4000;
            backdrop-filter: blur(2px);
        }
        .side-menu {
            position: fixed; top: 0; left: -280px;
            width: 280px; height: 100%;
            background: #121212; color: #ffffff;
            padding: 0;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 4100;
            box-shadow: none;
            display: flex; flex-direction: column;
        }
        .side-menu.open { left: 0; box-shadow: 5px 0 20px rgba(0,0,0,0.5); }

        .side-menu-header {
            padding: 50px 15px 20px 15px; /* Added top padding to prevent status bar overlap */
            background-color: #000000 !important;
            background-image: none !important;
            border-bottom: 1px solid #1a1a1a;
            background-image: 
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px),
                repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px);
            display: flex; align-items: flex-start;
            position: relative;
        }
        .profile-avatar {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: #ff4d4d;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px;
            color: #fff;
            margin-right: 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            text-decoration: none;
        }
        .profile-text { display: flex; flex-direction: column; flex: 1; }
        .profile-name { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 2px;}
        .profile-id   { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 8px;}
        .sound-btn {
            background: #ff4d4d;
            border: none; border-radius: 15px; padding: 4px 10px;
            color: #fff; font-size: 12px; font-weight: 600;
            display:inline-flex; align-items:center; gap: 6px; 
            cursor: pointer; align-self: flex-start;
        }
        .side-close-btn { 
            position: absolute;
            top: 45px; right: 15px;
            background: #ff4d4d; border: none; color: #fff; 
            font-size: 14px; width: 28px; height: 28px; border-radius: 50%;
            cursor: pointer; display:flex; align-items:center; justify-content:center;
        }

        .side-menu-body { flex: 1; overflow-y: auto; padding-bottom: 20px; }
        .side-menu-item {
            display: flex; align-items: center;
            padding: 12px 15px;
            font-size: 15px; color: #ffffff;
            font-weight: 600;
            text-decoration: none;
            border-bottom: 1px solid #1a1a1a;
            transition: all 0.2s ease;
        }
        .side-menu-item .icon-box {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: #ff4d4d;
            display: flex; align-items: center; justify-content: center;
            margin-right: 15px;
        }
        .side-menu-item .icon-box img {
            width: 20px; height: 20px;
            object-fit: contain;
        }
        .side-menu-item .icon-box i {
            color: #ffffff;
            font-size: 16px;
        }
        .side-menu-item:hover { background: #f9f9f9; }

        .side-menu-footer { 
            padding: 15px; 
            text-align: center; 
        }
        .version-text {
            color: #ff4d4d;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.4;
        }

        .popup-box {
            width: 85%;
            max-width: 320px;
            background: linear-gradient(145deg, #ffffff, #f0f2f5);
            border-radius: 28px;
            padding: 30px 20px;
            text-align: center;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            color: #1a1a1a;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .popup-overlay.show {
            display: flex;
        }

        .popup-overlay.show .popup-box {
            transform: translateY(0);
            opacity: 1;
        }

        .popup-icon {
            width: 85px;
            height: 85px;
            /* Gorgeous Shiny Metallic Red Chrome styling matching closed markets */
            background: linear-gradient(to bottom, #f3555a 0%, #c11016 48%, #850005 52%, #e51d24 100%) !important;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            color: #ffffff !important;
            margin: 0 auto 20px;
            border: 2.5px solid #850005 !important;
            box-shadow: 0 8px 16px rgba(193, 16, 22, 0.35), inset 0 2px 3px rgba(255, 255, 255, 0.5), inset 0 -2px 3px rgba(0, 0, 0, 0.6) !important;
            text-shadow: 0 -1px 1.5px rgba(0, 0, 0, 0.7) !important;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .popup-overlay.show .popup-icon {
            transform: scale(1.05);
        }

        .popup-box h2 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #000;
        }

        .popup-box p {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .popup-close-btn {
            width: 100%;
            height: 48px;
            background: #000000;
            color: #ffffff;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 10px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }

        .popup-close-btn:active {
            transform: scale(0.96);
        }

        .share-btn i {
            font-size: 22px;
            color: #000000;
        }

        /* ── Market Timetable Modal ── */
        .time-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.65);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .time-modal-content {
            background: #ffffff;
            width: 100%;
            max-width: 350px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            animation: modalPopIn 0.22s ease-out;
        }

        @keyframes modalPopIn {
            from { transform: scale(0.85); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .time-modal-header {
            background: #8b0000;
            color: #ffffff;
            padding: 12px 14px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            text-transform: uppercase;
            font-family: 'Poppins', sans-serif;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .time-modal-body {
            padding: 16px 14px 20px 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .time-modal-status-bar {
            font-size: 13px;
            margin-bottom: 16px;
            text-align: center;
            font-family: 'Poppins', sans-serif;
        }

        .time-modal-status-bar .day-name {
            font-weight: 700;
            color: #111111;
        }

        .time-modal-status-bar .status-msg {
            font-weight: 700;
        }

        .status-closed-msg {
            color: #ff3b30;
        }

        .status-open-msg {
            color: #00b894;
        }

        .time-modal-boxes-wrap {
            display: flex;
            gap: 10px;
            width: 100%;
            margin-bottom: 18px;
        }

        .time-box {
            flex: 1;
            border: 1px solid #d0d0d0;
            border-radius: 10px;
            overflow: hidden;
            background: #ffffff;
            text-align: center;
        }

        .time-box-title {
            font-weight: 900;
            font-size: 14px;
            color: #111111;
            padding: 8px 4px;
            border-bottom: 2px solid #8b0000;
            letter-spacing: 0.5px;
            font-family: 'Poppins', sans-serif;
        }

        .time-box-row {
            padding: 8px 4px;
        }

        .time-box-label {
            font-size: 9.5px;
            font-weight: 600;
            color: #444444;
            text-transform: uppercase;
            line-height: 1.2;
            margin-bottom: 4px;
            font-family: 'Poppins', sans-serif;
        }

        .time-box-value {
            font-size: 12px;
            font-weight: 600;
            color: #111111;
            font-family: 'Poppins', sans-serif;
        }

        .time-box-divider {
            border-top: 1px solid #e8e8e8;
        }

        .time-modal-ok-btn {
            background: #ff3b30;
            color: #ffffff;
            border: none;
            width: 140px;
            padding: 10px 0;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(255, 59, 48, 0.4);
            transition: transform 0.15s ease;
        }

        .time-modal-ok-btn:active {
            transform: scale(0.96);
        }

        /* ── Custom 3-Line Decreasing Burger Icon ── */
        .custom-burger-btn {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 22px;
            height: 15px;
            cursor: pointer;
            padding: 1px 0;
            flex-shrink: 0;
        }

        .custom-burger-btn span {
            display: block;
            font-weight: 800;
            margin-bottom: 8px;
            color: #000;
        }

        .popup-box p {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .popup-close-btn {
            width: 100%;
            height: 48px;
            background: #000000;
            color: #ffffff;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 10px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }

        .popup-close-btn:active {
            transform: scale(0.96);
        }

        .share-btn i {
            font-size: 22px;
            color: #000000;
        }

        /* ── Market Timetable Modal ── */
        .time-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.65);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .time-modal-content {
            background: #ffffff;
            width: 100%;
            max-width: 350px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            animation: modalPopIn 0.22s ease-out;
        }

        @keyframes modalPopIn {
            from { transform: scale(0.85); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .time-modal-header {
            background: #8b0000;
            color: #ffffff;
            padding: 12px 14px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            text-transform: uppercase;
            font-family: 'Poppins', sans-serif;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .time-modal-body {
            padding: 16px 14px 20px 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .time-modal-status-bar {
            font-size: 13px;
            margin-bottom: 16px;
            text-align: center;
            font-family: 'Poppins', sans-serif;
        }

        .time-modal-status-bar .day-name {
            font-weight: 700;
            color: #111111;
        }

        .time-modal-status-bar .status-msg {
            font-weight: 700;
        }

        .status-closed-msg {
            color: #ff3b30;
        }

        .status-open-msg {
            color: #00b894;
        }

        .time-modal-boxes-wrap {
            display: flex;
            gap: 10px;
            width: 100%;
            margin-bottom: 18px;
        }

        .time-box {
            flex: 1;
            border: 1px solid #d0d0d0;
            border-radius: 10px;
            overflow: hidden;
            background: #ffffff;
            text-align: center;
        }

        .time-box-title {
            font-weight: 900;
            font-size: 14px;
            color: #111111;
            padding: 8px 4px;
            border-bottom: 2px solid #8b0000;
            letter-spacing: 0.5px;
            font-family: 'Poppins', sans-serif;
        }

        .time-box-row {
            padding: 8px 4px;
        }

        .time-box-label {
            font-size: 9.5px;
            font-weight: 600;
            color: #444444;
            text-transform: uppercase;
            line-height: 1.2;
            margin-bottom: 4px;
            font-family: 'Poppins', sans-serif;
        }

        .time-box-value {
            font-size: 12px;
            font-weight: 600;
            color: #111111;
            font-family: 'Poppins', sans-serif;
        }

        .time-box-divider {
            border-top: 1px solid #e8e8e8;
        }

        .time-modal-ok-btn {
            background: #ff3b30;
            color: #ffffff;
            border: none;
            width: 140px;
            padding: 10px 0;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(255, 59, 48, 0.4);
            transition: transform 0.15s ease;
        }

        .time-modal-ok-btn:active {
            transform: scale(0.96);
        }

        /* ── Custom 3-Line Decreasing Burger Icon ── */
        .custom-burger-btn {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 22px;
            height: 15px;
            cursor: pointer;
            padding: 1px 0;
            flex-shrink: 0;
        }

        .custom-burger-btn span {
            display: block;
            height: 2.5px;
            background-color: #ffffff;
            border-radius: 3px;
        }

        .custom-burger-btn span:nth-child(1) {
            width: 22px; /* Pehle line badi */
        }

        .custom-burger-btn span:nth-child(2) {
            width: 15px; /* Uske baad choti */
        }

        .custom-burger-btn span:nth-child(3) {
            width: 9px;  /* Uske baad aur choti */
        }
            /* ── Support Popup Modal (Screenshot Matching) ── */
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
            background: #121212;
            border: 1px solid #222222;
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

        .support-modal-close:active {
            transform: scale(0.92);
        }

        .support-modal-title {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
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
            transition: transform 0.15s ease, box-shadow 0.15s ease;
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

        .support-btn-icon img { width: 32px; height: 32px; object-fit: contain; }
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

        .support-btn-chat .support-btn-icon i {
            color: #ffc107;
            font-size: 30px;
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
    <!-- Header -->
    <header>
        <div class="header-left">
            <div id="menuToggle" onclick="openM(event)" title="Menu">
                <img src="icons/burger_icon.svg" alt="Menu" style="width: 22px; height: 22px; filter: brightness(0) invert(1);">
            </div>
            <div class="header-title-flash"><img src="icons/gold_coin.svg" alt="coin" style="width: 18px; height: 18px; vertical-align: middle; margin-top: -3px;"> SUBH GAMING <img src="icons/gold_coin.svg" alt="coin" style="width: 18px; height: 18px; vertical-align: middle; margin-top: -3px;"></div>
        </div>
        <div class="header-right">
            <a href="deposit.php" class="wallet-display" style="text-decoration: none;">
                <i class="fa-solid fa-wallet"></i>
                <span id="walletVal"><?= (int)$totalBalance ?></span>
            </a>
            <a href="notice_board.php" class="bell-icon-btn" id="notifyLink" style="position: relative;">
                <i class="fa-solid fa-bell"></i>
                <div id="notifyDot"
                    style="display: none; position: absolute; top: 0; right: 0; width: 8px; height: 8px; background: #ff1f50; border-radius: 50%;">
                </div>
            </a>
        </div>
    </header>

    <div class="content">

      <?php
      /* ── Helper: build "10 Ka X" ratio string from play_amount & win_amount ── */
      function rateRatio($play, $win) {
          $p = (float)$play;
          $w = (float)$win;
          if ($p <= 0) return htmlspecialchars($win);
          $ratio = $w / $p;
          $w10 = $ratio * 10;
          $r = ($w10 == (int)$w10) ? (int)$w10 : number_format($w10, 1);
          return '10 Ka ' . $r;
      }

      $rateColors = ['#ffffff','#ffffff','#ffffff','#ffffff','#ffffff','#ffffff','#ffffff','#ffffff','#ffffff'];
      $rateIcons  = [
          'single-digit.svg','jodi-digit.svg','single-pana.svg','double-pana.svg',
          'triple-pana.svg','half-sangam.svg','full-sangam.svg','single-digit.svg','jodi-digit.svg'
      ];

      /* ── Default fallback rows if DB empty ── */
      $defaultRates = [
          ['game_type'=>'Single Digit',  'play_amount'=>10, 'win_amount'=>95],
          ['game_type'=>'Jodi Digits',   'play_amount'=>10, 'win_amount'=>950],
          ['game_type'=>'Single Panna',  'play_amount'=>10, 'win_amount'=>1500],
          ['game_type'=>'Double Panna',  'play_amount'=>10, 'win_amount'=>3000],
          ['game_type'=>'Triple Panna',  'play_amount'=>10, 'win_amount'=>7000],
          ['game_type'=>'Half Sangam',   'play_amount'=>10, 'win_amount'=>10000],
          ['game_type'=>'Full Sangam',   'play_amount'=>10, 'win_amount'=>100000],
      ];
      $starlineRates = [
          ['game_type'=>'Single Digit',  'play_amount'=>10, 'win_amount'=>95],
          ['game_type'=>'Single Panna',  'play_amount'=>10, 'win_amount'=>1500],
          ['game_type'=>'Double Panna',  'play_amount'=>10, 'win_amount'=>3000],
          ['game_type'=>'Triple Panna',  'play_amount'=>10, 'win_amount'=>7000],
      ];
      $jackpotRates = [
          ['game_type'=>'Jodi Digits',   'play_amount'=>10, 'win_amount'=>950],
      ];

      $mainRates = !empty($gameRates) ? $gameRates : $defaultRates;

      /* ── Render one section ── */
      function renderRateSection($heading, $rows, $rateColors, $rateIcons) {
      ?>
      <div class="rate-section">
          <div class="rate-header-wrapper">
              <div class="rate-header">
                  <img src="icons/gold_coin.svg" alt="coins" style="width: 22px; height: 22px; object-fit: contain;">
                  <span><?= htmlspecialchars($heading) ?></span>
                  <img src="icons/gold_coin.svg" alt="coins" style="width: 22px; height: 22px; object-fit: contain;">
              </div>
          </div>
          <?php foreach ($rows as $idx => $r):
              $bg  = $rateColors[$idx % count($rateColors)];
              $ico = $rateIcons[$idx  % count($rateIcons)];
          ?>
          <div class="rate-row" style="background-color: <?= htmlspecialchars($bg) ?>;">
              <div class="rate-left">
                  <img src="icons/<?= htmlspecialchars($ico) ?>" alt="<?= htmlspecialchars($r['game_type']) ?>">
                  <span class="rate-name"><?= htmlspecialchars($r['game_type']) ?></span>
              </div>
              <div class="rate-right"><?= rateRatio($r['play_amount'], $r['win_amount']) ?></div>
          </div>
          <?php endforeach; ?>
      </div>
      <?php
      }

      renderRateSection('Game Rates Chart',    $mainRates,    $rateColors, $rateIcons);
      renderRateSection('Starline Rates Chart', $starlineRates, $rateColors, $rateIcons);
      renderRateSection('Jackpot Rates Chart',  $jackpotRates,  $rateColors, $rateIcons);
      ?>

    </div><!-- /.content -->

    <!-- Closed Popup HTML -->
    <div id="closedPopup" class="popup-overlay" style="align-items: center; justify-content: center; padding: 20px;" onclick="closeClosedPopup(event)">
        <div class="popup-box-slim" onclick="event.stopPropagation()" style="background: linear-gradient(135deg, #8b0000 0%, #5a0000 100%); border: 1.5px solid #d4af37; border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.5); max-width: 90%; margin: 0 auto; transform: scale(0.9); transition: transform 0.3s ease; opacity: 0;" id="closedPopupInnerBox">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="background: rgba(255,215,0,0.2); border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fa-solid fa-lock" style="color: #ffd700; font-size: 14px;"></i>
                </div>
                <div style="color: #ffffff; font-weight: 700; font-size: 13px; text-transform: uppercase; line-height: 1.2; font-family: 'Poppins', sans-serif;" id="closedPopupMessage">
                    THIS GAME IS CURRENTLY CLOSED
                </div>
            </div>
            <!-- Hidden elements to preserve JS compatibility -->
            <h2 id="closedPopupTitle" style="display: none;"></h2>
            <div id="popupMarketTime" style="display: none;"></div>
        </div>
    </div>

        <!-- SIDE MENU -->
    <div class="side-menu-overlay" id="sideOverlay" onclick="closeM(event)"></div>
    <div class="side-menu" id="sideMenu">
        <!-- Header (user info) -->
        <div class="side-menu-header">
            <div class="profile-avatar"><i class="fas fa-user"></i></div>
            <div class="profile-text">
                <div class="profile-name"><?= htmlspecialchars($userName) ?></div>
                <div class="profile-id"><?= htmlspecialchars($userMobile ?: $code) ?></div>
            </div>
            <button type="button" class="side-close-btn" id="sideCloseBtn" onclick="closeM(event)">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Menu Items -->
        <div class="side-menu-body">
            <a href="user_dashboard.php" class="side-menu-item">
                <div class="icon-box"><img src="icons/home.svg" alt="Home"></div>
                <span class="label">Home</span>
            </a>
            <a href="user_bid_history.php" class="side-menu-item">
                <div class="icon-box"><img src="icons/bid.svg" alt="Bid"></div>
                <span class="label">Bid History</span>
            </a>
            <a href="user_wining.php" class="side-menu-item">
                <div class="icon-box"><img src="icons/win_history.svg" alt="Win"></div>
                <span class="label">Win History</span>
            </a>

            <a href="price_chart.php" class="side-menu-item active">
                <div class="icon-box"><img src="icons/game_rates.svg" alt="Game Rates"></div>
                <span class="label">Game Rates</span>
            </a>
            <a href="user_panel_chart.php" class="side-menu-item">
                <div class="icon-box"><img src="icons/chart_icon.svg" alt="Panel Chart"></div>
                <span class="label">Panel Chart</span>
            </a>
            <a href="how_to_play.php" class="side-menu-item">
                <div class="icon-box"><img src="icons/how_to_play.svg" alt="How to Play"></div>
                <span class="label">How To Play</span>
            </a>

            <a href="settings.php" class="side-menu-item">
                <div class="icon-box"><img src="icons/setting,svg.svg" alt="Settings"></div>
                <span class="label">Settings</span>
            </a>
            <a href="javascript:void(0);" class="side-menu-item" id="shareBtn">
                <div class="icon-box"><img src="icons/share.svg" alt="Share"></div>
                <span class="label">Share Application</span>
            </a>
            <a href="change_password.php" class="side-menu-item">
                <div class="icon-box"><img src="icons/change_password.svg" alt="Change Password"></div>
                <span class="label">Change Password</span>
            </a>
            <a href="user_referral.php" class="side-menu-item">
                <div class="icon-box"><img src="icons/referral.svg" alt="Referral"></div>
                <span class="label">Referral</span>
            </a>

            <a href="user_logout.php" class="side-menu-item" id="logoutSideBtn">
                <div class="icon-box"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
                <span class="label">Logout</span>
            </a>
        </div>

        <!-- Footer -->
        <div class="side-menu-footer">
            <div class="version-text">SUBH GAMING</div>
        </div>
    </div>

    <!-- Market Timetable Modal Popup -->
    <div id="marketTimeModal" class="time-modal-overlay" onclick="closeMarketTimeModal(event)">
        <div class="time-modal-content" onclick="event.stopPropagation();">
            <div class="time-modal-header">
                <span>🪙</span> <span id="mModalTitle">KARNATAKA DAY</span> <span>🪙</span>
            </div>
            <div class="time-modal-body">
                <div class="time-modal-status-bar">
                    <span class="day-name" id="mModalDay"><?= date('l') ?></span> | 
                    <span class="status-msg" id="mModalStatus">Betting is closed for today</span>
                </div>
                <div class="time-modal-boxes-wrap">
                    <div class="time-box">
                        <div class="time-box-title">OPEN</div>
                        <div class="time-box-row">
                            <div class="time-box-label">OPEN RESULT TIME</div>
                            <div class="time-box-value" id="mModalOpenResult">10:10 AM</div>
                        </div>
                        <div class="time-box-divider"></div>
                        <div class="time-box-row">
                            <div class="time-box-label">OPEN BID LAST TIME</div>
                            <div class="time-box-value" id="mModalOpenBid">10:00 AM</div>
                        </div>
                    </div>
                    <div class="time-box">
                        <div class="time-box-title">CLOSE</div>
                        <div class="time-box-row">
                            <div class="time-box-label">CLOSE RESULT TIME</div>
                            <div class="time-box-value" id="mModalCloseResult">11:10 AM</div>
                        </div>
                        <div class="time-box-divider"></div>
                        <div class="time-box-row">
                            <div class="time-box-label">CLOSE BID LAST TIME</div>
                            <div class="time-box-value" id="mModalCloseBid">11:00 AM</div>
                        </div>
                    </div>
                </div>
                <button type="button" class="time-modal-ok-btn" onclick="closeMarketTimeModal()">OK</button>
            </div>
        </div>
    </div>

    <nav class="bottom-menu">
        <a href="user_result.php" class="menu-item">
            <img src="icons/bid_icon.svg" alt="My Bids" class="nav-icon">
            <span><?= __("My Bids") ?></span>
        </a>
        <a href="price_chart.php" class="menu-item active">
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

    <script>
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

    function openMarketTimeModal(title, day, statusMsg, isOpen, openResult, openBid, closeResult, closeBid) {
        document.getElementById('mModalTitle').innerText = title;
        document.getElementById('mModalDay').innerText = day;
        var sEl = document.getElementById('mModalStatus');
        sEl.innerText = statusMsg;
        if (isOpen) {
            sEl.className = 'status-msg status-open-msg';
        } else {
            sEl.className = 'status-msg status-closed-msg';
        }
        document.getElementById('mModalOpenResult').innerText = openResult;
        document.getElementById('mModalOpenBid').innerText = openBid;
        document.getElementById('mModalCloseResult').innerText = closeResult;
        document.getElementById('mModalCloseBid').innerText = closeBid;

        var modal = document.getElementById('marketTimeModal');
        modal.style.display = 'flex';
    }

    function closeMarketTimeModal(e) {
        if (e && e.target && e.target !== e.currentTarget && !e.target.classList.contains('time-modal-ok-btn')) {
            return;
        }
        var modal = document.getElementById('marketTimeModal');
        modal.style.display = 'none';
    }

    /* SIDE MENU TOGGLE */
    function openM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();
        var sideMenu = document.getElementById('sideMenu');
        var sideOverlay = document.getElementById('sideOverlay');
        if (sideMenu) sideMenu.classList.add('open');
        if (sideOverlay) sideOverlay.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    function closeM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(true); } if(e && e.preventDefault) e.preventDefault();
        var sideMenu = document.getElementById('sideMenu');
        var sideOverlay = document.getElementById('sideOverlay');
        if (sideMenu) sideMenu.classList.remove('open');
        if (sideOverlay) sideOverlay.style.display = 'none';
        document.body.style.overflow = '';
    }
    window.openM = openM;
    window.closeM = closeM;

    document.addEventListener('DOMContentLoaded', function() {
        var menuBtn = document.getElementById('menuToggle');
        var sideMenu = document.getElementById('sideMenu');
        var sideOverlay = document.getElementById('sideOverlay');
        var sideClose = document.getElementById('sideCloseBtn');
        var liveSupportBtn = document.getElementById('liveSupportBtn');

        if (menuBtn) menuBtn.addEventListener('click', openM);
        if (sideOverlay) sideOverlay.addEventListener('click', closeM);
        if (sideClose) sideClose.addEventListener('click', closeM);

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

        refreshSupportDot();
        setInterval(refreshSupportDot, 3000);

        var shareBtn = document.getElementById('shareBtn');
        if (shareBtn) shareBtn.addEventListener('click', function() {
            closeM();
            var url = "https://subhgaming.online/download.php";
            var fullText = "Market Price \n♠️*Game Rate*♠️ \n\nDownload App:\n" + url;
            if (navigator.share) {
                navigator.share({ text: fullText });
            } else {
                window.location.href = 'whatsapp://send?text=' + encodeURIComponent(fullText);
            }
        });
    });

    // Popup Logic
    function handleMarketClick(el, link, isOpenPHP) {
        if (document.body.dataset.deactive === '1') return;
        var isClosed = el.classList.contains('closed-market');
        if (!isClosed) {
            window.location.href = link;
        } else {
            if (navigator.vibrate) { navigator.vibrate(100); }
            if (window.AndroidNotify && typeof window.AndroidNotify.vibrate === 'function') {
                window.AndroidNotify.vibrate(100);
            }
            try {
                var closeSound = new Audio('sound/closed.wav');
                closeSound.volume = 0.2;
                closeSound.play().catch(function(e){});
            } catch(err) {}
            var popup = document.getElementById('closedPopup');
            var popupTitle = document.getElementById('closedPopupTitle');
            var popupMessage = document.getElementById('closedPopupMessage');
            var isHoliday = el.dataset.holiday == '1';

            if (isHoliday) {
                popupTitle.innerText = '<?= __("Market Holiday") ?>';
                popupMessage.innerText = '<?= __("THIS GAME IS CURRENTLY ON HOLIDAY") ?>';
            } else {
                popupTitle.innerText = '<?= __("Market Closed") ?>';
                popupMessage.innerText = '<?= __("THIS GAME IS CURRENTLY CLOSED") ?>';
            }
            popup.style.display = 'flex';
            setTimeout(function() { 
                popup.classList.add('show'); 
                var innerBox = document.getElementById('closedPopupInnerBox');
                if(innerBox) { innerBox.style.opacity = '1'; innerBox.style.transform = 'scale(1)'; }
            }, 10);

            if(window.closedPopupTimeout) clearTimeout(window.closedPopupTimeout);
            window.closedPopupTimeout = setTimeout(function() {
                closeClosedPopup();
            }, 2000);
        }
    }

    function closeClosedPopup(e) {
        var popup = document.getElementById('closedPopup');
        if (popup) popup.classList.remove('show');
        var innerBox = document.getElementById('closedPopupInnerBox');
        if(innerBox) { innerBox.style.opacity = '0'; innerBox.style.transform = 'scale(0.9)'; }
        setTimeout(function() { if (popup) popup.style.display = 'none'; }, 300);
    }

    // Market Tick
    function tick() {
        var serverStartM = <?= $nowMinServer ?>;
        var jsStartMs = Date.now();
        setInterval(function() {
            var elapsedM = Math.floor((Date.now() - jsStartMs) / 60000);
            var curM = (serverStartM + elapsedM) % 1440;
                var isUserDeactive = document.body.dataset.deactive === '1';
                var rows = document.querySelectorAll('.market-card-custom');
                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i];
                    if (row.dataset.holiday == '1') continue;
                    var o = +row.dataset.open, c = +row.dataset.close;
                    var open = (o < c) ? (curM < c) : (curM >= o || curM < c);

                    var finalOpen = open && !isUserDeactive;
                    var playRight = row.querySelector('.market-card-right');
                    var clockIcon = row.querySelector('.clock-icon');
                    var statusText = row.querySelector('.market-card-status span');
                    if (finalOpen) {
                        row.classList.remove('closed-market');
                        row.classList.add('open-market');
                        row.style.order = '1';
                        if (playRight) {
                            playRight.className = 'market-card-right play-bg-open';
                        }
                        if (clockIcon) {
                            clockIcon.className = 'fi fi-rr-alarm-clock clock-icon clock-open';
                        }
                        if (statusText) {
                            statusText.className = 'status-text-running';
                            statusText.innerText = '<?= __("Running Now") ?>';
                        }
                    } else {
                        row.classList.remove('open-market');
                        row.classList.add('closed-market');
                        row.style.order = '2';
                        if (playRight) {
                            playRight.className = 'market-card-right play-bg-closed';
                        }
                        if (clockIcon) {
                            clockIcon.className = 'fi fi-rr-alarm-clock clock-icon clock-closed';
                        }
                        if (statusText) {
                            statusText.className = 'status-text-closed';
                            statusText.innerText = '<?= __("Betting Closed for Today") ?>';
                        }
                    }
                }
            }, 30000);
        }
        var upBtn = document.createElement('button');
        upBtn.className = 'back-to-top';
        upBtn.innerHTML = '<i class="fa-solid fa-arrow-up"></i>';
        document.body.appendChild(upBtn);

        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) upBtn.style.display = 'flex';
            else upBtn.style.display = 'none';
        }, { passive: true });

        upBtn.onclick = function() { window.scrollTo({ top: 0, behavior: 'smooth' }); };

        tick();

        /* ✅ Real-time Balance & Notice Polling (Standardized) */
        (function() {
            var KEY_SEEN = 'last_notice_seen';
            var notifyDot = document.getElementById('notifyDot');
            var notifyLink = document.getElementById('notifyLink');
            
            function pollNotice() {
                fetch('notice_status.php', { cache: 'no-store' })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (!d || typeof d.id === 'undefined') return;
                        var lastSeen = +localStorage.getItem(KEY_SEEN) || 0;
                        if (notifyDot) notifyDot.style.display = (+d.id > lastSeen) ? 'block' : 'none';
                    }).catch(function() {});
            }
            
            if (notifyLink) {
                notifyLink.addEventListener('click', function() {
                    if (notifyDot) notifyDot.style.display = 'none';
                });
            }

            function pollWallet() {
                try {
                    fetch('ajax_wallet.php', { cache: 'no-store' })
                        .then(function(r) { return r.json(); })
                        .then(function(d) {
                            if (d && d.ok && d.balances) {
                                var total = parseFloat(d.balances.total || '0');
                                document.body.dataset.wallet = Math.round(total);
                                var wbEl = document.getElementById('walletVal');
                                if (wbEl) wbEl.textContent = Math.round(total);
                            }
                        }).catch(function(e) {});
                } catch (e) {}
                setTimeout(pollWallet, 15000);
            }

            setTimeout(pollNotice, 2000);
            setInterval(pollNotice, 60000);
            setTimeout(pollWallet, 5000);
        })();
    </script>

    <!-- Pink Theme Win Celebration Modal -->
    <div id="pinkWinModal" class="pink-win-modal-overlay" onclick="if(event.target===this) closeWinModal();">
        <div class="pink-win-modal-card">
            <button type="button" class="pink-win-modal-close" onclick="closeWinModal()">&times;</button>
            
            <div class="pink-win-emojis">🎊 🎉 ✨ 🎉 🎊</div>
            
            <div class="pink-win-trophy-ring">
                <span class="pink-win-trophy-emoji">🏆</span>
            </div>
            
            <h2 class="pink-win-title"><?= __('Congratulations!') ?></h2>
            <p class="pink-win-subtitle"><?= __('Today is Your Lucky Day!') ?> 🍀</p>
            
            <div class="pink-win-amount-badge">
                <?= __('You Won') ?> <span id="winModalAmount">₹0</span>
            </div>
            
            <div class="pink-win-stats-box">
                <p class="pink-win-stats">
                    <span id="winModalBetsCount">1 winning bet</span> today • Good Luck! 🎯
                </p>
            </div>
            
            <button type="button" class="pink-win-action-btn" onclick="closeWinModal()">
                🎉 <?= __('Awesome!') ?>
            </button>
        </div>
    </div>

    <script>
        var winModalTimer = null;
        var winAudio = null;

        function showWinModal(amount, todayCount) {
            var modal = document.getElementById('pinkWinModal');
            var amountEl = document.getElementById('winModalAmount');
            var betsEl = document.getElementById('winModalBetsCount');
            
            if (!modal) return;
            
            if (amountEl) {
                var num = parseFloat(amount || 0);
                var formatted = (num % 1 === 0) ? num.toLocaleString('en-IN') : num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                amountEl.textContent = '₹' + formatted;
            }
            if (betsEl) {
                var cnt = parseInt(todayCount || 1, 10);
                betsEl.textContent = cnt + (cnt === 1 ? ' winning bet' : ' winning bets');
            }
            
            modal.style.display = 'flex';
            void modal.offsetWidth; // Force reflow
            modal.classList.add('show');
            
            /* Play win sound (sound/win.mp3) */
            try {
                if (!winAudio) {
                    winAudio = new Audio('sound/win.mp3');
                }
                winAudio.currentTime = 0;
                var playPromise = winAudio.play();
                if (playPromise !== undefined) {
                    playPromise.catch(function(e) {});
                }
            } catch (e) {}

            if (navigator.vibrate) {
                try { navigator.vibrate([120, 60, 120]); } catch(e){}
            }
            
            if (winModalTimer) clearTimeout(winModalTimer);
            
            /* Auto hide after 4 seconds (4000ms) */
            winModalTimer = setTimeout(function() {
                closeWinModal();
            }, 4000);
        }

        function closeWinModal() {
            var modal = document.getElementById('pinkWinModal');
            if (!modal) return;
            if (winModalTimer) {
                clearTimeout(winModalTimer);
                winModalTimer = null;
            }

            /* Stop/pause sound when modal hides */
            if (winAudio) {
                try {
                    winAudio.pause();
                    winAudio.currentTime = 0;
                } catch (e) {}
            }

            modal.classList.remove('show');
            setTimeout(function() {
                modal.style.display = 'none';
            }, 350);
        }

        function checkNewWinModal() {
            fetch('user_dashboard.php?action=check_win_modal', { cache: 'no-store' })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d && d.has_win) {
                        showWinModal(d.win_amount, d.today_win_count);
                    }
                })
                .catch(function(e) {});
        }

        /* Check for win every 8 seconds */
        setInterval(checkNewWinModal, 8000);

        <?php if (!empty($initialWinData)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                showWinModal(<?= (float)$initialWinData['win_amount'] ?>, <?= (int)$initialWinData['today_win_count'] ?>);
            }, 400);
        });
        <?php endif; ?>
    </script>
  <script src="assets/js/user_notice_tracker.js?v=4"></script>
</body>

</html>

