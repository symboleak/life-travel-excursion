<?php
/**
 * Gestionnaires de synchronisation hors-ligne - Partie 2
 *
 * Contient les gestionnaires spécifiques pour chaque type de données synchronisables.
 *
 * @package Life Travel Excursion
 * @version 2.5.0
 */

defined('ABSPATH') || exit;

/**
 * Classe de gestion des gestionnaires de synchronisation hors-ligne
 */
class Life_Travel_Offline_Sync_Handlers {
    /**
     * Instance unique (Singleton)
     * @var Life_Travel_Offline_Sync_Handlers
     */
    private static $instance = null;
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        // Initialisation
    }
    
    /**
     * Retourne l'instance unique (Singleton)
     * 
     * @return Life_Travel_Offline_Sync_Handlers
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Synchronise les demandes de réservation
     * 
     * @param array $data Données de réservation
     * @param int $user_id ID utilisateur
     * @param string $device_id ID de l'appareil
     * @return array Résultat de la synchronisation
     */
    public function sync_booking_request($data, $user_id, $device_id) {
        // Initialisation du résultat
        $result = [
            'success' => false,
            'error' => ['message' => __('Erreur de synchronisation de la réservation', 'life-travel-excursion')],
            'data' => []
        ];
        
        try {
            // Décoder les données
            $booking_data = is_string($data) ? json_decode($data, true) : $data;
            
            if (!is_array($booking_data)) {
                throw new Exception(__('Format de réservation invalide', 'life-travel-excursion'));
            }
            
            // Valider les données minimales requises
            if (!isset($booking_data['product_id'], $booking_data['start_date'], $booking_data['participants'])) {
                throw new Exception(__('Données de réservation incomplètes', 'life-travel-excursion'));
            }
            
            // Données de la réservation
            $product_id = absint($booking_data['product_id']);
            $start_date = sanitize_text_field($booking_data['start_date']);
            $end_date = isset($booking_data['end_date']) ? sanitize_text_field($booking_data['end_date']) : $start_date;
            $participants = absint($booking_data['participants']);
            $extras = isset($booking_data['extras']) ? $booking_data['extras'] : [];
            $activities = isset($booking_data['activities']) ? $booking_data['activities'] : [];
            $customer_data = isset($booking_data['customer']) ? $booking_data['customer'] : [];
            
            // Vérifier la disponibilité si possible
            if (function_exists('life_travel_excursion_check_availability')) {
                $availability = life_travel_excursion_check_availability($product_id, $start_date, $participants);
                
                if (isset($availability['available']) && !$availability['available']) {
                    throw new Exception(__('Excursion non disponible pour cette date et ce nombre de participants', 'life-travel-excursion'));
                }
            }
            
            // Stocker la demande de réservation en attente
            $booking_id = $this->store_pending_booking($booking_data, $user_id, $device_id);
            
            if (!$booking_id) {
                throw new Exception(__('Erreur lors de l\'enregistrement de la réservation', 'life-travel-excursion'));
            }
            
            // Envoyer un e-mail de confirmation si requis
            if (isset($booking_data['send_confirmation']) && $booking_data['send_confirmation'] && !empty($customer_data['email'])) {
                $this->send_booking_confirmation_email($booking_id, $customer_data['email']);
            }
            
            $result['success'] = true;
            $result['data'] = [
                'booking_id' => $booking_id,
                'status' => 'pending',
                'message' => __('Demande de réservation enregistrée avec succès et en attente de traitement', 'life-travel-excursion')
            ];
            
        } catch (Exception $e) {
            $result['error']['message'] = $e->getMessage();
            
            // Journaliser l'erreur
            error_log('[Life Travel] Booking sync error: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Stocke une réservation en attente
     * 
     * @param array $booking_data Données de réservation
     * @param int $user_id ID utilisateur
     * @param string $device_id ID de l'appareil
     * @return int|false ID de réservation ou false si échec
     */
    private function store_pending_booking($booking_data, $user_id, $device_id) {
        global $wpdb;
        
        // Table des réservations
        $table_name = $wpdb->prefix . 'life_travel_pending_bookings';
        
        // S'assurer que la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->create_pending_bookings_table();
        }
        
        // Préparer les données d'insertion
        $data = [
            'user_id' => $user_id,
            'device_id' => $device_id,
            'product_id' => absint($booking_data['product_id']),
            'start_date' => sanitize_text_field($booking_data['start_date']),
            'end_date' => isset($booking_data['end_date']) ? sanitize_text_field($booking_data['end_date']) : $booking_data['start_date'],
            'participants' => absint($booking_data['participants']),
            'booking_data' => json_encode($booking_data),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'modified_at' => current_time('mysql'),
            'origin' => 'offline'
        ];
        
        // Formats
        $formats = ['%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'];
        
        // Insérer
        $result = $wpdb->insert($table_name, $data, $formats);
        
        if ($result) {
            // Retourner l'ID inséré
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Crée la table des réservations en attente
     */
    private function create_pending_bookings_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'life_travel_pending_bookings';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL DEFAULT 0,
            device_id varchar(100) NOT NULL,
            product_id bigint(20) NOT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            participants int(11) NOT NULL,
            booking_data longtext NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            modified_at datetime NOT NULL,
            origin varchar(50) NOT NULL DEFAULT 'offline',
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY device_id (device_id),
            KEY product_id (product_id),
            KEY start_date (start_date),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Envoie un e-mail de confirmation de réservation
     * 
     * @param int $booking_id ID de réservation
     * @param string $email Adresse e-mail du client
     * @return bool Succès ou échec
     */
    private function send_booking_confirmation_email($booking_id, $email) {
        // Récupérer les détails de la réservation
        global $wpdb;
        $table_name = $wpdb->prefix . 'life_travel_pending_bookings';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $booking_id));
        
        if (!$booking) {
            return false;
        }
        
        // Décoder les données de réservation
        $booking_data = json_decode($booking->booking_data, true);
        
        // Récupérer les informations du produit
        $product = wc_get_product($booking->product_id);
        $product_name = $product ? $product->get_name() : __('Excursion', 'life-travel-excursion');
        
        // Construire l'e-mail
        $subject = sprintf(__('Confirmation de demande de réservation #%d', 'life-travel-excursion'), $booking_id);
        
        $body = sprintf(__('Bonjour,%s', 'life-travel-excursion'), "\n\n");
        $body .= sprintf(__('Nous avons bien reçu votre demande de réservation pour "%s".%s', 'life-travel-excursion'), $product_name, "\n\n");
        $body .= sprintf(__('Détails de la réservation:%s', 'life-travel-excursion'), "\n");
        $body .= sprintf(__('- Numéro de référence: %d%s', 'life-travel-excursion'), $booking_id, "\n");
        $body .= sprintf(__('- Date: %s%s', 'life-travel-excursion'), $booking->start_date, "\n");
        $body .= sprintf(__('- Nombre de participants: %d%s', 'life-travel-excursion'), $booking->participants, "\n\n");
        
        $body .= __('Votre demande est actuellement en attente de validation. Notre équipe va la traiter dans les plus brefs délais.', 'life-travel-excursion') . "\n\n";
        $body .= __('Merci pour votre confiance.', 'life-travel-excursion') . "\n\n";
        $body .= get_bloginfo('name');
        
        // Envoyer l'e-mail
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        return wp_mail($email, $subject, nl2br($body), $headers);
    }
    
    /**
     * Synchronise les préférences utilisateur
     * 
     * @param array $data Préférences
     * @param int $user_id ID utilisateur
     * @param string $device_id ID de l'appareil
     * @return array Résultat de la synchronisation
     */
    public function sync_user_preferences($data, $user_id, $device_id) {
        // Initialisation du résultat
        $result = [
            'success' => false,
            'error' => ['message' => __('Erreur de synchronisation des préférences', 'life-travel-excursion')],
            'data' => []
        ];
        
        try {
            // Décoder les données
            $preferences = is_string($data) ? json_decode($data, true) : $data;
            
            if (!is_array($preferences)) {
                throw new Exception(__('Format de préférences invalide', 'life-travel-excursion'));
            }
            
            // Filtrer les préférences autorisées
            $allowed_preferences = [
                'notification_email',
                'notification_sms',
                'notification_push',
                'currency',
                'language',
                'theme',
                'display_mode',
                'map_view',
                'default_participants'
            ];
            
            $filtered_preferences = [];
            foreach ($preferences as $key => $value) {
                if (in_array($key, $allowed_preferences)) {
                    $filtered_preferences[$key] = sanitize_text_field($value);
                }
            }
            
            // Si l'utilisateur est connecté, mettre à jour ses préférences
            if ($user_id > 0) {
                // Mettre à jour les préférences utilisateur
                foreach ($filtered_preferences as $key => $value) {
                    update_user_meta($user_id, 'life_travel_' . $key, $value);
                }
                
                $result['success'] = true;
                $result['data'] = [
                    'message' => __('Préférences mises à jour avec succès', 'life-travel-excursion'),
                    'updated_fields' => array_keys($filtered_preferences)
                ];
            } else {
                // Pour les utilisateurs non connectés, stocker en cookie
                $expiry = time() + (30 * DAY_IN_SECONDS); // 30 jours
                setcookie('life_travel_preferences', json_encode($filtered_preferences), $expiry, COOKIEPATH, COOKIE_DOMAIN);
                
                $result['success'] = true;
                $result['data'] = [
                    'message' => __('Préférences stockées localement', 'life-travel-excursion'),
                    'updated_fields' => array_keys($filtered_preferences)
                ];
            }
        } catch (Exception $e) {
            $result['error']['message'] = $e->getMessage();
            
            // Journaliser l'erreur
            error_log('[Life Travel] Preferences sync error: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Synchronise les excursions consultées
     * 
     * @param array $data Liste des excursions
     * @param int $user_id ID utilisateur
     * @param string $device_id ID de l'appareil
     * @return array Résultat de la synchronisation
     */
    public function sync_viewed_excursions($data, $user_id, $device_id) {
        // Initialisation du résultat
        $result = [
            'success' => false,
            'error' => ['message' => __('Erreur de synchronisation des excursions consultées', 'life-travel-excursion')],
            'data' => []
        ];
        
        try {
            // Décoder les données
            $viewed_excursions = is_string($data) ? json_decode($data, true) : $data;
            
            if (!is_array($viewed_excursions)) {
                throw new Exception(__('Format de données invalide', 'life-travel-excursion'));
            }
            
            // Filtrer et valider les IDs d'excursions
            $valid_excursions = [];
            foreach ($viewed_excursions as $item) {
                if (isset($item['id'], $item['timestamp'])) {
                    $product_id = absint($item['id']);
                    $timestamp = absint($item['timestamp']);
                    
                    // Vérifier que le produit existe
                    if (get_post_type($product_id) === 'product') {
                        $valid_excursions[] = [
                            'id' => $product_id,
                            'timestamp' => $timestamp
                        ];
                    }
                }
            }
            
            // Limiter à 50 excursions
            $valid_excursions = array_slice($valid_excursions, 0, 50);
            
            // Si l'utilisateur est connecté, mettre à jour son historique
            if ($user_id > 0) {
                // Récupérer l'historique existant
                $existing_history = get_user_meta($user_id, 'life_travel_viewed_excursions', true);
                $existing_history = is_array($existing_history) ? $existing_history : [];
                
                // Fusionner avec le nouvel historique
                $merged_history = $this->merge_viewed_excursions($existing_history, $valid_excursions);
                
                // Mettre à jour
                update_user_meta($user_id, 'life_travel_viewed_excursions', $merged_history);
                
                $result['success'] = true;
                $result['data'] = [
                    'message' => __('Historique synchronisé avec succès', 'life-travel-excursion'),
                    'count' => count($merged_history)
                ];
            } else {
                // Pour les utilisateurs non connectés, stocker localement
                $result['success'] = true;
                $result['data'] = [
                    'message' => __('Historique enregistré localement', 'life-travel-excursion'),
                    'count' => count($valid_excursions)
                ];
            }
        } catch (Exception $e) {
            $result['error']['message'] = $e->getMessage();
            
            // Journaliser l'erreur
            error_log('[Life Travel] Viewed excursions sync error: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Fusionne deux listes d'excursions consultées
     * 
     * @param array $list1 Première liste
     * @param array $list2 Deuxième liste
     * @return array Liste fusionnée
     */
    private function merge_viewed_excursions($list1, $list2) {
        // Créer un tableau indexé par ID d'excursion
        $merged = [];
        
        // Ajouter les éléments de la première liste
        foreach ($list1 as $item) {
            $id = $item['id'];
            $merged[$id] = $item;
        }
        
        // Ajouter ou mettre à jour avec les éléments de la deuxième liste
        foreach ($list2 as $item) {
            $id = $item['id'];
            
            // Si l'élément existe déjà, prendre le plus récent
            if (isset($merged[$id])) {
                $time1 = $merged[$id]['timestamp'];
                $time2 = $item['timestamp'];
                
                if ($time2 > $time1) {
                    $merged[$id] = $item;
                }
            } else {
                $merged[$id] = $item;
            }
        }
        
        // Trier par timestamp décroissant (plus récent en premier)
        usort($merged, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        // Limiter à 50 éléments
        return array_slice($merged, 0, 50);
    }
    
    /**
     * Synchronise les excursions favorites
     * 
     * @param array $data Liste des favoris
     * @param int $user_id ID utilisateur
     * @param string $device_id ID de l'appareil
     * @return array Résultat de la synchronisation
     */
    public function sync_favorite_excursions($data, $user_id, $device_id) {
        // Initialisation du résultat
        $result = [
            'success' => false,
            'error' => ['message' => __('Erreur de synchronisation des favoris', 'life-travel-excursion')],
            'data' => []
        ];
        
        try {
            // Décoder les données
            $favorites = is_string($data) ? json_decode($data, true) : $data;
            
            if (!is_array($favorites)) {
                throw new Exception(__('Format de favoris invalide', 'life-travel-excursion'));
            }
            
            // Filtrer et valider les IDs d'excursions
            $valid_favorites = [];
            foreach ($favorites as $item) {
                $product_id = absint($item);
                
                // Vérifier que le produit existe
                if (get_post_type($product_id) === 'product') {
                    $valid_favorites[] = $product_id;
                }
            }
            
            // Limiter à 50 favoris
            $valid_favorites = array_slice($valid_favorites, 0, 50);
            
            // Si l'utilisateur est connecté, mettre à jour ses favoris
            if ($user_id > 0) {
                update_user_meta($user_id, 'life_travel_favorite_excursions', $valid_favorites);
                
                $result['success'] = true;
                $result['data'] = [
                    'message' => __('Favoris synchronisés avec succès', 'life-travel-excursion'),
                    'count' => count($valid_favorites)
                ];
            } else {
                // Pour les utilisateurs non connectés, stockage local uniquement
                $result['success'] = true;
                $result['data'] = [
                    'message' => __('Favoris enregistrés localement', 'life-travel-excursion'),
                    'count' => count($valid_favorites)
                ];
            }
        } catch (Exception $e) {
            $result['error']['message'] = $e->getMessage();
            
            // Journaliser l'erreur
            error_log('[Life Travel] Favorites sync error: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Prépare un package de données pour le mode hors-ligne
     * 
     * @param int $user_id ID utilisateur
     * @return array Données pour mode hors-ligne
     */
    public function prepare_offline_bundle($user_id = 0) {
        // Obtenir la configuration hors-ligne
        $config = Life_Travel_Offline_Sync_Manager::get_instance()->get_offline_config();
        
        // Excursions populaires
        $popular_excursions = $this->get_popular_excursions(10);
        
        // Données de base
        $bundle = [
            'timestamp' => current_time('timestamp'),
            'expiry' => current_time('timestamp') + (HOUR_IN_SECONDS * $config['expiry_time']),
            'version' => '1.0',
            'site_info' => [
                'name' => get_bloginfo('name'),
                'url' => home_url(),
                'logo' => get_site_icon_url(),
                'currency' => get_woocommerce_currency(),
                'currency_symbol' => get_woocommerce_currency_symbol()
            ],
            'popular_excursions' => [],
            'user_data' => []
        ];
        
        // Ajouter les excursions populaires
        foreach ($popular_excursions as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;
            
            $bundle['popular_excursions'][] = [
                'id' => $product_id,
                'name' => $product->get_name(),
                'excerpt' => $product->get_short_description(),
                'price' => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'thumbnail' => get_the_post_thumbnail_url($product_id, 'thumbnail'),
                'link' => get_permalink($product_id),
                'categories' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']),
                'rating' => $product->get_average_rating()
            ];
        }
        
        // Ajouter les données utilisateur si connecté
        if ($user_id > 0) {
            $bundle['user_data'] = [
                'viewed_excursions' => get_user_meta($user_id, 'life_travel_viewed_excursions', true) ?: [],
                'favorite_excursions' => get_user_meta($user_id, 'life_travel_favorite_excursions', true) ?: [],
                'preferences' => $this->get_user_preferences($user_id)
            ];
        }
        
        return $bundle;
    }
    
    /**
     * Récupère les préférences d'un utilisateur
     * 
     * @param int $user_id ID utilisateur
     * @return array Préférences
     */
    private function get_user_preferences($user_id) {
        $preferences = [];
        $allowed_preferences = [
            'notification_email',
            'notification_sms',
            'notification_push',
            'currency',
            'language',
            'theme',
            'display_mode',
            'map_view',
            'default_participants'
        ];
        
        foreach ($allowed_preferences as $key) {
            $value = get_user_meta($user_id, 'life_travel_' . $key, true);
            if ($value) {
                $preferences[$key] = $value;
            }
        }
        
        return $preferences;
    }
    
    /**
     * Récupère les excursions populaires
     * 
     * @param int $limit Nombre d'excursions
     * @return array IDs d'excursions
     */
    private function get_popular_excursions($limit = 5) {
        global $wpdb;
        
        // Récupérer depuis le cache si disponible
        $cached = get_transient('life_travel_popular_excursions');
        if ($cached !== false) {
            return $cached;
        }
        
        // Requête pour les produits les plus vendus
        $popular_query = "
            SELECT p.ID, COUNT(oim.meta_value) as order_count
            FROM {$wpdb->posts} p
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oim.meta_value = p.ID
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_item_id = oim.order_item_id
            JOIN {$wpdb->posts} orders ON orders.ID = oi.order_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND oim.meta_key = '_product_id'
            AND orders.post_status IN ('wc-completed', 'wc-processing')
            GROUP BY p.ID
            ORDER BY order_count DESC
            LIMIT %d
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($popular_query, $limit));
        $popular_ids = [];
        
        if ($results) {
            foreach ($results as $item) {
                $popular_ids[] = (int)$item->ID;
            }
        } else {
            // Fallback: produits récents
            $args = [
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids'
            ];
            
            $popular_ids = get_posts($args);
        }
        
        // Mettre en cache
        set_transient('life_travel_popular_excursions', $popular_ids, DAY_IN_SECONDS);
        
        return $popular_ids;
    }
}

// Initialisation de la classe
function life_travel_offline_sync_handlers() {
    return Life_Travel_Offline_Sync_Handlers::get_instance();
}

// Démarrage
add_action('plugins_loaded', 'life_travel_offline_sync_handlers');
