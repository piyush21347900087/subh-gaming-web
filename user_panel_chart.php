<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/lang.php';

$isPublic = (isset($_GET['public']) && $_GET['public'] == '1');

if (!$isPublic) {
  require_once 'includes/user_ping.php';
  require_once 'includes/live_ping.php';
  if (!isset($_SESSION['user']['id'])) { header('Location: user_login.php'); exit; }
}

$uid = (int) ($_SESSION['user']['id'] ?? 0);
$db = db();
$isDeactive = (isset($_SESSION['user']['is_active']) && (int) $_SESSION['user']['is_active'] === 0);
$db->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$db->set_charset('utf8mb4');
date_default_timezone_set('Asia/Kolkata');

$waSupportUrl = "https://wa.me/917020691217";
try {
    $rsSettings = $db->query("SELECT whatsapp_no_1, whatsapp_no_2 FROM admin_payment_details WHERE id = 1 LIMIT 1");
    if ($rsSettings && $rowSettings = $rsSettings->fetch_assoc()) {
        $w1 = $rowSettings["whatsapp_no_1"] ?? "";
        $w2 = $rowSettings["whatsapp_no_2"] ?? "";
        $digits = preg_replace("/\D/", "", $w1 ?: $w2);
        if (strlen($digits) === 10) $digits = "91" . $digits;
        if (!empty($digits)) $waSupportUrl = "https://wa.me/" . $digits;
    }
} catch (Throwable $e) {}

$code = "";
$sideUserName = "User";
$sideUserMobile = "";
$sideUserSince = "";
$totalBalance = 0;

if (!$isPublic) {
  $stmt = $db->prepare("SELECT user_id, name, mobile_no, created_at FROM users WHERE id = ?");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $uRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($uRow) {
    $code = $uRow['user_id'];
    if (preg_match('/^\d+$/', $code)) {
      $code = normalize_user_id($code);
    } elseif (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $code, $m)) {
      $code = $m[1] . str_pad($m[2], 2, "0", STR_PAD_LEFT);
    }
    $sideUserName = $uRow['name'];
    $sideUserMobile = $uRow['mobile_no'] ?: $code;
    $sideUserSince = $uRow['created_at'] ? date('d-m-Y', strtotime($uRow['created_at'])) : '';
  }

  // Balance calculation
  $hasBonusCol = false;
  $resB = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
  if ($resB && $resB->num_rows > 0) { $hasBonusCol = true; }

  $bonusCountsInBalance = $hasBonusCol && function_exists('is_deposit_bonus_enabled') && is_deposit_bonus_enabled($db);
  $depositDone = (float) ($db->query("SELECT (IFNULL(SUM(amount),0) + " . ($bonusCountsInBalance ? "IFNULL(SUM(bonus_amount),0)" : "0") . ") FROM deposit WHERE status='Done' AND user_id='$code'")->fetch_row()[0] ?? 0);
  $withdrawMinus = (float) ($db->query("SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE user_id='$code' AND LOWER(status) IN ('pending','approved','done')")->fetch_row()[0] ?? 0);
  $walletBalance = max(0.0, $depositDone - $withdrawMinus);
  $winBalance = (float) ($db->query("SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id='$code'")->fetch_row()[0] ?? 0);
  $totalBalance = (float) $walletBalance + (float) $winBalance;
}

$markets = [];
$resM = $db->query("SELECT market_name FROM markets WHERE is_published = 1 ORDER BY sort_order, market_name");
while ($row = $resM->fetch_assoc()) $markets[] = strtoupper($row['market_name']);

$today = date('Y-m-d');
$months = isset($_GET['months']) ? (int) $_GET['months'] : 4;
$defaultFrom = date('Y-m-d', strtotime('-' . $months . ' months'));

$filterMarket = strtoupper($_GET['market'] ?? '');
if (!in_array($filterMarket, $markets)) $filterMarket = !empty($markets) ? $markets[0] : '';

$from = $_GET['from'] ?? $defaultFrom;
$to = $_GET['to'] ?? $today;
$rawType = strtolower($_GET['type'] ?? '');
$chartType = in_array($rawType, ['jodi', 'panel']) ? $rawType : 'panel';

$rows = [];
if ($filterMarket !== '') {
  $sql = "SELECT result_date, result_str FROM market_result WHERE market = ? AND result_date BETWEEN ? AND ? ORDER BY result_date DESC";
  $stmt = $db->prepare($sql);
  $stmt->bind_param('sss', $filterMarket, $from, $to);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}
$db->close();

function parse_result(string $str): array {
  $str = trim($str);
  if (preg_match('/^([0-9*]{3})-([0-9*]{2})-([0-9*]{3})$/', $str, $m)) return ['op'=>$m[1],'jd'=>$m[2],'cp'=>$m[3]];
  return ['op'=>'','jd'=>$str,'cp'=>''];
}

$weeks = [];
foreach ($rows as $row) {
  $ts = strtotime($row['result_date']);
  $weekday = (int) date('N', $ts);
  $weekStart = date('Y-m-d', strtotime('-' . ($weekday - 1) . ' days', $ts));
  if (!isset($weeks[$weekStart])) $weeks[$weekStart] = ['start'=>$weekStart, 'end'=>date('Y-m-d', strtotime('+' . (7 - $weekday) . ' days', $ts)), 'days'=>[]];
  $weeks[$weekStart]['days'][$weekday] = parse_result($row['result_str']);
}
krsort($weeks);
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= ucfirst($chartType) ?> Chart | ANKRAJ 666</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Poppins:wght@300;400;500;600;700;800&family=Roboto:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-brands/css/uicons-brands.css">
  <style>
        :root {
            --primary-bg: #000000;
            --card-bg: #ffffff;
            --accent-main: #000000;
            --accent-red: #e60000;
            --text-main: #ffffff;
            --text-dim: #666666;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            --border-radius: 15px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        html { background-color: #000000 !important; scroll-behavior: smooth; }
        body {
            background-color: #000000 !important;
            background: #000000 !important;
            color: var(--text-main);
            padding-bottom: 100px;
            overflow-x: hidden;
        }

        /* ── Header ── */
        header {
            background-color: #000000 !important;
            background-image: none !important;
            padding-top: calc(env(safe-area-inset-top, 0px) + 25px);
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
        .wallet-display i { font-size: 13px; }

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

        /* ── Side Menu ── */
        .side-menu-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.6);
            display: none; z-index: 4000;
            backdrop-filter: blur(2px);
        }
        .side-menu {
            position: fixed; top: 0; left: -280px;
            width: 280px; height: 100%;
            background: #121212; color: #ffffff;
            padding: 0;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 4100;
            display: flex; flex-direction: column;
        }
        .side-menu.open { left: 0; box-shadow: 5px 0 20px rgba(0,0,0,0.5); }

        .side-menu-header {
            padding: 50px 15px 20px 15px;
            background-color: #000000 !important;
            background-image: none !important;
            border-bottom: 1px solid #1a1a1a;
            display: flex; align-items: flex-start;
            position: relative;
        }
        .profile-avatar {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: #ff4d4d;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px; color: #fff;
            margin-right: 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            text-decoration: none;
        }
        .profile-text { display: flex; flex-direction: column; flex: 1; }
        .profile-name { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 2px; }
        .profile-id   { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 8px; }
        .profile-since { font-size: 12px; color: #aaa; }
        .side-close-btn {
            position: absolute; top: 45px; right: 15px;
            background: #ff4d4d; border: none; color: #fff;
            font-size: 14px; width: 28px; height: 28px; border-radius: 50%;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
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
        .side-menu-item .icon-box img { width: 20px; height: 20px; object-fit: contain; }
        .side-menu-item .icon-box i { color: #ffffff; font-size: 16px; }
        .side-menu-item:hover { background: rgba(255,77,77,0.08); }
        .side-menu-item.active { background: rgba(255,77,77,0.12); }

        .side-menu-footer { padding: 15px; text-align: center; }
        .version-text { color: #ff4d4d; font-size: 14px; font-weight: 600; line-height: 1.4; }

        /* ── Content ── */
        .content { max-width: 480px; padding: 15px; margin: 0 auto; }

        .filter-box {
            background: #111111;
            border-radius: 12px;
            padding: 14px;
            box-shadow: var(--shadow);
            margin-bottom: 16px;
            border: 1px solid #1a1a1a;
        }
        .filter-row { display: flex; gap: 10px; margin-bottom: 10px; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-row select, .filter-row input {
            flex: 1; padding: 10px 12px; border-radius: 8px;
            border: 1px solid #333333;
            background: #1a1a1a; font-size: 13px; font-weight: 600;
            color: #ffffff; outline: none; transition: all 0.3s ease;
        }
        .filter-row select:focus, .filter-row input:focus {
            border-color: #e53935;
        }

        /* ── Table ── */
        .table-wrap {
            background: #111111;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: var(--shadow);
            border: 1px solid #1a1a1a;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 20px;
        }
        table { width: 100%; border-collapse: collapse; min-width: 500px; table-layout: fixed; }
        thead th {
            background: #8b0000;
            color: #ffd700;
            font-size: 12px; font-weight: 800;
            padding: 12px 5px;
            border-bottom: 2px solid #5a0000;
            text-transform: uppercase; letter-spacing: 0.5px;
            text-align: center;
        }
        tbody td {
            border: 1px solid #1a1a1a;
            text-align: center;
            padding: 10px 4px;
            vertical-align: middle;
            height: 80px;
        }
        .date-cell {
            background: #1a1a1a;
            font-size: 11px;
            color: #e53935;
            font-weight: 800;
            line-height: 1.3;
            width: 85px;
        }
        .jodi-val { font-size: 18px; font-weight: 800; color: #ffffff; line-height: 1.1; }
        .p-open, .p-close { font-size: 11px; color: #888888; font-weight: 700; line-height: 1.1; }
        .sun-col { color: #e60000 !important; }
        tbody tr:nth-child(even) { background: #0a0a0a; }

        @media (max-width: 768px) {
            table { min-width: 500px; }
            .date-cell { width: 75px; font-size: 10px; }
            .jodi-val { font-size: 16px; }
            .p-open, .p-close { font-size: 10px; }
        }
        @media (max-width: 520px) {
            .content { padding: 10px 5px; }
            .table-wrap { border-radius: 12px; }
            table { min-width: 100%; table-layout: fixed; }
            thead th { font-size: 9px; padding: 8px 1px; }
            tbody td { padding: 5px 1px; height: 65px; }
            .date-cell { width: 16%; font-size: 8px; }
            .jodi-val { font-size: 13px; }
            .p-open, .p-close { font-size: 8px; }
        }
        @media (max-width: 380px) {
            thead th { font-size: 8px; }
            .date-cell { font-size: 7px; width: 17%; }
            .jodi-val { font-size: 11px; }
            .p-open, .p-close { font-size: 7px; }
        }

        /* ── Market Modal ── */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.7);
            display: none; z-index: 5000;
            overflow-y: auto;
            align-items: flex-start;
            justify-content: center;
            padding: 20px;
        }
        .modal-inner {
            background: #111111;
            border-radius: 16px;
            width: 100%; max-width: 440px;
            margin: 0 auto; overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6);
        }
        .modal-hdr {
            height: 56px; background: #8b0000;
            display: flex; align-items: center; justify-content: center;
            position: sticky; top: 0; z-index: 100;
        }
        .modal-hdr h2 { color: #ffd700; font-size: 16px; font-weight: 800; text-transform: uppercase; }
        .modal-body { padding: 12px; }
        .market-row {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 12px 14px;
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 8px;
            border: 1px solid #2a2a2a;
        }
        .market-name { font-size: 13px; font-weight: 700; color: #ffffff; }
        .m-btn {
            padding: 7px 16px; border-radius: 8px;
            border: none; font-size: 11px; font-weight: 800;
            cursor: pointer; color: #fff; text-transform: uppercase;
            letter-spacing: 0.4px; margin-left: 5px;
        }
        .btn-j { background: linear-gradient(135deg, #0052D4 0%, #4364F7 50%, #6FB1FC 100%); }
        .btn-p { background: linear-gradient(135deg, #8b0000 0%, #c62828 50%, #8b0000 100%); }

        <?php if ($isPublic): ?>
          header, .filter-box, .side-menu, .side-menu-overlay, .bottom-menu { display: none !important; }
          body { padding: 0 !important; }
          .table-wrap { border-radius: 0; border: none; }
        <?php endif; ?>        nav.bottom-menu {
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

        
        <?php if ($isPublic): ?>
          header, .filter-box, .side-menu, .side-menu-overlay, .bottom-menu { display: none !important; }
          body { padding: 0 !important; }
          .table-wrap { border-radius: 0; border: none; }
        <?php endif; ?>
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

        .support-modal-close:active {
            transform: scale(0.92);
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
<body data-deactive="<?= $isDeactive ? '1' : '0' ?>">
  <?php if (!$isPublic): ?>
  <div class="side-menu-overlay" id="sideOverlay" onclick="closeM(event)"></div>
    <div class="side-menu" id="sideMenu">
        <!-- Header (user info) -->
        <div class="side-menu-header">
            <div class="profile-avatar"><i class="fas fa-user"></i></div>
            <div class="profile-text">
                <div class="profile-name"><?= htmlspecialchars($sideUserName) ?></div>
                <div class="profile-id"><?= htmlspecialchars($sideUserMobile) ?></div>
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
            <a href="user_panel_chart.php" class="side-menu-item active">
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

  <header>
    <div class="header-left">
      <i class="fa-solid fa-bars" id="menuToggle"></i>
      <span class="header-title-flash">Panel Chart</span>
    </div>
    <div class="header-right">
      <a href="user_wallet.php" class="wallet-display">
        <i class="fa-solid fa-wallet"></i>
        <span id="walletVal"><?= $isDeactive ? '*' : '&#8377;' . number_format($totalBalance, 0) ?></span>
      </a>
      <a href="notice_board.php" class="bell-icon-btn" id="notifyLink">
        <i class="fa-solid fa-bell"></i>
        <div class="icon-badge" id="notifyBadge" style="display: none;"></div>
      </a>
    </div>
  </header>
  <?php endif; ?>

  <div class="content">
    <?php if (!$isPublic): ?>
    <div class="filter-box">
      <form method="get" id="filterForm">
        <div class="filter-row">
          <select name="market" onchange="this.form.submit()">
            <?php foreach ($markets as $m): ?>
              <option value="<?= $m ?>" <?= $filterMarket===$m?'selected':'' ?>><?= $m ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-row">
          <input type="date" name="from" value="<?= $from ?>" onchange="this.form.submit()">
          <input type="date" name="to" value="<?= $to ?>" onchange="this.form.submit()">
        </div>
        <input type="hidden" name="type" value="<?= $chartType ?>">
      </form>
    </div>
    <?php endif; ?>

    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>DATE</th><th>MON</th><th>TUE</th><th>WED</th><th>THU</th><th>FRI</th><th>SAT</th><th class="sun-col">SUN</th></tr>
        </thead>
        <tbody>
          <?php foreach ($weeks as $wStart => $week): ?>
            <tr>
              <td class="date-cell"><?= date('d/m/y', strtotime($wStart)) ?><br>to<br><?= date('d/m/y', strtotime($week['end'])) ?></td>
              <?php for($d=1;$d<=7;$d++): ?>
                <td>
                  <?php if(isset($week['days'][$d])): $p=$week['days'][$d]; ?>
                    <?php if($chartType==='jodi'): ?>
                      <div class="jodi-val <?= $d==7?'sun-col':'' ?>"><?= $p['jd'] ?></div>
                    <?php else: ?>
                      <div class="p-open"><?= $p['op'] ?></div>
                      <div class="jodi-val <?= $d==7?'sun-col':'' ?>"><?= $p['jd'] ?></div>
                      <div class="p-close"><?= $p['cp'] ?></div>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

    <?php if (!$isPublic): ?>
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
  <?php endif; ?>

  <!-- Market Select Modal -->
  <div id="chartModal" class="modal-overlay">
    <div class="modal-inner" style="margin-top: 40px;">
      <div class="modal-hdr"><h2>Select Market Chart</h2></div>
      <div class="modal-body">
        <?php foreach ($markets as $m): ?>
          <div class="market-row">
            <div class="market-name"><?= $m ?></div>
            <div class="market-btns">
              <button class="m-btn btn-j" data-m="<?= $m ?>" data-t="jodi">JODI</button>
              <button class="m-btn btn-p" data-m="<?= $m ?>" data-t="panel">PANEL</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <script>
    function openSupportModal() {
        var modal = document.getElementById("supportModalOverlay");
        if (modal) { modal.classList.add("show"); document.body.style.overflow = "hidden"; }
    }
    function closeSupportModal(e) {
        if (e && e.stopPropagation) e.stopPropagation();
        var modal = document.getElementById("supportModalOverlay");
        if (modal) { modal.classList.remove("show"); document.body.style.overflow = ""; }
    }
    window.openSupportModal = openSupportModal;
    window.closeSupportModal = closeSupportModal;

    function openM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();
        var s = document.getElementById("sideMenu"), o = document.getElementById("sideOverlay");
        if (s) s.classList.add("open"); if (o) o.style.display = "block";
        document.body.style.overflow = "hidden";
    }
    function closeM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(true); } if(e && e.preventDefault) e.preventDefault();
        var s = document.getElementById("sideMenu"), o = document.getElementById("sideOverlay");
        if (s) s.classList.remove("open"); if (o) o.style.display = "none";
        document.body.style.overflow = "";
    }
    window.openM = openM; window.closeM = closeM;

    document.addEventListener("DOMContentLoaded", function() {
        var menuBtn = document.getElementById("menuToggle");
        var sideOverlay = document.getElementById("sideOverlay");
        var sideClose = document.getElementById("sideCloseBtn");
        if (menuBtn) menuBtn.addEventListener("click", openM);
        if (sideOverlay) sideOverlay.addEventListener("click", closeM);
        if (sideClose) sideClose.addEventListener("click", closeM);

        function refreshSupportDot() {
            var btn = document.getElementById("liveSupportBtn");
            var overlay = document.getElementById("supportModalOverlay");
            var chatBtn = document.getElementById("modalChatBtn") || document.querySelector(".support-btn-chat");
            fetch("get_support_unread.php?_=" + Date.now(), { cache: "no-store", credentials: "same-origin" })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var u = parseInt(data.unread, 10) || 0;
                [btn, overlay, chatBtn].forEach(function(el) {
                    if (!el) return;
                    u > 0 ? el.classList.add("has-support-unread") : el.classList.remove("has-support-unread");
                });
            }).catch(function() {});
        }
        refreshSupportDot();
        setInterval(refreshSupportDot, 3000);

        var isPublic = <?= $isPublic ? "true" : "false" ?>;
        var chartModal = document.getElementById("chartModal");
        var params = new URLSearchParams(window.location.search);
        if (!isPublic && !params.get("type") && chartModal) chartModal.style.display = "flex";

        document.querySelectorAll(".m-btn").forEach(function(btn) {
            btn.onclick = function() {
                var url = new URL(window.location.href);
                url.searchParams.set("market", btn.dataset.m);
                url.searchParams.set("type", btn.dataset.t);
                window.location.href = url.toString();
            };
        });

        <?php if (!$isPublic): ?>
        (function() {
            async function pollWallet() {
                try {
                    var r = await fetch("user_wallet.php?ajax=status", { cache: "no-store" });
                    var d = await r.json();
                    if (d && d.ok && d.balances) {
                        var total = parseFloat(d.balances.total || "0");
                        var fmt = total.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        var el = document.getElementById("walletVal");
                        if (el) el.textContent = "&#8377;" + fmt;
                    }
                } catch(e) {}
                setTimeout(pollWallet, 15000);
            }
            setTimeout(pollWallet, 5000);
        })();
        <?php endif; ?>
    });
  </script>
  <?php if (!$isPublic): ?>
  <script src="assets/js/user_notice_tracker.js?v=4"></script>
  <?php endif; ?>
</body>
</html>
