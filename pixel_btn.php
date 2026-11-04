<?php
$f = file_get_contents("index.php");

$findBtn = "<a href=\"index.php?apk=1\" class=\"btn-large btn-download\">";
$replaceBtn = "<a href=\"index.php?apk=1\" id=\"download_app_btn\" class=\"btn-large btn-download\" onclick=\"if(window.fbq) { fbq('track', 'Lead'); fbq('trackCustom', 'DownloadClick'); }\">";

$f = str_replace($findBtn, $replaceBtn, $f);
file_put_contents("index.php", $f);
echo "DONE";
?>
