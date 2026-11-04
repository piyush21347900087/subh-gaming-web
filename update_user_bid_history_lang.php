<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Total Bid:' => [
        'en' => 'Total Bid:', 'hi' => 'कुल बोली:', 'kn' => 'ಒಟ್ಟು ಬಿಡ್:', 'ml' => 'മൊത്തം ബിഡ്:', 
        'gu' => 'કુલ બોલી:', 'mr' => 'एकूण बोली:', 'ta' => 'மொத்த ஏலம்:', 'te' => 'మొత్తం బిడ్:'
    ],
    'No bid history found.' => [
        'en' => 'No bid history found.', 'hi' => 'कोई बोली इतिहास नहीं मिला।', 'kn' => 'ಯಾವುದೇ ಬಿಡ್ ಇತಿಹಾಸ ಕಂಡುಬಂದಿಲ್ಲ.', 'ml' => 'ബിഡ് ചരിത്രമൊന്നും കണ്ടെത്തിയില്ല.', 
        'gu' => 'કોઈ બોલીનો ઇતિહાસ મળ્યો નથી.', 'mr' => 'कोणताही बोली इतिहास आढळला नाही.', 'ta' => 'ஏல வரலாறு எதுவும் காணப்படவில்லை.', 'te' => 'బిడ్ చరిత్ర కనుగొనబడలేదు.'
    ],
    'Unable to load bid history.' => [
        'en' => 'Unable to load bid history.', 'hi' => 'बोली इतिहास लोड करने में असमर्थ।', 'kn' => 'ಬಿಡ್ ಇತಿಹಾಸವನ್ನು ಲೋಡ್ ಮಾಡಲು ಸಾಧ್ಯವಾಗುತ್ತಿಲ್ಲ.', 'ml' => 'ബിഡ് ചരിത്രം ലോഡുചെയ്യാനായില്ല.', 
        'gu' => 'બોલી ઇતિહાસ લોડ કરવામાં અસમર્થ.', 'mr' => 'बोली इतिहास लोड करण्यात अक्षम.', 'ta' => 'ஏல வரலாற்றை ஏற்ற முடியவில்லை.', 'te' => 'బిడ్ చరిత్రను లోడ్ చేయడం సాధ్యపడలేదు.'
    ],
    'Bid Type:' => [
        'en' => 'Bid Type:', 'hi' => 'बोली का प्रकार:', 'kn' => 'ಬಿಡ್ ಪ್ರಕಾರ:', 'ml' => 'ബിഡ് തരം:', 
        'gu' => 'બોલીનો પ્રકાર:', 'mr' => 'बोलीचा प्रकार:', 'ta' => 'ஏல வகை:', 'te' => 'బిడ్ రకం:'
    ],
    'SUN' => [
";

if (strpos($content, "'Total Bid:' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added bid history translations to lang.php.\n";
}

$chartFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_bid_history.php';
$chartContent = file_get_contents($chartFile);

// Change SQL Query
$sqlSearch = "DATE_FORMAT(bid_time, '%d %b %Y') AS bid_date";
$sqlReplace = "bid_time AS raw_date, DATE_FORMAT(bid_time, '%d %b %Y') AS bid_date";
$chartContent = str_replace($sqlSearch, $sqlReplace, $chartContent);

$replacements = [
    'Total Bid:' => '<?= __("Total Bid:") ?>',
    '>Today<' => '><?= __("Today") ?><',
    '>One Week<' => '><?= __("One Week") ?><',
    'No bid history found.' => '<?= __("No bid history found.") ?>',
    'Unable to load bid history.' => '<?= __("Unable to load bid history.") ?>',
    // Replace analyzeBid to insert dictionary
    "const b = data[i], info = analyzeBid(b);" => "const b = data[i], info = analyzeBid(b);
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
                'CLOSE': '<?= __(\"CLOSE\") ?>',
                'O/C': 'O/C'
            };",
    "const dateStr = b.bid_date || '';" => "const dt = new Date((b.raw_date || '').replace(' ', 'T')); const dateStr = b.raw_date ? dt.toLocaleDateString('<?= \$_SESSION[\"lang\"] ?? \"en\" ?>-IN',{day:'2-digit',month:'short',year:'numeric'}) : (b.bid_date || '');",
    "const timeStr = b.bid_clock || '';" => "const timeStr = b.raw_date ? dt.toLocaleTimeString('<?= \$_SESSION[\"lang\"] ?? \"en\" ?>-IN',{hour:'2-digit',minute:'2-digit',hour12:true}) : (b.bid_clock || '');",
    'Session: ${info.sess}' => '<?= __("Session:") ?> ${sessionLabels[info.sess] || info.sess}',
    'Bid Type: ${info.type}' => '<?= __("Bid Type:") ?> ${bidTypesDict[info.type] || info.type}'
];

foreach ($replacements as $search => $replace) {
    if (strpos($chartContent, $replace) === false) {
        $chartContent = str_replace($search, $replace, $chartContent);
    }
}

file_put_contents($chartFile, $chartContent);
echo "user_bid_history.php strings updated.";
