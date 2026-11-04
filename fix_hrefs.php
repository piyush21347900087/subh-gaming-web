<?php
$files = glob("*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    $modified = false;
    
    if (strpos($content, '<a href="javascript:void(0);" class="side-menu-item" id="shareBtn">') !== false) {
        $content = str_replace('<a href="javascript:void(0);" class="side-menu-item" id="shareBtn">', '<a href="javascript:void(0);" class="side-menu-item" id="shareBtn">', $content);
        $modified = true;
    }
    if (strpos($content, '<a href="javascript:void(0);" class="side-menu-item">') !== false) {
        $content = str_replace('<a href="javascript:void(0);" class="side-menu-item">', '<a href="javascript:void(0);" class="side-menu-item">', $content);
        $modified = true;
    }
    
    // Also, ensure menu buttons using <a href="#"> are converted
    if (strpos($content, '<a href="javascript:void(0);" id="menuToggle"') !== false) {
        $content = str_replace('<a href="javascript:void(0);" id="menuToggle"', '<a href="javascript:void(0);" id="menuToggle"', $content);
        $modified = true;
    }
    
    if ($modified) {
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
