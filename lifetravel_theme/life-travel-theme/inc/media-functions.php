<?php
/**
 * Fonctions de gestion des médias et images pour Life Travel
 *
 * @package Life_Travel
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialiser les fonctions de gestion des images
 */
function life_travel_setup_media_functions() {
    // Ajouter la prise en charge des formats d'image modernes
    add_filter('upload_mimes', 'life_travel_custom_mime_types');
    
    // Optimiser les images lors de l'upload
    add_filter('wp_handle_upload', 'life_travel_optimize_uploaded_image');
    
    // Gérer le remplacement des images temporaires
    add_action('admin_enqueue_scripts', 'life_travel_admin_media_scripts');
    
    // Ajouter des tailles d'images adaptées aux excursions
    add_action('after_setup_theme', 'life_travel_register_image_sizes');
}
add_action('init', 'life_travel_setup_media_functions');

/**
 * Ajouter des tailles d'images adaptées aux excursions et optimisées pour le web
 */
function life_travel_register_image_sizes() {
    // Taille pour les miniatures d'excursion (optimisée pour mobile)
    add_image_size('excursion-thumbnail', 400, 300, true);
    
    // Taille pour les images de liste d'excursions
    add_image_size('excursion-card', 600, 400, true);
    
    // Taille pour les bannières d'excursion
    add_image_size('excursion-banner', 1200, 600, true);
    
    // Taille pour les galeries d'excursion
    add_image_size('excursion-gallery', 800, 600, true);
    
    // Miniatures carrées pour témoignages
    add_image_size('testimonial', 150, 150, true);
}

/**
 * Ajouter la prise en charge des formats d'image modernes (WebP, AVIF)
 * 
 * @param array $mimes Les types MIME autorisés
 * @return array Types MIME modifiés
 */
function life_travel_custom_mime_types($mimes) {
    // Activer WebP
    $mimes['webp'] = 'image/webp';
    
    // Activer AVIF si PHP 8.1+ (meilleure compression que WebP)
    if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
        $mimes['avif'] = 'image/avif';
    }
    
    return $mimes;
}

/**
 * Optimiser les images lors de l'upload (compression, métadonnées)
 * 
 * @param array $upload Les données d'upload
 * @return array Les données d'upload modifiées
 */
function life_travel_optimize_uploaded_image($upload) {
    // Vérifier si c'est une image
    if (strpos($upload['type'], 'image/') !== 0) {
        return $upload;
    }
    
    // Journaliser l'upload en mode débogage
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Image uploadée: ' . $upload['file']);
    }
    
    // Générer les tailles d'images additionnelles si GD ou Imagick est disponible
    if (extension_loaded('gd') || extension_loaded('imagick')) {
        // L'optimisation est gérée par WordPress
    }
    
    return $upload;
}

/**
 * Charger les scripts admin pour la gestion des images
 * 
 * @param string $hook_suffix Le hook d'admin actuel
 */
function life_travel_admin_media_scripts($hook_suffix) {
    // Charger uniquement sur les pages média et excursions
    if (!in_array($hook_suffix, array('upload.php', 'post.php', 'post-new.php'))) {
        return;
    }
    
    // Vérifier si nous sommes sur une page d'édition de produit WooCommerce
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'product') {
        // Ici le code pour charger les scripts nécessaires à la gestion des images
        // d'excursion dans l'admin
    }
}

/**
 * Créer une correspondance entre les images temporaires et les images WordPress
 * 
 * Cette fonction peut être appelée manuellement lors de la migration
 * du prototype vers WordPress pour remplacer les placeholders
 */
function life_travel_map_temporary_images() {
    // Définir les correspondances entre les images temporaires et les slugs/noms
    $image_mappings = array(
        'mont-cameroun.jpg' => 'excursion-mont-cameroun',
        'chutes-lobe.jpg' => 'excursion-chutes-lobe',
        'dja-reserve.jpg' => 'excursion-reserve-dja',
        'kribi-beach.jpg' => 'excursion-plages-kribi',
        'rhumsiki.jpg' => 'excursion-rhumsiki',
        'chefferie.jpg' => 'excursion-chefferie-bafoussam',
        'yaounde.jpg' => 'excursion-yaounde',
    );
    
    // En production, cette fonction rechercherait les correspondances dans la médiathèque
    // et mettrait à jour les liens dans le contenu
    
    return $image_mappings;
}

/**
 * Obtenir l'URL optimisée d'une image pour Life Travel
 * 
 * @param int|string $image_id ID de l'image ou nom de fichier de l'image temporaire
 * @param string $size Taille de l'image à récupérer
 * @return string URL de l'image
 */
function life_travel_get_image_url($image_id, $size = 'full') {
    // Si c'est un ID numérique, utiliser les fonctions WordPress
    if (is_numeric($image_id)) {
        return wp_get_attachment_image_url($image_id, $size);
    }
    
    // Sinon, c'est probablement un nom de fichier temporaire
    $mappings = life_travel_map_temporary_images();
    
    if (isset($mappings[$image_id])) {
        // Rechercher l'image par slug
        $args = array(
            'post_type' => 'attachment',
            'name' => $mappings[$image_id],
            'posts_per_page' => 1,
            'post_status' => 'inherit',
        );
        
        $query = new WP_Query($args);
        
        if (!empty($query->posts)) {
            return wp_get_attachment_image_url($query->posts[0]->ID, $size);
        }
    }
    
    // Retourner l'URL du placeholder si l'image n'est pas trouvée
    return get_template_directory_uri() . '/assets/images/placeholder.jpg';
}
