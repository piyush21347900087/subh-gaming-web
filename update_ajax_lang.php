<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Invalid mobile number.' => [
        'en' => 'Invalid mobile number.', 'hi' => 'अमान्य मोबाइल नंबर।', 'kn' => 'ಅಮಾನ್ಯ ಮೊಬೈಲ್ ಸಂಖ್ಯೆ.', 'ml' => 'അസാധുവായ മൊബൈൽ നമ്പർ.', 
        'gu' => 'અમાન્ય મોબાઈલ નંબર.', 'mr' => 'अवैध मोबाईल नंबर.', 'ta' => 'தவறான மொபைல் எண்.', 'te' => 'చెల్లని మొబైల్ సంఖ్య.'
    ],
    'DB Connection Error' => [
        'en' => 'DB Connection Error', 'hi' => 'डेटाबेस कनेक्शन त्रुटि', 'kn' => 'ಡೇಟಾಬೇಸ್ ಸಂಪರ್ಕ ದೋಷ', 'ml' => 'ഡാറ്റാബേസ് കണക്ഷൻ പിശക്', 
        'gu' => 'ડેટાબેઝ કનેક્શન ભૂલ', 'mr' => 'डेटाबेस कनेक्शन त्रुटी', 'ta' => 'தரவுத்தள இணைப்பு பிழை', 'te' => 'డేటాబేస్ కనెక్షన్ లోపం'
    ],
    'Failed to send OTP. Please try again later.' => [
        'en' => 'Failed to send OTP. Please try again later.', 'hi' => 'ओटीपी भेजने में विफल। कृपया बाद में पुनः प्रयास करें।', 'kn' => 'OTP ಕಳುಹಿಸಲು ವಿಫಲವಾಗಿದೆ. ದಯವಿಟ್ಟು ನಂತರ ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ.', 'ml' => 'OTP അയയ്‌ക്കുന്നതിൽ പരാജയപ്പെട്ടു. ദയവായി പിന്നീട് വീണ്ടും ശ്രമിക്കുക.', 
        'gu' => 'OTP મોકલવામાં નિષ્ફળ. કૃપા કરીને પછીથી ફરી પ્રયાસ કરો.', 'mr' => 'OTP पाठविण्यात अयशस्वी. कृपया नंतर पुन्हा प्रयत्न करा.', 'ta' => 'OTP அனுப்ப முடியவில்லை. சிறிது நேரம் கழித்து மீண்டும் முயற்சிக்கவும்.', 'te' => 'OTP పంపడం విఫలమైంది. దయచేసి తర్వాత మళ్లీ ప్రయత్నించండి.'
    ],
    'OTP sent successfully.' => [
        'en' => 'OTP sent successfully.', 'hi' => 'ओटीपी सफलतापूर्वक भेजा गया।', 'kn' => 'OTP ಯಶಸ್ವಿಯಾಗಿ ಕಳುಹಿಸಲಾಗಿದೆ.', 'ml' => 'OTP വിജയകരമായി അയച്ചു.', 
        'gu' => 'OTP સફળતાપૂર્વક મોકલવામાં આવ્યો.', 'mr' => 'OTP यशस्वीरित्या पाठवला.', 'ta' => 'OTP வெற்றிகரமாக அனுப்பப்பட்டது.', 'te' => 'OTP విజయవంతంగా పంపబడింది.'
    ],
    'Mobile number not registered.' => [
        'en' => 'Mobile number not registered.', 'hi' => 'मोबाइल नंबर पंजीकृत नहीं है।', 'kn' => 'ಮೊಬೈಲ್ ಸಂಖ್ಯೆ ನೋಂದಾಯಿಸಿಲ್ಲ.', 'ml' => 'മൊബൈൽ നമ്പർ രജിസ്റ്റർ ചെയ്തിട്ടില്ല.', 
        'gu' => 'મોબાઈલ નંબર નોંધાયેલ નથી.', 'mr' => 'मोबाईल नंबर नोंदणीकृत नाही.', 'ta' => 'மொபைல் எண் பதிவு செய்யப்படவில்லை.', 'te' => 'మొబైల్ సంఖ్య నమోదు కాలేదు.'
    ],
    'OTP verified.' => [
        'en' => 'OTP verified.', 'hi' => 'ओटीपी सत्यापित हो गया।', 'kn' => 'OTP ಪರಿಶೀಲಿಸಲಾಗಿದೆ.', 'ml' => 'OTP പരിശോധിച്ചുറപ്പിച്ചു.', 
        'gu' => 'OTP ચકાસાયેલ છે.', 'mr' => 'OTP सत्यापित झाला.', 'ta' => 'OTP சரிபார்க்கப்பட்டது.', 'te' => 'OTP ధృవీకరించబడింది.'
    ],
    'Invalid OTP.' => [
        'en' => 'Invalid OTP.', 'hi' => 'अमान्य ओटीपी।', 'kn' => 'ಅಮಾನ್ಯ OTP.', 'ml' => 'അസാധുവായ OTP.', 
        'gu' => 'અમાન્ય OTP.', 'mr' => 'अवैध OTP.', 'ta' => 'தவறான OTP.', 'te' => 'చెల్లని OTP.'
    ],
    'Invalid action.' => [
        'en' => 'Invalid action.', 'hi' => 'अमान्य कार्रवाई।', 'kn' => 'ಅಮಾನ್ಯ ಕ್ರಿಯೆ.', 'ml' => 'അസാധുവായ പ്രവർത്തനം.', 
        'gu' => 'અમાન્ય ક્રિયા.', 'mr' => 'अवैध कृती.', 'ta' => 'தவறான செயல்.', 'te' => 'చెల్లని చర్య.'
    ],
    'SUN' => [
";

if (strpos($content, "'Invalid mobile number.' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added ajax error translations.\n";
}

// Update ajax_otp_handler.php
$ajaxFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/ajax_otp_handler.php';
$ajaxContent = file_get_contents($ajaxFile);

if (strpos($ajaxContent, 'includes/lang.php') === false) {
    $ajaxContent = preg_replace('/<\?php\s+/', "<?php\nrequire_once 'includes/lang.php';\n", $ajaxContent, 1);
}

// Replace literal strings with __()
$ajaxContent = str_replace("'message' => 'Invalid mobile number.'", "'message' => __('Invalid mobile number.')", $ajaxContent);
$ajaxContent = str_replace("'message' => 'DB Connection Error'", "'message' => __('DB Connection Error')", $ajaxContent);
$ajaxContent = str_replace("'message' => 'Mobile number already registered.'", "'message' => __('Mobile number already registered.')", $ajaxContent);
$ajaxContent = preg_replace("/'message' => \\\$sms_res\['message'\] \?: 'Failed to send OTP\. Please try again later\.'/", "'message' => \$sms_res['message'] ?: __('Failed to send OTP. Please try again later.')", $ajaxContent);
$ajaxContent = str_replace("'message' => 'OTP sent successfully.'", "'message' => __('OTP sent successfully.')", $ajaxContent);
$ajaxContent = str_replace("'message' => 'Mobile number not registered.'", "'message' => __('Mobile number not registered.')", $ajaxContent);
$ajaxContent = str_replace("'message' => 'OTP verified.'", "'message' => __('OTP verified.')", $ajaxContent);
$ajaxContent = str_replace("'message' => 'Invalid OTP.'", "'message' => __('Invalid OTP.')", $ajaxContent);
$ajaxContent = str_replace("'message' => 'Invalid action.'", "'message' => __('Invalid action.')", $ajaxContent);
$ajaxContent = str_replace("'message' => 'Server error: ' . ", "'message' => 'Server error: ' . ", $ajaxContent); // Not translating server error with dynamic stacktrace for now

file_put_contents($ajaxFile, $ajaxContent);
echo "ajax_otp_handler.php updated.";
