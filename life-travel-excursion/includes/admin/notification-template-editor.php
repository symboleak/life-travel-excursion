<?php
/**
 * Interface d'administration pour les modèles de notification
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

/**
 * Classe pour l'interface d'administration des modèles de notification
 */
class Life_Travel_Notification_Template_Editor {
    /**
     * Instance de la classe
     */
    private static $instance = null;
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Hooks d'administration
        add_action('admin_menu', [$this, 'add_template_editor_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // AJAX pour la prévisualisation et la sauvegarde
        add_action('wp_ajax_lte_preview_template', [$this, 'ajax_preview_template']);
        add_action('wp_ajax_lte_save_template', [$this, 'ajax_save_template']);
    }
    
    /**
     * Récupère l'instance unique
     *
     * @return Life_Travel_Notification_Template_Editor
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Ajoute la page d'édition des modèles au menu admin
     */
    public function add_template_editor_menu() {
        add_submenu_page(
            'woocommerce',
            __('Modèles de notification', 'life-travel-excursion'),
            __('Modèles de notification', 'life-travel-excursion'),
            'manage_options',
            'lte-notification-templates',
            [$this, 'render_template_editor_page']
        );
    }
    
    /**
     * Charge les scripts et styles pour l'admin
     */
    public function enqueue_admin_scripts($hook) {
        if ('woocommerce_page_lte-notification-templates' !== $hook) {
            return;
        }
        
        // Charger CodeMirror pour l'éditeur de texte avancé
        wp_enqueue_code_editor(['type' => 'text/html']);
        wp_enqueue_script('wp-theme-plugin-editor');
        
        // Styles et scripts personnalisés
        wp_enqueue_style('lte-template-editor-style', LIFE_TRAVEL_EXCURSION_URL . 'assets/css/template-editor.css', [], LIFE_TRAVEL_EXCURSION_VERSION);
        wp_enqueue_script('lte-template-editor-script', LIFE_TRAVEL_EXCURSION_URL . 'assets/js/template-editor.js', ['jquery', 'wp-theme-plugin-editor'], LIFE_TRAVEL_EXCURSION_VERSION, true);
        
        // Ajouter les données localisées
        wp_localize_script('lte-template-editor-script', 'lteTemplateEditor', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lte_template_editor'),
            'previewNonce' => wp_create_nonce('lte_preview_template'),
            'saveNonce' => wp_create_nonce('lte_save_template'),
            'previewError' => __('Erreur lors de la génération de la prévisualisation.', 'life-travel-excursion'),
            'saveSuccess' => __('Modèle enregistré avec succès.', 'life-travel-excursion'),
            'saveError' => __('Erreur lors de l\'enregistrement du modèle.', 'life-travel-excursion')
        ]);
    }
    
    /**
     * Affiche la page d'édition des modèles
     */
    public function render_template_editor_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.', 'life-travel-excursion'));
        }
        
        // Récupérer le modèle à éditer
        $notification_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'order_confirmation';
        $channel = isset($_GET['channel']) ? sanitize_text_field($_GET['channel']) : 'email';
        
        // Récupérer le gestionnaire de modèles
        $templates = Life_Travel_Notification_Templates::get_instance();
        
        // Récupérer les types et canaux de notification disponibles
        $notification_types = $this->get_notification_types();
        $notification_channels = $this->get_notification_channels();
        
        // Récupérer les variables disponibles
        $available_variables = $this->get_available_variables();
        
        // Récupérer le contenu du modèle
        $template_content = $templates->get_template($notification_type, $channel);
        
        // Récupérer le contenu par défaut pour comparaison
        $default_content = $templates->get_default_template($notification_type, $channel);
        
        // Vérifier s'il s'agit du sujet d'email
        $is_subject = $channel === 'subject';
        
        // Afficher l'interface
        include LIFE_TRAVEL_EXCURSION_DIR . 'templates/admin/template-editor.php';
    }
    
    /**
     * Récupère les types de notification disponibles
     *
     * @return array Types de notification
     */
    private function get_notification_types() {
        $notifications = Life_Travel_User_Notifications::get_instance();
        return $this->get_property($notifications, 'notification_types');
    }
    
    /**
     * Récupère les canaux de notification disponibles
     *
     * @return array Canaux de notification
     */
    private function get_notification_channels() {
        $notifications = Life_Travel_User_Notifications::get_instance();
        return $this->get_property($notifications, 'notification_channels');
    }
    
    /**
     * Récupère les variables disponibles
     *
     * @return array Variables disponibles
     */
    private function get_available_variables() {
        $templates = Life_Travel_Notification_Templates::get_instance();
        return $this->get_property($templates, 'available_variables');
    }
    
    /**
     * Récupère une propriété protégée/privée d'un objet de manière sécurisée
     *
     * @param object $object Objet
     * @param string $property Nom de la propriété
     * @return mixed Valeur de la propriété ou tableau vide
     */
    private function get_property($object, $property) {
        try {
            $reflection = new ReflectionClass($object);
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            return $prop->getValue($object);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Traite les requêtes AJAX pour prévisualiser un modèle
     */
    public function ajax_preview_template() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lte_preview_template')) {
            wp_send_json_error(['message' => __('Erreur de sécurité. Veuillez rafraîchir la page.', 'life-travel-excursion')]);
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Vous n\'avez pas les permissions nécessaires.', 'life-travel-excursion')]);
            return;
        }
        
        // Récupérer les données
        $template_content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
        $notification_type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $channel = isset($_POST['channel']) ? sanitize_text_field($_POST['channel']) : '';
        
        if (empty($template_content) || empty($notification_type) || empty($channel)) {
            wp_send_json_error(['message' => __('Données invalides.', 'life-travel-excursion')]);
            return;
        }
        
        // Récupérer le gestionnaire de modèles
        $templates = Life_Travel_Notification_Templates::get_instance();
        
        // Préparer des données d'exemple
        $sample_data = $this->get_sample_data($notification_type);
        
        // Remplacer les variables dans le modèle
        $preview_content = $templates->replace_template_variables($template_content, $sample_data);
        
        // Si c'est un email, ajouter le formatage HTML nécessaire
        if ($channel === 'email') {
            // Ajouter les variables du titre et logo pour le template HTML
            $email_data = array_merge($sample_data, [
                '{email_title}' => 'Prévisualisation du modèle',
                '{logo_url}' => $this->get_logo_url()
            ]);
            $preview_content = $templates->replace_template_variables($preview_content, $email_data);
        }
        
        wp_send_json_success([
            'preview' => $preview_content,
            'sample_data' => $sample_data
        ]);
    }
    
    /**
     * Traite les requêtes AJAX pour sauvegarder un modèle
     */
    public function ajax_save_template() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lte_save_template')) {
            wp_send_json_error(['message' => __('Erreur de sécurité. Veuillez rafraîchir la page.', 'life-travel-excursion')]);
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Vous n\'avez pas les permissions nécessaires.', 'life-travel-excursion')]);
            return;
        }
        
        // Récupérer les données
        $template_content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
        $notification_type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $channel = isset($_POST['channel']) ? sanitize_text_field($_POST['channel']) : '';
        
        if (empty($notification_type) || empty($channel)) {
            wp_send_json_error(['message' => __('Données invalides.', 'life-travel-excursion')]);
            return;
        }
        
        // Sauvegarder le modèle
        $option_name = "lte_notification_template_{$notification_type}_{$channel}";
        update_option($option_name, $template_content);
        
        wp_send_json_success(['message' => __('Modèle enregistré avec succès.', 'life-travel-excursion')]);
    }
    
    /**
     * Récupère des données d'exemple pour prévisualiser un modèle
     *
     * @param string $notification_type Type de notification
     * @return array Données d'exemple
     */
    private function get_sample_data($notification_type) {
        // Données de base communes à tous les modèles
        $common_data = [
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => get_bloginfo('url'),
            '{current_date}' => date_i18n(get_option('date_format')),
            '{current_year}' => date('Y'),
            '{customer_name}' => 'Jean Dupont',
            '{customer_first_name}' => 'Jean',
            '{customer_last_name}' => 'Dupont',
            '{customer_email}' => 'client@example.com',
            '{customer_phone}' => '+237612345678',
            '{account_url}' => wc_get_page_permalink('myaccount'),
            '{login_url}' => wp_login_url()
        ];
        
        // Données spécifiques selon le type de notification
        $specific_data = [];
        
        switch ($notification_type) {
            case 'order_confirmation':
            case 'order_status':
                $specific_data = [
                    '{order_number}' => '1234',
                    '{order_date}' => date_i18n(get_option('date_format')),
                    '{order_total}' => wc_price(150000),
                    '{order_status}' => __('En attente de paiement', 'life-travel-excursion'),
                    '{payment_method}' => 'Orange Money',
                    '{order_details_url}' => wc_get_endpoint_url('view-order', '1234', wc_get_page_permalink('myaccount')),
                    '{product_name}' => 'Excursion aux Chutes de la Lobé',
                    '{excursion_date}' => date_i18n(get_option('date_format'), strtotime('+2 weeks')),
                    '{participants_count}' => '2',
                    '{meeting_point}' => 'Hôtel Ibis, Douala',
                    '{meeting_time}' => '08:00'
                ];
                break;
                
            case 'excursion_reminder':
                $specific_data = [
                    '{order_number}' => '1234',
                    '{product_name}' => 'Excursion aux Chutes de la Lobé',
                    '{excursion_date}' => date_i18n(get_option('date_format'), strtotime('+3 days')),
                    '{days_before}' => '3',
                    '{participants_count}' => '2',
                    '{meeting_point}' => 'Hôtel Ibis, Douala',
                    '{meeting_time}' => '08:00',
                    '{order_details_url}' => wc_get_endpoint_url('view-order', '1234', wc_get_page_permalink('myaccount'))
                ];
                break;
                
            case 'account_created':
                // Utiliser seulement les données communes
                break;
                
            case 'password_reset':
                $specific_data = [
                    '{reset_link}' => wp_login_url() . '?action=rp&key=sample_key&login=sample_login',
                    '{reset_expiry}' => '24 heures'
                ];
                break;
                
            case 'new_login':
                $specific_data = [
                    '{login_date}' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
                    '{login_location}' => 'Yaoundé, Cameroun',
                    '{login_device}' => 'iPhone (Safari)',
                    '{login_ip}' => '192.168.1.1'
                ];
                break;
        }
        
        return array_merge($common_data, $specific_data);
    }
    
    /**
     * Récupère l'URL du logo du site
     *
     * @return string URL du logo
     */
    private function get_logo_url() {
        $custom_logo_id = get_theme_mod('custom_logo');
        
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            return $logo_url ? $logo_url : '';
        }
        
        // Logo par défaut si aucun logo personnalisé n'est défini
        return LIFE_TRAVEL_EXCURSION_URL . 'assets/images/logo.png';
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_Notification_Template_Editor::get_instance();
});
