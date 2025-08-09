<?php
/**
 * Template pour le tableau de bord de fidélité dans l'espace client
 *
 * Ce template affiche l'historique des points, le solde actuel, et les opportunités
 * pour gagner plus de points.
 *
 * @package Life_Travel
 * @subpackage Templates
 * @since 2.5.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();

if (!$user_id) {
    return;
}

// Récupérer les points actuels
$loyalty_points = apply_filters('lte_get_user_loyalty_points', 0, $user_id);

// Récupérer les paramètres de points
$points_value = get_option('lte_points_value', 100); // Par défaut 100 points = 1€
$points_value_currency = $points_value > 0 ? $loyalty_points / $points_value : 0;

// Récupérer les opportunités de gagner des points
$facebook_points = get_option('lte_points_facebook', 10);
$twitter_points = get_option('lte_points_twitter', 10);
$whatsapp_points = get_option('lte_points_whatsapp', 5);
$instagram_points = get_option('lte_points_instagram', 15);

// Récupérer l'historique des points
$notifications = get_user_meta($user_id, '_lte_loyalty_notifications', true);
if (!is_array($notifications)) {
    $notifications = array();
}

// Trier par date (la plus récente en premier)
usort($notifications, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

?>

<div class="lte-loyalty-dashboard">
    <div class="lte-loyalty-header">
        <h2><?php _e('Mon programme de fidélité', 'life-travel-excursion'); ?></h2>
        <p class="lte-loyalty-intro">
            <?php _e('Gagnez des points en réservant des excursions, laissant des avis, ou en partageant notre site sur les réseaux sociaux.', 'life-travel-excursion'); ?>
        </p>
    </div>
    
    <div class="lte-loyalty-balance-card">
        <div class="lte-balance-content">
            <div class="lte-balance-info">
                <span class="lte-balance-title"><?php _e('Votre solde actuel', 'life-travel-excursion'); ?></span>
                <span class="lte-balance-points"><?php echo esc_html($loyalty_points); ?></span>
                <span class="lte-balance-label"><?php _e('points', 'life-travel-excursion'); ?></span>
            </div>
            <div class="lte-balance-conversion">
                <p>
                    <?php 
                    printf(
                        __('Équivalent à environ <strong>%.2f €</strong> de réduction', 'life-travel-excursion'),
                        $points_value_currency
                    ); 
                    ?>
                </p>
                <p class="lte-balance-help">
                    <?php 
                    printf(
                        __('Vous pouvez utiliser vos points lors de votre prochaine réservation pour obtenir jusqu\'à %d%% de réduction.', 'life-travel-excursion'),
                        get_option('lte_max_points_discount_percent', 25)
                    ); 
                    ?>
                </p>
            </div>
        </div>
        <div class="lte-balance-actions">
            <a href="<?php echo esc_url(wc_get_endpoint_url('excursions', '', wc_get_page_permalink('myaccount'))); ?>" class="button">
                <?php _e('Voir les excursions', 'life-travel-excursion'); ?>
            </a>
        </div>
    </div>
    
    <div class="lte-loyalty-sections">
        <div class="lte-loyalty-section lte-earn-points">
            <h3><?php _e('Comment gagner plus de points', 'life-travel-excursion'); ?></h3>
            <div class="lte-earn-options">
                <div class="lte-earn-option">
                    <div class="lte-earn-icon">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                    <div class="lte-earn-details">
                        <h4><?php _e('Réserver une excursion', 'life-travel-excursion'); ?></h4>
                        <p><?php _e('Gagnez des points à chaque excursion réservée.', 'life-travel-excursion'); ?></p>
                    </div>
                </div>
                <div class="lte-earn-option">
                    <div class="lte-earn-icon">
                        <span class="dashicons dashicons-share"></span>
                    </div>
                    <div class="lte-earn-details">
                        <h4><?php _e('Partager sur les réseaux sociaux', 'life-travel-excursion'); ?></h4>
                        <p>
                            <?php 
                            printf(
                                __('Facebook: %d pts • Twitter: %d pts • Instagram: %d pts', 'life-travel-excursion'),
                                $facebook_points,
                                $twitter_points,
                                $instagram_points
                            ); 
                            ?>
                        </p>
                    </div>
                </div>
                <div class="lte-earn-option">
                    <div class="lte-earn-icon">
                        <span class="dashicons dashicons-star-filled"></span>
                    </div>
                    <div class="lte-earn-details">
                        <h4><?php _e('Laisser un avis', 'life-travel-excursion'); ?></h4>
                        <p><?php _e('Gagnez des points en partageant votre expérience.', 'life-travel-excursion'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="lte-loyalty-section lte-points-history">
            <h3><?php _e('Historique de vos points', 'life-travel-excursion'); ?></h3>
            <?php if (empty($notifications)) : ?>
                <p class="lte-no-history"><?php _e('Vous n\'avez pas encore d\'historique de points.', 'life-travel-excursion'); ?></p>
            <?php else : ?>
                <div class="lte-history-list">
                    <?php foreach ($notifications as $notification) : ?>
                        <div class="lte-history-item">
                            <div class="lte-history-date">
                                <?php echo date_i18n(get_option('date_format'), $notification['timestamp']); ?>
                            </div>
                            <div class="lte-history-details">
                                <span class="lte-history-points">+<?php echo esc_html($notification['points']); ?> <?php _e('points', 'life-travel-excursion'); ?></span>
                                <span class="lte-history-desc">
                                    <?php 
                                    printf(
                                        __('Commande #%d', 'life-travel-excursion'),
                                        $notification['order_id']
                                    ); 
                                    ?>
                                </span>
                            </div>
                            <?php if (!empty($notification['breakdown'])) : ?>
                                <div class="lte-history-breakdown">
                                    <button class="lte-toggle-breakdown" type="button">
                                        <?php _e('Détails', 'life-travel-excursion'); ?>
                                    </button>
                                    <div class="lte-breakdown-details" style="display: none;">
                                        <ul>
                                            <?php foreach ($notification['breakdown'] as $product_id => $details) : ?>
                                                <li>
                                                    <?php echo esc_html($details['name']); ?>: 
                                                    <strong>+<?php echo esc_html($details['points']); ?> <?php _e('points', 'life-travel-excursion'); ?></strong>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .lte-loyalty-dashboard {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }
    .lte-loyalty-header {
        margin-bottom: 25px;
    }
    .lte-loyalty-balance-card {
        background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
        color: white;
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .lte-balance-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .lte-balance-info {
        display: flex;
        flex-direction: column;
    }
    .lte-balance-title {
        font-size: 16px;
        opacity: 0.9;
        margin-bottom: 5px;
    }
    .lte-balance-points {
        font-size: 48px;
        font-weight: 700;
        line-height: 1;
    }
    .lte-balance-label {
        font-size: 16px;
        opacity: 0.9;
    }
    .lte-balance-conversion {
        background: rgba(255,255,255,0.15);
        padding: 10px 15px;
        border-radius: 5px;
        font-size: 14px;
    }
    .lte-balance-conversion p {
        margin: 5px 0;
    }
    .lte-balance-help {
        opacity: 0.9;
        font-size: 13px;
    }
    .lte-balance-actions {
        text-align: right;
    }
    .lte-balance-actions .button {
        background: white;
        color: #2271b1;
        border: none;
        font-weight: 500;
    }
    .lte-loyalty-sections {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }
    .lte-loyalty-section {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .lte-earn-options {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .lte-earn-option {
        display: flex;
        align-items: flex-start;
        padding: 10px;
        background: #f8f8f8;
        border-radius: 5px;
    }
    .lte-earn-icon {
        background: #e6f2f8;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
    }
    .lte-earn-icon .dashicons {
        color: #2271b1;
        font-size: 20px;
        width: 20px;
        height: 20px;
    }
    .lte-earn-details h4 {
        margin: 0 0 5px 0;
        font-size: 16px;
    }
    .lte-earn-details p {
        margin: 0;
        font-size: 14px;
        color: #666;
    }
    .lte-history-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .lte-history-item {
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    .lte-history-date {
        font-size: 12px;
        color: #666;
        margin-bottom: 5px;
    }
    .lte-history-details {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .lte-history-points {
        font-weight: 600;
        color: #2e7d32;
    }
    .lte-history-desc {
        font-size: 14px;
    }
    .lte-history-breakdown {
        margin-top: 5px;
    }
    .lte-toggle-breakdown {
        background: none;
        border: none;
        color: #2271b1;
        font-size: 13px;
        cursor: pointer;
        padding: 0;
        text-decoration: underline;
    }
    .lte-breakdown-details {
        background: #f8f8f8;
        padding: 10px;
        margin-top: 5px;
        border-radius: 5px;
    }
    .lte-breakdown-details ul {
        margin: 0;
        padding-left: 20px;
        font-size: 13px;
    }
    .lte-no-history {
        padding: 20px;
        background: #f8f8f8;
        border-radius: 5px;
        text-align: center;
        color: #666;
    }
    
    @media (max-width: 768px) {
        .lte-balance-content {
            flex-direction: column;
            align-items: flex-start;
        }
        .lte-balance-conversion {
            margin-top: 15px;
            width: 100%;
        }
        .lte-loyalty-sections {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
jQuery(document).ready(function($) {
    $('.lte-toggle-breakdown').on('click', function() {
        $(this).next('.lte-breakdown-details').slideToggle();
    });
});
</script>
