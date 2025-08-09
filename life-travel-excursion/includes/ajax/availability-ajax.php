<?php
/**
 * Gestionnaire de requêtes AJAX pour la disponibilité des excursions
 * 
 * Ce fichier gère les requêtes AJAX pour vérifier la disponibilité des dates
 * et autres fonctionnalités liées au calendrier de réservation.
 * Implémenté avec les mêmes standards de sécurité que sync_abandoned_cart.
 *
 * @package Life_Travel_Excursion
 * @since 2.0.0
 */

// Sortie directe interdite
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de gestion des requêtes AJAX pour la disponibilité des excursions
 */
class Life_Travel_Availability_Ajax {
    
    /**
     * Instance unique (Singleton)
     * @var Life_Travel_Availability_Ajax
     */
    private static $instance = null;
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        // Ajouter les points d'entrée AJAX
        add_action('wp_ajax_life_travel_check_date_availability', array($this, 'check_date_availability'));
        add_action('wp_ajax_nopriv_life_travel_check_date_availability', array($this, 'check_date_availability'));
        
        add_action('wp_ajax_life_travel_get_excursion_availability', array($this, 'get_excursion_availability'));
        add_action('wp_ajax_nopriv_life_travel_get_excursion_availability', array($this, 'get_excursion_availability'));
    }
    
    /**
     * Obtenir l'instance unique
     * @return Life_Travel_Availability_Ajax
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Vérifier si une date est disponible pour la réservation
     * Point d'entrée AJAX
     */
    public function check_date_availability() {
        // Vérifier le nonce pour la sécurité
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_frontend_nonce')) {
            wp_send_json_error(array(
                'message' => __('Erreur de sécurité. Veuillez rafraîchir la page.', 'life-travel-excursion')
            ));
            return;
        }
        
        // Validation des entrées
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $participants = isset($_POST['participants']) ? absint($_POST['participants']) : 1;
        
        if (empty($date) || empty($product_id)) {
            wp_send_json_error(array(
                'message' => __('Paramètres invalides.', 'life-travel-excursion')
            ));
            return;
        }
        
        // Valider le format de date (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            wp_send_json_error(array(
                'message' => __('Format de date invalide.', 'life-travel-excursion')
            ));
            return;
        }
        
        // Vérifier si la date est disponible en utilisant le filtre
        $available = apply_filters('life_travel_excursion_check_date_available', true, $date, $product_id);
        
        if (!$available) {
            wp_send_json_error(array(
                'message' => __('Cette date n\'est pas disponible.', 'life-travel-excursion')
            ));
            return;
        }
        
        // Vérifier la capacité pour le nombre de participants demandé
        $has_capacity = $this->check_capacity_for_participants($product_id, $date, $participants);
        
        if (!$has_capacity['available']) {
            wp_send_json_error(array(
                'message' => __('Pas assez de places disponibles pour cette date.', 'life-travel-excursion')
            ));
            return;
        }
        
        // Renvoyer les informations de disponibilité
        $response = array(
            'message' => __('Disponible', 'life-travel-excursion'),
            'stock_status' => 'available'
        );
        
        // Si le stock est limité, indiquer dans la réponse
        if ($has_capacity['stock_status'] === 'limited') {
            $response['message'] = sprintf(
                __('Plus que %d places disponibles !', 'life-travel-excursion'),
                $has_capacity['available_slots']
            );
            $response['stock_status'] = 'limited';
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Récupérer la configuration de disponibilité d'une excursion
     * Point d'entrée AJAX
     */
    public function get_excursion_availability() {
        // Vérifier le nonce pour la sécurité
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_frontend_nonce')) {
            wp_send_json_error(array(
                'message' => __('Erreur de sécurité. Veuillez rafraîchir la page.', 'life-travel-excursion')
            ));
            return;
        }
        
        // Validation des entrées
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        
        if (empty($product_id)) {
            wp_send_json_error(array(
                'message' => __('ID de produit manquant.', 'life-travel-excursion')
            ));
            return;
        }
        
        // Récupérer la configuration de disponibilité
        $availability = apply_filters('life_travel_excursion_get_availability', array(), $product_id);
        
        // Renvoyer les informations
        wp_send_json_success($availability);
    }
    
    /**
     * Vérifier la capacité d'une excursion pour un nombre de participants donné
     * 
     * Version optimisée avec mise en cache et optimisée pour les réseaux lents
     * 
     * @param int $product_id ID du produit/excursion
     * @param string $date Date (format Y-m-d)
     * @param int $participants Nombre de participants
     * @return array Informations sur la capacité
     */
    private function check_capacity_for_participants($product_id, $date, $participants) {
        // Sécurisation des entrées
        $product_id = absint($product_id);
        $date = sanitize_text_field($date);
        $participants = absint($participants);
        
        // Cache mémoire statique pour les appels multiples dans la même requête
        static $capacity_cache = [];
        $cache_key = "capacity_{$product_id}_{$date}_{$participants}";
        
        if (isset($capacity_cache[$cache_key])) {
            return $capacity_cache[$cache_key];
        }
        
        // Résultat par défaut: disponible
        $result = array(
            'available' => true,
            'stock_status' => 'available',
            'available_slots' => 0
        );
        
        // Vérifier si le produit existe
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'excursion') {
            $capacity_cache[$cache_key] = $result;
            return $result;
        }
        
        // Vérifier la capacité maximum pour cette excursion
        $max_capacity = intval(get_post_meta($product_id, '_max_capacity', true));
        if ($max_capacity <= 0) {
            // Si pas de limite de capacité, retourner disponible
            $capacity_cache[$cache_key] = $result;
            return $result;
        }
        
        // Options d'affichage du stock
        $display_stock = get_post_meta($product_id, '_display_stock', true);
        $stock_threshold = get_post_meta($product_id, '_stock_threshold', true);
        if (empty($stock_threshold)) {
            $stock_threshold = 5; // Seuil par défaut
        }
        
        // Options générales stock
        $options = get_option('life_travel_excursion_options', array());
        $low_stock_threshold = isset($options['low_stock_threshold']) ? intval($options['low_stock_threshold']) : 3;
        
        // Si l'affichage du stock est désactivé, retourner simplement la disponibilité
        if ($display_stock !== 'yes') {
            $capacity_cache[$cache_key] = $result;
            return $result;
        }
        
        // Obtenir le nombre de participants déjà réservés
        $total_booked = 0;
        
        // Utiliser l'optimiseur de base de données si disponible
        if (function_exists('life_travel_db_optimizer')) {
            $total_booked = life_travel_db_optimizer()->get_booked_participants_count($product_id, $date);
        } else {
            // Fallback vers la méthode standard
            $bookings = $this->get_bookings_for_date($product_id, $date);
            
            foreach ($bookings as $booking) {
                // Vérifier si c'est une agrégation (optimisation pour les réseaux lents)
                if (isset($booking['aggregate']) && $booking['aggregate']) {
                    $total_booked = intval($booking['participants']);
                    break;
                } else {
                    $total_booked += intval($booking['participants']);
                }
            }
        }
        
        // Calculer les places restantes
        $available_slots = $max_capacity - $total_booked;
        $result['available_slots'] = $available_slots;
        
        // Vérifier si assez de places pour les participants demandés
        if ($available_slots < $participants) {
            $result['available'] = false;
            $capacity_cache[$cache_key] = $result;
            return $result;
        }
        
        // Déterminer le statut du stock
        $remaining_after_booking = $available_slots - $participants;
        
        if ($remaining_after_booking <= $low_stock_threshold) {
            $result['stock_status'] = 'limited';
        } else if ($remaining_after_booking <= $stock_threshold) {
            $result['stock_status'] = 'medium';
        }
        
        // Stocker dans le cache
        $capacity_cache[$cache_key] = $result;
        
        return $result;
    }
    
    /**
     * Récupérer les réservations existantes pour une excursion à une date donnée
     * 
     * Version optimisée avec mise en cache pour les réseaux instables comme au Cameroun
     * 
     * @param int $product_id ID du produit/excursion
     * @param string $date Date (format Y-m-d)
     * @return array Liste des réservations
     */
    private function get_bookings_for_date($product_id, $date) {
        // Sécurisation des entrées
        $product_id = absint($product_id);
        $date = sanitize_text_field($date);
        
        // Vérifier si l'optimiseur de base de données est disponible
        if (function_exists('life_travel_db_optimizer')) {
            // Utiliser l'optimiseur pour obtenir le nombre de participants réservés
            $total_participants = life_travel_db_optimizer()->get_booked_participants_count($product_id, $date);
            
            // Simplification pour les contextes à faible connectivité: 
            // Retourner une réservation fictive avec le nombre total de participants
            // au lieu de plusieurs réservations individuelles
            if ($total_participants > 0) {
                return [
                    [
                        'order_id' => 0, // ID fictif
                        'participants' => $total_participants,
                        'aggregate' => true // Indiquer que c'est une agrégation pour détection
                    ]
                ];
            }
            
            return []; // Aucune réservation
        }
        
        // Fallback vers l'ancienne méthode si l'optimiseur n'est pas disponible
        global $wpdb;
        $bookings = array();
        
        // Code de cache local simple comme fallback
        static $cache = [];
        $cache_key = "bookings_{$product_id}_{$date}";
        
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }
        
        // Récupérer les commandes avec ce produit pour cette date (version optimisée de la requête)
        $query = $wpdb->prepare(
            "SELECT p.ID as order_id, 
                    COALESCE((SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '_booking_participants' LIMIT 1), '1') as participants
            FROM {$wpdb->posts} p
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id' AND oim.meta_value = %d
            JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start_date' AND pm_start.meta_value <= %s
            LEFT JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = '_booking_end_date' AND (pm_end.meta_value >= %s OR pm_end.meta_value IS NULL)
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-on-hold')
            AND (pm_end.meta_value IS NULL OR pm_start.meta_value = %s)
            GROUP BY p.ID",
            $product_id,
            $date,
            $date,
            $date
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if (!empty($results)) {
            foreach ($results as $result) {
                $bookings[] = array(
                    'order_id' => $result['order_id'],
                    'participants' => intval($result['participants'])
                );
            }
        }
        
        // Stocker dans le cache local
        $cache[$cache_key] = $bookings;
        
        return $bookings;
    }
}

// Initialiser l'instance
Life_Travel_Availability_Ajax::get_instance();
