<?php
// update_android_branding.php
// Updates Android project to SUBH GAMING

$rootDir = dirname(__DIR__) . '/app/src/main';
$updated = [];

function replaceAndroidBranding($content)
{
    $original = $content;

    $replacements = [
        'https://subhgaming.online' => 'https://subhgaming.online',
        'https://subhgaming.online' => 'https://subhgaming.online',
        'subhgaming.online' => 'subhgaming.online',
        'SUBH GAMING' => 'SUBH GAMING',
        'SUBH GAMING' => 'SUBH GAMING',
        'SUBH GAMING' => 'SUBH GAMING',
        'all_dpboss' => 'all_SUBHGAMING', 
        'all_BABAJI' => 'all_SUBHGAMING', 
    ];

    foreach ($replacements as $old => $new) {
        $content = str_replace($old, $new, $content);
    }

    return $content !== $original ? $content : false;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile())
        continue;

    $ext = $file->getExtension();
    // Process java and xml files
    if (!in_array($ext, ['java', 'xml']))
        continue;

    $path = $file->getPathname();

    $content = file_get_contents($path);
    $newContent = replaceAndroidBranding($content);

    if ($newContent !== false) {
        file_put_contents($path, $newContent);
        $updated[] = basename($path);
        echo "✓ " . basename($path) . "\n";
    }
}

echo "Total updated: " . count($updated) . "\n";









