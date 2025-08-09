<?php
/**
 * Gestion des requêtes AJAX pour l'authentification
 * 
 * @package Life_Travel_Excursion
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class Life_Travel_Authentication_Ajax {
    /**
     * Instance de la classe
     */
    private static $instance = null;
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Enregistrer les handlers AJAX
        add_action('wp_ajax_nopriv_lte_send_auth_code', [$this, 'ajax_send_auth_code']);
        add_action('wp_ajax_nopriv_lte_verify_auth_code', [$this, 'ajax_verify_auth_code']);
        add_action('wp_ajax_lte_2fa_setup', [$this, 'ajax_setup_2fa']);
        add_action('wp_ajax_lte_2fa_verify', [$this, 'ajax_verify_2fa']);
        add_action('wp_ajax_lte_toggle_2fa', [$this, 'ajax_toggle_2fa']);
    }
    
    /**
     * Récupère l'instance unique
     * 
     * @return Life_Travel_Authentication_Ajax
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Génère un code aléatoire à 6 chiffres
     * 
     * @return string
     */
    private function generate_otp_code() {
        return sprintf('%06d', mt_rand(0, 999999));
    }
    
    /**
     * Envoie un code par email
     * 
     * @param string $email Adresse email du destinataire
     * @param string $code Code à envoyer
     * @return bool Succès ou échec
     */
    private function send_email_code($email, $code) {
        $subject = __('Votre code de connexion Life Travel', 'life-travel-excursion');
        
        $message = sprintf(
            /* translators: %1$s: code, %2$d: expiration minutes */
            __('Votre code de connexion est : %1$s. Ce code est valable pendant %2$d minutes.', 'life-travel-excursion'),
            $code,
            ceil(get_option('lte_otp_expiry', 600) / 60)
        );
        
        $message .= "\n\n";
        $message .= __('Si vous n\'avez pas demandé ce code, vous pouvez ignorer cet email.', 'life-travel-excursion');
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        // Log la tentative en mode debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('Tentative d\'envoi de code OTP à %s: %s', $email, $code));
        }
        
        return wp_mail($email, $subject, $message, $headers);
    }
    
    /**
     * Envoie un code par SMS via Twilio
     * 
     * @param string $phone Numéro de téléphone du destinataire
     * @param string $code Code à envoyer
     * @return bool Succès ou échec
     */
    private function send_sms_code($phone, $code) {
        // Formater le numéro de téléphone pour s'assurer qu'il est au format E.164
        $phone = $this->format_phone_number($phone);
        
        // Vérifier si un plugin Twilio WordPress est activé
        if ($this->is_twilio_plugin_active()) {
            $result = $this->send_sms_via_wp_plugin($phone, $code);
            if ($result) {
                return true;
            }
            // Si échec avec le plugin, continuer avec notre propre implémentation
        }
        
        // Vérifier si Twilio est configuré
        $twilio_sid = get_option('lte_twilio_sid', '');
        $twilio_token = get_option('lte_twilio_token', '');
        $twilio_phone = get_option('lte_twilio_phone', '');
        
        if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_phone)) {
            // Fallback au mode de secours email
            return $this->send_code_via_fallback($phone, $code);
        }
        
        // Vérifier si la bibliothèque Twilio est disponible
        if (!$this->load_twilio_sdk()) {
            // Fallback au mode de secours
            return $this->send_code_via_fallback($phone, $code);
        }
        
        try {
            // Formater le message
            $message = sprintf(
                /* translators: %1$s: code, %2$d: expiration minutes */
                __('Votre code Life Travel est: %1$s. Valable %2$d minutes.', 'life-travel-excursion'),
                $code,
                ceil(get_option('lte_otp_expiry', 600) / 60)
            );
            
            // Initialiser le client Twilio
            $client = new \Twilio\Rest\Client($twilio_sid, $twilio_token);
            
            // Envoyer le SMS
            $result = $client->messages->create(
                $phone, // Numéro du destinataire
                [
                    'from' => $twilio_phone, // Numéro Twilio
                    'body' => $message
                ]
            );
            
            // Log le résultat en mode debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('Envoi SMS via Twilio à %s: %s', $phone, $result->sid));
            }
            
            return true;
        } catch (\Exception $e) {
            // Log l'erreur
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('Erreur Twilio: %s', $e->getMessage()));
            }
            
            // Fallback au mode de secours
            return $this->send_code_via_fallback($phone, $code);
        }
    }
    
    /**
     * Formate un numéro de téléphone au format E.164
     * 
     * @param string $phone Numéro de téléphone à formater
     * @return string Numéro formaté
     */
    private function format_phone_number($phone) {
        // Supprimer tous les caractères non numériques sauf le +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // S'assurer que le numéro commence par +
        if (substr($phone, 0, 1) !== '+') {
            // Si c'est un numéro camerounais sans indicatif
            if (strlen($phone) === 9 && (substr($phone, 0, 1) === '6' || substr($phone, 0, 1) === '2')) {
                $phone = '+237' . $phone;
            } else {
                $phone = '+' . $phone;
            }
        }
        
        return $phone;
    }
    
    /**
     * Vérifie si un plugin Twilio pour WordPress est activé
     * 
     * @return bool
     */
    private function is_twilio_plugin_active() {
        // Vérifier les plugins Twilio courants
        $twilio_plugins = [
            'twilio-core/twilio.php',
            'twilio-sms/twilio-sms.php',
            'wp-twilio-core/twilio.php', 
            'wpt-twilio-integration/twilio-integration.php'
        ];
        
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        foreach ($twilio_plugins as $plugin) {
            if (function_exists('is_plugin_active') && is_plugin_active($plugin)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Envoie un SMS en utilisant un plugin Twilio WordPress
     * 
     * @param string $phone Numéro de téléphone du destinataire
     * @param string $code Code à envoyer
     * @return bool Succès ou échec
     */
    private function send_sms_via_wp_plugin($phone, $code) {
        // Formater le message
        $message = sprintf(
            /* translators: %1$s: code, %2$d: expiration minutes */
            __('Votre code Life Travel est: %1$s. Valable %2$d minutes.', 'life-travel-excursion'),
            $code,
            ceil(get_option('lte_otp_expiry', 600) / 60)
        );
        
        // WP Twilio Core
        if (function_exists('twl_send_sms')) {
            $result = twl_send_sms($phone, $message);
            return $result !== false;
        }
        
        // WPT Twilio Integration
        if (function_exists('wpt_send_sms')) {
            $result = wpt_send_sms($phone, $message);
            return $result !== false;
        }
        
        // Twilio SMS
        if (class_exists('\TwilioSMS') && method_exists('\TwilioSMS', 'send_message')) {
            $twilio_sms = new \TwilioSMS();
            $result = $twilio_sms->send_message($phone, $message);
            return $result !== false;
        }
        
        return false;
    }
    
    /**
     * Charge la bibliothèque Twilio SDK
     * 
     * @return bool Succès ou échec
     */
    private function load_twilio_sdk() {
        // Vérifier si la classe est déjà disponible
        if (class_exists('Twilio\\Rest\\Client')) {
            return true;
        }
        
        // Essayer de charger via notre autoloader Composer
        $composer_autoload = LIFE_TRAVEL_EXCURSION_DIR . 'vendor/autoload.php';
        if (file_exists($composer_autoload)) {
            require_once $composer_autoload;
            if (class_exists('Twilio\\Rest\\Client')) {
                return true;
            }
        }
        
        // Essayer de charger depuis des emplacements alternatifs courants
        $alt_locations = [
            ABSPATH . 'wp-content/plugins/twilio-core/includes/vendor/autoload.php',
            ABSPATH . 'wp-content/plugins/twilio-sms/vendor/autoload.php',
            WP_PLUGIN_DIR . '/twilio-core/includes/vendor/autoload.php',
            WP_PLUGIN_DIR . '/twilio-sms/vendor/autoload.php'
        ];
        
        foreach ($alt_locations as $file) {
            if (file_exists($file)) {
                require_once $file;
                if (class_exists('Twilio\\Rest\\Client')) {
                    return true;
                }
            }
        }
        
        // Essayer de charger la bibliothèque directement si elle existe
        if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'vendor/twilio/sdk/src/Twilio/autoload.php')) {
            require_once LIFE_TRAVEL_EXCURSION_DIR . 'vendor/twilio/sdk/src/Twilio/autoload.php';
            if (class_exists('Twilio\\Rest\\Client')) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Envoie un code via une méthode de secours
     * 
     * @param string $contact Email ou téléphone
     * @param string $code Code à envoyer
     * @return bool Succès ou échec
     */
    private function send_code_via_fallback($contact, $code) {
        $fallback_method = get_option('lte_sms_fallback_method', 'email');
        
        switch ($fallback_method) {
            case 'email_to_sms':
                // Envoyer via un service Email-to-SMS
                $carrier_domain = get_option('lte_email_to_sms_domain', '');
                
                if (empty($carrier_domain)) {
                    return false;
                }
                
                // Nettoyer le numéro de téléphone
                $clean_phone = preg_replace('/[^0-9]/', '', $contact);
                
                // Créer l'adresse email du service Email-to-SMS
                $email = $clean_phone . '@' . $carrier_domain;
                
                // Envoyer l'email (format court pour SMS)
                $subject = __('Code', 'life-travel-excursion');
                $message = sprintf(__('Code: %s', 'life-travel-excursion'), $code);
                $headers = ['Content-Type: text/plain; charset=UTF-8'];
                
                return wp_mail($email, $subject, $message, $headers);
                
            case 'api':
                // Envoyer via une API REST alternative
                $api_url = get_option('lte_sms_api_url', '');
                $api_key = get_option('lte_sms_api_key', '');
                
                if (empty($api_url) || empty($api_key)) {
                    return false;
                }
                
                // Préparer le message
                $message = sprintf(
                    /* translators: %s: code */
                    __('Votre code Life Travel est: %s.', 'life-travel-excursion'),
                    $code
                );
                
                // Appeler l'API
                $response = wp_remote_post($api_url, [
                    'body' => [
                        'apiKey' => $api_key,
                        'to' => $contact,
                        'message' => $message
                    ],
                    'timeout' => 45,
                    'headers' => [
                        'Content-Type' => 'application/json'
                    ]
                ]);
                
                return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
                
            case 'email':
            default:
                // Si le contact semble être un email
                if (is_email($contact)) {
                    return $this->send_email_code($contact, $code);
                }
                
                // Sinon, tenter de trouver un utilisateur avec ce numéro de téléphone
                $user = $this->get_user_by_phone($contact);
                
                if ($user && !empty($user->user_email)) {
                    return $this->send_email_code($user->user_email, $code);
                }
                
                return false;
        }
    }
    
    /**
     * Recherche un utilisateur par son numéro de téléphone
     * 
     * @param string $phone Numéro de téléphone
     * @return WP_User|false Utilisateur ou false si non trouvé
     */
    private function get_user_by_phone($phone) {
        // Nettoyer le numéro de téléphone
        $clean_phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Rechercher dans les meta données
        $users = get_users([
            'meta_key' => 'phone',
            'meta_value' => $clean_phone,
            'number' => 1,
            'count_total' => false
        ]);
        
        return !empty($users) ? $users[0] : false;
    }
    
    /**
     * Stocke un code OTP temporaire
     * 
     * @param string $identifier Email ou téléphone
     * @param string $code Code OTP
     * @param string $type Type de code (email, phone)
     * @return bool Succès ou échec
     */
    private function store_otp_code($identifier, $code, $type) {
        // Sécuriser l'identifiant pour éviter les injections
        $safe_identifier = sanitize_text_field($identifier);
        
        // Calculer le hachage du code (ne jamais stocker le code en clair)
        $code_hash = wp_hash_password($code);
        
        // Stocker dans une option temporaire
        $transient_name = 'lte_otp_' . md5($type . '_' . $safe_identifier);
        
        // Expiration du code
        $expiry = get_option('lte_otp_expiry', 600); // 10 minutes par défaut
        
        $otp_data = [
            'hash' => $code_hash,
            'attempts' => 0,
            'created' => time()
        ];
        
        return set_transient($transient_name, $otp_data, $expiry);
    }
    
    /**
     * Vérifie un code OTP
     * 
     * @param string $identifier Email ou téléphone
     * @param string $code Code OTP à vérifier
     * @param string $type Type de code (email, phone)
     * @return bool Validité du code
     */
    private function verify_otp_code($identifier, $code, $type) {
        // Sécuriser l'identifiant
        $safe_identifier = sanitize_text_field($identifier);
        
        // Récupérer les données du code
        $transient_name = 'lte_otp_' . md5($type . '_' . $safe_identifier);
        $otp_data = get_transient($transient_name);
        
        if (!$otp_data) {
            // Code expiré ou non existant
            return false;
        }
        
        // Incrémenter le compteur de tentatives
        $otp_data['attempts']++;
        
        // Limiter les tentatives à 5
        if ($otp_data['attempts'] >= 5) {
            // Supprimer le code après trop de tentatives
            delete_transient($transient_name);
            return false;
        }
        
        // Mettre à jour le compteur
        set_transient($transient_name, $otp_data, get_option('lte_otp_expiry', 600));
        
        // Vérifier si le code correspond
        return wp_check_password($code, $otp_data['hash']);
    }
    
    /**
     * Traite la demande d'envoi de code d'authentification
     */
    public function ajax_send_auth_code() {
        // Vérifier le nonce
        if (!check_ajax_referer('lte_auth_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Erreur de sécurité.', 'life-travel-excursion')]);
        }
        
        // Vérifier si l'utilisateur est bloqué
        if ($this->is_user_locked_out()) {
            wp_send_json_error(['message' => __('Trop de tentatives échouées. Veuillez réessayer plus tard.', 'life-travel-excursion')]);
        }
        
        // Récupérer les données
        $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';
        $identifier = isset($_POST['identifier']) ? sanitize_text_field($_POST['identifier']) : '';
        
        // Valider les données
        if (empty($method) || empty($identifier)) {
            wp_send_json_error(['message' => __('Données invalides.', 'life-travel-excursion')]);
        }
        
        // Valider le format selon la méthode
        if ($method === 'email' && !is_email($identifier)) {
            wp_send_json_error(['message' => __('Format d\'email invalide.', 'life-travel-excursion')]);
        } elseif ($method === 'phone' && !preg_match('/^\+?[0-9]{8,15}$/', $identifier)) {
            wp_send_json_error(['message' => __('Format de numéro de téléphone invalide.', 'life-travel-excursion')]);
        }
        
        // Générer un code
        $code = $this->generate_otp_code();
        
        // Stocker le code
        $stored = $this->store_otp_code($identifier, $code, $method);
        
        if (!$stored) {
            wp_send_json_error(['message' => __('Erreur lors du stockage du code.', 'life-travel-excursion')]);
        }
        
        // Envoyer le code
        $sent = false;
        if ($method === 'email') {
            $sent = $this->send_email_code($identifier, $code);
        } elseif ($method === 'phone') {
            $sent = $this->send_sms_code($identifier, $code);
        }
        
        if ($sent) {
            wp_send_json_success(['message' => __('Code envoyé avec succès.', 'life-travel-excursion')]);
        } else {
            wp_send_json_error(['message' => __('Erreur lors de l\'envoi du code.', 'life-travel-excursion')]);
        }
    }
    
    /**
     * Traite la vérification du code d'authentification
     */
    public function ajax_verify_auth_code() {
        // Vérifier le nonce
        if (!check_ajax_referer('lte_auth_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Erreur de sécurité.', 'life-travel-excursion')]);
        }
        
        // Vérifier si l'utilisateur est bloqué
        if ($this->is_user_locked_out()) {
            wp_send_json_error(['message' => __('Trop de tentatives échouées. Veuillez réessayer plus tard.', 'life-travel-excursion')]);
        }
        
        // Récupérer les données
        $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';
        $identifier = isset($_POST['identifier']) ? sanitize_text_field($_POST['identifier']) : '';
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : '';
        
        // Valider les données
        if (empty($method) || empty($identifier) || empty($code)) {
            wp_send_json_error(['message' => __('Données invalides.', 'life-travel-excursion')]);
        }
        
        // Vérifier le code
        $verified = $this->verify_otp_code($identifier, $code, $method);
        
        if (!$verified) {
            // Incrémenter le compteur d'échecs
            $this->increment_failed_attempts();
            
            wp_send_json_error(['message' => __('Code invalide ou expiré.', 'life-travel-excursion')]);
        }
        
        // Trouver ou créer l'utilisateur
        $user_id = $this->find_or_create_user($identifier, $method);
        
        if (!$user_id) {
            wp_send_json_error(['message' => __('Erreur lors de la création de l\'utilisateur.', 'life-travel-excursion')]);
        }
        
        // Connecter l'utilisateur
        $this->login_user($user_id);
        
        // Intégration avec récupération des paniers abandonnés
        $this->sync_abandoned_cart($user_id, $identifier);
        
        // Réinitialiser le compteur d'échecs
        $this->reset_failed_attempts();
        
        // Préparer l'URL de redirection
        if (empty($redirect_to)) {
            $redirect_to = home_url();
        }
        
        // Répondre avec succès
        wp_send_json_success([
            'message' => __('Authentification réussie.', 'life-travel-excursion'),
            'redirect' => $redirect_to
        ]);
    }
    
    /**
     * Trouve ou crée un utilisateur basé sur son identifiant
     * 
     * @param string $identifier Email ou téléphone
     * @param string $method Méthode d'authentification
     * @return int|false ID de l'utilisateur ou false en cas d'échec
     */
    private function find_or_create_user($identifier, $method) {
        $user_id = false;
        
        if ($method === 'email') {
            // Chercher par email
            $user = get_user_by('email', $identifier);
            
            if ($user) {
                $user_id = $user->ID;
            } else {
                // Créer un nouvel utilisateur
                $username = $this->generate_username_from_email($identifier);
                
                $user_id = wp_create_user(
                    $username,
                    wp_generate_password(20, true, true),
                    $identifier
                );
                
                if (!is_wp_error($user_id)) {
                    // Assigner le rôle client
                    $user = new WP_User($user_id);
                    $user->set_role('customer');
                    
                    // Envoyer notification de création de compte
                    $this->send_welcome_email($identifier);
                } else {
                    $user_id = false;
                }
            }
        } elseif ($method === 'phone') {
            // Chercher par téléphone dans la meta
            $user = $this->get_user_by_phone($identifier);
            
            if ($user) {
                $user_id = $user->ID;
            } else {
                // Créer un nouvel utilisateur avec un email temporaire
                $clean_phone = preg_replace('/[^0-9]/', '', $identifier);
                $temp_email = 'phone_' . $clean_phone . '@example.com';
                $username = 'user_' . $clean_phone;
                
                $user_id = wp_create_user(
                    $username,
                    wp_generate_password(20, true, true),
                    $temp_email
                );
                
                if (!is_wp_error($user_id)) {
                    // Ajouter le téléphone comme meta
                    update_user_meta($user_id, 'phone', $identifier);
                    
                    // Assigner le rôle client
                    $user = new WP_User($user_id);
                    $user->set_role('customer');
                    
                    // Envoyer SMS de bienvenue
                    $this->send_welcome_sms($identifier);
                } else {
                    $user_id = false;
                }
            }
        }
        
        return $user_id;
    }
    
    /**
     * Génère un nom d'utilisateur à partir d'un email
     * 
     * @param string $email Adresse email
     * @return string Nom d'utilisateur unique
     */
    private function generate_username_from_email($email) {
        $username = substr($email, 0, strpos($email, '@'));
        $username = sanitize_user($username, true);
        
        // Vérifier si le nom d'utilisateur existe déjà
        $suffix = 1;
        $temp_username = $username;
        
        while (username_exists($temp_username)) {
            $temp_username = $username . $suffix;
            $suffix++;
        }
        
        return $temp_username;
    }
    
    /**
     * Connecte l'utilisateur
     * 
     * @param int $user_id ID de l'utilisateur
     */
    private function login_user($user_id) {
        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        // Log de connexion
        $user = get_userdata($user_id);
        do_action('wp_login', $user->user_login, $user);
    }
    
    /**
     * Envoie un email de bienvenue aux nouveaux utilisateurs
     * 
     * @param string $email Adresse email
     */
    private function send_welcome_email($email) {
        $subject = __('Bienvenue sur Life Travel', 'life-travel-excursion');
        
        $message = __('Bienvenue sur Life Travel!', 'life-travel-excursion') . "\n\n";
        $message .= __('Votre compte a été créé avec succès. Vous pouvez désormais vous connecter avec votre adresse email et un code envoyé par email.', 'life-travel-excursion') . "\n\n";
        $message .= sprintf(__('Visiter le site: %s', 'life-travel-excursion'), home_url()) . "\n\n";
        $message .= __('À très bientôt!', 'life-travel-excursion') . "\n";
        $message .= get_bloginfo('name');
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        wp_mail($email, $subject, $message, $headers);
    }
    
    /**
     * Envoie un SMS de bienvenue aux nouveaux utilisateurs
     * 
     * @param string $phone Numéro de téléphone
     */
    private function send_welcome_sms($phone) {
        $message = __('Bienvenue sur Life Travel! Votre compte a été créé avec succès.', 'life-travel-excursion');
        
        // Utiliser l'envoi SMS simple
        $this->send_sms_code($phone, $message);
    }
    
    /**
     * Vérifie si l'utilisateur est bloqué
     * 
     * @return bool
     */
    private function is_user_locked_out() {
        $ip = $this->get_client_ip();
        $lockout = get_transient('lte_login_lockout_' . $ip);
        
        return $lockout !== false;
    }
    
    /**
     * Obtient l'adresse IP du client
     * 
     * @return string
     */
    private function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_array = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ip_array[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Sanitize IP
        $ip = filter_var($ip, FILTER_VALIDATE_IP);
        return $ip ?: '0.0.0.0';
    }
    
    /**
     * Incrémente le compteur de tentatives échouées
     */
    private function increment_failed_attempts() {
        $ip = $this->get_client_ip();
        $attempts = (int) get_transient('lte_failed_attempts_' . $ip);
        
        if ($attempts >= get_option('lte_max_login_attempts', 5) - 1) {
            // Bloquer l'utilisateur pour un certain temps
            set_transient(
                'lte_login_lockout_' . $ip,
                time() + get_option('lte_lockout_duration', 900),
                get_option('lte_lockout_duration', 900)
            );
            
            // Réinitialiser le compteur
            delete_transient('lte_failed_attempts_' . $ip);
        } else {
            // Incrémenter le compteur
            set_transient(
                'lte_failed_attempts_' . $ip,
                $attempts + 1,
                get_option('lte_lockout_duration', 900)
            );
        }
    }
    
    /**
     * Réinitialise le compteur de tentatives échouées
     */
    private function reset_failed_attempts() {
        $ip = $this->get_client_ip();
        delete_transient('lte_failed_attempts_' . $ip);
    }
    
    /**
     * Synchronise les paniers abandonnés après connexion
     * 
     * @param int $user_id ID de l'utilisateur
     * @param string $identifier Email ou téléphone
     */
    private function sync_abandoned_cart($user_id, $identifier) {
        // Récupérer le panier actuel avant la connexion
        $current_cart = WC()->session ? WC()->session->get('cart') : [];
        
        // Récupérer le panier sauvegardé pour l'utilisateur
        $saved_cart = get_user_meta($user_id, '_lte_saved_cart', true);
        
        // Si les deux paniers ont du contenu, tenter une fusion intelligente
        if (!empty($current_cart) && !empty($saved_cart)) {
            // Utiliser la méthode existante de sync de panier abandonné
            if (class_exists('Life_Travel_Site_Integration')) {
                $site_integration = Life_Travel_Site_Integration::get_instance();
                
                if (method_exists($site_integration, 'sync_abandoned_cart')) {
                    // Appeler la méthode existante qui gère la synchronisation
                    $site_integration->sync_abandoned_cart([
                        'user_id' => $user_id,
                        'cart_data' => $current_cart
                    ]);
                }
            }
        } else if (empty($current_cart) && !empty($saved_cart)) {
            // Si le panier actuel est vide mais qu'un panier sauvegardé existe
            if (WC()->session) {
                WC()->session->set('cart', $saved_cart);
            }
        } else if (!empty($current_cart) && empty($saved_cart)) {
            // Si le panier actuel a du contenu mais pas de panier sauvegardé
            update_user_meta($user_id, '_lte_saved_cart', $current_cart);
        }
    }
    
    /**
     * Traite la configuration de l'authentification à deux facteurs
     */
    public function ajax_setup_2fa() {
        // Vérifier le nonce
        check_ajax_referer('lte_2fa_setup_nonce', 'nonce');
        
        // Vérifier que l'utilisateur est connecté
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('Vous devez être connecté pour configurer l\'authentification à deux facteurs.', 'life-travel-excursion')
            ]);
            return;
        }
        
        // Vérifier les droits (administrateur uniquement)
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Vous n\'avez pas les permissions nécessaires pour configurer cette option.', 'life-travel-excursion')
            ]);
            return;
        }
        
        $user_id = get_current_user_id();
        $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : 'email';
        
        // Vérifier la méthode
        if (!in_array($method, ['email', 'phone'])) {
            wp_send_json_error([
                'message' => __('Méthode d\'authentification non valide.', 'life-travel-excursion')
            ]);
            return;
        }
        
        // Récupérer l'identifiant (email ou téléphone)
        $identifier = '';
        
        if ($method === 'email') {
            $user_info = get_userdata($user_id);
            $identifier = $user_info->user_email;
        } else {
            // Téléphone
            $phone = get_user_meta($user_id, '_lte_phone', true);
            
            if (empty($phone)) {
                wp_send_json_error([
                    'message' => __('Aucun numéro de téléphone n\'est associé à votre compte.', 'life-travel-excursion')
                ]);
                return;
            }
            
            $identifier = $phone;
        }
        
        // Générer un code
        $code = $this->generate_otp_code();
        
        // Stocker le code temporairement
        $stored = $this->store_otp_code($identifier, $code, '2fa_setup');
        
        if (!$stored) {
            wp_send_json_error([
                'message' => __('Impossible de générer un code de sécurité. Veuillez réessayer.', 'life-travel-excursion')
            ]);
            return;
        }
        
        // Envoyer le code
        $sent = false;
        
        if ($method === 'email') {
            $sent = $this->send_email_code($identifier, $code);
        } else {
            $sent = $this->send_sms_code($identifier, $code);
        }
        
        if (!$sent) {
            wp_send_json_error([
                'message' => __('Impossible d\'envoyer le code de sécurité. Veuillez réessayer.', 'life-travel-excursion')
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => sprintf(
                /* translators: %s: email or phone */
                __('Un code de vérification a été envoyé à %s.', 'life-travel-excursion'),
                $method === 'email' ? $identifier : substr($identifier, 0, 4) . '****' . substr($identifier, -2)
            )
        ]);
    }
    
    /**
     * Traite la vérification de l'authentification à deux facteurs
     */
    public function ajax_verify_2fa() {
        // Vérifier le nonce
        check_ajax_referer('lte_2fa_verify', 'nonce');
        
        // Vérifier les données requises
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        
        if (empty($code)) {
            wp_send_json_error([
                'message' => __('Code de vérification manquant.', 'life-travel-excursion')
            ]);
            return;
        }
        
        // Vérifier si nous sommes dans le contexte d'authentification admin
        if (isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
            // Contexte admin 2FA
            $user_id = absint($_POST['user_id']);
            $user = get_userdata($user_id);
            
            if (!$user) {
                wp_send_json_error([
                    'message' => __('Utilisateur invalide.', 'life-travel-excursion')
                ]);
                return;
            }
            
            // Vérifier la méthode de 2FA préférée
            $method = get_user_meta($user_id, '_lte_2fa_method', true);
            $method = empty($method) ? 'email' : $method;
            
            // Récupérer l'identifiant
            $identifier = '';
            
            if ($method === 'email') {
                $identifier = $user->user_email;
            } else {
                $identifier = get_user_meta($user_id, '_lte_phone', true);
            }
            
            // Vérifier le code
            $valid = $this->verify_otp_code($identifier, $code, '2fa_login');
            
            if (!$valid) {
                // Incrémenter le compteur de tentatives échouées
                $this->increment_failed_attempts();
                
                wp_send_json_error([
                    'message' => __('Code de vérification invalide.', 'life-travel-excursion')
                ]);
                return;
            }
            
            // Réinitialiser le compteur de tentatives échouées
            $this->reset_failed_attempts();
            
            // Marquer cette session comme vérifiée par 2FA
            $token = wp_generate_password(64, false);
            set_transient('lte_2fa_verified_' . $user_id, $token, 12 * HOUR_IN_SECONDS);
            
            // Définir un cookie pour cette session
            setcookie(
                'lte_2fa_token',
                $token,
                time() + 12 * HOUR_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
            
            wp_send_json_success([
                'message' => __('Vérification réussie.', 'life-travel-excursion')
            ]);
        } else {
            // Contexte de configuration 2FA
            if (!is_user_logged_in()) {
                wp_send_json_error([
                    'message' => __('Vous devez être connecté.', 'life-travel-excursion')
                ]);
                return;
            }
            
            $user_id = get_current_user_id();
            $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : 'email';
            
            // Récupérer l'identifiant
            $identifier = '';
            
            if ($method === 'email') {
                $user_info = get_userdata($user_id);
                $identifier = $user_info->user_email;
            } else {
                $identifier = get_user_meta($user_id, '_lte_phone', true);
            }
            
            // Vérifier le code
            $valid = $this->verify_otp_code($identifier, $code, '2fa_setup');
            
            if (!$valid) {
                wp_send_json_error([
                    'message' => __('Code de vérification invalide.', 'life-travel-excursion')
                ]);
                return;
            }
            
            // Activer la 2FA
            update_user_meta($user_id, '_lte_2fa_enabled', '1');
            update_user_meta($user_id, '_lte_2fa_method', $method);
            
            // Générer des codes de secours
            $backup_codes = [];
            for ($i = 0; $i < 5; $i++) {
                $backup_codes[] = strtoupper(substr(wp_generate_password(10, false), 0, 8));
            }
            
            update_user_meta($user_id, '_lte_2fa_backup_codes', $backup_codes);
            
            wp_send_json_success([
                'message' => __('L\'authentification à deux facteurs a été activée avec succès.', 'life-travel-excursion'),
                'backup_codes' => $backup_codes
            ]);
        }
    }
    
    /**
     * Active ou désactive l'authentification à deux facteurs
     */
    public function ajax_toggle_2fa() {
        // Vérifier le nonce
        check_ajax_referer('lte_toggle_2fa', 'nonce');
        
        // Vérifier que l'utilisateur est connecté
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('Vous devez être connecté pour modifier les paramètres d\'authentification.', 'life-travel-excursion')
            ]);
            return;
        }
        
        // Récupérer l'action (activer/désactiver)
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        
        if (!in_array($action, ['enable', 'disable'])) {
            wp_send_json_error([
                'message' => __('Action non valide.', 'life-travel-excursion')
            ]);
            return;
        }
        
        $user_id = get_current_user_id();
        
        if ($action === 'disable') {
            // Vérifier le mot de passe pour confirmation
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $user = get_user_by('id', $user_id);
            
            if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
                wp_send_json_error([
                    'message' => __('Mot de passe incorrect.', 'life-travel-excursion')
                ]);
                return;
            }
            
            // Désactiver la 2FA
            delete_user_meta($user_id, '_lte_2fa_enabled');
            delete_user_meta($user_id, '_lte_2fa_method');
            delete_user_meta($user_id, '_lte_2fa_backup_codes');
            
            wp_send_json_success([
                'message' => __('L\'authentification à deux facteurs a été désactivée.', 'life-travel-excursion')
            ]);
        } else {
            // Pour activer, nous devons d'abord envoyer un code
            // Cette action est traitée par ajax_setup_2fa et complétée par ajax_verify_2fa
            wp_send_json_error([
                'message' => __('Pour activer l\'authentification à deux facteurs, veuillez utiliser le processus de configuration.', 'life-travel-excursion')
            ]);
        }
    }
    
    /**
     * Envoie un code d'authentification à deux facteurs à un administrateur
     * 
     * @param int $user_id ID de l'utilisateur admin
     * @return bool Succès ou échec
     */
    public function send_admin_2fa_code($user_id) {
        // Vérifier si l'utilisateur existe et a la 2FA activée
        $two_factor_enabled = get_user_meta($user_id, '_lte_2fa_enabled', true);
        
        if (!$two_factor_enabled) {
            return false;
        }
        
        // Récupérer la méthode préférée
        $method = get_user_meta($user_id, '_lte_2fa_method', true);
        $method = empty($method) ? 'email' : $method;
        
        // Générer un code
        $code = $this->generate_otp_code();
        
        // Récupérer l'identifiant
        $identifier = '';
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }
        
        if ($method === 'email') {
            $identifier = $user->user_email;
        } else {
            $identifier = get_user_meta($user_id, '_lte_phone', true);
            if (empty($identifier)) {
                // Fallback à l'email si le téléphone n'est pas disponible
                $identifier = $user->user_email;
                $method = 'email';
            }
        }
        
        // Stocker le code
        $stored = $this->store_otp_code($identifier, $code, '2fa_login');
        
        if (!$stored) {
            return false;
        }
        
        // Envoyer le code
        $sent = false;
        
        if ($method === 'email') {
            $sent = $this->send_email_code($identifier, $code);
        } else {
            $sent = $this->send_sms_code($identifier, $code);
        }
        
        return $sent;
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_Authentication_Ajax::get_instance();
});
