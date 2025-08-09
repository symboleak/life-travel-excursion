<?php
/**
 * Template for displaying "Mes excursions" in My Account.
 *
 * Ce template affiche un récapitulatif des commandes contenant des produits de type "excursion" pour le client connecté.
 *
 * @package Life_Travel_Excursion
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_user = wp_get_current_user();
$customer_orders = wc_get_orders( array(
    'customer' => $current_user->ID,
    'limit'    => -1,
) );
?>

<div class="my-account-excursions-container">
    <h3><?php _e( 'Mes excursions', 'life-travel-excursion' ); ?></h3>
    <?php if ( ! empty( $customer_orders ) ) : ?>
        <table class="shop_table my_account_orders">
            <thead>
                <tr>
                    <th><?php _e( 'Numéro de commande', 'life-travel-excursion' ); ?></th>
                    <th><?php _e( 'Date', 'life-travel-excursion' ); ?></th>
                    <th><?php _e( 'Total', 'life-travel-excursion' ); ?></th>
                    <th><?php _e( 'Statut', 'life-travel-excursion' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $customer_orders as $order ) : ?>
                    <?php
                    $has_excursion = false;
                    foreach ( $order->get_items() as $item ) {
                        $product = $item->get_product();
                        if ( $product && 'excursion' === $product->get_type() ) {
                            $has_excursion = true;
                            break;
                        }
                    }
                    if ( $has_excursion ) :
                    ?>
                    <tr>
                        <td><?php echo esc_html( $order->get_order_number() ); ?></td>
                        <td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
                        <td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                        <td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php _e( 'Aucune excursion trouvée.', 'life-travel-excursion' ); ?></p>
    <?php endif; ?>
</div>