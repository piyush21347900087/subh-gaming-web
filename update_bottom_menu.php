<?php
$ud = file_get_contents('user_dashboard.php');
$htp = file_get_contents('how_to_play.php');

// 1. Extract CSS
if (preg_match('/(\/\* Bottom Menu \(Floating Center Design\).*?)<\/style>/is', $ud, $cssMatch)) {
    $ud_css = $cssMatch[1];
    
    // Replace in how_to_play.php
    $htp = preg_replace('/(\/\* BOTTOM MENU \(Synced\).*?)<\/style>/is', $ud_css . "\n</style>", $htp);
}

// 2. Extract HTML
if (preg_match('/(<!-- End Jelly Bounce Wrapper removed -->\s*<nav class="bottom-menu">.*?<\/nav>)/is', $ud, $htmlMatch)) {
    $ud_html = $htmlMatch[1];
    
    // Remove "active" from user_dashboard HOME in how_to_play if desired, but user said "same user_dashboard k bottom menu k jaisa karo", 
    // so let's keep it EXACTLY the same, active state and all.
    $htp = preg_replace('/(<!-- BOTTOM MENU \(Standardized\) -->\s*<nav class="bottom-menu">.*?<\/nav>)/is', $ud_html, $htp);
}

file_put_contents('how_to_play.php', $htp);
echo "Bottom menu updated in how_to_play.php";
