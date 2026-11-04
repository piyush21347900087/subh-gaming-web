<?php
require_once 'includes/lang.php';
$files = [
    'd:/SUBH GAMING/SUBH GAMING Web Code/price_chart.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/how_to_play.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/deposit.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_wining.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_bid_history.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_panel_chart.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/notice_board.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_profile.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_bank.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/deposit.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/deposit_history.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/withdrawal.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/withdrawal_history.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_result.php'
];

$replacements = [
    '<span>Home</span>' => '<span><?= __("Home") ?></span>',
    '<span>Game Rates</span>' => '<span><?= __("Game Rates") ?></span>',
    '<span>How to Play</span>' => '<span><?= __("How to Play") ?></span>',
    '<span>My Wallet</span>' => '<span><?= __("My Wallet") ?></span>',
    '<span>Winning History</span>' => '<span><?= __("Winning History") ?></span>',
    '<span>Bid History</span>' => '<span><?= __("Bid History") ?></span>',
    '<span>Panel Chart</span>' => '<span><?= __("Panel Chart") ?></span>',
    '<span>Latest App</span>' => '<span><?= __("Latest App") ?></span>',
    '<span>Share App</span>' => '<span><?= __("Share App") ?></span>',
    '<span>Languages</span>' => '<span><?= __("Languages") ?></span>',
    '<span>Logout</span>' => '<span><?= __("Logout") ?></span>'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    file_put_contents($file, $content);
}
echo "Menus without label class updated.";
