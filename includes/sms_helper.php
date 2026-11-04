<?php
/**
 * SMS Helper for AquaSMS API (login.aquasms.com)
 * Integrated for SUBH GAMING
 */

// Load environment config (secrets from .env file)
require_once __DIR__ . '/config.php';
// AQUASMS_USERNAME, AQUASMS_APIKEY, etc. are now loaded from .env via config.php

/**
 * Send Single / Multiple SMS via AquaSMS
 *
 * @param string $mobile Mobile number(s) (comma separated for multiple)
 * @param string $message SMS message body
 * @param string|null $peid Optional Entity ID for DLT
 * @param string|null $templateid Optional Template ID for DLT
 * @return string|array Raw API response or error string
 */
function send_sms($mobile, $message, $peid = null, $templateid = null) {
    // Clean mobile number (strip non-digits, keep last 10 digits per number)
    $numbers = array_map(function($num) {
        $clean = preg_replace('/[^0-9]/', '', trim($num));
        return (strlen($clean) > 10) ? substr($clean, -10) : $clean;
    }, explode(',', (string)$mobile));
    
    $clean_numbers = implode(',', array_filter($numbers));
    if (empty($clean_numbers)) {
        return json_encode([['responseCode' => 'Invalid mobile number']]);
    }

    $params = [
        'username'   => AQUASMS_USERNAME,
        'message'    => $message,
        'sendername' => AQUASMS_SENDERNAME,
        'smstype'    => AQUASMS_SMSTYPE,
        'numbers'    => $clean_numbers,
        'apikey'     => AQUASMS_APIKEY,
    ];

    // Always use v2 endpoint (as per AquaSMS API URL)
    $endpoint = AQUASMS_BASE_URL . '/v2/sendSMS';

    // Use constants as defaults, allow override via function params
    $params['peid']       = !empty($peid)       ? $peid       : AQUASMS_PEID;
    $params['templateid'] = !empty($templateid) ? $templateid : AQUASMS_TEMPLATEID;

    $url = $endpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'KISMAT555-AquaSMS-Client/1.0');
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || empty($response)) {
        $context = stream_context_create([
            'http' => ['timeout' => 15, 'ignore_errors' => true],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $response = @file_get_contents($url, false, $context);
    }

    $final_res = $response ?: json_encode([['responseCode' => $error ?: 'Connection failed']]);

    // Optional: write to sms debug log
    try {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
        $logLine = '[' . date('Y-m-d H:i:s') . '] TO: ' . $clean_numbers . ' | URL: ' . $url . ' | RES: ' . $final_res . "\n";
        @file_put_contents($logDir . '/sms.log', $logLine, FILE_APPEND);
    } catch (Throwable $e) {}

    return $final_res;
}

/**
 * Generate numeric OTP of specified length (default 4 digits)
 */
function generate_otp($length = 4) {
    if ($length <= 1) $length = 4;
    $min = (int) pow(10, $length - 1);
    $max = (int) pow(10, $length) - 1;
    try {
        return (string) random_int($min, $max);
    } catch (Throwable $e) {
        return (string) rand($min, $max);
    }
}

/**
 * Format OTP message according to registered DLT content:
 * "Your OTP code for verification is : {#var#} CRTFUL"
 */
function format_otp_message($otp) {
    return "Your OTP code for verification is : " . $otp . " CRTFUL";
}

/**
 * Check SMS Credit balance from AquaSMS
 */
function get_sms_credit() {
    $url = AQUASMS_BASE_URL . '/getSMSCredit?username=' . urlencode(AQUASMS_USERNAME) . '&apikey=' . urlencode(AQUASMS_APIKEY);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

/**
 * Get Delivery Report for a message ID
 */
function get_sms_dlr($msgid) {
    $url = AQUASMS_BASE_URL . '/getDLR?username=' . urlencode(AQUASMS_USERNAME) . '&msgid=' . urlencode($msgid) . '&apikey=' . urlencode(AQUASMS_APIKEY);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}
