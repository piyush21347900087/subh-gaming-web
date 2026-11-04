<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="images/fevicon.png">
    <meta charset="UTF-8">
    <title>SUBH GAMING Deposit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
<body style="font-family: Arial, sans-serif; padding:20px;">
    <h2>SUBH GAMING Wallet Deposit (UPI – All Apps Supported)</h2>

    <form id="upiForm" onsubmit="return startUpiPayment(event);">
        <label>Amount (₹):</label><br>
        <input type="number" id="amount" name="amount" min="1" required
               style="padding:6px 10px; margin:8px 0; width:160px;">
        <br><br>
        <button type="submit" style="padding:8px 18px; cursor:pointer;">
            Pay via UPI
        </button>
    </form>

    <p style="margin-top:20px; color:#555; max-width:320px;">
        Saare UPI apps (Paytm / GPay / PhonePe / BHIM) chooser me aa jayenge.
    </p>

<script>
function startUpiPayment(e) {
    e.preventDefault();

    var amountInput = document.getElementById('amount');
    var amount = parseFloat(amountInput.value || '0');

    if (isNaN(amount) || amount <= 0) {
        alert('Sahi amount daalein');
        return false;
    }

    // 🟢 WORKING UPI ID
    var upiId = "7021812900@ptaxis";

    // 🟢 NPCI Standard Universal UPI Intent
    // ✔ Paytm show hota hai
    // ✔ PhonePe show hota hai
    // ✔ Google Pay show hota hai
    // ✔ BHIM show hota hai
    // ✔ “security decline” कम होता है क्योंकि note हटाया गया है
    var upiUrl =
        "upi://pay" +
        "?pa=" + encodeURIComponent(upiId) +
        "&pn=" + encodeURIComponent("Amina") +
        "&am=" + encodeURIComponent(amount.toFixed(2)) +
        "&cu=INR" +
        "&mode=00" +        // important for Paytm
        "&orgid=000000" +   // required for Paytm chooser
        "&sign=XYZ";        // dummy signature to allow Paytm

    console.log("UPI URL:", upiUrl);

    window.location.href = upiUrl;
    return false;
}
</script>

</body>
</html>




