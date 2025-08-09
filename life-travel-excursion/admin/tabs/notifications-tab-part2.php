<?php
/**
 * Deuxième partie de l'onglet des paramètres de notifications
 * 
 * @package Life_Travel_Excursion
 * @since 2.0.0
 */

// Sortie directe interdite
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Notifications administratives -->
<div class="life-travel-admin-card">
    <h3 class="life-travel-card-header">
        <span class="dashicons dashicons-admin-users"></span> 
        <?php _e('Notifications administratives', 'life-travel-excursion'); ?>
    </h3>
    <div class="life-travel-card-body">
        <div class="life-travel-form-field">
            <label class="life-travel-toggle-switch">
                <input type="checkbox" name="admin_notifications_enabled" value="yes" 
                    <?php checked($options['admin_notifications_enabled'], 'yes'); ?>>
                <span class="life-travel-toggle-slider"></span>
                <?php _e('Activer les notifications pour les administrateurs', 'life-travel-excursion'); ?>
            </label>
            <p class="description">
                <?php _e('Permet aux administrateurs de recevoir des notifications concernant les réservations et les paiements.', 'life-travel-excursion'); ?>
            </p>
        </div>
        
        <div class="life-travel-form-field">
            <label><?php _e('Méthodes de notification', 'life-travel-excursion'); ?></label>
            <div class="life-travel-checkbox-group">
                <label class="life-travel-checkbox-label">
                    <input type="checkbox" name="admin_notification_methods[]" value="email" 
                        <?php checked(in_array('email', $options['admin_notification_methods']), true); ?>>
                    <?php _e('Email', 'life-travel-excursion'); ?>
                </label>
                <label class="life-travel-checkbox-label">
                    <input type="checkbox" name="admin_notification_methods[]" value="sms" 
                        <?php checked(in_array('sms', $options['admin_notification_methods']), true); ?>>
                    <?php _e('SMS', 'life-travel-excursion'); ?>
                </label>
                <label class="life-travel-checkbox-label">
                    <input type="checkbox" name="admin_notification_methods[]" value="whatsapp" 
                        <?php checked(in_array('whatsapp', $options['admin_notification_methods']), true); ?>>
                    <?php _e('WhatsApp', 'life-travel-excursion'); ?>
                </label>
            </div>
        </div>
        
        <div class="life-travel-form-field">
            <label><?php _e('Destinataires des notifications', 'life-travel-excursion'); ?></label>
            <div class="life-travel-repeater" id="admin_recipients_repeater">
                <div class="life-travel-repeater-items">
                    <?php 
                    // Afficher les destinataires existants
                    if (!empty($options['admin_recipients'])) :
                        foreach ($options['admin_recipients'] as $index => $recipient) : 
                    ?>
                        <div class="life-travel-repeater-item">
                            <div class="life-travel-repeater-item-header">
                                <span class="life-travel-repeater-title">
                                    <?php echo esc_html($recipient['email']); ?>
                                </span>
                                <button type="button" class="life-travel-repeater-remove button">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            <div class="life-travel-repeater-item-content">
                                <div class="life-travel-form-row">
                                    <div class="life-travel-form-field">
                                        <label>
                                            <?php _e('Email', 'life-travel-excursion'); ?>
                                            <span class="required">*</span>
                                        </label>
                                        <input type="email" name="admin_recipient_email[]" 
                                               value="<?php echo esc_attr($recipient['email']); ?>" required>
                                    </div>
                                    <div class="life-travel-form-field">
                                        <label><?php _e('Téléphone (format international)', 'life-travel-excursion'); ?></label>
                                        <input type="tel" name="admin_recipient_phone[]" 
                                               value="<?php echo esc_attr($recipient['phone']); ?>" 
                                               placeholder="+237...">
                                    </div>
                                </div>
                                <div class="life-travel-form-field">
                                    <label><?php _e('Canaux de notification préférés', 'life-travel-excursion'); ?></label>
                                    <div class="life-travel-checkbox-group">
                                        <label class="life-travel-checkbox-label">
                                            <input type="checkbox" name="admin_recipient_methods[<?php echo $index; ?>][]" value="email" 
                                                <?php checked(in_array('email', $recipient['methods']), true); ?>>
                                            <?php _e('Email', 'life-travel-excursion'); ?>
                                        </label>
                                        <label class="life-travel-checkbox-label">
                                            <input type="checkbox" name="admin_recipient_methods[<?php echo $index; ?>][]" value="sms" 
                                                <?php checked(in_array('sms', $recipient['methods']), true); ?>>
                                            <?php _e('SMS', 'life-travel-excursion'); ?>
                                        </label>
                                        <label class="life-travel-checkbox-label">
                                            <input type="checkbox" name="admin_recipient_methods[<?php echo $index; ?>][]" value="whatsapp" 
                                                <?php checked(in_array('whatsapp', $recipient['methods']), true); ?>>
                                            <?php _e('WhatsApp', 'life-travel-excursion'); ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endforeach; 
                    else : 
                        // Afficher un élément vide si aucun destinataire n'existe
                    ?>
                        <div class="life-travel-repeater-item">
                            <div class="life-travel-repeater-item-header">
                                <span class="life-travel-repeater-title">
                                    <?php _e('Nouveau destinataire', 'life-travel-excursion'); ?>
                                </span>
                                <button type="button" class="life-travel-repeater-remove button">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            <div class="life-travel-repeater-item-content">
                                <div class="life-travel-form-row">
                                    <div class="life-travel-form-field">
                                        <label>
                                            <?php _e('Email', 'life-travel-excursion'); ?>
                                            <span class="required">*</span>
                                        </label>
                                        <input type="email" name="admin_recipient_email[]" required>
                                    </div>
                                    <div class="life-travel-form-field">
                                        <label><?php _e('Téléphone (format international)', 'life-travel-excursion'); ?></label>
                                        <input type="tel" name="admin_recipient_phone[]" placeholder="+237...">
                                    </div>
                                </div>
                                <div class="life-travel-form-field">
                                    <label><?php _e('Canaux de notification préférés', 'life-travel-excursion'); ?></label>
                                    <div class="life-travel-checkbox-group">
                                        <label class="life-travel-checkbox-label">
                                            <input type="checkbox" name="admin_recipient_methods[0][]" value="email" checked>
                                            <?php _e('Email', 'life-travel-excursion'); ?>
                                        </label>
                                        <label class="life-travel-checkbox-label">
                                            <input type="checkbox" name="admin_recipient_methods[0][]" value="sms">
                                            <?php _e('SMS', 'life-travel-excursion'); ?>
                                        </label>
                                        <label class="life-travel-checkbox-label">
                                            <input type="checkbox" name="admin_recipient_methods[0][]" value="whatsapp">
                                            <?php _e('WhatsApp', 'life-travel-excursion'); ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="button" class="life-travel-repeater-add button">
                    <span class="dashicons dashicons-plus"></span>
                    <?php _e('Ajouter un destinataire', 'life-travel-excursion'); ?>
                </button>
                
                <div class="life-travel-repeater-template" style="display: none;">
                    <!-- Modèle pour nouvel élément -->
                    <div class="life-travel-repeater-item">
                        <div class="life-travel-repeater-item-header">
                            <span class="life-travel-repeater-title">
                                <?php _e('Nouveau destinataire', 'life-travel-excursion'); ?>
                            </span>
                            <button type="button" class="life-travel-repeater-remove button">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        <div class="life-travel-repeater-item-content">
                            <div class="life-travel-form-row">
                                <div class="life-travel-form-field">
                                    <label>
                                        <?php _e('Email', 'life-travel-excursion'); ?>
                                        <span class="required">*</span>
                                    </label>
                                    <input type="email" name="admin_recipient_email[]" required>
                                </div>
                                <div class="life-travel-form-field">
                                    <label><?php _e('Téléphone (format international)', 'life-travel-excursion'); ?></label>
                                    <input type="tel" name="admin_recipient_phone[]" placeholder="+237...">
                                </div>
                            </div>
                            <div class="life-travel-form-field">
                                <label><?php _e('Canaux de notification préférés', 'life-travel-excursion'); ?></label>
                                <div class="life-travel-checkbox-group">
                                    <label class="life-travel-checkbox-label">
                                        <input type="checkbox" name="admin_recipient_methods[%%INDEX%%][]" value="email" checked>
                                        <?php _e('Email', 'life-travel-excursion'); ?>
                                    </label>
                                    <label class="life-travel-checkbox-label">
                                        <input type="checkbox" name="admin_recipient_methods[%%INDEX%%][]" value="sms">
                                        <?php _e('SMS', 'life-travel-excursion'); ?>
                                    </label>
                                    <label class="life-travel-checkbox-label">
                                        <input type="checkbox" name="admin_recipient_methods[%%INDEX%%][]" value="whatsapp">
                                        <?php _e('WhatsApp', 'life-travel-excursion'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="life-travel-form-field">
            <label><?php _e('Types d\'événements', 'life-travel-excursion'); ?></label>
            <div class="life-travel-checkbox-group">
                <label class="life-travel-checkbox-label">
                    <input type="checkbox" name="notify_on_new_booking" value="yes" 
                        <?php checked($options['notify_on_new_booking'], 'yes'); ?>>
                    <?php _e('Nouvelles réservations', 'life-travel-excursion'); ?>
                </label>
                <label class="life-travel-checkbox-label">
                    <input type="checkbox" name="notify_on_payment" value="yes" 
                        <?php checked($options['notify_on_payment'], 'yes'); ?>>
                    <?php _e('Paiements', 'life-travel-excursion'); ?>
                </label>
                <label class="life-travel-checkbox-label">
                    <input type="checkbox" name="notify_on_cancellation" value="yes" 
                        <?php checked($options['notify_on_cancellation'], 'yes'); ?>>
                    <?php _e('Annulations', 'life-travel-excursion'); ?>
                </label>
                <label class="life-travel-checkbox-label">
                    <input type="checkbox" name="notify_daily_summary" value="yes" 
                        <?php checked($options['notify_daily_summary'], 'yes'); ?>>
                    <?php _e('Rapport journalier', 'life-travel-excursion'); ?>
                </label>
            </div>
        </div>
        
        <div class="life-travel-form-field" id="dailySummaryOptions">
            <label for="daily_summary_time"><?php _e('Heure du rapport journalier', 'life-travel-excursion'); ?></label>
            <input type="time" id="daily_summary_time" name="daily_summary_time" 
                   value="<?php echo esc_attr($options['daily_summary_time']); ?>">
            <p class="description">
                <?php _e('Heure à laquelle le rapport journalier sera envoyé. Utilise le fuseau horaire du site.', 'life-travel-excursion'); ?>
            </p>
        </div>
    </div>
</div>

<!-- Rappels d'excursion -->
<div class="life-travel-admin-card">
    <h3 class="life-travel-card-header">
        <span class="dashicons dashicons-calendar-alt"></span> 
        <?php _e('Rappels d\'excursion', 'life-travel-excursion'); ?>
    </h3>
    <div class="life-travel-card-body">
        <div class="life-travel-form-field">
            <label class="life-travel-toggle-switch">
                <input type="checkbox" name="reminders_enabled" value="yes" 
                    <?php checked($options['reminders_enabled'], 'yes'); ?>>
                <span class="life-travel-toggle-slider"></span>
                <?php _e('Activer les rappels automatiques', 'life-travel-excursion'); ?>
            </label>
            <p class="description">
                <?php _e('Envoyer des rappels aux clients avant leurs excursions pour réduire les no-shows.', 'life-travel-excursion'); ?>
            </p>
        </div>
        
        <div class="life-travel-form-row" id="reminderOptions">
            <div class="life-travel-form-field">
                <label for="reminder_before_days">
                    <?php _e('Envoyer le rappel', 'life-travel-excursion'); ?>
                </label>
                <div class="life-travel-input-group">
                    <input type="number" id="reminder_before_days" name="reminder_before_days" 
                           value="<?php echo intval($options['reminder_before_days']); ?>" min="1" max="7">
                    <span class="life-travel-input-suffix">
                        <?php _e('jours avant', 'life-travel-excursion'); ?>
                    </span>
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <label><?php _e('Méthodes de rappel', 'life-travel-excursion'); ?></label>
                <div class="life-travel-checkbox-group">
                    <label class="life-travel-checkbox-label">
                        <input type="checkbox" name="reminder_methods[]" value="email" 
                            <?php checked(in_array('email', $options['reminder_methods']), true); ?>>
                        <?php _e('Email', 'life-travel-excursion'); ?>
                    </label>
                    <label class="life-travel-checkbox-label">
                        <input type="checkbox" name="reminder_methods[]" value="sms" 
                            <?php checked(in_array('sms', $options['reminder_methods']), true); ?>>
                        <?php _e('SMS', 'life-travel-excursion'); ?>
                    </label>
                    <label class="life-travel-checkbox-label">
                        <input type="checkbox" name="reminder_methods[]" value="whatsapp" 
                            <?php checked(in_array('whatsapp', $options['reminder_methods']), true); ?>>
                        <?php _e('WhatsApp', 'life-travel-excursion'); ?>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Options avancées -->
<div class="life-travel-admin-card">
    <h3 class="life-travel-card-header">
        <span class="dashicons dashicons-admin-tools"></span> 
        <?php _e('Options avancées', 'life-travel-excursion'); ?>
    </h3>
    <div class="life-travel-card-body">
        <div class="life-travel-form-field">
            <label class="life-travel-toggle-switch">
                <input type="checkbox" name="log_notifications" value="yes" 
                    <?php checked($options['log_notifications'], 'yes'); ?>>
                <span class="life-travel-toggle-slider"></span>
                <?php _e('Journaliser les notifications', 'life-travel-excursion'); ?>
            </label>
            <p class="description">
                <?php _e('Enregistrer toutes les notifications envoyées pour référence et débogage.', 'life-travel-excursion'); ?>
            </p>
        </div>
        
        <div class="life-travel-form-field" id="logExpiration">
            <label for="log_expiration">
                <?php _e('Conserver les journaux pendant', 'life-travel-excursion'); ?>
            </label>
            <div class="life-travel-input-group">
                <input type="number" id="log_expiration" name="log_expiration" 
                       value="<?php echo intval($options['log_expiration']); ?>" min="1" max="365">
                <span class="life-travel-input-suffix">
                    <?php _e('jours', 'life-travel-excursion'); ?>
                </span>
            </div>
        </div>
        
        <div class="life-travel-form-field">
            <label class="life-travel-toggle-switch">
                <input type="checkbox" name="fallback_enabled" value="yes" 
                    <?php checked($options['fallback_enabled'], 'yes'); ?>>
                <span class="life-travel-toggle-slider"></span>
                <?php _e('Activer les mécanismes de repli', 'life-travel-excursion'); ?>
            </label>
            <p class="description">
                <?php _e('Tentatives automatiques avec canaux alternatifs en cas d\'échec d\'envoi.', 'life-travel-excursion'); ?>
            </p>
        </div>
        
        <div id="fallbackOptions">
            <div class="life-travel-form-row">
                <div class="life-travel-form-field">
                    <label for="fallback_threshold">
                        <?php _e('Nombre de tentatives', 'life-travel-excursion'); ?>
                    </label>
                    <input type="number" id="fallback_threshold" name="fallback_threshold" 
                           value="<?php echo intval($options['fallback_threshold']); ?>" min="1" max="10">
                </div>
                
                <div class="life-travel-form-field">
                    <label for="fallback_interval">
                        <?php _e('Intervalle entre les tentatives', 'life-travel-excursion'); ?>
                    </label>
                    <div class="life-travel-input-group">
                        <input type="number" id="fallback_interval" name="fallback_interval" 
                               value="<?php echo intval($options['fallback_interval']); ?>" min="5" max="120">
                        <span class="life-travel-input-suffix">
                            <?php _e('minutes', 'life-travel-excursion'); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="life-travel-form-field">
                <p class="description">
                    <?php _e('Exemple: Si envoi SMS échoue, essayer WhatsApp, puis Email. Ordre de repli automatique: SMS → WhatsApp → Email.', 'life-travel-excursion'); ?>
                </p>
            </div>
        </div>
        
        <div class="life-travel-form-field">
            <a href="<?php echo esc_url(admin_url('admin.php?page=life-travel-logs')); ?>" class="button">
                <span class="dashicons dashicons-list-view"></span>
                <?php _e('Consulter les journaux de notification', 'life-travel-excursion'); ?>
            </a>
            
            <a href="<?php echo esc_url(admin_url('admin.php?page=life-travel-notification-templates')); ?>" class="button">
                <span class="dashicons dashicons-edit"></span>
                <?php _e('Gérer les modèles de notification', 'life-travel-excursion'); ?>
            </a>
        </div>
    </div>
</div>

<div class="life-travel-admin-actions">
    <button type="submit" name="life_travel_save_notifications" class="button button-primary">
        <span class="dashicons dashicons-saved"></span>
        <?php _e('Enregistrer les paramètres de notification', 'life-travel-excursion'); ?>
    </button>
    
    <button type="button" id="test_notification" class="button">
        <span class="dashicons dashicons-email-alt"></span>
        <?php _e('Envoyer une notification de test', 'life-travel-excursion'); ?>
    </button>
</div>

<script>
jQuery(document).ready(function($) {
    // Gestion des rappels d'excursion
    $('input[name="reminders_enabled"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#reminderOptions').slideDown(200);
        } else {
            $('#reminderOptions').slideUp(200);
        }
    }).trigger('change');
    
    // Gestion des options de journalisation
    $('input[name="log_notifications"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#logExpiration').slideDown(200);
        } else {
            $('#logExpiration').slideUp(200);
        }
    }).trigger('change');
    
    // Gestion des options de repli
    $('input[name="fallback_enabled"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#fallbackOptions').slideDown(200);
        } else {
            $('#fallbackOptions').slideUp(200);
        }
    }).trigger('change');
    
    // Gestion du rapport journalier
    $('input[name="notify_daily_summary"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#dailySummaryOptions').slideDown(200);
        } else {
            $('#dailySummaryOptions').slideUp(200);
        }
    }).trigger('change');
    
    // Gestion du repeater pour les destinataires administrateurs
    var recipientIndex = <?php echo !empty($options['admin_recipients']) ? count($options['admin_recipients']) : 1; ?>;
    
    // Fonction pour mettre à jour les index
    function updateRecipientIndexes() {
        $('#admin_recipients_repeater .life-travel-repeater-item').each(function(idx) {
            $(this).find('[name^="admin_recipient_methods["]').each(function() {
                var name = $(this).attr('name');
                name = name.replace(/admin_recipient_methods\[\d+\]/, 'admin_recipient_methods[' + idx + ']');
                $(this).attr('name', name);
            });
        });
    }
    
    // Fonction pour mettre à jour les titres des éléments
    function updateRecipientTitles() {
        $('#admin_recipients_repeater .life-travel-repeater-item').each(function() {
            var email = $(this).find('[name="admin_recipient_email[]"]').val();
            var title = email ? email : '<?php _e('Nouveau destinataire', 'life-travel-excursion'); ?>';
            $(this).find('.life-travel-repeater-title').text(title);
        });
    }
    
    // Ajout d'un nouveau destinataire
    $('#admin_recipients_repeater .life-travel-repeater-add').on('click', function() {
        var template = $('#admin_recipients_repeater .life-travel-repeater-template').html();
        template = template.replace(/%%INDEX%%/g, recipientIndex);
        
        $('#admin_recipients_repeater .life-travel-repeater-items').append(template);
        recipientIndex++;
        
        updateRecipientTitles();
        initRecipientEvents();
    });
    
    // Initialisation des événements pour les éléments existants et nouveaux
    function initRecipientEvents() {
        // Suppression d'un destinataire
        $('#admin_recipients_repeater .life-travel-repeater-remove').off('click').on('click', function() {
            var item = $(this).closest('.life-travel-repeater-item');
            
            // Animation de suppression
            item.slideUp(200, function() {
                $(this).remove();
                updateRecipientIndexes();
            });
        });
        
        // Mise à jour du titre lors de la modification de l'email
        $('#admin_recipients_repeater [name="admin_recipient_email[]"]').off('input').on('input', function() {
            updateRecipientTitles();
        });
        
        // Toggle du contenu
        $('#admin_recipients_repeater .life-travel-repeater-item-header').off('click').on('click', function(e) {
            if ($(e.target).hasClass('life-travel-repeater-remove') || 
                $(e.target).closest('.life-travel-repeater-remove').length) {
                return;
            }
            
            $(this).next('.life-travel-repeater-item-content').slideToggle(200);
        });
    }
    
    // Initialiser les événements
    initRecipientEvents();
    
    // Notification de test
    $('#test_notification').on('click', function() {
        if (confirm('<?php _e('Envoyer une notification de test à tous les destinataires configurés ?', 'life-travel-excursion'); ?>')) {
            $(this).prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> <?php _e('Envoi en cours...', 'life-travel-excursion'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'life_travel_test_notification',
                    nonce: '<?php echo wp_create_nonce('life_travel_test_notification'); ?>',
                    recipients: $('input[name="admin_recipient_email[]"]').map(function() {
                        return $(this).val();
                    }).get()
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('Notification de test envoyée avec succès.', 'life-travel-excursion'); ?>');
                    } else {
                        alert('<?php _e('Erreur lors de l\'envoi de la notification de test: ', 'life-travel-excursion'); ?>' + response.data.message);
                    }
                },
                error: function() {
                    alert('<?php _e('Erreur de communication avec le serveur.', 'life-travel-excursion'); ?>');
                },
                complete: function() {
                    $('#test_notification').prop('disabled', false).html('<span class="dashicons dashicons-email-alt"></span> <?php _e('Envoyer une notification de test', 'life-travel-excursion'); ?>');
                }
            });
        }
    });
});
</script>
