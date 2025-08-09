<?php
/**
 * Gestion des notifications administratives
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

/**
 * Classe de gestion des notifications administratives
 */
class Life_Travel_Admin_Notifications {
    /**
     * Instance de la classe
     */
    private static $instance = null;
    
    /**
     * Liste des administrateurs à notifier
     */
    private $admin_recipients = [];
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Initialiser les destinataires
        $this->init_recipients();
        
        // Hooks pour les événements de réservation
        add_action('woocommerce_new_order', [$this, 'notify_new_reservation'], 10, 1);
        add_action('woocommerce_order_status_changed', [$this, 'notify_status_change'], 10, 4);
        
        // Hooks pour la page admin
        add_action('admin_init', [$this, 'register_notification_settings']);
        add_action('admin_menu', [$this, 'add_notification_settings_page']);
        
        // Hook pour les exports Excel à la demande
        add_action('wp_ajax_lte_export_reservations', [$this, 'handle_export_request']);
    }
    
    /**
     * Récupère l'instance unique
     *
     * @return Life_Travel_Admin_Notifications
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialise la liste des destinataires des notifications
     */
    private function init_recipients() {
        // Récupérer la configuration des destinataires
        $config_recipients = get_option('lte_notification_recipients', []);
        
        if (empty($config_recipients)) {
            // Par défaut, tous les administrateurs
            $admins = get_users(['role' => 'administrator']);
            foreach ($admins as $admin) {
                $this->admin_recipients[] = [
                    'user_id' => $admin->ID,
                    'email' => $admin->user_email,
                    'notify_email' => true,
                    'notify_whatsapp' => false,
                    'phone' => get_user_meta($admin->ID, '_lte_phone', true)
                ];
            }
        } else {
            $this->admin_recipients = $config_recipients;
        }
    }

    /**
     * Notifie les administrateurs d'une nouvelle réservation
     *
     * @param int $order_id ID de la commande
     */
    public function notify_new_reservation($order_id) {
        $order = wc_get_order($order_id);
        
        // Vérifier si c'est une commande d'excursion
        if (!$this->is_excursion_order($order)) {
            return;
        }
        
        // Générer le fichier CSV
        $csv_generator = Life_Travel_CSV_Generator::get_instance();
        $csv_file = $csv_generator->generate_order_csv($order_id);
        $all_reservations_file = $csv_generator->generate_all_reservations_csv();
        
        $subject = sprintf(
            __('Nouvelle réservation #%s - %s', 'life-travel-excursion'),
            $order->get_order_number(),
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
        );
        
        $message = $this->prepare_notification_message('new_reservation', [
            'order_id' => $order_id,
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'order_total' => $order->get_formatted_order_total(),
            'single_excel_url' => $csv_generator->get_download_url($csv_file),
            'all_excel_url' => $csv_generator->get_download_url($all_reservations_file),
        ]);
        
        // Envoyer les notifications
        $this->send_notifications($subject, $message, $order_id);
    }

    /**
     * Notifie les administrateurs d'un changement de statut
     *
     * @param int $order_id ID de la commande
     * @param string $status_from Ancien statut
     * @param string $status_to Nouveau statut
     * @param WC_Order $order Objet commande
     */
    public function notify_status_change($order_id, $status_from, $status_to, $order) {
        // Vérifier si c'est une commande d'excursion
        if (!$this->is_excursion_order($order)) {
            return;
        }
        
        // Générer le fichier CSV
        $csv_generator = Life_Travel_CSV_Generator::get_instance();
        $csv_file = $csv_generator->generate_order_csv($order_id);
        $all_reservations_file = $csv_generator->generate_all_reservations_csv();
        
        $subject = sprintf(
            __('Changement de statut pour la réservation #%s - %s', 'life-travel-excursion'),
            $order->get_order_number(),
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
        );
        
        $message = $this->prepare_notification_message('status_change', [
            'order_id' => $order_id,
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'status_from' => $status_from,
            'status_to' => $status_to,
            'single_excel_url' => $csv_generator->get_download_url($csv_file),
            'all_excel_url' => $csv_generator->get_download_url($all_reservations_file),
        ]);
        
        // Envoyer les notifications
        $this->send_notifications($subject, $message, $order_id);
    }

    /**
     * Vérifie si une commande contient des excursions
     *
     * @param WC_Order $order Commande à vérifier
     * @return bool
     */
    private function is_excursion_order($order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && has_term('excursion', 'product_cat', $product->get_id())) {
                return true;
            }
        }
        return false;
    }

    /**
     * Prépare le message de notification selon le type d'événement
     *
     * @param string $notification_type Type de notification
     * @param array $data Données pour le message
     * @return string Message formaté
     */
    private function prepare_notification_message($notification_type, $data) {
        ob_start();
        
        switch ($notification_type) {
            case 'new_reservation':
                ?>
                <p>Une nouvelle réservation a été effectuée sur le site Life Travel.</p>
                <p><strong>Réservation #<?php echo esc_html($data['order_id']); ?></strong><br>
                Client: <?php echo esc_html($data['customer_name']); ?><br>
                Montant: <?php echo esc_html($data['order_total']); ?></p>
                
                <p>Vous pouvez consulter les détails de cette réservation :</p>
                <ul>
                    <li><a href="<?php echo esc_url($data['single_excel_url']); ?>">Télécharger les détails de cette réservation (CSV)</a></li>
                    <li><a href="<?php echo esc_url($data['all_excel_url']); ?>">Télécharger le récapitulatif de toutes les réservations en cours (CSV)</a></li>
                </ul>
                <?php
                break;
                
            case 'status_change':
                ?>
                <p>Le statut d'une réservation a été modifié sur le site Life Travel.</p>
                <p><strong>Réservation #<?php echo esc_html($data['order_id']); ?></strong><br>
                Client: <?php echo esc_html($data['customer_name']); ?><br>
                Changement de statut: <?php echo esc_html($data['status_from']); ?> → <?php echo esc_html($data['status_to']); ?></p>
                
                <p>Vous pouvez consulter les détails de cette réservation :</p>
                <ul>
                    <li><a href="<?php echo esc_url($data['single_excel_url']); ?>">Télécharger les détails de cette réservation (CSV)</a></li>
                    <li><a href="<?php echo esc_url($data['all_excel_url']); ?>">Télécharger le récapitulatif de toutes les réservations en cours (CSV)</a></li>
                </ul>
                <?php
                break;
        }
        
        return ob_get_clean();
    }

    /**
     * Envoie les notifications aux administrateurs configurés
     *
     * @param string $subject Sujet du message
     * @param string $message Contenu du message
     * @param int $order_id ID de la commande concernée
     */
    private function send_notifications($subject, $message, $order_id) {
        foreach ($this->admin_recipients as $recipient) {
            // Notification par email
            if ($recipient['notify_email'] && !empty($recipient['email'])) {
                $this->send_email_notification($recipient['email'], $subject, $message);
            }
            
            // Notification par WhatsApp
            if ($recipient['notify_whatsapp'] && !empty($recipient['phone'])) {
                $whatsapp_message = $this->prepare_whatsapp_message($subject, $message);
                $this->send_whatsapp_notification($recipient['phone'], $whatsapp_message);
            }
        }
    }
    
    /**
     * Envoie une notification par email
     *
     * @param string $to_email Adresse email du destinataire
     * @param string $subject Sujet du message
     * @param string $message Contenu du message
     * @return bool Succès ou échec
     */
    private function send_email_notification($to_email, $subject, $message) {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        return wp_mail($to_email, $subject, $message, $headers);
    }
    
    /**
     * Prépare un message WhatsApp à partir du contenu HTML
     *
     * @param string $subject Sujet du message
     * @param string $html_message Message HTML
     * @return string Message texte pour WhatsApp
     */
    private function prepare_whatsapp_message($subject, $html_message) {
        // Convertir le HTML en texte brut
        $text = strip_tags($html_message);
        $text = str_replace('&nbsp;', ' ', $text);
        $text = html_entity_decode($text);
        
        // Ajouter le sujet comme titre
        $whatsapp_message = $subject . "\n\n" . $text;
        
        // Extraire les URLs des liens Excel
        if (preg_match_all('/<a\s+href="([^"]+)">([^<]+)<\/a>/i', $html_message, $matches)) {
            $whatsapp_message .= "\n\nLiens de téléchargement:";
            
            for ($i = 0; $i < count($matches[1]); $i++) {
                $url = $matches[1][$i];
                $text = $matches[2][$i];
                $whatsapp_message .= "\n" . $text . ": " . $url;
            }
        }
        
        return $whatsapp_message;
    }
    
    /**
     * Enregistre les paramètres de notification dans l'admin
     */
    public function register_notification_settings() {
        register_setting('lte_notification_options', 'lte_notification_recipients', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_recipients']
        ]);
        
        register_setting('lte_notification_options', 'lte_enable_order_notifications', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        register_setting('lte_notification_options', 'lte_enable_status_notifications', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
    }
    
    /**
     * Sanitize les récipients pour les options
     *
     * @param array $recipients Liste des destinataires
     * @return array Liste sanitisée
     */
    public function sanitize_recipients($recipients) {
        if (!is_array($recipients)) {
            return [];
        }
        
        $sanitized = [];
        
        foreach ($recipients as $recipient) {
            if (isset($recipient['user_id']) && isset($recipient['email'])) {
                $sanitized[] = [
                    'user_id' => absint($recipient['user_id']),
                    'email' => sanitize_email($recipient['email']),
                    'notify_email' => isset($recipient['notify_email']) ? (bool) $recipient['notify_email'] : false,
                    'notify_whatsapp' => isset($recipient['notify_whatsapp']) ? (bool) $recipient['notify_whatsapp'] : false,
                    'phone' => isset($recipient['phone']) ? sanitize_text_field($recipient['phone']) : ''
                ];
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Ajoute la page de paramètres de notification
     */
    public function add_notification_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Notifications Life Travel', 'life-travel-excursion'),
            __('Notifications', 'life-travel-excursion'),
            'manage_options',
            'lte-notification-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Affiche la page de paramètres
     */
    public function render_settings_page() {
        // Cette méthode sera implémentée dans le fichier de template admin
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'templates/admin/notification-settings.php';
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_Admin_Notifications::get_instance();
});
