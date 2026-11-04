const fs = require('fs');

// 1. Fix price_chart.php side menu HTML
let pc = fs.readFileSync('price_chart.php', 'utf8');

const sideMenuHtml = `
  <!-- SIDE MENU -->
    <div class="side-menu-overlay" id="sideOverlay"></div>
    <div class="side-menu" id="sideMenu">
        <!-- Header (user info) -->
        <div class="side-menu-header">
            <div class="profile-avatar"><i class="fas fa-user"></i></div>
            <div class="profile-text">
                <div class="profile-name"><?= htmlspecialchars($userName) ?></div>
                <div class="profile-id"><?= htmlspecialchars($userMobile ?: $code) ?></div>
            </div>
            <button type="button" class="side-close-btn" id="sideCloseBtn">
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
            <a href="how_to_play.php" class="side-menu-item">
                <div class="icon-box"><img src="icons/how_to_play.svg" alt="How to Play"></div>
                <span class="label">How To Play</span>
            </a>

            <a href="settings.php" class="side-menu-item">
                <div class="icon-box"><img src="icons/setting,svg.svg" alt="Settings"></div>
                <span class="label">Settings</span>
            </a>
            <a href="#" class="side-menu-item" id="shareBtn">
                <div class="icon-box"><img src="icons/share.svg" alt="Share"></div>
                <span class="label">Share Application</span>
            </a>
            <a href="user_update.php" class="side-menu-item">
                <div class="icon-box"><img src="icons/change_password.svg" alt="Change Password"></div>
                <span class="label">Change Password</span>
            </a>

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
`;

// Insert sideMenuHtml before <header> if not present
if (!pc.includes('id="sideMenu"')) {
    pc = pc.replace('<header>', sideMenuHtml + '\n    <header>');
}

// 2. Update active icon filter to Lite Red (#ff5252 / #ff4d4d)
// Dark red filter: brightness(0) saturate(100%) invert(29%) sepia(85%) saturate(7483%) hue-rotate(353deg) brightness(101%) contrast(105%)
// Light red filter: brightness(0) saturate(100%) invert(48%) sepia(79%) saturate(2476%) hue-rotate(328deg) brightness(102%) contrast(101%)

const oldFilterRegex = /filter:\s*brightness\(0\)\s*saturate\(100%\)\s*invert\(29%\)\s*sepia\(85%\)\s*saturate\(7483%\)\s*hue-rotate\(353deg\)\s*brightness\(101%\)\s*contrast\(105%\);/g;
const liteRedFilter = 'filter: brightness(0) saturate(100%) invert(48%) sepia(79%) saturate(2476%) hue-rotate(328deg) brightness(102%) contrast(101%);';

pc = pc.replace(oldFilterRegex, liteRedFilter);

fs.writeFileSync('price_chart.php', pc, 'utf8');

// Also update user_dashboard.php
let ud = fs.readFileSync('user_dashboard.php', 'utf8');
if (!ud.includes('id="sideMenu"')) {
    ud = ud.replace('<header>', sideMenuHtml + '\n    <header>');
}
ud = ud.replace(oldFilterRegex, liteRedFilter);
fs.writeFileSync('user_dashboard.php', ud, 'utf8');

console.log("Successfully fixed side menu and lite red filter!");
