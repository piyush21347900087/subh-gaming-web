<?php
require_once 'includes/db.php';
require_once 'includes/lang.php';
// user_dashboard.php - Premium SUBH GAMING Dashboard
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
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
} catch (Throwable $e) {
    echo '<!-- Database error: ' . htmlspecialchars($e->getMessage()) . ' -->';
    echo '<div style="font-family:sans-serif; text-align:center; padding:50px 20px; background:#111; color:#fff;"><h2>Server Maintenance</h2><p>Database is currently updating. Please refresh in a few seconds.</p></div>';
    exit;
}

/* ===================== FETCH APP SETTINGS ===================== */
$whatsapp_no_1 = '';
$whatsapp_no_2 = '';
$telegram_id = '';
$telegram_channel = '';
$show_telegram = 1;
$show_telegram_channel = 1;
$waSupportUrl = '';
$tgSupportUrl = '';
$tgChannelUrl = '';
try {
    $rsSettings = $db->query("SELECT whatsapp_no_1, whatsapp_no_2, show_bank, telegram_id, telegram_channel, show_telegram, show_telegram_channel FROM admin_payment_details WHERE id = 1 LIMIT 1");
    if ($rsSettings && $rowSettings = $rsSettings->fetch_assoc()) {
        $whatsapp_no_1 = $rowSettings['whatsapp_no_1'] ?? '';
        $whatsapp_no_2 = $rowSettings['whatsapp_no_2'] ?? '';
        $telegram_id = trim($rowSettings['telegram_id'] ?? '');
        $telegram_channel = trim($rowSettings['telegram_channel'] ?? '');
        $show_telegram = (int) ($rowSettings['show_telegram'] ?? 1);
        $show_telegram_channel = (int) ($rowSettings['show_telegram_channel'] ?? 1);
        $showBank = (int) ($rowSettings['show_bank'] ?? 1);
        $waAdminDigits = preg_replace('/\D/', '', $whatsapp_no_1 ?: $whatsapp_no_2);
        if (strlen($waAdminDigits) === 10) { $waAdminDigits = '91' . $waAdminDigits; }
        if (!empty($waAdminDigits)) {
            $waSupportUrl = "https://wa.me/" . $waAdminDigits;
        }
        if (!empty($telegram_id) && $show_telegram) {
            $tgSupportUrl = str_starts_with($telegram_id, 'http') ? $telegram_id : ('https://t.me/' . ltrim($telegram_id, '@'));
        }
        if (!empty($telegram_channel) && $show_telegram_channel) {
            $tgChannelUrl = str_starts_with($telegram_channel, 'http') ? $telegram_channel : ('https://t.me/' . ltrim($telegram_channel, '@'));
        }
    }
} catch (Throwable $e) {
}

/* ===================== HELPERS ===================== */
if (!function_exists('fetch_one')) {
    function fetch_one(mysqli $db, string $sql, string $types = '', array $params = []): ?string
    {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return null;
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $stmt->bind_result($val);
        $out = $stmt->fetch() ? $val : null;
        $stmt->close();
        return $out;
    }
}

/* ===================== USER INFO & BALANCE ===================== */
$resUser = $db->query("SELECT user_id, name, mobile_no, created_at FROM users WHERE id = $uid LIMIT 1");
$userRow = ($resUser && $resUser->num_rows > 0) ? $resUser->fetch_assoc() : [];

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

if (!function_exists('dashboard_chat_uid')) {
    function dashboard_chat_uid(string $uid): string
    {
        $uid = strtoupper(trim($uid));
        if ($uid === '') {
            return '';
        }
        if (preg_match('/^\d+$/', $uid)) {
            return 'KR' . str_pad($uid, 2, '0', STR_PAD_LEFT);
        }
        if (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $uid, $m)) {
            return 'KR' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
        }
        return preg_replace('/[^A-Z0-9_-]/', '', $uid);
    }
}

if (!function_exists('dashboard_support_unread_count')) {
    function dashboard_support_unread_count(mysqli $db, string $chatUid): int
    {
        if ($chatUid === '') {
            return 0;
        }

        try {
            $db->query("
                CREATE TABLE IF NOT EXISTS chat_seen_user(
                  user_id VARCHAR(50) PRIMARY KEY,
                  last_seen BIGINT UNSIGNED NOT NULL DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci
            ");

            $seen = 0;
            $s = $db->prepare("SELECT last_seen FROM chat_seen_user WHERE user_id=? LIMIT 1");
            $s->bind_param('s', $chatUid);
            $s->execute();
            $s->bind_result($seenDb);
            if ($s->fetch()) {
                $seen = (int) $seenDb;
            }
            $s->close();

            $c = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE user_id=? AND sender='admin' AND id > ?");
            $c->bind_param('si', $chatUid, $seen);
            $c->execute();
            $c->bind_result($cnt);
            $c->fetch();
            $c->close();

            return (int) $cnt;
        } catch (Throwable $e) {
            return 0;
        }
    }
}

$supportChatUid = dashboard_chat_uid($code);
$supportUnreadCount = dashboard_support_unread_count($db, $supportChatUid);

if (($_GET['action'] ?? '') === 'support_unread') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo $supportUnreadCount;
    exit;
}

if (($_GET['action'] ?? '') === 'check_win_modal') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    
    if (empty($_SESSION['user']['id'])) {
        echo json_encode(['has_win' => false]);
        exit;
    }
    
    $uidStr = (string) $_SESSION['user']['id'];
    $userCode = $code ?? '';
    
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM `wining` LIKE 'notified'");
        if ($checkCol && $checkCol->num_rows === 0) {
            $db->query("ALTER TABLE `wining` ADD COLUMN `notified` TINYINT(1) DEFAULT 0");
        }
    } catch (Throwable $e) {}

    $winRows = [];
    $stmtWin = $db->prepare("SELECT id, market, bet_type, wining_balance, win_time FROM wining WHERE user_id IN (?, ?) AND LOWER(status) = 'win' AND IFNULL(is_reversed, 0) = 0 AND (notified IS NULL OR notified = 0) ORDER BY id DESC LIMIT 10");
    if ($stmtWin) {
        $stmtWin->bind_param('ss', $userCode, $uidStr);
        $stmtWin->execute();
        $resWin = $stmtWin->get_result();
        while ($rw = $resWin->fetch_assoc()) {
            $winRows[] = $rw;
        }
        $stmtWin->close();
    }
    
    if (count($winRows) > 0) {
        $totalWinAmount = 0;
        $winIds = [];
        $latestMarket = $winRows[0]['market'] ?? '';
        foreach ($winRows as $w) {
            $totalWinAmount += (float) $w['wining_balance'];
            $winIds[] = (int) $w['id'];
        }
        
        $todayWinCount = 0;
        $stmtCnt = $db->prepare("SELECT COUNT(*) FROM wining WHERE user_id IN (?, ?) AND LOWER(status) = 'win' AND IFNULL(is_reversed, 0) = 0 AND DATE(win_time) = CURDATE()");
        if ($stmtCnt) {
            $stmtCnt->bind_param('ss', $userCode, $uidStr);
            $stmtCnt->execute();
            $stmtCnt->bind_result($todayWinCount);
            $stmtCnt->fetch();
            $stmtCnt->close();
        }
        
        if (count($winIds) > 0) {
            $idList = implode(',', array_map('intval', $winIds));
            $db->query("UPDATE wining SET notified = 1 WHERE id IN ($idList)");
        }
        
        echo json_encode([
            'has_win' => true,
            'win_amount' => $totalWinAmount,
            'market' => $latestMarket,
            'today_win_count' => max(1, (int) $todayWinCount)
        ]);
        exit;
    }
    
    echo json_encode(['has_win' => false]);
    exit;
}

$totalBalance = get_total_balance($db, $code);
$walletBalance = $totalBalance;
$winBalance = 0;

/* ===================== INITIAL WIN CHECK ===================== */
$initialWinData = null;
try {
    $checkCol = $db->query("SHOW COLUMNS FROM `wining` LIKE 'notified'");
    if ($checkCol && $checkCol->num_rows === 0) {
        $db->query("ALTER TABLE `wining` ADD COLUMN `notified` TINYINT(1) DEFAULT 0");
    }
    
    $uidStr = (string) $uid;
    $winRowsInit = [];
    $stmtWinInit = $db->prepare("SELECT id, market, bet_type, wining_balance, win_time FROM wining WHERE user_id IN (?, ?) AND LOWER(status) = 'win' AND IFNULL(is_reversed, 0) = 0 AND (notified IS NULL OR notified = 0) ORDER BY id DESC LIMIT 10");
    if ($stmtWinInit) {
        $stmtWinInit->bind_param('ss', $code, $uidStr);
        $stmtWinInit->execute();
        $resWinInit = $stmtWinInit->get_result();
        while ($rw = $resWinInit->fetch_assoc()) {
            $winRowsInit[] = $rw;
        }
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
        
        $initialWinData = [
            'win_amount' => $totalWinAmountInit,
            'market' => $latestMarketInit,
            'today_win_count' => max(1, (int) $todayWinCountInit)
        ];
    }
} catch (Throwable $e) {}

/* ===================== MARKET RESULTS ===================== */
$resArr = [];
if ((int)date('G') < 6) {
    $today = date('Y-m-d', strtotime('-1 day'));
} else {
    $today = date('Y-m-d');
}
$lastDeclaredAt = fetch_one($db, "SELECT IFNULL(MAX(declared_at),'') FROM market_result WHERE result_date = ?", 's', [$today]) ?: '';

$stmt = $db->prepare("SELECT market, result_str FROM market_result WHERE result_date = ?");
if ($stmt) {
    $stmt->bind_param('s', $today);
    if ($stmt->execute()) {
        $stmt->bind_result($mkt, $rStr);
        while ($stmt->fetch()) {
            $resArr[strtoupper($mkt)] = $rStr;
        }
    }
    $stmt->close();
}

/* ===================== MARKETS FETCH ===================== */
$placeholder = '*** - ** - ***';
$markets = [];
$hasMarketColorCol = false;
$resMarketColor = $db->query("SHOW COLUMNS FROM `markets` LIKE 'market_color'");
if ($resMarketColor && $resMarketColor->num_rows > 0) {
    $hasMarketColorCol = true;
}

$q = "SELECT market_name, open_time, close_time, custom_close_text, today_closed, saturday_closed, sunday_closed, sort_order" . ($hasMarketColorCol ? ", market_color" : "") . " FROM markets WHERE is_published = 1 ORDER BY open_time ASC, close_time ASC, sort_order ASC";
$res = $db->query($q);
while ($res && $row = $res->fetch_assoc()) {
    $name = $row['market_name'];
    $nums = $resArr[strtoupper(trim($name))] ?? $placeholder;
    $open = $row['open_time'] ? date('H:i', strtotime($row['open_time'])) : '00:00';
    $close = $row['close_time'] ? date('H:i', strtotime($row['close_time'])) : '00:00';
    $markets[] = [$name, $nums, $open, $close, $row['custom_close_text'], (int) $row['today_closed'], (int) $row['saturday_closed'], (int) $row['sunday_closed'], $row['market_color'] ?? '#000000'];
}



/* ===================== MARQUEE ===================== */
$marqueeText = 'Welcome to SUBH GAMING. Premium Betting Experience.';
$qB = $db->query("SELECT banner FROM banner_text ORDER BY id DESC LIMIT 1");
if ($qB && $rB = $qB->fetch_assoc()) {
    $marqueeText = $rB['banner'] ?: $marqueeText;
}

// Fetch game rates from DB
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
    <title>SUBH GAMING | Dashboard</title>
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
            background: radial-gradient(circle, #ffffff 20%, #ffebee 60%, #ffcdd2 100%);
            border: 3px solid #e53935;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 3px 8px rgba(255, 255, 255, 1), 0 10px 28px rgba(139, 0, 0, 0.35);
            position: relative;
            z-index: 2;
            animation: pinkTrophyGlow 2s infinite ease-in-out;
        }

        @keyframes pinkTrophyGlow {
            0%, 100% { transform: scale(1); box-shadow: inset 0 3px 8px rgba(255, 255, 255, 1), 0 10px 28px rgba(139, 0, 0, 0.35); }
            50% { transform: scale(1.06); box-shadow: inset 0 3px 8px rgba(255, 255, 255, 1), 0 16px 36px rgba(139, 0, 0, 0.5); }
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
            background: linear-gradient(135deg, #e53935 0%, #c62828 50%, #8b0000 100%);
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
            color: #8b0000;
            font-family: 'Poppins', sans-serif;
            position: relative;
            z-index: 2;
            opacity: 0.9;
        }

        .pink-win-amount-badge {
            background: linear-gradient(135deg, #e53935 0%, #c62828 50%, #8b0000 100%);
            color: #ffffff;
            padding: 14px 20px;
            border-radius: 18px;
            font-size: 24px;
            font-weight: 900;
            margin: 14px 0 14px;
            box-shadow: 0 10px 25px rgba(139, 0, 0, 0.45), inset 0 2px 4px rgba(255, 255, 255, 0.4);
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
            background: rgba(139, 0, 0, 0.08);
            border: 1px dashed rgba(139, 0, 0, 0.35);
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
            color: #8b0000;
            font-family: 'Poppins', sans-serif;
        }

        .pink-win-action-btn {
            width: 100%;
            border: none;
            background: linear-gradient(135deg, #e53935 0%, #c62828 50%, #8b0000 100%);
            color: #ffffff;
            padding: 15px 22px;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(139, 0, 0, 0.45), inset 0 2px 2px rgba(255, 255, 255, 0.3);
            transition: all 0.25s ease;
            font-family: 'Poppins', sans-serif;
            position: relative;
            z-index: 2;
            letter-spacing: 0.3px;
        }

        .pink-win-action-btn:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 12px 30px rgba(139, 0, 0, 0.55);
        }

        .pink-win-action-btn:active {
            transform: scale(0.97);
        }

        :root {
            --primary-bg: #000000;
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
        }

        html {
            background-color: #000000 !important;
            background: #000000 !important;
        }

        body {
            background-color: #000000 !important;
            background: #000000 !important;
            color: var(--text-main);
            overflow-x: clip;
        }

        .fixed-top-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1000;
            background: #000000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        header {
            background-color: #000000 !important;
            background-image: none !important;
            /* Push content below Android status bar but keep pattern behind it */
            padding-top: calc(env(safe-area-inset-top, 0px) + 10px);
            padding-bottom: 10px;
            padding-left: 14px;
            padding-right: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            0% {
                transform: translateX(100vw);
            }

            100% {
                transform: translateX(-100%);
            }
        }

        /* Sections */
        .content { min-height: 100vh; background-color: #000000; box-shadow: 0 0 30px rgba(0,0,0,0.5);
            max-width: 480px;
            margin: 0 auto;
            padding: calc(env(safe-area-inset-top, 0px) + 168px) 15px 90px 15px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            border-left: 4px solid #000000;
            padding-left: 10px;
        }

        /* ── Action Buttons ── */
        .action-btns-row { display:flex; gap:10px; margin-bottom:6px; }
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
            background: #000000 !important;
            padding: 6px 14px 6px 14px;
            max-width: 480px;
            margin: 0 auto;
            border-bottom: 1px solid #1a1a1a;
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
            box-shadow: 0 4px 15px rgba(0,0,0,0.35);
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

        .btn-play-pill span { font-size: 13px; font-weight: 800; }

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
            font-weight: 800;
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
            font-weight: 800;
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
            font-weight: 800;
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
            font-weight: 800;
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
            background-color: #000000;
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

        .support-btn-telegram {
            background: #0088cc;
        }

        .support-btn-channel {
            background: linear-gradient(135deg, #0284c7, #0369a1);
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
    <!-- Fixed Top Header & Action Buttons (FROZEN ON SCROLL) -->
    <div class="fixed-top-wrapper">
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

        <!-- Fixed Action Buttons Header Wrapper -->
        <div class="sticky-action-bar">
            <!-- Action Buttons Row 1 -->
            <div class="action-btns-row" style="margin-bottom: 8px;">
                <a href="deposit.php" class="action-btn-custom btn-add-money-green">
                    <div class="icon-circle-custom"><img src="icons/deposit_icon.svg" alt="Add" style="width: 26px; height: 26px;"></div> <span class="btn-text-center"><?= __("ADD MONEY") ?></span>
                </a>
                <a href="withdrawal.php" class="action-btn-custom btn-withdraw-red">
                    <div class="icon-circle-custom"><img src="icons/withdrawal_icon.svg" alt="Withdraw" style="width: 26px; height: 26px;"></div> <span class="btn-text-center"><?= __("WITHDRAW") ?></span>
                </a>
            </div>

            <!-- Action Buttons Row 2 -->
            <div class="action-btns-row">
                <a href="starline4.php" class="btn-play-pill-custom">
                    <div class="icon-square-custom"><i class="fa-solid fa-play"></i></div> <span class="btn-text-center">STARLINE</span>
                </a>
                <a href="gali_desawar.php" class="btn-play-pill-custom">
                    <div class="icon-square-custom"><i class="fa-solid fa-play"></i></div> <span class="btn-text-center">DESAWAR</span>
                </a>
            </div>
        </div>
    </div>

    <div class="content">

        <div class="market-list" id="marketBoard">
            <?php
            foreach ($markets as [$name, $nums, $open, $close, $custom, $tC, $sC, $suC, $marketColor]):

                $oH = (int) explode(':', $open)[0];
                $oM = (int) explode(':', $open)[1];
                $cH = (int) explode(':', $close)[0];
                $cM = (int) explode(':', $close)[1];
                $oMin = $oH * 60 + $oM;
                $cMin = $cH * 60 + $cM;
                $isHoliday = ($tC === 1 || ($todayDow === 6 && $sC === 1) || ($todayDow === 7 && $suC === 1));
                $holidayText = '';
                if ($isHoliday) {
                    if ($sC === 1 && $suC === 1) {
                        $holidayText = 'SATURDAY AND SUNDAY HOLIDAY';
                    } elseif ($todayDow === 6 && $sC === 1) {
                        $holidayText = 'SATURDAY HOLIDAY';
                    } elseif ($todayDow === 7 && $suC === 1) {
                        $holidayText = 'SUNDAY HOLIDAY';
                    } else {
                        $holidayText = 'HOLIDAY';
                    }
                }
                if ($isHoliday)
                    $isOpen = false;
                else
                    $isOpen = ($oMin < $cMin) ? ($nowMinServer < $cMin) : ($nowMinServer >= $oMin || $nowMinServer < $cMin);

                $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_');
                ?>
                <div class="market-card-custom <?= !$isOpen || $isDeactive ? 'closed-market' : 'open-market' ?> <?= $isHoliday ? 'holiday-market' : '' ?>" 
                    data-open="<?= $oMin ?>"
                    data-close="<?= $cMin ?>" data-holiday="<?= $isHoliday ? 1 : 0 ?>"
                    onclick="handleMarketClick(this, '<?= $slug ?>.php', <?= $isOpen ? 'true' : 'false' ?>)">
                    
                    <!-- Main Info Area (Left + Middle Sub-Columns) -->
                    <div class="market-card-main-info">
                        <!-- Sub-Column 1 (LEFT): Market Name, (i) + Result, Status -->
                        <div class="market-col-left">
                            <h3 class="market-card-title"><?= htmlspecialchars($name) ?></h3>

                            <?php
                            $dispNums = strip_tags($nums);
                            $uName = strtoupper(trim($name));
                            $uNums = strtoupper(trim($dispNums));
                            if (strpos($uNums, $uName) === 0) {
                                $dispNums = trim(substr($uNums, strlen($uName)));
                                $dispNums = ltrim($dispNums, ': -');
                            }
                            ?>
                            <div class="market-number-row">
                                <div class="market-number-wrapper">
                                    <a href="javascript:void(0);"
                                       onclick="event.stopPropagation(); openMarketTimeModal('<?= htmlspecialchars($name, ENT_QUOTES) ?>', '<?= date('l') ?>', '<?= $isOpen && !$isDeactive ? __('Running Now') : ($isHoliday ? htmlspecialchars(__($holidayText), ENT_QUOTES) : __('Betting is closed for today')) ?>', <?= $isOpen && !$isDeactive ? 'true' : 'false' ?>, '<?= date('h:i A', strtotime($open . ' +10 minutes')) ?>', '<?= date('h:i A', strtotime($open)) ?>', '<?= date('h:i A', strtotime($close . ' +10 minutes')) ?>', '<?= date('h:i A', strtotime($close)) ?>');"
                                       class="info-btn-icon" title="View Timetable">
                                       <span class="info-circle-outline">i</span>
                                    </a>
                                    <span class="market-card-number"><?= htmlspecialchars($dispNums) ?></span>
                                </div>
                            </div>

                            <div class="market-card-status">
                                <?php if ($isOpen && !$isDeactive): ?>
                                    <span class="status-text-running"><?= __("Running Now") ?></span>
                                <?php elseif ($isHoliday): ?>
                                    <span class="status-text-closed"><?= htmlspecialchars(__($holidayText)) ?></span>
                                <?php else: ?>
                                    <span class="status-text-closed"><?= __("Betting Closed for Today") ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Sub-Column 2 (RIGHT): Clock Icon & Open/Close Bids Times -->
                        <div class="market-col-right">
                            <i class="fi fi-rr-alarm-clock clock-icon <?= $isOpen && !$isDeactive ? 'clock-open' : 'clock-closed' ?>"></i>
                            <div class="market-card-times">
                                <div>Open Bids -</div>
                                <div class="time-val"><?= date('h:i A', strtotime($open)) ?></div>
                                <div style="margin-top: 2px;">Close Bids -</div>
                                <div class="time-val"><?= date('h:i A', strtotime($close)) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Section (25% Play Area) -->
                    <div class="market-card-right <?= $isOpen && !$isDeactive ? 'play-bg-open' : 'play-bg-closed' ?>">
                        <i class="fa-solid fa-play play-triangle"></i>
                        <span class="play-label">Play</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

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

            <?php if (!empty($waSupportUrl)): ?>
            <a href="<?= htmlspecialchars($waSupportUrl) ?>" target="_blank" class="side-menu-item" style="color:#25d366;">
                <div class="icon-box"><i class="fa-brands fa-whatsapp" style="color:#25d366; font-size:18px;"></i></div>
                <span class="label">WhatsApp Support</span>
            </a>
            <?php endif; ?>
            <?php if (!empty($tgSupportUrl)): ?>
            <a href="<?= htmlspecialchars($tgSupportUrl) ?>" target="_blank" class="side-menu-item" style="color:#0088cc;">
                <div class="icon-box"><i class="fa-brands fa-telegram" style="color:#0088cc; font-size:18px;"></i></div>
                <span class="label">Telegram Support</span>
            </a>
            <?php endif; ?>
            <?php if (!empty($tgChannelUrl)): ?>
            <a href="<?= htmlspecialchars($tgChannelUrl) ?>" target="_blank" class="side-menu-item" style="color:#38bdf8;">
                <div class="icon-box"><i class="fa-solid fa-bullhorn" style="color:#38bdf8; font-size:16px;"></i></div>
                <span class="label">Telegram Channel</span>
            </a>
            <?php endif; ?>

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
        <a href="price_chart.php" class="menu-item">
            <img src="icons/coin_icon.svg" alt="Game Rates" class="nav-icon">
            <span><?= __("Game Rates") ?></span>
        </a>
        <a href="user_dashboard.php" class="menu-item home-item active">
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
                <?php if (!empty($waSupportUrl)): ?>
                <a href="<?= htmlspecialchars($waSupportUrl) ?>" target="_blank" rel="noopener" class="support-btn support-btn-whatsapp">
                    <div class="support-btn-icon">
                        <i class="fa-brands fa-whatsapp"></i>
                    </div>
                    <span>WhatsApp</span>
                </a>
                <?php endif; ?>
                <?php if (!empty($tgSupportUrl)): ?>
                <a href="<?= htmlspecialchars($tgSupportUrl) ?>" target="_blank" rel="noopener" class="support-btn support-btn-telegram">
                    <div class="support-btn-icon">
                        <i class="fa-brands fa-telegram"></i>
                    </div>
                    <span>Telegram</span>
                </a>
                <?php endif; ?>
                <?php if (!empty($tgChannelUrl)): ?>
                <a href="<?= htmlspecialchars($tgChannelUrl) ?>" target="_blank" rel="noopener" class="support-btn support-btn-channel">
                    <div class="support-btn-icon">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <span>TG Channel</span>
                </a>
                <?php endif; ?>
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

            /* SHARE */
            var shareBtn = document.getElementById('shareBtn');
            if (shareBtn) shareBtn.addEventListener('click', function() {
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
                ?>
                var fullText = <?= json_encode($shareText, JSON_UNESCAPED_UNICODE) ?>;
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

            // Check DOM status directly so tick() real-time changes are respected
            var isClosed = el.classList.contains('closed-market');

            if (!isClosed) {
                window.location.href = link;
            } else {
                if (navigator.vibrate) { navigator.vibrate(100); } // Vibrate on click
                if (window.AndroidNotify && typeof window.AndroidNotify.vibrate === 'function') {
                    window.AndroidNotify.vibrate(100); // Trigger native vibration
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
        upBtn.innerHTML = '<i class="fa-solid fa-angles-up"></i>';
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


















