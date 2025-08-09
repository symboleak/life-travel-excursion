<?php
/**
 * Life Travel - Outil de diagnostic des bridges
 *
 * Cet outil permet de diagnostiquer les problèmes liés aux bridges, notamment 
 * les dépendances circulaires, et d'afficher des informations détaillées sur l'état
 * du système de bridges. Conçu spécialement pour faciliter la maintenance dans 
 * le contexte camerounais avec des connexions réseau instables.
 *
 * @package Life Travel Excursion
 * @version 3.0.0
 */

defined('ABSPATH') || exit;

/**
 * Classe pour le diagnostic des bridges de Life Travel
 */
class Life_Travel_Bridges_Diagnostics {
    /**
     * Initialise l'outil de diagnostic et enregistre les hooks nécessaires
     */
    public static function init() {
        // N'activer le diagnostic que dans l'admin et pour les utilisateurs autorisés
        if (is_admin()) {
            // Ajouter une page d'outil de diagnostic
            add_action('admin_menu', [self::class, 'add_diagnostics_page']);
            
            // Ajouter un widget au tableau de bord pour un accès rapide
            add_action('wp_dashboard_setup', [self::class, 'add_dashboard_widget']);
            
            // Ajouter une action AJAX pour rafraîchir les données
            add_action('wp_ajax_life_travel_refresh_bridge_diagnostics', [self::class, 'ajax_refresh_diagnostics']);
        }
    }
    
    /**
     * Ajoute une page d'outils de diagnostic dans le menu admin
     */
    public static function add_diagnostics_page() {
        add_submenu_page(
            'tools.php',
            __('Diagnostic des bridges Life Travel', 'life-travel-excursion'),
            __('Diagnostic Life Travel', 'life-travel-excursion'),
            'manage_options',
            'life-travel-bridges-diagnostics',
            [self::class, 'render_diagnostics_page']
        );
    }
    
    /**
     * Ajoute un widget au tableau de bord pour un accès rapide
     */
    public static function add_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'life_travel_bridges_widget',
                __('État des bridges Life Travel', 'life-travel-excursion'),
                [self::class, 'render_dashboard_widget']
            );
        }
    }
    
    /**
     * Récupère et affiche les informations de diagnostic
     */
    public static function render_diagnostics_page() {
        // Vérifier les autorisations
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'life-travel-excursion'));
        }
        
        // Obtenir les données de diagnostic
        $diagnostics = self::get_diagnostic_data();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Diagnostic des bridges Life Travel', 'life-travel-excursion'); ?></h1>
            
            <p><?php _e('Cet outil permet de diagnostiquer les problèmes liés aux bridges du plugin Life Travel Excursion, en particulier les dépendances circulaires qui peuvent affecter les performances dans les réseaux camerounais.', 'life-travel-excursion'); ?></p>
            
            <div class="notice notice-info inline">
                <p><?php _e('Les bridges sont des composants modulaires qui permettent de charger des fonctionnalités spécifiques en fonction des besoins, tout en évitant les dépendances circulaires.', 'life-travel-excursion'); ?></p>
            </div>
            
            <button id="refresh-diagnostics" class="button button-primary">
                <?php _e('Rafraîchir les diagnostics', 'life-travel-excursion'); ?>
            </button>
            
            <div id="diagnostic-results">
                <?php self::render_diagnostic_results($diagnostics); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#refresh-diagnostics').on('click', function() {
                var $button = $(this);
                var $results = $('#diagnostic-results');
                
                $button.prop('disabled', true).text('<?php _e('Rafraîchissement en cours...', 'life-travel-excursion'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'life_travel_refresh_bridge_diagnostics',
                        nonce: '<?php echo wp_create_nonce('life_travel_refresh_bridge_diagnostics'); ?>'
                    },
                    success: function(response) {
                        $results.html(response);
                        $button.prop('disabled', false).text('<?php _e('Rafraîchir les diagnostics', 'life-travel-excursion'); ?>');
                    },
                    error: function() {
                        $results.html('<div class="notice notice-error"><p><?php _e('Erreur lors du rafraîchissement des diagnostics.', 'life-travel-excursion'); ?></p></div>');
                        $button.prop('disabled', false).text('<?php _e('Rafraîchir les diagnostics', 'life-travel-excursion'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Affiche un aperçu de l'état des bridges dans le widget du tableau de bord
     */
    public static function render_dashboard_widget() {
        $diagnostics = self::get_diagnostic_data();
        
        // Déterminer l'état global
        $has_circular_deps = !empty($diagnostics['circular_dependencies']);
        $all_bridges_loaded = empty($diagnostics['missing_bridges']);
        $global_status = $has_circular_deps ? 'danger' : ($all_bridges_loaded ? 'success' : 'warning');
        
        ?>
        <div class="life-travel-diagnostics-widget">
            <div class="status-indicator status-<?php echo $global_status; ?>">
                <?php if ($global_status === 'success'): ?>
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php _e('Tous les bridges sont correctement chargés', 'life-travel-excursion'); ?></span>
                <?php elseif ($global_status === 'warning'): ?>
                    <span class="dashicons dashicons-warning"></span>
                    <span><?php _e('Certains bridges ne sont pas chargés', 'life-travel-excursion'); ?></span>
                <?php else: ?>
                    <span class="dashicons dashicons-no"></span>
                    <span><?php _e('Dépendances circulaires détectées', 'life-travel-excursion'); ?></span>
                <?php endif; ?>
            </div>
            
            <p>
                <a href="<?php echo admin_url('tools.php?page=life-travel-bridges-diagnostics'); ?>">
                    <?php _e('Voir le diagnostic complet', 'life-travel-excursion'); ?>
                </a>
            </p>
        </div>
        
        <style>
            .life-travel-diagnostics-widget .status-indicator {
                padding: 10px;
                margin-bottom: 10px;
                border-radius: 3px;
            }
            .life-travel-diagnostics-widget .status-success {
                background-color: #edfaef;
                border-left: 4px solid #46b450;
            }
            .life-travel-diagnostics-widget .status-warning {
                background-color: #fff8e5;
                border-left: 4px solid #ffb900;
            }
            .life-travel-diagnostics-widget .status-danger {
                background-color: #fbeaea;
                border-left: 4px solid #dc3232;
            }
        </style>
        <?php
    }
    
    /**
     * Traite la requête AJAX pour rafraîchir les diagnostics
     */
    public static function ajax_refresh_diagnostics() {
        // Vérifier le nonce et les autorisations
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'life_travel_refresh_bridge_diagnostics')) {
            wp_send_json_error(__('Nonce invalide.', 'life-travel-excursion'));
            exit;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Autorisations insuffisantes.', 'life-travel-excursion'));
            exit;
        }
        
        // Obtenir des données fraîches
        $diagnostics = self::get_diagnostic_data(true);
        
        // Rendre les résultats
        ob_start();
        self::render_diagnostic_results($diagnostics);
        $html = ob_get_clean();
        
        echo $html;
        exit;
    }
    
    /**
     * Affiche les résultats du diagnostic sous forme de tableaux et d'alertes
     * 
     * @param array $diagnostics Données de diagnostic
     */
    private static function render_diagnostic_results($diagnostics) {
        // Style pour l'affichage
        ?>
        <style>
            .bridge-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            .bridge-table th, .bridge-table td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            .bridge-table th {
                background-color: #f5f5f5;
            }
            .bridge-table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .bridge-status {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                color: white;
                font-weight: bold;
            }
            .bridge-status-loaded {
                background-color: #46b450;
            }
            .bridge-status-missing {
                background-color: #dc3232;
            }
            .dependency-path {
                font-family: monospace;
                padding: 8px;
                margin: 5px 0;
                background-color: #f9f9f9;
                border-left: 4px solid #dc3232;
            }
        </style>
        
        <?php
        // 1. Afficher d'abord les dépendances circulaires (si présentes)
        if (!empty($diagnostics['circular_dependencies'])) {
            ?>
            <div class="notice notice-error">
                <h3><?php _e('⚠️ Dépendances circulaires détectées', 'life-travel-excursion'); ?></h3>
                <p><?php _e('Les dépendances circulaires peuvent causer des problèmes de performance et de stabilité, en particulier dans les environnements à connectivité limitée comme au Cameroun.', 'life-travel-excursion'); ?></p>
                
                <ul>
                    <?php foreach ($diagnostics['circular_dependencies'] as $cycle): ?>
                        <li class="dependency-path"><?php echo implode(' → ', array_map('esc_html', $cycle)); ?></li>
                    <?php endforeach; ?>
                </ul>
                
                <p>
                    <strong><?php _e('Solution recommandée:', 'life-travel-excursion'); ?></strong>
                    <?php _e('Utilisez le noyau central des bridges (bridges-core.php) pour résoudre ces dépendances circulaires.', 'life-travel-excursion'); ?>
                </p>
            </div>
            <?php
        } else {
            ?>
            <div class="notice notice-success">
                <p><?php _e('✅ Aucune dépendance circulaire détectée.', 'life-travel-excursion'); ?></p>
            </div>
            <?php
        }
        
        // 2. Tableau des bridges et leur statut
        ?>
        <h3><?php _e('Statut des bridges', 'life-travel-excursion'); ?></h3>
        
        <table class="bridge-table">
            <thead>
                <tr>
                    <th><?php _e('Bridge', 'life-travel-excursion'); ?></th>
                    <th><?php _e('Statut', 'life-travel-excursion'); ?></th>
                    <th><?php _e('Version', 'life-travel-excursion'); ?></th>
                    <th><?php _e('Fonctions', 'life-travel-excursion'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($diagnostics['bridges'] as $bridge => $data): ?>
                    <tr>
                        <td><?php echo esc_html($bridge); ?></td>
                        <td>
                            <?php if ($data['loaded']): ?>
                                <span class="bridge-status bridge-status-loaded"><?php _e('Chargé', 'life-travel-excursion'); ?></span>
                            <?php else: ?>
                                <span class="bridge-status bridge-status-missing"><?php _e('Non chargé', 'life-travel-excursion'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($data['version'] ?: __('N/A', 'life-travel-excursion')); ?></td>
                        <td>
                            <?php
                            if (!empty($data['functions'])) {
                                echo implode(', ', array_map('esc_html', $data['functions']));
                            } else {
                                _e('Aucune fonction déclarée', 'life-travel-excursion');
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
        // 3. Informations sur le système de chargement
        ?>
        <h3><?php _e('Système de chargement', 'life-travel-excursion'); ?></h3>
        
        <table class="bridge-table">
            <tr>
                <th><?php _e('Composant', 'life-travel-excursion'); ?></th>
                <th><?php _e('Statut', 'life-travel-excursion'); ?></th>
            </tr>
            <tr>
                <td><?php _e('Noyau central des bridges (bridges-core.php)', 'life-travel-excursion'); ?></td>
                <td>
                    <?php if ($diagnostics['core_available']): ?>
                        <span class="bridge-status bridge-status-loaded"><?php _e('Disponible', 'life-travel-excursion'); ?></span>
                    <?php else: ?>
                        <span class="bridge-status bridge-status-missing"><?php _e('Non disponible', 'life-travel-excursion'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><?php _e('Initialiseur des bridges (init-bridges.php)', 'life-travel-excursion'); ?></td>
                <td>
                    <?php if ($diagnostics['initializer_available']): ?>
                        <span class="bridge-status bridge-status-loaded"><?php _e('Disponible', 'life-travel-excursion'); ?></span>
                    <?php else: ?>
                        <span class="bridge-status bridge-status-missing"><?php _e('Non disponible', 'life-travel-excursion'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <?php
        // 4. Répertorier les bridges manquants si nécessaire
        if (!empty($diagnostics['missing_bridges'])) {
            ?>
            <h3><?php _e('Bridges manquants', 'life-travel-excursion'); ?></h3>
            <div class="notice notice-warning">
                <p><?php _e('Certains bridges requis ne sont pas chargés:', 'life-travel-excursion'); ?></p>
                <ul>
                    <?php foreach ($diagnostics['missing_bridges'] as $bridge): ?>
                        <li><?php echo esc_html($bridge); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        }
    }
    
    /**
     * Récupère les données de diagnostic des bridges
     * 
     * @param bool $force_refresh Forcer le rafraîchissement du cache
     * @return array Données de diagnostic
     */
    public static function get_diagnostic_data($force_refresh = false) {
        // Vérifier si les données sont en cache (sauf si on force le rafraîchissement)
        $cache_key = 'life_travel_bridges_diagnostics';
        $cached_data = get_transient($cache_key);
        
        if (!$force_refresh && $cached_data !== false) {
            return $cached_data;
        }
        
        // Initialiser les données de diagnostic
        $diagnostics = [
            'bridges' => [],
            'circular_dependencies' => [],
            'missing_bridges' => [],
            'core_available' => false,
            'initializer_available' => false
        ];
        
        // Vérifier la disponibilité du noyau et de l'initialiseur
        $diagnostics['core_available'] = class_exists('Life_Travel_Bridges_Core');
        $diagnostics['initializer_available'] = class_exists('Life_Travel_Bridge_Initializer');
        
        // Si le noyau central est disponible, utiliser ses fonctions
        if (function_exists('life_travel_bridges_core')) {
            $diagnostics['bridges'] = life_travel_bridges_core()->get_bridge_registry();
            $diagnostics['circular_dependencies'] = life_travel_bridges_core()->get_circular_dependencies();
            
            // Identifier les bridges manquants
            foreach ($diagnostics['bridges'] as $bridge => $data) {
                if (!$data['loaded']) {
                    $diagnostics['missing_bridges'][] = $bridge;
                }
            }
        } else if (function_exists('life_travel_bridge_initializer')) {
            // Fallback vers l'ancien système d'initialisation
            $initializer = life_travel_bridge_initializer();
            $status = $initializer->get_initialization_status();
            
            // Adapter les données au format attendu
            $bridges = [];
            $required_bridges = ['bridge-validator', 'images', 'offline', 'pwa'];
            
            foreach ($required_bridges as $bridge) {
                $bridge_file = ($bridge === 'bridge-validator') ? $bridge . '.php' : $bridge . '-bridge.php';
                $loaded = in_array($bridge_file, $status['loaded']);
                
                $bridges[$bridge] = [
                    'loaded' => $loaded,
                    'version' => '',
                    'functions' => []
                ];
                
                if (!$loaded) {
                    $diagnostics['missing_bridges'][] = $bridge;
                }
            }
            
            $diagnostics['bridges'] = $bridges;
            
            // Essayer d'obtenir les dépendances circulaires
            if (function_exists('life_travel_check_circular_dependencies')) {
                $diagnostics['circular_dependencies'] = life_travel_check_circular_dependencies();
            }
        } else {
            // Aucun système de bridges n'est disponible
            $required_bridges = ['bridge-validator', 'images', 'offline', 'pwa'];
            
            foreach ($required_bridges as $bridge) {
                $diagnostics['bridges'][$bridge] = [
                    'loaded' => false,
                    'version' => '',
                    'functions' => []
                ];
                
                $diagnostics['missing_bridges'][] = $bridge;
            }
        }
        
        // Mettre en cache les résultats pour 1 heure
        set_transient($cache_key, $diagnostics, HOUR_IN_SECONDS);
        
        return $diagnostics;
    }
}

// Initialiser l'outil de diagnostic
add_action('plugins_loaded', ['Life_Travel_Bridges_Diagnostics', 'init'], 20);
