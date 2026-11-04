<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_wining.php';
$content = file_get_contents($file);

$search1 = ".win-box { background: #ffffff; border-radius: 14px; box-shadow: var(--shadow); padding: 6px 10px; position: relative; border-left: 5px solid #2ecc71; margin-bottom: 7px; }";
$replace1 = ".win-box { background: #ffffff; border-radius: 10px; box-shadow: var(--shadow); padding: 4px 8px; position: relative; border-left: 4px solid #2ecc71; margin-bottom: 4px; }";

$search2 = "        .win-center-text {
            text-align: center;
            font-size: 26px;
            font-weight: 900;
            margin: 8px 0 2px 0;
            letter-spacing: 4px;
            animation: colorChange 2s infinite;
        }";
$replace2 = "        .win-center-text {
            text-align: center;
            font-size: 18px;
            font-weight: 900;
            margin: 0;
            line-height: 1.2;
            letter-spacing: 2px;
            animation: colorChange 2s infinite;
        }";


$content_normalized = str_replace("\r\n", "\n", $content);
$search1 = str_replace("\r\n", "\n", $search1);
$replace1 = str_replace("\r\n", "\n", $replace1);
$search2 = str_replace("\r\n", "\n", $search2);
$replace2 = str_replace("\r\n", "\n", $replace2);

$count1 = 0; $count2 = 0;
$content_normalized = str_replace($search1, $replace1, $content_normalized, $count1);
$content_normalized = str_replace($search2, $replace2, $content_normalized, $count2);

file_put_contents($file, $content_normalized);
echo "Replaced. count1: $count1, count2: $count2";
