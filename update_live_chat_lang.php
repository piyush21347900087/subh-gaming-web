<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/live_chat.php';
if (file_exists($file)) {
    $content = file_get_contents($file);
    
    // Replace Support
    $content = str_replace('>Support</span>', '><?= __("Support") ?></span>', $content);
    // Replace Offline in HTML
    $content = str_replace('>Offline</div>', '><?= __("Offline") ?></div>', $content);
    // Replace Online in JS
    $content = str_replace("statTxt.innerText = 'Online';", "statTxt.innerText = '<?= __(\"Online\") ?>';", $content);
    // Replace Offline in JS
    $content = str_replace("statTxt.innerText = 'Offline';", "statTxt.innerText = '<?= __(\"Offline\") ?>';", $content);
    
    // Also make sure lang.php is included at the top
    if (strpos($content, 'lang.php') === false && strpos($content, '<?php') !== false) {
        $content = preg_replace('/<\?php\s+/', "<?php\nrequire_once 'includes/lang.php';\n", $content, 1);
    }
    
    file_put_contents($file, $content);
    echo "live_chat.php updated.";
} else {
    echo "File not found.";
}
