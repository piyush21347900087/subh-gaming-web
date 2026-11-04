<?php
$files = glob("*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    $modified = false;
    
    // Change function signature
    if (strpos($content, 'function openM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();') !== false) {
        $content = str_replace('function openM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();', 'function openM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();', $content);
        $modified = true;
    }
    // Also if it's openM() without space
    if (strpos($content, 'function openM(e){ if(e && e.preventDefault) e.preventDefault();') !== false) {
        $content = str_replace('function openM(e){ if(e && e.preventDefault) e.preventDefault();', 'function openM(e){ if(e && e.preventDefault) e.preventDefault();', $content);
        $modified = true;
    }
    // Change inline onclick calls
    if (strpos($content, 'onclick="openM(event)"') !== false) {
        $content = str_replace('onclick="openM(event)"', 'onclick="openM(event)"', $content);
        $modified = true;
    }
    
    // Check if sideMenu has a href
    // We already replaced side menu in a few files.
    
    if ($modified) {
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
