<?php
// notice_status.php – FILE BASED (UPDATED / FIXED FOR SOUND + RED DOT)
// Old logic preserved (notice_flag.txt) + cache bypass + safety

date_default_timezone_set('Asia/Kolkata');

/* ===== Headers (IMPORTANT: no cache) ===== */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$flagFile = __DIR__ . '/notice_flag.txt';
$id = 0;

/* ===== Read current notice id ===== */
if (is_file($flagFile)) {
    $raw = trim((string)@file_get_contents($flagFile));
    if (ctype_digit($raw)) {
        $id = (int)$raw;
    }
}

/* ===== Safety ===== */
if ($id < 0) $id = 0;

/* ===== Response (ts helps cache bypass + debugging) ===== */
echo json_encode([
    'id' => $id,
    'ts' => time()
], JSON_UNESCAPED_SLASHES);
