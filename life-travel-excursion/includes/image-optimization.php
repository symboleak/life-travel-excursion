<?php
/**
 * Optimisation des images pour le site Life Travel
 * 
 * Ce fichier contient toutes les fonctions nécessaires pour optimiser
 * les images et améliorer les performances, particulièrement sur les 
 * connexions lentes ou mobiles.
 * 
 * @package Life Travel Excursion
 * @version 2.3.3
 */

defined('ABSPATH') || exit;

/**
 * Classe d'optimisation des images
 */
class Life_Travel_Image_Optimization {
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Activer le lazy loading pour les images
        add_filter('wp_get_attachment_image_attributes', array($this, 'add_lazyload_to_attachment_images'), 10, 3);
        add_filter('the_content', array($this, 'add_lazyload_to_content_images'), 99);
        add_filter('post_thumbnail_html', array($this, 'add_lazyload_to_post_thumbnails'), 10, 5);
        
        // Ajouter le support pour les images WebP quand disponibles
        add_filter('wp_calculate_image_srcset', array($this, 'add_webp_support_to_srcset'), 10, 5);
        
        // Ajouter des fallbacks pour les images manquantes
        add_action('wp_head', array($this, 'add_image_fallback_script'));
        
        // Utiliser des miniatures optimisées pour les appareils mobiles
        add_filter('wp_get_attachment_image_src', array($this, 'optimize_image_size_for_device'), 10, 4);
        
        // Décharger les styles inutiles sur les pages non-pertinentes
        add_action('wp_enqueue_scripts', array($this, 'dequeue_unused_styles'), 999);
    }
    
    /**
     * Ajoute les attributs de lazy loading aux images d'attachements
     *
     * @param array $attr Les attributs de l'image
     * @param WP_Post $attachment L'objet attachement
     * @param string|array $size La taille de l'image
     * @return array Les attributs modifiés
     */
    public function add_lazyload_to_attachment_images($attr, $attachment, $size) {
        // Ne pas appliquer le lazy loading aux avatars ou aux petites images
        if (isset($attr['class']) && strpos($attr['class'], 'avatar') !== false) {
            return $attr;
        }
        
        // Sauvegarde de l'URL originale dans data-src
        $attr['data-src'] = $attr['src'];
        
        // Petite image transparente comme placeholder
        $attr['src'] = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        
        // Ajouter la classe lazyload
        $attr['class'] = isset($attr['class']) ? $attr['class'] . ' lazyload' : 'lazyload';
        
        // Ajouter noscript fallback
        $attr['data-fallback'] = '<noscript><img src="' . esc_attr($attr['data-src']) . '" alt="' . esc_attr($attr['alt']) . '" /></noscript>';
        
        return $attr;
    }
    
    /**
     * Ajoute les attributs de lazy loading aux images dans le contenu
     *
     * @param string $content Le contenu du post
     * @return string Le contenu modifié
     */
    public function add_lazyload_to_content_images($content) {
        if (empty($content) || is_feed() || is_admin()) {
            return $content;
        }
        
        // Ne pas appliquer aux pages AMP
        if (function_exists('is_amp_endpoint') && is_amp_endpoint()) {
            return $content;
        }
        
        // Remplacer les balises img avec version lazy loading
        $content = preg_replace_callback(
            '/<img([^>]+)>/i',
            function($matches) {
                $img_tag = $matches[0];
                $img_attr = $matches[1];
                
                // Ignorer les images qui ont déjà des attributs de lazy loading
                if (strpos($img_attr, 'data-src') !== false || strpos($img_attr, 'lazyload') !== false) {
                    return $img_tag;
                }
                
                // Extraire l'URL de l'image
                preg_match('/src=[\'"](.*?)[\'"]/i', $img_attr, $src_matches);
                if (empty($src_matches)) {
                    return $img_tag;
                }
                
                $src = $src_matches[1];
                
                // Remplacer src par data-src et ajouter un placeholder
                $new_attr = str_replace(
                    'src="' . $src . '"',
                    'src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="' . $src . '"',
                    $img_attr
                );
                
                // Ajouter la classe lazyload
                if (strpos($new_attr, 'class=') !== false) {
                    $new_attr = preg_replace('/class=([\'"])(.*?)([\'"])/i', 'class=$1$2 lazyload$3', $new_attr);
                } else {
                    $new_attr .= ' class="lazyload"';
                }
                
                // Ajouter noscript fallback après l'image
                $new_img = '<img' . $new_attr . '><noscript><img src="' . $src . '" ' . $img_attr . '></noscript>';
                
                return $new_img;
            },
            $content
        );
        
        return $content;
    }
    
    /**
     * Ajoute les attributs de lazy loading aux thumbnails des posts
     *
     * @param string $html Le HTML de la thumbnail
     * @param int $post_id L'ID du post
     * @param int $post_thumbnail_id L'ID de la thumbnail
     * @param string|array $size La taille de l'image
     * @param array $attr Les attributs additionnels
     * @return string Le HTML modifié
     */
    public function add_lazyload_to_post_thumbnails($html, $post_id, $post_thumbnail_id, $size, $attr) {
        // Appliquer la même logique que pour les images de contenu
        return $this->add_lazyload_to_content_images($html);
    }
    
    /**
     * Ajoute le support WebP aux images quand disponible
     *
     * @param array $sources Les sources de l'image
     * @param array $size_array Les dimensions de l'image
     * @param string $image_src L'URL de l'image
     * @param array $image_meta Les métadonnées de l'image
     * @param int $attachment_id L'ID de l'attachement
     * @return array Les sources modifiées
     */
    public function add_webp_support_to_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        // Vérifier si le navigateur supporte WebP (vérifié côté client par JS)
        if (!empty($sources)) {
            foreach ($sources as $width => $source) {
                // Vérifier si une version WebP existe
                $webp_url = $source['url'] . '.webp';
                $webp_file = str_replace(site_url('/'), ABSPATH, $webp_url);
                
                if (file_exists($webp_file)) {
                    $sources[$width]['url'] = $webp_url;
                    $sources[$width]['format'] = 'image/webp';
                }
            }
        }
        
        return $sources;
    }
    
    /**
     * Ajoute un script pour gérer les images qui échouent à charger
     */
    public function add_image_fallback_script() {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Détecter la prise en charge de WebP
            var webp = new Image();
            webp.onload = function() { 
                if (webp.height === 1) {
                    document.documentElement.classList.add('webp-support');
                }
            };
            webp.onerror = function() {
                document.documentElement.classList.add('no-webp-support');
            };
            webp.src = 'data:image/webp;base64,UklGRhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
            
            // Lazy loading des images
            var lazyloadImages = document.querySelectorAll('img.lazyload');
            if ('IntersectionObserver' in window) {
                var imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var image = entry.target;
                            image.src = image.dataset.src;
                            image.classList.remove('lazyload');
                            imageObserver.unobserve(image);
                        }
                    });
                });

                lazyloadImages.forEach(function(image) {
                    imageObserver.observe(image);
                });
            } else {
                // Fallback pour les navigateurs sans IntersectionObserver
                var lazyloadThrottleTimeout;
                function lazyload() {
                    if (lazyloadThrottleTimeout) {
                        clearTimeout(lazyloadThrottleTimeout);
                    }
                    
                    lazyloadThrottleTimeout = setTimeout(function() {
                        var scrollTop = window.pageYOffset;
                        lazyloadImages.forEach(function(img) {
                            if (img.offsetTop < (window.innerHeight + scrollTop)) {
                                img.src = img.dataset.src;
                                img.classList.remove('lazyload');
                            }
                        });
                        
                        if (lazyloadImages.length == 0) { 
                            document.removeEventListener('scroll', lazyload);
                            window.removeEventListener('resize', lazyload);
                            window.removeEventListener('orientationChange', lazyload);
                        }
                    }, 20);
                }
                
                document.addEventListener('scroll', lazyload);
                window.addEventListener('resize', lazyload);
                window.addEventListener('orientationChange', lazyload);
                lazyload();
            }
            
            // Gérer les erreurs d'images
            document.addEventListener('error', function(e) {
                if (e.target.tagName.toLowerCase() === 'img') {
                    e.target.src = '<?php echo esc_url( LIFE_TRAVEL_EXCURSION_URL . 'assets/img/fallback-image.jpg' ); ?>';
                    e.target.classList.add('img-fallback');
                }
            }, true);
        });
        </script>
        <?php
    }
    
    /**
     * Optimise la taille des images en fonction de l'appareil
     *
     * @param array $image Les détails de l'image
     * @param int $attachment_id L'ID de l'attachement
     * @param string|array $size La taille demandée
     * @param bool $icon Si l'icône doit être utilisée
     * @return array Les détails modifiés
     */
    public function optimize_image_size_for_device($image, $attachment_id, $size, $icon) {
        // Ne rien faire pour les icônes
        if ($icon) {
            return $image;
        }
        
        // Vérifier si c'est un appareil mobile avec bande passante limitée
        if (wp_is_mobile() && isset($_COOKIE['lt_slow_connection'])) {
            // Si c'est une image de grande taille ou 'full', utiliser une taille plus petite
            if ($size === 'full' || (is_array($size) && $size[0] > 800)) {
                $medium_image = wp_get_attachment_image_src($attachment_id, 'medium', $icon);
                if ($medium_image) {
                    return $medium_image;
                }
            }
        }
        
        return $image;
    }
    
    /**
     * Décharge les styles et scripts inutiles sur certaines pages
     */
    public function dequeue_unused_styles() {
        // Ne rien faire dans l'admin
        if (is_admin()) {
            return;
        }
        
        // Identification des pages où tous les styles sont nécessaires
        $keep_all_styles = is_product() || is_shop() || is_cart() || is_checkout() || is_account_page();
        
        if (!$keep_all_styles) {
            // Liste des styles à conserver partout
            $essential_styles = array(
                'life-travel-excursion-style',
                'wp-block-library',
                'dashicons'
            );
            
            global $wp_styles;
            foreach ($wp_styles->queue as $handle) {
                // Garder les styles essentiels et ceux de notre thème/plugin
                if (!in_array($handle, $essential_styles) && 
                    strpos($handle, 'life-travel') === false &&
                    strpos($handle, 'woocommerce') !== false) {
                    wp_dequeue_style($handle);
                }
            }
        }
    }
}

// Initialiser la classe
new Life_Travel_Image_Optimization();

/**
 * Crée une image de fallback au cas où elle n'existe pas
 */
if (!function_exists('life_travel_create_fallback_image')) {
    function life_travel_create_fallback_image() {
        $base_dir = defined('LIFE_TRAVEL_EXCURSION_DIR') ? LIFE_TRAVEL_EXCURSION_DIR : ( defined('LIFE_TRAVEL_PLUGIN_DIR') ? LIFE_TRAVEL_PLUGIN_DIR : (dirname(__DIR__) . '/') );
        $fallback_path = $base_dir . 'assets/img/fallback-image.jpg';
        
        // S'assurer que le dossier existe
        $fallback_dir = dirname($fallback_path);
        if (!file_exists($fallback_dir)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($fallback_dir);
            } else {
                @mkdir($fallback_dir, 0755, true);
            }
        }
        
        if (!file_exists($fallback_path)) {
            // Créer une image simple avec GD
            if (function_exists('imagecreatetruecolor')) {
                $img = imagecreatetruecolor(800, 600);
                $bg_color = imagecolorallocate($img, 241, 241, 241);
                $text_color = imagecolorallocate($img, 153, 153, 153);
                
                // Remplir l'arrière-plan
                imagefilledrectangle($img, 0, 0, 800, 600, $bg_color);
                
                // Ajouter du texte
                if (function_exists('imageantialias')) {
                    imageantialias($img, true);
                }
                
                // Utiliser une police intégrée si possible
                if (function_exists('imagestring')) {
                    imagestring($img, 5, 300, 280, 'Image non disponible', $text_color);
                }
                
                // Sauvegarder l'image
                imagejpeg($img, $fallback_path, 90);
                imagedestroy($img);
            }
        }
    }
}
