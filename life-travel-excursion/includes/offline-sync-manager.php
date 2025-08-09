<?php
/**
 * Gestionnaire de synchronisation hors-ligne
 *
 * Permet l'utilisation des fonctionnalités clés du plugin même sans connexion Internet,
 * avec synchronisation des données lorsque la connexion est rétablie.
 *
 * @package Life Travel Excursion
 * @version 2.5.0
 */

defined('ABSPATH') || exit;

/**
 * Classe de gestion des fonctionnalités hors-ligne avancées
 */
class Life_Travel_Offline_Sync_Manager {
    /**
     * Instance unique (Singleton)
     * @var Life_Travel_Offline_Sync_Manager
     */
    private static $instance = null;
    
    /**
     * Types de données qui peuvent être synchronisées
     * @var array
     */
    private $syncable_types = [
        'cart_items',
        'booking_request',
        'user_preferences', 
        'viewed_excursions',
        'favorite_excursions'
    ];
    
    /**
     * Configuration par défaut
     * @var array
     */
    private $default_config = [
        'offline_mode_enabled' => true,
        'max_offline_storage' => 10, // En MB
        'sync_interval' => 60,       // En secondes
        'retry_attempts' => 3,
        'expiry_time' => 24,         // En heures
        'auto_sync' => true,
        'debug_mode' => false
    ];
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Retourne l'instance unique (Singleton)
     * 
     * @return Life_Travel_Offline_Sync_Manager
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialise les hooks WordPress
     */
    private function init_hooks() {
        // Hooks AJAX pour synchroniser les données
        add_action('wp_ajax_life_travel_sync_offline_data', [$this, 'sync_offline_data']);
        add_action('wp_ajax_nopriv_life_travel_sync_offline_data', [$this, 'sync_offline_data']);
        
        // Hook pour obtenir les données nécessaires au mode hors-ligne
        add_action('wp_ajax_life_travel_get_offline_bundle', [$this, 'get_offline_bundle']);
        add_action('wp_ajax_nopriv_life_travel_get_offline_bundle', [$this, 'get_offline_bundle']);
        
        // Ajouter le JS et CSS nécessaires
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Hook pour traiter les tentatives de réservation en mode hors-ligne
        add_action('wp_ajax_life_travel_process_offline_booking', [$this, 'process_offline_booking']);
        add_action('wp_ajax_nopriv_life_travel_process_offline_booking', [$this, 'process_offline_booking']);
        
        // Nettoyage régulier des données hors-ligne expirées
        add_action('life_travel_cleanup_offline_data', [$this, 'cleanup_expired_data']);
        if (!wp_next_scheduled('life_travel_cleanup_offline_data')) {
            wp_schedule_event(time(), 'daily', 'life_travel_cleanup_offline_data');
        }
        
        // Modifier le contenu de la page pour injecter des données hors-ligne
        add_filter('the_content', [$this, 'inject_offline_data'], 999);
        
        // Ajouter les actions système pour le débogage
        add_action('admin_post_life_travel_test_offline_sync', [$this, 'test_offline_sync']);
    }
    
    /**
     * Enregistre les scripts et styles nécessaires au mode hors-ligne
     */
    public function enqueue_scripts() {
        // Ne pas charger sur les pages admin
        if (is_admin()) {
            return;
        }
        
        $version = defined('LIFE_TRAVEL_VERSION') ? LIFE_TRAVEL_VERSION : '2.5.0';
        $min = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        
        // Scripts principaux
        wp_enqueue_script(
            'life-travel-offline-core',
            plugins_url("assets/js/offline-core{$min}.js", dirname(__FILE__)),
            ['jquery', 'wp-util'],
            $version,
            true
        );
        
        // Scripts pour IndexedDB et service worker
        wp_enqueue_script(
            'life-travel-offline-storage',
            plugins_url("assets/js/offline-storage{$min}.js", dirname(__FILE__)),
            ['life-travel-offline-core'],
            $version,
            true
        );
        
        // Script de synchronisation
        wp_enqueue_script(
            'life-travel-offline-sync',
            plugins_url("assets/js/offline-sync{$min}.js", dirname(__FILE__)),
            ['life-travel-offline-storage'],
            $version,
            true
        );
        
        // Style pour le mode hors-ligne
        wp_enqueue_style(
            'life-travel-offline-style',
            plugins_url("assets/css/offline-mode{$min}.css", dirname(__FILE__)),
            [],
            $version
        );
        
        // Localisation pour JavaScript
        wp_localize_script('life-travel-offline-core', 'lifeTravelOffline', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('life_travel_offline_nonce'),
            'config' => $this->get_offline_config(),
            'l10n' => [
                'offline_mode' => __('Mode hors-ligne actif', 'life-travel-excursion'),
                'sync_needed' => __('Synchronisation nécessaire', 'life-travel-excursion'),
                'syncing' => __('Synchronisation en cours...', 'life-travel-excursion'),
                'sync_success' => __('Synchronisation réussie', 'life-travel-excursion'),
                'sync_error' => __('Erreur de synchronisation', 'life-travel-excursion'),
                'retry' => __('Réessayer', 'life-travel-excursion'),
                'offline_data_available' => __('Données disponibles hors-ligne', 'life-travel-excursion'),
                'limited_functionality' => __('Fonctionnalités limitées', 'life-travel-excursion')
            ]
        ]);
        
        // Enregistrement du service worker si supporté
        $this->register_service_worker();
    }
    
    /**
     * Enregistre le service worker pour fonctionnalités hors-ligne avancées
     */
    private function register_service_worker() {
        add_action('wp_footer', function() {
            ?>
            <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    // Centralisation de l'enregistrement dans pwa-bridge.php
                    navigator.serviceWorker.ready
                    .then(function(registration) {
                        console.log('Service Worker prêt (centralisé):', registration.scope);
                    })
                    .catch(function(error) {
                        console.log('Service Worker non prêt:', error);
                    });
                });
            }
            </script>
            <?php
        });
    }
    
    /**
     * Récupère la configuration pour le mode hors-ligne
     * 
     * @return array Configuration
     */
    public function get_offline_config() {
        $config = get_option('life_travel_offline_config', []);
        return wp_parse_args($config, $this->default_config);
    }
    
    /**
     * Met à jour la configuration du mode hors-ligne
     * 
     * @param array $new_config Nouvelle configuration
     * @return bool Succès ou échec
     */
    public function update_offline_config($new_config) {
        // Fusionner avec la configuration existante
        $current_config = $this->get_offline_config();
        $updated_config = wp_parse_args($new_config, $current_config);
        
        // Valider les valeurs
        $updated_config['max_offline_storage'] = abs(intval($updated_config['max_offline_storage']));
        $updated_config['sync_interval'] = abs(intval($updated_config['sync_interval']));
        $updated_config['retry_attempts'] = abs(intval($updated_config['retry_attempts']));
        $updated_config['expiry_time'] = abs(intval($updated_config['expiry_time']));
        
        return update_option('life_travel_offline_config', $updated_config);
    }
    
    /**
     * Point d'entrée AJAX pour synchroniser les données hors-ligne
     */
    public function sync_offline_data() {
        // Vérifier le nonce
        check_ajax_referer('life_travel_offline_nonce', 'security');
        
        // Valider les données
        $data_type = isset($_POST['data_type']) ? sanitize_text_field($_POST['data_type']) : '';
        $data = isset($_POST['data']) ? $_POST['data'] : '';
        $device_id = isset($_POST['device_id']) ? sanitize_text_field($_POST['device_id']) : '';
        
        if (!in_array($data_type, $this->syncable_types)) {
            wp_send_json_error(['message' => __('Type de données invalide', 'life-travel-excursion')]);
            return;
        }
        
        if (empty($data)) {
            wp_send_json_error(['message' => __('Données vides', 'life-travel-excursion')]);
            return;
        }
        
        // Traiter en fonction du type de données
        $result = $this->process_sync_data($data_type, $data, $device_id);
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * Traite les données de synchronisation selon le type
     * 
     * @param string $data_type Type de données
     * @param mixed $data Données à traiter
     * @param string $device_id ID de l'appareil
     * @return array Résultat du traitement
     */
    private function process_sync_data($data_type, $data, $device_id) {
        $user_id = get_current_user_id();
        $result = [
            'success' => false,
            'error' => ['message' => __('Erreur de traitement', 'life-travel-excursion')],
            'data' => []
        ];
        
        switch ($data_type) {
            case 'cart_items':
                $result = $this->sync_cart_items($data, $user_id, $device_id);
                break;
                
            case 'booking_request':
                $result = $this->sync_booking_request($data, $user_id, $device_id);
                break;
                
            case 'user_preferences':
                $result = $this->sync_user_preferences($data, $user_id, $device_id);
                break;
                
            case 'viewed_excursions':
                $result = $this->sync_viewed_excursions($data, $user_id, $device_id);
                break;
                
            case 'favorite_excursions':
                $result = $this->sync_favorite_excursions($data, $user_id, $device_id);
                break;
        }
        
        // Journaliser la synchronisation
        $this->log_sync_event($data_type, $result['success'], $user_id, $device_id);
        
        return $result;
    }
    
    /**
     * Synchronise les éléments du panier
     * 
     * @param array $data Données du panier
     * @param int $user_id ID utilisateur
     * @param string $device_id ID de l'appareil
     * @return array Résultat de la synchronisation
     */
    private function sync_cart_items($data, $user_id, $device_id) {
        // Initialisation du résultat
        $result = [
            'success' => false,
            'error' => ['message' => __('Erreur de synchronisation du panier', 'life-travel-excursion')],
            'data' => []
        ];
        
        try {
            // Décoder les données
            $cart_items = is_string($data) ? json_decode($data, true) : $data;
            
            if (!is_array($cart_items)) {
                throw new Exception(__('Format de panier invalide', 'life-travel-excursion'));
            }
            
            // Si l'utilisateur est connecté, mettre à jour son panier
            if ($user_id > 0) {
                // Fusionner avec le panier existant
                $existing_cart = WC()->cart->get_cart();
                $updated_cart = $this->merge_carts($existing_cart, $cart_items);
                
                // Vider le panier actuel
                WC()->cart->empty_cart();
                
                // Ajouter les éléments fusionnés
                foreach ($updated_cart as $cart_item) {
                    $product_id = isset($cart_item['product_id']) ? $cart_item['product_id'] : 0;
                    $quantity = isset($cart_item['quantity']) ? $cart_item['quantity'] : 1;
                    $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
                    $variation = isset($cart_item['variation']) ? $cart_item['variation'] : [];
                    $cart_item_data = isset($cart_item['cart_item_data']) ? $cart_item['cart_item_data'] : [];
                    
                    WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation, $cart_item_data);
                }
                
                // Sauvegarder le panier
                WC()->cart->persistent_cart_update();
                
                $result['success'] = true;
                $result['data'] = [
                    'cart_hash' => WC()->cart->get_cart_hash(),
                    'item_count' => WC()->cart->get_cart_contents_count(),
                    'cart_total' => WC()->cart->get_cart_total()
                ];
            } else {
                // Utilisateur non connecté, stocker dans les cookies pour fusion ultérieure
                $this->store_offline_cart($cart_items, $device_id);
                
                $result['success'] = true;
                $result['data'] = [
                    'message' => __('Panier stocké localement', 'life-travel-excursion'),
                    'item_count' => count($cart_items)
                ];
            }
        } catch (Exception $e) {
            $result['error']['message'] = $e->getMessage();
            
            // Journaliser l'erreur pour le débogage
            if ($this->get_offline_config()['debug_mode']) {
                error_log('[Life Travel] Sync error: ' . $e->getMessage());
            }
        }
        
        return $result;
    }
    
    /**
     * Fusionne deux paniers
     * 
     * @param array $cart1 Premier panier
     * @param array $cart2 Deuxième panier
     * @return array Panier fusionné
     */
    private function merge_carts($cart1, $cart2) {
        // Créer un tableau indexé par clé de produit
        $merged = [];
        
        // Ajouter les éléments du premier panier
        foreach ($cart1 as $key => $item) {
            $product_id = $item['product_id'];
            $variation_id = $item['variation_id'] ?? 0;
            $merge_key = $product_id . '_' . $variation_id;
            
            $merged[$merge_key] = $item;
        }
        
        // Ajouter ou mettre à jour avec les éléments du deuxième panier
        foreach ($cart2 as $item) {
            $product_id = $item['product_id'];
            $variation_id = $item['variation_id'] ?? 0;
            $merge_key = $product_id . '_' . $variation_id;
            
            // Si l'élément existe déjà, prendre le plus récent
            if (isset($merged[$merge_key])) {
                // Comparer les timestamps si disponibles
                $time1 = $merged[$merge_key]['time_added'] ?? 0;
                $time2 = $item['time_added'] ?? 0;
                
                if ($time2 > $time1) {
                    $merged[$merge_key] = $item;
                }
            } else {
                $merged[$merge_key] = $item;
            }
        }
        
        return array_values($merged);
    }
    
    /**
     * Stocke le panier hors-ligne
     * 
     * @param array $cart_items Éléments du panier
     * @param string $device_id ID de l'appareil
     */
    private function store_offline_cart($cart_items, $device_id) {
        // Récupérer les paniers existants
        $offline_carts = get_option('life_travel_offline_carts', []);
        
        // Ajouter/mettre à jour ce panier
        $offline_carts[$device_id] = [
            'items' => $cart_items,
            'time' => current_time('timestamp'),
            'expiry' => current_time('timestamp') + (HOUR_IN_SECONDS * $this->get_offline_config()['expiry_time'])
        ];
        
        // Sauvegarder
        update_option('life_travel_offline_carts', $offline_carts);
    }
    
    /**
     * Journalise un événement de synchronisation
     * 
     * @param string $data_type Type de données
     * @param bool $success Succès ou échec
     * @param int $user_id ID utilisateur
     * @param string $device_id ID de l'appareil
     */
    private function log_sync_event($data_type, $success, $user_id, $device_id) {
        global $wpdb;
        
        // Uniquement si la journalisation est activée
        if (!$this->get_offline_config()['debug_mode']) {
            return;
        }
        
        // Table des journaux de synchronisation
        $table_name = $wpdb->prefix . 'life_travel_sync_logs';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Créer la table si elle n'existe pas
            $this->create_sync_log_table();
        }
        
        // Insérer le journal
        $wpdb->insert(
            $table_name,
            [
                'data_type' => $data_type,
                'success' => $success ? 1 : 0,
                'user_id' => $user_id,
                'device_id' => $device_id,
                'sync_time' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ],
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Crée la table de journalisation de synchronisation
     */
    private function create_sync_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'life_travel_sync_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            data_type varchar(50) NOT NULL,
            success tinyint(1) NOT NULL DEFAULT 0,
            user_id bigint(20) NOT NULL DEFAULT 0,
            device_id varchar(100) NOT NULL,
            sync_time datetime NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text NOT NULL,
            PRIMARY KEY  (id),
            KEY data_type (data_type),
            KEY user_id (user_id),
            KEY device_id (device_id),
            KEY sync_time (sync_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    // Suite dans la partie 2...
}
