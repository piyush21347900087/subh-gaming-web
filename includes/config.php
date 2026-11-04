<?php
/**
 * config.php - Central Environment/Config Loader for MAHADEV KING
 *
 * Loads secrets from .env file (if present) OR from server environment variables.
 * Include this file ONCE at the top of your entry points.
 *
 * Usage:
 *   require_once __DIR__ . '/includes/config.php';
 *
 * Then use defined constants like DB_HOST, AQUASMS_APIKEY, etc.
 */

if (!function_exists('_mk_load_env')) {
    function _mk_load_env(): void
    {
        // Already loaded?
        if (defined('_MK_ENV_LOADED')) return;
        define('_MK_ENV_LOADED', true);

        // Try loading .env file from web root
        $envFile = dirname(__DIR__) . '/.env';
        $envVars = [];

        if (is_file($envFile) && is_readable($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                // Skip comments and empty lines
                if ($line === '' || $line[0] === '#') continue;
                if (strpos($line, '=') === false) continue;

                [$key, $value] = explode('=', $line, 2);
                $key   = trim($key);
                $value = trim($value);
                // Strip surrounding quotes if present
                if (strlen($value) >= 2 &&
                    (($value[0] === '"' && $value[-1] === '"') ||
                     ($value[0] === "'" && $value[-1] === "'"))) {
                    $value = substr($value, 1, -1);
                }
                $envVars[$key] = $value;
                // Also set in environment for getenv() compatibility
                putenv("{$key}={$value}");
            }
        }

        // Helper: get value from .env array OR system env
        $env = function (string $key, string $default = '') use ($envVars): string {
            if (isset($envVars[$key]) && $envVars[$key] !== '') return $envVars[$key];
            $sys = getenv($key);
            return ($sys !== false && $sys !== '') ? $sys : $default;
        };

        // ============ Database ============
        if (!defined('DB_HOST')) define('DB_HOST', $env('DB_HOST', 'localhost'));
        if (!defined('DB_USER')) define('DB_USER', $env('DB_USER', ''));
        if (!defined('DB_PASS')) define('DB_PASS', $env('DB_PASS', ''));
        if (!defined('DB_NAME')) define('DB_NAME', $env('DB_NAME', ''));

        // ============ AquaSMS ============
        if (!defined('AQUASMS_USERNAME'))   define('AQUASMS_USERNAME',   $env('AQUASMS_USERNAME',   ''));
        if (!defined('AQUASMS_APIKEY'))     define('AQUASMS_APIKEY',     $env('AQUASMS_APIKEY',     ''));
        if (!defined('AQUASMS_SENDERNAME')) define('AQUASMS_SENDERNAME', $env('AQUASMS_SENDERNAME', 'CRTFUL'));
        if (!defined('AQUASMS_SMSTYPE'))    define('AQUASMS_SMSTYPE',    $env('AQUASMS_SMSTYPE',    'TRANS'));
        if (!defined('AQUASMS_BASE_URL'))   define('AQUASMS_BASE_URL',   $env('AQUASMS_BASE_URL',   'http://login.aquasms.com'));
        if (!defined('AQUASMS_PEID'))       define('AQUASMS_PEID',       $env('AQUASMS_PEID',       ''));
        if (!defined('AQUASMS_TEMPLATEID')) define('AQUASMS_TEMPLATEID', $env('AQUASMS_TEMPLATEID', ''));

        // ============ FCM / Firebase ============
        if (!defined('FCM_PROJECT_ID')) define('FCM_PROJECT_ID', $env('FCM_PROJECT_ID', ''));
        $saRelPath = $env('GOOGLE_SA_JSON_PATH', 'secure/service-account.json');
        $saAbsPath = dirname(__DIR__) . '/' . ltrim($saRelPath, '/');
        if (!defined('GOOGLE_SA_JSON')) define('GOOGLE_SA_JSON', $saAbsPath);

        // ============ Payment Gateways (Fallback tokens) ============
        if (!defined('IMB_TOKEN_FALLBACK'))      define('IMB_TOKEN_FALLBACK',      $env('IMB_TOKEN_FALLBACK',      ''));
        if (!defined('YUVIPAY_SECRET_KEY'))      define('YUVIPAY_SECRET_KEY',      $env('YUVIPAY_SECRET_KEY',      ''));
        if (!defined('YUVIPAY_MERCH_ID'))        define('YUVIPAY_MERCH_ID',        $env('YUVIPAY_MERCH_ID',        ''));
    }
}

_mk_load_env();

