/**
 * Life Travel - Détecteur de qualité de connexion
 * 
 * Ce script analyse la qualité de la connexion internet de l'utilisateur
 * et adapte l'interface en conséquence. Il utilise plusieurs méthodes de détection:
 * 1. Navigation Timing API pour mesurer les performances réelles
 * 2. Network Information API (si disponible)
 * 3. Tests de téléchargement d'images
 * 4. Mode hors-ligne
 * 
 * Les niveaux de connexion sont:
 * - fast: Connexion rapide (< 200ms)
 * - medium: Connexion moyenne (200ms - 1000ms)
 * - slow: Connexion lente (> 1000ms)
 * - offline: Pas de connexion
 * 
 * @package Life Travel Excursion
 * @version 2.4.0
 */

(function($) {
    'use strict';

    // Objet principal
    var LifeTravelNetworkDetector = {
        // Configuration
        config: {
            testImageUrl: '/wp-includes/images/spinner.gif',
            testInterval: 60000, // Test toutes les minutes
            fastThreshold: lifeTravelNetwork.thresholds.fast || 200,
            mediumThreshold: lifeTravelNetwork.thresholds.medium || 1000,
            ajaxUrl: lifeTravelNetwork.ajaxurl,
            nonce: lifeTravelNetwork.nonce,
            currentLevel: lifeTravelNetwork.currentLevel || 'medium'
        },
        
        // État
        state: {
            connectionLevel: 'unknown',
            lastTestTime: 0,
            isTestRunning: false,
            testResults: [],
            previousLevel: null
        },
        
        // Initialisation
        init: function() {
            // Récupérer l'état actuel depuis le cookie ou l'objet lifeTravelNetwork
            this.state.connectionLevel = this.getCookie('life_travel_connection_level') || this.config.currentLevel;
            this.state.previousLevel = this.state.connectionLevel;
            
            // Lancer un test initial
            this.runConnectionTest();
            
            // Configurer les tests périodiques
            setInterval(this.runConnectionTest.bind(this), this.config.testInterval);
            
            // Écouter les changements de connectivité
            this.setupConnectivityListeners();
            
            // Appliquer les optimisations en fonction du niveau de connexion
            this.applyOptimizations();
        },
        
        // Exécuter un test de connexion
        runConnectionTest: function() {
            if (this.state.isTestRunning) {
                return;
            }
            
            this.state.isTestRunning = true;
            var startTime = new Date().getTime();
            this.state.lastTestTime = startTime;
            
            // Vérifier d'abord si le navigateur sait qu'il est hors-ligne
            if (!navigator.onLine) {
                this.setConnectionLevel('offline');
                this.state.isTestRunning = false;
                return;
            }
            
            // Test avec Network Information API si disponible
            if (this.testWithNetworkAPI()) {
                this.state.isTestRunning = false;
                return;
            }
            
            // Test avec Navigation Timing API
            if (this.testWithTimingAPI()) {
                this.state.isTestRunning = false;
                return;
            }
            
            // Fallback: Tester en téléchargeant une petite image
            var self = this;
            var img = new Image();
            
            img.onload = function() {
                var loadTime = new Date().getTime() - startTime;
                self.processTestResult(loadTime);
                self.state.isTestRunning = false;
            };
            
            img.onerror = function() {
                self.setConnectionLevel('offline');
                self.state.isTestRunning = false;
            };
            
            // Ajouter un timestamp pour éviter la mise en cache
            img.src = this.config.testImageUrl + '?t=' + startTime;
        },
        
        // Tester avec la Network Information API
        testWithNetworkAPI: function() {
            if (!navigator.connection) {
                return false;
            }
            
            var connection = navigator.connection;
            var level = 'medium'; // Niveau par défaut
            
            // Vérifier le mode économie de données
            if (connection.saveData) {
                this.setConnectionLevel('slow');
                return true;
            }
            
            // Vérifier le type de connexion
            if (connection.effectiveType) {
                switch (connection.effectiveType) {
                    case 'slow-2g':
                    case '2g':
                        level = 'slow';
                        break;
                    case '3g':
                        level = 'medium';
                        break;
                    case '4g':
                        level = 'fast';
                        break;
                }
                this.setConnectionLevel(level);
                return true;
            }
            
            return false;
        },
        
        // Tester avec la Navigation Timing API
        testWithTimingAPI: function() {
            if (!window.performance || !window.performance.timing) {
                return false;
            }
            
            var timing = window.performance.timing;
            var loadTime = timing.domContentLoadedEventEnd - timing.navigationStart;
            
            // Seulement utiliser si la page est complètement chargée et récente
            if (loadTime > 0 && (Date.now() - timing.navigationStart < 60000)) {
                this.processTestResult(loadTime);
                return true;
            }
            
            return false;
        },
        
        // Traiter le résultat d'un test de vitesse
        processTestResult: function(loadTime) {
            // Ajouter le résultat à l'historique (garder les 5 derniers)
            this.state.testResults.push(loadTime);
            if (this.state.testResults.length > 5) {
                this.state.testResults.shift();
            }
            
            // Calculer la moyenne des résultats
            var sum = this.state.testResults.reduce(function(a, b) { return a + b; }, 0);
            var avgLoadTime = sum / this.state.testResults.length;
            
            // Déterminer le niveau de connexion
            var level = 'medium';
            
            if (avgLoadTime < this.config.fastThreshold) {
                level = 'fast';
            } else if (avgLoadTime < this.config.mediumThreshold) {
                level = 'medium';
            } else {
                level = 'slow';
            }
            
            this.setConnectionLevel(level);
        },
        
        // Configurer les écouteurs de changement de connectivité
        setupConnectivityListeners: function() {
            var self = this;
            
            // Événement standard de changement de connectivité
            window.addEventListener('online', function() {
                self.runConnectionTest();
            });
            
            window.addEventListener('offline', function() {
                self.setConnectionLevel('offline');
            });
            
            // Écouteur pour Network Information API si disponible
            if (navigator.connection) {
                navigator.connection.addEventListener('change', function() {
                    self.testWithNetworkAPI() || self.runConnectionTest();
                });
            }
            
            // Écouteur pour les changements de visibilité (retour sur l'onglet)
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') {
                    self.runConnectionTest();
                }
            });
        },
        
        // Définir le niveau de connexion
        setConnectionLevel: function(level) {
            // Si le niveau n'a pas changé, ne rien faire
            if (this.state.connectionLevel === level) {
                return;
            }
            
            // Sauvegarder l'ancien niveau
            this.state.previousLevel = this.state.connectionLevel;
            
            // Mettre à jour le niveau
            this.state.connectionLevel = level;
            
            // Sauvegarder dans un cookie (expire après 1 heure)
            this.setCookie('life_travel_connection_level', level, 1);
            
            // Envoyer au serveur via AJAX
            this.sendToServer(level);
            
            // Appliquer les optimisations
            this.applyOptimizations();
            
            // Déclencher un événement pour les autres scripts
            $(document).trigger('life_travel_connection_change', [level, this.state.previousLevel]);
            
            console.log('Life Travel: Niveau de connexion mis à jour - ' + level);
        },
        
        // Envoyer le niveau de connexion au serveur
        sendToServer: function(level) {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'life_travel_update_connection_level',
                    level: level,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.reload) {
                        // Le serveur demande un rechargement (changement majeur)
                        window.location.reload();
                    }
                }
            });
        },
        
        // Appliquer les optimisations en fonction du niveau de connexion
        applyOptimizations: function() {
            var level = this.state.connectionLevel;
            
            // Ajouter une classe au body pour le CSS
            $('body')
                .removeClass('connection-fast connection-medium connection-slow connection-offline')
                .addClass('connection-' + level);
            
            // Ajustements spécifiques selon le niveau
            switch (level) {
                case 'slow':
                    this.applySlowOptimizations();
                    break;
                case 'offline':
                    this.applyOfflineOptimizations();
                    break;
            }
        },
        
        // Optimisations pour connexion lente
        applySlowOptimizations: function() {
            // Désactiver les animations
            $('body').addClass('reduce-motion');
            
            // Arrêter les vidéos en arrière-plan
            $('.background-video').each(function() {
                if (this.pause) {
                    this.pause();
                }
            });
            
            // Charger les images en basse qualité
            $('img:not(.processed-for-slow)').each(function() {
                var $img = $(this);
                
                // Ne pas traiter les images déjà chargées ou celles sans source
                if (!$img.attr('src') || $img.hasClass('processed-for-slow')) {
                    return;
                }
                
                // Marquer comme traitée
                $img.addClass('processed-for-slow');
                
                // Stocker la source originale
                $img.attr('data-high-quality', $img.attr('src'));
                
                // Si srcset est présent, le stocker aussi
                if ($img.attr('srcset')) {
                    $img.attr('data-high-quality-srcset', $img.attr('srcset'));
                    $img.removeAttr('srcset');
                }
                
                // Remplacer par une version basse qualité si disponible
                var lowQualitySrc = $img.attr('src').replace(/\.(jpe?g|png)$/i, '-low.$1');
                
                // Vérifier si la version basse qualité existe
                $.ajax({
                    url: lowQualitySrc,
                    type: 'HEAD',
                    success: function() {
                        $img.attr('src', lowQualitySrc);
                    }
                });
            });
        },
        
        // Optimisations pour mode hors-ligne
        applyOfflineOptimizations: function() {
            // Désactiver tous les formulaires
            $('form').each(function() {
                var $form = $(this);
                
                // Ne pas désactiver le formulaire de recherche qui peut fonctionner en local
                if ($form.hasClass('search-form')) {
                    return;
                }
                
                // Sauvegarder l'état
                if (!$form.data('original-state')) {
                    $form.data('original-state', {
                        html: $form.html()
                    });
                }
                
                // Remplacer par un message
                $form.html('<div class="offline-message">' + 
                           'Ce formulaire n\'est pas disponible en mode hors-ligne. ' +
                           'Veuillez vous reconnecter pour l\'utiliser.' +
                           '</div>');
            });
            
            // Masquer les éléments qui nécessitent une connexion
            $('.requires-connection').hide();
            $('.offline-alternative').show();
            
            // Afficher une notification
            if (!$('.offline-notification').length) {
                $('<div class="offline-notification">' +
                  '<strong>Mode hors-ligne actif</strong>' +
                  '<p>Vous consultez actuellement une version limitée du site. Certaines fonctionnalités ne sont pas disponibles.</p>' +
                  '<button class="retry-connection">Réessayer</button>' +
                  '</div>').appendTo('body');
                
                // Gérer le bouton de réessai
                $('.retry-connection').on('click', function() {
                    $(this).text('Vérification...').prop('disabled', true);
                    LifeTravelNetworkDetector.runConnectionTest();
                });
            }
        },
        
        // Fonctions utilitaires pour les cookies
        setCookie: function(name, value, hours) {
            var expires = '';
            if (hours) {
                var date = new Date();
                date.setTime(date.getTime() + (hours * 60 * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            document.cookie = name + '=' + value + expires + '; path=/; SameSite=Lax';
        },
        
        getCookie: function(name) {
            var nameEQ = name + '=';
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) === ' ') {
                    c = c.substring(1, c.length);
                }
                if (c.indexOf(nameEQ) === 0) {
                    return c.substring(nameEQ.length, c.length);
                }
            }
            return null;
        }
    };

    // Initialiser le détecteur quand le document est prêt
    $(document).ready(function() {
        LifeTravelNetworkDetector.init();
    });

    // Exposer l'API pour d'autres scripts
    window.LifeTravelNetworkDetector = LifeTravelNetworkDetector;

})(jQuery);
