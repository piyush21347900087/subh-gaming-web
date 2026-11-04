<?php
$content = file_get_contents("withdrawal_req.php");

$find1 = "'image' => 'https://subhgaming.online/images/logo.png',";
$rep1 = "";
$content = str_replace($find1, $rep1, $content);

$find2 = "'picture' => 'https://subhgaming.online/images/logo.png'";
$rep2 = "";
$content = str_replace($find2, $rep2, $content);

// Cleanup empty lines left by str_replace
$content = preg_replace("/\n\s*\n/", "\n", $content);

file_put_contents("withdrawal_req.php", $content);
echo "Updated withdrawal_req.php\n";

$fcm = file_get_contents("fcm_helper.php");
$fcm = str_replace("'image'   => 'https://subhgaming.online/images/logo.png',", "", $fcm);
$fcm = str_replace("'picture' => 'https://subhgaming.online/images/logo.png',", "", $fcm);
$fcm = preg_replace("/\n\s*\n/", "\n", $fcm);
file_put_contents("fcm_helper.php", $fcm);
echo "Updated fcm_helper.php\n";

