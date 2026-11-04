<?php
require_once 'includes/db.php';
// fetch_user_winings_api.php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kolkata');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$user_id = $_GET['user_id'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

if (!$user_id) {
    echo json_encode([]);
    exit;
}

$db = db();

$dateWinClause = "";
$params = [$user_id];
$types = 's';
if ($from && $to) {
    $fromTs = $from . ' 00:00:00';
    $toTs = $to . ' 23:59:59';
    $dateWinClause = " AND win_time BETWEEN ? AND ? ";
    $params[] = $fromTs;
    $params[] = $toTs;
    $types .= 'ss';
}

$sql = "
    SELECT id, user_id, market, session_type, bet_type,
           single_digit, jodi_digit, single_pana, double_pana, triple_pana,
           open_digit, close_digit, open_pana_x, close_pana_x,
           left_digit, right_digit, jodi,
           amount, wining_balance, status, win_time, win_date
    FROM wining
    WHERE user_id=? {$dateWinClause}
";

$sql .= " ORDER BY win_time DESC, id DESC";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
    $betType = strtolower((string) ($r['bet_type'] ?? ''));

    // Fallback logic for bet_type if empty
    if ($betType === '' || $betType === '0' || $betType === 'single_digit') {
        if (!empty($r['jodi']))
            $betType = 'jodi digit';
        elseif (!empty($r['left_digit']))
            $betType = 'left digit';
        elseif (!empty($r['right_digit']))
            $betType = 'right digit';
        elseif (!empty($r['jodi_digit']))
            $betType = 'jodi_digit';
        elseif (!empty($r['single_pana']))
            $betType = 'single_pana';
        elseif (!empty($r['double_pana']))
            $betType = 'double_pana';
        elseif (!empty($r['triple_pana']))
            $betType = 'triple_pana';
        else
            $betType = 'single_digit';
    }

    // Calculate value based on betType
    $value = '';
    if ($betType === 'full_sangam') {
        $opx = trim((string) ($r['open_pana_x'] ?? ''));
        $cpx = trim((string) ($r['close_pana_x'] ?? ''));
        $value = trim($opx . '-' . $cpx, '-');
    } elseif ($betType === 'half_sangam') {
        $d = trim((string) ($r['single_digit'] ?? ''));
        $opx = trim((string) ($r['open_pana_x'] ?? ''));
        $cpx = trim((string) ($r['close_pana_x'] ?? ''));
        $p = ($opx !== '') ? $opx : $cpx;
        $value = trim($d . '-' . $p, '-');
    } else {
        if ($betType === 'single_digit')
            $value = (string) ($r['single_digit'] ?? '');
        elseif ($betType === 'jodi_digit')
            $value = (string) ($r['jodi_digit'] ?? '');
        elseif ($betType === 'single_pana')
            $value = (string) ($r['single_pana'] ?? '');
        elseif ($betType === 'double_pana')
            $value = (string) ($r['double_pana'] ?? '');
        elseif ($betType === 'triple_pana')
            $value = (string) ($r['triple_pana'] ?? '');
        elseif ($betType === 'left digit')
            $value = (string) ($r['left_digit'] ?? '');
        elseif ($betType === 'right digit')
            $value = (string) ($r['right_digit'] ?? '');
        elseif ($betType === 'jodi digit')
            $value = (string) ($r['jodi'] ?? '');
    }

    $rows[] = [
        'id' => $r['id'],
        'market' => $r['market'],
        'session_type' => $r['session_type'],
        'session' => $r['session_type'],
        'bet_type' => $betType,
        'value' => $value,
        'amount' => (float) $r['amount'],
        'wining_balance' => (float) $r['wining_balance'],
        'status' => $r['status'],
        'win_time' => $r['win_time'],
        'win_date' => $r['win_date'] ?? date('Y-m-d', strtotime($r['win_time'])),
    ];
}
$stmt->close();
$db->close();

echo json_encode($rows);
