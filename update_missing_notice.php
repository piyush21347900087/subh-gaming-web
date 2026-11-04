<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'No Notice Available' => [
        'en' => 'No Notice Available', 'hi' => 'कोई नोटिस उपलब्ध नहीं है', 'kn' => 'ಯಾವುದೇ ಸೂಚನೆ ಲಭ್ಯವಿಲ್ಲ', 'ml' => 'നോട്ടീസ് ലഭ്യമല്ല', 
        'gu' => 'કોઈ નોટિસ ઉપલબ્ધ નથી', 'mr' => 'कोणतीही सूचना उपलब्ध नाही', 'ta' => 'எந்த அறிவிப்பும் கிடைக்கவில்லை', 'te' => 'ఎలాంటి నోటీసు అందుబాటులో లేదు'
    ],
    'Is waqt koi active notice nahi hai.' => [
        'en' => 'There are no active notices at this time.', 'hi' => 'इस समय कोई सक्रिय नोटिस नहीं है।', 'kn' => 'ಈ ಸಮಯದಲ್ಲಿ ಯಾವುದೇ ಸಕ್ರಿಯ ಸೂಚನೆ ಇಲ್ಲ.', 'ml' => 'ഇപ്പോൾ സജീവമായ നോട്ടീസുകളൊന്നുമില്ല.', 
        'gu' => 'આ સમયે કોઈ સક્રિય નોટિસ નથી.', 'mr' => 'सध्या कोणतीही सक्रिय सूचना नाही.', 'ta' => 'தற்போது செயலில் உள்ள அறிவிப்புகள் எதுவும் இல்லை.', 'te' => 'ప్రస్తుతం యాక్టివ్ నోటీసులు లేవు.'
    ],
    'SUN' => [
";

if (strpos($content, "'No Notice Available' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added missing notice translations.\n";
}
