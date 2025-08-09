<?php
/**
 * Life Travel - Initialisateur de bridges
 *
 * Ce fichier gère l'initialisation ordonnée des différents ponts (bridges)
 * du plugin Life Travel pour éviter les dépendances circulaires et assurer
 * une exécution cohérente.
 *
 * Version optimisée qui utilise le noyau central des bridges pour éliminer
 * complètement les dépendances circulaires et améliorer la robustesse.
 *
 * @package Life Travel Excursion
 * @version 3.0.0
 */

defined('ABSPATH') || exit;

// Charger le noyau central des bridges s'il n'est pas déjà chargé
if (!class_exists('Life_Travel_Bridges_Core')) {
    $core_path = plugin_dir_path(__FILE__) . 'bridges-core.php';
    if (file_exists($core_path)) {
        require_once $core_path;
    }
}

/**
 * Classe d'initialisation des bridges avec compatibilité ascendante
 * pour préserver le fonctionnement du code existant
 */
class Life_Travel_Bridge_Initializer {
    /**
     * Ordre d'initialisation des bridges
     * @var array
     */
    private $bridge_order = array(
        'bridge-validator.php',   // Toujours en premier
        'images-bridge.php',      // Indépendant des autres
        'offline-bridge.php',     // Dépend partiellement de PWA mais sans circularité
        'pwa-bridge.php',         // Dépend des autres, doit être chargé en dernier
    );
    
    /**
     * Statut d'initialisation des bridges
     * @var array
     */
    private $initialized = array();
    
    /**
     * Chemin racine des bridges
     * @var string
     */
    private $bridges_path;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->bridges_path = plugin_dir_path(__FILE__);
        
        // Initialiser le statut
        foreach ($this->bridge_order as $bridge) {
            $this->initialized[$bridge] = false;
        }
        
        // Si le noyau central est disponible, lui déléguer l'initialisation
        if (function_exists('life_travel_bridges_core')) {
            // Le noyau se charge de l'initialisation, on ne s'enregistre pas
        } else {
            // Fallback vers l'ancien système
            add_action('plugins_loaded', array($this, 'init_bridges'), 5);
        }
    }
    
    /**
     * Initialise tous les bridges dans l'ordre défini
     */
    public function init_bridges() {
        // Si le noyau central est disponible, lui déléguer l'initialisation
        if (function_exists('life_travel_bridges_core')) {
            // Le noyau se charge de l'initialisation
            return;
        }
        
        // Fallback vers l'ancien système
        foreach ($this->bridge_order as $bridge) {
            $this->init_bridge($bridge);
        }
    }
    
    /**
     * Initialise un bridge spécifique et ses dépendances
     * 
     * @param string $bridge Nom du fichier bridge
     * @return bool Succès de l'initialisation
     */
    public function init_bridge($bridge) {
        // Si le noyau central est disponible, lui déléguer l'initialisation
        if (function_exists('life_travel_bridges_core')) {
            // Obtenir le bridge_name à partir du nom de fichier
            $bridge_name = str_replace(['-bridge.php', '.php'], '', $bridge);
            return life_travel_bridges_core()->is_bridge_loaded($bridge_name);
        }
        
        // Fallback vers l'ancien système
        // Si déjà initialisé, sortir
        if (isset($this->initialized[$bridge]) && $this->initialized[$bridge]) {
            return true;
        }
        
        // Chemin complet du bridge
        $bridge_path = $this->bridges_path . $bridge;
        
        // Vérifier l'existence du fichier
        if (!file_exists($bridge_path)) {
            // Journaliser l'erreur
            error_log('Life Travel: Bridge manquant - ' . $bridge_path);
            return false;
        }
        
        // Inclure le bridge
        require_once $bridge_path;
        
        // Marquer comme initialisé
        $this->initialized[$bridge] = true;
        
        return true;
    }
    
    /**
     * Vérifie si les bridges ont été initialisés correctement
     * 
     * @return array Statut d'initialisation
     */
    public function get_initialization_status() {
        // Si le noyau central est disponible, utiliser ses informations
        if (function_exists('life_travel_bridges_core')) {
            $registry = life_travel_bridges_core()->get_bridge_registry();
            $circular_deps = life_travel_bridges_core()->get_circular_dependencies();
            
            $status = array(
                'success' => true,
                'loaded' => array(),
                'failed' => array(),
                'circular_dependencies' => $circular_deps
            );
            
            // Vérifier si tous les bridges nécessaires sont chargés
            $all_loaded = true;
            
            foreach ($registry as $bridge_name => $bridge_data) {
                $bridge_file = ($bridge_name === 'bridge-validator') ? 
                    $bridge_name . '.php' : $bridge_name . '-bridge.php';
                    
                if ($bridge_data['loaded']) {
                    $status['loaded'][] = $bridge_file;
                } else {
                    $status['failed'][] = $bridge_file;
                    $all_loaded = false;
                }
            }
            
            $status['success'] = $all_loaded && empty($circular_deps);
            return $status;
        }
        
        // Fallback vers l'ancien système
        // Vérifier si tous les bridges attendus sont chargés
        $all_loaded = true;
        $status = array(
            'success' => true,
            'loaded' => array(),
            'failed' => array()
        );
        
        foreach ($this->bridge_order as $bridge) {
            if (isset($this->initialized[$bridge]) && $this->initialized[$bridge]) {
                $status['loaded'][] = $bridge;
            } else {
                $status['failed'][] = $bridge;
                $all_loaded = false;
            }
        }
        
        $status['success'] = $all_loaded;
        
        return $status;
    }
}

/**
 * N'instancier l'ancien initializer que si le nouveau noyau n'est pas disponible
 * pour assurer la compatibilité ascendante tout en évitant la duplication
 */
global $life_travel_bridge_initializer;

if (!function_exists('life_travel_bridges_core')) {
    $life_travel_bridge_initializer = new Life_Travel_Bridge_Initializer();
} else {
    // Instancier uniquement pour maintenir la compatibilité avec le code existant
    // sans activer l'initialisation (déléguée au noyau central)
    $life_travel_bridge_initializer = new Life_Travel_Bridge_Initializer();
}

/**
 * Obtient l'instance de l'initialiseur de bridges
 * 
 * Version compatible qui retourne l'ancien initializer ou un wrapper autour
 * du nouveau noyau central selon ce qui est disponible
 * 
 * @return Life_Travel_Bridge_Initializer Instance de l'initialiseur
 */
function life_travel_bridge_initializer() {
    global $life_travel_bridge_initializer;
    return $life_travel_bridge_initializer;
}