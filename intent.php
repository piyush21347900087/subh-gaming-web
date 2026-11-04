<?php
// ---------- CONFIGURATION ----------
$merchant_id = "SUBH GAMING"; // Replace with your real Merchant ID
$api_key = "fc0f6c626eb72b0dLL52de4b50a58e2e"; // Your IMB API key
$api_url = "https://pay.imb.org.in";
$webhook_url = "https://subhgaming.online/intent.php"; // Must match your domain

// ---------- INITIATE PAYMENT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
  $amount = $_POST['amount'];
  $txn_id = "TXN" . time();

  $payload = [
    "merchantId" => $merchant_id,
    "merchantTransactionId" => $txn_id,
    "merchantUserId" => "user" . time(),
    "amount" => $amount * 100, // ₹ to paise
    "redirectUrl" => $webhook_url,
    "redirectMode" => "POST",
    "callbackUrl" => $webhook_url,
    "mobileNumber" => "9999999999",
    "paymentInstrument" => [
      "type" => "UPI_INTENT",
      "vpa" => "9028916466@pthdfc"
    ]
  ];

  $payload_str = json_encode($payload);
  $base64 = base64_encode($payload_str);
  $x_verify = hash('sha256', $base64 . "/pg/v1/pay" . $api_key) . "###1";

  $headers = [
    "Content-Type: application/json",
    "X-VERIFY: $x_verify",
    "X-MERCHANT-ID: $merchant_id"
  ];

  $post_data = json_encode(["request" => $base64]);

  $ch = curl_init("$api_url/apis/pg/v1/pay");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post_data,
    CURLOPT_HTTPHEADER => $headers
  ]);

  $response = curl_exec($ch);

  if ($response === false) {
    echo '<h3 style="color:red;">❌ cURL Error: ' . curl_error($ch) . '</h3>';
    curl_close($ch);
    exit;
  }

  curl_close($ch);

  // Try decoding JSON response
  $res = json_decode($response, true);
  if (json_last_error() !== JSON_ERROR_NONE || empty($res)) {
    echo '<h3 style="color:red;">❌ Invalid response from IMB server</h3>';
    echo '<p>Error: ' . htmlspecialchars(json_last_error_msg()) . '</p>';
    echo '<pre>Raw Response: ' . htmlspecialchars($response) . '</pre>';
    exit;
  }

  if (isset($res['data']['instrumentResponse']['intentUrl'])) {
    $intent_url = $res['data']['instrumentResponse']['intentUrl'];
    header("Location: $intent_url");
    exit;
  } else {
    echo '<h3 style="color:red;">❌ Payment initiation failed</h3>';
    echo '<pre>' . htmlspecialchars(json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
    exit;
  }
}

// ---------- HANDLE CALLBACK (Webhook) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transactionId'])) {
  $txn_id = $_POST['transactionId'];
  $status_url = "$api_url/apis/pg/v1/status/$merchant_id/$txn_id";
  $x_verify = hash('sha256', "/pg/v1/status/$merchant_id/$txn_id" . $api_key) . "###1";

  $ch = curl_init($status_url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      "Content-Type: application/json",
      "X-VERIFY: $x_verify",
      "X-MERCHANT-ID: $merchant_id"
    ]
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  $res = json_decode($response, true);
  if (json_last_error() !== JSON_ERROR_NONE || empty($res)) {
    echo '<h3 style="color:red;">❌ Invalid status response from IMB</h3>';
    echo '<p>Error: ' . htmlspecialchars(json_last_error_msg()) . '</p>';
    echo '<pre>' . htmlspecialchars($response) . '</pre>';
    exit;
  }

  if ($res['code'] === 'PAYMENT_SUCCESS') {
    echo "<h2 style='color:green;'>✅ Payment Successful</h2>";
  } else {
    echo "<h2 style='color:red;'>❌ Payment Failed or Pending</h2>";
  }

  echo "<pre>" . htmlspecialchars(json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "</pre>";
  exit;
}
?>

<!-- ---------- HTML FORM UI ---------- -->
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
  <meta charset="UTF-8">
  <title>Pay via IMB UPI</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background: #fffbea;
      font-family: sans-serif;
      text-align: center;
      padding: 40px 20px;
    }

    input,
    button {
      padding: 12px;
      font-size: 16px;
      margin-top: 10px;
      border-radius: 6px;
      border: 1px solid #ccc;
      width: 80%;
      max-width: 300px;
    }

    button {
      background: #d32f2f;
      color: white;
      cursor: pointer;
      border: none;
    }

    button:hover {
      background: #a91e1e;
    }
  </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>

<body>

  <h2>IMB UPI Intent Payment</h2>

  <form method="POST">
    <input type="number" name="amount" placeholder="Enter Amount in ₹" required min="1"><br>
    <button type="submit">Pay Now</button>
  </form>

</body>

</html>






