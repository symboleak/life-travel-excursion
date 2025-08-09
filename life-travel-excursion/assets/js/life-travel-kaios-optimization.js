/**
 * Life Travel - Optimisations KaiOS
 * 
 * Ce script fournit des optimisations de performance et de gestion mémoire
 * pour les appareils KaiOS. Il est critique pour le contexte camerounais où
 * ces téléphones à touches abordables sont largement utilisés, souvent
 * sur des réseaux à bande passante limitée.
 * 
 * @package Life Travel Excursion
 * @version 2.4.0
 */

// Namespace pour éviter les collisions
window.LIFE_TRAVEL = window.LIFE_TRAVEL || {};
window.LIFE_TRAVEL.KAIOS = window.LIFE_TRAVEL.KAIOS || {};

/**
 * Gestionnaire d'optimisation pour appareils KaiOS
 */
(function() {
    // S'assurer que nous sommes sur un appareil KaiOS
    if (!window.LIFE_TRAVEL.KAIOS.isKaiOS) return;
    
    // Référence à l'espace de noms
    var KAIOS = window.LIFE_TRAVEL.KAIOS;
    
    // Paramètres d'optimisation spécifiques à KaiOS
    KAIOS.optimization = {
        // Suivi des performances
        performanceMetrics: {
            pageLoadStart: Date.now(),
            resourcesLoaded: 0,
            resourcesFailed: 0,
            memoryWarnings: 0,
            lastMemoryCheck: 0
        },
        
        // Configuration adaptée aux appareils KaiOS
        config: {
            // Limites de chargement pour éviter de surcharger l'appareil
            maxImagesPerView: 5,         // Maximum d'images à charger simultanément
            maxElementsInLists: 20,      // Maximum d'éléments à afficher dans les listes
            refreshRate: 500,            // Intervalle pour la mise à jour de l'UI (ms)
            lowMemoryThreshold: 2000000, // 2MB - seuil pour le mode économie de mémoire
            imageLazyLoadOffset: 100     // Distance en pixels pour le lazy loading
        },
        
        // État actuel
        state: {
            isLowMemoryMode: false,      // Mode économie de mémoire
            isPaused: false,             // Interface mise en pause (paginée)
            isContentTruncated: false,   // Contenu tronqué pour économiser la mémoire
            currentPage: 0,              // Page actuelle pour la pagination
            deferredOperations: []       // Opérations reportées à plus tard
        }
    };
    
    /**
     * Initialise les optimisations pour KaiOS
     */
    KAIOS.initOptimizations = function() {
        // Ajouter la classe d'optimisation
        document.documentElement.classList.add('lt-kaios-optimized');
        
        // Détecter les appareils à faible mémoire
        checkDeviceCapabilities();
        
        // Optimiser le chargement des images
        setupLazyLoading();
        
        // Pagination des listes et contenus longs
        setupPagination();
        
        // Optimiser les événements scroll/resize (très coûteux sur KaiOS)
        optimizeEventHandlers();
        
        // Économiser la batterie en limitant les animations
        limitAnimations();
        
        // Surveillance de la mémoire
        if (window.performance && window.performance.memory) {
            setInterval(checkMemoryUsage, 30000); // Vérifier toutes les 30 secondes
        }
        
        // Surveiller la performance
        setupPerformanceMonitoring();
        
        console.info('Life Travel: Optimisations KaiOS initialisées');
    };
    
    /**
     * Vérifie les capacités de l'appareil et ajuste les optimisations
     */
    function checkDeviceCapabilities() {
        // Capacités mémoire (estimation)
        var lowMemory = false;
        
        // Vérifier si l'API de mémoire est disponible
        if (window.performance && window.performance.memory) {
            lowMemory = window.performance.memory.jsHeapSizeLimit < 
                        KAIOS.optimization.config.lowMemoryThreshold;
        } else {
            // Vérification alternative basée sur le user agent
            lowMemory = KAIOS.version && parseFloat(KAIOS.version) < 2.5;
        }
        
        // Appliquer le mode économie de mémoire si nécessaire
        if (lowMemory) {
            KAIOS.optimization.state.isLowMemoryMode = true;
            document.documentElement.classList.add('lt-kaios-low-memory');
            
            // Réduire encore plus les limites
            KAIOS.optimization.config.maxImagesPerView = 3;
            KAIOS.optimization.config.maxElementsInLists = 10;
            
            console.info('Life Travel: Mode économie de mémoire KaiOS activé');
        }
    }
    
    /**
     * Configure le lazy loading optimisé pour KaiOS
     */
    function setupLazyLoading() {
        // Trouver toutes les images
        var images = document.querySelectorAll('img:not(.lt-critical-image)');
        
        // Convertir les images en lazy loading
        for (var i = 0; i < images.length; i++) {
            var img = images[i];
            
            // Stocker l'URL d'origine
            if (img.src) {
                img.setAttribute('data-src', img.src);
                
                // Remplacer par un placeholder
                img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3C/svg%3E';
                img.classList.add('lt-lazy-image');
            }
        }
        
        // Observer le scroll pour charger les images
        document.addEventListener('scroll', lazyLoadImages);
        window.addEventListener('resize', lazyLoadImages);
        window.addEventListener('orientationchange', lazyLoadImages);
        
        // Chargement initial
        setTimeout(lazyLoadImages, 500);
    }
    
    /**
     * Charge les images en fonction de la visibilité
     */
    function lazyLoadImages() {
        // Éviter les appels trop fréquents
        if (KAIOS.optimization.state.lazyLoadProcessing) return;
        KAIOS.optimization.state.lazyLoadProcessing = true;
        
        setTimeout(function() {
            var lazyImages = document.querySelectorAll('img.lt-lazy-image');
            var loadedCount = 0;
            var maxImages = KAIOS.optimization.config.maxImagesPerView;
            var offset = KAIOS.optimization.config.imageLazyLoadOffset;
            
            for (var i = 0; i < lazyImages.length; i++) {
                if (loadedCount >= maxImages) break;
                
                var img = lazyImages[i];
                
                // Vérifier si l'image est visible
                var rect = img.getBoundingClientRect();
                var isVisible = rect.top <= window.innerHeight + offset && 
                               rect.bottom >= 0 - offset && 
                               rect.left <= window.innerWidth + offset && 
                               rect.right >= 0 - offset;
                
                if (isVisible) {
                    img.src = img.getAttribute('data-src');
                    img.removeAttribute('data-src');
                    img.classList.remove('lt-lazy-image');
                    loadedCount++;
                }
            }
            
            KAIOS.optimization.state.lazyLoadProcessing = false;
        }, 200);
    }
    
    /**
     * Configure la pagination pour les longues listes
     */
    function setupPagination() {
        // Identifier les listes longues
        var longLists = document.querySelectorAll('.lt-list, .products, .excursions, [data-lt-paginate]');
        
        for (var i = 0; i < longLists.length; i++) {
            var list = longLists[i];
            var items = list.children;
            var maxItems = KAIOS.optimization.config.maxElementsInLists;
            
            // Ne paginer que si nécessaire
            if (items.length > maxItems) {
                list.classList.add('lt-paginated');
                
                // Cacher les éléments en trop
                for (var j = maxItems; j < items.length; j++) {
                    items[j].style.display = 'none';
                    items[j].setAttribute('data-lt-page', Math.floor(j / maxItems));
                }
                
                // Créer la navigation de pagination
                var paginationNav = document.createElement('div');
                paginationNav.className = 'lt-pagination-nav';
                
                var prevButton = document.createElement('button');
                prevButton.className = 'lt-prev-page';
                prevButton.textContent = 'Précédent';
                prevButton.setAttribute('data-list-id', 'list-' + i);
                prevButton.addEventListener('click', goToPrevPage);
                
                var nextButton = document.createElement('button');
                nextButton.className = 'lt-next-page';
                nextButton.textContent = 'Suivant';
                nextButton.setAttribute('data-list-id', 'list-' + i);
                nextButton.addEventListener('click', goToNextPage);
                
                var pageInfo = document.createElement('span');
                pageInfo.className = 'lt-page-info';
                pageInfo.textContent = 'Page 1/' + Math.ceil(items.length / maxItems);
                
                paginationNav.appendChild(prevButton);
                paginationNav.appendChild(pageInfo);
                paginationNav.appendChild(nextButton);
                
                // Ajouter la navigation après la liste
                list.parentNode.insertBefore(paginationNav, list.nextSibling);
                
                // Stocker l'état de pagination
                list.setAttribute('data-lt-current-page', '0');
                list.setAttribute('data-lt-total-pages', Math.ceil(items.length / maxItems));
                
                // Désactiver le bouton précédent initialement
                prevButton.disabled = true;
            }
        }
    }
    
    /**
     * Passe à la page précédente
     */
    function goToPrevPage(event) {
        var listId = event.currentTarget.getAttribute('data-list-id');
        var list = document.querySelector('[data-list-id="' + listId + '"]');
        
        if (!list) return;
        
        var currentPage = parseInt(list.getAttribute('data-lt-current-page'));
        var totalPages = parseInt(list.getAttribute('data-lt-total-pages'));
        
        if (currentPage > 0) {
            // Cacher les éléments de la page actuelle
            var currentItems = list.querySelectorAll('[data-lt-page="' + currentPage + '"]');
            for (var i = 0; i < currentItems.length; i++) {
                currentItems[i].style.display = 'none';
            }
            
            // Afficher les éléments de la page précédente
            currentPage--;
            var prevItems = list.querySelectorAll('[data-lt-page="' + currentPage + '"]');
            for (var j = 0; j < prevItems.length; j++) {
                prevItems[j].style.display = '';
            }
            
            // Mettre à jour l'état
            list.setAttribute('data-lt-current-page', currentPage);
            
            // Mettre à jour les boutons
            var nextButton = list.parentNode.querySelector('.lt-next-page');
            nextButton.disabled = false;
            
            if (currentPage === 0) {
                event.currentTarget.disabled = true;
            }
            
            // Mettre à jour l'info de page
            var pageInfo = list.parentNode.querySelector('.lt-page-info');
            pageInfo.textContent = 'Page ' + (currentPage + 1) + '/' + totalPages;
        }
    }
    
    /**
     * Passe à la page suivante
     */
    function goToNextPage(event) {
        var listId = event.currentTarget.getAttribute('data-list-id');
        var list = document.querySelector('[data-list-id="' + listId + '"]');
        
        if (!list) return;
        
        var currentPage = parseInt(list.getAttribute('data-lt-current-page'));
        var totalPages = parseInt(list.getAttribute('data-lt-total-pages'));
        
        if (currentPage < totalPages - 1) {
            // Cacher les éléments de la page actuelle
            var currentItems = list.querySelectorAll('[data-lt-page="' + currentPage + '"]');
            for (var i = 0; i < currentItems.length; i++) {
                currentItems[i].style.display = 'none';
            }
            
            // Afficher les éléments de la page suivante
            currentPage++;
            var nextItems = list.querySelectorAll('[data-lt-page="' + currentPage + '"]');
            for (var j = 0; j < nextItems.length; j++) {
                nextItems[j].style.display = '';
            }
            
            // Mettre à jour l'état
            list.setAttribute('data-lt-current-page', currentPage);
            
            // Mettre à jour les boutons
            var prevButton = list.parentNode.querySelector('.lt-prev-page');
            prevButton.disabled = false;
            
            if (currentPage === totalPages - 1) {
                event.currentTarget.disabled = true;
            }
            
            // Mettre à jour l'info de page
            var pageInfo = list.parentNode.querySelector('.lt-page-info');
            pageInfo.textContent = 'Page ' + (currentPage + 1) + '/' + totalPages;
        }
    }
    
    /**
     * Optimise les gestionnaires d'événements (très coûteux sur KaiOS)
     */
    function optimizeEventHandlers() {
        // Limiter la fréquence des événements de défilement
        var scrollTimeout;
        var originalScrollEvent = window.onscroll;
        
        window.onscroll = function(event) {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function() {
                if (typeof originalScrollEvent === 'function') {
                    originalScrollEvent(event);
                }
                // Déclencher un événement custom optimisé
                var optimizedEvent = new CustomEvent('optimizedScroll');
                window.dispatchEvent(optimizedEvent);
            }, KAIOS.optimization.config.refreshRate);
        };
        
        // Limiter la fréquence des événements de redimensionnement
        var resizeTimeout;
        var originalResizeEvent = window.onresize;
        
        window.onresize = function(event) {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (typeof originalResizeEvent === 'function') {
                    originalResizeEvent(event);
                }
                // Déclencher un événement custom optimisé
                var optimizedEvent = new CustomEvent('optimizedResize');
                window.dispatchEvent(optimizedEvent);
            }, KAIOS.optimization.config.refreshRate);
        };
        
        // Remplacer les événements scroll standard par des versions optimisées
        document.addEventListener('scroll', function(e) {
            if (e.target === document) {
                e.stopPropagation();
            }
        }, true);
    }
    
    /**
     * Limite les animations pour économiser batterie et CPU
     */
    function limitAnimations() {
        // Ajouter une classe pour contrôler les animations via CSS
        document.documentElement.classList.add('lt-reduce-motion');
        
        // Désactiver les animations CSS si besoin
        if (KAIOS.optimization.state.isLowMemoryMode) {
            var style = document.createElement('style');
            style.textContent = `
                .lt-kaios-optimized * {
                    transition-duration: 0ms !important;
                    animation-duration: 0ms !important;
                    animation-iteration-count: 1 !important;
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    /**
     * Surveille l'utilisation de la mémoire
     */
    function checkMemoryUsage() {
        if (!window.performance || !window.performance.memory) return;
        
        var memory = window.performance.memory;
        var usedHeapSize = memory.usedJSHeapSize;
        var totalHeapSize = memory.jsHeapSizeLimit;
        var usageRatio = usedHeapSize / totalHeapSize;
        
        KAIOS.optimization.performanceMetrics.lastMemoryCheck = Date.now();
        
        // Alerte si l'utilisation de la mémoire est élevée
        if (usageRatio > 0.7) {
            console.warn('Life Travel KaiOS: Utilisation mémoire élevée (' + 
                          Math.round(usageRatio * 100) + '%)');
            
            KAIOS.optimization.performanceMetrics.memoryWarnings++;
            
            // Prendre des mesures en fonction de la gravité
            if (usageRatio > 0.9) {
                // Situation critique - libérer des ressources
                reduceMemoryFootprint();
            }
        }
    }
    
    /**
     * Réduit l'empreinte mémoire en libérant des ressources
     */
    function reduceMemoryFootprint() {
        // Activer le mode économie de mémoire
        KAIOS.optimization.state.isLowMemoryMode = true;
        document.documentElement.classList.add('lt-kaios-low-memory');
        
        // Libérer les images non visibles
        var images = document.querySelectorAll('img:not(.lt-critical-image)');
        var visibleCount = 0;
        
        for (var i = 0; i < images.length; i++) {
            var img = images[i];
            var rect = img.getBoundingClientRect();
            
            // Garder seulement les images visibles
            if (rect.top <= window.innerHeight && 
                rect.bottom >= 0 && 
                rect.left <= window.innerWidth && 
                rect.right >= 0) {
                
                visibleCount++;
                
                // Limiter le nombre d'images même visibles
                if (visibleCount > 3) {
                    // Stocker l'URL pour rechargement futur
                    if (!img.hasAttribute('data-src') && img.src) {
                        img.setAttribute('data-src', img.src);
                    }
                    img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3C/svg%3E';
                }
            } else {
                // Image hors écran - libérer
                if (!img.hasAttribute('data-src') && img.src) {
                    img.setAttribute('data-src', img.src);
                }
                img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3C/svg%3E';
            }
        }
        
        // Suggérer au navigateur d'exécuter le garbage collector
        if (window.gc) {
            try {
                window.gc();
            } catch (e) {}
        }
    }
    
    /**
     * Configure la surveillance des performances
     */
    function setupPerformanceMonitoring() {
        // Mesurer le temps de chargement complet
        window.addEventListener('load', function() {
            var loadTime = Date.now() - KAIOS.optimization.performanceMetrics.pageLoadStart;
            console.info('Life Travel KaiOS: Page chargée en ' + loadTime + 'ms');
            
            // Envoyer les métriques au serveur pour analyse
            if (navigator.onLine && window.fetch) {
                try {
                    var metrics = {
                        loadTime: loadTime,
                        userAgent: navigator.userAgent,
                        kaiOSVersion: KAIOS.version,
                        isLowMemoryMode: KAIOS.optimization.state.isLowMemoryMode,
                        url: window.location.pathname
                    };
                    
                    // Utiliser sendBeacon si disponible pour ne pas bloquer
                    if (navigator.sendBeacon) {
                        var blob = new Blob([JSON.stringify(metrics)], {type: 'application/json'});
                        navigator.sendBeacon('/wp-admin/admin-ajax.php?action=life_travel_kaios_metrics', blob);
                    } else {
                        // Fallback pour les appareils sans sendBeacon
                        fetch('/wp-admin/admin-ajax.php?action=life_travel_kaios_metrics', {
                            method: 'POST',
                            body: JSON.stringify(metrics),
                            headers: {'Content-Type': 'application/json'},
                            // Ne pas attendre la réponse
                            keepalive: true
                        }).catch(function() {});
                    }
                } catch (e) {}
            }
        });
    }
    
    // Initialiser les optimisations lorsque le DOM est prêt
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', KAIOS.initOptimizations);
    } else {
        KAIOS.initOptimizations();
    }
})();
