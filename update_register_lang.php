<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_register.php';
$content = file_get_contents($file);

$searchPHP = "            \$ins = \$mysqli->prepare(\"INSERT INTO users (user_id,name,mobile_no,password,is_active) VALUES (?,?,?,?,?)\");
            \$is_active = 1;
            \$ins->bind_param('ssssi', \$user_id, \$name, \$mobile, \$password, \$is_active);";

$replacePHP = "            \$ins = \$mysqli->prepare(\"INSERT INTO users (user_id,name,mobile_no,password,is_active,language) VALUES (?,?,?,?,?,?)\");
            \$is_active = 1;
            \$langCode = \$_COOKIE['app_lang'] ?? 'en';
            \$ins->bind_param('ssssis', \$user_id, \$name, \$mobile, \$password, \$is_active, \$langCode);";

$content = str_replace(str_replace("\r\n", "\n", $searchPHP), str_replace("\r\n", "\n", $replacePHP), str_replace("\r\n", "\n", $content));

file_put_contents($file, $content);
echo "user_register.php updated for language DB insert.";
