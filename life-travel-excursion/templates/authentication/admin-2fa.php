<?php
/**
 * Template pour l'authentification à deux facteurs des administrateurs
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

// Récupérer les paramètres de l'URL
$redirect = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : admin_url();
$user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;

// Vérifier si l'utilisateur existe et est un administrateur
$user = get_userdata($user_id);
if (!$user || !in_array('administrator', $user->roles)) {
    wp_die(__('Accès non autorisé.', 'life-travel-excursion'));
}

// Vérifier si l'authentification à deux facteurs est activée pour cet utilisateur
$two_factor_enabled = get_user_meta($user_id, '_lte_2fa_enabled', true);
if (!$two_factor_enabled) {
    // Rediriger directement si la 2FA n'est pas activée
    wp_redirect($redirect);
    exit;
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Vérification de sécurité', 'life-travel-excursion'); ?></title>
    <?php wp_head(); ?>
    <style>
        body {
            background-color: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .lte-admin-2fa-container {
            max-width: 400px;
            width: 100%;
            padding: 30px;
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .lte-admin-2fa-logo {
            margin-bottom: 20px;
        }
        .lte-admin-2fa-logo img {
            max-width: 100px;
            height: auto;
        }
        .lte-admin-2fa-container h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
            font-weight: 500;
        }
        .lte-admin-2fa-container p {
            margin-bottom: 20px;
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        .lte-admin-2fa-form input[type="text"] {
            width: 100%;
            padding: 12px;
            font-size: 18px;
            text-align: center;
            letter-spacing: 0.2em;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .lte-admin-2fa-form button {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        .lte-admin-2fa-form button:hover {
            background-color: #388E3C;
        }
        .lte-auth-message {
            margin: 10px 0;
            padding: 12px 15px;
            border-radius: 4px;
            font-size: 14px;
            display: none;
        }
        .lte-auth-message.error {
            background-color: #FFEBEE;
            color: #D32F2F;
            border: 1px solid #FFCDD2;
        }
        .lte-auth-message.success {
            background-color: #E8F5E9;
            color: #388E3C;
            border: 1px solid #C8E6C9;
        }
        .lte-admin-2fa-help {
            margin-top: 20px;
            font-size: 13px;
            color: #999;
        }
        .lte-admin-2fa-help a {
            color: #4CAF50;
            text-decoration: none;
        }
        .lte-admin-2fa-help a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="lte-admin-2fa-container">
        <div class="lte-admin-2fa-logo">
            <?php 
            $logo = get_option('lte_auth_logo', '');
            if ($logo) {
                echo '<img src="' . esc_url($logo) . '" alt="' . esc_attr(get_bloginfo('name')) . '">';
            } else {
                echo '<h2>' . esc_html(get_bloginfo('name')) . '</h2>';
            }
            ?>
        </div>
        
        <h1><?php esc_html_e('Vérification de sécurité', 'life-travel-excursion'); ?></h1>
        
        <p>
            <?php
            /* translators: %s: nom d'utilisateur */
            printf(
                esc_html__('Bonjour %s, veuillez entrer le code de sécurité qui vous a été envoyé pour continuer.', 'life-travel-excursion'),
                '<strong>' . esc_html($user->display_name) . '</strong>'
            );
            ?>
        </p>
        
        <form class="lte-admin-2fa-form">
            <div class="lte-auth-message"></div>
            
            <input type="text" name="code" placeholder="<?php esc_attr_e('Code de sécurité', 'life-travel-excursion'); ?>" pattern="[0-9]*" inputmode="numeric" maxlength="6" required autofocus>
            
            <button type="submit">
                <?php esc_html_e('Vérifier', 'life-travel-excursion'); ?>
            </button>
            
            <?php wp_nonce_field('lte_2fa_verify', 'nonce'); ?>
            <input type="hidden" name="redirect" value="<?php echo esc_attr($redirect); ?>">
        </form>
        
        <div class="lte-admin-2fa-help">
            <p>
                <?php esc_html_e('Vous n\'avez pas reçu de code?', 'life-travel-excursion'); ?>
                <a href="<?php echo esc_url(add_query_arg('resend', '1')); ?>">
                    <?php esc_html_e('Renvoyer le code', 'life-travel-excursion'); ?>
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url(wp_logout_url()); ?>">
                    <?php esc_html_e('Se déconnecter', 'life-travel-excursion'); ?>
                </a>
            </p>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html><?php
// Envoyer un nouveau code si demandé
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    do_action('lte_admin_send_2fa_code', $user_id);
}
?>
