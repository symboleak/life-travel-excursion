<?php
/**
 * Module de contact WhatsApp permanent pour Life Travel Excursion
 * Affiche un bouton WhatsApp discret mais toujours accessible sur mobile
 */

defined('ABSPATH') || exit;

class Life_Travel_WhatsApp_Contact {
    /**
     * Constructeur
     */
    public function __construct() {
        // Ajouter le bouton WhatsApp dans le footer
        add_action('wp_footer', array($this, 'display_whatsapp_button'));
        
        // Enregistrer les styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Ajouter les réglages au Customizer
        add_action('customize_register', array($this, 'register_customizer_settings'));
    }
    
    /**
     * Enregistre les styles et scripts
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'life-travel-whatsapp',
            LIFE_TRAVEL_ASSETS_URL . 'css/whatsapp-button.css',
            array(),
            LIFE_TRAVEL_EXCURSION_VERSION
        );
    }
    
    /**
     * Affiche le bouton WhatsApp
     */
    public function display_whatsapp_button() {
        // Vérifier si le bouton est activé
        if (!get_theme_mod('lte_whatsapp_enabled', true)) {
            return;
        }
        
        // Récupérer le numéro de téléphone
        $phone = get_theme_mod('lte_whatsapp_number', '');
        if (empty($phone)) {
            return;
        }
        
        // Récupérer le message prédéfini
        $message = get_theme_mod('lte_whatsapp_message', __('Bonjour, je souhaiterais des informations sur vos excursions.', 'life-travel-excursion'));
        $message = urlencode($message);
        
        // Récupérer les options d'affichage
        $position = get_theme_mod('lte_whatsapp_position', 'bottom-right');
        $icon_only = get_theme_mod('lte_whatsapp_icon_only', false);
        $hide_desktop = get_theme_mod('lte_whatsapp_hide_desktop', false);
        
        // Classes CSS pour les options d'affichage
        $classes = array('lte-whatsapp-button', $position);
        if ($icon_only) {
            $classes[] = 'icon-only';
        }
        if ($hide_desktop) {
            $classes[] = 'mobile-only';
        }
        
        // Construire l'URL WhatsApp
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);
        $whatsapp_url = "https://wa.me/{$phone_clean}?text={$message}";
        
        // Texte du bouton
        $button_text = get_theme_mod('lte_whatsapp_text', __('Contactez-nous', 'life-travel-excursion'));
        
        // Afficher le bouton
        ?>
        <a href="<?php echo esc_url($whatsapp_url); ?>" 
           target="_blank" 
           rel="noopener noreferrer" 
           class="<?php echo esc_attr(implode(' ', $classes)); ?>"
           title="<?php echo esc_attr($button_text); ?>">
            <svg class="lte-whatsapp-icon" viewBox="0 0 24 24">
                <path fill="currentColor" d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.274.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.72.045.419-.1.824zm-3.423-14.416c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm.029 18.88c-1.161 0-2.305-.292-3.32-.844l-3.677.964.984-3.595c-.607-1.052-.927-2.246-.926-3.468.001-3.825 3.113-6.937 6.937-6.937 1.856.001 3.598.723 4.907 2.034 1.31 1.311 2.031 3.054 2.03 4.908-.001 3.825-3.113 6.938-6.935 6.938z"/>
            </svg>
            <?php if (!$icon_only) : ?>
                <span class="lte-whatsapp-text"><?php echo esc_html($button_text); ?></span>
            <?php endif; ?>
        </a>
        
        <style>
            .lte-whatsapp-button {
                display: flex;
                align-items: center;
                justify-content: center;
                position: fixed;
                z-index: 9999;
                background-color: #25D366;
                color: white;
                border-radius: 50px;
                padding: 8px 20px;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
                text-decoration: none;
                transition: all 0.3s ease;
            }
            
            .lte-whatsapp-button:hover {
                background-color: #128C7E;
                transform: scale(1.05);
            }
            
            .lte-whatsapp-button.icon-only {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                padding: 0;
            }
            
            .lte-whatsapp-icon {
                width: 24px;
                height: 24px;
                margin-right: 8px;
            }
            
            .lte-whatsapp-button.icon-only .lte-whatsapp-icon {
                margin-right: 0;
            }
            
            /* Positions */
            .lte-whatsapp-button.bottom-right {
                bottom: 20px;
                right: 20px;
            }
            
            .lte-whatsapp-button.bottom-left {
                bottom: 20px;
                left: 20px;
            }
            
            .lte-whatsapp-button.middle-right {
                top: 50%;
                right: 20px;
                transform: translateY(-50%);
            }
            
            .lte-whatsapp-button.middle-left {
                top: 50%;
                left: 20px;
                transform: translateY(-50%);
            }
            
            .lte-whatsapp-button.mobile-only {
                display: none;
            }
            
            @media (max-width: 768px) {
                .lte-whatsapp-button.mobile-only {
                    display: flex;
                }
                
                .lte-whatsapp-button {
                    padding: 8px 16px;
                }
                
                .lte-whatsapp-text {
                    font-size: 14px;
                }
                
                .lte-whatsapp-button.icon-only {
                    width: 50px;
                    height: 50px;
                }
            }
        </style>
        <?php
    }
    
    /**
     * Enregistre les réglages dans le Customizer
     */
    public function register_customizer_settings($wp_customize) {
        // Section pour le bouton WhatsApp
        $wp_customize->add_section('lte_whatsapp', array(
            'title' => __('Bouton WhatsApp', 'life-travel-excursion'),
            'description' => __('Configurez le bouton WhatsApp permanent', 'life-travel-excursion'),
            'priority' => 100,
        ));
        
        // Activer/désactiver le bouton
        $wp_customize->add_setting('lte_whatsapp_enabled', array(
            'default' => true,
            'sanitize_callback' => 'absint',
        ));
        
        $wp_customize->add_control('lte_whatsapp_enabled', array(
            'label' => __('Activer le bouton WhatsApp', 'life-travel-excursion'),
            'section' => 'lte_whatsapp',
            'type' => 'checkbox',
        ));
        
        // Numéro de téléphone
        $wp_customize->add_setting('lte_whatsapp_number', array(
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        $wp_customize->add_control('lte_whatsapp_number', array(
            'label' => __('Numéro de téléphone WhatsApp', 'life-travel-excursion'),
            'section' => 'lte_whatsapp',
            'type' => 'text',
            'description' => __('Format international, ex: +237612345678', 'life-travel-excursion'),
        ));
        
        // Message prédéfini
        $wp_customize->add_setting('lte_whatsapp_message', array(
            'default' => __('Bonjour, je souhaiterais des informations sur vos excursions.', 'life-travel-excursion'),
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        $wp_customize->add_control('lte_whatsapp_message', array(
            'label' => __('Message prédéfini', 'life-travel-excursion'),
            'section' => 'lte_whatsapp',
            'type' => 'textarea',
        ));
        
        // Texte du bouton
        $wp_customize->add_setting('lte_whatsapp_text', array(
            'default' => __('Contactez-nous', 'life-travel-excursion'),
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        $wp_customize->add_control('lte_whatsapp_text', array(
            'label' => __('Texte du bouton', 'life-travel-excursion'),
            'section' => 'lte_whatsapp',
            'type' => 'text',
        ));
        
        // Position du bouton
        $wp_customize->add_setting('lte_whatsapp_position', array(
            'default' => 'bottom-right',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        $wp_customize->add_control('lte_whatsapp_position', array(
            'label' => __('Position du bouton', 'life-travel-excursion'),
            'section' => 'lte_whatsapp',
            'type' => 'select',
            'choices' => array(
                'bottom-right' => __('Bas droite', 'life-travel-excursion'),
                'bottom-left' => __('Bas gauche', 'life-travel-excursion'),
                'middle-right' => __('Milieu droite', 'life-travel-excursion'),
                'middle-left' => __('Milieu gauche', 'life-travel-excursion'),
            ),
        ));
        
        // Option icône uniquement
        $wp_customize->add_setting('lte_whatsapp_icon_only', array(
            'default' => false,
            'sanitize_callback' => 'absint',
        ));
        
        $wp_customize->add_control('lte_whatsapp_icon_only', array(
            'label' => __('Afficher uniquement l\'icône', 'life-travel-excursion'),
            'section' => 'lte_whatsapp',
            'type' => 'checkbox',
        ));
        
        // Option masquer sur desktop
        $wp_customize->add_setting('lte_whatsapp_hide_desktop', array(
            'default' => false,
            'sanitize_callback' => 'absint',
        ));
        
        $wp_customize->add_control('lte_whatsapp_hide_desktop', array(
            'label' => __('Afficher uniquement sur mobile', 'life-travel-excursion'),
            'section' => 'lte_whatsapp',
            'type' => 'checkbox',
        ));
    }
}

// Initialiser le bouton WhatsApp
$lte_whatsapp_contact = new Life_Travel_WhatsApp_Contact();
