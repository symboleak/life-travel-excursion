<?php
/**
 * Frontend My Account enhancements
 */
defined('ABSPATH') || exit;

// Display simplified reservations list on dashboard
add_action('woocommerce_account_dashboard', 'lte_myaccount_reservations');
function lte_myaccount_reservations() {
    $customer_id = get_current_user_id();
    if (!$customer_id) return;
    $orders = wc_get_orders(['customer_id'=>$customer_id, 'limit'=>-1]);
    if (empty($orders)) {
        echo '<p>' . __('Aucune réservation trouvée.', 'life-travel-excursion') . '</p>';
        return;
    }
    echo '<h2>' . __('Mes Réservations', 'life-travel-excursion') . '</h2>';
    // Séparer en cours vs terminées
    $ongoing = []; $history = [];
    foreach ($orders as $order) {
        if ($order->has_status('completed')) $history[] = $order; else $ongoing[] = $order;
    }
    echo '<div class="lte-account-tabs"><button data-tab="ongoing">'.__('Réservations en cours','life-travel-excursion').'</button><button data-tab="history">'.__('Historique','life-travel-excursion').'</button></div>';
    echo '<div class="lte-tab-content" id="lte-tab-ongoing"><ul class="lte-reservations-list">';
    foreach ($ongoing as $order) {
        $date = $order->get_date_created()->date_i18n('d/m/Y');
        echo '<li><a href="'.esc_url($order->get_view_order_url()).'">'.sprintf('#%1$d',$order->get_id()).' - '.esc_html($date).'</a></li>';
    }
    echo '</ul></div>';
    echo '<div class="lte-tab-content" id="lte-tab-history" style="display:none"><ul class="lte-reservations-list">';
    foreach ($history as $order) {
        $date = $order->get_date_created()->date_i18n('d/m/Y');
        echo '<li><a href="'.esc_url($order->get_view_order_url()).'">'.sprintf('#%1$d',$order->get_id()).' - '.esc_html($date).'</a></li>';
    }
    echo '</ul></div>';
}
