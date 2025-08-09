/**
 * Moniteur de performances pour Life Travel Excursion
 * 
 * Mesure et rapporte les métriques de performance essentielles pour l'expérience utilisateur
 * Compatible avec Core Web Vitals
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

(function() {
    'use strict';

    // Objet principal du moniteur de performances
    var LifeTravelPerformance = {
        // Métriques de performance
        metrics: {},
        
        // Initialisation
        init: function() {
            // Vérifier si l'API Performance est disponible
            if (!window.performance || !window.performance.timing) {
                console.log('API Performance non disponible');
                return;
            }

            // Détecter la vitesse de connexion si disponible
            this.detectConnectionSpeed();
            
            // Écouter les événements de performance
            this.listenToPerformanceEvents();
            
            // Mesurer les métriques au chargement de la page
            window.addEventListener('load', this.measurePageLoadMetrics.bind(this));
            
            // Enregistrer les métriques de performance après navigation
            window.addEventListener('unload', this.sendPerformanceData.bind(this));
        },
        
        // Détecte la vitesse de connexion (si disponible)
        detectConnectionSpeed: function() {
            var connection = navigator.connection || 
                             navigator.mozConnection || 
                             navigator.webkitConnection;
            
            if (connection) {
                var type = connection.effectiveType || connection.type;
                var isSlow = (type === 'slow-2g' || type === '2g' || type === '3g');
                
                // Stocker dans un cookie pour utilisation côté serveur
                document.cookie = 'life_travel_connection_speed=' + 
                                  (isSlow ? 'slow' : 'fast') + 
                                  ';path=/;max-age=3600';
                
                // Si l'économie de données est activée
                if (connection.saveData) {
                    document.documentElement.classList.add('life-travel-save-data');
                }
            }
        },
        
        // Écoute les événements de performance
        listenToPerformanceEvents: function() {
            // Observer les métriques Web Vitals si disponibles
            if ('PerformanceObserver' in window) {
                try {
                    // Observer LCP (Largest Contentful Paint)
                    var lcpObserver = new PerformanceObserver((entryList) => {
                        var entries = entryList.getEntries();
                        var lastEntry = entries[entries.length - 1];
                        this.metrics.lcp = lastEntry.startTime;
                    });
                    lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });
                    
                    // Observer FID (First Input Delay)
                    var fidObserver = new PerformanceObserver((entryList) => {
                        var entries = entryList.getEntries();
                        var firstInput = entries[0];
                        this.metrics.fid = firstInput.processingStart - firstInput.startTime;
                    });
                    fidObserver.observe({ type: 'first-input', buffered: true });
                    
                    // Observer CLS (Cumulative Layout Shift)
                    var clsValue = 0;
                    var clsObserver = new PerformanceObserver((entryList) => {
                        var entries = entryList.getEntries();
                        entries.forEach(entry => {
                            // N'incluez que les changements sans interaction utilisateur
                            if (!entry.hadRecentInput) {
                                clsValue += entry.value;
                            }
                        });
                        this.metrics.cls = clsValue;
                    });
                    clsObserver.observe({ type: 'layout-shift', buffered: true });
                    
                } catch (e) {
                    console.log('Erreur lors de l\'observation des métriques Web Vitals:', e);
                }
            }
        },
        
        // Mesure les métriques de chargement de la page
        measurePageLoadMetrics: function() {
            var timing = performance.timing;
            
            // Calculer les métriques standard
            this.metrics.loadTime = timing.loadEventEnd - timing.navigationStart;
            this.metrics.domReady = timing.domContentLoadedEventEnd - timing.navigationStart;
            this.metrics.ttfb = timing.responseStart - timing.navigationStart;
            this.metrics.networkLatency = timing.responseEnd - timing.fetchStart;
            this.metrics.processingTime = timing.loadEventStart - timing.responseEnd;
            
            // Journaliser pour le débogage
            if (window.lifeTravelDebug) {
                console.log('Métriques de performance:', this.metrics);
            }
            
            // Optimize UX based on performance metrics
            this.optimizeUX();
        },
        
        // Optimiser l'expérience utilisateur basée sur les métriques
        optimizeUX: function() {
            // Ajouter une classe au body en fonction de la performance
            var performance = 'fast';
            
            if (this.metrics.loadTime > 5000 || this.metrics.ttfb > 1000) {
                performance = 'slow';
            } else if (this.metrics.loadTime > 2500 || this.metrics.ttfb > 500) {
                performance = 'medium';
            }
            
            document.body.classList.add('life-travel-performance-' + performance);
            
            // Adapter l'interface en fonction de la performance
            if (performance === 'slow') {
                // Désactiver certaines animations
                document.body.classList.add('life-travel-reduce-animations');
                
                // Charger les images avec une qualité inférieure
                document.querySelectorAll('img[data-quality-src]').forEach(function(img) {
                    img.src = img.getAttribute('data-quality-src');
                });
            }
        },
        
        // Envoie les données de performance pour analyse
        sendPerformanceData: function() {
            if (Object.keys(this.metrics).length === 0) return;
            
            // Utiliser le Beacon API pour envoyer les données de manière non bloquante
            if (navigator.sendBeacon) {
                var data = new FormData();
                data.append('action', 'life_travel_log_performance');
                data.append('metrics', JSON.stringify(this.metrics));
                data.append('page_url', window.location.href);
                data.append('user_agent', navigator.userAgent);
                
                navigator.sendBeacon(lifeTravelPerformance.ajaxUrl, data);
            }
        }
    };
    
    // Initialiser une fois le DOM chargé
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lifeTravelPerformance !== 'undefined') {
            LifeTravelPerformance.ajaxUrl = lifeTravelPerformance.ajaxUrl;
            LifeTravelPerformance.init();
        }
    });
    
    // Exposer certaines fonctionnalités à la portée globale
    window.LifeTravelPerformance = {
        // Méthode pour mesurer manuellement le temps d'exécution
        measureTime: function(label, callback) {
            if (typeof callback !== 'function') return;
            
            var startTime = performance.now();
            callback();
            var endTime = performance.now();
            
            console.log('[Performance] ' + label + ': ' + (endTime - startTime).toFixed(2) + 'ms');
        }
    };
})();
