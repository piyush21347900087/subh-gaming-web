<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/languages.php';
$content = file_get_contents($file);

// Replace pink color with dashboard red
$content = str_replace('#ff005c', '#e60000', $content);

// Replace icon background from light pink to light red
$content = str_replace('#ffebf2', '#ffcccc', $content);

// Replace continue button with gradient
$content = str_replace('background: #e60000;', 'background: linear-gradient(to right, #e60000, #ff3333);', $content);

// Replace font from Outfit to Poppins to fully match
$content = str_replace('Outfit', 'Poppins', $content);

file_put_contents($file, $content);
echo "Theme updated.";
