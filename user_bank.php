<?php
require_once 'includes/lang.php';
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/user_ping.php';
require_once 'includes/live_ping.php';

$db = db();
if ($db->connect_error) { die('DB error: ' . $db->connect_error); }
$db->set_charset('utf8mb4');
$db->query("SET time_zone = '+05:30'");

$userDbId = (int) $_SESSION['user']['id'];
$showBankGlobal = (int) ($db->query("SELECT show_bank FROM admin_payment_details WHERE id=1 LIMIT 1")->fetch_row()[0] ?? 1);

/* ---- Static Data / ID Formatting ---- */
$stmt = $db->prepare("SELECT user_id, is_active, name, mobile_no, created_at FROM users WHERE id=?");
$stmt->bind_param('i', $userDbId);
$stmt->execute();
$stmt->bind_result($uniqueUserId, $isActive, $userName, $userMobile, $userCreatedAt);
$stmt->fetch();
$stmt->close();
$userName = $userName ?: ($_SESSION['user']['name'] ?? 'User');
$userMobile = $userMobile ?: ($_SESSION['user']['mobile_no'] ?? '');

$displayCode = $uniqueUserId;
if (preg_match('/^\d+$/', $displayCode)) {
  $displayCode = 'KR' . str_pad($displayCode, 2, '0', STR_PAD_LEFT);
} elseif (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $displayCode, $m)) {
    $displayCode = $m[1] . str_pad($m[2], 2, "0", STR_PAD_LEFT);
}

/* Balance - Synchronized with Dashboard logic */
$bankDepDone = (float) ($db->query("SELECT IFNULL(SUM(amount + bonus_amount),0) FROM deposit WHERE status='Done' AND user_id='" . $db->real_escape_string($uniqueUserId) . "'")->fetch_row()[0] ?? 0);
$bankWdMinus = (float) ($db->query("SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE user_id='" . $db->real_escape_string($uniqueUserId) . "' AND LOWER(status) IN ('pending','approved','done')")->fetch_row()[0] ?? 0);
$bankWinBal = (float) ($db->query("SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id='" . $db->real_escape_string($uniqueUserId) . "'")->fetch_row()[0] ?? 0);
$bankTotalBal = ($bankDepDone - $bankWdMinus) + $bankWinBal;

/* ---- Record Fetch ---- */
$bankStatus = 'Incomplete'; $upiStatus = 'Incomplete';
$bankData = []; $upiData = [];

$q = $db->prepare("SELECT bank_name,account_name,account_number,ifsc,branch,status FROM bank_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
$q->bind_param('s', $uniqueUserId); $q->execute(); $q->bind_result($bn, $an, $ac, $if, $br, $s);
if ($q->fetch()) { $bankStatus = $s; $bankData = ['bank_name' => $bn, 'account_name' => $an, 'account_number' => $ac, 'ifsc' => $if, 'branch' => $br]; }
$q->close();

$q = $db->prepare("SELECT upi_id, qr_image, status FROM upi_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
$q->bind_param('s', $uniqueUserId); $q->execute(); $q->bind_result($upi, $qr, $s);
if ($q->fetch()) { $upiStatus = $s; $upiData = ['upi_id' => $upi, 'qr_image' => $qr]; }
$q->close();

$upiNums = [];
foreach (['phonepe', 'gpay', 'paytm', 'bhim'] as $t) {
  $q = $db->prepare("SELECT upi_number FROM upi_numbers WHERE user_id=? AND upi_type=? ORDER BY id DESC LIMIT 1");
  $q->bind_param('ss', $uniqueUserId, $t); $q->execute(); $q->bind_result($rnum);
  $upiNums[$t] = ($q->fetch()) ? $rnum : '';
  $q->close();
}

/* POST AJAX */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');
  if ($_POST['action'] === 'submit_bank') {
    $bn = trim($_POST['bank_name'] ?? ''); $an = trim($_POST['account_name'] ?? ''); $ac = trim($_POST['account_number'] ?? ''); $ifsc = trim($_POST['ifsc'] ?? ''); $br = trim($_POST['branch'] ?? '');
    if (!$bn || !$an || !$ac || !$ifsc || !$br) { echo json_encode(['ok' => false, 'error' => 'All fields required']); exit; }
    $q = $db->prepare("SELECT id FROM bank_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $q->bind_param('s', $uniqueUserId); $q->execute(); $q->bind_result($eid);
    if ($q->fetch()) { $q->close(); $u = $db->prepare("UPDATE bank_kyc SET bank_name=?,account_name=?,account_number=?,ifsc=?,branch=?,status='Approved',date_time=NOW() WHERE id=?"); $u->bind_param('sssssi', $bn, $an, $ac, $ifsc, $br, $eid); $ok = $u->execute(); }
    else { $q->close(); $u = $db->prepare("INSERT INTO bank_kyc (user_id,bank_name,account_name,account_number,ifsc,branch,status,date_time) VALUES (?,?,?,?,?,?,'Approved',NOW())"); $u->bind_param('ssssss', $uniqueUserId, $bn, $an, $ac, $ifsc, $br); $ok = $u->execute(); }
    echo json_encode(['ok' => $ok]); exit;
  }
  if ($_POST['action'] === 'submit_upi') {
    $upiId = trim($_POST['upi_id'] ?? ''); $qrPath = $_POST['current_qr'] ?? '';
    if (!$upiId) { echo json_encode(['ok' => false, 'error' => 'UPI ID required']); exit; }
    if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === 0) {
      $dir = 'uploads/qr/'; if (!is_dir($dir)) mkdir($dir, 0777, true);
      $ext = pathinfo($_FILES['qr_image']['name'], PATHINFO_EXTENSION);
      $newFile = $dir . 'qr_' . time() . '_' . rand(100, 999) . '.' . $ext;
      if (move_uploaded_file($_FILES['qr_image']['tmp_name'], $newFile)) { $qrPath = $newFile; }
    }
    $q = $db->prepare("SELECT id FROM upi_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $q->bind_param('s', $uniqueUserId); $q->execute(); $q->bind_result($eid);
    if ($q->fetch()) { $q->close(); $u = $db->prepare("UPDATE upi_kyc SET upi_id=?, qr_image=?, status='Linked', date_time=NOW() WHERE id=?"); $u->bind_param('ssi', $upiId, $qrPath, $eid); $ok = $u->execute(); }
    else { $q->close(); $u = $db->prepare("INSERT INTO upi_kyc (user_id,upi_id,qr_image,status,date_time) VALUES (?,?,?, 'Linked', NOW())"); $u->bind_param('sss', $uniqueUserId, $upiId, $qrPath); $ok = $u->execute(); }
    echo json_encode(['ok' => $ok]); exit;
  }
  if ($_POST['action'] === 'submit_upi_number') {
    $type = strtolower(trim($_POST['upi_type'] ?? '')); $num = preg_replace('/\D+/', '', $_POST['upi_number'] ?? '');
    if (strlen($num) !== 10) { echo json_encode(['ok' => false, 'error' => '10 digit number required']); exit; }
    $chk = $db->prepare("SELECT id FROM upi_numbers WHERE user_id=? AND upi_type=? ORDER BY id DESC LIMIT 1");
    $chk->bind_param('ss', $uniqueUserId, $type); $chk->execute(); $chk->bind_result($eid);
    if ($chk->fetch()) { $chk->close(); $u = $db->prepare("UPDATE upi_numbers SET upi_number=?, date_time=NOW() WHERE id=?"); $u->bind_param('si', $num, $eid); $ok = $u->execute(); }
    else { $chk->close(); $u = $db->prepare("INSERT INTO upi_numbers (user_id,upi_number,upi_type,date_time) VALUES (?,?,?,NOW())"); $u->bind_param('sss', $uniqueUserId, $num, $type); $ok = $u->execute(); }
    echo json_encode(['ok' => $ok]); exit;
  }
  exit;
}

// Fetch Game Rates for Sharing
$gameRates = [];
try {
    $resRates = $db->query("SELECT game_type, play_amount, win_amount FROM game_rates ORDER BY id ASC");
    if ($resRates) {
        while ($r = $resRates->fetch_assoc()) {
            $gameRates[] = $r;
        }
    }
} catch (Throwable $e) {}

/* Side menu identity fallback */
$sideUserName = $userName;
$sideUserMobile = $userMobile ?: $displayCode;
$sideUserSince = $userCreatedAt ? date('d-m-Y', strtotime($userCreatedAt)) : '';
$code = $displayCode ?: ($uniqueUserId ?? '');

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#070b14">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Bank Details | SUBH GAMING</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css">
    <style>
        :root {
            --primary-bg: #f8f9fa;
            --card-bg: #ffffff;
            --accent-main: #000000;
            --accent-red: #e60000;
            --text-main: #333333;
            --text-dim: #666666;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            --border-radius: 15px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        html { background-color: var(--primary-bg); }
        body { background-color: var(--primary-bg); color: var(--text-main); padding-bottom: 100px; overflow-x: hidden; }

        header {
            background-color: #ff005c;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-bottom: none;
        }

        .header-left,
        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title-flash {
            color: #ffffff;
            font-size: 17px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 0px;
        }

        .wallet-display {
            background: #ffffff;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            color: #000000;
            font-weight: 800;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 6px;
        }

        .wallet-icon-btn {
            display: none;
        }

        .bell-icon-btn {
            background: transparent;
            color: #ffffff !important;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
            text-decoration: none;
            border: none;
            font-size: 20px;
        }

        /* Header Toggle Btn */
        #menuToggle {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000000 !important;
            box-shadow: none;
            cursor: pointer;
            border: none;
            font-size: 18px !important;
        }

        .content { max-width: 480px; margin: 0 auto; padding: 15px; }
        .section-title { font-size: 18px; font-weight: 600; margin-bottom: 15px; color: #333; border-left: 4px solid #000000; padding-left: 10px; text-transform: uppercase; }

        /* Sync Cards */
        .sync-card {
            background-color: #ffffff;
            border-radius: 20px;
            padding: 14px 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(255, 0, 92, 0.3);
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
            color: #000000;
            gap: 14px;
            cursor: pointer;
        }
        .sync-card:active {
            transform: scale(0.98);
        }
        .card-icon {
            width: 42px;
            height: 42px;
            border: none;
            border-radius: 50%;
            background: #ff005c !important;
            color: #000000 !important;
            box-shadow: 0 4px 10px rgba(255, 0, 92, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .card-icon i {
            color: #000000 !important;
        }
        .card-info { flex: 1; display: flex; flex-direction: column; min-width: 0; text-align: left; }
        .card-label { font-size: 10px; font-weight: 800; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .card-value { font-size: 15px; font-weight: 800; color: #000000; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .status-badge { font-size: 10px; font-weight: 900; padding: 4px 12px; border-radius: 12px; text-transform: uppercase; }
        .status-pending { background: rgba(243, 156, 18, 0.1); color: #f39c12; border: 1px solid #f39c12; }
        .status-approved { background: #2ecc71 !important; color: #ffffff !important; border: none !important; }
        .status-linked { background: #2ecc71 !important; color: #ffffff !important; border: none !important; }
        .status-incomplete { background: #ff005c !important; color: #ffffff !important; border: none !important; }

        /* Grid for Small UPI Apps */
        .upi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 15px; }
        .mini-card {
            background-color: #ffffff;
            border-radius: 20px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(255, 0, 92, 0.3);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .mini-card:active {
            transform: scale(0.98);
        }
        .mini-card img { height: 40px; object-fit: contain; }
        .mini-status { font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; }
        .text-green { color: #2ecc71; }
        .text-red { color: #e60000; }

        /* Bottom Menu (Floating Center Design) */
        nav.bottom-menu {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 75px;
            background: #ffffff;
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 2000;
            box-shadow: 0 -5px 25px rgba(0, 0, 0, 0.08);
            border-top: 1px solid rgba(0, 0, 0, 0.04);
            animation: jellyBounce 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
        }

        @keyframes jellyBounce {
            0% {
                opacity: 0;
                transform: scale(0.9);
            }

            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .menu-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #666666;
            text-decoration: none;
            flex: 1;
            height: 100%;
            transition: all 0.3s ease;
        }

        .menu-item i {
            width: 38px;
            height: 38px;
            background: transparent;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #000000;
            margin-bottom: 6px;
            box-shadow: none;
            border: 1.5px solid #000000;
        }

        .menu-item span {
            font-size: 11px;
            font-weight: 500;
            color: #000000;
        }

        .menu-item.active span {
            font-weight: 700;
        }

        .menu-item.active i {
            box-shadow: none;
            background: rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
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

        .menu-item.home-item {
            position: relative;
            top: -12px;
            flex: 1.2;
            animation: floatHome 3s infinite ease-in-out;
        }

        @keyframes floatHome {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .menu-item.home-item i {
            width: 55px !important;
            height: 55px !important;
            background: #ff005c !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #000000 !important;
            font-size: 24px !important;
            box-shadow: 0 4px 10px rgba(255, 0, 92, 0.4) !important;
            border: none !important;
            margin-bottom: 0 !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .menu-item.home-item:active i {
            transform: scale(0.95);
            box-shadow: 0 2px 5px rgba(255, 0, 92, 0.4) !important;
        }

        .menu-item.home-item span {
            display: none;
        }

        /* SIDE MENU Standardized (V6 Black & Gold) */
        .side-menu-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .6);
            display: none;
            z-index: 4000;
            backdrop-filter: blur(2px);
        }

        .side-menu {
            position: fixed;
            top: 0;
            left: -240px;
            width: min(240px, 80vw);
            height: 100%;
            background: #ffffff;
            color: #333333;
            padding: 0;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 4100;
            box-shadow: 10px 0 30px rgba(0, 0, 0, 0.12);
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(0, 0, 0, 0.06);
        }

        .side-menu.open {
            left: 0;
        }

        .side-menu-header {
            padding: 16px 14px;
            background: #ff005c;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .profile-avatar {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #ff005c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #ffffff;
            box-shadow: none;
            flex-shrink: 0;
            text-decoration: none !important;
            border: 2px solid #ffffff;
        }

        .profile-avatar i {
            text-decoration: none !important;
        }

        .profile-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
            padding-top: 2px;
        }

        .profile-name {
            font-size: 17px;
            font-weight: 800;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .profile-id {
            font-size: 11px;
            margin-top: 2px;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 600;
        }

        .profile-since {
            font-size: 11px;
            margin-top: 4px;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }

        .side-close-btn {
            margin-left: auto;
            background: transparent;
            border: none;
            color: #ffffff;
            font-size: 24px;
            cursor: pointer;
            transition: transform 0.2s;
            line-height: 1;
            padding: 4px;
        }
        
        .side-close-btn:hover {
            transform: scale(1.1);
        }

        .side-menu-body {
            padding: 14px 0 8px;
            flex: 1;
            overflow-y: auto;
        }

        .side-menu-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 11px 14px;
            margin: 2px 0;
            font-size: 13px;
            color: #1f2735;
            text-decoration: none;
            border-radius: 0;
            background: transparent;
            border: none;
            transition: all 0.3s ease;
            font-weight: 700;
        }

        .side-menu-item i {
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #ffffff;
            background: linear-gradient(135deg, #ff005c 0%, #ff4b82 50%, #ff005c 100%);
            box-shadow: 0 4px 6px rgba(255, 0, 92, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
        }

        .side-menu-item:hover {
            background: rgba(0, 0, 0, 0.04);
            color: #000000;
            transform: none;
            box-shadow: none;
        }
        
        .side-menu-item:hover i {
            color: #ffffff;
            transform: scale(1.05);
            box-shadow: 0 10px 20px rgba(255, 0, 92, 0.2);
        }

        .side-menu-item.active {
            background: rgba(0, 0, 0, 0.06);
            color: #000000;
            font-weight: 700;
            border: none;
            box-shadow: none;
        }

        .side-menu-item.active i {
            color: #ffffff;
        }

        .side-menu-footer {
            padding: 16px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            background: #ffffff;
        }

        .side-footer-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .side-logout-btn {
            flex: 1;
            height: 48px;
            background: #ffffff;
            color: #e74c3c;
            border: 1px solid #e74c3c;
            border-radius: 999px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: none;
        }

        .share-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: linear-gradient(135deg, #e6e9f0, #eef1f5, #bdc3c7);
            color: #000000;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Modals and Forms */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); display: none; z-index: 5000; align-items: center; justify-content: center; backdrop-filter: blur(5px); padding: 20px; }
        .modal-content { background: #fff; width: 100%; max-width: 420px; border-radius: 15px; overflow: hidden; border: none; box-shadow: 0 20px 50px rgba(0,0,0,0.15); }
        .modal-header { padding: 15px 20px; background: #fff; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 15px; font-weight: 800; color: #333; text-transform: uppercase; }
        .modal-body { padding: 20px; }
        .modal-body.view-mode { color: #333; font-size: 15px; line-height: 2.2; font-weight: 700; }
        .modal-body.view-mode b { color: #888; font-size: 10px; text-transform: uppercase; display: inline-block; width: 120px; font-weight: 800; }
        
        .form-group { margin-bottom: 20px; width: 100%; text-align: center; }
        .form-group label { display: block; font-size: 11px; font-weight: 800; color: #888; margin-bottom: 8px; text-transform: uppercase; }
        .form-control { width: 100%; height: 48px; background: #fff; border-radius: 12px; border: 1px solid #ddd; padding: 0 15px; font-size: 16px; font-weight: 700; outline: none; color: #333; text-align: center; }
        .form-control:focus { border-color: #000; box-shadow: 0 0 0 3px rgba(0,0,0,0.08); }
        
        .mod-btn { width: 100%; padding: 12px; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; text-transform: uppercase; background: #ff005c; color: #fff; box-shadow: 0 4px 12px rgba(255, 0, 92, 0.4); transition: 0.2s; }
        .mod-btn:active { transform: translateY(2px); }
        .mod-btn.cancel-btn { background: #000000; color: #fff; margin-top: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); }

        #toast { position: fixed; top: 25px; left: 50%; transform: translateX(-50%); background: #000; color: #fff; padding: 12px 30px; border-radius: 40px; z-index: 9999; font-weight: 800; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.3); font-size: 14px; }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <div id="toast"></div>

    <header>
        <div class="header-left">
            <i class="fa-solid fa-bars-staggered" id="menuToggle"></i>
            <img src="images/logo.png" alt="SUBH GAMING" style="height: 16px; filter: brightness(0);">
        </div>
        <div class="header-right">
            <a href="deposit.php" class="wallet-display" style="text-decoration: none;">
                <span id="walletVal">₹<?= number_format($bankTotalBal, 2) ?></span>
            </a>
            <a href="deposit.php" class="wallet-icon-btn">
                <i class="fa-solid fa-wallet"></i>
            </a>
            <a href="notice_board.php" class="bell-icon-btn" id="notifyLink" style="position: relative;">
                <i class="fa-solid fa-bell"></i>
                <div id="notifyDot" style="display: none; position: absolute; top: 0; right: 0; width: 8px; height: 8px; background: #ff1f50; border-radius: 50%;"></div>
            </a>
        </div>
    </header>

    <div class="content">
        <?php if ($isActive): ?>
            <div class="section-title"><?= __("KYC & BANK ACCESS") ?></div>
            
            <div class="sync-card" onclick="<?= $bankStatus === 'Incomplete' ? "openMod('bank-modal')" : "showBankView()" ?>">
                <div class="card-icon"><i class="fi fi-sr-bank"></i></div>
                <div class="card-info">
                    <label class="card-label"><?= __("Primary Bank Account") ?></label>
                    <span class="card-value"><?= $bankStatus === 'Incomplete' ? __("Add Bank Account") : ($bankData['bank_name'] ?? __("View Bank Details")) ?></span>
                </div>
                <span class="status-badge status-<?= strtolower($bankStatus) ?>"><?= $bankStatus === 'Incomplete' ? __("Add Now") : __("Linked") ?></span>
            </div>

            <div class="sync-card" onclick="<?= $upiStatus === 'Incomplete' ? "openMod('upi-modal')" : "showUpiView()" ?>">
                <div class="card-icon"><i class="fi fi-sr-id-badge"></i></div>
                <div class="card-info">
                    <label class="card-label"><?= __("Verified UPI Identity") ?></label>
                    <span class="card-value"><?= $upiStatus === 'Incomplete' ? __("Submit UPI QR") : ($upiData['upi_id'] ?? __("View UPI QR")) ?></span>
                </div>
                <span class="status-badge status-<?= strtolower($upiStatus) ?>"><?= $upiStatus === 'Incomplete' ? __("Add Now") : __("Linked") ?></span>
            </div>

            <div class="section-title" style="margin-top:30px;"><?= __("Direct Transfer Apps") ?></div>
            <div class="upi-grid">
                <?php 
                $apps = [
                    ['id'=>'phonepe', 'name'=>__('PhonePe'), 'img'=>'images/phonepe.png'],
                    ['id'=>'gpay', 'name'=>__('G-Pay'), 'img'=>'images/googlepay.png'],
                    ['id'=>'paytm', 'name'=>__('Paytm'), 'img'=>'images/paytm.png'],
                    ['id'=>'bhim', 'name'=>__('BHIM'), 'img'=>'images/bhim.png'],
                ];
                foreach($apps as $app): 
                    $exists = !empty($upiNums[$app['id']]);
                ?>
                <div class="mini-card" onclick="openMiniUpi('<?= $app['id'] ?>', '<?= $app['name'] ?>', '<?= $upiNums[$app['id']] ?>')">
                    <img src="<?= $app['img'] ?>" alt="<?= $app['name'] ?>">
                    <div class="mini-status <?= $exists ? 'text-green':'text-red' ?>"><?= $exists ? __("Linked") : __("Add Now") ?></div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div style="text-align:center; padding:70px 25px;">
                <i class="fi fi-sr-lock" style="font-size:70px; color:#000; margin-bottom:30px;"></i>
                <h2 style="color:#000; font-weight:900;"><?= __("KYC LOCKED") ?></h2>
                <p style="color:var(--text-dim); margin-top:12px;">Please contact admin to enable Bank/KYC access for your account.</p>
                <a href="https://wa.me/917021812900" style="margin-top:35px; background:#000; color:#fff; padding:16px 40px; border-radius:40px; text-decoration:none; display:inline-flex; align-items:center; gap:12px; font-weight:800; box-shadow:0 12px 25px rgba(0,0,0,0.18);"><i class="fa-brands fa-whatsapp" style="font-size:24px;"></i> CHAT WITH ADMIN</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modals -->
    <div id="bank-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header"><h3><?= __("Add Bank Account") ?></h3><i class="fi fi-rr-cross-small" style="cursor:pointer; font-size:22px;" onclick="closeMod('bank-modal')"></i></div>
            <div class="modal-body">
                <div class="form-group"><label><?= __("Bank Name") ?></label><input type="text" id="bn_name" class="form-control" placeholder="e.g. HDFC Bank"></div>
                <div class="form-group"><label><?= __("Account Holder Name") ?></label><input type="text" id="bn_holder" class="form-control" placeholder="<?= __("Your Name") ?>"></div>
                <div class="form-group"><label><?= __("Account Number") ?></label><input type="text" id="bn_acc" class="form-control" placeholder="<?= __("Acc Number") ?>"></div>
                <div class="form-group"><label><?= __("IFSC Code") ?></label><input type="text" id="bn_ifsc" class="form-control" placeholder="<?= __("11 Digit Code") ?>"></div>
                <div class="form-group"><label><?= __("Branch Name") ?></label><input type="text" id="bn_branch" class="form-control" placeholder="<?= __("Branch City") ?>"></div>
                <button class="mod-btn" onclick="submitBank()"><?= __("SAVE DETAILS") ?></button>
                <button class="mod-btn cancel-btn" onclick="closeMod('bank-modal')"><?= __("CLOSE") ?></button>
            </div>
        </div>
    </div>

    <div id="bank-view" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header"><h3><?= __("Bank Details") ?></h3><i class="fi fi-rr-cross-small" style="cursor:pointer; font-size:22px;" onclick="closeMod('bank-view')"></i></div>
            <div class="modal-body view-mode" id="bankViewBody"></div>
            <div style="padding: 0 25px 25px;">
                <button class="mod-btn" onclick="openMod('bank-modal'); closeMod('bank-view');"><?= __("EDIT DETAILS") ?></button>
                <button class="mod-btn cancel-btn" onclick="closeMod('bank-view')"><?= __("CLOSE") ?></button>
            </div>
        </div>
    </div>

    <div id="upi-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header"><h3><?= __("Update UPI/QR") ?></h3><i class="fi fi-rr-cross-small" style="cursor:pointer;" onclick="closeMod('upi-modal')"></i></div>
            <div class="modal-body">
                <div class="form-group"><label>UPI ID</label><input type="text" id="u_id" class="form-control" placeholder="username@upi"></div>
                <div class="form-group">
                    <label>Upload QR Image</label>
                    <input type="file" id="u_qr" class="form-control" accept="image/*" onchange="previewQR(this)" style="padding-top:10px;">
                    <div id="qrPreview" style="margin-top:10px; border:2px dashed #eee; border-radius:12px; height:120px; display:flex; align-items:center; justify-content:center; overflow:hidden;"><span style="color:#aaa;"><?= __("No File Selected") ?></span></div>
                </div>
                <button class="mod-btn" onclick="submitUpi()">VERIFY & SAVE</button>
                <button class="mod-btn cancel-btn" onclick="closeMod('upi-modal')"><?= __("CLOSE") ?></button>
            </div>
        </div>
    </div>

    <div id="upi-view" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header"><h3><?= __("UPI Details") ?></h3><i class="fi fi-rr-cross-small" style="cursor:pointer;" onclick="closeMod('upi-view')"></i></div>
            <div class="modal-body" style="text-align:center;">
                <div style="font-weight:900; color:#000; font-size:18px; margin-bottom:15px;" id="v_upi_val"></div>
                <div style="background:#f9f9f9; padding:15px; border-radius:15px;">
                    <img id="v_upi_qr" style="max-width:200px; display:none;">
                    <div id="v_upi_noqr" style="color:#999;">QR Not Uploaded</div>
                </div>
                <button class="mod-btn" style="margin-top:20px;" onclick="openMod('upi-modal'); closeMod('upi-view');"><?= __("CHANGE / RE-UPLOAD") ?></button>
                <button class="mod-btn cancel-btn" onclick="closeMod('upi-view')"><?= __("CLOSE") ?></button>
            </div>
        </div>
    </div>

    <div id="mini-upi-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header"><h3 id="miniTitle"><?= __("Link App") ?></h3><i class="fi fi-rr-cross-small" style="cursor:pointer;" onclick="closeMod('mini-upi-modal')"></i></div>
            <div class="modal-body">
                <input type="hidden" id="miniType">
                <div class="form-group"><label><?= __("Mobile Number") ?></label><input type="text" id="miniVal" class="form-control" maxlength="10" placeholder="<?= __("10 Digit Number") ?>"></div>
                <button class="mod-btn" onclick="saveMini()"><?= __("LINK NOW") ?></button>
                <button class="mod-btn cancel-btn" onclick="closeMod('mini-upi-modal')"><?= __("CANCEL") ?></button>
            </div>
        </div>
    </div>

    <!-- SIDE MENU -->
    <div class="side-menu-overlay" id="sideOverlay"></div>
    <div class="side-menu" id="sideMenu">
        <div class="side-menu-header">
            <a href="user_profile.php" class="profile-avatar"><i class="fi fi-rr-user"></i></a>
            <div class="profile-text">
                <div class="profile-name"><?= htmlspecialchars($userName) ?></div>
                <div class="profile-id"><?= htmlspecialchars($userMobile ?: $code) ?></div>

            </div>
            <button type="button" class="side-close-btn" id="sideCloseBtn"><i class="fas fa-times"></i></button>
        </div>

        <div class="side-menu-body">
            <a href="user_dashboard.php" class="side-menu-item">
                <i class="fi fi-rr-home"></i>
                <span class="label"><?= __("Home") ?></span>
            </a>
            <a href="user_profile.php" class="side-menu-item">
                <i class="fi fi-rr-user"></i>
                <span class="label"><?= __("Profile") ?></span>
            </a>
            <a href="price_chart.php" class="side-menu-item">
                <i class="fi fi-rr-stats"></i>
                <span class="label"><?= __("Game Rates") ?></span>
            </a>
            <a href="how_to_play.php" class="side-menu-item">
                <i class="fi fi-rr-play-alt"></i>
                <span class="label"><?= __("How to Play") ?></span>
            </a>

            <a href="user_wining.php" class="side-menu-item">
                <i class="fi fi-rr-trophy"></i>
                <span class="label"><?= __("Winning History") ?></span>
            </a>
            <a href="user_bid_history.php" class="side-menu-item">
                <i class="fi fi-rr-list"></i>
                <span class="label"><?= __("Bid History") ?></span>
            </a>
            <a href="user_panel_chart.php" class="side-menu-item">
                <i class="fi fi-rr-apps"></i>
                <span class="label"><?= __("Panel Chart") ?></span>
            </a>
            <a href="app/mahadevking.apk" download class="side-menu-item">
                <i class="fi fi-rr-download"></i>
                <span class="label"><?= __("Latest App") ?></span>
            </a>
        <a href="languages.php" class="side-menu-item">
            <i class="fa-solid fa-language"></i><span class="label"><?= __("Languages") ?></span>
        </a>
            <a href="javascript:void(0)" class="side-menu-item" id="shareBtn">
                <i class="fi fi-rr-share"></i>
                <span class="label"><?= __("Share App") ?></span>
            </a>
            <a href="user_logout.php" class="side-menu-item">
                <i class="fi fi-rr-sign-out-alt"></i>
                <span class="label"><?= __("Logout") ?></span>
            </a>
        </div>
    </div>

    <!-- Bottom Menu -->
    <nav class="bottom-menu">
        <a href="user_result.php" class="menu-item"><i class="fi fi-rr-trophy"></i><span><?= __("My Bids") ?></span></a>
        <a href="deposit.php" class="menu-item"><i class="fi fi-rr-wallet"></i><span><?= __("Funds") ?></span></a>
        <a href="user_dashboard.php" class="menu-item home-item"><i class="fi fi-rr-home"></i><span><?= __("HOME") ?></span></a>
        <a href="user_bank.php" class="menu-item active"><i class="fi fi-rr-bank"></i><span><?= __("Bank") ?></span></a>
        <a href="live_chat.php" class="menu-item"><i class="fi fi-rr-headset"></i><span><?= __("Support") ?></span></a>
    </nav>

    <script>
var BANK = <?= json_encode($bankData) ?>;
var UPI = <?= json_encode($upiData) ?>;
var doneSound = new Audio('sound/done.mp3');

function openMod(id) {document.getElementById(id).style.display = 'flex';document.body.style.overflow = 'hidden';}
function closeMod(id) {document.getElementById(id).style.display = 'none';document.body.style.overflow = '';}
function showT(msg) {var t = document.getElementById('toast');t.innerText = msg;t.style.display = 'block';setTimeout(function () {t.style.display = 'none';}, 3000);}

function showBankView() {
  var html = "<b><?= __("Bank Name") ?></b> ".concat(BANK.bank_name, "<br><b><?= __("Holder") ?></b> ").concat(BANK.account_name, "<br><b><?= __("Acc Number") ?></b> ").concat(BANK.account_number, "<br><b><?= __("IFSC Code") ?></b> ").concat(BANK.ifsc, "<br><b><?= __("Branch") ?></b> ").concat(BANK.branch);
  document.getElementById('bankViewBody').innerHTML = html;
  openMod('bank-view');
}
function showUpiView() {
  document.getElementById('v_upi_val').innerText = UPI.upi_id;
  var img = document.getElementById('v_upi_qr');
  var noqr = document.getElementById('v_upi_noqr');
  if (UPI.qr_image) {img.src = UPI.qr_image.split('?')[0] + '?t=' + new Date().getTime();img.style.display = 'inline-block';noqr.style.display = 'none';} else
  {img.style.display = 'none';noqr.style.display = 'block';}
  openMod('upi-view');
}
function previewQR(input) {
  var preview = document.getElementById('qrPreview');
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function (e) {preview.innerHTML = '<img src="' + e.target.result + '" style="max-height:100%;">';};
    reader.readAsDataURL(input.files[0]);
  }
}
function openMiniUpi(type, name, val) {
  document.getElementById('miniTitle').innerText = 'Setup ' + name;
  document.getElementById('miniType').value = type;
  document.getElementById('miniVal').value = val || '';
  openMod('mini-upi-modal');
}

function submitBank() {
  try {doneSound.play();} catch (e) {}
  var fd = new FormData();
  fd.append('action', 'submit_bank');
  fd.append('bank_name', document.getElementById('bn_name').value);
  fd.append('account_name', document.getElementById('bn_holder').value);
  fd.append('account_number', document.getElementById('bn_acc').value);
  fd.append('ifsc', document.getElementById('bn_ifsc').value);
  fd.append('branch', document.getElementById('bn_branch').value);
  fetch('', { method: 'POST', body: fd }).then(function (r) {return r.json();}).then(function (j) {
    if (j.ok) {
      showT('Saving Details...');
      setTimeout(function () {location.reload();}, 1200);
    } else {showT(j.error);}
  });
}
function submitUpi() {
  try {doneSound.play();} catch (e) {}
  var fd = new FormData();
  fd.append('action', 'submit_upi');
  fd.append('upi_id', document.getElementById('u_id').value);
  fd.append('current_qr', UPI.qr_image || '');
  var file = document.getElementById('u_qr').files[0];
  if (file) fd.append('qr_image', file);
  fetch('', { method: 'POST', body: fd }).then(function (r) {return r.json();}).then(function (j) {
    if (j.ok) {
      showT('Saving UPI...');
      setTimeout(function () {location.reload();}, 1200);
    } else {showT(j.error);}
  });
}
function saveMini() {
  try {doneSound.play();} catch (e) {}
  var fd = new FormData();
  fd.append('action', 'submit_upi_number');
  fd.append('upi_type', document.getElementById('miniType').value);
  fd.append('upi_number', document.getElementById('miniVal').value);
  fetch('', { method: 'POST', body: fd }).then(function (r) {return r.json();}).then(function (j) {
    if (j.ok) {
      showT('Linking App...');
      setTimeout(function () {location.reload();}, 1200);
    } else {showT(j.error);}
  });
}

/* SIDE MENU TOGGLE */
var menuBtn = document.getElementById('menuToggle');
var sideMenu = document.getElementById('sideMenu');
var sideOverlay = document.getElementById('sideOverlay');
var sideClose = document.getElementById('sideCloseBtn');

function openM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();
  sideMenu.classList.add('open');
  sideOverlay.style.display = 'block';
  document.body.style.overflow = 'hidden';
}
function closeM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(true); } if(e && e.preventDefault) e.preventDefault();
  sideMenu.classList.remove('open');
  sideOverlay.style.display = 'none';
  document.body.style.overflow = '';
}

if (menuBtn) menuBtn.addEventListener('click', openM);
if (sideOverlay) sideOverlay.addEventListener('click', closeM);
if (sideClose) sideClose.addEventListener('click', closeM);

/* SHARE */
var shareBtn = document.getElementById('shareBtn');
if (shareBtn) shareBtn.addEventListener('click', function () {
  closeM();
  var url = "https://subhgaming.online/download.php";
  <?php
            $shareText = "Market Price \n♠️*Game Rate*♠️ \n\n";
            if (!empty($gameRates)) {
                foreach ($gameRates as $r) {
                    $shareText .= "▪️" . $r['game_type'] . " ₹" . $r['play_amount'] . " = ₹" . $r['win_amount'] . " ";
                }
            } else {
                $shareText .= "▪️Single Digit ₹10 = ₹95 ▪️Jodi Digit ₹10 = ₹950 ▪️Single Pana ₹10 = ₹1500 ▪️Double Pana ₹10 = ₹3000 ▪️Triple Pana ₹10 = ₹7000 ▪️Half Sangam ₹10 = ₹10000 ▪️Full Sangam ₹10 = ₹100000 ";
            }
            $shareText .= "\n\nDownload App:\n" . "https://subhgaming.online/download.php";
            ?>;
  var fullText = <?= json_encode($shareText, JSON_UNESCAPED_UNICODE) ?>;
  if (navigator.share) {
    navigator.share({ text: fullText });
  } else {
    window.location.href = 'whatsapp://send?text=' + encodeURIComponent(fullText);
  }
});</script>
    <script src="notify_bundle.php?v=2"></script>
    <script src="assets/js/user_notice_tracker.js?v=4"></script>
</body>
</html>
