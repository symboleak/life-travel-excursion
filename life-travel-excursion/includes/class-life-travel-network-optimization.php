<?php
/**
 * Gestionnaire d'optimisation réseau pour Life Travel Excursion
 *
 * Intègre tous les modules d'optimisation pour les connexions lentes et le mode hors-ligne.
 * Spécialement conçu pour les contextes de connexion instable comme au Cameroun.
 *
 * @package Life Travel Excursion
 * @version 2.5.0
 */

defined('ABSPATH') || exit;

/**
 * Classe principale d'optimisation réseau
 */
class Life_Travel_Network_Optimization {
    /**
     * Instance unique (Singleton)
     * @var Life_Travel_Network_Optimization
     */
    private static $instance = null;
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        $this->initialize();
    }
    
    /**
     * Retourne l'instance unique
     * 
     * @return Life_Travel_Network_Optimization
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialise tous les modules d'optimisation réseau
     */
    private function initialize() {
        // Inclure et initialiser le gestionnaire de connexion lente
        $this->include_cameroon_optimizer();
        
        // Inclure et initialiser l'optimiseur AJAX pour le calcul de prix
        $this->include_pricing_optimizer();
        
        // Inclure et initialiser le gestionnaire de synchronisation hors-ligne
        $this->include_offline_sync_manager();
        
        // Ajouter les scripts et styles nécessaires
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // Ajouter les endpoints AJAX pour le mode hors-ligne
        add_action('wp_ajax_life_travel_network_test', [$this, 'handle_network_test']);
        add_action('wp_ajax_nopriv_life_travel_network_test', [$this, 'handle_network_test']);
        
        // Ajouter le support de la page hors-ligne
        add_action('wp_head', [$this, 'add_offline_support']);
        
        // Initialiser le système d'analyse de performance réseau
        add_action('init', [$this, 'init_network_analytics']);
        
        // Ajouter l'état du réseau aux requêtes AJAX
        add_filter('life_travel_ajax_data', [$this, 'add_network_status_to_ajax']);
    }
    
    /**
     * Inclut et initialise l'optimiseur Cameroun
     */
    private function include_cameroon_optimizer() {
        $optimizer_path = plugin_dir_path(dirname(__FILE__)) . 'includes/cameroon-assets-optimizer.php';
        
        if (file_exists($optimizer_path)) {
            require_once $optimizer_path;
            
            // Activer l'optimiseur camerounais si disponible
            if (class_exists('Life_Travel_Cameroon_Optimizer')) {
                Life_Travel_Cameroon_Optimizer::get_instance();
            }
        }
    }
    
    /**
     * Inclut et initialise l'optimiseur AJAX pour le calcul de prix
     */
    private function include_pricing_optimizer() {
        $pricing_optimizer_path = plugin_dir_path(dirname(__FILE__)) . 'includes/ajax/pricing-ajax-optimizer.php';
        
        if (file_exists($pricing_optimizer_path)) {
            require_once $pricing_optimizer_path;
            
            // Activer l'optimiseur de calcul de prix
            if (function_exists('life_travel_pricing_ajax_optimizer')) {
                life_travel_pricing_ajax_optimizer();
            }
        }
    }
    
    /**
     * Inclut et initialise le gestionnaire de synchronisation hors-ligne
     */
    private function include_offline_sync_manager() {
        $offline_sync_path = plugin_dir_path(dirname(__FILE__)) . 'includes/offline-sync-manager.php';
        $offline_handlers_path = plugin_dir_path(dirname(__FILE__)) . 'includes/offline-sync-manager-handlers.php';
        
        if (file_exists($offline_sync_path)) {
            require_once $offline_sync_path;
            
            // Activer le gestionnaire de synchronisation hors-ligne
            if (class_exists('Life_Travel_Offline_Sync_Manager')) {
                Life_Travel_Offline_Sync_Manager::get_instance();
            }
        }
        
        if (file_exists($offline_handlers_path)) {
            require_once $offline_handlers_path;
            
            // Activer les gestionnaires spécifiques
            if (function_exists('life_travel_offline_sync_handlers')) {
                life_travel_offline_sync_handlers();
            }
        }
    }
    
    /**
     * Enregistre les scripts et styles pour le frontend
     */
    public function enqueue_frontend_assets() {
        $version = defined('LIFE_TRAVEL_VERSION') ? LIFE_TRAVEL_VERSION : '2.5.0';
        $min = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        
        // Styles pour le mode hors-ligne
        wp_enqueue_style(
            'life-travel-offline-style',
            plugins_url('assets/css/offline-mode' . $min . '.css', dirname(__FILE__)),
            [],
            $version
        );
        
        // Script principal pour la détection réseau
        wp_enqueue_script(
            'life-travel-offline-core',
            plugins_url('assets/js/offline-core' . $min . '.js', dirname(__FILE__)),
            ['jquery'],
            $version,
            true
        );
        
        // Script de stockage local
        wp_enqueue_script(
            'life-travel-offline-storage',
            plugins_url('assets/js/offline-storage' . $min . '.js', dirname(__FILE__)),
            ['life-travel-offline-core'],
            $version,
            true
        );
        
        // Script de synchronisation
        wp_enqueue_script(
            'life-travel-offline-sync',
            plugins_url('assets/js/offline-sync' . $min . '.js', dirname(__FILE__)),
            ['life-travel-offline-storage'],
            $version,
            true
        );
        
        // Configuration pour les scripts
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
    }
    
    /**
     * Obtient la configuration du mode hors-ligne
     * 
     * @return array Configuration
     */
    private function get_offline_config() {
        $default_config = [
            'offline_mode_enabled' => true,
            'max_offline_storage' => 10, // En MB
            'sync_interval' => 60,       // En secondes
            'retry_attempts' => 3,
            'expiry_time' => 24,         // En heures
            'auto_sync' => true,
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG
        ];
        
        // Récupérer la configuration depuis les options
        $config = get_option('life_travel_offline_config', []);
        
        return wp_parse_args($config, $default_config);
    }
    
    /**
     * Ajoute le support de la page hors-ligne
     */
    public function add_offline_support() {
        ?>
        <link rel="manifest" href="<?php echo esc_url(plugins_url('assets/manifest.json', dirname(__FILE__))); ?>">
        <meta name="theme-color" content="#3498db">
        <link rel="apple-touch-icon" href="<?php echo esc_url(plugins_url('assets/img/touch-icon.png', dirname(__FILE__))); ?>">
        <?php
    }
    
    /**
     * Initialise le système d'analyse de performance réseau
     */
    public function init_network_analytics() {
        // Activer la collecte de statistiques réseau uniquement si nécessaire
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            // Collecter les données de performance réseau
            add_action('shutdown', [$this, 'collect_network_performance_data'], 999);
        }
    }
    
    /**
     * Collecte les données de performance réseau
     */
    public function collect_network_performance_data() {
        // Ne pas collecter pour les requêtes admin-ajax pour éviter la récursivité
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // Récupérer les données de performance
        $performance_data = [
            'page_load_time' => timer_stop(0, 3),
            'memory_usage' => memory_get_peak_usage(true),
            'queries' => get_num_queries(),
            'timestamp' => time(),
            'url' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'ip_address' => $this->get_client_ip()
        ];
        
        // Ne stocker que si activé dans les options
        if (get_option('life_travel_collect_performance_data', 'no') === 'yes') {
            $this->store_performance_data($performance_data);
        }
    }
    
    /**
     * Stocke les données de performance
     * 
     * @param array $data Données de performance
     */
    private function store_performance_data($data) {
        global $wpdb;
        
        // Table des performances
        $table_name = $wpdb->prefix . 'life_travel_performance_logs';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Créer la table si elle n'existe pas
            $this->create_performance_table();
        }
        
        // Insérer les données
        $wpdb->insert(
            $table_name,
            [
                'page_load_time' => $data['page_load_time'],
                'memory_usage' => $data['memory_usage'],
                'queries' => $data['queries'],
                'timestamp' => date('Y-m-d H:i:s', $data['timestamp']),
                'url' => $data['url'],
                'user_agent' => $data['user_agent'],
                'ip_address' => $data['ip_address']
            ],
            ['%f', '%d', '%d', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Crée la table des performances
     */
    private function create_performance_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'life_travel_performance_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            page_load_time float NOT NULL,
            memory_usage bigint(20) NOT NULL,
            queries int(11) NOT NULL,
            timestamp datetime NOT NULL,
            url varchar(255) NOT NULL,
            user_agent text NOT NULL,
            ip_address varchar(45) NOT NULL,
            PRIMARY KEY  (id),
            KEY timestamp (timestamp),
            KEY url (url)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Gère le point d'entrée AJAX pour le test réseau
     */
    public function handle_network_test() {
        $response = [
            'success' => true,
            'serverTime' => time(),
            'timestamp' => microtime(true)
        ];
        
        // Ajouter des informations supplémentaires pour les utilisateurs connectés
        if (is_user_logged_in() && current_user_can('edit_posts')) {
            $response['memoryUsage'] = memory_get_usage(true);
            $response['phpVersion'] = phpversion();
            $response['wpVersion'] = get_bloginfo('version');
        }
        
        wp_send_json($response);
    }
    
    /**
     * Ajoute le statut réseau aux requêtes AJAX
     * 
     * @param array $data Données AJAX
     * @return array Données modifiées
     */
    public function add_network_status_to_ajax($data) {
        // Ajouter le statut réseau aux données AJAX si disponible
        if (isset($_POST['network_status'])) {
            $data['network_status'] = sanitize_text_field($_POST['network_status']);
        }
        
        return $data;
    }
    
    /**
     * Obtient l'adresse IP du client
     * 
     * @return string Adresse IP
     */
    private function get_client_ip() {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = sanitize_text_field($_SERVER[$key]);
                $ips = explode(',', $ip);
                $cleaned_ip = trim($ips[0]);
                
                // Valider l'adresse IP
                if (filter_var($cleaned_ip, FILTER_VALIDATE_IP)) {
                    return $cleaned_ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
}

// Initialisation
function life_travel_network_optimization() {
    return Life_Travel_Network_Optimization::get_instance();
}

// Démarrage automatique
add_action('plugins_loaded', 'life_travel_network_optimization');
