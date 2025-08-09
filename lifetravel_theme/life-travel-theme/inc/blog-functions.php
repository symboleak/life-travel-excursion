<?php
/**
 * Blog functions for Life Travel
 *
 * @package Life_Travel
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if user has completed order for specific excursion
 *
 * @param int $excursion_id The excursion post ID
 * @return bool True if user can view exclusive content
 */
function life_travel_verify_excursion_participant($excursion_id) {
    if (!$excursion_id || !is_user_logged_in()) {
        return false;
    }

    // Get current user
    $current_user = wp_get_current_user();
    
    // Check if user is admin or editor (always allow access)
    if (current_user_can('edit_posts')) {
        return true;
    }
    
    // Get related excursion product ID
    $related_product_id = get_post_meta($excursion_id, '_related_product_id', true);
    
    if (!$related_product_id) {
        return false;
    }
    
    // Check if WooCommerce is active
    if (class_exists('WooCommerce')) {
        // Get customer orders
        $orders = wc_get_orders(array(
            'customer_id' => $current_user->ID,
            'status' => 'completed',
            'limit' => -1,
        ));
        
        if ($orders) {
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    
                    // Check if this order contains the related product
                    if ($product_id == $related_product_id) {
                        return true;
                    }
                }
            }
        }
    }
    
    return false;
}

/**
 * Verifies if user can comment on excursion post
 *
 * @param int $post_id The post ID
 * @return bool True if user can comment
 */
function life_travel_can_comment_on_excursion($post_id) {
    // Always allow admin and editors to comment
    if (current_user_can('edit_posts')) {
        return true;
    }
    
    // Verify if normal user is a participant
    return life_travel_verify_excursion_participant($post_id);
}

/**
 * Custom comment form validation for excursion posts
 *
 * @param array $commentdata Comment data
 * @return array Filtered comment data
 */
function life_travel_validate_excursion_comment($commentdata) {
    $post_id = $commentdata['comment_post_ID'];
    $post_type = get_post_type($post_id);
    
    // Only apply validation to excursion posts in 'excursion' category
    if ($post_type === 'post' && has_category('excursion', $post_id)) {
        // Check nonce first for security
        if (!isset($_POST['excursion_comment_nonce']) || 
            !wp_verify_nonce($_POST['excursion_comment_nonce'], 'excursion_comment_' . $post_id)) {
            wp_die(__('La validation de sécurité a échoué. Veuillez réessayer.', 'life-travel'));
        }
        
        // Check if user is allowed to comment
        if (!life_travel_can_comment_on_excursion($post_id)) {
            wp_die(__('Seuls les participants de cette excursion peuvent commenter.', 'life-travel'));
        }
    }
    
    return $commentdata;
}
add_filter('preprocess_comment', 'life_travel_validate_excursion_comment');

/**
 * Add nonce field to comment form for excursion posts
 */
function life_travel_add_comment_nonce() {
    if (is_singular('post') && has_category('excursion')) {
        wp_nonce_field('excursion_comment_' . get_the_ID(), 'excursion_comment_nonce');
    }
}
add_action('comment_form_top', 'life_travel_add_comment_nonce');

/**
 * Filter comments to show message for non-participants
 *
 * @param string $comment_template Comment template path
 * @return string Modified template path
 */
function life_travel_comments_template($comment_template) {
    if (is_singular('post') && has_category('excursion')) {
        if (!life_travel_can_comment_on_excursion(get_the_ID()) && !is_user_logged_in()) {
            echo '<div class="excursion-comments-notice">';
            echo '<p>' . esc_html__('Seuls les participants à cette excursion peuvent voir et publier des commentaires.', 'life-travel') . '</p>';
            echo '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="button">' . esc_html__('Se connecter', 'life-travel') . '</a>';
            echo '</div>';
            return locate_template('templates/no-comments.php');
        }
    }
    
    return $comment_template;
}
add_filter('comments_template', 'life_travel_comments_template', 10, 1);

/**
 * Create 'excursion_guest' role dynamically for participants
 *
 * @param int $user_id User ID
 * @param int $excursion_id Excursion post ID
 * @return void
 */
function life_travel_add_excursion_guest_role($user_id, $excursion_id) {
    // Create custom capability for this specific excursion
    $cap_name = 'access_excursion_' . $excursion_id;
    
    // Add capability to user
    $user = get_user_by('id', $user_id);
    $user->add_cap($cap_name);
    
    // Also add generic excursion guest role if doesn't exist
    $role_exists = get_role('excursion_guest');
    if (!$role_exists) {
        add_role(
            'excursion_guest',
            __('Participant d\'excursion', 'life-travel'),
            array(
                'read' => true,
                'comment' => true,
                'upload_files' => false,
                'access_excursion_content' => true,
            )
        );
    }
}

/**
 * Register exclusive gallery shortcode
 */
function life_travel_exclusive_gallery_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => get_the_ID(),
    ), $atts, 'lt_exclusive_gallery');
    
    $excursion_id = intval($atts['id']);
    
    // Verify user access
    if (!life_travel_verify_excursion_participant($excursion_id)) {
        return '<div class="exclusive-content-restricted">
            <p>' . esc_html__('Ce contenu est réservé aux participants de l\'excursion.', 'life-travel') . '</p>
            <p><a href="' . esc_url(get_permalink(wc_get_page_id('shop'))) . '" class="button">' . 
            esc_html__('Découvrir nos excursions', 'life-travel') . '</a></p>
        </div>';
    }
    
    // Generate nonce for downloading images
    $download_nonce = wp_create_nonce('download_exclusive_' . $excursion_id);
    
    // Get exclusive gallery from ACF
    $gallery = get_field('excursion_exclusive_gallery', $excursion_id);
    if (!$gallery) {
        return '<div class="exclusive-gallery-empty">
            <p>' . esc_html__('Aucune image exclusive disponible pour le moment.', 'life-travel') . '</p>
        </div>';
    }
    
    $output = '<div class="exclusive-gallery" data-excursion-id="' . esc_attr($excursion_id) . '">';
    $output .= '<div class="gallery-controls">';
    $output .= '<a href="' . esc_url(admin_url('admin-ajax.php?action=download_exclusive_gallery&excursion_id=' . $excursion_id . '&nonce=' . $download_nonce)) . '" class="download-all">' . 
        esc_html__('Télécharger toutes les photos', 'life-travel') . '</a>';
    $output .= '</div>';
    
    $output .= '<div class="gallery-grid">';
    foreach ($gallery as $image) {
        $output .= '<div class="gallery-item">';
        $output .= '<a href="' . esc_url($image['url']) . '" data-lightbox="exclusive-gallery">';
        $output .= '<img src="' . esc_url($image['sizes']['medium']) . '" alt="' . esc_attr($image['alt']) . '">';
        $output .= '</a>';
        $output .= '<a href="' . esc_url(admin_url('admin-ajax.php?action=download_exclusive_image&image_id=' . $image['ID'] . '&excursion_id=' . $excursion_id . '&nonce=' . $download_nonce)) . '" class="download-image" title="' . esc_attr__('Télécharger', 'life-travel') . '">';
        $output .= '<span class="dashicons dashicons-download"></span>';
        $output .= '</a>';
        $output .= '</div>';
    }
    $output .= '</div>'; // .gallery-grid
    $output .= '</div>'; // .exclusive-gallery
    
    return $output;
}
add_shortcode('lt_exclusive_gallery', 'life_travel_exclusive_gallery_shortcode');

/**
 * AJAX handler for downloading exclusive images
 */
function life_travel_download_exclusive_image() {
    // Security checks
    $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';
    $excursion_id = isset($_GET['excursion_id']) ? intval($_GET['excursion_id']) : 0;
    $image_id = isset($_GET['image_id']) ? intval($_GET['image_id']) : 0;
    
    if (!wp_verify_nonce($nonce, 'download_exclusive_' . $excursion_id) || 
        !life_travel_verify_excursion_participant($excursion_id) || 
        !$image_id) {
        wp_die(__('Accès non autorisé.', 'life-travel'));
    }
    
    // Get image data
    $file_path = get_attached_file($image_id);
    if (!$file_path || !file_exists($file_path)) {
        wp_die(__('Image non trouvée.', 'life-travel'));
    }
    
    // Get image info
    $file_info = pathinfo($file_path);
    $file_name = basename($file_path);
    
    // Set headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    ob_clean();
    flush();
    readfile($file_path);
    exit;
}
add_action('wp_ajax_download_exclusive_image', 'life_travel_download_exclusive_image');
add_action('wp_ajax_nopriv_download_exclusive_image', 'life_travel_download_exclusive_image');

/**
 * AJAX handler for downloading all exclusive gallery as ZIP
 */
function life_travel_download_exclusive_gallery() {
    // Security checks
    $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';
    $excursion_id = isset($_GET['excursion_id']) ? intval($_GET['excursion_id']) : 0;
    
    if (!wp_verify_nonce($nonce, 'download_exclusive_' . $excursion_id) || 
        !life_travel_verify_excursion_participant($excursion_id)) {
        wp_die(__('Accès non autorisé.', 'life-travel'));
    }
    
    // Get gallery
    $gallery = get_field('excursion_exclusive_gallery', $excursion_id);
    if (!$gallery) {
        wp_die(__('Aucune image disponible.', 'life-travel'));
    }
    
    // Create temporary file for ZIP
    $temp_file = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive();
    if ($zip->open($temp_file, ZipArchive::CREATE) !== true) {
        wp_die(__('Impossible de créer l\'archive ZIP.', 'life-travel'));
    }
    
    // Add images to ZIP
    foreach ($gallery as $image) {
        $file_path = get_attached_file($image['ID']);
        if ($file_path && file_exists($file_path)) {
            $zip->addFile($file_path, basename($file_path));
        }
    }
    
    $zip->close();
    
    // Get post title for zip filename
    $post_title = get_the_title($excursion_id);
    $safe_title = sanitize_title($post_title);
    $zip_name = 'excursion-' . $safe_title . '-photos.zip';
    
    // Send ZIP file to browser
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_name . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($temp_file));
    ob_clean();
    flush();
    readfile($temp_file);
    unlink($temp_file); // Delete temporary file
    exit;
}
add_action('wp_ajax_download_exclusive_gallery', 'life_travel_download_exclusive_gallery');
add_action('wp_ajax_nopriv_download_exclusive_gallery', 'life_travel_download_exclusive_gallery');

/**
 * Add Open Graph meta tags for blog posts
 */
function life_travel_add_opengraph_tags() {
    if (!is_singular('post') || !has_category('excursion')) {
        return;
    }
    
    global $post;
    
    // Get post data
    $post_title = get_the_title();
    $post_url = get_permalink();
    $post_desc = has_excerpt() ? get_the_excerpt() : wp_trim_words(get_the_content(), 30, '...');
    
    // Get featured image
    $image = '';
    if (has_post_thumbnail()) {
        $image_data = wp_get_attachment_image_src(get_post_thumbnail_id(), 'large');
        $image = $image_data[0];
    }
    
    // Output Open Graph tags
    echo '<meta property="og:title" content="' . esc_attr($post_title) . '" />';
    echo '<meta property="og:type" content="article" />';
    echo '<meta property="og:url" content="' . esc_url($post_url) . '" />';
    
    if ($image) {
        echo '<meta property="og:image" content="' . esc_url($image) . '" />';
    }
    
    echo '<meta property="og:description" content="' . esc_attr($post_desc) . '" />';
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />';
    
    // Article specific tags
    echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c')) . '" />';
    echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c')) . '" />';
    echo '<meta property="article:section" content="Excursions" />';
    
    // Get tags
    $tags = get_the_tags();
    if ($tags) {
        foreach ($tags as $tag) {
            echo '<meta property="article:tag" content="' . esc_attr($tag->name) . '" />';
        }
    }
}
add_action('wp_head', 'life_travel_add_opengraph_tags');

/**
 * Register "Excursion Recap" Gutenberg block
 */
function life_travel_register_excursion_recap_block() {
    // Register script
    wp_register_script(
        'life-travel-excursion-recap-block',
        get_stylesheet_directory_uri() . '/assets/js/blocks/excursion-recap.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        LIFE_TRAVEL_VERSION
    );
    
    // Register block
    register_block_type('life-travel/excursion-recap', array(
        'editor_script' => 'life-travel-excursion-recap-block',
        'render_callback' => 'life_travel_render_excursion_recap_block',
        'attributes' => array(
            'relatedProductId' => array(
                'type' => 'number',
                'default' => 0,
            ),
        ),
    ));
}
add_action('init', 'life_travel_register_excursion_recap_block');

/**
 * Server-side rendering for excursion recap block
 */
function life_travel_render_excursion_recap_block($attributes) {
    $related_product_id = isset($attributes['relatedProductId']) ? intval($attributes['relatedProductId']) : 0;
    
    // Save related product ID as post meta
    if ($related_product_id && get_the_ID()) {
        update_post_meta(get_the_ID(), '_related_product_id', $related_product_id);
    }
    
    ob_start();
    ?>
    <div class="excursion-recap">
        <?php if ($related_product_id): ?>
            <?php
            // Get product data if WooCommerce is active
            if (class_exists('WooCommerce')) {
                $product = wc_get_product($related_product_id);
                if ($product) {
                    ?>
                    <div class="excursion-product-link">
                        <p>
                            <?php echo esc_html__('Cette excursion correspond au produit: ', 'life-travel'); ?>
                            <a href="<?php echo esc_url(get_permalink($related_product_id)); ?>"><?php echo esc_html($product->get_name()); ?></a>
                        </p>
                    </div>
                    <?php
                }
            }
            ?>
        <?php else: ?>
            <div class="excursion-product-link-empty">
                <p><?php echo esc_html__('Aucun produit d\'excursion associé.', 'life-travel'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Add upload security for excursion media
 */
function life_travel_secure_uploads($mimes) {
    // Only allow image and MP4 uploads for non-administrators
    if (!current_user_can('administrator')) {
        $mimes = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'mp4|m4v' => 'video/mp4',
        );
    }
    
    return $mimes;
}
add_filter('upload_mimes', 'life_travel_secure_uploads');

/**
 * Restrict access to excursion exclusive media
 */
function life_travel_restrict_attachment_access($template) {
    if (is_attachment() && !current_user_can('edit_posts')) {
        $attachment_id = get_the_ID();
        $post_parent = wp_get_post_parent_id($attachment_id);
        
        // Check if this attachment is part of an excursion post
        if ($post_parent && has_category('excursion', $post_parent)) {
            // Check if current user is a participant
            if (!life_travel_verify_excursion_participant($post_parent)) {
                // Redirect to parent post
                wp_redirect(get_permalink($post_parent));
                exit;
            }
        }
    }
    
    return $template;
}
add_filter('template_include', 'life_travel_restrict_attachment_access');
