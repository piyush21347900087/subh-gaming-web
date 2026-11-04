<?php
require_once 'includes/lang.php';
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_dashboard.php';
$content = file_get_contents($file);

$content = str_replace('>CHART</span>', '><?= __("CHART") ?></span>', $content);
$content = str_replace("statusText.innerText = 'RUNNING';", "statusText.innerText = '<?= __(\"RUNNING\") ?>';", $content);

file_put_contents($file, $content);
echo "Chart and RUNNING fixed in JS.";
