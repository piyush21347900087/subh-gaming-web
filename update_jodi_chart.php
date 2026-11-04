<?php
$files = [
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_jodi_chart.php'
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
    
    if (strpos($content, 'lang.php') === false && strpos($content, '<?php') !== false) {
        $content = preg_replace('/<\?php\s+/', "<?php\nrequire_once 'includes/lang.php';\n", $content, 1);
    }
    
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    file_put_contents($file, $content);
}
echo "user_jodi_chart.php menus updated.";
