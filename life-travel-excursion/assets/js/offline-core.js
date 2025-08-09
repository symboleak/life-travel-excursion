/**
 * Life Travel Offline Core
 * 
 * Module principal pour la détection réseau et la gestion du mode hors-ligne
 * 
 * @package Life Travel Excursion
 * @version 2.5.0
 */

/* global lifeTravelOffline */
(function($) {
    'use strict';

    // Configuration par défaut
    var defaultConfig = {
        testUrl: '/wp-admin/admin-ajax.php',
        testInterval: 30000, // 30 secondes
        lowBandwidthThreshold: 50, // kbps
        highLatencyThreshold: 1000, // ms
        retryAttempts: 3,
        statusDisplayDuration: 5000, // 5 secondes
        enableDebug: false
    };

    // État du réseau et du système
    var state = {
        online: navigator.onLine,
        connectionType: null,
        downlink: null,
        rtt: null,
        networkStatus: 'unknown', // unknown, normal, slow, very_slow, offline
        lastCheck: 0,
        pendingSync: false,
        notifications: []
    };

    // Interface principale
    var LifeTravelOfflineCore = {
        /**
         * Initialise le module
         */
        init: function() {
            // Fusionner la configuration
            this.config = $.extend({}, defaultConfig, lifeTravelOffline.config || {});
            
            // Initialiser les écouteurs d'événements
            this.initEventListeners();
            
            // Vérifier l'état du réseau immédiatement
            this.checkNetworkStatus();
            
            // Configurer la vérification périodique
            setInterval(this.checkNetworkStatus.bind(this), this.config.testInterval);
            
            // Initialiser l'interface utilisateur
            this.initUI();
            
            // Activer le débogage si configuré
            if (this.config.enableDebug) {
                console.log('Life Travel Offline Core initialisé avec la configuration:', this.config);
            }
            
            // Déclencher l'événement d'initialisation
            $(document).trigger('life_travel_offline_init', [this]);
            
            return this;
        },
        
        /**
         * Initialise les écouteurs d'événements
         */
        initEventListeners: function() {
            // Événements de connectivité du navigateur
            window.addEventListener('online', this.handleOnlineEvent.bind(this));
            window.addEventListener('offline', this.handleOfflineEvent.bind(this));
            
            // Écouter les changements de Network Information API si disponible
            if ('connection' in navigator && 'effectiveType' in navigator.connection) {
                navigator.connection.addEventListener('change', this.handleConnectionChange.bind(this));
            }
            
            // Écouteurs personnalisés
            $(document).on('life_travel_network_status_change', this.handleNetworkStatusChange.bind(this));
            $(document).on('life_travel_sync_required', this.handleSyncRequired.bind(this));
            $(document).on('life_travel_sync_complete', this.handleSyncComplete.bind(this));
            
            // Intercepter les soumissions de formulaire en mode hors-ligne
            $(document).on('submit', 'form.life-travel-form', this.handleFormSubmit.bind(this));
            
            // Intercepter les clics sur les boutons d'action
            $(document).on('click', '.life-travel-action-button', this.handleActionClick.bind(this));
        },
        
        /**
         * Initialise l'interface utilisateur
         */
        initUI: function() {
            // Créer l'indicateur de statut s'il n'existe pas déjà
            if ($('#life-travel-offline-indicator').length === 0) {
                $('body').append(
                    '<div id="life-travel-offline-indicator" class="life-travel-status-indicator">' +
                    '<span class="status-icon"></span>' +
                    '<span class="status-text"></span>' +
                    '</div>'
                );
                
                // Ajouter le bouton de synchronisation
                $('body').append(
                    '<div id="life-travel-sync-button" class="life-travel-action-button">' +
                    '<span class="sync-icon"></span>' +
                    '<span class="sync-text">' + lifeTravelOffline.l10n.sync_needed + '</span>' +
                    '</div>'
                );
                
                // Cacher le bouton par défaut
                $('#life-travel-sync-button').hide();
            }
            
            // Mettre à jour l'indicateur en fonction de l'état actuel
            this.updateStatusDisplay();
        },
        
        /**
         * Gère l'événement de passage en ligne
         */
        handleOnlineEvent: function() {
            console.log('Navigateur en ligne');
            state.online = true;
            
            // Vérifier l'état réel du réseau
            this.checkNetworkStatus(function(status) {
                if (status !== 'offline') {
                    // Si des données sont en attente de synchronisation, déclencher une synchronisation
                    if (state.pendingSync) {
                        $(document).trigger('life_travel_sync_required');
                    }
                }
            });
        },
        
        /**
         * Gère l'événement de passage hors-ligne
         */
        handleOfflineEvent: function() {
            console.log('Navigateur hors-ligne');
            state.online = false;
            state.networkStatus = 'offline';
            
            // Mettre à jour l'affichage
            this.updateStatusDisplay();
            
            // Déclencher l'événement de changement de statut
            $(document).trigger('life_travel_network_status_change', ['offline']);
        },
        
        /**
         * Gère les changements dans l'API Network Information
         */
        handleConnectionChange: function() {
            if (this.config.enableDebug) {
                console.log('Changement de connexion détecté', navigator.connection);
            }
            
            // Mettre à jour les informations de connexion
            this.updateConnectionInfo();
            
            // Vérifier l'état du réseau
            this.checkNetworkStatus();
        },
        
        /**
         * Met à jour les informations de connexion depuis l'API
         */
        updateConnectionInfo: function() {
            if ('connection' in navigator) {
                state.connectionType = navigator.connection.effectiveType || navigator.connection.type || null;
                state.downlink = navigator.connection.downlink || null;
                state.rtt = navigator.connection.rtt || null;
                
                if (this.config.enableDebug) {
                    console.log('Info connexion:', {
                        type: state.connectionType,
                        downlink: state.downlink,
                        rtt: state.rtt
                    });
                }
            }
        },
        
        /**
         * Vérifie l'état du réseau
         * 
         * @param {Function} callback Fonction de rappel appelée avec le statut
         */
        checkNetworkStatus: function(callback) {
            // Mettre à jour les infos de connexion si disponibles
            this.updateConnectionInfo();
            
            // Si le navigateur indique qu'on est hors-ligne, pas besoin de tester
            if (!navigator.onLine) {
                state.networkStatus = 'offline';
                this.updateStatusDisplay();
                if (callback) callback('offline');
                return;
            }
            
            // Si les infos de connexion sont disponibles, les utiliser pour une estimation préliminaire
            var preliminaryStatus = this.estimateNetworkStatus();
            
            // Pour une vérification précise, faire une requête test
            if (Date.now() - state.lastCheck > this.config.testInterval / 2) {
                this.performNetworkTest(function(testStatus) {
                    // Mettre à jour le statut en fonction des résultats du test
                    state.networkStatus = testStatus;
                    state.lastCheck = Date.now();
                    
                    this.updateStatusDisplay();
                    
                    if (callback) callback(testStatus);
                    
                    // Déclencher l'événement de changement de statut
                    $(document).trigger('life_travel_network_status_change', [testStatus]);
                }.bind(this));
            } else {
                // Utiliser l'estimation préliminaire si le dernier test est récent
                state.networkStatus = preliminaryStatus;
                this.updateStatusDisplay();
                if (callback) callback(preliminaryStatus);
            }
        },
        
        /**
         * Estime l'état du réseau en fonction des informations disponibles
         * 
         * @return {string} Statut estimé du réseau
         */
        estimateNetworkStatus: function() {
            // Si Network Information API est disponible
            if (state.connectionType) {
                switch (state.connectionType) {
                    case 'slow-2g':
                    case '2g':
                        return 'very_slow';
                    case '3g':
                        return 'slow';
                    case '4g':
                    case 'wifi':
                    case 'ethernet':
                        return 'normal';
                    default:
                        return 'unknown';
                }
            }
            
            // Estimation basée sur downlink et rtt
            if (state.downlink !== null && state.rtt !== null) {
                if (state.downlink < 0.1 || state.rtt > 2000) {
                    return 'very_slow';
                } else if (state.downlink < 0.5 || state.rtt > 1000) {
                    return 'slow';
                } else {
                    return 'normal';
                }
            }
            
            // Pas assez d'informations
            return 'unknown';
        },
        
        /**
         * Effectue un test réseau
         * 
         * @param {Function} callback Fonction de rappel appelée avec le résultat
         */
        performNetworkTest: function(callback) {
            var testStart = Date.now();
            var testUrl = this.config.testUrl + '?action=life_travel_network_test&t=' + testStart;
            
            // Requête de test avec timeout
            $.ajax({
                url: testUrl,
                type: 'GET',
                timeout: 5000, // 5 secondes de timeout
                success: function(response) {
                    var latency = Date.now() - testStart;
                    var testResult;
                    
                    if (latency > this.config.highLatencyThreshold) {
                        testResult = 'very_slow';
                    } else if (latency > this.config.highLatencyThreshold / 2) {
                        testResult = 'slow';
                    } else {
                        testResult = 'normal';
                    }
                    
                    if (this.config.enableDebug) {
                        console.log('Test réseau terminé:', {
                            latency: latency + 'ms',
                            result: testResult
                        });
                    }
                    
                    callback(testResult);
                }.bind(this),
                error: function(xhr, status, error) {
                    // En cas d'erreur, vérifier si c'est un timeout ou une erreur réseau
                    if (status === 'timeout') {
                        if (this.config.enableDebug) {
                            console.log('Test réseau: timeout');
                        }
                        callback('very_slow');
                    } else {
                        if (this.config.enableDebug) {
                            console.log('Test réseau: erreur', error);
                        }
                        
                        // Vérifier si nous sommes vraiment hors-ligne ou si c'est une autre erreur
                        if (!navigator.onLine) {
                            callback('offline');
                        } else {
                            callback('very_slow');
                        }
                    }
                }.bind(this)
            });
        },
        
        /**
         * Met à jour l'affichage du statut
         */
        updateStatusDisplay: function() {
            var $indicator = $('#life-travel-offline-indicator');
            var $statusText = $indicator.find('.status-text');
            var $statusIcon = $indicator.find('.status-icon');
            
            // Reset classes
            $indicator.removeClass('online offline slow very-slow unknown');
            
            // Définir l'apparence en fonction du statut
            switch (state.networkStatus) {
                case 'offline':
                    $indicator.addClass('offline').show();
                    $statusText.text(lifeTravelOffline.l10n.offline_mode);
                    $statusIcon.html('<i class="dashicons dashicons-marker"></i>');
                    break;
                case 'very_slow':
                    $indicator.addClass('very-slow').show();
                    $statusText.text(lifeTravelOffline.l10n.limited_functionality);
                    $statusIcon.html('<i class="dashicons dashicons-warning"></i>');
                    break;
                case 'slow':
                    $indicator.addClass('slow').show();
                    $statusText.text(lifeTravelOffline.l10n.offline_data_available);
                    $statusIcon.html('<i class="dashicons dashicons-backup"></i>');
                    break;
                case 'normal':
                    $indicator.addClass('online').show();
                    // Cacher après un délai
                    setTimeout(function() {
                        $indicator.fadeOut(500);
                    }, this.config.statusDisplayDuration);
                    $statusText.text('');
                    $statusIcon.html('<i class="dashicons dashicons-yes"></i>');
                    break;
                default:
                    $indicator.addClass('unknown').hide();
                    $statusText.text('');
                    $statusIcon.html('');
            }
            
            // Mettre à jour le bouton de synchronisation
            if (state.pendingSync && state.networkStatus !== 'offline') {
                $('#life-travel-sync-button').show();
            } else {
                $('#life-travel-sync-button').hide();
            }
            
            // Ajouter une classe au body pour le styling CSS
            $('body').removeClass('lt-offline lt-slow lt-very-slow lt-normal lt-unknown')
                    .addClass('lt-' + (state.networkStatus === 'very_slow' ? 'very-slow' : state.networkStatus));
        },
        
        /**
         * Gère les changements de statut réseau
         * 
         * @param {Event} event Événement
         * @param {string} status Nouveau statut réseau
         */
        handleNetworkStatusChange: function(event, status) {
            if (this.config.enableDebug) {
                console.log('Changement de statut réseau:', status);
            }
            
            // Déclencher des actions spécifiques en fonction du changement de statut
            if (status === 'offline') {
                this.showNotification(lifeTravelOffline.l10n.offline_mode, 'warning', 0);
            } else if (status === 'normal' && state.pendingSync) {
                this.showNotification(lifeTravelOffline.l10n.sync_needed, 'info', 10000);
            }
        },
        
        /**
         * Gère la demande de synchronisation
         */
        handleSyncRequired: function() {
            state.pendingSync = true;
            
            // Afficher le bouton de synchronisation si en ligne
            if (state.networkStatus !== 'offline') {
                $('#life-travel-sync-button').show();
                this.showNotification(lifeTravelOffline.l10n.sync_needed, 'info', 10000);
            }
        },
        
        /**
         * Gère la fin de synchronisation
         */
        handleSyncComplete: function() {
            state.pendingSync = false;
            $('#life-travel-sync-button').hide();
            this.showNotification(lifeTravelOffline.l10n.sync_success, 'success', 5000);
        },
        
        /**
         * Gère la soumission de formulaire
         * 
         * @param {Event} event Événement
         * @return {boolean} True pour continuer, false pour annuler
         */
        handleFormSubmit: function(event) {
            // Si nous sommes hors-ligne, intercepter les formulaires
            if (state.networkStatus === 'offline') {
                var $form = $(event.target);
                
                // Vérifier si le formulaire est compatible avec le mode hors-ligne
                if ($form.hasClass('life-travel-offline-compatible')) {
                    // Stocker les données du formulaire pour synchronisation ultérieure
                    this.storeFormData($form);
                    
                    // Afficher un message à l'utilisateur
                    this.showNotification(
                        lifeTravelOffline.l10n.offline_data_available,
                        'info',
                        5000
                    );
                    
                    // Marquer comme nécessitant une synchronisation
                    state.pendingSync = true;
                    
                    // Empêcher la soumission normale
                    event.preventDefault();
                    return false;
                } else {
                    // Formulaire non compatible avec le mode hors-ligne
                    this.showNotification(
                        lifeTravelOffline.l10n.limited_functionality,
                        'warning',
                        5000
                    );
                    
                    // Empêcher la soumission
                    event.preventDefault();
                    return false;
                }
            }
            
            // Continuer normalement si en ligne
            return true;
        },
        
        /**
         * Stocke les données d'un formulaire pour synchronisation ultérieure
         * 
         * @param {jQuery} $form Formulaire jQuery
         */
        storeFormData: function($form) {
            var formData = $form.serializeArray();
            var formType = $form.data('form-type') || 'unknown';
            var formId = $form.attr('id') || 'form_' + Date.now();
            
            // Déclencher l'événement de stockage pour que le module de stockage le gère
            $(document).trigger('life_travel_store_form_data', [formType, formId, formData]);
        },
        
        /**
         * Gère les clics sur les boutons d'action
         * 
         * @param {Event} event Événement
         */
        handleActionClick: function(event) {
            var $button = $(event.currentTarget);
            var action = $button.data('action');
            
            switch (action) {
                case 'sync':
                    // Déclencher une synchronisation
                    $(document).trigger('life_travel_start_sync');
                    break;
                case 'retry':
                    // Réessayer une action
                    var actionId = $button.data('action-id');
                    $(document).trigger('life_travel_retry_action', [actionId]);
                    break;
            }
        },
        
        /**
         * Affiche une notification
         * 
         * @param {string} message Message à afficher
         * @param {string} type Type de notification (info, success, warning, error)
         * @param {number} duration Durée d'affichage en ms (0 = ne pas masquer)
         */
        showNotification: function(message, type, duration) {
            // Créer le conteneur de notifications s'il n'existe pas
            if ($('#life-travel-notifications').length === 0) {
                $('body').append('<div id="life-travel-notifications"></div>');
            }
            
            // Générer un ID unique
            var notificationId = 'notification-' + Date.now();
            
            // Créer la notification
            var $notification = $(
                '<div class="life-travel-notification life-travel-notification-' + type + '" id="' + notificationId + '">' +
                '<span class="notification-message">' + message + '</span>' +
                '<span class="notification-close">×</span>' +
                '</div>'
            );
            
            // Ajouter au conteneur
            $('#life-travel-notifications').append($notification);
            
            // Afficher avec animation
            $notification.hide().slideDown(300);
            
            // Gérer la fermeture
            $notification.find('.notification-close').on('click', function() {
                $notification.slideUp(300, function() {
                    $(this).remove();
                });
            });
            
            // Masquer automatiquement après la durée spécifiée
            if (duration > 0) {
                setTimeout(function() {
                    $notification.slideUp(300, function() {
                        $(this).remove();
                    });
                }, duration);
            }
            
            // Stocker pour référence
            state.notifications.push({
                id: notificationId,
                message: message,
                type: type,
                timestamp: Date.now()
            });
            
            return notificationId;
        },
        
        /**
         * Obtient l'état actuel du réseau
         * 
         * @return {Object} État actuel
         */
        getState: function() {
            return $.extend({}, state);
        },
        
        /**
         * Obtient le statut réseau actuel
         * 
         * @return {string} Statut réseau
         */
        getNetworkStatus: function() {
            return state.networkStatus;
        }
    };

    // Initialiser au chargement de la page
    $(function() {
        window.LifeTravelOfflineCore = LifeTravelOfflineCore.init();
    });

})(jQuery);
