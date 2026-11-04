<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/open_market.php';
$content = file_get_contents($file);

$search = '/* ================== GLOBAL API MODE TOGGLE ================== */
if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\' && isset($_POST[\'action\']) && $_POST[\'action\'] === \'toggle_api_mode\') {';

$replace = '/* ================== GLOBAL API MODE TOGGLE ================== */
// Ensure table exists to prevent crashes
$db->query("CREATE TABLE IF NOT EXISTS rk_config (
    `key` VARCHAR(50) PRIMARY KEY,
    `value` VARCHAR(255) NOT NULL
)");

if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\' && isset($_POST[\'action\']) && $_POST[\'action\'] === \'toggle_api_mode\') {';

// Normalize line endings to \n before replacing
$content_normalized = str_replace("\r\n", "\n", $content);
$search = str_replace("\r\n", "\n", $search);
$replace = str_replace("\r\n", "\n", $replace);

$count = 0;
$content_normalized = str_replace($search, $replace, $content_normalized, $count);

file_put_contents($file, $content_normalized);
echo "Replaced properly. count=$count";
