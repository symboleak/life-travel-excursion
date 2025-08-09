<?php
/**
 * Pont de gestion des fonctionnalités hors ligne
 * 
 * Ce fichier assure la compatibilité entre l'ancien système de page hors ligne
 * et le nouveau système de messages personnalisables et adaptatifs.
 * 
 * @package Life Travel Excursion
 * @version 2.5.0
 */

defined('ABSPATH') || exit;

// S'assurer que le validateur de bridge est chargé
if (!function_exists('life_travel_register_bridge')) {
    require_once dirname(__FILE__) . '/bridge-validator.php';
}

// Enregistrer ce bridge auprès du validateur
life_travel_register_bridge('offline', '2.5.0', array(
    'life_travel_load_offline_system',
    'life_travel_generate_offline_page',
    'life_travel_offline_page_content',
    'life_travel_schedule_offline_sync',
    'life_travel_process_offline_sync',
    'life_travel_sync_reservation',
    'life_travel_sync_loyalty_points',
    'life_travel_ajax_sync_loyalty_points'
));

/**
 * Charge le système de gestion hors ligne approprié et génère
 * la page hors ligne optimisée.
 * 
 * @return bool True si un système a été chargé
 */
function life_travel_load_offline_system() {
    // Vérifier si le nouveau système est disponible
    $new_system_file = __DIR__ . '/class-life-travel-offline-messages.php';
    $use_new_offline_system = life_travel_bridge_get_option('life_travel_use_new_offline_system', null);
    
    // Si l'option n'existe pas encore, définir sur true pour les nouvelles installations
    if ($use_new_offline_system === null) {
        // Vérifier si une ancienne page offline.html personnalisée existe
        $offline_file = plugin_dir_path(dirname(__FILE__)) . 'offline.html';
        
        // Si la page existe et diffère de la version par défaut, conserver l'ancien système
        $use_new_offline_system = true;
        
        if (file_exists($offline_file)) {
            $offline_content = file_get_contents($offline_file);
            
            // Si la page a été personnalisée (différente du modèle par défaut), garder l'ancien système
            if (strpos($offline_content, 'Life Travel - Connexion perdue') === false) {
                $use_new_offline_system = false;
            }
        }
        
        update_option('life_travel_use_new_offline_system', $use_new_offline_system);
    }
    
    // Charger le système approprié
    if ($use_new_offline_system && file_exists($new_system_file)) {
        require_once $new_system_file;
        
        // Générer la nouvelle page offline.html lors de l'activation
        // Utiliser la fonction de génération de page offline centralisée pour éviter les dépendances circulaires
        if (!has_action('activate_life-travel-excursion/life-travel-excursion.php', 'life_travel_generate_offline_page')) {
            add_action('activate_life-travel-excursion/life-travel-excursion.php', 'life_travel_generate_offline_page');
        }
        
        return true;
    }
    
    return false;
}

/**
 * Génère une page hors ligne optimisée basée sur le nouveau système
 * Cette fonction est appelée lors de l'activation du plugin
 */
if (!function_exists('life_travel_generate_offline_page')) {
function life_travel_generate_offline_page() {
    // Vérifier si nous utilisons le nouveau système
    if (!life_travel_bridge_get_option('life_travel_use_new_offline_system', false)) {
        return;
    }
    
    // Chemin du fichier offline.html
    $offline_file = plugin_dir_path(dirname(__FILE__)) . 'offline.html';
    $source_file = plugin_dir_path(dirname(__FILE__)) . 'offline/index.html';
    
    // Si le fichier source existe, l'utiliser comme base
    if (file_exists($source_file)) {
        // Copier le fichier de la source vers la destination
        copy($source_file, $offline_file);
        return;
    }
    
    // Sinon, générer une page de base
    $content = get_offline_page_template();
    file_put_contents($offline_file, $content);
}
}

/**
 * Génère le contenu HTML de la page hors ligne
 * 
 * @return string Le contenu HTML
 */
function get_offline_page_template() {
    // Récupérer les messages personnalisés si disponibles
    $offline_messages = array();
    if (class_exists('Life_Travel_Offline_Messages')) {
        $messages_instance = life_travel_offline_messages();
        $offline_messages = $messages_instance->get_all_messages();
    }
    
    // Message par défaut si aucun message personnalisé n'est disponible
    $general_title = isset($offline_messages['general']['title']) ? 
        $offline_messages['general']['title'] : 
        'Vous êtes hors ligne';
        
    $general_message = isset($offline_messages['general']['message']) ? 
        $offline_messages['general']['message'] : 
        'Votre connexion internet semble interrompue. Certaines fonctionnalités de Life Travel sont limitées en mode hors ligne.';
        
    $action_text = isset($offline_messages['general']['action_text']) ? 
        $offline_messages['general']['action_text'] : 
        'Réessayer';
    
    // Construire le HTML avec syntaxe HEREDOC pour éviter les problèmes d'encodage
    $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mode Hors-Ligne - Life Travel Cameroun</title>
    <meta name="description" content="Vous êtes actuellement en mode hors-ligne. Certaines fonctionnalités de Life Travel ne sont pas disponibles.">
    <meta name="theme-color" content="#0073B2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" href="assets/img/logos/logo-main.svg" type="image/svg+xml">
    <!-- Polyfills pour compatibilité maximum (KaiOS, Opera Mini, UC Browser, anciens Android) -->
    <script>
        // Définition du namespace global pour éviter les collisions
        window.LIFE_TRAVEL = window.LIFE_TRAVEL || {};
        // Stocker les informations de compatibilité
        window.LIFE_TRAVEL.COMPAT = {
            isOperaMini: navigator.userAgent.indexOf("Opera Mini") > -1,
            isOldAndroid: navigator.userAgent.indexOf("Android") > -1 && 
                        parseFloat(navigator.userAgent.slice(navigator.userAgent.indexOf("Android") + 8)) < 4.4,
            isUCMini: navigator.userAgent.indexOf("UCBrowser") > -1 && navigator.userAgent.indexOf("Mini") > -1,
            hasServiceWorker: "serviceWorker" in navigator,
            hasPromise: typeof Promise !== "undefined",
            hasFetch: typeof fetch !== "undefined",
            hasIndexedDB: typeof indexedDB !== "undefined"
        };
        // Signal indiquant que les polyfills ne sont pas encore chargés
        window.LIFE_TRAVEL.POLYFILLS_LOADED = false;
    </script>
    <!-- Chargement des polyfills avec priorité absolue -->
    <script src="assets/js/life-travel-polyfills.js"></script>
    <style>
        /* Styles minimalistes pour la page hors-ligne */
        :root {
            --primary-color: #0073B2;
            --secondary-color: #FF9E00;
            --text-color: #333333;
            --light-gray: #f5f5f5;
            --border-color: #e0e0e0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--text-color);
            line-height: 1.6;
            background-color: #fff;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .offline-header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 30px;
        }
        
        .offline-logo {
            max-width: 200px;
            height: auto;
            margin-bottom: 20px;
        }
        
        .offline-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .offline-subtitle {
            font-size: 16px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .offline-status {
            background-color: #fff3cd;
            border: 1px solid #ffecb5;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .offline-section {
            background-color: var(--light-gray);
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        
        .offline-section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .offline-section p {
            margin-bottom: 15px;
        }
        
        .offline-section ul {
            padding-left: 20px;
            margin-bottom: 15px;
        }
        
        .offline-section li {
            margin-bottom: 8px;
        }
        
        .cached-links {
            list-style: none;
            padding: 0;
        }
        
        .cached-links li {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: #fff;
        }
        
        .cached-links a {
            display: block;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .cached-links a:hover {
            text-decoration: underline;
        }
        
        .retry-button {
            display: block;
            width: 100%;
            max-width: 200px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 30px auto;
            text-align: center;
        }
        
        .retry-button:hover {
            background-color: #005d8f;
        }
        
        .offline-footer {
            text-align: center;
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
            margin-top: 40px;
            font-size: 14px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .offline-title {
                font-size: 20px;
            }
            
            .offline-section {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <header class="offline-header">
        <img src="' . esc_url( LIFE_TRAVEL_EXCURSION_URL . 'assets/img/logos/logo-main.png' ) . '" alt="Life Travel Logo" class="offline-logo" onerror="this.style.display=\'none\'">
        <h1 class="offline-title">' . esc_html($general_title) . '</h1>
        <p class="offline-subtitle">Life Travel Cameroun - Mode Hors-Ligne</p>
    </header>
    
    <div class="offline-status">
        <strong>Vous êtes actuellement hors-ligne</strong>
        <p>' . esc_html($general_message) . '</p>
    </div>
    
    <section class="offline-section">
        <h2>Navigation hors-ligne</h2>
        <p>Certaines pages que vous avez déjà visitées sont disponibles :</p>
        <ul id="cached-pages" class="cached-links">
            <li>Chargement des pages en cache...</li>
        </ul>
    </section>
    
    <section class="offline-section">
        <h2>Fonctionnalités limitées</h2>
        <p>En mode hors-ligne, les fonctionnalités suivantes ne sont pas disponibles :</p>
        <ul>
            <li>Réservation d\'excursions</li>
            <li>Paiements et transactions</li>
            <li>Mise à jour des disponibilités</li>
            <li>Envoi de messages de contact</li>
            <li>Partage sur réseaux sociaux</li>
        </ul>
    </section>
    
    <button id="retry-connection" class="retry-button">' . esc_html($action_text) . '</button>
    
    <div class="offline-section">
        <h2>Conseils pour la navigation hors-ligne</h2>
        <p>Les actions que vous effectuez en mode hors-ligne seront enregistrées localement et synchronisées automatiquement lorsque vous serez à nouveau en ligne.</p>
        <p>Pour une meilleure expérience, nous vous recommandons de revenir sur le site une fois votre connexion rétablie.</p>
    </div>
    
    <!-- Section spécifique pour les appareils KaiOS (téléphones à touches populaires au Cameroun) -->
    <div id="kaios-help" class="offline-section kaios-section" style="display: none;">
        <h2>Navigation sur téléphone à touches</h2>
        <p>Utilisez ces touches sur votre téléphone KaiOS :</p>
        <div class="keyboard-shortcuts">
            <ul>
                <li><span class="key">2</span> (Haut) : Naviguer vers le haut</li>
                <li><span class="key">8</span> (Bas) : Naviguer vers le bas</li>
                <li><span class="key">5</span> (OK) : Sélectionner</li>
                <li><span class="key">4/6</span> (Gauche/Droite) : Changer de section</li>
                <li><span class="key">Retour</span> : Retour à la page précédente</li>
            </ul>
        </div>
        <p>Les éléments actuellement sélectionnés sont mis en évidence avec un contour bleu.</p>
    </div>
    
    <footer class="offline-footer">
        <p>&copy; ' . date('Y') . ' Life Travel Cameroun - Version hors-ligne</p>
        <p>Une meilleure expérience est disponible en ligne sur <strong>life-travel.cm</strong></p>
        <div id="softkeys" class="softkey" style="display: none;">
            <div id="softkey-left">Retour</div>
            <div id="softkey-center">Sélectionner</div>
            <div id="softkey-right">Options</div>
        </div>
    </footer>
    
    <script>
        // Attendre le chargement complet des polyfills avant d'executer le code principal
        function waitForPolyfills(callback) {
            if (window.LIFE_TRAVEL.POLYFILLS_LOADED) {
                callback();
            } else {
                window.addEventListener('lifeTravelPolyfillsReady', callback);
            }
        }
        
        // Fonction principale d'initialisation (executee apres le chargement des polyfills)
        function initOfflinePage() {
            // Détection de KaiOS et configuration de l'interface adaptative
            var isKaiOS = navigator.userAgent.indexOf('KAIOS') > -1;
            var kaiOSVersion = null;
            
            if (isKaiOS) {
                // Déterminer la version de KaiOS
                var match = navigator.userAgent.match(/KAIOS\/(\d+\.\d+)/);
                kaiOSVersion = match ? match[1] : '2.5'; // Version par défaut
                
                // Exposer les informations KaiOS globalement pour autres scripts
                window.LIFE_TRAVEL = window.LIFE_TRAVEL || {};
                window.LIFE_TRAVEL.KAIOS = {
                    isKaiOS: true,
                    version: kaiOSVersion,
                    hasKaiHardwareKeys: typeof navigator.getDeviceStorage === 'function',
                    isLowEndDevice: navigator.deviceMemory < 1 || navigator.hardwareConcurrency < 2
                };
                
                // Configurer l'interface spécifique à KaiOS
                setupKaiOSInterface();
            }
            
            // Ajouter des classes sur le body pour permettre des styles CSS adaptatifs
            var body = document.body;
            
            // Détection des navigateurs spécifiques
            if (window.LIFE_TRAVEL.COMPAT.isOperaMini) {
                body.classList.add('lt-opera-mini');
            }
            if (window.LIFE_TRAVEL.COMPAT.isOldAndroid) {
                body.classList.add('lt-old-android');
            }
            if (window.LIFE_TRAVEL.COMPAT.isUCMini) {
                body.classList.add('lt-uc-mini');
            }
            
            // Mode dégradé si les API critiques ne sont pas disponibles
            if (!window.LIFE_TRAVEL.COMPAT.hasPromise || !window.LIFE_TRAVEL.COMPAT.hasFetch) {
                body.classList.add('lt-degraded-mode');
            }
            
            // Fonction pour tenter de rétablir la connexion
            window.retryConnection = function() {
                try {
                    var button = document.getElementById('retry-connection');
                    button.textContent = 'Vérification de la connexion...';
                    button.disabled = true;
                    
                    var isOnline = navigator.onLine !== undefined ? navigator.onLine : false;
                    
                    if (isOnline) {
                        // Si nous sommes en ligne, recharger la page
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        // Si nous sommes toujours hors-ligne
                        setTimeout(function() {
                            button.textContent = 'Toujours hors-ligne - Réessayer';
                            button.disabled = false;
                            
                            var status = document.querySelector('.offline-status');
                            if (status) {
                                status.innerHTML = '<strong>Vous êtes toujours hors-ligne</strong><p>Veuillez activer votre connexion WiFi ou données mobiles et réessayer</p>';
                            }
                        }, 2000);
                    }
                } catch (e) {
                    // Gestion d'erreur pour les navigateurs problématiques
                    console.error('Erreur lors de la vérification de la connexion:', e.message || 'Erreur inconnue');
                    var button = document.getElementById('retry-connection');
                    if (button) {
                        button.textContent = 'Réessayer';
                        button.disabled = false;
                    }
                }
            };
            
            // Attacher le gestionnaire d'événement au bouton
            var retryButton = document.getElementById('retry-connection');
            if (retryButton) {
                retryButton.addEventListener('click', window.retryConnection);
            }
            
            // Écouter les changements de connectivité
            window.addEventListener('online', function() {
                var status = document.querySelector('.offline-status');
                if (status) {
                    status.innerHTML = '<strong>Connexion rétablie !</strong><p>Redirection vers le site en ligne dans 3 secondes...</p>';
                }
                
                setTimeout(function() {
                    window.location.reload();
                }, 3000);
            });
            
            // Configuration spécifique pour l'interface KaiOS
            function setupKaiOSInterface() {
                // Ajouter des classes spécifiques à KaiOS
                document.body.classList.add('lt-kaios');
                document.body.classList.add('lt-kaios-' + kaiOSVersion.replace('.', '-'));
                
                // Afficher les éléments d'aide KaiOS
                document.getElementById('kaios-help').style.display = 'block';
                document.getElementById('softkeys').style.display = 'flex';
                
                // Mise à jour des touches contextuelles (softkeys)
                document.addEventListener('focusin', function(e) {
                    var centerKey = document.getElementById('softkey-center');
                    if (!centerKey) return;
                    
                    if (e.target.tagName === 'BUTTON') {
                        centerKey.textContent = e.target.textContent;
                    } else if (e.target.tagName === 'A') {
                        centerKey.textContent = 'Ouvrir';
                    } else if (e.target.tagName === 'INPUT') {
                        centerKey.textContent = 'Modifier';
                    } else {
                        centerKey.textContent = 'Sélectionner';
                    }
                });
                
                // Configuration de la navigation au clavier
                if (typeof setupKeyboardNavigation !== 'function') {
                    // Une version simple de la navigation si le script complet n'est pas chargé
                    setupBasicKeyboardNavigation();
                }
                
                // Optimisation pour les appareils à faible mémoire
                if (window.LIFE_TRAVEL.KAIOS.isLowEndDevice) {
                    document.body.classList.add('lt-low-memory');
                    
                    // Réduire les éléments visuels non essentiels
                    var nonEssentialElements = document.querySelectorAll('.lt-nonessential');
                    for (var i = 0; i < nonEssentialElements.length; i++) {
                        nonEssentialElements[i].style.display = 'none';
                    }
                    
                    // Désactiver les animations
                    var style = document.createElement('style');
                    style.textContent = `* { transition: none !important; animation: none !important; }`;
                    document.head.appendChild(style);
                }
            }
            
            // Une implémentation basique de navigation au clavier
            function setupBasicKeyboardNavigation() {
                var focusableElements = [];
                var currentIndex = -1;
                
                // Trouver tous les éléments interactifs
                function refreshFocusableElements() {
                    var selector = 'a[href]:not([tabindex="-1"]), button:not([disabled]):not([tabindex="-1"]), ' + 
                                  'input:not([disabled]):not([type="hidden"]):not([tabindex="-1"]), select:not([disabled]):not([tabindex="-1"])';
                    
                    focusableElements = Array.from(document.querySelectorAll(selector))
                        .filter(function(el) { 
                            return el.offsetParent !== null && 
                                  getComputedStyle(el).display !== 'none' && 
                                  getComputedStyle(el).visibility !== 'hidden';
                        });
                    
                    // Si aucun élément n'a le focus, focus sur le premier
                    if (document.activeElement === document.body && focusableElements.length > 0) {
                        currentIndex = 0;
                        focusableElements[0].focus();
                    } else {
                        // Trouver l'index de l'élément actif
                        currentIndex = focusableElements.indexOf(document.activeElement);
                    }
                }
                
                // Gérer les événements clavier
                document.addEventListener('keydown', function(event) {
                    // Rafraîchir la liste des éléments focusables
                    if (focusableElements.length === 0) {
                        refreshFocusableElements();
                    }
                    
                    var handled = false;
                    
                    switch (event.key) {
                        case 'ArrowUp': // Touche 2 sur KaiOS
                            currentIndex = Math.max(0, currentIndex - 1);
                            handled = true;
                            break;
                            
                        case 'ArrowDown': // Touche 8 sur KaiOS
                            currentIndex = Math.min(focusableElements.length - 1, currentIndex + 1);
                            handled = true;
                            break;
                    }
                    
                    if (handled && focusableElements[currentIndex]) {
                        focusableElements[currentIndex].focus();
                        event.preventDefault();
                    }
                });
                
                // Observer les changements dans le DOM pour mettre à jour les éléments focusables
                if ('MutationObserver' in window) {
                    var observer = new MutationObserver(function() {
                        refreshFocusableElements();
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true,
                        attributes: true,
                        attributeFilter: ['style', 'class', 'hidden', 'disabled']
                    });
                }
                
                // Initialisation
                refreshFocusableElements();
            }
            
            // Vérifier l'état initial de la connexion
            if (navigator.onLine) {
                // Si nous sommes déjà en ligne, tenter de revenir au site principal
                setTimeout(function() {
                    window.location.href = '/';
                }, 1000);
            }
            
            // Fonction sécurisée pour obtenir un nom convivial de page
            function getPageName(pathname) {
                if (!pathname || pathname === '/') return 'Accueil';
                
                try {
                    var parts = pathname.split('/').filter(function(part) { return part !== ''; });
                    if (parts.length === 0) return 'Page inconnue';
                    
                    // Convertir le dernier segment en nom lisible
                    var lastPart = parts[parts.length - 1];
                    
                    // Nettoyer le texte
                    lastPart = lastPart.replace(/-|_/g, ' ');
                    
                    // Mettre en majuscule la première lettre
                    return lastPart.charAt(0).toUpperCase() + lastPart.slice(1);
                } catch (e) {
                    return 'Page ' + pathname;
                }
            }
            
            // Vérifier si le Cache API est disponible
            if ('caches' in window) {
                try {
                    // Tenter de récupérer la liste des pages en cache avec gestion d'erreur améliorée
                    caches.open('life-travel-cache-v1')
                        .then(function(cache) {
                            return cache.keys()
                                .then(function(requests) {
                                    // Filtrer pour ne garder que les URLs HTML (pages)
                                    var htmlRequests = [];
                                    
                                    // Utiliser une boucle for classique pour compatibilité max
                                    for (var i = 0; i < requests.length; i++) {
                                        var request = requests[i];
                                        try {
                                            var url = new URL(request.url);
                                            var path = url.pathname || '';
                                            var isResource = path.match(/\.(css|js|png|jpg|jpeg|gif|webp|svg|woff|woff2|ttf|eot)$/i);
                                            var isOffline = path === '/offline/' || path === '/offline.html';
                                            
                                            if (!isResource && !isOffline) {
                                                htmlRequests.push(request);
                                            }
                                        } catch (e) {
                                            console.warn('Erreur lors du traitement de l\'URL:', e.message);
                                        }
                                    }
                                    
                                    // Si nous avons trouvé des pages en cache
                                    var cachedPagesList = document.getElementById('cached-pages');
                                    if (cachedPagesList) {
                                        if (htmlRequests.length > 0) {
                                            cachedPagesList.innerHTML = '';
                                            
                                            // Limiter le nombre de pages affichées pour éviter les problèmes de mémoire
                                            var maxPages = Math.min(htmlRequests.length, 10);
                                            
                                            // Créer un élément de liste pour chaque page (max 10)
                                            for (var j = 0; j < maxPages; j++) {
                                                try {
                                                    var req = htmlRequests[j];
                                                    var reqUrl = new URL(req.url);
                                                    var pathname = reqUrl.pathname || '/';
                                                    
                                                    // Éviter les chemins non valides
                                                    if (pathname) {
                                                        var li = document.createElement('li');
                                                        var a = document.createElement('a');
                                                        
                                                        a.href = pathname;
                                                        a.textContent = getPageName(pathname);
                                                        
                                                        li.appendChild(a);
                                                        cachedPagesList.appendChild(li);
                                                    }
                                                } catch (e) {
                                                    console.warn('Erreur lors de la création d\'un élément de page:', e.message);
                                                }
                                            }
                                            
                                            // Indiquer s'il y a plus de pages que celles affichées
                                            if (htmlRequests.length > maxPages) {
                                                var moreLi = document.createElement('li');
                                                moreLi.className = 'more-pages';
                                                moreLi.textContent = '... et ' + (htmlRequests.length - maxPages) + ' autres pages';
                                                cachedPagesList.appendChild(moreLi);
                                            }
                                        } else {
                                            cachedPagesList.innerHTML = '<li>Aucune page en cache disponible</li>';
                                        }
                                    }
                                })
                                .catch(function(error) {
                                    console.warn('Erreur lors de la récupération des clés du cache:', error.message || 'Erreur inconnue');
                                    var cachedPages = document.getElementById('cached-pages');
                                    if (cachedPages) {
                                        cachedPages.innerHTML = '<li>Impossible de lire le cache</li>';
                                    }
                                });
                        })
                        .catch(function(error) {
                            console.warn('Erreur lors de l\'ouverture du cache:', error.message || 'Erreur inconnue');
                            var cachedPages = document.getElementById('cached-pages');
                            if (cachedPages) {
                                cachedPages.innerHTML = '<li>Impossible d\'accéder au cache</li>';
                            }
                        });
                } catch (e) {
                    console.error('Erreur générale lors de l\'accès au cache:', e.message || 'Erreur inconnue');
                    var cachedPages = document.getElementById('cached-pages');
                    if (cachedPages) {
                        cachedPages.innerHTML = '<li>Cache indisponible sur ce navigateur</li>';
                    }
                }
            } else {
                var cachedPages = document.getElementById('cached-pages');
                if (cachedPages) {
                    cachedPages.innerHTML = '<li>Cache non disponible sur ce navigateur</li>';
                }
            }
        }
        
        // Attacher le gestionnaire d'événement au bouton de rafraîchissement
        var button = document.getElementById('retry-connection');
        if (button) {
            button.addEventListener('click', retryConnection);
        }
        
        // Appeler waitForPolyfills pour exécuter initOfflinePage une fois les polyfills chargés
        waitForPolyfills(initOfflinePage);
    </script>
</body>
</html>
HTML;
}

/**
 * Vérifie si nous utilisons le nouveau système de gestion hors ligne
 * 
 * @return bool True si nous utilisons le nouveau système
 */
function life_travel_is_using_new_offline_system() {
    return get_option('life_travel_use_new_offline_system', false);
}

/**
 * Permet de basculer entre les deux systèmes de gestion hors ligne
 * 
 * @param bool $use_new_system True pour utiliser le nouveau système
 * @return bool Résultat de l'opération
 */
function life_travel_switch_offline_system($use_new_system = true) {
    $result = update_option('life_travel_use_new_offline_system', (bool) $use_new_system);
    
    // Regénérer la page hors ligne si nous passons au nouveau système
    if ($use_new_system) {
        life_travel_generate_offline_page();
    }
    
    return $result;
}

/**
 * Gère la synchronisation des données entre le mode hors ligne et en ligne
 * Spécifiquement adapté au contexte camerounais avec connectivité intermittente
 *
 * @param array $data Données à synchroniser
 * @param string $type Type de données (réservation, panier, etc.)
 * @return bool True si la synchronisation est programmée
 */
function life_travel_schedule_offline_sync($data, $type = 'reservation') {
    // Créer la queue de synchronisation si elle n'existe pas
    $sync_queue = get_option('life_travel_offline_sync_queue', array());
    
    // Ajouter les données à la queue avec un timestamp
    $sync_item = array(
        'data' => $data,
        'type' => $type,
        'timestamp' => time(),
        'attempts' => 0,
        'id' => uniqid('lt_sync_')
    );
    
    $sync_queue[] = $sync_item;
    
    // Sauvegarder la queue mise à jour
    update_option('life_travel_offline_sync_queue', $sync_queue);
    
    // Planifier la synchronisation si elle n'est pas déjà planifiée
    if (!wp_next_scheduled('life_travel_process_offline_sync')) {
        wp_schedule_event(time(), 'hourly', 'life_travel_process_offline_sync');
    }
    
    // Fournir une copie locale des données pour le stockage côté client
    return array(
        'success' => true,
        'sync_id' => $sync_item['id'],
        'message' => 'Synchronisation programmée pour quand la connexion sera stable'
    );
}

/**
 * Traite la queue de synchronisation
 * Utilise un système de backoff exponentiel adapté au contexte camerounais
 */
function life_travel_process_offline_sync() {
    // Récupérer la queue de synchronisation
    $sync_queue = get_option('life_travel_offline_sync_queue', array());
    
    if (empty($sync_queue)) {
        return;
    }
    
    // Vérifier la connectivité Internet
    $has_connectivity = life_travel_check_connectivity();
    
    if (!$has_connectivity) {
        // Pas de connectivité, réessayer plus tard
        return;
    }
    
    // Traiter chaque élément de la queue
    foreach ($sync_queue as $key => $item) {
        // Calculer le délai d'attente avec backoff exponentiel
        $backoff_time = pow(2, $item['attempts']) * 30; // 30s, 1m, 2m, 4m, 8m, etc.
        $time_since_last_attempt = time() - $item['timestamp'];
        
        // Si le temps de backoff n'est pas écoulé et ce n'est pas la première tentative, passer
        if ($item['attempts'] > 0 && $time_since_last_attempt < $backoff_time) {
            continue;
        }
        
        // Essayer de synchroniser l'élément selon son type
        $success = false;
        switch ($item['type']) {
            case 'reservation':
                $success = life_travel_sync_reservation($item['data']);
                break;
            case 'cart':
                $success = life_travel_sync_cart($item['data']);
                break;
            case 'user_preferences':
                $success = life_travel_sync_user_preferences($item['data']);
                break;
            default:
                // Type inconnu, considérer comme réussi pour ne pas bloquer
                $success = true;
        }
        
        if ($success) {
            // Synchronisation réussie, retirer de la queue
            unset($sync_queue[$key]);
        } else {
            // Échec, incrémenter le compteur de tentatives
            $sync_queue[$key]['attempts']++;
            $sync_queue[$key]['timestamp'] = time();
            
            // Abandonner après 10 tentatives (environ 17 heures de tentatives)
            if ($sync_queue[$key]['attempts'] >= 10) {
                // Journaliser l'échec permanent
                error_log(sprintf(
                    'Life Travel: Abandon de la synchronisation %s après 10 tentatives',
                    $item['id']
                ));
                unset($sync_queue[$key]);
            }
        }
    }
    
    // Réindexer le tableau
    $sync_queue = array_values($sync_queue);
    
    // Mettre à jour la queue
    update_option('life_travel_offline_sync_queue', $sync_queue);
    
    // Si la queue est vide, supprimer la planification
    if (empty($sync_queue)) {
        wp_clear_scheduled_hook('life_travel_process_offline_sync');
    }
}

/**
 * Vérifie la connexion Internet en testant des points d'extrémité fiables
 * Adaptée au contexte camerounais avec des tests de points d'extrémité locaux
 *
 * @return bool True si la connexion est stable
 */
function life_travel_check_connectivity() {
    // Liste des points d'extrémité à tester (certains locaux pour le Cameroun)
    $endpoints = array(
        'https://www.google.com',
        'https://www.camtel.cm',    // Fournisseur national camerounais
        'https://api.wordpress.org',
        'https://www.orange.cm'      // Opérateur majeur au Cameroun
    );
    
    // Récupérer un point d'extrémité aléatoire pour tester
    $endpoint = $endpoints[array_rand($endpoints)];
    
    // Faire une requête HTTP légère avec un petit timeout (5s)
    $response = wp_remote_get($endpoint, array(
        'timeout' => 5,
        'sslverify' => false  // Nécessaire dans certains contextes camerounais
    ));
    
    // Vérifier si la requête a réussi
    return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
}

/**
 * Synchronise une réservation depuis le stockage hors ligne
 *
 * @param array $data Données de réservation
 * @return bool True si la synchronisation a réussi
 */
function life_travel_sync_reservation($data) {
    // Sanitize les données entrantes
    $data = life_travel_sanitize_reservation_data($data);
    
    // Vérifier si la réservation existe déjà
    global $wpdb;
    $table_name = $wpdb->prefix . 'life_travel_reservations';
    
    $existing_id = isset($data['reservation_id']) ? $data['reservation_id'] : false;
    $exists = false;
    
    if ($existing_id) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE reservation_id = %s",
            $existing_id
        ));
    }
    
    if ($exists) {
        // Mettre à jour la réservation existante
        $result = $wpdb->update(
            $table_name,
            $data,
            array('reservation_id' => $existing_id)
        );
    } else {
        // Créer une nouvelle réservation
        $result = $wpdb->insert($table_name, $data);
    }
    
    // Journaliser l'action pour débogage
    if (!$result) {
        error_log(sprintf(
            'Life Travel: Échec de synchronisation de réservation - %s',
            $wpdb->last_error
        ));
    }
    
    return $result !== false;
}

/**
 * Sanitize les données de réservation
 *
 * @param array $data Données brutes
 * @return array Données nettoyées
 */
function life_travel_sanitize_reservation_data($data) {
    $clean_data = array();
    
    // Liste des champs attendus et leur fonction de sanitization
    // Attention : cette liste est utilisée aussi par le pont PWA, ne pas modifier sans coordination
    $fields = array(
        'reservation_id' => 'sanitize_text_field',
        'user_id' => 'intval',
        'excursion_id' => 'intval',
        'date_debut' => 'sanitize_text_field',
        'date_fin' => 'sanitize_text_field',
        'nb_adultes' => 'intval',
        'nb_enfants' => 'intval',
        'prix_total' => 'floatval',
        'extras' => 'sanitize_text_field',  // JSON encodé
        'statut' => 'sanitize_text_field',
        'created_at' => 'sanitize_text_field',
        'updated_at' => 'sanitize_text_field',
        'notes' => 'sanitize_textarea_field',
        'origin' => 'sanitize_text_field'
    );
    
    // Appliquer les fonctions de sanitization
    foreach ($fields as $field => $sanitize_function) {
        if (isset($data[$field])) {
            $clean_data[$field] = call_user_func($sanitize_function, $data[$field]);
        }
    }
    
    // Définir des valeurs par défaut pour les champs obligatoires manquants
    if (empty($clean_data['reservation_id'])) {
        $clean_data['reservation_id'] = 'LT-' . time() . '-' . wp_rand(1000, 9999);
    }
    
    if (!isset($clean_data['created_at'])) {
        $clean_data['created_at'] = current_time('mysql');
    }
    
    $clean_data['updated_at'] = current_time('mysql');
    $clean_data['origin'] = 'offline_sync';
    
    return $clean_data;
}

/**
 * Synchronise un panier abandonné depuis le stockage hors-ligne
 *
        'user_id' => $data['user_id'] ?? 'non-défini',
        'item_count' => count($data['items'])
    ]));
    
    // Initialisation du panier
    if (!function_exists('WC')) {
        error_log('Life Travel: WooCommerce non disponible pour synchronisation panier');
        return false;
    }
    
    // Préparation des données
    $user_id = (int)($data['user_id'] ?? 0);
    $cart_items = $data['items'];
    $cart_was_empty = WC()->cart->is_empty();
    $cart_mode = isset($data['mode']) ? $data['mode'] : 'merge'; // merge, replace
    
    // En mode remplacement, on vide d'abord le panier
    if ($cart_mode === 'replace' && !$cart_was_empty) {
        WC()->cart->empty_cart();
        error_log('Life Travel: Panier vidé avant synchronisation (mode remplacement)');
    }
    
    // Récupérer les produits actuels du panier pour comparaison/fusion
    $current_cart_items = [];
    if ($cart_mode === 'merge') {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $current_cart_items[$product_id] = [
                'key' => $cart_item_key,
                'quantity' => $cart_item['quantity'],
                'variation_id' => $cart_item['variation_id'] ?? 0,
                'variation' => $cart_item['variation'] ?? []
            ];
        }
    }
    
    // Timestamp de la dernière synchronisation pour cette session
    $last_sync = 0;
    if (!empty($data['session_id'])) {
        $last_sync = (int)get_transient('lte_cart_sync_' . sanitize_key($data['session_id']));
    }
    
    // Déterminer si ces données sont plus récentes
    $current_timestamp = (int)($data['timestamp'] ?? time());
    if ($last_sync > $current_timestamp) {
        error_log('Life Travel: Données de panier plus anciennes que la dernière synchronisation');
        return false; // Ne pas synchroniser des données plus anciennes
    }
    
    // Commencer le traitement des articles
    $added_count = 0;
    $updated_count = 0;
    $error_count = 0;
    
    foreach ($cart_items as $item) {
        // Validation des données obligatoires pour chaque article
        if (empty($item['product_id'])) {
            $error_count++;
            continue;
        }
        
        $product_id = (int)$item['product_id'];
        $variation_id = isset($item['variation_id']) ? (int)$item['variation_id'] : 0;
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        $cart_item_data = [];
        
        // Traitement des champs supplémentaires/métadonnées
        if (!empty($item['meta_data']) && is_array($item['meta_data'])) {
            foreach ($item['meta_data'] as $meta_key => $meta_value) {
                $cart_item_data[$meta_key] = $meta_value;
            }
        }
        
        // Traitement des dates pour excursions
        if (!empty($item['date_start']) && !empty($item['date_end'])) {
            $cart_item_data['date_start'] = sanitize_text_field($item['date_start']);
            $cart_item_data['date_end'] = sanitize_text_field($item['date_end']);
        }
        
        // Traitement des participants
        if (!empty($item['participants']) && is_numeric($item['participants'])) {
            $cart_item_data['participants'] = (int)$item['participants'];
        }
        
        // Vérifier si le produit existe déjà dans le panier (pour la fusion)
        if ($cart_mode === 'merge' && isset($current_cart_items[$product_id])) {
            // Mise à jour de la quantité si nécessaire
            $cart_item_key = $current_cart_items[$product_id]['key'];
            $current_qty = $current_cart_items[$product_id]['quantity'];
            
            if ($quantity !== $current_qty) {
                WC()->cart->set_quantity($cart_item_key, $quantity);
                $updated_count++;
            }
        } else {
            // Ajouter le produit au panier
            $cart_item_key = WC()->cart->add_to_cart(
                $product_id,
                $quantity,
                $variation_id,
                [], // variations
                $cart_item_data
            );
            
            if ($cart_item_key) {
                $added_count++;
            } else {
                $error_count++;
                error_log('Life Travel: Erreur lors de l\'ajout du produit ' . $product_id . ' au panier');
            }
        }
    }
    
    // Recalculer les totaux
    WC()->cart->calculate_totals();
    
    // Enregistrer timestamp pour cette session
    if (!empty($data['session_id'])) {
        set_transient('lte_cart_sync_' . sanitize_key($data['session_id']), time(), DAY_IN_SECONDS);
    }
    
    // Journaliser le résultat
    error_log('Life Travel: Synchronisation panier terminée - ' . json_encode([
        'ajoutés' => $added_count,
        'mis à jour' => $updated_count,
        'erreurs' => $error_count,
        'total_articles' => count(WC()->cart->get_cart())
    ]));
    
    // Mettre à jour métadonnées pour le suivi hors-ligne
    if ($user_id > 0) {
        update_user_meta($user_id, '_lte_last_cart_sync', time());
    }
    
    return $error_count === 0;
}

/**
 * Synchronise les préférences utilisateur depuis le stockage hors-ligne
 *
 * @param array $data Données de préférences utilisateur
 * @return bool True si la synchronisation a réussi
 */
function life_travel_sync_user_preferences($data) {
    // Vérification des données obligatoires
    if (empty($data) || !is_array($data) || empty($data['user_id'])) {
        error_log('Life Travel: Données de préférences utilisateur vides ou invalides');
        return false;
    }
    
    // Récupération de l'ID utilisateur
    $user_id = (int)$data['user_id'];
    
    // Vérifier que l'utilisateur existe
    if (!get_user_by('id', $user_id)) {
        error_log('Life Travel: Utilisateur ' . $user_id . ' introuvable pour synchronisation des préférences');
        return false;
    }
    
    // Log pour debugging
    error_log('Life Travel: Début synchronisation préférences utilisateur - ' . json_encode([
        'user_id' => $user_id,
        'timestamp' => $data['timestamp'] ?? time(),
        'device_id' => $data['device_id'] ?? 'non-défini',
    ]));
    
    // Timestamp de la dernière synchronisation pour cet utilisateur
    $last_sync = (int)get_user_meta($user_id, '_lte_preferences_sync_time', true);
    
    // Déterminer si ces données sont plus récentes
    $current_timestamp = (int)($data['timestamp'] ?? time());
    if ($last_sync > $current_timestamp) {
        error_log('Life Travel: Données de préférences plus anciennes que la dernière synchronisation');
        return false; // Ne pas synchroniser des données plus anciennes
    }
    
    // Collecte des préférences par catégorie
    $prefs_categories = [
        'interface' => isset($data['interface']) && is_array($data['interface']) ? $data['interface'] : [],
        'notifications' => isset($data['notifications']) && is_array($data['notifications']) ? $data['notifications'] : [],
        'favorite_excursions' => isset($data['favorite_excursions']) && is_array($data['favorite_excursions']) ? $data['favorite_excursions'] : [],
        'search_history' => isset($data['search_history']) && is_array($data['search_history']) ? $data['search_history'] : [],
        'display' => isset($data['display']) && is_array($data['display']) ? $data['display'] : [],
    ];
    
    // Traitement des préférences d'interface
    if (!empty($prefs_categories['interface'])) {
        $allowed_interface_prefs = [
            'theme_mode', // 'light', 'dark', 'auto'
            'text_size', // 'small', 'medium', 'large'
            'reduced_animations', // true, false
            'high_contrast', // true, false
            'data_saver' // true, false
        ];
        
        $interface_prefs = [];
        foreach ($prefs_categories['interface'] as $key => $value) {
            if (in_array($key, $allowed_interface_prefs)) {
                $interface_prefs[$key] = $value;
            }
        }
        
        if (!empty($interface_prefs)) {
            update_user_meta($user_id, '_lte_interface_preferences', $interface_prefs);
        }
    }
    
    // Traitement des préférences de notification
    if (!empty($prefs_categories['notifications'])) {
        $allowed_notification_prefs = [
            'email_promotions', // true, false
            'email_booking_updates', // true, false
            'push_enabled', // true, false
            'push_loyalty_updates', // true, false
            'push_booking_updates', // true, false
            'sms_enabled', // true, false
            'whatsapp_enabled', // true, false
            'quiet_hours_start', // HH:MM
            'quiet_hours_end' // HH:MM
        ];
        
        $notification_prefs = [];
        foreach ($prefs_categories['notifications'] as $key => $value) {
            if (in_array($key, $allowed_notification_prefs)) {
                $notification_prefs[$key] = $value;
            }
        }
        
        if (!empty($notification_prefs)) {
            update_user_meta($user_id, '_lte_notification_preferences', $notification_prefs);
        }
    }
    
    // Traitement des excursions favorites
    if (!empty($prefs_categories['favorite_excursions'])) {
        $existing_favorites = get_user_meta($user_id, '_lte_favorite_excursions', true);
        if (!is_array($existing_favorites)) {
            $existing_favorites = [];
        }
        
        $mode = isset($data['favorites_mode']) ? $data['favorites_mode'] : 'merge'; // merge, replace
        
        if ($mode === 'replace') {
            // Remplacer complètement la liste
            $new_favorites = [];
            foreach ($prefs_categories['favorite_excursions'] as $excursion_id) {
                $excursion_id = (int)$excursion_id;
                if ($excursion_id > 0 && get_post_type($excursion_id) === 'product') {
                    $new_favorites[] = $excursion_id;
                }
            }
            update_user_meta($user_id, '_lte_favorite_excursions', array_unique($new_favorites));
        } else {
            // Fusionner avec les favoris existants
            foreach ($prefs_categories['favorite_excursions'] as $fav_item) {
                // Format: {id: ID, action: 'add'|'remove'}
                if (isset($fav_item['id']) && isset($fav_item['action'])) {
                    $excursion_id = (int)$fav_item['id'];
                    $action = $fav_item['action'];
                    
                    if ($excursion_id > 0 && get_post_type($excursion_id) === 'product') {
                        if ($action === 'add' && !in_array($excursion_id, $existing_favorites)) {
                            $existing_favorites[] = $excursion_id;
                        } elseif ($action === 'remove') {
                            $existing_favorites = array_diff($existing_favorites, [$excursion_id]);
                        }
                    }
                }
            }
            update_user_meta($user_id, '_lte_favorite_excursions', array_values(array_unique($existing_favorites)));
        }
    }
    
    // Traitement de l'historique de recherche
    if (!empty($prefs_categories['search_history'])) {
        $existing_searches = get_user_meta($user_id, '_lte_search_history', true);
        if (!is_array($existing_searches)) {
            $existing_searches = [];
        }
        
        $mode = isset($data['search_history_mode']) ? $data['search_history_mode'] : 'append'; // append, replace
        
        if ($mode === 'replace') {
            // Limiter à 50 entrées maximum
            $new_searches = array_slice($prefs_categories['search_history'], 0, 50);
            update_user_meta($user_id, '_lte_search_history', $new_searches);
        } else {
            // Ajouter les nouvelles recherches au début
            $combined_searches = array_merge($prefs_categories['search_history'], $existing_searches);
            
            // Dédoublonner tout en préservant l'ordre
            $unique_searches = [];
            $seen_queries = [];
            
            foreach ($combined_searches as $search_entry) {
                if (isset($search_entry['query'])) {
                    $query = $search_entry['query'];
                    if (!isset($seen_queries[$query])) {
                        $unique_searches[] = $search_entry;
                        $seen_queries[$query] = true;
                    }
                }
            }
            
            // Limiter à 50 entrées maximum
            $unique_searches = array_slice($unique_searches, 0, 50);
            update_user_meta($user_id, '_lte_search_history', $unique_searches);
        }
    }
    
    // Traitement des préférences d'affichage
    if (!empty($prefs_categories['display'])) {
        $allowed_display_prefs = [
            'currency', // 'XAF', 'EUR', etc.
            'date_format', // 'DD/MM/YYYY', 'MM/DD/YYYY', etc.
            'language', // 'fr', 'en', etc.
            'map_type', // 'roadmap', 'satellite', etc.
            'distance_unit' // 'km', 'mi'
        ];
        
        $display_prefs = [];
        foreach ($prefs_categories['display'] as $key => $value) {
            if (in_array($key, $allowed_display_prefs)) {
                $display_prefs[$key] = $value;
            }
        }
        
        if (!empty($display_prefs)) {
            update_user_meta($user_id, '_lte_display_preferences', $display_prefs);
        }
    }
    
    // Détecter l'appareil
    if (!empty($data['device_info'])) {
        $devices = get_user_meta($user_id, '_lte_known_devices', true);
        if (!is_array($devices)) {
            $devices = [];
        }
        
        $device_id = !empty($data['device_id']) ? sanitize_key($data['device_id']) : md5(json_encode($data['device_info']));
        
        $devices[$device_id] = [
            'info' => $data['device_info'],
            'last_sync' => time(),
            'name' => $data['device_info']['name'] ?? __('Appareil inconnu', 'life-travel-excursion')
        ];
        
        update_user_meta($user_id, '_lte_known_devices', $devices);
    }
    
    // Mettre à jour le timestamp de synchronisation
    update_user_meta($user_id, '_lte_preferences_sync_time', time());
    
    // Journaliser le résultat
    error_log('Life Travel: Synchronisation des préférences utilisateur terminée pour l\'utilisateur ' . $user_id);
    
    // Déclencher une action pour d'autres extensions
    do_action('lte_user_preferences_synced', $user_id, $data);
    
    return true;
}

// Enregistrer le cron pour la synchronisation
add_action('life_travel_process_offline_sync', 'life_travel_process_offline_sync');

// Exposer la fonction de planification de synchronisation à AJAX
add_action('wp_ajax_life_travel_schedule_sync', 'life_travel_ajax_schedule_sync');
add_action('wp_ajax_nopriv_life_travel_schedule_sync', 'life_travel_ajax_schedule_sync');

/**
 * Gestionnaire AJAX pour la planification de synchronisation
 */
function life_travel_ajax_schedule_sync() {
    // Vérifier le nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_offline_sync')) {
        wp_send_json_error('Sécurité non valide');
    }
    
    // Récupérer les données et le type
    $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : array();
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'reservation';
    
    // Planifier la synchronisation
    $result = life_travel_schedule_offline_sync($data, $type);
    
    // Renvoyer le résultat
    wp_send_json_success($result);
}

// Définir ces fonctions comme partagées pour éviter les dépendances circulaires
if (function_exists('life_travel_define_shared_function')) {
    // Fonction de génération de page offline partagée avec le pont PWA
    life_travel_define_shared_function('life_travel_generate_offline_page_shared', 'life_travel_generate_offline_page');
    
    // Fonction de synchronisation offline partagée avec le pont PWA
    life_travel_define_shared_function('life_travel_sync_reservation_shared', 'life_travel_sync_reservation');
}

// S'assurer que le système n'est chargé qu'une seule fois
if (!defined('LIFE_TRAVEL_OFFLINE_INITIALIZED')) {
    // Charger le système approprié
    life_travel_load_offline_system();
    
    define('LIFE_TRAVEL_OFFLINE_INITIALIZED', true);
}

/**
 * Synchronise les points de fidélité depuis le stockage hors-ligne
 *
 * @param array $transactions Transactions de points en attente
 * @param int $user_id ID de l'utilisateur (optionnel)
 * @return array Résultats de la synchronisation
 */
function life_travel_sync_loyalty_points($transactions, $user_id = 0) {
    // Vérification des données obligatoires
    if (empty($transactions) || !is_array($transactions)) {
        return [
            'status' => 'error',
            'code' => 'invalid_data',
            'message' => __('Données de transactions invalides', 'life-travel-excursion'),
            'synced' => 0,
            'failed' => 0,
            'synced_ids' => []
        ];
    }
    
    // Si l'ID utilisateur n'est pas fourni, utiliser l'utilisateur connecté
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    // Vérifier que l'utilisateur est valide
    if (!$user_id) {
        return [
            'status' => 'error',
            'code' => 'invalid_user',
            'message' => __('Utilisateur non valide', 'life-travel-excursion'),
            'synced' => 0,
            'failed' => 0,
            'synced_ids' => []
        ];
    }
    
    // Statistiques pour le suivi
    $stats = [
        'synced' => 0,
        'failed' => 0,
        'synced_ids' => []
    ];
    
    // Obtenir l'instance du gestionnaire de fidélité
    $loyalty_manager = Life_Travel_Loyalty_Excursions::get_instance();
    
    // Traiter chaque transaction
    foreach ($transactions as $transaction) {
        // Vérifier les données minimales requises
        if (empty($transaction['id']) || empty($transaction['points']) || empty($transaction['action'])) {
            $stats['failed']++;
            continue;
        }
        
        // Convertir le nombre de points en entier
        $points = absint($transaction['points']);
        
        // Vérifier que le nombre de points est valide
        if ($points <= 0) {
            $stats['failed']++;
            continue;
        }
        
        // Préparer les détails supplémentaires
        $details = !empty($transaction['details']) ? $transaction['details'] : [];
        
        // Ajouter la source de la transaction
        $source = !empty($transaction['source']) ? $transaction['source'] : 'offline_sync';
        
        // Enregistrer la transaction pour traitement
        $result = $loyalty_manager->store_offline_points(
            $user_id,
            $points,
            $transaction['action'],
            [
                'source' => $source,
                'details' => $details,
                'offline_id' => $transaction['id'],
                'timestamp' => isset($transaction['timestamp']) ? $transaction['timestamp'] : time()
            ]
        );
        
        // Ajouter aux statistiques
        if ($result) {
            $stats['synced']++;
            $stats['synced_ids'][] = $transaction['id'];
        } else {
            $stats['failed']++;
        }
    }
    
    // Tenter de synchroniser les transactions en attente
    $sync_result = $loyalty_manager->sync_offline_points($user_id);
    
    // Récupérer le solde actuel de points
    $current_points = $loyalty_manager->get_user_loyalty_points($user_id);
    
    // Mettre à jour les métadonnées utilisateur pour le suivi
    update_user_meta($user_id, '_lte_last_loyalty_sync', time());
    
    // Journalisation pour le débogage
    error_log(sprintf(
        'Life Travel: Synchronisation des points de fidélité - %d transactions traitées, %d en succès, %d en échec',
        count($transactions),
        $stats['synced'],
        $stats['failed']
    ));
    
    // Retourner les résultats
    return [
        'status' => 'success',
        'synced' => $stats['synced'],
        'failed' => $stats['failed'],
        'synced_ids' => $stats['synced_ids'],
        'current_points' => $current_points,
        'message' => sprintf(
            __('%d transaction(s) synchronisée(s), %d échec(s)', 'life-travel-excursion'),
            $stats['synced'],
            $stats['failed']
        )
    ];
}

/**
 * Handler AJAX pour la synchronisation des points de fidélité
 */
function life_travel_ajax_sync_loyalty_points() {
    // Vérifier la sécurité
    if (!check_ajax_referer('lte_sync_loyalty_points', 'nonce', false)) {
        wp_send_json_error([
            'message' => __('Erreur de sécurité', 'life-travel-excursion')
        ]);
        wp_die();
    }
    
    // Récupérer l'utilisateur actuel
    $user_id = get_current_user_id();
    
    // Vérifier que l'utilisateur est connecté
    if (!$user_id) {
        wp_send_json_error([
            'message' => __('Utilisateur non connecté', 'life-travel-excursion')
        ]);
        wp_die();
    }
    
    // Récupérer les transactions à synchroniser
    $transactions = isset($_POST['transactions']) ? json_decode(stripslashes($_POST['transactions']), true) : [];
    
    // Si aucune transaction, retourner succès avec zéro synchronisations
    if (empty($transactions)) {
        wp_send_json_success([
            'message' => __('Aucune transaction à synchroniser', 'life-travel-excursion'),
            'synced' => 0,
            'failed' => 0,
            'synced_ids' => []
        ]);
        wp_die();
    }
    
    // Synchroniser les points
    $result = life_travel_sync_loyalty_points($transactions, $user_id);
    
    // Retourner la réponse
    if ($result['status'] === 'success') {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
    
    wp_die();
}

// Enregistrer le gestionnaire AJAX pour la synchronisation des points
add_action('wp_ajax_lte_sync_loyalty_points', 'life_travel_ajax_sync_loyalty_points');
add_action('wp_ajax_nopriv_lte_sync_loyalty_points', 'life_travel_ajax_sync_loyalty_points');