/**
 * JavaScript pour l'interface d'administration unifiée Life Travel Excursion
 * 
 * Optimisé pour compatibilité cross-browser et robustesse
 * 
 * @package Life_Travel_Excursion
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    // État local pour le suivi des opérations
    const lifeTravel = {
        initializing: true,
        unsavedChanges: false,
        activeTemplates: {},
        networkStatus: navigator.onLine,
        pendingOperations: []
    };
    
    /**
     * Initialisation principale
     */
    function init() {
        // Initialiser les onglets
        initTabs();
        
        // Initialiser les méthodes de paiement
        initPaymentMethods();
        
        // Initialiser les modèles de notification
        initNotificationTemplates();
        
        // Initialiser le sélecteur de couleur
        initColorPickers();
        
        // Gestion des toggles
        initToggles();
        
        // Gestion des uploads d'images
        initMediaUploads();
        
        // Gestion de copie dans le presse-papier
        initClipboardCopy();
        
        // Détection de modification du formulaire
        initFormChangeDetection();
        
        // Détection de réseau hors-ligne
        initOfflineDetection();
        
        // Plus d'initialisation...
        lifeTravel.initializing = false;
    }
    
    /**
     * Initialisation des onglets d'administration
     */
    function initTabs() {
        $('.life-travel-admin-tabs a.nav-tab').on('click', function(e) {
            if (lifeTravel.unsavedChanges) {
                const confirmed = confirm(lifeTravel.i18n.unsaved_changes);
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }
    
    /**
     * Initialisation des méthodes de paiement
     */
    function initPaymentMethods() {
        // Toggle pour afficher/masquer les détails d'une méthode de paiement
        $('.life-travel-payment-method-toggle input[type="checkbox"]').on('change', function() {
            const methodId = $(this).attr('name');
            const detailsId = '#' + methodId + '_details';
            
            if ($(this).is(':checked')) {
                $(detailsId).slideDown(200);
            } else {
                $(detailsId).slideUp(200);
            }
        }).trigger('change');
        
        // Pour la compatibilité mobile, rendez le header entier cliquable
        $('.life-travel-payment-method-header').on('click', function(e) {
            // Éviter le clic si on a cliqué sur le toggle directement
            if ($(e.target).hasClass('life-travel-toggle-switch') || 
                $(e.target).closest('.life-travel-toggle-switch').length) {
                return;
            }
            
            const toggle = $(this).find('input[type="checkbox"]');
            toggle.prop('checked', !toggle.prop('checked')).trigger('change');
        });
    }
    
    /**
     * Initialisation des modèles de notification
     */
    function initNotificationTemplates() {
        // Toggle pour afficher/masquer le contenu d'un modèle
        $('.life-travel-template-header').on('click', function() {
            const templateId = $(this).data('template');
            const body = $(this).next('.life-travel-template-body');
            
            body.toggleClass('active').slideToggle(200);
            
            // Sauvegarder l'état pour la prochaine visite
            lifeTravel.activeTemplates[templateId] = body.hasClass('active');
        });
        
        // Onglets de canaux de notification dans chaque modèle
        $('.life-travel-template-tab').on('click', function() {
            const tabId = $(this).data('tab');
            const parent = $(this).closest('.life-travel-template-body');
            
            // Activer l'onglet
            parent.find('.life-travel-template-tab').removeClass('active');
            $(this).addClass('active');
            
            // Afficher le contenu correspondant
            parent.find('.life-travel-template-content').removeClass('active');
            parent.find('.life-travel-template-content[data-tab="' + tabId + '"]').addClass('active');
        });
        
        // Insertion de variables dans l'éditeur
        $('.life-travel-variable-item').on('click', function() {
            const variable = $(this).data('variable');
            const editorId = $(this).closest('.life-travel-template-content').find('textarea').attr('id');
            
            if (editorId) {
                insertTextAtCursor(document.getElementById(editorId), '{{' + variable + '}}');
            }
        });
    }
    
    /**
     * Initialisation des sélecteurs de couleur
     */
    function initColorPickers() {
        if ($.fn.wpColorPicker) {
            // Initialiser avec la fonction WordPress
            $('.life-travel-color-picker').wpColorPicker({
                change: function(event, ui) {
                    updateColorPreview();
                    markFormAsChanged();
                }
            });
            
            // Mise à jour de l'aperçu des couleurs
            function updateColorPreview() {
                const primaryColor = $('#primary_color').val();
                const secondaryColor = $('#secondary_color').val();
                
                if (primaryColor) {
                    $('.life-travel-preview-primary').css({
                        'background-color': primaryColor,
                        'border-color': primaryColor
                    });
                }
                
                if (secondaryColor) {
                    $('.life-travel-preview-secondary').css({
                        'background-color': secondaryColor,
                        'border-color': secondaryColor
                    });
                }
            }
            
            // Exécuter une fois au chargement
            updateColorPreview();
        }
    }
    
    /**
     * Initialisation des toggles et options conditionnelles
     */
    function initToggles() {
        // Options du mode hors-ligne
        $('input[name="enable_offline_mode"]').on('change', function() {
            if ($(this).is(':checked')) {
                $('#offlineOptions').slideDown(200);
            } else {
                $('#offlineOptions').slideUp(200);
            }
        }).trigger('change');
        
        // Options des rappels de paiement
        $('input[name="enable_payment_reminders"]').on('change', function() {
            if ($(this).is(':checked')) {
                $('#paymentReminderOptions').slideDown(200);
            } else {
                $('#paymentReminderOptions').slideUp(200);
            }
        }).trigger('change');
        
        // Options de paiement partiel
        $('input[name="partial_payment_enabled"]').on('change', function() {
            if ($(this).is(':checked')) {
                $('#partialPaymentOptions').slideDown(200);
            } else {
                $('#partialPaymentOptions').slideUp(200);
            }
        }).trigger('change');
    }
    
    /**
     * Initialisation des uploads de média
     */
    function initMediaUploads() {
        // S'assurer que wp.media est disponible
        if (typeof wp !== 'undefined' && wp.media) {
            $('.life-travel-upload-button').on('click', function(e) {
                e.preventDefault();
                
                const button = $(this);
                const inputField = button.siblings('input');
                const previewDiv = button.closest('.life-travel-form-field').find('.life-travel-logo-preview');
                
                // Créer un frame média
                const frame = wp.media({
                    title: lifeTravel.i18n.select_image || 'Sélectionner une image',
                    multiple: false,
                    library: {
                        type: 'image'
                    },
                    button: {
                        text: lifeTravel.i18n.use_image || 'Utiliser cette image'
                    }
                });
                
                // Gérer la sélection
                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    
                    // Mettre à jour le champ et l'aperçu
                    inputField.val(attachment.url).trigger('change');
                    
                    if (previewDiv.length) {
                        previewDiv.html('<img src="' + attachment.url + '" alt="Preview">');
                    }
                    
                    markFormAsChanged();
                });
                
                // Ouvrir le sélecteur de média
                frame.open();
            });
        }
    }
    
    /**
     * Initialisation de la fonctionnalité de copie
     */
    function initClipboardCopy() {
        $('.life-travel-copy-button').on('click', function() {
            const targetId = $(this).data('target');
            const copyText = document.getElementById(targetId);
            
            // Sélectionner le texte
            copyText.select();
            copyText.setSelectionRange(0, 99999); // Pour mobile
            
            try {
                // Tenter de copier dans le presse-papier
                const success = document.execCommand('copy');
                
                if (success) {
                    // Animation de succès
                    $(this).find('.dashicons')
                        .removeClass('dashicons-clipboard')
                        .addClass('dashicons-yes');
                    
                    setTimeout(() => {
                        $(this).find('.dashicons')
                            .removeClass('dashicons-yes')
                            .addClass('dashicons-clipboard');
                    }, 1500);
                }
            } catch (err) {
                console.error('Erreur lors de la copie:', err);
            }
        });
    }
    
    /**
     * Détection de changements non enregistrés
     */
    function initFormChangeDetection() {
        $('.life-travel-admin-form :input').on('change', function() {
            if (!lifeTravel.initializing) {
                markFormAsChanged();
            }
        });
        
        $('.life-travel-admin-form').on('submit', function() {
            lifeTravel.unsavedChanges = false;
        });
        
        // Avertissement avant de quitter la page
        $(window).on('beforeunload', function() {
            if (lifeTravel.unsavedChanges) {
                return lifeTravel.i18n.unsaved_changes || 'Vous avez des modifications non enregistrées.';
            }
        });
    }
    
    /**
     * Détection et gestion du mode hors ligne
     */
    function initOfflineDetection() {
        function updateOnlineStatus() {
            const isOnline = navigator.onLine;
            
            if (isOnline !== lifeTravel.networkStatus) {
                lifeTravel.networkStatus = isOnline;
                
                if (isOnline) {
                    // Passage en ligne - traiter les opérations en attente
                    $('.life-travel-offline-notice').slideUp(300, function() {
                        $(this).remove();
                    });
                    
                    processPendingOperations();
                } else {
                    // Passage hors ligne - afficher une notification
                    if ($('.life-travel-offline-notice').length === 0) {
                        const offlineMessage = $('<div class="notice notice-warning life-travel-offline-notice">' +
                            '<p><strong>' + (lifeTravel.i18n.offline_message || 'Vous êtes actuellement hors ligne.') + '</strong></p>' +
                            '<p>' + (lifeTravel.i18n.offline_warning || 'Vos modifications seront enregistrées localement et synchronisées lorsque la connexion sera rétablie.') + '</p>' +
                            '</div>');
                        
                        $('.life-travel-admin-header').after(offlineMessage);
                    }
                }
            }
        }
        
        // Événements de détection de réseau
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        
        // Vérification initiale
        updateOnlineStatus();
    }
    
    /**
     * Traitement des opérations en attente une fois la connexion rétablie
     */
    function processPendingOperations() {
        if (lifeTravel.pendingOperations.length === 0) {
            return;
        }
        
        const pendingOps = [...lifeTravel.pendingOperations];
        lifeTravel.pendingOperations = [];
        
        pendingOps.forEach(operation => {
            try {
                if (operation.type === 'form_submit') {
                    // Soumettre le formulaire stocké
                    const formData = operation.data;
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'life_travel_sync_admin_settings',
                            nonce: lifeTravel.nonce,
                            form_data: formData
                        },
                        success: function(response) {
                            if (response.success) {
                                showNotice('success', lifeTravel.i18n.synced_success || 'Paramètres synchronisés avec succès.');
                            } else {
                                showNotice('error', response.data.message || lifeTravel.i18n.sync_error);
                            }
                        },
                        error: function() {
                            showNotice('error', lifeTravel.i18n.sync_error || 'Erreur lors de la synchronisation.');
                            lifeTravel.pendingOperations.push(operation);
                        }
                    });
                }
            } catch (error) {
                console.error('Erreur de traitement d\'opération:', error);
                lifeTravel.pendingOperations.push(operation);
            }
        });
    }
    
    /**
     * Marquer le formulaire comme modifié
     */
    function markFormAsChanged() {
        lifeTravel.unsavedChanges = true;
    }
    
    /**
     * Insérer du texte à la position du curseur dans un champ textarea
     * 
     * @param {HTMLElement} field Le champ texte
     * @param {string} text Le texte à insérer
     */
    function insertTextAtCursor(field, text) {
        if (!field) return;
        
        // Sauvegarder la position de défilement
        const scrollPos = field.scrollTop;
        
        // IE / Edge support
        if (document.selection) {
            field.focus();
            var sel = document.selection.createRange();
            sel.text = text;
            field.focus();
        }
        // Firefox / Chrome support
        else if (field.selectionStart || field.selectionStart === 0) {
            const startPos = field.selectionStart;
            const endPos = field.selectionEnd;
            
            field.value = field.value.substring(0, startPos) + text + field.value.substring(endPos, field.value.length);
            field.selectionStart = startPos + text.length;
            field.selectionEnd = startPos + text.length;
            field.focus();
        } else {
            field.value += text;
            field.focus();
        }
        
        // Restaurer la position de défilement
        field.scrollTop = scrollPos;
        
        // Déclencher l'événement de changement
        $(field).trigger('change');
    }
    
    /**
     * Afficher une notification dans l'interface d'administration
     * 
     * @param {string} type Type de notice (success, error, warning, info)
     * @param {string} message Message à afficher
     */
    function showNotice(type, message) {
        const noticeClass = 'notice notice-' + type;
        const notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
        
        // Ajouter en haut de la page
        $('.life-travel-admin-tabs').before(notice);
        
        // Animer l'entrée
        notice.hide().slideDown(300);
        
        // Auto-disparition après 5 secondes pour les succès
        if (type === 'success') {
            setTimeout(function() {
                notice.slideUp(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }
    
    // Initialiser tout au chargement du document
    $(document).ready(function() {
        init();
    });
    
})(jQuery);
