<?php
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
    '<span class="label">Home</span>' => '<span class="label"><?= __("Home") ?></span>',
    '<span class="label">Game Rates</span>' => '<span class="label"><?= __("Game Rates") ?></span>',
    '<span class="label">How to Play</span>' => '<span class="label"><?= __("How to Play") ?></span>',
    '<span class="label">My Wallet</span>' => '<span class="label"><?= __("My Wallet") ?></span>',
    '<span class="label">Winning History</span>' => '<span class="label"><?= __("Winning History") ?></span>',
    '<span class="label">Bid History</span>' => '<span class="label"><?= __("Bid History") ?></span>',
    '<span class="label">Panel Chart</span>' => '<span class="label"><?= __("Panel Chart") ?></span>',
    '<span class="label">Latest App</span>' => '<span class="label"><?= __("Latest App") ?></span>',
    '<span class="label">Share App</span>' => '<span class="label"><?= __("Share App") ?></span>',
    '<span class="label">Languages</span>' => '<span class="label"><?= __("Languages") ?></span>',
    '<span class="label">Logout</span>' => '<span class="label"><?= __("Logout") ?></span>',
    
    '<span>My Bids</span>' => '<span><?= __("My Bids") ?></span>',
    '<span>Funds</span>' => '<span><?= __("Funds") ?></span>',
    '<span>HOME</span>' => '<span><?= __("HOME") ?></span>',
    '<span>Bank</span>' => '<span><?= __("Bank") ?></span>',
    '<span>Profile</span>' => '<span><?= __("Profile") ?></span>'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Check for require_once 'includes/lang.php'; at the top, if missing, add it
    if (strpos($content, 'lang.php') === false && strpos($content, '<?php') !== false) {
        $content = preg_replace('/<\?php\s+/', "<?php\nrequire_once 'includes/lang.php';\n", $content, 1);
    }

    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    file_put_contents($file, $content);
}
echo "Menus updated on all sub-pages.";
