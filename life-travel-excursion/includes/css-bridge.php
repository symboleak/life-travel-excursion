<?php
/**
 * Pont d'unification des styles CSS
 * 
 * Ce fichier assure la transition entre les anciens fichiers CSS
 * et nos nouvelles versions optimisées et unifiées.
 * 
 * @package Life Travel Excursion
 * @version 2.4.0
 */

defined('ABSPATH') || exit;

/**
 * Enregistre et charge les styles CSS appropriés (anciens ou nouveaux)
 * 
 * @param string $context 'admin' ou 'frontend' selon le contexte d'exécution
 * @return void
 */
function life_travel_load_optimized_styles($context = 'frontend') {
    $use_optimized_styles = get_option('life_travel_use_optimized_styles', true);
    
    // Styles d'administration
    if ($context === 'admin' && is_admin()) {
        if ($use_optimized_styles && file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'assets/css/admin-unified.css')) {
            // Charger la version optimisée
            wp_deregister_style('life-travel-admin-style');
            wp_deregister_style('life-travel-admin-media');
            wp_deregister_style('life-travel-admin-abandoned-cart');
            
            wp_enqueue_style(
                'life-travel-admin-unified', 
                LIFE_TRAVEL_EXCURSION_URL . 'assets/css/admin-unified.css',
                array(),
                LIFE_TRAVEL_EXCURSION_VERSION
            );
        } else {
            // Charger les anciens styles séparément
            wp_enqueue_style(
                'life-travel-admin-style', 
                LIFE_TRAVEL_EXCURSION_URL . 'assets/css/admin.css',
                array(),
                LIFE_TRAVEL_EXCURSION_VERSION
            );
            
            // Charger les styles additionnels selon la page
            $screen = get_current_screen();
            if ($screen) {
                if ($screen->id === 'toplevel_page_life-travel-dashboard') {
                    wp_enqueue_style(
                        'life-travel-admin-media', 
                        LIFE_TRAVEL_EXCURSION_URL . 'assets/css/admin-media.css',
                        array('life-travel-admin-style'),
                        LIFE_TRAVEL_EXCURSION_VERSION
                    );
                }
                
                if ($screen->id === 'life-travel_page_life-travel-cart-recovery') {
                    wp_enqueue_style(
                        'life-travel-admin-abandoned-cart', 
                        LIFE_TRAVEL_EXCURSION_URL . 'assets/css/admin-abandoned-cart.css',
                        array('life-travel-admin-style'),
                        LIFE_TRAVEL_EXCURSION_VERSION
                    );
                }
            }
        }
    }
    // Styles frontend
    else if ($context === 'frontend' && !is_admin()) {
        if ($use_optimized_styles && file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'assets/css/frontend-unified.css')) {
            // Charger la version optimisée
            wp_deregister_style('life-travel-excursion-style');
            wp_deregister_style('life-travel-card');
            wp_deregister_style('life-travel-price-display');
            wp_deregister_style('life-travel-interactive-map');
            
            wp_enqueue_style(
                'life-travel-frontend-unified', 
                LIFE_TRAVEL_EXCURSION_URL . 'assets/css/frontend-unified.css',
                array(),
                LIFE_TRAVEL_EXCURSION_VERSION
            );
            
            // jQuery UI est toujours nécessaire
            wp_enqueue_style(
                'jquery-ui-css', 
                LIFE_TRAVEL_EXCURSION_URL . 'css/jquery-ui.min.css',
                array(),
                LIFE_TRAVEL_EXCURSION_VERSION
            );
            
            // Ajouter la classe thème au body
            add_filter('body_class', function($classes) {
                $classes[] = 'life-travel-theme';
                
                // Ajouter des classes contextuelles pour l'optimisation
                if (get_option('life_travel_optimize_for_slow_connection', false)) {
                    $classes[] = 'slow-connection';
                }
                
                if (get_option('life_travel_optimize_for_cameroon', true)) {
                    $classes[] = 'optimized-for-cameroon';
                }
                
                return $classes;
            });
        } else {
            // Charger les anciens styles séparément
            wp_enqueue_style(
                'life-travel-excursion-style', 
                LIFE_TRAVEL_EXCURSION_URL . 'css/style.css',
                array(),
                LIFE_TRAVEL_EXCURSION_VERSION
            );
            
            // Styles conditionnels selon le contexte
            if (is_product()) {
                wp_enqueue_style(
                    'life-travel-price-display', 
                    LIFE_TRAVEL_EXCURSION_URL . 'assets/css/price-display.css',
                    array('life-travel-excursion-style'),
                    LIFE_TRAVEL_EXCURSION_VERSION
                );
            }
            
            if (is_single() || is_archive()) {
                wp_enqueue_style(
                    'life-travel-card', 
                    LIFE_TRAVEL_EXCURSION_URL . 'assets/css/life-travel-card.css',
                    array('life-travel-excursion-style'),
                    LIFE_TRAVEL_EXCURSION_VERSION
                );
            }
            
            if (is_page('excursions-map') || has_shortcode(get_post()->post_content, 'life_travel_map')) {
                wp_enqueue_style(
                    'life-travel-interactive-map', 
                    LIFE_TRAVEL_EXCURSION_URL . 'assets/css/interactive-map.css',
                    array('life-travel-excursion-style'),
                    LIFE_TRAVEL_EXCURSION_VERSION
                );
            }
        }
    }
}

/**
 * Bascule entre les styles optimisés et les styles originaux
 * 
 * @param bool $use_optimized True pour utiliser les styles optimisés
 * @return bool Résultat de l'opération
 */
function life_travel_switch_to_optimized_styles($use_optimized = true) {
    return update_option('life_travel_use_optimized_styles', (bool) $use_optimized);
}

/**
 * Vérifie si nous utilisons les styles optimisés
 * 
 * @return bool True si nous utilisons les styles optimisés
 */
function life_travel_is_using_optimized_styles() {
    return get_option('life_travel_use_optimized_styles', true);
}
