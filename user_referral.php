<?php
require_once 'includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/user_ping.php';
require_once 'includes/live_ping.php';

if (!isset($_SESSION['user'])) { header('Location: user_login.php'); exit; }

$uid = (int)$_SESSION['user']['id'];
$db  = db();

/* ── Helpers ── */
if (!function_exists('rk_get_id_variations')) {
  function rk_get_id_variations(string $id, ?array $uData = null): array {
    $id = trim($id);
    if (!$id) return [];
    $v = [$id, strtoupper($id), strtolower($id)];

    if ($uData) {
      if (!empty($uData['user_id'])) {
        $v[] = (string)$uData['user_id'];
        $v[] = strtoupper((string)$uData['user_id']);
        $v[] = strtolower((string)$uData['user_id']);
      }
      if (!empty($uData['mobile_no'])) $v[] = (string)$uData['mobile_no'];
      if (!empty($uData['id'])) $v[] = (string)$uData['id'];
    }

    if (preg_match('/^\d+$/', $id)) {
      $v[] = 'BB24-' . str_pad($id, 2, '0', STR_PAD_LEFT);
      $v[] = 'GR' . str_pad($id, 2, '0', STR_PAD_LEFT);
      $v[] = 'GR' . str_pad($id, 3, '0', STR_PAD_LEFT);
      $v[] = 'GR' . str_pad($id, 5, '0', STR_PAD_LEFT);
    }
    if (preg_match('/^BB24-(\d+)$/i', $id, $m)) {
      $num = ltrim($m[1], '0') ?: '0';
      $v[] = $num;
    }
    if (preg_match('/^([A-Z]+)(\d+)$/i', $id, $m)) {
      $p = strtoupper($m[1]);
      $nStr = $m[2];
      $n = (int)ltrim($nStr, '0');
      $v[] = $p . $n;
      $v[] = $p . str_pad((string)$n, 2, '0', STR_PAD_LEFT);
      $v[] = $p . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
      $v[] = $p . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
      $v[] = $p . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
      $v[] = strtolower($p) . $n;
      $v[] = strtolower($p) . str_pad((string)$n, 2, '0', STR_PAD_LEFT);
      $v[] = strtolower($p) . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
      $v[] = strtolower($p) . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
    }
    return array_values(array_unique(array_filter($v, 'strlen')));
  }
}

/* ── Date Filter ── */
$range    = $_GET['range'] ?? 'all';
$fromDate = $_GET['from'] ?? '';
$toDate   = $_GET['to'] ?? '';

$dateCondRef = "";
$dateCondDep = "";

if ($range === 'today') {
    $dateCondRef = " AND DATE(created_at) = CURDATE()";
    $dateCondDep = " AND DATE(date_time) = CURDATE()";
} elseif ($range === '7') {
    $dateCondRef = " AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $dateCondDep = " AND DATE(date_time) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($range === '15') {
    $dateCondRef = " AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)";
    $dateCondDep = " AND DATE(date_time) >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)";
} elseif ($range === '30') {
    $dateCondRef = " AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $dateCondDep = " AND DATE(date_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
} elseif ($range === 'custom' && $fromDate && $toDate) {
    // Escape dates securely
    $f = $db->real_escape_string($fromDate);
    $t = $db->real_escape_string($toDate);
    $dateCondRef = " AND DATE(created_at) BETWEEN '$f' AND '$t'";
    $dateCondDep = " AND DATE(date_time) BETWEEN '$f' AND '$t'";
}

/* ── Create table ── */
try { $db->query("CREATE TABLE IF NOT EXISTS `referrals` (`id` INT AUTO_INCREMENT, `referrer_id` VARCHAR(50) NOT NULL, `referee_id` VARCHAR(50) NOT NULL, `referee_name` VARCHAR(100), `referee_mobile` VARCHAR(20), `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(`id`), UNIQUE KEY `uniq_referee_id`(`referee_id`), KEY `idx_ref`(`referrer_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $e){}

/* ── User ── */
$stmt = $db->prepare("SELECT id, user_id, name, mobile_no, is_active FROM users WHERE id=?");
$stmt->bind_param('i',$uid); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();
if ($user) {
  $c = (string)$user['user_id'];
  if (preg_match('/^\d+$/', $c)) {
    $user['user_id'] = 'KR' . str_pad($c, 2, '0', STR_PAD_LEFT);
  } elseif (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $c, $m)) {
    $user['user_id'] = $m[1] . str_pad($m[2], 2, '0', STR_PAD_LEFT);
  }
}
$code      = $user['user_id'] ?? '';
$userName  = $user['name']    ?? $code;
$isDeactive= isset($user['is_active']) && (int)$user['is_active']===0;
if (isset($user['is_active'])) $_SESSION['user']['is_active']=(int)$user['is_active'];

/* ── Commission % ── */
$level1 = 2.00;
try {
  $r=$db->query("SELECT * FROM referral_com_percnt ORDER BY id DESC LIMIT 1");
  if ($r && $row=$r->fetch_assoc()) {
    $level1 = (float)($row['percentage'] ?? $row['level1'] ?? 2.00);
  }
} catch(Throwable $e){}
$totalPct = $level1;
$commDisp = rtrim(rtrim(number_format($totalPct,2),'0'),'.');

/* ── Auto-sync any existing deposits ── */
if (function_exists('process_all_pending_referral_commissions')) { process_all_pending_referral_commissions($db); }

/* ── Data ── */
$totalReferred=0; $totalDeposits=0; $totalComm=0;
$appLink = 'https://subhgaming.online/download.php';

if ($code) {
  $ref_vars  = rk_get_id_variations($code, $user);
  if (!empty($ref_vars)) {
    $in = implode(',', array_fill(0, count($ref_vars), '?'));
    $t  = str_repeat('s', count($ref_vars));

    // Total referred count (from referrals table + users table)
    $q = $db->prepare("SELECT COUNT(DISTINCT referee_user_id) FROM (
        SELECT referee_id AS referee_user_id FROM referrals WHERE referrer_id IN ($in) $dateCondRef
        UNION
        SELECT user_id AS referee_user_id FROM users WHERE referral IN ($in) $dateCondRef
    ) AS combined_refs");
    if($q){ $q->bind_param($t.$t,...array_merge($ref_vars, $ref_vars)); $q->execute(); $q->bind_result($totalReferred); $q->fetch(); $q->close(); }

    // Total commission earned
    $q = $db->prepare("SELECT IFNULL(SUM(amount),0) FROM deposit WHERE status='Done' AND method IN ('REF_COMM', 'Referral Commission') AND user_id IN ($in) $dateCondDep");
    if($q){ $q->bind_param($t,...$ref_vars); $q->execute(); $q->bind_result($totalComm); $q->fetch(); $q->close(); }

    // Total referred deposits
    $depVars=[];
    $q=$db->prepare("SELECT DISTINCT u.user_id, u.mobile_no FROM users u LEFT JOIN referrals r ON r.referee_id=u.user_id WHERE u.referral IN ($in) OR r.referrer_id IN ($in)");
    if($q){ $q->bind_param($t.$t,...array_merge($ref_vars,$ref_vars)); $q->execute(); $res=$q->get_result(); while($r=$res->fetch_assoc()) foreach(rk_get_id_variations((string)$r['user_id'], $r) as $v) $depVars[]=$v; $q->close(); }
    $q=$db->prepare("SELECT DISTINCT referee_id, referee_mobile FROM referrals WHERE referrer_id IN ($in)");
    if($q){ $q->bind_param($t,...$ref_vars); $q->execute(); $res=$q->get_result(); while($r=$res->fetch_assoc()) foreach(rk_get_id_variations((string)$r['referee_id'], ['user_id'=>$r['referee_id'],'mobile_no'=>$r['referee_mobile']]) as $v) $depVars[]=$v; $q->close(); }
    $depVars=array_values(array_unique(array_filter($depVars,'strlen')));
    if(!empty($depVars)){
      $di=implode(',',array_fill(0,count($depVars),'?')); $dt=str_repeat('s',count($depVars));
      $q=$db->prepare("SELECT IFNULL(SUM(amount),0) FROM deposit WHERE status='Done' AND (method IS NULL OR method NOT IN ('REF_COMM', 'Referral Commission')) AND user_id IN ($di) $dateCondDep");
      if($q){ $q->bind_param($dt,...$depVars); $q->execute(); $q->bind_result($totalDeposits); $q->fetch(); $q->close(); }
    }

    /* ── Fetch Detailed Referred Users List ── */
    $referredMap = [];

    // 1. Fetch from referrals table
    $stmt1 = $db->prepare("
      SELECT r.referee_id, r.referee_name, r.referee_mobile, r.created_at, u.name AS u_name, u.mobile_no AS u_mobile, u.user_id AS u_user_id
      FROM referrals r
      LEFT JOIN users u ON (u.user_id = r.referee_id OR u.mobile_no = r.referee_mobile)
      WHERE r.referrer_id IN ($in) $dateCondRef
      ORDER BY r.id DESC
    ");
    if ($stmt1) {
      $stmt1->bind_param($t, ...$ref_vars);
      $stmt1->execute();
      $res1 = $stmt1->get_result();
      while ($r = $res1->fetch_assoc()) {
        $key = $r['referee_id'] ?: $r['referee_mobile'];
        if (!$key) continue;
        $mNo = (!empty($r['u_mobile']) && $r['u_mobile'] !== '0000000000') ? $r['u_mobile'] : $r['referee_mobile'];
        $uName = !empty($r['u_name']) ? $r['u_name'] : $r['referee_name'];
        $uId = !empty($r['u_user_id']) ? $r['u_user_id'] : $r['referee_id'];
        $referredMap[$key] = [
          'id' => $uId,
          'user_id' => $uId,
          'name' => $uName,
          'mobile_no' => $mNo,
          'created_at' => $r['created_at']
        ];
      }
      $stmt1->close();
    }

    // 2. Fetch from users table
    $stmt2 = $db->prepare("
      SELECT u.id, u.user_id, u.name, u.mobile_no, u.created_at
      FROM users u
      WHERE u.referral IN ($in) $dateCondRef
      ORDER BY u.id DESC
    ");
    if ($stmt2) {
      $stmt2->bind_param($t, ...$ref_vars);
      $stmt2->execute();
      $res2 = $stmt2->get_result();
      while ($u = $res2->fetch_assoc()) {
        $key = $u['user_id'] ?: $u['mobile_no'];
        if (!$key) continue;
        if (!isset($referredMap[$key])) {
          $referredMap[$key] = [
            'id' => $u['user_id'],
            'user_id' => $u['user_id'],
            'name' => $u['name'],
            'mobile_no' => $u['mobile_no'],
            'created_at' => $u['created_at']
          ];
        } else {
          if (!empty($u['name'])) $referredMap[$key]['name'] = $u['name'];
          if (!empty($u['mobile_no']) && $referredMap[$key]['mobile_no'] === '0000000000') $referredMap[$key]['mobile_no'] = $u['mobile_no'];
        }
      }
      $stmt2->close();
    }

    // Calculate individual deposit amounts for each referred user
    foreach ($referredMap as $k => &$rUser) {
      $uVars = rk_get_id_variations((string)($rUser['user_id'] ?? ''), ['user_id' => $rUser['user_id'], 'mobile_no' => $rUser['mobile_no']]);
      $uDep = 0;
      if (!empty($uVars)) {
        $inU = implode(',', array_fill(0, count($uVars), '?'));
        $tU  = str_repeat('s', count($uVars));
        $stD = $db->prepare("SELECT IFNULL(SUM(amount), 0) FROM deposit WHERE status='Done' AND (method IS NULL OR method NOT IN ('REF_COMM', 'Referral Commission')) AND user_id IN ($inU) $dateCondDep");
        if ($stD) {
          $stD->bind_param($tU, ...$uVars);
          $stD->execute();
          $stD->bind_result($uDep);
          $stD->fetch();
          $stD->close();
        }
      }
      $rUser['user_deposit'] = (float)$uDep;
    }
    unset($rUser);

    $referredUsersList = array_values($referredMap);
    if (count($referredUsersList) > 0) {
      $totalReferred = max($totalReferred, count($referredUsersList));
    }
  }
}

/* ── Wallet Balance ── */
$totalBalance = 0;
if (!empty($code)) {
    $hasBonusCol = false;
    $resB = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
    if ($resB && $resB->num_rows > 0) { $hasBonusCol = true; }
    
    $depSql = "SELECT (IFNULL(SUM(amount),0) + " . ($hasBonusCol ? "IFNULL(SUM(bonus_amount),0)" : "0") . ") FROM deposit WHERE status='Done' AND user_id=?";
    $st = $db->prepare($depSql);
    $st->bind_param('s', $code);
    $st->execute();
    $st->bind_result($depositDone);
    $st->fetch();
    $st->close();
    
    $st = $db->prepare("SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE LOWER(status) IN ('pending','approved','done') AND user_id=?");
    $st->bind_param('s', $code);
    $st->execute();
    $st->bind_result($withdrawMinus);
    $st->fetch();
    $st->close();
    
    $st = $db->prepare("SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id=?");
    $st->bind_param('s', $code);
    $st->execute();
    $st->bind_result($winBalance);
    $st->fetch();
    $st->close();
    
    $totalBalance = (float)($depositDone - $withdrawMinus) + (float)$winBalance;
    if ($totalBalance < 0) $totalBalance = 0;
}

/* ── WhatsApp Support URL ── */
$waSupportUrl = 'https://wa.me/917020691217';
try {
    $rsWa = $db->query("SELECT whatsapp_no_1, whatsapp_no_2 FROM admin_payment_details WHERE id=1 LIMIT 1");
    if ($rsWa && $rwWa = $rsWa->fetch_assoc()) {
        $waNo = preg_replace('/\D/', '', $rwWa['whatsapp_no_1'] ?: $rwWa['whatsapp_no_2']);
        if (strlen($waNo) === 10) { $waNo = '91' . $waNo; }
        if (!empty($waNo)) { $waSupportUrl = 'https://wa.me/' . $waNo; }
    }
} catch (Throwable $e) {}

$db->close();
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="images/fevicon.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#000000">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title>Refer &amp; Earn | SUBH GAMING</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-brands/css/uicons-brands.css">
<style>
:root {
    --red: #e53935;
    --red-light: #ff5252;
    --green: #27ae60;
    --green-light: #2ecc71;
    --yellow: #ffeb3b;
    --brown: #795548;
    --icon-blue: #0b1e48;
    --danger-red: #e60000;
    --purple: #7b1fa2;
    --grey: #444;
    --primary-bg: #000000;
    --card-bg: #ffffff;
    --text-main: #ffffff;
    --shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
}

html {
    scroll-behavior: smooth;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

body {
    background: #000000 !important;
    background-color: #000000 !important;
    color: #ffffff;
    min-height: 100vh;
    padding-bottom: 100px;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-y: contain;
}

        /* ── Header matching notice_board.php ── */
        header {
            background-color: #000000 !important;
            background-image: none !important;
            border-bottom: 1px solid #1a1a1a;
            padding-top: calc(env(safe-area-inset-top) + 12px) !important;
            padding-bottom: 12px !important;
            padding-left: 14px !important;
            padding-right: 14px !important;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #e53935;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            text-decoration: none;
            font-size: 15px;
            cursor: pointer;
            border: none;
            flex-shrink: 0;
            transition: opacity 0.15s ease;
        }

        .back-btn:active {
            opacity: 0.8;
        }

        .header-title-flash {
            color: #ffffff !important;
            font-size: 19px !important;
            font-weight: 800 !important;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
        }

        .header-title-flash img {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            margin-top: -2px;
        }


.container {
    max-width: 600px;
    margin: 0 auto;
    padding: 15px;
}

/* Filter bar */
.filter-wrapper {
    margin-bottom: 15px;
    width: 100%;
}
.filter-bar {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.fbtn {
    flex: 1;
    min-width: 55px;
    background: #121212;
    border: 1px solid #1a1a1a;
    color: #94a3b8;
    border-radius: 10px;
    padding: 9px 4px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    text-align: center;
    white-space: nowrap;
    transition: all 0.2s;
    text-decoration: none;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}
.fbtn.active {
    background: #e53935;
    color: #ffffff;
    border: none;
    box-shadow: 0 4px 10px rgba(229, 57, 53, 0.35);
}
.fbtn:hover:not(.active) {
    background: rgba(229, 57, 53, 0.15);
    color: #e53935;
}
.custom-row {
    display: none;
    gap: 8px;
    margin-top: 10px;
    align-items: center;
}
.custom-row.show {
    display: flex;
}
.custom-row input {
    flex: 1;
    padding: 10px;
    background: #121212;
    border: 1px solid #1a1a1a;
    border-radius: 8px;
    font-size: 12px;
    color: #ffffff;
    outline: none;
}
.custom-row button {
    background: #e53935;
    color: #fff;
    border-radius: 8px;
    padding: 10px 16px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    border: none;
    box-shadow: 0 3px 0 #b71c1c;
}
.custom-row button:active {
    transform: translateY(2px);
    box-shadow: none;
}

/* Hero Section */
.hero {
    background: linear-gradient(135deg, #121212 0%, #1f1f1f 50%, #0a0a0a 100%);
    border: 1px solid #1a1a1a;
    border-radius: 18px;
    padding: 14px 18px 12px;
    text-align: center;
    margin-bottom: 14px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.45);
}
.hero::before {
    content: '';
    position: absolute;
    top: -40%;
    left: -40%;
    width: 180%;
    height: 180%;
    background: radial-gradient(circle, rgba(229,57,53,0.12) 0%, transparent 60%);
    pointer-events: none;
}
.hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background:
        repeating-linear-gradient(45deg, rgba(255,255,255,0.02) 0, rgba(255,255,255,0.02) 1px, transparent 0, transparent 12px),
        repeating-linear-gradient(-45deg, rgba(255,255,255,0.02) 0, rgba(255,255,255,0.02) 1px, transparent 0, transparent 12px);
    pointer-events: none;
}
.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(229, 57, 53, 0.2);
    border: 1.5px solid #e53935;
    border-radius: 99px;
    color: #ffffff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .8px;
    text-transform: uppercase;
    padding: 4px 12px;
    margin-bottom: 6px;
    position: relative;
    z-index: 1;
    backdrop-filter: blur(4px);
}
.hero-rate {
    font-size: 48px;
    font-weight: 900;
    color: #e53935;
    line-height: 1;
    margin: 2px 0 4px;
    position: relative;
    z-index: 1;
    text-shadow: 0 4px 15px rgba(229,57,53,0.3);
    letter-spacing: -2px;
}
.hero-rate sup {
    font-size: 22px;
    vertical-align: super;
    letter-spacing: 0;
}
.hero-sub {
    font-size: 11px;
    color: rgba(255,255,255,0.85);
    font-weight: 600;
    margin-top: 1px;
    position: relative;
    z-index: 1;
    letter-spacing: 0.3px;
}

/* Stats Cards (White Boxes) */
.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}
.stat {
    background: #ffffff;
    border-radius: 14px;
    padding: 0;
    text-align: center;
    box-shadow: 0 4px 14px rgba(0,0,0,0.1);
    overflow: hidden;
    border: 1px solid #eeeeee;
}
.stat-top {
    height: 4px;
    width: 100%;
}
.stat-top.gr { background: linear-gradient(90deg, #27ae60, #2ecc71); }
.stat-top.bl { background: linear-gradient(90deg, #e53935, #ff5252); }
.stat-top.cy { background: linear-gradient(90deg, #e67e22, #f39c12); }
.stat-inner {
    padding: 12px 6px 10px;
}
.stat .si {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    margin: 0 auto 8px;
}
.si.gr {
    background: linear-gradient(135deg, #d4edda, #a8d5b5);
    color: #1a7a40;
}
.si.bl {
    background: linear-gradient(135deg, #ffebee, #ffcdd2);
    color: #e53935;
}
.si.cy {
    background: linear-gradient(135deg, #fdecd2, #f9c87a);
    color: #b35c00;
}
.stat .sv {
    font-size: 17px;
    font-weight: 900;
    color: #111111;
    line-height: 1;
    margin-bottom: 4px;
}
.stat .sl {
    font-size: 9px;
    font-weight: 800;
    color: #666666;
    text-transform: uppercase;
    letter-spacing: .6px;
}

/* Content Cards (White Boxes) */
.card {
    background: #ffffff;
    border: 1px solid #eeeeee;
    border-left: 4px solid #e53935;
    border-radius: 14px;
    padding: 16px 14px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin-bottom: 15px;
}
.card-title {
    font-size: 13px;
    font-weight: 800;
    color: #111111;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.card-title::before {
    content: '';
    width: 4px;
    height: 14px;
    background: #e53935;
    border-radius: 99px;
    flex-shrink: 0;
}

/* Referral code boxes */
.ref-box {
    background: #f8f9fb;
    border: 1.5px dashed #e53935;
    border-radius: 10px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.ref-lbl {
    font-size: 10px;
    font-weight: 700;
    color: #666666;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 2px;
}
.ref-val {
    font-size: 16px;
    font-weight: 900;
    color: #e53935;
    letter-spacing: 0.5px;
}
.copy-btn {
    background: linear-gradient(135deg, #e53935 0%, #ff5252 50%, #e53935 100%);
    border: none;
    border-radius: 8px;
    color: #ffffff;
    font-size: 12px;
    font-weight: 800;
    padding: 8px 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    flex-shrink: 0;
    transition: all .1s ease;
    box-shadow: 0 3px 0 #b71c1c, 0 4px 8px rgba(229, 57, 53, 0.4);
    text-transform: uppercase;
}
.copy-btn:active {
    transform: translateY(3px);
    box-shadow: 0 0px 0 #b71c1c, 0 2px 4px rgba(229, 57, 53, 0.3);
}

/* WhatsApp Share Button */
.wa-btn {
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 800;
    color: #ffffff;
    text-decoration: none;
    border: none;
    cursor: pointer;
    position: relative;
    transition: all 0.1s ease;
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 50%, #27ae60 100%);
    box-shadow: 0 4px 0 #1e7e43, 0 6px 12px rgba(39, 174, 96, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-top: 10px;
    overflow: hidden;
}
.wa-btn::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 5%;
    width: 90%;
    height: 30%;
    background: linear-gradient(180deg, rgba(255,255,255,0.25), transparent);
    border-radius: 8px 8px 0 0;
    pointer-events: none;
}
.wa-btn:active {
    transform: translateY(4px);
    box-shadow: 0 0px 0 #1e7e43, 0 2px 5px rgba(39, 174, 96, 0.3);
}

/* Toast */
.toast {
    display: none;
    position: fixed;
    top: 70px;
    left: 50%;
    transform: translateX(-50%);
    background: #e53935;
    color: #ffffff;
    padding: 10px 20px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 13px;
    z-index: 9999;
    box-shadow: 0 4px 15px rgba(229, 57, 53, 0.4);
    white-space: nowrap;
}

/* Bottom Menu - 100% exact match user_dashboard.php */
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

/* Support Popup Modal - exact match user_dashboard.php */
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
<body>
<?php if($isDeactive): ?>
<div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;">
  <div style="background:#fff;border-radius:16px;padding:32px 24px;text-align:center;max-width:320px;width:90%;box-shadow:0 4px 24px rgba(0,0,0,.15);">
    <i class="fas fa-lock" style="font-size:40px;color:#8b0000;margin-bottom:12px;"></i>
    <h3 style="color:#000;margin-bottom:8px;">Account Deactivated</h3>
    <p style="color:#888;font-size:13px;margin-bottom:16px;">Please contact support.</p>
    <a href="option.php" style="background:#8b0000;color:#fff;border-radius:10px;padding:10px 24px;text-decoration:none;font-weight:700;">Go Back</a>
  </div>
</div>
<?php else: ?>

<!-- Header matching notice_board.php -->
<header>
    <div class="header-left">
        <a class="back-btn" href="javascript:history.back()" title="Back">
            <i class="fa-solid fa-chevron-left"></i>
        </a>
        <div class="header-title-flash">
            <img src="icons/gold_coin.svg" alt="coin">
            REFER &amp; EARN
            <img src="icons/gold_coin.svg" alt="coin">
        </div>
    </div>
</header>

<div class="container">

  <!-- Filter Tabs -->
  <div class="filter-wrapper">
    <div class="filter-bar">
      <?php
      $filterList = [
        'all'   => 'All Time',
        'today' => 'Today',
        '7'     => '7 Days',
        '15'    => '15 Days',
        '30'    => '30 Days',
        'custom'=> 'Custom',
      ];
      foreach($filterList as $k => $l):
        $isActive = ($k === $range); ?>
        <a href="?range=<?= $k ?>" class="fbtn<?= $isActive ? ' active' : '' ?>" <?= $k === 'custom' ? 'onclick="toggleCustom(event)"' : '' ?>><?= $l ?></a>
      <?php endforeach; ?>
    </div>
    <form class="custom-row <?= $range === 'custom' ? 'show' : '' ?>" id="customRow" method="GET" action="user_referral.php">
      <input type="hidden" name="range" value="custom">
      <input type="date" name="from" value="<?= htmlspecialchars($fromDate) ?>" required>
      <input type="date" name="to" value="<?= htmlspecialchars($toDate) ?>" required>
      <button type="submit">Go</button>
    </form>
  </div>

  <div class="hero-wrapper">
    <!-- Hero -->
    <div class="hero">
      <div class="hero-badge">🔥 Lifetime Earning Program</div>
      <div class="hero-rate"><?= $commDisp ?><sup>%</sup></div>
      <div class="hero-sub">Referral up to <?= $commDisp ?>% Commission — Lifetime</div>
    </div>

    <!-- Stats -->
    <div class="stats">
      <div class="stat">
        <div class="stat-top gr"></div>
        <div class="stat-inner">
          <div class="si gr"><i class="fas fa-users"></i></div>
          <div class="sv"><?= $totalReferred ?></div>
          <div class="sl">Referred</div>
        </div>
      </div>
      <div class="stat">
        <div class="stat-top bl"></div>
        <div class="stat-inner">
          <div class="si bl"><i class="fas fa-rupee-sign"></i></div>
          <div class="sv">₹<?= number_format($totalDeposits,0) ?></div>
          <div class="sl">Deposits</div>
        </div>
      </div>
      <div class="stat">
        <div class="stat-top cy"></div>
        <div class="stat-inner">
          <div class="si cy"><i class="fas fa-coins"></i></div>
          <div class="sv">₹<?= number_format($totalComm,0) ?></div>
          <div class="sl">Earned</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card-wrapper">
    <!-- Referral Code -->
    <div class="card">
      <div class="card-title">Your Referral Code</div>
      <div class="ref-box">
        <div>
          <div class="ref-lbl">Share with friends</div>
          <div class="ref-val"><?= htmlspecialchars($code) ?></div>
        </div>
        <button class="copy-btn" onclick="copyCode()"><i class="fas fa-copy"></i> Copy</button>
      </div>

      <div class="card-title">Your Referral Link</div>
      <div class="ref-box">
        <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; margin-right: 10px;">
          <div class="ref-lbl">Registration Link</div>
          <div class="ref-val" style="font-size: 13px; font-weight: 600; text-transform: lowercase; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">https://subhgaming.online/user_register.php?ref=<?= htmlspecialchars($code) ?></div>
        </div>
        <button class="copy-btn" onclick="copyLink()"><i class="fas fa-copy"></i> Copy</button>
      </div>
      
      <button class="wa-btn" onclick="shareWA()">
        <i class="fab fa-whatsapp"></i> Share on WhatsApp
      </button>
    </div>


  </div>

</div>

<div class="toast" id="toast"></div>

<!-- Bottom Navigation Bar (matching user_dashboard.php) -->
<nav class="bottom-menu">
    <a href="user_result.php" class="menu-item">
        <img src="icons/bid_icon.svg" alt="My Bids" class="nav-icon">
        <span>My Bids</span>
    </a>
    <a href="price_chart.php" class="menu-item">
        <img src="icons/coin_icon.svg" alt="Game Rates" class="nav-icon">
        <span>Game Rates</span>
    </a>
    <a href="user_dashboard.php" class="menu-item home-item">
        <div class="home-circle">
            <img src="icons/home_icon.svg" alt="Home" class="nav-icon">
        </div>
        <span>HOME</span>
    </a>
    <a href="user_profile.php" class="menu-item">
        <img src="icons/profile_icon.svg" alt="Account" class="nav-icon">
        <span>Account</span>
    </a>
    <a href="javascript:void(0)" onclick="openSupportModal()" class="menu-item" id="liveSupportBtn" style="position: relative;">
        <img src="icons/chat_icon.svg" alt="Support" class="nav-icon">
        <div class="support-dot-badge"></div>
        <span>Support</span>
    </a>
</nav>

<script>
const CODE    = <?= json_encode($code) ?>;
const APP_LNK = <?= json_encode($appLink) ?>;
const L1      = <?= json_encode(number_format($level1, 2)) ?>;
const TOTAL   = <?= json_encode(number_format($totalPct, 2)) ?>;

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 2200);
}
function copyCode() {
  navigator.clipboard.writeText(CODE).then(() => showToast('Code copied! ✔')).catch(() => showToast('Code copied! ✔'));
}
function copyLink() {
  const link = `https://subhgaming.online/user_register.php?ref=${CODE}`;
  navigator.clipboard.writeText(link).then(() => showToast('Link copied! ✔')).catch(() => showToast('Link copied! ✔'));
}
function shareWA() {
  const regLink = `https://subhgaming.online/user_register.php?ref=${CODE}`;
  const txt = `🎯 SUBH GAMING – Refer & Earn!\n\nMere code se join karo: *${CODE}*\n*Register Here:* ${regLink}\n\n*App Download:* ${APP_LNK}\n\n💰 *REFERRAL COMMISSION [${TOTAL}%]:*\n👉 Direct Friend - *${L1}%*\n\nLifetime Commission on every deposit!`;
  window.location.href = 'https://wa.me/?text=' + encodeURIComponent(txt);
}

function toggleCustom(e) {
  e.preventDefault();
  document.querySelectorAll('.fbtn').forEach(btn => btn.classList.remove('active'));
  e.target.classList.add('active');
  document.getElementById('customRow').classList.add('show');
}

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
</script>

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
</body>
</html>

