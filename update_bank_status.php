<?php
require_once 'includes/lang.php';
$bankFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_bank.php';
$bankContent = file_get_contents($bankFile);

$replacements = [
    '<?= $bankStatus ?></span>' => '<?= __($bankStatus) ?></span>',
    '<?= $upiStatus ?></span>' => '<?= __($upiStatus) ?></span>'
];

foreach ($replacements as $search => $replace) {
    $bankContent = str_replace($search, $replace, $bankContent);
}

file_put_contents($bankFile, $bankContent);
echo "user_bank.php status labels translated.";
