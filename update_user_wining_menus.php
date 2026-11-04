<?php
$ud = file_get_contents('user_dashboard.php');
$uw = file_get_contents('user_wining.php');

// 1. Extract CSS from user_dashboard.php
preg_match('/(header\s*\{.*?)(\/\* 3D Action Buttons \*\/)/is', $ud, $cssMatch);
$ud_css = $cssMatch[1];
// Remove the specific dashboard css at the end of the match if any, up to side menu overlay
// Actually, it's better to just grab specific blocks.
preg_match('/(header\s*\{.*?)\/\* End Jelly Bounce Wrapper removed \*\//is', $ud, $m1);
preg_match('/(\/\* Bottom Menu \(Floating Center Design\).*?)<\/style>/is', $ud, $m2);
$new_css = $m1[1] . "\n" . $m2[1];

// Replace CSS in user_wining.php
// From "header {" to ".menu-item.center-btn.active::before { display: none; }"
$uw = preg_replace('/header\s*\{.*\.menu-item\.center-btn\.active::before\s*\{\s*display:\s*none;\s*\}/is', $new_css, $uw);

// 2. Extract HTML from user_dashboard.php
preg_match('/(<!-- SIDE MENU -->.*?<\/div>\s*<\/div>)/is', $ud, $m3);
preg_match('/(<!-- HEADER -->.*?<\/header>)/is', $ud, $m4);
preg_match('/(<!-- End Jelly Bounce Wrapper removed -->\s*<nav class="bottom-menu">.*?<\/nav>)/is', $ud, $m5);

$side_html = $m3[1];
// adjust active for side menu
$side_html = str_replace('class="side-menu-item active"', 'class="side-menu-item"', $side_html);
$side_html = str_replace('href="user_wining.php" class="side-menu-item"', 'href="user_wining.php" class="side-menu-item active"', $side_html);

$header_html = $m4[1];
$bottom_html = $m5[1];

// Replace HTML in user_wining.php
// Side Menu & Header
$uw = preg_replace('/<div class="side-menu-overlay" id="sideOverlay"><\/div>.*?<\/header>/is', $side_html . "\n" . $header_html, $uw);

// Bottom Menu
$uw = preg_replace('/<nav class="bottom-menu">.*?<\/nav>/is', $bottom_html, $uw);

file_put_contents('user_wining.php', $uw);
echo "Successfully updated user_wining.php\n";
