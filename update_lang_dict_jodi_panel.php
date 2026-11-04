<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$newTranslations = "    'JODI' => [
        'en' => 'JODI', 'hi' => 'जोड़ी', 'kn' => 'ಜೋಡಿ', 'ml' => 'ജോഡി', 
        'gu' => 'જોડી', 'mr' => 'जोडी', 'ta' => 'ஜோடி', 'te' => 'జోడి'
    ],
    'PANEL' => [
        'en' => 'PANEL', 'hi' => 'पैनल', 'kn' => 'ಪ್ಯಾನೆಲ್', 'ml' => 'പാനൽ', 
        'gu' => 'પેનલ', 'mr' => 'पॅनेल', 'ta' => 'பேனல்', 'te' => 'ప్యానెల్'
    ]
];";

$content = str_replace('];', $newTranslations, $content);

file_put_contents($file, $content);
echo "lang.php updated with JODI and PANEL.";
