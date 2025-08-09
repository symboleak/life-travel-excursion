<?php
/**
 * Vue d'analyse des paniers abandonnés
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

defined('ABSPATH') || exit;

/**
 * Classe pour l'affichage de la vue d'analyse des paniers abandonnés
 */
class Life_Travel_Abandoned_Cart_Analytics_View {
    
    /**
     * Affiche la vue d'analyse
     */
    public static function render_analytics() {
        // Récupérer l'analyseur
        $analyzer = Life_Travel_Abandoned_Cart_Analyzer::get_instance();
        
        // Récupérer la plage de dates sélectionnée
        $range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : '30d';
        $custom_start = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $custom_end = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        
        // Récupérer les données pour les graphiques
        $chart_data = $analyzer->get_chart_data($range, $custom_start, $custom_end);
        
        // Localiser le script avec les données
        wp_localize_script('life-travel-admin-cart', 'lifeTravelAnalytics', array_merge($chart_data, array(
            'currencySymbol' => get_woocommerce_currency_symbol(),
            'labels' => array(
                'abandoned' => __('Abandonnés', 'life-travel-excursion'),
                'recovered' => __('Récupérés', 'life-travel-excursion'),
                'reminded' => __('Emails envoyés', 'life-travel-excursion'),
                'avgValue' => __('Valeur moyenne', 'life-travel-excursion')
            )
        )));
        
        echo '<div class="wrap life-travel-cart-dashboard">';
        echo '<div class="life-travel-header">';
        echo '<h1 class="life-travel-title">' . esc_html__('Analyse des paniers abandonnés', 'life-travel-excursion') . '</h1>';
        echo '</div>';
        
        // Sélecteur de période
        echo '<div class="life-travel-date-range-selector">';
        self::render_date_range_selector($range, $custom_start, $custom_end);
        echo '</div>';
        
        // Graphiques et analyses
        echo '<div class="life-travel-analytics-container">';
        
        // Taux de récupération
        echo '<div class="life-travel-analytics-card">';
        echo '<h2>' . esc_html__('Taux de récupération', 'life-travel-excursion') . '</h2>';
        echo '<div class="life-travel-analytics-chart" id="life-travel-recovery-rate-chart"></div>';
        echo '</div>';
        
        // Produits les plus abandonnés
        echo '<div class="life-travel-analytics-card">';
        echo '<h2>' . esc_html__('Produits les plus abandonnés', 'life-travel-excursion') . '</h2>';
        echo '<div class="life-travel-analytics-chart" id="life-travel-abandoned-products-chart"></div>';
        echo '</div>';
        
        // Valeur des paniers abandonnés
        echo '<div class="life-travel-analytics-card">';
        echo '<h2>' . esc_html__('Valeur des paniers abandonnés', 'life-travel-excursion') . '</h2>';
        echo '<div class="life-travel-analytics-chart" id="life-travel-cart-value-chart"></div>';
        echo '</div>';
        
        // Efficacité des emails
        echo '<div class="life-travel-analytics-card">';
        echo '<h2>' . esc_html__('Efficacité des emails de récupération', 'life-travel-excursion') . '</h2>';
        echo '<div class="life-travel-analytics-chart" id="life-travel-email-efficiency-chart"></div>';
        echo '</div>';
        
        echo '</div>'; // .life-travel-analytics-container
        
        // Tableau des tendances
        echo '<div class="life-travel-trends">';
        self::display_abandoned_cart_trends($analyzer, $range, $custom_start, $custom_end);
        echo '</div>';
        
        echo '</div>'; // .wrap
    }
    
    /**
     * Affiche le sélecteur de plage de dates pour l'analyse
     * 
     * @param string $current_range Plage de dates actuelle
     * @param string $date_from Date de début personnalisée
     * @param string $date_to Date de fin personnalisée
     */
    private static function render_date_range_selector($current_range, $date_from, $date_to) {
        $ranges = array(
            '7d' => __('7 derniers jours', 'life-travel-excursion'),
            '30d' => __('30 derniers jours', 'life-travel-excursion'),
            '90d' => __('90 derniers jours', 'life-travel-excursion'),
            'year' => __('Année en cours', 'life-travel-excursion'),
            'custom' => __('Personnalisé', 'life-travel-excursion')
        );
        
        echo '<form method="get" id="life-travel-analytics-range">';
        echo '<input type="hidden" name="page" value="life-travel-abandoned-carts-analytics">';
        
        // Sélecteur de plage prédéfinie
        echo '<select name="range" id="life-travel-range-selector">';
        foreach ($ranges as $range => $label) {
            echo '<option value="' . esc_attr($range) . '" ' . selected($current_range, $range, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        
        // Dates personnalisées (affichées uniquement si "custom" est sélectionné)
        echo '<div id="life-travel-custom-range" style="' . ($current_range === 'custom' ? '' : 'display:none;') . '">';
        echo '<input type="date" name="date_from" value="' . esc_attr($date_from) . '" placeholder="' . esc_attr__('Date de début', 'life-travel-excursion') . '">';
        echo '<input type="date" name="date_to" value="' . esc_attr($date_to) . '" placeholder="' . esc_attr__('Date de fin', 'life-travel-excursion') . '">';
        echo '</div>';
        
        // Bouton d'application
        echo '<input type="submit" class="button" value="' . esc_attr__('Appliquer', 'life-travel-excursion') . '">';
        
        echo '</form>';
        
        // Script pour afficher/masquer les dates personnalisées
        echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                $("#life-travel-range-selector").on("change", function() {
                    if ($(this).val() === "custom") {
                        $("#life-travel-custom-range").show();
                    } else {
                        $("#life-travel-custom-range").hide();
                    }
                });
            });
        </script>';
    }
    
    /**
     * Affiche les tendances des paniers abandonnés sous forme de tableau
     * 
     * @param Life_Travel_Abandoned_Cart_Analyzer $analyzer Analyseur de paniers abandonnés
     * @param string $range Plage de dates sélectionnée
     * @param string $custom_start Date de début personnalisée
     * @param string $custom_end Date de fin personnalisée
     */
    private static function display_abandoned_cart_trends($analyzer, $range, $custom_start, $custom_end) {
        // Récupérer la plage de dates
        $dates = $analyzer->get_date_range($range, $custom_start, $custom_end);
        $start_date = $dates['start_date'];
        $end_date = $dates['end_date'];
        
        // Récupérer les tendances
        $trends = $analyzer->get_cart_trends($start_date, $end_date);
        
        if (empty($trends)) {
            echo '<p>' . esc_html__('Aucune donnée disponible pour la période sélectionnée.', 'life-travel-excursion') . '</p>';
            return;
        }
        
        // Tableau des tendances (version textuelle)
        echo '<h2>' . esc_html__('Tendances des paniers abandonnés', 'life-travel-excursion') . '</h2>';
        
        echo '<table class="widefat life-travel-trends-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Date', 'life-travel-excursion') . '</th>';
        echo '<th>' . esc_html__('Abandonnés', 'life-travel-excursion') . '</th>';
        echo '<th>' . esc_html__('Récupérés', 'life-travel-excursion') . '</th>';
        echo '<th>' . esc_html__('Taux', 'life-travel-excursion') . '</th>';
        echo '<th>' . esc_html__('Emails envoyés', 'life-travel-excursion') . '</th>';
        echo '<th>' . esc_html__('Valeur moyenne', 'life-travel-excursion') . '</th>';
        echo '</tr>';
        echo '</thead>';
        
        echo '<tbody>';
        foreach ($trends as $day) {
            $recovery_rate = $day->total > 0 ? round(($day->recovered / $day->total) * 100, 2) : 0;
            
            echo '<tr>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($day->date))) . '</td>';
            echo '<td>' . esc_html($day->total) . '</td>';
            echo '<td>' . esc_html($day->recovered) . '</td>';
            echo '<td>' . esc_html($recovery_rate) . '%</td>';
            echo '<td>' . esc_html($day->reminded) . '</td>';
            echo '<td>' . esc_html(wc_price($day->avg_value)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        
        echo '</table>';
    }
    
    /**
     * Génère un rapport de sécurité pour l'activité des paniers abandonnés
     */
    public static function generate_security_report() {
        global $wpdb;
        
        // Générer un rapport de sécurité basique
        $report = array(
            'titre' => __('Rapport de sécurité des paniers abandonnés', 'life-travel-excursion'),
            'date' => current_time('mysql'),
            'statistiques' => array(),
            'alertes' => array()
        );
        
        // Récupérer les tentatives de récupération suspectes (si la table de journal existe)
        $log_table = $wpdb->prefix . 'life_travel_error_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$log_table}'") === $log_table) {
            $suspicious_attempts = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$log_table} 
                 WHERE error_type = 'security' 
                 AND error_date > DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
            
            $report['statistiques']['tentatives_suspectes'] = $suspicious_attempts;
            
            if ($suspicious_attempts > 10) {
                $report['alertes'][] = __('Nombre élevé de tentatives suspectes détectées. Vérifiez les journaux de sécurité.', 'life-travel-excursion');
            }
        }
        
        // Vérifier les paniers récupérés rapidement (potentielle exploitation)
        $cart_table = $wpdb->prefix . 'life_travel_abandoned_carts';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$cart_table}'") === $cart_table) {
            $quick_recoveries = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$cart_table}
                 WHERE recovered = 1
                 AND TIMESTAMPDIFF(MINUTE, created_at, recovered_at) < 5"
            );
            
            $report['statistiques']['recuperations_rapides'] = $quick_recoveries;
            
            if ($quick_recoveries > 5) {
                $report['alertes'][] = __('Plusieurs paniers ont été récupérés très rapidement après leur création. Cela pourrait indiquer un problème de sécurité.', 'life-travel-excursion');
            }
        }
        
        // Ajouter des recommandations de sécurité
        $report['recommandations'] = array(
            __('Vérifiez régulièrement les journaux de sécurité pour détecter des activités suspectes.', 'life-travel-excursion'),
            __('Assurez-vous que tous les tokens de récupération sont correctement générés et validés.', 'life-travel-excursion'),
            __('Limitez le nombre de tentatives de récupération pour une même adresse IP.', 'life-travel-excursion'),
            __('Validez rigoureusement toutes les données des formulaires liés aux paniers abandonnés.', 'life-travel-excursion')
        );
        
        return $report;
    }
}
