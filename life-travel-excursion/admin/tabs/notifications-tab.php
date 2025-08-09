<?php
/**
 * Onglet des paramètres de notifications de Life Travel Excursion
 * 
 * @package Life_Travel_Excursion
 * @since 2.0.0
 */

// Sortie directe interdite
if (!defined('ABSPATH')) {
    exit;
}

// Récupérer les options enregistrées
$options = get_option('life_travel_notification_options', array());

// Valeurs par défaut
$defaults = array(
    // Paramètres Email
    'email_enabled' => 'yes',
    'email_sender_name' => get_bloginfo('name'),
    'email_sender_email' => get_option('admin_email'),
    'email_reply_to' => get_option('admin_email'),
    
    // Paramètres SMS
    'sms_enabled' => 'yes',
    'sms_provider' => 'twilio',
    'twilio_sid' => '',
    'twilio_token' => '',
    'twilio_phone' => '',
    
    // Paramètres WhatsApp
    'whatsapp_enabled' => 'yes',
    'whatsapp_provider' => 'twilio',
    'whatsapp_twilio_sid' => '',
    'whatsapp_twilio_token' => '',
    'whatsapp_twilio_phone' => '',
    
    // Notifications administrateur
    'admin_notifications_enabled' => 'yes',
    'admin_notification_methods' => array('email'),
    'admin_recipients' => array(),
    'notify_on_new_booking' => 'yes',
    'notify_on_payment' => 'yes',
    'notify_on_cancellation' => 'yes',
    'notify_daily_summary' => 'yes',
    'daily_summary_time' => '06:00',
    
    // Options de journalisation
    'log_notifications' => 'yes',
    'log_expiration' => 30, // jours
    
    // Options pour les rappels
    'reminders_enabled' => 'yes',
    'reminder_before_days' => 3,
    'reminder_methods' => array('email', 'sms'),
    
    // Options de repli (fallback)
    'fallback_enabled' => 'yes',
    'fallback_threshold' => 3, // tentatives
    'fallback_interval' => 30, // minutes
);

// Fusionner avec les valeurs par défaut
$options = wp_parse_args($options, $defaults);

// Traiter l'enregistrement du formulaire
if (isset($_POST['life_travel_save_notifications'])) {
    check_admin_referer('life_travel_notifications_nonce');
    
    // Validation et assainissement des entrées - paramètres Email
    $options['email_enabled'] = isset($_POST['email_enabled']) ? 'yes' : 'no';
    $options['email_sender_name'] = sanitize_text_field($_POST['email_sender_name']);
    $options['email_sender_email'] = sanitize_email($_POST['email_sender_email']);
    $options['email_reply_to'] = sanitize_email($_POST['email_reply_to']);
    
    // Validation et assainissement des entrées - paramètres SMS
    $options['sms_enabled'] = isset($_POST['sms_enabled']) ? 'yes' : 'no';
    $options['sms_provider'] = sanitize_text_field($_POST['sms_provider']);
    $options['twilio_sid'] = sanitize_text_field($_POST['twilio_sid']);
    
    // Ne pas écraser les tokens s'ils sont vides
    if (!empty($_POST['twilio_token'])) {
        $options['twilio_token'] = sanitize_text_field($_POST['twilio_token']);
    }
    
    $options['twilio_phone'] = sanitize_text_field($_POST['twilio_phone']);
    
    // Validation et assainissement des entrées - paramètres WhatsApp
    $options['whatsapp_enabled'] = isset($_POST['whatsapp_enabled']) ? 'yes' : 'no';
    $options['whatsapp_provider'] = sanitize_text_field($_POST['whatsapp_provider']);
    
    // Si utilisation du même compte Twilio, copier les identifiants
    if (isset($_POST['use_same_twilio']) && $_POST['use_same_twilio'] === 'yes') {
        $options['whatsapp_twilio_sid'] = $options['twilio_sid'];
        $options['whatsapp_twilio_token'] = $options['twilio_token'];
    } else {
        $options['whatsapp_twilio_sid'] = sanitize_text_field($_POST['whatsapp_twilio_sid']);
        if (!empty($_POST['whatsapp_twilio_token'])) {
            $options['whatsapp_twilio_token'] = sanitize_text_field($_POST['whatsapp_twilio_token']);
        }
    }
    
    $options['whatsapp_twilio_phone'] = sanitize_text_field($_POST['whatsapp_twilio_phone']);
    
    // Validation et assainissement des entrées - notifications administrateur
    $options['admin_notifications_enabled'] = isset($_POST['admin_notifications_enabled']) ? 'yes' : 'no';
    $options['admin_notification_methods'] = isset($_POST['admin_notification_methods']) ? array_map('sanitize_text_field', $_POST['admin_notification_methods']) : array();
    
    // Traitement des destinataires administrateurs
    $admin_recipients = array();
    if (isset($_POST['admin_recipient_email']) && is_array($_POST['admin_recipient_email'])) {
        foreach ($_POST['admin_recipient_email'] as $key => $email) {
            if (empty($email)) continue;
            
            $phone = isset($_POST['admin_recipient_phone'][$key]) ? sanitize_text_field($_POST['admin_recipient_phone'][$key]) : '';
            $methods = isset($_POST['admin_recipient_methods'][$key]) ? array_map('sanitize_text_field', $_POST['admin_recipient_methods'][$key]) : array('email');
            
            $admin_recipients[] = array(
                'email' => sanitize_email($email),
                'phone' => $phone,
                'methods' => $methods
            );
        }
    }
    $options['admin_recipients'] = $admin_recipients;
    
    $options['notify_on_new_booking'] = isset($_POST['notify_on_new_booking']) ? 'yes' : 'no';
    $options['notify_on_payment'] = isset($_POST['notify_on_payment']) ? 'yes' : 'no';
    $options['notify_on_cancellation'] = isset($_POST['notify_on_cancellation']) ? 'yes' : 'no';
    $options['notify_daily_summary'] = isset($_POST['notify_daily_summary']) ? 'yes' : 'no';
    $options['daily_summary_time'] = sanitize_text_field($_POST['daily_summary_time']);
    
    // Validation et assainissement des entrées - options de journalisation
    $options['log_notifications'] = isset($_POST['log_notifications']) ? 'yes' : 'no';
    $options['log_expiration'] = absint($_POST['log_expiration']);
    
    // Validation et assainissement des entrées - options pour les rappels
    $options['reminders_enabled'] = isset($_POST['reminders_enabled']) ? 'yes' : 'no';
    $options['reminder_before_days'] = absint($_POST['reminder_before_days']);
    $options['reminder_methods'] = isset($_POST['reminder_methods']) ? array_map('sanitize_text_field', $_POST['reminder_methods']) : array();
    
    // Validation et assainissement des entrées - options de repli
    $options['fallback_enabled'] = isset($_POST['fallback_enabled']) ? 'yes' : 'no';
    $options['fallback_threshold'] = absint($_POST['fallback_threshold']);
    $options['fallback_interval'] = absint($_POST['fallback_interval']);
    
    // Validation supplémentaire
    if ($options['log_expiration'] < 1) {
        $options['log_expiration'] = 30;
    }
    
    if ($options['reminder_before_days'] < 1) {
        $options['reminder_before_days'] = 3;
    }
    
    if ($options['fallback_threshold'] < 1) {
        $options['fallback_threshold'] = 3;
    }
    
    if ($options['fallback_interval'] < 5) {
        $options['fallback_interval'] = 30;
    }
    
    // Enregistrer les options
    update_option('life_travel_notification_options', $options);
    
    // Afficher un message de succès
    add_settings_error(
        'life_travel_notification_settings',
        'settings_updated',
        __('Paramètres de notification mis à jour avec succès !', 'life-travel-excursion'),
        'updated'
    );
}

// Afficher les erreurs/messages de succès
settings_errors('life_travel_notification_settings');
?>

<form method="post" action="" class="life-travel-admin-form">
    <?php wp_nonce_field('life_travel_notifications_nonce'); ?>
    
    <div class="life-travel-admin-card">
        <h3 class="life-travel-card-header">
            <span class="dashicons dashicons-email"></span> 
            <?php _e('Configuration Email', 'life-travel-excursion'); ?>
        </h3>
        <div class="life-travel-card-body">
            <div class="life-travel-form-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="email_enabled" value="yes" 
                        <?php checked($options['email_enabled'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Activer les notifications par email', 'life-travel-excursion'); ?>
                </label>
            </div>
            
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="email_sender_name">
                        <?php _e('Nom de l\'expéditeur', 'life-travel-excursion'); ?>
                    </label>
                    <input type="text" id="email_sender_name" name="email_sender_name" 
                           value="<?php echo esc_attr($options['email_sender_name']); ?>">
                </div>
                
                <div class="life-travel-form-field">
                    <label for="email_sender_email">
                        <?php _e('Email de l\'expéditeur', 'life-travel-excursion'); ?>
                    </label>
                    <input type="email" id="email_sender_email" name="email_sender_email" 
                           value="<?php echo esc_attr($options['email_sender_email']); ?>">
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <label for="email_reply_to">
                    <?php _e('Adresse de réponse', 'life-travel-excursion'); ?>
                </label>
                <input type="email" id="email_reply_to" name="email_reply_to" 
                       value="<?php echo esc_attr($options['email_reply_to']); ?>">
                <p class="description">
                    <?php _e('Adresse email où les clients peuvent répondre.', 'life-travel-excursion'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Configuration SMS -->
    <div class="life-travel-admin-card">
        <h3 class="life-travel-card-header">
            <span class="dashicons dashicons-smartphone"></span> 
            <?php _e('Configuration SMS', 'life-travel-excursion'); ?>
        </h3>
        <div class="life-travel-card-body">
            <div class="life-travel-form-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="sms_enabled" value="yes" 
                        <?php checked($options['sms_enabled'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Activer les notifications par SMS', 'life-travel-excursion'); ?>
                </label>
                <p class="description">
                    <?php _e('Envoyer des notifications par SMS aux clients et administrateurs.', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="life-travel-form-field">
                <label for="sms_provider">
                    <?php _e('Fournisseur SMS', 'life-travel-excursion'); ?>
                </label>
                <select id="sms_provider" name="sms_provider">
                    <option value="twilio" <?php selected($options['sms_provider'], 'twilio'); ?>>
                        <?php _e('Twilio', 'life-travel-excursion'); ?>
                    </option>
                    <option value="custom" <?php selected($options['sms_provider'], 'custom'); ?>>
                        <?php _e('API personnalisée', 'life-travel-excursion'); ?>
                    </option>
                </select>
            </div>
            
            <div id="twilio_settings" class="life-travel-provider-settings">
                <div class="life-travel-form-field">
                    <label for="twilio_sid">
                        <?php _e('Twilio Account SID', 'life-travel-excursion'); ?>
                    </label>
                    <input type="text" id="twilio_sid" name="twilio_sid" 
                           value="<?php echo esc_attr($options['twilio_sid']); ?>"
                           placeholder="AC...">
                </div>
                
                <div class="life-travel-form-field">
                    <label for="twilio_token">
                        <?php _e('Twilio Auth Token', 'life-travel-excursion'); ?>
                    </label>
                    <input type="password" id="twilio_token" name="twilio_token" 
                           placeholder="<?php echo empty($options['twilio_token']) ? '' : '••••••••••••'; ?>">
                    <p class="description">
                        <?php _e('Laissez vide pour conserver le token existant.', 'life-travel-excursion'); ?>
                    </p>
                </div>
                
                <div class="life-travel-form-field">
                    <label for="twilio_phone">
                        <?php _e('Numéro de téléphone Twilio', 'life-travel-excursion'); ?>
                    </label>
                    <input type="text" id="twilio_phone" name="twilio_phone" 
                           value="<?php echo esc_attr($options['twilio_phone']); ?>"
                           placeholder="+237...">
                    <p class="description">
                        <?php _e('Inclure l\'indicatif pays (ex: +237).', 'life-travel-excursion'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Configuration WhatsApp -->
    <div class="life-travel-admin-card">
        <h3 class="life-travel-card-header">
            <span class="dashicons dashicons-whatsapp"></span> 
            <?php _e('Configuration WhatsApp', 'life-travel-excursion'); ?>
        </h3>
        <div class="life-travel-card-body">
            <div class="life-travel-form-field">
                <label class="life-travel-toggle-switch">
                    <input type="checkbox" name="whatsapp_enabled" value="yes" 
                        <?php checked($options['whatsapp_enabled'], 'yes'); ?>>
                    <span class="life-travel-toggle-slider"></span>
                    <?php _e('Activer les notifications WhatsApp', 'life-travel-excursion'); ?>
                </label>
                <p class="description">
                    <?php _e('Envoyer des notifications via WhatsApp (populaire au Cameroun).', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="life-travel-form-field">
                <label for="whatsapp_provider">
                    <?php _e('Fournisseur WhatsApp', 'life-travel-excursion'); ?>
                </label>
                <select id="whatsapp_provider" name="whatsapp_provider">
                    <option value="twilio" <?php selected($options['whatsapp_provider'], 'twilio'); ?>>
                        <?php _e('Twilio', 'life-travel-excursion'); ?>
                    </option>
                </select>
            </div>
            
            <div class="life-travel-form-field">
                <label class="life-travel-checkbox-label">
                    <input type="checkbox" name="use_same_twilio" value="yes" id="use_same_twilio" 
                           <?php checked($options['whatsapp_twilio_sid'] === $options['twilio_sid'] && !empty($options['twilio_sid']), true); ?>>
                    <?php _e('Utiliser les mêmes identifiants Twilio que pour les SMS', 'life-travel-excursion'); ?>
                </label>
            </div>
            
            <div id="whatsapp_twilio_settings" class="life-travel-provider-settings">
                <div class="life-travel-form-field">
                    <label for="whatsapp_twilio_sid">
                        <?php _e('Twilio Account SID pour WhatsApp', 'life-travel-excursion'); ?>
                    </label>
                    <input type="text" id="whatsapp_twilio_sid" name="whatsapp_twilio_sid" 
                           value="<?php echo esc_attr($options['whatsapp_twilio_sid']); ?>"
                           placeholder="AC...">
                </div>
                
                <div class="life-travel-form-field">
                    <label for="whatsapp_twilio_token">
                        <?php _e('Twilio Auth Token pour WhatsApp', 'life-travel-excursion'); ?>
                    </label>
                    <input type="password" id="whatsapp_twilio_token" name="whatsapp_twilio_token" 
                           placeholder="<?php echo empty($options['whatsapp_twilio_token']) ? '' : '••••••••••••'; ?>">
                    <p class="description">
                        <?php _e('Laissez vide pour conserver le token existant.', 'life-travel-excursion'); ?>
                    </p>
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <label for="whatsapp_twilio_phone">
                    <?php _e('Numéro WhatsApp Business', 'life-travel-excursion'); ?>
                </label>
                <input type="text" id="whatsapp_twilio_phone" name="whatsapp_twilio_phone" 
                       value="<?php echo esc_attr($options['whatsapp_twilio_phone']); ?>"
                       placeholder="+237...">
                <p class="description">
                    <?php _e('Doit être un numéro WhatsApp Business vérifié par Twilio.', 'life-travel-excursion'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Le reste de l'onglet notifications continue dans notifications-tab-part2.php -->
    <?php require_once LIFE_TRAVEL_PLUGIN_DIR . 'admin/tabs/notifications-tab-part2.php'; ?>
</form>

<script>
jQuery(document).ready(function($) {
    // Gestion du fournisseur SMS
    $('#sms_provider').on('change', function() {
        const provider = $(this).val();
        $('.life-travel-provider-settings').hide();
        $('#' + provider + '_settings').show();
    }).trigger('change');
    
    // Gestion des identifiants WhatsApp
    $('#use_same_twilio').on('change', function() {
        if ($(this).is(':checked')) {
            $('#whatsapp_twilio_settings').slideUp(200);
            // Copier les valeurs des champs SMS vers WhatsApp
            $('#whatsapp_twilio_sid').val($('#twilio_sid').val());
            $('#whatsapp_twilio_phone').val($('#twilio_phone').val());
        } else {
            $('#whatsapp_twilio_settings').slideDown(200);
        }
    }).trigger('change');
    
    // Synchroniser les champs Twilio si l'option "utiliser les mêmes identifiants" est cochée
    $('#twilio_sid').on('input', function() {
        if ($('#use_same_twilio').is(':checked')) {
            $('#whatsapp_twilio_sid').val($(this).val());
        }
    });
    
    $('#twilio_phone').on('input', function() {
        if ($('#use_same_twilio').is(':checked')) {
            $('#whatsapp_twilio_phone').val($(this).val());
        }
    });
});
</script>
