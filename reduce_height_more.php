<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_wining.php';
$content = file_get_contents($file);

$search1 = "        .win-center-text {
            text-align: center;
            font-size: 18px;
            font-weight: 900;
            margin: 0;
            line-height: 1.2;
            letter-spacing: 2px;
            animation: colorChange 2s infinite;
        }";
$replace1 = "        .win-center-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 18px;
            font-weight: 900;
            margin: 0;
            line-height: 1;
            letter-spacing: 2px;
            animation: colorChange 2s infinite;
            z-index: 10;
            background: rgba(255,255,255,0.7);
            padding: 2px 8px;
            border-radius: 6px;
        }";


$content_normalized = str_replace("\r\n", "\n", $content);
$search1 = str_replace("\r\n", "\n", $search1);
$replace1 = str_replace("\r\n", "\n", $replace1);

$count1 = 0;
$content_normalized = str_replace($search1, $replace1, $content_normalized, $count1);

file_put_contents($file, $content_normalized);
echo "Replaced. count1: $count1";
