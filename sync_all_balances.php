<?php
// One-time / on-demand: recalculates every user's Total Balance (same formula as bio_data.php)
// and writes it into users.wallet_balance. Admin login required.
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'includes/db.php';
if (php_sapi_name() !== 'cli' && !isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}
@set_time_limit(0);
$db = db();
$res = $db->query("SELECT id, user_id FROM users");
$count = 0;
while ($r = $res->fetch_assoc()) {
    bio_total_balance($db, (int) $r['id'], trim((string) $r['user_id']));
    $count++;
}
echo "Synced {$count} users";
