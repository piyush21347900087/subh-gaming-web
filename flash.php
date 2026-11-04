<?php
$f = file_get_contents("index.php");

$findCSS = <<<'EOF'
        .btn-download {
            background: #ff4d4d;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(255, 77, 77, 0.3);
        }
EOF;

$replaceCSS = <<<'EOF'
        .btn-download {
            background: #ff4d4d;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(255, 77, 77, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-download::after {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.4) 50%, rgba(255,255,255,0) 100%);
            transform: skewX(-25deg);
            animation: flashSweep 2.5s infinite ease-in-out;
        }

        @keyframes flashSweep {
            0% { left: -150%; }
            40% { left: 200%; }
            100% { left: 200%; }
        }
EOF;

$f = str_replace($findCSS, $replaceCSS, $f);
file_put_contents("index.php", $f);
echo "DONE";
?>
