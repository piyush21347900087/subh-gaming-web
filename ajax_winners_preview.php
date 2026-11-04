<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = db();
$market = strtoupper(trim($_POST['market'] ?? ''));
$result_date = trim($_POST['result_date'] ?? '');
$open_pana = trim($_POST['open_pana'] ?? '');
$open_digit = trim($_POST['open_digit'] ?? '');
$close_digit = trim($_POST['close_digit'] ?? '');
$close_pana = trim($_POST['close_pana'] ?? '');

if ($market === '' || $result_date === '') {
    echo json_encode(['error' => 'Market and Date are required']);
    exit;
}

function digitFromPana($p) {
    $p = trim((string)$p);
    if (!preg_match('/^\d{3}$/', $p)) return '';
    $sum = ((int)$p[0]) + ((int)$p[1]) + ((int)$p[2]);
    return (string)($sum % 10);
}

// Normalize result components
$openP = ($open_pana === '***') ? '' : $open_pana;
$openD = ($open_digit === '*') ? '' : $open_digit;
if ($openD === '' && $openP !== '') $openD = digitFromPana($openP);

$closeD = ($close_digit === '*') ? '' : $close_digit;
$closeP = ($close_pana === '***') ? '' : $close_pana;
if ($closeD === '' && $closeP !== '') $closeD = digitFromPana($closeP);

$jodiR = '';
if ($openD !== '' && $closeD !== '') {
    $jodiR = sprintf('%02d', (int)($openD . $closeD));
}

// Fetch market payouts (Check markets table, then gali_markets table)
$marketData = null;
$mSt = $db->prepare("SELECT * FROM markets WHERE UPPER(TRIM(market_name)) = ? LIMIT 1");
$mSt->bind_param('s', $market);
$mSt->execute();
$marketData = $mSt->get_result()->fetch_assoc();
$mSt->close();

if (!$marketData) {
    // Check gali_markets table
    $gSt = $db->prepare("SELECT * FROM gali_markets WHERE UPPER(TRIM(market_name)) = ? LIMIT 1");
    $gSt->bind_param('s', $market);
    $gSt->execute();
    $galiMkt = $gSt->get_result()->fetch_assoc();
    $gSt->close();

    if ($galiMkt) {
        $marketData = [
            'payout_single_digit' => $galiMkt['payout_single_digit'] ?? 95,
            'payout_jodi_digit'   => $galiMkt['payout_jodi_digit'] ?? 950,
            'payout_single_pana'  => 1500,
            'payout_double_pana'  => 3000,
            'payout_triple_pana'  => 7000,
            'payout_half_sangam'  => 10000,
            'payout_full_sangam'  => 100000
        ];
    } else {
        // Fallback default payouts
        $marketData = [
            'payout_single_digit' => 95,
            'payout_jodi_digit'   => 950,
            'payout_single_pana'  => 1500,
            'payout_double_pana'  => 3000,
            'payout_triple_pana'  => 7000,
            'payout_half_sangam'  => 10000,
            'payout_full_sangam'  => 100000
        ];
    }
}

$winners = [];
$debugBids = [];
$fromTS = $result_date . ' 00:00:00';
$toTS = $result_date . ' 23:59:59';

// 1. Fetch Bids from standard `bid` table
$sql = "
    SELECT b.*, u.name as full_name, COALESCE(u.user_id, b.user_id) as user_code
    FROM bid b
    LEFT JOIN users u ON (u.user_id COLLATE utf8mb4_general_ci = b.user_id COLLATE utf8mb4_general_ci OR CAST(u.id AS CHAR) = b.user_id)
    WHERE (b.market COLLATE utf8mb4_general_ci = ? COLLATE utf8mb4_general_ci OR UPPER(TRIM(b.market)) = UPPER(TRIM(?)))
      AND b.bid_time BETWEEN ? AND ?
      AND COALESCE(b.is_cancelled, 0) = 0
";
$st = $db->prepare($sql);
$st->bind_param('ssss', $market, $market, $fromTS, $toTS);
$st->execute();
$res = $st->get_result();

while ($b = $res->fetch_assoc()) {
    $gameType = strtoupper(trim($b['game_type'] ?? ''));
    $gameType = str_replace(' ', '_', $gameType);
    if ($gameType === '') {
        if (isset($b['single_digit']) && $b['single_digit'] !== null && $b['single_digit'] !== '' && (string)$b['single_digit'] !== '—') {
            $gameType = 'SINGLE_DIGIT';
        } elseif (isset($b['jodi_digit']) && $b['jodi_digit'] !== null && $b['jodi_digit'] !== '' && (string)$b['jodi_digit'] !== '—') {
            $gameType = 'JODI';
        } elseif (isset($b['single_pana']) && $b['single_pana'] !== null && $b['single_pana'] !== '' && (string)$b['single_pana'] !== '—') {
            $gameType = 'SINGLE_PANA';
        } elseif (isset($b['double_pana']) && $b['double_pana'] !== null && $b['double_pana'] !== '' && (string)$b['double_pana'] !== '—') {
            $gameType = 'DOUBLE_PANA';
        } elseif (isset($b['triple_pana']) && $b['triple_pana'] !== null && $b['triple_pana'] !== '' && (string)$b['triple_pana'] !== '—') {
            $gameType = 'TRIPLE_PANA';
        } elseif (isset($b['open_digit']) && $b['open_digit'] !== null && $b['open_digit'] !== '' && (string)$b['open_digit'] !== '—') {
            $gameType = 'SINGLE_DIGIT';
        } elseif (isset($b['close_digit']) && $b['close_digit'] !== null && $b['close_digit'] !== '' && (string)$b['close_digit'] !== '—') {
            $gameType = 'SINGLE_DIGIT';
        }
    }
    $sess = strtolower(trim($b['session_type'] ?? 'open'));
    $amt = (float)$b['amount'];
    $units = $amt / 10.0;
    $winAmt = 0;
    $isWin = false;
    $bidVal = "";

    switch ($gameType) {
        case 'SINGLE_DIGIT':
            $val = trim($b['single_digit'] ?? '');
            if ($val === '') {
                $val = trim(($b['open_digit'] ?: $b['close_digit']) ?? '');
            }
            $bidVal = $val;
            if ($sess === 'open' && $openD !== '' && $val === $openD) {
                $isWin = true; $winAmt = $units * (float)$marketData['payout_single_digit'];
            } elseif ($sess === 'close' && $closeD !== '' && $val === $closeD) {
                $isWin = true; $winAmt = $units * (float)$marketData['payout_single_digit'];
            }
            break;

        case 'JODI_DIGIT':
        case 'JODI':
        case 'JODIDIGIT':
        case 'JODI_BULK':
        case 'JODI_DIGIT_BULK':
            $val = trim($b['jodi_digit'] ?? '');
            if ($val === '') {
                $val = trim(($b['single_digit'] ?: ($b['open_digit'] . $b['close_digit'])) ?? '');
            }
            $bidVal = (preg_match('/^\d{1,2}$/', $val)) ? sprintf('%02d', (int)$val) : $val;
            if ($jodiR !== '' && $bidVal !== '' && $bidVal === $jodiR) {
                $isWin = true; 
                $winAmt = $units * (float)$marketData['payout_jodi_digit'];
            }
            break;

        case 'SINGLE_PANA':
        case 'SP_MOTOR':
        case 'SPMOTOR':
            $val = trim($b['single_pana'] ?? '');
            if ($val === '') {
                $val = trim(($b['open_pana'] ?: $b['close_pana']) ?? '');
            }
            $bidVal = $val;
            if ($sess === 'open' && $openP !== '' && $val === $openP) {
                $isWin = true; $winAmt = $units * (float)$marketData['payout_single_pana'];
            } elseif ($sess === 'close' && $closeP !== '' && $val === $closeP) {
                $isWin = true; $winAmt = $units * (float)$marketData['payout_single_pana'];
            }
            break;

        case 'DOUBLE_PANA':
        case 'DP_MOTOR':
        case 'DPMOTOR':
            $val = trim($b['double_pana'] ?? '');
            if ($val === '') {
                $val = trim(($b['open_pana'] ?: $b['close_pana']) ?? '');
            }
            $bidVal = $val;
            if ($sess === 'open' && $openP !== '' && $val === $openP) {
                $isWin = true; $winAmt = $units * (float)$marketData['payout_double_pana'];
            } elseif ($sess === 'close' && $closeP !== '' && $val === $closeP) {
                $isWin = true; $winAmt = $units * (float)$marketData['payout_double_pana'];
            }
            break;

        case 'TRIPLE_PANA':
            $val = trim($b['triple_pana'] ?? '');
            if ($val === '') {
                $val = trim(($b['open_pana'] ?: $b['close_pana']) ?? '');
            }
            $bidVal = $val;
            if ($sess === 'open' && $openP !== '' && $val === $openP) {
                $isWin = true; $winAmt = $units * (float)$marketData['payout_triple_pana'];
            } elseif ($sess === 'close' && $closeP !== '' && $val === $closeP) {
                $isWin = true; $winAmt = $units * (float)$marketData['payout_triple_pana'];
            }
            break;

        case 'HALF_SANGAM':
            $bidOD = trim($b['open_digit'] ?? '');
            $bidCD = trim($b['close_digit'] ?? '');
            $bidOP = trim($b['open_pana'] ?? '');
            $bidCP = trim($b['close_pana'] ?? '');
            
            if ($bidOD !== '' && $bidCP !== '') { 
                $bidVal = $bidOD . '-' . $bidCP;
                if ($openD !== '' && $closeP !== '' && $bidOD === $openD && $bidCP === $closeP) {
                    $isWin = true; $winAmt = $units * (float)$marketData['payout_half_sangam'];
                }
            } elseif ($bidOP !== '' && $bidCD !== '') { 
                $bidVal = $bidOP . '-' . $bidCD;
                if ($openP !== '' && $closeD !== '' && $bidOP === $openP && $bidCD === $closeD) {
                    $isWin = true; $winAmt = $units * (float)$marketData['payout_half_sangam'];
                }
            }
            break;

        case 'FULL_SANGAM':
            $bidOP = trim($b['open_pana'] ?? '');
            $bidCP = trim($b['close_pana'] ?? '');
            $bidVal = $bidOP . '-' . $bidCP;
            if ($openP !== '' && $closeP !== '' && $bidOP === $openP && $bidCP === $closeP) {
                $isWin = true; $winAmt = $units * (float)$marketData['payout_full_sangam'];
            }
            break;
    }

    $debugBids[] = [
        'id' => $b['id'],
        'table' => 'bid',
        'game_type' => $gameType,
        'val_checked' => $bidVal,
        'is_win' => $isWin
    ];

    if ($isWin) {
        $winners[] = [
            'bid_id' => $b['id'],
            'user_code' => $b['user_code'],
            'full_name' => $b['full_name'] ?? 'Unknown',
            'game_type' => $gameType,
            'session' => $sess,
            'bid_number' => $bidVal,
            'amount' => $amt,
            'win_amount' => round($winAmt, 2)
        ];
    }
}
$st->close();

// 2. Fetch Bids from `gali_bids` if table exists
$chkG = $db->query("SHOW TABLES LIKE 'gali_bids'");
if ($chkG && $chkG->num_rows > 0) {
    $sqlG = "
        SELECT gb.*, u.name as full_name, COALESCE(u.user_id, gb.user_id) as user_code
        FROM gali_bids gb
        LEFT JOIN users u ON (u.user_id COLLATE utf8mb4_general_ci = gb.user_id COLLATE utf8mb4_general_ci OR CAST(u.id AS CHAR) = gb.user_id)
        WHERE UPPER(TRIM(gb.market_name)) = UPPER(TRIM(?))
          AND (gb.created_at BETWEEN ? AND ? OR gb.date = ?)
    ";
    $stG = $db->prepare($sqlG);
    $stG->bind_param('ssss', $market, $fromTS, $toTS, $result_date);
    $stG->execute();
    $resG = $stG->get_result();

    while ($gb = $resG->fetch_assoc()) {
        $val = trim($gb['digit'] ?? '');
        $bidVal = (preg_match('/^\d{1,2}$/', $val)) ? sprintf('%02d', (int)$val) : $val;
        $amt = (float)$gb['amount'];
        $units = $amt / 10.0;
        $isWin = false;
        $winAmt = 0;

        if ($jodiR !== '' && $bidVal !== '' && $bidVal === $jodiR) {
            $isWin = true;
            $winAmt = $units * (float)$marketData['payout_jodi_digit'];
        }

        $debugBids[] = [
            'id' => $gb['id'],
            'table' => 'gali_bids',
            'game_type' => $gb['game_type'] ?? 'JODI_DIGIT',
            'val_checked' => $bidVal,
            'is_win' => $isWin
        ];

        if ($isWin) {
            $winners[] = [
                'bid_id' => $gb['id'],
                'user_code' => $gb['user_code'],
                'full_name' => $gb['full_name'] ?? 'Unknown',
                'game_type' => $gb['game_type'] ?? 'JODI DIGIT',
                'session' => 'open/close',
                'bid_number' => $bidVal,
                'amount' => $amt,
                'win_amount' => round($winAmt, 2)
            ];
        }
    }
    $stG->close();
}

echo json_encode([
    'success' => true, 
    'winners' => $winners,
    'debug' => [
        'bids_found' => count($debugBids),
        'all_bids' => $debugBids,
        'params' => [
            'market' => $market,
            'result_date' => $result_date,
            'open_pana' => $openP,
            'open_digit' => $openD,
            'close_digit' => $closeD,
            'close_pana' => $closeP,
            'jodiR' => $jodiR
        ]
    ]
]);
