<?php
/**
 * Intégration des notifications push pour le système de fidélité
 *
 * Permet d'envoyer des notifications push aux utilisateurs
 * lorsqu'ils gagnent des points de fidélité.
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
 * Classe pour gérer les notifications push liées au système de fidélité
 */
class Life_Travel_Loyalty_Push_Notifications {
    
    /**
     * Instance unique (pattern singleton)
     */
    private static $instance = null;
    
    /**
     * Constructor
     */
    private function __construct() {
        // Hooks pour l'envoi des notifications push
        add_action('lte_loyalty_points_awarded', array($this, 'send_points_push_notification'), 10, 3);
        
        // Ajouter les scripts nécessaires
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX pour l'inscription aux notifications
        add_action('wp_ajax_lte_register_push_device', array($this, 'register_push_device'));
        add_action('wp_ajax_nopriv_lte_register_push_device', array($this, 'register_push_device'));
    }
    
    /**
     * Retourne l'instance unique de la classe
     *
     * @return Life_Travel_Loyalty_Push_Notifications
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Enregistre les scripts nécessaires
     */
    public function enqueue_scripts() {
        if (!is_user_logged_in()) {
            return;
        }
        
        // Vérifier que l'utilisateur a activé les notifications
        $user_id = get_current_user_id();
        $notifications_enabled = get_user_meta($user_id, '_lte_push_notifications_enabled', true);
        
        if ($notifications_enabled !== 'yes') {
            // Ajouter un script pour demander la permission
            wp_enqueue_script(
                'lte-push-permission',
                LIFE_TRAVEL_EXCURSION_URL . 'assets/js/push-permission.js',
                array('jquery'),
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
            
            wp_localize_script('lte-push-permission', 'ltePushObj', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lte_push_nonce'),
                'askText' => __('Voulez-vous être notifié lorsque vous gagnez des points de fidélité ?', 'life-travel-excursion'),
                'userId' => $user_id
            ));
        }
    }
    
    /**
     * Enregistre un appareil pour les notifications push
     */
    public function register_push_device() {
        // Vérifier le nonce
        check_ajax_referer('lte_push_nonce', 'nonce');
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
        $device_token = isset($_POST['device_token']) ? sanitize_text_field($_POST['device_token']) : '';
        $device_type = isset($_POST['device_type']) ? sanitize_text_field($_POST['device_type']) : 'web';
        
        if (!$user_id || empty($device_token)) {
            wp_send_json_error(array('message' => 'Données invalides'));
            return;
        }
        
        // Stocker le token de l'appareil
        update_user_meta($user_id, '_lte_push_device_token', $device_token);
        update_user_meta($user_id, '_lte_push_device_type', $device_type);
        update_user_meta($user_id, '_lte_push_notifications_enabled', 'yes');
        
        // Réponse de succès
        wp_send_json_success(array('message' => 'Appareil enregistré avec succès'));
    }
    
    /**
     * Envoie une notification push lorsque l'utilisateur gagne des points
     *
     * @param int $user_id ID de l'utilisateur
     * @param int $points Nombre de points gagnés
     * @param int $order_id ID de la commande
     */
    public function send_points_push_notification($user_id, $points, $order_id) {
        // Vérifier que l'utilisateur a activé les notifications
        $notifications_enabled = get_user_meta($user_id, '_lte_push_notifications_enabled', true);
        
        if ($notifications_enabled !== 'yes') {
            return;
        }
        
        // Récupérer le token de l'appareil
        $device_token = get_user_meta($user_id, '_lte_push_device_token', true);
        $device_type = get_user_meta($user_id, '_lte_push_device_type', true);
        
        if (empty($device_token)) {
            return;
        }
        
        // Préparer le message
        $title = __('Points de fidélité', 'life-travel-excursion');
        $message = sprintf(
            __('Félicitations ! Vous avez gagné %d points avec votre commande #%d.', 'life-travel-excursion'),
            $points,
            $order_id
        );
        
        $notification_data = array(
            'title' => $title,
            'message' => $message,
            'icon' => LIFE_TRAVEL_EXCURSION_URL . 'assets/images/loyalty-icon.png',
            'url' => wc_get_endpoint_url('loyalty', '', wc_get_page_permalink('myaccount')),
            'user_id' => $user_id,
            'order_id' => $order_id,
            'points' => $points,
            'type' => 'loyalty_points'
        );
        
        // Utiliser le gestionnaire de notifications push central si disponible
        if (class_exists('Life_Travel_Push_Notifications')
            && isset($GLOBALS['lte_push_notifications'])
            && is_object($GLOBALS['lte_push_notifications'])
            && method_exists($GLOBALS['lte_push_notifications'], 'send_notification_to_user')) {
            // Adapter le payload au format du gestionnaire central
            $payload = array(
                'title' => $notification_data['title'],
                'body'  => $notification_data['message'],
                'icon'  => $notification_data['icon'],
                'data'  => array(
                    'url'      => $notification_data['url'],
                    'type'     => $notification_data['type'],
                    'order_id' => $notification_data['order_id'],
                    'user_id'  => $notification_data['user_id'],
                    'points'   => $notification_data['points'],
                ),
            );
            $GLOBALS['lte_push_notifications']->send_notification_to_user($user_id, $payload);
        } elseif (class_exists('Life_Travel_Push_Notifications_Manager')) {
            // Appel indirect pour éviter l'erreur IDE "Undefined type"
            $push_manager = call_user_func(array('Life_Travel_Push_Notifications_Manager', 'get_instance'));
            if (is_object($push_manager) && method_exists($push_manager, 'send_notification')) {
                $push_manager->send_notification($device_token, $notification_data, $device_type);
            } else {
                // Fallback si l'instance ou la méthode n'est pas disponible
                $this->send_web_push_notification($device_token, $notification_data);
            }
        } else {
            // Méthode alternative si le gestionnaire n'est pas disponible
            $this->send_web_push_notification($device_token, $notification_data);
        }
        
        // Enregistrer la notification dans la base de données
        $this->log_push_notification($user_id, $notification_data);
    }
    
    /**
     * Envoie une notification push web (méthode alternative)
     *
     * @param string $device_token Token de l'appareil
     * @param array $notification_data Données de la notification
     */
    private function send_web_push_notification($device_token, $notification_data) {
        // Utilisation de l'API Web Push (supposant que vous avez configuré un service comme Firebase)
        $api_key = get_option('lte_firebase_server_key', '');
        
        if (empty($api_key)) {
            return;
        }
        
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        $headers = array(
            'Authorization: key=' . $api_key,
            'Content-Type: application/json'
        );
        
        $fields = array(
            'to' => $device_token,
            'notification' => array(
                'title' => $notification_data['title'],
                'body' => $notification_data['message'],
                'icon' => $notification_data['icon'],
                'click_action' => $notification_data['url']
            ),
            'data' => array(
                'type' => $notification_data['type'],
                'user_id' => $notification_data['user_id'],
                'order_id' => $notification_data['order_id'],
                'points' => $notification_data['points']
            )
        );
        
        $args = array(
            'body' => json_encode($fields),
            'headers' => $headers,
            'timeout' => 15
        );
        
        $response = wp_remote_post($url, $args);
        
        // Log pour le débogage
        if (is_wp_error($response)) {
            error_log('Erreur d\'envoi de notification push: ' . $response->get_error_message());
        }
    }
    
    /**
     * Enregistre la notification push dans la base de données
     *
     * @param int $user_id ID de l'utilisateur
     * @param array $notification_data Données de la notification
     */
    private function log_push_notification($user_id, $notification_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lte_push_notifications';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        // Insérer la notification
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'title' => $notification_data['title'],
                'message' => $notification_data['message'],
                'type' => $notification_data['type'],
                'data' => json_encode($notification_data),
                'is_read' => 0,
                'date_created' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );
    }
}

// Initialiser la classe
add_action('init', function() {
    Life_Travel_Loyalty_Push_Notifications::get_instance();
});

// Suppression d'un ancien hook récursif qui re-déclenchait
// 'lte_loyalty_points_awarded' et causait une boucle infinie.
// Le déclenchement de l'événement doit être fait à la source
// (système de points), pas depuis son propre gestionnaire.
