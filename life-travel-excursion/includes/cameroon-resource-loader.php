<?php
/**
 * Optimiseur de chargement des ressources pour les conditions réseau camerounaises
 *
 * Ce fichier améliore le chargement des ressources en:
 * - Priorisant le contenu essentiel
 * - Différant les scripts non critiques
 * - Adaptant les images aux conditions de réseau
 * - Implémentant des stratégies de fallback pour les ressources externes
 *
 * @package Life Travel Excursion
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Classe pour l'optimisation du chargement des ressources dans des conditions réseau difficiles
 */
class Life_Travel_Cameroon_Resource_Loader {
    /**
     * Délai maximal d'attente pour les ressources externes (5 secondes)
     */
    const TIMEOUT_THRESHOLD = 5000;
    
    /**
     * Liste des scripts critiques qui doivent toujours être chargés
     */
    private $critical_scripts = [
        'jquery',
        'jquery-core',
        'life-travel-excursion-core'
    ];
    
    /**
     * Liste des styles critiques qui doivent toujours être chargés
     */
    private $critical_styles = [
        'life-travel-excursion-base',
        'woocommerce-general'
    ];
    
    /**
     * État de la connexion (détecté via JavaScript)
     */
    private $connection_state = 'unknown';
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Détecter l'état de la connexion
        $this->detect_connection();
        
        // Hooks d'optimisation des ressources
        add_action('wp_enqueue_scripts', [$this, 'optimize_resource_loading'], 999);
        add_action('wp_head', [$this, 'add_connection_detection_script'], 1);
        add_filter('script_loader_tag', [$this, 'add_loading_attributes'], 10, 3);
        add_filter('style_loader_tag', [$this, 'optimize_style_loading'], 10, 4);
        add_filter('wp_resource_hints', [$this, 'add_resource_hints'], 10, 2);
        
        // Optimisation des images
        add_filter('wp_get_attachment_image_attributes', [$this, 'optimize_images'], 10, 3);
        add_filter('the_content', [$this, 'process_content_images'], 999);
        
        // Stratégies de fallback
        add_action('wp_footer', [$this, 'add_resource_fallback_script'], 999);
    }
    
    /**
     * Détecte l'état de la connexion à partir des informations disponibles
     */
    private function detect_connection() {
        // Vérifier si nous avons l'information dans un cookie
        if (isset($_COOKIE['life_travel_connection_state'])) {
            $this->connection_state = sanitize_text_field($_COOKIE['life_travel_connection_state']);
        }
        
        // Vérifier les en-têtes Save-Data (Chrome/Opera)
        if (isset($_SERVER['HTTP_SAVE_DATA']) && strtolower($_SERVER['HTTP_SAVE_DATA']) === 'on') {
            $this->connection_state = 'slow';
        }
        
        // Vérifier l'en-tête de méthode de réseau (certains navigateurs mobiles)
        if (isset($_SERVER['HTTP_ACN'])) {
            $network_type = strtolower($_SERVER['HTTP_ACN']);
            
            if (in_array($network_type, ['2g', 'slow-2g'])) {
                $this->connection_state = 'very-slow';
            } elseif ($network_type === '3g') {
                $this->connection_state = 'slow';
            }
        }
    }
    
    /**
     * Optimise le chargement des ressources en fonction des conditions réseau
     */
    public function optimize_resource_loading() {
        global $wp_scripts, $wp_styles;
        
        // N'appliquer l'optimisation que pour les visiteurs non connectés
        if (is_user_logged_in() && !isset($_GET['preview_low_bandwidth'])) {
            return;
        }
        
        // Traitement des scripts
        if (!empty($wp_scripts->registered)) {
            foreach ($wp_scripts->registered as $handle => $script) {
                // Ne pas toucher aux scripts critiques
                if (in_array($handle, $this->critical_scripts)) {
                    continue;
                }
                
                // Différer les scripts non critiques
                if ($this->connection_state === 'slow' || $this->connection_state === 'very-slow') {
                    // Pour les connexions très lentes, certains scripts peuvent être complètement désactivés
                    if ($this->connection_state === 'very-slow' && $this->is_heavy_script($handle)) {
                        wp_dequeue_script($handle);
                        continue;
                    }
                    
                    // Différer le chargement pour les scripts restants
                    $script->extra['group'] = 1; // Chargement en pied de page
                }
                
                // Ajouter des attributs pour optimiser le chargement
                $script->extra['async'] = true;
                
                // Appliquer les timeouts pour les ressources externes
                if ($this->is_external_script($script->src)) {
                    $script->extra['data-timeout'] = self::TIMEOUT_THRESHOLD;
                }
            }
        }
        
        // Traitement des styles
        if (!empty($wp_styles->registered)) {
            foreach ($wp_styles->registered as $handle => $style) {
                // Ne pas toucher aux styles critiques
                if (in_array($handle, $this->critical_styles)) {
                    continue;
                }
                
                // Pour les connexions très lentes, utiliser uniquement les styles essentiels
                if ($this->connection_state === 'very-slow' && !$this->is_essential_style($handle)) {
                    wp_dequeue_style($handle);
                    continue;
                }
                
                // Charger de manière asynchrone les styles non critiques
                if ($this->connection_state === 'slow') {
                    $style->extra['loading'] = 'async';
                }
            }
        }
        
        // Charger le CSS critique en ligne pour les chemins d'accès critiques
        if ($this->connection_state === 'slow' || $this->connection_state === 'very-slow') {
            $this->add_critical_css();
        }
    }
    
    /**
     * Vérifie si un script est considéré comme "lourd"
     * 
     * @param string $handle Handle du script
     * @return bool True si le script est considéré comme lourd
     */
    private function is_heavy_script($handle) {
        $heavy_scripts = [
            'wc-cart-fragments',
            'google-recaptcha',
            'wp-embed',
            'wp-mediaelement',
            'life-travel-map-integration',
            'life-travel-animations'
        ];
        
        return in_array($handle, $heavy_scripts);
    }
    
    /**
     * Vérifie si un script est hébergé sur un domaine externe
     * 
     * @param string $src URL source du script
     * @return bool True si le script est externe
     */
    private function is_external_script($src) {
        if (empty($src)) {
            return false;
        }
        
        $home_url = trailingslashit(home_url());
        return strpos($src, $home_url) !== 0 && strpos($src, '//') === 0 || strpos($src, 'http') === 0;
    }
    
    /**
     * Vérifie si un style est essentiel pour l'interface utilisateur
     * 
     * @param string $handle Handle du style
     * @return bool True si le style est considéré comme essentiel
     */
    private function is_essential_style($handle) {
        $essential_styles = array_merge($this->critical_styles, [
            'life-travel-excursion-essential',
            'woocommerce-smallscreen',
            'life-travel-mobile'
        ]);
        
        return in_array($handle, $essential_styles);
    }
    
    /**
     * Ajoute un script pour détecter l'état de la connexion côté client
     */
    public function add_connection_detection_script() {
        ?>
<script>
// Détecter les capacités réseau et enregistrer dans un cookie
(function() {
    function saveConnectionInfo() {
        var connectionState = 'unknown';
        
        // Utiliser Network Information API si disponible
        if (navigator.connection) {
            var conn = navigator.connection;
            
            if (conn.saveData) {
                connectionState = 'slow';
            } else if (conn.effectiveType) {
                switch (conn.effectiveType) {
                    case 'slow-2g':
                    case '2g':
                        connectionState = 'very-slow';
                        break;
                    case '3g':
                        connectionState = 'slow';
                        break;
                    case '4g':
                    case '5g':
                        connectionState = 'fast';
                        break;
                }
            }
        }
        
        // Estimer en fonction de la vitesse de chargement
        if (connectionState === 'unknown' && window.performance && window.performance.timing) {
            var navTiming = window.performance.timing;
            var loadTime = navTiming.domContentLoadedEventEnd - navTiming.navigationStart;
            
            if (loadTime > 5000) {
                connectionState = 'very-slow';
            } else if (loadTime > 2000) {
                connectionState = 'slow';
            } else {
                connectionState = 'fast';
            }
        }
        
        // Enregistrer dans un cookie pour les futures requêtes
        document.cookie = 'life_travel_connection_state=' + connectionState + '; path=/; max-age=3600';
        
        // Ajouter la classe à l'élément HTML pour le CSS adaptatif
        document.documentElement.className += ' connection-' + connectionState;
        
        return connectionState;
    }
    
    // Exécuter immédiatement et configurer un écouteur pour les changements de connexion
    var state = saveConnectionInfo();
    
    // Si l'API Network Information est disponible, surveiller les changements
    if (navigator.connection && navigator.connection.addEventListener) {
        navigator.connection.addEventListener('change', saveConnectionInfo);
    }
    
    // Exposer l'état pour d'autres scripts
    window.lifeTravel = window.lifeTravel || {};
    window.lifeTravel.connectionState = state;
})();
</script>
        <?php
    }
    
    /**
     * Ajoute des attributs de chargement aux balises de script
     * 
     * @param string $tag Balise script HTML
     * @param string $handle Identifiant du script
     * @param string $src URL source du script
     * @return string Balise script modifiée
     */
    public function add_loading_attributes($tag, $handle, $src) {
        // Ne pas modifier les scripts critiques
        if (in_array($handle, $this->critical_scripts)) {
            return $tag;
        }
        
        // Ajouter l'attribut async pour les scripts différés
        if (strpos($tag, 'async') === false && strpos($tag, 'defer') === false) {
            // Priorité au defer pour les scripts non critiques
            $tag = str_replace(' src', ' defer src', $tag);
        }
        
        // Ajouter un attribut de timeout pour les scripts externes
        if ($this->is_external_script($src) && strpos($tag, 'data-timeout') === false) {
            $tag = str_replace('></script>', ' data-timeout="' . self::TIMEOUT_THRESHOLD . '"></script>', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Optimise le chargement des styles CSS
     * 
     * @param string $tag Balise link HTML
     * @param string $handle Identifiant du style
     * @param string $href URL source du style
     * @param string $media Attribut media du style
     * @return string Balise link modifiée
     */
    public function optimize_style_loading($tag, $handle, $href, $media) {
        global $wp_styles;
        
        // Ne pas modifier les styles critiques
        if (in_array($handle, $this->critical_styles)) {
            return $tag;
        }
        
        // Obtenir l'objet style
        $style = $wp_styles->registered[$handle];
        
        // Vérifier s'il faut charger le style de manière asynchrone
        if (isset($style->extra['loading']) && $style->extra['loading'] === 'async') {
            // Utiliser preload avec onload pour le chargement asynchrone
            $tag = str_replace(
                ['rel="stylesheet"', 'media="' . $media . '"'],
                ['rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"', 'media="' . $media . '"'],
                $tag
            );
            
            // Ajouter un fallback pour les navigateurs qui ne supportent pas l'attribut onload
            $tag .= '<noscript><link rel="stylesheet" href="' . esc_url($href) . '" media="' . esc_attr($media) . '"></noscript>';
        }
        
        return $tag;
    }
    
    /**
     * Ajoute des indices de préchargement pour les ressources critiques
     * 
     * @param array $hints Indices de ressources existants
     * @param string $relation_type Type de relation (preconnect, dns-prefetch, etc.)
     * @return array Indices de ressources modifiés
     */
    public function add_resource_hints($hints, $relation_type) {
        // Ajouter des préconnexions pour les domaines d'images essentiels
        if ('preconnect' === $relation_type) {
            // Ajouter les domaines externes essentiels
            $domains = [
                'https://maps.googleapis.com',
                'https://fonts.gstatic.com',
                'https://secure.xivah-pay.cm' // Passerelle de paiement camerounaise
            ];
            
            foreach ($domains as $domain) {
                $hints[] = $domain;
            }
        }
        
        // Ajouter des prefetch pour les ressources qui seront nécessaires bientôt
        if ('prefetch' === $relation_type && ($this->connection_state !== 'very-slow')) {
            // Ne précharger des ressources que si la connexion n'est pas très lente
            if (is_product() && function_exists('is_product') && is_product()) {
                // Précharger les ressources du formulaire d'ajout au panier
                $hints[] = get_template_directory_uri() . '/assets/js/add-to-cart.js';
            }
        }
        
        return $hints;
    }
    
    /**
     * Optimise les attributs des images pour le chargement adaptatif
     * 
     * @param array $attr Attributs de l'image
     * @param WP_Post $attachment Post de l'attachement
     * @param string|array $size Taille de l'image
     * @return array Attributs modifiés
     */
    public function optimize_images($attr, $attachment, $size) {
        // Ajouter le lazy loading pour toutes les images
        if (!isset($attr['loading'])) {
            $attr['loading'] = 'lazy';
        }
        
        // Pour les connexions très lentes, utiliser des images de qualité inférieure
        if ($this->connection_state === 'very-slow' || $this->connection_state === 'slow') {
            // Si nous avons une version basse résolution, l'utiliser
            $low_res_url = $this->get_low_res_image_url($attachment->ID, $size);
            
            if ($low_res_url) {
                $attr['src'] = $low_res_url;
                
                // Conserver l'image haute résolution comme source potentielle
                if (!isset($attr['data-high-res-src'])) {
                    $attr['data-high-res-src'] = $attr['src'];
                }
            }
        }
        
        return $attr;
    }
    
    /**
     * Obtient l'URL d'une image en basse résolution
     * 
     * @param int $attachment_id ID de l'attachement
     * @param string|array $size Taille demandée
     * @return string|false URL de l'image basse résolution ou false si non disponible
     */
    private function get_low_res_image_url($attachment_id, $size) {
        // Vérifier si nous avons une version thumbnail
        if (is_string($size) && $size !== 'thumbnail') {
            $thumb_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
            if ($thumb_url) {
                return $thumb_url;
            }
        }
        
        // Générer une version basse qualité avec un filtre de qualité
        add_filter('jpeg_quality', function() { return 40; }, 999);
        add_filter('wp_editor_set_quality', function() { return 40; }, 999);
        
        $low_res_url = wp_get_attachment_image_url($attachment_id, $size);
        
        // Restaurer la qualité par défaut
        remove_all_filters('jpeg_quality', 999);
        remove_all_filters('wp_editor_set_quality', 999);
        
        return $low_res_url;
    }
    
    /**
     * Traite les images dans le contenu pour optimiser leur chargement
     * 
     * @param string $content Contenu HTML
     * @return string Contenu modifié
     */
    public function process_content_images($content) {
        // Ne pas traiter pour les connexions rapides ou en admin
        if ($this->connection_state === 'fast' || is_admin()) {
            return $content;
        }
        
        // Traiter les balises img dans le contenu
        $content = preg_replace_callback('/<img([^>]+)>/i', [$this, 'process_image_tag'], $content);
        
        return $content;
    }
    
    /**
     * Traite une balise image individuelle
     * 
     * @param array $matches Correspondances de l'expression régulière
     * @return string Balise image modifiée
     */
    private function process_image_tag($matches) {
        $img_tag = $matches[0];
        $img_attr = $matches[1];
        
        // Ajouter lazy loading si non présent
        if (strpos($img_attr, 'loading=') === false) {
            $img_tag = str_replace('<img', '<img loading="lazy"', $img_tag);
        }
        
        // Pour les connexions très lentes, modifier la qualité des images
        if ($this->connection_state === 'very-slow') {
            // Ajouter un filtre CSS pour économiser de la bande passante
            $img_tag = str_replace('<img', '<img style="filter: blur(0px);"', $img_tag);
            
            // Réduire la qualité via un paramètre de requête pour les images du site
            if (preg_match('/src=["\']([^"\']+)["\']/', $img_tag, $src_match)) {
                $src = $src_match[1];
                if (strpos($src, home_url()) === 0 && strpos($src, '.jpg') !== false) {
                    $new_src = add_query_arg('quality', '40', $src);
                    $img_tag = str_replace($src, $new_src, $img_tag);
                }
            }
        }
        
        return $img_tag;
    }
    
    /**
     * Ajoute le CSS critique en ligne pour les pages essentielles
     */
    private function add_critical_css() {
        $critical_css = '';
        
        // CSS de base essentiel pour toutes les pages
        $critical_css .= "
        body { font-family: sans-serif; color: #333; line-height: 1.5; }
        .container, .site-content { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 15px; }
        .site-header { padding: 10px 0; background: #fff; }
        .site-footer { margin-top: 20px; padding: 20px 0; background: #f8f8f8; }
        .woocommerce ul.products li.product { text-align: center; }
        .woocommerce-message, .woocommerce-error { padding: 10px; margin-bottom: 20px; }
        .woocommerce-message { background: #f7f6f7; color: #515151; }
        .woocommerce-error { background: #f7f6f7; color: #b81c23; }
        ";
        
        // CSS spécifique au type de page
        if (is_product()) {
            $critical_css .= "
            .woocommerce div.product { margin-bottom: 0; position: relative; }
            .woocommerce div.product .product_title { clear: none; margin-top: 0; padding: 0; }
            .woocommerce div.product p.price { color: #77a464; font-weight: 700; margin: 0 0 .5em; }
            .woocommerce div.product form.cart { margin-bottom: 2em; }
            .woocommerce div.product form.cart div.quantity { float: left; margin: 0 4px 0 0; }
            .woocommerce div.product form.cart .button { vertical-align: middle; float: left; }
            ";
        } elseif (is_checkout()) {
            $critical_css .= "
            .woocommerce-checkout .shop_table { background: #fafafa; }
            .woocommerce form .form-row { padding: 3px; margin: 0 0 6px; }
            .woocommerce form .form-row label { line-height: 2; }
            .woocommerce form .form-row input.input-text { background: #fff; border: 1px solid #ddd; padding: 8px; }
            ";
        }
        
        // Output le CSS critique
        if (!empty($critical_css)) {
            echo '<style id="life-travel-critical-css">' . $critical_css . '</style>';
        }
    }
    
    /**
     * Ajoute un script de fallback pour les ressources qui ne se chargent pas
     */
    public function add_resource_fallback_script() {
        // N'ajouter que pour les connexions lentes
        if ($this->connection_state !== 'slow' && $this->connection_state !== 'very-slow') {
            return;
        }
        
        ?>
<script>
// Gestionnaire de fallback pour les ressources externes
(function() {
    function handleResourceTimeout() {
        // Trouver tous les scripts avec un attribut de timeout
        var scripts = document.querySelectorAll('script[data-timeout]');
        
        scripts.forEach(function(script) {
            var timeout = parseInt(script.getAttribute('data-timeout'));
            var src = script.getAttribute('src');
            
            if (src && timeout) {
                // Créer un timeout pour détecter les échecs de chargement
                setTimeout(function() {
                    // Si le script n'est pas encore chargé après le timeout
                    if (!window.loadedScripts || !window.loadedScripts[src]) {
                        console.warn('Script timeout reached for: ' + src);
                        
                        // Essayer de charger une version locale si disponible
                        var localFallback = script.getAttribute('data-local-fallback');
                        if (localFallback) {
                            console.log('Attempting to load local fallback: ' + localFallback);
                            var fallbackScript = document.createElement('script');
                            fallbackScript.src = localFallback;
                            document.body.appendChild(fallbackScript);
                        }
                        
                        // Déclencher un événement pour que d'autres scripts puissent réagir
                        var event = new CustomEvent('resource:failed', { detail: { src: src } });
                        document.dispatchEvent(event);
                    }
                }, timeout);
            }
        });
    }
    
    // Initialiser le suivi des scripts chargés
    window.loadedScripts = {};
    
    // Écouter les événements de chargement de script
    document.addEventListener('load', function(e) {
        if (e.target.tagName === 'SCRIPT' && e.target.src) {
            window.loadedScripts[e.target.src] = true;
        }
    }, true);
    
    // Exécuter la gestion du timeout après le chargement de la page
    if (document.readyState === 'complete') {
        handleResourceTimeout();
    } else {
        window.addEventListener('load', handleResourceTimeout);
    }
})();
</script>
        <?php
    }
}

// Initialisation conditionnelle
function life_travel_initialize_cameroon_resource_loader() {
    // Vérifier si le système est actif dans les options
    $use_cameroon_loader = get_option('life_travel_use_cameroon_loader', true);
    
    if ($use_cameroon_loader) {
        return new Life_Travel_Cameroon_Resource_Loader();
    }
    
    return null;
}

// Singleton du chargeur de ressources
function life_travel_cameroon_resource_loader() {
    static $instance = null;
    
    if (null === $instance) {
        $instance = life_travel_initialize_cameroon_resource_loader();
    }
    
    return $instance;
}

// Initialiser le chargeur de ressources
life_travel_cameroon_resource_loader();
