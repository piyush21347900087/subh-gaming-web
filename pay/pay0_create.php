<?php
/**
 * Pay0 Order Creator - BABA JI
 * Initiates payment using Pay0 API SDK Logic
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
$db = db();

$order_id = trim((string)($_GET['order_id'] ?? ''));
$amount = (float)($_GET['amount'] ?? 0);
$uid_raw = trim((string)($_GET['uid'] ?? ''));

// Fetch Pay0 Token from Admin Settings
$pay0Token = '';
$res = $db->query("SELECT pay0_token FROM admin_payment_details WHERE id = 1 LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $pay0Token = trim((string)($row['pay0_token'] ?? ''));
}

if ($pay0Token === '') {
    die("Error: Pay0 Gateway not configured in Admin Panel. Please enter 'Api Key' in Pay0 Token field.");
}

// Fetch User Mobile and Internal Integer ID
$userMobile = '0000000000';
$uid = 0;

// If UID comes as something like "BB01" or "LL02", extract just the numbers
if (!is_numeric($uid_raw)) {
    preg_match('/\d+/', $uid_raw, $matches);
    if (!empty($matches[0])) {
        $uid_raw = (string)(int)$matches[0]; // "01" becomes "1"
    }
}

if (is_numeric($uid_raw) && (int)$uid_raw > 0) {
    $uid = (int)$uid_raw;
    $stmt = $db->prepare("SELECT mobile_no FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->bind_result($userMobile);
    $stmt->fetch();
    $stmt->close();
} else {
    // Fallback if still totally messed up, try looking up the raw string anyway
    $stmt = $db->prepare("SELECT id, mobile_no FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("s", $uid_raw);
    $stmt->execute();
    $stmt->bind_result($uid, $userMobile);
    $stmt->fetch();
    $stmt->close();
}

if ($order_id === '' || $amount <= 0 || $uid <= 0) {
    echo "Invalid Request: Missing parameters (OrderID: $order_id, Amount: $amount, UID: $uid_raw)";
    exit;
}

// Prepare Pay0 API Call
$api_url = 'https://pay0.shop/api/create-order';
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'] ?? 'baba111.online';

$data = [
    'customer_mobile' => $userMobile,
    'user_token'      => $pay0Token,
    'amount'           => (string)$amount,
    'order_id'         => $order_id,
    'redirect_url'     => "$proto://$host/deposit_history.php?upi=SUCCESS&order_id=$order_id",
    'remark1'          => 'Wallet Deposit',
    'remark2'          => 'User ID: ' . $uid
];

// Initialize cURL session
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

$response = curl_exec($ch);
$err = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    file_put_contents(__DIR__ . '/pay0.log', date('Y-m-d H:i:s') . " | cURL Err: $err\n", FILE_APPEND);
    die("Connection Error: Unable to reach Pay0. Check your server internet.");
}

$result = json_decode((string)$response, true);
$log_msg = date('Y-m-d H:i:s') . " | Req: " . json_encode($data) . " | Res: " . $response . "\n";
file_put_contents(__DIR__ . '/pay0.log', $log_msg, FILE_APPEND);

if ($result && isset($result['status']) && $result['status'] === true) {
    $paymentUrl = $result['result']['payment_url'] ?? '';
    if ($paymentUrl !== '') {
        header("Location: " . $paymentUrl);
        exit;
    } else {
        die("<h3>Error: Gateway didn't provide payment link.</h3><p>Raw response: $response</p>");
    }
} else {
    $msg = $result['message'] ?? 'Gateway rejected the request.';
    die("
    <div style='font-family:sans-serif; text-align:center; padding:40px;'>
        <h2 style='color:#c62828;'>Payment Gateway Error</h2>
        <p style='font-size:18px;'><b>Error Message:</b> $msg</p>
        <p>Please contact support or go back and try again.</p>
        <p style='color:#999;font-size:12px;'>Raw server response: $response</p>
    </div>
    ");
}
