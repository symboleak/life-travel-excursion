<?php
/**
 * Deuxième partie de l'onglet des paramètres de types d'excursions
 * Conçu avec la même robustesse que la méthode sync_abandoned_cart
 * 
 * @package Life_Travel_Excursion
 * @since 2.0.0
 */

// Sortie directe interdite
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Configuration des excursions de groupe -->
<div class="life-travel-admin-card">
    <h3 class="life-travel-card-header">
        <span class="dashicons dashicons-groups"></span> 
        <?php _e('Excursions de groupe', 'life-travel-excursion'); ?>
    </h3>
    <div class="life-travel-card-body">
        <div class="life-travel-form-field">
            <label class="life-travel-toggle-switch">
                <input type="checkbox" name="group_excursion_enabled" value="yes" 
                    <?php checked($options['group_excursion_enabled'], 'yes'); ?>>
                <span class="life-travel-toggle-slider"></span>
                <?php _e('Activer les excursions de groupe', 'life-travel-excursion'); ?>
            </label>
            <p class="description">
                <?php _e('Les excursions de groupe permettent à plusieurs clients de réserver ensemble la même excursion à date fixe.', 'life-travel-excursion'); ?>
            </p>
        </div>
        
        <div id="group_excursion_options">
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="group_min_participants">
                        <?php _e('Nombre minimum de participants', 'life-travel-excursion'); ?>
                    </label>
                    <input type="number" id="group_min_participants" name="group_min_participants" 
                           value="<?php echo intval($options['group_min_participants']); ?>" min="1" max="100">
                </div>
                
                <div class="life-travel-form-field">
                    <label for="group_max_participants">
                        <?php _e('Nombre maximum de participants', 'life-travel-excursion'); ?>
                    </label>
                    <input type="number" id="group_max_participants" name="group_max_participants" 
                           value="<?php echo intval($options['group_max_participants']); ?>" min="1" max="200">
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <label for="group_default_price">
                    <?php _e('Prix par défaut (par personne)', 'life-travel-excursion'); ?>
                </label>
                <div class="life-travel-input-group">
                    <input type="number" id="group_default_price" name="group_default_price" 
                           value="<?php echo floatval($options['group_default_price']); ?>" min="0" step="100">
                    <span class="life-travel-input-suffix">XAF</span>
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="group_availability_calendar" value="yes" 
                        <?php checked($options['group_availability_calendar'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Afficher le calendrier de disponibilité', 'life-travel-excursion'); ?>
                </label>
            </div>
            
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="group_booking_lead_time">
                        <?php _e('Délai minimum de réservation', 'life-travel-excursion'); ?>
                    </label>
                    <div class="life-travel-input-group">
                        <input type="number" id="group_booking_lead_time" name="group_booking_lead_time" 
                               value="<?php echo intval($options['group_booking_lead_time']); ?>" min="0" max="168">
                        <span class="life-travel-input-suffix">
                            <?php _e('heures', 'life-travel-excursion'); ?>
                        </span>
                    </div>
                    <p class="description">
                        <?php _e('Temps minimum requis avant le début de l\'excursion pour pouvoir réserver.', 'life-travel-excursion'); ?>
                    </p>
                </div>
                
                <div class="life-travel-form-field">
                    <label for="group_cancellation_period">
                        <?php _e('Période d\'annulation', 'life-travel-excursion'); ?>
                    </label>
                    <div class="life-travel-input-group">
                        <input type="number" id="group_cancellation_period" name="group_cancellation_period" 
                               value="<?php echo intval($options['group_cancellation_period']); ?>" min="0" max="168">
                        <span class="life-travel-input-suffix">
                            <?php _e('heures', 'life-travel-excursion'); ?>
                        </span>
                    </div>
                    <p class="description">
                        <?php _e('Temps minimum requis avant le début de l\'excursion pour pouvoir annuler avec remboursement.', 'life-travel-excursion'); ?>
                    </p>
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="group_enable_discount" value="yes" 
                        <?php checked($options['group_enable_discount'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Activer les remises pour grands groupes', 'life-travel-excursion'); ?>
                </label>
            </div>
            
            <div id="group_discount_options" class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="group_discount_threshold">
                        <?php _e('Seuil de remise', 'life-travel-excursion'); ?>
                    </label>
                    <div class="life-travel-input-group">
                        <input type="number" id="group_discount_threshold" name="group_discount_threshold" 
                               value="<?php echo intval($options['group_discount_threshold']); ?>" min="2" max="100">
                        <span class="life-travel-input-suffix">
                            <?php _e('participants', 'life-travel-excursion'); ?>
                        </span>
                    </div>
                    <p class="description">
                        <?php _e('Nombre de participants à partir duquel la remise s\'applique.', 'life-travel-excursion'); ?>
                    </p>
                </div>
                
                <div class="life-travel-form-field">
                    <label for="group_discount_rate">
                        <?php _e('Taux de remise', 'life-travel-excursion'); ?>
                    </label>
                    <div class="life-travel-input-group">
                        <input type="number" id="group_discount_rate" name="group_discount_rate" 
                               value="<?php echo intval($options['group_discount_rate']); ?>" min="1" max="50">
                        <span class="life-travel-input-suffix">%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Configuration des excursions privées -->
<div class="life-travel-admin-card">
    <h3 class="life-travel-card-header">
        <span class="dashicons dashicons-star-filled"></span> 
        <?php _e('Excursions privées', 'life-travel-excursion'); ?>
    </h3>
    <div class="life-travel-card-body">
        <div class="life-travel-form-field">
            <label class="life-travel-toggle-switch">
                <input type="checkbox" name="private_excursion_enabled" value="yes" 
                    <?php checked($options['private_excursion_enabled'], 'yes'); ?>>
                <span class="life-travel-toggle-slider"></span>
                <?php _e('Activer les excursions privées', 'life-travel-excursion'); ?>
            </label>
            <p class="description">
                <?php _e('Les excursions privées sont réservées exclusivement pour un client et son groupe.', 'life-travel-excursion'); ?>
            </p>
        </div>
        
        <div id="private_excursion_options">
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="private_min_participants">
                        <?php _e('Nombre minimum de participants', 'life-travel-excursion'); ?>
                    </label>
                    <input type="number" id="private_min_participants" name="private_min_participants" 
                           value="<?php echo intval($options['private_min_participants']); ?>" min="1" max="50">
                </div>
                
                <div class="life-travel-form-field">
                    <label for="private_max_participants">
                        <?php _e('Nombre maximum de participants', 'life-travel-excursion'); ?>
                    </label>
                    <input type="number" id="private_max_participants" name="private_max_participants" 
                           value="<?php echo intval($options['private_max_participants']); ?>" min="1" max="100">
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <label for="private_default_price">
                    <?php _e('Prix par défaut (montant total)', 'life-travel-excursion'); ?>
                </label>
                <div class="life-travel-input-group">
                    <input type="number" id="private_default_price" name="private_default_price" 
                           value="<?php echo floatval($options['private_default_price']); ?>" min="0" step="1000">
                    <span class="life-travel-input-suffix">XAF</span>
                </div>
                <p class="description">
                    <?php _e('Prix par défaut pour une excursion privée (non par personne).', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label class="life-travel-toggle-switch">
                        <input type="checkbox" name="private_availability_calendar" value="yes" 
                            <?php checked($options['private_availability_calendar'], 'yes'); ?>>
                        <span class="life-travel-toggle-slider"></span>
                        <?php _e('Afficher le calendrier de disponibilité', 'life-travel-excursion'); ?>
                    </label>
                </div>
                
                <div class="life-travel-form-field">
                    <label class="life-travel-toggle-switch">
                        <input type="checkbox" name="private_vehicle_options" value="yes" 
                            <?php checked($options['private_vehicle_options'], 'yes'); ?>>
                        <span class="life-travel-toggle-slider"></span>
                        <?php _e('Proposer des options de véhicule', 'life-travel-excursion'); ?>
                    </label>
                </div>
            </div>
            
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="private_booking_lead_time">
                        <?php _e('Délai minimum de réservation', 'life-travel-excursion'); ?>
                    </label>
                    <div class="life-travel-input-group">
                        <input type="number" id="private_booking_lead_time" name="private_booking_lead_time" 
                               value="<?php echo intval($options['private_booking_lead_time']); ?>" min="0" max="168">
                        <span class="life-travel-input-suffix">
                            <?php _e('heures', 'life-travel-excursion'); ?>
                        </span>
                    </div>
                </div>
                
                <div class="life-travel-form-field">
                    <label for="private_cancellation_period">
                        <?php _e('Période d\'annulation', 'life-travel-excursion'); ?>
                    </label>
                    <div class="life-travel-input-group">
                        <input type="number" id="private_cancellation_period" name="private_cancellation_period" 
                               value="<?php echo intval($options['private_cancellation_period']); ?>" min="0" max="168">
                        <span class="life-travel-input-suffix">
                            <?php _e('heures', 'life-travel-excursion'); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="private_enable_pricing_tiers" value="yes" 
                        <?php checked($options['private_enable_pricing_tiers'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Activer les niveaux de prix', 'life-travel-excursion'); ?>
                </label>
                <p class="description">
                    <?php _e('Définir différents prix selon le nombre de participants.', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div id="private_pricing_tiers" class="life-travel-repeater">
                <div class="life-travel-repeater-items">
                    <?php 
                    // Afficher les niveaux de prix existants
                    if (!empty($options['private_pricing_tiers'])) :
                        foreach ($options['private_pricing_tiers'] as $tier) : 
                    ?>
                        <div class="life-travel-repeater-item">
                            <div class="life-travel-repeater-item-header">
                                <span class="life-travel-repeater-title">
                                    <?php 
                                    echo sprintf(
                                        __('%d à %d participants: %s XAF', 'life-travel-excursion'),
                                        $tier['min'],
                                        $tier['max'],
                                        number_format($tier['price'], 0, $options['price_decimal_separator'], $options['price_thousand_separator'])
                                    ); 
                                    ?>
                                </span>
                                <button type="button" class="life-travel-repeater-remove button">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            <div class="life-travel-repeater-item-content">
                                <div class="life-travel-form-row">
                                    <div class="life-travel-form-field">
                                        <label><?php _e('Participants min', 'life-travel-excursion'); ?></label>
                                        <input type="number" name="private_tier_min[]" 
                                               value="<?php echo intval($tier['min']); ?>" min="1" required>
                                    </div>
                                    <div class="life-travel-form-field">
                                        <label><?php _e('Participants max', 'life-travel-excursion'); ?></label>
                                        <input type="number" name="private_tier_max[]" 
                                               value="<?php echo intval($tier['max']); ?>" min="1" required>
                                    </div>
                                    <div class="life-travel-form-field">
                                        <label><?php _e('Prix', 'life-travel-excursion'); ?></label>
                                        <div class="life-travel-input-group">
                                            <input type="number" name="private_tier_price[]" 
                                                   value="<?php echo floatval($tier['price']); ?>" min="0" step="1000" required>
                                            <span class="life-travel-input-suffix">XAF</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endforeach;
                    else :
                        // Afficher un élément vide si aucun niveau n'existe
                    ?>
                        <div class="life-travel-repeater-item">
                            <div class="life-travel-repeater-item-header">
                                <span class="life-travel-repeater-title">
                                    <?php _e('Nouveau niveau de prix', 'life-travel-excursion'); ?>
                                </span>
                                <button type="button" class="life-travel-repeater-remove button">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            <div class="life-travel-repeater-item-content">
                                <div class="life-travel-form-row">
                                    <div class="life-travel-form-field">
                                        <label><?php _e('Participants min', 'life-travel-excursion'); ?></label>
                                        <input type="number" name="private_tier_min[]" value="1" min="1" required>
                                    </div>
                                    <div class="life-travel-form-field">
                                        <label><?php _e('Participants max', 'life-travel-excursion'); ?></label>
                                        <input type="number" name="private_tier_max[]" value="5" min="1" required>
                                    </div>
                                    <div class="life-travel-form-field">
                                        <label><?php _e('Prix', 'life-travel-excursion'); ?></label>
                                        <div class="life-travel-input-group">
                                            <input type="number" name="private_tier_price[]" value="25000" min="0" step="1000" required>
                                            <span class="life-travel-input-suffix">XAF</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="button" class="life-travel-repeater-add button">
                    <span class="dashicons dashicons-plus"></span>
                    <?php _e('Ajouter un niveau de prix', 'life-travel-excursion'); ?>
                </button>
                
                <div class="life-travel-repeater-template" style="display: none;">
                    <!-- Modèle pour nouvel élément -->
                    <div class="life-travel-repeater-item">
                        <div class="life-travel-repeater-item-header">
                            <span class="life-travel-repeater-title">
                                <?php _e('Nouveau niveau de prix', 'life-travel-excursion'); ?>
                            </span>
                            <button type="button" class="life-travel-repeater-remove button">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        <div class="life-travel-repeater-item-content">
                            <div class="life-travel-form-row">
                                <div class="life-travel-form-field">
                                    <label><?php _e('Participants min', 'life-travel-excursion'); ?></label>
                                    <input type="number" name="private_tier_min[]" value="1" min="1" required>
                                </div>
                                <div class="life-travel-form-field">
                                    <label><?php _e('Participants max', 'life-travel-excursion'); ?></label>
                                    <input type="number" name="private_tier_max[]" value="5" min="1" required>
                                </div>
                                <div class="life-travel-form-field">
                                    <label><?php _e('Prix', 'life-travel-excursion'); ?></label>
                                    <div class="life-travel-input-group">
                                        <input type="number" name="private_tier_price[]" value="25000" min="0" step="1000" required>
                                        <span class="life-travel-input-suffix">XAF</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Suite dans excursions-tab-part3.php -->
<?php require_once LIFE_TRAVEL_PLUGIN_DIR . 'admin/tabs/excursions-tab-part3.php'; ?>

<script>
jQuery(document).ready(function($) {
    // Gestion des excursions de groupe
    $('input[name="group_excursion_enabled"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#group_excursion_options').slideDown(200);
        } else {
            $('#group_excursion_options').slideUp(200);
        }
    }).trigger('change');
    
    // Gestion des remises pour grands groupes
    $('input[name="group_enable_discount"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#group_discount_options').slideDown(200);
        } else {
            $('#group_discount_options').slideUp(200);
        }
    }).trigger('change');
    
    // Gestion des excursions privées
    $('input[name="private_excursion_enabled"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#private_excursion_options').slideDown(200);
        } else {
            $('#private_excursion_options').slideUp(200);
        }
    }).trigger('change');
    
    // Gestion des niveaux de prix pour excursions privées
    $('input[name="private_enable_pricing_tiers"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#private_pricing_tiers').slideDown(200);
        } else {
            $('#private_pricing_tiers').slideUp(200);
        }
    }).trigger('change');
    
    // Gestion du repeater pour les niveaux de prix
    // Ajout d'un nouveau niveau
    $('#private_pricing_tiers .life-travel-repeater-add').on('click', function() {
        var template = $('#private_pricing_tiers .life-travel-repeater-template').html();
        $('#private_pricing_tiers .life-travel-repeater-items').append(template);
        initPricingTierEvents();
        updateTierTitles();
    });
    
    // Initialisation des événements pour les niveaux existants et nouveaux
    function initPricingTierEvents() {
        // Suppression d'un niveau
        $('#private_pricing_tiers .life-travel-repeater-remove').off('click').on('click', function() {
            var item = $(this).closest('.life-travel-repeater-item');
            
            // Animation de suppression
            item.slideUp(200, function() {
                $(this).remove();
                updateTierTitles();
            });
        });
        
        // Mise à jour du titre lors de modifications
        $('#private_pricing_tiers input').off('change').on('change', function() {
            updateTierTitles();
        });
        
        // Toggle du contenu
        $('#private_pricing_tiers .life-travel-repeater-item-header').off('click').on('click', function(e) {
            if ($(e.target).hasClass('life-travel-repeater-remove') || 
                $(e.target).closest('.life-travel-repeater-remove').length) {
                return;
            }
            
            $(this).next('.life-travel-repeater-item-content').slideToggle(200);
        });
    }
    
    // Mise à jour des titres des niveaux
    function updateTierTitles() {
        $('#private_pricing_tiers .life-travel-repeater-item').each(function() {
            var min = $(this).find('input[name="private_tier_min[]"]').val() || '?';
            var max = $(this).find('input[name="private_tier_max[]"]').val() || '?';
            var price = $(this).find('input[name="private_tier_price[]"]').val() || '0';
            
            // Formater le prix
            var formattedPrice = price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, "<?php echo $options['price_thousand_separator']; ?>");
            
            var title = min + ' à ' + max + ' participants: ' + formattedPrice + ' XAF';
            $(this).find('.life-travel-repeater-title').text(title);
        });
    }
    
    // Validation sur les niveaux de prix pour éviter les chevauchements
    $('form').on('submit', function(e) {
        if ($('input[name="private_enable_pricing_tiers"]').is(':checked')) {
            var tiers = [];
            var valid = true;
            
            // Collecter tous les niveaux
            $('#private_pricing_tiers .life-travel-repeater-item').each(function() {
                var min = parseInt($(this).find('input[name="private_tier_min[]"]').val(), 10);
                var max = parseInt($(this).find('input[name="private_tier_max[]"]').val(), 10);
                
                // Validation basique
                if (min > max) {
                    alert("<?php _e('Erreur: Le nombre minimum de participants ne peut pas être supérieur au maximum.', 'life-travel-excursion'); ?>");
                    valid = false;
                    return false;
                }
                
                tiers.push({ min: min, max: max });
            });
            
            // Vérifier les chevauchements
            if (valid && tiers.length > 1) {
                tiers.sort((a, b) => a.min - b.min);
                
                for (var i = 0; i < tiers.length - 1; i++) {
                    if (tiers[i].max >= tiers[i + 1].min) {
                        alert("<?php _e('Erreur: Les niveaux de prix se chevauchent. Veuillez vérifier vos valeurs.', 'life-travel-excursion'); ?>");
                        valid = false;
                        break;
                    }
                }
            }
            
            if (!valid) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Initialiser les événements
    initPricingTierEvents();
    updateTierTitles();
});
</script>
