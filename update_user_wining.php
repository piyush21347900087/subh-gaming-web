<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/user_wining.php';
$content = file_get_contents($file);

// 1. Update SQL Query to fetch bet_key
$search1 = "SELECT market, bet_type AS game_type, session_type, amount, wining_balance, win_time AS created_at FROM wining";
$replace1 = "SELECT market, bet_type AS game_type, session_type, amount, wining_balance, win_time AS created_at, bet_key FROM wining";

// 2. Add CSS rules
$search2 = "    .prev-empty {
            padding: 18px 6px;
            text-align: center;
            color: #666;
            font-size: .9rem
        }
    </style>";
$replace2 = "    .prev-empty {
            padding: 18px 6px;
            text-align: center;
            color: #666;
            font-size: .9rem
        }
        .win-center-anim {
            text-align: center;
            padding: 15px 0;
            animation: pulseWin 1.5s infinite;
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.05), rgba(40, 167, 69, 0.15));
            border-radius: 12px;
            margin: 15px 0;
            border: 1px dashed rgba(40, 167, 69, 0.3);
        }
        .win-text {
            font-size: 20px;
            font-weight: 800;
            color: #28a745;
            text-shadow: 0 0 10px rgba(40, 167, 69, 0.3);
            letter-spacing: 2px;
            line-height: 1;
        }
        .win-number {
            font-size: 32px;
            font-weight: 900;
            color: #e60000;
            background: -webkit-linear-gradient(#ff0000, #990000);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-top: 5px;
            line-height: 1;
            letter-spacing: 1px;
        }
        @keyframes pulseWin {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>";

// 3. Update JavaScript output
$search3 = "            const winAmount = parseFloat(w.wining_balance || 0);
            list.insertAdjacentHTML('beforeend', `<div class=\"win-box\"><div class=\"row-flex\"><h2>\${w.market}</h2><div class=\"session-chip\">Session: \${session}</div></div><div class=\"row-flex\" style=\"margin-top:2px;\"><div><div class=\"win-val\">Win ₹\${winAmount.toLocaleString('en-IN')}</div><div class=\"bid-amount\">Bid ₹\${bidAmount.toLocaleString('en-IN')}</div></div><div class=\"win-side-tags\"><div class=\"info-tag\">\${bidType}</div></div></div><div class=\"footer-info\"><span><i class=\"far fa-calendar-alt\"></i> \${dateStr}</span><span><i class=\"far fa-clock\"></i> \${timeStr}</span></div></div>`);";
            
$replace3 = "            const winAmount = parseFloat(w.wining_balance || 0);
            const betKey = w.bet_key ? w.bet_key : 'N/A';
            list.insertAdjacentHTML('beforeend', `<div class=\"win-box\">
                <div class=\"row-flex\">
                    <h2>\${w.market}</h2>
                    <div class=\"session-chip\">Session: \${session}</div>
                </div>
                
                <div class=\"win-center-anim\">
                    <div class=\"win-text\">🎉 WINNER 🎉</div>
                    <div class=\"win-number\">\${betKey}</div>
                </div>

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

$content_normalized = str_replace("\r\n", "\n", $content);
$search1 = str_replace("\r\n", "\n", $search1);
$replace1 = str_replace("\r\n", "\n", $replace1);
$search2 = str_replace("\r\n", "\n", $search2);
$replace2 = str_replace("\r\n", "\n", $replace2);
$search3 = str_replace("\r\n", "\n", $search3);
$replace3 = str_replace("\r\n", "\n", $replace3);

$count1 = 0; $count2 = 0; $count3 = 0;
$content_normalized = str_replace($search1, $replace1, $content_normalized, $count1);
$content_normalized = str_replace($search2, $replace2, $content_normalized, $count2);
$content_normalized = str_replace($search3, $replace3, $content_normalized, $count3);

file_put_contents($file, $content_normalized);
echo "Replaced properly. count1=$count1, count2=$count2, count3=$count3";
