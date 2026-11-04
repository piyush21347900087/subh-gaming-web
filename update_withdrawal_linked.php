<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Linked' => [
        'en' => 'Linked', 'hi' => 'लिंक किया गया', 'kn' => 'ಲಿಂಕ್ ಮಾಡಲಾಗಿದೆ', 'ml' => 'ലിങ്ക് ചെയ്തു', 
        'gu' => 'લિંક કરેલ છે', 'mr' => 'लिंक केलेले', 'ta' => 'இணைக்கப்பட்டது', 'te' => 'లింక్ చేయబడింది'
    ],
    'Not Linked' => [
        'en' => 'Not Linked', 'hi' => 'लिंक नहीं है', 'kn' => 'ಲಿಂಕ್ ಮಾಡಲಾಗಿಲ್ಲ', 'ml' => 'ലിങ്ക് ചെയ്തിട്ടില്ല', 
        'gu' => 'લિંક કરેલ નથી', 'mr' => 'लिंक केलेले नाही', 'ta' => 'இணைக்கப்படவில்லை', 'te' => 'లింక్ చేయబడలేదు'
    ],
    'SUN' => [
";

if (strpos($content, "'Linked' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added Linked/Not Linked translations to lang.php.\n";
}

$chartFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/withdrawal.php';
$chartContent = file_get_contents($chartFile);

$replacements = [
    "(m.linked ? 'Linked' : 'Not Linked')" => "(m.linked ? '<?= __(\"Linked\") ?>' : '<?= __(\"Not Linked\") ?>')",
    "<div class=\"feed-action\">Amount withdrawn</div>" => "<div class=\"feed-action\"><?= __(\"Amount withdrawn\") ?></div>"
];

foreach ($replacements as $search => $replace) {
    if (strpos($chartContent, $replace) === false) {
        $chartContent = str_replace($search, $replace, $chartContent);
    }
}

file_put_contents($chartFile, $chartContent);
echo "withdrawal.php strings updated.";
