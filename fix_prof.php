<?php
$dash = file_get_contents("user_dashboard.php");
$prof = file_get_contents("user_profile.php");

// Dashboard side menu HTML
$startDash = strpos($dash, '<div class="side-menu-overlay" id="sideOverlay"');
$footerPos = strpos($dash, '<div class="side-menu-footer">', $startDash);
$endDash = strpos($dash, '</div>', $footerPos);
$endDash = strpos($dash, '</div>', $endDash + 6) + 6;
$dashMenuHtml = substr($dash, $startDash, $endDash - $startDash);

// Profile side menu HTML
$startProf = strpos($prof, '<div class="side-menu-overlay" id="sideOverlay"');
if ($startProf !== false) {
    $footerPosProf = strpos($prof, '<div class="side-menu-footer">', $startProf);
    if ($footerPosProf !== false) {
        $endProf = strpos($prof, '</div>', $footerPosProf);
        $endProf = strpos($prof, '</div>', $endProf + 6) + 6;
        $profMenuHtml = substr($prof, $startProf, $endProf - $startProf);
        
        $newProf = str_replace($profMenuHtml, $dashMenuHtml, $prof);
        file_put_contents("user_profile.php", $newProf);
        echo "Successfully replaced side menu in user_profile.php\n";
    } else {
        echo "Footer not found in user_profile.php side menu\n";
    }
} else {
    echo "side-menu-overlay not found in user_profile.php\n";
}
