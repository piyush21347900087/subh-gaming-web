<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/includes/lang.php';
$content = file_get_contents($file);

$additions = "    'Join SUBH GAMING' => [
        'en' => 'Join SUBH GAMING', 'hi' => 'SUBH GAMING से जुड़ें', 'kn' => 'SUBH GAMING ಸೇರಿ', 'ml' => 'SUBH GAMING-ൽ ചേരുക', 
        'gu' => 'SUBH GAMING સાથે જોડાઓ', 'mr' => 'SUBH GAMING मध्ये सामील व्हा', 'ta' => 'SUBH GAMING இல் சேரவும்', 'te' => 'SUBH GAMING లో చేరండి'
    ],
    'SUN' => [
";

if (strpos($content, "'Join SUBH GAMING' => [") === false) {
    $content = str_replace("    'SUN' => [
", $additions, $content);
    file_put_contents($file, $content);
    echo "Added 'Join SUBH GAMING' translation.\n";
}

// Update user_register.php
$regFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_register.php';
$regContent = file_get_contents($regFile);

$regContent = str_replace('<h1>REGISTER</h1>', '<h1><?= strtoupper(__("Register")) ?></h1>', $regContent);
$regContent = str_replace('<p>Join SUBH GAMING</p>', '<p><?= __("Join SUBH GAMING") ?></p>', $regContent);
$regContent = preg_replace('/>Sign\s*In<\/a>/', '><?= __("Sign In") ?></a>', $regContent);

file_put_contents($regFile, $regContent);
echo "user_register.php updated.";
