<?php
/**
 * Life Travel - Paramètres de notification admin
 * 
 * Interface permettant aux administrateurs de configurer
 * les notifications email et WhatsApp pour les réservations
 * 
 * @package Life Travel Excursion
 * @version 2.3.3
 */

defined('ABSPATH') || exit;

class Life_Travel_Notification_Settings {
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Ajouter le menu
        add_action('admin_menu', array($this, 'add_menu'));
        
        // Enregistrer les paramètres
        add_action('admin_init', array($this, 'register_settings'));
        
        // Ajax pour tester les notifications
        add_action('wp_ajax_test_life_travel_notification', array($this, 'test_notification'));
    }
    
    /**
     * Ajouter le menu dans l'administration
     */
    public function add_menu() {
        add_submenu_page(
            'edit.php?post_type=product',
            __('Notifications Life Travel', 'life-travel-excursion'),
            __('Notifications', 'life-travel-excursion'),
            'manage_options',
            'life-travel-notifications',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Enregistrer les paramètres
     */
    public function register_settings() {
        register_setting('life_travel_notification_settings', 'life_travel_notification_methods');
        register_setting('life_travel_notification_settings', 'life_travel_admin_emails');
        register_setting('life_travel_notification_settings', 'life_travel_admin_whatsapp');
        register_setting('life_travel_notification_settings', 'life_travel_twilio_sid');
        register_setting('life_travel_notification_settings', 'life_travel_twilio_token');
        register_setting('life_travel_notification_settings', 'life_travel_twilio_number');
        register_setting('life_travel_notification_settings', 'life_travel_notification_events');
    }
    
    /**
     * Afficher la page des paramètres
     */
    public function settings_page() {
        // Récupérer les paramètres actuels
        $notification_methods = get_option('life_travel_notification_methods', array('email'));
        $admin_emails = get_option('life_travel_admin_emails', get_option('admin_email'));
        $admin_whatsapp = get_option('life_travel_admin_whatsapp', '');
        $twilio_sid = get_option('life_travel_twilio_sid', '');
        $twilio_token = get_option('life_travel_twilio_token', '');
        $twilio_number = get_option('life_travel_twilio_number', '');
        $notification_events = get_option('life_travel_notification_events', array(
            'booking_new',
            'booking_cancelled',
            'booking_completed'
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Paramètres de notification Life Travel', 'life-travel-excursion'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('life_travel_notification_settings'); ?>
                
                <div class="notice notice-info">
                    <p><?php _e('Configurez comment vous souhaitez être notifié des changements dans les réservations d\'excursions.', 'life-travel-excursion'); ?></p>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Méthodes de notification', 'life-travel-excursion'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="life_travel_notification_methods[]" value="email" <?php checked(in_array('email', $notification_methods)); ?> />
                                    <?php _e('Email', 'life-travel-excursion'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="life_travel_notification_methods[]" value="whatsapp" <?php checked(in_array('whatsapp', $notification_methods)); ?> />
                                    <?php _e('WhatsApp', 'life-travel-excursion'); ?>
                                </label>
                                
                                <p class="description"><?php _e('Sélectionnez les méthodes de notification que vous souhaitez utiliser.', 'life-travel-excursion'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Emails des administrateurs', 'life-travel-excursion'); ?></th>
                        <td>
                            <textarea name="life_travel_admin_emails" rows="3" class="large-text"><?php echo esc_textarea($admin_emails); ?></textarea>
                            <p class="description"><?php _e('Entrez les adresses email qui recevront les notifications (séparées par des virgules).', 'life-travel-excursion'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Numéros WhatsApp', 'life-travel-excursion'); ?></th>
                        <td>
                            <textarea name="life_travel_admin_whatsapp" rows="3" class="large-text"><?php echo esc_textarea($admin_whatsapp); ?></textarea>
                            <p class="description"><?php _e('Entrez les numéros WhatsApp qui recevront les notifications (séparés par des virgules, format international avec +237).', 'life-travel-excursion'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row" colspan="2">
                            <h3><?php _e('Configuration Twilio (pour WhatsApp)', 'life-travel-excursion'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Twilio Account SID', 'life-travel-excursion'); ?></th>
                        <td>
                            <input type="text" name="life_travel_twilio_sid" value="<?php echo esc_attr($twilio_sid); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Twilio Auth Token', 'life-travel-excursion'); ?></th>
                        <td>
                            <input type="password" name="life_travel_twilio_token" value="<?php echo esc_attr($twilio_token); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Numéro Twilio', 'life-travel-excursion'); ?></th>
                        <td>
                            <input type="text" name="life_travel_twilio_number" value="<?php echo esc_attr($twilio_number); ?>" class="regular-text" />
                            <p class="description"><?php _e('Le numéro Twilio au format international (+xxxx).', 'life-travel-excursion'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Événements déclenchant une notification', 'life-travel-excursion'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="life_travel_notification_events[]" value="booking_new" <?php checked(in_array('booking_new', $notification_events)); ?> />
                                    <?php _e('Nouvelle réservation', 'life-travel-excursion'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="life_travel_notification_events[]" value="booking_cancelled" <?php checked(in_array('booking_cancelled', $notification_events)); ?> />
                                    <?php _e('Réservation annulée', 'life-travel-excursion'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="life_travel_notification_events[]" value="booking_completed" <?php checked(in_array('booking_completed', $notification_events)); ?> />
                                    <?php _e('Réservation terminée', 'life-travel-excursion'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="life_travel_notification_events[]" value="booking_modified" <?php checked(in_array('booking_modified', $notification_events)); ?> />
                                    <?php _e('Réservation modifiée', 'life-travel-excursion'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="life_travel_notification_events[]" value="payment_received" <?php checked(in_array('payment_received', $notification_events)); ?> />
                                    <?php _e('Paiement reçu', 'life-travel-excursion'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <div class="notification-test-area" style="margin: 20px 0; padding: 15px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 4px;">
                    <h3><?php _e('Tester les notifications', 'life-travel-excursion'); ?></h3>
                    
                    <p>
                        <button type="button" id="test-email-notification" class="button button-secondary"><?php _e('Tester la notification par email', 'life-travel-excursion'); ?></button>
                        <button type="button" id="test-whatsapp-notification" class="button button-secondary"><?php _e('Tester la notification WhatsApp', 'life-travel-excursion'); ?></button>
                        <span id="test-notification-result" style="margin-left: 10px; display: inline-block;"></span>
                    </p>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <script>
                jQuery(document).ready(function($) {
                    // Test de la notification par email
                    $('#test-email-notification').on('click', function() {
                        var $result = $('#test-notification-result');
                        $result.html('<span style="color: #666;">Envoi du test...</span>');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'test_life_travel_notification',
                                notification_type: 'email',
                                security: '<?php echo wp_create_nonce('test_notification_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    $result.html('<span style="color: green;">' + response.data + '</span>');
                                } else {
                                    $result.html('<span style="color: red;">' + response.data + '</span>');
                                }
                            },
                            error: function() {
                                $result.html('<span style="color: red;">Erreur lors de l\'envoi du test</span>');
                            }
                        });
                    });
                    
                    // Test de la notification WhatsApp
                    $('#test-whatsapp-notification').on('click', function() {
                        var $result = $('#test-notification-result');
                        $result.html('<span style="color: #666;">Envoi du test...</span>');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'test_life_travel_notification',
                                notification_type: 'whatsapp',
                                security: '<?php echo wp_create_nonce('test_notification_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    $result.html('<span style="color: green;">' + response.data + '</span>');
                                } else {
                                    $result.html('<span style="color: red;">' + response.data + '</span>');
                                }
                            },
                            error: function() {
                                $result.html('<span style="color: red;">Erreur lors de l\'envoi du test</span>');
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }
    
    /**
     * Tester l'envoi d'une notification
     */
    public function test_notification() {
        // Vérifier la sécurité
        check_ajax_referer('test_notification_nonce', 'security');
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Vous n\'avez pas les permissions nécessaires.', 'life-travel-excursion'));
        }
        
        $type = isset($_POST['notification_type']) ? sanitize_text_field($_POST['notification_type']) : '';
        
        // En fonction du type de notification
        if ($type === 'email') {
            // Tester la notification par email
            $admin_emails = get_option('life_travel_admin_emails', get_option('admin_email'));
            
            if (empty($admin_emails)) {
                wp_send_json_error(__('Aucune adresse email n\'a été configurée.', 'life-travel-excursion'));
            }
            
            $emails = array_map('trim', explode(',', $admin_emails));
            $subject = __('Test de notification Life Travel', 'life-travel-excursion');
            $message = __("Ceci est un test de notification pour Life Travel Excursion.\n\nSi vous recevez ce message, la configuration de vos notifications par email fonctionne correctement.", 'life-travel-excursion');
            
            $success = false;
            $sent_count = 0;
            
            foreach ($emails as $email) {
                if (is_email($email)) {
                    $result = wp_mail($email, $subject, $message);
                    if ($result) {
                        $sent_count++;
                    }
                }
            }
            
            if ($sent_count > 0) {
                wp_send_json_success(sprintf(__('Test envoyé à %d adresse(s) email.', 'life-travel-excursion'), $sent_count));
            } else {
                wp_send_json_error(__('Erreur lors de l\'envoi du test par email.', 'life-travel-excursion'));
            }
        } elseif ($type === 'whatsapp') {
            // Tester la notification WhatsApp si la fonction est disponible
            if (!function_exists('life_travel_excursion_send_twilio_notification')) {
                wp_send_json_error(__('Le module WhatsApp n\'est pas disponible. Vérifiez que Twilio est correctement configuré.', 'life-travel-excursion'));
            }
            
            $admin_whatsapp = get_option('life_travel_admin_whatsapp', '');
            
            if (empty($admin_whatsapp)) {
                wp_send_json_error(__('Aucun numéro WhatsApp n\'a été configuré.', 'life-travel-excursion'));
            }
            
            $numbers = array_map('trim', explode(',', $admin_whatsapp));
            $message = __("Ceci est un test de notification WhatsApp pour Life Travel Excursion. Si vous recevez ce message, la configuration de vos notifications WhatsApp fonctionne correctement.", 'life-travel-excursion');
            
            $success = false;
            $sent_count = 0;
            
            foreach ($numbers as $number) {
                $result = life_travel_excursion_send_twilio_notification($number, $message, 'whatsapp');
                if ($result) {
                    $sent_count++;
                }
            }
            
            if ($sent_count > 0) {
                wp_send_json_success(sprintf(__('Test WhatsApp envoyé à %d numéro(s).', 'life-travel-excursion'), $sent_count));
            } else {
                wp_send_json_error(__('Erreur lors de l\'envoi du test WhatsApp. Vérifiez les paramètres Twilio.', 'life-travel-excursion'));
            }
        } else {
            wp_send_json_error(__('Type de notification non valide.', 'life-travel-excursion'));
        }
    }
}

// Initialiser les paramètres de notification
new Life_Travel_Notification_Settings();
