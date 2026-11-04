<?php
// This script updates all chart files in the charts/ folder by injecting SEO meta tags into their <head>
    <link rel="icon" type="image/png" href="images/fevicon.png"> section.

$chartsDir = __DIR__ . '/charts';
$files = scandir($chartsDir);

$metaTags = '
  <meta name="description" content="SUBH GAMING Matka Charts - The most trusted online Satta Matka app in India. Get fastest live results for Kalyan, Mumbai, Ghaziabad, Faridabad. Join now for best Satta King tips, charts, and guessing.">
  <meta name="keywords" content="SUBH GAMING Matka, Satta Matka, Kalyan Matka, Satta King, Matka Result, Dpboss, Matka 420, Tara Matka, Indian Satta, Satta Matka App, Online Satta, Matka Play, Kalyan Result, Mumbai Matka, Ghaziabad Satta, Faridabad Satta, SUBH GAMING Satta, Fastest Matka Result, Trusted Satta App">
';

echo "Updating chart files in: $chartsDir\n";

foreach ($files as $file) {
    if ($file === '.' || $file === '..')
        continue;

    // Only process PHP files, specifically chart files if needed, but 'all' was requested.
    // Assuming all files in charts/ are chart pages.
    if (pathinfo($file, PATHINFO_EXTENSION) !== 'php')
        continue;

    $filePath = $chartsDir . '/' . $file;
    $content = file_get_contents($filePath);

    // Check if meta tags already exist to avoid duplication (simple check)
    if (strpos($content, 'name="description"') !== false && strpos($content, 'SUBH GAMING Matka') !== false) {
        echo "Skipping $file (Already updated)\n";
        continue;
    }

    // Pattern to find <head>
    <link rel="icon" type="image/png" href="images/fevicon.png">...<title>...</title>... and insert after title or at start of head
    // Let's try to insert after <title> line if present, otherwise after <head>
    <link rel="icon" type="image/png" href="images/fevicon.png">

    if (preg_match('/(<title>.*?<\/title>)/si', $content, $matches)) {
        $replacement = $matches[1] . "\n" . $metaTags;
        $newContent = str_replace($matches[1], $replacement, $content);

        if (file_put_contents($filePath, $newContent)) {
            echo "Updated: $file\n";
        } else {
            echo "Failed to write: $file\n";
        }
    } elseif (preg_match('/(<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">)/si', $content, $matches)) {
        $replacement = $matches[1] . "\n" . $metaTags;
        $newContent = str_replace($matches[1], $replacement, $content);
        if (file_put_contents($filePath, $newContent)) {
            echo "Updated (after head): $file\n";
        } else {
            echo "Failed to write: $file\n";
        }
    } else {
        echo "Skipping $file (No <title> or <head>
    <link rel="icon" type="image/png" href="images/fevicon.png"> tag found)\n";
    }
}

echo "Done.\n";
