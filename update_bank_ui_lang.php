<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Rejected' => [
        'en' => 'Rejected', 'hi' => 'अस्वीकृत', 'kn' => 'ತಿರಸ್ಕರಿಸಲಾಗಿದೆ', 'ml' => 'നിരസിച്ചു', 
        'gu' => 'નકારવામાં આવ્યું', 'mr' => 'नाकारले', 'ta' => 'நிராகரிக்கப்பட்டது', 'te' => 'తిరస్కరించబడింది'
    ],
    'Branch Name' => [
        'en' => 'Branch Name', 'hi' => 'शाखा का नाम', 'kn' => 'ಶಾಖೆಯ ಹೆಸರು', 'ml' => 'ശാഖയുടെ പേര്', 
        'gu' => 'શાખાનું નામ', 'mr' => 'शाखेचे नाव', 'ta' => 'கிளை பெயர்', 'te' => 'బ్రాంచ్ పేరు'
    ],
    'Branch City' => [
        'en' => 'Branch City', 'hi' => 'शाखा शहर', 'kn' => 'ಶಾಖೆಯ ನಗರ', 'ml' => 'ബ്രാഞ്ച് സിറ്റി', 
        'gu' => 'શાખા શહેર', 'mr' => 'शाखा शहर', 'ta' => 'கிளை நகரம்', 'te' => 'బ్రాంచ్ నగరం'
    ],
    'Your Name' => [
        'en' => 'Your Name', 'hi' => 'आपका नाम', 'kn' => 'ನಿಮ್ಮ ಹೆಸರು', 'ml' => 'നിങ്ങളുടെ പേര്', 
        'gu' => 'તમારું નામ', 'mr' => 'तुमचे नाव', 'ta' => 'உங்கள் பெயர்', 'te' => 'మీ పేరు'
    ],
    'Acc Number' => [
        'en' => 'Acc Number', 'hi' => 'खाता संख्या', 'kn' => 'ಖಾತೆ ಸಂಖ್ಯೆ', 'ml' => 'അക്കൗണ്ട് നമ്പർ', 
        'gu' => 'ખાતા નંબર', 'mr' => 'खाते क्रमांक', 'ta' => 'கணக்கு எண்', 'te' => 'ఖాతా సంఖ్య'
    ],
    '11 Digit Code' => [
        'en' => '11 Digit Code', 'hi' => '11 अंकों का कोड', 'kn' => '11 ಅಂಕಿಯ ಕೋಡ್', 'ml' => '11 അക്ക കോഡ്', 
        'gu' => '11 અંકનો કોડ', 'mr' => '११ अंकी कोड', 'ta' => '11 இலக்க குறியீடு', 'te' => '11 అంకెల కోడ్'
    ],
    'SAVE DETAILS' => [
        'en' => 'SAVE DETAILS', 'hi' => 'विवरण सहेजें', 'kn' => 'ವಿವರಗಳನ್ನು ಉಳಿಸಿ', 'ml' => 'വിശദാംശങ്ങൾ സംരക്ഷിക്കുക', 
        'gu' => 'વિગતો સાચવો', 'mr' => 'तपशील जतन करा', 'ta' => 'விவரங்களைச் சேமி', 'te' => 'వివరాలను సేవ్ చేయండి'
    ],
    'CLOSE' => [
        'en' => 'CLOSE', 'hi' => 'बंद करें', 'kn' => 'ಮುಚ್ಚಿ', 'ml' => 'അടയ്ക്കുക', 
        'gu' => 'બંધ કરો', 'mr' => 'बंद करा', 'ta' => 'மூடு', 'te' => 'మూసివేయు'
    ],
    'EDIT DETAILS' => [
        'en' => 'EDIT DETAILS', 'hi' => 'विवरण संपादित करें', 'kn' => 'ವಿವರಗಳನ್ನು ಸಂಪಾದಿಸಿ', 'ml' => 'വിശദാംശങ്ങൾ എഡിറ്റുചെയ്യുക', 
        'gu' => 'વિગતો સંપાદિત કરો', 'mr' => 'तपशील संपादित करा', 'ta' => 'விவரங்களைத் திருத்து', 'te' => 'వివరాలను సవరించండి'
    ],
    'CHANGE / RE-UPLOAD' => [
        'en' => 'CHANGE / RE-UPLOAD', 'hi' => 'बदलें / फिर से अपलोड करें', 'kn' => 'ಬದಲಾಯಿಸಿ / ಮರು-ಅಪ್‌ಲೋಡ್ ಮಾಡಿ', 'ml' => 'മാറ്റുക / വീണ്ടും അപ്‌ലോഡ് ചെയ്യുക', 
        'gu' => 'બદલો / ફરીથી અપલોડ કરો', 'mr' => 'बदला / पुन्हा अपलोड करा', 'ta' => 'மாற்று / மீண்டும் பதிவேற்றவும்', 'te' => 'మార్చండి / మళ్లీ అప్‌లోడ్ చేయండి'
    ],
    '10 Digit Number' => [
        'en' => '10 Digit Number', 'hi' => '10 अंकों का नंबर', 'kn' => '10 ಅಂಕಿಯ ಸಂಖ್ಯೆ', 'ml' => '10 അക്ക നമ്പർ', 
        'gu' => '10 અંકનો નંબર', 'mr' => '१० अंकी क्रमांक', 'ta' => '10 இலக்க எண்', 'te' => '10 అంకెల సంఖ్య'
    ],
    'LINK NOW' => [
        'en' => 'LINK NOW', 'hi' => 'अभी लिंक करें', 'kn' => 'ಈಗ ಲಿಂಕ್ ಮಾಡಿ', 'ml' => 'ഇപ്പോൾ ലിങ്ക് ചെയ്യുക', 
        'gu' => 'હવે લિંક કરો', 'mr' => 'आता लिंक करा', 'ta' => 'இப்போது இணைக்கவும்', 'te' => 'ఇప్పుడే లింక్ చేయండి'
    ],
    'CANCEL' => [
        'en' => 'CANCEL', 'hi' => 'रद्द करें', 'kn' => 'ರದ್ದುಮಾಡಿ', 'ml' => 'റദ്ദാക്കുക', 
        'gu' => 'રદ કરો', 'mr' => 'रद्द करा', 'ta' => 'ரத்து செய்', 'te' => 'రద్దు చేయండి'
    ],
    'Holder' => [
        'en' => 'Holder', 'hi' => 'धारक', 'kn' => 'ಹೊಂದಿರುವವರು', 'ml' => 'ഉടമ', 
        'gu' => 'ધારક', 'mr' => 'धारक', 'ta' => 'வைத்திருப்பவர்', 'te' => 'హోల్డర్'
    ],
    'Branch' => [
        'en' => 'Branch', 'hi' => 'शाखा', 'kn' => 'ಶಾಖೆ', 'ml' => 'ശാഖ', 
        'gu' => 'શાખા', 'mr' => 'शाखा', 'ta' => 'கிளை', 'te' => 'బ్రాంచ్'
    ],
    'Bank Details' => [
        'en' => 'Bank Details', 'hi' => 'बैंक विवरण', 'kn' => 'ಬ್ಯಾಂಕ್ ವಿವರಗಳು', 'ml' => 'ബാങ്ക് വിശദാംശങ്ങൾ', 
        'gu' => 'બેંક વિગતો', 'mr' => 'बँक तपशील', 'ta' => 'வங்கி விவரங்கள்', 'te' => 'బ్యాంక్ వివరాలు'
    ],
    'Update UPI/QR' => [
        'en' => 'Update UPI/QR', 'hi' => 'यूपीआई/क्यूआर अपडेट करें', 'kn' => 'UPI/QR ನವೀಕರಿಸಿ', 'ml' => 'UPI/QR അപ്ഡേറ്റ് ചെയ്യുക', 
        'gu' => 'UPI/QR અપડેટ કરો', 'mr' => 'UPI/QR अपडेट करा', 'ta' => 'UPI/QR ஐப் புதுப்பிக்கவும்', 'te' => 'UPI/QR అప్‌డేట్ చేయండి'
    ],
    'UPI Details' => [
        'en' => 'UPI Details', 'hi' => 'यूपीआई विवरण', 'kn' => 'UPI ವಿವರಗಳು', 'ml' => 'UPI വിശദാംശങ്ങൾ', 
        'gu' => 'UPI વિગતો', 'mr' => 'UPI तपशील', 'ta' => 'UPI விவரங்கள்', 'te' => 'UPI వివరాలు'
    ],
    'Link App' => [
        'en' => 'Link App', 'hi' => 'ऐप लिंक करें', 'kn' => 'ಅಪ್ಲಿಕೇಶನ್ ಲಿಂಕ್ ಮಾಡಿ', 'ml' => 'ലിങ്ക് ആപ്പ്', 
        'gu' => 'લિંક એપ', 'mr' => 'अॅप लिंक करा', 'ta' => 'பயன்பாட்டை இணைக்கவும்', 'te' => 'లింక్ యాప్'
    ],
    'No File Selected' => [
        'en' => 'No File Selected', 'hi' => 'कोई फ़ाइल नहीं चुनी गई', 'kn' => 'ಯಾವುದೇ ಫೈಲ್ ಆಯ್ಕೆ ಮಾಡಲಾಗಿಲ್ಲ', 'ml' => 'ഫയലൊന്നും തിരഞ്ഞെടുത്തിട്ടില്ല', 
        'gu' => 'કોઈ ફાઇલ પસંદ કરી નથી', 'mr' => 'कोणतीही फाईल निवडलेली नाही', 'ta' => 'எந்த கோப்பும் தேர்ந்தெடுக்கப்படவில்லை', 'te' => 'ఏ ఫైల్ ఎంచుకోబడలేదు'
    ],
    'SUN' => [
";

if (strpos($content, "'Rejected' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added new bank UI texts to lang.php.\n";
}

$bankFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_bank.php';
$bankContent = file_get_contents($bankFile);

$replacements = [
    '<label>Branch Name</label>' => '<label><?= __("Branch Name") ?></label>',
    'placeholder="Branch City"' => 'placeholder="<?= __("Branch City") ?>"',
    'placeholder="Your Name"' => 'placeholder="<?= __("Your Name") ?>"',
    'placeholder="Acc Number"' => 'placeholder="<?= __("Acc Number") ?>"',
    'placeholder="11 Digit Code"' => 'placeholder="<?= __("11 Digit Code") ?>"',
    '>SAVE DETAILS<' => '><?= __("SAVE DETAILS") ?><',
    '>CLOSE<' => '><?= __("CLOSE") ?><',
    '>EDIT DETAILS<' => '><?= __("EDIT DETAILS") ?><',
    '>CHANGE / RE-UPLOAD<' => '><?= __("CHANGE / RE-UPLOAD") ?><',
    '>CANCEL<' => '><?= __("CANCEL") ?><',
    '>LINK NOW<' => '><?= __("LINK NOW") ?><',
    'placeholder="10 Digit Number"' => 'placeholder="<?= __("10 Digit Number") ?>"',
    '<b>Holder</b>' => '<b><?= __("Holder") ?></b>',
    '<b>Acc Number</b>' => '<b><?= __("Acc Number") ?></b>',
    '<b>Branch</b>' => '<b><?= __("Branch") ?></b>',
    '<h3>Bank Details</h3>' => '<h3><?= __("Bank Details") ?></h3>',
    '<h3>Update UPI/QR</h3>' => '<h3><?= __("Update UPI/QR") ?></h3>',
    '<h3>UPI Details</h3>' => '<h3><?= __("UPI Details") ?></h3>',
    '<h3 id="miniTitle">Link App</h3>' => '<h3 id="miniTitle"><?= __("Link App") ?></h3>',
    '>No File Selected<' => '><?= __("No File Selected") ?><'
];

foreach ($replacements as $search => $replace) {
    $bankContent = str_replace($search, $replace, $bankContent);
}

file_put_contents($bankFile, $bankContent);
echo "user_bank.php UI strings updated.";
