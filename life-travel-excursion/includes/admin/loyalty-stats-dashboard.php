<?php
/**
 * Dashboard des statistiques de fidélité
 * 
 * Affiche des statistiques détaillées sur l'utilisation des points de fidélité
 * dans l'interface d'administration.
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
 * Classe pour gérer le tableau de bord des statistiques de fidélité
 */
class Life_Travel_Loyalty_Stats_Dashboard {
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Ajouter les menus et sous-menus
        add_action('admin_menu', array($this, 'add_stats_submenu'));
        
        // Ajouter les styles et scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Ajoute le sous-menu pour les statistiques de fidélité
     */
    public function add_stats_submenu() {
        add_submenu_page(
            'edit.php?post_type=product',
            __('Statistiques de fidélité', 'life-travel-excursion'),
            __('Stats Fidélité', 'life-travel-excursion'),
            'manage_options',
            'loyalty-stats',
            array($this, 'render_stats_page')
        );
    }
    
    /**
     * Charge les assets CSS et JS pour le dashboard
     */
    public function enqueue_admin_assets($hook) {
        if ('product_page_loyalty-stats' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'loyalty-stats-css',
            LIFE_TRAVEL_EXCURSION_URL . 'assets/css/admin/loyalty-stats.css',
            array(),
            LIFE_TRAVEL_EXCURSION_VERSION
        );
        
        wp_enqueue_script(
            'loyalty-stats-js',
            LIFE_TRAVEL_EXCURSION_URL . 'assets/js/admin/loyalty-stats.js',
            array('jquery', 'wp-api'),
            LIFE_TRAVEL_EXCURSION_VERSION,
            true
        );
        
        wp_localize_script('loyalty-stats-js', 'loyaltyStatsObj', array(
            'nonce' => wp_create_nonce('loyalty_stats_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ));
    }
    
    /**
     * Affiche la page des statistiques de fidélité
     */
    public function render_stats_page() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les autorisations suffisantes pour accéder à cette page.', 'life-travel-excursion'));
        }
        
        // Récupérer les données de statistiques
        $stats = $this->get_loyalty_statistics();
        
        // Afficher le tableau de bord
        $this->render_dashboard_header();
        $this->render_summary_cards($stats);
        $this->render_charts($stats);
        $this->render_users_table($stats['top_users']);
        $this->render_dashboard_footer();
    }
    
    /**
     * Récupère toutes les statistiques de fidélité
     * 
     * @return array Tableau de statistiques
     */
    private function get_loyalty_statistics() {
        global $wpdb;
        
        // Utiliser un cache transient pour éviter de surcharger la BDD
        $cache_key = 'lte_loyalty_stats_' . date('Ymd');
        $cached_stats = get_transient($cache_key);
        
        if (false !== $cached_stats) {
            return $cached_stats;
        }
        
        // Statistiques à récupérer
        $stats = array(
            'total_users_with_points' => 0,
            'total_points_awarded' => 0,
            'total_points_redeemed' => 0,
            'average_points_per_user' => 0,
            'points_by_excursion' => array(),
            'points_by_source' => array(
                'purchase' => 0,
                'social_share' => 0,
                'admin' => 0,
                'other' => 0
            ),
            'top_users' => array(),
            'usage_over_time' => array(),
            'redemption_rate' => 0
        );
        
        // Nombre d'utilisateurs avec des points
        $users_with_points = $wpdb->get_var("
            SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '_lte_loyalty_points' 
            AND meta_value > 0
        ");
        $stats['total_users_with_points'] = (int) $users_with_points;
        
        // Total des points attribués (historique)
        $total_points = $wpdb->get_var("
            SELECT SUM(meta_value) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '_lte_loyalty_points_history_total'
        ");
        $stats['total_points_awarded'] = (int) $total_points;
        
        // Total des points utilisés
        $total_redeemed = $wpdb->get_var("
            SELECT SUM(meta_value) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '_lte_loyalty_points_redeemed'
        ");
        $stats['total_points_redeemed'] = (int) $total_redeemed;
        
        // Calcul de la moyenne par utilisateur
        if ($stats['total_users_with_points'] > 0) {
            $stats['average_points_per_user'] = round($stats['total_points_awarded'] / $stats['total_users_with_points']);
        }
        
        // Taux de conversion (points utilisés / points attribués)
        if ($stats['total_points_awarded'] > 0) {
            $stats['redemption_rate'] = round(($stats['total_points_redeemed'] / $stats['total_points_awarded']) * 100, 2);
        }
        
        // Répartition par source
        $stats['points_by_source'] = $this->get_points_by_source();
        
        // Répartition par excursion
        $stats['points_by_excursion'] = $this->get_points_by_excursion();
        
        // Top utilisateurs
        $stats['top_users'] = $this->get_top_users(10);
        
        // Utilisation dans le temps (6 derniers mois)
        $stats['usage_over_time'] = $this->get_usage_over_time(6);
        
        // Mettre en cache pour 24 heures
        set_transient($cache_key, $stats, DAY_IN_SECONDS);
        
        return $stats;
    }
    
    /**
     * Récupère les points par source
     */
    private function get_points_by_source() {
        global $wpdb;
        
        $points_by_source = array(
            'purchase' => 0,
            'social_share' => 0,
            'admin' => 0,
            'other' => 0
        );
        
        // Cette requête suppose que vous stockez la source des points dans l'historique
        $results = $wpdb->get_results("
            SELECT source, SUM(points) as total
            FROM {$wpdb->prefix}lte_points_history
            GROUP BY source
        ");
        
        if ($results) {
            foreach ($results as $row) {
                if (isset($points_by_source[$row->source])) {
                    $points_by_source[$row->source] = (int) $row->total;
                } else {
                    $points_by_source['other'] += (int) $row->total;
                }
            }
        }
        
        return $points_by_source;
    }
    
    /**
     * Récupère les points par excursion
     */
    private function get_points_by_excursion() {
        global $wpdb;
        
        $points_by_excursion = array();
        
        // Cette requête suppose que vous stockez le produit associé dans l'historique
        $results = $wpdb->get_results("
            SELECT product_id, SUM(points) as total
            FROM {$wpdb->prefix}lte_points_history
            WHERE product_id > 0
            GROUP BY product_id
            ORDER BY total DESC
            LIMIT 10
        ");
        
        if ($results) {
            foreach ($results as $row) {
                $product = wc_get_product($row->product_id);
                if ($product) {
                    $points_by_excursion[$product->get_name()] = (int) $row->total;
                }
            }
        }
        
        return $points_by_excursion;
    }
    
    /**
     * Récupère les utilisateurs avec le plus de points
     */
    private function get_top_users($limit = 10) {
        global $wpdb;
        
        $top_users = array();
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT um.user_id, um.meta_value as points, u.display_name, u.user_email
            FROM {$wpdb->usermeta} um
            JOIN {$wpdb->users} u ON um.user_id = u.ID
            WHERE um.meta_key = '_lte_loyalty_points'
            AND um.meta_value > 0
            ORDER BY CAST(um.meta_value AS SIGNED) DESC
            LIMIT %d
        ", $limit));
        
        if ($results) {
            foreach ($results as $row) {
                $top_users[] = array(
                    'user_id' => $row->user_id,
                    'name' => $row->display_name,
                    'email' => $row->user_email,
                    'points' => (int) $row->points,
                    'value' => $this->get_points_value((int) $row->points),
                    'last_activity' => $this->get_user_last_activity($row->user_id)
                );
            }
        }
        
        return $top_users;
    }
    
    /**
     * Récupère l'historique d'utilisation des points sur une période
     */
    private function get_usage_over_time($months = 6) {
        global $wpdb;
        
        $usage = array();
        
        // Préparer les données pour les X derniers mois
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $usage[$month] = array(
                'earned' => 0,
                'redeemed' => 0
            );
        }
        
        // Points gagnés par mois
        $earned_results = $wpdb->get_results("
            SELECT DATE_FORMAT(date_created, '%Y-%m') as month, SUM(points) as total
            FROM {$wpdb->prefix}lte_points_history
            WHERE date_created >= DATE_SUB(NOW(), INTERVAL {$months} MONTH)
            GROUP BY month
            ORDER BY month ASC
        ");
        
        if ($earned_results) {
            foreach ($earned_results as $row) {
                if (isset($usage[$row->month])) {
                    $usage[$row->month]['earned'] = (int) $row->total;
                }
            }
        }
        
        // Points utilisés par mois
        $redeemed_results = $wpdb->get_results("
            SELECT DATE_FORMAT(date_created, '%Y-%m') as month, SUM(points) as total
            FROM {$wpdb->prefix}lte_points_redemption
            WHERE date_created >= DATE_SUB(NOW(), INTERVAL {$months} MONTH)
            GROUP BY month
            ORDER BY month ASC
        ");
        
        if ($redeemed_results) {
            foreach ($redeemed_results as $row) {
                if (isset($usage[$row->month])) {
                    $usage[$row->month]['redeemed'] = (int) $row->total;
                }
            }
        }
        
        return $usage;
    }
    
    /**
     * Récupère la dernière activité d'un utilisateur
     */
    private function get_user_last_activity($user_id) {
        global $wpdb;
        
        $last_activity = $wpdb->get_var($wpdb->prepare("
            SELECT MAX(date_created)
            FROM {$wpdb->prefix}lte_points_history
            WHERE user_id = %d
        ", $user_id));
        
        return $last_activity ? $last_activity : '';
    }
    
    /**
     * Convertit des points en valeur monétaire
     */
    private function get_points_value($points) {
        $points_value = get_option('lte_points_value', 100);
        
        if ($points_value <= 0) {
            return 0;
        }
        
        return round($points / $points_value, 2);
    }
    
    /**
     * Affiche l'en-tête du tableau de bord
     */
    private function render_dashboard_header() {
        ?>
        <div class="wrap lte-loyalty-stats-dashboard">
            <h1><?php _e('Tableau de bord des statistiques de fidélité', 'life-travel-excursion'); ?></h1>
            
            <div class="lte-dashboard-description">
                <p><?php _e('Ce tableau de bord présente des statistiques détaillées sur l\'utilisation du système de points de fidélité.', 'life-travel-excursion'); ?></p>
                <div class="lte-dashboard-actions">
                    <button class="button button-primary" id="lte-export-stats">
                        <?php _e('Exporter les données (CSV)', 'life-travel-excursion'); ?>
                    </button>
                    <button class="button" id="lte-refresh-stats">
                        <?php _e('Actualiser les données', 'life-travel-excursion'); ?>
                    </button>
                </div>
            </div>
        <?php
    }
    
    /**
     * Affiche les cartes récapitulatives
     */
    private function render_summary_cards($stats) {
        ?>
        <div class="lte-stats-cards">
            <div class="lte-stats-card">
                <div class="lte-stats-card-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="lte-stats-card-content">
                    <h2><?php echo esc_html($stats['total_users_with_points']); ?></h2>
                    <p><?php _e('Utilisateurs avec points', 'life-travel-excursion'); ?></p>
                </div>
            </div>
            
            <div class="lte-stats-card">
                <div class="lte-stats-card-icon">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <div class="lte-stats-card-content">
                    <h2><?php echo esc_html(number_format($stats['total_points_awarded'], 0, ',', ' ')); ?></h2>
                    <p><?php _e('Points totaux attribués', 'life-travel-excursion'); ?></p>
                </div>
            </div>
            
            <div class="lte-stats-card">
                <div class="lte-stats-card-icon">
                    <span class="dashicons dashicons-cart"></span>
                </div>
                <div class="lte-stats-card-content">
                    <h2><?php echo esc_html(number_format($stats['total_points_redeemed'], 0, ',', ' ')); ?></h2>
                    <p><?php _e('Points totaux utilisés', 'life-travel-excursion'); ?></p>
                </div>
            </div>
            
            <div class="lte-stats-card">
                <div class="lte-stats-card-icon">
                    <span class="dashicons dashicons-chart-area"></span>
                </div>
                <div class="lte-stats-card-content">
                    <h2><?php echo esc_html($stats['redemption_rate']); ?>%</h2>
                    <p><?php _e('Taux d\'utilisation', 'life-travel-excursion'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Affiche les graphiques
     */
    private function render_charts($stats) {
        ?>
        <div class="lte-stats-charts">
            <div class="lte-stats-chart-container">
                <h2><?php _e('Répartition des points par source', 'life-travel-excursion'); ?></h2>
                <div class="lte-stats-chart" id="lte-chart-sources"></div>
                <div class="lte-chart-legend" id="lte-legend-sources"></div>
            </div>
            
            <div class="lte-stats-chart-container">
                <h2><?php _e('Évolution sur 6 mois', 'life-travel-excursion'); ?></h2>
                <div class="lte-stats-chart" id="lte-chart-timeline"></div>
                <div class="lte-chart-legend" id="lte-legend-timeline"></div>
            </div>
            
            <div class="lte-stats-chart-container full-width">
                <h2><?php _e('Top 10 excursions par points attribués', 'life-travel-excursion'); ?></h2>
                <div class="lte-stats-chart" id="lte-chart-excursions"></div>
            </div>
            
            <!-- Données pour les graphiques -->
            <script type="text/javascript">
                var lteChartData = {
                    sources: <?php echo json_encode($stats['points_by_source']); ?>,
                    excursions: <?php echo json_encode($stats['points_by_excursion']); ?>,
                    timeline: <?php echo json_encode($stats['usage_over_time']); ?>
                };
            </script>
        </div>
        <?php
    }
    
    /**
     * Affiche le tableau des utilisateurs
     */
    private function render_users_table($users) {
        ?>
        <div class="lte-stats-table-container">
            <h2><?php _e('Top utilisateurs par points', 'life-travel-excursion'); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Utilisateur', 'life-travel-excursion'); ?></th>
                        <th><?php _e('Email', 'life-travel-excursion'); ?></th>
                        <th><?php _e('Points', 'life-travel-excursion'); ?></th>
                        <th><?php _e('Valeur (€)', 'life-travel-excursion'); ?></th>
                        <th><?php _e('Dernière activité', 'life-travel-excursion'); ?></th>
                        <th><?php _e('Actions', 'life-travel-excursion'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)) : ?>
                        <tr>
                            <td colspan="6"><?php _e('Aucun utilisateur trouvé.', 'life-travel-excursion'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($users as $user) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $user['user_id'])); ?>">
                                        <?php echo esc_html($user['name']); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($user['email']); ?></td>
                                <td><?php echo esc_html(number_format($user['points'], 0, ',', ' ')); ?></td>
                                <td><?php echo esc_html(number_format($user['value'], 2, ',', ' ')); ?> €</td>
                                <td>
                                    <?php echo $user['last_activity'] ? esc_html(date_i18n(get_option('date_format'), strtotime($user['last_activity']))) : '-'; ?>
                                </td>
                                <td>
                                    <button class="button button-small edit-user-points" data-user-id="<?php echo esc_attr($user['user_id']); ?>">
                                        <?php _e('Modifier', 'life-travel-excursion'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Affiche le pied de page du tableau de bord
     */
    private function render_dashboard_footer() {
        ?>
            <div class="lte-dashboard-footer">
                <p>
                    <?php 
                    printf(
                        __('Dernière mise à jour : %s. Les données sont mises en cache pendant 24 heures.', 'life-travel-excursion'),
                        date_i18n(get_option('date_format') . ' ' . get_option('time_format'))
                    ); 
                    ?>
                </p>
            </div>
        </div><!-- .wrap -->
        
        <!-- Modal pour modifier les points -->
        <div id="lte-edit-points-modal" style="display: none;">
            <div class="lte-modal-content">
                <h2><?php _e('Modifier les points', 'life-travel-excursion'); ?></h2>
                
                <form id="lte-edit-points-form">
                    <input type="hidden" id="lte-user-id" name="user_id" value="">
                    
                    <div class="lte-form-row">
                        <label for="lte-current-points"><?php _e('Points actuels', 'life-travel-excursion'); ?></label>
                        <input type="text" id="lte-current-points" readonly>
                    </div>
                    
                    <div class="lte-form-row">
                        <label for="lte-adjustment"><?php _e('Ajustement', 'life-travel-excursion'); ?></label>
                        <select id="lte-adjustment-type" name="adjustment_type">
                            <option value="add"><?php _e('Ajouter', 'life-travel-excursion'); ?></option>
                            <option value="subtract"><?php _e('Soustraire', 'life-travel-excursion'); ?></option>
                            <option value="set"><?php _e('Définir à', 'life-travel-excursion'); ?></option>
                        </select>
                        <input type="number" id="lte-points-amount" name="points_amount" min="0" step="1" required>
                    </div>
                    
                    <div class="lte-form-row">
                        <label for="lte-reason"><?php _e('Raison', 'life-travel-excursion'); ?></label>
                        <textarea id="lte-reason" name="reason" rows="3" required></textarea>
                    </div>
                    
                    <div class="lte-form-actions">
                        <button type="button" class="button" id="lte-cancel-edit"><?php _e('Annuler', 'life-travel-excursion'); ?></button>
                        <button type="submit" class="button button-primary"><?php _e('Enregistrer', 'life-travel-excursion'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}

// Initialiser le tableau de bord
new Life_Travel_Loyalty_Stats_Dashboard();
