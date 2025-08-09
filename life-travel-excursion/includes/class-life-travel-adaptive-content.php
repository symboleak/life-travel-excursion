<?php
/**
 * Classe d'adaptation du contenu pour les connexions variables
 *
 * Cette classe permet d'adapter dynamiquement le contenu (images, scripts, CSS)
 * en fonction de la qualité de la connexion internet de l'utilisateur.
 * Particulièrement utile dans le contexte camerounais où les connexions
 * peuvent être lentes, instables ou coûteuses.
 *
 * @package Life Travel Excursion
 * @since 2.4.0
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Classe Life_Travel_Adaptive_Content
 */
class Life_Travel_Adaptive_Content {

    /**
     * Instance unique (pattern Singleton)
     *
     * @var Life_Travel_Adaptive_Content
     */
    private static $instance = null;

    /**
     * Niveau de connexion détecté
     * 
     * @var string 'fast', 'medium', 'slow' ou 'offline'
     */
    private $connection_level = 'medium';

    /**
     * Paramètres d'optimisation
     * 
     * @var array
     */
    private $settings = array();

    /**
     * Constructeur
     */
    private function __construct() {
        // Charger les paramètres depuis les options WordPress
        $this->load_settings();
        
        // Détecter le niveau de connexion côté serveur
        $this->detect_connection_level();
        
        // Initialiser les hooks
        $this->init_hooks();
    }

    /**
     * Obtenir l'instance unique de la classe
     * 
     * @return Life_Travel_Adaptive_Content
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Charger les paramètres depuis les options WordPress
     */
    private function load_settings() {
        $defaults = array(
            'enabled' => true,
            'detection_mode' => 'auto', // 'auto', 'manual'
            'fast_threshold' => 200,    // ms
            'medium_threshold' => 1000, // ms
            'image_quality' => array(
                'fast' => 90,   // %
                'medium' => 75, // %
                'slow' => 60,   // %
                'offline' => 40 // %
            ),
            'lazy_loading' => array(
                'enabled' => true,
                'threshold' => 0.5, // 50% visible avant chargement
                'skip_first' => true // Ne pas appliquer au premier élément visible
            ),
            'features' => array(
                'webp' => true,
                'srcset' => true,
                'preload_critical' => true,
                'defer_non_critical' => true,
                'minify' => true,
                'local_storage' => true
            ),
            'offline_mode' => array(
                'enabled' => true,
                'cache_pages' => array('home', 'excursions', 'about'),
                'max_cache_size' => 50, // MB
                'cache_images' => true
            )
        );

        // Fusionner avec les options enregistrées
        $saved_settings = get_option('life_travel_adaptive_content_settings', array());
        $this->settings = wp_parse_args($saved_settings, $defaults);
    }

    /**
     * Détecter le niveau de connexion côté serveur (estimation initiale)
     */
    private function detect_connection_level() {
        // Vérifier si un niveau de connexion est déjà défini dans les cookies
        if (isset($_COOKIE['life_travel_connection_level'])) {
            $level = sanitize_key($_COOKIE['life_travel_connection_level']);
            if (in_array($level, array('fast', 'medium', 'slow', 'offline'))) {
                $this->connection_level = $level;
                return;
            }
        }

        // Vérifier le User-Agent pour les en-têtes Save-Data
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $save_data = isset($_SERVER['HTTP_SAVE_DATA']) ? $_SERVER['HTTP_SAVE_DATA'] : '';
        
        if ($save_data === 'on' || strpos($user_agent, 'Android') !== false) {
            // Mode économie de données activé ou appareil Android (plus susceptible d'avoir une connexion limitée)
            $this->connection_level = 'slow';
        } else {
            // Niveau par défaut
            $this->connection_level = 'medium';
        }
    }

    /**
     * Initialiser les hooks WordPress
     */
    private function init_hooks() {
        // Activer uniquement si la fonctionnalité est activée
        if (!$this->settings['enabled']) {
            return;
        }

        // Hooks frontend
        if (!is_admin()) {
            // Modification du contenu HTML
            add_filter('the_content', array($this, 'adapt_content'));
            
            // Optimisation des images
            add_filter('wp_get_attachment_image_attributes', array($this, 'optimize_image_attributes'), 10, 3);
            
            // Ajout des scripts et styles
            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
            
            // Ajout du Service Worker pour le mode hors-ligne
            add_action('wp_head', array($this, 'add_service_worker'));
            
            // Hook de pied de page pour le script de détection JavaScript
            add_action('wp_footer', array($this, 'add_detection_script'), 99);
        }
    }

    /**
     * Adapter le contenu HTML en fonction du niveau de connexion
     * 
     * @param string $content Le contenu WordPress.
     * @return string Le contenu adapté.
     */
    public function adapt_content($content) {
        // Si mode hors-ligne et que le contenu est déjà en cache local, utiliser une version simplifiée
        if ($this->connection_level === 'offline' && $this->settings['offline_mode']['enabled']) {
            // Retirer les iframes et contenus externes
            $content = preg_replace('/<iframe.*?<\/iframe>/s', '<div class="offline-placeholder">Contenu non disponible en mode hors-ligne</div>', $content);
        }

        // Adapter les images en fonction du niveau de connexion
        if ($this->connection_level === 'slow' || $this->connection_level === 'medium') {
            // Ajouter lazy loading à toutes les images
            if ($this->settings['lazy_loading']['enabled']) {
                $content = $this->add_lazy_loading($content);
            }
        }

        return $content;
    }

    /**
     * Ajouter le lazy loading aux images
     * 
     * @param string $content Le contenu HTML.
     * @return string Le contenu avec lazy loading.
     */
    private function add_lazy_loading($content) {
        // Ne pas traiter si le contenu est vide ou non string
        if (empty($content) || !is_string($content)) {
            return $content;
        }

        // Utiliser DOMDocument pour manipuler le HTML de manière plus fiable
        if (!function_exists('libxml_use_internal_errors')) {
            return $content; // Sécurité si la fonction n'existe pas
        }

        // Sauvegarder l'état actuel des erreurs libxml
        $previous_state = libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));

        // Récupérer toutes les images
        $images = $dom->getElementsByTagName('img');
        $skip_first = $this->settings['lazy_loading']['skip_first'];
        $count = 0;

        foreach ($images as $img) {
            $count++;
            
            // Ne pas appliquer au premier élément visible si configuré ainsi
            if ($skip_first && $count === 1) {
                continue;
            }
            
            // Déjà configuré avec loading lazy?
            if ($img->hasAttribute('loading') && $img->getAttribute('loading') === 'lazy') {
                continue;
            }
            
            // Appliquer lazy loading standard HTML5
            $img->setAttribute('loading', 'lazy');
            
            // Ajouter un placeholder pour améliorer l'UX
            if (!$img->hasAttribute('data-src') && $img->hasAttribute('src')) {
                $src = $img->getAttribute('src');
                $img->setAttribute('data-src', $src);
                
                // Déterminer la couleur dominante pour un placeholder (simplifié)
                $placeholder = $this->get_placeholder_url($img->getAttribute('width'), $img->getAttribute('height'));
                $img->setAttribute('src', $placeholder);
                
                // Ajouter une classe pour le JavaScript
                $classes = $img->getAttribute('class');
                $img->setAttribute('class', $classes . ' life-travel-lazy');
            }
        }

        // Récupérer le HTML modifié
        $new_content = $dom->saveHTML();
        
        // Restaurer l'état précédent des erreurs libxml
        libxml_use_internal_errors($previous_state);
        
        if ($new_content) {
            // Extraire le body uniquement (DOMDocument ajoute html/body tags)
            $start = strpos($new_content, '<body>') + 6;
            $length = strpos($new_content, '</body>') - $start;
            return substr($new_content, $start, $length);
        }
        
        return $content; // Retour au contenu original en cas d'erreur
    }

    /**
     * Générer une URL de placeholder pour le lazy loading
     * 
     * @param int $width Largeur de l'image.
     * @param int $height Hauteur de l'image.
     * @return string URL du placeholder.
     */
    private function get_placeholder_url($width, $height) {
        // Utiliser des valeurs par défaut si non spécifiées
        $width = $width ? intval($width) : 300;
        $height = $height ? intval($height) : 200;
        
        // Couleur de base grise claire
        $color = '9ccdddff-e0e0e0';
        
        // Générer une URL de placeholder SVG data URI
        return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' viewBox='0 0 $width $height'%3E%3Crect width='100%25' height='100%25' fill='%23e0e0e0'/%3E%3C/svg%3E";
    }

    /**
     * Optimiser les attributs des images en fonction du niveau de connexion
     * 
     * @param array $attr Les attributs de l'image.
     * @param WP_Post $attachment L'attachement.
     * @param array|string $size La taille de l'image.
     * @return array Les attributs modifiés.
     */
    public function optimize_image_attributes($attr, $attachment, $size) {
        // Si WebP est supporté et activé, utiliser WebP
        if ($this->settings['features']['webp'] && $this->is_webp_supported()) {
            $webp_url = $this->get_webp_url($attr['src']);
            if ($webp_url) {
                $attr['src'] = $webp_url;
            }
        }
        
        // Ajouter des tailles responsives si activé
        if ($this->settings['features']['srcset'] && !isset($attr['srcset'])) {
            $srcset = wp_get_attachment_image_srcset($attachment->ID, $size);
            if ($srcset) {
                $attr['srcset'] = $srcset;
                $attr['sizes'] = wp_get_attachment_image_sizes($attachment->ID, $size);
            }
        }
        
        // Déterminer si lazy loading doit être appliqué
        if ($this->settings['lazy_loading']['enabled'] && 
            ($this->connection_level === 'slow' || $this->connection_level === 'medium')) {
            $attr['loading'] = 'lazy';
        }
        
        return $attr;
    }

    /**
     * Vérifier si le navigateur supporte WebP
     * 
     * @return bool Vrai si WebP est supporté.
     */
    private function is_webp_supported() {
        if (isset($_SERVER['HTTP_ACCEPT'])) {
            return strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false;
        }
        return false;
    }

    /**
     * Obtenir l'URL WebP pour une image si disponible
     * 
     * @param string $url URL de l'image.
     * @return string|bool URL WebP ou false si non disponible.
     */
    private function get_webp_url($url) {
        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $url);
        
        // Vérifier si le fichier WebP existe
        $webp_path = str_replace(site_url('/'), ABSPATH, $webp_url);
        if (file_exists($webp_path)) {
            return $webp_url;
        }
        
        return false;
    }

    /**
     * Charger les assets JavaScript et CSS
     */
    public function enqueue_assets() {
        // Script de détection de la connexion
        wp_enqueue_script(
            'life-travel-network-detector',
            plugins_url('/assets/js/network-detector.js', dirname(__FILE__)),
            array('jquery'),
            '2.4.0',
            true
        );
        
        // Passer les paramètres au script
        wp_localize_script('life-travel-network-detector', 'lifeTravelNetwork', array(
            'thresholds' => array(
                'fast' => $this->settings['fast_threshold'],
                'medium' => $this->settings['medium_threshold']
            ),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('life_travel_network_nonce'),
            'currentLevel' => $this->connection_level
        ));
        
        // CSS adaptatif selon le niveau de connexion
        $css_file = 'standard';
        
        switch ($this->connection_level) {
            case 'slow':
                $css_file = 'minimal';
                break;
            case 'medium':
                $css_file = 'optimized';
                break;
            case 'offline':
                $css_file = 'offline';
                break;
            default:
                $css_file = 'standard';
        }
        
        wp_enqueue_style(
            'life-travel-adaptive',
            plugins_url("/assets/css/adaptive-{$css_file}.css", dirname(__FILE__)),
            array(),
            '2.4.0'
        );
    }

    /**
     * Ajouter le Service Worker pour le mode hors-ligne
     */
    public function add_service_worker() {
        if (!$this->settings['offline_mode']['enabled']) {
            return;
        }
        
        ?>
        <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                // Centralisation : le SW est enregistré par pwa-bridge.php
                navigator.serviceWorker.ready
                    .then(function(registration) {
                        console.log('Life Travel Service Worker prêt (centralisé): ', registration.scope);
                    })
                    .catch(function(error) {
                        console.log('Life Travel Service Worker non prêt: ', error);
                    });
            });
        }
        </script>
        <?php
    }

    /**
     * Ajouter le script de détection de la qualité de connexion
     */
    public function add_detection_script() {
        // Script déjà chargé via wp_enqueue_scripts
        if ($this->settings['detection_mode'] !== 'auto') {
            return;
        }
        
        ?>
        <script>
        (function() {
            // Ce script s'exécute tôt pour détecter la connexion
            var startTime = new Date().getTime();
            var img = new Image();
            var connectionLevel = 'medium'; // Niveau par défaut
            
            function setConnectionCookie(level) {
                document.cookie = "life_travel_connection_level=" + level + "; path=/; max-age=3600";
                
                // Si le niveau a changé, recharger la page
                if (lifeTravelNetwork.currentLevel !== level) {
                    window.location.reload();
                }
            }
            
            img.onload = function() {
                var loadTime = new Date().getTime() - startTime;
                
                if (loadTime < lifeTravelNetwork.thresholds.fast) {
                    connectionLevel = 'fast';
                } else if (loadTime < lifeTravelNetwork.thresholds.medium) {
                    connectionLevel = 'medium';
                } else {
                    connectionLevel = 'slow';
                }
                
                setConnectionCookie(connectionLevel);
            };
            
            img.onerror = function() {
                // Erreur de chargement, probablement hors-ligne
                setConnectionCookie('offline');
            };
            
            // Utiliser un petit fichier pour tester
            img.src = '<?php echo esc_url(includes_url('/images/spinner.gif')); ?>?t=' + startTime;
            
            // Vérifier également l'API Network Information si disponible
            if (navigator.connection) {
                var connection = navigator.connection;
                
                if (connection.saveData) {
                    // Mode économie de données activé
                    connectionLevel = 'slow';
                    setConnectionCookie(connectionLevel);
                } else if (connection.effectiveType) {
                    // Vérifier le type de connexion
                    switch (connection.effectiveType) {
                        case 'slow-2g':
                        case '2g':
                            connectionLevel = 'slow';
                            break;
                        case '3g':
                            connectionLevel = 'medium';
                            break;
                        case '4g':
                            connectionLevel = 'fast';
                            break;
                    }
                    setConnectionCookie(connectionLevel);
                }
                
                // Écouter les changements de connexion
                connection.addEventListener('change', function() {
                    // Réexécuter le test
                    startTime = new Date().getTime();
                    img.src = '<?php echo esc_url(includes_url('/images/spinner.gif')); ?>?t=' + startTime;
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * Récupérer le niveau de connexion actuel
     * 
     * @return string Le niveau de connexion ('fast', 'medium', 'slow', 'offline').
     */
    public function get_connection_level() {
        return $this->connection_level;
    }

    /**
     * Définir manuellement le niveau de connexion
     * 
     * @param string $level Le niveau de connexion.
     * @return void
     */
    public function set_connection_level($level) {
        if (in_array($level, array('fast', 'medium', 'slow', 'offline'))) {
            $this->connection_level = $level;
            setcookie('life_travel_connection_level', $level, time() + 3600, '/');
        }
    }

    /**
     * Récupérer un paramètre de configuration
     * 
     * @param string $key Clé du paramètre.
     * @param mixed $default Valeur par défaut si non trouvée.
     * @return mixed La valeur du paramètre.
     */
    public function get_setting($key, $default = null) {
        if (isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        return $default;
    }
}

// Initialiser la classe
function life_travel_adaptive_content() {
    return Life_Travel_Adaptive_Content::get_instance();
}

// Démarrer l'adaptation du contenu
life_travel_adaptive_content();
