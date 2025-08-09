<?php
/**
 * Gestionnaire de notifications
 * 
 * Ce fichier gère l'envoi de notifications (SMS, email) en utilisant Twilio comme principal service
 * avec des options de fallback pour assurer la continuité des notifications
 * 
 * @package Life Travel Excursion
 * @version 2.3.3
 */

defined('ABSPATH') || exit;

// AJOUT_IMPORTANT: Installer la bibliothèque Twilio avec la commande suivante:
// composer require twilio/sdk

// Vérifier si la bibliothèque Twilio est disponible
$twilio_available = class_exists('Twilio\Rest\Client');

// Si Twilio n'est pas disponible, essayer de le charger via Composer si possible
if (!$twilio_available && file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'vendor/autoload.php')) {
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'vendor/autoload.php';
    $twilio_available = class_exists('Twilio\Rest\Client');
}

/**
 * Classe de gestion des notifications
 */
class Life_Travel_Notifications_Manager {
    /**
     * Singleton instance
     *
     * @var Life_Travel_Notifications_Manager
     */
    private static $instance = null;
    
    /**
     * Client Twilio si disponible
     *
     * @var Twilio\Rest\Client|null
     */
    private $twilio_client = null;
    
    /**
     * Options de configuration
     *
     * @var array
     */
    private $options;
    
    /**
     * Journal d'événements
     *
     * @var WC_Logger
     */
    private $logger;
    
    /**
     * Constructeur
     */
    private function __construct() {
        global $twilio_available;
        
        $this->options = get_option('life_travel_notifications_options', array(
            'sms_enabled' => 'yes',
            'email_enabled' => 'yes',
            // AJOUT_IMPORTANT: Ajoutez vos identifiants Twilio ici
            'twilio_sid' => '', // Votre Twilio Account SID
            'twilio_token' => '', // Votre Twilio Auth Token
            'twilio_phone' => '', // Votre numéro Twilio (format: +237...)
            // AJOUT_IMPORTANT: Configurez votre méthode de secours pour les notifications
            'fallback_gateway' => 'email', // 'email', 'api', 'email2sms'
            'api_key' => '', // Clé API alternative si vous utilisez 'api'
            'api_endpoint' => '', // URL API alternative si vous utilisez 'api'
            'email2sms_domain' => '', // Domaine si vous utilisez 'email2sms'
            'sender_id' => 'Life Travel', // Nom de l'expéditeur
            'debug_mode' => 'no'
        ));
        
        $this->logger = new WC_Logger();
        
        // Initialiser Twilio si disponible
        if ($twilio_available && !empty($this->options['twilio_sid']) && !empty($this->options['twilio_token'])) {
            try {
                $this->twilio_client = new \Twilio\Rest\Client(
                    $this->options['twilio_sid'],
                    $this->options['twilio_token']
                );
                $this->log('Twilio client initialized successfully');
            } catch (\Exception $e) {
                $this->log('Twilio initialization error: ' . $e->getMessage());
                $this->twilio_client = null;
            }
        } else {
            $this->log('Twilio not available or not configured, using fallback methods');
        }
        
        // Ajouter les hooks pour les événements importants
        add_action('woocommerce_order_status_changed', array($this, 'notify_order_status_change'), 10, 4);
        
        // Ajouter le hook pour les notifications d'expédition
        add_action('woocommerce_order_status_completed', array($this, 'notify_order_completed'), 10, 1);
    }
    
    /**
     * Récupère l'instance unique
     *
     * @return Life_Travel_Notifications_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Envoie une notification SMS
     *
     * @param string $recipient Le numéro de téléphone du destinataire
     * @param string $message Le contenu du message
     * @param string $type Le type de notification (sms, whatsapp)
     * @return bool Succès ou échec
     */
    public function send_notification($recipient, $message, $type = 'sms') {
        // Vérifier si les notifications sont activées
        if ($type == 'sms' && $this->options['sms_enabled'] !== 'yes') {
            $this->log("SMS notifications are disabled");
            return false;
        }
        
        // Log du message
        $this->log("Envoi de notification $type à $recipient: " . substr($message, 0, 100) . "...");
        
        // Nettoyer le numéro de téléphone
        $recipient = $this->sanitize_phone_number($recipient);
        
        // Limiter la longueur du message
        $message = $this->truncate_message($message, $type);
        
        $success = false;
        
        // Essayer d'abord avec Twilio si disponible
        if ($this->twilio_client !== null && !empty($this->options['twilio_phone'])) {
            try {
                $this->log("Tentative d'envoi via Twilio");
                
                if ($type === 'whatsapp') {
                    // Format spécifique pour WhatsApp via Twilio
                    $from = "whatsapp:" . $this->options['twilio_phone'];
                    $to = "whatsapp:" . $recipient;
                } else {
                    // Format standard pour SMS
                    $from = $this->options['twilio_phone'];
                    $to = $recipient;
                }
                
                $twilio_message = $this->twilio_client->messages->create(
                    $to,
                    [
                        'from' => $from,
                        'body' => $message
                    ]
                );
                
                // Vérifier le statut de l'envoi
                if ($twilio_message->sid) {
                    $this->log("Message envoyé avec succès via Twilio: SID " . $twilio_message->sid);
                    return true;
                }
            } catch (\Exception $e) {
                $this->log("Erreur Twilio: " . $e->getMessage());
                // Continuer avec les méthodes alternatives
            }
        }
        
        // Si Twilio a échoué ou n'est pas disponible, utiliser la méthode de secours
        $this->log("Utilisation de la méthode de secours: " . $this->options['fallback_gateway']);
        
        switch ($this->options['fallback_gateway']) {
            case 'api':
                $success = $this->send_via_api($recipient, $message, $type);
                break;
                
            case 'email2sms':
                $success = $this->send_via_email2sms($recipient, $message);
                break;
                
            case 'email':
            default:
                // Envoyer par email comme solution de repli
                $success = $this->send_via_email($recipient, $message, $type);
                break;
        }
        
        // Log du résultat
        $status = $success ? 'réussie' : 'échouée';
        $this->log("Notification $status pour $recipient via méthode de secours");
        
        return $success;
    }
    
    /**
     * Envoie une notification par API REST
     *
     * @param string $recipient Le numéro de téléphone du destinataire
     * @param string $message Le contenu du message
     * @param string $type Le type de notification
     * @return bool Succès ou échec
     */
    private function send_via_api($recipient, $message, $type = 'sms') {
        // Vérifier si la clé API est configurée
        if (empty($this->options['api_key']) || empty($this->options['api_endpoint'])) {
            $this->log("Échec de l'envoi par API: Clé API ou endpoint manquant");
            return false;
        }
        
        // Préparer les données
        $data = array(
            'to' => $recipient,
            'message' => $message,
            'type' => $type,
            'sender' => $this->options['sender_id']
        );
        
        // Envoyer la requête
        $response = wp_remote_post($this->options['api_endpoint'], array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->options['api_key'],
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'cookies' => array()
        ));
        
        // Vérifier la réponse
        if (is_wp_error($response)) {
            $this->log("Erreur API: " . $response->get_error_message());
            return false;
        }
        
        // Vérifier le code de statut
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code != 200) {
            $this->log("Erreur API: HTTP $status_code - " . wp_remote_retrieve_body($response));
            return false;
        }
        
        // Traiter la réponse
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['success']) || $body['success'] !== true) {
            $this->log("Erreur API: " . (isset($body['message']) ? $body['message'] : 'Réponse invalide'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Envoie une notification par email-to-SMS
     *
     * @param string $recipient Le numéro de téléphone du destinataire
     * @param string $message Le contenu du message
     * @return bool Succès ou échec
     */
    private function send_via_email2sms($recipient, $message) {
        // Vérifier si le domaine email2sms est configuré
        if (empty($this->options['email2sms_domain'])) {
            $this->log("Échec de l'envoi par Email2SMS: Domaine manquant");
            return false;
        }
        
        // Construire l'adresse email
        $email = $recipient . '@' . $this->options['email2sms_domain'];
        
        // Envoyer l'email
        $subject = $this->options['sender_id'];
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        $success = wp_mail($email, $subject, $message, $headers);
        
        // Log du résultat
        if (!$success) {
            $this->log("Échec de l'envoi Email2SMS à $email");
        }
        
        return $success;
    }
    
    /**
     * Envoie une notification par email (solution de repli)
     *
     * @param string $recipient Le numéro de téléphone du destinataire
     * @param string $message Le contenu du message
     * @param string $type Le type de notification
     * @return bool Succès ou échec
     */
    private function send_via_email($recipient, $message, $type = 'sms') {
        // Trouver l'utilisateur associé à ce numéro de téléphone
        $user_id = $this->find_user_by_phone($recipient);
        $email = '';
        
        if ($user_id) {
            $user = get_userdata($user_id);
            $email = $user->user_email;
        }
        
        // Si aucun email trouvé, utiliser l'email de l'administrateur
        if (empty($email)) {
            $email = get_option('admin_email');
        }
        
        // Préparer l'email
        $subject = sprintf(__('[%s] Notification %s', 'life-travel-excursion'), $this->options['sender_id'], strtoupper($type));
        
        $body = sprintf(
            __("Cette notification aurait été envoyée par %s au numéro %s.\n\nContenu du message:\n%s", 'life-travel-excursion'),
            $type,
            $recipient,
            $message
        );
        
        // Envoyer l'email
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        $success = wp_mail($email, $subject, $body, $headers);
        
        // Log du résultat
        if (!$success) {
            $this->log("Échec de l'envoi email à $email");
        }
        
        return $success;
    }
    
    /**
     * Notification de changement de statut de commande
     *
     * @param int $order_id ID de la commande
     * @param string $old_status Ancien statut
     * @param string $new_status Nouveau statut
     * @param WC_Order $order Objet de la commande
     */
    public function notify_order_status_change($order_id, $old_status, $new_status, $order) {
        // Ne rien faire si les statuts sont les mêmes
        if ($old_status === $new_status) {
            return;
        }
        
        // Récupérer les informations du client
        $customer_phone = $order->get_billing_phone();
        
        // Vérifier si un numéro de téléphone est disponible
        if (empty($customer_phone)) {
            $this->log("Pas de notification: Téléphone client manquant pour la commande #$order_id");
            return;
        }
        
        // Préparer le message selon le nouveau statut
        $message = $this->get_status_notification_message($order, $new_status);
        
        // Envoyer la notification
        if (!empty($message)) {
            $this->send_notification($customer_phone, $message, 'sms');
        }
    }
    
    /**
     * Notification de commande terminée
     *
     * @param int $order_id ID de la commande
     */
    public function notify_order_completed($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Vérifier si c'est une commande d'excursion
        $has_excursion = false;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_type() === 'excursion') {
                $has_excursion = true;
                break;
            }
        }
        
        if (!$has_excursion) {
            return;
        }
        
        // Récupérer les informations du client
        $customer_phone = $order->get_billing_phone();
        
        // Vérifier si un numéro de téléphone est disponible
        if (empty($customer_phone)) {
            $this->log("Pas de notification: Téléphone client manquant pour la commande #$order_id");
            return;
        }
        
        // Récupérer les détails des excursions
        $excursions = array();
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            if ($product && $product->get_type() === 'excursion') {
                $excursion_name = $product->get_name();
                
                // Récupérer les métadonnées de l'excursion
                $participants = $item->get_meta('participants');
                $start_date = $item->get_meta('start_date');
                $booking_id = $order->get_order_number() . '-' . $product->get_id();
                
                $formatted_date = date_i18n(get_option('date_format'), strtotime($start_date));
                
                // Informations sur les véhicules
                $vehicles_needed = $item->get_meta('vehicles_needed');
                $vehicle_info = '';
                
                if ($vehicles_needed > 1) {
                    $vehicle_info = sprintf(
                        __("\n%d véhicules réservés", 'life-travel-excursion'),
                        $vehicles_needed
                    );
                }
                
                $excursions[] = array(
                    'name' => $excursion_name,
                    'booking_id' => $booking_id,
                    'formatted_date' => $formatted_date,
                    'participants' => $participants,
                    'vehicle_info' => $vehicle_info
                );
            }
        }
        
        // Construire le message
        $message = __("Votre réservation chez Life Travel est confirmée!\n\n", 'life-travel-excursion');
        
        foreach ($excursions as $excursion) {
            $message .= sprintf(
                __("Excursion: %s\nRéférence: %s\nDate: %s\nParticipants: %d%s\n\n", 'life-travel-excursion'),
                $excursion['name'],
                $excursion['booking_id'],
                $excursion['formatted_date'],
                $excursion['participants'],
                $excursion['vehicle_info']
            );
        }
        
        // Ajouter un message de conclusion
        $message .= __("Nous vous contacterons 24h avant l'excursion pour confirmer les détails. Pour toute question, n'hésitez pas à nous contacter.", 'life-travel-excursion');
        
        // Envoyer la notification au client
        $this->send_notification($customer_phone, $message, 'sms');
        
        // Notifier également les administrateurs
        $admin_users = get_users(array('role' => 'administrator'));
        
        if (!empty($admin_users)) {
            $excursion_name = !empty($excursions) ? $excursions[0]['name'] : __('excursion', 'life-travel-excursion');
            
            $admin_message = sprintf(
                __("Nouvelle réservation : Commande #%s confirmée pour l'excursion %s.", 'life-travel-excursion'),
                $order->get_order_number(),
                $excursion_name
            );
            
            foreach ($admin_users as $admin) {
                $admin_phone = get_user_meta($admin->ID, 'billing_phone', true);
                
                if (!empty($admin_phone)) {
                    $this->send_notification($admin_phone, $admin_message, 'sms');
                } else {
                    // Envoyer un email comme solution de repli
                    wp_mail(
                        $admin->user_email,
                        __('Nouvelle réservation confirmée', 'life-travel-excursion'),
                        $admin_message
                    );
                }
            }
        }
    }
    
    /**
     * Génère un message de notification selon le statut de la commande
     *
     * @param WC_Order $order Objet de la commande
     * @param string $status Nouveau statut
     * @return string Message de notification
     */
    private function get_status_notification_message($order, $status) {
        $message = '';
        $order_number = $order->get_order_number();
        
        switch ($status) {
            case 'processing':
                $message = sprintf(
                    __("Votre commande #%s chez Life Travel est en cours de traitement. Nous vous tiendrons informé de son évolution.", 'life-travel-excursion'),
                    $order_number
                );
                break;
                
            case 'on-hold':
                $message = sprintf(
                    __("Votre commande #%s chez Life Travel a été mise en attente. Nous vous contacterons pour plus d'informations.", 'life-travel-excursion'),
                    $order_number
                );
                break;
                
            case 'completed':
                $message = sprintf(
                    __("Votre commande #%s chez Life Travel a été confirmée! Vous recevrez un email avec tous les détails.", 'life-travel-excursion'),
                    $order_number
                );
                break;
                
            case 'cancelled':
                $message = sprintf(
                    __("Votre commande #%s chez Life Travel a été annulée. Pour toute question, n'hésitez pas à nous contacter.", 'life-travel-excursion'),
                    $order_number
                );
                break;
                
            case 'refunded':
                $message = sprintf(
                    __("Votre commande #%s chez Life Travel a été remboursée. Le remboursement peut prendre quelques jours pour apparaître sur votre compte.", 'life-travel-excursion'),
                    $order_number
                );
                break;
                
            case 'failed':
                $message = sprintf(
                    __("Le paiement de votre commande #%s chez Life Travel a échoué. Veuillez vérifier vos informations de paiement et réessayer.", 'life-travel-excursion'),
                    $order_number
                );
                break;
                
            default:
                $message = sprintf(
                    __("Le statut de votre commande #%s chez Life Travel a été mis à jour. Consultez votre compte pour plus de détails.", 'life-travel-excursion'),
                    $order_number
                );
                break;
        }
        
        return $message;
    }
    
    /**
     * Nettoie un numéro de téléphone pour le rendre compatible avec les API
     *
     * @param string $phone Numéro de téléphone
     * @return string Numéro nettoyé
     */
    private function sanitize_phone_number($phone) {
        // Supprimer tous les caractères non numériques
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // S'assurer que le numéro commence par + pour l'international
        if (substr($phone, 0, 1) !== '+') {
            // Si le numéro commence par un 0, le remplacer par l'indicatif du pays
            if (substr($phone, 0, 1) === '0') {
                $phone = '+237' . substr($phone, 1); // +237 pour le Cameroun
            } else {
                // Ajouter +237 sinon
                $phone = '+237' . $phone;
            }
        }
        
        return $phone;
    }
    
    /**
     * Tronque un message à la longueur maximale selon le type
     *
     * @param string $message Message à tronquer
     * @param string $type Type de notification
     * @return string Message tronqué
     */
    private function truncate_message($message, $type = 'sms') {
        $max_length = 160; // Longueur standard SMS
        
        if ($type === 'whatsapp') {
            $max_length = 1000; // Plus long pour WhatsApp
        }
        
        if (strlen($message) <= $max_length) {
            return $message;
        }
        
        return substr($message, 0, $max_length - 3) . '...';
    }
    
    /**
     * Trouve un utilisateur par son numéro de téléphone
     *
     * @param string $phone Numéro de téléphone
     * @return int|bool ID utilisateur ou false
     */
    private function find_user_by_phone($phone) {
        global $wpdb;
        
        // Nettoyer le numéro de téléphone
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Rechercher dans les métadonnées utilisateur
        $user_id = $wpdb->get_var($wpdb->prepare("
            SELECT user_id 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'billing_phone' 
            AND meta_value LIKE %s", 
            '%' . $phone . '%'
        ));
        
        return $user_id ? (int) $user_id : false;
    }
    
    /**
     * Enregistre un message dans le journal
     *
     * @param string $message Message à enregistrer
     */
    private function log($message) {
        if ($this->options['debug_mode'] === 'yes') {
            $this->logger->add('life-travel-notifications', $message);
        }
    }
    
    /**
     * Page d'administration
     */
    public function admin_page() {
        // Traiter la sauvegarde du formulaire
        if (isset($_POST['life_travel_notifications_save']) && check_admin_referer('life_travel_notifications_settings')) {
            $this->options = array(
                'sms_enabled' => isset($_POST['sms_enabled']) ? 'yes' : 'no',
                'email_enabled' => isset($_POST['email_enabled']) ? 'yes' : 'no',
                'twilio_sid' => sanitize_text_field($_POST['twilio_sid']),
                'twilio_token' => sanitize_text_field($_POST['twilio_token']),
                'twilio_phone' => sanitize_text_field($_POST['twilio_phone']),
                'fallback_gateway' => sanitize_text_field($_POST['fallback_gateway']),
                'api_key' => sanitize_text_field($_POST['api_key']),
                'api_endpoint' => esc_url_raw($_POST['api_endpoint']),
                'email2sms_domain' => sanitize_text_field($_POST['email2sms_domain']),
                'sender_id' => sanitize_text_field($_POST['sender_id']),
                'debug_mode' => isset($_POST['debug_mode']) ? 'yes' : 'no'
            );
            
            update_option('life_travel_notifications_options', $this->options);
            
            // Réinitialiser le client Twilio
            global $twilio_available;
            if ($twilio_available && !empty($this->options['twilio_sid']) && !empty($this->options['twilio_token'])) {
                try {
                    $this->twilio_client = new \Twilio\Rest\Client(
                        $this->options['twilio_sid'],
                        $this->options['twilio_token']
                    );
                    $this->log('Twilio client reinitialized after settings update');
                } catch (\Exception $e) {
                    $this->log('Twilio reinitialization error: ' . $e->getMessage());
                    $this->twilio_client = null;
                }
            }
            
            echo '<div class="notice notice-success"><p>' . __('Paramètres sauvegardés avec succès.', 'life-travel-excursion') . '</p></div>';
        }
        
        // Afficher le formulaire
        ?>
        <div class="wrap">
            <h1><?php _e('Paramètres des notifications', 'life-travel-excursion'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('life_travel_notifications_settings'); ?>
                
                <h2><?php _e('Paramètres généraux', 'life-travel-excursion'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Activer les notifications SMS', 'life-travel-excursion'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sms_enabled" value="1" <?php checked($this->options['sms_enabled'], 'yes'); ?>>
                                <?php _e('Activer', 'life-travel-excursion'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Activer les notifications par email', 'life-travel-excursion'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="email_enabled" value="1" <?php checked($this->options['email_enabled'], 'yes'); ?>>
                                <?php _e('Activer', 'life-travel-excursion'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('ID Expéditeur', 'life-travel-excursion'); ?></th>
                        <td>
                            <input type="text" name="sender_id" value="<?php echo esc_attr($this->options['sender_id']); ?>" class="regular-text">
                            <p class="description"><?php _e('Nom de l\'expéditeur qui apparaîtra sur les messages (11 caractères max)', 'life-travel-excursion'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Mode débogage', 'life-travel-excursion'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug_mode" value="1" <?php checked($this->options['debug_mode'], 'yes'); ?>>
                                <?php _e('Activer', 'life-travel-excursion'); ?>
                            </label>
                            <p class="description"><?php _e('Enregistre les événements dans le journal WooCommerce', 'life-travel-excursion'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Configuration Twilio (Méthode principale)', 'life-travel-excursion'); ?></h2>
                <p class="description">
                    <?php 
                        global $twilio_available;
                        if ($twilio_available) {
                            echo '<span style="color: green;">✓ ' . __('Bibliothèque Twilio détectée', 'life-travel-excursion') . '</span>';
                        } else {
                            echo '<span style="color: red;">⚠ ' . __('Bibliothèque Twilio non détectée. Veuillez installer la bibliothèque Twilio via Composer.', 'life-travel-excursion') . '</span>';
                        }
                    ?>
                </p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Twilio Account SID', 'life-travel-excursion'); ?></th>
                        <td>
                            <input type="text" name="twilio_sid" value="<?php echo esc_attr($this->options['twilio_sid']); ?>" class="regular-text">
                            <p class="description"><?php _e('Identifiant de compte Twilio (Account SID)', 'life-travel-excursion'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Twilio Auth Token', 'life-travel-excursion'); ?></th>
                        <td>
                            <input type="password" name="twilio_token" value="<?php echo esc_attr($this->options['twilio_token']); ?>" class="regular-text">
                            <p class="description"><?php _e('Jeton d\'authentification Twilio', 'life-travel-excursion'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Numéro de téléphone Twilio', 'life-travel-excursion'); ?></th>
                        <td>
                            <input type="text" name="twilio_phone" value="<?php echo esc_attr($this->options['twilio_phone']); ?>" class="regular-text">
                            <p class="description"><?php _e('Numéro de téléphone fourni par Twilio, avec l\'indicatif pays (ex: +12025551234)', 'life-travel-excursion'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Méthode de secours (en cas d\'erreur Twilio)', 'life-travel-excursion'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Méthode de secours', 'life-travel-excursion'); ?></th>
                        <td>
                            <select name="fallback_gateway" id="fallback_gateway">
                                <option value="email" <?php selected($this->options['fallback_gateway'], 'email'); ?>><?php _e('Email (solution par défaut)', 'life-travel-excursion'); ?></option>
                                <option value="api" <?php selected($this->options['fallback_gateway'], 'api'); ?>><?php _e('API REST alternative', 'life-travel-excursion'); ?></option>
                                <option value="email2sms" <?php selected($this->options['fallback_gateway'], 'email2sms'); ?>><?php _e('Email-to-SMS', 'life-travel-excursion'); ?></option>
                            </select>
                            <p class="description"><?php _e('Méthode utilisée si Twilio n\'est pas disponible ou rencontre une erreur', 'life-travel-excursion'); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="fallback api">
                        <th scope="row"><?php _e('Clé API', 'life-travel-excursion'); ?></th>
                        <td>
                            <input type="password" name="api_key" value="<?php echo esc_attr($this->options['api_key']); ?>" class="regular-text">
                            <p class="description"><?php _e('Clé API de votre fournisseur SMS alternatif', 'life-travel-excursion'); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="fallback api">
                        <th scope="row"><?php _e('URL Endpoint API', 'life-travel-excursion'); ?></th>
                        <td>
                            <input type="url" name="api_endpoint" value="<?php echo esc_url($this->options['api_endpoint']); ?>" class="regular-text">
                            <p class="description"><?php _e('URL de l\'API de votre fournisseur SMS alternatif', 'life-travel-excursion'); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="fallback email2sms">
                        <th scope="row"><?php _e('Domaine Email2SMS', 'life-travel-excursion'); ?></th>
                        <td>
                            <input type="text" name="email2sms_domain" value="<?php echo esc_attr($this->options['email2sms_domain']); ?>" class="regular-text">
                            <p class="description"><?php _e('Domaine pour le service Email-to-SMS (ex: sms.example.com)', 'life-travel-excursion'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="life_travel_notifications_save" class="button-primary" value="<?php _e('Enregistrer les modifications', 'life-travel-excursion'); ?>">
                </p>
            </form>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2><?php _e('Guide d\'installation de Twilio', 'life-travel-excursion'); ?></h2>
                <ol>
                    <li><?php _e('Créez un compte sur <a href="https://www.twilio.com" target="_blank">Twilio.com</a>', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Obtenez votre Account SID et Auth Token depuis le tableau de bord Twilio', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Achetez un numéro de téléphone Twilio ou utilisez un numéro existant', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Installez la bibliothèque PHP Twilio en exécutant <code>composer require twilio/sdk</code> dans le répertoire de votre plugin', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Configurez les paramètres ci-dessus avec vos identifiants', 'life-travel-excursion'); ?></li>
                </ol>
            </div>
            
            <script>
                jQuery(document).ready(function($) {
                    function toggleFallbackFields() {
                        var gateway = $('#fallback_gateway').val();
                        
                        $('.fallback').hide();
                        $('.fallback.' + gateway).show();
                    }
                    
                    $('#fallback_gateway').on('change', toggleFallbackFields);
                    toggleFallbackFields();
                });
            </script>
        </div>
        <?php
    }
}

// Fonction pour envoyer une notification
function life_travel_send_notification($recipient, $message, $type = 'sms') {
    $notifications = Life_Travel_Notifications_Manager::get_instance();
    return $notifications->send_notification($recipient, $message, $type);
}

// Ajouter la page d'administration
function life_travel_notifications_admin_menu() {
    add_submenu_page(
        'woocommerce',
        __('Notifications', 'life-travel-excursion'),
        __('Notifications', 'life-travel-excursion'),
        'manage_options',
        'life-travel-notifications',
        array(Life_Travel_Notifications_Manager::get_instance(), 'admin_page')
    );
}
add_action('admin_menu', 'life_travel_notifications_admin_menu', 99);
