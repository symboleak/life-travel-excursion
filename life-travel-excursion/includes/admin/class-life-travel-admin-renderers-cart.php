<?php
/**
 * Renderers de gestion des paniers et des commandes pour Life Travel
 *
 * Ce fichier contient les méthodes de rendu pour la gestion des paniers, 
 * y compris la récupération des paniers abandonnés et le suivi des commandes
 * dans l'interface administrateur unifiée
 *
 * @package Life Travel Excursion
 * @version 2.3.7
 */

defined('ABSPATH') || exit;

/**
 * Méthodes de rendu pour la gestion des paniers et commandes
 */
trait Life_Travel_Admin_Renderers_Cart {
    
    /**
     * Affiche l'interface de gestion des paniers abandonnés
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_cart_abandoned($page_id, $section_id) {
        // Récupérer les paramètres actuels
        $recovery_enabled = get_option('life_travel_abandoned_cart_recovery', 'on');
        $recovery_delay = get_option('life_travel_abandoned_cart_delay', 30);
        $recovery_emails = get_option('life_travel_abandoned_cart_emails', 3);
        $recovery_retry = get_option('life_travel_abandoned_cart_retry_delay', 24);
        $recovery_threshold = get_option('life_travel_abandoned_cart_threshold', 100);
        $recovery_offline_sync = get_option('life_travel_abandoned_cart_offline_sync', 'on');
        
        // Traitement du formulaire
        if (isset($_POST['life_travel_save_cart_settings']) && check_admin_referer('life_travel_save_cart_settings')) {
            $recovery_enabled = isset($_POST['life_travel_abandoned_cart_recovery']) ? 'on' : 'off';
            update_option('life_travel_abandoned_cart_recovery', $recovery_enabled);
            
            if (isset($_POST['life_travel_abandoned_cart_delay'])) {
                $delay = intval($_POST['life_travel_abandoned_cart_delay']);
                $delay = max(15, min(120, $delay)); // Limiter entre 15 et 120 minutes
                update_option('life_travel_abandoned_cart_delay', $delay);
                $recovery_delay = $delay;
            }
            
            if (isset($_POST['life_travel_abandoned_cart_emails'])) {
                $emails = intval($_POST['life_travel_abandoned_cart_emails']);
                $emails = max(1, min(5, $emails)); // Limiter entre 1 et 5 emails
                update_option('life_travel_abandoned_cart_emails', $emails);
                $recovery_emails = $emails;
            }
            
            if (isset($_POST['life_travel_abandoned_cart_retry_delay'])) {
                $retry = intval($_POST['life_travel_abandoned_cart_retry_delay']);
                $retry = max(12, min(72, $retry)); // Limiter entre 12 et 72 heures
                update_option('life_travel_abandoned_cart_retry_delay', $retry);
                $recovery_retry = $retry;
            }
            
            if (isset($_POST['life_travel_abandoned_cart_threshold'])) {
                $threshold = intval($_POST['life_travel_abandoned_cart_threshold']);
                $threshold = max(0, min(1000, $threshold)); // Limiter entre 0 et 1000 FCFA
                update_option('life_travel_abandoned_cart_threshold', $threshold);
                $recovery_threshold = $threshold;
            }
            
            $offline_sync = isset($_POST['life_travel_abandoned_cart_offline_sync']) ? 'on' : 'off';
            update_option('life_travel_abandoned_cart_offline_sync', $offline_sync);
            $recovery_offline_sync = $offline_sync;
            
            echo '<div class="updated"><p>' . esc_html__('Paramètres de récupération des paniers abandonnés enregistrés avec succès.', 'life-travel-excursion') . '</p></div>';
        }
        
        // Récupérer les statistiques des paniers abandonnés
        $abandoned_stats = $this->get_abandoned_cart_stats();
        
        // Afficher l'interface utilisateur
        ?>
        <div class="life-travel-admin-section">
            <div class="life-travel-admin-intro">
                <h3><?php _e('Gestion des paniers abandonnés', 'life-travel-excursion'); ?></h3>
                <p><?php _e('Configurez la récupération automatique des paniers abandonnés et suivez les performances.', 'life-travel-excursion'); ?></p>
            </div>
            
            <div class="life-travel-admin-stats">
                <div class="life-travel-stat-box">
                    <span class="life-travel-stat-number"><?php echo esc_html($abandoned_stats['total']); ?></span>
                    <span class="life-travel-stat-label"><?php _e('Paniers abandonnés (30j)', 'life-travel-excursion'); ?></span>
                </div>
                
                <div class="life-travel-stat-box">
                    <span class="life-travel-stat-number"><?php echo esc_html($abandoned_stats['recovered']); ?></span>
                    <span class="life-travel-stat-label"><?php _e('Paniers récupérés', 'life-travel-excursion'); ?></span>
                </div>
                
                <div class="life-travel-stat-box">
                    <span class="life-travel-stat-number"><?php echo esc_html($abandoned_stats['recovery_rate']); ?>%</span>
                    <span class="life-travel-stat-label"><?php _e('Taux de récupération', 'life-travel-excursion'); ?></span>
                </div>
                
                <div class="life-travel-stat-box">
                    <span class="life-travel-stat-number"><?php echo esc_html(number_format($abandoned_stats['revenue'], 0, ',', ' ')); ?> FCFA</span>
                    <span class="life-travel-stat-label"><?php _e('Revenus récupérés', 'life-travel-excursion'); ?></span>
                </div>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('life_travel_save_cart_settings'); ?>
                
                <div class="life-travel-admin-field-group">
                    <div class="life-travel-admin-field">
                        <label>
                            <?php _e('Récupération des paniers abandonnés', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Active le système de suivi et récupération des paniers abandonnés.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-toggle-field">
                            <label class="life-travel-toggle">
                                <input type="checkbox" name="life_travel_abandoned_cart_recovery" <?php checked($recovery_enabled, 'on'); ?>>
                                <span class="life-travel-toggle-slider"></span>
                            </label>
                            <span class="life-travel-toggle-label"><?php _e('Activer la récupération des paniers abandonnés', 'life-travel-excursion'); ?></span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Cette fonctionnalité utilise notre système robuste de synchronisation avec gestion des connexions intermittentes', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="life-travel-admin-field-group">
                    <h4><?php _e('Paramètres de récupération', 'life-travel-excursion'); ?></h4>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_abandoned_cart_delay">
                            <?php _e('Délai avant abandon (minutes)', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Temps d\'inactivité avant qu\'un panier soit considéré comme abandonné.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-number-field">
                            <input type="number" name="life_travel_abandoned_cart_delay" id="life_travel_abandoned_cart_delay" 
                                   min="15" max="120" value="<?php echo esc_attr($recovery_delay); ?>">
                        </div>
                        
                        <p class="description">
                            <?php _e('Valeur recommandée : 30 minutes', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_abandoned_cart_emails">
                            <?php _e('Nombre d\'emails de relance', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Nombre maximum d\'emails de rappel envoyés pour un panier abandonné.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-number-field">
                            <input type="number" name="life_travel_abandoned_cart_emails" id="life_travel_abandoned_cart_emails" 
                                   min="1" max="5" value="<?php echo esc_attr($recovery_emails); ?>">
                        </div>
                        
                        <p class="description">
                            <?php _e('Une valeur trop élevée peut être perçue comme du spam', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_abandoned_cart_retry_delay">
                            <?php _e('Délai entre relances (heures)', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Temps d\'attente entre deux emails de relance.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-number-field">
                            <input type="number" name="life_travel_abandoned_cart_retry_delay" id="life_travel_abandoned_cart_retry_delay" 
                                   min="12" max="72" value="<?php echo esc_attr($recovery_retry); ?>">
                        </div>
                        
                        <p class="description">
                            <?php _e('Valeur recommandée : 24 heures', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_abandoned_cart_threshold">
                            <?php _e('Seuil de valeur minimum (FCFA)', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Valeur minimale du panier pour déclencher la récupération. 0 = tous les paniers.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-number-field">
                            <input type="number" name="life_travel_abandoned_cart_threshold" id="life_travel_abandoned_cart_threshold" 
                                   min="0" step="100" value="<?php echo esc_attr($recovery_threshold); ?>">
                        </div>
                        
                        <p class="description">
                            <?php _e('Concentrez vos efforts sur les paniers de valeur significative', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="life-travel-admin-field-group">
                    <h4><?php _e('Sécurité et synchronisation', 'life-travel-excursion'); ?></h4>
                    
                    <div class="life-travel-admin-field">
                        <label>
                            <?php _e('Synchronisation hors ligne', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Permet de sauvegarder les données de panier localement pendant les pertes de connexion et de les synchroniser plus tard.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-toggle-field">
                            <label class="life-travel-toggle">
                                <input type="checkbox" name="life_travel_abandoned_cart_offline_sync" <?php checked($recovery_offline_sync, 'on'); ?>>
                                <span class="life-travel-toggle-slider"></span>
                            </label>
                            <span class="life-travel-toggle-label"><?php _e('Activer la synchronisation hors ligne', 'life-travel-excursion'); ?></span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Recommandé au Cameroun pour gérer les connexions instables', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-info-box">
                        <div class="life-travel-admin-info-icon">
                            <span class="dashicons dashicons-shield"></span>
                        </div>
                        <div class="life-travel-admin-info-content">
                            <h4><?php _e('Protection avancée des données', 'life-travel-excursion'); ?></h4>
                            <p><?php _e('Notre système de récupération de paniers utilise :', 'life-travel-excursion'); ?></p>
                            <ul>
                                <li><?php _e('Validation et assainissement complet des données', 'life-travel-excursion'); ?></li>
                                <li><?php _e('Protection contre les attaques CSRF via nonces', 'life-travel-excursion'); ?></li>
                                <li><?php _e('Validation JSON et sanitisation des entrées/sorties', 'life-travel-excursion'); ?></li>
                                <li><?php _e('Journalisation sécurisée des erreurs', 'life-travel-excursion'); ?></li>
                                <li><?php _e('Vérification des formats de données multiples', 'life-travel-excursion'); ?></li>
                                <li><?php _e('Validation des données spécifiques aux excursions', 'life-travel-excursion'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="life-travel-admin-submit">
                    <input type="submit" name="life_travel_save_cart_settings" class="button button-primary" value="<?php esc_attr_e('Enregistrer les modifications', 'life-travel-excursion'); ?>">
                </div>
            </form>
            
            <div class="life-travel-admin-tips">
                <h4><?php _e('Conseils pour optimiser la récupération des paniers', 'life-travel-excursion'); ?></h4>
                <ul>
                    <li><?php _e('Offrez un code promo ou une réduction dans vos emails de relance', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Personnalisez vos messages en fonction du contenu du panier', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Au Cameroun, la synchro hors ligne est essentielle pour récupérer les clients avec connexions instables', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Analysez régulièrement les statistiques pour ajuster votre stratégie', 'life-travel-excursion'); ?></li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Mise à jour dynamique des paramètres en fonction de l'activation/désactivation
            $('input[name="life_travel_abandoned_cart_recovery"]').on('change', function() {
                var isEnabled = $(this).is(':checked');
                $('.life-travel-admin-field-group:not(:first-child) input, .life-travel-admin-field-group:not(:first-child) select').prop('disabled', !isEnabled);
                $('.life-travel-admin-field-group:not(:first-child)').toggleClass('disabled', !isEnabled);
            }).trigger('change');
        });
        </script>
        <?php
    }
    
    /**
     * Récupère les statistiques des paniers abandonnés
     * 
     * @return array Tableau associatif des statistiques
     */
    private function get_abandoned_cart_stats() {
        global $wpdb;
        
        // Vérifier d'abord si les statistiques sont en cache
        $cached_stats = get_transient('life_travel_abandoned_cart_stats');
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        
        // Initialiser le tableau de statistiques
        $stats = array(
            'total' => 0,
            'recovered' => 0,
            'recovery_rate' => 0,
            'revenue' => 0,
            'average_value' => 0,
            'last_week' => array(
                'total' => 0,
                'recovered' => 0
            )
        );
        
        // 1. Total des paniers abandonnés
        $total_abandoned = $wpdb->get_var(
            "SELECT COUNT(*) 
            FROM {$wpdb->prefix}life_travel_abandoned_carts 
            WHERE cart_recovered = 0"
        );
        $stats['total'] = $total_abandoned ?: 0;
        
        // 2. Total des paniers récupérés
        $total_recovered = $wpdb->get_var(
            "SELECT COUNT(*) 
            FROM {$wpdb->prefix}life_travel_abandoned_carts 
            WHERE cart_recovered = 1"
        );
        $stats['recovered'] = $total_recovered ?: 0;
        
        // 3. Taux de récupération
        $total_carts = $total_abandoned + $total_recovered;
        $stats['recovery_rate'] = $total_carts > 0 ? round(($total_recovered / $total_carts) * 100) : 0;
        
        // 4. Chiffre d'affaires récupéré
        $revenue_query = 
            "SELECT SUM(ac.cart_value) as total_revenue 
            FROM {$wpdb->prefix}life_travel_abandoned_carts ac
            JOIN {$wpdb->posts} p ON ac.order_id = p.ID
            WHERE ac.cart_recovered = 1 
            AND p.post_status IN ('wc-processing', 'wc-completed')";  
        
        $revenue = $wpdb->get_var($revenue_query);
        $stats['revenue'] = $revenue ?: 0;
        
        // 5. Valeur moyenne des paniers récupérés
        $stats['average_value'] = $total_recovered > 0 ? round($revenue / $total_recovered) : 0;
        
        // 6. Statistiques de la dernière semaine
        $last_week_start = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        $last_week_abandoned = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->prefix}life_travel_abandoned_carts 
            WHERE cart_recovered = 0 
            AND created_at >= %s",
            $last_week_start
        ));
        
        $last_week_recovered = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->prefix}life_travel_abandoned_carts 
            WHERE cart_recovered = 1 
            AND recovered_at >= %s",
            $last_week_start
        ));
        
        $stats['last_week']['total'] = $last_week_abandoned ?: 0;
        $stats['last_week']['recovered'] = $last_week_recovered ?: 0;
        
        // 7. Optimisations spécifiques pour le contexte camerounais
        
        // Nombre de paniers abandonnés dus à des problèmes de connexion
        $network_issue_carts = $wpdb->get_var(
            "SELECT COUNT(*) 
            FROM {$wpdb->prefix}life_travel_abandoned_carts 
            WHERE cart_recovered = 0 
            AND meta_value LIKE '%connection_issue%'"
        );
        $stats['network_issues'] = $network_issue_carts ?: 0;
        
        // Nombre de paniers abandonnés dus à des problèmes de paiement
        $payment_issue_carts = $wpdb->get_var(
            "SELECT COUNT(*) 
            FROM {$wpdb->prefix}life_travel_abandoned_carts 
            WHERE cart_recovered = 0 
            AND meta_value LIKE '%payment_issue%'"
        );
        $stats['payment_issues'] = $payment_issue_carts ?: 0;
        
        // Méthodes de paiement préférées dans les paniers récupérés
        $payment_methods_query = 
            "SELECT meta_value, COUNT(*) as count 
            FROM {$wpdb->prefix}life_travel_abandoned_carts ac
            JOIN {$wpdb->postmeta} pm ON ac.order_id = pm.post_id 
            WHERE ac.cart_recovered = 1 
            AND pm.meta_key = '_payment_method_title'
            GROUP BY meta_value
            ORDER BY count DESC
            LIMIT 3";
        
        $payment_methods = $wpdb->get_results($payment_methods_query);
        $stats['preferred_payment_methods'] = [];
        
        if ($payment_methods) {
            foreach ($payment_methods as $method) {
                $stats['preferred_payment_methods'][] = [
                    'method' => $method->meta_value,
                    'count' => $method->count
                ];
            }
        }
        
        // Mettre en cache les résultats pour 1 heure
        set_transient('life_travel_abandoned_cart_stats', $stats, HOUR_IN_SECONDS);
        
        return $stats;
    }
}
