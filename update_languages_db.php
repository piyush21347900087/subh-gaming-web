<?php
$file = 'd:/SUBH GAMING/SUBH GAMING Web Code/languages.php';
$content = file_get_contents($file);

$searchPHP = "    // Check if the user is logged in already just in case
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    if (isset(\$_SESSION['user']['id']) || isset(\$_COOKIE['remember_mobile'])) {
        header(\"Location: user_dashboard.php\");
    } else {
        header(\"Location: user_register.php\");
    }";

$replacePHP = "    // Check if the user is logged in already just in case
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    if (isset(\$_SESSION['user']['id'])) {
        require_once 'includes/db.php';
        \$db = db();
        \$stmt = \$db->prepare(\"UPDATE users SET language = ? WHERE id = ?\");
        if (\$stmt) {
            \$stmt->bind_param('si', \$lang, \$_SESSION['user']['id']);
            \$stmt->execute();
            \$stmt->close();
        }
        header(\"Location: user_dashboard.php\");
    } elseif (isset(\$_COOKIE['remember_mobile'])) {
        header(\"Location: user_dashboard.php\");
    } else {
        header(\"Location: user_register.php\");
    }";

$content = str_replace(str_replace("\r\n", "\n", $searchPHP), str_replace("\r\n", "\n", $replacePHP), str_replace("\r\n", "\n", $content));

file_put_contents($file, $content);
echo "languages.php updated to save DB.";
