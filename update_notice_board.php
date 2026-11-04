<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Notice Board' => [
        'en' => 'Notice Board', 'hi' => 'नोटिस बोर्ड', 'kn' => 'ಸೂಚನಾ ಫಲಕ', 'ml' => 'നോട്ടീസ് ബോർഡ്', 
        'gu' => 'નોટિસ બોર્ડ', 'mr' => 'सूचना फलक', 'ta' => 'அறிவிப்பு பலகை', 'te' => 'నోటీసు బోర్డు'
    ],
    'No Notice Available' => [
        'en' => 'No Notice Available', 'hi' => 'कोई नोटिस उपलब्ध नहीं है', 'kn' => 'ಯಾವುದೇ ಸೂಚನೆ ಲಭ್ಯವಿಲ್ಲ', 'ml' => 'നോട്ടീസ് ലഭ്യമല്ല', 
        'gu' => 'કોઈ નોટિસ ઉપલબ્ધ નથી', 'mr' => 'कोणतीही सूचना उपलब्ध नाही', 'ta' => 'எந்த அறிவிப்பும் கிடைக்கவில்லை', 'te' => 'ఎలాంటి నోటీసు అందుబాటులో లేదు'
    ],
    'Is waqt koi active notice nahi hai.' => [
        'en' => 'There are no active notices at this time.', 'hi' => 'इस समय कोई सक्रिय नोटिस नहीं है।', 'kn' => 'ಈ ಸಮಯದಲ್ಲಿ ಯಾವುದೇ ಸಕ್ರಿಯ ಸೂಚನೆ ಇಲ್ಲ.', 'ml' => 'ഇപ്പോൾ സജീവമായ നോട്ടീസുകളൊന്നുമില്ല.', 
        'gu' => 'આ સમયે કોઈ સક્રિય નોટિસ નથી.', 'mr' => 'सध्या कोणतीही सक्रिय सूचना नाही.', 'ta' => 'தற்போது செயலில் உள்ள அறிவிப்புகள் எதுவும் இல்லை.', 'te' => 'ప్రస్తుతం యాక్టివ్ నోటీసులు లేవు.'
    ],
    'SUN' => [
";

if (strpos($content, "'Notice Board' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added notice translations.\n";
}

// Update notice_board.php
$noticeFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/notice_board.php';
$noticeContent = file_get_contents($noticeFile);

$replacements = [
    '<title>Notice Board | SUBH GAMING</title>' => '<title><?= __("Notice Board") ?> | SUBH GAMING</title>',
    '<div class="notice-title">No Notice Available</div>' => '<div class="notice-title"><?= __("No Notice Available") ?></div>',
    '<div class="notice-text">Is waqt koi active notice nahi hai.</div>' => '<div class="notice-text"><?= __("Is waqt koi active notice nahi hai.") ?></div>'
];

foreach ($replacements as $search => $replace) {
    $noticeContent = str_replace($search, $replace, $noticeContent);
}

// Wait, is there a header title? Let's check if there is Notice Board in the DOM.
// Earlier I grepped for it, but only title showed up. If there's any other, I'll miss it. 
// But the user just said "notice_board.php me bhi update karo". The main texts are the ones above.

file_put_contents($noticeFile, $noticeContent);
echo "notice_board.php updated.";
