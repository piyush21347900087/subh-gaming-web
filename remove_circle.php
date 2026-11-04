<?php
$f = file_get_contents("index.php");

$search = <<<'EOF'
        .logo-box img {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 4px solid #ff4d4d;
            box-shadow: 0 4px 25px rgba(255, 0, 0, 0.25);
            background: #000000;
            padding: 5px;
        }
EOF;

$replace = <<<'EOF'
        .logo-box img {
            width: 220px;
            height: auto;
            max-width: 100%;
            filter: drop-shadow(0 10px 25px rgba(255, 0, 0, 0.2));
        }
EOF;

$f = str_replace($search, $replace, $f);
file_put_contents("index.php", $f);
echo "DONE";
?>
