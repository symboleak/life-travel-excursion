<?php
/**
 * Analyseur de sécurité pour les paniers abandonnés
 * 
 * Détecte les problèmes de sécurité potentiels, les patterns suspects
 * et produit des alertes basées sur l'analyse des journaux de sécurité.
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

defined('ABSPATH') || exit;

/**
 * Classe pour l'analyse de sécurité des paniers abandonnés
 */
class Life_Travel_Security_Analyzer {
    
    /**
     * Instance unique (singleton)
     * @var Life_Travel_Security_Analyzer
     */
    private static $instance = null;
    
    /**
     * Référence au logger de sécurité
     * @var Life_Travel_Security_Logger
     */
    private $logger;
    
    /**
     * Seuils de détection par défaut
     * @var array
     */
    private $thresholds = array(
        'suspicious_ip_attempts' => 5,     // Nombre de tentatives suspectes avant alerte
        'token_validation_failures' => 3,  // Nombre d'échecs de validation de token avant alerte
        'rapid_cart_creation' => 10,       // Nombre de paniers créés rapidement avant alerte
        'suspicious_recovery_attempts' => 3 // Tentatives de récupération suspectes
    );
    
    /**
     * Constructeur privé (pattern singleton)
     */
    private function __construct() {
        // Initialiser le logger
        $this->logger = Life_Travel_Security_Logger::get_instance();
        
        // Charger les seuils personnalisés depuis les options
        $custom_thresholds = get_option('life_travel_security_thresholds', array());
        if (is_array($custom_thresholds) && !empty($custom_thresholds)) {
            $this->thresholds = array_merge($this->thresholds, $custom_thresholds);
        }
        
        // Hooks pour l'analyse de sécurité
        add_action('life_travel_recovery_token_validation', array($this, 'analyze_token_validation'), 10, 3);
        add_action('life_travel_cart_abandoned', array($this, 'analyze_cart_creation'), 10, 2);
        add_action('admin_init', array($this, 'schedule_security_analysis'));
    }
    
    /**
     * Obtenir l'instance unique (pattern singleton)
     * 
     * @return Life_Travel_Security_Analyzer Instance unique
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Planifie l'analyse de sécurité périodique
     */
    public function schedule_security_analysis() {
        if (!wp_next_scheduled('life_travel_security_analysis')) {
            wp_schedule_event(time(), 'hourly', 'life_travel_security_analysis');
        }
        
        add_action('life_travel_security_analysis', array($this, 'perform_periodic_analysis'));
    }
    
    /**
     * Effectue l'analyse de sécurité périodique
     */
    public function perform_periodic_analysis() {
        $this->detect_suspicious_ips();
        $this->detect_brute_force_attempts();
        $this->detect_cart_manipulation();
        $this->detect_unusual_recovery_patterns();
    }
    
    /**
     * Analyse une validation de token et détecte les tentatives frauduleuses
     * 
     * @param string $token Token de récupération
     * @param bool $is_valid Si le token est valide
     * @param int $cart_id ID du panier (si disponible)
     */
    public function analyze_token_validation($token, $is_valid, $cart_id) {
        if (!$is_valid) {
            // Vérifier si nous avons dépassé le seuil d'échecs de validation
            $recent_failures = $this->logger->count_recent_failed_validations(30); // 30 minutes
            
            if ($recent_failures >= $this->thresholds['token_validation_failures']) {
                // Détecter une potentielle attaque par force brute
                $ip_address = $this->logger->get_client_ip();
                $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
                
                $alert_data = array(
                    'ip_address' => $ip_address,
                    'user_agent' => $user_agent,
                    'failure_count' => $recent_failures,
                    'time_period' => '30 minutes',
                    'threshold' => $this->thresholds['token_validation_failures']
                );
                
                // Loguer l'événement de sécurité
                $this->logger->log_event(
                    'token_brute_force_attempt',
                    $alert_data,
                    'critical',
                    $cart_id
                );
                
                // Déclencher une action pour d'autres mesures de sécurité
                do_action('life_travel_security_token_brute_force', $ip_address, $recent_failures, $alert_data);
            }
        }
    }
    
    /**
     * Analyse la création de paniers pour détecter des patterns suspects
     * 
     * @param int $cart_id ID du panier
     * @param array $cart_data Données du panier
     */
    public function analyze_cart_creation($cart_id, $cart_data) {
        $ip_address = $this->logger->get_client_ip();
        
        // Vérifier la création rapide de paniers de la même IP
        global $wpdb;
        $log_table = $wpdb->prefix . 'life_travel_security_log';
        
        $recent_carts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$log_table} 
             WHERE event_type = 'cart_abandoned' 
             AND ip_address = %s 
             AND event_time >= %s",
            $ip_address,
            date('Y-m-d H:i:s', strtotime('-1 hour'))
        ));
        
        if ($recent_carts >= $this->thresholds['rapid_cart_creation']) {
            // Possible comportement de bot ou de script
            $alert_data = array(
                'ip_address' => $ip_address,
                'cart_count' => $recent_carts,
                'time_period' => '1 hour',
                'threshold' => $this->thresholds['rapid_cart_creation']
            );
            
            // Loguer l'événement suspect
            $this->logger->log_event(
                'rapid_cart_creation',
                $alert_data,
                'warning',
                $cart_id
            );
            
            // Déclencher une action pour d'autres mesures
            do_action('life_travel_security_rapid_cart_creation', $ip_address, $recent_carts, $alert_data);
        }
    }
    
    /**
     * Détecte les IPs suspectes basées sur le comportement
     */
    public function detect_suspicious_ips() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'life_travel_security_log';
        
        // Trouver les IPs avec plusieurs événements d'erreur ou critiques récents
        $suspicious_ips = $wpdb->get_results($wpdb->prepare(
            "SELECT ip_address, COUNT(*) as event_count 
             FROM {$log_table} 
             WHERE severity >= %d 
             AND event_time >= %s 
             GROUP BY ip_address 
             HAVING COUNT(*) >= %d",
            3, // Niveau de gravité 'error' ou supérieur
            date('Y-m-d H:i:s', strtotime('-24 hours')),
            $this->thresholds['suspicious_ip_attempts']
        ));
        
        foreach ($suspicious_ips as $ip_data) {
            $alert_data = array(
                'ip_address' => $ip_data->ip_address,
                'event_count' => $ip_data->event_count,
                'time_period' => '24 hours',
                'threshold' => $this->thresholds['suspicious_ip_attempts']
            );
            
            // Loguer l'IP suspecte
            $this->logger->log_event(
                'suspicious_ip_activity',
                $alert_data,
                'warning'
            );
            
            // Déclencher une action pour d'autres mesures
            do_action('life_travel_security_suspicious_ip', $ip_data->ip_address, $ip_data->event_count, $alert_data);
        }
    }
    
    /**
     * Détecte les tentatives de force brute sur les tokens
     */
    public function detect_brute_force_attempts() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'life_travel_security_log';
        
        // Rechercher des tentatives répétées d'échec de validation de token
        $potential_attacks = $wpdb->get_results($wpdb->prepare(
            "SELECT ip_address, COUNT(*) as failure_count 
             FROM {$log_table} 
             WHERE event_type = 'token_validation' 
             AND event_data LIKE %s
             AND event_time >= %s 
             GROUP BY ip_address 
             HAVING COUNT(*) >= %d",
            '%"is_valid":false%',
            date('Y-m-d H:i:s', strtotime('-1 hour')),
            $this->thresholds['token_validation_failures']
        ));
        
        foreach ($potential_attacks as $attack_data) {
            $alert_data = array(
                'ip_address' => $attack_data->ip_address,
                'failure_count' => $attack_data->failure_count,
                'time_period' => '1 hour',
                'threshold' => $this->thresholds['token_validation_failures']
            );
            
            // Loguer la tentative d'attaque
            $this->logger->log_event(
                'brute_force_detected',
                $alert_data,
                'critical'
            );
            
            // Bloquer temporairement l'IP ou prendre d'autres mesures
            do_action('life_travel_security_brute_force_detected', $attack_data->ip_address, $attack_data->failure_count, $alert_data);
        }
    }
    
    /**
     * Détecte la manipulation suspecte de paniers
     */
    public function detect_cart_manipulation() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'life_travel_security_log';
        $cart_table = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Rechercher des paniers avec des modifications suspectes
        $suspicious_carts = $wpdb->get_results(
            "SELECT c.id, c.email, c.cart_total, 
                    COUNT(l.id) as event_count 
             FROM {$cart_table} c 
             JOIN {$log_table} l ON c.id = l.cart_id 
             WHERE l.event_type IN ('cart_updated', 'sync_abandoned_cart') 
             AND l.event_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
             GROUP BY c.id 
             HAVING COUNT(l.id) > 5"
        );
        
        foreach ($suspicious_carts as $cart) {
            $alert_data = array(
                'cart_id' => $cart->id,
                'email' => $cart->email,
                'cart_total' => $cart->cart_total,
                'event_count' => $cart->event_count,
                'time_period' => '24 hours'
            );
            
            // Loguer l'activité suspecte
            $this->logger->log_event(
                'suspicious_cart_manipulation',
                $alert_data,
                'warning',
                $cart->id
            );
            
            // Déclencher une action pour d'autres mesures
            do_action('life_travel_security_cart_manipulation', $cart->id, $cart->event_count, $alert_data);
        }
    }
    
    /**
     * Détecte les patterns inhabituels de récupération
     */
    public function detect_unusual_recovery_patterns() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'life_travel_security_log';
        
        // Rechercher des tentatives de récupération multiples pour un même panier
        $unusual_recoveries = $wpdb->get_results($wpdb->prepare(
            "SELECT cart_id, ip_address, COUNT(*) as attempt_count 
             FROM {$log_table} 
             WHERE event_type = 'cart_recovery_attempt' 
             AND event_time >= %s 
             GROUP BY cart_id, ip_address 
             HAVING COUNT(*) >= %d",
            date('Y-m-d H:i:s', strtotime('-24 hours')),
            $this->thresholds['suspicious_recovery_attempts']
        ));
        
        foreach ($unusual_recoveries as $recovery) {
            $alert_data = array(
                'cart_id' => $recovery->cart_id,
                'ip_address' => $recovery->ip_address,
                'attempt_count' => $recovery->attempt_count,
                'time_period' => '24 hours',
                'threshold' => $this->thresholds['suspicious_recovery_attempts']
            );
            
            // Loguer l'activité suspecte
            $this->logger->log_event(
                'unusual_recovery_pattern',
                $alert_data,
                'warning',
                $recovery->cart_id
            );
            
            // Déclencher une action pour d'autres mesures
            do_action('life_travel_security_unusual_recovery', $recovery->cart_id, $recovery->attempt_count, $alert_data);
        }
    }
    
    /**
     * Obtient des recommandations de sécurité basées sur l'analyse
     * 
     * @return array Recommandations de sécurité
     */
    public function get_security_recommendations() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'life_travel_security_log';
        
        $recommendations = array();
        
        // Vérifier les tentatives de force brute récentes
        $brute_force_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_address) 
             FROM {$log_table} 
             WHERE (event_type = 'token_brute_force_attempt' OR event_type = 'brute_force_detected')
             AND event_time >= %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        
        if ($brute_force_count > 0) {
            $recommendations[] = array(
                'type' => 'critical',
                'title' => sprintf(_n('%d adresse IP a tenté des attaques par force brute', '%d adresses IP ont tenté des attaques par force brute', $brute_force_count, 'life-travel-excursion'), $brute_force_count),
                'description' => __('Envisagez d\'implémenter un système de limitation de taux (rate limiting) ou une protection CAPTCHA pour les tentatives de récupération de panier.', 'life-travel-excursion'),
                'action' => 'implement_rate_limiting'
            );
        }
        
        // Vérifier l'activité suspecte des IPs
        $suspicious_ip_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_address) 
             FROM {$log_table} 
             WHERE event_type = 'suspicious_ip_activity'
             AND event_time >= %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        
        if ($suspicious_ip_count > 0) {
            $recommendations[] = array(
                'type' => 'warning',
                'title' => sprintf(_n('%d adresse IP suspecte détectée', '%d adresses IP suspectes détectées', $suspicious_ip_count, 'life-travel-excursion'), $suspicious_ip_count),
                'description' => __('Examinez les journaux de sécurité pour ces adresses IP et envisagez de les bloquer temporairement si nécessaire.', 'life-travel-excursion'),
                'action' => 'review_suspicious_ips'
            );
        }
        
        // Vérifier la manipulation de paniers
        $manipulated_carts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT cart_id) 
             FROM {$log_table} 
             WHERE event_type = 'suspicious_cart_manipulation'
             AND event_time >= %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        
        if ($manipulated_carts > 0) {
            $recommendations[] = array(
                'type' => 'warning',
                'title' => sprintf(_n('%d panier avec activité suspecte', '%d paniers avec activité suspecte', $manipulated_carts, 'life-travel-excursion'), $manipulated_carts),
                'description' => __('Des modifications fréquentes de paniers ont été détectées. Vérifiez si ces modifications sont légitimes ou si elles indiquent une tentative d\'exploitation.', 'life-travel-excursion'),
                'action' => 'review_cart_manipulation'
            );
        }
        
        // Recommandation générale si aucun problème spécifique n'est trouvé
        if (empty($recommendations)) {
            $recommendations[] = array(
                'type' => 'info',
                'title' => __('Aucun problème de sécurité majeur détecté', 'life-travel-excursion'),
                'description' => __('Continuez à surveiller régulièrement les journaux de sécurité et envisagez des revues périodiques de sécurité.', 'life-travel-excursion'),
                'action' => 'continue_monitoring'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Génère un score de risque de sécurité basé sur l'activité récente
     * 
     * @return array Score de risque et détails
     */
    public function calculate_security_risk_score() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'life_travel_security_log';
        
        // Période d'analyse - 7 derniers jours
        $date_threshold = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        // Compter les événements par niveau de gravité
        $severity_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT severity, COUNT(*) as count 
             FROM {$log_table} 
             WHERE event_time >= %s 
             GROUP BY severity",
            $date_threshold
        ));
        
        // Calculer le score de risque pondéré
        $risk_score = 0;
        $max_score = 100;
        $total_events = 0;
        $severity_details = array();
        
        // Poids par niveau de gravité
        $severity_weights = array(
            0 => 0,    // info: pas d'impact sur le score
            1 => 0.5,  // notice: impact minimal
            2 => 2,    // warning: impact modéré
            3 => 5,    // error: impact important
            4 => 10    // critical: impact maximum
        );
        
        foreach ($severity_counts as $count) {
            $severity = intval($count->severity);
            $count_val = intval($count->count);
            $total_events += $count_val;
            
            if (isset($severity_weights[$severity])) {
                $weight = $severity_weights[$severity];
                $severity_score = $count_val * $weight;
                $risk_score += $severity_score;
                
                // Convertir la sévérité numérique en texte
                $severity_text = 'info';
                foreach ($this->logger->severity_levels as $level_text => $level_val) {
                    if ($level_val === $severity) {
                        $severity_text = $level_text;
                        break;
                    }
                }
                
                $severity_details[$severity_text] = array(
                    'count' => $count_val,
                    'score_contribution' => $severity_score
                );
            }
        }
        
        // Normaliser le score entre 0 et 100, avec un maximum atteignable à environ 50 événements critiques
        $normalizer = 500; // Valeur à ajuster selon les besoins
        $normalized_score = min($max_score, ($risk_score / $normalizer) * $max_score);
        $risk_score = round($normalized_score);
        
        // Déterminer le niveau de risque
        $risk_level = 'low';
        if ($risk_score >= 75) {
            $risk_level = 'critical';
        } elseif ($risk_score >= 50) {
            $risk_level = 'high';
        } elseif ($risk_score >= 25) {
            $risk_level = 'medium';
        }
        
        return array(
            'score' => $risk_score,
            'level' => $risk_level,
            'total_events' => $total_events,
            'details' => $severity_details,
            'period' => '7 days'
        );
    }
    
    /**
     * Obtient les statistiques des paniers abandonnés pour l'analyse de sécurité
     * 
     * @return array Statistiques des paniers
     */
    public function get_cart_statistics() {
        global $wpdb;
        $cart_table = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Paniers abandonnés
        $abandoned_carts = $wpdb->get_var(
            "SELECT COUNT(*) FROM $cart_table WHERE recovered = 0"
        );
        
        // Paniers récupérés
        $recovered_carts = $wpdb->get_var(
            "SELECT COUNT(*) FROM $cart_table WHERE recovered = 1"
        );
        
        // Calcul du taux de récupération
        $total_carts = $abandoned_carts + $recovered_carts;
        $recovery_rate = $total_carts > 0 ? round(($recovered_carts / $total_carts) * 100, 1) : 0;
        
        // Valeur totale des paniers abandonnés
        $total_abandoned_value = $wpdb->get_var(
            "SELECT SUM(cart_total) FROM $cart_table WHERE recovered = 0"
        );
        
        // Valeur moyenne des paniers
        $avg_cart_value = $total_carts > 0 ? 
            $wpdb->get_var("SELECT AVG(cart_total) FROM $cart_table") : 0;
        
        // Récupérations récentes
        $recent_recoveries = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $cart_table WHERE recovered = 1 AND last_updated >= %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        
        return array(
            'abandoned_carts' => (int) $abandoned_carts,
            'recovered_carts' => (int) $recovered_carts,
            'recovery_rate' => $recovery_rate,
            'total_abandoned_value' => (float) $total_abandoned_value,
            'avg_cart_value' => (float) $avg_cart_value,
            'recent_recoveries' => (int) $recent_recoveries
        );
    }
}

// Initialiser l'analyseur
Life_Travel_Security_Analyzer::get_instance();
