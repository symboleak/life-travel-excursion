<?php
/**
 * Analyseur de paniers abandonnés
 * 
 * Fournit des fonctionnalités d'analyse et de statistiques pour les paniers abandonnés
 * avec une attention particulière à la sécurité des données et à l'intégrité.
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

defined('ABSPATH') || exit;

/**
 * Classe analyseur de paniers abandonnés
 */
class Life_Travel_Abandoned_Cart_Analyzer {
    
    /**
     * Instance unique (singleton)
     * @var Life_Travel_Abandoned_Cart_Analyzer
     */
    private static $instance = null;
    
    /**
     * Constructeur privé (pattern singleton)
     */
    private function __construct() {
        // Aucune initialisation spéciale nécessaire
    }
    
    /**
     * Obtenir l'instance unique (pattern singleton)
     * 
     * @return Life_Travel_Abandoned_Cart_Analyzer Instance unique
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Récupère les paniers abandonnés qui nécessitent un email de récupération
     * 
     * @return array Liste des paniers nécessitant un email
     */
    public function get_carts_needing_email() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return array();
        }
        
        // Récupérer les paramètres
        $settings = get_option('life_travel_abandoned_cart_settings', array());
        $wait_time = isset($settings['recovery_wait_time']) ? intval($settings['recovery_wait_time']) : 60; // Minutes
        $max_emails = isset($settings['max_recovery_emails']) ? intval($settings['max_recovery_emails']) : 3;
        $email_interval = isset($settings['email_interval']) ? intval($settings['email_interval']) : 24; // Heures
        
        // Calculer le timestamp pour le temps d'attente
        $wait_timestamp = date('Y-m-d H:i:s', strtotime("-{$wait_time} minutes"));
        
        // Calculer le timestamp pour l'intervalle d'email
        $interval_timestamp = date('Y-m-d H:i:s', strtotime("-{$email_interval} hours"));
        
        // Requête préparée pour plus de sécurité
        $carts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE recovered = 0
             AND created_at <= %s
             AND (
                 reminder_sent = 0
                 OR (
                     reminder_sent = 1
                     AND reminder_count < %d
                     AND last_reminder_sent <= %s
                 )
             )
             ORDER BY created_at DESC",
            $wait_timestamp,
            $max_emails,
            $interval_timestamp
        ));
        
        return $carts;
    }
    
    /**
     * Récupère les statistiques globales des paniers abandonnés
     * 
     * @return array Statistiques des paniers abandonnés
     */
    public function get_cart_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return $this->get_empty_statistics();
        }
        
        // Statistiques globales avec requêtes préparées pour sécurité
        $stats = array(
            'total_carts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"),
            'recovered_carts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE recovered = 1"),
            'abandoned_carts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE recovered = 0"),
            'reminded_carts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE reminder_sent = 1"),
            'avg_cart_value' => 0,
            'total_abandoned_value' => 0,
            'recent_recoveries' => 0
        );
        
        // Ajouter les statistiques de valeur
        $avg_value = $wpdb->get_var("SELECT AVG(cart_total) FROM {$table_name}");
        $stats['avg_cart_value'] = $avg_value ? round((float) $avg_value, 2) : 0;
        
        $total_value = $wpdb->get_var("SELECT SUM(cart_total) FROM {$table_name} WHERE recovered = 0");
        $stats['total_abandoned_value'] = $total_value ? round((float) $total_value, 2) : 0;
        
        // Récupérations récentes (7 derniers jours)
        $recent_period = date('Y-m-d H:i:s', strtotime('-7 days'));
        $stats['recent_recoveries'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE recovered = 1 AND last_updated > %s",
            $recent_period
        ));
        
        // Calculer le taux de récupération
        $stats['recovery_rate'] = $stats['total_carts'] > 0 
            ? round(($stats['recovered_carts'] / $stats['total_carts']) * 100, 2) 
            : 0;
        
        return $stats;
    }
    
    /**
     * Renvoie des statistiques vides pour les cas où la table n'existe pas
     * 
     * @return array Statistiques vides
     */
    private function get_empty_statistics() {
        return array(
            'total_carts' => 0,
            'recovered_carts' => 0,
            'abandoned_carts' => 0,
            'reminded_carts' => 0,
            'avg_cart_value' => 0,
            'total_abandoned_value' => 0,
            'recent_recoveries' => 0,
            'recovery_rate' => 0
        );
    }
    
    /**
     * Récupère les tendances des paniers abandonnés sur une période donnée
     * 
     * @param string $start_date Date de début (format Y-m-d)
     * @param string $end_date Date de fin (format Y-m-d)
     * @return array Données de tendance
     */
    public function get_cart_trends($start_date, $end_date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return array();
        }
        
        // Valider les dates et les formater correctement pour prévenir les injections SQL
        if (!$this->validate_date($start_date) || !$this->validate_date($end_date)) {
            return array();
        }
        
        // Requête pour obtenir les tendances avec requête préparée pour sécurité
        $trends = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(recovered) as recovered,
                SUM(reminder_sent) as reminded,
                AVG(cart_total) as avg_value
             FROM {$table_name}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
        
        return $trends;
    }
    
    /**
     * Récupère les produits les plus abandonnés
     * 
     * @param string $start_date Date de début (format Y-m-d)
     * @param string $end_date Date de fin (format Y-m-d)
     * @param int $limit Nombre maximum de produits à retourner
     * @return array Liste des produits les plus abandonnés
     */
    public function get_most_abandoned_products($start_date, $end_date, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return array();
        }
        
        // Valider les dates
        if (!$this->validate_date($start_date) || !$this->validate_date($end_date)) {
            return array();
        }
        
        // Valider la limite
        $limit = absint($limit);
        if ($limit < 1) {
            $limit = 10;
        }
        
        // Récupérer tous les paniers abandonnés dans la période
        $carts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, cart_contents FROM {$table_name}
             WHERE created_at BETWEEN %s AND %s
             AND recovered = 0",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
        
        if (empty($carts)) {
            return array();
        }
        
        // Analyser les paniers et compter les produits
        $product_counts = array();
        
        foreach ($carts as $cart) {
            $contents = $this->safely_decode_cart_contents($cart->cart_contents);
            
            if (!$contents) {
                continue;
            }
            
            // Compatibilité avec différents formats de panier
            if (is_array($contents)) {
                foreach ($contents as $item) {
                    // Format attendu pour les produits
                    if (isset($item['product_id'])) {
                        $product_id = absint($item['product_id']);
                        
                        if (!isset($product_counts[$product_id])) {
                            $product_counts[$product_id] = array(
                                'id' => $product_id,
                                'name' => $this->get_product_name($product_id),
                                'count' => 0
                            );
                        }
                        
                        $product_counts[$product_id]['count']++;
                    }
                }
            }
        }
        
        // Trier par fréquence d'abandon
        usort($product_counts, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        // Limiter le nombre de résultats
        return array_slice($product_counts, 0, $limit);
    }
    
    /**
     * Récupère les informations sur l'efficacité des emails de récupération
     * 
     * @param string $start_date Date de début (format Y-m-d)
     * @param string $end_date Date de fin (format Y-m-d)
     * @return array Statistiques d'efficacité des emails
     */
    public function get_email_efficiency($start_date, $end_date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return array(
                'sent' => 0,
                'recovered' => 0,
                'conversion_rate' => 0
            );
        }
        
        // Valider les dates
        if (!$this->validate_date($start_date) || !$this->validate_date($end_date)) {
            return array(
                'sent' => 0,
                'recovered' => 0,
                'conversion_rate' => 0
            );
        }
        
        // Nombre total d'emails envoyés
        $sent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name}
             WHERE reminder_sent = 1
             AND last_reminder_sent BETWEEN %s AND %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
        
        // Nombre de paniers récupérés après email
        $recovered = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name}
             WHERE recovered = 1
             AND reminder_sent = 1
             AND recovered_at BETWEEN %s AND %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
        
        // Calculer le taux de conversion
        $conversion_rate = $sent > 0 ? round(($recovered / $sent) * 100, 2) : 0;
        
        return array(
            'sent' => $sent,
            'recovered' => $recovered,
            'conversion_rate' => $conversion_rate
        );
    }
    
    /**
     * Décode en toute sécurité le contenu du panier avec validation des données
     * 
     * @param string $cart_contents Contenu du panier encodé
     * @return array|false Contenu du panier décodé ou false en cas d'échec
     */
    private function safely_decode_cart_contents($cart_contents) {
        if (empty($cart_contents)) {
            return false;
        }
        
        // Désérialisation sécurisée
        $contents = maybe_unserialize($cart_contents);
        
        // Si c'est déjà un tableau, le renvoyer
        if (is_array($contents)) {
            return $contents;
        }
        
        // Essayer de décoder JSON
        $json_contents = json_decode($cart_contents, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json_contents)) {
            return $json_contents;
        }
        
        // Si nous arrivons ici, le format n'est pas reconnu
        return false;
    }
    
    /**
     * Récupère le nom d'un produit en toute sécurité
     * 
     * @param int $product_id ID du produit
     * @return string Nom du produit
     */
    private function get_product_name($product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            return $product->get_name();
        }
        
        return __('Produit inconnu', 'life-travel-excursion') . ' (ID: ' . $product_id . ')';
    }
    
    /**
     * Valide une date au format Y-m-d
     * 
     * @param string $date Date à valider
     * @return bool True si la date est valide, false sinon
     */
    private function validate_date($date) {
        if (empty($date)) {
            return false;
        }
        
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Récupère la plage de dates en fonction d'un identifiant de plage
     * 
     * @param string $range Identifiant de la plage ('7d', '30d', '90d', 'year', 'custom')
     * @param string $custom_start Date de début personnalisée (pour 'custom')
     * @param string $custom_end Date de fin personnalisée (pour 'custom')
     * @return array Plage de dates (start_date, end_date)
     */
    public function get_date_range($range, $custom_start = '', $custom_end = '') {
        $end_date = date('Y-m-d');
        $start_date = '';
        
        switch ($range) {
            case '7d':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30d':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
            case '90d':
                $start_date = date('Y-m-d', strtotime('-90 days'));
                break;
            case 'year':
                $start_date = date('Y-01-01');
                break;
            case 'custom':
                if ($this->validate_date($custom_start)) {
                    $start_date = $custom_start;
                } else {
                    $start_date = date('Y-m-d', strtotime('-30 days'));
                }
                
                if ($this->validate_date($custom_end)) {
                    $end_date = $custom_end;
                }
                break;
            default:
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
        }
        
        return array(
            'start_date' => $start_date,
            'end_date' => $end_date
        );
    }
    
    /**
     * Récupère les données pour les graphiques d'analyse
     * 
     * @param string $range Identifiant de la plage
     * @param string $custom_start Date de début personnalisée
     * @param string $custom_end Date de fin personnalisée
     * @return array Données pour les graphiques
     */
    public function get_chart_data($range, $custom_start = '', $custom_end = '') {
        // Récupérer la plage de dates
        $dates = $this->get_date_range($range, $custom_start, $custom_end);
        $start_date = $dates['start_date'];
        $end_date = $dates['end_date'];
        
        // Récupérer les tendances
        $trends = $this->get_cart_trends($start_date, $end_date);
        
        // Préparer les données pour les graphiques
        $chart_data = array(
            'dates' => array(),
            'totals' => array(),
            'recovered' => array(),
            'reminded' => array(),
            'avgValues' => array()
        );
        
        foreach ($trends as $day) {
            $chart_data['dates'][] = $day->date;
            $chart_data['totals'][] = (int) $day->total;
            $chart_data['recovered'][] = (int) $day->recovered;
            $chart_data['reminded'][] = (int) $day->reminded;
            $chart_data['avgValues'][] = round((float) $day->avg_value, 2);
        }
        
        // Ajouter les produits les plus abandonnés
        $chart_data['products'] = $this->get_most_abandoned_products($start_date, $end_date);
        
        // Ajouter les statistiques d'efficacité des emails
        $chart_data['emailEfficiency'] = $this->get_email_efficiency($start_date, $end_date);
        
        return $chart_data;
    }
}
