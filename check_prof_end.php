<?php
$dash = file_get_contents("user_dashboard.php");
$prof = file_get_contents("user_profile.php");

$startDashCss = strpos($dash, '/* SIDE MENU Standardized');
$endDashCss = strpos($dash, '/* SLIDER */', $startDashCss);

$startProfCss = strpos($prof, '/* SIDE MENU Standardized');
// what's after side menu in profile?
echo substr($prof, $startProfCss + 1500, 1000);
