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
    $changed = false;
    if (strpos($content, $oldBtn) !== false) {
        $content = str_replace($oldBtn, $newBtn, $content);
        $changed = true;
    }
    if (strpos($content, $oldWallet) !== false) {
        $content = str_replace($oldWallet, $newWallet, $content);
        $changed = true;
    }
    if ($changed) {
        file_put_contents($file, $content);
        $count++;
    }
}
echo "Updated $count files.";
?>
