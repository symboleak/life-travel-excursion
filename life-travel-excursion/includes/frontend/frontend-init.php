<?php
/**
 * Initialisation des composants frontend pour Life Travel Excursion
 * 
 * Ce fichier gère l'initialisation des scripts, styles et autres composants
 * nécessaires au bon fonctionnement du frontend du plugin.
 * 
 * @package Life_Travel_Excursion
 * @since 2.0.0
 */

// Sortie directe interdite
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe d'initialisation des composants frontend
 */
class Life_Travel_Frontend_Init {
    
    /**
     * Instance unique (Singleton)
     * @var Life_Travel_Frontend_Init
     */
    private static $instance = null;
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        // Initialiser les composants frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Ajouter les shortcodes
        add_shortcode('life_travel_booking_form', array($this, 'booking_form_shortcode'));
        
        // Ajouter des filtres utilitaires
        add_filter('life_travel_get_formatted_price', array($this, 'get_formatted_price'), 10, 2);
    }
    
    /**
     * Obtenir l'instance unique
     * @return Life_Travel_Frontend_Init
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Enregistrer et charger les assets frontend (CSS/JS)
     */
    public function enqueue_frontend_assets() {
        $min_suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
        
        // Enregistrer les styles
        wp_enqueue_style(
            'jquery-ui-style',
            'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css',
            array(),
            '1.12.1'
        );
        
        wp_enqueue_style(
            'life-travel-excursion-style',
            LIFE_TRAVEL_PLUGIN_URL . 'assets/css/frontend' . $min_suffix . '.css',
            array('jquery-ui-style'),
            LIFE_TRAVEL_VERSION
        );
        
        // Enregistrer jQuery UI Datepicker
        wp_enqueue_script('jquery-ui-datepicker');
        
        // Enregistrer les scripts principaux
        wp_enqueue_script(
            'life-travel-excursion-frontend',
            LIFE_TRAVEL_PLUGIN_URL . 'assets/js/life-travel-excursion-frontend' . $min_suffix . '.js',
            array('jquery', 'jquery-ui-datepicker'),
            LIFE_TRAVEL_VERSION,
            true
        );
        
        // Enregistrer le script du calendrier de réservation
        wp_enqueue_script(
            'life-travel-booking-calendar',
            LIFE_TRAVEL_PLUGIN_URL . 'assets/js/booking-calendar' . $min_suffix . '.js',
            array('jquery', 'jquery-ui-datepicker', 'life-travel-excursion-frontend'),
            LIFE_TRAVEL_VERSION,
            true
        );
        
        // Enregistrer le script de calculateur de prix
        wp_enqueue_script(
            'life-travel-price-calculator',
            LIFE_TRAVEL_PLUGIN_URL . 'assets/js/price-calculator' . $min_suffix . '.js',
            array('jquery', 'life-travel-excursion-frontend'),
            LIFE_TRAVEL_VERSION,
            true
        );
        
        // Localiser les scripts
        $this->localize_frontend_scripts();
    }
    
    /**
     * Localiser les scripts frontend avec les données nécessaires
     */
    private function localize_frontend_scripts() {
        // Options globales
        $options = get_option('life_travel_excursion_options', array());
        
        // Données de base pour le frontend
        $data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('life_travel_frontend_nonce'),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'is_mobile' => wp_is_mobile(),
        );
        
        // Appliquer le filtre pour permettre à d'autres composants d'ajouter des données
        $data = apply_filters('life_travel_frontend_localize_script', $data);
        
        // Localiser les scripts
        wp_localize_script('life-travel-excursion-frontend', 'lifeTravel', $data);
    }
    
    /**
     * Shortcode pour le formulaire de réservation
     * 
     * @param array $atts Attributs du shortcode
     * @return string Contenu HTML
     */
    public function booking_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'template' => 'default',
        ), $atts, 'life_travel_booking_form');
        
        $product_id = absint($atts['id']);
        if (empty($product_id)) {
            return '<p class="life-travel-error">' . __('ID d\'excursion non spécifié.', 'life-travel-excursion') . '</p>';
        }
        
        // Vérifier si le produit existe et est une excursion
        $product = wc_get_product($product_id);
        if (!$product) {
            return '<p class="life-travel-error">' . __('Excursion non trouvée.', 'life-travel-excursion') . '</p>';
        }
        
        // Récupérer les données de l'excursion
        $excursion_data = $this->get_excursion_data($product_id);
        
        // Inclure le template du formulaire
        ob_start();
        include LIFE_TRAVEL_PLUGIN_DIR . 'templates/excursion-booking-form.php';
        return ob_get_clean();
    }
    
    /**
     * Récupérer les données d'une excursion
     * 
     * @param int $product_id ID du produit
     * @return array Données de l'excursion
     */
    private function get_excursion_data($product_id) {
        // Sécurisation de l'entrée
        $product_id = absint($product_id);
        
        // Données par défaut
        $data = array(
            'participant_limit' => 30,
            'min_days_before' => 1,
            'is_fixed_date' => 'no',
            'start_date' => '',
            'end_date' => '',
            'pricing_tiers' => array(),
            'extras_list' => '',
            'activities_list' => '',
            'excursion_type' => 'group',
        );
        
        // Récupérer les méta-données du produit
        $data['participant_limit'] = get_post_meta($product_id, '_participant_limit', true) ?: $data['participant_limit'];
        $data['min_days_before'] = get_post_meta($product_id, '_min_days_before', true) ?: $data['min_days_before'];
        $data['is_fixed_date'] = get_post_meta($product_id, '_is_fixed_date', true) ?: $data['is_fixed_date'];
        $data['start_date'] = get_post_meta($product_id, '_start_date', true) ?: $data['start_date'];
        $data['end_date'] = get_post_meta($product_id, '_end_date', true) ?: $data['end_date'];
        $data['pricing_tiers'] = get_post_meta($product_id, '_pricing_tiers', true) ?: $data['pricing_tiers'];
        $data['extras_list'] = get_post_meta($product_id, '_extras_list', true) ?: $data['extras_list'];
        $data['activities_list'] = get_post_meta($product_id, '_activities_list', true) ?: $data['activities_list'];
        $data['excursion_type'] = get_post_meta($product_id, '_excursion_type', true) ?: $data['excursion_type'];
        
        // Appliquer les filtres pour permettre d'étendre les données
        return apply_filters('life_travel_excursion_data', $data, $product_id);
    }
    
    /**
     * Formatter un prix selon les paramètres configurés
     * 
     * @param float $price Prix à formater
     * @param array $options Options de formatage spécifiques (optionnel)
     * @return string Prix formaté
     */
    public function get_formatted_price($price, $options = array()) {
        // Récupérer les options globales
        $global_options = get_option('life_travel_excursion_options', array());
        
        // Fusionner avec les options spécifiques
        $format_options = wp_parse_args($options, array(
            'currency_position' => isset($global_options['currency_position']) ? $global_options['currency_position'] : 'after',
            'thousand_separator' => isset($global_options['price_thousand_separator']) ? $global_options['price_thousand_separator'] : ' ',
            'decimal_separator' => isset($global_options['price_decimal_separator']) ? $global_options['price_decimal_separator'] : ',',
            'decimals' => isset($global_options['price_decimals']) ? intval($global_options['price_decimals']) : 0,
        ));
        
        // Formater le prix
        $formatted_price = number_format(
            $price,
            $format_options['decimals'],
            $format_options['decimal_separator'],
            $format_options['thousand_separator']
        );
        
        // Ajouter le symbole de devise selon la position configurée
        $currency_symbol = get_woocommerce_currency_symbol();
        
        if ($format_options['currency_position'] === 'before') {
            return $currency_symbol . $formatted_price;
        } else {
            return $formatted_price . ' ' . $currency_symbol;
        }
    }
}

// Initialiser l'instance
Life_Travel_Frontend_Init::get_instance();
