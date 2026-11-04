<?php
$content = file_get_contents("gali_desawar.php");
$content = str_replace('id="menuToggle" title="Back"', 'title="Back"', $content);
file_put_contents("gali_desawar.php", $content);
echo "Fixed";
