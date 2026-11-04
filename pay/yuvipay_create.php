<?php
/**
 * YuviPay Order Creator - BABA JI
 * Initiates payment using YuviPay API (v1)
 * Documentation logic: hash_hmac('sha256', ksort_params_piped, secret_key)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
$db = db();

$order_id = trim((string)($_GET['order_id'] ?? ''));
$amount = (float)($_GET['amount'] ?? 0);
$uid_raw = trim((string)($_GET['uid'] ?? ''));

// Debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
// log file will be created on first write


// Fetch YuviPay Credentials from Admin Settings
$secret_key = '';
$merch_id = '';
try {
    $res = $db->query("SELECT yuvipay_token, yuvipay_merch_id FROM admin_payment_details WHERE id = 1 LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $secret_key = trim((string)($row['yuvipay_token'] ?? ''));
        $merch_id = trim((string)($row['yuvipay_merch_id'] ?? ''));
    }
} catch (Exception $e) {
    // Ignore db missing column error so it can fallback
}

// Fallbacks from instructions / examples if empty
// Fallback from .env config (no hardcoded secrets)
if ($secret_key === '' || strlen($secret_key) < 10) {
    require_once __DIR__ . '/../includes/config.php';
    $secret_key = defined('YUVIPAY_SECRET_KEY') ? YUVIPAY_SECRET_KEY : '';
}
if ($merch_id === '' || strlen($merch_id) < 5) {
    require_once __DIR__ . '/../includes/config.php';
    $merch_id = defined('YUVIPAY_MERCH_ID') ? YUVIPAY_MERCH_ID : '';
}

// Final safety check to avoid "Merchant not found" if someone swapped token/merch_id
if (strlen($merch_id) > 25 && strlen($secret_key) < 25) {
    if (strpos($merch_id, 'qz') !== false || strpos($merch_id, 'W') !== false) {
        $temp = $merch_id;
        $merch_id = $secret_key;
        $secret_key = $temp;
    }
}


// Fetch User Mobile
$userMobile = '0000000000';
$uid = 0;
if (!is_numeric($uid_raw)) {
    preg_match('/\d+/', $uid_raw, $matches);
    if (!empty($matches[0])) $uid_raw = (string)(int)$matches[0];
}
if (is_numeric($uid_raw) && (int)$uid_raw > 0) {
    $uid = (int)$uid_raw;
    $stmt = $db->prepare("SELECT mobile_no FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->bind_result($userMobile);
    $stmt->fetch();
    $stmt->close();
}

if ($order_id === '' || $amount <= 0 || $uid <= 0) {
    die("Invalid Request: Missing parameters.");
}

// ✅ Flush loading screen INSTANTLY to browser before slow API call
ob_implicit_flush(true);
if (ob_get_level()) ob_end_flush();
echo '<!DOCTYPE html><html><head>
    <link rel="icon" type="image/png" href="images/fevicon.png"><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Opening Payment...</title>
<style>
body{margin:0;background:#0a0a1a;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;font-family:sans-serif;color:#fff;}
.spinner{width:60px;height:60px;border:4px solid rgba(255,255,255,.2);border-top-color:#2e7d32;border-radius:50%;animation:spin .8s linear infinite;margin-bottom:20px;}
@keyframes spin{to{transform:rotate(360deg)}}
h2{font-size:20px;margin:0 0 8px}
p{color:rgba(255,255,255,.6);font-size:14px;margin:0}
</style><link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head><body>
<div class="spinner"></div>
<h2>&#128716; Opening UPI Payment</h2>
<p>Please wait... connecting to gateway</p>
</body></html>';
flush();

// Prepare YuviPay API Call
$api_url = 'https://yuvipay.org/api/v1/order';
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'] ?? 'subhgaming.online';

$data = [
    'customer_mobile' => (string)$userMobile,
    'merch_id'        => $merch_id,
    'amount'          => (int)$amount,
    'order_id'        => $order_id,
    'currency'        => 'INR',
    // ✅ Include uid+amount in redirect so deposit.php can create record as fallback
    'redirect_url'    => "$proto://$host/deposit.php?upi=SUCCESS&order_id=" . urlencode($order_id) . "&uid=" . urlencode((string)$uid) . "&amt=" . urlencode((string)(int)$amount),
    'udf1'            => 'Wallet Deposit',
    'udf2'            => 'User ID: ' . $uid,
    'udf3'            => '',
    'udf4'            => '',
    'udf5'            => ''
];

/**
 * Formula for Generating X-VERIFY:
 * X-VERIFY = hash_hmac('sha256', dataString, secret_key)
 */
function generatexverify(array $params, string $key): string {
    ksort($params);
    $dataString = implode('|', array_map(function ($k, $v) {
        return $k . '=' . $v;
    }, array_keys($params), $params));
    return hash_hmac('sha256', $dataString, $key);
}

$xverify = generatexverify($data, $secret_key);

$headers = [
    'Content-Type: application/json',
    'X-VERIFY: ' . $xverify
];

// Initialize cURL session
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    file_put_contents(__DIR__ . '/yuvipay.log', date('Y-m-d H:i:s') . " | cURL Err: $err\n", FILE_APPEND);
    die("Connection Error.");
}

$result = json_decode((string)$response, true);
file_put_contents(__DIR__ . '/yuvipay.log', date('Y-m-d H:i:s') . " | Req: " . json_encode($data) . " | Res: " . $response . "\n", FILE_APPEND);

if ($result && isset($result['status']) && $result['status'] === true) {
    $paymentUrl = $result['result']['payment_url'] ?? '';
    if ($paymentUrl !== '') {
        // ✅ Use JS redirect since HTML was already flushed to browser
        echo '<script>window.location.replace(' . json_encode($paymentUrl) . ');</script>';
        exit;
    } else {
        echo '<script>document.body.innerHTML=\'<div style="text-align:center;padding:40px"><h2 style="color:#e53935">&#10060; Gateway Error</h2><p>No payment URL provided.</p><a href="deposit_history.php" style="color:#64b5f6">&#8592; Go Back</a></div>\';</script>';
        exit;
    }
} else {
    $msg = htmlspecialchars($result['message'] ?? 'Gateway rejected the request.');
    echo '<script>document.body.innerHTML=\'<div style="text-align:center;padding:40px"><h2 style="color:#e53935">&#10060; Payment Error</h2><p>' . addslashes($msg) . '</p><a href="deposit_history.php" style="color:#64b5f6">&#8592; Go Back</a></div>\';</script>';
    exit;
}

