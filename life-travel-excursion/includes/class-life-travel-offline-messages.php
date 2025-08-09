<?php
/**
 * Life Travel Offline Messages
 * 
 * Cette classe gère les messages personnalisables affichés aux utilisateurs 
 * lorsqu'ils tentent d'effectuer des actions en mode hors ligne.
 *
 * @package Life Travel Excursion
 * @since 2.4.0
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Classe Life_Travel_Offline_Messages
 */
class Life_Travel_Offline_Messages {

    /**
     * Instance unique (pattern Singleton)
     *
     * @var Life_Travel_Offline_Messages
     */
    private static $instance = null;
    
    /**
     * Messages par défaut
     *
     * @var array
     */
    private $default_messages;
    
    /**
     * Messages personnalisés
     *
     * @var array
     */
    private $custom_messages;
    
    /**
     * Messages par défaut non traduits
     * 
     * @var array
     */
    private $raw_messages;

    /**
     * Indique si les messages ont été initialisés
     * 
     * @var bool
     */
    private $initialized = false;

    /**
     * Constructeur - n'utilise pas de traductions pour éviter les erreurs
     */
    private function __construct() {
        // Définir les messages par défaut (sans traduction)
        $this->raw_messages = array(
            'reservation' => array(
                'title' => 'Réservation en mode hors ligne',
                'message' => 'Votre réservation a été enregistrée localement sur votre appareil. Une fois la connexion rétablie, veuillez revisiter cette page pour synchroniser votre réservation avec notre système. Aucun paiement ne sera traité et votre place ne sera pas garantie tant que la synchronisation n\'aura pas été effectuée.',
                'action_text' => 'Je comprends',
                'icon' => 'calendar'
            ),
            'payment' => array(
                'title' => 'Paiement impossible en mode hors ligne',
                'message' => 'Les paiements ne peuvent pas être traités en mode hors ligne. Vos informations de paiement n\'ont pas été enregistrées pour des raisons de sécurité. Veuillez vous reconnecter à Internet pour finaliser votre paiement.',
                'action_text' => 'J\'ai compris',
                'icon' => 'credit-card'
            ),
            'contact' => array(
                'title' => 'Message enregistré localement',
                'message' => 'Votre message a été enregistré sur votre appareil. Il sera automatiquement envoyé dès que vous serez à nouveau connecté à Internet. Vous recevrez une notification lorsque votre message aura été envoyé.',
                'action_text' => 'D\'accord',
                'icon' => 'email'
            ),
            'cart_add' => array(
                'title' => 'Produit ajouté en mode hors ligne',
                'message' => 'Ce produit a été ajouté à votre panier local. Veuillez noter que les prix et la disponibilité peuvent avoir changé depuis votre dernière connexion. Votre panier sera mis à jour automatiquement lorsque vous serez de retour en ligne.',
                'action_text' => 'Continuer mes achats',
                'icon' => 'cart'
            ),
            'synchronization' => array(
                'title' => 'Synchronisation nécessaire',
                'message' => 'Certaines actions effectuées en mode hors ligne doivent être synchronisées avec notre système. Veuillez vous connecter à Internet dès que possible pour finaliser vos opérations et garantir que vos réservations soient prises en compte.',
                'action_text' => 'Synchroniser maintenant',
                'icon' => 'update'
            ),
            'general' => array(
                'title' => 'Mode hors ligne actif',
                'message' => 'Vous naviguez actuellement en mode hors ligne. Certaines fonctionnalités sont limitées. Les informations affichées peuvent ne pas être à jour et vos actions seront enregistrées localement jusqu\'à ce que vous soyez de nouveau en ligne.',
                'action_text' => 'J\'ai compris',
                'icon' => 'wifi-off'
            )
        );

        // Initialiser les hooks - mais pas les messages traduits
        $this->init_hooks();

        // Ajouter un hook pour initialiser les traductions au bon moment
        add_action('init', array($this, 'init_messages'), 10);
    }
    
    /**
     * Initialise les messages traduits - appelé au hook init
     */
    public function init_messages() {
        if ($this->initialized) {
            return;
        }

        // Définir les messages par défaut avec traduction
        $this->default_messages = array();
        
        // Traduire tous les messages
        foreach ($this->raw_messages as $type => $content) {
            $this->default_messages[$type] = array(
                'title' => __($content['title'], 'life-travel-excursion'),
                'message' => __($content['message'], 'life-travel-excursion'),
                'action_text' => __($content['action_text'], 'life-travel-excursion'),
                'icon' => $content['icon']
            );
        }
        
        // Charger les messages personnalisés
        $this->load_custom_messages();
        
        $this->initialized = true;
    }

    /**
     * Obtenir l'instance unique
     * 
     * @return Life_Travel_Offline_Messages
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Initialiser les hooks
     */
    private function init_hooks() {
        // Enregistrer les scripts et styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Ajouter les messages dans le footer pour être utilisés par JavaScript
        add_action('wp_footer', array($this, 'add_messages_to_footer'), 30);
        
        // AJAX pour enregistrer les messages personnalisés
        add_action('wp_ajax_life_travel_save_offline_messages', array($this, 'ajax_save_messages'));
    }
    
    /**
     * Charger les messages personnalisés depuis les options
     */
    private function load_custom_messages() {
        $this->custom_messages = get_option('life_travel_offline_custom_messages', array());
    }
    
    /**
     * Enregistrer les assets nécessaires
     */
    public function enqueue_assets() {
        // Ne pas charger en admin
        if (is_admin()) {
            return;
        }
        
        // CSS pour les notifications
        wp_enqueue_style(
            'life-travel-offline-notifications',
            plugins_url('assets/css/offline-notifications.css', dirname(__FILE__)),
            array(),
            LIFE_TRAVEL_VERSION
        );
        
        // JavaScript pour gérer les notifications
        wp_enqueue_script(
            'life-travel-offline-notifications',
            plugins_url('assets/js/offline-notifications.js', dirname(__FILE__)),
            array('jquery', 'life-travel-network-detector'),
            LIFE_TRAVEL_VERSION,
            true
        );
    }
    
    /**
     * Ajouter les messages dans le footer pour être utilisés par JavaScript
     */
    public function add_messages_to_footer() {
        // Fusionner les messages par défaut avec les messages personnalisés
        $messages = $this->get_all_messages();
        
        // Inclure la structure HTML de base pour les notifications (sera cachée par défaut)
        ?>
        <div id="life-travel-offline-notification" class="life-travel-offline-notification" style="display:none;">
            <div class="life-travel-offline-notification-inner">
                <div class="life-travel-offline-notification-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="life-travel-offline-notification-content">
                    <h3 class="life-travel-offline-notification-title"></h3>
                    <div class="life-travel-offline-notification-message"></div>
                    <div class="life-travel-offline-notification-actions">
                        <button class="life-travel-offline-notification-button"></button>
                    </div>
                </div>
                <button class="life-travel-offline-notification-close">&times;</button>
            </div>
        </div>
        
        <script>
        // Définir les messages pour le JavaScript
        var life_travel_offline_messages = <?php echo wp_json_encode($messages); ?>;
        </script>
        <?php
    }
    
    /**
     * Récupère tous les messages (défaut + personnalisés)
     * 
     * @return array Messages combinés
     */
    public function get_all_messages() {
        $messages = $this->default_messages;
        
        // Remplacer les messages par défaut par les messages personnalisés s'ils existent
        foreach ($this->custom_messages as $type => $message_data) {
            if (isset($messages[$type])) {
                // Ne remplacer que les champs définis
                foreach ($message_data as $field => $value) {
                    if (!empty($value)) {
                        $messages[$type][$field] = $value;
                    }
                }
            }
        }
        
        return $messages;
    }
    
    /**
     * Récupérer un message spécifique
     * 
     * @param string $type Type de message (reservation, payment, etc.)
     * @return array Données du message
     */
    public function get_message($type) {
        $messages = $this->get_all_messages();
        
        if (isset($messages[$type])) {
            return $messages[$type];
        }
        
        // Retourner le message général par défaut
        return $messages['general'];
    }
    
    /**
     * Gestionnaire AJAX pour sauvegarder les messages personnalisés
     */
    public function ajax_save_messages() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_offline_messages_nonce')) {
            wp_send_json_error(array('message' => __('Sécurité non vérifiée.', 'life-travel-excursion')));
            exit;
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas la permission d\'effectuer cette action.', 'life-travel-excursion')));
            exit;
        }
        
        // Récupérer les données
        $custom_messages = array();
        
        // Traiter chaque type de message
        foreach ($this->default_messages as $type => $default_data) {
            $custom_messages[$type] = array();
            
            // Traiter chaque champ du message
            foreach (array('title', 'message', 'action_text') as $field) {
                $key = "life_travel_offline_{$type}_{$field}";
                
                if (isset($_POST[$key])) {
                    $custom_messages[$type][$field] = wp_kses_post($_POST[$key]);
                }
            }
        }
        
        // Sauvegarder les messages personnalisés
        update_option('life_travel_offline_custom_messages', $custom_messages);
        
        // Renvoyer une réponse de succès
        wp_send_json_success(array(
            'message' => __('Messages enregistrés avec succès.', 'life-travel-excursion')
        ));
    }
}

/**
 * Fonction pratique pour accéder à l'instance de Life_Travel_Offline_Messages
 * 
 * @return Life_Travel_Offline_Messages
 */
function life_travel_offline_messages() {
    return Life_Travel_Offline_Messages::get_instance();
}

// Initialisation
life_travel_offline_messages();
