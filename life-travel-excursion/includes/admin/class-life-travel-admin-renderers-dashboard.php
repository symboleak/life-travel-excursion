<?php
/**
 * Renderers du tableau de bord pour Life Travel
 *
 * Ce fichier contient les méthodes de rendu pour le tableau de bord principal
 *
 * @package Life Travel Excursion
 * @version 2.3.7
 */

defined('ABSPATH') || exit;

/**
 * Méthodes de rendu pour le tableau de bord
 */
trait Life_Travel_Admin_Renderers_Dashboard {
    
    /**
     * Affiche la vue d'ensemble du tableau de bord
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_dashboard_overview($page_id, $section_id) {
        // Obtenir des statistiques de base
        $stats = $this->get_site_statistics();
        
        // Afficher une interface simplifiée avec des grandes cartes cliquables
        ?>
        <div class="life-travel-dashboard">
            <div class="life-travel-welcome">
                <h2><?php _e('Bienvenue sur votre tableau de bord Life Travel', 'life-travel-excursion'); ?></h2>
                <p><?php _e('Ce tableau de bord a été conçu pour vous aider à gérer facilement toutes les fonctionnalités de votre site.', 'life-travel-excursion'); ?></p>
            </div>
            
            <div class="life-travel-status-panel">
                <h3><?php _e('État du site', 'life-travel-excursion'); ?></h3>
                
                <div class="life-travel-status-items">
                    <!-- État de la connexion -->
                    <div class="life-travel-status-item <?php echo esc_attr($stats['connection_status']); ?>">
                        <span class="dashicons dashicons-admin-site"></span>
                        <h4><?php _e('Connexion Internet', 'life-travel-excursion'); ?></h4>
                        <div class="life-travel-status-value">
                            <?php 
                            if ($stats['connection_status'] === 'good') {
                                _e('Bonne', 'life-travel-excursion');
                            } elseif ($stats['connection_status'] === 'medium') {
                                _e('Moyenne', 'life-travel-excursion');
                            } else {
                                _e('Lente', 'life-travel-excursion');
                            }
                            ?>
                        </div>
                        <p class="life-travel-status-desc">
                            <?php 
                            if ($stats['connection_status'] === 'slow') {
                                _e('Des optimisations sont en place pour les connexions lentes', 'life-travel-excursion');
                            }
                            ?>
                        </p>
                    </div>
                    
                    <!-- État des excursions -->
                    <div class="life-travel-status-item">
                        <span class="dashicons dashicons-palmtree"></span>
                        <h4><?php _e('Excursions', 'life-travel-excursion'); ?></h4>
                        <div class="life-travel-status-value">
                            <?php echo esc_html($stats['excursions_count']); ?>
                        </div>
                        <p class="life-travel-status-desc">
                            <?php _e('Nombre total d\'excursions', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <!-- Réservations récentes -->
                    <div class="life-travel-status-item">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <h4><?php _e('Réservations récentes', 'life-travel-excursion'); ?></h4>
                        <div class="life-travel-status-value">
                            <?php echo esc_html($stats['recent_bookings']); ?>
                        </div>
                        <p class="life-travel-status-desc">
                            <?php _e('Au cours des 7 derniers jours', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <!-- Paniers abandonnés -->
                    <div class="life-travel-status-item">
                        <span class="dashicons dashicons-cart"></span>
                        <h4><?php _e('Paniers abandonnés', 'life-travel-excursion'); ?></h4>
                        <div class="life-travel-status-value">
                            <?php echo esc_html($stats['abandoned_carts']); ?>
                        </div>
                        <p class="life-travel-status-desc">
                            <?php _e('À récupérer', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="life-travel-quick-links">
                <h3><?php _e('Accès rapide', 'life-travel-excursion'); ?></h3>
                
                <div class="life-travel-card-grid">
                    <!-- Carte Excursions -->
                    <a href="<?php echo esc_url(admin_url('admin.php?page=life-travel-excursions')); ?>" class="life-travel-card">
                        <span class="dashicons dashicons-palmtree"></span>
                        <h4><?php _e('Gérer les excursions', 'life-travel-excursion'); ?></h4>
                        <p><?php _e('Créer, modifier ou planifier des excursions', 'life-travel-excursion'); ?></p>
                    </a>
                    
                    <!-- Carte Médias -->
                    <a href="<?php echo esc_url(admin_url('admin.php?page=life-travel-media')); ?>" class="life-travel-card">
                        <span class="dashicons dashicons-format-image"></span>
                        <h4><?php _e('Images et logo', 'life-travel-excursion'); ?></h4>
                        <p><?php _e('Gérer les médias du site', 'life-travel-excursion'); ?></p>
                    </a>
                    
                    <!-- Carte Paiements -->
                    <a href="<?php echo esc_url(admin_url('admin.php?page=life-travel-payments')); ?>" class="life-travel-card">
                        <span class="dashicons dashicons-money-alt"></span>
                        <h4><?php _e('Modes de paiement', 'life-travel-excursion'); ?></h4>
                        <p><?php _e('Configurer les paiements', 'life-travel-excursion'); ?></p>
                    </a>
                    
                    <!-- Carte Récupération -->
                    <a href="<?php echo esc_url(admin_url('admin.php?page=life-travel-abandoned_carts')); ?>" class="life-travel-card">
                        <span class="dashicons dashicons-cart"></span>
                        <h4><?php _e('Récupérer des ventes', 'life-travel-excursion'); ?></h4>
                        <p><?php _e('Récupérer les paniers abandonnés', 'life-travel-excursion'); ?></p>
                    </a>
                </div>
            </div>
            
            <div class="life-travel-latest-activity">
                <h3><?php _e('Activité récente', 'life-travel-excursion'); ?></h3>
                
                <?php if (!empty($stats['recent_activity'])) : ?>
                    <ul class="life-travel-activity-list">
                        <?php foreach ($stats['recent_activity'] as $activity) : ?>
                            <li>
                                <span class="activity-date"><?php echo esc_html($activity['date']); ?></span>
                                <span class="activity-type"><?php echo esc_html($activity['type']); ?></span>
                                <span class="activity-desc"><?php echo esc_html($activity['description']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="life-travel-no-activity">
                        <?php _e('Aucune activité récente à afficher.', 'life-travel-excursion'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Affiche les actions rapides du tableau de bord
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_dashboard_actions($page_id, $section_id) {
        // Actions communes pour les administrateurs non techniques
        ?>
        <div class="life-travel-quick-actions">
            <h3><?php _e('Actions courantes', 'life-travel-excursion'); ?></h3>
            
            <div class="life-travel-action-grid">
                <!-- Créer une excursion -->
                <div class="life-travel-action-card">
                    <h4><?php _e('Nouvelle excursion', 'life-travel-excursion'); ?></h4>
                    <p><?php _e('Créer une nouvelle excursion ou voyage', 'life-travel-excursion'); ?></p>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=excursion_custom')); ?>" class="button button-primary">
                        <?php _e('Créer maintenant', 'life-travel-excursion'); ?>
                    </a>
                </div>
                
                <!-- Changer le logo -->
                <div class="life-travel-action-card">
                    <h4><?php _e('Mettre à jour le logo', 'life-travel-excursion'); ?></h4>
                    <p><?php _e('Changer le logo principal du site', 'life-travel-excursion'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=life-travel-media&section=logo')); ?>" class="button button-primary">
                        <?php _e('Modifier le logo', 'life-travel-excursion'); ?>
                    </a>
                </div>
                
                <!-- Tester la connexion -->
                <div class="life-travel-action-card">
                    <h4><?php _e('Vérifier la connexion', 'life-travel-excursion'); ?></h4>
                    <p><?php _e('Analyser la vitesse de connexion du site', 'life-travel-excursion'); ?></p>
                    <button type="button" id="life-travel-test-connection" class="button button-secondary">
                        <?php _e('Tester maintenant', 'life-travel-excursion'); ?>
                    </button>
                    <span class="spinner"></span>
                    <div id="life-travel-connection-result"></div>
                </div>
                
                <!-- Récupérer des paniers -->
                <div class="life-travel-action-card">
                    <h4><?php _e('Récupérer des ventes', 'life-travel-excursion'); ?></h4>
                    <p><?php _e('Envoyer des rappels pour paniers abandonnés', 'life-travel-excursion'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=life-travel-abandoned_carts&section=recovery')); ?>" class="button button-primary">
                        <?php _e('Voir les paniers', 'life-travel-excursion'); ?>
                    </a>
                </div>
            </div>
            
            <h3><?php _e('Tutoriels vidéo', 'life-travel-excursion'); ?></h3>
            
            <div class="life-travel-video-tutorials">
                <div class="life-travel-video-card">
                    <h4><?php _e('Créer une excursion', 'life-travel-excursion'); ?></h4>
                    <div class="life-travel-video-preview" data-video-id="VIDEO_ID_1">
                        <img src="<?php echo esc_url(LIFE_TRAVEL_EXCURSION_URL . 'assets/img/backgrounds/video-placeholder.svg'); ?>" alt="Tutoriel vidéo">
                        <span class="dashicons dashicons-controls-play"></span>
                    </div>
                </div>
                
                <div class="life-travel-video-card">
                    <h4><?php _e('Gérer les réservations', 'life-travel-excursion'); ?></h4>
                    <div class="life-travel-video-preview" data-video-id="VIDEO_ID_2">
                        <img src="<?php echo esc_url(LIFE_TRAVEL_EXCURSION_URL . 'assets/img/backgrounds/video-placeholder.svg'); ?>" alt="Tutoriel vidéo">
                        <span class="dashicons dashicons-controls-play"></span>
                    </div>
                </div>
                
                <div class="life-travel-video-card">
                    <h4><?php _e('Personnaliser le site', 'life-travel-excursion'); ?></h4>
                    <div class="life-travel-video-preview" data-video-id="VIDEO_ID_3">
                        <img src="<?php echo esc_url(LIFE_TRAVEL_EXCURSION_URL . 'assets/img/backgrounds/video-placeholder.svg'); ?>" alt="Tutoriel vidéo">
                        <span class="dashicons dashicons-controls-play"></span>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Test de connexion
            $('#life-travel-test-connection').on('click', function() {
                var $button = $(this);
                var $spinner = $button.next('.spinner');
                var $result = $('#life-travel-connection-result');
                
                $button.prop('disabled', true);
                $spinner.css('visibility', 'visible');
                $result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'life_travel_test_connection',
                        nonce: lifeTravelAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var statusClass = '';
                            if (response.data.speed === 'fast') {
                                statusClass = 'good';
                            } else if (response.data.speed === 'medium') {
                                statusClass = 'warning';
                            } else {
                                statusClass = 'error';
                            }
                            
                            $result.html('<div class="life-travel-status ' + statusClass + '">' + 
                                '<span class="dashicons dashicons-yes-alt"></span> ' +
                                response.data.message +
                                '</div>'
                            );
                        } else {
                            $result.html('<div class="life-travel-status error">' + 
                                '<span class="dashicons dashicons-warning"></span> ' +
                                response.data.message +
                                '</div>'
                            );
                        }
                    },
                    error: function() {
                        $result.html('<div class="life-travel-status error">' + 
                            '<span class="dashicons dashicons-warning"></span> ' +
                            lifeTravelAdmin.i18n.error +
                            '</div>'
                        );
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                        $spinner.css('visibility', 'hidden');
                    }
                });
            });
            
            // Tutoriels vidéo
            $('.life-travel-video-preview').on('click', function() {
                var videoId = $(this).data('video-id');
                // Code pour afficher la vidéo dans une lightbox
            });
        });
        </script>
        <?php
    }
    
    /**
     * Récupère les statistiques du site avec système de cache optimisé
     * 
     * Cette méthode utilise un cache transient pour éviter de surcharger
     * la base de données. Particulièrement adaptée au contexte camerounais
     * avec des connexions potentiellement instables.
     * 
     * @return array Statistiques du site
     */
    private function get_site_statistics() {
        // Vérifier si les statistiques sont en cache
        $cached_stats = get_transient('life_travel_site_stats');
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        $stats = array(
            'connection_status' => 'good', // Valeur par défaut
            'excursions_count' => 0,
            'recent_bookings' => 0,
            'abandoned_carts' => 0,
            'recent_activity' => array()
        );
        
        // Vérifier la connexion
        if (function_exists('life_travel_get_connection_status')) {
            $connection = life_travel_get_connection_status();
            $stats['connection_status'] = $connection;
        }
        
        // Compter les excursions
        $excursions_query = new WP_Query(array(
            'post_type' => 'excursion_custom',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        $stats['excursions_count'] = $excursions_query->found_posts;
        
        // Compter les réservations récentes
        if (function_exists('wc_get_orders')) {
            $args = array(
                'status' => array('wc-processing', 'wc-completed'),
                'date_created' => '>' . (time() - 7 * DAY_IN_SECONDS),
                'limit' => -1,
                'return' => 'ids',
            );
            
            $orders = wc_get_orders($args);
            $stats['recent_bookings'] = count($orders);
        }
        
        // Compter les paniers abandonnés
        if (class_exists('Life_Travel_Abandoned_Cart')) {
            $abandoned_carts = Life_Travel_Abandoned_Cart::get_abandoned_carts();
            $stats['abandoned_carts'] = count($abandoned_carts);
        }
        
        // Activité récente
        $recent_activity = array();
        
        // Dernières commandes
        if (function_exists('wc_get_orders')) {
            $args = array(
                'limit' => 5,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'objects',
            );
            
            $orders = wc_get_orders($args);
            
            foreach ($orders as $order) {
                $recent_activity[] = array(
                    'date' => $order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
                    'type' => __('Nouvelle commande', 'life-travel-excursion'),
                    'description' => sprintf(
                        __('Commande #%s - %s', 'life-travel-excursion'),
                        $order->get_order_number(),
                        $order->get_formatted_order_total()
                    )
                );
            }
        }
        
        // Dernières pages modifiées
        $recent_posts_query = new WP_Query(array(
            'post_type' => array('post', 'page', 'excursion_custom'),
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'modified',
            'order' => 'DESC'
        ));
        
        if ($recent_posts_query->have_posts()) {
            while ($recent_posts_query->have_posts()) {
                $recent_posts_query->the_post();
                
                $recent_activity[] = array(
                    'date' => get_the_modified_date(get_option('date_format') . ' ' . get_option('time_format')),
                    'type' => __('Contenu modifié', 'life-travel-excursion'),
                    'description' => sprintf(
                        __('%s - %s', 'life-travel-excursion'),
                        get_the_title(),
                        get_post_type_object(get_post_type())->labels->singular_name
                    )
                );
            }
            wp_reset_postdata();
        }
        
        // Trier par date
        usort($recent_activity, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        // Limiter à 10 éléments
        $stats['recent_activity'] = array_slice($recent_activity, 0, 10);
        
        // Ajouter des métriques spécifiques pour le contexte camerounais
        if (class_exists('Life_Travel_Cameroon_Assets_Optimizer')) {
            $cameroon_optimizer = Life_Travel_Cameroon_Assets_Optimizer::get_instance();
            $network_stats = $cameroon_optimizer->get_network_stats();
            $stats['network_speed'] = $network_stats['speed'] ?? 'unknown';
            $stats['network_stability'] = $network_stats['stability'] ?? 'unknown';
            $stats['offline_support'] = $network_stats['offline_capable'] ?? false;
        }
        
        // Mettre en cache les résultats pendant 15 minutes
        // Un temps plus court que pour les autres caches car ces données changent fréquemment
        set_transient('life_travel_site_stats', $stats, 15 * MINUTE_IN_SECONDS);
        
        return $stats;
    }
}
