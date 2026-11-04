<?php
// security.php - India-only traffic enforcer

function check_india_traffic() {
    // 0. Allow payment gateways, callbacks & webhooks to bypass the regional check
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'deposit') !== false || strpos($uri, 'pay/') !== false || strpos($uri, 'webhook') !== false || strpos($uri, 'intent') !== false) {
        return;
    }

    // 1. Check if it's a local request - allow it
    $ip = $_SERVER['REMOTE_ADDR'];
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0) {
        return;
    }

    // 2. Cloudflare header is most efficient (highly recommended for production)
    if (isset($_SERVER["HTTP_CF_IPCOUNTRY"])) {
        $country = strtoupper($_SERVER["HTTP_CF_IPCOUNTRY"]);
        if ($country !== 'IN') {
             block_access($country);
        }
        return;
    }

    // 3. Fallback: Use a free GeoIP API
    // We use ip-api.com as it is fast and doesn't require an API key for low-volume requests
    $ctx = stream_context_create(['http' => ['timeout' => 3]]); // 3 second timeout to avoid hanging
    $api_url = "http://ip-api.com/json/" . urlencode($ip) . "?fields=status,countryCode";
    $response = @file_get_contents($api_url, false, $ctx);
    
    if ($response) {
        $details = json_decode($response);
        if (isset($details->status) && $details->status === 'success') {
            if (isset($details->countryCode) && strtoupper($details->countryCode) !== 'IN') {
                block_access($details->countryCode);
            }
        }
    }
}

function block_access($countryCode) {
    header('HTTP/1.1 403 Forbidden');
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
    <link rel='icon' type='image/png' href='images/fevicon.png'>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Access Forbidden</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .card { background: white; padding: 2.5rem; border-radius: 1rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); text-align: center; max-width: 450px; border: 1px solid #e2e8f0; }
            h1 { color: #dc2626; margin-bottom: 1rem; font-size: 1.5rem; }
            p { line-height: 1.6; color: #64748b; }
            .country { font-weight: bold; color: #0f172a; background: #f1f5f9; padding: 0.2rem 0.6rem; border-radius: 0.3rem; }
            .flag { font-size: 3rem; margin-bottom: 1rem; }
        </style>
    <link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>
    <body>
        <div class='card'>
            <div class='flag'>🚫🇮🇳</div>
            <h1>SUBH GAMING - Access Restricted</h1>
            <p>We're sorry, but this application is currently only accessible from <b>India</b> to ensure the best service quality for our primary users.</p>
            <p>Your current location is detected as: <span class='country'>$countryCode</span></p>
            <p style='margin-top: 2rem; font-size: 0.875rem;'>If you believe this is an error, please contact support.</p>
        </div>
    </body>
    </html>";
    exit;
}

// Execute the check
check_india_traffic();

