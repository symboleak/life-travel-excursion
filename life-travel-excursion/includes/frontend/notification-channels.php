<?php
/**
 * Gestion des canaux de notification
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

/**
 * Classe de gestion des canaux de notification
 */
class Life_Travel_Notification_Channels {
    /**
     * Instance de la classe
     */
    private static $instance = null;
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Hooks pour gérer les canaux de notification
        add_action('lte_send_notification', [$this, 'send_notification'], 10, 4);
    }
    
    /**
     * Récupère l'instance unique
     *
     * @return Life_Travel_Notification_Channels
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Envoie une notification sur les canaux appropriés
     *
     * @param int $user_id ID de l'utilisateur destinataire
     * @param string $notification_type Type de notification
     * @param array $data Données pour le modèle
     * @param array $channels Canaux spécifiques à utiliser (facultatif)
     * @return array Résultats des envois
     */
    public function send_notification($user_id, $notification_type, $data = [], $channels = []) {
        // Récupérer le gestionnaire de notifications
        $notifications = Life_Travel_User_Notifications::get_instance();
        
        // Vérifier si l'utilisateur doit recevoir ce type de notification
        if (!$notifications->should_notify_user($user_id, $notification_type)) {
            return [
                'status' => 'skipped',
                'message' => sprintf(__('L\'utilisateur %d a désactivé les notifications de type %s', 'life-travel-excursion'), $user_id, $notification_type)
            ];
        }
        
        // Récupérer les préférences de l'utilisateur
        $preferences = $notifications->get_user_preferences($user_id);
        
        // Récupérer les informations de contact de l'utilisateur
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return [
                'status' => 'error',
                'message' => sprintf(__('Utilisateur %d introuvable', 'life-travel-excursion'), $user_id)
            ];
        }
        
        // Ajouter les informations utilisateur aux données
        $data = $this->add_user_data($data, $user);
        
        // Déterminer les canaux à utiliser
        $channels_to_use = $this->get_channels_to_use($preferences, $channels);
        
        // Envoyer la notification sur chaque canal activé
        $results = [];
        
        foreach ($channels_to_use as $channel) {
            $method = 'send_via_' . $channel;
            
            if (method_exists($this, $method)) {
                try {
                    $result = $this->$method($user, $notification_type, $data);
                    $results[$channel] = $result;
                } catch (Exception $e) {
                    $results[$channel] = [
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                    
                    // Log l'erreur
                    $this->log_error(sprintf(
                        'Erreur lors de l\'envoi de notification %s via %s à l\'utilisateur %d: %s',
                        $notification_type,
                        $channel,
                        $user_id,
                        $e->getMessage()
                    ));
                }
            }
        }
        
        return [
            'status' => 'complete',
            'channels' => $results
        ];
    }
    
    /**
     * Ajoute les données utilisateur aux données de modèle
     *
     * @param array $data Données existantes
     * @param WP_User $user Utilisateur
     * @return array Données complétées
     */
    private function add_user_data($data, $user) {
        // Données utilisateur de base
        $user_data = [
            '{customer_name}' => $user->display_name,
            '{customer_first_name}' => $user->first_name ?: $user->display_name,
            '{customer_last_name}' => $user->last_name,
            '{customer_email}' => $user->user_email,
            '{customer_phone}' => get_user_meta($user->ID, 'billing_phone', true),
            '{account_url}' => wc_get_page_permalink('myaccount'),
            '{login_url}' => wp_login_url()
        ];
        
        return array_merge($user_data, $data);
    }
    
    /**
     * Détermine les canaux à utiliser pour une notification
     *
     * @param array $preferences Préférences de l'utilisateur
     * @param array $specific_channels Canaux spécifiques demandés
     * @return array Canaux à utiliser
     */
    private function get_channels_to_use($preferences, $specific_channels = []) {
        $channels_to_use = [];
        
        // Si des canaux spécifiques sont demandés, les utiliser
        if (!empty($specific_channels)) {
            $channels_to_use = $specific_channels;
        } 
        // Sinon, utiliser les préférences de l'utilisateur
        else {
            foreach ($preferences['channels'] as $channel => $enabled) {
                if ($enabled) {
                    $channels_to_use[] = $channel;
                }
            }
        }
        
        // Vérifier que les canaux sont disponibles
        $available_channels = [];
        foreach ($channels_to_use as $channel) {
            if ($this->is_channel_available($channel)) {
                $available_channels[] = $channel;
            }
        }
        
        return $available_channels;
    }
    
    /**
     * Vérifie si un canal est disponible
     *
     * @param string $channel Canal à vérifier
     * @return bool
     */
    private function is_channel_available($channel) {
        switch ($channel) {
            case 'email':
                return true;
                
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
     * Envoie une notification par email
     *
     * @param WP_User $user Utilisateur destinataire
     * @param string $notification_type Type de notification
     * @param array $data Données pour le modèle
     * @return array Résultat de l'envoi
     */
    private function send_via_email($user, $notification_type, $data) {
        // Récupérer les modèles de notification
        $templates = Life_Travel_Notification_Templates::get_instance();
        
        // Récupérer le sujet et le contenu
        $subject = $templates->get_template($notification_type, 'subject');
        $content = $templates->get_template($notification_type, 'email');
        
        // Remplacer les variables dans le sujet et le contenu
        $subject = $templates->replace_template_variables($subject, $data);
        $content = $templates->replace_template_variables($content, $data);
        
        // Ajouter la variable du titre de l'email pour le template HTML
        $email_data = array_merge($data, ['{email_title}' => $subject]);
        $content = $templates->replace_template_variables($content, $email_data);
        
        // Envoyer l'email
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $result = wp_mail($user->user_email, $subject, $content, $headers);
        
        if ($result) {
            return [
                'status' => 'success',
                'message' => sprintf(__('Email envoyé avec succès à %s', 'life-travel-excursion'), $user->user_email)
            ];
        } else {
            return [
                'status' => 'error',
                'message' => sprintf(__('Échec de l\'envoi de l\'email à %s', 'life-travel-excursion'), $user->user_email)
            ];
        }
    }
    
    /**
     * Envoie une notification par SMS via Twilio
     *
     * @param WP_User $user Utilisateur destinataire
     * @param string $notification_type Type de notification
     * @param array $data Données pour le modèle
     * @return array Résultat de l'envoi
     */
    private function send_via_sms($user, $notification_type, $data) {
        // Récupérer le numéro de téléphone de l'utilisateur
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        
        if (empty($phone)) {
            return [
                'status' => 'error',
                'message' => sprintf(__('Numéro de téléphone non défini pour l\'utilisateur %s', 'life-travel-excursion'), $user->display_name)
            ];
        }
        
        // Récupérer les modèles de notification
        $templates = Life_Travel_Notification_Templates::get_instance();
        
        // Récupérer le contenu SMS
        $content = $templates->get_template($notification_type, 'sms');
        
        // Remplacer les variables
        $content = $templates->replace_template_variables($content, $data);
        
        // Envoyer le SMS via Twilio
        $twilio_sid = get_option('lte_twilio_sid', '');
        $twilio_token = get_option('lte_twilio_token', '');
        $twilio_phone = get_option('lte_twilio_phone', '');
        
        // Vérifier que Twilio est configuré
        if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_phone)) {
            return [
                'status' => 'error',
                'message' => __('Configuration Twilio incomplète pour l\'envoi de SMS', 'life-travel-excursion')
            ];
        }
        
        // Formater le numéro de téléphone
        $phone = $this->format_phone_number($phone);
        
        // Charger la bibliothèque Twilio
        if (!$this->load_twilio_sdk()) {
            return [
                'status' => 'error',
                'message' => __('Impossible de charger le SDK Twilio', 'life-travel-excursion')
            ];
        }
        
        try {
            // Initialiser le client Twilio
            $client = new \Twilio\Rest\Client($twilio_sid, $twilio_token);
            
            // Envoyer le SMS
            $message = $client->messages->create(
                $phone,
                [
                    'from' => $twilio_phone,
                    'body' => $content
                ]
            );
            
            return [
                'status' => 'success',
                'message' => sprintf(__('SMS envoyé avec succès à %s (SID: %s)', 'life-travel-excursion'), $phone, $message->sid)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => sprintf(__('Erreur lors de l\'envoi du SMS: %s', 'life-travel-excursion'), $e->getMessage())
            ];
        }
    }
    
    /**
     * Envoie une notification par WhatsApp via Twilio
     *
     * @param WP_User $user Utilisateur destinataire
     * @param string $notification_type Type de notification
     * @param array $data Données pour le modèle
     * @return array Résultat de l'envoi
     */
    private function send_via_whatsapp($user, $notification_type, $data) {
        // Récupérer le numéro de téléphone de l'utilisateur
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        
        if (empty($phone)) {
            return [
                'status' => 'error',
                'message' => sprintf(__('Numéro de téléphone non défini pour l\'utilisateur %s', 'life-travel-excursion'), $user->display_name)
            ];
        }
        
        // Récupérer les modèles de notification
        $templates = Life_Travel_Notification_Templates::get_instance();
        
        // Récupérer le contenu WhatsApp
        $content = $templates->get_template($notification_type, 'whatsapp');
        
        // Remplacer les variables
        $content = $templates->replace_template_variables($content, $data);
        
        // Envoyer le message WhatsApp via Twilio
        $twilio_sid = get_option('lte_twilio_sid', '');
        $twilio_token = get_option('lte_twilio_token', '');
        $twilio_whatsapp = get_option('lte_twilio_whatsapp', '');
        
        // Vérifier que Twilio est configuré
        if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_whatsapp)) {
            return [
                'status' => 'error',
                'message' => __('Configuration Twilio incomplète pour l\'envoi de WhatsApp', 'life-travel-excursion')
            ];
        }
        
        // Formater le numéro de téléphone
        $phone = $this->format_phone_number($phone);
        
        // Charger la bibliothèque Twilio
        if (!$this->load_twilio_sdk()) {
            return [
                'status' => 'error',
                'message' => __('Impossible de charger le SDK Twilio', 'life-travel-excursion')
            ];
        }
        
        try {
            // Initialiser le client Twilio
            $client = new \Twilio\Rest\Client($twilio_sid, $twilio_token);
            
            // Envoyer le message WhatsApp
            $message = $client->messages->create(
                'whatsapp:' . $phone,
                [
                    'from' => 'whatsapp:' . $twilio_whatsapp,
                    'body' => $content
                ]
            );
            
            return [
                'status' => 'success',
                'message' => sprintf(__('Message WhatsApp envoyé avec succès à %s (SID: %s)', 'life-travel-excursion'), $phone, $message->sid)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => sprintf(__('Erreur lors de l\'envoi du message WhatsApp: %s', 'life-travel-excursion'), $e->getMessage())
            ];
        }
    }
    
    /**
     * Formate un numéro de téléphone au format E.164
     * 
     * @param string $phone Numéro de téléphone à formater
     * @return string Numéro formaté
     */
    private function format_phone_number($phone) {
        // Supprimer tous les caractères non numériques sauf le +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // S'assurer que le numéro commence par +
        if (substr($phone, 0, 1) !== '+') {
            // Si c'est un numéro camerounais sans indicatif
            if (strlen($phone) === 9 && (substr($phone, 0, 1) === '6' || substr($phone, 0, 1) === '2')) {
                $phone = '+237' . $phone;
            } else {
                $phone = '+' . $phone;
            }
        }
        
        return $phone;
    }
    
    /**
     * Charge la bibliothèque Twilio SDK
     * 
     * @return bool Succès ou échec
     */
    private function load_twilio_sdk() {
        // Vérifier si la classe est déjà disponible
        if (class_exists('Twilio\\Rest\\Client')) {
            return true;
        }
        
        // Essayer de charger via notre autoloader Composer
        $composer_autoload = LIFE_TRAVEL_EXCURSION_DIR . 'vendor/autoload.php';
        if (file_exists($composer_autoload)) {
            require_once $composer_autoload;
            if (class_exists('Twilio\\Rest\\Client')) {
                return true;
            }
        }
        
        // Essayer de charger depuis des emplacements alternatifs courants
        $alt_locations = [
            ABSPATH . 'wp-content/plugins/twilio-core/includes/vendor/autoload.php',
            ABSPATH . 'wp-content/plugins/twilio-sms/vendor/autoload.php',
            WP_PLUGIN_DIR . '/twilio-core/includes/vendor/autoload.php',
            WP_PLUGIN_DIR . '/twilio-sms/vendor/autoload.php'
        ];
        
        foreach ($alt_locations as $file) {
            if (file_exists($file)) {
                require_once $file;
                if (class_exists('Twilio\\Rest\\Client')) {
                    return true;
                }
            }
        }
        
        // Essayer de charger la bibliothèque directement si elle existe
        if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'vendor/twilio/sdk/src/Twilio/autoload.php')) {
            require_once LIFE_TRAVEL_EXCURSION_DIR . 'vendor/twilio/sdk/src/Twilio/autoload.php';
            if (class_exists('Twilio\\Rest\\Client')) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Journalise une erreur
     *
     * @param string $message Message d'erreur
     */
    private function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Life Travel Notifications] ' . $message);
        }
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_Notification_Channels::get_instance();
});
