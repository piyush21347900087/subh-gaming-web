<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'KYC & BANK ACCESS' => [
        'en' => 'KYC & BANK ACCESS', 'hi' => 'केवाईसी और बैंक एक्सेस', 'kn' => 'KYC ಮತ್ತು ಬ್ಯಾಂಕ್ ಪ್ರವೇಶ', 'ml' => 'KYC & ബാങ്ക് ആക്സസ്', 
        'gu' => 'KYC અને બેંક ઍક્સેસ', 'mr' => 'केवायसी आणि बँक प्रवेश', 'ta' => 'KYC மற்றும் வங்கி அணுகல்', 'te' => 'KYC & బ్యాంక్ యాక్సెస్'
    ],
    'Primary Bank Account' => [
        'en' => 'Primary Bank Account', 'hi' => 'प्राथमिक बैंक खाता', 'kn' => 'ಪ್ರಾಥಮಿಕ ಬ್ಯಾಂಕ್ ಖಾತೆ', 'ml' => 'പ്രൈമറി ബാങ്ക് അക്കൗണ്ട്', 
        'gu' => 'પ્રાથમિક બેંક ખાતું', 'mr' => 'प्राथमिक बँक खाते', 'ta' => 'முதன்மை வங்கி கணக்கு', 'te' => 'ప్రాథమిక బ్యాంక్ ఖాతా'
    ],
    'Add Bank Account' => [
        'en' => 'Add Bank Account', 'hi' => 'बैंक खाता जोड़ें', 'kn' => 'ಬ್ಯಾಂಕ್ ಖಾತೆಯನ್ನು ಸೇರಿಸಿ', 'ml' => 'ബാങ്ക് അക്കൗണ്ട് ചേർക്കുക', 
        'gu' => 'બેંક ખાતું ઉમેરો', 'mr' => 'बँक खाते जोडा', 'ta' => 'வங்கி கணக்கைச் சேர்க்கவும்', 'te' => 'బ్యాంక్ ఖాతాను జోడించండి'
    ],
    'Incomplete' => [
        'en' => 'Incomplete', 'hi' => 'अपूर्ण', 'kn' => 'ಅಪೂರ್ಣ', 'ml' => 'അപൂർണ്ണമാണ്', 
        'gu' => 'અપૂર્ણ', 'mr' => 'अपूर्ण', 'ta' => 'முழுமையடையவில்லை', 'te' => 'అసంపూర్ణ'
    ],
    'Verified UPI Identity' => [
        'en' => 'Verified UPI Identity', 'hi' => 'सत्यापित यूपीआई पहचान', 'kn' => 'ಪರಿಶೀಲಿಸಲಾದ UPI ಗುರುತು', 'ml' => 'പരിശോധിച്ച UPI ഐഡൻ്റിറ്റി', 
        'gu' => 'ચકાસાયેલ UPI ઓળખ', 'mr' => 'सत्यापित UPI ओळख', 'ta' => 'சரிபார்க்கப்பட்ட UPI அடையாளம்', 'te' => 'ధృవీకరించబడిన UPI గుర్తింపు'
    ],
    'Approved' => [
        'en' => 'Approved', 'hi' => 'मंज़ूर', 'kn' => 'ಅನುಮೋದಿಸಲಾಗಿದೆ', 'ml' => 'അംഗീകരിച്ചു', 
        'gu' => 'મંજૂર', 'mr' => 'मंजूर', 'ta' => 'அங்கீகரிக்கப்பட்டது', 'te' => 'ఆమోదించబడింది'
    ],
    'Pending' => [
        'en' => 'Pending', 'hi' => 'लंबित', 'kn' => 'ಬಾಕಿ ಉಳಿದಿದೆ', 'ml' => 'തീർപ്പുകൽപ്പിച്ചിട്ടില്ല', 
        'gu' => 'બાકી', 'mr' => 'प्रलंबित', 'ta' => 'நிலுவையில் உள்ளது', 'te' => 'పెండింగ్‌లో ఉంది'
    ],
    'Direct Transfer Apps' => [
        'en' => 'Direct Transfer Apps', 'hi' => 'सीधे ट्रांसफर ऐप्स', 'kn' => 'ನೇರ ವರ್ಗಾವಣೆ ಅಪ್ಲಿಕೇಶನ್‌ಗಳು', 'ml' => 'നേരിട്ടുള്ള ട്രാൻസ്ഫർ ആപ്പുകൾ', 
        'gu' => 'ડાયરેક્ટ ટ્રાન્સફર એપ્સ', 'mr' => 'थेट हस्तांतरण अॅप्स', 'ta' => 'நேரடி பரிமாற்ற பயன்பாடுகள்', 'te' => 'డైరెక్ట్ బదిలీ యాప్‌లు'
    ],
    'PhonePe' => [
        'en' => 'PhonePe', 'hi' => 'फोनपे', 'kn' => 'PhonePe', 'ml' => 'PhonePe', 
        'gu' => 'PhonePe', 'mr' => 'PhonePe', 'ta' => 'PhonePe', 'te' => 'PhonePe'
    ],
    'Linked' => [
        'en' => 'Linked', 'hi' => 'जुड़ा हुआ', 'kn' => 'ಲಿಂಕ್ ಮಾಡಲಾಗಿದೆ', 'ml' => 'ലിങ്ക് ചെയ്തു', 
        'gu' => 'જોડાયેલ', 'mr' => 'लिंक केलेले', 'ta' => 'இணைக்கப்பட்டுள்ளது', 'te' => 'లింక్ చేయబడింది'
    ],
    'G-Pay' => [
        'en' => 'G-Pay', 'hi' => 'जी-पे', 'kn' => 'G-Pay', 'ml' => 'G-Pay', 
        'gu' => 'G-Pay', 'mr' => 'G-Pay', 'ta' => 'G-Pay', 'te' => 'G-Pay'
    ],
    'Paytm' => [
        'en' => 'Paytm', 'hi' => 'पेटीएम', 'kn' => 'Paytm', 'ml' => 'Paytm', 
        'gu' => 'Paytm', 'mr' => 'Paytm', 'ta' => 'Paytm', 'te' => 'Paytm'
    ],
    'BHIM' => [
        'en' => 'BHIM', 'hi' => 'भीम', 'kn' => 'BHIM', 'ml' => 'BHIM', 
        'gu' => 'BHIM', 'mr' => 'BHIM', 'ta' => 'BHIM', 'te' => 'BHIM'
    ],
    'Add Now' => [
        'en' => 'Add Now', 'hi' => 'अभी जोड़ें', 'kn' => 'ಈಗ ಸೇರಿಸಿ', 'ml' => 'ഇപ്പോൾ ചേർക്കുക', 
        'gu' => 'હવે ઉમેરો', 'mr' => 'आता जोडा', 'ta' => 'இப்போது சேர்', 'te' => 'ఇప్పుడే జోడించండి'
    ],
    'Submit UPI QR' => [
        'en' => 'Submit UPI QR', 'hi' => 'यूपीआई क्यूआर सबमिट करें', 'kn' => 'UPI QR ಸಲ್ಲಿಸಿ', 'ml' => 'UPI QR സമർപ്പിക്കുക', 
        'gu' => 'UPI QR સબમિટ કરો', 'mr' => 'UPI QR सबमिट करा', 'ta' => 'UPI QR சமர்ப்பிக்கவும்', 'te' => 'UPI QR సమర్పించండి'
    ],
    'View Bank Details' => [
        'en' => 'View Bank Details', 'hi' => 'बैंक विवरण देखें', 'kn' => 'ಬ್ಯಾಂಕ್ ವಿವರಗಳನ್ನು ವೀಕ್ಷಿಸಿ', 'ml' => 'ബാങ്ക് വിശദാംശങ്ങൾ കാണുക', 
        'gu' => 'બેંક વિગતો જુઓ', 'mr' => 'बँक तपशील पहा', 'ta' => 'வங்கி விவரங்களைப் பார்க்கவும்', 'te' => 'బ్యాంక్ వివరాలను వీక్షించండి'
    ],
    'View UPI QR' => [
        'en' => 'View UPI QR', 'hi' => 'यूपीआई क्यूआर देखें', 'kn' => 'UPI QR ವೀಕ್ಷಿಸಿ', 'ml' => 'UPI QR കാണുക', 
        'gu' => 'UPI QR જુઓ', 'mr' => 'UPI QR पहा', 'ta' => 'UPI QR ஐப் பார்க்கவும்', 'te' => 'UPI QR వీక్షించండి'
    ],
    'KYC LOCKED' => [
        'en' => 'KYC LOCKED', 'hi' => 'केवाईसी लॉक है', 'kn' => 'KYC ಲಾಕ್ ಆಗಿದೆ', 'ml' => 'KYC ലോക്ക് ചെയ്‌തു', 
        'gu' => 'KYC લૉક થયેલ છે', 'mr' => 'केवायसी लॉक आहे', 'ta' => 'KYC பூட்டப்பட்டுள்ளது', 'te' => 'KYC లాక్ చేయబడింది'
    ],
    'SUN' => [
";

if (strpos($content, "'KYC & BANK ACCESS' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added bank translations to lang.php.\n";
}

$bankFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_bank.php';
$bankContent = file_get_contents($bankFile);

$replacements = [
    '<div class="section-title">KYC & BANK ACCESS</div>' => '<div class="section-title"><?= __("KYC & BANK ACCESS") ?></div>',
    '<label class="card-label">Primary Bank Account</label>' => '<label class="card-label"><?= __("Primary Bank Account") ?></label>',
    '<span class="card-value"><?= $bankStatus === \'Incomplete\' ? \'Add Bank Account\' : ($bankData[\'bank_name\'] ?? \'View Bank Details\') ?></span>' => '<span class="card-value"><?= $bankStatus === \'Incomplete\' ? __("Add Bank Account") : ($bankData[\'bank_name\'] ?? __("View Bank Details")) ?></span>',
    '<label class="card-label">Verified UPI Identity</label>' => '<label class="card-label"><?= __("Verified UPI Identity") ?></label>',
    '<span class="card-value"><?= $upiStatus === \'Incomplete\' ? \'Submit UPI QR\' : ($upiData[\'upi_id\'] ?? \'View UPI QR\') ?></span>' => '<span class="card-value"><?= $upiStatus === \'Incomplete\' ? __("Submit UPI QR") : ($upiData[\'upi_id\'] ?? __("View UPI QR")) ?></span>',
    '<div class="section-title" style="margin-top:30px;">Direct Transfer Apps</div>' => '<div class="section-title" style="margin-top:30px;"><?= __("Direct Transfer Apps") ?></div>',
    '\'PhonePe\'' => '__(\'PhonePe\')',
    '\'G-Pay\'' => '__(\'G-Pay\')',
    '\'Paytm\'' => '__(\'Paytm\')',
    '\'BHIM\'' => '__(\'BHIM\')',
    '<?= $exists ? \'Linked\':\'Add Now\' ?>' => '<?= $exists ? __("Linked") : __("Add Now") ?>',
    '<?= strtoupper($bankStatus) ?>' => '<?= strtoupper(__($bankStatus)) ?>',
    '<?= strtoupper($upiStatus) ?>' => '<?= strtoupper(__($upiStatus)) ?>',
    '<h2 style="color:#000; font-weight:900;">KYC LOCKED</h2>' => '<h2 style="color:#000; font-weight:900;"><?= __("KYC LOCKED") ?></h2>',
    '<h3>Add Bank Account</h3>' => '<h3><?= __("Add Bank Account") ?></h3>'
];

foreach ($replacements as $search => $replace) {
    $bankContent = str_replace($search, $replace, $bankContent);
}

// Since the phonepe/gpay names might be in an array definition, let's fix it safely:
// ['id'=>'phonepe', 'name'=>'PhonePe', 'img'=>'images/phonepe.png'] => ... 'name'=>__('PhonePe') ...
$bankContent = str_replace("['id'=>'phonepe', 'name'=>'PhonePe', 'img'=>'images/phonepe.png']", "['id'=>'phonepe', 'name'=>__('PhonePe'), 'img'=>'images/phonepe.png']", $bankContent);
$bankContent = str_replace("['id'=>'gpay', 'name'=>'G-Pay', 'img'=>'images/googlepay.png']", "['id'=>'gpay', 'name'=>__('G-Pay'), 'img'=>'images/googlepay.png']", $bankContent);
$bankContent = str_replace("['id'=>'paytm', 'name'=>'Paytm', 'img'=>'images/paytm.png']", "['id'=>'paytm', 'name'=>__('Paytm'), 'img'=>'images/paytm.png']", $bankContent);

file_put_contents($bankFile, $bankContent);
echo "user_bank.php translations updated.";
