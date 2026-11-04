<?php
$files = glob("*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    $modified = false;
    
    if (strpos($content, 'function closeM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(true); } if(e && e.preventDefault) e.preventDefault();') !== false) {
        $content = str_replace('function closeM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(true); } if(e && e.preventDefault) e.preventDefault();', 'function closeM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(true); } if(e && e.preventDefault) e.preventDefault();', $content);
        $modified = true;
    }
    if (strpos($content, 'onclick="closeM(event)"') !== false) {
        $content = str_replace('onclick="closeM(event)"', 'onclick="closeM(event)"', $content);
        $modified = true;
    }
    
    if ($modified) {
        file_put_contents($file, $content);
        echo "Updated closeM in $file\n";
    }
}
