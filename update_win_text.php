<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_wining.php';
$content = file_get_contents($file);

// Replace CSS
$searchCSS = "        .win-badge-container {
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

$replaceCSS = "        .win-center-text {
            text-align: center;
            font-size: 26px;
            font-weight: 900;
            margin: 8px 0 2px 0;
            letter-spacing: 4px;
            animation: colorChange 2s infinite;
        }
        @keyframes colorChange {
            0% { color: #ff0000; text-shadow: 0 2px 8px rgba(255,0,0,0.4); }
            25% { color: #00cc00; text-shadow: 0 2px 8px rgba(0,204,0,0.4); }
            50% { color: #0066ff; text-shadow: 0 2px 8px rgba(0,102,255,0.4); }
            75% { color: #ff00ff; text-shadow: 0 2px 8px rgba(255,0,255,0.4); }
            100% { color: #ff0000; text-shadow: 0 2px 8px rgba(255,0,0,0.4); }
        }";


// Replace JS
$searchJS = "            list.insertAdjacentHTML('beforeend', `<div class=\"win-box\">
                <div class=\"row-flex\">
                    <h2>\${w.market}</h2>
                    <div class=\"session-chip\">Session: \${session}</div>
                </div>
                
                <div class=\"win-badge-container\">
                    <div class=\"win-badge\">
                        <div class=\"win-icon\"><i class=\"fas fa-trophy\"></i></div>
                        <div class=\"win-text-col\">
                            <div class=\"win-title\">WINNER</div>
                            <div class=\"win-num\">\${parsedNum}</div>
                        </div>
                    </div>
                </div>

                <div class=\"row-flex\" style=\"margin-top:2px;\">";
                
$replaceJS = "            list.insertAdjacentHTML('beforeend', `<div class=\"win-box\">
                <div class=\"row-flex\" style=\"align-items:flex-start;\">
                    <h2>\${w.market}</h2>
                    <div style=\"display:flex; flex-direction:column; align-items:flex-end; gap:4px;\">
                        <div class=\"session-chip\">Session: \${session}</div>
                        <div style=\"font-size:12px; font-weight:800; color:#0066cc; background:#eef4ff; padding:2px 8px; border-radius:12px; border:1px solid #cce0ff;\">No: \${parsedNum}</div>
                    </div>
                </div>
                
                <div class=\"win-center-text\">WIN</div>

                <div class=\"row-flex\" style=\"margin-top:2px;\">";

$content_normalized = str_replace("\r\n", "\n", $content);
$searchCSS = str_replace("\r\n", "\n", $searchCSS);
$replaceCSS = str_replace("\r\n", "\n", $replaceCSS);
$searchJS = str_replace("\r\n", "\n", $searchJS);
$replaceJS = str_replace("\r\n", "\n", $replaceJS);

$count1 = 0; $count2 = 0;
$content_normalized = str_replace($searchCSS, $replaceCSS, $content_normalized, $count1);
$content_normalized = str_replace($searchJS, $replaceJS, $content_normalized, $count2);

file_put_contents($file, $content_normalized);
echo "Replaced. CSS count: $count1, JS count: $count2";
