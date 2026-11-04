<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Total Winnings:' => [
        'en' => 'Total Winnings:', 'hi' => 'कुल जीत:', 'kn' => 'ಒಟ್ಟು ಗೆಲುವುಗಳು:', 'ml' => 'മൊത്തം വിജയങ്ങൾ:', 
        'gu' => 'કુલ જીત:', 'mr' => 'एकूण विजय:', 'ta' => 'மொத்த வெற்றிகள்:', 'te' => 'మొత్తం విజయాలు:'
    ],
    'Today' => [
        'en' => 'Today', 'hi' => 'आज', 'kn' => 'ಇಂದು', 'ml' => 'ഇന്ന്', 
        'gu' => 'આજે', 'mr' => 'आज', 'ta' => 'இன்று', 'te' => 'ఈరోజు'
    ],
    'One Week' => [
        'en' => 'One Week', 'hi' => 'एक सप्ताह', 'kn' => 'ಒಂದು ವಾರ', 'ml' => 'ഒരാഴ്ച', 
        'gu' => 'એક અઠવાડિયું', 'mr' => 'एक आठवडा', 'ta' => 'ஒரு வாரம்', 'te' => 'ఒక వారం'
    ],
    'Session:' => [
        'en' => 'Session:', 'hi' => 'सत्र:', 'kn' => 'ಅಧಿವೇಶನ:', 'ml' => 'സെഷൻ:', 
        'gu' => 'સત્ર:', 'mr' => 'सत्र:', 'ta' => 'அமர்வு:', 'te' => 'సెషన్:'
    ],
    'OPEN' => [
        'en' => 'OPEN', 'hi' => 'ओपन', 'kn' => 'ತೆರೆಯಿರಿ', 'ml' => 'തുറക്കുക', 
        'gu' => 'ઓપન', 'mr' => 'ओपन', 'ta' => 'திறக்க', 'te' => 'తెరవండి'
    ],
    'CLOSE' => [
        'en' => 'CLOSE', 'hi' => 'क्लोज', 'kn' => 'ಮುಚ್ಚಿ', 'ml' => 'അടയ്ക്കുക', 
        'gu' => 'ક્લોઝ', 'mr' => 'क्लोज', 'ta' => 'மூடு', 'te' => 'మూసివేయండి'
    ],
    'Bid' => [
        'en' => 'Bid', 'hi' => 'बोली', 'kn' => 'ಬಿಡ್', 'ml' => 'ബിഡ്', 
        'gu' => 'બોલી', 'mr' => 'बोली', 'ta' => 'ஏலம்', 'te' => 'బిడ్'
    ],
    'Win' => [
        'en' => 'Win', 'hi' => 'जीत', 'kn' => 'ಗೆಲುವು', 'ml' => 'വിജയം', 
        'gu' => 'જીત', 'mr' => 'विजय', 'ta' => 'வெற்றி', 'te' => 'గెలుపు'
    ],
    'No winning records found.' => [
        'en' => 'No winning records found.', 'hi' => 'कोई जीत रिकॉर्ड नहीं मिला।', 'kn' => 'ಯಾವುದೇ ಗೆಲುವಿನ ದಾಖಲೆಗಳು ಕಂಡುಬಂದಿಲ್ಲ.', 'ml' => 'വിജയിച്ച റെക്കോർഡുകളൊന്നും കണ്ടെത്തിയില്ല.', 
        'gu' => 'કોઈ જીતવાના રેકોર્ડ મળ્યા નથી.', 'mr' => 'कोणत्याही विजयाच्या नोंदी आढळल्या नाहीत.', 'ta' => 'வெற்றிப் பதிவுகள் எதுவும் காணப்படவில்லை.', 'te' => 'విజేత రికార్డులు కనుగొనబడలేదు.'
    ],
    'SUN' => [
";

if (strpos($content, "'Total Winnings:' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added user_wining translations to lang.php.\n";
}

$chartFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_wining.php';
$chartContent = file_get_contents($chartFile);

$replacements = [
    'Total Winnings:' => '<?= __("Total Winnings:") ?>',
    '>Today<' => '><?= __("Today") ?><',
    '>One Week<' => '><?= __("One Week") ?><',
    'Session: ${session}' => '<?= __("Session:") ?> ${sessionLabels[session] || session}',
    'Win ₹' => '<?= __("Win") ?> ₹',
    'Bid ₹' => '<?= __("Bid") ?> ₹',
    "No winning records found." => "<?= __(\"No winning records found.\") ?>",
    // Replace the specific date format string
    "dt.toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'})" => "dt.toLocaleDateString('<?= \$_SESSION[\"lang\"] ?? \"en\" ?>-IN',{day:'2-digit',month:'short',year:'numeric'})",
    // Inject the session and bidType dictionaries into JS
    "const bidType = String(w.game_type || 'Bet').replace(/_/g, ' ').toUpperCase();" => "
            const bidTypesDict = {
                'SINGLE DIGIT': '<?= __(\"Single Digit\") ?>',
                'JODI DIGIT': '<?= __(\"Jodi Digit\") ?>',
                'SINGLE PANA': '<?= __(\"Single Pana\") ?>',
                'DOUBLE PANA': '<?= __(\"Double Pana\") ?>',
                'TRIPLE PANA': '<?= __(\"Triple Pana\") ?>',
                'HALF SANGAM': '<?= __(\"Half Sangam\") ?>',
                'FULL SANGAM': '<?= __(\"Full Sangam\") ?>'
            };
            const sessionLabels = {
                'OPEN': '<?= __(\"OPEN\") ?>',
                'CLOSE': '<?= __(\"CLOSE\") ?>'
            };
            let bidTypeStr = String(w.game_type || 'Bet').replace(/_/g, ' ').toUpperCase();
            const bidType = bidTypesDict[bidTypeStr] || bidTypeStr;
    ",
];

foreach ($replacements as $search => $replace) {
    if (strpos($chartContent, $replace) === false) {
        $chartContent = str_replace($search, $replace, $chartContent);
    }
}

file_put_contents($chartFile, $chartContent);
echo "user_wining.php strings updated.";
