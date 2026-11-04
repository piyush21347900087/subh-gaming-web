<?php
require_once 'includes/lang.php';
$chartFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/withdrawal.php';
$chartContent = file_get_contents($chartFile);

$replacements = [
    "b.textContent = m.linked ? 'Linked' : 'Not Linked';" => "b.textContent = m.linked ? '<?= __(\"Linked\") ?>' : '<?= __(\"Not Linked\") ?>';"
];

foreach ($replacements as $search => $replace) {
    if (strpos($chartContent, $replace) === false) {
        $chartContent = str_replace($search, $replace, $chartContent);
    }
}

file_put_contents($chartFile, $chartContent);
echo "withdrawal.php b.textContent updated.";
