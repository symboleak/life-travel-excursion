<?php
/**
 * Gestionnaire de stockage local
 * 
 * Fournit une solution de stockage côté client sans dépendre de LocalForage
 * Cette classe gère la persistance des données via IndexedDB, WebSQL ou localStorage
 * 
 * @package Life Travel Excursion
 * @version 2.3.3
 */

// AJOUT_IMPORTANT: Créez un dossier 'assets/js' dans votre plugin si ce n'est pas déjà fait
// Vérifiez que le fichier storage-manager.js est bien présent dans ce dossier

defined('ABSPATH') || exit;

class Life_Travel_Storage_Manager {
    /**
     * Constructeur
     */
    public function __construct() {
        // Enregistrer les scripts de stockage
        add_action('wp_enqueue_scripts', array($this, 'register_scripts'));
        
        // Initialiser Ajax pour le fallback serveur
        add_action('wp_ajax_life_travel_store_data', array($this, 'ajax_store_data'));
        add_action('wp_ajax_nopriv_life_travel_store_data', array($this, 'ajax_store_data'));
        add_action('wp_ajax_life_travel_retrieve_data', array($this, 'ajax_retrieve_data'));
        add_action('wp_ajax_nopriv_life_travel_retrieve_data', array($this, 'ajax_retrieve_data'));
    }
    
    /**
     * Enregistrer les scripts nécessaires
     */
    public function register_scripts() {
        // Version du plugin pour cache busting
        $version = LIFE_TRAVEL_EXCURSION_VERSION;
        
        // Scripts principaux
        wp_register_script(
            'life-travel-storage',
            plugins_url('assets/js/storage-manager.js', dirname(__FILE__)),
            array('jquery'),
            $version,
            true
        );
        
        // Localiser les variables pour JavaScript
        wp_localize_script('life-travel-storage', 'lifeTravel', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('life_travel_storage_nonce'),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false',
            'storage_type' => 'auto' // auto, indexeddb, websql, localstorage, ajax
        ));
    }
    
    /**
     * Activer les scripts si nécessaire
     */
    public function enqueue_scripts() {
        wp_enqueue_script('life-travel-storage');
    }
    
    /**
     * Point d'entrée AJAX pour stocker des données
     */
    public function ajax_store_data() {
        // Vérifier le nonce
        if (!check_ajax_referer('life_travel_storage_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            exit;
        }
        
        // Récupération des données
        $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $value = isset($_POST['data']) ? sanitize_text_field($_POST['data']) : '';
        $user_id = get_current_user_id();
        
        if (empty($key)) {
            wp_send_json_error(array('message' => 'Key is required'));
            exit;
        }
        
        // Stocker les données dans la session et/ou les métadonnées utilisateur
        $storage_key = 'life_travel_' . $key;
        
        if ($user_id > 0) {
            // Utilisateur connecté, stocker dans les métadonnées
            update_user_meta($user_id, $storage_key, $value);
        } else {
            // Utilisateur anonyme, stocker dans les options temporaires
            set_transient($storage_key . '_' . $this->get_visitor_id(), $value, WEEK_IN_SECONDS);
        }
        
        wp_send_json_success(array('message' => 'Data stored successfully'));
    }
    
    /**
     * Point d'entrée AJAX pour récupérer des données
     */
    public function ajax_retrieve_data() {
        // Vérifier le nonce
        if (!check_ajax_referer('life_travel_storage_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            exit;
        }
        
        // Récupération de la clé
        $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $user_id = get_current_user_id();
        
        if (empty($key)) {
            wp_send_json_error(array('message' => 'Key is required'));
            exit;
        }
        
        // Récupérer les données depuis la source appropriée
        $storage_key = 'life_travel_' . $key;
        $value = null;
        
        if ($user_id > 0) {
            // Utilisateur connecté, récupérer depuis les métadonnées
            $value = get_user_meta($user_id, $storage_key, true);
        } else {
            // Utilisateur anonyme, récupérer depuis les options temporaires
            $value = get_transient($storage_key . '_' . $this->get_visitor_id());
        }
        
        if ($value !== false && $value !== '') {
            wp_send_json_success(array('data' => $value));
        } else {
            wp_send_json_error(array('message' => 'No data found'));
        }
    }
    
    /**
     * Générer un ID visiteur pour les utilisateurs non connectés
     * 
     * @return string ID visiteur
     */
    private function get_visitor_id() {
        if (isset($_COOKIE['life_travel_visitor_id'])) {
            return sanitize_text_field($_COOKIE['life_travel_visitor_id']);
        }
        
        // Générer un nouvel ID
        $visitor_id = 'visitor_' . wp_hash(uniqid('', true));
        
        // Définir le cookie pour 30 jours
        setcookie('life_travel_visitor_id', $visitor_id, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        
        return $visitor_id;
    }
}

// Initialiser le gestionnaire de stockage
function life_travel_storage_init() {
    // Créer une instance
    $storage_manager = new Life_Travel_Storage_Manager();
    
    // Stocker dans la variable globale pour y accéder facilement ailleurs
    $GLOBALS['life_travel_storage'] = $storage_manager;
    
    return $storage_manager;
}

// Initialiser le gestionnaire de stockage
add_action('init', 'life_travel_storage_init');

/**
 * Assurer que les scripts de stockage sont chargés sur les pages où c'est nécessaire
 */
function life_travel_ensure_storage_scripts() {
    if (is_product() || is_checkout() || is_cart() || is_account_page()) {
        if (isset($GLOBALS['life_travel_storage'])) {
            $GLOBALS['life_travel_storage']->enqueue_scripts();
        }
    }
}
add_action('wp_enqueue_scripts', 'life_travel_ensure_storage_scripts', 20);
