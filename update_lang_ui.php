<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/languages.php';
$content = file_get_contents($file);

// Add PHP to get current lang
$searchTop = '?>
<!DOCTYPE html>';
$replaceTop = '?>
<?php
$currentLang = $_COOKIE[\'app_lang\'] ?? \'en\';
?>
<!DOCTYPE html>';
$content = str_replace($searchTop, $replaceTop, $content);

// Replace hardcoded "selected" class for each card with dynamic check
$languages = ['en', 'hi', 'kn', 'ml', 'gu', 'mr', 'ta', 'te'];
foreach ($languages as $code) {
    if ($code === 'en') {
        $content = str_replace('<div class="lang-card selected" onclick="selectLang(this, \'en\')">', '<div class="lang-card <?= $currentLang === \'en\' ? \'selected\' : \'\' ?>" onclick="selectLang(this, \'en\')">', $content);
    } else {
        $content = str_replace('<div class="lang-card" onclick="selectLang(this, \''.$code.'\')">', '<div class="lang-card <?= $currentLang === \''.$code.'\' ? \'selected\' : \'\' ?>" onclick="selectLang(this, \''.$code.'\')">', $content);
    }
}

// Update hidden input value
$content = str_replace('<input type="hidden" name="lang" id="selectedLangInput" value="en">', '<input type="hidden" name="lang" id="selectedLangInput" value="<?= htmlspecialchars($currentLang) ?>">', $content);

file_put_contents($file, $content);
echo "languages.php updated to show current selection.";
