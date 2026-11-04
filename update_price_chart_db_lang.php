<?php
require_once 'includes/lang.php';
$chartFile = 'd:/SUBH GAMING/SUBH GAMING Web Code/price_chart.php';
$chartContent = file_get_contents($chartFile);

$replacements = [
    "<?= htmlspecialchars(\$rate['game_type']) ?>" => "<?= htmlspecialchars(__(\$rate['game_type'])) ?>"
];

foreach ($replacements as $search => $replace) {
    if (strpos($chartContent, $replace) === false) {
        $chartContent = str_replace($search, $replace, $chartContent);
    }
}

file_put_contents($chartFile, $chartContent);
echo "price_chart.php dynamic strings updated.";
