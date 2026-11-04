<?php
$prof = file_get_contents("user_profile.php");
$startProfCss = strpos($prof, '.side-menu-overlay');
echo substr($prof, $startProfCss - 100, 1000);
