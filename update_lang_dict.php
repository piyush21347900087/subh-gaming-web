<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$newTranslations = "    'Choose Language' => [
        'en' => 'Choose Language', 'hi' => 'भाषा चुनें', 'kn' => 'ಭಾಷೆಯನ್ನು ಆರಿಸಿ', 'ml' => 'ഭാഷ തിരഞ്ഞെടുക്കുക', 
        'gu' => 'ભાષા પસંદ કરો', 'mr' => 'भाषा निवडा', 'ta' => 'மொழியைத் தேர்ந்தெடுக்கவும்', 'te' => 'భాషను ఎంచుకోండి'
    ],
    'CONTINUE' => [
        'en' => 'CONTINUE', 'hi' => 'जारी रखें', 'kn' => 'ಮುಂದುವರಿಸಿ', 'ml' => 'തുടരുക', 
        'gu' => 'ચાલુ રાખો', 'mr' => 'पुढे जा', 'ta' => 'தொடரவும்', 'te' => 'కొనసాగించండి'
    ]
];";

$content = str_replace('];', $newTranslations, $content);

file_put_contents($file, $content);
echo "lang.php updated with new translations.";
