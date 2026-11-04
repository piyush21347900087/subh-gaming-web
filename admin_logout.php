<?php
require_once 'includes/db.php';
// admin_logout.php – logs out admin and redirects to login page
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

// ---- Update admin_status to OFFLINE ----
if (isset($_SESSION['admin_id'])) {

    $adminId = (int)$_SESSION['admin_id'];

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $db = db();

        $stmt = $db->prepare("UPDATE admin_status SET status='offline' WHERE id=?");
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();
        $db->close();

    } catch (Throwable $e) {
        // agar DB error aaye to bhi logout continue hoga
    }
}

// ---- Clear session ----
$_SESSION = [];

// ---- Destroy session cookie ----
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ---- Destroy session ----
session_destroy();

// Redirect to login page
header('Location: admin_login.php');
exit;
