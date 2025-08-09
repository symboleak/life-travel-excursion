/**
 * Life Travel Storage Manager
 * 
 * Système de stockage local sans dépendance externe
 * Implémente une API similaire à LocalForage mais entièrement intégrée
 * 
 * @package Life Travel Excursion
 * @version 2.3.3
 */

(function($) {
    'use strict';
    
    // Détecter si les API de stockage sont disponibles
    const hasIndexedDB = 'indexedDB' in window;
    const hasWebSQL = 'openDatabase' in window;
    const hasLocalStorage = (function() {
        try {
            localStorage.setItem('lt_test', 'test');
            localStorage.removeItem('lt_test');
            return true;
        } catch (e) {
            return false;
        }
    })();
    
    // Configuration
    const DB_NAME = 'life_travel_storage';
    const STORE_NAME = 'life_travel_data';
    const DB_VERSION = 1;
    const DEBUG = lifeTravel.debug_mode === 'true';
    
    // Type de stockage à utiliser (auto, indexeddb, websql, localstorage, ajax)
    let preferredStorage = lifeTravel.storage_type || 'auto';
    
    // Journalisation si le débogage est activé
    function log(...args) {
        if (DEBUG) {
            console.log('[Life Travel Storage]', ...args);
        }
    }
    
    /**
     * Classe principale de gestion du stockage
     */
    class LifeTravelStorage {
        constructor() {
            this.driver = null;
            this.ready = false;
            this.readyPromise = this.init();
        }
        
        /**
         * Initialise le gestionnaire de stockage avec le meilleur driver disponible
         */
        async init() {
            log('Initialisation du gestionnaire de stockage');
            
            if (preferredStorage === 'auto') {
                // Essayer les drivers dans l'ordre de préférence
                if (hasIndexedDB) {
                    try {
                        await this.initIndexedDB();
                        log('Utilisation de IndexedDB');
                        return;
                    } catch (e) {
                        log('Échec de l\'initialisation de IndexedDB:', e);
                    }
                }
                
                if (hasWebSQL) {
                    try {
                        await this.initWebSQL();
                        log('Utilisation de WebSQL');
                        return;
                    } catch (e) {
                        log('Échec de l\'initialisation de WebSQL:', e);
                    }
                }
                
                if (hasLocalStorage) {
                    try {
                        this.initLocalStorage();
                        log('Utilisation de localStorage');
                        return;
                    } catch (e) {
                        log('Échec de l\'initialisation de localStorage:', e);
                    }
                }
                
                // Fallback final: AJAX
                this.initAjax();
                log('Utilisation du fallback AJAX');
            } else {
                // Utiliser le stockage spécifié
                switch (preferredStorage) {
                    case 'indexeddb':
                        if (hasIndexedDB) {
                            await this.initIndexedDB();
                        } else {
                            log('IndexedDB demandé mais non disponible');
                            await this.init();
                        }
                        break;
                        
                    case 'websql':
                        if (hasWebSQL) {
                            await this.initWebSQL();
                        } else {
                            log('WebSQL demandé mais non disponible');
                            await this.init();
                        }
                        break;
                        
                    case 'localstorage':
                        if (hasLocalStorage) {
                            this.initLocalStorage();
                        } else {
                            log('localStorage demandé mais non disponible');
                            await this.init();
                        }
                        break;
                        
                    case 'ajax':
                        this.initAjax();
                        break;
                        
                    default:
                        log('Type de stockage inconnu, utilisation auto');
                        preferredStorage = 'auto';
                        await this.init();
                        break;
                }
            }
        }
        
        /**
         * Initialise IndexedDB
         */
        initIndexedDB() {
            return new Promise((resolve, reject) => {
                if (!hasIndexedDB) {
                    return reject(new Error('IndexedDB non supporté'));
                }
                
                const request = indexedDB.open(DB_NAME, DB_VERSION);
                
                request.onerror = (event) => {
                    log('Erreur d\'ouverture IndexedDB:', event.target.error);
                    reject(event.target.error);
                };
                
                request.onupgradeneeded = (event) => {
                    const db = event.target.result;
                    
                    // Créer l'object store s'il n'existe pas
                    if (!db.objectStoreNames.contains(STORE_NAME)) {
                        db.createObjectStore(STORE_NAME, { keyPath: 'key' });
                        log('Store IndexedDB créé');
                    }
                };
                
                request.onsuccess = (event) => {
                    this.db = event.target.result;
                    this.driver = 'indexeddb';
                    this.ready = true;
                    log('IndexedDB initialisé avec succès');
                    resolve();
                };
            });
        }
        
        /**
         * Initialise WebSQL
         */
        initWebSQL() {
            return new Promise((resolve, reject) => {
                if (!hasWebSQL) {
                    return reject(new Error('WebSQL non supporté'));
                }
                
                try {
                    this.db = openDatabase(
                        DB_NAME,
                        '1.0',
                        'Life Travel Storage',
                        2 * 1024 * 1024
                    );
                    
                    this.db.transaction((tx) => {
                        tx.executeSql(
                            'CREATE TABLE IF NOT EXISTS ' + STORE_NAME + ' (key TEXT PRIMARY KEY, value TEXT)',
                            [],
                            () => {
                                this.driver = 'websql';
                                this.ready = true;
                                log('WebSQL initialisé avec succès');
                                resolve();
                            },
                            (tx, error) => {
                                log('Erreur de création de table WebSQL:', error);
                                reject(error);
                            }
                        );
                    });
                } catch (e) {
                    log('Erreur d\'initialisation WebSQL:', e);
                    reject(e);
                }
            });
        }
        
        /**
         * Initialise localStorage
         */
        initLocalStorage() {
            if (!hasLocalStorage) {
                throw new Error('localStorage non supporté');
            }
            
            this.driver = 'localstorage';
            this.ready = true;
            return Promise.resolve();
        }
        
        /**
         * Initialise le fallback AJAX
         */
        initAjax() {
            this.driver = 'ajax';
            this.ready = true;
            return Promise.resolve();
        }
        
        /**
         * S'assure que le gestionnaire est prêt avant d'exécuter une opération
         */
        async ensureReady() {
            if (!this.ready) {
                await this.readyPromise;
            }
        }
        
        /**
         * Stocke une valeur
         * 
         * @param {string} key La clé
         * @param {*} value La valeur à stocker
         * @returns {Promise}
         */
        async setItem(key, value) {
            await this.ensureReady();
            
            // Sérialiser l'objet si nécessaire
            const serializedValue = JSON.stringify(value);
            
            switch (this.driver) {
                case 'indexeddb':
                    return new Promise((resolve, reject) => {
                        const tx = this.db.transaction([STORE_NAME], 'readwrite');
                        const store = tx.objectStore(STORE_NAME);
                        
                        const request = store.put({ key: key, value: serializedValue });
                        
                        request.onsuccess = () => resolve();
                        request.onerror = (e) => reject(e.target.error);
                    });
                    
                case 'websql':
                    return new Promise((resolve, reject) => {
                        this.db.transaction((tx) => {
                            tx.executeSql(
                                'INSERT OR REPLACE INTO ' + STORE_NAME + ' (key, value) VALUES (?, ?)',
                                [key, serializedValue],
                                () => resolve(),
                                (tx, error) => reject(error)
                            );
                        });
                    });
                    
                case 'localstorage':
                    try {
                        localStorage.setItem(DB_NAME + '_' + key, serializedValue);
                        return Promise.resolve();
                    } catch (e) {
                        return Promise.reject(e);
                    }
                    
                case 'ajax':
                    return new Promise((resolve, reject) => {
                        $.ajax({
                            url: lifeTravel.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'life_travel_store_data',
                                nonce: lifeTravel.nonce,
                                key: key,
                                data: serializedValue
                            },
                            success: function(response) {
                                if (response.success) {
                                    resolve();
                                } else {
                                    reject(new Error(response.data.message));
                                }
                            },
                            error: function(xhr, status, error) {
                                reject(error);
                            }
                        });
                    });
            }
        }
        
        /**
         * Récupère une valeur
         * 
         * @param {string} key La clé
         * @returns {Promise<*>} La valeur récupérée
         */
        async getItem(key) {
            await this.ensureReady();
            
            switch (this.driver) {
                case 'indexeddb':
                    return new Promise((resolve, reject) => {
                        const tx = this.db.transaction([STORE_NAME], 'readonly');
                        const store = tx.objectStore(STORE_NAME);
                        
                        const request = store.get(key);
                        
                        request.onsuccess = (event) => {
                            const result = event.target.result;
                            if (result) {
                                try {
                                    resolve(JSON.parse(result.value));
                                } catch (e) {
                                    resolve(result.value);
                                }
                            } else {
                                resolve(null);
                            }
                        };
                        
                        request.onerror = (e) => reject(e.target.error);
                    });
                    
                case 'websql':
                    return new Promise((resolve, reject) => {
                        this.db.transaction((tx) => {
                            tx.executeSql(
                                'SELECT value FROM ' + STORE_NAME + ' WHERE key = ?',
                                [key],
                                (tx, results) => {
                                    if (results.rows.length > 0) {
                                        const value = results.rows.item(0).value;
                                        try {
                                            resolve(JSON.parse(value));
                                        } catch (e) {
                                            resolve(value);
                                        }
                                    } else {
                                        resolve(null);
                                    }
                                },
                                (tx, error) => reject(error)
                            );
                        });
                    });
                    
                case 'localstorage':
                    try {
                        const value = localStorage.getItem(DB_NAME + '_' + key);
                        if (value !== null) {
                            try {
                                return Promise.resolve(JSON.parse(value));
                            } catch (e) {
                                return Promise.resolve(value);
                            }
                        } else {
                            return Promise.resolve(null);
                        }
                    } catch (e) {
                        return Promise.reject(e);
                    }
                    
                case 'ajax':
                    return new Promise((resolve, reject) => {
                        $.ajax({
                            url: lifeTravel.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'life_travel_retrieve_data',
                                nonce: lifeTravel.nonce,
                                key: key
                            },
                            success: function(response) {
                                if (response.success) {
                                    try {
                                        resolve(JSON.parse(response.data.data));
                                    } catch (e) {
                                        resolve(response.data.data);
                                    }
                                } else {
                                    resolve(null);
                                }
                            },
                            error: function(xhr, status, error) {
                                reject(error);
                            }
                        });
                    });
            }
        }
        
        /**
         * Supprime une valeur
         * 
         * @param {string} key La clé
         * @returns {Promise}
         */
        async removeItem(key) {
            await this.ensureReady();
            
            switch (this.driver) {
                case 'indexeddb':
                    return new Promise((resolve, reject) => {
                        const tx = this.db.transaction([STORE_NAME], 'readwrite');
                        const store = tx.objectStore(STORE_NAME);
                        
                        const request = store.delete(key);
                        
                        request.onsuccess = () => resolve();
                        request.onerror = (e) => reject(e.target.error);
                    });
                    
                case 'websql':
                    return new Promise((resolve, reject) => {
                        this.db.transaction((tx) => {
                            tx.executeSql(
                                'DELETE FROM ' + STORE_NAME + ' WHERE key = ?',
                                [key],
                                () => resolve(),
                                (tx, error) => reject(error)
                            );
                        });
                    });
                    
                case 'localstorage':
                    try {
                        localStorage.removeItem(DB_NAME + '_' + key);
                        return Promise.resolve();
                    } catch (e) {
                        return Promise.reject(e);
                    }
                    
                case 'ajax':
                    // Pour l'AJAX, nous envoyons simplement une valeur vide pour "supprimer"
                    return this.setItem(key, '');
            }
        }
        
        /**
         * Efface toutes les données
         * 
         * @returns {Promise}
         */
        async clear() {
            await this.ensureReady();
            
            switch (this.driver) {
                case 'indexeddb':
                    return new Promise((resolve, reject) => {
                        const tx = this.db.transaction([STORE_NAME], 'readwrite');
                        const store = tx.objectStore(STORE_NAME);
                        
                        const request = store.clear();
                        
                        request.onsuccess = () => resolve();
                        request.onerror = (e) => reject(e.target.error);
                    });
                    
                case 'websql':
                    return new Promise((resolve, reject) => {
                        this.db.transaction((tx) => {
                            tx.executeSql(
                                'DELETE FROM ' + STORE_NAME,
                                [],
                                () => resolve(),
                                (tx, error) => reject(error)
                            );
                        });
                    });
                    
                case 'localstorage':
                    try {
                        const prefix = DB_NAME + '_';
                        for (let i = 0; i < localStorage.length; i++) {
                            const key = localStorage.key(i);
                            if (key.startsWith(prefix)) {
                                localStorage.removeItem(key);
                            }
                        }
                        return Promise.resolve();
                    } catch (e) {
                        return Promise.reject(e);
                    }
                    
                case 'ajax':
                    // Pour l'AJAX, nous ne pouvons pas vraiment "tout effacer"
                    // Mais nous pouvons supprimer les clés connues
                    // (À développer en fonction des besoins)
                    return Promise.resolve();
            }
        }
        
        /**
         * Récupère toutes les clés stockées
         * 
         * @returns {Promise<Array>} Les clés
         */
        async keys() {
            await this.ensureReady();
            
            switch (this.driver) {
                case 'indexeddb':
                    return new Promise((resolve, reject) => {
                        const tx = this.db.transaction([STORE_NAME], 'readonly');
                        const store = tx.objectStore(STORE_NAME);
                        
                        const request = store.getAllKeys();
                        
                        request.onsuccess = (event) => {
                            resolve(event.target.result);
                        };
                        
                        request.onerror = (e) => reject(e.target.error);
                    });
                    
                case 'websql':
                    return new Promise((resolve, reject) => {
                        this.db.transaction((tx) => {
                            tx.executeSql(
                                'SELECT key FROM ' + STORE_NAME,
                                [],
                                (tx, results) => {
                                    const keys = [];
                                    for (let i = 0; i < results.rows.length; i++) {
                                        keys.push(results.rows.item(i).key);
                                    }
                                    resolve(keys);
                                },
                                (tx, error) => reject(error)
                            );
                        });
                    });
                    
                case 'localstorage':
                    try {
                        const keys = [];
                        const prefix = DB_NAME + '_';
                        for (let i = 0; i < localStorage.length; i++) {
                            const key = localStorage.key(i);
                            if (key.startsWith(prefix)) {
                                keys.push(key.substring(prefix.length));
                            }
                        }
                        return Promise.resolve(keys);
                    } catch (e) {
                        return Promise.reject(e);
                    }
                    
                case 'ajax':
                    // Pour l'AJAX, nous ne pouvons pas récupérer toutes les clés
                    return Promise.resolve([]);
            }
        }
    }
    
    // Créer une instance globale
    window.lifeTravel = window.lifeTravel || {};
    window.lifeTravel.storage = new LifeTravelStorage();
    
    // API compatible avec LocalForage pour faciliter la migration
    window.lifeTravelStorage = {
        setItem: (key, value) => window.lifeTravel.storage.setItem(key, value),
        getItem: (key) => window.lifeTravel.storage.getItem(key),
        removeItem: (key) => window.lifeTravel.storage.removeItem(key),
        clear: () => window.lifeTravel.storage.clear(),
        keys: () => window.lifeTravel.storage.keys(),
        ready: () => window.lifeTravel.storage.ensureReady()
    };
    
    // Trigger un événement lorsque le stockage est prêt
    window.lifeTravel.storage.readyPromise.then(() => {
        $(document).trigger('life_travel_storage_ready');
    });
    
})(jQuery);
