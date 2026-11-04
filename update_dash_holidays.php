<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'SATUREDAY AND SUNDAY HOLIDAY' => [
        'en' => 'SATURDAY AND SUNDAY HOLIDAY', 'hi' => 'शनिवार और रविवार छुट्टी', 'kn' => 'ಶನಿವಾರ ಮತ್ತು ಭಾನುವಾರ ರಜೆ', 'ml' => 'ശനിയാഴ്ചയും ഞായറാഴ്ചയും അവധി', 
        'gu' => 'શનિવાર અને રવિવાર રજા', 'mr' => 'शनिवार आणि रविवार सुट्टी', 'ta' => 'சனி மற்றும் ஞாயிறு விடுமுறை', 'te' => 'శనివారం మరియు ఆదివారం సెలవు'
    ],
    'SATUREDAY HOLIDAY' => [
        'en' => 'SATURDAY HOLIDAY', 'hi' => 'शनिवार की छुट्टी', 'kn' => 'ಶನಿವಾರ ರಜೆ', 'ml' => 'ശനിയാഴ്ച അവധി', 
        'gu' => 'શનિવાર રજા', 'mr' => 'शनिवार सुट्टी', 'ta' => 'சனிக்கிழமை விடுமுறை', 'te' => 'శనివారం సెలవు'
    ],
    'SUNDAY HOLIDAY' => [
        'en' => 'SUNDAY HOLIDAY', 'hi' => 'रविवार की छुट्टी', 'kn' => 'ಭಾನುವಾರ ರಜೆ', 'ml' => 'ഞായറാഴ്ച അവധി', 
        'gu' => 'રવિવાર રજા', 'mr' => 'रविवार सुट्टी', 'ta' => 'ஞாயிறு விடுமுறை', 'te' => 'ఆదివారం సెలవు'
    ],
    'Market Holiday' => [
        'en' => 'Market Holiday', 'hi' => 'मार्केट की छुट्टी', 'kn' => 'ಮಾರುಕಟ್ಟೆ ರಜೆ', 'ml' => 'മാർക്കറ്റ് അവധി', 
        'gu' => 'માર્કેટ રજા', 'mr' => 'मार्केट सुट्टी', 'ta' => 'சந்தை விடுமுறை', 'te' => 'మార్కెట్ సెలవు'
    ],
    'This market is on holiday today.' => [
        'en' => 'This market is on holiday today.', 'hi' => 'आज यह मार्केट बंद (छुट्टी) है।', 'kn' => 'ಈ ಮಾರುಕಟ್ಟೆಗೆ ಇಂದು ರಜೆ.', 'ml' => 'ഈ മാർക്കറ്റിന് ഇന്ന് അവധിയാണ്.', 
        'gu' => 'આ માર્કેટ આજે રજા પર છે.', 'mr' => 'हे मार्केट आज सुट्टीवर आहे.', 'ta' => 'இந்த சந்தை இன்று விடுமுறையில் உள்ளது.', 'te' => 'ఈ మార్కెట్ ఈరోజు సెలవులో ఉంది.'
    ],
    'Market Timings:' => [
        'en' => 'Market Timings:', 'hi' => 'मार्केट का समय:', 'kn' => 'ಮಾರುಕಟ್ಟೆ ಸಮಯ:', 'ml' => 'മാർക്കറ്റ് സമയങ്ങൾ:', 
        'gu' => 'માર્કેટનો સમય:', 'mr' => 'मार्केटची वेळ:', 'ta' => 'சந்தை நேரங்கள்:', 'te' => 'మార్కెట్ సమయాలు:'
    ],
    'SUN' => [
";

if (strpos($content, "'SATUREDAY AND SUNDAY HOLIDAY' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added holiday translations.\n";
}

// Update user_dashboard.php
$dashFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_dashboard.php';
$dashContent = file_get_contents($dashFile);

$replacements = [
    '<div class="holiday-note"><?= htmlspecialchars($holidayText) ?></div>' => '<div class="holiday-note"><?= htmlspecialchars(__($holidayText)) ?></div>',
    '<h2 id="closedPopupTitle">Market Closed</h2>' => '<h2 id="closedPopupTitle"><?= __("Market Closed") ?></h2>',
    '<p id="closedPopupMessage">Sorry, bidding for this market is currently closed. Please check the timings.</p>' => '<p id="closedPopupMessage"><?= __("Sorry, bidding for this market is currently closed. Please check the timings.") ?></p>',
    "popupTitle.innerText = 'Market Holiday';" => "popupTitle.innerText = '<?= __(\"Market Holiday\") ?>';",
    "popupMessage.innerText = 'This market is on holiday today.';" => "popupMessage.innerText = '<?= __(\"This market is on holiday today.\") ?>';",
    "'\\nMarket Timings:\\n'" => "'\\n<?= __(\"Market Timings:\") ?>\\n'",
    "popupTitle.innerText = 'Market Closed';" => "popupTitle.innerText = '<?= __(\"Market Closed\") ?>';",
    "popupMessage.innerText = 'Sorry, bidding for this market is currently closed. Please check the timings.';" => "popupMessage.innerText = '<?= __(\"Sorry, bidding for this market is currently closed. Please check the timings.\") ?>';"
];

foreach ($replacements as $search => $replace) {
    $dashContent = str_replace($search, $replace, $dashContent);
}

file_put_contents($dashFile, $dashContent);
echo "user_dashboard.php updated.";
