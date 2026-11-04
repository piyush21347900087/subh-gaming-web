<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
error_reporting(0);
ini_set('display_errors', '0');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/includes/config.php';

function index_asset_url(string $path): string
{
    $cleanPath = ltrim($path, '/');
    $filePath = __DIR__ . '/' . $cleanPath;
    $v = is_file($filePath) ? (filemtime($filePath) . '-' . filesize($filePath)) : (string) time();
    return $cleanPath . '?v=' . rawurlencode($v);
}

/* ===== APK DIRECT DOWNLOAD REQUEST ===== */
if (isset($_GET['apk']) && $_GET['apk'] === '1') {
    try {
        require_once __DIR__ . '/includes/db.php';
        $db = db();
        $db->query("
            INSERT INTO downloads (id, download_count, created_at)
            VALUES (1, 1, NOW())
            ON DUPLICATE KEY UPDATE 
                download_count = download_count + 1,
                created_at = NOW()
        ");
        $db->close();
    } catch (Throwable $e) {}

    $files = [
        __DIR__ . '/app/subhgaming.apk',
        __DIR__ . '/app/mahadevking.apk',
        __DIR__ . '/app/app.apk'
    ];
    $apkPath = '';
    foreach ($files as $f) {
        if (is_file($f)) { $apkPath = $f; break; }
    }

    if ($apkPath !== '') {
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="SavariyaSeth.apk"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($apkPath));
        readfile($apkPath);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Savariya Seth APK is being updated. Please contact support via WhatsApp.';
    }
    exit;
}

/* ---- Fetch Data from Database ---- */
$markets = [];
$whatsappNo = defined('WHATSAPP_NUMBER') && WHATSAPP_NUMBER !== '' ? WHATSAPP_NUMBER : '919876543210';
$callingNo  = defined('SUPPORT_PHONE') && SUPPORT_PHONE !== '' ? SUPPORT_PHONE : '919876543210';
$telegramLink = 'https://t.me/' . ltrim(defined('TELEGRAM_USERNAME') && TELEGRAM_USERNAME !== '' ? TELEGRAM_USERNAME : 'savariyaseth_official', '@');

try {
    require_once __DIR__ . '/includes/db.php';
    $db = db();

    // Fetch all contacts from Admin Panel settings (admin_payment_details)
    $resWa = $db->query("SELECT whatsapp_no_1, whatsapp_no_2, whatsapp_deposit_no, telegram_id, telegram_channel FROM admin_payment_details WHERE id = 1 LIMIT 1");
    if ($resWa && $rowWa = $resWa->fetch_assoc()) {
        $w1   = trim((string)($rowWa['whatsapp_no_1'] ?? ''));
        $w2   = trim((string)($rowWa['whatsapp_no_2'] ?? ''));
        $wDep = trim((string)($rowWa['whatsapp_deposit_no'] ?? ''));
        $tgCh = trim((string)($rowWa['telegram_channel'] ?? ''));
        $tgId = trim((string)($rowWa['telegram_id'] ?? ''));

        // 1. WhatsApp Button (WhatsApp No 1, fallback to 2)
        if ($w1 !== '') {
            $whatsappNo = $w1;
        } elseif ($w2 !== '') {
            $whatsappNo = $w2;
        }

        // 2. Call Now Button (WhatsApp No 2 / Deposit No / WhatsApp No 1)
        if ($w2 !== '') {
            $callingNo = $w2;
        } elseif ($wDep !== '') {
            $callingNo = $wDep;
        } elseif ($w1 !== '') {
            $callingNo = $w1;
        }

        // 3. Telegram Button (Channel link or username)
        if ($tgCh !== '') {
            $telegramLink = (strpos($tgCh, 'http') === 0) ? $tgCh : ('https://t.me/' . ltrim($tgCh, '@'));
        } elseif ($tgId !== '') {
            $telegramLink = (strpos($tgId, 'http') === 0) ? $tgId : ('https://t.me/' . ltrim($tgId, '@'));
        }
    }

    // Fetch active markets with latest result
    $res = $db->query("SELECT market_name, open_time, close_time FROM markets WHERE status = 1 ORDER BY sort_order ASC, id ASC LIMIT 12");
    if ($res) {
        $stmtLatest = $db->prepare("SELECT result_str FROM market_result WHERE market = ? ORDER BY result_date DESC LIMIT 1");
        while ($row = $res->fetch_assoc()) {
            $label = $row['market_name'] ?? '';
            $latestResult = '***-**-***';
            if ($stmtLatest && $label !== '') {
                $stmtLatest->bind_param('s', $label);
                $stmtLatest->execute();
                $stmtLatest->bind_result($r);
                if ($stmtLatest->fetch() && !empty($r)) {
                    $latestResult = $r;
                }
                $stmtLatest->free_result();
            }
            $markets[] = [
                'name'   => $label,
                'open'   => $row['open_time'] ?? '',
                'close'  => $row['close_time'] ?? '',
                'result' => $latestResult
            ];
        }
        if ($stmtLatest) $stmtLatest->close();
    }
    $db->close();
} catch (Throwable $e) {
    // Graceful fallback if database is booting
}

$cleanWa   = preg_replace('/[^0-9]/', '', $whatsappNo);
$cleanCall = preg_replace('/[^0-9+]/', '', $callingNo);
$telegramUser = defined('TELEGRAM_USERNAME') && TELEGRAM_USERNAME !== '' ? TELEGRAM_USERNAME : 'savariyaseth_official';
$cleanTg   = ltrim((string)$telegramUser, '@');
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#070b14">
    <title>सांवलिया सेठ | SAVARIYA SETH - Official Gaming Platform</title>
    
    <link rel="icon" type="image/jpeg" href="<?= htmlspecialchars(index_asset_url('images/savariya_seth.jpg')) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(index_asset_url('images/savariya_seth.jpg')) ?>">
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800;900&family=Poppins:wght@300;400;500;600;700;800&family=Rozha+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --gold-primary: #f59e0b;
            --gold-light: #fde047;
            --gold-dark: #b45309;
            --bg-dark: #070b14;
            --card-bg: rgba(15, 23, 42, 0.75);
            --card-border: rgba(245, 158, 11, 0.25);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-green: #22c55e;
            --accent-blue: #0ea5e9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(circle at 50% 0%, rgba(245, 158, 11, 0.15) 0%, transparent 60%),
                radial-gradient(circle at 100% 50%, rgba(14, 165, 233, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 0% 100%, rgba(124, 58, 237, 0.1) 0%, transparent 50%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            padding-bottom: 75px; /* For mobile floating bar */
        }

        /* Ambient Glow */
        .ambient-glow {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 350px;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.18) 0%, rgba(0,0,0,0) 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* Top Header */
        .top-navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(7, 11, 20, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(245, 158, 11, 0.2);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-container {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 2px solid var(--gold-light);
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.6);
            object-fit: cover;
        }

        .brand-text h1 {
            font-family: 'Cinzel', serif;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: 1px;
            background: linear-gradient(135deg, #fff 20%, var(--gold-light) 60%, var(--gold-primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.1;
        }

        .brand-text span {
            font-size: 10px;
            color: var(--gold-light);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .admin-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 6px 14px;
            border-radius: 20px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .admin-link:hover {
            color: #fff;
            border-color: var(--gold-primary);
            background: rgba(245, 158, 11, 0.1);
        }

        /* Container */
        .wrapper {
            max-width: 650px;
            margin: 0 auto;
            width: 100%;
            padding: 24px 18px;
            position: relative;
            z-index: 1;
        }

        /* Divine Hero Section */
        .hero-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 30px 20px;
            text-align: center;
            box-shadow: 0 15px 40px rgba(0,0,0,0.6), 0 0 30px rgba(245, 158, 11, 0.12);
            position: relative;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .hero-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--gold-light), var(--gold-primary), transparent);
        }

        /* Golden Aura Ring around God Photo */
        .divine-photo-wrapper {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 16px auto;
        }

        .divine-aura {
            position: absolute;
            top: -6px;
            left: -6px;
            right: -6px;
            bottom: -6px;
            border-radius: 50%;
            background: conic-gradient(from 0deg, var(--gold-light), #ef4444, #8b5cf6, var(--gold-light));
            animation: rotateAura 6s linear infinite;
            filter: blur(4px);
            opacity: 0.85;
        }

        @keyframes rotateAura {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .divine-photo {
            position: relative;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 8px 25px rgba(0,0,0,0.7);
            background: #000;
        }

        .devotion-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.25), rgba(180, 83, 9, 0.35));
            border: 1px solid rgba(253, 224, 71, 0.4);
            color: var(--gold-light);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 12px;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .hero-title {
            font-family: 'Cinzel', serif;
            font-size: 30px;
            font-weight: 900;
            letter-spacing: 1.5px;
            line-height: 1.2;
            margin-bottom: 6px;
            background: linear-gradient(135deg, #ffffff 10%, #fde047 60%, #f59e0b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 24px;
        }

        /* Main Download Button */
        .btn-download-main {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            width: 100%;
            padding: 18px 24px;
            border-radius: 18px;
            background: linear-gradient(135deg, #e11d48, #f43f5e 50%, #fb7185);
            color: #ffffff;
            text-decoration: none;
            font-size: 19px;
            font-weight: 800;
            box-shadow: 0 10px 30px rgba(225, 29, 72, 0.5), 0 0 20px rgba(244, 63, 94, 0.3);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 18px;
        }

        .btn-download-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 35px rgba(225, 29, 72, 0.65);
        }

        .btn-download-main::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.45), transparent);
            transform: skewX(-25deg);
            animation: shimmerEffect 2.2s infinite;
        }

        @keyframes shimmerEffect {
            0% { left: -100%; }
            50% { left: 200%; }
            100% { left: 200%; }
        }

        .btn-download-main i {
            font-size: 26px;
            animation: bounceIcon 1.8s infinite;
        }

        @keyframes bounceIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        .download-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            font-size: 12px;
            color: var(--text-muted);
            margin-top: -6px;
            margin-bottom: 8px;
        }

        .download-info span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* 3 Contact Action Cards (WhatsApp, Telegram, Call Now) */
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 24px;
        }

        .contact-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px 8px;
            border-radius: 16px;
            text-decoration: none;
            color: #fff;
            font-weight: 700;
            font-size: 13px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
            transition: transform 0.2s, filter 0.2s;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .contact-btn:hover {
            transform: translateY(-3px);
            filter: brightness(1.1);
        }

        .contact-btn i {
            font-size: 28px;
        }

        .btn-whatsapp {
            background: linear-gradient(135deg, #16a34a, #22c55e);
            box-shadow: 0 8px 22px rgba(34, 197, 94, 0.35);
        }

        .btn-telegram {
            background: linear-gradient(135deg, #0284c7, #0ea5e9);
            box-shadow: 0 8px 22px rgba(14, 165, 233, 0.35);
        }

        .btn-call {
            background: linear-gradient(135deg, #d97706, #f59e0b);
            box-shadow: 0 8px 22px rgba(245, 158, 11, 0.35);
        }

        /* Trust Badges Bar */
        .trust-banner {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 12px 8px;
            margin-bottom: 24px;
            text-align: center;
        }

        .trust-item i {
            font-size: 20px;
            color: var(--gold-light);
            margin-bottom: 4px;
        }

        .trust-item h4 {
            font-size: 11px;
            color: #ffffff;
            font-weight: 700;
            line-height: 1.2;
        }

        .trust-item p {
            font-size: 9px;
            color: var(--text-muted);
        }

        /* Section Headings */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            padding: 0 4px;
        }

        .section-title {
            font-size: 17px;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            color: var(--gold-primary);
        }

        .live-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #f87171;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
        }

        .live-dot {
            width: 7px;
            height: 7px;
            background: #ef4444;
            border-radius: 50%;
            animation: pulseDot 1.2s infinite;
        }

        @keyframes pulseDot {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.3); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* Market Result Cards Grid */
        .markets-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        @media (max-width: 420px) {
            .markets-grid {
                grid-template-columns: 1fr;
            }
        }

        .market-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 14px 16px;
            position: relative;
            transition: all 0.3s;
        }

        .market-card:hover {
            border-color: var(--gold-primary);
            transform: translateY(-2px);
        }

        .market-name {
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .market-result {
            font-size: 19px;
            font-weight: 800;
            color: var(--gold-light);
            letter-spacing: 1px;
            font-family: monospace;
            margin-bottom: 6px;
        }

        .market-time {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            color: var(--text-muted);
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding-top: 6px;
        }

        /* Game Rates Section */
        .rates-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .rates-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .rate-box {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .rate-type {
            font-size: 13px;
            font-weight: 600;
            color: #e2e8f0;
        }

        .rate-value {
            font-size: 14px;
            font-weight: 800;
            color: var(--gold-light);
        }

        /* 3 Steps Guide */
        .steps-card {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 18px 12px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-around;
            align-items: center;
        }

        .step-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 6px;
        }

        .step-num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold-light), var(--gold-primary));
            color: #000;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .step-label {
            font-size: 12px;
            font-weight: 600;
            color: #ffffff;
        }

        .step-divider {
            color: rgba(255, 255, 255, 0.2);
            font-size: 16px;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 24px 20px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }

        .footer strong {
            color: var(--gold-light);
        }

        /* Floating Bottom Bar (Mobile First) */
        .bottom-float-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(11, 16, 28, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-top: 1px solid rgba(245, 158, 11, 0.3);
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1.5fr;
            padding: 8px 12px;
            gap: 8px;
            z-index: 1000;
            box-shadow: 0 -10px 25px rgba(0,0,0,0.7);
        }

        .float-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            text-decoration: none;
            color: #cbd5e1;
            font-size: 10px;
            font-weight: 600;
            padding: 6px 4px;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .float-btn i {
            font-size: 18px;
        }

        .float-btn.wa {
            color: #22c55e;
        }

        .float-btn.tg {
            color: #38bdf8;
        }

        .float-btn.call {
            color: #f59e0b;
        }

        .float-btn.dl {
            background: linear-gradient(135deg, #e11d48, #f43f5e);
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            flex-direction: row;
            gap: 6px;
            box-shadow: 0 4px 15px rgba(225, 29, 72, 0.5);
        }
    </style>
</head>
<body>

    <div class="ambient-glow"></div>

    <!-- Top Navigation Bar -->
    <header class="top-navbar">
        <a href="index.php" class="brand-container">
            <img src="<?= htmlspecialchars(index_asset_url('images/savariya_seth.jpg')) ?>" alt="Savariya Seth" class="brand-avatar" onerror="this.src='icons/icon.png'">
            <div class="brand-text">
                <h1>SAVARIYA SETH</h1>
                <span>OFFICIAL PLATFORM</span>
            </div>
        </a>
        <a href="admin_login.php" class="admin-link">
            <i class="fa-solid fa-lock"></i> Admin
        </a>
    </header>

    <div class="wrapper">

        <!-- Divine Hero Card -->
        <section class="hero-card">
            <!-- Rotating Aura with Deity Photo -->
            <div class="divine-photo-wrapper">
                <div class="divine-aura"></div>
                <img src="<?= htmlspecialchars(index_asset_url('images/savariya_seth.jpg')) ?>" alt="श्री साँवलिया सेठ" class="divine-photo" onerror="this.src='icons/icon.png'">
            </div>

            <div class="devotion-badge">
                <i class="fa-solid fa-om"></i> जय श्री साँवलिया सेठ जी <i class="fa-solid fa-om"></i>
            </div>

            <h2 class="hero-title">SAVARIYA SETH</h2>
            <p class="hero-subtitle">India's Most Trusted & Fastest Matka Result Gaming Platform</p>

            <!-- Main Big Download Button -->
            <a href="index.php?apk=1" class="btn-download-main">
                <i class="fa-brands fa-android"></i>
                <span>DOWNLOAD ANDROID APP</span>
            </a>

            <div class="download-info">
                <span><i class="fa-solid fa-shield-halved" style="color:#22c55e;"></i> 100% Virus-Free</span>
                <span>•</span>
                <span><i class="fa-solid fa-bolt" style="color:#f59e0b;"></i> Fast & Direct</span>
                <span>•</span>
                <span>v2.5 APK</span>
            </div>
        </section>

        <!-- 3 Instant Action Contact Buttons (WhatsApp, Telegram, Call Now) -->
        <section class="contact-grid">
            <!-- WhatsApp -->
            <a href="https://wa.me/<?= htmlspecialchars($cleanWa) ?>?text=<?= urlencode('Hello Savariya Seth Admin! I want to play & register.') ?>" target="_blank" class="contact-btn btn-whatsapp">
                <i class="fa-brands fa-whatsapp"></i>
                <span>WhatsApp</span>
            </a>

            <!-- Telegram -->
            <a href="<?= htmlspecialchars($telegramLink) ?>" target="_blank" class="contact-btn btn-telegram">
                <i class="fa-brands fa-telegram"></i>
                <span>Telegram</span>
            </a>

            <!-- Call Now -->
            <a href="tel:<?= htmlspecialchars($cleanCall) ?>" class="contact-btn btn-call">
                <i class="fa-solid fa-phone-volume"></i>
                <span>Call Now</span>
            </a>
        </section>

        <!-- Trust Badges Bar -->
        <section class="trust-banner">
            <div class="trust-item">
                <i class="fa-solid fa-bolt-lightning"></i>
                <h4>5 Min Payout</h4>
                <p>Instant UPI</p>
            </div>
            <div class="trust-item">
                <i class="fa-solid fa-trophy"></i>
                <h4>Highest Rate</h4>
                <p>10 Ka 1000</p>
            </div>
            <div class="trust-item">
                <i class="fa-solid fa-lock"></i>
                <h4>100% Safe</h4>
                <p>Secure Play</p>
            </div>
            <div class="trust-item">
                <i class="fa-solid fa-headset"></i>
                <h4>24/7 Help</h4>
                <p>Live Support</p>
            </div>
        </section>

        <!-- How It Works (3 Steps) -->
        <section class="steps-card">
            <div class="step-col">
                <div class="step-num">1</div>
                <div class="step-label">App Download</div>
            </div>
            <i class="fa-solid fa-angle-right step-divider"></i>
            <div class="step-col">
                <div class="step-num">2</div>
                <div class="step-label">Free Register</div>
            </div>
            <i class="fa-solid fa-angle-right step-divider"></i>
            <div class="step-col">
                <div class="step-num">3</div>
                <div class="step-label">Play & Win</div>
            </div>
        </section>

        <!-- Live Markets & Results -->
        <div class="section-header">
            <div class="section-title">
                <i class="fa-solid fa-chart-line"></i> Today Live Results
            </div>
            <div class="live-tag">
                <span class="live-dot"></span> LIVE
            </div>
        </div>

        <section class="markets-grid">
            <?php if (!empty($markets)): ?>
                <?php foreach ($markets as $m): ?>
                    <div class="market-card">
                        <div class="market-name"><?= htmlspecialchars($m['name']) ?></div>
                        <div class="market-result"><?= htmlspecialchars($m['result']) ?></div>
                        <div class="market-time">
                            <span><i class="fa-regular fa-clock"></i> Open: <?= htmlspecialchars($m['open']) ?></span>
                            <span>Close: <?= htmlspecialchars($m['close']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Default Popular Market Highlights -->
                <div class="market-card">
                    <div class="market-name">KALYAN MORNING</div>
                    <div class="market-result">120-34-456</div>
                    <div class="market-time"><span>Open: 11:00 AM</span><span>Close: 12:02 PM</span></div>
                </div>
                <div class="market-card">
                    <div class="market-name">MILAN DAY</div>
                    <div class="market-result">350-89-469</div>
                    <div class="market-time"><span>Open: 03:00 PM</span><span>Close: 05:00 PM</span></div>
                </div>
                <div class="market-card">
                    <div class="market-name">KALYAN</div>
                    <div class="market-result">248-40-280</div>
                    <div class="market-time"><span>Open: 04:30 PM</span><span>Close: 06:30 PM</span></div>
                </div>
                <div class="market-card">
                    <div class="market-name">MAIN BAZAR</div>
                    <div class="market-result">478-95-159</div>
                    <div class="market-time"><span>Open: 09:35 PM</span><span>Close: 12:05 AM</span></div>
                </div>
            <?php endif; ?>
        </section>

        <!-- Best Market Rates Table -->
        <div class="section-header">
            <div class="section-title">
                <i class="fa-solid fa-coins"></i> Market Payout Rates
            </div>
        </div>

        <section class="rates-card">
            <div class="rates-grid">
                <div class="rate-box">
                    <span class="rate-type">Single Digit</span>
                    <span class="rate-value">10 Ka 100</span>
                </div>
                <div class="rate-box">
                    <span class="rate-type">Jodi Digit</span>
                    <span class="rate-value">10 Ka 1000</span>
                </div>
                <div class="rate-box">
                    <span class="rate-type">Single Pana</span>
                    <span class="rate-value">10 Ka 1500</span>
                </div>
                <div class="rate-box">
                    <span class="rate-type">Double Pana</span>
                    <span class="rate-value">10 Ka 3000</span>
                </div>
                <div class="rate-box">
                    <span class="rate-type">Triple Pana</span>
                    <span class="rate-value">10 Ka 7000</span>
                </div>
                <div class="rate-box">
                    <span class="rate-type">Half Sangam</span>
                    <span class="rate-value">10 Ka 10000</span>
                </div>
            </div>
        </section>

        <!-- Final CTA Box -->
        <section class="hero-card" style="margin-top: 10px;">
            <h3 style="color:#ffffff;font-size:20px;font-weight:800;margin-bottom:8px;">Ready To Play & Win?</h3>
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:18px;">Join thousands of satisfied players on India's No.1 platform.</p>
            <a href="index.php?apk=1" class="btn-download-main" style="margin-bottom:0;">
                <i class="fa-solid fa-download"></i>
                <span>GET SAVARIYA SETH APP</span>
            </a>
        </section>

    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>🙏 <strong>सांवलिया सेठ - Savariya Seth Gaming</strong> 🙏</p>
        <p>100% Genuine, Trustworthy & Transparent Platform</p>
        <p style="margin-top:8px;">&copy; <?= date('Y') ?> Savariya Seth. All Rights Reserved.</p>
    </footer>

    <!-- Fixed Floating Quick Action Bar for Mobile -->
    <nav class="bottom-float-bar">
        <a href="https://wa.me/<?= htmlspecialchars($cleanWa) ?>" target="_blank" class="float-btn wa">
            <i class="fa-brands fa-whatsapp"></i>
            <span>WhatsApp</span>
        </a>
        <a href="<?= htmlspecialchars($telegramLink) ?>" target="_blank" class="float-btn tg">
            <i class="fa-brands fa-telegram"></i>
            <span>Telegram</span>
        </a>
        <a href="tel:<?= htmlspecialchars($cleanCall) ?>" class="float-btn call">
            <i class="fa-solid fa-phone"></i>
            <span>Call</span>
        </a>
        <a href="index.php?apk=1" class="float-btn dl">
            <i class="fa-solid fa-download"></i>
            <span>App Install</span>
        </a>
    </nav>

</body>
</html>
