<?php
/**
 * Intégration des préférences de notification dans l'espace client
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

/**
 * Classe d'intégration des préférences de notification dans l'espace client
 */
class Life_Travel_User_Notification_Settings {
    /**
     * Instance de la classe
     */
    private static $instance = null;
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Ajouter un nouvel onglet dans WooCommerce Mon Compte
        add_filter('woocommerce_account_menu_items', [$this, 'add_notification_menu_item']);
        add_action('init', [$this, 'add_notification_endpoint']);
        add_action('woocommerce_account_notifications_endpoint', [$this, 'notification_preferences_content']);
        
        // S'assurer que l'endpoint est disponible
        add_filter('query_vars', [$this, 'add_notification_query_vars']);
        add_action('woocommerce_after_account_navigation', [$this, 'add_notification_endpoint_url']);
        
        // Scripts et styles pour la page de préférences
        add_action('wp_enqueue_scripts', [$this, 'enqueue_notification_scripts']);
    }
    
    /**
     * Récupère l'instance unique
     *
     * @return Life_Travel_User_Notification_Settings
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Ajoute l'onglet Notifications au menu Mon Compte
     *
     * @param array $menu_items Items du menu
     * @return array Items du menu modifiés
     */
    public function add_notification_menu_item($menu_items) {
        // Ajouter après l'onglet "Détails du compte"
        $position = array_search('edit-account', array_keys($menu_items));
        
        if ($position !== false) {
            $new_items = array_slice($menu_items, 0, $position + 1, true);
            $new_items['notifications'] = __('Notifications', 'life-travel-excursion');
            $new_items = array_merge($new_items, array_slice($menu_items, $position + 1, null, true));
            
            return $new_items;
        }
        
        // Si "edit-account" n'existe pas, ajouter à la fin
        $menu_items['notifications'] = __('Notifications', 'life-travel-excursion');
        
        return $menu_items;
    }
    
    /**
     * Ajoute l'endpoint pour les notifications
     */
    public function add_notification_endpoint() {
        add_rewrite_endpoint('notifications', EP_ROOT | EP_PAGES);
        
        // Vérifier si les règles de réécriture doivent être rafraîchies
        $option = get_option('lte_notifications_endpoint_flushed', false);
        
        if (!$option) {
            flush_rewrite_rules();
            update_option('lte_notifications_endpoint_flushed', true);
        }
    }
    
    /**
     * Ajoute la variable de requête pour l'endpoint
     *
     * @param array $vars Variables de requête
     * @return array Variables de requête modifiées
     */
    public function add_notification_query_vars($vars) {
        $vars[] = 'notifications';
        return $vars;
    }
    
    /**
     * Ajoute l'URL de l'endpoint pour JavaScript
     */
    public function add_notification_endpoint_url() {
        echo '<script type="text/javascript">var lte_notifications_endpoint = "' . esc_url(wc_get_endpoint_url('notifications')) . '";</script>';
    }
    
    /**
     * Enqueue les scripts et styles pour la page de préférences
     */
    public function enqueue_notification_scripts() {
        if (is_account_page() && is_wc_endpoint_url('notifications')) {
            wp_enqueue_style('lte-notifications-style', LIFE_TRAVEL_EXCURSION_URL . 'assets/css/user-notifications.css', [], LIFE_TRAVEL_EXCURSION_VERSION);
            wp_enqueue_script('lte-notifications-script', LIFE_TRAVEL_EXCURSION_URL . 'assets/js/user-notifications.js', ['jquery'], LIFE_TRAVEL_EXCURSION_VERSION, true);
            
            // Ajouter les données localisées
            wp_localize_script('lte-notifications-script', 'lteNotifications', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lte_notification_actions'),
                'saveSuccess' => __('Vos préférences de notification ont été enregistrées avec succès.', 'life-travel-excursion'),
                'saveError' => __('Une erreur s\'est produite lors de l\'enregistrement de vos préférences.', 'life-travel-excursion')
            ]);
        }
    }
    
    /**
     * Affiche le contenu de la page de préférences de notification
     */
    public function notification_preferences_content() {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return;
        }
        
        // Vérifier si nous venons de sauvegarder les préférences
        $saved = isset($_GET['saved']) && $_GET['saved'] === '1';
        
        // Récupérer le gestionnaire de notifications
        $notifications = Life_Travel_User_Notifications::get_instance();
        
        // Récupérer les préférences de l'utilisateur
        $preferences = $notifications->get_user_preferences($user_id);
        
        // Récupérer les types de notifications et canaux disponibles
        $notification_types = $this->get_notification_types();
        $notification_channels = $this->get_notification_channels();
        
        // Regrouper les types de notifications par groupe
        $grouped_types = [];
        foreach ($notification_types as $type_id => $type) {
            $group = isset($type['group']) ? $type['group'] : 'other';
            if (!isset($grouped_types[$group])) {
                $grouped_types[$group] = [];
            }
            $grouped_types[$group][$type_id] = $type;
        }
        
        // Afficher le formulaire
        ?>
        <div class="lte-notification-preferences">
            <h2><?php esc_html_e('Préférences de notification', 'life-travel-excursion'); ?></h2>
            
            <?php if ($saved): ?>
                <div class="woocommerce-message" role="alert">
                    <?php esc_html_e('Vos préférences de notification ont été mises à jour.', 'life-travel-excursion'); ?>
                </div>
            <?php endif; ?>
            
            <p><?php esc_html_e('Choisissez comment vous souhaitez être informé des événements importants concernant vos réservations et votre compte.', 'life-travel-excursion'); ?></p>
            
            <form class="woocommerce-EditAccountForm" action="" method="post">
                <?php wp_nonce_field('lte_save_notification_preferences', 'lte_notification_preferences_nonce'); ?>
                
                <div class="lte-notification-channels">
                    <h3><?php esc_html_e('Canaux de notification', 'life-travel-excursion'); ?></h3>
                    <p class="description"><?php esc_html_e('Sélectionnez les canaux par lesquels vous souhaitez recevoir des notifications.', 'life-travel-excursion'); ?></p>
                    
                    <?php foreach ($notification_channels as $channel_id => $channel): 
                        $available = $notifications->is_channel_available($channel_id);
                        $checked = isset($preferences['channels'][$channel_id]) && $preferences['channels'][$channel_id] && $available;
                        $disabled = !$available;
                        ?>
                        <div class="lte-notification-channel <?php echo $disabled ? 'disabled' : ''; ?>">
                            <label>
                                <input type="checkbox" 
                                       name="lte_notification_channel_<?php echo esc_attr($channel_id); ?>" 
                                       value="1" 
                                       <?php checked($checked); ?>
                                       <?php disabled($disabled); ?>>
                                <span class="channel-icon dashicons <?php echo esc_attr($channel['icon']); ?>"></span>
                                <span class="channel-name"><?php echo esc_html($channel['name']); ?></span>
                                <?php if ($disabled): ?>
                                    <span class="channel-unavailable"><?php esc_html_e('(Non disponible)', 'life-travel-excursion'); ?></span>
                                <?php endif; ?>
                            </label>
                            <p class="description"><?php echo esc_html($channel['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="lte-notification-types">
                    <h3><?php esc_html_e('Types de notifications', 'life-travel-excursion'); ?></h3>
                    <p class="description"><?php esc_html_e('Choisissez les types de notifications que vous souhaitez recevoir.', 'life-travel-excursion'); ?></p>
                    
                    <?php 
                    // Noms des groupes de notifications en français
                    $group_names = [
                        'orders' => __('Commandes et réservations', 'life-travel-excursion'),
                        'account' => __('Compte et sécurité', 'life-travel-excursion'),
                        'marketing' => __('Marketing et promotions', 'life-travel-excursion'),
                        'other' => __('Autres notifications', 'life-travel-excursion')
                    ];
                    
                    // Afficher les notifications par groupe
                    foreach ($grouped_types as $group => $types): 
                        if (empty($types)) continue;
                    ?>
                        <div class="lte-notification-group">
                            <h4><?php echo esc_html($group_names[$group] ?? $group); ?></h4>
                            
                            <?php foreach ($types as $type_id => $type): 
                                $required = isset($type['required']) && $type['required'];
                                $checked = isset($preferences['types'][$type_id]) && $preferences['types'][$type_id];
                                ?>
                                <div class="lte-notification-type <?php echo $required ? 'required' : ''; ?>">
                                    <label>
                                        <input type="checkbox" 
                                               name="lte_notification_type_<?php echo esc_attr($type_id); ?>" 
                                               value="1" 
                                               <?php checked($checked); ?>
                                               <?php disabled($required); ?>>
                                        <span class="type-name"><?php echo esc_html($type['name']); ?></span>
                                        <?php if ($required): ?>
                                            <span class="type-required"><?php esc_html_e('(Obligatoire)', 'life-travel-excursion'); ?></span>
                                        <?php endif; ?>
                                    </label>
                                    <p class="description"><?php echo esc_html($type['description']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="lte-notification-contact">
                    <h3><?php esc_html_e('Informations de contact', 'life-travel-excursion'); ?></h3>
                    <p class="description"><?php esc_html_e('Vérifiez que vos informations de contact sont correctes pour recevoir les notifications.', 'life-travel-excursion'); ?></p>
                    
                    <?php 
                    $user = wp_get_current_user();
                    $phone = get_user_meta($user_id, 'billing_phone', true);
                    ?>
                    
                    <div class="lte-contact-info">
                        <p><strong><?php esc_html_e('Email', 'life-travel-excursion'); ?>:</strong> <?php echo esc_html($user->user_email); ?></p>
                        <p><strong><?php esc_html_e('Téléphone', 'life-travel-excursion'); ?>:</strong> <?php echo !empty($phone) ? esc_html($phone) : esc_html__('Non renseigné', 'life-travel-excursion'); ?></p>
                        
                        <p>
                            <a href="<?php echo esc_url(wc_get_endpoint_url('edit-account')); ?>" class="button">
                                <?php esc_html_e('Mettre à jour mes informations', 'life-travel-excursion'); ?>
                            </a>
                        </p>
                    </div>
                </div>
                
                <p class="woocommerce-form-row">
                    <button type="submit" class="woocommerce-Button button" name="save_notification_preferences" value="Enregistrer">
                        <?php esc_html_e('Enregistrer les préférences', 'life-travel-excursion'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Récupère les types de notifications disponibles
     *
     * @return array Types de notifications
     */
    private function get_notification_types() {
        $notifications = Life_Travel_User_Notifications::get_instance();
        return $this->get_property($notifications, 'notification_types');
    }
    
    /**
     * Récupère les canaux de notification disponibles
     *
     * @return array Canaux de notification
     */
    private function get_notification_channels() {
        $notifications = Life_Travel_User_Notifications::get_instance();
        return $this->get_property($notifications, 'notification_channels');
    }
    
    /**
     * Récupère une propriété protégée/privée d'un objet de manière sécurisée
     *
     * @param object $object Objet
     * @param string $property Nom de la propriété
     * @return mixed Valeur de la propriété ou tableau vide
     */
    private function get_property($object, $property) {
        // Utiliser la réflexion pour accéder aux propriétés privées
        try {
            $reflection = new ReflectionClass($object);
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            return $prop->getValue($object);
        } catch (Exception $e) {
            return [];
        }
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_User_Notification_Settings::get_instance();
});
