<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Deposit History' => [
        'en' => 'Deposit History', 'hi' => 'जमा इतिहास', 'kn' => 'ಠೇವಣಿ ಇತಿಹಾಸ', 'ml' => 'നിക്ഷേപ ചരിത്രം', 
        'gu' => 'થાપણ ઇતિહાસ', 'mr' => 'ठेव इतिहास', 'ta' => 'வைப்பு வரலாறு', 'te' => 'డిపాజిట్ చరిత్ర'
    ],
    'Sr' => [
        'en' => 'Sr', 'hi' => 'क्र.', 'kn' => 'ಕ್ರ.ಸಂ', 'ml' => 'നം.', 
        'gu' => 'ક્રમ', 'mr' => 'क्र.', 'ta' => 'வ.எண்', 'te' => 'క్ర.సం'
    ],
    'Amount' => [
        'en' => 'Amount', 'hi' => 'राशि', 'kn' => 'ಮೊತ್ತ', 'ml' => 'തുക', 
        'gu' => 'રકમ', 'mr' => 'रक्कम', 'ta' => 'தொகை', 'te' => 'మొత్తం'
    ],
    'Date/Time' => [
        'en' => 'Date/Time', 'hi' => 'दिनांक/समय', 'kn' => 'ದಿನಾಂಕ/ಸಮಯ', 'ml' => 'തീയതി/സമയം', 
        'gu' => 'તારીખ/સમય', 'mr' => 'तारीख/वेळ', 'ta' => 'தேதி/நேரம்', 'te' => 'తేదీ/సమయం'
    ],
    'Status' => [
        'en' => 'Status', 'hi' => 'स्थिति', 'kn' => 'ಸ್ಥಿತಿ', 'ml' => 'നില', 
        'gu' => 'સ્થિતિ', 'mr' => 'स्थिती', 'ta' => 'நிலை', 'te' => 'స్థితి'
    ],
    'Admin' => [
        'en' => 'Admin', 'hi' => 'एडमिन', 'kn' => 'ನಿರ್ವಾಹಕ', 'ml' => 'അഡ്മിൻ', 
        'gu' => 'એડમિન', 'mr' => 'अॅडमिन', 'ta' => 'நிர்வாகி', 'te' => 'అడ్మిన్'
    ],
";

if (strpos($content, "'Deposit History' => [") === false) {
    $content = preg_replace("/\s*'SUN'\s*=>\s*\[/i", "\n" . $additions . "    'SUN' => [", $content);
    file_put_contents($file, $content);
    echo "Added deposit history translations to lang.php.\n";
} else {
    echo "Already added.\n";
}

$chartFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/deposit_history.php';
$chartContent = file_get_contents($chartFile);

$replacements = [
    '<div class="info-text" id="infoText">Deposit History / जमा हिस्ट्री</div>' => '<div class="info-text" id="infoText"><?= __("Deposit History") ?></div>',
    "info.textContent = 'Deposit History / जमा हिस्ट्री';" => "info.textContent = '<?= __(\"Deposit History\") ?>';",
    '<th>Sr</th>' => '<th><?= __("Sr") ?></th>',
    '<th>Amount</th>' => '<th><?= __("Amount") ?></th>',
    '<th>Date/Time</th>' => '<th><?= __("Date/Time") ?></th>',
    '<th>Method</th>' => '<th><?= __("Method") ?></th>',
    '<th>Status</th>' => '<th><?= __("Status") ?></th>',
    "'ADMIN' => 'Admin'," => "'ADMIN' => __(\"Admin\"),",
    "return 'Admin';" => "return __(\"Admin\");"
];

foreach ($replacements as $search => $replace) {
    if (strpos($chartContent, $replace) === false) {
        $chartContent = str_replace($search, $replace, $chartContent);
    }
}

file_put_contents($chartFile, $chartContent);
echo "deposit_history.php strings updated.";
