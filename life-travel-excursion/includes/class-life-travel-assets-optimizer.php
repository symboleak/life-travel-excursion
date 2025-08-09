<?php
/**
 * Life Travel Assets Optimizer
 * 
 * Cette classe gère l'optimisation des ressources CSS et JavaScript pour
 * améliorer les performances et réduire le temps de chargement et la
 * consommation de bande passante.
 *
 * @package Life Travel Excursion
 * @since 2.4.0
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Classe Life_Travel_Assets_Optimizer
 */
class Life_Travel_Assets_Optimizer {

    /**
     * Instance unique (pattern Singleton)
     *
     * @var Life_Travel_Assets_Optimizer
     */
    private static $instance = null;
    
    /**
     * Répertoire racine du plugin
     *
     * @var string
     */
    private $plugin_root;
    
    /**
     * Répertoire des assets sources
     *
     * @var string
     */
    private $source_dir;
    
    /**
     * Répertoire des assets minifiés
     *
     * @var string
     */
    private $dist_dir;
    
    /**
     * Configuration d'optimisation
     *
     * @var array
     */
    private $config;
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Définir les chemins de manière sécurisée
        if (defined('LIFE_TRAVEL_EXCURSION_DIR')) {
            // Utiliser la constante définie dans le fichier principal du plugin
            $this->plugin_root = LIFE_TRAVEL_EXCURSION_DIR;
        } else {
            // Fallback - remonter de deux niveaux depuis le répertoire actuel
            $this->plugin_root = trailingslashit(dirname(dirname(__FILE__)));
        }
        
        // Vérifier que le chemin existe
        if (!is_dir($this->plugin_root)) {
            // Log d'erreur
            if (function_exists('error_log')) {
                error_log('Life Travel Assets Optimizer: Impossible de déterminer le répertoire racine du plugin');
            }
            return; // Ne pas initialiser si le chemin est incorrect
        }
        
        $this->source_dir = $this->plugin_root . 'assets/';
        $this->dist_dir = $this->plugin_root . 'assets/dist/';
        
        // Charger la configuration
        $this->load_config();
        
        // Vérifier que le répertoire source existe
        if (!is_dir($this->source_dir)) {
            if (function_exists('error_log')) {
                error_log('Life Travel Assets Optimizer: Le répertoire source n\'existe pas: ' . $this->source_dir);
            }
            return;
        }
        
        // Créer le répertoire dist s'il n'existe pas et si on a les permissions
        if (!is_dir($this->dist_dir) && is_writable(dirname($this->dist_dir))) {
            $created = wp_mkdir_p($this->dist_dir);
            
            if ($created) {
                wp_mkdir_p($this->dist_dir . 'css');
                wp_mkdir_p($this->dist_dir . 'js');
            } else {
                if (function_exists('error_log')) {
                    error_log('Life Travel Assets Optimizer: Impossible de créer le répertoire: ' . $this->dist_dir);
                }
            }
        }
        
        // Initialiser les hooks
        $this->init_hooks();
    }
    
    /**
     * Obtenir l'instance unique
     * 
     * @return Life_Travel_Assets_Optimizer
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Charger la configuration
     */
    private function load_config() {
        $default_config = array(
            'minify_css' => true,
            'minify_js' => true,
            'combine_css' => true,
            'combine_js' => true,
            'defer_js' => true,
            'preload_critical' => true,
            'image_optimization' => array(
                'enabled' => true,
                'webp_conversion' => true,
                'quality' => 80
            ),
            'exclude_files' => array(
                'css' => array('admin.css'),
                'js' => array('admin.js', 'network-detector.js')
            )
        );
        
        // Fusionner avec les options sauvegardées dans WordPress
        $saved_config = get_option('life_travel_assets_optimizer_config', array());
        $this->config = wp_parse_args($saved_config, $default_config);
    }
    
    /**
     * Initialiser les hooks WordPress
     */
    private function init_hooks() {
        // Hook pour l'optimisation des assets lors de l'activation du plugin
        add_action('life_travel_plugin_activated', array($this, 'optimize_all_assets'));
        
        // Hooks pour l'optimisation automatique
        add_action('wp_enqueue_scripts', array($this, 'optimize_frontend_assets'), 999);
        add_action('wp_head', array($this, 'add_preload_tags'), 1);
        add_filter('style_loader_tag', array($this, 'optimize_css_tag'), 10, 4);
        add_filter('script_loader_tag', array($this, 'optimize_js_tag'), 10, 3);
        
        // Hook pour l'AJAX
        add_action('wp_ajax_life_travel_optimize_assets', array($this, 'ajax_optimize_assets'));
    }
    
    /**
     * Optimise tous les assets
     * 
     * @return array Résultats de l'optimisation
     */
    public function optimize_all_assets() {
        $results = array(
            'css' => $this->optimize_css_files(),
            'js' => $this->optimize_js_files(),
            'images' => $this->optimize_images()
        );
        
        // Enregistrer la date de la dernière optimisation
        update_option('life_travel_last_optimization', time());
        
        return $results;
    }
    
    /**
     * Gestionnaire AJAX pour l'optimisation des assets
     */
    public function ajax_optimize_assets() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_optimize_assets_nonce')) {
            wp_send_json_error(array('message' => __('Sécurité : nonce invalide.', 'life-travel-excursion')));
            exit;
        }
        
        // Vérifier les droits
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas les droits nécessaires.', 'life-travel-excursion')));
            exit;
        }
        
        // Optimiser les assets
        $results = $this->optimize_all_assets();
        
        // Renvoyer les résultats
        wp_send_json_success(array(
            'message' => __('Optimisation des ressources terminée avec succès.', 'life-travel-excursion'),
            'results' => $results
        ));
    }
    
    /**
     * Optimise les fichiers CSS
     * 
     * @return array Résultats de l'optimisation
     */
    public function optimize_css_files() {
        $css_dir = $this->source_dir . 'css/';
        $css_dist_dir = $this->dist_dir . 'css/';
        $results = array(
            'processed' => 0,
            'combined' => false,
            'saved_bytes' => 0,
            'errors' => array()
        );
        
        // Vérifier si le répertoire CSS existe
        if (!is_dir($css_dir)) {
            $results['errors'][] = __('Le répertoire CSS source n\'existe pas.', 'life-travel-excursion');
            return $results;
        }
        
        // Créer le répertoire de destination s'il n'existe pas
        if (!is_dir($css_dist_dir)) {
            wp_mkdir_p($css_dist_dir);
        }
        
        // Récupérer les fichiers CSS
        $css_files = glob($css_dir . '*.css');
        
        // Si la combinaison est activée, préparer la combinaison
        $combined_css = '';
        $excluded_files = $this->config['exclude_files']['css'];
        
        // Traiter chaque fichier CSS
        foreach ($css_files as $css_file) {
            $filename = basename($css_file);
            
            // Ignorer les fichiers exclus
            if (in_array($filename, $excluded_files)) {
                continue;
            }
            
            // Lire le contenu du fichier
            $css_content = file_get_contents($css_file);
            $original_size = strlen($css_content);
            
            // Minifier le CSS si activé
            if ($this->config['minify_css']) {
                $css_content = $this->minify_css($css_content);
            }
            
            $new_size = strlen($css_content);
            $results['saved_bytes'] += ($original_size - $new_size);
            
            // Si la combinaison est activée, ajouter au fichier combiné
            if ($this->config['combine_css']) {
                $combined_css .= "/* " . $filename . " */\n" . $css_content . "\n";
            } else {
                // Sinon, enregistrer le fichier minifié
                $output_file = $css_dist_dir . $filename;
                if (file_put_contents($output_file, $css_content)) {
                    $results['processed']++;
                } else {
                    $results['errors'][] = sprintf(__('Impossible d\'écrire le fichier %s.', 'life-travel-excursion'), $output_file);
                }
            }
        }
        
        // Si la combinaison est activée, enregistrer le fichier combiné
        if ($this->config['combine_css'] && !empty($combined_css)) {
            $output_file = $css_dist_dir . 'life-travel-combined.css';
            if (file_put_contents($output_file, $combined_css)) {
                $results['combined'] = true;
                $results['processed']++;
            } else {
                $results['errors'][] = __('Impossible d\'écrire le fichier CSS combiné.', 'life-travel-excursion');
            }
        }
        
        return $results;
    }
    
    /**
     * Optimise les fichiers JavaScript
     * 
     * @return array Résultats de l'optimisation
     */
    public function optimize_js_files() {
        $js_dir = $this->source_dir . 'js/';
        $js_dist_dir = $this->dist_dir . 'js/';
        $results = array(
            'processed' => 0,
            'combined' => false,
            'saved_bytes' => 0,
            'errors' => array()
        );
        
        // Vérifier si le répertoire JS existe
        if (!is_dir($js_dir)) {
            $results['errors'][] = __('Le répertoire JS source n\'existe pas.', 'life-travel-excursion');
            return $results;
        }
        
        // Créer le répertoire de destination s'il n'existe pas
        if (!is_dir($js_dist_dir)) {
            wp_mkdir_p($js_dist_dir);
        }
        
        // Récupérer les fichiers JS
        $js_files = glob($js_dir . '*.js');
        
        // Si la combinaison est activée, préparer la combinaison
        $combined_js = '';
        $excluded_files = $this->config['exclude_files']['js'];
        
        // Traiter chaque fichier JS
        foreach ($js_files as $js_file) {
            $filename = basename($js_file);
            
            // Ignorer les fichiers exclus
            if (in_array($filename, $excluded_files)) {
                continue;
            }
            
            // Lire le contenu du fichier
            $js_content = file_get_contents($js_file);
            $original_size = strlen($js_content);
            
            // Minifier le JS si activé
            if ($this->config['minify_js']) {
                $js_content = $this->minify_js($js_content);
            }
            
            $new_size = strlen($js_content);
            $results['saved_bytes'] += ($original_size - $new_size);
            
            // Si la combinaison est activée, ajouter au fichier combiné
            if ($this->config['combine_js']) {
                $combined_js .= "/* " . $filename . " */\n" . $js_content . "\n";
            } else {
                // Sinon, enregistrer le fichier minifié
                $output_file = $js_dist_dir . $filename;
                if (file_put_contents($output_file, $js_content)) {
                    $results['processed']++;
                } else {
                    $results['errors'][] = sprintf(__('Impossible d\'écrire le fichier %s.', 'life-travel-excursion'), $output_file);
                }
            }
        }
        
        // Si la combinaison est activée, enregistrer le fichier combiné
        if ($this->config['combine_js'] && !empty($combined_js)) {
            $output_file = $js_dist_dir . 'life-travel-combined.js';
            if (file_put_contents($output_file, $combined_js)) {
                $results['combined'] = true;
                $results['processed']++;
            } else {
                $results['errors'][] = __('Impossible d\'écrire le fichier JS combiné.', 'life-travel-excursion');
            }
        }
        
        return $results;
    }
    
    /**
     * Optimisation des images
     * 
     * Utilise la bibliothèque GD ou des fonctions natives PHP pour optimiser les images
     * et générer des versions WebP si possible.
     * 
     * @return array Résultats de l'optimisation
     */
    public function optimize_images() {
        $results = array(
            'processed' => 0,
            'webp_converted' => 0,
            'saved_bytes' => 0,
            'errors' => array()
        );
        
        // Vérifier si l'optimisation d'images est activée
        if (!$this->config['image_optimization']['enabled']) {
            return $results;
        }
        
        // Vérifier si GD est disponible
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng')) {
            $results['errors'][] = __('L\'extension GD n\'est pas disponible. L\'optimisation d\'images est désactivée.', 'life-travel-excursion');
            return $results;
        }
        
        // Vérifier la qualité de compression
        $quality = isset($this->config['image_optimization']['quality']) ? 
            intval($this->config['image_optimization']['quality']) : 80;
        
        // S'assurer que la qualité est entre 0 et 100
        $quality = max(0, min(100, $quality));
        
        // Répertoires d'images à optimiser
        $image_dirs = array(
            $this->source_dir . 'img/',
            $this->source_dir . 'img/gallery/',
            $this->source_dir . 'img/backgrounds/',
            $this->source_dir . 'img/icons/'
        );
        
        // Répertoire de destination pour les images optimisées
        $dest_dir = $this->dist_dir . 'img/';
        
        // Créer le répertoire de destination s'il n'existe pas
        if (!is_dir($dest_dir) && is_writable(dirname($dest_dir))) {
            wp_mkdir_p($dest_dir);
        }
        
        // Vérifier si le répertoire de destination existe
        if (!is_dir($dest_dir) || !is_writable($dest_dir)) {
            $results['errors'][] = __('Le répertoire de destination n\'est pas accessible en écriture.', 'life-travel-excursion');
            return $results;
        }
        
        // Formats d'images à optimiser
        $formats = array('jpg', 'jpeg', 'png', 'gif');
        
        // Support WebP
        $webp_support = function_exists('imagewebp');
        
        // Parcourir chaque répertoire
        foreach ($image_dirs as $image_dir) {
            if (!is_dir($image_dir)) {
                continue;
            }
            
            // Parcourir chaque format
            foreach ($formats as $format) {
                $images = glob($image_dir . '*.' . $format);
                
                foreach ($images as $image) {
                    // Obtenir la taille originale
                    $original_size = filesize($image);
                    
                    // Créer le chemin de destination
                    $image_name = basename($image);
                    $output_file = $dest_dir . $image_name;
                    
                    try {
                        // Charger et optimiser l'image selon son format
                        $gdImage = null;
                        $success = false;
                        
                        switch (strtolower(pathinfo($image, PATHINFO_EXTENSION))) {
                            case 'jpg':
                            case 'jpeg':
                                $gdImage = @imagecreatefromjpeg($image);
                                if ($gdImage) {
                                    $success = @imagejpeg($gdImage, $output_file, $quality);
                                }
                                break;
                                
                            case 'png':
                                $gdImage = @imagecreatefrompng($image);
                                if ($gdImage) {
                                    // Préserver la transparence
                                    @imagealphablending($gdImage, false);
                                    @imagesavealpha($gdImage, true);
                                    // PNG utilise un niveau de compression de 0-9, convertir de 0-100
                                    $png_quality = 9 - floor(($quality / 100) * 9);
                                    $success = @imagepng($gdImage, $output_file, $png_quality);
                                }
                                break;
                                
                            case 'gif':
                                // Pour GIF, simplement copier le fichier car GD peut perdre l'animation
                                $success = @copy($image, $output_file);
                                break;
                        }
                        
                        // Si l'optimisation a réussi
                        if ($success) {
                            $results['processed']++;
                            $optimized_size = filesize($output_file);
                            $saved = $original_size - $optimized_size;
                            $results['saved_bytes'] += max(0, $saved); // Éviter les nombres négatifs
                            
                            // Convertir en WebP si possible
                            if ($webp_support && $gdImage && $this->config['image_optimization']['webp_conversion']) {
                                $webp_file = $dest_dir . pathinfo($image_name, PATHINFO_FILENAME) . '.webp';
                                if (@imagewebp($gdImage, $webp_file, $quality)) {
                                    $results['webp_converted']++;
                                }
                            }
                        } else {
                            $results['errors'][] = sprintf(__('Impossible d\'optimiser %s', 'life-travel-excursion'), $image_name);
                        }
                        
                        // Libérer la mémoire
                        if ($gdImage) {
                            @imagedestroy($gdImage);
                        }
                        
                    } catch (Exception $e) {
                        $results['errors'][] = sprintf(__('Erreur lors de l\'optimisation de %s: %s', 'life-travel-excursion'), 
                            $image_name, $e->getMessage());
                    }
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Minifier le contenu CSS
     * 
     * @param string $css Contenu CSS à minifier
     * @return string CSS minifié
     */
    private function minify_css($css) {
        // Supprimer les commentaires
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Supprimer les espaces inutiles
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Supprimer les espaces avant et après les caractères { } : ; ,
        $css = preg_replace('/\s*({|}|:|;|,)\s*/', '$1', $css);
        
        // Supprimer les points-virgules finaux inutiles
        $css = preg_replace('/;}/', '}', $css);
        
        // Supprimer les zéros inutiles
        $css = preg_replace('/(^|[^0-9])0+([0-9]+)/', '$1$2', $css);
        $css = preg_replace('/([0-9]+)\.0+px/', '$1px', $css);
        
        // Supprimer les unités pour les valeurs nulles
        $css = preg_replace('/([:\s])0(px|pt|em|rem|%|in|cm|mm|pc|ex|vw|vh|vmin|vmax)/', '${1}0', $css);
        
        return trim($css);
    }
    
    /**
     * Minifier le contenu JavaScript
     * 
     * Note: C'est une méthode de minification très basique.
     * Pour une implémentation réelle, on utiliserait une bibliothèque
     * comme JSMin ou un outil comme Closure Compiler.
     * 
     * @param string $js Contenu JavaScript à minifier
     * @return string JavaScript minifié
     */
    private function minify_js($js) {
        // Supprimer les commentaires sur une ligne
        $js = preg_replace('~//.*$~m', '', $js);
        
        // Supprimer les commentaires multi-lignes
        $js = preg_replace('~/\*.*?\*/~s', '', $js);
        
        // Supprimer les espaces multiples, tabulations et nouvelles lignes
        $js = preg_replace('~\s+~', ' ', $js);
        
        // Supprimer les espaces avant et après certains caractères
        $js = preg_replace('~\s*([{}:;,=\+\-\*\/\(\)\[\]])\s*~', '$1', $js);
        
        return trim($js);
    }
    
    /**
     * Modifier l'URL des ressources CSS pour utiliser les versions optimisées
     * 
     * @param string $src URL du fichier CSS
     * @param string $handle ID du style
     * @return string URL modifiée
     */
    public function use_optimized_css($src, $handle) {
        // Ignorer les ressources externes
        if ($src && strpos($src, site_url()) !== false && strpos($src, 'assets/css') !== false) {
            $filename = basename($src);
            
            // Ignorer les fichiers exclus
            if (in_array($filename, $this->config['exclude_files']['css'])) {
                return $src;
            }
            
            // Si la combinaison est activée, remplacer par le fichier combiné
            if ($this->config['combine_css']) {
                $dist_url = str_replace('/assets/css/', '/assets/dist/css/', dirname($src));
                return $dist_url . '/life-travel-combined.css';
            } else {
                // Sinon, utiliser la version minifiée
                return str_replace('/assets/css/', '/assets/dist/css/', $src);
            }
        }
        
        return $src;
    }
    
    /**
     * Modifier l'URL des ressources JavaScript pour utiliser les versions optimisées
     * 
     * @param string $src URL du fichier JavaScript
     * @param string $handle ID du script
     * @return string URL modifiée
     */
    public function use_optimized_js($src, $handle) {
        // Ignorer les ressources externes
        if ($src && strpos($src, site_url()) !== false && strpos($src, 'assets/js') !== false) {
            $filename = basename($src);
            
            // Ignorer les fichiers exclus
            if (in_array($filename, $this->config['exclude_files']['js'])) {
                return $src;
            }
            
            // Si la combinaison est activée, remplacer par le fichier combiné
            if ($this->config['combine_js']) {
                $dist_url = str_replace('/assets/js/', '/assets/dist/js/', dirname($src));
                return $dist_url . '/life-travel-combined.js';
            } else {
                // Sinon, utiliser la version minifiée
                return str_replace('/assets/js/', '/assets/dist/js/', $src);
            }
        }
        
        return $src;
    }
    
    /**
     * Ajouter l'attribut defer aux balises script
     * 
     * @param string $tag Balise script complète
     * @param string $handle ID du script
     * @param string $src URL du script
     * @return string Balise script modifiée
     */
    public function add_defer_to_js($tag, $handle, $src) {
        // Ne pas ajouter defer si déjà présent ou si c'est un script inline
        if ($this->config['defer_js'] && strpos($tag, 'defer') === false && strpos($tag, '<script>') === false) {
            // Ignorer certains scripts critiques
            $critical_scripts = array('jquery', 'jquery-core', 'jquery-migrate');
            
            if (!in_array($handle, $critical_scripts)) {
                $tag = str_replace(' src=', ' defer src=', $tag);
            }
        }
        
        return $tag;
    }
    
    /**
     * Mettre à jour la configuration
     * 
     * @param array $config Nouvelle configuration
     * @return bool Succès de la mise à jour
     */
    public function update_config($config) {
        $this->config = wp_parse_args($config, $this->config);
        return update_option('life_travel_assets_optimizer_config', $this->config);
    }
    
    /**
     * Optimise les assets frontend
     */
    public function optimize_frontend_assets() {
        if (is_admin()) {
            return;
        }
        
        // Remplacer les URLs des resources par les versions optimisées
        if ($this->config['minify_css'] || $this->config['combine_css']) {
            add_filter('style_loader_src', array($this, 'use_optimized_css'), 10, 2);
        }
        
        if ($this->config['minify_js'] || $this->config['combine_js']) {
            add_filter('script_loader_src', array($this, 'use_optimized_js'), 10, 2);
        }
        
        // Ajouter l'attribut defer aux scripts
        if ($this->config['defer_js']) {
            add_filter('script_loader_tag', array($this, 'add_defer_to_js'), 10, 3);
        }
    }
    
    /**
     * Ajoute des balises preload pour les ressources critiques
     */
    public function add_preload_tags() {
        if (!$this->config['preload_critical'] || is_admin()) {
            return;
        }
        
        // Liste des ressources critiques à précharger
        $critical_resources = array(
            // CSS critique
            array(
                'href' => $this->config['combine_css'] 
                    ? plugins_url('assets/dist/css/life-travel-combined.css', dirname(__FILE__)) 
                    : plugins_url('assets/dist/css/adaptive-standard.css', dirname(__FILE__)),
                'as' => 'style',
                'type' => 'text/css',
                'media' => 'all'
            ),
            // Police d'icônes (à adapter selon vos besoins)
            array(
                'href' => plugins_url('assets/fonts/life-travel-icons.woff2', dirname(__FILE__)),
                'as' => 'font',
                'type' => 'font/woff2',
                'crossorigin' => 'anonymous'
            ),
            // Image principale (à adapter selon vos besoins)
            array(
                'href' => plugins_url('assets/img/main-hero.webp', dirname(__FILE__)),
                'as' => 'image',
                'type' => 'image/webp'
            )
        );
        
        // Générer les balises preload
        foreach ($critical_resources as $resource) {
            $attributes = '';
            foreach ($resource as $attr => $value) {
                $attributes .= $attr . '="' . esc_attr($value) . '" ';
            }
            echo "<link rel=\"preload\" {$attributes}/>\n";
        }
    }
    
    /**
     * Optimise les balises CSS
     * 
     * @param string $tag    Balise HTML complète
     * @param string $handle ID de la feuille de style
     * @param string $href   URL de la feuille de style
     * @param string $media  Attribut media
     * @return string        Balise HTML modifiée
     */
    public function optimize_css_tag($tag, $handle, $href, $media) {
        // Ignorer si nous sommes dans l'admin
        if (is_admin()) {
            return $tag;
        }
        
        // Identifier les feuilles de style non critiques pour les charger en différé
        $non_critical = array('animate', 'slider', 'lightbox');
        $is_non_critical = false;
        
        foreach ($non_critical as $keyword) {
            if (strpos($handle, $keyword) !== false || strpos($href, $keyword) !== false) {
                $is_non_critical = true;
                break;
            }
        }
        
        // Pour les feuilles de style non critiques, utiliser rel="preload" avec onload
        if ($is_non_critical) {
            $tag = str_replace('rel=\'stylesheet\'', 'rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"', $tag);
            // Ajouter un fallback pour les navigateurs sans JavaScript
            $tag .= "\n<noscript><link rel='stylesheet' href='" . esc_url($href) . "' media='" . esc_attr($media) . "' /></noscript>";
        }
        
        return $tag;
    }
    
    /**
     * Optimise les balises JavaScript
     * 
     * @param string $tag    Balise script complète
     * @param string $handle ID du script
     * @param string $src    URL du script
     * @return string        Balise script modifiée
     */
    public function optimize_js_tag($tag, $handle, $src) {
        // Ignorer si nous sommes dans l'admin
        if (is_admin()) {
            return $tag;
        }
        
        // Ajouter l'attribut defer pour les scripts non critiques
        if ($this->config['defer_js']) {
            // Exclure certains scripts critiques
            $critical_scripts = array('jquery', 'jquery-core', 'jquery-migrate', 'network-detector');
            
            if (!in_array($handle, $critical_scripts) && strpos($tag, 'defer') === false) {
                $tag = str_replace('<script ', '<script defer ', $tag);
            }
        }
        
        return $tag;
    }
}

// Initialiser l'optimiseur d'assets
function life_travel_assets_optimizer() {
    return Life_Travel_Assets_Optimizer::get_instance();
}

// Initialisation
life_travel_assets_optimizer();
