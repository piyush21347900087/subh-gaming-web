<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = trim($_GET['user_id'] ?? '');
if ($uid === '') {
    header("Location: user_login.php");
    exit;
}

// sanitize
$uid = preg_replace('/[^A-Za-z0-9_]/', '', $uid);

// Save in session for safety (optional)
$_SESSION['app_uid'] = $uid;

// Redirect to app home with uid in URL
header("Location: user_login.php?uid=" . urlencode($uid));
exit;
