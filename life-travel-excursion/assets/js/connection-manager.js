/**
 * Gestionnaire de connexion pour Life Travel
 * Optimisé pour les connexions lentes (Cameroun) et l'adaptabilité mobile
 * @package Life Travel
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    // Objet principal du gestionnaire de connexion
    var LifeTravelConnectionManager = {
        /**
         * Configuration par défaut
         */
        config: {
            slowConnectionThreshold: 1000, // en ms
            checkInterval: 30000, // Vérifier la connexion toutes les 30 secondes
            imageQualityLevels: {
                high: { maxWidth: 1920, quality: 80 },
                medium: { maxWidth: 1200, quality: 70 },
                low: { maxWidth: 800, quality: 60 },
                ultraLow: { maxWidth: 400, quality: 50 }
            },
            offlineMode: {
                enabled: true,
                cacheDuration: 7 // jours
            }
        },
        
        /**
         * Initialisation du gestionnaire
         */
        init: function() {
            // État de la connexion
            this.connectionStatus = {
                online: navigator.onLine,
                speed: 'unknown', // 'fast', 'medium', 'slow'
                lastChecked: Date.now()
            };
            
            // Initialiser l'interface pour la connexion
            this.initUI();
            
            // Configurer les événements de connexion
            this.setupConnectionEvents();
            
            // Tester la vitesse de connexion initiale
            this.checkConnectionSpeed();
            
            // Configurer la gestion des médias en fonction de la connexion
            this.setupMediaHandling();
            
            // Configurer l'adaptabilité mobile
            this.setupMobileAdaptability();
        },
        
        /**
         * Initialiser l'interface utilisateur
         */
        initUI: function() {
            // Indicateur de statut de connexion
            $('body').append('<div class="connection-status">Vérification...</div>');
            this.$statusElement = $('.connection-status');
            
            // Message hors ligne pour toutes les pages
            $('.site-main').prepend('<div class="offline-message">Vous êtes actuellement en mode hors ligne. Certaines fonctionnalités peuvent être limitées.</div>');
            this.$offlineMessage = $('.offline-message');
            
            // Masquer le message hors ligne par défaut
            if (navigator.onLine) {
                this.$offlineMessage.hide();
            }
        },
        
        /**
         * Configurer les événements de connexion
         */
        setupConnectionEvents: function() {
            var self = this;
            
            // Événements en ligne/hors ligne
            window.addEventListener('online', function() {
                self.connectionStatus.online = true;
                self.updateConnectionUI();
                self.checkConnectionSpeed();
            });
            
            window.addEventListener('offline', function() {
                self.connectionStatus.online = false;
                self.connectionStatus.speed = 'offline';
                self.updateConnectionUI();
            });
            
            // Vérifier la connexion périodiquement
            setInterval(function() {
                if (navigator.onLine) {
                    self.checkConnectionSpeed();
                }
            }, this.config.checkInterval);
        },
        
        /**
         * Vérifier la vitesse de connexion
         */
        checkConnectionSpeed: function() {
            var self = this;
            var startTime = Date.now();
            
            // Image de test légère (1 KB)
            var testImage = new Image();
            var testUrl = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
            
            // Événement de chargement
            testImage.onload = function() {
                var endTime = Date.now();
                var duration = endTime - startTime;
                
                // Déterminer la vitesse de connexion
                if (duration < 200) {
                    self.connectionStatus.speed = 'fast';
                } else if (duration < self.config.slowConnectionThreshold) {
                    self.connectionStatus.speed = 'medium';
                } else {
                    self.connectionStatus.speed = 'slow';
                }
                
                self.connectionStatus.lastChecked = endTime;
                self.updateConnectionUI();
                
                // Adapter les médias en fonction de la vitesse
                self.adaptMediaToConnection();
            };
            
            // Erreur de chargement
            testImage.onerror = function() {
                self.connectionStatus.online = false;
                self.connectionStatus.speed = 'offline';
                self.updateConnectionUI();
            };
            
            // Lancer le test
            this.$statusElement.text('Vérification...');
            testImage.src = testUrl + '?t=' + Date.now(); // Éviter le cache
        },
        
        /**
         * Mettre à jour l'interface utilisateur en fonction de l'état de connexion
         */
        updateConnectionUI: function() {
            // Mettre à jour l'indicateur de statut
            this.$statusElement.removeClass('online offline slow');
            
            if (!this.connectionStatus.online) {
                this.$statusElement.addClass('offline').text('Hors ligne');
                $('body').addClass('offline-mode');
                this.$offlineMessage.show();
            } else {
                $('body').removeClass('offline-mode');
                this.$offlineMessage.hide();
                
                if (this.connectionStatus.speed === 'slow') {
                    this.$statusElement.addClass('slow').text('Connexion lente');
                    $('body').addClass('low-bandwidth-mode');
                } else {
                    this.$statusElement.addClass('online').text('En ligne');
                    
                    // Seulement supprimer le mode basse bande passante si la vitesse est rapide
                    if (this.connectionStatus.speed === 'fast') {
                        $('body').removeClass('low-bandwidth-mode');
                    }
                }
            }
        },
        
        /**
         * Adapter les médias en fonction de la vitesse de connexion
         */
        adaptMediaToConnection: function() {
            var self = this;
            
            // Si connexion lente, adapter les images
            if (this.connectionStatus.speed === 'slow') {
                // Remplacer les vidéos par des images fixes
                $('video:not([data-connection-managed])').each(function() {
                    var $video = $(this);
                    $video.attr('data-connection-managed', 'true');
                    
                    // Sauvegarder les sources originales
                    if (!$video.attr('data-original-sources')) {
                        var sources = [];
                        $video.find('source').each(function() {
                            sources.push({
                                src: $(this).attr('src'),
                                type: $(this).attr('type')
                            });
                        });
                        $video.attr('data-original-sources', JSON.stringify(sources));
                    }
                    
                    // Utiliser une image à la place si la vidéo n'est pas critique
                    if (!$video.hasClass('critical-video')) {
                        var posterUrl = $video.attr('poster');
                        var $img = $('<img>', {
                            src: posterUrl || '/assets/img/backgrounds/video-placeholder.svg',
                            alt: 'Vidéo non chargée (connexion lente)',
                            class: 'video-placeholder',
                            width: $video.attr('width'),
                            height: $video.attr('height')
                        });
                        
                        var $playButton = $('<div>', {
                            class: 'video-play-button',
                            html: '<svg viewBox="0 0 24 24" width="64" height="64"><path fill="#ffffff" d="M8 5v14l11-7z"/></svg>'
                        });
                        
                        var $container = $('<div>', {
                            class: 'video-container slow-connection',
                            'data-video-id': $video.attr('id')
                        }).append($img).append($playButton);
                        
                        // Remplacer la vidéo par le conteneur
                        $video.after($container).hide();
                        
                        // Cliquer sur le bouton pour charger la vidéo
                        $playButton.on('click', function() {
                            var videoId = $container.data('video-id');
                            var $videoEl = $('#' + videoId);
                            
                            // Restaurer les sources originales
                            if ($videoEl.attr('data-original-sources')) {
                                var originalSources = JSON.parse($videoEl.attr('data-original-sources'));
                                $videoEl.empty();
                                
                                $.each(originalSources, function(i, source) {
                                    $('<source>', {
                                        src: source.src,
                                        type: source.type
                                    }).appendTo($videoEl);
                                });
                                
                                $videoEl.load();
                            }
                            
                            // Afficher la vidéo et masquer le conteneur
                            $videoEl.show();
                            $container.hide();
                            
                            // Lire la vidéo
                            setTimeout(function() {
                                $videoEl[0].play();
                            }, 100);
                        });
                    }
                });
                
                // Adapter la qualité des images
                this.setImagesQuality('low');
                
                // Désactiver le préchargement des ressources non critiques
                $('link[rel="preload"]:not([data-critical="true"])').each(function() {
                    $(this).attr('rel', 'prefetch');
                });
            } else if (this.connectionStatus.speed === 'medium') {
                // Qualité moyenne pour les connexions moyennes
                this.setImagesQuality('medium');
            } else {
                // Haute qualité pour les connexions rapides
                this.setImagesQuality('high');
                
                // Restaurer les préchargements
                $('link[rel="prefetch"][data-critical="false"]').each(function() {
                    $(this).attr('rel', 'preload');
                });
            }
        },
        
        /**
         * Définir la qualité des images en fonction de la vitesse de connexion
         * @param {string} qualityLevel - Niveau de qualité ('high', 'medium', 'low', 'ultraLow')
         */
        setImagesQuality: function(qualityLevel) {
            var settings = this.config.imageQualityLevels[qualityLevel];
            
            // Mettre à jour les attributs srcset pour les images responsives qui ne sont pas encore chargées
            $('img[data-srcset]:not([srcset]):not(.quality-managed)').each(function() {
                var $img = $(this);
                $img.addClass('quality-managed');
                
                var srcset = $img.attr('data-srcset');
                var newSrcset = srcset.split(',').filter(function(src) {
                    // Filtrer les résolutions trop grandes
                    var width = parseInt(src.match(/\s(\d+)w/)[1], 10);
                    return width <= settings.maxWidth;
                }).join(',');
                
                $img.attr('srcset', newSrcset);
            });
            
            // Ajouter un paramètre de qualité aux images à charger
            $('img[data-src]:not([src]):not(.quality-managed)').each(function() {
                var $img = $(this);
                $img.addClass('quality-managed');
                
                var src = $img.attr('data-src');
                // Ajouter un paramètre de qualité si l'URL pointe vers notre site
                if (src.indexOf(window.location.hostname) > -1 || src.indexOf('/') === 0) {
                    var separator = src.indexOf('?') > -1 ? '&' : '?';
                    src = src + separator + 'q=' + settings.quality;
                }
                
                $img.attr('data-src', src);
            });
        },
        
        /**
         * Configurer la gestion des médias en fonction de la connexion
         */
        setupMediaHandling: function() {
            // Observer les images pour le chargement progressif
            if ('IntersectionObserver' in window) {
                var options = {
                    rootMargin: '200px 0px',
                    threshold: 0.01
                };
                
                var observer = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var $target = $(entry.target);
                            
                            // Images progressives
                            if ($target.hasClass('progressive-image') && $target.attr('data-src')) {
                                var img = new Image();
                                img.onload = function() {
                                    $target.attr('src', img.src);
                                    $target.addClass('loaded');
                                };
                                img.src = $target.attr('data-src');
                                $target.removeAttr('data-src');
                            }
                            
                            // Images standards
                            else if ($target.attr('data-src')) {
                                $target.attr('src', $target.attr('data-src'));
                                $target.removeAttr('data-src');
                                $target.addClass('loaded');
                            }
                            
                            observer.unobserve(entry.target);
                        }
                    });
                }, options);
                
                // Observer toutes les images avec un attribut data-src
                document.querySelectorAll('img[data-src]').forEach(function(img) {
                    observer.observe(img);
                });
            }
            // Fallback pour les navigateurs qui ne supportent pas IntersectionObserver
            else {
                $('img[data-src]').each(function() {
                    $(this).attr('src', $(this).attr('data-src'));
                    $(this).removeAttr('data-src');
                    $(this).addClass('loaded');
                });
            }
        },
        
        /**
         * Configurer l'adaptabilité mobile
         */
        setupMobileAdaptability: function() {
            var self = this;
            
            // Détection de l'orientation de l'appareil
            var checkOrientation = function() {
                if (window.innerHeight > window.innerWidth) {
                    // Mode portrait
                    $('body').removeClass('landscape-mode').addClass('portrait-mode');
                } else {
                    // Mode paysage
                    $('body').removeClass('portrait-mode').addClass('landscape-mode');
                }
            };
            
            // Vérification initiale
            checkOrientation();
            
            // Vérifier à chaque changement d'orientation
            window.addEventListener('orientationchange', checkOrientation);
            window.addEventListener('resize', checkOrientation);
            
            // Adaptations pour les écrans tactiles
            if ('ontouchstart' in window) {
                $('body').addClass('touch-device');
                
                // Améliorer la zone de clic pour les éléments interactifs
                $('.menu-item > a, .button, .social-link, .gallery-image a').each(function() {
                    var $element = $(this);
                    var minTargetSize = 44; // Taille minimale recommandée en pixels
                    
                    var width = $element.outerWidth();
                    var height = $element.outerHeight();
                    
                    // Si l'élément est trop petit, augmenter sa zone tactile
                    if (width < minTargetSize || height < minTargetSize) {
                        $element.css({
                            padding: Math.max(
                                parseInt($element.css('padding-top') || 0, 10),
                                Math.ceil((minTargetSize - height) / 2)
                            ) + 'px ' + Math.max(
                                parseInt($element.css('padding-right') || 0, 10),
                                Math.ceil((minTargetSize - width) / 2)
                            ) + 'px'
                        });
                    }
                });
            }
        }
    };
    
    // Initialiser le gestionnaire de connexion au chargement du document
    $(document).ready(function() {
        LifeTravelConnectionManager.init();
    });
    
})(jQuery);
