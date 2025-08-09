<?php
/**
 * Plugin Name: Life Travel Payment Adapters
 * Plugin URI: https://lifetravel.com
 * Description: Adaptateurs pour les passerelles de paiement IwomiPay utilisées par Life Travel
 * Version: 1.0.0
 * Author: Life Travel Team
 * Text Domain: life-travel-payment-adapters
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.2
 */

// Sécurité
if (!defined('ABSPATH')) {
    exit;
}

define('LTE_PAYMENT_ADAPTERS_VERSION', '1.0.0');
define('LTE_PAYMENT_ADAPTERS_PATH', plugin_dir_path(__FILE__));
define('LTE_PAYMENT_ADAPTERS_URL', plugin_dir_url(__FILE__));

/**
 * Vérifier que WooCommerce est actif
 */
function lte_payment_adapters_check_dependencies() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'lte_payment_adapters_missing_wc_notice');
        return false;
    }
    
    // Vérifier que les plugins IwomiPay sont présents
    $required_plugins = array(
        'iwomipay-momo-woocommerce/iwomipay-momo-woocommerce.php' => 'IwomiPay MTN MoMo',
        'iwomipay-om-woocommerce/iwomipay-om-woocommerce.php' => 'IwomiPay Orange Money'
    );
    
    $missing_plugins = array();
    foreach ($required_plugins as $plugin_path => $plugin_name) {
        if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin_path)) {
            $missing_plugins[] = $plugin_name;
        }
    }
    
    if (!empty($missing_plugins)) {
        add_action('admin_notices', function() use ($missing_plugins) {
            lte_payment_adapters_missing_plugins_notice($missing_plugins);
        });
        return false;
    }
    
    return true;
}

/**
 * Notice d'erreur si WooCommerce est manquant
 */
function lte_payment_adapters_missing_wc_notice() {
    ?>
    <div class="error">
        <p><?php _e('Life Travel Payment Adapters nécessite WooCommerce pour fonctionner. Veuillez installer et activer WooCommerce.', 'life-travel-payment-adapters'); ?></p>
    </div>
    <?php
}

/**
 * Notice d'erreur si des plugins IwomiPay sont manquants
 */
function lte_payment_adapters_missing_plugins_notice($missing_plugins) {
    ?>
    <div class="error">
        <p>
            <?php 
            printf(
                __('Life Travel Payment Adapters nécessite les plugins suivants: %s. Veuillez les installer et les activer.', 'life-travel-payment-adapters'),
                '<strong>' . implode(', ', $missing_plugins) . '</strong>'
            ); 
            ?>
        </p>
    </div>
    <?php
}

/**
 * Initialisation du plugin
 */
function lte_payment_adapters_init() {
    if (!lte_payment_adapters_check_dependencies()) {
        return;
    }
    
    // Charger les classes
    require_once LTE_PAYMENT_ADAPTERS_PATH . 'includes/class-payment-manager.php';
    require_once LTE_PAYMENT_ADAPTERS_PATH . 'includes/class-momo-adapter.php';
    require_once LTE_PAYMENT_ADAPTERS_PATH . 'includes/class-om-adapter.php';
    
    // Initialiser le gestionnaire de paiement
    $payment_manager = new LTE_Payment_Manager();
    $payment_manager->init();
}
add_action('plugins_loaded', 'lte_payment_adapters_init', 20); // Priorité 20 pour s'assurer que WooCommerce est chargé
