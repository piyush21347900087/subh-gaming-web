<?php
require_once __DIR__ . '/includes/security.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';
require_once 'includes/views_index.php';
require_once 'includes/app_brand.php';

if (isset($_GET['apk']) && $_GET['apk'] === '1') {
    try {
        require_once 'includes/db.php';
        $db = db();
        $db->set_charset('utf8mb4');
        $db->query("SET time_zone = '+05:30'");
        $db->query("INSERT INTO downloads (id, download_count, created_at) VALUES (1, 1, NOW()) ON DUPLICATE KEY UPDATE download_count = download_count + 1, created_at = NOW()");
        $db->close();
    } catch (Throwable $e) {
    }

    $file = __DIR__ . '/app/subh_gaming.apk';
    if (!is_file($file)) {
        $file = __DIR__ . '/app/mahadevking.apk';
    }
    if (is_file($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="subh-gaming.apk"');
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars(SUBH_APP_NAME); ?> | Premium Gaming Platform</title>
    <meta name="description" content="<?php echo htmlspecialchars(SUBH_APP_NAME); ?> brings secure gaming, fast results, live charts, and premium Matka experiences." />
    <meta name="keywords" content="subh gaming, andar, matka, live results, premium betting, secure wallet, matka app" />
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta property="og:title" content="<?php echo htmlspecialchars(SUBH_APP_NAME); ?> | premium digital gaming" />
    <meta property="og:description" content="Download the <?php echo htmlspecialchars(SUBH_APP_NAME); ?> app and experience a premium gaming platform." />
    <meta property="og:image" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/images/fevicon.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #07111f;
            --bg-2: #0d1728;
            --surface: rgba(15, 23, 34, 0.88);
            --surface-strong: #111827;
            --panel: rgba(17, 24, 39, 0.9);
            --border: rgba(148, 163, 184, 0.18);
            --text: #edf4ff;
            --muted: #9fb3cb;
            --primary: #7c3aed;
            --secondary: #2dd4bf;
            --gold: #fbbf24;
            --success: #22c55e;
            --danger: #ef4444;
            --shadow: 0 28px 70px rgba(2, 6, 23, 0.5);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; font-family: 'Inter', sans-serif; }
        body {
            background: radial-gradient(circle at top left, rgba(124, 58, 237, 0.25), transparent 25%),
                        radial-gradient(circle at bottom right, rgba(45, 212, 191, 0.18), transparent 30%),
                        linear-gradient(135deg, var(--bg), var(--bg-2));
            color: var(--text);
            line-height: 1.5;
            overflow-x: hidden;
        }
        a { color: inherit; text-decoration: none; }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 24px 18px 80px; }
        header {
            display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 14px 0 24px;
        }
        .brand { display: flex; align-items: center; gap: 14px; }
        .brand-mark { width: 48px; height: 48px; border-radius: 16px; display: grid; place-items: center; font-weight: 800; background: linear-gradient(135deg, var(--primary), var(--secondary)); box-shadow: 0 16px 32px rgba(124, 58, 237, 0.38); }
        .brand-text strong { display: block; font-size: 1.1rem; }
        .brand-text span { color: var(--muted); font-size: 0.75rem; letter-spacing: 0.08em; text-transform: uppercase; }
        .header-actions { display: flex; gap: 12px; align-items: center; }
        .pill {
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px; border: 1px solid var(--border);
            background: rgba(148,163,184,0.06); color: var(--text); font-weight: 600;
        }
        .hero { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 24px; align-items: center; padding: 28px 0 10px; }
        .hero-copy h1 { font-size: clamp(2.5rem, 4vw, 4.4rem); line-height: 1.02; margin-bottom: 18px; }
        .hero-copy h1 .accent { color: var(--secondary); }
        .hero-copy p { font-size: 1.06rem; color: var(--muted); max-width: 620px; }
        .cta-row { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 26px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; min-width: 188px; border-radius: 14px; padding: 15px 20px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; border: 1px solid transparent; transition: 0.2s ease; cursor: pointer;
        }
        .btn.primary { background: linear-gradient(135deg, var(--primary), #4f46e5); color: white; box-shadow: 0 20px 36px rgba(124,58,237,.26); }
        .btn.secondary { background: rgba(148,163,184,0.06); color: var(--text); border-color: var(--border); }
        .hero-panel {
            background: linear-gradient(180deg, rgba(124,58,237,0.18), rgba(15,23,34,0.95)); border: 1px solid var(--border);
            border-radius: 28px; padding: 22px; box-shadow: var(--shadow);
        }
        .mock-app {
            background: linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02)); border: 1px solid var(--border); border-radius: 22px; padding: 20px; min-height: 400px;
        }
        .mini-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .mini-header .live { display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 999px; background: rgba(34,197,94,0.12); color: #a7f3d0; font-size: 0.76rem; }
        .score-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .score-card { background: rgba(255,255,255,0.04); border: 1px solid var(--border); border-radius: 18px; padding: 18px 14px; }
        .score-card .label { color: var(--muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; }
        .score-card strong { display: block; font-size: 2rem; margin-top: 8px; }
        .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 22px; }
        .stat { border: 1px solid var(--border); border-radius: 16px; background: rgba(255,255,255,0.02); padding: 16px; }
        .stat strong { display: block; margin-top: 10px; font-size: 1.5rem; }
        .section { margin-top: 52px; }
        .section-head { display: flex; justify-content: space-between; align-items: end; gap: 16px; margin-bottom: 18px; }
        .section-head h2 { font-size: clamp(1.55rem, 2vw, 2.2rem); }
        .section-head span { color: var(--muted); }
        .feature-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        .feature { background: rgba(15,23,34,0.7); border: 1px solid var(--border); border-radius: 22px; padding: 22px; }
        .feature .icon { width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; background: linear-gradient(135deg, rgba(124,58,237,0.2), rgba(45,212,191,0.12)); color: var(--secondary); font-size: 1.3rem; margin-bottom: 16px; }
        .feature h3 { margin-bottom: 8px; font-size: 1.2rem; }
        .feature p { color: var(--muted); }
        @media (max-width: 900px) {
            .hero { grid-template-columns: 1fr; }
            .feature-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 540px) {
            header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; justify-content: space-between; }
            .stats { grid-template-columns: 1fr; }
            .cta-row .btn { width: 100%; }
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <div class="wrap">
        <header>
            <div class="brand">
                <div class="brand-mark">SG</div>
                <div class="brand-text">
                    <strong><?php echo htmlspecialchars(SUBH_APP_NAME); ?></strong>
                    <span><?php echo htmlspecialchars(SUBH_APP_TAGLINE); ?></span>
                </div>
            </div>
            <div class="header-actions">
                <div class="pill">Live • Fast Results</div>
            </div>
        </header>

        <section class="hero">
            <div class="hero-copy">
                <h1>Play <span class="accent">Andar</span> with a premium gaming experience.</h1>
                <p><?php echo htmlspecialchars(SUBH_APP_NAME); ?> is a premium digital gaming platform built for secure wallet flow, live charts, quick result access, and a modern player-first interface.</p>
                <div class="cta-row">
                    <a class="btn primary" href="<?php echo htmlspecialchars(SUBH_API_BASE); ?>">Open App</a>
                    <a class="btn secondary" href="admin_login.php">Admin Login</a>
                </div>
            </div>

            <div class="hero-panel">
                <div class="mock-app">
                    <div class="mini-header">
                        <strong>Live Dashboard</strong>
                        <span class="live">● Online</span>
                    </div>
                    <div class="score-grid">
                        <div class="score-card">
                            <div class="label">Markets</div>
                            <strong>48+</strong>
                        </div>
                        <div class="score-card">
                            <div class="label">Payouts</div>
                            <strong>9x</strong>
                        </div>
                    </div>
                    <div class="stats">
                        <div class="stat"><span>Players</span><strong>12K</strong></div>
                        <div class="stat"><span>Wins</span><strong>96%</strong></div>
                        <div class="stat"><span>Speed</span><strong>2s</strong></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-head">
                <h2>Built for a modern gaming brand</h2>
                <span>Design system • APIs • Admin ready</span>
            </div>
            <div class="feature-grid">
                <article class="feature">
                    <div class="icon">⚡</div>
                    <h3>Fast UX</h3>
                    <p>Responsive layouts, quick navigation, and premium card interactions built for mobile-first players.</p>
                </article>
                <article class="feature">
                    <div class="icon">🔒</div>
                    <h3>Secure Flow</h3>
                    <p>Wallet, auth, and audit-ready schema support a safer operational pipeline.</p>
                </article>
                <article class="feature">
                    <div class="icon">📊</div>
                    <h3>Data Ready</h3>
                    <p>Admin dashboard, migration history, and database control layer are prepared for clean deployment.</p>
                </article>
            </div>
        </section>
    </div>
</body>
</html>

            background: #dadce0;
        }

        .stat-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--gp-dark-text);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            margin-bottom: 2px;
            height: 20px;
        }

        .stat-label {
            font-size: 11px;
            color: var(--gp-grey-text);
            white-space: nowrap;
        }

        /* Download Button */
        .download-btn {
            display: block;
            width: 100%;
            background-color: #01875f;
            color: white;
            text-align: center;
            padding: 12px 0;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            margin-bottom: 32px;
            transition: background-color 0.2s;
        }

        .download-btn:active {
            background-color: #016e4e;
        }

        /* --- Screenshot Slider --- */
        .screenshot-container {
            margin: 0 -16px 32px -16px;
            overflow-x: auto;
            display: flex;
            gap: 12px;
            padding: 0 16px;
            scrollbar-width: none;
        }

        .screenshot-container::-webkit-scrollbar {
            display: none;
        }

        .screenshot-item {
            flex: 0 0 auto;
            width: 180px;
            height: 320px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--gp-border);
        }

        .screenshot-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* --- Sections Content --- */
        .content-section {
            padding: 24px 0;
            border-top: 1px solid var(--gp-border);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .section-header h2 {
            font-size: 18px;
            font-weight: 500;
        }

        .section-header .arrow {
            color: var(--gp-grey-text);
            font-size: 20px;
        }

        .section-text {
            font-size: 14px;
            color: var(--gp-grey-text);
            line-height: 1.6;
        }

        /* Data Safety Card */
        .safety-card {
            border: 1px solid var(--gp-border);
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
        }

        .safety-item {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }

        .safety-icon {
            font-size: 20px;
            color: var(--gp-grey-text);
        }

        .safety-info h3 {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .safety-info p {
            font-size: 12px;
            color: var(--gp-grey-text);
        }

        /* Ratings & Reviews */
        .rating-summary {
            display: flex;
            align-items: center;
            gap: 32px;
            margin-top: 24px;
        }

        .rating-big-num {
            font-size: 48px;
            font-weight: 500;
        }

        .rating-bars {
            flex: 1;
        }

        .bar-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 4px;
        }

        .bar-num {
            font-size: 12px;
            width: 8px;
        }

        .bar-bg {
            flex: 1;
            height: 8px;
            background: #e8eaed;
            border-radius: 4px;
            position: relative;
        }

        .bar-fill {
            height: 100%;
            background: var(--gp-green);
            border-radius: 4px;
        }

        /* Footer */
        footer {
            background-color: var(--gp-bg);
            padding: 24px 16px 48px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .footer-header {
            font-size: 18px;
            font-weight: 500;
            color: var(--gp-dark-text);
            margin-bottom: 12px;
        }

        .footer-text {
            font-size: 13px;
            color: var(--gp-dark-text);
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .flag-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: var(--gp-dark-text);
            font-weight: 500;
            text-decoration: none;
            margin-bottom: 48px;
        }

        .footer-divider {
            border-top: 1px solid #f1f3f4;
            margin-bottom: 32px;
        }

        .footer-links {
            display: flex;
            gap: 120px;
            margin-bottom: 48px;
            flex-wrap: wrap;
        }

        .footer-col {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .footer-col a {
            text-decoration: none;
            color: var(--gp-grey-text);
            font-size: 14px;
        }

        .footer-col a:hover {
            color: var(--gp-dark-text);
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .footer-bottom-links {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            font-size: 12px;
            color: var(--gp-grey-text);
        }

        .footer-bottom-links a {
            text-decoration: none;
            color: var(--gp-grey-text);
        }

        .region-select {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--gp-dark-text);
        }

        /* Mobile specific adjustments */
        @media (max-width: 600px) {
            .app-identity {
                gap: 16px;
            }

            .app-icon {
                width: 72px;
                height: 72px;
            }

            .app-info-main h1 {
                font-size: 20px;
            }

            .screenshot-item {
                width: 110px;
                height: 195px;
            }

            .footer-links {
                gap: 32px;
                flex-direction: column;
            }

            .footer-bottom {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>
    <?php
    $modalStatusLocal = getAppSetting('modal_status', 'OFF');
    ?>

    <div id="mainContent">
        <header>
            <div class="header-left">
                <img src="images/googleplayfulllogo.png" alt="Google Play" class="header-logo">
            </div>
            <div class="header-right">
                <button class="icon-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" style="width: 20px; height: 20px;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
                <button class="icon-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" style="width: 20px; height: 20px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                </button>
                <button class="icon-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" style="width: 20px; height: 20px;">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </button>
            </div>
        </header>

        <div class="hero">
            <div class="app-identity">
                <img src="images/fevicon.png" alt="SUBH GAMING Matka" class="app-icon">
                <div class="app-info-main">
                    <h1>SUBH GAMING Matka</h1>
                    <p class="developer-name">SUBH GAMING Matka Team</p>
                    <p class="info-tags">In-app purchases</p>
                </div>
            </div>

            <div class="stats-bar">
                <div class="stat-item">
                    <div class="stat-value">4.6 <svg viewBox="0 0 24 24" width="16" height="16">
                            <path
                                d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"
                                fill="currentColor" />
                        </svg></div>
                    <div class="stat-label">5.9k Reviews</div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <div class="stat-value">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="12" y1="8" x2="12" y2="16"></line>
                            <polyline points="8 12 12 16 16 12"></polyline>
                        </svg>
                    </div>
                    <div class="stat-label">7.6 MB</div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <div class="stat-value">
                        <span
                            style="border: 1px solid currentColor; padding: 0 4px; border-radius: 2px; font-size: 11px; font-weight: bold; line-height: 1;">18+</span>
                    </div>
                    <div class="stat-label">Rated for 3+</div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <div class="stat-value">50K+</div>
                    <div class="stat-label">Downloads</div>
                </div>
            </div>

            <a href="landing.php?apk=1" class="download-btn">Download</a>

            <div class="screenshot-container">
                <div class="screenshot-item"><img src="images/screenshot1.jpeg" alt="Screenshot 1"
                        onclick="openScreenshot(this.src)" style="cursor: pointer;"></div>
                <div class="screenshot-item"><img src="images/screenshot2.jpeg" alt="Screenshot 2"
                        onclick="openScreenshot(this.src)" style="cursor: pointer;"></div>
                <div class="screenshot-item"><img src="images/screenshot3.jpeg" alt="Screenshot 3"
                        onclick="openScreenshot(this.src)" style="cursor: pointer;"></div>
                <div class="screenshot-item"><img src="images/screenshot4.jpeg" alt="Screenshot 4"
                        onclick="openScreenshot(this.src)" style="cursor: pointer;"></div>
                <div class="screenshot-item"><img src="images/screenshot5.jpeg" alt="Screenshot 5"
                        onclick="openScreenshot(this.src)" style="cursor: pointer;"></div>
                <div class="screenshot-item"><img src="images/screenshot6.jpeg" alt="Screenshot 6"
                        onclick="openScreenshot(this.src)" style="cursor: pointer;"></div>
            </div>

            <div class="content-section">
                <div class="section-header">
                    <h2 style="font-size: 20px; color: var(--gp-dark-text);">About this app</h2>
                    <span class="arrow"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg></span>
                </div>
                <div class="section-text" style="color: var(--gp-dark-text); font-size: 14px; margin-bottom: 24px;">
                    <p style="margin-bottom: 16px;">SUBH GAMING Matka is a free Android app that lets users participate
                        in
                        and check the results of online Matka games. Matka is a widely popular form of entertainment in
                        India. The SUBH GAMING Matka app provides live and lightning-fast results for games like
                        Shridevi,
                        Milan, Kalyan, Rajdhani, Time Bazar, and more.</p>
                    <p>To get started with SUBH GAMING Matka , users simply need to download and install the app from the
                        Play Store. After installation, they can create an account by entering their name, phone number,
                        and
                        a secure password. Phone number verification is required to complete the registration process.
                    </p>
                </div>

                <div style="margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 8px;">
                    <span
                        style="border: 1px solid var(--gp-border); padding: 6px 16px; border-radius: 4px; font-size: 13px; color: var(--gp-dark-text);">E-Sports</span>
                    <span
                        style="border: 1px solid var(--gp-border); padding: 6px 16px; border-radius: 4px; font-size: 13px; color: var(--gp-dark-text);">Casual
                        Gaming</span>
                    <span
                        style="border: 1px solid var(--gp-border); padding: 6px 16px; border-radius: 4px; font-size: 13px; color: var(--gp-dark-text);">Mobile
                        Gaming</span>
                    <span
                        style="border: 1px solid var(--gp-border); padding: 6px 16px; border-radius: 4px; font-size: 13px; color: var(--gp-dark-text);">Card
                        Gaming</span>
                </div>

                <p style="font-size: 14px; color: var(--gp-dark-text); margin-bottom: 16px;">You can use SUBH GAMING
                    Matka
                    to:</p>

                <div style="margin-bottom: 16px;">
                    <h3 style="font-size: 14px; font-weight: 500; color: var(--gp-dark-text);">Updated on</h3>
                    <p style="font-size: 14px; color: var(--gp-dark-text);">Mar 16, 2026</p>
                </div>

                <span
                    style="border: 1px solid var(--gp-border); padding: 6px 16px; border-radius: 16px; font-size: 13px; color: var(--gp-grey-text); display: inline-block;">Productivity</span>
            </div>

            <div class="content-section">
                <div class="section-header">
                    <h2 style="font-size: 20px; color: var(--gp-dark-text);">Data safety</h2>
                    <span class="arrow"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg></span>
                </div>
                <div class="section-text" style="color: var(--gp-dark-text); font-size: 14px; margin-bottom: 24px;">
                    Safety starts with understanding how developers collect and share your data. Data privacy and
                    security
                    practices may vary based on your use, region, and age. The developer provided this information and
                    may
                    update it over time.
                </div>
                <div class="safety-card" style="padding: 24px; border: 1px solid var(--gp-border); border-radius: 8px;">
                    <div class="safety-item" style="display: flex; gap: 24px; margin-bottom: 24px;">
                        <span class="safety-icon" style="color: var(--gp-grey-text); display: flex;"><svg width="24"
                                height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z">
                                </path>
                            </svg></span>
                        <div class="safety-info">
                            <h3
                                style="font-size: 14px; font-weight: 400; color: var(--gp-dark-text); margin-bottom: 4px;">
                                No data shared with third parties</h3>
                            <p style="font-size: 14px; color: var(--gp-dark-text); margin-bottom: 0;">Learn more about
                                how
                                developers declare sharing</p>
                        </div>
                    </div>
                    <div class="safety-item" style="display: flex; gap: 24px; margin-bottom: 24px;">
                        <span class="safety-icon" style="color: var(--gp-grey-text); display: flex;"><svg width="24"
                                height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z">
                                </path>
                            </svg></span>
                        <div class="safety-info">
                            <h3
                                style="font-size: 14px; font-weight: 400; color: var(--gp-dark-text); margin-bottom: 4px;">
                                This app may collect these data types</h3>
                            <p style="font-size: 14px; color: var(--gp-dark-text); margin-bottom: 0;">Location, Personal
                                info and 5 others</p>
                        </div>
                    </div>
                    <div class="safety-item"
                        style="display: flex; gap: 24px; margin-bottom: 24px; align-items: center;">
                        <span class="safety-icon" style="color: var(--gp-grey-text); display: flex;"><svg width="24"
                                height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                </path>
                            </svg></span>
                        <div class="safety-info">
                            <h3
                                style="font-size: 14px; font-weight: 400; color: var(--gp-dark-text); margin-bottom: 0;">
                                Data is encrypted in transit</h3>
                        </div>
                    </div>
                    <div class="safety-item"
                        style="display: flex; gap: 24px; margin-bottom: 24px; align-items: center;">
                        <span class="safety-icon" style="color: var(--gp-grey-text); display: flex;"><svg width="24"
                                height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                </path>
                            </svg></span>
                        <div class="safety-info">
                            <h3
                                style="font-size: 14px; font-weight: 400; color: var(--gp-dark-text); margin-bottom: 0;">
                                Data is encrypted in transit</h3>
                        </div>
                    </div>
                    <p style="color:#01875f; font-size:14px; font-weight:500;">See details</p>
                </div>
            </div>
            <p style="font-size: 12px; color: var(--gp-grey-text); margin-top: 16px;">Ratings and reviews are verified.
                They
                were left by users with the same type of device as yours.</p>


            <div class="content-section">
                <div class="section-header">
                    <h2>Ratings and reviews</h2>
                    <span class="arrow">→</span>
                </div>
                <div class="rating-summary">
                    <div
                        style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 100px;">
                        <div class="rating-big-num" style="font-size: 56px; line-height: 1;">4.6</div>
                        <div style="color: #1a73e8; font-size: 14px; letter-spacing: 2px;">★★★★★</div>
                        <div style="font-size: 12px; color: var(--gp-grey-text); margin-top: 4px;">5899 Reviews</div>
                    </div>
                    <div class="rating-bars">
                        <div class="bar-row">
                            <span class="bar-num">5</span>
                            <div class="bar-bg">
                                <div class="bar-fill" style="width: 85%;"></div>
                            </div>
                        </div>
                        <div class="bar-row">
                            <span class="bar-num">4</span>
                            <div class="bar-bg">
                                <div class="bar-fill" style="width: 75%;"></div>
                            </div>
                        </div>
                        <div class="bar-row">
                            <span class="bar-num">3</span>
                            <div class="bar-bg">
                                <div class="bar-fill" style="width: 3%;"></div>
                            </div>
                        </div>
                        <div class="bar-row">
                            <span class="bar-num">2</span>
                            <div class="bar-bg">
                                <div class="bar-fill" style="width: 1%;"></div>
                            </div>
                        </div>
                        <div class="bar-row">
                            <span class="bar-num">1</span>
                            <div class="bar-bg">
                                <div class="bar-fill" style="width: 1%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Review List -->
                <div class="review-list" style="margin-top: 32px;">
                    <!-- Review 1 -->
                    <div class="review-item" style="margin-bottom: 24px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                            <div
                                style="width: 32px; height: 32px; border-radius: 50%; background: #2a6db5; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 500;">
                                A</div>
                            <span style="font-size: 14px; font-weight: 500; color: #202124;">Arjun Yadav</span>
                        </div>
                        <div style="color: #01875f; font-size: 16px; letter-spacing: 2px; margin-bottom: 8px;">★★★★★
                        </div>
                        <p style="font-size: 14px; color: #5f6368; line-height: 1.4;">This app is truly amazing! I have been using SUBH GAMING Matka since 2021 and I really appreciate the fast results and smooth experience.</p>
                    </div>

                    <!-- Review 2 -->
                    <div class="review-item" style="margin-bottom: 24px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                            <div
                                style="width: 32px; height: 32px; border-radius: 50%; background: #673ab7; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 500;">
                                V</div>
                            <span style="font-size: 14px; font-weight: 500; color: #202124;">Vikram Singh</span>
                        </div>
                        <div style="color: #01875f; font-size: 16px; letter-spacing: 2px; margin-bottom: 8px;">★★★★★
                        </div>
                        <p style="font-size: 14px; color: #5f6368; line-height: 1.4;">I play on SUBH GAMING Matka every day. The winning payouts are very quick and the interface is very user-friendly.</p>
                    </div>

                    <!-- Review 3 -->
                    <div class="review-item" style="margin-bottom: 24px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                            <div
                                style="width: 32px; height: 32px; border-radius: 50%; background: #4caf50; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 500;">
                                M</div>
                            <span style="font-size: 14px; font-weight: 500; color: #202124;">Manish Kumar</span>
                        </div>
                        <div style="color: #01875f; font-size: 16px; letter-spacing: 2px; margin-bottom: 8px;">★★★★★
                        </div>
                        <p style="font-size: 14px; color: #5f6368; line-height: 1.4;">SUBH GAMING Matka has become my favorite app. It's stable, reliable, and the support team is very helpful.</p>
                    </div>

                    <!-- Review 4 -->
                    <div class="review-item" style="margin-bottom: 24px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                            <div
                                style="width: 32px; height: 32px; border-radius: 50%; background: #9c27b0; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 500;">
                                S</div>
                            <span style="font-size: 14px; font-weight: 500; color: #202124;">Suresh Patil</span>
                        </div>
                        <div style="color: #01875f; font-size: 16px; letter-spacing: 2px; margin-bottom: 8px;">★★★★★
                        </div>
                        <p style="font-size: 14px; color: #5f6368; line-height: 1.4;">Excellent gaming platform. The diversity of markets like Milan and Kalyan is perfect for all players.</p>
                    </div>

                    <!-- Review 5 -->
                    <div class="review-item" style="margin-bottom: 24px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                            <div
                                style="width: 32px; height: 32px; border-radius: 50%; background: #1a73e8; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 500;">
                                K</div>
                            <span style="font-size: 14px; font-weight: 500; color: #202124;">Kiran Sharma</span>
                        </div>
                        <div style="color: #01875f; font-size: 16px; letter-spacing: 2px; margin-bottom: 8px;">★★★★★
                        </div>
                        <p style="font-size: 14px; color: #5f6368; line-height: 1.4;">SUBH GAMING Matka is definitely the most trusted app. I've recommended it to all my friends who enjoy Matka.</p>
                    </div>

                    <!-- Review 6 -->
                    <div class="review-item" style="margin-bottom: 24px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                            <div
                                style="width: 32px; height: 32px; border-radius: 50%; background: #3c4043; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 500;">
                                P</div>
                            <span style="font-size: 14px; font-weight: 500; color: #202124;">Pankaj Mehra</span>
                        </div>
                        <div style="color: #01875f; font-size: 16px; letter-spacing: 2px; margin-bottom: 8px;">★★★★★
                        </div>
                        <p style="font-size: 14px; color: #5f6368; line-height: 1.4;">I enjoy the fantasy features on SUBH GAMING Matka. They even added football recently, which is great!</p>
                    </div>

                    <!-- Review 7 -->
                    <div class="review-item" style="margin-bottom: 24px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                            <div
                                style="width: 32px; height: 32px; border-radius: 50%; background: #4caf50; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 500;">
                                D</div>
                            <span style="font-size: 14px; font-weight: 500; color: #202124;">Deepak Gupta</span>
                        </div>
                        <div style="color: #01875f; font-size: 16px; letter-spacing: 2px; margin-bottom: 8px;">★★★★★
                        </div>
                        <p style="font-size: 14px; color: #5f6368; line-height: 1.4;">SUBH GAMING Matka offers the best experience with multiple game options. Winning has never been this easy!</p>
                    </div>
                </div>

                <p style="color:#01875f; font-size:14px; font-weight:500; margin-top: 8px;">See all reviews</p>
            </div>
        </div>

        <footer>
            <h2 class="footer-header">What's new</h2>
            <p class="footer-text">
                We've enhanced the game experience with smoother gameplay and faster results in our latest updates.
                We've
                also added new popular Matka games, including Shridevi, Milan, Kalyan, and Rajdhani. The user interface
                has
                been improved for easier navigation and a more intuitive design, ensuring seamless use. Additionally,
                we've
                addressed minor bugs to enhance overall app performance.
            </p>
            <a href="#" class="flag-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                    <line x1="4" y1="22" x2="4" y2="15"></line>
                </svg>
                Flag as inappropriate
            </a>

            <div class="footer-divider"></div>

            <div class="footer-links">
                <div class="footer-col">
                    <a href="#">Google Play</a>
                    <a href="#">Play Pass</a>
                    <a href="#">Play Points</a>
                    <a href="#">Gift cards</a>
                    <a href="#">Redeem</a>
                    <a href="#">Refund policy</a>
                </div>
                <div class="footer-col">
                    <a href="#">Children and family</a>
                    <a href="#">Parent guide</a>
                    <a href="#">Family sharing</a>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="footer-bottom-links">
                    <a href="#">Terms of service</a>
                    <a href="#">Privacy</a>
                    <a href="#">About Google Play</a>
                    <a href="#">Developers</a>
                    <a href="#">Google Store</a>
                    <span>All prices include GST.</span>
                </div>
                <div class="region-select">
                    <span>India (English (India))</span>
                    <span style="font-size: 16px;">🇮🇳</span>
                </div>
            </div>
        </footer>

        <!-- Scroll to Top Button -->
        <button id="scrollToTopBtn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
            style="display: none; position: fixed; bottom: 30px; right: 30px; z-index: 9000; border: none; outline: none; background-color: #01875f; color: white; cursor: pointer; padding: 12px; border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.3); width: 48px; height: 48px; align-items: center; justify-content: center; transition: background-color 0.3s;">
            <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2.5" fill="none"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 19V5M5 12l7-7 7 7" />
            </svg>
        </button>

        <script>
            // Scroll to Top visibility
            window.onscroll = function () {
                var scrollBtn = document.getElementById("scrollToTopBtn");
                if (document.body.scrollTop > 400 || document.documentElement.scrollTop > 400) {
                    if (scrollBtn.style.display !== 'flex') scrollBtn.style.display = "flex";
                } else {
                    if (scrollBtn.style.display !== 'none') scrollBtn.style.display = "none";
                }
            };

            function openScreenshot(src) {
                document.getElementById('screenshotModalImg').src = src;
                document.getElementById('screenshotModal').style.display = 'flex';
            }

            function closeScreenshot() {
                document.getElementById('screenshotModal').style.display = 'none';
            }
        </script>

        <!-- Screenshot Fullscreen Modal -->
        <div id="screenshotModal" onclick="closeScreenshot()"
            style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.9); z-index: 20000; align-items: center; justify-content: center; cursor: zoom-out;">
            <span onclick="closeScreenshot()"
                style="position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
            <img id="screenshotModalImg" src=""
                style="max-width: 90%; max-height: 90vh; border-radius: 8px; object-fit: contain;">
        </div>

    </div> <!-- End of mainContent -->
</body>

</html>





