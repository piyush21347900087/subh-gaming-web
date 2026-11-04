<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$newTranslations = "    'My Wallet' => [
        'en' => 'My Wallet', 'hi' => 'मेरा वॉलेट', 'kn' => 'ನನ್ನ ವಾಲೆಟ್', 'ml' => 'എന്റെ വാലറ്റ്', 
        'gu' => 'મારું વૉલેટ', 'mr' => 'माझे वॉलेट', 'ta' => 'என் வாலட்', 'te' => 'నా వాలెట్'
    ],
    'Winning History' => [
        'en' => 'Winning History', 'hi' => 'जीत का इतिहास', 'kn' => 'ಗೆಲುವಿನ ಇತಿಹಾಸ', 'ml' => 'വിജയ ചരിത്രം', 
        'gu' => 'જીતનો ઇતિહાસ', 'mr' => 'जिंकण्याचा इतिहास', 'ta' => 'வெற்றி வரலாறு', 'te' => 'విజయం చరిత్ర'
    ],
    'Share App' => [
        'en' => 'Share App', 'hi' => 'ऐप शेयर करें', 'kn' => 'ಆಪ್ ಹಂಚಿಕೊಳ್ಳಿ', 'ml' => 'ആപ്പ് പങ്കിടുക', 
        'gu' => 'એપ શેર કરો', 'mr' => 'ॲप शेअर करा', 'ta' => 'ஆப் பகிரவும்', 'te' => 'యాప్ షేర్ చేయండి'
    ],
    'My Bids' => [
        'en' => 'My Bids', 'hi' => 'मेरी बिड्स', 'kn' => 'ನನ್ನ ಬಿಡ್ಸ್', 'ml' => 'എന്റെ ബിഡുകൾ', 
        'gu' => 'મારી બિડ્સ', 'mr' => 'माझ्या बिड्स', 'ta' => 'என் பிட்ஸ்', 'te' => 'నా బిడ్స్'
    ],
    'Funds' => [
        'en' => 'Funds', 'hi' => 'फंड्स', 'kn' => 'ಫಂಡ್ಸ್', 'ml' => 'ഫണ്ടുകൾ', 
        'gu' => 'ફંડ્સ', 'mr' => 'फंड्स', 'ta' => 'நிதிகள்', 'te' => 'ఫండ్స్'
    ],
    'Bank' => [
        'en' => 'Bank', 'hi' => 'बैंक', 'kn' => 'ಬ್ಯಾಂಕ್', 'ml' => 'ബാങ്ക്', 
        'gu' => 'બેંક', 'mr' => 'बँक', 'ta' => 'வங்கி', 'te' => 'బ్యాంక్'
    ],
    'Profile' => [
        'en' => 'Profile', 'hi' => 'प्रोफाइल', 'kn' => 'ಪ್ರೊಫೈಲ್', 'ml' => 'പ്രൊഫൈൽ', 
        'gu' => 'પ્રોફાઇલ', 'mr' => 'प्रोफाईल', 'ta' => 'சுயவிவரம்', 'te' => 'ప్రొఫైల్'
    ],
    'HOME' => [
        'en' => 'HOME', 'hi' => 'होम', 'kn' => 'ಮುಖಪುಟ', 'ml' => 'ഹോം', 
        'gu' => 'હોમ', 'mr' => 'मुख्यपृष्ठ', 'ta' => 'முகப்பு', 'te' => 'హోమ్'
    ]
];";

$content = str_replace('];', $newTranslations, $content);

file_put_contents($file, $content);
echo "lang.php updated with bottom/side menu translations.";
