<?php
/**
 * Admin metabox for excursion voting
 */
defined('ABSPATH') || exit;

function lte_register_vote_metabox() {
    add_meta_box(
        'lte_vote',
        __('Enable Voting','life-travel-excursion'),
        'lte_vote_metabox_callback',
        'product',
        'side',
        'default'
    );
}
add_action('add_meta_boxes','lte_register_vote_metabox');

function lte_vote_metabox_callback($post) {
    wp_nonce_field('lte_vote_save','lte_vote_nonce');
    $enabled = get_post_meta($post->ID,'_lte_enable_vote',true);
    echo '<label><input type="checkbox" name="lte_enable_vote" '.checked($enabled,'on',false).' /> '.__('Enable voting for this excursion','life-travel-excursion').'</label>';
}

function lte_save_vote_metabox($post_id) {
    if (!isset($_POST['lte_vote_nonce']) || !wp_verify_nonce($_POST['lte_vote_nonce'],'lte_vote_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    $val = isset($_POST['lte_enable_vote']) ? 'on' : 'off';
    update_post_meta($post_id,'_lte_enable_vote',$val);
}
add_action('save_post_product','lte_save_vote_metabox');
