<?php
/**
 * Template pour le formulaire de connexion
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

// Attributs du shortcode
$redirect = isset($atts['redirect']) ? $atts['redirect'] : '';
$show_register = isset($atts['show_register']) ? $atts['show_register'] : 'yes';
$show_lost_password = isset($atts['show_lost_password']) ? $atts['show_lost_password'] : 'yes';

// Options des méthodes d'authentification disponibles
$auth_methods = isset($atts['auth_methods']) ? explode(',', $atts['auth_methods']) : ['email', 'phone', 'facebook'];

// Limiter aux méthodes activées dans les options
$enabled_methods = [];
if (in_array('email', $auth_methods) && get_option('lte_enable_email_auth', 'yes') === 'yes') {
    $enabled_methods[] = 'email';
}
if (in_array('phone', $auth_methods) && get_option('lte_enable_phone_auth', 'yes') === 'yes') {
    $enabled_methods[] = 'phone';
}
if (in_array('facebook', $auth_methods) && get_option('lte_enable_facebook_auth', 'no') === 'yes') {
    $enabled_methods[] = 'facebook';
}

// S'assurer qu'au moins une méthode est activée
if (empty($enabled_methods)) {
    $enabled_methods[] = 'email'; // Email par défaut
}
?>

<div class="lte-auth-container lte-login-form">
    <?php if (count($enabled_methods) > 1) : ?>
    <!-- Onglets des méthodes d'authentification -->
    <div class="lte-auth-tabs">
        <?php foreach ($enabled_methods as $index => $method) : 
            $is_active = $index === 0;
            $method_name = '';
            
            switch ($method) {
                case 'email':
                    $method_name = __('Email', 'life-travel-excursion');
                    break;
                case 'phone':
                    $method_name = __('Téléphone', 'life-travel-excursion');
                    break;
                case 'facebook':
                    $method_name = __('Facebook', 'life-travel-excursion');
                    break;
            }
        ?>
        <button class="lte-auth-tab <?php echo $is_active ? 'active' : ''; ?>" data-method="<?php echo esc_attr($method); ?>">
            <?php echo esc_html($method_name); ?>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <div class="lte-auth-content">
        <!-- Méthode Email -->
        <?php if (in_array('email', $enabled_methods)) : 
            $is_active = $enabled_methods[0] === 'email';
        ?>
        <div class="lte-auth-method lte-method-email <?php echo $is_active ? 'active' : ''; ?>">
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
                        <button type="button" class="lte-resend-code-btn lte-secondary-btn">
                            <?php esc_html_e('Renvoyer le code', 'life-travel-excursion'); ?>
                        </button>
                    </div>
                </div>
                
                <?php wp_nonce_field('lte_auth_action', 'lte_auth_nonce'); ?>
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">
                <input type="hidden" name="auth_method" value="email">
            </form>
            
            <?php
            // Description explicative
            $email_description = get_option('lte_email_auth_description', '');
            if (!empty($email_description)) :
            ?>
            <div class="lte-auth-description">
                <?php echo wp_kses_post(wpautop($email_description)); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Méthode Téléphone -->
        <?php if (in_array('phone', $enabled_methods)) : 
            $is_active = $enabled_methods[0] === 'phone';
        ?>
        <div class="lte-auth-method lte-method-phone <?php echo $is_active ? 'active' : ''; ?>">
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
                        <button type="button" class="lte-resend-code-btn lte-secondary-btn">
                            <?php esc_html_e('Renvoyer le code', 'life-travel-excursion'); ?>
                        </button>
                    </div>
                </div>
                
                <?php wp_nonce_field('lte_auth_action', 'lte_auth_nonce'); ?>
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">
                <input type="hidden" name="auth_method" value="phone">
            </form>
            
            <?php
            // Description explicative
            $phone_description = get_option('lte_phone_auth_description', '');
            if (!empty($phone_description)) :
            ?>
            <div class="lte-auth-description">
                <?php echo wp_kses_post(wpautop($phone_description)); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Méthode Facebook -->
        <?php if (in_array('facebook', $enabled_methods)) : 
            $is_active = $enabled_methods[0] === 'facebook';
        ?>
        <div class="lte-auth-method lte-method-facebook <?php echo $is_active ? 'active' : ''; ?>">
            <div class="lte-facebook-login">
                <p><?php esc_html_e('Connectez-vous facilement avec votre compte Facebook.', 'life-travel-excursion'); ?></p>
                <button type="button" class="lte-facebook-btn">
                    <span class="lte-facebook-icon"></span>
                    <?php esc_html_e('Continuer avec Facebook', 'life-travel-excursion'); ?>
                </button>
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">
            </div>
            
            <?php
            // Description explicative
            $fb_description = get_option('lte_facebook_auth_description', '');
            if (!empty($fb_description)) :
            ?>
            <div class="lte-auth-description">
                <?php echo wp_kses_post(wpautop($fb_description)); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php 
    // Afficher les liens (inscription, mot de passe oublié)
    if ($show_register === 'yes' || $show_lost_password === 'yes') : 
    ?>
    <div class="lte-auth-links">
        <?php if ($show_register === 'yes') : ?>
        <a href="<?php echo esc_url(wp_registration_url()); ?>" class="lte-register-link">
            <?php esc_html_e('Créer un compte', 'life-travel-excursion'); ?>
        </a>
        <?php endif; ?>
        
        <?php if ($show_lost_password === 'yes') : ?>
        <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="lte-lost-password-link">
            <?php esc_html_e('Mot de passe oublié?', 'life-travel-excursion'); ?>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php
    // Intégration avec la synchronisation des paniers abandonnés
    $enable_cart_recovery = get_option('lte_enable_cart_recovery', 'yes');
    if ($enable_cart_recovery === 'yes') :
    ?>
    <div class="lte-auth-cart-recovery" style="display: none;">
        <p><?php esc_html_e('Vous avez un panier en attente. Souhaitez-vous le récupérer?', 'life-travel-excursion'); ?></p>
        <button type="button" class="lte-recover-cart-btn">
            <?php esc_html_e('Récupérer mon panier', 'life-travel-excursion'); ?>
        </button>
        <button type="button" class="lte-ignore-cart-btn lte-secondary-btn">
            <?php esc_html_e('Non merci', 'life-travel-excursion'); ?>
        </button>
    </div>
    <?php endif; ?>
    
    <?php
    // Logos de sécurité et confiance
    $show_trust_badges = get_option('lte_show_auth_trust_badges', 'yes');
    if ($show_trust_badges === 'yes') :
    ?>
    <div class="lte-auth-trust">
        <p><?php esc_html_e('Connexion sécurisée', 'life-travel-excursion'); ?></p>
        <div class="lte-auth-trust-badges">
            <?php
            // Afficher les badges de confiance configurés
            $trust_badges = get_option('lte_auth_trust_badges', []);
            if (!empty($trust_badges)) {
                foreach ($trust_badges as $badge) {
                    if (!empty($badge['icon']) && !empty($badge['label'])) {
                        echo '<div class="lte-trust-badge">';
                        echo '<img src="' . esc_url($badge['icon']) . '" alt="' . esc_attr($badge['label']) . '">';
                        echo '<span>' . esc_html($badge['label']) . '</span>';
                        echo '</div>';
                    }
                }
            } else {
                // Badges par défaut
                echo '<div class="lte-trust-badge lte-secure"><span>' . esc_html__('Chiffrement SSL', 'life-travel-excursion') . '</span></div>';
                echo '<div class="lte-trust-badge lte-privacy"><span>' . esc_html__('Respect de la vie privée', 'life-travel-excursion') . '</span></div>';
            }
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Charger le SDK Facebook si nécessaire
if (in_array('facebook', $enabled_methods)) {
    $fb_app_id = get_option('lte_facebook_app_id', '');
    
    if (!empty($fb_app_id)) {
        ?>
        <div id="fb-root"></div>
        <script>
            window.fbAsyncInit = function() {
                FB.init({
                    appId: '<?php echo esc_js($fb_app_id); ?>',
                    cookie: true,
                    xfbml: true,
                    version: 'v14.0'
                });
            };
            
            (function(d, s, id) {
                var js, fjs = d.getElementsByTagName(s)[0];
                if (d.getElementById(id)) return;
                js = d.createElement(s); js.id = id;
                js.src = "https://connect.facebook.net/fr_FR/sdk.js";
                fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'facebook-jssdk'));
        </script>
        <?php
    }
}
?>
