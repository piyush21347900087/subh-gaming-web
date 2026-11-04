<?php
require_once 'includes/lang.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';

/* ✅ No-cache (WebView friendly) */
header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/* Safe Include */
if (file_exists('includes/user_ping.php'))
  require_once 'includes/user_ping.php';
if (file_exists('includes/live_ping.php'))
  require_once 'includes/live_ping.php';

if (!isset($_SESSION['user']['id'])) {
  header('Location: user_login.php');
  exit;
}

$db = db();
$uid = (int) $_SESSION['user']['id'];




/* ===================== LOGIC FROM USER_WALLET ===================== */

// 1. Fetch User Code & Name
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
// Mapping for existing result page logic
$LLCode = $code;

$userName = $_SESSION['user']['name'] ?? '';
if ($userName === '') {
  $userName = fetch_one_val($db, 'SELECT name FROM users WHERE id = ?', 'i', [$uid]) ?: $code;
}
$userMobile = fetch_one_val($db, 'SELECT mobile_no FROM users WHERE id = ?', 'i', [$uid]) ?: ($_SESSION['user']['mobile_no'] ?? '');
$userCreatedAt = fetch_one_val($db, 'SELECT created_at FROM users WHERE id = ?', 'i', [$uid]) ?: '';
$userSince = $userCreatedAt ? date('d-m-Y', strtotime($userCreatedAt)) : '';

$isDeactive = (isset($_SESSION['user']['is_active']) && (int) $_SESSION['user']['is_active'] === 0);

$whatsapp_no_1 = '';
$whatsapp_no_2 = '';
$waSupportUrl = "https://wa.me/917020691217";
try {
    $rsSettings = $db->query("SELECT whatsapp_no_1, whatsapp_no_2 FROM admin_payment_details WHERE id = 1 LIMIT 1");
    if ($rsSettings && $rowSettings = $rsSettings->fetch_assoc()) {
        $whatsapp_no_1 = $rowSettings['whatsapp_no_1'] ?? '';
        $whatsapp_no_2 = $rowSettings['whatsapp_no_2'] ?? '';
        $waAdminDigits = preg_replace('/\D/', '', $whatsapp_no_1 ?: $whatsapp_no_2);
        if (strlen($waAdminDigits) === 10) { $waAdminDigits = '91' . $waAdminDigits; }
        if (empty($waAdminDigits)) { $waAdminDigits = '917020691217'; }
        $waSupportUrl = "https://wa.me/" . $waAdminDigits;
    }
} catch (Throwable $e) {
}

// 2. Refresh isDeactive from DB
$dbStatus = fetch_one_val($db, 'SELECT is_active FROM users WHERE id = ?', 'i', [$uid]);
if ($dbStatus !== null) {
  $isActiveVal = (int) $dbStatus;
  $isDeactive = ($isActiveVal === 0);
  $_SESSION['user']['is_active'] = $isActiveVal;
}

// 3. Balance Calculation (for Header)
$depositDone = (float) (fetch_one_val($db, "SELECT IFNULL(SUM(amount + bonus_amount),0) FROM deposit WHERE status='Done' AND user_id=?", 's', [$code]) ?? 0);
$withdrawMinus = (float) (fetch_one_val($db, "SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE user_id=? AND LOWER(status) IN ('pending','approved','done')", 's', [$code]) ?? 0);
$walletBalance = $depositDone - $withdrawMinus;
$winBalance = (float) (fetch_one_val($db, "SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id=?", 's', [$code]) ?? 0);
$totalBalance = (float) $walletBalance + (float) $winBalance;
$showBank = (int) ($db->query("SELECT show_bank FROM admin_payment_details WHERE id=1")->fetch_row()[0] ?? 1);



/* Share Data for Sidebar */
$shareTitle = "Market Price";
$shareDownloadLink = "https://subhgaming.online/download.php";
$shareText = "♠️*Game Rate*♠️\n\nDownload App:\n" . $shareDownloadLink;
$shareImgCandidates = ["images/share.png", "share.png"];

/* Side menu identity fallback */
$sideUserName = $userName ?? ($user['name'] ?? ($_SESSION['user']['name'] ?? 'User'));
$sideUserMobile = $userMobile ?: ($user['mobile_no'] ?? ($uniqueUserId ?? ($code ?? ($user['user_id'] ?? ''))));
$sideUserSince = $userSince;
if ($sideUserMobile === '' || $sideUserSince === '') {
  try {
    if (isset($db, $uid)) {
      $stmtSide = $db->prepare('SELECT mobile_no, created_at FROM users WHERE id=? LIMIT 1');
      $stmtSide->bind_param('i', $uid);
      $stmtSide->execute();
      $sideRow = $stmtSide->get_result()->fetch_assoc();
      $stmtSide->close();
      if ($sideUserMobile === '' && !empty($sideRow['mobile_no']))
        $sideUserMobile = $sideRow['mobile_no'];
      if ($sideUserSince === '' && !empty($sideRow['created_at']))
        $sideUserSince = date('d-m-Y', strtotime($sideRow['created_at']));
    }
  } catch (Throwable $e) {
  }
}
$db->close();

// Fetch Game Rates for Sharing
$gameRates = [];
try {
    $dbR = db();
    $resRates = $dbR->query("SELECT game_type, play_amount, win_amount FROM game_rates ORDER BY id ASC");
    if ($resRates) {
        while ($r = $resRates->fetch_assoc()) {
            $gameRates[] = $r;
        }
    }
    $dbR->close();
} catch (Throwable $e) {}

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
    <title>Results – SUBH GAMING</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Poppins:wght@300;400;500;600;700;800&family=Roboto:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-brands/css/uicons-brands.css">
    <style>
        :root {
            --primary-bg: #000000;
            --card-bg: #121212;
            --accent-main: #e53935;
            --accent-red: #e60000;
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

        .content {
            max-width: 480px;
            margin: 0 auto;
            padding: 15px;
        }

        /* ── Rate Header ── */
        .rate-header-wrapper {
            margin-top: 4px;
            margin-bottom: 18px;
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
            font-weight: 700;
            text-transform: capitalize;
            letter-spacing: 0.2px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
            filter: drop-shadow(0 2px 5px rgba(0, 0, 0, 0.4));
            box-sizing: border-box;

            border-style: solid;
            border-width: 17px;
            border-image-source: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='72' height='72' viewBox='0 0 72 72'%3E%3Cpath d='M16 0 L56 0 A16 16 0 0 0 72 16 L72 56 A16 16 0 0 0 56 72 L16 72 A16 16 0 0 0 0 56 L0 16 A16 16 0 0 0 16 0 Z' fill='%23800000'/%3E%3Cpath d='M20.8 5.5 L51.2 5.5 A21.5 21.5 0 0 0 66.5 20.8 L66.5 51.2 A21.5 21.5 0 0 0 51.2 66.5 L20.8 66.5 A21.5 21.5 0 0 0 5.5 51.2 L5.5 20.8 A21.5 21.5 0 0 0 20.8 5.5 Z' fill='none' stroke='%23d93b3b' stroke-width='1.3'/%3E%3C/svg%3E");
            border-image-slice: 25 fill;
            border-image-repeat: stretch;
            background: transparent;
        }

        /* ── Bid Menu Cards (100% Matching user_profile.php) ── */
        .bid-menu-card {
            background: #ffffff;
            border-radius: 13px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 10px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .bid-menu-card:active {
            transform: scale(0.98);
            background: #fafafa;
        }

        .bid-menu-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .bid-menu-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .bid-menu-icon svg,
        .bid-menu-icon img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .bid-menu-icon svg.icon-game-result {
            width: 38px;
            height: 38px;
        }

        .bid-menu-text {
            display: flex;
            flex-direction: column;
        }

        .bid-menu-title {
            font-size: 14px;
            font-weight: 700;
            color: #1f2937;
            line-height: 1.2;
        }

        .bid-menu-sub {
            font-size: 11px;
            font-weight: 400;
            color: #6b7280;
            line-height: 1.3;
            margin-top: 3px;
        }

        .bid-menu-arrow {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #800000;
            font-size: 12px;
            font-weight: 900;
            flex-shrink: 0;
            margin-left: 10px;
        }

        /* Bottom Menu (Matching Screenshot Design) */
        /* ── BOTTOM MENU 100% Exact Copy of user_profile.php / user_dashboard.php ── */
        nav.bottom-menu {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 62px;
            background-color: #000000 !important;
            background-image: none !important;
            border-top: 1px solid #1a1a1a;
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
            filter: brightness(0) invert(1) !important;
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
            padding: 50px 15px 20px 15px;
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
        .side-menu-item:hover { background: #1a1a1a; }

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

<body data-wallet="<?= htmlspecialchars(number_format((float) $totalBalance, 2, '.', ''), ENT_QUOTES) ?>"
  data-share-title="<?= htmlspecialchars($shareTitle, ENT_QUOTES) ?>"
  data-share-text="<?= htmlspecialchars($shareText, ENT_QUOTES) ?>"
  data-share-link="<?= htmlspecialchars($shareDownloadLink, ENT_QUOTES) ?>"
  data-share-imgs='<?= htmlspecialchars(json_encode($shareImgCandidates), ENT_QUOTES) ?>'
  style="<?= $isDeactive ? 'background:#000;padding:0;' : '' ?>">

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

    <!-- Rate Header Banner -->
    <div class="rate-header-wrapper">
        <div class="rate-header">
            <img src="icons/gold_coin.svg" alt="coins" style="width: 22px; height: 22px; object-fit: contain;">
            <span><?= __("Game Bids") ?></span>
            <img src="icons/gold_coin.svg" alt="coins" style="width: 22px; height: 22px; object-fit: contain;">
        </div>
    </div>

    <!-- 6 Bid & Result Cards -->
    <div class="bid-menu-list">
      <!-- 1. Bid History -->
      <a href="user_bid_history.php" class="bid-menu-card">
        <div class="bid-menu-left">
          <div class="bid-menu-icon">
            <svg width="34" height="34" viewBox="-2 -2 83 84" xmlns="http://www.w3.org/2000/svg">
              <path fill="#7a3618" fill-rule="evenodd" d="M34.58,37.29c-11.09,9.56-21.59,17.43-32.08,27.64-3.74,3.64-2.93,8.86.11,11.75,2.75,2.61,7.38,3.3,11.28-.46l27.74-31.45-7.04-7.49Z"/>
              <path fill="#a24b23" fill-rule="evenodd" d="M2.52,76.61l35.43-35.58-3.52-3.6c-11.04,9.5-21.49,17.34-31.93,27.51-3.7,3.6-2.95,8.77.02,11.67"/>
              <rect fill="#a24b23" x="14.27" y="12.06" width="37.29" height="8.72" transform="translate(-1.79 28.6) rotate(-45.85)"/>
              <rect fill="#7a3618" x="43.75" y="41.53" width="37.29" height="8.72" transform="translate(-13.99 58.69) rotate(-45.85)"/>
              <polygon fill="#febc11" fill-rule="evenodd" points="69.22 32.6 46.16 9.04 26.48 29.31 30.19 33.1 49.54 52.87 69.22 32.6"/>
              <polygon fill="#f7941d" fill-rule="evenodd" points="38.06 41.14 41.4 44.55 49.54 52.87 69.22 32.6 57.88 21.01 38.06 41.14"/>
              <path fill="#d05a27" fill-rule="evenodd" d="M27.22,43.5l4.08,4.21,6.65-6.68-3.52-3.6c-2.43,2.09-4.83,4.1-7.2,6.07"/>
              <path fill="#a04b23" fill-rule="evenodd" d="M35.49,51.73l-4.2-4.03,6.66-6.68,3.68,3.76c-2.08,2.44-4.17,4.56-6.13,6.95"/>
              <path fill="#a24a24" fill-rule="evenodd" d="M48.65,65.02h22.78c3.91,0,7.1,3.2,7.1,7.1v7.1h-36.99v-7.1c0-3.91,3.2-7.1,7.11-7.1"/>
              <path fill="#793618" fill-rule="evenodd" d="M60.26,65.02v14.21h18.27v-7.1c0-3.91-3.2-7.1-7.1-7.1h-11.16Z"/>
            </svg>
          </div>
          <div class="bid-menu-text">
            <div class="bid-menu-title"><?= __("Bid History") ?></div>
            <div class="bid-menu-sub"><?= __("View your main games bidding history") ?></div>
          </div>
        </div>
        <div class="bid-menu-arrow">
          <i class="fas fa-chevron-right"></i>
        </div>
      </a>

      <!-- 2. Game Results -->
      <a href="user_wining.php" class="bid-menu-card">
        <div class="bid-menu-left">
          <div class="bid-menu-icon">
            <svg class="icon-game-result" width="38" height="38" viewBox="255 345 95 95" xmlns="http://www.w3.org/2000/svg">
              <path fill="none" stroke="#010101" stroke-miterlimit="22.93" stroke-width=".57px" d="M283.26,387.51c.77-3.26,4.24-8.88,6.43-10.8,1.11-1.05,2.6-1.23,4.4-.7l2.16-2.35"/>
              <path fill="#27a752" fill-rule="evenodd" d="M294.09,376.01l.6-.65,1.56-1.7c-.05.06-.11.11-.16.17-1.26-5.47-2.68-10.71-3.24-17.36-6.37,3.55-14.12,4.64-18.81,2.89.91,2.93,2.06,5.31,3.29,7.67,2.84,5.1,5.93,9.6,9.22,13.61,1.08-1.66,2.22-3.12,3.15-3.93,1.11-1.05,2.6-1.23,4.4-.7"/>
              <path fill="#0a9748" fill-rule="evenodd" d="M286.54,380.64c-3.29-4.01-6.38-8.51-9.22-13.61.16.31.33.62.49.93-2.49-.75-4.96-1.65-7.42-2.66.1,6.05,2.6,9.63,5.91,11.72.1.06.21.13.31.19l.02.06c2.83,2.18,5.61,4.15,8.36,6,.47-.89,1-1.79,1.55-2.63"/>
              <path fill="#0f6d38" fill-rule="evenodd" d="M285,383.27c-2.75-1.86-5.54-3.83-8.36-6l2.87,10.26c1.26-.1,2.52-.13,3.77-.08,0,.02,0,.04-.01.06.28-1.19.93-2.7,1.74-4.23"/>
              <path fill="#27a752" fill-rule="evenodd" d="M320.07,393.6h-1.48s-4.99,5.5-4.99,5.5c6.33,3.34,12.11,8.38,17.64,14.19,0,0,0,0,0,0l4.44,1.38c.82-5.67,2.61-11.35,5.92-17.02-7.66-1.33-13.49-3.92-21.42-4.04-.03,0-.07,0-.1,0"/>
              <path fill="#069848" fill-rule="evenodd" d="M331.23,413.29c-5.52-5.81-11.31-10.85-17.64-14.19l-1.31,1.44c-.98.95-2.16,1.3-3.55,1.05-.29-.05-.55-.12-.79-.2,1.4,2.4,2.63,4.78,3.75,7.15,0,0,0,0,0,0l.41.9,6.98,15.24c5.96-.21,11.14-2.18,15.66-5.67-1.15-1.82-2.36-3.9-3.51-5.72"/>
              <path fill="#fab070" fill-rule="evenodd" d="M305.45,374.12c.26-3.32-4.05-5.58-6.11-3.71-1.04,1.11-2.06,2.15-3.09,3.24l-1.56,1.7c.83.35,2.03.76,3.21.98,1.82.33,3.64.48,4.22,1.4l3.33-3.55s0-.04,0-.06"/>
              <path fill="#fab070" fill-rule="evenodd" d="M306.4,374.65l-3.67,4.35c.66,2.36-1.68,4.54-4.8,5.4l-1.69,2.32c-.45.62-.9,1.45-.59,2.36.68,1.99,3.28,3.71,5.2,1.79l10.27-11.93c0-.06.02-.12.02-.19.26-2.22-2.86-4.6-4.75-4.1"/>
              <path fill="#fab070" fill-rule="evenodd" d="M312.05,379.71c-3.27,3.8-6.54,7.6-9.81,11.4-.72.84-.98,1.84-.56,2.91.76,1.38,2.32,2.57,3.94,1.91.79-.32,1.23-1.03,2.01-1.87l8.41-9.08s.02-.07.04-.11c1.12-2.35-2.18-5.56-4.02-5.17"/>
              <path fill="#fab070" fill-rule="evenodd" d="M316.92,385.55l-9.06,10.11c-.64.72-.96,1.27-.86,2.31.22,2.23,3.17,3.32,4.78,1.63,1.82-1.92,3.77-4.28,5.72-6.38.31-.34.6-.69.92-1.01l.22-.29c.11-.01.2.02.26.08.38.33,0,1.24-.27,1.57l-.04.05h1.48c-.19-.01-.38-.01-.57-.01.67-1.19,1.1-2.81,1.1-3.99,0-2.18-1.62-4.08-3.68-4.06"/>
              <path fill="#fab070" fill-rule="evenodd" d="M300.86,395.09c-.59-1.32-.72-1.92-2.41-2.12-1.6-.19-2.93-1.44-3.48-2.95-.42-1.16-.76-1.68-1.81-2.35.55-.93.83-2.02.82-3.28-.47-.08-1.23-.58-1.24-1.09,0-.18.07-.25.29-.17,1.42.48,3.05.61,4.51.24,1.86-.46,4.33-1.51,4.33-3.63,0-1.23-.56-1.95-1.71-2.15-2.04-.24-4.06-.8-6.07-1.57-1.8-.53-3.29-.35-4.4.7-.92.81-2.07,2.27-3.15,3.93-.55.84-1.08,1.74-1.55,2.63-.81,1.53-1.45,3.04-1.74,4.23-.02.08-.03.15-.04.23-.42,2.17-.27,3.49.4,5.59l1.94,6.14c.8,2.53.81,5.29-.41,7.7l-14.81,29.21h24.71l9.43-21.88c2.66-1.74,5.09-3.71,7.23-5.97-1.12-2.37-2.34-4.75-3.75-7.15-1.02-.35-1.66-.96-2.4-1.98-1.17-1.42-1.34-1.68-2.93-2.52-.75-.4-1.44-1.09-1.75-1.8"/>
              <path fill="#f36b2d" fill-rule="evenodd" d="M289.88,405.04s.09.02.14.02c3.38.65,8.82,4.11,10.51,8.71.16.44-.19,1.03-.62,1.21-.53.22-1.2.01-1.35-.4-1.29-3.51-4.79-6.36-8.92-7.44-1.12-.3-1.25-2.01.25-2.09"/>
              <path fill="#f36b2d" fill-rule="evenodd" d="M300.15,377.58c1.14.2,1.71.92,1.71,2.15,0,2.12-2.47,3.17-4.33,3.63-1.46.37-3.09.24-4.51-.24-.21-.08-.29,0-.29.17.02.51.77,1.01,1.24,1.09.01,1.26-.28,2.35-.82,3.28,1.05.67,1.39,1.19,1.81,2.35.55,1.51,1.88,2.76,3.48,2.95,1.7.2,1.82.8,2.41,2.12.32.71,1.01,1.4,1.75,1.8,1.59.84,1.76,1.1,2.93,2.52.74,1.01,1.38,1.63,2.4,1.98.24.08.5.15.79.2,1.39.25,2.58-.1,3.55-1.05l1.31-1.44,4.99-5.49.04-.05c.27-.33.65-1.24.27-1.57-.06-.06-.15-.09-.26-.08l-.22.29c-.31.32-.6.68-.92,1.01-1.95,2.1-3.9,4.46-5.72,6.38-1.61,1.69-4.56.6-4.78-1.63-.1-1.03.22-1.59.86-2.31l9.06-10.11c-.09,0-.18,0-.28.01-.57.07-.74-.14-.61-.58l-8.41,9.08c-.78.84-1.22,1.55-2.01,1.87-1.61.65-3.18-.54-3.94-1.91-.42-1.07-.16-2.07.56-2.91,3.27-3.8,6.54-7.6,9.81-11.4-.07.01-.13.03-.19.06-.8.16-.79-.33-.73-.83l-10.27,11.93c-1.91,1.92-4.52.2-5.2-1.79-.31-.91.13-1.73.59-2.36l1.69-2.32c3.12-.87,5.46-3.04,4.8-5.4l3.67-4.35c-.06.02-.12.04-.18.06-.48.12-.75-.03-.77-.53l-3.33,3.55c-.59-.92-2.4-1.06-4.22-1.4-1.18-.22-2.39-.63-3.21-.98l-.6.65c2.01.77,4.03,1.33,6.07,1.57"/>
            </svg>
          </div>
          <div class="bid-menu-text">
            <div class="bid-menu-title"><?= __("Game Results") ?></div>
            <div class="bid-menu-sub"><?= __("View your main games result history") ?></div>
          </div>
        </div>
        <div class="bid-menu-arrow">
          <i class="fas fa-chevron-right"></i>
        </div>
      </a>

      <!-- 3. Starline Bid History -->
      <a href="user_bid_history.php?type=starline" class="bid-menu-card">
        <div class="bid-menu-left">
          <div class="bid-menu-icon">
            <svg width="34" height="34" viewBox="-2 -2 83 84" xmlns="http://www.w3.org/2000/svg">
              <path fill="#7a3618" fill-rule="evenodd" d="M34.58,37.29c-11.09,9.56-21.59,17.43-32.08,27.64-3.74,3.64-2.93,8.86.11,11.75,2.75,2.61,7.38,3.3,11.28-.46l27.74-31.45-7.04-7.49Z"/>
              <path fill="#a24b23" fill-rule="evenodd" d="M2.52,76.61l35.43-35.58-3.52-3.6c-11.04,9.5-21.49,17.34-31.93,27.51-3.7,3.6-2.95,8.77.02,11.67"/>
              <rect fill="#a24b23" x="14.27" y="12.06" width="37.29" height="8.72" transform="translate(-1.79 28.6) rotate(-45.85)"/>
              <rect fill="#7a3618" x="43.75" y="41.53" width="37.29" height="8.72" transform="translate(-13.99 58.69) rotate(-45.85)"/>
              <polygon fill="#febc11" fill-rule="evenodd" points="69.22 32.6 46.16 9.04 26.48 29.31 30.19 33.1 49.54 52.87 69.22 32.6"/>
              <polygon fill="#f7941d" fill-rule="evenodd" points="38.06 41.14 41.4 44.55 49.54 52.87 69.22 32.6 57.88 21.01 38.06 41.14"/>
              <path fill="#d05a27" fill-rule="evenodd" d="M27.22,43.5l4.08,4.21,6.65-6.68-3.52-3.6c-2.43,2.09-4.83,4.1-7.2,6.07"/>
              <path fill="#a04b23" fill-rule="evenodd" d="M35.49,51.73l-4.2-4.03,6.66-6.68,3.68,3.76c-2.08,2.44-4.17,4.56-6.13,6.95"/>
              <path fill="#a24a24" fill-rule="evenodd" d="M48.65,65.02h22.78c3.91,0,7.1,3.2,7.1,7.1v7.1h-36.99v-7.1c0-3.91,3.2-7.1,7.11-7.1"/>
              <path fill="#793618" fill-rule="evenodd" d="M60.26,65.02v14.21h18.27v-7.1c0-3.91-3.2-7.1-7.1-7.1h-11.16Z"/>
            </svg>
          </div>
          <div class="bid-menu-text">
            <div class="bid-menu-title"><?= __("Starline Bid History") ?></div>
            <div class="bid-menu-sub"><?= __("View starline games bidding history") ?></div>
          </div>
        </div>
        <div class="bid-menu-arrow">
          <i class="fas fa-chevron-right"></i>
        </div>
      </a>

      <!-- 4. Starline Game Results -->
      <a href="user_wining.php?type=starline" class="bid-menu-card">
        <div class="bid-menu-left">
          <div class="bid-menu-icon">
            <svg class="icon-game-result" width="38" height="38" viewBox="255 345 95 95" xmlns="http://www.w3.org/2000/svg">
              <path fill="none" stroke="#010101" stroke-miterlimit="22.93" stroke-width=".57px" d="M283.26,387.51c.77-3.26,4.24-8.88,6.43-10.8,1.11-1.05,2.6-1.23,4.4-.7l2.16-2.35"/>
              <path fill="#27a752" fill-rule="evenodd" d="M294.09,376.01l.6-.65,1.56-1.7c-.05.06-.11.11-.16.17-1.26-5.47-2.68-10.71-3.24-17.36-6.37,3.55-14.12,4.64-18.81,2.89.91,2.93,2.06,5.31,3.29,7.67,2.84,5.1,5.93,9.6,9.22,13.61,1.08-1.66,2.22-3.12,3.15-3.93,1.11-1.05,2.6-1.23,4.4-.7"/>
              <path fill="#0a9748" fill-rule="evenodd" d="M286.54,380.64c-3.29-4.01-6.38-8.51-9.22-13.61.16.31.33.62.49.93-2.49-.75-4.96-1.65-7.42-2.66.1,6.05,2.6,9.63,5.91,11.72.1.06.21.13.31.19l.02.06c2.83,2.18,5.61,4.15,8.36,6,.47-.89,1-1.79,1.55-2.63"/>
              <path fill="#0f6d38" fill-rule="evenodd" d="M285,383.27c-2.75-1.86-5.54-3.83-8.36-6l2.87,10.26c1.26-.1,2.52-.13,3.77-.08,0,.02,0,.04-.01.06.28-1.19.93-2.7,1.74-4.23"/>
              <path fill="#27a752" fill-rule="evenodd" d="M320.07,393.6h-1.48s-4.99,5.5-4.99,5.5c6.33,3.34,12.11,8.38,17.64,14.19,0,0,0,0,0,0l4.44,1.38c.82-5.67,2.61-11.35,5.92-17.02-7.66-1.33-13.49-3.92-21.42-4.04-.03,0-.07,0-.1,0"/>
              <path fill="#069848" fill-rule="evenodd" d="M331.23,413.29c-5.52-5.81-11.31-10.85-17.64-14.19l-1.31,1.44c-.98.95-2.16,1.3-3.55,1.05-.29-.05-.55-.12-.79-.2,1.4,2.4,2.63,4.78,3.75,7.15,0,0,0,0,0,0l.41.9,6.98,15.24c5.96-.21,11.14-2.18,15.66-5.67-1.15-1.82-2.36-3.9-3.51-5.72"/>
              <path fill="#fab070" fill-rule="evenodd" d="M305.45,374.12c.26-3.32-4.05-5.58-6.11-3.71-1.04,1.11-2.06,2.15-3.09,3.24l-1.56,1.7c.83.35,2.03.76,3.21.98,1.82.33,3.64.48,4.22,1.4l3.33-3.55s0-.04,0-.06"/>
              <path fill="#fab070" fill-rule="evenodd" d="M306.4,374.65l-3.67,4.35c.66,2.36-1.68,4.54-4.8,5.4l-1.69,2.32c-.45.62-.9,1.45-.59,2.36.68,1.99,3.28,3.71,5.2,1.79l10.27-11.93c0-.06.02-.12.02-.19.26-2.22-2.86-4.6-4.75-4.1"/>
              <path fill="#fab070" fill-rule="evenodd" d="M312.05,379.71c-3.27,3.8-6.54,7.6-9.81,11.4-.72.84-.98,1.84-.56,2.91.76,1.38,2.32,2.57,3.94,1.91.79-.32,1.23-1.03,2.01-1.87l8.41-9.08s.02-.07.04-.11c1.12-2.35-2.18-5.56-4.02-5.17"/>
              <path fill="#fab070" fill-rule="evenodd" d="M316.92,385.55l-9.06,10.11c-.64.72-.96,1.27-.86,2.31.22,2.23,3.17,3.32,4.78,1.63,1.82-1.92,3.77-4.28,5.72-6.38.31-.34.6-.69.92-1.01l.22-.29c.11-.01.2.02.26.08.38.33,0,1.24-.27,1.57l-.04.05h1.48c-.19-.01-.38-.01-.57-.01.67-1.19,1.1-2.81,1.1-3.99,0-2.18-1.62-4.08-3.68-4.06"/>
              <path fill="#fab070" fill-rule="evenodd" d="M300.86,395.09c-.59-1.32-.72-1.92-2.41-2.12-1.6-.19-2.93-1.44-3.48-2.95-.42-1.16-.76-1.68-1.81-2.35.55-.93.83-2.02.82-3.28-.47-.08-1.23-.58-1.24-1.09,0-.18.07-.25.29-.17,1.42.48,3.05.61,4.51.24,1.86-.46,4.33-1.51,4.33-3.63,0-1.23-.56-1.95-1.71-2.15-2.04-.24-4.06-.8-6.07-1.57-1.8-.53-3.29-.35-4.4.7-.92.81-2.07,2.27-3.15,3.93-.55.84-1.08,1.74-1.55,2.63-.81,1.53-1.45,3.04-1.74,4.23-.02.08-.03.15-.04.23-.42,2.17-.27,3.49.4,5.59l1.94,6.14c.8,2.53.81,5.29-.41,7.7l-14.81,29.21h24.71l9.43-21.88c2.66-1.74,5.09-3.71,7.23-5.97-1.12-2.37-2.34-4.75-3.75-7.15-1.02-.35-1.66-.96-2.4-1.98-1.17-1.42-1.34-1.68-2.93-2.52-.75-.4-1.44-1.09-1.75-1.8"/>
              <path fill="#f36b2d" fill-rule="evenodd" d="M289.88,405.04s.09.02.14.02c3.38.65,8.82,4.11,10.51,8.71.16.44-.19,1.03-.62,1.21-.53.22-1.2.01-1.35-.4-1.29-3.51-4.79-6.36-8.92-7.44-1.12-.3-1.25-2.01.25-2.09"/>
              <path fill="#f36b2d" fill-rule="evenodd" d="M300.15,377.58c1.14.2,1.71.92,1.71,2.15,0,2.12-2.47,3.17-4.33,3.63-1.46.37-3.09.24-4.51-.24-.21-.08-.29,0-.29.17.02.51.77,1.01,1.24,1.09.01,1.26-.28,2.35-.82,3.28,1.05.67,1.39,1.19,1.81,2.35.55,1.51,1.88,2.76,3.48,2.95,1.7.2,1.82.8,2.41,2.12.32.71,1.01,1.4,1.75,1.8,1.59.84,1.76,1.1,2.93,2.52.74,1.01,1.38,1.63,2.4,1.98.24.08.5.15.79.2,1.39.25,2.58-.1,3.55-1.05l1.31-1.44,4.99-5.49.04-.05c.27-.33.65-1.24.27-1.57-.06-.06-.15-.09-.26-.08l-.22.29c-.31.32-.6.68-.92,1.01-1.95,2.1-3.9,4.46-5.72,6.38-1.61,1.69-4.56.6-4.78-1.63-.1-1.03.22-1.59.86-2.31l9.06-10.11c-.09,0-.18,0-.28.01-.57.07-.74-.14-.61-.58l-8.41,9.08c-.78.84-1.22,1.55-2.01,1.87-1.61.65-3.18-.54-3.94-1.91-.42-1.07-.16-2.07.56-2.91,3.27-3.8,6.54-7.6,9.81-11.4-.07.01-.13.03-.19.06-.8.16-.79-.33-.73-.83l-10.27,11.93c-1.91,1.92-4.52.2-5.2-1.79-.31-.91.13-1.73.59-2.36l1.69-2.32c3.12-.87,5.46-3.04,4.8-5.4l3.67-4.35c-.06.02-.12.04-.18.06-.48.12-.75-.03-.77-.53l-3.33,3.55c-.59-.92-2.4-1.06-4.22-1.4-1.18-.22-2.39-.63-3.21-.98l-.6.65c2.01.77,4.03,1.33,6.07,1.57"/>
            </svg>
          </div>
          <div class="bid-menu-text">
            <div class="bid-menu-title"><?= __("Starline Game Results") ?></div>
            <div class="bid-menu-sub"><?= __("View your starline games result history") ?></div>
          </div>
        </div>
        <div class="bid-menu-arrow">
          <i class="fas fa-chevron-right"></i>
        </div>
      </a>

      <!-- 5. Jackpot Bid History -->
      <a href="bid_history2.php" class="bid-menu-card">
        <div class="bid-menu-left">
          <div class="bid-menu-icon">
            <svg width="34" height="34" viewBox="-2 -2 83 84" xmlns="http://www.w3.org/2000/svg">
              <path fill="#7a3618" fill-rule="evenodd" d="M34.58,37.29c-11.09,9.56-21.59,17.43-32.08,27.64-3.74,3.64-2.93,8.86.11,11.75,2.75,2.61,7.38,3.3,11.28-.46l27.74-31.45-7.04-7.49Z"/>
              <path fill="#a24b23" fill-rule="evenodd" d="M2.52,76.61l35.43-35.58-3.52-3.6c-11.04,9.5-21.49,17.34-31.93,27.51-3.7,3.6-2.95,8.77.02,11.67"/>
              <rect fill="#a24b23" x="14.27" y="12.06" width="37.29" height="8.72" transform="translate(-1.79 28.6) rotate(-45.85)"/>
              <rect fill="#7a3618" x="43.75" y="41.53" width="37.29" height="8.72" transform="translate(-13.99 58.69) rotate(-45.85)"/>
              <polygon fill="#febc11" fill-rule="evenodd" points="69.22 32.6 46.16 9.04 26.48 29.31 30.19 33.1 49.54 52.87 69.22 32.6"/>
              <polygon fill="#f7941d" fill-rule="evenodd" points="38.06 41.14 41.4 44.55 49.54 52.87 69.22 32.6 57.88 21.01 38.06 41.14"/>
              <path fill="#d05a27" fill-rule="evenodd" d="M27.22,43.5l4.08,4.21,6.65-6.68-3.52-3.6c-2.43,2.09-4.83,4.1-7.2,6.07"/>
              <path fill="#a04b23" fill-rule="evenodd" d="M35.49,51.73l-4.2-4.03,6.66-6.68,3.68,3.76c-2.08,2.44-4.17,4.56-6.13,6.95"/>
              <path fill="#a24a24" fill-rule="evenodd" d="M48.65,65.02h22.78c3.91,0,7.1,3.2,7.1,7.1v7.1h-36.99v-7.1c0-3.91,3.2-7.1,7.11-7.1"/>
              <path fill="#793618" fill-rule="evenodd" d="M60.26,65.02v14.21h18.27v-7.1c0-3.91-3.2-7.1-7.1-7.1h-11.16Z"/>
            </svg>
          </div>
          <div class="bid-menu-text">
            <div class="bid-menu-title"><?= __("Jackpot Bid History") ?></div>
            <div class="bid-menu-sub"><?= __("View jackpot games bidding history") ?></div>
          </div>
        </div>
        <div class="bid-menu-arrow">
          <i class="fas fa-chevron-right"></i>
        </div>
      </a>

      <!-- 6. Jackpot Game Results -->
      <a href="user_wining.php?type=jackpot" class="bid-menu-card">
        <div class="bid-menu-left">
          <div class="bid-menu-icon">
            <svg class="icon-game-result" width="38" height="38" viewBox="255 345 95 95" xmlns="http://www.w3.org/2000/svg">
              <path fill="none" stroke="#010101" stroke-miterlimit="22.93" stroke-width=".57px" d="M283.26,387.51c.77-3.26,4.24-8.88,6.43-10.8,1.11-1.05,2.6-1.23,4.4-.7l2.16-2.35"/>
              <path fill="#27a752" fill-rule="evenodd" d="M294.09,376.01l.6-.65,1.56-1.7c-.05.06-.11.11-.16.17-1.26-5.47-2.68-10.71-3.24-17.36-6.37,3.55-14.12,4.64-18.81,2.89.91,2.93,2.06,5.31,3.29,7.67,2.84,5.1,5.93,9.6,9.22,13.61,1.08-1.66,2.22-3.12,3.15-3.93,1.11-1.05,2.6-1.23,4.4-.7"/>
              <path fill="#0a9748" fill-rule="evenodd" d="M286.54,380.64c-3.29-4.01-6.38-8.51-9.22-13.61.16.31.33.62.49.93-2.49-.75-4.96-1.65-7.42-2.66.1,6.05,2.6,9.63,5.91,11.72.1.06.21.13.31.19l.02.06c2.83,2.18,5.61,4.15,8.36,6,.47-.89,1-1.79,1.55-2.63"/>
              <path fill="#0f6d38" fill-rule="evenodd" d="M285,383.27c-2.75-1.86-5.54-3.83-8.36-6l2.87,10.26c1.26-.1,2.52-.13,3.77-.08,0,.02,0,.04-.01.06.28-1.19.93-2.7,1.74-4.23"/>
              <path fill="#27a752" fill-rule="evenodd" d="M320.07,393.6h-1.48s-4.99,5.5-4.99,5.5c6.33,3.34,12.11,8.38,17.64,14.19,0,0,0,0,0,0l4.44,1.38c.82-5.67,2.61-11.35,5.92-17.02-7.66-1.33-13.49-3.92-21.42-4.04-.03,0-.07,0-.1,0"/>
              <path fill="#069848" fill-rule="evenodd" d="M331.23,413.29c-5.52-5.81-11.31-10.85-17.64-14.19l-1.31,1.44c-.98.95-2.16,1.3-3.55,1.05-.29-.05-.55-.12-.79-.2,1.4,2.4,2.63,4.78,3.75,7.15,0,0,0,0,0,0l.41.9,6.98,15.24c5.96-.21,11.14-2.18,15.66-5.67-1.15-1.82-2.36-3.9-3.51-5.72"/>
              <path fill="#fab070" fill-rule="evenodd" d="M305.45,374.12c.26-3.32-4.05-5.58-6.11-3.71-1.04,1.11-2.06,2.15-3.09,3.24l-1.56,1.7c.83.35,2.03.76,3.21.98,1.82.33,3.64.48,4.22,1.4l3.33-3.55s0-.04,0-.06"/>
              <path fill="#fab070" fill-rule="evenodd" d="M306.4,374.65l-3.67,4.35c.66,2.36-1.68,4.54-4.8,5.4l-1.69,2.32c-.45.62-.9,1.45-.59,2.36.68,1.99,3.28,3.71,5.2,1.79l10.27-11.93c0-.06.02-.12.02-.19.26-2.22-2.86-4.6-4.75-4.1"/>
              <path fill="#fab070" fill-rule="evenodd" d="M312.05,379.71c-3.27,3.8-6.54,7.6-9.81,11.4-.72.84-.98,1.84-.56,2.91.76,1.38,2.32,2.57,3.94,1.91.79-.32,1.23-1.03,2.01-1.87l8.41-9.08s.02-.07.04-.11c1.12-2.35-2.18-5.56-4.02-5.17"/>
              <path fill="#fab070" fill-rule="evenodd" d="M316.92,385.55l-9.06,10.11c-.64.72-.96,1.27-.86,2.31.22,2.23,3.17,3.32,4.78,1.63,1.82-1.92,3.77-4.28,5.72-6.38.31-.34.6-.69.92-1.01l.22-.29c.11-.01.2.02.26.08.38.33,0,1.24-.27,1.57l-.04.05h1.48c-.19-.01-.38-.01-.57-.01.67-1.19,1.1-2.81,1.1-3.99,0-2.18-1.62-4.08-3.68-4.06"/>
              <path fill="#fab070" fill-rule="evenodd" d="M300.86,395.09c-.59-1.32-.72-1.92-2.41-2.12-1.6-.19-2.93-1.44-3.48-2.95-.42-1.16-.76-1.68-1.81-2.35.55-.93.83-2.02.82-3.28-.47-.08-1.23-.58-1.24-1.09,0-.18.07-.25.29-.17,1.42.48,3.05.61,4.51.24,1.86-.46,4.33-1.51,4.33-3.63,0-1.23-.56-1.95-1.71-2.15-2.04-.24-4.06-.8-6.07-1.57-1.8-.53-3.29-.35-4.4.7-.92.81-2.07,2.27-3.15,3.93-.55.84-1.08,1.74-1.55,2.63-.81,1.53-1.45,3.04-1.74,4.23-.02.08-.03.15-.04.23-.42,2.17-.27,3.49.4,5.59l1.94,6.14c.8,2.53.81,5.29-.41,7.7l-14.81,29.21h24.71l9.43-21.88c2.66-1.74,5.09-3.71,7.23-5.97-1.12-2.37-2.34-4.75-3.75-7.15-1.02-.35-1.66-.96-2.4-1.98-1.17-1.42-1.34-1.68-2.93-2.52-.75-.4-1.44-1.09-1.75-1.8"/>
              <path fill="#f36b2d" fill-rule="evenodd" d="M289.88,405.04s.09.02.14.02c3.38.65,8.82,4.11,10.51,8.71.16.44-.19,1.03-.62,1.21-.53.22-1.2.01-1.35-.4-1.29-3.51-4.79-6.36-8.92-7.44-1.12-.3-1.25-2.01.25-2.09"/>
              <path fill="#f36b2d" fill-rule="evenodd" d="M300.15,377.58c1.14.2,1.71.92,1.71,2.15,0,2.12-2.47,3.17-4.33,3.63-1.46.37-3.09.24-4.51-.24-.21-.08-.29,0-.29.17.02.51.77,1.01,1.24,1.09.01,1.26-.28,2.35-.82,3.28,1.05.67,1.39,1.19,1.81,2.35.55,1.51,1.88,2.76,3.48,2.95,1.7.2,1.82.8,2.41,2.12.32.71,1.01,1.4,1.75,1.8,1.59.84,1.76,1.1,2.93,2.52.74,1.01,1.38,1.63,2.4,1.98.24.08.5.15.79.2,1.39.25,2.58-.1,3.55-1.05l1.31-1.44,4.99-5.49.04-.05c.27-.33.65-1.24.27-1.57-.06-.06-.15-.09-.26-.08l-.22.29c-.31.32-.6.68-.92,1.01-1.95,2.1-3.9,4.46-5.72,6.38-1.61,1.69-4.56.6-4.78-1.63-.1-1.03.22-1.59.86-2.31l9.06-10.11c-.09,0-.18,0-.28.01-.57.07-.74-.14-.61-.58l-8.41,9.08c-.78.84-1.22,1.55-2.01,1.87-1.61.65-3.18-.54-3.94-1.91-.42-1.07-.16-2.07.56-2.91,3.27-3.8,6.54-7.6,9.81-11.4-.07.01-.13.03-.19.06-.8.16-.79-.33-.73-.83l-10.27,11.93c-1.91,1.92-4.52.2-5.2-1.79-.31-.91.13-1.73.59-2.36l1.69-2.32c3.12-.87,5.46-3.04,4.8-5.4l3.67-4.35c-.06.02-.12.04-.18.06-.48.12-.75-.03-.77-.53l-3.33,3.55c-.59-.92-2.4-1.06-4.22-1.4-1.18-.22-2.39-.63-3.21-.98l-.6.65c2.01.77,4.03,1.33,6.07,1.57"/>
            </svg>
          </div>
          <div class="bid-menu-text">
            <div class="bid-menu-title"><?= __("Jackpot Game Results") ?></div>
            <div class="bid-menu-sub"><?= __("View your jackpot games result history") ?></div>
          </div>
        </div>
        <div class="bid-menu-arrow">
          <i class="fas fa-chevron-right"></i>
        </div>
      </a>
    </div>

  </div><!-- /.content -->
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

            <a href="price_chart.php" class="side-menu-item">
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

    <!-- BOTTOM MENU -->
    <nav class="bottom-menu">
        <a href="user_result.php" class="menu-item active">
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

    <div id="customPopup"
      style="display:none; position:fixed; top:20px; left:50%; transform:translateX(-50%); background:var(--accent-green); color:#fff; padding:12px 24px; border-radius:30px; z-index:9999; font-weight:700; box-shadow:0 10px 20px rgba(0,0,0,0.3);">
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function () {
  var menuBtn = document.getElementById('menuToggle');
  var sideMenu = document.getElementById('sideMenu');
  var sideOverlay = document.getElementById('sideOverlay');
  var sideClose = document.getElementById('sideCloseBtn');

  function openM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();
    if (sideMenu) sideMenu.classList.add('open');
    if (sideOverlay) sideOverlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }
  function closeM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(true); } if(e && e.preventDefault) e.preventDefault();
    if (sideMenu) sideMenu.classList.remove('open');
    if (sideOverlay) sideOverlay.style.display = 'none';
    document.body.style.overflow = '';
  }
  window.openM = openM;
  window.closeM = closeM;

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

  /* SHARE */
  var shareBtn = document.getElementById('shareBtn');
  if (shareBtn) {
    shareBtn.addEventListener('click', function () {
      closeM();

      var url = "https://subhgaming.online/download.php";
      <?php
            $shareTextFull = "Market Price \n♠️*Game Rate*♠️ \n\n";
            if (!empty($gameRates)) {
                foreach ($gameRates as $r) {
                    $shareTextFull .= "▪️" . $r['game_type'] . " ₹" . $r['play_amount'] . " = ₹" . $r['win_amount'] . " ";
                }
            } else {
                $shareTextFull .= "▪️Single Digit ₹10 = ₹95 ▪️Jodi Digit ₹10 = ₹950 ▪️Single Pana ₹10 = ₹1500 ▪️Double Pana ₹10 = ₹3000 ▪️Triple Pana ₹10 = ₹7000 ▪️Half Sangam ₹10 = ₹10000 ▪️Full Sangam ₹10 = ₹100000 ";
            }
            $shareTextFull .= "\n\nDownload App:\n" . "https://subhgaming.online/download.php";
            ?>;
      var fullText = <?= json_encode($shareTextFull, JSON_UNESCAPED_UNICODE) ?>;




      if (navigator.share) {

        navigator.share({ text: fullText });

      } else {

        window.location.href = 'whatsapp://send?text=' + encodeURIComponent(fullText);

      }

    });
  }


});

function showPopup(msg) {
  var p = document.getElementById('customPopup');
  if (!p) return;
  p.textContent = msg;
  p.style.display = 'block';
  setTimeout(function () {p.style.display = 'none';}, 2500);
}</script>
  <script src="assets/js/user_notice_tracker.js?v=4"></script>
</body>

</html>
