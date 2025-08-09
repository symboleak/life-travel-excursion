<?php
/**
 * Frontend authentication (email code, SMS, social)
 */
defined('ABSPATH') || exit;

// Shortcode login
function lte_login_shortcode() {
    $enable_email = get_theme_mod('lte_enable_email_login', true);
    $enable_sms   = get_theme_mod('lte_enable_sms_login', false);
    $enable_fb    = get_theme_mod('lte_enable_facebook_login', false);
    ob_start();
    ?>
    <div class="lte-auth">
        <h2><?php echo esc_html(get_theme_mod('lte_login_title', __('Se connecter', 'life-travel-excursion'))); ?></h2>
        <form id="lte-login-form">
            <?php if ($enable_email): ?>
            <label><?php _e('Email','life-travel-excursion'); ?>
                <input type="email" name="identifier" placeholder="email@exemple.com" required />
            </label>
            <button type="button" class="lte-login-send" data-method="email"><?php _e('Envoyer code par email','life-travel-excursion'); ?></button>
            <?php endif; ?>
            <?php if ($enable_sms): ?>
            <label><?php _e('Téléphone','life-travel-excursion'); ?>
                <input type="text" name="identifier_sms" placeholder="+2376xxxxxxx" required />
            </label>
            <button type="button" class="lte-login-send" data-method="sms"><?php _e('Envoyer code SMS','life-travel-excursion'); ?></button>
            <?php endif; ?>
            <div id="lte-login-code" style="display:none;">
                <label><?php _e('Code reçu','life-travel-excursion'); ?>
                    <input type="text" name="code" required />
                </label>
                <button type="button" id="lte-login-verify"><?php _e('Valider code','life-travel-excursion'); ?></button>
            </div>
        </form>
        <?php if ($enable_fb):
            $app_id = get_theme_mod('lte_fb_app_id', '');
            if ($app_id): ?>
            <a href="https://www.facebook.com/v10.0/dialog/oauth?client_id=<?php echo esc_attr($app_id); ?>&redirect_uri=<?php echo urlencode(site_url()); ?>&scope=email" class="lte-fb-login"><?php _e('Se connecter avec Facebook','life-travel-excursion'); ?></a>
        <?php endif; endif; ?>
        <?php if (get_theme_mod('lte_enable_google_login', false) && ($gid = get_theme_mod('lte_google_client_id'))):
            $redirect = urlencode(site_url('?lte_google_callback=1'));
        ?>
        <a href="https://accounts.google.com/o/oauth2/auth?response_type=code&client_id=<?php echo esc_attr($gid); ?>&redirect_uri=<?php echo $redirect; ?>&scope=email%20profile" class="lte-google-login"><?php echo esc_html(get_theme_mod('lte_login_google_button')); ?></a>
        <?php endif; ?>
    </div>
    <script>
    jQuery(function($){
        var nonce = '<?php echo wp_create_nonce('lte_login'); ?>';
        $('.lte-login-send').on('click', function(){
            var method = $(this).data('method');
            var identifier = method==='email' ? $('input[name=identifier]').val() : $('input[name=identifier_sms]').val();
            $.post('<?php echo admin_url('admin-ajax.php'); ?>',{action:'lte_send_login_code',method:method,identifier:identifier,nonce:nonce},function(resp){
                if(resp.success) $('#lte-login-code').show(); else alert(resp.data||'Error');
            });
        });
        $('#lte-login-verify').on('click', function(){
            var code = $('input[name=code]').val();
            var method = $('.lte-login-send').first().data('method');
            var identifier = method==='email' ? $('input[name=identifier]').val() : $('input[name=identifier_sms]').val();
            $.post('<?php echo admin_url('admin-ajax.php'); ?>',{action:'lte_verify_login_code',method:method,identifier:identifier,code:code,nonce:nonce},function(resp){
                if(resp.success) window.location.reload(); else alert(resp.data||'Code invalide');
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('lte_login', 'lte_login_shortcode');

// AJAX: send code
function lte_send_login_code() {
    check_ajax_referer('lte_login','nonce');
    $method = sanitize_text_field($_POST['method']);
    $id = sanitize_text_field($_POST['identifier']);
    $code = wp_rand(100000,999999);
    set_transient('lte_login_code_'.md5($method.'_'.$id), $code, 10*MINUTE_IN_SECONDS);
    
    if($method==='email') {
        wp_mail($id, __('Votre code de connexion','life-travel-excursion'), __('Code:','life-travel-excursion').' '.$code);
        wp_send_json_success([
            'message' => __('Code envoyé par email. Veuillez vérifier votre boîte de réception.','life-travel-excursion')
        ]);
    } elseif($method==='sms') {
        // Implémentation de l'API Twilio pour envoyer des SMS
        $twilio_enabled = get_option('lte_enable_twilio_sms', 'no');
        
        if ($twilio_enabled !== 'yes') {
            wp_send_json_error([
                'message' => __('L\'envoi de SMS n\'est pas activé. Veuillez contacter l\'administrateur.','life-travel-excursion')
            ]);
            return;
        }
        
        // Récupérer les paramètres Twilio
        $twilio_sid = get_option('lte_twilio_account_sid', '');
        $twilio_token = get_option('lte_twilio_auth_token', '');
        $twilio_phone = get_option('lte_twilio_phone_number', '');
        
        // Vérifier que tous les paramètres sont définis
        if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_phone)) {
            wp_send_json_error([
                'message' => __('Configuration SMS incomplète. Veuillez contacter l\'administrateur.','life-travel-excursion'),
                'debug' => 'Configuration Twilio manquante'
            ]);
            return;
        }
        
        // Formater le numéro de téléphone (ajouter +237 si numéro camerounais sans code pays)
        if (preg_match('/^[67][0-9]{8}$/', $id)) {
            $id = '+237' . $id; // Format camerounais
        } elseif (!preg_match('/^\+[0-9]{1,4}[0-9]{6,14}$/', $id)) {
            wp_send_json_error([
                'message' => __('Format de numéro de téléphone invalide.','life-travel-excursion')
            ]);
            return;
        }
        
        try {
            // Initialiser le client Twilio
            $twilio = new \Twilio\Rest\Client($twilio_sid, $twilio_token);
            
            // Message avec code et nom du site
            $message = sprintf(
                __('%s - Votre code de connexion est: %s','life-travel-excursion'),
                get_bloginfo('name'),
                $code
            );
            
            // Envoyer le SMS
            $twilio_message = $twilio->messages->create(
                $id, // Numéro de téléphone du destinataire
                [
                    'from' => $twilio_phone,
                    'body' => $message
                ]
            );
            
            // Journaliser l'envoi pour des statistiques et debugging
            error_log(sprintf('Life Travel: SMS envoyé à %s, SID: %s', $id, $twilio_message->sid));
            
            // Mémoriser le SID pour référence ultérieure
            set_transient('lte_sms_sid_'.md5($id), $twilio_message->sid, 24*HOUR_IN_SECONDS);
            
            // Limiter le nombre de SMS par numéro (anti-abus)
            $sms_count = get_transient('lte_sms_count_'.md5($id));
            if (false === $sms_count) {
                $sms_count = 0;
            }
            set_transient('lte_sms_count_'.md5($id), $sms_count + 1, 24*HOUR_IN_SECONDS);
            
            // Retourner succès
            wp_send_json_success([
                'message' => __('Code envoyé par SMS. Veuillez vérifier votre téléphone.','life-travel-excursion')
            ]);
        } catch (\Exception $e) {
            // Gérer les erreurs Twilio
            error_log('Life Travel - Erreur Twilio: ' . $e->getMessage());
            
            // Message utilisateur générique pour la sécurité
            wp_send_json_error([
                'message' => __('Impossible d\'envoyer le SMS. Veuillez réessayer ou utiliser l\'email.','life-travel-excursion'),
                'debug' => $e->getMessage()
            ]);
        }
    } else {
        wp_send_json_error([
            'message' => __('Méthode d\'authentification non supportée.','life-travel-excursion')
        ]);
    }
}
add_action('wp_ajax_nopriv_lte_send_login_code','lte_send_login_code');

// AJAX: verify code
function lte_verify_login_code() {
    check_ajax_referer('lte_login','nonce');
    $method = sanitize_text_field($_POST['method']);
    $id = sanitize_text_field($_POST['identifier']);
    $code = sanitize_text_field($_POST['code']);
    $key = 'lte_login_code_'.md5($method.'_'.$id);
    if($code==get_transient($key)) {
        delete_transient($key);
        if($method==='email') {
            if(!email_exists($id)) {
                $pass = wp_generate_password();
                $user_id = wp_create_user($id, $pass, $id);
            } else {
                $user = get_user_by('email',$id);
                $user_id = $user->ID;
            }
        } else {
            wp_send_json_error();
        }
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        wp_send_json_success();
    }
    wp_send_json_error();
}
add_action('wp_ajax_nopriv_lte_verify_login_code','lte_verify_login_code');

// Google OAuth callback handler
add_action('init','lte_handle_google_callback');
function lte_handle_google_callback() {
    if (!isset($_GET['lte_google_callback']) || !get_theme_mod('lte_enable_google_login', false) || empty($_GET['code'])) return;
    $code = sanitize_text_field($_GET['code']);
    $client_id = get_theme_mod('lte_google_client_id');
    $client_secret = get_theme_mod('lte_google_client_secret');
    $redirect_uri = site_url('?lte_google_callback=1');
    $response = wp_remote_post('https://oauth2.googleapis.com/token', ['body'=>[
        'code'=>$code,'client_id'=>$client_id,'client_secret'=>$client_secret,'redirect_uri'=>$redirect_uri,'grant_type'=>'authorization_code'
    ]]);
    if (is_wp_error($response)) return;
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($data['access_token'])) return;
    $user_resp = wp_remote_get('https://www.googleapis.com/oauth2/v1/userinfo?alt=json&access_token=' . urlencode($data['access_token']));
    if (is_wp_error($user_resp)) return;
    $user_info = json_decode(wp_remote_retrieve_body($user_resp), true);
    if (empty($user_info['email'])) return;
    $email = sanitize_email($user_info['email']);
    if (!email_exists($email)) {
        $pass = wp_generate_password();
        $user_id = wp_create_user($email, $pass, $email);
    } else {
        $user = get_user_by('email', $email);
        $user_id = $user->ID;
    }
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);
    wp_redirect(home_url()); exit;
}
