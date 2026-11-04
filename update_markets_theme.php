<?php
$files = glob(__DIR__ . '/*.php');

$oldBody = <<<CSS
    body {
      background-color: #000000 !important;
      background: #000000 !important;
      color: #ffffff;
      min-height: 100vh;
      padding-bottom: 90px;
    }
CSS;

$newBody = <<<CSS
    body {
      background-color: #000000 !important;
      background: #000000 !important;
      color: #ffffff;
      min-height: 100vh;
      padding-bottom: 90px;
    }
CSS;

$oldHeader = <<<CSS
    header {
      background-color: #000000 !important;
      background-image: none !important;
      padding-top: calc(env(safe-area-inset-top) + 10px);
      padding-bottom: 10px;
      padding-left: 14px;
      padding-right: 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      border-bottom: 1px solid #1a1a1a;
    }
CSS;

$newHeader = <<<CSS
    header {
      background-color: #000000 !important;
      background-image: none !important;
      padding-top: calc(env(safe-area-inset-top) + 10px);
      padding-bottom: 10px;
      padding-left: 14px;
      padding-right: 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      border-bottom: 1px solid #1a1a1a;
    }
CSS;

$count = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'background: linear-gradient(180deg, #881313') !== false) {
        $content = str_replace($oldBody, $newBody, $content);
        $content = str_replace($oldHeader, $newHeader, $content);
        file_put_contents($file, $content);
        $count++;
    }
}
echo "Updated $count files.";
?>
