<?php
/**
 * Module de mini-carte interactive pour Life Travel Excursion
 * Affiche une carte avec les lieux des excursions
 */

defined('ABSPATH') || exit;

class Life_Travel_Interactive_Map {
    /**
     * Constructeur
     */
    public function __construct() {
        // Enregistrer les styles et scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Ajouter le shortcode pour la carte
        add_shortcode('life_travel_map', array($this, 'map_shortcode'));
        
        // Ajouter la carte sur les pages d'archive des produits
        add_action('woocommerce_before_shop_loop', array($this, 'display_map_on_archive'), 10);
        
        // Ajouter les réglages au Customizer
        add_action('customize_register', array($this, 'register_customizer_settings'));
        
        // Ajouter les meta box pour les coordonnées des lieux
        add_action('add_meta_boxes', array($this, 'add_location_meta_box'));
        add_action('save_post', array($this, 'save_location_meta'));
        
        // AJAX pour récupérer les marqueurs
        add_action('wp_ajax_get_map_markers', array($this, 'get_map_markers'));
        add_action('wp_ajax_nopriv_get_map_markers', array($this, 'get_map_markers'));
    }
    
    /**
     * Enregistre les styles et scripts nécessaires pour la carte
     */
    public function enqueue_assets() {
        // Ne charger les ressources que sur les pages pertinentes
        $load_map = is_product_category() || is_shop() || 
                    is_product() || 
                    is_page() && has_shortcode(get_post()->post_content, 'life_travel_map');
        
        if (!$load_map) {
            return;
        }
        
        // Déterminer la bibliothèque de carte à utiliser
        $map_provider = get_theme_mod('lte_map_provider', 'leaflet');
        
        if ($map_provider === 'google') {
            // Clé API Google Maps
            $api_key = get_theme_mod('lte_google_maps_api_key', '');
            
            if (!empty($api_key)) {
                wp_enqueue_script(
                    'google-maps',
                    'https://maps.googleapis.com/maps/api/js?key=' . $api_key . '&libraries=places',
                    array(),
                    null,
                    true
                );
            }
        } else {
            // Leaflet (gratuit et open source)
            wp_enqueue_style(
                'leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
                array(),
                '1.9.4'
            );
            
            wp_enqueue_script(
                'leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
                array(),
                '1.9.4',
                true
            );
            
            // Cluster de marqueurs pour Leaflet
            wp_enqueue_style(
                'leaflet-markercluster',
                'https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css',
                array('leaflet'),
                '1.4.1'
            );
            
            wp_enqueue_style(
                'leaflet-markercluster-default',
                'https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css',
                array('leaflet-markercluster'),
                '1.4.1'
            );
            
            wp_enqueue_script(
                'leaflet-markercluster',
                'https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js',
                array('leaflet'),
                '1.4.1',
                true
            );
        }
        
        // Script principal de la carte
        wp_enqueue_script(
            'life-travel-map',
            LIFE_TRAVEL_ASSETS_URL . 'js/interactive-map.js',
            array('jquery'),
            LIFE_TRAVEL_EXCURSION_VERSION,
            true
        );
        
        // Style de la carte
        wp_enqueue_style(
            'life-travel-map',
            LIFE_TRAVEL_ASSETS_URL . 'css/interactive-map.css',
            array(),
            LIFE_TRAVEL_EXCURSION_VERSION
        );
        
        // Transmettre les données de configuration au script
        wp_localize_script('life-travel-map', 'lteMapConfig', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lte_map_nonce'),
            'provider' => $map_provider,
            'default_lat' => get_theme_mod('lte_map_default_lat', 4.0511),      // Douala par défaut
            'default_lng' => get_theme_mod('lte_map_default_lng', 9.7679),
            'default_zoom' => get_theme_mod('lte_map_default_zoom', 8),
            'marker_icon' => LIFE_TRAVEL_ASSETS_URL . 'img/map-marker.png',
            'i18n' => array(
                'view_details' => __('Voir les détails', 'life-travel-excursion'),
                'starting_from' => __('À partir de', 'life-travel-excursion'),
                'loading' => __('Chargement de la carte...', 'life-travel-excursion'),
                'error_loading' => __('Erreur lors du chargement de la carte', 'life-travel-excursion'),
            )
        ));
    }
    
    /**
     * Shortcode pour afficher la carte interactive
     */
    public function map_shortcode($atts) {
        // Extraire les attributs du shortcode
        $atts = shortcode_atts(array(
            'height' => '400px',
            'width' => '100%',
            'zoom' => get_theme_mod('lte_map_default_zoom', 8),
            'category' => '',
        ), $atts);
        
        // Générer un ID unique pour la carte
        $map_id = 'lte-map-' . mt_rand(1000, 9999);
        
        // Construire le style CSS personnalisé
        $style = "height: {$atts['height']}; width: {$atts['width']};";
        
        // Début du buffer de sortie
        ob_start();
        ?>
        <div class="lte-map-container">
            <div id="<?php echo esc_attr($map_id); ?>" class="lte-interactive-map" style="<?php echo esc_attr($style); ?>" 
                 data-zoom="<?php echo esc_attr($atts['zoom']); ?>"
                 data-category="<?php echo esc_attr($atts['category']); ?>">
                <div class="lte-map-loader">
                    <div class="lte-spinner"></div>
                    <p><?php _e('Chargement de la carte...', 'life-travel-excursion'); ?></p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Affiche la carte sur les pages d'archive des produits
     */
    public function display_map_on_archive() {
        // Vérifier si la carte doit être affichée sur les archives
        if (!get_theme_mod('lte_map_show_on_archive', true)) {
            return;
        }
        
        // Récupérer la catégorie actuelle si pertinent
        $category = '';
        if (is_product_category()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $category = $term->slug;
            }
        }
        
        // Hauteur personnalisée pour la page d'archive
        $height = get_theme_mod('lte_map_archive_height', '350px');
        
        // Afficher la carte
        echo do_shortcode("[life_travel_map height=\"{$height}\" category=\"{$category}\"]");
    }
    
    /**
     * Récupère les marqueurs pour la carte via AJAX
     */
    public function get_map_markers() {
        // Vérifier le nonce
        check_ajax_referer('lte_map_nonce', 'nonce');
        
        // Initialiser la réponse
        $response = array(
            'success' => false,
            'markers' => array()
        );
        
        // Récupérer la catégorie si fournie
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        
        // Arguments de requête pour récupérer les produits de type excursion
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => 'excursion',
                )
            )
        );
        
        // Filtrer par catégorie si fournie
        if (!empty($category)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $category,
            );
        }
        
        // Exécuter la requête
        $products = new WP_Query($args);
        
        if ($products->have_posts()) {
            while ($products->have_posts()) {
                $products->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
                
                // Récupérer les coordonnées du produit
                $lat = get_post_meta($product_id, '_excursion_latitude', true);
                $lng = get_post_meta($product_id, '_excursion_longitude', true);
                
                // Vérifier que les coordonnées sont valides
                if (empty($lat) || empty($lng)) {
                    continue;
                }
                
                // Construire les données du marqueur
                $marker = array(
                    'id' => $product_id,
                    'title' => get_the_title(),
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'url' => get_permalink(),
                    'thumbnail' => get_the_post_thumbnail_url($product_id, 'thumbnail'),
                    'price' => $product->get_price_html(),
                    'excerpt' => wp_trim_words(get_the_excerpt(), 15),
                );
                
                // Ajouter à la liste des marqueurs
                $response['markers'][] = $marker;
            }
            
            $response['success'] = true;
        }
        
        // Réinitialiser la requête
        wp_reset_postdata();
        
        // Envoyer la réponse JSON
        wp_send_json($response);
    }
    
    /**
     * Ajoute une meta box pour les coordonnées du lieu d'excursion
     */
    public function add_location_meta_box() {
        add_meta_box(
            'lte_excursion_location',
            __('Emplacement de l\'excursion', 'life-travel-excursion'),
            array($this, 'render_location_meta_box'),
            'product',
            'normal',
            'high'
        );
    }
    
    /**
     * Affiche le contenu de la meta box pour les coordonnées
     */
    public function render_location_meta_box($post) {
        // Récupérer les valeurs actuelles
        $latitude = get_post_meta($post->ID, '_excursion_latitude', true);
        $longitude = get_post_meta($post->ID, '_excursion_longitude', true);
        $address = get_post_meta($post->ID, '_excursion_address', true);
        
        // Nonce pour la sécurité
        wp_nonce_field('lte_save_location_data', 'lte_location_nonce');
        
        // Afficher les champs
        ?>
        <div class="lte-location-fields">
            <p>
                <label for="excursion_address">
                    <?php _e('Adresse de l\'excursion', 'life-travel-excursion'); ?>
                </label>
                <input type="text" id="excursion_address" name="excursion_address" 
                       value="<?php echo esc_attr($address); ?>" class="widefat" 
                       placeholder="<?php _e('Ex: Kribi, Cameroun', 'life-travel-excursion'); ?>">
                <span class="description">
                    <?php _e('Entrez l\'adresse du lieu de l\'excursion', 'life-travel-excursion'); ?>
                </span>
            </p>
            
            <div class="lte-coords-group">
                <p>
                    <label for="excursion_latitude">
                        <?php _e('Latitude', 'life-travel-excursion'); ?>
                    </label>
                    <input type="text" id="excursion_latitude" name="excursion_latitude" 
                           value="<?php echo esc_attr($latitude); ?>" 
                           placeholder="<?php _e('Ex: 4.0511', 'life-travel-excursion'); ?>">
                </p>
                
                <p>
                    <label for="excursion_longitude">
                        <?php _e('Longitude', 'life-travel-excursion'); ?>
                    </label>
                    <input type="text" id="excursion_longitude" name="excursion_longitude" 
                           value="<?php echo esc_attr($longitude); ?>" 
                           placeholder="<?php _e('Ex: 9.7679', 'life-travel-excursion'); ?>">
                </p>
            </div>
            
            <div class="lte-location-preview">
                <p>
                    <button type="button" class="button" id="lte-locate-btn">
                        <?php _e('Localiser sur la carte', 'life-travel-excursion'); ?>
                    </button>
                </p>
                <div id="lte-location-map" style="height: 300px; width: 100%;"></div>
            </div>
            
            <style>
                .lte-coords-group {
                    display: flex;
                    gap: 20px;
                }
                .lte-coords-group p {
                    flex: 1;
                }
                .lte-location-preview {
                    margin-top: 15px;
                }
                #lte-location-map {
                    margin-top: 10px;
                    border: 1px solid #ddd;
                }
            </style>
            
            <script>
                jQuery(document).ready(function($) {
                    // Code pour initialiser la carte (sera remplacé par le bon code selon le fournisseur)
                    var map, marker;
                    
                    // Fonction pour initialiser la carte
                    function initMap() {
                        var lat = $('#excursion_latitude').val() || <?php echo get_theme_mod('lte_map_default_lat', 4.0511); ?>;
                        var lng = $('#excursion_longitude').val() || <?php echo get_theme_mod('lte_map_default_lng', 9.7679); ?>;
                        
                        // Créer la carte avec Leaflet
                        map = L.map('lte-location-map').setView([lat, lng], 10);
                        
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        }).addTo(map);
                        
                        // Ajouter un marqueur s'il y a des coordonnées
                        if (lat && lng) {
                            marker = L.marker([lat, lng], {
                                draggable: true
                            }).addTo(map);
                            
                            // Mettre à jour les coordonnées lors du déplacement du marqueur
                            marker.on('dragend', function(e) {
                                var position = marker.getLatLng();
                                $('#excursion_latitude').val(position.lat.toFixed(6));
                                $('#excursion_longitude').val(position.lng.toFixed(6));
                            });
                        }
                        
                        // Cliquer sur la carte pour ajouter un marqueur
                        map.on('click', function(e) {
                            if (marker) {
                                marker.setLatLng(e.latlng);
                            } else {
                                marker = L.marker(e.latlng, {
                                    draggable: true
                                }).addTo(map);
                                
                                marker.on('dragend', function(e) {
                                    var position = marker.getLatLng();
                                    $('#excursion_latitude').val(position.lat.toFixed(6));
                                    $('#excursion_longitude').val(position.lng.toFixed(6));
                                });
                            }
                            
                            $('#excursion_latitude').val(e.latlng.lat.toFixed(6));
                            $('#excursion_longitude').val(e.latlng.lng.toFixed(6));
                        });
                    }
                    
                    // Initialiser la carte après le chargement de Leaflet
                    if (typeof L !== 'undefined') {
                        initMap();
                    } else {
                        // Charger Leaflet si ce n'est pas déjà fait
                        $.getScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', function() {
                            $('<link>')
                                .appendTo('head')
                                .attr({
                                    type: 'text/css',
                                    rel: 'stylesheet',
                                    href: 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
                                });
                            
                            setTimeout(initMap, 100);
                        });
                    }
                    
                    // Localiser via l'adresse
                    $('#lte-locate-btn').on('click', function() {
                        var address = $('#excursion_address').val();
                        if (!address) {
                            alert('<?php _e("Veuillez saisir une adresse", "life-travel-excursion"); ?>');
                            return;
                        }
                        
                        // Utiliser Nominatim pour la géolocalisation
                        $.getJSON('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(address), function(data) {
                            if (data && data.length > 0) {
                                var lat = parseFloat(data[0].lat);
                                var lng = parseFloat(data[0].lon);
                                
                                // Mettre à jour les champs
                                $('#excursion_latitude').val(lat.toFixed(6));
                                $('#excursion_longitude').val(lng.toFixed(6));
                                
                                // Mettre à jour la carte
                                map.setView([lat, lng], 13);
                                
                                if (marker) {
                                    marker.setLatLng([lat, lng]);
                                } else {
                                    marker = L.marker([lat, lng], {
                                        draggable: true
                                    }).addTo(map);
                                    
                                    marker.on('dragend', function(e) {
                                        var position = marker.getLatLng();
                                        $('#excursion_latitude').val(position.lat.toFixed(6));
                                        $('#excursion_longitude').val(position.lng.toFixed(6));
                                    });
                                }
                            } else {
                                alert('<?php _e("Adresse non trouvée", "life-travel-excursion"); ?>');
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }
    
    /**
     * Enregistre les coordonnées du lieu d'excursion
     */
    public function save_location_meta($post_id) {
        // Vérifier si c'est une sauvegarde automatique
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Vérifier le nonce
        if (!isset($_POST['lte_location_nonce']) || !wp_verify_nonce($_POST['lte_location_nonce'], 'lte_save_location_data')) {
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Enregistrer les coordonnées
        if (isset($_POST['excursion_latitude'])) {
            update_post_meta($post_id, '_excursion_latitude', sanitize_text_field($_POST['excursion_latitude']));
        }
        
        if (isset($_POST['excursion_longitude'])) {
            update_post_meta($post_id, '_excursion_longitude', sanitize_text_field($_POST['excursion_longitude']));
        }
        
        if (isset($_POST['excursion_address'])) {
            update_post_meta($post_id, '_excursion_address', sanitize_text_field($_POST['excursion_address']));
        }
    }
    
    /**
     * Enregistre les réglages dans le Customizer
     */
    public function register_customizer_settings($wp_customize) {
        // Section pour la carte interactive
        $wp_customize->add_section('lte_interactive_map', array(
            'title' => __('Carte interactive', 'life-travel-excursion'),
            'description' => __('Configurez la carte interactive des excursions', 'life-travel-excursion'),
            'priority' => 35,
        ));
        
        // Choix du fournisseur de carte
        $wp_customize->add_setting('lte_map_provider', array(
            'default' => 'leaflet',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        $wp_customize->add_control('lte_map_provider', array(
            'label' => __('Fournisseur de carte', 'life-travel-excursion'),
            'section' => 'lte_interactive_map',
            'type' => 'select',
            'choices' => array(
                'leaflet' => __('Leaflet (OpenStreetMap, gratuit)', 'life-travel-excursion'),
                'google' => __('Google Maps (nécessite une clé API)', 'life-travel-excursion'),
            ),
        ));
        
        // Clé API Google Maps
        $wp_customize->add_setting('lte_google_maps_api_key', array(
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        $wp_customize->add_control('lte_google_maps_api_key', array(
            'label' => __('Clé API Google Maps', 'life-travel-excursion'),
            'section' => 'lte_interactive_map',
            'type' => 'text',
            'description' => __('Nécessaire uniquement si vous utilisez Google Maps comme fournisseur', 'life-travel-excursion'),
        ));
        
        // Position par défaut (latitude)
        $wp_customize->add_setting('lte_map_default_lat', array(
            'default' => 4.0511, // Douala
            'sanitize_callback' => 'floatval',
        ));
        
        $wp_customize->add_control('lte_map_default_lat', array(
            'label' => __('Latitude par défaut', 'life-travel-excursion'),
            'section' => 'lte_interactive_map',
            'type' => 'number',
            'input_attrs' => array(
                'step' => 0.000001,
            ),
        ));
        
        // Position par défaut (longitude)
        $wp_customize->add_setting('lte_map_default_lng', array(
            'default' => 9.7679, // Douala
            'sanitize_callback' => 'floatval',
        ));
        
        $wp_customize->add_control('lte_map_default_lng', array(
            'label' => __('Longitude par défaut', 'life-travel-excursion'),
            'section' => 'lte_interactive_map',
            'type' => 'number',
            'input_attrs' => array(
                'step' => 0.000001,
            ),
        ));
        
        // Niveau de zoom par défaut
        $wp_customize->add_setting('lte_map_default_zoom', array(
            'default' => 8,
            'sanitize_callback' => 'absint',
        ));
        
        $wp_customize->add_control('lte_map_default_zoom', array(
            'label' => __('Niveau de zoom par défaut', 'life-travel-excursion'),
            'section' => 'lte_interactive_map',
            'type' => 'range',
            'input_attrs' => array(
                'min' => 1,
                'max' => 18,
                'step' => 1,
            ),
        ));
        
        // Afficher la carte sur les pages d'archive
        $wp_customize->add_setting('lte_map_show_on_archive', array(
            'default' => true,
            'sanitize_callback' => 'absint',
        ));
        
        $wp_customize->add_control('lte_map_show_on_archive', array(
            'label' => __('Afficher la carte sur les pages de catégorie', 'life-travel-excursion'),
            'section' => 'lte_interactive_map',
            'type' => 'checkbox',
        ));
        
        // Hauteur de la carte sur les pages d'archive
        $wp_customize->add_setting('lte_map_archive_height', array(
            'default' => '350px',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        $wp_customize->add_control('lte_map_archive_height', array(
            'label' => __('Hauteur de la carte sur les pages de catégorie', 'life-travel-excursion'),
            'section' => 'lte_interactive_map',
            'type' => 'text',
        ));
    }
}

// Initialiser la classe de carte interactive
$lte_interactive_map = new Life_Travel_Interactive_Map();
