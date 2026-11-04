<?php
$f = 'bio_data.php';
$c = file_get_contents($f);
$c = preg_replace('/<\?=\s*\$isActiveRaw\s*\?\s*\'Active Account\'\s*:\s*\'Deactive Account\'\s*\?>\s*<\/span>/', '', $c);
file_put_contents($f, $c);
echo 'Done';
?>
