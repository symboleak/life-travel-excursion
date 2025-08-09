<?php
/**
 * Onglet des paramètres de paiement de Life Travel Excursion
 * 
 * @package Life_Travel_Excursion
 * @since 2.0.0
 */

// Sortie directe interdite
if (!defined('ABSPATH')) {
    exit;
}

// Récupérer les options enregistrées
$options = get_option('life_travel_payment_options', array());

// Valeurs par défaut
$defaults = array(
    // Options IwomiPay générales
    'iwomipay_enabled' => 'yes',
    'iwomipay_environment' => 'sandbox',
    'iwomipay_merchant_key' => '',
    'iwomipay_merchant_secret' => '',
    'iwomipay_callback_url' => home_url('wc-api/iwomipay-callback'),
    
    // Options IwomiPay Card
    'iwomipay_card_enabled' => 'yes',
    'iwomipay_card_title' => __('Paiement par carte bancaire', 'life-travel-excursion'),
    'iwomipay_card_description' => __('Payez en toute sécurité avec votre carte bancaire (Visa, Mastercard, etc.)', 'life-travel-excursion'),
    
    // Options IwomiPay Mobile Money
    'iwomipay_momo_enabled' => 'yes',
    'iwomipay_momo_title' => __('Paiement par Mobile Money', 'life-travel-excursion'),
    'iwomipay_momo_description' => __('Payez via MTN Mobile Money, Orange Money ou autres services locaux.', 'life-travel-excursion'),
    'iwomipay_momo_operators' => array('mtn', 'orange', 'yoomee'),
    
    // Options IwomiPay Orange Money spécifiques
    'iwomipay_orange_enabled' => 'yes',
    'iwomipay_orange_title' => __('Paiement par Orange Money', 'life-travel-excursion'),
    'iwomipay_orange_description' => __('Payez via Orange Money.', 'life-travel-excursion'),
    
    // Options générales de paiement
    'payment_success_message' => __('Merci pour votre paiement. Votre transaction a été complétée, et une confirmation a été envoyée à votre adresse email.', 'life-travel-excursion'),
    'payment_pending_message' => __('Merci pour votre commande. Nous attendons la confirmation de votre paiement.', 'life-travel-excursion'),
    'payment_failed_message' => __('Votre paiement a échoué. Veuillez réessayer ou contacter le support.', 'life-travel-excursion'),
    'enable_payment_reminders' => 'yes',
    'payment_reminder_delay' => 24, // heures
    'max_payment_attempts' => 3,
    'partial_payment_enabled' => 'no',
    'partial_payment_percentage' => 30,
);

// Fusionner avec les valeurs par défaut
$options = wp_parse_args($options, $defaults);

// Traiter l'enregistrement du formulaire
if (isset($_POST['life_travel_save_payment'])) {
    check_admin_referer('life_travel_payment_nonce');
    
    // Valider et sanitiser les entrées
    // IwomiPay général
    $options['iwomipay_enabled'] = isset($_POST['iwomipay_enabled']) ? 'yes' : 'no';
    $options['iwomipay_environment'] = sanitize_text_field($_POST['iwomipay_environment']);
    $options['iwomipay_merchant_key'] = sanitize_text_field($_POST['iwomipay_merchant_key']);
    
    // Ne pas écraser le secret s'il est vide (permet de ne pas avoir à le ressaisir à chaque fois)
    if (!empty($_POST['iwomipay_merchant_secret'])) {
        $options['iwomipay_merchant_secret'] = sanitize_text_field($_POST['iwomipay_merchant_secret']);
    }
    
    // IwomiPay Card
    $options['iwomipay_card_enabled'] = isset($_POST['iwomipay_card_enabled']) ? 'yes' : 'no';
    $options['iwomipay_card_title'] = sanitize_text_field($_POST['iwomipay_card_title']);
    $options['iwomipay_card_description'] = sanitize_textarea_field($_POST['iwomipay_card_description']);
    
    // IwomiPay Mobile Money
    $options['iwomipay_momo_enabled'] = isset($_POST['iwomipay_momo_enabled']) ? 'yes' : 'no';
    $options['iwomipay_momo_title'] = sanitize_text_field($_POST['iwomipay_momo_title']);
    $options['iwomipay_momo_description'] = sanitize_textarea_field($_POST['iwomipay_momo_description']);
    $options['iwomipay_momo_operators'] = isset($_POST['iwomipay_momo_operators']) ? array_map('sanitize_text_field', $_POST['iwomipay_momo_operators']) : array();
    
    // IwomiPay Orange Money
    $options['iwomipay_orange_enabled'] = isset($_POST['iwomipay_orange_enabled']) ? 'yes' : 'no';
    $options['iwomipay_orange_title'] = sanitize_text_field($_POST['iwomipay_orange_title']);
    $options['iwomipay_orange_description'] = sanitize_textarea_field($_POST['iwomipay_orange_description']);
    
    // Options générales
    $options['payment_success_message'] = sanitize_textarea_field($_POST['payment_success_message']);
    $options['payment_pending_message'] = sanitize_textarea_field($_POST['payment_pending_message']);
    $options['payment_failed_message'] = sanitize_textarea_field($_POST['payment_failed_message']);
    $options['enable_payment_reminders'] = isset($_POST['enable_payment_reminders']) ? 'yes' : 'no';
    $options['payment_reminder_delay'] = absint($_POST['payment_reminder_delay']);
    $options['max_payment_attempts'] = absint($_POST['max_payment_attempts']);
    $options['partial_payment_enabled'] = isset($_POST['partial_payment_enabled']) ? 'yes' : 'no';
    $options['partial_payment_percentage'] = intval($_POST['partial_payment_percentage']);
    
    // Validation supplémentaire
    if ($options['partial_payment_percentage'] < 10 || $options['partial_payment_percentage'] > 90) {
        $options['partial_payment_percentage'] = 30; // Valeur par défaut sécurisée
    }
    
    // Enregistrer les options
    update_option('life_travel_payment_options', $options);
    
    // Afficher un message de succès
    add_settings_error(
        'life_travel_payment_settings',
        'settings_updated',
        __('Paramètres de paiement mis à jour avec succès !', 'life-travel-excursion'),
        'updated'
    );
}

// Afficher les erreurs/messages de succès
settings_errors('life_travel_payment_settings');
?>

<form method="post" action="" class="life-travel-admin-form">
    <?php wp_nonce_field('life_travel_payment_nonce'); ?>
    
    <div class="life-travel-admin-card">
        <h3 class="life-travel-card-header">
            <span class="dashicons dashicons-money-alt"></span> 
            <?php _e('Configuration IwomiPay', 'life-travel-excursion'); ?>
        </h3>
        <div class="life-travel-card-body">
            <p class="life-travel-description">
                <?php _e('IwomiPay est une passerelle de paiement adaptée pour le Cameroun et l\'Afrique Centrale. Ces paramètres contrôlent toutes les méthodes de paiement IwomiPay.', 'life-travel-excursion'); ?>
            </p>
            
            <div class="life-travel-form-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="iwomipay_enabled" value="yes" 
                        <?php checked($options['iwomipay_enabled'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Activer IwomiPay', 'life-travel-excursion'); ?>
                </label>
                <p class="description">
                    <?php _e('Active ou désactive toutes les passerelles de paiement IwomiPay.', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="life-travel-form-field">
                <label for="iwomipay_environment">
                    <?php _e('Environnement', 'life-travel-excursion'); ?>
                </label>
                <select id="iwomipay_environment" name="iwomipay_environment">
                    <option value="sandbox" <?php selected($options['iwomipay_environment'], 'sandbox'); ?>>
                        <?php _e('Sandbox (Test)', 'life-travel-excursion'); ?>
                    </option>
                    <option value="production" <?php selected($options['iwomipay_environment'], 'production'); ?>>
                        <?php _e('Production', 'life-travel-excursion'); ?>
                    </option>
                </select>
                <p class="description">
                    <?php _e('Utilisez Sandbox pour les tests et Production pour les transactions réelles.', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="iwomipay_merchant_key">
                        <?php _e('Clé marchande', 'life-travel-excursion'); ?>
                        <span class="required">*</span>
                    </label>
                    <input type="text" id="iwomipay_merchant_key" name="iwomipay_merchant_key" 
                           value="<?php echo esc_attr($options['iwomipay_merchant_key']); ?>" 
                           placeholder="iwomi_xxx" required>
                </div>
                
                <div class="life-travel-form-field">
                    <label for="iwomipay_merchant_secret">
                        <?php _e('Secret marchand', 'life-travel-excursion'); ?>
                        <span class="required">*</span>
                    </label>
                    <input type="password" id="iwomipay_merchant_secret" name="iwomipay_merchant_secret" 
                           placeholder="<?php echo empty($options['iwomipay_merchant_secret']) ? '' : '••••••••••••'; ?>">
                    <p class="description">
                        <?php _e('Laissez vide pour conserver le secret existant.', 'life-travel-excursion'); ?>
                    </p>
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <label for="iwomipay_callback_url">
                    <?php _e('URL de callback', 'life-travel-excursion'); ?>
                </label>
                <div class="life-travel-copy-field">
                    <input type="text" id="iwomipay_callback_url" value="<?php echo esc_url($options['iwomipay_callback_url']); ?>" readonly>
                    <button type="button" class="button life-travel-copy-button" data-target="iwomipay_callback_url">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>
                <p class="description">
                    <?php _e('Configurez cette URL dans votre tableau de bord IwomiPay.', 'life-travel-excursion'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <div class="life-travel-admin-card life-travel-payment-methods">
        <h3 class="life-travel-card-header">
            <span class="dashicons dashicons-credit-card"></span> 
            <?php _e('Méthodes de paiement', 'life-travel-excursion'); ?>
        </h3>
        <div class="life-travel-card-body">
            <!-- Paiement par carte -->
            <div class="life-travel-payment-method">
                <div class="life-travel-payment-method-header">
                    <div class="life-travel-payment-method-icon">
                        <span class="dashicons dashicons-credit-card"></span>
                    </div>
                    <div class="life-travel-payment-method-title">
                        <h4><?php _e('Paiement par carte bancaire', 'life-travel-excursion'); ?></h4>
                    </div>
                    <div class="life-travel-payment-method-toggle">
                        <label class="life-travel-toggle-switch">
                            <input type="checkbox" name="iwomipay_card_enabled" value="yes" 
                                <?php checked($options['iwomipay_card_enabled'], 'yes'); ?>>
                            <span class="life-travel-toggle-slider"></span>
                        </label>
                    </div>
                </div>
                
                <div class="life-travel-payment-method-details" id="iwomipay_card_details">
                    <div class="life-travel-form-field">
                        <label for="iwomipay_card_title">
                            <?php _e('Titre affiché', 'life-travel-excursion'); ?>
                        </label>
                        <input type="text" id="iwomipay_card_title" name="iwomipay_card_title" 
                               value="<?php echo esc_attr($options['iwomipay_card_title']); ?>">
                    </div>
                    
                    <div class="life-travel-form-field">
                        <label for="iwomipay_card_description">
                            <?php _e('Description', 'life-travel-excursion'); ?>
                        </label>
                        <textarea id="iwomipay_card_description" name="iwomipay_card_description" rows="2"><?php echo esc_textarea($options['iwomipay_card_description']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Paiement Mobile Money -->
            <div class="life-travel-payment-method">
                <div class="life-travel-payment-method-header">
                    <div class="life-travel-payment-method-icon">
                        <span class="dashicons dashicons-smartphone"></span>
                    </div>
                    <div class="life-travel-payment-method-title">
                        <h4><?php _e('Paiement Mobile Money', 'life-travel-excursion'); ?></h4>
                    </div>
                    <div class="life-travel-payment-method-toggle">
                        <label class="life-travel-toggle-switch">
                            <input type="checkbox" name="iwomipay_momo_enabled" value="yes" 
                                <?php checked($options['iwomipay_momo_enabled'], 'yes'); ?>>
                            <span class="life-travel-toggle-slider"></span>
                        </label>
                    </div>
                </div>
                
                <div class="life-travel-payment-method-details" id="iwomipay_momo_details">
                    <div class="life-travel-form-field">
                        <label for="iwomipay_momo_title">
                            <?php _e('Titre affiché', 'life-travel-excursion'); ?>
                        </label>
                        <input type="text" id="iwomipay_momo_title" name="iwomipay_momo_title" 
                               value="<?php echo esc_attr($options['iwomipay_momo_title']); ?>">
                    </div>
                    
                    <div class="life-travel-form-field">
                        <label for="iwomipay_momo_description">
                            <?php _e('Description', 'life-travel-excursion'); ?>
                        </label>
                        <textarea id="iwomipay_momo_description" name="iwomipay_momo_description" rows="2"><?php echo esc_textarea($options['iwomipay_momo_description']); ?></textarea>
                    </div>
                    
                    <div class="life-travel-form-field">
                        <label><?php _e('Opérateurs acceptés', 'life-travel-excursion'); ?></label>
                        <div class="life-travel-checkbox-group">
                            <?php 
                            $operators = array(
                                'mtn' => __('MTN Mobile Money', 'life-travel-excursion'),
                                'orange' => __('Orange Money', 'life-travel-excursion'),
                                'yoomee' => __('Yoomee Money', 'life-travel-excursion'),
                                'nexttel' => __('Nexttel Possa', 'life-travel-excursion')
                            );
                            
                            foreach ($operators as $op_id => $op_name) : ?>
                                <label class="life-travel-checkbox-label">
                                    <input type="checkbox" name="iwomipay_momo_operators[]" value="<?php echo esc_attr($op_id); ?>" 
                                        <?php checked(in_array($op_id, $options['iwomipay_momo_operators']), true); ?>>
                                    <?php echo esc_html($op_name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Paiement Orange Money spécifique -->
            <div class="life-travel-payment-method">
                <div class="life-travel-payment-method-header">
                    <div class="life-travel-payment-method-icon">
                        <span class="dashicons dashicons-smartphone"></span>
                    </div>
                    <div class="life-travel-payment-method-title">
                        <h4><?php _e('Paiement Orange Money (Dédié)', 'life-travel-excursion'); ?></h4>
                    </div>
                    <div class="life-travel-payment-method-toggle">
                        <label class="life-travel-toggle-switch">
                            <input type="checkbox" name="iwomipay_orange_enabled" value="yes" 
                                <?php checked($options['iwomipay_orange_enabled'], 'yes'); ?>>
                            <span class="life-travel-toggle-slider"></span>
                        </label>
                    </div>
                </div>
                
                <div class="life-travel-payment-method-details" id="iwomipay_orange_details">
                    <div class="life-travel-form-field">
                        <label for="iwomipay_orange_title">
                            <?php _e('Titre affiché', 'life-travel-excursion'); ?>
                        </label>
                        <input type="text" id="iwomipay_orange_title" name="iwomipay_orange_title" 
                               value="<?php echo esc_attr($options['iwomipay_orange_title']); ?>">
                    </div>
                    
                    <div class="life-travel-form-field">
                        <label for="iwomipay_orange_description">
                            <?php _e('Description', 'life-travel-excursion'); ?>
                        </label>
                        <textarea id="iwomipay_orange_description" name="iwomipay_orange_description" rows="2"><?php echo esc_textarea($options['iwomipay_orange_description']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="life-travel-admin-card">
        <h3 class="life-travel-card-header">
            <span class="dashicons dashicons-admin-settings"></span> 
            <?php _e('Paramètres avancés', 'life-travel-excursion'); ?>
        </h3>
        <div class="life-travel-card-body">
            <div class="life-travel-form-field">
                <label for="payment_success_message">
                    <?php _e('Message de succès', 'life-travel-excursion'); ?>
                </label>
                <textarea id="payment_success_message" name="payment_success_message" rows="2"><?php echo esc_textarea($options['payment_success_message']); ?></textarea>
            </div>
            
            <div class="life-travel-form-field">
                <label for="payment_pending_message">
                    <?php _e('Message de paiement en attente', 'life-travel-excursion'); ?>
                </label>
                <textarea id="payment_pending_message" name="payment_pending_message" rows="2"><?php echo esc_textarea($options['payment_pending_message']); ?></textarea>
            </div>
            
            <div class="life-travel-form-field">
                <label for="payment_failed_message">
                    <?php _e('Message d\'échec', 'life-travel-excursion'); ?>
                </label>
                <textarea id="payment_failed_message" name="payment_failed_message" rows="2"><?php echo esc_textarea($options['payment_failed_message']); ?></textarea>
            </div>
            
            <div class="life-travel-form-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="enable_payment_reminders" value="yes" 
                        <?php checked($options['enable_payment_reminders'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Activer les rappels de paiement', 'life-travel-excursion'); ?>
                </label>
                <p class="description">
                    <?php _e('Envoie des rappels automatiques pour les paiements en attente.', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="life-travel-form-row payment-reminder-options" id="paymentReminderOptions">
                <div class="life-travel-form-field">
                    <label for="payment_reminder_delay">
                        <?php _e('Délai entre les rappels', 'life-travel-excursion'); ?>
                    </label>
                    <div class="life-travel-input-group">
                        <input type="number" id="payment_reminder_delay" name="payment_reminder_delay" 
                               value="<?php echo intval($options['payment_reminder_delay']); ?>" min="1" max="72">
                        <span class="life-travel-input-suffix">
                            <?php _e('heures', 'life-travel-excursion'); ?>
                        </span>
                    </div>
                </div>
                
                <div class="life-travel-form-field">
                    <label for="max_payment_attempts">
                        <?php _e('Nombre maximum de tentatives', 'life-travel-excursion'); ?>
                    </label>
                    <input type="number" id="max_payment_attempts" name="max_payment_attempts" 
                           value="<?php echo intval($options['max_payment_attempts']); ?>" min="1" max="10">
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="partial_payment_enabled" value="yes" 
                        <?php checked($options['partial_payment_enabled'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Activer le paiement partiel', 'life-travel-excursion'); ?>
                </label>
                <p class="description">
                    <?php _e('Permet aux clients de payer un acompte puis de régler le solde plus tard.', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="life-travel-form-field partial-payment-options" id="partialPaymentOptions">
                <label for="partial_payment_percentage">
                    <?php _e('Pourcentage d\'acompte', 'life-travel-excursion'); ?>
                </label>
                <div class="life-travel-input-group">
                    <input type="number" id="partial_payment_percentage" name="partial_payment_percentage" 
                           value="<?php echo intval($options['partial_payment_percentage']); ?>" min="10" max="90">
                    <span class="life-travel-input-suffix">%</span>
                </div>
                <p class="description">
                    <?php _e('Montant minimum à payer en pourcentage du total (entre 10% et 90%).', 'life-travel-excursion'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <div class="life-travel-admin-actions">
        <button type="submit" name="life_travel_save_payment" class="button button-primary">
            <span class="dashicons dashicons-saved"></span>
            <?php _e('Enregistrer les paramètres de paiement', 'life-travel-excursion'); ?>
        </button>
        
        <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=checkout')); ?>" class="button" target="_blank">
            <span class="dashicons dashicons-external"></span>
            <?php _e('Paramètres WooCommerce', 'life-travel-excursion'); ?>
        </a>
    </div>
</form>

<script>
jQuery(document).ready(function($) {
    // Gestion des toggles des méthodes de paiement
    $('.life-travel-payment-method-toggle input[type="checkbox"]').change(function() {
        const detailsId = '#' + $(this).attr('name') + '_details';
        if ($(this).is(':checked')) {
            $(detailsId).slideDown();
        } else {
            $(detailsId).slideUp();
        }
    }).trigger('change');
    
    // Gestion du toggle des rappels de paiement
    $('input[name="enable_payment_reminders"]').change(function() {
        if ($(this).is(':checked')) {
            $('#paymentReminderOptions').slideDown();
        } else {
            $('#paymentReminderOptions').slideUp();
        }
    }).trigger('change');
    
    // Gestion du toggle du paiement partiel
    $('input[name="partial_payment_enabled"]').change(function() {
        if ($(this).is(':checked')) {
            $('#partialPaymentOptions').slideDown();
        } else {
            $('#partialPaymentOptions').slideUp();
        }
    }).trigger('change');
    
    // Fonction de copie dans le presse-papier
    $('.life-travel-copy-button').click(function() {
        const targetId = $(this).data('target');
        const copyText = document.getElementById(targetId);
        
        copyText.select();
        copyText.setSelectionRange(0, 99999); // Pour mobile
        
        try {
            const success = document.execCommand('copy');
            if (success) {
                // Animation de succès
                $(this).find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
                setTimeout(() => {
                    $(this).find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
                }, 1500);
            }
        } catch (err) {
            console.error('Erreur lors de la copie:', err);
        }
    });
});
</script>
