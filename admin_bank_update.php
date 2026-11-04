<?php
require_once 'includes/db.php';
// admin_bank_update.php  ✅ (SAFE + COMPLETE UPDATED FILE)
// ✅ FIX: bind_param types COUNT mismatch (Fatal error line 370)
// ✅ KEEP: same UI/theme + existing flow (no damage)
// ✅ ADD: 3 optional fields for Intent URLs (webhook/success/failed)

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

try {
    $db = db();
} catch (Exception $e) {
    die('DB error: ' . $e->getMessage());
}

const DEFAULT_INTENT_WEBHOOK_URL = 'https://dp-boss.fun/webhook.php';
const DEFAULT_INTENT_SUCCESS_REDIRECT = 'https://dp-boss.fun/user_wallet.php?status=SUCCESS';
const DEFAULT_INTENT_FAILED_REDIRECT = 'https://dp-boss.fun/user_wallet.php?status=FAILED';

function normalize_intent_url(string $value, string $default): string
{
    $value = trim($value);
    if ($value === '') {
        return $default;
    }

    return preg_replace(
        '~(?:https?://)?(?:www\.)?kalyanratan\.online~i',
        'https://dp-boss.fun',
        $value
    ) ?? $default;
}

/* ====== Role Check ====== */
$adminIdentifier = $_SESSION['admin_id'] ?? $_SESSION['admin'];
$sessionRole = strtolower(trim($_SESSION['admin_role'] ?? 'admin'));
$isMaster = false;
$isManager = ($sessionRole === 'manager');
try {
    $stmtInf = $db->prepare("SELECT role FROM admin WHERE id = ? OR email = ? OR admin_id = ? OR name = ? LIMIT 1");
    $stmtInf->bind_param("isss", $adminIdentifier, $adminIdentifier, $adminIdentifier, $adminIdentifier);
    $stmtInf->execute();
    $resInf = $stmtInf->get_result();
    if ($rowR = $resInf->fetch_assoc()) {
        $r = strtolower(trim($rowR['role']));
        if ($r === 'master') {
            $isMaster = true;
        }
        if ($r === 'manager') {
            $isManager = true;
        }
    }
    $stmtInf->close();
} catch (Throwable $e) {}

if ($isManager) {
    header('Location: admin_dashboard.php');
    exit;
}

/* ====== Ensure table exists (with new columns) ====== */
$createSql = "
CREATE TABLE IF NOT EXISTS admin_payment_details (
  id INT NOT NULL PRIMARY KEY,
  bank_name VARCHAR(150) NOT NULL DEFAULT '',
  bank_account_name VARCHAR(150) NOT NULL DEFAULT '',
  bank_account_number VARCHAR(50) NOT NULL DEFAULT '',
  bank_ifsc VARCHAR(50) NOT NULL DEFAULT '',
  bank_branch VARCHAR(150) NOT NULL DEFAULT '',
  bank_note VARCHAR(255) NOT NULL DEFAULT '',
  upi_id VARCHAR(150) NOT NULL DEFAULT '',
  upi_name VARCHAR(150) NOT NULL DEFAULT '',
  upi_note VARCHAR(255) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  upi_qr VARCHAR(255) NOT NULL DEFAULT '',
  show_bank TINYINT(1) NOT NULL DEFAULT 1,
  show_upi_id TINYINT(1) NOT NULL DEFAULT 1,
  show_mobile_upi TINYINT(1) NOT NULL DEFAULT 0,
  show_crypto TINYINT(1) NOT NULL DEFAULT 0,
  crypto_name VARCHAR(50) NOT NULL DEFAULT 'USDT',
  crypto_network VARCHAR(50) NOT NULL DEFAULT 'TRC20',
  crypto_address VARCHAR(255) NOT NULL DEFAULT '',
  inr_rate_per_unit DECIMAL(10,4) NOT NULL DEFAULT 90.0000,
  crypto_qr VARCHAR(255) NOT NULL DEFAULT '',
  upi_intent VARCHAR(150) NOT NULL DEFAULT '',
  merchant_upi_name VARCHAR(150) NOT NULL DEFAULT '',
  enable_upi_intent TINYINT(1) NOT NULL DEFAULT 0,
  auto_accept_intent TINYINT(1) NOT NULL DEFAULT 1,

  /* ✅ NEW */
  intent_webhook_url VARCHAR(255) NOT NULL DEFAULT '',
  intent_success_redirect VARCHAR(255) NOT NULL DEFAULT '',
  intent_failed_redirect VARCHAR(255) NOT NULL DEFAULT '',

  /* ✅ TELEGRAM & UNIVERSAL PG */
  telegram_id VARCHAR(255) NOT NULL DEFAULT '',
  telegram_channel VARCHAR(255) NOT NULL DEFAULT '',
  show_telegram TINYINT(1) NOT NULL DEFAULT 1,
  show_telegram_channel TINYINT(1) NOT NULL DEFAULT 1,
  pg_provider VARCHAR(50) NOT NULL DEFAULT 'manual_upi',
  pg_merchant_id VARCHAR(255) NOT NULL DEFAULT '',
  pg_api_key VARCHAR(255) NOT NULL DEFAULT '',
  pg_secret_key VARCHAR(255) NOT NULL DEFAULT '',
  pg_webhook_url VARCHAR(255) NOT NULL DEFAULT '',
  pg_auto_accept TINYINT(1) NOT NULL DEFAULT 1,

  /* ✅ DEDICATED CATEGORY 2 PG KEYS */
  phonepe_merchant_id VARCHAR(255) NOT NULL DEFAULT '',
  phonepe_salt_key VARCHAR(255) NOT NULL DEFAULT '',
  phonepe_salt_index VARCHAR(50) NOT NULL DEFAULT '1',
  phonepe_webhook_url VARCHAR(255) NOT NULL DEFAULT '',
  razorpay_key_id VARCHAR(255) NOT NULL DEFAULT '',
  razorpay_key_secret VARCHAR(255) NOT NULL DEFAULT '',
  razorpay_webhook_secret VARCHAR(255) NOT NULL DEFAULT '',
  cashfree_app_id VARCHAR(255) NOT NULL DEFAULT '',
  cashfree_secret_key VARCHAR(255) NOT NULL DEFAULT '',
  cashfree_webhook_signature VARCHAR(255) NOT NULL DEFAULT '',
  pay0_merchant_id VARCHAR(255) NOT NULL DEFAULT '',

  /* ✅ CATEGORY 4 TELEGRAM BOT & SUPPORT */
  telegram_bot_token VARCHAR(255) NOT NULL DEFAULT '',
  telegram_channel_chat_id VARCHAR(255) NOT NULL DEFAULT '',
  telegram_bot_auto_post TINYINT(1) NOT NULL DEFAULT 1,

  /* ✅ WHATSAPP */
  whatsapp_no_1 VARCHAR(20) NOT NULL DEFAULT '',
  whatsapp_no_2 VARCHAR(20) NOT NULL DEFAULT '',
  show_whatsapp_deposit TINYINT(1) NOT NULL DEFAULT 0,
  whatsapp_deposit_no VARCHAR(20) NOT NULL DEFAULT '',

  /* ✅ IMB */
  show_imb TINYINT(1) NOT NULL DEFAULT 0,
  imb_token VARCHAR(255) NOT NULL DEFAULT '',

  /* ✅ LIMITS */
  min_deposit INT NOT NULL DEFAULT 300,
  min_withdraw INT NOT NULL DEFAULT 1000,
  min_bid INT NOT NULL DEFAULT 10,

  /* ✅ UPI APPS ENABLE/DISABLE */
  enable_phonepe TINYINT(1) NOT NULL DEFAULT 1,
  enable_gpay TINYINT(1) NOT NULL DEFAULT 1,
  enable_paytm TINYINT(1) NOT NULL DEFAULT 1,
  enable_bhim TINYINT(1) NOT NULL DEFAULT 1,
  enable_amazonpay TINYINT(1) NOT NULL DEFAULT 1,
  enable_mobikwik TINYINT(1) NOT NULL DEFAULT 1,
  enable_jiofinance TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$db->query($createSql);

try {
    $db->query("
        CREATE TABLE IF NOT EXISTS withdrawal_time (
            id INT AUTO_INCREMENT PRIMARY KEY,
            enable_withdraw_time TINYINT(1) NOT NULL DEFAULT 1,
            withdraw_start_time VARCHAR(50) NOT NULL DEFAULT '07:00 AM',
            withdraw_end_time VARCHAR(50) NOT NULL DEFAULT '11:00 AM',
            withdraw_note VARCHAR(255) NOT NULL DEFAULT 'Withdrawal allowed from 7:00 AM to 11:00 AM only.'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Try to add column if missing
    try {
        $db->query("ALTER TABLE withdrawal_time ADD COLUMN enable_withdraw_time TINYINT(1) NOT NULL DEFAULT 1");
    } catch (Exception $eCol) {}

    $check = $db->query("SELECT id FROM withdrawal_time WHERE id=1");
    if ($check && $check->num_rows === 0) {
        $db->query("INSERT INTO withdrawal_time (id, enable_withdraw_time, withdraw_start_time, withdraw_end_time, withdraw_note) VALUES (1, 1, '07:00 AM', '11:00 AM', 'Withdrawal allowed from 7:00 AM to 11:00 AM only.')");
    }
} catch (Exception $e) {}

/* --- Try to add missing columns on existing table (ignore errors) --- */
$alterSqls = [
    "ALTER TABLE admin_payment_details ADD COLUMN show_bank TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE admin_payment_details ADD COLUMN show_upi_id TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE admin_payment_details ADD COLUMN show_mobile_upi TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE admin_payment_details ADD COLUMN show_crypto TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE admin_payment_details ADD COLUMN crypto_name VARCHAR(50) NOT NULL DEFAULT 'USDT'",
    "ALTER TABLE admin_payment_details ADD COLUMN crypto_network VARCHAR(50) NOT NULL DEFAULT 'TRC20'",
    "ALTER TABLE admin_payment_details ADD COLUMN crypto_address VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN crypto_qr VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN inr_rate_per_unit DECIMAL(10,4) NOT NULL DEFAULT 90.0000",
    "ALTER TABLE admin_payment_details ADD COLUMN upi_intent VARCHAR(150) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN merchant_upi_name VARCHAR(150) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN enable_upi_intent TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE admin_payment_details ADD COLUMN auto_accept_intent TINYINT(1) NOT NULL DEFAULT 1",

    /* ✅ NEW */
    "ALTER TABLE admin_payment_details ADD COLUMN intent_webhook_url VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN intent_success_redirect VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN intent_failed_redirect VARCHAR(255) NOT NULL DEFAULT ''",

    /* ✅ TELEGRAM & UNIVERSAL PG */
    "ALTER TABLE admin_payment_details ADD COLUMN telegram_id VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN telegram_channel VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN show_telegram TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE admin_payment_details ADD COLUMN show_telegram_channel TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE admin_payment_details ADD COLUMN pg_provider VARCHAR(50) NOT NULL DEFAULT 'manual_upi'",
    "ALTER TABLE admin_payment_details ADD COLUMN pg_merchant_id VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN pg_api_key VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN pg_secret_key VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN pg_webhook_url VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN pg_auto_accept TINYINT(1) NOT NULL DEFAULT 1",

    /* ✅ DEDICATED CATEGORY 2 PG KEYS */
    "ALTER TABLE admin_payment_details ADD COLUMN phonepe_merchant_id VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN phonepe_salt_key VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN phonepe_salt_index VARCHAR(50) NOT NULL DEFAULT '1'",
    "ALTER TABLE admin_payment_details ADD COLUMN phonepe_webhook_url VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN razorpay_key_id VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN razorpay_key_secret VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN razorpay_webhook_secret VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN cashfree_app_id VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN cashfree_secret_key VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN cashfree_webhook_signature VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN pay0_merchant_id VARCHAR(255) NOT NULL DEFAULT ''",

    /* ✅ CATEGORY 4 TELEGRAM BOT & SUPPORT */
    "ALTER TABLE admin_payment_details ADD COLUMN telegram_bot_token VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN telegram_channel_chat_id VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN telegram_bot_auto_post TINYINT(1) NOT NULL DEFAULT 1",

    /* ✅ WHATSAPP */
    "ALTER TABLE admin_payment_details ADD COLUMN whatsapp_no_1 VARCHAR(20) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN whatsapp_no_2 VARCHAR(20) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN show_whatsapp_deposit TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE admin_payment_details ADD COLUMN whatsapp_deposit_no VARCHAR(20) NOT NULL DEFAULT ''",

    /* ✅ PAY0 */
    "ALTER TABLE admin_payment_details ADD COLUMN show_pay0 TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE admin_payment_details ADD COLUMN pay0_token VARCHAR(255) NOT NULL DEFAULT ''",

    /* ✅ IMB */
    "ALTER TABLE admin_payment_details ADD COLUMN show_imb TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE admin_payment_details ADD COLUMN imb_token VARCHAR(255) NOT NULL DEFAULT ''",

    /* ✅ YUVIPAY */
    "ALTER TABLE admin_payment_details ADD COLUMN show_yuvipay TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE admin_payment_details ADD COLUMN yuvipay_token VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE admin_payment_details ADD COLUMN yuvipay_merch_id VARCHAR(255) NOT NULL DEFAULT ''",

    /* ✅ LIMITS */
    "ALTER TABLE admin_payment_details ADD COLUMN min_deposit INT NOT NULL DEFAULT 300",
    "ALTER TABLE admin_payment_details ADD COLUMN min_withdraw INT NOT NULL DEFAULT 1000",
    "ALTER TABLE admin_payment_details ADD COLUMN min_bid INT NOT NULL DEFAULT 10",

    /* ✅ UPI APPS ENABLE/DISABLE */
    "ALTER TABLE admin_payment_details ADD COLUMN enable_phonepe TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE admin_payment_details ADD COLUMN enable_gpay TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE admin_payment_details ADD COLUMN enable_paytm TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE admin_payment_details ADD COLUMN enable_bhim TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE admin_payment_details ADD COLUMN enable_amazonpay TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE admin_payment_details ADD COLUMN enable_mobikwik TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE admin_payment_details ADD COLUMN enable_jiofinance TINYINT(1) NOT NULL DEFAULT 1",

    /* ✅ FIX DEPOSIT ENUM */
    "ALTER TABLE deposit MODIFY COLUMN method VARCHAR(150) NOT NULL DEFAULT 'ADMIN'"
];

foreach ($alterSqls as $sql) {
    try {
        $db->query($sql);
    } catch (Exception $e) { /* ignore */
    }
}

/* ====== Fetch current details for form ====== */
$details = [
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
    'show_bank' => 1,
    'show_upi_id' => 1,
    'show_mobile_upi' => 0,
    'show_crypto' => 0,
    'crypto_name' => 'USDT',
    'crypto_network' => 'TRC20',
    'crypto_address' => '',
    'crypto_qr' => '',
    'inr_rate_per_unit' => 90.0000,
    'upi_intent' => '',
    'merchant_upi_name' => '',
    'enable_upi_intent' => 0,
    'auto_accept_intent' => 1,

    /* ✅ NEW */
    'intent_webhook_url' => DEFAULT_INTENT_WEBHOOK_URL,
    'intent_success_redirect' => DEFAULT_INTENT_SUCCESS_REDIRECT,
    'intent_failed_redirect' => DEFAULT_INTENT_FAILED_REDIRECT,

    /* ✅ TELEGRAM & UNIVERSAL PG */
    'telegram_id' => '',
    'telegram_channel' => '',
    'show_telegram' => 1,
    'show_telegram_channel' => 1,
    'pg_provider' => 'manual_upi',
    'pg_merchant_id' => '',
    'pg_api_key' => '',
    'pg_secret_key' => '',
    'pg_webhook_url' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'subhgaming.online') . '/webhook.php',
    'pg_auto_accept' => 1,
    'phonepe_merchant_id' => '',
    'phonepe_salt_key' => '',
    'phonepe_salt_index' => '1',
    'phonepe_webhook_url' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'subhgaming.online') . '/webhook_phonepe.php',
    'razorpay_key_id' => '',
    'razorpay_key_secret' => '',
    'razorpay_webhook_secret' => '',
    'cashfree_app_id' => '',
    'cashfree_secret_key' => '',
    'cashfree_webhook_signature' => '',
    'pay0_merchant_id' => '',
    'telegram_bot_token' => '',
    'telegram_channel_chat_id' => '',
    'telegram_bot_auto_post' => 1,

    /* ✅ WHATSAPP */
    'whatsapp_no_1' => '',
    'whatsapp_no_2' => '',
    'show_whatsapp_deposit' => 0,
    'whatsapp_deposit_no' => '',

    /* ✅ PAY0 */
    'show_pay0' => 0,
    'pay0_token' => '',

    /* ✅ IMB */
    'show_imb' => 0,
    'imb_token' => '',

    /* ✅ YUVIPAY */
    'show_yuvipay' => 0,
    'yuvipay_token' => '',
    'yuvipay_merch_id' => '',


    /* ✅ LIMITS */
    'min_deposit' => 300,
    'min_withdraw' => 1000,
    'min_bid' => 10,

    /* ✅ UPI APPS ENABLE/DISABLE */
    'enable_phonepe' => 1,
    'enable_gpay' => 1,
    'enable_paytm' => 1,
    'enable_bhim' => 1,
    'enable_amazonpay' => 1,
    'enable_mobikwik' => 1,
    'enable_jiofinance' => 1,
    'enable_withdraw_time' => 1,
    'withdraw_start_time' => '07:00 AM',
    'withdraw_end_time' => '11:00 AM',
    'withdraw_note' => 'Withdrawal allowed from 7:00 AM to 11:00 AM only.',
    'updated_at' => ''
];

try {
    $stmt = $db->prepare("SELECT * FROM admin_payment_details WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        foreach ($row as $k => $v) {
            if (array_key_exists($k, $details))
                $details[$k] = $v;
        }
    }
    $stmt->close();

    try {
        $resWd = $db->query("SELECT enable_withdraw_time, withdraw_start_time, withdraw_end_time, withdraw_note FROM withdrawal_time WHERE id=1 LIMIT 1");
        if ($resWd && $rowWd = $resWd->fetch_assoc()) {
            $details['enable_withdraw_time'] = (int)($rowWd['enable_withdraw_time'] ?? 1);
            $details['withdraw_start_time'] = $rowWd['withdraw_start_time'];
            $details['withdraw_end_time'] = $rowWd['withdraw_end_time'];
            $details['withdraw_note'] = $rowWd['withdraw_note'];
        }
    } catch (Exception $eWd) {}

    $oldIntentUrls = [
        'intent_webhook_url' => (string)$details['intent_webhook_url'],
        'intent_success_redirect' => (string)$details['intent_success_redirect'],
        'intent_failed_redirect' => (string)$details['intent_failed_redirect'],
    ];

    $details['intent_webhook_url'] = normalize_intent_url((string)$details['intent_webhook_url'], DEFAULT_INTENT_WEBHOOK_URL);
    $details['intent_success_redirect'] = normalize_intent_url((string)$details['intent_success_redirect'], DEFAULT_INTENT_SUCCESS_REDIRECT);
    $details['intent_failed_redirect'] = normalize_intent_url((string)$details['intent_failed_redirect'], DEFAULT_INTENT_FAILED_REDIRECT);

    if ($oldIntentUrls['intent_webhook_url'] !== $details['intent_webhook_url']
        || $oldIntentUrls['intent_success_redirect'] !== $details['intent_success_redirect']
        || $oldIntentUrls['intent_failed_redirect'] !== $details['intent_failed_redirect']) {
        $fixStmt = $db->prepare("
            UPDATE admin_payment_details
               SET intent_webhook_url = ?,
                   intent_success_redirect = ?,
                   intent_failed_redirect = ?
             WHERE id = 1
        ");
        if ($fixStmt) {
            $fixStmt->bind_param(
                'sss',
                $details['intent_webhook_url'],
                $details['intent_success_redirect'],
                $details['intent_failed_redirect']
            );
            $fixStmt->execute();
            $fixStmt->close();
        }
    }
} catch (Exception $e) {
    // ignore
}

$successMsg = $errorMsg = '';

/* ====== Handle POST ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_account_name = trim($_POST['bank_account_name'] ?? '');
    $bank_account_number = trim($_POST['bank_account_number'] ?? '');
    $bank_ifsc = trim($_POST['bank_ifsc'] ?? '');
    $bank_branch = trim($_POST['bank_branch'] ?? '');
    $bank_note = trim($_POST['bank_note'] ?? '');
    $upi_id = trim($_POST['upi_id'] ?? '');
    $upi_name = trim($_POST['upi_name'] ?? '');
    $upi_note = trim($_POST['upi_note'] ?? '');
    $upi_qr = $details['upi_qr'] ?? '';

    $show_bank = isset($_POST['show_bank']) ? 1 : 0;
    $show_upi_id = isset($_POST['show_upi_id']) ? 1 : 0;
    $show_mobile_upi = isset($_POST['show_mobile_upi']) ? 1 : 0;
    $show_crypto = isset($_POST['show_crypto']) ? 1 : 0;

    $crypto_name = trim($_POST['crypto_name'] ?? 'USDT');
    $crypto_network = trim($_POST['crypto_network'] ?? 'TRC20');
    $crypto_address = trim($_POST['crypto_address'] ?? '');
    $crypto_qr = $details['crypto_qr'] ?? '';
    $rateInput = trim($_POST['inr_rate_per_unit'] ?? '90');
    $inr_rate_per_unit = is_numeric($rateInput) ? (float) $rateInput : 90.0000;

    $upi_intent = trim($_POST['upi_intent'] ?? '');
    $merchant_upi_name = trim($_POST['merchant_upi_name'] ?? '');
    $enable_upi_intent = isset($_POST['enable_upi_intent']) ? 1 : 0;
    $auto_accept_intent = isset($_POST['auto_accept_intent']) ? 1 : 0;

    $intent_webhook_url = normalize_intent_url((string)($_POST['intent_webhook_url'] ?? ''), DEFAULT_INTENT_WEBHOOK_URL);
    $intent_success_redirect = normalize_intent_url((string)($_POST['intent_success_redirect'] ?? ''), DEFAULT_INTENT_SUCCESS_REDIRECT);
    $intent_failed_redirect = normalize_intent_url((string)($_POST['intent_failed_redirect'] ?? ''), DEFAULT_INTENT_FAILED_REDIRECT);

    /* ✅ TELEGRAM & PG INPUTS */
    $telegram_id = trim($_POST['telegram_id'] ?? '');
    $telegram_channel = trim($_POST['telegram_channel'] ?? '');
    $show_telegram = isset($_POST['show_telegram']) ? 1 : 0;
    $show_telegram_channel = isset($_POST['show_telegram_channel']) ? 1 : 0;

    $pg_provider = trim($_POST['pg_provider'] ?? 'manual_upi');
    $pg_merchant_id = trim($_POST['pg_merchant_id'] ?? '');
    $pg_api_key = trim($_POST['pg_api_key'] ?? '');
    $pg_secret_key = trim($_POST['pg_secret_key'] ?? '');
    $pg_webhook_url = trim($_POST['pg_webhook_url'] ?? '');
    $pg_auto_accept = isset($_POST['pg_auto_accept']) ? 1 : 0;

    /* Category 2 Dedicated Keys */
    $phonepe_merchant_id = trim($_POST['phonepe_merchant_id'] ?? '');
    $phonepe_salt_key = trim($_POST['phonepe_salt_key'] ?? '');
    $phonepe_salt_index = trim($_POST['phonepe_salt_index'] ?? '1');
    $phonepe_webhook_url = trim($_POST['phonepe_webhook_url'] ?? '');
    $razorpay_key_id = trim($_POST['razorpay_key_id'] ?? '');
    $razorpay_key_secret = trim($_POST['razorpay_key_secret'] ?? '');
    $razorpay_webhook_secret = trim($_POST['razorpay_webhook_secret'] ?? '');
    $cashfree_app_id = trim($_POST['cashfree_app_id'] ?? '');
    $cashfree_secret_key = trim($_POST['cashfree_secret_key'] ?? '');
    $cashfree_webhook_signature = trim($_POST['cashfree_webhook_signature'] ?? '');
    $pay0_merchant_id = trim($_POST['pay0_merchant_id'] ?? '');

    /* Category 4 Telegram Bot Keys */
    $telegram_bot_token = trim($_POST['telegram_bot_token'] ?? '');
    $telegram_channel_chat_id = trim($_POST['telegram_channel_chat_id'] ?? '');
    $telegram_bot_auto_post = isset($_POST['telegram_bot_auto_post']) ? 1 : 0;

    /* ✅ WHATSAPP */
    $whatsapp_no_1 = trim($_POST['whatsapp_no_1'] ?? '');
    $whatsapp_no_2 = trim($_POST['whatsapp_no_2'] ?? '');
    $show_whatsapp_deposit = isset($_POST['show_whatsapp_deposit']) ? 1 : 0;
    $whatsapp_deposit_no = trim($_POST['whatsapp_deposit_no'] ?? '');

    $show_pay0 = isset($_POST['show_pay0']) ? 1 : 0;
    $pay0_token = trim($_POST['pay0_token'] ?? '');

    $show_imb = isset($_POST['show_imb']) ? 1 : 0;
    $imb_token = trim($_POST['imb_token'] ?? '');

    $show_yuvipay = isset($_POST['show_yuvipay']) ? 1 : 0;
    $yuvipay_token = trim($_POST['yuvipay_token'] ?? '');
    $yuvipay_merch_id = trim($_POST['yuvipay_merch_id'] ?? '');

    $min_deposit = (int) ($_POST['min_deposit'] ?? 300);
    $min_withdraw = (int) ($_POST['min_withdraw'] ?? 1000);
    $min_bid = (int) ($_POST['min_bid'] ?? 10);

    $enable_phonepe = isset($_POST['enable_phonepe']) ? 1 : 0;
    $enable_gpay = isset($_POST['enable_gpay']) ? 1 : 0;
    $enable_paytm = isset($_POST['enable_paytm']) ? 1 : 0;
    $enable_bhim = isset($_POST['enable_bhim']) ? 1 : 0;
    $enable_amazonpay = isset($_POST['enable_amazonpay']) ? 1 : 0;
    $enable_mobikwik = isset($_POST['enable_mobikwik']) ? 1 : 0;
    $enable_jiofinance = isset($_POST['enable_jiofinance']) ? 1 : 0;

    $crypto_network = strtoupper($crypto_network);
    if (!in_array($crypto_network, ['TRC20', 'ERC20'], true))
        $crypto_network = 'TRC20';

    /* --- UPI QR upload --- */
    if (!empty($_FILES['upi_qr']['name']) && $_FILES['upi_qr']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['upi_qr']['tmp_name'];
        $mime = @mime_content_type($tmpPath);

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if (!isset($allowed[$mime])) {
            $errorMsg = 'Only JPG, PNG or WEBP images allowed for UPI QR.';
        } else {
            $ext = $allowed[$mime];
            $uploadDir = __DIR__ . '/uploads/upi_qr';
            $publicDir = 'uploads/upi_qr';

            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0775, true);

            $newName = 'upi_qr_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $destFs = $uploadDir . '/' . $newName;
            $destWeb = $publicDir . '/' . $newName;

            if (move_uploaded_file($tmpPath, $destFs)) {
                if (!empty($upi_qr)) {
                    $oldFs = __DIR__ . '/' . ltrim($upi_qr, '/');
                    if (strpos($upi_qr, $publicDir) === 0 && file_exists($oldFs))
                        @unlink($oldFs);
                }
                $upi_qr = $destWeb;
            } else {
                $errorMsg = 'Failed to upload UPI QR image.';
            }
        }
    }

    /* --- Crypto QR upload --- */
    if ($errorMsg === '' && !empty($_FILES['crypto_qr']['name']) && $_FILES['crypto_qr']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['crypto_qr']['tmp_name'];
        $mime = @mime_content_type($tmpPath);

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if (!isset($allowed[$mime])) {
            $errorMsg = 'Only JPG, PNG or WEBP images allowed for Crypto QR.';
        } else {
            $ext = $allowed[$mime];
            $uploadDir = __DIR__ . '/uploads/crypto_qr';
            $publicDir = 'uploads/crypto_qr';

            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0775, true);

            $newName = 'crypto_qr_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $destFs = $uploadDir . '/' . $newName;
            $destWeb = $publicDir . '/' . $newName;

            if (move_uploaded_file($tmpPath, $destFs)) {
                if (!empty($crypto_qr)) {
                    $oldFs = __DIR__ . '/' . ltrim($crypto_qr, '/');
                    if (strpos($crypto_qr, $publicDir) === 0 && file_exists($oldFs))
                        @unlink($oldFs);
                }
                $crypto_qr = $destWeb;
            } else {
                $errorMsg = 'Failed to upload Crypto QR image.';
            }
        }
    }

    if ($errorMsg === '') {
        try {
            $stmt = $db->prepare("SELECT id FROM admin_payment_details WHERE id = 1");
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();

            if ($exists) {
                $stmt = $db->prepare("
                    UPDATE admin_payment_details
                       SET bank_name = ?,
                           bank_account_name = ?,
                           bank_account_number = ?,
                           bank_ifsc = ?,
                           bank_branch = ?,
                           bank_note = ?,
                           upi_id = ?,
                           upi_name = ?,
                           upi_note = ?,
                           upi_qr = ?,
                           show_bank = ?,
                           show_upi_id = ?,
                           show_mobile_upi = ?,
                           show_crypto = ?,
                           crypto_name = ?,
                           crypto_network = ?,
                           crypto_address = ?,
                           crypto_qr = ?,
                           inr_rate_per_unit = ?,
                           upi_intent = ?,
                           merchant_upi_name = ?,
                           enable_upi_intent = ?,
                           auto_accept_intent = ?,
                           intent_webhook_url = ?,
                           intent_success_redirect = ?,
                           intent_failed_redirect = ?,
                           whatsapp_no_1 = ?,
                           whatsapp_no_2 = ?,
                           show_whatsapp_deposit = ?,
                           whatsapp_deposit_no = ?,
                           show_pay0 = ?,
                           pay0_token = ?,
                           show_imb = ?,
                           imb_token = ?,
                           show_yuvipay = ?,
                           yuvipay_token = ?,
                           yuvipay_merch_id = ?,
                           min_deposit = ?,
                           min_withdraw = ?,
                           min_bid = ?,
                           enable_phonepe = ?,
                           enable_gpay = ?,
                           enable_paytm = ?,
                           enable_bhim = ?,
                           enable_amazonpay = ?,
                           enable_mobikwik = ?,
                           enable_jiofinance = ?,
                           updated_at = NOW()
                     WHERE id = 1
                ");
            } else {
                // 39 placeholders (matching bind vars)
                $stmt = $db->prepare("
                    INSERT INTO admin_payment_details
                        (id, bank_name, bank_account_name, bank_account_number,
                         bank_ifsc, bank_branch, bank_note,
                         upi_id, upi_name, upi_note, upi_qr,
                         show_bank, show_upi_id, show_mobile_upi, show_crypto,
                         crypto_name, crypto_network, crypto_address, crypto_qr,
                         inr_rate_per_unit, upi_intent, merchant_upi_name,
                         enable_upi_intent, auto_accept_intent,
                         intent_webhook_url, intent_success_redirect, intent_failed_redirect,
                         whatsapp_no_1, whatsapp_no_2, show_whatsapp_deposit, whatsapp_deposit_no,
                         show_pay0, pay0_token, show_imb, imb_token, show_yuvipay, yuvipay_token, yuvipay_merch_id,
                         min_deposit, min_withdraw, min_bid,
                         enable_phonepe, enable_gpay, enable_paytm, enable_bhim, enable_amazonpay, enable_mobikwik, enable_jiofinance,
                         updated_at)
                    VALUES
                        (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
            }

            if (!$stmt)
                throw new Exception('Prepare failed: ' . $db->error);

            /*
              ✅ MAIN FIX:
              Type string length must equal bind variables count (37).
            */
            $stmt->bind_param(
                'ssssssssssiiiissssdssiisssssisisisissiiiiiiiiii',
                $bank_name,
                $bank_account_name,
                $bank_account_number,
                $bank_ifsc,
                $bank_branch,
                $bank_note,
                $upi_id,
                $upi_name,
                $upi_note,
                $upi_qr,
                $show_bank,
                $show_upi_id,
                $show_mobile_upi,
                $show_crypto,
                $crypto_name,
                $crypto_network,
                $crypto_address,
                $crypto_qr,
                $inr_rate_per_unit,
                $upi_intent,
                $merchant_upi_name,
                $enable_upi_intent,
                $auto_accept_intent,
                $intent_webhook_url,
                $intent_success_redirect,
                $intent_failed_redirect,
                $whatsapp_no_1,
                $whatsapp_no_2,
                $show_whatsapp_deposit,
                $whatsapp_deposit_no,
                $show_pay0,
                $pay0_token,
                $show_imb,
                $imb_token,
                $show_yuvipay,
                $yuvipay_token,
                $yuvipay_merch_id,
                $min_deposit,
                $min_withdraw,
                $min_bid,
                $enable_phonepe,
                $enable_gpay,
                $enable_paytm,
                $enable_bhim,
                $enable_amazonpay,
                $enable_mobikwik,
                $enable_jiofinance
            );

            $stmt->execute();
            $stmt->close();

            // Save Category 2 Payment Gateways & Category 4 Telegram Bot settings safely
            try {
                $stPg = $db->prepare("UPDATE admin_payment_details SET 
                    telegram_id = ?, 
                    telegram_channel = ?, 
                    show_telegram = ?, 
                    show_telegram_channel = ?, 
                    pg_provider = ?, 
                    pg_merchant_id = ?, 
                    pg_api_key = ?, 
                    pg_secret_key = ?, 
                    pg_webhook_url = ?, 
                    pg_auto_accept = ?,
                    phonepe_merchant_id = ?,
                    phonepe_salt_key = ?,
                    phonepe_salt_index = ?,
                    phonepe_webhook_url = ?,
                    razorpay_key_id = ?,
                    razorpay_key_secret = ?,
                    razorpay_webhook_secret = ?,
                    cashfree_app_id = ?,
                    cashfree_secret_key = ?,
                    cashfree_webhook_signature = ?,
                    pay0_merchant_id = ?,
                    telegram_bot_token = ?,
                    telegram_channel_chat_id = ?,
                    telegram_bot_auto_post = ?
                    WHERE id = 1");
                if ($stPg) {
                    $stPg->bind_param("ssiisssssisssssssssssssi", 
                        $telegram_id, 
                        $telegram_channel, 
                        $show_telegram, 
                        $show_telegram_channel, 
                        $pg_provider, 
                        $pg_merchant_id, 
                        $pg_api_key, 
                        $pg_secret_key, 
                        $pg_webhook_url, 
                        $pg_auto_accept,
                        $phonepe_merchant_id,
                        $phonepe_salt_key,
                        $phonepe_salt_index,
                        $phonepe_webhook_url,
                        $razorpay_key_id,
                        $razorpay_key_secret,
                        $razorpay_webhook_secret,
                        $cashfree_app_id,
                        $cashfree_secret_key,
                        $cashfree_webhook_signature,
                        $pay0_merchant_id,
                        $telegram_bot_token,
                        $telegram_channel_chat_id,
                        $telegram_bot_auto_post
                    );
                    $stPg->execute();
                    $stPg->close();
                }
            } catch (Throwable $ePg) {}

            $details['telegram_id'] = $telegram_id;
            $details['telegram_channel'] = $telegram_channel;
            $details['show_telegram'] = $show_telegram;
            $details['show_telegram_channel'] = $show_telegram_channel;
            $details['pg_provider'] = $pg_provider;
            $details['pg_merchant_id'] = $pg_merchant_id;
            $details['pg_api_key'] = $pg_api_key;
            $details['pg_secret_key'] = $pg_secret_key;
            $details['pg_webhook_url'] = $pg_webhook_url;
            $details['pg_auto_accept'] = $pg_auto_accept;

            $successMsg = 'Bank, UPI, Payment Gateway, Telegram & WhatsApp settings saved successfully.';

            // refresh details
            $details['bank_name'] = $bank_name;
            $details['bank_account_name'] = $bank_account_name;
            $details['bank_account_number'] = $bank_account_number;
            $details['bank_ifsc'] = $bank_ifsc;
            $details['bank_branch'] = $bank_branch;
            $details['bank_note'] = $bank_note;
            $details['upi_id'] = $upi_id;
            $details['upi_name'] = $upi_name;
            $details['upi_note'] = $upi_note;
            $details['upi_qr'] = $upi_qr;
            $details['show_bank'] = $show_bank;
            $details['show_upi_id'] = $show_upi_id;
            $details['show_mobile_upi'] = $show_mobile_upi;
            $details['show_crypto'] = $show_crypto;
            $details['crypto_name'] = $crypto_name;
            $details['crypto_network'] = $crypto_network;
            $details['crypto_address'] = $crypto_address;
            $details['crypto_qr'] = $crypto_qr;
            $details['inr_rate_per_unit'] = $inr_rate_per_unit;
            $details['upi_intent'] = $upi_intent;
            $details['merchant_upi_name'] = $merchant_upi_name;
            $details['enable_upi_intent'] = $enable_upi_intent;
            $details['auto_accept_intent'] = $auto_accept_intent;

            $details['intent_webhook_url'] = $intent_webhook_url;
            $details['intent_success_redirect'] = $intent_success_redirect;
            $details['intent_failed_redirect'] = $intent_failed_redirect;

            $details['whatsapp_no_1'] = $whatsapp_no_1;
            $details['whatsapp_no_2'] = $whatsapp_no_2;
            $details['show_whatsapp_deposit'] = $show_whatsapp_deposit;
            $details['whatsapp_deposit_no'] = $whatsapp_deposit_no;

            $details['show_pay0'] = $show_pay0;
            $details['pay0_token'] = $pay0_token;

            $details['show_imb'] = $show_imb;
            $details['imb_token'] = $imb_token;

            $details['show_yuvipay'] = $show_yuvipay;
            $details['yuvipay_token'] = $yuvipay_token;
            $details['yuvipay_merch_id'] = $yuvipay_merch_id;

            $details['min_bid'] = $min_bid;

            $details['enable_phonepe'] = $enable_phonepe;
            $details['enable_gpay'] = $enable_gpay;
            $details['enable_paytm'] = $enable_paytm;
            $details['enable_bhim'] = $enable_bhim;
            $details['enable_amazonpay'] = $enable_amazonpay;
            $details['enable_mobikwik'] = $enable_mobikwik;
            $details['enable_jiofinance'] = $enable_jiofinance;
            
            // Standalone update for withdrawal times
            $enable_withdraw_time = isset($_POST['enable_withdraw_time']) ? 1 : 0;
            $withdraw_start = trim($_POST['withdraw_start_time'] ?? '07:00 AM');
            $withdraw_end = trim($_POST['withdraw_end_time'] ?? '11:00 AM');
            $withdraw_note = trim($_POST['withdraw_note'] ?? 'Withdrawal allowed from 7:00 AM to 11:00 AM only.');
            
            $stWd = $db->prepare("UPDATE withdrawal_time SET enable_withdraw_time=?, withdraw_start_time=?, withdraw_end_time=?, withdraw_note=? WHERE id=1");
            $stWd->bind_param('isss', $enable_withdraw_time, $withdraw_start, $withdraw_end, $withdraw_note);
            $stWd->execute();
            $stWd->close();
            
            // Dynamically update withdrawal_time.sql
            $sqlContent = "-- Dedicated Database table for Withdrawal configurations\n" .
                          "CREATE TABLE IF NOT EXISTS withdrawal_time (\n" .
                          "    id INT AUTO_INCREMENT PRIMARY KEY,\n" .
                          "    enable_withdraw_time TINYINT(1) NOT NULL DEFAULT 1,\n" .
                          "    withdraw_start_time VARCHAR(50) NOT NULL DEFAULT '07:00 AM',\n" .
                          "    withdraw_end_time VARCHAR(50) NOT NULL DEFAULT '11:00 AM',\n" .
                          "    withdraw_note VARCHAR(255) NOT NULL DEFAULT 'Withdrawal allowed from 7:00 AM to 11:00 AM only.'\n" .
                          ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n" .
                          "-- Insert dynamic row\n" .
                          "INSERT INTO withdrawal_time (id, enable_withdraw_time, withdraw_start_time, withdraw_end_time, withdraw_note)\n" .
                          "VALUES (1, " . $enable_withdraw_time . ", '" . $db->real_escape_string($withdraw_start) . "', '" . $db->real_escape_string($withdraw_end) . "', '" . $db->real_escape_string($withdraw_note) . "')\n" .
                          "ON DUPLICATE KEY UPDATE enable_withdraw_time=VALUES(enable_withdraw_time), withdraw_start_time=VALUES(withdraw_start_time), withdraw_end_time=VALUES(withdraw_end_time), withdraw_note=VALUES(withdraw_note);";
            
            @file_put_contents(__DIR__ . '/withdrawal_time.sql', $sqlContent);
            
            $details['enable_withdraw_time'] = $enable_withdraw_time;
            
            @file_put_contents(__DIR__ . '/withdrawal_time.sql', $sqlContent);
            
            $details['withdraw_start_time'] = $withdraw_start;
            $details['withdraw_end_time'] = $withdraw_end;
            $details['withdraw_note'] = $withdraw_note;

            $details['updated_at'] = date('Y-m-d H:i:s');
        } catch (Exception $e) {
            $errorMsg = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <title>Admin – Bank, UPI & Crypto Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f0e5;
        }

        .container {
            max-width: 860px;
            margin: 28px auto;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.15);
            padding: 22px 18px 26px 18px;
        }

        .top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .top-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 14px;
            border-radius: 999px;
            border: 1px solid #b71c1c;
            background: #fff;
            font-size: 13px;
            color: #b71c1c;
            text-decoration: none;
            cursor: pointer;
        }

        .back-btn span {
            font-size: 15px;
            margin-right: 4px;
        }

        h2 {
            margin: 0;
            font-size: 19px;
            color: #222;
        }

        .updated-at {
            font-size: 11px;
            color: #795548;
            text-align: right;
        }

        .section-title {
            margin-top: 18px;
            margin-bottom: 8px;
            font-size: 15px;
            font-weight: 600;
            color: #4e342e;
            border-left: 4px solid #b71c1c;
            padding-left: 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px 14px;
        }

        label {
            display: block;
            font-size: 13px;
            margin-bottom: 3px;
            color: #555;
        }

        input[type="text"],
        textarea,
        input[type="file"],
        input[type="number"],
        select {
            width: 100%;
            box-sizing: border-box;
            border-radius: 8px;
            border: 1px solid #d7ccc8;
            padding: 8px 9px;
            font-size: 13px;
            outline: none;
            background: #faf8f5;
        }

        textarea {
            resize: vertical;
            min-height: 60px;
        }

        input[type="text"]:focus,
        textarea:focus,
        input[type="number"]:focus,
        select:focus {
            border-color: #b71c1c;
            background: #fff;
            box-shadow: 0 0 0 2px rgba(183, 28, 28, 0.08);
        }

        .btn-row {
            margin-top: 18px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-save {
            border: none;
            padding: 9px 18px;
            border-radius: 999px;
            background: #b71c1c;
            color: #fff;
            font-size: 13px;
            cursor: pointer;
            box-shadow: 0 3px 8px rgba(183, 28, 28, 0.45);
        }

        .btn-save:hover {
            filter: brightness(1.05);
        }

        .msg {
            margin-bottom: 10px;
            font-size: 13px;
            border-radius: 8px;
            padding: 8px 10px;
        }

        .msg.success {
            background: #e8f5e9;
            color: #1b5e20;
            border: 1px solid #c8e6c9;
        }

        .msg.error {
            background: #ffebee;
            color: #b71c1c;
            border: 1px solid #ffcdd2;
        }

        /* Success Popup / Toast Styling */
        #successPopup {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            z-index: 10000;
            animation: slideIn 0.5s ease-out;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
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

        .qr-preview {
            margin-top: 8px;
            padding: 6px;
            border-radius: 10px;
            background: #fff8e1;
            border: 1px solid #ffe082;
            text-align: center;
        }

        .qr-preview img {
            max-width: 150px;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
        }

        .qr-preview .hint {
            margin-top: 4px;
            font-size: 11px;
            color: #6d4c41;
        }

        .switch-card {
            margin-top: 10px;
            margin-bottom: 10px;
            background: #fff7f5;
            border-radius: 12px;
            border: 1px solid #ffccbc;
            padding: 10px 12px;
        }

        .switch-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 4px;
            border-radius: 8px;
            gap: 10px;
        }

        .switch-row span.label-text {
            font-size: 13px;
            color: #4e342e;
        }

        .toggle {
            position: relative;
            width: 44px;
            height: 22px;
            flex: 0 0 auto;
        }

        .toggle input {
            display: none;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 22px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 17px;
            width: 17px;
            left: 3px;
            bottom: 2.5px;
            background-color: #fff;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
        }

        .toggle input:checked+.slider {
            background-color: #c62828;
        }

        .toggle input:checked+.slider:before {
            transform: translateX(20px);
        }

        .warn-text {
            font-size: 11px;
            color: #b71c1c;
            margin-top: 4px;
            line-height: 1.35;
        }

        @media (max-width: 600px) {
            .container {
                padding: 12px;
                margin-top: 18px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="top-row">
            <div class="top-left">
                <a href="admin_dashboard.php" class="back-btn">
                    <span>&larr;</span> Back to Dashboard
                </a>
                <h2>Bank, UPI & Crypto Settings</h2>
            </div>
            <div class="updated-at">
                <?php
                require_once 'includes/db.php';
                if (!empty($details['updated_at'])): ?>
                    Last Updated: <?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['updated_at']); ?>
                    <?php
                    require_once 'includes/db.php';
                else: ?>
                    Not updated yet
                    <?php
                    require_once 'includes/db.php';
                endif; ?>
            </div>
        </div>

        <?php if ($successMsg): ?>
            <div id="successPopup" style="display: flex;">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($successMsg); ?></span>
            </div>
            <audio id="notifSound" src="sound/notification.mp3" preload="auto"></audio>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.__bankSettingsSavedOnce) return;
                    window.__bankSettingsSavedOnce = true;

                    const sound = document.getElementById('notifSound');
                    if (sound) {
                        sound.play().catch(e => console.log("Audio play blocked by browser. Click anywhere to play next time."));
                    }
                    setTimeout(() => {
                        const pop = document.getElementById('successPopup');
                        if (pop) pop.style.display = 'none';
                    }, 5000);
                    setTimeout(() => {
                        window.location.replace(window.location.pathname + window.location.search);
                    }, 3000);
                });
            </script>
        <?php endif; ?>
        <?php
        require_once 'includes/db.php';
        if ($errorMsg): ?>
            <div class="msg error"><?php
            require_once 'includes/db.php';
            echo htmlspecialchars($errorMsg); ?></div>
            <?php
            require_once 'includes/db.php';
        endif; ?>

        <form method="post" enctype="multipart/form-data">

            <div class="section-title">Transaction Limits</div>
            <div class="form-grid">
                <div>
                    <label>Minimum Deposit Amount</label>
                    <input type="number" name="min_deposit" value="<?php echo (int) $details['min_deposit']; ?>"
                        placeholder="300">
                </div>
                <div>
                    <label>Minimum Withdrawal Amount</label>
                    <input type="number" name="min_withdraw" value="<?php echo (int) $details['min_withdraw']; ?>"
                        placeholder="1000">
                </div>
                <div>
                    <label>Minimum Bid Amount</label>
                    <input type="number" name="min_bid" value="<?php echo (int) $details['min_bid']; ?>"
                        placeholder="10">
                </div>
            </div>

            <div class="section-title" style="margin-top:22px;">Show / Hide Methods (User Wallet, Withdrawal & KYC)
            </div>
            <div class="switch-card">
                <div class="switch-row">
                    <span class="label-text">Bank (NEFT / IMPS / RTGS)</span>
                    <label class="toggle">
                        <input type="checkbox" name="show_bank" <?php
                        require_once 'includes/db.php';
                        echo !empty($details['show_bank']) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="switch-row">
                    <span class="label-text">UPI ID (xxxx@upi)</span>
                    <label class="toggle">
                        <input type="checkbox" name="show_upi_id" <?php
                        require_once 'includes/db.php';
                        echo !empty($details['show_upi_id']) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="switch-row">
                    <span class="label-text">Mobile UPI (PhonePe / GPay / Paytm)</span>
                    <label class="toggle">
                        <input type="checkbox" name="show_mobile_upi" <?php
                        require_once 'includes/db.php';
                        echo !empty($details['show_mobile_upi']) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="switch-row">
                    <span class="label-text">Crypto (USDT) – Deposit + Crypto KYC</span>
                    <label class="toggle">
                        <input type="checkbox" name="show_crypto" <?php
                        require_once 'includes/db.php';
                        echo !empty($details['show_crypto']) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="switch-row">
                    <span class="label-text">WhatsApp Deposit (Direct Chat)</span>
                    <label class="toggle">
                        <input type="checkbox" name="show_whatsapp_deposit" <?php
                        require_once 'includes/db.php';
                        echo !empty($details['show_whatsapp_deposit']) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="switch-row">
                    <span class="label-text">Fast UPI (Pay0 - Automated)</span>
                    <label class="toggle">
                        <input type="checkbox" name="show_pay0" <?php
                        echo !empty($details['show_pay0']) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="switch-row">
                    <span class="label-text">IMB Payment Gateway</span>
                    <label class="toggle">
                        <input type="checkbox" name="show_imb" <?php
                        echo !empty($details['show_imb']) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="section-title">Bank Details (For NEFT / IMPS / RTGS)</div>
            <div class="form-grid">
                <div>
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['bank_name']); ?>" placeholder="e.g. HDFC Bank">
                </div>
                <div>
                    <label>Account Holder Name</label>
                    <input type="text" name="bank_account_name" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['bank_account_name']); ?>" placeholder="e.g. LALA 111">
                </div>
                <div>
                    <label>Account Number</label>
                    <input type="text" name="bank_account_number" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['bank_account_number']); ?>" placeholder="e.g. 123456789012">
                </div>
                <div>
                    <label>IFSC Code</label>
                    <input type="text" name="bank_ifsc" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['bank_ifsc']); ?>" placeholder="e.g. HDFC0001234">
                </div>
                <div>
                    <label>Branch</label>
                    <input type="text" name="bank_branch" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['bank_branch']); ?>" placeholder="e.g. Mumbra Branch">
                </div>
                <div>
                    <label>Note (Optional)</label>
                    <textarea name="bank_note" placeholder="e.g. Send payment screenshot on Live Chat."><?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['bank_note']);
                    ?></textarea>
                </div>
            </div>

            <div class="section-title" style="margin-top:22px;">UPI Details + QR</div>
            <div class="form-grid">
                <div>
                    <label>UPI ID</label>
                    <input type="text" name="upi_id" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['upi_id']); ?>" placeholder="e.g. 9137355129@yesbank">
                </div>
                <div>
                    <label>UPI Name (Receiver)</label>
                    <input type="text" name="upi_name" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['upi_name']); ?>" placeholder="e.g. LALA 111">
                </div>
                <div>
                    <label>UPI Note (Optional)</label>
                    <textarea name="upi_note"
                        placeholder="e.g. Use only this UPI for deposit. Do not pay on old numbers."><?php
                        require_once 'includes/db.php';
                        echo htmlspecialchars($details['upi_note']);
                        ?></textarea>
                </div>
                <div>
                    <label>UPI QR Image (JPG / PNG / WEBP)</label>
                    <input type="file" name="upi_qr" id="upiQrInput" accept="image/*">
                    <?php
                    require_once 'includes/db.php';
                    if (!empty($details['upi_qr'])): ?>
                        <div class="qr-preview" id="upiQrPreviewWrap">
                            <img id="upiQrPreviewImg" src="<?php
                            require_once 'includes/db.php';
                            echo htmlspecialchars($details['upi_qr']); ?>" alt="UPI QR">
                            <div class="hint" id="upiQrPreviewHint">
                                This QR will be shown to users in <b>user_wallet.php</b> Deposit section.
                            </div>
                        </div>
                        <?php
                        require_once 'includes/db.php';
                    else: ?>
                        <div class="qr-preview" id="upiQrPreviewWrap" style="display:none;">
                            <img id="upiQrPreviewImg" src="" alt="UPI QR Preview">
                            <div class="hint" id="upiQrPreviewHint">New QR preview. Save changes to publish it.</div>
                        </div>
                        <?php
                    endif; ?>
                </div>
            </div>

            <div class="section-title" style="margin-top:22px;">UPI Intent (Merchant)</div>
            <div class="form-grid">
                <div>
                    <label>Intent UPI ID (merchant)</label>
                    <input type="text" name="upi_intent" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['upi_intent']); ?>" placeholder="e.g. lala111@upi (merchant UPI)">
                </div>
                <div>
                    <label>Merchant UPI Name</label>
                    <input type="text" name="merchant_upi_name" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['merchant_upi_name']); ?>" placeholder="e.g. LALA 111 MERCHANT">
                </div>
            </div>

            <div class="section-title" style="margin-top:12px;">UPI Intent URLs (Optional)</div>
            <div class="form-grid">
                <div>
                    <label>Intent Webhook URL</label>
                    <input type="text" name="intent_webhook_url" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['intent_webhook_url']); ?>"
                        placeholder="https://dp-boss.fun/webhook.php">
                    <div class="warn-text">Gateway webhook use kar rahe ho to yahan set karo.</div>
                </div>
                <div>
                    <label>SUCCESS Redirect URL</label>
                    <input type="text" name="intent_success_redirect" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['intent_success_redirect']); ?>"
                        placeholder="https://dp-boss.fun/user_wallet.php?status=SUCCESS">
                    <div class="warn-text">UPI success ke baad app/webview yahan redirect kare.</div>
                </div>
                <div>
                    <label>FAILED Redirect URL</label>
                    <input type="text" name="intent_failed_redirect" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['intent_failed_redirect']); ?>"
                        placeholder="https://dp-boss.fun/user_wallet.php?status=FAILED">
                    <div class="warn-text">UPI fail/cancel ke baad yahan redirect.</div>
                </div>
            </div>

            <div class="section-title" style="margin-top:22px;">UPI Intent Apps Control (User Wallet)</div>
            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                <div class="switch-card" style="padding: 10px;">
                    <div class="switch-row">
                        <span class="label-text"><img src="images/phonpe-icon.png" style="width:20px; vertical-align:middle; margin-right:5px;"> PhonePe</span>
                        <label class="toggle">
                            <input type="checkbox" name="enable_phonepe" <?php echo $details['enable_phonepe'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="switch-card" style="padding: 10px;">
                    <div class="switch-row">
                        <span class="label-text"><img src="images/gpay-icon.png" style="width:20px; vertical-align:middle; margin-right:5px;"> Google Pay</span>
                        <label class="toggle">
                            <input type="checkbox" name="enable_gpay" <?php echo $details['enable_gpay'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="switch-card" style="padding: 10px;">
                    <div class="switch-row">
                        <span class="label-text"><img src="images/paytm-icon.png" style="width:20px; vertical-align:middle; margin-right:5px;"> Paytm</span>
                        <label class="toggle">
                            <input type="checkbox" name="enable_paytm" <?php echo $details['enable_paytm'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="switch-card" style="padding: 10px;">
                    <div class="switch-row">
                        <span class="label-text"><img src="images/bhim-icon.png" style="width:20px; vertical-align:middle; margin-right:5px;"> BHIM</span>
                        <label class="toggle">
                            <input type="checkbox" name="enable_bhim" <?php echo $details['enable_bhim'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="switch-card" style="padding: 10px;">
                    <div class="switch-row">
                        <span class="label-text"><img src="images/amazon-pay-icon.png" style="width:20px; vertical-align:middle; margin-right:5px;"> Amazon Pay</span>
                        <label class="toggle">
                            <input type="checkbox" name="enable_amazonpay" <?php echo $details['enable_amazonpay'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="switch-card" style="padding: 10px;">
                    <div class="switch-row">
                        <span class="label-text"><img src="images/mobikwik-icon.png" style="width:20px; vertical-align:middle; margin-right:5px;"> MobiKwik</span>
                        <label class="toggle">
                            <input type="checkbox" name="enable_mobikwik" <?php echo $details['enable_mobikwik'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="switch-card" style="padding: 10px;">
                    <div class="switch-row">
                        <span class="label-text"><img src="images/jiopay-icon.png" style="width:20px; vertical-align:middle; margin-right:5px;"> Jio Finance</span>
                        <label class="toggle">
                            <input type="checkbox" name="enable_jiofinance" <?php echo $details['enable_jiofinance'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="section-title" style="margin-top:22px;">Pay0 Payment Gateway (Fast UPI)</div>
            <div class="form-grid">
                <div>
                    <label>Pay0 User Token</label>
                    <input type="text" name="pay0_token" value="<?php
                    echo htmlspecialchars($details['pay0_token']); ?>" placeholder="Enter Pay0 User Token">
                    <div class="warn-text">Copy from Pay0 Dashboard -> Settings/API.</div>
                </div>
            </div>
            
            <div class="switch-card" style="margin-top:15px; border-left:4px solid #c62828;">
                <div class="switch-row">
                    <span class="label-text">
                        <b>Enable Pay0 (Fast UPI)?</b> <br>
                        <small>If enabled, it will be the ONLY deposit method shown to users.</small>
                    </span>
                    <label class="toggle">
                        <input type="checkbox" name="show_pay0" <?php
                        echo !empty($details['show_pay0']) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="section-title" style="margin-top:22px;">IMB Payment Gateway</div>
            <div class="form-grid">
                <div>
                    <label>IMB Access Token</label>
                    <input type="text" name="imb_token" value="<?php
                    echo htmlspecialchars($details['imb_token']); ?>" placeholder="Enter IMB Access Token">
                </div>
            </div>
            
            <div class="switch-card" style="margin-top:15px; border-left:4px solid #c62828;">
                <div class="switch-row">
                    <span class="label-text">
                        <b>Enable IMB Gateway?</b> <br>
                        <small>If enabled, it will be shown alongside other payment methods or act as the primary method.</small>
                    </span>
                    <label class="toggle">
                        <input type="checkbox" name="show_imb" <?php
                        echo !empty($details['show_imb']) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="section-title" style="margin-top:22px;">YuviPay Payment Gateway (https://yuvipay.org/)</div>
            <div class="form-grid">
                <div>
                    <label>YuviPay Merchant ID</label>
                    <input type="text" name="yuvipay_merch_id" value="<?php
                    echo htmlspecialchars($details['yuvipay_merch_id']); ?>" placeholder="Enter YuviPay Merchant ID">
                </div>
                <div>
                    <label>YuviPay Secret Key</label>
                    <input type="text" name="yuvipay_token" value="<?php
                    echo htmlspecialchars($details['yuvipay_token']); ?>" placeholder="Enter YuviPay Secret Key">
                </div>
            </div>
            
            <div class="switch-card" style="margin-top:15px; border-left:4px solid #c62828;">
                <div class="switch-row">
                    <span class="label-text">
                        <b>Enable YuviPay Gateway?</b> <br>
                        <small>If enabled, it will be shown to users as a payment option.</small>
                    </span>
                    <label class="toggle">
                        <input type="checkbox" name="show_yuvipay" <?php
                        echo !empty($details['show_yuvipay']) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="switch-card">
                <div class="switch-row">
                    <span class="label-text">
                        UPI Intent Flow (PhonePe / GPay / Paytm / BHIM buttons app me dikhao?)
                    </span>
                    <label class="toggle">
                        <input type="checkbox" name="enable_upi_intent" <?php
                        require_once 'includes/db.php';
                        echo !empty($details['enable_upi_intent']) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="switch-row">
                    <span class="label-text">
                        UPI Intent se aane wale <b>successful</b> payment ka deposit request auto-accept kare?
                    </span>
                    <label class="toggle">
                        <input type="checkbox" name="auto_accept_intent" <?php
                        require_once 'includes/db.php';
                        echo !empty($details['auto_accept_intent']) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="warn-text">
                    Note: Auto-accept ka logic aap <b>deposit_req.php</b> / webhook me handle karoge.
                </div>
            </div>



            <div class="section-title" style="margin-top:22px;">Crypto (USDT) Settings</div>
            <div class="form-grid">
                <div>
                    <label>Crypto Name</label>
                    <input type="text" name="crypto_name" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['crypto_name']); ?>" placeholder="USDT">
                </div>
                <div>
                    <label>Network</label>
                    <select name="crypto_network">
                        <?php
                        require_once 'includes/db.php';
                        $net = strtoupper($details['crypto_network'] ?? 'TRC20');
                        if (!in_array($net, ['TRC20', 'ERC20'], true))
                            $net = 'TRC20';
                        ?>
                        <option value="TRC20" <?php
                        require_once 'includes/db.php';
                        echo $net === 'TRC20' ? 'selected' : ''; ?>>TRC20</option>
                        <option value="ERC20" <?php
                        require_once 'includes/db.php';
                        echo $net === 'ERC20' ? 'selected' : ''; ?>>ERC20</option>
                    </select>
                </div>
                <div>
                    <label>Crypto Address</label>
                    <textarea name="crypto_address" placeholder="USDT Address"><?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['crypto_address']); ?></textarea>
                    <div class="warn-text">
                        Note: Sirf isi address par fund accept karein. Galat network pe bheje gaye fund lost ho sakte
                        hain.
                    </div>
                </div>
                <div>
                    <label>Crypto QR Image (JPG / PNG / WEBP)</label>
                    <input type="file" name="crypto_qr" accept="image/*">
                    <?php
                    require_once 'includes/db.php';
                    if (!empty($details['crypto_qr'])): ?>
                        <div class="qr-preview">
                            <img src="<?php
                            require_once 'includes/db.php';
                            echo htmlspecialchars($details['crypto_qr']); ?>" alt="Crypto QR">
                            <div class="hint">
                                Yeh QR user_wallet.php me Crypto section me show hoga.
                            </div>
                        </div>
                        <?php
                        require_once 'includes/db.php';
                    endif; ?>
                </div>
                <div>
                    <label>1 <?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars($details['crypto_name']); ?> = INR</label>
                    <input type="number" step="0.0001" name="inr_rate_per_unit" value="<?php
                    require_once 'includes/db.php';
                    echo htmlspecialchars((float) $details['inr_rate_per_unit']); ?>" placeholder="90.0000">
                    <div class="warn-text">
                        Example: Agar 1 USDT = ₹92.50 hai to yaha 92.50 daalein.
                    </div>
                </div>
            </div>

            
            <!-- ==================== CATEGORY 2: PAYMENT GATEWAYS API ==================== -->
            <div class="section-title" style="margin-top:25px; border-left: 4px solid #8b5cf6; padding-left: 10px;" id="pg_settings">
                <i class="fas fa-credit-card" style="color:#8b5cf6;"></i> Category 2: Payment Gateway API Settings
            </div>
            
            <div class="form-grid">
                <div style="grid-column: 1 / -1;">
                    <label style="font-size: 15px; color:#8b5cf6;"><b>⚡ Primary Active Payment Gateway</b></label>
                    <select name="pg_provider" style="width:100%; padding:12px; border-radius:8px; border:2px solid #8b5cf6; background:#fff; font-weight:700; font-size:15px;">
                        <option value="manual_upi" <?php echo ($details['pg_provider'] ?? '') === 'manual_upi' ? 'selected' : ''; ?>>🪙 Manual UPI / Custom QR Code (Zero Fees)</option>
                        <option value="phonepe" <?php echo ($details['pg_provider'] ?? '') === 'phonepe' ? 'selected' : ''; ?>>🟣 PhonePe PG (Merchant Business Gateway)</option>
                        <option value="razorpay" <?php echo ($details['pg_provider'] ?? '') === 'razorpay' ? 'selected' : ''; ?>>🔵 Razorpay Payment Gateway</option>
                        <option value="cashfree" <?php echo ($details['pg_provider'] ?? '') === 'cashfree' ? 'selected' : ''; ?>>🟢 Cashfree Payment Gateway</option>
                        <option value="upi_intent" <?php echo ($details['pg_provider'] ?? '') === 'upi_intent' ? 'selected' : ''; ?>>📲 UPI Direct Intent (PhonePe/GPay/Paytm)</option>
                        <option value="pay0" <?php echo ($details['pg_provider'] ?? '') === 'pay0' ? 'selected' : ''; ?>>🟠 Pay0 Payment Gateway</option>
                        <option value="yuvipay" <?php echo ($details['pg_provider'] ?? '') === 'yuvipay' ? 'selected' : ''; ?>>🟡 YuviPay Payment Gateway</option>
                        <option value="imb" <?php echo ($details['pg_provider'] ?? '') === 'imb' ? 'selected' : ''; ?>>🔴 IMB Payment Gateway</option>
                    </select>
                    <div class="warn-text">Aap yahan se jis gateway ko select karenge, users ko deposit page par wahi gateway sabse upar dikhega.</div>
                </div>
            </div>

            <!-- 1. PhonePe Business PG Settings -->
            <div style="background: rgba(139,92,246,0.04); border: 1px solid rgba(139,92,246,0.2); border-radius: 12px; padding: 16px; margin-top: 15px;">
                <div style="font-weight: 700; font-size: 14px; color: #6d28d9; margin-bottom: 12px; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-mobile-screen"></i> PhonePe Business Payment Gateway
                </div>
                <div class="form-grid">
                    <div>
                        <label>PhonePe Merchant ID</label>
                        <input type="text" name="phonepe_merchant_id" value="<?php echo htmlspecialchars($details['phonepe_merchant_id'] ?? ''); ?>" placeholder="e.g. M22xxxxxxxxx">
                    </div>
                    <div>
                        <label>PhonePe Salt Key (Production)</label>
                        <input type="text" name="phonepe_salt_key" value="<?php echo htmlspecialchars($details['phonepe_salt_key'] ?? ''); ?>" placeholder="Enter PhonePe Salt Key">
                    </div>
                    <div>
                        <label>PhonePe Salt Index</label>
                        <input type="text" name="phonepe_salt_index" value="<?php echo htmlspecialchars($details['phonepe_salt_index'] ?? '1'); ?>" placeholder="Usually 1">
                    </div>
                    <div>
                        <label>PhonePe Webhook URL (For Instant Callback)</label>
                        <input type="text" name="phonepe_webhook_url" value="<?php echo htmlspecialchars(!empty($details['phonepe_webhook_url']) ? $details['phonepe_webhook_url'] : ('https://' . ($_SERVER['HTTP_HOST'] ?? 'subhgaming.online') . '/webhook_phonepe.php')); ?>">
                    </div>
                </div>
            </div>

            <!-- 2. Razorpay PG Settings -->
            <div style="background: rgba(59,130,246,0.04); border: 1px solid rgba(59,130,246,0.2); border-radius: 12px; padding: 16px; margin-top: 15px;">
                <div style="font-weight: 700; font-size: 14px; color: #2563eb; margin-bottom: 12px; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-bolt"></i> Razorpay Payment Gateway
                </div>
                <div class="form-grid">
                    <div>
                        <label>Razorpay Key ID</label>
                        <input type="text" name="razorpay_key_id" value="<?php echo htmlspecialchars($details['razorpay_key_id'] ?? ''); ?>" placeholder="e.g. rzp_live_xxxxxxxx">
                    </div>
                    <div>
                        <label>Razorpay Key Secret</label>
                        <input type="text" name="razorpay_key_secret" value="<?php echo htmlspecialchars($details['razorpay_key_secret'] ?? ''); ?>" placeholder="Enter Razorpay Secret Key">
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label>Razorpay Webhook Secret</label>
                        <input type="text" name="razorpay_webhook_secret" value="<?php echo htmlspecialchars($details['razorpay_webhook_secret'] ?? ''); ?>" placeholder="Enter Webhook Secret for callback verification">
                    </div>
                </div>
            </div>

            <!-- 3. Cashfree PG Settings -->
            <div style="background: rgba(16,185,129,0.04); border: 1px solid rgba(16,185,129,0.2); border-radius: 12px; padding: 16px; margin-top: 15px;">
                <div style="font-weight: 700; font-size: 14px; color: #059669; margin-bottom: 12px; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-shield-halved"></i> Cashfree Payment Gateway
                </div>
                <div class="form-grid">
                    <div>
                        <label>Cashfree App ID / Client ID</label>
                        <input type="text" name="cashfree_app_id" value="<?php echo htmlspecialchars($details['cashfree_app_id'] ?? ''); ?>" placeholder="e.g. CF_APP_xxxx">
                    </div>
                    <div>
                        <label>Cashfree Secret Key</label>
                        <input type="text" name="cashfree_secret_key" value="<?php echo htmlspecialchars($details['cashfree_secret_key'] ?? ''); ?>" placeholder="Enter Cashfree Secret Key">
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label>Cashfree Webhook Signature / Secret</label>
                        <input type="text" name="cashfree_webhook_signature" value="<?php echo htmlspecialchars($details['cashfree_webhook_signature'] ?? ''); ?>" placeholder="Enter Webhook Signature">
                    </div>
                </div>
            </div>

            <!-- 4. Pay0 / YuviPay / IMB Settings -->
            <div style="background: rgba(245,158,11,0.04); border: 1px solid rgba(245,158,11,0.2); border-radius: 12px; padding: 16px; margin-top: 15px;">
                <div style="font-weight: 700; font-size: 14px; color: #d97706; margin-bottom: 12px; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-wallet"></i> Pay0 / YuviPay / IMB Gateways
                </div>
                <div class="form-grid">
                    <div>
                        <label>Pay0 Merchant Token</label>
                        <input type="text" name="pay0_token" value="<?php echo htmlspecialchars($details['pay0_token'] ?? ''); ?>" placeholder="Enter Pay0 Token">
                    </div>
                    <div>
                        <label>Pay0 Merchant ID</label>
                        <input type="text" name="pay0_merchant_id" value="<?php echo htmlspecialchars($details['pay0_merchant_id'] ?? ''); ?>" placeholder="Enter Pay0 Merchant ID">
                    </div>
                    <div>
                        <label>YuviPay Merchant Token</label>
                        <input type="text" name="yuvipay_token" value="<?php echo htmlspecialchars($details['yuvipay_token'] ?? ''); ?>" placeholder="Enter YuviPay Token">
                    </div>
                    <div>
                        <label>YuviPay Merchant ID</label>
                        <input type="text" name="yuvipay_merch_id" value="<?php echo htmlspecialchars($details['yuvipay_merch_id'] ?? ''); ?>" placeholder="Enter YuviPay Merchant ID">
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label>IMB Gateway Token</label>
                        <input type="text" name="imb_token" value="<?php echo htmlspecialchars($details['imb_token'] ?? ''); ?>" placeholder="Enter IMB Token">
                    </div>
                </div>
            </div>

            <div class="switch-card" style="margin-top:15px;">
                <div class="switch-row">
                    <span class="label-text"><b>Auto-Approve Webhook Verified Deposits?</b> (Instant balance addition on success)</span>
                    <label class="toggle">
                        <input type="checkbox" name="pg_auto_accept" <?php echo !empty($details['pg_auto_accept']) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <!-- ==================== CATEGORY 4: SOCIAL & TELEGRAM BOT API ==================== -->
            <div class="section-title" style="margin-top:25px; border-left: 4px solid #0088cc; padding-left: 10px;" id="telegram_settings">
                <i class="fab fa-telegram" style="color:#0088cc;"></i> Category 4: Telegram Support & Official Bot API Settings
            </div>

            <div style="background: rgba(0,136,204,0.04); border: 1px solid rgba(0,136,204,0.2); border-radius: 12px; padding: 16px; margin-top: 15px;">
                <div style="font-weight: 700; font-size: 14px; color: #0088cc; margin-bottom: 12px; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-headset"></i> Telegram User Support & Official Channel Links
                </div>
                <div class="form-grid">
                    <div>
                        <label>Telegram Support Username / Link</label>
                        <input type="text" name="telegram_id"
                            value="<?php echo htmlspecialchars($details['telegram_id'] ?? ''); ?>"
                            placeholder="e.g. @SubhGaming_Support or https://t.me/subhgaming_support">
                        <div class="warn-text">User App drawer aur Support Modal me 1-on-1 direct support ke liye dikhega.</div>
                    </div>
                    <div>
                        <label>Telegram Official Channel Link</label>
                        <input type="text" name="telegram_channel"
                            value="<?php echo htmlspecialchars($details['telegram_channel'] ?? ''); ?>"
                            placeholder="e.g. https://t.me/subhgaming_official">
                        <div class="warn-text">App drawer me players ko daily results & notices channel join karne ke liye dikhega.</div>
                    </div>
                </div>
                <div class="switch-card" style="margin-top:10px;">
                    <div class="switch-row">
                        <span class="label-text"><b>Show Telegram Support & Channel to Users in App Drawer?</b></span>
                        <label class="toggle">
                            <input type="checkbox" name="show_telegram" <?php echo !empty($details['show_telegram']) ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Telegram Bot API for Automated Channel Broadcast -->
            <div style="background: rgba(0,136,204,0.04); border: 1px solid rgba(0,136,204,0.2); border-radius: 12px; padding: 16px; margin-top: 15px;">
                <div style="font-weight: 700; font-size: 14px; color: #0284c7; margin-bottom: 12px; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-robot"></i> Telegram Bot API (Automated Channel Broadcast)
                </div>
                <div class="form-grid">
                    <div>
                        <label>Telegram Bot API Token (from @BotFather)</label>
                        <input type="text" name="telegram_bot_token"
                            value="<?php echo htmlspecialchars($details['telegram_bot_token'] ?? ''); ?>"
                            placeholder="e.g. 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ">
                        <div class="warn-text">Telegram par @BotFather se free bot banakar token yahan daalein.</div>
                    </div>
                    <div>
                        <label>Telegram Channel Chat ID / Username</label>
                        <input type="text" name="telegram_channel_chat_id"
                            value="<?php echo htmlspecialchars($details['telegram_channel_chat_id'] ?? ''); ?>"
                            placeholder="e.g. @subhgaming_official or -1001234567890">
                        <div class="warn-text">Apne bot ko is channel me Administrator banakar result auto-post karwayein.</div>
                    </div>
                </div>
                <div class="switch-card" style="margin-top:10px;">
                    <div class="switch-row">
                        <span class="label-text"><b>Auto-Broadcast Game Results to Telegram Channel?</b></span>
                        <label class="toggle">
                            <input type="checkbox" name="telegram_bot_auto_post" <?php echo !empty($details['telegram_bot_auto_post']) ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="section-title" style="margin-top:22px;" id="whatsapp_settings">WhatsApp Support Numbers</div>
            <div class="form-grid">
                <div>
                    <label>WhatsApp Number 1 (General/Dashboard)</label>
                    <input type="text" name="whatsapp_no_1"
                        value="<?php echo htmlspecialchars($details['whatsapp_no_1']); ?>"
                        placeholder="e.g. 91xxxxxxxxxx">
                    <div class="warn-text">Used on Dashboard and Main Support buttons. (Must include country code, e.g.,
                        91)</div>
                </div>
                <div>
                    <label>WhatsApp Number 2 (Wallet/Bank)</label>
                    <input type="text" name="whatsapp_no_2"
                        value="<?php echo htmlspecialchars($details['whatsapp_no_2']); ?>"
                        placeholder="e.g. 91xxxxxxxxxx">
                    <div class="warn-text">Used on Wallet and specialized support sections.</div>
                </div>
                <div>
                    <label>WhatsApp Deposit Number</label>
                    <input type="text" name="whatsapp_deposit_no"
                        value="<?php echo htmlspecialchars($details['whatsapp_deposit_no']); ?>"
                        placeholder="e.g. 91xxxxxxxxxx">
                    <div class="warn-text">Used specifically for the "WhatsApp Deposit" option.</div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px; margin-bottom: 10px; padding-bottom: 5px;">
                <div class="section-title" style="margin: 0; border-bottom: none; padding-bottom: 0;">Withdrawal Time Settings</div>
                <label class="toggle" style="margin-right: 10px;">
                    <input type="checkbox" name="enable_withdraw_time" <?php
                    echo !empty($details['enable_withdraw_time']) ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="form-grid">
                <div>
                    <label>Withdrawal Start Time</label>
                    <input type="text" name="withdraw_start_time" value="<?php echo htmlspecialchars($details['withdraw_start_time'] ?? '07:00 AM'); ?>" placeholder="e.g. 07:00 AM">
                </div>
                <div>
                    <label>Withdrawal End Time</label>
                    <input type="text" name="withdraw_end_time" value="<?php echo htmlspecialchars($details['withdraw_end_time'] ?? '11:00 AM'); ?>" placeholder="e.g. 11:00 AM">
                </div>
                <div style="grid-column: 1 / -1;">
                    <label>Withdrawal Note / Popup Message (Min 20 characters)</label>
                    <textarea name="withdraw_note" placeholder="Enter note for users outside withdrawal hours..."><?php echo htmlspecialchars($details['withdraw_note'] ?? 'Withdrawal allowed from 7:00 AM to 11:00 AM only.'); ?></textarea>
                </div>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn-save">Save Settings</button>
            </div>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const settingsForm = document.querySelector('form[method="post"]');
            const saveBtn = document.querySelector('.btn-save');
            if (settingsForm && saveBtn) {
                settingsForm.addEventListener('submit', function () {
                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Saving...';
                }, { once: true });
            }

            const input = document.getElementById('upiQrInput');
            const wrap = document.getElementById('upiQrPreviewWrap');
            const img = document.getElementById('upiQrPreviewImg');
            const hint = document.getElementById('upiQrPreviewHint');
            if (!input || !wrap || !img) return;

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                if (!file || !file.type || !file.type.startsWith('image/')) {
                    return;
                }
                img.src = URL.createObjectURL(file);
                wrap.style.display = 'block';
                if (hint) {
                    hint.innerHTML = 'New QR preview. Save changes to publish it.';
                }
            });
        });
    </script>
</body>

</html>
