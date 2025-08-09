<?php
/**
 * Pont d'unification des scripts JavaScript
 * 
 * Ce fichier assure la transition entre les anciens scripts JS
 * et nos nouvelles versions optimisées et unifiées.
 * 
 * @package Life Travel Excursion
 * @version 2.4.0
 */

defined('ABSPATH') || exit;

/**
 * Enregistre et charge les scripts JS appropriés (anciens ou nouveaux)
 * 
 * @param string $context 'admin' ou 'frontend' selon le contexte d'exécution
 * @return void
 */
function life_travel_load_optimized_scripts($context = 'frontend') {
    $use_optimized_scripts = get_option('life_travel_use_optimized_scripts', true);
    
    // Scripts d'administration
    if ($context === 'admin' && is_admin()) {
        if ($use_optimized_scripts && file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'js/admin-unified.js')) {
            // Charger la version optimisée
            wp_deregister_script('life-travel-admin-excursion');
            wp_deregister_script('life-travel-admin-script');
            
            wp_enqueue_script(
                'life-travel-admin-unified', 
                LIFE_TRAVEL_EXCURSION_URL . 'js/admin-unified.js',
                array('jquery', 'jquery-ui-datepicker'),
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
        } else {
            // Charger les anciens scripts séparément
            wp_enqueue_script(
                'life-travel-admin-excursion', 
                LIFE_TRAVEL_EXCURSION_URL . 'js/admin-excursion.js',
                array('jquery'),
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
            
            wp_enqueue_script(
                'life-travel-admin-script', 
                LIFE_TRAVEL_EXCURSION_URL . 'js/admin-script.js',
                array('jquery'),
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
        }
    }
    // Scripts frontend
    else if ($context === 'frontend' && !is_admin()) {
        if ($use_optimized_scripts && file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'js/script-optimized.js')) {
            // Charger la version optimisée
            wp_deregister_script('life-travel-script');
            
            wp_enqueue_script(
                'life-travel-script-optimized', 
                LIFE_TRAVEL_EXCURSION_URL . 'js/script-optimized.js',
                array('jquery', 'jquery-ui-datepicker'),
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
            
            // Passer les variables nécessaires au script
            wp_localize_script('life-travel-script-optimized', 'life_travel_excursion_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('life_travel_excursion_nonce'),
                'is_offline_supported' => (int) get_option('life_travel_offline_support', 1)
            ));
        } else {
            // Charger l'ancien script
            wp_enqueue_script(
                'life-travel-script', 
                LIFE_TRAVEL_EXCURSION_URL . 'js/script.js',
                array('jquery', 'jquery-ui-datepicker'),
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
            
            // Passer les variables nécessaires au script
            wp_localize_script('life-travel-script', 'life_travel_excursion_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('life_travel_excursion_nonce'),
                'is_offline_supported' => (int) get_option('life_travel_offline_support', 1)
            ));
        }
    }
}

/**
 * Bascule entre les scripts optimisés et les scripts originaux
 * 
 * @param bool $use_optimized True pour utiliser les scripts optimisés
 * @return bool Résultat de l'opération
 */
function life_travel_switch_to_optimized_scripts($use_optimized = true) {
    return update_option('life_travel_use_optimized_scripts', (bool) $use_optimized);
}

/**
 * Vérifie si nous utilisons les scripts optimisés
 * 
 * @return bool True si nous utilisons les scripts optimisés
 */
function life_travel_is_using_optimized_scripts() {
    return get_option('life_travel_use_optimized_scripts', true);
}
