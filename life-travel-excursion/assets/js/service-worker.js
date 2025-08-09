/**
 * Service Worker pour Life Travel Excursion
 * Permet de gérer les connexions intermittentes et d'améliorer l'expérience utilisateur
 * Optimisé pour les réseaux lents et mobiles
 * @version 2.3.4
 */

const CACHE_NAME = 'life-travel-cache-v2'; // Mise à jour de la version du cache
const OFFLINE_PAGE = '/offline.html';
const API_CACHE_NAME = 'life-travel-api-cache-v1';
const STATIC_CACHE_NAME = 'life-travel-static-cache-v1';

// Durée d'expiration du cache API en millisecondes (24 heures)
const API_CACHE_EXPIRATION = 24 * 60 * 60 * 1000;

// Stratégies de mise en cache
const STRATEGIES = {
  CACHE_FIRST: 'cache-first',       // Priorité au cache, puis réseau
  NETWORK_FIRST: 'network-first',   // Priorité au réseau, puis cache
  STALE_WHILE_REVALIDATE: 'stale-while-revalidate' // Renvoie cache immédiatement, puis met à jour en arrière-plan
};

// Configuration pour différents types de ressources
const RESOURCE_CONFIGS = [
  // Ressources statiques à mettre en cache immédiatement
  {
    pattern: /\.(?:css|js|woff2|woff|ttf|svg|png|jpg|jpeg|gif|webp|ico)$/i,
    strategy: STRATEGIES.CACHE_FIRST,
    cacheName: STATIC_CACHE_NAME,
    maxAgeSeconds: 30 * 24 * 60 * 60 // 30 jours
  },
  // API calls
  {
    pattern: /\/wp-json\/|\/admin-ajax\.php/i,
    strategy: STRATEGIES.STALE_WHILE_REVALIDATE,
    cacheName: API_CACHE_NAME,
    maxAgeSeconds: 24 * 60 * 60 // 24 heures
  }
];

// Ressources à mettre en cache immédiatement pour l'utilisation hors ligne
const INITIAL_CACHED_RESOURCES = [
  OFFLINE_PAGE,
  '/assets/css/life-travel-excursion.css',
  '/assets/js/life-travel-excursion-frontend.js',
  '/assets/js/price-calculator.js',
  '/assets/img/momo-icon.png',
  '/assets/img/card-icon.png'
];

// Installation du service worker
self.addEventListener('install', (event) => {
  event.waitUntil(
    Promise.all([
      // Création du cache principal
      caches.open(STATIC_CACHE_NAME).then((cache) => {
        console.log('Service worker installé - Mise en cache des ressources statiques');
        return cache.addAll(INITIAL_CACHED_RESOURCES);
      }),
      // Création du cache API
      caches.open(API_CACHE_NAME)
    ])
    .then(() => {
      // Skip waiting pour activer immédiatement le nouveau service worker
      return self.skipWaiting();
    })
    .catch(error => {
      console.error('Erreur lors de la mise en cache des ressources:', error);
    })
  );
});

// Activation du service worker
self.addEventListener('activate', (event) => {
  const currentCaches = [STATIC_CACHE_NAME, API_CACHE_NAME];
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            // Supprimer les caches qui ne font pas partie de nos caches actuels
            if (!currentCaches.includes(cacheName)) {
              console.log('Suppression de l\'ancien cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        // Prendre le contrôle de tous les clients ouverts
        return self.clients.claim();
      })
      .then(() => {
        // Nettoyer les entrées de cache expirées pour l'API
        return cleanExpiredCache();
      })
  );
});

/**
 * Nettoie les entrées de cache expirées
 */
async function cleanExpiredCache() {
  const cache = await caches.open(API_CACHE_NAME);
  const requests = await cache.keys();
  const now = Date.now();
  
  for (const request of requests) {
    // Récupérer la réponse et vérifier les métadonnées
    const response = await cache.match(request);
    if (response) {
      const responseClone = response.clone();
      const metadata = await responseClone.headers.get('sw-cache-metadata');
      
      if (metadata) {
        try {
          const parsed = JSON.parse(metadata);
          if (parsed.timestamp && (now - parsed.timestamp > API_CACHE_EXPIRATION)) {
            console.log('Suppression de l\'entrée de cache expirée:', request.url);
            await cache.delete(request);
          }
        } catch (e) {
          console.error('Erreur lors de l\'analyse des métadonnées de cache:', e);
        }
      }
    }
  }
}

// Interception des requêtes réseau avec stratégies adaptées
self.addEventListener('fetch', (event) => {
  // Ignorer les requêtes non GET
  if (event.request.method !== 'GET') {
    return;
  }
  
  // Déterminer la configuration applicable pour cette requête
  const url = new URL(event.request.url);
  const config = getResourceConfig(url);
  
  // Appliquer la stratégie de mise en cache appropriée
  switch (config.strategy) {
    case STRATEGIES.CACHE_FIRST:
      event.respondWith(handleCacheFirst(event.request, config));
      break;
    case STRATEGIES.NETWORK_FIRST:
      event.respondWith(handleNetworkFirst(event.request, config));
      break;
    case STRATEGIES.STALE_WHILE_REVALIDATE:
      event.respondWith(handleStaleWhileRevalidate(event.request, config));
      break;
    default:
      // Stratégie par défaut pour les navigations: réseau d'abord puis page hors ligne
      if (event.request.mode === 'navigate') {
        event.respondWith(handleNavigationRequest(event.request));
      }
  }
});

/**
 * Détermine la configuration applicable pour une URL
 */
function getResourceConfig(url) {
  // Vérifier les configurations spécifiques
  for (const config of RESOURCE_CONFIGS) {
    if (config.pattern.test(url.pathname)) {
      return config;
    }
  }
  
  // Configuration par défaut
  return {
    strategy: STRATEGIES.NETWORK_FIRST,
    cacheName: STATIC_CACHE_NAME,
    maxAgeSeconds: 24 * 60 * 60 // 24 heures
  };
}

/**
 * Gère les requêtes avec stratégie "Cache d'abord"
 */
async function handleCacheFirst(request, config) {
  const cache = await caches.open(config.cacheName);
  const cachedResponse = await cache.match(request);
  
  if (cachedResponse) {
    return cachedResponse;
  }
  
  try {
    const networkResponse = await fetch(request);
    
    // Mettre en cache si c'est une réponse valide
    if (networkResponse.ok) {
      // Clone la réponse pour la mise en cache
      const responseToCache = networkResponse.clone();
      cache.put(request, responseToCache);
    }
    
    return networkResponse;
  } catch (error) {
    // Gestion des erreurs réseau
    console.error('Erreur réseau pour ressource:', request.url, error);
    
    // Retourner une réponse d'erreur appropriée selon le type de fichier
    return handleErrorResponse(request);
  }
}

/**
 * Gère les requêtes avec stratégie "Réseau d'abord"
 */
async function handleNetworkFirst(request, config) {
  try {
    // Essayer le réseau d'abord avec un timeout
    const networkResponse = await fetchWithTimeout(request, 3000);
    
    // Mettre en cache la réponse fraîche
    const cache = await caches.open(config.cacheName);
    if (networkResponse.ok) {
      const responseToCache = networkResponse.clone();
      // Ajouter des métadonnées à la réponse mise en cache
      const headers = new Headers(responseToCache.headers);
      headers.append('sw-cache-metadata', JSON.stringify({
        timestamp: Date.now(),
        url: request.url
      }));
      
      const enhancedResponse = new Response(await responseToCache.blob(), {
        status: responseToCache.status,
        statusText: responseToCache.statusText,
        headers: headers
      });
      
      cache.put(request, enhancedResponse);
    }
    
    return networkResponse;
  } catch (error) {
    console.log('Échec du réseau, fallback vers cache:', request.url);
    
    // Fallback vers le cache
    const cache = await caches.open(config.cacheName);
    const cachedResponse = await cache.match(request);
    
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Aucune réponse en cache non plus
    return handleErrorResponse(request);
  }
}

/**
 * Gère les requêtes avec stratégie "Stale While Revalidate"
 */
async function handleStaleWhileRevalidate(request, config) {
  const cache = await caches.open(config.cacheName);
  const cachedResponse = await cache.match(request);
  
  // Lancer la mise à jour en arrière-plan, que nous ayons une réponse en cache ou non
  const fetchPromise = fetch(request).then(networkResponse => {
    if (networkResponse.ok) {
      // Ajouter des métadonnées à la réponse mise en cache
      const responseToCache = networkResponse.clone();
      const headers = new Headers(responseToCache.headers);
      headers.append('sw-cache-metadata', JSON.stringify({
        timestamp: Date.now(),
        url: request.url
      }));
      
      const enhancedResponse = new Response(responseToCache.body, {
        status: responseToCache.status,
        statusText: responseToCache.statusText,
        headers: headers
      });
      
      cache.put(request, enhancedResponse);
    }
    return networkResponse;
  }).catch(error => {
    console.error('Erreur réseau dans stale-while-revalidate:', error);
    return null;
  });
  
  // Si nous avons une réponse en cache, la renvoyer immédiatement
  if (cachedResponse) {
    return cachedResponse;
  }
  
  // Sinon, attendre la réponse réseau
  const networkResponse = await fetchPromise;
  if (networkResponse) {
    return networkResponse;
  }
  
  // Si le réseau échoue et nous n'avons pas de cache, retourner une erreur
  return handleErrorResponse(request);
}

/**
 * Gère les requêtes de navigation (HTML)
 */
async function handleNavigationRequest(request) {
  try {
    // Essayer le réseau pour les requêtes de navigation
    const networkResponse = await fetchWithTimeout(request, 5000);
    return networkResponse;
  } catch (error) {
    console.log('Échec de navigation, fallback vers page hors ligne', error);
    
    // Retourner la page hors ligne en cas d'échec
    return caches.match(OFFLINE_PAGE);
  }
}

/**
 * Wrapper autour de fetch avec un timeout
 */
function fetchWithTimeout(request, timeout) {
  return new Promise((resolve, reject) => {
    // Configurer le timeout
    const timeoutId = setTimeout(() => {
      reject(new Error('Request timeout'));
    }, timeout);
    
    fetch(request).then(
      (response) => {
        clearTimeout(timeoutId);
        resolve(response);
      },
      (err) => {
        clearTimeout(timeoutId);
        reject(err);
      }
    );
  });
}

/**
 * Gère les réponses d'erreur adaptées au type de contenu
 */
function handleErrorResponse(request) {
  const url = new URL(request.url);
  
  // Si c'est une navigation, retourner la page hors ligne
  if (request.mode === 'navigate') {
    return caches.match(OFFLINE_PAGE);
  }
  
  // Selon l'extension du fichier, retourner une réponse adaptée
  const ext = url.pathname.split('.').pop().toLowerCase();
  
  // Pour les images
  if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)) {
    // Retourner une image d'erreur ou un SVG léger pour les images
    return new Response(
      '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="150" viewBox="0 0 200 150">' +
      '<rect width="200" height="150" fill="#f5f5f5"/>' +
      '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="14">' +
      'Image non disponible' +
      '</text></svg>',
      {
        status: 503,
        statusText: 'Service Unavailable',
        headers: {
          'Content-Type': 'image/svg+xml',
          'Cache-Control': 'no-store'
        }
      }
    );
  }
  
  // Pour les API JSON
  if (ext === 'json' || url.pathname.includes('/wp-json/') || url.pathname.includes('/admin-ajax.php')) {
    return new Response(
      JSON.stringify({ error: 'Offline', message: 'You are currently offline' }),
      {
        status: 503,
        statusText: 'Service Unavailable',
        headers: {
          'Content-Type': 'application/json',
          'Cache-Control': 'no-store'
        }
      }
    );
  }
  
  // Pour tout le reste (CSS, JS, etc.)
  return new Response('', {
    status: 503,
    statusText: 'Service Unavailable',
    headers: {
      'Cache-Control': 'no-store'
    }
  });
}

// Gestion des messages du client
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

// Constantes de sécurité pour le stockage et la validation des données
const SECURITY_CONSTANTS = {
  // Durée de validité maximale d'un panier stocké localement (24 heures)
  MAX_CART_AGE_MS: 24 * 60 * 60 * 1000,
  // Détails sensibles à ne jamais stocker en clair
  SENSITIVE_FIELDS: ['card_number', 'cvv', 'password', 'address'],
  // Limite de taille maximale des données de panier pour prévenir les attaques de stockage
  MAX_CART_SIZE_BYTES: 100 * 1024, // 100 KB
  // Nombre maximal d'articles dans un panier
  MAX_CART_ITEMS: 50
};

// Stockage local des données de panier abandonnées avec sécurité renforcée
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-cart') {
    event.waitUntil(syncCart());
  } else if (event.tag === 'clean-expired-cart-data') {
    event.waitUntil(cleanExpiredCartData());
  }
});

/**
 * Fonction de validation approfondie des données du panier
 * @param {Object} cartData Les données du panier à valider
 * @returns {Object} Données validées et nettoyées ou null si invalides
 */
async function validateCartData(cartData) {
  try {
    // Vérifier si les données sont un objet valide
    if (!cartData || typeof cartData !== 'object') {
      console.error('Données de panier invalides: format non valide');
      return null;
    }
    
    // Vérifier les champs requis
    if (!cartData.email) {
      console.error('Données de panier invalides: email manquant');
      return null;
    }
    
    // Valider l'email avec une expression régulière
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!emailRegex.test(cartData.email)) {
      console.error('Données de panier invalides: format d\'email incorrect');
      return null;
    }
    
    // Vérifier la fraîcheur des données
    if (cartData.timestamp) {
      const now = Date.now();
      if (now - cartData.timestamp > SECURITY_CONSTANTS.MAX_CART_AGE_MS) {
        console.error('Données de panier expirées');
        return null;
      }
    } else {
      // Ajouter un timestamp s'il n'existe pas
      cartData.timestamp = Date.now();
    }
    
    // Sanitizer pour les données du panier (protection XSS de base)
    const sanitizeString = (str) => {
      if (typeof str !== 'string') return str;
      return str
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/`/g, '&#96;');
    };
    
    // Fonction récursive pour sanitizer tous les champs de type string
    const sanitizeRecursive = (obj) => {
      if (!obj || typeof obj !== 'object') return obj;
      
      const result = Array.isArray(obj) ? [] : {};
      
      for (const key in obj) {
        if (Object.prototype.hasOwnProperty.call(obj, key)) {
          // Exclure les champs sensibles
          if (SECURITY_CONSTANTS.SENSITIVE_FIELDS.includes(key)) {
            continue;
          }
          
          const value = obj[key];
          if (typeof value === 'string') {
            result[key] = sanitizeString(value);
          } else if (typeof value === 'object' && value !== null) {
            result[key] = sanitizeRecursive(value);
          } else {
            result[key] = value;
          }
        }
      }
      
      return result;
    };
    
    // Sanitizer les données du panier
    const sanitizedCart = sanitizeRecursive(cartData);
    
    // Vérifier la structure du panier
    if (sanitizedCart.cart_contents) {
      // Limiter le nombre d'articles pour prévenir les attaques par déni de service
      const cartItems = Object.keys(sanitizedCart.cart_contents);
      if (cartItems.length > SECURITY_CONSTANTS.MAX_CART_ITEMS) {
        console.error('Données de panier invalides: trop d\'articles');
        return null;
      }
      
      // Vérifier chaque article du panier
      for (const key of cartItems) {
        const item = sanitizedCart.cart_contents[key];
        // Vérifier les champs obligatoires de chaque article
        if (!item.product_id || typeof item.product_id !== 'number') {
          console.error('Article de panier invalide: ID de produit manquant ou incorrect');
          return null;
        }
        
        // Valider la quantité
        if (item.quantity) {
          item.quantity = parseInt(item.quantity, 10);
          if (isNaN(item.quantity) || item.quantity <= 0) {
            item.quantity = 1; // Valeur par défaut sécurisée
          }
        }
      }
    }
    
    // Vérifier la taille des données
    const dataSize = new Blob([JSON.stringify(sanitizedCart)]).size;
    if (dataSize > SECURITY_CONSTANTS.MAX_CART_SIZE_BYTES) {
      console.error('Données de panier trop volumineuses');
      return null;
    }
    
    return sanitizedCart;
  } catch (error) {
    console.error('Erreur lors de la validation des données du panier:', error);
    return null;
  }
}

/**
 * Fonction pour chiffrer légèrement les données sensibles du panier avant stockage
 * @param {Object} cartData Les données du panier à protéger
 * @returns {Object} Données avec certains champs sensibles obfusqués
 */
function protectSensitiveCartData(cartData) {
  try {
    // Fonction pour masquer partiellement une chaîne (ex: email)
    const obfuscateEmail = (email) => {
      if (!email || typeof email !== 'string') return email;
      const parts = email.split('@');
      if (parts.length !== 2) return email;
      
      const name = parts[0];
      const domain = parts[1];
      
      // Masquer une partie du nom d'utilisateur
      let maskedName;
      if (name.length <= 2) {
        maskedName = name;
      } else {
        maskedName = name.substring(0, Math.min(3, name.length)) + 
                    '*'.repeat(Math.max(name.length - 3, 0));
      }
      
      return `${maskedName}@${domain}`;
    };
    
    // Clone des données pour ne pas modifier l'original
    const protectedData = JSON.parse(JSON.stringify(cartData));
    
    // Masquer l'email
    if (protectedData.email) {
      // Garder une copie visible pour l'UI mais protégée pour le stockage
      protectedData._display_email = protectedData.email;
      protectedData.email = obfuscateEmail(protectedData.email);
    }
    
    // Ajouter un identifiant unique pour la session
    protectedData._secure_id = generateUniqueId();
    
    return protectedData;
  } catch (error) {
    console.error('Erreur lors de la protection des données sensibles:', error);
    return cartData; // Revenir aux données originales en cas d'erreur
  }
}

/**
 * Génère un identifiant unique pour le suivi des données
 */
function generateUniqueId() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}

/**
 * Nettoyer les données de panier expirées
 */
async function cleanExpiredCartData() {
  try {
    // Vérifier si nous avons des données de panier
    const cartData = await localforage.getItem('abandoned_cart');
    if (!cartData) return;
    
    // Vérifier la fraîcheur des données
    if (cartData.timestamp) {
      const now = Date.now();
      if (now - cartData.timestamp > SECURITY_CONSTANTS.MAX_CART_AGE_MS) {
        // Supprimer les données expirées
        await localforage.removeItem('abandoned_cart');
        await localforage.removeItem('cart_nonce');
        console.log('Données de panier expirées supprimées');
      }
    }
  } catch (error) {
    console.error('Erreur lors du nettoyage des données expirées:', error);
  }
}

/**
 * Fonction pour synchroniser les paniers abandonnés avec sécurité renforcée
 */
async function syncCart() {
  try {
    // 1. Récupération des données avec vérification de validité
    const rawCartData = await localforage.getItem('abandoned_cart');
    const cartNonce = await localforage.getItem('cart_nonce');
    
    if (!rawCartData) {
      console.log('Aucun panier à synchroniser');
      return;
    }
    
    // 2. Validation approfondie des données
    const validatedCartData = await validateCartData(rawCartData);
    if (!validatedCartData) {
      console.error('Impossible de synchroniser: données de panier invalides');
      // Supprimer les données invalides
      await localforage.removeItem('abandoned_cart');
      return false;
    }
    
    // 3. Vérification du nonce
    if (!cartNonce) {
      console.error('Impossible de synchroniser: nonce de sécurité manquant');
      return false;
    }
    
    // 4. Ajouter des en-têtes de sécurité renforcés
    const securityHeaders = {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache',
      'Expires': '0'
    };
    
    // 5. Générer un identifiant de requête unique pour prévenir les rejeux
    const requestId = generateUniqueId();
    
    // 6. Préparer les données avec l'identifiant de requête
    const formData = new URLSearchParams({
      action: 'life_travel_sync_abandoned_cart',
      cart_data: JSON.stringify(validatedCartData),
      security: cartNonce,
      request_id: requestId,
      client_timestamp: Date.now().toString()
    });
    
    // 7. Définir des règles de backoff intelligentes
    const maxRetries = 3;
    const baseDelay = 1000; // 1 seconde de base
    const maxDelay = 8000;  // 8 secondes maximum
    let lastError = null;
    
    // 8. Tentatives multiples avec backoff exponentiel et jitter
    for (let attempt = 0; attempt < maxRetries; attempt++) {
      try {
        // Envoyer les données au serveur avec un timeout raisonnable
        const response = await fetchWithTimeout('/wp-admin/admin-ajax.php', {
          method: 'POST',
          headers: securityHeaders,
          body: formData,
          credentials: 'same-origin' // Envoyer les cookies pour l'authentification
        }, 15000); // 15 secondes de timeout
        
        // Analyser la réponse avec gestion d'erreur
        const result = await response.json();
        
        if (response.ok && result.success) {
          // 9. Succès - suppression sécurisée des données locales
          await localforage.removeItem('abandoned_cart');
          await localforage.removeItem('cart_nonce');
          console.log('Panier abandonné synchronisé avec succès, identifiant:', 
                     result.cart_id || 'non spécifié');
          
          // 10. Notifier les clients ouverts avec informations sécurisées
          const clients = await self.clients.matchAll();
          clients.forEach(client => {
            client.postMessage({ 
              type: 'CART_SYNCED', 
              timestamp: Date.now(),
              requestId: requestId, // Pour vérification côté client
              cartId: result.cart_id || null
            });
          });
          
          return true;
        } else {
          // Gérer les réponses négatives du serveur
          const errorMessage = (result.error && typeof result.error === 'string') 
            ? result.error 
            : 'Réponse de serveur invalide';
          throw new Error(errorMessage);
        }
      } catch (fetchError) {
        lastError = fetchError;
        
        // Calculer le délai avant la prochaine tentative avec jitter
        const exponentialDelay = Math.min(maxDelay, baseDelay * Math.pow(2, attempt));
        const jitter = Math.random() * 0.3 * exponentialDelay; // 0-30% de jitter
        const backoffDelay = Math.floor(exponentialDelay + jitter);
        
        console.error(`Tentative ${attempt + 1}/${maxRetries} échouée, nouvelle tentative dans ${backoffDelay}ms`, fetchError);
        
        // Attendre avant de réessayer
        if (attempt < maxRetries - 1) {
          await new Promise(resolve => setTimeout(resolve, backoffDelay));
        }
      }
    }
    
    // 11. Toutes les tentatives ont échoué - enregistrer pour synchronisation future
    console.error('Échec de synchronisation après plusieurs tentatives:', lastError);
    
    // Si les données sont encore valides, les conserver pour une future synchronisation
    const currentTime = Date.now();
    if (validatedCartData.timestamp && 
        (currentTime - validatedCartData.timestamp <= SECURITY_CONSTANTS.MAX_CART_AGE_MS)) {
      console.log('Les données du panier seront conservées pour une synchronisation ultérieure');
      return false;
    } else {
      // Les données sont trop anciennes, les supprimer
      await localforage.removeItem('abandoned_cart');
      await localforage.removeItem('cart_nonce');
      console.log('Données de panier expirées supprimées après échec de synchronisation');
      return false;
    }
  } catch (error) {
    console.error('Erreur critique lors de la synchronisation du panier:', error);
    return false;
  }
}
