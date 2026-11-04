<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Added to wallet' => [
        'en' => 'Added to wallet', 'hi' => 'वॉलेट में जोड़ा गया', 'kn' => 'ವಾಲೆಟ್‌ಗೆ ಸೇರಿಸಲಾಗಿದೆ', 'ml' => 'വാലറ്റിലേക്ക് ചേർത്തു', 
        'gu' => 'વોલેટમાં ઉમેર્યું', 'mr' => 'वॉलेटमध्ये जोडले', 'ta' => 'வாலெட்டில் சேர்க்கப்பட்டது', 'te' => 'వాలెట్‌కు జోడించబడింది'
    ],
    'Amount withdrawn' => [
        'en' => 'Amount withdrawn', 'hi' => 'निकासी की गई राशि', 'kn' => 'ಹಿಂಪಡೆದ ಮೊತ್ತ', 'ml' => 'പിൻവലിച്ച തുക', 
        'gu' => 'ઉપાડેલી રકમ', 'mr' => 'काढलेली रक्कम', 'ta' => 'திரும்பப் பெறப்பட்ட தொகை', 'te' => 'ఉపసంహరించబడిన మొత్తం'
    ],
    'just now' => [
        'en' => 'just now', 'hi' => 'अभी-अभी', 'kn' => 'ಈಗತಾನೆ', 'ml' => 'ഇപ്പോൾ മാത്രം', 
        'gu' => 'હમણાં જ', 'mr' => 'आत्ताच', 'ta' => 'இப்போதுதான்', 'te' => 'ఇప్పుడే'
    ],
    '1 min ago' => [
        'en' => '1 min ago', 'hi' => '1 मिनट पहले', 'kn' => '1 ನಿಮಿಷದ ಹಿಂದೆ', 'ml' => '1 മിനിറ്റ് മുമ്പ്', 
        'gu' => '1 મિનિટ પહેલા', 'mr' => '1 मिनिटापूर्वी', 'ta' => '1 நிமிடம் முன்பு', 'te' => '1 నిమిషం క్రితం'
    ],
    '2 min ago' => [
        'en' => '2 min ago', 'hi' => '2 मिनट पहले', 'kn' => '2 ನಿಮಿಷಗಳ ಹಿಂದೆ', 'ml' => '2 മിനിറ്റ് മുമ്പ്', 
        'gu' => '2 મિનિટ પહેલા', 'mr' => '2 मिनिटांपूर्वी', 'ta' => '2 நிமிடங்கள் முன்பு', 'te' => '2 నిమిషాల క్రితం'
    ],
    '3 min ago' => [
        'en' => '3 min ago', 'hi' => '3 मिनट पहले', 'kn' => '3 ನಿಮಿಷಗಳ ಹಿಂದೆ', 'ml' => '3 മിനിറ്റ് മുമ്പ്', 
        'gu' => '3 મિનિટ પહેલા', 'mr' => '3 मिनिटांपूर्वी', 'ta' => '3 நிமிடங்கள் முன்பு', 'te' => '3 నిమిషాల క్రితం'
    ],
    '4 min ago' => [
        'en' => '4 min ago', 'hi' => '4 मिनट पहले', 'kn' => '4 ನಿಮಿಷಗಳ ಹಿಂದೆ', 'ml' => '4 മിനിറ്റ് മുമ്പ്', 
        'gu' => '4 મિનિટ પહેલા', 'mr' => '4 मिनिटांपूर्वी', 'ta' => '4 நிமிடங்கள் முன்பு', 'te' => '4 నిమిషాల క్రితం'
    ],
    '5 min ago' => [
        'en' => '5 min ago', 'hi' => '5 मिनट पहले', 'kn' => '5 ನಿಮಿಷಗಳ ಹಿಂದೆ', 'ml' => '5 മിനിറ്റ് മുമ്പ്', 
        'gu' => '5 મિનિટ પહેલા', 'mr' => '5 मिनिटांपूर्वी', 'ta' => '5 நிமிடங்கள் முன்பு', 'te' => '5 నిమిషాల క్రితం'
    ],
    'SUN' => [
";

if (strpos($content, "'Added to wallet' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added more translations to lang.php.\n";
}

$files = [
    'd:/SUBH GAMING/SUBH GAMING Web Code/deposit.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/withdrawal.php'
];

$replacements = [
    // Previous deposit.php request
    'Scan QR to Pay' => '<?= __("Scan QR to Pay") ?>',
    'Name:</div>' => '<?= __("Name:") ?></div>',
    'UPI ID:</div>' => '<?= __("UPI ID:") ?></div>',
    'Download QR' => '<?= __("Download QR") ?>',
    'After payment enter amount and submit request.' => '<?= __("After payment enter amount and submit request.") ?>',
    'Upload Screenshot:</label>' => '<?= __("Upload Screenshot:") ?></label>',
    '>Choose file...<' => '><?= __("Choose file...") ?><',
    "'Choose file...'" => "'<?= __(\"Choose file...\") ?>'",
    "'Proceed / Submit'" => "'<?= __(\"Proceed / Submit\") ?>'",
    "'Submit Deposit'" => "'<?= __(\"Submit Deposit\") ?>'",
    
    // JS Activity feed updates for both deposit.php and withdrawal.php
    "'just now','1 min ago','2 min ago','3 min ago','4 min ago','5 min ago'" => "'<?= __(\"just now\") ?>','<?= __(\"1 min ago\") ?>','<?= __(\"2 min ago\") ?>','<?= __(\"3 min ago\") ?>','<?= __(\"4 min ago\") ?>','<?= __(\"5 min ago\") ?>'",
    "'just now', '1 min ago', '2 min ago', '3 min ago', '4 min ago', '5 min ago'" => "'<?= __(\"just now\") ?>', '<?= __(\"1 min ago\") ?>', '<?= __(\"2 min ago\") ?>', '<?= __(\"3 min ago\") ?>', '<?= __(\"4 min ago\") ?>', '<?= __(\"5 min ago\") ?>'",
    "Added to wallet" => "<?= __(\"Added to wallet\") ?>",
    "'Amount withdrawn'" => "'<?= __(\"Amount withdrawn\") ?>'"
];

foreach ($files as $f) {
    if (file_exists($f)) {
        $fc = file_get_contents($f);
        foreach ($replacements as $search => $replace) {
            // Only replace if not already replaced to avoid double substitution
            if (strpos($fc, $replace) === false) {
                $fc = str_replace($search, $replace, $fc);
            }
        }
        file_put_contents($f, $fc);
        echo basename($f) . " strings updated.\n";
    }
}
