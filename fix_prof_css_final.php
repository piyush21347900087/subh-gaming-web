<?php
$dash = file_get_contents("user_dashboard.php");
$prof = file_get_contents("user_profile.php");

$startDashCss = strpos($dash, '/* SIDE MENU Standardized');
$endDashCss = strpos($dash, '.popup-box {', $startDashCss);
$dashCss = substr($dash, $startDashCss, $endDashCss - $startDashCss);

$startProfCss = strpos($prof, '/* SIDE MENU Standardized');
$endProfCss = strpos($prof, '/* ── BOTTOM MENU', $startProfCss);
if ($endProfCss === false) {
     $endProfCss = strpos($prof, '/* Profile Card */', $startProfCss);
}
$profCss = substr($prof, $startProfCss, $endProfCss - $startProfCss);

if (trim($dashCss) === trim($profCss)) {
    echo "CSS is already perfectly identical!\n";
} else {
    echo "Replacing CSS to make it 100% same...\n";
    $newProf = str_replace($profCss, $dashCss, $prof);
    file_put_contents("user_profile.php", $newProf);
    echo "Replaced CSS.";
}
