<?php
/**
 * Gestion des paniers abandonnés
 * 
 * Ce fichier gère la récupération des paniers abandonnés pour maximiser les conversions
 * Sécurité renforcée contre les attaques CSRF et les injections SQL
 * 
 * @package Life Travel Excursion
 * @version 2.3.4
 */

defined('ABSPATH') || exit;

// Charger la configuration centralisée
require_once __DIR__ . '/config.php';

/**
 * Classe de gestion des paniers abandonnés
 */
class Life_Travel_Abandoned_Cart {
    /**
     * Nom de la table en base de données
     * @var string
     */
    private $table_name;
    
    /**
     * Durée de vie du cookie de suivi (en secondes)
     * @var int
     */
    private $cookie_lifetime;
    
    /**
     * Nombre maximum de tentatives de récupération
     * @var int
     */
    private $max_recovery_attempts;
    
    /**
     * Constructeur
     */
    public function __construct() {
        global $wpdb;
        
        // Initialiser les propriétés
        $this->table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        $this->cookie_lifetime = LIFE_TRAVEL_ABANDONED_CART_TIMEOUT;
        $this->max_recovery_attempts = 3;
        
        // Enregistrer les paniers abandonnés
        add_action('woocommerce_cart_updated', array($this, 'save_abandoned_cart'));
        
        // Endpoints AJAX avec sécurité renforcée
        add_action('wp_ajax_life_travel_sync_abandoned_cart', array($this, 'sync_abandoned_cart'));
        add_action('wp_ajax_nopriv_life_travel_sync_abandoned_cart', array($this, 'sync_abandoned_cart'));
        
        // Planifier l'envoi d'emails de récupération
        add_action('life_travel_send_abandoned_cart_emails', array($this, 'send_recovery_emails'));
        add_action('init', array($this, 'schedule_recovery_emails'));
        
        // Filtres et actions pour la sécurité
        add_action('wp_enqueue_scripts', array($this, 'add_cart_recovery_nonce'));
        add_filter('life_travel_recovery_link', array($this, 'secure_recovery_link'), 10, 2);
        
        // Ajout de hooks pour la récupération des paniers
        add_action('wp', array($this, 'process_recovery_request'));
        add_action('woocommerce_add_to_cart', array($this, 'mark_cart_as_recovered'), 10, 6);
    }
    
    /**
     * Planifie l'événement quotidien d'envoi d'emails de récupération
     */
    public function schedule_recovery_emails() {
        if (!wp_next_scheduled('life_travel_send_abandoned_cart_emails')) {
            wp_schedule_event(time(), 'daily', 'life_travel_send_abandoned_cart_emails');
        }
    }
    
    /**
     * Ajoute les nonces de sécurité pour les interactions avec le panier abandonné
     */
    public function add_cart_recovery_nonce() {
        // Vérifier que le script principal est bien enregistré
        if (wp_script_is('life-travel-excursion-frontend', 'registered')) {
            // Préparer les données pour la sécurité des interactions avec le panier
            $cart_security = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'sync_nonce' => life_travel_get_nonce('sync_cart'),
                'recover_nonce' => life_travel_get_nonce('recover_cart'),
                'delete_nonce' => life_travel_get_nonce('delete_cart')
            );
            
            // Transmettre les données au script frontend
            wp_localize_script('life-travel-excursion-frontend', 'lifeTravelCart', $cart_security);
            
            // Stocker le nonce de synchronisation dans localForage pour le service worker
            if (wp_script_is('life-travel-register-sw', 'registered')) {
                wp_add_inline_script('life-travel-register-sw', 
                    'if ("localforage" in window) {
                        localforage.setItem("cart_nonce", "' . $cart_security['sync_nonce'] . '");
                    }',
                    'after'
                );
            }
        }
    }
    
    /**
     * Sécurise le lien de récupération de panier avec un nonce et une signature
     * 
     * @param string $link Lien de récupération
     * @param object $cart Objet panier abandonné
     * @return string Lien sécurisé
     */
    public function secure_recovery_link($link, $cart) {
        // Générer un nonce spécifique pour ce panier
        $cart_id = absint($cart->id);
        $nonce = wp_create_nonce('life_travel_recover_cart_' . $cart_id);
        
        // Ajouter une signature basée sur l'email pour vérification supplémentaire
        $signature = wp_hash($cart->email . '|' . $cart_id . '|' . $cart->created_at);
        $signature = substr($signature, 0, 16); // Utiliser seulement une partie du hash
        
        // Ajouter le nonce et la signature au lien
        $link = add_query_arg(array(
            'recovery_nonce' => $nonce,
            'sig' => $signature
        ), $link);
        
        return $link;
    }
    
    /**
     * Traite une requête de récupération de panier abandonné
     */
    public function process_recovery_request() {
        // Vérifier si c'est une requête de récupération
        if (!isset($_GET['life_travel_recover'])) {
            return;
        }
        
        // Assainir et valider le paramètre de récupération
        $recovery_param = sanitize_text_field($_GET['life_travel_recover']);
        
        try {
            // Décoder les données
            $decoded_data = base64_decode($recovery_param);
            if (false === $decoded_data) {
                throw new Exception(__('Données de récupération invalides', 'life-travel-excursion'));
            }
            
            $recovery_data = explode('|', $decoded_data);
            if (count($recovery_data) !== 2) {
                throw new Exception(__('Format de données de récupération invalide', 'life-travel-excursion'));
            }
            
            // Extraire et valider l'ID du panier et l'email
            $cart_id = absint($recovery_data[0]);
            $email = sanitize_email($recovery_data[1]);
            
            if (!$cart_id || !is_email($email)) {
                throw new Exception(__('Identifiants de récupération invalides', 'life-travel-excursion'));
            }
            
            // Vérification de la signature HMAC pour prévenir la falsification
            $provided_sig = isset($_GET['sig']) ? sanitize_text_field($_GET['sig']) : '';
            global $wpdb;
            $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
            $created_at = $wpdb->get_var($wpdb->prepare(
                "SELECT created_at FROM {$table_name} WHERE id = %d AND email = %s",
                $cart_id,
                $email
            ));
            $expected_sig = substr(wp_hash($email . '|' . $cart_id . '|' . $created_at), 0, 16);
            if (empty($provided_sig) || !hash_equals($expected_sig, $provided_sig)) {
                throw new Exception(__('Signature de récupération invalide', 'life-travel-excursion'));
            }
            
            // Vérifier le nonce de récupération
            if (!isset($_GET['recovery_nonce'])) {
                throw new Exception(__('Jeton de sécurité manquant', 'life-travel-excursion'));
            }
            
            $nonce = sanitize_text_field($_GET['recovery_nonce']);
            if (!wp_verify_nonce($nonce, 'life_travel_recover_cart_' . $cart_id)) {
                throw new Exception(__('Jeton de sécurité expiré ou invalide', 'life-travel-excursion'));
            }
            
            // Récupérer le panier et le restaurer
            $this->restore_abandoned_cart($cart_id, $email);
            
        } catch (Exception $e) {
            // Journaliser l'erreur
            life_travel_log_security_issue('Tentative de récupération de panier abandonné échouée: ' . $e->getMessage());
            
            // Afficher un message d'erreur
            wc_add_notice($e->getMessage(), 'error');
        }
    }
    
    /**
     * Enregistre les paniers abandonnés en base de données avec sécurité renforcée
     * 
     * @return int|bool ID du panier enregistré ou false en cas d'échec
     */
    public function save_abandoned_cart() {
        try {
            // 1. Vérifications préliminaires
            
            // Ne rien faire si WooCommerce n'est pas chargé correctement
            if (!function_exists('WC') || !WC()->cart) {
                throw new Exception('WooCommerce cart not available');
            }
            
            // Ne rien faire si le panier est vide ou si on est sur la page de checkout
            if (WC()->cart->is_empty() || is_checkout()) {
                return false;
            }
            
            // 2. Récupération et validation de l'email
            
            $user_id = get_current_user_id();
            $email = '';
            
            // Stratégie de priorité pour l'email: utilisateur connecté > formulaire > cookie
            if ($user_id > 0) {
                $user = get_userdata($user_id);
                if ($user && !empty($user->user_email)) {
                    $email = sanitize_email($user->user_email);
                }
            }
            
            // Essayer de récupérer l'email du formulaire si vide
            if (empty($email) && !empty($_POST['billing_email'])) {
                $form_email = sanitize_email(wp_unslash($_POST['billing_email']));
                if (is_email($form_email)) {
                    $email = $form_email;
                }
            }
            
            // Enfin, essayer le cookie si toujours vide
            if (empty($email) && isset($_COOKIE['life_travel_guest_email'])) {
                $cookie_email = sanitize_email(wp_unslash($_COOKIE['life_travel_guest_email']));
                if (is_email($cookie_email)) {
                    $email = $cookie_email;
                }
            }
            
            // Ne pas continuer sans email valide
            if (empty($email) || !is_email($email)) {
                throw new Exception('No valid email available for abandoned cart');
            }
            
            // 3. Sécurisation et préparation des données du panier
            
            // Récupérer et valider le contenu du panier
            $cart_contents = WC()->cart->get_cart_for_session();
            if (empty($cart_contents) || !is_array($cart_contents)) {
                throw new Exception('Invalid cart contents');
            }
            
            // Assainir le contenu du panier pour éviter les injections
            $sanitized_cart = array();
            foreach ($cart_contents as $key => $item) {
                // Vérifier que chaque élément du panier est valide
                if (!isset($item['product_id']) || !is_numeric($item['product_id'])) {
                    continue; // Ignorer les articles invalides
                }
                
                // Créer une version assainie de l'article
                $sanitized_item = array(
                    'key' => sanitize_text_field($key),
                    'product_id' => absint($item['product_id']),
                    'variation_id' => isset($item['variation_id']) ? absint($item['variation_id']) : 0,
                    'quantity' => isset($item['quantity']) ? absint($item['quantity']) : 1
                );
                
                // Gérer les variations et attributs spécifiques s'ils existent
                if (isset($item['variation']) && is_array($item['variation'])) {
                    $sanitized_item['variation'] = array_map('sanitize_text_field', $item['variation']);
                }
                
                // Ajouter les métadonnées d'excursion spécifiques (dates, participants)
                if (isset($item['participants'])) {
                    $sanitized_item['participants'] = absint($item['participants']);
                }
                
                if (isset($item['start_date'])) {
                    $start_date = sanitize_text_field($item['start_date']);
                    // Valider que c'est bien une date
                    if (strtotime($start_date)) {
                        $sanitized_item['start_date'] = $start_date;
                    }
                }
                
                if (isset($item['end_date'])) {
                    $end_date = sanitize_text_field($item['end_date']);
                    // Valider que c'est bien une date
                    if (strtotime($end_date)) {
                        $sanitized_item['end_date'] = $end_date;
                    }
                }
                
                // Inclure tout autre métadonnée spécifique importante
                foreach (['line_total', 'line_tax', 'line_subtotal', 'line_subtotal_tax'] as $price_key) {
                    if (isset($item[$price_key])) {
                        $sanitized_item[$price_key] = (float) $item[$price_key];
                    }
                }
                
                $sanitized_cart[$key] = $sanitized_item;
            }
            
            // Assurer qu'il reste au moins un article après assainissement
            if (empty($sanitized_cart)) {
                throw new Exception('No valid items in cart after sanitization');
            }
            
            // 4. Préparer les données pour la base de données
            
            // Obtenir l'heure actuelle au format GMT
            $now = current_time('mysql', true); 
            
            $cart_data = array(
                'user_id' => absint($user_id),
                'email' => $email,
                'cart_contents' => $sanitized_cart,
                'cart_total' => (float) WC()->cart->get_cart_contents_total(),
                'currency' => sanitize_text_field(get_woocommerce_currency()),
                'last_updated' => $now,
                'recovered' => 0,
                'reminder_sent' => 0,
                'browser_data' => $this->get_browser_data(), // Information supplémentaire sur l'utilisateur
                'offline_recovery' => 0
            );
            
            // 5. Mise à jour ou insertion en base de données
            
            global $wpdb;
            
            // Vérifier si un panier abandonné existe déjà pour cet utilisateur ou cet email
            $existing_cart_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_name} 
                 WHERE email = %s AND recovered = 0 
                 ORDER BY last_updated DESC LIMIT 1",
                $cart_data['email']
            ));
            
            // Insert ou update avec sérialisation sécurisée
            if ($existing_cart_id) {
                // Mettre à jour le panier existant
                $result = $wpdb->update(
                    $this->table_name,
                    array(
                        'user_id' => $cart_data['user_id'],
                        'cart_contents' => maybe_serialize($cart_data['cart_contents']),
                        'cart_total' => $cart_data['cart_total'],
                        'currency' => $cart_data['currency'],
                        'last_updated' => $cart_data['last_updated'],
                        'browser_data' => maybe_serialize($cart_data['browser_data'])
                    ),
                    array('id' => $existing_cart_id),
                    array('%d', '%s', '%f', '%s', '%s', '%s'), // Formats des données
                    array('%d') // Format de la clause WHERE
                );
                
                // Mettre à jour notre cookie de suivi
                $this->set_tracking_cookie($existing_cart_id, $email);
                
                return $result !== false ? $existing_cart_id : false;
            } else {
                // Créer un nouveau panier
                $result = $wpdb->insert(
                    $this->table_name,
                    array(
                        'user_id' => $cart_data['user_id'],
                        'email' => $cart_data['email'],
                        'cart_contents' => maybe_serialize($cart_data['cart_contents']),
                        'cart_total' => $cart_data['cart_total'],
                        'currency' => $cart_data['currency'],
                        'created_at' => $now,
                        'last_updated' => $now,
                        'recovered' => 0,
                        'reminder_sent' => 0,
                        'browser_data' => maybe_serialize($cart_data['browser_data']),
                        'offline_recovery' => 0
                    ),
                    array('%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%d', '%s', '%d') // Formats des données
                );
                
                if ($result !== false) {
                    $cart_id = $wpdb->insert_id;
                    // Mettre à jour notre cookie de suivi
                    $this->set_tracking_cookie($cart_id, $email);
                    return $cart_id;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            // Journaliser l'erreur pour débogage
            error_log('Life Travel Abandoned Cart Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Définit le cookie de suivi pour les paniers abandonnés
     * 
     * @param int $cart_id ID du panier abandonné
     * @param string $email Email associé au panier
     */
    private function set_tracking_cookie($cart_id, $email) {
        // Ne rien faire si les cookies ne sont pas acceptés
        if (headers_sent()) {
            return;
        }
        
        // Crypter les données du cookie
        $data = base64_encode(json_encode([
            'id' => $cart_id,
            'email' => $email,
            'timestamp' => time()
        ]));
        
        // Définir le cookie avec httponly et secure si disponible
        setcookie(
            'life_travel_abandoned_cart',
            $data,
            time() + $this->cookie_lifetime,
            LIFE_TRAVEL_COOKIE_PATH,
            LIFE_TRAVEL_COOKIE_DOMAIN,
            LIFE_TRAVEL_SECURE_COOKIE,
            true // HttpOnly
        );
    }
    
    /**
     * Récupère des informations sur le navigateur et l'environnement de l'utilisateur
     * 
     * @return array Données sur le navigateur et l'environnement
     */
    private function get_browser_data() {
        $data = array(
            'ip' => $this->get_anonymized_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'referrer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '',
            'timestamp' => time(),
            'device' => wp_is_mobile() ? 'mobile' : 'desktop'
        );
        
        return $data;
    }
    
    /**
     * Récupère l'adresse IP anonymisée de l'utilisateur (conforme RGPD)
     * 
     * @return string IP anonymisée
     */
    private function get_anonymized_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Anonymiser l'IP en supprimant le dernier octet (IPv4) ou les derniers 64 bits (IPv6)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: remplacer le dernier octet par 0
            $anonymized_ip = preg_replace('/\d+$/', '0', $ip);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: conserver uniquement les 64 premiers bits
            $segments = explode(':', $ip);
            $anonymized_segments = array_slice($segments, 0, 4);
            $anonymized_ip = implode(':', $anonymized_segments) . ':0:0:0:0';
        } else {
            $anonymized_ip = '';
        }
        
        return $anonymized_ip;
    }
    
    /**
     * Synchronise les paniers abandonnés depuis le service worker (pendant la reprise de connexion)
     */
    public function sync_abandoned_cart() {
        try {
            // 1. Vérification de sécurité CSRF
            if (!check_ajax_referer('life_travel_sync_cart', 'security', false)) {
                throw new Exception('Invalid security token');
            }
            
            // 2. Vérification et assainissement des données d'entrée
            if (!isset($_POST['cart_data'])) {
                throw new Exception('Missing cart data');
            }
            
            // Décoder et valider les données JSON de manière sécurisée
            // Utiliser wp_unslash plutôt que stripslashes pour éviter double-processing
            $cart_data_json = wp_unslash($_POST['cart_data']);
            $cart_data = json_decode($cart_data_json, true);
            
            // Vérifier si le JSON est valide
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON: ' . json_last_error_msg());
            }
            
            // Vérifier la structure de base des données
            if (!is_array($cart_data)) {
                throw new Exception('Cart data must be an array');
            }
            
            // 3. Validation de l'email
            if (empty($cart_data['email'])) {
                throw new Exception('Email is required');
            }
            
            $email = sanitize_email($cart_data['email']);
            if (!is_email($email)) {
                throw new Exception('Invalid email format');
            }
            
            // 4. Préparer et assainir les données du panier
            
            // Extraire et valider les champs de base
            $user_id = get_current_user_id();
            $cart_total = isset($cart_data['cart_total']) ? (float) $cart_data['cart_total'] : 0.0;
            $currency = isset($cart_data['currency']) && !empty($cart_data['currency']) 
                ? sanitize_text_field($cart_data['currency']) 
                : get_woocommerce_currency();
            
            // Traitement du contenu du panier selon le format
            $cart_contents = array();
            
            if (isset($cart_data['cart_contents']) && is_array($cart_data['cart_contents'])) {
                // Format standard de panier WooCommerce
                foreach ($cart_data['cart_contents'] as $key => $item) {
                    if (!is_array($item) || !isset($item['product_id'])) {
                        continue;
                    }
                    
                    $product_id = absint($item['product_id']);
                    if (!$product_id) continue;
                    
                    $sanitized_item = array(
                        'product_id' => $product_id,
                        'quantity' => isset($item['quantity']) ? absint($item['quantity']) : 1
                    );
                    
                    // Gérer les attributs de variation
                    if (isset($item['variation_id'])) {
                        $sanitized_item['variation_id'] = absint($item['variation_id']);
                    }
                    
                    if (isset($item['variation']) && is_array($item['variation'])) {
                        $sanitized_item['variation'] = array_map('sanitize_text_field', $item['variation']);
                    }
                    
                    // Gérer les métadonnées des excursions
                    if (isset($item['participants'])) {
                        $sanitized_item['participants'] = absint($item['participants']);
                    }
                    
                    if (isset($item['start_date']) && strtotime($item['start_date'])) {
                        $sanitized_item['start_date'] = sanitize_text_field($item['start_date']);
                    }
                    
                    if (isset($item['end_date']) && strtotime($item['end_date'])) {
                        $sanitized_item['end_date'] = sanitize_text_field($item['end_date']);
                    }
                    
                    $cart_key = sanitize_key($key) ?: md5($product_id . time() . mt_rand());
                    $cart_contents[$cart_key] = $sanitized_item;
                }
            } elseif (isset($cart_data['product_id']) && absint($cart_data['product_id']) > 0) {
                // Format simplifié pour un seul produit
                $product_id = absint($cart_data['product_id']);
                if (get_post($product_id)) {
                    $cart_key = md5($product_id . time() . mt_rand());
                    $cart_item = array(
                        'product_id' => $product_id,
                        'quantity' => isset($cart_data['quantity']) ? absint($cart_data['quantity']) : 1
                    );
                    
                    // Ajouter les champs spécifiques si présents
                    if (isset($cart_data['participants'])) {
                        $cart_item['participants'] = absint($cart_data['participants']);
                    }
                    
                    if (isset($cart_data['start_date']) && strtotime($cart_data['start_date'])) {
                        $cart_item['start_date'] = sanitize_text_field($cart_data['start_date']);
                    }
                    
                    if (isset($cart_data['end_date']) && strtotime($cart_data['end_date'])) {
                        $cart_item['end_date'] = sanitize_text_field($cart_data['end_date']);
                    }
                    
                    $cart_contents[$cart_key] = $cart_item;
                }
            }
            
            // Vérifier que le panier a un contenu valide après assainissement
            if (empty($cart_contents)) {
                throw new Exception('Empty cart after sanitization');
            }
            
            // 5. Préparation pour la base de données
            
            // Informations sur l'appareil et le contexte
            $browser_data = $this->get_browser_data();
            
            // Obtenir l'heure actuelle au format GMT
            $current_time = current_time('mysql', true);
            
            // 6. Vérifier l'existence d'un panier abandonné pour cet email
            global $wpdb;
            $existing_cart_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_name} 
                 WHERE email = %s AND recovered = 0 
                 ORDER BY last_updated DESC LIMIT 1",
                $email
            ));
            
            // 7. Mettre à jour ou insérer le panier
            if ($existing_cart_id) {
                // Mettre à jour un panier existant avec formats de données spécifiés
                $result = $wpdb->update(
                    $this->table_name,
                    array(
                        'cart_contents' => maybe_serialize($cart_contents),
                        'cart_total' => $cart_total,
                        'currency' => $currency,
                        'last_updated' => $current_time,
                        'offline_recovery' => 1
                    ),
                    array('id' => $existing_cart_id),
                    array('%s', '%f', '%s', '%s', '%d'), // Formats des données
                    array('%d') // Format de WHERE
                );
                
                $cart_id = $existing_cart_id;
            } else {
                // Créer un nouveau panier avec formats de données spécifiés
                $result = $wpdb->insert(
                    $this->table_name,
                    array(
                        'user_id' => $user_id,
                        'email' => $email,
                        'cart_contents' => maybe_serialize($cart_contents),
                        'cart_total' => $cart_total,
                        'currency' => $currency,
                        'created_at' => $current_time,
                        'last_updated' => $current_time,
                        'recovered' => 0,
                        'reminder_sent' => 0,
                        'offline_recovery' => 1
                    ),
                    array('%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%d', '%d') // Formats des données
                );
                
                $cart_id = $wpdb->insert_id;
            }
            
            // 8. Réponse de succès
            if ($result !== false) {
                // Mettre à jour le cookie de suivi
                $this->set_tracking_cookie($cart_id, $email);
                
                wp_send_json_success([
                    'message' => __('Panier synchronisé avec succès', 'life-travel-excursion'),
                    'cart_id' => $cart_id,
                    'recovered' => $existing_cart_id ? true : false
                ]);
            } else {
                throw new Exception('Failed to save cart data: ' . $wpdb->last_error);
            }
            
        } catch (Exception $e) {
            // Journaliser l'erreur
            $error_message = 'Sync abandoned cart error: ' . $e->getMessage();
            life_travel_log_security_issue($error_message, 'sync_error');
            
            // Renvoyer une réponse d'erreur
            wp_send_json_error([
                'message' => __('Erreur lors de la synchronisation du panier', 'life-travel-excursion'),
                'error' => (defined('WP_DEBUG') && WP_DEBUG) ? $e->getMessage() : null // Montrer le détail uniquement en mode debug
            ]);
            exit;
        }
        exit;
    }
    
    /**
     * Envoie des emails de récupération pour les paniers abandonnés
     */
    public function send_recovery_emails() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Récupérer les paniers abandonnés depuis plus de 3 heures mais moins de 24 heures
        // et pour lesquels aucun rappel n'a été envoyé
        $abandoned_carts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE recovered = 0 
            AND reminder_sent = 0 
            AND last_updated < %s 
            AND last_updated > %s",
            date('Y-m-d H:i:s', strtotime('-3 hours')),
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
        
        if (empty($abandoned_carts)) {
            return;
        }
        
        foreach ($abandoned_carts as $cart) {
            // Récupérer les détails du panier
            $cart_contents = maybe_unserialize($cart->cart_contents);
            if (empty($cart_contents)) {
                continue;
            }
            
            // Informations pour l'email
            $user_email = $cart->email;
            $recovery_url = add_query_arg(array(
                'life_travel_recover' => base64_encode($cart->id . '|' . $user_email),
            ), wc_get_page_permalink('checkout'));
            
            // Construire le contenu de l'email
            $email_subject = __('Votre excursion vous attend !', 'life-travel-excursion');
            
            // Récupérer des informations sur les produits dans le panier
            $products_html = '';
            
            if (isset($cart_contents['product_id'])) {
                // Cas spécial de récupération hors ligne
                $product = wc_get_product($cart_contents['product_id']);
                if ($product) {
                    $products_html .= '<li>' . $product->get_name() . ' - ' . 
                        $cart_contents['participants'] . ' personne(s) - ' . 
                        $cart_contents['start_date'] . '</li>';
                }
            } else {
                // Cas normal de panier WooCommerce
                foreach ($cart_contents as $item_key => $item) {
                    $product_id = $item['product_id'];
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $products_html .= '<li>' . $product->get_name() . '</li>';
                    }
                }
            }
            
            $email_body = '
            <p>' . __('Bonjour,', 'life-travel-excursion') . '</p>
            <p>' . __('Nous avons remarqué que vous avez commencé à réserver une excursion sur notre site mais n\'avez pas finalisé votre commande.', 'life-travel-excursion') . '</p>
            <p>' . __('Voici un récapitulatif de votre sélection :', 'life-travel-excursion') . '</p>
            <ul>
                ' . $products_html . '
            </ul>
            <p>' . __('Pour finaliser votre réservation, il vous suffit de cliquer sur le bouton ci-dessous :', 'life-travel-excursion') . '</p>
            <p style="text-align: center;">
                <a href="' . esc_url($recovery_url) . '" style="background-color: #4CAF50; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">
                    ' . __('Terminer ma réservation', 'life-travel-excursion') . '
                </a>
            </p>
            <p>' . __('Cette offre est valable pour les 24 prochaines heures.', 'life-travel-excursion') . '</p>
            <p>' . __('Si vous avez des questions, n\'hésitez pas à nous contacter.', 'life-travel-excursion') . '</p>
            <p>' . __('Cordialement,', 'life-travel-excursion') . '</p>
            <p>' . __('L\'équipe Life Travel', 'life-travel-excursion') . '</p>
            ';
            
            // Envoyer l'email
            $headers = array('Content-Type: text/html; charset=UTF-8');
            $email_sent = wp_mail($user_email, $email_subject, $email_body, $headers);
            
            // Mettre à jour le statut du rappel
            if ($email_sent) {
                $wpdb->update(
                    $table_name,
                    array('reminder_sent' => 1),
                    array('id' => $cart->id)
                );
            }
        }
    }
    
    /**
     * Restaure un panier abandonné et redirige vers le checkout
     * 
     * @param int $cart_id
     * @param string $email
     * @return void
     */
    public function restore_abandoned_cart($cart_id, $email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Récupérer le panier non encore récupéré
        $cart = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND email = %s AND recovered = 0",
            $cart_id,
            $email
        ));
        
        if (!$cart) {
            return;
        }
        
        // Récupérer le contenu du panier
        $cart_contents = maybe_unserialize($cart->cart_contents);
        if (empty($cart_contents)) {
            return;
        }
        
        // Vider le panier actuel et reconstituer
        if (function_exists('WC') && WC()->cart) {
            WC()->cart->empty_cart();
            
            if (isset($cart_contents['product_id'])) {
                // Cas spécial de récupération hors ligne
                $product_id = $cart_contents['product_id'];
                $participants = $cart_contents['participants'] ?? 1;
                $start_date = $cart_contents['start_date'] ?? '';
                
                $cart_item_data = array(
                    'participants' => absint($participants),
                    'start_date' => sanitize_text_field($start_date),
                    'unique_key' => md5(microtime() . rand()),
                );
                
                WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
            } else {
                // Cas normal de panier WooCommerce
                foreach ($cart_contents as $item) {
                    if (empty($item['product_id'])) {
                        continue;
                    }
                    
                    WC()->cart->add_to_cart(
                        absint($item['product_id']),
                        isset($item['quantity']) ? absint($item['quantity']) : 1,
                        isset($item['variation_id']) ? absint($item['variation_id']) : 0,
                        isset($item['variation']) && is_array($item['variation']) ? $item['variation'] : array(),
                        is_array($item) ? $item : array()
                    );
                }
            }
        }
        
        // Marquer le panier comme récupéré
        $wpdb->update(
            $table_name,
            array('recovered' => 1),
            array('id' => $cart_id)
        );
        
        // Rediriger vers le checkout
        if (function_exists('wc_get_checkout_url')) {
            wp_redirect(wc_get_checkout_url());
            exit;
        }
    }
    
    /**
     * Crée la table pour stocker les paniers abandonnés
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT 0,
            email varchar(100) NOT NULL,
            cart_contents longtext NOT NULL,
            cart_total decimal(10,2) NOT NULL,
            currency varchar(10) NOT NULL,
            created_at datetime NOT NULL,
            last_updated datetime NOT NULL,
            recovered tinyint(1) NOT NULL DEFAULT 0,
            reminder_sent tinyint(1) NOT NULL DEFAULT 0,
            offline_recovery tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY email (email),
            KEY recovered (recovered)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialiser la classe (et l'exposer globalement)
$GLOBALS['life_travel_abandoned_cart'] = new Life_Travel_Abandoned_Cart();

// Fonction pour récupérer un panier abandonné
function life_travel_recover_abandoned_cart() {
    if (!isset($_GET['life_travel_recover'])) {
        return;
    }
    
    // Vérifier si le paramètre de récupération est valide
    $recovery_param = sanitize_text_field($_GET['life_travel_recover']);
    $recovery_data = explode('|', base64_decode($recovery_param));
    if (count($recovery_data) !== 2) {
        wp_die(esc_html__('Lien de récupération invalide.', 'life-travel-excursion'), esc_html__('Erreur', 'life-travel-excursion'), array('response' => 403));
        return;
    }
    
    // Assainir les données de récupération
    $cart_id = absint($recovery_data[0]);
    $email = sanitize_email($recovery_data[1]);
    
    // Vérifier le nonce de récupération
    if (isset($_GET['recovery_nonce'])) {
        $nonce = sanitize_text_field($_GET['recovery_nonce']);
        if (!wp_verify_nonce($nonce, 'life_travel_recover_cart_' . $cart_id)) {
            wp_die(esc_html__('Lien de récupération expiré ou non valide.', 'life-travel-excursion'), esc_html__('Erreur de sécurité', 'life-travel-excursion'), array('response' => 403));
            return;
        }
    }
    
    // Déléguer à l'instance de la classe si disponible
    if (isset($GLOBALS['life_travel_abandoned_cart']) && $GLOBALS['life_travel_abandoned_cart'] instanceof Life_Travel_Abandoned_Cart) {
        $GLOBALS['life_travel_abandoned_cart']->restore_abandoned_cart($cart_id, $email);
    }
    return;
}
add_action('wp', 'life_travel_recover_abandoned_cart');

/**
 * Synchronise un panier depuis des données (utilisé par offline-bridge)
 *
 * @param array $cart_data
 * @return bool Succès de la synchronisation
 */
function life_travel_sync_cart($cart_data) {
    try {
        if (!is_array($cart_data)) {
            return false;
        }
        
        // 1. Validation email
        $email = isset($cart_data['email']) ? sanitize_email($cart_data['email']) : '';
        if (empty($email) || !is_email($email)) {
            return false;
        }
        
        // 2. Préparer données de base
        $user_id = get_current_user_id();
        $cart_total = isset($cart_data['cart_total']) ? (float) $cart_data['cart_total'] : 0.0;
        $currency = isset($cart_data['currency']) && !empty($cart_data['currency'])
            ? sanitize_text_field($cart_data['currency'])
            : (function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD');
        
        // 3. Construire le contenu du panier
        $cart_contents = array();
        if (isset($cart_data['cart_contents']) && is_array($cart_data['cart_contents'])) {
            foreach ($cart_data['cart_contents'] as $key => $item) {
                if (!is_array($item) || !isset($item['product_id'])) {
                    continue;
                }
                $product_id = absint($item['product_id']);
                if (!$product_id) continue;
                
                $sanitized_item = array(
                    'product_id' => $product_id,
                    'quantity' => isset($item['quantity']) ? absint($item['quantity']) : 1,
                    'variation_id' => isset($item['variation_id']) ? absint($item['variation_id']) : 0,
                );
                if (isset($item['variation']) && is_array($item['variation'])) {
                    $sanitized_item['variation'] = array_map('sanitize_text_field', $item['variation']);
                }
                if (isset($item['participants'])) {
                    $sanitized_item['participants'] = absint($item['participants']);
                }
                if (isset($item['start_date']) && strtotime($item['start_date'])) {
                    $sanitized_item['start_date'] = sanitize_text_field($item['start_date']);
                }
                if (isset($item['end_date']) && strtotime($item['end_date'])) {
                    $sanitized_item['end_date'] = sanitize_text_field($item['end_date']);
                }
                $cart_key = sanitize_key((string) $key) ?: md5($product_id . time() . mt_rand());
                $cart_contents[$cart_key] = $sanitized_item;
            }
        } elseif (isset($cart_data['product_id']) && absint($cart_data['product_id']) > 0) {
            $product_id = absint($cart_data['product_id']);
            if (get_post($product_id)) {
                $cart_key = md5($product_id . time() . mt_rand());
                $cart_item = array(
                    'product_id' => $product_id,
                    'quantity' => isset($cart_data['quantity']) ? absint($cart_data['quantity']) : 1,
                );
                if (isset($cart_data['participants'])) {
                    $cart_item['participants'] = absint($cart_data['participants']);
                }
                if (isset($cart_data['start_date']) && strtotime($cart_data['start_date'])) {
                    $cart_item['start_date'] = sanitize_text_field($cart_data['start_date']);
                }
                if (isset($cart_data['end_date']) && strtotime($cart_data['end_date'])) {
                    $cart_item['end_date'] = sanitize_text_field($cart_data['end_date']);
                }
                $cart_contents[$cart_key] = $cart_item;
            }
        }
        
        if (empty($cart_contents)) {
            return false;
        }
        
        // 4. Persistance
        global $wpdb;
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        $current_time = current_time('mysql', true);
        
        $existing_cart_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE email = %s AND recovered = 0 ORDER BY last_updated DESC LIMIT 1",
            $email
        ));
        
        if ($existing_cart_id) {
            $result = $wpdb->update(
                $table_name,
                array(
                    'cart_contents' => maybe_serialize($cart_contents),
                    'cart_total' => $cart_total,
                    'currency' => $currency,
                    'last_updated' => $current_time,
                    'offline_recovery' => 1
                ),
                array('id' => $existing_cart_id)
            );
            return $result !== false;
        } else {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'email' => $email,
                    'cart_contents' => maybe_serialize($cart_contents),
                    'cart_total' => $cart_total,
                    'currency' => $currency,
                    'created_at' => $current_time,
                    'last_updated' => $current_time,
                    'recovered' => 0,
                    'reminder_sent' => 0,
                    'offline_recovery' => 1
                )
            );
            return $result !== false;
        }
    } catch (Exception $e) {
        return false;
    }
}
