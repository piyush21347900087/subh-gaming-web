<?php
$prof = file_get_contents("user_profile.php");
$pos = strpos($prof, '.side-menu {');
if ($pos !== false) {
    echo substr($prof, $pos, 800);
} else {
    echo ".side-menu CSS not found";
}
