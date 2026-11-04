<?php
// api_gali_result_ts.php — Returns latest gali_result timestamp (for auto-refresh polling)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once 'includes/db.php';

try {
    $db = db();
    $db->query("SET time_zone = '+05:30'");

    $today = date('Y-m-d');

    // Get MAX(id) of today's gali_result → any new INSERT changes this
    $row = $db->query(
        "SELECT COALESCE(MAX(id), 0) AS latest_id,
                COALESCE(MAX(result_date), '') AS latest_date
         FROM gali_result
         WHERE result_date = '$today'"
    )->fetch_assoc();

    echo json_encode([
        'ok' => true,
        'latest_id' => (int) ($row['latest_id'] ?? 0),
        'ts' => time()
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'latest_id' => 0, 'ts' => time()]);
}
