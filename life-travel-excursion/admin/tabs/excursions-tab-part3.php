<?php
/**
 * Troisième partie de l'onglet des paramètres de types d'excursions
 * Implémentée avec les mêmes principes de sécurité et robustesse que la méthode sync_abandoned_cart
 * 
 * @package Life_Travel_Excursion
 * @since 2.0.0
 */

// Sortie directe interdite
if (!defined('ABSPATH')) {
    exit;
}

// Calendrier polyfill pour date picker 
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
?>

<!-- Configuration des options de réservation -->
<div class="life-travel-admin-card">
    <h3 class="life-travel-card-header">
        <span class="dashicons dashicons-calendar-alt"></span> 
        <?php _e('Options de réservation', 'life-travel-excursion'); ?>
    </h3>
    <div class="life-travel-card-body">
        <div class="life-travel-form-field">
            <label><?php _e('Jours disponibles par défaut', 'life-travel-excursion'); ?></label>
            <div class="life-travel-checkbox-group">
                <?php
                $days = array(
                    'monday'    => __('Lundi', 'life-travel-excursion'),
                    'tuesday'   => __('Mardi', 'life-travel-excursion'),
                    'wednesday' => __('Mercredi', 'life-travel-excursion'),
                    'thursday'  => __('Jeudi', 'life-travel-excursion'),
                    'friday'    => __('Vendredi', 'life-travel-excursion'),
                    'saturday'  => __('Samedi', 'life-travel-excursion'),
                    'sunday'    => __('Dimanche', 'life-travel-excursion')
                );
                
                foreach ($days as $day_key => $day_label) :
                    $checked = in_array($day_key, $options['default_availability']) ? 'checked' : '';
                ?>
                    <label class="life-travel-checkbox">
                        <input type="checkbox" name="default_availability[]" value="<?php echo esc_attr($day_key); ?>" <?php echo $checked; ?>>
                        <span><?php echo esc_html($day_label); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="description">
                <?php _e('Jours de la semaine où les excursions sont généralement disponibles.', 'life-travel-excursion'); ?>
            </p>
        </div>
        
        <div class="life-travel-form-row">
            <div class="life-travel-form-field">
                <label for="daily_start_time">
                    <?php _e('Heure de début quotidienne', 'life-travel-excursion'); ?>
                </label>
                <input type="time" id="daily_start_time" name="daily_start_time" 
                       value="<?php echo esc_attr($options['daily_start_time']); ?>">
            </div>
            
            <div class="life-travel-form-field">
                <label for="daily_end_time">
                    <?php _e('Heure de fin quotidienne', 'life-travel-excursion'); ?>
                </label>
                <input type="time" id="daily_end_time" name="daily_end_time" 
                       value="<?php echo esc_attr($options['daily_end_time']); ?>">
            </div>
        </div>
        
        <div class="life-travel-form-field">
            <label for="booking_window_days">
                <?php _e('Fenêtre de réservation', 'life-travel-excursion'); ?>
            </label>
            <div class="life-travel-input-group">
                <input type="number" id="booking_window_days" name="booking_window_days" 
                       value="<?php echo intval($options['booking_window_days']); ?>" min="1" max="365">
                <span class="life-travel-input-suffix">
                    <?php _e('jours', 'life-travel-excursion'); ?>
                </span>
            </div>
            <p class="description">
                <?php _e('Période future pendant laquelle les clients peuvent effectuer des réservations.', 'life-travel-excursion'); ?>
            </p>
        </div>
        
        <div class="life-travel-form-field">
            <label for="excluded_dates">
                <?php _e('Dates exclues (non disponibles)', 'life-travel-excursion'); ?>
            </label>
            <input type="text" id="excluded_dates" name="excluded_dates" 
                   value="<?php echo esc_attr(implode(', ', $options['excluded_dates'])); ?>" 
                   class="life-travel-datepicker-input" readonly>
            <div id="excluded_dates_calendar"></div>
            <p class="description">
                <?php _e('Sélectionnez les dates auxquelles aucune excursion n\'est disponible (jours fériés, fermeture, etc.).', 'life-travel-excursion'); ?>
            </p>
        </div>
        
        <div class="life-travel-form-field">
            <label class="life-travel-toggle-switch">
                <input type="checkbox" name="seasonal_pricing" value="yes" 
                    <?php checked($options['seasonal_pricing'], 'yes'); ?>>
                <span class="life-travel-toggle-slider"></span>
                <?php _e('Activer les prix saisonniers', 'life-travel-excursion'); ?>
            </label>
            <p class="description">
                <?php _e('Définir des périodes avec des prix spécifiques (haute saison, basse saison, etc.).', 'life-travel-excursion'); ?>
            </p>
        </div>
        
        <div id="seasonal_pricing_options" class="life-travel-repeater">
            <div class="life-travel-repeater-items">
                <?php 
                // Afficher les périodes saisonnières existantes
                if (!empty($options['seasonal_prices'])) :
                    foreach ($options['seasonal_prices'] as $season) : 
                        $modifier_text = $season['type'] == 'percentage' 
                            ? $season['modifier'] . '%' 
                            : number_format($season['modifier'], 0, $options['price_decimal_separator'], $options['price_thousand_separator']) . ' XAF';
                        $modifier_type = $season['type'] == 'percentage' ? __('pourcentage', 'life-travel-excursion') : __('montant fixe', 'life-travel-excursion');
                ?>
                    <div class="life-travel-repeater-item">
                        <div class="life-travel-repeater-item-header">
                            <span class="life-travel-repeater-title">
                                <?php 
                                echo sprintf(
                                    __('Du %s au %s: %s (%s)', 'life-travel-excursion'),
                                    $season['start_date'],
                                    $season['end_date'],
                                    $modifier_text,
                                    $modifier_type
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
                                    <label><?php _e('Date de début', 'life-travel-excursion'); ?></label>
                                    <input type="text" name="seasonal_start_date[]" class="seasonal-datepicker"
                                           value="<?php echo esc_attr($season['start_date']); ?>" placeholder="YYYY-MM-DD" required>
                                </div>
                                <div class="life-travel-form-field">
                                    <label><?php _e('Date de fin', 'life-travel-excursion'); ?></label>
                                    <input type="text" name="seasonal_end_date[]" class="seasonal-datepicker"
                                           value="<?php echo esc_attr($season['end_date']); ?>" placeholder="YYYY-MM-DD" required>
                                </div>
                            </div>
                            <div class="life-travel-form-row">
                                <div class="life-travel-form-field">
                                    <label><?php _e('Modificateur de prix', 'life-travel-excursion'); ?></label>
                                    <input type="number" name="seasonal_price_modifier[]" 
                                           value="<?php echo floatval($season['modifier']); ?>" step="any" required>
                                </div>
                                <div class="life-travel-form-field">
                                    <label><?php _e('Type de modificateur', 'life-travel-excursion'); ?></label>
                                    <select name="seasonal_price_type[]">
                                        <option value="percentage" <?php selected($season['type'], 'percentage'); ?>>
                                            <?php _e('Pourcentage (%)', 'life-travel-excursion'); ?>
                                        </option>
                                        <option value="fixed" <?php selected($season['type'], 'fixed'); ?>>
                                            <?php _e('Montant fixe (XAF)', 'life-travel-excursion'); ?>
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <p class="description">
                                <?php _e('Pour les pourcentages, utilisez des valeurs positives pour augmenter le prix, négatives pour le réduire.', 'life-travel-excursion'); ?>
                            </p>
                        </div>
                    </div>
                <?php 
                    endforeach;
                else :
                    // Afficher un élément vide si aucune période n'existe
                ?>
                    <div class="life-travel-repeater-item">
                        <div class="life-travel-repeater-item-header">
                            <span class="life-travel-repeater-title">
                                <?php _e('Nouvelle période saisonnière', 'life-travel-excursion'); ?>
                            </span>
                            <button type="button" class="life-travel-repeater-remove button">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        <div class="life-travel-repeater-item-content">
                            <div class="life-travel-form-row">
                                <div class="life-travel-form-field">
                                    <label><?php _e('Date de début', 'life-travel-excursion'); ?></label>
                                    <input type="text" name="seasonal_start_date[]" class="seasonal-datepicker"
                                           placeholder="YYYY-MM-DD" required>
                                </div>
                                <div class="life-travel-form-field">
                                    <label><?php _e('Date de fin', 'life-travel-excursion'); ?></label>
                                    <input type="text" name="seasonal_end_date[]" class="seasonal-datepicker"
                                           placeholder="YYYY-MM-DD" required>
                                </div>
                            </div>
                            <div class="life-travel-form-row">
                                <div class="life-travel-form-field">
                                    <label><?php _e('Modificateur de prix', 'life-travel-excursion'); ?></label>
                                    <input type="number" name="seasonal_price_modifier[]" value="15" step="any" required>
                                </div>
                                <div class="life-travel-form-field">
                                    <label><?php _e('Type de modificateur', 'life-travel-excursion'); ?></label>
                                    <select name="seasonal_price_type[]">
                                        <option value="percentage"><?php _e('Pourcentage (%)', 'life-travel-excursion'); ?></option>
                                        <option value="fixed"><?php _e('Montant fixe (XAF)', 'life-travel-excursion'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <p class="description">
                                <?php _e('Pour les pourcentages, utilisez des valeurs positives pour augmenter le prix, négatives pour le réduire.', 'life-travel-excursion'); ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="button" class="life-travel-repeater-add button">
                <span class="dashicons dashicons-plus"></span>
                <?php _e('Ajouter une période saisonnière', 'life-travel-excursion'); ?>
            </button>
            
            <div class="life-travel-repeater-template" style="display: none;">
                <!-- Modèle pour nouvel élément -->
                <div class="life-travel-repeater-item">
                    <div class="life-travel-repeater-item-header">
                        <span class="life-travel-repeater-title">
                            <?php _e('Nouvelle période saisonnière', 'life-travel-excursion'); ?>
                        </span>
                        <button type="button" class="life-travel-repeater-remove button">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                    <div class="life-travel-repeater-item-content">
                        <div class="life-travel-form-row">
                            <div class="life-travel-form-field">
                                <label><?php _e('Date de début', 'life-travel-excursion'); ?></label>
                                <input type="text" name="seasonal_start_date[]" class="seasonal-datepicker"
                                       placeholder="YYYY-MM-DD" required>
                            </div>
                            <div class="life-travel-form-field">
                                <label><?php _e('Date de fin', 'life-travel-excursion'); ?></label>
                                <input type="text" name="seasonal_end_date[]" class="seasonal-datepicker"
                                       placeholder="YYYY-MM-DD" required>
                            </div>
                        </div>
                        <div class="life-travel-form-row">
                            <div class="life-travel-form-field">
                                <label><?php _e('Modificateur de prix', 'life-travel-excursion'); ?></label>
                                <input type="number" name="seasonal_price_modifier[]" value="15" step="any" required>
                            </div>
                            <div class="life-travel-form-field">
                                <label><?php _e('Type de modificateur', 'life-travel-excursion'); ?></label>
                                <select name="seasonal_price_type[]">
                                    <option value="percentage"><?php _e('Pourcentage (%)', 'life-travel-excursion'); ?></option>
                                    <option value="fixed"><?php _e('Montant fixe (XAF)', 'life-travel-excursion'); ?></option>
                                </select>
                            </div>
                        </div>
                        <p class="description">
                            <?php _e('Pour les pourcentages, utilisez des valeurs positives pour augmenter le prix, négatives pour le réduire.', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Configuration des extras et activités -->
<div class="life-travel-admin-card">
    <h3 class="life-travel-card-header">
        <span class="dashicons dashicons-list-view"></span> 
        <?php _e('Extras et activités', 'life-travel-excursion'); ?>
    </h3>
    <div class="life-travel-card-body">
        <div class="life-travel-form-row">
            <div class="life-travel-form-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="enable_extras" value="yes" 
                        <?php checked($options['enable_extras'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Activer les extras', 'life-travel-excursion'); ?>
                </label>
                <p class="description">
                    <?php _e('Permettre aux clients de sélectionner des options supplémentaires (transport, repas, etc.).', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="life-travel-form-field extras-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="extras_required" value="yes" 
                        <?php checked($options['extras_required'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Extras obligatoires', 'life-travel-excursion'); ?>
                </label>
                <p class="description">
                    <?php _e('Forcer les clients à sélectionner au moins un extra.', 'life-travel-excursion'); ?>
                </p>
            </div>
        </div>
        
        <div class="life-travel-form-field extras-field">
            <label for="max_extras">
                <?php _e('Nombre maximum d\'extras par réservation', 'life-travel-excursion'); ?>
            </label>
            <input type="number" id="max_extras" name="max_extras" 
                   value="<?php echo intval($options['max_extras']); ?>" min="1" max="20">
        </div>
        
        <div class="life-travel-form-row">
            <div class="life-travel-form-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="enable_activities" value="yes" 
                        <?php checked($options['enable_activities'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Activer les activités', 'life-travel-excursion'); ?>
                </label>
                <p class="description">
                    <?php _e('Permettre aux clients de sélectionner des activités spécifiques pendant l\'excursion.', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="life-travel-form-field activities-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="activities_required" value="yes" 
                        <?php checked($options['activities_required'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Activités obligatoires', 'life-travel-excursion'); ?>
                </label>
                <p class="description">
                    <?php _e('Forcer les clients à sélectionner au moins une activité.', 'life-travel-excursion'); ?>
                </p>
            </div>
        </div>
        
        <div class="life-travel-form-field activities-field">
            <label for="max_activities">
                <?php _e('Nombre maximum d\'activités par réservation', 'life-travel-excursion'); ?>
            </label>
            <input type="number" id="max_activities" name="max_activities" 
                   value="<?php echo intval($options['max_activities']); ?>" min="1" max="10">
        </div>
        
        <div class="life-travel-notice life-travel-info-notice">
            <p>
                <?php _e('Remarque: Les extras et activités spécifiques se configurent individuellement pour chaque excursion.', 'life-travel-excursion'); ?>
            </p>
        </div>
    </div>
</div>

<!-- Bouton de sauvegarde -->
<div class="life-travel-form-actions">
    <button type="submit" name="life_travel_save_excursions" class="button button-primary">
        <span class="dashicons dashicons-saved"></span>
        <?php _e('Enregistrer les paramètres', 'life-travel-excursion'); ?>
    </button>
    
    <a href="?page=life-travel-admin&tab=excursions" class="button">
        <span class="dashicons dashicons-dismiss"></span>
        <?php _e('Annuler les modifications', 'life-travel-excursion'); ?>
    </a>
</div>

<script>
jQuery(document).ready(function($) {
    // Gestion du calendrier pour dates exclues
    var excludedDates = [];
    <?php if (!empty($options['excluded_dates'])) : ?>
        excludedDates = <?php echo json_encode($options['excluded_dates']); ?>;
    <?php endif; ?>
    
    // Initialiser le calendrier
    $('#excluded_dates_calendar').datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true,
        yearRange: 'c-1:c+2',
        firstDay: 1, // Lundi comme premier jour
        beforeShowDay: function(date) {
            var dateString = $.datepicker.formatDate('yy-mm-dd', date);
            var isExcluded = $.inArray(dateString, excludedDates) !== -1;
            return [true, isExcluded ? 'ui-state-highlight' : ''];
        },
        onSelect: function(dateText) {
            var index = $.inArray(dateText, excludedDates);
            if (index !== -1) {
                // Supprimer la date si déjà sélectionnée
                excludedDates.splice(index, 1);
            } else {
                // Ajouter la date
                excludedDates.push(dateText);
            }
            // Mettre à jour le champ input
            $('#excluded_dates').val(excludedDates.join(', '));
        }
    });
    
    // Afficher le calendrier en cliquant sur le champ
    $('#excluded_dates').on('click', function() {
        $('#excluded_dates_calendar').toggle();
    });
    
    // Cacher le calendrier en cliquant ailleurs
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#excluded_dates_calendar, #excluded_dates').length) {
            $('#excluded_dates_calendar').hide();
        }
    });
    
    // Gestion des prix saisonniers
    $('input[name="seasonal_pricing"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#seasonal_pricing_options').slideDown(200);
        } else {
            $('#seasonal_pricing_options').slideUp(200);
        }
    }).trigger('change');
    
    // Gestion du repeater pour les périodes saisonnières
    $('#seasonal_pricing_options .life-travel-repeater-add').on('click', function() {
        var template = $('#seasonal_pricing_options .life-travel-repeater-template').html();
        $('#seasonal_pricing_options .life-travel-repeater-items').append(template);
        initSeasonalEvents();
        updateSeasonalTitles();
    });
    
    // Initialiser les datepickers pour périodes saisonnières
    function initSeasonalDatepickers() {
        $('.seasonal-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            yearRange: 'c-1:c+2',
            firstDay: 1
        });
    }
    
    // Initialisation des événements pour les périodes existantes et nouvelles
    function initSeasonalEvents() {
        // Initialiser les datepickers
        initSeasonalDatepickers();
        
        // Suppression d'une période
        $('#seasonal_pricing_options .life-travel-repeater-remove').off('click').on('click', function() {
            var item = $(this).closest('.life-travel-repeater-item');
            
            // Animation de suppression
            item.slideUp(200, function() {
                $(this).remove();
                updateSeasonalTitles();
            });
        });
        
        // Mise à jour du titre lors de modifications
        $('#seasonal_pricing_options input, #seasonal_pricing_options select').off('change').on('change', function() {
            updateSeasonalTitles();
        });
        
        // Toggle du contenu
        $('#seasonal_pricing_options .life-travel-repeater-item-header').off('click').on('click', function(e) {
            if ($(e.target).hasClass('life-travel-repeater-remove') || 
                $(e.target).closest('.life-travel-repeater-remove').length) {
                return;
            }
            
            $(this).next('.life-travel-repeater-item-content').slideToggle(200);
        });
    }
    
    // Mise à jour des titres des périodes
    function updateSeasonalTitles() {
        $('#seasonal_pricing_options .life-travel-repeater-item').each(function() {
            var startDate = $(this).find('input[name="seasonal_start_date[]"]').val() || '?';
            var endDate = $(this).find('input[name="seasonal_end_date[]"]').val() || '?';
            var modifier = $(this).find('input[name="seasonal_price_modifier[]"]').val() || '0';
            var type = $(this).find('select[name="seasonal_price_type[]"]').val();
            
            var modifierText = type === 'percentage' ? modifier + '%' : modifier + ' XAF';
            var modifierType = type === 'percentage' ? '<?php _e('pourcentage', 'life-travel-excursion'); ?>' : '<?php _e('montant fixe', 'life-travel-excursion'); ?>';
            
            var title = '<?php _e('Du', 'life-travel-excursion'); ?> ' + startDate + ' <?php _e('au', 'life-travel-excursion'); ?> ' + endDate + ': ' + modifierText + ' (' + modifierType + ')';
            $(this).find('.life-travel-repeater-title').text(title);
        });
    }
    
    // Validation sur les périodes saisonnières pour éviter les chevauchements
    $('form').on('submit', function(e) {
        if ($('input[name="seasonal_pricing"]').is(':checked')) {
            var seasons = [];
            var valid = true;
            
            // Collecter toutes les périodes
            $('#seasonal_pricing_options .life-travel-repeater-item').each(function() {
                var startDate = $(this).find('input[name="seasonal_start_date[]"]').val();
                var endDate = $(this).find('input[name="seasonal_end_date[]"]').val();
                
                // Validation basique des dates
                if (!startDate || !endDate) {
                    return true; // Continuer la boucle
                }
                
                if (startDate > endDate) {
                    alert("<?php _e('Erreur: La date de début ne peut pas être postérieure à la date de fin.', 'life-travel-excursion'); ?>");
                    valid = false;
                    return false;
                }
                
                seasons.push({ start: new Date(startDate), end: new Date(endDate) });
            });
            
            // Vérifier les chevauchements
            if (valid && seasons.length > 1) {
                for (var i = 0; i < seasons.length; i++) {
                    for (var j = i + 1; j < seasons.length; j++) {
                        if (
                            (seasons[i].start <= seasons[j].end && seasons[i].end >= seasons[j].start) ||
                            (seasons[j].start <= seasons[i].end && seasons[j].end >= seasons[i].start)
                        ) {
                            alert("<?php _e('Erreur: Certaines périodes saisonnières se chevauchent. Veuillez vérifier vos dates.', 'life-travel-excursion'); ?>");
                            valid = false;
                            break;
                        }
                    }
                    if (!valid) break;
                }
            }
            
            if (!valid) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Gestion des extras et activités
    $('input[name="enable_extras"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('.extras-field').slideDown(200);
        } else {
            $('.extras-field').slideUp(200);
        }
    }).trigger('change');
    
    $('input[name="enable_activities"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('.activities-field').slideDown(200);
        } else {
            $('.activities-field').slideUp(200);
        }
    }).trigger('change');
    
    // Initialiser les événements
    initSeasonalEvents();
    updateSeasonalTitles();
    initSeasonalDatepickers();
});
</script>
