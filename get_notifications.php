<?php
require_once 'includes/db.php';
/* Filename: get_notifications.php
   Purpose: Fetch Win/Lose notifications with strict Session/Result validation
*/

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// 1. DB Connection
$db = db();
if ($db->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed']);
    exit;
}
$db->set_charset('utf8mb4');
date_default_timezone_set('Asia/Kolkata');

require_once 'includes/fcm_helper.php';

// 2. Validate User ID
$user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
if (empty($user_id)) {
    echo json_encode(['status' => 'error', 'message' => 'User ID required']);
    exit;
}

$userVariants = get_all_uid_variants($user_id);
if (empty($userVariants)) { $userVariants = [$user_id]; }
$placeholders = implode(',', array_fill(0, count($userVariants), '?'));

// 3. Fetch Un-notified Wins/Losses joined with Market Result
$sql = "
SELECT 
    w.id,
    w.market,
    w.session_type,
    w.bet_type,
    w.amount,
    w.wining_balance,
    w.status,
    w.win_date,
    mr.open_digit,
    mr.close_digit,
    mr.result_str
FROM wining w
LEFT JOIN market_result mr 
    ON w.market = mr.market 
    AND w.win_date = mr.result_date
WHERE w.user_id IN ({$placeholders}) 
  AND w.notified = 0
ORDER BY w.id DESC
";

$stmt = $db->prepare($sql);
$types = str_repeat('s', count($userVariants));
$stmt->bind_param($types, ...$userVariants);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
$ids_to_update = [];

while ($row = $result->fetch_assoc()) {
    
    // Data Extraction
    $betType = strtolower($row['bet_type'] ?? '');
    $session = strtolower($row['session_type'] ?? ''); // 'open' or 'close'
    $status  = ucfirst(strtolower($row['status'])); // 'Win' or 'Lose'
    
    // Result Check
    // Note: Result table me kabhi kabhi '*' hota hai, usse empty maane
    $resOpen  = str_replace('*', '', trim((string)$row['open_digit']));
    $resClose = str_replace('*', '', trim((string)$row['close_digit']));
    
    $isOpenDeclared  = ($resOpen !== '');
    $isCloseDeclared = ($resClose !== '');
    
    $shouldShow = false;

    // ---------------------------------------------------------
    // RULE 1: Jodi, Half Sangam, Full Sangam
    // "Close session par hi show ho (Jab pura result aa jaye)"
    // ---------------------------------------------------------
    if (in_array($betType, ['jodi_digit', 'half_sangam', 'full_sangam'])) {
        if ($isOpenDeclared && $isCloseDeclared) {
            $shouldShow = true;
        }
    }
    
    // ---------------------------------------------------------
    // RULE 2: Single Digit, Pana (Single, Double, Triple)
    // "Open ka result aane par Open ka, Close par Close ka"
    // ---------------------------------------------------------
    else {
        // Agar Bet Open Session ki hai -> Check Open Result
        if ($session === 'open') {
            if ($isOpenDeclared) {
                $shouldShow = true;
            }
        } 
        // Agar Bet Close Session ki hai -> Check Close Result
        else if ($session === 'close') {
            if ($isCloseDeclared) {
                $shouldShow = true;
            }
        }
    }

    // ---------------------------------------------------------
    // Prepare Notification
    // ---------------------------------------------------------
    if ($shouldShow) {
        
        $title = "";
        $message = "";
        
        if ($status === 'Win') {
            $title = "Congratulations! You Won";
            $message = "Market: {$row['market']} ({$row['session_type']})\n" .
                       "Game: " . ucfirst(str_replace('_', ' ', $betType)) . "\n" .
                       "Amount Won: " . number_format($row['wining_balance']);
        } else {
            // Lose
            $title = "Better Luck Next Time";
            $message = "Market: {$row['market']} ({$row['session_type']})\n" .
                       "Game: " . ucfirst(str_replace('_', ' ', $betType)) . "\n" .
                       "Bet Amount: " . number_format($row['amount']);
        }

        $notifications[] = [
            'id'      => $row['id'],
            'title'   => $title,
            'message' => $message,
            'status'  => $status, // 'Win' or 'Lose'
            'amount'  => ($status === 'Win') ? $row['wining_balance'] : 0,
            'market'  => $row['market'],
            'date'    => $row['win_date']
        ];
        
        // Is ID ko update list me daalein
        $ids_to_update[] = $row['id'];
    }
}

// -------------------------------------------------------------
// 4. Update Status (Mark as notified)
// -------------------------------------------------------------
if (!empty($ids_to_update)) {
    $ids_str = implode(',', array_map('intval', $ids_to_update));
    $updateSql = "UPDATE wining SET notified = 1, notified_at = NOW() WHERE id IN ($ids_str)";
    $db->query($updateSql);
}

// 5. Return JSON
echo json_encode([
    'status' => 'success',
    'count'  => count($notifications),
    'data'   => $notifications
]);

$db->close();
