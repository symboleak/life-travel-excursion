<?php
/**
 * Gestionnaires AJAX pour l'optimisation de base de données
 *
 * Ce fichier définit les points d'entrée AJAX pour les actions liées à l'optimisation
 * de base de données, notamment la purge du cache et la surveillance des performances.
 *
 * @package Life Travel Excursion
 * @version 3.0.0
 */

defined('ABSPATH') || exit;

/**
 * Classe de gestion des requêtes AJAX pour l'optimisation de base de données
 */
class Life_Travel_Database_Ajax {
    /**
     * Instance unique (Singleton)
     * @var Life_Travel_Database_Ajax
     */
    private static $instance = null;
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        // Enregistrer les points d'entrée AJAX
        add_action('wp_ajax_life_travel_purge_query_cache', [$this, 'ajax_purge_query_cache']);
        add_action('wp_ajax_life_travel_toggle_query_monitoring', [$this, 'ajax_toggle_query_monitoring']);
        add_action('wp_ajax_life_travel_admin_save_db_options', [$this, 'ajax_save_db_options']);
    }
    
    /**
     * Obtient l'instance unique
     * @return Life_Travel_Database_Ajax
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Gestionnaire AJAX pour purger le cache de requêtes
     */
    public function ajax_purge_query_cache() {
        // Vérifier le nonce et les autorisations
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_admin_nonce')) {
            wp_send_json_error([
                'message' => __('Erreur de sécurité. Veuillez rafraîchir la page.', 'life-travel-excursion')
            ]);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Vous n\'avez pas les droits nécessaires.', 'life-travel-excursion')
            ]);
            return;
        }
        
        // Purger le cache si l'optimiseur est disponible
        if (function_exists('life_travel_db_optimizer') && method_exists(life_travel_db_optimizer(), 'purge_availability_cache')) {
            life_travel_db_optimizer()->purge_availability_cache();
            wp_send_json_success([
                'message' => __('Le cache des requêtes a été purgé avec succès.', 'life-travel-excursion')
            ]);
        } else {
            // Fallback : supprimer directement les transients
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_lte_db_cache_%'");
            
            wp_send_json_success([
                'message' => __('Le cache des requêtes a été purgé avec succès (méthode alternative).', 'life-travel-excursion')
            ]);
        }
    }
    
    /**
     * Gestionnaire AJAX pour activer/désactiver la surveillance des requêtes
     */
    public function ajax_toggle_query_monitoring() {
        // Vérifier le nonce et les autorisations
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_admin_nonce')) {
            wp_send_json_error([
                'message' => __('Erreur de sécurité. Veuillez rafraîchir la page.', 'life-travel-excursion')
            ]);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Vous n\'avez pas les droits nécessaires.', 'life-travel-excursion')
            ]);
            return;
        }
        
        // Récupérer l'action (start ou stop)
        $action = isset($_POST['monitoring']) ? sanitize_key($_POST['monitoring']) : '';
        
        if (!in_array($action, ['start', 'stop'])) {
            wp_send_json_error([
                'message' => __('Action invalide.', 'life-travel-excursion')
            ]);
            return;
        }
        
        // Activer ou désactiver la surveillance
        if ($action === 'start') {
            update_option('life_travel_query_monitoring', 'yes');
            update_option('life_travel_query_monitoring_start', time());
            update_option('life_travel_query_monitoring_end', time() + 86400); // 24 heures
            
            wp_send_json_success([
                'message' => __('Surveillance des requêtes activée pour 24 heures.', 'life-travel-excursion')
            ]);
        } else {
            update_option('life_travel_query_monitoring', 'no');
            
            wp_send_json_success([
                'message' => __('Surveillance des requêtes désactivée.', 'life-travel-excursion')
            ]);
        }
    }
    
    /**
     * Gestionnaire AJAX pour sauvegarder les options de base de données
     */
    public function ajax_save_db_options() {
        // Vérifier le nonce et les autorisations
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_admin_nonce')) {
            wp_send_json_error([
                'message' => __('Erreur de sécurité. Veuillez rafraîchir la page.', 'life-travel-excursion')
            ]);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Vous n\'avez pas les droits nécessaires.', 'life-travel-excursion')
            ]);
            return;
        }
        
        // Récupérer et valider les options
        $enabled = isset($_POST['enabled']) ? sanitize_key($_POST['enabled']) : 'yes';
        $ttl = isset($_POST['ttl']) ? absint($_POST['ttl']) : 3600;
        $adaptive = isset($_POST['adaptive']) ? sanitize_key($_POST['adaptive']) : 'yes';
        
        // Valider les valeurs
        $enabled = in_array($enabled, ['yes', 'no']) ? $enabled : 'yes';
        $ttl = max(60, min(86400, $ttl)); // Entre 1 minute et 24 heures
        $adaptive = in_array($adaptive, ['yes', 'no']) ? $adaptive : 'yes';
        
        // Sauvegarder les options
        update_option('life_travel_db_cache_enabled', $enabled);
        update_option('life_travel_db_cache_ttl', $ttl);
        update_option('life_travel_adaptive_cache', $adaptive);
        
        // Mettre à jour l'optimiseur si disponible
        if (function_exists('life_travel_db_optimizer') && method_exists(life_travel_db_optimizer(), 'adjust_cache_ttl')) {
            life_travel_db_optimizer()->adjust_cache_ttl();
        }
        
        wp_send_json_success([
            'message' => __('Options de cache sauvegardées avec succès.', 'life-travel-excursion')
        ]);
    }
}

// Initialiser le gestionnaire AJAX
function life_travel_database_ajax() {
    return Life_Travel_Database_Ajax::get_instance();
}

// Démarrer le gestionnaire si on est en admin
if (is_admin()) {
    add_action('init', 'life_travel_database_ajax');
}
