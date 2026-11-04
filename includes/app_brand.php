<?php
/**
 * Central branding & API configuration for the Subh Gaming product suite.
 * Update these values once to propagate the app name and endpoint settings.
 */
if (!defined('SUBH_APP_NAME')) {
    define('SUBH_APP_NAME', 'Savariya Seth');
}
if (!defined('SUBH_APP_SLUG')) {
    define('SUBH_APP_SLUG', 'savariya-seth');
}
if (!defined('SUBH_APP_TAGLINE')) {
    define('SUBH_APP_TAGLINE', 'जय श्री साँवलिया सेठ • Fast Results • 100% Trusted');
}
if (!defined('SUBH_APP_DOMAIN')) {
    define('SUBH_APP_DOMAIN', $_SERVER['HTTP_HOST'] ?? 'localhost');
}
if (!defined('SUBH_API_BASE')) {
    define('SUBH_API_BASE', 'https://' . (string) SUBH_APP_DOMAIN . '/api');
}
if (!defined('SUBH_APP_LOGO')) {
    define('SUBH_APP_LOGO', '/images/savariya_seth.jpg');
}
if (!defined('SUBH_THEME_PRIMARY')) {
    define('SUBH_THEME_PRIMARY', '#7c3aed');
}
if (!defined('SUBH_THEME_SECONDARY')) {
    define('SUBH_THEME_SECONDARY', '#2dd4bf');
}
if (!defined('SUBH_THEME_DARK')) {
    define('SUBH_THEME_DARK', '#0b1020');
}
if (!defined('SUBH_THEME_PANEL')) {
    define('SUBH_THEME_PANEL', '#121a2a');
}
if (!defined('SUBH_THEME_GOLD')) {
    define('SUBH_THEME_GOLD', '#fbbf24');
}
