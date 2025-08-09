/**
 * Life Travel - Script pour la page hors-ligne
 * Optimisé pour le contexte camerounais avec connectivité variable et réseau instable
 * - Détection fine des connexions lentes ou intermittentes
 * - Stratégies d'économie de données (MTN, Orange, Camtel)
 * - Synchronisation intelligente avec backoff exponentiel
 * - Compatibilité mobile complète (Opera Mini, UC Browser, etc.)
 */

// Configuration adaptée au contexte camerounais
const CONFIG = window.LIFE_TRAVEL_CONFIG || {
    connectionCheckTimeout: 5000,     // Timeout adapté aux réseaux lents
    syncAttemptInterval: 30000,       // Vérification périodique
    maxSyncRetries: 5,                // Nombre max de tentatives
    connectionEndpoints: [            // Endpoints locaux
        'https://www.google.com',
        'https://www.camtel.cm',
        'https://www.orange.cm',
        'https://www.mtn.cm'
    ],
    fallbackToLocalStorage: true      // Fallback sans IndexedDB
};

// Variables globales
const CACHE_NAME = window.LIFE_TRAVEL_CONFIG && window.LIFE_TRAVEL_CONFIG.cacheName
    ? window.LIFE_TRAVEL_CONFIG.cacheName
    : 'life-travel-cache-v2';
let networkTimeout;
let connectionCheckInterval;
let syncBackoffInterval = 1000; // Démarrage à 1s, augmente exponentiellement
let pendingBookings = [];       // Réservations en attente de synchronisation

// Déclaration factice des fonctions pour éviter les problèmes de référence
// Ces fonctions seront remplacées par leurs vraies implémentations plus loin
let syncPendingData = function(force = false) {
    return Promise.resolve();
};

/**
 * Vérifie que toutes les ressources critiques sont disponibles
 * Spécialement important pour les connexions instables camerounaises
 * @param {number} attempt Numéro de tentative actuelle
 * @param {number} delay Délai avant nouvelle tentative (croissance exponentielle)
 * @returns {Promise} Promise résolue quand les ressources sont disponibles
 */
function ensureResourcesLoaded(attempt = 0, delay = 500) {
    return new Promise((resolve, reject) => {
        // Vérifier si la configuration globale est chargée
        const configLoaded = window.LIFE_TRAVEL_CONFIG !== undefined;
        // Vérifier si le gestionnaire d'icônes est disponible
        const iconsLoaded = window.LifeTravelIcons !== undefined;
        
        if (configLoaded && iconsLoaded) {
            resolve({
                config: window.LIFE_TRAVEL_CONFIG,
                icons: window.LifeTravelIcons
            });
            return;
        }
        
        // Arrêter après trop de tentatives pour éviter les boucles infinies
        if (attempt >= 3) {
            console.warn(`Impossible de charger toutes les ressources après ${attempt} tentatives.`);
            // Résoudre quand même mais avec un statut d'erreur
            resolve({
                incomplete: true,
                config: window.LIFE_TRAVEL_CONFIG,
                icons: window.LifeTravelIcons
            });
            return;
        }
        
        // Attendre et réessayer avec un délai exponentiel
        console.log(`Attente des ressources... Tentative ${attempt + 1}/3`);
        setTimeout(() => {
            // Récursion avec backoff exponentiel
            ensureResourcesLoaded(attempt + 1, delay * 1.5)
                .then(resolve)
                .catch(reject);
        }, delay);
    });
}

// Helper pour les appels fetch plus robustes, avec timeout
function safeFetch(url, options = {}) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 
        options.timeout || CONFIG.connectionCheckTimeout || 5000);
    
    return fetch(url, {
        ...options,
        signal: controller.signal
    })
    .then(response => {
        clearTimeout(timeoutId);
        return response;
    })
    .catch(error => {
        clearTimeout(timeoutId);
        console.error('Erreur réseau:', error);
        return { ok: false, status: 0 };
    });
}

/**
 * Initialise toutes les icônes SVG dans le document
 * Cette fonction garantit que toutes les icônes sont bien chargées et rendues
 */
function initializeIcons() {
    if (window.LifeTravelIcons) {
        // Précharger le sprite pour les futures utilisations
        window.LifeTravelIcons.preload().then(function() {
            // Initialiser toutes les icônes présentes dans le document
            const iconElements = document.querySelectorAll('[data-lt-icon]');
            iconElements.forEach(function(el) {
                const iconName = el.getAttribute('data-lt-icon');
                const color = el.getAttribute('data-lt-color');
                const size = el.getAttribute('data-lt-size');
                window.LifeTravelIcons.insert(el, iconName, {
                    color: color,
                    size: size,
                    class: el.getAttribute('data-lt-class')
                });
            });
        }).catch(function(error) {
            console.error('Erreur lors du chargement des icônes:', error);
        });
    }
}

// Initialisation au chargement du document
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser l'état de la connexion
    checkNetworkStatus();
    
    // Initialiser les icônes SVG
    initializeIcons();
    
    // Configurer l'écouteur pour le bouton de reconnexion
    const retryButton = document.getElementById('retry-connection');
    if (retryButton) {
        retryButton.addEventListener('click', retryConnection);
    }
    
    // Configurer le bouton de synchronisation forcée
    const forceSyncButton = document.getElementById('force-sync');
    if (forceSyncButton) {
        forceSyncButton.addEventListener('click', function() {
            forceSyncButton.disabled = true;
            forceSyncButton.textContent = 'Synchronisation en cours...';
            syncPendingData(true).finally(function() {
                forceSyncButton.disabled = false;
                forceSyncButton.textContent = 'Forcer la synchronisation';
            });
        });
    }
    
    // Configurer le bouton de rafraîchissement du cache
    const updateCacheButton = document.getElementById('update-cached-pages');
    if (updateCacheButton) {
        updateCacheButton.addEventListener('click', function() {
            updateCacheButton.disabled = true;
            updateCacheButton.textContent = 'Mise à jour...';
            loadCachedPages(true).finally(function() {
                updateCacheButton.disabled = false;
                updateCacheButton.innerHTML = '<span data-lt-icon="sync"></span> Actualiser le cache';
                
                // Réinitialiser les icônes SVG
                if (window.LifeTravelIcons) {
                    const iconElements = document.querySelectorAll('[data-lt-icon]');
                    iconElements.forEach(function(el) {
                        const iconName = el.getAttribute('data-lt-icon');
                        const color = el.getAttribute('data-lt-color');
                        window.LifeTravelIcons.insert(el, iconName, { color: color });
                    });
                }
            });
        });
    }
    
    // Configurer les toggles (mode économie de données, images légères)
    const saveDataMode = document.getElementById('save-data-mode');
    if (saveDataMode) {
        // Initialiser l'état depuis localStorage
        saveDataMode.checked = localStorage.getItem('lt_data_saving_mode') === 'true';
        
        saveDataMode.addEventListener('change', function() {
            localStorage.setItem('lt_data_saving_mode', saveDataMode.checked);
            // Notifier le service worker
            if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({
                    action: 'SET_DATA_SAVING_MODE',
                    value: saveDataMode.checked
                });
            }
        });
    }
    
    const toggleImages = document.getElementById('toggle-images');
    if (toggleImages) {
        // Initialiser l'état depuis localStorage
        toggleImages.checked = localStorage.getItem('lt_lightweight_images') !== 'false';
        
        toggleImages.addEventListener('change', function() {
            localStorage.setItem('lt_lightweight_images', toggleImages.checked);
            // Notifier le service worker
            if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({
                    action: 'SET_LIGHTWEIGHT_IMAGES',
                    value: toggleImages.checked
                });
            }
        });
    }
    
    const toggleDataSaver = document.getElementById('toggle-data-saver');
    if (toggleDataSaver) {
        // Initialiser l'état depuis localStorage
        toggleDataSaver.checked = localStorage.getItem('lt_data_saving_mode') === 'true';
        
        toggleDataSaver.addEventListener('change', function() {
            localStorage.setItem('lt_data_saving_mode', toggleDataSaver.checked);
            localStorage.setItem('lt_lightweight_images', toggleDataSaver.checked);
            
            // Synchroniser les autres toggles
            if (saveDataMode) saveDataMode.checked = toggleDataSaver.checked;
            if (toggleImages) toggleImages.checked = toggleDataSaver.checked;
            
            // Notifier le service worker
            if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({
                    action: 'SET_DATA_SAVING_MODE',
                    value: toggleDataSaver.checked
                });
            }
        });
    }
    
    // D'abord, vérifier que toutes les ressources nécessaires sont chargées
    ensureResourcesLoaded()
        .then(resources => {
            console.log('Ressources chargées:', resources.incomplete ? 'partiellement' : 'complètement');
            
            // Initialiser les icônes SVG avec gestion robuste des erreurs
            if (window.LifeTravelIcons) {
                window.LifeTravelIcons.preload()
                    .then(() => {
                        const iconElements = document.querySelectorAll('[data-lt-icon]');
                        console.log(`Initialisation de ${iconElements.length} icônes SVG`);
                        
                        iconElements.forEach(function(el) {
                            try {
                                const iconName = el.getAttribute('data-lt-icon');
                                const color = el.getAttribute('data-lt-color');
                                window.LifeTravelIcons.insert(el, iconName, { color: color });
                            } catch (e) {
                                console.warn('Erreur lors de l\'initialisation d\'une icône:', e);
                            }
                        });
                    })
                    .catch(error => {
                        console.error('Erreur lors du chargement des icônes, continuant malgré tout:', error);
                    });
            }
            
            // Écouter les changements de connectivité
            window.addEventListener('online', handleOnlineStatus);
            window.addEventListener('offline', handleOfflineStatus);
            
            // Vérifier périodiquement la connexion (toutes les 30 secondes ou selon config)
            connectionCheckInterval = setInterval(checkNetworkStatus, CONFIG.syncAttemptInterval || 30000);
            
            // Charger les réservations en attente avec gestion robuste des erreurs
            try {
                loadPendingBookings();
            } catch (e) {
                console.error('Erreur lors du chargement des réservations:', e);
            }
            
            // Charger les pages en cache
            try {
                loadCachedPages();
            } catch (e) {
                console.error('Erreur lors du chargement des pages en cache:', e);
            }
            
            // Vérifier la disponibilité du service worker
            checkServiceWorkerSupport();
            
            // Exécuter une vérification immédiate de la connexion
            checkNetworkStatus();
        })
        .catch(error => {
            console.error('Erreur critique lors de l\'initialisation des ressources:', error);
            // Essayer de continuer malgré tout avec une expérience dégradée
            
            // Écouter les changements de connectivité
            window.addEventListener('online', handleOnlineStatus);
            window.addEventListener('offline', handleOfflineStatus);
            
            // Charger les réservations en attente avec plus de robustesse
            try {
                loadPendingBookings();
            } catch (e) {
                console.error('Erreur lors du chargement des réservations:', e);
            }
        });
});

/**
 * Vérifie l'état actuel de la connexion avec une détection intelligente
 * adaptée aux réseaux camerounais (MTN, Orange, Camtel)
 */
function checkNetworkStatus() {
    // Détection basique (compatible avec tous les navigateurs)
    if (!navigator.onLine) {
        handleOfflineStatus();
        return;
    }
    
    // Détection avancée pour les réseaux camerounais (tests multiples)
    testConnection()
        .then(function(connectionInfo) {
            if (connectionInfo.online) {
                handleOnlineStatus(connectionInfo);
            } else {
                handleOfflineStatus();
            }
        })
        .catch(function() {
            handleOfflineStatus();
        });
}

/**
 * Test de connectivité avancé adapté aux conditions camerounaises
 * Teste plusieurs endpoints avec un timeout adapté aux réseaux lents
 */
function testConnection() {
    // Détecter le type et la qualité de connexion
    const connectionInfo = {
        online: false,
        type: 'unknown',
        quality: 'unknown',
        effectiveBandwidth: 0,
        latency: 0
    };
    
    // Utiliser l'API Network Information si disponible (Chrome/Android)
    if ('connection' in navigator && navigator.connection) {
        connectionInfo.type = navigator.connection.type || 'unknown';
        connectionInfo.effectiveBandwidth = navigator.connection.downlink || 0;
        connectionInfo.saveData = navigator.connection.saveData || false;
        
        // Évaluer la qualité (adapté aux conditions camerounaises)
        if (connectionInfo.effectiveBandwidth > 2) {
            connectionInfo.quality = 'good';
        } else if (connectionInfo.effectiveBandwidth > 0.5) {
            connectionInfo.quality = 'medium';
        } else if (connectionInfo.effectiveBandwidth > 0) {
            connectionInfo.quality = 'poor';
        }
        
        // Mise à jour de l'interface
        updateConnectionQualityUI(connectionInfo);
    }
    
    // Vérifier les connexions aux endpoints les plus pertinents pour le Cameroun
    // avec un timeout adapté aux réseaux lents
    return new Promise(function(resolve) {
        // Choisir aléatoirement 2 endpoints pour réduire la charge
        const endpoints = CONFIG.connectionEndpoints.sort(() => 0.5 - Math.random()).slice(0, 2);
        let endpointsTested = 0;
        let successfulTests = 0;
        
        // Fonction pour tester un seul endpoint
        function testEndpoint(url) {
            const startTime = Date.now();
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), CONFIG.connectionCheckTimeout);
            
            fetch(url, { 
                method: 'HEAD',
                mode: 'no-cors',
                cache: 'no-store',
                signal: controller.signal
            })
            .then(() => {
                clearTimeout(timeoutId);
                const latency = Date.now() - startTime;
                connectionInfo.latency = latency;
                successfulTests++;
                
                // Mise à jour de la qualité basée sur la latence
                if (latency < 300) {
                    connectionInfo.quality = 'good';
                } else if (latency < 1000) {
                    connectionInfo.quality = 'medium';
                } else {
                    connectionInfo.quality = 'poor';
                }
            })
            .catch(() => {})
            .finally(() => {
                endpointsTested++;
                
                // Une fois tous les tests terminés
                if (endpointsTested === endpoints.length) {
                    connectionInfo.online = successfulTests > 0;
                    updateConnectionQualityUI(connectionInfo);
                    resolve(connectionInfo);
                }
            });
        }
        
        // Tester tous les endpoints sélectionnés
        if (endpoints.length > 0) {
            endpoints.forEach(testEndpoint);
        } else {
            // Fallback si pas d'endpoints configurés
            connectionInfo.online = navigator.onLine;
            resolve(connectionInfo);
        }
    });
}

/**
 * Met à jour l'interface utilisateur avec les informations de qualité de connexion
 */
function updateConnectionQualityUI(connectionInfo) {
    // Mettre à jour l'attribut data-connection sur l'HTML
    document.documentElement.setAttribute('data-connection', connectionInfo.online ? connectionInfo.quality : 'offline');
    
    // Mettre à jour l'indicateur de qualité
    const connectionQuality = document.getElementById('connection-quality');
    const connectionLevel = document.getElementById('connection-level');
    
    if (connectionQuality && connectionInfo.online) {
        connectionQuality.classList.remove('hidden');
        
        if (connectionLevel) {
            let qualityMessage = 'Connexion ';
            
            switch(connectionInfo.quality) {
                case 'good':
                    qualityMessage += 'bonne';
                    connectionQuality.className = 'connection-quality good';
                    break;
                case 'medium':
                    qualityMessage += 'moyenne';
                    connectionQuality.className = 'connection-quality medium';
                    break;
                case 'poor':
                    qualityMessage += 'faible';
                    connectionQuality.className = 'connection-quality poor';
                    break;
                default:
                    qualityMessage += 'inconnue';
                    connectionQuality.className = 'connection-quality unknown';
            }
            
            qualityMessage += connectionInfo.type !== 'unknown' ? ` (${connectionInfo.type})` : '';
            connectionLevel.textContent = qualityMessage;
        }
    } else if (connectionQuality) {
        connectionQuality.classList.add('hidden');
    }
    
    // Suggérer le mode économie de données pour les connexions lentes
    if (connectionInfo.quality === 'poor' || connectionInfo.saveData) {
        const saveDataMode = document.getElementById('save-data-mode');
        if (saveDataMode && !saveDataMode.checked) {
            saveDataMode.checked = true;
            // Déclencher l'événement change pour activer le mode
            saveDataMode.dispatchEvent(new Event('change'));
        }
    }
}

/**
 * Gère l'état connecté avec prise en compte de la qualité de connexion
 * @param {Object} connectionInfo Informations sur la connexion actuelle
 */
function handleOnlineStatus(connectionInfo = { quality: 'unknown' }) {
    document.body.classList.remove('lt-offline');
    document.body.classList.add('lt-online');
    document.body.setAttribute('data-connection-quality', connectionInfo.quality || 'unknown');
    
    const statusElement = document.querySelector('.offline-status');
    if (statusElement) {
        statusElement.classList.remove('offline');
        statusElement.classList.add('online');
        
        // Adapter le message selon la qualité de connexion
        let messageHtml = '<div class="status-content"><strong>Connexion rétablie !</strong>';
        
        if (connectionInfo.quality === 'poor') {
            messageHtml += '<p>Connexion faible détectée. Mode économie de données activé.</p>';
            // Activer automatiquement le mode économie de données
            activateDataSavingMode();
            // Délai plus long pour la redirection
            var redirectDelay = 5000;
        } else if (connectionInfo.quality === 'medium') {
            messageHtml += '<p>Connexion moyenne. Redirection dans 3 secondes...</p>';
            var redirectDelay = 3000;
        } else {
            messageHtml += '<p>Redirection vers le site complet dans 2 secondes...</p>';
            var redirectDelay = 2000;
        }
        
        messageHtml += '</div>';
        
        // Ajouter le bouton pour rester sur la page offline
        messageHtml += '<button id="stay-offline" class="offline-button secondary">'+
            'Rester en mode offline</button>';
        
        statusElement.innerHTML = messageHtml;
        
        // Configurer le bouton pour rester offline
        const stayOfflineButton = document.getElementById('stay-offline');
        if (stayOfflineButton) {
            stayOfflineButton.addEventListener('click', function(e) {
                e.preventDefault();
                // Annuler la redirection
                if (networkTimeout) {
                    clearTimeout(networkTimeout);
                }
                // Mettre à jour l'interface pour l'utilisation offline volontaire
                updateForVoluntaryOfflineMode();
            });
        }
    }
    
    // Nettoyer les timeouts existants
    if (networkTimeout) {
        clearTimeout(networkTimeout);
    }
    
    // Lancer la synchronisation des données en attente
    syncPendingData();
    
    // Mettre à jour la section des réservations en attente
    updatePendingBookingsUI();
    
    // Rediriger après un délai, sauf si indiqué autrement
    if (localStorage.getItem('stay_offline') !== 'true') {
        networkTimeout = setTimeout(function() {
            window.location.reload();
        }, redirectDelay || 3000);
    }
}

/**
 * Met à jour l'interface pour le mode offline volontaire
 */
function updateForVoluntaryOfflineMode() {
    // Stocker la préférence utilisateur
    localStorage.setItem('stay_offline', 'true');
    
    const statusElement = document.querySelector('.offline-status');
    if (statusElement) {
        statusElement.innerHTML = '<div class="status-icon" data-lt-icon="wifi" data-lt-color="#2980B9"></div>' +
            '<div class="status-content">' +
            '<strong>Mode offline conservé</strong>' +
            '<p>Vous êtes connecté, mais utilisez le mode offline pour économiser les données</p>' +
            '</div>' +
            '<button id="go-online" class="offline-button primary">' +
            'Passer en mode online</button>';
        
        // Initialiser les icônes
        if (window.LifeTravelIcons) {
            window.LifeTravelIcons.preload().then(function() {
                const iconElements = document.querySelectorAll('[data-lt-icon]');
                iconElements.forEach(function(el) {
                    const iconName = el.getAttribute('data-lt-icon');
                    const color = el.getAttribute('data-lt-color');
                    window.LifeTravelIcons.insert(el, iconName, { color: color });
                });
            });
        }
        
        // Configurer le bouton pour passer en mode online
        const goOnlineButton = document.getElementById('go-online');
        if (goOnlineButton) {
            goOnlineButton.addEventListener('click', function() {
                localStorage.removeItem('stay_offline');
                window.location.reload();
            });
        }
    }
}

/**
 * Active le mode économie de données
 */
function activateDataSavingMode() {
    const saveDataMode = document.getElementById('save-data-mode');
    const toggleImagesBox = document.getElementById('toggle-images');
    const toggleDataSaver = document.getElementById('toggle-data-saver');
    
    // Activer les cases à cocher correspondantes
    if (saveDataMode && !saveDataMode.checked) {
        saveDataMode.checked = true;
    }
    
    if (toggleDataSaver && !toggleDataSaver.checked) {
        toggleDataSaver.checked = true;
    }
    
    if (toggleImagesBox && !toggleImagesBox.checked) {
        toggleImagesBox.checked = true;
    }
    
    // Stocker dans localStorage pour le service worker
    localStorage.setItem('lt_data_saving_mode', 'true');
    localStorage.setItem('lt_lightweight_images', 'true');
    
    // Notifier le service worker du changement
    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({
            action: 'SET_DATA_SAVING_MODE',
            value: true
        });
    }
}

/**
 * Synchronise les données en attente avec le serveur
 * Optimisé pour les connexions instables au Cameroun avec backoff exponentiel
 * et réessai intelligent des opérations échouées
 * 
 * @param {boolean} forceSync - Force la synchronisation même si les conditions réseau sont faibles
 * @returns {Promise<object>} Résultat de la synchronisation
 */
async function syncPendingData(forceSync = false) {
    // Vérifier l'état de la connexion avant de tenter la synchronisation
    const isOnline = navigator.onLine;
    const syncStatusElement = document.getElementById('sync-status');
    const syncBadge = document.getElementById('sync-badge');
    
    if (syncStatusElement) {
        syncStatusElement.textContent = 'Vérification de la connexion...';
        syncStatusElement.classList.add('checking');
    }
    
    if (!isOnline && !forceSync) {
        console.warn('Synchronisation impossible: Appareil hors ligne');
        if (syncStatusElement) {
            syncStatusElement.textContent = 'Impossible de synchroniser (hors ligne)';
            syncStatusElement.classList.remove('checking');
            syncStatusElement.classList.add('offline');
        }
        return { success: false, status: 'offline', message: 'Appareil hors ligne' };
    }
    
    // Récupérer la qualité de la connexion
    const connectionQuality = getConnectionQuality();
    
    // Si la connexion est trop faible et qu'on ne force pas la synchronisation, annuler
    if (connectionQuality === 'poor' && !forceSync) {
        console.warn('Synchronisation reportée: Connexion de mauvaise qualité');
        if (syncStatusElement) {
            syncStatusElement.textContent = 'Connexion trop faible, synchronisation reportée';
            syncStatusElement.classList.remove('checking');
            syncStatusElement.classList.add('weak');
        }
        return { 
            success: false, 
            status: 'poor-connection', 
            message: 'Connexion de mauvaise qualité, nouvelle tentative plus tard' 
        };
    }
    
    try {
        if (syncStatusElement) {
            syncStatusElement.textContent = 'Synchronisation en cours...';
            syncStatusElement.classList.remove('offline', 'weak');
            syncStatusElement.classList.add('syncing');
        }
        
        if (syncBadge) {
            syncBadge.style.display = 'inline-block';
        }
        
        // 1. Récupérer les données en attente depuis les différents stockages
        const pendingItems = await getPendingItems();
        
        if (pendingItems.length === 0) {
            console.log('Aucune donnée en attente à synchroniser');
            if (syncStatusElement) {
                syncStatusElement.textContent = 'Synchronisé (aucune donnée en attente)';
                syncStatusElement.classList.remove('checking', 'syncing');
                syncStatusElement.classList.add('synced');
            }
            if (syncBadge) {
                syncBadge.style.display = 'none';
            }
            return { success: true, status: 'no-pending-data', syncedItems: 0 };
        }
        
        console.log(`Synchronisation de ${pendingItems.length} éléments en attente`);
        
        // Paramètres pour le backoff exponentiel (adapté aux réalités camerounaises)
        const backoffConfig = {
            initialDelay: 1000,    // 1 seconde
            maxDelay: 30000,       // 30 secondes
            factor: 1.5,           // Facteur multiplicatif
            maxAttempts: 5,        // Nombre maximum de tentatives
            jitter: 0.2            // Variation aléatoire (20%)
        };
        
        // 2. Synchroniser chaque élément avec backoff exponentiel
        const results = await Promise.allSettled(pendingItems.map(async (item) => {
            let attempt = 0;
            let lastError = null;
            
            while (attempt < backoffConfig.maxAttempts) {
                try {
                    // Si ce n'est pas la première tentative, attendre avec backoff exponentiel
                    if (attempt > 0) {
                        const delay = Math.min(
                            backoffConfig.initialDelay * Math.pow(backoffConfig.factor, attempt),
                            backoffConfig.maxDelay
                        );
                        
                        // Ajouter un facteur aléatoire (jitter) pour réduire les risques de collision
                        const jitterFactor = 1 - backoffConfig.jitter + (Math.random() * backoffConfig.jitter * 2);
                        const finalDelay = Math.floor(delay * jitterFactor);
                        
                        console.log(`Réessai #${attempt} pour l'item ${item.id} après ${finalDelay}ms`);
                        await new Promise(resolve => setTimeout(resolve, finalDelay));
                    }
                    
                    // Tentative de synchronisation vers le serveur avec un timeout adapté
                    const response = await fetchWithTimeout('/wp-admin/admin-ajax.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'life_travel_sync_offline_data',
                            item_type: item.type,
                            item_data: JSON.stringify(item.data),
                            item_id: item.id,
                            timestamp: item.timestamp,
                            security: lifeTravel.nonce || ''
                        }),
                        timeout: 15000 // 15 secondes de timeout (adapté pour le Cameroun)
                    });
                    
                    // Vérifier si la réponse est OK
                    if (!response.ok) {
                        throw new Error(`Erreur HTTP: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (!result.success) {
                        throw new Error(result.message || 'Erreur de synchronisation');
                    }
                    
                    // Synchronisation réussie, supprimer l'élément de la file d'attente locale
                    await removePendingItem(item.id);
                    
                    return { 
                        id: item.id, 
                        type: item.type, 
                        success: true, 
                        serverResponse: result 
                    };
                } catch (error) {
                    lastError = error;
                    console.warn(`Échec de synchronisation pour l'item ${item.id} (tentative ${attempt + 1}/${backoffConfig.maxAttempts}):`, error);
                    attempt++;
                }
            }
            
            // Si on atteint ce point, toutes les tentatives ont échoué
            console.error(`Synchronisation échouée pour l'item ${item.id} après ${attempt} tentatives`);
            return { 
                id: item.id, 
                type: item.type, 
                success: false, 
                error: lastError?.message || 'Trop de tentatives infructueuses' 
            };
        }));
        
        // 3. Analyser les résultats
        const successful = results.filter(r => r.status === 'fulfilled' && r.value.success).length;
        const failed = results.length - successful;
        
        // Mise à jour de l'interface
        if (syncStatusElement) {
            if (successful === results.length) {
                syncStatusElement.textContent = `Synchronisation réussie (${successful} éléments)`;
                syncStatusElement.classList.remove('checking', 'syncing');
                syncStatusElement.classList.add('synced');
            } else if (successful > 0) {
                syncStatusElement.textContent = `Synchronisation partielle (${successful}/${results.length})`;
                syncStatusElement.classList.remove('checking', 'syncing');
                syncStatusElement.classList.add('partial');
            } else {
                syncStatusElement.textContent = 'Synchronisation échouée';
                syncStatusElement.classList.remove('checking', 'syncing');
                syncStatusElement.classList.add('failed');
            }
        }
        
        // Mettre à jour le badge
        if (syncBadge) {
            const remainingCount = await getPendingItemsCount();
            if (remainingCount > 0) {
                syncBadge.textContent = remainingCount;
                syncBadge.style.display = 'inline-block';
            } else {
                syncBadge.style.display = 'none';
            }
        }
        
        return {
            success: failed === 0,
            status: failed === 0 ? 'complete' : (successful > 0 ? 'partial' : 'failed'),
            syncedItems: successful,
            failedItems: failed,
            details: results.map(r => r.status === 'fulfilled' ? r.value : r.reason)
        };
    } catch (error) {
        console.error('Erreur lors de la synchronisation:', error);
        
        if (syncStatusElement) {
            syncStatusElement.textContent = 'Erreur de synchronisation';
            syncStatusElement.classList.remove('checking', 'syncing');
            syncStatusElement.classList.add('failed');
        }
        
        return { 
            success: false, 
            status: 'error', 
            error: error.message || 'Erreur inconnue lors de la synchronisation' 
        };
    }
}

/**
 * Initialise et ouvre la base de données IndexedDB pour le stockage offline
 * Cette implémentation est optimisée pour le contexte camerounais avec gestion des erreurs robuste
 * 
 * @returns {Promise<IDBDatabase>} - L'instance de base de données
 */
function openDatabase() {
    return new Promise((resolve, reject) => {
        // Vérifier si IndexedDB est supporté (pas le cas pour Opera Mini et certains navigateurs KaiOS)
        if (!('indexedDB' in window)) {
            return reject(new Error('IndexedDB n\'est pas supporté par ce navigateur'));
        }

        // Configuration de la base de données
        const dbName = 'LifeTravelOfflineDB';
        const dbVersion = 1;
        
        // Ouvrir la base de données avec gestion d'erreurs améliorée
        const request = indexedDB.open(dbName, dbVersion);
        
        // Gérer les erreurs (fréquentes sur les appareils avec stockage limité)
        request.onerror = (event) => {
            console.error('Erreur lors de l\'ouverture de la base de données IndexedDB:', event.target.error);
            
            // Vérifier s'il s'agit d'une erreur de quota dépassé (fréquent au Cameroun sur les appareils à faible mémoire)
            if (event.target.error.name === 'QuotaExceededError') {
                // Notifier l'utilisateur et basculer vers localStorage
                showStorageWarning('L\'espace de stockage de votre appareil est insuffisant. Certaines fonctionnalités hors ligne seront limitées.');
                // Tenter de libérer de l'espace en supprimant les données anciennes
                cleanOldOfflineData();
            }
            
            reject(event.target.error);
        };
        
        // Gérer le cas où la base de données est bloquée (peut arriver sur certains appareils)
        request.onblocked = (event) => {
            console.warn('Base de données bloquée. Veuillez fermer les autres onglets de ce site.');
            reject(new Error('Base de données bloquée'));
        };
        
        // Gérer la mise à jour du schéma si nécessaire
        request.onupgradeneeded = (event) => {
            console.log(`Mise à jour de la base de données IndexedDB vers la version ${dbVersion}`);
            const db = event.target.result;
            
            // Créer les object stores nécessaires s'ils n'existent pas
            if (!db.objectStoreNames.contains('pendingItems')) {
                const store = db.createObjectStore('pendingItems', { keyPath: 'id' });
                // Créer des index pour améliorer les performances de recherche
                store.createIndex('timestamp', 'timestamp', { unique: false });
                store.createIndex('type', 'type', { unique: false });
            }
            
            // Créer un store pour les données mise en cache local
            if (!db.objectStoreNames.contains('cachedData')) {
                const store = db.createObjectStore('cachedData', { keyPath: 'key' });
                store.createIndex('expiry', 'expiry', { unique: false });
            }
        };
        
        // Quand la base de données est prête
        request.onsuccess = (event) => {
            const db = event.target.result;
            
            // Gérer les erreurs inattendues
            db.onerror = (event) => {
                console.error('Erreur de base de données IndexedDB:', event.target.error);
            };
            
            resolve(db);
        };
    });
}

/**
 * Nettoie les données hors ligne anciennes pour libérer de l'espace
 * Important pour les appareils à faible stockage courants au Cameroun
 */
async function cleanOldOfflineData() {
    try {
        // Nettoyer les éléments de plus de 30 jours dans IndexedDB
        if ('indexedDB' in window) {
            const db = await openDatabase().catch(() => null);
            if (db) {
                const thirtyDaysAgo = Date.now() - (30 * 24 * 60 * 60 * 1000);
                const transaction = db.transaction(['cachedData'], 'readwrite');
                const store = transaction.objectStore('cachedData');
                const index = store.index('expiry');
                
                // Supprimer les éléments expirés
                const range = IDBKeyRange.upperBound(thirtyDaysAgo);
                const request = index.openCursor(range);
                
                request.onsuccess = function(event) {
                    const cursor = event.target.result;
                    if (cursor) {
                        store.delete(cursor.primaryKey);
                        cursor.continue();
                    }
                };
            }
        }
        
        // Nettoyer également localStorage
        const itemsToKeep = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.startsWith('lt_cache_')) {
                try {
                    const item = JSON.parse(localStorage.getItem(key));
                    if (item.expiry && item.expiry < Date.now() - (30 * 24 * 60 * 60 * 1000)) {
                        localStorage.removeItem(key);
                    }
                } catch (e) {
                    // Si l'item est corrompu, le supprimer
                    localStorage.removeItem(key);
                }
            }
        }
        
        console.log('Nettoyage des données hors ligne anciennes terminé');
    } catch (error) {
        console.error('Erreur lors du nettoyage des données anciennes:', error);
    }
}

/**
 * Affiche un avertissement concernant le stockage
 * 
 * @param {string} message - Message d'avertissement à afficher
 */
function showStorageWarning(message) {
    // Vérifier si l'élément d'avertissement existe déjà
    let warningElement = document.getElementById('storage-warning');
    
    // Si non, le créer
    if (!warningElement) {
        warningElement = document.createElement('div');
        warningElement.id = 'storage-warning';
        warningElement.className = 'storage-warning';
        document.body.appendChild(warningElement);
        
        // Ajouter des styles inline pour s'assurer qu'il est visible
        Object.assign(warningElement.style, {
            position: 'fixed',
            bottom: '20px',
            left: '20px',
            right: '20px',
            backgroundColor: '#fff3cd',
            color: '#856404',
            padding: '10px 15px',
            borderRadius: '5px',
            boxShadow: '0 2px 4px rgba(0,0,0,0.2)',
            zIndex: '9999',
            fontSize: '14px'
        });
        
        // Ajouter un bouton de fermeture
        const closeButton = document.createElement('button');
        closeButton.textContent = '×';
        closeButton.addEventListener('click', () => warningElement.remove());
        Object.assign(closeButton.style, {
            position: 'absolute',
            right: '10px',
            top: '10px',
            background: 'none',
            border: 'none',
            fontSize: '20px',
            cursor: 'pointer'
        });
        warningElement.appendChild(closeButton);
    }
    
    // Mettre à jour le message
    warningElement.innerHTML = `<p>${message}</p>${warningElement.innerHTML.split('</p>')[1] || ''}`;
    
    // Faire disparaître automatiquement après 10 secondes
    setTimeout(() => {
        if (warningElement && warningElement.parentNode) {
            warningElement.remove();
        }
    }, 10000);
}

/**
 * Récupère les éléments en attente depuis les cookies
 * Utilisé comme fallback pour Opera Mini et les navigateurs sans IndexedDB/localStorage
 * 
 * @returns {Array} Éléments en attente trouvés dans les cookies
 */
function getCookiePendingItems() {
    const items = [];
    const cookies = document.cookie.split(';');
    
    for (let i = 0; i < cookies.length; i++) {
        const cookie = cookies[i].trim();
        
        // Rechercher les cookies contenant des données en attente
        if (cookie.startsWith('lt_pending_')) {
            try {
                const keyParts = cookie.split('=');
                const key = keyParts[0];
                const value = decodeURIComponent(keyParts[1]);
                
                // Extraire l'ID de l'élément depuis la clé du cookie
                const itemId = key.replace('lt_pending_', '');
                
                // Parser la valeur JSON
                const itemData = JSON.parse(value);
                
                // Ajouter l'élément à la liste
                items.push({
                    id: itemId,
                    type: itemData.type || 'unknown',
                    data: itemData.data || {},
                    timestamp: itemData.timestamp || Date.now()
                });
            } catch (e) {
                console.warn(`Impossible de parser le cookie ${cookie}:`, e);
            }
        }
    }
    
    return items;
}

/**
 * Supprime un élément en attente des cookies
 * 
 * @param {string} itemId - ID de l'élément à supprimer
 */
function removeCookiePendingItem(itemId) {
    // Définir le cookie avec une date d'expiration passée pour le supprimer
    document.cookie = `lt_pending_${itemId}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`;
}

/**
 * Récupère tous les éléments en attente de synchronisation
 * Utilise une stratégie de recherche hybride: IndexedDB, localStorage, puis cookies
 * 
 * @returns {Promise<Array>} Liste des éléments en attente
 */
async function getPendingItems() {
    const items = [];
    
    // 1. Tenter d'abord IndexedDB
    try {
        if ('indexedDB' in window) {
            const db = await openDatabase();
            const transaction = db.transaction(['pendingItems'], 'readonly');
            const store = transaction.objectStore('pendingItems');
            const allItems = await new Promise((resolve, reject) => {
                const request = store.getAll();
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
            
            items.push(...allItems);
            console.log(`${allItems.length} éléments récupérés depuis IndexedDB`);
        }
    } catch (error) {
        console.warn('Erreur lors de la récupération depuis IndexedDB:', error);
    }
    
    // 2. Rechercher également dans localStorage (s'il y a des anciens éléments)
    try {
        const pendingItemsJson = localStorage.getItem('lt_pending_items');
        if (pendingItemsJson) {
            const localItems = JSON.parse(pendingItemsJson);
            if (Array.isArray(localItems) && localItems.length > 0) {
                // Vérifier les doublons
                const existingIds = items.map(item => item.id);
                const uniqueLocalItems = localItems.filter(item => !existingIds.includes(item.id));
                
                items.push(...uniqueLocalItems);
                console.log(`${uniqueLocalItems.length} éléments récupérés depuis localStorage`);
            }
        }
    } catch (error) {
        console.warn('Erreur lors de la récupération depuis localStorage:', error);
    }
    
    // 3. En dernier recours, rechercher dans les cookies (pour compatibilité Opera Mini/KaiOS)
    try {
        const cookieItems = getCookiePendingItems();
        if (cookieItems.length > 0) {
            // Vérifier les doublons
            const existingIds = items.map(item => item.id);
            const uniqueCookieItems = cookieItems.filter(item => !existingIds.includes(item.id));
            
            items.push(...uniqueCookieItems);
            console.log(`${uniqueCookieItems.length} éléments récupérés depuis les cookies`);
        }
    } catch (error) {
        console.warn('Erreur lors de la récupération depuis les cookies:', error);
    }
    
    // Trier par timestamp pour synchroniser les plus anciens d'abord
    return items.sort((a, b) => a.timestamp - b.timestamp);
}

/**
 * Récupère le nombre d'éléments en attente de synchronisation
 * 
 * @returns {Promise<number>} Nombre d'éléments en attente
 */
async function getPendingItemsCount() {
    try {
        const items = await getPendingItems();
        return items.length;
    } catch (error) {
        console.error('Erreur lors du comptage des éléments en attente:', error);
        return 0;
    }
}

/**
 * Supprime un élément de la file d'attente après synchronisation réussie
 * 
 * @param {string} itemId - ID de l'élément à supprimer
 * @returns {Promise<boolean>} - Résultat de l'opération
 */
async function removePendingItem(itemId) {
    let success = false;
    
    // 1. Supprimer de IndexedDB
    try {
        if ('indexedDB' in window) {
            const db = await openDatabase();
            const transaction = db.transaction(['pendingItems'], 'readwrite');
            const store = transaction.objectStore('pendingItems');
            await new Promise((resolve, reject) => {
                const request = store.delete(itemId);
                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
            });
            success = true;
        }
    } catch (error) {
        console.warn(`Erreur lors de la suppression de l'élément ${itemId} depuis IndexedDB:`, error);
    }
    
    // 2. Supprimer de localStorage
    try {
        const pendingItemsJson = localStorage.getItem('lt_pending_items');
        if (pendingItemsJson) {
            let localItems = JSON.parse(pendingItemsJson);
            if (Array.isArray(localItems)) {
                const initialLength = localItems.length;
                localItems = localItems.filter(item => item.id !== itemId);
                
                if (localItems.length < initialLength) {
                    localStorage.setItem('lt_pending_items', JSON.stringify(localItems));
                    success = true;
                }
            }
        }
    } catch (error) {
        console.warn(`Erreur lors de la suppression de l'élément ${itemId} depuis localStorage:`, error);
    }
    
    // 3. Supprimer des cookies
    try {
        removeCookiePendingItem(itemId);
        success = true;
    } catch (error) {
        console.warn(`Erreur lors de la suppression de l'élément ${itemId} depuis les cookies:`, error);
    }
    
    return success;
}

/**
 * Effectue une requête fetch avec un timeout adapté au contexte camerounais
 * 
 * @param {string} url - URL à interroger
 * @param {Object} options - Options pour fetch
 * @param {number} options.timeout - Timeout en ms (défaut: 15000ms)
 * @returns {Promise<Response>} - Réponse fetch
 */
function fetchWithTimeout(url, options = {}) {
    const timeout = options.timeout || 15000; // 15 secondes par défaut pour le Cameroun
    
    // AbortController n'est pas supporté par tous les navigateurs (notamment Opera Mini, certains KaiOS)
    // Vérifier d'abord si AbortController est disponible
    if (typeof AbortController === 'undefined') {
        console.warn('AbortController non supporté, timeout non disponible');
        // Fallback vers fetch standard
        return fetch(url, options);
    }
    
    const controller = new AbortController();
    const { signal } = controller;
    
    // Fusionner le signal avec les options existantes
    const fetchOptions = { ...options, signal };
    
    // Créer un timeout qui annulera la requête
    const timeoutId = setTimeout(() => controller.abort(), timeout);
    
    // Pattern avec nettoyage du timeout en cas de succès
    return fetch(url, fetchOptions)
        .then(response => {
            clearTimeout(timeoutId);
            return response;
        })
        .catch(error => {
            clearTimeout(timeoutId);
            // Si l'erreur vient du timeout, la formater pour être plus explicite
            if (error.name === 'AbortError') {
                throw new Error(`La requête a dépassé le délai de ${timeout} ms, problème de réseau possible`);
            }
            throw error;
        });
}

/**
 * Évalue la qualité de la connexion Internet actuelle
 * Adapté spécifiquement pour détecter les réseaux camerounais (MTN, Orange, Camtel)
 * 
 * @returns {string} - Qualité de connexion: 'excellent', 'good', 'medium', 'poor', 'offline'
 */
function getConnectionQuality() {
    // Si pas de connectivité, retourner 'offline'
    if (!navigator.onLine) {
        return 'offline';
    }
    
    // Utiliser l'API Network Information si disponible
    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    
    if (connection) {
        // Récupérer les informations de connexion
        const effectiveType = connection.effectiveType; // '2g', '3g', '4g', etc.
        const downlink = connection.downlink; // Vitesse en Mbps
        const rtt = connection.rtt; // Round-trip time en ms
        const saveData = connection.saveData; // Mode économie de données
        
        // Détection spécifique au Cameroun
        const isSaveDataMode = saveData || localStorage.getItem('lt_data_saving_mode') === 'true';
        
        // Si mode économie de données activé, considérer comme connexion faible
        if (isSaveDataMode) {
            return 'poor';
        }
        
        // Évaluation de la qualité de connexion adaptée au contexte camerounais
        if (effectiveType === '4g' && downlink > 5 && rtt < 100) {
            return 'excellent'; // Rare au Cameroun, principalement dans les grandes villes
        } else if ((effectiveType === '4g' || effectiveType === '3g') && downlink > 1.5 && rtt < 300) {
            return 'good'; // Bonnes zones urbaines
        } else if (effectiveType === '3g' && downlink > 0.5 && rtt < 600) {
            return 'medium'; // Zones urbaines standards
        } else if (effectiveType === '2g' || downlink < 0.5 || rtt > 600) {
            return 'poor'; // Zones rurales ou réseaux congestionnés
        }
        
        // Par défaut, considérer comme 'medium' (cas standard au Cameroun)
        return 'medium';
    }
    
    // Si l'API n'est pas disponible (comme sur Opera Mini populaire au Cameroun), 
    // utiliser une heuristique basée sur les données stockées localement
    
    // Vérifier si nous avons mesuré des temps de réponse récemment
    const lastNetworkCheck = localStorage.getItem('lt_network_check');
    if (lastNetworkCheck) {
        try {
            const networkData = JSON.parse(lastNetworkCheck);
            const now = Date.now();
            
            // Si les données sont récentes (moins de 5 minutes)
            if (now - networkData.timestamp < 5 * 60 * 1000) {
                if (networkData.rtt < 300) {
                    return 'good';
                } else if (networkData.rtt < 600) {
                    return 'medium';
                } else {
                    return 'poor';
                }
            }
        } catch (e) {
            console.warn('Erreur lors de l\'analyse des données réseau:', e);
        }
    }
    
    // Valeur par défaut pour le Cameroun (supposer une connexion moyenne)
    return 'medium';
}

/**
 * Gère l'état déconnecté avec fonctionnalités adaptées au contexte camerounais
 */
function handleOfflineStatus() {
    document.body.classList.remove('lt-online');
    document.body.classList.add('lt-offline');
    document.documentElement.setAttribute('data-connection', 'offline');
    
    // Charger les réservations en attente depuis le stockage local
    loadPendingBookings();
    
    const statusElement = document.querySelector('.offline-status');
    if (statusElement) {
        statusElement.classList.remove('online');
        statusElement.classList.add('offline');
        
        // Mise à jour avec l'icône et les informations détaillées
        statusElement.innerHTML = 
            '<div class="status-icon" data-lt-icon="offline" data-lt-color="#E74C3C"></div>' +
            '<div class="status-content">' +
                '<strong>Vous êtes hors ligne</strong>' +
                '<p>Optimisé pour le Cameroun : vos données seront synchronisées automatiquement</p>' +
            '</div>' +
            '<button id="retry-connection" class="offline-button primary">' +
                '<span data-lt-icon="refresh"></span> Vérifier la connexion' +
            '</button>';
        
        // Initialiser les icônes
        if (window.LifeTravelIcons) {
            window.LifeTravelIcons.preload().then(function() {
                const iconElements = document.querySelectorAll('[data-lt-icon]');
                iconElements.forEach(function(el) {
                    const iconName = el.getAttribute('data-lt-icon');
                    const color = el.getAttribute('data-lt-color');
                    window.LifeTravelIcons.insert(el, iconName, { color: color });
                });
            });
        }
        
        // Ré-attacher l'événement au bouton de reconnexion
        const retryButton = document.getElementById('retry-connection');
        if (retryButton) {
            retryButton.disabled = false;
            retryButton.addEventListener('click', retryConnection);
        }
    }
    
    // Activer le mode économie de données pour la prochaine connexion
    activateDataSavingMode();
    
    // Mettre à jour l'UI des réservations en attente
    updatePendingBookingsUI();
    
    // Vérifier la disponibilité du service worker pour les synchronisations
    checkServiceWorkerSupport();
}

/**
 * Détecte les capacités de stockage du navigateur (adapté pour le Cameroun)
 * @returns {Object} Objet contenant les capacités détectées
 */
function detectStorageCapabilities() {
    const capabilities = {
        indexedDB: false,
        localStorage: false,
        cookieStorage: false,
        storageLimit: 0
    };
    
    // Détection d'Opera Mini (très populaire au Cameroun)
    const isOperaMini = navigator.userAgent.indexOf('Opera Mini') > -1;
    
    // Détection des anciens Android (communs au Cameroun)
    const isOldAndroid = (
        navigator.userAgent.indexOf('Android') > -1 && 
        parseFloat(navigator.userAgent.slice(navigator.userAgent.indexOf('Android') + 8)) < 5
    );
    
    try {
        // Vérification IndexedDB
        capabilities.indexedDB = window.indexedDB !== undefined && 
                               !isOperaMini && // Opera Mini ne supporte pas IndexedDB
                               !isOldAndroid; // Android < 5 a des implémentations buggées
        
        // Vérification localStorage 
        if (window.localStorage) {
            capabilities.localStorage = true;
            
            // Déterminer la limite approximative (Opera Mini est limité à 5MB)
            capabilities.storageLimit = isOperaMini ? 5 * 1024 * 1024 : 10 * 1024 * 1024;
            
            // Test d'allocation si possible
            try {
                const testKey = '_lt_storage_test';
                localStorage.setItem(testKey, 'true');
                localStorage.removeItem(testKey);
            } catch (e) {
                console.warn('localStorage limité ou indisponible');
                capabilities.localStorage = false;
            }
        }
        
        // Vérification des cookies (dernier recours)
        if (!capabilities.indexedDB && !capabilities.localStorage) {
            capabilities.cookieStorage = navigator.cookieEnabled;
        }
    } catch (e) {
        console.error('Erreur lors de la détection des capacités de stockage', e);
    }
    
    return capabilities;
}

// Stockage des capacités détectées pour réutilisation
const STORAGE_CAPABILITIES = detectStorageCapabilities();

/**
 * Charge les réservations en attente depuis le stockage local
 * Optimisé pour Opera Mini et navigateurs limités (populaires au Cameroun)
 */
function loadPendingBookings() {
    // Si aucune réservation n'est déjà chargée
    if (pendingBookings && pendingBookings.length > 0) {
        // Déjà chargé, simplement mettre à jour l'UI
        updatePendingBookingsUI();
        return;
    }

    pendingBookings = [];  // initialisation par défaut
    
    // Stratégie de chargement adaptée aux capacités
    tryLoadFromStorage()
        .then(function(bookings) {
            if (bookings && bookings.length > 0) {
                pendingBookings = bookings;
                updatePendingBookingsUI();
            }
        })
        .catch(function(error) {
            console.error('Erreur de chargement des réservations:', error);
            // Afficher un message d'erreur utilisateur si nécessaire
            showStorageErrorMessage();
        });
}

/**
 * Tente de charger les données à partir du meilleur stockage disponible
 * @returns {Promise} Promise contenant les réservations ou erreur
 */
function tryLoadFromStorage() {
    return new Promise(function(resolve, reject) {
        // Essayer IndexedDB en priorité (si disponible et supporté)
        if (STORAGE_CAPABILITIES.indexedDB) {
            try {
                const request = indexedDB.open('LifeTravelOfflineDB', 1);
                
                request.onupgradeneeded = function(event) {
                    const db = event.target.result;
                    if (!db.objectStoreNames.contains('pendingBookings')) {
                        db.createObjectStore('pendingBookings', { keyPath: 'id' });
                    }
                };
                
                request.onerror = function() {
                    console.warn('IndexedDB error, fallback to localStorage');
                    tryLoadFromLocalStorage(resolve, reject);
                };
                
                request.onsuccess = function(event) {
                    try {
                        const db = event.target.result;
                        const transaction = db.transaction(['pendingBookings'], 'readonly');
                        const store = transaction.objectStore('pendingBookings');
                        const getAllRequest = store.getAll();
                        
                        getAllRequest.onsuccess = function() {
                            resolve(getAllRequest.result || []);
                        };
                        
                        getAllRequest.onerror = function() {
                            console.warn('IndexedDB getAll error, fallback to localStorage');
                            tryLoadFromLocalStorage(resolve, reject);
                        };
                        
                        // Ajouter un timeout pour éviter les blocages sur les appareils lents
                        setTimeout(function() {
                            if (getAllRequest.readyState !== 'done') {
                                console.warn('IndexedDB timeout, fallback to localStorage');
                                tryLoadFromLocalStorage(resolve, reject);
                            }
                        }, 2000); // 2 secondes de timeout maximum
                    } catch (e) {
                        console.error('IndexedDB usage error:', e);
                        tryLoadFromLocalStorage(resolve, reject);
                    }
                };
            } catch (e) {
                console.error('IndexedDB access error:', e);
                tryLoadFromLocalStorage(resolve, reject);
            }
        } else {
            // IndexedDB non disponible, essayer localStorage directement
            tryLoadFromLocalStorage(resolve, reject);
        }
    });
}

/**
 * Tente de charger les données depuis localStorage (fallback)
 * @param {Function} resolve Fonction pour résoudre la promesse
 * @param {Function} reject Fonction pour rejeter la promesse
 */
function tryLoadFromLocalStorage(resolve, reject) {
    if (STORAGE_CAPABILITIES.localStorage) {
        try {
            const storedBookings = localStorage.getItem('lt_pending_bookings');
            if (storedBookings) {
                const bookings = JSON.parse(storedBookings) || [];
                resolve(bookings);
            } else {
                resolve([]);
            }
        } catch (e) {
            console.error('localStorage error:', e);
            tryLoadFromCookies(resolve, reject);
        }
    } else {
        tryLoadFromCookies(resolve, reject);
    }
}

/**
 * Dernier recours: essayer de charger à partir des cookies (très limité)
 * @param {Function} resolve Fonction pour résoudre la promesse
 * @param {Function} reject Fonction pour rejeter la promesse
 */
function tryLoadFromCookies(resolve, reject) {
    if (STORAGE_CAPABILITIES.cookieStorage) {
        try {
            const cookieValue = getCookie('lt_minimal_booking_data');
            if (cookieValue) {
                // Format minimal pour les cookies (limités en taille)
                resolve([{ id: 'cookie-booking', minimized: true, timestamp: Date.now() }]);
            } else {
                resolve([]);
            }
        } catch (e) {
            console.error('Cookie storage error:', e);
            reject(new Error('Aucun stockage disponible'));
        }
    } else {
        reject(new Error('Aucun stockage disponible'));
    }
}

/**
 * Récupère un cookie par son nom
 * @param {string} name Nom du cookie
 * @returns {string|null} Valeur du cookie ou null
 */
function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? match[2] : null;
}

/**
 * Affiche un message d'erreur concernant le stockage
 */
function showStorageErrorMessage() {
    const errorContainer = document.querySelector('.offline-status') || 
                           document.querySelector('.offline-container');
    
    if (errorContainer) {
        const errorMessage = document.createElement('div');
        errorMessage.className = 'storage-error-message';
        errorMessage.innerHTML = 
            '<strong>Votre navigateur a des limitations de stockage</strong>' +
            '<p>Les réservations hors ligne pourraient ne pas être enregistrées correctement.</p>';
        
        // Insérer au début du conteneur
        errorContainer.insertBefore(errorMessage, errorContainer.firstChild);
    }
}

/**
 * Met à jour l'interface utilisateur avec les réservations en attente
 */
function updatePendingBookingsUI() {
    const pendingBookingsElement = document.getElementById('pending-bookings');
    if (!pendingBookingsElement) return;
    
    if (pendingBookings && pendingBookings.length > 0) {
        pendingBookingsElement.innerHTML = 
            `<span class="count">${pendingBookings.length}</span> réservation(s) en attente`;
        pendingBookingsElement.classList.add('has-pending');
    } else {
        pendingBookingsElement.textContent = 'Aucune';
        pendingBookingsElement.classList.remove('has-pending');
    }
}

/**
 * Vérifie la disponibilité du service worker pour les synchronisations
 */
function checkServiceWorkerSupport() {
    const syncIndicator = document.getElementById('sync-status');
    const syncMessage = syncIndicator ? syncIndicator.querySelector('.sync-message') : null;
    
    if (syncMessage) {
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            navigator.serviceWorker.ready.then(function(registration) {
                if (registration.sync) {
                    syncMessage.textContent = 'Synchronisation automatique disponible';
                    syncIndicator.classList.add('supported');
                } else {
                    syncMessage.textContent = 'Synchronisation manuelle uniquement';
                    syncIndicator.classList.add('manual');
                }
            }).catch(function() {
                syncMessage.textContent = 'Synchronisation manuelle uniquement';
                syncIndicator.classList.add('manual');
            });
        } else {
            syncMessage.textContent = 'Synchronisation manuelle uniquement';
            syncIndicator.classList.add('manual');
        }
    }
}

/**
 * Tente de se reconnecter au réseau
 */
function retryConnection() {
    const button = document.getElementById('retry-connection');
    if (button) {
        button.disabled = true;
        button.textContent = 'Vérification...';
    }
    
    const statusElement = document.querySelector('.offline-status');
    if (statusElement) {
        statusElement.innerHTML = '<strong>Vérification de la connexion...</strong>';
    }
    
    // Vérifier la connexion après un court délai
    setTimeout(function() {
        if (navigator.onLine) {
            handleOnlineStatus();
        } else {
            // Si toujours hors ligne
            if (button) {
                button.textContent = 'Toujours hors-ligne - Réessayer';
                button.disabled = false;
            }
            
            if (statusElement) {
                statusElement.innerHTML = '<strong>Vous êtes toujours hors-ligne</strong>' + 
                    '<p>Veuillez activer votre connexion WiFi ou données mobiles et réessayer</p>';
            }
        }
    }, 2000);
}

/**
 * Synchronise les données en attente (à utiliser lorsque la connexion est rétablie)
 * @param {boolean} force - Si vrai, force la synchronisation même avec une connexion faible
 * @returns {Promise} - Promesse résolue quand la synchronisation est terminée
 */
function syncPendingData(force = false) {
    // Mettre à jour l'indicateur de synchronisation
    const syncIndicator = document.getElementById('sync-status');
    const syncMessage = syncIndicator ? syncIndicator.querySelector('.sync-message') : null;
    
    if (syncMessage) {
        syncMessage.textContent = 'Synchronisation en cours...';
        syncIndicator.className = 'sync-indicator syncing';
    }
    
    // Si aucune réservation en attente, rien à faire
    if (!pendingBookings || pendingBookings.length === 0) {
        if (syncMessage) {
            syncMessage.textContent = 'Aucune donnée à synchroniser';
            syncIndicator.className = 'sync-indicator idle';
        }
        return Promise.resolve();
    }
    
    // Vérifier d'abord la qualité de la connexion
    return testConnection()
        .then(function(connectionInfo) {
            // Ne pas synchroniser avec une connexion faible, sauf si forcé
            if (!force && connectionInfo.quality === 'poor') {
                if (syncMessage) {
                    syncMessage.textContent = 'En attente d\'une meilleure connexion';
                    syncIndicator.className = 'sync-indicator waiting';
                }
                return Promise.reject(new Error('Connexion trop faible pour synchroniser'));
            }
            
            // Créer un tableau de promesses pour chaque réservation
            const syncPromises = pendingBookings.map(function(booking, index) {
                // Simuler un délai progressif pour éviter de surcharger la connexion
                const delay = index * 500;
                
                return new Promise(function(resolve) {
                    setTimeout(function() {
                        // Appel au serveur pour synchroniser cette réservation
                        fetch('/wp-admin/admin-ajax.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                action: 'life_travel_sync_offline_booking',
                                booking_data: JSON.stringify(booking),
                                nonce: booking.nonce || ''
                            })
                        })
                        .then(function(response) {
                            if (response.ok) {
                                return response.json();
                            }
                            throw new Error('Erreur réseau');
                        })
                        .then(function(data) {
                            if (data.success) {
                                // Supprimer cette réservation des données en attente
                                resolve({ success: true, id: booking.id });
                            } else {
                                resolve({ success: false, id: booking.id, error: data.message });
                            }
                        })
                        .catch(function(error) {
                            resolve({ success: false, id: booking.id, error: error.message });
                        });
                    }, delay);
                });
            });
            
            // Attendre que toutes les synchronisations soient terminées
            return Promise.all(syncPromises)
                .then(function(results) {
                    // Filtrer les réservations qui ont échoué
                    const successIds = results.filter(r => r.success).map(r => r.id);
                    const failedResults = results.filter(r => !r.success);
                    
                    // Mettre à jour la liste des réservations en attente
                    pendingBookings = pendingBookings.filter(function(booking) {
                        return !successIds.includes(booking.id);
                    });
                    
                    // Enregistrer les réservations restantes dans le stockage
                    if ('indexedDB' in window) {
                        // Utiliser IndexedDB si disponible
                        const request = indexedDB.open('LifeTravelOfflineDB', 1);
                        
                        request.onsuccess = function(event) {
                            const db = event.target.result;
                            const transaction = db.transaction(['pendingBookings'], 'readwrite');
                            const store = transaction.objectStore('pendingBookings');
                            
                            // Supprimer toutes les réservations
                            store.clear();
                            
                            // Ajouter les réservations restantes
                            pendingBookings.forEach(function(booking) {
                                store.add(booking);
                            });
                        };
                    } else if (CONFIG.fallbackToLocalStorage) {
                        // Fallback à localStorage
                        localStorage.setItem('lt_pending_bookings', JSON.stringify(pendingBookings));
                    }
                    
                    // Mettre à jour l'UI
                    updatePendingBookingsUI();
                    
                    // Mettre à jour le message de statut
                    if (syncMessage) {
                        if (failedResults.length === 0) {
                            syncMessage.textContent = 'Synchronisation réussie';
                            syncIndicator.className = 'sync-indicator success';
                        } else {
                            syncMessage.textContent = 
                                `${successIds.length} réservations synchronisées, ${failedResults.length} en attente`;
                            syncIndicator.className = 'sync-indicator partial';
                        }
                    }
                    
                    return { success: successIds.length, failed: failedResults.length };
                });
        })
        .catch(function(error) {
            console.error('Erreur lors de la synchronisation:', error);
            if (syncMessage) {
                syncMessage.textContent = 'Synchronisation échouée';
                syncIndicator.className = 'sync-indicator failed';
            }
            return Promise.reject(error);
        });
}

/**
 * Charge les pages disponibles en cache
 * @param {boolean} forceRefresh - Si vrai, force le rafraîchissement du cache
 * @returns {Promise} - Promesse résolue quand le chargement est terminé
 */
function loadCachedPages(forceRefresh = false) {
    // Vérifier si l'API Cache est disponible
    if (!('caches' in window)) {
        return Promise.reject(new Error('API Cache non disponible'));
    }
    
    const cachedPagesList = document.getElementById('cached-pages');
    if (cachedPagesList) {
        // Montrer l'indicateur de chargement
        cachedPagesList.innerHTML = '<li class="loading-item">Chargement des pages disponibles...</li>';
    }
    
    // Rafraîchir le cache si demandé
    if (forceRefresh && navigator.onLine) {
        // Notifier le service worker de rafraîchir le cache
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                action: 'REFRESH_CACHE',
                urls: ['/', '/excursions/', '/a-propos/', '/contact/']
            });
        }
        
        // Attendre un peu pour que le service worker ait le temps de rafraîchir le cache
        return new Promise(function(resolve) {
            setTimeout(function() {
                loadCachedPagesFromCache().then(resolve).catch(resolve);
            }, 1500); // Délai adapté au contexte camerounais
        });
    }
    
    return loadCachedPagesFromCache();
}

/**
 * Fonction interne pour charger les pages depuis le cache
 * @private
 */
function loadCachedPagesFromCache() {
    return caches.open(CACHE_NAME)
        .then(function(cache) {
            return cache.keys();
        })
        .then(function(requests) {
            // Filtrer pour garder uniquement les pages HTML
            const htmlRequests = requests.filter(function(request) {
                const url = new URL(request.url);
                return !url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|webp|svg|woff|woff2|ttf|eot)$/i)
                    && url.pathname !== '/offline/' && !url.pathname.includes('/wp-admin/');
            });
            
            // Si des pages en cache sont trouvées
            const cachedPagesList = document.getElementById('cached-pages');
            if (cachedPagesList && htmlRequests.length > 0) {
                cachedPagesList.innerHTML = '';
                
                // Créer un élément de liste pour chaque page
                const addedPaths = new Set(); // Éviter les doublons
                
                htmlRequests.forEach(function(request) {
                    const url = new URL(request.url);
                    const pathname = url.pathname;
                    
                    // Éviter les doublons et les pages non pertinentes
                    if ((pathname === '/' || pathname.endsWith('/')) && !addedPaths.has(pathname)) {
                        addedPaths.add(pathname);
                        
                        const li = document.createElement('li');
                        const a = document.createElement('a');
                        
                        a.href = pathname;
                        a.textContent = getPageName(pathname);
                        
                        // Ajouter une classe pour l'animation
                        li.appendChild(a);
                        li.classList.add('fade-in');
                        
                        // Ajouter après un court délai pour l'animation
                        setTimeout(function() {
                            cachedPagesList.appendChild(li);
                        }, addedPaths.size * 100); // Délai progressif
                    }
                });
                
                // Si aucune page trouvée après filtrage
                if (addedPaths.size === 0) {
                    cachedPagesList.innerHTML = '<li class="no-pages">Aucune page en cache actuellement</li>';
                }
            } else if (cachedPagesList) {
                cachedPagesList.innerHTML = '<li class="no-pages">Aucune page en cache actuellement</li>';
            }
            
            return htmlRequests;
        })
        .catch(function(error) {
            console.error('Erreur lors de la récupération du cache :', error);
            const cachedPagesList = document.getElementById('cached-pages');
            if (cachedPagesList) {
                cachedPagesList.innerHTML = '<li class="error">Erreur lors de la récupération du cache</li>';
            }
            return [];
        });
}

/**
 * Convertit le chemin d'URL en nom de page convivial
 */
function getPageName(pathname) {
    if (pathname === '/') return 'Accueil';
    
    const parts = pathname.split('/').filter(Boolean);
    if (parts.length === 0) return 'Page inconnue';
    
    // Convertir le dernier segment en nom convivial
    let pageName = parts[parts.length - 1] || parts[0];
    pageName = pageName.replace(/-/g, ' ');
    pageName = pageName.charAt(0).toUpperCase() + pageName.slice(1);
    
    return pageName;
}

/**
 * Vérifie si une ressource spécifique est en cache
 */
function isResourceCached(url) {
    return caches.open(CACHE_NAME)
        .then(function(cache) {
            return cache.match(url);
        })
        .then(function(response) {
            return !!response;
        })
        .catch(function() {
            return false;
        });
}

/**
 * Gère la suppression du cache lors de la reconnexion
 * (utilisée pour forcer le rafraîchissement des ressources)
 */
function handleCacheRefresh() {
    // Cette fonction peut être utilisée pour nettoyer certaines parties du cache
    // quand l'utilisateur se reconnecte, afin d'obtenir les dernières versions
    if (!navigator.onLine) return;
    
    caches.open(CACHE_NAME)
        .then(function(cache) {
            // Liste des ressources à toujours rafraîchir
            const resourcesToRefresh = [
                '/',
                '/excursions/',
                '/a-propos/',
                '/contact/'
            ];
            
            // Supprimer ces ressources pour qu'elles soient récupérées fraîches
            Promise.all(resourcesToRefresh.map(url => cache.delete(url)))
                .catch(error => console.log('Erreur lors du rafraîchissement du cache:', error));
        });
}

/**
 * Garantit le chargement de toutes les ressources critiques avant de continuer
 * Cette fonction est essentielle pour la robustesse dans le contexte camerounais
 * 
 * @returns {Promise<boolean>} - True si toutes les ressources sont chargées, sinon false
 */
async function ensureResourcesLoaded() {
    // Enregistrer le début du chargement pour le suivi des performances
    const startTime = performance.now();
    console.log('Début du chargement des ressources critiques');
    
    // Éléments d'UI pour indiquer le chargement
    const loadingElement = document.getElementById('loading-screen');
    const progressElement = document.getElementById('loading-progress');
    const statusElement = document.getElementById('loading-status');
    
    // Montrer l'écran de chargement si disponible
    if (loadingElement) {
        loadingElement.style.display = 'flex';
    }
    
    // Liste des ressources critiques à charger
    const criticalResources = [
        { type: 'script', src: '../assets/js/life-travel-icons.js', retries: 3 },
        { type: 'css', href: '../assets/css/adaptive-offline.css', retries: 2 },
        { type: 'image', src: '../assets/img/logos/logo-main.png', retries: 1 },
        { type: 'image', src: '../assets/img/offline-placeholder.svg', retries: 1 }
    ];
    
    // Fonction pour mettre à jour la progression
    const updateProgress = (current, total, message = '') => {
        const percentage = Math.round((current / total) * 100);
        if (progressElement) {
            progressElement.style.width = `${percentage}%`;
            progressElement.setAttribute('aria-valuenow', percentage);
        }
        if (statusElement && message) {
            statusElement.textContent = message;
        }
        console.log(`Progression: ${percentage}% - ${message}`);
    };
    
    // Tableau pour stocker les ressources réussies
    const loadedResources = [];
    const failedResources = [];
    
    // Fonction de chargement avec retry et timeout
    async function loadResource(resource, attempt = 1) {
        const maxAttempts = resource.retries + 1;
        const timeout = 8000; // 8s timeout adapté pour les connexions lentes camerounaises
        
        updateProgress(loadedResources.length, criticalResources.length, 
                      `Chargement de ${resource.src || resource.href} (${attempt}/${maxAttempts})`);
        
        return new Promise(async (resolve) => {
            // Create un timeout
            const timeoutId = setTimeout(() => {
                console.warn(`Timeout lors du chargement de ${resource.src || resource.href} (tentative ${attempt}/${maxAttempts})`);
                if (attempt < maxAttempts) {
                    // Réessayer avec backoff exponentiel (important pour les réseaux intermittents camerounais)
                    const backoffDelay = Math.min(1000 * Math.pow(1.5, attempt - 1), 8000);
                    console.log(`Nouvelle tentative dans ${backoffDelay}ms`);
                    setTimeout(() => {
                        loadResource(resource, attempt + 1).then(resolve);
                    }, backoffDelay);
                } else {
                    console.error(`Échec du chargement après ${maxAttempts} tentatives pour ${resource.src || resource.href}`);
                    failedResources.push(resource);
                    resolve(false);
                }
            }, timeout);
            
            try {
                // Différentes méthodes de chargement selon le type de ressource
                if (resource.type === 'script') {
                    await loadScript(resource.src);
                } else if (resource.type === 'css') {
                    await loadCSS(resource.href);
                } else if (resource.type === 'json') {
                    await fetch(resource.src);
                } else if (resource.type === 'image') {
                    await loadImage(resource.src);
                }
                
                // Succès
                clearTimeout(timeoutId);
                loadedResources.push(resource);
                updateProgress(loadedResources.length, criticalResources.length, 'Ressource chargée');
                resolve(true);
            } catch (error) {
                clearTimeout(timeoutId);
                console.warn(`Erreur lors du chargement de ${resource.src || resource.href}: ${error.message}`);
                
                if (attempt < maxAttempts) {
                    // Réessayer avec backoff exponentiel
                    const backoffDelay = Math.min(1000 * Math.pow(1.5, attempt - 1), 8000);
                    console.log(`Nouvelle tentative dans ${backoffDelay}ms`);
                    setTimeout(() => {
                        loadResource(resource, attempt + 1).then(resolve);
                    }, backoffDelay);
                } else {
                    console.error(`Échec du chargement après ${maxAttempts} tentatives`);
                    failedResources.push(resource);
                    resolve(false);
                }
            }
        });
    }
    
    // Fonctions auxiliaires de chargement de ressources
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.body.appendChild(script);
        });
    }
    
    function loadCSS(href) {
        return new Promise((resolve, reject) => {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.onload = resolve;
            link.onerror = reject;
            document.head.appendChild(link);
        });
    }
    
    function loadImage(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.src = src;
            img.onload = resolve;
            img.onerror = reject;
        });
    }
    
    // Charger toutes les ressources critiques parallèlement
    const results = await Promise.allSettled(criticalResources.map(resource => loadResource(resource)));
    
    // Analyser les résultats
    const allLoaded = results.every(result => result.status === 'fulfilled' && result.value === true);
    const loadedCount = results.filter(result => result.status === 'fulfilled' && result.value === true).length;
    
    // Mettre à jour l'UI pour indiquer la progression
    updateProgress(loadedCount, criticalResources.length, 
                 allLoaded ? 'Chargement terminé' : 'Chargement partiel');
    
    // Désactiver l'écran de chargement
    setTimeout(() => {
        if (loadingElement) {
            loadingElement.style.display = 'none';
        }
        
        // Si certaines ressources ont échoué, montrer un avertissement
        if (failedResources.length > 0) {
            console.warn(`${failedResources.length} ressources n'ont pas pu être chargées:`, 
                        failedResources.map(r => r.src || r.href));
            
            // Mode dégradé activé
            document.body.classList.add('degraded-mode');
            
            // Informer l'utilisateur des fonctionnalités limitées
            showStorageWarning('Certaines ressources n\'ont pas pu être chargées. ' +
                              'Certaines fonctionnalités pourraient être limitées.');
        }
    }, 1000);
    
    // Mesurer le temps total
    const loadTime = performance.now() - startTime;
    console.log(`Chargement terminé en ${Math.round(loadTime)}ms. ${loadedCount}/${criticalResources.length} ressources chargées`);
    
    // Mettre à jour les mesures de performances dans localStorage pour adaptation future
    try {
        localStorage.setItem('lt_last_load_time', JSON.stringify({
            timestamp: Date.now(),
            loadTime: loadTime,
            resourcesLoaded: loadedCount,
            resourcesTotal: criticalResources.length,
            failed: failedResources.length
        }));
    } catch (e) {
        console.warn('Impossible de sauvegarder les métriques de performance:', e);
    }
    
    return allLoaded;
}

// Point d'initialisation principal, déclenché au chargement du document
document.addEventListener('DOMContentLoaded', async function() {
    console.log('Life Travel Offline: Démarrage de l\'initialisation - Optimisé pour le Cameroun');
    
    // Assurer le chargement de toutes les ressources critiques avant d'initialiser l'application
    await ensureResourcesLoaded();
    
    // Vérifier l'état de la connexion et mettre à jour l'interface
    updateConnectionStatus();
    
    // Initialiser les gestionnaires d'événements
    initEventListeners();
    
    // Charger les excursions depuis le cache si disponibles
    loadCachedExcursions();
    
    // Initialiser le mode économie de données si activé
    if (localStorage.getItem('lt_data_saving_mode') === 'true') {
        activateDataSavingMode();
    }
    
    // Détecter les navigateurs avec fonctionnalités limitées (comme Opera Mini)
    if (isLimitedBrowser()) {
        document.body.classList.add('limited-browser');
    }
    
    // Vérifier s'il y a des données en attente de synchronisation
    try {
        const pendingCount = await getPendingItemsCount();
        if (pendingCount > 0) {
            console.log(`${pendingCount} éléments en attente de synchronisation`);        
            // Mettre à jour le badge de synchronisation s'il existe
            const syncBadge = document.getElementById('sync-badge');
            if (syncBadge) {
                syncBadge.textContent = pendingCount;
                syncBadge.style.display = 'inline-block';
            }
            
            // Si nous sommes en ligne, tenter une synchronisation après un court délai
            if (navigator.onLine) {
                setTimeout(async () => {
                    await syncPendingData();
                }, 5000); // 5 secondes après le chargement de la page
            }
        }
    } catch (error) {
        console.error('Erreur lors de la vérification des données en attente:', error);
    }
    
    console.log('Life Travel Offline: Initialisation terminée');
});
