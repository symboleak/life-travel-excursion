<?php
/**
 * Options du Customizer pour le système d'authentification
 *
 * @package Life_Travel_Excursion
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Classe qui gère les options du customizer pour l'authentification
 */
class Life_Travel_Authentication_Customizer {
    /**
     * Instance de la classe
     */
    private static $instance = null;
    
    /**
     * Constructeur
     */
    private function __construct() {
        add_action('customize_register', [$this, 'register_customizer_settings']);
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    /**
     * Récupère l'instance unique
     *
     * @return Life_Travel_Authentication_Customizer
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Enregistre les options dans les réglages WordPress
     */
    public function register_settings() {
        // Méthodes d'authentification
        register_setting('lte_auth_options', 'lte_enable_email_auth', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        register_setting('lte_auth_options', 'lte_enable_phone_auth', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        register_setting('lte_auth_options', 'lte_enable_facebook_auth', [
            'type' => 'string',
            'default' => 'no',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        // Options Facebook
        register_setting('lte_auth_options', 'lte_facebook_app_id', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        register_setting('lte_auth_options', 'lte_facebook_app_secret', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        // Options Twilio pour SMS
        register_setting('lte_auth_options', 'lte_twilio_sid', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        register_setting('lte_auth_options', 'lte_twilio_token', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        register_setting('lte_auth_options', 'lte_twilio_phone', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        // Options de sécurité
        register_setting('lte_auth_options', 'lte_otp_expiry', [
            'type' => 'integer',
            'default' => 600,
            'sanitize_callback' => 'absint',
        ]);
        
        register_setting('lte_auth_options', 'lte_max_login_attempts', [
            'type' => 'integer',
            'default' => 5,
            'sanitize_callback' => 'absint',
        ]);
        
        register_setting('lte_auth_options', 'lte_lockout_duration', [
            'type' => 'integer',
            'default' => 900,
            'sanitize_callback' => 'absint',
        ]);
        
        register_setting('lte_auth_options', 'lte_enforce_admin_2fa', [
            'type' => 'string',
            'default' => 'no',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        // Options de récupération de panier
        register_setting('lte_auth_options', 'lte_enable_cart_recovery', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        // Options visuelles
        register_setting('lte_auth_options', 'lte_auth_logo', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'esc_url_raw',
        ]);
        
        register_setting('lte_auth_options', 'lte_show_auth_trust_badges', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        register_setting('lte_auth_options', 'lte_auth_trust_badges', [
            'type' => 'array',
            'default' => [],
            'sanitize_callback' => [$this, 'sanitize_trust_badges'],
        ]);
        
        // Textes descriptifs
        register_setting('lte_auth_options', 'lte_email_auth_description', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'wp_kses_post',
        ]);
        
        register_setting('lte_auth_options', 'lte_phone_auth_description', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'wp_kses_post',
        ]);
        
        register_setting('lte_auth_options', 'lte_facebook_auth_description', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'wp_kses_post',
        ]);
    }
    
    /**
     * Sanitize les badges de confiance
     *
     * @param array $badges Liste des badges
     * @return array
     */
    public function sanitize_trust_badges($badges) {
        if (!is_array($badges)) {
            return [];
        }
        
        $sanitized = [];
        
        foreach ($badges as $badge) {
            if (!empty($badge['icon']) && !empty($badge['label'])) {
                $sanitized[] = [
                    'icon' => esc_url_raw($badge['icon']),
                    'label' => sanitize_text_field($badge['label']),
                ];
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Enregistre les paramètres du customizer
     *
     * @param WP_Customize_Manager $wp_customize Objet customizer
     */
    public function register_customizer_settings($wp_customize) {
        // Section d'authentification
        $wp_customize->add_section('lte_auth_section', [
            'title' => __('Life Travel - Authentification', 'life-travel-excursion'),
            'priority' => 160,
            'description' => __('Options pour le système d\'authentification sécurisé.', 'life-travel-excursion'),
        ]);
        
        // Panel pour les méthodes d'authentification
        $wp_customize->add_panel('lte_auth_methods_panel', [
            'title' => __('Méthodes d\'authentification', 'life-travel-excursion'),
            'priority' => 10,
            'panel' => '',
            'section' => 'lte_auth_section',
        ]);
        
        // Section Email
        $wp_customize->add_section('lte_auth_email_section', [
            'title' => __('Email', 'life-travel-excursion'),
            'panel' => 'lte_auth_methods_panel',
            'priority' => 10,
        ]);
        
        $wp_customize->add_setting('lte_enable_email_auth', [
            'default' => 'yes',
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        $wp_customize->add_control('lte_enable_email_auth', [
            'label' => __('Activer l\'authentification par email', 'life-travel-excursion'),
            'section' => 'lte_auth_email_section',
            'settings' => 'lte_enable_email_auth',
            'type' => 'radio',
            'choices' => [
                'yes' => __('Activé', 'life-travel-excursion'),
                'no' => __('Désactivé', 'life-travel-excursion'),
            ],
        ]);
        
        $wp_customize->add_setting('lte_email_auth_description', [
            'default' => __('Connectez-vous en toute sécurité avec votre adresse email. Un code à usage unique vous sera envoyé.', 'life-travel-excursion'),
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'wp_kses_post',
        ]);
        
        $wp_customize->add_control('lte_email_auth_description', [
            'label' => __('Description explicative', 'life-travel-excursion'),
            'section' => 'lte_auth_email_section',
            'settings' => 'lte_email_auth_description',
            'type' => 'textarea',
        ]);
        
        // Section Téléphone
        $wp_customize->add_section('lte_auth_phone_section', [
            'title' => __('Téléphone', 'life-travel-excursion'),
            'panel' => 'lte_auth_methods_panel',
            'priority' => 20,
        ]);
        
        $wp_customize->add_setting('lte_enable_phone_auth', [
            'default' => 'yes',
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        $wp_customize->add_control('lte_enable_phone_auth', [
            'label' => __('Activer l\'authentification par téléphone', 'life-travel-excursion'),
            'section' => 'lte_auth_phone_section',
            'settings' => 'lte_enable_phone_auth',
            'type' => 'radio',
            'choices' => [
                'yes' => __('Activé', 'life-travel-excursion'),
                'no' => __('Désactivé', 'life-travel-excursion'),
            ],
        ]);
        
        $wp_customize->add_setting('lte_phone_auth_description', [
            'default' => __('Authentifiez-vous rapidement avec votre téléphone. Nous vous enverrons un SMS avec un code unique.', 'life-travel-excursion'),
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'wp_kses_post',
        ]);
        
        $wp_customize->add_control('lte_phone_auth_description', [
            'label' => __('Description explicative', 'life-travel-excursion'),
            'section' => 'lte_auth_phone_section',
            'settings' => 'lte_phone_auth_description',
            'type' => 'textarea',
        ]);
        
        // Paramètres Twilio
        $wp_customize->add_setting('lte_twilio_sid', [
            'default' => '',
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        $wp_customize->add_control('lte_twilio_sid', [
            'label' => __('Twilio SID', 'life-travel-excursion'),
            'section' => 'lte_auth_phone_section',
            'settings' => 'lte_twilio_sid',
            'type' => 'text',
        ]);
        
        $wp_customize->add_setting('lte_twilio_token', [
            'default' => '',
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        $wp_customize->add_control('lte_twilio_token', [
            'label' => __('Twilio Token', 'life-travel-excursion'),
            'section' => 'lte_auth_phone_section',
            'settings' => 'lte_twilio_token',
            'type' => 'password',
        ]);
        
        $wp_customize->add_setting('lte_twilio_phone', [
            'default' => '',
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        $wp_customize->add_control('lte_twilio_phone', [
            'label' => __('Numéro de téléphone Twilio', 'life-travel-excursion'),
            'section' => 'lte_auth_phone_section',
            'settings' => 'lte_twilio_phone',
            'type' => 'text',
            'description' => __('Format international, ex: +12025551234', 'life-travel-excursion'),
        ]);
        
        // Section Facebook
        $wp_customize->add_section('lte_auth_facebook_section', [
            'title' => __('Facebook', 'life-travel-excursion'),
            'panel' => 'lte_auth_methods_panel',
            'priority' => 30,
        ]);
        
        $wp_customize->add_setting('lte_enable_facebook_auth', [
            'default' => 'no',
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        $wp_customize->add_control('lte_enable_facebook_auth', [
            'label' => __('Activer l\'authentification par Facebook', 'life-travel-excursion'),
            'section' => 'lte_auth_facebook_section',
            'settings' => 'lte_enable_facebook_auth',
            'type' => 'radio',
            'choices' => [
                'yes' => __('Activé', 'life-travel-excursion'),
                'no' => __('Désactivé', 'life-travel-excursion'),
            ],
        ]);
        
        $wp_customize->add_setting('lte_facebook_app_id', [
            'default' => '',
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        $wp_customize->add_control('lte_facebook_app_id', [
            'label' => __('Facebook App ID', 'life-travel-excursion'),
            'section' => 'lte_auth_facebook_section',
            'settings' => 'lte_facebook_app_id',
            'type' => 'text',
        ]);
        
        $wp_customize->add_setting('lte_facebook_app_secret', [
            'default' => '',
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        $wp_customize->add_control('lte_facebook_app_secret', [
            'label' => __('Facebook App Secret', 'life-travel-excursion'),
            'section' => 'lte_auth_facebook_section',
            'settings' => 'lte_facebook_app_secret',
            'type' => 'password',
        ]);
        
        $wp_customize->add_setting('lte_facebook_auth_description', [
            'default' => __('Connectez-vous rapidement avec votre compte Facebook. Vous pourrez accéder à vos excursions immédiatement.', 'life-travel-excursion'),
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'wp_kses_post',
        ]);
        
        $wp_customize->add_control('lte_facebook_auth_description', [
            'label' => __('Description explicative', 'life-travel-excursion'),
            'section' => 'lte_auth_facebook_section',
            'settings' => 'lte_facebook_auth_description',
            'type' => 'textarea',
        ]);
        
        // Section Sécurité
        $wp_customize->add_section('lte_auth_security_section', [
            'title' => __('Paramètres de sécurité', 'life-travel-excursion'),
            'section' => 'lte_auth_section',
            'priority' => 20,
        ]);
        
        $wp_customize->add_setting('lte_otp_expiry', [
            'default' => 600,
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'absint',
        ]);
        
        $wp_customize->add_control('lte_otp_expiry', [
            'label' => __('Durée de validité des codes OTP (secondes)', 'life-travel-excursion'),
            'section' => 'lte_auth_security_section',
            'settings' => 'lte_otp_expiry',
            'type' => 'number',
            'input_attrs' => [
                'min' => 60,
                'max' => 3600,
                'step' => 60,
            ],
        ]);
        
        $wp_customize->add_setting('lte_max_login_attempts', [
            'default' => 5,
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'absint',
        ]);
        
        $wp_customize->add_control('lte_max_login_attempts', [
            'label' => __('Nombre maximal de tentatives de connexion', 'life-travel-excursion'),
            'section' => 'lte_auth_security_section',
            'settings' => 'lte_max_login_attempts',
            'type' => 'number',
            'input_attrs' => [
                'min' => 3,
                'max' => 10,
                'step' => 1,
            ],
        ]);
        
        $wp_customize->add_setting('lte_lockout_duration', [
            'default' => 900,
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'absint',
        ]);
        
        $wp_customize->add_control('lte_lockout_duration', [
            'label' => __('Durée de blocage après échec (secondes)', 'life-travel-excursion'),
            'section' => 'lte_auth_security_section',
            'settings' => 'lte_lockout_duration',
            'type' => 'number',
            'input_attrs' => [
                'min' => 60,
                'max' => 3600,
                'step' => 60,
            ],
        ]);
        
        $wp_customize->add_setting('lte_enforce_admin_2fa', [
            'default' => 'no',
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        $wp_customize->add_control('lte_enforce_admin_2fa', [
            'label' => __('Forcer l\'authentification à deux facteurs pour les administrateurs', 'life-travel-excursion'),
            'section' => 'lte_auth_security_section',
            'settings' => 'lte_enforce_admin_2fa',
            'type' => 'radio',
            'choices' => [
                'yes' => __('Obligatoire', 'life-travel-excursion'),
                'no' => __('Optionnel', 'life-travel-excursion'),
            ],
        ]);
        
        // Section Apparence
        $wp_customize->add_section('lte_auth_appearance_section', [
            'title' => __('Apparence', 'life-travel-excursion'),
            'section' => 'lte_auth_section',
            'priority' => 30,
        ]);
        
        $wp_customize->add_setting('lte_auth_logo', [
            'default' => '',
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'esc_url_raw',
        ]);
        
        $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'lte_auth_logo', [
            'label' => __('Logo sur les pages d\'authentification', 'life-travel-excursion'),
            'section' => 'lte_auth_appearance_section',
            'settings' => 'lte_auth_logo',
        ]));
        
        $wp_customize->add_setting('lte_show_auth_trust_badges', [
            'default' => 'yes',
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        $wp_customize->add_control('lte_show_auth_trust_badges', [
            'label' => __('Afficher les badges de confiance', 'life-travel-excursion'),
            'section' => 'lte_auth_appearance_section',
            'settings' => 'lte_show_auth_trust_badges',
            'type' => 'radio',
            'choices' => [
                'yes' => __('Oui', 'life-travel-excursion'),
                'no' => __('Non', 'life-travel-excursion'),
            ],
        ]);
        
        // Section Récupération de panier
        $wp_customize->add_section('lte_auth_cart_recovery_section', [
            'title' => __('Récupération de panier', 'life-travel-excursion'),
            'section' => 'lte_auth_section',
            'priority' => 40,
        ]);
        
        $wp_customize->add_setting('lte_enable_cart_recovery', [
            'default' => 'yes',
            'type' => 'option',
            'capability' => 'manage_options',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        $wp_customize->add_control('lte_enable_cart_recovery', [
            'label' => __('Activer la récupération de panier lors de la connexion', 'life-travel-excursion'),
            'section' => 'lte_auth_cart_recovery_section',
            'settings' => 'lte_enable_cart_recovery',
            'type' => 'radio',
            'choices' => [
                'yes' => __('Activé', 'life-travel-excursion'),
                'no' => __('Désactivé', 'life-travel-excursion'),
            ],
            'description' => __('Cette option permet de récupérer intelligemment les paniers abandonnés lors de la connexion utilisateur, assurant ainsi une expérience utilisateur fluide même en cas de problème de connexion réseau.', 'life-travel-excursion'),
        ]);
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_Authentication_Customizer::get_instance();
});
