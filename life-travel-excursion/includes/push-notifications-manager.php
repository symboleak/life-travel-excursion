<?php
/**
 * Gestionnaire de notifications push pour Life Travel Excursion
 * Permet d'envoyer des notifications aux utilisateurs pour les rappels d'excursions et promotions
 */

defined('ABSPATH') || exit;

class Life_Travel_Push_Notifications {
    /**
     * Clés VAPID pour l'identification du serveur (à générer en production)
     */
    private $vapid_public_key = 'BNbxGYDvlx4zvs2Ss2lFSfL5bpwRbDXkDVcH7uHlHN9YwLxP6YF9V5xPx1j4jcB8npH0NJXeGG9EEfKMxC0xmH4';
    private $vapid_private_key = 'qgXbGI_Xow-LkFHdcvHBRA9U3_ztRcB9RrGCvL156hQ'; // À remplacer en production

    /**
     * Table de base de données pour les abonnements
     */
    private $table_name;

    /**
     * Constructeur
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'lte_push_subscriptions';

        // Créer la table des abonnements si nécessaire
        add_action('init', array($this, 'create_subscriptions_table'));

        // Enregistrer les styles et scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Ajouter les endpoints AJAX
        add_action('wp_ajax_life_travel_push_subscribe', array($this, 'handle_subscription'));
        add_action('wp_ajax_nopriv_life_travel_push_subscribe', array($this, 'handle_subscription'));
        add_action('wp_ajax_life_travel_push_unsubscribe', array($this, 'handle_unsubscription'));
        add_action('wp_ajax_nopriv_life_travel_push_unsubscribe', array($this, 'handle_unsubscription'));

        // Ajouter les options dans le Customizer
        add_action('customize_register', array($this, 'register_customizer_settings'));

        // Hook pour envoyer des notifications automatiques (rappels d'excursions)
        add_action('life_travel_excursion_reminder', array($this, 'send_excursion_reminder'));

        // Hook pour l'envoi de notifications de promotion
        add_action('life_travel_send_promo_notification', array($this, 'send_promo_notification'));

        // Ajouter la gestion push au service worker
        add_action('wp_footer', array($this, 'add_push_support_script'));
    }

    /**
     * Crée la table des abonnements push
     */
    public function create_subscriptions_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            endpoint varchar(512) NOT NULL,
            auth_token varchar(128) NOT NULL,
            public_key varchar(128) NOT NULL,
            subscription_data longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY endpoint (endpoint(191))
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Enregistre les fichiers CSS et JS
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'life-travel-notifications',
            LIFE_TRAVEL_ASSETS_URL . 'css/notifications.css',
            array(),
            LIFE_TRAVEL_EXCURSION_VERSION
        );

        wp_enqueue_script(
            'life-travel-push-notifications',
            LIFE_TRAVEL_ASSETS_URL . 'js/push-notifications.js',
            array('jquery', 'localforage'),
            LIFE_TRAVEL_EXCURSION_VERSION,
            true
        );

        // Passer le nonce au script pour sécuriser les requêtes AJAX
        wp_add_inline_script(
            'life-travel-push-notifications',
            'document.body.setAttribute("data-push-nonce", "' . wp_create_nonce('life_travel_push_nonce') . '");',
            'before'
        );
    }

    /**
     * Gestionnaire d'abonnement aux notifications push
     */
    public function handle_subscription() {
        // Vérifier le nonce
        check_ajax_referer('life_travel_push_nonce', 'security');

        // Valider les données d'abonnement
        if (empty($_POST['subscription'])) {
            wp_send_json_error(array('message' => 'Données d\'abonnement manquantes'));
            return;
        }

        // Nettoyer et valider les données JSON
        $subscription_data = sanitize_text_field($_POST['subscription']);
        $subscription = json_decode($subscription_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => 'Format JSON invalide'));
            return;
        }

        // Valider la structure minimale attendue
        if (empty($subscription['endpoint']) || empty($subscription['keys']['auth']) || empty($subscription['keys']['p256dh'])) {
            wp_send_json_error(array('message' => 'Données d\'abonnement incomplètes'));
            return;
        }

        global $wpdb;

        // Vérifier si l'endpoint existe déjà
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE endpoint = %s",
            $subscription['endpoint']
        ));

        $user_id = get_current_user_id(); // 0 si non connecté

        if ($existing) {
            // Mettre à jour l'abonnement existant
            $updated = $wpdb->update(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'auth_token' => $subscription['keys']['auth'],
                    'public_key' => $subscription['keys']['p256dh'],
                    'subscription_data' => $subscription_data,
                    'updated_at' => current_time('mysql')
                ),
                array('endpoint' => $subscription['endpoint'])
            );

            if ($updated !== false) {
                wp_send_json_success(array('message' => 'Abonnement mis à jour'));
            } else {
                wp_send_json_error(array('message' => 'Erreur lors de la mise à jour de l\'abonnement'));
            }
        } else {
            // Créer un nouvel abonnement
            $inserted = $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'endpoint' => $subscription['endpoint'],
                    'auth_token' => $subscription['keys']['auth'],
                    'public_key' => $subscription['keys']['p256dh'],
                    'subscription_data' => $subscription_data,
                    'created_at' => current_time('mysql')
                )
            );

            if ($inserted) {
                wp_send_json_success(array('message' => 'Abonnement créé avec succès'));
            } else {
                wp_send_json_error(array('message' => 'Erreur lors de la création de l\'abonnement'));
            }
        }
    }

    /**
     * Gestionnaire de désabonnement
     */
    public function handle_unsubscription() {
        // Vérifier le nonce
        check_ajax_referer('life_travel_push_nonce', 'security');

        // Valider les données d'abonnement
        if (empty($_POST['subscription'])) {
            wp_send_json_error(array('message' => 'Données d\'abonnement manquantes'));
            return;
        }

        // Nettoyer et valider les données JSON
        $subscription_data = sanitize_text_field($_POST['subscription']);
        $subscription = json_decode($subscription_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => 'Format JSON invalide'));
            return;
        }

        // Valider l'endpoint
        if (empty($subscription['endpoint'])) {
            wp_send_json_error(array('message' => 'Endpoint manquant'));
            return;
        }

        global $wpdb;

        // Supprimer l'abonnement de la base de données
        $deleted = $wpdb->delete(
            $this->table_name,
            array('endpoint' => $subscription['endpoint'])
        );

        if ($deleted) {
            wp_send_json_success(array('message' => 'Désabonnement réussi'));
        } else {
            wp_send_json_error(array('message' => 'Abonnement non trouvé ou erreur lors du désabonnement'));
        }
    }

    /**
     * Enregistre les options dans le Customizer
     */
    public function register_customizer_settings($wp_customize) {
        // Section pour les notifications push
        $wp_customize->add_section('lte_push_notifications', array(
            'title' => __('Notifications Push', 'life-travel-excursion'),
            'priority' => 35,
            'description' => __('Paramètres pour les notifications push', 'life-travel-excursion'),
        ));

        // Option pour activer/désactiver les notifications push
        $wp_customize->add_setting('lte_push_enabled', array(
            'default' => true,
            'sanitize_callback' => 'absint',
        ));

        $wp_customize->add_control('lte_push_enabled', array(
            'label' => __('Activer les notifications push', 'life-travel-excursion'),
            'section' => 'lte_push_notifications',
            'type' => 'checkbox',
        ));

        // Option pour le délai de rappel avant une excursion
        $wp_customize->add_setting('lte_push_reminder_days', array(
            'default' => 1,
            'sanitize_callback' => 'absint',
        ));

        $wp_customize->add_control('lte_push_reminder_days', array(
            'label' => __('Jours avant l\'excursion pour envoyer un rappel', 'life-travel-excursion'),
            'section' => 'lte_push_notifications',
            'type' => 'number',
            'input_attrs' => array(
                'min' => 1,
                'max' => 7,
                'step' => 1,
            ),
        ));

        // Option pour le message de rappel
        $wp_customize->add_setting('lte_push_reminder_text', array(
            'default' => __('Rappel : Votre excursion "%title%" est prévue pour demain!', 'life-travel-excursion'),
            'sanitize_callback' => 'sanitize_text_field',
        ));

        $wp_customize->add_control('lte_push_reminder_text', array(
            'label' => __('Texte pour les notifications de rappel', 'life-travel-excursion'),
            'section' => 'lte_push_notifications',
            'type' => 'text',
            'description' => __('Utilisez %title% pour le nom de l\'excursion et %date% pour sa date.', 'life-travel-excursion'),
        ));

        // Option pour activer les notifications de promotion
        $wp_customize->add_setting('lte_push_promo_enabled', array(
            'default' => true,
            'sanitize_callback' => 'absint',
        ));

        $wp_customize->add_control('lte_push_promo_enabled', array(
            'label' => __('Activer les notifications de promotion', 'life-travel-excursion'),
            'section' => 'lte_push_notifications',
            'type' => 'checkbox',
        ));
    }

    /**
     * Envoie une notification de rappel pour une excursion
     */
    public function send_excursion_reminder($order_id) {
        if (!get_theme_mod('lte_push_enabled', true)) {
            return false;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $order_items = $order->get_items();
        $excursion_details = [];

        foreach ($order_items as $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if ($product && $product->get_type() === 'excursion') {
                $excursion_date = $item->get_meta('_excursion_date');
                
                if ($excursion_date) {
                    $excursion_details[] = [
                        'title' => $product->get_name(),
                        'date' => $excursion_date,
                        'product_id' => $product_id
                    ];
                }
            }
        }

        if (empty($excursion_details)) {
            return false;
        }

        // Récupérer l'utilisateur associé à la commande
        $user_id = $order->get_user_id();
        $first_excursion = $excursion_details[0];

        // Construire le message de notification
        $reminder_template = get_theme_mod('lte_push_reminder_text', __('Rappel : Votre excursion "%title%" est prévue pour demain!', 'life-travel-excursion'));
        $message = str_replace(
            ['%title%', '%date%'],
            [$first_excursion['title'], date_i18n(get_option('date_format'), strtotime($first_excursion['date']))],
            $reminder_template
        );

        // Récupérer l'URL de la page produit
        $product_url = get_permalink($first_excursion['product_id']);

        // Préparer les données de notification
        $notification_data = [
            'title' => __('Rappel d\'excursion', 'life-travel-excursion'),
            'body' => $message,
            'icon' => wp_get_attachment_url(get_theme_mod('lte_push_icon')),
            'badge' => wp_get_attachment_url(get_theme_mod('lte_push_badge')),
            'data' => [
                'url' => $product_url,
                'type' => 'reminder',
                'excursion_id' => $first_excursion['product_id']
            ]
        ];

        return $this->send_notification_to_user($user_id, $notification_data);
    }

    /**
     * Envoi d'une notification de promotion
     */
    public function send_promo_notification($promo_id) {
        if (!get_theme_mod('lte_push_promo_enabled', true)) {
            return false;
        }

        $promo = get_post($promo_id);
        if (!$promo || $promo->post_type !== 'promotion') {
            return false;
        }

        // Récupérer les données de la promotion
        $discount = get_post_meta($promo_id, '_promo_discount', true);
        $excursion_id = get_post_meta($promo_id, '_promo_excursion_id', true);
        $excursion = wc_get_product($excursion_id);

        if (!$excursion) {
            return false;
        }

        // Préparer le message
        $title = sprintf(
            __('Promo: %s%% de réduction!', 'life-travel-excursion'),
            $discount
        );
        
        $message = sprintf(
            __('Profitez de %s%% de réduction sur l\'excursion "%s". Offre limitée!', 'life-travel-excursion'),
            $discount,
            $excursion->get_name()
        );

        // Données de notification
        $notification_data = [
            'title' => $title,
            'body' => $message,
            'icon' => wp_get_attachment_url(get_theme_mod('lte_push_icon')),
            'badge' => wp_get_attachment_url(get_theme_mod('lte_push_badge')),
            'data' => [
                'url' => get_permalink($excursion_id),
                'type' => 'promo',
                'promo_id' => $promo_id,
                'excursion_id' => $excursion_id
            ]
        ];

        // Envoyer à tous les abonnés
        return $this->send_notification_to_all($notification_data);
    }

    /**
     * Envoie une notification à tous les abonnés
     */
    public function send_notification_to_all($data) {
        global $wpdb;

        // Récupérer tous les abonnements
        $subscriptions = $wpdb->get_results(
            "SELECT subscription_data FROM {$this->table_name}",
            ARRAY_A
        );

        if (empty($subscriptions)) {
            return false;
        }

        $success_count = 0;
        $fail_count = 0;

        foreach ($subscriptions as $row) {
            $subscription = json_decode($row['subscription_data'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE || empty($subscription)) {
                $fail_count++;
                continue;
            }

            $result = $this->send_web_push($subscription, $data);
            
            if ($result) {
                $success_count++;
            } else {
                $fail_count++;
            }
        }

        return [
            'success' => $success_count > 0,
            'success_count' => $success_count,
            'fail_count' => $fail_count
        ];
    }

    /**
     * Envoie une notification à un utilisateur spécifique
     */
    public function send_notification_to_user($user_id, $data) {
        if (!$user_id) {
            return false;
        }

        global $wpdb;

        // Récupérer les abonnements de l'utilisateur
        $subscriptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT subscription_data FROM {$this->table_name} WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );

        if (empty($subscriptions)) {
            return false;
        }

        $success_count = 0;
        $fail_count = 0;

        foreach ($subscriptions as $row) {
            $subscription = json_decode($row['subscription_data'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE || empty($subscription)) {
                $fail_count++;
                continue;
            }

            $result = $this->send_web_push($subscription, $data);
            
            if ($result) {
                $success_count++;
            } else {
                $fail_count++;
            }
        }

        return [
            'success' => $success_count > 0,
            'success_count' => $success_count,
            'fail_count' => $fail_count
        ];
    }

    /**
     * Envoie une notification Web Push à un endpoint spécifique
     */
    private function send_web_push($subscription, $data) {
        if (!class_exists('Minishlink\WebPush\WebPush')) {
            // Vérifier si la classe WebPush existe, sinon charger la bibliothèque
            if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'vendor/autoload.php')) {
                require_once LIFE_TRAVEL_EXCURSION_DIR . 'vendor/autoload.php';
            } else {
                error_log('Bibliothèque WebPush non trouvée');
                return false;
            }
        }

        try {
            $auth = [
                'VAPID' => [
                    'subject' => get_bloginfo('url'),
                    'publicKey' => $this->vapid_public_key,
                    'privateKey' => $this->vapid_private_key,
                ],
            ];

            $webPush = new \Minishlink\WebPush\WebPush($auth);

            $report = $webPush->sendNotification(
                $subscription['endpoint'],
                json_encode($data),
                $subscription['keys']['p256dh'],
                $subscription['keys']['auth']
            );

            return $report->isSuccess();
        } catch (Exception $e) {
            error_log('Erreur lors de l\'envoi de la notification push: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ajoute le script d'intégration au service worker
     */
    public function add_push_support_script() {
        if (!get_theme_mod('lte_push_enabled', true)) {
            return;
        }
        ?>
        <script>
            // Ajouter le support des notifications push au service worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(registration => {
                    // Événement push
                    registration.addEventListener('push', event => {
                        if (!event.data) {
                            return;
                        }

                        try {
                            const notification = event.data.json();
                            event.waitUntil(
                                self.registration.showNotification(notification.title, {
                                    body: notification.body,
                                    icon: notification.icon || '<?php echo esc_url(LIFE_TRAVEL_ASSETS_URL . 'img/notification-icon.png'); ?>',
                                    badge: notification.badge || '<?php echo esc_url(LIFE_TRAVEL_ASSETS_URL . 'img/notification-badge.png'); ?>',
                                    data: notification.data
                                })
                            );
                        } catch (e) {
                            console.error('Erreur lors du traitement de la notification push:', e);
                        }
                    });

                    // Événement de clic sur notification
                    registration.addEventListener('notificationclick', event => {
                        event.notification.close();

                        if (event.notification.data && event.notification.data.url) {
                            // Naviguer vers l'URL spécifiée
                            event.waitUntil(
                                clients.openWindow(event.notification.data.url)
                            );
                        }
                    });
                });
            }
        </script>
        <?php
    }
}

// Instancier la classe
$lte_push_notifications = new Life_Travel_Push_Notifications();
