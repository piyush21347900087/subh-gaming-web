<?php
$f = file_get_contents("index.php");

$findBtn = "class=\"btn-large btn-whatsapp\">";
$replaceBtn = "id=\"whatsapp_btn\" class=\"btn-large btn-whatsapp\" onclick=\"if(window.fbq) { fbq('track', 'Contact'); }\">";

$f = str_replace($findBtn, $replaceBtn, $f);
file_put_contents("index.php", $f);
echo "DONE";
?>
