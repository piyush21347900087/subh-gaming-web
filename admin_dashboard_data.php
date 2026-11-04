<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['admin'])) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$db = db();

// LOGIN (total online)
$row = $db->query("SELECT COUNT(*) FROM live WHERE status='online'")->fetch_row();
$liveUsers = $row ? (int)$row[0] : 0;

// LIVE (active within last 20 sec)
$row2 = $db->query(
    "SELECT COUNT(*) FROM live
     WHERE status='online'
       AND last_seen >= NOW() - INTERVAL 20 SECOND"
)->fetch_row();
$liveActive = $row2 ? (int)$row2[0] : 0;

$db->close();

echo json_encode([
    'liveUsers'  => $liveUsers,
    'liveActive' => $liveActive
]);
