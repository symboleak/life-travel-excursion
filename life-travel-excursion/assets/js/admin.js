/**
 * Scripts du tableau de bord administrateur unifié Life Travel
 *
 * Ce fichier contient les fonctionnalités JavaScript pour l'interface
 * d'administration unifiée de Life Travel, avec des optimisations pour
 * les connexions variables.
 *
 * @package Life Travel Excursion
 * @version 2.3.7
 */

(function($) {
    'use strict';
    
    // Objet principal pour l'interface d'administration
    var LifeTravelAdmin = {
        // Initialisation
        init: function() {
            this.initTooltips();
            this.initMediaUploaders();
            this.initRangeSliders();
            this.initToggles();
            this.initAjaxSave();
            this.initTabNavigation();
            this.initGalleryPreviews();
            this.initSeasonalSettings();
            this.initConnectionDetection();
        },
        
        // Initialiser les tooltips d'aide
        initTooltips: function() {
            $('.life-travel-tooltip').on('mouseover', function() {
                var $tooltip = $(this);
                if (!$tooltip.data('tooltip-initialized')) {
                    var tooltipText = $tooltip.data('tooltip');
                    var $content = $('<div class="life-travel-tooltip-content"></div>').text(tooltipText);
                    $tooltip.append($content);
                    $tooltip.data('tooltip-initialized', true);
                }
            });
        },
        
        // Initialiser les sélecteurs de médias
        initMediaUploaders: function() {
            // Gestionnaire pour sélectionner un média
            $('.life-travel-select-media').on('click', function() {
                var targetId = $(this).data('target');
                var mediaUploader;
                var $button = $(this);
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media({
                    title: lifeTravel.i18n.selectImage,
                    button: {
                        text: lifeTravel.i18n.useImage
                    },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#' + targetId).val(attachment.id);
                    
                    // Mettre à jour l'aperçu
                    var $preview = $('#' + targetId).closest('.life-travel-media-field').find('.life-travel-media-preview');
                    
                    if (attachment.type === 'image') {
                        $preview.html('<img src="' + attachment.url + '" alt="">');
                    } else {
                        $preview.html('<div class="life-travel-no-image">Format non supporté</div>');
                    }
                    
                    // Afficher le bouton de suppression
                    $('#' + targetId).closest('.life-travel-media-field').find('.life-travel-remove-media').show();
                });
                
                mediaUploader.open();
            });
            
            // Gestionnaire pour supprimer un média
            $('.life-travel-remove-media').on('click', function() {
                var targetId = $(this).data('target');
                $('#' + targetId).val('');
                
                // Mettre à jour l'aperçu
                var $preview = $('#' + targetId).closest('.life-travel-media-field').find('.life-travel-media-preview');
                $preview.html('<div class="life-travel-no-image">Aucune image</div>');
                
                // Cacher le bouton de suppression
                $(this).hide();
            });
        },
        
        // Initialiser les curseurs de plage
        initRangeSliders: function() {
            $('.life-travel-range-field input[type="range"]').on('input', function() {
                $(this).next('.life-travel-range-value').text($(this).val() + '%');
            });
            
            // Prévisualisation des paramètres spécifiques
            $('#life_travel_background_overlay_opacity').on('input', function() {
                var opacity = $(this).val() / 100;
                $('.life-travel-background-preview').css('--overlay-opacity', opacity);
            });
        },
        
        // Initialiser les commutateurs de toggle
        initToggles: function() {
            // Gestion des dépendances des champs
            $('input[type="checkbox"][data-controls]').on('change', function() {
                var isChecked = $(this).is(':checked');
                var targetSelector = '#' + $(this).data('controls');
                
                if (isChecked) {
                    $(targetSelector).removeClass('disabled').find('input, select, textarea').prop('disabled', false);
                } else {
                    $(targetSelector).addClass('disabled').find('input, select, textarea').prop('disabled', true);
                }
            }).trigger('change');
        },
        
        // Initialiser les sauvegarde AJAX
        initAjaxSave: function() {
            $('.life-travel-ajax-save').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var data = {
                    action: 'life_travel_admin_save_option',
                    nonce: lifeTravel.nonce,
                    option_name: $button.data('option'),
                    option_value: $('#' + $button.data('target')).val()
                };
                
                // Désactiver le bouton pendant la sauvegarde
                $button.prop('disabled', true).addClass('updating-message');
                
                // Envoyer la requête AJAX
                $.post(lifeTravel.ajaxUrl, data, function(response) {
                    $button.prop('disabled', false).removeClass('updating-message');
                    
                    if (response.success) {
                        // Afficher un message de succès
                        var $message = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        $button.closest('form').before($message);
                        
                        // Faire disparaître le message après quelques secondes
                        setTimeout(function() {
                            $message.fadeOut(function() {
                                $message.remove();
                            });
                        }, 3000);
                    } else {
                        // Afficher un message d'erreur
                        var $message = $('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>');
                        $button.closest('form').before($message);
                    }
                });
            });
        },
        
        // Initialiser la navigation par onglets
        initTabNavigation: function() {
            $('.life-travel-admin-tabs a').on('click', function(e) {
                e.preventDefault();
                
                var $tab = $(this);
                var targetSection = $tab.attr('href').substring(1);
                
                // Mettre à jour l'URL avec le paramètre de section
                var url = window.location.href.split('?')[0] + '?page=' + getQueryParam('page') + '&section=' + targetSection;
                window.history.pushState({}, '', url);
                
                // Mettre à jour les onglets et sections
                $('.life-travel-admin-tabs li').removeClass('active');
                $tab.parent().addClass('active');
                
                $('.life-travel-admin-section').hide();
                $('#' + targetSection).show();
            });
            
            // Fonction utilitaire pour récupérer un paramètre de l'URL
            function getQueryParam(name) {
                var match = RegExp('[?&]' + name + '=([^&]*)').exec(window.location.search);
                return match && decodeURIComponent(match[1].replace(/\+/g, ' '));
            }
        },
        
        // Initialiser les prévisualisations de galerie
        initGalleryPreviews: function() {
            // Prévisualisation des styles de galerie
            $('input[name="life_travel_gallery_style"]').on('change', function() {
                var selectedStyle = $(this).val();
                $('.life-travel-gallery-style').removeClass('active');
                $('.life-travel-gallery-' + selectedStyle).addClass('active');
            });
        },
        
        // Initialiser les paramètres saisonniers
        initSeasonalSettings: function() {
            // Gestion de l'affichage des paramètres saisonniers
            $('input[name="life_travel_seasonal_rates_enabled"]').on('change', function() {
                var isEnabled = $(this).is(':checked');
                $('#life-travel-seasonal-settings').toggleClass('disabled', !isEnabled);
                $('#life-travel-seasonal-settings input').prop('disabled', !isEnabled);
            }).trigger('change');
            
            // Validation des sélections de mois (haute et basse saison ne doivent pas se chevaucher)
            $('.life-travel-month-checkbox input').on('change', function() {
                var isHighSeason = $(this).attr('name').includes('high_season');
                var month = $(this).val();
                
                // Désélectionner le même mois dans l'autre saison
                if (isHighSeason && $(this).is(':checked')) {
                    $('input[name="life_travel_low_season_months[]"][value="' + month + '"]').prop('checked', false);
                } else if (!isHighSeason && $(this).is(':checked')) {
                    $('input[name="life_travel_high_season_months[]"][value="' + month + '"]').prop('checked', false);
                }
            });
        },
        
        // Détection et adaptation à la connexion
        initConnectionDetection: function() {
            // Détecter la qualité de la connexion
            if ('connection' in navigator) {
                var updateConnectionStatus = function() {
                    var connectionType = navigator.connection.effectiveType;
                    var connectionSpeed;
                    
                    // Déterminer la vitesse de connexion
                    switch (connectionType) {
                        case 'slow-2g':
                        case '2g':
                            connectionSpeed = 'slow';
                            break;
                        case '3g':
                            connectionSpeed = 'medium';
                            break;
                        case '4g':
                            connectionSpeed = 'fast';
                            break;
                        default:
                            connectionSpeed = 'unknown';
                    }
                    
                    // Mettre à jour l'interface selon la vitesse
                    $('body').removeClass('connection-slow connection-medium connection-fast')
                            .addClass('connection-' + connectionSpeed);
                            
                    // Adapter les images selon la connexion
                    if (connectionSpeed === 'slow') {
                        // Réduire la qualité des images
                        $('.life-travel-media-preview img').addClass('low-quality');
                    }
                };
                
                // Mettre à jour le statut initial
                updateConnectionStatus();
                
                // Mettre à jour lors des changements de connexion
                navigator.connection.addEventListener('change', updateConnectionStatus);
            }
            
            // Détecter l'état de connexion
            window.addEventListener('online', function() {
                $('.life-travel-connection-status').removeClass('offline').addClass('online')
                    .text('En ligne');
            });
            
            window.addEventListener('offline', function() {
                $('.life-travel-connection-status').removeClass('online').addClass('offline')
                    .text('Hors ligne - Les modifications seront synchronisées ultérieurement');
            });
        }
    };
    
    // Initialiser l'interface d'administration au chargement de la page
    $(document).ready(function() {
        LifeTravelAdmin.init();
    });
    
})(jQuery);
