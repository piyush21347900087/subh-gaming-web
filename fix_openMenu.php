<?php
$files = glob("*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    $modified = false;
    
    if (strpos($content, 'function openMenu(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();') !== false) {
        $content = str_replace('function openMenu(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();', 'function openMenu(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();', $content);
        $modified = true;
    }
    if (strpos($content, 'function openMenu(e)  { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();') !== false) {
        $content = str_replace('function openMenu(e)  { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();', 'function openMenu(e)  { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();', $content);
        $modified = true;
    }
    if (strpos($content, 'onclick="openMenu(event)"') !== false) {
        $content = str_replace('onclick="openMenu(event)"', 'onclick="openMenu(event)"', $content);
        $modified = true;
    }
    
    if ($modified) {
        file_put_contents($file, $content);
        echo "Updated openMenu in $file\n";
    }
}
