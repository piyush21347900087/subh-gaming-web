<?php
// privacy_policy.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <title>Privacy Policy | SUBH GAMING</title>
    <meta name="description" content="Privacy Policy for SUBH GAMING. We ensure the security and privacy of your data on our platform.">
    <meta name="keywords" content="SUBH GAMING matka, satta matka, kalyan matka, kalyan panel chart, kalyan jodi chart, milan day chart, rajdhani night chart, satta king, dpboss, matka guessing, online matka play, indian matka, satta result, matka 420, tara matka, fix matka, satta matka 143, kalyan result, mumbai matka, gali desawar, fast matka result, kalyan chart, satta matka market, matka boss, satta batta, kalyan open to close, satta king result, matka gambling, matka prediction, matka number, satta matka net, matka world, satta matka live, matka online result, satta bazaar, kalyan result today, mumbai result today, matka game, satta king tips, best matka guessing, matka app download">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        :root {
            --LL-red: #b71c1c;
            --LL-red-dark: #7f1111;
            --LL-gold: #f9b237;
            --LL-bg: #f5f0e5;
            --LL-text-main: #1f130d;
            --LL-text-muted: #6b5545;
            --LL-card: #ffffff;
            --LL-border-soft: #e5d5c4;
            --LL-shadow-soft: 0 8px 24px rgba(0, 0, 0, 0.12);
            --LL-footer-bg: #2d1811;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #FFFFFF;
            color: var(--LL-text-main);
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        .scroll-top-btn {
            position: fixed;
            bottom: 30px;
            right: 25px;
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #0066cc, #004d99);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 8px 25px rgba(0, 102, 204, 0.35);
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
        }

        .scroll-top-btn.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .scroll-top-btn:hover {
            transform: translateY(-5px);
            background: linear-gradient(135deg, #0077ee, #0055aa);
            box-shadow: 0 12px 30px rgba(0, 102, 204, 0.5);
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* NAVBAR */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            height: 70px;
            display: flex;
            align-items: center;
        }

        .nav-inner {
            max-width: 1100px;
            width: 100%;
            margin: 0 auto;
            padding: 0 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-logo {
            width: 140px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            position: relative;
            overflow: hidden;
        }

        .brand-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            position: relative;
            z-index: 1;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.22));
            animation: logoFlash 3.2s ease-in-out infinite;
        }

        .brand-logo::after {
            content: "";
            position: absolute;
            top: -20%;
            left: -35%;
            width: 32%;
            height: 140%;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.85), rgba(255, 255, 255, 0));
            transform: skewX(-24deg);
            animation: logoShine 3.2s ease-in-out infinite;
            pointer-events: none;
            z-index: 2;
        }

        @keyframes logoFlash {
            0%, 100% {
                filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.18)) brightness(1);
            }
            18% {
                filter: drop-shadow(0 0 18px rgba(255, 255, 255, 0.4)) brightness(1.12);
            }
            28% {
                filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.22)) brightness(1.02);
            }
        }

        @keyframes logoShine {
            0%, 70%, 100% {
                left: -45%;
                opacity: 0;
            }
            12% {
                opacity: 0;
            }
            22% {
                left: 118%;
                opacity: 1;
            }
            30% {
                opacity: 0;
            }
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 14px;
        }

        .nav-link {
            position: relative;
            font-weight: 500;
            color: var(--LL-text-muted);
            padding: 5px 0;
        }

        .nav-link.active {
            color: var(--LL-red);
        }

        .nav-link::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--LL-gold), var(--LL-red));
            border-radius: 999px;
            transition: width 0.22s ease-out;
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }

        .nav-download-btn {
            padding: 0 20px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            background: linear-gradient(90deg, #0066cc, #0088ff);
            color: #fff;
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            transition: 0.3s;
            height: 40px;
            text-transform: uppercase;
        }

        .nav-icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #424242;
            display: flex;
            justify-content: center;
            align-items: center;
            text-decoration: none;
            font-size: 16px;
            transition: 0.3s;
            border: 1px solid var(--LL-border-soft);
        }

        .nav-icon-btn:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-download-btn:hover {
            opacity: 0.95;
            transform: translateY(-2px);
        }

        .nav-download-btn span.icon {
            font-size: 15px;
        }

        .nav-toggle {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            width: 40px;
            height: 40px;
            justify-content: center;
            align-items: center;
        }

        .nav-toggle span {
            width: 24px;
            height: 2px;
            border-radius: 999px;
            background: #ffffff;
            transition: all 0.3s ease;
        }

        /* MOBILE MENU */
        .mobile-menu {
            display: none;
            position: absolute;
            left: 0;
            right: 0;
            top: 70px;
            background: #ffffff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            padding: 10px 0 20px;
            z-index: 90;
            animation: slideDown 0.3s ease forwards;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .navbar.nav-open .mobile-menu {
            display: block;
        }

        .navbar.nav-open .nav-toggle span:nth-child(1) {
            transform: translateY(7px) rotate(45deg);
        }

        .navbar.nav-open .nav-toggle span:nth-child(2) {
            opacity: 0;
        }

        .navbar.nav-open .nav-toggle span:nth-child(3) {
            transform: translateY(-7px) rotate(-45deg);
        }

        .mobile-links {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            margin-bottom: 15px;
        }

        .mobile-links .nav-link {
            width: 100%;
            text-align: center;
            padding: 8px 0;
        }

        .mobile-download-btn {
            margin: 0 auto;
            width: 90%;
            max-width: 300px;
            justify-content: center;
            height: 48px;
        }

        @media (max-width: 880px) {

            .nav-links,
            .nav-download-btn {
                display: none;
            }

            .nav-toggle {
                display: flex;
                margin-left: auto;
            }
        }

        /* LAYOUT */
        .page-wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px 16px 60px;
            flex: 1;
            width: 100%;
        }

        .page-header {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--LL-text-main);
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--LL-text-muted);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.5;
        }

        /* POLICY CARD */
        .policy-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--LL-shadow-soft);
            border: 1px solid rgba(244, 192, 123, 0.5);
        }

        @media (max-width: 600px) {
            .policy-card {
                padding: 18px 16px;
            }
        }

        .policy-section {
            margin-bottom: 24px;
        }

        .policy-section:last-child {
            margin-bottom: 0;
        }

        .policy-heading {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--LL-text-main);
        }

        .policy-text {
            font-size: 13px;
            line-height: 1.7;
            color: var(--LL-text-muted);
        }

        .policy-list {
            margin-top: 8px;
            padding-left: 20px;
            font-size: 13px;
            color: var(--LL-text-muted);
            line-height: 1.6;
        }

        .policy-list li {
            margin-bottom: 6px;
        }

        .policy-highlight {
            color: var(--LL-red);
            font-weight: 600;
        }

        /* FOOTER */
        .footer {
            background: var(--LL-footer-bg);
            padding: 20px 16px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            text-align: center;
            margin-top: auto;
        }

        .footer-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }

        @media(min-width: 600px) {
            .footer-inner {
                flex-direction: row;
                justify-content: space-between;
            }
        }

        .footer-links {
            display: flex;
            gap: 15px;
        }

        .footer-links a {
            color: #fff;
            text-decoration: none;
            border-bottom: 1px dotted rgba(255, 255, 255, 0.5);
        }

        .footer-links a:hover {
            opacity: 1;
            border-bottom-style: solid;
        }

        /* OVERRIDE FOR WHITE HEADER */
        .navbar {
            background: #ffffff !important;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06) !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12) !important;
        }
        .nav-link {
            color: #1f2937 !important;
        }
        .nav-link.active {
            color: var(--LL-red) !important;
        }
        .nav-toggle span {
            background: #1f2937 !important;
        }
        .mobile-links .nav-link {
            color: #000000 !important;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

    <header class="navbar" id="navbar">
        <div class="nav-inner">
            <div class="nav-left">
                <div class="brand-logo">
                    <img src="images/logo.png" alt="SUBH GAMING Logo">
                </div>
            </div>

            <nav class="nav-links">
                <a href="index.php" class="nav-link">Home</a>
                <a href="charts.php" class="nav-link">Charts</a>
                <span class="nav-link active">Privacy Policy</span>
            </nav>

            <div class="nav-right">
                <button class="nav-download-btn" onclick="window.location.href='index.php?apk=1&ref=nav'">
                    <span>Download App</span>
                </button>
            </div>

            <div class="nav-toggle" id="navToggle">
                <span></span><span></span><span></span>
            </div>

            <div class="mobile-menu" id="mobileMenu">
                <nav class="mobile-links">
                    <a href="index.php" class="nav-link">Home</a>
                    <a href="charts.php" class="nav-link">Charts</a>
                    <a href="privacy_policy.php" class="nav-link active">Privacy Policy</a>
                </nav>
                <button class="nav-download-btn mobile-download-btn"
                    onclick="window.location.href='index.php?apk=1&ref=mob'">
                    <span>Download App</span>
                </button>
            </div>
        </div>
    </header>

    <main class="page-wrap">
        <div class="page-header">
            <h1 class="page-title">Privacy Policy</h1>
            <div class="page-subtitle">
                SUBH GAMING application aur website par aapke data ki security aur privacy hamari zimmedari hai.
            </div>
        </div>

        <div class="policy-card">
            <div class="policy-section">
                <div class="policy-heading">1. Introduction</div>
                <p class="policy-text">
                    Ye Privacy Policy <span class="policy-highlight">SUBH GAMING</span> (website aur Android application)
                    par lagu hoti hai. Iska purpose hai aapko batana ki hum kaun-kaun sa data collect karte hain,
                    kaise istemal karte hain aur kaise secure rakhte hain.
                </p>
            </div>

            <div class="policy-section">
                <div class="policy-heading">2. Information We Collect</div>
                <p class="policy-text">
                    Hum sirf wo information collect karte hain jo service chalane ke liye zaroori hoti hai.
                    Hum koi extra personal data be-wajah collect nahi karte.
                </p>
                <ul class="policy-list">
                    <li>Account details (name, mobile number, user ID).</li>
                    <li>Login information (basic device info, approximate IP logs – security ke liye).</li>
                    <li>Wallet &amp; transaction information (deposit, withdrawal, winning history).</li>
                    <li>UPI / Bank details jo aap khud provide karte hain withdrawal aur KYC ke liye.</li>
                </ul>
            </div>

            <div class="policy-section">
                <div class="policy-heading">3. How We Use Your Information</div>
                <p class="policy-text">
                    Aapka data hum sirf legal aur genuine purpose ke liye use karte hain:
                </p>
                <ul class="policy-list">
                    <li>Account create karne aur aapko authenticate (login) karne ke liye.</li>
                    <li>Wallet balance calculate karne, deposits &amp; withdrawals process karne ke liye.</li>
                    <li>Results, wallet update, notice, ya security alerts ke liye notification bhejne ke liye.</li>
                    <li>Fraud, abuse ya kisi illegal activity ko detect aur prevent karne ke liye.</li>
                </ul>
                <p class="policy-text" style="margin-top:8px;">
                    SUBH GAMING app <strong>result notification dikhane</strong> ke liye hi notification permission use
                    karta hai
                    aur KYC ke liye image upload ke waqt hi media access ka use karta hai. Iske alawa background me
                    koi extra personal data secretly collect nahi kiya jata.
                </p>
            </div>

            <div class="policy-section">
                <div class="policy-heading">4. Data Sharing &amp; Third Parties</div>
                <p class="policy-text">
                    Hum aapka personal data kisi bhi third party ko <strong>sell</strong> nahi karte. Limited cases
                    me data share ho sakta hai:
                </p>
                <ul class="policy-list">
                    <li>Payment gateway / bank partners ke sath, sirf transaction process karne ke liye.</li>
                    <li>Legal authority / law enforcement jab kanoon ke mutabiq zaroori ho.</li>
                    <li>Service providers (server / SMS / notification etc.) jo hamare behalf par kaam karte hain.</li>
                </ul>
            </div>

            <div class="policy-section">
                <div class="policy-heading">5. Data Security</div>
                <p class="policy-text">
                    Hum appropriate technical aur organisational measures use karte hain jisse aapka data secure rahe:
                </p>
                <ul class="policy-list">
                    <li>Secure server aur database configuration.</li>
                    <li>Limited access – sirf authorised admin panel users ko hi sensitive data access milta hai.</li>
                    <li>Regular monitoring to detect unusual activity ya misuse.</li>
                </ul>
                <p class="policy-text" style="margin-top:8px;">
                    Phir bhi koi bhi online system 100% secure nahi hota. Hum hamesha best effort se security
                    improve karte rehte hain.
                </p>
            </div>

            <div class="policy-section">
                <div class="policy-heading">6. Cookies &amp; App Permissions</div>
                <p class="policy-text">
                    Website par hum basic cookies ka use kar sakte hain jisse aapka session remember ho sake
                    (for example: login session). Android application me kuch permissions ki zaroorat padti hai,
                    lekin unka use clearly limited hota hai:
                </p>
                <ul class="policy-list">
                    <li>
                        <strong>Notification Permission</strong> – sirf market ka
                        <span class="policy-highlight">result dikhane</span> ke liye notification send karne me use hota
                        hai.
                        Koi extra marketing ya unwanted push spam ke liye use nahi kiya jata.
                    </li>
                    <li>
                        <strong>Media / Image Access</strong> – sirf tab use hota hai jab aap
                        <span class="policy-highlight">KYC documents, screenshot, ya bank proof</span> upload karte
                        hain.
                        Gallery ya personal photos background me scan/store nahi kiye jate.
                    </li>
                    <li>
                        <strong>Download / Storage Permission</strong> – sirf app ka
                        <span class="policy-highlight">latest version download</span> karne ke liye use hota hai,
                        taaki aap SUBH GAMING ka updated APK save aur install kar saken.
                    </li>
                </ul>
                <p class="policy-text" style="margin-top:8px;">
                    In permissions ke alawa app koi hidden tracking ya unnecessary data collection nahi karta.
                </p>
            </div>

            <div class="policy-section">
                <div class="policy-heading">7. Your Rights</div>
                <p class="policy-text">
                    Aapko apne data par kuch basic rights hamesha milte hain:
                </p>
                <ul class="policy-list">
                    <li>Apne account ki basic details dekhne aur update karne ka right.</li>
                    <li>Support se contact karke galat information correct karwane ka option.</li>
                    <li>Account close karwane ka request dene ka option (as per legal &amp; record-keeping needs).</li>
                </ul>
            </div>

            <div class="policy-section">
                <div class="policy-heading">8. Children’s Privacy</div>
                <p class="policy-text">
                    SUBH GAMING platform <strong>18 saal se kam umr</strong> ke users ke liye design nahi kiya gaya.
                    Agar hume pata chale ki kisi minor ne galat information dekar account banaya hai toh hum account ko
                    block/close kar sakte hain.
                </p>
            </div>

            <div class="policy-section">
                <div class="policy-heading">9. Changes to this Policy</div>
                <p class="policy-text">
                    Kabhi kabhi hum is Privacy Policy me changes kar sakte hain. Jab bhi update hoga,
                    upar ka <strong>Last Updated</strong> date change ho jayega (agar add kiya gaya ho) aur updated
                    policy
                    website / app par available rahegi.
                </p>
            </div>

            <div class="policy-section">
                <div class="policy-heading">10. Contact Us</div>
                <p class="policy-text">
                    Agar aapko is Privacy Policy ke baare me koi bhi sawal, concern ya request ho,
                    toh aap hamse contact kar sakte hain:
                </p>
                <ul class="policy-list">
                    <li>Email: <span class="policy-highlight">kismat555official@gmail.com</span></li>
                    <li>In-app support / live chat se message bhej kar.</li>
                </ul>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-inner">
            <div>© <?php echo date('Y'); ?> SUBH GAMING. All Rights Reserved.</div>
            <div class="footer-links">
                <a href="index.php">Home</a>
                <a href="charts.php">Charts</a>
                <a href="privacy_policy.php">Privacy Policy</a>
            </div>
        </div>
    </footer>

    <script>
        var navbar = document.getElementById('navbar');
        var navToggle = document.getElementById('navToggle');

        if (navToggle && navbar) {
            navToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                navbar.classList.toggle('nav-open');
            });
        }

        document.addEventListener('click', function (e) {
            if (!navbar.classList.contains('nav-open')) return;
            if (!navbar.contains(e.target)) {
                navbar.classList.remove('nav-open');
            }
        });

        document.querySelectorAll('#mobileMenu a').forEach(function (link) {
            link.addEventListener('click', function () {
                navbar.classList.remove('nav-open');
            });
        });
    </script>

    <button class="scroll-top-btn" id="scrollTopBtn" title="Go to top">
        <i class="fa-solid fa-chevron-up"></i>
    </button>

    <script>
        var scrollTopBtn = document.getElementById('scrollTopBtn');
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 400) {
                scrollTopBtn.classList.add('show');
            } else {
                scrollTopBtn.classList.remove('show');
            }
        });
        scrollTopBtn.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>
</body>

</html>


