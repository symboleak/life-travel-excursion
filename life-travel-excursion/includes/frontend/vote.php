<?php
/**
 * Frontend voting shortcode and AJAX
 */
defined('ABSPATH') || exit;

function lte_vote_shortcode($atts) {
    global $post;
    $enabled = get_post_meta($post->ID, '_lte_enable_vote', true);
    if ($enabled !== 'on') return '';
    ob_start();
    ?>
    <div class="lte-vote" data-product-id="<?php echo esc_attr($post->ID); ?>">
        <button class="lte-vote-button" data-vote="like"><?php _e('👍 Vote','life-travel-excursion'); ?></button>
        <div class="lte-vote-results"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('lte_vote', 'lte_vote_shortcode');

function lte_vote_submit_handler() {
    check_ajax_referer('lte_vote', 'nonce');
    $post_id = intval($_POST['product_id']);
    $user_id = get_current_user_id();
    if (!$user_id || !$post_id) wp_send_json_error();
    $table = $GLOBALS['wpdb']->prefix . 'lte_votes';
    // Prevent multiple votes
    $exists = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
        "SELECT COUNT(*) FROM $table WHERE user_id=%d AND product_id=%d", $user_id, $post_id
    ));
    if ($exists) wp_send_json_error(['message'=>__('Already voted','life-travel-excursion')]);
    $GLOBALS['wpdb']->insert($table, ['user_id'=>$user_id,'product_id'=>$post_id,'vote'=>1,'timestamp'=>current_time('mysql')]);
    wp_send_json_success();
}
add_action('wp_ajax_lte_vote_submit','lte_vote_submit_handler');

function lte_vote_results_shortcode($atts) {
    global $post;
    $table = $GLOBALS['wpdb']->prefix . 'lte_votes';
    $count = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
        "SELECT COUNT(*) FROM $table WHERE product_id=%d", $post->ID
    ));
    return '<div class="lte-vote-summary">'.sprintf(_n('%d vote','%d votes',$count,'life-travel-excursion'), $count).'</div>';
}
add_shortcode('lte_vote_results', 'lte_vote_results_shortcode');
