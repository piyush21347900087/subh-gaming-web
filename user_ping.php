<?php
/* ----------------------------------------------------------
   includes/user_ping.php
   • Ensures user is logged in
   • users.is_active EVERY REQUEST refreshed from DB
   • users.last_seen updated max once per 60 sec
---------------------------------------------------------- */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* ---------- 1. Must be logged in ---------- */
if (empty($_SESSION['user']['id'])) {
    header('Location: user_login.php');
    exit;
}

$uid = (int) $_SESSION['user']['id'];

/* ---------- 2. Constants ---------- */
if (!defined('USER_PING_INTERVAL')) {
    define('USER_PING_INTERVAL', 60); // seconds
}

$now = time();

/* ---------- 3. DB Connection ---------- */
// Ensure db.php is loaded
require_once __DIR__ . '/db.php';

try {
    $db = db();
} catch (Throwable $e) {
    // Fail silently
    return;
}

/* -----------------------------------------------------
   4. is_active check → Refreshed every request from DB to block instantly
   ----------------------------------------------------- */
$isActive = 1;
if ($stmt = $db->prepare("SELECT is_active FROM users WHERE id = ?")) {
    $stmt->bind_param('i', $uid);
    if ($stmt->execute()) {
        $stmt->bind_result($dbActive);
        if ($stmt->fetch()) {
            $isActive = (int) $dbActive;
        }
    }
    $stmt->close();
}
$_SESSION['user']['is_active'] = $isActive;

if ($isActive === 0) {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
              || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
              || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);
              
    if ($isAjax) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Account deactivated']);
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Suspended</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: 'Roboto', sans-serif;
                background: #f8fafc;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                color: #333;
                box-sizing: border-box;
            }
            .freeze-card {
                background: #fff;
                border-radius: 20px;
                padding: 30px 24px;
                max-width: 400px;
                width: 90%;
                text-align: center;
                box-shadow: 0 10px 25px rgba(0,0,0,0.08);
                border: 1px solid rgba(229, 57, 53, 0.1);
            }
            .freeze-icon {
                font-size: 55px;
                color: #e53935;
                margin-bottom: 18px;
                animation: pulseWarning 1.5s infinite;
            }
            @keyframes pulseWarning {
                0% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.1); opacity: 0.8; }
                100% { transform: scale(1); opacity: 1; }
            }
            .freeze-title {
                font-size: 20px;
                font-weight: 700;
                color: #111;
                margin-bottom: 10px;
            }
            .freeze-desc {
                font-size: 14px;
                color: #666;
                line-height: 1.5;
                margin-bottom: 24px;
            }
            .btn-admin {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                height: 48px;
                background: #ff005c;
                color: #fff;
                text-decoration: none;
                font-weight: 700;
                border-radius: 50px;
                box-shadow: 0 4px 12px rgba(255,0,92,0.25);
                margin-bottom: 12px;
                transition: transform 0.2s ease;
            }
            .btn-admin:active {
                transform: scale(0.98);
            }
            .btn-logout {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                height: 48px;
                background: #f1f5f9;
                color: #475569;
                text-decoration: none;
                font-weight: 600;
                border-radius: 50px;
                border: 1px solid #cbd5e1;
            }
        </style>
    <link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
    <body>
        <div class="freeze-card">
            <div class="freeze-icon">
                <i class="fas fa-lock"></i>
            </div>
            <div class="freeze-title">Account Suspended</div>
            <div class="freeze-desc">Your account has been deactivated by the administrator. Please contact support to resolve this issue.</div>
            
            <a href="https://wa.me/917020691217" class="btn-admin" target="_blank" rel="noopener">
                <i class="fab fa-whatsapp" style="font-size:18px;"></i> Contact to Admin
            </a>
            
            <a href="user_logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/* -----------------------------------------------------
   5. last_seen update → sirf 60 second me 1 baar
----------------------------------------------------- */
$needUpdate = (
    empty($_SESSION['last_ping']) ||
    (time() - $_SESSION['last_ping']) >= USER_PING_INTERVAL
);

if ($needUpdate) {
    if ($stmt2 = $db->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")) {
        $stmt2->bind_param('i', $uid);
        $stmt2->execute();
        $stmt2->close();
    }
    $_SESSION['last_ping'] = time();
}

// DO NOT CLOSE SHARED CONNECTION
// $db->close();
