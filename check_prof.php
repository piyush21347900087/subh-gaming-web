<?php
$prof = file_get_contents("user_profile.php");

$startProf = strpos($prof, '<div class="side-menu-overlay" id="sideOverlay"');
if ($startProf === false) {
    echo "side-menu-overlay not found\n";
} else {
    // Check what variables it uses for user name/mobile
    echo substr($prof, $startProf, 800);
}
