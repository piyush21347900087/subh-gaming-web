<?php
require_once 'includes/db.php';
header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

$orderId = trim((string)($_REQUEST['order_id'] ?? ''));

if ($orderId === '') {
    echo json_encode(['ok' => false, 'error' => 'Order ID missing', 'status' => 'UNKNOWN']);
    exit;
}

$db = db();
$hasOrder = false;

// Check if order_id column exists
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) c
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'deposit'
          AND COLUMN_NAME = 'order_id'
    ");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $hasOrder = (int) ($row['c'] ?? 0) > 0;
} catch (Throwable $e) {
}

try {
    if ($hasOrder) {
        $st = $db->prepare("SELECT status, amount, method FROM deposit WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $st->bind_param('s', $orderId);
    } else {
        // Fallback to transaction_id
        $st = $db->prepare("SELECT status, amount, method FROM deposit WHERE transaction_id = ? ORDER BY id DESC LIMIT 1");
        $st->bind_param('s', $orderId);
    }
    
    $st->execute();
    $res = $st->get_result();
    $row = $res->fetch_assoc();
    $st->close();

    if (!$row) {
        echo json_encode(['ok' => true, 'status' => 'NOT_FOUND', 'message' => 'No transaction found']);
        exit;
    }

    $status = strtolower(trim((string)$row['status']));
    $displayStatus = 'PENDING';

    if (in_array($status, ['done', 'success', 'completed', 'approved'], true)) {
        $displayStatus = 'SUCCESS';
    } elseif (in_array($status, ['failed', 'fail', 'cancelled', 'rejected'], true)) {
        $displayStatus = 'FAILED';
    }

    // --- INSTANT CLEARANCE FOR IMB ---
    // If it is still pending in our DB, proactively poll the IMB gateway
    $method = strtoupper(trim((string)($row['method'] ?? '')));
    if ($displayStatus === 'PENDING' && ($method === 'IMB' || strpos($method, 'IMB') !== false)) {
        
        require_once 'deposit.php'; // For update_deposit_and_credit
        
        // Fetch IMB Token
        $imbToken = '';
        $tRes = $db->query("SELECT imb_token FROM admin_payment_details WHERE id=1 LIMIT 1");
        if ($tRes && $tRow = $tRes->fetch_assoc()) {
            $imbToken = trim((string)($tRow['imb_token'] ?? ''));
        }
        
        if ($imbToken !== '') {
            $ch = curl_init('https://secure-stage.imb.org.in/api/check-order-status');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'user_token' => $imbToken,
                    'order_id' => $orderId,
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Token: ' . $imbToken,
                ],
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300 && $response) {
                $json = json_decode((string)$response, true);
                if (is_array($json)) {
                    $gwStatusRaw = strtoupper((string)($json['result']['status'] ?? $json['result']['txnStatus'] ?? $json['txnStatus'] ?? $json['payment_status'] ?? $json['status'] ?? 'PENDING'));
                    
                    $gwStatus = 'PENDING';
                    if (in_array($gwStatusRaw, ['SUCCESS', 'SUCCESSFUL', 'COMPLETED', 'DONE', 'TRUE', 'PAID'], true)) {
                        $gwStatus = 'SUCCESS';
                    } elseif (in_array($gwStatusRaw, ['FAILD', 'FAILED', 'FAIL', 'FAILURE', 'FALSE', 'ERROR', 'CANCELLED', 'CANCELED'], true)) {
                        $gwStatus = 'FAILED';
                    }
                    
                    if ($gwStatus === 'SUCCESS' || $gwStatus === 'FAILED') {
                        $upRes = update_deposit_and_credit($db, $orderId, $gwStatus, 'IMB');
                        if (!empty($upRes['ok'])) {
                            $displayStatus = $gwStatus;
                        }
                    }
                }
            }
        }
    }
    // ---------------------------------

    echo json_encode([
        'ok' => true,
        'status' => $displayStatus,
        'amount' => (float)$row['amount'],
        'order_id' => $orderId
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage(), 'status' => 'ERROR']);
}
$db->close();
