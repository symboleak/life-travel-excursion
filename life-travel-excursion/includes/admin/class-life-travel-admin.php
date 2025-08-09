<?php
/**
 * Tableau de bord unifié pour Life Travel
 *
 * Ce fichier définit l'interface d'administration unifiée pour Life Travel,
 * conçue pour être accessible aux administrateurs sans compétences techniques.
 *
 * @package Life Travel Excursion
 * @version 2.3.7
 */

defined('ABSPATH') || exit;

// Inclure les fichiers des traits de renderers
require_once dirname(__FILE__) . '/class-life-travel-admin-renderers-dashboard.php';
require_once dirname(__FILE__) . '/class-life-travel-admin-renderers-media.php';
require_once dirname(__FILE__) . '/class-life-travel-admin-renderers-network.php';
require_once dirname(__FILE__) . '/class-life-travel-admin-renderers-network-tester.php';
require_once dirname(__FILE__) . '/class-life-travel-admin-renderers-payments.php';
require_once dirname(__FILE__) . '/class-life-travel-admin-renderers-cart.php';
require_once dirname(__FILE__) . '/class-life-travel-admin-renderers-excursions.php';
require_once dirname(__FILE__) . '/class-life-travel-admin-renderers-optimizer.php';
require_once dirname(__FILE__) . '/class-life-travel-admin-renderers-offline.php';
require_once dirname(__FILE__) . '/class-life-travel-admin-renderers-database.php';
require_once dirname(__FILE__) . '/class-life-travel-admin-renderers-performance.php';

/**
 * Classe principale du tableau de bord Life Travel
 */
class Life_Travel_Admin {
    // Importer les traits de renderers
    use Life_Travel_Admin_Renderers_Dashboard;
    use Life_Travel_Admin_Renderers_Media;
    use Life_Travel_Admin_Renderers_Network;
    use Life_Travel_Admin_Renderers_Network_Tester;
    use Life_Travel_Admin_Renderers_Payments;
    use Life_Travel_Admin_Renderers_Cart;
    use Life_Travel_Admin_Renderers_Excursions;
    use Life_Travel_Admin_Renderers_Optimizer;
    use Life_Travel_Admin_Renderers_Offline;
    use Life_Travel_Admin_Renderers_Database;
    use Life_Travel_Admin_Renderers_Performance;
    /**
     * Instance unique
     * @var Life_Travel_Admin
     */
    private static $instance = null;
    
    /**
     * Pages d'administration enregistrées
     * @var array
     */
    private $admin_pages = [];
    
    /**
     * Options d'administration enregistrées
     * @var array
     */
    private $admin_options = [];
    
    /**
     * Tooltips d'aide
     * @var array
     */
    private $tooltips = [];
    
    /**
     * Obtient l'instance unique
     * @return Life_Travel_Admin
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Ajouter les menus d'administration
        add_action('admin_menu', array($this, 'register_admin_menu'));
        
        // Enregistrer les assets d'administration
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        
        // Ajouter les écrans d'aide contextuels
        add_action('current_screen', array($this, 'add_help_tabs'));
        
        // Initialiser les pages par défaut
        $this->init_default_pages();
        
        // Ajouter AJAX handlers
        add_action('wp_ajax_life_travel_admin_save_option', array($this, 'ajax_save_option'));
        
        // Permettre l'extension par d'autres plugins
        do_action('life_travel_admin_init', $this);
    }
    
    /**
     * Initialise les pages d'administration par défaut
     */
    private function init_default_pages() {
        // Tableau de bord principal
        $this->register_admin_page('dashboard', [
            'title' => __('Tableau de bord Life Travel', 'life-travel-excursion'),
            'menu_title' => __('Life Travel', 'life-travel-excursion'),
            'capability' => 'manage_options',
            'icon' => 'dashicons-palmtree',
            'position' => 30,
            'sections' => [
                'overview' => [
                    'title' => __('Vue d\'ensemble', 'life-travel-excursion'),
                    'callback' => array($this, 'render_dashboard_overview')
                ],
                'quick_actions' => [
                    'title' => __('Actions rapides', 'life-travel-excursion'),
                    'callback' => array($this, 'render_dashboard_actions')
                ]
            ]
        ]);
        
        // Page de gestion des médias simplifiée
        $this->register_admin_page('media', [
            'title' => __('Médias du site', 'life-travel-excursion'),
            'parent' => 'dashboard',
            'capability' => 'upload_files',
            'sections' => [
                'logo' => [
                    'title' => __('Logo et identité', 'life-travel-excursion'),
                    'callback' => array($this, 'render_media_logos')
                ],
                'backgrounds' => [
                    'title' => __('Images d\'arrière-plan', 'life-travel-excursion'),
                    'callback' => array($this, 'render_media_backgrounds')
                ],
                'gallery' => [
                    'title' => __('Galeries d\'images', 'life-travel-excursion'),
                    'callback' => array($this, 'render_media_gallery')
                ]
            ]
        ]);
        
        // Page de réseau et performances
        $this->register_admin_page('network', [
            'title' => __('Réseau et performances', 'life-travel-excursion'),
            'parent' => 'dashboard',
            'capability' => 'manage_options',
            'sections' => [
                'connection' => [
                    'title' => __('Optimisations pour connexions lentes', 'life-travel-excursion'),
                    'callback' => array($this, 'render_network_connection')
                ],
                'mobile' => [
                    'title' => __('Optimisations mobiles', 'life-travel-excursion'),
                    'callback' => array($this, 'render_network_mobile')
                ],
                'tester' => [
                    'title' => __('Testeur de connexion', 'life-travel-excursion'),
                    'callback' => array($this, 'render_network_tester')
                ]
            ]
        ]);
        
        // Page des excursions
        $this->register_admin_page('excursions', [
            'title' => __('Excursions', 'life-travel-excursion'),
            'parent' => 'dashboard',
            'capability' => 'edit_posts',
            'sections' => [
                'manage' => [
                    'title' => __('Gérer les excursions', 'life-travel-excursion'),
                    'callback' => array($this, 'render_excursions_dashboard')
                ],
                'settings' => [
                    'title' => __('Paramètres globaux', 'life-travel-excursion'),
                    'callback' => array($this, 'render_excursions_settings')
                ]
            ]
        ]);
        
        // Page de l'optimisateur d'assets
        $this->register_admin_page('optimizer', [
            'title' => __('Optimisateur', 'life-travel-excursion'),
            'parent' => 'dashboard',
            'capability' => 'manage_options',
            'sections' => [
                'assets' => [
                    'title' => __('Optimisation des assets', 'life-travel-excursion'),
                    'callback' => array($this, 'render_optimizer_assets')
                ]
            ]
        ]);
        
        // Page des messages hors ligne
        $this->register_admin_page('offline', [
            'title' => __('Messages Hors Ligne', 'life-travel-excursion'),
            'parent' => 'network',
            'capability' => 'manage_options',
            'sections' => [
                'messages' => [
                    'title' => __('Personnalisation', 'life-travel-excursion'),
                    'callback' => array($this, 'render_offline_messages')
                ]
            ]
        ]);
        
        // Page des paiements simplifiée
        $this->register_admin_page('payments', [
            'title' => __('Paiements', 'life-travel-excursion'),
            'parent' => 'dashboard',
            'capability' => 'manage_woocommerce',
            'sections' => [
                'gateways' => [
                    'title' => __('Méthodes de paiement', 'life-travel-excursion'),
                    'callback' => array($this, 'render_payments_gateways')
                ],
                'status' => [
                    'title' => __('État des paiements', 'life-travel-excursion'),
                    'callback' => array($this, 'render_payments_status')
                ]
            ]
        ]);
        
        // Page des paniers abandonnés
        $this->register_admin_page('abandoned_carts', [
            'title' => __('Paniers abandonnés', 'life-travel-excursion'),
            'parent' => 'dashboard',
            'capability' => 'manage_woocommerce',
            'sections' => [
                'stats' => [
                    'title' => __('Statistiques', 'life-travel-excursion'),
                    'callback' => array($this, 'render_cart_abandoned')
                ],
                'recovery' => [
                    'title' => __('Récupération', 'life-travel-excursion'),
                    'callback' => array($this, 'render_cart_abandoned')
                ]
            ]
        ]);
        
        // Page d'optimisation de base de données (intégration pour le contexte camerounais)
        $this->register_database_admin_page();
        
        // Page d'analyse des performances (optimisations pour le contexte camerounais)
        $this->register_admin_page('performance', [
            'title' => __('Analyse des performances', 'life-travel-excursion'),
            'parent' => 'dashboard',
            'capability' => 'manage_options',
            'sections' => [
                'overview' => [
                    'title' => __('Vue d\'ensemble', 'life-travel-excursion'),
                    'callback' => array($this, 'render_performance_dashboard')
                ]
            ]
        ]);
    }
    
    /**
     * Enregistre une page d'administration
     * 
     * @param string $id Identifiant de la page
     * @param array $args Arguments de configuration
     */
    public function register_admin_page($id, $args = []) {
        $defaults = [
            'title' => '',             // Titre de la page
            'menu_title' => '',        // Titre du menu (facultatif)
            'capability' => 'manage_options', // Capacité requise
            'icon' => '',              // Icône (seulement pour les pages principales)
            'position' => null,        // Position dans le menu (pages principales)
            'parent' => '',            // Page parente (sous-pages)
            'sections' => [],          // Sections de la page
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        if (empty($args['menu_title'])) {
            $args['menu_title'] = $args['title'];
        }
        
        $this->admin_pages[$id] = $args;
        
        return $this;
    }
    
    /**
     * Enregistre les menus d'administration dans WordPress
     */
    public function register_admin_menu() {
        foreach ($this->admin_pages as $id => $page) {
            // Page principale ou sous-page
            if (empty($page['parent'])) {
                // Page principale
                $hook = add_menu_page(
                    $page['title'],
                    $page['menu_title'],
                    $page['capability'],
                    'life-travel-' . $id,
                    array($this, 'render_admin_page'),
                    $page['icon'],
                    $page['position']
                );
            } else {
                // Sous-page
                $parent = 'life-travel-' . $page['parent'];
                $hook = add_submenu_page(
                    $parent,
                    $page['title'],
                    $page['menu_title'],
                    $page['capability'],
                    'life-travel-' . $id,
                    array($this, 'render_admin_page')
                );
            }
            
            // Hook spécifique à la page
            add_action('load-' . $hook, array($this, 'page_init'));
        }
    }
    
    /**
     * Charge les assets d'administration (CSS et JavaScript)
     * 
     * @param string $hook_suffix Le suffixe de la page actuelle
     */
    public function admin_assets($hook_suffix) {
        // Vérifier si nous sommes sur une page Life Travel
        if (strpos($hook_suffix, 'life-travel-') !== 0) {
            return;
        }
        
        // CSS principal
        wp_enqueue_style(
            'life-travel-admin',
            plugin_dir_url(WP_PLUGIN_DIR . '/life-travel-excursion/life-travel-excursion.php') . 'assets/css/admin.css',
            array(),
            '2.3.7'
        );
        
        // JavaScript principal
        wp_enqueue_script(
            'life-travel-admin',
            plugin_dir_url(WP_PLUGIN_DIR . '/life-travel-excursion/life-travel-excursion.php') . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            '2.3.7',
            true
        );
        
        // Fallback pour l'environnement de développement
        if (!file_exists(plugin_dir_path(WP_PLUGIN_DIR . '/life-travel-excursion/life-travel-excursion.php') . 'assets/css/admin.css')) {
            // Utiliser un chemin relatif si le plugin n'est pas dans le répertoire standard
            $plugin_base_url = trailingslashit(site_url()) . 'wp-content/plugins/life-travel-excursion/';
            
            wp_deregister_style('life-travel-admin');
            wp_enqueue_style(
                'life-travel-admin',
                $plugin_base_url . 'assets/css/admin.css',
                array(),
                '2.3.7'
            );
            
            wp_deregister_script('life-travel-admin');
            wp_enqueue_script(
                'life-travel-admin',
                $plugin_base_url . 'assets/js/admin.js',
                array('jquery', 'wp-util'),
                '2.3.7',
                true
            );
        }
        
        // Media uploader de WordPress
        wp_enqueue_media();
        
        // Localize script
        wp_localize_script('life-travel-admin', 'lifeTravel', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('life_travel_admin_nonce'),
            'i18n' => array(
                'save' => __('Enregistrer', 'life-travel-excursion'),
                'cancel' => __('Annuler', 'life-travel-excursion'),
                'confirm' => __('Êtes-vous sûr ?', 'life-travel-excursion'),
                'selectImage' => __('Sélectionner une image', 'life-travel-excursion'),
                'useImage' => __('Utiliser cette image', 'life-travel-excursion')
            )
        ));
    }
    
    /**
     * Initialise une page d'administration
     */
    public function page_init() {
        $screen = get_current_screen();
        
        // Extraire l'ID de la page à partir du screen ID
        if (preg_match('/life-travel-(\w+)$/', $screen->id, $matches)) {
            $page_id = $matches[1];
            
            // Ajouter un filtre pour les sections personnalisées
            add_filter('screen_settings', array($this, 'screen_settings'), 10, 2);
            
            // Action pour permettre l'extension
            do_action('life_travel_admin_page_init', $page_id, $screen);
        }
    }
    
    /**
     * Rend une page d'administration
     */
    public function render_admin_page() {
        $screen = get_current_screen();
        
        // Extraire l'ID de la page à partir du screen ID
        if (preg_match('/life-travel-(\w+)$/', $screen->id, $matches)) {
            $page_id = $matches[1];
            
            // Récupérer les informations de la page
            if (isset($this->admin_pages[$page_id])) {
                $page = $this->admin_pages[$page_id];
                
                // Ouvrir le container
                echo '<div class="wrap life-travel-admin-wrap">';
                echo '<h1>' . esc_html($page['title']) . '</h1>';
                
                // Navigation des sections
                if (!empty($page['sections']) && count($page['sections']) > 1) {
                    echo '<nav class="life-travel-admin-tabs">';
                    echo '<ul>';
                    
                    foreach ($page['sections'] as $section_id => $section) {
                        $active = isset($_GET['section']) ? $_GET['section'] === $section_id : 0 === key($page['sections']);
                        $class = $active ? 'active' : '';
                        $url = add_query_arg('section', $section_id);
                        
                        echo '<li class="' . esc_attr($class) . '">';
                        echo '<a href="' . esc_url($url) . '">' . esc_html($section['title']) . '</a>';
                        echo '</li>';
                    }
                    
                    echo '</ul>';
                    echo '</nav>';
                }
                
                // Contenu de la section actuelle
                $current_section = isset($_GET['section']) ? $_GET['section'] : key($page['sections']);
                
                if (isset($page['sections'][$current_section])) {
                    $section = $page['sections'][$current_section];
                    
                    echo '<div class="life-travel-admin-section-wrap">';
                    
                    // Appeler la fonction de callback pour cette section
                    if (isset($section['callback']) && is_callable($section['callback'])) {
                        call_user_func($section['callback'], $page_id, $current_section);
                    }
                    
                    echo '</div>';
                }
                
                echo '</div>'; // .wrap
            }
        }
    }
    
    /**
     * Ajoute des options de configuration à l'écran d'administration
     * 
     * @param string $settings Paramètres HTML existants
     * @param WP_Screen $screen Objet d'écran actuel
     * @return string Paramètres HTML mis à jour
     */
    public function screen_settings($settings, $screen) {
        // Extraire l'ID de la page à partir du screen ID
        if (preg_match('/life-travel-(\w+)$/', $screen->id, $matches)) {
            $page_id = $matches[1];
            
            // Vérifier si la page a des paramètres d'écran
            if (isset($this->admin_pages[$page_id]['screen_settings']) && is_callable($this->admin_pages[$page_id]['screen_settings'])) {
                $settings .= call_user_func($this->admin_pages[$page_id]['screen_settings'], $screen);
            }
        }
        
        return $settings;
    }
    
    /**
     * Gestionnaire AJAX pour sauvegarder une option
     */
    public function ajax_save_option() {
        // Vérifier le nonce pour la sécurité
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_admin_nonce')) {
            wp_send_json_error(array('message' => __('Sécurité : nonce invalide.', 'life-travel-excursion')));
            exit;
        }
        
        // Vérifier les droits
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas les droits nécessaires.', 'life-travel-excursion')));
            exit;
        }
        
        // Récupérer les données
        $option_name = isset($_POST['option_name']) ? sanitize_key($_POST['option_name']) : '';
        $option_value = isset($_POST['option_value']) ? $_POST['option_value'] : '';
        
        // Valider l'option
        if (empty($option_name) || !isset($this->admin_options[$option_name])) {
            wp_send_json_error(array('message' => __('Option invalide.', 'life-travel-excursion')));
            exit;
        }
        
        // Sanitiser la valeur selon le type d'option
        $option_config = $this->admin_options[$option_name];
        $sanitized_value = $this->sanitize_option_value($option_value, $option_config);
        
        // Sauvegarder l'option
        update_option($option_name, $sanitized_value);
        
        // Succès
        wp_send_json_success(array(
            'message' => __('Option enregistrée avec succès.', 'life-travel-excursion'),
            'value' => $sanitized_value
        ));
    }
    
    /**
     * Sanitise une valeur d'option selon son type
     * 
     * @param mixed $value Valeur à sanitiser
     * @param array $config Configuration de l'option
     * @return mixed Valeur sanitaisée
     */
    private function sanitize_option_value($value, $config) {
        $type = isset($config['type']) ? $config['type'] : 'text';
        
        switch ($type) {
            case 'text':
                return sanitize_text_field($value);
                
            case 'textarea':
                return sanitize_textarea_field($value);
                
            case 'number':
                $min = isset($config['min']) ? $config['min'] : 0;
                $max = isset($config['max']) ? $config['max'] : PHP_INT_MAX;
                return max($min, min($max, intval($value)));
                
            case 'checkbox':
                return $value ? 'on' : 'off';
                
            case 'select':
                $options = isset($config['options']) ? $config['options'] : array();
                return in_array($value, array_keys($options)) ? $value : '';
                
            case 'media':
                return absint($value);
                
            case 'color':
                return sanitize_hex_color($value);
                
            case 'array':
                if (!is_array($value)) {
                    return array();
                }
                
                $sanitized = array();
                foreach ($value as $key => $item) {
                    if (is_string($key)) {
                        $sanitized[sanitize_key($key)] = sanitize_text_field($item);
                    } else {
                        $sanitized[] = sanitize_text_field($item);
                    }
                }
                return $sanitized;
                
            default:
                // Filtrable pour les types personnalisés
                return apply_filters('life_travel_sanitize_option_' . $type, $value, $config);
        }
    }
}