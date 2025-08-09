<?php
/**
 * Plugin Name: Life Travel Excursion
 * Plugin URI: https://www.life-travel.org/plugins/excursions
 * Description: Plugin pour gérer les excursions et voyages pour Life Travel avec support offline robuste pour le Cameroun
 * Version: 2.5.0
 * Author: Life Travel Team
 * Author URI: https://www.life-travel.org/
 * Text Domain: life-travel-excursion
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 8.5
 */

// Protection contre l'accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Définition des constantes principales
 * 
 * Section optimisée pour définir toutes les constantes utilisées dans le plugin
 * avec des commentaires explicites
 */
// Version et chemins du plugin
define('LIFE_TRAVEL_EXCURSION_VERSION', '2.5.0'); // Version du plugin pour comparaisons et mises à jour
define('LIFE_TRAVEL_EXCURSION_DIR', plugin_dir_path(__FILE__)); // Chemin absolu du plugin avec trailing slash
define('LIFE_TRAVEL_EXCURSION_URL', plugin_dir_url(__FILE__)); // URL du plugin avec trailing slash
define('LIFE_TRAVEL_EXCURSION_ASSETS', LIFE_TRAVEL_EXCURSION_URL . 'assets/'); // URL des assets

// Constantes pour le système offline/PWA (spécifique au contexte camerounais)
define('LIFE_TRAVEL_CACHE_NAME', 'life-travel-cache-v2'); // Nom du cache SW (must match avec JS)
define('LIFE_TRAVEL_SVG_PATH', LIFE_TRAVEL_EXCURSION_ASSETS . 'sprite.svg'); // Chemin du sprite SVG (unifié)
define('LIFE_TRAVEL_CAMEROON_NETWORK_TIMEOUT', 5000); // Timeout adapté aux réseaux camerounais

/**
 * Chargement de l'autoload Composer si disponible
 */
if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'vendor/autoload.php')) {
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'vendor/autoload.php';
}

/**
 * Initialisation de base du plugin
 * 
 * Création des dossiers essentiels si nécessaire
 */
// Créer les dossiers requis s'ils n'existent pas (avec protection contre les erreurs)
if (!file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes') && !@mkdir(LIFE_TRAVEL_EXCURSION_DIR . 'includes', 0755, true)) {
    error_log('Life Travel: Impossible de créer le dossier includes');
}

if (!file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'assets/img') && !@mkdir(LIFE_TRAVEL_EXCURSION_DIR . 'assets/img', 0755, true)) {
    error_log('Life Travel: Impossible de créer le dossier assets/img');
}

if (!file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'payment-gateways') && !@mkdir(LIFE_TRAVEL_EXCURSION_DIR . 'payment-gateways', 0755, true)) {
    error_log('Life Travel: Impossible de créer le dossier payment-gateways');
}

/**
 * Déclarations des fonctions principales et hooks
 */

/**
 * Fonction d'activation du plugin
 * 
 * Exécutée lorsque le plugin est activé via l'interface d'administration
 * Configure les options par défaut et crée les structures nécessaires
 */
function life_travel_excursion_activate() {
    // Ajouter une version dans les options pour les mises à jour futures
    add_option('life_travel_excursion_version', LIFE_TRAVEL_EXCURSION_VERSION);
    
    // Activer par défaut les nouveaux systèmes pour les nouvelles installations
    add_option('life_travel_use_new_admin', true);
    add_option('life_travel_use_new_optimizer', true);
    add_option('life_travel_use_new_cart_system', true);
    add_option('life_travel_use_new_offline_system', true);
    
    // Créer les tables nécessaires via les ponts
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/abandoned-cart-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/abandoned-cart-bridge.php';
        // Utiliser la méthode de création de table de la classe de compatibilité
        if (class_exists('Life_Travel_Abandoned_Cart')) {
            Life_Travel_Abandoned_Cart::create_table();
        }
    }
    
    // Générer la page hors ligne via le pont si disponible
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/offline-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/offline-bridge.php';
        if (function_exists('life_travel_generate_offline_page')) {
            life_travel_generate_offline_page();
        }
    } else if (!file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'offline.html')) {
        // Fallback: créer une page hors ligne basique si aucun pont n'est disponible
        $offline_template = '<' . '!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Life Travel - Connexion perdue</title>
    <style>
        body {
            font-family: sans-serif;
            text-align: center;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 8px;
        }
        h1 {
            color: #333;
        }
        .btn {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Vous êtes hors ligne</h1>
        <p>Votre connexion internet semble interrompue. Vos données ont été sauvegardées.</p>
        <a href="javascript:window.location.reload()" class="btn">Réessayer</a>
    </div>
</body>
</html>';
        file_put_contents(LIFE_TRAVEL_EXCURSION_DIR . 'offline.html', $offline_template);
    }
    
    // Créer l'image de secours (fallback) lors de l'activation
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/image-optimization.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/image-optimization.php';
        if (function_exists('life_travel_create_fallback_image')) {
            life_travel_create_fallback_image();
        }
    }
    // Forcer le flush des règles de réécriture pour l’endpoint Service Worker unifié
    // L'endpoint /life-travel-sw.js sera disponible dès le prochain chargement
    update_option('life_travel_flush_rewrite_rules', true);
}

/**
 * Message d'erreur affiché si WooCommerce n'est pas actif
 * 
 * Cette fonction est accessible globalement pour garantir
 * qu'elle peut être appelée indépendamment des conditions d'activation
 */
function life_travel_excursion_woocommerce_missing_notice() {
    echo '<div class="error"><p>' . esc_html__( 'Le plugin Life Travel Excursion nécessite WooCommerce pour fonctionner.', 'life-travel-excursion' ) . '</p></div>';
}

/**
 * Initialisation du type de produit personnalisé pour les excursions
 * 
 * Enregistre les hooks nécessaires pour le type de produit "excursion"
 * Cette fonction est exécutée uniquement après le chargement de WooCommerce
 */
function life_travel_excursion_init() {
    // Définition du type de produit personnalisé "excursion"
    if (!class_exists('WC_Product_Excursion') && class_exists('WC_Product')) {
        class WC_Product_Excursion extends WC_Product {
            /**
             * Retourne le type spécifique de produit
             * 
             * @return string
             */
            public function get_type() {
                return 'excursion';
            }
        }
    }
    
    /**
     * Ajoute le type "excursion" au sélecteur de types de produits WooCommerce
     * 
     * @param array $types Types de produits WooCommerce existants
     * @return array Types de produits mis à jour
     */
    function life_travel_excursion_add_product_type($types) {
        $types['excursion'] = __('Excursion', 'life-travel-excursion');
        return $types;
    }
    
    /**
     * Enregistre la classe personnalisée pour le type de produit "excursion"
     * 
     * @param string $classname Nom de la classe produit actuelle
     * @param string $product_type Type de produit à vérifier
     * @return string Nom de la classe à utiliser pour ce produit
     */
    function life_travel_excursion_product_class($classname, $product_type) {
        if ($product_type === 'excursion') {
            $classname = 'WC_Product_Excursion';
        }
        return $classname;
    }
    
    // Hooks pour le type de produit excursion
    add_filter('product_type_selector', 'life_travel_excursion_add_product_type');
    add_filter('woocommerce_product_class', 'life_travel_excursion_product_class', 10, 2);
    
    // Charger les scripts et styles pour le front et l'admin
    add_action('wp_enqueue_scripts', 'life_travel_excursion_enqueue_scripts');
    add_action('admin_enqueue_scripts', 'life_travel_excursion_admin_scripts');
    
    // Hooks pour les champs personnalisés et le formulaire de réservation
    add_action('woocommerce_product_options_general_product_data', 'life_travel_excursion_product_fields');
    add_action('woocommerce_process_product_meta', 'life_travel_excursion_save_product_fields');
    add_action('woocommerce_single_product_summary', 'life_travel_excursion_display_booking_form', 25);
    
    // Charger le système de fidélité
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/loyalty-integration.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/loyalty-integration.php';
    }
    
    // Charger le panneau d'administration de fidélité
    if (is_admin() && file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/loyalty-admin.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/loyalty-admin.php';
    }
}

// Afficher le formulaire de connexion dans la page Mon Compte pour les utilisateurs non connectés
// avec une fonction anonyme plus moderne et mieux encapsulée
add_action('woocommerce_before_my_account', function() {
    if (!is_user_logged_in()) {
        echo do_shortcode('[lte_login]');
        return;
    }
});

/**
 * Exporte les commandes d'excursions au format CSV
 * 
 * Fonction appelée uniquement via le paramètre GET export_orders_csv=1
 * Vérifie les permissions et génère un fichier CSV des commandes avec produits de type excursion
 */
function life_travel_excursion_export_csv() {
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=excursions_orders_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Numéro de commande', 'Date', 'Total', 'Statut'));
    
    // Récupérer uniquement les commandes avec excursions pour optimiser la performance
    $orders = wc_get_orders(array(
        'limit' => -1,
        'type' => 'shop_order',
    ));
    
    foreach ($orders as $order) {
        $has_excursion = false;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && 'excursion' === $product->get_type()) {
                $has_excursion = true;
                break;
            }
        }
        
        if ($has_excursion) {
            fputcsv($output, array(
                $order->get_order_number(),
                $order->get_date_created()->format('Y-m-d'),
                $order->get_formatted_order_total(),
                wc_get_order_status_name($order->get_status())
            ));
        }
    }
    
    fclose($output);
    exit;
}

/**
 * Enregistrement des hooks principaux
 * Organisés par catégories fonctionnelles pour une meilleure lisibilité
 */

// === Hooks d'initialisation et d'activation ===
// Hook d'activation du plugin - configuration initiale
register_activation_hook(__FILE__, 'life_travel_excursion_activate');

// Le hook woocommerce_loaded pour l'initialisation du type de produit a été déplacé
// dans la section conditionnelle principale pour un chargement optimal

// === Hooks AJAX pour les réservations ===
// Actions AJAX pour le panier et le calcul de prix des excursions
add_action('wp_ajax_life_travel_excursion_add_to_cart', 'life_travel_excursion_add_to_cart');
add_action('wp_ajax_nopriv_life_travel_excursion_add_to_cart', 'life_travel_excursion_add_to_cart');
add_action('wp_ajax_life_travel_excursion_calculate_price', 'life_travel_excursion_calculate_price_handler');
add_action('wp_ajax_nopriv_life_travel_excursion_calculate_price', 'life_travel_excursion_calculate_price_handler');

// === Hooks WooCommerce pour les produits et paniers ===
// Intégration avec WooCommerce pour les fonctionnalités d'excursion
add_filter('woocommerce_get_item_data', 'life_travel_excursion_display_cart_item_data', 10, 2);
add_action('woocommerce_checkout_create_order_line_item', 'life_travel_excursion_add_order_item_meta', 10, 4);
add_filter('woocommerce_add_to_cart_validation', 'life_travel_excursion_add_to_cart_validation', 10, 3);

// === Hooks de notification ===
// Notification lors de la complétion d'une commande
add_action('woocommerce_order_status_completed', 'life_travel_excursion_notify_order_completed');

// === Hooks d'administration et d'export ===
if (is_admin()) {
    // Hooks pour les colonnes personnalisées dans la liste des commandes
    add_filter('manage_edit-shop_order_columns', 'life_travel_excursion_add_admin_order_columns');
    add_action('manage_shop_order_posts_custom_column', 'life_travel_excursion_display_admin_order_column_content', 10, 2);
    add_filter('woocommerce_shop_order_list_table_request', 'life_travel_excursion_filter_orders_by_excursions');
    add_filter('woocommerce_shop_order_types', 'life_travel_excursion_add_shop_order_filter');
    
    // Export CSV - activé uniquement quand nécessaire
    if (isset($_GET['export_orders_csv']) && $_GET['export_orders_csv'] == '1') {
        add_action('init', 'life_travel_excursion_export_csv');
    }
}

// Hooks frontend
add_action('woocommerce_before_my_account', 'life_travel_excursion_display_login_form');

/**
 * Vérification des dépendances et initialisation conditionnelle
 * 
 * Cette section vérifie que les prérequis du plugin sont remplis
 * avant de charger les fonctionnalités principales
 */

/**
 * Structure principale du plugin
 */
// Vérifier si WooCommerce est actif
$woocommerce_active = in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));

// Charger le plugin uniquement si WooCommerce est actif
if ($woocommerce_active) {
    
    // Inclure les fichiers d'intégration
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/config.php'; // Configuration centralisée en premier
    
    // Ponts de migration pour éviter les doublons et assurer la transition
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/performance-optimizer-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/performance-optimizer-bridge.php';
    } else {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/performance-optimizer.php'; // Ancien optimisateur
    }
    
    // Pont pour les scripts JavaScript
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/js-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/js-bridge.php';
    }
    
    // Pont pour les styles CSS
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/css-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/css-bridge.php';
    }
    
    // Nouveau système centralisé d'élimination des dépendances circulaires entre bridges
    // Ce système permet une meilleure robustesse et performances dans les réseaux instables
    $bridges_core_path = LIFE_TRAVEL_EXCURSION_DIR . 'includes/bridges-core.php';
    if (file_exists($bridges_core_path)) {
        // Charger le noyau central des bridges en premier (résout les dépendances circulaires)
        require_once $bridges_core_path;
        
        // Puis charger l'initialiser de bridges pour la compatibilité ascendante
        if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/init-bridges.php')) {
            require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/init-bridges.php';
            // L'initialisation des bridges sera gérée dans l'ordre optimal par le noyau central
        }
        
        // Charger l'outil de diagnostic des bridges pour le débogage et la maintenance
        if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/bridges-diagnostics.php')) {
            require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/bridges-diagnostics.php';
            // L'outil de diagnostic s'initialisera automatiquement via son hook plugins_loaded
        }
    } else if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/init-bridges.php')) {
        // Fallback vers l'ancien système centralisé si le noyau n'est pas disponible
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/init-bridges.php';
        // L'initialisation des bridges sera gérée dans l'ordre optimal par l'initialiseur
    } else {
        // Fallback ultime vers le chargement individuel des ponts
        if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/pwa-bridge.php')) {
            require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/pwa-bridge.php';
        }
        
        // Pont pour les ressources graphiques
        if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/images-bridge.php')) {
            require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/images-bridge.php';
        }
    }
    
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/abandoned-cart-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/abandoned-cart-bridge.php';
    } else {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/abandoned-cart-recovery.php'; // Ancien système de paniers
    }
    
    // Système d'optimisation des requêtes de base de données (critique pour le contexte camerounais)
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/database-optimization.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/database-optimization.php';
        
        // Charger le gestionnaire AJAX pour l'optimisation de base de données (admin uniquement)
        if (is_admin() && file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/database-ajax.php')) {
            require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/database-ajax.php';
        }
        
        // Charger l'installateur d'index pour optimiser les requêtes de disponibilité
        if (is_admin() && file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/index-installer.php')) {
            require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/index-installer.php';
        }
    }
    
    // Optimiseur d'assets pour les réseaux à faible connectivité (contexte camerounais)
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/cameroon-assets-optimizer.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/cameroon-assets-optimizer.php';
    }
    
    // Autres composants standard
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/life-travel-site-integration.php';
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/payment-gateways-adapter.php';
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/life-travel-price-display.php';
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/integration-loader.php'; // Intégration admin-frontend
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/image-optimization.php';
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/notifications-manager.php';
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/local-storage-manager.php';
    
    // Chargement du validateur de ponts pour garantir la cohérence des interfaces
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/bridge-validator.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/bridge-validator.php';
    }
    
    // Gestion du mode hors ligne (chargé par l'initialiseur, uniquement si fallback nécessaire)
    if (!file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/init-bridges.php') && 
        file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/offline-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/offline-bridge.php';
    }
    
    // Chargement du tableau de bord d'administration unifié ou de l'ancien système
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin-bridge.php';
    } else if (is_admin()) {
        // Ancien système d'administration (pour compatibilité)
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/woocommerce-admin-extension.php';
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/admin-menu.php';
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/excursion-metabox.php';
    }
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/abandoned-cart-security.php'; // Nouveau
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/push-notifications-manager.php'; // Notifications push
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/loyalty-social.php'; // Fidélité et partage social
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/whatsapp-contact.php'; // Bouton WhatsApp permanent
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/interactive-map.php'; // Mini-carte interactive
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/checkout-enhancement.php'; // Amélioration du checkout
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/my-account-tabs.php'; // Onglets personnalisés My Account
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/authentication-manager.php'; // Authentification sécurisée
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/authentication-ajax.php'; // AJAX pour l'authentification
    require_once __DIR__ . '/includes/admin/customizer.php'; // Customizer settings
    require_once __DIR__ . '/includes/admin/admin-notifications.php'; // Notifications admin
    require_once __DIR__ . '/includes/admin/csv-generator.php'; // Génération de CSV
    require_once __DIR__ . '/includes/admin/whatsapp-sender.php'; // Envoi WhatsApp
    require_once __DIR__ . '/includes/admin/notification-ajax.php'; // AJAX pour les notifications
    require_once __DIR__ . '/includes/admin/notification-templates.php'; // Modèles de notification
    require_once __DIR__ . '/includes/admin/notification-template-editor.php'; // Éditeur de modèles
    require_once __DIR__ . '/includes/frontend/user-notifications.php'; // Notifications utilisateur
    require_once __DIR__ . '/includes/frontend/user-notification-settings.php'; // Préférences de notification
    require_once __DIR__ . '/includes/frontend/notification-channels.php'; // Canaux de notification

    // Inclure les fichiers d'administration
    if (is_admin()) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/notification-settings.php';
    }
    
    // Passerelle Orange Money: charger seulement si WooCommerce est disponible
    add_action('plugins_loaded', function() {
        if (class_exists('WooCommerce') && class_exists('WC_Payment_Gateway')) {
            require_once __DIR__ . '/includes/payment-gateway-orange-money.php';
            add_filter('woocommerce_payment_gateways', function($gateways) {
                $gateways[] = 'Life_Travel_Gateway_Orange_Money';
                return $gateways;
            });
        } else {
            // Alerte admin si WooCommerce n'est pas disponible
            if (is_admin()) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-warning"><p>Life Travel: la passerelle Orange Money nécessite WooCommerce. Veuillez activer WooCommerce.</p></div>';
                });
            }
        }
    }, 20);
    
        add_action( 'wp_ajax_life_travel_excursion_add_to_cart', 'life_travel_excursion_add_to_cart' );
        add_action( 'wp_ajax_nopriv_life_travel_excursion_add_to_cart', 'life_travel_excursion_add_to_cart' );
        add_filter( 'woocommerce_get_item_data', 'life_travel_excursion_display_cart_item_data', 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', 'life_travel_excursion_add_order_item_meta', 10, 4 );
        add_filter( 'woocommerce_add_to_cart_validation', 'life_travel_excursion_add_to_cart_validation', 10, 3 );
        add_action( 'admin_menu', 'life_travel_excursion_add_settings_page' );
        add_action( 'admin_init', 'life_travel_excursion_register_settings' );

        // Handler AJAX pour le calcul des tarifs
        add_action( 'wp_ajax_life_travel_excursion_calculate_price', 'life_travel_excursion_calculate_price_handler' );
        add_action( 'wp_ajax_nopriv_life_travel_excursion_calculate_price', 'life_travel_excursion_calculate_price_handler' );

        // --- Nouvelles fonctionnalités ---
        // 1. Endpoint "Mes excursions" dans My Account
        add_action( 'init', 'life_travel_excursion_add_myaccount_endpoint' );
        add_filter( 'woocommerce_account_menu_items', 'life_travel_excursion_myaccount_menu_items' );
        add_action( 'woocommerce_account_mes-excursions_endpoint', 'life_travel_excursion_myaccount_content' );

        // 2. Filtre pour restreindre la soumission d'avis (placeholder)
        add_filter( 'pre_comment_approved', 'life_travel_excursion_restrict_review_submission', 10, 2 );

        // --- Module d'export CSV pour administrateurs ---
        if ( is_admin() && isset( $_GET['export_orders_csv'] ) && $_GET['export_orders_csv'] == '1' ) {
            add_action( 'init', 'life_travel_excursion_export_csv' );
        }
        // Ces hooks ont été définis globalement au début du fichier dans la section des hooks principaux

    // Enqueue scripts et styles avec chargement différé pour le script non critique
    function life_travel_excursion_admin_scripts() {
        $screen = get_current_screen();
        
        // N'enregistrer que sur les pages produits
        if ( $screen->id == 'product' ) {
            // Utiliser le pont CSS s'il est disponible
            if (function_exists('life_travel_load_optimized_styles')) {
                life_travel_load_optimized_styles('admin');
            } else {
                // Fallback vers l'ancienne méthode si le pont n'est pas disponible
                wp_enqueue_style( 'life-travel-admin-style', LIFE_TRAVEL_EXCURSION_URL . 'css/admin-style.css', array(), LIFE_TRAVEL_EXCURSION_VERSION );
            }
            
            // Utiliser le pont JavaScript s'il est disponible
            if (function_exists('life_travel_load_optimized_scripts')) {
                life_travel_load_optimized_scripts('admin');
            } else {
                // Fallback vers l'ancienne méthode si le pont n'est pas disponible
                wp_enqueue_script( 'life-travel-admin-excursion', LIFE_TRAVEL_EXCURSION_URL . 'js/admin-excursion.js', array('jquery'), LIFE_TRAVEL_EXCURSION_VERSION, true );
                wp_enqueue_script( 'life-travel-admin-script', LIFE_TRAVEL_EXCURSION_URL . 'js/admin-script.js', array('jquery'), LIFE_TRAVEL_EXCURSION_VERSION, true );
            }
            
            // Localiser le script pour les traductions (fonctionne avec les deux versions)
            $script_handle = function_exists('life_travel_is_using_optimized_scripts') && life_travel_is_using_optimized_scripts() 
                ? 'life-travel-admin-unified' 
                : 'life-travel-admin-script';
                
            wp_localize_script( $script_handle, 'life_travel_excursion_admin', array(
                'min_participants' => __( 'Participants min', 'life-travel-excursion' ),
                'max_participants' => __( 'Participants max', 'life-travel-excursion' ),
                'price_per_person' => __( 'Prix par personne', 'life-travel-excursion' ),
                'extra_name' => __( 'Nom de l\'extra', 'life-travel-excursion' ),
                'price' => __( 'Prix', 'life-travel-excursion' ),
                'quantity' => __( 'Quantité', 'life-travel-excursion' ),
                'selection' => __( 'Sélection', 'life-travel-excursion' ),
                'days' => __( 'Jours', 'life-travel-excursion' ),
                'unique' => __( 'Unique', 'life-travel-excursion' ),
                'participants' => __( 'Participants', 'life-travel-excursion' ),
                'days_participants' => __( 'Jours x Participants', 'life-travel-excursion' ),
                'activity_name' => __( 'Nom de l\'activité', 'life-travel-excursion' ),
                'activity_price' => __( 'Prix de l\'activité', 'life-travel-excursion' ),
                'activity_max_duration' => __( 'Durée maximale (heures)', 'life-travel-excursion' ),
            ) );
        }
    }

    function life_travel_excursion_enqueue_scripts() {
        if ( is_product() ) {
            global $product;
            if ( ! is_a( $product, 'WC_Product' ) ) {
                $product_id = get_the_ID();
                $product = wc_get_product( $product_id );
            }
            if ( $product && 'excursion' === $product->get_type() ) {
                // Utiliser le pont CSS s'il est disponible
                if (function_exists('life_travel_load_optimized_styles')) {
                    life_travel_load_optimized_styles('frontend');
                } else {
                    // Fallback vers l'ancienne méthode
                    wp_enqueue_style( 'jquery-ui-css', LIFE_TRAVEL_EXCURSION_URL . 'css/jquery-ui.min.css' );
                    wp_enqueue_style( 'life-travel-excursion-style', LIFE_TRAVEL_EXCURSION_URL . 'css/style.css' );
                }
                
                // Utiliser le pont JavaScript s'il est disponible
                if (function_exists('life_travel_load_optimized_scripts')) {
                    life_travel_load_optimized_scripts('frontend');
                } else {
                    // Fallback vers l'ancienne méthode
                    wp_enqueue_script( 'jquery-ui-datepicker' );
                    wp_enqueue_script( 'life-travel-script', LIFE_TRAVEL_EXCURSION_URL . 'js/script.js', 
                        array( 'jquery', 'jquery-ui-datepicker' ), 
                        LIFE_TRAVEL_EXCURSION_VERSION, 
                        true
                    );
                    wp_script_add_data( 'life-travel-script', 'defer', true );
                    
                    // Localiser le script pour les traductions
                    wp_localize_script( 'life-travel-script', 'life_travel_excursion_ajax', array(
                    'ajax_url'                   => admin_url( 'admin-ajax.php' ),
                    'nonce'                      => wp_create_nonce( 'life_travel_excursion_nonce' ),
                    'participant_limit_exceeded' => __( 'Le nombre de participants dépasse la limite autorisée pour cette excursion.', 'life-travel-excursion' ),
                    'min_days_before_message'    => __( 'Vous devez réserver au moins %d jours à l\'avance.', 'life-travel-excursion' ),
                    'unavailable_dates'          => array(),
                    'current_user'               => is_user_logged_in() ? array(
                        'display_name' => wp_get_current_user()->display_name,
                        'email'        => wp_get_current_user()->user_email,
                    ) : null,
                    'is_user_logged_in'          => is_user_logged_in(),
                    'login_url'                  => wp_login_url( get_permalink() ),
                    'register_url'               => wp_registration_url(),
                ) );
            }
        }
    }
}

    // Affichage des champs personnalisés dans l'administration du produit
    function life_travel_excursion_product_fields() {
        global $woocommerce, $post;
        
        // Style pour améliorer l'interface
        echo '<style>
            .excursion-section {
                margin: 15px 0;
                padding: 15px;
                background: #f8f8f8;
                border-left: 3px solid #2271b1;
            }
            .excursion-section h3 {
                margin-top: 0;
                color: #2271b1;
                font-size: 14px;
                font-weight: 600;
            }
            .excursion-help {
                background: #e7f5fa;
                border: 1px solid #b7e0f1;
                border-radius: 3px;
                padding: 10px 15px;
                margin: 10px 0;
            }
            .excursion-help h4 {
                margin: 0 0 5px 0;
                color: #0073aa;
            }
            .excursion-help ul {
                margin: 5px 0 5px 20px;
            }
        </style>';
        
        echo '<div class="options_group show_if_excursion">';
        
        // Message d'introduction
        echo '<div class="excursion-help">
            <h4>' . __( 'Configuration de l\'excursion', 'life-travel-excursion' ) . '</h4>
            <p>' . __( 'Utilisez les paramètres ci-dessous pour configurer votre excursion. Chaque section permet de définir un aspect particulier du produit.', 'life-travel-excursion' ) . '</p>
        </div>';

        // SECTION: PARAMÈTRES DE BASE DE L'EXCURSION
        echo '<div class="excursion-section">';
        echo '<h3>' . __( '1. Paramètres généraux de l\'excursion', 'life-travel-excursion' ) . '</h3>';
        echo '<p>' . __( 'Définissez les informations de base de votre excursion comme la capacité, les dates, et les délais de réservation.', 'life-travel-excursion' ) . '</p>';
        
        // Limite de participants
        woocommerce_wp_text_input( array(
            'id'                => '_participant_limit',
            'label'             => __( 'Limite de participants', 'life-travel-excursion' ),
            'description'       => __( 'Nombre maximal de participants pour cette excursion. Ce nombre sera vérifié lors de la réservation.', 'life-travel-excursion' ),
            'desc_tip'          => true,
            'type'              => 'number',
            'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
        ) );

        // Délai minimal avant réservation
        woocommerce_wp_text_input( array(
            'id'                => '_min_days_before',
            'label'             => __( 'Délai minimal avant réservation (jours)', 'life-travel-excursion' ),
            'description'       => __( 'Nombre de jours minimum avant la date de départ. Exemple: 2 signifie que les clients doivent réserver au moins 2 jours avant.', 'life-travel-excursion' ),
            'desc_tip'          => true,
            'type'              => 'number',
            'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
        ) );
        echo '</div>';
        
        // SECTION: GESTION DES DATES
        echo '<div class="excursion-section">';
        echo '<h3>' . __( '2. Dates et périodes de l\'excursion', 'life-travel-excursion' ) . '</h3>';
        echo '<p>' . __( 'Définissez si votre excursion a des dates fixes ou si les clients peuvent choisir leurs dates.', 'life-travel-excursion' ) . '</p>';

        // Excursion à date fixe
        woocommerce_wp_checkbox( array(
            'id'          => '_is_fixed_date',
            'label'       => __( 'Excursion à date fixe', 'life-travel-excursion' ),
            'description' => __( 'Cochez cette case si l\'excursion a une date fixe (les clients ne pourront pas choisir leur date).', 'life-travel-excursion' ),
        ) );

        // Dates de l'excursion
        echo '<div class="date-fields" style="padding-left: 15px; margin-top: 10px;">';
        echo '<p><strong>' . __( 'Période de l\'excursion', 'life-travel-excursion' ) . '</strong></p>';
        woocommerce_wp_text_input( array(
            'id'          => '_start_date',
            'label'       => __( 'Date de début', 'life-travel-excursion' ),
            'description' => __( 'Date de début de l\'excursion.', 'life-travel-excursion' ),
            'desc_tip'    => true,
            'type'        => 'date',
        ) );
        woocommerce_wp_text_input( array(
            'id'          => '_end_date',
            'label'       => __( 'Date de fin', 'life-travel-excursion' ),
            'description' => __( 'Date de fin de l\'excursion. Pour une excursion d\'un jour, utilisez la même date que le début.', 'life-travel-excursion' ),
            'desc_tip'    => true,
            'type'        => 'date',
        ) );
        echo '</div>';

        echo '</div>';
        
        // SECTION: TARIFICATION
        echo '<div class="excursion-section">';
        echo '<h3>' . __( '3. Tarification dynamique', 'life-travel-excursion' ) . '</h3>';
        echo '<p>' . __( 'Définissez différents paliers de prix selon le nombre de participants (offres de groupe).', 'life-travel-excursion' ) . '</p>';
        
        // Explication visuelle des paliers de prix
        echo '<div class="excursion-help">';
        echo '<h4>' . __( 'Comment fonctionnent les paliers de prix ?', 'life-travel-excursion' ) . '</h4>';
        echo '<p>' . __( 'Définissez des tarifs dégressifs selon le nombre de participants. Par exemple:', 'life-travel-excursion' ) . '</p>';
        echo '<ul>';
        echo '<li>' . __( '<strong>1|2|100</strong> signifie que pour 1 à 2 participants, le prix est de 100 € par personne', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( '<strong>3|5|90</strong> signifie que pour 3 à 5 participants, le prix est réduit à 90 € par personne', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( '<strong>6|10|80</strong> signifie que pour 6 à 10 participants, le prix est encore réduit à 80 € par personne', 'life-travel-excursion' ) . '</li>';
        echo '</ul>';
        echo '</div>';
        
        // Paliers de prix
        echo '<p class="form-field"><label for="_pricing_tiers">' . __( 'Définition des paliers de prix', 'life-travel-excursion' ) . '</label>';
        echo '<textarea id="_pricing_tiers" name="_pricing_tiers" placeholder="' . __( '1|2|100\n3|5|90\n6|10|80', 'life-travel-excursion' ) . '" rows="5" cols="20">' . esc_textarea( get_post_meta( $post->ID, '_pricing_tiers', true ) ) . '</textarea>';
        echo '<span class="description">' . __( 'Format : min_participants|max_participants|prix_par_personne, un palier par ligne.', 'life-travel-excursion' ) . '</span></p>';

        // SECTION: OPTIONS ET EXTRAS
        echo '<div class="excursion-section">';
        echo '<h3>' . __( '4. Options supplémentaires et extras', 'life-travel-excursion' ) . '</h3>';
        echo '<p>' . __( 'Proposez des extras et activités optionnelles que vos clients pourront ajouter à leur réservation.', 'life-travel-excursion' ) . '</p>';
        
        // Explication des extras
        echo '<div class="excursion-help">';
        echo '<h4>' . __( 'Comment configurer les extras ?', 'life-travel-excursion' ) . '</h4>';
        echo '<p>' . __( 'Les extras sont des options payantes que les clients peuvent ajouter à leur excursion:', 'life-travel-excursion' ) . '</p>';
        echo '<ul>';
        echo '<li>' . __( '<strong>Boissons|5|quantité|participants</strong> signifie que les boissons coûtent 5€ par participant.', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( '<strong>Location équipement|10|quantité|jours</strong> signifie que la location d\'équipement coûte 10€ par jour.', 'life-travel-excursion' ) . '</li>';
        echo '</ul>';
        echo '<p><strong>' . __( 'Options de multiplicateurs:', 'life-travel-excursion' ) . '</strong></p>';
        echo '<ul>';
        echo '<li>' . __( '<strong>participants</strong>: le prix est multiplié par le nombre de participants', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( '<strong>jours</strong>: le prix est multiplié par le nombre de jours', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( '<strong>jours_participants</strong>: le prix est multiplié par (jours × participants)', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( '<strong>fixe</strong>: prix fixe quelle que soit la durée ou le nombre de participants', 'life-travel-excursion' ) . '</li>';
        echo '</ul>';
        echo '</div>';
        
        // Extras classiques
        echo '<p class="form-field"><label for="_extras"><strong>' . __( 'Extras proposés', 'life-travel-excursion' ) . '</strong></label>';
        echo '<textarea id="_extras" name="_extras" placeholder="' . __( 'Boissons|5|quantité|participants\nLocation équipement|10|quantité|jours', 'life-travel-excursion' ) . '" rows="5" cols="20">' . esc_textarea( get_post_meta( $post->ID, '_extras', true ) ) . '</textarea>';
        echo '<span class="description">' . __( 'Format : Nom|Prix|Type|Multiplicateur, un extra par ligne.', 'life-travel-excursion' ) . '</span></p>';

        // Explication des activités
        echo '<div class="excursion-help">';
        echo '<h4>' . __( 'Comment configurer les activités spécifiques ?', 'life-travel-excursion' ) . '</h4>';
        echo '<p>' . __( 'Les activités sont des options qui peuvent être réalisées durant l\'excursion:', 'life-travel-excursion' ) . '</p>';
        echo '<ul>';
        echo '<li>' . __( '<strong>Randonnée|20|8</strong> signifie que l\'activité "Randonnée" coûte 20€ et peut durer maximum 8 jours.', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( '<strong>Plongée|50|1</strong> signifie que l\'activité "Plongée" coûte 50€ et dure au maximum 1 jour.', 'life-travel-excursion' ) . '</li>';
        echo '</ul>';
        echo '</div>';
        
        // Activités spécifiques
        echo '<p class="form-field"><label for="_activities"><strong>' . __( 'Activités spécifiques', 'life-travel-excursion' ) . '</strong></label>';
        echo '<textarea id="_activities" name="_activities" placeholder="' . __( 'Randonnée|20|8\nPlongée|50|1', 'life-travel-excursion' ) . '" rows="5" cols="20">' . esc_textarea( get_post_meta( $post->ID, '_activities', true ) ) . '</textarea>';
        echo '<span class="description">' . __( 'Format : Nom|Prix|Durée_max (en jours), une activité par ligne.', 'life-travel-excursion' ) . '</span></p>';
        echo '</div>';

        // SECTION: GESTION DES HORAIRES ET DE LA DURÉE
        echo '<div class="excursion-section">';
        echo '<h3>' . __( '5. Horaires et durée', 'life-travel-excursion' ) . '</h3>';
        echo '<p>' . __( 'Définissez les plages horaires et la durée de votre excursion.', 'life-travel-excursion' ) . '</p>';
        
        // Explication des ajustements horaires
        echo '<div class="excursion-help">';
        echo '<h4>' . __( 'Comment fonctionnent les ajustements horaires ?', 'life-travel-excursion' ) . '</h4>';
        
        echo '<div style="background: #f0f8ff; padding: 15px; border-radius: 5px; margin-bottom: 15px;">';
        echo '<p><strong>' . __( 'Exemple concret:', 'life-travel-excursion' ) . '</strong></p>';
        echo '<ul>';
        echo '<li>' . __( 'Une pirogue a une capacité de base de <strong>8 personnes</strong>', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( 'Le seuil minimum pour une unité supplémentaire est de <strong>3 personnes</strong>', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( 'Le coût additionnel par pirogue est de <strong>20€</strong>', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( 'Nombre maximal de pirogues disponibles: <strong>3</strong>', 'life-travel-excursion' ) . '</li>';
        echo '</ul>';
        
        echo '<p>' . __( 'Résultats:', 'life-travel-excursion' ) . '</p>';
        echo '<ul>';
        echo '<li>' . __( 'Pour <strong>1 à 8 personnes</strong>: 1 pirogue est utilisée, pas de coût supplémentaire', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( 'Pour <strong>9 à 10 personnes</strong>: toujours 1 pirogue (il faut au moins 3 personnes supplémentaires pour déclencher une 2ème pirogue)', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( 'Pour <strong>11 à 16 personnes</strong>: 2 pirogues sont utilisées, +20€ sur le prix total', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( 'Pour <strong>17 à 24 personnes</strong>: 3 pirogues sont utilisées, +40€ sur le prix total', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( 'Au-delà de <strong>24 personnes</strong>: la réservation sera refusée (capacité maximale atteinte)', 'life-travel-excursion' ) . '</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '<p><strong>' . __( 'Règles appliquées:', 'life-travel-excursion' ) . '</strong></p>';
        echo '<ol style="margin-left: 20px;">';
        echo '<li>' . __( 'Définissez la <strong>capacité de base</strong> d\'un véhicule/pirogue.', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( 'Le système ajoute automatiquement un véhicule supplémentaire quand le nombre de participants dépasse la capacité d\'au moins le <strong>seuil minimum</strong>.', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( 'Chaque véhicule supplémentaire ajoute un <strong>coût additionnel</strong> au prix total de la réservation.', 'life-travel-excursion' ) . '</li>';
        echo '<li>' . __( 'Le nombre total de véhicules est plafonné au <strong>nombre maximal de véhicules</strong> défini ci-dessous.', 'life-travel-excursion' ) . '</li>';
        echo '</ol>';
        echo '</div>';
        
        echo '<div class="options_group vehicle_management show_if_excursion" style="padding: 15px; background: #f9f9f9; border-radius: 5px;">';
        
        // Configuration en grille pour meilleure lisibilité
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
        
        // Capacité de base
        echo '<div>';
        woocommerce_wp_text_input( array(
            'id'                => '_base_capacity',
            'label'             => __( 'Capacité de base par véhicule', 'life-travel-excursion' ),
            'description'       => __( 'Nombre de participants que peut accueillir un seul véhicule/pirogue. Ex: 8 pour une pirogue.', 'life-travel-excursion' ),
            'desc_tip'          => true,
            'type'              => 'number',
            'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
        ) );
        echo '</div>';
        
        // Seuil minimum
        echo '<div>';
        woocommerce_wp_text_input( array(
            'id'                => '_min_additional_participants',
            'label'             => __( 'Seuil pour véhicule supplémentaire', 'life-travel-excursion' ),
            'description'       => __( 'Nombre minimum de participants supplémentaires pour déclencher un véhicule additionnel. Ex: 3 signifie qu\'il faut au moins 3 personnes en plus de la capacité de base.', 'life-travel-excursion' ),
            'desc_tip'          => true,
            'type'              => 'number',
            'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
        ) );
        echo '</div>';
        
        // Coût additionnel
        echo '<div>';
        woocommerce_wp_text_input( array(
            'id'                => '_additional_unit_cost',
            'label'             => __( 'Coût par véhicule supplémentaire', 'life-travel-excursion' ),
            'description'       => __( 'Montant ajouté au prix total pour chaque véhicule/pirogue supplémentaire nécessaire. Ex: 20€ par pirogue additionnelle.', 'life-travel-excursion' ),
            'desc_tip'          => true,
            'type'              => 'number',
            'custom_attributes' => array( 'min' => '0', 'step' => '0.01' ),
        ) );
        echo '</div>';
        
        // Plafonnement du nombre de véhicules
        echo '<div>';
        woocommerce_wp_text_input( array(
            'id'                => '_max_vehicles',
            'label'             => __( 'Nombre maximal de véhicules', 'life-travel-excursion' ),
            'description'       => __( 'Limitez le nombre total de véhicules/pirogues disponibles pour cette excursion, quelle que soit la demande. Valeur par défaut: 1', 'life-travel-excursion' ),
            'desc_tip'          => true,
            'type'              => 'number',
            'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
            'value'             => get_post_meta( $post->ID, '_max_vehicles', true ) ? get_post_meta( $post->ID, '_max_vehicles', true ) : 1,
        ) );
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Sauvegarde les métadonnées du produit de type excursion
     * 
     * Vérifie et sauvegarde toutes les métadonnées associées à une excursion
     * avec validation des données et vérification de sécurité.
     * 
     * @param int $post_id ID du produit en cours de sauvegarde
     * @return void
     */
    function life_travel_excursion_save_product_fields( $post_id ) {
        // Vérification de sécurité
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Vérifier si c'est une sauvegarde automatique, dans ce cas ne rien faire
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        // Vérifier le type de post
        if ( 'product' !== get_post_type( $post_id ) ) {
            return;
        }
        
        // Début de la sauvegarde des données avec validation
        // Paramètres généraux
        $participant_limit = isset( $_POST['_participant_limit'] ) ? max(1, intval( $_POST['_participant_limit'] )) : 1;
        update_post_meta( $post_id, '_participant_limit', $participant_limit );
        
        $min_days_before = isset( $_POST['_min_days_before'] ) ? max(0, intval( $_POST['_min_days_before'] )) : 0;
        update_post_meta( $post_id, '_min_days_before', $min_days_before );
        
        // Générer un log pour le débogage
        error_log( 'Life Travel Excursion: Sauvegarde des paramètres pour le produit ' . $post_id . ' (limite participants: ' . $participant_limit . ', délai minimal: ' . $min_days_before . ' jours)' );
        
        // Options de date
        $is_fixed_date = isset( $_POST['_is_fixed_date'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_is_fixed_date', $is_fixed_date );
        
        // Validation des dates
        $start_date = isset( $_POST['_start_date'] ) ? sanitize_text_field( $_POST['_start_date'] ) : '';
        $end_date = isset( $_POST['_end_date'] ) ? sanitize_text_field( $_POST['_end_date'] ) : '';
        
        // Vérification de la validité des dates
        if (!empty($start_date) && !empty($end_date)) {
            // Vérifier que la date de fin est >= date de début
            $start_timestamp = strtotime($start_date);
            $end_timestamp = strtotime($end_date);
            
            if ($start_timestamp && $end_timestamp) {
                if ($end_timestamp < $start_timestamp) {
                    // Si la date de fin est antérieure à la date de début, utiliser la date de début comme date de fin
                    $end_date = $start_date;
                    // Journaliser cette correction pour débogage
                    error_log('Life Travel Excursion: Correction automatique de la date de fin pour le produit ' . $post_id . ' (date fin < date début).');
                }
            }
        }
        
        // Sauvegarde des dates validées
        update_post_meta( $post_id, '_start_date', $start_date );
        update_post_meta( $post_id, '_end_date', $end_date );
        
        // Paliers de prix - validation et sauvegarde
        $pricing_tiers = isset( $_POST['_pricing_tiers'] ) ? sanitize_textarea_field( $_POST['_pricing_tiers'] ) : '';
        update_post_meta( $post_id, '_pricing_tiers', $pricing_tiers );
        
        // Extras - validation et sauvegarde
        $extras = isset( $_POST['_extras'] ) ? sanitize_textarea_field( $_POST['_extras'] ) : '';
        update_post_meta( $post_id, '_extras', $extras );
        
        // Activités - validation et sauvegarde
        $activities = isset( $_POST['_activities'] ) ? sanitize_textarea_field( $_POST['_activities'] ) : '';
        update_post_meta( $post_id, '_activities', $activities );
        
        // Ajustements horaires - validation et sauvegarde
        $time_slot_pricing = isset( $_POST['_time_slot_pricing'] ) ? sanitize_textarea_field( $_POST['_time_slot_pricing'] ) : '';
        update_post_meta( $post_id, '_time_slot_pricing', $time_slot_pricing );
        
        // Heures fixes - validation et sauvegarde
        $fixed_start_time = isset( $_POST['_fixed_start_time'] ) ? sanitize_text_field( $_POST['_fixed_start_time'] ) : '';
        $fixed_end_time = isset( $_POST['_fixed_end_time'] ) ? sanitize_text_field( $_POST['_fixed_end_time'] ) : '';
        
        // Vérification des heures
        if (!empty($fixed_start_time) && !empty($fixed_end_time)) {
            // Convertir en minutes pour simplifier la comparaison
            $start_parts = explode(':', $fixed_start_time);
            $end_parts = explode(':', $fixed_end_time);
            
            if (count($start_parts) >= 2 && count($end_parts) >= 2) {
                $start_minutes = intval($start_parts[0]) * 60 + intval($start_parts[1]);
                $end_minutes = intval($end_parts[0]) * 60 + intval($end_parts[1]);
                
                if ($end_minutes <= $start_minutes) {
                    // Ajouter au moins 1h si l'heure de fin est inférieure ou égale à l'heure de début
                    $end_minutes = $start_minutes + 60;
                    $fixed_end_time = sprintf('%02d:%02d', floor($end_minutes / 60), $end_minutes % 60);
                    error_log('Life Travel Excursion: Correction automatique de l\'heure de fin pour le produit ' . $post_id);
                }
            }
        }
        
        update_post_meta( $post_id, '_fixed_start_time', $fixed_start_time );
        update_post_meta( $post_id, '_fixed_end_time', $fixed_end_time );
        
        // Durées - validation et sauvegarde
        $min_duration_hours = isset( $_POST['_min_duration_hours'] ) ? max(1, intval( $_POST['_min_duration_hours'] )) : 1;
        update_post_meta( $post_id, '_min_duration_hours', $min_duration_hours );
        
        $max_duration_days = isset( $_POST['_max_duration_days'] ) ? max(1, intval( $_POST['_max_duration_days'] )) : 1;
        update_post_meta( $post_id, '_max_duration_days', $max_duration_days );
        
        // Capacités des véhicules - validation et sauvegarde
        $base_capacity = isset( $_POST['_base_capacity'] ) ? max(1, intval( $_POST['_base_capacity'] )) : 1;
        update_post_meta( $post_id, '_base_capacity', $base_capacity );
        
        $min_additional = isset( $_POST['_min_additional_participants'] ) ? max(1, intval( $_POST['_min_additional_participants'] )) : 1;
        update_post_meta( $post_id, '_min_additional_participants', $min_additional );
        
        $additional_unit_cost = isset( $_POST['_additional_unit_cost'] ) ? max(0, floatval( $_POST['_additional_unit_cost'] )) : 0;
        update_post_meta( $post_id, '_additional_unit_cost', $additional_unit_cost );
        
        // Plafonnement des véhicules - validation et sauvegarde
        $max_vehicles = isset( $_POST['_max_vehicles'] ) ? max(1, intval( $_POST['_max_vehicles'] )) : 1;
        update_post_meta( $post_id, '_max_vehicles', $max_vehicles );
        
        // Log de débogage pour la configuration des véhicules
        error_log(
            'Life Travel Excursion: Configuration des véhicules pour le produit ' . $post_id . ':\n' .
            'Capacité de base: ' . $base_capacity . '\n' .
            'Seuil pour véhicule supplémentaire: ' . $min_additional . '\n' .
            'Coût par véhicule supplémentaire: ' . $additional_unit_cost . '\n' .
            'Nombre maximal de véhicules: ' . $max_vehicles
        );
    }

    function life_travel_excursion_display_booking_form() {
        global $product;
        if ( 'excursion' !== $product->get_type() ) {
            return;
        }
        $participant_limit = get_post_meta( $product->get_id(), '_participant_limit', true );
        $min_days_before   = get_post_meta( $product->get_id(), '_min_days_before', true );
        $is_fixed_date     = get_post_meta( $product->get_id(), '_is_fixed_date', true );
        $start_date        = get_post_meta( $product->get_id(), '_start_date', true );
        $end_date          = get_post_meta( $product->get_id(), '_end_date', true );
        $pricing_tiers     = get_post_meta( $product->get_id(), '_pricing_tiers', true );
        $extras_list       = get_post_meta( $product->get_id(), '_extras', true );
        $activities_list   = get_post_meta( $product->get_id(), '_activities', true );
        wc_get_template( 'excursion-booking-form.php', array(
            'product'           => $product,
            'participant_limit' => $participant_limit,
            'min_days_before'   => $min_days_before,
            'is_fixed_date'     => $is_fixed_date,
            'start_date'        => $start_date,
            'end_date'          => $end_date,
            'pricing_tiers'     => $pricing_tiers,
            'extras_list'       => $extras_list,
            'activities_list'   => $activities_list,
        ), '', plugin_dir_path( __FILE__ ) . 'templates/' );

        echo '<div class="related-excursions">';
        echo '<h3>' . __( 'Vous pourriez aussi aimer', 'life-travel-excursion' ) . '</h3>';
        woocommerce_output_related_products( array(
            'posts_per_page' => 3,
            'columns'        => 3,
        ) );
        echo '</div>';
    }

    /**
     * Fonction d'ajout au panier via AJAX
     * 
     * Traite les requêtes AJAX pour ajouter une excursion au panier WooCommerce
     * avec toutes les informations de réservation (date, participants, extras...)
     * 
     * Sécurité améliorée:
     * - Vérification du nonce pour prévenir les attaques CSRF
     * - Validation des données utilisateur
     * - Gestion des erreurs détaillée avec logs
     */
    function life_travel_excursion_add_to_cart() {
        // Vérification du nonce pour la sécurité
        if (!check_ajax_referer('life_travel_excursion_nonce', 'security', false)) {
            wp_send_json_error(array(
                'message' => __('Erreur de sécurité. Veuillez rafraîchir la page et réessayer.', 'life-travel-excursion'),
                'code' => 'security_error'
            ));
            exit;
        }
        
        // Récupération et validation des données
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $product = wc_get_product($product_id);
        
        if (!$product || 'excursion' !== $product->get_type()) {
            error_log("[Life Travel] Produit invalide ou non-excursion. ID: $product_id");
            wp_send_json_error(array(
                'message' => __('Produit invalide ou indisponible.', 'life-travel-excursion'),
                'code' => 'invalid_product'
            ));
            exit;
        }
        
        // Vérification de connexion utilisateur
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('Vous devez être connecté pour réserver cette excursion.', 'life-travel-excursion'),
                'code' => 'login_required',
                'login_url' => wp_login_url(get_permalink($product_id))
            ));
            exit;
        }
        
        try {
            // Récupération des données de réservation avec sanitization
            $participants = isset($_POST['participants']) ? max(1, absint($_POST['participants'])) : 1;
            $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
            $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
            $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
            $end_time = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';
            $extras = isset($_POST['extras']) ? array_map('sanitize_text_field', $_POST['extras']) : array();
            $activities = isset($_POST['activities']) ? array_map('sanitize_text_field', $_POST['activities']) : array();
            
            // Validation de la réservation
            $validation = life_travel_excursion_validate_booking($product_id, $participants, $start_date, $end_date);
            if (!$validation['success']) {
                error_log("[Life Travel] Validation échec: " . $validation['message']);
                wp_send_json_error(array(
                    'message' => $validation['message'],
                    'code' => 'validation_error'
                ));
                exit;
            }
            
            // Calcul du prix avec tous les paramètres
            $pricing_details = life_travel_excursion_get_pricing_details($product_id, $participants, $start_date, $end_date, $extras, $activities);
            
            // Création des données pour le panier
            $cart_item_data = array(
                'participants' => $participants,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'extras' => $extras,
                'activities' => $activities,
                // Clé unique pour éviter la fusion des articles similaires
                'unique_key' => md5(microtime() . rand()),
            );
            
            // Ajouter au panier
            $added = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
            
            if ($added) {
                // Log de succès pour débogage
                error_log("[Life Travel] Réservation réussie pour le produit $product_id (Participants: $participants, Date: $start_date)");
                
                wp_send_json_success(array(
                    'message' => __('Excursion ajoutée au panier !', 'life-travel-excursion'),
                    'cart_url' => wc_get_cart_url()
                ));
            } else {
                error_log("[Life Travel] Échec de l'ajout au panier pour le produit ID: $product_id");
                wp_send_json_error(array(
                    'message' => __('Une erreur est survenue lors de l\'ajout au panier. Veuillez réessayer.', 'life-travel-excursion'),
                    'code' => 'cart_error'
                ));
            }
        } catch (Exception $e) {
            error_log("[Life Travel Exception] " . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Une erreur est survenue lors de l\'ajout au panier. Veuillez réessayer.', 'life-travel-excursion'),
                'code' => 'exception',
                'details' => WP_DEBUG ? $e->getMessage() : ''
            ));
        }
    }

    /**
     * Valide une réservation d'excursion avec des contraintes améliorées
     * 
     * Vérifie de manière approfondie la validité d'une réservation selon:
     * - Limites de participants
     * - Disponibilité des places
     * - Délais de réservation
     * - Capacité des véhicules et contraintes
     * - Horaires et durées
     * - Géolocalisation et restrictions géographiques
     * 
     * @param int $product_id ID du produit excursion
     * @param int $participants Nombre de participants
     * @param string $start_date Date de début au format YYYY-MM-DD
     * @param string $end_date Date de fin au format YYYY-MM-DD
     * @return array Tableau avec clés 'success' (booléen) et 'message' (string)
     */
    function life_travel_excursion_validate_booking( $product_id, $participants, $start_date, $end_date ) {
        // Générer une clé de cache unique pour cette validation
        $cache_key = 'lt_validation_' . md5($product_id . '_' . $participants . '_' . $start_date . '_' . $end_date);
        $cached_result = get_transient($cache_key);
        
        // Retourner le résultat en cache si disponible et récent (moins de 5 minutes)
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Vérification du produit
        $product = wc_get_product( $product_id );
        if (!$product) {
            $result = array(
                'success' => false,
                'message' => __( 'Produit introuvable ou indisponible. Veuillez rafraîchir la page.', 'life-travel-excursion' ),
                'code' => 'product_not_found'
            );
            
            error_log("[Life Travel] Validation échec: produit introuvable ID=$product_id");
            set_transient($cache_key, $result, 2 * MINUTE_IN_SECONDS); // Mettre en cache pour 2 minutes
            return $result;
        }
        
        // ÉTAPE 1: Vérification des limites globales de participants
        $participant_limit = intval(get_post_meta( $product_id, '_participant_limit', true ));
        if ( $participant_limit > 0 && $participants > $participant_limit ) {
            error_log("[Life Travel] Validation échec: limite globale ($participants > $participant_limit)");
            return array(
                'success' => false,
                'message' => sprintf(__( 'Le nombre maximum de participants autorisé est de %d personnes.', 'life-travel-excursion' ), $participant_limit),
                'code' => 'global_limit_exceeded'
            );
        }
        
        // ÉTAPE 2: Vérification des places disponibles
        $remaining_seats = life_travel_excursion_get_remaining_seats( $product_id, $start_date, $end_date );
        if ( $participants > $remaining_seats ) {
            error_log("[Life Travel] Validation échec: places insuffisantes ($participants > $remaining_seats)");
            return array(
                'success' => false,
                'message' => sprintf( __( 'Il ne reste que %d places disponibles pour cette excursion.', 'life-travel-excursion' ), $remaining_seats ),
                'code' => 'insufficient_seats'
            );
        }
        
        // ÉTAPE 3: Vérification du délai de réservation
        $min_days_before = intval(get_post_meta( $product_id, '_min_days_before', true ));
        $current_date = new DateTime();
        try {
            $start_date_obj = new DateTime( $start_date );
        } catch ( Exception $e ) {
            error_log("[Life Travel] Validation échec: date invalide ($start_date)");
            return array(
                'success' => false,
                'message' => __( 'La date de début est invalide. Veuillez vérifier votre saisie.', 'life-travel-excursion' ),
                'code' => 'invalid_date_format'
            );
        }
        
        $interval = $current_date->diff( $start_date_obj )->days;
        if ( $min_days_before > 0 && $interval < $min_days_before ) {
            error_log("[Life Travel] Validation échec: délai insuffisant ($interval < $min_days_before jours)");
            return array(
                'success' => false,
                'message' => sprintf( __( 'Pour cette excursion, il faut réserver au moins %d jours à l\'avance.', 'life-travel-excursion' ), $min_days_before ),
                'code' => 'booking_too_late'
            );
        }
        
        // ÉTAPE 4: Vérification des capacités des véhicules
        $base_capacity = intval(get_post_meta( $product_id, '_base_capacity', true ));
        $min_additional = intval(get_post_meta( $product_id, '_min_additional_participants', true ));
        $max_vehicles = intval(get_post_meta( $product_id, '_max_vehicles', true ));
        $vehicle_cost = floatval(get_post_meta( $product_id, '_additional_vehicle_cost', true ));
        
        // Valeurs par défaut si non définies
        if (!$base_capacity) $base_capacity = $participant_limit > 0 ? $participant_limit : 10;
        if (!$min_additional) $min_additional = 1;
        if (!$max_vehicles) $max_vehicles = 1; // Par défaut, 1 véhicule maximum
        
        // Calcul du nombre de véhicules nécessaires
        $vehicles_needed = 1; // Premier véhicule
        $remaining_participants = max(0, $participants - $base_capacity);
        
        if ($remaining_participants > 0) {
            // Combien de véhicules supplémentaires sont nécessaires?
            $additional_vehicles = ceil($remaining_participants / $min_additional);
            $vehicles_needed += $additional_vehicles;
            
            // NOUVEAUTÉ: Vérification des participants additionnels (si moins que le minimum requis)
            if ($remaining_participants % $min_additional !== 0 && $vehicles_needed > 1) {
                $last_vehicle_participants = $remaining_participants % $min_additional;
                
                if ($last_vehicle_participants > 0 && $last_vehicle_participants < $min_additional) {
                    error_log("[Life Travel] Validation avertissement: participants additionnels insuffisants ($last_vehicle_participants < $min_additional)");
                    
                    return array(
                        'success' => false,
                        'message' => sprintf( __( 'Pour débloquer une unité additionnelle (ex. une pirogue supplémentaire), il faut au moins %d participants supplémentaires. Actuellement, vous avez %d participant(s) en plus. Ajoutez %d participant(s) supplémentaire(s) pour optimiser votre réservation.', 'life-travel-excursion' ), 
                                       $min_additional, 
                                       $last_vehicle_participants,
                                       $min_additional - $last_vehicle_participants),
                        'code' => 'inefficient_vehicle_allocation',
                        'missing_participants' => $min_additional - $last_vehicle_participants
                    );
                }
            }
        }
        
        // NOUVEAUTÉ: Vérification du maximum de véhicules disponibles
        if ($vehicles_needed > $max_vehicles) {
            $max_capacity = $base_capacity + ($max_vehicles - 1) * $min_additional;
            error_log("[Life Travel] Validation échec: véhicules insuffisants ($vehicles_needed > $max_vehicles)");
            
            return array(
                'success' => false,
                'message' => sprintf( __( 'Le nombre de participants (%d) dépasse notre capacité maximale de %d personnes avec %d %s. Veuillez réduire le nombre de participants ou nous contacter pour une réservation personnalisée.', 'life-travel-excursion' ), 
                               $participants, 
                               $max_capacity,
                               $max_vehicles, 
                               _n('véhicule', 'véhicules', $max_vehicles, 'life-travel-excursion')),
                'code' => 'vehicle_limit_exceeded',
                'max_capacity' => $max_capacity,
                'current_participants' => $participants
            );
        }
        
        // ÉTAPE 5: Vérification spécifique selon le type d'excursion (fixe ou flexible)
        $is_fixed_date = get_post_meta( $product_id, '_is_fixed_date', true );
        if ( 'yes' === $is_fixed_date ) {
            // Excursion à date fixe
            if ( empty( $_POST['start_time'] ) ) {
                return array(
                    'success' => false,
                    'message' => __( 'Veuillez sélectionner un créneau horaire pour cette excursion.', 'life-travel-excursion' ),
                    'code' => 'missing_timeslot'
                );
            }
            
            $fixed_start = get_post_meta( $product_id, '_fixed_start_time', true );
            $fixed_end   = get_post_meta( $product_id, '_fixed_end_time', true );
            $selected_time = $_POST['start_time'];
            
            if ( $selected_time < $fixed_start || $selected_time > $fixed_end ) {
                return array(
                    'success' => false,
                    'message' => sprintf(__( 'Le créneau horaire sélectionné n\'est pas disponible. Veuillez choisir entre %s et %s.', 'life-travel-excursion' ), $fixed_start, $fixed_end),
                    'code' => 'invalid_timeslot'
                );
            }
        } else {
            // Excursion à date flexible
            $max_duration_days = intval(get_post_meta( $product_id, '_max_duration_days', true ));
            
            if ( $max_duration_days === 1 ) {
                // Excursion d'une journée
                if ( empty($_POST['start_time']) || empty($_POST['end_time']) ) {
                    return array(
                        'success' => false,
                        'message' => __( 'Veuillez renseigner l\'heure de début et l\'heure de fin pour cette excursion.', 'life-travel-excursion' ),
                        'code' => 'missing_times'
                    );
                }
                
                $start_time = DateTime::createFromFormat('H:i', $_POST['start_time']);
                $end_time   = DateTime::createFromFormat('H:i', $_POST['end_time']);
                
                if ( ! $start_time || ! $end_time ) {
                    return array(
                        'success' => false,
                        'message' => __( 'Les heures renseignées ne sont pas valides. Veuillez vérifier votre saisie.', 'life-travel-excursion' ),
                        'code' => 'invalid_time_format'
                    );
                }
                
                $duration = (int)$end_time->diff($start_time)->format('%h');
                $min_duration = intval(get_post_meta( $product_id, '_min_duration_hours', true ));
                
                if ( $duration < $min_duration ) {
                    return array(
                        'success' => false,
                        'message' => sprintf( __( 'L\'excursion doit durer au moins %d heures. Veuillez ajuster les horaires.', 'life-travel-excursion' ), $min_duration ),
                        'code' => 'too_short_duration'
                    );
                }
            } else {
                // Excursion multi-jours
                try {
                    $end_date_obj = new DateTime( $end_date );
                    $duration_days = $end_date_obj->diff($start_date_obj)->days + 1;
                    
                    if ($duration_days > $max_duration_days) {
                        return array(
                            'success' => false,
                            'message' => sprintf( __( 'Cette excursion ne peut pas durer plus de %d jours. Veuillez ajuster les dates.', 'life-travel-excursion' ), $max_duration_days ),
                            'code' => 'too_long_duration'
                        );
                    }
                } catch (Exception $e) {
                    return array(
                        'success' => false,
                        'message' => __( 'Les dates sélectionnées sont invalides. Veuillez vérifier votre saisie.', 'life-travel-excursion' ),
                        'code' => 'invalid_date_range'
                    );
                }
            }
        }
        
        // ÉTAPE 6: Vérification des excursions simultanées
        if ( ! life_travel_excursion_check_simultaneous_excursions( $start_date, $end_date, get_option( 'max_simultaneous_excursions', 1 ) ) ) {
            return array(
                'success' => false,
                'message' => __( 'Le nombre maximal d\'excursions simultanées a été atteint pour les dates sélectionnées. Veuillez choisir une autre date.', 'life-travel-excursion' ),
                'code' => 'max_simultaneous_reached'
            );
        }
        
        // Tout est validé
        return array(
            'success' => true,
            'message' => __( 'Réservation validée.', 'life-travel-excursion' ),
            'vehicles_needed' => $vehicles_needed,
            'participants' => $participants
        );
    }

    /**
     * Calcule le nombre de places restantes pour une excursion à une date donnée
     * Fonction améliorée avec cache pour optimiser les performances
     * 
     * @param int $product_id ID du produit excursion
     * @param string $start_date Date de début au format YYYY-MM-DD
     * @param string $end_date Date de fin au format YYYY-MM-DD
     * @return int Nombre de places restantes
     */
    function life_travel_excursion_get_remaining_seats( $product_id, $start_date, $end_date ) {
        // Générer une clé de cache unique pour cette requête
        $cache_key = 'lt_seats_' . md5($product_id . '_' . $start_date . '_' . $end_date);
        $cached_seats = get_transient($cache_key);
        
        // Si le résultat est en cache et valide, l'utiliser (cache de 15 minutes)
        if ($cached_seats !== false) {
            return $cached_seats;
        }
        
        // Limite maximale de participants pour ce produit
        $max_participants = intval(get_post_meta($product_id, '_participant_limit', true));
        if (!$max_participants) {
            $max_participants = 10; // Valeur par défaut
        }
        
        // Dans un environnement réel, nous calculerions le nombre de participants déjà inscrits
        // en vérifiant les commandes existantes pour cette date
        $booked_seats = 0; // Pour le prototype, on considère qu'aucune place n'est réservée
        
        // Si nous avons un système de base de données avec les commandes:
        // 1. Récupérer toutes les commandes valides pour cette date
        // 2. Calculer le nombre total de participants déjà réservés
        
        $remaining_seats = max(0, $max_participants - $booked_seats);
        
        // Mettre en cache le résultat pour 15 minutes
        set_transient($cache_key, $remaining_seats, 15 * MINUTE_IN_SECONDS);
        
        return $remaining_seats;
    }

    function life_travel_excursion_check_simultaneous_excursions( $start_date, $end_date, $max_simultaneous ) {
        global $wpdb;
        $query = "
            SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items AS items
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS meta_start ON items.order_item_id = meta_start.order_item_id AND meta_start.meta_key = '_start_date'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS meta_end ON items.order_item_id = meta_end.order_item_id AND meta_end.meta_key = '_end_date'
            WHERE items.order_item_type = 'line_item'
            AND (
                (meta_start.meta_value <= %s AND meta_end.meta_value >= %s)
                OR (meta_start.meta_value <= %s AND meta_end.meta_value >= %s)
            )
        ";
        $count = $wpdb->get_var( $wpdb->prepare( $query, $end_date, $start_date, $end_date, $start_date ) );
        return ( $count < $max_simultaneous );
    }

    /**
     * Calcule le prix détaillé d'une excursion en tenant compte de tous les paramètres
     * 
     * Calcule le prix total en considérant:
     * - Prix de base selon le nombre de participants (tarifs par paliers)
     * - Ajustements de prix selon créneaux horaires
     * - Durée de l'excursion (en jours)
     * - Extras sélectionnés
     * - Activités choisies
     * - Véhicules supplémentaires nécessaires selon la capacité
     * 
     * @param int $product_id ID du produit excursion
     * @param int $participants Nombre de participants
     * @param string $start_date Date de début au format YYYY-MM-DD
     * @param string $end_date Date de fin au format YYYY-MM-DD
     * @param array $extras Tableau des extras sélectionnés
     * @param array $activities Tableau des activités sélectionnées
     * @return array Détails du prix avec décomposition
     */
    function life_travel_excursion_get_pricing_details($product_id, $participants, $start_date, $end_date, $extras = [], $activities = []) {
        // Générer une clé de cache unique pour cette requête
        $cache_key = 'lt_price_' . md5($product_id . '_' . $participants . '_' . $start_date . '_' . $end_date . '_' . serialize($extras) . '_' . serialize($activities));
        $cached_result = get_transient($cache_key);
        
        // Retourner le résultat mis en cache si disponible
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Initialisation
        $product = wc_get_product($product_id);
        if (!$product) {
            error_log("[Life Travel] Calcul de prix impossible: produit introuvable ID=$product_id");
            return array(
                'error' => true,
                'message' => __('Produit introuvable', 'life-travel-excursion')
            );
        }
        
        // ------ ÉTAPE 1: Prix de base par personne ------
        $base_price = floatval($product->get_price());
        $price_per_person = $base_price;
        
        // Vérifier les paliers de prix
        $pricing_tiers = get_post_meta($product_id, '_pricing_tiers', true);
        if ($pricing_tiers) {
            $tiers = explode("\n", $pricing_tiers);
            foreach ($tiers as $tier) {
                $tier_parts = explode('|', $tier);
                if (count($tier_parts) !== 3) continue;
                
                list($min, $max, $price) = array_map('trim', $tier_parts);
                if (is_numeric($min) && is_numeric($max) && is_numeric($price)) {
                    if ($participants >= $min && $participants <= $max) {
                        $price_per_person = floatval($price);
                        break;
                    }
                }
            }
        }
        
        // ------ ÉTAPE 2: Ajustements de prix selon créneaux horaires ------
        $time_slot_adjustment = 0;
        $time_slot_rules = get_post_meta($product_id, '_time_slot_pricing', true);
        $selected_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
        
        if ($time_slot_rules && !empty($selected_time)) {
            $rules = explode("\n", $time_slot_rules);
            foreach ($rules as $rule) {
                $parts = explode('|', $rule);
                if (count($parts) == 2) {
                    list($time_range, $adjustment) = array_map('trim', $parts);
                    if (strpos($time_range, '-') !== false) {
                        list($range_start, $range_end) = explode('-', $time_range);
                        if ($selected_time >= $range_start && $selected_time <= $range_end) {
                            $time_slot_adjustment = floatval($adjustment);
                            break;
                        }
                    }
                }
            }
        }
        
        // Appliquer l'ajustement de créneau horaire
        $price_per_person += $time_slot_adjustment;
        
        // ------ ÉTAPE 3: Calcul de la durée en jours ------
        $days = 1;
        if ($start_date && $end_date) {
            try {
                $start = new DateTime($start_date);
                $end = new DateTime($end_date);
                $interval = $start->diff($end);
                $days = $interval->days + 1;
            } catch (Exception $e) {
                error_log("[Life Travel] Erreur calcul durée: " . $e->getMessage());
                $days = 1;
            }
        }
        
        // ------ ÉTAPE 4: Calcul du prix des extras ------
        $extras_price = 0;
        $extras_breakdown = [];
        
        if ($extras && is_array($extras)) {
            $extras_list = get_post_meta($product_id, '_extras', true);
            $extras_array = explode("\n", $extras_list);
            
            foreach ($extras_array as $extra_line) {
                $extra_parts = explode('|', $extra_line);
                if (count($extra_parts) !== 4) continue;
                
                list($extra_name, $extra_price, $extra_type, $extra_multiplier) = array_map('trim', $extra_parts);
                $extra_key = sanitize_title($extra_name);
                
                if (isset($extras[$extra_key])) {
                    $quantity = $extras[$extra_key];
                    $extra_total_price = floatval($extra_price);
                    
                    // Gérer le type d'extra (Booléen ou Quantité)
                    if (strtolower($extra_type) === 'quantité') {
                        $quantity = intval($quantity);
                        $extra_total_price *= $quantity;
                    }
                    
                    // Appliquer les multiplicateurs
                    if (strtolower($extra_multiplier) === 'jours') {
                        $extra_total_price *= $days;
                    } elseif (strtolower($extra_multiplier) === 'participants') {
                        $extra_total_price *= $participants;
                    } elseif (strtolower($extra_multiplier) === 'jours_participants') {
                        $extra_total_price *= $participants * $days;
                    }
                    
                    $extras_price += $extra_total_price;
                    $extras_breakdown[] = array(
                        'name' => $extra_name,
                        'price' => $extra_total_price,
                        'quantity' => $quantity,
                        'multiplier' => $extra_multiplier
                    );
                }
            }
        }
        
        // ------ ÉTAPE 5: Calcul du prix des activités ------
        $activities_price = 0;
        $activities_breakdown = [];
        
        if ($activities && is_array($activities)) {
            $activities_list = get_post_meta($product_id, '_activities', true);
            $activities_array = explode("\n", $activities_list);
            
            foreach ($activities_array as $activity_line) {
                $activity_parts = explode('|', $activity_line);
                if (count($activity_parts) !== 3) continue;
                
                list($activity_name, $activity_price, $max_duration) = array_map('trim', $activity_parts);
                $activity_key = sanitize_title($activity_name);
                
                if (isset($activities[$activity_key])) {
                    $selected_days = intval($activities[$activity_key]);
                    if ($selected_days > intval($max_duration)) {
                        $selected_days = intval($max_duration);
                    }
                    
                    $activity_total_price = floatval($activity_price) * $selected_days;
                    $activities_price += $activity_total_price;
                    
                    $activities_breakdown[] = array(
                        'name' => $activity_name,
                        'price' => $activity_total_price,
                        'days' => $selected_days,
                        'price_per_day' => floatval($activity_price)
                    );
                }
            }
        }
        
        // ------ ÉTAPE 6: Calcul du coût des véhicules supplémentaires ------
        $additional_cost = 0;
        $vehicles_needed = 1;
        $vehicles_breakdown = [];
        
        // Récupérer les paramètres de véhicules
        $base_capacity = intval(get_post_meta($product_id, '_base_capacity', true));
        $min_additional = intval(get_post_meta($product_id, '_min_additional_participants', true));
        $max_vehicles = intval(get_post_meta($product_id, '_max_vehicles', true));
        $vehicle_cost = floatval(get_post_meta($product_id, '_additional_vehicle_cost', true));
        
        // Valeurs par défaut si non définies
        if (!$base_capacity) $base_capacity = $participant_limit > 0 ? $participant_limit : 10;
        if (!$min_additional) $min_additional = 1;
        if (!$max_vehicles) $max_vehicles = 1; // Par défaut, 1 véhicule maximum
        
        // NOUVEAUTÉ: Calcul des véhicules nécessaires et du coût associé
        if ($base_capacity > 0 && $participants > $base_capacity) {
            $remaining_participants = $participants - $base_capacity;
            $additional_vehicles = ceil($remaining_participants / $min_additional);
            $vehicles_needed = 1 + $additional_vehicles;
            
            // Limiter au nombre maximum de véhicules disponibles
            if ($vehicles_needed > $max_vehicles) {
                $vehicles_needed = $max_vehicles;
            }
            
            // Calcul du coût des véhicules supplémentaires
            if ($vehicles_needed > 1) {
                $additional_cost = ($vehicles_needed - 1) * $vehicle_cost;
                
                // Détail des véhicules pour la facturation
                $vehicles_breakdown = array(
                    'base_vehicle' => 1,
                    'additional_vehicles' => $vehicles_needed - 1,
                    'total_vehicles' => $vehicles_needed,
                    'cost_per_vehicle' => $vehicle_cost,
                    'total_additional_cost' => $additional_cost,
                    'base_capacity' => $base_capacity,
                    'min_additional' => $min_additional,
                    'max_capacity' => $base_capacity + ($max_vehicles - 1) * $min_additional,
                );
            }
        }
        
        // ------ ÉTAPE 7: Calcul du prix total ------
        $subtotal = $price_per_person * $participants * $days;
        $total_price = $subtotal + $extras_price + $activities_price + $additional_cost;
        
        // Conversion du prix selon la devise et le format
        $total_price = life_travel_excursion_convert_price($total_price);
        
        // Création du tableau de résultat avec détail
        $result = array(
            'price_per_person' => life_travel_excursion_convert_price($price_per_person),
            'subtotal' => life_travel_excursion_convert_price($subtotal),
            'extras_price' => life_travel_excursion_convert_price($extras_price),
            'extras_breakdown' => $extras_breakdown,
            'activities_price' => life_travel_excursion_convert_price($activities_price),
            'activities_breakdown' => $activities_breakdown,
            'additional_cost' => life_travel_excursion_convert_price($additional_cost),
            'vehicles_breakdown' => $vehicles_breakdown,
            'total_price' => $total_price,
            'participants' => $participants,
            'days' => $days,
            'available_seats' => life_travel_excursion_get_remaining_seats($product_id, $start_date, $end_date),
            'vehicles_needed' => $vehicles_needed,
            'max_vehicles' => $max_vehicles,
            'calculation_time' => current_time('mysql')
        );
        
        // Mettre en cache le résultat pour 5 minutes
        set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
        
        return $result;
    }


    /**
     * Calcule le prix détaillé d'une excursion en tenant compte de tous les paramètres
     * 
     * Calcule le prix total en considérant:
     * - Prix de base selon le nombre de participants (tarifs par paliers)
     * - Ajustements de prix selon créneaux horaires
     * - Durée de l'excursion (en jours)
     * - Extras sélectionnés
     * - Activités choisies
     * - Véhicules supplémentaires nécessaires selon la capacité
     * 
     * @param int $product_id ID du produit excursion
     * @param int $participants Nombre de participants
     * @param string $start_date Date de début au format YYYY-MM-DD
     * @param string $end_date Date de fin au format YYYY-MM-DD
     * @param array $extras Tableau des extras sélectionnés
     * @param array $activities Tableau des activités sélectionnées
     * @return array Détails du prix avec décomposition
     */

    function life_travel_excursion_calculate_price_handler() {
        // Vérification du nonce pour sécurité
        check_ajax_referer('life_travel_excursion_nonce', 'security');
        
        try {
            // Récupération et validation des données
            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            $product = wc_get_product($product_id);
            
            if (!$product || 'excursion' !== $product->get_type()) {
                error_log("[Life Travel] Calcul de prix: produit invalide. ID: $product_id");
                wp_send_json_error(array(
                    'message' => __('Ce produit n\'est pas une excursion valide.', 'life-travel-excursion'),
                    'code' => 'invalid_product'
                ));
                exit;
            }
            
            // Sanitisation des données d'entrée
            $participants = isset($_POST['participants']) ? max(1, absint($_POST['participants'])) : 1;
            $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
            $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : $start_date;
            $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
            $end_time = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';
            
            // Sanitisation des extras et activités (tableaux)
            $extras = array();
            if (isset($_POST['extras']) && is_array($_POST['extras'])) {
                foreach ($_POST['extras'] as $key => $value) {
                    $extras[sanitize_key($key)] = sanitize_text_field($value);
                }
            }
            
            $activities = array();
            if (isset($_POST['activities']) && is_array($_POST['activities'])) {
                foreach ($_POST['activities'] as $key => $value) {
                    $activities[sanitize_key($key)] = sanitize_text_field($value);
                }
            }
            
            // Début du tracking de performance
            $start_time_calc = microtime(true);
            
            // Calcul détaillé du prix
            $pricing = life_travel_excursion_get_pricing_details($product_id, $participants, $start_date, $end_date, $extras, $activities);
            
            // Vérifier si une erreur s'est produite
            if (isset($pricing['error']) && $pricing['error']) {
                wp_send_json_error(array(
                    'message' => $pricing['message'],
                    'code' => 'pricing_error'
                ));
                exit;
            }
            
            // Fin du tracking de performance
            $calc_time = round((microtime(true) - $start_time_calc) * 1000, 2); // en ms
            
            // Préparer les données de véhicules pour l'affichage
            $vehicles_info = '';
            if (isset($pricing['vehicles_needed']) && $pricing['vehicles_needed'] > 1) {
                $vehicles_breakdown = isset($pricing['vehicles_breakdown']) ? $pricing['vehicles_breakdown'] : array();
                $additional_vehicles = isset($vehicles_breakdown['additional_vehicles']) ? $vehicles_breakdown['additional_vehicles'] : 0;
                
                $vehicles_info = sprintf(
                    __('Cette réservation nécessite %d %s (%d %s principal + %d %s)', 'life-travel-excursion'),
                    $pricing['vehicles_needed'],
                    _n('véhicule', 'véhicules', $pricing['vehicles_needed'], 'life-travel-excursion'),
                    1,
                    __('véhicule', 'life-travel-excursion'),
                    $additional_vehicles,
                    _n('supplémentaire', 'supplémentaires', $additional_vehicles, 'life-travel-excursion')
                );
            }
            
            // Créer la réponse détaillée pour le client
            $response = array(
                // Prix formatés pour l'affichage
                'price_per_person_html' => wc_price($pricing['price_per_person']),
                'subtotal_html'         => wc_price($pricing['subtotal']), // Nouveau
                'extras_price_html'     => wc_price($pricing['extras_price']),
                'activities_price_html' => wc_price($pricing['activities_price']),
                'additional_cost_html'  => wc_price($pricing['additional_cost']),
                'total_price_html'      => wc_price($pricing['total_price']),
                
                // Informations sur la réservation
                'participants'          => $pricing['participants'],
                'days'                  => $pricing['days'],
                'available_seats'       => $pricing['available_seats'],
                
                // Détails des véhicules
                'vehicles_needed'       => $pricing['vehicles_needed'],
                'max_vehicles'          => $pricing['max_vehicles'],
                'vehicles_info'         => $vehicles_info,
                'vehicles_breakdown'    => $pricing['vehicles_breakdown'],
                
                // Détails supplémentaires
                'extras_breakdown'      => $pricing['extras_breakdown'],
                'activities_breakdown'  => $pricing['activities_breakdown'],
                'calculation_time_ms'   => $calc_time,
            );
            
            // Ajouter des infos pour le débogage si WP_DEBUG est activé
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $response['debug_info'] = array(
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'request_time' => current_time('mysql'),
                    'cache_hit' => isset($pricing['cache_hit']) ? $pricing['cache_hit'] : false,
                );
            }
            
            // Envoyer la réponse formatée
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            error_log("[Life Travel] Exception calcul prix: " . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Une erreur est survenue lors du calcul du prix. Veuillez réessayer.', 'life-travel-excursion'),
                'code' => 'exception',
                'details' => (defined('WP_DEBUG') && WP_DEBUG) ? $e->getMessage() : ''
            ));
        }
    }

    /**
     * Améliore l'affichage des détails d'une excursion dans le panier
     *
     * Affiche toutes les informations importantes de réservation dans le panier,
     * y compris les détails des véhicules, participants, dates, heures et options.
     * 
     * @param array $item_data Données existantes de l'article
     * @param array $cart_item Élément du panier
     * @return array Données enrichies pour l'affichage
     */
    function life_travel_excursion_display_cart_item_data($item_data, $cart_item) {
        // Vérifier si c'est bien une excursion
        if (!isset($cart_item['participants'])) {
            return $item_data;
        }
        
        // Détails des participants
        $item_data[] = array(
            'key'   => __('Participants', 'life-travel-excursion'),
            'value' => intval($cart_item['participants']),
            'display' => '<strong>' . __('Participants', 'life-travel-excursion') . '</strong>: ' . intval($cart_item['participants']),
        );
        
        // Détails des véhicules (NOUVEAUTÉ)
        if (isset($cart_item['product_id'])) {
            $product_id = $cart_item['product_id'];
            $participants = intval($cart_item['participants']);
            
            // Récupérer les paramètres de véhicules
            $base_capacity = intval(get_post_meta($product_id, '_base_capacity', true));
            $min_additional = intval(get_post_meta($product_id, '_min_additional_participants', true));
            $max_vehicles = intval(get_post_meta($product_id, '_max_vehicles', true));
            
            // Utiliser des valeurs par défaut si non définies
            if (!$base_capacity) $base_capacity = intval(get_post_meta($product_id, '_participant_limit', true)) ?: 10;
            if (!$min_additional) $min_additional = 1;
            if (!$max_vehicles) $max_vehicles = 1;
            
            // Calculer les véhicules nécessaires
            $vehicles_needed = 1; // Premier véhicule
            if ($base_capacity > 0 && $participants > $base_capacity) {
                $remaining_participants = $participants - $base_capacity;
                $additional_vehicles = ceil($remaining_participants / $min_additional);
                $vehicles_needed = 1 + $additional_vehicles;
                
                // Limiter au maximum de véhicules disponibles
                if ($vehicles_needed > $max_vehicles) {
                    $vehicles_needed = $max_vehicles;
                }
            }
            
            // Ajouter l'information sur les véhicules si plus d'un véhicule
            if ($vehicles_needed > 1) {
                $vehicles_info = sprintf(
                    __('%d %s (%d principal + %d %s)', 'life-travel-excursion'),
                    $vehicles_needed,
                    _n('véhicule', 'véhicules', $vehicles_needed, 'life-travel-excursion'),
                    1,
                    $vehicles_needed - 1,
                    _n('supplémentaire', 'supplémentaires', $vehicles_needed - 1, 'life-travel-excursion')
                );
                
                $item_data[] = array(
                    'key'   => __('Véhicules', 'life-travel-excursion'),
                    'value' => $vehicles_info,
                    'display' => '<strong>' . __('Véhicules', 'life-travel-excursion') . '</strong>: ' . $vehicles_info,
                );
            }
        }
        
        // Détails des dates
        if (isset($cart_item['start_date'])) {
            // Formater la date pour l'affichage
            $formatted_date = date_i18n(get_option('date_format'), strtotime($cart_item['start_date']));
            
            $item_data[] = array(
                'key'   => __('Date de début', 'life-travel-excursion'),
                'value' => $formatted_date,
                'display' => '<strong>' . __('Date de début', 'life-travel-excursion') . '</strong>: ' . $formatted_date,
            );
        }
        
        if (isset($cart_item['end_date']) && $cart_item['end_date'] !== $cart_item['start_date']) {
            $formatted_date = date_i18n(get_option('date_format'), strtotime($cart_item['end_date']));
            
            $item_data[] = array(
                'key'   => __('Date de fin', 'life-travel-excursion'),
                'value' => $formatted_date,
                'display' => '<strong>' . __('Date de fin', 'life-travel-excursion') . '</strong>: ' . $formatted_date,
            );
            
            // Calculer la durée
            try {
                $start = new DateTime($cart_item['start_date']);
                $end = new DateTime($cart_item['end_date']);
                $interval = $start->diff($end);
                $days = $interval->days + 1;
                
                if ($days > 1) {
                    $item_data[] = array(
                        'key'   => __('Durée', 'life-travel-excursion'),
                        'value' => sprintf(_n('%d jour', '%d jours', $days, 'life-travel-excursion'), $days),
                        'display' => '<strong>' . __('Durée', 'life-travel-excursion') . '</strong>: ' . 
                                      sprintf(_n('%d jour', '%d jours', $days, 'life-travel-excursion'), $days),
                    );
                }
            } catch (Exception $e) {
                // Silencieusement ignorer les erreurs de calcul de date
            }
        }
        
        // Détails des heures
        if (isset($cart_item['start_time']) && !empty($cart_item['start_time'])) {            
            $item_data[] = array(
                'key'   => __('Heure de début', 'life-travel-excursion'),
                'value' => esc_html($cart_item['start_time']),
                'display' => '<strong>' . __('Heure de début', 'life-travel-excursion') . '</strong>: ' . 
                           esc_html($cart_item['start_time']),
            );
        }
        
        if (isset($cart_item['end_time']) && !empty($cart_item['end_time'])) {
            $item_data[] = array(
                'key'   => __('Heure de fin', 'life-travel-excursion'),
                'value' => esc_html($cart_item['end_time']),
                'display' => '<strong>' . __('Heure de fin', 'life-travel-excursion') . '</strong>: ' . 
                           esc_html($cart_item['end_time']),
            );
            
            // Calculer la durée en heures si disponible
            if (isset($cart_item['start_time']) && !empty($cart_item['start_time'])) {
                try {
                    $start_time = DateTime::createFromFormat('H:i', $cart_item['start_time']);
                    $end_time = DateTime::createFromFormat('H:i', $cart_item['end_time']);
                    
                    if ($start_time && $end_time) {
                        $duration = (int)$end_time->diff($start_time)->format('%h');
                        
                        if ($duration > 0) {
                            $item_data[] = array(
                                'key'   => __('Durée', 'life-travel-excursion'),
                                'value' => sprintf(_n('%d heure', '%d heures', $duration, 'life-travel-excursion'), $duration),
                                'display' => '<strong>' . __('Durée', 'life-travel-excursion') . '</strong>: ' . 
                                              sprintf(_n('%d heure', '%d heures', $duration, 'life-travel-excursion'), $duration),
                            );
                        }
                    }
                } catch (Exception $e) {
                    // Ignorer silencieusement les erreurs de format d'heure
                }
            }
        }
        
        // Détails des extras
        if (isset($cart_item['extras']) && !empty($cart_item['extras'])) {
            $extras_display = '';
            $extras_list = [];
            
            // Récupérer la liste complète des extras du produit pour avoir les noms complets
            if (isset($cart_item['product_id'])) {
                $extras_config = get_post_meta($cart_item['product_id'], '_extras', true);
                $extras_array = explode("\n", $extras_config);
                $extras_lookup = [];
                
                foreach ($extras_array as $extra_line) {
                    $extra_parts = explode('|', $extra_line);
                    if (count($extra_parts) >= 1) {
                        $name = trim($extra_parts[0]);
                        $key = sanitize_title($name);
                        $extras_lookup[$key] = $name;
                    }
                }
                
                // Générer l'affichage amélioré des extras
                foreach ($cart_item['extras'] as $key => $value) {
                    $display_name = isset($extras_lookup[$key]) ? $extras_lookup[$key] : ucfirst(str_replace('_', ' ', $key));
                    $extras_list[] = $display_name . ': ' . sanitize_text_field($value);
                }
            } else {
                // Fallback si product_id n'est pas disponible
                foreach ($cart_item['extras'] as $key => $value) {
                    $extras_list[] = ucfirst(str_replace('_', ' ', sanitize_text_field($key))) . ': ' . sanitize_text_field($value);
                }
            }
            
            $extras_display = implode(', ', $extras_list);
            
            $item_data[] = array(
                'key'   => __('Extras', 'life-travel-excursion'),
                'value' => esc_html($extras_display),
                'display' => '<strong>' . __('Extras', 'life-travel-excursion') . '</strong>:<br>' . 
                           esc_html($extras_display),
            );
        }
        
        // Détails des activités
        if (isset($cart_item['activities']) && !empty($cart_item['activities'])) {
            $activities_list = [];
            
            // Récupérer la liste complète des activités du produit pour avoir les noms complets
            if (isset($cart_item['product_id'])) {
                $activities_config = get_post_meta($cart_item['product_id'], '_activities', true);
                $activities_array = explode("\n", $activities_config);
                $activities_lookup = [];
                
                foreach ($activities_array as $activity_line) {
                    $activity_parts = explode('|', $activity_line);
                    if (count($activity_parts) >= 1) {
                        $name = trim($activity_parts[0]);
                        $key = sanitize_title($name);
                        $activities_lookup[$key] = $name;
                    }
                }
                
                // Générer l'affichage amélioré des activités
                foreach ($cart_item['activities'] as $key => $value) {
                    $display_name = isset($activities_lookup[$key]) ? $activities_lookup[$key] : ucfirst(str_replace('_', ' ', $key));
                    $activities_list[] = $display_name . ': ' . sanitize_text_field($value) . ' ' . __('jour(s)', 'life-travel-excursion');
                }
            } else {
                // Fallback si product_id n'est pas disponible
                foreach ($cart_item['activities'] as $key => $value) {
                    $activities_list[] = ucfirst(str_replace('_', ' ', sanitize_text_field($key))) . ': ' . 
                                         sanitize_text_field($value) . ' ' . __('jour(s)', 'life-travel-excursion');
                }
            }
            
            $activities_display = implode(', ', $activities_list);
            
            $item_data[] = array(
                'key'   => __('Activités', 'life-travel-excursion'),
                'value' => esc_html($activities_display),
                'display' => '<strong>' . __('Activités', 'life-travel-excursion') . '</strong>:<br>' . 
                           esc_html($activities_display),
            );
        }
        
        return $item_data;
    }

    /**
     * Ajoute les métadonnées de réservation d'excursion aux articles des commandes
     *
     * Enrichit les articles de commande avec toutes les informations importantes 
     * de réservation, y compris les véhicules, participants, dates et options.
     * 
     * @param WC_Order_Item $item Élément de commande
     * @param string $cart_item_key Clé de l'article dans le panier
     * @param array $values Valeurs de l'article du panier
     * @param WC_Order $order Commande
     */
    function life_travel_excursion_add_order_item_meta($item, $cart_item_key, $values, $order) {
        // Vérifier si c'est bien une excursion
        if (!isset($values['participants'])) {
            return;
        }
        
        // Générer un ID unique pour la réservation
        $booking_id = 'EXC-' . date('Ymd') . '-' . substr(uniqid(), -6);
        $item->add_meta_data('_booking_id', $booking_id, true);
        $item->add_meta_data(__('Référence', 'life-travel-excursion'), $booking_id, true);
        
        // Détails des participants
        $item->add_meta_data(__('Participants', 'life-travel-excursion'), intval($values['participants']), true);
        
        // Détails des véhicules (NOUVEAUTÉ)
        if (isset($values['product_id'])) {
            $product_id = $values['product_id'];
            $participants = intval($values['participants']);
            
            // Récupérer les paramètres de véhicules
            $base_capacity = intval(get_post_meta($product_id, '_base_capacity', true));
            $min_additional = intval(get_post_meta($product_id, '_min_additional_participants', true));
            $max_vehicles = intval(get_post_meta($product_id, '_max_vehicles', true));
            $vehicle_cost = floatval(get_post_meta($product_id, '_additional_vehicle_cost', true));
            
            // Valeurs par défaut si non définies
            if (!$base_capacity) $base_capacity = intval(get_post_meta($product_id, '_participant_limit', true)) ?: 10;
            if (!$min_additional) $min_additional = 1;
            if (!$max_vehicles) $max_vehicles = 1;
            
            // Calculer les véhicules nécessaires
            $vehicles_needed = 1; // Premier véhicule
            if ($base_capacity > 0 && $participants > $base_capacity) {
                $remaining_participants = $participants - $base_capacity;
                $additional_vehicles = ceil($remaining_participants / $min_additional);
                $vehicles_needed = 1 + $additional_vehicles;
                
                // Limiter au maximum de véhicules disponibles
                if ($vehicles_needed > $max_vehicles) {
                    $vehicles_needed = $max_vehicles;
                }
            }
            
            // Stocker les informations de véhicules pour l'administration
            $item->add_meta_data('_vehicles_needed', $vehicles_needed, true);
            $item->add_meta_data('_base_capacity', $base_capacity, true);
            $item->add_meta_data('_additional_vehicle_cost', $vehicle_cost, true);
            
            // Ajouter l'information visible sur les véhicules si plus d'un véhicule
            if ($vehicles_needed > 1) {
                $vehicles_info = sprintf(
                    __('%d %s (%d principal + %d %s)', 'life-travel-excursion'),
                    $vehicles_needed,
                    _n('véhicule', 'véhicules', $vehicles_needed, 'life-travel-excursion'),
                    1,
                    $vehicles_needed - 1,
                    _n('supplémentaire', 'supplémentaires', $vehicles_needed - 1, 'life-travel-excursion')
                );
                
                $item->add_meta_data(__('Véhicules', 'life-travel-excursion'), $vehicles_info, true);
                
                // Ajouter le coût des véhicules supplémentaires si applicable
                if ($vehicle_cost > 0) {
                    $additional_cost = ($vehicles_needed - 1) * $vehicle_cost;
                    $item->add_meta_data('_additional_vehicles_cost', $additional_cost, true);
                    $item->add_meta_data(__('Coût des véhicules supplémentaires', 'life-travel-excursion'), wc_price($additional_cost), true);
                }
            }
        }
        
        // Détails des dates 
        if (isset($values['start_date'])) {
            // Format ISO pour les dates en métadonnées cachées
            $item->add_meta_data('_start_date', sanitize_text_field($values['start_date']), true);
            
            // Format local pour l'affichage
            $formatted_date = date_i18n(get_option('date_format'), strtotime($values['start_date']));
            $item->add_meta_data(__('Date de début', 'life-travel-excursion'), $formatted_date, true);
        }
        
        if (isset($values['end_date']) && $values['end_date'] !== $values['start_date']) {
            // Format ISO pour les dates en métadonnées cachées
            $item->add_meta_data('_end_date', sanitize_text_field($values['end_date']), true);
            
            // Ne pas dupliquer l'affichage si c'est la même date
            $formatted_date = date_i18n(get_option('date_format'), strtotime($values['end_date']));
            $item->add_meta_data(__('Date de fin', 'life-travel-excursion'), $formatted_date, true);
            
            // Calculer la durée
            try {
                $start = new DateTime($values['start_date']);
                $end = new DateTime($values['end_date']);
                $interval = $start->diff($end);
                $days = $interval->days + 1;
                
                if ($days > 1) {
                    $item->add_meta_data('_duration_days', $days, true);
                    $item->add_meta_data(__('Durée', 'life-travel-excursion'), 
                                          sprintf(_n('%d jour', '%d jours', $days, 'life-travel-excursion'), $days), 
                                          true);
                }
            } catch (Exception $e) {
                // Ignorer silencieusement les erreurs de date
            }
        }
        
        // Détails des heures
        if (isset($values['start_time']) && !empty($values['start_time'])) {
            $item->add_meta_data('_start_time', sanitize_text_field($values['start_time']), true);
            $item->add_meta_data(__('Heure de début', 'life-travel-excursion'), 
                              sanitize_text_field($values['start_time']), 
                              true);
        }
        
        if (isset($values['end_time']) && !empty($values['end_time'])) {
            $item->add_meta_data('_end_time', sanitize_text_field($values['end_time']), true);
            $item->add_meta_data(__('Heure de fin', 'life-travel-excursion'), 
                              sanitize_text_field($values['end_time']), 
                              true);
            
            // Calculer la durée en heures
            if (isset($values['start_time']) && !empty($values['start_time'])) {
                try {
                    $start_time = DateTime::createFromFormat('H:i', $values['start_time']);
                    $end_time = DateTime::createFromFormat('H:i', $values['end_time']);
                    
                    if ($start_time && $end_time) {
                        $duration = (int)$end_time->diff($start_time)->format('%h');
                        
                        if ($duration > 0) {
                            $item->add_meta_data('_duration_hours', $duration, true);
                            // Pas nécessaire d'afficher la durée en heures si on a déjà la durée en jours
                            if (!isset($days) || $days <= 1) {
                                $item->add_meta_data(__('Durée', 'life-travel-excursion'),
                                                  sprintf(_n('%d heure', '%d heures', $duration, 'life-travel-excursion'), $duration),
                                                  true);
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Ignorer silencieusement les erreurs de format d'heure
                }
            }
        }
        
        // Détails des extras
        if (isset($values['extras']) && !empty($values['extras'])) {
            // Sauvegarder les données brutes pour traitement futur
            $item->add_meta_data('_extras', maybe_serialize($values['extras']), true);
            
            $extras_list = [];
            
            // Récupérer la liste complète des extras du produit pour avoir les noms complets
            if (isset($values['product_id'])) {
                $extras_config = get_post_meta($values['product_id'], '_extras', true);
                $extras_array = explode("\n", $extras_config);
                $extras_lookup = [];
                
                foreach ($extras_array as $extra_line) {
                    $extra_parts = explode('|', $extra_line);
                    if (count($extra_parts) >= 1) {
                        $name = trim($extra_parts[0]);
                        $key = sanitize_title($name);
                        $extras_lookup[$key] = $name;
                    }
                }
                
                // Générer l'affichage amélioré des extras
                foreach ($values['extras'] as $key => $value) {
                    $display_name = isset($extras_lookup[$key]) ? $extras_lookup[$key] : ucfirst(str_replace('_', ' ', $key));
                    $extras_list[] = $display_name . ': ' . sanitize_text_field($value);
                }
            } else {
                // Fallback si product_id n'est pas disponible
                foreach ($values['extras'] as $key => $value) {
                    $extras_list[] = ucfirst(str_replace('_', ' ', sanitize_text_field($key))) . ': ' . sanitize_text_field($value);
                }
            }
            
            $extras_display = implode(', ', $extras_list);
            $item->add_meta_data(__('Extras', 'life-travel-excursion'), esc_html($extras_display), true);
        }
        
        // Détails des activités
        if (isset($values['activities']) && !empty($values['activities'])) {
            // Sauvegarder les données brutes pour traitement futur
            $item->add_meta_data('_activities', maybe_serialize($values['activities']), true);
            
            $activities_list = [];
            
            // Récupérer la liste complète des activités du produit pour avoir les noms complets
            if (isset($values['product_id'])) {
                $activities_config = get_post_meta($values['product_id'], '_activities', true);
                $activities_array = explode("\n", $activities_config);
                $activities_lookup = [];
                
                foreach ($activities_array as $activity_line) {
                    $activity_parts = explode('|', $activity_line);
                    if (count($activity_parts) >= 1) {
                        $name = trim($activity_parts[0]);
                        $key = sanitize_title($name);
                        $activities_lookup[$key] = $name;
                    }
                }
                
                // Générer l'affichage amélioré des activités
                foreach ($values['activities'] as $key => $value) {
                    $display_name = isset($activities_lookup[$key]) ? $activities_lookup[$key] : ucfirst(str_replace('_', ' ', $key));
                    $activities_list[] = $display_name . ': ' . sanitize_text_field($value) . ' ' . __('jour(s)', 'life-travel-excursion');
                }
            } else {
                // Fallback si product_id n'est pas disponible
                foreach ($values['activities'] as $key => $value) {
                    $activities_list[] = ucfirst(str_replace('_', ' ', sanitize_text_field($key))) . ': ' . 
                                         sanitize_text_field($value) . ' ' . __('jour(s)', 'life-travel-excursion');
                }
            }
            
            $activities_display = implode(', ', $activities_list);
            $item->add_meta_data(__('Activités', 'life-travel-excursion'), esc_html($activities_display), true);
        }
    }

    function life_travel_excursion_add_to_cart_validation( $passed, $product_id, $quantity ) {
        $product = wc_get_product( $product_id );
        if ( 'excursion' === $product->get_type() ) {
            if ( ! is_user_logged_in() ) {
                wc_add_notice( __( 'Vous devez être connecté pour réserver cette excursion.', 'life-travel-excursion' ), 'error' );
                return false;
            }
        }
        return $passed;
    }

    /**
     * Convertit les prix en tenant compte des plugins de devise multiple
     * 
     * @param float $price Le prix à convertir
     * @return float Le prix converti
     */
    function life_travel_excursion_convert_price($price) {
        if (class_exists('WOOMultiCurrency')) {
            $multi_currency = WOOMultiCurrency()->get_current_currency();
            return apply_filters('wmc_convert_price', floatval($price), sanitize_text_field($multi_currency));
        }
        return floatval($price);
    }
    
    /**
     * Ajoute une colonne personnalisée à la liste des commandes dans l'administration
     * pour afficher les détails des réservations d'excursions
     */
    function life_travel_excursion_add_admin_order_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            // Ajouter la nouvelle colonne après la colonne 'order-status'
            if ($key === 'order_status') {
                $new_columns['excursion_details'] = __('Détails Excursion', 'life-travel-excursion');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Affiche le contenu de la colonne personnalisée pour les réservations d'excursions
     */
    function life_travel_excursion_display_admin_order_column_content($column, $order_id) {
        if ($column !== 'excursion_details') {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $has_excursion = false;
        $excursion_details = array();
        
        // Parcourir tous les articles de la commande
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            // Vérifier si c'est une excursion
            if ($product->get_type() === 'excursion') {
                $has_excursion = true;
                
                // Récupérer les informations clés
                $booking_id = $item->get_meta('_booking_id');
                $participants = $item->get_meta('Participants');
                $start_date = $item->get_meta('Date de début');
                $vehicles_needed = $item->get_meta('_vehicles_needed');
                if (!$vehicles_needed) $vehicles_needed = 1;
                
                // Créer un résumé concis pour l'affichage
                $excursion_summary = sprintf(
                    '<div class="excursion-admin-summary">'
                    . '<strong>%s</strong><br>'
                    . '<span>%s: %s</span><br>'
                    . '<span>%s: %d</span>'
                    . '%s'
                    . '</div>',
                    esc_html($product->get_name()),
                    __('Date', 'life-travel-excursion'),
                    esc_html($start_date),
                    __('Participants', 'life-travel-excursion'),
                    intval($participants),
                    $vehicles_needed > 1 ? '<br><span class="excursion-vehicles">' . sprintf(_n('%d véhicule', '%d véhicules', $vehicles_needed, 'life-travel-excursion'), $vehicles_needed) . '</span>' : ''
                );
                
                $excursion_details[] = $excursion_summary;
            }
        }
        
        // Afficher les informations si des excursions ont été trouvées
        if ($has_excursion) {
            echo implode('<hr style="margin: 8px 0;">', $excursion_details);
            
            // Ajouter un peu de style pour la colonne
            echo '<style>'
                . '.excursion-admin-summary { font-size: 12px; line-height: 1.4; }'
                . '.excursion-vehicles { color: #d63638; font-weight: bold; }'
                . '</style>';
        } else {
            echo '<span class="na">-</span>';
        }
    }
    
    /**
     * Ajoute un filtrage rapide pour les commandes contenant des excursions
     */
    function life_travel_excursion_add_shop_order_filter($filters) {
        $filters['shop_order_excursion'] = __('Excursions uniquement', 'life-travel-excursion');
        return $filters;
    }
    
    /**
     * Modifie la requête de liste des commandes pour filtrer par excursions
     */
    function life_travel_excursion_filter_orders_by_excursions($query) {
        global $pagenow, $post_type, $wpdb;
        
        if ($pagenow === 'edit.php' && $post_type === 'shop_order' && isset($_GET['shop_order_type']) && $_GET['shop_order_type'] === 'shop_order_excursion') {
            // Sous-requête pour trouver les commandes contenant des excursions
            $order_ids = $wpdb->get_col(
                "SELECT DISTINCT order_items.order_id
                FROM {$wpdb->prefix}woocommerce_order_items as order_items
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as itemmeta ON order_items.order_item_id = itemmeta.order_item_id
                LEFT JOIN {$wpdb->postmeta} AS products ON products.meta_value = itemmeta.meta_value
                LEFT JOIN {$wpdb->posts} AS product_posts ON products.post_id = product_posts.ID
                WHERE order_items.order_item_type = 'line_item'
                AND itemmeta.meta_key = '_product_id'
                AND product_posts.post_type = 'product'
                AND EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} 
                    WHERE post_id = products.post_id 
                    AND meta_key = '_excursion_type' AND meta_value = 'yes'
                )"
            );
            
            if (!empty($order_ids)) {
                $query->set('post__in', $order_ids);
            } else {
                // Aucune commande avec excursion, forcer un résultat vide
                $query->set('post__in', array(0));
            }
        }
        
        return $query;
    }

    function life_travel_excursion_add_settings_page() {
        add_options_page(
            __( 'Paramètres Life Travel Excursion', 'life-travel-excursion' ),
            __( 'Life Travel Excursion', 'life-travel-excursion' ),
            'manage_options',
            'life-travel-excursion',
            'life_travel_excursion_render_settings_page'
        );
    }

    function life_travel_excursion_render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Paramètres Life Travel Excursion', 'life-travel-excursion' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'life_travel_excursion_settings_group' );
                do_settings_sections( 'life-travel-excursion' );
                submit_button();
                ?>
            </form>
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'export_orders_csv', '1', admin_url() ) ); ?>" class="button"><?php _e( 'Exporter les commandes (CSV)', 'life-travel-excursion' ); ?></a>
                <div id="debug-tools" style="margin-top:20px; padding:10px; border:1px solid #ccc;">
                    <h2>Outils de Débogage</h2>
                    <button onclick="console.log('Test AJAX Calcul Prix');">Test Calcul Tarif</button>
                    <button onclick="alert('Test Export CSV');">Test Export CSV</button>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    function life_travel_excursion_register_settings() {
        register_setting( 'life_travel_excursion_settings_group', 'max_simultaneous_excursions', 'intval' );
        add_settings_section(
            'life_travel_excursion_main_section',
            __( 'Paramètres Principaux', 'life-travel-excursion' ),
            null,
            'life-travel-excursion'
        );
        add_settings_field(
            'max_simultaneous_excursions',
            __( 'Nombre maximal d\'excursions simultanées', 'life-travel-excursion' ),
            'life_travel_excursion_max_simultaneous_excursions_callback',
            'life-travel-excursion',
            'life_travel_excursion_main_section'
        );
    }

    function life_travel_excursion_max_simultaneous_excursions_callback() {
        $value = get_option( 'max_simultaneous_excursions', 1 );
        echo '<input type="number" id="max_simultaneous_excursions" name="max_simultaneous_excursions" value="' . esc_attr( $value ) . '" min="1" />';
    }

    // Espace client et notifications
    function life_travel_excursion_add_myaccount_endpoint() {
        add_rewrite_endpoint( 'mes-excursions', EP_ROOT | EP_PAGES );
    }

    function life_travel_excursion_myaccount_menu_items( $items ) {
        $new_items = array();
        foreach ( $items as $key => $value ) {
            $new_items[ $key ] = $value;
            if ( 'dashboard' === $key ) {
                $new_items['mes-excursions'] = __( 'Mes excursions', 'life-travel-excursion' );
            }
        }
        return $new_items;
    }

    function life_travel_excursion_myaccount_content() {
        wc_get_template( 'myaccount-mes-excursions.php', array(), '', plugin_dir_path( __FILE__ ) . 'templates/' );
    }
    
    // Affichage du formulaire de réservation sur les pages produit

    function life_travel_excursion_restrict_review_submission( $approved, $commentdata ) {
        return $approved;
    }

    function life_travel_excursion_send_twilio_notification( $to, $message, $channel = 'sms' ) {
        $account_sid = 'YOUR_TWILIO_ACCOUNT_SID';
        $auth_token  = 'YOUR_TWILIO_AUTH_TOKEN';
        $from_sms    = 'YOUR_TWILIO_SMS_NUMBER';
        $from_whatsapp = 'whatsapp:YOUR_TWILIO_WHATSAPP_NUMBER';
        $from = ( 'whatsapp' === $channel ) ? $from_whatsapp : $from_sms;
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $account_sid . '/Messages.json';
        $body = array(
            'From' => $from,
            'To'   => ( 'whatsapp' === $channel ) ? 'whatsapp:' . $to : $to,
            'Body' => $message,
        );
        $args = array(
            'body'    => $body,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ),
            ),
            'timeout' => 15,
        );
        $response = wp_remote_post( $url, $args );
        if ( is_wp_error( $response ) ) {
            error_log("[Twilio] " . $response->get_error_message());
            return false;
        }
        $status_code = wp_remote_retrieve_response_code( $response );
        return ( 200 === $status_code || 201 === $status_code );
    }

    /**
     * Gestion des notifications et des statuts de réservation d'excursion
     * 
     * Envoie des notifications personnalisées pour les excursions et met à jour
     * les statuts de réservation lors de la complétion d'une commande.
     * 
     * @param int $order_id ID de la commande
     */
    function life_travel_excursion_notify_order_completed($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        // Vérifier si la commande contient des excursions
        $contains_excursion = false;
        $excursion_details = array();
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            if ($product->get_type() === 'excursion') {
                $contains_excursion = true;
                
                // Récupérer les détails de l'excursion
                $booking_id = $item->get_meta('_booking_id');
                $participants = $item->get_meta('Participants');
                $start_date = $item->get_meta('_start_date'); // Format ISO
                $formatted_date = $item->get_meta('Date de début'); // Format local
                $vehicles_needed = $item->get_meta('_vehicles_needed') ?: 1;
                
                // Stocker les détails pour la notification
                $excursion_details[] = array(
                    'name' => $product->get_name(),
                    'booking_id' => $booking_id,
                    'participants' => $participants,
                    'start_date' => $start_date,
                    'formatted_date' => $formatted_date,
                    'vehicles' => $vehicles_needed
                );
                
                // Mise à jour du statut de réservation
                wc_update_order_item_meta($item_id, '_booking_status', 'confirmed');
                wc_update_order_item_meta($item_id, '_confirmation_date', current_time('mysql'));
                
                // Log pour débogage
                error_log("[Life Travel] Réservation confirmée: $booking_id, Excursion: {$product->get_name()}");
                
                // Vérifier si des véhicules supplémentaires sont nécessaires
                if ($vehicles_needed > 1) {
                    // Notification interne pour la gestion des véhicules
                    do_action('life_travel_excursion_notify_additional_vehicles', $order_id, $item_id, $vehicles_needed, $start_date);
                }
            }
        }
        
        // Si la commande ne contient pas d'excursion, on s'arrête là
        if (!$contains_excursion) return;
        
        // Informations client
        $customer_phone = $order->get_billing_phone();
        $customer_email = $order->get_billing_email();
        $customer_name  = $order->get_billing_first_name();
        $order_date = $order->get_date_created()->format('d/m/Y');
        // Construction du message de notification
        $message = sprintf(
            __('Bonjour %s, merci pour votre réservation avec Life Travel (N°%s)!', 'life-travel-excursion'),
            $customer_name,
            $order->get_order_number()
        );
        
        // Ajouter les détails de chaque excursion réservée
        foreach ($excursion_details as $index => $excursion) {
            // Générer des informations sur les véhicules si nécessaire
            $vehicle_info = '';
            if ($excursion['vehicles'] > 1) {
                $vehicle_info = sprintf(
                    __(' (%d %s alloués)', 'life-travel-excursion'),
                    $excursion['vehicles'],
                    _n('véhicule', 'véhicules', $excursion['vehicles'], 'life-travel-excursion')
                );
            }
            
            // Ajouter cette excursion au message
            $message .= "\n\n" . sprintf(
                __('Excursion: %s\nRéférence: %s\nDate: %s\nParticipants: %d%s', 'life-travel-excursion'),
                $excursion['name'],
                $excursion['booking_id'],
                $excursion['formatted_date'],
                $excursion['participants'],
                $vehicle_info
            );
        }
        
        // Ajouter un message de conclusion
        $message .= "\n\n" . __('Nous vous contacterons 24h avant l\'excursion pour confirmer les détails. Pour toute question, n\'hésitez pas à nous contacter.', 'life-travel-excursion');
        if ( ! empty( $customer_phone ) ) {
            life_travel_excursion_send_twilio_notification( $customer_phone, $message, 'sms' );
        }
        $admin_users = get_users( array( 'role' => 'administrator' ) );
        if ( ! empty( $admin_users ) ) {
            $admin_message = sprintf(
                __( 'Nouvelle réservation : Commande #%s confirmée pour l\'excursion %s.', 'life-travel-excursion' ),
                $order->get_order_number(),
                $excursion_name
            );
            foreach ( $admin_users as $admin ) {
                $admin_phone = get_user_meta( $admin->ID, 'billing_phone', true );
                $admin_email = $admin->user_email;
                if ( ! empty( $admin_phone ) ) {
                    life_travel_excursion_send_twilio_notification( $admin_phone, $admin_message, 'sms' );
                } else {
                    wp_mail( $admin_email, __( 'Nouvelle réservation confirmée', 'life-travel-excursion' ), $admin_message );
                }
            }
        }
    }
    // Inclusions frontend intégrées dans la structure principale
    require_once __DIR__ . '/includes/frontend/myaccount.php';
    require_once __DIR__ . '/includes/frontend/auth.php';
    require_once __DIR__ . '/includes/frontend/loyalty.php';
    
    // Hook pour initialiser le type de produit personalisé "excursion"
    // Exécuté uniquement après le chargement de WooCommerce
    add_action('woocommerce_loaded', 'life_travel_excursion_init');
} 
// Fin de la structure conditionnelle principale (if $woocommerce_active)
else {
    // Message d'erreur si WooCommerce n'est pas actif
    add_action('admin_notices', 'life_travel_excursion_woocommerce_missing_notice');
}