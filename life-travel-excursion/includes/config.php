<?php
/**
 * Configuration centralisée pour Life Travel Excursion
 * 
 * Contient toutes les constantes, paramètres et configurations globales
 * pour assurer une cohérence et faciliter la maintenance
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

defined('ABSPATH') || exit;

// Version du plugin
define('LIFE_TRAVEL_VERSION', '2.3.4');

// Chemins et URLs
// Répertoire du plugin principal (racine)
define('LIFE_TRAVEL_PLUGIN_DIR', dirname(__DIR__) . '/');
define('LIFE_TRAVEL_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
define('LIFE_TRAVEL_ASSETS_URL', LIFE_TRAVEL_PLUGIN_URL . 'assets/');
define('LIFE_TRAVEL_INCLUDES_DIR', LIFE_TRAVEL_PLUGIN_DIR . 'includes/');
define('LIFE_TRAVEL_TEMPLATES_DIR', LIFE_TRAVEL_PLUGIN_DIR . 'templates/');

// Site principal - Utilisez un chemin relatif pour la portabilité entre environnements
define('LIFE_TRAVEL_SITE_DIR', get_option('life_travel_site_dir', dirname(LIFE_TRAVEL_PLUGIN_DIR) . '/CascadeProjects/life-travel'));
define('LIFE_TRAVEL_SITE_URL', get_option('life_travel_site_url', home_url()));

// Configuration API et intégrations externes
define('LIFE_TRAVEL_API_ENDPOINT', get_option('life_travel_api_endpoint', 'https://api.life-travel.org/v1'));
define('LIFE_TRAVEL_MAPS_API_KEY', get_option('life_travel_maps_api_key', ''));

// Paramètres de sécurité
define('LIFE_TRAVEL_SECURE_COOKIE', is_ssl());
define('LIFE_TRAVEL_SESSION_EXPIRATION', 7200); // 2 heures en secondes
define('LIFE_TRAVEL_COOKIE_PATH', COOKIEPATH ?: '/');
define('LIFE_TRAVEL_COOKIE_DOMAIN', COOKIE_DOMAIN ?: '');

// Paramètres des passerelles de paiement
define('LIFE_TRAVEL_PAYMENT_TEST_MODE', get_option('life_travel_payment_test_mode', true));
define('LIFE_TRAVEL_IWOMIPAY_TEST_API_KEY', get_option('life_travel_iwomipay_test_api_key', ''));
define('LIFE_TRAVEL_IWOMIPAY_LIVE_API_KEY', get_option('life_travel_iwomipay_live_api_key', ''));

// Paramètres par défaut pour les réservations
define('LIFE_TRAVEL_MIN_BOOKING_ADVANCE', get_option('life_travel_min_booking_advance', 1)); // Jours minimum à l'avance
define('LIFE_TRAVEL_MAX_PARTICIPANTS', get_option('life_travel_max_participants', 20));
define('LIFE_TRAVEL_ABANDONED_CART_TIMEOUT', 60 * 60); // 1 heure en secondes

// Paramètres de performances
define('LIFE_TRAVEL_CACHE_DURATION', 3600); // 1 heure en secondes
define('LIFE_TRAVEL_IMAGE_QUALITY', 85); // Qualité des images WebP (0-100)

// Fonctions d'aide pour la sécurité

/**
 * Génère un nonce pour les actions AJAX
 * 
 * @param string $action Action spécifique
 * @return string Nonce généré
 */
function life_travel_get_nonce($action) {
    return wp_create_nonce('life_travel_' . $action . '_nonce');
}

/**
 * Vérifie un nonce pour les actions AJAX
 * 
 * @param string $nonce Nonce à vérifier
 * @param string $action Action spécifique
 * @return bool True si valide, sinon False
 */
function life_travel_verify_nonce($nonce, $action) {
    return wp_verify_nonce($nonce, 'life_travel_' . $action . '_nonce');
}

/**
 * Assainit et valide un email
 * 
 * @param string $email Email à valider
 * @return string|bool Email assaini ou false si invalide
 */
function life_travel_validate_email($email) {
    $email = sanitize_email($email);
    return is_email($email) ? $email : false;
}

/**
 * Vérifie les permissions d'administration
 * 
 * @param string $capability Capacité requise (default: manage_options)
 * @return bool True si l'utilisateur a les permissions
 */
function life_travel_check_admin_permission($capability = 'manage_options') {
    if (!current_user_can($capability)) {
        return false;
    }
    return true;
}

/**
 * Journalise les erreurs de sécurité
 * 
 * @param string $message Message d'erreur
 * @param string $type Type d'erreur (default: security)
 * @return void
 */
function life_travel_log_security_issue($message, $type = 'security') {
    // Central WP Logger via WooCommerce if available
    $context = array(
        'type'    => $type,
        'user_id' => get_current_user_id(),
        'ip'      => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown')
    );
    if ( function_exists('wc_get_logger') ) {
        $logger = wc_get_logger();
        $logger->warning($message, $context);
    } else {
        // fallback
        error_log(sprintf('[%s] %s | user:%d ip:%s', $type, $message, $context['user_id'], $context['ip']));
    }
}
