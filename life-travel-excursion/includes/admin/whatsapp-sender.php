<?php
/**
 * Envoi de messages WhatsApp via Twilio
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

/**
 * Classe d'envoi de messages WhatsApp via Twilio
 */
class Life_Travel_WhatsApp_Sender {
    /**
     * Instance de la classe
     */
    private static $instance = null;
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Rien à initialiser pour l'instant
    }
    
    /**
     * Récupère l'instance unique
     *
     * @return Life_Travel_WhatsApp_Sender
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Envoie un message WhatsApp à un numéro
     *
     * @param string $to_phone Numéro de téléphone du destinataire
     * @param string $message Message à envoyer
     * @return bool Succès ou échec
     */
    public function send_whatsapp_message($to_phone, $message) {
        // Vérifier si Twilio est configuré
        $twilio_sid = get_option('lte_twilio_sid', '');
        $twilio_token = get_option('lte_twilio_token', '');
        $twilio_whatsapp = get_option('lte_twilio_whatsapp', '');
        
        if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_whatsapp)) {
            $this->log_error('Configuration Twilio incomplète pour WhatsApp');
            return false;
        }
        
        // Formater le numéro de téléphone
        $to_phone = $this->format_phone_number($to_phone);
        
        // Charger la bibliothèque Twilio
        if (!$this->load_twilio_sdk()) {
            $this->log_error('Impossible de charger le SDK Twilio pour WhatsApp');
            return false;
        }
        
        try {
            // Initialiser le client Twilio
            $client = new \Twilio\Rest\Client($twilio_sid, $twilio_token);
            
            // Envoyer le message via WhatsApp
            $result = $client->messages->create(
                'whatsapp:' . $to_phone,
                [
                    'from' => 'whatsapp:' . $twilio_whatsapp,
                    'body' => $message
                ]
            );
            
            // Log le résultat en mode debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('Envoi WhatsApp via Twilio à %s: %s', $to_phone, $result->sid));
            }
            
            return true;
        } catch (\Exception $e) {
            // Log l'erreur
            $this->log_error('Erreur lors de l\'envoi WhatsApp: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envoie une notification WhatsApp avec un fichier Excel joint
     *
     * @param string $to_phone Numéro de téléphone du destinataire
     * @param string $message Message à envoyer
     * @param string $excel_url URL du fichier Excel
     * @return bool Succès ou échec
     */
    public function send_whatsapp_with_excel($to_phone, $message, $excel_url) {
        // Vérifier si Twilio est configuré
        $twilio_sid = get_option('lte_twilio_sid', '');
        $twilio_token = get_option('lte_twilio_token', '');
        $twilio_whatsapp = get_option('lte_twilio_whatsapp', '');
        
        if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_whatsapp)) {
            $this->log_error('Configuration Twilio incomplète pour WhatsApp');
            return false;
        }
        
        // Formater le numéro de téléphone
        $to_phone = $this->format_phone_number($to_phone);
        
        // Charger la bibliothèque Twilio
        if (!$this->load_twilio_sdk()) {
            $this->log_error('Impossible de charger le SDK Twilio pour WhatsApp');
            return false;
        }
        
        try {
            // Initialiser le client Twilio
            $client = new \Twilio\Rest\Client($twilio_sid, $twilio_token);
            
            // Ajouter l'URL du fichier au message
            $full_message = $message . "\n\nTélécharger le fichier Excel: " . $excel_url;
            
            // Envoyer le message via WhatsApp
            $result = $client->messages->create(
                'whatsapp:' . $to_phone,
                [
                    'from' => 'whatsapp:' . $twilio_whatsapp,
                    'body' => $full_message
                ]
            );
            
            // Log le résultat en mode debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('Envoi WhatsApp avec Excel via Twilio à %s: %s', $to_phone, $result->sid));
            }
            
            return true;
        } catch (\Exception $e) {
            // Log l'erreur
            $this->log_error('Erreur lors de l\'envoi WhatsApp avec Excel: ' . $e->getMessage());
            return false;
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
            error_log('[Life Travel WhatsApp] ' . $message);
        }
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_WhatsApp_Sender::get_instance();
});
