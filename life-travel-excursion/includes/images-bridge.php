<?php
/**
 * Life Travel - Pont de gestion des images et SVG
 *
 * Centralise et optimise la gestion des images, SVG et sprites pour une
 * expérience utilisateur plus rapide et mieux adaptée au contexte camerounais.
 * 
 * Fonctionnalités:
 * - Optimisation des images avec WebP et fallbacks
 * - Gestion centralisée des SVG
 * - Génération de sprites SVG
 * - Gestion différée et prioritaire selon la connexion
 * - Intégration avec le pont PWA
 *
 * @package Life_Travel
 * @version 2.5.0
 */

// Si accès direct, sortir
if (!defined('ABSPATH')) {
    exit;
}

// S'assurer que le validateur de bridge est chargé
if (!function_exists('life_travel_register_bridge')) {
    require_once dirname(__FILE__) . '/bridge-validator.php';
}

// Enregistrer ce bridge auprès du validateur
life_travel_register_bridge('images', '2.5.0', array(
    'life_travel_filter_image_attributes',
    'life_travel_content_image_to_picture',
    'life_travel_add_svg_mime_support',
    'life_travel_get_svg_icon',
    'life_travel_add_image_assets_to_pwa_cache',
    'life_travel_get_optimized_picture_tag'
));

// Définir les constantes du pont d'images
define('LIFE_TRAVEL_IMAGES_VERSION', '2.5.0');
define('LIFE_TRAVEL_SVG_DIR', LIFE_TRAVEL_EXCURSION_DIR . 'assets/svg/');
define('LIFE_TRAVEL_SVG_URL', LIFE_TRAVEL_EXCURSION_URL . 'assets/svg/');
define('LIFE_TRAVEL_OPTIMIZED_IMAGES_DIR', LIFE_TRAVEL_EXCURSION_DIR . 'assets/img-opt/');
define('LIFE_TRAVEL_OPTIMIZED_IMAGES_URL', LIFE_TRAVEL_EXCURSION_URL . 'assets/img-opt/');

if (!function_exists('life_travel_get_svg_path')) {
    /**
     * Retourne le chemin du sprite SVG unifié du plugin.
     * Par défaut, retourne l'URL (utile côté front). Mettre $as_url=false pour le chemin disque.
     *
     * @param bool $as_url
     * @return string
     */
    function life_travel_get_svg_path($as_url = true) {
        $sprite_url = defined('LIFE_TRAVEL_SVG_PATH')
            ? LIFE_TRAVEL_SVG_PATH
            : LIFE_TRAVEL_EXCURSION_URL . 'assets/sprite.svg';
        $sprite_path = LIFE_TRAVEL_EXCURSION_DIR . 'assets/sprite.svg';
        return $as_url ? $sprite_url : $sprite_path;
    }
}

/**
 * Filtre les attributs des images pour appliquer le chargement différé et classes CSS adaptées
 *
 * @param array $attr Attributs de l'image
 * @param WP_Post $attachment Objet d'attachement
 * @param string|array $size Taille de l'image
 * @return array Attributs modifiés
 */
function life_travel_filter_image_attributes($attr, $attachment, $size) {
    // Ajouter le chargement différé par défaut
    if (!isset($attr['loading'])) {
        $attr['loading'] = 'lazy';
    }
    
    // Ajouter des classes CSS pour identifier les images optimisées
    if (!isset($attr['class'])) {
        $attr['class'] = '';
    }
    $attr['class'] .= ' life-travel-image';
    
    // Ajouter la compatibilité avec le pont PWA pour le cache offline
    if (function_exists('life_travel_is_offline_cache_enabled') && life_travel_is_offline_cache_enabled()) {
        $attr['data-offline-cacheable'] = 'true';
    }
    
    return $attr;
}

/**
 * Convertit les balises img du contenu en balises picture
 * pour supporter WebP avec fallback
 *
 * @param string $content Contenu HTML
 * @return string Contenu modifié
 */
function life_travel_content_image_to_picture($content) {
    // Vérifier si le pont PWA a les fonctions d'optimisation d'images
    if (!function_exists('life_travel_get_optimized_picture_tag')) {
        return $content;
    }
    
    // Rechercher toutes les balises img
    if (preg_match_all('/<img[^>]+>/i', $content, $matches)) {
        foreach ($matches[0] as $img_tag) {
            // Extraire l'URL de l'image
            if (preg_match('/src=["\']([^"\']*)["\']/i', $img_tag, $src_matches)) {
                $img_url = $src_matches[1];
                
                // Extraire les attributs
                $attr = array();
                
                // Alt text
                if (preg_match('/alt=["\']([^"\']*)["\']/i', $img_tag, $alt_matches)) {
                    $attr['alt'] = $alt_matches[1];
                }
                
                // Classes
                if (preg_match('/class=["\']([^"\']*)["\']/i', $img_tag, $class_matches)) {
                    $attr['class'] = $class_matches[1];
                }
                
                // Générer la balise picture
                $picture_tag = life_travel_get_optimized_picture_tag($img_url, 'medium', $attr);
                
                // Remplacer l'image par la balise picture
                $content = str_replace($img_tag, $picture_tag, $content);
            }
        }
    }
    
    return $content;
}

/**
 * Ajoute le support des fichiers SVG à WordPress
 *
 * @param array $mimes Types MIME autorisés
 * @return array Types MIME modifiés
 */
function life_travel_add_svg_mime_support($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}

/**
 * Inclut le sprite SVG dans le footer pour utilisation par les icônes
 */
function life_travel_include_svg_sprite() {
    $sprite_path = LIFE_TRAVEL_EXCURSION_DIR . 'assets/sprite.svg';
    $sprite_url = LIFE_TRAVEL_EXCURSION_URL . 'assets/sprite.svg';
    
    // Vérifier si le sprite existe
    if (file_exists($sprite_path)) {
        echo '<div style="display:none;">';
        @include($sprite_path);
        echo '</div>';
    } else {
        // Générer le sprite s'il n'existe pas
        life_travel_generate_svg_sprite();
    }
}

/**
 * Génère le sprite SVG à partir des icônes individuelles
 *
 * @return bool Succès de la génération
 */
function life_travel_generate_svg_sprite() {
    // Créer le répertoire SVG s'il n'existe pas
    if (!file_exists(LIFE_TRAVEL_SVG_DIR)) {
        wp_mkdir_p(LIFE_TRAVEL_SVG_DIR);
    }
    
    // Liste des icônes SVG à inclure
    $svg_files = glob(LIFE_TRAVEL_SVG_DIR . '*.svg');
    
    if (empty($svg_files)) {
        return false;
    }
    
    // Démarrer le sprite
    $sprite_content = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none;">';
    
    foreach ($svg_files as $svg_file) {
        if (basename($svg_file) != 'sprite.svg') {
            $id = 'icon-' . pathinfo($svg_file, PATHINFO_FILENAME);
            $content = file_get_contents($svg_file);
            
            // Extraire le contenu SVG à l'intérieur des balises svg
            if (preg_match('/<svg[^>]*>(.*?)<\/svg>/is', $content, $matches)) {
                $inner_content = $matches[1];
                $sprite_content .= '<symbol id="' . $id . '">' . $inner_content . '</symbol>';
            }
        }
    }
    
    // Fermer le sprite
    $sprite_content .= '</svg>';
    
    // Enregistrer le sprite dans assets/sprite.svg (emplacement unifié)
    $saved = file_put_contents(LIFE_TRAVEL_EXCURSION_DIR . 'assets/sprite.svg', $sprite_content);
    
    return ($saved !== false);
}

/**
 * Renvoie le markup HTML pour utiliser une icône SVG du sprite
 *
 * @param string $icon_name Nom de l'icône (sans préfixe)
 * @param array $attr Attributs supplémentaires
 * @return string Markup HTML
 */
function life_travel_get_svg_icon($icon_name, $attr = array()) {
    $default_attr = array(
        'class' => 'life-travel-icon',
        'width' => '24',
        'height' => '24',
        'aria-hidden' => 'true'
    );
    
    $attr = wp_parse_args($attr, $default_attr);
    
    // Construire les attributs
    $attributes = '';
    foreach ($attr as $name => $value) {
        $attributes .= ' ' . $name . '="' . esc_attr($value) . '"';
    }
    
    // Construire l'icône
    $svg = '<svg' . $attributes . '><use xlink:href="#icon-' . esc_attr($icon_name) . '"></use></svg>';
    
    return $svg;
}

/**
 * Intègre le pont d'images avec le pont PWA pour la mise en cache offline
 * Compatible avec le pont centralisé pour éviter les dépendances circulaires
 * 
 * @param array $urls Liste des URLs à mettre en cache
 * @return array Liste mise à jour
 */
function life_travel_add_image_assets_to_pwa_cache($urls) {
    // Vérifier si l'optimisation des images est activée
    if (!life_travel_use_optimized_images()) {
        return $urls;
    }
    
    // Vérifier si le cache offline est activé via la fonction centralisée
    if (function_exists('life_travel_is_offline_cache_enabled') && !life_travel_is_offline_cache_enabled()) {
        return $urls;
    }
    
    // Ajouter les ressources d'images essentielles au cache offline
    $image_urls = array(
        LIFE_TRAVEL_EXCURSION_URL . 'assets/img/logo.png',
        LIFE_TRAVEL_EXCURSION_URL . 'assets/img/icon-192.png',
        LIFE_TRAVEL_EXCURSION_URL . 'assets/img/icon-512.png',
        LIFE_TRAVEL_EXCURSION_URL . 'assets/img/offline-banner.jpg',
        LIFE_TRAVEL_EXCURSION_URL . 'assets/sprite.svg'
    );
    
    $urls = array_merge($urls, $image_urls);
    
    // Ajouter toutes les icônes SVG prioritaires
    $priority_icons = array('wifi', 'wifi-off', 'warning', 'info', 'sync', 'home');
    
    foreach ($priority_icons as $icon) {
        $urls[] = LIFE_TRAVEL_SVG_URL . $icon . '.svg';
    }
    
    return $urls;
}

/**
 * Vérifie si le système d'images optimisées est actif
 * Utilise le système centralisé d'options pour éviter les dépendances circulaires
 */
function life_travel_use_optimized_images() {
    if (function_exists('life_travel_bridge_get_option')) {
        return life_travel_bridge_get_option('life_travel_use_optimized_images', true);
    }
    return get_option('life_travel_use_optimized_images', true);
}

/**
 * Bascule entre le système d'images optimisées et le système original
 */
function life_travel_switch_to_optimized_images($use_optimized = true) {
    // Utiliser la fonction centralisée si disponible
    if (function_exists('life_travel_bridge_get_option')) {
        update_option('life_travel_use_optimized_images', $use_optimized);
    } else {
        update_option('life_travel_use_optimized_images', $use_optimized);
    }
    
    if ($use_optimized) {
        // Générer le sprite SVG si nécessaire
        life_travel_rebuild_sprite_svg();
    }
    
    return $use_optimized;
}

/**
 * Construit le sprite SVG unifié
 */
function life_travel_rebuild_sprite_svg() {
    // Chemin vers le dossier des SVG individuels
    $svg_dir = LIFE_TRAVEL_EXCURSION_DIR . 'assets/svg';
    $sprite_path = LIFE_TRAVEL_EXCURSION_DIR . 'assets/sprite.svg';
    
    // Vérifier si le dossier existe
    if (!file_exists($svg_dir)) {
        return false;
    }
    
    // Commencer le contenu du sprite
    $sprite_content = '<?xml version="1.0" encoding="UTF-8"?>';
    $sprite_content .= '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="0" height="0" style="position:absolute">';
    
    // Obtenir tous les fichiers SVG
    $svg_files = glob($svg_dir . '/*.svg');
    
    foreach ($svg_files as $svg_file) {
        $icon_name = basename($svg_file, '.svg');
        $svg_content = file_get_contents($svg_file);
        
        // Extraire le contenu SVG
        preg_match('/<svg[^>]*>(.*?)<\/svg>/is', $svg_content, $matches);
        
        if (isset($matches[1])) {
            $inner_content = $matches[1];
            
            // Extraire les attributs viewBox, width et height
            preg_match('/viewBox=["\']([^"\']*)["\']/', $svg_content, $viewbox_match);
            $viewbox = isset($viewbox_match[1]) ? $viewbox_match[1] : '0 0 24 24';
            
            // Ajouter au sprite
            $sprite_content .= '<symbol id="icon-' . esc_attr($icon_name) . '" viewBox="' . esc_attr($viewbox) . '">' . $inner_content . '</symbol>';
        }
    }
    
    // Fermer le sprite
    $sprite_content .= '</svg>';
    
    // Écrire le fichier
    return file_put_contents($sprite_path, $sprite_content);
}

/**
 * Obtient l'URL d'une icône SVG
 *
 * @param string $icon_name Nom de l'icône (sans extension)
 * @param array $args Arguments optionnels (classe, largeur, hauteur, etc.)
 * @return string HTML de l'icône
 */
function life_travel_get_icon($icon_name, $args = array()) {
    $defaults = array(
        'class' => '',
        'width' => '24',
        'height' => '24',
        'aria-hidden' => 'true',
        'role' => 'img',
        'echo' => true
    );
    
    $args = wp_parse_args($args, $defaults);
    $classes = 'lt-icon lt-icon-' . $icon_name;
    
    if (!empty($args['class'])) {
        $classes .= ' ' . $args['class'];
    }
    
    if (life_travel_use_optimized_images()) {
        // Utiliser le sprite SVG
        $svg = '<svg class="' . esc_attr($classes) . '" width="' . esc_attr($args['width']) . '" height="' . esc_attr($args['height']) . '" aria-hidden="' . esc_attr($args['aria-hidden']) . '" role="' . esc_attr($args['role']) . '">';
        $svg .= '<use xlink:href="' . esc_url(LIFE_TRAVEL_EXCURSION_URL . 'assets/sprite.svg#icon-' . $icon_name) . '"></use>';
        $svg .= '</svg>';
    } else {
        // Utiliser le SVG indépendant
        $svg_url = LIFE_TRAVEL_EXCURSION_URL . 'assets/svg/' . $icon_name . '.svg';
        $svg = '<img src="' . esc_url($svg_url) . '" class="' . esc_attr($classes) . '" width="' . esc_attr($args['width']) . '" height="' . esc_attr($args['height']) . '" aria-hidden="' . esc_attr($args['aria-hidden']) . '" role="' . esc_attr($args['role']) . '" alt="" />';
    }
    
    if ($args['echo']) {
        echo $svg;
    }
    
    return $svg;
}

/**
 * Obtient une image optimisée
 *
 * @param string $image_name Nom de l'image (sans extension)
 * @param string $size Taille de l'image (small, medium, large)
 * @param array $args Arguments optionnels
 * @return string HTML de l'image
 */
function life_travel_get_image($image_name, $size = 'medium', $args = array()) {
    $defaults = array(
        'class' => '',
        'alt' => '',
        'lazy' => true,
        'width' => '',
        'height' => '',
        'fallback' => true,
        'echo' => true
    );
    
    $args = wp_parse_args($args, $defaults);
    $classes = 'lt-image lt-image-' . $image_name;
    
    if (!empty($args['class'])) {
        $classes .= ' ' . $args['class'];
    }
    
    if ($args['lazy']) {
        $classes .= ' lt-lazy';
    }
    
    // Déterminer le bon chemin d'image
    if (life_travel_use_optimized_images()) {
        // Utiliser les images optimisées
        $image_url = LIFE_TRAVEL_EXCURSION_URL . 'assets/img-opt/' . $image_name . '-' . $size . '.webp';
        $fallback_url = LIFE_TRAVEL_EXCURSION_URL . 'assets/img-opt/' . $image_name . '-' . $size . '.jpg';
    } else {
        // Utiliser les images originales
        $image_url = LIFE_TRAVEL_EXCURSION_URL . 'assets/img/' . $image_name . '.jpg';
        $fallback_url = $image_url;
    }
    
    $width_attr = !empty($args['width']) ? ' width="' . esc_attr($args['width']) . '"' : '';
    $height_attr = !empty($args['height']) ? ' height="' . esc_attr($args['height']) . '"' : '';
    
    $html = '';
    
    if (life_travel_use_optimized_images() && $args['fallback']) {
        // Version avec picture et fallback pour compatibilité
        $html .= '<picture>';
        $html .= '<source srcset="' . esc_url($image_url) . '" type="image/webp">';
        $html .= '<img src="' . esc_url($fallback_url) . '" class="' . esc_attr($classes) . '"' . $width_attr . $height_attr . ' alt="' . esc_attr($args['alt']) . '"';
        
        if ($args['lazy']) {
            $html .= ' loading="lazy"';
        }
        
        $html .= '>';
        $html .= '</picture>';
    } else {
        // Version simple
        $html .= '<img src="' . esc_url($image_url) . '" class="' . esc_attr($classes) . '"' . $width_attr . $height_attr . ' alt="' . esc_attr($args['alt']) . '"';
        
        if ($args['lazy']) {
            $html .= ' loading="lazy"';
        }
        
        $html .= '>';
    }
    
    if ($args['echo']) {
        echo $html;
    }
    
    return $html;
}

/**
 * Crée la structure de dossiers nécessaire pour les images optimisées
 */
function life_travel_ensure_image_directories() {
    $dirs = array(
        LIFE_TRAVEL_SVG_DIR,
        LIFE_TRAVEL_OPTIMIZED_IMAGES_DIR,
        LIFE_TRAVEL_OPTIMIZED_IMAGES_DIR . 'fallback'
    );
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
}

/**
 * Optimise les SVG existants et les place dans le dossier unifié
 */
function life_travel_optimize_existing_svgs() {
    // Vérifier si on utilise les images optimisées
    if (!life_travel_use_optimized_images()) {
        return;
    }
    
    // Créer le dossier SVG s'il n'existe pas
    $svg_dir = LIFE_TRAVEL_EXCURSION_DIR . 'assets/svg';
    if (!file_exists($svg_dir)) {
        wp_mkdir_p($svg_dir);
    }
    
    // Tableau des emplacements connus de SVG
    $svg_locations = array(
        LIFE_TRAVEL_EXCURSION_DIR . 'assets/images' => array('facebook-icon.svg', 'lock-icon.svg', 'shield-icon.svg'),
        LIFE_TRAVEL_EXCURSION_DIR . 'assets/img' => array('offline-placeholder.svg', 'placeholder.svg')
    );
    
    // Parcourir les emplacements
    foreach ($svg_locations as $directory => $files) {
        if (!file_exists($directory)) {
            continue;
        }
        
        foreach ($files as $file) {
            $source_file = $directory . '/' . $file;
            $target_file = $svg_dir . '/' . $file;
            
            if (file_exists($source_file) && !file_exists($target_file)) {
                // Lire le contenu du SVG
                $svg_content = file_get_contents($source_file);
                
                // Optimiser le SVG (supprimer les commentaires, espaces, etc.)
                $svg_content = preg_replace('/<!--.*?-->/s', '', $svg_content);
                $svg_content = preg_replace('/\s+/', ' ', $svg_content);
                $svg_content = str_replace('> <', '><', $svg_content);
                
                // Écrire le SVG optimisé
                file_put_contents($target_file, $svg_content);
            }
        }
    }
    
    // Construire le sprite SVG
    life_travel_rebuild_sprite_svg();
}

/**
 * Enregistre les styles CSS pour les icônes
 */
function life_travel_register_icon_styles() {
    // Vérifier si on utilise les images optimisées
    if (life_travel_use_optimized_images()) {
        // Ajouter les styles personnalisés pour les icônes
        add_action('wp_head', 'life_travel_print_icon_styles');
        add_action('admin_head', 'life_travel_print_icon_styles');
    }
}

/**
 * Imprime les styles CSS pour les icônes
 */
function life_travel_print_icon_styles() {
    ?>
    <style>
        .lt-icon {
            display: inline-block;
            vertical-align: middle;
            width: 1em;
            height: 1em;
            stroke-width: 0;
            fill: currentColor;
        }
        
        .lt-icon-btn {
            cursor: pointer;
            padding: 0.5em;
            background: transparent;
            border: none;
            color: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .lt-lazy {
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .lt-lazy.loaded {
            opacity: 1;
        }
    </style>
    <?php
}

/**
 * Ajoute un script pour le chargement différé des images
 */
function life_travel_add_lazy_loading_script() {
    // Vérifier si on utilise les images optimisées
    if (life_travel_use_optimized_images()) {
        add_action('wp_footer', 'life_travel_print_lazy_loading_script');
    }
}

/**
 * Imprime le script pour le chargement différé des images
 */
function life_travel_print_lazy_loading_script() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var lazyImages = document.querySelectorAll('.lt-lazy');
        
        if ('IntersectionObserver' in window) {
            var imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var image = entry.target;
                        image.classList.add('loaded');
                        imageObserver.unobserve(image);
                    }
                });
            });
            
            lazyImages.forEach(function(image) {
                imageObserver.observe(image);
            });
        } else {
            // Fallback pour les navigateurs qui ne supportent pas IntersectionObserver
            lazyImages.forEach(function(image) {
                image.classList.add('loaded');
            });
        }
    });
    </script>
    <?php
}

// Initialisation
function life_travel_init_images_bridge() {
    // Créer la structure de dossiers nécessaire
    life_travel_ensure_image_directories();
    
    // Vérifier si l'optimisation des images est activée via le système centralisé
    $use_optimized_images = life_travel_use_optimized_images();
    
    // Optimiser les SVG et générer le sprite si nécessaire
    // Utiliser la fonction centralisée si disponible
    $use_svg_system = function_exists('life_travel_bridge_get_option') ?
        life_travel_bridge_get_option('life_travel_use_svg_system', false) :
        get_option('life_travel_use_svg_system', false);
        
    if ($use_svg_system) {
        life_travel_optimize_existing_svgs();
        add_filter('upload_mimes', 'life_travel_add_svg_mime_support');
        add_action('wp_footer', 'life_travel_include_svg_sprite');
    }
    
    // Enregistrer les styles et scripts pour les icônes
    life_travel_register_icon_styles();
    life_travel_add_lazy_loading_script();
    
    // Intégration avec le pont PWA - utiliser la fonction partagée si disponible
    if (function_exists('life_travel_is_offline_cache_enabled') && life_travel_is_offline_cache_enabled()) {
        // Ajouter les ressources d'images au cache offline
        add_filter('life_travel_offline_cache_urls', 'life_travel_add_image_assets_to_pwa_cache');
    }
    
    // Filtrer les images WordPress si l'optimisation est activée
    if ($use_optimized_images) {
        add_filter('wp_get_attachment_image_attributes', 'life_travel_filter_image_attributes', 10, 3);
        add_filter('the_content', 'life_travel_content_image_to_picture');
    }
    
    // Définir les fonctions partagées pour éviter les dépendances circulaires
    if (function_exists('life_travel_define_shared_function')) {
        life_travel_define_shared_function('life_travel_get_optimized_picture_tag_shared', 'life_travel_get_optimized_picture_tag');
        life_travel_define_shared_function('life_travel_filter_image_attributes_shared', 'life_travel_filter_image_attributes');
    }
}

// Initialiser le pont d'images
// S'assurer qu'il n'est pas initialisé plusieurs fois
if (!defined('LIFE_TRAVEL_IMAGES_INITIALIZED')) {
    add_action('init', 'life_travel_init_images_bridge');
    define('LIFE_TRAVEL_IMAGES_INITIALIZED', true);
}
