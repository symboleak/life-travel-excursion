<?php
/**
 * Gestionnaire de médias Life Travel
 * Ajoute des fonctionnalités de gestion des médias pour le thème Life Travel
 * 
 * @package Life Travel
 * @version 1.0
 */

defined('ABSPATH') || exit;

/**
 * Classe gestionnaire de médias
 */
class Life_Travel_Media_Manager {
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Ajouter un menu d'administration pour la gestion des médias
        add_action('admin_menu', array($this, 'add_media_menu'));
        
        // Enregistrer les réglages
        add_action('admin_init', array($this, 'register_media_settings'));
        
        // Ajouter les scripts d'administration
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Ajouter les supports de tailles d'image personnalisées
        add_action('after_setup_theme', array($this, 'add_image_sizes'));
        
        // Filtrer les médias pour les optimiser
        add_filter('wp_get_attachment_image_attributes', array($this, 'filter_image_attributes'), 10, 3);
        
        // Support des SVG dans WordPress
        add_filter('upload_mimes', array($this, 'allow_svg_upload'));
        add_filter('wp_check_filetype_and_ext', array($this, 'fix_svg_mime_type'), 10, 5);
    }
    
    /**
     * Ajouter un menu d'administration pour la gestion des médias
     */
    public function add_media_menu() {
        add_submenu_page(
            'themes.php',
            'Gestion des médias Life Travel',
            'Médias Life Travel',
            'manage_options',
            'life-travel-media',
            array($this, 'render_media_page')
        );
    }
    
    /**
     * Affichage de la page de gestion des médias
     */
    public function render_media_page() {
        ?>
        <div class="wrap">
            <h1>Gestion des médias Life Travel</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('life_travel_media_options');
                do_settings_sections('life-travel-media');
                submit_button('Enregistrer les modifications');
                ?>
            </form>
            
            <div class="media-uploader-section">
                <h2>Téléverser de nouveaux éléments visuels</h2>
                <p>Utilisez cette section pour téléverser rapidement des éléments visuels pour votre site.</p>
                
                <div class="media-categories">
                    <div class="media-category">
                        <h3>Logo du site</h3>
                        <div class="media-preview">
                            <?php 
                            $logo_id = get_option('life_travel_logo_id');
                            if ($logo_id) {
                                echo wp_get_attachment_image($logo_id, 'medium');
                            } else {
                                echo '<div class="no-image">Aucun logo défini</div>';
                            }
                            ?>
                        </div>
                        <button type="button" class="button upload-media-button" data-target="life_travel_logo_id">
                            Modifier le logo
                        </button>
                        <input type="hidden" name="life_travel_logo_id" id="life_travel_logo_id" value="<?php echo esc_attr(get_option('life_travel_logo_id')); ?>">
                    </div>
                    
                    <div class="media-category">
                        <h3>Image d'arrière-plan principale</h3>
                        <div class="media-preview">
                            <?php 
                            $bg_id = get_option('life_travel_main_background_id');
                            if ($bg_id) {
                                echo wp_get_attachment_image($bg_id, 'medium');
                            } else {
                                echo '<div class="no-image">Aucune image définie</div>';
                            }
                            ?>
                        </div>
                        <button type="button" class="button upload-media-button" data-target="life_travel_main_background_id">
                            Modifier l'arrière-plan
                        </button>
                        <input type="hidden" name="life_travel_main_background_id" id="life_travel_main_background_id" value="<?php echo esc_attr(get_option('life_travel_main_background_id')); ?>">
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enregistrement des réglages pour les médias
     */
    public function register_media_settings() {
        register_setting('life_travel_media_options', 'life_travel_logo_id');
        register_setting('life_travel_media_options', 'life_travel_main_background_id');
        register_setting('life_travel_media_options', 'life_travel_image_quality', array(
            'type' => 'integer',
            'default' => 85,
            'sanitize_callback' => 'absint',
        ));
        
        add_settings_section(
            'life_travel_media_general',
            'Paramètres généraux',
            array($this, 'render_general_section'),
            'life-travel-media'
        );
        
        add_settings_field(
            'life_travel_image_quality',
            'Qualité des images',
            array($this, 'render_image_quality_field'),
            'life-travel-media',
            'life_travel_media_general'
        );
    }
    
    /**
     * Rendu de la section générale
     */
    public function render_general_section() {
        echo '<p>Configurez les paramètres généraux de gestion des médias pour votre site.</p>';
    }
    
    /**
     * Rendu du champ de qualité d'image
     */
    public function render_image_quality_field() {
        $quality = get_option('life_travel_image_quality', 85);
        ?>
        <input type="range" min="60" max="100" name="life_travel_image_quality" id="life_travel_image_quality" value="<?php echo esc_attr($quality); ?>">
        <span class="quality-value"><?php echo esc_html($quality); ?>%</span>
        <p class="description">Définissez la qualité de compression des images JPEG. Une valeur plus élevée donne une meilleure qualité mais des fichiers plus lourds.</p>
        <?php
    }
    
    /**
     * Chargement des scripts d'administration
     */
    public function enqueue_admin_scripts($hook) {
        if ('appearance_page_life-travel-media' !== $hook) {
            return;
        }
        
        wp_enqueue_media();
        
        wp_enqueue_script(
            'life-travel-media-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin-media.js',
            array('jquery'),
            '1.0',
            true
        );
        
        wp_enqueue_style(
            'life-travel-media-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin-media.css',
            array(),
            '1.0'
        );
    }
    
    /**
     * Ajouter des tailles d'image personnalisées
     */
    public function add_image_sizes() {
        // Taille pour les arrière-plans de section
        add_image_size('life-travel-background', 1920, 1080, true);
        
        // Taille pour les cartes d'excursion
        add_image_size('life-travel-excursion-card', 600, 400, true);
        
        // Taille pour les galeries
        add_image_size('life-travel-gallery', 800, 600, false);
        
        // Taille pour les vignettes
        add_image_size('life-travel-thumbnail', 300, 200, true);
    }
    
    /**
     * Filtrer les attributs d'image pour améliorer les performances
     */
    public function filter_image_attributes($attributes, $attachment, $size) {
        // Ajouter lazy loading pour toutes les images sauf logo et éléments critiques
        if (!in_array($size, array('thumbnail', 'life-travel-logo'))) {
            $attributes['loading'] = 'lazy';
        }
        
        // Ajouter des dimensions explicites pour réduire le layout shift
        if (isset($attributes['src'])) {
            $image_src = wp_get_attachment_image_src($attachment->ID, $size);
            if ($image_src) {
                $attributes['width'] = $image_src[1];
                $attributes['height'] = $image_src[2];
            }
        }
        
        return $attributes;
    }
    
    /**
     * Autoriser le téléversement de fichiers SVG
     */
    public function allow_svg_upload($mimes) {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }
    
    /**
     * Corriger le type MIME pour les SVG
     */
    public function fix_svg_mime_type($data, $file, $filename, $mimes, $real_mime = null) {
        if (version_compare($GLOBALS['wp_version'], '5.1.0', '>=')) {
            $dosvg = in_array($real_mime, array('image/svg', 'image/svg+xml'));
        } else {
            $dosvg = ('.svg' === strtolower(substr($filename, -4)));
        }

        if ($dosvg) {
            $data['ext'] = 'svg';
            $data['type'] = 'image/svg+xml';
        }

        return $data;
    }
}

// Initialiser le gestionnaire de médias
new Life_Travel_Media_Manager();
