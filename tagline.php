<?php
$f = file_get_contents("index.php");
$f = str_replace(
    "<p class=\"tagline\">Official Satta Matka Platform</p>",
    "<p class=\"tagline\">Secure & Instant Result Platform</p>",
    $f
);
file_put_contents("index.php", $f);
echo "DONE";
?>
