<?php
/**
 * Classe de gestion des adaptateurs de paiement pour Life Travel
 */

if (!defined('ABSPATH')) {
    exit; // Sortie si accès direct
}

class LTE_Payment_Manager {
    /**
     * Instance unique de la classe
     * @var LTE_Payment_Manager
     */
    private static $instance = null;

    /**
     * Constructeur
     */
    public function __construct() {
        // Rien à initialiser pour l'instant
    }

    /**
     * Singleton: obtenir l'instance unique
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialiser les hooks et filtres
     */
    public function init() {
        // Filtrer les gateways WooCommerce pour utiliser nos adaptateurs
        add_filter('woocommerce_payment_gateways', array($this, 'register_payment_gateways'), 20);
        
        // Hooks pour journaliser les transactions pour le suivi
        add_action('woocommerce_payment_complete', array($this, 'log_payment_complete'), 10, 1);
        
        // Hooks spécifiques pour la gestion des erreurs
        add_action('woocommerce_api_lte_iwomipay_callback', array($this, 'handle_iwomipay_callback'), 10);
        
        // Hooks d'administration
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * Enregistrer nos adaptateurs de passerelles de paiement
     */
    public function register_payment_gateways($gateways) {
        // Remplacer les gateways standards par nos adaptateurs
        foreach($gateways as $key => $gateway) {
            if ($gateway === 'WC_Momo_Iwomipay') {
                $gateways[$key] = 'LTE_Momo_Adapter';
            } else if ($gateway === 'WC_OM_Iwomipay') {
                $gateways[$key] = 'LTE_OM_Adapter';
            }
        }
        
        return $gateways;
    }

    /**
     * Journal de suivi des paiements
     */
    public function log_payment_complete($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $payment_method = $order->get_payment_method();
        if (strpos($payment_method, 'iwomipay') !== false) {
            // Journaliser la transaction IwomiPay
            $this->log_transaction($order);
        }
    }
    
    /**
     * Enregistrer les détails de la transaction
     */
    private function log_transaction($order) {
        // Implémenter la journalisation des transactions
        // Cette fonction pourrait enregistrer les détails dans un fichier log
        // ou dans une table personnalisée pour le reporting
    }
    
    /**
     * Gestion du callback IwomiPay
     */
    public function handle_iwomipay_callback() {
        // Logique de callback centralisée
        // Cette fonction pourrait rediriger vers la bonne passerelle
        // ou implémenter une logique commune
    }
    
    /**
     * Ajout du menu d'administration
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Life Travel Paiements', 'life-travel-payment-adapters'),
            __('Life Travel Paiements', 'life-travel-payment-adapters'),
            'manage_woocommerce',
            'life-travel-payments',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Rendu de la page d'administration
     */
    public function render_admin_page() {
        // Interface d'administration pour surveiller et gérer les paiements
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Life Travel - Gestion des Paiements', 'life-travel-payment-adapters'); ?></h1>
            <div class="card">
                <h2><?php echo esc_html__('Statut des passerelles de paiement', 'life-travel-payment-adapters'); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Passerelle', 'life-travel-payment-adapters'); ?></th>
                            <th><?php echo esc_html__('Statut', 'life-travel-payment-adapters'); ?></th>
                            <th><?php echo esc_html__('Version', 'life-travel-payment-adapters'); ?></th>
                            <th><?php echo esc_html__('Actions', 'life-travel-payment-adapters'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>MTN MoMo (IwomiPay)</td>
                            <td><?php echo $this->check_gateway_status('momo') ? '<span style="color:green;">✓ Actif</span>' : '<span style="color:red;">✗ Inactif</span>'; ?></td>
                            <td><?php echo $this->get_gateway_version('momo'); ?></td>
                            <td><a href="#" class="button"><?php echo esc_html__('Configuration', 'life-travel-payment-adapters'); ?></a></td>
                        </tr>
                        <tr>
                            <td>Orange Money (IwomiPay)</td>
                            <td><?php echo $this->check_gateway_status('om') ? '<span style="color:green;">✓ Actif</span>' : '<span style="color:red;">✗ Inactif</span>'; ?></td>
                            <td><?php echo $this->get_gateway_version('om'); ?></td>
                            <td><a href="#" class="button"><?php echo esc_html__('Configuration', 'life-travel-payment-adapters'); ?></a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Vérifier si une passerelle est active
     */
    private function check_gateway_status($gateway_type) {
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        
        $gateway_id = 'iwomipay_payment_' . $gateway_type;
        return isset($available_gateways[$gateway_id]);
    }
    
    /**
     * Obtenir la version d'une passerelle
     */
    private function get_gateway_version($gateway_type) {
        // Dans une implémentation réelle, vous pourriez extraire cette information 
        // du plugin d'origine ou de votre propre système de versionnage
        return '1.0.0';
    }
}
