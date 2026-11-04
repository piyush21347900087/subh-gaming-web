<?php
/**
 * ==============================================================================
 * SUBH GAMING - CENTRAL API SECURITY & AUTH KEYS (2026)
 * ==============================================================================
 * Central repository of cryptographic keys for all APIs, webhooks,
 * payment callbacks, and Android app communication.
 * ==============================================================================
 */

if (!defined('SUBH_API_SECRET_KEY')) {
    define('SUBH_API_SECRET_KEY', 'SG_2026_9f8e7d6c5b4a3x2z1k0m_SECURE');
}

if (!defined('SUBH_WINS_TRIGGER_KEY')) {
    define('SUBH_WINS_TRIGGER_KEY', 'SUBH_WINS_SECURE_2026');
}

if (!defined('SUBH_RESULT_SYNC_KEY')) {
    define('SUBH_RESULT_SYNC_KEY', 'SUBH_SYNC_2026_v9a');
}

if (!defined('SUBH_CLEAN_KEY')) {
    define('SUBH_CLEAN_KEY', 'SubhGamingClean2026');
}

// Fallback constant definitions for backward compatibility
if (!defined('API_SECRET_KEY')) {
    define('API_SECRET_KEY', SUBH_API_SECRET_KEY);
}
if (!defined('WINS_TRIGGER_KEY')) {
    define('WINS_TRIGGER_KEY', SUBH_WINS_TRIGGER_KEY);
}
if (!defined('WINS_COMPUTE_URL')) {
    define('WINS_COMPUTE_URL', 'fetch_wins_api.php');
}
