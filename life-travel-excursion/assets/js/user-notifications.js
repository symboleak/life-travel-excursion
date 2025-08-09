/**
 * Script pour la gestion des préférences de notification
 */
(function($) {
    'use strict';
    
    // Initialisation lors du chargement du document
    $(document).ready(function() {
        // Améliorer l'interface utilisateur
        enhanceNotificationInterface();
        
        // Gérer les changements de canaux
        handleChannelChanges();
    });
    
    /**
     * Améliore l'interface utilisateur des préférences de notification
     */
    function enhanceNotificationInterface() {
        // Ajouter des tooltips pour les informations
        $('.lte-notification-channel, .lte-notification-type').each(function() {
            var $this = $(this);
            var $description = $this.find('.description');
            var descText = $description.text();
            
            if (descText) {
                $this.find('label').attr('title', descText);
            }
        });
        
        // Ajouter des effets visuels sur le hover
        $('.lte-notification-channel, .lte-notification-type').hover(
            function() {
                $(this).css('background-color', '#f5f5f5');
            },
            function() {
                $(this).css('background-color', 'transparent');
            }
        );
        
        // Grouper les types de notification par catégorie avec animation
        $('.lte-notification-group h4').click(function() {
            var $this = $(this);
            var $group = $this.siblings();
            
            $group.slideToggle('fast');
            $this.toggleClass('collapsed');
            
            if ($this.hasClass('collapsed')) {
                $this.append(' <span class="dashicons dashicons-arrow-down-alt2"></span>');
            } else {
                $this.find('.dashicons').remove();
            }
        });
        
        // Vérifier si au moins un canal est activé
        checkActiveChannels();
    }
    
    /**
     * Gère les changements de canaux de notification
     */
    function handleChannelChanges() {
        // Lors d'un changement de canal
        $('.lte-notification-channel input[type="checkbox"]').change(function() {
            checkActiveChannels();
        });
    }
    
    /**
     * Vérifie qu'au moins un canal est activé
     */
    function checkActiveChannels() {
        var hasActiveChannel = false;
        
        $('.lte-notification-channel input[type="checkbox"]').each(function() {
            if ($(this).is(':checked') && !$(this).is(':disabled')) {
                hasActiveChannel = true;
                return false; // Sortir de la boucle
            }
        });
        
        // Si aucun canal n'est activé, afficher un avertissement
        if (!hasActiveChannel) {
            if ($('.lte-no-channels-warning').length === 0) {
                var warningHtml = '<div class="woocommerce-info lte-no-channels-warning">' +
                    '<span class="dashicons dashicons-warning"></span> ' +
                    'Aucun canal de notification n\'est actuellement activé. Vous ne recevrez aucune notification.' +
                    '</div>';
                    
                $('.lte-notification-channels').after(warningHtml);
            }
        } else {
            $('.lte-no-channels-warning').remove();
        }
    }
    
    /**
     * Sauvegarde les préférences via AJAX
     */
    function savePreferencesAjax() {
        var $form = $('.woocommerce-EditAccountForm');
        var formData = $form.serialize();
        
        $.ajax({
            url: lteNotifications.ajaxUrl,
            type: 'POST',
            data: {
                action: 'lte_save_notification_preferences_ajax',
                nonce: lteNotifications.nonce,
                form_data: formData
            },
            beforeSend: function() {
                $form.find('button[type="submit"]').prop('disabled', true).text('Enregistrement...');
            },
            success: function(response) {
                if (response.success) {
                    // Montrer un message de succès
                    $form.before('<div class="woocommerce-message" role="alert">' + lteNotifications.saveSuccess + '</div>');
                    
                    // Faire disparaître le message après 3 secondes
                    setTimeout(function() {
                        $('.woocommerce-message').fadeOut('slow', function() {
                            $(this).remove();
                        });
                    }, 3000);
                } else {
                    // Montrer un message d'erreur
                    $form.before('<div class="woocommerce-error" role="alert">' + lteNotifications.saveError + '</div>');
                }
            },
            error: function() {
                $form.before('<div class="woocommerce-error" role="alert">' + lteNotifications.saveError + '</div>');
            },
            complete: function() {
                $form.find('button[type="submit"]').prop('disabled', false).text('Enregistrer les préférences');
            }
        });
    }
    
})(jQuery);
