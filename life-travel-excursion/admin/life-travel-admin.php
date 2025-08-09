<?php
/**
 * Fichier principal de l'administration unifiée de Life Travel Excursion
 * 
 * Ce fichier gère l'interface d'administration principale qui centralise toutes
 * les options et paramètres du plugin Life Travel Excursion dans une interface
 * conviviale et accessible aux utilisateurs non techniques.
 * 
 * @package Life_Travel_Excursion
 * @since 2.0.0
 */

// Sortie directe interdite
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe principale de l'administration unifiée de Life Travel
 */
class Life_Travel_Admin_Interface {

    /**
     * Instance unique de la classe (pattern Singleton)
     * @var Life_Travel_Admin_Interface
     */
    private static $instance = null;

    /**
     * Onglets disponibles dans l'administration
     * @var array
     */
    private $tabs = array();

    /**
     * Constructeur
     */
    private function __construct() {
        // Ajouter les hooks d'administration
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Initialiser les onglets d'administration
        $this->init_tabs();
    }

    /**
     * Obtenir l'instance unique de la classe
     * 
     * @return Life_Travel_Admin_Interface Instance unique
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialiser les onglets d'administration
     */
    private function init_tabs() {
        $this->tabs = array(
            'general' => array(
                'title' => __('Général', 'life-travel-excursion'),
                'icon' => 'dashicons-admin-generic',
                'description' => __('Paramètres généraux du plugin Life Travel Excursion', 'life-travel-excursion'),
                'callback' => array($this, 'render_general_tab'),
                'position' => 10
            ),
            'payment' => array(
                'title' => __('Paiement', 'life-travel-excursion'),
                'icon' => 'dashicons-money-alt',
                'description' => __('Configuration des méthodes de paiement et des options de transaction', 'life-travel-excursion'),
                'callback' => array($this, 'render_payment_tab'),
                'position' => 20
            ),
            'notifications' => array(
                'title' => __('Notifications', 'life-travel-excursion'),
                'icon' => 'dashicons-email',
                'description' => __('Gestion des notifications email, SMS et WhatsApp', 'life-travel-excursion'),
                'callback' => array($this, 'render_notifications_tab'),
                'position' => 30
            ),
            'excursions' => array(
                'title' => __('Types d\'excursions', 'life-travel-excursion'),
                'icon' => 'dashicons-palmtree',
                'description' => __('Paramètres par défaut pour les différents types d\'excursions', 'life-travel-excursion'),
                'callback' => array($this, 'render_excursions_tab'),
                'position' => 40
            ),
            'security' => array(
                'title' => __('Sécurité', 'life-travel-excursion'),
                'icon' => 'dashicons-shield',
                'description' => __('Options de sécurité et d\'authentification', 'life-travel-excursion'),
                'callback' => array($this, 'render_security_tab'),
                'position' => 50
            ),
            'advanced' => array(
                'title' => __('Avancé', 'life-travel-excursion'),
                'icon' => 'dashicons-admin-tools',
                'description' => __('Paramètres avancés et outils de développement', 'life-travel-excursion'),
                'callback' => array($this, 'render_advanced_tab'),
                'position' => 60
            ),
        );
        
        // Trier les onglets par position
        uasort($this->tabs, function($a, $b) {
            return $a['position'] - $b['position'];
        });
        
        // Permettre aux extensions d'ajouter des onglets supplémentaires
        $this->tabs = apply_filters('life_travel_admin_tabs', $this->tabs);
    }

    /**
     * Ajouter le menu principal et les sous-menus dans l'administration WordPress
     */
    public function add_admin_menu() {
        $capability = 'manage_options';
        
        // Ajouter le menu principal
        add_menu_page(
            __('Life Travel Excursion', 'life-travel-excursion'),
            __('Life Travel', 'life-travel-excursion'),
            $capability,
            'life-travel-admin',
            array($this, 'render_admin_page'),
            'dashicons-palmtree',
            25 // Position dans le menu WordPress
        );
        
        // Ajouter les sous-menus pour chaque onglet
        foreach ($this->tabs as $tab_id => $tab) {
            add_submenu_page(
                'life-travel-admin',
                $tab['title'],
                $tab['title'],
                $capability,
                'life-travel-admin&tab=' . $tab_id,
                array($this, 'render_admin_page')
            );
        }
    }

    /**
     * Enregistrer et charger les assets (CSS/JS) de l'administration
     */
    public function enqueue_admin_assets($hook) {
        // Charger uniquement sur les pages de notre plugin
        if (strpos($hook, 'life-travel-admin') === false) {
            return;
        }
        
        // CSS principal de l'administration
        wp_enqueue_style(
            'life-travel-admin-css',
            LIFE_TRAVEL_PLUGIN_URL . 'assets/css/admin/admin-interface.css',
            array(),
            LIFE_TRAVEL_VERSION
        );
        
        // JavaScript pour l'interface d'administration
        wp_enqueue_script(
            'life-travel-admin-js',
            LIFE_TRAVEL_PLUGIN_URL . 'assets/js/admin/admin-interface.js',
            array('jquery', 'jquery-ui-tabs', 'wp-color-picker'),
            LIFE_TRAVEL_VERSION,
            true
        );
        
        // Passer des données au script
        wp_localize_script('life-travel-admin-js', 'lifeTravel', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('life_travel_admin_nonce'),
            'i18n' => array(
                'confirm_reset' => __('Êtes-vous sûr de vouloir réinitialiser les paramètres par défaut ?', 'life-travel-excursion'),
                'saved' => __('Paramètres enregistrés avec succès !', 'life-travel-excursion'),
                'error' => __('Une erreur s\'est produite. Veuillez réessayer.', 'life-travel-excursion')
            )
        ));
        
        // Charger les autres dépendances de WordPress
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
    }

    /**
     * Enregistrer tous les paramètres du plugin dans WordPress
     */
    public function register_settings() {
        // Enregistrer les groupes de réglages pour chaque onglet
        register_setting('life_travel_general_settings', 'life_travel_general_options');
        register_setting('life_travel_payment_settings', 'life_travel_payment_options');
        register_setting('life_travel_notification_settings', 'life_travel_notification_options');
        register_setting('life_travel_excursion_settings', 'life_travel_excursion_options');
        register_setting('life_travel_security_settings', 'life_travel_security_options');
        register_setting('life_travel_advanced_settings', 'life_travel_advanced_options');
    }

    /**
     * Afficher la page d'administration principale
     */
    public function render_admin_page() {
        // Récupérer l'onglet actif
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        
        // Vérifier que l'onglet existe
        if (!isset($this->tabs[$active_tab])) {
            $active_tab = 'general';
        }
        
        // Afficher l'en-tête de la page
        $this->render_admin_header();
        
        // Afficher les onglets
        $this->render_admin_tabs($active_tab);
        
        // Afficher le contenu de l'onglet actif
        echo '<div class="life-travel-tab-content">';
        
        // Vérifier que la méthode de callback existe
        if (isset($this->tabs[$active_tab]['callback']) && is_callable($this->tabs[$active_tab]['callback'])) {
            call_user_func($this->tabs[$active_tab]['callback']);
        } else {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Erreur: Impossible d\'afficher cet onglet.', 'life-travel-excursion');
            echo '</p></div>';
        }
        
        echo '</div>';
        
        // Afficher le pied de page
        $this->render_admin_footer();
    }

    /**
     * Afficher l'en-tête de la page d'administration
     */
    private function render_admin_header() {
        ?>
        <div class="wrap life-travel-admin-wrap">
            <h1 class="life-travel-admin-title">
                <span class="dashicons dashicons-palmtree"></span>
                <?php echo esc_html__('Administration Life Travel Excursion', 'life-travel-excursion'); ?>
            </h1>
            <div class="life-travel-admin-header">
                <div class="life-travel-admin-version">
                    <?php printf(
                        esc_html__('Version %s', 'life-travel-excursion'),
                        LIFE_TRAVEL_VERSION
                    ); ?>
                </div>
                <div class="life-travel-admin-support">
                    <a href="https://support.life-travel.org" target="_blank" class="button">
                        <span class="dashicons dashicons-editor-help"></span>
                        <?php echo esc_html__('Support', 'life-travel-excursion'); ?>
                    </a>
                    <a href="https://docs.life-travel.org" target="_blank" class="button">
                        <span class="dashicons dashicons-book"></span>
                        <?php echo esc_html__('Documentation', 'life-travel-excursion'); ?>
                    </a>
                </div>
            </div>
        <?php
    }

    /**
     * Afficher les onglets de navigation
     * 
     * @param string $active_tab ID de l'onglet actif
     */
    private function render_admin_tabs($active_tab) {
        echo '<div class="life-travel-admin-tabs">';
        echo '<h2 class="nav-tab-wrapper">';
        
        foreach ($this->tabs as $tab_id => $tab) {
            $active_class = ($active_tab === $tab_id) ? 'nav-tab-active' : '';
            $url = admin_url('admin.php?page=life-travel-admin&tab=' . $tab_id);
            
            echo '<a href="' . esc_url($url) . '" class="nav-tab ' . esc_attr($active_class) . '">';
            echo '<span class="dashicons ' . esc_attr($tab['icon']) . '"></span> ';
            echo esc_html($tab['title']);
            echo '</a>';
        }
        
        echo '</h2>';
        
        // Afficher la description de l'onglet actif
        if (isset($this->tabs[$active_tab]['description'])) {
            echo '<div class="life-travel-tab-description">';
            echo '<p>' . esc_html($this->tabs[$active_tab]['description']) . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Afficher le pied de page de l'administration
     */
    private function render_admin_footer() {
        ?>
            <div class="life-travel-admin-footer">
                <p>
                    <?php printf(
                        esc_html__('Life Travel Excursion - Développé pour le Cameroun - %s', 'life-travel-excursion'),
                        date_i18n('Y')
                    ); ?>
                </p>
            </div>
        </div><!-- .wrap -->
        <?php
    }

    /**
     * Méthodes de rendu pour chaque onglet 
     * Chaque onglet sera implémenté dans un fichier séparé pour plus de clarté
     */
    public function render_general_tab() {
        require_once LIFE_TRAVEL_PLUGIN_DIR . 'admin/tabs/general-tab.php';
    }
    
    public function render_payment_tab() {
        require_once LIFE_TRAVEL_PLUGIN_DIR . 'admin/tabs/payment-tab.php';
    }
    
    public function render_notifications_tab() {
        require_once LIFE_TRAVEL_PLUGIN_DIR . 'admin/tabs/notifications-tab.php';
    }
    
    public function render_excursions_tab() {
        require_once LIFE_TRAVEL_PLUGIN_DIR . 'admin/tabs/excursions-tab.php';
    }
    
    public function render_security_tab() {
        require_once LIFE_TRAVEL_PLUGIN_DIR . 'admin/tabs/security-tab.php';
    }
    
    public function render_advanced_tab() {
        require_once LIFE_TRAVEL_PLUGIN_DIR . 'admin/tabs/advanced-tab.php';
    }
}

// Initialiser l'administration Life Travel
function life_travel_admin_init() {
    Life_Travel_Admin_Interface::get_instance();
}
add_action('plugins_loaded', 'life_travel_admin_init');
