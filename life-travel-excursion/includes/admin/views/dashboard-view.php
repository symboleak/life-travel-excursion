<?php
/**
 * Vue du tableau de bord principal des paniers abandonnés
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

defined('ABSPATH') || exit;

/**
 * Classe pour l'affichage de la vue principale du tableau de bord
 */
class Life_Travel_Abandoned_Cart_Dashboard_View {
    
    /**
     * Affiche la vue du tableau de bord
     */
    public static function render_dashboard() {
        // Charger Chart.js dans l'admin
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true);
        
        // Titre de la page
        echo '<div class="wrap life-travel-cart-dashboard">';
        echo '<div class="life-travel-header">';
        echo '<h1 class="life-travel-title">' . esc_html__('Paniers abandonnés', 'life-travel-excursion') . '</h1>';
        
        // Bouton d'exportation (facultatif)
        if (current_user_can('manage_options')) {
            echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin.php?page=life-travel-abandoned-carts&action=export_csv'), 'life_travel_export_carts')) . '" class="button button-primary">';
            echo '<span class="dashicons dashicons-download" style="margin-top: 4px;"></span> ' . esc_html__('Exporter (CSV)', 'life-travel-excursion');
            echo '</a>';
        }
        
        echo '</div>'; // .life-travel-header
        
        // Filtres et recherche
        echo '<div class="life-travel-filter-form">';
        self::render_filter_form();
        echo '</div>';
        
        // Tableau des paniers abandonnés
        $cart_list = new Life_Travel_Abandoned_Cart_List();
        $cart_list->prepare_items();
        
        echo '<form id="life-travel-carts" method="post">';
        
        // Ajouter un nonce pour la sécurité
        wp_nonce_field('life_travel_cart_action');
        
        $cart_list->display();
        echo '</form>';
        
        // Statistiques
        echo '<div class="life-travel-stats-container">';
        self::display_cart_statistics();
        echo '</div>';
        
        echo '</div>'; // .wrap
    }
    
    /**
     * Affiche le formulaire de filtrage
     */
    private static function render_filter_form() {
        $status = isset($_GET['cart_status']) ? sanitize_text_field($_GET['cart_status']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="life-travel-abandoned-carts">';
        
        // Statut
        echo '<select name="cart_status">';
        echo '<option value="">' . esc_html__('Tous les statuts', 'life-travel-excursion') . '</option>';
        echo '<option value="abandoned" ' . selected($status, 'abandoned', false) . '>' . esc_html__('Abandonnés', 'life-travel-excursion') . '</option>';
        echo '<option value="recovered" ' . selected($status, 'recovered', false) . '>' . esc_html__('Récupérés', 'life-travel-excursion') . '</option>';
        echo '<option value="reminded" ' . selected($status, 'reminded', false) . '>' . esc_html__('Rappelés', 'life-travel-excursion') . '</option>';
        echo '</select>';
        
        // Période
        echo '<input type="date" name="date_from" placeholder="' . esc_attr__('Date de début', 'life-travel-excursion') . '" value="' . esc_attr($date_from) . '">';
        echo '<input type="date" name="date_to" placeholder="' . esc_attr__('Date de fin', 'life-travel-excursion') . '" value="' . esc_attr($date_to) . '">';
        
        // Recherche
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Rechercher par email...', 'life-travel-excursion') . '">';
        
        // Bouton de filtrage
        echo '<input type="submit" class="button" value="' . esc_attr__('Filtrer', 'life-travel-excursion') . '">';
        
        // Lien de réinitialisation
        echo '<a href="' . esc_url(admin_url('admin.php?page=life-travel-abandoned-carts')) . '" class="button-link">' . esc_html__('Réinitialiser', 'life-travel-excursion') . '</a>';
        
        echo '</form>';
    }
    
    /**
     * Affiche les statistiques des paniers abandonnés
     */
    private static function display_cart_statistics() {
        // Récupérer l'analyseur
        $analyzer = Life_Travel_Abandoned_Cart_Analyzer::get_instance();
        $stats = $analyzer->get_cart_statistics();
        
        // Affichage des statistiques
        echo '<h2>' . esc_html__('Statistiques des paniers abandonnés', 'life-travel-excursion') . '</h2>';
        
        echo '<div class="life-travel-stats-grid">';
        
        // Paniers abandonnés
        echo '<div class="life-travel-stat-card abandoned">';
        echo '<span class="life-travel-stat-value">' . esc_html($stats['abandoned_carts']) . '</span>';
        echo '<span class="life-travel-stat-label">' . esc_html__('Paniers abandonnés', 'life-travel-excursion') . '</span>';
        echo '</div>';
        
        // Paniers récupérés
        echo '<div class="life-travel-stat-card recovered">';
        echo '<span class="life-travel-stat-value">' . esc_html($stats['recovered_carts']) . '</span>';
        echo '<span class="life-travel-stat-label">' . esc_html__('Paniers récupérés', 'life-travel-excursion') . '</span>';
        echo '</div>';
        
        // Taux de récupération
        echo '<div class="life-travel-stat-card recovery-rate">';
        echo '<span class="life-travel-stat-value">' . esc_html($stats['recovery_rate']) . '%</span>';
        echo '<span class="life-travel-stat-label">' . esc_html__('Taux de récupération', 'life-travel-excursion') . '</span>';
        echo '</div>';
        
        // Valeur totale des paniers
        echo '<div class="life-travel-stat-card total-value">';
        echo '<span class="life-travel-stat-value">' . esc_html(wc_price($stats['total_abandoned_value'])) . '</span>';
        echo '<span class="life-travel-stat-label">' . esc_html__('Valeur à récupérer', 'life-travel-excursion') . '</span>';
        echo '</div>';
        
        echo '</div>'; // .life-travel-stats-grid
        
        echo '<p class="life-travel-stats-note">';
        echo sprintf(
            esc_html__('Valeur moyenne des paniers : %s | Récupérations récentes (7 jours) : %d', 'life-travel-excursion'),
            wc_price($stats['avg_cart_value']),
            $stats['recent_recoveries']
        );
        echo '</p>';
        
        // Graphique des statistiques de paniers
        echo '<canvas id="cartStatsChart" width="400" height="200"></canvas>';
        echo '<script>document.addEventListener("DOMContentLoaded", function(){
            const ctx2 = document.getElementById("cartStatsChart").getContext("2d");
            new Chart(ctx2, {
                type: "bar",
                data: {
                    labels: [
                        "' . esc_js(__('Abandonnés', 'life-travel-excursion')) . '",
                        "' . esc_js(__('Récupérés', 'life-travel-excursion')) . '",
                        "' . esc_js(__('Taux %', 'life-travel-excursion')) . '"
                    ],
                    datasets: [{
                        label: "' . esc_js(__('Statistiques', 'life-travel-excursion')) . '",
                        data: [' . intval($stats['abandoned_carts']) . ', ' . intval($stats['recovered_carts']) . ', ' . floatval($stats['recovery_rate']) . '],
                        backgroundColor: ["#e74c3c","#2ecc71","#3498db"]
                    }]
                },
                options: {responsive:true, scales:{y:{beginAtZero:true}}}
            });
        });</script>';
    }
}
