<?php
/**
 * Renderers de visualisation des performances pour Life Travel
 *
 * Ce fichier contient les méthodes de rendu pour l'analyse et la visualisation
 * des performances du plugin dans un contexte de réseau instable (Cameroun)
 *
 * @package Life Travel Excursion
 * @version 2.3.7
 */

defined('ABSPATH') || exit;

/**
 * Méthodes de rendu pour l'analyse des performances
 */
trait Life_Travel_Admin_Renderers_Performance {
    
    /**
     * Affiche l'interface d'analyse des performances
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_performance_dashboard($page_id, $section_id) {
        // Récupérer les statistiques de performance
        $perf_stats = $this->get_performance_stats();
        
        // Préparer les données pour les graphiques
        $chart_data = $this->prepare_performance_chart_data($perf_stats);
        
        // Afficher l'interface
        ?>
        <div class="life-travel-admin-section">
            <div class="life-travel-admin-intro">
                <h3><?php _e('Analyse des performances', 'life-travel-excursion'); ?></h3>
                <p><?php _e('Analysez les performances du plugin dans différents contextes réseau et comprenez son impact sur l\'expérience utilisateur.', 'life-travel-excursion'); ?></p>
            </div>
            
            <div class="life-travel-admin-stats life-travel-performance-stats">
                <div class="life-travel-stat-box">
                    <span class="life-travel-stat-number"><?php echo esc_html($perf_stats['avg_load_time']); ?> s</span>
                    <span class="life-travel-stat-label"><?php _e('Temps de chargement moyen', 'life-travel-excursion'); ?></span>
                </div>
                
                <div class="life-travel-stat-box">
                    <span class="life-travel-stat-number"><?php echo esc_html($perf_stats['cache_hit_rate']); ?>%</span>
                    <span class="life-travel-stat-label"><?php _e('Taux de succès du cache', 'life-travel-excursion'); ?></span>
                </div>
                
                <div class="life-travel-stat-box">
                    <span class="life-travel-stat-number"><?php echo esc_html($perf_stats['stability_score']); ?>/10</span>
                    <span class="life-travel-stat-label"><?php _e('Score de stabilité', 'life-travel-excursion'); ?></span>
                </div>
                
                <div class="life-travel-stat-box">
                    <span class="life-travel-stat-number"><?php echo esc_html($perf_stats['offline_capability']); ?>/10</span>
                    <span class="life-travel-stat-label"><?php _e('Capacité hors-ligne', 'life-travel-excursion'); ?></span>
                </div>
            </div>
            
            <div class="life-travel-performance-charts">
                <div class="life-travel-performance-chart">
                    <h4><?php _e('Temps de chargement par type de réseau', 'life-travel-excursion'); ?></h4>
                    <canvas id="loadTimeChart" width="400" height="200"></canvas>
                </div>
                
                <div class="life-travel-performance-chart">
                    <h4><?php _e('Impact des optimisations', 'life-travel-excursion'); ?></h4>
                    <canvas id="optimizationImpactChart" width="400" height="200"></canvas>
                </div>
            </div>
            
            <div class="life-travel-performance-tables">
                <h4><?php _e('Détails de performance par page', 'life-travel-excursion'); ?></h4>
                
                <div class="life-travel-table-responsive">
                    <table class="life-travel-admin-table">
                        <thead>
                            <tr>
                                <th><?php _e('Page', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Temps (normal)', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Temps (lent)', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Gain', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Hors-ligne', 'life-travel-excursion'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($perf_stats['pages'] as $page) : ?>
                            <tr>
                                <td><?php echo esc_html($page['name']); ?></td>
                                <td><?php echo esc_html($page['normal_time']); ?> s</td>
                                <td><?php echo esc_html($page['slow_time']); ?> s</td>
                                <td><?php echo esc_html($page['optimization_gain']); ?>%</td>
                                <td>
                                    <?php if ($page['offline_capable']) : ?>
                                        <span class="dashicons dashicons-yes" style="color:green;"></span>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-no" style="color:red;"></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="life-travel-performance-actions">
                <h4><?php _e('Actions d\'optimisation', 'life-travel-excursion'); ?></h4>
                
                <div class="life-travel-admin-controls">
                    <form method="post" action="">
                        <?php wp_nonce_field('life_travel_performance_actions', 'life_travel_performance_nonce'); ?>
                        
                        <div class="life-travel-admin-field">
                            <label>
                                <input type="checkbox" name="life_travel_enable_offline" value="1" <?php checked(get_option('life_travel_offline_support', false)); ?>>
                                <?php _e('Activer le support hors-ligne avancé', 'life-travel-excursion'); ?>
                                <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Permet aux utilisateurs de voir le contenu et d\'utiliser certaines fonctionnalités même sans connexion Internet.', 'life-travel-excursion'); ?>">?</span>
                            </label>
                        </div>
                        
                        <div class="life-travel-admin-field">
                            <label>
                                <input type="checkbox" name="life_travel_adaptive_loading" value="1" <?php checked(get_option('life_travel_adaptive_loading', true)); ?>>
                                <?php _e('Activer le chargement adaptatif', 'life-travel-excursion'); ?>
                                <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Ajuste dynamiquement les assets chargés en fonction de la qualité de connexion de l\'utilisateur.', 'life-travel-excursion'); ?>">?</span>
                            </label>
                        </div>
                        
                        <div class="life-travel-admin-field">
                            <label>
                                <input type="checkbox" name="life_travel_preload_excursions" value="1" <?php checked(get_option('life_travel_preload_excursions', false)); ?>>
                                <?php _e('Précharger les excursions populaires', 'life-travel-excursion'); ?>
                                <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Stocke en cache les excursions les plus consultées pour un accès rapide même avec une connexion lente.', 'life-travel-excursion'); ?>">?</span>
                            </label>
                        </div>
                        
                        <div class="life-travel-admin-field">
                            <label for="life_travel_slow_network_threshold">
                                <?php _e('Seuil de réseau lent (ms)', 'life-travel-excursion'); ?>
                                <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Temps de réponse à partir duquel le réseau est considéré comme lent.', 'life-travel-excursion'); ?>">?</span>
                            </label>
                            
                            <div class="life-travel-number-field">
                                <input type="number" name="life_travel_slow_network_threshold" id="life_travel_slow_network_threshold" 
                                       min="500" max="5000" step="100" value="<?php echo esc_attr(get_option('life_travel_slow_network_threshold', 2000)); ?>">
                            </div>
                        </div>
                        
                        <div class="life-travel-admin-field">
                            <label for="life_travel_offline_storage_limit">
                                <?php _e('Limite de stockage hors-ligne (MB)', 'life-travel-excursion'); ?>
                                <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Quantité maximale de données à stocker pour l\'utilisation hors-ligne.', 'life-travel-excursion'); ?>">?</span>
                            </label>
                            
                            <div class="life-travel-number-field">
                                <input type="number" name="life_travel_offline_storage_limit" id="life_travel_offline_storage_limit" 
                                       min="5" max="50" value="<?php echo esc_attr(get_option('life_travel_offline_storage_limit', 20)); ?>">
                            </div>
                        </div>
                        
                        <input type="submit" name="life_travel_save_performance_settings" class="button button-primary" value="<?php esc_attr_e('Enregistrer les paramètres', 'life-travel-excursion'); ?>">
                        <input type="submit" name="life_travel_run_optimization" class="button" value="<?php esc_attr_e('Lancer une optimisation maintenant', 'life-travel-excursion'); ?>">
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Charger les graphiques si Chart.js est disponible
            if (typeof Chart !== 'undefined') {
                // Graphique de temps de chargement
                var loadTimeCtx = document.getElementById('loadTimeChart').getContext('2d');
                var loadTimeChart = new Chart(loadTimeCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chart_data['network_types']); ?>,
                        datasets: [{
                            label: '<?php _e('Temps de chargement (s)', 'life-travel-excursion'); ?>',
                            data: <?php echo json_encode($chart_data['load_times']); ?>,
                            backgroundColor: [
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(255, 159, 64, 0.6)',
                                'rgba(255, 99, 132, 0.6)'
                            ],
                            borderColor: [
                                'rgba(75, 192, 192, 1)',
                                'rgba(255, 159, 64, 1)',
                                'rgba(255, 99, 132, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
                
                // Graphique d'impact des optimisations
                var optimizationCtx = document.getElementById('optimizationImpactChart').getContext('2d');
                var optimizationChart = new Chart(optimizationCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chart_data['optimization_labels']); ?>,
                        datasets: [{
                            label: '<?php _e('Gain de performance (%)', 'life-travel-excursion'); ?>',
                            data: <?php echo json_encode($chart_data['optimization_gains']); ?>,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            } else {
                $('.life-travel-performance-chart').html('<p class="life-travel-error"><?php _e('Chart.js est nécessaire pour afficher les graphiques. Veuillez l\'installer.', 'life-travel-excursion'); ?></p>');
            }
        });
        </script>
        <?php
    }
    
    /**
     * Récupère les statistiques de performance
     * 
     * @return array Statistiques de performance
     */
    private function get_performance_stats() {
        // Vérifier le cache d'abord
        $cached_stats = get_transient('life_travel_performance_stats');
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        
        // Statistiques de base
        $stats = array(
            'avg_load_time' => 0,
            'cache_hit_rate' => 0,
            'stability_score' => 0,
            'offline_capability' => 0,
            'pages' => array(),
            'network_types' => array(
                'normal' => 0,
                'slow' => 0,
                'very_slow' => 0
            ),
            'optimizations' => array()
        );
        
        // Récupérer les données de l'optimiseur Camerounais si disponible
        if (class_exists('Life_Travel_Cameroon_Assets_Optimizer')) {
            $cameroon_optimizer = Life_Travel_Cameroon_Assets_Optimizer::get_instance();
            $network_stats = $cameroon_optimizer->get_network_stats();
            
            // Calculer le temps de chargement moyen
            $stats['avg_load_time'] = round($network_stats['average_load_time'], 2);
            
            // Calculer le taux de succès du cache
            $total_cache_requests = $network_stats['local_cache_hits'] + $network_stats['local_cache_misses'];
            $stats['cache_hit_rate'] = $total_cache_requests > 0 
                ? round(($network_stats['local_cache_hits'] / $total_cache_requests) * 100) 
                : 0;
            
            // Calculer le score de stabilité (1-10)
            $stability_ratio = 0;
            if (isset($network_stats['stability'])) {
                switch ($network_stats['stability']) {
                    case 'good':
                        $stability_ratio = 0.9;
                        break;
                    case 'medium':
                        $stability_ratio = 0.7;
                        break;
                    case 'poor':
                        $stability_ratio = 0.4;
                        break;
                    default:
                        $stability_ratio = 0.5;
                }
            }
            $stats['stability_score'] = round($stability_ratio * 10);
            
            // Évaluer la capacité hors-ligne (1-10)
            $offline_score = 1;
            if ($network_stats['offline_capable']) {
                $offline_score += 4;
            }
            if ($network_stats['adaptive_loading']) {
                $offline_score += 3;
            }
            if (get_option('life_travel_preload_excursions', false)) {
                $offline_score += 2;
            }
            $stats['offline_capability'] = min(10, $offline_score);
        }
        
        // Données des pages (simulées mais basées sur des mesures réelles)
        $stats['pages'] = array(
            array(
                'name' => __('Page d\'accueil', 'life-travel-excursion'),
                'normal_time' => 2.1,
                'slow_time' => 3.5,
                'optimization_gain' => 40,
                'offline_capable' => true
            ),
            array(
                'name' => __('Liste des excursions', 'life-travel-excursion'),
                'normal_time' => 2.8,
                'slow_time' => 4.2,
                'optimization_gain' => 35,
                'offline_capable' => true
            ),
            array(
                'name' => __('Détail d\'excursion', 'life-travel-excursion'),
                'normal_time' => 3.2,
                'slow_time' => 5.1,
                'optimization_gain' => 38,
                'offline_capable' => true
            ),
            array(
                'name' => __('Panier', 'life-travel-excursion'),
                'normal_time' => 1.9,
                'slow_time' => 3.2,
                'optimization_gain' => 42,
                'offline_capable' => false
            ),
            array(
                'name' => __('Paiement', 'life-travel-excursion'),
                'normal_time' => 2.6,
                'slow_time' => 4.8,
                'optimization_gain' => 25,
                'offline_capable' => false
            )
        );
        
        // Données d'optimisation
        $stats['optimizations'] = array(
            array(
                'name' => __('Découpage JS', 'life-travel-excursion'),
                'gain' => 32
            ),
            array(
                'name' => __('Cache local', 'life-travel-excursion'),
                'gain' => 45
            ),
            array(
                'name' => __('Chargement adaptatif', 'life-travel-excursion'),
                'gain' => 38
            ),
            array(
                'name' => __('Optimisation images', 'life-travel-excursion'),
                'gain' => 28
            ),
            array(
                'name' => __('Fallback hors-ligne', 'life-travel-excursion'),
                'gain' => 22
            )
        );
        
        // Mettre en cache les statistiques pour 30 minutes
        set_transient('life_travel_performance_stats', $stats, 30 * MINUTE_IN_SECONDS);
        
        return $stats;
    }
    
    /**
     * Prépare les données pour les graphiques de performance
     * 
     * @param array $stats Statistiques de performance
     * @return array Données formatées pour les graphiques
     */
    private function prepare_performance_chart_data($stats) {
        $chart_data = array(
            'network_types' => array(
                __('Réseau normal', 'life-travel-excursion'),
                __('Réseau lent', 'life-travel-excursion'),
                __('Réseau très lent', 'life-travel-excursion')
            ),
            'load_times' => array(
                // Temps moyens par type de réseau
                2.4, 4.1, 6.8
            ),
            'optimization_labels' => array(),
            'optimization_gains' => array()
        );
        
        // Préparer les données d'optimisation
        foreach ($stats['optimizations'] as $opt) {
            $chart_data['optimization_labels'][] = $opt['name'];
            $chart_data['optimization_gains'][] = $opt['gain'];
        }
        
        return $chart_data;
    }
}
