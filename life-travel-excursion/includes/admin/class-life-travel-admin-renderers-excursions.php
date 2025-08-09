<?php
/**
 * Renderers de gestion des excursions pour Life Travel
 *
 * Ce fichier contient les méthodes de rendu pour la gestion centralisée
 * des excursions et leurs paramètres dans l'interface administrateur unifiée
 *
 * @package Life Travel Excursion
 * @version 2.3.7
 */

defined('ABSPATH') || exit;

/**
 * Méthodes de rendu pour la gestion des excursions
 */
trait Life_Travel_Admin_Renderers_Excursions {
    
    /**
     * Affiche l'interface principale de gestion des excursions
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_excursions_dashboard($page_id, $section_id) {
        // Récupérer les statistiques des excursions
        $excursion_stats = $this->get_excursions_stats();
        
        // Afficher l'interface utilisateur
        ?>
        <div class="life-travel-admin-section">
            <div class="life-travel-admin-intro">
                <h3><?php _e('Tableau de bord des excursions', 'life-travel-excursion'); ?></h3>
                <p><?php _e('Gérez de manière centralisée toutes vos offres d\'excursions et leurs paramètres.', 'life-travel-excursion'); ?></p>
                
                <div class="life-travel-admin-actions">
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=excursion')); ?>" class="button button-primary">
                        <span class="dashicons dashicons-plus"></span> <?php _e('Ajouter une excursion', 'life-travel-excursion'); ?>
                    </a>
                    <a href="#life-travel-global-settings" class="button">
                        <span class="dashicons dashicons-admin-settings"></span> <?php _e('Paramètres globaux', 'life-travel-excursion'); ?>
                    </a>
                </div>
            </div>
            
            <div class="life-travel-admin-stats">
                <div class="life-travel-stat-box">
                    <span class="life-travel-stat-number"><?php echo esc_html($excursion_stats['total']); ?></span>
                    <span class="life-travel-stat-label"><?php _e('Excursions actives', 'life-travel-excursion'); ?></span>
                </div>
                
                <div class="life-travel-stat-box">
                    <span class="life-travel-stat-number"><?php echo esc_html($excursion_stats['bookings']); ?></span>
                    <span class="life-travel-stat-label"><?php _e('Réservations (30j)', 'life-travel-excursion'); ?></span>
                </div>
                
                <div class="life-travel-stat-box">
                    <span class="life-travel-stat-number"><?php echo esc_html($excursion_stats['popular_category']); ?></span>
                    <span class="life-travel-stat-label"><?php _e('Catégorie populaire', 'life-travel-excursion'); ?></span>
                </div>
                
                <div class="life-travel-stat-box">
                    <span class="life-travel-stat-number"><?php echo esc_html(number_format($excursion_stats['revenue'], 0, ',', ' ')); ?> FCFA</span>
                    <span class="life-travel-stat-label"><?php _e('Revenus (30j)', 'life-travel-excursion'); ?></span>
                </div>
            </div>
            
            <div class="life-travel-admin-excursions-list">
                <h4><?php _e('Excursions récentes', 'life-travel-excursion'); ?></h4>
                
                <div class="life-travel-table-responsive">
                    <table class="life-travel-admin-table">
                        <thead>
                            <tr>
                                <th><?php _e('Image', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Titre', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Catégorie', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Prix', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Réservations', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Actions', 'life-travel-excursion'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($excursion_stats['recent_excursions'] as $excursion) : ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($excursion['image'])) : ?>
                                            <img src="<?php echo esc_url($excursion['image']); ?>" alt="<?php echo esc_attr($excursion['title']); ?>" class="life-travel-admin-thumbnail">
                                        <?php else : ?>
                                            <div class="life-travel-no-image"></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($excursion['title']); ?></td>
                                    <td><?php echo esc_html($excursion['category']); ?></td>
                                    <td><?php echo esc_html(number_format($excursion['price'], 0, ',', ' ')); ?> FCFA</td>
                                    <td><?php echo esc_html($excursion['bookings']); ?></td>
                                    <td>
                                        <div class="life-travel-admin-actions">
                                            <a href="<?php echo esc_url($excursion['edit_url']); ?>" class="button button-small">
                                                <span class="dashicons dashicons-edit"></span>
                                            </a>
                                            <a href="<?php echo esc_url($excursion['view_url']); ?>" class="button button-small" target="_blank">
                                                <span class="dashicons dashicons-visibility"></span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="life-travel-admin-view-all">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=excursion')); ?>" class="button">
                        <?php _e('Voir toutes les excursions', 'life-travel-excursion'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Récupère les statistiques d'excursions depuis la base de données
     * 
     * Récupère des statistiques réelles sur les excursions, incluant le nombre total,
     * les réservations, la catégorie la plus populaire, le chiffre d'affaires et
     * les excursions récentes avec leurs détails.
     * 
     * @return array Tableau associatif des statistiques
     */
    private function get_excursions_stats() {
        global $wpdb;
        
        // Initialiser les statistiques
        $stats = array(
            'total' => 0,
            'bookings' => 0,
            'popular_category' => '',
            'revenue' => 0,
            'recent_excursions' => array()
        );
        
        // Récupérer les excursions récentes
        $recent_excursions = get_posts(array(
            'post_type' => 'excursion',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (!empty($recent_excursions)) {
            foreach ($recent_excursions as $excursion) {
                $image = get_the_post_thumbnail_url($excursion->ID, 'thumbnail');
                $categories = get_the_terms($excursion->ID, 'excursion_category');
                $category_name = !empty($categories) ? $categories[0]->name : __('Non catégorisé', 'life-travel-excursion');
                
                // Récupérer le prix (en supposant que c'est un custom field)
                $price = get_post_meta($excursion->ID, '_excursion_price', true);
                $price = !empty($price) ? $price : 0;
                
                // Compter les réservations (implémentation simplifiée)
                $bookings_count = 0; // À remplacer par le vrai comptage
                
                $stats['recent_excursions'][] = array(
                    'id' => $excursion->ID,
                    'title' => $excursion->post_title,
                    'image' => $image,
                    'category' => $category_name,
                    'price' => $price,
                    'bookings' => $bookings_count,
                    'edit_url' => get_edit_post_link($excursion->ID),
                    'view_url' => get_permalink($excursion->ID)
                );
            }
        }
        
        // Mettre à jour les statistiques globales
        $stats['total'] = wp_count_posts('excursion')->publish;
        
        // 1. Calcul du nombre total de réservations
        global $wpdb;
        $bookings_query = 
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-processing', 'wc-completed')
            AND oim.meta_key = '_product_id'
            AND oim.meta_value IN (
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'excursion' AND post_status = 'publish'
            )";
            
        $total_bookings = $wpdb->get_var($bookings_query);
        $stats['bookings'] = $total_bookings ?: 0;
        
        // 2. Déterminer la catégorie d'excursion la plus populaire
        $popular_category_query = 
            "SELECT t.name, COUNT(*) as booking_count
            FROM {$wpdb->posts} p
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            JOIN {$wpdb->term_relationships} tr ON oim.meta_value = tr.object_id
            JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-processing', 'wc-completed')
            AND oim.meta_key = '_product_id'
            AND tt.taxonomy = 'excursion_category'
            GROUP BY t.name
            ORDER BY booking_count DESC
            LIMIT 1";
            
        $popular_category = $wpdb->get_row($popular_category_query);
        $stats['popular_category'] = $popular_category ? $popular_category->name : 'Non catégorisé';
        
        // 3. Calculer le chiffre d'affaires total des excursions
        $revenue_query = 
            "SELECT SUM(oim_total.meta_value) as total_revenue
            FROM {$wpdb->posts} p
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_total ON oi.order_item_id = oim_total.order_item_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-processing', 'wc-completed')
            AND oim.meta_key = '_product_id'
            AND oim_total.meta_key = '_line_total'
            AND oim.meta_value IN (
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'excursion' AND post_status = 'publish'
            )";
            
        $total_revenue = $wpdb->get_var($revenue_query);
        $stats['revenue'] = $total_revenue ?: 0;
        
        // 4. Améliorer le comptage des réservations pour chaque excursion récente
        if (!empty($stats['recent_excursions'])) {
            foreach ($stats['recent_excursions'] as $key => $excursion) {
                $excursion_id = $excursion['id'];
                
                // Compter les réservations réelles pour cette excursion
                $excursion_bookings_query = $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} p
                    JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
                    JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                    WHERE p.post_type = 'shop_order'
                    AND p.post_status IN ('wc-processing', 'wc-completed')
                    AND oim.meta_key = '_product_id'
                    AND oim.meta_value = %d",
                    $excursion_id
                );
                
                $bookings_count = $wpdb->get_var($excursion_bookings_query);
                $stats['recent_excursions'][$key]['bookings'] = $bookings_count ?: 0;
            }
        }
        
        // Mise en cache pour améliorer les performances
        set_transient('life_travel_excursions_stats', $stats, HOUR_IN_SECONDS);
        
        return $stats;
    }
    
    /**
     * Affiche l'interface de gestion des paramètres globaux des excursions
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_excursions_settings($page_id, $section_id) {
        // Récupérer les paramètres actuels
        $min_booking_time = get_option('life_travel_min_booking_time', 24);
        $group_discount = get_option('life_travel_group_discount', 10);
        $cancellation_policy = get_option('life_travel_cancellation_policy', 48);
        $cancellation_fee = get_option('life_travel_cancellation_fee', 25);
        $seasonal_rates_enabled = get_option('life_travel_seasonal_rates_enabled', 'on');
        $high_season_months = get_option('life_travel_high_season_months', array(11, 12, 1, 2));
        $high_season_markup = get_option('life_travel_high_season_markup', 15);
        $low_season_months = get_option('life_travel_low_season_months', array(6, 7, 8));
        $low_season_discount = get_option('life_travel_low_season_discount', 10);
        
        // Traitement du formulaire
        if (isset($_POST['life_travel_save_excursion_settings']) && check_admin_referer('life_travel_save_excursion_settings')) {
            // Temps minimum de réservation
            if (isset($_POST['life_travel_min_booking_time'])) {
                $min_time = intval($_POST['life_travel_min_booking_time']);
                $min_time = max(1, min(72, $min_time)); // Limiter entre 1 et 72 heures
                update_option('life_travel_min_booking_time', $min_time);
                $min_booking_time = $min_time;
            }
            
            // Remise pour groupes
            if (isset($_POST['life_travel_group_discount'])) {
                $discount = intval($_POST['life_travel_group_discount']);
                $discount = max(0, min(30, $discount)); // Limiter entre 0 et 30%
                update_option('life_travel_group_discount', $discount);
                $group_discount = $discount;
            }
            
            // Politique d'annulation
            if (isset($_POST['life_travel_cancellation_policy'])) {
                $policy = intval($_POST['life_travel_cancellation_policy']);
                $policy = max(12, min(72, $policy)); // Limiter entre 12 et 72 heures
                update_option('life_travel_cancellation_policy', $policy);
                $cancellation_policy = $policy;
            }
            
            // Frais d'annulation
            if (isset($_POST['life_travel_cancellation_fee'])) {
                $fee = intval($_POST['life_travel_cancellation_fee']);
                $fee = max(0, min(50, $fee)); // Limiter entre 0 et 50%
                update_option('life_travel_cancellation_fee', $fee);
                $cancellation_fee = $fee;
            }
            
            // Tarifs saisonniers
            $seasonal_rates_enabled = isset($_POST['life_travel_seasonal_rates_enabled']) ? 'on' : 'off';
            update_option('life_travel_seasonal_rates_enabled', $seasonal_rates_enabled);
            
            // Mois de haute saison
            if (isset($_POST['life_travel_high_season_months']) && is_array($_POST['life_travel_high_season_months'])) {
                $high_months = array_map('intval', $_POST['life_travel_high_season_months']);
                $high_months = array_filter($high_months, function($month) {
                    return $month >= 1 && $month <= 12;
                });
                update_option('life_travel_high_season_months', $high_months);
                $high_season_months = $high_months;
            }
            
            // Majoration haute saison
            if (isset($_POST['life_travel_high_season_markup'])) {
                $markup = intval($_POST['life_travel_high_season_markup']);
                $markup = max(0, min(50, $markup)); // Limiter entre 0 et 50%
                update_option('life_travel_high_season_markup', $markup);
                $high_season_markup = $markup;
            }
            
            // Mois de basse saison
            if (isset($_POST['life_travel_low_season_months']) && is_array($_POST['life_travel_low_season_months'])) {
                $low_months = array_map('intval', $_POST['life_travel_low_season_months']);
                $low_months = array_filter($low_months, function($month) {
                    return $month >= 1 && $month <= 12;
                });
                update_option('life_travel_low_season_months', $low_months);
                $low_season_months = $low_months;
            }
            
            // Remise basse saison
            if (isset($_POST['life_travel_low_season_discount'])) {
                $low_discount = intval($_POST['life_travel_low_season_discount']);
                $low_discount = max(0, min(50, $low_discount)); // Limiter entre 0 et 50%
                update_option('life_travel_low_season_discount', $low_discount);
                $low_season_discount = $low_discount;
            }
            
            echo '<div class="updated"><p>' . esc_html__('Paramètres des excursions enregistrés avec succès.', 'life-travel-excursion') . '</p></div>';
        }
        
        // Afficher l'interface utilisateur
        ?>
        <div class="life-travel-admin-section" id="life-travel-global-settings">
            <div class="life-travel-admin-intro">
                <h3><?php _e('Paramètres globaux des excursions', 'life-travel-excursion'); ?></h3>
                <p><?php _e('Configurez les paramètres qui s\'appliquent à toutes les excursions.', 'life-travel-excursion'); ?></p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('life_travel_save_excursion_settings'); ?>
                
                <div class="life-travel-admin-field-group">
                    <h4><?php _e('Options de réservation', 'life-travel-excursion'); ?></h4>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_min_booking_time">
                            <?php _e('Délai minimum de réservation (heures)', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Temps minimum avant le début de l\'excursion pour effectuer une réservation.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-number-field">
                            <input type="number" name="life_travel_min_booking_time" id="life_travel_min_booking_time" 
                                   min="1" max="72" value="<?php echo esc_attr($min_booking_time); ?>">
                        </div>
                        
                        <p class="description">
                            <?php _e('Recommandé : 24 heures (peut être modifié par excursion)', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_group_discount">
                            <?php _e('Remise pour groupes (%)', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Pourcentage de réduction pour les réservations de groupe (5 personnes ou plus).', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-number-field">
                            <input type="number" name="life_travel_group_discount" id="life_travel_group_discount" 
                                   min="0" max="30" value="<?php echo esc_attr($group_discount); ?>">
                        </div>
                        
                        <p class="description">
                            <?php _e('0 = aucune remise automatique pour les groupes', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="life-travel-admin-field-group">
                    <h4><?php _e('Politique d\'annulation', 'life-travel-excursion'); ?></h4>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_cancellation_policy">
                            <?php _e('Délai d\'annulation sans frais (heures)', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Temps avant le début de l\'excursion où l\'annulation est gratuite.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-number-field">
                            <input type="number" name="life_travel_cancellation_policy" id="life_travel_cancellation_policy" 
                                   min="12" max="72" value="<?php echo esc_attr($cancellation_policy); ?>">
                        </div>
                        
                        <p class="description">
                            <?php _e('Recommandé : 48 heures avant le début de l\'excursion', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_cancellation_fee">
                            <?php _e('Frais d\'annulation tardive (%)', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Pourcentage du prix total facturé pour une annulation en dehors du délai autorisé.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-number-field">
                            <input type="number" name="life_travel_cancellation_fee" id="life_travel_cancellation_fee" 
                                   min="0" max="50" value="<?php echo esc_attr($cancellation_fee); ?>">
                        </div>
                        
                        <p class="description">
                            <?php _e('Recommandé : 25% du prix de l\'excursion', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="life-travel-admin-field-group">
                    <h4><?php _e('Tarifs saisonniers', 'life-travel-excursion'); ?></h4>
                    
                    <div class="life-travel-admin-field">
                        <label>
                            <?php _e('Activer les tarifs saisonniers', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Permet d\'appliquer automatiquement des changements de prix selon les saisons.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-toggle-field">
                            <label class="life-travel-toggle">
                                <input type="checkbox" name="life_travel_seasonal_rates_enabled" <?php checked($seasonal_rates_enabled, 'on'); ?>>
                                <span class="life-travel-toggle-slider"></span>
                            </label>
                            <span class="life-travel-toggle-label"><?php _e('Activer les tarifs saisonniers', 'life-travel-excursion'); ?></span>
                        </div>
                    </div>
                    
                    <div class="life-travel-admin-seasonal-settings" id="life-travel-seasonal-settings">
                        <div class="life-travel-admin-field life-travel-season-field">
                            <label>
                                <?php _e('Mois de haute saison', 'life-travel-excursion'); ?>
                                <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Sélectionnez les mois qui constituent la haute saison touristique.', 'life-travel-excursion'); ?>">?</span>
                            </label>
                            
                            <div class="life-travel-month-checkboxes">
                                <?php 
                                $months = array(
                                    1 => __('Janvier', 'life-travel-excursion'),
                                    2 => __('Février', 'life-travel-excursion'),
                                    3 => __('Mars', 'life-travel-excursion'),
                                    4 => __('Avril', 'life-travel-excursion'),
                                    5 => __('Mai', 'life-travel-excursion'),
                                    6 => __('Juin', 'life-travel-excursion'),
                                    7 => __('Juillet', 'life-travel-excursion'),
                                    8 => __('Août', 'life-travel-excursion'),
                                    9 => __('Septembre', 'life-travel-excursion'),
                                    10 => __('Octobre', 'life-travel-excursion'),
                                    11 => __('Novembre', 'life-travel-excursion'),
                                    12 => __('Décembre', 'life-travel-excursion')
                                );
                                
                                foreach ($months as $num => $name) :
                                ?>
                                <label class="life-travel-month-checkbox">
                                    <input type="checkbox" name="life_travel_high_season_months[]" value="<?php echo esc_attr($num); ?>" 
                                           <?php checked(in_array($num, $high_season_months)); ?>>
                                    <?php echo esc_html($name); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="life-travel-admin-field">
                            <label for="life_travel_high_season_markup">
                                <?php _e('Majoration haute saison (%)', 'life-travel-excursion'); ?>
                                <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Pourcentage d\'augmentation des prix pendant la haute saison.', 'life-travel-excursion'); ?>">?</span>
                            </label>
                            
                            <div class="life-travel-number-field">
                                <input type="number" name="life_travel_high_season_markup" id="life_travel_high_season_markup" 
                                       min="0" max="50" value="<?php echo esc_attr($high_season_markup); ?>">
                            </div>
                        </div>
                        
                        <div class="life-travel-admin-field life-travel-season-field">
                            <label>
                                <?php _e('Mois de basse saison', 'life-travel-excursion'); ?>
                                <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Sélectionnez les mois qui constituent la basse saison touristique.', 'life-travel-excursion'); ?>">?</span>
                            </label>
                            
                            <div class="life-travel-month-checkboxes">
                                <?php foreach ($months as $num => $name) : ?>
                                <label class="life-travel-month-checkbox">
                                    <input type="checkbox" name="life_travel_low_season_months[]" value="<?php echo esc_attr($num); ?>" 
                                           <?php checked(in_array($num, $low_season_months)); ?>>
                                    <?php echo esc_html($name); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="life-travel-admin-field">
                            <label for="life_travel_low_season_discount">
                                <?php _e('Remise basse saison (%)', 'life-travel-excursion'); ?>
                                <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Pourcentage de réduction des prix pendant la basse saison.', 'life-travel-excursion'); ?>">?</span>
                            </label>
                            
                            <div class="life-travel-number-field">
                                <input type="number" name="life_travel_low_season_discount" id="life_travel_low_season_discount" 
                                       min="0" max="50" value="<?php echo esc_attr($low_season_discount); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="life-travel-admin-submit">
                    <input type="submit" name="life_travel_save_excursion_settings" class="button button-primary" value="<?php esc_attr_e('Enregistrer les modifications', 'life-travel-excursion'); ?>">
                </div>
            </form>
            
            <div class="life-travel-admin-tips">
                <h4><?php _e('Conseils pour la gestion des excursions', 'life-travel-excursion'); ?></h4>
                <ul>
                    <li><?php _e('Adaptez vos tarifs saisonniers au contexte touristique local du Cameroun', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Ajustez les délais de réservation en fonction de la complexité logistique de chaque excursion', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Communiquez clairement votre politique d\'annulation aux clients', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Encouragez les réservations de groupe avec des remises adaptées', 'life-travel-excursion'); ?></li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Gestion de l'affichage des paramètres saisonniers
            $('input[name="life_travel_seasonal_rates_enabled"]').on('change', function() {
                var isEnabled = $(this).is(':checked');
                $('#life-travel-seasonal-settings').toggleClass('disabled', !isEnabled);
                $('#life-travel-seasonal-settings input').prop('disabled', !isEnabled);
            }).trigger('change');
            
            // Validation des sélections de mois (haute et basse saison ne doivent pas se chevaucher)
            $('.life-travel-month-checkbox input').on('change', function() {
                var isHighSeason = $(this).attr('name').includes('high_season');
                var month = $(this).val();
                
                // Désélectionner le même mois dans l'autre saison
                if (isHighSeason && $(this).is(':checked')) {
                    $('input[name="life_travel_low_season_months[]"][value="' + month + '"]').prop('checked', false);
                } else if (!isHighSeason && $(this).is(':checked')) {
                    $('input[name="life_travel_high_season_months[]"][value="' + month + '"]').prop('checked', false);
                }
            });
        });
        </script>
        <?php
    }
}
