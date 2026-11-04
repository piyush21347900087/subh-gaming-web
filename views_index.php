<?php
require_once 'includes/db.php';
// includes/views_index.php
// ✅ No UI output (safe include)
// ✅ Tracks: country, region, city, device, os, platform, browser, user_agent
// ✅ repeat_user = same IP + same user_agent (more accurate "same device") visit count (1,2,3...)
// ✅ Counts once per session per page

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------------------------
   CONFIG
--------------------------- */
$pageName = 'index';
$sessionKey = 'pv_done_' . $pageName;

/* ---------------------------
   Helpers
--------------------------- */
function pv_get_real_ip(): string
{
    $keys = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED_FOR',  // Proxy
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = (string) $_SERVER[$k];
            if ($k === 'HTTP_X_FORWARDED_FOR') {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP))
                return $ip;
        }
    }
    return '0.0.0.0';
}

function pv_parse_ua(string $ua): array
{
    $u = strtolower($ua);

    // Device type (simple)
    $device = 'Desktop';
    if (strpos($u, 'ipad') !== false || strpos($u, 'tablet') !== false) {
        $device = 'Tablet';
    } elseif (strpos($u, 'mobile') !== false || strpos($u, 'android') !== false || strpos($u, 'iphone') !== false) {
        $device = 'Mobile';
    }

    // Platform / OS
    $platform = 'Unknown';
    if (strpos($u, 'android') !== false)
        $platform = 'Android';
    elseif (strpos($u, 'iphone') !== false || strpos($u, 'ipad') !== false || strpos($u, 'ios') !== false)
        $platform = 'iOS';
    elseif (strpos($u, 'windows') !== false)
        $platform = 'Windows';
    elseif (strpos($u, 'macintosh') !== false || strpos($u, 'mac os') !== false)
        $platform = 'Mac';
    elseif (strpos($u, 'linux') !== false)
        $platform = 'Linux';

    $os = $platform; // keep same (stable)

    // Browser
    $browser = 'Unknown';
    if (strpos($u, 'edg/') !== false)
        $browser = 'Edge';
    elseif (strpos($u, 'opr/') !== false || strpos($u, 'opera') !== false)
        $browser = 'Opera';
    elseif (strpos($u, 'chrome/') !== false && strpos($u, 'safari/') !== false && strpos($u, 'edg/') === false)
        $browser = 'Chrome';
    elseif (strpos($u, 'safari/') !== false && strpos($u, 'chrome/') === false)
        $browser = 'Safari';
    elseif (strpos($u, 'firefox/') !== false)
        $browser = 'Firefox';

    return [$device, $os, $platform, $browser];
}

function pv_fetch_geo(string $ip, int $timeoutSec = 2): array
{
    // ipapi.co (fast)
    $url = "https://ipapi.co/" . urlencode($ip) . "/json/";

    // Prefer cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => $timeoutSec,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'babajiPageViews/1.0',
        ]);
        $out = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($out && $code >= 200 && $code < 300) {
            $json = json_decode($out, true);
            return is_array($json) ? $json : [];
        }
        return [];
    }

    // Fallback
    $ctx = stream_context_create([
        'http' => ['timeout' => $timeoutSec],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $out = @file_get_contents($url, false, $ctx);
    $json = @json_decode((string) $out, true);
    return is_array($json) ? $json : [];
}

function pv_db_connect(): ?mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);
    try {
        $db = db();
        return $db;
    } catch (Throwable $e) {
        error_log("views_index db connect error: " . $e->getMessage());
        return null;
    }
}

function pv_has_column(mysqli $db, string $table, string $col): bool
{
    try {
        $st = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        if (!$st)
            return false;
        $st->bind_param('s', $col);
        $st->execute();
        // get_result may not exist on some hosting, so use store_result
        $st->store_result();
        $ok = ($st->num_rows > 0);
        $st->free_result();
        $st->close();
        return $ok;
    } catch (Throwable $e) {
        return false;
    }
}

function pv_ensure_table_and_columns(mysqli $db): void
{
    // Create base table (your required columns)
    $db->query("
        CREATE TABLE IF NOT EXISTS `page_views` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `page_name` VARCHAR(80) DEFAULT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `country` VARCHAR(120) DEFAULT NULL,
            `region` VARCHAR(120) DEFAULT NULL,
            `city` VARCHAR(120) DEFAULT NULL,
            `device` VARCHAR(50) DEFAULT NULL,
            `os` VARCHAR(60) DEFAULT NULL,
            `platform` VARCHAR(60) DEFAULT NULL,
            `browser` VARCHAR(60) DEFAULT NULL,
            `repeat_user` INT NOT NULL DEFAULT 1,
            `user_agent` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_created_at` (`created_at`),
            KEY `idx_page` (`page_name`),
            KEY `idx_ip` (`ip_address`),
            KEY `idx_country` (`country`),
            KEY `idx_region` (`region`),
            KEY `idx_ip_ua` (`ip_address`,`user_agent`(40))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Add missing columns safely (old table support)
    $adds = [
        'page_name' => "ALTER TABLE `page_views` ADD COLUMN `page_name` VARCHAR(80) DEFAULT NULL AFTER `id`",
        'country' => "ALTER TABLE `page_views` ADD COLUMN `country` VARCHAR(120) DEFAULT NULL AFTER `ip_address`",
        'region' => "ALTER TABLE `page_views` ADD COLUMN `region` VARCHAR(120) DEFAULT NULL AFTER `country`",
        'city' => "ALTER TABLE `page_views` ADD COLUMN `city` VARCHAR(120) DEFAULT NULL AFTER `region`",
        'device' => "ALTER TABLE `page_views` ADD COLUMN `device` VARCHAR(50) DEFAULT NULL AFTER `city`",
        'os' => "ALTER TABLE `page_views` ADD COLUMN `os` VARCHAR(60) DEFAULT NULL AFTER `device`",
        'platform' => "ALTER TABLE `page_views` ADD COLUMN `platform` VARCHAR(60) DEFAULT NULL AFTER `os`",
        'browser' => "ALTER TABLE `page_views` ADD COLUMN `browser` VARCHAR(60) DEFAULT NULL AFTER `platform`",
        'repeat_user' => "ALTER TABLE `page_views` ADD COLUMN `repeat_user` INT NOT NULL DEFAULT 1 AFTER `browser`",
        'user_agent' => "ALTER TABLE `page_views` ADD COLUMN `user_agent` VARCHAR(255) DEFAULT NULL AFTER `repeat_user`",
        'created_at' => "ALTER TABLE `page_views` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `user_agent`",
    ];

    foreach ($adds as $col => $sql) {
        if (!pv_has_column($db, 'page_views', $col)) {
            try {
                $db->query($sql);
            } catch (Throwable $e) { /* ignore */
            }
        }
    }

    // Helpful indexes (ignore errors)
    try {
        $db->query("CREATE INDEX `idx_ip_ua` ON `page_views` (`ip_address`, `user_agent`(40))");
    } catch (Throwable $e) {
    }
    try {
        $db->query("CREATE INDEX `idx_page_time` ON `page_views` (`page_name`, `created_at`)");
    } catch (Throwable $e) {
    }
}

/* ---------------------------
   MAIN
--------------------------- */

// ✅ once per session
if (!empty($_SESSION[$sessionKey])) {
    return;
}

$db = pv_db_connect();
if (!$db)
    return;

// pv_ensure_table_and_columns($db); // Disabled for performance/stability

$ip = pv_get_real_ip();
$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

list($device, $os, $platform, $browser) = pv_parse_ua($ua);

// Geo
$geo = pv_fetch_geo($ip, 2);
$country = (string) ($geo['country_name'] ?? ($geo['country'] ?? 'Unknown'));
$region = (string) ($geo['region'] ?? ($geo['region_code'] ?? 'Unknown'));
$city = (string) ($geo['city'] ?? '');

// ✅ repeat_user (same IP + same UA) = COUNT + 1
$repeat = 1;

try {
    $db->begin_transaction();

    $st = $db->prepare("SELECT COUNT(*) FROM page_views WHERE ip_address=? AND user_agent=?");
    if (!$st) {
        throw new RuntimeException('page_views count prepare failed: ' . $db->error);
    }
    $st->bind_param('ss', $ip, $ua);
    $st->execute();
    $st->bind_result($cnt);
    $st->fetch();
    $st->close();

    $repeat = ((int) $cnt) + 1;
    if ($repeat < 1)
        $repeat = 1;

    $ins = $db->prepare("
        INSERT INTO page_views
        (page_name, ip_address, country, region, city, device, os, platform, browser, repeat_user, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$ins) {
        throw new RuntimeException('page_views insert prepare failed: ' . $db->error);
    }
    $ins->bind_param(
        "sssssssssis",
        $pageName,
        $ip,
        $country,
        $region,
        $city,
        $device,
        $os,
        $platform,
        $browser,
        $repeat,
        $ua
    );
    $ins->execute();
    $ins->close();

    $db->commit();
} catch (Throwable $e) {
    try {
        $db->rollback();
    } catch (Throwable $ee) {
    }
    error_log("views_index insert error: " . $e->getMessage());
}

// mark done for session
$_SESSION[$sessionKey] = 1;

// $db->close();
return;
