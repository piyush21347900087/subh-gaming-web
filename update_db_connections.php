<?php
// update_db_connections.php
// Script to update all database connections to use includes/db.php

$rootDir = __DIR__;
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootDir),
    RecursiveIteratorIterator::SELF_FIRST
);

$phpFiles = [];
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        // Skip vendor, node_modules, etc
        if (
            strpos($path, 'vendor') === false &&
            strpos($path, 'node_modules') === false &&
            strpos($path, 'PHPMailer') === false
        ) {
            $phpFiles[] = $path;
        }
    }
}

$oldPattern1 = "new mysqli('localhost','u243882542_root_shreeji','xWS#EcOvv9$','u243882542_shreeji')";
$oldPattern2 = 'new mysqli("localhost","u243882542_root_shreeji","xWS#EcOvv9$","u243882542_shreeji")';
$oldPattern3 = "new mysqli('localhost', 'u243882542_root_shreeji', 'xWS#EcOvv9$', 'u243882542_shreeji')";

$updated = 0;
$log = [];

foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    $originalContent = $content;

    // Check if file has old connection
    if (
        strpos($content, 'new mysqli') !== false &&
        (strpos($content, 'u243882542') !== false || strpos($content, 'u215500541') !== false)
    ) {

        // Skip if already using includes/db.php
        if (
            strpos($content, "require_once 'includes/db.php'") !== false ||
            strpos($content, 'require_once "includes/db.php"') !== false
        ) {
            $log[] = "SKIP (already updated): $file";
            continue;
        }

        $log[] = "UPDATING: $file";
        $updated++;
    }
}

file_put_contents($rootDir . '/db_update_log.txt', implode("\n", $log));
echo "Found " . count($phpFiles) . " PHP files\n";
echo "Need updating: $updated files\n";
echo "Check db_update_log.txt for details\n";


