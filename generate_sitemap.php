<?php
ini_set('display_errors', 1);
require_once 'includes/db.php';

/* Helper to clean slugs (V5 logic) */
function slug_variants(string $marketName): array
{
    $name = strtolower(trim($marketName));
    $slug = preg_replace('/[^a-z0-9]+/', '_', $name);
    $slug = trim($slug, '_');
    return ($slug === '') ? [] : [$slug];
}

$baseUrl = 'https://subhgaming.online';
$mainFiles = [
    'index.php',
    'user_login.php',
    'user_register.php',
    'user_forgot_password.php',
    'charts.php',
    'privacy_policy.php',
    'landing.php'
];

/* 1. Add Main Files */
$urls = [];
foreach ($mainFiles as $f) {
    if (file_exists(__DIR__ . '/' . $f)) {
        $urls[] = [
            'loc' => $baseUrl . '/' . $f,
            'lastmod' => date('Y-m-d', filemtime(__DIR__ . '/' . $f)),
            'priority' => '1.0'
        ];
    }
}

/* 2. Add Charts from charts folder */
$chartsDir = __DIR__ . '/charts';
if (is_dir($chartsDir)) {
    $files = scandir($chartsDir);
    foreach ($files as $f) {
        // Check if file is a PHP file (specifically looking for chart files)
        // Adjust the condition if you want ALL files, but usually sitemaps list specific pages.
        // Based on file list, they all end in _full_chart.php
        if ($f !== '.' && $f !== '..' && strpos($f, '_full_chart.php') !== false) {
            $urls[] = [
                'loc' => $baseUrl . '/charts/' . $f,
                'lastmod' => date('Y-m-d', filemtime($chartsDir . '/' . $f)),
                'priority' => '0.8'
            ];
        }
    }
}

/* 3. Generate XML */
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($urls as $u) {
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($u['loc']) . "</loc>\n";
    $xml .= "    <lastmod>" . $u['lastmod'] . "</lastmod>\n";
    $xml .= "    <changefreq>daily</changefreq>\n";
    $xml .= "    <priority>" . $u['priority'] . "</priority>\n";
    $xml .= "  </url>\n";
}

$xml .= '</urlset>';

if (file_put_contents(__DIR__ . '/sitemap.xml', $xml)) {
    echo "Sitemap generated successfully! <a href='sitemap.xml'>View Sitemap</a>";
} else {
    echo "Error writing sitemap.xml. Check permissions.";
}
