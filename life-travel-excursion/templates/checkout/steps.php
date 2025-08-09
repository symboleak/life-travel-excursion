<?php
/**
 * Template pour les étapes visuelles du checkout
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

// Récupérer l'étape actuelle (peut être définie par d'autres modules)
$current_step = apply_filters('lte_checkout_current_step', 'information');

// Intégration avec le système de fidélité - obtenir les points disponibles
$loyalty_points = 0;
$points_value = get_option('lte_points_value', 100); // Défaut: 100 points = 1€
$max_discount_percent = get_option('lte_max_discount_percent', 20); // Max 20% de réduction

if (is_user_logged_in()) {
    $user_id = get_current_user_id();
    $loyalty_points = get_user_meta($user_id, '_lte_loyalty_points', true) ?: 0;
}

// Calculer la valeur potentielle de réduction
$cart_total = WC()->cart ? WC()->cart->get_total('edit') : 0;
$max_discount_amount = $cart_total * ($max_discount_percent / 100);
$points_value_currency = $points_value > 0 ? $loyalty_points / $points_value : 0;
$available_discount = min($points_value_currency, $max_discount_amount);
?>

<div class="lte-checkout-steps">
    <ul>
        <li class="step <?php echo $current_step === 'information' ? 'active' : ($current_step === 'excursion' || $current_step === 'payment' || $current_step === 'confirmation' ? 'completed' : ''); ?>" data-step="information">
            <span class="step-number">1</span>
            <span class="step-title"><?php esc_html_e('Informations', 'life-travel-excursion'); ?></span>
        </li>
        <li class="step <?php echo $current_step === 'excursion' ? 'active' : ($current_step === 'payment' || $current_step === 'confirmation' ? 'completed' : ''); ?>" data-step="excursion">
            <span class="step-number">2</span>
            <span class="step-title"><?php esc_html_e('Excursion', 'life-travel-excursion'); ?></span>
        </li>
        <li class="step <?php echo $current_step === 'payment' ? 'active' : ($current_step === 'confirmation' ? 'completed' : ''); ?>" data-step="payment">
            <span class="step-number">3</span>
            <span class="step-title"><?php esc_html_e('Paiement', 'life-travel-excursion'); ?></span>
        </li>
        <li class="step <?php echo $current_step === 'confirmation' ? 'active' : ''; ?>" data-step="confirmation">
            <span class="step-number">4</span>
            <span class="step-title"><?php esc_html_e('Confirmation', 'life-travel-excursion'); ?></span>
        </li>
    </ul>
</div>

<?php
// Afficher la notification de points de fidélité si l'utilisateur a des points
if ($loyalty_points > 0 && $available_discount > 0) {
    ?>
    <div class="lte-loyalty-checkout-notice">
        <p>
            <?php 
            /* translators: %1$d: points, %2$s: formatted amount */
            printf(
                esc_html__('Vous avez %1$d points de fidélité disponibles (valeur: %2$s). Vous pourrez les utiliser à l\'étape de paiement.', 'life-travel-excursion'),
                $loyalty_points,
                wc_price($available_discount)
            ); 
            ?>
        </p>
    </div>
    <?php
}

// Vérifier s'il y a un panier abandonné à restaurer
$abandoned_cart = apply_filters('lte_abandoned_cart_exists', false);

if ($abandoned_cart && !isset($_COOKIE['lte_abandoned_cart_dismissed'])) {
    ?>
    <div class="lte-abandoned-cart-notice">
        <span class="close">&times;</span>
        <p>
            <?php esc_html_e('Nous avons trouvé votre panier abandonné. Souhaitez-vous le restaurer?', 'life-travel-excursion'); ?>
            <button class="lte-restore-cart-button button"><?php esc_html_e('Restaurer mon panier', 'life-travel-excursion'); ?></button>
        </p>
    </div>
    <?php
}
?>
