<?php
$f = file_get_contents("index.php");

$findCSS = <<<'EOF'
        .logo-box img {
            width: 220px;
            height: auto;
            max-width: 100%;
            filter: drop-shadow(0 10px 25px rgba(255, 0, 0, 0.2));
        }
EOF;

$replaceCSS = <<<'EOF'
        .logo-box img {
            width: 220px;
            height: auto;
            max-width: 100%;
            filter: drop-shadow(0 10px 25px rgba(255, 0, 0, 0.2));
            animation: floatLogo 3s ease-in-out infinite;
        }

        @keyframes floatLogo {
            0% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
            100% { transform: translateY(0); }
        }
EOF;

$f = str_replace($findCSS, $replaceCSS, $f);
file_put_contents("index.php", $f);
echo "DONE";
?>
