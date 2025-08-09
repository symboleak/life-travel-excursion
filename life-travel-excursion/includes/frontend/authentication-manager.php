<?php
/**
 * Classe de gestion de l'authentification fluide et sécurisée
 * 
 * @package Life_Travel_Excursion
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class Life_Travel_Authentication_Manager {
    /**
     * Instance unique de la classe (pattern Singleton)
     */
    private static $instance = null;
    
    /**
     * Durée de validité du code OTP en secondes
     */
    private $otp_expiry = 600; // 10 minutes
    
    /**
     * Nombre maximum de tentatives de connexion
     */
    private $max_login_attempts = 5;
    
    /**
     * Durée du blocage après échec d'authentification (en secondes)
     */
    private $lockout_duration = 900; // 15 minutes
    
    /**
     * Constructeur privé pour le pattern Singleton
     */
    private function __construct() {
        // Enregistrer les hooks pour l'authentification
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Endpoints AJAX
        add_action('wp_ajax_nopriv_lte_send_auth_code', [$this, 'ajax_send_auth_code']);
        add_action('wp_ajax_nopriv_lte_verify_auth_code', [$this, 'ajax_verify_auth_code']);
        add_action('wp_ajax_lte_2fa_setup', [$this, 'ajax_setup_2fa']);
        add_action('wp_ajax_lte_2fa_verify', [$this, 'ajax_verify_2fa']);
        
        // Hooks pour la sécurité des administrateurs
        add_action('wp_login', [$this, 'check_admin_2fa'], 10, 2);
        
        // Personnalisation du formulaire de connexion
        add_filter('login_form_middle', [$this, 'add_auth_options_to_login']);
        
        // Intégration avec les récupérations de paniers abandonnés
        add_action('wp_login', [$this, 'sync_abandoned_cart_after_login'], 10, 2);
        
        // Observer les tentatives de connexion échouées
        add_action('wp_login_failed', [$this, 'track_failed_login']);
        
        // Charger les options
        $this->load_options();
    }
    
    /**
     * Charge les options depuis la configuration
     */
    private function load_options() {
        $this->otp_expiry = get_option('lte_otp_expiry', 600);
        $this->max_login_attempts = get_option('lte_max_login_attempts', 5);
        $this->lockout_duration = get_option('lte_lockout_duration', 900);
    }
    
    /**
     * Récupère l'instance unique de la classe
     * 
     * @return Life_Travel_Authentication_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Enregistre les shortcodes pour les formulaires d'authentification
     */
    public function register_shortcodes() {
        add_shortcode('lte_login', [$this, 'login_form_shortcode']);
        add_shortcode('lte_register', [$this, 'register_form_shortcode']);
        add_shortcode('lte_account', [$this, 'account_shortcode']);
    }
    
    /**
     * Enregistre les scripts et styles
     */
    public function enqueue_assets() {
        // Charger les scripts uniquement sur les pages où ils sont nécessaires
        if (is_page() && (has_shortcode(get_post()->post_content, 'lte_login') || 
            has_shortcode(get_post()->post_content, 'lte_register') ||
            has_shortcode(get_post()->post_content, 'lte_account'))) {
            
            wp_enqueue_style(
                'lte-authentication',
                LIFE_TRAVEL_EXCURSION_URL . 'assets/css/authentication.css',
                [],
                LIFE_TRAVEL_EXCURSION_VERSION
            );
            
            wp_enqueue_script(
                'lte-authentication',
                LIFE_TRAVEL_EXCURSION_URL . 'assets/js/authentication.js',
                ['jquery'],
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
            
            // Localisation pour JavaScript
            wp_localize_script('lte-authentication', 'lteAuth', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lte_auth_nonce'),
                'i18n' => [
                    'enter_email' => __('Veuillez entrer une adresse email valide.', 'life-travel-excursion'),
                    'enter_phone' => __('Veuillez entrer un numéro de téléphone valide.', 'life-travel-excursion'),
                    'enter_code' => __('Veuillez entrer le code reçu.', 'life-travel-excursion'),
                    'code_sent' => __('Code envoyé ! Vérifiez votre boîte de réception ou téléphone.', 'life-travel-excursion'),
                    'invalid_code' => __('Code invalide. Veuillez réessayer.', 'life-travel-excursion'),
                    'error_sending' => __('Erreur lors de l\'envoi du code. Veuillez réessayer.', 'life-travel-excursion'),
                    'network_error' => __('Erreur réseau. Veuillez vérifier votre connexion.', 'life-travel-excursion')
                ]
            ]);
        }
    }
    
    /**
     * Shortcode pour le formulaire de connexion
     * 
     * @param array $atts Les attributs du shortcode
     * @return string Le HTML du formulaire
     */
    public function login_form_shortcode($atts) {
        // Fusionner les attributs avec les valeurs par défaut
        $atts = shortcode_atts([
            'redirect' => '',
            'show_register' => 'yes',
            'show_lost_password' => 'yes',
            'auth_methods' => 'email,phone,facebook'
        ], $atts);
        
        // Si l'utilisateur est déjà connecté, afficher un message
        if (is_user_logged_in()) {
            return $this->get_logged_in_message($atts['redirect']);
        }
        
        // Vérifier si l'utilisateur est bloqué
        if ($this->is_user_locked_out()) {
            return $this->get_lockout_message();
        }
        
        // Obtenir les méthodes d'authentification disponibles
        $auth_methods = explode(',', $atts['auth_methods']);
        
        // Démarrer la capture de sortie
        ob_start();
        
        // Charger le template
        $template_path = LIFE_TRAVEL_EXCURSION_DIR . 'templates/authentication/login-form.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Template de secours intégré
            ?>
            <div class="lte-auth-container lte-login-form">
                <div class="lte-auth-tabs">
                    <?php if (in_array('email', $auth_methods)) : ?>
                    <button class="lte-auth-tab active" data-method="email">
                        <?php esc_html_e('Email', 'life-travel-excursion'); ?>
                    </button>
                    <?php endif; ?>
                    
                    <?php if (in_array('phone', $auth_methods)) : ?>
                    <button class="lte-auth-tab" data-method="phone">
                        <?php esc_html_e('Téléphone', 'life-travel-excursion'); ?>
                    </button>
                    <?php endif; ?>
                    
                    <?php if (in_array('facebook', $auth_methods)) : ?>
                    <button class="lte-auth-tab" data-method="facebook">
                        <?php esc_html_e('Facebook', 'life-travel-excursion'); ?>
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="lte-auth-content">
                    <!-- Méthode Email -->
                    <?php if (in_array('email', $auth_methods)) : ?>
                    <div class="lte-auth-method lte-method-email active">
                        <form class="lte-auth-form lte-email-form">
                            <div class="lte-form-step lte-step-email active">
                                <div class="lte-form-group">
                                    <label for="lte-email"><?php esc_html_e('Email', 'life-travel-excursion'); ?></label>
                                    <input type="email" id="lte-email" name="email" required>
                                </div>
                                <div class="lte-form-actions">
                                    <button type="button" class="lte-send-code-btn">
                                        <?php esc_html_e('Recevoir un code', 'life-travel-excursion'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="lte-form-step lte-step-code">
                                <div class="lte-form-group">
                                    <label for="lte-email-code"><?php esc_html_e('Code reçu', 'life-travel-excursion'); ?></label>
                                    <input type="text" id="lte-email-code" name="code" pattern="[0-9]*" inputmode="numeric" maxlength="6" required>
                                </div>
                                <div class="lte-form-actions">
                                    <button type="button" class="lte-verify-code-btn">
                                        <?php esc_html_e('Vérifier', 'life-travel-excursion'); ?>
                                    </button>
                                    <button type="button" class="lte-resend-code-btn">
                                        <?php esc_html_e('Renvoyer le code', 'life-travel-excursion'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <?php wp_nonce_field('lte_auth_action', 'lte_auth_nonce'); ?>
                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($atts['redirect']); ?>">
                            <input type="hidden" name="auth_method" value="email">
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Méthode Téléphone -->
                    <?php if (in_array('phone', $auth_methods)) : ?>
                    <div class="lte-auth-method lte-method-phone">
                        <form class="lte-auth-form lte-phone-form">
                            <div class="lte-form-step lte-step-phone active">
                                <div class="lte-form-group">
                                    <label for="lte-phone"><?php esc_html_e('Téléphone', 'life-travel-excursion'); ?></label>
                                    <input type="tel" id="lte-phone" name="phone" placeholder="+237xxxxxxxxx" required>
                                </div>
                                <div class="lte-form-actions">
                                    <button type="button" class="lte-send-code-btn">
                                        <?php esc_html_e('Recevoir un code', 'life-travel-excursion'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="lte-form-step lte-step-code">
                                <div class="lte-form-group">
                                    <label for="lte-phone-code"><?php esc_html_e('Code reçu', 'life-travel-excursion'); ?></label>
                                    <input type="text" id="lte-phone-code" name="code" pattern="[0-9]*" inputmode="numeric" maxlength="6" required>
                                </div>
                                <div class="lte-form-actions">
                                    <button type="button" class="lte-verify-code-btn">
                                        <?php esc_html_e('Vérifier', 'life-travel-excursion'); ?>
                                    </button>
                                    <button type="button" class="lte-resend-code-btn">
                                        <?php esc_html_e('Renvoyer le code', 'life-travel-excursion'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <?php wp_nonce_field('lte_auth_action', 'lte_auth_nonce'); ?>
                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($atts['redirect']); ?>">
                            <input type="hidden" name="auth_method" value="phone">
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Méthode Facebook -->
                    <?php if (in_array('facebook', $auth_methods)) : ?>
                    <div class="lte-auth-method lte-method-facebook">
                        <div class="lte-facebook-login">
                            <p><?php esc_html_e('Connectez-vous facilement avec votre compte Facebook.', 'life-travel-excursion'); ?></p>
                            <button type="button" class="lte-facebook-btn">
                                <span class="lte-facebook-icon"></span>
                                <?php esc_html_e('Continuer avec Facebook', 'life-travel-excursion'); ?>
                            </button>
                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($atts['redirect']); ?>">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($atts['show_register'] === 'yes' || $atts['show_lost_password'] === 'yes') : ?>
                <div class="lte-auth-links">
                    <?php if ($atts['show_register'] === 'yes') : ?>
                    <a href="<?php echo esc_url(wp_registration_url()); ?>" class="lte-register-link">
                        <?php esc_html_e('Créer un compte', 'life-travel-excursion'); ?>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_lost_password'] === 'yes') : ?>
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="lte-lost-password-link">
                        <?php esc_html_e('Mot de passe oublié?', 'life-travel-excursion'); ?>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php
        }
        
        return ob_get_clean();
    }
    
    /**
     * Obtient le message à afficher quand l'utilisateur est déjà connecté
     * 
     * @param string $redirect URL de redirection
     * @return string Le HTML du message
     */
    private function get_logged_in_message($redirect) {
        $user = wp_get_current_user();
        $redirect_url = $redirect ? $redirect : admin_url();
        
        ob_start();
        ?>
        <div class="lte-logged-in-message">
            <p>
                <?php
                /* translators: %s: nom d'utilisateur */
                printf(
                    esc_html__('Vous êtes déjà connecté en tant que %s.', 'life-travel-excursion'),
                    '<strong>' . esc_html($user->display_name) . '</strong>'
                );
                ?>
            </p>
            <div class="lte-logged-in-actions">
                <a href="<?php echo esc_url($redirect_url); ?>" class="button">
                    <?php esc_html_e('Aller à mon compte', 'life-travel-excursion'); ?>
                </a>
                <a href="<?php echo esc_url(wp_logout_url()); ?>" class="button button-secondary">
                    <?php esc_html_e('Se déconnecter', 'life-travel-excursion'); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Obtient le message à afficher quand l'utilisateur est bloqué
     * 
     * @return string Le HTML du message
     */
    private function get_lockout_message() {
        $lockout_time = get_transient('lte_login_lockout_' . $this->get_client_ip());
        $remaining_time = $lockout_time - time();
        $minutes = ceil($remaining_time / 60);
        
        ob_start();
        ?>
        <div class="lte-lockout-message">
            <p>
                <?php
                /* translators: %d: temps restant en minutes */
                printf(
                    esc_html__('Trop de tentatives de connexion échouées. Veuillez réessayer dans %d minutes.', 'life-travel-excursion'),
                    $minutes
                );
                ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
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
     * Shortcode pour le formulaire d'inscription
     * 
     * @param array $atts Les attributs du shortcode
     * @return string Le HTML du formulaire
     */
    public function register_form_shortcode($atts) {
        // Implémentation à venir dans la seconde partie
        return 'Formulaire d\'inscription à venir';
    }
    
    /**
     * Shortcode pour la gestion de compte
     * 
     * @param array $atts Les attributs du shortcode
     * @return string Le HTML du formulaire
     */
    public function account_shortcode($atts) {
        // Implémentation à venir dans la seconde partie
        return 'Gestion de compte à venir';
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_Authentication_Manager::get_instance();
});
