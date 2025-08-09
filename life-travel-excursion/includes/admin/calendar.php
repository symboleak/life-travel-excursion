<?php
/**
 * Meta box for excursion calendar dates
 */
defined('ABSPATH') || exit;

function lte_register_excursion_calendar_metabox() {
    add_meta_box(
        'lte_calendar',
        __('Excursion Dates','life-travel-excursion'),
        'lte_calendar_metabox_callback',
        'product',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'lte_register_excursion_calendar_metabox');

function lte_calendar_metabox_callback($post) {
    wp_nonce_field('lte_calendar_save', 'lte_calendar_nonce');
    $dates = get_post_meta($post->ID, '_lte_excursion_dates', true);
    $dates = is_array($dates) ? $dates : [];
    echo '<div id="lte-calendar-entries">';
    foreach ($dates as $i => $entry) {
        echo '<p>' .
            '<label>Date: <input type="date" name="lte_excursion_dates['.$i.'][date]" value="'.esc_attr($entry['date']).'" /></label> ' .
            '<label>Description: <input type="text" name="lte_excursion_dates['.$i.'][desc]" value="'.esc_attr($entry['desc']).'" size="20" /></label> ' .
            '<label>Image ID: <input type="number" name="lte_excursion_dates['.$i.'][image]" value="'.esc_attr($entry['image']).'" size="6" /></label> ' .
            '<button class="button lte-remove-entry">'.__('Remove','life-travel-excursion').'</button>' .
        '</p>';
    }
    echo '</div>';
    echo '<button type="button" class="button" id="lte-add-entry">'.__('Add Date','life-travel-excursion').'</button>';
}

function lte_save_calendar_metabox($post_id) {
    if (!isset($_POST['lte_calendar_nonce']) || !wp_verify_nonce($_POST['lte_calendar_nonce'], 'lte_calendar_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (isset($_POST['lte_excursion_dates']) && is_array($_POST['lte_excursion_dates'])) {
        $clean = [];
        foreach ($_POST['lte_excursion_dates'] as $item) {
            if (empty($item['date'])) continue;
            $clean[] = [
                'date' => sanitize_text_field($item['date']),
                'desc' => sanitize_text_field($item['desc']),
                'image' => absint($item['image'])
            ];
        }
        update_post_meta($post_id, '_lte_excursion_dates', $clean);
    } else {
        delete_post_meta($post_id, '_lte_excursion_dates');
    }
}
add_action('save_post_product', 'lte_save_calendar_metabox');
