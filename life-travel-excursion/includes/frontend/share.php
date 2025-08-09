<?php
/**
 * Social sharing buttons with loyalty share bonus
 */
defined('ABSPATH') || exit;

// Enqueue share JS
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('lte-share', plugin_dir_url(__FILE__).'../assets/js/share.js', ['jquery'], false, true);
    wp_localize_script('lte-share', 'lte_share_params', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('lte_loyalty_share'),
    ]);
});

// Display share buttons on product pages
add_action('woocommerce_after_single_product', 'lte_share_buttons');
add_filter('the_content', 'lte_share_buttons_blog');

function lte_share_buttons() {
    if (!is_user_logged_in() || !get_theme_mod('lte_loyalty_enabled', false)) return;
    $points = get_theme_mod('lte_loyalty_share_bonus_points', 10);
    $url = urlencode(get_permalink());
    $title = urlencode(get_the_title());
    echo '<div class="lte-share-buttons"><p>'.sprintf(__('Partager et gagnez %d points','life-travel-excursion'),$points).'</p>';
    echo '<button class="lte-share-btn" data-network="facebook" data-url="'.$url.'" data-post-id="'.get_the_ID().'">Facebook</button>';
    echo '<button class="lte-share-btn" data-network="twitter" data-url="'.$url.'" data-post-id="'.get_the_ID().'">Twitter</button>';
    if (get_theme_mod('lte_enable_instagram_share', false)) {
        echo '<button class="lte-share-btn" data-network="instagram" data-url="'.$url.'" data-post-id="'.get_the_ID().'">Instagram</button>';
    }
    echo '<button class="lte-share-btn" data-network="whatsapp" data-url="'.$url.'" data-post-id="'.get_the_ID().'">WhatsApp</button>';
    echo '</div>';
}

function lte_share_buttons_blog($content) {
    if (is_singular('post') && is_user_logged_in() && get_theme_mod('lte_loyalty_enabled', false)) {
        ob_start(); lte_share_buttons(); $share = ob_get_clean();
        return $content . $share;
    }
    return $content;
}

// AJAX handler to award share points
add_action('wp_ajax_lte_loyalty_award_share', 'lte_loyalty_award_share');
function lte_loyalty_award_share() {
    check_ajax_referer('lte_loyalty_share','nonce');
    $post_id = intval($_POST['post_id']);
    $user_id = get_current_user_id();
    if (get_user_meta($user_id, 'lte_shared_'. $post_id, true)) {
        wp_send_json_error(__('Déjà partagé','life-travel-excursion'));
    }
    $points = get_theme_mod('lte_loyalty_share_bonus_points', 10);
    // Award points
    lte_loyalty_add_points($user_id, $points, 'share_'.$post_id);
    update_user_meta($user_id, 'lte_shared_'. $post_id, 1);
    wp_send_json_success(sprintf(__('Vous avez gagné %d points','life-travel-excursion'),$points));
}
