<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user']['id'])) {
    die("Error: Login required.");
}

$db = db();
$uid = (int)$_SESSION['user']['id'];
$amount = (float)($_POST['amount'] ?? 0);

if ($amount <= 0) {
    die("Error: Invalid Amount.");
}

$userRes = $db->query("SELECT user_id, mobile_no FROM users WHERE id = $uid LIMIT 1");
$userRow = $userRes ? $userRes->fetch_assoc() : null;
$customerCode = trim($userRow['user_id'] ?? '') ?: (string)$uid;
$customerMobile = trim($userRow['mobile_no'] ?? '9999999999');

$resPay = $db->query("SELECT imb_token FROM admin_payment_details WHERE id=1 LIMIT 1");
$apiKey = trim($resPay ? $resPay->fetch_assoc()['imb_token'] : '');

$orderId = "IMB" . date('YmdHis') . mt_rand(100, 999);

$chk = $db->query("SHOW COLUMNS FROM deposit");
$cols = []; while($c = $chk->fetch_assoc()) $cols[] = $c['Field'];

if (in_array('order_id', $cols)) {
    $sql = "INSERT INTO deposit (user_id, amount, method, status, order_id, date_time) VALUES (?, ?, 'IMB', 'Pending', ?, ?)";
    $st = $db->prepare($sql);
    $nowStr = date('Y-m-d H:i:s');
    $st->bind_param('sdss', $customerCode, $amount, $orderId, $nowStr);
} else {
    $sql = "INSERT INTO deposit (user_id, amount, method, status, date_time) VALUES (?, ?, 'IMB', 'Pending', ?)";
    $st = $db->prepare($sql);
    $nowStr = date('Y-m-d H:i:s');
    $st->bind_param('sds', $customerCode, $amount, $nowStr);
}
$st->execute(); $st->close();

$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$postFields = [
    'user_token'      => $apiKey,
    'amount'          => number_format($amount, 2, '.', ''),
    'order_id'        => $orderId,
    'customer_mobile' => $customerMobile,
    'redirect_url'    => "$baseUrl/pay/imb_return.php?order_id=$orderId",
    'callback_url'    => "$baseUrl/pay/webhookimb.php",
    'webhook_url'     => "$baseUrl/pay/webhookimb.php",
    'remark1'         => $customerCode,
    'remark2'         => 'Wallet Deposit'
];

// IMB's resolved IP: 164.52.217.230
// Try hostname first, then direct IP with SNI bypass
$attempts = [
    ['url' => 'https://secure-stage.imb.org.in/api/create-order', 'host' => ''],
    ['url' => 'https://164.52.217.230/api/create-order',          'host' => 'secure-stage.imb.org.in'],
    ['url' => 'https://imb.org.in/api/create-order',              'host' => ''],
    ['url' => 'https://164.52.217.230/api/create-order',          'host' => 'imb.org.in'],
];

$response = ''; $err = ''; $httpCode = 0; $finalUrl = '';
$debugLog = [];

foreach ($attempts as $attempt) {
    $finalUrl = $attempt['url'];
    $headers = ['Content-Type: application/x-www-form-urlencoded', 'Token: ' . $apiKey];
    if ($attempt['host']) $headers[] = 'Host: ' . $attempt['host'];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $attempt['url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postFields),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
    ]);
    $response = curl_exec($curl);
    $err      = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $debugLog[] = "URL: {$attempt['url']} | HTTP: $httpCode | Err: $err | Res: " . substr($response, 0, 150);
    if ($httpCode === 200 && !empty($response)) break;
}

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
file_put_contents($logDir . '/imb_api.log', "[" . date('Y-m-d H:i:s') . "] Order: $orderId\n" . implode("\n", $debugLog) . "\n\n", FILE_APPEND);

$res = json_decode($response, true);

// Check all possible success keys from IMB
$payUrl = $res['result']['payment_url'] ?? $res['data']['payment_url'] ?? $res['payment_url'] ?? '';
$isSuccess = !empty($payUrl) || (isset($res['status']) && in_array($res['status'], [true, 'true', 1, 'SUCCESS', 'success'], true));

if ($isSuccess && $payUrl) {
    header("Location: $payUrl");
    exit;
}

// Show detailed error for debugging
echo "<style>body{font-family:sans-serif;padding:20px;background:#f5f5f5;}pre{background:#fff;padding:15px;border-radius:8px;overflow:auto;font-size:12px;}</style>";
echo "<h3 style='color:#c62828'>Initialization Failed</h3>";
echo "<b>Token Used:</b> " . ($apiKey ? substr($apiKey,0,10).'...' : '<span style="color:red">EMPTY - Check Admin Panel!</span>') . "<br><br>";
echo "<b>Debug Log:</b><pre>" . implode("\n", $debugLog) . "</pre>";
echo "<b>Raw API Response:</b><pre>" . htmlspecialchars($response ?: 'No response') . "</pre>";
echo "<a href='../deposit.php' style='display:inline-block;margin-top:15px;background:#333;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;'>Go Back</a>";
