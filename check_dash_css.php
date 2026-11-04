<?php
$dash = file_get_contents("user_dashboard.php");
$pos = strpos($dash, '.side-menu {');
if ($pos !== false) {
    echo substr($dash, $pos, 1500);
}
