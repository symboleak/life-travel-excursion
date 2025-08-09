<?php
/**
 * Intégration du système de fidélité avec le partage social
 * Permet aux utilisateurs de gagner des points en partageant sur les réseaux sociaux
 */

defined('ABSPATH') || exit;

class Life_Travel_Loyalty_Social {
    /**
     * Constructeur
     */
    public function __construct() {
        // Ajouter les endpoints AJAX pour le partage social
        add_action('wp_ajax_lte_social_share', array($this, 'handle_social_share'));
        add_action('wp_ajax_nopriv_lte_social_share', array($this, 'handle_guest_social_share'));

        // Ajouter les boutons de partage social aux pages produit
        add_action('woocommerce_share', array($this, 'add_social_share_buttons'));
        add_action('woocommerce_after_single_product_summary', array($this, 'add_social_share_buttons'), 50);
        
        // Ajouter les styles et scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Ajouter les réglages de fidélité au Customizer
        add_action('customize_register', array($this, 'register_customizer_settings'));
        
        // Afficher le solde de points dans My Account
        add_action('woocommerce_account_dashboard', array($this, 'display_loyalty_points_balance'));
    }

    /**
     * Enregistre les styles et scripts
     */
    public function enqueue_assets() {
        // Uniquement sur les pages pertinentes (single product, blog, etc.)
        if (is_product() || is_singular('post') || is_page() || is_account_page()) {
            wp_enqueue_style(
                'life-travel-social-share',
                LIFE_TRAVEL_ASSETS_URL . 'css/social-share.css',
                array(),
                LIFE_TRAVEL_EXCURSION_VERSION
            );
            
            wp_enqueue_script(
                'life-travel-social-share',
                LIFE_TRAVEL_ASSETS_URL . 'js/social-share.js',
                array('jquery'),
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
            
            wp_localize_script('life-travel-social-share', 'lteSocialShare', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lte_social_share_nonce'),
                'messages' => array(
                    'success' => __('Merci pour votre partage! Points ajoutés à votre compte.', 'life-travel-excursion'),
                    'error' => __('Erreur lors du partage. Veuillez réessayer.', 'life-travel-excursion'),
                    'login_required' => __('Veuillez vous connecter pour gagner des points.', 'life-travel-excursion'),
                    'max_reached' => __('Vous avez atteint le maximum de partages pour aujourd\'hui.', 'life-travel-excursion'),
                ),
                'is_logged_in' => is_user_logged_in(),
                'login_url' => wp_login_url(get_permalink()),
            ));
        }
    }

    /**
     * Ajoute les boutons de partage social
     */
    public function add_social_share_buttons() {
        // Ne pas afficher sur certaines pages
        if (is_cart() || is_checkout()) {
            return;
        }
        
        // Vérifier si le système de fidélité est activé
        $loyalty_enabled = get_theme_mod('lte_loyalty_enabled', false);
        
        // Préparer les points par réseau si le système est activé
        $loyalty_points = array();
        if ($loyalty_enabled) {
            $loyalty_points = array(
                'facebook' => get_theme_mod('lte_loyalty_points_facebook', 5),
                'twitter' => get_theme_mod('lte_loyalty_points_twitter', 4),
                'whatsapp' => get_theme_mod('lte_loyalty_points_whatsapp', 3),
                'instagram' => get_theme_mod('lte_loyalty_points_instagram', 5),
            );
        }
        
        // Récupérer l'URL et le titre actuels
        $share_url = esc_url(get_permalink());
        $share_title = esc_attr(get_the_title());
        
        // Message incitatif si les points sont activés
        $loyalty_message = '';
        if ($loyalty_enabled && is_user_logged_in()) {
            $max_points = max($loyalty_points);
            $loyalty_message = sprintf(
                __('Partagez et gagnez jusqu\'à %d points de fidélité!', 'life-travel-excursion'),
                $max_points
            );
        }
        
        // Afficher les boutons de partage
        ?>
        <div class="lte-social-share">
            <?php if ($loyalty_message) : ?>
                <p class="lte-share-points"><?php echo $loyalty_message; ?></p>
            <?php endif; ?>
            
            <div class="lte-share-buttons">
                <!-- Facebook -->
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" 
                   target="_blank" 
                   class="lte-share-button lte-facebook"
                   data-network="facebook"
                   <?php if ($loyalty_enabled) : ?>
                   data-points="<?php echo esc_attr($loyalty_points['facebook']); ?>"
                   <?php endif; ?>>
                    <i class="fab fa-facebook-f"></i>
                    <span><?php _e('Partager', 'life-travel-excursion'); ?></span>
                </a>
                
                <!-- Twitter -->
                <a href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>&text=<?php echo $share_title; ?>" 
                   target="_blank" 
                   class="lte-share-button lte-twitter"
                   data-network="twitter"
                   <?php if ($loyalty_enabled) : ?>
                   data-points="<?php echo esc_attr($loyalty_points['twitter']); ?>"
                   <?php endif; ?>>
                    <i class="fab fa-twitter"></i>
                    <span><?php _e('Tweet', 'life-travel-excursion'); ?></span>
                </a>
                
                <!-- WhatsApp (surtout pour mobile) -->
                <a href="https://api.whatsapp.com/send?text=<?php echo $share_title . ' ' . $share_url; ?>" 
                   target="_blank" 
                   class="lte-share-button lte-whatsapp"
                   data-network="whatsapp"
                   <?php if ($loyalty_enabled) : ?>
                   data-points="<?php echo esc_attr($loyalty_points['whatsapp']); ?>"
                   <?php endif; ?>>
                    <i class="fab fa-whatsapp"></i>
                    <span><?php _e('WhatsApp', 'life-travel-excursion'); ?></span>
                </a>
                
                <!-- Instagram Stories (via lien copié) -->
                <a href="#" 
                   class="lte-share-button lte-instagram lte-copy-link"
                   data-network="instagram"
                   data-url="<?php echo $share_url; ?>"
                   <?php if ($loyalty_enabled) : ?>
                   data-points="<?php echo esc_attr($loyalty_points['instagram']); ?>"
                   <?php endif; ?>>
                    <i class="fab fa-instagram"></i>
                    <span><?php _e('Copier lien', 'life-travel-excursion'); ?></span>
                </a>
            </div>
            
            <?php if (is_user_logged_in() && $loyalty_enabled) : ?>
                <div class="lte-share-notice"></div>
            <?php elseif (!is_user_logged_in() && $loyalty_enabled) : ?>
                <p class="lte-login-notice">
                    <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">
                        <?php _e('Connectez-vous pour gagner des points en partageant!', 'life-travel-excursion'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Gère les partages sociaux pour les utilisateurs connectés
     */
    public function handle_social_share() {
        // Vérifier le nonce
        check_ajax_referer('lte_social_share_nonce', 'nonce');
        
        // Vérifier si l'utilisateur est connecté
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('Vous devez être connecté pour gagner des points', 'life-travel-excursion')
            ));
            return;
        }
        
        // Vérifier si le système de fidélité est activé
        if (!get_theme_mod('lte_loyalty_enabled', false)) {
            wp_send_json_error(array(
                'message' => __('Le système de fidélité est désactivé', 'life-travel-excursion')
            ));
            return;
        }
        
        // Récupérer et valider le réseau social
        $network = isset($_POST['network']) ? sanitize_text_field($_POST['network']) : '';
        if (!in_array($network, array('facebook', 'twitter', 'whatsapp', 'instagram'))) {
            wp_send_json_error(array(
                'message' => __('Réseau social non valide', 'life-travel-excursion')
            ));
            return;
        }
        
        // Récupérer l'ID utilisateur
        $user_id = get_current_user_id();
        
        // Vérifier les limites de partage par jour
        $shares_today = get_user_meta($user_id, '_lte_social_shares_today', true);
        if (!is_array($shares_today)) {
            $shares_today = array();
        }
        
        $today = date('Y-m-d');
        if (!isset($shares_today[$today])) {
            $shares_today[$today] = array();
        }
        
        if (!isset($shares_today[$today][$network])) {
            $shares_today[$today][$network] = 0;
        }
        
        // Limiter le nombre de partages par réseau par jour
        $max_shares_per_day = get_theme_mod('lte_loyalty_max_shares_per_day', 3);
        if ($shares_today[$today][$network] >= $max_shares_per_day) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Vous avez atteint le maximum de %d partages sur %s aujourd\'hui', 'life-travel-excursion'), 
                    $max_shares_per_day,
                    ucfirst($network)
                )
            ));
            return;
        }
        
        // Récupérer les points pour ce réseau
        $points_map = array(
            'facebook' => get_theme_mod('lte_loyalty_points_facebook', 5),
            'twitter' => get_theme_mod('lte_loyalty_points_twitter', 4),
            'whatsapp' => get_theme_mod('lte_loyalty_points_whatsapp', 3),
            'instagram' => get_theme_mod('lte_loyalty_points_instagram', 5)
        );
        
        $points_to_add = isset($points_map[$network]) ? $points_map[$network] : 0;
        
        // Récupérer le solde actuel
        $current_points = get_user_meta($user_id, '_lte_loyalty_points', true);
        if (!is_numeric($current_points)) {
            $current_points = 0;
        }
        
        // Ajouter les points
        $new_points = $current_points + $points_to_add;
        update_user_meta($user_id, '_lte_loyalty_points', $new_points);
        
        // Mettre à jour le compteur de partages
        $shares_today[$today][$network]++;
        update_user_meta($user_id, '_lte_social_shares_today', $shares_today);
        
        // Nettoyer les données anciennes (plus de 7 jours)
        foreach (array_keys($shares_today) as $date) {
            $date_diff = (strtotime($today) - strtotime($date)) / (60 * 60 * 24);
            if ($date_diff > 7) {
                unset($shares_today[$date]);
            }
        }
        update_user_meta($user_id, '_lte_social_shares_today', $shares_today);
        
        // Retourner le résultat
        wp_send_json_success(array(
            'points_added' => $points_to_add,
            'new_total' => $new_points,
            'shares_today' => $shares_today[$today][$network],
            'max_shares' => $max_shares_per_day,
            'message' => sprintf(
                __('Vous avez gagné %d points pour votre partage sur %s!', 'life-travel-excursion'),
                $points_to_add,
                ucfirst($network)
            )
        ));
    }

    /**
     * Gère les partages sociaux pour les utilisateurs non connectés
     */
    public function handle_guest_social_share() {
        // Vérifier le nonce
        check_ajax_referer('lte_social_share_nonce', 'nonce');
        
        // Pour les invités, on ne peut pas attribuer de points,
        // mais on peut suivre les partages de manière anonyme à des fins statistiques
        
        // Récupérer et valider le réseau social
        $network = isset($_POST['network']) ? sanitize_text_field($_POST['network']) : '';
        if (!in_array($network, array('facebook', 'twitter', 'whatsapp', 'instagram'))) {
            wp_send_json_error();
            return;
        }
        
        // On pourrait ici enregistrer des statistiques anonymes
        // ...
        
        wp_send_json_success(array(
            'message' => __('Merci pour votre partage! Connectez-vous pour gagner des points.', 'life-travel-excursion')
        ));
    }

    /**
     * Enregistre les réglages de fidélité dans le Customizer
     */
    public function register_customizer_settings($wp_customize) {
        // Section pour la fidélité
        $wp_customize->add_section('lte_loyalty', array(
            'title' => __('Système de fidélité', 'life-travel-excursion'),
            'description' => __('Paramètres du système de fidélité par points', 'life-travel-excursion'),
            'priority' => 30
        ));
        
        // Activer/désactiver le système de fidélité
        $wp_customize->add_setting('lte_loyalty_enabled', array(
            'default' => false,
            'sanitize_callback' => 'absint'
        ));
        
        $wp_customize->add_control('lte_loyalty_enabled', array(
            'label' => __('Activer le système de fidélité', 'life-travel-excursion'),
            'section' => 'lte_loyalty',
            'type' => 'checkbox'
        ));
        
        // Taux de conversion (1€ = X points)
        $wp_customize->add_setting('lte_loyalty_conversion', array(
            'default' => 1,
            'sanitize_callback' => 'floatval'
        ));
        
        $wp_customize->add_control('lte_loyalty_conversion', array(
            'label' => __('Taux de conversion (1€ = X points)', 'life-travel-excursion'),
            'section' => 'lte_loyalty',
            'type' => 'number',
            'input_attrs' => array(
                'min' => 0.1,
                'step' => 0.1
            )
        ));
        
        // Pourcentage maximum de réduction
        $wp_customize->add_setting('lte_loyalty_max_discount_pct', array(
            'default' => 20,
            'sanitize_callback' => 'absint'
        ));
        
        $wp_customize->add_control('lte_loyalty_max_discount_pct', array(
            'label' => __('Pourcentage maximum de réduction (%)', 'life-travel-excursion'),
            'section' => 'lte_loyalty',
            'type' => 'number',
            'input_attrs' => array(
                'min' => 1,
                'max' => 100,
                'step' => 1
            ),
            'description' => __('Pourcentage maximum du total du panier pouvant être payé avec des points', 'life-travel-excursion')
        ));
        
        // Points pour partage Facebook
        $wp_customize->add_setting('lte_loyalty_points_facebook', array(
            'default' => 5,
            'sanitize_callback' => 'absint'
        ));
        
        $wp_customize->add_control('lte_loyalty_points_facebook', array(
            'label' => __('Points pour partage Facebook', 'life-travel-excursion'),
            'section' => 'lte_loyalty',
            'type' => 'number',
            'input_attrs' => array(
                'min' => 0,
                'step' => 1
            )
        ));
        
        // Points pour partage Twitter
        $wp_customize->add_setting('lte_loyalty_points_twitter', array(
            'default' => 4,
            'sanitize_callback' => 'absint'
        ));
        
        $wp_customize->add_control('lte_loyalty_points_twitter', array(
            'label' => __('Points pour partage Twitter', 'life-travel-excursion'),
            'section' => 'lte_loyalty',
            'type' => 'number',
            'input_attrs' => array(
                'min' => 0,
                'step' => 1
            )
        ));
        
        // Points pour partage WhatsApp
        $wp_customize->add_setting('lte_loyalty_points_whatsapp', array(
            'default' => 3,
            'sanitize_callback' => 'absint'
        ));
        
        $wp_customize->add_control('lte_loyalty_points_whatsapp', array(
            'label' => __('Points pour partage WhatsApp', 'life-travel-excursion'),
            'section' => 'lte_loyalty',
            'type' => 'number',
            'input_attrs' => array(
                'min' => 0,
                'step' => 1
            )
        ));
        
        // Points pour partage Instagram
        $wp_customize->add_setting('lte_loyalty_points_instagram', array(
            'default' => 5,
            'sanitize_callback' => 'absint'
        ));
        
        $wp_customize->add_control('lte_loyalty_points_instagram', array(
            'label' => __('Points pour partage Instagram', 'life-travel-excursion'),
            'section' => 'lte_loyalty',
            'type' => 'number',
            'input_attrs' => array(
                'min' => 0,
                'step' => 1
            )
        ));
        
        // Maximum de partages par jour
        $wp_customize->add_setting('lte_loyalty_max_shares_per_day', array(
            'default' => 3,
            'sanitize_callback' => 'absint'
        ));
        
        $wp_customize->add_control('lte_loyalty_max_shares_per_day', array(
            'label' => __('Maximum de partages par jour', 'life-travel-excursion'),
            'section' => 'lte_loyalty',
            'type' => 'number',
            'input_attrs' => array(
                'min' => 1,
                'step' => 1
            ),
            'description' => __('Nombre maximum de partages par réseau social par jour pour gagner des points', 'life-travel-excursion')
        ));
    }

    /**
     * Affiche le solde de points dans le tableau de bord My Account
     */
    public function display_loyalty_points_balance() {
        // Vérifier si le système de fidélité est activé
        if (!get_theme_mod('lte_loyalty_enabled', false)) {
            return;
        }
        
        // Récupérer les points de l'utilisateur
        $user_id = get_current_user_id();
        $points = get_user_meta($user_id, '_lte_loyalty_points', true);
        
        if (!is_numeric($points)) {
            $points = 0;
        }
        
        // Calculer la valeur en euros
        $conversion = get_theme_mod('lte_loyalty_conversion', 1);
        $value = $conversion > 0 ? round($points / $conversion, 2) : 0;
        
        // Afficher le solde
        ?>
        <div class="lte-loyalty-dashboard">
            <h3><?php _e('Votre fidélité', 'life-travel-excursion'); ?></h3>
            <div class="lte-loyalty-points">
                <div class="lte-points-balance">
                    <span class="lte-points-count"><?php echo esc_html($points); ?></span>
                    <span class="lte-points-label"><?php _e('points', 'life-travel-excursion'); ?></span>
                </div>
                <div class="lte-points-value">
                    <span class="lte-value-label"><?php _e('Valeur approximative:', 'life-travel-excursion'); ?></span>
                    <span class="lte-value-amount"><?php echo wc_price($value); ?></span>
                </div>
                <p class="lte-points-info">
                    <?php 
                    echo sprintf(
                        __('Utilisez vos points lors de votre prochaine commande pour obtenir jusqu\'à %d%% de réduction!', 'life-travel-excursion'),
                        get_theme_mod('lte_loyalty_max_discount_pct', 20)
                    ); 
                    ?>
                </p>
                <div class="lte-points-actions">
                    <a href="<?php echo esc_url(wc_get_endpoint_url('mes-excursions')); ?>" class="button">
                        <?php _e('Voir mes excursions', 'life-travel-excursion'); ?>
                    </a>
                    <a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="button">
                        <?php _e('Réserver une excursion', 'life-travel-excursion'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialiser la classe si le système de fidélité est activé ou en mode admin
if (get_theme_mod('lte_loyalty_enabled', false) || is_admin()) {
    $lte_loyalty_social = new Life_Travel_Loyalty_Social();
}
