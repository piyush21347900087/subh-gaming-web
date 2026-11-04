<?php
require_once 'includes/lang.php';
$files = [
    'd:/SUBH GAMING/SUBH GAMING Web Code/deposit.php',
    'd:/SUBH GAMING/SUBH GAMING Web Code/withdrawal.php'
];

$replacements = [
    '<div class="hindi-label">पैसे डालें / Min Deposit ₹<?= (int) $MIN_DEPOSIT ?></div>' => '<div class="hindi-label"><?= __("Add Money / Min Deposit") ?> ₹<?= (int) $MIN_DEPOSIT ?></div>',
    '<div class="hindi-label">पैसे निकालें / Min Withdraw ₹<?= (int) $MIN_WITHDRAW ?></div>' => '<div class="hindi-label"><?= __("Withdraw Money / Min Withdraw") ?> ₹<?= (int) $MIN_WITHDRAW ?></div>'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }
        file_put_contents($file, $content);
        echo basename($file) . " updated.\n";
    }
}
