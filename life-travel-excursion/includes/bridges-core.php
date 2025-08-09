<?php
/**
 * Noyau central du système de bridges pour Life Travel Excursion
 *
 * Ce fichier implémente le coeur du système de bridges qui permet d'éliminer
 * les dépendances circulaires en centralisant les fonctions communes et en
 * orchestrant le chargement des bridges dans un ordre optimal.
 *
 * Avantages:
 * - Élimine les dépendances circulaires
 * - Centralise les fonctions communes
 * - Fournit un point d'entrée unique pour l'initialisation
 * - Facilite la maintenance et l'évolution
 *
 * @package Life Travel Excursion
 * @version 3.0.0
 */

defined('ABSPATH') || exit;

/**
 * Classe principale pour le coeur du système de bridges
 */
class Life_Travel_Bridges_Core {
    /**
     * Instance unique (patron Singleton)
     * @var Life_Travel_Bridges_Core
     */
    private static $instance = null;
    
    /**
     * Registre des bridges chargés
     * @var array
     */
    private $bridges = [];
    
    /**
     * Registre des fonctions partagées
     * @var array
     */
    private $shared_functions = [];
    
    /**
     * Ordre de chargement des bridges
     * @var array
     */
    private $load_order = [
        'bridge-validator.php',   // Validateur toujours en premier
        'images-bridge.php',      // Bridge d'images (peu de dépendances)
        'offline-bridge.php',     // Bridge offline
        'pwa-bridge.php',         // Bridge PWA (dépendances nombreuses)
    ];
    
    /**
     * Dépendances connues entre les bridges
     * @var array
     */
    private $dependencies = [
        'pwa' => ['images', 'offline'],
        'offline' => ['images'],
        'images' => [],
    ];
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        // Initialiser le registre des bridges
        $this->initialize_registry();
        
        // Enregistrer les fonctions partagées
        $this->register_shared_functions();
        
        // Configurer les hooks d'initialisation
        add_action('plugins_loaded', [$this, 'load_bridges'], 5);
        add_action('admin_init', [$this, 'validate_bridges'], 10);
    }
    
    /**
     * Obtient l'instance unique
     * 
     * @return Life_Travel_Bridges_Core Instance unique
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Initialise le registre des bridges
     */
    private function initialize_registry() {
        $this->bridges = [
            'pwa' => [
                'loaded' => false,
                'version' => '',
                'functions' => [],
                'path' => '',
            ],
            'offline' => [
                'loaded' => false,
                'version' => '',
                'functions' => [],
                'path' => '',
            ],
            'images' => [
                'loaded' => false,
                'version' => '',
                'functions' => [],
                'path' => '',
            ],
        ];
        
        // Exposer le registre dans une variable globale pour compatibilité
        $GLOBALS['life_travel_bridges'] = &$this->bridges;
    }
    
    /**
     * Enregistre les fonctions partagées qui sont utilisées par plusieurs bridges
     * pour résoudre les dépendances circulaires
     */
    private function register_shared_functions() {
        // Fonction partagée: Vérification si PWA est activé
        $this->define_shared_function('life_travel_is_pwa_enabled', function() {
            return get_option('life_travel_pwa_enabled', true);
        });
        
        // Fonction partagée: Vérification si le cache offline est activé
        $this->define_shared_function('life_travel_is_offline_cache_enabled', function() {
            return $this->call_shared_function('life_travel_is_pwa_enabled') && 
                   get_option('life_travel_offline_cache_enabled', true);
        });
        
        // Fonction partagée: Préparation du cache des ressources offline
        $this->define_shared_function('life_travel_prepare_offline_resources', function($resources = []) {
            static $prepared_resources = null;
            
            // Si nous appelons la fonction pour définir les ressources
            if (!empty($resources)) {
                $prepared_resources = $resources;
                return true;
            }
            
            // Si nous appelons la fonction pour obtenir les ressources
            return $prepared_resources ?: [];
        });
        
        // Fonction partagée: Détection de la connexion réseau
        $this->define_shared_function('life_travel_detect_network_status', function() {
            // Vérifier si nous avons l'information dans un cookie
            $status = isset($_COOKIE['life_travel_connection_state']) ? 
                      sanitize_text_field($_COOKIE['life_travel_connection_state']) : 'unknown';
            
            // Vérifier les en-têtes Save-Data (Chrome/Opera)
            if (isset($_SERVER['HTTP_SAVE_DATA']) && strtolower($_SERVER['HTTP_SAVE_DATA']) === 'on') {
                $status = 'slow';
            }
            
            return $status;
        });
    }
    
    /**
     * Définit une fonction partagée
     * 
     * @param string $function_name Nom de la fonction
     * @param callable $callback Fonction de rappel
     * @return bool Succès de l'opération
     */
    public function define_shared_function($function_name, $callback) {
        // Si la fonction existe déjà, ne pas la redéfinir, mais
        // enregistrer quand même le callback pour call_shared_function().
        if (function_exists($function_name)) {
            $this->shared_functions[$function_name] = $callback;
            return true;
        }
        
        // Enregistrer la fonction dans le registre interne
        $this->shared_functions[$function_name] = $callback;
        
        // Définir la fonction globalement
        $function_definition = 'function ' . $function_name . '() {
            $core = Life_Travel_Bridges_Core::get_instance();
            return $core->call_shared_function("' . $function_name . '", func_get_args());
        }';
        
        eval($function_definition);
        return true;
    }
    
    /**
     * Appelle une fonction partagée
     * 
     * @param string $function_name Nom de la fonction
     * @param array $args Arguments à passer à la fonction
     * @return mixed Résultat de la fonction
     */
    public function call_shared_function($function_name, $args = []) {
        if (isset($this->shared_functions[$function_name]) && is_callable($this->shared_functions[$function_name])) {
            return call_user_func_array($this->shared_functions[$function_name], is_array($args) ? $args : [$args]);
        }
        
        return null;
    }
    
    /**
     * Charge les bridges dans l'ordre optimal
     */
    public function load_bridges() {
        // Vérifier si le chargement est forcé dans un ordre précis
        $force_order = get_option('life_travel_force_bridge_order', true);
        
        if ($force_order) {
            // Charger dans l'ordre prédéfini
            foreach ($this->load_order as $bridge_file) {
                $this->load_bridge($bridge_file);
            }
        } else {
            // Charger en respectant les dépendances
            $this->load_bridges_with_dependencies();
        }
    }
    
    /**
     * Charge un bridge spécifique
     * 
     * @param string $bridge_file Nom du fichier bridge
     * @return bool Succès du chargement
     */
    private function load_bridge($bridge_file) {
        $bridge_path = plugin_dir_path(__FILE__) . $bridge_file;
        
        // Vérifier si le fichier existe et n'est pas déjà inclus
        if (file_exists($bridge_path) && !in_array($bridge_path, get_included_files())) {
            // Extraire le nom du bridge à partir du nom de fichier
            $bridge_name = str_replace(['-bridge.php', '.php'], '', $bridge_file);
            
            // Enregistrer le chemin du bridge
            if (isset($this->bridges[$bridge_name])) {
                $this->bridges[$bridge_name]['path'] = $bridge_path;
            }
            
            // Charger le bridge
            require_once $bridge_path;
            return true;
        }
        
        return false;
    }
    
    /**
     * Charge les bridges en respectant leurs dépendances
     */
    private function load_bridges_with_dependencies() {
        // Calculer l'ordre de chargement basé sur les dépendances
        $load_order = $this->calculate_dependency_order();
        
        // Charger dans l'ordre calculé
        foreach ($load_order as $bridge_name) {
            $bridge_file = $bridge_name . '-bridge.php';
            
            // Si le bridge est bridge-validator, utiliser le nom de fichier direct
            if ($bridge_name === 'bridge-validator') {
                $bridge_file = $bridge_name . '.php';
            }
            
            $this->load_bridge($bridge_file);
        }
    }
    
    /**
     * Calcule l'ordre de chargement optimal basé sur les dépendances
     * en utilisant un algorithme de tri topologique
     * 
     * @return array Ordre de chargement des bridges
     */
    private function calculate_dependency_order() {
        $visited = [];
        $temp_mark = [];
        $order = [];
        
        // Fonction récursive pour le tri topologique
        $visit = function($node) use (&$visited, &$temp_mark, &$order, &$visit) {
            // Si déjà visité définitivement, passer
            if (isset($visited[$node]) && $visited[$node]) {
                return;
            }
            
            // Si déjà visité temporairement, c'est une dépendance circulaire
            if (isset($temp_mark[$node]) && $temp_mark[$node]) {
                // Dans ce cas, on continue quand même pour avoir un ordre utilisable
                return;
            }
            
            // Marquer comme visité temporairement
            $temp_mark[$node] = true;
            
            // Visiter toutes les dépendances
            if (isset($this->dependencies[$node])) {
                foreach ($this->dependencies[$node] as $dependency) {
                    $visit($dependency);
                }
            }
            
            // Marquer comme visité définitivement
            $temp_mark[$node] = false;
            $visited[$node] = true;
            
            // Ajouter à l'ordre de chargement
            $order[] = $node;
        };
        
        // Visiter tous les bridges
        foreach (array_keys($this->dependencies) as $bridge) {
            $visit($bridge);
        }
        
        // Ajouter bridge-validator au début
        array_unshift($order, 'bridge-validator');
        
        return $order;
    }
    
    /**
     * Enregistre un bridge comme chargé
     * 
     * @param string $bridge_name Nom du bridge
     * @param string $version Version du bridge
     * @param array $functions Fonctions fournies par le bridge
     * @return bool Succès de l'enregistrement
     */
    public function register_bridge($bridge_name, $version, $functions = []) {
        if (!isset($this->bridges[$bridge_name])) {
            $this->bridges[$bridge_name] = [
                'loaded' => false,
                'version' => '',
                'functions' => [],
                'path' => '',
            ];
        }
        
        $this->bridges[$bridge_name]['loaded'] = true;
        $this->bridges[$bridge_name]['version'] = $version;
        
        if (!empty($functions)) {
            $this->bridges[$bridge_name]['functions'] = array_merge(
                $this->bridges[$bridge_name]['functions'],
                $functions
            );
        }
        
        return true;
    }
    
    /**
     * Vérifie si un bridge est chargé
     * 
     * @param string $bridge_name Nom du bridge
     * @return bool True si le bridge est chargé
     */
    public function is_bridge_loaded($bridge_name) {
        return isset($this->bridges[$bridge_name]) && $this->bridges[$bridge_name]['loaded'];
    }
    
    /**
     * Valide la cohérence des bridges
     */
    public function validate_bridges() {
        // Ne valider qu'en admin et si nécessaire
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        
        $validation_results = $this->check_bridge_interfaces();
        
        if (!empty($validation_results['errors'])) {
            add_action('admin_notices', function() use ($validation_results) {
                echo '<div class="error"><p>';
                echo '<strong>' . esc_html__('Life Travel Excursion: Problèmes de cohérence des bridges détectés.', 'life-travel-excursion') . '</strong>';
                echo '<ul>';
                
                foreach ($validation_results['errors'] as $error) {
                    echo '<li>' . esc_html($error) . '</li>';
                }
                
                echo '</ul>';
                echo '</p></div>';
            });
        }
        
        // Vérifier les dépendances circulaires
        $circular_deps = $this->check_circular_dependencies();
        
        if (!empty($circular_deps)) {
            add_action('admin_notices', function() use ($circular_deps) {
                echo '<div class="error"><p>';
                echo '<strong>' . esc_html__('Life Travel Excursion: Dépendances circulaires détectées.', 'life-travel-excursion') . '</strong>';
                echo '<ul>';
                
                foreach ($circular_deps as $dep) {
                    echo '<li>' . esc_html(implode(' → ', $dep)) . '</li>';
                }
                
                echo '</ul>';
                echo '</p></div>';
            });
        }
    }
    
    /**
     * Vérifie la cohérence des interfaces entre les bridges
     * 
     * @return array Résultats de validation
     */
    private function check_bridge_interfaces() {
        $results = [
            'success' => true,
            'errors' => [],
        ];
        
        // Vérifier que tous les bridges requis sont chargés
        $required_bridges = ['pwa', 'offline', 'images'];
        
        foreach ($required_bridges as $bridge) {
            if (!$this->is_bridge_loaded($bridge)) {
                $results['success'] = false;
                $results['errors'][] = sprintf(
                    __('Le bridge %s n\'est pas chargé.', 'life-travel-excursion'),
                    $bridge
                );
            }
        }
        
        // Vérifier la cohérence des versions (si elles proviennent de la même version du plugin)
        $versions = [];
        
        foreach ($this->bridges as $bridge => $data) {
            if ($data['loaded']) {
                $versions[$bridge] = $data['version'];
            }
        }
        
        if (count(array_unique($versions)) > 1) {
            $results['success'] = false;
            $results['errors'][] = __('Les bridges ont des versions différentes, ce qui peut causer des problèmes de compatibilité.', 'life-travel-excursion');
        }
        
        // Vérifier que les fonctions déclarées existent
        foreach ($this->bridges as $bridge => $data) {
            if (!$data['loaded']) {
                continue;
            }
            
            foreach ($data['functions'] as $function) {
                if (!function_exists($function)) {
                    $results['success'] = false;
                    $results['errors'][] = sprintf(
                        __('La fonction %s déclarée par le bridge %s n\'existe pas.', 'life-travel-excursion'),
                        $function,
                        $bridge
                    );
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Vérifie les dépendances circulaires
     * 
     * @return array Dépendances circulaires
     */
    private function check_circular_dependencies() {
        $circular_deps = [];
        
        // Fonction récursive pour détecter les circuits
        $detect_cycles = function($node, $path = []) use (&$detect_cycles, &$circular_deps) {
            // Si le nœud est déjà dans le chemin, c'est une dépendance circulaire
            if (in_array($node, $path)) {
                $cycle_path = array_slice($path, array_search($node, $path));
                $cycle_path[] = $node;
                $circular_deps[] = $cycle_path;
                return;
            }
            
            // Ajouter le nœud au chemin
            $path[] = $node;
            
            // Explorer toutes les dépendances
            if (isset($this->dependencies[$node])) {
                foreach ($this->dependencies[$node] as $dependency) {
                    $detect_cycles($dependency, $path);
                }
            }
        };
        
        // Détecter à partir de chaque nœud
        foreach (array_keys($this->dependencies) as $node) {
            $detect_cycles($node);
        }
        
        return $circular_deps;
    }
    
    /**
     * Obtient le registre des bridges
     * 
     * @return array Registre des bridges
     */
    public function get_bridge_registry() {
        return $this->bridges;
    }
    
    /**
     * Obtient les dépendances circulaires
     * 
     * @return array Dépendances circulaires
     */
    public function get_circular_dependencies() {
        return $this->check_circular_dependencies();
    }
}

/**
 * Fonction d'accès au noyau des bridges
 * 
 * @return Life_Travel_Bridges_Core Instance du noyau
 */
if (!function_exists('life_travel_bridges_core')) {
function life_travel_bridges_core() {
    return Life_Travel_Bridges_Core::get_instance();
}
}

/**
 * Fonction de compatibilité: Enregistre un bridge
 * 
 * @param string $bridge_name Nom du bridge
 * @param string $version Version du bridge
 * @param array $functions Fonctions fournies par le bridge
 * @return bool Succès de l'enregistrement
 */
if (!function_exists('life_travel_register_bridge')) {
function life_travel_register_bridge($bridge_name, $version, $functions = []) {
    return life_travel_bridges_core()->register_bridge($bridge_name, $version, $functions);
}
}

/**
 * Fonction de compatibilité: Vérifie si un bridge est chargé
 * 
 * @param string $bridge_name Nom du bridge
 * @return bool True si le bridge est chargé
 */
if (!function_exists('life_travel_is_bridge_loaded')) {
function life_travel_is_bridge_loaded($bridge_name) {
    return life_travel_bridges_core()->is_bridge_loaded($bridge_name);
}
}

/**
 * Fonction de compatibilité: Obtient le registre des bridges
 * 
 * @return array Registre des bridges
 */
if (!function_exists('life_travel_get_bridge_registry')) {
function life_travel_get_bridge_registry() {
    return life_travel_bridges_core()->get_bridge_registry();
}
}

/**
 * Fonction de compatibilité: Obtient les dépendances circulaires
 * 
 * @return array Dépendances circulaires
 */
if (!function_exists('life_travel_get_circular_dependencies')) {
function life_travel_get_circular_dependencies() {
    return life_travel_bridges_core()->get_circular_dependencies();
}
}

// Initialiser le noyau des bridges
life_travel_bridges_core();
