<?php
/**
 * Intégration du système de fidélité
 *
 * Ce fichier coordonne tous les composants du système de fidélité
 * et enregistre les endpoints WooCommerce nécessaires.
 *
 * @package Life_Travel
 * @subpackage Frontend
 * @since 2.5.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe d'intégration du système de fidélité
 */
class Life_Travel_Loyalty_Integration {
    
    /**
     * Instance unique (pattern singleton)
     */
    private static $instance = null;
    
    /**
     * Constructor
     */
    private function __construct() {
        // Charger les dépendances
        $this->load_dependencies();
        
        // Enregistrer l'endpoint WooCommerce
        add_action('init', array($this, 'add_loyalty_endpoint'));
        
        // Ajouter le nouvel onglet au compte client
        add_filter('woocommerce_account_menu_items', array($this, 'add_loyalty_tab'));
        
        // Afficher le contenu de l'onglet
        add_action('woocommerce_account_loyalty_endpoint', array($this, 'loyalty_tab_content'));
        
        // Ajouter les notifications dans le panier et le checkout
        add_action('woocommerce_before_cart', array($this, 'display_cart_loyalty_notice'));
        add_action('woocommerce_before_checkout_form', array($this, 'display_cart_loyalty_notice'));
        
        // Ajouter des CSS personnalisés
        add_action('wp_enqueue_scripts', array($this, 'enqueue_loyalty_styles'));
    }
    
    /**
     * Charge les fichiers de dépendance
     */
    private function load_dependencies() {
        // Charger le gestionnaire de points pour les excursions
        if (!class_exists('Life_Travel_Loyalty_Excursions')) {
            require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/loyalty-excursions.php';
        }
        
        // Charger le gestionnaire de points pour les partages sociaux
        if (!class_exists('Life_Travel_Loyalty_Social')) {
            require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/loyalty-social.php';
        }
    }
    
    /**
     * Retourne l'instance unique de la classe
     *
     * @return Life_Travel_Loyalty_Integration
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Ajoute l'endpoint loyalty au compte client
     */
    public function add_loyalty_endpoint() {
        add_rewrite_endpoint('loyalty', EP_ROOT | EP_PAGES);
        
        // Vérifier si la règle a déjà été ajoutée
        if (!get_option('lte_loyalty_endpoint_added')) {
            // Forcer la mise à jour des règles de réécriture
            flush_rewrite_rules();
            update_option('lte_loyalty_endpoint_added', true);
        }
    }
    
    /**
     * Ajoute l'onglet de fidélité au menu du compte client
     *
     * @param array $items Les éléments du menu existants
     * @return array Les éléments du menu modifiés
     */
    public function add_loyalty_tab($items) {
        // Insérer l'onglet loyalty après dashboard
        $new_items = array();
        
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            
            if ($key === 'dashboard') {
                $new_items['loyalty'] = __('Mes points de fidélité', 'life-travel-excursion');
            }
        }
        
        return $new_items;
    }
    
    /**
     * Affiche le contenu de l'onglet loyalty
     */
    public function loyalty_tab_content() {
        // Charger le template
        wc_get_template(
            'myaccount/loyalty-dashboard.php', 
            array(), 
            '', 
            LIFE_TRAVEL_EXCURSION_DIR . 'templates/'
        );
    }
    
    /**
     * Ajoute une notification dans le panier concernant les points
     */
    public function display_cart_loyalty_notice() {
        // Ne rien afficher si l'utilisateur n'est pas connecté
        if (!is_user_logged_in()) {
            return;
        }
        
        // Vérifier si le panier contient des excursions
        $has_excursions = false;
        $potential_points = 0;
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            
            if (!$product || $product->get_type() !== 'excursion') {
                continue;
            }
            
            $has_excursions = true;
            
            // Calculer les points potentiels
            $product_id = $product->get_id();
            $points_type = get_post_meta($product_id, '_loyalty_points_type', true);
            $points_value = get_post_meta($product_id, '_loyalty_points_value', true);
            
            if (!$points_type || !$points_value) {
                // Configuration par défaut si non spécifiée
                $points_type = 'percentage';
                $points_value = get_option('lte_points_per_currency', 5);
            }
            
            $item_total = $cart_item['line_total'];
            
            if ($points_type === 'fixed') {
                // Points fixes par excursion
                $potential_points += intval($points_value);
            } else {
                // Points basés sur le montant dépensé
                $potential_points += floor($item_total * intval($points_value) / 100);
            }
            
            // Appliquer le plafond spécifique à l'excursion s'il existe
            $excursion_max = get_post_meta($product_id, '_loyalty_points_max', true);
            if ($excursion_max && $potential_points > intval($excursion_max)) {
                $potential_points = intval($excursion_max);
            }
        }
        
        // Appliquer le plafond global
        $max_points_limit = get_option('lte_max_loyalty_points', 1000);
        if ($max_points_limit > 0 && $potential_points > $max_points_limit) {
            $potential_points = $max_points_limit;
        }
        
        // Afficher la notification si des points peuvent être gagnés
        if ($has_excursions && $potential_points > 0) {
            echo '<div class="woocommerce-info lte-loyalty-cart-notice">';
            printf(
                __('Avec cette commande, vous gagnerez environ <strong>%d points de fidélité</strong> ! <a href="%s">En savoir plus</a>.', 'life-travel-excursion'),
                $potential_points,
                esc_url(wc_get_endpoint_url('loyalty', '', wc_get_page_permalink('myaccount')))
            );
            echo '</div>';
        }
    }
    
    /**
     * Enregistre les styles CSS pour le système de fidélité
     */
    public function enqueue_loyalty_styles() {
        // Uniquement sur les pages concernées
        if (!is_account_page() && !is_cart() && !is_checkout()) {
            return;
        }
        
        // Enregistrer et ajouter le CSS inline
        $css = '
        .lte-loyalty-cart-notice {
            border-left-color: #2e7d32;
        }
        .woocommerce-MyAccount-navigation-link--loyalty a::before {
            content: "\\f155";
            font-family: dashicons;
        }
        ';
        
        wp_register_style('lte-loyalty-styles', false);
        wp_enqueue_style('lte-loyalty-styles');
        wp_add_inline_style('lte-loyalty-styles', $css);
        
        // Ajouter dashicons sur le frontend
        wp_enqueue_style('dashicons');
    }
}

// Initialisation
add_action('plugins_loaded', function() {
    Life_Travel_Loyalty_Integration::get_instance();
});
