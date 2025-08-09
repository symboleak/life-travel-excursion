<?php
/**
 * Classe principale d'administration des paniers abandonnés
 * 
 * Orchestre toutes les fonctionnalités d'administration liées aux paniers abandonnés
 * et sert de point d'entrée pour l'intégration dans le plugin principal.
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

defined('ABSPATH') || exit;

/**
 * Classe principale d'administration des paniers abandonnés
 */
class Life_Travel_Abandoned_Cart_Admin {
    
    /**
     * Instance unique (singleton)
     * @var Life_Travel_Abandoned_Cart_Admin
     */
    private static $instance = null;
    
    /**
     * Constructeur privé (pattern singleton)
     */
    private function __construct() {
        // Charger les dépendances
        $this->load_dependencies();
        
        // Initialiser les hooks
        $this->init_hooks();
    }
    
    /**
     * Obtenir l'instance unique (pattern singleton)
     * 
     * @return Life_Travel_Abandoned_Cart_Admin Instance unique
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Charge les dépendances nécessaires
     */
    private function load_dependencies() {
        // Liste des paniers abandonnés (basé sur WP_List_Table)
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/abandoned-cart-list.php';
        
        // Tableau de bord des paniers abandonnés
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/abandoned-cart-dashboard.php';
        
        // Analyseur de paniers abandonnés
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/abandoned-cart-analyzer.php';
        
        // Gestionnaire d'emails de récupération
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/abandoned-cart-mailer.php';
    }
    
    /**
     * Initialise les hooks d'administration
     */
    private function init_hooks() {
        // Initialiser le tableau de bord
        add_action('init', array($this, 'init_dashboard'));
        
        // Hooks AJAX
        add_action('wp_ajax_life_travel_admin_send_recovery_email', array($this, 'ajax_send_recovery_email'));
        add_action('wp_ajax_life_travel_admin_delete_cart', array($this, 'ajax_delete_cart'));
        add_action('wp_ajax_life_travel_get_email_template', array($this, 'ajax_get_email_template'));
        
        // Ajouter le lien rapide dans la liste des plugins
        add_filter('plugin_action_links_life-travel-excursion/life-travel-excursion.php', array($this, 'add_action_links'));
        
        // Ajouter des métaboxes pour WooCommerce
        add_action('add_meta_boxes', array($this, 'add_order_metabox'));
        
        // Vérifier les paniers abandonnés régulièrement
        add_action('admin_init', array($this, 'schedule_cart_check'));
        add_action('life_travel_check_abandoned_carts', array($this, 'check_abandoned_carts'));
    }
    
    /**
     * Initialise le tableau de bord des paniers abandonnés
     */
    public function init_dashboard() {
        // S'assurer que nous sommes dans l'administration
        if (!is_admin()) {
            return;
        }
        
        // Initialiser le tableau de bord
        Life_Travel_Abandoned_Cart_Dashboard::get_instance();
    }
    
    /**
     * Ajoute des liens rapides dans la liste des plugins
     * 
     * @param array $links Liens existants
     * @return array Liens modifiés
     */
    public function add_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=life-travel-abandoned-carts') . '">' . __('Paniers abandonnés', 'life-travel-excursion') . '</a>',
        );
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * Ajoute une metabox aux commandes WooCommerce pour indiquer si 
     * elle provient d'une récupération de panier abandonné
     */
    public function add_order_metabox() {
        add_meta_box(
            'life-travel-cart-recovery-info',
            __('Récupération de panier', 'life-travel-excursion'),
            array($this, 'render_order_metabox'),
            'shop_order',
            'side',
            'default'
        );
    }
    
    /**
     * Affiche le contenu de la metabox des commandes
     * 
     * @param WP_Post $post Objet post de la commande
     */
    public function render_order_metabox($post) {
        $order_id = $post->ID;
        $recovered = get_post_meta($order_id, '_life_travel_recovered_cart', true);
        
        if ($recovered) {
            $recovery_date = get_post_meta($order_id, '_life_travel_recovery_date', true);
            $cart_id = get_post_meta($order_id, '_life_travel_abandoned_cart_id', true);
            
            echo '<p style="margin-bottom:10px;"><span class="dashicons dashicons-yes" style="color:#46b450;"></span> ' . 
                esc_html__('Cette commande provient d\'un panier abandonné récupéré.', 'life-travel-excursion') . 
                '</p>';
            
            if ($recovery_date) {
                echo '<p><strong>' . esc_html__('Date de récupération:', 'life-travel-excursion') . '</strong><br>';
                echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($recovery_date)));
                echo '</p>';
            }
            
            if ($cart_id) {
                echo '<p><strong>' . esc_html__('ID du panier:', 'life-travel-excursion') . '</strong> ' . esc_html($cart_id) . '</p>';
            }
        } else {
            echo '<p>' . esc_html__('Cette commande ne provient pas d\'un panier abandonné récupéré.', 'life-travel-excursion') . '</p>';
        }
    }
    
    /**
     * Planifie la vérification régulière des paniers abandonnés
     */
    public function schedule_cart_check() {
        if (!wp_next_scheduled('life_travel_check_abandoned_carts')) {
            wp_schedule_event(time(), 'hourly', 'life_travel_check_abandoned_carts');
        }
    }
    
    /**
     * Vérifie les paniers abandonnés et envoie des emails de rappel si nécessaire
     */
    public function check_abandoned_carts() {
        // Récupérer les paramètres des paniers abandonnés
        $settings = get_option('life_travel_abandoned_cart_settings', array());
        
        // Vérifier si la récupération automatique est activée
        if (empty($settings['enable_recovery']) || $settings['enable_recovery'] !== 'yes') {
            return;
        }
        
        // Vérifier si l'envoi automatique d'emails est activé
        if (empty($settings['send_email_automatically']) || $settings['send_email_automatically'] !== 'yes') {
            return;
        }
        
        // Obtenir le mailer et analyser les paniers à récupérer
        $mailer = Life_Travel_Abandoned_Cart_Mailer::get_instance();
        $analyzer = Life_Travel_Abandoned_Cart_Analyzer::get_instance();
        
        // Récupérer les paniers abandonnés qui nécessitent un email
        $carts_needing_email = $analyzer->get_carts_needing_email();
        
        // Envoyer les emails de récupération
        foreach ($carts_needing_email as $cart) {
            $mailer->send_recovery_email($cart->id);
        }
    }
    
    /**
     * Gestionnaire AJAX pour l'envoi d'email de récupération
     */
    public function ajax_send_recovery_email() {
        // Vérifier le nonce
        if (!check_ajax_referer('life_travel_admin_cart_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Erreur de sécurité. Veuillez rafraîchir la page et réessayer.', 'life-travel-excursion')
            ));
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('Vous n\'avez pas les droits suffisants pour effectuer cette action.', 'life-travel-excursion')
            ));
        }
        
        // Récupérer l'ID du panier
        $cart_id = isset($_POST['cart_id']) ? absint($_POST['cart_id']) : 0;
        
        if ($cart_id === 0) {
            wp_send_json_error(array(
                'message' => __('Panier non valide.', 'life-travel-excursion')
            ));
        }
        
        // Envoyer l'email de récupération
        $mailer = Life_Travel_Abandoned_Cart_Mailer::get_instance();
        $result = $mailer->send_recovery_email($cart_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Email de récupération envoyé avec succès.', 'life-travel-excursion'),
                'status' => __('Envoyé', 'life-travel-excursion')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Impossible d\'envoyer l\'email de récupération.', 'life-travel-excursion')
            ));
        }
    }
    
    /**
     * Gestionnaire AJAX pour la suppression d'un panier
     */
    public function ajax_delete_cart() {
        // Vérifier le nonce
        if (!check_ajax_referer('life_travel_admin_cart_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Erreur de sécurité. Veuillez rafraîchir la page et réessayer.', 'life-travel-excursion')
            ));
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('Vous n\'avez pas les droits suffisants pour effectuer cette action.', 'life-travel-excursion')
            ));
        }
        
        // Récupérer l'ID du panier
        $cart_id = isset($_POST['cart_id']) ? absint($_POST['cart_id']) : 0;
        
        if ($cart_id === 0) {
            wp_send_json_error(array(
                'message' => __('Panier non valide.', 'life-travel-excursion')
            ));
        }
        
        // Supprimer le panier
        global $wpdb;
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $cart_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Panier supprimé avec succès.', 'life-travel-excursion')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Impossible de supprimer le panier.', 'life-travel-excursion')
            ));
        }
    }
    
    /**
     * Gestionnaire AJAX pour récupérer un modèle d'email
     */
    public function ajax_get_email_template() {
        // Vérifier le nonce
        if (!check_ajax_referer('life_travel_admin_cart_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Erreur de sécurité. Veuillez rafraîchir la page et réessayer.', 'life-travel-excursion')
            ));
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('Vous n\'avez pas les droits suffisants pour effectuer cette action.', 'life-travel-excursion')
            ));
        }
        
        // Récupérer le modèle demandé
        $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : 'default';
        
        // Obtenir le contenu du modèle
        $mailer = Life_Travel_Abandoned_Cart_Mailer::get_instance();
        $content = $mailer->get_email_template($template);
        
        if ($content) {
            wp_send_json_success(array(
                'content' => $content
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Modèle d\'email non trouvé.', 'life-travel-excursion')
            ));
        }
    }
}
