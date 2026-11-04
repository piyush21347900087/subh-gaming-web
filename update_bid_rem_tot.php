<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "
    'Remaining' => [
        'en' => 'Remaining', 'hi' => 'बचे हुए', 'kn' => 'ಉಳಿದಿದೆ', 'ml' => 'ബാക്കി', 
        'gu' => 'બાકી', 'mr' => 'उर्वरित', 'ta' => 'மீதம்', 'te' => 'మిగిలి ఉన్నవి'
    ],
    'Total Bid' => [
        'en' => 'Total Bid', 'hi' => 'कुल बोली', 'kn' => 'ಒಟ್ಟು ಬಿಡ್', 'ml' => 'മൊത്തം ബിഡ്', 
        'gu' => 'કુલ બોલી', 'mr' => 'एकूण बोली', 'ta' => 'மொத்த ஏலம்', 'te' => 'మొత్తం బిడ్'
    ],
    'Insufficient wallet balance!' => [
        'en' => 'Insufficient wallet balance!', 'hi' => 'वॉलेट में अपर्याप्त राशि!', 'kn' => 'ವಾಲೆಟ್‌ನಲ್ಲಿ ಸಾಕಷ್ಟು ಹಣವಿಲ್ಲ!', 'ml' => 'വാലറ്റിൽ ആവശ്യത്തിന് പണമില്ല!', 
        'gu' => 'વૉલેટમાં અપૂરતી રકમ!', 'mr' => 'वॉलेटमध्ये पुरेशी रक्कम नाही!', 'ta' => 'வாலெட்டில் போதிய பணம் இல்லை!', 'te' => 'వాలెట్‌లో తగినంత నగదు లేదు!'
    ],
";

if (strpos($content, "'Remaining' => [") === false) {
    $content = preg_replace("/\s*'SUN'\s*=>\s*\[/i", "\n" . $additions . "    'SUN' => [", $content);
    file_put_contents($file, $content);
    echo "Added Remaining, Total Bid strings to lang.php.\n";
}

$dir = 'd:/SUBH GAMING/SUBH GAMING Web Code/';
$targetFiles = [
    'single_digit_bulk.php',
    'single_digit.php',
    'jodi_digit_bulk.php',
    'jodi_digit.php',
    'single_pana_bulk.php',
    'single_pana.php',
    'double_pana_bulk.php',
    'double_pana.php',
    'triple_pana_bulk.php',
    'triple_pana.php',
    'sp_motor.php',
    'dp_motor.php',
    'half_sangam.php',
    'full_sangam.php'
];

$replacements = [
    '<span class="rem">Remaining: ₹<?= $totalBalance ?></span>' => '<span class="rem"><?= __("Remaining") ?>: ₹<?= $totalBalance ?></span>',
    '<span class="tot">Total Bid: ₹0</span>' => '<span class="tot"><?= __("Total Bid") ?>: ₹0</span>',
    'totalBar.querySelector(\'.rem\').textContent = `Remaining: ₹${remBal}`;' => 'totalBar.querySelector(\'.rem\').textContent = `<?= __("Remaining") ?>: ₹${remBal}`;',
    'totalBar.querySelector(\'.tot\').textContent = `Total Bid: ₹${total}`;' => 'totalBar.querySelector(\'.tot\').textContent = `<?= __("Total Bid") ?>: ₹${total}`;',
    "'Insufficient wallet balance!'" => "'<?= __(\"Insufficient wallet balance!\") ?>'"
];

$updatedCount = 0;
foreach ($targetFiles as $basename) {
    $f = $dir . $basename;
    if (!file_exists($f)) continue;
    
    $fc = file_get_contents($f);
    $original = $fc;
    foreach ($replacements as $search => $replace) {
        if (strpos($fc, $search) !== false) {
            $fc = str_replace($search, $replace, $fc);
        }
    }
    if ($fc !== $original) {
        file_put_contents($f, $fc);
        echo "Updated " . basename($f) . "\n";
        $updatedCount++;
    }
}
echo "Total updated: $updatedCount\n";
