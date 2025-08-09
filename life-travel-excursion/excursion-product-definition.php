<?php
/**
 * Définitions et fonctions du type de produit "excursion"
 * 
 * Contient la classe du produit personnalisé et les fonctions d'enregistrement
 * Ce fichier est conçu pour être inclus au début du fichier principal
 */

// Vérifier si la classe existe déjà pour éviter les redéclarations
if (!class_exists('WC_Product_Excursion') && class_exists('WC_Product_Simple')) {
    /**
     * Classe de produit personnalisée pour les excursions
     */
    class WC_Product_Excursion extends WC_Product_Simple {
        /**
         * Retourne le type de produit
         * 
         * @return string Le type de produit "excursion"
         */
        public function get_type() {
            return 'excursion';
        }
    }
}

/**
 * Ajoute le type "excursion" au sélecteur de types de produits WooCommerce
 * 
 * @param array $types Types de produits WooCommerce existants
 * @return array Types de produits mis à jour
 */
function life_travel_excursion_add_product_type($types) {
    $types['excursion'] = __('Excursion', 'life-travel-excursion');
    return $types;
}

/**
 * Enregistre la classe personnalisée pour le type de produit "excursion"
 * 
 * @param string $classname Nom de la classe produit actuelle
 * @param string $product_type Type de produit à vérifier
 * @return string Nom de la classe à utiliser pour ce produit
 */
function life_travel_excursion_product_class($classname, $product_type) {
    if ($product_type === 'excursion') {
        $classname = 'WC_Product_Excursion';
    }
    return $classname;
}

/**
 * Initialisation du type de produit personnalisé pour les excursions
 * 
 * Enregistre les hooks nécessaires pour le type de produit "excursion"
 * Cette fonction est exécutée uniquement après le chargement de WooCommerce
 */
function life_travel_excursion_init() {
    // Hooks pour le type de produit excursion
    add_filter('product_type_selector', 'life_travel_excursion_add_product_type');
    add_filter('woocommerce_product_class', 'life_travel_excursion_product_class', 10, 2);
    
    // Charger les scripts et styles pour le front et l'admin
    add_action('wp_enqueue_scripts', 'life_travel_excursion_enqueue_scripts');
    add_action('admin_enqueue_scripts', 'life_travel_excursion_admin_scripts');
    
    // Hooks pour les champs personnalisés et le formulaire de réservation
    add_action('woocommerce_product_options_general_product_data', 'life_travel_excursion_product_fields');
    add_action('woocommerce_process_product_meta', 'life_travel_excursion_save_product_fields');
    add_action('woocommerce_single_product_summary', 'life_travel_excursion_display_booking_form', 25);
}
