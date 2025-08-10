<?php
/**
 * Ce fichier aide l'IDE à reconnaître les fonctions WordPress et WooCommerce
 * Les erreurs "Undefined function" seront ainsi évitées
 */

// WordPress Core Functions
if (!function_exists('plugins_url')) { function plugins_url($path = '', $plugin = '') {} }
if (!function_exists('plugin_basename')) { function plugin_basename($file) {} }
if (!function_exists('wp_json_encode')) { function wp_json_encode($data, $options = 0, $depth = 512) {} }
if (!function_exists('wp_remote_post')) { function wp_remote_post($url, $args = array()) {} }
if (!function_exists('is_wp_error')) { function is_wp_error($thing) {} }
if (!function_exists('wpautop')) { function wpautop($pee, $br = true) {} }
if (!function_exists('wptexturize')) { function wptexturize($text) {} }

// WooCommerce Functions
if (!class_exists('WC_Payment_Gateway')) {
    class WC_Payment_Gateway {
        public function __construct() {}
        public function reduce_order_stock($order) {}
        // Autres méthodes WooCommerce nécessaires...
    }
}
