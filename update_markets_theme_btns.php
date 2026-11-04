<?php
$files = glob(__DIR__ . '/*.php');

$oldBtn = <<<CSS
    .header-back-btn {
      width: 38px;
      height: 38px;
      border-radius: 8px;
      background: #e53935;
CSS;

$newBtn = <<<CSS
    .header-back-btn {
      width: 38px;
      height: 38px;
      border-radius: 8px;
      background: #e53935;
CSS;

$oldWallet = <<<CSS
    .wallet-display {
      background: #e53935;
      border: none;
CSS;

$newWallet = <<<CSS
    .wallet-display {
      background: #e53935;
      border: none;
CSS;

$count = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'background: #e53935;') !== false) {
        $content = str_replace($oldBtn, $newBtn, $content);
        $content = str_replace($oldWallet, $newWallet, $content);
        file_put_contents($file, $content);
        $count++;
    }
}
echo "Updated $count files.";
?>
