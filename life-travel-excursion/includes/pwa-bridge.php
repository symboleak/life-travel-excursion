<?php
/**
 * Life Travel - Pont PWA
 *
 * Ce fichier sert de pont entre l'ancienne et la nouvelle gestion des fonctionnalités
 * Progressive Web App (PWA) du plugin Life Travel. Il permet une transition progressive
 * de l'ancien système vers le nouveau, avec des options de bascule pour l'administrateur.
 *
 * @package Life Travel Excursion
 * @version 2.5.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// S'assurer que le validateur de bridge est chargé
if (!function_exists('life_travel_register_bridge')) {
    require_once dirname(__FILE__) . '/bridge-validator.php';
}

// Fallback IDE pour la version de cache PWA si non définie ailleurs
if (!defined('LIFE_TRAVEL_CACHE_VERSION')) {
    define('LIFE_TRAVEL_CACHE_VERSION', defined('LIFE_TRAVEL_EXCURSION_VERSION') ? LIFE_TRAVEL_EXCURSION_VERSION : '1.0.0');
}

// Enregistrer ce bridge auprès du validateur
life_travel_register_bridge('pwa', '2.5.0', array(
    'life_travel_register_service_worker',
    'life_travel_register_scripts',
    'life_travel_optimized_sw_register',
    'life_travel_get_connection_detection_script',
    'life_travel_register_offline_resources'
));

/**
 * Gère l'enregistrement du Service Worker et des scripts associés
 * 
 * @since 2.4.0 Ajout du chargement des polyfills et optimisations pour le contexte camerounais
 */
function life_travel_register_service_worker() {
    // Vérifier si on utilise le nouveau gestionnaire PWA
    $use_optimized_pwa = get_option('life_travel_use_optimized_pwa', true);
    
    if ($use_optimized_pwa) {
        // Enregistrer les scripts avec priorité pour les polyfills
        add_action('wp_enqueue_scripts', 'life_travel_register_scripts', 5); // Priorité élevée (5) pour charger avant les autres scripts
        
        // Nouvel enregistrement du service worker avec optimisations
        add_action('wp_footer', 'life_travel_optimized_sw_register');
    } else {
        // Ancien enregistrement du service worker
        add_action('wp_footer', 'life_travel_legacy_sw_register');
    }
    
    // Ajouter les endpoints AJAX pour la gestion PWA
    add_action('wp_ajax_life_travel_update_pwa_cache', 'life_travel_update_pwa_cache');
    add_action('wp_ajax_nopriv_life_travel_update_pwa_cache', 'life_travel_update_pwa_cache');
    
    add_action('wp_ajax_life_travel_sync_offline_data', 'life_travel_sync_offline_data');
    add_action('wp_ajax_nopriv_life_travel_sync_offline_data', 'life_travel_sync_offline_data');
    
    // Nouvelle API pour la compatibilité et les informations réseau optimisées pour le Cameroun
    add_action('wp_ajax_life_travel_network_info', 'life_travel_network_info');
    add_action('wp_ajax_nopriv_life_travel_network_info', 'life_travel_network_info');
}

/**
 * Enregistre les scripts pour le système PWA optimisé pour le Cameroun
 * 
 * @since 2.4.0 Ajout du chargement des polyfills pour compatibilité étendue
 */
function life_travel_register_scripts() {
    // Vérifier si on utilise le système optimisé
    if (!life_travel_bridge_get_option('life_travel_use_optimized_pwa', true)) {
        return;
    }
    
    // Obtenir la version du plugin pour le cache-busting
    $plugin_version = defined('LIFE_TRAVEL_VERSION') ? LIFE_TRAVEL_VERSION : '2.4.0';
    
    // Enregistrer le script de polyfills avec la plus haute priorité
    wp_enqueue_script(
        'life-travel-polyfills',
        LIFE_TRAVEL_EXCURSION_URL . 'assets/js/life-travel-polyfills.js',
        array(), // pas de dépendances
        $plugin_version,
        false // dans le head pour chargement prioritaire
    );
    
    // Enregistrer le script d'icônes SVG
    wp_enqueue_script(
        'life-travel-icons',
        LIFE_TRAVEL_EXCURSION_URL . 'assets/js/life-travel-icons.js',
        array('life-travel-polyfills'), // dépend des polyfills
        $plugin_version,
        true // dans le footer
    );
    
    // Ajouter les scripts spécifiques KaiOS, très populaire au Cameroun
    wp_enqueue_script(
        'life-travel-kaios',
        LIFE_TRAVEL_EXCURSION_URL . 'assets/js/life-travel-kaios.js',
        array('life-travel-polyfills'),
        $plugin_version,
        true
    );
    
    wp_enqueue_script(
        'life-travel-kaios-optimization',
        LIFE_TRAVEL_EXCURSION_URL . 'assets/js/life-travel-kaios-optimization.js',
        array('life-travel-polyfills', 'life-travel-kaios'),
        $plugin_version,
        true
    );
    
    // Détecteur de réseau spécifique à KaiOS pour les conditions réseau camerounaises
    wp_enqueue_script(
        'life-travel-kaios-network',
        LIFE_TRAVEL_EXCURSION_URL . 'assets/js/kaios-network-detector.js',
        array('life-travel-polyfills', 'life-travel-kaios'),
        $plugin_version,
        true
    );
    
    // Ajouter la feuille de style spécifique à KaiOS
    wp_enqueue_style(
        'life-travel-kaios-style',
        LIFE_TRAVEL_EXCURSION_URL . 'assets/css/life-travel-kaios.css',
        array(),
        $plugin_version
    );
    
    // Ajouter d'autres scripts spécifiques au contexte camerounais si nécessaire
    if (life_travel_bridge_get_option('life_travel_optimize_for_cameroon', true)) {
        // Scripts optimisés pour le contexte camerounais (connexions lentes, économie de données)
        wp_enqueue_script(
            'life-travel-network-detector',
            LIFE_TRAVEL_EXCURSION_URL . 'assets/js/network-detector.js',
            array('life-travel-polyfills'),
            $plugin_version,
            true
        );
    }
}

/**
 * Enregistre le service worker optimisé
 * 
 * @since 2.4.0 Amélioré avec détection de compatibilité et ajout des polyfills
 */
function life_travel_optimized_sw_register() {
    // Obtenir l'URL du service worker
    $sw_path = home_url('/life-travel-sw.js');
    
    ?>
    <script>
    // Vérification préliminaire pour détecter les navigateurs non compatibles
    var isOperaMini = navigator.userAgent.indexOf('Opera Mini') > -1;
    var isOldAndroid = navigator.userAgent.indexOf('Android') > -1 && 
                      parseFloat(navigator.userAgent.slice(navigator.userAgent.indexOf('Android') + 8)) < 4.4;
    var isUCMini = navigator.userAgent.indexOf('UCBrowser') > -1 && navigator.userAgent.indexOf('Mini') > -1;
    var isKaiOS = navigator.userAgent.indexOf('KAIOS') > -1;
    
    // Stockage des informations de compatibilité pour décisions ultérieures
    window.LIFE_TRAVEL = window.LIFE_TRAVEL || {};
    window.LIFE_TRAVEL.COMPAT = {
        isOperaMini: isOperaMini,
        isOldAndroid: isOldAndroid,
        isUCMini: isUCMini,
        isKaiOS: isKaiOS,
        hasServiceWorker: 'serviceWorker' in navigator,
        hasPromise: typeof Promise !== 'undefined',
        hasFetch: typeof fetch !== 'undefined',
        hasIndexedDB: typeof indexedDB !== 'undefined',
        hasKaiHardwareKeys: typeof navigator.getDeviceStorage === 'function'
    };
    
    // Attendre le chargement des polyfills si nécessaire
    function waitForPolyfills(callback) {
        if (window.LIFE_TRAVEL.POLYFILLS_LOADED) {
            callback();
        } else {
            window.addEventListener('lifeTravelPolyfillsReady', callback);
        }
    }
    
    /**
     * Active le mode dégradé pour les navigateurs non compatibles
     * ou en cas d'échec du service worker
     * 
     * @param {string} reason - Raison du fallback
     */
    function enableGracefulFallback(reason) {
        console.info('Life Travel: Activating graceful fallback mode. Reason:', reason);
        
        // Marquer le mode dégradé pour référence future
        try {
            localStorage.setItem('lt_degraded_mode', 'true');
            localStorage.setItem('lt_degraded_reason', reason);
        } catch (e) {
            // Fallback pour Opera Mini et autres navigateurs sans localStorage
            document.cookie = 'lt_degraded_mode=true;path=/;max-age=86400';
        }
        
        // Ajouter une classe au body pour les styles CSS alternatifs
        document.body.classList.add('lt-degraded-mode');
        document.body.classList.add('lt-'+reason); // Classe spécifique à la raison
        
        // Charger les ressources essentielles si nécessaire
        if (reason === 'unsupported-browser') {
            // Pour les navigateurs comme Opera Mini et KaiOS, charger une version très légère
            var lightweightCSS = document.createElement('link');
            lightweightCSS.rel = 'stylesheet';
            lightweightCSS.href = '<?php echo esc_url(LIFE_TRAVEL_EXCURSION_URL . 'assets/css/lightweight.css'); ?>';
            document.head.appendChild(lightweightCSS);
            
            // Si c'est spécifiquement KaiOS, charger aussi les styles KaiOS
            if (isKaiOS) {
                var kaiOSCSS = document.createElement('link');
                kaiOSCSS.rel = 'stylesheet';
                kaiOSCSS.href = '<?php echo esc_url(LIFE_TRAVEL_EXCURSION_URL . 'assets/css/life-travel-kaios.css'); ?>';
                document.head.appendChild(kaiOSCSS);
            }
        }
        
        // Afficher une notification non intrusive (si toast container existe)
        var toastContainer = document.getElementById('lt-toast-container');
        if (toastContainer) {
            toastContainer.innerHTML += '<div class="lt-toast info">Mode léger activé pour optimiser votre expérience</div>';
        }
    }
    
    /**
     * Gère les messages du Service Worker
     */
    function setupServiceWorkerMessaging() {
        if (!('serviceWorker' in navigator) || !navigator.serviceWorker.controller) {
            return; // Pas de service worker, rien à configurer
        }
        
        // Détection de KaiOS à partir des propriétés du client
        self.addEventListener('message', function(event) {
          if (event.data && event.data.type === 'CLIENT_INFO') {
            // Stocker les informations du client pour référence future
            self.LIFE_TRAVEL.clientInfo = event.data.clientInfo || {};
            
            // Vérifier si le client est un appareil KaiOS
            if (self.LIFE_TRAVEL.clientInfo.isKaiOS) {
              console.info('Life Travel SW: KaiOS device detected, applying optimizations');
              self.LIFE_TRAVEL.isKaiOS = true;
              self.LIFE_TRAVEL.kaiOSVersion = self.LIFE_TRAVEL.clientInfo.kaiOSVersion || '2.5';
              
              // Adapter les timeout réseau pour KaiOS
              self.LIFE_TRAVEL.CONFIG.networkTimeout = self.LIFE_TRAVEL.CONFIG.kaiOS.networkTimeout;
              self.LIFE_TRAVEL.CONFIG.shortNetworkTimeout = self.LIFE_TRAVEL.CONFIG.kaiOS.shortNetworkTimeout;
              self.LIFE_TRAVEL.CONFIG.longNetworkTimeout = self.LIFE_TRAVEL.CONFIG.kaiOS.longNetworkTimeout;
              
              // Prioriser le cache des ressources essentielles pour KaiOS
              self.LIFE_TRAVEL.PRIORITIZE_KAIOS_ASSETS = true;
              
              // Limiter le stockage pour éviter de saturer la mémoire limitée
              if (self.LIFE_TRAVEL.clientInfo.isLowEndDevice) {
                self.LIFE_TRAVEL.LOW_MEMORY_MODE = true;
                console.info('Life Travel SW: KaiOS low memory mode activated');
              }
            }
          }
          
          // Réception des infos sur la qualité réseau de KaiOS
          if (event.data && event.data.type === 'KAIOS_NETWORK_INFO') {
            self.LIFE_TRAVEL.connection = self.LIFE_TRAVEL.connection || {};
            self.LIFE_TRAVEL.connection.quality = event.data.quality || 'unknown';
            self.LIFE_TRAVEL.connection.type = event.data.connectionType || 'unknown';
            self.LIFE_TRAVEL.connection.saveData = event.data.saveData || false;
            
            // Adapter les stratégies de cache en fonction de la qualité réseau
            if (event.data.quality === 'poor' || event.data.saveData) {
              // Mode économie de données pour les réseaux lents/chers typiques au Cameroun
              self.LIFE_TRAVEL.DATA_SAVING_MODE = true;
            }
          }
        });
        
        // Gestionnaire de messages du service worker
        navigator.serviceWorker.addEventListener('message', function(event) {
            if (event.data && event.data.type) {
                switch(event.data.type) {
                    case 'CACHE_UPDATED':
                        console.log('Life Travel SW: Cache updated for: ' + event.data.url);
                        break;
                    case 'SW_ACTIVATED':
                        console.log('Life Travel SW: Activation complète', event.data);
                        // Vérifier la version du cache pour décider d'une mise à jour
                        if (event.data.cacheVersion && event.data.cacheVersion !== '<?php echo esc_js(LIFE_TRAVEL_CACHE_VERSION); ?>') {
                            // Proposer la mise à jour si une nouvelle version est détectée
                            // (sauf en mode économie de données)
                            var isDataSaving = localStorage.getItem('lt_data_saving_mode') === 'true';
                            if (!isDataSaving) {
                                showUpdateNotification();
                            }
                        }
                        break;
                    case 'SYNC_COMPLETED':
                        console.log('Life Travel SW: Synchronisation terminée', event.data);
                        // Notifier l'utilisateur que les données ont été synchronisées
                        if (event.data.success) {
                            showNotification('Synchronisation réussie', 
                                            'Vos données ont été synchronisées avec succès.');
                        }
                        break;
                    case 'NETWORK_STATUS':
                        console.log('Life Travel SW: État réseau mis à jour', event.data);
                        // Mettre à jour l'interface utilisateur selon l'état de la connexion
                        updateUIForNetworkStatus(event.data.online, event.data.quality);
                        break;
                }
            }
        });
    }
    
    /**
     * Affiche une notification standard Life Travel
     */
    function showNotification(title, message) {
        var toastContainer = document.getElementById('lt-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'lt-toast-container';
            document.body.appendChild(toastContainer);
        }
        
        var toast = document.createElement('div');
        toast.className = 'lt-toast info';
        toast.innerHTML = '<strong>' + title + '</strong><p>' + message + '</p>';
        
        toastContainer.appendChild(toast);
        
        // Supprimer la notification après 5 secondes
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
     * Affiche une notification de mise à jour disponible
     */
    function showUpdateNotification() {
        var toastContainer = document.getElementById('lt-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'lt-toast-container';
            document.body.appendChild(toastContainer);
        }
        
        var toast = document.createElement('div');
        toast.className = 'lt-toast update';
        toast.innerHTML = '<strong>Mise à jour disponible</strong><p>Une nouvelle version du site est disponible.</p><button class="lt-reload">Actualiser maintenant</button>';
        
        // Ajouter l'écouteur d'événement pour le bouton d'actualisation
        toast.querySelector('.lt-reload').addEventListener('click', function() {
            window.location.reload();
        });
        
        toastContainer.appendChild(toast);
    }
    
    /**
     * Met à jour l'interface selon l'état de la connexion
     */
    function updateUIForNetworkStatus(isOnline, quality) {
        // Mettre à jour les classes sur le body
        if (isOnline) {
            document.body.classList.remove('lt-offline');
            document.body.classList.add('lt-online');
            
            // Ajouter la classe de qualité si disponible
            if (quality) {
                document.body.classList.remove('lt-network-poor', 'lt-network-medium', 'lt-network-good', 'lt-network-excellent');
                document.body.classList.add('lt-network-' + quality);
            }
        } else {
            document.body.classList.remove('lt-online');
            document.body.classList.add('lt-offline');
        }
        
        // Mettre à jour l'indicateur d'état s'il existe
        var statusIndicator = document.querySelector('.lt-connection-status');
        if (statusIndicator) {
            statusIndicator.className = 'lt-connection-status ' + (isOnline ? 'online' : 'offline');
            if (quality && isOnline) {
                statusIndicator.setAttribute('data-quality', quality);
            }
        }
    }
    
    // Enregistrement du Service Worker optimisé pour Life Travel avec compatibilité étendue
    function initServiceWorker() {
        // Déterminer si le navigateur peut utiliser le service worker de manière fiable
        // Note: KaiOS 2.5+ peut utiliser Service Worker, mais avec des limitations
        var canUseServiceWorker = 'serviceWorker' in navigator && 
                                !isOperaMini && 
                                !isUCMini && 
                                typeof Promise !== 'undefined' &&
                                !(isKaiOS && window.LIFE_TRAVEL.KAIOS && parseFloat(window.LIFE_TRAVEL.KAIOS.version) < 2.5);
                                
        if (canUseServiceWorker) {
            // Enregistrer le service worker avec gestion des erreurs améliorée
            waitForPolyfills(function() {
                try {
                    navigator.serviceWorker.register('<?php echo esc_url($sw_path); ?>', {
                        scope: '/'
                    }).then(function(registration) {
                        console.log('Life Travel SW: Registered with scope:', registration.scope);
                        
                        // Vérifier les mises à jour du service worker
                        registration.update();
                        
                        // Configurer une mise à jour périodique adaptée aux réseaux camerounais
                        // Détection de la qualité réseau pour l'intervalle (plus long sur réseaux lents)
                        var updateInterval = navigator.connection && 
                                            (navigator.connection.effectiveType === '2g' || 
                                             navigator.connection.saveData) ? 
                                            6 * 3600000 : // 6 heures sur connexions lentes/économie de données
                                            3 * 3600000;  // 3 heures sur connexions normales
                                            
                        setInterval(function() {
                            // N'effectuer la mise à jour que si l'utilisateur est en ligne
                            if (navigator.onLine) {
                                registration.update();
                            }
                        }, updateInterval);
                        
                        // Notifier la page principale lorsque le service worker est mis à jour
                        navigator.serviceWorker.addEventListener('controllerchange', function() {
                            console.log('Life Travel SW: Service Worker updated');
                            // Notification pour l'utilisateur (désactivée en mode économie de données)
                            var isDataSaving = localStorage.getItem('lt_data_saving_mode') === 'true';
                            if (!isDataSaving) {
                                showUpdateNotification();
                            }
                        });
                        
                        // Configurer la gestion des messages
                        setupServiceWorkerMessaging();
                    
                    }).catch(function(error) {
                        console.warn('Life Travel SW: Registration failed:', error.message || 'Unknown error');
                        // En cas d'échec, activer le mode dégradé (fallback complet CSS/JS)
                        enableGracefulFallback('service-worker-failed');
                    });
                } catch (e) {
                    console.error('Life Travel SW: Error during registration attempt:', e.message || 'Unknown error');
                    // Activer le mode dégradé en cas d'erreur inattendue
                    enableGracefulFallback('registration-error');
                }
            });
        } else {
            // Navigateur non compatible avec Service Worker (Opera Mini, etc.)
            console.info('Life Travel SW: Browser does not support Service Worker, using compatibility mode');
            // Activer le mode dégradé pour les navigateurs non supportés
            enableGracefulFallback('unsupported-browser');
        }
    // Initialisation du Service Worker au chargement de la page
    window.addEventListener('load', function() {
        initServiceWorker();
        
        // Détecter les changements d'état de connexion
        window.addEventListener('online', function() {
            document.body.classList.remove('lt-offline');
            document.body.classList.add('lt-online');
            
            // Synchroniser les données stockées localement
            if (typeof life_travel_sync_offline_data === 'function') {
                life_travel_sync_offline_data();
            }
        });
        
        window.addEventListener('offline', function() {
            document.body.classList.remove('lt-online');
            document.body.classList.add('lt-offline');
            
            // Notifier l'utilisateur qu'il est hors ligne
            life_travel_show_offline_notice();
        });
        
        // Détection avancée de la qualité du réseau pour le contexte camerounais
        if (navigator.connection) {
            // Utiliser l'API Network Information si disponible
            function updateNetworkQuality() {
                var connection = navigator.connection;
                var quality = 'unknown';
                
                // Évaluer la qualité de la connexion
                if (connection.effectiveType === '4g' && !connection.saveData) {
                    quality = 'excellent';
                } else if (connection.effectiveType === '4g' && connection.saveData) {
                    quality = 'good';
                } else if (connection.effectiveType === '3g') {
                    quality = 'medium';
                } else {
                    quality = 'poor'; // 2g ou moins
                }
                
                // Envoyer les métriques réseau au serveur pour analyse
                if (navigator.onLine) {
                    var data = {
                        action: 'life_travel_network_info',
                        quality: quality,
                        type: connection.type || 'unknown',
                        effectiveType: connection.effectiveType || 'unknown',
                        downlink: connection.downlink || 0,
                        rtt: connection.rtt || 0,
                        saveData: connection.saveData || false
                    };
                    
                    // Utiliser sendBeacon pour ne pas interférer avec la navigation
                    if (navigator.sendBeacon) {
                        var formData = new FormData();
                        for (var key in data) {
                            formData.append(key, data[key]);
                        }
                        navigator.sendBeacon('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', formData);
                    }
                }
                
                // Mettre à jour l'interface utilisateur pour refléter la qualité de connexion
                updateUIForNetworkStatus(navigator.onLine, quality);
                
                // Informer le Service Worker de la qualité réseau actuelle
                if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({
                        type: 'NETWORK_QUALITY',
                        quality: quality,
                        saveData: connection.saveData || false,
                        downlink: connection.downlink || 0
                    });
                }
            }
            
            // Surveiller les changements de connexion
            navigator.connection.addEventListener('change', updateNetworkQuality);
            
            // Évaluation initiale
            updateNetworkQuality();
        }
    });
    }
    
    /**
     * Endpoint AJAX pour collecter les informations réseau des utilisateurs
     * 
     * Permet d'analyser les conditions réseau au Cameroun et d'adapter
     * dynamiquement nos stratégies de cache et de chargement
     * 
     * @since 2.4.0
     */
    /**
     * Synchronise les données stockées localement
     */
    
    /**
     * Synchronise les données stockées localement
     */
    function life_travel_sync_offline_data() {
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                command: 'life-travel-sync'
            });
        }
    }
    
    /**
     * Affiche une notification à l'utilisateur
     */
    function life_travel_show_notification(title, message) {
        // Vérifier si la classe de notification existe déjà
        if (document.querySelector('.lt-notification-container')) {
            document.querySelector('.lt-notification-container').remove();
        }
        
        // Créer la notification
        var container = document.createElement('div');
        container.className = 'lt-notification-container';
        
        var notification = document.createElement('div');
        notification.className = 'lt-notification';
        
        var titleEl = document.createElement('h4');
        titleEl.textContent = title;
        
        var messageEl = document.createElement('p');
        messageEl.textContent = message;
        
        var closeBtn = document.createElement('button');
        closeBtn.className = 'lt-notification-close';
        closeBtn.textContent = '×';
        closeBtn.addEventListener('click', function() {
            container.classList.add('lt-notification-hidden');
            setTimeout(function() {
                container.remove();
            }, 300);
        });
        
        notification.appendChild(titleEl);
        notification.appendChild(messageEl);
        notification.appendChild(closeBtn);
        container.appendChild(notification);
        
        document.body.appendChild(container);
        
        // Masquer automatiquement après 5 secondes
        setTimeout(function() {
            container.classList.add('lt-notification-hidden');
            setTimeout(function() {
                container.remove();
            }, 300);
        }, 5000);
    }
    
    /**
     * Affiche un message hors ligne à l'utilisateur
     */
    function life_travel_show_offline_notice() {
        // Code pour afficher le message hors ligne
        var offlineMessage = '<?php echo esc_js(__("Vous êtes actuellement hors ligne. Certaines fonctionnalités peuvent être limitées.", "life-travel-excursion")); ?>';
        life_travel_show_notification('Hors ligne', offlineMessage);
    }
    </script>
    <?php
}

/**
 * Enregistre l'ancien service worker (legacy)
 */
function life_travel_legacy_sw_register() {
    // Obtenir l'URL du service worker
    $sw_path = home_url('/life-travel-sw.js');
    
    ?>
    <script>
    // Enregistrement du Service Worker pour Life Travel
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('<?php echo esc_url($sw_path); ?>', { scope: '/' });
        });
    }
    </script>
    <?php
}

/**
 * Endpoint AJAX pour collecter les informations réseau des utilisateurs
 * 
 * Permet d'analyser les conditions réseau au Cameroun et d'adapter
 * dynamiquement nos stratégies de cache et de chargement
 * 
 * @since 2.4.0
 */
function life_travel_network_info() {
    // Vérifier l'origine de la requête (ne meurt pas en cas d'échec)
    check_ajax_referer('life_travel_network_info', 'nonce', false);

    // Données réseau de base
    $network_data = array(
        'quality' => isset($_POST['quality']) ? sanitize_text_field($_POST['quality']) : 'unknown',
        'type' => isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'unknown',
        'effective_type' => isset($_POST['effectiveType']) ? sanitize_text_field($_POST['effectiveType']) : 'unknown',
        'downlink' => isset($_POST['downlink']) ? floatval($_POST['downlink']) : 0,
        'rtt' => isset($_POST['rtt']) ? intval($_POST['rtt']) : 0,
        'save_data' => isset($_POST['saveData']) && $_POST['saveData'] === 'true',
        'timestamp' => current_time('mysql'),
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
        'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
    );

    // Informations supplémentaires pour l'analyse
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $network_data['user_id'] = $current_user->ID;
    } else {
        $network_data['user_id'] = 0;
    }

    // Ajouter une entrée dans les logs pour analyse ultérieure
    $log_file = LIFE_TRAVEL_EXCURSION_DIR . 'logs/network-quality.log';
    $log_dir = dirname($log_file);

    // Créer le répertoire de logs s'il n'existe pas
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }

    // Formater les données pour le log
    $log_entry = date('Y-m-d H:i:s') . ' | ';
    $log_entry .= 'Quality: ' . $network_data['quality'] . ' | ';
    $log_entry .= 'Type: ' . $network_data['type'] . ' | ';
    $log_entry .= 'EffType: ' . $network_data['effective_type'] . ' | ';
    $log_entry .= 'Downlink: ' . $network_data['downlink'] . 'Mbps | ';
    $log_entry .= 'RTT: ' . $network_data['rtt'] . 'ms | ';
    $log_entry .= 'SaveData: ' . ($network_data['save_data'] ? 'Yes' : 'No') . ' | ';
    $log_entry .= 'UserAgent: ' . substr($network_data['user_agent'], 0, 100) . ' | ';
    $log_entry .= 'IP: ' . $network_data['ip'] . PHP_EOL;

    // Écrire dans le fichier log
    @file_put_contents($log_file, $log_entry, FILE_APPEND);

    // Stocker les statistiques globales pour analyse
    $network_stats = get_option('life_travel_network_stats', array());

    // Initialiser si nécessaire
    if (!is_array($network_stats)) {
        $network_stats = array(
            'total_reports' => 0,
            'quality' => array(
                'poor' => 0,
                'medium' => 0,
                'good' => 0,
                'excellent' => 0,
                'unknown' => 0
            ),
            'save_data_enabled' => 0,
            'connections' => array(
                'cellular' => 0,
                'wifi' => 0,
                'other' => 0
            ),
            'last_updated' => current_time('mysql')
        );
    }

    // Mettre à jour les statistiques
    $network_stats['total_reports']++;

    // Qualité
    if (isset($network_stats['quality'][$network_data['quality']])) {
        $network_stats['quality'][$network_data['quality']]++;
    } else {
        $network_stats['quality']['unknown']++;
    }

    // Mode économie de données
    if ($network_data['save_data']) {
        $network_stats['save_data_enabled']++;
    }

    // Type de connexion
    if ($network_data['type'] === 'cellular') {
        $network_stats['connections']['cellular']++;
    } else if ($network_data['type'] === 'wifi') {
        $network_stats['connections']['wifi']++;
    } else {
        $network_stats['connections']['other']++;
    }

    $network_stats['last_updated'] = current_time('mysql');

    // Sauvegarder les statistiques
    update_option('life_travel_network_stats', $network_stats, false);

    // Réponse minimaliste pour économiser la bande passante
    wp_die();
}

/**
 * Met à jour le cache PWA
 */
function life_travel_update_pwa_cache() {
    // Vérifier le nonce pour la sécurité
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_pwa_nonce')) {
        wp_send_json_error('Nonce invalide');
        exit;
    }
    
    // URLs à mettre en cache
    $urls = isset($_POST['urls']) ? (array)$_POST['urls'] : array();
    
    // Filtrer et valider les URLs
    $urls = array_filter($urls, function($url) {
        return esc_url_raw($url) === $url;
    });
    
    // Si nous avons des URLs valides, envoyer un message au service worker
    if (!empty($urls)) {
        wp_send_json_success(array(
            'message' => 'URLs envoyées au service worker pour mise en cache',
            'urls' => $urls
        ));
    } else {
        wp_send_json_error('Aucune URL valide fournie');
    }
    
    exit;
}

/**
 * Synchronise les données hors ligne
 */
function life_travel_sync_offline_data() {
    // Vérifier le nonce pour la sécurité
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_pwa_nonce')) {
        wp_send_json_error('Nonce invalide');
        exit;
    }
    
    // Données à synchroniser
    $data = isset($_POST['data']) ? $_POST['data'] : array();
    
    // Si le système de panier abandonné est activé, synchroniser avec lui
    if (function_exists('life_travel_sync_with_abandoned_cart') && !empty($data['cart'])) {
        $sync_result = call_user_func('life_travel_sync_with_abandoned_cart', $data['cart']);
        wp_send_json_success(array(
            'message' => 'Données synchronisées avec succès',
            'cart_sync' => $sync_result
        ));
    } else {
        wp_send_json_success(array(
            'message' => 'Rien à synchroniser'
        ));
    }
    
    exit;
}

/**
 * Ajoute le manifeste PWA et les méta-tags
 */
function life_travel_add_pwa_manifest() {
    // Vérifier si on utilise le nouveau gestionnaire PWA
    $use_optimized_pwa = get_option('life_travel_use_optimized_pwa', true);
    
    if ($use_optimized_pwa) {
        // Générer le manifeste de manière dynamique
        add_action('wp_head', 'life_travel_optimized_pwa_head');
    } else {
        // Ancien système de manifeste statique
        add_action('wp_head', 'life_travel_legacy_pwa_head');
    }
}

/**
 * Ajoute les balises d'en-tête PWA optimisées
 */
function life_travel_optimized_pwa_head() {
    // Couleur du thème et autres paramètres PWA
    $theme_color = '#3498db'; // Bleu Life Travel
    
    // URL du manifeste
    $manifest_url = LIFE_TRAVEL_EXCURSION_URL . 'manifest.json?' . LIFE_TRAVEL_EXCURSION_VERSION;
    
    // Récupérer les logos configurés
    $site_icon_url = get_site_icon_url();
    $logo_url = esc_url(get_option('life_travel_logo_main', ''));
    
    if (empty($site_icon_url) && !empty($logo_url)) {
        $site_icon_url = $logo_url;
    }
    
    ?>
    <!-- Life Travel PWA -->
    <meta name="theme-color" content="<?php echo esc_attr($theme_color); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr(get_bloginfo('name')); ?>">
    
    <?php if (!empty($site_icon_url)) : ?>
    <link rel="apple-touch-icon" href="<?php echo esc_url($site_icon_url); ?>">
    <?php endif; ?>
    
    <link rel="manifest" href="<?php echo esc_url($manifest_url); ?>">
    <?php
}

/**
 * Ajoute les balises d'en-tête PWA legacy
 */
function life_travel_legacy_pwa_head() {
    // Version simplifiée pour la rétrocompatibilité
    $manifest_url = LIFE_TRAVEL_EXCURSION_URL . 'manifest.json';
    ?>
    <link rel="manifest" href="<?php echo esc_url($manifest_url); ?>">
    <?php
}

/**
 * Génère le manifeste PWA dynamiquement
 */
function life_travel_generate_manifest() {
    // Vérifier si on utilise le nouveau gestionnaire PWA
    $use_optimized_pwa = get_option('life_travel_use_optimized_pwa', true);
    
    if ($use_optimized_pwa) {
        add_action('init', 'life_travel_register_manifest_endpoint');
    }
}

/**
 * Expose le Service Worker à la racine: /life-travel-sw.js
 */
function life_travel_generate_service_worker_endpoint() {
    $use_optimized_pwa = get_option('life_travel_use_optimized_pwa', true);
    if ($use_optimized_pwa) {
        add_action('init', 'life_travel_register_sw_endpoint');
    }
}

/**
 * Enregistre l'endpoint pour servir /life-travel-sw.js
 */
function life_travel_register_sw_endpoint() {
    add_rewrite_rule('^life-travel-sw\.js$', 'index.php?life_travel_sw=1', 'top');
    add_filter('query_vars', 'life_travel_add_sw_query_var');
    add_action('template_redirect', 'life_travel_serve_sw');
    
    // Flusher les règles au besoin (même option de contrôle que le manifeste)
    if (get_option('life_travel_flush_rewrite_rules', false)) {
        flush_rewrite_rules();
        update_option('life_travel_flush_rewrite_rules', false);
    }
}

/**
 * Ajoute la query var life_travel_sw
 */
function life_travel_add_sw_query_var($vars) {
    $vars[] = 'life_travel_sw';
    return $vars;
}

/**
 * Sert le Service Worker unifié à /life-travel-sw.js
 */
function life_travel_serve_sw() {
    if ((int) get_query_var('life_travel_sw') === 1) {
        // Sécurité basique: pas d'HTML, désactiver toute mise en cache agressive
        header('Content-Type: application/javascript; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        // Permettre la portée racine
        header('Service-Worker-Allowed: /');
        // Forcer les mises à jour fréquentes
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        // Injection: base URL du plugin pour la résolution des assets depuis un SW servi à la racine
        $plugin_base_url = defined('LIFE_TRAVEL_EXCURSION_URL') ? LIFE_TRAVEL_EXCURSION_URL : plugin_dir_url(__FILE__) . '../';
        $injected = "/* Injected by Life Travel PWA */\n" .
                    "self.LIFE_TRAVEL_PLUGIN_BASE = " . json_encode(trailingslashit($plugin_base_url)) . ";\n" .
                    "self.LIFE_TRAVEL_PLUGIN_SCOPE = '/';\n";

        // Charger le fichier SW source du plugin
        $sw_file = trailingslashit(LIFE_TRAVEL_EXCURSION_DIR) . 'life-travel-sw.js';
        if (file_exists($sw_file)) {
            // Sortie: injection + contenu original
            echo $injected;
            // Lire et émettre le contenu du SW
            readfile($sw_file);
        } else {
            // Fallback minimal si le fichier n'existe pas
            echo $injected . "self.addEventListener('install',e=>self.skipWaiting());self.addEventListener('activate',e=>self.clients.claim());";
        }
        exit;
    }
}

/**
 * Enregistre l'endpoint pour le manifeste PWA dynamique
 */
function life_travel_register_manifest_endpoint() {
    add_rewrite_rule('^manifest\.json$', 'index.php?life_travel_manifest=1', 'top');
    add_filter('query_vars', 'life_travel_add_manifest_query_var');
    add_action('template_redirect', 'life_travel_serve_manifest');
    
    // Flusher les règles de réécriture au besoin
    if (get_option('life_travel_flush_rewrite_rules', false)) {
        flush_rewrite_rules();
        update_option('life_travel_flush_rewrite_rules', false);
    }
}

/**
 * Ajoute la variable de requête pour le manifeste
 */
function life_travel_add_manifest_query_var($vars) {
    $vars[] = 'life_travel_manifest';
    return $vars;
}

/**
 * Sert le manifeste PWA dynamiquement
 */
function life_travel_serve_manifest() {
    if (get_query_var('life_travel_manifest') == 1) {
        header('Content-Type: application/json');
        
        // Récupérer les informations du site
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');
        $theme_color = '#3498db'; // Bleu Life Travel
        $background_color = '#ffffff';
        
        // Récupérer les logos configurés
        $site_icon_url = get_site_icon_url(192);
        $logo_url = esc_url(get_option('life_travel_logo_main', ''));
        
        if (empty($site_icon_url) && !empty($logo_url)) {
            $site_icon_url = $logo_url;
        }
        
        // Construire le manifeste
        $manifest = array(
            'name' => $site_name,
            'short_name' => $site_name,
            'description' => $site_description,
            'start_url' => home_url('/?pwa=1'),
            'display' => 'standalone',
            'background_color' => $background_color,
            'theme_color' => $theme_color,
            'icons' => array()
        );
        
        // Ajouter l'icône principale si disponible
        if (!empty($site_icon_url)) {
            $manifest['icons'][] = array(
                'src' => $site_icon_url,
                'sizes' => '192x192',
                'type' => 'image/png'
            );
        }
        
        // Permettre aux extensions de modifier le manifeste
        $manifest = apply_filters('life_travel_pwa_manifest', $manifest);
        
        echo json_encode($manifest);
        exit;
    }
}

/**
 * Bascule entre les versions optimisées et legacy du système PWA
 */
function life_travel_switch_to_optimized_pwa($use_optimized = true) {
    $old_value = get_option('life_travel_use_optimized_pwa', true);
    update_option('life_travel_use_optimized_pwa', $use_optimized);
    
    // Si nous passons de legacy à optimisé, nous devons flusher les règles de réécriture
    if (!$old_value && $use_optimized) {
        update_option('life_travel_flush_rewrite_rules', true);
    }
    
    return $use_optimized;
}

/**
 * Génère ou met à jour la page hors ligne
 */
if (!function_exists('life_travel_generate_offline_page')) {
function life_travel_generate_offline_page() {
    // Vérifier si on utilise le nouveau gestionnaire PWA
    $use_optimized_pwa = get_option('life_travel_use_optimized_pwa', true);
    
    if ($use_optimized_pwa) {
        // Générer une page hors ligne avancée
        life_travel_generate_advanced_offline_page();
    } else {
        // Utiliser l'ancienne page hors ligne simple
        life_travel_generate_simple_offline_page();
    }
}
}

/**
 * Génère une page hors ligne simple
 */
function life_travel_generate_simple_offline_page() {
    // Chemin vers la page hors ligne simple
    $offline_file = LIFE_TRAVEL_EXCURSION_DIR . 'offline.html';
    $offline_dir = LIFE_TRAVEL_EXCURSION_DIR . 'offline';
    
    // Vérifier si le fichier existe déjà
    if (!file_exists($offline_file)) {
        // Contenu simple pour la page hors ligne
        $offline_content = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Life Travel - Hors ligne</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { color: #3498db; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Vous êtes hors ligne</h1>
        <p>Il semble que vous n\'ayez pas de connexion Internet en ce moment.</p>
        <p>Veuillez vérifier votre connexion et réessayer.</p>
        <button onclick="window.location.reload()">Réessayer</button>
    </div>
</body>
</html>';
        
        // Écrire le fichier
        file_put_contents($offline_file, $offline_content);
    }
    
    // Créer le répertoire offline s'il n'existe pas
    if (!file_exists($offline_dir)) {
        mkdir($offline_dir, 0755, true);
        
        // Copier le fichier offline.html dans le répertoire
        copy($offline_file, $offline_dir . '/index.html');
    }
}

/**
 * Génère une page hors-ligne avancée
 */
function life_travel_generate_advanced_offline_page() {
    // Structure des dossiers pour la page offline avancée
    $offline_dir = LIFE_TRAVEL_EXCURSION_DIR . 'offline';
    $assets_dir = $offline_dir . '/assets';
    $css_dir = $assets_dir . '/css';
    $js_dir = $assets_dir . '/js';
    $img_dir = $assets_dir . '/img';
    
    // Créer les dossiers nécessaires s'ils n'existent pas
    if (!file_exists($offline_dir)) {
        wp_mkdir_p($offline_dir);
    }
    
    if (!file_exists($css_dir)) {
        wp_mkdir_p($css_dir);
    }
    
    if (!file_exists($js_dir)) {
        wp_mkdir_p($js_dir);
    }
    
    if (!file_exists($img_dir)) {
        wp_mkdir_p($img_dir);
    }
    
    // Les fichiers existent déjà, ils ont été créés manuellement
    // Nous allons nous assurer que la page principale existe
    if (!file_exists($offline_dir . '/index.html')) {
        // Si elle n'existe pas, utiliser la version simple
        life_travel_generate_simple_offline_page();
    }
    
    return true;
}

/**
 * Définit les tailles d'images optimisées pour le contexte camerounais
 * avec des connexions à bande passante limitée
 *
 * @return array Tableau des tailles d'images optimisées
 */
function life_travel_get_optimized_image_sizes() {
    return array(
        'thumbnail' => array(
            'width' => 150,
            'height' => 150,
            'quality' => 75,
            'priority' => 'high' // Chargé en priorité
        ),
        'medium' => array(
            'width' => 300,
            'height' => 300,
            'quality' => 70,
            'priority' => 'medium'
        ),
        'large' => array(
            'width' => 600,
            'height' => 600,
            'quality' => 65,
            'priority' => 'low'
        ),
        'full' => array(
            'width' => 1200,
            'height' => 1200,
            'quality' => 60,
            'priority' => 'low'
        )
    );
}

/**
 * Génère une URL d'image optimisée pour le contexte camerounais
 *
 * @param int|string $attachment_id ID de l'attachment ou URL de l'image
 * @param string $size Taille de l'image (thumbnail, medium, large)
 * @param bool $ensure_webp Forcer la génération du format WebP
 * @return string URL de l'image optimisée
 */
function life_travel_get_optimized_image_url($attachment_id, $size = 'medium', $ensure_webp = true) {
    // Si l'optimisation d'images n'est pas activée, retourner l'URL normale
    if (!get_option('life_travel_use_optimized_images', true)) {
        if (is_numeric($attachment_id)) {
            return wp_get_attachment_image_url($attachment_id, $size);
        }
        return $attachment_id; // Si c'est déjà une URL
    }

    // Vérifier si c'est un ID d'attachement ou une URL
    if (is_numeric($attachment_id)) {
        // Récupérer l'URL de l'image
        $image_url = wp_get_attachment_image_url($attachment_id, $size);
        if (!$image_url) {
            return '';
        }
    } else {
        $image_url = $attachment_id;
    }

    // Obtenir la taille adaptée au contexte camerounais
    $sizes = life_travel_get_optimized_image_sizes();
    $selected_size = isset($sizes[$size]) ? $sizes[$size] : $sizes['medium'];

    // Vérifier si le WebP existe déjà, sinon utiliser l'image originale
    $webp_url = str_replace(array('.jpg', '.jpeg', '.png'), '.webp', $image_url);
    $webp_path = str_replace(LIFE_TRAVEL_EXCURSION_URL, LIFE_TRAVEL_EXCURSION_DIR, $webp_url);

    if ($ensure_webp && !file_exists($webp_path) && function_exists('imagewebp')) {
        // Créer une version WebP si possible
        life_travel_create_webp_version($image_url, $webp_path, $selected_size);
    }

    // Si le WebP existe, l'utiliser, sinon utiliser l'image originale
    if (file_exists($webp_path)) {
        return $webp_url;
    }

    return $image_url;
}

/**
 * Crée une version WebP d'une image pour optimiser le chargement
 *
 * @param string $source_url URL de l'image source
 * @param string $dest_path Chemin de destination pour le WebP
 * @param array $size Paramètres de taille et qualité
 * @return bool Succès de l'opération
 */
function life_travel_create_webp_version($source_url, $dest_path, $size = array()) {
    // Vérifier si la fonction GD et WebP sont disponibles
    if (!function_exists('imagewebp')) {
        return false;
    }

    // Paramètres par défaut
    $default_size = array(
        'width' => 300,
        'height' => 300,
        'quality' => 70
    );
    $size = wp_parse_args($size, $default_size);

    // Chemin de l'image source
    $source_path = str_replace(LIFE_TRAVEL_EXCURSION_URL, LIFE_TRAVEL_EXCURSION_DIR, $source_url);
    if (!file_exists($source_path)) {
        return false;
    }

    // Créer le répertoire de destination si nécessaire
    $dest_dir = dirname($dest_path);
    if (!file_exists($dest_dir)) {
        wp_mkdir_p($dest_dir);
    }

    // Charger l'image selon son type
    $image_type = exif_imagetype($source_path);
    $image = null;

    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($source_path);
            // Gérer la transparence
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }

    if (!$image) {
        return false;
    }

    // Redimensionner si nécessaire
    $src_width = imagesx($image);
    $src_height = imagesy($image);

    // Ne redimensionner que si nécessaire
    if ($src_width > $size['width'] || $src_height > $size['height']) {
        // Calculer les nouvelles dimensions en conservant les proportions
        $ratio = min($size['width'] / $src_width, $size['height'] / $src_height);
        $new_width = (int)($src_width * $ratio);
        $new_height = (int)($src_height * $ratio);

        // Créer l'image redimensionnée
        $new_image = imagecreatetruecolor($new_width, $new_height);

        // Préserver la transparence pour PNG
        if ($image_type === IMAGETYPE_PNG) {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
        }

        // Redimensionner l'image
        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $src_width, $src_height);
        imagedestroy($image);
        $image = $new_image;
    }

    // Sauvegarder en WebP
    $success = imagewebp($image, $dest_path, $size['quality']);
    imagedestroy($image);

    return $success;
}

/**
 * Retourne le markup HTML pour une image optimisée avec balise picture et fallback
 *
 * @param int|string $attachment_id ID de l'attachment ou URL de l'image
 * @param string $size Taille de l'image
 * @param array $attr Attributs supplémentaires
 * @return string Markup HTML
 */
function life_travel_get_optimized_picture_tag($attachment_id, $size = 'medium', $attr = array()) {
    // Si l'optimisation d'images n'est pas activée, retourner l'image normale
    if (!get_option('life_travel_use_optimized_images', true)) {
        if (is_numeric($attachment_id)) {
            return wp_get_attachment_image($attachment_id, $size, false, $attr);
        }
        $default_attr = array(
            'src' => $attachment_id,
            'alt' => ''
        );
        $attr = wp_parse_args($attr, $default_attr);
        return '<img ' . implode(' ', array_map(function($name, $value) {
            return $name . '="' . esc_attr($value) . '"';
        }, array_keys($attr), array_values($attr))) . ' />';
    }

    // Récupérer les URL optimisées
    $webp_url = life_travel_get_optimized_image_url($attachment_id, $size, true);
    
    // Récupérer l'URL de l'image originale pour fallback
    if (is_numeric($attachment_id)) {
        $fallback_url = wp_get_attachment_image_url($attachment_id, $size);
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    } else {
        $fallback_url = $attachment_id;
        $alt_text = isset($attr['alt']) ? $attr['alt'] : '';
    }

    // Fusionner les attributs par défaut avec ceux fournis
    $default_attr = array(
        'alt' => $alt_text,
        'loading' => 'lazy',
        'class' => 'life-travel-optimized'
    );
    $attr = wp_parse_args($attr, $default_attr);

    // Construire les attributs de l'image
    $img_attributes = '';
    foreach ($attr as $name => $value) {
        $img_attributes .= ' ' . $name . '="' . esc_attr($value) . '"';
    }

    // Générer le markup optimisé pour le contexte camerounais
    $html = '<picture class="life-travel-picture">';
    
    // Ajouter la source WebP si disponible
    if ($webp_url !== $fallback_url) {
        $html .= '<source srcset="' . esc_url($webp_url) . '" type="image/webp">';
    }
    
    // Ajouter l'image fallback
    $html .= '<img src="' . esc_url($fallback_url) . '"' . $img_attributes . '>';
    $html .= '</picture>';

    return $html;
}

/**
 * Vérifie si la mise en cache offline est activée
 *
 * @return bool
 */
if (!function_exists('life_travel_is_offline_cache_enabled')) {
    function life_travel_is_offline_cache_enabled() {
        return get_option('life_travel_use_pwa_features', true);
    }
}

/**
 * Détecte la qualité de la connexion via l'API Network Information
 * et adapte le comportement du site en fonction
 */
function life_travel_add_connection_detection() {
    if (!life_travel_is_offline_cache_enabled()) {
        return;
    }
    
    // N'ajouter que sur le frontend
    if (is_admin()) {
        return;
    }
    
    add_action('wp_footer', 'life_travel_print_connection_detection_script');
}

/**
 * Retourne un script optimisé de configuration globale adapté au contexte camerounais
 * Ce script est utilisé à la fois par la PWA et la page offline
 *
 * @return string Script de configuration
 */
function life_travel_get_connection_detection_script() {
    ob_start();
    ?>
    <script>
    // Configuration globale pour le contexte camerounais
    window.LIFE_TRAVEL_CONFIG = window.LIFE_TRAVEL_CONFIG || {};
    window.LIFE_TRAVEL_CONFIG.connectionEndpoints = [
        'https://www.google.com',
        'https://www.camtel.cm',
        'https://www.orange.cm',
        'https://www.mtn.cm'
    ];
    window.LIFE_TRAVEL_CONFIG.connectionCheckTimeout = 5000; // 5 secondes max pour les connexions lentes
    window.LIFE_TRAVEL_CONFIG.cacheName = 'life-travel-cache-v2';
    window.LIFE_TRAVEL_CONFIG.svgSpritePath = '<?php echo esc_url(
        function_exists('life_travel_get_svg_path')
            ? life_travel_get_svg_path(true)
            : ( defined('LIFE_TRAVEL_SVG_PATH')
                ? LIFE_TRAVEL_SVG_PATH
                : ( defined('LIFE_TRAVEL_EXCURSION_URL')
                    ? LIFE_TRAVEL_EXCURSION_URL . 'assets/sprite.svg'
                    : plugin_dir_url(__FILE__) . '../assets/sprite.svg' ) )
    ); ?>';
    
    // Détection de la qualité de connexion pour contexte camerounais
    (function() {
        // Variables globales pour la qualité de connexion
        window.lifeTravel = window.lifeTravel || {};
        window.lifeTravel.connection = {
            type: 'unknown',
            quality: 'unknown',
            downlink: 0,
            rtt: 0,
            saveData: false
        };
        
        // Fonction de mise à jour
        function updateConnectionInfo() {
            var connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            
            if (connection) {
                window.lifeTravel.connection.type = connection.type || 'unknown';
                window.lifeTravel.connection.downlink = connection.downlink || 0;
                window.lifeTravel.connection.rtt = connection.rtt || 0;
                window.lifeTravel.connection.saveData = connection.saveData || false;
                
                // Déterminer la qualité de connexion pour le contexte camerounais
                var quality = 'medium';
                
                if (connection.type === 'slow-2g' || connection.type === '2g' || 
                    (connection.downlink > 0 && connection.downlink < 0.5) ||
                    (connection.rtt > 0 && connection.rtt > 1000)) {
                    quality = 'poor';
                } else if (connection.type === '3g' || 
                         (connection.downlink >= 0.5 && connection.downlink < 2) ||
                         (connection.rtt > 0 && connection.rtt > 500 && connection.rtt <= 1000)) {
                    quality = 'medium';
                } else if (connection.type === '4g' || connection.type === 'wifi' || connection.type === 'ethernet' ||
                         connection.downlink >= 2 || (connection.rtt > 0 && connection.rtt <= 500)) {
                    quality = 'good';
                }
                
                // Ajouter un événement pour mettre à jour lorsque la connexion change
                if ('onchange' in connection) {
                    connection.addEventListener('change', updateConnectionInfo);
                }
                
                window.lifeTravel.connection.quality = quality;
                
                // Appliquer des optimisations spécifiques au contexte camerounais
                applyConnectionBasedOptimizations();
            }
        }
        
        // Appliquer des optimisations basées sur la qualité de connexion
        function applyConnectionBasedOptimizations() {
            var quality = window.lifeTravel.connection.quality;
            var saveData = window.lifeTravel.connection.saveData;
            
            document.documentElement.setAttribute('data-connection', quality);
            document.body.setAttribute('data-connection-quality', quality);
            
            if (saveData) {
                document.documentElement.setAttribute('data-save-data', 'true');
                document.body.setAttribute('data-save-data', 'true');
            }
            
            // Optimisations pour connexion lente
            if (quality === 'poor') {
                // Désactiver le chargement des images non essentielles
                document.querySelectorAll('img:not([data-priority="high"])').
                    forEach(function(img) {
                        if (!img.hasAttribute('data-src')) {
                            img.setAttribute('data-src', img.src);
                            img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3C/svg%3E';
                            img.classList.add('lt-connection-lazy');
                        }
                    });
                
                // Ajouter un bouton pour charger les images
                if (!document.getElementById('lt-load-images')) {
                    var loadButton = document.createElement('button');
                    loadButton.id = 'lt-load-images';
                    loadButton.className = 'lt-load-button';
                    loadButton.innerHTML = 'Charger les images';
                    loadButton.addEventListener('click', function() {
                        document.querySelectorAll('.lt-connection-lazy')
                            .forEach(function(img) {
                                if (img.hasAttribute('data-src')) {
                                    img.src = img.getAttribute('data-src');
                                    img.classList.remove('lt-connection-lazy');
                                }
                            });
                        this.remove();
                    });
                    
                    // Ajouter à la fin du contenu principal
                    var mainContent = document.querySelector('.content-area') || document.querySelector('main') || document.body;
                    mainContent.appendChild(loadButton);
                }
            }
            
            // Notifier le service worker de l'état de la connexion
            if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({
                    type: 'CONNECTION_INFO',
                    payload: window.lifeTravel.connection
                });
            }
        }
        
        // Initialiser la détection
        updateConnectionInfo();
        
        // Ré-vérifier périodiquement (utile dans le contexte camerounais)
        setInterval(updateConnectionInfo, 30000); // 30 secondes
    })();
    </script>
    <style>
    .lt-connection-lazy {
        opacity: 0.2;
    }
    .lt-load-button {
        display: block;
        margin: 20px auto;
        padding: 10px 15px;
        background: #e67e22;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
    }
    html[data-connection="poor"] .lt-hide-on-slow,
    html[data-connection="poor"] .lt-hide-on-poor,
    html[data-save-data="true"] .lt-hide-on-savedata {
        display: none !important;
    }
    
    /* Styles pour les indicateurs de qualité de connexion */
    html[data-connection="good"] .connection-indicator {
        background-color: var(--connection-good, #2ecc71);
    }
    html[data-connection="medium"] .connection-indicator {
        background-color: var(--connection-medium, #f39c12);
    }
    html[data-connection="poor"] .connection-indicator {
        background-color: var(--connection-poor, #e74c3c);
    }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Imprime le script de détection de la qualité de connexion
 * Spécifiquement adapté au contexte camerounais avec des connexions variables
 */
function life_travel_print_connection_detection_script() {
    echo life_travel_get_connection_detection_script();
}

/**
 * Enregistre les ressources à mettre en cache pour le mode offline
 * Cette fonction est utilisée par d'autres bridges via life_travel_is_offline_cache_enabled()
 *
 * @param array $urls Liste d'URLs à mettre en cache
 * @return array Liste mise à jour
 */
function life_travel_register_offline_resources($urls = array()) {
    if (empty($urls)) {
        $urls = array();
    }
    
    // URLs essentielles
    $essential_urls = array(
        home_url('/'),
        home_url('/offline/'),
        LIFE_TRAVEL_EXCURSION_URL . 'offline/index.html',
        LIFE_TRAVEL_EXCURSION_URL . 'offline/assets/css/offline.css',
        LIFE_TRAVEL_EXCURSION_URL . 'offline/assets/js/offline.js',
        LIFE_TRAVEL_EXCURSION_URL . 'offline/assets/img/logo-offline.svg'
    );
    
    $urls = array_merge($urls, $essential_urls);
    
    // Permettre aux autres composants d'ajouter leurs ressources
    $urls = apply_filters('life_travel_offline_cache_urls', $urls);
    
    // Supprimer les doublons
    $urls = array_unique($urls);
    
    return $urls;
}

// Définir cette fonction comme partagée pour éviter les problèmes de dépendances circulaires
if (function_exists('life_travel_define_shared_function')) {
    life_travel_define_shared_function('life_travel_register_offline_resources_shared', 'life_travel_register_offline_resources');
}

// Initialiser le pont PWA - s'assurer que ces fonctions ne sont exécutées qu'une fois
if (!defined('LIFE_TRAVEL_PWA_INITIALIZED')) {
    life_travel_register_service_worker();
    life_travel_add_pwa_manifest();
    life_travel_generate_manifest();
    life_travel_generate_service_worker_endpoint();
    life_travel_add_connection_detection();
    
    define('LIFE_TRAVEL_PWA_INITIALIZED', true);
}

