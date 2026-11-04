<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'SUBH GAMING Tutorials' => [
        'en' => 'SUBH GAMING Tutorials', 'hi' => 'मटका किंग ट्यूटोरियल्स', 'kn' => 'ಮಟ್ಕಾ ಕಿಂಗ್ ಟ್ಯುಟೋರಿಯಲ್‌ಗಳು', 'ml' => 'മറ്റ്ക കിംഗ് ട്യൂട്ടോറിയലുകൾ', 
        'gu' => 'મટકા કિંગ ટ્યુટોરિયલ્સ', 'mr' => 'मटका किंग ट्यूटोरियल्स', 'ta' => 'மட்கா கிங் டுடோரியல்கள்', 'te' => 'మట్కా కింగ్ ట్యుటోరియల్స్'
    ],
    'Watch video tutorial' => [
        'en' => 'Watch video tutorial', 'hi' => 'वीडियो ट्यूटोरियल देखें', 'kn' => 'ವೀಡಿಯೊ ಟ್ಯುಟೋರಿಯಲ್ ವೀಕ್ಷಿಸಿ', 'ml' => 'വീഡിയോ ട്യൂട്ടോറിയൽ കാണുക', 
        'gu' => 'વિડિઓ ટ્યુટોરીયલ જુઓ', 'mr' => 'व्हिडिओ ट्यूटोरियल पहा', 'ta' => 'வீடியோ டுடோரியலைப் பார்க்கவும்', 'te' => 'వీడియో ట్యుటోరియల్ చూడండి'
    ],
    'SUN' => [
";

if (strpos($content, "'SUBH GAMING Tutorials' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added Tutorials translations to lang.php.\n";
}

$chartFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/how_to_play.php';
$chartContent = file_get_contents($chartFile);

$replacements = [
    '>SUBH GAMING Tutorials<' => '><?= __("SUBH GAMING Tutorials") ?><',
    '<p>Watch video tutorial</p>' => '<p><?= __("Watch video tutorial") ?></p>'
];

foreach ($replacements as $search => $replace) {
    if (strpos($chartContent, $replace) === false) {
        $chartContent = str_replace($search, $replace, $chartContent);
    }
}

file_put_contents($chartFile, $chartContent);
echo "how_to_play.php strings updated.";
