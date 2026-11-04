<?php
$dir = 'd:/SUBH GAMING/SUBH GAMING Web Code/';
$files = glob($dir . '*.php');

$replacements = [
    '<option value="Open"><?= __("Open") ?></option>' => '<option value="Open">Open</option>',
    '><?= __("Close") ?></option>' => '>Close</option>',
    '><?= __("Market Closed") ?></option>' => '>Market Closed</option>',
    'placeholder="<?= __("Enter points") ?>"' => 'placeholder="Enter points"',
    '<span><?= __("SUBMIT") ?></span>' => '<span>SUBMIT</span>',
    '><?= __("Alert") ?></h2>' => '>Alert</h2>',
    '><?= __("OK") ?></button>' => '>OK</button>',
    '><?= __("Bid Submitted!") ?></div>' => '>Bid Submitted!</div>',
    '><?= __("Your bid has been placed successfully.") ?>' => '>Your bid has been placed successfully.',
    "'<?= __(\"Connection error. Please try again.\") ?>'" => "'Connection error. Please try again.'",
    "'<?= __(\"Submission failed.\") ?>'" => "'Submission failed.'",
    "<?= __(\"Single Digit\") ?> - SD" => "Single Digit - SD",
    "<?= __(\"Jodi Digit\") ?> - JD" => "Jodi Digit - JD",
    "<?= __(\"Single Pana\") ?> - SP" => "Single Pana - SP",
    "<?= __(\"Double Pana\") ?> - DP" => "Double Pana - DP",
    "<?= __(\"Triple Pana\") ?> - TP" => "Triple Pana - TP",
    "<?= __(\"Half Sangam\") ?> - HS" => "Half Sangam - HS",
    "<?= __(\"Full Sangam\") ?> - FS" => "Full Sangam - FS",
    "<?= __(\"SP Motor\") ?>" => "SP Motor",
    "<?= __(\"DP Motor\") ?>" => "DP Motor",
    "<?= __(\"Single Digit Bulk\") ?> - SDB" => "Single Digit Bulk - SDB",
    "<?= __(\"Jodi Digit Bulk\") ?> - JDB" => "Jodi Digit Bulk - JDB",
    "<?= __(\"Single Pana Bulk\") ?> - SPB" => "Single Pana Bulk - SPB",
    "<?= __(\"Double Pana Bulk\") ?> - DPB" => "Double Pana Bulk - DPB",
    "<?= __(\"Triple Pana Bulk\") ?> - TPB" => "Triple Pana Bulk - TPB"
];

$updatedCount = 0;
foreach ($files as $f) {
    $fc = file_get_contents($f);
    // Ignore my script files
    if (basename($f) === 'revert_bid_games_lang.php' || basename($f) === 'fix_bid_games_lang.php') {
        continue;
    }
    
    $original = $fc;
    foreach ($replacements as $search => $replace) {
        if (strpos($fc, $search) !== false) {
            $fc = str_replace($search, $replace, $fc);
        }
    }
    if ($fc !== $original) {
        file_put_contents($f, $fc);
        echo "Reverted " . basename($f) . "\n";
        $updatedCount++;
    }
}
echo "Total reverted: $updatedCount\n";
