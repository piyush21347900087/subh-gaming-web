<?php
$dir = 'd:/SUBH GAMING/SUBH GAMING Web Code/';
$files = glob($dir . '*.php');

$replacements = [
    '<option value="Open">Open</option>' => '<option value="Open"><?= __("Open") ?></option>',
    '>Close</option>' => '><?= __("Close") ?></option>',
    '>Market Closed</option>' => '><?= __("Market Closed") ?></option>',
    'placeholder="Enter points"' => 'placeholder="<?= __("Enter points") ?>"',
    '<span>SUBMIT</span>' => '<span><?= __("SUBMIT") ?></span>',
    '>Alert</h2>' => '><?= __("Alert") ?></h2>',
    '>OK</button>' => '><?= __("OK") ?></button>',
    '>Bid Submitted!</div>' => '><?= __("Bid Submitted!") ?></div>',
    '>Your bid has been placed successfully.' => '><?= __("Your bid has been placed successfully.") ?>',
    "'Connection error. Please try again.'" => "'<?= __(\"Connection error. Please try again.\") ?>'",
    "'Submission failed.'" => "'<?= __(\"Submission failed.\") ?>'"
];

$updatedCount = 0;
$targetFiles = [
    'single_digit_bulk.php',
    'single_digit.php',
    'jodi_digit_bulk.php',
    'jodi_digit.php',
    'single_pana_bulk.php',
    'single_pana.php',
    'double_pana_bulk.php',
    'double_pana.php',
    'triple_pana_bulk.php',
    'triple_pana.php',
    'sp_motor.php',
    'dp_motor.php',
    'half_sangam.php',
    'full_sangam.php'
];

foreach ($files as $f) {
    if (!in_array(basename($f), $targetFiles)) {
        continue;
    }
    
    $fc = file_get_contents($f);
    $original = $fc;
    foreach ($replacements as $search => $replace) {
        if (strpos($fc, $search) !== false && strpos($fc, $replace) === false) {
            $fc = str_replace($search, $replace, $fc);
        }
    }
    if ($fc !== $original) {
        file_put_contents($f, $fc);
        echo "Updated " . basename($f) . "\n";
        $updatedCount++;
    }
}
echo "Total updated: $updatedCount\n";
