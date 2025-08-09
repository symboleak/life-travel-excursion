<?php
/**
 * Système de cache adapté aux conditions réseau du Cameroun
 *
 * Ce fichier implémente des stratégies de mise en cache spécifiquement conçues
 * pour optimiser l'expérience utilisateur dans des conditions de réseau difficiles:
 * - Bande passante limitée
 * - Connexions instables
 * - Latence élevée
 * - Coût élevé des données
 *
 * @package Life Travel Excursion
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Classe de gestion de cache adaptée au contexte camerounais
 */
class Life_Travel_Cameroon_Cache {
    /**
     * Durée de mise en cache standard (24 heures)
     */
    const CACHE_DURATION_STANDARD = 86400;
    
    /**
     * Durée de mise en cache longue (72 heures)
     */
    const CACHE_DURATION_LONG = 259200;
    
    /**
     * Préfixe pour les clés de cache
     */
    const CACHE_PREFIX = 'ltcmr_';
    
    /**
     * Taille maximale des objets mis en cache (en Ko)
     */
    const MAX_CACHE_SIZE = 512;
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Initialiser le système de cache
        add_action('init', [$this, 'init_cache_system'], 5);
        
        // Hooks pour le contenu dynamique
        add_filter('life_travel_product_data', [$this, 'cache_product_data'], 10, 2);
        add_filter('life_travel_excursion_availability', [$this, 'cache_availability_data'], 10, 3);
        
        // Purger le cache lorsque nécessaire
        add_action('woocommerce_save_product', [$this, 'purge_product_cache'], 10, 2);
        add_action('woocommerce_order_status_changed', [$this, 'purge_availability_cache'], 10, 3);
    }
    
    /**
     * Initialise le système de cache
     */
    public function init_cache_system() {
        // Vérifier si nous avons besoin de nettoyer le cache
        $this->maybe_cleanup_cache();
        
        // Configurer les en-têtes de cache pour les visiteurs
        $this->setup_http_cache_headers();
    }
    
    /**
     * Configure les en-têtes HTTP pour le cache navigateur
     */
    private function setup_http_cache_headers() {
        // Ne pas appliquer pour les utilisateurs connectés, panier, checkout
        if (is_user_logged_in() || is_cart() || is_checkout()) {
            return;
        }
        
        // Adapter la durée du cache en fonction du type de page
        $cache_duration = self::CACHE_DURATION_STANDARD;
        
        // Cache plus long pour les contenus statiques
        if (is_page() && !is_page(['cart', 'checkout', 'my-account'])) {
            $cache_duration = self::CACHE_DURATION_LONG;
        }
        
        // Pour les produits d'excursion, utiliser une durée standard
        if (is_product()) {
            $product = wc_get_product();
            if ($product && $product->get_type() === 'excursion') {
                $cache_duration = self::CACHE_DURATION_STANDARD;
            }
        }
        
        // Définir les en-têtes de cache
        header('Cache-Control: public, max-age=' . $cache_duration);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache_duration) . ' GMT');
        header('X-Life-Travel-Cache: enabled');
        // Ajout d'un en-tête de contrôle spécifique aux conditions réseau camerounaises
        header('X-Life-Travel-Cameroon-Optimized: true');
    }
    
    /**
     * Mise en cache des données de produit
     * 
     * @param array $product_data Données du produit
     * @param int $product_id ID du produit
     * @return array Données du produit (en cache ou fraîches)
     */
    public function cache_product_data($product_data, $product_id) {
        if (empty($product_id)) {
            return $product_data;
        }
        
        // Générer une clé de cache unique
        $cache_key = self::CACHE_PREFIX . 'product_' . $product_id;
        
        // Vérifier si nous avons ces données en cache
        $cached_data = get_transient($cache_key);
        
        if (false !== $cached_data) {
            return $cached_data;
        }
        
        // Pas de cache trouvé, stocker pour une utilisation future
        set_transient($cache_key, $product_data, self::CACHE_DURATION_STANDARD);
        
        return $product_data;
    }
    
    /**
     * Mise en cache des données de disponibilité
     * 
     * @param array $availability Données de disponibilité
     * @param int $product_id ID du produit
     * @param string $date Date pour laquelle on vérifie la disponibilité
     * @return array Données de disponibilité
     */
    public function cache_availability_data($availability, $product_id, $date) {
        if (empty($product_id) || empty($date)) {
            return $availability;
        }
        
        // Générer une clé de cache unique
        $cache_key = self::CACHE_PREFIX . 'availability_' . $product_id . '_' . sanitize_key($date);
        
        // Vérifier si nous avons ces données en cache
        $cached_data = get_transient($cache_key);
        
        if (false !== $cached_data) {
            return $cached_data;
        }
        
        // Vérifier la taille des données avant de les mettre en cache
        $data_size = $this->get_data_size($availability);
        if ($data_size > self::MAX_CACHE_SIZE) {
            // Trop gros pour le cache, nettoyer le minimum nécessaire
            $availability = $this->optimize_availability_data($availability);
        }
        
        // Mettre en cache pour une durée plus courte si les données sont volatiles
        $cache_duration = $this->get_dynamic_cache_duration($product_id, $date);
        set_transient($cache_key, $availability, $cache_duration);
        
        return $availability;
    }
    
    /**
     * Optimise la taille des données de disponibilité
     * 
     * @param array $availability Données de disponibilité
     * @return array Données optimisées
     */
    private function optimize_availability_data($availability) {
        // Version simplifiée pour le cache
        $optimized = [];
        
        if (isset($availability['available_slots'])) {
            $optimized['available_slots'] = $availability['available_slots'];
        }
        
        if (isset($availability['seats_remaining'])) {
            $optimized['seats_remaining'] = $availability['seats_remaining'];
        }
        
        if (isset($availability['is_available'])) {
            $optimized['is_available'] = $availability['is_available'];
        }
        
        return $optimized;
    }
    
    /**
     * Calcule dynamiquement la durée de cache appropriée
     * 
     * @param int $product_id ID du produit
     * @param string $date Date au format Y-m-d
     * @return int Durée du cache en secondes
     */
    private function get_dynamic_cache_duration($product_id, $date) {
        // Convertir la date en timestamp
        $date_time = strtotime($date);
        $now = time();
        
        // Si la date est éloignée, cache plus long
        $days_until = ($date_time - $now) / DAY_IN_SECONDS;
        
        if ($days_until > 30) {
            // Plus d'un mois à l'avance, les disponibilités changent peu
            return self::CACHE_DURATION_LONG;
        } else if ($days_until > 7) {
            // Plus d'une semaine à l'avance
            return self::CACHE_DURATION_STANDARD;
        } else if ($days_until > 1) {
            // Entre 1 et 7 jours, durée plus courte
            return 3600 * 6; // 6 heures
        } else {
            // Moins de 24h, très court
            return 1800; // 30 minutes
        }
    }
    
    /**
     * Calcule la taille approximative des données
     * 
     * @param mixed $data Données à mesurer
     * @return int Taille approximative en Ko
     */
    private function get_data_size($data) {
        $serialized = serialize($data);
        return strlen($serialized) / 1024; // Taille en Ko
    }
    
    /**
     * Nettoie le cache si nécessaire
     */
    private function maybe_cleanup_cache() {
        // Vérifier si un nettoyage est nécessaire (une fois par jour)
        $last_cleanup = get_option('life_travel_last_cache_cleanup');
        $now = time();
        
        if ($last_cleanup && ($now - $last_cleanup) < DAY_IN_SECONDS) {
            return;
        }
        
        // Nettoyer les caches orphelins ou expirés
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options 
                WHERE option_name LIKE %s 
                AND option_name NOT LIKE %s",
                '_transient_timeout_' . self::CACHE_PREFIX . '%',
                '_transient_timeout_' . self::CACHE_PREFIX . 'essential%'
            )
        );
        
        // Mettre à jour la date du dernier nettoyage
        update_option('life_travel_last_cache_cleanup', $now);
    }
    
    /**
     * Purge le cache d'un produit spécifique
     * 
     * @param int $product_id ID du produit
     * @param WC_Product $product Instance du produit
     */
    public function purge_product_cache($product_id, $product = null) {
        if (!$product) {
            $product = wc_get_product($product_id);
        }
        
        // Vérifier si c'est une excursion
        if ($product && $product->get_type() === 'excursion') {
            $cache_key = self::CACHE_PREFIX . 'product_' . $product_id;
            delete_transient($cache_key);
            
            // Journal de débogage
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Life Travel: Cache produit purgé pour l'excursion ID $product_id");
            }
        }
    }
    
    /**
     * Purge le cache de disponibilité lors des changements de statut de commande
     * 
     * @param int $order_id ID de la commande
     * @param string $old_status Ancien statut
     * @param string $new_status Nouveau statut
     */
    public function purge_availability_cache($order_id, $old_status, $new_status) {
        // Purger uniquement si le statut a changé de façon significative
        $significant_change = false;
        $status_changes = [
            'pending' => ['processing', 'completed', 'cancelled'],
            'processing' => ['completed', 'refunded', 'cancelled'],
            'on-hold' => ['processing', 'completed', 'cancelled']
        ];
        
        if (isset($status_changes[$old_status]) && in_array($new_status, $status_changes[$old_status])) {
            $significant_change = true;
        }
        
        if (!$significant_change) {
            return;
        }
        
        // Récupérer la commande
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Parcourir les produits de la commande
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if ($product && $product->get_type() === 'excursion') {
                // Récupérer la date de l'excursion des métadonnées
                $excursion_date = $item->get_meta('_excursion_date');
                
                if (!empty($excursion_date)) {
                    // Purger le cache de disponibilité
                    $cache_key = self::CACHE_PREFIX . 'availability_' . $product_id . '_' . sanitize_key($excursion_date);
                    delete_transient($cache_key);
                    
                    // Journal de débogage
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Life Travel: Cache de disponibilité purgé pour l'excursion ID $product_id à la date $excursion_date");
                    }
                }
            }
        }
    }
}

// Initialisation conditionnelle
function life_travel_initialize_cameroon_cache() {
    // Vérifier si le système est actif dans les options
    $use_cameroon_cache = get_option('life_travel_use_cameroon_cache', true);
    
    if ($use_cameroon_cache) {
        return new Life_Travel_Cameroon_Cache();
    }
    
    return null;
}

// Singleton de cache
function life_travel_cameroon_cache() {
    static $instance = null;
    
    if (null === $instance) {
        $instance = life_travel_initialize_cameroon_cache();
    }
    
    return $instance;
}

// Initialiser le système de cache
life_travel_cameroon_cache();
