<?php
/**
 * Optimisation des requêtes de base de données pour Life Travel Excursion
 *
 * Ce fichier implémente des solutions d'optimisation pour les requêtes de base
 * de données, particulièrement pour les contextes à faible connectivité comme
 * au Cameroun. Il met en cache les résultats les plus fréquemment demandés et
 * optimise les requêtes les plus coûteuses.
 *
 * @package Life Travel Excursion
 * @version 3.0.0
 */

defined('ABSPATH') || exit;

/**
 * Classe d'optimisation des requêtes de base de données
 */
class Life_Travel_DB_Optimizer {
    /**
     * Instance unique (Singleton)
     * @var Life_Travel_DB_Optimizer
     */
    private static $instance = null;
    
    /**
     * Tableau de mise en cache des requêtes
     * @var array
     */
    private $cache = [];
    
    /**
     * Durée de vie du cache en secondes
     * @var int
     */
    private $cache_ttl = 3600; // 1 heure par défaut
    
    /**
     * Durée de vie courte pour les données fréquemment modifiées
     * @var int
     */
    private $short_cache_ttl = 300; // 5 minutes
    
    /**
     * Préfixe pour les clés de transient
     * @var string
     */
    private $transient_prefix = 'lte_db_cache_';
    
    /**
     * Constructeur privé (pattern Singleton)
     */
    private function __construct() {
        // Définir la durée du cache en fonction de la connectivité
        $this->adjust_cache_ttl();
        
        // Activer la mise en cache des requêtes
        add_filter('life_travel_excursion_check_date_available', [$this, 'cached_check_date_available'], 10, 3);
        
        // Purge du cache lors des modifications pertinentes
        add_action('woocommerce_order_status_changed', [$this, 'purge_cache_on_order_change'], 10, 3);
        add_action('woocommerce_new_order', [$this, 'purge_availability_cache']);
        add_action('woocommerce_update_product', [$this, 'purge_product_cache']);
        
        // Optimisation des index sur les tables de meta
        add_action('admin_init', [$this, 'maybe_create_booking_indexes']);
    }
    
    /**
     * Obtenir l'instance unique
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Ajuste la durée du cache en fonction de la connectivité détectée
     */
    private function adjust_cache_ttl() {
        // Détecter la connectivité (utilisation de la fonction partagée si disponible)
        $connection_status = 'normal';
        if (function_exists('life_travel_detect_network_status')) {
            $connection_status = life_travel_detect_network_status();
        }
        
        // Ajuster la durée du cache selon la connectivité
        switch ($connection_status) {
            case 'slow':
                // Connectivité lente, augmenter la durée du cache
                $this->cache_ttl = 7200; // 2 heures
                $this->short_cache_ttl = 900; // 15 minutes
                break;
            case 'very_slow':
                // Très lente, maximiser la durée du cache
                $this->cache_ttl = 14400; // 4 heures
                $this->short_cache_ttl = 1800; // 30 minutes
                break;
            case 'offline':
                // Utilisation en mode offline (via service worker)
                $this->cache_ttl = 86400; // 24 heures
                $this->short_cache_ttl = 3600; // 1 heure
                break;
            case 'normal':
            default:
                // Connectivité normale, valeurs par défaut
                $this->cache_ttl = 3600; // 1 heure
                $this->short_cache_ttl = 300; // 5 minutes
                break;
        }
    }
    
    /**
     * Version mise en cache de la vérification de disponibilité des dates
     */
    public function cached_check_date_available($available, $date, $product_id) {
        $cache_key = $this->transient_prefix . 'avail_' . $product_id . '_' . str_replace('-', '', $date);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result === 'yes';
        }
        
        // Si pas en cache, exécuter la vérification standard
        $actual_result = $this->check_date_available_optimized($date, $product_id);
        
        // Mettre en cache le résultat
        set_transient($cache_key, $actual_result ? 'yes' : 'no', $this->short_cache_ttl);
        
        return $actual_result;
    }
    
    /**
     * Version optimisée de la vérification de disponibilité des dates
     */
    private function check_date_available_optimized($date, $product_id) {
        global $wpdb;
        
        // Sécuriser les entrées
        $product_id = absint($product_id);
        $date = sanitize_text_field($date);
        
        // Vérifier les limites de dates configurées pour le produit
        $min_date = get_post_meta($product_id, '_min_booking_date', true);
        $max_date = get_post_meta($product_id, '_max_booking_date', true);
        
        if (!empty($min_date) && $date < $min_date) {
            return false;
        }
        
        if (!empty($max_date) && $date > $max_date) {
            return false;
        }
        
        // Vérifier les dates exclues
        $excluded_dates = get_post_meta($product_id, '_excluded_dates', true);
        if (!empty($excluded_dates) && is_array($excluded_dates) && in_array($date, $excluded_dates)) {
            return false;
        }
        
        // Récupérer la capacité maximum
        $max_capacity = intval(get_post_meta($product_id, '_max_capacity', true));
        if ($max_capacity <= 0) {
            $max_capacity = 999; // Valeur par défaut si non définie
        }
        
        // Requête optimisée pour obtenir les réservations à cette date
        $booked_participants = $this->get_booked_participants_count($product_id, $date);
        
        // Vérifier s'il reste de la place
        return $booked_participants < $max_capacity;
    }
    
    /**
     * Version optimisée pour obtenir le nombre de participants déjà réservés
     */
    public function get_booked_participants_count($product_id, $date) {
        global $wpdb;
        
        // Sécuriser les entrées
        $product_id = absint($product_id);
        $date = sanitize_text_field($date);
        
        // Vérifier dans le cache mémoire d'abord
        $cache_key = "booked_{$product_id}_{$date}";
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        // Ensuite vérifier dans le cache transient
        $transient_key = $this->transient_prefix . 'booked_' . $product_id . '_' . str_replace('-', '', $date);
        $cached_count = get_transient($transient_key);
        
        if ($cached_count !== false) {
            // Mettre dans le cache mémoire aussi
            $this->cache[$cache_key] = $cached_count;
            return $cached_count;
        }
        
        // Requête optimisée pour les réservations d'un jour
        $single_day_query = $wpdb->prepare(
            "SELECT SUM(CAST(oim2.meta_value AS UNSIGNED)) as total
            FROM {$wpdb->prefix}woocommerce_order_items oi
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id' AND oim.meta_value = %d
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_booking_participants'
            JOIN {$wpdb->posts} p ON oi.order_id = p.ID
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_booking_start_date' AND pm.meta_value = %s
            WHERE p.post_type = 'shop_order' AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-on-hold')",
            $product_id,
            $date
        );
        
        $single_day_result = $wpdb->get_var($single_day_query);
        $single_day_count = $single_day_result ? intval($single_day_result) : 0;
        
        // Requête optimisée pour les réservations de plusieurs jours
        $multi_day_query = $wpdb->prepare(
            "SELECT SUM(CAST(oim2.meta_value AS UNSIGNED)) as total
            FROM {$wpdb->prefix}woocommerce_order_items oi
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id' AND oim.meta_value = %d
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_booking_participants'
            JOIN {$wpdb->posts} p ON oi.order_id = p.ID
            JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start_date' AND pm_start.meta_value <= %s
            JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = '_booking_end_date' AND pm_end.meta_value >= %s
            WHERE p.post_type = 'shop_order' AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-on-hold')",
            $product_id,
            $date,
            $date
        );
        
        $multi_day_result = $wpdb->get_var($multi_day_query);
        $multi_day_count = $multi_day_result ? intval($multi_day_result) : 0;
        
        // Éviter le double comptage en prenant le plus grand des deux (les réservations d'un jour sont incluses dans les multi-jours)
        $total_count = max($single_day_count, $multi_day_count);
        
        // Mettre en cache le résultat
        set_transient($transient_key, $total_count, $this->short_cache_ttl);
        $this->cache[$cache_key] = $total_count;
        
        return $total_count;
    }
    
    /**
     * Optimisation: Créer des index nécessaires pour accélérer les requêtes de réservation
     */
    public function maybe_create_booking_indexes() {
        global $wpdb;
        
        // Vérifier si l'utilisateur est administrateur
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Option pour suivre la création des index
        $indexes_created = get_option('life_travel_booking_indexes_created');
        
        // Ne créer les index qu'une seule fois
        if ($indexes_created === 'yes') {
            return;
        }
        
        // Index pour les métadonnées de réservation
        $this->create_booking_meta_index('_booking_start_date');
        $this->create_booking_meta_index('_booking_end_date');
        $this->create_booking_meta_index('_booking_participants');
        $this->create_booking_meta_index('_life_travel_booking_data');
        
        // Index pour les métadonnées de produit d'excursion
        $this->create_booking_meta_index('_product_id', $wpdb->prefix . 'woocommerce_order_itemmeta');
        
        // Marquer que les index ont été créés
        update_option('life_travel_booking_indexes_created', 'yes');
    }
    
    /**
     * Crée un index sur une colonne de métadonnées si nécessaire
     */
    private function create_booking_meta_index($meta_key, $table = '') {
        global $wpdb;
        
        // Utiliser la table postmeta par défaut si aucune table n'est spécifiée
        $table = !empty($table) ? $table : $wpdb->postmeta;
        
        // Nom de l'index
        $index_name = 'idx_' . md5($meta_key);
        
        // Vérifier si l'index existe déjà
        $index_exists = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = '{$index_name}'");
        
        if (empty($index_exists)) {
            // Créer l'index
            $wpdb->query("CREATE INDEX {$index_name} ON {$table} (meta_key, meta_value)");
        }
    }
    
    /**
     * Purge le cache lors d'un changement de statut de commande
     */
    public function purge_cache_on_order_change($order_id, $old_status, $new_status) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Purger uniquement pour les changements pertinents
        $relevant_statuses = ['processing', 'completed', 'on-hold', 'cancelled', 'refunded'];
        
        $old_relevant = in_array('wc-' . $old_status, $relevant_statuses) || in_array($old_status, $relevant_statuses);
        $new_relevant = in_array('wc-' . $new_status, $relevant_statuses) || in_array($new_status, $relevant_statuses);
        
        if (!$old_relevant && !$new_relevant) {
            return;
        }
        
        // Parcourir les produits de la commande
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            // Purger uniquement pour les excursions
            if ($product && $product->get_type() === 'excursion') {
                $this->purge_product_cache($product_id);
            }
        }
    }
    
    /**
     * Purge le cache de disponibilité en général
     */
    public function purge_availability_cache() {
        global $wpdb;
        
        // Supprimer tous les transients liés à la disponibilité
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%{$this->transient_prefix}avail_%' OR option_name LIKE '%{$this->transient_prefix}booked_%'");
        
        // Réinitialiser le cache mémoire
        $this->cache = [];
    }
    
    /**
     * Purge le cache pour un produit spécifique
     */
    public function purge_product_cache($product_id) {
        global $wpdb;
        
        $product_id = is_object($product_id) ? $product_id->get_id() : absint($product_id);
        
        // Supprimer tous les transients liés à ce produit
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%{$this->transient_prefix}%_{$product_id}_%'"));
        
        // Réinitialiser les entrées de cache mémoire liées à ce produit
        foreach ($this->cache as $key => $value) {
            if (strpos($key, "booked_{$product_id}_") !== false) {
                unset($this->cache[$key]);
            }
        }
    }
}

// Initialiser l'optimiseur
function life_travel_db_optimizer() {
    return Life_Travel_DB_Optimizer::get_instance();
}

// Démarrer l'optimiseur
add_action('plugins_loaded', 'life_travel_db_optimizer', 15);

/**
 * Fonction d'optimisation asynchrone des requêtes intensives
 * 
 * @param callable $callback La fonction à exécuter
 * @param array $args Les arguments à passer à la fonction
 * @param int $priority La priorité (plus basse = plus prioritaire)
 * @return boolean|array False si échec, tableau de résultats sinon
 */
function life_travel_async_query($callback, $args = [], $priority = 10) {
    // Vérifier si on peut utiliser des requêtes asynchrones
    if (!function_exists('wp_async_task_add') && !class_exists('WP_Async_Task')) {
        // Si l'API asynchrone n'est pas disponible, exécuter synchroniquement
        return call_user_func_array($callback, $args);
    }
    
    // Identifiant unique pour cette tâche
    $task_id = 'lte_query_' . md5(serialize([$callback, $args]));
    
    // Ajouter la tâche à la file d'attente asynchrone
    $result = wp_async_task_add([
        'id' => $task_id,
        'callback' => $callback,
        'args' => $args,
        'priority' => $priority
    ]);
    
    return $result;
}
