<?php
/**
 * SUBH GAMING – Shared Header Include
 * ========================================
 * Variables required BEFORE this file is included:
 *   $totalBalance  (float)   – wallet + win balance to display
 *   $userName      (string)  – user's display name (used by nav_side.php)
 *   $code          (string)  – user ID code        (used by nav_side.php)
 */
$_navBalance = isset($totalBalance) ? number_format((float)$totalBalance, 2, '.', '') : '0.00';
?>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- ════════════════ SUBH GAMING HEADER ════════════════ -->
<header>
    <div class="header-left">
        <div class="header-icon-btn" id="menuToggle" style="cursor:pointer;" title="Open Menu">
            <i class="fa-solid fa-bars"></i>
        </div>
        <a href="user_dashboard.php" class="logo-wrap">
            <img src="images/logo.png" alt="Subh Gaming" class="logo-img">
            <span class="subh-brand-badge">SUBH GAMING</span>
        </a>
    </div>
    <div class="header-right">
        <a href="deposit.php" class="wallet-display">
            <i class="fa-solid fa-wallet"></i>
            <span id="navWalletVal">₹<?= htmlspecialchars($_navBalance) ?></span>
        </a>
        <a href="notice_board.php" class="header-icon-btn" id="navNotifyLink" aria-label="Notifications" title="Notifications">
            <i class="fa-solid fa-bell"></i>
            <span class="notify-dot" id="navNotifyDot"></span>
        </a>
    </div>
</header>
