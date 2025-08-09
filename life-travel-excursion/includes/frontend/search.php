<?php
/**
 * Frontend search shortcode and AJAX for suggestions
 */
defined('ABSPATH') || exit;

function lte_search_shortcode() {
    ob_start();
    ?>
    <form class="lte-search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <input type="search" name="s" placeholder="<?php echo esc_attr(get_theme_mod('lte_search_placeholder','Search excursions...')); ?>" autocomplete="off" />
        <button type="submit"><span class="dashicons dashicons-search"></span></button>
        <div class="lte-search-results"></div>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('lte_search', 'lte_search_shortcode');

// AJAX suggestions
function lte_search_suggestions() {
    check_ajax_referer('lte_search', 'nonce');
    $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
    if (strlen($term) < 2) {
        wp_send_json([]);
    }
    $query = new WP_Query([
        'post_type'      => ['product','post'],
        's'              => $term,
        'posts_per_page' => 5,
        'post_status'    => 'publish',
    ]);
    $results = [];
    while ($query->have_posts()) {
        $query->the_post();
        $results[] = [
            'title' => get_the_title(),
            'link'  => get_permalink(),
            'thumb' => get_the_post_thumbnail_url(get_the_ID(),'thumbnail'),
            'type'  => get_post_type(),
        ];
    }
    wp_reset_postdata();
    wp_send_json($results);
}
add_action('wp_ajax_lte_search_suggestions','lte_search_suggestions');
add_action('wp_ajax_nopriv_lte_search_suggestions','lte_search_suggestions');
