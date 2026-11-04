<?php
require_once 'includes/lang.php';
$files = [
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_panel_chart.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_jodi_chart.php'
];

$replacements = [
    '<th>DATE</th>' => '<th><?= __("DATE") ?></th>',
    '<th>MON</th>' => '<th><?= __("MON") ?></th>',
    '<th>TUE</th>' => '<th><?= __("TUE") ?></th>',
    '<th>WED</th>' => '<th><?= __("WED") ?></th>',
    '<th>THU</th>' => '<th><?= __("THU") ?></th>',
    '<th>FRI</th>' => '<th><?= __("FRI") ?></th>',
    '<th>SAT</th>' => '<th><?= __("SAT") ?></th>',
    '<th class="sun-col">SUN</th>' => '<th class="sun-col"><?= __("SUN") ?></th>'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    file_put_contents($file, $content);
}
echo "Chart days headers updated.";
