<?php
/**
 * Renderers de gestion des paiements pour Life Travel
 *
 * Ce fichier contient les méthodes de rendu pour la gestion unifiée des passerelles
 * de paiement dans l'interface administrateur
 *
 * @package Life Travel Excursion
 * @version 2.3.7
 */

defined('ABSPATH') || exit;

/**
 * Méthodes de rendu pour la gestion des paiements
 */
trait Life_Travel_Admin_Renderers_Payments {
    
    /**
     * Affiche l'interface de gestion des passerelles de paiement
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_payments_gateways($page_id, $section_id) {
        // Vérifier si WooCommerce est actif
        if (!class_exists('WooCommerce')) {
            echo '<div class="error"><p>' . esc_html__('WooCommerce doit être activé pour gérer les passerelles de paiement.', 'life-travel-excursion') . '</p></div>';
            return;
        }
        
        // Récupérer les passerelles de paiement disponibles
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        
        // Récupérer les paramètres personnalisés
        $momo_display_name = get_option('life_travel_momo_display_name', __('MTN Mobile Money', 'life-travel-excursion'));
        $om_display_name = get_option('life_travel_om_display_name', __('Orange Money', 'life-travel-excursion'));
        $payment_environment = get_option('life_travel_payment_environment', 'sandbox');
        
        // Traitement du formulaire
        if (isset($_POST['life_travel_save_payment_settings']) && check_admin_referer('life_travel_save_payment_settings')) {
            // Sauvegarder les noms d'affichage
            if (isset($_POST['life_travel_momo_display_name'])) {
                $momo_name = sanitize_text_field($_POST['life_travel_momo_display_name']);
                update_option('life_travel_momo_display_name', $momo_name);
                $momo_display_name = $momo_name;
            }
            
            if (isset($_POST['life_travel_om_display_name'])) {
                $om_name = sanitize_text_field($_POST['life_travel_om_display_name']);
                update_option('life_travel_om_display_name', $om_name);
                $om_display_name = $om_name;
            }
            
            // Sauvegarder l'environnement de paiement
            if (isset($_POST['life_travel_payment_environment'])) {
                $environment = sanitize_text_field($_POST['life_travel_payment_environment']);
                update_option('life_travel_payment_environment', $environment);
                $payment_environment = $environment;
            }
            
            echo '<div class="updated"><p>' . esc_html__('Paramètres de paiement enregistrés avec succès.', 'life-travel-excursion') . '</p></div>';
        }
        
        // Afficher l'interface utilisateur
        ?>
        <div class="life-travel-admin-section">
            <div class="life-travel-admin-intro">
                <h3><?php _e('Passerelles de paiement', 'life-travel-excursion'); ?></h3>
                <p><?php _e('Configurez et gérez vos passerelles de paiement de manière centralisée.', 'life-travel-excursion'); ?></p>
            </div>
            
            <div class="life-travel-gateways-overview">
                <h4><?php _e('Passerelles actives', 'life-travel-excursion'); ?></h4>
                
                <div class="life-travel-gateways-grid">
                    <?php
                    $gateway_found = false;
                    
                    // Afficher chaque passerelle disponible
                    if (!empty($available_gateways)) {
                        foreach ($available_gateways as $gateway_id => $gateway) {
                            $gateway_found = true;
                            $status_class = $gateway->is_available() ? 'active' : 'inactive';
                            $status_text = $gateway->is_available() ? __('Active', 'life-travel-excursion') : __('Inactive', 'life-travel-excursion');
                            
                            // Déterminer l'icône
                            $icon_class = 'dashicons-money-alt';
                            if (strpos($gateway_id, 'momo') !== false || strpos($gateway_id, 'mtn') !== false) {
                                $icon_class = 'dashicons-smartphone';
                            } elseif (strpos($gateway_id, 'orange') !== false) {
                                $icon_class = 'dashicons-smartphone';
                            } elseif (strpos($gateway_id, 'card') !== false || strpos($gateway_id, 'credit') !== false) {
                                $icon_class = 'dashicons-credit-card';
                            }
                            ?>
                            <div class="life-travel-gateway-card <?php echo esc_attr($status_class); ?>">
                                <div class="life-travel-gateway-icon">
                                    <span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
                                </div>
                                <div class="life-travel-gateway-details">
                                    <h5><?php echo esc_html($gateway->get_title()); ?></h5>
                                    <div class="life-travel-gateway-status"><?php echo esc_html($status_text); ?></div>
                                    <div class="life-travel-gateway-description"><?php echo wp_kses_post($gateway->get_description()); ?></div>
                                </div>
                                <div class="life-travel-gateway-actions">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $gateway_id)); ?>" class="button">
                                        <?php _e('Configurer', 'life-travel-excursion'); ?>
                                    </a>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    
                    if (!$gateway_found) {
                        echo '<p>' . esc_html__('Aucune passerelle de paiement n\'est actuellement configurée.', 'life-travel-excursion') . '</p>';
                    }
                    ?>
                </div>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('life_travel_save_payment_settings'); ?>
                
                <div class="life-travel-admin-field-group">
                    <h4><?php _e('Paramètres généraux de paiement', 'life-travel-excursion'); ?></h4>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_payment_environment">
                            <?php _e('Environnement', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Détermine si vous utilisez le mode test (sandbox) ou production.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-select-field">
                            <select name="life_travel_payment_environment" id="life_travel_payment_environment">
                                <option value="sandbox" <?php selected($payment_environment, 'sandbox'); ?>><?php _e('Sandbox (test)', 'life-travel-excursion'); ?></option>
                                <option value="production" <?php selected($payment_environment, 'production'); ?>><?php _e('Production (réel)', 'life-travel-excursion'); ?></option>
                            </select>
                        </div>
                        
                        <p class="description">
                            <?php _e('IMPORTANT: Utilisez toujours le mode Sandbox pour les tests avant de passer en production', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <h4><?php _e('Personnalisation des passerelles mobiles', 'life-travel-excursion'); ?></h4>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_momo_display_name">
                            <?php _e('Nom affiché pour MTN Mobile Money', 'life-travel-excursion'); ?>
                        </label>
                        
                        <div class="life-travel-text-field">
                            <input type="text" name="life_travel_momo_display_name" id="life_travel_momo_display_name" value="<?php echo esc_attr($momo_display_name); ?>">
                        </div>
                        
                        <p class="description">
                            <?php _e('Nom affiché aux clients lors du paiement', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_om_display_name">
                            <?php _e('Nom affiché pour Orange Money', 'life-travel-excursion'); ?>
                        </label>
                        
                        <div class="life-travel-text-field">
                            <input type="text" name="life_travel_om_display_name" id="life_travel_om_display_name" value="<?php echo esc_attr($om_display_name); ?>">
                        </div>
                        
                        <p class="description">
                            <?php _e('Nom affiché aux clients lors du paiement', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="life-travel-admin-submit">
                    <input type="submit" name="life_travel_save_payment_settings" class="button button-primary" value="<?php esc_attr_e('Enregistrer les modifications', 'life-travel-excursion'); ?>">
                </div>
            </form>
            
            <div class="life-travel-payment-test-tools">
                <h4><?php _e('Outils de test des paiements', 'life-travel-excursion'); ?></h4>
                
                <p><?php _e('Ces outils vous permettent de tester vos passerelles de paiement sans effectuer de transactions réelles.', 'life-travel-excursion'); ?></p>
                
                <div class="life-travel-test-buttons">
                    <button type="button" class="button life-travel-test-gateway" data-gateway="momo">
                        <?php _e('Tester MTN Mobile Money', 'life-travel-excursion'); ?>
                    </button>
                    
                    <button type="button" class="button life-travel-test-gateway" data-gateway="orange">
                        <?php _e('Tester Orange Money', 'life-travel-excursion'); ?>
                    </button>
                    
                    <button type="button" class="button life-travel-test-gateway" data-gateway="all">
                        <?php _e('Tester toutes les passerelles', 'life-travel-excursion'); ?>
                    </button>
                </div>
                
                <div class="life-travel-test-results"></div>
            </div>
            
            <div class="life-travel-admin-tips">
                <h4><?php _e('Conseils de sécurité pour les paiements', 'life-travel-excursion'); ?></h4>
                <ul>
                    <li><?php _e('Ne partagez jamais vos clés d\'API de production, utilisez des variables d\'environnement', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Effectuez régulièrement des transactions de test pour vérifier le bon fonctionnement', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Conservez une sauvegarde sécurisée de vos identifiants de paiement', 'life-travel-excursion'); ?></li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Tests des passerelles de paiement
            $('.life-travel-test-gateway').on('click', function() {
                var gateway = $(this).data('gateway');
                var $button = $(this);
                var $results = $('.life-travel-test-results');
                
                $button.prop('disabled', true);
                $results.html('<div class="life-travel-test-loading"><?php _e('Test en cours...', 'life-travel-excursion'); ?></div>');
                
                // Effectuer le test via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'life_travel_test_payment_gateway',
                        gateway: gateway,
                        nonce: '<?php echo wp_create_nonce('life_travel_test_payment_gateway'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $results.html('<div class="life-travel-test-success">' + response.data.message + '</div>');
                        } else {
                            $results.html('<div class="life-travel-test-error">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        $results.html('<div class="life-travel-test-error"><?php _e('Erreur lors du test. Vérifiez la connexion réseau.', 'life-travel-excursion'); ?></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Affiche l'interface de suivi de l'état des paiements
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_payments_status($page_id, $section_id) {
        // Vérifier si WooCommerce est actif
        if (!class_exists('WooCommerce')) {
            echo '<div class="error"><p>' . esc_html__('WooCommerce doit être activé pour suivre les paiements.', 'life-travel-excursion') . '</p></div>';
            return;
        }
        
        // Récupérer les transactions récentes
        $recent_orders = wc_get_orders(array(
            'limit' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ));
        
        // Calculer les statistiques de paiement
        $total_completed = 0;
        $total_failed = 0;
        $total_pending = 0;
        $payment_methods = array();
        
        $recent_orders_data = array();
        
        if (!empty($recent_orders)) {
            foreach ($recent_orders as $order) {
                $payment_method = $order->get_payment_method_title();
                $status = $order->get_status();
                
                // Ajouter aux compteurs
                if (in_array($status, array('completed', 'processing'))) {
                    $total_completed++;
                } elseif (in_array($status, array('failed', 'cancelled'))) {
                    $total_failed++;
                } elseif (in_array($status, array('pending', 'on-hold'))) {
                    $total_pending++;
                }
                
                // Compter les méthodes de paiement
                if (!empty($payment_method)) {
                    if (isset($payment_methods[$payment_method])) {
                        $payment_methods[$payment_method]++;
                    } else {
                        $payment_methods[$payment_method] = 1;
                    }
                }
                
                // Ajouter aux données de commandes récentes
                $recent_orders_data[] = array(
                    'id' => $order->get_id(),
                    'date' => $order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
                    'customer' => $order->get_formatted_billing_full_name(),
                    'total' => $order->get_formatted_order_total(),
                    'payment_method' => $payment_method,
                    'status' => $status,
                );
            }
        }
        
        // Afficher l'interface utilisateur
        ?>
        <div class="life-travel-admin-section">
            <div class="life-travel-admin-intro">
                <h3><?php _e('État des paiements', 'life-travel-excursion'); ?></h3>
                <p><?php _e('Suivez l\'état de vos paiements et transactions.', 'life-travel-excursion'); ?></p>
            </div>
            
            <div class="life-travel-payment-stats">
                <div class="life-travel-stats-card completed">
                    <div class="life-travel-stats-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="life-travel-stats-content">
                        <div class="life-travel-stats-value"><?php echo esc_html($total_completed); ?></div>
                        <div class="life-travel-stats-label"><?php _e('Paiements réussis', 'life-travel-excursion'); ?></div>
                    </div>
                </div>
                
                <div class="life-travel-stats-card pending">
                    <div class="life-travel-stats-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="life-travel-stats-content">
                        <div class="life-travel-stats-value"><?php echo esc_html($total_pending); ?></div>
                        <div class="life-travel-stats-label"><?php _e('Paiements en attente', 'life-travel-excursion'); ?></div>
                    </div>
                </div>
                
                <div class="life-travel-stats-card failed">
                    <div class="life-travel-stats-icon">
                        <span class="dashicons dashicons-no-alt"></span>
                    </div>
                    <div class="life-travel-stats-content">
                        <div class="life-travel-stats-value"><?php echo esc_html($total_failed); ?></div>
                        <div class="life-travel-stats-label"><?php _e('Paiements échoués', 'life-travel-excursion'); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="life-travel-payment-methods">
                <h4><?php _e('Utilisation des méthodes de paiement', 'life-travel-excursion'); ?></h4>
                
                <?php if (!empty($payment_methods)) : ?>
                    <div class="life-travel-payment-methods-chart">
                        <canvas id="life-travel-payment-methods-chart"></canvas>
                    </div>
                <?php else : ?>
                    <p><?php _e('Aucune donnée de paiement disponible.', 'life-travel-excursion'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="life-travel-recent-transactions">
                <h4><?php _e('Transactions récentes', 'life-travel-excursion'); ?></h4>
                
                <?php if (!empty($recent_orders_data)) : ?>
                    <table class="life-travel-transactions-table">
                        <thead>
                            <tr>
                                <th><?php _e('Commande', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Date', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Client', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Montant', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Méthode', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Statut', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Actions', 'life-travel-excursion'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders_data as $order) : ?>
                                <tr class="status-<?php echo esc_attr($order['status']); ?>">
                                    <td>#<?php echo esc_html($order['id']); ?></td>
                                    <td><?php echo esc_html($order['date']); ?></td>
                                    <td><?php echo esc_html($order['customer']); ?></td>
                                    <td><?php echo wp_kses_post($order['total']); ?></td>
                                    <td><?php echo esc_html($order['payment_method']); ?></td>
                                    <td>
                                        <span class="life-travel-status-badge status-<?php echo esc_attr($order['status']); ?>">
                                            <?php echo esc_html(wc_get_order_status_name($order['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $order['id'] . '&action=edit')); ?>" class="button button-small">
                                            <?php _e('Voir', 'life-travel-excursion'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php _e('Aucune transaction récente.', 'life-travel-excursion'); ?></p>
                <?php endif; ?>
                
                <p class="life-travel-view-all">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>" class="button">
                        <?php _e('Voir toutes les commandes', 'life-travel-excursion'); ?>
                    </a>
                </p>
            </div>
        </div>
        
        <?php if (!empty($payment_methods)) : ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        jQuery(document).ready(function($) {
            // Préparer les données pour le graphique
            var labels = <?php echo json_encode(array_keys($payment_methods)); ?>;
            var data = <?php echo json_encode(array_values($payment_methods)); ?>;
            var backgroundColors = [
                '#4CAF50',
                '#FF9800',
                '#2196F3',
                '#9C27B0',
                '#607D8B'
            ];
            
            // Créer le graphique
            var ctx = document.getElementById('life-travel-payment-methods-chart').getContext('2d');
            var paymentMethodsChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: true,
                        text: '<?php _e('Répartition des méthodes de paiement', 'life-travel-excursion'); ?>'
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    }
                }
            });
        });
        </script>
        <?php endif; ?>
        <?php
    }
}
