<?php
// languages.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lang'])) {
    $lang = $_POST['lang'];
    // Set cookie valid for 1 year
    setcookie('app_lang_selected', '1', time() + (86400 * 365), "/");
    setcookie('app_lang', $lang, time() + (86400 * 365), "/");
    
    // Check if the user is logged in already just in case
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    if (isset($_SESSION['user']['id'])) {
        require_once 'includes/db.php';
        $db = db();
        $stmt = $db->prepare("UPDATE users SET language = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $lang, $_SESSION['user']['id']);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: user_dashboard.php");
    } elseif (isset($_COOKIE['remember_mobile'])) {
        header("Location: user_dashboard.php");
    } else {
        header("Location: user_register.php");
    }
    exit;
}
?>
<?php
require_once 'includes/lang.php';
$currentLang = $_COOKIE['app_lang'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Choose Language</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        body {
            background-color: #f8f9fa;
            color: #333333;
        }
        .container {
            max-width: 480px;
            margin: 0 auto;
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }
        .header {
            background-color: #8b0000;
            background-image: 
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px),
                repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0.09) 1px, transparent 0, transparent 14px);
            padding-top: calc(env(safe-area-inset-top, 0px) + 12px);
            padding-bottom: 12px;
            padding-left: 16px;
            padding-right: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header h1 {
            font-size: 18px;
            font-weight: 800;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .back-btn {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #e53935;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff !important;
            border: none;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.15s ease;
        }
        .back-btn:active {
            transform: scale(0.92);
        }
        
        /* Grid Layout: 2 items per row */
        .lang-grid {
            padding: 20px 16px 110px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }
        
        /* Modern Vertical Card Shape */
        .lang-card {
            background: #ffffff;
            border: 1.5px solid #eef0f3;
            border-radius: 18px;
            padding: 18px 12px 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }
        
        .lang-card:active {
            transform: scale(0.96);
        }
        
        .lang-card.selected {
            border: 2px solid #e53935;
            background: #fff8f8;
            box-shadow: 0 8px 22px rgba(229, 57, 53, 0.16);
        }
        
        /* Checkmark Badge */
        .check-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #e53935;
            font-size: 16px;
            opacity: 0;
            transform: scale(0.4);
            transition: all 0.25s ease;
        }
        
        .lang-card.selected .check-badge {
            opacity: 1;
            transform: scale(1);
        }
        
        /* Icon Shape & Styling */
        .lang-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #f0f2f5, #e4e7eb);
            color: #444444;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 10px;
            transition: all 0.25s ease;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.06);
        }
        
        .lang-card:hover .lang-icon {
            transform: scale(1.06);
        }
        
        .lang-card.selected .lang-icon {
            background: linear-gradient(135deg, #e53935 0%, #c62828 50%, #8b0000 100%);
            color: #ffffff;
            box-shadow: 0 6px 14px rgba(229, 57, 53, 0.35);
        }
        
        .lang-name-local {
            font-size: 15px;
            font-weight: 700;
            color: #111111;
            line-height: 1.2;
            margin-bottom: 2px;
        }
        
        .lang-card.selected .lang-name-local {
            color: #e53935;
        }
        
        .lang-name-en {
            font-size: 12px;
            font-weight: 500;
            color: #888888;
        }
        
        .footer-fixed {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 480px;
            padding: 16px 20px 30px;
            background: #ffffff;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .btn-continue {
            width: 100%;
            background: linear-gradient(135deg, #e53935 0%, #c62828 50%, #8b0000 100%);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 15px;
            padding: 16px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            outline: none;
            box-shadow: 0 5px 0 #8b0000, 0 6px 12px rgba(229, 57, 53, 0.4);
            position: relative;
            transition: all 0.1s ease;
            overflow: hidden;
            animation: jellyPulse 3s infinite ease-in-out;
        }
        
        .btn-continue::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 5%;
            width: 90%;
            height: 30%;
            background: linear-gradient(180deg,rgba(255,255,255,0.25),transparent);
            border-radius: 10px 10px 0 0;
            pointer-events: none;
            z-index: 2;
        }

        .btn-continue::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: btnFlash 3s infinite;
            z-index: 1;
        }
        
        .btn-continue:active {
            animation: none;
            transform: translateY(4px);
            box-shadow: 0 0px 0 #8b0000, 0 2px 5px rgba(229, 57, 53, 0.3);
        }

        @keyframes jellyPulse {
            0%, 100% { transform: scale(1, 1); }
            25% { transform: scale(1.05, 0.95); }
            50% { transform: scale(0.95, 1.05); }
            75% { transform: scale(1.02, 0.98); }
        }

        @keyframes btnFlash {
            0% { left: -100%; }
            20% { left: 100%; }
            100% { left: 100%; }
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>

<div class="container">
    <div class="header">
        <button class="back-btn" onclick="goBack()"><i class="fa-solid fa-chevron-left"></i></button>
        <h1><?= __("Choose Language") ?></h1>
        <div style="width: 38px;"></div>
    </div>
    
    <div class="lang-grid">
        <!-- English -->
        <div class="lang-card <?= $currentLang === 'en' ? 'selected' : '' ?>" onclick="selectLang(this, 'en')">
            <div class="check-badge"><i class="fa-solid fa-circle-check"></i></div>
            <div class="lang-icon">
                <span>EN</span>
            </div>
            <div class="lang-name-local">ENGLISH</div>
            <div class="lang-name-en">English</div>
        </div>
        
        <!-- Hindi -->
        <div class="lang-card <?= $currentLang === 'hi' ? 'selected' : '' ?>" onclick="selectLang(this, 'hi')">
            <div class="check-badge"><i class="fa-solid fa-circle-check"></i></div>
            <div class="lang-icon">
                <span>अ</span>
            </div>
            <div class="lang-name-local">हिंदी</div>
            <div class="lang-name-en">Hindi</div>
        </div>
        
        <!-- Kannada -->
        <div class="lang-card <?= $currentLang === 'kn' ? 'selected' : '' ?>" onclick="selectLang(this, 'kn')">
            <div class="check-badge"><i class="fa-solid fa-circle-check"></i></div>
            <div class="lang-icon">
                <span>ಕ</span>
            </div>
            <div class="lang-name-local">ಕನ್ನಡ</div>
            <div class="lang-name-en">Kannada</div>
        </div>
        
        <!-- Malayalam -->
        <div class="lang-card <?= $currentLang === 'ml' ? 'selected' : '' ?>" onclick="selectLang(this, 'ml')">
            <div class="check-badge"><i class="fa-solid fa-circle-check"></i></div>
            <div class="lang-icon">
                <span>മ</span>
            </div>
            <div class="lang-name-local">മലയാളം</div>
            <div class="lang-name-en">Malayalam</div>
        </div>
        
        <!-- Gujarati -->
        <div class="lang-card <?= $currentLang === 'gu' ? 'selected' : '' ?>" onclick="selectLang(this, 'gu')">
            <div class="check-badge"><i class="fa-solid fa-circle-check"></i></div>
            <div class="lang-icon">
                <span>ગ</span>
            </div>
            <div class="lang-name-local">ગુજરાતી</div>
            <div class="lang-name-en">Gujarati</div>
        </div>
        
        <!-- Marathi -->
        <div class="lang-card <?= $currentLang === 'mr' ? 'selected' : '' ?>" onclick="selectLang(this, 'mr')">
            <div class="check-badge"><i class="fa-solid fa-circle-check"></i></div>
            <div class="lang-icon">
                <span>म</span>
            </div>
            <div class="lang-name-local">मराठी</div>
            <div class="lang-name-en">Marathi</div>
        </div>
        
        <!-- Tamil -->
        <div class="lang-card <?= $currentLang === 'ta' ? 'selected' : '' ?>" onclick="selectLang(this, 'ta')">
            <div class="check-badge"><i class="fa-solid fa-circle-check"></i></div>
            <div class="lang-icon">
                <span>த</span>
            </div>
            <div class="lang-name-local">தமிழ்</div>
            <div class="lang-name-en">Tamil</div>
        </div>
        
        <!-- Telugu -->
        <div class="lang-card <?= $currentLang === 'te' ? 'selected' : '' ?>" onclick="selectLang(this, 'te')">
            <div class="check-badge"><i class="fa-solid fa-circle-check"></i></div>
            <div class="lang-icon">
                <span>తె</span>
            </div>
            <div class="lang-name-local">తెలుగు</div>
            <div class="lang-name-en">Telugu</div>
        </div>
    </div>
    
    <div class="footer-fixed">
        <form method="POST" id="langForm" onsubmit="saveLangToLocal()">
            <input type="hidden" name="lang" id="selectedLangInput" value="<?= htmlspecialchars($currentLang) ?>">
            <button type="submit" class="btn-continue"><?= __("CONTINUE") ?></button>
        </form>
    </div>
</div>

<script>

    function saveLangToLocal() {
        localStorage.setItem('app_lang_selected', '1');
        localStorage.setItem('app_lang', document.getElementById('selectedLangInput').value);
    }

    function goBack() {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = "user_login.php";
        }
    }
    
    function selectLang(el, code) {
        // Remove selected from all
        var cards = document.querySelectorAll('.lang-card');
        for (var i = 0; i < cards.length; i++) {
            cards[i].classList.remove('selected');
        }
        // Add selected to clicked
        el.classList.add('selected');
        // Set hidden input value
        document.getElementById('selectedLangInput').value = code;
    }
</script>

</body>
</html>
