<?php
/**
 * Optimisation réseau pour Life Travel
 * Spécialement conçu pour les connexions lentes comme celles du Cameroun
 * 
 * @package Life Travel
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Classe d'optimisation réseau
 */
class Life_Travel_Network_Optimization {
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Ajouter les filtres d'optimisation des médias
        add_filter('wp_calculate_image_srcset', array($this, 'optimize_srcset_for_slow_connections'), 10, 5);
        add_filter('jpeg_quality', array($this, 'adjust_jpeg_quality'), 10, 2);
        add_filter('wp_get_attachment_image_attributes', array($this, 'add_image_loading_attributes'), 10, 3);
        
        // Optimisation HTML
        add_action('wp_head', array($this, 'add_connection_detection_script'), 0);
        
        // Activer la compression GZIP si non activée
        add_action('init', array($this, 'enable_gzip_compression'));
        
        // Précharger les ressources critiques
        add_action('wp_head', array($this, 'preload_critical_resources'), 1);
        
        // Ajouter des méta-balises pour le mode hors-ligne
        add_action('wp_head', array($this, 'add_offline_metadata'));
    }
    
    /**
     * Optimiser les ensembles de sources d'images pour les connexions lentes
     * 
     * @param array $sources Les sources d'image
     * @param array $size_array Les dimensions de l'image
     * @param string $image_src L'URL de l'image source
     * @param array $image_meta Les métadonnées de l'image
     * @param int $attachment_id L'ID de l'attachement
     * @return array Les sources optimisées
     */
    public function optimize_srcset_for_slow_connections($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        // Si le navigateur a indiqué une connexion lente, réduire la qualité des images
        if (isset($_COOKIE['lt_connection_speed']) && $_COOKIE['lt_connection_speed'] === 'slow') {
            foreach ($sources as $width => $source) {
                // Ajouter un paramètre de qualité pour nos propres images
                if (strpos($source['url'], site_url()) !== false) {
                    $sources[$width]['url'] = add_query_arg('q', 60, $source['url']);
                }
                
                // Limiter la taille maximale des images sur les connexions lentes
                if ($width > 1200) {
                    unset($sources[$width]);
                }
            }
        }
        
        return $sources;
    }
    
    /**
     * Ajuster la qualité JPEG en fonction de la connexion
     * 
     * @param int $quality La qualité JPEG
     * @param string $context Le contexte de l'image
     * @return int La qualité JPEG ajustée
     */
    public function adjust_jpeg_quality($quality, $context) {
        // Réduire la qualité pour les connexions lentes
        if (isset($_COOKIE['lt_connection_speed'])) {
            switch ($_COOKIE['lt_connection_speed']) {
                case 'slow':
                    return 60; // Basse qualité pour les connexions lentes
                case 'medium':
                    return 75; // Qualité moyenne pour les connexions moyennes
                default:
                    return $quality;
            }
        }
        
        return $quality;
    }
    
    /**
     * Ajouter des attributs de chargement aux images
     * 
     * @param array $attr Les attributs de l'image
     * @param WP_Post $attachment L'attachement
     * @param string $size La taille de l'image
     * @return array Les attributs modifiés
     */
    public function add_image_loading_attributes($attr, $attachment, $size) {
        // Ajouter le chargement différé pour toutes les images non critiques
        if (!isset($attr['class']) || strpos($attr['class'], 'critical-image') === false) {
            $attr['loading'] = 'lazy';
            
            // Ajouter l'attribut decoding pour améliorer la performance
            $attr['decoding'] = 'async';
        }
        
        return $attr;
    }
    
    /**
     * Ajouter un script de détection de la vitesse de connexion
     */
    public function add_connection_detection_script() {
        ?>
        <script>
        (function() {
            // Vérifier si le cookie est déjà défini
            function getCookie(name) {
                var value = '; ' + document.cookie;
                var parts = value.split('; ' + name + '=');
                if (parts.length === 2) return parts.pop().split(';').shift();
                return null;
            }
            
            // Ne vérifier la connexion que si aucun cookie n'est défini ou s'il est expiré
            var connectionSpeed = getCookie('lt_connection_speed');
            if (!connectionSpeed || getCookie('lt_last_check') < (Date.now() - 3600000)) {
                // Petite image de test (1KB)
                var startTime = Date.now();
                var img = new Image();
                img.onload = function() {
                    var endTime = Date.now();
                    var duration = endTime - startTime;
                    
                    // Définir la vitesse de connexion en fonction du temps de chargement
                    var speed = duration < 200 ? 'fast' : (duration < 1000 ? 'medium' : 'slow');
                    
                    // Définir un cookie valide 1 heure
                    document.cookie = 'lt_connection_speed=' + speed + '; path=/; max-age=3600';
                    document.cookie = 'lt_last_check=' + Date.now() + '; path=/; max-age=3600';
                    
                    // Si la connexion est lente, ajouter une classe au body pour CSS
                    if (speed === 'slow') {
                        document.body.classList.add('slow-connection');
                    }
                };
                img.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7?' + Date.now();
            }
            else if (connectionSpeed === 'slow') {
                // Si déjà identifié comme lent, ajouter la classe directement
                document.body.classList.add('slow-connection');
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Activer la compression GZIP si ce n'est pas déjà fait
     */
    public function enable_gzip_compression() {
        // Vérifier si la compression est déjà activée
        if (extension_loaded('zlib') && !ini_get('zlib.output_compression') && !headers_sent()) {
            ini_set('zlib.output_compression', 'On');
            ini_set('zlib.output_compression_level', '5');
        }
    }
    
    /**
     * Précharger les ressources critiques
     */
    public function preload_critical_resources() {
        // Ne précharger que les ressources critiques qui impactent le rendu initial
        $critical_resources = array(
            'logo' => array(
                'url' => plugins_url('assets/img/logos/logo-main.png', dirname(__FILE__)),
                'type' => 'image/png'
            ),
            'main_css' => array(
                'url' => plugins_url('assets/css/integration.min.css', dirname(__FILE__)),
                'type' => 'style'
            ),
            'font' => array(
                'url' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap',
                'type' => 'style'
            )
        );
        
        // N'ajouter les préchargements que pour les connexions rapides
        $connection_speed = isset($_COOKIE['lt_connection_speed']) ? $_COOKIE['lt_connection_speed'] : 'fast';
        
        if ($connection_speed !== 'slow') {
            foreach ($critical_resources as $resource) {
                echo '<link rel="preload" href="' . esc_url($resource['url']) . '" as="' . esc_attr($resource['type']) . '" data-critical="true">' . "\n";
            }
        } else {
            // Pour les connexions lentes, précharger uniquement le logo
            echo '<link rel="preload" href="' . esc_url($critical_resources['logo']['url']) . '" as="image" data-critical="true">' . "\n";
        }
    }
    
    /**
     * Ajouter des méta-balises pour le mode hors-ligne
     */
    public function add_offline_metadata() {
        ?>
        <!-- Méta-balises pour la gestion du mode hors-ligne -->
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr(get_bloginfo('name')); ?>">
        <meta name="theme-color" content="#0073B2">
        <meta name="application-name" content="<?php echo esc_attr(get_bloginfo('name')); ?>">
        <?php
    }
}

// Initialiser l'optimisation réseau
new Life_Travel_Network_Optimization();
