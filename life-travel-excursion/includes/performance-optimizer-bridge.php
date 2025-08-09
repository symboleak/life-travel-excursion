<?php
/**
 * Pont d'optimisation de performances pour Life Travel Excursion
 * 
 * Ce fichier sert de pont entre l'ancien système d'optimisation des performances
 * et le nouveau système optimiseur d'assets, permettant une transition en douceur.
 * 
 * @package Life Travel Excursion
 * @version 2.4.0
 */

defined('ABSPATH') || exit;

/**
 * Charge le bon optimiseur de performances en fonction de la configuration
 * et de la disponibilité des fichiers.
 */
function life_travel_load_performance_optimizer() {
    // Vérifier si le nouvel optimiseur est disponible
    if (file_exists(__DIR__ . '/class-life-travel-assets-optimizer.php')) {
        // Vérifier si nous avons déjà migré ou si nous devons le faire
        $use_new_optimizer = get_option('life_travel_use_new_optimizer', null);
        
        // Si l'option n'existe pas encore, définir la valeur par défaut à true pour les nouvelles installations
        if ($use_new_optimizer === null) {
            $use_new_optimizer = true;
            update_option('life_travel_use_new_optimizer', true);
        }
        
        // Charger le système de cache spécifique au Cameroun pour les réseaux instables
        if (file_exists(__DIR__ . '/cameroon-network-cache.php')) {
            require_once __DIR__ . '/cameroon-network-cache.php';
        }
        
        // Charger l'optimiseur de ressources adapté aux conditions réseau camerounaises
        if (file_exists(__DIR__ . '/cameroon-resource-loader.php')) {
            require_once __DIR__ . '/cameroon-resource-loader.php';
        }
        
        if ($use_new_optimizer) {
            // Charger le nouvel optimiseur
            require_once __DIR__ . '/class-life-travel-assets-optimizer.php';
            
            // Si l'ancien optimiseur est chargé dynamiquement, créer un wrapper pour maintenir la compatibilité
            if (!class_exists('Life_Travel_Performance_Optimizer')) {
                class Life_Travel_Performance_Optimizer {
                    public function __construct() {
                        // Le constructeur redirige simplement vers le nouvel optimiseur
                        $optimizer = life_travel_assets_optimizer();
                        
                        // Ajouter un message de dépréciation dans les journaux si WP_DEBUG est activé
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('Life Travel: Utilisation de l\'ancien optimiseur de performances détectée. Veuillez passer au nouvel optimiseur d\'assets.');
                        }
                    }
                    
                    // Méthodes de compatibilité pour les appels directs à l'ancien optimiseur
                    public function optimize_assets_loading() {
                        // Rediriger vers le nouvel optimiseur
                        $optimizer = life_travel_assets_optimizer();
                        $optimizer->optimize_frontend_assets();
                    }
                    
                    public function add_async_defer_attributes($tag, $handle, $src) {
                        $optimizer = life_travel_assets_optimizer();
                        return $optimizer->add_defer_to_js($tag, $handle, $src);
                    }
                }
                
                // Initialiser pour maintenir la compatibilité avec l'ancien code
                new Life_Travel_Performance_Optimizer();
            }
            
            return true;
        }
    }
    
    // Fallback à l'ancien optimiseur
    if (file_exists(__DIR__ . '/performance-optimizer.php')) {
        require_once __DIR__ . '/performance-optimizer.php';
        return true;
    }
    
    return false;
}

/**
 * Fonction utilitaire pour vérifier si nous utilisons le nouvel optimiseur
 * 
 * @return bool True si nous utilisons le nouvel optimiseur
 */
function life_travel_is_using_new_optimizer() {
    return get_option('life_travel_use_new_optimizer', false);
}

/**
 * Permet de passer d'un optimiseur à l'autre
 * 
 * @param bool $use_new_optimizer True pour utiliser le nouvel optimiseur
 * @return bool Résultat de l'opération
 */
function life_travel_switch_optimizer($use_new_optimizer = true) {
    // Sauvegarder l'option
    $result = update_option('life_travel_use_new_optimizer', (bool) $use_new_optimizer);
    
    // Vider tous les caches pour éviter les problèmes
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Vider les caches d'optimisation
    delete_transient('life_travel_minified_css');
    delete_transient('life_travel_minified_js');
    
    return $result;
}

// Charger l'optimiseur approprié
life_travel_load_performance_optimizer();
