<?php
/**
 * Installateur d'index pour optimisation des requêtes de base de données
 *
 * Ce script crée des index supplémentaires dans la base de données pour
 * optimiser les requêtes liées aux réservations d'excursions, particulièrement
 * important pour les environnements à connectivité limitée comme au Cameroun.
 *
 * @package Life Travel Excursion
 * @version 3.0.0
 */

defined('ABSPATH') || exit;

/**
 * Classe d'installation des index de base de données
 */
class Life_Travel_Index_Installer {
    /**
     * Instance unique (Singleton)
     * @var Life_Travel_Index_Installer
     */
    private static $instance = null;
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        // Ajouter la page d'administration pour l'optimisation
        add_action('admin_menu', [$this, 'add_admin_page']);
        
        // Ajouter l'action AJAX pour l'installation des index
        add_action('wp_ajax_life_travel_install_indexes', [$this, 'ajax_install_indexes']);
        
        // Vérifier et suggérer les optimisations au besoin
        add_action('admin_notices', [$this, 'check_index_optimization']);
    }
    
    /**
     * Obtenir l'instance unique
     * @return Life_Travel_Index_Installer
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Ajoute une page d'administration pour l'optimisation de la base de données
     */
    public function add_admin_page() {
        // N'ajouter la page que pour les administrateurs
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_submenu_page(
            'woocommerce',
            __('Optimisation de la base pour Life Travel', 'life-travel-excursion'),
            __('Optimisation Life Travel', 'life-travel-excursion'),
            'manage_options',
            'life-travel-db-optimization',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Affiche la page d'administration pour l'optimisation
     */
    public function render_admin_page() {
        // Vérifier les autorisations
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'life-travel-excursion'));
        }
        
        // Récupérer l'état actuel des index
        $indexes_status = $this->get_indexes_status();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Optimisation de la base de données Life Travel', 'life-travel-excursion'); ?></h1>
            
            <div class="notice notice-info inline">
                <p>
                    <?php _e('Cette page vous permet d\'optimiser la base de données pour améliorer les performances du plugin Life Travel Excursion, particulièrement utile dans les environnements à connectivité limitée comme au Cameroun.', 'life-travel-excursion'); ?>
                </p>
            </div>
            
            <div class="card">
                <h2><?php _e('État des index', 'life-travel-excursion'); ?></h2>
                <p>
                    <?php if ($indexes_status['all_installed']): ?>
                        <span style="color: green; font-weight: bold;">
                            <?php _e('✓ Tous les index sont installés et optimisés.', 'life-travel-excursion'); ?>
                        </span>
                    <?php else: ?>
                        <span style="color: orange; font-weight: bold;">
                            <?php _e('⚠ Certains index ne sont pas installés, les performances peuvent être affectées.', 'life-travel-excursion'); ?>
                        </span>
                    <?php endif; ?>
                </p>
                
                <table class="widefat" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php _e('Table', 'life-travel-excursion'); ?></th>
                            <th><?php _e('Champ', 'life-travel-excursion'); ?></th>
                            <th><?php _e('Index', 'life-travel-excursion'); ?></th>
                            <th><?php _e('État', 'life-travel-excursion'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($indexes_status['indexes'] as $index): ?>
                            <tr>
                                <td><?php echo esc_html($index['table']); ?></td>
                                <td><?php echo esc_html($index['field']); ?></td>
                                <td><?php echo esc_html($index['index_name']); ?></td>
                                <td>
                                    <?php if ($index['installed']): ?>
                                        <span style="color: green;">
                                            <?php _e('Installé', 'life-travel-excursion'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: red;">
                                            <?php _e('Non installé', 'life-travel-excursion'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px;">
                    <button id="install-indexes" class="button button-primary">
                        <?php _e('Installer/Optimiser les index', 'life-travel-excursion'); ?>
                    </button>
                    <span id="installation-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
                    <div id="installation-result" style="margin-top: 10px;"></div>
                </div>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Informations additionnelles', 'life-travel-excursion'); ?></h2>
                <p>
                    <?php _e('L\'installation des index peut améliorer considérablement les performances des requêtes de recherche de réservations. Ces optimisations sont particulièrement utiles dans les environnements à connectivité limitée.', 'life-travel-excursion'); ?>
                </p>
                <p>
                    <?php _e('Quelques détails techniques :', 'life-travel-excursion'); ?>
                </p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><?php _e('Les index créés accélèrent les requêtes de disponibilité des excursions', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Les recherches de réservations par date sont optimisées', 'life-travel-excursion'); ?></li>
                    <li><?php _e('L\'affichage des commandes et des rapports est plus rapide', 'life-travel-excursion'); ?></li>
                </ul>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#install-indexes').on('click', function() {
                var $button = $(this);
                var $spinner = $('#installation-spinner');
                var $result = $('#installation-result');
                
                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                $result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'life_travel_install_indexes',
                        nonce: '<?php echo wp_create_nonce('life_travel_install_indexes'); ?>'
                    },
                    success: function(response) {
                        $spinner.removeClass('is-active');
                        $button.prop('disabled', false);
                        
                        if (response.success) {
                            $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                            // Recharger la page après 2 secondes
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $spinner.removeClass('is-active');
                        $button.prop('disabled', false);
                        $result.html('<div class="notice notice-error inline"><p><?php _e('Une erreur est survenue pendant l\'installation des index.', 'life-travel-excursion'); ?></p></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Traite la requête AJAX pour installer les index
     */
    public function ajax_install_indexes() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_install_indexes')) {
            wp_send_json_error([
                'message' => __('Erreur de sécurité. Veuillez rafraîchir la page.', 'life-travel-excursion')
            ]);
            return;
        }
        
        // Vérifier les autorisations
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Vous n\'avez pas les droits suffisants pour effectuer cette action.', 'life-travel-excursion')
            ]);
            return;
        }
        
        // Installer les index
        $result = $this->install_all_indexes();
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => __('Les index ont été installés avec succès.', 'life-travel-excursion')
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(
                    __('Erreur lors de l\'installation des index : %s', 'life-travel-excursion'),
                    $result['error']
                )
            ]);
        }
    }
    
    /**
     * Vérifie l'état des index et affiche une notification si des optimisations sont recommandées
     */
    public function check_index_optimization() {
        // Ne vérifier que dans l'admin et pour les administrateurs
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        
        // Éviter les vérifications sur toutes les pages pour des raisons de performance
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['dashboard', 'woocommerce_page_wc-admin', 'woocommerce_page_wc-orders', 'plugins'])) {
            return;
        }
        
        // Vérifier si la notification a déjà été affichée récemment
        $last_check = get_option('life_travel_index_check_time', 0);
        if ((time() - $last_check) < DAY_IN_SECONDS) {
            return;
        }
        
        // Mettre à jour la date de dernière vérification
        update_option('life_travel_index_check_time', time());
        
        // Vérifier l'état des index
        $indexes_status = $this->get_indexes_status();
        
        if (!$indexes_status['all_installed']) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php _e('Optimisation Life Travel recommandée :', 'life-travel-excursion'); ?></strong>
                    <?php _e('Des optimisations de base de données sont disponibles pour améliorer les performances de Life Travel Excursion, particulièrement utiles dans le contexte camerounais.', 'life-travel-excursion'); ?>
                </p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=life-travel-db-optimization'); ?>" class="button button-primary">
                        <?php _e('Optimiser maintenant', 'life-travel-excursion'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Récupère l'état des index nécessaires
     * @return array État des index
     */
    private function get_indexes_status() {
        global $wpdb;
        
        $indexes_list = $this->get_required_indexes();
        $all_installed = true;
        
        foreach ($indexes_list as &$index) {
            // Vérifier si l'index existe déjà
            $index_exists = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW INDEX FROM %s WHERE Key_name = %s",
                    $index['table'],
                    $index['index_name']
                )
            );
            
            $index['installed'] = !empty($index_exists);
            
            if (!$index['installed']) {
                $all_installed = false;
            }
        }
        
        return [
            'all_installed' => $all_installed,
            'indexes' => $indexes_list
        ];
    }
    
    /**
     * Liste des index nécessaires pour optimiser les requêtes
     * @return array Liste des index à créer
     */
    private function get_required_indexes() {
        global $wpdb;
        
        return [
            [
                'table' => $wpdb->postmeta,
                'field' => '_booking_start_date',
                'index_name' => 'idx_booking_start_date'
            ],
            [
                'table' => $wpdb->postmeta,
                'field' => '_booking_end_date',
                'index_name' => 'idx_booking_end_date'
            ],
            [
                'table' => $wpdb->postmeta,
                'field' => '_booking_participants',
                'index_name' => 'idx_booking_participants'
            ],
            [
                'table' => $wpdb->postmeta,
                'field' => '_life_travel_booking_data',
                'index_name' => 'idx_life_travel_booking_data'
            ],
            [
                'table' => $wpdb->prefix . 'woocommerce_order_itemmeta',
                'field' => '_product_id',
                'index_name' => 'idx_product_id'
            ],
            [
                'table' => $wpdb->posts,
                'field' => 'post_type,post_status',
                'index_name' => 'idx_post_type_status'
            ]
        ];
    }
    
    /**
     * Installe tous les index nécessaires
     * @return array Résultat de l'opération
     */
    private function install_all_indexes() {
        global $wpdb;
        
        $indexes = $this->get_required_indexes();
        $result = [
            'success' => true,
            'error' => '',
            'installed' => []
        ];
        
        foreach ($indexes as $index) {
            try {
                // Vérifier si l'index existe déjà
                $index_exists = $wpdb->get_results(
                    $wpdb->prepare(
                        "SHOW INDEX FROM %s WHERE Key_name = %s",
                        $index['table'],
                        $index['index_name']
                    )
                );
                
                if (empty($index_exists)) {
                    // Adapter la requête en fonction du type de champ
                    if (strpos($index['field'], ',') !== false) {
                        // Index composé
                        $fields = explode(',', $index['field']);
                        $fields_sql = implode('`, `', $fields);
                        $wpdb->query("CREATE INDEX {$index['index_name']} ON {$index['table']} (`{$fields_sql}`)");
                    } else {
                        // Vérifier si c'est un champ de métadonnées
                        if (strpos($index['table'], 'meta') !== false) {
                            // Pour les tables de métadonnées, créer un index sur meta_key et meta_value
                            $wpdb->query("CREATE INDEX {$index['index_name']} ON {$index['table']} (meta_key(191), meta_value(191))");
                        } else {
                            // Index standard
                            $wpdb->query("CREATE INDEX {$index['index_name']} ON {$index['table']} (`{$index['field']}`)");
                        }
                    }
                    
                    $result['installed'][] = $index['index_name'];
                }
            } catch (Exception $e) {
                $result['success'] = false;
                $result['error'] = $e->getMessage();
                break;
            }
        }
        
        return $result;
    }
}

// Initialiser l'installateur
function life_travel_index_installer() {
    return Life_Travel_Index_Installer::get_instance();
}

// Démarrer l'installateur uniquement dans l'admin
if (is_admin()) {
    add_action('plugins_loaded', 'life_travel_index_installer', 20);
}
