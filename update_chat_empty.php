<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

if (strpos($content, 'No messages yet') === false) {
    $newTranslations = "    'No messages yet. Start the conversation!' => [
        'en' => 'No messages yet. Start the conversation!', 
        'hi' => 'अभी तक कोई मैसेज नहीं है। बातचीत शुरू करें!', 
        'kn' => 'ಇನ್ನೂ ಯಾವುದೇ ಸಂದೇಶಗಳಿಲ್ಲ. ಸಂಭಾಷಣೆ ಪ್ರಾರಂಭಿಸಿ!', 
        'ml' => 'സന്ദേശങ്ങളൊന്നുമില്ല. സംഭാഷണം ആരംഭിക്കുക!', 
        'gu' => 'હજુ સુધી કોઈ મેસેજ નથી. વાતચીત શરૂ કરો!', 
        'mr' => 'अद्याप कोणतेही संदेश नाहीत. संवाद सुरू करा!', 
        'ta' => 'இதுவரை செய்திகள் இல்லை. உரையாடலைத் தொடங்கவும்!', 
        'te' => 'ఇంకా సందేశాలు లేవు. సంభాషణను ప్రారంభించండి!'
    ],
    'SUN' => [
";

    $content = str_replace("    'SUN' => [
", $newTranslations, $content);
    file_put_contents($file, $content);
    echo "Added 'No messages yet' to lang.php.\n";
}

// Update live_chat.php
$chatFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/live_chat.php';
$chatContent = file_get_contents($chatFile);

// Replace it in the JS string
$chatContent = str_replace(
    "'<div class=\"hint\">No messages yet. Start the conversation!</div>'", 
    "'<div class=\"hint\">' + '<?= __(\'No messages yet. Start the conversation!\') ?>' + '</div>'", 
    $chatContent
);

file_put_contents($chatFile, $chatContent);
echo "live_chat.php updated.";
