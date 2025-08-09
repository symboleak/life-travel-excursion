/**
 * Life Travel - Service Worker Optimisé pour le Cameroun
 * 
 * Ce service worker permet au site Life Travel de fonctionner partiellement
 * en mode hors-ligne, en mettant en cache les ressources essentielles et
 * en offrant une expérience dégradée mais fonctionnelle aux utilisateurs
 * en cas de connexion internet intermittente (contexte camerounais).
 * 
 * @package Life Travel Excursion
 * @version 2.4.0
 */

// Namespace global pour éviter les collisions avec d'autres scripts
self.LIFE_TRAVEL = self.LIFE_TRAVEL || {};

// Détection de KaiOS à partir des propriétés du client
// Ce code s'exécute lors de l'installation du SW
self.addEventListener('message', function(event) {
  if (event.data && event.data.type === 'CLIENT_INFO') {
    // Stocker les informations du client pour référence future
    self.LIFE_TRAVEL.clientInfo = event.data.clientInfo || {};
    
    // Vérifier si le client est un appareil KaiOS
    if (self.LIFE_TRAVEL.clientInfo.isKaiOS) {
      console.info('Life Travel SW: KaiOS device detected, applying optimizations');
      self.LIFE_TRAVEL.isKaiOS = true;
      self.LIFE_TRAVEL.kaiOSVersion = self.LIFE_TRAVEL.clientInfo.kaiOSVersion || '2.5';
      
      // Adapter les timeout réseau pour KaiOS
      self.LIFE_TRAVEL.CONFIG.networkTimeout = self.LIFE_TRAVEL.CONFIG.kaiOS.networkTimeout;
      self.LIFE_TRAVEL.CONFIG.shortNetworkTimeout = self.LIFE_TRAVEL.CONFIG.kaiOS.shortNetworkTimeout;
      self.LIFE_TRAVEL.CONFIG.longNetworkTimeout = self.LIFE_TRAVEL.CONFIG.kaiOS.longNetworkTimeout;
    }
  }
});

// Nom et version du cache (harmonisé avec PHP)
const CACHE_NAME = 'life-travel-cache-v2';
self.LIFE_TRAVEL.CACHE_NAME = CACHE_NAME; // Exposer dans le namespace
const PLUGIN_BASE = new URL('./', self.location).pathname;
self.LIFE_TRAVEL.PLUGIN_BASE = PLUGIN_BASE;
const OFFLINE_URL = PLUGIN_BASE + 'offline.html';
self.LIFE_TRAVEL.OFFLINE_URL = OFFLINE_URL; // Exposer dans le namespace

// Configuration des timeouts, adaptés aux réalités des réseaux camerounais
self.LIFE_TRAVEL.CONFIG = {
  // Timeouts pour les différents types de requêtes (en millisecondes)
  networkTimeout: 8000,        // Délai standard
  shortNetworkTimeout: 4000,   // Pour les ressources moins critiques
  longNetworkTimeout: 15000,   // Pour les ressources prioritaires
  backgroundSyncTimeout: 30000, // Pour les synchronisations en arrière-plan
  
  // Configuration spécifique KaiOS (téléphones à touches très populaires au Cameroun)
  kaiOS: {
    // Timeouts spécifiques pour KaiOS (plus longs à cause des limitations CPU/mémoire)
    networkTimeout: 12000,       // Délai standard plus long pour KaiOS
    shortNetworkTimeout: 6000,   // Délai court plus long pour KaiOS
    longNetworkTimeout: 20000,   // Délai long plus long pour KaiOS
    
    // Taille maximale des éléments en cache réduite pour les appareils KaiOS (à mémoire limitée)
    maxCacheSize: {
      'document': 1 * 1024 * 1024,  // 1MB (moitié de la taille standard)
      'image': 500 * 1024,         // 500KB (moitié de la taille standard)
      'script': 250 * 1024,        // 250KB (moitié de la taille standard)
      'style': 100 * 1024,         // 100KB (moitié de la taille standard)
      'font': 500 * 1024,          // 500KB (moitié de la taille standard)
      'other': 100 * 1024          // 100KB (moitié de la taille standard)
    },
    
    // Stratégies de mise en cache limitées pour économiser la mémoire
    skipLargeAssets: true,       // Ne pas mettre en cache les ressources volumineuses
    preferLightweightVersions: true, // Préférer les versions légères des ressources
    useMinimalCache: true        // Utiliser une stratégie de cache minimale
  },
  
  // Taille maximale des éléments en cache par type (en octets)
  maxCacheSize: {
    'document': 2 * 1024 * 1024,  // 2MB
    'image': 1 * 1024 * 1024,     // 1MB
    'script': 500 * 1024,         // 500KB
    'style': 250 * 1024,          // 250KB
    'font': 1 * 1024 * 1024,      // 1MB
    'other': 250 * 1024           // 250KB
  },
  
  // Classifier les ressources par priorité
  highPriorityContent: ['document', 'script', 'navigation'],
  lowPriorityContent: ['image', 'audio', 'video'],
  
  // Ressources spécifiques pour KaiOS - priorité aux éléments essentiels
  kaiOSEssentialContent: [
    PLUGIN_BASE + 'assets/css/life-travel-kaios.css',
    PLUGIN_BASE + 'assets/css/lightweight.css',
    PLUGIN_BASE + 'assets/js/life-travel-kaios.js',
    PLUGIN_BASE + 'assets/js/life-travel-kaios-optimization.js',
    OFFLINE_URL,
    PLUGIN_BASE + 'assets/js/life-travel-polyfills.js'
  ],
  
  // Options de backoff exponentiel
  backoff: {
    initialDelay: 1000,       // Délai initial
    maxDelay: 30000,          // Délai maximum
    factor: 1.5,              // Facteur multiplicatif entre les tentatives
    maxAttempts: 5,           // Nombre maximum de tentatives
    jitter: 0.1               // Facteur de variation aléatoire (10%)
  }
};

// Information sur la connexion (sera mise à jour dynamiquement)
self.LIFE_TRAVEL.connection = {
  type: 'unknown',               // Type de connexion
  effectiveType: '3g',           // Type effectif de connexion
  downlink: 1.0,                 // Vitesse de téléchargement (Mbps)
  rtt: 500,                      // Round-trip time (ms)
  saveData: false,               // Mode économie de données activé
  failedRequests: 0,             // Compteur d'échecs réseau
  lastStatus: 'online',          // Dernier état connu de la connexion
  lastUpdate: Date.now()          // Dernier moment de mise à jour
};

// Ressources à mettre en cache lors de l'installation
const CORE_ASSETS = [
  // Pages essentielles (scopées au plugin)
  OFFLINE_URL,

  // Styles essentiels
  PLUGIN_BASE + 'assets/css/adaptive-offline.css',
  PLUGIN_BASE + 'assets/css/life-travel-excursion.css',

  // Scripts essentiels
  PLUGIN_BASE + 'assets/js/network-detector.js',
  PLUGIN_BASE + 'assets/js/life-travel-polyfills.js',

  // Images essentielles
  PLUGIN_BASE + 'assets/img/logos/logo-main.svg',
  PLUGIN_BASE + 'assets/img/offline-placeholder.svg'
];

// Installation du service worker
self.addEventListener('install', event => {
  console.log('Service Worker: Installation de la version optimisée pour le Cameroun');
  
  event.waitUntil(
    (async () => {
      try {
        // Initialisation de l'information de réseau
        // Importante pour le contexte camerounais où les conditions réseau sont variables
        updateNetworkInfo();
        
        // Ouvrir le cache
        const cache = await caches.open(CACHE_NAME);
        console.log('Service Worker: Cache ouvert avec succès');
        
        // Mise en cache des ressources avec gestion des erreurs robuste
        // Utilisation de Promise.allSettled pour continuer même si certains éléments échouent
        const results = await Promise.allSettled(CORE_ASSETS.map(async url => {
          try {
            // Utiliser safeFetch pour une meilleure résilience aux problèmes réseau
            const response = await safeFetch(url, { 
              timeout: self.LIFE_TRAVEL.CONFIG.longNetworkTimeout,
              retries: 2,
              useBackoff: true
            });
            
            if (response.ok) {
              await cache.put(url, response);
              console.log(`Service Worker: Mise en cache réussie pour ${url}`);
              return { url, success: true };
            } else {
              console.warn(`Service Worker: Impossible de mettre en cache ${url}, statut: ${response.status}`);
              return { url, success: false, status: response.status };
            }
          } catch (error) {
            console.warn(`Service Worker: Échec de mise en cache pour ${url}`, error.message || 'Erreur inconnue');
            return { url, success: false, error: error.message || 'Erreur inconnue' };
          }
        }));
        
        // Analyser les résultats pour un meilleur diagnostic
        const succeeded = results.filter(r => r.status === 'fulfilled' && r.value.success).length;
        const failed = CORE_ASSETS.length - succeeded;
        
        console.log(`Service Worker: Mise en cache terminée. ${succeeded}/${CORE_ASSETS.length} ressources mises en cache avec succès.`);
        
        if (failed > 0) {
          console.warn(`Service Worker: ${failed} ressources n'ont pas pu être mises en cache. L'installation continue néanmoins.`);
        }
        
        // Force l'activation immédiate sans attendre la fermeture des onglets
        await self.skipWaiting();
        console.log('Service Worker: skipWaiting() réussi');
        
        return { status: 'success', cached: succeeded, failed };
      } catch (error) {
        // Même en cas d'échec global, on continue l'installation
        // Critical pour le contexte camerounais: meilleur d'avoir un SW partiellement fonctionnel que rien
        console.error('Service Worker: Erreur critique lors de l\'installation', error);
        
        // Tenter quand même le skipWaiting pour assurer l'activation
        await self.skipWaiting().catch(e => console.error('skipWaiting failed:', e));
        
        return { status: 'partial-failure', error: error.message || 'Erreur inconnue' };
      }
    })()
  );
});

// Activation: nettoyer les anciens caches et initialiser les fonctionnalités
self.addEventListener('activate', event => {
  console.log('Service Worker: Activation de la version optimisée pour le Cameroun');
  
  event.waitUntil(
    (async () => {
      try {
        // 1. Nettoyage des anciens caches
        const cacheNames = await caches.keys();
        const outdatedCaches = cacheNames.filter(cacheName => {
          return cacheName.startsWith('life-travel-cache-') && cacheName !== CACHE_NAME;
        });
        
        // Journaliser les caches identifiés pour suppression
        if (outdatedCaches.length > 0) {
          console.log(`Service Worker: ${outdatedCaches.length} ancien(s) cache(s) à supprimer:`, outdatedCaches);
        } else {
          console.log('Service Worker: Aucun ancien cache à supprimer');
        }
        
        // Supprimer les anciens caches de manière robuste
        const deletionResults = await Promise.allSettled(outdatedCaches.map(async cacheName => {
          try {
            const success = await caches.delete(cacheName);
            return { cacheName, success };
          } catch (error) {
            console.error(`Service Worker: Erreur lors de la suppression du cache ${cacheName}:`, error);
            return { cacheName, success: false, error: error.message };
          }
        }));
        
        // Résumé des suppressions
        const successfulDeletions = deletionResults.filter(r => r.status === 'fulfilled' && r.value.success).length;
        if (successfulDeletions > 0) {
          console.log(`Service Worker: ${successfulDeletions}/${outdatedCaches.length} cache(s) supprimé(s) avec succès`);
        }
        
        // 2. Initialisation des fonctionnalités et détection des fonctionnalités spécifiques au contexte camerounais
        
        // Vérifier la connexion et adapter les stratégies en conséquence
        updateNetworkInfo();
        
        // Vérifier si le mode économie de données est actif (très important au Cameroun)
        const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (conn && conn.saveData) {
          console.log('Service Worker: Mode économie de données détecté - Adaptation pour le contexte camerounais');
          self.LIFE_TRAVEL.connection.saveData = true;
        }
        
        // 3. Prendre le contrôle de tous les clients sans recharger (clients.claim)
        await self.clients.claim();
        console.info('Service Worker: Contrôle pris sur tous les clients');
        
        // 4. Notifier tous les clients que le service worker est prêt
        const windowClients = await self.clients.matchAll({ type: 'window' });
        for (const client of windowClients) {
          client.postMessage({
            type: 'SW_ACTIVATED',
            cacheVersion: CACHE_NAME,
            timestamp: Date.now(),
            networkStatus: self.LIFE_TRAVEL.connection.lastStatus,
            saveDataMode: self.LIFE_TRAVEL.connection.saveData
          });
        }
        
        console.log('Service Worker: Activation complète et réussie');
        return { status: 'success' };
      } catch (error) {
        console.error('Service Worker: Erreur pendant la phase d\'activation:', error);
        
        // Même en cas d'erreur, essayer de prendre le contrôle des clients
        try {
          await self.clients.claim();
        } catch (claimError) {
          console.error('Service Worker: Échec de clients.claim():', claimError);
        }
        
        return { status: 'error', message: error.message || 'Erreur inconnue' };
      }
    })()
  );
});

// Configuration pour le contexte camerounais
const CAMEROON_CONFIG = {
  // Temps maximum pour attendre avant de basculer sur le cache (ms)
  networkTimeout: 5000,
  // Connexions très lentes au Cameroun - timeout plus long pour les ressources importantes
  longNetworkTimeout: 10000,
  // Timeout court pour les ressources critiques sur réseaux MTN/Orange faibles
  shortNetworkTimeout: 3000,
  // Nombre maximum de tentatives pour les ressources critiques
  maxRetries: 3,
  // Délai entre les tentatives (backoff exponentiel)
  retryDelay: 1000,
  // Types de contenus prioritaires (doivent être chargés en premier)
  highPriorityContent: ['document', 'style', 'font'],
  // Types de contenus à mettre en cache agressivement
  aggressivelyCacheContent: ['image', 'font', 'script', 'style'],
  // Types de contenu lourds à éviter sur connexions lentes
  lowPriorityContent: ['video', 'audio'],
  // Taille maximale de cache par type de contenu (en octets)
  maxCacheSize: {
    images: 5 * 1024 * 1024, // 5MB max pour les images (très important au Cameroun)
    fonts: 2 * 1024 * 1024,  // 2MB pour les polices
    scripts: 3 * 1024 * 1024, // 3MB pour les scripts
    styles: 1 * 1024 * 1024   // 1MB pour les styles
  },
  // URLs des endpoints de vérification de connectivité (opérateurs camerounais)
  connectivityCheckEndpoints: [
    'https://www.google.com/favicon.ico',
    'https://www.camtel.cm/favicon.ico',
    'https://www.orange.cm/favicon.ico',
    'https://www.mtn.cm/favicon.ico'
  ]
};

// Exposer la configuration dans le namespace
self.LIFE_TRAVEL.CONFIG = CAMEROON_CONFIG;

// Namespace pour les états de connexion (pour éviter les collisions)
self.LIFE_TRAVEL.connection = {
  type: 'unknown',       // unknown, 2g, 3g, 4g, wifi
  quality: 'medium',     // poor, medium, good (pour harmoniser avec le reste du code)
  saveData: false,
  lastCheck: 0,
  failedRequests: 0      // Compteur d'erreurs réseau pour détection proactive
};

// Variables locales pour un accès plus rapide (les originales restent dans le namespace)
let connectionType = self.LIFE_TRAVEL.connection.type;
let connectionQuality = self.LIFE_TRAVEL.connection.quality;
let saveDataMode = self.LIFE_TRAVEL.connection.saveData;
let lastConnectivityCheck = self.LIFE_TRAVEL.connection.lastCheck;
let failedNetworkRequests = self.LIFE_TRAVEL.connection.failedRequests;

/**
 * Fetch sécurisé avec timeout et gestion d'erreur améliorée pour le contexte camerounais
 * @param {Request|string} request La requête à exécuter
 * @param {Object} options Options de fetch et timeout
 * @returns {Promise<Response>} La réponse ou l'erreur gérée
 */
function safeFetch(request, options = {}) {
  const timeout = options.timeout || self.LIFE_TRAVEL.CONFIG.networkTimeout;
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeout);
  
  // Ajouter le signal à la requête
  const fetchOptions = { ...options, signal: controller.signal };
  
  return fetch(request, fetchOptions)
    .then(response => {
      clearTimeout(timeoutId);
      // Réinitialiser le compteur d'erreurs si la requête réussit
      failedNetworkRequests = 0;
      self.LIFE_TRAVEL.connection.failedRequests = 0;
      return response;
    })
    .catch(error => {
      clearTimeout(timeoutId);
      
      // Incrémenter le compteur d'erreurs réseau
      failedNetworkRequests++;
      self.LIFE_TRAVEL.connection.failedRequests = failedNetworkRequests;
      
      // Si plus de 3 échecs consécutifs, on considère que la connexion est instable
      if (failedNetworkRequests >= 3) {
        // Forcer la mise à jour de l'état de connexion
        connectionQuality = 'poor';
        self.LIFE_TRAVEL.connection.quality = 'poor';
      }
      
      console.error(`Fetch error (${connectionQuality}):`, error.message || 'Network error');
      
      // Catégoriser les erreurs pour une meilleure gestion
      let errorType = 'generic';
      if (error.name === 'AbortError') {
        errorType = 'timeout';
      } else if (error.name === 'TypeError') {
        errorType = 'network';
      }
      
      // Enrichir l'erreur avec des métadonnées utiles pour le débogage
      const enhancedError = new Error(`Network error: ${error.message || 'Unknown'} (${connectionQuality})`);
      enhancedError.originalError = error;
      enhancedError.type = errorType;
      enhancedError.connectionQuality = connectionQuality;
      enhancedError.timestamp = Date.now();
      
      // Lancer l'erreur enrichie
      throw enhancedError;
    });
}

/**
 * Détermine le type de contenu d'une requête
 * @param {Request} request La requête à analyser
 * @returns {string} Le type de contenu
 */
function getContentType(request) {
  const url = new URL(request.url);
  const extension = url.pathname.split('.').pop();
  
  if (/\.(jpe?g|png|gif|webp|svg)$/i.test(url.pathname)) {
    return 'image';
  } else if (/\.(woff2?|ttf|otf|eot)$/i.test(url.pathname)) {
    return 'font';
  } else if (/\.(js)$/i.test(url.pathname)) {
    return 'script';
  } else if (/\.(css)$/i.test(url.pathname)) {
    return 'style';
  } else if (/\.(html|php)$/i.test(url.pathname)) {
    return 'document';
  } else if (/\.(mp4|webm|mov)$/i.test(url.pathname)) {
    return 'video';
  } else if (/\.(mp3|wav|ogg)$/i.test(url.pathname)) {
    return 'audio';
  }
  return 'other';
}

/**
 * Détermine la stratégie de cache appropriée selon le type de contenu et la connexion
 * @param {Request} request La requête à analyser
 * @returns {string} La stratégie de cache à utiliser
 */
function getCacheStrategy(request) {
  const contentType = getContentType(request);
  const url = new URL(request.url);
  
  // Ne pas intercepter les points d'extrémité d'API
  if (url.pathname.includes('/wp-json/') || url.pathname.includes('/admin-ajax.php')) {
    return 'network-only';
  }
  
  // Mode d'économie de données (saveData mode)
  if (saveDataMode) {
    if (CAMEROON_CONFIG.lowPriorityContent.includes(contentType)) {
      return 'cache-only'; // Empêcher les vidéos de charger sur les connexions limitées
    }
    return 'cache-first'; // Priorité au cache pour tout le reste en mode économie
  }
  
  // Pour les connexions lentes (typique dans certaines zones du Cameroun)
  if (connectionQuality === 'slow') {
    if (CAMEROON_CONFIG.aggressivelyCacheContent.includes(contentType)) {
      return 'stale-while-revalidate'; // Utiliser le cache immédiatement et mettre à jour en arrière-plan
    }
    return 'cache-first';
  }
  
  // Pour les connexions moyennes (cas le plus fréquent au Cameroun)
  if (connectionQuality === 'medium') {
    if (contentType === 'document') {
      return 'network-first'; // Pages HTML toujours depuis le réseau si possible
    }
    if (contentType === 'image' || contentType === 'style') {
      return 'stale-while-revalidate'; // Afficher rapidement puis mettre à jour
    }
    return 'network-first-with-timeout'; // Réseau avec timeout adapté au contexte camerounais
  }
  
  // Pour les bonnes connexions (rare au Cameroun, mais possible dans les grandes villes)
  return 'network-first';
}

// Interception des requêtes fetch
self.addEventListener('fetch', event => {
  // Ignorer les requêtes non GET ou qui ne commencent pas par http
  if (event.request.method !== 'GET' || 
      !event.request.url.startsWith('http')) {
    return;
  }
  
  // Déterminer la stratégie de cache
  const strategy = getCacheStrategy(event.request);
  
  // Appliquer la stratégie appropriée
  switch (strategy) {
    case 'cache-only':
      event.respondWith(
        caches.match(event.request)
          .then(cachedResponse => {
            return cachedResponse || caches.match(OFFLINE_URL);
          })
      );
      break;
      
    case 'cache-first':
      event.respondWith(
        caches.match(event.request)
          .then(cachedResponse => {
            if (cachedResponse) {
              return cachedResponse;
            }
            
            return fetch(event.request)
              .then(response => {
                // Mettre en cache si la réponse est valide
                if (response && response.status === 200) {
                  const responseClone = response.clone();
                  caches.open(CACHE_NAME).then(cache => {
                    cache.put(event.request, responseClone);
                  });
                }
                return response;
              })
              .catch(() => {
                // Rediriger vers la page hors-ligne pour les pages HTML
                if (getContentType(event.request) === 'document') {
                  return caches.match(OFFLINE_URL);
                }
                
                // Pour les images, utiliser un placeholder
                if (getContentType(event.request) === 'image') {
                  return caches.match(PLUGIN_BASE + 'assets/img/offline-placeholder.svg');
                }
                
                // Pour les autres ressources, échec standard
                return new Response('Contenu non disponible hors-ligne', {
                  status: 503,
                  statusText: 'Service Unavailable',
                  headers: new Headers({
                    'Content-Type': 'text/plain',
                    'Cache-Control': 'no-store'
                  })
                });
              });
          })
      );
      break;
      
    case 'network-first':
      event.respondWith(
        (async () => {
          // Déterminer le timeout en fonction du type de contenu et de l'état du réseau
          let timeout = CAMEROON_CONFIG.networkTimeout;
          const contentType = getContentType(event.request);
          
          // Ajuster le timeout en fonction du type de contenu et de la qualité de connexion
          if (CAMEROON_CONFIG.highPriorityContent.includes(contentType)) {
            timeout = connectionQuality === 'poor' ? 
              CAMEROON_CONFIG.longNetworkTimeout : CAMEROON_CONFIG.networkTimeout;
          } else if (CAMEROON_CONFIG.lowPriorityContent.includes(contentType)) {
            timeout = CAMEROON_CONFIG.shortNetworkTimeout;
          }
          
          // Vérification rapide du cache avant de faire un fetch
          const cachedResponse = await caches.match(event.request);
          
          try {
            // Si la connexion est clairement mauvaise, utiliser le cache directement si disponible
            if (connectionQuality === 'poor' && failedNetworkRequests >= 3 && cachedResponse) {
              console.log('Using cache directly due to poor connection:', event.request.url);
              return cachedResponse;
            }
            
            // Tenter la requête réseau avec timeout
            const networkResponse = await safeFetch(event.request, { timeout });
            
            // Mettre en cache la réponse réseau si elle est valide
            if (networkResponse && networkResponse.status === 200) {
              const responseClone = networkResponse.clone();
              const cache = await caches.open(CACHE_NAME);
              cache.put(event.request, responseClone).catch(err => {
                console.warn('Cache update failed:', err);
              });
            }
            
            return networkResponse;
          } catch (error) {
            console.warn(`Network request failed (${connectionQuality}):`, error.message);
            
            // Utiliser le cache si disponible
            if (cachedResponse) {
              return cachedResponse;
            }
            
            // Pour les requêtes de navigation, rediriger vers la page offline
            if (event.request.mode === 'navigate') {
              return caches.match(OFFLINE_URL);
            }
            
            // Pour les images, utiliser un placeholder
            if (contentType === 'image') {
              return caches.match(PLUGIN_BASE + 'assets/img/offline-placeholder.svg');
            }
            
            // Pour les autres ressources, renvoyer une réponse d'erreur claire
            return new Response(
              `Contenu indisponible: ${error.message || 'Connexion perdue'}`, {
              status: 503,
              statusText: 'Service indisponible - connexion instable',
              headers: new Headers({
                'Content-Type': 'text/plain',
                'Cache-Control': 'no-store'
              })
            });
          }
        })()
      );
      break;
      
    case 'network-first-with-timeout':
      event.respondWith(
        (async () => {
          // Déterminer le timeout optimal basé sur le contexte Camerounais et type de contenu
          const contentType = getContentType(event.request);
          
          // Définir des timeouts adaptés aux conditions spécifiques camerounaises
          let timeout = self.LIFE_TRAVEL.connection.quality === 'poor' ?
            self.LIFE_TRAVEL.CONFIG.longNetworkTimeout : // 10s pour les connexions faibles (zones rurales)
            (self.LIFE_TRAVEL.connection.quality === 'medium' ? 
              self.LIFE_TRAVEL.CONFIG.networkTimeout : // 5s pour les connexions moyennes (villes moyennes)
              self.LIFE_TRAVEL.CONFIG.shortNetworkTimeout); // 3s pour les bonnes connexions (grandes villes)
          
          // Priorité pour les URLs critiques (adapté aux opérateurs camerounais)
          const url = new URL(event.request.url);
          if (url.hostname.includes('mtn.cm') || url.hostname.includes('orange.cm') ||
              url.hostname.includes('camtel.cm')) {
            // Adapter le timeout pour les sites d'opérateurs locaux qui sont souvent lents
            timeout = self.LIFE_TRAVEL.CONFIG.longNetworkTimeout;
          }
          
          // Vérifier le cache d'abord pour une réponse plus rapide
          const cachedResponse = await caches.match(event.request);
          
          // Démarrer un compteur pour mesurer le temps de réponse réseau
          const startTime = Date.now();
          
          try {
            // Système de retry avec backoff exponentiel pour les ressources critiques
            const maxRetries = self.LIFE_TRAVEL.CONFIG.highPriorityContent.includes(contentType) ? 
                            self.LIFE_TRAVEL.CONFIG.maxRetries : 1;
            let retries = 0;
            let response = null;
            
            while (retries <= maxRetries) {
              try {
                // Utiliser safeFetch avec timeout adapté
                response = await safeFetch(event.request, { 
                  timeout: timeout * Math.pow(1.5, retries) // Augmentation exponentielle du timeout
                });
                
                // Vérifier si la réponse est valide
                if (!response || !response.ok) {
                  console.warn(`Réponse réseau non valide (${response ? response.status : 'null'})`);
                  
                  // Si c'est une erreur 4xx, ne pas réessayer (problème client)
                  if (response && response.status >= 400 && response.status < 500) {
                    // Retourner la réponse d'erreur telle quelle
                    break;
                  }
                  
                  // Pour les erreurs 5xx ou autres, continuer avec les réessais
                  throw new Error(`Erreur réseau: ${response ? response.status : 'non valide'}`);
                }
                
                // Si on arrive ici, la requête a réussi
                break;
              } catch (e) {
                retries++;
                
                // Log détaillé pour le débogage
                console.warn(`Tentative ${retries}/${maxRetries} échouée:`, e.message || 'Erreur inconnue');
                
                // Si c'est une erreur de timeout, augmenter le timeout plus agressivement
                if (e.type === 'timeout') {
                  timeout = timeout * 2;
                  console.info(`Timeout augmenté à ${timeout}ms pour la prochaine tentative`);
                }
                
                // Si on a atteint le nombre max de tentatives, lancer l'erreur
                if (retries > maxRetries) {
                  throw e;
                }
                
                // Attendre avant de réessayer (backoff exponentiel)
                const backoffDelay = self.LIFE_TRAVEL.CONFIG.retryDelay * Math.pow(2, retries - 1);
                console.info(`Attente de ${backoffDelay}ms avant nouvelle tentative...`);
                await new Promise(resolve => setTimeout(resolve, backoffDelay));
              }
            }
            
            // Mise à jour de la qualité de connexion basée sur le temps de réponse
            const responseTime = Date.now() - startTime;
            let newQuality = connectionQuality;
            
            if (responseTime > self.LIFE_TRAVEL.CONFIG.longNetworkTimeout) {
              newQuality = 'poor';
            } else if (responseTime > self.LIFE_TRAVEL.CONFIG.networkTimeout) {
              newQuality = 'medium';
            } else if (responseTime < self.LIFE_TRAVEL.CONFIG.shortNetworkTimeout) {
              newQuality = 'good';
            }
            
            // Mettre à jour les variables globales et locales
            if (newQuality !== connectionQuality) {
              connectionQuality = newQuality;
              self.LIFE_TRAVEL.connection.quality = newQuality;
              console.info(`Qualité de connexion mise à jour: ${newQuality} (temps de réponse: ${responseTime}ms)`);
            }
            
            // Mettre en cache si la réponse est valide
            if (response && response.status === 200) {
              try {
                const responseClone = response.clone();
                const cache = await caches.open(CACHE_NAME);
                
                // Vérification de taille pour éviter de saturer le cache sur appareils limités
                const contentSize = parseInt(response.headers.get('content-length') || '0');
                const maxSize = self.LIFE_TRAVEL.CONFIG.maxCacheSize[contentType] || 1024 * 1024; // 1MB par défaut
                
                if (contentSize === 0 || contentSize < maxSize) {
                  await cache.put(event.request, responseClone);
                  console.info(`Ressource mise en cache: ${event.request.url} (${contentSize} octets)`);
                } else {
                  console.warn(`Ressource trop volumineuse pour le cache: ${contentSize} octets > ${maxSize} octets`);
                }
              } catch (cacheError) {
                console.error('Erreur lors de la mise en cache:', cacheError);
                // Ne pas bloquer la réponse même si la mise en cache échoue
              }
            }
            
            // Retourner la réponse réseau si disponible, sinon le cache
            return response || cachedResponse || new Response(
              'Contenu non disponible', {
                status: 503,
                statusText: 'Service indisponible',
                headers: new Headers({ 'Content-Type': 'text/plain' })
              }
            );
          } catch (error) {
            // Log détaillé de l'erreur finale après toutes les tentatives
            console.warn(`Échec après plusieurs tentatives (${connectionQuality}):`, error);
            self.LIFE_TRAVEL.connection.failedRequests++;
            failedNetworkRequests = self.LIFE_TRAVEL.connection.failedRequests;
            
            // Si trop d'échecs consécutifs, dégrader la qualité de connexion
            if (failedNetworkRequests >= 3 && connectionQuality !== 'poor') {
              connectionQuality = 'poor';
              self.LIFE_TRAVEL.connection.quality = 'poor';
              console.warn('Connexion dégradée à "poor" après échecs consécutifs');
            }
            
            // Si nous avons une réponse en cache, l'utiliser
            if (cachedResponse) {
              console.info(`Utilisation du cache pour: ${event.request.url}`);
              return cachedResponse;
            }
            
            // Redirection vers offline pour les pages
            if (event.request.mode === 'navigate') {
              console.info(`Redirection vers page offline pour: ${event.request.url}`);
              return caches.match(OFFLINE_URL);
            }
            
            // Placeholder pour les images
            if (contentType === 'image') {
              console.info(`Utilisation du placeholder pour image: ${event.request.url}`);
              return caches.match(PLUGIN_BASE + 'assets/img/offline-placeholder.svg')
                .catch(() => new Response('Image non disponible', { status: 503 }));
            }
            
            // Réponse par défaut adaptée au contexte camerounais
            return new Response(
              `Contenu non disponible - Problème de connexion internet. ${error.message || ''}`, {
              status: 503,
              statusText: 'Connexion internet instable - Veuillez réessayer',
              headers: new Headers({
                'Content-Type': 'text/plain',
                'Cache-Control': 'no-store',
                'X-Connection-Quality': connectionQuality
              })
            });
          }
        })()
      );
      break;
      
    case 'stale-while-revalidate':
      event.respondWith(
        (async () => {
          // Récupérer la réponse du cache immédiatement (pour affichage rapide)
          const cachedResponse = await caches.match(event.request);
          
          // Type de contenu pour adapter la stratégie
          const contentType = getContentType(event.request);

          // L'approche stale-while-revalidate: renvoyer le cache immédiatement si disponible
          // tout en lançant la mise à jour en arrière-plan
          const backgroundUpdatePromise = (async () => {
            try {
              // Déterminer le timeout approprié selon le type de contenu et la priorité
              let timeout = self.LIFE_TRAVEL.CONFIG.networkTimeout; // Valeur par défaut
              
              if (self.LIFE_TRAVEL.CONFIG.highPriorityContent.includes(contentType)) {
                timeout = self.LIFE_TRAVEL.CONFIG.longNetworkTimeout; // Plus long pour le contenu important
              } else if (self.LIFE_TRAVEL.CONFIG.lowPriorityContent.includes(contentType)) {
                timeout = self.LIFE_TRAVEL.CONFIG.shortNetworkTimeout; // Court pour le contenu moins important
              }
              
              // Tentative de récupération réseau avec safeFetch (incluant gestion des timeouts)
              const networkResponse = await safeFetch(event.request, { timeout });
              
              // Mise à jour du cache si la réponse est valide
              if (networkResponse && networkResponse.status === 200) {
                // Cloner la réponse avant de la mettre en cache
                const responseClone = networkResponse.clone();
                
                // Vérifier la taille avant de mettre en cache
                const contentSize = parseInt(networkResponse.headers.get('content-length') || '0');
                const maxSize = self.LIFE_TRAVEL.CONFIG.maxCacheSize[contentType] || 1024 * 1024; // 1MB par défaut
                
                if (contentSize === 0 || contentSize < maxSize) {
                  const cache = await caches.open(CACHE_NAME);
                  await cache.put(event.request, responseClone);
                  console.info(`SWR: Mise à jour réussie du cache pour ${event.request.url}`);
                  
                  // Réinitialiser le compteur d'échecs réseau après une mise à jour réussie
                  self.LIFE_TRAVEL.connection.failedRequests = 0;
                  failedNetworkRequests = 0;
                  
                  // Notifier le client si c'est une requête de navigation
                  if (event.request.mode === 'navigate') {
                    const clients = await self.clients.matchAll({type: 'window'});
                    for (const client of clients) {
                      client.postMessage({
                        type: 'CACHE_UPDATED',
                        url: event.request.url
                      });
                    }
                  }
                } else {
                  console.warn(`SWR: Ressource trop volumineuse pour cache: ${contentSize} octets > ${maxSize} octets`);
                }
              }
              
              return networkResponse;
            } catch (error) {
              // Gestion des erreurs de mise à jour en arrière-plan
              console.warn(`SWR: Erreur lors de la mise à jour en arrière-plan:`, error);
              console.info(`SWR: Mise à jour échouée pour: ${event.request.url}`);
              throw error; // Propager l'erreur pour la gestion finale
            }
          })();

          // Renvoyer la réponse du cache immédiatement si disponible
          if (cachedResponse) {
            // Déclencher la mise à jour en arrière-plan sans attendre
            // On utilise catch pour éviter que les erreurs de la mise à jour n'impactent l'expérience utilisateur
            backgroundUpdatePromise.catch(error => {
              console.warn(`SWR: Erreur ignorée dans la mise à jour en arrière-plan:`, error);
            });
            
            return cachedResponse;
          }
          
          // Si rien n'est en cache, on doit attendre la réponse réseau
          try {
            const networkResponse = await backgroundUpdatePromise;
            return networkResponse;
          } catch (error) {
            // Cas d'échec complet: rien en cache, erreur réseau
            console.error(`SWR: Échec complet (ni cache, ni réseau):`, error);
            
            // Réponse adaptée au type de contenu
            if (contentType === 'document') {
              return caches.match(OFFLINE_URL);
            } else if (contentType === 'image') {
              return caches.match(PLUGIN_BASE + 'assets/img/offline-placeholder.svg')
                .catch(() => new Response('Image non disponible', { status: 503 }));
            }
            
            return new Response(
              `Contenu non disponible - Problème de connexion internet.`, {
              status: 503,
              statusText: 'Service indisponible',
              headers: new Headers({
                'Content-Type': 'text/plain',
                'Cache-Control': 'no-store'
              })
            });
          }
        })()
      );
      break;
      
    case 'network-only':
      event.respondWith(
        (async () => {
          // Type de contenu pour adapter le timeout
          const contentType = getContentType(event.request);
          
          // Déterminer le timeout approprié
          let timeout = self.LIFE_TRAVEL.CONFIG.networkTimeout;
          
          // Adapter le timeout au type de contenu (important pour le contexte camerounais)
          if (self.LIFE_TRAVEL.CONFIG.highPriorityContent.includes(contentType)) {
            timeout = self.LIFE_TRAVEL.CONFIG.longNetworkTimeout;
          }
          
          try {
            // Tentative de récupération réseau avec safeFetch (incluant gestion avancée des timeouts)
            const response = await safeFetch(event.request, { timeout });
            return response;
          } catch (error) {
            // Pour les navigations uniquement, rediriger vers la page offline
            if (event.request.mode === 'navigate') {
              console.warn(`Navigation échouée, redirection vers la page offline: ${event.request.url}`);
              return caches.match(OFFLINE_URL);
            }
            
            // Réponse d'erreur adaptée au contexte avec message explicite pour les utilisateurs camerounais
            console.error(`Échec réseau pour: ${event.request.url}, erreur: ${error.message || 'inconnue'}`);
            
            return new Response(
              `Contenu temporairement indisponible. Vérifiez votre connexion et réessayez plus tard.`, {
              status: 503,
              statusText: 'Service indisponible - Réseau instable',
              headers: new Headers({
                'Content-Type': 'text/plain',
                'Cache-Control': 'no-store',
                'Retry-After': '60'
              })
            });
          }
        })()
      );
      break;
      
    default:
      // Ne pas intercepter cette requête
      return;
  }
});

/**
 * Détermine le type de contenu d'une requête pour adapter la stratégie de mise en cache
 * 
 * @param {Request} request - Requête à analyser
 * @returns {string} - Type de contenu (document, image, script, style, font, json, api, other)
 */
function getContentType(request) {
  const url = request.url;
  
  // Déterminer par le mode de navigation (le plus sécuritaire)
  if (request.mode === 'navigate') {
    return 'document';
  }
  
  // Analyser les en-têtes Accept (seulement utile pour certaines requêtes)
  const accept = request.headers.get('Accept') || '';
  if (accept.includes('text/html')) return 'document';
  if (accept.includes('image/')) return 'image';
  if (accept.includes('text/css')) return 'style';
  if (accept.includes('application/javascript')) return 'script';
  if (accept.includes('font/')) return 'font';
  if (accept.includes('application/json')) return 'json';
  
  // Analyser l'URL si les en-têtes ne sont pas décisifs
  if (url.match(/\.(jpg|jpeg|png|gif|webp|svg|avif)($|\?)/i)) return 'image';
  if (url.match(/\.(css|scss)($|\?)/i)) return 'style';
  if (url.match(/\.(js|mjs)($|\?)/i)) return 'script';
  if (url.match(/\.(woff|woff2|ttf|otf|eot)($|\?)/i)) return 'font';
  if (url.match(/\.(json)($|\?)/i)) return 'json';
  if (url.match(/\.(html|htm|php)($|\?)/i)) return 'document';
  
  // Détecter les API par pattern d'URL (adapté aux API WordPress)
  if (url.includes('/wp-json/') || 
      url.includes('/api/') || 
      url.includes('/wp-admin/admin-ajax.php')) {
    return 'api';
  }
  
  // Défaut
  return 'other';
}

/**
 * Adapte les stratégies de cache et les timeouts en fonction 
 * des informations réseau détectées (adaptation au contexte camerounais)
 */
function updateNetworkInfo() {
  // Détecter le support de l'API Network Information
  const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  
  if (connection) {
    // Mettre à jour les propriétés de la connexion
    self.LIFE_TRAVEL.connection.type = connection.type || 'unknown';
    self.LIFE_TRAVEL.connection.effectiveType = connection.effectiveType || '3g';
    self.LIFE_TRAVEL.connection.downlink = connection.downlink || 1.0;
    self.LIFE_TRAVEL.connection.rtt = connection.rtt || 500;
    self.LIFE_TRAVEL.connection.saveData = !!connection.saveData;
    self.LIFE_TRAVEL.connection.lastUpdate = Date.now();
    
    // Adapter les timeouts en fonction de la qualité de la connexion
    // Particulier pour le contexte camerounais avec des réseaux instables et variables
    switch (connection.effectiveType) {
      case 'slow-2g':
      case '2g':
        // Conn. très lente: augmenter les timeouts, diminuer la taille max
        self.LIFE_TRAVEL.CONFIG.networkTimeout = 15000;
        self.LIFE_TRAVEL.CONFIG.longNetworkTimeout = 30000;
        self.LIFE_TRAVEL.CONFIG.shortNetworkTimeout = 8000;
        // Réduire la taille max pour 2G (pour économiser les données)
        Object.keys(self.LIFE_TRAVEL.CONFIG.maxCacheSize).forEach(key => {
          self.LIFE_TRAVEL.CONFIG.maxCacheSize[key] = Math.floor(self.LIFE_TRAVEL.CONFIG.maxCacheSize[key] * 0.5);
        });
        break;
      
      case '3g':
        // Conn. moyenne: timeouts modérés
        self.LIFE_TRAVEL.CONFIG.networkTimeout = 10000;
        self.LIFE_TRAVEL.CONFIG.longNetworkTimeout = 20000;
        self.LIFE_TRAVEL.CONFIG.shortNetworkTimeout = 6000;
        break;
      
      case '4g':
        // Conn. rapide: timeouts courts
        self.LIFE_TRAVEL.CONFIG.networkTimeout = 8000;
        self.LIFE_TRAVEL.CONFIG.longNetworkTimeout = 15000;
        self.LIFE_TRAVEL.CONFIG.shortNetworkTimeout = 4000;
        break;
      
      default:
        // Valeurs par défaut adaptées au contexte camerounais
        self.LIFE_TRAVEL.CONFIG.networkTimeout = 10000;
        self.LIFE_TRAVEL.CONFIG.longNetworkTimeout = 20000;
        self.LIFE_TRAVEL.CONFIG.shortNetworkTimeout = 5000;
    }
    
    // Mode économie de données
    if (connection.saveData) {
      console.info('Mode économie de données détecté, adaptation de la stratégie de cache');
      // Réduire agressivement la taille max pour économiser les données
      Object.keys(self.LIFE_TRAVEL.CONFIG.maxCacheSize).forEach(key => {
        self.LIFE_TRAVEL.CONFIG.maxCacheSize[key] = Math.floor(self.LIFE_TRAVEL.CONFIG.maxCacheSize[key] * 0.3);
      });
    }
  }
  
  // Vérification de l'état de la connexion par ping
  checkConnectivity()
    .then(isOnline => {
      self.LIFE_TRAVEL.connection.lastStatus = isOnline ? 'online' : 'offline';
      console.log(`État de la connexion: ${isOnline ? 'En ligne' : 'Hors ligne'}`);
    })
    .catch(() => {
      // En cas d'erreur, supposer hors ligne pour être prudent
      self.LIFE_TRAVEL.connection.lastStatus = 'offline';
    });
}

/**
 * Vérifie régulièrement la connectivité réelle via une requête de ping légère
 * Adapté au contexte camerounais où la connexion peut être très instable
 * 
 * @returns {Promise<boolean>} - True si en ligne, false sinon
 */
async function checkConnectivity() {
  try {
    // URL légère et résistante pour le test
    const testUrl = self.registration.scope + 'ping.txt?_=' + Date.now();
    
    // Requête avec timeout court et cache bust
    const response = await fetch(testUrl, {
      method: 'HEAD',
      cache: 'no-store',
      redirect: 'follow',
      // Ne pas inclure les cookies pour une requête plus légère
      credentials: 'omit',
      // Timeout court pour ne pas bloquer
      signal: AbortSignal.timeout(2000)
    });
    
    return response.ok;
  } catch (error) {
    return false;
  }
}

/**
 * Version améliorée de fetch avec gestion des timeouts, des retries, 
 * et adaptation aux conditions réseau camerounaises
 * 
 * @param {Request|string} resource - Ressource à récupérer
 * @param {Object} options - Options de la requête
 * @param {number} options.timeout - Timeout en ms
 * @param {number} options.retries - Nombre de tentatives max
 * @param {boolean} options.useBackoff - Utiliser le backoff exponentiel
 * @returns {Promise<Response>} - Réponse fetch
 */
async function safeFetch(resource, options = {}) {
  // Convertir l'URL en objet Request si nécessaire
  const request = resource instanceof Request ? resource : new Request(resource);
  
  // Paramètres par défaut adaptés au contexte camerounais
  const timeout = options.timeout || self.LIFE_TRAVEL.CONFIG.networkTimeout;
  const maxRetries = options.retries || 
    (self.LIFE_TRAVEL.connection.effectiveType === '2g' ? 3 : 2);
  const useBackoff = options.useBackoff !== undefined ? options.useBackoff : true;
  
  // Controller pour gérer le timeout
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeout);
  
  // Ajouter le signal à l'objet options pour fetch
  const fetchOptions = { ...options, signal: controller.signal };
  
  // Retry loop avec backoff exponentiel
  let retryCount = 0;
  let lastError;
  
  while (retryCount <= maxRetries) {
    try {
      // Si ce n'est pas la première tentative, appliquer un délai exponentiel
      if (retryCount > 0 && useBackoff) {
        const backoffConfig = self.LIFE_TRAVEL.CONFIG.backoff;
        const delay = Math.min(
          backoffConfig.initialDelay * Math.pow(backoffConfig.factor, retryCount - 1),
          backoffConfig.maxDelay
        );
        
        // Ajouter un facteur aléatoire (jitter) pour éviter les tempêtes de requêtes
        const jitterFactor = 1 - backoffConfig.jitter + (Math.random() * backoffConfig.jitter * 2);
        const finalDelay = delay * jitterFactor;
        
        await new Promise(resolve => setTimeout(resolve, finalDelay));
        console.info(`safeFetch: Tentative ${retryCount}/${maxRetries} après ${Math.round(finalDelay)}ms`);
      }
      
      // Nettoyage du timeout précédent si existant et création d'un nouveau
      clearTimeout(timeoutId);
      const newTimeoutId = setTimeout(() => controller.abort(), timeout);
      
      // Exécuter la requête
      const response = await fetch(request, fetchOptions);
      
      // Après succès, nettoyage
      clearTimeout(newTimeoutId);
      
      // En cas de succès, réinitialiser le compteur d'échecs
      // (seulement si la réponse est valide)
      if (response.ok) {
        self.LIFE_TRAVEL.connection.failedRequests = 0;
        return response;
      } else {
        // Réponse HTTP mais avec erreur (4xx, 5xx)
        lastError = new Error(`HTTP error ${response.status}`);
        lastError.response = response;
        lastError.type = 'http';
        throw lastError;
      }
    } catch (error) {
      lastError = error;
      
      // Observer et classifier le type d'erreur pour meilleure gestion
      if (error.name === 'AbortError') {
        lastError.type = 'timeout';
        console.warn(`safeFetch: Timeout après ${timeout}ms pour ${request.url}`);
      } else if (error.message && error.message.includes('fetch')) {
        lastError.type = 'network';
        console.warn(`safeFetch: Erreur réseau pour ${request.url}: ${error.message}`);
        
        // Incrémenter le compteur d'échecs réseau
        self.LIFE_TRAVEL.connection.failedRequests++;
        
        // Si trop d'échecs consécutifs, marquer comme hors ligne
        if (self.LIFE_TRAVEL.connection.failedRequests > 5) {
          self.LIFE_TRAVEL.connection.lastStatus = 'offline';
        }
      }
      
      // Continuer avec la prochaine tentative
      retryCount++;
    }
  }
  
  // Si nous atteignons ce point, c'est que toutes les tentatives ont échoué
  console.error(`safeFetch: Échec après ${maxRetries} tentatives pour ${request.url}`);
  
  // Enrichir l'erreur avec des informations utiles
  lastError.retriesAttempted = retryCount - 1;
  lastError.url = request.url;
  lastError.networkInfo = { ...self.LIFE_TRAVEL.connection };
  
  throw lastError;
}

// Décider si une réponse doit être mise en cache
function shouldCacheResponse(url) {
  // Ne pas mettre en cache les URLs d'admin, de paiement ou de tracking
  if (url.includes('/wp-admin/') || 
      url.includes('/checkout/') || 
      url.includes('/payment/') || 
      url.includes('/analytics/') ||
      url.includes('/admin/')) {
    return false;
  }
  
  // Ne pas mettre en cache les requêtes API sauf certaines spécifiques
  if (url.includes('/wp-json/') && 
      !url.includes('/wp-json/life-travel/v1/excursions')) {
    return false;
  }
  
  // Types de fichiers à mettre en cache par défaut
  const cacheableExtensions = [
    '.html', '.css', '.js', '.json', 
    '.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg', 
    '.woff', '.woff2', '.ttf', '.eot'
  ];
  
  // Vérifier si l'URL se termine par une extension à mettre en cache
  for (const ext of cacheableExtensions) {
    if (url.endsWith(ext)) {
      return true;
    }
  }
  
  // Par défaut, mettre en cache les requêtes pour les pages (sans extension)
  if (!url.split('/').pop().includes('.')) {
    return true;
  }
  
  // Ne pas mettre en cache par défaut
  return false;
}

// Écouteur de messages depuis les clients
self.addEventListener('message', event => {
  // Commande pour mettre à jour le cache
  if (event.data && event.data.command === 'updateCache') {
    console.log('Service Worker: Mise à jour du cache demandée');
    
    // URLs à mettre à jour
    const urlsToCache = event.data.urls || [];
    
    // Mettre à jour le cache avec les URLs fournies
    if (urlsToCache.length > 0) {
      caches.open(CACHE_NAME)
        .then(cache => {
          return Promise.all(
            urlsToCache.map(url => {
              return fetch(url)
                .then(response => {
                  if (response && response.status === 200 && shouldCacheResponse(url)) {
                    return cache.put(url, response);
                  }
                })
                .catch(error => {
                  console.error('Service Worker: Erreur lors de la mise à jour du cache pour:', url, error);
                });
            })
          );
        })
        .then(() => {
          // Notifier le client que la mise à jour est terminée
          if (event.source) {
            event.source.postMessage({
              command: 'updateComplete',
              success: true
            });
          }
        })
        .catch(error => {
          console.error('Service Worker: Erreur lors de la mise à jour du cache', error);
          if (event.source) {
            event.source.postMessage({
              command: 'updateComplete',
              success: false,
              error: error.message
            });
          }
        });
    }
  }
  
  // Commande pour vider le cache
  if (event.data && event.data.command === 'clearCache') {
    console.log('Service Worker: Nettoyage du cache demandé');
    
    caches.delete(CACHE_NAME)
      .then(success => {
        if (event.source) {
          event.source.postMessage({
            command: 'clearComplete',
            success: success
          });
        }
        
        // Recréer le cache avec les ressources essentielles
        return caches.open(CACHE_NAME)
          .then(cache => {
            return cache.addAll(CORE_ASSETS);
          });
      })
      .catch(error => {
        console.error('Service Worker: Erreur lors du nettoyage du cache', error);
        if (event.source) {
          event.source.postMessage({
            command: 'clearComplete',
            success: false,
            error: error.message
          });
        }
      });
  }
});

// Synchronisation en arrière-plan
self.addEventListener('sync', event => {
  if (event.tag === 'life-travel-sync') {
    event.waitUntil(
      // Récupérer les données à synchroniser depuis IndexedDB
      self.clients.matchAll().then(clients => {
        if (clients.length > 0) {
          // Informer tous les clients que la synchronisation commence
          clients.forEach(client => {
            client.postMessage({
              command: 'syncStarted'
            });
          });
          
          // Traiter la synchronisation ici (code non inclus pour simplifier)
          // Dans une implémentation réelle, ce serait la récupération depuis
          // IndexedDB et l'envoi vers le serveur
          
          // Simuler une synchronisation réussie pour l'exemple
          setTimeout(() => {
            clients.forEach(client => {
              client.postMessage({
                command: 'syncCompleted',
                success: true
              });
            });
          }, 1000);
        }
      })
    );
  }
});

// Intercepter les notifications push
self.addEventListener('push', event => {
  // Données de la notification
  const data = event.data.json();
  
  const options = {
    body: data.body || 'Notification Life Travel',
    icon: data.icon || '/assets/img/logo-notification.png',
    badge: data.badge || '/assets/img/badge.png',
    data: {
      url: data.url || '/'
    }
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title || 'Life Travel', options)
  );
});

// Gérer le clic sur une notification
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  event.waitUntil(
    clients.openWindow(event.notification.data.url)
  );
});
