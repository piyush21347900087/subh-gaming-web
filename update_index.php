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
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 4px solid #ff4d4d;
            box-shadow: 0 4px 25px rgba(255, 0, 0, 0.25);
            background: #000000;
            padding: 5px;
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
        }
        .btn-whatsapp {
            background: #25D366;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(37, 211, 102, 0.25);
        }

        .footer {
            text-align: center;
            padding: 24px;
            color: #666666;
            font-size: 13px;
            margin-top: auto;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

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
        <p class="tagline">Official Satta Matka Platform</p>

        <!-- Main Action Buttons -->
        <a href="index.php?apk=1" class="btn-large btn-download">
            <i class="fa-solid fa-download"></i> Download App
        </a>

        <?php if (!empty($whatsappNo)): ?>
        <a href="https://wa.me/<?= preg_replace('/\\D/', '', $whatsappNo) ?>" target="_blank" rel="noopener" class="btn-large btn-whatsapp">
            <i class="fa-brands fa-whatsapp"></i> WhatsApp
        </a>
        <?php endif; ?>

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
