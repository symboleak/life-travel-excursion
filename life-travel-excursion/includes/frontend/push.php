<?php
/**
 * Frontend Push Notifications Subscription
 */
defined('ABSPATH') || exit;

// Shortcode to display push subscription prompt
function lte_push_prompt_shortcode() {
    if (!get_theme_mod('lte_push_enabled', false)) return '';
    ob_start(); ?>
    <div id="lte-push-prompt" class="lte-push-prompt">
        <button id="lte-enable-push"><?php _e('Activer les notifications', 'life-travel-excursion'); ?></button>
    </div>
    <script>
    (function(){
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
        navigator.serviceWorker.ready
        .then(function(reg){
            var btn = document.getElementById('lte-enable-push');
            if (!btn) return;
            btn.onclick = function(){
                reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey: '<?php echo esc_js(get_theme_mod('lte_push_vapid_key', '')); ?>'})
                .then(function(sub){
                    // send subscription to server via AJAX
                    jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {action:'lte_save_subscription', sub: JSON.stringify(sub), nonce:'<?php echo wp_create_nonce('lte_push'); ?>'});
                    alert('<?php _e('Notifications activées !', 'life-travel-excursion'); ?>');
                });
            };
        })
        .catch(function(error){
            console.log('Service Worker non prêt:', error);
        });
    })();
    </script>
    <?php return ob_get_clean();
}
add_shortcode('lte_push_prompt','lte_push_prompt_shortcode');

// AJAX to save subscription
function lte_save_subscription() {
    check_ajax_referer('lte_push','nonce');
    $sub = json_decode(stripslashes($_POST['sub']), true);
    if ($sub && is_user_logged_in()) {
        update_user_meta(get_current_user_id(), '_lte_push_subscription', $sub);
        wp_send_json_success();
    }
    wp_send_json_error();
}
add_action('wp_ajax_lte_save_subscription','lte_save_subscription');
add_action('wp_ajax_nopriv_lte_save_subscription','lte_save_subscription');
