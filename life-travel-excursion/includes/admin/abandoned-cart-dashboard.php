<?php
/**
 * Tableau de bord d'administration des paniers abandonnés
 * 
 * Interface sécurisée pour la gestion et l'analyse des paniers abandonnés
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

defined('ABSPATH') || exit;

// Inclure la classe de liste des paniers abandonnés
require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/abandoned-cart-list.php';

// Inclure les fichiers de vues
require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/views/dashboard-view.php';
require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/views/analytics-view.php';
require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/views/settings-view.php';

/**
 * Classe pour le tableau de bord des paniers abandonnés
 */
class Life_Travel_Abandoned_Cart_Dashboard {
    
    /**
     * Instance unique (singleton)
     * @var Life_Travel_Abandoned_Cart_Dashboard
     */
    private static $instance = null;
    
    /**
     * Slug du menu
     * @var string
     */
    private $menu_slug = 'life-travel-abandoned-carts';
    
    /**
     * Constructeur privé (pattern singleton)
     */
    private function __construct() {
        // Ajouter le menu d'administration
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enregistrer les actions Ajax
        add_action('wp_ajax_life_travel_admin_send_recovery_email', array($this, 'ajax_send_recovery_email'));
        add_action('wp_ajax_life_travel_admin_delete_cart', array($this, 'ajax_delete_cart'));
        
        // Charger les scripts et styles d'administration
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Obtenir l'instance unique (pattern singleton)
     * 
     * @return Life_Travel_Abandoned_Cart_Dashboard Instance unique
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Ajoute les menus d'administration
     */
    public function add_admin_menu() {
        // Vérifier les capacités
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        // Menu principal
        add_menu_page(
            __('Paniers abandonnés', 'life-travel-excursion'),
            __('Paniers abandonnés', 'life-travel-excursion'),
            'manage_woocommerce',
            $this->menu_slug,
            array($this, 'render_dashboard_page'),
            'dashicons-cart',
            56 // Position après WooCommerce
        );
        
        // Sous-menus
        add_submenu_page(
            $this->menu_slug,
            __('Paniers abandonnés', 'life-travel-excursion'),
            __('Tous les paniers', 'life-travel-excursion'),
            'manage_woocommerce',
            $this->menu_slug,
            array($this, 'render_dashboard_page')
        );
        
        add_submenu_page(
            $this->menu_slug,
            __('Analyse des paniers', 'life-travel-excursion'),
            __('Analyse', 'life-travel-excursion'),
            'manage_woocommerce',
            $this->menu_slug . '-analytics',
            array($this, 'render_analytics_page')
        );
        
        add_submenu_page(
            $this->menu_slug,
            __('Paramètres des paniers abandonnés', 'life-travel-excursion'),
            __('Paramètres', 'life-travel-excursion'),
            'manage_woocommerce',
            $this->menu_slug . '-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Charge les assets pour l'administration
     * 
     * @param string $hook Page actuelle
     */
    public function enqueue_admin_assets($hook) {
        // Ne charger que sur nos pages
        if (strpos($hook, $this->menu_slug) === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'life-travel-admin-cart',
            LIFE_TRAVEL_EXCURSION_URL . 'assets/css/admin-abandoned-cart.css',
            array(),
            LIFE_TRAVEL_EXCURSION_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'life-travel-admin-cart',
            LIFE_TRAVEL_EXCURSION_URL . 'assets/js/admin-abandoned-cart.js',
            array('jquery'),
            LIFE_TRAVEL_EXCURSION_VERSION,
            true
        );
        
        // Localisation du script
        wp_localize_script('life-travel-admin-cart', 'lifeTravelAdminCart', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('life_travel_admin_cart_nonce'),
            'messages' => array(
                'confirmDelete' => __('Êtes-vous sûr de vouloir supprimer ce panier abandonné ?', 'life-travel-excursion'),
                'confirmDeleteMultiple' => __('Êtes-vous sûr de vouloir supprimer ces paniers abandonnés ?', 'life-travel-excursion'),
                'confirmRecovery' => __('Envoyer un email de récupération pour ce panier ?', 'life-travel-excursion'),
                'confirmRecoveryMultiple' => __('Envoyer des emails de récupération pour ces paniers ?', 'life-travel-excursion'),
                'processingRequest' => __('Traitement en cours...', 'life-travel-excursion'),
                'success' => __('Opération réussie.', 'life-travel-excursion'),
                'error' => __('Une erreur est survenue.', 'life-travel-excursion')
            )
        ));
        
        // Ajouter Chart.js pour les pages d'analyse
        if (strpos($hook, '-analytics') !== false) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js',
                array(),
                '3.7.1',
                true
            );
        }
    }
    
    /**
     * Affiche la page principale du tableau de bord
     */
    public function render_dashboard_page() {
        // Vérifier les capacités
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'life-travel-excursion'));
        }
        
        // Traiter les actions
        $this->process_admin_actions();
        
        // Afficher les notifications
        $this->display_admin_notices();
        
        // Afficher la vue du tableau de bord
        Life_Travel_Abandoned_Cart_Dashboard_View::render_dashboard();
    }
    
    /**
     * Affiche la page d'analyse des paniers abandonnés
     */
    public function render_analytics_page() {
        // Vérifier les capacités
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'life-travel-excursion'));
        }
        
        // Afficher la vue d'analyse
        Life_Travel_Abandoned_Cart_Analytics_View::render_analytics();
    }
    
    /**
     * Affiche la page des paramètres
     */
    public function render_settings_page() {
        // Vérifier les capacités
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'life-travel-excursion'));
        }
        
        // Traiter l'enregistrement des paramètres
        if (isset($_POST['life_travel_save_cart_settings']) && check_admin_referer('life_travel_cart_settings')) {
            $this->save_cart_settings();
        }
        
        // Afficher la vue des paramètres
        Life_Travel_Abandoned_Cart_Settings_View::render_settings();
    }
    
    /**
     * Traite les actions administratives
     */
    private function process_admin_actions() {
        if (!isset($_REQUEST['action']) || !isset($_REQUEST['cart_id'])) {
            return;
        }
        
        // Vérifier le nonce
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'life_travel_cart_action')) {
            wp_die(__('Action non autorisée.', 'life-travel-excursion'));
        }
        
        $action = sanitize_text_field($_REQUEST['action']);
        
        switch ($action) {
            case 'send_recovery_email':
                $this->process_send_email();
                break;
                
            case 'delete_cart':
                $this->process_delete_cart();
                break;
                
            case 'bulk_send_recovery':
                $this->process_bulk_send_email();
                break;
                
            case 'bulk_delete':
                $this->process_bulk_delete();
                break;
        }
    }
    
    /**
     * Traite l'envoi d'un email de récupération
     */
    private function process_send_email() {
        $cart_id = isset($_REQUEST['cart_id']) ? absint($_REQUEST['cart_id']) : 0;
        
        if ($cart_id === 0) {
            $this->add_admin_notice(__('Panier non valide.', 'life-travel-excursion'), 'error');
            return;
        }
        
        $mailer = Life_Travel_Abandoned_Cart_Mailer::get_instance();
        $result = $mailer->send_recovery_email($cart_id);
        
        if ($result) {
            $this->add_admin_notice(__('Email de récupération envoyé avec succès.', 'life-travel-excursion'), 'success');
        } else {
            $this->add_admin_notice(__('Impossible d\'envoyer l\'email de récupération.', 'life-travel-excursion'), 'error');
        }
    }
    
    /**
     * Traite la suppression d'un panier
     */
    private function process_delete_cart() {
        $cart_id = isset($_REQUEST['cart_id']) ? absint($_REQUEST['cart_id']) : 0;
        
        if ($cart_id === 0) {
            $this->add_admin_notice(__('Panier non valide.', 'life-travel-excursion'), 'error');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $cart_id),
            array('%d')
        );
        
        if ($result !== false) {
            $this->add_admin_notice(__('Panier supprimé avec succès.', 'life-travel-excursion'), 'success');
        } else {
            $this->add_admin_notice(__('Impossible de supprimer le panier.', 'life-travel-excursion'), 'error');
        }
    }
    
    /**
     * Traite l'envoi en masse d'emails de récupération
     */
    private function process_bulk_send_email() {
        $cart_ids = isset($_REQUEST['cart_id']) ? array_map('absint', (array) $_REQUEST['cart_id']) : array();
        
        if (empty($cart_ids)) {
            $this->add_admin_notice(__('Aucun panier sélectionné.', 'life-travel-excursion'), 'error');
            return;
        }
        
        $mailer = Life_Travel_Abandoned_Cart_Mailer::get_instance();
        $success_count = 0;
        
        foreach ($cart_ids as $cart_id) {
            if ($mailer->send_recovery_email($cart_id)) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            $this->add_admin_notice(
                sprintf(
                    __('%d email(s) de récupération envoyé(s) avec succès.', 'life-travel-excursion'),
                    $success_count
                ),
                'success'
            );
        } else {
            $this->add_admin_notice(__('Impossible d\'envoyer les emails de récupération.', 'life-travel-excursion'), 'error');
        }
    }
    
    /**
     * Traite la suppression en masse de paniers
     */
    private function process_bulk_delete() {
        $cart_ids = isset($_REQUEST['cart_id']) ? array_map('absint', (array) $_REQUEST['cart_id']) : array();
        
        if (empty($cart_ids)) {
            $this->add_admin_notice(__('Aucun panier sélectionné.', 'life-travel-excursion'), 'error');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Sécurise la liste des IDs pour la requête
        $placeholders = implode(',', array_fill(0, count($cart_ids), '%d'));
        
        $query = $wpdb->prepare(
            "DELETE FROM {$table_name} WHERE id IN ({$placeholders})",
            $cart_ids
        );
        
        $result = $wpdb->query($query);
        
        if ($result !== false) {
            $this->add_admin_notice(
                sprintf(
                    __('%d panier(s) supprimé(s) avec succès.', 'life-travel-excursion'),
                    $result
                ),
                'success'
            );
        } else {
            $this->add_admin_notice(__('Impossible de supprimer les paniers.', 'life-travel-excursion'), 'error');
        }
    }
    
    /**
     * Enregistre les paramètres des paniers abandonnés
     */
    private function save_cart_settings() {
        $settings = isset($_POST['settings']) ? (array) $_POST['settings'] : array();
        
        // Validation et assainissement des données
        $sanitized_settings = array(
            'enable_recovery' => isset($settings['enable_recovery']) ? 'yes' : 'no',
            'recovery_wait_time' => isset($settings['recovery_wait_time']) ? absint($settings['recovery_wait_time']) : 60,
            'send_email_automatically' => isset($settings['send_email_automatically']) ? 'yes' : 'no',
            'email_template' => isset($settings['email_template']) ? sanitize_text_field($settings['email_template']) : 'default',
            'email_subject' => isset($settings['email_subject']) ? sanitize_text_field($settings['email_subject']) : '',
            'recovery_link_expiry' => isset($settings['recovery_link_expiry']) ? absint($settings['recovery_link_expiry']) : 7,
            'max_recovery_emails' => isset($settings['max_recovery_emails']) ? absint($settings['max_recovery_emails']) : 3,
            'email_interval' => isset($settings['email_interval']) ? absint($settings['email_interval']) : 24
        );
        
        // Enregistrer les paramètres
        update_option('life_travel_abandoned_cart_settings', $sanitized_settings);
        
        $this->add_admin_notice(__('Paramètres enregistrés avec succès.', 'life-travel-excursion'), 'success');
    }
    
    /**
     * Ajoute une notification d'administration
     * 
     * @param string $message Message à afficher
     * @param string $type Type de notification (success, error, warning, info)
     */
    private function add_admin_notice($message, $type = 'info') {
        $notices = get_transient('life_travel_admin_notices') ?: array();
        
        $notices[] = array(
            'message' => $message,
            'type' => $type
        );
        
        set_transient('life_travel_admin_notices', $notices, 60);
    }
    
    /**
     * Affiche les notifications d'administration
     */
    private function display_admin_notices() {
        $notices = get_transient('life_travel_admin_notices');
        
        if ($notices) {
            foreach ($notices as $notice) {
                echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible">';
                echo '<p>' . esc_html($notice['message']) . '</p>';
                echo '</div>';
            }
            
            delete_transient('life_travel_admin_notices');
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
}
