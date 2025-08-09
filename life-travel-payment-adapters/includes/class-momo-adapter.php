<?php
/**
 * Adaptateur pour la passerelle MTN MoMo de IwomiPay
 */

if (!defined('ABSPATH')) {
    exit; // Sortie si accès direct
}

// S'assurer que la classe d'origine existe
if (!class_exists('WC_Momo_Iwomipay')) {
    return;
}

/**
 * Classe adaptateur pour MTN MoMo
 */
class LTE_Momo_Adapter extends WC_Momo_Iwomipay {
    /**
     * Constructeur
     */
    public function __construct() {
        // Appel du constructeur parent
        parent::__construct();
        
        // Personnalisation de l'ID et du titre
        $this->id = 'iwomipay_payment_momo';
        $this->method_title = __('MTN Mobile Money (Life Travel)', 'life-travel-payment-adapters');
        $this->method_description = __('Paiement via MTN Mobile Money - Adapté pour Life Travel', 'life-travel-payment-adapters');
        
        // Ajouter des hooks spécifiques à Life Travel
        add_action('woocommerce_receipt_' . $this->id, array($this, 'lte_receipt_page'));
        add_action('woocommerce_api_lte_momo_callback', array($this, 'lte_check_response'));
        
        // Modifier l'URL de callback
        $this->notify_url = WC()->api_request_url('lte_momo_callback');
    }
    
    /**
     * Initialiser les paramètres
     */
    public function init_settings() {
        parent::init_settings();
        
        // Personnaliser les paramètres si nécessaire
        $this->settings['description'] = isset($this->settings['description']) 
            ? $this->settings['description'] 
            : __('Paiement sécurisé via MTN Mobile Money - Par Life Travel', 'life-travel-payment-adapters');
    }
    
    /**
     * Page de réception du paiement (personnalisée)
     */
    public function lte_receipt_page($order_id) {
        // Personnalisation de la page de réception avant de déléguer au parent
        echo '<div class="lte-payment-notice">';
        echo __('Vous allez être redirigé vers MTN Mobile Money pour compléter votre paiement...', 'life-travel-payment-adapters');
        echo '</div>';
        
        // Appel de la méthode parente
        parent::receipt_page($order_id);
    }
    
    /**
     * Vérification de la réponse (surcharge)
     */
    public function lte_check_response() {
        // Journaliser la réponse
        $this->log_payment_response($_REQUEST);
        
        // Déléguer à la méthode parente
        parent::check_response();
    }
    
    /**
     * Journaliser la réponse de paiement
     */
    private function log_payment_response($response_data) {
        // Implémenter la journalisation
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            $log_file = WP_CONTENT_DIR . '/lte-payment-logs/momo-' . date('Y-m-d') . '.log';
            $log_dir = dirname($log_file);
            
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            
            $log_data = date('[Y-m-d H:i:s]') . ' Response: ' . print_r($response_data, true);
            file_put_contents($log_file, $log_data . PHP_EOL, FILE_APPEND);
        }
    }
    
    /**
     * Traiter le paiement (surcharge)
     */
    public function process_payment($order_id) {
        // Ajouter une action de pré-traitement
        do_action('lte_before_momo_payment_process', $order_id, $this);
        
        // Déléguer à la méthode parente
        $result = parent::process_payment($order_id);
        
        // Ajouter une action de post-traitement
        do_action('lte_after_momo_payment_process', $order_id, $result, $this);
        
        return $result;
    }
    
    /**
     * Vérifier si WOOCOMMERCE_VERSION est définie et la définir si nécessaire
     */
    protected function ensure_wc_version() {
        if (!defined('WOOCOMMERCE_VERSION')) {
            define('WOOCOMMERCE_VERSION', WC()->version);
        }
    }
    
    /**
     * Gestion moderne du stock
     */
    protected function reduce_order_stock($order) {
        $this->ensure_wc_version();
        
        if (function_exists('wc_reduce_stock_levels')) {
            wc_reduce_stock_levels($order->get_id());
        } else {
            $order->reduce_order_stock();
        }
    }
}
