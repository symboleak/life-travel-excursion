<?php
/**
 * Gestion de l'affichage des prix et des détails
 * 
 * Ce fichier améliore l'affichage des prix des excursions dans le panier, 
 * le checkout et les pages produits, en tenant compte des contraintes mobiles.
 * 
 * @package Life Travel Excursion
 * @version 2.3.3
 */

defined('ABSPATH') || exit;

class Life_Travel_Price_Display {

    /**
     * Constructeur
     */
    public function __construct() {
        // Filtres pour l'affichage des prix et des détails dans WooCommerce
        add_filter('woocommerce_get_price_html', array($this, 'modify_excursion_price_display'), 10, 2);
        add_filter('woocommerce_cart_item_name', array($this, 'add_excursion_details_to_cart'), 10, 3);
        add_filter('woocommerce_order_item_name', array($this, 'add_excursion_details_to_order'), 10, 2);
        
        // Ajouter la décomposition des prix dans les produits excursion
        add_action('woocommerce_before_add_to_cart_button', array($this, 'add_price_breakdown_before_button'));
        add_action('woocommerce_after_add_to_cart_form', array($this, 'add_price_breakdown_container'));
        
        // Format adapté pour les mobiles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_responsive_price_assets'));
    }
    
    /**
     * Modifie l'affichage du prix pour les produits de type excursion
     * 
     * @param string $price_html Le HTML du prix
     * @param WC_Product $product Le produit
     * @return string Le HTML du prix modifié
     */
    public function modify_excursion_price_display($price_html, $product) {
        if ($product->get_type() !== 'excursion') {
            return $price_html;
        }
        
        // Prix par personne si défini, sinon prix standard
        $price_per_person = $product->get_meta('_price_per_person');
        
        if (!$price_per_person) {
            $price_per_person = $product->get_price();
        }
        
        // Format du prix tenant compte des contraintes mobiles
        if (wp_is_mobile()) {
            // Version compacte pour mobile
            $html = sprintf(
                '<span class="excursion-price mobile">%s<span class="price-per">/%s</span></span>',
                wc_price($price_per_person),
                __('pers.', 'life-travel-excursion')
            );
        } else {
            // Version desktop complète
            $html = sprintf(
                '<span class="excursion-price">%s<span class="price-per">/%s</span></span>',
                wc_price($price_per_person),
                __('personne', 'life-travel-excursion')
            );
        }
        
        return $html;
    }
    
    /**
     * Ajouter les détails de l'excursion dans le panier
     * 
     * @param string $name Nom du produit
     * @param array $cart_item Élément du panier
     * @param string $cart_item_key Clé de l'élément
     * @return string Nom augmenté avec les détails
     */
    public function add_excursion_details_to_cart($name, $cart_item, $cart_item_key) {
        if (!isset($cart_item['data']) || $cart_item['data']->get_type() !== 'excursion') {
            return $name;
        }
        
        // Récupérer les données de l'excursion
        $participants = isset($cart_item['participants']) ? intval($cart_item['participants']) : 1;
        $start_date = isset($cart_item['start_date']) ? $cart_item['start_date'] : '';
        $end_date = isset($cart_item['end_date']) ? $cart_item['end_date'] : '';
        $vehicles_needed = isset($cart_item['vehicles_needed']) ? intval($cart_item['vehicles_needed']) : 1;
        
        // Formater les dates pour l'affichage
        $formatted_start = !empty($start_date) ? date_i18n(get_option('date_format'), strtotime($start_date)) : '';
        $formatted_end = !empty($end_date) ? date_i18n(get_option('date_format'), strtotime($end_date)) : '';
        
        // Info des dates
        $date_info = '';
        if (!empty($formatted_start) && !empty($formatted_end) && $formatted_start !== $formatted_end) {
            $date_info = sprintf(__('Du %s au %s', 'life-travel-excursion'), $formatted_start, $formatted_end);
        } elseif (!empty($formatted_start)) {
            $date_info = sprintf(__('Le %s', 'life-travel-excursion'), $formatted_start);
        }
        
        // Pour mobile, format compact
        if (wp_is_mobile()) {
            $html = '<div class="excursion-cart-details mobile">';
            $html .= '<span class="excursion-name">' . esc_html($name) . '</span>';
            
            if (!empty($date_info)) {
                $html .= '<span class="excursion-date">' . esc_html($date_info) . '</span>';
            }
            
            $html .= '<span class="excursion-participants">' . sprintf(_n('%d participant', '%d participants', $participants, 'life-travel-excursion'), $participants) . '</span>';
            
            // Info véhicules si plus d'un véhicule
            if ($vehicles_needed > 1) {
                $html .= '<span class="excursion-vehicles">' . sprintf(_n('%d véhicule', '%d véhicules', $vehicles_needed, 'life-travel-excursion'), $vehicles_needed) . '</span>';
            }
            
            $html .= '</div>';
        } else {
            // Format desktop complet
            $html = '<div class="excursion-cart-details">';
            $html .= '<span class="excursion-name">' . esc_html($name) . '</span>';
            
            $html .= '<div class="excursion-meta">';
            
            if (!empty($date_info)) {
                $html .= '<span class="excursion-date">' . esc_html($date_info) . '</span>';
            }
            
            $html .= '<span class="excursion-participants">' . sprintf(_n('%d participant', '%d participants', $participants, 'life-travel-excursion'), $participants) . '</span>';
            
            // Info véhicules
            $html .= '<span class="excursion-vehicles">' . sprintf(_n('%d véhicule', '%d véhicules', $vehicles_needed, 'life-travel-excursion'), $vehicles_needed) . '</span>';
            
            $html .= '</div>';
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * Ajouter les détails de l'excursion dans une commande
     * 
     * @param string $name Nom de l'élément
     * @param WC_Order_Item $item L'élément de commande
     * @return string Nom augmenté avec les détails
     */
    public function add_excursion_details_to_order($name, $item) {
        // Vérifier si c'est une excursion
        $product_id = $item->get_product_id();
        $product = wc_get_product($product_id);
        
        if (!$product || $product->get_type() !== 'excursion') {
            return $name;
        }
        
        // Récupérer les métadonnées de l'élément
        $participants = $item->get_meta('Participants') ? intval($item->get_meta('Participants')) : 1;
        $start_date = $item->get_meta('Date de début');
        $end_date = $item->get_meta('Date de fin');
        $vehicles_needed = $item->get_meta('_vehicles_needed') ? intval($item->get_meta('_vehicles_needed')) : 1;
        
        // Info des dates
        $date_info = '';
        if (!empty($start_date) && !empty($end_date) && $start_date !== $end_date) {
            $date_info = sprintf(__('Du %s au %s', 'life-travel-excursion'), $start_date, $end_date);
        } elseif (!empty($start_date)) {
            $date_info = sprintf(__('Le %s', 'life-travel-excursion'), $start_date);
        }
        
        // Format adapté pour l'administration et le frontend
        $html = '<div class="excursion-order-details">';
        $html .= '<span class="excursion-name">' . esc_html($name) . '</span>';
        
        $html .= '<div class="excursion-meta">';
        
        if (!empty($date_info)) {
            $html .= '<span class="excursion-date">' . esc_html($date_info) . '</span>';
        }
        
        $html .= '<span class="excursion-participants">' . sprintf(_n('%d participant', '%d participants', $participants, 'life-travel-excursion'), $participants) . '</span>';
        
        // Info véhicules
        if ($vehicles_needed > 1) {
            $html .= '<span class="excursion-vehicles">' . sprintf(_n('%d véhicule', '%d véhicules', $vehicles_needed, 'life-travel-excursion'), $vehicles_needed) . '</span>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Ajouter le bouton de calcul de prix
     */
    public function add_price_breakdown_before_button() {
        global $product;
        
        if (!$product || $product->get_type() !== 'excursion') {
            return;
        }
        
        // Bouton pour calculer le prix
        echo '<button type="button" class="calculate-price-button button alt">' . esc_html__('Calculer le prix', 'life-travel-excursion') . '</button>';
    }
    
    /**
     * Ajouter le conteneur pour la décomposition des prix
     */
    public function add_price_breakdown_container() {
        global $product;
        
        if (!$product || $product->get_type() !== 'excursion') {
            return;
        }
        
        // Conteneur pour afficher la décomposition des prix
        echo '<div class="price-breakdown"></div>';
    }
    
    /**
     * Enregistrer les scripts et styles pour l'affichage responsive des prix
     */
    public function enqueue_responsive_price_assets() {
        // Styles CSS pour l'affichage des prix
        wp_enqueue_style(
            'life-travel-price-display',
            LIFE_TRAVEL_EXCURSION_ASSETS . 'css/price-display.css',
            array(),
            LIFE_TRAVEL_EXCURSION_VERSION
        );
        
        // Script JS pour le calcul et l'affichage des prix
        wp_enqueue_script(
            'life-travel-price-calculator',
            LIFE_TRAVEL_EXCURSION_ASSETS . 'js/price-calculator.js',
            array('jquery'),
            LIFE_TRAVEL_EXCURSION_VERSION,
            true
        );
        
        // Passer des variables au script
        wp_localize_script(
            'life-travel-price-calculator',
            'lifeTravelPrices',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'security' => wp_create_nonce('calculate_price_nonce'),
                'is_mobile' => wp_is_mobile(),
                'currency' => get_woocommerce_currency_symbol(),
                'strings' => array(
                    'calculating' => __('Calcul en cours...', 'life-travel-excursion'),
                    'per_person' => __('par personne', 'life-travel-excursion'),
                    'base_price' => __('Prix de base', 'life-travel-excursion'),
                    'extras' => __('Extras', 'life-travel-excursion'),
                    'activities' => __('Activités', 'life-travel-excursion'),
                    'vehicles' => __('Véhicules', 'life-travel-excursion'),
                    'total' => __('Total', 'life-travel-excursion'),
                )
            )
        );
    }
}

// Initialiser la classe
new Life_Travel_Price_Display();
