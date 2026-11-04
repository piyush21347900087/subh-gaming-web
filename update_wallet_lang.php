<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Add Money / Min Deposit' => [
        'en' => 'Add Money / Min Deposit', 'hi' => 'पैसे डालें / Min Deposit', 'kn' => 'ಹಣ ಸೇರಿಸಿ / ಕನಿಷ್ಠ ಠೇವಣಿ', 'ml' => 'പണം ചേർക്കുക / കുറഞ്ഞ നിക്ഷേപം', 
        'gu' => 'પૈસા ઉમેરો / ન્યૂનતમ જમા', 'mr' => 'पैसे जोडा / किमान ठेव', 'ta' => 'பணம் சேர் / குறைந்தபட்ச வைப்பு', 'te' => 'డబ్బు జోడించండి / కనీస డిపాజిట్'
    ],
    'Withdraw Money / Min Withdraw' => [
        'en' => 'Withdraw Money / Min Withdraw', 'hi' => 'पैसे निकालें / Min Withdraw', 'kn' => 'ಹಣ ಹಿಂಪಡೆಯಿರಿ / ಕನಿಷ್ಠ ಹಿಂಪಡೆಯುವಿಕೆ', 'ml' => 'പണം പിൻവലിക്കുക / കുറഞ്ഞ പിൻവലിക്കൽ', 
        'gu' => 'પૈસા ઉપાડો / ન્યૂનતમ ઉપાડ', 'mr' => 'पैसे काढा / किमान काढणे', 'ta' => 'பணம் எடு / குறைந்தபட்சம் எடு', 'te' => 'డబ్బు ఉపసంహరించు / కనీస ఉపసంహరణ'
    ],
    'ADD FUND' => [
        'en' => 'ADD FUND', 'hi' => 'फंड जोड़ें', 'kn' => 'ಫಂಡ್ ಸೇರಿಸಿ', 'ml' => 'ഫണ്ട് ചേർക്കുക', 
        'gu' => 'ફંડ ઉમેરો', 'mr' => 'फंड जोडा', 'ta' => 'நிதியைச் சேர்', 'te' => 'ఫండ్ జోడించండి'
    ],
    'Add fund' => [
        'en' => 'Add fund', 'hi' => 'फंड जोड़ें', 'kn' => 'ಫಂಡ್ ಸೇರಿಸಿ', 'ml' => 'ഫണ്ട് ചേർക്കുക', 
        'gu' => 'ફંડ ઉમેરો', 'mr' => 'फंड जोडा', 'ta' => 'நிதியைச் சேர்', 'te' => 'ఫండ్ జోడించండి'
    ],
    'WITHDRAW FUND' => [
        'en' => 'WITHDRAW FUND', 'hi' => 'फंड निकालें', 'kn' => 'ಫಂಡ್ ಹಿಂಪಡೆಯಿರಿ', 'ml' => 'ഫണ്ട് പിൻവലിക്കുക', 
        'gu' => 'ફંડ ઉપાડો', 'mr' => 'फंड काढा', 'ta' => 'நிதியை எடுக்கவும்', 'te' => 'ఫండ్ ఉపసంహరించండి'
    ],
    'Withdraw Fund' => [
        'en' => 'Withdraw Fund', 'hi' => 'फंड निकालें', 'kn' => 'ಫಂಡ್ ಹಿಂಪಡೆಯಿರಿ', 'ml' => 'ഫണ്ട് പിൻവലിക്കുക', 
        'gu' => 'ફંડ ઉપાડો', 'mr' => 'फंड काढा', 'ta' => 'நிதியை எடுக்கவும்', 'te' => 'ఫండ్ ఉపసంహరించండి'
    ],
    'ADD BANK DETAILS' => [
        'en' => 'ADD BANK DETAILS', 'hi' => 'बैंक विवरण जोड़ें', 'kn' => 'ಬ್ಯಾಂಕ್ ವಿವರಗಳನ್ನು ಸೇರಿಸಿ', 'ml' => 'ബാങ്ക് വിശദാംശങ്ങൾ ചേർക്കുക', 
        'gu' => 'બેંક વિગતો ઉમેરો', 'mr' => 'बँक तपशील जोडा', 'ta' => 'வங்கி விவரங்களைச் சேர்', 'te' => 'బ్యాంక్ వివరాలను జోడించండి'
    ],
    'Add your Bank details' => [
        'en' => 'Add your Bank details', 'hi' => 'अपना बैंक विवरण जोड़ें', 'kn' => 'ನಿಮ್ಮ ಬ್ಯಾಂಕ್ ವಿವರಗಳನ್ನು ಸೇರಿಸಿ', 'ml' => 'നിങ്ങളുടെ ബാങ്ക് വിശദാംശങ്ങൾ ചേർക്കുക', 
        'gu' => 'તમારી બેંક વિગતો ઉમેરો', 'mr' => 'तुमचे बँक तपशील जोडा', 'ta' => 'உங்கள் வங்கி விவரங்களைச் சேர்க்கவும்', 'te' => 'మీ బ్యాంక్ వివరాలను జోడించండి'
    ],
    'DEPOSIT HISTORY' => [
        'en' => 'DEPOSIT HISTORY', 'hi' => 'जमा का इतिहास', 'kn' => 'ಠೇವಣಿ ಇತಿಹಾಸ', 'ml' => 'നിക്ഷേപ ചരിത്രം', 
        'gu' => 'જમા ઇતિહાસ', 'mr' => 'ठेव इतिहास', 'ta' => 'வைப்பு வரலாறு', 'te' => 'డిపాజిట్ చరిత్ర'
    ],
    'View your deposit history' => [
        'en' => 'View your deposit history', 'hi' => 'अपना जमा इतिहास देखें', 'kn' => 'ನಿಮ್ಮ ಠೇವಣಿ ಇತಿಹಾಸವನ್ನು ವೀಕ್ಷಿಸಿ', 'ml' => 'നിങ്ങളുടെ നിക്ഷേപ ചരിത്രം കാണുക', 
        'gu' => 'તમારો જમા ઇતિહાસ જુઓ', 'mr' => 'तुमचा ठेव इतिहास पहा', 'ta' => 'உங்கள் வைப்பு வரலாற்றைக் காண்க', 'te' => 'మీ డిపాజిట్ చరిత్రను వీక్షించండి'
    ],
    'WITHDRAWAL HISTORY' => [
        'en' => 'WITHDRAWAL HISTORY', 'hi' => 'निकासी का इतिहास', 'kn' => 'ಹಿಂಪಡೆಯುವಿಕೆಯ ಇತಿಹಾಸ', 'ml' => 'പിൻവലിക്കൽ ചരിത്രം', 
        'gu' => 'ઉપાડનો ઇતિહાસ', 'mr' => 'काढण्याचा इतिहास', 'ta' => 'திரும்பப் பெறுதல் வரலாறு', 'te' => 'ఉపసంహరణ చరిత్ర'
    ],
    'View your withdraw history' => [
        'en' => 'View your withdraw history', 'hi' => 'अपना निकासी इतिहास देखें', 'kn' => 'ನಿಮ್ಮ ಹಿಂಪಡೆಯುವಿಕೆಯ ಇತಿಹಾಸವನ್ನು ವೀಕ್ಷಿಸಿ', 'ml' => 'നിങ്ങളുടെ പിൻവലിക്കൽ ചരിത്രം കാണുക', 
        'gu' => 'તમારો ઉપાડનો ઇતિહાસ જુઓ', 'mr' => 'तुमचा काढण्याचा इतिहास पहा', 'ta' => 'உங்கள் திரும்பப் பெறுதல் வரலாற்றைக் காண்க', 'te' => 'మీ ఉపసంహరణ చరిత్రను వీక్షించండి'
    ],
    'Withdrawal allowed from 11:00 AM to 8:00 PM only.' => [
        'en' => 'Withdrawal allowed from 11:00 AM to 8:00 PM only.', 'hi' => 'निकासी केवल सुबह 11:00 बजे से रात 8:00 बजे तक अनुमत है।', 'kn' => 'ಬೆಳಿಗ್ಗೆ 11:00 ರಿಂದ ರಾತ್ರಿ 8:00 ರವರೆಗೆ ಮಾತ್ರ ಹಿಂಪಡೆಯಲು ಅವಕಾಶವಿದೆ.', 'ml' => 'രാവിലെ 11:00 മുതൽ രാത്രി 8:00 വരെ മാത്രമേ പിൻവലിക്കാൻ അനുവാദമുള്ളൂ.', 
        'gu' => 'ઉપાડ માત્ર સવારે 11:00 થી રાત્રે 8:00 વાગ્યા સુધી જ માન્ય છે.', 'mr' => 'पैसे काढण्याची परवानगी फक्त सकाळी 11:00 ते रात्री 8:00 वाजेपर्यंत आहे.', 'ta' => 'காலை 11:00 மணி முதல் இரவு 8:00 மணி வரை மட்டுமே பணம் எடுக்க அனுமதிக்கப்படும்.', 'te' => 'ఉదయం 11:00 నుండి రాత్రి 8:00 వరకు మాత్రమే ఉపసంహరణ అనుమతించబడుతుంది.'
    ],
    'Withdrawal are available every day from 6:00 AM to 11:00 AM.' => [
        'en' => 'Withdrawal are available every day from 6:00 AM to 11:00 AM.', 'hi' => 'निकासी हर दिन सुबह 6:00 बजे से सुबह 11:00 बजे तक उपलब्ध है।', 'kn' => 'ಹಿಂಪಡೆಯುವಿಕೆಯು ಪ್ರತಿದಿನ ಬೆಳಿಗ್ಗೆ 6:00 ರಿಂದ ಬೆಳಿಗ್ಗೆ 11:00 ರವರೆಗೆ ಲಭ್ಯವಿದೆ.', 'ml' => 'പിൻവലിക്കൽ എല്ലാ ദിവസവും രാവിലെ 6:00 മുതൽ 11:00 വരെ ലഭ്യമാണ്.', 
        'gu' => 'ઉપાડ દરરોજ સવારે 6:00 થી 11:00 વાગ્યા સુધી ઉપલબ્ધ છે.', 'mr' => 'पैसे काढण्याची सुविधा दररोज सकाळी 6:00 ते 11:00 वाजेपर्यंत उपलब्ध आहे.', 'ta' => 'காலை 6:00 மணி முதல் 11:00 மணி வரை ஒவ்வொரு நாளும் பணம் எடுக்க கிடைக்கும்.', 'te' => 'ఉదయం 6:00 నుండి 11:00 వరకు ప్రతిరోజూ ఉపసంహరణ అందుబాటులో ఉంటుంది.'
    ],
    'SUN' => [
";

if (strpos($content, "'ADD FUND' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added user_wallet translations to lang.php.\n";
}

$walletFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/deposit.php';
$walletContent = file_get_contents($walletFile);

$replacements = [
    '<div class="hindi-label">पैसे डालें / Min Deposit ₹<?= (int) $MIN_DEPOSIT ?></div>' => '<div class="hindi-label"><?= __("Add Money / Min Deposit") ?> ₹<?= (int) $MIN_DEPOSIT ?></div>',
    '<div class="hindi-label">पैसे निकालें / Min Withdraw ₹<?= (int) $MIN_WITHDRAW ?></div>' => '<div class="hindi-label"><?= __("Withdraw Money / Min Withdraw") ?> ₹<?= (int) $MIN_WITHDRAW ?></div>',
    '>ADD FUND<' => '><?= __("ADD FUND") ?><',
    '>Add fund<' => '><?= __("Add fund") ?><',
    '>WITHDRAW FUND<' => '><?= __("WITHDRAW FUND") ?><',
    '>Withdraw Fund<' => '><?= __("Withdraw Fund") ?><',
    '>ADD BANK DETAILS<' => '><?= __("ADD BANK DETAILS") ?><',
    '>Add your Bank details<' => '><?= __("Add your Bank details") ?><',
    '>DEPOSIT HISTORY<' => '><?= __("DEPOSIT HISTORY") ?><',
    '>View your deposit history<' => '><?= __("View your deposit history") ?><',
    '>WITHDRAWAL HISTORY<' => '><?= __("WITHDRAWAL HISTORY") ?><',
    '>View your withdraw history<' => '><?= __("View your withdraw history") ?><',
    '<div class="marquee-content"><?= htmlspecialchars($marqueeText) ?></div>' => '<div class="marquee-content"><?= htmlspecialchars(__($marqueeText)) ?></div>'
];

foreach ($replacements as $search => $replace) {
    $walletContent = str_replace($search, $replace, $walletContent);
}

file_put_contents($walletFile, $walletContent);
echo "deposit.php strings updated.";
