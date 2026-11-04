<?php
$dash = file_get_contents("user_dashboard.php");
$startDash = strpos($dash, '<div class="side-menu-overlay" id="sideOverlay"');
$footerPos = strpos($dash, '<div class="side-menu-footer">', $startDash);
$endDash = strpos($dash, '</div>', $footerPos);
$endDash = strpos($dash, '</div>', $endDash + 6) + 6;

$dashMenuHtml = substr($dash, $startDash, $endDash - $startDash);
echo $dashMenuHtml;
