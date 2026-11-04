<?php
/**
 * ==============================================================================
 * SUBH GAMING - CENTRAL REST API GATEWAY (v1)
 * ==============================================================================
 * Provides clean, secure, JSON-based endpoints for mobile apps, frontend clients,
 * and admin automation.
 * ==============================================================================
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-App-Key');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/app_brand.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$endpoint = $_GET['endpoint'] ?? '';

function apiResponse(bool $success, string $message, $data = null, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode([
        'success'   => $success,
        'app'       => SUBH_APP_NAME,
        'version'   => '2.0.0',
        'message'   => $message,
        'data'      => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone'  => date_default_timezone_get()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    $db = db();
} catch (Throwable $e) {
    apiResponse(false, 'Database connection error: ' . $e->getMessage(), null, 500);
}

// Router
switch ($action ?: $endpoint) {
    case 'config':
    case 'app_settings':
        $res = $db->query("SELECT setting_key, setting_value FROM app_settings");
        $settings = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        $payRes = $db->query("SELECT * FROM admin_payment_details LIMIT 1");
        $payDetails = $payRes ? $payRes->fetch_assoc() : [];

        $bannerRes = $db->query("SELECT banner FROM banner_text LIMIT 1");
        $banner = $bannerRes ? ($bannerRes->fetch_assoc()['banner'] ?? '') : '';

        apiResponse(true, 'App configuration loaded', [
            'app_name'         => SUBH_APP_NAME,
            'app_slug'         => SUBH_APP_SLUG,
            'tagline'          => SUBH_APP_TAGLINE,
            'banner_text'      => $banner,
            'whatsapp_1'       => $payDetails['whatsapp_no_1'] ?? '',
            'whatsapp_2'       => $payDetails['whatsapp_no_2'] ?? '',
            'whatsapp_deposit' => $payDetails['whatsapp_deposit'] ?? ($payDetails['whatsapp_no_1'] ?? ''),
            'telegram_link'    => $payDetails['telegram_link'] ?? '',
            'telegram_channel' => $payDetails['telegram_channel'] ?? '',
            'active_gateway'   => $payDetails['active_gateway'] ?? 'manual_upi',
            'upi_id'           => $payDetails['upi_id'] ?? '',
            'qr_code_url'      => !empty($payDetails['qr_code_image']) ? 'uploads/qr/' . $payDetails['qr_code_image'] : '',
            'min_deposit'      => (float)($payDetails['min_deposit'] ?? 100),
            'min_withdraw'     => (float)($payDetails['min_withdraw'] ?? 500),
            'min_bid'          => (float)($payDetails['min_bid'] ?? 10),
            'settings'         => $settings
        ]);
        break;

    case 'markets':
    case 'get_markets':
        $today = date('Y-m-d');
        $resArr = [];
        $stmtRes = $db->prepare("SELECT market, result_str FROM market_result WHERE result_date = ?");
        if ($stmtRes) {
            $stmtRes->bind_param('s', $today);
            $stmtRes->execute();
            $stmtRes->bind_result($mkt, $rStr);
            while ($stmtRes->fetch()) {
                $resArr[strtoupper($mkt)] = $rStr;
            }
            $stmtRes->close();
        }

        $resMarkets = $db->query("SELECT id, market, open_time, close_time, status FROM markets ORDER BY id ASC");
        $marketsList = [];
        if ($resMarkets) {
            while ($m = $resMarkets->fetch_assoc()) {
                $mktKey = strtoupper($m['market']);
                $m['current_result'] = $resArr[$mktKey] ?? '*** - ** - ***';
                $marketsList[] = $m;
            }
        }
        apiResponse(true, 'Markets retrieved successfully', [
            'total'   => count($marketsList),
            'markets' => $marketsList
        ]);
        break;

    case 'game_rates':
        $resRates = $db->query("SELECT game_type, play_amount, win_amount FROM game_rates ORDER BY id ASC");
        $rates = [];
        if ($resRates) {
            while ($r = $resRates->fetch_assoc()) {
                $rates[] = $r;
            }
        }
        apiResponse(true, 'Game rates retrieved', $rates);
        break;

    case 'results':
    case 'today_results':
        $date = $_GET['date'] ?? date('Y-m-d');
        $stmt = $db->prepare("SELECT market, result_str, declared_at FROM market_result WHERE result_date = ? ORDER BY declared_at DESC");
        $results = [];
        if ($stmt) {
            $stmt->bind_param('s', $date);
            $stmt->execute();
            $stmt->bind_result($mkt, $rStr, $declAt);
            while ($stmt->fetch()) {
                $results[] = [
                    'market'      => $mkt,
                    'result'      => $rStr,
                    'declared_at' => $declAt
                ];
            }
            $stmt->close();
        }
        apiResponse(true, 'Market results for ' . $date, $results);
        break;

    case 'user_balance':
        $uid = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
        $userCode = trim((string)($_GET['code'] ?? $_POST['code'] ?? ''));
        if ($uid <= 0 && empty($userCode)) {
            apiResponse(false, 'Missing user_id or user code', null, 400);
        }

        if ($uid > 0) {
            $resU = $db->query("SELECT id, user_id, name, wallet_balance FROM users WHERE id = {$uid} LIMIT 1");
        } else {
            $escCode = $db->real_escape_string($userCode);
            $resU = $db->query("SELECT id, user_id, name, wallet_balance FROM users WHERE user_id = '{$escCode}' LIMIT 1");
        }

        if ($resU && $rowU = $resU->fetch_assoc()) {
            $balance = (float)$rowU['wallet_balance'];
            apiResponse(true, 'Balance fetched', [
                'id'             => (int)$rowU['id'],
                'user_code'      => $rowU['user_id'],
                'name'           => $rowU['name'],
                'wallet_balance' => $balance
            ]);
        } else {
            apiResponse(false, 'User not found', null, 404);
        }
        break;

    case 'status':
    default:
        apiResponse(true, 'Subh Gaming API Gateway Active', [
            'service'   => 'Subh Gaming Core API',
            'version'   => '2.0.0',
            'status'    => 'ONLINE',
            'endpoints' => [
                'config'      => '?action=config',
                'markets'     => '?action=markets',
                'game_rates'  => '?action=game_rates',
                'results'     => '?action=results&date=YYYY-MM-DD',
                'user_balance'=> '?action=user_balance&user_id=1'
            ]
        ]);
        break;
}
