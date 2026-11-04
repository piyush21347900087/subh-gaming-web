<?php
$dir = 'd:/SUBH GAMING/SUBH GAMING Web Code';
$files = glob($dir . '/*.php');

$insertNavSide = '        <a href="languages.php" class="side-menu-item<?= _navActive(\'languages.php\') ?>">
            <i class="fa-solid fa-language"></i><span class="label">Languages</span>
        </a>';

$insertHardcoded = '        <a href="languages.php" class="side-menu-item">
            <i class="fa-solid fa-language"></i><span class="label">Languages</span>
        </a>';

$count = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'class="side-menu-body"') !== false) {
        if (strpos($content, 'languages.php') === false) {
            
            // Check if it's nav_side.php
            if (basename($file) === 'nav_side.php') {
                $replacement = '<div class="side-menu-body">' . "\n" . $insertNavSide;
            } else {
                $replacement = '<div class="side-menu-body">' . "\n" . $insertHardcoded;
            }
            
            $content = str_replace('<div class="side-menu-body">', $replacement, $content);
            file_put_contents($file, $content);
            echo "Updated " . basename($file) . "\n";
            $count++;
        }
    }
}

// Also check includes/nav_side.php
$navSideFile = $dir . '/includes/nav_side.php';
if (file_exists($navSideFile)) {
    $content = file_get_contents($navSideFile);
    if (strpos($content, 'class="side-menu-body"') !== false && strpos($content, 'languages.php') === false) {
        $replacement = '<div class="side-menu-body">' . "\n" . $insertNavSide;
        $content = str_replace('<div class="side-menu-body">', $replacement, $content);
        file_put_contents($navSideFile, $content);
        echo "Updated includes/nav_side.php\n";
        $count++;
    }
}

echo "Total updated: $count\n";
