<?php
$dash = file_get_contents("user_dashboard.php");
$pos = strpos($dash, '.side-menu-item:hover');
echo substr($dash, $pos - 100, 300);
