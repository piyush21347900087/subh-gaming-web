<?php
require_once 'includes/lang.php';
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_dashboard.php';
$content = file_get_contents($file);

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

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

file_put_contents($file, $content);
echo "user_dashboard.php menus translated.";
