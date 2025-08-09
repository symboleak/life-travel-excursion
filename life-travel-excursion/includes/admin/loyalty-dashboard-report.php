<?php
/**
 * Rapport avancé pour le tableau de bord de fidélité
 * 
 * Ajoute des visualisations avancées et des rapports d'analyse 
 * au tableau de bord de fidélité.
 * 
 * @package Life_Travel
 * @subpackage Admin
 * @since 2.5.0
 */

// Sécurité
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour les rapports avancés du système de fidélité
 */
class Life_Travel_Loyalty_Dashboard_Report {
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Ajouter les endpoints AJAX
        add_action('wp_ajax_lte_get_loyalty_report', array($this, 'get_loyalty_report'));
        add_action('wp_ajax_lte_export_loyalty_data', array($this, 'export_loyalty_data'));
        
        // Ajouter le widget au tableau de bord WP
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    /**
     * Ajoute un widget au tableau de bord WordPress
     */
    public function add_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'lte_loyalty_dashboard_widget',
                __('Aperçu du système de fidélité', 'life-travel-excursion'),
                array($this, 'render_dashboard_widget')
            );
        }
    }
    
    /**
     * Affiche le widget du tableau de bord
     */
    public function render_dashboard_widget() {
        $stats = $this->get_quick_stats();
        
        echo '<div class="lte-loyalty-widget">';
        
        // Stats générales
        echo '<div class="lte-stats-overview">';
        echo '<div class="lte-stat-box">';
        echo '<span class="lte-stat-value">' . number_format($stats['users_with_points'], 0, ',', ' ') . '</span>';
        echo '<span class="lte-stat-label">' . __('Utilisateurs', 'life-travel-excursion') . '</span>';
        echo '</div>';
        
        echo '<div class="lte-stat-box">';
        echo '<span class="lte-stat-value">' . number_format($stats['total_points'], 0, ',', ' ') . '</span>';
        echo '<span class="lte-stat-label">' . __('Points', 'life-travel-excursion') . '</span>';
        echo '</div>';
        
        echo '<div class="lte-stat-box">';
        echo '<span class="lte-stat-value">' . number_format($stats['points_redeemed'], 0, ',', ' ') . '</span>';
        echo '<span class="lte-stat-label">' . __('Utilisés', 'life-travel-excursion') . '</span>';
        echo '</div>';
        
        echo '<div class="lte-stat-box">';
        echo '<span class="lte-stat-value">' . $stats['redemption_rate'] . '%</span>';
        echo '<span class="lte-stat-label">' . __('Taux d\'utilisation', 'life-travel-excursion') . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Mini-graphique de tendance
        echo '<div class="lte-trend-chart">';
        echo '<h4>' . __('Tendance des 7 derniers jours', 'life-travel-excursion') . '</h4>';
        echo '<canvas id="lte-widget-chart" style="height: 100px;"></canvas>';
        
        // Données pour le graphique
        $trend_data = $this->get_trend_data(7);
        echo '<script>
            jQuery(document).ready(function($) {
                if (typeof Chart !== "undefined") {
                    var ctx = document.getElementById("lte-widget-chart").getContext("2d");
                    new Chart(ctx, {
                        type: "line",
                        data: {
                            labels: ' . json_encode(array_keys($trend_data)) . ',
                            datasets: [
                                {
                                    label: "' . __('Points attribués', 'life-travel-excursion') . '",
                                    data: ' . json_encode(array_column($trend_data, 'earned')) . ',
                                    borderColor: "#0073aa",
                                    tension: 0.4,
                                    fill: false
                                },
                                {
                                    label: "' . __('Points utilisés', 'life-travel-excursion') . '",
                                    data: ' . json_encode(array_column($trend_data, 'redeemed')) . ',
                                    borderColor: "#d54e21",
                                    tension: 0.4,
                                    fill: false
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true },
                                x: { display: false }
                            },
                            plugins: {
                                legend: { display: false }
                            }
                        }
                    });
                }
            });
        </script>';
        echo '</div>';
        
        // Lien vers le rapport complet
        echo '<p class="lte-view-report"><a href="' . admin_url('edit.php?post_type=product&page=loyalty-stats') . '">' . 
            __('Voir le rapport complet', 'life-travel-excursion') . 
            ' <span class="dashicons dashicons-arrow-right-alt"></span></a></p>';
        
        echo '</div>';
        
        // Styles pour le widget
        echo '<style>
            .lte-loyalty-widget {
                padding: 0;
            }
            .lte-stats-overview {
                display: flex;
                justify-content: space-between;
                margin-bottom: 15px;
            }
            .lte-stat-box {
                text-align: center;
                flex: 1;
                padding: 10px 5px;
            }
            .lte-stat-value {
                display: block;
                font-size: 18px;
                font-weight: bold;
                color: #0073aa;
            }
            .lte-stat-label {
                display: block;
                font-size: 12px;
                color: #666;
            }
            .lte-trend-chart {
                margin-bottom: 15px;
            }
            .lte-trend-chart h4 {
                margin: 0 0 10px 0;
                font-size: 14px;
            }
            .lte-view-report {
                text-align: right;
                margin: 0;
            }
            .lte-view-report a {
                font-size: 13px;
                text-decoration: none;
            }
            .lte-view-report .dashicons {
                font-size: 16px;
                vertical-align: middle;
            }
        </style>';
    }
    
    /**
     * Récupère les statistiques rapides pour le widget
     * 
     * @return array Tableau des statistiques rapides
     */
    private function get_quick_stats() {
        global $wpdb;
        
        // Utiliser le cache si disponible
        $cache_key = 'lte_loyalty_quick_stats_' . date('Ymd');
        $cached_stats = get_transient($cache_key);
        
        if (false !== $cached_stats) {
            return $cached_stats;
        }
        
        // Utilisateurs avec points
        $users_with_points = $wpdb->get_var("
            SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '_lte_loyalty_points' 
            AND meta_value > 0
        ");
        
        // Total des points attribués
        $total_points = $wpdb->get_var("
            SELECT SUM(meta_value) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '_lte_loyalty_points'
        ");
        
        $total_points = $total_points ? $total_points : 0;
        
        // Points utilisés (via les commandes)
        $points_redeemed = $wpdb->get_var("
            SELECT SUM(meta_value) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_lte_loyalty_points_used'
        ");
        
        $points_redeemed = $points_redeemed ? $points_redeemed : 0;
        
        // Taux d'utilisation
        $redemption_rate = 0;
        if ($total_points > 0) {
            $redemption_rate = round(($points_redeemed / ($total_points + $points_redeemed)) * 100, 1);
        }
        
        $stats = array(
            'users_with_points' => $users_with_points,
            'total_points' => $total_points,
            'points_redeemed' => $points_redeemed,
            'redemption_rate' => $redemption_rate
        );
        
        // Mettre en cache pour 6 heures
        set_transient($cache_key, $stats, 6 * HOUR_IN_SECONDS);
        
        return $stats;
    }
    
    /**
     * Récupère les données de tendance pour un nombre de jours
     * 
     * @param int $days Nombre de jours
     * @return array Données de tendance
     */
    private function get_trend_data($days = 7) {
        global $wpdb;
        
        $trend_data = array();
        
        // Préparer les données pour les X derniers jours
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $trend_data[$date] = array(
                'earned' => 0,
                'redeemed' => 0
            );
        }
        
        // Points gagnés par jour (simulation simple - à remplacer par les vraies données)
        $earned_results = array(
            date('Y-m-d', strtotime("-6 days")) => 120,
            date('Y-m-d', strtotime("-5 days")) => 85,
            date('Y-m-d', strtotime("-4 days")) => 210,
            date('Y-m-d', strtotime("-3 days")) => 150,
            date('Y-m-d', strtotime("-2 days")) => 180,
            date('Y-m-d', strtotime("-1 days")) => 95,
            date('Y-m-d') => 130
        );
        
        // Points utilisés par jour (simulation simple - à remplacer par les vraies données)
        $redeemed_results = array(
            date('Y-m-d', strtotime("-6 days")) => 30,
            date('Y-m-d', strtotime("-5 days")) => 45,
            date('Y-m-d', strtotime("-4 days")) => 60,
            date('Y-m-d', strtotime("-3 days")) => 40,
            date('Y-m-d', strtotime("-2 days")) => 90,
            date('Y-m-d', strtotime("-1 days")) => 50,
            date('Y-m-d') => 65
        );
        
        // Fusionner les données simulées
        foreach ($earned_results as $date => $earned) {
            if (isset($trend_data[$date])) {
                $trend_data[$date]['earned'] = $earned;
            }
        }
        
        foreach ($redeemed_results as $date => $redeemed) {
            if (isset($trend_data[$date])) {
                $trend_data[$date]['redeemed'] = $redeemed;
            }
        }
        
        return $trend_data;
    }
    
    /**
     * Génère un rapport complet au format JSON (endpoint AJAX)
     */
    public function get_loyalty_report() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes'));
            return;
        }
        
        // Vérifier le nonce
        check_ajax_referer('lte_loyalty_report_nonce', 'nonce');
        
        // Type de rapport demandé
        $report_type = isset($_POST['report_type']) ? sanitize_text_field($_POST['report_type']) : 'summary';
        
        // Période demandée
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : '30days';
        
        // Générer le rapport demandé
        $report = array();
        
        switch ($report_type) {
            case 'summary':
                $report = $this->generate_summary_report();
                break;
            case 'users':
                $report = $this->generate_users_report();
                break;
            case 'products':
                $report = $this->generate_products_report();
                break;
            case 'trends':
                $report = $this->generate_trends_report($period);
                break;
            default:
                wp_send_json_error(array('message' => 'Type de rapport invalide'));
                return;
        }
        
        wp_send_json_success($report);
    }
    
    /**
     * Génère un rapport de synthèse
     */
    private function generate_summary_report() {
        // Récupérer les statistiques rapides
        $quick_stats = $this->get_quick_stats();
        
        // Points par source
        $points_by_source = array(
            'purchase' => 65,
            'social_share' => 15,
            'admin' => 10,
            'other' => 10
        );
        
        return array(
            'quick_stats' => $quick_stats,
            'points_by_source' => $points_by_source,
            'top_users' => $this->get_top_users(5),
            'top_products' => $this->get_top_products(5)
        );
    }
    
    /**
     * Génère un rapport détaillé des utilisateurs
     */
    private function generate_users_report() {
        return array(
            'users' => $this->get_top_users(20),
            'activity' => $this->get_user_activity()
        );
    }
    
    /**
     * Génère un rapport détaillé des produits
     */
    private function generate_products_report() {
        return array(
            'products' => $this->get_top_products(20),
            'categories' => $this->get_points_by_category()
        );
    }
    
    /**
     * Génère un rapport de tendances
     */
    private function generate_trends_report($period) {
        $days = 30;
        
        switch ($period) {
            case '7days':
                $days = 7;
                break;
            case '90days':
                $days = 90;
                break;
            case '365days':
                $days = 365;
                break;
        }
        
        return array(
            'trends' => $this->get_trend_data($days),
            'period' => $period
        );
    }
    
    /**
     * Récupère les utilisateurs avec le plus de points
     */
    private function get_top_users($limit = 10) {
        global $wpdb;
        
        $top_users = array();
        
        // Simulation simple - à remplacer par une vraie requête
        for ($i = 1; $i <= $limit; $i++) {
            $top_users[] = array(
                'user_id' => $i,
                'name' => 'Utilisateur ' . $i,
                'email' => 'user' . $i . '@example.com',
                'points' => rand(100, 1000),
                'value' => number_format(rand(100, 1000) / 100, 2),
                'last_activity' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days'))
            );
        }
        
        // Trier par points
        usort($top_users, function($a, $b) {
            return $b['points'] - $a['points'];
        });
        
        return $top_users;
    }
    
    /**
     * Récupère les produits avec le plus de points attribués
     */
    private function get_top_products($limit = 10) {
        global $wpdb;
        
        $top_products = array();
        
        // Simulation simple - à remplacer par une vraie requête
        $product_names = array(
            'Excursion Safari Cameroun',
            'Tour de la ville de Douala',
            'Balade en pirogue sur le fleuve Wouri',
            'Visite du Mont Cameroun',
            'Plage de Limbé',
            'Tour des chefferies',
            'Réserve de Dja',
            'Chutes de la Lobé',
            'Parc National de Waza',
            'Visite de Yaoundé'
        );
        
        for ($i = 0; $i < min($limit, count($product_names)); $i++) {
            $top_products[] = array(
                'product_id' => $i + 1,
                'name' => $product_names[$i],
                'points_earned' => rand(200, 2000),
                'orders_count' => rand(5, 50),
                'avg_points' => rand(10, 50)
            );
        }
        
        // Trier par points
        usort($top_products, function($a, $b) {
            return $b['points_earned'] - $a['points_earned'];
        });
        
        return $top_products;
    }
    
    /**
     * Récupère l'activité des utilisateurs
     */
    private function get_user_activity() {
        $activity = array(
            'new_users' => rand(10, 30),
            'active_users' => rand(50, 200),
            'avg_points_per_user' => rand(100, 500),
            'participation_rate' => rand(40, 90)
        );
        
        return $activity;
    }
    
    /**
     * Récupère les points par catégorie
     */
    private function get_points_by_category() {
        $categories = array(
            'Safari' => rand(1000, 5000),
            'City Tours' => rand(800, 4000),
            'Nature' => rand(1200, 6000),
            'Cultural' => rand(900, 4500),
            'Beach' => rand(700, 3500)
        );
        
        return $categories;
    }
    
    /**
     * Exporte les données du système de fidélité (endpoint AJAX)
     */
    public function export_loyalty_data() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes'));
            return;
        }
        
        // Vérifier le nonce
        check_ajax_referer('lte_loyalty_export_nonce', 'nonce');
        
        // Type d'export
        $export_type = isset($_POST['export_type']) ? sanitize_text_field($_POST['export_type']) : 'all';
        
        // Générer le nom du fichier
        $filename = 'loyalty-data-' . date('Y-m-d') . '.csv';
        
        // Ouvrir un flux de fichier temporaire
        $temp_file = fopen('php://temp', 'r+');
        
        // En-tête CSV selon le type d'export
        $header = array();
        
        switch ($export_type) {
            case 'users':
                $header = array('ID', 'Nom', 'Email', 'Points', 'Valeur', 'Dernière activité');
                $data = $this->get_top_users(500); // Obtenir tous les utilisateurs
                break;
            case 'products':
                $header = array('ID', 'Nom', 'Points gagnés', 'Nombre de commandes', 'Points moyens');
                $data = $this->get_top_products(100); // Obtenir tous les produits
                break;
            case 'all':
            default:
                $header = array('Type', 'ID', 'Nom', 'Points', 'Date');
                $data = $this->get_all_points_history();
                break;
        }
        
        // Écrire l'en-tête
        fputcsv($temp_file, $header);
        
        // Écrire les lignes de données
        foreach ($data as $row) {
            // Formater la ligne selon le type d'export
            $line = array();
            
            switch ($export_type) {
                case 'users':
                    $line = array(
                        $row['user_id'],
                        $row['name'],
                        $row['email'],
                        $row['points'],
                        $row['value'],
                        $row['last_activity']
                    );
                    break;
                case 'products':
                    $line = array(
                        $row['product_id'],
                        $row['name'],
                        $row['points_earned'],
                        $row['orders_count'],
                        $row['avg_points']
                    );
                    break;
                case 'all':
                default:
                    $line = array(
                        $row['type'],
                        $row['id'],
                        $row['name'],
                        $row['points'],
                        $row['date']
                    );
                    break;
            }
            
            fputcsv($temp_file, $line);
        }
        
        // Rembobiner le flux
        rewind($temp_file);
        
        // Lire le contenu
        $csv_content = stream_get_contents($temp_file);
        
        // Fermer le flux
        fclose($temp_file);
        
        // Encodage Base64 pour le transfert
        $base64_csv = base64_encode($csv_content);
        
        // Retourner l'URL de téléchargement
        wp_send_json_success(array(
            'filename' => $filename,
            'content' => $base64_csv
        ));
    }
    
    /**
     * Récupère l'historique complet des points
     */
    private function get_all_points_history() {
        // Simulation simple - à remplacer par une vraie requête
        $history = array();
        
        // Simuler des données pour les points gagnés
        for ($i = 1; $i <= 50; $i++) {
            $history[] = array(
                'type' => 'earned',
                'id' => $i,
                'name' => 'Utilisateur ' . rand(1, 20),
                'points' => rand(10, 100),
                'date' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 90) . ' days'))
            );
        }
        
        // Simuler des données pour les points dépensés
        for ($i = 1; $i <= 20; $i++) {
            $history[] = array(
                'type' => 'redeemed',
                'id' => $i,
                'name' => 'Utilisateur ' . rand(1, 20),
                'points' => rand(50, 200),
                'date' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days'))
            );
        }
        
        // Trier par date
        usort($history, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $history;
    }
}

// Initialiser la classe
new Life_Travel_Loyalty_Dashboard_Report();
