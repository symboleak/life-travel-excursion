<?php
/**
 * Custom Taxonomies for Life Travel
 *
 * @package Life_Travel_Core
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Taxonomies Class
 */
class Life_Travel_Core_Taxonomies {
    /**
     * Instance
     * 
     * @var Life_Travel_Core_Taxonomies|null
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
        add_action('init', array($this, 'register_taxonomies'), 5);
        add_filter('term_updated_messages', array($this, 'custom_updated_messages'));
    }

    /**
     * Register Custom Taxonomies
     */
    public static function register_taxonomies() {
        if (!is_blog_installed()) {
            return;
        }

        // Région (Region)
        $labels = array(
            'name'                       => _x('Régions', 'taxonomy general name', 'life-travel-core'),
            'singular_name'              => _x('Région', 'taxonomy singular name', 'life-travel-core'),
            'search_items'               => __('Rechercher des régions', 'life-travel-core'),
            'popular_items'              => __('Régions populaires', 'life-travel-core'),
            'all_items'                  => __('Toutes les régions', 'life-travel-core'),
            'parent_item'                => __('Région parente', 'life-travel-core'),
            'parent_item_colon'          => __('Région parente :', 'life-travel-core'),
            'edit_item'                  => __('Modifier la région', 'life-travel-core'),
            'update_item'                => __('Mettre à jour la région', 'life-travel-core'),
            'add_new_item'               => __('Ajouter une région', 'life-travel-core'),
            'new_item_name'              => __('Nom de la nouvelle région', 'life-travel-core'),
            'separate_items_with_commas' => __('Séparer les régions avec des virgules', 'life-travel-core'),
            'add_or_remove_items'        => __('Ajouter ou supprimer des régions', 'life-travel-core'),
            'choose_from_most_used'      => __('Choisir parmi les régions les plus utilisées', 'life-travel-core'),
            'not_found'                  => __('Aucune région trouvée.', 'life-travel-core'),
            'menu_name'                  => __('Régions', 'life-travel-core'),
            'back_to_items'              => __('Retour aux régions', 'life-travel-core'),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'region'),
        );

        register_taxonomy('excursion_region', array('excursion_custom', 'product'), $args);

        // Type d'activité (Activity Type)
        $labels = array(
            'name'                       => _x('Types d\'activité', 'taxonomy general name', 'life-travel-core'),
            'singular_name'              => _x('Type d\'activité', 'taxonomy singular name', 'life-travel-core'),
            'search_items'               => __('Rechercher des types d\'activité', 'life-travel-core'),
            'popular_items'              => __('Types d\'activité populaires', 'life-travel-core'),
            'all_items'                  => __('Tous les types d\'activité', 'life-travel-core'),
            'parent_item'                => __('Type d\'activité parent', 'life-travel-core'),
            'parent_item_colon'          => __('Type d\'activité parent :', 'life-travel-core'),
            'edit_item'                  => __('Modifier le type d\'activité', 'life-travel-core'),
            'update_item'                => __('Mettre à jour le type d\'activité', 'life-travel-core'),
            'add_new_item'               => __('Ajouter un type d\'activité', 'life-travel-core'),
            'new_item_name'              => __('Nom du nouveau type d\'activité', 'life-travel-core'),
            'separate_items_with_commas' => __('Séparer les types d\'activité avec des virgules', 'life-travel-core'),
            'add_or_remove_items'        => __('Ajouter ou supprimer des types d\'activité', 'life-travel-core'),
            'choose_from_most_used'      => __('Choisir parmi les types d\'activité les plus utilisés', 'life-travel-core'),
            'not_found'                  => __('Aucun type d\'activité trouvé.', 'life-travel-core'),
            'menu_name'                  => __('Types d\'activité', 'life-travel-core'),
            'back_to_items'              => __('Retour aux types d\'activité', 'life-travel-core'),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'activite'),
        );

        register_taxonomy('excursion_activity', array('excursion_custom', 'product'), $args);

        // Durée (Duration)
        $labels = array(
            'name'                       => _x('Durées', 'taxonomy general name', 'life-travel-core'),
            'singular_name'              => _x('Durée', 'taxonomy singular name', 'life-travel-core'),
            'search_items'               => __('Rechercher des durées', 'life-travel-core'),
            'popular_items'              => __('Durées populaires', 'life-travel-core'),
            'all_items'                  => __('Toutes les durées', 'life-travel-core'),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __('Modifier la durée', 'life-travel-core'),
            'update_item'                => __('Mettre à jour la durée', 'life-travel-core'),
            'add_new_item'               => __('Ajouter une durée', 'life-travel-core'),
            'new_item_name'              => __('Nom de la nouvelle durée', 'life-travel-core'),
            'separate_items_with_commas' => __('Séparer les durées avec des virgules', 'life-travel-core'),
            'add_or_remove_items'        => __('Ajouter ou supprimer des durées', 'life-travel-core'),
            'choose_from_most_used'      => __('Choisir parmi les durées les plus utilisées', 'life-travel-core'),
            'not_found'                  => __('Aucune durée trouvée.', 'life-travel-core'),
            'menu_name'                  => __('Durées', 'life-travel-core'),
            'back_to_items'              => __('Retour aux durées', 'life-travel-core'),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'duree'),
        );

        register_taxonomy('excursion_duration', array('excursion_custom', 'product'), $args);

        // Difficulté (Difficulty)
        $labels = array(
            'name'                       => _x('Niveaux de difficulté', 'taxonomy general name', 'life-travel-core'),
            'singular_name'              => _x('Niveau de difficulté', 'taxonomy singular name', 'life-travel-core'),
            'search_items'               => __('Rechercher des niveaux de difficulté', 'life-travel-core'),
            'popular_items'              => __('Niveaux de difficulté populaires', 'life-travel-core'),
            'all_items'                  => __('Tous les niveaux de difficulté', 'life-travel-core'),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __('Modifier le niveau de difficulté', 'life-travel-core'),
            'update_item'                => __('Mettre à jour le niveau de difficulté', 'life-travel-core'),
            'add_new_item'               => __('Ajouter un niveau de difficulté', 'life-travel-core'),
            'new_item_name'              => __('Nom du nouveau niveau de difficulté', 'life-travel-core'),
            'separate_items_with_commas' => __('Séparer les niveaux de difficulté avec des virgules', 'life-travel-core'),
            'add_or_remove_items'        => __('Ajouter ou supprimer des niveaux de difficulté', 'life-travel-core'),
            'choose_from_most_used'      => __('Choisir parmi les niveaux de difficulté les plus utilisés', 'life-travel-core'),
            'not_found'                  => __('Aucun niveau de difficulté trouvé.', 'life-travel-core'),
            'menu_name'                  => __('Difficulté', 'life-travel-core'),
            'back_to_items'              => __('Retour aux niveaux de difficulté', 'life-travel-core'),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'difficulte'),
        );

        register_taxonomy('excursion_difficulty', array('excursion_custom', 'product'), $args);
    }

    /**
     * Custom update messages
     */
    public function custom_updated_messages($messages) {
        $messages['excursion_region'] = array(
            0 => '',
            1 => __('Région ajoutée.', 'life-travel-core'),
            2 => __('Région supprimée.', 'life-travel-core'),
            3 => __('Région mise à jour.', 'life-travel-core'),
            4 => __('Région supprimée.', 'life-travel-core'),
            5 => __('Région mise à jour.', 'life-travel-core'),
            6 => __('Région ajoutée.', 'life-travel-core'),
        );

        $messages['excursion_activity'] = array(
            0 => '',
            1 => __('Type d\'activité ajouté.', 'life-travel-core'),
            2 => __('Type d\'activité supprimé.', 'life-travel-core'),
            3 => __('Type d\'activité mis à jour.', 'life-travel-core'),
            4 => __('Type d\'activité supprimé.', 'life-travel-core'),
            5 => __('Type d\'activité mis à jour.', 'life-travel-core'),
            6 => __('Type d\'activité ajouté.', 'life-travel-core'),
        );

        $messages['excursion_duration'] = array(
            0 => '',
            1 => __('Durée ajoutée.', 'life-travel-core'),
            2 => __('Durée supprimée.', 'life-travel-core'),
            3 => __('Durée mise à jour.', 'life-travel-core'),
            4 => __('Durée supprimée.', 'life-travel-core'),
            5 => __('Durée mise à jour.', 'life-travel-core'),
            6 => __('Durée ajoutée.', 'life-travel-core'),
        );

        $messages['excursion_difficulty'] = array(
            0 => '',
            1 => __('Niveau de difficulté ajouté.', 'life-travel-core'),
            2 => __('Niveau de difficulté supprimé.', 'life-travel-core'),
            3 => __('Niveau de difficulté mis à jour.', 'life-travel-core'),
            4 => __('Niveau de difficulté supprimé.', 'life-travel-core'),
            5 => __('Niveau de difficulté mis à jour.', 'life-travel-core'),
            6 => __('Niveau de difficulté ajouté.', 'life-travel-core'),
        );

        return $messages;
    }
}

// Initialize Taxonomies class
Life_Travel_Core_Taxonomies::instance();
