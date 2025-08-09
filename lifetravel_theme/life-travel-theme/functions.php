<?php
/**
 * Life Travel Theme functions and definitions
 *
 * @package Life_Travel
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('LIFE_TRAVEL_VERSION', '1.0.0');
define('LIFE_TRAVEL_DIR', get_stylesheet_directory());
define('LIFE_TRAVEL_URI', get_stylesheet_directory_uri());

/**
 * Includes
 * 
 * @since 1.0.0
 * @modified 1.1.0 - Ajout du fichier de sécurité et d'accessibilité
 */
$life_travel_includes = array(
    '/inc/theme-setup.php',        // Configuration du thème
    '/inc/enqueue-scripts.php',    // Chargement des CSS et JS
    '/inc/template-functions.php', // Fonctions de template personnalisées
    '/inc/woocommerce-hooks.php',  // Hooks WooCommerce
    '/inc/blog-functions.php',     // Fonctions pour blog et excursions
    '/inc/security-functions.php', // Fonctions de sécurité
);

// Load required files
foreach ($life_travel_includes as $file) {
    if (file_exists(LIFE_TRAVEL_DIR . $file)) {
        require_once LIFE_TRAVEL_DIR . $file;
    }
}

/**
 * Set up theme hooks
 */
function life_travel_theme_setup() {
    // Add theme support
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    add_theme_support('editor-styles');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    
    // Support pour TranslatePress et traductions
    load_theme_textdomain('life-travel', get_template_directory() . '/languages');
    
    // Support spécifique pour TranslatePress (sélecteur de langue personnalisé)
    if (function_exists('trp_enable_translatepress')) {
        add_theme_support('trp-language-switcher');
    }
    
    // Custom image sizes optimized for mobile
    add_image_size('life-travel-thumbnail', 320, 213, true);
    add_image_size('life-travel-medium', 768, 432, true);
    add_image_size('life-travel-large', 1280, 720, true);
    
    // Register navigation menus
    register_nav_menus(array(
        'primary' => esc_html__('Menu Principal', 'life-travel'),
        'mobile'  => esc_html__('Menu Mobile', 'life-travel'),
        'footer'  => esc_html__('Menu Pied de Page', 'life-travel'),
    ));
    
    // Editor color palette
    add_theme_support('editor-color-palette', array(
        array(
            'name'  => esc_html__('Vert Forêt', 'life-travel'),
            'slug'  => 'primary',
            'color' => '#1F4D36',
        ),
        array(
            'name'  => esc_html__('Ocre Doré', 'life-travel'),
            'slug'  => 'secondary',
            'color' => '#E19D4C',
        ),
        array(
            'name'  => esc_html__('Sable Clair', 'life-travel'),
            'slug'  => 'light',
            'color' => '#F7F3EB',
        ),
        array(
            'name'  => esc_html__('Brun Terre', 'life-travel'),
            'slug'  => 'dark',
            'color' => '#3D2B1F',
        ),
        array(
            'name'  => esc_html__('Rouge Kayak', 'life-travel'),
            'slug'  => 'accent',
            'color' => '#C53D13',
        ),
    ));
}
add_action('after_setup_theme', 'life_travel_theme_setup');

/**
 * Integration with excursion-addon plugin
 * This hook customizes the templates from the proprietary addon
 */
function life_travel_excursion_addon_integration() {
    if (class_exists('Excursion_Addon')) {
        // Custom templates path for the addon
        add_filter('excursion_addon_template_path', function($path) {
            return 'woocommerce/excursion-addon/';
        });
        
        // Filter fields display in the addon
        add_filter('excursion_addon_variation_fields', function($fields) {
            // Customize variation fields display
            return $fields;
        });
    }
}
add_action('plugins_loaded', 'life_travel_excursion_addon_integration');

/**
 * Support for TranslatePress WooCommerce integration
 * Ensures proper translation of excursion product metadata
 */
function life_travel_translatepress_woocommerce_support() {
    // Skip if TranslatePress is not active
    if (!function_exists('trp_translate_gettext')) {
        return;
    }
    
    // Make product meta translatable
    add_filter('trp_translatable_custom_post_meta', 'life_travel_translatable_excursion_meta', 10, 2);
}
add_action('plugins_loaded', 'life_travel_translatepress_woocommerce_support', 20);

/**
 * Define translatable meta fields for excursion products
 */
function life_travel_translatable_excursion_meta($metas, $post_id) {
    // Excursion-specific fields that should be translatable
    $excursion_metas = array(
        '_price_per_person',
        '_excursion_description',
        '_excursion_details',
        '_excursion_included',
        '_excursion_not_included',
        '_excursion_itinerary',
        '_excursion_location_description'
    );
    
    return array_merge($metas, $excursion_metas);
}

/**
 * Performance optimizations
 * 
 * @since 1.0.0
 * @modified 1.1.0 - Ajout d'optimisations pour les connexions à faible bande passante
 * @modified 1.2.0 - Support pour TranslatePress
 */
function life_travel_performance_enhancements() {
    // Enable WebP image delivery if supported
    add_filter('wp_calculate_image_srcset_meta', 'life_travel_enable_webp_srcset');
    
    // Disable emoji scripts
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    
    // Remove unnecessary meta tags
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('wp_head', 'feed_links_extra', 3);
    
    // Optimiser le chargement des ressources JS
    add_filter('script_loader_tag', 'life_travel_add_defer_attribute', 10, 2);
    
    // Activer GZIP si disponible mais non activé
    life_travel_check_enable_gzip();
    
    // Optimiser le chargement des images
    add_filter('wp_get_attachment_image_attributes', 'life_travel_lazy_load_images', 10, 3);
    
    // Définir l'en-tête Cache-Control pour les ressources statiques
    add_action('wp_enqueue_scripts', 'life_travel_set_cache_headers', 999);
}
add_action('init', 'life_travel_performance_enhancements');

/**
 * Enable WebP in srcset when available
 *
 * @param array $image_meta Image metadata
 * @return array Modified image metadata
 */
function life_travel_enable_webp_srcset($image_meta) {
    if (function_exists('imagewebp')) {
        $image_meta['webp_enabled'] = true;
    }
    return $image_meta;
}

/**
 * Ajoute l'attribut defer aux balises script pour améliorer le chargement
 * 
 * @param string $tag La balise script
 * @param string $handle L'identifiant du script
 * @return string La balise script modifiée
 */
function life_travel_add_defer_attribute($tag, $handle) {
    // Ne pas différer les scripts jQuery ou qui en dépendent
    if (strpos($handle, 'jquery') !== false) {
        return $tag;
    }
    
    // Exclure certains scripts critiques
    $critical_scripts = array('woocommerce-js', 'wc-cart-fragments');
    if (in_array($handle, $critical_scripts)) {
        return $tag;
    }
    
    // Logger les scripts différés pour le débogage (mode développement uniquement)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Script différé : ' . $handle);
    }
    
    // Ajouter l'attribut defer
    return str_replace(' src', ' defer src', $tag);
}

/**
 * Vérifie et active la compression GZIP si non activée
 */
function life_travel_check_enable_gzip() {
    // Vérifier si nous sommes dans l'environnement Apache
    if (function_exists('apache_get_modules')) {
        if (!in_array('mod_deflate', apache_get_modules())) {
            // Logger l'avertissement pour les administrateurs
            if (current_user_can('administrator') && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Life Travel: mod_deflate non activé dans Apache. La compression GZIP n\'est pas disponible.');
            }
        }
    }
}

/**
 * Optimise le chargement des images avec lazy loading et tailles appropriées
 * 
 * @param array $attr Attributs de l'image
 * @param WP_Post $attachment L'objet pièce jointe
 * @param mixed $size La taille demandée
 * @return array Attributs modifiés
 */
function life_travel_lazy_load_images($attr, $attachment, $size) {
    // Ajouter le lazy loading natif pour les navigateurs modernes
    if (!isset($attr['loading'])) {
        $attr['loading'] = 'lazy';
    }
    
    // Ajouter des indices de taille pour améliorer le CLS (Cumulative Layout Shift)
    if (isset($attachment->ID)) {
        $metadata = wp_get_attachment_metadata($attachment->ID);
        if (!empty($metadata) && isset($metadata['width']) && isset($metadata['height'])) {
            // Calculer les dimensions en fonction de la taille demandée
            $dimensions = image_get_intermediate_size($attachment->ID, $size);
            if ($dimensions) {
                $attr['width'] = $dimensions['width'];
                $attr['height'] = $dimensions['height'];
            } else {
                $attr['width'] = $metadata['width'];
                $attr['height'] = $metadata['height'];
            }
        }
    }
    
    return $attr;
}

/**
 * Configure les en-têtes de cache pour les ressources statiques
 */
function life_travel_set_cache_headers() {
    // Ne pas définir les en-têtes pour les administrateurs (facilite le développement)
    if (current_user_can('administrator') && !isset($_GET['cache_test'])) {
        return;
    }
    
    $cache_time = 604800; // 1 semaine en secondes
    
    // Définir Cache-Control pour les ressources statiques
    header('Cache-Control: public, max-age=' . $cache_time);
}
