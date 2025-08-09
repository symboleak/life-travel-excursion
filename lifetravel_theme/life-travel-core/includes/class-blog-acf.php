<?php
/**
 * Blog ACF Fields Configuration for Life Travel
 *
 * @package Life_Travel_Core
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Blog ACF Fields Class
 */
class Life_Travel_Core_Blog_ACF {
    /**
     * Instance
     * 
     * @var Life_Travel_Core_Blog_ACF|null
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
        // Check if ACF plugin is active
        if (class_exists('ACF')) {
            add_action('acf/init', array($this, 'register_blog_field_groups'));
        }
    }

    /**
     * Register ACF field groups for blog posts
     */
    public function register_blog_field_groups() {
        // Excursion Blog Post Fields
        acf_add_local_field_group(array(
            'key' => 'group_excursion_blog',
            'title' => __('Détails du Carnet de Voyage', 'life-travel-core'),
            'fields' => array(
                array(
                    'key' => 'field_excursion_video',
                    'label' => __('Vidéo de l\'excursion', 'life-travel-core'),
                    'name' => 'excursion_video',
                    'type' => 'url',
                    'instructions' => __('URL YouTube, Vimeo ou fichier MP4 (max 50MB)', 'life-travel-core'),
                    'required' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'placeholder' => 'https://',
                ),
                array(
                    'key' => 'field_excursion_public_gallery',
                    'label' => __('Galerie publique', 'life-travel-core'),
                    'name' => 'excursion_public_gallery',
                    'type' => 'gallery',
                    'instructions' => __('Images visibles par tous les visiteurs', 'life-travel-core'),
                    'required' => 0,
                    'min' => 0,
                    'max' => 15,
                    'insert' => 'append',
                    'library' => 'all',
                    'min_width' => 0,
                    'min_height' => 0,
                    'min_size' => 0,
                    'max_width' => 0,
                    'max_height' => 0,
                    'max_size' => 2,
                    'mime_types' => 'jpg, jpeg, png, webp',
                ),
                array(
                    'key' => 'field_excursion_exclusive_gallery',
                    'label' => __('Galerie exclusive', 'life-travel-core'),
                    'name' => 'excursion_exclusive_gallery',
                    'type' => 'gallery',
                    'instructions' => __('Images visibles uniquement par les participants à l\'excursion', 'life-travel-core'),
                    'required' => 0,
                    'min' => 0,
                    'max' => 50,
                    'insert' => 'append',
                    'library' => 'all',
                    'min_width' => 0,
                    'min_height' => 0,
                    'min_size' => 0,
                    'max_width' => 0,
                    'max_height' => 0,
                    'max_size' => 5,
                    'mime_types' => 'jpg, jpeg, png, webp',
                ),
                array(
                    'key' => 'field_excursion_private_notes',
                    'label' => __('Notes privées', 'life-travel-core'),
                    'name' => 'excursion_private_notes',
                    'type' => 'textarea',
                    'instructions' => __('Notes privées visibles uniquement par les administrateurs', 'life-travel-core'),
                    'required' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'placeholder' => '',
                    'maxlength' => '',
                    'rows' => 4,
                    'new_lines' => 'wpautop',
                ),
                array(
                    'key' => 'field_excursion_date_completed',
                    'label' => __('Date de l\'excursion', 'life-travel-core'),
                    'name' => 'excursion_date_completed',
                    'type' => 'date_picker',
                    'instructions' => __('Date à laquelle l\'excursion a eu lieu', 'life-travel-core'),
                    'required' => 0,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'd/m/Y',
                    'first_day' => 1,
                ),
                array(
                    'key' => 'field_excursion_highlights',
                    'label' => __('Points forts', 'life-travel-core'),
                    'name' => 'excursion_blog_highlights',
                    'type' => 'repeater',
                    'instructions' => __('Principaux points forts de cette excursion', 'life-travel-core'),
                    'required' => 0,
                    'min' => 0,
                    'max' => 6,
                    'layout' => 'table',
                    'button_label' => __('Ajouter un point fort', 'life-travel-core'),
                    'sub_fields' => array(
                        array(
                            'key' => 'field_excursion_highlight_text',
                            'label' => __('Description', 'life-travel-core'),
                            'name' => 'text',
                            'type' => 'text',
                            'required' => 1,
                        ),
                        array(
                            'key' => 'field_excursion_highlight_icon',
                            'label' => __('Icône', 'life-travel-core'),
                            'name' => 'icon',
                            'type' => 'select',
                            'required' => 0,
                            'choices' => array(
                                'mountain' => __('Montagne', 'life-travel-core'),
                                'beach' => __('Plage', 'life-travel-core'),
                                'forest' => __('Forêt', 'life-travel-core'),
                                'waterfall' => __('Cascade', 'life-travel-core'),
                                'wildlife' => __('Faune', 'life-travel-core'),
                                'culture' => __('Culture', 'life-travel-core'),
                                'food' => __('Gastronomie', 'life-travel-core'),
                                'photo' => __('Photographie', 'life-travel-core'),
                                'adventure' => __('Aventure', 'life-travel-core'),
                                'history' => __('Histoire', 'life-travel-core'),
                            ),
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'post',
                    ),
                    array(
                        'param' => 'post_category',
                        'operator' => '==',
                        'value' => 'category:excursion',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => __('Champs pour les articles du blog "Carnet de Voyages"', 'life-travel-core'),
        ));
    }
}

// Initialize Blog ACF Fields class
Life_Travel_Core_Blog_ACF::instance();
