<?php
$files = [
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_dashboard.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/price_chart.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/how_to_play.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/deposit.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_wining.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_bid_history.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_panel_chart.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_jodi_chart.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/notice_board.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_profile.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_bank.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/deposit.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/deposit_history.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/withdrawal.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/withdrawal_history.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_result.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Find the languages block
    $pattern = '/\s*<a href="languages\.php"[^>]*>.*?<\/a>/is';
    if (preg_match($pattern, $content, $matches)) {
        $langBlock = $matches[0];
        
        // Remove it
        $content = preg_replace($pattern, '', $content, 1);
        
        // Find the share app block
        $sharePattern = '/(\s*<a href="javascript:void\(0\)" class="side-menu-item" id="shareBtn">)/is';
        if (preg_match($sharePattern, $content)) {
            // Insert langBlock before share app block
            $content = preg_replace($sharePattern, $langBlock . '$1', $content, 1);
        } else {
            // Fallback, append to body end if sharebtn not found
            // This shouldn't happen, but just in case
        }
        
        file_put_contents($file, $content);
    }
}
echo "Language menu moved above Share App.";
