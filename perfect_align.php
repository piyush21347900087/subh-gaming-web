<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_wining.php';
$content = file_get_contents($file);

// 1. Fix win-box CSS
$searchBox = ".win-box { background: #ffffff; border-radius: 10px; box-shadow: var(--shadow); padding: 4px 8px; position: relative; border-left: 4px solid #2ecc71; margin-bottom: 4px; }";
$replaceBox = ".win-box { background: #ffffff; border-radius: 12px; box-shadow: var(--shadow); padding: 8px 12px; position: relative; border-left: 4px solid #2ecc71; margin-bottom: 12px; }";

// 2. Fix win-center-text CSS
$searchText = "        .win-center-text {
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
$replaceText = "        .win-center-text {
            font-size: 20px;
            font-weight: 900;
            margin: 0;
            line-height: 1;
            letter-spacing: 2px;
            animation: colorChange 2s infinite;
            flex: 1;
            text-align: center;
        }
        .no-chip {
            font-size:10px; 
            font-weight:800; 
            color:#0066cc; 
            background:#eef4ff; 
            padding:2px 8px; 
            border-radius:999px; 
            border:1px solid rgba(0,102,204,0.16);
        }";

// 3. Fix JS Template
$searchJS = "            list.insertAdjacentHTML('beforeend', `<div class=\"win-box\">
                <div class=\"row-flex\" style=\"align-items:flex-start;\">
                    <h2>\${w.market}</h2>
                    <div style=\"display:flex; flex-direction:column; align-items:flex-end; gap:4px;\">
                        <div class=\"session-chip\">Session: \${session}</div>
                        <div style=\"font-size:12px; font-weight:800; color:#0066cc; background:#eef4ff; padding:2px 8px; border-radius:12px; border:1px solid #cce0ff;\">No: \${parsedNum}</div>
                    </div>
                </div>
                
                <div class=\"win-center-text\">WIN</div>

                <div class=\"row-flex\" style=\"margin-top:2px;\">
                    <div>
                        <div class=\"win-val\">Win ₹\${winAmount.toLocaleString('en-IN')}</div>
                        <div class=\"bid-amount\">Bid ₹\${bidAmount.toLocaleString('en-IN')}</div>
                    </div>
                    <div class=\"win-side-tags\">
                        <div class=\"info-tag\">\${bidType}</div>
                    </div>
                </div>
                <div class=\"footer-info\">
                    <span><i class=\"far fa-calendar-alt\"></i> \${dateStr}</span>
                    <span><i class=\"far fa-clock\"></i> \${timeStr}</span>
                </div>
            </div>`);";

$replaceJS = "            list.insertAdjacentHTML('beforeend', `<div class=\"win-box\">
                <div class=\"row-flex\">
                    <h2>\${w.market}</h2>
                    <div style=\"display:flex; align-items:center; gap:6px;\">
                        <div class=\"session-chip\">Session: \${session}</div>
                        <div class=\"no-chip\">No: \${parsedNum}</div>
                    </div>
                </div>

                <div class=\"row-flex\" style=\"margin-top:6px; margin-bottom:6px;\">
                    <div style=\"flex:1;\">
                        <div class=\"win-val\">Win ₹\${winAmount.toLocaleString('en-IN')}</div>
                        <div class=\"bid-amount\">Bid ₹\${bidAmount.toLocaleString('en-IN')}</div>
                    </div>
                    
                    <div class=\"win-center-text\">WIN</div>

                    <div class=\"win-side-tags\" style=\"flex:1; align-items:flex-end;\">
                        <div class=\"info-tag\">\${bidType}</div>
                    </div>
                </div>
                
                <div class=\"footer-info\">
                    <span><i class=\"far fa-calendar-alt\"></i> \${dateStr}</span>
                    <span><i class=\"far fa-clock\"></i> \${timeStr}</span>
                </div>
            </div>`);";

$content_normalized = str_replace("\r\n", "\n", $content);
$searchBox = str_replace("\r\n", "\n", $searchBox);
$replaceBox = str_replace("\r\n", "\n", $replaceBox);
$searchText = str_replace("\r\n", "\n", $searchText);
$replaceText = str_replace("\r\n", "\n", $replaceText);
$searchJS = str_replace("\r\n", "\n", $searchJS);
$replaceJS = str_replace("\r\n", "\n", $replaceJS);

$count1 = 0; $count2 = 0; $count3 = 0;
$content_normalized = str_replace($searchBox, $replaceBox, $content_normalized, $count1);
$content_normalized = str_replace($searchText, $replaceText, $content_normalized, $count2);
$content_normalized = str_replace($searchJS, $replaceJS, $content_normalized, $count3);

file_put_contents($file, $content_normalized);
echo "Replaced. count1=$count1, count2=$count2, count3=$count3";
