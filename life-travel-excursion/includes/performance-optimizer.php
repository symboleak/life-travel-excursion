<?php
/**
 * Optimiseur de performances pour Life Travel Excursion
 * 
 * Ce fichier centralise toutes les stratégies d'optimisation des performances
 * pour offrir une expérience utilisateur fluide sur tous les appareils
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

defined('ABSPATH') || exit;

// Charger la configuration centralisée
require_once __DIR__ . '/config.php';

/**
 * Classe responsable de l'optimisation des performances
 */
class Life_Travel_Performance_Optimizer {
    
    /**
     * Cache interne pour les requêtes fréquentes
     * @var array
     */
    private $cache = [];
    
    /**
     * Durée de mise en cache des requêtes (en secondes)
     * @var int
     */
    private $cache_ttl = 3600; // 1 heure
    
    /**
     * Paramètres d'optimisation des images
     * @var array
     */
    private $image_optimization = [];
    
    /**
     * Délai de chargement différé (en ms)
     * @var int
     */
    private $lazy_load_delay = 200;
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Initialiser les paramètres d'optimisation des images
        $this->image_optimization = [
            'enable_webp' => true,
            'quality' => 80,
            'max_width' => 1600,
            'optimize_thumbnails' => true
        ];
        
        // Hooks pour l'optimisation des performances
        add_action('wp_enqueue_scripts', [$this, 'optimize_assets_loading'], 999);
        add_filter('script_loader_tag', [$this, 'add_async_defer_attributes'], 10, 3);
        add_action('wp_head', [$this, 'add_resource_hints'], 1);
        add_action('template_redirect', [$this, 'maybe_disable_emojis']);
        add_filter('wp_resource_hints', [$this, 'add_preconnect_hints'], 10, 2);
        
        // Optimisation des requêtes API
        add_filter('life_travel_api_request_args', [$this, 'optimize_api_requests']);
        
        // Support pour HTTP/2 Server Push (si disponible)
        add_action('wp_head', [$this, 'http2_resource_hints'], 0);
        
        // Optimisation des requêtes de base de données
        add_action('init', [$this, 'setup_query_optimization']);
        
        // Gestion de cache avancée
        add_action('init', [$this, 'setup_advanced_caching']);
    }
    
    /**
     * Optimise le chargement des assets en fonction du contexte
     */
    public function optimize_assets_loading() {
        // Désactiver les scripts et styles non nécessaires sur les pages courantes
        $this->disable_unnecessary_assets();
        
        // Ajouter les attributs pour le lazy loading des images
        add_filter('wp_get_attachment_image_attributes', [$this, 'add_lazyload_attributes'], 10, 3);
        
        // Charger les CSS critiques en ligne pour les chemins d'accès critiques
        if (is_product() || is_checkout() || is_cart()) {
            $this->add_critical_css();
        }
        
        // Différer les scripts non essentiels
        $this->defer_non_essential_scripts();
        
        // Minifier le CSS et JS à la volée si la version minifiée n'existe pas
        $this->maybe_minify_assets();
    }
    
    /**
     * Désactive les assets non nécessaires selon le contexte de la page
     */
    private function disable_unnecessary_assets() {
        global $wp_scripts, $wp_styles;
        
        // Liste des handles à toujours conserver
        $essential_scripts = [
            'jquery',
            'jquery-core',
            'life-travel-excursion-frontend',
            'woocommerce',
            'wc-cart-fragments'
        ];
        
        $essential_styles = [
            'life-travel-excursion-style',
            'woocommerce-layout',
            'woocommerce-general'
        ];
        
        // Conserver uniquement les ressources requises sur les pages d'excursion
        if (is_product() && $this->is_excursion_product()) {
            foreach ($wp_scripts->registered as $handle => $script) {
                if (!in_array($handle, $essential_scripts) && 
                    !strpos($handle, 'life-travel') && 
                    !strpos($handle, 'wc-')) {
                    wp_dequeue_script($handle);
                }
            }
            
            foreach ($wp_styles->registered as $handle => $style) {
                if (!in_array($handle, $essential_styles) && 
                    !strpos($handle, 'life-travel') && 
                    !strpos($handle, 'woocommerce')) {
                    wp_dequeue_style($handle);
                }
            }
        }
        
        // Désactiver les emojis partout
        $this->disable_emojis();
        
        // Désactiver l'embed WordPress si non utilisé
        $this->disable_embeds();
    }
    
    /**
     * Vérifie si le produit actuel est une excursion
     * 
     * @return bool True si c'est une excursion
     */
    private function is_excursion_product() {
        global $product;
        
        if (!$product) {
            return false;
        }
        
        // Vérifier si le produit a des attributs d'excursion
        $excursion_attributes = [
            '_life_travel_duration',
            '_life_travel_destination',
            '_life_travel_max_participants'
        ];
        
        foreach ($excursion_attributes as $attr) {
            if (get_post_meta($product->get_id(), $attr, true)) {
                return true;
            }
        }
        
        // Vérifier si le produit appartient à une catégorie d'excursion
        $excursion_categories = ['excursion', 'tour', 'adventure', 'trip'];
        $product_cats = $product->get_category_ids();
        
        foreach ($product_cats as $cat_id) {
            $term = get_term($cat_id, 'product_cat');
            if ($term && in_array($term->slug, $excursion_categories)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Ajoute des attributs async/defer aux scripts non critiques
     */
    public function add_async_defer_attributes($tag, $handle, $src) {
        // Liste des scripts qui peuvent être chargés de manière asynchrone
        $async_scripts = [
            'google-analytics',
            'facebook-pixel',
            'life-travel-register-sw',
            'life-travel-map-integration'
        ];
        
        // Liste des scripts qui peuvent être différés
        $defer_scripts = [
            'life-travel-reviews',
            'life-travel-sharing'
        ];
        
        // Ajouter l'attribut async si nécessaire
        if (in_array($handle, $async_scripts)) {
            return str_replace(' src', ' async src', $tag);
        }
        
        // Ajouter l'attribut defer si nécessaire
        if (in_array($handle, $defer_scripts)) {
            return str_replace(' src', ' defer src', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Ajoute les attributs pour le lazy loading des images
     */
    public function add_lazyload_attributes($attr, $attachment, $size) {
        // Ne pas appliquer aux avatars, logos et images d'en-tête
        $excluded_classes = ['avatar', 'logo', 'header-image'];
        
        foreach ($excluded_classes as $class) {
            if (isset($attr['class']) && strpos($attr['class'], $class) !== false) {
                return $attr;
            }
        }
        
        // Sauvegarder le src original
        $attr['data-src'] = $attr['src'];
        
        // Utiliser une image de placeholder
        $attr['src'] = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 ' . $attr['width'] . ' ' . $attr['height'] . '\'%3E%3C/svg%3E';
        
        // Ajouter la classe pour le lazy loading
        if (isset($attr['class'])) {
            $attr['class'] .= ' life-travel-lazy-load';
        } else {
            $attr['class'] = 'life-travel-lazy-load';
        }
        
        // Ajouter l'attribut loading lazy natif
        $attr['loading'] = 'lazy';
        
        return $attr;
    }
    
    /**
     * Ajoute des indications de ressources pour les préchargements
     */
    public function add_resource_hints() {
        // Précharger les polices essentielles
        echo '<link rel="preload" href="' . esc_url(LIFE_TRAVEL_PLUGIN_URL . 'assets/fonts/primary-font.woff2') . '" as="font" type="font/woff2" crossorigin="anonymous">' . "\n";
        
        // Précharger les images critiques (logo, images d'en-tête)
        echo '<link rel="preload" href="' . esc_url(LIFE_TRAVEL_PLUGIN_URL . 'assets/img/logo.svg') . '" as="image">' . "\n";
        
        // Précharger le CSS critique
        echo '<link rel="preload" href="' . esc_url(LIFE_TRAVEL_PLUGIN_URL . 'assets/css/life-travel-excursion.css') . '" as="style">' . "\n";
        
        // Précharger le JS critique
        echo '<link rel="preload" href="' . esc_url(LIFE_TRAVEL_PLUGIN_URL . 'assets/js/life-travel-excursion-frontend.js') . '" as="script">' . "\n";
    }
    
    /**
     * Ajoute le CSS critique en ligne pour accélérer le rendu initial
     */
    private function add_critical_css() {
        // Déterminer le CSS critique selon le contexte de la page
        $critical_css = '';
        
        if (is_checkout()) {
            $critical_css_file = LIFE_TRAVEL_PLUGIN_DIR . 'assets/css/critical/checkout-critical.css';
        } elseif (is_cart()) {
            $critical_css_file = LIFE_TRAVEL_PLUGIN_DIR . 'assets/css/critical/cart-critical.css';
        } elseif (is_product()) {
            $critical_css_file = LIFE_TRAVEL_PLUGIN_DIR . 'assets/css/critical/product-critical.css';
        } else {
            $critical_css_file = LIFE_TRAVEL_PLUGIN_DIR . 'assets/css/critical/general-critical.css';
        }
        
        // Vérifier si le fichier existe
        if (file_exists($critical_css_file)) {
            $critical_css = file_get_contents($critical_css_file);
            
            // Minifier le CSS
            $critical_css = $this->minify_css($critical_css);
            
            // Ajouter le CSS critique en ligne dans l'en-tête
            add_action('wp_head', function() use ($critical_css) {
                echo '<style id="life-travel-critical-css">' . $critical_css . '</style>';
            }, 5);
        }
    }
    
    /**
     * Minifie le CSS à la volée
     */
    private function minify_css($css) {
        // Supprimer les commentaires
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Supprimer les espaces autour des caractères { } : ; ,
        $css = preg_replace('/\s*{\s*/', '{', $css);
        $css = preg_replace('/\s*}\s*/', '}', $css);
        $css = preg_replace('/\s*:\s*/', ':', $css);
        $css = preg_replace('/\s*;\s*/', ';', $css);
        $css = preg_replace('/\s*,\s*/', ',', $css);
        
        // Supprimer les espaces inutiles
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Supprimer les espaces au début et à la fin
        $css = trim($css);
        
        return $css;
    }
    
    /**
     * Diffère les scripts non essentiels
     */
    private function defer_non_essential_scripts() {
        // Implémenter ici la logique pour différer les scripts non critiques
        add_filter('script_loader_tag', function($tag, $handle) {
            // Liste des scripts à charger après le rendu initial
            $deferred_scripts = [
                'life-travel-social-share',
                'life-travel-reviews-display',
                'life-travel-related-excursions'
            ];
            
            if (in_array($handle, $deferred_scripts)) {
                return str_replace('<script', '<script defer', $tag);
            }
            
            return $tag;
        }, 10, 2);
    }
    
    /**
     * Minifie les assets à la volée si nécessaire
     */
    private function maybe_minify_assets() {
        // Implémenter la minification à la volée des assets
        // (ce code est simplifié - dans un environnement de production, utilisez un système de build)
        
        if (LIFE_TRAVEL_DEBUG) {
            return; // Ne pas minifier en mode débogage
        }
        
        // Filtrer les styles pour les minifier
        add_filter('style_loader_src', function($src) {
            // Vérifier si c'est un asset du plugin et s'il n'est pas déjà minifié
            if (strpos($src, LIFE_TRAVEL_PLUGIN_URL) !== false && strpos($src, '.min.css') === false) {
                $min_src = str_replace('.css', '.min.css', $src);
                $min_path = str_replace(LIFE_TRAVEL_PLUGIN_URL, LIFE_TRAVEL_PLUGIN_DIR, $min_src);
                
                // Si la version minifiée existe, l'utiliser
                if (file_exists($min_path)) {
                    return $min_src;
                }
            }
            
            return $src;
        });
        
        // Filtrer les scripts pour les minifier
        add_filter('script_loader_src', function($src) {
            // Vérifier si c'est un asset du plugin et s'il n'est pas déjà minifié
            if (strpos($src, LIFE_TRAVEL_PLUGIN_URL) !== false && strpos($src, '.min.js') === false) {
                $min_src = str_replace('.js', '.min.js', $src);
                $min_path = str_replace(LIFE_TRAVEL_PLUGIN_URL, LIFE_TRAVEL_PLUGIN_DIR, $min_src);
                
                // Si la version minifiée existe, l'utiliser
                if (file_exists($min_path)) {
                    return $min_src;
                }
            }
            
            return $src;
        });
    }
    
    /**
     * Désactive complètement les emojis pour les gains de performance
     */
    private function disable_emojis() {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        
        // Supprimer également du TinyMCE
        add_filter('tiny_mce_plugins', function($plugins) {
            if (is_array($plugins)) {
                return array_diff($plugins, ['wpemoji']);
            }
            return $plugins;
        });
    }
    
    /**
     * Vérifie si nous devons désactiver les emojis en fonction du contexte
     */
    public function maybe_disable_emojis() {
        // Désactiver les emojis partout par défaut, mais les activer sur certaines pages si nécessaire
        $this->disable_emojis();
    }
    
    /**
     * Désactive les intégrations (embeds) WordPress si non utilisées
     */
    private function disable_embeds() {
        global $wp;
        
        // Ne pas désactiver sur les pages avec des commentaires
        if (is_singular() && comments_open()) {
            return;
        }
        
        // Désactiver oEmbed Discovery Links
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        
        // Supprimer l'API REST oEmbed
        add_filter('rest_enabled', function($enabled) {
            return $enabled;
        });
        
        // Supprimer les scripts et styles d'intégration
        wp_deregister_script('wp-embed');
    }
    
    /**
     * Ajoute des indices preconnect pour les domaines externes
     */
    public function add_preconnect_hints($hints, $relation_type) {
        if ('preconnect' === $relation_type) {
            // Liste des domaines pour lesquels nous voulons établir des connexions anticipées
            $domains = [
                'https://fonts.googleapis.com',
                'https://fonts.gstatic.com',
                'https://maps.googleapis.com',
                'https://connect.facebook.net'
            ];
            
            foreach ($domains as $domain) {
                $hints[] = [
                    'href' => $domain,
                    'crossorigin' => 'anonymous'
                ];
            }
        }
        
        return $hints;
    }
    
    /**
     * Ajoute des indices HTTP/2 Server Push pour les ressources critiques
     */
    public function http2_resource_hints() {
        // Vérifier si nous sommes sur Apache avec mod_http2 ou Nginx avec HTTP/2
        if (!function_exists('header_register_callback')) {
            return;
        }
        
        header_register_callback(function() {
            // Liste des ressources critiques à pousser
            $resources = [
                LIFE_TRAVEL_PLUGIN_URL . 'assets/css/life-travel-excursion.css' => 'style',
                LIFE_TRAVEL_PLUGIN_URL . 'assets/js/life-travel-excursion-frontend.js' => 'script',
                LIFE_TRAVEL_PLUGIN_URL . 'assets/fonts/primary-font.woff2' => 'font'
            ];
            
            // Ajouter les en-têtes Link pour HTTP/2 Server Push
            foreach ($resources as $url => $type) {
                header('Link: <' . esc_url($url) . '>; rel=preload; as=' . $type . 
                      ($type === 'font' ? '; crossorigin=anonymous' : ''), false);
            }
        });
    }
    
    /**
     * Optimise les requêtes API en ajoutant des paramètres de cache et de contrôle
     */
    public function optimize_api_requests($args) {
        // Ajouter un cache-control pour permettre aux CDN de mettre en cache
        if (!isset($args['headers'])) {
            $args['headers'] = [];
        }
        
        // Ajouter un paramètre de version pour invalidation de cache
        if (isset($args['url']) && strpos($args['url'], '?') !== false) {
            $args['url'] .= '&_v=' . LIFE_TRAVEL_VERSION;
        } elseif (isset($args['url'])) {
            $args['url'] .= '?_v=' . LIFE_TRAVEL_VERSION;
        }
        
        return $args;
    }
    
    /**
     * Configure l'optimisation des requêtes de base de données
     */
    public function setup_query_optimization() {
        // Utiliser des requêtes optimisées pour les produits d'excursion fréquemment consultés
        add_filter('posts_clauses', [$this, 'optimize_excursion_queries'], 10, 2);
        
        // Mise en cache des résultats de recherche fréquents
        add_filter('posts_results', [$this, 'cache_search_results'], 10, 2);
    }
    
    /**
     * Optimise les requêtes de produits d'excursion
     */
    public function optimize_excursion_queries($clauses, $query) {
        global $wpdb;
        
        // Optimiser uniquement les requêtes de produits
        if (!$query->is_main_query() || $query->get('post_type') !== 'product') {
            return $clauses;
        }
        
        // Ajouter des indices pour les filtres de taxonomie (catégories, tags)
        if (!empty($query->tax_query->queries)) {
            $clauses['join'] .= " USE INDEX (type_status_date)";
        }
        
        // Optimiser les requêtes de tri par prix
        if ($query->get('orderby') === 'meta_value_num' && $query->get('meta_key') === '_price') {
            $clauses['orderby'] = "CAST({$wpdb->postmeta}.meta_value AS DECIMAL(10,2)) " . $query->get('order');
        }
        
        return $clauses;
    }
    
    /**
     * Met en cache les résultats de recherche fréquents
     */
    public function cache_search_results($posts, $query) {
        // Ne mettre en cache que les requêtes de recherche
        if (!$query->is_search() || count($posts) === 0) {
            return $posts;
        }
        
        // Créer une clé de cache unique pour cette recherche
        $search_terms = $query->get('s');
        $cache_key = 'life_travel_search_' . md5($search_terms);
        
        // Stocker les résultats dans la cache interne
        $this->cache[$cache_key] = [
            'posts' => $posts,
            'timestamp' => time(),
            'search_terms' => $search_terms
        ];
        
        // Stocker dans le cache de transient WP pour une utilisation future
        set_transient($cache_key, $posts, $this->cache_ttl);
        
        return $posts;
    }
    
    /**
     * Configure un système de cache avancé
     */
    public function setup_advanced_caching() {
        // Ajouter un cache pour les pages fréquemment consultées
        add_action('template_redirect', function() {
            // Ne pas mettre en cache les pages de panier et de checkout
            if (is_cart() || is_checkout()) {
                return;
            }
            
            // Définir les en-têtes de cache appropriés pour les pages publiques
            if (!is_user_logged_in()) {
                $cache_ttl = 3600; // 1 heure
                
                header('Cache-Control: public, max-age=' . $cache_ttl);
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache_ttl) . ' GMT');
                header('X-Life-Travel-Cache: enabled');
            }
        });
    }
}

// Initialiser l'optimiseur de performances
new Life_Travel_Performance_Optimizer();

/**
 * Fonction globale pour précharger des assets
 * 
 * @param string $url URL de la ressource à précharger
 * @param string $type Type de ressource (style, script, font, image)
 * @param bool $crossorigin Indique si la ressource est cross-origin
 */
function life_travel_preload_asset($url, $type, $crossorigin = false) {
    // Sanitize URL
    $url = esc_url($url);
    
    // Valider le type
    $allowed_types = ['style', 'script', 'font', 'image', 'fetch'];
    if (!in_array($type, $allowed_types)) {
        $type = 'fetch';
    }
    
    // Générer la balise de préchargement
    $html = '<link rel="preload" href="' . $url . '" as="' . $type . '"';
    
    // Ajouter crossorigin si nécessaire
    if ($crossorigin) {
        $html .= ' crossorigin="anonymous"';
    }
    
    $html .= '>' . "\n";
    
    // Output avec échappement
    echo $html;
}

/**
 * Vérifie si la page est consultée sur un appareil mobile
 * 
 * @return bool True si l'appareil est mobile
 */
function life_travel_is_mobile() {
    static $is_mobile = null;
    
    if ($is_mobile === null) {
        if (function_exists('wp_is_mobile')) {
            $is_mobile = wp_is_mobile();
        } else {
            $is_mobile = preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile/i', $_SERVER['HTTP_USER_AGENT']);
        }
    }
    
    return $is_mobile;
}

/**
 * Vérifie si la connexion est lente (basé sur l'en-tête Save-Data ou l'estimation client-side)
 * 
 * @return bool True si la connexion est lente
 */
function life_travel_is_slow_connection() {
    // Vérifier l'en-tête Save-Data (Chrome)
    if (isset($_SERVER['HTTP_SAVE_DATA']) && strtolower($_SERVER['HTTP_SAVE_DATA']) === 'on') {
        return true;
    }
    
    // Vérifier le cookie de vitesse de connexion (défini par JS)
    if (isset($_COOKIE['life_travel_connection_speed']) && $_COOKIE['life_travel_connection_speed'] === 'slow') {
        return true;
    }
    
    return false;
}

/**
 * Fonction utilitaire pour créer un hash de fichier pour l'invalidation de cache
 * 
 * @param string $file_path Chemin du fichier
 * @return string Hash du fichier ou version du plugin
 */
function life_travel_get_file_version($file_path) {
    $full_path = LIFE_TRAVEL_PLUGIN_DIR . ltrim($file_path, '/');
    
    if (file_exists($full_path)) {
        return substr(md5_file($full_path), 0, 8);
    }
    
    return LIFE_TRAVEL_VERSION;
}
