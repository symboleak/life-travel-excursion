<?php
/**
 * Shortcodes Life Travel
 * Shortcodes pour l'affichage des éléments visuels et autres fonctionnalités
 * 
 * @package Life Travel
 * @version 1.0
 */

defined('ABSPATH') || exit;

/**
 * Classe des shortcodes Life Travel
 */
class Life_Travel_Shortcodes {
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Enregistrer les shortcodes
        add_shortcode('life_travel_gallery', array($this, 'gallery_shortcode'));
        add_shortcode('life_travel_logo', array($this, 'logo_shortcode'));
        add_shortcode('life_travel_video', array($this, 'video_shortcode'));
        add_shortcode('life_travel_icon', array($this, 'icon_shortcode'));
    }
    
    /**
     * Shortcode pour l'affichage d'une galerie d'images
     * 
     * @param array $atts Attributs du shortcode
     * @param string $content Contenu entre les balises du shortcode
     * @return string HTML de la galerie
     */
    public function gallery_shortcode($atts, $content = null) {
        // Attributs par défaut
        $atts = shortcode_atts(array(
            'ids' => '',
            'columns' => 3,
            'size' => 'medium',
            'link' => 'file',
            'captions' => 'true',
            'class' => '',
            'animation' => 'fade', // fade, slide, zoom
        ), $atts);
        
        // Sanitisation des attributs
        $ids = array_filter(array_map('absint', explode(',', $atts['ids'])));
        $columns = max(1, min(6, (int) $atts['columns']));
        $link = in_array($atts['link'], array('file', 'none', 'attachment')) ? $atts['link'] : 'file';
        $show_captions = filter_var($atts['captions'], FILTER_VALIDATE_BOOLEAN);
        $animation = in_array($atts['animation'], array('fade', 'slide', 'zoom')) ? $atts['animation'] : 'fade';
        
        // Si aucune ID n'est spécifiée, retourner un message
        if (empty($ids)) {
            if (current_user_can('edit_posts')) {
                return '<p class="life-travel-notice life-travel-warning">Veuillez spécifier des IDs d\'images pour la galerie.</p>';
            }
            return '';
        }
        
        // Préparer les classes CSS
        $gallery_classes = array(
            'life-travel-gallery',
            'gallery-columns-' . $columns,
            'animation-' . $animation,
            'gallery-link-' . $link
        );
        
        if (!empty($atts['class'])) {
            $gallery_classes[] = esc_attr($atts['class']);
        }
        
        // Début du HTML de la galerie
        $output = '<div class="' . esc_attr(implode(' ', $gallery_classes)) . '">';
        $output .= '<div class="gallery-grid">';
        
        // Boucle sur chaque image
        foreach ($ids as $id) {
            // Vérifier que l'ID correspond bien à une image
            if (!wp_attachment_is_image($id)) {
                continue;
            }
            
            // Récupérer les informations de l'image
            $image_data = wp_get_attachment_image_src($id, $atts['size']);
            $full_image = wp_get_attachment_image_src($id, 'full');
            $attachment = get_post($id);
            
            if (!$image_data) {
                continue;
            }
            
            // Préparer le HTML de l'image
            $output .= '<div class="gallery-image">';
            
            // Lien vers l'image pleine taille ou la page d'attachement
            if ($link === 'file') {
                $output .= '<a href="' . esc_url($full_image[0]) . '" class="lightbox" data-caption="' . esc_attr($attachment->post_excerpt) . '">';
            } elseif ($link === 'attachment') {
                $output .= '<a href="' . esc_url(get_attachment_link($id)) . '">';
            }
            
            // Image avec attributs responsifs
            $output .= '<img src="' . esc_url($image_data[0]) . '" alt="' . esc_attr(get_post_meta($id, '_wp_attachment_image_alt', true)) . '" width="' . esc_attr($image_data[1]) . '" height="' . esc_attr($image_data[2]) . '" loading="lazy">';
            
            // Légende si activée
            if ($show_captions && !empty($attachment->post_excerpt)) {
                $output .= '<div class="caption">' . esc_html($attachment->post_excerpt) . '</div>';
            }
            
            // Fermer le lien si nécessaire
            if ($link !== 'none') {
                $output .= '</a>';
            }
            
            $output .= '</div>'; // .gallery-image
        }
        
        $output .= '</div>'; // .gallery-grid
        $output .= '</div>'; // .life-travel-gallery
        
        return $output;
    }
    
    /**
     * Shortcode pour l'affichage du logo
     * 
     * @param array $atts Attributs du shortcode
     * @return string HTML du logo
     */
    public function logo_shortcode($atts) {
        // Attributs par défaut
        $atts = shortcode_atts(array(
            'version' => 'color', // color, white, dark
            'width' => '',
            'height' => '',
            'class' => '',
            'link' => 'yes', // yes, no
        ), $atts);
        
        // Sanitisation des attributs
        $version = in_array($atts['version'], array('color', 'white', 'dark')) ? $atts['version'] : 'color';
        $width = !empty($atts['width']) ? (int) $atts['width'] : '';
        $height = !empty($atts['height']) ? (int) $atts['height'] : '';
        $add_link = filter_var($atts['link'], FILTER_VALIDATE_BOOLEAN);
        
        // Déterminer le fichier du logo à utiliser
        $logo_file = 'logo-main.png'; // Utilise le PNG par défaut (meilleure compatibilité navigateurs)
        if ($version === 'white') {
            $logo_file = 'logo-white.svg';
        } elseif ($version === 'dark') {
            $logo_file = 'logo-dark.svg';
        }
        
        // Construire les attributs de l'image
        $logo_attrs = array(
            'class' => 'site-logo site-logo-' . $version . (!empty($atts['class']) ? ' ' . esc_attr($atts['class']) : ''),
            'alt' => get_bloginfo('name')
        );
        
        if ($width) {
            $logo_attrs['width'] = $width;
            $logo_attrs['style'] = 'max-width: ' . $width . 'px;';
        }
        
        if ($height) {
            $logo_attrs['height'] = $height;
            if (isset($logo_attrs['style'])) {
                $logo_attrs['style'] .= ' max-height: ' . $height . 'px;';
            } else {
                $logo_attrs['style'] = 'max-height: ' . $height . 'px;';
            }
        }
        
        // Construire le HTML
        $output = '';
        
        // Ajouter le lien si nécessaire
        if ($add_link) {
            $output .= '<a href="' . esc_url(home_url('/')) . '" rel="home">';
        }
        
        // Ajouter l'image du logo
        $logo_url = plugins_url('assets/img/logos/' . $logo_file, dirname(__FILE__));
        
        $output .= '<img src="' . esc_url($logo_url) . '"';
        foreach ($logo_attrs as $attr => $value) {
            $output .= ' ' . $attr . '="' . esc_attr($value) . '"';
        }
        $output .= '>';
        
        // Fermer le lien si nécessaire
        if ($add_link) {
            $output .= '</a>';
        }
        
        return $output;
    }
    
    /**
     * Shortcode pour l'affichage d'une vidéo
     * 
     * @param array $atts Attributs du shortcode
     * @return string HTML de la vidéo
     */
    public function video_shortcode($atts) {
        // Attributs par défaut
        $atts = shortcode_atts(array(
            'src' => '', // URL de la vidéo
            'youtube' => '', // ID YouTube
            'width' => '100%',
            'height' => 'auto',
            'autoplay' => 'false',
            'controls' => 'true',
            'loop' => 'false',
            'muted' => 'false',
            'poster' => '', // URL de l'image d'aperçu
            'class' => '',
            'lazy' => 'true', // Chargement différé
        ), $atts);
        
        // Sanitisation des attributs
        $autoplay = filter_var($atts['autoplay'], FILTER_VALIDATE_BOOLEAN);
        $controls = filter_var($atts['controls'], FILTER_VALIDATE_BOOLEAN);
        $loop = filter_var($atts['loop'], FILTER_VALIDATE_BOOLEAN);
        $muted = filter_var($atts['muted'], FILTER_VALIDATE_BOOLEAN);
        $lazy = filter_var($atts['lazy'], FILTER_VALIDATE_BOOLEAN);
        
        // Construire les classes CSS
        $video_classes = array('life-travel-video');
        if (!empty($atts['class'])) {
            $video_classes[] = esc_attr($atts['class']);
        }
        
        // Si c'est une vidéo YouTube
        if (!empty($atts['youtube'])) {
            $youtube_id = sanitize_text_field($atts['youtube']);
            
            // Si le chargement différé est activé, afficher une miniature
            if ($lazy) {
                $poster = !empty($atts['poster']) ? esc_url($atts['poster']) : 'https://img.youtube.com/vi/' . $youtube_id . '/maxresdefault.jpg';
                
                $output = '<div class="youtube-thumbnail ' . esc_attr(implode(' ', $video_classes)) . '" data-video-id="' . esc_attr($youtube_id) . '" style="width: ' . esc_attr($atts['width']) . '; position: relative; cursor: pointer;">';
                $output .= '<img src="' . esc_url($poster) . '" alt="Miniature YouTube" style="width: 100%; height: auto;">';
                $output .= '<div class="play-button" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 68px; height: 48px; background-color: rgba(0,0,0,0.7); border-radius: 10px; display: flex; align-items: center; justify-content: center;">';
                $output .= '<svg width="24" height="24" viewBox="0 0 24 24" fill="#fff"><path d="M8 5v14l11-7z"/></svg>';
                $output .= '</div>';
                $output .= '</div>';
                
                return $output;
            }
            
            // Sinon, afficher l'iframe YouTube directement
            $params = array(
                'autoplay' => $autoplay ? 1 : 0,
                'controls' => $controls ? 1 : 0,
                'loop' => $loop ? 1 : 0,
                'mute' => $muted ? 1 : 0,
                'rel' => 0
            );
            
            $youtube_url = add_query_arg($params, 'https://www.youtube.com/embed/' . $youtube_id);
            
            $output = '<iframe class="' . esc_attr(implode(' ', $video_classes)) . '" width="' . esc_attr($atts['width']) . '" height="' . esc_attr($atts['height']) . '" src="' . esc_url($youtube_url) . '" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
            
            return $output;
        }
        
        // Si c'est une vidéo hébergée
        if (!empty($atts['src'])) {
            $video_src = esc_url($atts['src']);
            
            // Construire les attributs de la balise video
            $video_attrs = array(
                'class' => implode(' ', $video_classes),
                'width' => esc_attr($atts['width']),
                'height' => esc_attr($atts['height'])
            );
            
            if ($autoplay) $video_attrs['autoplay'] = '';
            if ($controls) $video_attrs['controls'] = '';
            if ($loop) $video_attrs['loop'] = '';
            if ($muted) $video_attrs['muted'] = '';
            if (!empty($atts['poster'])) $video_attrs['poster'] = esc_url($atts['poster']);
            
            // Si le chargement différé est activé
            if ($lazy) {
                $video_attrs['data-src'] = $video_src;
                $video_attrs['data-autoplay'] = $autoplay ? 'true' : 'false';
                
                if ($autoplay) {
                    unset($video_attrs['autoplay']);
                }
            } else {
                $video_attrs['src'] = $video_src;
            }
            
            // Construire le HTML
            $output = '<video';
            foreach ($video_attrs as $attr => $value) {
                $output .= ' ' . $attr . ($value !== '' ? '="' . $value . '"' : '');
            }
            $output .= '>';
            
            // Si le chargement n'est pas différé, ajouter une source
            if (!$lazy) {
                $output .= '<source src="' . esc_url($video_src) . '" type="video/mp4">';
            }
            
            $output .= 'Votre navigateur ne prend pas en charge la lecture de vidéos HTML5.';
            $output .= '</video>';
            
            return $output;
        }
        
        // Si aucune source n'est spécifiée, retourner un message d'erreur pour les administrateurs
        if (current_user_can('edit_posts')) {
            return '<p class="life-travel-notice life-travel-error">Veuillez spécifier une source vidéo (src) ou un ID YouTube (youtube).</p>';
        }
        
        return '';
    }
    
    /**
     * Shortcode pour l'affichage d'une icône
     * 
     * @param array $atts Attributs du shortcode
     * @return string HTML de l'icône
     */
    public function icon_shortcode($atts) {
        // Attributs par défaut
        $atts = shortcode_atts(array(
            'name' => '', // Nom de l'icône
            'color' => '', // Couleur de l'icône
            'size' => 'medium', // small, medium, large
            'class' => '', // Classes CSS supplémentaires
        ), $atts);
        
        // Sanitisation des attributs
        $name = sanitize_text_field($atts['name']);
        $color = sanitize_hex_color($atts['color']);
        $size = in_array($atts['size'], array('small', 'medium', 'large')) ? $atts['size'] : 'medium';
        
        // Si aucun nom n'est spécifié, retourner un message d'erreur pour les administrateurs
        if (empty($name)) {
            if (current_user_can('edit_posts')) {
                return '<p class="life-travel-notice life-travel-error">Veuillez spécifier un nom d\'icône.</p>';
            }
            return '';
        }
        
        // Tableau des tailles d'icônes en pixels
        $size_map = array(
            'small' => '16px',
            'medium' => '24px',
            'large' => '36px'
        );
        
        // Construire les classes CSS
        $icon_classes = array(
            'life-travel-icon',
            'icon-' . $name,
            'icon-size-' . $size
        );
        
        if (!empty($atts['class'])) {
            $icon_classes[] = esc_attr($atts['class']);
        }
        
        // Style inline pour la couleur et la taille
        $style = '';
        if (!empty($color)) {
            $style .= 'color: ' . $color . ';';
        }
        
        $style .= 'width: ' . $size_map[$size] . '; height: ' . $size_map[$size] . ';';
        
        // Chemin vers le fichier d'icône
        $icon_path = 'assets/img/icons/' . $name . '.svg';
        $icon_url = plugins_url($icon_path, dirname(__FILE__));
        
        // Vérifier si le fichier existe
        $icon_file = dirname(dirname(__FILE__)) . '/' . $icon_path;
        
        if (file_exists($icon_file)) {
            // Si le fichier existe, l'inclure directement pour pouvoir modifier ses attributs
            $svg_content = file_get_contents($icon_file);
            
            // Ajouter les classes et le style aux attributs SVG
            $svg_content = preg_replace(
                '/<svg/',
                '<svg class="' . esc_attr(implode(' ', $icon_classes)) . '" style="' . esc_attr($style) . '"',
                $svg_content,
                1
            );
            
            return $svg_content;
        } else {
            // Fallback si le fichier n'existe pas
            return '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($name) . '" class="' . esc_attr(implode(' ', $icon_classes)) . '" style="' . esc_attr($style) . '">';
        }
    }
}

// Initialiser les shortcodes
new Life_Travel_Shortcodes();
