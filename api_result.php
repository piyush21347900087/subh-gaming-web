<?php
/**
 * api_result.php - CLIENT SIDE RECEIVER
 * Handlers: Sync results, Test connectivity, Trigger wins.
 */
require_once 'includes/db.php';

if (!defined('GOOGLE_SA_JSON')) {
    define('GOOGLE_SA_JSON', __DIR__ . '/secure/service-account.json');
}

require_once 'includes/fcm_helper.php';

if (!defined('PUSH_LOGO_URL')) {
    define('PUSH_LOGO_URL', 'https://subhgaming.online/images/logo.png');
}
if (!defined('PUSH_TOPIC')) {
    define('PUSH_TOPIC', 'all_subhgaming');
}

function ll_format_result(string $market, string $res): string
{
    if (preg_match('/^\*{3}-([0-9*]{2})-\*{3}$/', $res, $matches)) {
        return $matches[1];
    }
    return $res;
}

header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
require_once __DIR__ . '/includes/api_keys.php';
if ($key !== SUBH_RESULT_SYNC_KEY && $key !== 'LL_SYNC_2025') {
    die(json_encode(['ok' => false, 'error' => 'Invalid Key']));
}

$messages = [];
$db = db();

// 1. TEST SIGNAL
if (isset($_GET['test'])) {
    if (defined('GOOGLE_SA_JSON') && file_exists(GOOGLE_SA_JSON)) {
        $tok = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
        if ($tok) {
            $data = [
                'type' => 'test_notify',
                'title' => "SUBH GAMING - Connection Test",
                'body' => "Client side API is working and triggered this notification.",
                'image' => PUSH_LOGO_URL,
                'picture' => PUSH_LOGO_URL
            ];
            // Send to topic
            fcm_v1_send_topic(FCM_PROJECT_ID, $tok, $data, 'all_subhgaming');
            $messages[] = 'Test Notification triggered';
        }
    }
}

// 2. TRIGGER WINS / NOTIFICATIONS
if (isset($_GET['trigger_wins'])) {
    $market = $_GET['market'] ?? '';
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Call local win computation (Background ping)
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $winUrl = $protocol."://".$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])."/fetch_wins_api.php?trigger=1&market=".urlencode($market)."&from=".urlencode($date)."&to=".urlencode($date)."&key=LL_ADMIN_WINS_2025";
    
    $ch = curl_init($winUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    @curl_exec($ch);
    curl_close($ch);
    
    $messages[] = 'Wins computation triggered locally';
}

// 3. LIVE RESULT SYNC & BROADCAST NOTIFICATION
if (isset($_GET['market']) && isset($_GET['result'])) {
    $m = strtoupper(trim($_GET['market']));
    $r = trim($_GET['result']);
    $d = $_GET['date'] ?? date('Y-m-d');
    $mtype = strtolower(trim($_GET['mtype'] ?? ''));

    // Auto-detect gali type if mtype is empty but market exists in gali_markets
    if (empty($mtype)) {
        $chkG = $db->prepare("SELECT id FROM gali_markets WHERE UPPER(TRIM(market_name)) = ? LIMIT 1");
        $chkG->bind_param('s', $m);
        $chkG->execute();
        if ($chkG->get_result()->num_rows > 0) {
            $mtype = 'gali';
        }
        $chkG->close();
    }

    if ($mtype === 'gali') {
        $checkM = $db->prepare("SELECT id FROM gali_markets WHERE UPPER(TRIM(market_name)) = ? AND is_published = 1");
        $checkM->bind_param("s", $m);
        $checkM->execute();
        if ($checkM->get_result()->num_rows === 0) {
            die(json_encode(['ok' => false, 'error' => 'Gali Market not allowed or inactive']));
        }
        $checkM->close();

        $st = $db->prepare("INSERT INTO gali_result (market, result_date, jodi, result_str, declared_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE jodi=VALUES(jodi), result_str=VALUES(result_str), declared_at=NOW()");
        $st->bind_param('ssss', $m, $d, $r, $r);

        if ($st->execute()) {
            $messages[] = "Gali Result synced for $m";

            // BROADCAST NOTIFICATION
            if (defined('GOOGLE_SA_JSON') && file_exists(GOOGLE_SA_JSON)) {
                $tok = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
                if ($tok) {
                    $dataNotif = [
                        'type' => 'market_result',
                        'title' => $m,
                        'message' => $r,
                        'market' => $m,
                        'market_color' => '#0066cc',
                        'notification_color' => '#0066cc',
                        'title_html' => '<font color="#0066cc">' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</font>',
                        'result' => $r,
                        'logo' => PUSH_LOGO_URL,
                        'icon' => 'ic_notification',
                        'uniq' => $m . '_' . $d . '_GALI_SYNC_' . time()
                    ];
                    fcm_v1_send_topic(FCM_PROJECT_ID, $tok, $dataNotif, PUSH_TOPIC);
                    $messages[] = "Broadcast notification sent";
                }
            }

            // TRIGGER WINS CALCULATION AND NOTIFICATION
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $winUrl = $protocol."://".$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])."/fetch_wins_api.php?trigger=1&market=".urlencode($m)."&from=".urlencode($d)."&to=".urlencode($d)."&key=LL_ADMIN_WINS_2025";
            $ch = curl_init($winUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            @curl_exec($ch);
            curl_close($ch);

            $notifyUrl = $protocol."://".$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])."/notify_wins.php?market=".urlencode($m)."&from=".urlencode($d)."&to=".urlencode($d)."&session=both&key=LL_ADMIN_NOTIFY_2026";
            $ch2 = curl_init($notifyUrl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            @curl_exec($ch2);
            curl_close($ch2);
            $messages[] = "Wins and notifications triggered";

        } else {
            $messages[] = "DB Error: " . $st->error;
        }
        $st->close();
    } else {
        // ✅ CHECK IF MARKET IS ALLOWED
        $checkM = $db->prepare("SELECT id FROM markets WHERE UPPER(TRIM(market_name)) = ? AND is_published = 1");
        $checkM->bind_param("s", $m);
        $checkM->execute();
        if ($checkM->get_result()->num_rows === 0) {
            die(json_encode(['ok' => false, 'error' => 'Market not allowed or inactive']));
        }
        $checkM->close();
        
        // Parse result string (Pana-Digit-Pana)
        $parts = explode('-', $r);
        if (count($parts) == 3) {
            $op = $parts[0];
            $digits = $parts[1]; 
            $cp = $parts[2];
            $od = $digits[0] ?? '*';
            $cd = $digits[1] ?? '*';
            
            $st = $db->prepare("INSERT INTO market_result (market, result_date, open_pana, open_digit, close_digit, close_pana, result_str, declared_at) VALUES (?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE open_pana=VALUES(open_pana), open_digit=VALUES(open_digit), close_digit=VALUES(close_digit), close_pana=VALUES(close_pana), result_str=VALUES(result_str), declared_at=NOW()");
            $st->bind_param('sssssss', $m, $d, $op, $od, $cd, $cp, $r);
            
            if ($st->execute()) {
                $messages[] = "Result synced for $m";

                // BROADCAST NOTIFICATION
                if (defined('GOOGLE_SA_JSON') && file_exists(GOOGLE_SA_JSON)) {
                    $tok = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
                    if ($tok) {
                        $fmtRs = ll_format_result($m, $r);
                        $phaseSession = ($cp === '***') ? 'open' : 'close';

                        $dataAll = [
                            'type' => 'market_result',
                            'title' => $m,
                            'message' => $fmtRs,
                            'market' => $m,
                            'market_color' => '#0066cc',
                            'notification_color' => '#0066cc',
                            'title_html' => '<font color="#0066cc">' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</font>',
                            'result' => $fmtRs,
                            'logo' => PUSH_LOGO_URL,
                            'icon' => 'ic_notification',
                            'uniq' => $m . '_' . $d . '_RESULT_' . $phaseSession
                        ];
                        fcm_v1_send_topic(FCM_PROJECT_ID, $tok, $dataAll, PUSH_TOPIC);
                        $messages[] = "Broadcast notification sent";
                    }
                }

                // TRIGGER WINS CALCULATION AND NOTIFICATION
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $winUrl = $protocol."://".$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])."/fetch_wins_api.php?trigger=1&market=".urlencode($m)."&from=".urlencode($d)."&to=".urlencode($d)."&key=LL_ADMIN_WINS_2025";
                $ch = curl_init($winUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                @curl_exec($ch);
                curl_close($ch);

                $notifyUrl = $protocol."://".$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])."/notify_wins.php?market=".urlencode($m)."&from=".urlencode($d)."&to=".urlencode($d)."&session=both&key=LL_ADMIN_NOTIFY_2026";
                $ch2 = curl_init($notifyUrl);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                @curl_exec($ch2);
                curl_close($ch2);
                $messages[] = "Wins and notifications triggered";

            } else {
                $messages[] = "DB Error: " . $st->error;
            }
            $st->close();
        } else {
            $messages[] = "Invalid Result Format";
        }
    }
}

echo json_encode(['ok' => true, 'actions' => $messages]);





