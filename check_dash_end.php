<?php
$dash = file_get_contents("user_dashboard.php");
$startDashCss = strpos($dash, '/* SIDE MENU Standardized');
echo substr($dash, $startDashCss + 3500, 1000);
