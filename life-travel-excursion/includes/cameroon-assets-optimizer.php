<?php
/**
 * Optimiseur d'assets adapté aux conditions réseau du Cameroun
 *
 * Ce fichier contient des optimisations spécifiques pour le chargement des assets JS/CSS
 * dans des environnements à faible connectivité et bande passante limitée.
 *
 * @package Life Travel Excursion
 * @since 3.0.0
 */

// Protéger contre l'accès direct
defined('ABSPATH') || exit;

/**
 * Classe d'optimisation des assets pour faible connectivité
 */
class Life_Travel_Cameroon_Assets_Optimizer {
    /**
     * Instance unique (Singleton)
     * @var Life_Travel_Cameroon_Assets_Optimizer
     */
    private static $instance = null;
    
    /**
     * Configuration de l'optimisation
     * @var array
     */
    private $config;
    
    /**
     * Statut de la connexion réseau
     * @var string slow|very_slow|offline|normal
     */
    private $network_status = 'normal';
    
    /**
     * Scripts dont le chargement est reporté
     * @var array
     */
    private $deferred_scripts = [];
    
    /**
     * Liste des modules JS critiques déjà chargés
     * @var array
     */
    private $loaded_modules = [];
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        // Charger la configuration
        $this->config = [
            'enabled' => true,
            'prefetch_threshold' => 1000, // ms
            'critical_scripts' => [
                'jquery',
                'life-travel-core',
                'life-travel-network-detector'
            ],
            'deferred_scripts' => [
                'life-travel-social-share',
                'life-travel-lightbox',
                'life-travel-map',
                'life-travel-video-player',
                'life-travel-image-slider'
            ],
            'module_dependencies' => [
                'price-calculator' => ['jquery', 'life-travel-core'],
                'booking-form' => ['jquery', 'life-travel-core', 'price-calculator'],
                'extras-manager' => ['jquery', 'life-travel-core'],
                'activities-selector' => ['jquery', 'life-travel-core']
            ]
        ];
        
        // Initialiser les hooks uniquement si activé
        if ($this->config['enabled']) {
            $this->init_hooks();
        }
    }
    
    /**
     * Obtenir l'instance unique
     * @return Life_Travel_Cameroon_Assets_Optimizer
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Récupère les statistiques de performance réseau pour le tableau de bord
     * 
     * Fournit des métriques sur la vitesse du réseau, la stabilité et la
     * capacité hors-ligne en se basant sur les tests effectués et les
     * données collectées lors des visites précédentes.
     *
     * @return array Statistiques réseau (vitesse, stabilité, support hors-ligne)
     */
    public function get_network_stats() {
        // Essayer de récupérer depuis le cache
        $cached_stats = get_transient('life_travel_cameroon_network_stats');
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        
        // Initialiser les statistiques
        $stats = array(
            'speed' => 'unknown',
            'stability' => 'unknown',
            'offline_capable' => false,
            'adaptive_loading' => false,
            'local_cache_hits' => 0,
            'local_cache_misses' => 0,
            'average_load_time' => 0,
            'connection_drops' => 0
        );
        
        // Récupérer le statut réseau actuel
        $stats['speed'] = $this->network_status;
        
        // Charge les données de connexion historiques
        $history = get_option('life_travel_connection_history', array());
        
        if (!empty($history)) {
            // Calculer la stabilité basée sur l'historique
            $connection_drops = 0;
            $total_checks = count($history);
            $speed_sum = 0;
            
            foreach ($history as $entry) {
                if (isset($entry['status']) && $entry['status'] === 'offline') {
                    $connection_drops++;
                }
                
                if (isset($entry['load_time'])) {
                    $speed_sum += floatval($entry['load_time']);
                }
            }
            
            // Calculer les statistiques
            $stats['connection_drops'] = $connection_drops;
            $stats['average_load_time'] = $total_checks > 0 ? round($speed_sum / $total_checks, 2) : 0;
            
            // Déterminer la stabilité
            $stability_ratio = $total_checks > 0 ? (($total_checks - $connection_drops) / $total_checks) : 0;
            
            if ($stability_ratio > 0.9) {
                $stats['stability'] = 'good';
            } elseif ($stability_ratio > 0.7) {
                $stats['stability'] = 'medium';
            } else {
                $stats['stability'] = 'poor';
            }
        }
        
        // Vérifier si le support hors-ligne est activé
        $stats['offline_capable'] = get_option('life_travel_offline_support', false);
        
        // Vérifier si le chargement adaptatif est actif
        $stats['adaptive_loading'] = get_option('life_travel_adaptive_loading', true);
        
        // Obtenir les statistiques de cache local
        $cache_stats = get_option('life_travel_local_cache_stats', array('hits' => 0, 'misses' => 0));
        $stats['local_cache_hits'] = $cache_stats['hits'] ?? 0;
        $stats['local_cache_misses'] = $cache_stats['misses'] ?? 0;
        
        // Sauvegarder dans le cache pour 5 minutes
        set_transient('life_travel_cameroon_network_stats', $stats, 5 * MINUTE_IN_SECONDS);
        
        return $stats;
    }
    
    /**
     * Initialiser les hooks WordPress
     */
    private function init_hooks() {
        // Priorité 5 pour s'exécuter avant l'enregistrement des scripts
        add_action('wp_enqueue_scripts', [$this, 'detect_network_status'], 5);
        
        // Priorité 11 pour s'exécuter après l'enregistrement standard des scripts
        add_action('wp_enqueue_scripts', [$this, 'optimize_assets_loading'], 11);
        
        // Ajouter les préchargements dans le header
        add_action('wp_head', [$this, 'add_preload_hints'], 2);
        
        // Ajouter le script de chargement asynchrone dans le footer
        add_action('wp_footer', [$this, 'add_deferred_loader'], 999);
        
        // Filtrer les attributs des balises script et style
        add_filter('script_loader_tag', [$this, 'optimize_script_tag'], 10, 3);
        add_filter('style_loader_tag', [$this, 'optimize_style_tag'], 10, 4);
        
        // Ajouter les headers de cache optimisés
        add_action('send_headers', [$this, 'add_optimized_cache_headers']);
        
        // Ajouter les actions AJAX pour le chargement des modules à la demande
        add_action('wp_ajax_life_travel_load_module', [$this, 'ajax_load_module']);
        add_action('wp_ajax_nopriv_life_travel_load_module', [$this, 'ajax_load_module']);
    }
    
    /**
     * Détecter le statut du réseau (adapté au contexte camerounais)
     */
    public function detect_network_status() {
        // Si la fonction de détection du réseau existe, l'utiliser
        if (function_exists('life_travel_network_status')) {
            $this->network_status = life_travel_network_status();
            return;
        }
        
        // Sinon, utiliser notre propre détection basique
        // Vérifier si le cookie de statut réseau existe
        if (isset($_COOKIE['lte_network_status'])) {
            $status = sanitize_key($_COOKIE['lte_network_status']);
            if (in_array($status, ['slow', 'very_slow', 'offline', 'normal'])) {
                $this->network_status = $status;
            }
        }
        
        // Activer la détection côté client
        wp_enqueue_script(
            'life-travel-network-detector',
            plugins_url('assets/js/network-detector.js', dirname(__FILE__)),
            [],
            LIFE_TRAVEL_EXCURSION_VERSION,
            false
        );
        
        // Passer le statut actuel au script
        wp_localize_script('life-travel-network-detector', 'lteNetworkConfig', [
            'status' => $this->network_status,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'pingInterval' => ($this->network_status === 'very_slow') ? 30000 : 10000 // ms
        ]);
    }
    
    /**
     * Optimiser le chargement des assets en fonction de la qualité du réseau
     */
    public function optimize_assets_loading() {
        // Désactiver les scripts différables si le réseau est très lent/hors ligne
        if (in_array($this->network_status, ['very_slow', 'offline'])) {
            foreach ($this->config['deferred_scripts'] as $handle) {
                // Si le script est enregistré, le désinscrire et le marquer pour chargement différé
                if (wp_script_is($handle, 'enqueued')) {
                    wp_dequeue_script($handle);
                    $this->deferred_scripts[] = $handle;
                }
            }
            
            // Appliquer des optimisations plus agressives pour le réseau hors ligne
            if ($this->network_status === 'offline') {
                $this->apply_offline_optimizations();
            }
        }
        
        // Découper le JS monolithique en modules plus petits
        $this->split_frontend_js();
        
        // Ajouter le script principal d'optimisation
        wp_enqueue_script(
            'life-travel-cameroon-optimizer',
            plugins_url('assets/js/cameroon-optimizer.js', dirname(__FILE__)),
            ['jquery'],
            LIFE_TRAVEL_EXCURSION_VERSION,
            true
        );
        
        // Passer la configuration au script
        wp_localize_script('life-travel-cameroon-optimizer', 'lteCameroonConfig', [
            'networkStatus' => $this->network_status,
            'deferredScripts' => $this->deferred_scripts,
            'moduleMap' => $this->get_module_map(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('life_travel_load_module')
        ]);
    }
    
    /**
     * Découper le JavaScript monolithique en modules plus petits et chargés à la demande
     */
    private function split_frontend_js() {
        // Si on est sur une page d'excursion, charger uniquement les modules nécessaires
        if (is_singular('product') && has_term('excursion', 'product_cat')) {
            // Désinscrire le script monolithique s'il est enregistré
            if (wp_script_is('life-travel-excursion-frontend', 'enqueued')) {
                wp_dequeue_script('life-travel-excursion-frontend');
                
                // Charger uniquement le module de base
                wp_enqueue_script(
                    'life-travel-core',
                    plugins_url('assets/js/modules/core.js', dirname(__FILE__)),
                    ['jquery'],
                    LIFE_TRAVEL_EXCURSION_VERSION,
                    true
                );
                
                $this->loaded_modules[] = 'core';
                
                // Charger conditionnellement le module de calculateur de prix
                if ($this->is_booking_form_visible()) {
                    wp_enqueue_script(
                        'life-travel-price-calculator',
                        plugins_url('assets/js/modules/price-calculator.js', dirname(__FILE__)),
                        ['jquery', 'life-travel-core'],
                        LIFE_TRAVEL_EXCURSION_VERSION,
                        true
                    );
                    
                    $this->loaded_modules[] = 'price-calculator';
                }
            }
        }
    }
    
    /**
     * Vérifier si le formulaire de réservation est visible sur la page actuelle
     * @return bool
     */
    private function is_booking_form_visible() {
        global $post;
        
        if (!is_singular('product')) {
            return false;
        }
        
        // Vérifier si le produit est une excursion
        if (!has_term('excursion', 'product_cat', $post->ID)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtenir la liste des modules JS et leurs dépendances
     * @return array
     */
    private function get_module_map() {
        return [
            'core' => [
                'path' => plugins_url('assets/js/modules/core.js', dirname(__FILE__)),
                'deps' => ['jquery']
            ],
            'price-calculator' => [
                'path' => plugins_url('assets/js/modules/price-calculator.js', dirname(__FILE__)),
                'deps' => ['jquery', 'core']
            ],
            'booking-form' => [
                'path' => plugins_url('assets/js/modules/booking-form.js', dirname(__FILE__)),
                'deps' => ['jquery', 'core', 'price-calculator']
            ],
            'extras-manager' => [
                'path' => plugins_url('assets/js/modules/extras-manager.js', dirname(__FILE__)),
                'deps' => ['jquery', 'core']
            ],
            'activities-selector' => [
                'path' => plugins_url('assets/js/modules/activities-selector.js', dirname(__FILE__)),
                'deps' => ['jquery', 'core']
            ]
        ];
    }
    
    /**
     * Appliquer des optimisations pour le mode hors ligne
     */
    private function apply_offline_optimizations() {
        // Désinscrire tous les scripts non essentiels
        $essential_scripts = ['jquery', 'life-travel-core', 'life-travel-offline-manager'];
        
        global $wp_scripts;
        
        if (!empty($wp_scripts->queue)) {
            foreach ($wp_scripts->queue as $handle) {
                if (!in_array($handle, $essential_scripts)) {
                    wp_dequeue_script($handle);
                }
            }
        }
        
        // Charger le gestionnaire hors ligne
        wp_enqueue_script(
            'life-travel-offline-manager',
            plugins_url('assets/js/offline-manager.js', dirname(__FILE__)),
            ['jquery'],
            LIFE_TRAVEL_EXCURSION_VERSION,
            true
        );
    }
    
    /**
     * Ajouter des indices de préchargement pour les ressources importantes
     */
    public function add_preload_hints() {
        // Uniquement si le réseau est lent ou normal
        if (in_array($this->network_status, ['offline', 'very_slow'])) {
            return;
        }
        
        // Liste des ressources à précharger
        $preload_resources = [];
        
        // Précharger le core JS (toujours nécessaire)
        $preload_resources[] = [
            'url' => plugins_url('assets/js/modules/core.js', dirname(__FILE__)),
            'as' => 'script'
        ];
        
        // Sur les pages d'excursion, précharger le calculateur de prix
        if ($this->is_booking_form_visible()) {
            $preload_resources[] = [
                'url' => plugins_url('assets/js/modules/price-calculator.js', dirname(__FILE__)),
                'as' => 'script'
            ];
        }
        
        // Générer les balises link rel="preload"
        foreach ($preload_resources as $resource) {
            printf(
                '<link rel="preload" href="%s" as="%s" crossorigin="anonymous">',
                esc_url($resource['url']),
                esc_attr($resource['as'])
            );
        }
    }
    
    /**
     * Ajouter le chargeur de scripts différés dans le footer
     */
    public function add_deferred_loader() {
        // Ne rien faire si aucun script n'est différé
        if (empty($this->deferred_scripts)) {
            return;
        }
        
        // Générer le HTML du chargeur différé
        ?>
        <script>
        (function() {
            var loadDeferredScripts = function() {
                // Ne charger les scripts différés que si le réseau est disponible
                if ('online' in navigator && !navigator.onLine) {
                    console.log('Réseau hors ligne, chargement des scripts différés annulé');
                    return;
                }
                
                var scripts = <?php echo json_encode($this->deferred_scripts); ?>;
                var loadScript = function(src, callback) {
                    var script = document.createElement('script');
                    script.src = src;
                    script.async = true;
                    script.onload = callback || null;
                    document.body.appendChild(script);
                };
                
                var loadNext = function(index) {
                    if (index >= scripts.length) return;
                    var script = scripts[index];
                    var url = script.startsWith('http') ? script : '<?php echo esc_js(plugins_url('assets/js/', dirname(__FILE__))); ?>' + script + '.js';
                    loadScript(url, function() {
                        loadNext(index + 1);
                    });
                };
                
                loadNext(0);
            };
            
            // Déclencher le chargement après que la page soit complètement chargée
            if (document.readyState === 'complete') {
                setTimeout(loadDeferredScripts, 100);
            } else {
                window.addEventListener('load', function() {
                    setTimeout(loadDeferredScripts, 100);
                });
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Optimiser les balises script
     * 
     * @param string $tag    Balise script
     * @param string $handle Identifiant du script
     * @param string $src    URL du script
     * @return string        Balise script optimisée
     */
    public function optimize_script_tag($tag, $handle, $src) {
        // Ne pas modifier les scripts dans l'admin
        if (is_admin()) {
            return $tag;
        }
        
        // Ajouter async pour les scripts non-critiques
        if (!in_array($handle, $this->config['critical_scripts'])) {
            // Vérifier d'abord si async ou defer est déjà présent
            if (strpos($tag, 'async') === false && strpos($tag, 'defer') === false) {
                // Ajouter async ou defer selon la criticité
                if (strpos($handle, 'analytics') !== false || strpos($handle, 'tracking') !== false) {
                    $tag = str_replace('<script ', '<script async ', $tag);
                } else {
                    $tag = str_replace('<script ', '<script defer ', $tag);
                }
            }
        }
        
        return $tag;
    }
    
    /**
     * Optimiser les balises style
     * 
     * @param string $tag    Balise link
     * @param string $handle Identifiant du style
     * @param string $href   URL du style
     * @param string $media  Media query
     * @return string        Balise link optimisée
     */
    public function optimize_style_tag($tag, $handle, $href, $media) {
        // Ne pas modifier les styles dans l'admin
        if (is_admin()) {
            return $tag;
        }
        
        // Charger les styles non-critiques de manière asynchrone
        if (!in_array($handle, ['life-travel-core-style'])) {
            if (strpos($tag, 'media') !== false) {
                // Si un media query existe déjà, conserver sa valeur originale
                $original_media = $media ?: 'all';
                
                // Transformer en chargement asynchrone
                $tag = str_replace('media=', 'data-original-media=', $tag);
                $tag = str_replace('rel=\'stylesheet\'', 'rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\';this.media=this.getAttribute(\'data-original-media\')" media="print"', $tag);
                
                // Ajouter fallback pour le non-JS
                $tag .= "\n<noscript><link rel='stylesheet' id='{$handle}-noscript' href='{$href}' media='{$original_media}' /></noscript>";
            }
        }
        
        return $tag;
    }
    
    /**
     * Ajouter des headers optimisés pour le cache
     */
    public function add_optimized_cache_headers() {
        // Définir des directives de cache agressives pour les assets statiques
        if (is_admin()) {
            return;
        }
        
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        // Détecter si c'est un fichier statique (CSS, JS, images, etc.)
        $static_extensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'woff', 'woff2', 'ttf', 'eot'];
        $is_static = false;
        
        foreach ($static_extensions as $ext) {
            if (strpos($request_uri, '.' . $ext) !== false) {
                $is_static = true;
                break;
            }
        }
        
        if ($is_static) {
            // Cache très agressif pour les assets statiques, spécialement adapté aux réseaux lents
            // Pour les navigateurs
            header('Cache-Control: public, max-age=31536000, immutable');
            // Pour les CDN et proxies
            header('Surrogate-Control: public, max-age=31536000');
            
            // Headers de compression
            if (function_exists('gzencode') && strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
                header('Content-Encoding: gzip');
            }
            
            // ETag pour la validation
            $etag = '"' . md5($request_uri . filemtime($_SERVER['SCRIPT_FILENAME'])) . '"';
            header('ETag: ' . $etag);
            
            // Vérifier si l'asset est déjà en cache
            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
                // L'asset est déjà en cache, renvoyer 304 Not Modified
                header('HTTP/1.1 304 Not Modified');
                exit;
            }
        }
    }
    
    /**
     * Gestionnaire AJAX pour charger des modules JS à la demande
     */
    public function ajax_load_module() {
        // Vérification de sécurité
        check_ajax_referer('life_travel_load_module', 'nonce');
        
        // Récupérer le module demandé
        $module = isset($_POST['module']) ? sanitize_key($_POST['module']) : '';
        
        if (empty($module)) {
            wp_send_json_error(['message' => 'Module non spécifié']);
            return;
        }
        
        // Récupérer la carte des modules
        $module_map = $this->get_module_map();
        
        // Vérifier si le module existe
        if (!isset($module_map[$module])) {
            wp_send_json_error(['message' => 'Module inconnu']);
            return;
        }
        
        // Récupérer le chemin du fichier
        $file_path = str_replace(plugins_url('', dirname(__FILE__)), WP_PLUGIN_DIR, $module_map[$module]['path']);
        
        // Vérifier si le fichier existe
        if (!file_exists($file_path)) {
            wp_send_json_error(['message' => 'Module introuvable']);
            return;
        }
        
        // Lire le contenu du fichier
        $content = file_get_contents($file_path);
        
        // Envoyer le contenu
        wp_send_json_success([
            'module' => $module,
            'content' => $content
        ]);
    }
    
    /**
     * Vérifier si un module est déjà chargé
     * 
     * @param string $module Nom du module
     * @return bool True si le module est chargé
     */
    public function is_module_loaded($module) {
        return in_array($module, $this->loaded_modules);
    }
}

/**
 * Obtenir l'instance de l'optimiseur d'assets pour le Cameroun
 * 
 * @return Life_Travel_Cameroon_Assets_Optimizer
 */
function life_travel_cameroon_assets() {
    return Life_Travel_Cameroon_Assets_Optimizer::get_instance();
}

// Initialiser l'optimiseur
life_travel_cameroon_assets();
