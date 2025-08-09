<?php
/**
 * Template for displaying the excursion booking form.
 *
 * Ce template peut être personnalisé en le copiant dans votre thème sous /woocommerce/excursion-booking-form.php.
 *
 * @package Life_Travel_Excursion
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $product;

$participant_limit = isset( $participant_limit ) ? $participant_limit : '';
$min_days_before    = isset( $min_days_before ) ? $min_days_before : '';
$is_fixed_date      = isset( $is_fixed_date ) ? $is_fixed_date : '';
$start_date         = isset( $start_date ) ? $start_date : '';
$end_date           = isset( $end_date ) ? $end_date : '';
$pricing_tiers      = isset( $pricing_tiers ) ? $pricing_tiers : '';
$extras_list        = isset( $extras_list ) ? $extras_list : '';
$activities_list    = isset( $activities_list ) ? $activities_list : '';

// Pour les excursions à date fixe, récupérer les horaires définis
$fixed_start = '';
$fixed_end   = '';
if ( 'yes' === $is_fixed_date ) {
    $fixed_start = get_post_meta( $product->get_id(), '_fixed_start_time', true );
    $fixed_end   = get_post_meta( $product->get_id(), '_fixed_end_time', true );
}
?>
<div class="excursion-booking-form" role="form" aria-labelledby="excursion-booking-heading">
    <h2 id="excursion-booking-heading"><?php _e( 'Réserver cette excursion', 'life-travel-excursion' ); ?></h2>
    
    <form id="excursion-booking-form" method="post" novalidate>
        <input type="hidden" id="excursion_id" name="excursion_id" value="<?php echo esc_attr( $product->get_id() ); ?>" />

        <!-- Section Participants -->
        <div class="booking-section">
            <label for="participants"><?php _e( 'Nombre de participants', 'life-travel-excursion' ); ?></label>
            <input type="number" id="participants" name="participants" value="1" min="1" max="<?php echo esc_attr( $participant_limit ); ?>" required />
        </div>

        <?php if ( 'yes' === $is_fixed_date ) : ?>
            <!-- Pour une excursion à date fixe -->
            <div class="booking-section">
                <label><?php _e( 'Date de l\'excursion', 'life-travel-excursion' ); ?></label>
                <p><?php echo esc_html( $start_date ); ?></p>
            </div>
            <div class="booking-section">
                <label><?php _e( 'Créneau horaire', 'life-travel-excursion' ); ?></label>
                <p><?php echo esc_html( $fixed_start . ' - ' . $fixed_end ); ?></p>
                <input type="hidden" id="start_time" name="start_time" value="<?php echo esc_attr( $fixed_start ); ?>" />
            </div>
        <?php else : ?>
            <!-- Pour une excursion personnalisable -->
            <div class="booking-section">
                <label for="start_date"><?php _e( 'Date de début', 'life-travel-excursion' ); ?></label>
                <input type="text" id="start_date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" readonly required />
            </div>
            <div class="booking-section">
                <label for="end_date"><?php _e( 'Date de fin', 'life-travel-excursion' ); ?></label>
                <input type="text" id="end_date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" readonly required />
            </div>
            <?php 
            // Pour une excursion d'une seule journée, permettre la sélection des horaires
            $max_duration_days = intval( get_post_meta( $product->get_id(), '_max_duration_days', true ) );
            if ( $max_duration_days === 1 ) : ?>
                <div class="booking-section">
                    <label for="start_time"><?php _e( 'Heure de début', 'life-travel-excursion' ); ?></label>
                    <input type="time" id="start_time" name="start_time" required />
                </div>
                <div class="booking-section">
                    <label for="end_time"><?php _e( 'Heure de fin', 'life-travel-excursion' ); ?></label>
                    <input type="time" id="end_time" name="end_time" required />
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Section Extras -->
        <?php if ( ! empty( $extras_list ) && trim( $extras_list ) !== '' ) : 
            $extras_array = explode( "\n", $extras_list );
        ?>
        <div class="booking-section extras-section" aria-label="<?php _e( 'Options supplémentaires', 'life-travel-excursion' ); ?>">
            <h3><?php _e( 'Extras', 'life-travel-excursion' ); ?></h3>
            <?php foreach ( $extras_array as $extra_line ) : 
                $extra_parts = explode( '|', $extra_line );
                if ( count( $extra_parts ) !== 4 ) continue;
                list( $extra_name, $extra_price, $extra_type, $extra_multiplier ) = array_map( 'trim', $extra_parts );
                $extra_key = sanitize_title( $extra_name );
            ?>
            <div class="extra-item">
                <label>
                    <input type="checkbox" data-key="<?php echo esc_attr( $extra_key ); ?>" data-type="<?php echo esc_attr( $extra_type ); ?>" name="extras[<?php echo esc_attr( $extra_key ); ?>]" />
                    <?php echo esc_html( $extra_name . ' (' . wc_price( $extra_price ) . ')' ); ?>
                </label>
                <?php if ( strtolower( $extra_type ) === 'quantite' || strtolower( $extra_type ) === 'quantité' ) : ?>
                    <div class="extra-quantity" style="display:none;">
                        <input type="number" class="extra-quantity-input" value="1" min="1" />
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Section Activités -->
        <?php if ( ! empty( $activities_list ) && trim( $activities_list ) !== '' ) : 
            $activities_array = explode( "\n", $activities_list );
        ?>
        <div class="booking-section activities-section" aria-label="<?php _e( 'Choix d’activités', 'life-travel-excursion' ); ?>">
            <h3><?php _e( 'Activités', 'life-travel-excursion' ); ?></h3>
            <?php foreach ( $activities_array as $activity_line ) : 
                $activity_parts = explode( '|', $activity_line );
                if ( count( $activity_parts ) !== 3 ) continue;
                list( $activity_name, $activity_price, $max_duration ) = array_map( 'trim', $activity_parts );
                $activity_key = sanitize_title( $activity_name );
            ?>
            <div class="activity-item">
                <label>
                    <?php echo esc_html( $activity_name . ' (' . wc_price( $activity_price ) . ') ' . __( 'Durée max :', 'life-travel-excursion' ) . ' ' . $max_duration . ' ' . __( 'jour(s)', 'life-travel-excursion' ) ); ?>
                </label>
                <input type="number" name="activities[<?php echo esc_attr( $activity_key ); ?>]" value="0" min="0" max="<?php echo esc_attr( $max_duration ); ?>" />
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Section Tarification -->
        <div class="booking-section pricing-section" aria-live="polite">
            <h3><?php _e( 'Tarification', 'life-travel-excursion' ); ?></h3>
            <div class="pricing-summary">
                <p><strong><?php _e( 'Prix total : ', 'life-travel-excursion' ); ?><span id="total_price" style="font-size:1.5em; font-weight:bold;"></span></strong></p>
                <button type="button" id="toggle_pricing_details"><?php _e( 'Voir le détail', 'life-travel-excursion' ); ?></button>
            </div>
            <div id="pricing_details" style="display:none;">
                <p><?php _e( 'Prix par personne : ', 'life-travel-excursion' ); ?><span id="price_per_person"></span></p>
                <?php if ( ! empty( $extras_list ) && trim( $extras_list ) !== '' ) : ?>
                    <p><?php _e( 'Prix des extras : ', 'life-travel-excursion' ); ?><span id="extras_price"></span></p>
                <?php endif; ?>
                <?php if ( ! empty( $activities_list ) && trim( $activities_list ) !== '' ) : ?>
                    <p><?php _e( 'Prix des activités : ', 'life-travel-excursion' ); ?><span id="activities_price"></span></p>
                <?php endif; ?>
                <p><?php _e( 'Participants : ', 'life-travel-excursion' ); ?><span id="participant_count"></span></p>
                <p><?php _e( 'Nombre de jours : ', 'life-travel-excursion' ); ?><span id="day_count"></span></p>
                <!-- Indicateur d'unités supplémentaires déclenchées -->
                <p id="additional_units_info" style="display:none; color:#d9534f;"></p>
            </div>
            <!-- Spinner de chargement -->
            <div id="price_spinner" style="display:none;">
                <svg width="24" height="24" viewBox="0 0 50 50">
                  <circle cx="25" cy="25" r="20" fill="none" stroke="#0073aa" stroke-width="5" stroke-linecap="round">
                    <animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite" />
                  </circle>
                </svg>
            </div>
        </div>

        <!-- Section d'ajout au panier -->
        <div class="booking-section add-to-cart-section">
            <button type="button" id="add-to-cart-button" class="button"><?php _e( 'Ajouter au panier', 'life-travel-excursion' ); ?></button>
            <!-- Zone d'affichage des erreurs inline -->
            <div id="booking_error" style="display:none; color:#d9534f; margin-top:10px;"></div>
        </div>

        <!-- Section de partage social -->
        <div class="booking-section social-share-buttons">
            <h3><?php _e( 'Partager cette excursion', 'life-travel-excursion' ); ?></h3>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode( get_permalink() ); ?>" target="_blank"><?php _e( 'Facebook', 'life-travel-excursion' ); ?></a>
            <a href="https://www.instagram.com/" target="_blank"><?php _e( 'Instagram', 'life-travel-excursion' ); ?></a>
        </div>

        <!-- Section des points de fidélité (optionnelle) -->
        <div class="booking-section reward-points">
            <?php echo __( 'Vous avez accumulé X points. Utilisez vos points pour obtenir des réductions jusqu\'à 25%.', 'life-travel-excursion' ); ?>
        </div>
    </form>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // Basculer l'affichage des détails tarifaires
    document.getElementById('toggle_pricing_details').addEventListener('click', function(){
        var details = document.getElementById('pricing_details');
        details.style.display = (details.style.display === 'none' || details.style.display === '') ? 'block' : 'none';
    });
});
</script>