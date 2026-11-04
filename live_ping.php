<?php
require_once 'includes/db.php';
// includes/live_ping.php

if (session_status() !== PHP_SESSION_ACTIVE)
    session_start();

define('LIVE_SECONDS', 20); // Update every 20 seconds instead of every 1 second to save DB resources

if (!empty($_SESSION['user']['id'])) {
    $id = (int) $_SESSION['user']['id'];
    $now = time();

    if (empty($_SESSION['last_live_ping']) || $now - $_SESSION['last_live_ping'] >= LIVE_SECONDS) {
        $db = db();

        // Get real user_id and name from users table
        $stmt = $db->prepare("SELECT user_id, name FROM users WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($LL_user_id, $name);
        $stmt->fetch();
        $stmt->close();

        if (!empty($LL_user_id)) {
            // Upsert into live table with real user_id, name, and set status 'online'
            $stmt2 = $db->prepare(
                "INSERT INTO live (user_id, name, last_seen, status) VALUES (?, ?, NOW(), 'online')
                 ON DUPLICATE KEY UPDATE name=VALUES(name), last_seen=NOW(), status='online'"
            );
            $stmt2->bind_param('ss', $LL_user_id, $name);
            $stmt2->execute();
            $stmt2->close();
        }

        // $db->close(); // Shared connection, do not close!
        $_SESSION['last_live_ping'] = $now;
    }
}
