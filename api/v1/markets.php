<?php
/**
 * SUBH GAMING API v1 - Real-time Markets & Results
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/app_brand.php';
require_once __DIR__ . '/../../includes/api_keys.php';

date_default_timezone_set('Asia/Kolkata');
$nowMin = (int)date('G') * 60 + (int)date('i');

try {
    $db = db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$today = ((int)date('G') < 6) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

// Fetch today's declared results
$resultsMap = [];
$stmtRes = $db->prepare("SELECT market, result_str, declared_at FROM market_result WHERE result_date = ?");
if ($stmtRes) {
    $stmtRes->bind_param('s', $today);
    $stmtRes->execute();
    $stmtRes->bind_result($mkt, $rStr, $declAt);
    while ($stmtRes->fetch()) {
        $resultsMap[strtoupper($mkt)] = [
            'result'      => $rStr,
            'declared_at' => $declAt
        ];
    }
    $stmtRes->close();
}

// Fetch markets
$resMarkets = $db->query("SELECT id, market, open_time, close_time, status FROM markets ORDER BY id ASC");
$marketList = [];

if ($resMarkets) {
    while ($row = $resMarkets->fetch_assoc()) {
        $mktUpper = strtoupper($row['market']);
        $resData = $resultsMap[$mktUpper] ?? null;

        // Parse open and close times in minutes
        $openMin = 0;
        $closeMin = 0;
        if (preg_match('/(\d+):(\d+)\s*(AM|PM)?/i', $row['open_time'] ?? '', $m)) {
            $h = (int)$m[1];
            $min = (int)$m[2];
            $ampm = strtoupper($m[3] ?? '');
            if ($ampm === 'PM' && $h < 12) $h += 12;
            if ($ampm === 'AM' && $h === 12) $h = 0;
            $openMin = $h * 60 + $min;
        }
        if (preg_match('/(\d+):(\d+)\s*(AM|PM)?/i', $row['close_time'] ?? '', $m)) {
            $h = (int)$m[1];
            $min = (int)$m[2];
            $ampm = strtoupper($m[3] ?? '');
            if ($ampm === 'PM' && $h < 12) $h += 12;
            if ($ampm === 'AM' && $h === 12) $h = 0;
            $closeMin = $h * 60 + $min;
        }

        $isOpen = false;
        if (($row['status'] ?? 'Active') === 'Active') {
            if ($openMin < $closeMin) {
                $isOpen = ($nowMin >= $openMin && $nowMin < $closeMin);
            } else {
                $isOpen = ($nowMin >= $openMin || $nowMin < $closeMin);
            }
        }

        $marketList[] = [
            'id'             => (int)$row['id'],
            'market_name'    => $row['market'],
            'open_time'      => $row['open_time'],
            'close_time'     => $row['close_time'],
            'is_open'        => $isOpen,
            'current_result' => $resData ? $resData['result'] : '*** - ** - ***',
            'declared_at'    => $resData ? $resData['declared_at'] : null
        ];
    }
}

echo json_encode([
    'success'     => true,
    'app'         => SUBH_APP_NAME,
    'server_time' => date('Y-m-d H:i:s'),
    'total'       => count($marketList),
    'markets'     => $marketList
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
