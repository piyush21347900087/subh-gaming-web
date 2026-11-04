<?php
$dir = __DIR__ . '/charts';
$files = glob($dir . '/*_full_chart.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Fix overflow-x to auto to allow scrolling instead of cutting off text
    $content = str_replace('overflow-x: hidden;', 'overflow-x: auto;', $content);
    
    // Give table a minimum width so it doesn't squeeze too hard
    $content = str_replace('table-layout: fixed; min-width: 0;', 'table-layout: fixed; min-width: 400px;', $content);
    
    file_put_contents($file, $content);
}

// Also update create_new_market.php
$create = __DIR__ . '/create_new_market.php';
if(file_exists($create)) {
    $c = file_get_contents($create);
    $c = str_replace('overflow-x: hidden;', 'overflow-x: auto;', $c);
    $c = str_replace('table-layout: fixed; min-width: 0;', 'table-layout: fixed; min-width: 400px;', $c);
    file_put_contents($create, $c);
}

// Also update user_panel_chart.php
$user = __DIR__ . '/user_panel_chart.php';
if(file_exists($user)) {
    $u = file_get_contents($user);
    $u = str_replace('overflow-x: hidden;', 'overflow-x: auto;', $u);
    $u = preg_replace('/table \{ min-width: 0; width: 100%; table-layout: fixed; \}/', 'table { min-width: 400px; width: 100%; table-layout: fixed; }', $u);
    file_put_contents($user, $u);
}

echo "Done fixing mobile charts.";
?>
