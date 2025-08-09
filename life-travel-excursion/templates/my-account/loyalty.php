<?php
/**
 * Template pour l'onglet "Mes points de fidélité" dans la page Mon Compte
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

// Vérifier si l'utilisateur est connecté
if (!is_user_logged_in()) {
    echo '<p>' . esc_html__('Veuillez vous connecter pour voir vos points de fidélité.', 'life-travel-excursion') . '</p>';
    return;
}

// Calculer la valeur des points en monnaie
$points_value_currency = 0;
if ($points_value > 0) {
    $points_value_currency = $loyalty_points / $points_value;
}

// Formater le montant
$formatted_amount = wc_price($points_value_currency);
?>

<div class="lte-loyalty-wrapper">
    <!-- Résumé des points -->
    <div class="lte-loyalty-summary">
        <h2><?php esc_html_e('Vos points de fidélité', 'life-travel-excursion'); ?></h2>
        
        <div class="lte-points-value">
            <?php echo esc_html($loyalty_points); ?>
        </div>
        
        <span class="lte-points-label">
            <?php esc_html_e('points accumulés', 'life-travel-excursion'); ?>
        </span>
        
        <div class="lte-points-info">
            <?php 
            /* translators: %s: formatted amount */
            printf(esc_html__('Valeur approximative de vos points: %s', 'life-travel-excursion'), $formatted_amount); 
            ?>
        </div>
        
        <div class="lte-points-exchange">
            <?php
            /* translators: %1$d: points, %2$s: formatted amount */
            printf(esc_html__('%1$d points = %2$s de réduction', 'life-travel-excursion'), 
                   $points_value, 
                   wc_price(1)); 
            ?>
            <br>
            <small>
                <?php 
                /* translators: %d: percentage */
                printf(esc_html__('Maximum %d%% de réduction par commande', 'life-travel-excursion'), 
                       $max_discount_percent); 
                ?>
            </small>
        </div>
        
        <p>
            <a href="#" class="lte-loyalty-rules-toggle">
                <?php esc_html_e('Afficher les règles', 'life-travel-excursion'); ?>
            </a>
        </p>
        
        <div class="lte-loyalty-rules" style="display: none;">
            <h4><?php esc_html_e('Comment gagner des points?', 'life-travel-excursion'); ?></h4>
            
            <ul>
                <li>
                    <?php
                    /* translators: %d: points */
                    printf(esc_html__('Réservation d\'excursion: %d points pour chaque tranche de 1000 FCFA', 'life-travel-excursion'),
                           get_option('lte_points_per_currency', 5));
                    ?>
                </li>
                <li>
                    <?php
                    /* translators: %d: points */
                    printf(esc_html__('Partage sur Facebook: jusqu\'à %d points/jour', 'life-travel-excursion'),
                           get_option('lte_points_facebook', 10));
                    ?>
                </li>
                <li>
                    <?php
                    /* translators: %d: points */
                    printf(esc_html__('Partage sur Twitter: jusqu\'à %d points/jour', 'life-travel-excursion'),
                           get_option('lte_points_twitter', 10));
                    ?>
                </li>
                <li>
                    <?php
                    /* translators: %d: points */
                    printf(esc_html__('Partage sur WhatsApp: jusqu\'à %d points/jour', 'life-travel-excursion'),
                           get_option('lte_points_whatsapp', 5));
                    ?>
                </li>
                <li>
                    <?php
                    /* translators: %d: points */
                    printf(esc_html__('Partage sur Instagram: jusqu\'à %d points/jour', 'life-travel-excursion'),
                           get_option('lte_points_instagram', 15));
                    ?>
                </li>
                <li>
                    <?php esc_html_e('Écrire un avis: 50 points par avis approuvé', 'life-travel-excursion'); ?>
                </li>
            </ul>
            
            <h4><?php esc_html_e('Comment utiliser vos points?', 'life-travel-excursion'); ?></h4>
            
            <ul>
                <li><?php esc_html_e('Lors du paiement, vous pourrez choisir d\'utiliser vos points.', 'life-travel-excursion'); ?></li>
                <li><?php esc_html_e('La réduction sera automatiquement calculée.', 'life-travel-excursion'); ?></li>
                <li>
                    <?php
                    /* translators: %d: percentage */
                    printf(esc_html__('La réduction est limitée à %d%% du montant total.', 'life-travel-excursion'),
                           $max_discount_percent);
                    ?>
                </li>
                <li><?php esc_html_e('Les points ont une validité de 12 mois.', 'life-travel-excursion'); ?></li>
            </ul>
        </div>
    </div>
    
    <!-- Historique des points -->
    <div class="lte-loyalty-history">
        <h3><?php esc_html_e('Historique des points', 'life-travel-excursion'); ?></h3>
        
        <?php if (empty($loyalty_history)): ?>
            <p><?php esc_html_e('Aucun historique de points disponible.', 'life-travel-excursion'); ?></p>
        <?php else: ?>
            <table class="lte-loyalty-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'life-travel-excursion'); ?></th>
                        <th><?php esc_html_e('Description', 'life-travel-excursion'); ?></th>
                        <th><?php esc_html_e('Points', 'life-travel-excursion'); ?></th>
                        <th><?php esc_html_e('Solde', 'life-travel-excursion'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loyalty_history as $entry): ?>
                        <tr>
                            <td>
                                <?php 
                                echo esc_html(date_i18n(
                                    get_option('date_format') . ' ' . get_option('time_format'),
                                    strtotime($entry['date_created'])
                                )); 
                                ?>
                            </td>
                            <td><?php echo esc_html($entry['description']); ?></td>
                            <td>
                                <?php if ($entry['points'] > 0): ?>
                                    <span class="lte-points-earned">+<?php echo esc_html($entry['points']); ?></span>
                                <?php else: ?>
                                    <span class="lte-points-spent"><?php echo esc_html($entry['points']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($entry['balance']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (count($loyalty_history) >= 20): ?>
                <div class="lte-load-more-container">
                    <button class="button lte-load-more-history" data-page="1">
                        <?php esc_html_e('Charger plus', 'life-travel-excursion'); ?>
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Actions disponibles -->
    <div class="lte-loyalty-actions">
        <h3><?php esc_html_e('Gagnez plus de points', 'life-travel-excursion'); ?></h3>
        
        <div class="lte-actions-grid">
            <div class="lte-action-card">
                <h4><?php esc_html_e('Partager sur les réseaux sociaux', 'life-travel-excursion'); ?></h4>
                <p><?php esc_html_e('Partagez vos excursions préférées et gagnez des points!', 'life-travel-excursion'); ?></p>
                <a href="<?php echo esc_url(get_permalink(get_option('woocommerce_shop_page_id'))); ?>" class="button">
                    <?php esc_html_e('Voir les excursions', 'life-travel-excursion'); ?>
                </a>
            </div>
            
            <div class="lte-action-card">
                <h4><?php esc_html_e('Écrire un avis', 'life-travel-excursion'); ?></h4>
                <p><?php esc_html_e('Partagez votre expérience et gagnez 50 points!', 'life-travel-excursion'); ?></p>
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="button">
                    <?php esc_html_e('Mes commandes', 'life-travel-excursion'); ?>
                </a>
            </div>
            
            <div class="lte-action-card">
                <h4><?php esc_html_e('Réserver une nouvelle excursion', 'life-travel-excursion'); ?></h4>
                <p><?php esc_html_e('Gagnez 5 points pour chaque 1000 FCFA dépensés!', 'life-travel-excursion'); ?></p>
                <a href="<?php echo esc_url(get_permalink(get_option('woocommerce_shop_page_id'))); ?>" class="button">
                    <?php esc_html_e('Découvrir nos excursions', 'life-travel-excursion'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <!-- FAQ -->
    <div class="lte-help-section">
        <h3><?php esc_html_e('Questions fréquentes sur les points', 'life-travel-excursion'); ?></h3>
        
        <div class="lte-faq-item">
            <div class="lte-faq-question">
                <?php esc_html_e('Quand mes points expirent-ils?', 'life-travel-excursion'); ?>
            </div>
            <div class="lte-faq-answer">
                <?php esc_html_e('Vos points sont valables pendant 12 mois à compter de la date à laquelle vous les avez gagnés.', 'life-travel-excursion'); ?>
            </div>
        </div>
        
        <div class="lte-faq-item">
            <div class="lte-faq-question">
                <?php esc_html_e('Comment puis-je utiliser mes points?', 'life-travel-excursion'); ?>
            </div>
            <div class="lte-faq-answer">
                <?php esc_html_e('Lors du paiement, vous verrez une option pour utiliser vos points de fidélité. Sélectionnez simplement le nombre de points que vous souhaitez utiliser.', 'life-travel-excursion'); ?>
            </div>
        </div>
        
        <div class="lte-faq-item">
            <div class="lte-faq-question">
                <?php esc_html_e('Pourquoi n\'ai-je pas reçu mes points pour un partage social?', 'life-travel-excursion'); ?>
            </div>
            <div class="lte-faq-answer">
                <?php esc_html_e('Vous pouvez gagner des points pour un maximum de 3 partages par jour. Si vous avez déjà atteint cette limite, vous devrez attendre le lendemain pour gagner plus de points.', 'life-travel-excursion'); ?>
            </div>
        </div>
    </div>
</div>
