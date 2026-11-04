<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/languages.php';
$content = file_get_contents($file);

// 1. Box border and text color for selected card
$content = str_replace('border: 1.5px solid #e60000;', 'border: 1.5px solid #000000;', $content);
$content = str_replace('color: #e60000;', 'color: #000000;', $content);

// 2. Button background and shadow
$content = str_replace('background: linear-gradient(to right, #e60000, #ff3333);', 'background: linear-gradient(to right, #000000, #333333);', $content);
$content = str_replace('box-shadow: 0 4px 15px rgba(255, 0, 92, 0.3);', 'box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);', $content);

// Let's also set border-radius to 15px to match pill buttons if it's currently 8px
$content = str_replace('border-radius: 8px;', 'border-radius: 15px;', $content);

file_put_contents($file, $content);
echo "Colors updated to black.";
