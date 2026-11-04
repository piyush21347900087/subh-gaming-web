<?php
$f = file_get_contents("index.php");

$phpPart = substr($f, 0, strpos($f, "<!DOCTYPE html>"));

$htmlPart = <<<'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>SUBH GAMING | Official Platform</title>
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
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid #2a2a2a;
        }

        .header-title {
            color: #ffffff;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .header-title span {
            color: #ff0000;
        }

        .header-btns {
            display: flex;
            gap: 12px;
        }
        .btn-hdr {
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-login { background: #ff4d4d; color: #ffffff; }
        .btn-register { background: #ffffff; color: #000000; }

        .main-container {
            flex: 1;
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
            width: 100%;
        }

        .logo-box {
            text-align: center;
            margin: 20px 0 10px 0;
        }
        .logo-box img {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            border: 3px solid #ff4d4d;
            box-shadow: 0 4px 16px rgba(255, 0, 0, 0.15);
            background: #000000;
            padding: 4px;
        }
        
        .app-title {
            text-align: center;
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            margin-top: 10px;
        }
        .app-title span { color: #ff0000; }
        
        .tagline {
            text-align: center;
            color: #888888;
            font-size: 13px;
            margin-top: 4px;
            margin-bottom: 30px;
            font-weight: 500;
        }

        .btn-large {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 15px;
            border-radius: 14px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 12px;
            transition: all 0.2s;
        }
        .btn-download {
            background: #ff4d4d;
            color: #ffffff;
            box-shadow: 0 6px 15px rgba(255, 77, 77, 0.25);
        }
        .btn-whatsapp {
            background: #25D366;
            color: #ffffff;
            box-shadow: 0 6px 15px rgba(37, 211, 102, 0.2);
        }

        .section-box {
            background: #121212;
            border-radius: 16px;
            padding: 20px;
            margin-top: 25px;
            border: 1px solid #2a2a2a;
        }
        .box-title {
            color: #ff0000;
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        table { width: 100%; border-collapse: collapse; }
        th { background: #1a1a1a; color: #ff4d4d; padding: 12px; font-size: 12px; text-transform: uppercase; text-align: left; border-radius: 6px 6px 0 0; }
        td { padding: 12px; border-bottom: 1px solid #222222; color: #dddddd; font-size: 13px; font-weight: 500; }
        tr:last-child td { border-bottom: none; }

        .market-list { display: flex; flex-direction: column; gap: 10px; }
        .market-item {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #ff4d4d;
        }
        .m-name { color: #ffffff; font-weight: 700; font-size: 14px; margin-bottom: 4px; text-transform: uppercase; }
        .m-res { color: #ff4d4d; font-weight: 800; font-size: 17px; }
        
        .m-btns { display: flex; gap: 8px; }
        .btn-chart {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            color: #ffffff;
            text-transform: uppercase;
        }
        .btn-jodi { background: #ff4d4d; }
        .btn-panel { background: #333333; }

        .footer {
            text-align: center;
            padding: 24px;
            color: #666666;
            font-size: 12px;
            margin-top: auto;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

    <!-- Header -->
    <header class="header-bar">
        <div class="header-title">MAHADEV <span>KING</span></div>
        <div class="header-btns">
            <a href="user_login.php" class="btn-hdr btn-login">Login</a>
            <a href="user_register.php" class="btn-hdr btn-register">Register</a>
        </div>
    </header>

    <div class="main-container">

        <!-- Logo & Title -->
        <div class="logo-box">
            <img src="<?= htmlspecialchars(index_asset_url('images/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Logo" onerror="this.src='icons/icon.png';">
        </div>
        <h1 class="app-title">MAHADEV <span>KING</span></h1>
        <p class="tagline">Official Satta Matka Platform</p>

        <!-- Main Action Buttons -->
        <a href="index.php?apk=1" class="btn-large btn-download">
            <i class="fa-solid fa-download"></i> Download Application
        </a>

        <?php if (!empty($whatsappNo)): ?>
        <a href="https://wa.me/<?= preg_replace('/\\D/', '', $whatsappNo) ?>" target="_blank" rel="noopener" class="btn-large btn-whatsapp">
            <i class="fa-brands fa-whatsapp"></i> WhatsApp: <?= htmlspecialchars($whatsappNo) ?>
        </a>
        <?php endif; ?>

        <!-- Live Markets -->
        <?php if (!empty($markets)): ?>
        <div class="section-box">
            <h2 class="box-title"><i class="fa-solid fa-chart-line"></i> Live Market Results</h2>
            <div class="market-list">
                <?php foreach ($markets as $m): ?>
                    <?php
                    $chartSlug = strtolower(trim((string) $m['slug']));
                    $chartSlug = preg_replace('/[^a-z0-9]+/', '_', $chartSlug);
                    $chartBase = 'charts/' . trim($chartSlug, '_');
                    $jodiFile  = $chartBase . '_full_chart.php?type=jodi';
                    $panelFile = $chartBase . '_full_chart.php?type=panel';
                    ?>
                    <div class="market-item">
                        <div>
                            <div class="m-name"><?= htmlspecialchars($m['label']) ?></div>
                            <div class="m-res"><?= htmlspecialchars($m['latest']) ?></div>
                        </div>
                        <div class="m-btns">
                            <a href="<?= htmlspecialchars($jodiFile, ENT_QUOTES, 'UTF-8') ?>" class="btn-chart btn-jodi">Jodi</a>
                            <a href="<?= htmlspecialchars($panelFile, ENT_QUOTES, 'UTF-8') ?>" class="btn-chart btn-panel">Panel</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Game Rates -->
        <div class="section-box">
            <h2 class="box-title"><i class="fa-solid fa-trophy"></i> Market Game Rates</h2>
            <table>
                <thead>
                    <tr>
                        <th>Game</th>
                        <th>Play</th>
                        <th>Win</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($allRates)): ?>
                        <?php foreach ($allRates as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['game_type']) ?></td>
                            <td>₹<?= htmlspecialchars($r['play_amount']) ?></td>
                            <td>₹<?= number_format($r['win_amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td>Single Digit</td><td>₹10</td><td>₹95</td></tr>
                        <tr><td>Jodi Digit</td><td>₹10</td><td>₹950</td></tr>
                        <tr><td>Single Pana</td><td>₹10</td><td>₹1500</td></tr>
                        <tr><td>Double Pana</td><td>₹10</td><td>₹3000</td></tr>
                        <tr><td>Triple Pana</td><td>₹10</td><td>₹7000</td></tr>
                        <tr><td>Half Sangam</td><td>₹10</td><td>₹10000</td></tr>
                        <tr><td>Full Sangam</td><td>₹10</td><td>₹100000</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Footer -->
    <div class="footer">
        &copy; <?= date('Y') ?> SUBH GAMING. All Rights Reserved.
    </div>

</body>
</html>
EOF;

file_put_contents("index.php", $phpPart . $htmlPart);
echo "DONE";
?>
