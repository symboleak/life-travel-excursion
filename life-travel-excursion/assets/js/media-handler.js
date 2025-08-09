/**
 * Gestionnaire d'éléments visuels pour Life Travel
 * @package Life Travel
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    // Objet principal de gestion des médias
    var LifeTravelMedia = {
        /**
         * Initialisation du gestionnaire
         */
        init: function() {
            // Détection des fonctionnalités du navigateur
            this.detectFeatures();
            
            // Précharger les images importantes
            this.preloadCriticalImages();
            
            // Initialiser la gestion des images responsives
            this.setupResponsiveImages();
            
            // Initialiser la gestion des vidéos
            this.setupVideoHandlers();
            
            // Initialiser la gestion du logo
            this.setupLogoHandling();
            
            // Gestion des images de fond
            this.setupBackgroundImages();
        },
        
        /**
         * Détection des fonctionnalités du navigateur
         */
        detectFeatures: function() {
            this.hasIntersectionObserver = 'IntersectionObserver' in window;
            this.supportsWebP = false;
            
            // Tester le support WebP
            var webpCheck = new Image();
            webpCheck.onload = function() {
                LifeTravelMedia.supportsWebP = true;
            };
            webpCheck.onerror = function() {
                LifeTravelMedia.supportsWebP = false;
            };
            webpCheck.src = 'data:image/webp;base64,UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAwA0JaQAA3AA/vuUAAA=';
        },
        
        /**
         * Préchargement des images critiques
         */
        preloadCriticalImages: function() {
            // Si des images critiques sont définies dans les paramètres
            if (typeof LifeTravelParams !== 'undefined' && LifeTravelParams.visual_elements) {
                var critical = [LifeTravelParams.visual_elements.logo_url];
                
                if (LifeTravelParams.visual_elements.default_background) {
                    critical.push(LifeTravelParams.visual_elements.default_background);
                }
                
                // Précharger les images
                critical.forEach(function(src) {
                    if (src) {
                        var img = new Image();
                        img.src = src;
                    }
                });
            }
        },
        
        /**
         * Configuration des images responsives
         */
        setupResponsiveImages: function() {
            // Si le navigateur supporte l'Intersection Observer
            if (this.hasIntersectionObserver) {
                var options = {
                    rootMargin: '200px 0px',
                    threshold: 0.01
                };
                
                var observer = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            
                            // Si l'image a un attribut data-src, le charger
                            if (img.getAttribute('data-src')) {
                                img.src = img.getAttribute('data-src');
                                img.removeAttribute('data-src');
                            }
                            
                            // Si l'image a un attribut data-srcset, le charger
                            if (img.getAttribute('data-srcset')) {
                                img.srcset = img.getAttribute('data-srcset');
                                img.removeAttribute('data-srcset');
                            }
                            
                            // Ne plus observer cette image
                            observer.unobserve(img);
                        }
                    });
                }, options);
                
                // Observer toutes les images avec un attribut data-src
                document.querySelectorAll('img[data-src]').forEach(function(img) {
                    observer.observe(img);
                });
            } else {
                // Fallback pour les navigateurs qui ne supportent pas l'Intersection Observer
                document.querySelectorAll('img[data-src]').forEach(function(img) {
                    img.src = img.getAttribute('data-src');
                    img.removeAttribute('data-src');
                    
                    if (img.getAttribute('data-srcset')) {
                        img.srcset = img.getAttribute('data-srcset');
                        img.removeAttribute('data-srcset');
                    }
                });
            }
        },
        
        /**
         * Configuration des gestionnaires de vidéos
         */
        setupVideoHandlers: function() {
            // Si support de l'Intersection Observer pour charger les vidéos à la demande
            if (this.hasIntersectionObserver) {
                var options = {
                    rootMargin: '100px 0px',
                    threshold: 0.1
                };
                
                var observer = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var video = entry.target;
                            
                            // Si la vidéo a une source différée
                            if (video.getAttribute('data-src')) {
                                // Configurer la source
                                var source = document.createElement('source');
                                source.src = video.getAttribute('data-src');
                                source.type = video.getAttribute('data-type') || 'video/mp4';
                                
                                // Ajouter la source et charger la vidéo
                                video.appendChild(source);
                                video.load();
                                video.removeAttribute('data-src');
                                video.removeAttribute('data-type');
                                
                                // Lecture automatique si indiqué
                                if (video.hasAttribute('data-autoplay')) {
                                    // Vérifier si la lecture automatique est autorisée
                                    video.muted = true; // Nécessaire pour l'autoplay sur la plupart des navigateurs
                                    var playPromise = video.play();
                                    
                                    if (playPromise !== undefined) {
                                        playPromise.catch(function(error) {
                                            console.log('Autoplay prevented:', error);
                                        });
                                    }
                                }
                            }
                            
                            // Ne plus observer cette vidéo
                            observer.unobserve(video);
                        }
                    });
                }, options);
                
                // Observer toutes les vidéos avec un attribut data-src
                document.querySelectorAll('video[data-src]').forEach(function(video) {
                    observer.observe(video);
                });
            }
            
            // Gestion des miniatures de vidéos YouTube
            $('.youtube-thumbnail').on('click', function() {
                var $this = $(this);
                var videoId = $this.data('video-id');
                
                // Créer l'iframe et remplacer la miniature
                var iframe = $('<iframe>', {
                    src: 'https://www.youtube.com/embed/' + videoId + '?autoplay=1',
                    frameborder: 0,
                    allow: 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture',
                    allowfullscreen: true
                });
                
                $this.replaceWith(iframe);
            });
        },
        
        /**
         * Configuration spécifique pour le logo
         */
        setupLogoHandling: function() {
            // Adapter le logo en fonction de la taille d'écran
            var handleLogoResize = function() {
                var windowWidth = window.innerWidth;
                var logo = document.querySelector('.site-logo');
                
                if (logo) {
                    // Réduire le logo sur les petits écrans
                    if (windowWidth < 768) {
                        if (!logo.hasAttribute('data-original-width')) {
                            logo.setAttribute('data-original-width', logo.style.maxWidth || 'auto');
                        }
                        logo.style.maxWidth = '160px';
                    } else {
                        // Restaurer la taille d'origine
                        if (logo.hasAttribute('data-original-width')) {
                            logo.style.maxWidth = logo.getAttribute('data-original-width');
                        }
                    }
                    
                    // Gérer le logo en mode sombre
                    var isDarkHeader = logo.closest('.dark-header') !== null;
                    if (isDarkHeader) {
                        logo.classList.add('white-version');
                    } else {
                        logo.classList.remove('white-version');
                    }
                }
            };
            
            // Exécuter au chargement et au redimensionnement
            handleLogoResize();
            $(window).on('resize', handleLogoResize);
            
            // Observer les changements de thème
            if (window.matchMedia) {
                var darkModeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                darkModeMediaQuery.addListener(function(e) {
                    handleLogoResize();
                });
            }
        },
        
        /**
         * Configuration des images d'arrière-plan
         */
        setupBackgroundImages: function() {
            // Si le navigateur supporte l'Intersection Observer pour charger les images de fond à la demande
            if (this.hasIntersectionObserver) {
                var options = {
                    rootMargin: '200px 0px',
                    threshold: 0.01
                };
                
                var observer = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var element = entry.target;
                            var src = element.getAttribute('data-bg');
                            
                            if (src) {
                                element.style.backgroundImage = 'url(' + src + ')';
                                element.removeAttribute('data-bg');
                                
                                // Ajouter une classe lorsque l'image est chargée
                                var img = new Image();
                                img.onload = function() {
                                    element.classList.add('bg-loaded');
                                };
                                img.src = src;
                                
                                // Ne plus observer cet élément
                                observer.unobserve(element);
                            }
                        }
                    });
                }, options);
                
                // Observer tous les éléments avec un attribut data-bg
                document.querySelectorAll('[data-bg]').forEach(function(element) {
                    observer.observe(element);
                });
            } else {
                // Fallback pour les navigateurs qui ne supportent pas l'Intersection Observer
                document.querySelectorAll('[data-bg]').forEach(function(element) {
                    var src = element.getAttribute('data-bg');
                    if (src) {
                        element.style.backgroundImage = 'url(' + src + ')';
                        element.removeAttribute('data-bg');
                        element.classList.add('bg-loaded');
                    }
                });
            }
        }
    };
    
    // Initialiser le gestionnaire de médias au chargement du document
    $(document).ready(function() {
        LifeTravelMedia.init();
        
        // Initialiser les galeries d'images si lightbox est présent
        if ($.fn.lightbox) {
            $('.gallery-image a').lightbox({
                gallery: true,
                loop: true
            });
        }
    });
    
})(jQuery);
