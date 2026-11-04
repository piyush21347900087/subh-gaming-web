<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

if (strpos($content, 'Loading messages…') === false) {
    $newTranslations = "    'Loading messages…' => [
        'en' => 'Loading messages…', 'hi' => 'मैसेज लोड हो रहे हैं…', 'kn' => 'ಸಂದೇಶಗಳನ್ನು ಲೋಡ್ ಮಾಡಲಾಗುತ್ತಿದೆ…', 'ml' => 'സന്ദേശങ്ങൾ ലോഡുചെയ്യുന്നു…', 
        'gu' => 'મેસેજ લોડ થઈ રહ્યા છે…', 'mr' => 'संदेश लोड होत आहेत…', 'ta' => 'செய்திகளை ஏற்றுகிறது…', 'te' => 'సందేశాలను లోడ్ చేస్తోంది…'
    ],
    'Type your message…' => [
        'en' => 'Type your message…', 'hi' => 'अपना मैसेज टाइप करें…', 'kn' => 'ನಿಮ್ಮ ಸಂದೇಶವನ್ನು ಟೈಪ್ ಮಾಡಿ…', 'ml' => 'നിങ്ങളുടെ സന്ദേശം ടൈപ്പ് ചെയ്യുക…', 
        'gu' => 'તમારો મેસેજ ટાઈપ કરો…', 'mr' => 'तुमचा संदेश टाईप करा…', 'ta' => 'உங்கள் செய்தியை தட்டச்சு செய்யவும்…', 'te' => 'మీ సందేశాన్ని టైప్ చేయండి…'
    ],
    'SUN' => [
";

    $content = str_replace("    'SUN' => [
", $newTranslations, $content);
    file_put_contents($file, $content);
    echo "Added chat translations to lang.php.\n";
}

// Update live_chat.php
$chatFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/live_chat.php';
$chatContent = file_get_contents($chatFile);

$chatContent = str_replace('Loading messages…', '<?= __("Loading messages…") ?>', $chatContent);

// Use single quotes inside the PHP tag to avoid quote escaping issues
$chatContent = str_replace('placeholder="Type your message…"', 'placeholder="<?= __(\'Type your message…\') ?>"', $chatContent);
$chatContent = str_replace('input.placeholder = "Type your message…";', 'input.placeholder = "<?= __(\'Type your message…\') ?>";', $chatContent);

file_put_contents($chatFile, $chatContent);
echo "live_chat.php updated.";
