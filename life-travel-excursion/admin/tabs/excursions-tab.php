<?php
/**
 * Onglet des paramètres de types d'excursions de Life Travel Excursion
 * 
 * @package Life_Travel_Excursion
 * @since 2.0.0
 */

// Sortie directe interdite
if (!defined('ABSPATH')) {
    exit;
}

// Récupérer les options enregistrées
$options = get_option('life_travel_excursion_options', array());

// Valeurs par défaut
$defaults = array(
    // Paramètres généraux des excursions
    'currency_position' => 'after',
    'price_thousand_separator' => ' ',
    'price_decimal_separator' => ',',
    'price_decimals' => 0,
    'require_payment_type' => 'partial',
    'excursion_tax_class' => 'standard',
    'display_stock' => 'yes',
    'stock_threshold' => 5,
    'low_stock_threshold' => 3,
    
    // Excursions de groupe
    'group_excursion_enabled' => 'yes',
    'group_min_participants' => 2,
    'group_max_participants' => 30,
    'group_default_price' => 15000,
    'group_availability_calendar' => 'yes',
    'group_booking_lead_time' => 24, // heures
    'group_cancellation_period' => 48, // heures
    'group_enable_discount' => 'yes',
    'group_discount_threshold' => 10, // participants
    'group_discount_rate' => 10, // pourcentage
    
    // Excursions privées
    'private_excursion_enabled' => 'yes',
    'private_min_participants' => 1,
    'private_max_participants' => 15,
    'private_default_price' => 25000,
    'private_availability_calendar' => 'yes',
    'private_booking_lead_time' => 48, // heures
    'private_cancellation_period' => 72, // heures
    'private_vehicle_options' => 'yes',
    'private_enable_pricing_tiers' => 'yes',
    'private_pricing_tiers' => array(
        array('min' => 1, 'max' => 3, 'price' => 25000),
        array('min' => 4, 'max' => 8, 'price' => 20000),
        array('min' => 9, 'max' => 15, 'price' => 18000)
    ),
    
    // Options de réservation
    'default_availability' => array('monday', 'tuesday', 'wednesday', 'thursday', 'friday'),
    'daily_start_time' => '08:00',
    'daily_end_time' => '18:00',
    'booking_window_days' => 180, // jours
    'excluded_dates' => array(),
    'seasonal_pricing' => 'no',
    'seasonal_prices' => array(),
    
    // Options d'extras et d'activités
    'enable_extras' => 'yes',
    'extras_required' => 'no',
    'max_extras' => 5,
    'enable_activities' => 'yes',
    'activities_required' => 'no',
    'max_activities' => 3,
);

// Fusionner avec les valeurs par défaut
$options = wp_parse_args($options, $defaults);

// Traiter l'enregistrement du formulaire
if (isset($_POST['life_travel_save_excursions'])) {
    check_admin_referer('life_travel_excursions_nonce');
    
    // Validation et assainissement des entrées - paramètres généraux
    $options['currency_position'] = sanitize_text_field($_POST['currency_position']);
    $options['price_thousand_separator'] = sanitize_text_field($_POST['price_thousand_separator']);
    $options['price_decimal_separator'] = sanitize_text_field($_POST['price_decimal_separator']);
    $options['price_decimals'] = absint($_POST['price_decimals']);
    $options['require_payment_type'] = sanitize_text_field($_POST['require_payment_type']);
    $options['excursion_tax_class'] = sanitize_text_field($_POST['excursion_tax_class']);
    $options['display_stock'] = isset($_POST['display_stock']) ? 'yes' : 'no';
    $options['stock_threshold'] = absint($_POST['stock_threshold']);
    $options['low_stock_threshold'] = absint($_POST['low_stock_threshold']);
    
    // Validation et assainissement des entrées - excursions de groupe
    $options['group_excursion_enabled'] = isset($_POST['group_excursion_enabled']) ? 'yes' : 'no';
    $options['group_min_participants'] = absint($_POST['group_min_participants']);
    $options['group_max_participants'] = absint($_POST['group_max_participants']);
    $options['group_default_price'] = floatval($_POST['group_default_price']);
    $options['group_availability_calendar'] = isset($_POST['group_availability_calendar']) ? 'yes' : 'no';
    $options['group_booking_lead_time'] = absint($_POST['group_booking_lead_time']);
    $options['group_cancellation_period'] = absint($_POST['group_cancellation_period']);
    $options['group_enable_discount'] = isset($_POST['group_enable_discount']) ? 'yes' : 'no';
    $options['group_discount_threshold'] = absint($_POST['group_discount_threshold']);
    $options['group_discount_rate'] = absint($_POST['group_discount_rate']);
    
    // Validation et assainissement des entrées - excursions privées
    $options['private_excursion_enabled'] = isset($_POST['private_excursion_enabled']) ? 'yes' : 'no';
    $options['private_min_participants'] = absint($_POST['private_min_participants']);
    $options['private_max_participants'] = absint($_POST['private_max_participants']);
    $options['private_default_price'] = floatval($_POST['private_default_price']);
    $options['private_availability_calendar'] = isset($_POST['private_availability_calendar']) ? 'yes' : 'no';
    $options['private_booking_lead_time'] = absint($_POST['private_booking_lead_time']);
    $options['private_cancellation_period'] = absint($_POST['private_cancellation_period']);
    $options['private_vehicle_options'] = isset($_POST['private_vehicle_options']) ? 'yes' : 'no';
    $options['private_enable_pricing_tiers'] = isset($_POST['private_enable_pricing_tiers']) ? 'yes' : 'no';
    
    // Traitement des niveaux de prix pour excursions privées
    $private_pricing_tiers = array();
    if (isset($_POST['private_tier_min']) && is_array($_POST['private_tier_min'])) {
        foreach ($_POST['private_tier_min'] as $key => $min) {
            if (isset($_POST['private_tier_max'][$key]) && isset($_POST['private_tier_price'][$key])) {
                $private_pricing_tiers[] = array(
                    'min' => absint($min),
                    'max' => absint($_POST['private_tier_max'][$key]),
                    'price' => floatval($_POST['private_tier_price'][$key])
                );
            }
        }
    }
    $options['private_pricing_tiers'] = $private_pricing_tiers;
    
    // Validation et assainissement des entrées - options de réservation
    $options['default_availability'] = isset($_POST['default_availability']) ? 
                                      array_map('sanitize_text_field', $_POST['default_availability']) : 
                                      array();
    $options['daily_start_time'] = sanitize_text_field($_POST['daily_start_time']);
    $options['daily_end_time'] = sanitize_text_field($_POST['daily_end_time']);
    $options['booking_window_days'] = absint($_POST['booking_window_days']);
    
    // Traitement des dates exclues
    $excluded_dates = array();
    if (!empty($_POST['excluded_dates'])) {
        $dates = explode(',', sanitize_text_field($_POST['excluded_dates']));
        foreach ($dates as $date) {
            $date = trim($date);
            // Valider le format de date (YYYY-MM-DD)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $excluded_dates[] = $date;
            }
        }
    }
    $options['excluded_dates'] = $excluded_dates;
    
    $options['seasonal_pricing'] = isset($_POST['seasonal_pricing']) ? 'yes' : 'no';
    
    // Traitement des prix saisonniers
    $seasonal_prices = array();
    if (isset($_POST['seasonal_start_date']) && is_array($_POST['seasonal_start_date'])) {
        foreach ($_POST['seasonal_start_date'] as $key => $start_date) {
            if (isset($_POST['seasonal_end_date'][$key]) && isset($_POST['seasonal_price_modifier'][$key])) {
                // Valider les dates
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) && 
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['seasonal_end_date'][$key])) {
                    
                    $seasonal_prices[] = array(
                        'start_date' => sanitize_text_field($start_date),
                        'end_date' => sanitize_text_field($_POST['seasonal_end_date'][$key]),
                        'modifier' => floatval($_POST['seasonal_price_modifier'][$key]),
                        'type' => sanitize_text_field($_POST['seasonal_price_type'][$key])
                    );
                }
            }
        }
    }
    $options['seasonal_prices'] = $seasonal_prices;
    
    // Validation et assainissement des entrées - options d'extras et d'activités
    $options['enable_extras'] = isset($_POST['enable_extras']) ? 'yes' : 'no';
    $options['extras_required'] = isset($_POST['extras_required']) ? 'yes' : 'no';
    $options['max_extras'] = absint($_POST['max_extras']);
    $options['enable_activities'] = isset($_POST['enable_activities']) ? 'yes' : 'no';
    $options['activities_required'] = isset($_POST['activities_required']) ? 'yes' : 'no';
    $options['max_activities'] = absint($_POST['max_activities']);
    
    // Validation supplémentaire
    if ($options['group_min_participants'] < 1) {
        $options['group_min_participants'] = 2;
    }
    
    if ($options['group_max_participants'] < $options['group_min_participants']) {
        $options['group_max_participants'] = $options['group_min_participants'] + 10;
    }
    
    if ($options['private_min_participants'] < 1) {
        $options['private_min_participants'] = 1;
    }
    
    if ($options['private_max_participants'] < $options['private_min_participants']) {
        $options['private_max_participants'] = $options['private_min_participants'] + 10;
    }
    
    // Enregistrer les options
    update_option('life_travel_excursion_options', $options);
    
    // Afficher un message de succès
    add_settings_error(
        'life_travel_excursion_settings',
        'settings_updated',
        __('Paramètres des types d\'excursions mis à jour avec succès !', 'life-travel-excursion'),
        'updated'
    );
}

// Afficher les erreurs/messages de succès
settings_errors('life_travel_excursion_settings');
?>

<form method="post" action="" class="life-travel-admin-form">
    <?php wp_nonce_field('life_travel_excursions_nonce'); ?>
    
    <!-- Configuration générale des excursions -->
    <div class="life-travel-admin-card">
        <h3 class="life-travel-card-header">
            <span class="dashicons dashicons-admin-generic"></span> 
            <?php _e('Configuration générale des excursions', 'life-travel-excursion'); ?>
        </h3>
        <div class="life-travel-card-body">
            <p class="life-travel-description">
                <?php _e('Paramètres généraux applicables à tous les types d\'excursions.', 'life-travel-excursion'); ?>
            </p>
            
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="currency_position">
                        <?php _e('Position de la devise', 'life-travel-excursion'); ?>
                    </label>
                    <select id="currency_position" name="currency_position">
                        <option value="before" <?php selected($options['currency_position'], 'before'); ?>>
                            <?php _e('Avant le prix (ex: $10)', 'life-travel-excursion'); ?>
                        </option>
                        <option value="after" <?php selected($options['currency_position'], 'after'); ?>>
                            <?php _e('Après le prix (ex: 10 XAF)', 'life-travel-excursion'); ?>
                        </option>
                    </select>
                </div>
                
                <div class="life-travel-form-field">
                    <label for="price_decimals">
                        <?php _e('Décimales pour les prix', 'life-travel-excursion'); ?>
                    </label>
                    <input type="number" id="price_decimals" name="price_decimals" 
                           value="<?php echo intval($options['price_decimals']); ?>" min="0" max="4">
                </div>
            </div>
            
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="price_thousand_separator">
                        <?php _e('Séparateur de milliers', 'life-travel-excursion'); ?>
                    </label>
                    <input type="text" id="price_thousand_separator" name="price_thousand_separator" 
                           value="<?php echo esc_attr($options['price_thousand_separator']); ?>" maxlength="1">
                </div>
                
                <div class="life-travel-form-field">
                    <label for="price_decimal_separator">
                        <?php _e('Séparateur décimal', 'life-travel-excursion'); ?>
                    </label>
                    <input type="text" id="price_decimal_separator" name="price_decimal_separator" 
                           value="<?php echo esc_attr($options['price_decimal_separator']); ?>" maxlength="1">
                </div>
            </div>
            
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="require_payment_type">
                        <?php _e('Exigence de paiement par défaut', 'life-travel-excursion'); ?>
                    </label>
                    <select id="require_payment_type" name="require_payment_type">
                        <option value="full" <?php selected($options['require_payment_type'], 'full'); ?>>
                            <?php _e('Paiement complet', 'life-travel-excursion'); ?>
                        </option>
                        <option value="partial" <?php selected($options['require_payment_type'], 'partial'); ?>>
                            <?php _e('Acompte (partiel)', 'life-travel-excursion'); ?>
                        </option>
                        <option value="onsite" <?php selected($options['require_payment_type'], 'onsite'); ?>>
                            <?php _e('Paiement sur place', 'life-travel-excursion'); ?>
                        </option>
                    </select>
                </div>
                
                <div class="life-travel-form-field">
                    <label for="excursion_tax_class">
                        <?php _e('Classe de taxe', 'life-travel-excursion'); ?>
                    </label>
                    <select id="excursion_tax_class" name="excursion_tax_class">
                        <option value="standard" <?php selected($options['excursion_tax_class'], 'standard'); ?>>
                            <?php _e('Standard', 'life-travel-excursion'); ?>
                        </option>
                        <option value="reduced" <?php selected($options['excursion_tax_class'], 'reduced'); ?>>
                            <?php _e('Réduite', 'life-travel-excursion'); ?>
                        </option>
                        <option value="zero" <?php selected($options['excursion_tax_class'], 'zero'); ?>>
                            <?php _e('Zéro', 'life-travel-excursion'); ?>
                        </option>
                    </select>
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="display_stock" value="yes" 
                        <?php checked($options['display_stock'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Afficher le niveau de stock', 'life-travel-excursion'); ?>
                </label>
                <p class="description">
                    <?php _e('Afficher le nombre de places restantes sur la page de l\'excursion.', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="life-travel-form-row" id="stockThresholds">
                <div class="life-travel-form-field">
                    <label for="stock_threshold">
                        <?php _e('Seuil d\'affichage du stock', 'life-travel-excursion'); ?>
                    </label>
                    <input type="number" id="stock_threshold" name="stock_threshold" 
                           value="<?php echo intval($options['stock_threshold']); ?>" min="0" max="50">
                    <p class="description">
                        <?php _e('Afficher le nombre exact de places restantes seulement si moins que ce nombre.', 'life-travel-excursion'); ?>
                    </p>
                </div>
                
                <div class="life-travel-form-field">
                    <label for="low_stock_threshold">
                        <?php _e('Seuil de stock bas', 'life-travel-excursion'); ?>
                    </label>
                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" 
                           value="<?php echo intval($options['low_stock_threshold']); ?>" min="0" max="20">
                    <p class="description">
                        <?php _e('Afficher un message "Places limitées" si moins que ce nombre.', 'life-travel-excursion'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Suite dans excursions-tab-part2.php -->
    <?php require_once LIFE_TRAVEL_PLUGIN_DIR . 'admin/tabs/excursions-tab-part2.php'; ?>
</form>

<script>
jQuery(document).ready(function($) {
    // Gestion de l'affichage du stock
    $('input[name="display_stock"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#stockThresholds').slideDown(200);
        } else {
            $('#stockThresholds').slideUp(200);
        }
    }).trigger('change');
});
</script>
