<?php
@file_put_contents(__DIR__ . '/api_token_debug.log', date('[Y-m-d H:i:s] ') . "GLOBAL_HIT: " . ($_SERVER['REQUEST_METHOD'] ?? 'GET') . PHP_EOL, FILE_APPEND);
require_once 'includes/db.php';
// bio_data.php
@session_start();
date_default_timezone_set('Asia/Kolkata');

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF);

try {
    $db = db();
    $db->query("SET time_zone = '+05:30'");

    $hasBonusCol = false;
    $resB = $db->query("SHOW COLUMNS FROM deposit LIKE 'bonus_amount'");
    if ($resB && $resB->num_rows > 0) {
        $hasBonusCol = true;
    }
    $depSumExpr = $hasBonusCol ? "amount + bonus_amount" : "amount";

// ✅ Centralized FCM Helper
require_once 'includes/fcm_helper.php';


/* ===================== HELPERS ===================== */
// ✅ AUTO-FIX SCHEMA: Ensure method/status columns are VARCHAR to valid 'admin' inserts
try {
    $db->query("ALTER TABLE withdrawal MODIFY COLUMN method VARCHAR(50) DEFAULT 'Bank'");
    $db->query("ALTER TABLE withdrawal MODIFY COLUMN status VARCHAR(50) DEFAULT 'Pending'");
    $db->query("ALTER TABLE deposit MODIFY COLUMN method VARCHAR(50) DEFAULT 'UPI'");
    $db->query("ALTER TABLE deposit MODIFY COLUMN status VARCHAR(50) DEFAULT 'Pending'");

    // Check if wallet_balance exists
    $chkCol = $db->query("SHOW COLUMNS FROM users LIKE 'wallet_balance'");
    if ($chkCol->num_rows == 0) {
        // Restore Column
        $db->query("ALTER TABLE users ADD COLUMN wallet_balance DOUBLE(10,2) DEFAULT 0.00");
    }

    // Sync Balance for Current User (Self-Healing)
    if (isset($_GET['id'])) {
        $syncId = (int) $_GET['id'];
        $chkBidRef = $db->query("SHOW TABLES LIKE 'bid_refunds'");
        $hasBidRef = ($chkBidRef && $chkBidRef->num_rows > 0);
        $chkGali = $db->query("SHOW TABLES LIKE 'gali_bids'");
        $hasGali = ($chkGali && $chkGali->num_rows > 0);
        $chkWin3 = $db->query("SHOW TABLES LIKE 'wining3'");
        $hasWin3 = ($chkWin3 && $chkWin3->num_rows > 0);
        $chkWin4 = $db->query("SHOW TABLES LIKE 'wining4'");
        $hasWin4 = ($chkWin4 && $chkWin4->num_rows > 0);
        $chkBid3 = $db->query("SHOW TABLES LIKE 'bid3'");
        $hasBid3 = ($chkBid3 && $chkBid3->num_rows > 0);
        $chkBid4 = $db->query("SHOW TABLES LIKE 'bid4'");
        $hasBid4 = ($chkBid4 && $chkBid4->num_rows > 0);

        $sqlSync = "
            UPDATE users u
            SET wallet_balance = GREATEST(0.00, (
                COALESCE((SELECT SUM({$depSumExpr}) FROM deposit WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND LOWER(status)='done' AND (method IS NULL OR UPPER(method) <> 'REFUND')), 0)
                - COALESCE((SELECT SUM(amount) FROM withdrawal WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND LOWER(status) IN ('pending','approved','done') AND (method IS NULL OR (method NOT LIKE '%bid%' AND LOWER(method) <> 'game_bid'))), 0)
                + COALESCE((SELECT SUM(wining_balance) FROM wining WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci)), 0)
                " . ($hasWin3 ? "+ COALESCE((SELECT SUM(wining_balance) FROM wining3 WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND LOWER(status)='win'), 0)" : "") . "
                " . ($hasWin4 ? "+ COALESCE((SELECT SUM(wining_balance) FROM wining4 WHERE (user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND LOWER(status)='win'), 0)" : "") . "
                - COALESCE((SELECT SUM(amount) FROM bid b WHERE (b.user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR b.user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) " . ($hasBidRef ? "AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = b.id AND br.source_table IN ('bid','bids'))" : "") . "), 0)
                " . ($hasGali ? "- COALESCE((SELECT SUM(amount) FROM gali_bids g WHERE (g.user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR g.user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) " . ($hasBidRef ? "AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = g.id AND br.source_table IN ('gali_bid','gali_bids'))" : "") . "), 0)" : "") . "
                " . ($hasBid3 ? "- COALESCE((SELECT SUM(amount) FROM bid3 b WHERE (b.user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR b.user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND COALESCE(b.is_cancelled, 0) = 0), 0)" : "") . "
                " . ($hasBid4 ? "- COALESCE((SELECT SUM(amount) FROM bid4 b WHERE (b.user_id COLLATE utf8mb4_general_ci = u.user_id COLLATE utf8mb4_general_ci OR b.user_id COLLATE utf8mb4_general_ci = CAST(u.id AS CHAR) COLLATE utf8mb4_general_ci) AND COALESCE(b.is_cancelled, 0) = 0), 0)" : "") . "
            ))
            WHERE id = $syncId
        ";
        $db->query($sqlSync);
    }
} catch (Exception $e) {
}

function hasColumn(mysqli $db, string $table, string $col): bool
{
    $table = $db->real_escape_string($table);
    $col = $db->real_escape_string($col);
    $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
    return ($res && $res->num_rows > 0);
}

function formatMoney($n)
{
    return number_format((float) $n, 2, '.', '');
}

/**
 * ✅ SAFE NOTIFICATION (DB insert)
 * - Tries common tables (notifications / notify / user_notifications)
 * - If table/columns not found -> silently ignore (no fatal)
 */






function getShortBidType(string $type): string {
    $t = strtolower(trim($type));
    if ($t === 'single digit') return 'SD';
    if ($t === 'jodi digit' || $t === 'jodi') return 'JD';
    if ($t === 'single pana' || $t === 'single panna') return 'SP';
    if ($t === 'double pana' || $t === 'double panna') return 'DP';
    if ($t === 'triple pana' || $t === 'triple panna') return 'TP';
    if ($t === 'half sangam') return 'HS';
    if ($t === 'full sangam') return 'FS';
    if ($t === 'single digit bulk' || $t === 'single digit (bulk)') return 'SDB';
    if ($t === 'jodi digit bulk' || $t === 'jodi digit (bulk)') return 'JDB';
    if ($t === 'single pana bulk' || $t === 'single pana (bulk)') return 'SPB';
    if ($t === 'double pana bulk' || $t === 'double pana (bulk)') return 'DPB';
    if ($t === 'triple pana bulk' || $t === 'triple pana (bulk)') return 'TPB';
    return $type;
}

/* Bet formatter */
function buildBetDisplayRow(array $row): array
{
    $out = [
        'bid_type' => '-',
        'session_label' => '',
        'bet_html' => '-'
    ];

    $session = strtolower(trim((string) ($row['session_type'] ?? '')));
    $sessionLabel = $session ? ucfirst($session) : '-';

    $get = function ($k) use ($row) {
        return trim((string) ($row[$k] ?? ''));
    };
    
    $has = function($k) use ($get) {
        $v = $get($k);
        return $v !== '' && $v !== '-';
    };

    /* ---------- 0) Gali/Desawar Specific (digit/market_name) ---------- */
    $gt = strtoupper(trim((string)($row['game_type'] ?? $row['bet_type'] ?? '')));
    $digit = $get('digit');
    
    // If it's a jackpot source bid
    if (($row['src'] ?? '') === 'jackpot') {
        $out['session_label'] = !empty($row['close_time']) ? $row['close_time'] : 'Jackpot';
        if ($has('jodi_digit')) {
            $out['bid_type'] = 'Jodi Digit';
            $out['bet_html'] = htmlspecialchars($get('jodi_digit'), ENT_QUOTES, 'UTF-8');
            return $out;
        }
        if ($has('single_digit')) {
            $out['bid_type'] = 'Single Digit';
            $out['bet_html'] = htmlspecialchars($get('single_digit'), ENT_QUOTES, 'UTF-8');
            return $out;
        }
    }

    // If it's a starline source bid
    if (($row['src'] ?? '') === 'starline') {
        $out['session_label'] = !empty($row['close_time']) ? $row['close_time'] : 'Starline';
        if ($has('single_digit')) {
            $out['bid_type'] = 'Single Digit';
            $out['bet_html'] = htmlspecialchars($get('single_digit'), ENT_QUOTES, 'UTF-8');
            return $out;
        }
        if ($has('single_pana')) {
            $out['bid_type'] = 'Single Pana';
            $out['bet_html'] = htmlspecialchars($get('single_pana'), ENT_QUOTES, 'UTF-8');
            return $out;
        }
        if ($has('double_pana')) {
            $out['bid_type'] = 'Double Pana';
            $out['bet_html'] = htmlspecialchars($get('double_pana'), ENT_QUOTES, 'UTF-8');
            return $out;
        }
        if ($has('triple_pana')) {
            $out['bid_type'] = 'Triple Pana';
            $out['bet_html'] = htmlspecialchars($get('triple_pana'), ENT_QUOTES, 'UTF-8');
            return $out;
        }
    }

    /* ---------- 0) EXPLICIT SANGAM (Two-Column Format) ---------- */
    // [Sangam logic follows...]
    
    // Full Sangam
    if ($gt === 'FULL_SANGAM' || ($has('open_pana') && $has('close_pana'))) {
        $out['bid_type'] = 'Full Sangam';
        $out['session_label'] = 'Open'; // Full Sangam is usually one session
        $out['bet_html'] =
            '<div style="line-height:1.35;">'
            . '<div><span class="bet-lbl" data-short="OP:">Open Pana:</span> ' . htmlspecialchars($get('open_pana'), ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div><span class="bet-lbl" data-short="CP:">Close Pana:</span> ' . htmlspecialchars($get('close_pana'), ENT_QUOTES, 'UTF-8') . '</div>'
            . '</div>';
        return $out;
    }
    
    // Half Sangam
    if ($gt === 'HALF_SANGAM' || ($has('open_digit') && $has('close_pana')) || ($has('close_digit') && $has('open_pana'))) {
        $out['bid_type'] = 'Half Sangam';
        $out['session_label'] = $sessionLabel;
        $html = '<div style="line-height:1.35;">';
        if ($has('open_digit')) $html .= '<div><span class="bet-lbl" data-short="OD:">Open Digit:</span> ' . htmlspecialchars($get('open_digit'), ENT_QUOTES, 'UTF-8') . '</div>';
        if ($has('close_digit')) $html .= '<div><span class="bet-lbl" data-short="CD:">Close Digit:</span> ' . htmlspecialchars($get('close_digit'), ENT_QUOTES, 'UTF-8') . '</div>';
        if ($has('open_pana')) $html .= '<div><span class="bet-lbl" data-short="OP:">Open Pana:</span> ' . htmlspecialchars($get('open_pana'), ENT_QUOTES, 'UTF-8') . '</div>';
        if ($has('close_pana')) $html .= '<div><span class="bet-lbl" data-short="CP:">Close Pana:</span> ' . htmlspecialchars($get('close_pana'), ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '</div>';
        $out['bet_html'] = $html;
        return $out;
    }

    /* ---------- 1) JODI DIGIT ---------- */
    $jodi = $get('jodi_digit');
    if ($jodi !== '' && $jodi !== '-') {
        // [Existing Jodi Logic]
        $digits = preg_replace('/\D+/', '', $jodi);
        $openD = '-';
        $closeD = '-';

        if (strlen($digits) >= 2) {
            $openD = $digits[0];
            $closeD = $digits[1];
        } elseif (strlen($digits) === 1) {
            $openD = $digits[0];
            $closeD = '-';
        }

        $out['bid_type'] = 'Jodi Digit';
        $out['session_label'] = 'Open+Close';
        $out['bet_html'] =
            '<div style="line-height:1.35;">'
            . '<div><span class="bet-lbl" data-short="OD:">Open Digit:</span> ' . htmlspecialchars($openD, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div><span class="bet-lbl" data-short="CD:">Close Digit:</span> ' . htmlspecialchars($closeD, ENT_QUOTES, 'UTF-8') . '</div>'
            . '</div>';
        return $out;
    }

    /* ---------- 2) SINGLE-COLUMN FULL SANGAM (PPP-PPP) ---------- */
    $fullCandidates = ['full_sangam', 'single_pana', 'double_pana', 'triple_pana'];
    foreach ($fullCandidates as $f) {
        $v = $get($f);
        if ($v === '' || $v === '-')
            continue;

        $norm = preg_replace('/\s+/', '', $v);
        if (preg_match('/^(\d{3})[\-\/:](\d{3})$/', $norm, $m)) {
            $out['bid_type'] = 'Full Sangam';
            $out['session_label'] = $sessionLabel;
            $out['bet_html'] =
                '<div style="line-height:1.35;">'
                . '<div><span class="bet-lbl" data-short="OP:">Open Pana:</span> ' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div><span class="bet-lbl" data-short="CP:">Close Pana:</span> ' . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8') . '</div>'
                . '</div>';
            return $out;
        }
    }

    /* ---------- 3) SINGLE-COLUMN HALF SANGAM (D-PPP or PPP-D) ---------- */
    $halfCandidates = ['half_sangam', 'single_pana', 'double_pana', 'triple_pana', 'single_digit'];
    foreach ($halfCandidates as $f) {
        $v = $get($f);
        if ($v === '' || $v === '-')
            continue;

        $norm = preg_replace('/\s+/', '', $v);

        if (preg_match('/^(\d)[\-\/:](\d{3})$/', $norm, $m)) {
            $out['bid_type'] = 'Half Sangam';
            $out['session_label'] = $sessionLabel;
            $out['bet_html'] =
                '<div style="line-height:1.35;">'
                . '<div><span class="bet-lbl" data-short="OD:">Open Digit:</span> ' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div><span class="bet-lbl" data-short="CP:">Close Pana:</span> ' . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8') . '</div>'
                . '</div>';
            return $out;
        }

        if (preg_match('/^(\d{3})[\-\/:](\d)$/', $norm, $m)) {
            $out['bid_type'] = 'Half Sangam';
            $out['session_label'] = $sessionLabel;
            $out['bet_html'] =
                '<div style="line-height:1.35;">'
                . '<div><span class="bet-lbl" data-short="OP:">Open Pana:</span> ' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div><span class="bet-lbl" data-short="CD:">Close Digit:</span> ' . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8') . '</div>'
                . '</div>';
            return $out;
        }
    }

    /* ---------- 4) SINGLE DIGIT ---------- */
    $sd = $get('single_digit');
    if ($sd !== '' && $sd !== '-') {
        $out['bid_type'] = 'Single Digit';
        $out['session_label'] = $sessionLabel;
        $out['bet_html'] = htmlspecialchars($sd, ENT_QUOTES, 'UTF-8');
        return $out;
    }

    /* ---------- 5) SINGLE PANA ---------- */
    $sp = $get('single_pana');
    if ($sp !== '' && $sp !== '-') {
        $out['bid_type'] = 'Single Pana';
        $out['session_label'] = $sessionLabel;
        $out['bet_html'] = htmlspecialchars($sp, ENT_QUOTES, 'UTF-8');
        return $out;
    }

    /* ---------- 6) DOUBLE PANA ---------- */
    $dp = $get('double_pana');
    if ($dp !== '' && $dp !== '-') {
        $out['bid_type'] = 'Double Pana';
        $out['session_label'] = $sessionLabel;
        $out['bet_html'] = htmlspecialchars($dp, ENT_QUOTES, 'UTF-8');
        return $out;
    }

    /* ---------- 7) TRIPLE PANA ---------- */
    $tp = $get('triple_pana');
    if ($tp !== '' && $tp !== '-') {
        $out['bid_type'] = 'Triple Pana';
        $out['session_label'] = $sessionLabel;
        $out['bet_html'] = htmlspecialchars($tp, ENT_QUOTES, 'UTF-8');
        return $out;
    }

    /* ---------- 8) Fallback Digit (Gali/Wining/Other) ---------- */
    if ($digit !== '' && $digit !== '-') {
        $out['bid_type'] = $gt ?: 'Bet';
        $out['session_label'] = $sessionLabel;
        $out['bet_html'] = htmlspecialchars($digit, ENT_QUOTES, 'UTF-8');
        return $out;
    }

    // New Fallback for Wining rows that might have different keys
    $v = $get('value') ?: $get('bet_digit') ?: $get('bet_value');
    if ($v !== '' && $v !== '-') {
        $out['bid_type'] = $gt ?: 'Bet';
        $out['session_label'] = $sessionLabel;
        $out['bet_html'] = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        return $out;
    }

    $out['bid_type'] = '-';
    $out['session_label'] = $sessionLabel;
    $out['bet_html'] = '-';
    return $out;
}

/* ========== DELETE BID (AJAX) ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bid_ajax'])) {
    header('Content-Type: application/json');
    $bidId = (int)($_POST['bid_id'] ?? 0);
    $src = $_POST['src'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $bidTime = $_POST['bid_time'] ?? '';
    
    if ($bidId <= 0 || !$src || $userId <= 0) {
        echo json_encode(['ok' => 0, 'msg' => 'Invalid parameters']);
        exit;
    }
    
    try {
        $q = $db->prepare("SELECT user_id FROM users WHERE id=?");
        $q->bind_param('i', $userId);
        $q->execute();
        $row = $q->get_result()->fetch_assoc();
        $rawUserId = $row['user_id'] ?? '';
        $q->close();
        
        if ($src === 'main') {
            $db->query("DELETE FROM bid WHERE id = $bidId");
        } elseif ($src === 'gali') {
            $db->query("DELETE FROM gali_bids WHERE id = $bidId");
        } elseif ($src === 'karnataka') {
            $db->query("DELETE FROM bid3 WHERE id = $bidId");
        } elseif ($src === 'starline') {
            $db->query("DELETE FROM bid4 WHERE id = $bidId");
        }
        
        if ($rawUserId && $amount > 0 && $bidTime) {
            $wdDel = $db->prepare("DELETE FROM withdrawal WHERE method='game_bid' AND amount=? AND (user_id=? OR user_id=?) AND date_time=? LIMIT 1");
            $uidStr = (string)$userId;
            $wdDel->bind_param('dsss', $amount, $rawUserId, $uidStr, $bidTime);
            $wdDel->execute();
            $wdDel->close();
        }
        
        echo json_encode(['ok' => 1, 'msg' => 'Bid deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['ok' => 0, 'msg' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}
/* ========== PROFILE EDIT (AJAX) ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_edit_ajax'])) {
    header('Content-Type: application/json');

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $mob = trim($_POST['mobile_no'] ?? '');
    $mail = trim($_POST['email'] ?? '');
    $pwd = trim($_POST['password'] ?? '');

    if ($id <= 0 || !$name || !preg_match('/^\d{10}$/', $mob) || !$pwd) {
        echo json_encode(['ok' => 0, 'msg' => 'Please fill valid data']);
        exit;
    }

    $chk = $db->prepare("SELECT id FROM users WHERE mobile_no=? AND id<>?");
    $chk->bind_param('si', $mob, $id);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows) {
        echo json_encode(['ok' => 0, 'msg' => 'Mobile already in use']);
        exit;
    }

    $uUpd = $db->prepare("UPDATE users SET name=?, mobile_no=?, email=?, password=? WHERE id=?");
    $uUpd->bind_param('ssssi', $name, $mob, $mail, $pwd, $id);
    $ok = $uUpd->execute();

    echo json_encode(['ok' => $ok ? 1 : 0]);
    exit;
}

/* ========== ACTIVE / DEACTIVE TOGGLE (AJAX) ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active_ajax'])) {
    header('Content-Type: application/json');

    $id = (int) ($_POST['id'] ?? 0);
    $newState = ($_POST['new_state'] ?? '1') === '1' ? 1 : 0;

    if ($id <= 0) {
        echo json_encode(['ok' => 0, 'msg' => 'Invalid user']);
        exit;
    }

    try {
        $q = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $q->bind_param('ii', $newState, $id);
        $ok = $q->execute();
        echo json_encode(['ok' => $ok ? 1 : 0, 'state' => $newState]);
    } catch (Exception $e) {
        echo json_encode(['ok' => 0, 'msg' => 'Update error']);
    }
    exit;
}



/* ========== DATE RANGE FILTER ========== */
$range = $_GET['range'] ?? 'all';
$customFrom = trim($_GET['from'] ?? '');
$customTo = trim($_GET['to'] ?? '');

$today = date('Y-m-d');
$fromDate = null;
$toDate = null;

if ($range === 'today') {
    $fromDate = $today;
    $toDate = $today;
} elseif ($range === '7') {
    $fromDate = date('Y-m-d', strtotime('-6 days'));
    $toDate = $today;
} elseif ($range === '15') {
    $fromDate = date('Y-m-d', strtotime('-14 days'));
    $toDate = $today;
} elseif ($range === '30') {
    $fromDate = date('Y-m-d', strtotime('-29 days'));
    $toDate = $today;
} elseif ($range === 'custom') {
    if (
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $customFrom) &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $customTo)
    ) {
        $fromDate = $customFrom;
        $toDate = $customTo;
    } else {
        $range = 'all';
    }
}

$depDateCond = '';
$withDateCond = '';
$bidDateCond = '';
$winDateCond = '';

if ($fromDate && $toDate) {
    $fromEsc = $db->real_escape_string($fromDate);
    $toEsc = $db->real_escape_string($toDate);

    $depDateCond = " AND DATE(date_time) BETWEEN '{$fromEsc}' AND '{$toEsc}'";
    $withDateCond = " AND DATE(date_time) BETWEEN '{$fromEsc}' AND '{$toEsc}'";
    $bidDateCond = " AND DATE(bid_time)  BETWEEN '{$fromEsc}' AND '{$toEsc}'";
    $winDateCond = " AND DATE(win_time)  BETWEEN '{$fromEsc}' AND '{$toEsc}'";
}

/* ===================== USER & FILTER INITIALIZATION ===================== */
$idParam = trim((string)($_GET['id'] ?? $_GET['user_id'] ?? $_POST['id'] ?? ''));

$u = null;
$id = 0;
$rawUserId = '';

if ($idParam !== '') {
    if (is_numeric($idParam)) {
        $stmtU = $db->prepare("SELECT * FROM users WHERE id = ? OR user_id = ? LIMIT 1");
        $stmtU->bind_param('is', $idParam, $idParam);
    } else {
        $stmtU = $db->prepare("SELECT * FROM users WHERE user_id = ? OR id = ? LIMIT 1");
        $stmtU->bind_param('ss', $idParam, $idParam);
    }
    $stmtU->execute();
    $resU = $stmtU->get_result();
    if ($resU && $resU->num_rows > 0) {
        $u = $resU->fetch_assoc();
    }
    $stmtU->close();
}

if (!$u) {
    die('User not found or ID invalid: ' . htmlspecialchars($idParam));
}

$id = (int)($u['id'] ?? 0);
$rawUserId = trim((string)($u['user_id'] ?? ''));
$isActiveRaw = isset($u['is_active']) ? (int) $u['is_active'] : 1;
$isActiveRaw = $isActiveRaw ? 1 : 0;

$actionMsg = '';

/* ✅ AUTO-FIX SCHEMA FOR FCM IP & FETCH DEVICE ID & IP ADDRESS */
try {
    $db->query("ALTER TABLE user_fcm_tokens ADD COLUMN ip_address VARCHAR(64) DEFAULT NULL");
} catch (Throwable $e) {}
$userDeviceId = '-';
$userFcmId = 0;
$userCity = '-';
$userState = '-';
$userIpAddress = '-';
try {
    $stmtFcm = $db->prepare("SELECT id, device_id, ip_address, city, state FROM user_fcm_tokens WHERE user_id = ? OR user_id = ? ORDER BY id DESC LIMIT 1");
    $idStr = (string)$id;
    $stmtFcm->bind_param('ss', $rawUserId, $idStr);
    $stmtFcm->execute();
    $resFcm = $stmtFcm->get_result();
    if ($resFcm && $rowFcm = $resFcm->fetch_assoc()) {
        if (!empty($rowFcm['device_id'])) $userDeviceId = $rowFcm['device_id'];
        if (!empty($rowFcm['ip_address'])) $userIpAddress = $rowFcm['ip_address'];
        $userFcmId = $rowFcm['id'] ?? 0;
        $userCity = $rowFcm['city'] ?? '-';
        $userState = $rowFcm['state'] ?? '-';
    }
    $stmtFcm->close();
} catch (Exception $e) {}

// Fallback IP search from users, ad_tracking, or page_views if empty
try {
    if ($userIpAddress === '-') {
        if (hasColumn($db, 'users', 'ip_address') && !empty($u['ip_address'])) {
            $userIpAddress = $u['ip_address'];
        } elseif (hasColumn($db, 'users', 'ip') && !empty($u['ip'])) {
            $userIpAddress = $u['ip'];
        } else {
            $chkAd = $db->query("SHOW TABLES LIKE 'ad_tracking'");
            if ($chkAd && $chkAd->num_rows > 0) {
                $stmtIp = $db->prepare("SELECT ip_address FROM ad_tracking WHERE r_value = ? OR r_value = ? ORDER BY id DESC LIMIT 1");
                $stmtIp->bind_param('ss', $rawUserId, $idStr);
                $stmtIp->execute();
                $resIp = $stmtIp->get_result();
                if ($resIp && $rowIp = $resIp->fetch_assoc()) {
                    if (!empty($rowIp['ip_address'])) $userIpAddress = $rowIp['ip_address'];
                }
            }
        }
    }
} catch (Exception $e) {}

function get_ip_city(string $ip): string {
    $ip = trim($ip);
    if ($ip === '' || $ip === '-' || $ip === '127.0.0.1' || $ip === '::1') {
        return '-';
    }
    if (isset($_SESSION['ip_city_cache'][$ip])) {
        return $_SESSION['ip_city_cache'][$ip];
    }
    $city = '-';
    try {
        $url = "http://ip-api.com/json/" . urlencode($ip) . "?fields=status,city,regionName,country";
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
            if ($raw) {
                $json = json_decode($raw, true);
                if (!empty($json['status']) && $json['status'] === 'success' && !empty($json['city'])) {
                    $city = $json['city'] . (!empty($json['regionName']) ? ', ' . $json['regionName'] : '');
                }
            }
        }
    } catch (Throwable $e) {}
    $_SESSION['ip_city_cache'][$ip] = $city;
    return $city;
}

/* ✅ FETCH MIN LIMITS from admin_payment_details (Always) */
$minDep = 1.0;
$minWith = 1.0;
try {
    $stmtLimits = $db->query("SELECT min_deposit, min_withdraw FROM admin_payment_details WHERE id=1 LIMIT 1");
    if ($stmtLimits && $stmtLimits->num_rows > 0) {
        $rowLimits = $stmtLimits->fetch_assoc();
        $minDep = (float) $rowLimits['min_deposit'];
        $minWith = (float) $rowLimits['min_withdraw'];
    }
} catch (Exception $e) {
}

if ($minDep <= 0)
    $minDep = 1.0;
if ($minWith <= 0)
    $minWith = 1.0;

// Override for Admin
$minDep = 1.0;
$minWith = 1.0;

/* ========== QUICK WALLET ADJUST (FORM POST) ========== */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['wallet_action'], $_POST['wallet_amount'])
) {
    // START LOG
    @file_put_contents(__DIR__ . '/api_token_debug.log', date('[Y-m-d H:i:s] ') . "POST Wallet Action: " . $_POST['wallet_action'] . " | Amt: " . $_POST['wallet_amount'] . PHP_EOL, FILE_APPEND);
    try {
        $walletAction = $_POST['wallet_action']; // deposit / withdrawal
        $walletAmt = (float) ($_POST['wallet_amount'] ?? 0);

        // manual admin entries ke liye fixed method
        $methodDbDeposit = 'ADMIN'; // deposit table ENUM
        $methodDbWithdraw = 'admin'; // withdrawal table ENUM

        if (!in_array($walletAction, ['deposit', 'withdrawal'], true)) {
            $actionMsg = 'Invalid action.';
        } elseif ($walletAmt <= 0) {
            $actionMsg = 'Enter valid amount.';
        } elseif ($walletAction === 'deposit' && $walletAmt < $minDep) {
            $actionMsg = 'Minimum deposit is ₹' . number_format($minDep, 2);
        } elseif ($walletAction === 'withdrawal' && $walletAmt < $minWith) {
            $actionMsg = 'Minimum withdrawal is ₹' . number_format($minWith, 2);
        } else {
            $db->begin_transaction();

            // Lock and Fetch current Balance
            $lock = $db->prepare("SELECT wallet_balance FROM users WHERE id=? FOR UPDATE");
            $lock->bind_param('i', $id);
            $lock->execute();
            $curRow = $lock->get_result()->fetch_assoc();
            $lock->close();

            if (!$curRow) throw new Exception("User not found");
            $currentBal = (float)$curRow['wallet_balance'];

            // Sync Calculation
            $chkBidRef = $db->query("SHOW TABLES LIKE 'bid_refunds'")->num_rows > 0;
            $hasGali = $db->query("SHOW TABLES LIKE 'gali_bids'")->num_rows > 0;
            $hasWin3 = $db->query("SHOW TABLES LIKE 'wining3'")->num_rows > 0;
            $hasWin4 = $db->query("SHOW TABLES LIKE 'wining4'")->num_rows > 0;
            $hasBid3 = $db->query("SHOW TABLES LIKE 'bid3'")->num_rows > 0;
            $hasBid4 = $db->query("SHOW TABLES LIKE 'bid4'")->num_rows > 0;

            $sqlF = "SELECT GREATEST(0.00,
                (COALESCE((SELECT SUM({$depSumExpr}) FROM deposit WHERE (user_id='{$rawUserId}' OR user_id='{$id}') AND LOWER(status)='done' AND (method IS NULL OR UPPER(method) <> 'REFUND')),0)
                - COALESCE((SELECT SUM(amount) FROM withdrawal WHERE (user_id='{$rawUserId}' OR user_id='{$id}') AND LOWER(status) IN ('pending','approved','done') AND (method IS NULL OR (method NOT LIKE '%bid%' AND LOWER(method) <> 'game_bid'))),0)
                + COALESCE((SELECT SUM(wining_balance) FROM wining WHERE (user_id='{$rawUserId}' OR user_id='{$id}')),0)
                " . ($hasWin3 ? "+ COALESCE((SELECT SUM(wining_balance) FROM wining3 WHERE (user_id='{$rawUserId}' OR user_id='{$id}') AND LOWER(status)='win'), 0)" : "") . "
                " . ($hasWin4 ? "+ COALESCE((SELECT SUM(wining_balance) FROM wining4 WHERE (user_id='{$rawUserId}' OR user_id='{$id}') AND LOWER(status)='win'), 0)" : "") . "
                - COALESCE((SELECT SUM(amount) FROM bid b WHERE (b.user_id='{$rawUserId}' OR b.user_id='{$id}') " . ($chkBidRef ? "AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = b.id AND br.source_table IN ('bid','bids'))" : "") . "), 0)
                " . ($hasGali ? "- COALESCE((SELECT SUM(amount) FROM gali_bids g WHERE (g.user_id='{$rawUserId}' OR g.user_id='{$id}') " . ($chkBidRef ? "AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = g.id AND br.source_table IN ('gali_bid','gali_bids'))" : "") . "), 0)" : "") . "
                " . ($hasBid3 ? "- COALESCE((SELECT SUM(amount) FROM bid3 b WHERE (b.user_id='{$rawUserId}' OR b.user_id='{$id}') AND COALESCE(b.is_cancelled, 0) = 0), 0)" : "") . "
                " . ($hasBid4 ? "- COALESCE((SELECT SUM(amount) FROM bid4 b WHERE (b.user_id='{$rawUserId}' OR b.user_id='{$id}') AND COALESCE(b.is_cancelled, 0) = 0), 0)" : "") . "
            ))";
            $resF = $db->query($sqlF);
            if ($resF && $rowF = $resF->fetch_row()) {
                $currentBal = (float)$rowF[0];
            }

            if ($walletAction === 'deposit') {
                $hasOrder = hasColumn($db, 'deposit', 'order_id');
                $hasTxn = hasColumn($db, 'deposit', 'txn_id');

                $sqlIns = "INSERT INTO deposit (user_id, amount, method, status";
                if ($hasOrder) $sqlIns .= ", order_id";
                elseif ($hasTxn) $sqlIns .= ", txn_id";
                $sqlIns .= ", date_time) VALUES (?, ?, ?, 'Done'";
                if ($hasOrder || $hasTxn) $sqlIns .= ", ?";
                $sqlIns .= ", '" . date('Y-m-d H:i:s') . "')";

                $stmtAdd = $db->prepare($sqlIns);
                $oId = 'AD' . time() . mt_rand(100, 999);
                if ($hasOrder || $hasTxn) {
                    $stmtAdd->bind_param('sdss', $rawUserId, $walletAmt, $methodDbDeposit, $oId);
                } else {
                    $stmtAdd->bind_param('sds', $rawUserId, $walletAmt, $methodDbDeposit);
                }
                $stmtAdd->execute();
                $stmtAdd->close();

                $newBal = $currentBal + $walletAmt;
                $t = "Deposit Successful";
                $m = "Aapka deposit ₹" . number_format($walletAmt, 2) . " approved ho gaya.";
                pushUserNotification($db, $rawUserId, $t, $m, 'deposit');
                pushWalletFcm($db, $rawUserId, 'deposit', $walletAmt, 'done', $t, $m);
            } else {
                if ($currentBal < $walletAmt) throw new Exception("Insufficient balance (Limit: ₹" . number_format($currentBal, 2) . ")");

                $hasOrderW = hasColumn($db, 'withdrawal', 'order_id');
                $hasTxnW = hasColumn($db, 'withdrawal', 'txn_id');
                $sqlInsW = "INSERT INTO withdrawal (user_id, amount, method, status";
                if ($hasOrderW) $sqlInsW .= ", order_id";
                elseif ($hasTxnW) $sqlInsW .= ", txn_id";
                $sqlInsW .= ", date_time) VALUES (?, ?, ?, 'Done'";
                if ($hasOrderW || $hasTxnW) $sqlInsW .= ", ?";
                $sqlInsW .= ", '" . date('Y-m-d H:i:s') . "')";

                $stmtAdd = $db->prepare($sqlInsW);
                $oIdW = 'AW' . time() . mt_rand(100, 999);
                if ($hasOrderW || $hasTxnW) {
                    $stmtAdd->bind_param('sdss', $rawUserId, $walletAmt, $methodDbWithdraw, $oIdW);
                } else {
                    $stmtAdd->bind_param('sds', $rawUserId, $walletAmt, $methodDbWithdraw);
                }
                $stmtAdd->execute();
                $stmtAdd->close();

                $newBal = $currentBal - $walletAmt;
                $t = "Withdrawal Approved";
                $m = "Aapka withdrawal ₹" . number_format($walletAmt, 2) . " approved ho gaya.";
                pushUserNotification($db, $rawUserId, $t, $m, 'withdrawal');
                pushWalletFcm($db, $rawUserId, 'withdrawal', $walletAmt, 'done', $t, $m);
            }

            $upd = $db->prepare("UPDATE users SET wallet_balance=? WHERE id=?");
            $upd->bind_param('di', $newBal, $id);
            $upd->execute();
            $upd->close();

            $db->commit();
            $actionMsg = 'Wallet updated successfully.';
        }
    } catch (Exception $e) {
        $db->rollback();
        $actionMsg = 'Error: ' . $e->getMessage();
        @file_put_contents(__DIR__ . '/php_error.log', "[" . date('Y-m-d H:i:s') . "] Wallet error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    } catch (Throwable $t) {
        $actionMsg = 'Fatal error: ' . $t->getMessage() . ' in ' . $t->getFile() . ' on line ' . $t->getLine();
        @file_put_contents(__DIR__ . '/php_error.log', "[" . date('Y-m-d H:i:s') . "] FATAL: " . $t->getMessage() . PHP_EOL, FILE_APPEND);
    }

    $redir = "bio_data.php?id=" . $id;
    if ($range) $redir .= "&range=" . urlencode($range);
    if ($fromDate && $toDate) $redir .= "&from=" . $fromDate . "&to=" . $toDate;
    $redir .= "&msg=" . urlencode($actionMsg);
    header("Location: " . $redir);
    exit;
}

// Optional: show msg from redirect
if (!$actionMsg && isset($_GET['msg'])) {
    $actionMsg = trim((string) $_GET['msg']);
}

/* ========== BANK KYC UPDATE (FORM POST) ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bank_kyc_save'])) {
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $ifsc = trim($_POST['ifsc'] ?? '');
    $branch = trim($_POST['branch'] ?? '');
    $bank_status = trim($_POST['bank_status'] ?? 'Incomplete');

    try {
        $check = $db->prepare("SELECT id FROM bank_kyc WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $check->bind_param('s', $rawUserId);
        $check->execute();
        $resC = $check->get_result();

        if ($resC->num_rows) {
            $rowC = $resC->fetch_assoc();
            $bkId = (int) $rowC['id'];

            $upd = $db->prepare("
                UPDATE bank_kyc
                SET bank_name=?, account_name=?, account_number=?, ifsc=?, branch=?, status=?, date_time='" . date('Y-m-d H:i:s') . "'
                WHERE id=?
            ");
            $upd->bind_param('ssssssi', $bank_name, $account_name, $account_number, $ifsc, $branch, $bank_status, $bkId);
            $okBk = $upd->execute();
        } else {
            $ins = $db->prepare("
                INSERT INTO bank_kyc (user_id, bank_name, account_name, account_number, ifsc, branch, status, date_time)
                VALUES (?,?,?,?,?,?,?,'" . date('Y-m-d H:i:s') . "')
            ");
            $ins->bind_param('sssssss', $rawUserId, $bank_name, $account_name, $account_number, $ifsc, $branch, $bank_status);
            $okBk = $ins->execute();
        }

        $actionMsg = !empty($okBk) ? 'Bank KYC updated.' : 'Bank KYC update failed.';
    } catch (Exception $e) {
        $actionMsg = 'Bank KYC error.';
    }

    $redir = "bio_data.php?id=" . $id;
    if ($range)
        $redir .= "&range=" . urlencode($range);
    if ($fromDate && $toDate) {
        $redir .= "&from=" . $fromDate . "&to=" . $toDate;
    }
    header("Location: " . $redir);
    exit;
}

/* ========== UPI KYC UPDATE (FORM POST) + QR UPLOAD ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upi_kyc_save'])) {
    $upi_id = trim($_POST['upi_id'] ?? '');
    $upi_status = trim($_POST['upi_status'] ?? 'Incomplete');
    $current_qr = trim($_POST['current_qr'] ?? '');
    $qrPath = $current_qr;

    if (isset($_FILES['upi_qr']) && $_FILES['upi_qr']['error'] === UPLOAD_ERR_OK && $_FILES['upi_qr']['size'] > 0) {
        $uploadDir = __DIR__ . '/uploads/upi_qr/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $tmpName = $_FILES['upi_qr']['tmp_name'];
        $origName = $_FILES['upi_qr']['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed, true)) {
            $newName = 'qr_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $destAbs = $uploadDir . $newName;
            $destRel = 'uploads/upi_qr/' . $newName;

            if (@move_uploaded_file($tmpName, $destAbs)) {
                if ($current_qr && strpos($current_qr, 'uploads/upi_qr/') === 0) {
                    $oldPath = __DIR__ . '/' . $current_qr;
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $qrPath = $destRel;
            }
        }
    }

    try {
        $checkU = $db->prepare("SELECT id FROM upi_kyc WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $checkU->bind_param('s', $rawUserId);
        $checkU->execute();
        $resCU = $checkU->get_result();

        if ($resCU->num_rows) {
            $rowU = $resCU->fetch_assoc();
            $upId = (int) $rowU['id'];

            $updU = $db->prepare("
                UPDATE upi_kyc
                SET upi_id=?, status=?, qr_image=?, date_time='" . date('Y-m-d H:i:s') . "'
                WHERE id=?
            ");
            $updU->bind_param('sssi', $upi_id, $upi_status, $qrPath, $upId);
            $okUp = $updU->execute();
        } else {
            $insU = $db->prepare("
                INSERT INTO upi_kyc (user_id, upi_id, status, qr_image, date_time)
                VALUES (?,?,?,?,'" . date('Y-m-d H:i:s') . "')
            ");
            $insU->bind_param('ssss', $rawUserId, $upi_id, $upi_status, $qrPath);
            $okUp = $insU->execute();
        }

        $actionMsg = !empty($okUp) ? 'UPI KYC updated.' : 'UPI KYC update failed.';
    } catch (Exception $e) {
        $actionMsg = 'UPI KYC error.';
    }

    $redir = "bio_data.php?id=" . $id;
    if ($range)
        $redir .= "&range=" . urlencode($range);
    if ($fromDate && $toDate) {
        $redir .= "&from=" . $fromDate . "&to=" . $toDate;
    }
    header("Location: " . $redir);
    exit;
}

/* ========== CRYPTO (USDT) KYC UPDATE (FORM POST) + QR UPLOAD ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crypto_kyc_save'])) {
    $usdt_address = trim($_POST['usdt_address'] ?? '');
    $usdt_network = trim($_POST['usdt_network'] ?? '');
    $crypto_status = trim($_POST['crypto_status'] ?? 'Incomplete');
    $current_crypto_qr = trim($_POST['current_crypto_qr'] ?? '');
    $cryptoQrPath = $current_crypto_qr;

    if (isset($_FILES['crypto_qr']) && $_FILES['crypto_qr']['error'] === UPLOAD_ERR_OK && $_FILES['crypto_qr']['size'] > 0) {
        $uploadDir = __DIR__ . '/uploads/crypto_qr/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $tmpName = $_FILES['crypto_qr']['tmp_name'];
        $origName = $_FILES['crypto_qr']['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed, true)) {
            $newName = 'usdt_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $destAbs = $uploadDir . $newName;
            $destRel = 'uploads/crypto_qr/' . $newName;

            if (@move_uploaded_file($tmpName, $destAbs)) {
                if ($current_crypto_qr && strpos($current_crypto_qr, 'uploads/crypto_qr/') === 0) {
                    $oldPath = __DIR__ . '/' . $current_crypto_qr;
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $cryptoQrPath = $destRel;
            }
        }
    }

    try {
        $checkC = $db->prepare("SELECT id FROM crypto_kyc WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $checkC->bind_param('s', $rawUserId);
        $checkC->execute();
        $resCC = $checkC->get_result();

        if ($resCC->num_rows) {
            $rowC = $resCC->fetch_assoc();
            $ckId = (int) $rowC['id'];

            $updC = $db->prepare("
                UPDATE crypto_kyc
                SET address=?, network=?, status=?, qr_image=?, date_time='" . date('Y-m-d H:i:s') . "'
                WHERE id=?
            ");
            $updC->bind_param('ssssi', $usdt_address, $usdt_network, $crypto_status, $cryptoQrPath, $ckId);
            $okC = $updC->execute();
        } else {
            $insC = $db->prepare("
                INSERT INTO crypto_kyc (user_id, address, network, status, qr_image, date_time)
                VALUES (?,?,?,?,?,'" . date('Y-m-d H:i:s') . "')
            ");
            $insC->bind_param('sssss', $rawUserId, $usdt_address, $usdt_network, $crypto_status, $cryptoQrPath);
            $okC = $insC->execute();
        }

        $actionMsg = !empty($okC) ? 'USDT KYC updated.' : 'USDT KYC update failed.';
    } catch (Exception $e) {
        $actionMsg = 'USDT KYC error.';
    }

    $redir = "bio_data.php?id=" . $id;
    if ($range)
        $redir .= "&range=" . urlencode($range);
    if ($fromDate && $toDate) {
        $redir .= "&from=" . $fromDate . "&to=" . $toDate;
    }
    header("Location: " . $redir);
    exit;
}

/* ========== UPI MOBILE NUMBER UPDATE (FORM POST) ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upi_num_save'])) {
    $upi_type = trim($_POST['upi_type'] ?? '');
    $upi_number = trim($_POST['upi_number'] ?? '');

    try {
        if ($upi_type !== '') {
            $insUN = $db->prepare("
                INSERT INTO upi_numbers (user_id, upi_number, upi_type, date_time)
                VALUES (?,?,?,'" . date('Y-m-d H:i:s') . "')
            ");
            $insUN->bind_param('sss', $rawUserId, $upi_number, $upi_type);
            $okUN = $insUN->execute();
            $actionMsg = !empty($okUN) ? 'UPI mobile number updated.' : 'UPI mobile update failed.';
        } else {
            $actionMsg = 'UPI app type missing.';
        }
    } catch (Exception $e) {
        $actionMsg = 'UPI mobile error.';
    }

    $redir = "bio_data.php?id=" . $id;
    if ($range)
        $redir .= "&range=" . urlencode($range);
    if ($fromDate && $toDate) {
        $redir .= "&from=" . $fromDate . "&to=" . $toDate;
    }
    header("Location: " . $redir);
    exit;
}

/* ==== Display values ==== */
$userId = htmlspecialchars($rawUserId ?? '', ENT_QUOTES, 'UTF-8');
$userName = htmlspecialchars($u['name'] ?? '', ENT_QUOTES, 'UTF-8');
$userMail = htmlspecialchars($u['email'] ?? '-', ENT_QUOTES, 'UTF-8');
$userMob = htmlspecialchars($u['mobile_no'] ?? '-', ENT_QUOTES, 'UTF-8');
$userPwd = htmlspecialchars($u['password'] ?? '-', ENT_QUOTES, 'UTF-8');

$regDate = '-';
$accountAgeDays = 0;
$accountAgeStr = '';

if (!empty($u['created_at']) && $u['created_at'] !== '0000-00-00 00:00:00') {
    $regTimestamp = strtotime($u['created_at']);
    if ($regTimestamp) {
        $regDate = date('d-m-Y h:i A', $regTimestamp);
        
        $diffSecs = max(0, time() - $regTimestamp);
        $accountAgeDays = (int) floor($diffSecs / (60 * 60 * 24));
        
        if ($accountAgeDays === 0) {
            $accountAgeStr = '(Today)';
        } elseif ($accountAgeDays === 1) {
            $accountAgeStr = '(1 day old)';
        } else {
            $accountAgeStr = '(' . $accountAgeDays . ' days old)';
        }
    }
}

/* ---- Last seen Logic & Status Calculation ---- */
$lastSeenRaw = $u['last_seen'] ?? null;
try {
    $qLive = $db->prepare("SELECT last_seen FROM live WHERE user_id=? ORDER BY last_seen DESC LIMIT 1");
    $qLive->bind_param('s', $rawUserId);
    $qLive->execute();
    $rLive = $qLive->get_result();
    if ($rLive->num_rows) {
        $liveRow = $rLive->fetch_assoc();
        if (!empty($liveRow['last_seen']) && $liveRow['last_seen'] !== '0000-00-00 00:00:00') {
            $lastSeenRaw = $liveRow['last_seen'];
        }
    }
} catch (Exception $e) {
}

$lastSeenStr = '-';
$isLive = false;

if (!empty($lastSeenRaw) && $lastSeenRaw !== '0000-00-00 00:00:00') {
    $ts = strtotime($lastSeenRaw);
    if ($ts) {
        $lastSeenStr = date('d-m-Y h:i A', $ts);
        $diff = time() - $ts;
        if ($diff <= 30) {
            $isLive = true;
        }
    }
}

/* ================== Deposit history ================== */
$bonusColExpr = $hasBonusCol ? "bonus_amount" : "0 AS bonus_amount";
$sqlDep = "
    SELECT id, amount, {$bonusColExpr}, method, status,
           DATE_FORMAT(date_time,'%d-%m-%Y %h:%i %p') AS dt
    FROM deposit
    WHERE (user_id = ? OR user_id = ?) AND (method IS NULL OR (method != 'game_bid' AND UPPER(method) != 'REFUND'))
    {$depDateCond}
    ORDER BY id DESC
    LIMIT 500
";
$stmtDep = $db->prepare($sqlDep);
$stmtDep->bind_param('ss', $rawUserId, $id);
$stmtDep->execute();
$resDep = $stmtDep->get_result();

/* ================== Withdrawal history ================== */
$sqlW = "
    SELECT id, amount, method, status,
           DATE_FORMAT(date_time,'%d-%m-%Y %h:%i %p') AS dt
    FROM withdrawal
    WHERE (user_id = ? OR user_id = ?) AND (method IS NULL OR (method NOT LIKE '%bid%' AND LOWER(method) != 'game_bid'))
    {$withDateCond}
    ORDER BY id DESC
    LIMIT 500
";
$stmtW = $db->prepare($sqlW);
$stmtW->bind_param('ss', $rawUserId, $id);
$stmtW->execute();
$resW = $stmtW->get_result();

/* --- Withdrawal rows only with non-empty method --- */
$withdrawRows = [];
$withdrawSumDisplay = 0.0;
while ($row = $resW->fetch_assoc()) {
    $methodRaw = isset($row['method']) ? trim((string) $row['method']) : '';
    if ($methodRaw === '') {
        continue;
    }
    $withdrawRows[] = $row;
    $withdrawSumDisplay += (float) $row['amount'];
}

/* ================== Wining history (Merged) ================== */
$allWins = [];

// 1. Regular Main Winnings
try {
    $sqlWin = "
        SELECT *,
               DATE_FORMAT(win_time,'%d-%m-%Y %h:%i %p') AS dt
        FROM wining
        WHERE (user_id = ? OR user_id = ?)
        {$winDateCond}
        ORDER BY win_time DESC, id DESC
        LIMIT 500
    ";
    $stmtWin = $db->prepare($sqlWin);
    if ($stmtWin) {
        $stmtWin->bind_param('ss', $rawUserId, $id);
        $stmtWin->execute();
        $resWin1 = $stmtWin->get_result();
        while ($r = $resWin1->fetch_assoc()) {
            $r['src'] = 'main';
            $r['sort_ts'] = strtotime($r['win_time'] ?? '');
            $allWins[] = $r;
        }
        $stmtWin->close();
    }
} catch (Exception $e) {
}

// 2. Jackpot 3 Winnings
try {
    $chkWin3 = $db->query("SHOW TABLES LIKE 'wining3'");
    if ($chkWin3 && $chkWin3->num_rows > 0) {
        $sqlWin3 = "
            SELECT *,
                   DATE_FORMAT(win_time,'%d-%m-%Y %h:%i %p') AS dt
            FROM wining3
            WHERE (user_id = ? OR user_id = ?) AND LOWER(status)='win'
            {$winDateCond}
            ORDER BY win_time DESC, id DESC
            LIMIT 500
        ";
        $stmtWin3 = $db->prepare($sqlWin3);
        if ($stmtWin3) {
            $stmtWin3->bind_param('ss', $rawUserId, $id);
            $stmtWin3->execute();
            $resWin3 = $stmtWin3->get_result();
            while ($r = $resWin3->fetch_assoc()) {
                $r['src'] = 'jackpot';
                $r['sort_ts'] = strtotime($r['win_time'] ?? '');
                $allWins[] = $r;
            }
            $stmtWin3->close();
        }
    }
} catch (Exception $e) {
}

// 3. Starline 4 Winnings
try {
    $chkWin4 = $db->query("SHOW TABLES LIKE 'wining4'");
    if ($chkWin4 && $chkWin4->num_rows > 0) {
        $sqlWin4 = "
            SELECT *,
                   DATE_FORMAT(win_time,'%d-%m-%Y %h:%i %p') AS dt
            FROM wining4
            WHERE (user_id = ? OR user_id = ?) AND LOWER(status)='win'
            {$winDateCond}
            ORDER BY win_time DESC, id DESC
            LIMIT 500
        ";
        $stmtWin4 = $db->prepare($sqlWin4);
        if ($stmtWin4) {
            $stmtWin4->bind_param('ss', $rawUserId, $id);
            $stmtWin4->execute();
            $resWin4 = $stmtWin4->get_result();
            while ($r = $resWin4->fetch_assoc()) {
                $r['src'] = 'starline';
                $r['sort_ts'] = strtotime($r['win_time'] ?? '');
                $allWins[] = $r;
            }
            $stmtWin4->close();
        }
    }
} catch (Exception $e) {
}

// Sort all combined winnings by win_time DESC
usort($allWins, function ($a, $b) {
    if (($a['sort_ts'] ?? 0) == ($b['sort_ts'] ?? 0))
        return 0;
    return (($a['sort_ts'] ?? 0) < ($b['sort_ts'] ?? 0)) ? 1 : -1;
});
$countWin = count($allWins);

/* ================== Bid history (Merged) ================== */
$allBids = [];

// 1. Regular Bids
try {
    $sqlBid = "SELECT b.*, DATE_FORMAT(b.bid_time,'%d-%m-%Y %h:%i %p') AS dt 
               FROM bid b 
               WHERE (b.user_id = ? OR b.user_id = ?) 
               AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = b.id AND br.source_table IN ('bid','bids'))
               {$bidDateCond} 
               ORDER BY b.bid_time DESC, b.id DESC
               LIMIT 500";
    $stmtBid = $db->prepare($sqlBid);
    if ($stmtBid) {
        $stmtBid->bind_param('ss', $rawUserId, $id);
        $stmtBid->execute();
        $resBid1 = $stmtBid->get_result();
        while ($r = $resBid1->fetch_assoc()) {
            $r['src'] = 'main';
            $r['sort_ts'] = strtotime($r['bid_time'] ?? '');
            $allBids[] = $r;
        }
        $stmtBid->close();
    }
} catch (Exception $e) {
}

// 2. Gali Bids
try {
    $chkTable = $db->query("SHOW TABLES LIKE 'gali_bids'");
    if ($chkTable && $chkTable->num_rows > 0) {
        $galiCond = str_replace('bid_time', 'date', $bidDateCond);
        $sqlGali = "SELECT g.*, DATE_FORMAT(g.date,'%d-%m-%Y %h:%i %p') AS dt 
                    FROM gali_bids g 
                    WHERE (g.user_id = ? OR g.user_id = ?) 
                    AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = g.id AND br.source_table IN ('gali_bid','gali_bids'))
                    {$galiCond} 
                    ORDER BY g.date DESC, g.id DESC
                    LIMIT 500";
        $stmtG = $db->prepare($sqlGali);
        if ($stmtG) {
            $stmtG->bind_param('ss', $rawUserId, $id); 
            $stmtG->execute();
            $resG = $stmtG->get_result();
            while ($r = $resG->fetch_assoc()) {
                $r['market'] = $r['market_name'] ?? 'Gali';
                $r['src'] = 'gali';
                $r['sort_ts'] = strtotime($r['date'] ?? '');
                $allBids[] = $r;
            }
            $stmtG->close();
        }
    }
} catch (Exception $e) {
}

// 3. Jackpot 3 Bids
try {
    $chkBid3 = $db->query("SHOW TABLES LIKE 'bid3'");
    if ($chkBid3 && $chkBid3->num_rows > 0) {
        $sqlBid3 = "SELECT b.*, DATE_FORMAT(b.bid_time,'%d-%m-%Y %h:%i %p') AS dt 
                    FROM bid3 b 
                    WHERE (b.user_id = ? OR b.user_id = ?) 
                    AND COALESCE(b.is_cancelled, 0) = 0
                    {$bidDateCond} 
                    ORDER BY b.bid_time DESC, b.id DESC
                    LIMIT 500";
        $stmtBid3 = $db->prepare($sqlBid3);
        if ($stmtBid3) {
            $stmtBid3->bind_param('ss', $rawUserId, $id);
            $stmtBid3->execute();
            $resBid3 = $stmtBid3->get_result();
            while ($r = $resBid3->fetch_assoc()) {
                $r['src'] = 'jackpot';
                $r['session_type'] = !empty($r['close_time']) ? $r['close_time'] : 'Jackpot';
                $r['sort_ts'] = strtotime($r['bid_time'] ?? '');
                $allBids[] = $r;
            }
            $stmtBid3->close();
        }
    }
} catch (Exception $e) {
}

// 4. Starline 4 Bids
try {
    $chkBid4 = $db->query("SHOW TABLES LIKE 'bid4'");
    if ($chkBid4 && $chkBid4->num_rows > 0) {
        $sqlBid4 = "SELECT b.*, DATE_FORMAT(b.bid_time,'%d-%m-%Y %h:%i %p') AS dt 
                    FROM bid4 b 
                    WHERE (b.user_id = ? OR b.user_id = ?) 
                    AND COALESCE(b.is_cancelled, 0) = 0
                    {$bidDateCond} 
                    ORDER BY b.bid_time DESC, b.id DESC
                    LIMIT 500";
        $stmtBid4 = $db->prepare($sqlBid4);
        if ($stmtBid4) {
            $stmtBid4->bind_param('ss', $rawUserId, $id);
            $stmtBid4->execute();
            $resBid4 = $stmtBid4->get_result();
            while ($r = $resBid4->fetch_assoc()) {
                $r['src'] = 'starline';
                $r['session_type'] = !empty($r['close_time']) ? $r['close_time'] : 'Starline';
                $r['sort_ts'] = strtotime($r['bid_time'] ?? '');
                $allBids[] = $r;
            }
            $stmtBid4->close();
        }
    }
} catch (Exception $e) {
}

// Sort combined bids
usort($allBids, function ($a, $b) {
    if (($a['sort_ts'] ?? 0) == ($b['sort_ts'] ?? 0))
        return 0;
    return (($a['sort_ts'] ?? 0) < ($b['sort_ts'] ?? 0)) ? 1 : -1;
});

$countBids = count($allBids);

// Fetch Counts for pagination
$countDeposit = $resDep->num_rows;
$countWithdrawal = count($withdrawRows);

/* Pagination Params */
$allowedLimits = [10, 50, 100];
$reqLimit = (int)($_GET['limit'] ?? $_GET['l'] ?? 10);
$limit = in_array($reqLimit, $allowedLimits, true) ? $reqLimit : 10;

$pageDep  = max(1, (int)($_GET['pd'] ?? 1));
$pageWith = max(1, (int)($_GET['pw'] ?? 1));
$pageBid  = max(1, (int)($_GET['pb'] ?? 1));
$pageWin  = max(1, (int)($_GET['pv'] ?? 1));

function getPaginationInfo($total, $page, $limit) {
    if ($total <= 0) return ['start' => 0, 'end' => 0, 'total' => 0, 'pages' => 0];
    $pages = ceil($total / $limit);
    $page = max(1, min($page, $pages));
    $start = ($page - 1) * $limit;
    $end = min($start + $limit, $total);
    return [
        'start' => $start,
        'end' => $end,
        'display_start' => $start + 1,
        'total' => $total,
        'pages' => $pages,
        'current' => $page
    ];
}

$pgDep  = getPaginationInfo($countDeposit, $pageDep, $limit);
$pgWith = getPaginationInfo($countWithdrawal, $pageWith, $limit);
$pgBid  = getPaginationInfo($countBids, $pageBid, $limit);
$pgWin  = getPaginationInfo($countWin, $pageWin, $limit);

// Re-generate current URL for pagination links (excluding page params)
$baseUrl = "bio_data.php?id=" . $id;
if ($limit !== 10) $baseUrl .= "&limit=" . $limit;
if ($range) $baseUrl .= "&range=" . urlencode($range);
if ($fromDate && $toDate) $baseUrl .= "&from=" . urlencode($fromDate) . "&to=" . urlencode($toDate);

function pgLink($url, $params = []) {
    foreach ($_GET as $k => $v) {
        if (!in_array($k, ['pd', 'pw', 'pb', 'pv', 'id', 'range', 'from', 'to', 'limit'])) {
            $params[$k] = $v;
        }
    }
    $q = http_build_query($params);
    return $url . ($q ? '&' . $q : '');
}

/* ===== Totals ===== */
$sumDep = 0.0;
$sumWithAll = 0.0;
$sumWin = 0.0;
$sumWithPending = 0.0;

try {
    $sqlWithPending = "
        SELECT IFNULL(SUM(amount),0) AS s
        FROM withdrawal
        WHERE user_id = ?
          AND LOWER(status) = 'pending'
    ";
    $qWithPending = $db->prepare($sqlWithPending);
    if ($qWithPending) {
        $qWithPending->bind_param('s', $rawUserId);
        $qWithPending->execute();
        $rWithPending = $qWithPending->get_result()->fetch_assoc();
        $sumWithPending = (float) $rWithPending['s'];
        $qWithPending->close();
    }
} catch (Exception $e) {
}

try {
    $sqlDepSum = "
        SELECT IFNULL(SUM(amount),0) AS s
        FROM deposit
        WHERE user_id = ?
          AND LOWER(status) IN ('pending', 'approved', 'done')
          AND method <> 'REFUND'
        {$depDateCond}
    ";
    $qDepSum = $db->prepare($sqlDepSum);
    $qDepSum->bind_param('s', $rawUserId);
    $qDepSum->execute();
    $rDepSum = $qDepSum->get_result()->fetch_assoc();
    $sumDep = (float) $rDepSum['s'];
    $qDepSum->close();
} catch (Exception $e) {
}

$sumBonus = 0.0;
if ($hasBonusCol) {
    try {
        $sqlBonusSum = "
            SELECT IFNULL(SUM(bonus_amount),0) AS s
            FROM deposit
            WHERE user_id = ?
              AND LOWER(status) IN ('pending', 'approved', 'done')
              AND method <> 'REFUND'
            {$depDateCond}
        ";
        $qBonusSum = $db->prepare($sqlBonusSum);
        $qBonusSum->bind_param('s', $rawUserId);
        $qBonusSum->execute();
        $rBonusSum = $qBonusSum->get_result()->fetch_assoc();
        $sumBonus = (float) $rBonusSum['s'];
        $qBonusSum->close();
    } catch (Exception $e) {
    }
}

$sumRefund = 0.0;
try {
    $sqlRefundSum = "
        SELECT IFNULL(SUM({$depSumExpr}),0) AS s
        FROM deposit
        WHERE user_id = ?
          AND LOWER(status) IN ('pending', 'approved', 'done')
          AND method = 'REFUND'
        {$depDateCond}
    ";
    $qRefundSum = $db->prepare($sqlRefundSum);
    $qRefundSum->bind_param('s', $rawUserId);
    $qRefundSum->execute();
    $rRefundSum = $qRefundSum->get_result()->fetch_assoc();
    $sumRefund = (float) $rRefundSum['s'];
    $qRefundSum->close();
} catch (Exception $e) {
}

try {
    $sqlWithSumAll = "
        SELECT IFNULL(SUM(amount),0) AS s
        FROM withdrawal
        WHERE user_id = ?
          AND LOWER(status) IN ('pending', 'approved', 'done')
          AND method NOT LIKE '%bid%'
        {$withDateCond}
    ";
    $qWithSumAll = $db->prepare($sqlWithSumAll);
    $qWithSumAll->bind_param('s', $rawUserId);
    $qWithSumAll->execute();
    $rWithSumAll = $qWithSumAll->get_result()->fetch_assoc();
    $sumWithAll = (float) $rWithSumAll['s'];
} catch (Exception $e) {
}

try {
    $sqlWinSum = "
        SELECT IFNULL(SUM(wining_balance),0) AS s
        FROM wining
        WHERE (user_id = ? OR user_id = ?)
        {$winDateCond}
    ";
    $qWinSum = $db->prepare($sqlWinSum);
    $qWinSum->bind_param('ss', $rawUserId, $id);
    $qWinSum->execute();
    $rWinSum = $qWinSum->get_result()->fetch_assoc();
    $sumWin = (float) $rWinSum['s'];
    $qWinSum->close();

    // Starline & Jackpot Winnings
    $paddedIdBio = ($id !== '' && preg_match('/^\d+$/', (string)$id)) ? ("DM" . str_pad($id, 5, "0", STR_PAD_LEFT)) : (string)$rawUserId;
    foreach (['wining3', 'wining4'] as $winTable) {
        $safeWin = str_replace('`', '', $winTable);
        $chkWinT = $db->query("SHOW TABLES LIKE '{$safeWin}'");
        if ($chkWinT && $chkWinT->num_rows > 0) {
            $sqlWinExtra = "SELECT IFNULL(SUM(wining_balance),0) AS s FROM `{$safeWin}` WHERE (user_id = ? OR user_id = ? OR user_id = ?) AND LOWER(status)='win' {$winDateCond}";
            $qWinExtra = $db->prepare($sqlWinExtra);
            if ($qWinExtra) {
                $qWinExtra->bind_param('sss', $rawUserId, $id, $paddedIdBio);
                $qWinExtra->execute();
                $rWinExtra = $qWinExtra->get_result()->fetch_assoc();
                $sumWin += (float) ($rWinExtra['s'] ?? 0);
                $qWinExtra->close();
            }
        }
    }
} catch (Exception $e) {
}

/* ===== Bids & Wallet ===== */
$biddingAmount = 0.0;
try {
    // 1. Regular Bids Sum
    $sqlBidSum = "SELECT IFNULL(SUM(amount),0) AS s FROM bid b 
                  WHERE (user_id = ? OR user_id = ?) 
                  AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = b.id AND br.source_table IN ('bid','bids'))
                  {$bidDateCond}";
    $qBidSum = $db->prepare($sqlBidSum);
    $qBidSum->bind_param('ss', $rawUserId, $id);
    $qBidSum->execute();
    $rBidSum = $qBidSum->get_result()->fetch_assoc();
    $biddingAmount += (float) $rBidSum['s'];
    $qBidSum->close();

    // 2. Gali Bids Sum
    $chkTable = $db->query("SHOW TABLES LIKE 'gali_bids'");
    if ($chkTable && $chkTable->num_rows > 0) {
        $galiSumCond = str_replace('bid_time', 'date', $bidDateCond);
        $sqlGaliSum = "SELECT IFNULL(SUM(amount),0) AS s FROM gali_bids g 
                       WHERE user_id IN (?, ?) 
                       AND NOT EXISTS (SELECT 1 FROM bid_refunds br WHERE br.bid_id = g.id AND br.source_table IN ('gali_bid','gali_bids'))
                       {$galiSumCond}";
        $qGaliSum = $db->prepare($sqlGaliSum);
        if ($qGaliSum) {
            $qGaliSum->bind_param('ss', $id, $rawUserId);
            $qGaliSum->execute();
            $rGaliSum = $qGaliSum->get_result()->fetch_assoc();
            $biddingAmount += (float) $rGaliSum['s'];
            $qGaliSum->close();
        }
    }

    // 3. Jackpot 3 and Starline 4 Bids
    foreach (['bid3', 'bid4'] as $bidTable) {
        $safeBid = str_replace('`', '', $bidTable);
        $chkBidT = $db->query("SHOW TABLES LIKE '{$safeBid}'");
        if ($chkBidT && $chkBidT->num_rows > 0) {
            $sqlBidExtra = "SELECT IFNULL(SUM(amount),0) AS s FROM `{$safeBid}` WHERE (user_id = ? OR user_id = ?) AND COALESCE(is_cancelled, 0) = 0 {$bidDateCond}";
            $qBidExtra = $db->prepare($sqlBidExtra);
            if ($qBidExtra) {
                $qBidExtra->bind_param('ss', $rawUserId, $id);
                $qBidExtra->execute();
                $rBidExtra = $qBidExtra->get_result()->fetch_assoc();
                $biddingAmount += (float) ($rBidExtra['s'] ?? 0);
                $qBidExtra->close();
            }
        }
    }
} catch (Exception $e) {
}

// User Formula: Total Deposit + Total Bonus + Total Wining - Total Withdrawal - Bidding Amount (All Bids) = Wallet Balance
$rawWalletBalanceAfterBid = ($sumDep + $sumBonus + $sumWin) - ($sumWithAll + $biddingAmount); 
$walletBalanceAfterBid = max(0.00, $rawWalletBalanceAfterBid);
$pl = $sumWin - $biddingAmount;       // P&L (Wining - Bidding)

/* ===== KYC LOOKUP ===== */
$bankKycRow = null;
$upiKycRow = null;
$cryptoKycRow = null;
$bankKycStatus = 'Incomplete';
$upiKycStatus = 'Incomplete';
$cryptoKycStatus = 'Incomplete';

try {
    $stmtBank = $db->prepare("
        SELECT bank_name,account_name,account_number,ifsc,branch,status,date_time
        FROM bank_kyc
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtBank->bind_param('s', $rawUserId);
    $stmtBank->execute();
    $resBank = $stmtBank->get_result();
    if ($resBank->num_rows) {
        $bankKycRow = $resBank->fetch_assoc();
        $bankKycStatus = $bankKycRow['status'] ?: 'Incomplete';
    }
    $stmtBank->close();
} catch (Exception $e) {
}

try {
    $stmtUpi = $db->prepare("
        SELECT upi_id,qr_image,status,date_time
        FROM upi_kyc
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtUpi->bind_param('s', $rawUserId);
    $stmtUpi->execute();
    $resUpi = $stmtUpi->get_result();
    if ($resUpi->num_rows) {
        $upiKycRow = $resUpi->fetch_assoc();
        $upiKycStatus = $upiKycRow['status'] ?: 'Incomplete';
    }
    $stmtUpi->close();
} catch (Exception $e) {
}

try {
    $stmtCrypto = $db->prepare("
        SELECT address, network, status, qr_image, date_time
        FROM crypto_kyc
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtCrypto->bind_param('s', $rawUserId);
    $stmtCrypto->execute();
    $resCrypto = $stmtCrypto->get_result();
    if ($resCrypto->num_rows) {
        $cryptoKycRow = $resCrypto->fetch_assoc();
        $cryptoKycStatus = $cryptoKycRow['status'] ?: 'Incomplete';
    }
    $stmtCrypto->close();
} catch (Exception $e) {
}

/* ---- UPI Mobile numbers ---- */
$UPI_TYPES = ['phonepe', 'gpay', 'paytm', 'bhim'];
$upiNums = [];

foreach ($UPI_TYPES as $t) {
    try {
        $q = $db->prepare("
            SELECT upi_number, date_time
            FROM upi_numbers
            WHERE user_id = ? AND upi_type = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $q->bind_param('ss', $rawUserId, $t);
        $q->execute();
        $r = $q->get_result();
        if ($r->num_rows) {
            $row = $r->fetch_assoc();
            $upiNums[$t] = [
                'number' => $row['upi_number'],
                'date_time' => $row['date_time']
            ];
        }
        $q->close();
    } catch (Exception $e) {
    }
}

/* ===== ADMIN PAYMENT SETTINGS (USDT visibility) ===== */
$showCrypto = false;
try {
    $resAdm = $db->query("SELECT show_crypto FROM admin_payment_details LIMIT 1");
    if ($resAdm && $resAdm->num_rows) {
        $admRow = $resAdm->fetch_assoc();
        $showCrypto = ((int) $admRow['show_crypto'] === 1);
    }
} catch (Exception $e) {
}

$bankStatusClass = 'status-' . strtolower($bankKycStatus);
$upiStatusClass = 'status-' . strtolower($upiKycStatus);
$cryptoStatusClass = 'status-' . strtolower($cryptoKycStatus);

/* JS data arrays */
$bankKycJs = [
    'bank_name' => $bankKycRow['bank_name'] ?? '',
    'account_name' => $bankKycRow['account_name'] ?? '',
    'account_number' => $bankKycRow['account_number'] ?? '',
    'ifsc' => $bankKycRow['ifsc'] ?? '',
    'branch' => $bankKycRow['branch'] ?? '',
    'status' => $bankKycStatus
];

$upiKycJs = [
    'upi_id' => $upiKycRow['upi_id'] ?? '',
    'status' => $upiKycStatus,
    'qr_image' => $upiKycRow['qr_image'] ?? ''
];

$cryptoKycJs = [
    'address' => $cryptoKycRow['address'] ?? '',
    'network' => $cryptoKycRow['network'] ?? '',
    'status' => $cryptoKycStatus,
    'qr_image' => $cryptoKycRow['qr_image'] ?? ''
];

/* Account status text helpers */
$accMainText = $isActiveRaw ? 'Account Active' : 'Account Deactive';
$accNoteText = $isActiveRaw ? 'User can login & play.' : 'Login blocked – bids not allowed.';
$accBtnLabel = $isActiveRaw ? 'Deactivate Account' : 'Activate Account';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>User Bio – <?= $userId ?></title>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --red: #c62828;
            --green: #2e7d32;
            --amber: #ffb300;
            --brown: #4e342e;
            --blue: #1976d2;
            --bg: #f3f4f7;
            --glass: rgba(255, 255, 255, .9);
            --radius: 14px;
            --shadow: 0 4px 18px rgba(0, 0, 0, .08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Poppins, Arial, sans-serif
        }

        body {
            background: var(--bg);
            min-height: 100vh;
            padding: 24px 10px
        }

        a {
            color: inherit;
            text-decoration: none
        }

        .page-wrap {
            max-width: 1180px;
            margin: 0 auto 40px auto;
        }

        /* TOP BAR */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            padding: 10px 18px;
            border-radius: var(--radius);
            background: var(--glass);
            box-shadow: var(--shadow);
            gap: 12px;
            flex-wrap: wrap;
        }

        .top-bar-row-1 {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
        }

        .top-bar-row-2 {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #444;
        }

        .top-btn {
            background: #5d4037;
            color: #fff;
            padding: 8px 18px;
            border-radius: 999px;
            font-size: 14px;
        }

        .top-title {
            flex: 1;
            text-align: center;
            font-size: 22px;
            font-weight: 600;
            color: #4e342e;
        }

        /* Top Right Status */
        .top-right {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #444;
            flex-wrap: wrap;
        }

        .user-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-live {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .status-offline {
            background: #eceff1;
            color: #546e7a;
            border: 1px solid #cfd8dc;
        }

        /* Account badge colors */
        .acc-active {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }

        .acc-deactive {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-live .status-dot {
            background: #2e7d32;
            box-shadow: 0 0 5px rgba(46, 125, 50, 0.5);
        }

        .status-offline .status-dot {
            background: #90a4ae;
        }

        .acc-active .status-dot {
            background: #1565c0;
            box-shadow: 0 0 5px rgba(21, 101, 192, 0.5);
        }

        .acc-deactive .status-dot {
            background: #c62828;
            box-shadow: 0 0 5px rgba(198, 40, 40, 0.5);
        }

        /* MAIN CARD */
        .main-card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 18px;
        }

        /* User Info & Metrics Layout */
        .user-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        /* Account state row under name */
        .account-state-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .acc-pill {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .acc-pill-active {
            background: #e8f5e9;
            color: #2e7d32;
            border-color: #c8e6c9;
        }

        .acc-pill-deactive {
            background: #ffebee;
            color: #c62828;
            border-color: #ffcdd2;
        }

        .acc-note {
            font-size: 11px;
            color: #555;
        }

        /* Split layout */
        .user-split-layout {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        /* Left side: Info box */
        .user-left-panel {
            flex: 1.2;
            display: flex;
            flex-direction: column;
        }

        .info-box {
            background: #fdf5ee;
            border: 1px solid #ead8c7;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
            color: #4e342e;
        }

        .info-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(78, 52, 46, 0.08);
            padding-bottom: 6px;
            margin-bottom: 2px;
        }

        .info-line:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-label {
            font-weight: 700;
            color: #5d4037;
            font-size: 13px;
        }

        .info-value {
            font-weight: 600;
            color: #000;
            text-align: right;
            font-size: 13px;
        }

        .reg-date-val {
            font-size: 12px;
            font-weight: 600;
        }

        .acc-age-tag {
            font-size: 11px;
            color: #e65100;
            font-weight: 700;
            margin-left: 3px;
        }

        /* Right side: 4 Grid Boxes */
        .user-metrics-grid {
            flex: 1.5;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px;
        }

        .metric-card {
            background: #fff;
            border: 1px solid #ead8c7;
            border-radius: 12px;
            padding: 15px 10px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .metric-label {
            font-size: 12px;
            color: #6d4c41;
            margin-bottom: 8px;
        }

        .metric-value {
            font-size: 24px;
            font-weight: 800;
            color: #000;
        }

        /* CONTROL ROW */
        .control-row {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        /* Left Actions */
        .left-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 18px;
            border-radius: 999px;
            border: none;
            color: #fff;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .action-btn:active {
            transform: scale(0.98);
        }

        .btn-edit {
            background: #1976d2;
        }

        .btn-dep {
            background: #2e7d32;
        }

        .btn-with {
            background: #d32f2f;
        }

        .btn-acc {
            background: #6a1b9a;
        }

        /* Right Filters */
        .right-filters {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .filter-chip {
            padding: 7px 14px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            background: #f9f9f9;
            color: #555;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-chip:hover {
            background: #eee;
        }

        .filter-chip.active {
            background: #4e342e;
            color: #fff;
            border-color: #4e342e;
        }

        /* Custom Date Box Inline */
        .custom-range-box {
            display: none;
            align-items: center;
            gap: 5px;
            margin-left: 5px;
            background: #fff;
            padding: 3px;
            border-radius: 6px;
            border: 1px solid #eee;
        }

        .custom-range-box input[type="date"] {
            border: none;
            background: transparent;
            font-size: 12px;
            color: #333;
            padding: 2px;
        }

        .custom-btn-go {
            background: #4e342e;
            color: #fff;
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
        }

        /* Summary Boxes */
        .pl-summary {
            margin-top: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .pl-box {
            flex: 1 1 180px;
            background: #faf5ef;
            border-radius: 12px;
            padding: 12px 14px;
            border: 1px solid #e0d1c3;
        }

        .pl-title {
            font-size: 12px;
            color: #6d4c41;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .pl-value {
            font-size: 16px;
            font-weight: 800;
            color: #000;
        }

        .pl-positive {
            color: var(--green);
        }

        .pl-negative {
            color: var(--red);
        }

        .action-msg {
            margin-top: 6px;
            font-size: 12px;
            color: #2e7d32;
        }

        /* TABLES */
        .sections {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 16px;
        }

        .section {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 16px;
            scroll-margin-top: 15px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .section h3 {
            font-size: 15px;
            color: #4e342e;
        }

        .section-link {
            font-size: 12px;
            color: #0277bd;
        }

        .mini-table-wrap {
            overflow-x: auto;
            max-height: 340px;
        }

        .mini-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .mini-table th,
        .mini-table td {
            border: 1px solid #eee;
            padding: 10px 8px;
            text-align: center;
            vertical-align: middle;
        }

        .mini-table th {
            background: #f5eee7;
            font-weight: 700;
        }

        .mini-table td {
            font-weight: 600;
            color: #000;
        }

        .dt-cell {
            font-size: 11.5px;
            color: #555;
            white-space: nowrap;
            font-weight: 500;
        }

        .market-cell {
            font-weight: 600;
        }

        .bet-lbl {
            font-weight: 700;
        }

        .empty-note {
            font-size: 12px;
            color: #777;
            font-style: italic;
        }

        .total-row td {
            font-weight: 800;
            background: #fbe9e7;
        }

        /* KYC SECTION */
        .kyc-section {
            background: #ffffff;
            border: 1px solid #e0d7d0;
            position: relative;
            overflow: hidden;
            border-radius: var(--radius);
        }

        .kyc-section::before {
            content: '';
            position: absolute;
            right: -50px;
            top: -40px;
            width: 140px;
            height: 140px;
            background: radial-gradient(circle at center, rgba(198, 40, 40, 0.20), rgba(198, 40, 40, 0));
            border-radius: 50%;
        }

        .kyc-header {
            margin: -16px -16px 12px -16px;
            padding: 12px 18px;
            background: linear-gradient(90deg, #5d4037, #c62828);
            color: #fff;
            border-radius: 14px 14px 0 0;
        }

        .kyc-header-main {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .kyc-header-main i {
            font-size: 20px;
        }

        .kyc-header-main h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
        }

        .kyc-caption {
            font-size: 11px;
            opacity: .95;
        }

        .sub-title {
            font-size: 13px;
            font-weight: 700;
            color: #5d4037;
            margin: 6px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .upi-sub-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: nowrap;
            gap: 6px;
            width: 100%;
        }

        .upi-head-txt {
            white-space: nowrap;
            flex-shrink: 0;
            font-weight: 700;
        }

        .upi-sub-note {
            font-size: 11px;
            color: #7b5e57;
            white-space: nowrap;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .status-approved {
            background: #e8f5e9;
            color: #2e7d32;
            border-color: #c8e6c9;
        }

        .status-pending {
            background: #fff8e1;
            color: #ef6c00;
            border-color: #ffe0b2;
        }

        .status-incomplete,
        .status-rejected {
            background: #ffebee;
            color: #c62828;
            border-color: #ffcdd2;
        }

        /* KYC edit button */
        .kyc-edit-btn {
            border: none;
            background: #ffffff;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            color: #5d4037;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
        }

        .kyc-edit-btn i {
            font-size: 10px;
        }

        /* UPI App logo + text */
        .upi-app-cell {
            text-align: left !important;
        }

        .upi-app {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .upi-app img {
            width: 32px;
            height: 32px;
            object-fit: contain;
            border-radius: 8px;
            background: #ffffff;
        }

        .upi-app span {
            font-size: 14px;
            font-weight: 600;
            color: #000;
        }

        .mini-table tbody tr td.upi-app-cell {
            padding-top: 12px;
            padding-bottom: 12px;
        }

        /* MODALS (common) */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 200;
        }

        .modal-backdrop.show {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            min-width: 280px;
            max-width: 380px;
            box-shadow: var(--shadow);
        }

        .modal-title {
            font-size: 16px;
            font-weight: 600;
            color: #4e342e;
            margin-bottom: 14px;
            text-align: center;
        }

        .modal-row {
            margin-bottom: 12px;
        }

        .modal-row label {
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
            color: #555;
        }

        .modal-row input,
        .modal-row select {
            width: 100%;
            padding: 8px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 13px;
        }

        .modal-actions {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .modal-btn {
            padding: 8px 16px;
            border-radius: 999px;
            border: none;
            font-size: 12px;
            cursor: pointer;
        }

        .modal-cancel {
            background: #f5f5f5;
            color: #555;
        }

        .modal-submit {
            background: #2e7d32;
            color: #fff;
        }

        #profileModal .modal-box {
            max-width: 380px;
        }

        .modal-hint {
            font-size: 11px;
            color: #777;
            margin-top: 4px;
        }

        /* ✅ Wallet validation note */
        .val-note {
            margin-top: 6px;
            font-size: 11px;
            color: #c62828;
            display: none;
        }

        .val-note.show {
            display: block;
        }

        .input-error {
            border-color: #c62828 !important;
            box-shadow: 0 0 0 3px rgba(198, 40, 40, 0.08);
        }

        /* UPI & USDT QR preview */
        .qr-preview {
            margin-top: 8px;
            text-align: center;
        }

        .qr-preview img {
            max-width: 120px;
            max-height: 120px;
            border-radius: 10px;
            border: 1px solid #eee;
        }

        /* UPI numbers edit button */
        .upi-edit-btn {
            border: none;
            background: #eeeeee;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 11px;
            cursor: pointer;
        }

        /* Responsive */
        @media(max-width:900px) {
            .user-split-layout {
                flex-direction: column;
            }

            .user-metrics-grid {
                width: 100%;
            }

            .user-left-panel {
                width: 100%;
            }

            .top-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
                padding: 10px 12px;
            }

            .top-bar-row-1 {
                display: flex;
                align-items: center;
                justify-content: space-between;
                width: 100%;
                gap: 6px;
            }

            .top-btn {
                padding: 6px 12px;
                font-size: 12px;
                white-space: nowrap;
                flex-shrink: 0;
            }

            .top-title {
                font-size: 15px;
                font-weight: 700;
                white-space: nowrap;
                text-align: center;
                flex: 1;
                min-width: 0;
            }

            .top-live-wrap {
                flex-shrink: 0;
            }

            .top-bar-row-2 {
                display: flex;
                align-items: center;
                justify-content: space-between;
                width: 100%;
                gap: 6px;
                margin-top: 2px;
            }

            .user-status-badge {
                padding: 3px 8px;
                font-size: 10.5px;
            }

            .last-seen-txt {
                font-size: 10.5px;
                color: #666;
            }

            .control-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .right-filters {
                width: 100%;
                display: flex;
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 6px;
                gap: 5px;
                -webkit-overflow-scrolling: touch;
            }

            .right-filters::-webkit-scrollbar {
                height: 3px;
            }

            .right-filters::-webkit-scrollbar-thumb {
                background: rgba(0, 0, 0, 0.15);
                border-radius: 3px;
            }

            .filter-chip {
                white-space: nowrap;
                flex-shrink: 0;
                padding: 6px 10px;
                font-size: 11px;
            }

            .pagination-container {
                gap: 4px !important;
                padding: 8px 0 !important;
            }

            .pagination-info {
                font-size: 11px !important;
            }

            .pg-btn {
                padding: 5px 8px !important;
                font-size: 11px !important;
                gap: 3px !important;
            }

            .pl-summary {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }

            .pl-box {
                flex: none;
                width: 100%;
                padding: 10px 10px;
                box-sizing: border-box;
            }

            .pl-title {
                font-size: 11.5px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .pl-value {
                font-size: 15px;
            }

            .bid-type-badge {
                font-size: 0 !important;
                display: inline-block;
            }

            .bid-type-badge::after {
                content: attr(data-short);
                font-size: 11px;
                font-weight: 700;
            }

            .dt-cell {
                font-size: 10.5px !important;
                color: #666;
                padding: 5px 3px !important;
                white-space: nowrap;
                font-weight: 500;
            }

            .mini-table {
                font-size: 12px;
            }

            .mini-table th,
            .mini-table td {
                padding: 6px 4px !important;
            }

            .mini-table-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }

            .reg-date-val {
                font-size: 10px !important;
                font-weight: 600;
            }

            .acc-age-tag {
                font-size: 9px !important;
                margin-left: 2px;
            }

            .upi-head-txt {
                font-size: 11.5px !important;
            }

            .upi-sub-note {
                font-size: 9.5px !important;
            }

            .market-cell {
                font-size: 10.5px !important;
                line-height: 1.2 !important;
                white-space: nowrap;
            }

            .bet-lbl {
                font-size: 0 !important;
                display: inline-block;
            }
            .bet-lbl::after {
                content: attr(data-short);
                font-size: 11px;
                font-weight: 700;
                color: #4e342e;
            }
        }

        /* PAGINATION STYLES */
        .pagination-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 15px;
            padding: 10px 0;
            border-top: 1px solid #f0f0f0;
            flex-wrap: nowrap;
            gap: 8px;
            width: 100%;
        }

        .pagination-info {
            font-size: 13px;
            color: #4e342e;
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pg-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background: #fff;
            color: #4e342e;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            flex-shrink: 0;
            text-decoration: none;
        }

        .pg-btn:hover:not(:disabled) {
            background: #f5eee7;
            border-color: #4e342e;
        }

        .pg-btn:disabled, .pg-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f9f9f9;
            pointer-events: none;
        }

        .pg-btn i {
            font-size: 11px;
        }

        /* Success Popup / Toast Styling */
        #successPopup {
            display: none;
            position: fixed;
            top: 25px;
            right: 25px;
            background: #1565C0;
            color: #fff;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(21, 101, 192, 0.4);
            z-index: 9999;
            animation: slideInSuccess 0.5s cubic-bezier(0.18, 0.89, 0.32, 1.28);
            align-items: center;
            gap: 12px;
            font-weight: 500;
            font-size: 14px;
        }

        @keyframes slideInSuccess {
            from {
                transform: translateX(120%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        #successPopup i {
            font-size: 20px;
        }
    </style>
    <audio id="notifAudio" src="sound/notification.mp3" preload="auto"></audio>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

    <div class="page-wrap">

        <div class="top-bar" style="display:flex; align-items:center; justify-content:flex-start; gap:10px; flex-wrap:wrap; padding:12px 18px; border-radius:var(--radius); background:var(--glass); box-shadow:var(--shadow);">
            <!-- Back Button -->
            <a href="user_update.php" class="top-btn" style="flex-shrink:0;">
                <i class="fas fa-arrow-left"></i> Back
            </a>

            <!-- Live/Offline Badge -->
            <div class="top-live-wrap" style="flex-shrink:0;">
                <?php if ($isLive): ?>
                    <span class="user-status-badge status-live">
                        <span class="status-dot"></span> Live
                    </span>
                <?php else: ?>
                    <span class="user-status-badge status-offline">
                        <span class="status-dot"></span> Offline
                    </span>
                <?php endif; ?>
            </div>


                

            <!-- IP Address Badge (Left Aligned & Fully Visible) -->
            <span class="user-status-badge" style="background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; font-weight:600; padding:5px 12px; font-size:12.5px; border-radius:999px; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; flex-shrink:0;">
                <i class="fas fa-network-wired"></i> IP: <?= htmlspecialchars($userIpAddress, ENT_QUOTES, 'UTF-8') ?>
            </span>

            <!-- City/Location Badge from IP -->
            <?php
            if ($userCity === '-' || $userCity === null) {
                $apiCity = '-';
                $apiState = '-';
                if ($userIpAddress !== '-' && $userIpAddress !== '') {
                    try {
                        $url = "http://ip-api.com/json/" . urlencode($userIpAddress) . "?fields=status,city,regionName";
                        if (function_exists('curl_init')) {
                            $ch = curl_init($url);
                            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
                            $raw = curl_exec($ch);
                            curl_close($ch);
                            if ($raw) {
                                $json = json_decode($raw, true);
                                if (!empty($json['status']) && $json['status'] === 'success') {
                                    if (!empty($json['city'])) $apiCity = $json['city'];
                                    if (!empty($json['regionName'])) $apiState = $json['regionName'];
                                }
                            }
                        }
                    } catch (Throwable $e) {}
                    
                    if ($apiCity !== '-' || $apiState !== '-') {
                        $userCity = $apiCity;
                        $userState = $apiState;
                        if ($userFcmId > 0) {
                            $upd = $db->prepare("UPDATE user_fcm_tokens SET city=?, state=? WHERE id=?");
                            $upd->bind_param('ssi', $userCity, $userState, $userFcmId);
                            $upd->execute();
                        }
                    }
                }
            }
            ?>
            <?php if ($userCity !== '-'): ?>
                <span class="user-status-badge" style="background:#fff3e0; color:#e65100; border:1px solid #ffe0b2; font-weight:600; padding:5px 12px; font-size:12.5px; border-radius:999px; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; flex-shrink:0;">
                    <i class="fas fa-city"></i> City: <?= htmlspecialchars($userCity, ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php endif; ?>
            <?php if ($userState !== '-'): ?>
                <span class="user-status-badge" style="background:#f3e5f5; color:#6a1b9a; border:1px solid #e1bee7; font-weight:600; padding:5px 12px; font-size:12.5px; border-radius:999px; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; flex-shrink:0;">
                    <i class="fas fa-map"></i> State: <?= htmlspecialchars($userState, ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php endif; ?>

            <!-- Device ID Badge -->
            <span class="user-status-badge" style="background:#e3f2fd; color:#1565c0; border:1px solid #bbdefb; font-weight:600; padding:5px 12px; font-size:12.5px; border-radius:999px; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; flex-shrink:0;">
                <i class="fas fa-mobile-alt"></i> Device ID: <?= htmlspecialchars($userDeviceId, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($userDeviceId !== '-'): ?>
                    <button type="button" onclick="copyMobileNo('<?= htmlspecialchars($userDeviceId, ENT_QUOTES, 'UTF-8') ?>', this)" title="Copy Device ID" style="background:none; border:none; cursor:pointer; color:#1565c0; padding:0 2px; font-size:12px; outline:none; vertical-align:middle; margin-left:2px;">
                        <i class="fas fa-copy"></i>
                    </button>
                <?php endif; ?>
            </span>

            <!-- Last Seen (Right Aligned) -->
            <span class="last-seen-txt" style="margin-left:auto; font-size:11.5px; color:#666; white-space:nowrap; flex-shrink:0;">
                Last seen: <?= $lastSeenStr ?>
            </span>
        </div>

        <div class="main-card">

            <div class="user-title"><?= $userName ?></div>

            <div class="account-state-row">
                <span id="accStatePill" class="acc-pill <?= $isActiveRaw ? 'acc-pill-active' : 'acc-pill-deactive' ?>">
                    <?= htmlspecialchars($accMainText, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span id="accStateNote" class="acc-note">
                    <?= htmlspecialchars($accNoteText, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <div class="user-split-layout">

                <div class="user-left-panel">
                    <div class="info-box">
                        <div class="info-line">
                            <span class="info-label">User ID:</span>
                            <span class="info-value"><?= $userId ?></span>
                        </div>
                        <div class="info-line">
                            <span class="info-label">Mobile:</span>
                            <span class="info-value">
                                <?php
                                $rawMobVal = (string)($u['mobile_no'] ?? '');
                                $cleanMobVal = preg_replace('/\D/', '', $rawMobVal);
                                if (!empty($cleanMobVal)):
                                ?>
                                    <a href="https://wa.me/91<?= $cleanMobVal ?>" target="_blank" title="Chat on WhatsApp / WhatsApp Business" style="color:#25D366; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-weight:600; transition:color .15s;" onmouseover="this.style.color='#128C7E'" onmouseout="this.style.color='#25D366'">
                                        <i class="fab fa-whatsapp" style="color:#25D366; font-size:16px;"></i>
                                        <?= $userMob ?>
                                    </a>
                                    <button type="button" onclick="copyMobileNo('<?= htmlspecialchars($cleanMobVal, ENT_QUOTES, 'UTF-8') ?>', this)" title="Copy Mobile Number" style="background:none; border:none; cursor:pointer; color:#888; padding:2px 4px; font-size:14px; transition:color .15s; outline:none; vertical-align:middle; margin-left:4px;" onmouseover="this.style.color='#4e342e'" onmouseout="this.style.color='#888'">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                <?php else: ?>
                                    <?= $userMob ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-line">
                            <span class="info-label">Live Chat:</span>
                            <span class="info-value">
                                <a href="admin_live_chat.php?uid=<?= urlencode($rawUserId) ?>" title="Live Chat - <?= $userId ?>" style="display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:999px; background:linear-gradient(135deg, #e53935, #b71c1c); color:#fff; font-size:12px; font-weight:600; text-decoration:none; box-shadow:0 2px 6px rgba(229,57,53,.35); transition:transform .15s, box-shadow .15s;" onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 10px rgba(229,57,53,.5)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 2px 6px rgba(229,57,53,.35)';">
                                    <i class="fas fa-comment-dots"></i> Live Chat
                                </a>
                            </span>
                        </div>
                        <div class="info-line">
                            <span class="info-label">Reg. Date:</span>
                            <span class="info-value">
                                <span class="reg-date-val"><?= $regDate ?></span>
                                <?php if ($accountAgeStr): ?>
                                    <span class="acc-age-tag"><?= $accountAgeStr ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-line">
                            <span class="info-label">Password:</span>
                            <span class="info-value">
                                <?= $userPwd ?>
                                <?php if (!empty($u['password'])): ?>
                                    <button type="button" onclick="copyMobileNo('<?= htmlspecialchars($u['password'], ENT_QUOTES, 'UTF-8') ?>', this)" title="Copy Password" style="background:none; border:none; cursor:pointer; color:#888; padding:2px 4px; font-size:14px; transition:color .15s; outline:none; vertical-align:middle; margin-left:4px;" onmouseover="this.style.color='#4e342e'" onmouseout="this.style.color='#888'">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="user-metrics-grid">
                    <div class="metric-card">
                        <div class="metric-label">Number of Deposit</div>
                        <div class="metric-value"><?= (int) $countDeposit ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Number of Withdrawal</div>
                        <div class="metric-value"><?= (int) $countWithdrawal ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Number of Bids</div>
                        <div class="metric-value"><?= (int) $countBids ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Number of Winings</div>
                        <div class="metric-value"><?= (int) $countWin ?></div>
                    </div>
                </div>
            </div>

            <div class="control-row">

                <div class="left-actions">
                    <button type="button" class="action-btn btn-edit" id="editProfileBtn">
                        <i class="fas fa-pen"></i> Edit Profile
                    </button>
                    <button type="button" class="action-btn btn-dep" id="openDepBtn">
                        <i class="fas fa-plus"></i> Deposit
                    </button>
                    <button type="button" class="action-btn btn-with" id="openWithBtn">
                        <i class="fas fa-minus"></i> Withdrawal
                    </button>
                    <button type="button" class="action-btn btn-acc" id="toggleActiveBtn"
                        data-current="<?= $isActiveRaw ? 1 : 0 ?>">
                        <i class="fas <?= $isActiveRaw ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                        <?= htmlspecialchars($accBtnLabel, ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>

                <form method="get" id="filterForm" class="right-filters">
                    <button type="button" class="filter-chip <?= $range === 'all' ? 'active' : '' ?>"
                        data-range="all">All</button>
                    <button type="button" class="filter-chip <?= $range === 'today' ? 'active' : '' ?>"
                        data-range="today">Today</button>
                    <button type="button" class="filter-chip <?= $range === '7' ? 'active' : '' ?>" data-range="7">7
                        Days</button>
                    <button type="button" class="filter-chip <?= $range === '15' ? 'active' : '' ?>" data-range="15">15
                        Days</button>
                    <button type="button" class="filter-chip <?= $range === '30' ? 'active' : '' ?>" data-range="30">30
                        Days</button>
                    <button type="button" class="filter-chip <?= $range === 'custom' ? 'active' : '' ?>"
                        data-range="custom">Custom</button>

                    <div id="customRangeBox" class="custom-range-box">
                        <input type="date" id="customFrom"
                            value="<?= htmlspecialchars($fromDate ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <span style="font-size:10px;color:#888;">to</span>
                        <input type="date" id="customTo"
                            value="<?= htmlspecialchars($toDate ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <button type="button" class="custom-btn-go" id="applyCustomBtn">Go</button>
                    </div>

                    <div class="limit-select-wrap" style="display:inline-flex; align-items:center; gap:4px; margin-left:4px; flex-shrink:0;">
                        <span style="font-size:11px; font-weight:600; color:#555;">Show:</span>
                        <select name="limit" id="limitSelect" class="filter-chip" style="cursor:pointer; padding:5px 8px; font-weight:600; font-size:11px;" onchange="document.getElementById('limitInput').value=this.value; document.getElementById('filterForm').submit();">
                            <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                        </select>
                    </div>

                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="limit" id="limitInput" value="<?= $limit ?>">
                    <input type="hidden" name="range" id="rangeInput"
                        value="<?= htmlspecialchars($range, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="from" id="fromInput"
                        value="<?= htmlspecialchars($fromDate ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="to" id="toInput"
                        value="<?= htmlspecialchars($toDate ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </form>
            </div>

            <?php
            if ($actionMsg): 
                $isSuccess = (stripos($actionMsg, 'successfully') !== false);
            ?>
                <?php if ($isSuccess): ?>
                    <div id="successPopup">
                        <i class="fas fa-check-circle"></i>
                        <span><?= htmlspecialchars($actionMsg, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var popup = document.getElementById('successPopup');
                            var audio = document.getElementById('notifAudio');
                            popup.style.display = 'flex';
                            audio.play().catch(e => console.log('Audio error:', e));
                            setTimeout(function() {
                                popup.style.opacity = '0';
                                popup.style.transition = 'opacity 0.5s ease';
                                setTimeout(function() { return popup.style.display = 'none'; }, 500);
                            }, 4500);
                        });
                    </script>
                <?php else: ?>
                    <div class="action-msg" style="margin-top:10px;"><?= htmlspecialchars($actionMsg, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="pl-summary">
                <div class="pl-box">
                    <div class="pl-title">Total Deposit</div>
                    <div class="pl-value" id="sumDepVal">₹<?= formatMoney($sumDep) ?></div>
                </div>
                <?php if ($hasBonusCol): ?>
                <div class="pl-box">
                    <div class="pl-title">Total Bonus</div>
                    <div class="pl-value" style="color:var(--amber);">
                        ₹<?= formatMoney($sumBonus) ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="pl-box">
                    <div class="pl-title">Total Withdrawal</div>
                    <div class="pl-value" id="sumWithVal">₹<?= formatMoney($sumWithAll) ?></div>
                </div>
                <div class="pl-box">
                    <div class="pl-title">Total Bidding</div>
                    <div class="pl-value" id="bidAmtVal">₹<?= formatMoney($biddingAmount) ?></div>
                </div>
                <div class="pl-box">
                    <div class="pl-title">Total Wining</div>
                    <div class="pl-value" style="color:var(--green);">₹<?= formatMoney($sumWin) ?></div>
                </div>
                <?php
                require_once 'includes/db.php';
                $plClass = $pl >= 0 ? 'pl-positive' : 'pl-negative';
                $plIcon = $pl >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down';
                ?>
                <div class="pl-box">
                    <div class="pl-title">Profit / Loss</div>
                    <div class="pl-value <?= $plClass ?>">
                        <i class="fas <?= $plIcon ?>"></i> ₹<?= formatMoney($pl) ?>
                    </div>
                </div>
                <?php
                require_once 'includes/db.php';
                $wbClass = $walletBalanceAfterBid >= 0 ? 'pl-positive' : 'pl-negative'; ?>
                <div class="pl-box">
                    <div class="pl-title">Total Balance</div>
                    <div class="pl-value <?= $wbClass ?>" id="walletBalVal">
                        ₹<?= formatMoney($walletBalanceAfterBid) ?>
                    </div>
                </div>
                <div class="pl-box">
                    <div class="pl-title">Refund</div>
                    <div class="pl-value" style="color:var(--blue);">
                        ₹<?= formatMoney($sumRefund) ?>
                    </div>
                </div>
                <div class="pl-box">
                    <div class="pl-title">Withdrawal Pending</div>
                    <div class="pl-value" style="color:var(--red);">
                        ₹<?= formatMoney($sumWithPending) ?>
                    </div>
                </div>
                </div>

            </div>
        </div>

        <!-- ====== KYC OVERVIEW SECTION ====== -->
        <div class="sections">
            <div class="section kyc-section">
                <div class="section-header kyc-header">
                    <div class="kyc-header-main">
                        <i class="fas fa-id-card"></i>
                        <div>
                            <h3>KYC Details</h3>
                            <span class="kyc-caption">Bank &amp; UPI verification information</span>
                        </div>
                    </div>
                </div>

                <!-- Bank KYC -->
                <div style="margin-bottom:10px;">
                    <div class="sub-title">
                        <span>Bank KYC</span>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span class="status-chip <?= htmlspecialchars($bankStatusClass, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($bankKycStatus, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <button type="button" class="kyc-edit-btn" id="editBankKycBtn">
                                <i class="fas fa-pen"></i> Edit
                            </button>
                        </div>
                    </div>
                    <?php
                    require_once 'includes/db.php';
                    if ($bankKycRow): ?>
                        <div class="mini-table-wrap">
                            <table class="mini-table">
                                <thead>
                                    <tr>
                                        <th>Bank Name</th>
                                        <th>Account Name</th>
                                        <th>Account No.</th>
                                        <th>IFSC</th>
                                        <th>Branch</th>
                                        <th>Status</th>
                                        <th>Updated On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?= htmlspecialchars($bankKycRow['bank_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td><?= htmlspecialchars($bankKycRow['account_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td><?= htmlspecialchars($bankKycRow['account_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td><?= htmlspecialchars($bankKycRow['ifsc'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($bankKycRow['branch'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($bankKycRow['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php
                                            require_once 'includes/db.php';
                                            if (!empty($bankKycRow['date_time']) && $bankKycRow['date_time'] !== '0000-00-00 00:00:00') {
                                                echo date('d-m-Y h:i A', strtotime($bankKycRow['date_time']));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php
                        require_once 'includes/db.php';
                    else: ?>
                        <p class="empty-note">No Bank KYC record.</p>
                        <?php
                        require_once 'includes/db.php';
                    endif; ?>
                </div>

                <!-- UPI KYC -->
                <div style="margin-top:12px;margin-bottom:10px;">
                    <div class="sub-title">
                        <span>UPI KYC</span>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span class="status-chip <?= htmlspecialchars($upiStatusClass, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($upiKycStatus, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <button type="button" class="kyc-edit-btn" id="editUpiKycBtn">
                                <i class="fas fa-pen"></i> Edit
                            </button>
                        </div>
                    </div>
                    <?php
                    require_once 'includes/db.php';
                    if ($upiKycRow): ?>
                        <div class="mini-table-wrap">
                            <table class="mini-table">
                                <thead>
                                    <tr>
                                        <th>UPI ID</th>
                                        <th>Status</th>
                                        <th>QR</th>
                                        <th>Updated On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?= htmlspecialchars($upiKycRow['upi_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($upiKycRow['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php
                                            require_once 'includes/db.php';
                                            if (!empty($upiKycRow['qr_image'])): ?>
                                                <a href="<?= htmlspecialchars($upiKycRow['qr_image'], ENT_QUOTES, 'UTF-8') ?>"
                                                    target="_blank">View QR</a>
                                                <?php
                                                require_once 'includes/db.php';
                                            else: ?>
                                                -
                                                <?php
                                                require_once 'includes/db.php';
                                            endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            require_once 'includes/db.php';
                                            if (!empty($upiKycRow['date_time']) && $upiKycRow['date_time'] !== '0000-00-00 00:00:00') {
                                                echo date('d-m-Y h:i A', strtotime($upiKycRow['date_time']));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php
                        require_once 'includes/db.php';
                    else: ?>
                        <p class="empty-note">No UPI KYC record.</p>
                        <?php
                        require_once 'includes/db.php';
                    endif; ?>
                </div>

                <!-- USDT / Crypto KYC (only if enabled in admin_payment_details) -->
                <?php
                require_once 'includes/db.php';
                if ($showCrypto): ?>
                    <div style="margin-top:12px;margin-bottom:10px;">
                        <div class="sub-title">
                            <span>Crypto (USDT) KYC</span>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="status-chip <?= htmlspecialchars($cryptoStatusClass, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($cryptoKycStatus, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <button type="button" class="kyc-edit-btn" id="editCryptoKycBtn">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                            </div>
                        </div>
                        <?php
                        require_once 'includes/db.php';
                        if ($cryptoKycRow): ?>
                            <div class="mini-table-wrap">
                                <table class="mini-table">
                                    <thead>
                                        <tr>
                                            <th>USDT Address</th>
                                            <th>Network</th>
                                            <th>QR</th>
                                            <th>Status</th>
                                            <th>Updated On</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?= htmlspecialchars($cryptoKycRow['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td><?= htmlspecialchars($cryptoKycRow['network'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td>
                                                <?php
                                                require_once 'includes/db.php';
                                                if (!empty($cryptoKycRow['qr_image'])): ?>
                                                    <a href="<?= htmlspecialchars($cryptoKycRow['qr_image'], ENT_QUOTES, 'UTF-8') ?>"
                                                        target="_blank">View QR</a>
                                                    <?php
                                                    require_once 'includes/db.php';
                                                else: ?>
                                                    -
                                                    <?php
                                                    require_once 'includes/db.php';
                                                endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($cryptoKycRow['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?php
                                                require_once 'includes/db.php';
                                                if (!empty($cryptoKycRow['date_time']) && $cryptoKycRow['date_time'] !== '0000-00-00 00:00:00') {
                                                    echo date('d-m-Y h:i A', strtotime($cryptoKycRow['date_time']));
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <?php
                            require_once 'includes/db.php';
                        else: ?>
                            <p class="empty-note">No USDT KYC record.</p>
                            <?php
                            require_once 'includes/db.php';
                        endif; ?>
                    </div>
                    <?php
                    require_once 'includes/db.php';
                endif; ?>

                <!-- UPI Mobile Numbers -->
                <div style="margin-top:12px;">
                    <div class="sub-title upi-sub-title">
                        <span class="upi-head-txt">UPI Mobile Numbers</span>
                        <span class="upi-sub-note">(PhonePe / GPay / Paytm / BHIM)</span>
                    </div>
                    <?php
                    require_once 'includes/db.php';
                    $logos = [
                        'phonepe' => ['PhonePe', 'images/phonpe-icon.png'],
                        'gpay' => ['Google Pay', 'images/gpay-icon.png'],
                        'paytm' => ['Paytm', 'images/paytm-icon.png'],
                        'bhim' => ['BHIM', 'images/bhim-icon.png'],
                    ];
                    ?>
                    <div class="mini-table-wrap">
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>UPI App</th>
                                    <th>Mobile Number</th>
                                    <th>Updated On</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                require_once 'includes/db.php';
                                $i = 1;
                                foreach ($logos as $key => $arr):
                                    $label = $arr[0];
                                    $img = $arr[1];
                                    $row = $upiNums[$key] ?? null;
                                    $num = $row['number'] ?? '';
                                    $dt = $row['date_time'] ?? '';
                                    ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td class="upi-app-cell">
                                            <div class="upi-app">
                                                <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>"
                                                    alt="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                                                <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            if (!empty($num)):
                                                $cleanUpiNum = preg_replace('/\D/', '', $num);
                                            ?>
                                                <div style="display:inline-flex; align-items:center; gap:6px;">
                                                    <a href="https://wa.me/91<?= $cleanUpiNum ?>" target="_blank" title="Chat on WhatsApp / WhatsApp Business" style="color:#25D366; text-decoration:none; display:inline-flex; align-items:center; gap:5px; font-weight:600; transition:color .15s;" onmouseover="this.style.color='#128C7E'" onmouseout="this.style.color='#25D366'">
                                                        <i class="fab fa-whatsapp" style="color:#25D366; font-size:15px;"></i>
                                                        <?= htmlspecialchars($num, ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                    <button type="button" onclick="copyMobileNo('<?= htmlspecialchars($cleanUpiNum, ENT_QUOTES, 'UTF-8') ?>', this)" title="Copy Mobile Number" style="background:none; border:none; cursor:pointer; color:#888; padding:2px 4px; font-size:13px; transition:color .15s; outline:none;" onmouseover="this.style.color='#4e342e'" onmouseout="this.style.color='#888'">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="dt-cell">
                                            <?php
                                            require_once 'includes/db.php';
                                            if ($dt && $dt !== '0000-00-00 00:00:00') {
                                                echo date('d-m-Y h:i A', strtotime($dt));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button type="button" class="upi-edit-btn"
                                                data-upi-type="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                                data-upi-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                                                data-upi-number="<?= htmlspecialchars($num, ENT_QUOTES, 'UTF-8') ?>">
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                    <?php
                                    require_once 'includes/db.php';
                                endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- ===== Deposit / Withdrawal / Bid / Wining sections ===== -->
            <div class="section" id="secDeposit">
                <div class="section-header">
                    <h3>Deposit History <span style="font-size:12px; font-weight:normal; margin-left:10px; background:#1565C0; color:#fff; padding:2px 8px; border-radius:10px;">Total: ₹<?= formatMoney($sumDep) ?></span></h3>
                </div>
                <?php if ($countDeposit > 0): ?>
                    <div class="mini-table-wrap">
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Bonus</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalDepList = 0;
                                // Need to seek for pagination or move to array
                                mysqli_data_seek($resDep, $pgDep['start']);
                                $shown = 0;
                                while ($d = $resDep->fetch_assoc()):
                                    if ($shown >= $limit) break;
                                    $shown++;
                                    
                                    $amt = (float) $d['amount'];
                                    if (strtolower(trim($d['status'])) !== 'failed') {
                                        $totalDepList += $amt;
                                    }
                                    $methodDisplay = ($d['method'] && stripos($d['method'], 'admin') === false) ? $d['method'] : 'Admin';
                                    ?>
                                    <tr>
                                        <td><?= $pgDep['start'] + $shown ?></td>
                                        <td class="dt-cell"><?= $d['dt'] ?></td>
                                        <td>₹<?= $d['amount'] ?></td>
                                        <td style="color:var(--green); font-weight:700;">₹<?= (float)$d['bonus_amount'] ?></td>
                                        <td><?= htmlspecialchars($methodDisplay, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($d['status'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="pagination-container">
                        <a href="<?= pgLink($baseUrl, ['pd' => $pgDep['current'] - 1, 'pw' => $pageWith, 'pb' => $pageBid, 'pv' => $pageWin]) ?>#secDeposit" 
                           class="pg-btn <?= $pgDep['current'] <= 1 ? 'disabled' : '' ?>" 
                           <?= $pgDep['current'] <= 1 ? 'onclick="return false;"' : '' ?>>
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                        <div class="pagination-info">
                            Showing <?= $pgDep['display_start'] ?> to <?= $pgDep['end'] ?> of <?= $pgDep['total'] ?> entries
                        </div>
                        <a href="<?= pgLink($baseUrl, ['pd' => $pgDep['current'] + 1, 'pw' => $pageWith, 'pb' => $pageBid, 'pv' => $pageWin]) ?>#secDeposit" 
                           class="pg-btn <?= $pgDep['current'] >= $pgDep['pages'] ? 'disabled' : '' ?>"
                           <?= $pgDep['current'] >= $pgDep['pages'] ? 'onclick="return false;"' : '' ?>>
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <p class="empty-note">No deposit history.</p>
                <?php endif; ?>
            </div>

            <div class="section" id="secWithdrawal">
                <div class="section-header">
                    <h3>Withdrawal History <span style="font-size:12px; font-weight:normal; margin-left:10px; background:#1565C0; color:#fff; padding:2px 8px; border-radius:10px;">Total: ₹<?= formatMoney($sumWithAll) ?></span></h3>
                </div>
                <?php if ($countWithdrawal > 0): ?>
                    <div class="mini-table-wrap">
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $wSliced = array_slice($withdrawRows, $pgWith['start'], $limit);
                                $i = $pgWith['start'] + 1;
                                foreach ($wSliced as $w):
                                    $mDisp = (stripos($w['method'], 'admin') !== false) ? 'Admin' : $w['method'];
                                    ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td class="dt-cell"><?= $w['dt'] ?></td>
                                        <td>₹<?= $w['amount'] ?></td>
                                        <td><?= htmlspecialchars($mDisp, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($w['status'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-container">
                        <a href="<?= pgLink($baseUrl, ['pw' => $pgWith['current'] - 1, 'pd' => $pageDep, 'pb' => $pageBid, 'pv' => $pageWin]) ?>#secWithdrawal" 
                           class="pg-btn <?= $pgWith['current'] <= 1 ? 'disabled' : '' ?>" 
                           <?= $pgWith['current'] <= 1 ? 'onclick="return false;"' : '' ?>>
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                        <div class="pagination-info">
                            Showing <?= $pgWith['display_start'] ?> to <?= $pgWith['end'] ?> of <?= $pgWith['total'] ?> entries
                        </div>
                        <a href="<?= pgLink($baseUrl, ['pw' => $pgWith['current'] + 1, 'pd' => $pageDep, 'pb' => $pageBid, 'pv' => $pageWin]) ?>#secWithdrawal" 
                           class="pg-btn <?= $pgWith['current'] >= $pgWith['pages'] ? 'disabled' : '' ?>"
                           <?= $pgWith['current'] >= $pgWith['pages'] ? 'onclick="return false;"' : '' ?>>
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <p class="empty-note">No withdrawal history.</p>
                <?php endif; ?>
            </div>

            <div class="section" id="secBids">
                <div class="section-header">
                    <h3>Bid History <span style="font-size:12px; font-weight:normal; margin-left:10px; background:#1565C0; color:#fff; padding:2px 8px; border-radius:10px;">Total: ₹<?= formatMoney($biddingAmount) ?></span></h3>
                </div>
                <?php if ($countBids > 0): ?>
                    <div class="mini-table-wrap">
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Market</th>
                                    <th>Session</th>
                                    <th>Bid Type</th>
                                    <th>Bet Details</th>
                                    <th>Amount</th><th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $bSliced = array_slice($allBids, $pgBid['start'], $limit);
                                $i = $pgBid['start'] + 1;
                                foreach ($bSliced as $b):
                                    $betInfo = buildBetDisplayRow($b);
                                    ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td class="dt-cell"><?= $b['dt'] ?></td>
                                        <td class="market-cell"><?= htmlspecialchars($b['market'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($betInfo['session_label'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><b><span class="bid-type-badge" data-short="<?= htmlspecialchars(getShortBidType($betInfo['bid_type']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($betInfo['bid_type'], ENT_QUOTES, 'UTF-8') ?></span></b></td>
                                        <td style="text-align:left;"><?= $betInfo['bet_html'] ?></td>
                                        <td>₹<?= $b['amount'] ?></td>
                                        <td>
                                            <button type="button" style="background:#ff3b30; color:white; padding:4px 8px; border:none; border-radius:4px; cursor:pointer;" onclick="deleteBidAjax(<?= $b['id'] ?>, '<?= $b['src'] ?>', <?= $id ?>, <?= $b['amount'] ?>, '<?= htmlspecialchars($b['bid_time'] ?? $b['date'] ?? '', ENT_QUOTES) ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-container">
                        <a href="<?= pgLink($baseUrl, ['pb' => $pgBid['current'] - 1, 'pd' => $pageDep, 'pw' => $pageWith, 'pv' => $pageWin]) ?>#secBids" 
                           class="pg-btn <?= $pgBid['current'] <= 1 ? 'disabled' : '' ?>" 
                           <?= $pgBid['current'] <= 1 ? 'onclick="return false;"' : '' ?>>
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                        <div class="pagination-info">
                            Showing <?= $pgBid['display_start'] ?> to <?= $pgBid['end'] ?> of <?= $pgBid['total'] ?> entries
                        </div>
                        <a href="<?= pgLink($baseUrl, ['pb' => $pgBid['current'] + 1, 'pd' => $pageDep, 'pw' => $pageWith, 'pv' => $pageWin]) ?>#secBids" 
                           class="pg-btn <?= $pgBid['current'] >= $pgBid['pages'] ? 'disabled' : '' ?>"
                           <?= $pgBid['current'] >= $pgBid['pages'] ? 'onclick="return false;"' : '' ?>>
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <p class="empty-note">No bid history.</p>
                <?php endif; ?>
            </div>

            <div class="section" id="secWins">
                <div class="section-header">
                    <h3>Wining History <span style="font-size:12px; font-weight:normal; margin-left:10px; background:#1565C0; color:#fff; padding:2px 8px; border-radius:10px;">Total: ₹<?= formatMoney($sumWin) ?></span></h3>
                </div>
                <?php if ($countWin > 0): ?>
                    <div class="mini-table-wrap">
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Market</th>
                                    <th>Session</th>
                                    <th>Bid Type</th>
                                    <th>Bet Details</th>
                                    <th>Bet Amt</th>
                                    <th>Win Amt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $wSliced = array_slice($allWins, $pgWin['start'], $limit);
                                $shownWin = 0;
                                foreach ($wSliced as $r):
                                    $shownWin++;
                                    $betInfo = buildBetDisplayRow($r);
                                    ?>
                                    <tr>
                                        <td><?= $pgWin['start'] + $shownWin ?></td>
                                        <td class="dt-cell"><?= $r['dt'] ?></td>
                                        <td class="market-cell"><?= htmlspecialchars($r['market'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($betInfo['session_label'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><b><span class="bid-type-badge" data-short="<?= htmlspecialchars(getShortBidType($betInfo['bid_type']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($betInfo['bid_type'], ENT_QUOTES, 'UTF-8') ?></span></b></td>
                                        <td style="text-align:left;"><?= $betInfo['bet_html'] ?></td>
                                        <td>₹<?= $r['amount'] ?></td>
                                        <td>₹<?= $r['wining_balance'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-container">
                        <a href="<?= pgLink($baseUrl, ['pv' => $pgWin['current'] - 1, 'pd' => $pageDep, 'pw' => $pageWith, 'pb' => $pageBid]) ?>#secWins" 
                           class="pg-btn <?= $pgWin['current'] <= 1 ? 'disabled' : '' ?>" 
                           <?= $pgWin['current'] <= 1 ? 'onclick="return false;"' : '' ?>>
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                        <div class="pagination-info">
                            Showing <?= $pgWin['display_start'] ?> to <?= $pgWin['end'] ?> of <?= $pgWin['total'] ?> entries
                        </div>
                        <a href="<?= pgLink($baseUrl, ['pv' => $pgWin['current'] + 1, 'pd' => $pageDep, 'pw' => $pageWith, 'pb' => $pageBid]) ?>#secWins" 
                           class="pg-btn <?= $pgWin['current'] >= $pgWin['pages'] ? 'disabled' : '' ?>"
                           <?= $pgWin['current'] >= $pgWin['pages'] ? 'onclick="return false;"' : '' ?>>
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <p class="empty-note">No wining history.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Wallet Modal -->
    <div class="modal-backdrop" id="walletModal">
        <div class="modal-box">
            <div class="modal-title" id="walletModalTitle">Adjust Wallet</div>
            <form method="post" id="walletForm" action="bio_data.php?id=<?= (int)$id ?>">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <input type="hidden" name="wallet_action" id="walletAction" value="deposit">
                <div class="modal-row">
                    <label>Amount</label>
                    <input type="number" step="0.01" name="wallet_amount" id="walletAmount" required>
                    <div class="val-note show" id="walletValNote">Minimum deposit ₹<?= formatMoney($minDep) ?></div>
                </div>
                <div class="modal-row">
                    <label>Note (optional)</label>

                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-cancel" id="walletCancel">Cancel</button>
                    <button type="submit" class="modal-btn modal-submit" id="walletSubmitBtn">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal-backdrop" id="profileModal">
        <div class="modal-box">
            <div class="modal-title">Edit Profile</div>
            <div class="modal-row">
                <label>Name</label>
                <input type="text" id="editName">
            </div>
            <div class="modal-row">
                <label>Mobile</label>
                <input type="text" id="editMobile" maxlength="10">
            </div>
            <div class="modal-row">
                <label>Email</label>
                <input type="email" id="editEmail">
            </div>
            <div class="modal-row">
                <label>Password</label>
                <input type="text" id="editPassword">
            </div>
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-cancel" id="profileCancel">Cancel</button>
                <button type="button" class="modal-btn modal-submit" id="profileSaveBtn">Save</button>
            </div>
        </div>
    </div>

    <!-- Bank KYC Modal -->
    <div class="modal-backdrop" id="bankKycModal">
        <div class="modal-box">
            <div class="modal-title">Edit Bank KYC</div>
            <form method="post">
                <input type="hidden" name="bank_kyc_save" value="1">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <div class="modal-row">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" id="bank_name">
                </div>
                <div class="modal-row">
                    <label>Account Holder Name</label>
                    <input type="text" name="account_name" id="account_name">
                </div>
                <div class="modal-row">
                    <label>Account Number</label>
                    <input type="text" name="account_number" id="account_number">
                </div>
                <div class="modal-row">
                    <label>IFSC Code</label>
                    <input type="text" name="ifsc" id="ifsc">
                </div>
                <div class="modal-row">
                    <label>Branch</label>
                    <input type="text" name="branch" id="branch">
                </div>
                <div class="modal-row">
                    <label>Status</label>
                    <select name="bank_status" id="bank_status">
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Incomplete">Incomplete</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-cancel" data-close="bankKycModal">Cancel</button>
                    <button type="submit" class="modal-btn modal-submit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- UPI KYC Modal -->
    <div class="modal-backdrop" id="upiKycModal">
        <div class="modal-box">
            <div class="modal-title">Edit UPI KYC</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="upi_kyc_save" value="1">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <input type="hidden" name="current_qr" id="current_qr">
                <div class="modal-row">
                    <label>UPI ID</label>
                    <input type="text" name="upi_id" id="upi_id">
                </div>
                <div class="modal-row">
                    <label>Status</label>
                    <select name="upi_status" id="upi_status">
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Incomplete">Incomplete</option>
                    </select>
                </div>
                <div class="modal-row">
                    <label>QR Image (PNG / JPG / WEBP)</label>
                    <input type="file" name="upi_qr" id="upi_qr" accept="image/*">
                    <div class="modal-hint">Naya QR upload karne par purana auto replace ho jayega.</div>
                </div>
                <div class="qr-preview" id="qrPreviewBox" style="display:none;">
                    <div style="font-size:11px;color:#555;margin-bottom:4px;">Current QR</div>
                    <img id="qrPreviewImg" src="" alt="UPI QR">
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-cancel" data-close="upiKycModal">Cancel</button>
                    <button type="submit" class="modal-btn modal-submit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Crypto (USDT) KYC Modal -->
    <div class="modal-backdrop" id="cryptoKycModal">
        <div class="modal-box">
            <div class="modal-title">Edit Crypto (USDT) KYC</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="crypto_kyc_save" value="1">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <input type="hidden" name="current_crypto_qr" id="current_crypto_qr">
                <div class="modal-row">
                    <label>USDT Address</label>
                    <input type="text" name="usdt_address" id="usdt_address">
                </div>
                <div class="modal-row">
                    <label>Network</label>
                    <select name="usdt_network" id="usdt_network">
                        <option value="">Select Network</option>
                        <option value="TRC20">TRC20</option>
                        <option value="ERC20">ERC20</option>
                        <option value="BEP20">BEP20</option>
                        <option value="Polygon">Polygon</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="modal-row">
                    <label>Status</label>
                    <select name="crypto_status" id="crypto_status">
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Incomplete">Incomplete</option>
                    </select>
                </div>
                <div class="modal-row">
                    <label>USDT QR Image (PNG / JPG / WEBP)</label>
                    <input type="file" name="crypto_qr" id="crypto_qr" accept="image/*">
                    <div class="modal-hint">Naya QR upload karne par purana auto replace ho jayega.</div>
                </div>
                <div class="qr-preview" id="cryptoQrPreviewBox" style="display:none;">
                    <div style="font-size:11px;color:#555;margin-bottom:4px;">Current QR</div>
                    <img id="cryptoQrPreviewImg" src="" alt="USDT QR">
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-cancel" data-close="cryptoKycModal">Cancel</button>
                    <button type="submit" class="modal-btn modal-submit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- UPI Mobile Number Modal -->
    <div class="modal-backdrop" id="upiNumModal">
        <div class="modal-box">
            <div class="modal-title" id="upiModalTitle">Edit UPI Number</div>
            <form method="post">
                <input type="hidden" name="upi_num_save" value="1">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <input type="hidden" name="upi_type" id="upi_type">
                <div class="modal-row">
                    <label>UPI App</label>
                    <input type="text" id="upi_label" disabled>
                </div>
                <div class="modal-row">
                    <label>Mobile Number</label>
                    <input type="text" name="upi_number" id="upi_number" maxlength="10">
                    <div class="modal-hint">Blank rehne par bhi Save kar sakte ho.</div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-cancel" data-close="upiNumModal">Cancel</button>
                    <button type="submit" class="modal-btn modal-submit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        var filterForm = document.getElementById('filterForm');
        var chips = document.querySelectorAll('.filter-chip');
        var rangeInput = document.getElementById('rangeInput');
        var fromInput = document.getElementById('fromInput');
        var toInput = document.getElementById('toInput');
        var customBox = document.getElementById('customRangeBox');
        var customFrom = document.getElementById('customFrom');
        var customTo = document.getElementById('customTo');
        var applyCustomBtn = document.getElementById('applyCustomBtn');

        function updateCustomVisibility() {
            customBox.style.display = (rangeInput.value === 'custom') ? 'inline-flex' : 'none';
        }
        updateCustomVisibility();

        chips.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var r = btn.dataset.range;
                chips.forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                rangeInput.value = r;
                if (r === 'custom') {
                    customBox.style.display = 'inline-flex';
                } else {
                    fromInput.value = ''; toInput.value = '';
                    filterForm.submit();
                }
            });
        });

        applyCustomBtn.addEventListener('click', function() {
            if (!customFrom.value || !customTo.value) { alert('Select dates'); return; }
            rangeInput.value = 'custom';
            fromInput.value = customFrom.value;
            toInput.value = customTo.value;
            filterForm.submit();
        });

        /* Wallet Modal */
        var walletModal = document.getElementById('walletModal');
        var openDepBtn = document.getElementById('openDepBtn');
        var openWithBtn = document.getElementById('openWithBtn');
        var walletCancel = document.getElementById('walletCancel');
        var walletAction = document.getElementById('walletAction');
        var walletTitle = document.getElementById('walletModalTitle');
        var walletAmount = document.getElementById('walletAmount');
        var walletValNote = document.getElementById('walletValNote');
        var walletSubmitBtn = document.getElementById('walletSubmitBtn');

        var MIN_DEPOSIT = <?= (float) $minDep ?>;
        var MIN_WITHDRAW = <?= (float) $minWith ?>;
        var USER_TOTAL_BALANCE = <?= (float) max(0, (float)($u['wallet_balance'] ?? $walletBalanceAfterBid ?? 0)) ?>;

        function setWalletValidationUI(type) {
            var min = (type === 'deposit') ? MIN_DEPOSIT : MIN_WITHDRAW;
            walletAmount.setAttribute('min', String(min));
            if (type === 'withdrawal') {
                walletAmount.setAttribute('max', String(USER_TOTAL_BALANCE));
            } else {
                walletAmount.removeAttribute('max');
            }
            walletValNote.textContent = (type === 'deposit')
                ? `Minimum deposit ₹${min}`
                : `Minimum withdrawal ₹${min} | Max Limit ₹${USER_TOTAL_BALANCE.toFixed(2)}`;
            walletValNote.classList.add('show');
        }

        function validateWalletAmount() {
            var type = walletAction.value;
            var min = (type === 'deposit') ? MIN_DEPOSIT : MIN_WITHDRAW;
            var val = parseFloat(walletAmount.value || '0');

            if (!walletAmount.value) {
                walletAmount.classList.remove('input-error');
                walletSubmitBtn.disabled = false;
                return;
            }

            if (isNaN(val) || val < min) {
                walletAmount.classList.add('input-error');
                walletSubmitBtn.disabled = true;
                walletValNote.textContent = `Minimum ${type === 'deposit' ? 'deposit' : 'withdrawal'} ₹${min}`;
                walletValNote.classList.add('show');
            } else if (type === 'withdrawal' && val > USER_TOTAL_BALANCE) {
                walletAmount.classList.add('input-error');
                walletSubmitBtn.disabled = true;
                walletValNote.textContent = `Limit Exceeded! Max limit: ₹${USER_TOTAL_BALANCE.toFixed(2)}`;
                walletValNote.classList.add('show');
            } else {
                walletAmount.classList.remove('input-error');
                walletSubmitBtn.disabled = false;
                walletValNote.textContent = (type === 'deposit')
                    ? `Minimum deposit ₹${min}`
                    : `Minimum withdrawal ₹${min} | Max Limit ₹${USER_TOTAL_BALANCE.toFixed(2)}`;
                walletValNote.classList.add('show');
            }
        }

        function openWalletModal(type) {
            walletAction.value = type;
            walletAmount.value = '';
            walletSubmitBtn.disabled = false;
            walletAmount.classList.remove('input-error');

            walletTitle.textContent = (type === 'deposit')
                ? 'Deposit (Min ₹' + MIN_DEPOSIT + ')'
                : 'Withdrawal (Limit ₹' + USER_TOTAL_BALANCE.toFixed(2) + ')';

            setWalletValidationUI(type);

            walletModal.classList.add('show');
            setTimeout(function() { return walletAmount.focus(); }, 50);
        }
        openDepBtn.addEventListener('click', function() { return openWalletModal('deposit'); });
        openWithBtn.addEventListener('click', function() { return openWalletModal('withdrawal'); });
        walletCancel.addEventListener('click', function() { return walletModal.classList.remove('show'); });
        walletModal.addEventListener('click', function(e) {
            if (e.target.id === 'walletModal') walletModal.classList.remove('show');
        });
        walletAmount.addEventListener('input', validateWalletAmount);

        /* Form Submit */
        var walletForm = document.getElementById('walletForm');

        walletForm.addEventListener('submit', function (e) {
            var type = walletAction.value;
            var val = parseFloat(walletAmount.value || '0');

            if (type === 'withdrawal' && val > USER_TOTAL_BALANCE) {
                e.preventDefault();
                walletSubmitBtn.disabled = false;
                walletSubmitBtn.innerHTML = 'Submit';
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: '⚠️ Insufficient Balance!',
                        html: `
                            <div style="text-align: left; font-size: 15px; line-height: 1.8;">
                                <p style="margin-bottom: 8px; color: #ff6b6b; font-weight: bold;">
                                    Withdrawal amount Total Balance se zyada hai!
                                </p>
                                <div style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                                    <div><b>Real Total Balance:</b> <span style="color: #4cd137; font-weight: bold;">₹${USER_TOTAL_BALANCE.toFixed(2)}</span></div>
                                    <div><b>Max Withdrawal Limit:</b> <span style="color: #00d2d3; font-weight: bold;">₹${USER_TOTAL_BALANCE.toFixed(2)}</span></div>
                                    <div><b>Requested Amount:</b> <span style="color: #ff4757; font-weight: bold;">₹${val.toFixed(2)}</span></div>
                                </div>
                            </div>
                        `,
                        icon: 'error',
                        confirmButtonText: 'OK, Understood',
                        confirmButtonColor: '#e74c3c',
                        background: '#1c1427',
                        color: '#fff'
                    });
                } else {
                    alert('Insufficient Balance!\n\nReal Total Balance: ₹' + USER_TOTAL_BALANCE.toFixed(2) + '\nMax Limit: ₹' + USER_TOTAL_BALANCE.toFixed(2) + '\nRequested: ₹' + val.toFixed(2));
                }
                return false;
            }

            // Hide modal & show spinner while browser submits form and reloads page
            if (walletModal) {
                walletModal.classList.remove('show');
            }
            walletSubmitBtn.disabled = true;
            walletSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });

        /* Profile Edit */
        var profileModal = document.getElementById('profileModal');
        var editProfileBtn = document.getElementById('editProfileBtn');
        var profileCancel = document.getElementById('profileCancel');
        var profileSaveBtn = document.getElementById('profileSaveBtn');
        var editName = document.getElementById('editName');
        var editMobile = document.getElementById('editMobile');
        var editEmail = document.getElementById('editEmail');
        var editPassword = document.getElementById('editPassword');

        var pData = {
            id: <?= (int) $id ?>,
            name: <?= json_encode($u['name'] ?? '') ?>,
            mobile: <?= json_encode($u['mobile_no'] ?? '') ?>,
            email: <?= json_encode($u['email'] ?? '') ?>,
            password: <?= json_encode($u['password'] ?? '') ?>
        };

        editProfileBtn.addEventListener('click', function() {
            editName.value = pData.name;
            editMobile.value = pData.mobile;
            editEmail.value = pData.email;
            editPassword.value = pData.password;
            profileModal.classList.add('show');
        });
        profileCancel.addEventListener('click', function() { return profileModal.classList.remove('show'); });
        profileModal.addEventListener('click', function(e) {
            if (e.target.id === 'profileModal') profileModal.classList.remove('show');
        });
        editMobile.addEventListener('input', function() {
            editMobile.value = editMobile.value.replace(/[^0-9]/g, '').slice(0, 10);
        });

        profileSaveBtn.addEventListener('click', function() {
            var n = editName.value.trim();
            var m = editMobile.value.trim();
            var e = editEmail.value.trim();
            var p = editPassword.value.trim();
            if (!n || !/^\d{10}$/.test(m) || !p) { alert('Invalid data'); return; }

            var fd = new URLSearchParams();
            fd.append('profile_edit_ajax', '1');
            fd.append('id', pData.id);
            fd.append('name', n);
            fd.append('mobile_no', m);
            fd.append('email', e);
            fd.append('password', p);

            fetch('bio_data.php?id=' + pData.id, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: fd.toString()
            }).then(r => r.json()).then(function(j) {
                if (j.ok) { alert('Updated'); location.reload(); }
                else { alert(j.msg || 'Error'); }
            });
        });

        /* KYC JS DATA */
        var bankKycData = <?= json_encode($bankKycJs); ?>;
        var upiKycData = <?= json_encode($upiKycJs); ?>;
        var cryptoKycData = <?= json_encode($cryptoKycJs); ?>;

        /* Bank KYC Modal control */
        var bankKycModal = document.getElementById('bankKycModal');
        var editBankKycBtn = document.getElementById('editBankKycBtn');

        editBankKycBtn.addEventListener('click', function() {
            document.getElementById('bank_name').value = bankKycData.bank_name || '';
            document.getElementById('account_name').value = bankKycData.account_name || '';
            document.getElementById('account_number').value = bankKycData.account_number || '';
            document.getElementById('ifsc').value = bankKycData.ifsc || '';
            document.getElementById('branch').value = bankKycData.branch || '';
            document.getElementById('bank_status').value = bankKycData.status || 'Incomplete';
            bankKycModal.classList.add('show');
        });

        /* UPI KYC Modal control */
        var upiKycModal = document.getElementById('upiKycModal');
        var editUpiKycBtn = document.getElementById('editUpiKycBtn');
        var qrPreviewBox = document.getElementById('qrPreviewBox');
        var qrPreviewImg = document.getElementById('qrPreviewImg');

        editUpiKycBtn.addEventListener('click', function() {
            document.getElementById('upi_id').value = upiKycData.upi_id || '';
            document.getElementById('upi_status').value = upiKycData.status || 'Incomplete';
            document.getElementById('current_qr').value = upiKycData.qr_image || '';
            if (upiKycData.qr_image) {
                qrPreviewImg.src = upiKycData.qr_image;
                qrPreviewBox.style.display = 'block';
            } else {
                qrPreviewBox.style.display = 'none';
            }
            upiKycModal.classList.add('show');
        });

        /* Crypto (USDT) KYC Modal control */
        var cryptoKycModal = document.getElementById('cryptoKycModal');
        var editCryptoKycBtn = document.getElementById('editCryptoKycBtn');
        var cryptoQrPreviewBox = document.getElementById('cryptoQrPreviewBox');
        var cryptoQrPreviewImg = document.getElementById('cryptoQrPreviewImg');

        if (editCryptoKycBtn) {
            editCryptoKycBtn.addEventListener('click', function() {
                document.getElementById('usdt_address').value = cryptoKycData.address || '';
                document.getElementById('usdt_network').value = cryptoKycData.network || '';
                document.getElementById('crypto_status').value = cryptoKycData.status || 'Incomplete';
                document.getElementById('current_crypto_qr').value = cryptoKycData.qr_image || '';
                if (cryptoKycData.qr_image) {
                    cryptoQrPreviewImg.src = cryptoKycData.qr_image;
                    cryptoQrPreviewBox.style.display = 'block';
                } else {
                    cryptoQrPreviewBox.style.display = 'none';
                }
                cryptoKycModal.classList.add('show');
            });
        }

        /* UPI Numbers Modal control */
        var upiNumModal = document.getElementById('upiNumModal');
        var upiNumberInput = document.getElementById('upi_number');
        var upiTypeInput = document.getElementById('upi_type');
        var upiLabelInput = document.getElementById('upi_label');

        document.querySelectorAll('.upi-edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var t = btn.dataset.upiType;
                var lb = btn.dataset.upiLabel;
                var num = btn.dataset.upiNumber || '';
                upiTypeInput.value = t;
                upiLabelInput.value = lb;
                upiNumberInput.value = num;
                upiNumModal.classList.add('show');
            });
        });

        /* close buttons for KYC modals */
        document.querySelectorAll('.modal-btn.modal-cancel[data-close]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-close');
                var el = document.getElementById(id);
                if (el) el.classList.remove('show');
            });
        });

        /* backdrop click to close */
        [
            bankKycModal,
            upiKycModal,
            cryptoKycModal,
            upiNumModal
        ].filter(Boolean).forEach(function(mod) {
            mod.addEventListener('click', function(e) {
                if (e.target === mod) { mod.classList.remove('show'); }
            });
        });

        /* Restrict digits only for UPI mobile input */
        upiNumberInput.addEventListener('input', function() {
            upiNumberInput.value = upiNumberInput.value.replace(/[^0-9]/g, '').slice(0, 10);
        });

        /* ===== ACTIVE / DEACTIVE TOGGLE BUTTON (AJAX) ===== */
        var toggleActiveBtn = document.getElementById('toggleActiveBtn');
        var accStatePill = document.getElementById('accStatePill');
        var accStateNote = document.getElementById('accStateNote');
        var topAccBadge = document.getElementById('topAccBadge');

        if (toggleActiveBtn) {
            toggleActiveBtn.addEventListener('click', function() {
                var current = toggleActiveBtn.dataset.current === '1' ? 1 : 0;
                var next = current === 1 ? 0 : 1;

                if (!confirm(next === 0 ? 'Account Deactivate karein?' : 'Account Activate karein?')) {
                    return;
                }

                var fd = new URLSearchParams();
                fd.append('toggle_active_ajax', '1');
                fd.append('id', <?= (int) $id ?>);
                fd.append('new_state', next);

                fetch('bio_data.php?id=<?= (int) $id ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: fd.toString()
                }).then(r => r.json()).then(function(j) {
                    if (!j.ok) {
                        alert(j.msg || 'Update failed');
                        return;
                    }
                    var state = j.state === 1 ? 1 : 0;
                    toggleActiveBtn.dataset.current = state.toString();

                    if (state === 1) {
                        toggleActiveBtn.innerHTML = '<i class="fas fa-user-slash"></i> Deactivate Account';
                    } else {
                        toggleActiveBtn.innerHTML = '<i class="fas fa-user-check"></i> Activate Account';
                    }

                    accStatePill.textContent = state === 1 ? 'Account Active' : 'Account Deactive';
                    accStatePill.classList.remove('acc-pill-active', 'acc-pill-deactive');
                    accStatePill.classList.add(state === 1 ? 'acc-pill-active' : 'acc-pill-deactive');
                    accStateNote.textContent = state === 1
                        ? 'User can login & play.'
                        : 'Login blocked – bids not allowed.';


                }).catch(function(e) {
                    alert('Network error');
                });
            });
        }

        function copyMobileNo(text, btn) {
            if (!text) return;
            function showSuccess() {
                if (!btn) return;
                var icon = btn.querySelector('i');
                if (icon) {
                    var oldClass = icon.className;
                    var oldColor = icon.style.color;
                    icon.className = 'fas fa-check';
                    icon.style.color = '#2e7d32';
                    setTimeout(function() {
                        icon.className = oldClass;
                        icon.style.color = oldColor;
                    }, 1500);
                }
            }
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(showSuccess).catch(function() {
                    fallbackCopy(text, showSuccess);
                });
            } else {
                fallbackCopy(text, showSuccess);
            }
        }
        function fallbackCopy(text, cb) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                if (cb) cb();
            } catch (e) {}
            document.body.removeChild(ta);
        }

            function deleteBidAjax(bidId, src, userId, amount, bidTime) {
            if (!confirm('Are you sure you want to delete this bid?')) return;
            var fd = new FormData();
            fd.append('delete_bid_ajax', '1');
            fd.append('bid_id', bidId);
            fd.append('src', src);
            fd.append('user_id', userId);
            fd.append('amount', amount);
            fd.append('bid_time', bidTime);
            
            fetch(window.location.href, {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    location.reload();
                } else {
                    alert(data.msg || 'Failed to delete bid');
                }
            })
            .catch(err => {
                alert('Network error');
            });
        }
    </script>
</body>

</html>
<?php
    $db->close(); 
} catch (Throwable $globalE) {
    die('BioData Exception occurred: ' . htmlspecialchars($globalE->getMessage()) . ' in ' . $globalE->getFile() . ' on line ' . $globalE->getLine());
}







