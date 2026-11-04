<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'BID' => [
        'en' => 'BID', 'hi' => 'बोली', 'kn' => 'ಬಿಡ್', 'ml' => 'ബിഡ്', 
        'gu' => 'બિડ', 'mr' => 'बिड', 'ta' => 'ஏலம்', 'te' => 'బిడ్'
    ],
    'RESULT' => [
        'en' => 'RESULT', 'hi' => 'परिणाम', 'kn' => 'ಫಲಿತಾಂಶ', 'ml' => 'ഫലം', 
        'gu' => 'પરિણામ', 'mr' => 'निकाल', 'ta' => 'முடிவு', 'te' => 'ఫలితం'
    ],
    'CHART' => [
        'en' => 'CHART', 'hi' => 'चार्ट', 'kn' => 'ಚಾರ್ಟ್', 'ml' => 'ചാർട്ട്', 
        'gu' => 'ચાર્ટ', 'mr' => 'चार्ट', 'ta' => 'விளக்கப்படம்', 'te' => 'చార్ట్'
    ],
    'SUN' => [
";

if (strpos($content, "'BID' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added result texts to lang.php.\n";
}

$resultFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_result.php';
$resultContent = file_get_contents($resultFile);

$replacements = [
    '<span class="card-label">BID</span>' => '<span class="card-label"><?= __("BID") ?></span>',
    '<span class="card-value">Bid History</span>' => '<span class="card-value"><?= __("Bid History") ?></span>',
    '<span class="card-label">RESULT</span>' => '<span class="card-label"><?= __("RESULT") ?></span>',
    '<span class="card-value">Winning History</span>' => '<span class="card-value"><?= __("Winning History") ?></span>',
    '<span class="card-label">CHART</span>' => '<span class="card-label"><?= __("CHART") ?></span>',
    '<span class="card-value">Panel Chart</span>' => '<span class="card-value"><?= __("Panel Chart") ?></span>'
];

foreach ($replacements as $search => $replace) {
    $resultContent = str_replace($search, $replace, $resultContent);
}

file_put_contents($resultFile, $resultContent);
echo "user_result.php texts updated.";
