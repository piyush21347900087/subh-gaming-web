<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Request Withdrawal' => [
        'en' => 'Request Withdrawal', 'hi' => 'निकासी का अनुरोध करें', 'kn' => 'ಹಿಂಪಡೆಯುವಿಕೆಗೆ ವಿನಂತಿಸಿ', 'ml' => 'പിൻവലിക്കൽ അഭ്യർത്ഥിക്കുക', 
        'gu' => 'ઉપાડની વિનંતી કરો', 'mr' => 'पैसे काढण्याची विनंती करा', 'ta' => 'திரும்பப் பெறுதல் கோரிக்கை', 'te' => 'ఉపసంహరణను అభ్యర్థించండి'
    ],
    'Amount (₹):' => [
        'en' => 'Amount (₹):', 'hi' => 'राशि (₹):', 'kn' => 'ಮೊತ್ತ (₹):', 'ml' => 'തുക (₹):', 
        'gu' => 'રકમ (₹):', 'mr' => 'रक्कम (₹):', 'ta' => 'தொகை (₹):', 'te' => 'మొత్తం (₹):'
    ],
    'Enter Amount' => [
        'en' => 'Enter Amount', 'hi' => 'राशि दर्ज करें', 'kn' => 'ಮೊತ್ತವನ್ನು ನಮೂದಿಸಿ', 'ml' => 'തുക നൽകുക', 
        'gu' => 'રકમ દાખલ કરો', 'mr' => 'रक्कम प्रविष्ट करा', 'ta' => 'தொகையை உள்ளிடவும்', 'te' => 'మొత్తం నమోదు చేయండి'
    ],
    'Method:' => [
        'en' => 'Method:', 'hi' => 'तरीका:', 'kn' => 'ವಿಧಾನ:', 'ml' => 'രീതി:', 
        'gu' => 'પદ્ધતિ:', 'mr' => 'पद्धत:', 'ta' => 'முறை:', 'te' => 'పద్ధతి:'
    ],
    'Select Method' => [
        'en' => 'Select Method', 'hi' => 'तरीका चुनें', 'kn' => 'ವಿಧಾನವನ್ನು ಆಯ್ಕೆಮಾಡಿ', 'ml' => 'രീതി തിരഞ്ഞെടുക്കുക', 
        'gu' => 'પદ્ધતિ પસંદ કરો', 'mr' => 'पद्धत निवडा', 'ta' => 'முறையைத் தேர்ந்தெடுக்கவும்', 'te' => 'పద్ధతిని ఎంచుకోండి'
    ],
    'Submit Withdrawal' => [
        'en' => 'Submit Withdrawal', 'hi' => 'निकासी सबमिट करें', 'kn' => 'ಹಿಂಪಡೆಯುವಿಕೆಯನ್ನು ಸಲ್ಲಿಸಿ', 'ml' => 'പിൻവലിക്കൽ സമർപ്പിക്കുക', 
        'gu' => 'ઉપાડ સબમિટ કરો', 'mr' => 'पैसे काढणे सबमिट करा', 'ta' => 'திரும்பப் பெறுதலைச் சமர்ப்பிக்கவும்', 'te' => 'ఉపసంహరణను సమర్పించండి'
    ],
    'People Active Now' => [
        'en' => 'People Active Now', 'hi' => 'लोग अभी सक्रिय हैं', 'kn' => 'ಜನರು ಈಗ ಸಕ್ರಿಯರಾಗಿದ್ದಾರೆ', 'ml' => 'ആളുകൾ ഇപ്പോൾ സജീവമാണ്', 
        'gu' => 'લોકો હવે સક્રિય છે', 'mr' => 'लोक आता सक्रिय आहेत', 'ta' => 'மக்கள் இப்போது செயலில் உள்ளனர்', 'te' => 'ప్రజలు ఇప్పుడు యాక్టివ్‌గా ఉన్నారు'
    ],
    'Withdrawal are available every day from 6:00 AM to 11:00 AM.' => [
        'en' => 'Withdrawal are available every day from 6:00 AM to 11:00 AM.', 'hi' => 'निकासी हर दिन सुबह 6:00 बजे से सुबह 11:00 बजे तक उपलब्ध है।', 'kn' => 'ಪ್ರತಿದಿನ ಬೆಳಿಗ್ಗೆ 6:00 ರಿಂದ 11:00 ರವರೆಗೆ ಹಿಂಪಡೆಯುವಿಕೆ ಲಭ್ಯವಿದೆ.', 'ml' => 'എല്ലാ ദിവസവും രാവിലെ 6:00 മുതൽ രാവിലെ 11:00 വരെ പിൻവലിക്കൽ ലഭ്യമാണ്.', 
        'gu' => 'દરરોજ સવારે 6:00 થી સવારે 11:00 વાગ્યા સુધી ઉપાડ ઉપલબ્ધ છે.', 'mr' => 'दररोज सकाळी ६:०० ते ११:०० वाजेपर्यंत पैसे काढण्याची सुविधा उपलब्ध आहे.', 'ta' => 'தினசரி காலை 6:00 மணி முதல் 11:00 மணி வரை திரும்பப் பெறலாம்.', 'te' => 'ప్రతిరోజూ ఉదయం 6:00 నుండి ఉదయం 11:00 వరకు ఉపసంహరణ అందుబాటులో ఉంటుంది.'
    ],
    'SUN' => [
";

if (strpos($content, "'Request Withdrawal' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added withdrawal translations to lang.php.\n";
}

$chartFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/withdrawal.php';
$chartContent = file_get_contents($chartFile);

// Replaces in HTML & PHP
$replacements = [
    '>Request Withdrawal<' => '><?= __("Request Withdrawal") ?><',
    '>Amount (₹):<' => '><?= __("Amount (₹):") ?><',
    'placeholder="Enter Amount"' => 'placeholder="<?= __("Enter Amount") ?>"',
    '>Method:<' => '><?= __("Method:") ?><',
    '>Select Method<' => '><?= __("Select Method") ?><',
    "showPopup('Select Method');" => "showPopup('<?= __(\"Select Method\") ?>');",
    "'<span style=\"color: #333333 !important; font-weight: 900;\">Select Method</span>'" => "'<span style=\"color: #333333 !important; font-weight: 900;\"><?= __(\"Select Method\") ?></span>'",
    '>Submit Withdrawal<' => '><?= __("Submit Withdrawal") ?><',
    '>People Active Now ' => '><?= __("People Active Now") ?> ',
    '>Withdrawal History<' => '><?= __("Withdrawal History") ?><',
    "\$notice = 'Withdrawal are available every day from 6:00 AM to 11:00 AM.';" => "\$notice = __(\"Withdrawal are available every day from 6:00 AM to 11:00 AM.\");",
];

foreach ($replacements as $search => $replace) {
    if (strpos($chartContent, $replace) === false) {
        $chartContent = str_replace($search, $replace, $chartContent);
    }
}

file_put_contents($chartFile, $chartContent);
echo "withdrawal.php strings updated.";
