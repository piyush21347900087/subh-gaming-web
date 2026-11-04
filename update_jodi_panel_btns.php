<?php
require_once 'includes/lang.php';
$files = [
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_panel_chart.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_jodi_chart.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/user_dashboard.php'
];

$replacements = [
    '>JODI<' => '><?= __("JODI") ?><',
    '>PANEL<' => '><?= __("PANEL") ?><'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    file_put_contents($file, $content);
}
echo "JODI and PANEL buttons updated.";
