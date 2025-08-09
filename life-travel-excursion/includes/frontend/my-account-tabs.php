<?php
/**
 * My Account Tabs
 * 
 * Ajoute des onglets personnalisés à la page Mon Compte pour les excursions et les points de fidélité
 * 
 * @package Life_Travel_Excursion
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class Life_Travel_My_Account {
    /**
     * Instance unique
     */
    private static $instance = null;
    
    /**
     * Constructeur privé (singleton)
     */
    private function __construct() {
        // Ajouter des onglets personnalisés
        add_filter('woocommerce_account_menu_items', [$this, 'add_account_menu_items']);
        
        // Ajouter des endpoints pour les nouveaux onglets
        add_action('init', [$this, 'add_endpoints']);
        
        // Ajouter du contenu aux nouveaux onglets
        add_action('woocommerce_account_excursions_endpoint', [$this, 'excursions_content']);
        add_action('woocommerce_account_loyalty_endpoint', [$this, 'loyalty_content']);
        
        // Enqueue scripts et styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Sauvegarder les données de l'excursion dans la commande
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_excursion_data']);
        
        // Ajouter les données d'excursion dans les emails
        add_action('woocommerce_email_order_meta', [$this, 'add_excursion_data_to_emails'], 10, 3);
    }
    
    /**
     * Récupère l'instance unique
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Ajoute des points d'entrée personnalisés à WooCommerce
     */
    public function add_endpoints() {
        add_rewrite_endpoint('excursions', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('loyalty', EP_ROOT | EP_PAGES);
        
        // Vider le cache des règles de réécriture si nécessaire
        if (get_option('lte_flush_rewrite_rules', 'no') === 'yes') {
            flush_rewrite_rules();
            update_option('lte_flush_rewrite_rules', 'no');
        }
    }
    
    /**
     * Enregistre les styles et scripts
     */
    public function enqueue_assets() {
        if (is_account_page()) {
            wp_enqueue_style(
                'lte-my-account',
                LIFE_TRAVEL_EXCURSION_URL . 'assets/css/my-account.css',
                [],
                LIFE_TRAVEL_EXCURSION_VERSION
            );
            
            wp_enqueue_script(
                'lte-my-account',
                LIFE_TRAVEL_EXCURSION_URL . 'assets/js/my-account.js',
                ['jquery'],
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
            
            wp_localize_script('lte-my-account', 'lteAccount', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lte_account_nonce')
            ]);
        }
    }
    
    /**
     * Ajoute des éléments de menu personnalisés à la page Mon Compte
     */
    public function add_account_menu_items($items) {
        // Insérer avant "orders"
        $new_items = [];
        
        foreach ($items as $key => $item) {
            if ($key === 'orders') {
                $new_items['excursions'] = __('Mes excursions', 'life-travel-excursion');
            }
            $new_items[$key] = $item;
        }
        
        // Ajouter l'onglet fidélité avant logout
        $logout_item = $new_items['customer-logout'];
        unset($new_items['customer-logout']);
        $new_items['loyalty'] = __('Mes points de fidélité', 'life-travel-excursion');
        $new_items['customer-logout'] = $logout_item;
        
        return $new_items;
    }
    
    /**
     * Affiche le contenu de l'onglet Excursions
     */
    public function excursions_content() {
        // Récupérer les commandes de l'utilisateur avec des excursions
        $customer_orders = $this->get_customer_excursion_orders();
        
        // Afficher le template
        include_once $this->get_template_path('my-account/excursions.php');
    }
    
    /**
     * Affiche le contenu de l'onglet Points de fidélité
     */
    public function loyalty_content() {
        // Récupérer les points de fidélité de l'utilisateur
        $user_id = get_current_user_id();
        $loyalty_points = get_user_meta($user_id, '_lte_loyalty_points', true) ?: 0;
        $loyalty_history = $this->get_loyalty_history($user_id);
        
        // Récupérer le taux de conversion des points
        $points_value = get_option('lte_points_value', 100); // Valeur par défaut: 100 points = 1€
        $max_discount_percent = get_option('lte_max_discount_percent', 20); // Max 20% de réduction
        
        // Afficher le template
        include_once $this->get_template_path('my-account/loyalty.php');
    }
    
    /**
     * Récupère le chemin du template, avec fallback sur le template par défaut
     */
    private function get_template_path($template) {
        // Vérifier dans le thème
        $theme_template = get_stylesheet_directory() . '/life-travel/' . $template;
        
        if (file_exists($theme_template)) {
            return $theme_template;
        }
        
        // Vérifier dans le plugin
        $plugin_template = LIFE_TRAVEL_EXCURSION_DIR . 'templates/' . $template;
        
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        // Template par défaut intégré
        return LIFE_TRAVEL_EXCURSION_DIR . 'templates/default-' . basename($template);
    }
    
    /**
     * Récupère les commandes d'excursions de l'utilisateur courant
     */
    private function get_customer_excursion_orders() {
        $user_id = get_current_user_id();
        $excursion_orders = [];
        
        // Obtenir les commandes de l'utilisateur
        $customer_orders = wc_get_orders([
            'customer' => $user_id,
            'limit' => -1 // Toutes les commandes
        ]);
        
        foreach ($customer_orders as $order) {
            $has_excursion = false;
            
            // Vérifier si la commande contient une excursion
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $terms = get_the_terms($product_id, 'product_cat');
                
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        if ($term->slug === 'excursion') {
                            $has_excursion = true;
                            break 2;
                        }
                    }
                }
                
                // Alternative: vérifier par méta
                if (get_post_meta($product_id, '_is_excursion', true) === 'yes') {
                    $has_excursion = true;
                    break;
                }
            }
            
            // Si la commande contient une excursion, l'ajouter au tableau
            if ($has_excursion) {
                // Récupérer les données d'excursion
                $excursion_date = $order->get_meta('_excursion_date');
                $participants = $order->get_meta('_excursion_participants');
                
                $excursion_orders[] = [
                    'order' => $order,
                    'excursion_date' => $excursion_date,
                    'participants' => $participants
                ];
            }
        }
        
        // Trier par date d'excursion (les plus proches en premier)
        usort($excursion_orders, function($a, $b) {
            $date_a = $a['excursion_date'] ? strtotime($a['excursion_date']) : 0;
            $date_b = $b['excursion_date'] ? strtotime($b['excursion_date']) : 0;
            
            if ($date_a == $date_b) {
                return 0;
            }
            
            return ($date_a > $date_b) ? 1 : -1;
        });
        
        return $excursion_orders;
    }
    
    /**
     * Récupère l'historique des points de fidélité
     */
    private function get_loyalty_history($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lte_loyalty_history';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return [];
        }
        
        // Récupérer l'historique
        $history = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d ORDER BY date_created DESC LIMIT 20",
                $user_id
            ),
            ARRAY_A
        );
        
        return $history ?: [];
    }
    
    /**
     * Sauvegarde les données d'excursion dans la commande
     */
    public function save_excursion_data($order_id) {
        if (isset($_POST['excursion_date'])) {
            update_post_meta($order_id, '_excursion_date', sanitize_text_field($_POST['excursion_date']));
        }
        
        if (isset($_POST['excursion_participants'])) {
            update_post_meta($order_id, '_excursion_participants', absint($_POST['excursion_participants']));
        }
    }
    
    /**
     * Ajoute les données d'excursion aux emails
     */
    public function add_excursion_data_to_emails($order, $sent_to_admin, $plain_text) {
        $excursion_date = $order->get_meta('_excursion_date');
        $participants = $order->get_meta('_excursion_participants');
        
        if ($excursion_date || $participants) {
            echo '<h2>' . __('Détails de l\'excursion', 'life-travel-excursion') . '</h2>';
            echo '<ul>';
            
            if ($excursion_date) {
                echo '<li><strong>' . __('Date de l\'excursion:', 'life-travel-excursion') . '</strong> ' . date_i18n(get_option('date_format'), strtotime($excursion_date)) . '</li>';
            }
            
            if ($participants) {
                echo '<li><strong>' . __('Nombre de participants:', 'life-travel-excursion') . '</strong> ' . $participants . '</li>';
            }
            
            echo '</ul>';
        }
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_My_Account::get_instance();
});
