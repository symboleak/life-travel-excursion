<?php
/**
 * Gestion des notifications utilisateur
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

/**
 * Classe de gestion des notifications utilisateur
 */
class Life_Travel_User_Notifications {
    /**
     * Instance de la classe
     */
    private static $instance = null;
    
    /**
     * Types de notifications disponibles
     */
    private $notification_types = [];
    
    /**
     * Canaux de notification disponibles
     */
    private $notification_channels = [];
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Initialiser les types de notifications
        $this->init_notification_types();
        
        // Initialiser les canaux de notification
        $this->init_notification_channels();
        
        // Ajouter les hooks pour sauvegarder les préférences utilisateur
        add_action('woocommerce_save_account_details', [$this, 'save_notification_preferences']);
        add_action('user_register', [$this, 'set_default_preferences'], 10, 1);
        
        // Hooks pour les événements de notification
        add_action('woocommerce_order_status_changed', [$this, 'notify_order_status_change'], 20, 4);
        add_action('woocommerce_new_order', [$this, 'notify_new_order'], 20, 1);
        add_action('woocommerce_created_customer', [$this, 'notify_account_created'], 20, 3);
        
        // Hook pour les rappels d'excursion
        add_action('lte_daily_excursion_reminders', [$this, 'send_excursion_reminders']);
    }
    
    /**
     * Récupère l'instance unique
     *
     * @return Life_Travel_User_Notifications
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialise les types de notifications disponibles
     */
    private function init_notification_types() {
        $this->notification_types = [
            'order_confirmation' => [
                'name' => __('Confirmation de commande', 'life-travel-excursion'),
                'description' => __('Notification quand une nouvelle commande est passée', 'life-travel-excursion'),
                'default' => true,
                'required' => true,
                'group' => 'orders'
            ],
            'order_status' => [
                'name' => __('Changement de statut de commande', 'life-travel-excursion'),
                'description' => __('Notification quand le statut d\'une commande change', 'life-travel-excursion'),
                'default' => true,
                'required' => false,
                'group' => 'orders'
            ],
            'excursion_reminder' => [
                'name' => __('Rappel d\'excursion', 'life-travel-excursion'),
                'description' => __('Rappel avant la date d\'une excursion réservée', 'life-travel-excursion'),
                'default' => true,
                'required' => false,
                'group' => 'orders'
            ],
            'account_created' => [
                'name' => __('Création de compte', 'life-travel-excursion'),
                'description' => __('Notification quand un nouveau compte est créé', 'life-travel-excursion'),
                'default' => true,
                'required' => true,
                'group' => 'account'
            ],
            'password_reset' => [
                'name' => __('Réinitialisation de mot de passe', 'life-travel-excursion'),
                'description' => __('Notification de réinitialisation de mot de passe', 'life-travel-excursion'),
                'default' => true,
                'required' => true,
                'group' => 'account'
            ],
            'new_login' => [
                'name' => __('Nouvelle connexion', 'life-travel-excursion'),
                'description' => __('Notification lors d\'une connexion depuis un nouvel appareil', 'life-travel-excursion'),
                'default' => true,
                'required' => false,
                'group' => 'account'
            ],
            'promotions' => [
                'name' => __('Promotions et offres', 'life-travel-excursion'),
                'description' => __('Notifications de promotions et offres spéciales', 'life-travel-excursion'),
                'default' => false,
                'required' => false,
                'group' => 'marketing'
            ],
            'news' => [
                'name' => __('Actualités et nouveautés', 'life-travel-excursion'),
                'description' => __('Informations sur les nouvelles excursions et actualités', 'life-travel-excursion'),
                'default' => false,
                'required' => false,
                'group' => 'marketing'
            ]
        ];
        
        // Permettre l'ajout de types de notifications supplémentaires
        $this->notification_types = apply_filters('lte_notification_types', $this->notification_types);
    }
    
    /**
     * Initialise les canaux de notification disponibles
     */
    private function init_notification_channels() {
        $this->notification_channels = [
            'email' => [
                'name' => __('Email', 'life-travel-excursion'),
                'description' => __('Recevoir les notifications par email', 'life-travel-excursion'),
                'default' => true,
                'icon' => 'dashicons-email-alt',
                'always_available' => true
            ],
            'sms' => [
                'name' => __('SMS', 'life-travel-excursion'),
                'description' => __('Recevoir les notifications par SMS', 'life-travel-excursion'),
                'default' => false,
                'icon' => 'dashicons-smartphone',
                'always_available' => false
            ],
            'whatsapp' => [
                'name' => __('WhatsApp', 'life-travel-excursion'),
                'description' => __('Recevoir les notifications par WhatsApp', 'life-travel-excursion'),
                'default' => false,
                'icon' => 'dashicons-whatsapp',
                'always_available' => false
            ]
        ];
        
        // Permettre l'ajout de canaux de notification supplémentaires
        $this->notification_channels = apply_filters('lte_notification_channels', $this->notification_channels);
    }
    
    /**
     * Vérifie si un canal de notification est disponible
     *
     * @param string $channel Canal à vérifier
     * @return bool
     */
    public function is_channel_available($channel) {
        if (!isset($this->notification_channels[$channel])) {
            return false;
        }
        
        if ($this->notification_channels[$channel]['always_available']) {
            return true;
        }
        
        // Vérifier la disponibilité des canaux spécifiques
        switch ($channel) {
            case 'sms':
                return $this->is_sms_available();
            case 'whatsapp':
                return $this->is_whatsapp_available();
            default:
                return false;
        }
    }
    
    /**
     * Vérifie si les SMS sont disponibles
     *
     * @return bool
     */
    private function is_sms_available() {
        $twilio_sid = get_option('lte_twilio_sid', '');
        $twilio_token = get_option('lte_twilio_token', '');
        $twilio_phone = get_option('lte_twilio_phone', '');
        
        return !empty($twilio_sid) && !empty($twilio_token) && !empty($twilio_phone);
    }
    
    /**
     * Vérifie si WhatsApp est disponible
     *
     * @return bool
     */
    private function is_whatsapp_available() {
        $twilio_sid = get_option('lte_twilio_sid', '');
        $twilio_token = get_option('lte_twilio_token', '');
        $twilio_whatsapp = get_option('lte_twilio_whatsapp', '');
        
        return !empty($twilio_sid) && !empty($twilio_token) && !empty($twilio_whatsapp);
    }
    
    /**
     * Récupère les préférences de notification d'un utilisateur
     *
     * @param int $user_id ID de l'utilisateur
     * @return array Préférences de notification
     */
    public function get_user_preferences($user_id) {
        $preferences = get_user_meta($user_id, '_lte_notification_preferences', true);
        
        if (empty($preferences) || !is_array($preferences)) {
            // Initialiser avec les valeurs par défaut
            $preferences = $this->get_default_preferences();
        }
        
        return $preferences;
    }
    
    /**
     * Récupère les préférences de notification par défaut
     *
     * @return array Préférences par défaut
     */
    public function get_default_preferences() {
        $default_preferences = [
            'channels' => [],
            'types' => []
        ];
        
        // Canaux par défaut
        foreach ($this->notification_channels as $channel_id => $channel) {
            $default_preferences['channels'][$channel_id] = $channel['default'];
        }
        
        // Types de notifications par défaut
        foreach ($this->notification_types as $type_id => $type) {
            $default_preferences['types'][$type_id] = $type['default'];
        }
        
        return $default_preferences;
    }
    
    /**
     * Définit les préférences par défaut pour un nouvel utilisateur
     *
     * @param int $user_id ID de l'utilisateur
     */
    public function set_default_preferences($user_id) {
        $default_preferences = $this->get_default_preferences();
        update_user_meta($user_id, '_lte_notification_preferences', $default_preferences);
    }
    
    /**
     * Sauvegarde les préférences de notification d'un utilisateur
     *
     * @param int $user_id ID de l'utilisateur
     */
    public function save_notification_preferences($user_id) {
        if (!isset($_POST['lte_notification_preferences_nonce']) || 
            !wp_verify_nonce($_POST['lte_notification_preferences_nonce'], 'lte_save_notification_preferences')) {
            return;
        }
        
        $preferences = [
            'channels' => [],
            'types' => []
        ];
        
        // Récupérer les canaux sélectionnés
        foreach ($this->notification_channels as $channel_id => $channel) {
            $field_name = 'lte_notification_channel_' . $channel_id;
            $preferences['channels'][$channel_id] = isset($_POST[$field_name]) ? true : false;
            
            // Si le canal n'est pas disponible, le désactiver
            if (!$this->is_channel_available($channel_id)) {
                $preferences['channels'][$channel_id] = false;
            }
        }
        
        // Récupérer les types de notifications sélectionnés
        foreach ($this->notification_types as $type_id => $type) {
            $field_name = 'lte_notification_type_' . $type_id;
            $preferences['types'][$type_id] = isset($_POST[$field_name]) ? true : false;
            
            // Si le type est requis, le forcer à true
            if ($type['required']) {
                $preferences['types'][$type_id] = true;
            }
        }
        
        // Mettre à jour les préférences
        update_user_meta($user_id, '_lte_notification_preferences', $preferences);
    }
    
    /**
     * Vérifie si un utilisateur doit recevoir un type de notification
     *
     * @param int $user_id ID de l'utilisateur
     * @param string $notification_type Type de notification
     * @return bool
     */
    public function should_notify_user($user_id, $notification_type) {
        $preferences = $this->get_user_preferences($user_id);
        
        // Vérifier si l'utilisateur a activé ce type de notification
        if (isset($preferences['types'][$notification_type]) && $preferences['types'][$notification_type]) {
            // Vérifier s'il y a au moins un canal actif
            foreach ($preferences['channels'] as $channel => $enabled) {
                if ($enabled && $this->is_channel_available($channel)) {
                    return true;
                }
            }
        }
        
        return false;
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_User_Notifications::get_instance();
});
