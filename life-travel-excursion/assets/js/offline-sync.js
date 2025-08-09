/**
 * Life Travel Offline Sync
 * 
 * Module de synchronisation pour envoyer les données hors-ligne vers le serveur
 * 
 * @package Life Travel Excursion
 * @version 2.5.0
 */

/* global LifeTravelOfflineCore, LifeTravelOfflineStorage, lifeTravelOffline */
(function($) {
    'use strict';

    // Configuration par défaut
    var defaultConfig = {
        syncEndpoint: lifeTravelOffline.ajax_url,
        syncInterval: 60 * 1000, // 60 secondes
        maxRetryAttempts: 3,
        retryDelay: 10 * 1000, // 10 secondes
        batchSize: 10,
        enableDebug: false
    };

    // État de synchronisation
    var state = {
        syncing: false,
        lastSync: 0,
        syncQueue: {
            forms: [],
            bookings: [],
            carts: [],
            viewedExcursions: [],
            favoriteExcursions: []
        },
        retryCount: 0,
        syncStats: {
            totalSynced: 0,
            successCount: 0,
            failCount: 0,
            lastSyncTime: 0
        }
    };

    // Interface principale
    var LifeTravelOfflineSync = {
        /**
         * Initialise le module
         */
        init: function() {
            // Fusionner la configuration
            this.config = $.extend({}, defaultConfig, window.lifeTravelOfflineSync || {});
            
            // Initialiser les écouteurs d'événements
            this.initEventListeners();
            
            // Vérifier périodiquement s'il y a des données à synchroniser
            setInterval(this.checkSyncNeeded.bind(this), this.config.syncInterval);
            
            if (this.config.enableDebug) {
                console.log('Life Travel Offline Sync initialisé avec la configuration:', this.config);
            }
            
            // Attendre que le stockage soit prêt
            $(document).on('life_travel_storage_ready', function() {
                // Vérifier les données à synchroniser
                setTimeout(this.loadSyncQueue.bind(this), 1000);
            }.bind(this));
            
            return this;
        },
        
        /**
         * Initialise les écouteurs d'événements
         */
        initEventListeners: function() {
            // Événement pour démarrer la synchronisation
            $(document).on('life_travel_start_sync', this.startSync.bind(this));
            
            // Événement quand le statut réseau change
            $(document).on('life_travel_network_status_change', this.handleNetworkStatusChange.bind(this));
            
            // Bouton de synchronisation dans l'interface
            $(document).on('click', '#life-travel-sync-button', this.startSync.bind(this));
        },
        
        /**
         * Charge la file d'attente de synchronisation depuis le stockage local
         */
        loadSyncQueue: function() {
            var self = this;
            
            // Charger les formulaires non synchronisés
            LifeTravelOfflineStorage.getUnsyncedData('forms', function(forms) {
                state.syncQueue.forms = forms;
                
                // Charger les réservations non synchronisées
                LifeTravelOfflineStorage.getUnsyncedData('bookings', function(bookings) {
                    state.syncQueue.bookings = bookings;
                    
                    // Charger les paniers non synchronisés
                    LifeTravelOfflineStorage.getUnsyncedData('carts', function(carts) {
                        state.syncQueue.carts = carts;
                        
                        self.updateSyncStatus();
                    });
                });
            });
        },
        
        /**
         * Met à jour le statut de synchronisation
         */
        updateSyncStatus: function() {
            // Calculer le nombre total d'éléments à synchroniser
            var totalItems = 
                state.syncQueue.forms.length + 
                state.syncQueue.bookings.length + 
                state.syncQueue.carts.length;
            
            // Si des éléments sont en attente, déclencher l'événement
            if (totalItems > 0) {
                $(document).trigger('life_travel_sync_required');
                
                if (this.config.enableDebug) {
                    console.log('Données en attente de synchronisation:', totalItems, 'éléments');
                }
                
                // Si la connexion est bonne, démarrer la synchronisation automatiquement
                var networkStatus = LifeTravelOfflineCore.getNetworkStatus();
                if (networkStatus === 'normal' || networkStatus === 'slow') {
                    setTimeout(this.startSync.bind(this), 2000);
                }
            }
        },
        
        /**
         * Vérifie s'il y a des données à synchroniser
         */
        checkSyncNeeded: function() {
            // Ne pas vérifier si déjà en cours de synchronisation
            if (state.syncing) {
                return;
            }
            
            // Vérifier si assez de temps s'est écoulé depuis la dernière synchronisation
            var timeSinceLastSync = Date.now() - state.lastSync;
            if (timeSinceLastSync < this.config.syncInterval) {
                return;
            }
            
            // Recharger la file d'attente
            this.loadSyncQueue();
        },
        
        /**
         * Gère les changements de statut réseau
         * 
         * @param {Event} event Événement
         * @param {string} status Nouveau statut réseau
         */
        handleNetworkStatusChange: function(event, status) {
            // Si le réseau est de retour et qu'il y a des données à synchroniser
            if ((status === 'normal' || status === 'slow') && !state.syncing) {
                // Vérifier s'il y a des données à synchroniser
                var totalItems = 
                    state.syncQueue.forms.length + 
                    state.syncQueue.bookings.length + 
                    state.syncQueue.carts.length;
                
                if (totalItems > 0) {
                    // Attendre un peu avant de démarrer la synchronisation
                    setTimeout(this.startSync.bind(this), 3000);
                }
            }
        },
        
        /**
         * Démarre le processus de synchronisation
         */
        startSync: function() {
            // Ne pas démarrer si déjà en cours
            if (state.syncing) {
                return;
            }
            
            // Vérifier la connexion
            var networkStatus = LifeTravelOfflineCore.getNetworkStatus();
            if (networkStatus === 'offline') {
                if (this.config.enableDebug) {
                    console.log('Impossible de synchroniser: mode hors-ligne');
                }
                
                // Afficher une notification
                LifeTravelOfflineCore.showNotification(
                    lifeTravelOffline.l10n.offline_mode + ' - ' + lifeTravelOffline.l10n.sync_error,
                    'warning',
                    5000
                );
                
                return;
            }
            
            // Démarrer la synchronisation
            state.syncing = true;
            state.retryCount = 0;
            
            // Afficher une notification
            LifeTravelOfflineCore.showNotification(
                lifeTravelOffline.l10n.syncing,
                'info',
                0
            );
            
            // Changer l'apparence du bouton de synchronisation
            $('#life-travel-sync-button').addClass('syncing');
            
            // Recharger la file d'attente
            this.loadSyncQueue();
            
            // Commencer par synchroniser les paniers
            this.syncCarts();
        },
        
        /**
         * Synchronise les paniers
         */
        syncCarts: function() {
            var self = this;
            
            // Vérifier s'il y a des paniers à synchroniser
            if (state.syncQueue.carts.length === 0) {
                // Passer à l'étape suivante
                this.syncBookings();
                return;
            }
            
            // Limiter le nombre d'éléments à traiter
            var batchSize = Math.min(this.config.batchSize, state.syncQueue.carts.length);
            var batch = state.syncQueue.carts.slice(0, batchSize);
            
            // Préparer les données
            var cartData = {
                'action': 'life_travel_sync_offline_data',
                'security': lifeTravelOffline.nonce,
                'data_type': 'cart_items',
                'device_id': LifeTravelOfflineStorage.deviceId,
                'data': JSON.stringify(batch[0].items)
            };
            
            // Envoyer au serveur
            $.ajax({
                url: this.config.syncEndpoint,
                type: 'POST',
                data: cartData,
                success: function(response) {
                    if (response.success) {
                        // Marquer comme synchronisé
                        var ids = batch.map(function(item) { return item.id; });
                        LifeTravelOfflineStorage.markAsSynced('carts', ids);
                        
                        // Mettre à jour les stats
                        state.syncStats.totalSynced += batch.length;
                        state.syncStats.successCount++;
                        
                        // Retirer de la file d'attente
                        state.syncQueue.carts = state.syncQueue.carts.slice(batchSize);
                        
                        // Continuer avec les éléments restants
                        if (state.syncQueue.carts.length > 0) {
                            setTimeout(function() {
                                self.syncCarts();
                            }, 500);
                        } else {
                            // Passer à l'étape suivante
                            self.syncBookings();
                        }
                    } else {
                        // Erreur dans la réponse
                        self.handleSyncError('carts', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    // Erreur réseau
                    self.handleSyncError('carts', error);
                }
            });
        },
        
        /**
         * Synchronise les réservations
         */
        syncBookings: function() {
            var self = this;
            
            // Vérifier s'il y a des réservations à synchroniser
            if (state.syncQueue.bookings.length === 0) {
                // Passer à l'étape suivante
                this.syncForms();
                return;
            }
            
            // Limiter le nombre d'éléments à traiter
            var batchSize = Math.min(this.config.batchSize, state.syncQueue.bookings.length);
            var batch = state.syncQueue.bookings.slice(0, batchSize);
            
            // Traiter une réservation à la fois
            var booking = batch[0];
            
            // Préparer les données
            var bookingData = {
                'action': 'life_travel_sync_offline_data',
                'security': lifeTravelOffline.nonce,
                'data_type': 'booking_request',
                'device_id': LifeTravelOfflineStorage.deviceId,
                'data': JSON.stringify(booking)
            };
            
            // Envoyer au serveur
            $.ajax({
                url: this.config.syncEndpoint,
                type: 'POST',
                data: bookingData,
                success: function(response) {
                    if (response.success) {
                        // Marquer comme synchronisé
                        LifeTravelOfflineStorage.markAsSynced('bookings', booking.id);
                        
                        // Mettre à jour les stats
                        state.syncStats.totalSynced++;
                        state.syncStats.successCount++;
                        
                        // Retirer de la file d'attente
                        state.syncQueue.bookings = state.syncQueue.bookings.slice(1);
                        
                        // Continuer avec les éléments restants
                        if (state.syncQueue.bookings.length > 0) {
                            setTimeout(function() {
                                self.syncBookings();
                            }, 500);
                        } else {
                            // Passer à l'étape suivante
                            self.syncForms();
                        }
                    } else {
                        // Erreur dans la réponse
                        self.handleSyncError('bookings', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    // Erreur réseau
                    self.handleSyncError('bookings', error);
                }
            });
        },
        
        /**
         * Synchronise les formulaires
         */
        syncForms: function() {
            var self = this;
            
            // Vérifier s'il y a des formulaires à synchroniser
            if (state.syncQueue.forms.length === 0) {
                // Terminer la synchronisation
                this.finishSync();
                return;
            }
            
            // Limiter le nombre d'éléments à traiter
            var batchSize = Math.min(this.config.batchSize, state.syncQueue.forms.length);
            var batch = state.syncQueue.forms.slice(0, batchSize);
            
            // Traiter un formulaire à la fois
            var form = batch[0];
            
            // Déterminer l'action en fonction du type de formulaire
            var action = 'life_travel_sync_offline_data';
            var dataType = 'forms';
            
            // Adapter l'action si nécessaire
            if (form.formType === 'booking') {
                dataType = 'booking_request';
            } else if (form.formType === 'contact') {
                dataType = 'contact_form';
            } else if (form.formType === 'user_preferences') {
                dataType = 'user_preferences';
            }
            
            // Préparer les données
            var formData = {
                'action': action,
                'security': lifeTravelOffline.nonce,
                'data_type': dataType,
                'device_id': LifeTravelOfflineStorage.deviceId,
                'data': JSON.stringify(form.data)
            };
            
            // Envoyer au serveur
            $.ajax({
                url: this.config.syncEndpoint,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Marquer comme synchronisé
                        LifeTravelOfflineStorage.markAsSynced('forms', form.id);
                        
                        // Mettre à jour les stats
                        state.syncStats.totalSynced++;
                        state.syncStats.successCount++;
                        
                        // Retirer de la file d'attente
                        state.syncQueue.forms = state.syncQueue.forms.slice(1);
                        
                        // Continuer avec les éléments restants
                        if (state.syncQueue.forms.length > 0) {
                            setTimeout(function() {
                                self.syncForms();
                            }, 500);
                        } else {
                            // Terminer la synchronisation
                            self.finishSync();
                        }
                    } else {
                        // Erreur dans la réponse
                        self.handleSyncError('forms', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    // Erreur réseau
                    self.handleSyncError('forms', error);
                }
            });
        },
        
        /**
         * Gère les erreurs de synchronisation
         * 
         * @param {string} type Type de données en erreur
         * @param {string} message Message d'erreur
         */
        handleSyncError: function(type, message) {
            // Incrémenter le compteur d'erreurs
            state.retryCount++;
            state.syncStats.failCount++;
            
            if (this.config.enableDebug) {
                console.error('Erreur de synchronisation (' + type + '):', message);
            }
            
            // Vérifier si on peut réessayer
            if (state.retryCount < this.config.maxRetryAttempts) {
                var self = this;
                
                // Notifier l'utilisateur
                LifeTravelOfflineCore.showNotification(
                    lifeTravelOffline.l10n.sync_error + ' - ' + lifeTravelOffline.l10n.retry,
                    'warning',
                    5000
                );
                
                // Attendre avant de réessayer
                setTimeout(function() {
                    switch (type) {
                        case 'carts':
                            self.syncCarts();
                            break;
                        case 'bookings':
                            self.syncBookings();
                            break;
                        case 'forms':
                            self.syncForms();
                            break;
                    }
                }, this.config.retryDelay);
            } else {
                // Trop d'échecs, terminer la synchronisation
                this.finishSync(true);
                
                // Notifier l'utilisateur
                LifeTravelOfflineCore.showNotification(
                    lifeTravelOffline.l10n.sync_error,
                    'error',
                    10000
                );
            }
        },
        
        /**
         * Termine le processus de synchronisation
         * 
         * @param {boolean} withError Indique si la synchronisation s'est terminée avec une erreur
         */
        finishSync: function(withError) {
            // Mettre à jour l'état
            state.syncing = false;
            state.lastSync = Date.now();
            state.syncStats.lastSyncTime = Date.now();
            
            // Mettre à jour le bouton de synchronisation
            $('#life-travel-sync-button').removeClass('syncing');
            
            // Vérifier s'il reste des éléments à synchroniser
            var remainingItems = 
                state.syncQueue.forms.length + 
                state.syncQueue.bookings.length + 
                state.syncQueue.carts.length;
            
            if (remainingItems === 0) {
                // Tout est synchronisé
                $('#life-travel-sync-button').hide();
                
                // Notifier le succès
                LifeTravelOfflineCore.showNotification(
                    lifeTravelOffline.l10n.sync_success,
                    'success',
                    5000
                );
                
                // Déclencher l'événement de fin de synchronisation
                $(document).trigger('life_travel_sync_complete', [state.syncStats]);
            } else if (!withError) {
                // Certains éléments n'ont pas été synchronisés, mais sans erreur
                // Réessayer après un délai
                setTimeout(this.startSync.bind(this), this.config.syncInterval);
            }
            
            if (this.config.enableDebug) {
                console.log('Synchronisation terminée:', {
                    stats: state.syncStats,
                    remaining: remainingItems,
                    withError: withError
                });
            }
        },
        
        /**
         * Obtient l'état actuel de la synchronisation
         * 
         * @return {Object} État actuel
         */
        getState: function() {
            return $.extend({}, state);
        }
    };

    // Initialiser au chargement de la page
    $(function() {
        window.LifeTravelOfflineSync = LifeTravelOfflineSync.init();
    });

})(jQuery);
