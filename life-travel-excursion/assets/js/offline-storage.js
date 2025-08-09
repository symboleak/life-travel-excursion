/**
 * Life Travel Offline Storage
 * 
 * Module de stockage local avec IndexedDB pour le mode hors-ligne
 * 
 * @package Life Travel Excursion
 * @version 2.5.0
 */

/* global LifeTravelOfflineCore */
(function($) {
    'use strict';

    // Configuration par défaut
    var defaultConfig = {
        dbName: 'LifeTravelOfflineDB',
        dbVersion: 1,
        maxStorageSize: 10 * 1024 * 1024, // 10 MB
        expiryTime: 24 * 60 * 60 * 1000, // 24 heures en ms
        enableDebug: false
    };

    // État du stockage
    var state = {
        db: null,
        ready: false,
        pendingOperations: [],
        error: null
    };

    // Structures des tables
    var stores = {
        settings: { keyPath: 'id' },
        excursions: { keyPath: 'id', indexes: [{ name: 'timestamp', keyPath: 'timestamp' }] },
        carts: { keyPath: 'id' },
        bookings: { keyPath: 'id', indexes: [{ name: 'timestamp', keyPath: 'timestamp' }] },
        forms: { keyPath: 'id', indexes: [{ name: 'formType', keyPath: 'formType' }] }
    };

    // Interface principale
    var LifeTravelOfflineStorage = {
        /**
         * Initialise le module
         */
        init: function() {
            // Fusionner la configuration
            this.config = $.extend({}, defaultConfig, window.lifeTravelOfflineStorage || {});
            
            // Initialiser la base de données
            this.initDatabase();
            
            // Initialiser les écouteurs d'événements
            this.initEventListeners();
            
            // Définir le device ID
            this.initDeviceId();
            
            if (this.config.enableDebug) {
                console.log('Life Travel Offline Storage initialisé avec la configuration:', this.config);
            }
            
            return this;
        },
        
        /**
         * Initialise les écouteurs d'événements
         */
        initEventListeners: function() {
            // Événements liés aux formulaires
            $(document).on('life_travel_store_form_data', this.handleStoreFormData.bind(this));
            
            // Événements liés au stockage d'autres données
            $(document).on('life_travel_store_excursion', this.handleStoreExcursion.bind(this));
            $(document).on('life_travel_store_cart', this.handleStoreCart.bind(this));
            $(document).on('life_travel_store_booking', this.handleStoreBooking.bind(this));
            
            // Événements de récupération de données
            $(document).on('life_travel_get_stored_data', this.handleGetStoredData.bind(this));
            
            // Événements de nettoyage
            $(document).on('life_travel_cleanup_expired', this.cleanupExpiredData.bind(this));
        },
        
        /**
         * Initialise l'identifiant unique de l'appareil
         */
        initDeviceId: function() {
            // Vérifier si un ID existe déjà
            var deviceId = localStorage.getItem('life_travel_device_id');
            
            if (!deviceId) {
                // Générer un nouvel ID
                deviceId = 'device_' + Date.now() + '_' + Math.random().toString(36).substring(2, 15);
                localStorage.setItem('life_travel_device_id', deviceId);
            }
            
            this.deviceId = deviceId;
        },
        
        /**
         * Initialise la base de données IndexedDB
         */
        initDatabase: function() {
            var self = this;
            
            // Vérifier le support d'IndexedDB
            if (!window.indexedDB) {
                console.error('Votre navigateur ne supporte pas IndexedDB. Le stockage hors-ligne ne sera pas disponible.');
                state.error = 'INDEXEDDB_NOT_SUPPORTED';
                return;
            }
            
            // Ouvrir la base de données
            var request = indexedDB.open(this.config.dbName, this.config.dbVersion);
            
            // Gérer les erreurs
            request.onerror = function(event) {
                console.error('Erreur lors de l\'ouverture de la base de données:', event.target.error);
                state.error = 'DB_OPEN_ERROR';
            };
            
            // Mettre à niveau le schéma si nécessaire
            request.onupgradeneeded = function(event) {
                var db = event.target.result;
                
                // Créer les object stores nécessaires
                Object.keys(stores).forEach(function(storeName) {
                    var storeConfig = stores[storeName];
                    
                    // Vérifier si l'object store existe déjà
                    if (!db.objectStoreNames.contains(storeName)) {
                        // Créer l'object store
                        var objectStore = db.createObjectStore(storeName, { keyPath: storeConfig.keyPath });
                        
                        // Créer les index si définis
                        if (storeConfig.indexes) {
                            storeConfig.indexes.forEach(function(index) {
                                objectStore.createIndex(index.name, index.keyPath, { unique: index.unique || false });
                            });
                        }
                        
                        if (self.config.enableDebug) {
                            console.log('Object store créé:', storeName);
                        }
                    }
                });
            };
            
            // Base de données ouverte avec succès
            request.onsuccess = function(event) {
                state.db = event.target.result;
                state.ready = true;
                
                if (self.config.enableDebug) {
                    console.log('Base de données ouverte avec succès');
                }
                
                // Exécuter les opérations en attente
                self.processPendingOperations();
                
                // Déclencher l'événement prêt
                $(document).trigger('life_travel_storage_ready');
                
                // Nettoyer les données expirées
                self.cleanupExpiredData();
            };
        },
        
        /**
         * Traite les opérations en attente
         */
        processPendingOperations: function() {
            if (state.pendingOperations.length > 0 && state.ready) {
                state.pendingOperations.forEach(function(operation) {
                    operation.execute();
                });
                
                state.pendingOperations = [];
            }
        },
        
        /**
         * Ajoute une opération en attente
         * 
         * @param {Function} operation Fonction à exécuter
         */
        addPendingOperation: function(operation) {
            if (state.ready) {
                operation();
            } else {
                state.pendingOperations.push({ execute: operation });
            }
        },
        
        /**
         * Gère le stockage des données de formulaire
         * 
         * @param {Event} event Événement
         * @param {string} formType Type de formulaire
         * @param {string} formId ID du formulaire
         * @param {Array} formData Données du formulaire
         */
        handleStoreFormData: function(event, formType, formId, formData) {
            var data = {
                id: formId,
                formType: formType,
                data: formData,
                timestamp: Date.now(),
                synced: false,
                deviceId: this.deviceId
            };
            
            this.storeData('forms', data, function(success) {
                if (success) {
                    if (this.config.enableDebug) {
                        console.log('Formulaire stocké avec succès:', formId);
                    }
                    
                    // Marquer comme nécessitant une synchronisation
                    $(document).trigger('life_travel_sync_required');
                } else {
                    console.error('Erreur lors du stockage du formulaire:', formId);
                }
            }.bind(this));
        },
        
        /**
         * Gère le stockage d'une excursion
         * 
         * @param {Event} event Événement
         * @param {Object} excursionData Données de l'excursion
         */
        handleStoreExcursion: function(event, excursionData) {
            var data = $.extend({}, excursionData, {
                timestamp: Date.now(),
                deviceId: this.deviceId
            });
            
            this.storeData('excursions', data);
        },
        
        /**
         * Gère le stockage du panier
         * 
         * @param {Event} event Événement
         * @param {Object} cartData Données du panier
         */
        handleStoreCart: function(event, cartData) {
            var data = {
                id: 'cart_' + this.deviceId,
                items: cartData,
                timestamp: Date.now(),
                synced: false,
                deviceId: this.deviceId
            };
            
            this.storeData('carts', data);
        },
        
        /**
         * Gère le stockage d'une réservation
         * 
         * @param {Event} event Événement
         * @param {Object} bookingData Données de réservation
         */
        handleStoreBooking: function(event, bookingData) {
            var bookingId = bookingData.id || 'booking_' + Date.now();
            var data = $.extend({}, bookingData, {
                id: bookingId,
                timestamp: Date.now(),
                synced: false,
                deviceId: this.deviceId
            });
            
            this.storeData('bookings', data, function(success) {
                if (success) {
                    // Marquer comme nécessitant une synchronisation
                    $(document).trigger('life_travel_sync_required');
                    
                    // Notification
                    if (LifeTravelOfflineCore) {
                        LifeTravelOfflineCore.showNotification(
                            'Réservation enregistrée localement et sera synchronisée quand la connexion sera rétablie',
                            'info',
                            5000
                        );
                    }
                }
            });
        },
        
        /**
         * Gère la récupération de données stockées
         * 
         * @param {Event} event Événement
         * @param {string} storeName Nom du store
         * @param {string|number} key Clé à récupérer (optionnel)
         * @param {Function} callback Fonction de rappel
         */
        handleGetStoredData: function(event, storeName, key, callback) {
            if (typeof key === 'function' && !callback) {
                callback = key;
                key = null;
            }
            
            if (key) {
                this.getData(storeName, key, callback);
            } else {
                this.getAllData(storeName, callback);
            }
        },
        
        /**
         * Stocke des données dans un store
         * 
         * @param {string} storeName Nom du store
         * @param {Object} data Données à stocker
         * @param {Function} callback Fonction de rappel (optionnel)
         */
        storeData: function(storeName, data, callback) {
            this.addPendingOperation(function() {
                var transaction = state.db.transaction([storeName], 'readwrite');
                var store = transaction.objectStore(storeName);
                
                // Ajouter les données
                var request = store.put(data);
                
                request.onsuccess = function() {
                    if (callback) callback(true);
                };
                
                request.onerror = function(event) {
                    console.error('Erreur lors du stockage des données dans ' + storeName + ':', event.target.error);
                    if (callback) callback(false);
                };
            });
        },
        
        /**
         * Récupère des données d'un store
         * 
         * @param {string} storeName Nom du store
         * @param {string|number} key Clé à récupérer
         * @param {Function} callback Fonction de rappel
         */
        getData: function(storeName, key, callback) {
            this.addPendingOperation(function() {
                var transaction = state.db.transaction([storeName], 'readonly');
                var store = transaction.objectStore(storeName);
                
                // Récupérer les données
                var request = store.get(key);
                
                request.onsuccess = function(event) {
                    callback(event.target.result);
                };
                
                request.onerror = function(event) {
                    console.error('Erreur lors de la récupération des données de ' + storeName + ':', event.target.error);
                    callback(null);
                };
            });
        },
        
        /**
         * Récupère toutes les données d'un store
         * 
         * @param {string} storeName Nom du store
         * @param {Function} callback Fonction de rappel
         */
        getAllData: function(storeName, callback) {
            this.addPendingOperation(function() {
                var transaction = state.db.transaction([storeName], 'readonly');
                var store = transaction.objectStore(storeName);
                
                // Récupérer toutes les données
                var request = store.getAll();
                
                request.onsuccess = function(event) {
                    callback(event.target.result);
                };
                
                request.onerror = function(event) {
                    console.error('Erreur lors de la récupération de toutes les données de ' + storeName + ':', event.target.error);
                    callback([]);
                };
            });
        },
        
        /**
         * Récupère les données non synchronisées
         * 
         * @param {string} storeName Nom du store
         * @param {Function} callback Fonction de rappel
         */
        getUnsyncedData: function(storeName, callback) {
            this.addPendingOperation(function() {
                var transaction = state.db.transaction([storeName], 'readonly');
                var store = transaction.objectStore(storeName);
                var unsyncedData = [];
                
                // Parcourir toutes les données
                var cursorRequest = store.openCursor();
                
                cursorRequest.onsuccess = function(event) {
                    var cursor = event.target.result;
                    
                    if (cursor) {
                        // Vérifier si non synchronisé
                        if (cursor.value.synced === false) {
                            unsyncedData.push(cursor.value);
                        }
                        
                        cursor.continue();
                    } else {
                        // Fin du parcours
                        callback(unsyncedData);
                    }
                };
                
                cursorRequest.onerror = function(event) {
                    console.error('Erreur lors de la récupération des données non synchronisées de ' + storeName + ':', event.target.error);
                    callback([]);
                };
            });
        },
        
        /**
         * Marque des données comme synchronisées
         * 
         * @param {string} storeName Nom du store
         * @param {string|Array} ids IDs à marquer
         * @param {Function} callback Fonction de rappel (optionnel)
         */
        markAsSynced: function(storeName, ids, callback) {
            if (!Array.isArray(ids)) {
                ids = [ids];
            }
            
            this.addPendingOperation(function() {
                var transaction = state.db.transaction([storeName], 'readwrite');
                var store = transaction.objectStore(storeName);
                var remaining = ids.length;
                var successCount = 0;
                
                ids.forEach(function(id) {
                    // Récupérer l'objet
                    var getRequest = store.get(id);
                    
                    getRequest.onsuccess = function(event) {
                        var data = event.target.result;
                        
                        if (data) {
                            // Marquer comme synchronisé
                            data.synced = true;
                            data.syncTimestamp = Date.now();
                            
                            // Mettre à jour
                            var updateRequest = store.put(data);
                            
                            updateRequest.onsuccess = function() {
                                successCount++;
                                checkDone();
                            };
                            
                            updateRequest.onerror = function() {
                                checkDone();
                            };
                        } else {
                            checkDone();
                        }
                    };
                    
                    getRequest.onerror = function() {
                        checkDone();
                    };
                });
                
                function checkDone() {
                    remaining--;
                    
                    if (remaining === 0 && callback) {
                        callback(successCount === ids.length);
                    }
                }
            });
        },
        
        /**
         * Nettoie les données expirées
         */
        cleanupExpiredData: function() {
            var self = this;
            var now = Date.now();
            var expiryTime = this.config.expiryTime;
            var expiryTimestamp = now - expiryTime;
            
            // Nettoyer chaque store sauf les paramètres
            Object.keys(stores).forEach(function(storeName) {
                if (storeName !== 'settings') {
                    self.cleanupStore(storeName, expiryTimestamp);
                }
            });
        },
        
        /**
         * Nettoie un store spécifique
         * 
         * @param {string} storeName Nom du store
         * @param {number} expiryTimestamp Timestamp d'expiration
         */
        cleanupStore: function(storeName, expiryTimestamp) {
            this.addPendingOperation(function() {
                var transaction = state.db.transaction([storeName], 'readwrite');
                var store = transaction.objectStore(storeName);
                
                // Vérifier si l'index timestamp existe
                if (store.indexNames.contains('timestamp')) {
                    var index = store.index('timestamp');
                    var range = IDBKeyRange.upperBound(expiryTimestamp);
                    
                    // Utiliser l'index pour récupérer les anciennes entrées
                    var request = index.openCursor(range);
                    var deleteCount = 0;
                    
                    request.onsuccess = function(event) {
                        var cursor = event.target.result;
                        
                        if (cursor) {
                            // Ne pas supprimer les données non synchronisées
                            if (cursor.value.synced !== false) {
                                // Supprimer l'entrée
                                store.delete(cursor.value[stores[storeName].keyPath]);
                                deleteCount++;
                            }
                            
                            cursor.continue();
                        } else if (this.config.enableDebug && deleteCount > 0) {
                            console.log('Nettoyage de ' + storeName + ': ' + deleteCount + ' entrées supprimées');
                        }
                    }.bind(this);
                    
                    request.onerror = function(event) {
                        console.error('Erreur lors du nettoyage de ' + storeName + ':', event.target.error);
                    };
                } else {
                    // Parcourir toutes les entrées
                    var cursorRequest = store.openCursor();
                    var deleteCount = 0;
                    
                    cursorRequest.onsuccess = function(event) {
                        var cursor = event.target.result;
                        
                        if (cursor) {
                            var data = cursor.value;
                            
                            // Vérifier la date
                            if (data.timestamp && data.timestamp < expiryTimestamp) {
                                // Ne pas supprimer les données non synchronisées
                                if (data.synced !== false) {
                                    // Supprimer l'entrée
                                    store.delete(cursor.value[stores[storeName].keyPath]);
                                    deleteCount++;
                                }
                            }
                            
                            cursor.continue();
                        } else if (this.config.enableDebug && deleteCount > 0) {
                            console.log('Nettoyage de ' + storeName + ': ' + deleteCount + ' entrées supprimées');
                        }
                    }.bind(this);
                    
                    cursorRequest.onerror = function(event) {
                        console.error('Erreur lors du nettoyage de ' + storeName + ':', event.target.error);
                    };
                }
            }.bind(this));
        },
        
        /**
         * Obtient l'état actuel du stockage
         * 
         * @return {Object} État actuel
         */
        getState: function() {
            return $.extend({}, state, {
                deviceId: this.deviceId
            });
        }
    };

    // Initialiser au chargement de la page
    $(function() {
        window.LifeTravelOfflineStorage = LifeTravelOfflineStorage.init();
    });

})(jQuery);
