<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/languages.php';
$content = file_get_contents($file);

$searchHTML = '    <div class="header">
        <h1>Choose Language</h1>
    </div>';

$replaceHTML = '    <div class="header">
        <button class="back-btn" onclick="goBack()"><i class="fa-solid fa-arrow-left"></i></button>
        <h1>Choose Language</h1>
    </div>';

$searchCSS = '        .header {
            padding: 24px 20px 16px;
        }
        .header h1 {
            font-size: 20px;
            font-weight: 700;
            color: #111;
        }';

$replaceCSS = '        .header {
            padding: 24px 20px 16px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header h1 {
            font-size: 20px;
            font-weight: 700;
            color: #111;
            margin: 0;
        }
        .back-btn {
            background: transparent;
            border: none;
            font-size: 20px;
            color: #111;
            cursor: pointer;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }';

$searchJS = '    function selectLang(el, code) {';
$replaceJS = '    function goBack() {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = "user_login.php";
        }
    }
    
    function selectLang(el, code) {';

$content = str_replace(str_replace("\r\n", "\n", $searchHTML), str_replace("\r\n", "\n", $replaceHTML), str_replace("\r\n", "\n", $content));
$content = str_replace(str_replace("\r\n", "\n", $searchCSS), str_replace("\r\n", "\n", $replaceCSS), str_replace("\r\n", "\n", $content));
$content = str_replace(str_replace("\r\n", "\n", $searchJS), str_replace("\r\n", "\n", $replaceJS), str_replace("\r\n", "\n", $content));

file_put_contents($file, $content);
echo "Back button added.";
