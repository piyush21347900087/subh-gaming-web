<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'MY PROFILE' => [
        'en' => 'MY PROFILE', 'hi' => 'मेरी प्रोफाइल', 'kn' => 'ನನ್ನ ಪ್ರೊಫೈಲ್', 'ml' => 'എന്റെ പ്രൊഫൈൽ', 
        'gu' => 'મારી પ્રોફાઇલ', 'mr' => 'माझे प्रोफाइल', 'ta' => 'எனது சுயவிவரம்', 'te' => 'నా ప్రొఫైల్'
    ],
    'My Profile' => [
        'en' => 'My Profile', 'hi' => 'मेरी प्रोफाइल', 'kn' => 'ನನ್ನ ಪ್ರೊಫೈಲ್', 'ml' => 'എന്റെ പ്രൊഫൈൽ', 
        'gu' => 'મારી પ્રોફાઇલ', 'mr' => 'माझे प्रोफाइल', 'ta' => 'எனது சுயவிவரம்', 'te' => 'నా ప్రొఫైల్'
    ],
    'User ID' => [
        'en' => 'User ID', 'hi' => 'यूजर आईडी', 'kn' => 'ಬಳಕೆದಾರ ID', 'ml' => 'ഉപയോക്തൃ ഐഡി', 
        'gu' => 'વપરાશકર્તા ID', 'mr' => 'युजर आयडी', 'ta' => 'பயனர் ஐடி', 'te' => 'వినియోగదారు ID'
    ],
    'Full Name' => [
        'en' => 'Full Name', 'hi' => 'पूरा नाम', 'kn' => 'ಪೂರ್ಣ ಹೆಸರು', 'ml' => 'പൂർണ്ണ നാമം', 
        'gu' => 'પૂરું નામ', 'mr' => 'पूर्ण नाव', 'ta' => 'முழு பெயர்', 'te' => 'పూర్తి పేరు'
    ],
    'Registered On' => [
        'en' => 'Registered On', 'hi' => 'पंजीकरण की तिथि', 'kn' => 'ನೋಂದಾಯಿಸಲಾಗಿದೆ', 'ml' => 'രജിസ്റ്റർ ചെയ്തത്', 
        'gu' => 'નોંધણી તારીખ', 'mr' => 'नोंदणीची तारीख', 'ta' => 'பதிவு செய்யப்பட்டது', 'te' => 'నమోదు చేయబడింది'
    ],
    'UPDATE PROFILE' => [
        'en' => 'UPDATE PROFILE', 'hi' => 'प्रोफाइल अपडेट करें', 'kn' => 'ಪ್ರೊಫೈಲ್ ನವೀಕರಿಸಿ', 'ml' => 'പ്രൊഫൈൽ അപ്ഡേറ്റ് ചെയ്യുക', 
        'gu' => 'પ્રોફાઇલ અપડેટ કરો', 'mr' => 'प्रोफाइल अपडेट करा', 'ta' => 'சுயவிவரத்தைப் புதுப்பிக்கவும்', 'te' => 'ప్రొఫైల్‌ను నవీకరించండి'
    ],
    'Password (4 Digit)' => [
        'en' => 'Password (4 Digit)', 'hi' => 'पासवर्ड (4 अंक)', 'kn' => 'ಪಾಸ್ವರ್ಡ್ (4 ಅಂಕಿಯ)', 'ml' => 'പാസ്‌വേഡ് (4 അക്കം)', 
        'gu' => 'પાસવર્ડ (4 અંક)', 'mr' => 'पासवर्ड (4 अंकी)', 'ta' => 'கடவுச்சொல் (4 இலக்கம்)', 'te' => 'పాస్‌వర్డ్ (4 అంకెల)'
    ],
    'SAVE PROFILE' => [
        'en' => 'SAVE PROFILE', 'hi' => 'प्रोफाइल सहेजें', 'kn' => 'ಪ್ರೊಫೈಲ್ ಉಳಿಸಿ', 'ml' => 'പ്രൊഫൈൽ സംരക്ഷിക്കുക', 
        'gu' => 'પ્રોફાઇલ સાચવો', 'mr' => 'प्रोफाइल जतन करा', 'ta' => 'சுயவிவரத்தைச் சேமி', 'te' => 'ప్రొఫైల్‌ను సేవ్ చేయండి'
    ],
    'LOGOUT' => [
        'en' => 'LOGOUT', 'hi' => 'लॉग आउट', 'kn' => 'ಲಾಗ್ ಔಟ್', 'ml' => 'ലോഗ് ഔട്ട്', 
        'gu' => 'લૉગઆઉટ', 'mr' => 'लॉगआउट', 'ta' => 'வெளியேறு', 'te' => 'లాగ్అవుట్ చేయండి'
    ],
    'SUN' => [
";

if (strpos($content, "'MY PROFILE' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added user_profile texts to lang.php.\n";
}

$profileFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_profile.php';
$profileContent = file_get_contents($profileFile);

$replacements = [
    '<title>My Profile | SUBH GAMING</title>' => '<title><?= __("My Profile") ?> | SUBH GAMING</title>',
    '<div class="section-title">MY PROFILE</div>' => '<div class="section-title"><?= __("MY PROFILE") ?></div>',
    '<span class="profile-info-label">User ID</span>' => '<span class="profile-info-label"><?= __("User ID") ?></span>',
    '<span class="profile-info-label">Full Name</span>' => '<span class="profile-info-label"><?= __("Full Name") ?></span>',
    '<span class="profile-info-label">Registered On</span>' => '<span class="profile-info-label"><?= __("Registered On") ?></span>',
    '<div class="section-title">UPDATE PROFILE</div>' => '<div class="section-title"><?= __("UPDATE PROFILE") ?></div>',
    '<label>Password (4 Digit)</label>' => '<label><?= __("Password (4 Digit)") ?></label>',
    '>SAVE PROFILE<' => '><?= __("SAVE PROFILE") ?><',
    '>LOGOUT<' => '><?= __("LOGOUT") ?><'
];

foreach ($replacements as $search => $replace) {
    $profileContent = str_replace($search, $replace, $profileContent);
}

file_put_contents($profileFile, $profileContent);
echo "user_profile.php texts updated.";
