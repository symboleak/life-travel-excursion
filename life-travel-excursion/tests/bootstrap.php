<?php
// Bootstrap for PHPUnit tests

// Composer autoload
require __DIR__ . '/../vendor/autoload.php';
// Manually include WP_Mock class due to PSR-4 mapping issue
require_once __DIR__ . '/../vendor/10up/wp_mock/php/WP_Mock.php';

// Initialize WP Mock framework
\WP_Mock::setUp();

// Register tearDown
register_shutdown_function(function() {
    \WP_Mock::tearDown();
});

// Define WP environment constant
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
// Stub WP core functions/constants used by plugin bootstrap
if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {}
}
if (!function_exists('add_option')) {
    function add_option($option, $value = '') {}
}
if (!function_exists('get_option')) {
    function get_option($option, $default = null) {
        return $option === 'active_plugins' ? [] : $default;
    }
}
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        return $value;
    }
}
if (!function_exists('home_url')) {
    function home_url($path = '') { return 'http://example.com'; }
}
if (!function_exists('is_ssl')) {
    function is_ssl() { return false; }
}
if (!defined('COOKIEPATH')) {
    define('COOKIEPATH', '/');
}
if (!defined('COOKIE_DOMAIN')) {
    define('COOKIE_DOMAIN', '');
}
if (!function_exists('sanitize_email')) {
    function sanitize_email($email) { return $email; }
}
if (!function_exists('is_email')) {
    function is_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false; }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text) { return $text; }
}
if (!function_exists('current_user_can')) {
    function current_user_can($capability) { return true; }
}

// Stub WordPress plugin functions
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) { return dirname($file) . '/'; }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) { return 'http://example.com/'; }
}

// Define WordPress functions stubs if not loaded
if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $func) {}
}
if (!function_exists('get_theme_mod')) {
    function get_theme_mod($name, $default = null) { return $default; }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '') { return 'http://example.com/wp-admin/'.ltrim($path, '/'); }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) { return 'nonce'; }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return $text; }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return $text; }
}
if (!function_exists('__')) {
    function __($text, $domain = null) { return $text; }
}

// Load plugin main file
require_once __DIR__ . '/../life-travel-excursion.php';

// Explicitly include the loyalty file for testing
if (file_exists(__DIR__ . '/../includes/frontend/loyalty.php')) {
    require_once __DIR__ . '/../includes/frontend/loyalty.php';
}
