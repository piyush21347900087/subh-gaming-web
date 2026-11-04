<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_wining.php';
$content = file_get_contents($file);

$searchCSS = "        .win-badge-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 10px 0;
            margin: 10px 0;
        }
        .win-badge {
            background: linear-gradient(135deg, #ffd700, #ff9900);
            border-radius: 50px;
            padding: 8px 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(255, 170, 0, 0.4);
            animation: pulseWin 2s infinite;
            border: 2px solid #fff;
        }
        .win-icon {
            font-size: 26px;
            color: #fff;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }
        .win-text-col {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .win-title {
            font-size: 13px;
            font-weight: 800;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 2px;
            line-height: 1;
            margin-bottom: 2px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .win-num {
            font-size: 28px;
            font-weight: 900;
            color: #fff;
            line-height: 1;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        @keyframes pulseWin {
            0% { transform: scale(1); box-shadow: 0 4px 15px rgba(255, 170, 0, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 6px 20px rgba(255, 170, 0, 0.6); }
            100% { transform: scale(1); box-shadow: 0 4px 15px rgba(255, 170, 0, 0.4); }
        }";

$replaceCSS = "        .win-badge-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2px 0;
            margin: 4px 0;
        }
        .win-badge {
            background: linear-gradient(135deg, #ffd700, #ff9900);
            border-radius: 30px;
            padding: 4px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(255, 170, 0, 0.3);
            animation: pulseWin 2s infinite;
            border: 2px solid #fff;
        }
        .win-icon {
            font-size: 16px;
            color: #fff;
            filter: drop-shadow(0 1px 2px rgba(0,0,0,0.2));
        }
        .win-text-col {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .win-title {
            font-size: 10px;
            font-weight: 800;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1;
            margin-bottom: 1px;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.1);
        }
        .win-num {
            font-size: 18px;
            font-weight: 900;
            color: #fff;
            line-height: 1;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        @keyframes pulseWin {
            0% { transform: scale(1); box-shadow: 0 2px 8px rgba(255, 170, 0, 0.3); }
            50% { transform: scale(1.03); box-shadow: 0 4px 12px rgba(255, 170, 0, 0.5); }
            100% { transform: scale(1); box-shadow: 0 2px 8px rgba(255, 170, 0, 0.3); }
        }";

$content_normalized = str_replace("\r\n", "\n", $content);
$searchCSS = str_replace("\r\n", "\n", $searchCSS);
$replaceCSS = str_replace("\r\n", "\n", $replaceCSS);

$count = 0;
$content_normalized = str_replace($searchCSS, $replaceCSS, $content_normalized, $count);

file_put_contents($file, $content_normalized);
echo "Replaced CSS. count=$count";
