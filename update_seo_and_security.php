<?php
/**
 * SUBH GAMING SEO & Security Bulk Update Script
 * -----------------------------------------
 * This script automates:
 * 1. Injecting SEO meta keywords & description (BHAR BHAR KE!)
 * 2. Enabling India-only traffic restriction (GEO-BLOCKING)
 */

// 1. Define Massive Matka Keywords/Description
$seoDescription = "SUBH GAMING Matka - India's most trusted Satta Matka platform. Get the fastest live results for Kalyan, Mumbai, Milan, and Rajdhani. Access the best Matka charts, expert guessing, and Satta King tips. Join the #1 Matka app for reliable and quick updates. Online Matka Play made easy and secure for all users in India.";
$seoKeywords = "SUBH GAMING matka, satta matka, kalyan matka, kalyan panel chart, kalyan jodi chart, milan day chart, rajdhani night chart, satta king, dpboss, matka guessing, online matka play, indian matka, satta result, matka 420, tara matka, fix matka, satta matka 143, kalyan result, mumbai matka, gali desawar, fast matka result, kalyan chart, satta matka market, matka boss, satta batta, kalyan open to close, satta king result, matka gambling, matka prediction, matka number, satta matka net, matka world, satta matka live, matka online result, satta bazaar, kalyan result today, mumbai result today, matka game, satta king tips, best matka guessing, matka app download";

$metaTags = "\r\n    <!-- SUBH GAMING SEO BHAIYA - BHAR BHAR KE KEYWORDS -->\r\n    <meta name='description' content='" . htmlspecialchars($seoDescription) . "'>\r\n    <meta name='keywords' content='" . htmlspecialchars($seoKeywords) . "'>\r\n";

// 2. Define Security Inclusions
$securityInclusionRoot = "<?php\r\n// SUBH GAMING Security Check - India Only\r\nrequire_once __DIR__ . '/includes/security.php';\r\n";
$securityInclusionCharts = "<?php\r\n// SUBH GAMING Security Check - India Only\r\nrequire_once __DIR__ . '/../includes/security.php';\r\n";

// 3. Update Charts Folder
$chartsDir = __DIR__ . '/charts';
$chartFiles = scandir($chartsDir);

echo "--- PROCESSING CHARTS FOLDER ---\n";
foreach ($chartFiles as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $filePath = $chartsDir . '/' . $file;
        $content = file_get_contents($filePath);
        
        // 3a. Inject Security (at the very top)
        if (strpos($content, 'security.php') === false) {
            $content = preg_replace('/^<\?php/i', $securityInclusionCharts, $content);
        }

        // 3b. Inject SEO (in <head>
    <link rel="icon" type="image/png" href="images/fevicon.png">)
        if (strpos($content, 'meta name=\'keywords\'') === false) {
             // Find <head>
    <link rel="icon" type="image/png" href="images/fevicon.png"> or <title>
             if (preg_match('/(<title>.*?<\/title>)/si', $content, $matches)) {
                 $content = str_replace($matches[1], $matches[1] . $metaTags, $content);
             } elseif (preg_match('/(<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">)/si', $content, $matches)) {
                 $content = str_replace($matches[1], $matches[1] . $metaTags, $content);
             }
        }

        file_put_contents($filePath, $content);
        echo "Updated: charts/$file\n";
    }
}

// 4. Update Main Files (Root)
$rootFiles = ['index.php', 'kalyan.php', 'kalyan_morning.php', 'kalyan_night.php', 'karnataka_day.php', 'karnataka_night.php', 'madhur_day.php', 'madhur_morning.php', 'madhur_night.php', 'main_bazar.php', 'milan_day.php', 'milan_morning.php', 'milan_night.php', 'rajdhani_day.php', 'rajdhani_night.php', 'sridevi.php', 'sridevi_night.php', 'supreme_day.php', 'supreme_night.php', 'time_bazar.php', 'landing.php'];

echo "\n--- PROCESSING ROOT FILES ---\n";
foreach ($rootFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $filePath = __DIR__ . '/' . $file;
        $content = file_get_contents($filePath);
        
        // 4a. Inject Security
        if (strpos($content, 'security.php') === false) {
            $content = preg_replace('/^<\?php/i', $securityInclusionRoot, $content);
        }

        // 4b. Inject SEO
        if (strpos($content, 'meta name=\'keywords\'') === false) {
             if (preg_match('/(<title>.*?<\/title>)/si', $content, $matches)) {
                 $content = str_replace($matches[1], $matches[1] . $metaTags, $content);
             } elseif (preg_match('/(<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">)/si', $content, $matches)) {
                 $content = str_replace($matches[1], $matches[1] . $metaTags, $content);
             }
        }

        file_put_contents($filePath, $content);
        echo "Updated: $file\n";
    }
}

echo "\nUpdate Complete! Metka Satta keywords added bhar bhar के, and India-only traffic enabled.";
