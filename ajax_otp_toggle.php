<?php
// ajax_otp_toggle.php — dedicated OTP ON/OFF toggle handler
error_reporting(0);
ini_set('display_errors', 0);

// Clean all output buffers first
while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: application/json; charset=utf-8');

// Session check
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['admin'])) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['otp_enabled'])) {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

require_once __DIR__ . '/includes/db.php';

try {
    $db     = db();
    $intVal = (int)$_POST['otp_enabled'];
    $status = ($intVal === 1) ? 'ON' : 'OFF';

    // Ensure row exists first
    $db->query("INSERT IGNORE INTO `otp_settings` (`id`, `status`) VALUES (1, 'ON')");

    // Update status using string ON/OFF
    $st = $db->prepare("UPDATE `otp_settings` SET `status` = ? WHERE `id` = 1");
    $st->bind_param('s', $status);
    $ok = $st->execute();
    $st->close();
    $db->close();

    echo json_encode(['ok' => true, 'otp_enabled' => $intVal, 'status' => $status]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
exit;
