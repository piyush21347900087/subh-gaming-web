<?php
require_once 'includes/lang.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';
require_once 'includes/user_ping.php';
require_once 'includes/live_ping.php';
date_default_timezone_set("Asia/Kolkata");

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

$userName = $_SESSION['user']['name'] ?? '';
if ($userName === '') {
  $userName = fetch_one_val($db, 'SELECT name FROM users WHERE id = ?', 'i', [$uid]) ?: $code;
}

$userCreatedAt = fetch_one_val($db, 'SELECT created_at FROM users WHERE id = ?', 'i', [$uid]) ?: '';
$userSince = $userCreatedAt ? date('d-m-Y', strtotime($userCreatedAt)) : '';
$userMobile = fetch_one_val($db, 'SELECT mobile_no FROM users WHERE id = ?', 'i', [$uid]) ?: '';

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

/* ===== APK DIRECT DOWNLOAD REQUEST ===== */
if (isset($_GET['apk']) && $_GET['apk'] === '1') {
  $now = time();
  $shouldCount = false;
  if (!isset($_SESSION['last_apk_click']) || ($now - $_SESSION['last_apk_click']) > 3) {
    $_SESSION['last_apk_click'] = $now;
    $shouldCount = true;
  }
  if ($shouldCount) {
    try {
      $db->query("INSERT INTO downloads (id, download_count, created_at) VALUES (1, 1, NOW()) ON DUPLICATE KEY UPDATE download_count = download_count + 1, created_at = NOW()");
    } catch (Exception $e) {
    }
  }
  $file = __DIR__ . '/app/mahadevking.apk';
  if (is_file($file)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Disposition: attachment; filename="mahadevking.apk"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    readfile($file);
  } else {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'APK file not found.';
  }
  exit;
}

$dynamicButtons = [];
$resBtn = $db->query("SELECT * FROM how_to_play_buttons ORDER BY id ASC");
if ($resBtn) {
    while ($r = $resBtn->fetch_assoc()) {
        $dynamicButtons[] = $r;
    }
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
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <meta name="theme-color" content="#000000">
    <title><?= __("How to Play") ?> | SUBH GAMING</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --header-bg: #000000;
            --btn-red: #e53935;
            --text-dark: #ffffff;
            --text-gray: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            background-color: #000000 !important;
            background: #000000 !important;
            color: #ffffff;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── Header Standardized matching withdrawal_history.php & user_bid_history.php ── */
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

        /* ── Main Container ── */
        .content {
            max-width: 550px;
            margin: 0 auto;
            padding: 20px 16px 100px;
            min-height: calc(100vh - 65px);
        }

        .play-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #e53935;
            border-left: 4px solid #e53935;
            padding-left: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .btn-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .play-btn {
            background: #ffffff;
            padding: 14px 18px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #eeeeee;
            border-left: 4px solid #e53935;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .play-btn:active {
            transform: scale(0.98);
        }

        .btn-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .btn-info i {
            font-size: 24px;
            color: #ff0000;
        }

        .btn-text h3 {
            font-size: 15px;
            color: #111111;
            font-weight: 700;
        }

        .btn-text p {
            font-size: 12px;
            color: #666666;
            margin-top: 2px;
            font-weight: 500;
        }

        .arrow-icon {
            color: #e53935;
            font-size: 15px;
        }

        /* ── Centered Empty State ── */
        .empty-state-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: calc(70vh - 120px);
            text-align: center;
            padding: 20px;
        }

        .illustration-container { margin-bottom: 20px; }
        .illustration-container img { filter: invert(32%) sepia(87%) saturate(2986%) hue-rotate(346deg) brightness(96%) contrast(92%); }
        .empty-state-text { font-size: 16px; font-weight: 500; color: #94a3b8; }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

    <!-- Header identical to withdrawal_history.php -->
    <header>
        <div class="header-left">
            <a class="back-btn" href="javascript:history.back()" title="Back">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <div class="header-title-flash">
                <img src="icons/gold_coin.svg" alt="coin">
                <?= __("How to Play") ?>
                <img src="icons/gold_coin.svg" alt="coin">
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="content">
        <div class="play-title"><?= __("SUBH GAMING Tutorials") ?></div>

        <?php if (!empty($dynamicButtons)): ?>
            <div class="btn-list">
                <?php foreach ($dynamicButtons as $btn): ?>
                    <a href="<?= htmlspecialchars($btn['link']) ?>" target="_blank" rel="noopener" class="play-btn">
                        <div class="btn-info">
                            <i class="fa-brands fa-youtube"></i>
                            <div class="btn-text">
                                <h3><?= htmlspecialchars($btn['title']) ?></h3>
                                <p><?= __("Watch video tutorial") ?></p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right arrow-icon"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state-wrapper">
                <div class="illustration-container">
                    <img src="icons/loading.svg" alt="Loading" style="width: 50px; height: 50px; margin-bottom: 10px;">
                </div>
                <div class="empty-state-text">No Results Found!</div>
            </div>
        <?php endif; ?>
    </div>

    <script src="assets/js/user_notice_tracker.js?v=4"></script>
</body>
</html>
