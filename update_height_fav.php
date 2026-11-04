<?php
$f = file_get_contents("index.php");

// 1. Reduce height of .steps-row
$f = str_replace("padding: 16px 20px;", "padding: 10px 20px;", $f);

// 2. Add Favicon
$favCode = <<<'EOF'
    <title>SUBH GAMING | Official Platform</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(index_asset_url('images/logo.png'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(index_asset_url('images/logo.png'), ENT_QUOTES, 'UTF-8') ?>">
EOF;

$f = str_replace("<title>SUBH GAMING | Official Platform</title>", $favCode, $f);

file_put_contents("index.php", $f);
echo "DONE";
?>
