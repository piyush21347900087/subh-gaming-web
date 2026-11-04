<?php
require_once 'includes/lang.php';
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once 'includes/user_ping.php';
require_once 'includes/live_ping.php';

if (!isset($_SESSION['user'])) {
  header('Location: user_login.php');
  exit;
}

$uid = (int) $_SESSION['user']['id'];

$db = db();
if ($db->connect_error)
  die('DB error: ' . $db->connect_error);

$stmt = $db->prepare("
  SELECT user_id,name,email,referral,mobile_no,password,created_at,is_active
    FROM users WHERE id=?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if ($user) {
  $c = (string) $user['user_id'];
  if (preg_match('/^\d+$/', $c)) {
    $user['user_id'] = "KR" . str_pad($c, 2, "0", STR_PAD_LEFT);
  } elseif (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $c, $m)) {
    $user['user_id'] = $m[1] . str_pad($m[2], 2, "0", STR_PAD_LEFT);
  }
}
$stmt->close();

/* Balance for header */
$hasBonusCol = false;
$resB = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
if ($resB && $resB->num_rows > 0) { $hasBonusCol = true; }

$userCodeStr = $db->real_escape_string($user['user_id'] ?? '');
$depositDone = (float) ($db->query("SELECT (IFNULL(SUM(amount),0) + " . ($hasBonusCol ? "IFNULL(SUM(bonus_amount),0)" : "0") . ") FROM deposit WHERE status='Done' AND user_id='$userCodeStr'")->fetch_row()[0] ?? 0);
$withdrawMinus = (float) ($db->query("SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE user_id='$userCodeStr' AND LOWER(status) IN ('pending','approved','done')")->fetch_row()[0] ?? 0);
$winBalance = (float) ($db->query("SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id='$userCodeStr'")->fetch_row()[0] ?? 0);
$totalBalance = ($depositDone - $withdrawMinus) + $winBalance;
if ($totalBalance < 0) $totalBalance = 0;

/* Side menu identity */
$code = $user['user_id'] ?? '';
$userName = $user['name'] ?? ($_SESSION['user']['name'] ?? 'User');
$userMobile = $user['mobile_no'] ?? '';
$userCreatedAt = $user['created_at'] ?? '';
$userSince = $userCreatedAt ? date('d-m-Y', strtotime($userCreatedAt)) : '';

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

/* Bank Details POST & Fetch */
$uCode = $user['user_id'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_bank_modal') {
    header('Content-Type: application/json');
    $bn = trim($_POST['bank_name'] ?? '');
    $an = trim($_POST['account_name'] ?? '');
    $ac = trim($_POST['account_number'] ?? '');
    $ifsc = trim($_POST['ifsc'] ?? '');
    $br = trim($_POST['branch'] ?? 'NA');
    if (!$br) { $br = 'NA'; }

    if (!$bn || !$an || !$ac || !$ifsc) {
        echo json_encode(['ok' => false, 'error' => 'All bank details are required']);
        exit;
    }

    $qCheck = $db->prepare("SELECT id FROM bank_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $qCheck->bind_param('s', $uCode);
    $qCheck->execute();
    $qCheck->bind_result($eid);
    if ($qCheck->fetch()) {
        $qCheck->close();
        $uStmt = $db->prepare("UPDATE bank_kyc SET bank_name=?, account_name=?, account_number=?, ifsc=?, branch=?, status='Approved', date_time=NOW() WHERE id=?");
        $uStmt->bind_param('sssssi', $bn, $an, $ac, $ifsc, $br, $eid);
        $ok = $uStmt->execute();
        $uStmt->close();
    } else {
        $qCheck->close();
        $uStmt = $db->prepare("INSERT INTO bank_kyc (user_id, bank_name, account_name, account_number, ifsc, branch, status, date_time) VALUES (?,?,?,?,?,?,'Approved',NOW())");
        $uStmt->bind_param('ssssss', $uCode, $bn, $an, $ac, $ifsc, $br);
        $ok = $uStmt->execute();
        $uStmt->close();
    }

    echo json_encode(['ok' => $ok, 'message' => $ok ? 'Bank Details Updated Successfully!' : 'Failed to update bank details.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_upi_modal') {
    header('Content-Type: application/json');
    $upi_id = trim($_POST['upi_id'] ?? '');
    $phonepe = trim($_POST['phonepe'] ?? '');
    $gpay = trim($_POST['gpay'] ?? '');
    $paytm = trim($_POST['paytm'] ?? '');
    $bhim = trim($_POST['bhim'] ?? '');

    // 1. Update general UPI ID
    $qCheck = $db->prepare("SELECT id FROM upi_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $qCheck->bind_param('s', $uCode);
    $qCheck->execute();
    $qCheck->bind_result($eid);
    if ($qCheck->fetch()) {
        $qCheck->close();
        $uStmt = $db->prepare("UPDATE upi_kyc SET upi_id=?, status='Approved', date_time=NOW() WHERE id=?");
        $uStmt->bind_param('si', $upi_id, $eid);
        $uStmt->execute();
        $uStmt->close();
    } else {
        $qCheck->close();
        $uStmt = $db->prepare("INSERT INTO upi_kyc (user_id, upi_id, status, date_time) VALUES (?,?,'Approved',NOW())");
        $uStmt->bind_param('ss', $uCode, $upi_id);
        $uStmt->execute();
        $uStmt->close();
    }

    // 2. Update UPI numbers
    $apps = [
        'phonepe' => $phonepe,
        'gpay' => $gpay,
        'paytm' => $paytm,
        'bhim' => $bhim
    ];

    foreach ($apps as $type => $num) {
        $qCheck2 = $db->prepare("SELECT id FROM upi_numbers WHERE user_id=? AND upi_type=? LIMIT 1");
        $qCheck2->bind_param('ss', $uCode, $type);
        $qCheck2->execute();
        $qCheck2->bind_result($nid);
        if ($qCheck2->fetch()) {
            $qCheck2->close();
            $nStmt = $db->prepare("UPDATE upi_numbers SET upi_number=?, date_time=NOW() WHERE id=?");
            $nStmt->bind_param('si', $num, $nid);
            $nStmt->execute();
            $nStmt->close();
        } else {
            $qCheck2->close();
            $nStmt = $db->prepare("INSERT INTO upi_numbers (user_id, upi_number, upi_type, date_time) VALUES (?,?,?,NOW())");
            $nStmt->bind_param('sss', $uCode, $num, $type);
            $nStmt->execute();
            $nStmt->close();
        }
    }

    echo json_encode(['ok' => true, 'message' => 'UPI Details Updated Successfully!']);
    exit;
}

$userUpiData = ['upi_id' => '', 'phonepe' => '', 'gpay' => '', 'paytm' => '', 'bhim' => ''];
$qUpi = $db->prepare("SELECT upi_id FROM upi_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
$qUpi->bind_param('s', $uCode);
$qUpi->execute();
$qUpi->bind_result($b_upi);
if ($qUpi->fetch()) {
    $userUpiData['upi_id'] = $b_upi ?? '';
}
$qUpi->close();

$qUpiNum = $db->prepare("SELECT upi_type, upi_number FROM upi_numbers WHERE user_id=?");
$qUpiNum->bind_param('s', $uCode);
$qUpiNum->execute();
$qUpiNum->bind_result($u_type, $u_num);
while ($qUpiNum->fetch()) {
    if (array_key_exists($u_type, $userUpiData)) {
        $userUpiData[$u_type] = $u_num ?? '';
    }
}
$qUpiNum->close();

$userBankData = ['bank_name' => '', 'account_name' => '', 'account_number' => '', 'ifsc' => '', 'branch' => ''];
$qBank = $db->prepare("SELECT bank_name, account_name, account_number, ifsc, branch FROM bank_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
$qBank->bind_param('s', $uCode);
$qBank->execute();
$qBank->bind_result($b_bn, $b_an, $b_ac, $b_ifsc, $b_br);
if ($qBank->fetch()) {
    $userBankData = [
        'bank_name' => $b_bn ?? '',
        'account_name' => $b_an ?? '',
        'account_number' => $b_ac ?? '',
        'ifsc' => $b_ifsc ?? '',
        'branch' => $b_br ?? ''
    ];
}
$qBank->close();

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
    <meta name="theme-color" content="#070b14">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?= __("Profile") ?> | SUBH GAMING</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
            --text-main: #333333;
            --text-dim: #666666;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            --border-radius: 15px;
            --gradient-gold: linear-gradient(135deg, #ffd700 0%, #ffae00 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        html { background-color: #000000 !important; background: #000000 !important; }
        body { 
            background-color: #000000 !important; 
            background: #000000 !important; 
            color: var(--text-main); 
            padding-bottom: 80px; 
            overflow-x: hidden; 
        }

        /* ── Header 100% Matching user_dashboard.php ── */
        header {
            background-color: #000000 !important;
            background-image: none !important;
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

        /* ── Profile Details New UI (Screenshot Matching) ── */
        .content {
            max-width: 480px;
            margin: 0 auto;
            padding: 14px 14px 20px 14px;
        }

        /* Top Profile Details Box */
        .profile-details-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 14px 16px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 12px;
        }

        .profile-details-title {
            font-size: 15px;
            font-weight: 600;
            color: #1a202c;
            padding-bottom: 8px;
            border-bottom: 1px solid #edf2f7;
            margin-bottom: 12px;
        }

        .profile-user-row {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .profile-avatar-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }

        .profile-avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .profile-user-info {
            display: flex;
            flex-direction: column;
        }

        .profile-user-name {
            font-size: 16px;
            font-weight: 600;
            color: #800000;
            line-height: 1.2;
        }

        .profile-user-mobile {
            font-size: 14px;
            font-weight: 600;
            color: #800000;
            line-height: 1.2;
            margin-top: 4px;
        }

        /* Action Menu Cards */
        .profile-action-card {
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

        .profile-action-card:active {
            transform: scale(0.98);
            background: #fafafa;
        }

        .profile-action-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .profile-action-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .profile-action-icon img {
            width: 38px;
            height: 38px;
            object-fit: contain;
        }

        .profile-action-icon.bank-circle {
            background: #52c41a;
            border-radius: 50%;
            color: #ffffff;
            font-size: 20px;
        }

        .profile-action-text {
            display: flex;
            flex-direction: column;
        }

        .profile-action-title {
            font-size: 14px;
            font-weight: 700;
            color: #1f2937;
            line-height: 1.2;
        }

        .profile-action-sub {
            font-size: 11px;
            font-weight: 400;
            color: #6b7280;
            line-height: 1.3;
            margin-top: 3px;
        }

        .profile-action-arrow {
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

        /* Logout Row */
        .profile-logout-row {
            padding: 10px 4px;
            margin-top: 6px;
        }

        .profile-logout-link {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            color: #c00000;
        }

        .profile-logout-link img {
            width: 26px;
            height: 26px;
            object-fit: contain;
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

        /* ── BOTTOM MENU 100% Exact Copy of user_dashboard.php ── */
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

        .toast { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #000; color: #fff; padding: 12px 25px; border-radius: 30px; font-weight: 700; z-index: 9999; box-shadow: 0 10px 20px rgba(0,0,0,0.3); animation: slideDown 0.4s ease forwards; }
        @keyframes slideDown { from { top: -50px; opacity: 0; } to { top: 20px; opacity: 1; } }
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

        /* ── Add Bank Details Modal (Exact Screenshot Matching) ── */
        .bank-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            width: 100vw; height: 100vh;
            background: rgba(0, 0, 0, 0.6);
            z-index: 99999;
            align-items: flex-end;
            justify-content: center;
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        @media (min-width: 500px) {
            .bank-modal-overlay {
                align-items: center;
                padding: 20px;
            }
        }

        .bank-modal-overlay.show {
            display: flex !important;
            opacity: 1;
        }

        .bank-modal-card { background: #1a1a1a;
            width: 100%;
            max-width: 480px;
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
            border-bottom-left-radius: 0px;
            border-bottom-right-radius: 0px;
            overflow: hidden;
            box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.3);
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.1);
            box-sizing: border-box;
        }

        @media (min-width: 500px) {
            .bank-modal-card {
                border-radius: 18px;
                transform: scale(0.9);
                transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }
            .bank-modal-overlay.show .bank-modal-card {
                transform: scale(1);
            }
        }

        .bank-modal-overlay.show .bank-modal-card {
            transform: translateY(0);
        }

        .bank-modal-header { background: #000000;
            background-image: 
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px),
                repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px);
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .bank-modal-title {
            color: #ffffff;
            font-size: 18px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Poppins', sans-serif;
        }

        .bank-modal-close {
            background: transparent;
            border: none;
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            line-height: 1;
            transition: transform 0.15s ease;
        }

        .bank-modal-close:active {
            transform: scale(0.85);
        }

        .bank-modal-body { padding: 20px 18px 26px 18px; background: #1a1a1a;
        }

        .bank-sub-title { text-align: center; font-size: 16px; font-weight: 700; color: #ffffff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: 'Poppins', sans-serif;
        }

        .bank-sub-badge {
            width: 28px;
            height: 28px;
            background: #4caf50;
            border-radius: 50%;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .bank-input-group {
            margin-bottom: 16px;
            text-align: left;
        }

        .bank-input-label { display: block; font-size: 14px; font-weight: 600; color: #dddddd;
            margin-bottom: 6px;
            font-family: 'Poppins', sans-serif;
        }

        .bank-input-field { width: 100%; height: 48px; background: #ffffff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 0 16px; font-size: 15px; font-weight: 600; color: #000000; box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);
            outline: none;
            transition: border-color 0.2s, background-color 0.2s;
            font-family: 'Poppins', sans-serif;
        }

        .bank-input-field:focus { border-color: #ff4d4d; background: #ffffff;
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1);
        }

        .bank-submit-btn {
            width: 100%;
            height: 48px;
            background: #ff2a2a;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(255, 42, 42, 0.3);
            transition: transform 0.15s, background-color 0.2s;
            font-family: 'Poppins', sans-serif;
        }

        .bank-submit-btn:active {
            transform: scale(0.98);
            background: #e60000;
        }

        /* ── Success Popup Modal ── */
        .bank-success-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            width: 100vw; height: 100vh;
            background: rgba(0, 0, 0, 0.65);
            z-index: 100000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .bank-success-overlay.show {
            display: flex !important;
            opacity: 1;
        }

        .bank-success-card { background: #1a1a1a;
            width: 100%;
            max-width: 310px;
            border-radius: 20px;
            padding: 26px 20px 22px 20px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            transform: scale(0.8);
            transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-sizing: border-box;
        }

        .bank-success-overlay.show .bank-success-card {
            transform: scale(1);
        }

        .bank-success-icon-box { width: 60px; height: 60px; background: rgba(76, 175, 80, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; color: #4caf50;
            font-size: 28px;
            box-shadow: 0 6px 16px rgba(46, 125, 50, 0.2);
            animation: popCheck 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes popCheck {
            0% { transform: scale(0); }
            80% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }

        .bank-success-title { font-size: 18px; font-weight: 800; color: #ffffff;
            margin-bottom: 6px;
            font-family: 'Poppins', sans-serif;
        }

        .bank-success-msg { font-size: 13px; font-weight: 500; color: #aaaaaa;
            margin-bottom: 20px;
            line-height: 1.4;
            font-family: 'Poppins', sans-serif;
        }

        .bank-success-btn {
            width: 100%;
            height: 44px;
            background: #2e7d32;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(46, 125, 50, 0.35);
            transition: transform 0.15s ease, background-color 0.2s ease;
            font-family: 'Poppins', sans-serif;
        }

        .bank-success-btn:active {
            transform: scale(0.96);
            background: #1b5e20;
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
<body>

    <!-- Header 100% identical to user_dashboard.php -->
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
        <!-- 1. Profile Details Card -->
        <div class="profile-details-card">
            <div class="profile-details-title"><?= __("Profile Details") ?></div>
            <div class="profile-user-row">
                <div class="profile-avatar-circle">
                    <img src="icons/main_profile.svg" alt="Profile">
                </div>
                <div class="profile-user-info">
                    <div class="profile-user-name"><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
                    <div class="profile-user-mobile"><?= htmlspecialchars($user['mobile_no'] ?? '') ?></div>
                </div>
            </div>
        </div>

        <!-- 2. Add Bank A/C Details -->
                <a href="javascript:void(0)" onclick="openAddUpiModal()" class="profile-action-card">
            <div class="profile-action-left">
                <div class="profile-action-icon">
                    <img src="icons/upi.svg" alt="UPI Details">
                </div>
                <div class="profile-action-text">
                    <div class="profile-action-title"><?= __("Add UPI Details") ?></div>
                    <div class="profile-action-sub"><?= __("UPI, PhonePe, Google Pay, Paytm, BHIM") ?></div>
                </div>
            </div>
            <div class="profile-action-arrow">
                <i class="fa-solid fa-chevron-right"></i>
            </div>
        </a>
        <a href="javascript:void(0)" onclick="openAddBankModal()" class="profile-action-card">
            <div class="profile-action-left">
                <div class="profile-action-icon">
                    <img src="icons/add_bank.svg" alt="Add Bank">
                </div>
                <div class="profile-action-text">
                    <div class="profile-action-title"><?= __("Add Bank A/C Details") ?></div>
                    <div class="profile-action-sub"><?= __("Add your bank details to withdraw") ?></div>
                </div>
            </div>
            <div class="profile-action-arrow">
                <i class="fas fa-chevron-right"></i>
            </div>
        </a>

        <!-- 3. Add Fund -->
        <a href="deposit.php" class="profile-action-card">
            <div class="profile-action-left">
                <div class="profile-action-icon">
                    <img src="icons/deposit_icon.svg" onerror="this.src='icons/coin_icon.svg'" alt="Add Fund">
                </div>
                <div class="profile-action-text">
                    <div class="profile-action-title"><?= __("Add Fund") ?></div>
                    <div class="profile-action-sub"><?= __("You can add fund to your wallet") ?></div>
                </div>
            </div>
            <div class="profile-action-arrow">
                <i class="fas fa-chevron-right"></i>
            </div>
        </a>

        <!-- 4. Withdraw Fund -->
        <a href="withdrawal.php" class="profile-action-card">
            <div class="profile-action-left">
                <div class="profile-action-icon">
                    <img src="icons/withdrawal_icon.svg" onerror="this.src='icons/coin_icon.svg'" alt="Withdraw Fund">
                </div>
                <div class="profile-action-text">
                    <div class="profile-action-title"><?= __("Withdraw Fund") ?></div>
                    <div class="profile-action-sub"><?= __("You can withdraw winnings") ?></div>
                </div>
            </div>
            <div class="profile-action-arrow">
                <i class="fas fa-chevron-right"></i>
            </div>
        </a>

        <!-- 5. Fund Deposit History -->
        <a href="deposit_history.php" class="profile-action-card">
            <div class="profile-action-left">
                <div class="profile-action-icon">
                    <img src="icons/fund_deposit.svg" alt="Deposit History">
                </div>
                <div class="profile-action-text">
                    <div class="profile-action-title"><?= __("Fund Deposit History") ?></div>
                    <div class="profile-action-sub"><?= __("You can see history of your deposits") ?></div>
                </div>
            </div>
            <div class="profile-action-arrow">
                <i class="fas fa-chevron-right"></i>
            </div>
        </a>

        <!-- 6. Fund Withdraw History -->
        <a href="withdrawal_history.php" class="profile-action-card">
            <div class="profile-action-left">
                <div class="profile-action-icon">
                    <img src="icons/withdrawal_icon.svg" onerror="this.src='icons/coin_icon.svg'" alt="Withdraw History">
                </div>
                <div class="profile-action-text">
                    <div class="profile-action-title"><?= __("Fund Withdraw History") ?></div>
                    <div class="profile-action-sub"><?= __("You can see history of your withdrawls") ?></div>
                </div>
            </div>
            <div class="profile-action-arrow">
                <i class="fas fa-chevron-right"></i>
            </div>
        </a>

        <!-- 7. Logout App -->
        <div class="profile-logout-row">
            <a href="user_logout.php" class="profile-logout-link">
                <svg style="width: 26px; height: 26px; flex-shrink: 0;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 49.3 53.46">
                  <path fill="#e60000" fill-rule="evenodd" d="M22.9,0c6.99,0,12.66,5.67,12.66,12.66s-5.67,12.66-12.66,12.66-12.66-5.67-12.66-12.66S15.91,0,22.9,0"/>
                  <path fill="#e60000" fill-rule="evenodd" d="M24.58,50.89c-.34-.91-.43-2.33-.43-2.33v-19.05s.03-.18.03-.18c-6.6.69-13.48,2.74-19.91,5.82-2.61,1.25-4.28,3.82-4.28,6.66v7.74c0,1.76,1.46,3.18,3.27,3.18h22.25s-.43-.48-.94-1.84Z"/>
                  <g fill="#e60000">
                    <path fill="#e60000" d="M42.18,47.45c-.81-.52-1.89-.29-2.42.52-.36.55-1.58,1.99-2.88,1.99h-3.48s-.94.01-1.57-.6c-.47-.46-.71-1.23-.71-2.29v-15.87c0-1.05.24-1.82.71-2.29.62-.61,1.55-.6,1.62-.6h3.43c1.3,0,2.52,1.43,2.88,1.99.52.81,1.6,1.05,2.41.53.82-.52,1.06-1.6.54-2.42-.23-.37-2.39-3.6-5.83-3.6h-3.34c-.25-.01-2.41-.06-4.1,1.55-1.2,1.15-1.81,2.78-1.81,4.84v15.87c0,2.06.61,3.69,1.81,4.84,1.52,1.45,3.41,1.55,3.91,1.55.06,0,.1,0,.11,0h3.43c3.44,0,5.59-3.23,5.83-3.6.52-.81.28-1.89-.53-2.41Z"/>
                    <path fill="#e60000" d="M49.12,39.92s.02-.04.03-.06c.04-.08.07-.16.09-.25,0-.03.01-.07.02-.1.02-.07.03-.14.04-.22,0-.05,0-.11,0-.16,0-.05,0-.11,0-.16,0-.07-.02-.15-.04-.22,0-.03-.01-.07-.02-.1-.02-.08-.06-.17-.09-.25,0-.02-.02-.04-.03-.06-.04-.09-.1-.17-.16-.25,0-.01-.02-.02-.03-.04-.02-.02-.03-.05-.05-.07l-4.27-4.98c-.63-.73-1.73-.82-2.47-.19-.73.63-.82,1.73-.19,2.47l1.79,2.09h-7.88c-.97,0-1.75.78-1.75,1.75s.78,1.75,1.75,1.75h7.88l-1.79,2.09c-.63.73-.54,1.84.19,2.47.33.28.74.42,1.14.42.49,0,.98-.21,1.33-.61l4.27-4.97s.03-.05.05-.07c0-.01.02-.02.02-.04.06-.08.11-.16.16-.25Z"/>
                  </g>
                </svg>
                <span><?= __("Logout App") ?></span>
            </a>
        </div>
    </div>

    <!-- SIDE MENU 100% identical to user_dashboard.php -->
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

    <!-- BOTTOM MENU 100% matching user_dashboard.php (Account active) -->
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
        <a href="user_profile.php" class="menu-item active">
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

    <!-- ADD BANK DETAILS MODAL -->
    <div class="bank-modal-overlay" id="bankModalOverlay" onclick="closeAddBankModal(event)">
        <div class="bank-modal-card" onclick="event.stopPropagation()">
            <div class="bank-modal-header">
                <div class="bank-modal-title">
                    Add Bank Details <img src="icons/gold_coin.svg" alt="coin" style="width: 22px; height: 22px; vertical-align: middle; margin-top: -2px;">
                </div>
                <button type="button" class="bank-modal-close" onclick="closeAddBankModal(event)" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="bank-modal-body">
                <div class="bank-sub-title">
                    <img src="icons/add_bank.svg" alt="Add Bank" style="width: 28px; height: 28px; vertical-align: middle;">
                    <span>Fill the details below</span>
                    <img src="icons/add_bank.svg" alt="Add Bank" style="width: 28px; height: 28px; vertical-align: middle;">
                </div>
                <form id="addBankForm" onsubmit="submitAddBankModal(event)">
                    <div class="bank-input-group">
                        <label class="bank-input-label">Bank Name</label>
                        <input type="text" id="bm_bank_name" class="bank-input-field" placeholder="Enter Bank Name" maxlength="20" required>
                    </div>
                    <div class="bank-input-group">
                        <label class="bank-input-label">Account Holder Name</label>
                        <input type="text" id="bm_account_name" class="bank-input-field" placeholder="Enter Account Holder Name" maxlength="20" required>
                    </div>
                    <div class="bank-input-group">
                        <label class="bank-input-label">Account Number</label>
                        <input type="text" id="bm_account_number" class="bank-input-field" placeholder="000000000000" maxlength="20" required>
                    </div>
                    <div class="bank-input-group">
                        <label class="bank-input-label">IFSC Code</label>
                        <input type="text" id="bm_ifsc" class="bank-input-field" placeholder="Enter IFSC Code" maxlength="20" required style="text-transform: uppercase;">
                    </div>
                    <div class="bank-input-group">
                        <label class="bank-input-label">Branch Name</label>
                        <input type="text" id="bm_branch" class="bank-input-field" placeholder="Branch Name" maxlength="20">
                    </div>
                    <button type="submit" class="bank-submit-btn" id="bm_submit_btn">Submit</button>
                </form>
            </div>
        </div>
    </div>

        <!-- ADD UPI DETAILS MODAL -->
    <div class="bank-modal-overlay" id="upiModalOverlay" onclick="closeAddUpiModal(event)">
        <div class="bank-modal-card" onclick="event.stopPropagation()">
            <div class="bank-modal-header">
                <div class="bank-modal-title">
                    Add UPI Details <img src="icons/gold_coin.svg" alt="coin" style="width: 22px; height: 22px; vertical-align: middle; margin-top: -2px;">
                </div>
                <button type="button" class="bank-modal-close" onclick="closeAddUpiModal(event)" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="bank-modal-body">
                <div class="bank-sub-title">
                    <span>Fill UPI details below</span>
                </div>
                <form id="addUpiForm" onsubmit="submitAddUpiModal(event)">
                    <div class="bank-input-group">
                        <label class="bank-input-label">UPI ID</label>
                        <div style="position: relative;">
                            <input type="text" id="um_upi_id" class="bank-input-field" placeholder="enterupi@ybl" maxlength="30" style="padding-right: 80px;">
                            <img src="images/upi.png" alt="UPI" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); width: 44px; height: 44px; object-fit: contain; pointer-events: none; border-radius: 4px;">
                        </div>
                    </div>
                    <div class="bank-input-group">
                        <label class="bank-input-label">PhonePe Number</label>
                        <div style="position: relative;">
                            <input type="tel" id="um_phonepe" class="bank-input-field" placeholder="0000000000" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '');" style="padding-right: 80px;">
                            <img src="images/phonepe.png" alt="PhonePe" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%) scale(1.3); width: 44px; height: 44px; object-fit: contain; pointer-events: none; border-radius: 4px;">
                        </div>
                    </div>
                    <div class="bank-input-group">
                        <label class="bank-input-label">Google Pay Number</label>
                        <div style="position: relative;">
                            <input type="tel" id="um_gpay" class="bank-input-field" placeholder="0000000000" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '');" style="padding-right: 80px;">
                            <img src="images/googlepay.png" alt="GPay" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%) scale(1.3); width: 44px; height: 44px; object-fit: contain; pointer-events: none; border-radius: 4px;">
                        </div>
                    </div>
                    <div class="bank-input-group">
                        <label class="bank-input-label">Paytm Number</label>
                        <div style="position: relative;">
                            <input type="tel" id="um_paytm" class="bank-input-field" placeholder="0000000000" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '');" style="padding-right: 80px;">
                            <img src="images/paytm.png" alt="Paytm" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); width: 44px; height: 44px; object-fit: contain; pointer-events: none; border-radius: 4px;">
                        </div>
                    </div>
                    <div class="bank-input-group">
                        <label class="bank-input-label">BHIM Number</label>
                        <div style="position: relative;">
                            <input type="tel" id="um_bhim" class="bank-input-field" placeholder="0000000000" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '');" style="padding-right: 80px;">
                            <img src="images/bhim.png" alt="BHIM" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%) scale(1.3); width: 44px; height: 44px; object-fit: contain; pointer-events: none; border-radius: 4px;">
                        </div>
                    </div>
                    <button type="submit" class="bank-submit-btn" id="um_submit_btn">Submit</button>
                </form>
            </div>
        </div>
    </div>

    <!-- BANK SUCCESS POPUP MODAL -->
    <div class="bank-success-overlay" id="bankSuccessOverlay" onclick="closeBankSuccessModal(event)">
        <div class="bank-success-card" onclick="event.stopPropagation()">
            <div class="bank-success-icon-box">
                <i class="fa-solid fa-check"></i>
            </div>
            <div class="bank-success-title" id="bankSuccessTitle">Success!</div>
            <div class="bank-success-msg" id="bankSuccessMsg">Bank details saved successfully!</div>
            <button type="button" class="bank-success-btn" onclick="closeBankSuccessModal(event)">OK</button>
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

    /* Add Bank Modal JS */
    function openAddBankModal() {
        var modal = document.getElementById('bankModalOverlay');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }
    function closeAddBankModal(e) {
        if (e && e.stopPropagation) e.stopPropagation();
        var modal = document.getElementById('bankModalOverlay');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
    window.openAddBankModal = openAddBankModal;
    window.closeAddBankModal = closeAddBankModal;

    var bankSuccessTimer = null;
    function openBankSuccessModal(msg) {
        var modal = document.getElementById('bankSuccessOverlay');
        var msgEl = document.getElementById('bankSuccessMsg');
        if (msgEl && msg) msgEl.innerText = msg;
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            if (bankSuccessTimer) clearTimeout(bankSuccessTimer);
            bankSuccessTimer = setTimeout(function() {
                closeBankSuccessModal();
            }, 2000);
        }
    }
    function closeBankSuccessModal(e) {
        if (e && e.stopPropagation) e.stopPropagation();
        var modal = document.getElementById('bankSuccessOverlay');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
    window.openBankSuccessModal = openBankSuccessModal;
    window.closeBankSuccessModal = closeBankSuccessModal;

    function showToast(msg) {
        var oldToast = document.querySelector('.toast');
        if (oldToast) oldToast.remove();
        var toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerText = msg;
        document.body.appendChild(toast);
        setTimeout(function() {
            if (toast) toast.remove();
        }, 3000);
    }

        /* Add UPI Modal JS */
    function openAddUpiModal() {
        var modal = document.getElementById('upiModalOverlay');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }
    function closeAddUpiModal(e) {
        if (e && e.stopPropagation) e.stopPropagation();
        var modal = document.getElementById('upiModalOverlay');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
    window.openAddUpiModal = openAddUpiModal;
    window.closeAddUpiModal = closeAddUpiModal;

    function submitAddUpiModal(e) {
        if (e) e.preventDefault();
        var upi_id = document.getElementById('um_upi_id').value.trim();
        var phonepe = document.getElementById('um_phonepe').value.trim();
        var gpay = document.getElementById('um_gpay').value.trim();
        var paytm = document.getElementById('um_paytm').value.trim();
        var bhim = document.getElementById('um_bhim').value.trim();

        if (!upi_id && !phonepe && !gpay && !paytm && !bhim) {
            showToast('Please fill at least one UPI detail.');
            return false;
        }

        if (phonepe && phonepe.length !== 10) { showToast('PhonePe number must be exactly 10 digits.'); return false; }
        if (gpay && gpay.length !== 10) { showToast('Google Pay number must be exactly 10 digits.'); return false; }
        if (paytm && paytm.length !== 10) { showToast('Paytm number must be exactly 10 digits.'); return false; }
        if (bhim && bhim.length !== 10) { showToast('BHIM number must be exactly 10 digits.'); return false; }

        var submitBtn = document.getElementById('um_submit_btn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerText = 'Submitting...';
        }

        var formData = new FormData();
        formData.append('action', 'submit_upi_modal');
        formData.append('upi_id', upi_id);
        formData.append('phonepe', phonepe);
        formData.append('gpay', gpay);
        formData.append('paytm', paytm);
        formData.append('bhim', bhim);

        fetch('user_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Submit';
            }
            if (data.ok) {
                closeAddUpiModal();
                openBankSuccessModal(data.message || 'UPI Details Saved Successfully!');
            } else {
                showToast(data.error || 'Failed to save UPI details.');
            }
        })
        .catch(function(err) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Submit';
            }
            showToast('An error occurred. Please try again.');
        });
        return false;
    }

    function submitAddBankModal(e) {
        if (e) e.preventDefault();
        var bn = document.getElementById('bm_bank_name').value.trim();
        var an = document.getElementById('bm_account_name').value.trim();
        var ac = document.getElementById('bm_account_number').value.trim();
        var ifsc = document.getElementById('bm_ifsc').value.trim();
        var br = document.getElementById('bm_branch') ? document.getElementById('bm_branch').value.trim() : 'NA';

        if (!bn || !an || !ac || !ifsc) {
            showToast('Please fill in all required bank details.');
            return false;
        }

        var submitBtn = document.getElementById('bm_submit_btn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerText = 'Submitting...';
        }

        var formData = new FormData();
        formData.append('action', 'submit_bank_modal');
        formData.append('bank_name', bn);
        formData.append('account_name', an);
        formData.append('account_number', ac);
        formData.append('ifsc', ifsc);
        formData.append('branch', br || 'NA');

        fetch('user_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Submit';
            }
            if (data.ok) {
                closeAddBankModal();
                openBankSuccessModal(data.message || 'Bank Details Saved Successfully!');
            } else {
                showToast(data.error || 'Failed to save bank details.');
            }
        })
        .catch(function(err) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Submit';
            }
            showToast('An error occurred. Please try again.');
        });
        return false;
    }

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

    document.addEventListener('DOMContentLoaded', function () {
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

        /* SHARE */
        var shareBtn = document.getElementById('shareBtn');
        if (shareBtn) shareBtn.addEventListener('click', function () {
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
    });
    </script>
    <script src="notify_bundle.php?v=2"></script>
    <script src="assets/js/user_notice_tracker.js?v=4"></script>
</body>
</html>



















