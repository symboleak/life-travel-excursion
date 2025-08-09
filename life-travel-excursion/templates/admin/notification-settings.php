<?php
/**
 * Template pour la page de paramètres des notifications
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

// S'assurer que l'utilisateur a les permissions nécessaires
if (!current_user_can('manage_options')) {
    wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.', 'life-travel-excursion'));
}

// Récupérer les destinataires configurés
$recipients = get_option('lte_notification_recipients', []);

// Récupérer les paramètres de notification
$enable_order_notifications = get_option('lte_enable_order_notifications', 'yes');
$enable_status_notifications = get_option('lte_enable_status_notifications', 'yes');

// Récupérer les paramètres Twilio
$twilio_sid = get_option('lte_twilio_sid', '');
$twilio_token = get_option('lte_twilio_token', '');
$twilio_phone = get_option('lte_twilio_phone', '');
$twilio_whatsapp = get_option('lte_twilio_whatsapp', '');

// Réinitialiser les destinataires si nécessaire
if (isset($_POST['lte_reset_recipients']) && $_POST['lte_reset_recipients'] === '1' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'lte_reset_recipients')) {
    $recipients = [];
    $admins = get_users(['role' => 'administrator']);
    foreach ($admins as $admin) {
        $recipients[] = [
            'user_id' => $admin->ID,
            'email' => $admin->user_email,
            'notify_email' => true,
            'notify_whatsapp' => false,
            'phone' => get_user_meta($admin->ID, '_lte_phone', true)
        ];
    }
    
    update_option('lte_notification_recipients', $recipients);
    echo '<div class="notice notice-success"><p>' . __('Liste des destinataires réinitialisée avec succès.', 'life-travel-excursion') . '</p></div>';
}

// Vérifier si la bibliothèque PhpSpreadsheet est disponible
$spreadsheet_available = class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet');
?>

<div class="wrap">
    <h1><?php esc_html_e('Paramètres des notifications Life Travel', 'life-travel-excursion'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php esc_html_e('Cette page vous permet de configurer les notifications automatiques pour les administrateurs concernant les réservations d\'excursions.', 'life-travel-excursion'); ?></p>
    </div>
    
    <?php if (!$spreadsheet_available) : ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('La bibliothèque PhpSpreadsheet n\'est pas disponible. L\'export Excel ne fonctionnera pas correctement. Veuillez installer la dépendance via Composer.', 'life-travel-excursion'); ?></p>
            <p><code>composer require phpoffice/phpspreadsheet</code></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="options.php">
        <?php settings_fields('lte_notification_options'); ?>
        
        <div class="lte-admin-tabs">
            <div class="lte-tab-nav">
                <a href="#general" class="active"><?php esc_html_e('Général', 'life-travel-excursion'); ?></a>
                <a href="#recipients"><?php esc_html_e('Destinataires', 'life-travel-excursion'); ?></a>
                <a href="#twilio"><?php esc_html_e('Configuration Twilio', 'life-travel-excursion'); ?></a>
                <a href="#test"><?php esc_html_e('Test', 'life-travel-excursion'); ?></a>
            </div>
            
            <div class="lte-tab-content">
                <div id="general" class="lte-tab-pane active">
                    <h2><?php esc_html_e('Paramètres généraux', 'life-travel-excursion'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Notifications de nouvelles réservations', 'life-travel-excursion'); ?></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php esc_html_e('Notifications de nouvelles réservations', 'life-travel-excursion'); ?></legend>
                                    <label>
                                        <input type="radio" name="lte_enable_order_notifications" value="yes" <?php checked($enable_order_notifications, 'yes'); ?>>
                                        <?php esc_html_e('Activées', 'life-travel-excursion'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" name="lte_enable_order_notifications" value="no" <?php checked($enable_order_notifications, 'no'); ?>>
                                        <?php esc_html_e('Désactivées', 'life-travel-excursion'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('Envoyer des notifications lors de l\'enregistrement d\'une nouvelle réservation d\'excursion.', 'life-travel-excursion'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Notifications de changement de statut', 'life-travel-excursion'); ?></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php esc_html_e('Notifications de changement de statut', 'life-travel-excursion'); ?></legend>
                                    <label>
                                        <input type="radio" name="lte_enable_status_notifications" value="yes" <?php checked($enable_status_notifications, 'yes'); ?>>
                                        <?php esc_html_e('Activées', 'life-travel-excursion'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" name="lte_enable_status_notifications" value="no" <?php checked($enable_status_notifications, 'no'); ?>>
                                        <?php esc_html_e('Désactivées', 'life-travel-excursion'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('Envoyer des notifications lors d\'un changement de statut d\'une réservation d\'excursion.', 'life-travel-excursion'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div id="recipients" class="lte-tab-pane">
                    <h2><?php esc_html_e('Configuration des destinataires', 'life-travel-excursion'); ?></h2>
                    
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Utilisateur', 'life-travel-excursion'); ?></th>
                                <th><?php esc_html_e('Email', 'life-travel-excursion'); ?></th>
                                <th><?php esc_html_e('Notification Email', 'life-travel-excursion'); ?></th>
                                <th><?php esc_html_e('Téléphone', 'life-travel-excursion'); ?></th>
                                <th><?php esc_html_e('Notification WhatsApp', 'life-travel-excursion'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="recipient-list">
                            <?php if (!empty($recipients)) : ?>
                                <?php foreach ($recipients as $index => $recipient) : ?>
                                    <tr class="recipient-row">
                                        <td>
                                            <?php $user = get_user_by('id', $recipient['user_id']); ?>
                                            <?php echo $user ? esc_html($user->display_name) : esc_html__('Utilisateur inconnu', 'life-travel-excursion'); ?>
                                            <input type="hidden" name="lte_notification_recipients[<?php echo esc_attr($index); ?>][user_id]" value="<?php echo esc_attr($recipient['user_id']); ?>">
                                        </td>
                                        <td>
                                            <input type="email" name="lte_notification_recipients[<?php echo esc_attr($index); ?>][email]" value="<?php echo esc_attr($recipient['email']); ?>" class="regular-text">
                                        </td>
                                        <td>
                                            <input type="checkbox" name="lte_notification_recipients[<?php echo esc_attr($index); ?>][notify_email]" value="1" <?php checked(!empty($recipient['notify_email'])); ?>>
                                        </td>
                                        <td>
                                            <input type="text" name="lte_notification_recipients[<?php echo esc_attr($index); ?>][phone]" value="<?php echo esc_attr(isset($recipient['phone']) ? $recipient['phone'] : ''); ?>" class="regular-text" placeholder="+237xxxxxxxx">
                                        </td>
                                        <td>
                                            <input type="checkbox" name="lte_notification_recipients[<?php echo esc_attr($index); ?>][notify_whatsapp]" value="1" <?php checked(!empty($recipient['notify_whatsapp'])); ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5"><?php esc_html_e('Aucun destinataire configuré. Utilisez le bouton "Réinitialiser les destinataires" pour ajouter tous les administrateurs.', 'life-travel-excursion'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <form method="post">
                            <?php wp_nonce_field('lte_reset_recipients'); ?>
                            <input type="hidden" name="lte_reset_recipients" value="1">
                            <button type="submit" class="button button-secondary"><?php esc_html_e('Réinitialiser les destinataires', 'life-travel-excursion'); ?></button>
                        </form>
                    </p>
                </div>
                
                <div id="twilio" class="lte-tab-pane">
                    <h2><?php esc_html_e('Configuration Twilio pour les notifications', 'life-travel-excursion'); ?></h2>
                    
                    <p><?php esc_html_e('Configurez ces paramètres pour permettre l\'envoi de notifications par WhatsApp aux administrateurs.', 'life-travel-excursion'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Twilio Account SID', 'life-travel-excursion'); ?></th>
                            <td>
                                <input type="text" name="lte_twilio_sid" value="<?php echo esc_attr($twilio_sid); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('L\'identifiant de votre compte Twilio (Account SID).', 'life-travel-excursion'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Twilio Auth Token', 'life-travel-excursion'); ?></th>
                            <td>
                                <input type="password" name="lte_twilio_token" value="<?php echo esc_attr($twilio_token); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('Le jeton d\'authentification de votre compte Twilio (Auth Token).', 'life-travel-excursion'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Numéro de téléphone Twilio', 'life-travel-excursion'); ?></th>
                            <td>
                                <input type="text" name="lte_twilio_phone" value="<?php echo esc_attr($twilio_phone); ?>" class="regular-text" placeholder="+12345678901">
                                <p class="description"><?php esc_html_e('Le numéro de téléphone Twilio pour l\'envoi des SMS. Format international complet avec +.', 'life-travel-excursion'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Numéro WhatsApp Twilio', 'life-travel-excursion'); ?></th>
                            <td>
                                <input type="text" name="lte_twilio_whatsapp" value="<?php echo esc_attr($twilio_whatsapp); ?>" class="regular-text" placeholder="+12345678901">
                                <p class="description"><?php esc_html_e('Le numéro de téléphone Twilio configuré pour WhatsApp. Format international complet avec +.', 'life-travel-excursion'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div id="test" class="lte-tab-pane">
                    <h2><?php esc_html_e('Test des notifications', 'life-travel-excursion'); ?></h2>
                    
                    <p><?php esc_html_e('Utilisez ces options pour tester l\'envoi de notifications aux administrateurs.', 'life-travel-excursion'); ?></p>
                    
                    <p><button type="button" id="lte-test-email" class="button button-secondary"><?php esc_html_e('Tester l\'envoi d\'email', 'life-travel-excursion'); ?></button></p>
                    
                    <p><button type="button" id="lte-test-whatsapp" class="button button-secondary"><?php esc_html_e('Tester l\'envoi WhatsApp', 'life-travel-excursion'); ?></button></p>
                    
                    <p><button type="button" id="lte-export-test" class="button button-secondary"><?php esc_html_e('Tester l\'export Excel', 'life-travel-excursion'); ?></button></p>
                    
                    <div id="lte-test-result" class="notice notice-info" style="display: none;">
                        <p></p>
                    </div>
                    
                    <script>
                    jQuery(document).ready(function($) {
                        // Tester l'envoi d'email
                        $('#lte-test-email').on('click', function() {
                            var button = $(this);
                            button.prop('disabled', true).text('<?php esc_html_e('Envoi en cours...', 'life-travel-excursion'); ?>');
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'lte_test_notification_email',
                                    nonce: '<?php echo wp_create_nonce('lte_test_notification'); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        $('#lte-test-result').removeClass('notice-error').addClass('notice-success').show().find('p').text(response.data.message);
                                    } else {
                                        $('#lte-test-result').removeClass('notice-success').addClass('notice-error').show().find('p').text(response.data.message);
                                    }
                                    button.prop('disabled', false).text('<?php esc_html_e('Tester l\'envoi d\'email', 'life-travel-excursion'); ?>');
                                },
                                error: function() {
                                    $('#lte-test-result').removeClass('notice-success').addClass('notice-error').show().find('p').text('<?php esc_html_e('Erreur de communication avec le serveur.', 'life-travel-excursion'); ?>');
                                    button.prop('disabled', false).text('<?php esc_html_e('Tester l\'envoi d\'email', 'life-travel-excursion'); ?>');
                                }
                            });
                        });
                        
                        // Tester l'envoi WhatsApp
                        $('#lte-test-whatsapp').on('click', function() {
                            var button = $(this);
                            button.prop('disabled', true).text('<?php esc_html_e('Envoi en cours...', 'life-travel-excursion'); ?>');
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'lte_test_notification_whatsapp',
                                    nonce: '<?php echo wp_create_nonce('lte_test_notification'); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        $('#lte-test-result').removeClass('notice-error').addClass('notice-success').show().find('p').text(response.data.message);
                                    } else {
                                        $('#lte-test-result').removeClass('notice-success').addClass('notice-error').show().find('p').text(response.data.message);
                                    }
                                    button.prop('disabled', false).text('<?php esc_html_e('Tester l\'envoi WhatsApp', 'life-travel-excursion'); ?>');
                                },
                                error: function() {
                                    $('#lte-test-result').removeClass('notice-success').addClass('notice-error').show().find('p').text('<?php esc_html_e('Erreur de communication avec le serveur.', 'life-travel-excursion'); ?>');
                                    button.prop('disabled', false).text('<?php esc_html_e('Tester l\'envoi WhatsApp', 'life-travel-excursion'); ?>');
                                }
                            });
                        });
                        
                        // Tester l'export Excel
                        $('#lte-export-test').on('click', function() {
                            var button = $(this);
                            button.prop('disabled', true).text('<?php esc_html_e('Génération en cours...', 'life-travel-excursion'); ?>');
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'lte_test_excel_export',
                                    nonce: '<?php echo wp_create_nonce('lte_test_notification'); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        $('#lte-test-result').removeClass('notice-error').addClass('notice-success').show().find('p').html(response.data.message);
                                    } else {
                                        $('#lte-test-result').removeClass('notice-success').addClass('notice-error').show().find('p').text(response.data.message);
                                    }
                                    button.prop('disabled', false).text('<?php esc_html_e('Tester l\'export Excel', 'life-travel-excursion'); ?>');
                                },
                                error: function() {
                                    $('#lte-test-result').removeClass('notice-success').addClass('notice-error').show().find('p').text('<?php esc_html_e('Erreur de communication avec le serveur.', 'life-travel-excursion'); ?>');
                                    button.prop('disabled', false).text('<?php esc_html_e('Tester l\'export Excel', 'life-travel-excursion'); ?>');
                                }
                            });
                        });
                        
                        // Navigation par onglets
                        $('.lte-tab-nav a').on('click', function(e) {
                            e.preventDefault();
                            var target = $(this).attr('href');
                            
                            // Activer l'onglet
                            $('.lte-tab-nav a').removeClass('active');
                            $(this).addClass('active');
                            
                            // Afficher le contenu correspondant
                            $('.lte-tab-pane').removeClass('active');
                            $(target).addClass('active');
                        });
                    });
                    </script>
                    
                    <style>
                    .lte-admin-tabs {
                        margin-top: 20px;
                    }
                    
                    .lte-tab-nav {
                        display: flex;
                        border-bottom: 1px solid #ccc;
                        margin-bottom: 20px;
                    }
                    
                    .lte-tab-nav a {
                        padding: 10px 15px;
                        text-decoration: none;
                        margin-right: 5px;
                        border: 1px solid #ccc;
                        border-bottom: none;
                        background-color: #f9f9f9;
                        color: #333;
                        border-radius: 3px 3px 0 0;
                    }
                    
                    .lte-tab-nav a.active {
                        background-color: #fff;
                        border-bottom: 1px solid #fff;
                        margin-bottom: -1px;
                        font-weight: 600;
                    }
                    
                    .lte-tab-pane {
                        display: none;
                    }
                    
                    .lte-tab-pane.active {
                        display: block;
                    }
                    </style>
                </div>
            </div>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div><?php
