<?php
$f = file_get_contents("index.php");
$f = str_replace(
    "<i class=\"fa-solid fa-download\"></i> Download App",
    "<i class=\"fa-brands fa-android\"></i> Download App",
    $f
);
file_put_contents("index.php", $f);
echo "DONE";
?>
