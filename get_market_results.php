<?php
require_once 'includes/db.php';
/*
|--------------------------------------------------------------------------
| GET MARKET RESULTS API
|--------------------------------------------------------------------------
| This file fetches the latest market results for today from the database
| and outputs them in JSON format for the Android application.
|
| Access: Public (No Admin Session Check)
*/

/* ================== DB CONNECTION ================== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    // ⚠️ Use the same credentials as open_market.php
    $db = db();} catch (Exception $e) {
    // Log the error internally, but return a general error to the app
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Service Unavailable.']);
    exit;
}

/* ================== CONFIGURATION & HEADERS ================== */
// Set header to inform the client (Android App) that the response is JSON
header('Content-Type: application/json');
// Allow caching for a short period (e.g., 60 seconds)
header('Cache-Control: max-age=60, public'); 

$today = date('Y-m-d');

/* ================== FETCH LATEST RESULTS FOR TODAY ================== */

// Query to select the most recently declared result for each market today
$stmt = $db->prepare("
  SELECT 
    mr.market AS market_name, 
    mr.result_str AS result_number, 
    mr.open_pana, 
    mr.open_digit, 
    mr.close_digit, 
    mr.close_pana,
    UNIX_TIMESTAMP(mr.declared_at) AS declared_timestamp
    FROM market_result mr
    JOIN (
      -- Subquery to find the maximum (latest) declared time for each market today
      SELECT market, MAX(declared_at) AS dt
        FROM market_result
       WHERE result_date = ?
       GROUP BY market
    ) x ON x.market=mr.market AND x.dt=mr.declared_at
   WHERE mr.result_date = ?
   ORDER BY mr.market ASC
");

$stmt->bind_param('ss', $today, $today);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$db->close();

/* ================== JSON RESPONSE ================== */

// Structure the data for the Android App
$response = [
    'success' => true,
    'date' => $today,
    'total_markets' => count($rows),
    'results' => $rows
];

echo json_encode($response, JSON_PRETTY_PRINT);
