<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Deposit via' => [
        'en' => 'Deposit via', 'hi' => 'इसके माध्यम से जमा करें', 'kn' => 'ಮೂಲಕ ಠೇವಣಿ ಮಾಡಿ', 'ml' => 'വഴി നിക്ഷേപിക്കുക', 
        'gu' => 'દ્વારા જમા કરો', 'mr' => 'याद्वारे जमा करा', 'ta' => 'மூலம் வைப்பு', 'te' => 'ద్వారా డిపాజిట్'
    ],
    'Auto UPI' => [
        'en' => 'Auto UPI', 'hi' => 'ऑटो यूपीआई', 'kn' => 'ಸ್ವಯಂ UPI', 'ml' => 'ഓട്ടോ UPI', 
        'gu' => 'ઓટો UPI', 'mr' => 'ऑटो UPI', 'ta' => 'ஆட்டோ UPI', 'te' => 'ఆటో UPI'
    ],
    'UPI QR' => [
        'en' => 'UPI QR', 'hi' => 'यूपीआई क्यूआर', 'kn' => 'UPI QR', 'ml' => 'UPI QR', 
        'gu' => 'UPI QR', 'mr' => 'UPI QR', 'ta' => 'UPI QR', 'te' => 'UPI QR'
    ],
    'Special Bonus Offers' => [
        'en' => 'Special Bonus Offers', 'hi' => 'विशेष बोनस ऑफर', 'kn' => 'ವಿಶೇಷ ಬೋನಸ್ ಕೊಡುಗೆಗಳು', 'ml' => 'പ്രത്യേക ബോണസ് ഓഫറുകൾ', 
        'gu' => 'ખાસ બોનસ ઑફર્સ', 'mr' => 'विशेष बोनस ऑफर', 'ta' => 'சிறப்பு போனஸ் சலுகைகள்', 'te' => 'ప్రత్యేక బోనస్ ఆఫర్లు'
    ],
    'Amount (₹ INR):' => [
        'en' => 'Amount (₹ INR):', 'hi' => 'राशि (₹ INR):', 'kn' => 'ಮೊತ್ತ (₹ INR):', 'ml' => 'തുക (₹ INR):', 
        'gu' => 'રકમ (₹ INR):', 'mr' => 'रक्कम (₹ INR):', 'ta' => 'தொகை (₹ INR):', 'te' => 'మొత్తం (₹ INR):'
    ],
    'People Active Now' => [
        'en' => 'People Active Now', 'hi' => 'वर्तमान में सक्रिय लोग', 'kn' => 'ಈಗ ಸಕ್ರಿಯವಾಗಿರುವ ಜನರು', 'ml' => 'ഇപ്പോൾ സജീവമായ ആളുകൾ', 
        'gu' => 'હાલમાં સક્રિય લોકો', 'mr' => 'सध्या सक्रिय असलेले लोक', 'ta' => 'இப்போது செயலில் உள்ளவர்கள்', 'te' => 'ప్రస్తుతం సక్రియంగా ఉన్న వ్యక్తులు'
    ],
    'SUN' => [
";

if (strpos($content, "'Deposit via' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added deposit translations to lang.php.\n";
}

$depFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/deposit.php';
$depContent = file_get_contents($depFile);

$replacements = [
    '<h3>Deposit via</h3>' => '<h3><?= __("Deposit via") ?></h3>',
    '>Auto UPI<' => '><?= __("Auto UPI") ?><',
    '>UPI QR<' => '><?= __("UPI QR") ?><',
    '🎁 Special Bonus Offers 🎁' => '🎁 <?= __("Special Bonus Offers") ?> 🎁',
    '>Amount (₹ INR):<' => '><?= __("Amount (₹ INR):") ?><',
    '>People Active Now ' => '><?= __("People Active Now") ?> ',
    '>Deposit History<' => '><?= __("Deposit History") ?><'
];

foreach ($replacements as $search => $replace) {
    $depContent = str_replace($search, $replace, $depContent);
}

file_put_contents($depFile, $depContent);
echo "deposit.php strings updated.";
