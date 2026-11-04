<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Mobile number already registered.' => [
        'en' => 'Mobile number already registered.', 'hi' => 'मोबाइल नंबर पहले से पंजीकृत है।', 'kn' => 'ಮೊಬೈಲ್ ಸಂಖ್ಯೆ ಈಗಾಗಲೇ ನೋಂದಾಯಿಸಲಾಗಿದೆ.', 'ml' => 'മൊബൈൽ നമ്പർ ഇതിനകം രജിസ്റ്റർ ചെയ്തു.', 
        'gu' => 'મોબાઈલ નંબર પહેલેથી જ નોંધાયેલ છે.', 'mr' => 'मोबाईल नंबर आधीच नोंदणीकृत आहे.', 'ta' => 'மொபைல் எண் ஏற்கனவே பதிவு செய்யப்பட்டுள்ளது.', 'te' => 'మొబైల్ సంఖ్య ఇప్పటికే నమోదు చేయబడింది.'
    ],
    'OTP Not Sent' => [
        'en' => 'OTP Not Sent', 'hi' => 'ओटीपी नहीं भेजा गया', 'kn' => 'OTP ಕಳುಹಿಸಲಾಗಿಲ್ಲ', 'ml' => 'OTP അയച്ചില്ല', 
        'gu' => 'OTP મોકલવામાં આવ્યો નથી', 'mr' => 'OTP पाठवला नाही', 'ta' => 'OTP அனுப்பப்படவில்லை', 'te' => 'OTP పంపబడలేదు'
    ],
    'All fields are required.' => [
        'en' => 'All fields are required.', 'hi' => 'सभी फ़ील्ड आवश्यक हैं।', 'kn' => 'ಎಲ್ಲಾ ಕ್ಷೇತ್ರಗಳು ಅಗತ್ಯವಿದೆ.', 'ml' => 'എല്ലാ ഫീൽഡുകളും ആവശ്യമാണ്.', 
        'gu' => 'બધા ક્ષેત્રો જરૂરી છે.', 'mr' => 'सर्व फील्ड आवश्यक आहेत.', 'ta' => 'அனைத்து புலங்களும் தேவை.', 'te' => 'అన్ని ఫీల్డ్‌లు అవసరం.'
    ],
    'Enter a valid 10-digit mobile number.' => [
        'en' => 'Enter a valid 10-digit mobile number.', 'hi' => 'मान्य 10-अंकीय मोबाइल नंबर दर्ज करें।', 'kn' => 'ಮಾನ್ಯವಾದ 10-ಅಂಕಿಯ ಮೊಬೈಲ್ ಸಂಖ್ಯೆಯನ್ನು ನಮೂದಿಸಿ.', 'ml' => 'സാധുവായ 10-അക്ക മൊബൈൽ നമ്പർ നൽകുക.', 
        'gu' => 'માન્ય 10-અંકનો મોબાઈલ નંબર દાખલ કરો.', 'mr' => 'वैध 10-अंकी मोबाईल नंबर प्रविष्ट करा.', 'ta' => 'சரியான 10 இலக்க மொபைல் எண்ணை உள்ளிடவும்.', 'te' => 'చెల్లుబాటు అయ్యే 10-అంకెల మొబైల్ నంబర్‌ను నమోదు చేయండి.'
    ],
    'Password must be 4 digit numbers only.' => [
        'en' => 'Password must be 4 digit numbers only.', 'hi' => 'पासवर्ड केवल 4 अंकों का होना चाहिए।', 'kn' => 'ಪಾಸ್ವರ್ಡ್ ಕೇವಲ 4 ಅಂಕಿಯ ಸಂಖ್ಯೆಯಾಗಿರಬೇಕು.', 'ml' => 'പാസ്‌വേഡ് 4 അക്ക നമ്പറുകൾ മാത്രമായിരിക്കണം.', 
        'gu' => 'પાસવર્ડ માત્ર 4 અંકનો હોવો જોઈએ.', 'mr' => 'पासवर्ड फक्त 4 अंकी असावा.', 'ta' => 'கடவுச்சொல் 4 இலக்க எண்களாக மட்டுமே இருக்க வேண்டும்.', 'te' => 'పాస్వర్డ్ కేవలం 4 అంకెల సంఖ్య మాత్రమే ఉండాలి.'
    ],
    'Invalid or expired OTP.' => [
        'en' => 'Invalid or expired OTP.', 'hi' => 'अमान्य या समाप्त ओटीपी।', 'kn' => 'ಅಮಾನ್ಯ ಅಥವಾ ಅವಧಿ ಮುಗಿದ OTP.', 'ml' => 'അസാധുവായ അല്ലെങ്കിൽ കാലഹരണപ്പെട്ട OTP.', 
        'gu' => 'અમાન્ય અથવા સમયસીમા સમાપ્ત થયેલ OTP.', 'mr' => 'अवैध किंवा कालबाह्य OTP.', 'ta' => 'தவறான அல்லது காலாவதியான OTP.', 'te' => 'చెల్లని లేదా గడువు ముగిసిన OTP.'
    ],
    'Invalid Number' => [
        'en' => 'Invalid Number', 'hi' => 'अमान्य नंबर', 'kn' => 'ಅಮಾನ್ಯ ಸಂಖ್ಯೆ', 'ml' => 'അസാധുവായ നമ്പർ', 
        'gu' => 'અમાન્ય નંબર', 'mr' => 'अवैध नंबर', 'ta' => 'தவறான எண்', 'te' => 'చెల్లని సంఖ్య'
    ],
    'Please try again later.' => [
        'en' => 'Please try again later.', 'hi' => 'कृपया बाद में पुनः प्रयास करें।', 'kn' => 'ದಯವಿಟ್ಟು ನಂತರ ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ.', 'ml' => 'ദയവായി പിന്നീട് വീണ്ടും ശ്രമിക്കുക.', 
        'gu' => 'કૃપા કરીને પછીથી ફરી પ્રયાસ કરો.', 'mr' => 'कृपया नंतर पुन्हा प्रयत्न करा.', 'ta' => 'சிறிது நேரம் கழித்து மீண்டும் முயற்சிக்கவும்.', 'te' => 'దయచేసి తర్వాత మళ్లీ ప్రయత్నించండి.'
    ],
    'Connection Error' => [
        'en' => 'Connection Error', 'hi' => 'कनेक्शन त्रुटि', 'kn' => 'ಸಂಪರ್ಕ ದೋಷ', 'ml' => 'കണക്ഷൻ പിശക്', 
        'gu' => 'કનેક્શન ભૂલ', 'mr' => 'कनेक्शन त्रुटी', 'ta' => 'இணைப்பு பிழை', 'te' => 'కనెక్షన్ లోపం'
    ],
    'Unable to connect right now. Please try again.' => [
        'en' => 'Unable to connect right now. Please try again.', 'hi' => 'अभी कनेक्ट करने में असमर्थ। कृपया पुनः प्रयास करें।', 'kn' => 'ಈಗ ಸಂಪರ್ಕಿಸಲು ಸಾಧ್ಯವಿಲ್ಲ. ದಯವಿಟ್ಟು ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ.', 'ml' => 'ഇപ്പോൾ കണക്റ്റുചെയ്യാനായില്ല. ദയവായി വീണ്ടും ശ്രമിക്കുക.', 
        'gu' => 'અત્યારે કનેક્ટ કરવામાં અસમર્થ. કૃપા કરીને ફરી પ્રયાસ કરો.', 'mr' => 'सध्या कनेक्ट करण्यात अक्षम. कृपया पुन्हा प्रयत्न करा.', 'ta' => 'தற்போது இணைக்க முடியவில்லை. மீண்டும் முயற்சிக்கவும்.', 'te' => 'ప్రస్తుతం కనెక్ట్ చేయడం సాధ్యపడలేదు. దయచేసి మళ్లీ ప్రయత్నించండి.'
    ],
    'Notice' => [
        'en' => 'Notice', 'hi' => 'सूचना', 'kn' => 'ಸೂಚನೆ', 'ml' => 'അറിയിപ്പ്', 
        'gu' => 'સૂચના', 'mr' => 'सूचना', 'ta' => 'அறிவிப்பு', 'te' => 'గమనిక'
    ],
    'Please try again.' => [
        'en' => 'Please try again.', 'hi' => 'कृपया पुनः प्रयास करें।', 'kn' => 'ದಯವಿಟ್ಟು ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ.', 'ml' => 'ദയവായി വീണ്ടും ശ്രമിക്കുക.', 
        'gu' => 'કૃપા કરીને ફરી પ્રયાસ કરો.', 'mr' => 'कृपया पुन्हा प्रयत्न करा.', 'ta' => 'மீண்டும் முயற்சிக்கவும்.', 'te' => 'దయచేసి మళ్లీ ప్రయత్నించండి.'
    ],
    'SUN' => [
";

if (strpos($content, "'OTP Not Sent' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added error translations.\n";
}

// Update user_register.php
$regFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_register.php';
$regContent = file_get_contents($regFile);

// PHP variables
$regContent = str_replace('$message = "All fields are required.";', '$message = __("All fields are required.");', $regContent);
$regContent = str_replace('$message = "Enter a valid 10-digit mobile number.";', '$message = __("Enter a valid 10-digit mobile number.");', $regContent);
$regContent = str_replace('$message = "Password must be 4 digit numbers only.";', '$message = __("Password must be 4 digit numbers only.");', $regContent);
$regContent = str_replace('$message = "Invalid or expired OTP.";', '$message = __("Invalid or expired OTP.");', $regContent);
$regContent = str_replace('$message = "Mobile number already registered.";', '$message = __("Mobile number already registered.");', $regContent);

// JS strings
$regContent = str_replace("showNotice('Invalid Number', 'Enter a valid 10-digit mobile number.');", "showNotice('<?= __(\"Invalid Number\") ?>', '<?= __(\"Enter a valid 10-digit mobile number.\") ?>');", $regContent);
$regContent = str_replace("showNotice('OTP Not Sent', res.message || 'Please try again later.');", "showNotice('<?= __(\"OTP Not Sent\") ?>', res.message || '<?= __(\"Please try again later.\") ?>');", $regContent);
$regContent = str_replace("showNotice('Connection Error', 'Unable to connect right now. Please try again.');", "showNotice('<?= __(\"Connection Error\") ?>', '<?= __(\"Unable to connect right now. Please try again.\") ?>');", $regContent);

// HTML modal
$regContent = str_replace('<h2 id="noticeTitle">Notice</h2>', '<h2 id="noticeTitle"><?= __("Notice") ?></h2>', $regContent);
$regContent = str_replace('<p id="noticeText">Please try again.</p>', '<p id="noticeText"><?= __("Please try again.") ?></p>', $regContent);

file_put_contents($regFile, $regContent);
echo "user_register.php updated.";
