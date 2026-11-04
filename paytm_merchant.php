<?php
/**
 * paytm_merchant.php
 * Script to test QR generation for a specific Paytm Merchant UPI ID
 * Using a working QR service (qrserver.com)
 */

$upi_id = "paytmqrxtqd1v99u6@paytm";
$merchant_name = "SUBH GAMING Merchant";
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 100; // Default 100 for testing
$currency = "INR";

// Build UPI URL
// upi://pay?pa=address&pn=name&am=amount&cu=currency
$upi_payload = "upi://pay?pa=" . urlencode($upi_id) . "&pn=" . urlencode($merchant_name) . "&am=" . $amount . "&cu=" . $currency;

// QR Server API (Better alternative than deprecated Google Chart API)
// Added a timestamp as a cache-buster just in case
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($upi_payload) . "&t=" . time();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paytm Merchant QR Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #333;
        }
        .container {
            background: #fff;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .merchant-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #002e6e; /* Paytm Blue */
        }
        .upi-id {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }
        .qr-wrapper {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 15px;
            display: inline-block;
            margin-bottom: 20px;
            border: 1px solid #eee;
            min-height: 300px;
        }
        .qr-code {
            max-width: 100%;
            height: auto;
            display: block;
        }
        .amount-display {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 20px;
            color: #2e7d32; /* Green */
        }
        .paytm-logo {
            width: 80px;
            margin-bottom: 15px;
        }
        .footer-text {
            font-size: 12px;
            color: #999;
        }
        .input-group {
            margin-bottom: 20px;
        }
        input[type="number"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 100px;
            text-align: center;
            font-size: 16px;
        }
        button {
            padding: 10px 20px;
            background: #00baf2; /* Paytm Light Blue */
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-left: 10px;
        }
        button:hover {
            background: #00a3d6;
        }
    </style>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body>
    <div class="container">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/24/Paytm_Logo_%28standalone%29.svg/2560px-Paytm_Logo_%28standalone%29.svg.png" alt="Paytm" class="paytm-logo">
        
        <div class="merchant-name"><?php echo htmlspecialchars($merchant_name); ?></div>
        <div class="upi-id"><?php echo htmlspecialchars($upi_id); ?></div>
        
        <div class="input-group">
            <form action="" method="GET">
                <input type="number" name="amount" value="<?php echo $amount; ?>" min="1" step="1">
                <button type="submit">Update QR</button>
            </form>
        </div>

        <div class="amount-display">₹<?php echo number_format($amount, 2); ?></div>

        <div class="qr-wrapper">
            <img src="<?php echo $qr_url; ?>" alt="UPI QR Code" class="qr-code">
        </div>

        <div class="footer-text">
            Scan this QR using any UPI App to pay
        </div>
        <p style="font-size: 10px; color: #ccc; margin-top: 20px; word-break: break-all;">
            QR Source: <?php echo htmlspecialchars($qr_url); ?>
        </p>
    </div>
</body>
</html>




