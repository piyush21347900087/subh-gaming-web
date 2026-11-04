<?php
$files = glob("*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    $modified = false;
    
    // Add setSwipeEnabled(false) in openM and openMenu
    if (strpos($content, 'if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled)') === false) {
        $content = str_replace('function openM(e) {', 'function openM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); }', $content);
        $content = str_replace('function openMenu(e)  {', 'function openMenu(e)  { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); }', $content);
        $content = str_replace('function openMenu(e) {', 'function openMenu(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); }', $content);
        
        $content = str_replace('function closeM(e) {', 'function closeM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(true); }', $content);
        $content = str_replace('function closeMenu() {', 'function closeMenu() { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(true); }', $content);
        $modified = true;
    }
    
    if ($modified) {
        file_put_contents($file, $content);
        echo "Updated JS in $file\n";
    }
}
