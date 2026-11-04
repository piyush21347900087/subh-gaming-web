<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/nav_side.php';
$content = file_get_contents($file);

// Add the include for lang.php at the top
if (strpos($content, 'lang.php') === false) {
    $content = str_replace('<?php', "<?php\nrequire_once __DIR__ . '/lang.php';", $content);
}

// Array of replacements for exact HTML blocks
$replacements = [
    '<span>Home</span>' => '<span><?= __("Home") ?></span>',
    '<span>My Profile</span>' => '<span><?= __("My Profile") ?></span>',
    '<span>How to Play</span>' => '<span><?= __("How to Play") ?></span>',
    '<span>Notice Board</span>' => '<span><?= __("Notice Board") ?></span>',
    '<span>Latest App</span>' => '<span><?= __("Latest App") ?></span>',
    '<span>Game Rates</span>' => '<span><?= __("Game Rates") ?></span>',
    '<span>Transaction History</span>' => '<span><?= __("Transaction History") ?></span>',
    '<span>Wining History</span>' => '<span><?= __("Wining History") ?></span>',
    '<span>Bid History</span>' => '<span><?= __("Bid History") ?></span>',
    '<span>Panel Chart</span>' => '<span><?= __("Panel Chart") ?></span>',
    '<span>Languages</span>' => '<span><?= __("Languages") ?></span>',
    '<span>Logout</span>' => '<span><?= __("Logout") ?></span>'
];

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

file_put_contents($file, $content);
echo "nav_side.php translated.";
