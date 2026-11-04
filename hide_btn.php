<?php
$f = file_get_contents("index.php");

$hiddenBtn = <<<'EOF'
        <!-- Hidden button for Meta Event Setup / Tracking fallback -->
        <a href="index.php?apk=1" style="display:none;" onclick="fbq('trackCustom', 'DownloadApp');">DOWNLOAD NOW</a>

        <!-- Main Action Buttons -->
EOF;

$f = str_replace("<!-- Main Action Buttons -->", $hiddenBtn, $f);
file_put_contents("index.php", $f);
echo "DONE";
?>
