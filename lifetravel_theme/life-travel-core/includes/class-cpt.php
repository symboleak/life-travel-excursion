<?php
/**
 * Custom Post Types for Life Travel
 *
 * @package Life_Travel_Core
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CPT Class
 */
class Life_Travel_Core_CPT {
    /**
     * Instance
     * 
     * @var Life_Travel_Core_CPT|null
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
    public function __construct() {
        add_action('init', array($this, 'register_post_types'), 5);
        add_action('admin_menu', array($this, 'adjust_admin_menu'));
        add_filter('enter_title_here', array($this, 'custom_enter_title'));
        add_filter('post_updated_messages', array($this, 'custom_updated_messages'));
    }

    /**
     * Register Custom Post Types
     */
    public static function register_post_types() {
        if (!is_blog_installed() || post_type_exists('excursion_custom')) {
            return;
        }

        // Excursion personnalisée (Custom Excursion)
        $labels = array(
            'name'                  => _x('Excursions personnalisées', 'Post type general name', 'life-travel-core'),
            'singular_name'         => _x('Excursion personnalisée', 'Post type singular name', 'life-travel-core'),
            'menu_name'             => _x('Excursions sur mesure', 'Admin Menu text', 'life-travel-core'),
            'name_admin_bar'        => _x('Excursion personnalisée', 'Add New on Toolbar', 'life-travel-core'),
            'add_new'               => __('Ajouter', 'life-travel-core'),
            'add_new_item'          => __('Ajouter une excursion personnalisée', 'life-travel-core'),
            'new_item'              => __('Nouvelle excursion', 'life-travel-core'),
            'edit_item'             => __('Modifier l\'excursion', 'life-travel-core'),
            'view_item'             => __('Voir l\'excursion', 'life-travel-core'),
            'all_items'             => __('Toutes les excursions', 'life-travel-core'),
            'search_items'          => __('Rechercher des excursions', 'life-travel-core'),
            'not_found'             => __('Aucune excursion trouvée.', 'life-travel-core'),
            'not_found_in_trash'    => __('Aucune excursion trouvée dans la corbeille.', 'life-travel-core'),
            'featured_image'        => __('Image principale de l\'excursion', 'life-travel-core'),
            'set_featured_image'    => __('Définir l\'image principale', 'life-travel-core'),
            'remove_featured_image' => __('Supprimer l\'image principale', 'life-travel-core'),
            'use_featured_image'    => __('Utiliser comme image principale', 'life-travel-core'),
            'archives'              => __('Archives des excursions', 'life-travel-core'),
            'attributes'            => __('Attributs de l\'excursion', 'life-travel-core'),
            'insert_into_item'      => __('Insérer dans l\'excursion', 'life-travel-core'),
            'uploaded_to_this_item' => __('Téléversé pour cette excursion', 'life-travel-core'),
            'filter_items_list'     => __('Filtrer la liste des excursions', 'life-travel-core'),
            'items_list_navigation' => __('Navigation de la liste des excursions', 'life-travel-core'),
            'items_list'            => __('Liste des excursions', 'life-travel-core'),
        );

        $args = array(
            'labels'             => $labels,
            'description'        => __('Excursions personnalisables sur mesure', 'life-travel-core'),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true, // Nécessaire pour l'éditeur Gutenberg
            'query_var'          => true,
            'rewrite'            => array('slug' => 'excursion-sur-mesure'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-location-alt',
            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'comments'),
            'template'           => array(
                array('core/heading', array(
                    'content' => __('Description de l\'excursion', 'life-travel-core'),
                    'level' => 2
                )),
                array('core/paragraph', array(
                    'placeholder' => __('Décrivez cette expérience unique...', 'life-travel-core')
                )),
                array('life-travel-core/hero-banner', array()),
                array('core/columns', array(), array(
                    array('core/column', array(), array(
                        array('core/heading', array(
                            'content' => __('Points forts', 'life-travel-core'),
                            'level' => 3
                        )),
                        array('core/list', array())
                    )),
                    array('core/column', array(), array(
                        array('core/heading', array(
                            'content' => __('Informations pratiques', 'life-travel-core'),
                            'level' => 3
                        )),
                        array('core/paragraph', array())
                    ))
                )),
                array('life-travel-core/vote-module', array())
            ),
            'template_lock'      => 'false', // Peut être modifié mais suggère une structure
        );

        register_post_type('excursion_custom', $args);

        // Add REST API support
        add_post_type_support('excursion_custom', 'custom-fields');
    }

    /**
     * Adjust admin menu
     */
    public function adjust_admin_menu() {
        // Move custom excursions under WooCommerce menu for better organization
        if (class_exists('WooCommerce')) {
            // Remove from top level
            remove_menu_page('edit.php?post_type=excursion_custom');
            
            // Add as submenu under WooCommerce
            add_submenu_page(
                'woocommerce',
                __('Excursions personnalisées', 'life-travel-core'),
                __('Excursions sur mesure', 'life-travel-core'),
                'manage_woocommerce',
                'edit.php?post_type=excursion_custom',
                null,
                15
            );
        }
    }

    /**
     * Custom title placeholder
     */
    public function custom_enter_title($title) {
        $screen = get_current_screen();
        
        if ('excursion_custom' === $screen->post_type) {
            $title = __('Nom de l\'excursion sur mesure', 'life-travel-core');
        }
        
        return $title;
    }

    /**
     * Custom update messages
     */
    public function custom_updated_messages($messages) {
        global $post;

        $post_ID = isset($post->ID) ? $post->ID : 0;
        $post_type = get_post_type($post_ID);

        if ('excursion_custom' === $post_type) {
            $messages['excursion_custom'] = array(
                0  => '', // Unused. Messages start at index 1.
                1  => __('Excursion mise à jour.', 'life-travel-core'),
                2  => __('Champ mis à jour.', 'life-travel-core'),
                3  => __('Champ supprimé.', 'life-travel-core'),
                4  => __('Excursion mise à jour.', 'life-travel-core'),
                5  => isset($_GET['revision']) ? sprintf(__('Excursion restaurée à la révision du %s', 'life-travel-core'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
                6  => __('Excursion publiée.', 'life-travel-core'),
                7  => __('Excursion enregistrée.', 'life-travel-core'),
                8  => __('Excursion envoyée.', 'life-travel-core'),
                9  => sprintf(__('Excursion programmée pour : <strong>%1$s</strong>.', 'life-travel-core'), date_i18n(__('d/m/Y à H:i', 'life-travel-core'), strtotime($post->post_date))),
                10 => __('Brouillon d\'excursion mis à jour.', 'life-travel-core'),
            );
        }

        return $messages;
    }
}

// Initialize CPT class
Life_Travel_Core_CPT::instance();
