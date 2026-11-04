<?php
$dash = file_get_contents("user_dashboard.php");
$startDashCss = strpos($dash, '.side-menu-overlay');
echo substr($dash, $startDashCss - 100, 1000);
