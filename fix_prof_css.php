<?php
$dash = file_get_contents("user_dashboard.php");
$prof = file_get_contents("user_profile.php");

preg_match('/\.side-menu-overlay\s*\{.*?\/\*\s*──\s*Content\s*──\s*\*\//s', $dash, $dashCssMatch);
preg_match('/\.side-menu-overlay\s*\{.*?\/\*\s*Profile Card\s*\*\//s', $prof, $profCssMatch);

// It's safer to just extract from .side-menu-overlay up to the next major section comment.
$startDashCss = strpos($dash, '/* SIDE MENU */');
if ($startDashCss === false) $startDashCss = strpos($dash, '/* ── Side Menu ── */');
$endDashCss = strpos($dash, '/* ── Content ── */', $startDashCss);
if ($endDashCss === false) $endDashCss = strpos($dash, '/* CONTENT & FILTERS */', $startDashCss);
if ($endDashCss === false) $endDashCss = strpos($dash, '/* SLIDER */', $startDashCss);

$startProfCss = strpos($prof, '/* ── Side Menu ── */');
if ($startProfCss === false) $startProfCss = strpos($prof, '/* SIDE MENU */');
if ($startProfCss === false) $startProfCss = strpos($prof, '.side-menu-overlay');

$endProfCss = strpos($prof, '/* Profile Card */', $startProfCss);
if ($endProfCss === false) $endProfCss = strpos($prof, '/* ── Content ── */', $startProfCss);

$dashCss = substr($dash, $startDashCss, $endDashCss - $startDashCss);
$profCss = substr($prof, $startProfCss, $endProfCss - $startProfCss);

if ($dashCss && $profCss) {
    if (trim($dashCss) === trim($profCss)) {
        echo "CSS is exactly the same.\n";
    } else {
        echo "CSS differs! Replacing...\n";
        $newProf = str_replace($profCss, $dashCss, $prof);
        file_put_contents("user_profile.php", $newProf);
        echo "Replaced CSS in user_profile.php\n";
    }
} else {
    echo "Could not extract CSS blocks.\n";
}
