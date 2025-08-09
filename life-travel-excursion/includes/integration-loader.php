<?php
/**
 * Intégration des composants backend et frontend pour Life Travel Excursion
 * 
 * Ce fichier assure que tous les paramètres d'administration sont correctement
 * répercutés dans le frontend avec la même robustesse que sync_abandoned_cart.
 * 
 * @package Life_Travel_Excursion
 * @since 2.0.0
 */

// Sortie directe interdite
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de chargement des intégrations
 */
class Life_Travel_Integration_Loader {
    
    /**
     * Instance unique (Singleton)
     * @var Life_Travel_Integration_Loader
     */
    private static $instance = null;
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        // Charger les composants nécessaires
        $this->load_components();
        
        // Ajouter les filtres et actions pour l'intégration
        add_filter('life_travel_frontend_localize_script', array($this, 'add_admin_settings_to_frontend'));
    }
    
    /**
     * Obtenir l'instance unique
     * @return Life_Travel_Integration_Loader
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Charger les composants nécessaires
     */
    private function load_components() {
        // Charger les composants frontend
        require_once LIFE_TRAVEL_PLUGIN_DIR . 'includes/frontend/frontend-init.php';
        require_once LIFE_TRAVEL_PLUGIN_DIR . 'includes/frontend/availability-manager.php';
        require_once LIFE_TRAVEL_PLUGIN_DIR . 'includes/frontend/price-service.php';
        
        // Charger les composants AJAX
        require_once LIFE_TRAVEL_PLUGIN_DIR . 'includes/ajax/availability-ajax.php';
    }
    
    /**
     * Ajouter les paramètres d'administration aux données frontend
     * 
     * @param array $data Les données existantes
     * @return array Les données complétées
     */
    public function add_admin_settings_to_frontend($data) {
        // Récupérer les options globales
        $options = get_option('life_travel_excursion_options', array());
        
        // Ajouter les limites pour les extras et activités
        $data['limits'] = array(
            'max_extras' => isset($options['max_extras']) ? intval($options['max_extras']) : 5,
            'max_activities' => isset($options['max_activities']) ? intval($options['max_activities']) : 3,
            'extras_required' => isset($options['extras_required']) && $options['extras_required'] === 'yes',
            'activities_required' => isset($options['activities_required']) && $options['activities_required'] === 'yes',
        );
        
        // Ajouter les textes localisés
        $data['strings'] = array_merge($data['strings'] ?? array(), array(
            'max_extras_message' => sprintf(
                __('Vous avez atteint le nombre maximum d\'extras autorisés (%d).', 'life-travel-excursion'),
                $data['limits']['max_extras']
            ),
            'max_activities_message' => sprintf(
                __('Vous avez atteint le nombre maximum d\'activités autorisées (%d).', 'life-travel-excursion'),
                $data['limits']['max_activities']
            ),
            'calculating' => __('Calcul en cours...', 'life-travel-excursion'),
            'available' => __('Disponible', 'life-travel-excursion'),
            'limited_availability' => __('Places limitées', 'life-travel-excursion'),
            'unavailable' => __('Non disponible', 'life-travel-excursion'),
        ));
        
        return $data;
    }
    
    /**
     * Modifier les champs du formulaire de réservation pour appliquer les paramètres d'administration
     * 
     * @param array $fields Les champs actuels
     * @param int $product_id ID du produit
     * @return array Les champs modifiés
     */
    public function apply_admin_settings_to_booking_form($fields, $product_id) {
        // Récupérer les options globales
        $options = get_option('life_travel_excursion_options', array());
        
        // Récupérer le type d'excursion
        $excursion_type = get_post_meta($product_id, '_excursion_type', true) ?: 'group';
        
        // Appliquer les paramètres aux champs appropriés
        
        // 1. Champs de participants (min/max)
        if (isset($fields['participants'])) {
            if ($excursion_type === 'private') {
                $min_participants = isset($options['private_min_participants']) ? intval($options['private_min_participants']) : 1;
                $max_participants = isset($options['private_max_participants']) ? intval($options['private_max_participants']) : 15;
            } else {
                $min_participants = isset($options['group_min_participants']) ? intval($options['group_min_participants']) : 2;
                $max_participants = isset($options['group_max_participants']) ? intval($options['group_max_participants']) : 30;
            }
            
            // Remplacer par les valeurs spécifiques au produit si définies
            $product_min = get_post_meta($product_id, '_min_participants', true);
            $product_max = get_post_meta($product_id, '_max_participants', true);
            
            if (!empty($product_min)) {
                $min_participants = intval($product_min);
            }
            
            if (!empty($product_max)) {
                $max_participants = intval($product_max);
            }
            
            $fields['participants']['min'] = $min_participants;
            $fields['participants']['max'] = $max_participants;
            $fields['participants']['default'] = $min_participants;
        }
        
        // 2. Champs de date (disponibilité, délai minimum)
        if (isset($fields['date'])) {
            // Configuration de disponibilité
            $availability = apply_filters('life_travel_excursion_get_availability', array(), $product_id);
            
            $fields['date']['min_date'] = $availability['min_date'] ?? '';
            $fields['date']['max_date'] = $availability['max_date'] ?? '';
            $fields['date']['available_days'] = $availability['available_days'] ?? array();
            $fields['date']['excluded_dates'] = $availability['excluded_dates'] ?? array();
        }
        
        // 3. Champs d'extras (obligatoire, max)
        if (isset($fields['extras']) && isset($options['enable_extras']) && $options['enable_extras'] === 'yes') {
            $fields['extras']['required'] = isset($options['extras_required']) && $options['extras_required'] === 'yes';
            $fields['extras']['max_selection'] = isset($options['max_extras']) ? intval($options['max_extras']) : 5;
        } else if (isset($fields['extras'])) {
            // Si les extras sont désactivés globalement, les masquer
            $fields['extras']['visible'] = false;
        }
        
        // 4. Champs d'activités (obligatoire, max)
        if (isset($fields['activities']) && isset($options['enable_activities']) && $options['enable_activities'] === 'yes') {
            $fields['activities']['required'] = isset($options['activities_required']) && $options['activities_required'] === 'yes';
            $fields['activities']['max_selection'] = isset($options['max_activities']) ? intval($options['max_activities']) : 3;
        } else if (isset($fields['activities'])) {
            // Si les activités sont désactivées globalement, les masquer
            $fields['activities']['visible'] = false;
        }
        
        return $fields;
    }
}

// Initialiser l'instance
Life_Travel_Integration_Loader::get_instance();
