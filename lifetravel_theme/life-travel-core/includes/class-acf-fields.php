<?php
/**
 * ACF Fields Configuration for Life Travel
 *
 * @package Life_Travel_Core
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ACF Fields Class
 */
class Life_Travel_Core_ACF_Fields {
    /**
     * Instance
     * 
     * @var Life_Travel_Core_ACF_Fields|null
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
            add_action('acf/init', array($this, 'register_field_groups'));
            add_action('acf/include_fields', array($this, 'register_json_point'));
        } else {
            // Add admin notice if ACF is not active
            add_action('admin_notices', array($this, 'acf_missing_notice'));
        }
    }

    /**
     * Register ACF field groups
     */
    public function register_field_groups() {
        // Excursion Details Field Group
        acf_add_local_field_group(array(
            'key' => 'group_excursion_details',
            'title' => __('Détails de l\'excursion', 'life-travel-core'),
            'fields' => array(
                array(
                    'key' => 'field_excursion_duration',
                    'label' => __('Durée', 'life-travel-core'),
                    'name' => 'excursion_duration',
                    'type' => 'text',
                    'instructions' => __('Durée de l\'excursion (ex: 2 jours / 1 nuit)', 'life-travel-core'),
                    'required' => 1,
                    'wrapper' => array(
                        'width' => '50',
                        'class' => '',
                        'id' => '',
                    ),
                ),
                array(
                    'key' => 'field_excursion_difficulty',
                    'label' => __('Niveau de difficulté', 'life-travel-core'),
                    'name' => 'excursion_difficulty',
                    'type' => 'select',
                    'instructions' => __('Sélectionnez le niveau de difficulté', 'life-travel-core'),
                    'required' => 1,
                    'choices' => array(
                        'facile' => __('Facile - Accessible à tous', 'life-travel-core'),
                        'modere' => __('Modéré - Bonne condition physique', 'life-travel-core'),
                        'difficile' => __('Difficile - Expérience nécessaire', 'life-travel-core'),
                        'extreme' => __('Extrême - Pour experts', 'life-travel-core'),
                    ),
                    'default_value' => 'facile',
                    'wrapper' => array(
                        'width' => '50',
                        'class' => '',
                        'id' => '',
                    ),
                ),
                array(
                    'key' => 'field_excursion_highlights',
                    'label' => __('Points forts', 'life-travel-core'),
                    'name' => 'excursion_highlights',
                    'type' => 'repeater',
                    'instructions' => __('Ajoutez les points forts de l\'excursion', 'life-travel-core'),
                    'required' => 0,
                    'min' => 0,
                    'max' => 6,
                    'layout' => 'block',
                    'button_label' => __('Ajouter un point fort', 'life-travel-core'),
                    'sub_fields' => array(
                        array(
                            'key' => 'field_excursion_highlight_text',
                            'label' => __('Point fort', 'life-travel-core'),
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
                                'camera' => __('Photo', 'life-travel-core'),
                                'boat' => __('Bateau', 'life-travel-core'),
                                'hiking' => __('Randonnée', 'life-travel-core'),
                            ),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_excursion_gallery',
                    'label' => __('Galerie d\'images', 'life-travel-core'),
                    'name' => 'excursion_gallery',
                    'type' => 'gallery',
                    'instructions' => __('Ajoutez des images supplémentaires pour l\'excursion', 'life-travel-core'),
                    'required' => 0,
                    'min' => 0,
                    'max' => 10,
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
                    'key' => 'field_excursion_location',
                    'label' => __('Emplacement', 'life-travel-core'),
                    'name' => 'excursion_location',
                    'type' => 'google_map',
                    'instructions' => __('Sélectionnez l\'emplacement principal de l\'excursion', 'life-travel-core'),
                    'required' => 0,
                    'center_lat' => '4.0429408',
                    'center_lng' => '9.706203',
                    'zoom' => 10,
                    'height' => 400,
                ),
                array(
                    'key' => 'field_excursion_included',
                    'label' => __('Ce qui est inclus', 'life-travel-core'),
                    'name' => 'excursion_included',
                    'type' => 'repeater',
                    'instructions' => __('Ajoutez les éléments inclus dans l\'excursion', 'life-travel-core'),
                    'required' => 0,
                    'min' => 0,
                    'max' => 10,
                    'layout' => 'table',
                    'button_label' => __('Ajouter un élément inclus', 'life-travel-core'),
                    'sub_fields' => array(
                        array(
                            'key' => 'field_excursion_included_item',
                            'label' => __('Élément inclus', 'life-travel-core'),
                            'name' => 'item',
                            'type' => 'text',
                            'required' => 1,
                        ),
                    ),
                ),
                array(
                    'key' => 'field_excursion_not_included',
                    'label' => __('Ce qui n\'est pas inclus', 'life-travel-core'),
                    'name' => 'excursion_not_included',
                    'type' => 'repeater',
                    'instructions' => __('Ajoutez les éléments non inclus dans l\'excursion', 'life-travel-core'),
                    'required' => 0,
                    'min' => 0,
                    'max' => 10,
                    'layout' => 'table',
                    'button_label' => __('Ajouter un élément non inclus', 'life-travel-core'),
                    'sub_fields' => array(
                        array(
                            'key' => 'field_excursion_not_included_item',
                            'label' => __('Élément non inclus', 'life-travel-core'),
                            'name' => 'item',
                            'type' => 'text',
                            'required' => 1,
                        ),
                    ),
                ),
                array(
                    'key' => 'field_excursion_itinerary',
                    'label' => __('Itinéraire', 'life-travel-core'),
                    'name' => 'excursion_itinerary',
                    'type' => 'repeater',
                    'instructions' => __('Ajoutez les étapes de l\'itinéraire', 'life-travel-core'),
                    'required' => 0,
                    'min' => 0,
                    'max' => 20,
                    'layout' => 'block',
                    'button_label' => __('Ajouter une étape', 'life-travel-core'),
                    'sub_fields' => array(
                        array(
                            'key' => 'field_excursion_itinerary_day',
                            'label' => __('Jour / Titre', 'life-travel-core'),
                            'name' => 'day',
                            'type' => 'text',
                            'required' => 1,
                        ),
                        array(
                            'key' => 'field_excursion_itinerary_description',
                            'label' => __('Description', 'life-travel-core'),
                            'name' => 'description',
                            'type' => 'textarea',
                            'required' => 1,
                            'rows' => 4,
                        ),
                        array(
                            'key' => 'field_excursion_itinerary_image',
                            'label' => __('Image (optionnelle)', 'life-travel-core'),
                            'name' => 'image',
                            'type' => 'image',
                            'required' => 0,
                            'return_format' => 'array',
                            'preview_size' => 'medium',
                            'library' => 'all',
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'excursion_custom',
                    ),
                ),
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'product',
                    ),
                    array(
                        'param' => 'product_type',
                        'operator' => '==',
                        'value' => 'simple',
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
            'description' => __('Informations détaillées pour les excursions', 'life-travel-core'),
        ));

        // Calendar Settings Field Group
        acf_add_local_field_group(array(
            'key' => 'group_calendar_settings',
            'title' => __('Paramètres du calendrier', 'life-travel-core'),
            'fields' => array(
                array(
                    'key' => 'field_excursion_date',
                    'label' => __('Date de l\'excursion', 'life-travel-core'),
                    'name' => 'excursion_date',
                    'type' => 'date_picker',
                    'instructions' => __('Sélectionnez la date de l\'excursion', 'life-travel-core'),
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Ymd',
                    'first_day' => 1,
                ),
                array(
                    'key' => 'field_excursion_spots',
                    'label' => __('Nombre de places', 'life-travel-core'),
                    'name' => 'excursion_spots',
                    'type' => 'number',
                    'instructions' => __('Nombre total de places disponibles', 'life-travel-core'),
                    'required' => 1,
                    'min' => 1,
                    'max' => 100,
                    'step' => 1,
                    'default_value' => 10,
                ),
                array(
                    'key' => 'field_excursion_reserved',
                    'label' => __('Places réservées', 'life-travel-core'),
                    'name' => 'excursion_reserved',
                    'type' => 'number',
                    'instructions' => __('Nombre de places déjà réservées (mis à jour automatiquement)', 'life-travel-core'),
                    'required' => 0,
                    'min' => 0,
                    'max' => 100,
                    'step' => 1,
                    'default_value' => 0,
                    'readonly' => 1,
                ),
                array(
                    'key' => 'field_excursion_departure_time',
                    'label' => __('Heure de départ', 'life-travel-core'),
                    'name' => 'excursion_departure_time',
                    'type' => 'time_picker',
                    'instructions' => __('Heure de départ de l\'excursion', 'life-travel-core'),
                    'required' => 1,
                    'display_format' => 'H:i',
                    'return_format' => 'H:i',
                ),
                array(
                    'key' => 'field_excursion_meeting_point',
                    'label' => __('Point de rendez-vous', 'life-travel-core'),
                    'name' => 'excursion_meeting_point',
                    'type' => 'text',
                    'instructions' => __('Lieu de rendez-vous pour le départ', 'life-travel-core'),
                    'required' => 1,
                ),
                array(
                    'key' => 'field_excursion_featured_calendar',
                    'label' => __('Mettre en avant dans le calendrier', 'life-travel-core'),
                    'name' => 'excursion_featured_calendar',
                    'type' => 'true_false',
                    'instructions' => __('Mettre cette excursion en évidence dans le calendrier', 'life-travel-core'),
                    'required' => 0,
                    'default_value' => 0,
                    'ui' => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'product',
                    ),
                ),
            ),
            'menu_order' => 10,
            'position' => 'side',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => __('Paramètres pour l\'affichage dans le calendrier', 'life-travel-core'),
        ));

        // Vote Module Settings Field Group
        acf_add_local_field_group(array(
            'key' => 'group_vote_settings',
            'title' => __('Paramètres du module de vote', 'life-travel-core'),
            'fields' => array(
                array(
                    'key' => 'field_vote_question',
                    'label' => __('Question du vote', 'life-travel-core'),
                    'name' => 'vote_question',
                    'type' => 'text',
                    'instructions' => __('Question à poser aux visiteurs', 'life-travel-core'),
                    'required' => 1,
                    'default_value' => __('Quelle excursion souhaitez-vous pour le mois prochain ?', 'life-travel-core'),
                ),
                array(
                    'key' => 'field_vote_options',
                    'label' => __('Options de vote', 'life-travel-core'),
                    'name' => 'vote_options',
                    'type' => 'repeater',
                    'instructions' => __('Ajoutez les options de vote', 'life-travel-core'),
                    'required' => 1,
                    'min' => 2,
                    'max' => 6,
                    'layout' => 'block',
                    'button_label' => __('Ajouter une option', 'life-travel-core'),
                    'sub_fields' => array(
                        array(
                            'key' => 'field_vote_option_title',
                            'label' => __('Titre de l\'option', 'life-travel-core'),
                            'name' => 'title',
                            'type' => 'text',
                            'required' => 1,
                        ),
                        array(
                            'key' => 'field_vote_option_description',
                            'label' => __('Description', 'life-travel-core'),
                            'name' => 'description',
                            'type' => 'textarea',
                            'required' => 0,
                            'rows' => 3,
                        ),
                        array(
                            'key' => 'field_vote_option_image',
                            'label' => __('Image', 'life-travel-core'),
                            'name' => 'image',
                            'type' => 'image',
                            'required' => 0,
                            'return_format' => 'array',
                            'preview_size' => 'medium',
                            'library' => 'all',
                        ),
                    ),
                ),
                array(
                    'key' => 'field_vote_end_date',
                    'label' => __('Date de fin du vote', 'life-travel-core'),
                    'name' => 'vote_end_date',
                    'type' => 'date_picker',
                    'instructions' => __('Date de clôture du vote', 'life-travel-core'),
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Ymd',
                    'first_day' => 1,
                ),
                array(
                    'key' => 'field_vote_results_visible',
                    'label' => __('Résultats visibles', 'life-travel-core'),
                    'name' => 'vote_results_visible',
                    'type' => 'true_false',
                    'instructions' => __('Afficher les résultats en temps réel aux visiteurs', 'life-travel-core'),
                    'required' => 0,
                    'default_value' => 1,
                    'ui' => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'life-travel-settings',
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
            'description' => __('Paramètres pour le module de vote', 'life-travel-core'),
        ));

        // Slider Settings Field Group
        acf_add_local_field_group(array(
            'key' => 'group_slider_settings',
            'title' => __('Paramètres du slider', 'life-travel-core'),
            'fields' => array(
                array(
                    'key' => 'field_slider_title',
                    'label' => __('Titre du slider', 'life-travel-core'),
                    'name' => 'slider_title',
                    'type' => 'text',
                    'instructions' => __('Titre affiché au-dessus du slider', 'life-travel-core'),
                    'required' => 0,
                    'default_value' => __('Excursions du mois', 'life-travel-core'),
                ),
                array(
                    'key' => 'field_slider_subtitle',
                    'label' => __('Sous-titre du slider', 'life-travel-core'),
                    'name' => 'slider_subtitle',
                    'type' => 'text',
                    'instructions' => __('Sous-titre affiché sous le titre principal', 'life-travel-core'),
                    'required' => 0,
                ),
                array(
                    'key' => 'field_slider_excursions',
                    'label' => __('Excursions à afficher', 'life-travel-core'),
                    'name' => 'slider_excursions',
                    'type' => 'relationship',
                    'instructions' => __('Sélectionnez les excursions à afficher dans le slider', 'life-travel-core'),
                    'required' => 1,
                    'post_type' => array('product'),
                    'taxonomy' => '',
                    'filters' => array('search'),
                    'elements' => array('featured_image'),
                    'min' => 3,
                    'max' => 9,
                    'return_format' => 'id',
                ),
                array(
                    'key' => 'field_slider_style',
                    'label' => __('Style du slider', 'life-travel-core'),
                    'name' => 'slider_style',
                    'type' => 'select',
                    'instructions' => __('Choisissez le style visuel du slider', 'life-travel-core'),
                    'required' => 0,
                    'choices' => array(
                        'standard' => __('Standard', 'life-travel-core'),
                        'compact' => __('Compact', 'life-travel-core'),
                        'hero' => __('Hero (grand format)', 'life-travel-core'),
                    ),
                    'default_value' => 'standard',
                ),
                array(
                    'key' => 'field_slider_cta_text',
                    'label' => __('Texte du bouton CTA', 'life-travel-core'),
                    'name' => 'slider_cta_text',
                    'type' => 'text',
                    'instructions' => __('Texte pour le bouton d\'appel à l\'action (laisser vide pour ne pas afficher)', 'life-travel-core'),
                    'required' => 0,
                    'default_value' => __('Voir toutes nos excursions', 'life-travel-core'),
                ),
                array(
                    'key' => 'field_slider_cta_link',
                    'label' => __('Lien du bouton CTA', 'life-travel-core'),
                    'name' => 'slider_cta_link',
                    'type' => 'page_link',
                    'instructions' => __('Lien pour le bouton d\'appel à l\'action', 'life-travel-core'),
                    'required' => 0,
                    'post_type' => array('page'),
                    'allow_null' => 1,
                    'multiple' => 0,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'block',
                        'operator' => '==',
                        'value' => 'life-travel-core/month-slider',
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
            'description' => __('Paramètres pour le slider des excursions du mois', 'life-travel-core'),
        ));
    }

    /**
     * Register ACF JSON loading point
     */
    public function register_json_point() {
        acf_update_setting('save_json', LIFE_TRAVEL_CORE_DIR . 'acf-json');
        acf_append_setting('load_json', LIFE_TRAVEL_CORE_DIR . 'acf-json');
    }

    /**
     * ACF missing notice
     */
    public function acf_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo sprintf(
            __('Le plugin %1$s nécessite Advanced Custom Fields (ACF). Veuillez %2$sinstaller et activer ACF%3$s.', 'life-travel-core'),
            '<strong>Life Travel Core</strong>',
            '<a href="' . admin_url('plugin-install.php?tab=search&s=advanced+custom+fields') . '">',
            '</a>'
        );
        echo '</p></div>';
    }
}

// Initialize ACF Fields class
Life_Travel_Core_ACF_Fields::instance();
