<?php
/**
 * Onglet des paramètres généraux de Life Travel Excursion
 * 
 * @package Life_Travel_Excursion
 * @since 2.0.0
 */

// Sortie directe interdite
if (!defined('ABSPATH')) {
    exit;
}

// Récupérer les options enregistrées
$options = get_option('life_travel_general_options', array());

// Valeurs par défaut
$defaults = array(
    'company_name' => 'Life Travel Excursion',
    'company_logo' => '',
    'company_email' => get_option('admin_email'),
    'company_phone' => '',
    'company_address' => '',
    'company_currency' => 'XAF',
    'booking_expiration' => 30, // minutes
    'enable_offline_mode' => 'yes',
    'offline_message' => __('Vous êtes actuellement en mode hors ligne. Vos données seront synchronisées lorsque vous serez de nouveau connecté.', 'life-travel-excursion'),
    'primary_color' => '#4CAF50',
    'secondary_color' => '#FFC107',
    'display_language' => 'fr_FR',
);

// Fusionner avec les valeurs par défaut
$options = wp_parse_args($options, $defaults);

// Traiter l'enregistrement du formulaire
if (isset($_POST['life_travel_save_general'])) {
    check_admin_referer('life_travel_general_nonce');
    
    // Valider et sanitiser les entrées
    $options['company_name'] = sanitize_text_field($_POST['company_name']);
    $options['company_logo'] = esc_url_raw($_POST['company_logo']);
    $options['company_email'] = sanitize_email($_POST['company_email']);
    $options['company_phone'] = sanitize_text_field($_POST['company_phone']);
    $options['company_address'] = sanitize_textarea_field($_POST['company_address']);
    $options['company_currency'] = sanitize_text_field($_POST['company_currency']);
    $options['booking_expiration'] = absint($_POST['booking_expiration']);
    $options['enable_offline_mode'] = isset($_POST['enable_offline_mode']) ? 'yes' : 'no';
    $options['offline_message'] = sanitize_textarea_field($_POST['offline_message']);
    $options['primary_color'] = sanitize_hex_color($_POST['primary_color']);
    $options['secondary_color'] = sanitize_hex_color($_POST['secondary_color']);
    $options['display_language'] = sanitize_text_field($_POST['display_language']);
    
    // Enregistrer les options
    update_option('life_travel_general_options', $options);
    
    // Afficher un message de succès
    add_settings_error(
        'life_travel_general_settings',
        'settings_updated',
        __('Paramètres généraux mis à jour avec succès !', 'life-travel-excursion'),
        'updated'
    );
}

// Afficher les erreurs/messages de succès
settings_errors('life_travel_general_settings');
?>

<form method="post" action="" class="life-travel-admin-form">
    <?php wp_nonce_field('life_travel_general_nonce'); ?>
    
    <div class="life-travel-admin-card">
        <h3 class="life-travel-card-header">
            <span class="dashicons dashicons-store"></span> 
            <?php _e('Informations de l\'entreprise', 'life-travel-excursion'); ?>
        </h3>
        <div class="life-travel-card-body">
            <p class="life-travel-description">
                <?php _e('Ces informations seront utilisées dans vos factures, emails et autres communications avec vos clients.', 'life-travel-excursion'); ?>
            </p>
            
            <div class="life-travel-form-field">
                <label for="company_name">
                    <?php _e('Nom de l\'entreprise', 'life-travel-excursion'); ?>
                    <span class="required">*</span>
                </label>
                <input type="text" id="company_name" name="company_name" 
                       value="<?php echo esc_attr($options['company_name']); ?>" required>
                <p class="description">
                    <?php _e('Le nom commercial de votre entreprise d\'excursions.', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="life-travel-form-field">
                <label for="company_logo">
                    <?php _e('Logo de l\'entreprise', 'life-travel-excursion'); ?>
                </label>
                <div class="life-travel-media-field">
                    <input type="text" id="company_logo" name="company_logo" 
                           value="<?php echo esc_url($options['company_logo']); ?>" class="regular-text">
                    <button type="button" class="button life-travel-upload-button">
                        <?php _e('Choisir une image', 'life-travel-excursion'); ?>
                    </button>
                </div>
                <div class="life-travel-logo-preview">
                    <?php if (!empty($options['company_logo'])) : ?>
                        <img src="<?php echo esc_url($options['company_logo']); ?>" alt="Logo">
                    <?php endif; ?>
                </div>
                <p class="description">
                    <?php _e('Format recommandé : PNG ou SVG transparent, maximum 500KB.', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="company_email">
                        <?php _e('Email de contact', 'life-travel-excursion'); ?>
                        <span class="required">*</span>
                    </label>
                    <input type="email" id="company_email" name="company_email" 
                           value="<?php echo esc_attr($options['company_email']); ?>" required>
                </div>
                
                <div class="life-travel-form-field">
                    <label for="company_phone">
                        <?php _e('Téléphone', 'life-travel-excursion'); ?>
                        <span class="required">*</span>
                    </label>
                    <input type="tel" id="company_phone" name="company_phone" 
                           value="<?php echo esc_attr($options['company_phone']); ?>"
                           placeholder="+237 xxxxxxxxx" required>
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <label for="company_address">
                    <?php _e('Adresse', 'life-travel-excursion'); ?>
                </label>
                <textarea id="company_address" name="company_address" rows="3"><?php echo esc_textarea($options['company_address']); ?></textarea>
            </div>
        </div>
    </div>
    
    <div class="life-travel-admin-card">
        <h3 class="life-travel-card-header">
            <span class="dashicons dashicons-admin-settings"></span> 
            <?php _e('Paramètres de base', 'life-travel-excursion'); ?>
        </h3>
        <div class="life-travel-card-body">
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="company_currency">
                        <?php _e('Devise', 'life-travel-excursion'); ?>
                    </label>
                    <select id="company_currency" name="company_currency">
                        <option value="XAF" <?php selected($options['company_currency'], 'XAF'); ?>>
                            <?php _e('Franc CFA (XAF)', 'life-travel-excursion'); ?>
                        </option>
                        <option value="EUR" <?php selected($options['company_currency'], 'EUR'); ?>>
                            <?php _e('Euro (EUR)', 'life-travel-excursion'); ?>
                        </option>
                        <option value="USD" <?php selected($options['company_currency'], 'USD'); ?>>
                            <?php _e('Dollar américain (USD)', 'life-travel-excursion'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Devise principale pour vos excursions. Cette option se synchronisera avec les paramètres WooCommerce.', 'life-travel-excursion'); ?>
                    </p>
                </div>
                
                <div class="life-travel-form-field">
                    <label for="booking_expiration">
                        <?php _e('Expiration des réservations', 'life-travel-excursion'); ?>
                    </label>
                    <div class="life-travel-input-group">
                        <input type="number" id="booking_expiration" name="booking_expiration" 
                               value="<?php echo intval($options['booking_expiration']); ?>" min="5" max="180">
                        <span class="life-travel-input-suffix">
                            <?php _e('minutes', 'life-travel-excursion'); ?>
                        </span>
                    </div>
                    <p class="description">
                        <?php _e('Durée pendant laquelle un panier est réservé avant d\'être libéré. Minimum 5 minutes, recommandé 30 minutes.', 'life-travel-excursion'); ?>
                    </p>
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <label for="display_language">
                    <?php _e('Langue d\'affichage principale', 'life-travel-excursion'); ?>
                </label>
                <select id="display_language" name="display_language">
                    <option value="fr_FR" <?php selected($options['display_language'], 'fr_FR'); ?>>
                        <?php _e('Français', 'life-travel-excursion'); ?>
                    </option>
                    <option value="en_US" <?php selected($options['display_language'], 'en_US'); ?>>
                        <?php _e('Anglais', 'life-travel-excursion'); ?>
                    </option>
                    <option value="es_ES" <?php selected($options['display_language'], 'es_ES'); ?>>
                        <?php _e('Espagnol', 'life-travel-excursion'); ?>
                    </option>
                </select>
                <p class="description">
                    <?php _e('Langue principale utilisée sur votre site. Les clients pourront changer la langue s\'ils le souhaitent.', 'life-travel-excursion'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <div class="life-travel-admin-card">
        <h3 class="life-travel-card-header">
            <span class="dashicons dashicons-admin-appearance"></span> 
            <?php _e('Apparence', 'life-travel-excursion'); ?>
        </h3>
        <div class="life-travel-card-body">
            <p class="life-travel-description">
                <?php _e('Personnalisez l\'apparence de votre plugin Life Travel Excursion pour l\'adapter à votre marque.', 'life-travel-excursion'); ?>
            </p>
            
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="primary_color">
                        <?php _e('Couleur principale', 'life-travel-excursion'); ?>
                    </label>
                    <input type="text" id="primary_color" name="primary_color" 
                           value="<?php echo esc_attr($options['primary_color']); ?>" 
                           class="life-travel-color-picker">
                    <p class="description">
                        <?php _e('Couleur principale utilisée pour les boutons et accents.', 'life-travel-excursion'); ?>
                    </p>
                </div>
                
                <div class="life-travel-form-field">
                    <label for="secondary_color">
                        <?php _e('Couleur secondaire', 'life-travel-excursion'); ?>
                    </label>
                    <input type="text" id="secondary_color" name="secondary_color" 
                           value="<?php echo esc_attr($options['secondary_color']); ?>" 
                           class="life-travel-color-picker">
                    <p class="description">
                        <?php _e('Couleur secondaire utilisée pour les points forts et accents.', 'life-travel-excursion'); ?>
                    </p>
                </div>
            </div>
            
            <div class="life-travel-preview-panel">
                <h4><?php _e('Aperçu', 'life-travel-excursion'); ?></h4>
                <div class="life-travel-preview-buttons" id="colorPreview">
                    <button type="button" class="life-travel-preview-primary">
                        <?php _e('Bouton principal', 'life-travel-excursion'); ?>
                    </button>
                    <button type="button" class="life-travel-preview-secondary">
                        <?php _e('Bouton secondaire', 'life-travel-excursion'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="life-travel-admin-card">
        <h3 class="life-travel-card-header">
            <span class="dashicons dashicons-cloud"></span> 
            <?php _e('Mode hors ligne', 'life-travel-excursion'); ?>
        </h3>
        <div class="life-travel-card-body">
            <div class="life-travel-form-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="enable_offline_mode" value="yes" 
                        <?php checked($options['enable_offline_mode'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Activer le mode hors ligne', 'life-travel-excursion'); ?>
                </label>
                <p class="description">
                    <?php _e('Permet aux utilisateurs de continuer à consulter et préparer des réservations même sans connexion internet stable.', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="life-travel-form-field life-travel-offline-options" id="offlineOptions">
                <label for="offline_message">
                    <?php _e('Message hors ligne', 'life-travel-excursion'); ?>
                </label>
                <textarea id="offline_message" name="offline_message" rows="3"><?php echo esc_textarea($options['offline_message']); ?></textarea>
                <p class="description">
                    <?php _e('Message affiché aux utilisateurs quand ils sont hors ligne.', 'life-travel-excursion'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <div class="life-travel-admin-actions">
        <button type="submit" name="life_travel_save_general" class="button button-primary">
            <span class="dashicons dashicons-saved"></span>
            <?php _e('Enregistrer les paramètres', 'life-travel-excursion'); ?>
        </button>
    </div>
</form>

<script>
jQuery(document).ready(function($) {
    // Initialiser le sélecteur de couleur
    $('.life-travel-color-picker').wpColorPicker({
        change: function(event, ui) {
            updateColorPreview();
        }
    });
    
    // Mise à jour de l'aperçu des couleurs
    function updateColorPreview() {
        var primaryColor = $('#primary_color').val();
        var secondaryColor = $('#secondary_color').val();
        
        $('.life-travel-preview-primary').css({
            'background-color': primaryColor,
            'border-color': primaryColor
        });
        
        $('.life-travel-preview-secondary').css({
            'background-color': secondaryColor,
            'border-color': secondaryColor
        });
    }
    
    // Exécuter une fois au chargement
    updateColorPreview();
    
    // Gestion du mode hors ligne
    $('input[name="enable_offline_mode"]').change(function() {
        if ($(this).is(':checked')) {
            $('#offlineOptions').slideDown();
        } else {
            $('#offlineOptions').slideUp();
        }
    }).trigger('change');
    
    // Gestion de l'upload du logo
    $('.life-travel-upload-button').click(function(e) {
        e.preventDefault();
        
        var button = $(this);
        var logoField = $('#company_logo');
        var previewDiv = $('.life-travel-logo-preview');
        
        var frame = wp.media({
            title: '<?php _e('Sélectionner un logo', 'life-travel-excursion'); ?>',
            multiple: false,
            library: {
                type: 'image'
            },
            button: {
                text: '<?php _e('Utiliser cette image', 'life-travel-excursion'); ?>'
            }
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            logoField.val(attachment.url);
            
            previewDiv.html('<img src="' + attachment.url + '" alt="Logo">');
        });
        
        frame.open();
    });
});
</script>
