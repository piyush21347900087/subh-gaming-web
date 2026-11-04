<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/languages.php';
$content = file_get_contents($file);

$searchCSS = "        .lang-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #ffcccc;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
        }";

$replaceCSS = "        .lang-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e6e9f0, #eef1f5, #bdc3c7);
            color: #000000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: transform 0.2s ease;
        }
        .lang-card:hover .lang-icon, .lang-card.selected .lang-icon {
            transform: scale(1.05);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }";

$content = str_replace(str_replace("\r\n", "\n", $searchCSS), str_replace("\r\n", "\n", $replaceCSS), str_replace("\r\n", "\n", $content));

file_put_contents($file, $content);
echo "Bubble effect added.";
