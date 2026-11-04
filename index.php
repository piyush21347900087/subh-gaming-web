<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function index_asset_url(string $path, bool $absolute = false): string
{
    $cleanPath = ltrim($path, '/');
    $filePath = __DIR__ . '/' . $cleanPath;
    clearstatcache(true, $filePath);
    $version = is_file($filePath) ? (filemtime($filePath) . '-' . filesize($filePath)) : (string) time();
    $url = $cleanPath . '?v=' . rawurlencode($version);

    if ($absolute) {
        return 'https://subhgaming.online/' . $url;
    }

    return $url;
}

if (is_file(__DIR__ . '/includes/views_index.php')) {
    try {
        require_once __DIR__ . '/includes/views_index.php';
    } catch (Throwable $e) {
        error_log('index views tracker error: ' . $e->getMessage());
    }
}

/* ---- PHP MySQL error reporting ---- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ===== APK DIRECT DOWNLOAD REQUEST ===== */
if (isset($_GET['apk']) && $_GET['apk'] === '1') {
    try {
        require_once 'includes/db.php';
        $db = db();
        $db->set_charset('utf8mb4');
        $db->query("SET time_zone = '+05:30'");

        $db->query("
            INSERT INTO downloads (id, download_count, created_at)
            VALUES (1, 1, NOW())
            ON DUPLICATE KEY UPDATE 
                download_count = download_count + 1,
                created_at = NOW()
        ");

        $db->close();
    } catch (Throwable $e) {
        // ignore
    }

    $file = __DIR__ . '/app/mahadevking.apk';
    if (!is_file($file)) {
        $file = __DIR__ . '/app/mahadevking.apk';
    }
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

/* ---- Markets & Results ---- */
$markets = [];
$allRates = [];
$whatsappNo = '';

try {
    require_once 'includes/db.php';
    $db = db();
    $db->set_charset('utf8mb4');
    $db->query("SET time_zone = '+05:30'");

    $res = $db->query("SELECT * FROM markets ORDER BY sort_order ASC");
    if ($res) {
        $stmtLatest = $db->prepare(
            "SELECT result_str 
             FROM market_result 
             WHERE market = ? 
             ORDER BY result_date DESC 
             LIMIT 1"
        );

        while ($row = $res->fetch_assoc()) {
            $slug = $row['market'] ?? $row['market_name'] ?? '';
            $label = $row['market_name'] ?? $row['market'] ?? '';
            if ($slug === '' || $label === '') continue;

            $latestResult = '***-**-***';
            if ($stmtLatest) {
                $stmtLatest->bind_param('s', $label);
                $stmtLatest->execute();
                $stmtLatest->bind_result($r);
                if ($stmtLatest->fetch() && $r !== null && $r !== '') {
                    $latestResult = $r;
                }
                $stmtLatest->free_result();
            }

            $markets[] = [
                'slug' => $slug,
                'label' => $label,
                'latest' => $latestResult
            ];
        }
        if ($stmtLatest) { $stmtLatest->close(); }
    }

    /* ---- Game Rates ---- */
    $resRates = $db->query("SELECT * FROM game_rates ORDER BY id ASC");
    if ($resRates) {
        while ($r = $resRates->fetch_assoc()) {
            $allRates[] = $r;
        }
    }

    /* ---- WhatsApp Contact ---- */
    $resWa = $db->query("SELECT whatsapp_no_1 FROM admin_payment_details WHERE id = 1 LIMIT 1");
    if ($resWa && $rowWa = $resWa->fetch_assoc()) {
        $whatsappNo = trim($rowWa['whatsapp_no_1']);
    }

    $db->close();
} catch (Throwable $e) {
    error_log('index data fetch error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <title>SUBH GAMING | Official Platform</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(index_asset_url('images/logo.png'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(index_asset_url('images/logo.png'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; -webkit-tap-highlight-color: transparent; }
        body {
            background-color: #000000;
            background-image: 
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px),
                repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.05) 0, rgba(255, 255, 255, 0.05) 1px, transparent 0, transparent 14px);
            color: #dddddd;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header-bar {
            background: #111111;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid #2a2a2a;
        }

        .header-title {
            color: #ffffff;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .header-title span {
            color: #ff0000;
        }

        .main-container {
            flex: 1;
            padding: 40px 20px 20px 20px;
            max-width: 500px;
            margin: 0 auto;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo-box {
            text-align: center;
            margin: 0 0 20px 0;
        }
        .logo-box img {
            width: 220px;
            height: auto;
            max-width: 100%;
            filter: drop-shadow(0 10px 25px rgba(255, 0, 0, 0.2));
            animation: floatLogo 3s ease-in-out infinite;
        }

        @keyframes floatLogo {
            0% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
            100% { transform: translateY(0); }
        }
        
        .app-title {
            text-align: center;
            font-size: 32px;
            font-weight: 800;
            color: #ffffff;
            margin-top: 10px;
        }
        .app-title span { color: #ff0000; }
        
        .tagline {
            text-align: center;
            color: #888888;
            font-size: 15px;
            margin-top: 5px;
            margin-bottom: 40px;
            font-weight: 500;
        }

        .btn-large {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 18px;
            border-radius: 16px;
            text-decoration: none;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            transition: all 0.2s;
        }
        .btn-large i {
            font-size: 22px;
        }
        .btn-download {
            background: #ff4d4d;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(255, 77, 77, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-download::after {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.4) 50%, rgba(255,255,255,0) 100%);
            transform: skewX(-25deg);
            animation: flashSweep 2.5s infinite ease-in-out;
        }

        @keyframes flashSweep {
            0% { left: -150%; }
            40% { left: 200%; }
            100% { left: 200%; }
        }
        .btn-whatsapp {
            background: #25D366;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(37, 211, 102, 0.25);
        }

                .steps-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #121212;
            border: 1px solid #2a2a2a;
            border-radius: 14px;
            padding: 10px 20px;
            margin-bottom: 25px;
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            color: #dddddd;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .step-item i {
            color: #ff4d4d;
            font-size: 22px;
        }
        .step-arrow {
            color: #333333;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            padding: 24px;
            color: #666666;
            font-size: 13px;
            margin-top: auto;
        }
    </style>
    <!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '1133034892603213');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=1133034892603213&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

    <!-- Download Button -->
    <a href="index.php?apk=1" style="display:none;"
       onclick="fbq('trackCustom', 'DownloadApp');">
       DOWNLOAD NOW
    </a>

    <!-- Header -->
    <header class="header-bar">
        <div class="header-title">MAHADEV <span>KING</span></div>
    </header>

    <div class="main-container">

        <!-- Logo & Title -->
        <div class="logo-box">
            <img src="<?= htmlspecialchars(index_asset_url('images/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Logo" onerror="this.src='icons/icon.png';">
        </div>
        <h1 class="app-title">MAHADEV <span>KING</span></h1>
        <p class="tagline">Secure & Instant Result Platform</p>

        <!-- 3 Steps -->
        <div class="steps-row">
            <div class="step-item"><i class="fa-solid fa-cloud-arrow-down"></i><span>Download</span></div>
            <i class="fa-solid fa-chevron-right step-arrow"></i>
            <div class="step-item"><i class="fa-solid fa-mobile-screen"></i><span>Install</span></div>
            <i class="fa-solid fa-chevron-right step-arrow"></i>
            <div class="step-item"><i class="fa-solid fa-gamepad"></i><span>Play Now</span></div>
        </div>

        <!-- Main Action Buttons -->
        <a href="index.php?apk=1" id="download_app_btn" class="btn-large btn-download" onclick="if(window.fbq) { fbq('track', 'Lead'); fbq('trackCustom', 'DownloadApp'); }">
            <i class="fa-brands fa-android"></i> Download App
        </a>

    </div>

    <!-- Footer -->
    <div class="footer">
        &copy; <?= date('Y') ?> SUBH GAMING. All Rights Reserved.
    </div>

</body>
</html>

