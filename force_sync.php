<?php
$ud = file_get_contents("user_dashboard.php");
$htp = file_get_contents("how_to_play.php");

// 1. Copy exact side-menu CSS block
if (preg_match('/(\/\* SIDE MENU TOGGLE \*\/.*?)(?=\/\* SHARE \*\/|var shareBtn)/s', $ud, $m_js_ud)) {
    // maybe javascript is different? We will ignore JS for now, just CSS and HTML.
}

if (preg_match('/(\.side-menu-overlay\s*\{.*?\})(?=\s*\.content|\s*\/\* Content Styles \*\/|\s*<!--)/s', $ud, $css_match)) {
    $ud_css = $css_match[1];
    
    // Replace in how_to_play
    $htp = preg_replace('/(\.side-menu-overlay\s*\{.*?\})(?=\s*\/\* Content Styles \*\/|\s*\.content)/s', $ud_css, $htp);
}

// 2. Copy exact HTML
if (preg_match('/(<!-- SIDE MENU -->\s*<div class="side-menu-overlay" id="sideOverlay"><\/div>.*?<\/div>\s*<\/div>\s*)(?=<!-- End Jelly|<nav class="bottom-menu">)/s', $ud, $html_match)) {
    $ud_html = $html_match[1];
    
    // Make How to play active
    $ud_html = str_replace('<a href="user_dashboard.php" class="side-menu-item active">', '<a href="user_dashboard.php" class="side-menu-item">', $ud_html);
    $ud_html = str_replace('<a href="how_to_play.php" class="side-menu-item">', '<a href="how_to_play.php" class="side-menu-item active">', $ud_html);

    // Replace in how_to_play
    $htp = preg_replace('/<!-- SIDE MENU -->\s*<div class="side-menu-overlay" id="sideOverlay"><\/div>.*?<\/div>\s*<\/div>\s*(?=<!-- BOTTOM MENU|<nav class="bottom-menu">)/s', $ud_html, $htp);
}

file_put_contents("how_to_play.php", $htp);
echo "Done";
