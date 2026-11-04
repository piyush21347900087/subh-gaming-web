<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/languages.php';
$content = file_get_contents($file);

if (strpos($content, 'lang.php') === false) {
    $content = str_replace("<?php\n\$currentLang", "<?php\nrequire_once 'includes/lang.php';\n\$currentLang", $content);
}

$replacements = [
    '<h1>Choose Language</h1>' => '<h1><?= __("Choose Language") ?></h1>',
    '>CONTINUE<' => '><?= __("CONTINUE") ?><'
];

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

file_put_contents($file, $content);
echo "languages.php translated.";
