<?php
/**
 * Adaptateur pour les passerelles de paiement
 * 
 * Ce fichier crée une interface simplifiée pour intégrer les passerelles de paiement
 * IwomiPay (Mobile Money et Carte) dans notre plugin Life Travel Excursion.
 * 
 * @package Life Travel Excursion
 * @version 2.3.3
 */

defined('ABSPATH') || exit;

/**
 * Classe d'adaptation pour les passerelles de paiement
 */
class Life_Travel_Payment_Adapter {
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Initialiser les passerelles de paiement
        add_action('plugins_loaded', array($this, 'init_payment_gateways'));
        
        // Ajouter des champs de configuration supplémentaires
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_life_travel_payments', array($this, 'settings_tab_content'));
        add_action('woocommerce_update_options_life_travel_payments', array($this, 'update_settings'));
        
        // Personnaliser l'affichage des méthodes de paiement sur la page de checkout
        add_filter('woocommerce_available_payment_gateways', array($this, 'customize_payment_methods_display'));
        
        // Ajouter des icônes personnalisées aux méthodes de paiement
        add_filter('woocommerce_gateway_icon', array($this, 'custom_payment_gateway_icons'), 10, 2);
    }
    
    /**
     * Initialiser les passerelles de paiement
     */
    public function init_payment_gateways() {
        // Vérifier si WooCommerce est activé
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        
        // Définir les chemins vers les fichiers des passerelles installées dans le dossier payment-gateways
        $payment_gateways_dir = plugin_dir_path(dirname(__FILE__)) . 'payment-gateways/';
        $momo_file = $payment_gateways_dir . 'iwomipay-momo-woocommerce/iwomipay-momo-woocommerce.php';
        $card_file = $payment_gateways_dir . 'iwomipay-card-woocommerce/iwomipay-card-woocommerce.php';
        
        // Vérifier si les passerelles sont installées
        $momo_installed = file_exists($momo_file);
        $card_installed = file_exists($card_file);
        
        // Journal de débogage (si mode débogage activé)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('IwomiPay MoMo: ' . ($momo_installed ? 'Installé' : 'Manquant'));
            error_log('IwomiPay Card: ' . ($card_installed ? 'Installé' : 'Manquant'));
        }
        
        // Charger les passerelles si elles sont installées
        if ($momo_installed && !class_exists('WC_Momo_Iwomipay')) {
            require_once $momo_file;
        }
        
        if ($card_installed && !class_exists('WC_Card_Iwomipay')) {
            require_once $card_file;
        }
        
        // Créer les wrappers pour faciliter les futures migrations si nécessaire
        $this->create_payment_gateway_wrappers();
        
        // Ajouter nos passerelles à la liste WooCommerce
        add_filter('woocommerce_payment_gateways', array($this, 'add_payment_gateways'));
        
        // Ajouter un avertissement admin si les passerelles ne sont pas installées
        if (!$momo_installed || !$card_installed) {
            add_action('admin_notices', array($this, 'missing_gateways_notice'));
        }
    }
    
    /**
     * Créer des wrappers pour les passerelles de paiement
     * Cela permet de faciliter une future migration si nécessaire
     */
    private function create_payment_gateway_wrappers() {
        // Vérifier si les classes de base existent
        if (class_exists('WC_Momo_Iwomipay') && !class_exists('Life_Travel_Mobile_Money_Gateway')) {
            // Créer un wrapper pour MoMo
            if (!class_exists('Life_Travel_Mobile_Money_Gateway', false)) {
                class_alias('WC_Momo_Iwomipay', 'Life_Travel_Mobile_Money_Gateway');
            }
        }
        
        if (class_exists('WC_Card_Iwomipay') && !class_exists('Life_Travel_Card_Gateway')) {
            // Créer un wrapper pour Card
            if (!class_exists('Life_Travel_Card_Gateway', false)) {
                class_alias('WC_Card_Iwomipay', 'Life_Travel_Card_Gateway');
            }
        }
    }
    
    /**
     * Ajouter nos passerelles de paiement à WooCommerce
     * 
     * @param array $gateways Tableau des passerelles enregistrées
     * @return array Tableau mis à jour avec nos passerelles
     */
    public function add_payment_gateways($gateways) {
        // Priorité aux passerelles IwomiPay originales, avec nos alias comme fallback
        if (class_exists('WC_Momo_Iwomipay')) {
            $gateways[] = 'WC_Momo_Iwomipay';
        } elseif (class_exists('Life_Travel_Mobile_Money_Gateway')) {
            $gateways[] = 'Life_Travel_Mobile_Money_Gateway';
        }
        
        if (class_exists('WC_Card_Iwomipay')) {
            $gateways[] = 'WC_Card_Iwomipay';
        } elseif (class_exists('Life_Travel_Card_Gateway')) {
            $gateways[] = 'Life_Travel_Card_Gateway';
        }
        
        return $gateways;
    }
    
    /**
     * Ajouter un onglet de configuration pour les passerelles de paiement
     * 
     * @param array $tabs Onglets existants
     * @return array Onglets mis à jour
     */
    public function add_settings_tab($tabs) {
        $tabs['life_travel_payments'] = __('Paiements Life Travel', 'life-travel-excursion');
        return $tabs;
    }
    
    /**
     * Contenu de l'onglet de configuration
     */
    public function settings_tab_content() {
        woocommerce_admin_fields($this->get_settings());
    }
    
    /**
     * Mettre à jour les paramètres
     */
    public function update_settings() {
        woocommerce_update_options($this->get_settings());
    }
    
    /**
     * Obtenir les paramètres de configuration
     * 
     * @return array Paramètres de configuration
     */
    public function get_settings() {
        $settings = array(
            'section_title' => array(
                'name'     => __('Configuration des paiements pour Life Travel', 'life-travel-excursion'),
                'type'     => 'title',
                'desc'     => __('Configurez les options de paiement spécifiques à Life Travel Excursion.', 'life-travel-excursion'),
                'id'       => 'life_travel_payment_section_title'
            ),
            
            'test_mode' => array(
                'name'     => __('Mode test', 'life-travel-excursion'),
                'type'     => 'checkbox',
                'desc'     => __('Activer le mode test pour les passerelles de paiement', 'life-travel-excursion'),
                'default'  => 'yes',
                'id'       => 'life_travel_payment_test_mode'
            ),
            
            'mobile_money_priority' => array(
                'name'     => __('Priorité Mobile Money', 'life-travel-excursion'),
                'type'     => 'number',
                'desc'     => __('Ordre d\'affichage pour Mobile Money (plus petit = plus haut)', 'life-travel-excursion'),
                'default'  => '10',
                'id'       => 'life_travel_payment_momo_priority',
                'custom_attributes' => array(
                    'min'  => '1',
                    'step' => '1'
                )
            ),
            
            'card_priority' => array(
                'name'     => __('Priorité Carte', 'life-travel-excursion'),
                'type'     => 'number',
                'desc'     => __('Ordre d\'affichage pour paiement par Carte (plus petit = plus haut)', 'life-travel-excursion'),
                'default'  => '20',
                'id'       => 'life_travel_payment_card_priority',
                'custom_attributes' => array(
                    'min'  => '1',
                    'step' => '1'
                )
            ),
            
            'custom_icons' => array(
                'name'     => __('Utiliser icônes personnalisées', 'life-travel-excursion'),
                'type'     => 'checkbox',
                'desc'     => __('Utiliser les icônes Life Travel au lieu des icônes par défaut', 'life-travel-excursion'),
                'default'  => 'yes',
                'id'       => 'life_travel_payment_custom_icons'
            ),
            
            'section_end' => array(
                'type'     => 'sectionend',
                'id'       => 'life_travel_payment_section_end'
            )
        );
        
        return $settings;
    }
    
    /**
     * Personnaliser l'affichage des méthodes de paiement
     * 
     * @param array $available_gateways Passerelles disponibles
     * @return array Passerelles personnalisées
     */
    public function customize_payment_methods_display($available_gateways) {
        // Vérifier que nous sommes sur la page de checkout et que nous avons des passerelles
        if (!is_checkout() || empty($available_gateways)) {
            return $available_gateways;
        }
        
        // Vérifier si nous avons nos passerelles personnalisées
        if (isset($available_gateways['iwomipay_payment_momo'])) {
            // Modifier la priorité de la passerelle Mobile Money
            $available_gateways['iwomipay_payment_momo']->order = get_option('life_travel_payment_momo_priority', 10);
            
            // Personnaliser le titre si nécessaire
            if (get_option('life_travel_payment_custom_icons', 'yes') === 'yes') {
                $available_gateways['iwomipay_payment_momo']->title = __('Mobile Money (MTN)', 'life-travel-excursion');
            }
        }
        
        if (isset($available_gateways['iwomipay_payment_card'])) {
            // Modifier la priorité de la passerelle Carte
            $available_gateways['iwomipay_payment_card']->order = get_option('life_travel_payment_card_priority', 20);
            
            // Personnaliser le titre si nécessaire
            if (get_option('life_travel_payment_custom_icons', 'yes') === 'yes') {
                $available_gateways['iwomipay_payment_card']->title = __('Carte bancaire', 'life-travel-excursion');
            }
        }
        
        // Trier les passerelles par priorité
        uasort($available_gateways, function($a, $b) {
            return $a->order - $b->order;
        });
        
        return $available_gateways;
    }
    
    /**
     * Ajouter des icônes personnalisées aux méthodes de paiement
     * 
     * @param string $icon Icône actuelle
     * @param string $id ID de la passerelle
     * @return string Icône personnalisée
     */
    public function custom_payment_gateway_icons($icon, $id) {
        // Vérifier si nous devons utiliser des icônes personnalisées
        if (get_option('life_travel_payment_custom_icons', 'yes') !== 'yes') {
            return $icon;
        }
        
        $custom_icons = array(
            'iwomipay_payment_momo' => plugin_dir_url(dirname(__FILE__)) . 'assets/img/momo-icon.png',
            'iwomipay_payment_card' => plugin_dir_url(dirname(__FILE__)) . 'assets/img/card-icon.png'
        );
        
        if (isset($custom_icons[$id])) {
            $icon_url = $custom_icons[$id];
            $icon = '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($id) . '" class="life-travel-payment-icon" />';
        }
        
        return $icon;
    }
    
    /**
     * Affiche une notification si les passerelles de paiement sont manquantes
     */
    public function missing_gateways_notice() {
        $payment_gateways_dir = plugin_dir_path(dirname(__FILE__)) . 'payment-gateways/';
        $momo_file = $payment_gateways_dir . 'iwomipay-momo-woocommerce/iwomipay-momo-woocommerce.php';
        $card_file = $payment_gateways_dir . 'iwomipay-card-woocommerce/iwomipay-card-woocommerce.php';
        
        $missing_gateways = [];
        if (!file_exists($momo_file)) {
            $missing_gateways[] = 'IwomiPay Mobile Money';
        }
        if (!file_exists($card_file)) {
            $missing_gateways[] = 'IwomiPay Card';
        }
        
        if (!empty($missing_gateways)) {
            echo '<div class="error"><p><strong>';
            echo esc_html__('Passerelles de paiement manquantes:', 'life-travel-excursion') . ' ';
            echo esc_html(implode(', ', $missing_gateways));
            echo '</strong></p><p>';
            echo esc_html__('Veuillez installer les passerelles de paiement suivantes dans le dossier payment-gateways:', 'life-travel-excursion');
            echo '</p><ul>';
            foreach ($missing_gateways as $gateway) {
                echo '<li>- ' . esc_html($gateway) . '</li>';
            }
            echo '</ul>';
            echo '<p><a href="https://www.iwomipay.com/woocommerce" target="_blank" class="button">';
            echo esc_html__('Télécharger les passerelles IwomiPay', 'life-travel-excursion');
            echo '</a></p></div>';
        }
    }
}

// Initialiser l'adaptateur de paiement
new Life_Travel_Payment_Adapter();
