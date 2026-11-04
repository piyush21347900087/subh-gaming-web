<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Download' => [
        'en' => 'Download', 'hi' => 'डाउनलोड', 'kn' => 'ಡೌನ್‌ಲೋಡ್', 'ml' => 'ഡൗൺലോഡ്', 
        'gu' => 'ડાઉનલોડ', 'mr' => 'डाउनलोड', 'ta' => 'பதிவிறக்க', 'te' => 'డౌన్‌లోడ్'
    ],
    'SUN' => [
";

if (strpos($content, "'Download' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added 'Download' to lang.php.\n";
}
