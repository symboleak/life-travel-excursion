/**
 * Life Travel Storage
 * Solution de stockage local intégrée remplaçant la dépendance à localforage
 * Prend en charge IndexedDB, WebSQL et localStorage avec fallback automatique
 */

(function(global) {
    'use strict';

    // Namespace
    var LifeTravelStorage = {
        _dbName: 'life_travel_storage',
        _storeName: 'life_travel_store',
        _dbVersion: 1,
        _driver: null,
        _ready: false,
        _readyCallbacks: [],
        
        // Drivers disponibles
        INDEXEDDB: 'indexedDB',
        WEBSQL: 'webSQL',
        LOCALSTORAGE: 'localStorage',
        
        /**
         * Initialise le stockage avec le meilleur driver disponible
         */
        init: function() {
            var self = this;
            
            // Déjà initialisé
            if (this._ready) {
                return Promise.resolve();
            }
            
            // Déterminer le meilleur driver
            if (this._supportsIndexedDB()) {
                this._driver = this.INDEXEDDB;
                return this._initIndexedDB().then(function() {
                    self._ready = true;
                    self._executeReadyCallbacks();
                    return Promise.resolve();
                }).catch(function(err) {
                    // Fallback vers WebSQL
                    if (self._supportsWebSQL()) {
                        self._driver = self.WEBSQL;
                        return self._initWebSQL();
                    } else {
                        // Fallback vers localStorage
                        self._driver = self.LOCALSTORAGE;
                        return self._initLocalStorage();
                    }
                });
            } else if (this._supportsWebSQL()) {
                this._driver = this.WEBSQL;
                return this._initWebSQL().then(function() {
                    self._ready = true;
                    self._executeReadyCallbacks();
                    return Promise.resolve();
                }).catch(function(err) {
                    // Fallback vers localStorage
                    self._driver = self.LOCALSTORAGE;
                    return self._initLocalStorage();
                });
            } else {
                // Utiliser localStorage si rien d'autre n'est disponible
                this._driver = this.LOCALSTORAGE;
                return this._initLocalStorage().then(function() {
                    self._ready = true;
                    self._executeReadyCallbacks();
                    return Promise.resolve();
                });
            }
        },
        
        /**
         * Vérifie si IndexedDB est supporté
         */
        _supportsIndexedDB: function() {
            return !!(window.indexedDB && 
                      window.IDBKeyRange && 
                      window.IDBTransaction);
        },
        
        /**
         * Vérifie si WebSQL est supporté
         */
        _supportsWebSQL: function() {
            return !!window.openDatabase;
        },
        
        /**
         * Initialise IndexedDB
         */
        _initIndexedDB: function() {
            var self = this;
            
            return new Promise(function(resolve, reject) {
                var request = window.indexedDB.open(self._dbName, self._dbVersion);
                
                request.onerror = function(event) {
                    reject(event.target.error);
                };
                
                request.onsuccess = function(event) {
                    self._db = event.target.result;
                    resolve();
                };
                
                request.onupgradeneeded = function(event) {
                    var db = event.target.result;
                    
                    // Créer le object store s'il n'existe pas
                    if (!db.objectStoreNames.contains(self._storeName)) {
                        db.createObjectStore(self._storeName);
                    }
                };
            });
        },
        
        /**
         * Initialise WebSQL
         */
        _initWebSQL: function() {
            var self = this;
            
            return new Promise(function(resolve, reject) {
                try {
                    self._db = window.openDatabase(
                        self._dbName,
                        '1.0',
                        'Life Travel Storage',
                        5 * 1024 * 1024 // 5MB
                    );
                    
                    self._db.transaction(function(tx) {
                        tx.executeSql(
                            'CREATE TABLE IF NOT EXISTS ' + self._storeName + ' (key TEXT PRIMARY KEY, value TEXT)',
                            [],
                            function() { resolve(); },
                            function(tx, error) { reject(error); }
                        );
                    });
                } catch (error) {
                    reject(error);
                }
            });
        },
        
        /**
         * Initialise localStorage
         */
        _initLocalStorage: function() {
            return new Promise(function(resolve, reject) {
                try {
                    if (typeof localStorage === 'undefined') {
                        reject(new Error('localStorage non supporté'));
                    } else {
                        resolve();
                    }
                } catch (error) {
                    reject(error);
                }
            });
        },
        
        /**
         * Exécute les callbacks enregistrés quand le stockage est prêt
         */
        _executeReadyCallbacks: function() {
            while (this._readyCallbacks.length > 0) {
                var callback = this._readyCallbacks.shift();
                callback();
            }
        },
        
        /**
         * S'assure que le stockage est prêt avant d'exécuter une opération
         */
        _ensureReady: function() {
            var self = this;
            
            if (this._ready) {
                return Promise.resolve();
            } else {
                return new Promise(function(resolve) {
                    self._readyCallbacks.push(resolve);
                    self.init();
                });
            }
        },
        
        /**
         * Enregistre une valeur dans le stockage
         */
        setItem: function(key, value) {
            var self = this;
            
            return this._ensureReady().then(function() {
                return new Promise(function(resolve, reject) {
                    // Convertir en string pour un stockage uniforme
                    var valueStr = JSON.stringify(value);
                    
                    if (self._driver === self.INDEXEDDB) {
                        var transaction = self._db.transaction([self._storeName], 'readwrite');
                        var store = transaction.objectStore(self._storeName);
                        var request = store.put(valueStr, key);
                        
                        request.onsuccess = function() {
                            resolve(value);
                        };
                        
                        request.onerror = function(event) {
                            reject(event.target.error);
                        };
                    } else if (self._driver === self.WEBSQL) {
                        self._db.transaction(function(tx) {
                            tx.executeSql(
                                'INSERT OR REPLACE INTO ' + self._storeName + ' (key, value) VALUES (?, ?)',
                                [key, valueStr],
                                function() { resolve(value); },
                                function(tx, error) { reject(error); }
                            );
                        });
                    } else {
                        try {
                            localStorage.setItem(self._dbName + '_' + key, valueStr);
                            resolve(value);
                        } catch (error) {
                            reject(error);
                        }
                    }
                });
            });
        },
        
        /**
         * Récupère une valeur du stockage
         */
        getItem: function(key) {
            var self = this;
            
            return this._ensureReady().then(function() {
                return new Promise(function(resolve, reject) {
                    if (self._driver === self.INDEXEDDB) {
                        var transaction = self._db.transaction([self._storeName], 'readonly');
                        var store = transaction.objectStore(self._storeName);
                        var request = store.get(key);
                        
                        request.onsuccess = function(event) {
                            var result = event.target.result;
                            if (result === undefined) {
                                resolve(null);
                            } else {
                                try {
                                    resolve(JSON.parse(result));
                                } catch (e) {
                                    resolve(result);
                                }
                            }
                        };
                        
                        request.onerror = function(event) {
                            reject(event.target.error);
                        };
                    } else if (self._driver === self.WEBSQL) {
                        self._db.transaction(function(tx) {
                            tx.executeSql(
                                'SELECT value FROM ' + self._storeName + ' WHERE key = ?',
                                [key],
                                function(tx, results) {
                                    if (results.rows.length > 0) {
                                        var value = results.rows.item(0).value;
                                        try {
                                            resolve(JSON.parse(value));
                                        } catch (e) {
                                            resolve(value);
                                        }
                                    } else {
                                        resolve(null);
                                    }
                                },
                                function(tx, error) { reject(error); }
                            );
                        });
                    } else {
                        try {
                            var value = localStorage.getItem(self._dbName + '_' + key);
                            if (value === null) {
                                resolve(null);
                            } else {
                                try {
                                    resolve(JSON.parse(value));
                                } catch (e) {
                                    resolve(value);
                                }
                            }
                        } catch (error) {
                            reject(error);
                        }
                    }
                });
            });
        },
        
        /**
         * Supprime une valeur du stockage
         */
        removeItem: function(key) {
            var self = this;
            
            return this._ensureReady().then(function() {
                return new Promise(function(resolve, reject) {
                    if (self._driver === self.INDEXEDDB) {
                        var transaction = self._db.transaction([self._storeName], 'readwrite');
                        var store = transaction.objectStore(self._storeName);
                        var request = store.delete(key);
                        
                        request.onsuccess = function() {
                            resolve();
                        };
                        
                        request.onerror = function(event) {
                            reject(event.target.error);
                        };
                    } else if (self._driver === self.WEBSQL) {
                        self._db.transaction(function(tx) {
                            tx.executeSql(
                                'DELETE FROM ' + self._storeName + ' WHERE key = ?',
                                [key],
                                function() { resolve(); },
                                function(tx, error) { reject(error); }
                            );
                        });
                    } else {
                        try {
                            localStorage.removeItem(self._dbName + '_' + key);
                            resolve();
                        } catch (error) {
                            reject(error);
                        }
                    }
                });
            });
        },
        
        /**
         * Efface tout le stockage
         */
        clear: function() {
            var self = this;
            
            return this._ensureReady().then(function() {
                return new Promise(function(resolve, reject) {
                    if (self._driver === self.INDEXEDDB) {
                        var transaction = self._db.transaction([self._storeName], 'readwrite');
                        var store = transaction.objectStore(self._storeName);
                        var request = store.clear();
                        
                        request.onsuccess = function() {
                            resolve();
                        };
                        
                        request.onerror = function(event) {
                            reject(event.target.error);
                        };
                    } else if (self._driver === self.WEBSQL) {
                        self._db.transaction(function(tx) {
                            tx.executeSql(
                                'DELETE FROM ' + self._storeName,
                                [],
                                function() { resolve(); },
                                function(tx, error) { reject(error); }
                            );
                        });
                    } else {
                        try {
                            // Supprimer uniquement les entrées de notre namespace
                            var prefixLength = (self._dbName + '_').length;
                            
                            for (var i = 0; i < localStorage.length; i++) {
                                var key = localStorage.key(i);
                                if (key.substr(0, prefixLength) === self._dbName + '_') {
                                    localStorage.removeItem(key);
                                }
                            }
                            
                            resolve();
                        } catch (error) {
                            reject(error);
                        }
                    }
                });
            });
        }
    };
    
    // Initialiser automatiquement
    LifeTravelStorage.init();
    
    // Créer un alias pour compatibilité localforage
    var localforage = {
        setItem: function(key, value) {
            return LifeTravelStorage.setItem(key, value);
        },
        getItem: function(key) {
            return LifeTravelStorage.getItem(key);
        },
        removeItem: function(key) {
            return LifeTravelStorage.removeItem(key);
        },
        clear: function() {
            return LifeTravelStorage.clear();
        }
    };
    
    // Exposer au scope global
    global.LifeTravelStorage = LifeTravelStorage;
    global.localforage = localforage;

})(typeof window !== 'undefined' ? window : this);
