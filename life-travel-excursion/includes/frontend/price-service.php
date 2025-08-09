<?php
/**
 * Service de calcul des prix pour Life Travel Excursion
 * 
 * Ce fichier gère le calcul des prix des excursions en tenant compte
 * des paramètres d'administration (prix saisonniers, niveaux de prix, etc.).
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
 * Classe de service pour le calcul des prix des excursions
 */
class Life_Travel_Price_Service {
    
    /**
     * Instance unique (Singleton)
     * @var Life_Travel_Price_Service
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
        add_filter('life_travel_calculate_excursion_price', array($this, 'calculate_excursion_price'), 10, 3);
        add_filter('life_travel_apply_seasonal_pricing', array($this, 'apply_seasonal_pricing'), 10, 3);
        add_filter('life_travel_get_pricing_tier', array($this, 'get_pricing_tier'), 10, 3);
        
        // Points d'entrée AJAX
        add_action('wp_ajax_life_travel_excursion_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_nopriv_life_travel_excursion_calculate_price', array($this, 'ajax_calculate_price'));
    }
    
    /**
     * Obtenir l'instance unique
     * @return Life_Travel_Price_Service
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
     * Point d'entrée AJAX pour le calcul du prix d'une excursion
     */
    public function ajax_calculate_price() {
        // Vérifier le nonce pour la sécurité
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_frontend_nonce')) {
            wp_send_json_error(array(
                'message' => __('Erreur de sécurité. Veuillez rafraîchir la page.', 'life-travel-excursion')
            ));
            return;
        }
        
        // Validation des entrées
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $participants = isset($_POST['participants']) ? absint($_POST['participants']) : 1;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $extras = isset($_POST['extras']) ? array_map('absint', $_POST['extras']) : array();
        $activities = isset($_POST['activities']) ? array_map('absint', $_POST['activities']) : array();
        
        if (empty($product_id)) {
            wp_send_json_error(array(
                'message' => __('ID de produit manquant.', 'life-travel-excursion')
            ));
            return;
        }
        
        // Validation du format de date
        if (!empty($start_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            wp_send_json_error(array(
                'message' => __('Format de date de début invalide.', 'life-travel-excursion')
            ));
            return;
        }
        
        if (!empty($end_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            wp_send_json_error(array(
                'message' => __('Format de date de fin invalide.', 'life-travel-excursion')
            ));
            return;
        }
        
        // Préparer les données pour le calcul
        $price_data = array(
            'participants' => $participants,
            'start_date' => $start_date,
            'end_date' => !empty($end_date) ? $end_date : $start_date,
            'extras' => $extras,
            'activities' => $activities
        );
        
        // Calculer le prix
        $price_result = apply_filters('life_travel_calculate_excursion_price', array(), $product_id, $price_data);
        
        // Si erreur dans le calcul
        if (isset($price_result['error'])) {
            wp_send_json_error(array(
                'message' => $price_result['error']
            ));
            return;
        }
        
        // Formater les prix pour l'affichage
        $formatted_result = $this->format_price_result($price_result);
        
        // Renvoyer le résultat
        wp_send_json_success($formatted_result);
    }
    
    /**
     * Calculer le prix d'une excursion
     * 
     * @param array $result Résultat du calcul (vide par défaut)
     * @param int $product_id ID du produit/excursion
     * @param array $data Données pour le calcul
     * @return array Résultat du calcul
     */
    public function calculate_excursion_price($result, $product_id, $data) {
        // Sécurisation des entrées
        $product_id = absint($product_id);
        
        // Valeurs par défaut
        $price_result = array(
            'base_price' => 0,
            'extras_price' => 0,
            'activities_price' => 0,
            'subtotal' => 0,
            'total' => 0,
            'per_person' => 0,
            'num_days' => 1,
            'currency' => get_woocommerce_currency(),
            'details' => array()
        );
        
        if (empty($product_id)) {
            $price_result['error'] = __('ID de produit invalide.', 'life-travel-excursion');
            return $price_result;
        }
        
        // Récupérer le produit
        $product = wc_get_product($product_id);
        if (!$product) {
            $price_result['error'] = __('Produit non trouvé.', 'life-travel-excursion');
            return $price_result;
        }
        
        // Récupérer les données du produit
        $excursion_type = get_post_meta($product_id, '_excursion_type', true) ?: 'group';
        $base_price = floatval($product->get_price());
        
        // Extraire les données envoyées
        $participants = isset($data['participants']) ? absint($data['participants']) : 1;
        $start_date = isset($data['start_date']) ? sanitize_text_field($data['start_date']) : '';
        $end_date = isset($data['end_date']) ? sanitize_text_field($data['end_date']) : $start_date;
        $extras = isset($data['extras']) ? (array)$data['extras'] : array();
        $activities = isset($data['activities']) ? (array)$data['activities'] : array();
        
        // Calculer le nombre de jours de l'excursion
        $num_days = 1;
        if (!empty($start_date) && !empty($end_date) && $start_date !== $end_date) {
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $interval = $start->diff($end);
            $num_days = $interval->days + 1; // Inclure le premier et le dernier jour
        }
        $price_result['num_days'] = $num_days;
        
        // Calculer le prix de base selon le type d'excursion
        if ($excursion_type === 'private') {
            // Pour les excursions privées, utiliser le niveau de prix approprié
            $pricing_tier = apply_filters('life_travel_get_pricing_tier', array(), $product_id, $participants);
            
            if (isset($pricing_tier['error'])) {
                $price_result['error'] = $pricing_tier['error'];
                return $price_result;
            }
            
            if (isset($pricing_tier['price'])) {
                $base_price = floatval($pricing_tier['price']);
            }
            
            // Le prix des excursions privées est généralement un prix total, pas par personne
            $price_result['base_price'] = $base_price;
            $price_result['per_person'] = $participants > 0 ? $base_price / $participants : $base_price;
        } else {
            // Pour les excursions de groupe, le prix est par personne
            $price_result['per_person'] = $base_price;
            $price_result['base_price'] = $base_price * $participants;
            
            // Appliquer la remise pour grands groupes si configurée
            $options = $this->get_global_options();
            
            if (isset($options['group_enable_discount']) && $options['group_enable_discount'] === 'yes') {
                $discount_threshold = isset($options['group_discount_threshold']) ? intval($options['group_discount_threshold']) : 10;
                $discount_rate = isset($options['group_discount_rate']) ? floatval($options['group_discount_rate']) : 10;
                
                if ($participants >= $discount_threshold && $discount_rate > 0) {
                    $discount_amount = $price_result['base_price'] * ($discount_rate / 100);
                    $price_result['base_price'] -= $discount_amount;
                    
                    $price_result['details']['group_discount'] = array(
                        'label' => sprintf(__('Remise groupe (%d%% pour %d+ participants)', 'life-travel-excursion'), $discount_rate, $discount_threshold),
                        'amount' => -$discount_amount
                    );
                }
            }
        }
        
        // Si des dates sont spécifiées, appliquer les prix saisonniers
        if (!empty($start_date)) {
            // Appliquer les prix saisonniers
            $seasonal_result = apply_filters('life_travel_apply_seasonal_pricing', array(), $price_result['base_price'], $start_date);
            
            if (isset($seasonal_result['adjusted_price']) && $seasonal_result['adjusted_price'] != $price_result['base_price']) {
                $price_result['base_price'] = $seasonal_result['adjusted_price'];
                
                // Ajouter aux détails
                if (isset($seasonal_result['details'])) {
                    $price_result['details']['seasonal'] = $seasonal_result['details'];
                }
            }
        }
        
        // Calculer le prix pour plusieurs jours (si applicable)
        if ($num_days > 1) {
            // Certaines excursions peuvent avoir un prix par jour
            $per_day = get_post_meta($product_id, '_price_per_day', true);
            
            if ($per_day === 'yes') {
                $price_result['base_price'] *= $num_days;
                
                $price_result['details']['multiple_days'] = array(
                    'label' => sprintf(__('Prix pour %d jours', 'life-travel-excursion'), $num_days),
                    'amount' => $price_result['base_price']
                );
            }
        }
        
        // Calculer le prix des extras
        if (!empty($extras)) {
            $extras_list = get_post_meta($product_id, '_extras_list', true);
            if (!empty($extras_list)) {
                $extras_array = explode("\n", $extras_list);
                $extras_price = 0;
                
                foreach ($extras as $extra_id) {
                    $extra_id = absint($extra_id);
                    if (isset($extras_array[$extra_id])) {
                        $extra_line = trim($extras_array[$extra_id]);
                        // Format attendu: "Nom de l'extra | Prix"
                        $extra_parts = explode('|', $extra_line);
                        
                        if (count($extra_parts) >= 2) {
                            $extra_name = trim($extra_parts[0]);
                            $extra_price = floatval(trim($extra_parts[1]));
                            
                            // Appliquer le prix de l'extra
                            $extras_price += $extra_price;
                            
                            // Ajouter aux détails
                            $price_result['details']['extras'][] = array(
                                'label' => $extra_name,
                                'amount' => $extra_price
                            );
                        }
                    }
                }
                
                $price_result['extras_price'] = $extras_price;
            }
        }
        
        // Calculer le prix des activités
        if (!empty($activities)) {
            $activities_list = get_post_meta($product_id, '_activities_list', true);
            if (!empty($activities_list)) {
                $activities_array = explode("\n", $activities_list);
                $activities_price = 0;
                
                foreach ($activities as $activity_id) {
                    $activity_id = absint($activity_id);
                    if (isset($activities_array[$activity_id])) {
                        $activity_line = trim($activities_array[$activity_id]);
                        // Format attendu: "Nom de l'activité | Prix"
                        $activity_parts = explode('|', $activity_line);
                        
                        if (count($activity_parts) >= 2) {
                            $activity_name = trim($activity_parts[0]);
                            $activity_price = floatval(trim($activity_parts[1]));
                            
                            // Appliquer le prix de l'activité
                            $activities_price += $activity_price;
                            
                            // Ajouter aux détails
                            $price_result['details']['activities'][] = array(
                                'label' => $activity_name,
                                'amount' => $activity_price
                            );
                        }
                    }
                }
                
                $price_result['activities_price'] = $activities_price;
            }
        }
        
        // Calculer le sous-total
        $price_result['subtotal'] = $price_result['base_price'] + $price_result['extras_price'] + $price_result['activities_price'];
        
        // Appliquer d'autres ajustements (taxes, frais, etc.)
        // ...
        
        // Calculer le total
        $price_result['total'] = $price_result['subtotal'];
        
        return $price_result;
    }
    
    /**
     * Appliquer les prix saisonniers à un prix
     * 
     * @param array $result Résultat de l'ajustement (vide par défaut)
     * @param float $base_price Prix de base
     * @param string $date Date (format Y-m-d)
     * @return array Résultat de l'ajustement
     */
    public function apply_seasonal_pricing($result, $base_price, $date) {
        // Initialiser le résultat
        $result = array(
            'original_price' => $base_price,
            'adjusted_price' => $base_price,
            'details' => null
        );
        
        // Récupérer les options
        $options = $this->get_global_options();
        
        // Vérifier si les prix saisonniers sont activés
        if (isset($options['seasonal_pricing']) && $options['seasonal_pricing'] === 'yes') {
            // Parcourir les périodes saisonnières
            if (isset($options['seasonal_prices']) && is_array($options['seasonal_prices'])) {
                foreach ($options['seasonal_prices'] as $season) {
                    // Vérifier si la date est dans cette période
                    if (isset($season['start_date']) && isset($season['end_date']) && 
                        $date >= $season['start_date'] && $date <= $season['end_date']) {
                        
                        // Récupérer le type et la valeur du modificateur
                        $type = isset($season['type']) ? $season['type'] : 'percentage';
                        $modifier = isset($season['modifier']) ? floatval($season['modifier']) : 0;
                        
                        // Appliquer la modification
                        if ($type === 'percentage') {
                            // Modification en pourcentage
                            $percent = $modifier / 100;
                            $adjustment = $base_price * $percent;
                            $result['adjusted_price'] = $base_price + $adjustment;
                            
                            $result['details'] = array(
                                'label' => sprintf(__('Ajustement saisonnier (%+d%%)', 'life-travel-excursion'), $modifier),
                                'amount' => $adjustment
                            );
                        } else {
                            // Modification en montant fixe
                            $result['adjusted_price'] = $base_price + $modifier;
                            
                            $result['details'] = array(
                                'label' => sprintf(__('Ajustement saisonnier (%+.2f)', 'life-travel-excursion'), $modifier),
                                'amount' => $modifier
                            );
                        }
                        
                        // Une fois qu'une période est trouvée, on arrête
                        break;
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Récupérer le niveau de prix pour une excursion privée
     * 
     * @param array $result Résultat (vide par défaut)
     * @param int $product_id ID du produit/excursion
     * @param int $participants Nombre de participants
     * @return array Résultat avec le prix du niveau
     */
    public function get_pricing_tier($result, $product_id, $participants) {
        // Initialiser le résultat
        $result = array('price' => 0);
        
        // Sécurisation des entrées
        $product_id = absint($product_id);
        $participants = absint($participants);
        
        if (empty($product_id) || $participants < 1) {
            $result['error'] = __('Paramètres invalides pour le calcul du niveau de prix.', 'life-travel-excursion');
            return $result;
        }
        
        // Vérifier si l'excursion utilise des niveaux de prix
        $use_tiers = get_post_meta($product_id, '_use_pricing_tiers', true);
        
        if ($use_tiers === 'yes') {
            // Récupérer les niveaux de prix spécifiques au produit
            $product_tiers = get_post_meta($product_id, '_pricing_tiers', true);
            
            if (!empty($product_tiers) && is_array($product_tiers)) {
                // Chercher le niveau de prix correspondant
                foreach ($product_tiers as $tier) {
                    if (isset($tier['min']) && isset($tier['max']) && isset($tier['price'])) {
                        $min = intval($tier['min']);
                        $max = intval($tier['max']);
                        
                        if ($participants >= $min && $participants <= $max) {
                            $result['price'] = floatval($tier['price']);
                            $result['tier'] = $tier;
                            return $result;
                        }
                    }
                }
            }
        }
        
        // Si aucun niveau spécifique n'est trouvé ou utilisé, essayer les niveaux globaux
        $options = $this->get_global_options();
        
        if (isset($options['private_enable_pricing_tiers']) && $options['private_enable_pricing_tiers'] === 'yes') {
            if (isset($options['private_pricing_tiers']) && is_array($options['private_pricing_tiers'])) {
                // Chercher le niveau de prix correspondant
                foreach ($options['private_pricing_tiers'] as $tier) {
                    if (isset($tier['min']) && isset($tier['max']) && isset($tier['price'])) {
                        $min = intval($tier['min']);
                        $max = intval($tier['max']);
                        
                        if ($participants >= $min && $participants <= $max) {
                            $result['price'] = floatval($tier['price']);
                            $result['tier'] = $tier;
                            return $result;
                        }
                    }
                }
            }
        }
        
        // Si aucun niveau n'est trouvé, utiliser le prix de base du produit
        $product = wc_get_product($product_id);
        if ($product) {
            $result['price'] = floatval($product->get_price());
        } else {
            $result['error'] = __('Produit non trouvé.', 'life-travel-excursion');
        }
        
        return $result;
    }
    
    /**
     * Formater le résultat du calcul de prix pour l'affichage
     * 
     * @param array $price_result Résultat du calcul
     * @return array Résultat formaté
     */
    private function format_price_result($price_result) {
        // Récupérer les options de formatage
        $options = $this->get_global_options();
        
        $formatted = array(
            'raw' => $price_result, // Conserver les valeurs brutes
            'formatted' => array()
        );
        
        // Formater les prix principaux
        $formatted['formatted']['base_price'] = apply_filters(
            'life_travel_get_formatted_price',
            $price_result['base_price']
        );
        
        $formatted['formatted']['extras_price'] = apply_filters(
            'life_travel_get_formatted_price',
            $price_result['extras_price']
        );
        
        $formatted['formatted']['activities_price'] = apply_filters(
            'life_travel_get_formatted_price',
            $price_result['activities_price']
        );
        
        $formatted['formatted']['subtotal'] = apply_filters(
            'life_travel_get_formatted_price',
            $price_result['subtotal']
        );
        
        $formatted['formatted']['total'] = apply_filters(
            'life_travel_get_formatted_price',
            $price_result['total']
        );
        
        $formatted['formatted']['per_person'] = apply_filters(
            'life_travel_get_formatted_price',
            $price_result['per_person']
        );
        
        // Formater les détails si présents
        if (isset($price_result['details']) && is_array($price_result['details'])) {
            foreach ($price_result['details'] as $key => $detail) {
                if (is_array($detail)) {
                    if (isset($detail['amount'])) {
                        $formatted['formatted']['details'][$key]['amount'] = apply_filters(
                            'life_travel_get_formatted_price',
                            $detail['amount']
                        );
                        
                        // Copier les autres champs
                        foreach ($detail as $field => $value) {
                            if ($field !== 'amount') {
                                $formatted['formatted']['details'][$key][$field] = $value;
                            }
                        }
                    } elseif (is_array($detail) && !isset($detail['label'])) {
                        // Cas des tableaux d'extras/activités
                        foreach ($detail as $subkey => $subdetail) {
                            if (isset($subdetail['amount'])) {
                                $formatted['formatted']['details'][$key][$subkey]['amount'] = apply_filters(
                                    'life_travel_get_formatted_price',
                                    $subdetail['amount']
                                );
                                
                                // Copier les autres champs
                                foreach ($subdetail as $field => $value) {
                                    if ($field !== 'amount') {
                                        $formatted['formatted']['details'][$key][$subkey][$field] = $value;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Ajouter d'autres informations utiles
        $formatted['num_days'] = $price_result['num_days'];
        $formatted['currency'] = $price_result['currency'];
        
        return $formatted;
    }
}

// Initialiser l'instance
Life_Travel_Price_Service::get_instance();
