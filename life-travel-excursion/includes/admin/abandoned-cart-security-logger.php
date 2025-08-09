<?php
/**
 * Journal de sécurité pour les paniers abandonnés
 * 
 * Gère la journalisation sécurisée des événements liés aux paniers abandonnés
 * pour la détection, l'analyse et la réponse aux menaces de sécurité.
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

defined('ABSPATH') || exit;

/**
 * Classe pour la journalisation des événements de sécurité
 */
class Life_Travel_Security_Logger {
    
    /**
     * Instance unique (singleton)
     * @var Life_Travel_Security_Logger
     */
    private static $instance = null;
    
    /**
     * Nom de la table des journaux de sécurité
     * @var string
     */
    private $log_table;
    
    /**
     * Niveaux de gravité des événements
     * @var array
     */
    private $severity_levels = array(
        'info'      => 0, // Informationnel
        'notice'    => 1, // Événement notable mais non préoccupant
        'warning'   => 2, // Avertissement nécessitant une attention
        'error'     => 3, // Erreur significative
        'critical'  => 4  // Incident critique nécessitant une action immédiate
    );
    
    /**
     * Constructeur privé (pattern singleton)
     */
    private function __construct() {
        global $wpdb;
        $this->log_table = $wpdb->prefix . 'life_travel_security_log';
        
        // S'assurer que la table existe
        $this->maybe_create_table();
        
        // Initialiser les hooks
        add_action('plugins_loaded', array($this, 'setup_hooks'));
    }
    
    /**
     * Obtenir l'instance unique (pattern singleton)
     * 
     * @return Life_Travel_Security_Logger Instance unique
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Configure les hooks nécessaires
     */
    public function setup_hooks() {
        // Hooks pour les événements liés aux paniers abandonnés
        add_action('life_travel_cart_abandoned', array($this, 'log_cart_abandoned'), 10, 2);
        add_action('life_travel_cart_recovered', array($this, 'log_cart_recovered'), 10, 2);
        add_action('life_travel_recovery_email_sent', array($this, 'log_recovery_email'), 10, 2);
        add_action('life_travel_recovery_token_validation', array($this, 'log_token_validation'), 10, 3);
        
        // Nettoyage automatique des anciens journaux
        add_action('life_travel_cleanup_security_logs', array($this, 'cleanup_old_logs'));
        
        // Planifier le nettoyage des journaux
        if (!wp_next_scheduled('life_travel_cleanup_security_logs')) {
            wp_schedule_event(time(), 'daily', 'life_travel_cleanup_security_logs');
        }
    }
    
    /**
     * Crée la table des journaux si elle n'existe pas
     */
    private function maybe_create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->log_table}'") != $this->log_table) {
            // Création de la table avec colonnes sécurisées
            $sql = "CREATE TABLE {$this->log_table} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                event_time datetime NOT NULL,
                event_type varchar(50) NOT NULL,
                severity tinyint(1) NOT NULL DEFAULT 0,
                user_id bigint(20) DEFAULT NULL,
                ip_address varchar(45) NOT NULL,
                user_agent varchar(255) DEFAULT NULL,
                cart_id bigint(20) DEFAULT NULL,
                request_uri varchar(255) DEFAULT NULL,
                event_data longtext DEFAULT NULL,
                status varchar(20) NOT NULL DEFAULT 'new',
                PRIMARY KEY  (id),
                KEY event_time (event_time),
                KEY event_type (event_type),
                KEY severity (severity),
                KEY status (status),
                KEY cart_id (cart_id)
            ) $charset_collate;";
            
            // Utilisation de dbDelta pour une gestion propre des tables
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Enregistre un événement de sécurité
     * 
     * @param string $event_type Type d'événement
     * @param array $event_data Données de l'événement
     * @param string $severity Niveau de gravité (info, notice, warning, error, critical)
     * @param int $cart_id ID du panier concerné (optionnel)
     * @return int|false ID du journal ou false en cas d'échec
     */
    public function log_event($event_type, $event_data = array(), $severity = 'info', $cart_id = null) {
        global $wpdb;
        
        // Validation du type d'événement
        if (empty($event_type)) {
            return false;
        }
        
        // Validation du niveau de gravité
        if (!isset($this->severity_levels[$severity])) {
            $severity = 'info';
        }
        
        // Préparer les données de l'événement
        $log_data = array(
            'event_time' => current_time('mysql'),
            'event_type' => sanitize_text_field($event_type),
            'severity' => $this->severity_levels[$severity],
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : null,
            'cart_id' => $cart_id ? absint($cart_id) : null,
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : null,
            'event_data' => !empty($event_data) ? wp_json_encode($this->sanitize_event_data($event_data)) : null,
            'status' => 'new'
        );
        
        // Insérer dans la base de données
        $result = $wpdb->insert(
            $this->log_table,
            $log_data,
            array(
                '%s', // event_time
                '%s', // event_type
                '%d', // severity
                '%d', // user_id
                '%s', // ip_address
                '%s', // user_agent
                '%d', // cart_id
                '%s', // request_uri
                '%s', // event_data
                '%s'  // status
            )
        );
        
        if ($result === false) {
            // Journaliser l'erreur dans les logs WordPress si l'insertion échoue
            error_log('Life Travel Security: Échec de journalisation - ' . $wpdb->last_error);
            return false;
        }
        
        $log_id = $wpdb->insert_id;
        
        // Déclencher une action après la journalisation
        do_action('life_travel_security_event_logged', $log_id, $event_type, $severity, $event_data);
        
        // Si critique, déclencher une alerte immédiate
        if ($severity === 'critical') {
            $this->trigger_critical_alert($log_id, $event_type, $event_data);
        }
        
        return $log_id;
    }
    
    /**
     * Journalise un panier abandonné
     * 
     * @param int $cart_id ID du panier
     * @param array $cart_data Données du panier
     */
    public function log_cart_abandoned($cart_id, $cart_data) {
        $event_data = array(
            'cart_total' => isset($cart_data['cart_total']) ? $cart_data['cart_total'] : 0,
            'email' => isset($cart_data['email']) ? $cart_data['email'] : '',
            'items_count' => isset($cart_data['contents']) && is_array($cart_data['contents']) ? count($cart_data['contents']) : 0
        );
        
        $this->log_event('cart_abandoned', $event_data, 'info', $cart_id);
    }
    
    /**
     * Journalise la récupération d'un panier
     * 
     * @param int $cart_id ID du panier
     * @param array $recovery_data Données de récupération
     */
    public function log_cart_recovered($cart_id, $recovery_data) {
        $event_data = array(
            'recovery_method' => isset($recovery_data['method']) ? $recovery_data['method'] : 'unknown',
            'token_used' => isset($recovery_data['token']) ? $recovery_data['token'] : '',
            'order_id' => isset($recovery_data['order_id']) ? $recovery_data['order_id'] : 0
        );
        
        $this->log_event('cart_recovered', $event_data, 'info', $cart_id);
    }
    
    /**
     * Journalise l'envoi d'un email de récupération
     * 
     * @param int $cart_id ID du panier
     * @param string $email Adresse email
     */
    public function log_recovery_email($cart_id, $email) {
        $event_data = array(
            'email' => $email,
            'sent_time' => current_time('mysql')
        );
        
        $this->log_event('recovery_email_sent', $event_data, 'info', $cart_id);
    }
    
    /**
     * Journalise la validation d'un token de récupération
     * 
     * @param string $token Token de récupération
     * @param bool $is_valid Si le token est valide
     * @param int $cart_id ID du panier (si disponible)
     */
    public function log_token_validation($token, $is_valid, $cart_id = null) {
        $severity = $is_valid ? 'info' : 'warning';
        
        $event_data = array(
            'token' => $token,
            'is_valid' => $is_valid,
            'validation_time' => current_time('mysql')
        );
        
        // Si le token est invalide, ajouter des informations supplémentaires
        if (!$is_valid) {
            // Vérifier les tentatives récentes échouées
            $recent_failures = $this->count_recent_failed_validations();
            $event_data['recent_failures'] = $recent_failures;
            
            // Augmenter la gravité si plusieurs échecs récents
            if ($recent_failures > 5) {
                $severity = 'error';
            }
            if ($recent_failures > 10) {
                $severity = 'critical';
            }
        }
        
        $this->log_event('token_validation', $event_data, $severity, $cart_id);
    }
    
    /**
     * Compte le nombre de validations de token échouées récemment
     * 
     * @param int $minutes Intervalle de temps en minutes
     * @return int Nombre de validations échouées
     */
    public function count_recent_failed_validations($minutes = 60) {
        global $wpdb;
        
        $time_threshold = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        $ip_address = $this->get_client_ip();
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$this->log_table} 
             WHERE event_type = 'token_validation' 
             AND event_data LIKE %s
             AND ip_address = %s
             AND event_time > %s",
            '%"is_valid":false%',
            $ip_address,
            $time_threshold
        ));
        
        return (int) $count;
    }
    
    /**
     * Déclenche une alerte pour un événement critique
     * 
     * @param int $log_id ID du journal
     * @param string $event_type Type d'événement
     * @param array $event_data Données de l'événement
     */
    private function trigger_critical_alert($log_id, $event_type, $event_data) {
        // Envoyer une notification par email
        $admin_email = get_option('admin_email');
        
        $subject = sprintf(
            __('[ALERTE SÉCURITÉ] %s - Life Travel Excursion', 'life-travel-excursion'),
            $event_type
        );
        
        $message = sprintf(
            __("Une alerte de sécurité critique a été détectée.\n\nType d'événement: %s\nID de journal: %d\nHeure: %s\nIP: %s\n\nDonnées: %s\n\nVeuillez vérifier le tableau de bord de sécurité pour plus de détails.", 'life-travel-excursion'),
            $event_type,
            $log_id,
            current_time('mysql'),
            $this->get_client_ip(),
            wp_json_encode($event_data, JSON_PRETTY_PRINT)
        );
        
        // Utiliser wp_mail avec les headers appropriés
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);
        
        // Déclencher une action pour d'autres systèmes (comme Slack, etc.)
        do_action('life_travel_security_critical_alert', $log_id, $event_type, $event_data);
    }
    
    /**
     * Nettoie les anciens journaux
     * 
     * @param int $days Nombre de jours à conserver
     * @return int Nombre de journaux supprimés
     */
    public function cleanup_old_logs($days = 90) {
        global $wpdb;
        
        // Obtenir la date limite
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Supprimer les journaux plus anciens que la date limite
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->log_table} 
             WHERE event_time < %s 
             AND severity < %d",
            $cutoff_date,
            $this->severity_levels['error'] // Conserver les journaux d'erreurs et critiques plus longtemps
        ));
        
        return $result;
    }
    
    /**
     * Récupère l'adresse IP du client de manière sécurisée
     * 
     * @return string Adresse IP du client
     */
    private function get_client_ip() {
        $ip = '127.0.0.1';
        
        // Vérifier les différentes variables serveur
        $server_vars = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($server_vars as $var) {
            if (isset($_SERVER[$var])) {
                $ip = sanitize_text_field($_SERVER[$var]);
                break;
            }
        }
        
        // Si c'est une liste d'IPs, prendre la première
        if (strpos($ip, ',') !== false) {
            $ip_list = explode(',', $ip);
            $ip = trim($ip_list[0]);
        }
        
        // Valider l'IP
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
        
        return $ip;
    }
    
    /**
     * Sanitize les données d'événement avant l'enregistrement
     * 
     * @param array $data Données à sanitizer
     * @return array Données sanitisées
     */
    private function sanitize_event_data($data) {
        $sanitized = array();
        
        if (!is_array($data)) {
            return $sanitized;
        }
        
        foreach ($data as $key => $value) {
            // Sanitize la clé
            $key = sanitize_text_field($key);
            
            // Sanitize la valeur selon son type
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_event_data($value);
            } elseif (is_email($value)) {
                $sanitized[$key] = sanitize_email($value);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = floatval($value);
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Récupère les journaux de sécurité avec des filtres
     * 
     * @param array $args Arguments de filtre
     * @return array Journaux de sécurité
     */
    public function get_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'orderby' => 'event_time',
            'order' => 'DESC',
            'event_type' => '',
            'severity' => '',
            'cart_id' => '',
            'date_from' => '',
            'date_to' => '',
            'status' => '',
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Construire la requête
        $query = "SELECT * FROM {$this->log_table} WHERE 1=1";
        $query_args = array();
        
        // Filtre par type d'événement
        if (!empty($args['event_type'])) {
            $query .= " AND event_type = %s";
            $query_args[] = $args['event_type'];
        }
        
        // Filtre par gravité
        if (!empty($args['severity']) && isset($this->severity_levels[$args['severity']])) {
            $query .= " AND severity = %d";
            $query_args[] = $this->severity_levels[$args['severity']];
        }
        
        // Filtre par ID de panier
        if (!empty($args['cart_id'])) {
            $query .= " AND cart_id = %d";
            $query_args[] = absint($args['cart_id']);
        }
        
        // Filtre par date de début
        if (!empty($args['date_from'])) {
            $query .= " AND event_time >= %s";
            $query_args[] = $args['date_from'] . ' 00:00:00';
        }
        
        // Filtre par date de fin
        if (!empty($args['date_to'])) {
            $query .= " AND event_time <= %s";
            $query_args[] = $args['date_to'] . ' 23:59:59';
        }
        
        // Filtre par statut
        if (!empty($args['status'])) {
            $query .= " AND status = %s";
            $query_args[] = $args['status'];
        }
        
        // Recherche
        if (!empty($args['search'])) {
            $query .= " AND (event_type LIKE %s OR event_data LIKE %s OR ip_address LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $query_args[] = $search_term;
            $query_args[] = $search_term;
            $query_args[] = $search_term;
        }
        
        // Tri
        $valid_orderby = array('id', 'event_time', 'event_type', 'severity', 'ip_address', 'status');
        $orderby = in_array($args['orderby'], $valid_orderby) ? $args['orderby'] : 'event_time';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $query .= " ORDER BY {$orderby} {$order}";
        
        // Pagination
        $page = max(1, absint($args['page']));
        $per_page = max(1, absint($args['per_page']));
        $offset = ($page - 1) * $per_page;
        
        $query .= " LIMIT %d, %d";
        $query_args[] = $offset;
        $query_args[] = $per_page;
        
        // Préparer et exécuter la requête
        $prepared_query = !empty($query_args) ? $wpdb->prepare($query, $query_args) : $query;
        $results = $wpdb->get_results($prepared_query);
        
        // Formater les résultats
        $logs = array();
        foreach ($results as $row) {
            $logs[] = $this->format_log_entry($row);
        }
        
        return $logs;
    }
    
    /**
     * Formate une entrée de journal pour l'affichage
     * 
     * @param object $row Ligne de la base de données
     * @return array Entrée de journal formatée
     */
    private function format_log_entry($row) {
        // Convertir les données JSON en tableau
        $event_data = !empty($row->event_data) ? json_decode($row->event_data, true) : array();
        
        // Convertir le niveau de gravité en texte
        $severity_text = array_search($row->severity, $this->severity_levels) ?: 'info';
        
        return array(
            'id' => $row->id,
            'event_time' => $row->event_time,
            'event_type' => $row->event_type,
            'severity' => $severity_text,
            'user_id' => $row->user_id,
            'ip_address' => $row->ip_address,
            'user_agent' => $row->user_agent,
            'cart_id' => $row->cart_id,
            'request_uri' => $row->request_uri,
            'event_data' => $event_data,
            'status' => $row->status
        );
    }
    
    /**
     * Récupère les statistiques des journaux de sécurité
     * 
     * @param string $period Période (day, week, month, all)
     * @return array Statistiques des journaux
     */
    public function get_log_statistics($period = 'week') {
        global $wpdb;
        
        // Définir la période
        switch ($period) {
            case 'day':
                $date_threshold = date('Y-m-d H:i:s', strtotime('-1 day'));
                break;
            case 'week':
                $date_threshold = date('Y-m-d H:i:s', strtotime('-1 week'));
                break;
            case 'month':
                $date_threshold = date('Y-m-d H:i:s', strtotime('-1 month'));
                break;
            case 'all':
            default:
                $date_threshold = '1970-01-01 00:00:00';
                break;
        }
        
        // Statistiques par type d'événement
        $events_by_type = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) as count 
             FROM {$this->log_table} 
             WHERE event_time >= %s 
             GROUP BY event_type 
             ORDER BY count DESC",
            $date_threshold
        ));
        
        // Statistiques par niveau de gravité
        $events_by_severity = $wpdb->get_results($wpdb->prepare(
            "SELECT severity, COUNT(*) as count 
             FROM {$this->log_table} 
             WHERE event_time >= %s 
             GROUP BY severity 
             ORDER BY severity DESC",
            $date_threshold
        ));
        
        // Convertir les niveaux de gravité en texte
        $severity_stats = array();
        foreach ($events_by_severity as $row) {
            $severity_text = array_search($row->severity, $this->severity_levels) ?: 'unknown';
            $severity_stats[$severity_text] = (int) $row->count;
        }
        
        // Total d'événements
        $total_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->log_table} WHERE event_time >= %s",
            $date_threshold
        ));
        
        // Statistiques sur les paniers récupérés
        $recovered_carts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->log_table} 
             WHERE event_time >= %s AND event_type = 'cart_recovered'",
            $date_threshold
        ));
        
        return array(
            'total_events' => (int) $total_events,
            'events_by_type' => $events_by_type,
            'events_by_severity' => $severity_stats,
            'recovered_carts' => (int) $recovered_carts,
            'period' => $period
        );
    }
}
