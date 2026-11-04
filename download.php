<?php
/* ----------------------------------------------------------
   download.php (ENHANCED VERSION)
   ✅ Optimized for WhatsApp, Facebook, Twitter, & Telegram
   ✅ Shows Large Image Preview (Big Card)
   ✅ Direct Redirect to APK for Real Users
---------------------------------------------------------- */

// 1. Settings
$siteUrl = "https://subhgaming.online";
$imageUrl = $siteUrl . "/images/mobile.png";
$apkLink = $siteUrl . "/app/mahadevking.apk";

// App Details
$pageTitle = "SUBH GAMING - Game Rates";
// Fetch Game Rates for Metadata
$gameRates = [];
try {
    require_once 'includes/db.php';
    $db = db();
    $resR = $db->query("SELECT game_type, play_amount, win_amount FROM game_rates ORDER BY id ASC");
    if ($resR) {
        while ($rowR = $resR->fetch_assoc()) {
            $gameRates[] = $rowR;
        }
    }
    $db->close();
} catch (Throwable $e) {
}

// App Details

$pageDesc = "Download App Now! ";
if (!empty($gameRates)) {
    foreach ($gameRates as $r) {
        $pageDesc .= $r['game_type'] . " ₹" . $r['play_amount'] . " = ₹" . $r['win_amount'] . ". ";
    }
} else {
    $pageDesc .= "Single Digit ₹10 = ₹95. Jodi ₹10 = ₹950. Trusted & Fast Withdrawal.";
}

$appName = "SUBH GAMING";

// 2. Advanced Bot Detection
// Detects WhatsApp, Facebook, Twitter, Telegram, Discord, Slack, etc.
$userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$bots = [
    'whatsapp',
    'facebookexternalhit',
    'twitterbot',
    'telegrambot',
    'discordbot',
    'slackbot',
    'linkedinbot',
    'pinterest',
    'skypeuripreview'
];

$isBot = false;
foreach ($bots as $bot) {
    if (strpos($userAgent, $bot) !== false) {
        $isBot = true;
        break;
    }
}

// 3. Logic: Show HTML Card to Bots, Redirect Real Users
if ($isBot) {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <link rel="icon" type="image/png" href="images/mobile.png">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($pageTitle); ?></title>

        <meta property="og:site_name" content="<?php echo $appName; ?>">
        <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
        <meta property="og:description" content="<?php echo htmlspecialchars($pageDesc); ?>">
        <meta property="og:image" content="<?php echo $imageUrl; ?>">
        <meta property="og:image:secure_url" content="<?php echo $imageUrl; ?>">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:alt" content="App Preview">

        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">

        <meta property="og:url" content="<?php echo $siteUrl; ?>/download.php">
        <meta property="og:type" content="website">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
        <meta name="twitter:description" content="<?php echo htmlspecialchars($pageDesc); ?>">
        <meta name="twitter:image" content="<?php echo $imageUrl; ?>">

        <style>
            body {
                font-family: sans-serif;
                background: #f4f4f4;
                text-align: center;
                padding: 20px;
                margin: 0;
            }

            .card {
                background: white;
                max-width: 500px;
                margin: 20px auto;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            }

            img {
                width: 100%;
                height: auto;
                border-radius: 8px;
                margin-bottom: 15px;
            }

            h1 {
                color: #333;
                font-size: 22px;
                margin: 10px 0;
            }

            p {
                color: #666;
                font-size: 16px;
                line-height: 1.5;
            }

            .btn {
                display: inline-block;
                background: #c62828;
                color: white;
                padding: 12px 25px;
                text-decoration: none;
                border-radius: 50px;
                font-weight: bold;
                margin-top: 15px;
            }
        </style>
    <link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

    <body>
        <div class="card">
            <img src="<?php echo $imageUrl; ?>" alt="App Preview">
            <h1><?php echo $pageTitle; ?></h1>
            <p><?php echo $pageDesc; ?></p>
            <a href="<?php echo $apkLink; ?>" class="btn">Download APK Now</a>
        </div>
    </body>

    </html>
    <?php
    exit;
}

// 4. Real User Redirect
header("HTTP/1.1 302 Found");
header("Location: " . $apkLink);
exit;
