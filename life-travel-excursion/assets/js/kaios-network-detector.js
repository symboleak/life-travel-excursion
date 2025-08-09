/**
 * Life Travel - Détecteur de réseau optimisé pour KaiOS
 * 
 * Ce module est spécialement conçu pour les appareils KaiOS très populaires au Cameroun.
 * Il surveille la qualité du réseau et adapte l'expérience utilisateur en conséquence.
 * 
 * @package Life Travel Excursion
 * @version 2.4.0
 */

// Namespace pour éviter les collisions
window.LIFE_TRAVEL = window.LIFE_TRAVEL || {};
window.LIFE_TRAVEL.NETWORK = window.LIFE_TRAVEL.NETWORK || {};

(function() {
    // Vérifier si nous sommes sur un appareil KaiOS
    var isKaiOS = navigator.userAgent.indexOf('KAIOS') > -1;
    
    if (!isKaiOS) {
        // Ce script est spécifique aux appareils KaiOS, sortir si ce n'est pas le cas
        return;
    }
    
    // Configuration du détecteur réseau
    var config = {
        // Fréquence de vérification du réseau (en millisecondes)
        checkFrequency: 30000, // 30 secondes
        
        // Seuils de qualité réseau pour les réseaux camerounais
        thresholds: {
            good: 2000,    // moins de 2 secondes pour un ping
            medium: 5000,  // moins de 5 secondes
            poor: 10000    // plus de 10 secondes considéré très lent
        },
        
        // URLs de test pour vérifier la connectivité
        testUrls: [
            '/wp-admin/admin-ajax.php?action=life_travel_network_info',
            'https://life-travel.cm/api/ping'
        ],
        
        // Options pour économie de batterie
        saveBattery: true  // optimisation pour la batterie des téléphones KaiOS
    };
    
    // État actuel du réseau
    var state = {
        lastCheck: 0,
        quality: 'unknown',
        isOnline: navigator.onLine,
        pingTimes: [],
        failedAttempts: 0
    };
    
    /**
     * Vérifie la qualité du réseau
     */
    function checkNetworkQuality() {
        // Ne pas vérifier trop fréquemment pour économiser la batterie
        var now = Date.now();
        if (now - state.lastCheck < config.checkFrequency) {
            return Promise.resolve(state.quality);
        }
        
        state.lastCheck = now;
        
        // Si nous ne sommes pas en ligne selon l'API, inutile de tester
        if (!navigator.onLine) {
            state.isOnline = false;
            state.quality = 'offline';
            updateNetworkUI();
            return Promise.resolve('offline');
        }
        
        // Utiliser l'API Network Information si disponible (plus précis)
        if (navigator.connection) {
            var conn = navigator.connection;
            
            // Mise à jour des informations réseau
            if (conn.saveData) {
                document.body.classList.add('lt-save-data');
            }
            
            // Adapter la qualité en fonction du type de connexion
            if (conn.effectiveType) {
                switch (conn.effectiveType) {
                    case '4g':
                        state.quality = conn.saveData ? 'medium' : 'good';
                        break;
                    case '3g':
                        state.quality = 'medium';
                        break;
                    case '2g':
                    case 'slow-2g':
                        state.quality = 'poor';
                        break;
                    default:
                        // Conserver la qualité précédente ou tester
                        break;
                }
            }
        }
        
        // Faire un ping pour vérifier la réactivité réelle du réseau
        return testNetworkSpeed()
            .then(function(quality) {
                state.quality = quality;
                state.isOnline = true;
                state.failedAttempts = 0;
                updateNetworkUI();
                return quality;
            })
            .catch(function(error) {
                state.failedAttempts++;
                console.warn('Life Travel: Échec du test réseau KaiOS:', error.message || 'Erreur inconnue');
                
                // Après plusieurs échecs, considérer hors-ligne
                if (state.failedAttempts > 2) {
                    state.isOnline = false;
                    state.quality = 'offline';
                    updateNetworkUI();
                }
                
                return 'unknown';
            });
    }
    
    /**
     * Teste la vitesse du réseau par une requête ping
     */
    function testNetworkSpeed() {
        return new Promise(function(resolve, reject) {
            var testUrl = config.testUrls[0] + '&_=' + Date.now(); // Éviter le cache
            var startTime = Date.now();
            var timeoutId = null;
            
            // Timeout pour les connexions très lentes
            timeoutId = setTimeout(function() {
                state.quality = 'poor';
                reject(new Error('Timeout du test réseau'));
            }, config.thresholds.poor + 1000);
            
            // Effectuer la requête avec fetch ou un fallback
            var fetchPromise = window.fetch ?
                fetch(testUrl, { method: 'GET', cache: 'no-store' }) :
                ajaxFallback(testUrl);
            
            fetchPromise
                .then(function() {
                    clearTimeout(timeoutId);
                    var pingTime = Date.now() - startTime;
                    
                    // Stocker les temps pour moyenne glissante
                    state.pingTimes.push(pingTime);
                    if (state.pingTimes.length > 3) {
                        state.pingTimes.shift();
                    }
                    
                    // Calculer la moyenne des derniers pings
                    var avgPing = state.pingTimes.reduce(function(sum, time) {
                        return sum + time;
                    }, 0) / state.pingTimes.length;
                    
                    // Déterminer la qualité
                    var quality;
                    if (avgPing < config.thresholds.good) {
                        quality = 'good';
                    } else if (avgPing < config.thresholds.medium) {
                        quality = 'medium';
                    } else {
                        quality = 'poor';
                    }
                    
                    resolve(quality);
                })
                .catch(function(error) {
                    clearTimeout(timeoutId);
                    reject(error);
                });
        });
    }
    
    /**
     * Fallback pour les appareils sans fetch
     */
    function ajaxFallback(url) {
        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve();
                } else {
                    reject(new Error('Status: ' + xhr.status));
                }
            };
            
            xhr.onerror = function() {
                reject(new Error('Erreur réseau'));
            };
            
            xhr.send();
        });
    }
    
    /**
     * Met à jour l'interface utilisateur selon l'état du réseau
     */
    function updateNetworkUI() {
        // Mettre à jour les classes CSS
        document.body.classList.remove('lt-network-good', 'lt-network-medium', 'lt-network-poor', 'lt-network-offline');
        
        if (state.isOnline) {
            document.body.classList.add('lt-online');
            document.body.classList.remove('lt-offline');
            document.body.classList.add('lt-network-' + state.quality);
        } else {
            document.body.classList.add('lt-offline');
            document.body.classList.remove('lt-online');
        }
        
        // Mettre à jour l'indicateur de connexion si présent
        var indicator = document.querySelector('.lt-connection-status');
        if (indicator) {
            indicator.className = 'lt-connection-status ' + (state.isOnline ? 'online' : 'offline');
            if (state.quality && state.isOnline) {
                indicator.setAttribute('data-quality', state.quality);
            }
        }
        
        // Optimiser l'interface selon la qualité
        if (state.quality === 'poor' || !state.isOnline) {
            // Désactiver les éléments lourds pour les connexions lentes
            var heavyElements = document.querySelectorAll('.lt-network-heavy');
            for (var i = 0; i < heavyElements.length; i++) {
                heavyElements[i].classList.add('lt-disabled');
            }
            
            // Afficher les contenus alternatifs légers si disponibles
            var lightAlternatives = document.querySelectorAll('.lt-network-light');
            for (var j = 0; j < lightAlternatives.length; j++) {
                lightAlternatives[j].classList.remove('lt-disabled');
            }
        } else {
            // Réactiver les éléments lourds pour les bonnes connexions
            var heavyElements = document.querySelectorAll('.lt-network-heavy');
            for (var i = 0; i < heavyElements.length; i++) {
                heavyElements[i].classList.remove('lt-disabled');
            }
            
            // Masquer les contenus alternatifs légers
            var lightAlternatives = document.querySelectorAll('.lt-network-light');
            for (var j = 0; j < lightAlternatives.length; j++) {
                lightAlternatives[j].classList.add('lt-disabled');
            }
        }
        
        // Notifier le Service Worker de l'état réseau
        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'NETWORK_STATUS',
                online: state.isOnline,
                quality: state.quality
            });
        }
    }
    
    /**
     * Gère les changements d'état de connectivité
     */
    function handleConnectivityChange() {
        var wasOnline = state.isOnline;
        state.isOnline = navigator.onLine;
        
        // Si nous venons de passer en ligne, vérifier la qualité
        if (state.isOnline && !wasOnline) {
            checkNetworkQuality().then(function() {
                // Afficher une notification de connexion rétablie
                showConnectivityNotification(true);
            });
        } else if (!state.isOnline && wasOnline) {
            // Nous venons de passer hors-ligne
            state.quality = 'offline';
            updateNetworkUI();
            showConnectivityNotification(false);
        }
    }
    
    /**
     * Affiche une notification de changement de connectivité
     */
    function showConnectivityNotification(isOnline) {
        var container = document.getElementById('lt-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'lt-toast-container';
            document.body.appendChild(container);
        }
        
        var toast = document.createElement('div');
        toast.className = 'lt-toast ' + (isOnline ? 'success' : 'warning');
        
        if (isOnline) {
            toast.innerHTML = '<strong>Connexion rétablie</strong>';
            if (state.quality === 'poor') {
                toast.innerHTML += '<p>Attention, connexion de mauvaise qualité</p>';
            }
        } else {
            toast.innerHTML = '<strong>Vous êtes hors-ligne</strong><p>Passage en mode hors-ligne</p>';
        }
        
        container.appendChild(toast);
        
        // Supprimer après 5 secondes
        setTimeout(function() {
            toast.style.opacity = '0';
            setTimeout(function() {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 5000);
    }
    
    /**
     * Initialise le détecteur réseau
     */
    function init() {
        // Vérifier l'état initial
        checkNetworkQuality();
        
        // Écouter les changements de connectivité
        window.addEventListener('online', handleConnectivityChange);
        window.addEventListener('offline', handleConnectivityChange);
        
        // Pour les appareils qui supportent l'API Network Information
        if (navigator.connection) {
            navigator.connection.addEventListener('change', function() {
                checkNetworkQuality();
            });
        }
        
        // Vérification périodique adaptée à KaiOS (économie de batterie)
        var checkInterval = config.saveBattery ? 60000 : 30000; // 1 minute ou 30 secondes
        setInterval(checkNetworkQuality, checkInterval);
        
        // Exposer les API publiques
        window.LIFE_TRAVEL.NETWORK = {
            checkQuality: checkNetworkQuality,
            getState: function() { return Object.assign({}, state); }
        };
        
        console.info('Life Travel: Détecteur réseau KaiOS initialisé');
    }
    
    // Initialiser lorsque le DOM est prêt
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
