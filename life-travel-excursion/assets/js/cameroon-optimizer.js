/**
 * Life Travel - Optimiseur pour réseaux camerounais
 * 
 * Ce script gère des optimisations spécifiques pour les connexions lentes typiques du Cameroun:
 * - Chargement dynamique des modules JS
 * - Mise en cache agressive des ressources
 * - Stratégies de chargement adaptatif basé sur la qualité de connexion
 * - Gestion des ressources en mode hors ligne
 * 
 * @package Life Travel Excursion
 * @version 3.0.0
 */

(function($) {
    'use strict';
    
    // Objet principal
    var CameroonOptimizer = {
        // Configuration
        config: {
            networkStatus: lteCameroonConfig.networkStatus || 'normal',
            deferredScripts: lteCameroonConfig.deferredScripts || [],
            moduleMap: lteCameroonConfig.moduleMap || {},
            ajaxUrl: lteCameroonConfig.ajaxUrl || '',
            nonce: lteCameroonConfig.nonce || '',
            downloadedModules: []
        },
        
        // Initialisation
        init: function() {
            // Vérifier si nous sommes sur une page d'excursion
            if ($('.life-travel-excursion-form').length > 0) {
                this.optimizeExcursionPage();
            }
            
            // Activer le chargement intelligent des ressources
            this.setupIntelligentLoading();
            
            // Observer l'interaction utilisateur pour charger les modules au besoin
            this.setupInteractionObserver();
            
            // Activer le mode hors ligne si nécessaire
            if (this.config.networkStatus === 'offline') {
                this.enableOfflineMode();
            }
            
            // Écouter les changements de statut réseau
            window.addEventListener('online', this.handleOnlineStatus.bind(this));
            window.addEventListener('offline', this.handleOfflineStatus.bind(this));
        },
        
        // Optimiser la page d'excursion
        optimizeExcursionPage: function() {
            var self = this;
            
            // Charger immédiatement le calculateur de prix si le formulaire est présent
            if ($('.price-breakdown').length > 0) {
                this.loadModule('price-calculator', function() {
                    console.log('Module price-calculator chargé avec succès');
                });
            }
            
            // Retarder le chargement des extras jusqu'à ce qu'ils soient visibles
            if ($('.extras-section').length > 0) {
                this.loadOnVisible('.extras-section', 'extras-manager');
            }
            
            // Charger les activités uniquement lorsqu'elles sont visibles
            if ($('.activities-section').length > 0) {
                this.loadOnVisible('.activities-section', 'activities-selector');
            }
            
            // Optimiser les images
            this.optimizeImages();
        },
        
        // Charger un module JS
        loadModule: function(moduleName, callback) {
            // Vérifier si le module existe dans la carte
            if (!this.config.moduleMap[moduleName]) {
                console.error('Module inconnu: ' + moduleName);
                return;
            }
            
            // Vérifier si le module est déjà chargé
            if (this.config.downloadedModules.indexOf(moduleName) !== -1) {
                if (callback) callback();
                return;
            }
            
            var self = this;
            
            // En mode hors ligne, vérifier si le module est en cache
            if (this.config.networkStatus === 'offline') {
                var cachedModule = localStorage.getItem('lte_module_' + moduleName);
                if (cachedModule) {
                    try {
                        var moduleFunc = new Function(cachedModule);
                        moduleFunc();
                        this.config.downloadedModules.push(moduleName);
                        if (callback) callback();
                        return;
                    } catch (e) {
                        console.error('Erreur lors du chargement du module ' + moduleName + ' depuis le cache', e);
                    }
                }
                
                console.warn('Module ' + moduleName + ' non disponible en mode hors ligne');
                return;
            }
            
            // Charger le module via AJAX ou via le système standard
            if (this.config.networkStatus === 'very_slow' || this.config.networkStatus === 'slow') {
                // Pour les connexions lentes, charger via AJAX pour compression
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'life_travel_load_module',
                        module: moduleName,
                        nonce: this.config.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.content) {
                            try {
                                var moduleFunc = new Function(response.data.content);
                                moduleFunc();
                                self.config.downloadedModules.push(moduleName);
                                
                                // Mettre en cache pour une utilisation hors ligne
                                try {
                                    localStorage.setItem('lte_module_' + moduleName, response.data.content);
                                } catch (e) {
                                    console.warn('Impossible de mettre en cache le module ' + moduleName, e);
                                }
                                
                                if (callback) callback();
                            } catch (e) {
                                console.error('Erreur lors de l\'exécution du module ' + moduleName, e);
                                self.loadScriptTag(moduleName, callback);
                            }
                        } else {
                            self.loadScriptTag(moduleName, callback);
                        }
                    },
                    error: function() {
                        self.loadScriptTag(moduleName, callback);
                    }
                });
            } else {
                // Pour les connexions normales, charger directement le script
                this.loadScriptTag(moduleName, callback);
            }
        },
        
        // Charger un script via une balise script
        loadScriptTag: function(moduleName, callback) {
            var moduleInfo = this.config.moduleMap[moduleName];
            if (!moduleInfo) return;
            
            var script = document.createElement('script');
            script.src = moduleInfo.path;
            script.async = true;
            
            script.onload = function() {
                CameroonOptimizer.config.downloadedModules.push(moduleName);
                if (callback) callback();
            };
            
            script.onerror = function() {
                console.error('Erreur lors du chargement du module ' + moduleName);
            };
            
            document.body.appendChild(script);
        },
        
        // Configurer le chargement intelligent des ressources
        setupIntelligentLoading: function() {
            var self = this;
            
            // Attendre que la page soit entièrement chargée
            $(window).on('load', function() {
                // Charger les scripts différés après un délai
                setTimeout(function() {
                    self.loadDeferredScripts();
                }, 3000);
            });
        },
        
        // Charger les scripts différés
        loadDeferredScripts: function() {
            // Ne pas charger en mode hors ligne
            if (this.config.networkStatus === 'offline') {
                return;
            }
            
            var self = this;
            
            // Charger chaque script séquentiellement
            var loadScript = function(index) {
                if (index >= self.config.deferredScripts.length) return;
                
                var script = self.config.deferredScripts[index];
                
                var scriptElement = document.createElement('script');
                scriptElement.src = script.startsWith('http') ? script : '/wp-content/plugins/life-travel-excursion/assets/js/' + script + '.js';
                scriptElement.async = true;
                
                scriptElement.onload = function() {
                    console.log('Script différé chargé: ' + script);
                    loadScript(index + 1);
                };
                
                scriptElement.onerror = function() {
                    console.error('Erreur lors du chargement du script: ' + script);
                    loadScript(index + 1);
                };
                
                document.body.appendChild(scriptElement);
            };
            
            // Commencer le chargement
            loadScript(0);
        },
        
        // Charger un module lorsqu'un élément devient visible
        loadOnVisible: function(selector, moduleName) {
            if (!window.IntersectionObserver) {
                // Si IntersectionObserver n'est pas supporté, charger immédiatement
                this.loadModule(moduleName);
                return;
            }
            
            var self = this;
            var element = document.querySelector(selector);
            
            if (!element) return;
            
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        self.loadModule(moduleName);
                        observer.disconnect();
                    }
                });
            }, { threshold: 0.1 });
            
            observer.observe(element);
        },
        
        // Observer les interactions de l'utilisateur pour charger les modules pertinents
        setupInteractionObserver: function() {
            var self = this;
            
            // Charger le module de calculateur de prix lors de la modification du formulaire
            $('.life-travel-excursion-form').on('change', 'input, select', function() {
                self.loadModule('price-calculator');
            });
            
            // Charger le module d'extras lors du clic sur les extras
            $('.extras-section').on('click', '.extra-item', function() {
                self.loadModule('extras-manager');
            });
            
            // Charger le module d'activités lors du clic sur les activités
            $('.activities-section').on('click', '.activity-item', function() {
                self.loadModule('activities-selector');
            });
        },
        
        // Optimiser les images
        optimizeImages: function() {
            // Remplacer les images par des versions webp si le navigateur les supporte
            var supportsWebP = (function() {
                var canvas = document.createElement('canvas');
                if (canvas.getContext && canvas.getContext('2d')) {
                    return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
                }
                return false;
            })();
            
            if (supportsWebP) {
                $('img[data-webp]').each(function() {
                    var webpSrc = $(this).data('webp');
                    if (webpSrc) {
                        $(this).attr('src', webpSrc);
                    }
                });
            }
            
            // Lazy loading pour les images hors écran
            this.setupLazyLoading();
        },
        
        // Configurer le lazy loading des images
        setupLazyLoading: function() {
            if (!window.IntersectionObserver) {
                return;
            }
            
            var imageObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        var src = img.getAttribute('data-src');
                        
                        if (src) {
                            img.setAttribute('src', src);
                            img.removeAttribute('data-src');
                        }
                        
                        imageObserver.unobserve(img);
                    }
                });
            }, { rootMargin: '200px 0px' });
            
            document.querySelectorAll('img[data-src]').forEach(function(img) {
                imageObserver.observe(img);
            });
        },
        
        // Activer le mode hors ligne
        enableOfflineMode: function() {
            console.log('Activation du mode hors ligne');
            
            // Notifier l'utilisateur
            this.showNotification('Mode hors ligne activé. Certaines fonctionnalités peuvent être limitées.');
            
            // Désactiver les fonctionnalités qui nécessitent une connexion
            $('.online-only').hide();
            $('.offline-notice').show();
            
            // Désactiver les formulaires qui nécessitent AJAX
            $('form.requires-network').each(function() {
                $(this).addClass('disabled');
                $(this).find('input, button, select').prop('disabled', true);
                $(this).prepend('<div class="offline-notice">Non disponible en mode hors ligne</div>');
            });
        },
        
        // Gérer le passage en ligne
        handleOnlineStatus: function() {
            console.log('Connexion réseau rétablie');
            
            // Mettre à jour le statut du réseau
            this.config.networkStatus = 'normal';
            
            // Notifier l'utilisateur
            this.showNotification('Connexion internet rétablie. Rafraîchissez la page pour accéder à toutes les fonctionnalités.');
            
            // Réactiver les fonctionnalités
            $('.online-only').show();
            $('.offline-notice').hide();
            
            // Réactiver les formulaires
            $('form.requires-network').each(function() {
                $(this).removeClass('disabled');
                $(this).find('input, button, select').prop('disabled', false);
                $(this).find('.offline-notice').remove();
            });
        },
        
        // Gérer le passage hors ligne
        handleOfflineStatus: function() {
            console.log('Connexion réseau perdue');
            
            // Mettre à jour le statut du réseau
            this.config.networkStatus = 'offline';
            
            // Activer le mode hors ligne
            this.enableOfflineMode();
        },
        
        // Afficher une notification à l'utilisateur
        showNotification: function(message) {
            // Créer la notification si elle n'existe pas déjà
            if ($('#cameroon-optimizer-notification').length === 0) {
                $('body').append('<div id="cameroon-optimizer-notification"></div>');
            }
            
            // Afficher la notification
            $('#cameroon-optimizer-notification')
                .html(message)
                .addClass('active');
            
            // Masquer après 5 secondes
            setTimeout(function() {
                $('#cameroon-optimizer-notification').removeClass('active');
            }, 5000);
        }
    };
    
    // Initialiser l'optimiseur
    $(function() {
        CameroonOptimizer.init();
        
        // Ajouter le CSS pour les notifications
        $('head').append(
            '<style>' +
            '#cameroon-optimizer-notification {' +
            '  position: fixed;' +
            '  bottom: 20px;' +
            '  right: 20px;' +
            '  background: rgba(0,0,0,0.8);' +
            '  color: #fff;' +
            '  padding: 15px 20px;' +
            '  border-radius: 4px;' +
            '  font-size: 14px;' +
            '  z-index: 9999;' +
            '  transform: translateY(100px);' +
            '  opacity: 0;' +
            '  transition: all 0.3s ease;' +
            '}' +
            '#cameroon-optimizer-notification.active {' +
            '  transform: translateY(0);' +
            '  opacity: 1;' +
            '}' +
            '.offline-notice {' +
            '  background: #f8d7da;' +
            '  color: #721c24;' +
            '  padding: 10px;' +
            '  margin-bottom: 15px;' +
            '  border-radius: 4px;' +
            '  display: none;' +
            '}' +
            '</style>'
        );
    });
    
})(jQuery);
