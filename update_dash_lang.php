<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_dashboard.php';
$content = file_get_contents($file);

if (strpos($content, 'lang.php') === false) {
    $content = str_replace("require_once 'includes/db.php';", "require_once 'includes/db.php';\nrequire_once 'includes/lang.php';", $content);
}

$replacements = [
    '<p>Wallet Balance</p>' => '<p><?= __("Wallet Balance") ?></p>',
    '<span>Total Deposit</span>' => '<span><?= __("Total Deposit") ?></span>',
    '<span>Total Withdrawal</span>' => '<span><?= __("Total Withdrawal") ?></span>',
    '<span>Total Wining</span>' => '<span><?= __("Total Wining") ?></span>',
    '<span>Deposit</span>' => '<span><?= __("Deposit") ?></span>',
    '<span>Withdraw</span>' => '<span><?= __("Withdraw") ?></span>',
    '<span>Result History</span>' => '<span><?= __("Result History") ?></span>',
    '<span>Play Game</span>' => '<span><?= __("Play Game") ?></span>',
    'Open:</span>' => '<?= __("Open") ?>:</span>',
    'Close:</span>' => '<?= __("Close") ?>:</span>',
    '<a href="user_jodi_chart.php?name=<?= urlencode($name) ?>" class="chart-btn">JODI CHART</a>' => '<a href="user_jodi_chart.php?name=<?= urlencode($name) ?>" class="chart-btn"><?= __("JODI CHART") ?></a>',
    '<a href="user_panel_chart.php?name=<?= urlencode($name) ?>" class="chart-btn">PANEL CHART</a>' => '<a href="user_panel_chart.php?name=<?= urlencode($name) ?>" class="chart-btn"><?= __("PANEL CHART") ?></a>',
    '<div class="section-title">Live Market Rate</div>' => '<div class="section-title"><?= __("Live Market Rate") ?></div>'
];

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

file_put_contents($file, $content);
echo "user_dashboard.php translated.";
