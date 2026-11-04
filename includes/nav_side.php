<?php
require_once __DIR__ . '/lang.php';
/**
 * SUBH GAMING – Shared Side Menu Include
 * ===========================================
 * Variables required BEFORE this file is included:
 *   $userName  (string) – user display name
 *   $code      (string) – user ID code
 *
 * Active page is detected automatically via $_SERVER['PHP_SELF'].
 *
 * Usage:
 *   require_once 'includes/nav_side.php';
 */

// Detect current page for active highlighting
$_navPage = basename($_SERVER['PHP_SELF']);

// Helper: returns 'active' class if the given filename matches current page
function _navActive(string $page): string {
    global $_navPage;
    return $_navPage === $page ? ' active' : '';
}

$_navUser = htmlspecialchars(isset($userName) ? $userName : 'User');
$_navCode = htmlspecialchars(isset($code)     ? $code     : '');

// Fetch Game Rates & Support Channels for Menu & Sharing
$_navRates = [];
$_tgSupport = '';
$_tgChannel = '';
$_waSupport = '';
try {
    if (!isset($db)) {
        require_once 'includes/db.php';
        $db = db();
    }
    $resR = $db->query("SELECT game_type, play_amount, win_amount FROM game_rates ORDER BY id ASC");
    if ($resR) {
        while ($rowR = $resR->fetch_assoc()) {
            $_navRates[] = $rowR;
        }
    }
    $resPay = $db->query("SELECT whatsapp_no_1, whatsapp_no_2, telegram_id, telegram_channel, show_telegram, show_telegram_channel FROM admin_payment_details LIMIT 1");
    if ($resPay && $rP = $resPay->fetch_assoc()) {
        if (!empty($rP['show_telegram']) && !empty($rP['telegram_id'])) {
            $t = trim($rP['telegram_id']);
            $_tgSupport = str_starts_with($t, 'http') ? $t : ('https://t.me/' . ltrim($t, '@'));
        }
        if (!empty($rP['show_telegram_channel']) && !empty($rP['telegram_channel'])) {
            $c = trim($rP['telegram_channel']);
            $_tgChannel = str_starts_with($c, 'http') ? $c : ('https://t.me/' . ltrim($c, '@'));
        }
        $w = trim($rP['whatsapp_no_1'] ?: ($rP['whatsapp_no_2'] ?? ''));
        $wDigits = preg_replace('/\D/', '', $w);
        if (strlen($wDigits) === 10) { $wDigits = '91' . $wDigits; }
        if (!empty($wDigits)) {
            $_waSupport = "https://wa.me/" . $wDigits;
        }
    }
} catch (Throwable $e) {}
?>

<style>
/* =====================================================
   SUBH GAMING – Side Menu CSS
   (Exact copy from user_dashboard.php)
===================================================== */
.side-menu-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.6);
    display: none;
    z-index: 4000;
    backdrop-filter: blur(2px);
}
.side-menu {
    position: fixed; top: 0; left: -250px;
    width: 250px; height: 100%;
    background: #000000; color: #ffffff;
    padding: 0;
    transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 4100;
    box-shadow: 5px 0 20px rgba(0,0,0,0.5);
    display: flex; flex-direction: column;
    border-right: 1px solid var(--accent-yellow);
}
.side-menu.open { left: 0; }

.side-menu-header {
    padding: 20px 15px;
    background: linear-gradient(135deg, #1c1427, #000);
    border-bottom: 1px solid rgba(255,193,7,0.2);
    display: flex; align-items: center; gap: 12px;
}
.profile-avatar {
    width: 50px; height: 50px;
    border-radius: 50%;
    border: 2px solid var(--accent-yellow);
    background: rgba(255,193,7,0.1);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
    color: var(--accent-yellow);
    box-shadow: 0 0 15px rgba(255,193,7,0.2);
}
.profile-text { display: flex; flex-direction: column; min-width: 0; }
.profile-name { font-size: 16px; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.profile-id   { font-size: 11px; margin-top: 2px; color: var(--accent-yellow); font-weight: 600; }
.side-close-btn { margin-left: auto; background: transparent; border: none; color: var(--accent-yellow); font-size: 20px; cursor: pointer; }

.side-menu-body { padding: 10px; flex: 1; overflow-y: auto; }
.side-menu-item {
    display: flex; align-items: center;
    padding: 10px 14px; margin: 5px 0;
    font-size: 14px; color: #e0e0e0;
    text-decoration: none;
    border-radius: 10px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.05);
    transition: all 0.2s ease;
}
.side-menu-item i { margin-right: 15px; font-size: 18px; width: 24px; text-align: center; color: var(--accent-yellow); }
.side-menu-item:hover { background: rgba(255,193,7,0.08); color: var(--accent-yellow); border-color: rgba(255,193,7,0.3); transform: translateX(5px); }
.side-menu-item.active {
    background: var(--gradient-gold);
    color: #000; font-weight: 700; border: none;
    box-shadow: 0 4px 15px rgba(243,156,18,0.3);
}
.side-menu-item.active i { color: #000; }
.side-menu .side-menu-item.active {
    background: rgba(0, 0, 0, 0.06) !important;
    color: #000000 !important;
    font-weight: 700 !important;
    border: none !important;
    box-shadow: none !important;
}
.side-menu .side-menu-item.active i {
    color: #000000 !important;
    background: linear-gradient(135deg, #e6e9f0, #eef1f5, #bdc3c7) !important;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

.side-menu-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); background: #000; }
.side-footer-row  { display: flex; align-items: center; gap: 12px; }
.side-logout-btn {
    flex: 1; height: 48px;
    background: linear-gradient(90deg, #e74c3c, #c0392b);
    color: #fff; border-radius: 10px;
    font-size: 15px; font-weight: 700;
    text-decoration: none; text-transform: uppercase;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    box-shadow: 0 4px 12px rgba(231,76,60,0.3);
}
.share-btn {
    width: 48px; height: 48px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.1);
    background: var(--gradient-gold);
    color: #000;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(243,156,18,0.3);
}
</style>

<!-- ════════════════ SIDE MENU HTML ════════════════ -->
<div class="side-menu-overlay" id="sideOverlay"></div>
<div class="side-menu" id="sideMenu">

    <!-- Header (user info) -->
    <div class="side-menu-header">
        <div class="profile-avatar"><i class="fas fa-user"></i></div>
        <div class="profile-text">
            <div class="profile-name"><?= $_navUser ?></div>
            <div class="profile-id">ID: <?= $_navCode ?></div>
        </div>
        <button type="button" class="side-close-btn" id="sideCloseBtn">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Menu Items -->
    <div class="side-menu-body">
        <a href="languages.php" class="side-menu-item<?= _navActive('languages.php') ?>">
            <i class="fa-solid fa-language"></i><span class="label">Languages</span>
        </a>
        <a href="user_dashboard.php"  class="side-menu-item<?= _navActive('user_dashboard.php') ?>">
            <i class="fi fi-rr-home"></i><span class="label">Home</span>
        </a>
        <a href="user_profile.php"    class="side-menu-item<?= _navActive('user_profile.php') ?>">
            <i class="fi fi-rr-user"></i><span class="label">My Profile</span>
        </a>
        <a href="how_to_play.php"     class="side-menu-item<?= _navActive('how_to_play.php') ?>">
            <i class="fi fi-rr-info"></i><span class="label">How to Play</span>
        </a>
        <a href="notice_board.php"    class="side-menu-item<?= _navActive('notice_board.php') ?>">
            <i class="fi fi-rr-bell"></i><span class="label">Notice Board</span>
        </a>
        <a href="download.php"        class="side-menu-item<?= _navActive('download.php') ?>">
            <i class="fi fi-rr-download"></i><span class="label">Latest App</span>
        </a>
        <a href="price_chart.php"     class="side-menu-item<?= _navActive('price_chart.php') ?>">
            <i class="fi fi-rr-stats"></i><span class="label">Game Rates</span>
        </a>
        <a href="deposit.php"     class="side-menu-item<?= _navActive('deposit.php') ?>">
            <i class="fi fi-rr-receipt"></i><span class="label">Transaction History</span>
        </a>
        <a href="user_wining.php"     class="side-menu-item<?= _navActive('user_wining.php') ?>">
            <i class="fi fi-rr-trophy"></i><span class="label">Wining History</span>
        </a>
        <a href="user_bid_history.php" class="side-menu-item<?= _navActive('user_bid_history.php') ?>">
            <i class="fi fi-rr-list"></i><span class="label">Bid History</span>
        </a>
        <a href="user_panel_chart.php" class="side-menu-item<?= _navActive('user_panel_chart.php') ?>">
            <i class="fi fi-rr-apps"></i><span class="label">Panel Chart</span>
        </a>
        <?php if (!empty($_waSupport)): ?>
        <a href="<?= htmlspecialchars($_waSupport) ?>" target="_blank" class="side-menu-item" style="color:#22c55e;">
            <i class="fa-brands fa-whatsapp" style="color:#22c55e;"></i><span class="label">WhatsApp Support</span>
        </a>
        <?php endif; ?>
        <?php if (!empty($_tgSupport)): ?>
        <a href="<?= htmlspecialchars($_tgSupport) ?>" target="_blank" class="side-menu-item" style="color:#38bdf8;">
            <i class="fa-brands fa-telegram" style="color:#38bdf8;"></i><span class="label">Telegram Support</span>
        </a>
        <?php endif; ?>
        <?php if (!empty($_tgChannel)): ?>
        <a href="<?= htmlspecialchars($_tgChannel) ?>" target="_blank" class="side-menu-item" style="color:#0ea5e9;">
            <i class="fas fa-bullhorn" style="color:#0ea5e9;"></i><span class="label">Telegram Channel</span>
        </a>
        <?php endif; ?>
    </div>

    <!-- Footer (Logout + Share) -->
    <div class="side-menu-footer">
        <div class="side-footer-row">
            <a href="user_logout.php" class="side-logout-btn" id="logoutSideBtn">
                <i class="fas fa-sign-out-alt"></i><span><?= __("Logout") ?></span>
            </a>
            <button type="button" class="share-btn" id="shareBtn">
                <i class="fa-solid fa-share-nodes"></i>
            </button>
        </div>
    </div>
</div>

<!-- ════════════════ SIDE MENU SCRIPT ════════════════ -->
<script>
(function () {
    const menuBtn    = document.getElementById('menuToggle');
    const sideMenu   = document.getElementById('sideMenu');
    const sideOverlay = document.getElementById('sideOverlay');
    const sideClose  = document.getElementById('sideCloseBtn');
    const logoutBtn  = document.getElementById('logoutSideBtn');
    const shareBtn   = document.getElementById('shareBtn');

    function openMenu()  { sideMenu?.classList.add('open');    if (sideOverlay) sideOverlay.style.display = 'block'; }
    function closeMenu() { sideMenu?.classList.remove('open'); if (sideOverlay) sideOverlay.style.display = 'none';  }

    menuBtn?.addEventListener('click', openMenu);
    sideOverlay?.addEventListener('click', closeMenu);
    sideClose?.addEventListener('click', closeMenu);

    logoutBtn?.addEventListener('click', e => {
        e.preventDefault(); closeMenu();
        window.location.href = 'user_logout.php';
    });

    shareBtn?.addEventListener('click', () => {
        closeMenu();
        const host = window.location.host;
        const protocol = window.location.protocol;
        const appUrl = protocol + '//' + host + '/download.php';
        <?php
require_once __DIR__ . '/lang.php';
        $shareText = "Subh Gaming - Market Price \n♠️*Game Rate*♠️ \n\n";
        if (!empty($_navRates)) {
            foreach ($_navRates as $r) {
                $shareText .= "▪️" . $r['game_type'] . " ₹" . $r['play_amount'] . " = ₹" . $r['win_amount'] . " ";
            }
        } else {
            $shareText .= "▪️Single Digit ₹10 = ₹95 ▪️Jodi Digit ₹10 = ₹950 ▪️Single Pana ₹10 = ₹1500 ▪️Double Pana ₹10 = ₹3000 ▪️Triple Pana ₹10 = ₹7000 ▪️Half Sangam ₹10 = ₹10000 ▪️Full Sangam ₹10 = ₹100000 ";
        }
        $shareText .= "\n\nDownload Subh Gaming App:\n";
        ?>
        const fullText = `<?= $shareText ?>` + appUrl;
        if (navigator.share) {
            navigator.share({ text: fullText });
        } else {
            window.location.href = 'whatsapp://send?text=' + encodeURIComponent(fullText);
        }
    });

    /* ✅ ENHANCED: App Downloading Popup Interceptor */
    function initDownloaderUI() {
      document.body.addEventListener('click', (e) => {
        let target = e.target.closest('a[href="download.php"]');
        if (target) {
          e.preventDefault();
          
          const overlay = document.createElement('div');
          overlay.style.cssText = "position:fixed;inset:0;background:rgba(0,0,0,0.8);backdrop-filter:blur(8px);z-index:10000;display:flex;justify-content:center;align-items:center;font-family:'Poppins',sans-serif;opacity:0;transition:opacity 0.3s ease;";
          
          const modal = document.createElement('div');
          modal.style.cssText = "background:#1a1a1a;color:#fff;border:2px solid #39ff14;border-radius:20px;padding:30px 20px;width:85%;max-width:360px;text-align:center;box-shadow:0 0 25px rgba(57,255,20,0.4);transform:scale(0.8);transition:transform 0.3s ease;";
          
          modal.innerHTML = `
            <div style="position:relative;width:80px;height:80px;margin:0 auto 20px;">
              <div style="position:absolute;inset:0;border:4px solid rgba(57,255,20,0.2);border-top-color:#39ff14;border-radius:50%;animation:spin 1s linear infinite;"></div>
              <i class="fas fa-cloud-arrow-down" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:30px;color:#39ff14;animation:bounce 1s infinite;"></i>
            </div>
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:600;letter-spacing:0.5px;">Downloading App</h3>
            <p style="margin:0 0 15px;color:#aaa;font-size:13px;line-height:1.5;">Please wait while your application downloads in the background.</p>
            <div style="width:100%;height:6px;background:#333;border-radius:10px;overflow:hidden;">
              <div style="width:0%;height:100%;background:#39ff14;box-shadow:0 0:10px #39ff14;border-radius:10px;animation:fillBar 3.5s ease-in-out forwards;"></div>
            </div>
            <div style="margin-top:10px;color:#39ff14;font-size:11px;font-weight:700;" id="dlStatusNav">Preparing Files...</div>
          `;
          
          const style = document.createElement('style');
          style.textContent = `
            @keyframes spin { 100% { transform: rotate(360deg); } }
            @keyframes bounce { 0%, 100% { transform: translate(-50%, -45%); } 50% { transform: translate(-50%, -60%); } }
            @keyframes fillBar { 0% { width: 0%; } 30% { width: 40%; } 70% { width: 75%; } 100% { width: 100%; } }
          `;
          
          document.head.appendChild(style);
          overlay.appendChild(modal);
          document.body.appendChild(overlay);
          
          setTimeout(() => {
            overlay.style.opacity = '1';
            modal.style.transform = 'scale(1)';
          }, 50);
          
          setTimeout(() => { 
            const statusEl = document.getElementById('dlStatusNav');
            if (statusEl) statusEl.textContent = 'Downloading APK...'; 
          }, 1000);
          
          setTimeout(() => { 
            const statusEl = document.getElementById('dlStatusNav');
            if (statusEl) statusEl.textContent = 'Finishing Download...'; 
          }, 2500);
          
          const downloadIframe = document.createElement('iframe');
          downloadIframe.style.display = 'none';
          downloadIframe.src = 'download.php';
          document.body.appendChild(downloadIframe);
          
          setTimeout(() => {
            overlay.style.opacity = '0';
            modal.style.transform = 'scale(0.8)';
            setTimeout(() => { overlay.remove(); downloadIframe.remove(); }, 300);
          }, 4000);
        }
      });
    }
    
    if (document.readyState !== 'loading') {
      initDownloaderUI();
    } else {
      document.addEventListener('DOMContentLoaded', initDownloaderUI);
    }
})();
</script>



