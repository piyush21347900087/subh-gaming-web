<?php
$f = file_get_contents("index.php");
$f = str_replace(
    "<span>Play</span>",
    "<span>Play Now</span>",
    $f
);
file_put_contents("index.php", $f);
echo "DONE";
?>
