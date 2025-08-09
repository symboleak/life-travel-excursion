<?php
/**
 * Pont de validation de cohérence
 * 
 * Ce fichier assure la validation des interfaces entre les différents ponts
 * du système Life Travel pour éviter les incohérences et dépendances circulaires.
 * Il centralise également les fonctions communes pour éviter les problèmes spécifiques au contexte camerounais.
 * 
 * @package Life Travel Excursion
 * @version 2.5.0
 */

defined('ABSPATH') || exit;

// Structure de données pour suivre l'état des bridges
if (!isset($GLOBALS['life_travel_bridges'])) {
    $GLOBALS['life_travel_bridges'] = array(
        'pwa' => array(
            'loaded' => false,
            'version' => '',
            'functions' => array()
        ),
        'offline' => array(
            'loaded' => false,
            'version' => '',
            'functions' => array()
        ),
        'images' => array(
            'loaded' => false,
            'version' => '',
            'functions' => array()
        ),
        'kaios' => array(
            'loaded' => false,
            'version' => '',
            'functions' => array()
        )
    );
}

/**
 * Enregistre un bridge comme chargé et ses fonctions disponibles
 * 
 * Cette fonction doit être appelée au début de chaque fichier pont pour 
 * déclarer ses capacités et vérifier les dépendances
 * 
 * @param string $bridge_name Nom du bridge (‘pwa’, ‘offline’, ‘images’, etc.)
 * @param string $version Version du bridge
 * @param array $functions Liste des fonctions fournies par ce bridge
 * @return bool True si l'enregistrement a réussi
 */
if ( ! function_exists( 'life_travel_register_bridge' ) ) {

function life_travel_register_bridge($bridge_name, $version, $functions = array()) {
    if (!isset($GLOBALS['life_travel_bridges'][$bridge_name])) {
        $GLOBALS['life_travel_bridges'][$bridge_name] = array(
            'loaded' => false,
            'version' => '',
            'functions' => array()
        );
    }
    
    $GLOBALS['life_travel_bridges'][$bridge_name]['loaded'] = true;
    $GLOBALS['life_travel_bridges'][$bridge_name]['version'] = $version;
    
    if (!empty($functions)) {
        $GLOBALS['life_travel_bridges'][$bridge_name]['functions'] = array_merge(
            $GLOBALS['life_travel_bridges'][$bridge_name]['functions'],
            $functions
        );
    }
    
    return true;
}

} // fin du if (!function_exists())

/**
 * Vérifie si un bridge est chargé
 * 
 * @param string $bridge_name Nom du bridge à vérifier
 * @return bool True si le bridge est chargé
 */
if (!function_exists('life_travel_is_bridge_loaded')) {
    function life_travel_is_bridge_loaded($bridge_name) {
        return isset($GLOBALS['life_travel_bridges'][$bridge_name]) && 
               $GLOBALS['life_travel_bridges'][$bridge_name]['loaded'];
    }
}

/**
 * Centralise l'accès aux options pour éviter les dépendances circulaires
 * 
 * @param string $option_name Nom de l'option
 * @param mixed $default Valeur par défaut
 * @return mixed Valeur de l'option
 */
function life_travel_bridge_get_option($option_name, $default = false) {
    // Options mises en cache pour réduire les requêtes vers la base de données
    static $options_cache = array();
    
    if (isset($options_cache[$option_name])) {
        return $options_cache[$option_name];
    }
    
    $value = get_option($option_name, $default);
    $options_cache[$option_name] = $value;
    
    return $value;
}

/**
 * Fonction de compatibilité qui vérifie si les fonctionnalités PWA sont activées
 * Cette fonction est référencée dans plusieurs bridges
 * 
 * @return bool True si le mode PWA est activé
 */
if ( ! function_exists( 'life_travel_is_pwa_enabled' ) ) {
function life_travel_is_pwa_enabled() {
    return life_travel_bridge_get_option('life_travel_pwa_enabled', true);
}
} // fin if exists pwa

/**
 * Fonction de compatibilité pour vérifier si le cache offline est activé
 * 
 * @return bool True si le cache offline est activé
 */
if ( ! function_exists( 'life_travel_is_offline_cache_enabled' ) ) {
function life_travel_is_offline_cache_enabled() {
    return life_travel_is_pwa_enabled() && 
           life_travel_bridge_get_option('life_travel_offline_cache_enabled', true);
}
} // fin if exists offline

/**
 * Valide la cohérence des interfaces entre les différents ponts
 * 
 * Vérifie les signatures de fonctions et structures pour détecter les incohérences
 * potentielles et alerter les administrateurs. Utiliser sur admin_init.
 * 
 * @return array Rapport de validation avec succès/échecs
 */
function life_travel_validate_bridge_interfaces() {
    $results = array(
        'success' => true,
        'messages' => array(),
        'errors' => array(),
        'warnings' => array()
    );
    
    // Ponts à vérifier
    $bridges = array(
        'pwa' => array(
            'file' => plugin_dir_path(__FILE__) . 'pwa-bridge.php',
            'required_functions' => array(
                'life_travel_print_connection_detection_script',
                'life_travel_get_connection_detection_script',
                'life_travel_register_offline_resources'
            ),
            'optional_functions' => array(
                'life_travel_register_service_worker'
            )
        ),
        'offline' => array(
            'file' => plugin_dir_path(__FILE__) . 'offline-bridge.php',
            'required_functions' => array(
                'life_travel_generate_offline_page',
                'life_travel_schedule_offline_sync'
            ),
            'optional_functions' => array(
                'life_travel_process_offline_sync_queue'
            )
        ),
        'images' => array(
            'file' => plugin_dir_path(__FILE__) . 'images-bridge.php',
            'required_functions' => array(
                'life_travel_get_svg_path',
                'life_travel_filter_image_attributes'
            ),
            'optional_functions' => array(
                'life_travel_generate_svg_sprite'
            )
        )
    );
    
    // Options critiques à vérifier
    $critical_options = array(
        'life_travel_use_new_offline_system',
        'life_travel_use_svg_icons',
        'life_travel_cache_name',
        'life_travel_pwa_enabled'
    );
    
    // Vérifier la présence et la cohérence des ponts
    foreach ($bridges as $bridge_name => $bridge_data) {
        if (!file_exists($bridge_data['file'])) {
            $results['success'] = false;
            $results['errors'][] = sprintf(__('Pont %s manquant : %s', 'life-travel'), $bridge_name, $bridge_data['file']);
            continue;
        }
        
        // Vérifier les fonctions requises
        foreach ($bridge_data['required_functions'] as $function) {
            if (!function_exists($function)) {
                $results['success'] = false;
                $results['errors'][] = sprintf(__('Fonction requise manquante : %s dans %s', 'life-travel'), $function, $bridge_name);
            }
        }
        
        // Vérifier les fonctions optionnelles (avertissement seulement)
        foreach ($bridge_data['optional_functions'] as $function) {
            if (!function_exists($function)) {
                $results['warnings'][] = sprintf(__('Fonction optionnelle manquante : %s dans %s', 'life-travel'), $function, $bridge_name);
            }
        }
    }
    
    // Vérifier la cohérence des options critiques
    foreach ($critical_options as $option) {
        if (get_option($option) === false) {
            $results['warnings'][] = sprintf(__('Option critique non configurée : %s', 'life-travel'), $option);
        }
    }
    
    // Vérifier spécifiquement la cohérence du nom de cache
    $cache_name_pwa = defined('LIFE_TRAVEL_CACHE_NAME') ? LIFE_TRAVEL_CACHE_NAME : 'life-travel-cache-v2';
    $cache_name_option = life_travel_bridge_get_option('life_travel_cache_name', 'life-travel-cache-v2');
    
    if ($cache_name_pwa !== $cache_name_option) {
        $results['errors'][] = sprintf(
            __('Incohérence du nom de cache : %s (constante) vs %s (option)', 'life-travel'),
            $cache_name_pwa,
            $cache_name_option
        );
        $results['success'] = false;
    }
    
    // Vérifier les dépendances circulaires potentielles
    $circular_dependencies = life_travel_check_circular_dependencies();
    
    if (!empty($circular_dependencies)) {
        $results['warnings'][] = __('Dépendances circulaires potentielles détectées dans les ponts:', 'life-travel');
        
        foreach ($circular_dependencies as $dependency) {
            $results['warnings'][] = '- ' . $dependency;
        }
    }
    
    // Vérifier la cohérence des chemins SVG
    $svg_path_images = function_exists('life_travel_get_svg_path') ? life_travel_get_svg_path() : '';
    $svg_path_config = get_option('life_travel_svg_path', '');
    
    if ($svg_path_images && $svg_path_config && $svg_path_images !== $svg_path_config) {
        $results['warnings'][] = sprintf(
            __('Chemins SVG potentiellement incohérents: %s vs %s', 'life-travel'),
            $svg_path_images,
            $svg_path_config
        );
    }
    
    // Notifier l'administrateur en cas d'erreurs
    if (!$results['success'] && is_admin()) {
        add_action('admin_notices', 'life_travel_display_bridge_validation_errors');
    }
    
    return $results;
}

/**
 * Affiche les erreurs de validation des ponts dans l'interface admin
 */
function life_travel_display_bridge_validation_errors() {
    $results = life_travel_validate_bridge_interfaces();
    
    if (!$results['success']) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>' . __('Erreurs de cohérence détectées dans Life Travel', 'life-travel') . '</strong></p>';
        echo '<ul>';
        
        foreach ($results['errors'] as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }
    
    if (!empty($results['warnings'])) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . __('Avertissements de cohérence dans Life Travel', 'life-travel') . '</strong></p>';
        echo '<ul>';
        
        foreach ($results['warnings'] as $warning) {
            echo '<li>' . esc_html($warning) . '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }
}

/**
 * Enregistre le hook pour vérifier la cohérence des ponts
 */
function life_travel_register_bridge_validation() {
    // Vérifier uniquement en environnement admin
    if (is_admin()) {
        add_action('admin_init', 'life_travel_validate_bridge_interfaces');
    }
}

/**
 * Détecte les dépendances circulaires entre les bridges
 * 
 * Cette fonction analyse les dépendances entre les bridges pour trouver les circuits
 * 
 * @return array Liste des dépendances circulaires détectées
 */
function life_travel_check_circular_dependencies() {
    $dependencies = array();
    $bridges = $GLOBALS['life_travel_bridges'];
    $circular_dependencies = array();
    
    // Lister les dépendances connues
    if (function_exists('life_travel_init_images_bridge')) {
        // Images dépend de PWA pour le cache offline
        $dependencies['images']['pwa'] = true;
    }
    
    if (function_exists('life_travel_load_offline_system')) {
        // Offline dépend de PWA pour la synchronisation
        $dependencies['offline']['pwa'] = true;
    }
    
    if (function_exists('life_travel_register_service_worker')) {
        // PWA dépend de Offline pour la page offline
        $dependencies['pwa']['offline'] = true;
    }
    
    // Vérifier les dépendances circulaires A->B->A
    foreach ($dependencies as $bridge => $deps) {
        foreach ($deps as $dependency => $value) {
            if (isset($dependencies[$dependency][$bridge]) && $dependencies[$dependency][$bridge]) {
                $circular_dependencies[] = array($bridge, $dependency, $bridge);
            }
        }
    }
    
    return $circular_dependencies;
}

/**
 * Fonction publique pour récupérer les dépendances circulaires pour le diagnostic
 * 
 * @return array Liste des dépendances circulaires détectées
 */
if (!function_exists('life_travel_get_circular_dependencies')) {
function life_travel_get_circular_dependencies() {
    return life_travel_check_circular_dependencies();
}
} // fin if exists circular deps

/**
 * Fonction publique pour récupérer le registre des bridges pour le diagnostic
 * 
 * @return array Registre des bridges
 */
if (!function_exists('life_travel_get_bridge_registry')) {
function life_travel_get_bridge_registry() {
    global $life_travel_bridges;
    return $life_travel_bridges;
}
} // fin if exists bridge registry

/**
 * Définit une fonction qui sera utilisée par plusieurs ponts
 * pour éviter les dépendances circulaires
 * 
 * @param string $function_name Nom de la fonction à définir
 * @param callable $callback Fonction de rappel
 * @return bool True si la définition a réussi
 */
function life_travel_define_shared_function($function_name, $callback) {
    // Si la fonction existe déjà, ne pas la remplacer
    if (function_exists($function_name)) {
        return false;
    }
    
    // Définir la fonction dynamiquement
    if (is_callable($callback)) {
        // Créer une chaîne évaluable pour définir la fonction
        $function_definition = 'function ' . $function_name . '() { 
';
        $function_definition .= '    $args = func_get_args(); 
';
        $function_definition .= '    return call_user_func_array($GLOBALS["life_travel_shared_functions"]["' . $function_name . '"], $args); 
';
        $function_definition .= '} 
';
        
        // S'assurer que la structure globale pour les fonctions partagées existe
        if (!isset($GLOBALS['life_travel_shared_functions'])) {
            $GLOBALS['life_travel_shared_functions'] = array();
        }
        
        // Enregistrer la fonction de rappel
        $GLOBALS['life_travel_shared_functions'][$function_name] = $callback;
        
        // Évaluer la définition
        eval($function_definition);
        
        return true;
    }
    
    return false;
}

/**
 * Fonction qui charge les ponts dans le bon ordre pour éviter les dépendances circulaires
 */
function life_travel_load_bridges_in_order() {
    // Définir l'ordre de chargement pour éviter les conflits
    $load_order = array(
        'bridge-validator.php',    // Toujours en premier
        'images-bridge.php',       // Indépendant des autres
        'offline-bridge.php',      // Dépend de certaines fonctions PWA mais peut être chargé avant
        'pwa-bridge.php'          // Dépend des autres ponts, doit être chargé en dernier
    );
    
    foreach ($load_order as $bridge_file) {
        $bridge_path = plugin_dir_path(__FILE__) . $bridge_file;
        
        if (file_exists($bridge_path) && !in_array($bridge_path, get_included_files())) {
            require_once $bridge_path;
        }
    }
}

// Activer la validation de cohérence et le chargement ordonné des ponts
add_action('plugins_loaded', 'life_travel_register_bridge_validation', 5);
add_action('plugins_loaded', 'life_travel_load_bridges_in_order', 10);
