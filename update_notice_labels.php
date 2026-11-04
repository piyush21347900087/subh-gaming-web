<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'SUBH GAMING GAME' => [
        'en' => 'SUBH GAMING GAME', 'hi' => 'मटका किंग गेम', 'kn' => 'ಮಟ್ಕಾ ಕಿಂಗ್ ಗೇಮ್', 'ml' => 'മറ്റ്കാ കിംഗ് ഗെയിം', 
        'gu' => 'મટકા કિંગ ગેમ', 'mr' => 'मटका किंग गेम', 'ta' => 'மட்கா கிங் கேம்', 'te' => 'మట్కా కింగ్ గేమ్'
    ],
    'Market: MAHARASHTRA, GOA, DELHI & KARNATAKA' => [
        'en' => 'Market: MAHARASHTRA, GOA, DELHI & KARNATAKA', 'hi' => 'मार्केट: महाराष्ट्र, गोवा, दिल्ली और कर्नाटक', 'kn' => 'ಮಾರುಕಟ್ಟೆ: ಮಹಾರಾಷ್ಟ್ರ, ಗೋವಾ, ದೆಹಲಿ ಮತ್ತು ಕರ್ನಾಟಕ', 'ml' => 'മാർക്കറ്റ്: മഹാരാഷ്ട്ര, ഗോവ, ദില്ലി & കർണാടക', 
        'gu' => 'માર્કેટ: મહારાષ્ટ્ર, ગોવા, દિલ્હી અને કર્ણાટક', 'mr' => 'मार्केट: महाराष्ट्र, गोवा, दिल्ली आणि कर्नाटक', 'ta' => 'சந்தை: மகாராஷ்டிரா, கோவா, டெல்லி மற்றும் கர்நாடகா', 'te' => 'మార్కెట్: మహారాష్ట్ర, గోవా, ఢిల్లీ మరియు కర్ణాటక'
    ],
    'Download App:' => [
        'en' => 'Download App:', 'hi' => 'ऐप डाउनलोड करें:', 'kn' => 'ಆಪ್ ಡೌನ್‌ಲೋಡ್ ಮಾಡಿ:', 'ml' => 'ആപ്പ് ഡൗൺലോഡ് ചെയ്യുക:', 
        'gu' => 'એપ ડાઉનલોડ કરો:', 'mr' => 'अॅप डाउनलोड करा:', 'ta' => 'ஆப்பை பதிவிறக்கவும்:', 'te' => 'యాప్‌ని డౌన్‌లోడ్ చేయండి:'
    ],
    'Download App' => [
        'en' => 'Download App', 'hi' => 'ऐप डाउनलोड करें', 'kn' => 'ಆಪ್ ಡೌನ್‌ಲೋಡ್ ಮಾಡಿ', 'ml' => 'ആപ്പ് ഡൗൺലോഡ് ചെയ്യുക', 
        'gu' => 'એપ ડાઉનલોડ કરો', 'mr' => 'अॅप डाउनलोड करा', 'ta' => 'ஆப்பை பதிவிறக்கவும்', 'te' => 'యాప్‌ని డౌన్‌లోడ్ చేయండి'
    ],
    'Open Link' => [
        'en' => 'Open Link', 'hi' => 'लिंक खोलें', 'kn' => 'ಲಿಂಕ್ ತೆರೆಯಿರಿ', 'ml' => 'ലിങ്ക് തുറക്കുക', 
        'gu' => 'લિંક ખોલો', 'mr' => 'लिंक उघडा', 'ta' => 'இணைப்பைத் திற', 'te' => 'లింక్ తెరవండి'
    ],
    'SUN' => [
";

if (strpos($content, "'SUBH GAMING GAME' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added notice board labels to lang.php.\n";
}

$noticeFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/notice_board.php';
$noticeContent = file_get_contents($noticeFile);

// Replace hardcoded HTML texts
$noticeContent = str_replace('<span class="lite-label">SUBH GAMING GAME</span>', '<span class="lite-label"><?= __("SUBH GAMING GAME") ?></span>', $noticeContent);
$noticeContent = str_replace('<span class="market-label">Market: MAHARASHTRA, GOA, DELHI &amp; KARNATAKA</span>', '<span class="market-label"><?= __("Market: MAHARASHTRA, GOA, DELHI & KARNATAKA") ?></span>', $noticeContent);

// Replace PHP share string
$noticeContent = str_replace('$shareText .= "\n\nDownload App:\n"', '$shareText .= "\n\n" . __("Download App:") . "\n"', $noticeContent);

// Wrap link label in __() just in case the db outputs English "Download App"
$noticeContent = str_replace('<?= htmlspecialchars($linkLbl) ?>', '<?= htmlspecialchars(__($linkLbl)) ?>', $noticeContent);
$noticeContent = str_replace("\$linkLbl = 'Open Link';", "\$linkLbl = 'Open Link';", $noticeContent); // Not changing the logic string assignment but already wrapped below

file_put_contents($noticeFile, $noticeContent);
echo "notice_board.php labels updated.";
