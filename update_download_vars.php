<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Download Now' => [
        'en' => 'Download Now', 'hi' => 'अभी डाउनलोड करें', 'kn' => 'ಈಗ ಡೌನ್‌ಲೋಡ್ ಮಾಡಿ', 'ml' => 'ഇപ്പോൾ ഡൗൺലോഡ് ചെയ്യുക', 
        'gu' => 'હવે ડાઉનલોડ કરો', 'mr' => 'आता डाउनलोड करा', 'ta' => 'இப்போது பதிவிறக்கு', 'te' => 'ఇప్పుడే డౌన్‌లోడ్ చేయండి'
    ],
    'DOWNLOAD' => [
        'en' => 'DOWNLOAD', 'hi' => 'डाउनलोड', 'kn' => 'ಡೌನ್‌ಲೋಡ್', 'ml' => 'ഡൗൺലോഡ്', 
        'gu' => 'ડાઉનલોડ', 'mr' => 'डाउनलोड', 'ta' => 'பதிவிறக்க', 'te' => 'డౌన్‌లోడ్'
    ],
    'SUN' => [
";

if (strpos($content, "'Download Now' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added variations of Download to lang.php.\n";
}
