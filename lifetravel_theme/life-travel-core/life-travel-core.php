<?php
/**
 * Plugin Name: Life Travel Core
 * Plugin URI: https://lifetravel.cm
 * Description: Fonctionnalités essentielles pour le site Life Travel (Blocs Gutenberg, CPT, intégrations)
 * Version: 1.0.0
 * Author: VipeCoding-WebLead
 * Author URI: https://vipecoding.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: life-travel-core
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * WC requires at least: 8.0
 * WC tested up to: 8.5
 *
 * @package Life_Travel_Core
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('LIFE_TRAVEL_CORE_VERSION', '1.0.0');
define('LIFE_TRAVEL_CORE_FILE', __FILE__);
define('LIFE_TRAVEL_CORE_DIR', plugin_dir_path(__FILE__));
define('LIFE_TRAVEL_CORE_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
final class Life_Travel_Core {
    /**
     * Instance
     *
     * @var Life_Travel_Core|null
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once LIFE_TRAVEL_CORE_DIR . 'includes/class-cpt.php';
        require_once LIFE_TRAVEL_CORE_DIR . 'includes/class-taxonomies.php';
        require_once LIFE_TRAVEL_CORE_DIR . 'includes/class-acf-fields.php';
        require_once LIFE_TRAVEL_CORE_DIR . 'includes/class-woocommerce.php';
        require_once LIFE_TRAVEL_CORE_DIR . 'includes/class-optimizations.php';
        require_once LIFE_TRAVEL_CORE_DIR . 'includes/class-addon-bridge.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register blocks
        add_action('init', array($this, 'register_blocks'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        
        // Activation/deactivation hooks
        register_activation_hook(LIFE_TRAVEL_CORE_FILE, array($this, 'activate'));
        register_deactivation_hook(LIFE_TRAVEL_CORE_FILE, array($this, 'deactivate'));
    }

    /**
     * Register Gutenberg blocks
     */
    public function register_blocks() {
        // Only register blocks if Gutenberg is active
        if (!function_exists('register_block_type')) {
            return;
        }

        // Month Slider block
        register_block_type(LIFE_TRAVEL_CORE_DIR . 'blocks/month-slider/block.json');
        
        // Calendar block
        register_block_type(LIFE_TRAVEL_CORE_DIR . 'blocks/calendar/block.json');
        
        // Vote Module block
        register_block_type(LIFE_TRAVEL_CORE_DIR . 'blocks/vote-module/block.json');
        
        // Hero Banner block
        register_block_type(LIFE_TRAVEL_CORE_DIR . 'blocks/hero-banner/block.json');
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        // Main CSS
        wp_enqueue_style(
            'life-travel-core',
            LIFE_TRAVEL_CORE_URL . 'assets/css/life-travel-core.min.css',
            array(),
            LIFE_TRAVEL_CORE_VERSION
        );

        // Main JS
        wp_enqueue_script(
            'life-travel-core',
            LIFE_TRAVEL_CORE_URL . 'assets/js/life-travel-core.min.js',
            array('jquery'),
            LIFE_TRAVEL_CORE_VERSION,
            true
        );

        // Localize script with AJAX URLs and settings
        wp_localize_script('life-travel-core', 'lifeTravelCore', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('life-travel-core-nonce'),
            'isUserLoggedIn' => is_user_logged_in(),
        ));
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        // Editor CSS
        wp_enqueue_style(
            'life-travel-core-editor',
            LIFE_TRAVEL_CORE_URL . 'assets/css/life-travel-core-editor.min.css',
            array('wp-edit-blocks'),
            LIFE_TRAVEL_CORE_VERSION
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Flush rewrite rules after registering CPTs and taxonomies
        Life_Travel_Core_CPT::register_post_types();
        Life_Travel_Core_Taxonomies::register_taxonomies();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function life_travel_core() {
    return Life_Travel_Core::instance();
}

// Start the plugin
life_travel_core();
