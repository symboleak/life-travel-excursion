<?php
/**
 * Gestionnaire de disponibilité des excursions pour Life Travel
 * 
 * Ce fichier gère la disponibilité des excursions basée sur les paramètres administratifs,
 * appliquant les règles de réservation, dates exclues, et autres contraintes.
 * Implémenté avec les mêmes standards de sécurité et robustesse que sync_abandoned_cart().
 * 
 * @package Life_Travel_Excursion
 * @since 2.0.0
 */

// Sortie directe interdite
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe qui gère la disponibilité des excursions en tenant compte des paramètres d'administration
 */
class Life_Travel_Availability_Manager {
    
    /**
     * Instance unique (Singleton)
     * @var Life_Travel_Availability_Manager
     */
    private static $instance = null;
    
    /**
     * Options de configuration globales mises en cache
     * @var array
     */
    private $global_options = null;
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        // Ajouter les actions et filtres
        add_filter('life_travel_excursion_get_availability', array($this, 'get_excursion_availability'), 10, 2);
        add_filter('life_travel_excursion_check_date_available', array($this, 'check_date_available'), 10, 3);
        add_filter('life_travel_excursion_get_booking_window', array($this, 'get_booking_window'), 10, 2);
        add_filter('life_travel_excursion_validate_booking', array($this, 'validate_booking_request'), 10, 3);
        
        // Ajouter les données à l'API frontend
        add_filter('life_travel_frontend_localize_script', array($this, 'add_frontend_data'));
    }
    
    /**
     * Obtenir l'instance unique
     * @return Life_Travel_Availability_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtenir les options globales de configuration
     * @return array Les options
     */
    public function get_global_options() {
        if ($this->global_options === null) {
            $this->global_options = get_option('life_travel_excursion_options', array());
        }
        return $this->global_options;
    }
    
    /**
     * Ajouter les données de disponibilité aux scripts frontend
     * 
     * @param array $data Les données existantes pour le script frontend
     * @return array Les données modifiées
     */
    public function add_frontend_data($data) {
        $options = $this->get_global_options();
        
        // Ajouter les paramètres de disponibilité globaux
        $data['availability'] = array(
            'booking_window_days' => isset($options['booking_window_days']) ? intval($options['booking_window_days']) : 180,
            'default_availability' => isset($options['default_availability']) ? $options['default_availability'] : array(),
            'excluded_dates' => isset($options['excluded_dates']) ? $options['excluded_dates'] : array(),
            'daily_start_time' => isset($options['daily_start_time']) ? $options['daily_start_time'] : '08:00',
            'daily_end_time' => isset($options['daily_end_time']) ? $options['daily_end_time'] : '18:00',
            'group_booking_lead_time' => isset($options['group_booking_lead_time']) ? intval($options['group_booking_lead_time']) : 24,
            'private_booking_lead_time' => isset($options['private_booking_lead_time']) ? intval($options['private_booking_lead_time']) : 48,
        );
        
        // Ajouter les paramètres de formatage des prix
        $data['price_format'] = array(
            'currency_position' => isset($options['currency_position']) ? $options['currency_position'] : 'after',
            'thousand_separator' => isset($options['price_thousand_separator']) ? $options['price_thousand_separator'] : ' ',
            'decimal_separator' => isset($options['price_decimal_separator']) ? $options['price_decimal_separator'] : ',',
            'decimals' => isset($options['price_decimals']) ? intval($options['price_decimals']) : 0,
        );
        
        return $data;
    }
    
    /**
     * Récupère la configuration de disponibilité pour une excursion spécifique
     * 
     * @param array $availability La configuration de disponibilité actuelle
     * @param int $product_id ID du produit/excursion
     * @return array La configuration de disponibilité mise à jour
     */
    public function get_excursion_availability($availability, $product_id) {
        // Sécurisation des entrées
        $product_id = absint($product_id);
        if (empty($product_id)) {
            return $availability;
        }
        
        // Récupérer les options globales
        $options = $this->get_global_options();
        
        // Récupérer le type d'excursion (privée ou groupe)
        $excursion_type = get_post_meta($product_id, '_excursion_type', true);
        $excursion_type = in_array($excursion_type, array('private', 'group')) ? $excursion_type : 'group';
        
        // Configuration par défaut
        $default_availability = array(
            'min_date' => date('Y-m-d'),
            'max_date' => date('Y-m-d', strtotime('+180 days')),
            'available_days' => array('monday', 'tuesday', 'wednesday', 'thursday', 'friday'),
            'excluded_dates' => array(),
            'start_time' => '08:00',
            'end_time' => '18:00',
            'lead_time_hours' => 24
        );
        
        // Fusionner avec les options globales
        if (!empty($options)) {
            // Fenêtre de réservation (max_date)
            if (isset($options['booking_window_days']) && intval($options['booking_window_days']) > 0) {
                $default_availability['max_date'] = date('Y-m-d', strtotime('+' . intval($options['booking_window_days']) . ' days'));
            }
            
            // Jours disponibles de la semaine
            if (isset($options['default_availability']) && is_array($options['default_availability'])) {
                $default_availability['available_days'] = $options['default_availability'];
            }
            
            // Dates exclues globales
            if (isset($options['excluded_dates']) && is_array($options['excluded_dates'])) {
                $default_availability['excluded_dates'] = $options['excluded_dates'];
            }
            
            // Heures de début et fin
            if (isset($options['daily_start_time'])) {
                $default_availability['start_time'] = $options['daily_start_time'];
            }
            
            if (isset($options['daily_end_time'])) {
                $default_availability['end_time'] = $options['daily_end_time'];
            }
            
            // Délai minimum de réservation selon le type d'excursion
            if ($excursion_type === 'private' && isset($options['private_booking_lead_time'])) {
                $lead_time = intval($options['private_booking_lead_time']);
                $default_availability['lead_time_hours'] = $lead_time;
                $default_availability['min_date'] = date('Y-m-d', strtotime('+' . ceil($lead_time/24) . ' days'));
            } elseif ($excursion_type === 'group' && isset($options['group_booking_lead_time'])) {
                $lead_time = intval($options['group_booking_lead_time']);
                $default_availability['lead_time_hours'] = $lead_time;
                $default_availability['min_date'] = date('Y-m-d', strtotime('+' . ceil($lead_time/24) . ' days'));
            }
        }
        
        // Récupérer les paramètres spécifiques à l'excursion qui peuvent remplacer les options globales
        $product_lead_time = get_post_meta($product_id, '_booking_lead_time', true);
        if (!empty($product_lead_time)) {
            $lead_time = intval($product_lead_time);
            $default_availability['lead_time_hours'] = $lead_time;
            $default_availability['min_date'] = date('Y-m-d', strtotime('+' . ceil($lead_time/24) . ' days'));
        }
        
        $product_window = get_post_meta($product_id, '_booking_window_days', true);
        if (!empty($product_window)) {
            $default_availability['max_date'] = date('Y-m-d', strtotime('+' . intval($product_window) . ' days'));
        }
        
        $product_available_days = get_post_meta($product_id, '_available_days', true);
        if (!empty($product_available_days) && is_array($product_available_days)) {
            $default_availability['available_days'] = $product_available_days;
        }
        
        $product_excluded_dates = get_post_meta($product_id, '_excluded_dates', true);
        if (!empty($product_excluded_dates) && is_array($product_excluded_dates)) {
            // Combiner les dates exclues globales et spécifiques
            $default_availability['excluded_dates'] = array_unique(
                array_merge($default_availability['excluded_dates'], $product_excluded_dates)
            );
        }
        
        // Fusionner avec la configuration fournie
        return wp_parse_args($availability, $default_availability);
    }
    
    /**
     * Vérifie si une date spécifique est disponible pour la réservation
     * 
     * @param bool $available Disponibilité actuelle
     * @param string $date Date à vérifier (format Y-m-d)
     * @param int $product_id ID du produit/excursion
     * @return bool Disponibilité mise à jour
     */
    public function check_date_available($available, $date, $product_id) {
        // Sécurisation des entrées
        $product_id = absint($product_id);
        if (empty($product_id) || empty($date)) {
            return false;
        }
        
        // Valider le format de date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        
        // Récupérer la configuration de disponibilité
        $availability = apply_filters('life_travel_excursion_get_availability', array(), $product_id);
        
        // Vérifier si la date est dans la fenêtre de réservation
        $check_date = new DateTime($date);
        $min_date = new DateTime($availability['min_date']);
        $max_date = new DateTime($availability['max_date']);
        
        if ($check_date < $min_date || $check_date > $max_date) {
            return false;
        }
        
        // Vérifier si la date est dans les jours de semaine disponibles
        $day_of_week = strtolower($check_date->format('l')); // monday, tuesday, etc.
        if (!in_array($day_of_week, $availability['available_days'])) {
            return false;
        }
        
        // Vérifier si la date est dans les dates exclues
        if (in_array($date, $availability['excluded_dates'])) {
            return false;
        }
        
        // Vérifier la disponibilité spécifique pour cette date (stock)
        if (!$this->check_excursion_capacity($product_id, $date)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Vérifie si l'excursion a encore de la capacité pour la date donnée
     * 
     * @param int $product_id ID du produit/excursion
     * @param string $date Date à vérifier (format Y-m-d)
     * @return bool True si des places sont disponibles
     */
    private function check_excursion_capacity($product_id, $date) {
        // Récupérer la capacité maximale
        $max_capacity = get_post_meta($product_id, '_participant_limit', true);
        if (empty($max_capacity)) {
            return true; // Pas de limite = toujours disponible
        }
        
        // Récupérer les réservations existantes pour cette date
        $bookings = $this->get_bookings_for_date($product_id, $date);
        $total_participants = 0;
        
        foreach ($bookings as $booking) {
            $total_participants += intval($booking['participants']);
        }
        
        return ($total_participants < intval($max_capacity));
    }
    
    /**
     * Récupère les réservations existantes pour une excursion à une date donnée
     * 
     * @param int $product_id ID du produit/excursion
     * @param string $date Date (format Y-m-d)
     * @return array Liste des réservations
     */
    private function get_bookings_for_date($product_id, $date) {
        global $wpdb;
        
        // Sécurisation des entrées
        $product_id = absint($product_id);
        $date = sanitize_text_field($date);
        
        $bookings = array();
        
        // Récupérer les commandes avec ce produit pour cette date
        $query = $wpdb->prepare(
            "SELECT p.ID as order_id, pm.meta_value as booking_data
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_life_travel_booking_data'
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id 
                AND oim.meta_key = '_product_id' AND oim.meta_value = %d
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-on-hold')",
            $product_id
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if (!empty($results)) {
            foreach ($results as $result) {
                $booking_data = maybe_unserialize($result['booking_data']);
                
                if (is_array($booking_data) && isset($booking_data['start_date'])) {
                    // Pour les excursions d'un jour
                    if ($booking_data['start_date'] === $date) {
                        $bookings[] = array(
                            'order_id' => $result['order_id'],
                            'participants' => isset($booking_data['participants']) ? intval($booking_data['participants']) : 1
                        );
                    }
                    // Pour les excursions de plusieurs jours
                    elseif (isset($booking_data['end_date']) && $booking_data['start_date'] <= $date && $booking_data['end_date'] >= $date) {
                        $bookings[] = array(
                            'order_id' => $result['order_id'],
                            'participants' => isset($booking_data['participants']) ? intval($booking_data['participants']) : 1
                        );
                    }
                }
            }
        }
        
        return $bookings;
    }
    
    /**
     * Récupère la fenêtre de réservation pour une excursion
     * 
     * @param array $window La fenêtre de réservation actuelle
     * @param int $product_id ID du produit/excursion
     * @return array La fenêtre de réservation mise à jour
     */
    public function get_booking_window($window, $product_id) {
        // Récupérer la configuration de disponibilité
        $availability = apply_filters('life_travel_excursion_get_availability', array(), $product_id);
        
        return array(
            'min_date' => $availability['min_date'],
            'max_date' => $availability['max_date'],
            'lead_time_hours' => $availability['lead_time_hours'],
        );
    }
    
    /**
     * Valide une demande de réservation en fonction des règles de disponibilité
     * 
     * @param array $validation Résultat de validation actuel
     * @param array $booking_data Données de réservation
     * @param int $product_id ID du produit/excursion
     * @return array Résultat de validation mis à jour
     */
    public function validate_booking_request($validation, $booking_data, $product_id) {
        // Initialiser le résultat de validation
        if (!is_array($validation)) {
            $validation = array(
                'valid' => true,
                'errors' => array()
            );
        }
        
        // Sécurisation des entrées
        $product_id = absint($product_id);
        if (empty($product_id)) {
            $validation['valid'] = false;
            $validation['errors'][] = __('ID d\'excursion invalide.', 'life-travel-excursion');
            return $validation;
        }
        
        // Vérifier que les données de réservation sont complètes
        if (!isset($booking_data['start_date']) || empty($booking_data['start_date'])) {
            $validation['valid'] = false;
            $validation['errors'][] = __('Date de début manquante.', 'life-travel-excursion');
            return $validation;
        }
        
        // Vérifier le nombre de participants
        if (!isset($booking_data['participants']) || intval($booking_data['participants']) < 1) {
            $validation['valid'] = false;
            $validation['errors'][] = __('Nombre de participants invalide.', 'life-travel-excursion');
            return $validation;
        }
        
        // Récupérer le type d'excursion (privée ou groupe)
        $excursion_type = get_post_meta($product_id, '_excursion_type', true);
        $excursion_type = in_array($excursion_type, array('private', 'group')) ? $excursion_type : 'group';
        
        // Récupérer les options globales
        $options = $this->get_global_options();
        
        // Vérifier les limites de participants selon le type d'excursion
        $participants = intval($booking_data['participants']);
        
        if ($excursion_type === 'private') {
            $min_participants = isset($options['private_min_participants']) ? intval($options['private_min_participants']) : 1;
            $max_participants = isset($options['private_max_participants']) ? intval($options['private_max_participants']) : 15;
            
            // Remplacer par les valeurs spécifiques à l'excursion si définies
            $product_min = get_post_meta($product_id, '_min_participants', true);
            $product_max = get_post_meta($product_id, '_max_participants', true);
            
            if (!empty($product_min)) {
                $min_participants = intval($product_min);
            }
            
            if (!empty($product_max)) {
                $max_participants = intval($product_max);
            }
            
            if ($participants < $min_participants) {
                $validation['valid'] = false;
                $validation['errors'][] = sprintf(
                    __('Cette excursion privée nécessite au moins %d participants.', 'life-travel-excursion'),
                    $min_participants
                );
            }
            
            if ($participants > $max_participants) {
                $validation['valid'] = false;
                $validation['errors'][] = sprintf(
                    __('Cette excursion privée est limitée à %d participants maximum.', 'life-travel-excursion'),
                    $max_participants
                );
            }
        } else { // excursion de groupe
            $min_participants = isset($options['group_min_participants']) ? intval($options['group_min_participants']) : 2;
            $max_participants = isset($options['group_max_participants']) ? intval($options['group_max_participants']) : 30;
            
            // Remplacer par les valeurs spécifiques à l'excursion si définies
            $product_min = get_post_meta($product_id, '_min_participants', true);
            $product_max = get_post_meta($product_id, '_max_participants', true);
            
            if (!empty($product_min)) {
                $min_participants = intval($product_min);
            }
            
            if (!empty($product_max)) {
                $max_participants = intval($product_max);
            }
            
            if ($participants < $min_participants) {
                $validation['valid'] = false;
                $validation['errors'][] = sprintf(
                    __('Cette excursion de groupe nécessite au moins %d participants.', 'life-travel-excursion'),
                    $min_participants
                );
            }
            
            if ($participants > $max_participants) {
                $validation['valid'] = false;
                $validation['errors'][] = sprintf(
                    __('Cette excursion de groupe est limitée à %d participants maximum.', 'life-travel-excursion'),
                    $max_participants
                );
            }
            
            // Vérifier la capacité restante pour cette date
            if (!$this->check_excursion_capacity_for_participants($product_id, $booking_data['start_date'], $participants)) {
                $validation['valid'] = false;
                $validation['errors'][] = __('Il n\'y a pas assez de places disponibles pour cette date.', 'life-travel-excursion');
            }
        }
        
        // Vérifier que la date est disponible
        if (!apply_filters('life_travel_excursion_check_date_available', true, $booking_data['start_date'], $product_id)) {
            $validation['valid'] = false;
            $validation['errors'][] = __('Cette date n\'est pas disponible pour réservation.', 'life-travel-excursion');
        }
        
        // Pour les excursions de plusieurs jours, vérifier aussi la date de fin
        if (isset($booking_data['end_date']) && !empty($booking_data['end_date'])) {
            // Vérifier que la date de fin est après la date de début
            if ($booking_data['end_date'] < $booking_data['start_date']) {
                $validation['valid'] = false;
                $validation['errors'][] = __('La date de fin doit être après la date de début.', 'life-travel-excursion');
            }
            
            // Vérifier que la date de fin est disponible
            if (!apply_filters('life_travel_excursion_check_date_available', true, $booking_data['end_date'], $product_id)) {
                $validation['valid'] = false;
                $validation['errors'][] = __('La date de fin n\'est pas disponible pour réservation.', 'life-travel-excursion');
            }
        }
        
        return $validation;
    }
    
    /**
     * Vérifie si l'excursion a encore assez de capacité pour accueillir le nombre de participants demandé
     * 
     * @param int $product_id ID du produit/excursion
     * @param string $date Date à vérifier (format Y-m-d)
     * @param int $requested_participants Nombre de participants demandés
     * @return bool True si assez de places sont disponibles
     */
    private function check_excursion_capacity_for_participants($product_id, $date, $requested_participants) {
        // Récupérer la capacité maximale
        $max_capacity = get_post_meta($product_id, '_participant_limit', true);
        if (empty($max_capacity)) {
            return true; // Pas de limite = toujours disponible
        }
        
        // Récupérer les réservations existantes pour cette date
        $bookings = $this->get_bookings_for_date($product_id, $date);
        $total_participants = 0;
        
        foreach ($bookings as $booking) {
            $total_participants += intval($booking['participants']);
        }
        
        return (($total_participants + $requested_participants) <= intval($max_capacity));
    }
}

// Initialiser l'instance
Life_Travel_Availability_Manager::get_instance();
