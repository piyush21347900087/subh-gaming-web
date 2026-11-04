<?php
require_once 'includes/lang.php';
$files = [
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_dashboard.php'
];

$replacements = [
    '<span>Add Points</span>' => '<span><?= __("Add Points") ?></span>',
    '<span>Withdrawal</span>' => '<span><?= __("Withdrawal") ?></span>',
    '<span>Live Support</span>' => '<span><?= __("Live Support") ?></span>',
    '<span>CHART</span>' => '<span><?= __("CHART") ?></span>',
    '<span>PLAY</span><span>NOW</span>' => '<span><?= __("PLAY") ?></span><span><?= __("NOW") ?></span>',
    '<span>MARKET</span><span>CLOSED</span>' => '<span><?= __("MARKET") ?></span><span><?= __("CLOSED") ?></span>',
    '<span>MARKET</span><span>HOLIDAY</span>' => '<span><?= __("MARKET") ?></span><span><?= __("HOLIDAY") ?></span>',
    '>RUNNING</div>' => '><?= __("RUNNING") ?></div>',
    '>CLOSED</div>' => '><?= __("CLOSED") ?></div>',
    "statusText.innerText = 'CLOSED';" => "statusText.innerText = '<?= __(\"CLOSED\") ?>';",
    "btn.innerHTML = '<span>MARKET</span><span>CLOSED</span>';" => "btn.innerHTML = '<span><?= __(\"MARKET\") ?></span><span><?= __(\"CLOSED\") ?></span>';",
    "btn.innerHTML = '<span>PLAY</span><span>NOW</span>';" => "btn.innerHTML = '<span><?= __(\"PLAY\") ?></span><span><?= __(\"NOW\") ?></span>';"
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    file_put_contents($file, $content);
}
echo "Dashboard elements translated.";
