<?php
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
foreach ($it as $file) {
    if ($file->isDir())
        continue;
    if (strpos($file->getFilename(), '_full_chart.php') !== false) {
        echo $file->getPathname() . "\n";
    }
}
