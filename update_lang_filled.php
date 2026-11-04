<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/languages.php';
$content = file_get_contents($file);

$searchCSS = '        .lang-card.selected {
            border: 1.5px solid #000000;
            background: #fff;
        }
        
        .lang-left {';

$replaceCSS = '        .lang-card.selected {
            border: 1.5px solid #000000;
            background: linear-gradient(to right, #000000, #333333);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        .lang-card.selected .lang-name-en {
            color: #dddddd;
        }
        
        .lang-left {';

$searchColor = '        .lang-card.selected .lang-name-local {
            color: #000000;
        }';

$replaceColor = '        .lang-card.selected .lang-name-local {
            color: #ffffff;
        }';

$content = str_replace(str_replace("\r\n", "\n", $searchCSS), str_replace("\r\n", "\n", $replaceCSS), str_replace("\r\n", "\n", $content));
$content = str_replace(str_replace("\r\n", "\n", $searchColor), str_replace("\r\n", "\n", $replaceColor), str_replace("\r\n", "\n", $content));

file_put_contents($file, $content);
echo "Filled box style applied.";
