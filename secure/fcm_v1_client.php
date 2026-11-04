<?php
/**
 * Minimal FCM v1 client (PHP + cURL + OpenSSL)
 * Requires: service account json at /secure/service-account.json
 */
function fcm_v1_get_access_token($saPath)
{
    $sa = json_decode(file_get_contents($saPath), true);
    $iss = $sa['client_email'];
    $scope = 'https://www.googleapis.com/auth/firebase.messaging';
    $aud = $sa['token_uri']; // https://oauth2.googleapis.com/token
    $now = time();
    $payload = [
        'iss' => $iss,
        'scope' => $scope,
        'aud' => $aud,
        'iat' => $now,
        'exp' => $now + 3600,
    ];
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $b64 = fn($x) => rtrim(strtr(base64_encode(json_encode($x)), '+/', '-_'), '=');
    $jwtUnsigned = $b64($header) . '.' . $b64($payload);

    $privateKey = $sa['private_key'];
    openssl_sign($jwtUnsigned, $signature, $privateKey, 'sha256WithRSAEncryption');
    $jwt = $jwtUnsigned . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    // exchange JWT for access token
    $ch = curl_init($aud);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false)
        throw new Exception('OAuth token HTTP error: ' . curl_error($ch));
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200)
        throw new Exception('OAuth token bad status: ' . $code . ' ' . $resp);
    $data = json_decode($resp, true);
    return $data['access_token'];
}

function fcm_v1_send_to_topic($projectId, $saPath, $topic, $title, $body, $data = [])
{
    $token = fcm_v1_get_access_token($saPath);
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    $message = [
        'message' => [
            'topic' => $topic, // e.g. "LL00001"
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            // Android options (channel id etc.)
            'android' => [
                'notification' => [
                    'channel_id' => 'market_v2',
                    'sound' => 'default',
                    'icon' => 'ic_notification'
                ],
                'priority' => 'HIGH'
            ],
            'data' => $data, // key/value strings
        ]
    ];
    $notifColor = (string)($data['notification_color'] ?? $data['market_color'] ?? '');
    if ($notifColor !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $notifColor)) {
        $message['message']['android']['notification']['color'] = $notifColor;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($message),
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false)
        throw new Exception('FCM send HTTP error: ' . curl_error($ch));
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200)
        throw new Exception('FCM send bad status: ' . $code . ' ' . $resp);
    return true;
}
