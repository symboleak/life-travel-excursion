<?php
/**
 * Loyalty points system
 */
defined('ABSPATH') || exit;

// Display loyalty field in checkout
add_action('woocommerce_review_order_before_payment', 'lte_loyalty_checkout_field');
function lte_loyalty_checkout_field() {
    if (!is_user_logged_in() || !get_theme_mod('lte_loyalty_enabled', false)) return;
    $user_id = get_current_user_id();
    $points = get_user_meta($user_id, '_lte_loyalty_points', true) ?: 0;
    $conversion = get_theme_mod('lte_loyalty_conversion', 1);
    $max_pct = get_theme_mod('lte_loyalty_max_discount_pct', 20);
    $subtotal = WC()->cart->get_subtotal();
    $max_discount = $subtotal * ($max_pct/100);
    $max_points_redeem = floor($max_discount * $conversion);
    $usable = min($points, $max_points_redeem);
    if ($usable <= 0) return;
    echo '<div class="lte-loyalty">';
    echo '<p>'.sprintf(__('Vous avez %d points (1€ = %d points). Maximum %d points (%d%% max, soit €%.2f).', 'life-travel-excursion'), $points, $conversion, $usable, $max_pct, $max_discount).'</p>';
    echo '<input type="number" name="lte_loyalty_points_to_use" min="0" max="'.$usable.'" value="0" />';
    echo '</div>';
}

// Apply discount based on used points
add_action('woocommerce_cart_calculate_fees', 'lte_loyalty_apply_discount');
function lte_loyalty_apply_discount($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!isset($_POST['lte_loyalty_points_to_use']) || !is_user_logged_in()) return;
    $points = intval($_POST['lte_loyalty_points_to_use']);
    if ($points <= 0) return;
    $conversion = get_theme_mod('lte_loyalty_conversion', 1);
    $max_pct = get_theme_mod('lte_loyalty_max_discount_pct', 20);
    $subtotal = $cart->get_subtotal();
    $max_discount = $subtotal * ($max_pct/100);
    $amount = min($points / $conversion, $max_discount);
    if ($amount <= 0) return;
    $cart->add_fee(__('Réduction fidélité','life-travel-excursion'), -$amount);
    WC()->session->set('lte_loyalty_points_used', $points);
}

// Award points on order completion
add_action('woocommerce_order_status_completed', 'lte_loyalty_award_points');
function lte_loyalty_award_points($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    if (!$user_id) return;
    $total = $order->get_total();
    $conversion = get_theme_mod('lte_loyalty_conversion', 1);
    $points_earned = floor($total * $conversion);
    $current = get_user_meta($user_id, '_lte_loyalty_points', true) ?: 0;
    // subtract used points
    $used = WC()->session->get('lte_loyalty_points_used');
    if ($used) {
        $new = max(0, $current + $points_earned - intval($used));
        WC()->session->__unset('lte_loyalty_points_used');
    } else {
        $new = $current + $points_earned;
    }
    update_user_meta($user_id, '_lte_loyalty_points', $new);
}
