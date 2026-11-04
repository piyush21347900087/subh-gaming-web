<?php
/**
 * SUBH GAMING – Shared Bottom Navigation
 * ===================================================
 * Ultra-Modern Floating Dock with Glassmorphism and Neon Glow
 */

$_navPage = basename($_SERVER['PHP_SELF']);
function _nbActive(string $page): string { global $_navPage; return $_navPage === $page ? ' active' : ''; }
?>
<style>
nav.bottom-menu {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 64px;
    background: rgba(11, 17, 32, 0.94);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    display: flex;
    justify-content: space-around;
    align-items: center;
    z-index: 2000;
    box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.6), 0 -1px 0 rgba(139, 92, 246, 0.2);
    padding: 0 10px;
    border-top: 1px solid rgba(139, 92, 246, 0.25);
}

.menu-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    text-decoration: none;
    flex: 1;
    height: 100%;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.menu-item i {
    font-size: 20px !important;
    color: #94a3b8;
    margin-bottom: 3px;
    transition: all 0.22s ease;
}

.menu-item span {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.2px;
    color: #94a3b8;
    transition: all 0.22s ease;
}

/* Center Home button specific styles */
.menu-item.home-item {
    position: relative;
    top: -14px;
    flex: 1.15;
}

.menu-item.home-item i {
    width: 56px !important;
    height: 56px !important;
    background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 50%, #06b6d4 100%) !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #ffffff !important;
    font-size: 24px !important;
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.5), inset 0 2px 4px rgba(255, 255, 255, 0.4) !important;
    border: 3px solid #070b14 !important;
    margin-bottom: 0 !important;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.menu-item.home-item:hover i, .menu-item.home-item:active i {
    transform: scale(1.08) translateY(-2px);
    box-shadow: 0 12px 30px rgba(139, 92, 246, 0.7), inset 0 2px 4px rgba(255, 255, 255, 0.6) !important;
}

.menu-item.home-item span {
    display: none;
}

/* Active states */
.menu-item.active i {
    color: #a78bfa !important;
    transform: translateY(-2px);
    filter: drop-shadow(0 0 10px rgba(139, 92, 246, 0.8));
}

.menu-item.active span {
    color: #ffffff !important;
    font-weight: 700;
}

.menu-item.active::after {
    content: '';
    position: absolute;
    bottom: 6px;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: #8b5cf6;
    box-shadow: 0 0 8px #8b5cf6;
}

.menu-item.home-item.active::after {
    display: none;
}
</style>

<nav class="bottom-menu">
    <a href="user_result.php" class="menu-item<?= _nbActive('user_result.php') ?>">
        <i class="fa-solid fa-trophy"></i>
        <span>My Bids</span>
    </a>
    <a href="deposit.php" class="menu-item<?= _nbActive('deposit.php') ?>">
        <i class="fa-solid fa-wallet"></i>
        <span>Funds</span>
    </a>
    <a href="user_dashboard.php" class="menu-item home-item<?= _nbActive('user_dashboard.php') ?>">
        <i class="fa-solid fa-house"></i>
        <span>HOME</span>
    </a>
    <a href="user_bank.php" class="menu-item<?= _nbActive('user_bank.php') ?>">
        <i class="fa-solid fa-building-columns"></i>
        <span>Bank</span>
    </a>
    <a href="live_chat.php" class="menu-item<?= _nbActive('live_chat.php') ?>">
        <i class="fa-solid fa-headset"></i>
        <span>Support</span>
    </a>
</nav>
