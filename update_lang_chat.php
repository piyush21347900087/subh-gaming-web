<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$newTranslations = "    'Support' => [
        'en' => 'Support', 'hi' => 'सपोर्ट', 'kn' => 'ಬೆಂಬಲ', 'ml' => 'പിന്തുണ', 
        'gu' => 'સપોર્ટ', 'mr' => 'समर्थन', 'ta' => 'ஆதரவு', 'te' => 'మద్దతు'
    ],
    'Online' => [
        'en' => 'Online', 'hi' => 'ऑनलाइन', 'kn' => 'ಆನ್‌ಲೈನ್', 'ml' => 'ഓൺ‌ലൈൻ', 
        'gu' => 'ઓનલાઈન', 'mr' => 'ऑनलाइन', 'ta' => 'ஆன்லைன்', 'te' => 'ఆన్లైన్'
    ],
    'Offline' => [
        'en' => 'Offline', 'hi' => 'ऑफलाइन', 'kn' => 'ಆಫ್‌ಲೈನ್', 'ml' => 'ഓഫ്‌ലൈൻ', 
        'gu' => 'ઓફલાઈન', 'mr' => 'ऑफलाइन', 'ta' => 'ஆஃப்லைன்', 'te' => 'ఆఫ్లైన్'
    ],
    'SUN' => [
";

$content = str_replace("    'SUN' => [
", $newTranslations, $content);

file_put_contents($file, $content);
echo "Added Support, Online, Offline to lang.php.";
