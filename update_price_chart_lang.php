<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'SUBH GAMING Game Rates' => [
        'en' => 'SUBH GAMING Game Rates', 'hi' => 'मटका किंग गेम दरें', 'kn' => 'ಮಟ್ಕಾ ಕಿಂಗ್ ಆಟದ ದರಗಳು', 'ml' => 'മറ്റ്ക കിംഗ് ഗെയിം നിരക്കുകൾ', 
        'gu' => 'મટકા કિંગ ગેમ રેટ્સ', 'mr' => 'मटका किंग गेम दर', 'ta' => 'மட்கா கிங் விளையாட்டு விலைகள்', 'te' => 'మట్కా కింగ్ గేమ్ రేట్లు'
    ],
    'Play Type' => [
        'en' => 'Play Type', 'hi' => 'खेलने का प्रकार', 'kn' => 'ಪ್ಲೇ ಪ್ರಕಾರ', 'ml' => 'പ്ലേ തരം', 
        'gu' => 'રમવાનો પ્રકાર', 'mr' => 'खेळण्याचा प्रकार', 'ta' => 'விளையாட்டு வகை', 'te' => 'ఆట రకం'
    ],
    'Payout ₹10' => [
        'en' => 'Payout ₹10', 'hi' => 'पेआउट ₹10', 'kn' => 'ಪಾವತಿ ₹10', 'ml' => 'പേഔട്ട് ₹10', 
        'gu' => 'પેઆઉટ ₹10', 'mr' => 'पेआउट ₹10', 'ta' => 'பணம் செலுத்துதல் ₹10', 'te' => 'చెల్లింపు ₹10'
    ],
    '₹10 Wins' => [
        'en' => '₹10 Wins', 'hi' => '₹10 जीतता है', 'kn' => '₹10 ಗೆಲ್ಲುತ್ತದೆ', 'ml' => '₹10 വിജയിക്കുന്നു', 
        'gu' => '₹10 જીતે છે', 'mr' => '₹10 जिंकतो', 'ta' => '₹10 வெற்றிகள்', 'te' => '₹10 విజయాలు'
    ],
    'Single Digit' => [
        'en' => 'Single Digit', 'hi' => 'सिंगल डिजिट', 'kn' => 'ಏಕ ಅಂಕೆ', 'ml' => 'സിംഗിൾ ഡിജിറ്റ്', 
        'gu' => 'સિંગલ ડિજિટ', 'mr' => 'सिंगल डिजिट', 'ta' => 'ஒற்றை இலக்கம்', 'te' => 'సింగిల్ డిజిట్'
    ],
    'Jodi Digit' => [
        'en' => 'Jodi Digit', 'hi' => 'जोड़ी डिजिट', 'kn' => 'ಜೋಡಿ ಅಂಕೆ', 'ml' => 'ജോഡി ഡിജിറ്റ്', 
        'gu' => 'જોડી ડિજિટ', 'mr' => 'जोडी डिजिट', 'ta' => 'ஜோடி இலக்கம்', 'te' => 'జోడి డిజిట్'
    ],
    'Single Pana' => [
        'en' => 'Single Pana', 'hi' => 'सिंगल पाना', 'kn' => 'ಸಿಂಗಲ್ ಪನಾ', 'ml' => 'സിംഗിൾ പാന', 
        'gu' => 'સિંગલ પાના', 'mr' => 'सिंगल पाना', 'ta' => 'ஒற்றை பனா', 'te' => 'సింగిల్ పనా'
    ],
    'Double Pana' => [
        'en' => 'Double Pana', 'hi' => 'डबल पाना', 'kn' => 'ಡಬಲ್ ಪನಾ', 'ml' => 'ഡബിൾ പാന', 
        'gu' => 'ડબલ પાના', 'mr' => 'डबल पाना', 'ta' => 'இரட்டை பனா', 'te' => 'డబుల్ పనా'
    ],
    'Triple Pana' => [
        'en' => 'Triple Pana', 'hi' => 'ट्रिपल पाना', 'kn' => 'ಟ್ರಿಪಲ್ ಪನಾ', 'ml' => 'ട്രിപ്പിൾ പാന', 
        'gu' => 'ટ્રિપલ પાના', 'mr' => 'ट्रिपल पाना', 'ta' => 'மூன்று பனா', 'te' => 'ట్రిపుల్ పనా'
    ],
    'Half Sangam' => [
        'en' => 'Half Sangam', 'hi' => 'हाफ संगम', 'kn' => 'ಅರ್ಧ ಸಂಗಮ', 'ml' => 'ഹാഫ് സംഗം', 
        'gu' => 'હાફ સંગમ', 'mr' => 'हाफ संगम', 'ta' => 'அரை சங்கம்', 'te' => 'సగం సంగం'
    ],
    'Full Sangam' => [
        'en' => 'Full Sangam', 'hi' => 'फुल संगम', 'kn' => 'ಪೂರ್ಣ ಸಂಗಮ', 'ml' => 'ഫുൾ സംഗം', 
        'gu' => 'ફુલ સંગમ', 'mr' => 'फुल संगम', 'ta' => 'முழு சங்கம்', 'te' => 'పూర్తి సంగం'
    ],
    'SUN' => [
";

if (strpos($content, "'SUBH GAMING Game Rates' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added price_chart translations to lang.php.\n";
}

$chartFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/price_chart.php';
$chartContent = file_get_contents($chartFile);

$replacements = [
    '>SUBH GAMING Game Rates<' => '><?= __("SUBH GAMING Game Rates") ?><',
    '<th>Play Type</th>' => '<th><?= __("Play Type") ?></th>',
    '<th>Payout ₹10</th>' => '<th><?= __("Payout ₹10") ?></th>',
    '<th>₹10 Wins</th>' => '<th><?= __("₹10 Wins") ?></th>',
    '<td>Single Digit</td>' => '<td><?= __("Single Digit") ?></td>',
    '<td>Jodi Digit</td>' => '<td><?= __("Jodi Digit") ?></td>',
    '<td>Single Pana</td>' => '<td><?= __("Single Pana") ?></td>',
    '<td>Double Pana</td>' => '<td><?= __("Double Pana") ?></td>',
    '<td>Triple Pana</td>' => '<td><?= __("Triple Pana") ?></td>',
    '<td>Half Sangam</td>' => '<td><?= __("Half Sangam") ?></td>',
    '<td>Full Sangam</td>' => '<td><?= __("Full Sangam") ?></td>'
];

foreach ($replacements as $search => $replace) {
    if (strpos($chartContent, $replace) === false) {
        $chartContent = str_replace($search, $replace, $chartContent);
    }
}

file_put_contents($chartFile, $chartContent);
echo "price_chart.php strings updated.";
