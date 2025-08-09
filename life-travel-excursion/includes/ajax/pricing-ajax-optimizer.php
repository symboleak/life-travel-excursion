<?php
/**
 * Optimiseur AJAX de calcul de prix pour contexte camerounais
 *
 * Ce fichier contient des optimisations pour le calcul de prix des excursions,
 * spécifiquement conçues pour des connexions lentes ou instables.
 *
 * @package Life Travel Excursion
 * @version 2.5.0
 */

defined('ABSPATH') || exit;

/**
 * Classe d'optimisation AJAX pour le calcul de prix
 */
class Life_Travel_Pricing_Ajax_Optimizer {
    /**
     * Instance unique (Singleton)
     * @var Life_Travel_Pricing_Ajax_Optimizer
     */
    private static $instance = null;
    
    /**
     * Niveaux de cache
     * @var array
     */
    private $cache_levels = [
        'minimal' => 5 * MINUTE_IN_SECONDS,     // 5 minutes
        'low'     => 15 * MINUTE_IN_SECONDS,    // 15 minutes
        'medium'  => 30 * MINUTE_IN_SECONDS,    // 30 minutes
        'high'    => HOUR_IN_SECONDS,           // 1 heure
        'extreme' => 6 * HOUR_IN_SECONDS        // 6 heures
    ];
    
    /**
     * Données précalculées pour scénarios courants
     * @var array
     */
    private $precalculated_data = [];
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_precalculated_data();
    }
    
    /**
     * Retourne l'instance unique (Singleton)
     * 
     * @return Life_Travel_Pricing_Ajax_Optimizer
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialise les hooks WordPress
     */
    private function init_hooks() {
        // Remplace les points d'entrée AJAX standards par des versions optimisées
        add_action('wp_ajax_life_travel_calculate_price', [$this, 'optimized_calculate_price']);
        add_action('wp_ajax_nopriv_life_travel_calculate_price', [$this, 'optimized_calculate_price']);
        
        // Filtre pour modifier la durée de mise en cache en fonction des conditions réseau
        add_filter('life_travel_pricing_cache_duration', [$this, 'adaptive_cache_duration'], 10, 2);
        
        // Hook pour précharger les prix des excursions populaires
        add_action('wp_loaded', [$this, 'preload_popular_excursion_prices']);
        
        // Points d'entrée pour le mode hors-ligne
        add_action('wp_ajax_life_travel_get_pricing_bundle', [$this, 'get_pricing_bundle']);
        add_action('wp_ajax_nopriv_life_travel_get_pricing_bundle', [$this, 'get_pricing_bundle']);
    }
    
    /**
     * Charge les données précalculées pour les scénarios courants
     */
    private function load_precalculated_data() {
        $cached_data = get_transient('life_travel_precalculated_prices');
        
        if ($cached_data !== false) {
            $this->precalculated_data = $cached_data;
            return;
        }
        
        // Récupérer les excursions les plus populaires
        $popular_products = $this->get_popular_excursions();
        $precalculated = [];
        
        foreach ($popular_products as $product_id) {
            // Précalculer pour les combinaisons courantes
            $common_scenarios = $this->get_common_scenarios($product_id);
            $product_prices = [];
            
            foreach ($common_scenarios as $scenario) {
                $price_key = $this->generate_price_key(
                    $product_id,
                    $scenario['participants'],
                    $scenario['start_date'],
                    $scenario['end_date'],
                    $scenario['extras'],
                    $scenario['activities']
                );
                
                // Calculer le prix
                $pricing = life_travel_excursion_get_pricing_details(
                    $product_id,
                    $scenario['participants'],
                    $scenario['start_date'],
                    $scenario['end_date'],
                    $scenario['extras'],
                    $scenario['activities']
                );
                
                $product_prices[$price_key] = $pricing;
            }
            
            $precalculated[$product_id] = $product_prices;
        }
        
        $this->precalculated_data = $precalculated;
        set_transient('life_travel_precalculated_prices', $precalculated, DAY_IN_SECONDS);
    }
    
    /**
     * Version optimisée de la fonction de calcul de prix pour AJAX
     */
    public function optimized_calculate_price() {
        // Vérifier le nonce CSRF
        check_ajax_referer('life_travel_calculate_price_nonce', 'security');
        
        // Valider et assainir les entrées
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $participants = isset($_POST['participants']) ? absint($_POST['participants']) : 1;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $extras = isset($_POST['extras']) ? array_map('absint', (array)$_POST['extras']) : [];
        $activities = isset($_POST['activities']) ? array_map('absint', (array)$_POST['activities']) : [];
        
        // Vérifier la connectivité du client
        $network_status = isset($_POST['network_status']) ? sanitize_text_field($_POST['network_status']) : 'normal';
        
        // Valider le produit
        if (!$product_id || get_post_type($product_id) !== 'product') {
            $this->send_fallback_response('error', __('Produit invalide', 'life-travel-excursion'), $network_status);
            return;
        }
        
        // Valider les dates
        if (!empty($start_date)) {
            // Valider le format de date
            $start_timestamp = strtotime($start_date);
            if (!$start_timestamp) {
                $this->send_fallback_response('error', __('Format de date invalide', 'life-travel-excursion'), $network_status);
                return;
            }
            
            // Vérifier que la date est dans le futur
            if ($start_timestamp < strtotime('today')) {
                $this->send_fallback_response('error', __('La date doit être dans le futur', 'life-travel-excursion'), $network_status);
                return;
            }
        } else {
            $this->send_fallback_response('error', __('Date requise', 'life-travel-excursion'), $network_status);
            return;
        }
        
        // Générer la clé de cache
        $price_key = $this->generate_price_key($product_id, $participants, $start_date, $end_date, $extras, $activities);
        
        // Vérifier les données précalculées (le plus rapide)
        if (isset($this->precalculated_data[$product_id][$price_key])) {
            wp_send_json_success($this->precalculated_data[$product_id][$price_key]);
            return;
        }
        
        // Calculer dynamiquement
        $pricing = $this->get_optimized_pricing(
            $product_id,
            $participants,
            $start_date,
            $end_date,
            $extras,
            $activities,
            $network_status
        );
        
        wp_send_json_success($pricing);
    }
    
    /**
     * Crée une version optimisée du calcul de prix avec adaptations au contexte camerounais
     * 
     * @param int $product_id ID du produit
     * @param int $participants Nombre de participants
     * @param string $start_date Date de début
     * @param string $end_date Date de fin
     * @param array $extras Extras sélectionnés
     * @param array $activities Activités sélectionnées
     * @param string $network_status Statut réseau (normal, slow, very_slow, offline)
     * @return array Détails du prix
     */
    private function get_optimized_pricing($product_id, $participants, $start_date, $end_date, $extras, $activities, $network_status = 'normal') {
        // Générer une clé de cache unique
        $cache_key = 'lt_price_' . md5($product_id . '_' . $participants . '_' . $start_date . '_' . $end_date . '_' . serialize($extras) . '_' . serialize($activities));
        
        // Déterminer la durée de mise en cache en fonction de l'état du réseau
        $cache_duration = $this->get_cache_duration($network_status, $product_id);
        
        // Vérifier le cache
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            // Enregistrer le succès du cache
            $this->log_cache_hit($cache_key, $network_status);
            return $cached_result;
        }
        
        // Si nous sommes en mode hors-ligne ou très lent, utiliser l'approximation
        if ($network_status === 'offline' || $network_status === 'very_slow') {
            $approx_pricing = $this->get_approximate_pricing($product_id, $participants, $start_date);
            if ($approx_pricing) {
                // Sauvegarder dans le cache avec une durée plus longue
                set_transient($cache_key, $approx_pricing, $cache_duration);
                return $approx_pricing;
            }
        }
        
        // Calculer normalement si aucune des optimisations précédentes n'a fonctionné
        $pricing = life_travel_excursion_get_pricing_details(
            $product_id,
            $participants,
            $start_date,
            $end_date,
            $extras,
            $activities
        );
        
        // Mettre en cache avec la durée adaptée
        set_transient($cache_key, $pricing, $cache_duration);
        
        // Enregistrer l'échec du cache
        $this->log_cache_miss($cache_key, $network_status);
        
        return $pricing;
    }
    
    /**
     * Génère une approximation du prix basée sur les données de base
     * Utilisé en mode hors-ligne ou réseau très lent
     * 
     * @param int $product_id ID du produit
     * @param int $participants Nombre de participants
     * @param string $start_date Date de début
     * @return array|false Approximation du prix ou false si impossible
     */
    private function get_approximate_pricing($product_id, $participants, $start_date) {
        // Récupérer le produit
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        // Prix de base
        $base_price = floatval($product->get_price());
        
        // Vérifier si c'est en haute saison
        $is_high_season = $this->is_high_season($start_date);
        $season_multiplier = $is_high_season ? 1.2 : 1.0;
        
        // Appliquer des multiplicateurs simples basés sur le nombre de participants
        $group_discount = 1.0;
        if ($participants >= 10) {
            $group_discount = 0.9;
        } elseif ($participants >= 5) {
            $group_discount = 0.95;
        }
        
        // Calculer un prix approximatif
        $approx_price_per_person = $base_price * $season_multiplier * $group_discount;
        $approx_total = $approx_price_per_person * $participants;
        
        // Ajouter une note indiquant que c'est une approximation
        return [
            'price_per_person' => life_travel_excursion_convert_price($approx_price_per_person),
            'subtotal' => life_travel_excursion_convert_price($approx_total),
            'extras_price' => life_travel_excursion_convert_price(0),
            'extras_breakdown' => [],
            'activities_price' => life_travel_excursion_convert_price(0),
            'activities_breakdown' => [],
            'total_price' => life_travel_excursion_convert_price($approx_total),
            'participants' => $participants,
            'days' => 1,
            'is_approximation' => true,
            'approximation_reason' => $network_status,
            'calculation_time' => current_time('mysql')
        ];
    }
    
    /**
     * Détermine si une date est en haute saison
     * 
     * @param string $date Date au format YYYY-MM-DD
     * @return bool True si haute saison
     */
    private function is_high_season($date) {
        $month = date('n', strtotime($date));
        $high_season_months = [12, 1, 2, 7, 8]; // Décembre, Janvier, Février, Juillet, Août
        
        return in_array($month, $high_season_months);
    }
    
    /**
     * Détermine la durée de mise en cache en fonction du statut réseau
     * 
     * @param string $network_status Statut réseau (normal, slow, very_slow, offline)
     * @param int $product_id ID du produit pour personnalisation
     * @return int Durée en secondes
     */
    private function get_cache_duration($network_status, $product_id) {
        $base_duration = $this->cache_levels['medium']; // 30 minutes par défaut
        
        switch ($network_status) {
            case 'normal':
                $base_duration = $this->cache_levels['minimal']; // 5 minutes
                break;
            case 'slow':
                $base_duration = $this->cache_levels['medium']; // 30 minutes
                break;
            case 'very_slow':
                $base_duration = $this->cache_levels['high']; // 1 heure
                break;
            case 'offline':
                $base_duration = $this->cache_levels['extreme']; // 6 heures
                break;
        }
        
        // Permettre la personnalisation via le filtre
        return apply_filters('life_travel_pricing_cache_duration', $base_duration, [
            'network_status' => $network_status,
            'product_id' => $product_id
        ]);
    }
    
    /**
     * Adapte la durée du cache en fonction de facteurs externes
     * 
     * @param int $duration Durée en secondes
     * @param array $context Contexte de la requête
     * @return int Durée adaptée
     */
    public function adaptive_cache_duration($duration, $context) {
        // Facteurs d'ajustement
        $adjustments = [];
        
        // 1. Jour de la semaine (plus long le week-end)
        $is_weekend = (date('N') >= 6);
        if ($is_weekend) {
            $adjustments[] = 1.5; // +50% le week-end
        }
        
        // 2. Heure de la journée (plus court pendant les heures de pointe)
        $hour = (int)date('G');
        $is_peak_hour = ($hour >= 9 && $hour <= 18);
        if ($is_peak_hour) {
            $adjustments[] = 0.8; // -20% pendant les heures de pointe
        } else {
            $adjustments[] = 1.3; // +30% pendant les heures creuses
        }
        
        // 3. Haute saison
        if (isset($context['product_id'])) {
            $product_meta = get_post_meta($context['product_id'], '_peak_season', true);
            $is_peak_season = !empty($product_meta) && $product_meta === 'yes';
            
            if ($is_peak_season) {
                $adjustments[] = 0.7; // -30% en haute saison
            }
        }
        
        // Appliquer les ajustements
        foreach ($adjustments as $factor) {
            $duration = (int)($duration * $factor);
        }
        
        return $duration;
    }
    
    /**
     * Génère la clé de cache pour un scénario de prix
     * 
     * @param int $product_id ID du produit
     * @param int $participants Nombre de participants
     * @param string $start_date Date de début
     * @param string $end_date Date de fin
     * @param array $extras Extras sélectionnés
     * @param array $activities Activités sélectionnées
     * @return string Clé de cache unique
     */
    private function generate_price_key($product_id, $participants, $start_date, $end_date, $extras, $activities) {
        return md5($product_id . '_' . $participants . '_' . $start_date . '_' . $end_date . '_' . serialize($extras) . '_' . serialize($activities));
    }
    
    /**
     * Précharge les prix des excursions populaires
     */
    public function preload_popular_excursion_prices() {
        // Ne précharger que si le cache est vide
        if (get_transient('life_travel_precalculated_prices') !== false) {
            return;
        }
        
        // Programmation d'une tâche en arrière-plan pour ne pas ralentir le chargement
        if (!wp_next_scheduled('life_travel_preload_excursion_prices')) {
            wp_schedule_single_event(time() + 60, 'life_travel_preload_excursion_prices');
        }
    }
    
    /**
     * Obtient la liste des excursions les plus populaires
     * 
     * @param int $limit Nombre d'excursions à récupérer
     * @return array Liste des IDs d'excursions
     */
    private function get_popular_excursions($limit = 5) {
        global $wpdb;
        
        // Récupérer depuis le cache si disponible
        $cached = get_transient('life_travel_popular_excursions');
        if ($cached !== false) {
            return $cached;
        }
        
        // Requête pour obtenir les excursions les plus réservées
        $popular_query = "
            SELECT product_id, COUNT(*) as bookings 
            FROM {$wpdb->prefix}life_travel_bookings 
            GROUP BY product_id 
            ORDER BY bookings DESC 
            LIMIT %d
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($popular_query, $limit), ARRAY_A);
        $popular_ids = [];
        
        if ($results) {
            foreach ($results as $row) {
                $popular_ids[] = (int)$row['product_id'];
            }
        } else {
            // Fallback: récupérer les excursions les plus récentes
            $args = [
                'post_type' => 'product',
                'posts_per_page' => $limit,
                'post_status' => 'publish',
                'orderby' => 'date',
                'order' => 'DESC',
                'tax_query' => [
                    [
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => 'excursion',
                    ]
                ],
                'fields' => 'ids'
            ];
            
            $popular_ids = get_posts($args);
        }
        
        // Mettre en cache pour 24 heures
        set_transient('life_travel_popular_excursions', $popular_ids, DAY_IN_SECONDS);
        
        return $popular_ids;
    }
    
    /**
     * Obtient les scénarios courants pour une excursion
     * 
     * @param int $product_id ID du produit
     * @return array Liste des scénarios courants
     */
    private function get_common_scenarios($product_id) {
        // Participants courants
        $common_participants = [1, 2, 4, 6, 10];
        
        // Dates courantes (aujourd'hui + 7 jours, + 14 jours, + 30 jours)
        $dates = [
            date('Y-m-d', strtotime('+7 days')),
            date('Y-m-d', strtotime('+14 days')),
            date('Y-m-d', strtotime('+30 days'))
        ];
        
        $scenarios = [];
        
        foreach ($common_participants as $participants) {
            foreach ($dates as $date) {
                $scenarios[] = [
                    'participants' => $participants,
                    'start_date' => $date,
                    'end_date' => $date,
                    'extras' => [],
                    'activities' => []
                ];
            }
        }
        
        return $scenarios;
    }
    
    /**
     * Fournit un package de données de prix pour le mode hors-ligne
     */
    public function get_pricing_bundle() {
        // Vérifier le nonce
        check_ajax_referer('life_travel_offline_bundle_nonce', 'security');
        
        // Récupérer les excursions populaires
        $popular_excursions = $this->get_popular_excursions(10);
        $pricing_data = [];
        
        foreach ($popular_excursions as $product_id) {
            // Récupérer les données de base du produit
            $product = wc_get_product($product_id);
            if (!$product) continue;
            
            $product_data = [
                'id' => $product_id,
                'name' => $product->get_name(),
                'base_price' => $product->get_price(),
                'image' => get_the_post_thumbnail_url($product_id, 'thumbnail'),
                'pricing_scenarios' => []
            ];
            
            // Ajouter des scénarios précalculés
            $scenarios = $this->get_common_scenarios($product_id);
            
            foreach ($scenarios as $scenario) {
                $pricing = life_travel_excursion_get_pricing_details(
                    $product_id,
                    $scenario['participants'],
                    $scenario['start_date'],
                    $scenario['end_date'],
                    $scenario['extras'],
                    $scenario['activities']
                );
                
                // Clé unique pour ce scénario
                $scenario_key = $this->generate_price_key(
                    $product_id,
                    $scenario['participants'],
                    $scenario['start_date'],
                    $scenario['end_date'],
                    $scenario['extras'],
                    $scenario['activities']
                );
                
                $product_data['pricing_scenarios'][$scenario_key] = [
                    'params' => $scenario,
                    'pricing' => $pricing
                ];
            }
            
            $pricing_data[$product_id] = $product_data;
        }
        
        // Ajouter des métadonnées pour la gestion client-side
        $bundle = [
            'pricing_data' => $pricing_data,
            'timestamp' => current_time('timestamp'),
            'expiry' => current_time('timestamp') + 6 * HOUR_IN_SECONDS,
            'version' => '1.0',
            'currency' => get_woocommerce_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol()
        ];
        
        wp_send_json_success($bundle);
    }
    
    /**
     * Envoie une réponse de secours en cas de problème
     * 
     * @param string $status Statut (success/error)
     * @param string $message Message à afficher
     * @param string $network_status Statut réseau
     */
    private function send_fallback_response($status, $message, $network_status) {
        // Réponse standard pour réseau normal
        if ($network_status === 'normal' || $network_status === 'slow') {
            if ($status === 'error') {
                wp_send_json_error(['message' => $message]);
            } else {
                wp_send_json_success(['message' => $message]);
            }
            return;
        }
        
        // Pour les connexions très lentes ou hors-ligne, envoyer une réponse minimale
        $response = [
            'status' => $status,
            'message' => $message,
            'is_fallback' => true,
            'timestamp' => current_time('timestamp')
        ];
        
        // Compression de la réponse
        if (function_exists('gzencode')) {
            $compressed = gzencode(json_encode($response), 9);
            header('Content-Encoding: gzip');
            echo $compressed;
        } else {
            echo json_encode($response);
        }
        
        die();
    }
    
    /**
     * Enregistre un succès de cache
     * 
     * @param string $cache_key Clé de cache
     * @param string $network_status Statut réseau
     */
    private function log_cache_hit($cache_key, $network_status) {
        $cache_stats = get_option('life_travel_cache_stats', ['hits' => 0, 'misses' => 0]);
        $cache_stats['hits']++;
        update_option('life_travel_cache_stats', $cache_stats);
        
        // Enregistrement détaillé pour analyse
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Life Travel] Cache hit: $cache_key ($network_status)");
        }
    }
    
    /**
     * Enregistre un échec de cache
     * 
     * @param string $cache_key Clé de cache
     * @param string $network_status Statut réseau
     */
    private function log_cache_miss($cache_key, $network_status) {
        $cache_stats = get_option('life_travel_cache_stats', ['hits' => 0, 'misses' => 0]);
        $cache_stats['misses']++;
        update_option('life_travel_cache_stats', $cache_stats);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Life Travel] Cache miss: $cache_key ($network_status)");
        }
    }
}

// Initialisation
function life_travel_pricing_ajax_optimizer() {
    return Life_Travel_Pricing_Ajax_Optimizer::get_instance();
}

// Démarrage automatique
add_action('plugins_loaded', 'life_travel_pricing_ajax_optimizer');
