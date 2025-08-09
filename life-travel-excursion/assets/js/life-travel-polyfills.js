/**
 * Life Travel - Polyfills pour le contexte camerounais
 * 
 * Ce fichier fournit des polyfills essentiels pour garantir la compatibilité
 * avec les navigateurs plus anciens courants au Cameroun, notamment :
 * - Opera Mini (très populaire pour son économie de données)
 * - Anciens navigateurs Android (versions 4.4 et inférieures)
 * - Navigateurs KaiOS (téléphones à touches populaires)
 * - UC Browser (version mobile populaire)
 * 
 * @package Life Travel Excursion
 * @version 2.4.0
 */

(function() {
    'use strict';
    
    // Rapport de compatibilité pour le diagnostic
    var compatReport = {
        promiseSupport: typeof Promise !== 'undefined',
        fetchSupport: typeof fetch !== 'undefined',
        abortControllerSupport: typeof AbortController !== 'undefined',
        allSettledSupport: Promise && typeof Promise.allSettled === 'function',
        indexedDBSupport: typeof indexedDB !== 'undefined',
        localStorageSupport: typeof localStorage !== 'undefined',
        cssVariablesSupport: window.CSS && CSS.supports && CSS.supports('--var: 0')
    };
    
    // Journaliser la compatibilité (utile pour le diagnostic et les statistiques)
    try {
        console.log('[Life Travel] Rapport de compatibilité:', compatReport);
        
        // Enregistrer dans localStorage pour les statistiques
        if (compatReport.localStorageSupport) {
            localStorage.setItem('lt_compat_report', JSON.stringify({
                report: compatReport,
                userAgent: navigator.userAgent,
                timestamp: Date.now()
            }));
        }
    } catch (e) {
        // Ignorer les erreurs de logging
    }
    
    // Détecter les navigateurs problématiques
    var isOperaMini = navigator.userAgent.indexOf('Opera Mini') > -1;
    var isOldAndroid = navigator.userAgent.indexOf('Android') > -1 && 
                       parseFloat(navigator.userAgent.slice(navigator.userAgent.indexOf('Android') + 8)) < 5;
    var isUCBrowser = navigator.userAgent.indexOf('UCBrowser') > -1;
    var isKaiOS = navigator.userAgent.indexOf('KAIOS') > -1;
    
    // Définition d'une variable globale safe pour l'état de compatibilité
    window.LIFE_TRAVEL = window.LIFE_TRAVEL || {};
    window.LIFE_TRAVEL.COMPAT = {
        isOperaMini: isOperaMini,
        isOldAndroid: isOldAndroid,
        isUCBrowser: isUCBrowser,
        isKaiOS: isKaiOS,
        needsPolyfills: isOperaMini || isOldAndroid || isUCBrowser || isKaiOS || 
                         !compatReport.promiseSupport || !compatReport.fetchSupport ||
                         !compatReport.allSettledSupport || !compatReport.abortControllerSupport
    };
    
    /**
     * Polyfill pour Promise
     * Crucial pour les anciens navigateurs Android et Opera Mini
     */
    if (!compatReport.promiseSupport) {
        // Note: Un vrai polyfill Promise est complexe
        // Cette implémentation basique ne respecte pas toutes les spécifications,
        // mais fournit l'API minimale pour notre contexte
        window.Promise = function(executor) {
            var doneCallbacks = [];
            var failCallbacks = [];
            var state = 'pending';
            var value;
            
            function resolve(val) {
                if (state === 'pending') {
                    state = 'fulfilled';
                    value = val;
                    doneCallbacks.forEach(function(cb) {
                        setTimeout(function() {
                            cb(value);
                        }, 1);
                    });
                }
            }
            
            function reject(reason) {
                if (state === 'pending') {
                    state = 'rejected';
                    value = reason;
                    failCallbacks.forEach(function(cb) {
                        setTimeout(function() {
                            cb(value);
                        }, 1);
                    });
                }
            }
            
            this.then = function(onFulfilled, onRejected) {
                return new Promise(function(resolve, reject) {
                    if (onFulfilled && typeof onFulfilled === 'function') {
                        doneCallbacks.push(function(value) {
                            try {
                                var result = onFulfilled(value);
                                resolve(result);
                            } catch (e) {
                                reject(e);
                            }
                        });
                    }
                    
                    if (onRejected && typeof onRejected === 'function') {
                        failCallbacks.push(function(reason) {
                            try {
                                var result = onRejected(reason);
                                resolve(result);
                            } catch (e) {
                                reject(e);
                            }
                        });
                    }
                    
                    if (state === 'fulfilled') {
                        doneCallbacks.forEach(function(cb) {
                            setTimeout(function() {
                                cb(value);
                            }, 1);
                        });
                    } else if (state === 'rejected') {
                        failCallbacks.forEach(function(cb) {
                            setTimeout(function() {
                                cb(value);
                            }, 1);
                        });
                    }
                });
            };
            
            this.catch = function(onRejected) {
                return this.then(null, onRejected);
            };
            
            executor(resolve, reject);
        };
        
        // Promise.all basique
        window.Promise.all = function(promises) {
            return new Promise(function(resolve, reject) {
                var results = [];
                var remainingPromises = promises.length;
                
                if (remainingPromises === 0) {
                    resolve([]);
                    return;
                }
                
                promises.forEach(function(promise, index) {
                    Promise.resolve(promise).then(function(result) {
                        results[index] = result;
                        remainingPromises--;
                        
                        if (remainingPromises === 0) {
                            resolve(results);
                        }
                    }, reject);
                });
            });
        };
        
        // Utilitaire pour résoudre une valeur ou une promesse
        window.Promise.resolve = function(value) {
            return new Promise(function(resolve) {
                resolve(value);
            });
        };
        
        // Utilitaire pour rejeter avec une raison
        window.Promise.reject = function(reason) {
            return new Promise(function(resolve, reject) {
                reject(reason);
            });
        };
        
        console.log('[Life Travel] Polyfill Promise installé');
    }
    
    /**
     * Polyfill pour Promise.allSettled
     * Utilisé dans notre code pour la gestion robuste des ressources
     */
    if (window.Promise && !compatReport.allSettledSupport) {
        Promise.allSettled = function(promises) {
            return Promise.all(promises.map(function(promise) {
                return Promise.resolve(promise)
                    .then(function(value) {
                        return { status: 'fulfilled', value: value };
                    })
                    .catch(function(reason) {
                        return { status: 'rejected', reason: reason };
                    });
            }));
        };
        console.log('[Life Travel] Polyfill Promise.allSettled installé');
    }
    
    /**
     * Polyfill pour fetch et AbortController
     * Crucial pour les requêtes avec timeout dans le contexte camerounais
     */
    if (!compatReport.fetchSupport) {
        // Polyfill fetch simplifié basé sur XMLHttpRequest
        window.fetch = function(url, options) {
            options = options || {};
            
            return new Promise(function(resolve, reject) {
                var xhr = new XMLHttpRequest();
                
                xhr.open(options.method || 'GET', url, true);
                
                if (options.headers) {
                    Object.keys(options.headers).forEach(function(key) {
                        xhr.setRequestHeader(key, options.headers[key]);
                    });
                }
                
                // Gérer le signal d'annulation de l'AbortController
                if (options.signal) {
                    options.signal.addEventListener('abort', function() {
                        xhr.abort();
                        reject(new Error('Request aborted'));
                    });
                }
                
                xhr.onload = function() {
                    var response = {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        ok: xhr.status >= 200 && xhr.status < 300,
                        headers: {
                            get: function(header) {
                                return xhr.getResponseHeader(header);
                            }
                        },
                        text: function() {
                            return Promise.resolve(xhr.responseText);
                        },
                        json: function() {
                            try {
                                return Promise.resolve(JSON.parse(xhr.responseText));
                            } catch (e) {
                                return Promise.reject(e);
                            }
                        },
                        blob: function() {
                            return Promise.resolve(new Blob([xhr.response]));
                        },
                        clone: function() {
                            return response;
                        }
                    };
                    
                    resolve(response);
                };
                
                xhr.onerror = function() {
                    reject(new Error('Network error'));
                };
                
                xhr.send(options.body);
            });
        };
        
        console.log('[Life Travel] Polyfill fetch installé');
    }
    
    /**
     * Polyfill pour AbortController
     * Utilisé pour les timeouts dans le contexte camerounais
     */
    if (!compatReport.abortControllerSupport) {
        window.AbortController = function() {
            this.signal = {
                aborted: false,
                addEventListener: function(type, listener) {
                    if (type !== 'abort') return;
                    this._listeners = this._listeners || [];
                    this._listeners.push(listener);
                },
                removeEventListener: function(type, listener) {
                    if (type !== 'abort') return;
                    this._listeners = this._listeners || [];
                    this._listeners = this._listeners.filter(function(fn) {
                        return fn !== listener;
                    });
                },
                dispatchEvent: function(event) {
                    if (event.type !== 'abort') return;
                    this._listeners = this._listeners || [];
                    this._listeners.forEach(function(fn) {
                        fn(event);
                    });
                }
            };
            
            this.abort = function() {
                this.signal.aborted = true;
                var event = { type: 'abort', target: this.signal };
                this.signal.dispatchEvent(event);
            };
        };
        
        // Méthode statique timeout (utilisée dans notre code)
        window.AbortSignal = {
            timeout: function(ms) {
                var controller = new AbortController();
                setTimeout(function() {
                    controller.abort();
                }, ms);
                return controller.signal;
            }
        };
        
        console.log('[Life Travel] Polyfill AbortController installé');
    } else if (typeof AbortSignal !== 'undefined' && !AbortSignal.timeout) {
        // Polyfill uniquement pour AbortSignal.timeout si nécessaire
        AbortSignal.timeout = function(ms) {
            var controller = new AbortController();
            setTimeout(function() {
                controller.abort();
            }, ms);
            return controller.signal;
        };
        
        console.log('[Life Travel] Polyfill AbortSignal.timeout installé');
    }
    
    /**
     * Polyfill pour Array.prototype.includes
     * Utilisé pour les vérifications simples de présence dans des tableaux
     */
    if (!Array.prototype.includes) {
        Array.prototype.includes = function(searchElement, fromIndex) {
            if (this == null) {
                throw new TypeError('"this" is null or not defined');
            }
            
            var o = Object(this);
            var len = o.length >>> 0;
            
            if (len === 0) {
                return false;
            }
            
            var n = fromIndex | 0;
            var k = Math.max(n >= 0 ? n : len - Math.abs(n), 0);
            
            function sameValueZero(x, y) {
                return x === y || (typeof x === 'number' && typeof y === 'number' && isNaN(x) && isNaN(y));
            }
            
            while (k < len) {
                if (sameValueZero(o[k], searchElement)) {
                    return true;
                }
                k++;
            }
            
            return false;
        };
        
        console.log('[Life Travel] Polyfill Array.includes installé');
    }
    
    /**
     * Polyfill pour Object.assign
     * Utilisé dans notre code pour la fusion d'options
     */
    if (typeof Object.assign !== 'function') {
        Object.assign = function(target) {
            if (target == null) {
                throw new TypeError('Cannot convert undefined or null to object');
            }
            
            var to = Object(target);
            
            for (var i = 1; i < arguments.length; i++) {
                var nextSource = arguments[i];
                
                if (nextSource != null) {
                    for (var nextKey in nextSource) {
                        if (Object.prototype.hasOwnProperty.call(nextSource, nextKey)) {
                            to[nextKey] = nextSource[nextKey];
                        }
                    }
                }
            }
            
            return to;
        };
        
        console.log('[Life Travel] Polyfill Object.assign installé');
    }
    
    /**
     * Polyfill pour Element.prototype.matches
     * Utilisé pour la délégation d'événements
     */
    if (!Element.prototype.matches) {
        Element.prototype.matches =
            Element.prototype.matchesSelector ||
            Element.prototype.mozMatchesSelector ||
            Element.prototype.msMatchesSelector ||
            Element.prototype.oMatchesSelector ||
            Element.prototype.webkitMatchesSelector ||
            function(s) {
                var matches = (this.document || this.ownerDocument).querySelectorAll(s),
                    i = matches.length;
                while (--i >= 0 && matches.item(i) !== this) {}
                return i > -1;
            };
        
        console.log('[Life Travel] Polyfill Element.matches installé');
    }
    
    /**
     * Polyfill pour Console (Opera Mini, certains navigateurs KaiOS)
     * Pour éviter les erreurs sur console.log/warn/error
     */
    if (typeof console === 'undefined') {
        window.console = {
            log: function() {},
            warn: function() {},
            error: function() {},
            info: function() {}
        };
    } else {
        // S'assurer que toutes les méthodes existent
        if (!console.log) console.log = function() {};
        if (!console.warn) console.warn = function() {};
        if (!console.error) console.error = function() {};
        if (!console.info) console.info = function() {};
    }
    
    /**
     * Polyfill pour localStorage (Opera Mini en mode Turbo)
     * Fournit une implémentation basée sur les cookies si localStorage n'est pas disponible
     */
    if (!compatReport.localStorageSupport) {
        (function() {
            var cookies = {};
            
            // Parse tous les cookies existants
            document.cookie.split(';').forEach(function(cookie) {
                var parts = cookie.split('=');
                if (parts.length === 2 && parts[0].trim().indexOf('lt_') === 0) {
                    try {
                        cookies[parts[0].trim()] = decodeURIComponent(parts[1]);
                    } catch (e) {
                        // Ignorer les cookies malformés
                    }
                }
            });
            
            window.localStorage = {
                getItem: function(key) {
                    return cookies['lt_' + key] || null;
                },
                setItem: function(key, value) {
                    try {
                        var cookieKey = 'lt_' + key;
                        cookies[cookieKey] = String(value);
                        var date = new Date();
                        date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000)); // 30 jours
                        document.cookie = cookieKey + '=' + encodeURIComponent(String(value)) + 
                                         '; expires=' + date.toUTCString() + '; path=/';
                    } catch (e) {
                        console.warn('Erreur localStorage cookie fallback:', e);
                    }
                },
                removeItem: function(key) {
                    var cookieKey = 'lt_' + key;
                    delete cookies[cookieKey];
                    document.cookie = cookieKey + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                },
                clear: function() {
                    Object.keys(cookies).forEach(function(key) {
                        if (key.indexOf('lt_') === 0) {
                            delete cookies[key];
                            document.cookie = key + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                        }
                    });
                }
            };
            
            // Ajouter propriété length
            Object.defineProperty(window.localStorage, 'length', {
                get: function() {
                    return Object.keys(cookies).filter(function(key) {
                        return key.indexOf('lt_') === 0;
                    }).length;
                }
            });
            
            console.log('[Life Travel] Polyfill localStorage installé (basé sur cookies)');
        })();
    }
    
    // Marquer que les polyfills ont été chargés
    window.LIFE_TRAVEL.POLYFILLS_LOADED = true;
    
    // Function d'initialisation à appeler après le chargement complet
    window.LIFE_TRAVEL.initializePolyfills = function() {
        console.log('[Life Travel] Initialisation des polyfills terminée');
        
        // Déclencher un événement pour signaler que les polyfills sont prêts
        var event = new Event('lifeTravelPolyfillsReady');
        window.dispatchEvent(event);
    };
    
    // Exécuter l'initialisation
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(window.LIFE_TRAVEL.initializePolyfills, 1);
    } else {
        document.addEventListener('DOMContentLoaded', window.LIFE_TRAVEL.initializePolyfills);
    }
})();
