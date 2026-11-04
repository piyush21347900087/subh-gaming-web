<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$newTranslations = "    'DATE' => [
        'en' => 'DATE', 'hi' => 'तारीख', 'kn' => 'ದಿನಾಂಕ', 'ml' => 'തീയതി', 
        'gu' => 'તારીખ', 'mr' => 'तारीख', 'ta' => 'தேதி', 'te' => 'తేదీ'
    ],
    'MON' => [
        'en' => 'MON', 'hi' => 'सोम', 'kn' => 'ಸೋಮ', 'ml' => 'തിങ്കൾ', 
        'gu' => 'સોમ', 'mr' => 'सोम', 'ta' => 'திங்கள்', 'te' => 'సోమ'
    ],
    'TUE' => [
        'en' => 'TUE', 'hi' => 'मंगल', 'kn' => 'ಮಂಗಳ', 'ml' => 'ചൊവ്വ', 
        'gu' => 'મંગળ', 'mr' => 'मंगळ', 'ta' => 'செவ்வாய்', 'te' => 'మంగళ'
    ],
    'WED' => [
        'en' => 'WED', 'hi' => 'बुध', 'kn' => 'ಬುಧ', 'ml' => 'ബുധൻ', 
        'gu' => 'બુધ', 'mr' => 'बुध', 'ta' => 'புதன்', 'te' => 'బుధ'
    ],
    'THU' => [
        'en' => 'THU', 'hi' => 'गुरु', 'kn' => 'ಗುರು', 'ml' => 'വ്യാഴം', 
        'gu' => 'ગુરુ', 'mr' => 'गुरु', 'ta' => 'வியாழன்', 'te' => 'గురు'
    ],
    'FRI' => [
        'en' => 'FRI', 'hi' => 'शुक्र', 'kn' => 'ಶುಕ್ರ', 'ml' => 'വെള്ളി', 
        'gu' => 'શુક્ર', 'mr' => 'शुक्र', 'ta' => 'வெள்ளி', 'te' => 'శుక్ర'
    ],
    'SAT' => [
        'en' => 'SAT', 'hi' => 'शनि', 'kn' => 'ಶನಿ', 'ml' => 'ശനി', 
        'gu' => 'શનિ', 'mr' => 'शनि', 'ta' => 'சனி', 'te' => 'శని'
    ],
    'SUN' => [
        'en' => 'SUN', 'hi' => 'रवि', 'kn' => 'ಭಾನು', 'ml' => 'ഞായർ', 
        'gu' => 'રવિ', 'mr' => 'रवी', 'ta' => 'ஞாயிறு', 'te' => 'ఆది'
    ]
];";

$content = str_replace('];', $newTranslations, $content);

file_put_contents($file, $content);
echo "lang.php updated with days.";
