<?php
/**
 * Template pour l'onglet "Mes Excursions" dans la page Mon Compte
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

// Vérifier si l'utilisateur est connecté
if (!is_user_logged_in()) {
    echo '<p>' . esc_html__('Veuillez vous connecter pour voir vos excursions.', 'life-travel-excursion') . '</p>';
    return;
}

// Récupérer les commandes avec excursions
if (empty($customer_orders)) {
    echo '<p>' . esc_html__('Vous n\'avez pas encore réservé d\'excursion.', 'life-travel-excursion') . '</p>';
    
    // Ajouter un lien vers la boutique
    echo '<p><a href="' . esc_url(get_permalink(wc_get_page_id('shop'))) . '" class="button">';
    echo esc_html__('Découvrir nos excursions', 'life-travel-excursion');
    echo '</a></p>';
    return;
}
?>

<div class="lte-excursions-wrapper">
    <!-- Barre de filtres -->
    <div class="lte-excursions-filters">
        <div class="lte-filter-count">
            <?php 
            /* translators: %d: number of excursions */
            printf(esc_html__('Vous avez %d excursion(s)', 'life-travel-excursion'), count($customer_orders)); 
            ?>
            <span class="lte-excursion-count"><?php echo count($customer_orders); ?></span>
        </div>
        
        <div class="lte-filter-select">
            <label for="lte-excursion-filter"><?php esc_html_e('Filtrer par:', 'life-travel-excursion'); ?></label>
            <select id="lte-excursion-filter">
                <option value="all"><?php esc_html_e('Toutes les excursions', 'life-travel-excursion'); ?></option>
                <option value="upcoming"><?php esc_html_e('À venir', 'life-travel-excursion'); ?></option>
                <option value="completed"><?php esc_html_e('Terminées', 'life-travel-excursion'); ?></option>
                <option value="cancelled"><?php esc_html_e('Annulées', 'life-travel-excursion'); ?></option>
                <option value="processing"><?php esc_html_e('En traitement', 'life-travel-excursion'); ?></option>
            </select>
        </div>
    </div>
    
    <!-- Liste des excursions -->
    <div class="lte-excursions-list">
        <?php foreach ($customer_orders as $order_data): 
            $order = $order_data['order'];
            $excursion_date = $order_data['excursion_date'];
            $participants = $order_data['participants'];
            
            // Déterminer le statut de l'excursion
            $status = 'processing';
            $status_label = esc_html__('En traitement', 'life-travel-excursion');
            
            if ($order->has_status('cancelled')) {
                $status = 'cancelled';
                $status_label = esc_html__('Annulée', 'life-travel-excursion');
            } elseif ($order->has_status('completed')) {
                // Vérifier si l'excursion est passée
                if ($excursion_date && strtotime($excursion_date) < current_time('timestamp')) {
                    $status = 'completed';
                    $status_label = esc_html__('Terminée', 'life-travel-excursion');
                } else {
                    $status = 'upcoming';
                    $status_label = esc_html__('À venir', 'life-travel-excursion');
                }
            } elseif ($order->has_status('processing') || $order->has_status('on-hold')) {
                if ($excursion_date && strtotime($excursion_date) > current_time('timestamp')) {
                    $status = 'upcoming';
                    $status_label = esc_html__('À venir', 'life-travel-excursion');
                }
            }
            
            // Récupérer les informations du premier produit excursion
            $excursion_product = null;
            $excursion_name = '';
            $excursion_image = '';
            
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $terms = get_the_terms($product_id, 'product_cat');
                $is_excursion = false;
                
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        if ($term->slug === 'excursion') {
                            $is_excursion = true;
                            break;
                        }
                    }
                }
                
                // Vérifier aussi par méta
                if (!$is_excursion && get_post_meta($product_id, '_is_excursion', true) === 'yes') {
                    $is_excursion = true;
                }
                
                if ($is_excursion) {
                    $excursion_product = wc_get_product($product_id);
                    $excursion_name = $item->get_name();
                    $excursion_image = $excursion_product ? wp_get_attachment_image_url($excursion_product->get_image_id(), 'medium') : '';
                    break;
                }
            }
            
            if (!$excursion_name) {
                $excursion_name = esc_html__('Excursion', 'life-travel-excursion');
            }
            
            if (!$excursion_image) {
                $excursion_image = wc_placeholder_img_src('medium');
            }
        ?>
        
        <div class="lte-excursion-card" data-status="<?php echo esc_attr($status); ?>">
            <div class="lte-excursion-image" style="background-image: url('<?php echo esc_url($excursion_image); ?>');">
            </div>
            
            <div class="lte-excursion-details">
                <h3 class="lte-excursion-title"><?php echo esc_html($excursion_name); ?></h3>
                
                <span class="lte-excursion-status <?php echo esc_attr($status); ?>">
                    <?php echo esc_html($status_label); ?>
                </span>
                
                <div class="lte-excursion-meta">
                    <div class="lte-excursion-meta-item">
                        <span class="lte-excursion-meta-label"><?php esc_html_e('Commande #:', 'life-travel-excursion'); ?></span>
                        <?php echo esc_html($order->get_order_number()); ?>
                    </div>
                    
                    <?php if ($excursion_date): ?>
                    <div class="lte-excursion-meta-item">
                        <span class="lte-excursion-meta-label"><?php esc_html_e('Date:', 'life-travel-excursion'); ?></span>
                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($excursion_date))); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($participants): ?>
                    <div class="lte-excursion-meta-item">
                        <span class="lte-excursion-meta-label"><?php esc_html_e('Participants:', 'life-travel-excursion'); ?></span>
                        <?php echo esc_html($participants); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="lte-excursion-meta-item">
                        <span class="lte-excursion-meta-label"><?php esc_html_e('Total:', 'life-travel-excursion'); ?></span>
                        <?php echo wp_kses_post($order->get_formatted_order_total()); ?>
                    </div>
                </div>
                
                <div class="lte-excursion-actions">
                    <a href="<?php echo esc_url($order->get_view_order_url()); ?>" class="button">
                        <?php esc_html_e('Voir les détails', 'life-travel-excursion'); ?>
                    </a>
                    
                    <?php if ($status === 'upcoming'): ?>
                    <button class="button lte-excursion-toggle">
                        <?php esc_html_e('Voir les détails', 'life-travel-excursion'); ?>
                    </button>
                    <?php endif; ?>
                </div>
                
                <?php if ($status === 'upcoming'): ?>
                <div class="lte-excursion-extra-details" style="display: none;">
                    <h4><?php esc_html_e('Informations complémentaires', 'life-travel-excursion'); ?></h4>
                    
                    <p><?php esc_html_e('Voici quelques informations importantes pour votre excursion à venir:', 'life-travel-excursion'); ?></p>
                    
                    <ul>
                        <li><?php esc_html_e('Veuillez vous présenter 30 minutes avant le départ.', 'life-travel-excursion'); ?></li>
                        <li><?php esc_html_e('N\'oubliez pas votre pièce d\'identité.', 'life-travel-excursion'); ?></li>
                        <li><?php esc_html_e('Portez des vêtements confortables et adaptés à la météo.', 'life-travel-excursion'); ?></li>
                    </ul>
                    
                    <p>
                        <a href="#" class="button">
                            <?php esc_html_e('Télécharger le billet', 'life-travel-excursion'); ?>
                        </a>
                        
                        <?php if ($order->has_status('pending') || $order->has_status('on-hold')): ?>
                        <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="button">
                            <?php esc_html_e('Payer', 'life-travel-excursion'); ?>
                        </a>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="lte-help-section">
    <h3><?php esc_html_e('Besoin d\'aide?', 'life-travel-excursion'); ?></h3>
    
    <div class="lte-faq-item">
        <div class="lte-faq-question">
            <?php esc_html_e('Comment puis-je annuler ma réservation?', 'life-travel-excursion'); ?>
        </div>
        <div class="lte-faq-answer">
            <?php esc_html_e('Pour annuler votre réservation, veuillez nous contacter par téléphone ou par email au moins 48 heures avant la date de l\'excursion. Des frais d\'annulation peuvent s\'appliquer.', 'life-travel-excursion'); ?>
        </div>
    </div>
    
    <div class="lte-faq-item">
        <div class="lte-faq-question">
            <?php esc_html_e('Puis-je modifier la date de mon excursion?', 'life-travel-excursion'); ?>
        </div>
        <div class="lte-faq-answer">
            <?php esc_html_e('Oui, vous pouvez modifier la date de votre excursion jusqu\'à 72 heures avant le départ, sous réserve de disponibilité. Contactez-nous pour effectuer un changement.', 'life-travel-excursion'); ?>
        </div>
    </div>
    
    <div class="lte-contact-info">
        <p>
            <?php esc_html_e('Pour toute question, contactez-nous:', 'life-travel-excursion'); ?>
            <a href="mailto:contact@life-travel.org">contact@life-travel.org</a> |
            <a href="tel:+237612345678">+237 612 345 678</a>
        </p>
    </div>
</div>
