<?php
$prof = file_get_contents("user_profile.php");
$startProfCss = strpos($prof, '/* SIDE MENU Standardized');
echo substr($prof, $startProfCss + 2500, 1000);
