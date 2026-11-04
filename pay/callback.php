<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../deposit.php';

header('Content-Type: application/json; charset=utf-8');

$orderId = trim((string)($_REQUEST['order_id'] ?? ''));

if ($orderId === '') {
    echo json_encode(['ok' => false, 'error' => 'Order ID missing']);
    exit;
}

// Pay0 API Configuration
$url = "https://pay0.shop/api/check-order-status";
$postData = array(
    "user_token" => "2048f66bef68633fa3262d7a398ab577", // Ideally move to environment/admin settings
    "order_id" => $orderId
);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['ok' => false, 'error' => 'cURL Error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);
$responseData = json_decode($response, true);

if (!empty($responseData["status"]) && $responseData["status"] === "COMPLETED") {
    $txnStatus = strtoupper(trim((string)($responseData["result"]["txnStatus"] ?? '')));
    
    $db = db();
    $result = update_deposit_and_credit($db, $orderId, $txnStatus, 'UPI_INTENT');
    $db->close();
    
    echo json_encode([
        'ok' => true,
        'status' => $result['status'] ?? 'UNKNOWN',
        'gateway_status' => $txnStatus,
        'amount' => (float)($responseData["result"]["amount"] ?? 0),
        'utr' => $responseData["result"]["utr"] ?? ''
    ]);
} else {
    $errorMsg = $responseData["message"] ?? 'Gateway validation failed';
    echo json_encode(['ok' => false, 'error' => $errorMsg]);
}
