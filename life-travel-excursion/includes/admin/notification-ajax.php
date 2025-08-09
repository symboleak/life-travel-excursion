<?php
/**
 * Gestionnaire AJAX pour les notifications et tests
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

/**
 * Classe de gestion AJAX pour les notifications
 */
class Life_Travel_Notification_Ajax {
    /**
     * Instance de la classe
     */
    private static $instance = null;
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Hooks pour les tests AJAX
        add_action('wp_ajax_lte_test_notification_email', [$this, 'test_email_notification']);
        add_action('wp_ajax_lte_test_notification_whatsapp', [$this, 'test_whatsapp_notification']);
        add_action('wp_ajax_lte_test_excel_export', [$this, 'test_excel_export']);
        
        // Hook pour l'export à la demande
        add_action('wp_ajax_lte_export_reservations', [$this, 'handle_export_request']);
    }
    
    /**
     * Récupère l'instance unique
     *
     * @return Life_Travel_Notification_Ajax
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Teste l'envoi d'une notification par email
     */
    public function test_email_notification() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lte_test_notification')) {
            wp_send_json_error(['message' => __('Erreur de sécurité. Veuillez rafraîchir la page et réessayer.', 'life-travel-excursion')]);
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Vous n\'avez pas les permissions nécessaires.', 'life-travel-excursion')]);
            return;
        }
        
        // Récupérer les destinataires configurés
        $recipients = get_option('lte_notification_recipients', []);
        
        if (empty($recipients)) {
            wp_send_json_error(['message' => __('Aucun destinataire configuré. Veuillez ajouter des destinataires dans les paramètres.', 'life-travel-excursion')]);
            return;
        }
        
        // Préparer le message
        $subject = __('Test de notification - Life Travel', 'life-travel-excursion');
        $message = sprintf(
            __('Ceci est un message de test envoyé depuis la page de configuration des notifications Life Travel à %s.', 'life-travel-excursion'),
            current_time('Y-m-d H:i:s')
        );
        
        // Compter les envois réussis
        $success_count = 0;
        $email_recipients = [];
        
        foreach ($recipients as $recipient) {
            if (isset($recipient['notify_email']) && $recipient['notify_email'] && !empty($recipient['email'])) {
                $email_recipients[] = $recipient['email'];
                
                $headers = ['Content-Type: text/html; charset=UTF-8'];
                $html_message = '<div style="font-family: Arial, sans-serif; padding: 20px; max-width: 600px;">';
                $html_message .= '<h2 style="color: #4CAF50;">' . $subject . '</h2>';
                $html_message .= '<p>' . $message . '</p>';
                $html_message .= '<p>' . __('Cet email confirme que la configuration des notifications par email fonctionne correctement.', 'life-travel-excursion') . '</p>';
                $html_message .= '</div>';
                
                $result = wp_mail($recipient['email'], $subject, $html_message, $headers);
                
                if ($result) {
                    $success_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            wp_send_json_success([
                'message' => sprintf(
                    __('Message de test envoyé avec succès à %d destinataire(s) : %s', 'life-travel-excursion'),
                    $success_count,
                    implode(', ', $email_recipients)
                )
            ]);
        } else {
            wp_send_json_error(['message' => __('Aucun email n\'a pu être envoyé. Vérifiez la configuration des destinataires et du serveur SMTP.', 'life-travel-excursion')]);
        }
    }
    
    /**
     * Teste l'envoi d'une notification par WhatsApp
     */
    public function test_whatsapp_notification() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lte_test_notification')) {
            wp_send_json_error(['message' => __('Erreur de sécurité. Veuillez rafraîchir la page et réessayer.', 'life-travel-excursion')]);
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Vous n\'avez pas les permissions nécessaires.', 'life-travel-excursion')]);
            return;
        }
        
        // Récupérer les destinataires configurés
        $recipients = get_option('lte_notification_recipients', []);
        
        if (empty($recipients)) {
            wp_send_json_error(['message' => __('Aucun destinataire configuré. Veuillez ajouter des destinataires dans les paramètres.', 'life-travel-excursion')]);
            return;
        }
        
        // Vérifier la configuration Twilio
        $twilio_sid = get_option('lte_twilio_sid', '');
        $twilio_token = get_option('lte_twilio_token', '');
        $twilio_whatsapp = get_option('lte_twilio_whatsapp', '');
        
        if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_whatsapp)) {
            wp_send_json_error(['message' => __('Configuration Twilio incomplète. Veuillez configurer les paramètres Twilio.', 'life-travel-excursion')]);
            return;
        }
        
        // Initialiser l'envoyeur WhatsApp
        $whatsapp_sender = Life_Travel_WhatsApp_Sender::get_instance();
        
        // Préparer le message
        $message = sprintf(
            __('Test de notification WhatsApp - Life Travel\n\nCeci est un message de test envoyé depuis la page de configuration à %s.\n\nCe message confirme que la configuration WhatsApp fonctionne correctement.', 'life-travel-excursion'),
            current_time('Y-m-d H:i:s')
        );
        
        // Compter les envois réussis
        $success_count = 0;
        $whatsapp_recipients = [];
        
        foreach ($recipients as $recipient) {
            if (isset($recipient['notify_whatsapp']) && $recipient['notify_whatsapp'] && !empty($recipient['phone'])) {
                $result = $whatsapp_sender->send_whatsapp_message($recipient['phone'], $message);
                
                if ($result) {
                    $success_count++;
                    $whatsapp_recipients[] = $recipient['phone'];
                }
            }
        }
        
        if ($success_count > 0) {
            wp_send_json_success([
                'message' => sprintf(
                    __('Message WhatsApp de test envoyé avec succès à %d destinataire(s) : %s', 'life-travel-excursion'),
                    $success_count,
                    implode(', ', $whatsapp_recipients)
                )
            ]);
        } else {
            wp_send_json_error(['message' => __('Aucun message WhatsApp n\'a pu être envoyé. Vérifiez la configuration Twilio et les numéros de téléphone des destinataires.', 'life-travel-excursion')]);
        }
    }
    
    /**
     * Teste la génération d'un fichier Excel
     */
    public function test_excel_export() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lte_test_notification')) {
            wp_send_json_error(['message' => __('Erreur de sécurité. Veuillez rafraîchir la page et réessayer.', 'life-travel-excursion')]);
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Vous n\'avez pas les permissions nécessaires.', 'life-travel-excursion')]);
            return;
        }
        
        // Récupérer le générateur Excel
        $excel_generator = Life_Travel_Excel_Generator::get_instance();
        
        // Vérifier si la bibliothèque est disponible
        if (!$excel_generator->check_excel_library()) {
            wp_send_json_error(['message' => __('La bibliothèque PhpSpreadsheet n\'est pas disponible. Veuillez l\'installer via Composer.', 'life-travel-excursion')]);
            return;
        }
        
        // Générer un fichier de test
        $file = $excel_generator->generate_all_reservations_excel();
        
        if ($file && file_exists($file)) {
            $download_url = $excel_generator->get_download_url($file);
            
            wp_send_json_success([
                'message' => __('Fichier Excel généré avec succès.', 'life-travel-excursion') . ' <a href="' . esc_url($download_url) . '" target="_blank">' . __('Télécharger le fichier', 'life-travel-excursion') . '</a>'
            ]);
        } else {
            wp_send_json_error(['message' => __('Erreur lors de la génération du fichier Excel. Veuillez vérifier les permissions du dossier d\'export.', 'life-travel-excursion')]);
        }
    }
    
    /**
     * Gère les demandes d'export de réservations
     */
    public function handle_export_request() {
        // Vérifier le nonce
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'lte_export_reservations')) {
            wp_send_json_error(['message' => __('Erreur de sécurité. Veuillez rafraîchir la page et réessayer.', 'life-travel-excursion')]);
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Vous n\'avez pas les permissions nécessaires.', 'life-travel-excursion')]);
            return;
        }
        
        // Récupérer le type d'export
        $export_type = isset($_REQUEST['export_type']) ? sanitize_text_field($_REQUEST['export_type']) : '';
        
        // Récupérer le générateur Excel
        $excel_generator = Life_Travel_Excel_Generator::get_instance();
        
        // Générer le fichier selon le type demandé
        $file = null;
        
        if ($export_type === 'single' && isset($_REQUEST['order_id'])) {
            $order_id = absint($_REQUEST['order_id']);
            $file = $excel_generator->generate_order_excel($order_id);
        } else {
            $file = $excel_generator->generate_all_reservations_excel();
        }
        
        if ($file && file_exists($file)) {
            $download_url = $excel_generator->get_download_url($file);
            
            wp_send_json_success([
                'message' => __('Fichier Excel généré avec succès.', 'life-travel-excursion'),
                'download_url' => $download_url
            ]);
        } else {
            wp_send_json_error(['message' => __('Erreur lors de la génération du fichier Excel.', 'life-travel-excursion')]);
        }
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_Notification_Ajax::get_instance();
});
