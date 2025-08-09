/**
 * Module Core - Life Travel Excursion
 * 
 * Fonctionnalités de base requises par tous les autres modules
 * Version optimisée pour les connexions lentes (Cameroun)
 */

(function($) {
    'use strict';
    
    // Namespace global
    window.LifeTravel = window.LifeTravel || {};
    
    // Configuration
    LifeTravel.config = {
        ajaxUrl: lifeTravel.ajax_url || '',
        nonce: lifeTravel.nonce || '',
        isMobile: /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent),
        networkStatus: 'normal'
    };
    
    // API de base
    LifeTravel.core = {
        /**
         * Détecter l'état actuel du réseau
         * @return {string} État du réseau (normal, slow, very_slow, offline)
         */
        detectNetworkStatus: function() {
            // Utiliser le cookie s'il existe
            var statusCookie = LifeTravel.core.getCookie('lte_network_status');
            if (statusCookie) {
                LifeTravel.config.networkStatus = statusCookie;
                return statusCookie;
            }
            
            // Détection basique si pas de cookie
            if (!navigator.onLine) {
                LifeTravel.config.networkStatus = 'offline';
                return 'offline';
            }
            
            return 'normal';
        },
        
        /**
         * Obtenir un cookie par son nom
         * @param {string} name Nom du cookie
         * @return {string|null} Valeur du cookie ou null
         */
        getCookie: function(name) {
            var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return match ? match[2] : null;
        },
        
        /**
         * Définir un cookie
         * @param {string} name Nom du cookie
         * @param {string} value Valeur du cookie
         * @param {number} days Durée de vie en jours
         */
        setCookie: function(name, value, days) {
            var expires = '';
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            document.cookie = name + '=' + value + expires + '; path=/';
        },
        
        /**
         * Charger un module JS à la demande
         * @param {string} moduleName Nom du module
         * @param {Function} callback Fonction de rappel après chargement
         */
        loadModule: function(moduleName, callback) {
            // Vérifier si le module est déjà chargé
            if (LifeTravel[moduleName]) {
                if (callback) callback();
                return;
            }
            
            // Vérifier l'état du réseau
            if (LifeTravel.config.networkStatus === 'offline') {
                console.warn('Réseau hors ligne, impossible de charger le module: ' + moduleName);
                return;
            }
            
            // En mode très lent, informer l'utilisateur
            if (LifeTravel.config.networkStatus === 'very_slow') {
                LifeTravel.core.showLoadingMessage('Chargement du module ' + moduleName + '...');
            }
            
            // Tenter de charger le module via AJAX pour éviter une nouvelle requête HTTP
            $.ajax({
                url: LifeTravel.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'life_travel_load_module',
                    module: moduleName,
                    nonce: LifeTravel.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.content) {
                        // Exécuter le code du module
                        try {
                            var moduleFunc = new Function(response.data.content);
                            moduleFunc();
                            
                            if (callback) callback();
                        } catch (e) {
                            console.error('Erreur lors du chargement du module ' + moduleName, e);
                            // Fallback: charger via script tag
                            LifeTravel.core.loadScript('modules/' + moduleName + '.js', callback);
                        }
                    } else {
                        // Fallback: charger via script tag
                        LifeTravel.core.loadScript('modules/' + moduleName + '.js', callback);
                    }
                    
                    LifeTravel.core.hideLoadingMessage();
                },
                error: function() {
                    // Fallback: charger via script tag
                    LifeTravel.core.loadScript('modules/' + moduleName + '.js', callback);
                    LifeTravel.core.hideLoadingMessage();
                }
            });
        },
        
        /**
         * Charger un script via une balise script
         * @param {string} src Chemin du script (relatif au répertoire assets/js/)
         * @param {Function} callback Fonction de rappel
         */
        loadScript: function(src, callback) {
            var script = document.createElement('script');
            script.src = lifeTravel.assets_url + '/js/' + src;
            script.async = true;
            
            script.onload = function() {
                if (callback) callback();
            };
            
            script.onerror = function() {
                console.error('Erreur de chargement du script: ' + src);
                LifeTravel.core.showError('Erreur de chargement. Veuillez rafraîchir la page ou réessayer plus tard.');
            };
            
            document.body.appendChild(script);
        },
        
        /**
         * Afficher un message de chargement
         * @param {string} message Message à afficher
         */
        showLoadingMessage: function(message) {
            // Créer la notification si elle n'existe pas
            if (!$('#lte-loading-notification').length) {
                $('body').append('<div id="lte-loading-notification"></div>');
            }
            
            $('#lte-loading-notification')
                .html('<div class="spinner"></div><span>' + message + '</span>')
                .addClass('active');
                
            // Masquer automatiquement après 10 secondes
            setTimeout(function() {
                LifeTravel.core.hideLoadingMessage();
            }, 10000);
        },
        
        /**
         * Masquer le message de chargement
         */
        hideLoadingMessage: function() {
            $('#lte-loading-notification').removeClass('active');
        },
        
        /**
         * Afficher un message d'erreur
         * @param {string} message Message d'erreur
         */
        showError: function(message) {
            // Créer la notification si elle n'existe pas
            if (!$('#lte-error-notification').length) {
                $('body').append('<div id="lte-error-notification"></div>');
            }
            
            $('#lte-error-notification')
                .html('<div class="error-icon"></div><span>' + message + '</span>')
                .addClass('active');
                
            // Masquer automatiquement après 5 secondes
            setTimeout(function() {
                $('#lte-error-notification').removeClass('active');
            }, 5000);
        },
        
        /**
         * Formater un prix
         * @param {number} price Prix à formater
         * @return {string} Prix formaté
         */
        formatPrice: function(price) {
            return price.toLocaleString('fr-FR', {
                style: 'currency',
                currency: 'XAF',
                minimumFractionDigits: 0
            });
        }
    };
    
    // Initialisation au chargement du DOM
    $(function() {
        // Détecter l'état du réseau
        LifeTravel.core.detectNetworkStatus();
        
        // Ajouter le CSS pour les notifications
        $('head').append(
            '<style>' +
            '#lte-loading-notification, #lte-error-notification {' +
            '  position: fixed;' +
            '  bottom: 20px;' +
            '  left: 20px;' +
            '  background: rgba(0,0,0,0.8);' +
            '  color: white;' +
            '  padding: 10px 15px;' +
            '  border-radius: 4px;' +
            '  z-index: 9999;' +
            '  display: flex;' +
            '  align-items: center;' +
            '  transform: translateY(100px);' +
            '  opacity: 0;' +
            '  transition: all 0.3s ease;' +
            '}' +
            '#lte-loading-notification.active, #lte-error-notification.active {' +
            '  transform: translateY(0);' +
            '  opacity: 1;' +
            '}' +
            '#lte-loading-notification .spinner {' +
            '  width: 20px;' +
            '  height: 20px;' +
            '  border: 2px solid #fff;' +
            '  border-top-color: transparent;' +
            '  border-radius: 50%;' +
            '  margin-right: 10px;' +
            '  animation: lte-spin 1s linear infinite;' +
            '}' +
            '@keyframes lte-spin {' +
            '  to { transform: rotate(360deg); }' +
            '}' +
            '</style>'
        );
        
        // Écouter les changements d'état de réseau
        window.addEventListener('online', function() {
            if (LifeTravel.config.networkStatus === 'offline') {
                LifeTravel.config.networkStatus = 'normal';
                LifeTravel.core.setCookie('lte_network_status', 'normal', 1);
                LifeTravel.core.showLoadingMessage('Connexion rétablie. Rechargement des données...');
                
                // Recharger les données contextuelles
                setTimeout(function() {
                    LifeTravel.core.hideLoadingMessage();
                }, 1500);
            }
        });
        
        window.addEventListener('offline', function() {
            LifeTravel.config.networkStatus = 'offline';
            LifeTravel.core.setCookie('lte_network_status', 'offline', 1);
            LifeTravel.core.showError('Connexion perdue. Passage en mode hors ligne.');
        });
    });
    
})(jQuery);
