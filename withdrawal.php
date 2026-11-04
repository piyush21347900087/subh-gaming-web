<?php
require_once 'includes/lang.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'includes/db.php';

/* ✅ No-cache (WebView friendly) */
header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/* ✅ Debugging: Enable errors temporarily to fix 500 issue */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* Safe Include Wrapper to prevent 500 if files missing */
if (file_exists('includes/user_ping.php'))
  require_once 'includes/user_ping.php';
if (file_exists('includes/live_ping.php'))
  require_once 'includes/live_ping.php';

$isDeactive = (isset($_SESSION['user']['is_active']) && (int) $_SESSION['user']['is_active'] === 0);

if (!isset($_SESSION['user']['id'])) {
  header('Location: user_login.php');
  exit;
}

date_default_timezone_set('Asia/Kolkata');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = db();
} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  echo '<h3>Database Connection Error</h3>';
  echo '<p>Please check your database credentials in deposit.php</p>';
  echo '<p>Error: ' . $e->getMessage() . '</p>';
  exit;
}

$uid = (int) $_SESSION['user']['id'];

/* ===================== LIVE REAL WITHDRAWALS POLLING ENDPOINT ===================== */
if (isset($_GET['get_new_withdrawals'])) {
    header('Content-Type: application/json');
    $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    $newWithdrawals = [];
    try {
        $minWdPoll = 1000;
        $resMinP = $db->query("SELECT min_withdraw FROM admin_payment_details WHERE id=1 LIMIT 1");
        if ($resMinP && $rowMP = $resMinP->fetch_assoc()) {
            $minWdPoll = max(1, (int)$rowMP['min_withdraw']);
        }

        $rwNames = [];
        $rwRes = $db->query("SELECT name FROM recent_winners LIMIT 300");
        if ($rwRes) { while ($r = $rwRes->fetch_assoc()) { $rwNames[] = $r['name']; } }

        $stmt = $db->prepare("
            SELECT w.id, w.amount, w.date_time 
            FROM withdrawal w 
            JOIN users u ON w.user_id = u.user_id 
            WHERE LOWER(w.status) IN ('done', 'approved') AND w.amount >= ? AND w.id > ? 
            ORDER BY w.id ASC 
            LIMIT 10
        ");
        $stmt->bind_param('ii', $minWdPoll, $lastId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $displayName = (!empty($rwNames)) ? $rwNames[array_rand($rwNames)] : 'User';
            
            $newWithdrawals[] = [
                'id' => (int)$row['id'],
                'name' => $displayName,
                'amount' => '-₹' . number_format($row['amount'], 0, '', ','),
                'time' => __("just now")
            ];
        }
    } catch (Exception $e) {
        // Fail silently
    }
    echo json_encode($newWithdrawals);
    exit;
}

/* ✅ DATABASE SETUP - RUNS ONLY IF NECESSARY OR MANUALLY */
// (Schema checks moved to separate setup script for performance)

/* ✅ AUTO-REJECT AGED PENDING TRANSACTIONS (3 MINUTES = 180 SECOND TIMEOUT) 
   ONLY for automated gateways (IMB, Pay0, Intent, YuviPay) */
try {
  $qCleanup = $db->prepare("
      UPDATE deposit 
      SET status='Failed' 
      WHERE user_id=(SELECT user_id FROM users WHERE id=?)
        AND status='Pending' 
        AND credited = 0
        AND method IN ('IMB', 'UPI ZERO', 'UPI_INTENT', 'UPI Intent', 'UPI YUVI')
        AND date_time < (NOW() - INTERVAL 180 SECOND)
  ");
  $qCleanup->bind_param('i', $uid);
  $qCleanup->execute();
  $qCleanup->close();
} catch (Exception $e) {
}

/* ===================== HELPERS ===================== */
function fetch_one(mysqli $db, string $sql, string $types = '', array $params = []): ?string
{
  $stmt = $db->prepare($sql);
  if ($types !== '')
    $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $stmt->bind_result($val);
  $out = $stmt->fetch() ? $val : null;
  $stmt->close();
  return $out;
}

function ensure_bonus_rate_table(mysqli $db): void
{
  $db->query("CREATE TABLE IF NOT EXISTS bonus_rate (
    id INT AUTO_INCREMENT PRIMARY KEY,
    min_amount DECIMAL(12,2) NOT NULL UNIQUE,
    bonus_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $db->query("CREATE TABLE IF NOT EXISTS bonus_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    bonus_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $db->query("INSERT IGNORE INTO bonus_settings (id, bonus_enabled) VALUES (1, 1)");

  $count = (int) ($db->query("SELECT COUNT(*) FROM bonus_rate")->fetch_row()[0] ?? 0);
  if ($count === 0) {
    $defaults = [[1000, 150], [2000, 400], [5000, 2000], [10000, 4000], [20000, 6000], [30000, 15000]];
    $stmt = $db->prepare("INSERT INTO bonus_rate (min_amount, bonus_amount, is_active) VALUES (?, ?, 1)");
    foreach ($defaults as $row) {
      $min = (float) $row[0];
      $bonus = (float) $row[1];
      $stmt->bind_param('dd', $min, $bonus);
      $stmt->execute();
    }
    $stmt->close();
  }
}

function get_bonus_amount(mysqli $db, float $amount): float
{
  try {
    $enabled = (int) ($db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1")->fetch_row()[0] ?? 1);
    if ($enabled !== 1) {
      return 0.0;
    }
    $stmt = $db->prepare("SELECT bonus_amount FROM bonus_rate WHERE is_active=1 AND min_amount <= ? ORDER BY min_amount DESC LIMIT 1");
    $stmt->bind_param('d', $amount);
    $stmt->execute();
    $stmt->bind_result($bonus);
    $out = $stmt->fetch() ? (float) $bonus : 0.0;
    $stmt->close();
    return $out;
  } catch (Throwable $e) {
    return 0.0;
  }
}

function get_bonus_rates_for_js(mysqli $db): array
{
  try {
    $enabled = (int) ($db->query("SELECT bonus_enabled FROM bonus_settings WHERE id=1 LIMIT 1")->fetch_row()[0] ?? 1);
    if ($enabled !== 1) {
      return [];
    }
    $rows = [];
    $res = $db->query("SELECT min_amount, bonus_amount FROM bonus_rate WHERE is_active=1 ORDER BY min_amount ASC");
    while ($res && $row = $res->fetch_assoc()) {
      $rows[] = ['min' => (float) $row['min_amount'], 'bonus' => (float) $row['bonus_amount']];
    }
    return $rows;
  } catch (Throwable $e) {
    return [];
  }
}

try {
  ensure_bonus_rate_table($db);
} catch (Throwable $e) {
}

/**
 * ✅ Show correct method from deposit.method enum
 * ✅ UPDATED: Add REFUND support
 */
function displayMethod($m): string
{
  $raw = trim((string) $m);
  if ($raw === '')
    return 'Admin';

  $upper = strtoupper($raw);
  $lower = strtolower($raw);

  // DB enum values (primary)
  $enumMap = [
    'BANK' => 'Bank',
    'UPI' => 'UPI',
    'UPI_INTENT' => 'UPI Intent',
    'CRYPTO' => 'USDT',
    'ADMIN' => 'Admin',
    'REFUND' => 'Refund',     // ✅ NEW
    'IMB' => 'IMB Payment',
    'UPI YUVI' => 'UPI YUVI',
  ];
  if (isset($enumMap[$upper]))
    return $enumMap[$upper];

  // Backward / alternate strings
  $altMap = [
    'upi intent' => 'UPI Intent',
    'upi_intent' => 'UPI Intent',
    'intent' => 'UPI Intent',
    'phonepe' => 'PhonePe',
    'gpay' => 'Google Pay',
    'google pay' => 'Google Pay',
    'paytm' => 'Paytm',
    'bhim' => 'BHIM',
    'upi_qr' => 'UPI',
    'upi qr' => 'UPI',
    'usdt' => 'USDT',
    'refund' => 'Refund',      // ✅ NEW
    'imb' => 'IMB Payment',
    'upi yuvi' => 'UPI YUVI',
    'yuvipay' => 'UPI YUVI',
  ];
  if (isset($altMap[$lower]))
    return $altMap[$lower];

  return ucfirst($lower);
}

/* ===================== USER CODE/NAME ===================== */
$rawUserId = fetch_one($db, 'SELECT user_id FROM users WHERE id = ?', 'i', [$uid]) ?: '';
$code = $rawUserId;
if (preg_match('/^\d+$/', $code)) {
  $code = "KR" . str_pad($code, 2, "0", STR_PAD_LEFT);
} elseif (preg_match('/^(BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|KR)(\d+)$/', $code, $m)) {
  $code = $m[1] . str_pad($m[2], 2, "0", STR_PAD_LEFT);
}
$userName = $_SESSION['user']['name'] ?? '';
if ($userName === '') {
  $userName = fetch_one($db, 'SELECT name FROM users WHERE id = ?', 'i', [$uid]) ?: $code;
}
$userMobile = fetch_one($db, 'SELECT mobile_no FROM users WHERE id = ?', 'i', [$uid]) ?: '';
$userCreatedAt = fetch_one($db, 'SELECT created_at FROM users WHERE id = ?', 'i', [$uid]) ?: '';
$userSince = $userCreatedAt ? date('d-m-Y', strtotime($userCreatedAt)) : '';

/* ===================== REAL WITHDRAWALS FOR LIVE FEED ===================== */
$realWithdrawals = [];
$maxWithdrawalId = 0;
try {
  $minWdFeed = 1000;
  $resMinF = $db->query("SELECT min_withdraw FROM admin_payment_details WHERE id=1 LIMIT 1");
  if ($resMinF && $rowMF = $resMinF->fetch_assoc()) {
    $minWdFeed = max(1, (int)$rowMF['min_withdraw']);
  }

  $rwNames = [];
  $rwRes = $db->query("SELECT name FROM recent_winners LIMIT 300");
  if ($rwRes) { while ($r = $rwRes->fetch_assoc()) { $rwNames[] = $r['name']; } }

  $stmtReal = $db->prepare("
    SELECT w.id, w.amount, w.date_time 
    FROM withdrawal w 
    JOIN users u ON w.user_id = u.user_id 
    WHERE LOWER(w.status) IN ('done', 'approved') AND w.amount >= ?
    ORDER BY w.id DESC 
    LIMIT 20
  ");
  $stmtReal->bind_param('i', $minWdFeed);
  $stmtReal->execute();
  $resReal = $stmtReal->get_result();
  if ($resReal) {
    while ($row = $resReal->fetch_assoc()) {
      if ((int)$row['id'] > $maxWithdrawalId) {
        $maxWithdrawalId = (int)$row['id'];
      }
      $displayName = (!empty($rwNames)) ? $rwNames[array_rand($rwNames)] : 'User';
      
      $diffSeconds = time() - strtotime($row['date_time']);
      if ($diffSeconds < 60) {
        $timeStr = __("just now");
      } else if ($diffSeconds < 300) {
        $mins = floor($diffSeconds / 60);
        $timeStr = $mins . " " . __("min ago");
      } else {
        $timeStr = rand(1, 5) . " " . __("min ago");
      }

      $realWithdrawals[] = [
        'name' => $displayName,
        'amount' => '-₹' . number_format($row['amount'], 0, '', ','),
        'time' => $timeStr
      ];
    }
  }
} catch (Exception $e) {
  // Fail silently
}

/* ===================== KYC STATUS ===================== */
function latestStatus(mysqli $db, string $table, string $userId): string
{
  try {
    $sql = "SELECT status FROM {$table} WHERE user_id=? ORDER BY id DESC LIMIT 1";
    $st = $db->prepare($sql);
    $st->bind_param('s', $userId);
    $st->execute();
    $st->bind_result($status);
    $ok = $st->fetch();
    $st->close();
    return $ok ? (string) $status : 'Incomplete';
  } catch (Exception $e) {
    return 'Incomplete';
  }
}

$bankStatus = latestStatus($db, 'bank_kyc', $rawUserId);
$upiStatus = latestStatus($db, 'upi_kyc', $rawUserId);
$cryptoStatus = 'Incomplete';
try {
  $cryptoStatus = latestStatus($db, 'crypto_kyc', $rawUserId);
} catch (Exception $e) {
  $cryptoStatus = 'Incomplete';
}

/* linked UPI apps */
$linkedApps = ['phonepe' => false, 'gpay' => false, 'paytm' => false, 'bhim' => false];
if (!$isDeactive) {
  try {
    $st = $db->prepare("SELECT upi_type, upi_number FROM upi_numbers WHERE user_id=?");
    $st->bind_param('s', $rawUserId);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
      $t = strtolower(trim((string) $r['upi_type']));
      $n = trim((string) $r['upi_number']);
      if ($t !== '' && $n !== '' && isset($linkedApps[$t]))
        $linkedApps[$t] = true;
    }
    $st->close();
  } catch (Exception $e) {
  }
}

/* ===================== ADMIN PAYMENT DETAILS (FULL) ===================== */
$pay = [
  'bank_name' => '',
  'bank_account_name' => '',
  'bank_account_number' => '',
  'bank_ifsc' => '',
  'bank_branch' => '',
  'bank_note' => '',
  'upi_id' => '',
  'upi_name' => '',
  'upi_note' => '',
  'upi_qr' => '',
  'show_bank' => 0,
  'show_upi_id' => 0,
  'show_mobile_upi' => 0,
  'show_crypto' => 0,
  'crypto_name' => 'USDT',
  'crypto_network' => 'TRC20',
  'crypto_address' => '',
  'crypto_qr' => '',
  'inr_rate_per_unit' => 90.0,

  // ✅ intent columns
  'upi_intent' => '',
  'merchant_upi_name' => '',
  'auto_accept_intent' => 1,
  'enable_upi_intent' => 0,
  'intent_webhook_url' => '',
  'intent_success_redirect' => '',
  'intent_failed_redirect' => '',
  'min_deposit' => 300,
  'min_withdraw' => 1000,
  'withdraw_start_time' => '07:00 AM',
  'withdraw_end_time' => '11:00 AM',
  'withdraw_note' => 'Withdrawal allowed from 7:00 AM to 11:00 AM only.',
  'whatsapp_no_1' => '',
  'whatsapp_no_2' => '',
  'show_whatsapp_payment' => 0,
  'whatsapp_deposit_no' => '',
  'pay0_token' => '',
  'show_pay0' => 0,
  'imb_token' => '',
  'show_imb' => 0,
  'yuvipay_token' => '',
  'show_yuvipay' => 0,
];

try {
  $resPay = $db->query("SELECT * FROM admin_payment_details WHERE id=1 LIMIT 1");
  if ($resPay && $rowPay = $resPay->fetch_assoc()) {
    $pay = array_merge($pay, $rowPay);
  } else {
    // Fallback to latest if ID=1 not found
    $resPayFallback = $db->query("SELECT * FROM admin_payment_details ORDER BY id DESC LIMIT 1");
    if ($resPayFallback && $rowPayFallback = $resPayFallback->fetch_assoc()) {
      $pay = array_merge($pay, $rowPayFallback);
    }
  }
} catch (Exception $e) {
}

$showBank = 1; // Force enabled per user request
$showUpiId = (int) ($pay['show_upi_id'] ?? 1);
$showCrypto = (int) ($pay['show_crypto'] ?? 0);
$showMobileUpi = (int) ($pay['show_mobile_upi'] ?? 0);

// Debug Log
file_put_contents('debug_bank.txt', "Time: " . date('Y-m-d H:i:s') . " | Raw show_bank: " . ($pay['show_bank'] ?? 'NULL') . " | Cast showBank: " . $showBank . "\n", FILE_APPEND);

$enableUpiIntent = (int) ($pay['enable_upi_intent'] ?? 0);
$autoAcceptIntent = (int) ($pay['auto_accept_intent'] ?? 1);
$showWhatsApp = (int) ($pay['show_whatsapp_payment'] ?? 0);
$whatsappNo = trim($pay['whatsapp_no_1'] ?? ''); // Using WhatsApp Number 1 for Direct Admin Message feature
$showPay0 = (int) ($pay['show_pay0'] ?? 0);
$pay0Token = trim($pay['pay0_token'] ?? '');
$showImb = (int) ($pay['show_imb'] ?? 0);
$imbToken = trim($pay['imb_token'] ?? '');
$showYuvipay = (int) ($pay['show_yuvipay'] ?? 0);
$yuvipayToken = trim($pay['yuvipay_token'] ?? '');

$bankName = trim($pay['bank_name'] ?? '');
$bankHolder = trim($pay['bank_account_name'] ?? '');
$bankAcc = trim($pay['bank_account_number'] ?? '');
$bankIfsc = trim($pay['bank_ifsc'] ?? '');
$bankBranch = trim($pay['bank_branch'] ?? '');
$bankNote = trim($pay['bank_note'] ?? '');

$upiId = trim($pay['upi_id'] ?? '');
$upiName = trim($pay['upi_name'] ?? '');
$upiNote = trim($pay['upi_note'] ?? '');
$upiQr = trim($pay['upi_qr'] ?? '');

$cryptoName = trim($pay['crypto_name'] ?? 'USDT');
$cryptoNetwork = trim($pay['crypto_network'] ?? 'TRC20');
$cryptoAddress = trim($pay['crypto_address'] ?? '');
$cryptoQr = trim($pay['crypto_qr'] ?? '');
$cryptoRate = (float) ($pay['inr_rate_per_unit'] ?? 90.0);

/* ✅ Intent config */
$intentUpi = trim($pay['upi_intent'] ?? '');
$intentName = trim($pay['merchant_upi_name'] ?? '');

$enableWithdrawTime = 1;
$withdrawStartTime = '07:00 AM';
$withdrawEndTime = '11:00 AM';
$withdrawNote = 'Withdrawal allowed from 7:00 AM to 11:00 AM only.';

try {
  $resWd = $db->query("SELECT enable_withdraw_time, withdraw_start_time, withdraw_end_time, withdraw_note FROM withdrawal_time WHERE id=1 LIMIT 1");
  if ($resWd && $rowWd = $resWd->fetch_assoc()) {
    $enableWithdrawTime = (int)($rowWd['enable_withdraw_time'] ?? 1);
    $withdrawStartTime = trim($rowWd['withdraw_start_time']);
    $withdrawEndTime = trim($rowWd['withdraw_end_time']);
    $withdrawNote = trim($rowWd['withdraw_note']);
  }
} catch (Exception $e) {}

date_default_timezone_set('Asia/Kolkata');
$currentTimeStr = date('H:i');

function convertTo24H_PHP($timeStr) {
    $timeStr = trim($timeStr);
    if (stripos($timeStr, ' ') === false) {
        return str_pad($timeStr, 5, '0', STR_PAD_LEFT);
    }
    $parts = explode(' ', $timeStr);
    $timeParts = explode(':', $parts[0]);
    $hours = (int)$timeParts[0];
    $minutes = isset($timeParts[1]) ? $timeParts[1] : '00';
    $modifier = strtoupper($parts[1]);
    
    if ($hours === 12) $hours = 0;
    if ($modifier === 'PM') $hours += 12;
    
    return str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . $minutes;
}

$start24_PHP = convertTo24H_PHP($withdrawStartTime);
$end24_PHP = convertTo24H_PHP($withdrawEndTime);

$isWithdrawalAllowed = ($enableWithdrawTime === 0 || ($currentTimeStr >= $start24_PHP && $currentTimeStr <= $end24_PHP)) ? 1 : 0;
$intentSuccessRedirect = trim($pay['intent_success_redirect'] ?? '');
$intentFailedRedirect = trim($pay['intent_failed_redirect'] ?? '');

// ✅ Build the return URL so deposit.php can credit the wallet when user comes back
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'subhgaming.online';
$intentReturnUrl = "$proto://$host/deposit.php?upi=SUCCESS"; // order_id appended in JS

if ($bankName === '')
  $bankName = 'Your Bank Name';
if ($bankHolder === '')
  $bankHolder = 'Account Holder';
if ($bankAcc === '')
  $bankAcc = '0000000000';
if ($bankIfsc === '')
  $bankIfsc = 'IFSC000000';
if ($bankBranch === '')
  $bankBranch = 'Branch Name';
if ($upiId === '')
  $upiId = 'babaji@upi';
if ($upiName === '')
  $upiName = 'BABA JI';
if ($upiQr === '')
  $upiQr = 'images/QR.png';

/* ✅ Absolute base URL helper */
function site_base_url(): string
{
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  $proto = $https ? 'https://' : 'http://';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
  if ($dir === '.' || $dir === '/')
    $dir = '';
  return $proto . $host . ($dir ? $dir : '');
}
$BASE_URL = site_base_url();

/* ================== AUTO REJECT PENDING UPI INTENT / IMB / PAY0 / PAYO (AFTER 60 SECONDS) ================== */
$expiryTime = date('Y-m-d H:i:s', time() - 60);
$db->query("UPDATE deposit 
            SET status='Failed' 
            WHERE status='Pending' 
            AND (
                 method LIKE '%Intent%' 
                 OR method LIKE '%IMB%' 
                 OR method LIKE '%Pay0%'
                 OR method LIKE '%Payo%'
                 OR method='UPI_INTENT'
            )
            AND date_time < '$expiryTime'");


$flashMsg = '';

$upiResult = strtoupper(trim((string) ($_GET['upi'] ?? '')));
$upiOrder = trim((string) ($_GET['order_id'] ?? ($_GET['tr'] ?? ($_GET['transaction_id'] ?? ''))));
$credited = (int) ($_GET['credited'] ?? 0);

if (!$isDeactive && $upiResult !== '') {
  if (($upiResult === 'SUCCESS' || $upiResult === 'DONE') && $upiOrder !== '') {
    try {
      $db->begin_transaction();
      $stCheck = $db->prepare("SELECT id, amount, status, credited FROM deposit WHERE order_id = ? LIMIT 1 FOR UPDATE");
      $stCheck->bind_param('s', $upiOrder);
      $stCheck->execute();
      $resCheck = $stCheck->get_result();
      $rowDep = $resCheck->fetch_assoc();
      $stCheck->close();

      if ($rowDep && (in_array(strtolower(trim((string) $rowDep['status'])), ['pending', 'failed', ''], true)) && (int) ($rowDep['credited'] ?? 0) === 0) {
        $depAmt = (float) $rowDep['amount'];
        $depId = (int) $rowDep['id'];

        $bonus = get_bonus_amount($db, $depAmt);

        $totalCredit = $depAmt + $bonus;

        // 1. Update Deposit to Done
        $stUp = $db->prepare("UPDATE deposit SET status='Done', credited=1 WHERE id=?");
        $stUp->bind_param('i', $depId);
        $stUp->execute();
        $stUp->close();

        // Track bonus_amount in deposit table if column exists
        $stColBonus = $db->query("SHOW COLUMNS FROM deposit LIKE 'bonus_amount'");
        if ($stColBonus && $stColBonus->num_rows > 0) {
          $stUpBonus = $db->prepare("UPDATE deposit SET bonus_amount=? WHERE id=?");
          $stUpBonus->bind_param('di', $bonus, $depId);
          $stUpBonus->execute();
          $stUpBonus->close();
        }

        // 2. Credit User Balance
        $stCol = $db->query("SHOW COLUMNS FROM users LIKE 'wallet_balance'");
        if ($stCol && $stCol->num_rows > 0) {
          $stBal = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id=?");
        } else {
          $stBal = $db->prepare("UPDATE users SET wallet = wallet + ? WHERE user_id=?");
        }
        $stBal->bind_param('ds', $totalCredit, $code);
        $stBal->execute();
        $stBal->close();

        $credited = 1;
      }
      $db->commit();
    } catch (Exception $ex) {
      try {
        $db->rollback();
      } catch (Exception $er) {
      }
    }

    $flashMsg = $credited === 1
      ? ("✅ Payment Success • Wallet Updated • Order: " . $upiOrder)
      : ("✅ Payment Success • Processing • Order: " . $upiOrder);
  } elseif ($upiResult === 'FAILED') {
    $flashMsg = "❌ Payment Failed • Order: " . ($upiOrder !== '' ? $upiOrder : '');
  } else {
    $flashMsg = "ℹ️ Payment " . $upiResult . ($upiOrder !== '' ? (" • Order: " . $upiOrder) : '');
  }
}

/* Other flash */
if (isset($_GET['dep']) && $_GET['dep'] === 'ok')
  $flashMsg = '✅ Deposit Request Successful';
if (isset($_GET['wd']) && $_GET['wd'] === 'ok')
  $flashMsg = '✅ Withdrawal Request Successful';
if (isset($_GET['wd']) && $_GET['wd'] === 'fail' && isset($_GET['reason'])) {
  if ($_GET['reason'] === 'insufficient') {
    $flashMsg = '❌ Insufficient Balance';
  } elseif ($_GET['reason'] === 'time_closed') {
    $flashMsg = isset($_GET['msg']) ? urldecode($_GET['msg']) : '❌ Withdrawal Closed';
  } elseif ($_GET['reason'] === 'min_amount') {
    $flashMsg = '❌ Below Minimum Withdrawal';
  } else {
    $flashMsg = '❌ Withdrawal Request Failed';
  }
}


/* ===================== BALANCES ===================== */
$hasBonusCol = false;
$resB = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
if ($resB && $resB->num_rows > 0) {
  $hasBonusCol = true;
}

$depositDone = (float) (fetch_one(
  $db,
  "SELECT (IFNULL(SUM(amount),0) + " . ($hasBonusCol ? "IFNULL(SUM(bonus_amount),0)" : "0") . ") FROM deposit WHERE status='Done' AND user_id=?",
  's',
  [$code]
) ?? 0);

$withdrawMinus = (float) (fetch_one(
  $db,
  "SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE user_id=? AND LOWER(status) IN ('pending','approved','done')",
  's',
  [$code]
) ?? 0);

$walletBalance = max(0.0, $depositDone - $withdrawMinus);

$winBalance = (float) (fetch_one(
  $db,
  "SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id=?",
  's',
  [$code]
) ?? 0);

$totalBalance = (float)($depositDone - $withdrawMinus) + (float)$winBalance;
if ($totalBalance < 0) $totalBalance = 0;
$winBalance = (float)($totalBalance - $walletBalance);

/* ===================== POST SUBMISSION: WITHDRAWAL REQUEST ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount']) && isset($_POST['method'])) {
  if ($isDeactive) {
    header("Location: withdrawal.php?wd=fail&reason=deactive");
    exit;
  }

  $amount = (float)$_POST['amount'];
  $method = trim((string)$_POST['method']);
  $order_id = trim((string)($_POST['order_id'] ?? ''));

  $minWd = (int)($pay['min_withdraw'] ?? 1000);
  if ($amount < $minWd) {
    header("Location: withdrawal.php?wd=fail&reason=min_amount");
    exit;
  }

  if ($amount > $totalBalance) {
    header("Location: withdrawal.php?wd=fail&reason=insufficient");
    exit;
  }

  if (!$isWithdrawalAllowed) {
    header("Location: withdrawal.php?wd=fail&reason=time_closed&msg=" . urlencode($withdrawNote));
    exit;
  }

  if ($method === '') {
    header("Location: withdrawal.php?wd=fail&reason=invalid_method");
    exit;
  }

  if ($order_id === '') {
    $order_id = 'WD' . time() . mt_rand(100, 999);
  }

  // Check which columns exist in withdrawal table
  $hasOrderW = false;
  $hasTxnW = false;
  $resWCols = $db->query("SHOW COLUMNS FROM `withdrawal`");
  if ($resWCols) {
    while ($col = $resWCols->fetch_assoc()) {
      if ($col['Field'] === 'order_id') {
        $hasOrderW = true;
      }
      if ($col['Field'] === 'txn_id') {
        $hasTxnW = true;
      }
    }
  }

  $sqlInsW = "INSERT INTO withdrawal (user_id, amount, method, status";
  if ($hasOrderW) {
    $sqlInsW .= ", order_id";
  } elseif ($hasTxnW) {
    $sqlInsW .= ", txn_id";
  }
  $sqlInsW .= ", date_time) VALUES (?, ?, ?, 'Pending'";
  if ($hasOrderW || $hasTxnW) {
    $sqlInsW .= ", ?";
  }
  $sqlInsW .= ", '" . date('Y-m-d H:i:s') . "')";

  $stmtAdd = $db->prepare($sqlInsW);
  if ($hasOrderW || $hasTxnW) {
    $stmtAdd->bind_param('sdss', $rawUserId, $amount, $method, $order_id);
  } else {
    $stmtAdd->bind_param('sds', $rawUserId, $amount, $method);
  }

  if ($stmtAdd->execute()) {
    $stmtAdd->close();

    try {
        if (file_exists(__DIR__ . '/includes/fcm_helper.php')) {
            require_once __DIR__ . '/includes/fcm_helper.php';
            if (function_exists('send_admin_alert_notification')) {
                send_admin_alert_notification('admin_withdrawal', 'New Withdrawal Request', "Withdrawal request of ₹{$amount} by {$code}", $code, (float)$amount, $method);
            }
        }
    } catch (Throwable $e) {}

    header("Location: withdrawal_history.php?wd=ok");
    exit;
  } else {
    $stmtAdd->close();
    header("Location: withdrawal.php?wd=fail&reason=db_error");
    exit;
  }
}

/* ===================== AJAX: status poll ===================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'status') {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

  // ✅ AUTO REJECT EXPIRED TRANSACTIONS (3 MINUTES = 180 SECOND TIMEOUT)
  try {
    $q = $db->prepare("
            UPDATE deposit
               SET status='Failed'
             WHERE user_id=?
               AND status='Pending'
               AND credited = 0
               AND (method='UPI ZERO' OR method='UPI_INTENT' OR method='UPI Intent' OR method='UPI YUVI' OR method='IMB')
               AND date_time < (NOW() - INTERVAL 180 SECOND)
        ");
    $q->bind_param('s', $rawUserId);
    $q->execute();
    $q->close();
  } catch (Exception $e) {
  }

  $dep = ['id' => 0, 'status' => ''];
  $wd = ['id' => 0, 'status' => ''];

  try {
    $st = $db->prepare("SELECT id, status FROM deposit WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $st->bind_param('s', $rawUserId);
    $st->execute();
    $res = $st->get_result();
    if ($res && $res->num_rows) {
      $r = $res->fetch_assoc();
      $dep['id'] = (int) ($r['id'] ?? 0);
      $dep['status'] = (string) ($r['status'] ?? '');
    }
    $st->close();
  } catch (Exception $e) {
  }

  try {
    $st = $db->prepare("SELECT id, status FROM withdrawal WHERE user_id=? AND method != 'game_bid' ORDER BY id DESC LIMIT 1");
    $st->bind_param('s', $rawUserId);
    $st->execute();
    $res = $st->get_result();
    if ($res && $res->num_rows) {
      $r = $res->fetch_assoc();
      $wd['id'] = (int) ($r['id'] ?? 0);
      $wd['status'] = (string) ($r['status'] ?? '');
    }
    $st->close();
  } catch (Exception $e) {
  }

  $hasBonusCol = false;
  $resB = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
  if ($resB && $resB->num_rows > 0) {
    $hasBonusCol = true;
  }

  $depositDone2 = (float) (fetch_one(
    $db,
    "SELECT (IFNULL(SUM(amount),0) + " . ($hasBonusCol ? "IFNULL(SUM(bonus_amount),0)" : "0") . ") FROM deposit WHERE status='Done' AND user_id=?",
    's',
    [$code]
  ) ?? 0);

  $withdrawMinus2 = (float) (fetch_one(
    $db,
    "SELECT IFNULL(SUM(amount),0) FROM withdrawal WHERE user_id=? AND LOWER(status) IN ('pending','approved','done')",
    's',
    [$code]
  ) ?? 0);

  $walletBalance2 = max(0.0, $depositDone2 - $withdrawMinus2);

  $winBalance2 = (float) (fetch_one(
    $db,
    "SELECT IFNULL(SUM(wining_balance),0) FROM wining WHERE user_id=?",
    's',
    [$code]
  ) ?? 0);

  $totalBalance2 = (float)($depositDone2 - $withdrawMinus2) + (float)$winBalance2;
if ($totalBalance2 < 0) $totalBalance2 = 0;
$winBalance2 = (float)($totalBalance2 - $walletBalance2);
  $totalBalance2 = current_user_wallet($db); /* json */

  echo json_encode([
    'ok' => true,
    'deposit' => $dep,
    'withdrawal' => $wd,
    'balances' => [
      'wallet' => round($walletBalance2, 2),
      'winning' => round($winBalance2, 2),
      'total' => round($totalBalance2, 2),
    ],
    'ts' => time(),
  ], JSON_UNESCAPED_UNICODE);

  $db->close();
  exit;
}

/* ===================== LISTS ===================== */
$depositList = [];
$withdrawList = [];
if (!$isDeactive) {
  try {
    /* ✅ UPDATED: Fetch bonus_amount safely if column exists */
    $hasBonusCol = false;
    $resB = $db->query("SHOW COLUMNS FROM `deposit` LIKE 'bonus_amount'");
    if ($resB && $resB->num_rows > 0) {
      $hasBonusCol = true;
    }

    $sql = "SELECT id, amount, status, method, date_time, order_id" . ($hasBonusCol ? ", bonus_amount" : "") . " FROM deposit WHERE user_id=? ORDER BY id DESC LIMIT 300";
    $st = $db->prepare($sql);
    $st->bind_param('s', $rawUserId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc())
      $depositList[] = $row;
    $st->close();
  } catch (Exception $e) {
    error_log('[DEPOSIT LIST] ' . $e->getMessage());
  }

  try {
    $st = $db->prepare("
            SELECT id, amount, status, method,
                   date_time AS date_time_ist
              FROM withdrawal
             WHERE user_id=?
               AND TRIM(IFNULL(method,'')) <> ''
               AND method != 'game_bid'
             ORDER BY id DESC
             LIMIT 5
        ");
    $st->bind_param('s', $rawUserId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc())
      $withdrawList[] = $row;
    $st->close();
  } catch (Exception $e) {
    error_log('[WITHDRAW LIST] ' . $e->getMessage());
  }
}

/* linked checks */
$bankApproved = in_array(strtolower(trim($bankStatus)), ['approved', 'linked'], true);
$upiApproved = (strtolower(trim($upiStatus)) === 'approved');
$cryptoApproved = (strtolower(trim($cryptoStatus)) === 'approved');
$cryptoLinked = ($showCrypto === 1 && $cryptoAddress !== '' && $cryptoApproved);

/* share */
$shareTitle = "Game Rates";
$shareDownloadLink = "https://subhgaming.online/download.php";
$shareCommission = "";
$shareText =
  "SUBH GAMING - Game Rates\n"
  . "Download App Now! Single Digit ₹10 = ₹95. Jodi ₹10 = ₹950. Trusted & Fast Withdrawal.\n"
  . $shareDownloadLink;

$shareImgCandidates = [
  "https://subhgaming.online/images/share.png",
  "https://subhgaming.online/share.png",
  "images/share.png",
  "share.png"
];

$MIN_DEPOSIT = (int) ($pay['min_deposit'] ?? 300);
$MIN_WITHDRAW = (int) ($pay['min_withdraw'] ?? 1000);
if ($MIN_DEPOSIT < 1)
  $MIN_DEPOSIT = 300;
if ($MIN_WITHDRAW < 1)
  $MIN_WITHDRAW = 1000;
$bonusRatesForJs = get_bonus_rates_for_js($db);

/* default deposit tab - Priority: UPI QR (Requested Default) > Pay0 > IMB > Whatsapp > Intent ... */
$defaultDepositTab = ($showUpiId ? 'upi' : ($showYuvipay ? 'yuvipay' : ($showPay0 ? 'pay0' : ($showImb ? 'imb' : ($showWhatsApp ? 'whatsapp' : ($enableUpiIntent ? 'intent' : ($showBank ? 'bank' : ($showCrypto ? 'crypto' : 'upi'))))))));

/* withdrawal methods */
$withdrawMethods = [];
if (!empty($showBank))
  $withdrawMethods[] = ['key' => 'bank', 'label' => 'Bank', 'value' => 'Bank', 'linked' => $bankApproved];

if (!empty($showUpiId)) {
  $withdrawMethods[] = ['key' => 'upi', 'label' => 'UPI', 'value' => 'UPI', 'linked' => $upiApproved];
}

if ($showMobileUpi === 1) {
  $withdrawMethods[] = ['key' => 'phonepe', 'label' => 'PhonePe', 'value' => 'PhonePe', 'linked' => (bool) $linkedApps['phonepe']];
  $withdrawMethods[] = ['key' => 'gpay', 'label' => 'Google Pay', 'value' => 'Google Pay', 'linked' => (bool) $linkedApps['gpay']];
  $withdrawMethods[] = ['key' => 'paytm', 'label' => 'Paytm', 'value' => 'Paytm', 'linked' => (bool) $linkedApps['paytm']];
  $withdrawMethods[] = ['key' => 'bhim', 'label' => 'BHIM', 'value' => 'BHIM', 'linked' => (bool) $linkedApps['bhim']];
}

if ($showCrypto === 1)
  $withdrawMethods[] = ['key' => 'crypto', 'label' => 'Crypto', 'value' => 'Crypto', 'linked' => $cryptoLinked];

/* WhatsApp withdrawal removed as per request */

/* ✅ Intent return handler (api_update_balance.php) */
$intentReturnUrl = $BASE_URL . "/api_update_balance.php?key=LL_2026_5a9c8f2e7d1b4k6m3x&next=" . rawurlencode("deposit.php");

/* ✅ Marquee Fetch */
$notice = !empty($withdrawNote) ? __($withdrawNote) : __("Withdrawal are available every day from 6:00 AM to 11:00 AM.");
$marqueeText = $notice;
$qB = $db->query("SELECT banner FROM banner_text ORDER BY id DESC LIMIT 1");
if ($qB && $rB = $qB->fetch_assoc()) {
  $dbBanner = trim($rB['banner']);
  // Check if it's NOT the welcome text we want to hide
  if ($dbBanner !== '' && stripos($dbBanner, 'WELCOME') === false) {
    $marqueeText = $dbBanner . ' | ' . $notice;
  }
}

$db->close();

// Fetch Game Rates for Sharing
$gameRates = [];
try {
    $dbR = db();
    $resRates = $dbR->query("SELECT game_type, play_amount, win_amount FROM game_rates ORDER BY id ASC");
    if ($resRates) {
        while ($r = $resRates->fetch_assoc()) {
            $gameRates[] = $r;
        }
    }
    $dbR->close();
} catch (Throwable $e) {}


/* Side menu identity fallback */
$sideUserName = $userName ?? ($user['name'] ?? ($_SESSION['user']['name'] ?? 'User'));
$sideUserMobile = $userMobile ?? ($user['mobile_no'] ?? ($uniqueUserId ?? ($code ?? ($user['user_id'] ?? ''))));
$sideUserSince = $userSince ?? '';
if ($sideUserMobile === '' || $sideUserSince === '') {
  try {
    if (isset($db, $uid)) {
      $stmtSide = $db->prepare('SELECT mobile_no, created_at FROM users WHERE id=? LIMIT 1');
      $stmtSide->bind_param('i', $uid);
      $stmtSide->execute();
      $sideRow = $stmtSide->get_result()->fetch_assoc();
      $stmtSide->close();
      if ($sideUserMobile === '' && !empty($sideRow['mobile_no'])) $sideUserMobile = $sideRow['mobile_no'];
      if ($sideUserSince === '' && !empty($sideRow['created_at'])) $sideUserSince = date('d-m-Y', strtotime($sideRow['created_at']));
    }
  } catch (Throwable $e) {}
}
?>
<?php /* MK_WALLET_SYNC */ if (!function_exists('current_user_wallet') && !function_exists('db')) { require_once __DIR__ . '/includes/db.php'; } if (function_exists('current_user_wallet')) { $totalBalance = current_user_wallet($db ?? null); } ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#000000">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <title>SUBH GAMING | Withdrawal</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-brands/css/uicons-brands.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
      -webkit-tap-highlight-color: transparent;
    }

    :root {
      --primary-bg: #000000;
      --card-bg: #ffffff;
      --accent-blue: #0066cc;
      --accent-red: #e60000;
      --accent-yellow: #0066cc;
      --accent-gold: #0088ff;
      --gradient-gold: linear-gradient(135deg, #0066cc, #0088ff);
      --text-main: #ffffff;
      --text-dim: #a0a0a0;
      --shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
      --border-radius: 15px;
    }

    body {
      background-color: #000000 !important;
      color: #ffffff;
      padding-bottom: 100px;
      overflow-x: hidden;
    }

    header {
      background-color: #000000 !important;
      background-image: none !important;
      border-bottom: 1px solid #1a1a1a !important;
      padding-top: calc(env(safe-area-inset-top) + 12px) !important;
      padding-bottom: 12px !important;
      padding-left: 14px !important;
      padding-right: 14px !important;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    .back-btn {
      width: 38px;
      height: 38px;
      border-radius: 8px;
      background: #e53935;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff;
      text-decoration: none;
      font-size: 15px;
      cursor: pointer;
      border: none;
      flex-shrink: 0;
      transition: opacity 0.15s ease;
    }
    .back-btn:active {
      opacity: 0.8;
    }

    .header-left,
    .header-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .header-title-flash {
      color: #ffffff !important;
      font-size: 19px !important;
      font-weight: 800 !important;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .header-title-flash img {
      width: 18px;
      height: 18px;
      vertical-align: middle;
      margin-top: -2px;
    }

    .wallet-display {
      background: #ffffff;
      border: none;
      padding: 6px 12px;
      border-radius: 20px;
      color: #000000;
      font-weight: 800;
      font-size: 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 6px;
    }

    .wallet-icon-btn {
      display: none;
    }

    .bell-icon-btn {
      background: transparent;
      color: #ffffff !important;
      width: 35px;
      height: 35px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: none;
      text-decoration: none;
      border: none;
      font-size: 20px;
    }

    /* Header Toggle Btn */
    #menuToggle {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      background: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #000000 !important;
      box-shadow: none;
      cursor: pointer;
      border: none;
      font-size: 18px !important;
    }

    /* Marquee */
    .marquee-wrap {
        background: #ffffff;
        padding: 8px;
        color: #000000;
        font-weight: 700;
        font-size: 12px;
        text-transform: uppercase;
        overflow: hidden;
        white-space: nowrap;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .marquee-content {
        display: inline-block;
        animation: marquee 15s linear infinite;
    }

    @keyframes marquee {
      0% {
        transform: translateX(100vw);
      }

      100% {
        transform: translateX(-100%);
      }
    }

    /* ✅ Pay0 Gateway Loading Overlay */
    #pay0LoadingOverlay {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 99999;
      background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 20px;
    }

    #pay0LoadingOverlay.show {
      display: flex;
    }

    .pay0-loader-ring {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      border: 5px solid rgba(255, 255, 255, 0.1);
      border-top: 5px solid #ff4444;
      border-right: 5px solid #ff8800;
      animation: pay0Spin 0.9s linear infinite;
    }

    @keyframes pay0Spin {
      to {
        transform: rotate(360deg);
      }
    }

    .pay0-loader-icon {
      position: absolute;
      font-size: 28px;
      color: #fff;
    }

    .pay0-loader-wrap {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .pay0-loader-title {
      font-size: 20px;
      font-weight: 800;
      color: #fff;
      letter-spacing: 0.5px;
    }

    .pay0-loader-sub {
      font-size: 13px;
      color: rgba(255, 255, 255, 0.6);
      text-align: center;
      max-width: 260px;
      line-height: 1.5;
    }

    .pay0-loader-dots {
      display: flex;
      gap: 8px;
      margin-top: 4px;
    }

    .pay0-loader-dots span {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #ff5722;
      animation: pay0Bounce 1.2s ease-in-out infinite;
    }

    .pay0-loader-dots span:nth-child(2) {
      animation-delay: 0.2s;
    }

    .pay0-loader-dots span:nth-child(3) {
      animation-delay: 0.4s;
    }

    @keyframes pay0Bounce {

      0%,
      80%,
      100% {
        transform: scale(0.6);
        opacity: 0.4;
      }

      40% {
        transform: scale(1.2);
        opacity: 1;
      }
    }

    .pay0-secure-badge {
      display: flex;
      align-items: center;
      gap: 6px;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 20px;
      padding: 6px 14px;
      font-size: 12px;
      color: rgba(255, 255, 255, 0.7);
    }

    body.modal-open {
      overflow: hidden !important;
      height: 100vh;
      touch-action: none;
    }

    .content {
      max-width: 480px;
      margin: 0 auto;
      padding: 15px;
    }

    @media (max-width: 600px) {
      .content {
        padding-top: 6px;
      }

      .top-row-actions {
        margin-bottom: 14px;
      }
    }



    /* ✅ NEW: Withdrawal note under header */
    .withdraw-note {
      max-width: 480px;
      margin: 8px auto 6px;
      background: #fff3cd;
      border: 1px solid #ffecb5;
      border-left: 5px solid #e65100;
      color: #5d3a00;
      padding: 10px 12px;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 900;
      line-height: 1.35;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
    }

    /* WA CONTACTS */
    .wa-contact-row {
      display: flex;
      gap: 10px;
      margin-bottom: 15px;
      max-width: 480px;
      margin-left: auto;
      margin-right: auto;
    }

    .wa-contact-item {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none;
      background: #fff;
      padding: 10px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .wa-contact-item i {
      font-size: 24px;
      color: #25d366;
    }

    .wa-contact-item span {
      font-size: 13px;
      font-weight: 800;
      color: #ff3d47;
    }

    /* Deactive banner */
    .deactive-banner {
      background: #ffebee;
      border: 1px solid #ef9a9a;
      color: #b71c1c;
      padding: 10px 12px;
      font-size: 14px;
      border-radius: 10px;
      margin: 8px auto 8px;
      max-width: 480px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }

    .deactive-banner span {
      font-weight: 600;
    }

    .deactive-banner a {
      background: #25d366;
      color: #fff;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 13px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    /* ✅ NEW: Deactive extra line (under Chat Now) */
    .deactive-extra {
      max-width: 480px;
      margin: 0 auto 10px;
      background: #fff;
      border: 1px solid #eee8db;
      border-left: 5px solid #c62828;
      color: #4e342e;
      padding: 10px 12px;
      border-radius: 12px;
      font-size: 13px;
    }

    /* SIDE MENU Standardized */
    .side-menu-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .6);
        display: none;
        z-index: 4000;
        backdrop-filter: blur(2px);
    }

    .side-menu {
        position: fixed;
        top: 0;
        left: -240px;
        width: min(240px, 80vw);
        height: 100%;
        background: #ffffff;
        color: #333333;
        padding: 0;
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 4100;
        box-shadow: 10px 0 30px rgba(0, 0, 0, 0.12);
        display: flex;
        flex-direction: column;
        border-right: 1px solid rgba(0, 0, 0, 0.06);
    }

    .side-menu.open {
        left: 0;
    }

    .side-menu-header {
        padding: 16px 14px;
        background: #ff005c;
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }

    .profile-avatar {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        background: #ff005c;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        color: #ffffff;
        box-shadow: none;
        flex-shrink: 0;
        text-decoration: none !important;
        border: 2px solid #ffffff;
    }

    .profile-avatar i {
        text-decoration: none !important;
    }

    .profile-text {
        display: flex;
        flex-direction: column;
        min-width: 0;
        padding-top: 2px;
    }

    .profile-name {
        font-size: 17px;
        font-weight: 800;
        color: #ffffff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .profile-id {
        font-size: 11px;
        margin-top: 2px;
        color: rgba(255, 255, 255, 0.8);
        font-weight: 600;
    }

    .profile-since {
        font-size: 11px;
        margin-top: 4px;
        color: rgba(255, 255, 255, 0.6);
        font-weight: 500;
    }

    .side-close-btn {
        margin-left: auto;
        background: transparent;
        border: none;
        color: #ffffff;
        font-size: 24px;
        cursor: pointer;
        transition: transform 0.2s;
        line-height: 1;
        padding: 4px;
    }
    
    .side-close-btn:hover {
        transform: scale(1.1);
    }

    .side-menu-body {
        padding: 14px 0 8px;
        flex: 1;
        overflow-y: auto;
    }

    .side-menu-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 11px 14px;
        margin: 2px 0;
        font-size: 13px;
        color: #1f2735;
        text-decoration: none;
        border-radius: 0;
        background: transparent;
        border: none;
        transition: all 0.3s ease;
        font-weight: 700;
    }

    .side-menu-item i {
        width: 40px;
        height: 40px;
        min-width: 40px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #ffffff;
        background: linear-gradient(135deg, #ff005c 0%, #ff4b82 50%, #ff005c 100%);
        box-shadow: 0 4px 6px rgba(255, 0, 92, 0.3);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: none;
    }

    .side-menu-item:hover {
        background: rgba(0, 0, 0, 0.04);
        color: #000000;
        transform: none;
        box-shadow: none;
    }
    
    .side-menu-item:hover i {
        color: #ffffff;
        transform: scale(1.05);
        box-shadow: 0 6px 12px rgba(255, 0, 92, 0.4);
    }

    .side-menu-item.active {
        background: rgba(0, 0, 0, 0.06);
        color: #000000;
        font-weight: 700;
        border: none;
        box-shadow: none;
    }

    .side-menu-item.active i {
        color: #ffffff;
        background: linear-gradient(135deg, #ff005c 0%, #ff4b82 50%, #ff005c 100%);
    }

    .side-menu-footer {
        padding: 16px;
        border-top: 1px solid rgba(0, 0, 0, 0.08);
        background: #ffffff;
    }

    .side-footer-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .side-logout-btn {
        flex: 1;
        height: 48px;
        background: #ffffff;
        color: #e74c3c;
        border: 2px solid #e74c3c;
        border-radius: 999px;
        font-size: 15px;
        font-weight: 800;
        text-decoration: none;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-shadow: none;
        transition: all 0.3s ease;
    }
    
    .side-logout-btn:hover {
        background: #e74c3c;
        color: #ffffff;
    }

    .share-btn {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        border: none;
        background: linear-gradient(135deg, #128C7E 0%, #25D366 50%, #128C7E 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 10px rgba(37, 211, 102, 0.3);
        transition: all 0.3s ease;
    }
    
    .share-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 15px rgba(37, 211, 102, 0.4);
    }

    .share-btn i {
        font-size: 20px;
        color: #ffffff;
    }


    .action-card {
      border-radius: 12px;
      padding: 16px 10px;
      min-height: 64px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      text-decoration: none;
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      transition: transform 0.2s ease;
      background: var(--card-bg);
      border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .action-card:active {
      transform: scale(0.96);
    }

    .action-card i {
      width: 26px;
      text-align: center;
      font-size: 24px;
      line-height: 1;
      color: #fff;
      filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.2));
    }

    .action-card span {
      font-size: 15px;
      font-weight: 700;
      letter-spacing: 0.5px;
      white-space: nowrap;
      color: #fff;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }

    .mesh-blue {
      background-image: linear-gradient(135deg, #4facfe 0%, #00f2fe 50%, #2980b9 100%);
    }

    .mesh-add {
      background-image: linear-gradient(135deg, #f83600 0%, #f9d423 50%, #ff4b1f 100%);
    }

    .mesh-withdraw {
      background-image: linear-gradient(135deg, #b721ff 0%, #21d4fd 50%, #ff0844 100%);
    }

    .mesh-whatsapp {
      background-image: linear-gradient(135deg, #43e97b 0%, #38f9d7 50%, #11998e 100%);
    }

    .side-box-grid {
      margin: 12px 0 6px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .side-box-grid .action-card {
      padding: 14px 10px;
      min-height: 64px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      gap: 0;
    }

    .side-box-grid .action-card i {
      font-size: 22px;
      margin-bottom: 6px;
      width: auto;
    }

    .side-box-grid .action-card span {
      font-size: 13px;
      font-weight: 800;
      display: block;
      white-space: normal;
      line-height: 1.15;
      word-break: break-word;
    }



    /* ✅ NEW: Deactive footer note under Logout/Share */
    .deactive-footer-note {
      margin-top: 15px;
      background: #fff;
      border: 1px solid #eee8db;
      border-left: 5px solid #c62828;
      color: #4e342e;
      padding: 10px 12px;
      border-radius: 12px;
      font-size: 12.5px;
      font-weight: 900;
      line-height: 1.35;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
      text-align: center;
    }

    /* Wallet page UI */
    .section-wrap {
      max-width: 480px;
      margin: 4px auto 0;
      padding: 0 10px;
    }

    .section-title {
      font-size: 18px;
      font-weight: 700;
      margin: 20px 0 15px;
      color: #fff;
      border-left: 4px solid var(--accent-yellow);
      padding-left: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .hindi-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
      margin-bottom: 8px;
    }

    .hindi-label {
      text-align: center;
      font-weight: 800;
      color: var(--accent-yellow);
      font-size: 9.5px;
      opacity: 0.95;
      white-space: nowrap;
    }

    .top-row-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 25px;
    }

    @media (max-width: 600px) {
      .top-row-actions {
        margin-bottom: 8px;
      }
    }

    .top-pill-btn {
      background: linear-gradient(to right, #0066cc, #0088ff);
      border-radius: 12px;
      padding: 14px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 4px 12px rgba(0, 102, 204, 0.25);
      transition: transform 0.2s;
      cursor: pointer;
    }

    .top-pill-btn:active {
      transform: scale(0.96);
    }

    .top-pill-btn span {
      font-size: 12.5px;
      font-weight: 800;
      color: #ffffff;
      text-transform: uppercase;
    }

    .play-circle {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: #fff;
      color: #000;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.22), 0 3px 8px rgba(0, 0, 0, 0.2);
    }

    .play-circle i {
      color: #000 !important;
      text-shadow: none !important;
      filter: none !important;
    }

    .hist-toggle {
      background: rgba(255, 255, 255, 0.05);
      padding: 5px;
      border-radius: 12px;
      display: flex;
      gap: 5px;
      margin-bottom: 20px;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .hist-btn {
      flex: 1;
      background: transparent;
      border: 1px solid #0066cc;
      color: #aaa;
      font-weight: 700;
      padding: 10px 5px;
      border-radius: 8px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      font-size: 11px;
      transition: 0.3s;
      text-transform: uppercase;
    }

    .hist-btn.active {
      background: var(--gradient-gold);
      border-color: transparent;
      color: #ffffff;
      box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
    }

    .hist-btn i {
      font-size: 16px;
    }

    .info-text {
      text-align: center;
      color: #0066cc;
      margin-bottom: 12px;
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .transaction-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card-bg);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: var(--shadow);
      border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .transaction-table th {
      background: #f5f5f5;
      /* Solid background for sticky */
      padding: 8px 5px;
      text-align: center;
      font-size: 11px;
      font-weight: 700;
      color: #333333;
      text-transform: uppercase;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      position: sticky;
      top: 0;
      z-index: 10;
    }

    .transaction-table td {
      padding: 7px 5px;
      text-align: center;
      font-size: 13px;
      color: #333333;
      border-bottom: 1px solid rgba(255, 255, 255, 0.02);
      font-weight: 500;
    }

    .table-scroll-wrap {
      max-height: none;
      overflow-y: visible;
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.05);
      margin-bottom: 10px;
    }

    .transaction-table tr:last-child td {
      border-bottom: none;
    }

    .transaction-table .done,
    .transaction-table .approved {
      color: #27ae60 !important;
      font-weight: 700;
    }

    .transaction-table .pending {
      color: #f39c12 !important;
      font-weight: 700;
    }

    .transaction-table .failed,
    .transaction-table .rejected {
      color: #e74c3c !important;
      font-weight: 700;
    }


    .pagination {
      text-align: center;
      margin: 15px auto;
      max-width: 480px;
    }

    .bank-note {
      background: #fff8e1;
      border-left: 4px solid #ffc107;
      padding: 10px;
      margin-bottom: 12px;
      border-radius: 4px;
    }

    /* Pay0 specific styling */
    .pay0-logo-container {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-bottom: 15px;
      background: #fdfdfd;
      padding: 15px;
      border-radius: 12px;
      border: 1px dashed #ddd;
    }

    .pay0-logo-container img {
      height: 30px;
      object-fit: contain;
      opacity: 0.8;
      transition: opacity 0.3s;
    }

    .pay0-logo-container img:hover {
      opacity: 1;
    }

    .pagination button {
      background: linear-gradient(135deg, #c9a227, #f7d781, #c9a227);
      color: #000000;
      border: 1px solid rgba(0, 0, 0, 0.1);
      padding: 7px 18px;
      margin: 0 5px;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 800;
      box-shadow: 0 4px 12px rgba(201, 162, 39, 0.25);
      text-transform: uppercase;
      font-size: 11px;
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
    }

    .pagination button:disabled {
      background: #e0e0e0;
      color: #999;
      border-color: #ddd;
      box-shadow: none;
      cursor: not-allowed;
      opacity: 1;
    }

    .pagination button:not(:disabled):active {
      transform: scale(0.94);
    }

    /* modals */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.85);
      align-items: center;
      justify-content: center;
      z-index: 3000;
      backdrop-filter: blur(5px);
    }

    .modal.show {
      display: flex;
    }

    .modal-content {
      background: #111;
      border: 1px solid var(--accent-yellow);
      width: 92%;
      max-width: 450px;
      max-height: 85vh;
      overflow-y: auto;
      padding: 24px;
      border-radius: 20px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.8), 0 0 15px rgba(255, 193, 7, 0.1);
      position: relative;
      z-index: 1;
      -webkit-overflow-scrolling: touch;
      overscroll-behavior: contain;
      color: #fff;
    }

    .modal-content h3 {
      font-size: 22px;
      color: var(--accent-yellow);
      margin-bottom: 22px;
      text-align: center;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .close-btn {
      position: absolute;
      top: 14px;
      right: 18px;
      font-size: 26px;
      color: var(--accent-yellow);
      background: none;
      border: none;
      cursor: pointer;
      opacity: 0.8;
      transition: 0.2s;
    }

    .close-btn:hover {
      opacity: 1;
      transform: rotate(90deg);
    }

    .payment-option {
      display: flex;
      gap: 12px;
      margin-bottom: 18px;
      flex-wrap: wrap;
    }

    .option {
      flex: 1;
      padding: 12px 0;
      text-align: center;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #ccc;
      cursor: pointer;
      font-weight: 700;
      font-size: 13px;
      transition: all 0.2s;
    }

    .option.active {
      background: var(--gradient-gold);
      border: none;
      color: #000;
      box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
    }

    .bank-section,
    .qr-section,
    .crypto-section,
    .intent-section,
    .whatsapp-section,
    .pay0-section,
    .imb-section,
    .yuvipay-section {
      display: none;
      margin-bottom: 16px;
    }

    .bank-section.active,
    .qr-section.active,
    .crypto-section.active,
    .intent-section.active,
    .whatsapp-section.active,
    .pay0-section.active,
    .imb-section.active,
    .yuvipay-section.active {
      display: block;
    }

    .bank-lines {
      background: rgba(0, 102, 204, 0.05);
      padding: 15px;
      border-radius: 12px;
      font-size: 14px;
      line-height: 1.8;
      margin-bottom: 15px;
      color: #fff;
      border: 1px solid rgba(0, 102, 204, 0.2);
    }

    .bank-lines strong {
      color: var(--accent-yellow);
      font-weight: 800;
      min-width: 80px;
      display: inline-block;
    }

    .bank-note,
    .upi-note {
      font-size: 12px;
      color: #6d4c41;
      color: #aaa;
      margin-top: 4px;
      text-align: left;
    }

    .qr-section img.upi-qr {
      display: block;
      margin: 10px auto 14px auto;
      max-width: 140px;
      border: 2px solid var(--accent-yellow);
      border-radius: 12px;
      padding: 5px;
      background: #fff;
    }

    .qr-section .qr-id {
      margin-top: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      font-size: 14px;
      color: var(--accent-yellow);
      background: rgba(255, 193, 7, 0.05);
      padding: 8px;
      border-radius: 8px;
      border: 1px dashed rgba(255, 193, 7, 0.3);
    }

    .qr-section .upi-logo {
      height: 18px;
      width: auto;
      display: block;
    }

    .qr-section .upi-id-text {
      font-weight: 900;
      letter-spacing: .3px;
    }

    .crypto-addr {
      margin-top: 2px;
      padding: 10px 12px;
      border-radius: 12px;
      background: rgba(255, 193, 7, 0.05);
      border: 1px solid rgba(255, 193, 7, 0.2);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .crypto-addr span {
      flex: 1;
      font-size: 12px;
      line-height: 1.4;
      color: #eee;
      word-break: break-all;
      font-weight: 700;
    }

    .copy-btn {
      width: 40px;
      height: 40px;
      border: none;
      border-radius: 8px;
      background: var(--gradient-gold);
      color: #000;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: 0.2s;
    }

    .copy-btn:active {
      transform: scale(0.9);
    }

    .copy-btn i {
      color: #000 !important;
      font-size: 16px;
    }

    .modal-content label {
      display: block;
      font-size: 14px;
      color: #ccc;
      margin-bottom: 8px;
      font-weight: 600;
    }

    .modal-content input,
    .modal-content select {
      width: 100%;
      padding: 12px 15px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 10px;
      margin-bottom: 15px;
      font-size: 14px;
      color: #fff;
      outline: none;
      transition: 0.2s;
    }

    .modal-content input:focus {
      border-color: var(--accent-yellow);
      background: rgba(255, 255, 255, 0.08);
    }

    @keyframes bounceUp {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-5px); }
    }

    .submit-btn {
      width: 100%;
      padding: 14px;
      border: none;
      border-radius: 10px;
      font-size: 16px;
      font-weight: 700;
      color: #ffffff !important;
      cursor: pointer;
      text-transform: uppercase;
      background: linear-gradient(135deg, #0052D4 0%, #4364F7 50%, #6FB1FC 100%) !important;
      box-shadow: 0 4px 0 #003296, 0 6px 12px rgba(0, 82, 212, 0.5) !important;
      transition: all 0.3s ease;
      margin-top: 15px;
      animation: bounceUp 4s infinite ease-in-out;
      position: relative;
      overflow: hidden;
    }

    .submit-btn:active {
      transform: translateY(2px);
      box-shadow: 0 2px 0 #003296, 0 3px 6px rgba(0, 82, 212, 0.5) !important;
    }

    .submit-btn:hover {
      opacity: 0.95;
    }

    .submit-btn.withdraw {
      background: linear-gradient(90deg, #801619, #eb2227);
      box-shadow: 0 5px 15px rgba(235, 34, 39, 0.3);
      border: none !important;
    }

    .submit-btn.withdraw:hover {
      opacity: 0.95;
      box-shadow: 0 8px 20px rgba(235, 34, 39, 0.4);
    }

    /* ✅ Intent quick amount chips */
    .quick-amounts {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin: 12px 0 15px;
    }

    .quick-amount {
      width: 100%;
      border: 2px solid #c62828;
      background: #fff;
      color: #c62828;
      font-weight: 900;
      padding: 10px 10px;
      border-radius: 8px;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
    }

    .quick-amount:active {
      transform: scale(.97);
    }

    .deposit-page-content label {
      display: block;
      font-size: 13px;
      color: #444;
      margin-bottom: 6px;
      font-weight: 700;
    }

    .deposit-page-content input,
    .deposit-page-content select {
      width: 100%;
      padding: 12px 15px;
      background: #f8f9fc;
      border: 1.5px solid #d0d4db;
      border-radius: 10px;
      margin-bottom: 14px;
      font-size: 15px;
      font-weight: 600;
      color: #111;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
      box-shadow: 0 1px 4px rgba(0,0,0,0.06);
      box-sizing: border-box;
    }

    .deposit-page-content input:focus {
      border-color: #111;
      background: #fff;
      box-shadow: 0 2px 10px rgba(0,0,0,0.12);
    }

    /* ✅ Withdraw dropdown portal */
    .wd-select-wrap {
      position: relative;
      margin-bottom: 10px;
    }

    .wd-select-btn {
      width: 100%;
      padding: 12px 15px;
      border: 1.5px solid #d0d4db;
      border-radius: 10px;
      background: #f8f9fc;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      cursor: pointer;
      font-size: 15px;
      font-weight: 600;
      color: #111;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
      box-shadow: 0 1px 4px rgba(0,0,0,0.06);
      box-sizing: border-box;
      height: 48px;
    }

    .wd-select-btn:focus {
      border-color: #111;
      background: #fff;
      box-shadow: 0 2px 10px rgba(0,0,0,0.12);
    }

    .wd-select-left {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 0;
    }

    .wd-selected-label {
      font-size: 15px;
      font-weight: 600;
      color: #111 !important;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .wd-selected-badge {
      font-size: 12px;
      font-weight: 900;
      padding: 4px 10px;
      border-radius: 999px;
      border: 1px solid #e0e0e0;
      white-space: nowrap;
    }

    .wd-selected-badge.linked {
      border-color: #c8e6c9;
      background: #e8f5e9;
      color: #2e7d32;
    }

    .wd-selected-badge.notlinked {
      border-color: #ffcdd2;
      background: #ffebee;
      color: #b71c1c;
    }

    .wd-arrow {
      color: #795548 !important;
      font-size: 14px;
    }

    .wd-portal-dropdown {
      position: fixed;
      background: #fff;
      border: 1px solid #e0e0e0;
      border-radius: 12px;
      box-shadow: 0 14px 28px rgba(0, 0, 0, .16);
      overflow: visible;
      z-index: 99999;
      display: none;
      transform-origin: top;
      animation: ddIn .16s ease-out;
    }

    @keyframes ddIn {
      from {
        transform: scale(.98);
        opacity: .3
      }

      to {
        transform: scale(1);
        opacity: 1
      }
    }

    .wd-portal-dropdown.open {
      display: block;
    }

    .wd-portal-dropdown .list {
      max-height: none;
      overflow: visible;
    }

    .wd-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 12px;
      cursor: pointer;
      border-bottom: 1px solid #f1f1f1;
    }

    .wd-item:last-child {
      border-bottom: none;
    }

    .wd-item:hover {
      background: #faf7f2;
    }

    .wd-item .name {
      font-weight: 900;
      color: #3e2723;
    }

    .wd-item .badge {
      font-size: 12px;
      font-weight: 900;
      padding: 4px 10px;
      border-radius: 999px;
      border: 1px solid #e0e0e0;
    }

    .wd-item .badge.linked {
      border-color: #c8e6c9;
      background: #e8f5e9;
      color: #2e7d32;
    }

    .wd-item .badge.notlinked {
      border-color: #ffcdd2;
      background: #ffebee;
      color: #b71c1c;
    }

    /* KYC quick button */
    .kyc-actions {
      display: none;
      margin: 8px 0 12px;
    }

    .kyc-actions.show {
      display: block;
    }

    .kyc-btn {
      width: 100%;
      border: none;
      border-radius: 12px;
      padding: 11px 12px;
      font-weight: 900;
      cursor: pointer;
      background: linear-gradient(135deg, #4facfe, #0066cc);
      color: #ffffff;
      box-shadow: 0 4px 14px rgba(0, 102, 204, 0.25);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    /* popup */
    .custom-popup {
      display: none;
      position: fixed;
      top: 40px;
      left: 50%;
      transform: translateX(-50%);
      background: #2e7d32;
      color: #fff;
      padding: 18px 36px 16px 30px;
      font-size: 1.05rem;
      border-radius: 13px;
      font-weight: 800;
      box-shadow: 0 3px 24px rgba(0, 0, 0, .10);
      z-index: 9999;
      text-align: center;
      min-width: 260px;
      border: 2px solid #4caf50;
      animation: popupfadein .6s;
    }

    @keyframes popupfadein {
      from {
        opacity: 0;
        transform: translateX(-50%) translateY(-30px)
      }

      to {
        opacity: 1;
        transform: translateX(-50%) translateY(0)
      }
    }

    /* BOTTOM MENU */
    nav.bottom-menu {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 75px;
        background: #ffffff;
        display: flex;
        justify-content: space-around;
        align-items: center;
        z-index: 2000;
        box-shadow: 0 -5px 25px rgba(0, 0, 0, 0.08);
        border-top: 1px solid rgba(0, 0, 0, 0.04);
        animation: jellyBounce 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
    }

    @keyframes jellyBounce {
        0% {
            opacity: 0;
            transform: scale(0.9);
        }
        100% {
            opacity: 1;
            transform: scale(1);
        }
    }

    .menu-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #666666;
        text-decoration: none;
        flex: 1;
        height: 100%;
        transition: all 0.3s ease;
    }

    .menu-item i {
        width: 38px;
        height: 38px;
        background: transparent;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #000000;
        margin-bottom: 6px;
        box-shadow: none;
        border: 1.5px solid #000000;
    }

    .menu-item span {
        font-size: 11px;
        font-weight: 500;
        color: #000000;
    }

    .menu-item.active span {
        font-weight: 700;
    }

    .menu-item.active i {
        box-shadow: none;
        background: rgba(0, 0, 0, 0.05);
        transform: translateY(-2px);
    }

    .menu-item.home-item {
        position: relative;
        top: -12px;
        flex: 1.2;
        animation: floatHome 3s infinite ease-in-out;
    }

    @keyframes floatHome {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }

    .menu-item.home-item i {
        width: 55px !important;
        height: 55px !important;
        background: #ff005c !important;
        border-radius: 50% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        color: #000000 !important;
        font-size: 24px !important;
        box-shadow: 0 4px 10px rgba(255, 0, 92, 0.4) !important;
        border: none !important;
        margin-bottom: 0 !important;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .menu-item.home-item:active i {
        transform: scale(0.95);
        box-shadow: 0 2px 5px rgba(255, 0, 92, 0.4) !important;
    }

    .menu-item.home-item span {
        display: none;
    }
        /* Dashboard side menu sync */

        /* Dashboard side menu sync REMOVED (Handled in main style block) */

    /* Dashboard/Profile theme sync */
    :root {
      --accent-blue: #000000;
      --accent-yellow: #000000;
      --accent-gold: #333333;
      --gradient-gold: linear-gradient(135deg, #e6e9f0, #eef1f5, #bdc3c7);
      --shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .hist-btn,
    .hist-btn.active,
    .info-text {
      color: #000000;
    }

    .hist-btn {
      border-color: #000000;
    }

    .hist-btn.active {
      background: linear-gradient(135deg, #e6e9f0, #eef1f5, #bdc3c7);
      border-color: #000000;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }

    .top-row-actions {
      display: flex;
      gap: 15px;
      margin-bottom: 15px;
    }

    .top-pill-btn {
      flex: 1;
      color: #ffffff;
      padding: 8px 13px;
      border-radius: var(--border-radius);
      display: flex;
      align-items: center;
      justify-content: space-between;
      text-decoration: none;
      font-weight: 700;
      font-size: 14px;
      min-height: auto;
    }

    .top-pill-btn.deposit {
      background: linear-gradient(to right, #e60000, #ff3333);
      box-shadow: 0 4px 10px rgba(230, 0, 0, 0.3);
    }

    .top-pill-btn.withdrawal {
      background: linear-gradient(to right, #000000, #333333);
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    }

    .top-pill-btn span {
      font-size: 14px;
      font-weight: 700;
      color: #ffffff;
      text-transform: none;
    }

    .play-circle {
      width: 31px;
      height: 31px;
      background: #ffffff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      flex-shrink: 0;
      box-shadow: none;
    }

    .play-circle i {
      color: #000000 !important;
      text-shadow: none !important;
      filter: none !important;
    }

    .modal .modal-content,
    .modal .modal-content h3,
    .modal .modal-content h4,
    .modal .modal-content p,
    .modal .modal-content label,
    .modal .modal-content span,
    .modal .modal-content strong,
    .modal .modal-content div,
    .modal .bank-lines,
    .modal .bank-lines strong,
    .modal .bank-note,
    .modal .upi-note,
    .modal .qr-id,
    .modal .crypto-addr span {
      color: #ffffff !important;
    }

    .modal .bonus-pill,
    .modal div[style*="background: #f9f9f9"],
    .modal div[style*="background:#f9f9f9"],
    .modal div[style*="background: #fff"],
    .modal div[style*="background:#fff"] {
      background: #1f1f1f !important;
      border-color: #444444 !important;
    }

    .modal .bonus-pill span,
    .modal div[style*="background: #f9f9f9"] span,
    .modal div[style*="background:#f9f9f9"] span,
    .modal div[style*="background: #fff"] span,
    .modal div[style*="background:#fff"] span {
      color: #ffffff !important;
    }

    .modal .payment-option .option.active {
      color: #000000 !important;
    }

    .modal .close-btn {
      color: #ffffff !important;
    }

    .top-pill-btn {
      min-height: auto;
      padding: 8px 13px;
    }

    .play-circle {
      width: 31px;
      height: 31px;
      font-size: 14px;
    }




    /* Dashboard exact side menu sync */
    .side-menu-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .6);
      display: none;
      z-index: 4000;
      backdrop-filter: blur(2px);
    }

    .side-menu {
      position: fixed;
      top: 0;
      left: -240px;
      width: min(240px, 80vw);
      height: 100%;
      background: #ffffff;
      color: #333333;
      padding: 0;
      transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 4100;
      box-shadow: 10px 0 30px rgba(0, 0, 0, 0.12);
      display: flex;
      flex-direction: column;
      border-right: 1px solid rgba(0, 0, 0, 0.06);
    }

    .side-menu.open { left: 0; }

    .side-menu.open { left: 0; }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body data-deactive="<?= $isDeactive ? '1' : '0' ?>"
  data-wallet="<?= htmlspecialchars(number_format((float) $totalBalance, 2, '.', ''), ENT_QUOTES) ?>"
  data-share-title="<?= htmlspecialchars($shareTitle, ENT_QUOTES) ?>"
  data-share-text="<?= htmlspecialchars($shareText, ENT_QUOTES) ?>"
  data-share-link="<?= htmlspecialchars($shareDownloadLink, ENT_QUOTES) ?>"
  data-share-imgs='<?= htmlspecialchars(json_encode($shareImgCandidates, JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>'
  data-default-tab="<?= htmlspecialchars($defaultDepositTab, ENT_QUOTES) ?>"
  data-wd-methods='<?= htmlspecialchars(json_encode($withdrawMethods, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>'
  data-bonus-rates='<?= htmlspecialchars(json_encode($bonusRatesForJs, JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>'
  data-min-dep="<?= (int) $MIN_DEPOSIT ?>" data-min-wd="<?= (int) $MIN_WITHDRAW ?>"
  data-flash="<?= htmlspecialchars($flashMsg, ENT_QUOTES) ?>" data-enable-intent="<?= (int) $enableUpiIntent ?>"
  data-intent-upi="<?= htmlspecialchars($intentUpi, ENT_QUOTES) ?>"
  data-intent-name="<?= htmlspecialchars($intentName !== '' ? $intentName : 'BABA JI', ENT_QUOTES) ?>"
  data-intent-return="<?= htmlspecialchars($intentReturnUrl, ENT_QUOTES) ?>"
  data-show-whatsapp="<?= (int) $showWhatsApp ?>" data-whatsapp-no="<?= htmlspecialchars($whatsappNo) ?>"
  data-user-mobile="<?= htmlspecialchars($userMobile) ?>" data-user-id="<?= htmlspecialchars($code) ?>"
  data-user-pk="<?= $uid ?>" data-show-pay0="<?= (int) $showPay0 ?>" data-show-imb="<?= (int) $showImb ?>"
  data-show-yuvipay="<?= (int) $showYuvipay ?>"
  data-withdraw-time-enabled="<?= (int)$enableWithdrawTime ?>"
  data-withdraw-start="<?= htmlspecialchars($withdrawStartTime) ?>"
  data-withdraw-end="<?= htmlspecialchars($withdrawEndTime) ?>"
  data-bank-approved="<?= $bankApproved ? '1' : '0' ?>"
  style="<?= $isDeactive ? 'background:#fff;padding:0;' : '' ?>">

  <?php if (!$isDeactive): ?>
    <!-- Header -->
    <header>
      <div class="header-left">
        <a class="back-btn" href="user_dashboard.php" title="Back">
          <i class="fa-solid fa-chevron-left"></i>
        </a>
        <div class="header-title-flash">
          <img src="icons/gold_coin.svg" alt="coin">
          Withdraw Fund
          <img src="icons/gold_coin.svg" alt="coin">
        </div>
      </div>
    </header>
    <!-- SIDE MENU -->
    <div class="side-menu-overlay" id="sideOverlay"></div>
    <div class="side-menu" id="sideMenu">
        <div class="side-menu-header">
            <a href="user_profile.php" class="profile-avatar"><i class="fi fi-rr-user"></i></a>
            <div class="profile-text">
                <div class="profile-name"><?= htmlspecialchars($userName) ?></div>
                <div class="profile-id"><?= htmlspecialchars($userMobile ?: $code) ?></div>

            </div>
            <button type="button" class="side-close-btn" id="sideCloseBtn"><i class="fas fa-times"></i></button>
        </div>

        <div class="side-menu-body">
            <a href="user_dashboard.php" class="side-menu-item">
                <i class="fi fi-rr-home"></i>
                <span class="label"><?= __("Home") ?></span>
            </a>
            <a href="user_profile.php" class="side-menu-item">
                <i class="fi fi-rr-user"></i>
                <span class="label"><?= __("Profile") ?></span>
            </a>
            <a href="price_chart.php" class="side-menu-item">
                <i class="fi fi-rr-stats"></i>
                <span class="label"><?= __("Game Rates") ?></span>
            </a>
            <a href="how_to_play.php" class="side-menu-item">
                <i class="fi fi-rr-play-alt"></i>
                <span class="label"><?= __("How to Play") ?></span>
            </a>

            <a href="user_wining.php" class="side-menu-item">
                <i class="fi fi-rr-trophy"></i>
                <span class="label"><?= __("Winning History") ?></span>
            </a>
            <a href="user_bid_history.php" class="side-menu-item">
                <i class="fi fi-rr-list"></i>
                <span class="label"><?= __("Bid History") ?></span>
            </a>
            <a href="user_panel_chart.php" class="side-menu-item">
                <i class="fi fi-rr-apps"></i>
                <span class="label"><?= __("Panel Chart") ?></span>
            </a>
            <a href="app/mahadevking.apk" download class="side-menu-item">
                <i class="fi fi-rr-download"></i>
                <span class="label"><?= __("Latest App") ?></span>
            </a>
        <a href="languages.php" class="side-menu-item">
            <i class="fa-solid fa-language"></i><span class="label"><?= __("Languages") ?></span>
        </a>
            <a href="javascript:void(0)" class="side-menu-item" id="shareBtn">
                <i class="fi fi-rr-share"></i>
                <span class="label"><?= __("Share App") ?></span>
            </a>
            <a href="user_logout.php" class="side-menu-item">
                <i class="fi fi-rr-sign-out-alt"></i>
                <span class="label"><?= __("Logout") ?></span>
            </a>
        </div>
    </div>

  <?php endif; ?>



  <?php if ($isDeactive): ?>
    <div class="deactive-banner">
      <span>Contact to Admin</span>
      <a href="https://wa.me/917020691217" target="_blank" rel="noopener">
        <i class="fa-brands fa-whatsapp"></i> Chat Now
      </a>
    </div>

    <div style="text-align:center;margin-top:10px;margin-bottom:10px;">
      <a href="user_logout.php" class="logout-btn" style="background:#4e342e;">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </div>

    <!-- ✅ NEW: Extra line under Chat Now (Deactive user) -->
    <div class="deactive-extra">
      Fast Result - Update New Market Contact to Admin
    </div>
  <?php endif; ?>

  <div id="customPopup" class="custom-popup"></div>

  <!-- ✅ Pay0 Gateway Loading Overlay -->
  <div id="pay0LoadingOverlay">
    <div class="pay0-loader-wrap">
      <div class="pay0-loader-ring"></div>
      <i class="pay0-loader-icon fas fa-lock"></i>
    </div>
    <div class="pay0-loader-title">🔐 Secure Gateway</div>
    <div class="pay0-loader-sub">Payment gateway खुल रहा है...<br>Please wait, don't press back.</div>
    <div class="pay0-loader-dots">
      <span></span><span></span><span></span>
    </div>
    <div class="pay0-secure-badge">
      <i class="fas fa-shield-alt"></i> 100% Safe & Secure Payment
    </div>
  </div>

  <div class="content">
    <?php if (!$isDeactive): ?>
      <div class="section-wrap" style="padding: 15px; max-width: 480px; margin: 0 auto;">

        <!-- Current Balance Card -->
        <div style="display: flex; align-items: center; gap: 15px; background: #fff; border-radius: 12px; padding: 15px; margin-bottom: 18px; box-shadow: 0 4px 10px rgba(0,0,0,0.04);">
          <div style="width: 45px; height: 45px; background: #ffebee; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #d32f2f; font-size: 22px;">
            <i class="fa-solid fa-wallet"></i>
          </div>
          <div>
            <div style="font-size: 24px; font-weight: 800; color: #8b0000; line-height: 1.2;">₹<?= number_format($totalBalance, 2) ?></div>
            <div style="font-size: 12px; color: #666; font-weight: 500; margin-top: 2px;">Current Balance</div>
          </div>
        </div>

        <!-- Withdraw Form -->
        <form id="withdrawForm" method="POST" action="withdrawal.php" onsubmit="return onSubmitWithdraw(event)">
          <!-- Enter Points input box styled as card -->
          <div style="display: flex; align-items: center; background: #fff; border: 1.5px solid #eaeaea; border-radius: 12px; padding: 12px 16px; margin-bottom: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.02);">
            <span style="font-size: 22px; color: #9e9e9e; font-weight: 600; margin-right: 12px; font-family: sans-serif;">₹</span>
            <input type="number" name="amount" id="withdrawAmount" min="<?= (int) $MIN_WITHDRAW ?>" required placeholder="Enter Points" style="border: none; outline: none; width: 100%; font-size: 16px; font-weight: 600; color: #333;">
          </div>

          <div style="margin-bottom: 15px;">
            <button type="button" id="wdSelectBtn" style="width: 100%; display: flex; align-items: center; justify-content: space-between; background: #fff; border: 1.5px solid #eaeaea; border-radius: 12px; padding: 12px 16px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); cursor: pointer;">
              <span style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-building-columns" style="color:#795548;"></i>
                <span id="wdSelectedLabel" style="font-size: 16px; font-weight: 900; color: #333333;"><span style="color: #333333 !important; font-weight: 900;"><?= __("Select Method") ?></span></span>
                <span id="wdSelectedBadge" class="badge" style="display:none; font-size: 12px; font-weight: 900; padding: 4px 10px; border-radius: 999px; border: 1px solid #e0e0e0; margin-left: 8px;"></span>
              </span>
              <i class="fa-solid fa-chevron-down" style="color: #795548; font-size: 14px;"></i>
            </button>


          <input type="hidden" name="method" id="withdrawMethodHidden" value="Bank">
          <input type="hidden" name="order_id" id="wdOrderId" value="">

          <div id="kycActions" class="kyc-actions" style="margin-bottom: 15px;">
            <button type="button" class="kyc-btn" onclick="goToKyc()" style="width:100%; border:1px solid #ff3b30; color:#ff3b30; background:transparent; padding:10px; border-radius:10px; font-weight:600; display:flex; align-items:center; justify-content:center; gap:8px; cursor:pointer;">
              <i class="fa-solid fa-shield-halved"></i> KYC / Link Complete Now
            </button>
          </div>

          <button type="submit" class="btn-submit-withdraw-funds" id="withdrawSubmitBtn">Submit Request</button>
        </form>

        <!-- History Ribbon -->
        <div style="background: #f0f4f8; padding: 12px 30px; margin: 20px -30px 15px; font-size: 14px; font-weight: 700; color: #78909c; border-top: 1px solid #e0e0e0; border-bottom: 1px solid #e0e0e0; text-align: left;">
          Fund Withdraw Request History
        </div>

        <!-- Withdrawal History Table -->
        <div id="withdrawHistory" style="margin-top:10px;">
          <div class="table-scroll-wrap" style="max-height: none; overflow-y: visible; border-radius: 12px; border: 1px solid rgba(0, 0, 0, 0.05); margin-bottom: 10px;">
            <table class="transaction-table" id="tableWithdraw">
              <thead>
                <tr>
                  <th><?= __("Sr") ?></th>
                  <th><?= __("Amount") ?></th>
                  <th><?= __("Date/Time") ?></th>
                  <th><?= __("Method") ?></th>
                  <th><?= __("Status") ?></th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($withdrawList)):
                  $j = 1;
                  foreach ($withdrawList as $w): ?>
                    <?php
                    $dt = (string) ($w['date_time_ist'] ?? $w['date_time'] ?? '');
                    $dtFmt = $dt !== '' ? date('d M h:i A', strtotime($dt)) : '';
                    $stClass = strtolower(trim((string) $w['status']));
                    ?>
                    <tr>
                      <td><?= $j++ ?></td>
                      <td>₹<?= number_format((float) $w['amount'], 2) ?></td>
                      <td style="font-size: 10px;"><?= $dtFmt ?></td>
                      <td style="font-size: 11px;"><?= htmlspecialchars(displayMethod($w['method'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td class="<?= $stClass ?>" style="font-size: 9px;"><?= __(ucfirst((string) $w['status'])) ?></td>
                    </tr>
                  <?php endforeach; else: ?>
                  <tr>
                    <td colspan="5"><?= __("No data found") ?></td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="pagination" id="pgWithdraw"></div>
        </div>

      </div>
    <?php endif; ?>
  </div>


  <audio id="notifySound" src="sound/notification.mp3" preload="auto"></audio>
  <audio id="chatSound" src="sound/message.mp3" preload="auto"></audio>

  <script>
var uId = <?php echo (int) ($uid ?? 0); ?>;
/* ===== AUDIO UNLOCK ===== */
(function () {
  var unlocked = false;
  function hardUnlockOne(a) {
    if (!a) return Promise.resolve();
    try {
      a.muted = true;a.currentTime = 0;
      return a.play().then(function () {a.pause();a.currentTime = 0;a.muted = false;}).catch(function () {a.muted = false;});
    } catch (e) {
      try {a.muted = false;} catch (_) {}
      return Promise.resolve();
    }
  }
  function unlock() {
    if (unlocked) return;
    unlocked = true;
    Promise.all([hardUnlockOne(document.getElementById('notifySound')), hardUnlockOne(document.getElementById('chatSound'))]).finally(function () {});
    window.removeEventListener('click', unlock, true);
    window.removeEventListener('touchstart', unlock, true);
    window.removeEventListener('pointerdown', unlock, true);
  }
  window.addEventListener('click', unlock, true);
  window.addEventListener('touchstart', unlock, true);
  window.addEventListener('pointerdown', unlock, true);
})();

/* popup */
function quickImb(amt) {
  var el = document.getElementById('amountImb');
  if (el) el.value = amt;
}
function quickYuvipay(amt) {
  var el = document.getElementById('amountYuvipay');
  if (el) el.value = amt;
}
function showPopup(t) {
  var p = document.getElementById('customPopup');
  if (!p) return;
  p.textContent = t;
  p.style.display = 'block';
  setTimeout(function () {p.style.display = 'none';}, 2200);
}

/* ✅ flash popup + clean URL */
(function () {
  var msg = (document.body.dataset.flash || '').trim();
  if (msg) {
    setTimeout(function () {showPopup(msg);}, 500);
  }
  try {
    var u = new URL(window.location.href);
    ['status', 'upi', 'order_id', 'transaction_id', 'tr', 'dep', 'wd', 'credited', 'reason', 'error'].forEach(function(k) { u.searchParams.delete(k); });
    history.replaceState({}, '', u.pathname + (u.search ? u.search : ''));
  } catch (e) {}
})();


/* SIDE MENU TOGGLE */
var menuBtn = document.getElementById('menuToggle');
var sideMenu = document.getElementById('sideMenu');
var sideOverlay = document.getElementById('sideOverlay');
var sideClose = document.getElementById('sideCloseBtn');

function openM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(false); } if(e && e.preventDefault) e.preventDefault();
  sideMenu.classList.add('open');
  sideOverlay.style.display = 'block';
  document.body.style.overflow = 'hidden';
}
function closeM(e) { if(window.AndroidNotify && window.AndroidNotify.setSwipeEnabled) { window.AndroidNotify.setSwipeEnabled(true); } if(e && e.preventDefault) e.preventDefault();
  sideMenu.classList.remove('open');
  sideOverlay.style.display = 'none';
  document.body.style.overflow = '';
}

if (menuBtn) menuBtn.addEventListener('click', openM);
if (sideOverlay) sideOverlay.addEventListener('click', closeM);
if (sideClose) sideClose.addEventListener('click', closeM);

/* SHARE */
function shareReferral() {
  var title = (document.body.dataset.shareTitle || 'Game Rates').trim();
  var text = (document.body.dataset.shareText || '').trim();
  if (typeof AndroidNotify !== "undefined" && AndroidNotify.shareImageFromUrl) {
    AndroidNotify.shareImageFromUrl(title, text, "");
  } else {
    var url = "https://subhgaming.online/download.php";
    var fullText = "*".concat(title, "*\n\n").concat(text, "\n\nDownload App:\n").concat(url);
    window.location.href = "whatsapp://send?text=".concat(encodeURIComponent(fullText));
  }
}

/* history toggle */
function showHistory(which) {
  var dep = document.getElementById('depositHistory');
  var wit = document.getElementById('withdrawHistory');
  var info = document.getElementById('infoText');
  var b1 = document.getElementById('btnDep');
  var b2 = document.getElementById('btnWd');
  if (!dep || !wit || !info || !b1 || !b2) return;

  if (which === 'wd') {
    dep.style.display = 'none';
    wit.style.display = 'block';
    info.textContent = '<?= __("Withdrawal History") ?>';
    b1.classList.remove('active');b2.classList.add('active');
  } else {
    dep.style.display = 'block';
    wit.style.display = 'none';
    info.textContent = '<?= __("Deposit History") ?>';
    b2.classList.remove('active');b1.classList.add('active');
  }
}

/* pagination */
function initPagination(tblId, pgId) {
  var table = document.getElementById(tblId);
  var pg = document.getElementById(pgId);
  if (!table || !pg) return;
  var rows = Array.from(table.querySelectorAll('tbody tr'));
  var currentlyShowing = 10;

  // Create persistent button
  var btn = document.createElement('div');
  btn.innerHTML = 'Loading...';
  btn.style.cssText = 'text-align:center; padding:15px; cursor:pointer; font-size:14px; font-weight:700; color:#8b0000; display:none; background:#fff; margin-top:5px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);';
  btn.onclick = function() {
    currentlyShowing += 10;
    render();
  };
  
  pg.innerHTML = '';
  pg.appendChild(btn);

  function render() {
    var visibleCount = 0;
    rows.forEach(function(r, index) {
      if (index < currentlyShowing) {
        r.style.display = '';
        visibleCount++;
      } else {
        r.style.display = 'none';
      }
    });
    
    if (visibleCount < rows.length) {
      btn.style.display = 'block';
    } else {
      btn.style.display = 'none';
    }
  }
  
  render();
}
document.addEventListener('DOMContentLoaded', function () {

  /* Animated Live Feed JS */
  function initLiveFeed() {
    var contDep = document.getElementById('liveFeedContainerDeposit');
    var contWd = document.getElementById('liveFeedContainerWithdraw');

    var fNames = [
      /* Hindu */
      'Ayush R.','Rahul K.','Amit S.','Sandeep M.','Ravi B.','Vikram P.','Rohit A.','Deepak T.','Suresh Y.','Manish L.',
      'Rajesh G.','Anil V.','Sanjay D.','Naresh C.','Mahesh B.','Dinesh R.','Ramesh P.','Ganesh S.','Yogesh K.','Prakash J.',
      'Pooja T.','Sneha P.','Kavita M.','Priya W.','Neha S.','Sunita R.','Anita K.','Rekha D.','Geeta V.','Suman G.',
      'Asha B.','Meena T.','Lata S.','Usha P.','Kiran R.','Nisha M.','Ritu A.','Priti C.','Neeraj B.','Vishal J.',
      /* Muslim */
      'Mohammad A.','Salman K.','Imran H.','Arbaaz S.','Faisal R.','Junaid M.','Khalid A.','Rashid B.','Shahrukh N.','Irfan T.',
      'Aslam Q.','Rizwan F.','Karim U.','Nasir H.','Yasir P.','Shahid A.','Farooq B.','Tariq M.','Aqib R.','Zubair K.',
      'Fatima A.','Ayesha K.','Sana M.','Nazia R.','Rukhsar B.','Saima N.','Reshma S.','Aisha T.','Zara H.','Mehreen F.',
      'Shabnam R.','Noor A.','Hina K.','Razia M.','Salma B.',
      /* Christian */
      'Anthony D.','Robin F.','Kevin J.','Aaron M.','Mathew T.','Samuel P.','Daniel S.','Joseph K.','Abraham L.','Emmanuel R.',
      'Preethi A.','Lavanya J.','Jessica M.','Linda P.','Nancy K.','Susan T.','Maria D.','Christina S.','Anita F.','Rachel B.',
      'Benson K.','Johnson P.','Clement R.','Aloysius M.','Benedict S.',
      /* Sikh */
      'Gurpreet S.','Harpreet S.','Manpreet S.','Jaspreet S.','Kulwinder S.','Sukhwinder S.','Balwinder S.','Narinder S.','Paramjit S.','Tejinder S.',
      'Gurdeep K.','Hardeep K.','Gagandeep K.','Ramandeep K.','Kirandeep K.','Simran K.','Rupinder K.','Maninder K.','Amandeep K.','Navneet K.',
      /* Jain */
      'Nitin J.','Saurabh J.','Paresh S.','Hitesh S.','Mihir S.','Jignesh P.','Nikhil S.','Raju J.','Ashish M.','Kapil J.',
      'Dimple S.','Hetal J.','Mitali P.','Jinal S.','Foram J.',
      /* Repeats for realism */
      'Rahul K.','Amit S.','Sneha P.','Pooja T.','Rohit A.','Mohammad A.','Gurpreet S.','Salman K.','Priya W.','Vikram P.',
      'Ayush R.','Fatima A.','Simran K.','Ayesha K.','Manish L.','Kevin J.','Nitin J.','Sukhwinder S.','Neha S.','Deepak T.'
    ];
    var fTimes = ['<?= __("just now") ?>', '<?= __("1 min ago") ?>', '<?= __("2 min ago") ?>', '<?= __("3 min ago") ?>', '<?= __("4 min ago") ?>', '<?= __("5 min ago") ?>'];
    var realWithdrawals = <?php echo json_encode($realWithdrawals); ?>;
    var minWdLimit = <?php echo (int) ($pay['min_withdraw'] ?? 1000); ?>;
    var rawRoundFigures = [500, 1000, 1500, 2000, 2500, 3000, 4000, 5000, 6000, 8000, 10000, 12000, 15000];
    var roundFigures = rawRoundFigures.filter(function(amt) { return amt >= minWdLimit; });
    if (roundFigures.length === 0) roundFigures = [minWdLimit];

    function getRandomWithdrawalItem() {
      if (realWithdrawals && realWithdrawals.length > 0 && Math.random() < 0.3) {
        var idx = Math.floor(Math.random() * realWithdrawals.length);
        return realWithdrawals[idx];
      }
      var name = fNames[Math.floor(Math.random() * fNames.length)];
      var rawAmt;
      if (Math.random() < 0.85) {
        rawAmt = roundFigures[Math.floor(Math.random() * roundFigures.length)];
      } else {
        var maxWd = 15000;
        if (maxWd < minWdLimit) maxWd = minWdLimit + 5000;
        rawAmt = Math.floor(Math.random() * (maxWd - minWdLimit + 1) + minWdLimit);
        rawAmt = Math.round(rawAmt / 100) * 100;
        if (rawAmt < minWdLimit) rawAmt = minWdLimit;
      }
      var amount = '-₹' + rawAmt.toLocaleString('en-IN');
      var time = fTimes[Math.floor(Math.random() * fTimes.length)];
      return { name: name, amount: amount, time: time };
    }

    function createWithdrawalElement(data) {
      var el = document.createElement('div');
      el.className = 'feed-item withdrawal';
      el.style.cssText = 'opacity:0; transform:translateY(30px) scale(0.97); transition:opacity 0.4s ease, transform 0.4s ease;';
      el.innerHTML =
        '<div class="feed-item-left">' +
          '<div class="feed-icon"><i class="fa-solid fa-arrow-up"></i></div>' +
          '<div class="feed-details">' +
            '<div class="feed-name">' + data.name + '</div>' +
            '<div class="feed-action"><?= __("Amount withdrawn") ?></div>' +
          '</div>' +
        '</div>' +
        '<div class="feed-item-right">' +
          '<div class="feed-amount withdrawal-color">' + data.amount + '</div>' +
          '<div class="feed-time">' + data.time + '</div>' +
        '</div>';
      return el;
    }

    var wdCont = document.getElementById('liveFeedContainerWithdraw');
    if (wdCont) {
      for (var i = 0; i < 4; i++) {
        var el = createWithdrawalElement(getRandomWithdrawalItem());
        wdCont.appendChild(el);
        (function(e, delay) {
          setTimeout(function() {
            e.style.opacity = '1';
            e.style.transform = 'translateY(0) scale(1)';
          }, delay);
        })(el, i * 120);
      }

      function rotateFeed() {
        var items = wdCont.querySelectorAll('.feed-item');
        if (items.length > 0) {
          var top = items[0];
          top.style.transition = 'opacity 0.35s ease, transform 0.35s ease, max-height 0.35s ease, margin-bottom 0.35s ease, padding 0.35s ease';
          top.style.opacity = '0';
          top.style.transform = 'translateY(-30px) scale(0.95)';
          top.style.maxHeight = top.offsetHeight + 'px';
          setTimeout(function() {
            top.style.maxHeight = '0';
            top.style.marginBottom = '0';
            top.style.paddingTop = '0';
            top.style.paddingBottom = '0';
            top.style.overflow = 'hidden';
          }, 300);
          setTimeout(function() {
            if (top.parentNode) top.parentNode.removeChild(top);
            var newEl = createWithdrawalElement(getRandomWithdrawalItem());
            wdCont.appendChild(newEl);
            requestAnimationFrame(function() {
              requestAnimationFrame(function() {
                newEl.style.opacity = '1';
                newEl.style.transform = 'translateY(0) scale(1)';
              });
            });
          }, 600);
        }
        setTimeout(rotateFeed, Math.floor(Math.random() * 1000) + 2000);
      }
      setTimeout(rotateFeed, Math.floor(Math.random() * 1000) + 2000);
    }

    function getBaseActivePlayers() {
      var cacheStr = localStorage.getItem('activePlayersCache');
      var hour = new Date().getHours();
      if (cacheStr) {
        try {
          var cache = JSON.parse(cacheStr);
          if (cache.hour === hour && cache.value) {
            return parseInt(cache.value, 10);
          }
        } catch (e) {}
      }

      var val;
      if (hour < 6) {
        val = Math.floor(Math.random() * (20 - 10 + 1)) + 10;
      } else if (hour < 12) {
        val = Math.floor(Math.random() * (1500 - 1000 + 1)) + 1000;
      } else if (hour < 18) {
        val = Math.floor(Math.random() * (15000 - 5000 + 1)) + 5000;
      } else {
        val = Math.floor(Math.random() * (17000 - 10000 + 1)) + 10000;
      }

      localStorage.setItem('activePlayersCache', JSON.stringify({ hour: hour, value: val }));
      return val;
    }
    var currentActivePlayers = getBaseActivePlayers();
    var countEls = document.querySelectorAll('.activePlayerCountText');
    countEls.forEach(function(el) { el.textContent = currentActivePlayers.toLocaleString('en-IN'); });

    var nextEvent = Math.random() > 0.5 ? 'drop' : 'spike';

    function updatePlayerCount() {
      if (countEls.length > 0) {
        var diff = Math.floor(Math.random() * 5) - 2;

        // 5% chance of a sudden big event
        if (Math.random() < 0.05) {
          if (nextEvent === 'drop') {
            diff -= Math.floor(Math.random() * 50) + 30; // drop 30-80
            nextEvent = 'spike';
          } else {
            diff += Math.floor(Math.random() * 50) + 30; // spike 30-80
            nextEvent = 'drop';
          }
        }

        currentActivePlayers += diff;
        if (currentActivePlayers < 100) currentActivePlayers = 100;
        countEls.forEach(function(el) { el.textContent = currentActivePlayers.toLocaleString('en-IN'); });

        var hour = new Date().getHours();
        localStorage.setItem('activePlayersCache', JSON.stringify({ hour: hour, value: currentActivePlayers }));
      }
      setTimeout(updatePlayerCount, Math.floor(Math.random() * 1000) + 1000);
    }
    setTimeout(updatePlayerCount, Math.floor(Math.random() * 1000) + 1000);

    // Live polling for real user withdrawals
    var lastWithdrawalId = <?php echo (int)$maxWithdrawalId; ?>;
    function addRealWithdrawalToFeed(data) {
      var wdCont = document.getElementById('liveFeedContainerWithdraw');
      if (!wdCont) return;

      var newEl = createWithdrawalElement(data);
      // Prepend so it shows at the top immediately
      if (wdCont.firstChild) {
        wdCont.insertBefore(newEl, wdCont.firstChild);
      } else {
        wdCont.appendChild(newEl);
      }

      // Trigger animation in
      requestAnimationFrame(function() {
        requestAnimationFrame(function() {
          newEl.style.opacity = '1';
          newEl.style.transform = 'translateY(0) scale(1)';
        });
      });

      // Remove bottom element if > 4 items
      var items = wdCont.querySelectorAll('.feed-item');
      if (items.length > 4) {
        var last = items[items.length - 1];
        if (last && last.parentNode) {
          last.parentNode.removeChild(last);
        }
      }
    }

    function pollNewWithdrawals() {
      fetch('withdrawal.php?get_new_withdrawals=1&last_id=' + lastWithdrawalId)
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (data && data.length > 0) {
            data.forEach(function(item) {
              if (item.id > lastWithdrawalId) {
                lastWithdrawalId = item.id;
              }
              addRealWithdrawalToFeed(item);
            });
          }
        })
        .catch(function(err) {});
      setTimeout(pollNewWithdrawals, 5000);
    }
    setTimeout(pollNewWithdrawals, 5000);
  }
  initLiveFeed();

  if (document.body.dataset.deactive !== '1') {
    initPagination('tableDeposit', 'pgDeposit');
    initPagination('tableWithdraw', 'pgWithdraw');

    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'deposit') {
      openModal('depositModal');
    } else if (urlParams.get('action') === 'withdraw') {
      openModal('withdrawModal');
    }
  }
});

/* ✅ MODAL: ONLY X se close */
function lockBody() {document.body.classList.add('modal-open');}
function unlockBody() {document.body.classList.remove('modal-open');}

function openModal(id) {
  if (document.body.dataset.deactive === '1') {showPopup('Contact to Admin');return false;}
  lockBody();
  var modal = document.getElementById(id);
  if (modal) modal.classList.add('show');

  if (id === 'depositModal') {
    var def = (document.body.dataset.defaultTab || 'bank').toLowerCase();
    if (document.querySelector('.payment-option .option[data-method="intent"]')) {
      def = 'intent';
    }
    showMethod(def);
  }
}
function closeModal() {
  document.querySelectorAll('.modal').forEach(function(m) { m.classList.remove('show'); });
  closeWdDropdown();
  unlockBody();
}

/* ✅ Deposit method state */
var CURRENT_DEP_TAB = 'bank';

/* ✅ deposit method switch: send canonical enum to deposit.php */
function showMethod(m) {
  m = (m || 'bank').toLowerCase().trim();
  CURRENT_DEP_TAB = m;

  var bankSec = document.getElementById('secBank');
  var upiSec = document.getElementById('secUpi');
  var cryptoSec = document.getElementById('secCrypto');
  var intentSec = document.getElementById('secIntent');
  var whatsappSec = document.getElementById('secWhatsapp');
  var pay0Sec = document.getElementById('secPay0');
  var imbSec = document.getElementById('secImb');
  var yuvipaySec = document.getElementById('secYuvipay');

  if (bankSec) bankSec.classList.toggle('active', m === 'bank');
  if (upiSec) upiSec.classList.toggle('active', m === 'upi');
  if (cryptoSec) cryptoSec.classList.toggle('active', m === 'crypto');
  if (intentSec) intentSec.classList.toggle('active', m === 'intent');
  if (whatsappSec) whatsappSec.classList.toggle('active', m === 'whatsapp');
  if (pay0Sec) pay0Sec.classList.toggle('active', m === 'pay0');
  if (imbSec) imbSec.classList.toggle('active', m === 'imb');
  if (yuvipaySec) yuvipaySec.classList.toggle('active', m === 'yuvipay');

  // ✅ Disable inactive file inputs so they don't override the active one
  document.querySelectorAll('#depositForm .bank-section input[type="file"], #depositForm .qr-section input[type="file"], #depositForm .crypto-section input[type="file"]').forEach(function(f) { f.disabled = true; });
  var activeSec = m === 'bank' ? bankSec : m === 'upi' ? upiSec : m === 'crypto' ? cryptoSec : null;
  if (activeSec) {
    activeSec.querySelectorAll('input[type="file"]').forEach(function(f) { f.disabled = false; });
  }

  var methodInput = document.getElementById('methodInput');
  if (methodInput) {
    if (m === 'bank') methodInput.value = 'BANK';else
    if (m === 'upi') methodInput.value = 'UPI';else
    if (m === 'intent') methodInput.value = 'UPI_INTENT';else
    if (m === 'crypto') methodInput.value = 'CRYPTO';else
    if (m === 'whatsapp') methodInput.value = 'WHATSAPP';else
    if (m === 'pay0') methodInput.value = 'UPI ZERO';else
    if (m === 'imb') methodInput.value = 'IMB';else
    if (m === 'yuvipay') methodInput.value = 'UPI YUVI';else
    methodInput.value = 'BANK';
  }

  var depositSubmitBtn = document.getElementById('depositSubmitBtn');
  if (depositSubmitBtn) {
    if (m === 'intent') {
      depositSubmitBtn.style.display = 'none';
    } else {
      depositSubmitBtn.style.display = 'block';
      if (m === 'imb') {
        depositSubmitBtn.style.background = '#1976d2';
      } else if (m === 'yuvipay') {
        depositSubmitBtn.style.background = '#2e7d32';
      } else {
        depositSubmitBtn.style.background = '#c62828';
      }
    }
  }

  document.querySelectorAll('.payment-option .option').forEach(function (o) {
    var isMatch = (o.getAttribute('data-method') || '') === m;
    o.classList.toggle('active', isMatch);
    if (isMatch) {
      if (m === 'imb') o.style.color = '#1976d2';else
      if (m === 'yuvipay') o.style.color = '#2e7d32';else
      o.style.color = 'var(--red)';
    } else {
      o.style.color = ''; // reset inactive
    }
  });
}
document.addEventListener('DOMContentLoaded', function () {
  var def = (document.body.dataset.defaultTab || 'bank').toLowerCase();
  if (document.querySelector('.payment-option .option[data-method="intent"]')) {
    def = 'intent';
  }
  showMethod(def);
});

/* ✅ Pay0: Set + Submit (Forceful - directly opens gateway) */
function quickPay0(v) {
  // ✅ Force tab to pay0 FIRST
  CURRENT_DEP_TAB = 'pay0';
  showMethod('pay0');

  var inp = document.getElementById('amountPay0');
  if (inp) inp.value = String(v);

  // ✅ Show loading overlay immediately
  (function(){ var el = document.getElementById('pay0LoadingOverlay'); if(el) el.classList.add('show'); })()

  // ✅ Now trigger submit
  onSubmitDeposit(new Event('submit', { cancelable: true }));
}

/* ✅ YuviPay: Set + Submit */
/* function quickYuvipay(v) {
  CURRENT_DEP_TAB = 'yuvipay';
  showMethod('yuvipay');
   var inp = document.getElementById('amountYuvipay');
  if (inp) inp.value = String(v);
   (function(){ var el = document.getElementById('pay0LoadingOverlay'); if(el) el.classList.add('show'); })()
   onSubmitDeposit(new Event('submit', { cancelable: true }));
}
 // ✅ IMB: Set + Submit
function quickImb(v) {
  CURRENT_DEP_TAB = 'imb';
  showMethod('imb');
   var inp = document.getElementById('amountImb');
  if (inp) inp.value = String(v);
   (function(){ var el = document.getElementById('pay0LoadingOverlay'); if(el) el.classList.add('show'); })() // reuse overlay
   onSubmitDeposit(new Event('submit', { cancelable: true }));
} */








/* ✅ Quick amount setter (Intent) */
function setIntentAmount(v) {
  var el = document.getElementById('amountIntent');
  if (el) el.value = v;
}

// ✅ Direct UPI Intent handler — called directly from button onclick (NOT via form submit)
// This gives Chrome Android the MOST DIRECT user gesture → deep link chain
function openUpiIntent(btn) {
  var upi = (document.body.dataset.intentUpi || '').trim();
  var name = (document.body.dataset.intentName || 'BABA JI').trim();
  var retBase = (document.body.dataset.intentReturn || '').trim();
  var min = parseInt(document.body.dataset.minDep || '1', 10) || 1;
  var amt = parseFloat((document.getElementById('amountIntent') ? document.getElementById('amountIntent').value : '') || '0') || 0;

  if (!upi) {showPopup('UPI not configured. Contact admin.');return;}
  if (amt < min) {showPopup('Minimum deposit ₹' + min);return;}

  // Disable button to prevent double-tap
  var oldTxt = btn ? btn.textContent : '';
  if (btn) {btn.disabled = true;btn.textContent = '⏳ Opening UPI...';}

  var oid = makeOrderId('LL');

  // ✅ Build full return URL
  var retUrl = retBase ?
  retBase + '&order_id=' + encodeURIComponent(oid) :
  'https://subhgaming.online/deposit.php?upi=SUCCESS&order_id=' + encodeURIComponent(oid);

  // ✅ Build UPI deep link
  var upiLink =
  'upi://pay?pa=' + encodeURIComponent(upi) +
  '&pn=' + encodeURIComponent(name) +
  '&tr=' + encodeURIComponent(oid) +
  '&tn=' + encodeURIComponent('Wallet Deposit') +
  '&am=' + encodeURIComponent(amt.toFixed(2)) +
  '&cu=INR';

  // ✅ STEP 1: Fire deep link INSTANTLY (zero delay - direct user tap → deep link)
  window.location.href = upiLink;

  // ✅ STEP 2: Save Pending record AFTER deep link fires
  // fetch with keepalive=true specifically designed to survive page navigation
  setTimeout(function () {
    try {
      var fd = new FormData();
      fd.set('method', 'UPI_INTENT');
      fd.set('order_id', oid);
      fd.set('amount', amt.toFixed(2));
      fetch('deposit.php', { method: 'POST', body: fd, keepalive: true });
    } catch (e) {}
  }, 0); // setTimeout(0) = queues AFTER current tick but BEFORE page unloads

  // ✅ STEP 3: Re-enable button after 3s if page is still focused (app didn't open)
  setTimeout(function () {
    if (btn && document.visibilityState === 'visible') {
      btn.disabled = false;
      btn.textContent = '🔄 Tap Again to Open UPI';
    }
  }, 3000);
}

function openAppSpecificUpiIntent(appKey, imgEl) {
  var upi = (document.body.dataset.intentUpi || '').trim();
  var name = (document.body.dataset.intentName || 'BABA JI').trim();
  var retBase = (document.body.dataset.intentReturn || '').trim();
  var min = parseInt(document.body.dataset.minDep || '1', 10) || 1;
  var amt = parseFloat((document.getElementById('amountIntent') ? document.getElementById('amountIntent').value : '') || '0') || 0;

  if (!upi) {showPopup('UPI not configured. Contact admin.');return;}
  if (amt < min) {showPopup('Minimum deposit ₹' + min);return;}

  var overlay = document.getElementById('intentAppLoaderOverlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'intentAppLoaderOverlay';
    overlay.style = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.9);z-index:99999;display:flex;flex-direction:column;justify-content:center;align-items:center;color:#fff;font-family:sans-serif;backdrop-filter:blur(6px);';
    overlay.innerHTML = "\n          <div id=\"intentLoaderSpinner\" style=\"width:55px;height:55px;border:5px solid rgba(255,255,255,0.2);border-top-color:#39ff14;border-radius:50%;animation:spin 1s linear infinite;margin-bottom:20px;box-shadow:0 0 15px rgba(57,255,20,0.4);\"></div>\n          <div id=\"intentLoaderTitle\" style=\"font-size:18px;font-weight:bold;margin-bottom:10px;letter-spacing:0.5px;\">Connecting Securely...</div>\n          <div id=\"intentAppLoaderSubtext\" style=\"font-size:13px;color:#ccc;text-align:center;padding:0 20px;margin-bottom:25px;line-height:1.4;\">Opening UPI App...</div>\n          \n          <div id=\"intentOverlayActions\" style=\"display:none;flex-direction:column;gap:12px;width:85%;max-width:320px;\">\n             <button onclick=\"resolveIntentStatus('Done')\" style=\"background:#2e7d32;color:#fff;border:none;padding:14px;border-radius:10px;font-weight:bold;font-size:15px;cursor:pointer;box-shadow:0 4px 12px rgba(46,125,50,0.3);\">✅ Payment Success (Done)</button>\n             <button onclick=\"resolveIntentStatus('Failed')\" style=\"background:#c62828;color:#fff;border:none;padding:14px;border-radius:10px;font-weight:bold;font-size:15px;cursor:pointer;box-shadow:0 4px 12px rgba(198,40,40,0.3);\">❌ Cancelled / Back (Failed)</button>\n             <button onclick=\"resolveIntentStatus('Pending')\" style=\"background:#ef6c00;color:#fff;border:none;padding:14px;border-radius:10px;font-weight:bold;font-size:15px;cursor:pointer;box-shadow:0 4px 12px rgba(239,108,0,0.3);\">⏳ Pay Later (Pending)</button>\n          </div>\n          <style>@keyframes spin{to{transform:rotate(360deg);}}</style>\n        ";











    document.body.appendChild(overlay);
  }

  overlay.style.display = 'flex';
  var spinner = document.getElementById('intentLoaderSpinner');
  var title = document.getElementById('intentLoaderTitle');
  var sub = document.getElementById('intentAppLoaderSubtext');
  var actions = document.getElementById('intentOverlayActions');

  spinner.style.display = 'block';
  actions.style.display = 'none';
  overlay.style.background = 'rgba(0,0,0,0.9)';

  title.innerText = 'Connecting Securely...';
  sub.innerText = 'Launching ' + appKey + ' to send ₹' + amt + '...';

  var oid = makeOrderId('LL');

  var packages = {
    'phonepe': 'com.phonepe.app',
    'gpay': 'com.google.android.apps.nbu.paisa.user',
    'paytm': 'net.one97.paytm',
    'bhim': 'in.org.npci.upiapp',
    'amazonpay': 'in.amazon.mShop.android.shopping',
    'mobikwik': 'com.mobikwik_new',
    'jiofinance': 'com.jio.finance'
  };

  var packageId = packages[appKey.toLowerCase()] || '';

  var upiLink = 'upi://pay?pa=' + encodeURIComponent(upi) +
  '&pn=' + encodeURIComponent(name) +
  '&tr=' + encodeURIComponent(oid) +
  '&tn=' + encodeURIComponent('Wallet Deposit') +
  '&am=' + encodeURIComponent(amt.toFixed(2)) +
  '&cu=INR';

  var isAndroid = /android/i.test(navigator.userAgent);
  if (appKey.toLowerCase() === 'paytm') {
    upiLink = 'paytmmp://pay?pa=' + encodeURIComponent(upi) +
    '&pn=' + encodeURIComponent(name) +
    '&tr=' + encodeURIComponent(oid) +
    '&tn=' + encodeURIComponent('Wallet Deposit') +
    '&am=' + encodeURIComponent(amt.toFixed(2)) +
    '&cu=INR';
  } else if (isAndroid && packageId && appKey.toLowerCase() !== 'jiofinance') {
    upiLink = 'intent://pay' +
    '?pa=' + encodeURIComponent(upi) +
    '&pn=' + encodeURIComponent(name) +
    '&tr=' + encodeURIComponent(oid) +
    '&tn=' + encodeURIComponent('Wallet Deposit') +
    '&am=' + encodeURIComponent(amt.toFixed(2)) +
    '&cu=INR' +
    '#Intent;scheme=upi;package=' + packageId + ';end';
  }

  try {
    var fd = new FormData();
    fd.set('method', 'UPI_INTENT');
    fd.set('order_id', oid);
    fd.set('amount', amt.toFixed(2));
    fetch('deposit.php', { method: 'POST', body: fd, keepalive: true });
  } catch (e) {}

  setTimeout(function () {
    window.location.href = upiLink;

    var onFocusBack = function () {
      spinner.style.display = 'none';
      title.innerText = 'Confirm Payment Status';
      sub.innerText = 'Please select your payment action to update your wallet accurately.';
      actions.style.display = 'flex';
      window.removeEventListener('focus', onFocusBack);
    };

    setTimeout(function () {
      window.addEventListener('focus', onFocusBack);
      document.addEventListener('visibilitychange', function handleVis() {
        if (document.visibilityState === 'visible') {
          onFocusBack();
          document.removeEventListener('visibilitychange', handleVis);
        }
      });
    }, 1500);

  }, 800);
}

function resolveIntentStatus(status) {
  var overlay = document.getElementById('intentAppLoaderOverlay');
  var title = document.getElementById('intentLoaderTitle');
  var sub = document.getElementById('intentAppLoaderSubtext');
  var actions = document.getElementById('intentOverlayActions');

  actions.style.display = 'none';

  if (status === 'Done') {
    overlay.style.background = 'rgba(27,94,32,0.95)';
    title.innerText = '🎉 Status: DONE';
    sub.innerText = 'Payment request recorded successfully. Updating points shortly.';
    setTimeout(function () {overlay.style.display = 'none';location.reload();}, 2500);
  } else if (status === 'Failed') {
    overlay.style.background = 'rgba(183,28,28,0.95)';
    title.innerText = '❌ Status: FAILED';
    sub.innerText = 'Your transaction was flagged as cancelled.';
    setTimeout(function () {overlay.style.display = 'none';}, 2500);
  } else {
    overlay.style.background = 'rgba(33,33,33,0.95)';
    title.innerText = '⏳ Status: PENDING';
    sub.innerText = 'Continuing to standard assessment thresholds.';
    setTimeout(function () {overlay.style.display = 'none';}, 2500);
  }
}

/* crypto helpers */
function updateFromInr() {
  var inrField = document.getElementById('amountCryptoInr');
  var tokenField = document.getElementById('amountCryptoToken');
  if (!inrField || !tokenField) return;
  var rate = <?= json_encode((float) $cryptoRate) ?>;
  var inr = parseFloat(inrField.value) || 0;
  tokenField.value = rate > 0 && inr > 0 ? (inr / rate).toFixed(4) : '';
}
function updateFromToken() {
  var inrField = document.getElementById('amountCryptoInr');
  var tokenField = document.getElementById('amountCryptoToken');
  if (!inrField || !tokenField) return;
  var rate = <?= json_encode((float) $cryptoRate) ?>;
  var tok = parseFloat(tokenField.value) || 0;
  inrField.value = rate > 0 && tok > 0 ? (tok * rate).toFixed(2) : '';
}
function copyCryptoAddress() {
  var span = document.getElementById('cryptoAddressText');
  if (!span) return;
  var text = span.textContent.trim();
  if (!text) return;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(function () {showPopup('USDT address copied');}).catch(function () {fallbackCopy(text);});
  } else fallbackCopy(text);
}
function copyUpiIdText() {
  var span = document.getElementById('upiIdVal');
  if (!span) return;
  var text = span.textContent.trim();
  if (!text) return;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(function () {showPopup('UPI ID copied');}).catch(function () {fallbackCopy(text);});
  } else fallbackCopy(text);
}
function fallbackCopy(text) {
  var temp = document.createElement('textarea');
  temp.value = text;
  temp.style.position = 'fixed';
  temp.style.left = '-9999px';
  document.body.appendChild(temp);
  temp.select();
  try {document.execCommand('copy');} catch (e) {}
  document.body.removeChild(temp);
  showPopup('USDT address copied');
}

/* ✅ Order ID generator */
function makeOrderId(prefix) {
  var ts = Date.now();
  var rnd = Math.floor(Math.random() * 900000 + 100000);
  return "".concat(prefix).concat(ts).concat(rnd);
}

/* ✅ Deposit submit */
function onSubmitDeposit(e) {
  e.preventDefault();
  var min = parseInt(document.body.dataset.minDep || '1', 10) || 1;

  var tab = (CURRENT_DEP_TAB || 'bank').toLowerCase();
  showMethod(tab);

  var amt = 0;
  if (tab === 'bank') amt = parseFloat((document.getElementById('amountBank') ? document.getElementById('amountBank').value : '') || '0') || 0;else
  if (tab === 'upi') amt = parseFloat((document.getElementById('amountUpi') ? document.getElementById('amountUpi').value : '') || '0') || 0;else
  if (tab === 'intent') amt = parseFloat((document.getElementById('amountIntent') ? document.getElementById('amountIntent').value : '') || '0') || 0;else
  if (tab === 'crypto') amt = parseFloat((document.getElementById('amountCryptoInr') ? document.getElementById('amountCryptoInr').value : '') || '0') || 0;else
  if (tab === 'whatsapp') amt = parseFloat((document.getElementById('amountWhatsapp') ? document.getElementById('amountWhatsapp').value : '') || '0') || 0;else
  if (tab === 'pay0') amt = parseFloat((document.getElementById('amountPay0') ? document.getElementById('amountPay0').value : '') || '0') || 0;else
  if (tab === 'imb') amt = parseFloat((document.getElementById('amountImb') ? document.getElementById('amountImb').value : '') || '0') || 0;else
  if (tab === 'yuvipay') amt = parseFloat((document.getElementById('amountYuvipay') ? document.getElementById('amountYuvipay').value : '') || '0') || 0;

  if (amt < min) {showPopup('Minimum deposit ₹' + min);return false;}

  var hidden = document.getElementById('amountHidden');
  if (hidden) hidden.value = String(amt.toFixed(2));

  var btn = document.getElementById('depositSubmitBtn');
  var btnOld = btn ? btn.textContent : '';
  if (btn) {btn.disabled = true;btn.textContent = 'Processing... Please Wait';}

  var form = document.getElementById('depositForm');

  // ✅ UPI Intent
  if (tab === 'intent') {
    var upi = (document.body.dataset.intentUpi || '').trim();
    var name = (document.body.dataset.intentName || 'BABA JI').trim();
    var retBase = (document.body.dataset.intentReturn || '').trim();

    if (!upi) {
      showPopup('UPI Intent not configured. Please contact admin.');
      if (btn) {btn.disabled = false;btn.textContent = btnOld || '<?= __("Proceed / Submit") ?>';}
      return false;
    }

    var oid = makeOrderId('LL');
    var depOid = document.getElementById('depOrderId');
    if (depOid) depOid.value = oid;

    // ✅ Build return URL with full domain so UPI app knows where to redirect
    var retUrl = retBase ?
    retBase + '&order_id=' + encodeURIComponent(oid) :
    'https://subhgaming.online/deposit.php?upi=SUCCESS&order_id=' + encodeURIComponent(oid);

    // ✅ Build standard UPI deep link
    var upiLink =
    'upi://pay?pa=' + encodeURIComponent(upi) +
    '&pn=' + encodeURIComponent(name) +
    '&tr=' + encodeURIComponent(oid) +
    '&tn=' + encodeURIComponent('Wallet Deposit') +
    '&am=' + encodeURIComponent(amt.toFixed(2)) +
    '&cu=INR' +
    '&url=' + encodeURIComponent(retUrl);

    // ✅ STEP 1: Create Pending record FIRST via sendBeacon
    // sendBeacon is designed to work even when page navigates away — it MUST fire before the deep link!
    try {
      var fd2 = new FormData();
      fd2.set('method', 'UPI_INTENT');
      fd2.set('order_id', oid);
      fd2.set('amount', amt.toFixed(2));
      if (navigator.sendBeacon) {
        navigator.sendBeacon('deposit.php', fd2);
      } else {
        fetch('deposit.php', { method: 'POST', body: fd2, keepalive: true });
      }
    } catch (e) {}

    // ✅ STEP 2: Fire deep link AFTER sendBeacon is queued (both in sync — gesture still valid!)
    window.location.href = upiLink;

    return false;
  }

  // ✅ WhatsApp Deposit
  if (tab === 'whatsapp') {
    var waNo = (document.body.dataset.whatsappNo || '').trim();
    var uMob = (document.body.dataset.userMobile || '').trim();
    var uId = (document.body.dataset.userId || '').trim();

    if (!waNo) {
      showPopup('WhatsApp Support not configured');
      if (btn) {btn.disabled = false;btn.textContent = btnOld || '<?= __("Proceed / Submit") ?>';}
      return false;
    }

    var msg = "Hello Admin, I want to *Deposit Points*.\n\n*Amount:* ₹".concat(amt.toFixed(2), "\n*User Mobile:* ").concat(uMob, "\n*User ID:* ").concat(uId);
    var waLink = "https://wa.me/".concat(waNo, "?text=").concat(encodeURIComponent(msg));

    showPopup('Opening WhatsApp...');
    setTimeout(function () {window.location.href = waLink;}, 250);
    if (btn) {btn.disabled = false;btn.textContent = btnOld || '<?= __("Proceed / Submit") ?>';}
    return false;
  }

  // ✅ Pay0 Gateway (FORCEFUL - direct instant redirect)
  if (tab === 'pay0') {
    var oid = makeOrderId('P0');
    var uId = (document.body.dataset.userId || '').trim();
    var uPk = (document.body.dataset.userPk || '0').trim();

    // ✅ Show full-screen loading overlay
    (function(){ var el = document.getElementById('pay0LoadingOverlay'); if(el) el.classList.add('show'); })()

    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Opening Gateway...';
    }

    // ✅ Build the gateway URL (using uPk integer for the SDK)
    var payUrl = "pay/pay0_create.php?order_id=".concat(encodeURIComponent(oid), "&amount=").concat(encodeURIComponent(amt.toFixed(2)), "&uid=").concat(encodeURIComponent(uPk));

    // ✅ Create Pending record in background WHILE redirecting
    try {
      var fd = new FormData();
      fd.set('method', 'UPI ZERO');
      fd.set('order_id', oid);
      fd.set('amount', amt.toFixed(2));
      navigator.sendBeacon ? navigator.sendBeacon('deposit.php', fd) :
      fetch('deposit.php', { method: 'POST', body: fd, keepalive: true });
    } catch (err) {}

    // ✅ FORCEFUL: Use replace, no delay, no setTimeout
    window.location.replace(payUrl);
    return false;
  }

  // ✅ IMB Gateway
  if (tab === 'imb') {
    var oid = makeOrderId('IMB');
    var uId = (document.body.dataset.userId || '').trim();
    var uPk = (document.body.dataset.userPk || '0').trim();

    (function(){ var el = document.getElementById('pay0LoadingOverlay'); if(el) el.classList.add('show'); })()

    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Opening Gateway...';
    }

    var payUrl = "pay/imb_create.php?order_id=".concat(encodeURIComponent(oid), "&amount=").concat(encodeURIComponent(amt.toFixed(2)), "&uid=").concat(encodeURIComponent(uPk));

    try {
      var fd = new FormData();
      fd.set('method', 'IMB');
      fd.set('order_id', oid);
      fd.set('amount', amt.toFixed(2));
      navigator.sendBeacon ? navigator.sendBeacon('deposit.php', fd) :
      fetch('deposit.php', { method: 'POST', body: fd, keepalive: true });
    } catch (err) {}

    window.location.replace(payUrl);
    return false;
  }

  // ✅ YuviPay Gateway — FAST direct navigation
  if (tab === 'yuvipay') {
    var oid = makeOrderId('YUVI');
    var uPk = (document.body.dataset.userPk || '0').trim();

    // ✅ Show loading overlay immediately for instant feedback
    (function(){ var el = document.getElementById('pay0LoadingOverlay'); if(el) el.classList.add('show'); })()
    if (btn) {btn.disabled = true;btn.textContent = '⏳ Opening Gateway...';}

    // ✅ Save Pending record in background (keepalive survives navigation)
    try {
      var fd = new FormData();
      fd.set('method', 'UPI YUVI');fd.set('order_id', oid);fd.set('amount', amt.toFixed(2));
      fetch('deposit.php', { method: 'POST', body: fd, keepalive: true });
    } catch (e) {}

    // ✅ Navigate directly to yuvipay_create.php (INSTANT — no AJAX wait)
    // PHP handles the API call server-side and redirects to payment URL
    var payUrl = 'pay/yuvipay_create.php?order_id=' + encodeURIComponent(oid) +
    '&amount=' + encodeURIComponent(amt.toFixed(2)) +
    '&uid=' + encodeURIComponent(uPk);
    window.location.replace(payUrl);
    return false;
  }

  function onSubmitWithdraw(event) {
    var isBankApproved = document.body.dataset.bankApproved === '1';
    if (!isBankApproved) {
      event.preventDefault();
      showPopup('Update Bank Details First');
      return false;
    }
    var amt = parseFloat(document.getElementById('withdrawAmount').value) || 0;
    var bal = <?= (float) $totalBalance ?>;

    if (amt > bal) {
      event.preventDefault();
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Insufficient Balance',
          text: 'You do not have enough balance to withdraw.',
          icon: 'error',
          confirmButtonColor: '#c62828'
        });
      } else {
        showPopup('Insufficient Balance');
      }
      return false;
    }

    var isAllowed = <?= (int) $isWithdrawalAllowed ?>;
    var note = <?= json_encode($withdrawNote) ?>;

    if (!isAllowed) {
      event.preventDefault();
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Withdrawal Closed',
          text: note,
          icon: 'info',
          confirmButtonColor: '#c62828'
        });
      } else {
        alert(note);
      }
      return false;
    }

    return true;
  }

  // ✅ Normal deposit (Bank/UPI/Crypto)
  var depOid = document.getElementById('depOrderId');
  if (depOid && !depOid.value) {
    depOid.value = tab === 'upi' ? makeOrderId('UP') : tab === 'crypto' ? makeOrderId('USDT') : makeOrderId('DP');
  }

  showPopup('Deposit Request Successful');
  setTimeout(function () {
    try {form.submit();}
    catch (_) {
      if (btn) {btn.disabled = false;btn.textContent = btnOld || 'Submit';}
      showPopup('Try again');
    }
  }, 250);

  return false;
}

/* ✅ Withdrawal dropdown */
function goToKyc() {window.location.href = 'user_bank.php';}

var wdPortal = null;

function ensureWdPortal() {
  if (wdPortal) return wdPortal;
  wdPortal = document.createElement('div');
  wdPortal.id = 'wdPortalDropdown';
  wdPortal.className = 'wd-portal-dropdown';
  wdPortal.innerHTML = '<div class="list" role="listbox"></div>';
  document.body.appendChild(wdPortal);
  return wdPortal;
}

function closeWdDropdown() {
  if (!wdPortal) return;
  wdPortal.classList.remove('open');
  wdPortal.style.display = 'none';
  var btn = document.getElementById('wdSelectBtn');
  if(btn) btn.setAttribute('aria-expanded', 'false');
}

(function () {
  var method = ((document.getElementById('withdrawMethodHidden') ? document.getElementById('withdrawMethodHidden').value : '') || '').trim();
  if (!method) {
    showPopup('<?= __("Select Method") ?>');
    return false;
  }
  var methodsStr = document.body.dataset.wdMethods || '[]';
  var methods = [];
  try {methods = JSON.parse(methodsStr);} catch (e) {methods = [];}

  var btn = document.getElementById('wdSelectBtn');
  var lbl = document.getElementById('wdSelectedLabel');
  var badge = document.getElementById('wdSelectedBadge');
  var hidden = document.getElementById('withdrawMethodHidden');
  var kycBox = document.getElementById('kycActions');

  if (!btn || !lbl || !badge || !hidden) return;

  function setSelected(m) {
    if (!m) {
      lbl.innerHTML = '<span style="color: #333333 !important; font-weight: 900;"><?= __("Select Method") ?></span>';
      badge.style.display = 'none';
      hidden.value = '';
      if(kycBox) kycBox.classList.remove('show');
      return;
    }
    lbl.innerHTML = '<span style="color: #333333 !important; font-weight: 900;">' + m.label + '</span> <span style="color: ' + (m.linked ? '#2e7d32' : '#b71c1c') + ' !important; font-weight: bold; margin-left: 5px;">(' + (m.linked ? '<?= __("Linked") ?>' : '<?= __("Not Linked") ?>') + ')</span>';
    badge.style.display = 'none';
    hidden.value = m.value;
    if(kycBox) kycBox.classList.toggle('show', !m.linked);
  }

  function renderPortal() {
    var portal = ensureWdPortal();
    var list = portal.querySelector('.list');
    list.innerHTML = '';
    methods.forEach(function (m) {
      var item = document.createElement('div');
      item.className = 'wd-item';
      item.setAttribute('role', 'option');

      var left = document.createElement('div');
      left.className = 'name';
      left.textContent = m.label;

      var b = document.createElement('span');
      b.className = 'badge ' + (m.linked ? 'linked' : 'notlinked');
      b.textContent = m.linked ? '<?= __("Linked") ?>' : '<?= __("Incomplete") ?>';

      item.appendChild(left);
      item.appendChild(b);

      item.addEventListener('click', function () {
        setSelected(m);
        closeWdDropdown();
      });

      list.appendChild(item);
    });
  }

  function openPortal() {
    renderPortal();
    var portal = ensureWdPortal();

    var r = btn.getBoundingClientRect();
    var width = Math.round(r.width);
    portal.style.width = width + 'px';

    portal.style.display = 'block';
    portal.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');

    requestAnimationFrame(function () {
      var pr = portal.getBoundingClientRect();
      var vh = window.innerHeight || document.documentElement.clientHeight;

      var top = Math.round(r.bottom + 8);
      var left = Math.round(r.left);

      if (top + pr.height > vh - 10) {
        top = Math.max(10, Math.round(r.top - pr.height - 8));
      }

      if (left + pr.width > window.innerWidth - 10) left = Math.max(10, window.innerWidth - pr.width - 10);
      if (left < 10) left = 10;

      portal.style.top = top + 'px';
      portal.style.left = left + 'px';
    });
  }

  function togglePortal() {
    var portal = ensureWdPortal();
    if (portal.classList.contains('open')) closeWdDropdown();else
    openPortal();
  }

  btn.addEventListener('click', togglePortal);

  document.addEventListener('click', function (e) {
    if (!wdPortal) return;
    if (!wdPortal.classList.contains('open')) return;
    if (wdPortal.contains(e.target) || btn.contains(e.target)) return;
    closeWdDropdown();
  });

  window.addEventListener('resize', function () {if ((wdPortal && wdPortal.classList.contains('open'))) closeWdDropdown();});
  window.addEventListener('scroll', function () {if ((wdPortal && wdPortal.classList.contains('open'))) closeWdDropdown();}, true);

  setSelected(null);
})();

/* ✅ Withdrawal submit */
function onSubmitWithdraw(e) {
  e.preventDefault();



  var method = ((document.getElementById('withdrawMethodHidden') ? document.getElementById('withdrawMethodHidden').value : '') || '').trim();
  if (!method) {
    showPopup('<?= __("Select Method") ?>');
    return false;
  }
  var methodsStr = document.body.dataset.wdMethods || '[]';
  var methods = [];
  try { methods = JSON.parse(methodsStr); } catch(e) {}
  var selectedObj = methods.find(function(m) { return m.value === method; });
  if (selectedObj && !selectedObj.linked) {
    showPopup(method + ' is Incomplete. Please link it first.');
    return false;
  }

  var min = parseInt(document.body.dataset.minWd || '1000', 10) || 1000;
  var amt = parseFloat((document.getElementById('withdrawAmount') ? document.getElementById('withdrawAmount').value : '') || '0') || 0;

  if (amt < min) {
    showPopup('Minimum withdrawal ₹' + min);
    return false;
  }



  var currentBal = parseFloat(document.body.dataset.wallet || '0') || 0;
  if (amt > currentBal) {
    showPopup('Insufficient Balance');
    return false;
  }

  var timeEnabled = parseInt(document.body.dataset.withdrawTimeEnabled || '0', 10) === 1;
  var wStart = document.body.dataset.withdrawStart || '';
  var wEnd = document.body.dataset.withdrawEnd || '';

  // ✅ Close modal instantly before displaying any alerts
  if (typeof closeModal === 'function') closeModal();

  var wdOid = document.getElementById('wdOrderId');
  if (wdOid && !wdOid.value) wdOid.value = makeOrderId('WD');

  var btn = document.getElementById('withdrawSubmitBtn');
  var btnOld = btn ? btn.textContent : '';
  var form = document.getElementById('withdrawForm');

  // ✅ WhatsApp Withdrawal
  if (method === 'WhatsApp') {
    var waNo = (document.body.dataset.whatsappNo || '').trim();
    var uMob = (document.body.dataset.userMobile || '').trim();
    var uId = (document.body.dataset.userId || '').trim();

    if (!waNo) {
      showPopup('WhatsApp Support not configured');
      return false;
    }

    var msg = "Hello Admin, I want to *Withdraw Points*.\n\n*Amount:* ₹".concat(amt.toFixed(2), "\n*User Mobile:* ").concat(uMob, "\n*User ID:* ").concat(uId);
    var waLink = "https://wa.me/".concat(waNo, "?text=").concat(encodeURIComponent(msg));

    showPopup('Opening WhatsApp...');
    setTimeout(function () {window.location.href = waLink;}, 250);
    return false;
  }

  if (timeEnabled) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Withdrawal Time',
        html: "<div style=\"font-size: 16px; font-weight: 600; color: #ffc107; margin-bottom: 10px;\">Withdrawal Timing:</div>\n                   <div style=\"font-size: 15px; font-weight: 500; color: #fff;\">".concat(
          wStart, " to ").concat(wEnd, "</div>"),
        icon: 'info',
        confirmButtonColor: '#c62828',
        background: '#1c1427',
        color: '#fff'
      }).then(function () {
        if (btn) {btn.disabled = true;btn.textContent = 'Submitting...';}
        form.submit();
      });
    } else {
      alert("Withdrawal allowed from ".concat(wStart, " to ").concat(wEnd));
      if (btn) {btn.disabled = true;btn.textContent = 'Submitting...';}
      form.submit();
    }
  } else {
    if (btn) {btn.disabled = true;btn.textContent = 'Submitting...';}
    showPopup('Withdrawal Request Successful');
    setTimeout(function () {form.submit();}, 200);
  }

  return false;
}

/* Notice indicator + sound */
(function () {
  window.KISMAT555_NOTICE_TRACKER_STARTED = true;
  var KEY_SEEN = 'last_notice_seen';
  var KEY_SOUND = 'last_notice_sound_id';

  var lastSeen = +localStorage.getItem(KEY_SEEN) || 0;
  var lastSound = +localStorage.getItem(KEY_SOUND) || 0;

  var headerDot = document.getElementById('notifyDot');
  var headerLink = document.getElementById('notifyLink');
  var sideDot = document.getElementById('sideNotifyDot');
  var sideItem = document.getElementById('sideNotifyItem');
  var sound = document.getElementById('notifySound');

  function setDots(show) {
    if (headerDot) headerDot.style.display = show ? 'block' : 'none';
    if (sideDot) sideDot.style.display = show ? 'block' : 'none';
  }
  function play() {
    if (!sound) return;
    try {sound.pause();sound.currentTime = 0;sound.muted = false;sound.play().catch(function () {});} catch (e) {}
  }
  function pollNotice() {
    fetch('notice_status.php', { cache: 'no-store' }).
    then(function(r) { return r.json(); }).
    then(function (d) {
      if (!d || typeof d.id === 'undefined') return;
      var latestId = +d.id || 0;
      var hasNew = latestId > lastSeen;
      setDots(hasNew);

      if (hasNew && latestId > lastSound) {
        play();
        lastSound = latestId;
        localStorage.setItem(KEY_SOUND, String(lastSound));
      }
    }).catch(function () {});
  }
  function markSeen() {
    setDots(false);
    lastSeen = Math.max(lastSeen, lastSound);
    localStorage.setItem(KEY_SEEN, String(lastSeen));
  }

  if(headerLink) headerLink.addEventListener('click', markSeen);
  if(sideItem) sideItem.addEventListener('click', markSeen);

  setTimeout(pollNotice, 2000);
  setInterval(pollNotice, 45000);
})();

/* live chat indicator + sound */
(function () {
  var KEY_LAST_SEEN = 'last_chat_id';
  var KEY_LAST_SOUND = 'last_chat_sound_id';

  var lastSeen = +localStorage.getItem(KEY_LAST_SEEN) || 0;
  var lastSound = +localStorage.getItem(KEY_LAST_SOUND) || 0;

  var sideDot = document.getElementById('sideChatDot');
  var chatLink = document.getElementById('chatIconLink');
  var sound = document.getElementById('chatSound');

  function setDot(show) {
    if (sideDot) sideDot.style.display = show ? 'block' : 'none';
  }
  function play() {
    if (!sound) return;
    try {sound.pause();sound.currentTime = 0;sound.muted = false;sound.play().catch(function () {});} catch (e) {}
  }

  function check() {
    fetch('live_chat.php?action=fetch&_=' + Date.now(), { cache: 'no-store' }).
    then(function(r) { return r.json(); }).
    then(function (list) {
      if (!Array.isArray(list) || !list.length) return;
      var latest = list[list.length - 1];
      var latestId = +latest.id || 0;
      var isAdmin = String(latest.sender || '') === 'admin';

      var hasNew = isAdmin && latestId > lastSeen;
      setDot(hasNew);

      if (hasNew && latestId > lastSound) {
        play();
        lastSound = latestId;
        localStorage.setItem(KEY_SOUND, String(lastSound));
      }
    }).catch(function () {});
  }

  function markSeen() {
    setDot(false);
    fetch('live_chat.php?action=fetch&_=' + Date.now(), { cache: 'no-store' }).
    then(function(r) { return r.json(); }).
    then(function (list) {
      if (Array.isArray(list) && list.length) {
        var latestId = +list[list.length - 1].id || 0;
        lastSeen = latestId;
        localStorage.setItem(KEY_LAST_SEEN, String(lastSeen));
      }
    }).catch(function () {});
  }

  if(chatLink) chatLink.addEventListener('click', markSeen);

  setTimeout(check, 2000);
  setInterval(check, 15000);
})();

/* ✅ AUTO RELOAD after status change */
(function () {
  if (document.body.dataset.deactive === '1') return;

  var KEY_DEP = 'wallet_last_dep_sig';
  var KEY_WD = 'wallet_last_wd_sig';

  function sig(obj) {
    var id = +(obj ? obj.id : undefined) || 0;
    var st = ((obj ? obj.status : undefined) || '').toLowerCase().trim();
    return id + ':' + st;
  }

  if (!localStorage.getItem(KEY_DEP)) localStorage.setItem(KEY_DEP, '0:');
  if (!localStorage.getItem(KEY_WD)) localStorage.setItem(KEY_WD, '0:');

  var busy = false;
  function poll() {
    if (busy) return;
    busy = true;

    var nextInterval = 12000; // default 12s
    try {
      // ajax=status returns the latest deposit and withdrawal status for this user
      fetch('ajax_wallet.php', { cache: 'no-store' }).then(function(r) {
        return r.json();
      }).then(function(d) {
        if (!d || !d.ok) { busy = false; setTimeout(poll, nextInterval); return; }

        var depStatus = (d.deposit && d.deposit.status ? d.deposit.status : '').toLowerCase().trim();
        var depSig = sig(d.deposit);
        var wdSig = sig(d.withdrawal);

        // If Pending, poll much faster (3s) to detect 3-minute auto-failed or webhook success
        if (depStatus === 'pending') {
          nextInterval = 3000;
        }

        var oldDep = localStorage.getItem(KEY_DEP) || '0:';
        var oldWd = localStorage.getItem(KEY_WD) || '0:';

        // Live balance update (non-blocking)
        var total = parseFloat(d.balances && d.balances.total ? d.balances.total : '0') || 0;
        // Format matching 1,165 or 1,165.50
        var fmtTotal = total.toLocaleString('en-IN', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
        document.body.dataset.wallet = total.toFixed(2);
        var wbEl = document.getElementById('walletVal');
        if (wbEl) wbEl.textContent = '₹' + fmtTotal;

        // If signature changed (Status changed from Pending -> Done/Failed)
        if (depSig !== oldDep || wdSig !== oldWd) {
          localStorage.setItem(KEY_DEP, depSig);
          localStorage.setItem(KEY_WD, wdSig);
          window.location.reload();
          return;
        }
        busy = false;
        setTimeout(poll, nextInterval);
      }).catch(function(e) {
        busy = false;
        setTimeout(poll, nextInterval);
      });
    } catch (e) {
      busy = false;
      setTimeout(poll, nextInterval);
    }
  }

  setTimeout(poll, 3000);
})();
var shareBtn = document.getElementById('shareBtn');
if (shareBtn) shareBtn.addEventListener('click', function () {
  closeM();
  var url = "https://subhgaming.online/download.php";
  <?php
        $shareTextFull = "Market Price \n♠️*Game Rate*♠️ \n\n";
        if (!empty($gameRates)) {
            foreach ($gameRates as $r) {
                $shareTextFull .= "▪️" . $r['game_type'] . " ₹" . $r['play_amount'] . " = ₹" . $r['win_amount'] . " ";
            }
        } else {
            $shareTextFull .= "▪️Single Digit ₹10 = ₹95 ▪️Jodi Digit ₹10 = ₹950 ▪️Single Pana ₹10 = ₹1500 ▪️Double Pana ₹10 = ₹3000 ▪️Triple Pana ₹10 = ₹7000 ▪️Half Sangam ₹10 = ₹10000 ▪️Full Sangam ₹10 = ₹100000 ";
        }
        $shareTextFull .= "\n\nDownload App:\n" . "https://subhgaming.online/download.php";
        ?>;
  var fullText = <?= json_encode($shareTextFull, JSON_UNESCAPED_UNICODE) ?>;
  if (navigator.share) {
    navigator.share({ text: fullText });
  } else {
    window.location.href = 'whatsapp://send?text=' + encodeURIComponent(fullText);
  }
});</script>



  <script>
function setAllAmounts(val) {
  var inputs = ['amountIntent', 'amountBank', 'amountUpi', 'amountCryptoInr', 'amountWhatsapp', 'amountPay0', 'amountImb', 'amountYuvipay'];
  inputs.forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.value = val;
  });
  if (typeof updateFromInr === 'function') updateFromInr();
  var hidden = document.getElementById('amountHidden');
  if (hidden) hidden.value = val;

  updateBonusUI(val);
}

function updateBonusUI(val) {
  var bonus = 0;
  var amt = parseFloat(val) || 0;
  var rates = [];
  try {rates = JSON.parse(document.body.dataset.bonusRates || '[]');} catch (_) {rates = [];}
  rates.sort(function(a, b) { return (parseFloat(a.min) || 0) - (parseFloat(b.min) || 0); }).forEach(function (rate) {
    var min = parseFloat(rate.min) || 0;
    var rateBonus = parseFloat(rate.bonus) || 0;
    if (amt >= min) bonus = rateBonus;
  });

  var displays = ['bonusIntent', 'bonusUpi'];
  displays.forEach(function (id) {
    var el = document.getElementById(id);
    if (el) {
      if (bonus > 0) {
        el.style.display = 'block';
        el.innerHTML = '<i class="fas fa-gift"></i> Extra Bonus: +₹' + bonus + ' (Total: ₹' + (amt + bonus) + ')';
      } else {
        el.style.display = 'none';
        el.innerHTML = '';
      }
    }
  });
}

function initBonusListeners() {
  var inputs = ['amountIntent', 'amountUpi'];
  inputs.forEach(function (id) {
    var el = document.getElementById(id);
    if (el) {
      el.addEventListener('input', function (e) {
        updateBonusUI(e.target.value);
      });
      updateBonusUI(el.value);
    }
  });
}
if (document.readyState !== 'loading') {
  initBonusListeners();
} else {
  document.addEventListener('DOMContentLoaded', initBonusListeners);
}</script>

  <?php if (!$isDeactive): ?>
  <script src="notify_bundle.php?v=2"></script>
  <?php endif; ?>
  <!-- Scroll To Top Button -->
  <button id="scrollToTopBtn" class="back-to-top">
    <i class="fa-solid fa-arrow-up"></i>
  </button>
  <style>
    .back-to-top {
      position: fixed;
      bottom: 25px;
      right: 20px;
      width: 45px;
      height: 45px;
      background: #000000;
      color: #ffffff;
      border: none;
      border-radius: 50%;
      display: none;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      cursor: pointer;
      z-index: 999;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    }
    .back-to-top i {
      animation: bounceUp 1.2s infinite ease-in-out;
    }
    @keyframes bounceUp {
      0%, 100% {
        transform: translateY(0);
      }
      50% {
        transform: translateY(-5px);
      }
    }
    .btn-submit-withdraw-funds {
      margin-top: 15px;
      width: 100%;
      height: 50px;
      background: #ff3b30 !important;
      color: #ffffff !important;
      border: none;
      border-radius: 12px;
      font-size: 17px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(255, 59, 48, 0.35);
      margin-bottom: 20px;
      transition: transform 0.15s, background-color 0.2s;
      font-family: 'Poppins', sans-serif;
    }
    .btn-submit-withdraw-funds:active {
      transform: scale(0.98);
      background: #e60000 !important;
    }
  </style>
  <script>
    (function() {
      var upBtn = document.getElementById('scrollToTopBtn');
      window.addEventListener('scroll', function() {
        if (window.scrollY > 300) upBtn.style.display = 'flex';
        else upBtn.style.display = 'none';
      }, { passive: true });
      upBtn.onclick = function() { window.scrollTo({ top: 0, behavior: 'smooth' }); };
    })();
  </script>

  <script src="assets/js/user_notice_tracker.js?v=4"></script>
</body>

</html>










