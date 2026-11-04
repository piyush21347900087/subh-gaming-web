<?php
/**
 * auto_declare_starline4.php
 * Background / endpoint trigger for auto-generating Starline 4 results.
 */
header('Content-Type: application/json');
require_once 'includes/db.php';
require_once 'process_starline4_wins.php';
date_default_timezone_set('Asia/Kolkata');

$db = db();
$db->set_charset('utf8mb4');
starline4_ensure_schema($db);
starline4_seed_default_markets($db);

$res = starline4_auto_generate_results($db);
echo json_encode($res);
