<?php
/**
 * fcm_helper.php
 * Robust FCM v1 sending with Token Caching.
 * 
 * Usage:
 * $token = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
 * fcm_v1_send_token(FCM_PROJECT_ID, $token, $data, $fcmToken);
 */
/* ================== topic helper ================== */
if (!function_exists('sanitize_topic')) {
    function sanitize_topic($topic)
    {
        $t = trim($topic);
        if ($t === '')
            return '';
        $t = preg_replace('/[^a-zA-Z0-9\-\_\.\~\%]/', '_', $t);
        return substr($t, 0, 200);
    }
}
/* ================== OAuth2 Token (FCM v1) ================== */
if (!function_exists('google_access_token_from_sa_cached')) {
    function google_access_token_from_sa_cached($saPath)
    {
        static $memToken = '';
        static $memExp = 0;
        if ($memToken && $memExp > time() + 30)
            return $memToken;
        $cacheFile = __DIR__ . '/../secure/.fcm_access_token_cache.json';
        if (is_file($cacheFile)) {
            $j = json_decode(@file_get_contents($cacheFile), true);
            if ($j && !empty($j['access_token']) && !empty($j['expires_at']) && (int) $j['expires_at'] > time() + 30) {
                $memToken = (string) $j['access_token'];
                $memExp = (int) $j['expires_at'];
                return $memToken;
            }
        }
        if (!is_file($saPath))
            return '';
        $sa = json_decode(file_get_contents($saPath), true);
        if (!$sa || empty($sa['client_email']) || empty($sa['private_key']))
            return '';
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $now = time();
        $claims = [
            'iss' => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600
        ];
        $b64 = function ($x) {
            return rtrim(strtr(base64_encode(json_encode($x)), '+/', '-_'), '=');
        };
        $unsigned = $b64($header) . '.' . $b64($claims);
        openssl_sign($unsigned, $sig, $sa['private_key'], 'sha256WithRSAEncryption');
        $jwt = $unsigned . '.' . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSLVERSION => 6,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $res = json_decode((string) $raw, true);
        $tok = (string) ($res['access_token'] ?? '');
        if (!$tok)
            return '';
        $expiresIn = (int) ($res['expires_in'] ?? 3600);
        $expAt = time() + max(300, $expiresIn - 60);
        $memToken = $tok;
        $memExp = $expAt;
        @file_put_contents($cacheFile, json_encode([
            'access_token' => $tok,
            'expires_at' => $expAt,
            'saved_at' => time()
        ]));
        return $tok;
    }
}
/* ================== uid helper ================== */
if (!function_exists('normalize_uid')) {
    function normalize_uid($uid)
    {
        $uid = strtoupper(trim((string) $uid));
        if ($uid === '')
            return '';
        if (preg_match('/^\d+$/', $uid)) {
            return "KR" . str_pad($uid, 2, "0", STR_PAD_LEFT);
        }
        if (preg_match('/^(?:BB|LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|DS|DM|KR)0*(\d+)$/', $uid, $m)) {
            return "KR" . str_pad($m[1], 2, "0", STR_PAD_LEFT);
        }
        return $uid;
    }
}
if (!function_exists('legacy_uid_ll')) {
    function legacy_uid_ll($uid)
    {
        $u = normalize_uid($uid);
        if ($u === '')
            return '';
        if (preg_match('/^\d+$/', $u)) {
            return "KR" . str_pad($u, 2, "0", STR_PAD_LEFT);
        }
        if (preg_match('/^(?:LL|RK|DP|DB|DPB|KL|KLY|KB|KBT|DS|DM|KR)0*(\d+)$/', $u, $m)) {
            return "KR" . str_pad($m[1], 2, "0", STR_PAD_LEFT);
        }
        return $u;
    }
}
/* ================== push logic ================== */
if (!function_exists('pushUserNotification')) {
    function pushUserNotification(mysqli $db, string $userId, string $title, string $message, string $type = 'wallet'): bool
    {
        $payload = json_encode(['type' => $type], JSON_UNESCAPED_UNICODE);
        $userId = normalize_uid($userId);
        try {
            $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, payload, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            if ($stmt) {
                $stmt->bind_param('sssss', $userId, $title, $message, $type, $payload);
                $ok = $stmt->execute();
                $stmt->close();
                if ($ok) return true;
            }
        } catch (Exception $e) {}
        try {
            $stmt = $db->prepare("INSERT INTO notify (user_id, title, message, type, payload, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            if ($stmt) {
                $stmt->bind_param('sssss', $userId, $title, $message, $type, $payload);
                $ok = $stmt->execute();
                $stmt->close();
                if ($ok) return true;
            }
        } catch (Exception $e) {}
        try {
            $stmt = $db->prepare("INSERT INTO user_notifications (user_id, title, body, type, payload, seen, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            if ($stmt) {
                $stmt->bind_param('sssss', $userId, $title, $message, $type, $payload);
                $ok = $stmt->execute();
                $stmt->close();
                if ($ok) return true;
            }
        } catch (Exception $e) {}
        return false;
    }
}
if (!function_exists('get_latest_fcm_token')) {
    function get_latest_fcm_token(mysqli $db, string $userId): string
    {
        try {
            $exact = normalize_uid($userId);
            if ($exact === '') return '';
            $stmt = $db->prepare("SELECT fcm_token FROM user_fcm_tokens WHERE user_id = ? AND fcm_token<>'' ORDER BY updated_at DESC LIMIT 1");
            $stmt->bind_param('s', $exact);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            $tok = $row['fcm_token'] ?? '';
            if (is_string($tok) && trim($tok) !== '') return $tok;
            $legacy = legacy_uid_ll($exact);
            if ($legacy !== '' && $legacy !== $exact) {
                $stmt = $db->prepare("SELECT fcm_token FROM user_fcm_tokens WHERE user_id = ? AND fcm_token<>'' ORDER BY updated_at DESC LIMIT 1");
                $stmt->bind_param('s', $legacy);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                $tok = $row['fcm_token'] ?? '';
                if (is_string($tok) && trim($tok) !== '') return $tok;
            }
            return '';
        } catch (Throwable $e) { return ''; }
    }
}
if (!function_exists('pushWalletFcm')) {
    function pushWalletFcm(mysqli $db, string $userId, string $type, float $amount, string $status, string $title, string $message): void
    {
        try {
            $accessToken = google_access_token_from_sa_cached(GOOGLE_SA_JSON);
            if ($accessToken) {
                $payload = [
                    'type'    => $type,
                    'status'  => $status,
                    'title'   => 'SUBH GAMING | ' . $title,
                    'message' => $message,
                    'amount'  => $amount,
                    'user_id' => $userId,
                    'time'    => date('Y-m-d H:i:s'),
                    'icon'    => 'ic_notification',
                    'uniq'    => strtoupper($type) . ':' . normalize_uid($userId) . ':' . time()
                ];
                $token = get_latest_fcm_token($db, $userId);
                $userIdTopic = normalize_uid($userId);
                $success = false;
                if ($token !== '') {
                    $r = fcm_v1_send_token(FCM_PROJECT_ID, $accessToken, $payload, $token);
                    if ((int)($r['http'] ?? 0) === 200) $success = true;
                }
                if (!$success && $userIdTopic !== '') {
                    fcm_v1_send_topic(FCM_PROJECT_ID, $accessToken, $payload, $userIdTopic);
                }
            }
        } catch (Throwable $e) {}
    }
}
/* ================== FCM SEND HELPERS ================== */
if (!function_exists('fcm_v1_send_topic')) {
    function fcm_v1_send_topic($pid, $token, $data, $topic)
    {
        $logFile = __DIR__ . '/../api_fcm.log';
        $time    = date('Y-m-d H:i:s');
        $topic   = sanitize_topic($topic);
        @file_put_contents($logFile, "[{$time}] SEND_TOPIC_START: topic={$topic}, payload=" . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        if ($topic === '') return ['http' => 0, 'err' => 'empty-topic', 'body' => ''];
        $url = "https://fcm.googleapis.com/v1/projects/{$pid}/messages:send";
        $notif      = $data['notification'] ?? null;
        $isDataOnly = ($notif === 'none');
        if ($isDataOnly) {
            $notif = null;
        } elseif ($notif === null || empty($notif)) {
            $notif = [
                'title' => (string)($data['title'] ?? 'Notification'),
                'body'  => (string)($data['message'] ?? ($data['body'] ?? 'New update')),
            ];
            $img = $data['image'] ?? $data['image_url'] ?? $data['picture'] ?? null;
            if (!empty($img) && is_scalar($img)) {
                $notif['image'] = (string)$img;
            }
        }
        $cleanData = [];
        foreach ($data as $k => $v) {
            if ($k === 'notification') continue;
            if (is_scalar($v) || is_null($v)) {
                $cleanData[(string)$k] = (string)$v;
            }
        }
        $type = strtolower((string)($cleanData['type'] ?? ''));
        $chan = 'market_v11';
        if (in_array($type, ['chat', 'live_chat'])) {
            $chan = 'chat_v11';
        } elseif (in_array($type, ['admin_chat'])) {
            $chan = 'admin_chat_sound_v1';
        } elseif (in_array($type, ['win', 'wining', 'win_alert', 'deposit', 'deposit_approved', 'withdrawal', 'withdrawal_approved'])) {
            $chan = 'win_v11';
        } elseif (in_array($type, ['admin_deposit', 'admin_withdrawal'])) {
            $chan = 'admin_req_sound_v1';
        } elseif (in_array($type, ['notice', 'admin_notice'])) {
            $chan = 'admin_v11';
        }
        $buildPayload = function (string $targetTopic) use ($cleanData, $notif, $chan) {
            $payload = [
                'message' => [
                    'topic'   => $targetTopic,
                    'data'    => $cleanData,
                    'android' => ['priority' => 'high']
                ]
            ];
            if ($notif) {
                $payload['message']['notification'] = [
                    'title' => (string)($notif['title'] ?? 'SUBH GAMING'),
                    'body'  => (string)($notif['body'] ?? 'New Update')
                ];
                if (!empty($notif['image'])) {
                    $payload['message']['notification']['image'] = (string)$notif['image'];
                }
                $payload['message']['android']['notification'] = [
                    'channel_id' => $chan,
                    'sound'      => 'default',
                    'icon'       => 'ic_notification'
                ];
                $notifColor = (string)($cleanData['notification_color'] ?? $cleanData['market_color'] ?? '');
                if ($notifColor !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $notifColor)) {
                    $payload['message']['android']['notification']['color'] = $notifColor;
                }
                if (!empty($notif['image'])) {
                    $payload['message']['android']['notification']['image'] = (string)$notif['image'];
                }
            }
            return $payload;
        };
        $sendToTopic = function (string $targetTopic) use ($url, $token, $buildPayload) {
            $payload = $buildPayload($targetTopic);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json; charset=utf-8'
                ],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSLVERSION     => 6,
                CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ['http' => $code, 'body' => $body];
        };
        $primary = $sendToTopic($topic);
        $body = $primary['body'];
        $code = $primary['http'];
        if ($topic === 'all_SUBHGAMING') {
            $legacy = $sendToTopic('all_KALYANRATAN');
            @file_put_contents($logFile, "[{$time}] Legacy Topic: all_KALYANRATAN | HTTP: {$legacy['http']} | Body: {$legacy['body']}\n", FILE_APPEND);
        }
        @file_put_contents($logFile, "[{$time}] Topic: {$topic} | HTTP: {$code} | Body: {$body}\n", FILE_APPEND);
        return ['http' => $code, 'err' => '', 'body' => $body];
    }
}
if (!function_exists('fcm_v1_send_token')) {
    function fcm_v1_send_token($pid, $token, $data, $target)
    {
        $logFile = __DIR__ . '/../api_fcm.log';
        $time    = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[{$time}] SEND_TOKEN_START: target=" . substr($target, 0, 15) . "..., type=" . ($data['type'] ?? 'unknown') . "\n", FILE_APPEND);
        if (trim($target) === '') return ['http' => 0, 'err' => 'empty-token', 'body' => ''];
        $url = "https://fcm.googleapis.com/v1/projects/{$pid}/messages:send";
        $notif      = $data['notification'] ?? null;
        $isDataOnly = ($notif === 'none');
        if ($isDataOnly) {
            $notif = null;
        } elseif ($notif === null || empty($notif)) {
            $notif = [
                'title' => (string)($data['title'] ?? 'Notification'),
                'body'  => (string)($data['message'] ?? ($data['body'] ?? 'New update')),
            ];
            $img = $data['image'] ?? $data['image_url'] ?? $data['picture'] ?? null;
            if (!empty($img) && is_scalar($img)) {
                $notif['image'] = (string)$img;
            }
        }
        $cleanData = [];
        foreach ($data as $k => $v) {
            if ($k === 'notification') continue;
            if (is_scalar($v) || is_null($v)) {
                $cleanData[(string)$k] = (string)$v;
            }
        }
        $type = strtolower((string)($cleanData['type'] ?? ''));
        $chan = 'market_v11';
        if (in_array($type, ['chat', 'live_chat'])) {
            $chan = 'chat_v11';
        } elseif (in_array($type, ['admin_chat'])) {
            $chan = 'admin_chat_sound_v1';
        } elseif (in_array($type, ['win', 'wining', 'win_alert', 'deposit', 'deposit_approved', 'withdrawal', 'withdrawal_approved'])) {
            $chan = 'win_v11';
        } elseif (in_array($type, ['admin_deposit', 'admin_withdrawal'])) {
            $chan = 'admin_req_sound_v1';
        } elseif (in_array($type, ['notice', 'admin_notice'])) {
            $chan = 'admin_v11';
        }
        $payload = [
            'message' => [
                'token'   => $target,
                'data'    => $cleanData,
                'android' => ['priority' => 'high']
            ]
        ];
        if ($notif) {
            $payload['message']['notification'] = [
                'title' => (string)($notif['title'] ?? 'SUBH GAMING'),
                'body'  => (string)($notif['body'] ?? 'New Update')
            ];
            if (!empty($notif['image'])) {
                $payload['message']['notification']['image'] = (string)$notif['image'];
            }
            $payload['message']['android']['notification'] = [
                'channel_id' => $chan,
                'sound'      => 'default',
                'icon'       => 'ic_notification'
            ];
            $notifColor = (string)($cleanData['notification_color'] ?? $cleanData['market_color'] ?? '');
            if ($notifColor !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $notifColor)) {
                $payload['message']['android']['notification']['color'] = $notifColor;
            }
            if (!empty($notif['image'])) {
                $payload['message']['android']['notification']['image'] = (string)$notif['image'];
            }
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json; charset=utf-8'
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSLVERSION     => 6, 
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        @file_put_contents($logFile, "[{$time}] Target: " . substr($target, 0, 20) . "... | HTTP: {$code} | Body: {$body}\n", FILE_APPEND);
        return ['http' => $code, 'err' => '', 'body' => $body];
    }
}


