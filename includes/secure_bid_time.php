<?php
date_default_timezone_set('Asia/Kolkata');

function SUBHGAMING_internet_utc_timestamp(): ?int
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $urls = [
        'https://www.google.com/generate_204',
        'https://www.cloudflare.com/cdn-cgi/trace',
        'https://www.microsoft.com/',
    ];

    foreach ($urls as $url) {
        $headers = false;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true,
                CURLOPT_HEADER => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
            if (is_string($raw)) {
                $headers = preg_split('/\r\n|\n|\r/', $raw);
            }
        }

        if ($headers === false && ini_get('allow_url_fopen')) {
            $ctx = stream_context_create([
                'http' => ['method' => 'HEAD', 'timeout' => 5],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $headers = @get_headers($url, false, $ctx);
        }

        if (!is_array($headers)) {
            continue;
        }

        foreach ($headers as $line) {
            if (stripos((string) $line, 'Date:') === 0) {
                $ts = strtotime(trim(substr((string) $line, 5)));
                if ($ts !== false) {
                    $cached = $ts;
                    return $cached;
                }
            }
        }
    }

    return null;
}

function SUBHGAMING_bid_now_ist(): ?DateTimeImmutable
{
    $ts = SUBHGAMING_internet_utc_timestamp();
    if ($ts === null) {
        return null;
    }
    return (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('Asia/Kolkata'));
}

function SUBHGAMING_cut_time_or_null(?string $time): ?string
{
    if (!$time) {
        return null;
    }
    $time = trim($time);
    if ($time === '' || $time === '00:00:00' || $time === '00:00') {
        return null;
    }
    return substr($time, 0, 5);
}

function SUBHGAMING_market_state(mysqli $db, string $market, DateTimeImmutable $now): array
{
    $row = null;
    $market = trim($market);

    if ($market !== '') {
        $stmt = $db->prepare("SELECT market_name, open_time, close_time, today_closed, saturday_closed, sunday_closed, custom_close_text FROM markets WHERE UPPER(market_name)=UPPER(?) AND is_published=1 LIMIT 1");
        $stmt->bind_param('s', $market);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }

    if (!$row) {
        return [
            'ok' => false,
            'message' => 'Invalid market',
            'market' => strtoupper($market ?: 'MARKET'),
        ];
    }

    $openCut = SUBHGAMING_cut_time_or_null($row['open_time'] ?? null);
    $closeCut = SUBHGAMING_cut_time_or_null($row['close_time'] ?? null);
    if (!$openCut && !$closeCut) {
        $openCut = '00:00';
        $closeCut = '23:59';
    } elseif (!$openCut) {
        $openCut = '00:00';
    } elseif (!$closeCut) {
        $closeCut = '23:59';
    }

    $timeNow = $now->format('H:i');
    $dayName = $now->format('D');
    $isClosedToday = false;
    $reason = '';

    if (!empty($row['today_closed'])) {
        $isClosedToday = true;
        $reason = $row['custom_close_text'] ?: 'Today Market Closed';
    } elseif ($dayName === 'Sat' && !empty($row['saturday_closed'])) {
        $isClosedToday = true;
        $reason = $row['custom_close_text'] ?: 'Saturday Market Closed';
    } elseif ($dayName === 'Sun' && !empty($row['sunday_closed'])) {
        $isClosedToday = true;
        $reason = $row['custom_close_text'] ?: 'Sunday Market Closed';
    }

    $openDisabled = $isClosedToday || ($timeNow >= $openCut);
    $closeDisabled = $isClosedToday || ($timeNow >= $closeCut);
    $pageDisabled = $isClosedToday || ($timeNow >= $closeCut);

    return [
        'ok' => true,
        'message' => $reason,
        'market' => strtoupper($row['market_name']),
        'now_sql' => $now->format('Y-m-d H:i:s'),
        'open_disabled' => $openDisabled,
        'close_disabled' => $closeDisabled,
        'page_disabled' => $pageDisabled,
        'jodi_disabled' => $isClosedToday || ($timeNow >= $openCut),
    ];
}

function SUBHGAMING_secure_bid_context(mysqli $db, string $market): array
{
    $now = SUBHGAMING_bid_now_ist();
    if (!$now) {
        return ['ok' => false, 'message' => 'Internet time unavailable. Please try again.'];
    }

    $state = SUBHGAMING_market_state($db, $market, $now);
    if (empty($state['ok'])) {
        return $state;
    }
    if (!empty($state['page_disabled'])) {
        return ['ok' => false, 'message' => $state['message'] ?: 'Bidding closed'] + $state;
    }

    return ['ok' => true] + $state;
}

function SUBHGAMING_secure_session(array $ctx, string $requested, bool $jodiOnly = false): array
{
    if (empty($ctx['ok'])) {
        return ['ok' => false, 'message' => $ctx['message'] ?? 'Bidding closed'];
    }

    $requested = trim($requested);
    if ($jodiOnly) {
        if (!empty($ctx['jodi_disabled'])) {
            return ['ok' => false, 'message' => 'Open session closed'];
        }
        return ['ok' => true, 'session' => 'Open'];
    }

    if ($requested !== 'Open' && $requested !== 'Close') {
        return ['ok' => false, 'message' => 'Invalid session'];
    }
    if ($requested === 'Open' && !empty($ctx['open_disabled'])) {
        return ['ok' => false, 'message' => 'Open session closed'];
    }
    if ($requested === 'Close' && !empty($ctx['close_disabled'])) {
        return ['ok' => false, 'message' => 'Close session closed'];
    }

    return ['ok' => true, 'session' => $requested];
}

if (!function_exists('vardhan_secure_bid_context')) {
    function vardhan_secure_bid_context(mysqli $db, string $market): array
    {
        return SUBHGAMING_secure_bid_context($db, $market);
    }
}

if (!function_exists('vardhan_secure_session')) {
    function vardhan_secure_session(array $ctx, string $requested, bool $jodiOnly = false): array
    {
        return SUBHGAMING_secure_session($ctx, $requested, $jodiOnly);
    }
}

if (!function_exists('starline4_secure_bid_context')) {
    function starline4_secure_bid_context(mysqli $db, string $market): array
    {
        if (function_exists('starline4_market_state')) {
            $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
            return starline4_market_state($db, $market, $now);
        }
        return SUBHGAMING_secure_bid_context($db, $market);
    }
}


