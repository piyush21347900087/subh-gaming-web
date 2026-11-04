<?php
require_once 'includes/lang.php';
$chartFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/withdrawal_history.php';
$chartContent = file_get_contents($chartFile);

$replacements = [
    '<div class="info-text" id="infoText">Withdrawal History</div>' => '<div class="info-text" id="infoText"><?= __("Withdrawal History") ?></div>',
    "info.textContent = 'Withdrawal History / निकासी हिस्ट्री';" => "info.textContent = '<?= __(\"Withdrawal History\") ?>';",
    "info.textContent = 'Withdrawal History';" => "info.textContent = '<?= __(\"Withdrawal History\") ?>';",
    '<th>Sr</th>' => '<th><?= __("Sr") ?></th>',
    '<th>Amount</th>' => '<th><?= __("Amount") ?></th>',
    '<th>Date/Time</th>' => '<th><?= __("Date/Time") ?></th>',
    '<th>Method</th>' => '<th><?= __("Method") ?></th>',
    '<th>Status</th>' => '<th><?= __("Status") ?></th>',
    "'<button class=\"prev\">Prev</button><span class=\"page-info\" style=\"margin:0 8px;font-weight:900;color:#5d4037;\"></span><button class=\"next\">Next</button>';" => "'<button class=\"prev\"><?= __(\"Prev\") ?></button><span class=\"page-info\" style=\"margin:0 8px;font-weight:900;color:#5d4037;\"></span><button class=\"next\"><?= __(\"Next\") ?></button>';",
    '<td class="<?= $stClass ?>" style="font-size: 11px;"><?= ucfirst((string) $w[\'status\']) ?></td>' => '<td class="<?= $stClass ?>" style="font-size: 11px;"><?= __(ucfirst((string) $w[\'status\'])) ?></td>'
];

foreach ($replacements as $search => $replace) {
    if (strpos($chartContent, $search) !== false) {
        $chartContent = str_replace($search, $replace, $chartContent);
    }
}

file_put_contents($chartFile, $chartContent);
echo "withdrawal_history.php strings updated.";
