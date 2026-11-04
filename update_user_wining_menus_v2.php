<?php
$htp = file_get_contents('how_to_play.php');
$uw = file_get_contents('user_wining.php');

// --- 1. Extract CSS from how_to_play.php ---
preg_match('/(header\s*\{.*?)(\/\* Content Styles \*\/)/is', $htp, $cssMatch);
$new_css = "\n" . $cssMatch[1];

// --- 2. Replace CSS in user_wining.php ---
// Replace everything between `body { ... }` and `.summary-card {`
$uw = preg_replace('/(body\s*\{[^\}]+\})\s*(.*?)\s*(\.summary-card\s*\{)/is', "$1" . $new_css . "\n$3", $uw);

// --- 3. Extract HTML from how_to_play.php ---
preg_match('/(<!-- SIDE MENU -->.*?<\/div>\s*<\/div>\s*<!-- HEADER -->\s*<header>.*?<\/header>)/is', $htp, $htmlMatch);
$new_html = $htmlMatch[1];

// Adjust active state for user_wining.php
$new_html = str_replace('class="side-menu-item active"', 'class="side-menu-item"', $new_html);
$new_html = str_replace('href="user_wining.php" class="side-menu-item"', 'href="user_wining.php" class="side-menu-item active"', $new_html);

// --- 4. Replace HTML in user_wining.php ---
// Replace everything between `<body ...>` and `<div class="content">`
$uw = preg_replace('/(<body[^>]*>)\s*<!-- SIDE MENU -->.*?(<div class="content">)/is', "$1\n    " . $new_html . "\n\n  $2", $uw);

// --- 5. Replace Bottom Menu HTML ---
preg_match('/(<!-- BOTTOM MENU \(Standardized\) -->\s*<nav class="bottom-menu">.*?<\/nav>)/is', $htp, $bottomHtmlMatch);
$bottom_html = $bottomHtmlMatch[1];

// Replace bottom menu in user_wining.php
$uw = preg_replace('/<!-- End Jelly Bounce Wrapper removed -->\s*<nav class="bottom-menu">.*?<\/nav>/is', $bottom_html, $uw);
$uw = preg_replace('/<nav class="bottom-menu">.*?<\/nav>/is', $bottom_html, $uw); // fallback if first fails

file_put_contents('user_wining.php', $uw);
echo "user_wining.php fixed and updated successfully.\n";
